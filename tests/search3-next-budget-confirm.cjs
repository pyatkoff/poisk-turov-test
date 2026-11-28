'use strict';

const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {createHash}=require('node:crypto'),{execFileSync}=require('node:child_process');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const {parse}=require('../scripts/build/search3-js/node_modules/acorn');

const installed='8310e93438491fe9569e3bd6ef4873342ed1563f';
const appSha='3578923bdb58e3f3e4201c3ea4ca7b1d9ff3df27db7e62ca53329d9d3fc3a16b';
const cssSha='0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const rawSha='dd48a58b3c083b3786a011ac7ee9aa3090fa0b786ac8dfb08b0eb47896beece8';
const dataFile='visual-search/fixtures/live-search-2026-09-23.json';
const digest=x=>createHash('sha256').update(x).digest('hex');
const today=new Date().toISOString().slice(0,10);
const future=days=>new Date(Date.parse(today+'T12:00:00Z')+days*86400000).toISOString().slice(0,10);
const params=new URLSearchParams({scenario:'family',searched:'1',origin:'Москва',country:'4',from:future(14),to:future(20),
 minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
const parts=['editFilterBudget','updateDrawerPreview','closeFilters','renderResults'];
const cap=220000;
const expectedAt=(rows,limit)=>rows.filter(h=>h.offers.some(x=>x.meal==='Всё включено'&&x.total>0&&x.total<=limit)).length;
function archivedInventory(raw){
 const names={'AI':'Всё включено','ALL':'Всё включено','ALL INCLUSIVE':'Всё включено','ВСЕ ВКЛЮЧЕНО':'Всё включено','ВСЁ ВКЛЮЧЕНО':'Всё включено'};
 const rows=[];
 for(const h of raw.hotels){
  if(![4,5].includes(h.stars))continue;
  const resort=h.location.replace(/, Турция$/,'');
  const rating=Number(String(h.rating||'').replace(',','.'));
  const offers=h.offers.map((o,variant)=>{
   const label=String(o.meal?.name||o.meal?.russianName||o.meal).trim();
   const meal=names[label.toLocaleUpperCase('ru-RU').replace(/\s+/g,' ')]||label;
   const offset=((Number(o.dates.match(/^\d+/)?.[0])||1)-1)%7;
   const total=Number(o.price.replace(/\D/g,''));
   return {key:o.key,hotelId:h.id,variant,provider:decodeURIComponent(o.key).split(':')[0],
    cached:true,raw:{selectionEnabled:false,listingPriceState:'snapshot'},offset,nights:7,meal,mealRaw:o.meal,
    room:o.room,operator:o.operator,flight:o.flight,total,flightChoiceId:null};
  });
  if(!offers.some(o=>o.meal==='Всё включено'&&o.total>0&&o.total<=240000))continue;
  rows.push({id:h.id,name:h.name,country:'4',resort,region:'',subRegion:'',stars:h.stars,
   rating:rating>0&&rating<=5?rating:null,photos:[],amenities:[],legacyIds:[],beach:null,family:false,spa:false,
   raw:{description:h.facts,arrival:resort},offers});
 }
 return rows;
}

function fixtureInBrowser({rows,searchPath}){
 if(location.pathname!==searchPath)return;
 const shift=(day,count)=>new Date(Date.parse(day+'T12:00:00Z')+count*86400000).toISOString().slice(0,10);
 const stats={packets:0,active:null,resultCalls:0};
 window.longResults=stats;
 let adapter;
 Object.defineProperty(window,'AnyTourPrototypeData',{configurable:true,get:()=>adapter,set(data){
  const props=Object.getOwnPropertyDescriptors(data);
  const original=props.search?.value;
  if(typeof original!=='function'){adapter=data;return;}
  props.search={...props.search,value:function(query,emit,...rest){
   stats.resultCalls++;
   return Reflect.apply(original,this,[query,event=>{
    if(event?.type!=='results')return emit(event);
    const hotels=rows.map(hotel=>({...hotel,offers:hotel.offers.map(({offset,...offer})=>{
     const day=shift(query.from,offset);
     return {...offer,search:structuredClone(query),origin:query.origin,day,returnDay:shift(day,offer.nights),
      adults:query.adults,ages:query.ages.slice()};
    })}));
    stats.packets++;
    return emit({...event,hotels});
   },...rest]);
  }};
  adapter=Object.freeze(Object.create(Object.getPrototypeOf(data),props));
 }});

}

function instrument(source){
 const entries=[];
 function visit(node){
  if(!node||typeof node!=='object')return;
  if(Array.isArray(node)){for(const item of node)visit(item);return;}
  if(node.type==='FunctionDeclaration'&&parts.includes(node.id?.name)){
   const key=JSON.stringify(node.id.name);
   entries.push([node.body.start+1,'const timing=window.budgetDiagnostic;const start=timing?.running?performance.now():0;try{']);
   entries.push([node.body.end-1,'}finally{if(start){const elapsed=performance.now()-start;const item=timing.parts['+key+']??={ms:0,calls:0};item.ms+=elapsed;item.calls++;}}']);
  }
  for(const item of Object.values(node))if(typeof item==='object')visit(item);
 }
 visit(parse(source,{ecmaVersion:'latest'}));
 assert.equal(entries.length,2*parts.length);
 for(const [at,text]of entries.sort((a,b)=>b[0]-a[0]))source=source.slice(0,at)+text+source.slice(at);
 return source;
}
function candidate(source){
 const changes=[
  ['function editFilterBudget(){','function editFilterBudget(closing=false){'],
  [' if(changed)filterEdited();else if(filterDraft)updateDrawerPreview();',' if(closing&&budget.valid)return budget;\n if(changed)filterEdited();else if(filterDraft)updateDrawerPreview();'],
  ['if(apply){budget=editFilterBudget();if(!budget.valid&&!adapt)','if(apply){budget=editFilterBudget(true);if(!budget.valid&&!adapt)']
 ];
 for(const [from,to] of changes){
  assert.equal(source.split(from).length,2);
  source=source.replace(from,to);
 }
 return source;
}
function assetsFor(base,app){
 const sha=digest(app),url=base.appUrl.replace(base.appSha.slice(0,12),sha.slice(0,12));
 const rewrite=html=>html.replace(base.appUrl,url);
 return {...base,code:Buffer.from(app),appSha:sha,appUrl:url,home:rewrite(base.home),search:rewrite(base.search)};
}
function listenForApply(){
 const stats=window.budgetDiagnostic={running:false,parts:{}};
 const panel=document.querySelector('#filter-panel'),target=document.querySelector('#apply-filters');
 let tracked;
 function capture(event){
  if(!event.isTrusted||!target.contains(event.target))return;
  tracked=event;
  stats.started=performance.now();
  stats.running=true;
  document.removeEventListener('click',capture,true);
 }
 function finish(event){
  if(event!==tracked)return;
  stats.dispatch=performance.now()-stats.started;
  stats.running=false;
  stats.closed=!panel.classList.contains('open');
  document.removeEventListener('click',finish);
  requestAnimationFrame(()=>requestAnimationFrame(()=>{stats.frame=performance.now()-stats.started;}));
 }
 document.addEventListener('click',capture,true);
 document.addEventListener('click',finish);
}
const openPanel=page=>page.locator('.drawer-trigger').tap();
const waitCount=(page,count)=>page.waitForFunction(n=>new RegExp('^'+n+' ').test(document.querySelector('#results-summary')?.textContent),count,{timeout:7000});
const getMaximum=page=>new URL(page.url()).searchParams.get('max');
const panelIsOpen=page=>page.locator('#filter-panel').evaluate(node=>node.classList.contains('open'));
const readPrices=page=>page.locator('#cards .hotel-card').evaluateAll(cards=>cards.map(node=>[node.id,node.querySelector('.starting-price strong')?.textContent]));
const dismiss=page=>page.locator('#filter-panel [data-action="close-filters"]').tap();
async function expectConfirmation(page,stillOpen){
 await page.waitForFunction(()=>window.budgetDiagnostic?.frame>0,{},{timeout:6000});
 const data=await page.evaluate(()=>window.budgetDiagnostic);
 assert.equal(data.closed,!stillOpen);
 return data;
}

async function exercise(page,rows,expected,savedCards){
 const max=page.locator('#max-price'),submit=page.locator('#apply-filters'),error=page.locator('#filter-budget-error');
 const readText=()=>max.inputValue();
 await openPanel(page);
 assert.equal(await readText(),String(cap),'reopening shows the applied numeric budget');
 await max.fill('200000');
 await dismiss(page);
 assert.equal(getMaximum(page),String(cap),'cancel keeps the last applied budget');
 await openPanel(page);
 assert.equal(await readText(),String(cap),'reopening after cancel shows the applied budget');
 await max.fill('20,5');
 assert.equal(await submit.isDisabled(),true);
 assert.equal(await max.getAttribute('aria-invalid'),'true');
 assert(await error.isVisible());
 assert.equal(getMaximum(page),String(cap));
 await dismiss(page);
 await openPanel(page);
 // A value set by an input method before it dispatches its validation event
 // must still be validated at the trusted confirmation click.
 await max.evaluate(input=>{input.value='20,5';});
 await page.evaluate(listenForApply);
 await submit.tap();
 const invalid=await expectConfirmation(page,true);
 assert.equal(await page.evaluate(()=>document.activeElement?.id),'max-price');
 assert(await error.isVisible());
 assert.equal(getMaximum(page),String(cap));
 assert.deepEqual(await readPrices(page),savedCards);

 await max.fill('230000');
 await page.setViewportSize({width:1280,height:720});
 await page.waitForFunction(()=>innerWidth===1280&&!document.querySelector('#filter-panel').classList.contains('open'));
 await waitCount(page,expectedAt(rows,230000));
 assert.equal(getMaximum(page),'230000','valid draft follows the existing resize behavior');
 await page.setViewportSize({width:430,height:932});
 await openPanel(page);
 assert.equal(await readText(),'230000');
 await max.fill('20,5');
 await page.setViewportSize({width:1280,height:720});
 await page.waitForFunction(()=>innerWidth===1280&&!document.querySelector('#filter-panel').classList.contains('open'));
 assert.equal(getMaximum(page),'230000','resize retains the previous valid applied budget');
 await page.setViewportSize({width:430,height:932});
 const retained=new URL(page.url());
 retained.searchParams.set('max',String(cap));
 await page.goto(retained.href,{waitUntil:'domcontentloaded'});
 await waitCount(page,expected);
 assert.deepEqual(await readPrices(page),savedCards,'applied budget in URL restores the same cards and prices');
 await openPanel(page);
 assert.equal(await readText(),String(cap));
 return {invalid:{dispatch:invalid.dispatch,frame:invalid.frame,parts:invalid.parts},cancelled:true,restored:true,resized:true};
}

async function runOne(browser,server,route,rows,cpu,full=false){
 const expected=expectedAt(rows,cap);
 assert(expected>0&&expected<rows.length);
 const context=await browser.newContext({viewport:{width:430,height:932},deviceScaleFactor:3,hasTouch:true,isMobile:true,
  reducedMotion:'reduce',serviceWorkers:'block',proxy:{server:server.origin,bypass:'127.0.0.1'}});
 const page=await context.newPage(),external=[],errors=[];
 page.on('request',request=>{const url=new URL(request.url());if(url.origin!==server.origin)external.push(url.pathname);});
 page.on('pageerror',error=>errors.push(error.message));
 try{
  const cdp=await context.newCDPSession(page);
  await cdp.send('Emulation.setCPUThrottlingRate',{rate:cpu});
  await page.addInitScript(fixtureInBrowser,{rows,searchPath:route});
  assert.equal((await page.goto(server.origin+route+'?'+params,{waitUntil:'domcontentloaded'})).status(),200);
  await waitCount(page,rows.length);
  await page.evaluate(()=>document.fonts.ready);
  assert.deepEqual(await page.evaluate(()=>[innerWidth,innerHeight,devicePixelRatio,navigator.maxTouchPoints>0]),[430,932,3,true]);
  await openPanel(page);
  const max=page.locator('#max-price'),submit=page.locator('#apply-filters');
  await max.fill('220 000');
  await max.blur();
  assert.equal(await submit.isEnabled(),true);
  assert.equal(getMaximum(page),'240000','the draft does not commit on input');
  await page.evaluate(listenForApply);
  await submit.tap();
  const metrics=await expectConfirmation(page,false);
  await waitCount(page,expected);
  assert.equal(getMaximum(page),String(cap));
  assert.equal(await page.evaluate(()=>window.longResults.resultCalls),1);
  const cards=await readPrices(page);
  assert.equal(cards.length,Math.min(expected,24));
  const demo=full?await exercise(page,rows,expected,cards):null;
  assert.deepEqual(external,[]);
  assert.deepEqual(errors,[]);
  assert.deepEqual(server.rejected,[]);
  return {expected,cards,metrics,demo};
 }finally{await context.close();}
}

function median(list){
 const sorted=list.slice().sort((a,b)=>a-b),mid=Math.floor(sorted.length/2);
 return sorted.length%2?sorted[mid]:(sorted[mid-1]+sorted[mid])/2;
}
function summarizePairs(pairs){
 return Object.fromEntries(['dispatch','frame'].map(key=>{
  const before=pairs.map(pair=>pair.before.metrics[key]),after=pairs.map(pair=>pair.after.metrics[key]);
  const delta=after.map((t,i)=>t-before[i]);
  return [key,{before:median(before),after:median(after),paired:median(delta),faster:delta.filter(value=>value<0).length,
   lower:Math.min(...delta),upper:Math.max(...delta)}];
 }));
}

module.exports=async function measureMobileBudgetConfirm(browser,nextHome,output){
 const base=exactAssets(nextHome),root=path.resolve(__dirname,'..');
 assert.equal(base.appSha,appSha);assert.equal(base.sheetSha,cssSha);
 const changed=execFileSync('git',['diff','--name-only','0612e2a49bc4f8b4b3de0cad1ca1a5d5eceb92cf','HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
 assert.deepEqual(changed,['tests/search3-entry-owner-browser.cjs','tests/search3-next-budget-confirm.cjs']);
 const source=execFileSync('git',['show',installed+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
 assert.equal(digest(source),rawSha);
 const fixture=fs.readFileSync(path.join(nextHome.payload,dataFile));
 const rows=archivedInventory(JSON.parse(fixture)).slice(0,100);
 assert.equal(rows.length,100);
 const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const measured=assetsFor(base,await compile(instrument(source),{privateFunctions:true}));
 const lab=await startServer(nextHome,measured,paths);
 const attribution=[];
 try{
  for(const cpu of [1,4]){
   const warm=await runOne(browser,lab,paths.search,rows,cpu,cpu===1);
   const samples=[];
   for(let i=0;i<4;i++)samples.push(await runOne(browser,lab,paths.search,rows,cpu));
   const edit=median(samples.map(x=>x.metrics.parts.editFilterBudget.ms));
   const preview=median(samples.map(x=>x.metrics.parts.updateDrawerPreview?.ms??0));
   const dispatch=median(samples.map(x=>x.metrics.dispatch));
   const frame=median(samples.map(x=>x.metrics.frame));
   assert(samples.every(x=>x.metrics.parts.editFilterBudget.calls===1));
   assert(samples.every(x=>x.metrics.parts.updateDrawerPreview.calls===1));
   attribution.push({cpu,edit,preview,dispatch,frame,warm,samples});
  }
 }finally{await lab.close();}
 const report={source:process.env.SEARCH3_SOURCE_SHA,baseline:installed,app:appSha,css:cssSha,fixture:digest(fixture),
  browser:browser.version(),size:[430,932],throttling:'Chromium CDP CPU slowdown, not a physical device',attribution,groups:[]};
 // Only run the alternative when an otherwise hidden intermediate render is
 // measurable at the slower interactive profile.
 if(attribution.some(row=>row.preview>=(row.cpu===1?3:8))){
  const raw=candidate(source),built=assetsFor(base,await compile(raw,{privateFunctions:true}));
  report.candidate={raw:digest(raw),app:built.appSha};
  const servers={before:await startServer(nextHome,base,paths),after:await startServer(nextHome,built,paths)};
  try{
   for(const cpu of [1,4]){
    const warm={before:await runOne(browser,servers.before,paths.search,rows,cpu,cpu===1),
     after:await runOne(browser,servers.after,paths.search,rows,cpu,cpu===1)};
    assert.deepEqual(warm.before.cards,warm.after.cards);
    const pairs=[];
    for(let i=0;i<6;i++){
     const order=i%2?['before','after']:['after','before'],pair={order};
     for(const mode of order)pair[mode]=await runOne(browser,servers[mode],paths.search,rows,cpu);
     assert.deepEqual(pair.before.cards,pair.after.cards);
     pairs.push(pair);
    }
    report.groups.push({cpu,warm,pairs,summary:summarizePairs(pairs)});
   }
  }finally{await Promise.all(Object.values(servers).map(server=>server.close()));}
 }
 console.log('SEARCH3_BUDGET_CONFIRM '+JSON.stringify({
  attribution:attribution.map(({cpu,edit,preview,dispatch,frame})=>({cpu,edit,preview,dispatch,frame})),
  groups:report.groups.map(({cpu,summary})=>({cpu,summary}))}));
 fs.writeFileSync(path.join(output,'canonical-next-budget-confirm.json'),JSON.stringify(report,null,2)+'\n');
};
