'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {createHash}=require('node:crypto'),{execFileSync}=require('node:child_process');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const base='8310e93438491fe9569e3bd6ef4873342ed1563f';
const baselineApp='3578923bdb58e3f3e4201c3ea4ca7b1d9ff3df27db7e62ca53329d9d3fc3a16b';
const sheet='0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const snapshot='visual-search/fixtures/live-search-2026-09-23.json';
const dateKey=new Date().toISOString().slice(0,10);
const future=days=>new Date(Date.parse(dateKey+'T12:00Z')+days*86400000).toISOString().slice(0,10);
const digest=x=>createHash('sha256').update(x).digest('hex');
const screens=[{width:1280,height:720,dpr:1,touch:false},{width:430,height:932,dpr:3,touch:true}];
const target=220000,small=128023;
const query=new URLSearchParams({scenario:'family',searched:'1',origin:'Москва',country:'4',from:future(14),to:future(20),
 minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
const rawCandidate="d74a97afb5f62abc2b552257d13748ea80b5618b97f50ee5ae87a58b875d1f80",compiledCandidate="e4bacb7e5bd770a4d876f427eb9497845e889001201d9d8320fef8bb2c338126",patches=[["function renderResultCards(items,{selectionOnly=false}={}){","function renderResultCards(items,{selectionOnly=false,reuseFilteredCards=false}={}){"],[" paintResultCards(cards,entries,sameCardScope);"," if(reuseFilteredCards&&!sameCardScope){\n  const visibleIds=new Set(entries.map(entry=>entry.id));\n  for(const card of [...cards.children])if(card.id&&!visibleIds.has(card.id))card.remove();\n }\n paintResultCards(cards,entries,sameCardScope||reuseFilteredCards);"],[" renderedResultSource={hotels,items:inventory.items};renderedResultItems=items;"," const reuseFilteredCards=options.reuseFilteredCards===true&&renderedResultSource?.hotels===hotels;\n renderedResultSource={hotels,items:inventory.items};renderedResultItems=items;"],[" renderResultHeadings(items,total,pristine);\n renderResultCards(items);"," renderResultHeadings(items,total,pristine);\n if(reuseFilteredCards)renderResultCards(items,{reuseFilteredCards});else renderResultCards(items);"],["function syncFilters(){renderResults();updateSearchUI();}","function syncFilters(options){renderResults(options);updateSearchUI();}"],["if(rebuild)syncFilters();else{renderResults({keepFilters:true});updateSearchUI()}settleFilterRoots","if(rebuild)syncFilters({reuseFilteredCards:true});else{renderResults({keepFilters:true,reuseFilteredCards:true});updateSearchUI()}settleFilterRoots"],["state.selectedDate=next.selectedDate;syncFilters();if(budget","state.selectedDate=next.selectedDate;syncFilters({reuseFilteredCards:true});if(budget"]];
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



function measure({trigger,selector}){
 const timings=window.compactBudgetMeasurement={};
 let selected;
 const initial=new Map([...document.querySelectorAll('#cards .hotel-card')].map(node=>[node.id,node]));
 const removed=new Set();
 const collect=records=>{
  for(const mutation of records)for(const node of mutation.removedNodes)if(initial.get(node.id)===node)removed.add(node);
 };
 const observer=new MutationObserver(collect);
 function begin(e){
  if(!e.isTrusted||!e.target.closest(selector))return;
  selected=e;
  observer.observe(document.querySelector('#cards'),{childList:true});
  timings.started=performance.now();
  document.removeEventListener(trigger,begin,true);
 }
 function end(e){
  if(e!==selected)return;
  timings.dispatch=performance.now()-timings.started;
  document.removeEventListener(trigger,end);
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
   timings.frame=performance.now()-timings.started;
   collect(observer.takeRecords());
   observer.disconnect();
   const retained=[...document.querySelectorAll('#cards .hotel-card')].filter(node=>initial.get(node.id)===node);
   timings.dom={retained:retained.length,removed:removed.size,moved:retained.filter(node=>removed.has(node)).length};
  }));
 }
 document.addEventListener(trigger,begin,true);
 document.addEventListener(trigger,end);
}
const waitFrame=page=>page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
const waitCount=(page,count)=>page.waitForFunction(n=>new RegExp('^'+n+' ').test(document.querySelector('#results-summary')?.textContent),count,{timeout:9000});
const maxParam=page=>Number(new URL(page.url()).searchParams.get('max'));
const readCards=page=>page.locator('#cards .hotel-card').evaluateAll(cards=>cards.map(node=>[node.id,node.querySelector('.starting-price strong')?.textContent,node.querySelector('[data-card-entry]')?.dataset.key??null]));
const geometry=page=>page.evaluate(()=>{
 const bar=document.querySelector('#compact-search'),form=document.querySelector('#search-form'),search=document.querySelector('#search');
 return {width:innerWidth,height:innerHeight,scroll:scrollY,bodyHeight:document.documentElement.scrollHeight,
  top:document.querySelector('#results').getBoundingClientRect().top,searchBottom:search.getBoundingClientRect().bottom,
  hidden:bar.hidden,fixed:bar.getBoundingClientRect().top,formHidden:form.hidden,clearance:document.documentElement.style.getPropertyValue('--compact-search-clearance'),
  barHeight:bar.hidden?0:bar.offsetHeight};
});
function checkBar(state){
 const mobile=state.width<=760&&state.formHidden;
 assert.equal(state.hidden,!mobile&&state.searchBottom>88);
 assert.equal(state.clearance,state.hidden?'':(state.barHeight+8)+'px');
}

async function guard(page,screen){
 const input=page.locator('#max-price');
 if(screen.touch){
  checkBar(await geometry(page));
  return {narrow:true,after:await geometry(page)};
 }
 await page.evaluate(()=>window.scrollTo({top:document.documentElement.scrollHeight,behavior:'instant'}));
 await input.scrollIntoViewIfNeeded();
 await waitFrame(page);
 const before=await geometry(page);
 assert(before.scroll>0&&before.searchBottom<88);
 assert.equal(before.formHidden,true);checkBar(before);
 await input.fill(String(small));
 await waitCount(page,5);
 assert.equal(maxParam(page),small);
 await waitFrame(page);
 const after=await geometry(page);assert(after.bodyHeight<before.bodyHeight);
 checkBar(after);
 const matched=await readCards(page);
 await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
 await waitFrame(page);
 const top=await geometry(page);
 assert.equal(top.scroll,0);checkBar(top);

 await page.setViewportSize({width:430,height:932});
 await page.waitForFunction(()=>innerWidth===430);
 await waitFrame(page);
 const narrow=await geometry(page);
 assert.equal(narrow.hidden,false);checkBar(narrow);
 await page.setViewportSize({width:1280,height:720});
 await page.waitForFunction(()=>innerWidth===1280);
 await waitFrame(page);
 const wide=await geometry(page);
 checkBar(wide);
 const edit=page.locator('#applied-search [data-action="edit-search"]');
 await edit.click();
 assert.equal(await page.locator('#search-form').isVisible(),true);
 await waitFrame(page);
 checkBar(await geometry(page));
 return {scrolled:before.scroll,newScroll:after.scroll,shrank:true,small,matched,wide:wide.hidden,reopened:true};
}

async function sample(browser,server,route,rows,screen,cpu,exercise=false){
 const context=await browser.newContext({viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,
  hasTouch:screen.touch,isMobile:screen.touch,reducedMotion:'reduce',serviceWorkers:'block',
  proxy:{server:server.origin,bypass:'127.0.0.1'}});
 const page=await context.newPage(),external=[],errors=[];
 page.on('request',request=>{const url=new URL(request.url());if(url.origin!==server.origin)external.push(url.pathname);});
 page.on('pageerror',error=>errors.push(error.message));
 try{
  const client=await context.newCDPSession(page);
  await client.send('Emulation.setCPUThrottlingRate',{rate:cpu});
  await client.send('Performance.enable');
  await page.addInitScript(fixtureInBrowser,{rows,searchPath:route});
  assert.equal((await page.goto(server.origin+route+'?'+query,{waitUntil:'domcontentloaded'})).status(),200);
  await waitCount(page,100);await page.evaluate(()=>document.fonts.ready);await waitFrame(page);
  assert.deepEqual(await page.evaluate(()=>[innerWidth,innerHeight,devicePixelRatio,navigator.maxTouchPoints>0]),
   [screen.width,screen.height,screen.dpr,screen.touch]);
  const input=page.locator('#max-price');
  if(screen.touch){
   await page.locator('.drawer-trigger').tap();
   await input.fill(String(target));await input.blur();
  }else{
   await input.scrollIntoViewIfNeeded();
   assert.equal(await page.evaluate(()=>scrollY),0,'the initial desktop budget input is within the unscrolled viewport');
  }
  const read=async()=>Object.fromEntries((await client.send('Performance.getMetrics')).metrics.map(x=>[x.name,x.value]));
  assert.equal(maxParam(page),240000);
  await page.evaluate(measure,{selector:screen.touch?'#apply-filters':'#max-price',trigger:screen.touch?'click':'input'});
  const before=await read();
  if(screen.touch)await page.locator('#apply-filters').tap();
  else await input.fill(String(target));
  await page.waitForFunction(()=>window.compactBudgetMeasurement?.frame>0,{},{timeout:7000});
  await waitCount(page,94);
  const after=await read();
  const {dispatch,frame,dom}=await page.evaluate(()=>window.compactBudgetMeasurement);
  assert.equal(maxParam(page),target);
  assert.equal(await page.evaluate(()=>window.longResults.resultCalls),1);
  const cards=await readCards(page);
  assert.equal(cards.length,24);
  assert(cards.every(row=>row[1]));
  checkBar(await geometry(page));
  const extra=exercise?await guard(page,screen):null;
  assert.deepEqual(external,[]);assert.deepEqual(errors,[]);assert.deepEqual(server.rejected,[]);
  return {dispatch,frame,dom,cards,extra,layout:(after.LayoutDuration-before.LayoutDuration)*1000,style:(after.RecalcStyleDuration-before.RecalcStyleDuration)*1000};
 }finally{await context.close();}
}
function median(items){
 const sorted=items.slice().sort((a,b)=>a-b),mid=Math.floor(sorted.length/2);
 return sorted.length%2?sorted[mid]:(sorted[mid-1]+sorted[mid])/2;
}
function summarize(pairs){
 return Object.fromEntries(['dispatch','frame','layout','style'].map(key=>{
  const a=pairs.map(p=>p.before[key]),b=pairs.map(p=>p.after[key]),delta=b.map((value,i)=>value-a[i]);
  return [key,{before:median(a),after:median(b),paired:median(delta),faster:delta.filter(x=>x<0).length,
   lower:Math.min(...delta),upper:Math.max(...delta)}];
 }));
}
module.exports=async function compareBudgetGeometry(browser,nextHome,output){
 const exact=exactAssets(nextHome),root=path.resolve(__dirname,'..');
 assert.equal(exact.appSha,baselineApp);assert.equal(exact.sheetSha,sheet);
 assert.deepEqual(execFileSync('git',['diff','--name-only','0612e2a49bc4f8b4b3de0cad1ca1a5d5eceb92cf','HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort(),
  ['tests/search3-entry-owner-browser.cjs','tests/search3-next-budget-pruned-cards.cjs']);
 const source=execFileSync('git',['show',base+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
 assert.equal(digest(source),'dd48a58b3c083b3786a011ac7ee9aa3090fa0b786ac8dfb08b0eb47896beece8');
 let candidate=source;
 for(const [from,to]of patches){assert.equal(candidate.split(from).length,2);candidate=candidate.replace(from,to);}
 assert.equal(digest(candidate),rawCandidate);
 const code=await compile(candidate,{privateFunctions:true}),sha=digest(code);
 assert.equal(sha,compiledCandidate);
 const appUrl=exact.appUrl.replace(baselineApp.slice(0,12),sha.slice(0,12)),replace=html=>html.replace(exact.appUrl,appUrl);
 const updated={...exact,code:Buffer.from(code),appSha:sha,appUrl,home:replace(exact.home),search:replace(exact.search)};
 const saved=fs.readFileSync(path.join(nextHome.payload,snapshot)),rows=archivedInventory(JSON.parse(saved)).slice(0,100);
 assert.equal(rows.length,100);
 const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const servers={before:await startServer(nextHome,exact,paths),after:await startServer(nextHome,updated,paths)};
 const groups=[];
 try{
  for(const screen of screens)for(const cpu of [1,4]){
   async function visit(mode,full){return sample(browser,servers[mode],paths.search,rows,screen,cpu,full);}
   const warm={before:await visit('before',cpu===1),after:await visit('after',cpu===1)};
   assert.deepEqual(warm.before.cards,warm.after.cards);
   if(warm.before.extra?.matched)assert.deepEqual(warm.before.extra.matched,warm.after.extra.matched);
   const pairs=[];
   for(let i=0;i<6;i++){
    const order=i%2?['before','after']:['after','before'],pair={order};
    for(const mode of order)pair[mode]=await visit(mode,false);
    assert.deepEqual(pair.before.cards,pair.after.cards);
    assert.deepEqual(pair.before.dom,{retained:0,removed:24,moved:0});
    assert.deepEqual(pair.after.dom,{retained:21,removed:3,moved:0});
    pairs.push(pair);
   }
   const summary=summarize(pairs);
   console.log('SEARCH3_BUDGET_PRUNED_CARDS '+JSON.stringify({screen,cpu,summary}));
   groups.push({screen,cpu,summary,warm,pairs});
  }
  fs.writeFileSync(path.join(output,'canonical-next-budget-pruned-cards.json'),JSON.stringify({
   source:process.env.SEARCH3_SOURCE_SHA,base,beforeApp:baselineApp,afterApp:sha,css:sheet,browser:browser.version(),fixture:digest(saved),groups
  },null,2)+'\n');
 }finally{await Promise.all(Object.values(servers).map(server=>server.close()));}
};
