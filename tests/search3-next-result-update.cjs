'use strict';

const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {createHash}=require('node:crypto'),{execFileSync}=require('node:child_process');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const {parse}=require('../scripts/build/search3-js/node_modules/acorn');

const baseline='8310e93438491fe9569e3bd6ef4873342ed1563f';
const app='3578923bdb58e3f3e4201c3ea4ca7b1d9ff3df27db7e62ca53329d9d3fc3a16b';
const css='0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const expectedSource='dd48a58b3c083b3786a011ac7ee9aa3090fa0b786ac8dfb08b0eb47896beece8';
const digest=bytes=>createHash('sha256').update(bytes).digest('hex');
const snapshot='visual-search/fixtures/live-search-2026-09-23.json';
const cap=220000;
const firstDay=new Date().toISOString().slice(0,10);
const dateAfter=days=>new Date(Date.parse(firstDay+'T12:00Z')+days*86400000).toISOString().slice(0,10);
const params=new URLSearchParams({scenario:'family',searched:'1',origin:'Москва',country:'4',from:dateAfter(14),to:dateAfter(20),
 minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
const setup=[{width:430,height:932,dpr:3,touch:true},{width:1280,height:720,dpr:1,touch:false}];
const names=["closeFilters","editFilterBudget","syncFilters","renderResults","resultInventory","sortResultItems","renderResultHeadings","renderResultCards","cardHTML","paintResultCards","renderCalendarStrip","prepareResultCalendarBase","resultCalendarModel","syncResultCalendarDisclosure","refreshResultFilters","renderActive","renderSummary","renderSearchStatus","updateSearchUI","refreshResultPickerPreviews","syncDestinationViewport","updateURL","updateCompactSearch","countFacetOptionGroups","countFacetOptions"];
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
 const sites=[];
 function visit(node){
  if(!node||typeof node!=='object')return;
  if(Array.isArray(node)){for(const n of node)visit(n);return;}
  if(node.type==='FunctionDeclaration'&&names.includes(node.id?.name)){
   const name=JSON.stringify(node.id.name);
   sites.push([node.body.start+1,'const phases=window.resultUpdatePhases;const period=phases?.enter('+name+');try{']);
   sites.push([node.body.end-1,'}finally{if(period)phases.exit(period);}']);
  }
  for(const item of Object.values(node))if(typeof item==='object')visit(item);
 }
 visit(parse(source,{ecmaVersion:'latest'}));
 assert.equal(sites.length,names.length*2,'each named phase exists exactly once');
 for(const [at,text]of sites.sort((a,b)=>b[0]-a[0]))source=source.slice(0,at)+text+source.slice(at);
 return source;
}
function record({target,trigger}){
 const initial=document.querySelectorAll('#cards .hotel-card');
 const cards=list=>[...list].map(node=>{
  const price=node.querySelector('.starting-price strong')?.textContent;
  const key=node.querySelector('[data-card-entry]')?.dataset.key??null;
  return {node,id:node.id,price,key};
 });
 const before=cards(initial);
 let event;
 const stats=window.resultUpdatePhases={recording:false,stack:[],owners:{},enter(name){
  if(!this.recording)return null;
  const phase={name,start:performance.now(),children:0};
  this.stack.push(phase);
  return phase;
 },exit(phase){
  const elapsed=performance.now()-phase.start;
  if(this.stack.pop()!==phase)throw Error('unexpected synchronous profile tree');
  const row=this.owners[phase.name]??={calls:0,total:0,self:0};
  row.calls++;row.total+=elapsed;row.self+=elapsed-phase.children;
  if(this.stack.length)this.stack.at(-1).children+=elapsed;
 }};
 function start(e){
  if(!e.isTrusted||!e.target.closest(target))return;
  event=e;
  stats.recording=true;
  stats.start=performance.now();
  document.removeEventListener(trigger,start,true);
 }
 function finish(e){
  if(e!==event)return;
  stats.recording=false;
  stats.dispatch=performance.now()-stats.start;
  document.removeEventListener(trigger,finish);
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
   const after=cards(document.querySelectorAll('#cards .hotel-card'));
   const previous=new Map(before.map(node=>[node.id,node]));
   const overlap=after.filter(node=>previous.has(node.id));
   stats.cards={before:before.length,after:after.length,overlap:overlap.length,
    retained:overlap.filter(item=>previous.get(item.id).node===item.node).length,
    samePrice:overlap.filter(item=>previous.get(item.id).price===item.price).length,
    sameOffer:overlap.filter(item=>previous.get(item.id).key===item.key).length};
   stats.frame=performance.now()-stats.start;
  }));
 }
 document.addEventListener(trigger,start,true);
 document.addEventListener(trigger,finish);
}
const waitCount=(page,n)=>page.waitForFunction(count=>new RegExp('^'+count+' ').test(document.querySelector('#results-summary')?.textContent),n,{timeout:9000});
async function bench(browser,server,route,rows,screen,cpu){
 const count=rows.filter(h=>h.offers.some(o=>o.meal==='Всё включено'&&o.total>0&&o.total<=cap)).length;
 assert(count>0&&count<rows.length);
 const context=await browser.newContext({viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,
  hasTouch:screen.touch,isMobile:screen.touch,reducedMotion:'reduce',serviceWorkers:'block',
  proxy:{server:server.origin,bypass:'127.0.0.1'}});
 const page=await context.newPage(),unexpected=[],errors=[];
 page.on('request',request=>{const url=new URL(request.url());if(url.origin!==server.origin)unexpected.push(url.pathname);});
 page.on('pageerror',error=>errors.push(error.message));
 try{
  const client=await context.newCDPSession(page);
  await client.send('Emulation.setCPUThrottlingRate',{rate:cpu});
  await client.send('Performance.enable');
  await page.addInitScript(fixtureInBrowser,{rows,searchPath:route});
  assert.equal((await page.goto(server.origin+route+'?'+params,{waitUntil:'domcontentloaded'})).status(),200);
  await waitCount(page,rows.length);
  await page.evaluate(()=>document.fonts.ready);
  await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  assert.deepEqual(await page.evaluate(()=>[innerWidth,innerHeight,devicePixelRatio,navigator.maxTouchPoints>0]),
   [screen.width,screen.height,screen.dpr,screen.touch]);
  const input=page.locator('#max-price'), readMetrics=async()=>Object.fromEntries((await client.send('Performance.getMetrics')).metrics.map(x=>[x.name,x.value]));
  if(screen.touch){
   await page.locator('.drawer-trigger').tap();
   await input.fill(String(cap));
   await input.blur();
   assert(await page.locator('#apply-filters').isEnabled());
  }
  assert.equal(new URL(page.url()).searchParams.get('max'),'240000');
  await page.evaluate(record,{target:screen.touch?'#apply-filters':'#max-price',trigger:screen.touch?'click':'input'});
  const before=await readMetrics();
  if(screen.touch)await page.locator('#apply-filters').tap();
  else await input.fill(String(cap));
  await page.waitForFunction(()=>window.resultUpdatePhases?.frame>0,{},{timeout:5000});
  await waitCount(page,count);
  const after=await readMetrics();
  assert.equal(new URL(page.url()).searchParams.get('max'),String(cap));
  assert.deepEqual(unexpected,[]);assert.deepEqual(errors,[]);assert.deepEqual(server.rejected,[]);
  const result=await page.evaluate(()=>{
   const {dispatch,frame,owners,cards}=window.resultUpdatePhases;
   return {dispatch,frame,owners,cards,calls:window.longResults.resultCalls};
  });
  assert.equal(result.calls,1);
  assert.equal(result.cards.before,24);assert.equal(result.cards.after,Math.min(count,24));
  assert.equal(result.owners.renderResults.calls,1);
  assert.equal(result.owners.resultInventory.calls,1);
  return {...result,count,engine:Object.fromEntries(['LayoutDuration','RecalcStyleDuration','ScriptDuration','TaskDuration'].map(k=>{
   assert(Number.isFinite(before[k])&&Number.isFinite(after[k]),k);return [k,(after[k]-before[k])*1000];
  }))};
 }finally{await context.close();}
}
function middle(values){
 const s=values.slice().sort((a,b)=>a-b),n=Math.floor(s.length/2);
 return s.length%2?s[n]:(s[n-1]+s[n])/2;
}
function summarize(samples){
 const phase=Object.fromEntries(names.map(name=>[name,Object.fromEntries(['calls','total','self'].map(key=>[key,middle(samples.map(p=>p.owners[name]?.[key]??0))]))]));
 const engine=Object.fromEntries(Object.keys(samples[0].engine).map(key=>[key,middle(samples.map(p=>p.engine[key]))]));
 return {dispatch:middle(samples.map(p=>p.dispatch)),frame:middle(samples.map(p=>p.frame)),cards:samples[0].cards,phase,engine};
}
module.exports=async function profileResultUpdate(browser,nextHome,output){
 const exact=exactAssets(nextHome);
 assert.equal(exact.appSha,app);assert.equal(exact.sheetSha,css);
 const root=path.resolve(__dirname,'..');
 const touched=execFileSync('git',['diff','--name-only','0612e2a49bc4f8b4b3de0cad1ca1a5d5eceb92cf','HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
 assert.deepEqual(touched,['tests/search3-entry-owner-browser.cjs','tests/search3-next-result-update.cjs']);
 const original=execFileSync('git',['show',baseline+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
 assert.equal(digest(original),expectedSource);
 const code=await compile(instrument(original),{privateFunctions:true}),appSha=digest(code);
 const appUrl=exact.appUrl.replace(app.slice(0,12),appSha.slice(0,12));
 const rewrite=html=>html.replace(exact.appUrl,appUrl);
 const assets={...exact,code:Buffer.from(code),appSha,appUrl,home:rewrite(exact.home),search:rewrite(exact.search)};
 const saved=fs.readFileSync(path.join(nextHome.payload,snapshot));
 const source=archivedInventory(JSON.parse(saved));assert.equal(source.length,215);
 const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const server=await startServer(nextHome,assets,paths),groups=[];
 try{
  for(const screen of setup)for(const cpu of [1,4])for(const count of [24,100]){
   const rows=source.slice(0,count);
   const warm=await bench(browser,server,paths.search,rows,screen,cpu);
   const samples=[];
   for(let i=0;i<4;i++)samples.push(await bench(browser,server,paths.search,rows,screen,cpu));
   const summary=summarize(samples);
   console.log('SEARCH3_RESULT_UPDATE '+JSON.stringify({screen,cpu,count,summary}));
   groups.push({screen,cpu,count,summary,warm,samples});
  }
  fs.writeFileSync(path.join(output,'canonical-next-result-update.json'),JSON.stringify({source:process.env.SEARCH3_SOURCE_SHA,
   baseline,app,css,appSha,browser:browser.version(),fixture:digest(saved),groups},null,2)+'\n');
 }finally{await server.close();}
};
