'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {createHash}=require('node:crypto');
const {execFileSync}=require('node:child_process');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');

const installed='8310e93438491fe9569e3bd6ef4873342ed1563f';
const appliedApp='3578923bdb58e3f3e4201c3ea4ca7b1d9ff3df27db7e62ca53329d9d3fc3a16b';
const appliedSheet='0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const snapshot='visual-search/fixtures/live-search-2026-09-23.json';
const screens=[{width:430,height:932,dpr:3,mobile:true},{width:1280,height:720,dpr:1,mobile:false}];
const resultSelector='#cards .hotel-card';
const starsSelector='#filters [data-action="star"][data-value="4"]';
const appendSelector='#cards button[data-action="more-cards"]';
const milliseconds=()=>Number(process.hrtime.bigint()/1_000_000n);
const shift=(day,count)=>new Date(Date.parse(day+'T12:00:00Z')+count*86400000).toISOString().slice(0,10);
const fixedDay=new Date().toISOString().slice(0,10);
const dateKey=day=>shift(fixedDay,day);
const digest=bytes=>createHash('sha256').update(bytes).digest('hex');
const inventoryMarker='ratingCounts:{total:0,rating:0}';

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

 function timeDOM(callback){
  const rec=stats.active;
  if(!rec||!rec.started||rec.ended)return callback();
  const start=performance.now();
  try{return callback();}
  finally{rec.domMs+=performance.now()-start;rec.domCalls++;}
 }
 const proto=Element.prototype;
 const setter=Object.getOwnPropertyDescriptor(proto,'innerHTML');
 Object.defineProperty(proto,'innerHTML',{...setter,set(markup){
  if(this.id!=='cards'&&(this.tagName!=='TEMPLATE'||typeof markup!=='string'||!markup.startsWith('<article class="hotel-card')))return setter.set.call(this,markup);
  return timeDOM(()=>setter.set.call(this,markup));
 }});
 for(const [owner,name] of [[proto,'append'],[Node.prototype,'insertBefore'],[Node.prototype,'replaceChild']]){
  const original=owner[name];
  Object.defineProperty(owner,name,{...Object.getOwnPropertyDescriptor(owner,name),value:function(...args){
   return this.id==='cards'?timeDOM(()=>original.apply(this,args)):original.apply(this,args);
  }});
 }
}

function observeInteraction(selector){
 const stats=window.longResults, cards=document.querySelector('#cards'),filters=document.querySelector('#filter-panel');
 const rec={domMs:0,domCalls:0,addedCards:0,removedCards:0,filterMutations:0};
 stats.active=rec;
 const change=records=>{
  for(const item of records){
   if(item.target===cards){
    rec.addedCards+=Array.from(item.addedNodes).filter(node=>node.matches?.('.hotel-card')).length;
    rec.removedCards+=Array.from(item.removedNodes).filter(node=>node.matches?.('.hotel-card')).length;
   }else rec.filterMutations++;
  }
 };
 let observedClick;
 const observer=new MutationObserver(change);
 observer.observe(cards,{childList:true});
 observer.observe(filters,{childList:true,subtree:true,attributes:true,characterData:true});
 function capture(event){
  if(!event.isTrusted||!event.target.closest(selector))return;
  observedClick=event;
  rec.started=performance.now();
  document.removeEventListener('click',capture,true);
 }
 function settled(event){
  if(event!==observedClick)return;
  rec.ended=performance.now();
  document.removeEventListener('click',settled);
  queueMicrotask(()=>{
   change(observer.takeRecords());
   rec.afterMutations=performance.now();
   observer.disconnect();
   requestAnimationFrame(()=>requestAnimationFrame(()=>{rec.afterFrame=performance.now();}));
  });
 }
 document.addEventListener('click',capture,true);
 document.addEventListener('click',settled);
}

function inventoryCalls(coverage,appUrl,source){
 const app=coverage.result.find(entry=>new URL(entry.url||'about:blank').pathname.endsWith('/visual-search/app.js')&&entry.url.endsWith(appUrl));
 if(!app)return 0;
 const offset=source.indexOf(inventoryMarker);
 const own=app.functions.map(fn=>fn.ranges[0]).filter(range=>range.startOffset<=offset&&offset<range.endOffset)
  .sort((a,b)=>(a.endOffset-a.startOffset)-(b.endOffset-b.startOffset))[0];
 return own?.count||0;
}

async function interaction(page,screen,selector,label,profiler,sourceApp){
 const action=page.locator(selector);
 await action.scrollIntoViewIfNeeded();
 if(profiler)await profiler.send('Profiler.takePreciseCoverage');
 await page.evaluate(observeInteraction,selector);
 await (screen.mobile?action.tap():action.click());
 await page.waitForFunction(()=>window.longResults.active?.afterFrame>0,{},{timeout:4000});
 const measured=await page.evaluate(()=>{
  const {started,ended,afterMutations,afterFrame,domMs,domCalls,addedCards,removedCards,filterMutations}=window.longResults.active;
  return {dispatch:ended-started,mutations:afterMutations-started,frame:afterFrame-started,
   domMs,domCalls,addedCards,removedCards,filterMutations};
 });
 if(profiler)measured.recomputes=inventoryCalls(await profiler.send('Profiler.takePreciseCoverage'),sourceApp.appUrl,sourceApp.code.toString('utf8'));
 return {label,...measured};
}

async function verifySample(browser,server,fixture,paths,sourceApp,screen,collectCalls,actionType='filters'){
 const context=await browser.newContext({viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,
  hasTouch:screen.mobile,isMobile:screen.mobile,reducedMotion:'reduce',serviceWorkers:'block',
  proxy:{server:server.origin,bypass:'127.0.0.1'}});
 const page=await context.newPage();
 const external=[],errors=[];
 page.on('request',request=>{const url=new URL(request.url());if(url.origin!==server.origin)external.push(url.pathname);});
 page.on('pageerror',error=>errors.push(error.message));
 const read=selector=>page.locator(selector), summary=()=>read('#results-summary').innerText();
 const query=new URLSearchParams({scenario:'family',searched:'1',origin:'Москва',country:'4',from:dateKey(14),to:dateKey(20),
  minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
 const filteredCount=fixture.filter(h=>h.stars===5).length, start=milliseconds();
 if(filteredCount===0||filteredCount===fixture.length)throw Error('both star values must be represented');
 try{
  await page.addInitScript(fixtureInBrowser,{rows:fixture,searchPath:paths.search});
  assert.equal((await page.goto(server.origin+paths.search+'?'+query,{waitUntil:'domcontentloaded'})).status(),200);
  await page.waitForFunction(({root,count})=>window.longResults?.packets>0&&document.querySelectorAll(root).length===24&&
   new RegExp('^'+count+' ').test(document.querySelector('#results-summary')?.textContent),{root:resultSelector,count:fixture.length},{timeout:9000});
  await page.evaluate(()=>document.fonts.ready);
  await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  const viewport=await page.evaluate(()=>[innerWidth,innerHeight,devicePixelRatio,navigator.maxTouchPoints>0]);
  assert.deepEqual(viewport,[screen.width,screen.height,screen.dpr,screen.mobile]);
  const profiler=collectCalls?await context.newCDPSession(page):null;
  if(profiler){await profiler.send('Profiler.enable');await profiler.send('Profiler.startPreciseCoverage',{callCount:true,detailed:true});}
  if(actionType==='pagination'){
   const old=await read(resultSelector).evaluateAll(nodes=>nodes.map(node=>node.id));
   const append=await interaction(page,screen,appendSelector,'show-more',profiler,sourceApp);
   const current=await read(resultSelector).evaluateAll(nodes=>nodes.map(node=>node.id));
   assert.equal(current.length,48);
   assert.equal(new Set(current).size,48);
   assert.deepEqual(current.slice(0,24),old);
   assert.match(await summary(),/^100 /);
   assert.equal(append.addedCards,24);
   if(profiler)assert.equal(append.recomputes,0,'show more must not recalculate the result inventory');
   assert.equal(await page.evaluate(()=>window.longResults.resultCalls),1);
   if(profiler)await profiler.send('Profiler.stopPreciseCoverage');
   assert.deepEqual(errors,[]);
   assert.deepEqual(external,[]);
   assert.deepEqual(server.rejected,[]);
   return append;
  }
  if(fixture.length===24)assert.equal(await read(appendSelector).count(),0);
  const actions=[];
  const beforeCards=await read(resultSelector).evaluateAll(nodes=>{
   window.filterOriginalCards=new Map(nodes.map(node=>[node.id,node]));
   return nodes.map(node=>node.id);
  });
  if(screen.mobile){
   actions.push(await interaction(page,screen,'.drawer-trigger','open-filters',profiler,sourceApp));
   assert(await read('#filter-panel').evaluate(element=>element.classList.contains('open')));
  }
  const star=read(starsSelector);
  assert(await star.isVisible(),'the selected 4-star control must be present');
  actions.push(await interaction(page,screen,starsSelector,'change-stars',profiler,sourceApp));
  if(screen.mobile){
   assert.match(await read('#filter-preview-count').innerText(),new RegExp('\\b'+filteredCount+'\\b'));
   assert.match(await summary(),new RegExp('^'+fixture.length+' '),'draft must keep results unchanged until applied');
   actions.push(await interaction(page,screen,'#apply-filters','apply-filters',profiler,sourceApp));
   assert(!(await read('#filter-panel').evaluate(element=>element.classList.contains('open'))));
  }
  await page.waitForFunction(count=>new RegExp('^'+count+' ').test(document.querySelector('#results-summary')?.textContent),filteredCount,{timeout:4000});
  const cards=await read(resultSelector).evaluateAll(nodes=>nodes.map(node=>({id:node.id,price:node.querySelector('.starting-price strong')?.textContent,key:node.querySelector('[data-card-entry]')?.dataset.key??null,sameNode:window.filterOriginalCards.get(node.id)===node})));
  assert.equal(cards.length,Math.min(24,filteredCount));
  assert(cards.every(card=>card.price));
  if(collectCalls&&screen.mobile){
   await read('.drawer-trigger').tap();
   assert.match(await read('#filter-preview-count').innerText(),new RegExp('\\b'+filteredCount+'\\b'));
   assert.match(await read(starsSelector).getAttribute('aria-pressed'),/^false$/);
   await read('#filter-panel [data-action="close-filters"]').tap();
  }
  assert.equal(await page.evaluate(()=>window.longResults.resultCalls),1,'local filter and pagination stay offline without a new search');
  if(profiler){
   const filter=actions.find(row=>row.label===(screen.mobile?'apply-filters':'change-stars'));
   assert(filter.recomputes>=1,'inventory call is captured when the filter changes the applied results');
   await profiler.send('Profiler.stopPreciseCoverage');
  }
  assert.deepEqual(errors,[]);
  assert.deepEqual(external,[]);
  assert.deepEqual(server.rejected,[]);
  return {actions,append:null,filteredCount,beforeCards,cards,elapsedMs:milliseconds()-start};
 } finally {await context.close();}
}

function median(values){
 const list=values.slice().sort((a,b)=>a-b),mid=Math.floor(list.length/2);
 return list.length%2?list[mid]:(list[mid-1]+list[mid])/2;
}

function compare(pairs) {
 const operations=pairs[0].before.actions.map(x=>x.label);
 if(pairs[0].before.append)operations.unshift('show-more');
 return operations.map(label=>{
  const select=visit=>[visit.append,...visit.actions].find(action=>action?.label===label);
  return {label,...Object.fromEntries(['dispatch','mutations','frame','domMs','addedCards','removedCards'].map(key=>{
   const before=pairs.map(pair=>select(pair.before)[key]),after=pairs.map(pair=>select(pair.after)[key]);
   const difference=after.map((value,i)=>value-before[i]);
   return [key,{before:median(before),after:median(after),paired:median(difference),min:Math.min(...difference),
    max:Math.max(...difference),faster:difference.filter(x=>x<0).length}];
  }))};
 });
}

function sameResults(before,after){
 assert.equal(before.filteredCount,after.filteredCount);
 assert.deepEqual(before.beforeCards,after.beforeCards);
 const value=rows=>rows.map(({id,price,key})=>({id,price,key}));
 assert.deepEqual(value(before.cards),value(after.cards));
}
module.exports=async function measureAppliedFilters(browser,nextHome,output){
 const before=exactAssets(nextHome),root=path.resolve(__dirname,'..');
 assert.equal(before.appSha,appliedApp);
 assert.equal(before.sheetSha,appliedSheet);
 const scope=execFileSync('git',['diff','--name-only','0612e2a49bc4f8b4b3de0cad1ca1a5d5eceb92cf','HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
 assert.deepEqual(scope,['tests/search3-entry-owner-browser.cjs','tests/search3-next-apply-filters.cjs']);
 const rawSource=execFileSync('git',['show',installed+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
 assert.equal(digest(rawSource),'dd48a58b3c083b3786a011ac7ee9aa3090fa0b786ac8dfb08b0eb47896beece8');
 let localCandidate=rawSource;
 for(const [from,to] of [["function renderResultCards(items,{selectionOnly=false}={}){","function renderResultCards(items,{selectionOnly=false,reuseFilteredCards=false}={}){"],[" paintResultCards(cards,entries,sameCardScope);"," paintResultCards(cards,entries,sameCardScope||reuseFilteredCards);"],[" renderedResultSource={hotels,items:inventory.items};renderedResultItems=items;"," const reuseFilteredCards=options.reuseFilteredCards===true&&renderedResultSource?.hotels===hotels;\n renderedResultSource={hotels,items:inventory.items};renderedResultItems=items;"],[" renderResultHeadings(items,total,pristine);\n renderResultCards(items);"," renderResultHeadings(items,total,pristine);\n if(reuseFilteredCards)renderResultCards(items,{reuseFilteredCards});else renderResultCards(items);"],["function syncFilters(){renderResults();updateSearchUI();}","function syncFilters(options){renderResults(options);updateSearchUI();}"],["if(rebuild)syncFilters();else{renderResults({keepFilters:true});updateSearchUI()}settleFilterRoots","if(rebuild)syncFilters({reuseFilteredCards:true});else{renderResults({keepFilters:true,reuseFilteredCards:true});updateSearchUI()}settleFilterRoots"],["state.selectedDate=next.selectedDate;syncFilters();if(budget","state.selectedDate=next.selectedDate;syncFilters({reuseFilteredCards:true,deferFilterPanel:innerWidth<=1100});if(budget"]]){
  assert.equal(localCandidate.split(from).length,2);
  localCandidate=localCandidate.replace(from,to);
 }
 assert.equal(digest(localCandidate),'c591dbb1ffcf0efc986dcf524230c502f3d5c7b8e64fae755b00789fe76c818e');
 const candidate=await compile(localCandidate,{privateFunctions:true});
 const appSha=digest(candidate);
 assert.equal(appSha,'c26e40eeca052b44ea2673d1442a5aff55a6a7a13ea7a1cae581d01245f064f6');
 assert.notEqual(appSha,appliedApp);
 assert.equal(before.code.toString('utf8').split(inventoryMarker).length,2);
 assert.equal(candidate.split(inventoryMarker).length,2);
 const appUrl=before.appUrl.replace(appliedApp.slice(0,12),appSha.slice(0,12));
 const rewrite=html=>html.replace(before.appUrl,appUrl);
 const assets={...before,code:Buffer.from(candidate),appSha,appUrl,home:rewrite(before.home),search:rewrite(before.search)};
 const sources={before,after:assets};
 const saved=fs.readFileSync(path.join(nextHome.payload,snapshot));
 const raw=JSON.parse(saved),eligible=archivedInventory(raw);
 assert.equal(eligible.length,215);
 const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const server={before:await startServer(nextHome,before,paths),after:await startServer(nextHome,assets,paths)};
 const groups=[];
 try{
  for(const screen of screens)for(const count of [24,100]){
   const rows=eligible.slice(0,count);
   async function sample(mode,collectCalls){
    const result=await verifySample(browser,server[mode],rows,paths,sources[mode],screen,collectCalls);
    if(count===100)result.append=await verifySample(browser,server[mode],rows,paths,sources[mode],screen,collectCalls,'pagination');
    return result;
   }
   const coverage={before:await sample('before',true),after:await sample('after',true)};
   sameResults(coverage.before,coverage.after);
   assert(coverage.after.cards.some(card=>card.sameNode),'a matching card keeps its DOM node when exposed markup is unchanged');
   const pairs=[];
   for(let index=0;index<6;index++){
    const order=index%2?['before','after']:['after','before'],pair={order};
    for(const mode of order)pair[mode]=await sample(mode,false);
    sameResults(pair.before,pair.after);
    pairs.push(pair);
   }
   const group={screen,count,summary:compare(pairs),retained:{
    before:median(pairs.map(p=>p.before.cards.filter(x=>x.sameNode).length)),
    after:median(pairs.map(p=>p.after.cards.filter(x=>x.sameNode).length))},coverage,pairs};
   groups.push(group);
   console.log('SEARCH3_APPLY_FILTERS '+JSON.stringify({screen,count,summary:group.summary,retained:group.retained}));
  }
  fs.writeFileSync(path.join(output,'canonical-next-apply-filters.json'),JSON.stringify({baseline:installed,source:process.env.SEARCH3_SOURCE_SHA,
   beforeApp:appliedApp,afterApp:assets.appSha,css:assets.sheetSha,snapshot:digest(saved),browser:browser.version(),groups},null,2)+'\n');
 }finally{await Promise.all(Object.values(server).map(item=>item.close()));}
};
module.exports.archivedInventory=archivedInventory;
module.exports.fixtureInBrowser=fixtureInBrowser;
