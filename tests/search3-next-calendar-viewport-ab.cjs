'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {createHash}=require('node:crypto'),{execFileSync}=require('node:child_process');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const base='be60200e46c46c9b82c36427afa0169fd7c3a821';
const baselineApp='e4bacb7e5bd770a4d876f427eb9497845e889001201d9d8320fef8bb2c338126';
const sheet='0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const snapshot='visual-search/fixtures/live-search-2026-09-23.json';
const dateKey=new Date().toISOString().slice(0,10);
const future=days=>new Date(Date.parse(dateKey+'T12:00Z')+days*86400000).toISOString().slice(0,10);
const digest=x=>createHash('sha256').update(x).digest('hex');
const screens=[{width:1280,height:720,dpr:1,touch:false},{width:430,height:932,dpr:3,touch:true}];
const target=220000;
const query=new URLSearchParams({scenario:'family',searched:'1',origin:'Москва',country:'4',from:future(14),to:future(20),
 minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
const rawCandidate='76008646a83926fff1a6737e366e974101bd618ada8c4565affc4634545e29bc',compiledCandidate='a2f5b906a953364e735988eb055eb5b370cca5f1c675913ae0d1a2e0b663c633';
const patches=[["let deferredCalendarStrip=null;\nfunction isResultCalendarCollapsed(){","let deferredCalendarStrip=null;\nlet resultCalendarMobile;\nfunction isResultCalendarMobile(){\n return resultCalendarMobile??=(innerWidth<=760);\n}\nfunction isResultCalendarCollapsed(){"],["return innerWidth<=760?!resultCalendarExpanded:from===to&&expandedResultCalendarDay!==from;","return isResultCalendarMobile()?!resultCalendarExpanded:from===to&&expandedResultCalendarDay!==from;"],["if(innerWidth<=760)resultCalendarExpanded=!resultCalendarExpanded;","if(isResultCalendarMobile())resultCalendarExpanded=!resultCalendarExpanded;"],["const s=state.search,mobile=innerWidth<=760,singleDay=!mobile&&s.from===s.to;","const s=state.search,mobile=isResultCalendarMobile(),singleDay=!mobile&&s.from===s.to;"],["window.addEventListener('resize',()=>{\n syncResultCalendarDisclosure();","window.addEventListener('resize',()=>{\n resultCalendarMobile=undefined;\n syncResultCalendarDisclosure();"]];
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
 const disclosure=page.locator('.result-calendar-disclosure'),strip=page.locator('#price-strip');
 const prices=()=>strip.locator('[data-date]').evaluateAll(nodes=>nodes.map(node=>[node.dataset.date,node.querySelector('strong')?.textContent]));
 const setWidth=async(width,mobile,expanded)=>{
  await page.setViewportSize({width,height:screen.height});
  await page.waitForFunction(({width,mobile,expanded})=>{
   const button=document.querySelector('.result-calendar-disclosure');
   const preview=document.querySelector('#calendar-preview');
   return innerWidth===width && button.hidden===!mobile && preview.hidden===!expanded
    && button.getAttribute('aria-expanded')===String(expanded);
  },{width,mobile,expanded});
 };
 const toggle=async expanded=>{
  if(screen.touch)await disclosure.tap();else await disclosure.click();
  await page.waitForFunction(expanded=>document.querySelector('.result-calendar-disclosure').getAttribute('aria-expanded')===String(expanded),expanded);
 };
 await page.waitForFunction(()=>document.querySelector('#price-strip')?.getAttribute('aria-busy')==='false');
 await setWidth(760,true,false);
 await toggle(true);
 const initial=await prices();
 assert.equal(initial.length,7);
 assert(initial.some(row=>/^\d/.test(row[1]||'')));
 await setWidth(761,false,true);
 assert.deepEqual(await prices(),initial);
 await setWidth(760,true,true);
 await toggle(false);
 await setWidth(761,false,true);
 assert.deepEqual(await prices(),initial);
 await setWidth(760,true,false);
 await setWidth(screen.width,screen.touch,!screen.touch);
 return {boundary:[760,761,760,761,760],prices:initial,restored:true};
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
module.exports=async function compareCalendarViewport(browser,nextHome,output){
 const exact=exactAssets(nextHome),root=path.resolve(__dirname,'..');
 assert.equal(exact.appSha,baselineApp);assert.equal(exact.sheetSha,sheet);
 assert.deepEqual(execFileSync('git',['diff','--name-only','0dbcb1db42ac9ab26ae11228b04fa5c1f36edec1','HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort(),
  ['tests/search3-entry-owner-browser.cjs','tests/search3-next-calendar-viewport-ab.cjs']);
 const source=execFileSync('git',['show',base+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
 assert.equal(digest(source),'d74a97afb5f62abc2b552257d13748ea80b5618b97f50ee5ae87a58b875d1f80');
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
   assert.deepEqual(warm.before.extra,warm.after.extra);
   const pairs=[];
   for(let i=0;i<6;i++){
    const order=i%2?['before','after']:['after','before'],pair={order};
    for(const mode of order)pair[mode]=await visit(mode,false);
    assert.deepEqual(pair.before.cards,pair.after.cards);
    assert.deepEqual(pair.before.dom,{retained:21,removed:3,moved:0});
    assert.deepEqual(pair.after.dom,{retained:21,removed:3,moved:0});
    pairs.push(pair);
   }
   const summary=summarize(pairs);
   console.log('SEARCH3_CALENDAR_VIEWPORT_AB '+JSON.stringify({screen,cpu,summary}));
   groups.push({screen,cpu,summary,warm,pairs});
  }
  fs.writeFileSync(path.join(output,'canonical-next-calendar-viewport-ab.json'),JSON.stringify({
   source:process.env.SEARCH3_SOURCE_SHA,base,beforeApp:baselineApp,afterApp:sha,css:sheet,browser:browser.version(),fixture:digest(saved),groups
  },null,2)+'\n');
 }finally{await Promise.all(Object.values(servers).map(server=>server.close()));}
};
