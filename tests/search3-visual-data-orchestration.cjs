'use strict';
// Full LIVE data owner, deterministic intercepted transports and timers only.
// The probe exposes state to this test; it is never added to the runtime file.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const target=path.resolve(__dirname,'../v2/prototype-search/data.js');
const trip=()=>({origin:'Москва',country:'4',from:'2026-10-13',to:'2026-10-19',minNights:7,maxNights:7,adults:2,ages:[12,4]});
const copy=value=>JSON.parse(JSON.stringify(value));
const gate=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
const flush=async()=>{for(let i=0;i<16;i++)await Promise.resolve();};
const rows=(n=1)=>Array.from({length:n},(_,i)=>({id:101+i,name:'Fictional '+i,tours:[{id:'tv-'+i,provider:'tourvisor',price:120000+i,date:'2026-10-13',nights:7,meal:'AI',roomType:'TEST'}]}));
const eventSnapshot=event=>{
 const value=copy(event);
 if(Array.isArray(value.hotels))value.hotels=value.hotels.map(h=>({id:h.id,offers:h.offers.map(o=>({key:o.key,total:o.total,provider:o.provider,day:o.day,nights:o.nights,ages:o.ages}))}));
 return value;
};
function harness(source,{providers=false}={}){
 const log=[],events=[],calls=[],timers=new Map(),http=[];let id=0,clock=1791878400000,status={progress:100},inventory=rows(),onEvent=()=>{},respond=null,fetchGate=null;
 const window={location:{href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/',origin:'https://anytoour.ru'},
  V2_CONFIG:providers?{andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'}:{},
  V2Runtime:{setSearchId(value){log.push(['searchId',value]);},async api(action,body){calls.push({action,body:copy(body)});log.push(['api',action,copy(body)]);if(respond)return respond(action,body);if(action==='search_start')return{searchId:17};if(action==='search_status')return status;if(action==='search_results')return inventory;if(action==='search_continue')return{};throw Error('Unexpected API '+action);}},
  Search3CanonicalProfilesV1:{create(publish){return {reset(){log.push(['reset']);},read(value){log.push(['read',value.length]);return value;},clearOffers(value){log.push(['clearOffers',value]);},refresh(){log.push(['refresh']);publish();}}; }},
  dispatchEvent(event){log.push(['dispatch',event.type,copy(event.detail)]);}};
 const context=vm.createContext({window,URL,URLSearchParams,AbortController,Map,Set,WeakMap,Promise,structuredClone,
  Date:class extends Date{static now(){return clock;}},
  CustomEvent:class{constructor(type,{detail}){this.type=type;this.detail=detail;}},
  fetch:async(url,options)=>{
   assert(providers&&String(url).includes('api-andromeda-search3-preview.php'),'no unexpected network path');
   const body=JSON.parse(options.body);http.push(copy(body));log.push(['http',copy(body)]);
   if(fetchGate)await fetchGate;
   return {ok:true,status:200,json:async()=>({ok:true,data:{provider:'andromeda',generation:body.generation,hotels:[],
    date_range:{from:body.params.dateFrom,to:body.params.dateTo},search_ref:'a'.repeat(64),page:body.page,pages_count:2,
    status:'complete',selection_enabled:false,first_page_only:false,received_offers:0,mapped_offers:0}})};
  },
  setTimeout(fn,delay){const key=++id;timers.set(key,fn);log.push(['timer',key,delay]);return key;},
  clearTimeout(key){log.push(['clearTimer',key]);timers.delete(key);}});
 const marker='  root.AnyTourPrototypeData=Object.freeze(';
 assert.equal(source.split(marker).length,2,'one actual data-owner export');
 const probe=`  root.__test={run:()=>activeSearch,poll:run=>pollSearch(run),state:()=>({generation,searchId,context,searchParams,currentSupplierScope})};\n`;
 vm.runInContext(source.replace(marker,probe+marker),context,{filename:'prototype-search/data.js'});
 const api=window.AnyTourPrototypeData;
 api.catalog.departures=[{id:1,name:'Москва'}];api.catalog.countries=[{id:4,name:'Турция',tourvisorIds:['4']}];
 const callback=event=>{events.push(eventSnapshot(event));log.push(['event',eventSnapshot(event)]);onEvent(event);};
 return {api,log,events,calls,http,timers,probe:window.__test,callback,
  setEvent:fn=>onEvent=fn,setAPI:fn=>respond=fn,setStatus:value=>status=value,setRows:value=>inventory=value,setFetchGate:value=>fetchGate=value,
  tick:async()=>{const entry=timers.entries().next().value;assert(entry,'a timer is scheduled');timers.delete(entry[0]);await entry[1]();},
  advance:ms=>clock+=ms,
  capture(){const run=window.__test.run();return {log,events,calls,http,state:copy(window.__test.state()),run:run?copy({generation:run.generation,search:run.search,hotelIds:run.hotelIds,filters:run.filters,pending:run.pending,searchId:run.searchId,resumeOnly:run.resumeOnly,continued:run.continued,expired:run.expired,canContinue:run.canContinue,tvCanContinue:run.tvCanContinue,andromedaCanContinue:run.andromedaCanContinue,continueBaseline:run.continueBaseline,continueBaselineTourvisor:run.continueBaselineTourvisor,lastProgress:run.lastProgress,lastRead:run.lastRead,deadline:run.deadline,sourceCounts:run.sourceCounts,aborted:run.controller.signal.aborted}):null,timers:[...timers.keys()]};}};
}
async function characterize(source){
 const records=[];
 async function check(name,body,options){const h=harness(source,options);await body(h);await flush();records.push({name,...h.capture()});}
 await check('fresh search setup, independent snapshots, public descriptors',async h=>{
  const s=trip(),ids=['101'],filters={stars:[5],min:50000,max:180000};await h.api.search(s,h.callback,ids,filters);
  const r=h.probe.run();assert.equal(r.pending,true);assert.equal(r.canContinue,true);assert.equal(r.searchId,17);
  assert.equal(h.calls[0].action,'search_start');assert.deepEqual(h.calls[0].body.childs,[4,12]);
  s.ages[0]=16;ids.push('102');filters.stars[0]=3;
  assert.deepEqual(copy(r.search.ages),[12,4]);assert.deepEqual(copy(r.hotelIds),['101']);assert.deepEqual(copy(r.filters.stars),[5]);
  assert.equal(Object.isFrozen(h.api),true);assert.equal(h.api.search.length,2);assert.equal(h.api.resumeCached.length,2);
  assert.equal(typeof Object.getOwnPropertyDescriptor(h.api,'searchId').get,'function');assert.equal(Object.getOwnPropertyDescriptor(h.api,'search').writable,false);
  assert.deepEqual(h.events.map(e=>e.type),['loading','provider']);assert.deepEqual(h.log.slice(0,4).map(x=>x[0]),['clearTimer','searchId','reset','event']);
 });
 await check('cached resume performs no live start',async h=>{
  assert.equal(await h.api.resumeCached(trip(),h.callback,['101'],{stars:[5]}),true);
  assert.equal(h.calls.length,0);assert.equal(h.timers.size,0);assert.equal(h.probe.run().pending,false);
  assert.equal(h.probe.run().canContinue,false);assert.deepEqual(h.events.map(e=>e.type),['loading','complete']);
  assert.deepEqual(Object.keys(h.events[1].sources),['tourvisor','anex','andromeda','database']);
 });
 await check('invalid parameters do not stop an active search',async h=>{
  await h.api.search(trip(),h.callback);const run=h.probe.run(),before=h.log.length;
  await assert.rejects(h.api.search({...trip(),origin:'missing'},h.callback));
  assert.equal(h.probe.run(),run);assert.equal(h.log.length,before);
 });
 for(const method of ['search','resumeCached'])await check(method+' loading cancellation is passive',async h=>{
  h.setEvent(e=>{if(e.type==='loading')h.api.stop();});const result=await h.api[method](trip(),h.callback);
  assert.equal(result,method==='resumeCached'?false:undefined);assert.equal(h.calls.length,0);assert.equal(h.timers.size,0);
 });
 await check('provider callback cancellation prevents TV start',async h=>{
  h.setEvent(e=>{if(e.type==='provider')h.api.stop();});await h.api.search(trip(),h.callback);assert.equal(h.calls.length,0);
 });
 await check('throwing loading callback retains rejection semantics',async h=>{
  h.setEvent(()=>{throw Error('callback failure');});await assert.rejects(h.api.search(trip(),h.callback),/callback failure/);assert.equal(h.calls.length,0);
 });
 await check('late started response after stop is ignored',async h=>{
  const g=gate();h.setAPI(()=>g.promise);const search=h.api.search(trip(),h.callback);await flush();h.api.stop();g.resolve({searchId:91});await search;
  assert.equal(h.timers.size,0);assert.equal(h.api.searchId,0);
 });
 await check('invalid search id completes partially',async h=>{
  h.setAPI(()=>({searchId:'invalid'}));await h.api.search(trip(),h.callback);
  assert.equal(h.events.at(-1).type,'complete');assert.equal(h.events.at(-1).partial,true);assert.equal(h.probe.run().pending,false);
 });
 await check('progress first portion keeps polling cadence',async h=>{
  h.setStatus({progress:25});await h.api.search(trip(),h.callback);await h.tick();
  assert.equal(h.calls.at(-1).body.limit,25);assert.equal(h.log.at(-1)[2],2500);assert(!h.events.some(e=>e.type==='complete'));
 });
 await check('complete waits for both initial sources',async h=>{
  await h.api.search(trip(),h.callback);const a=gate(),b=gate(),run=h.probe.run();run.anex=a.promise;run.andromeda=b.promise;
  const polling=h.tick();await flush();assert(!h.events.some(e=>e.type==='complete'));a.resolve();await flush();assert(!h.events.some(e=>e.type==='complete'));
  b.resolve();await polling;assert.equal(h.events.at(-1).type,'complete');assert.equal(h.events.at(-1).union.offers,1);
  assert.equal(h.events.at(-1).canContinue,true);assert.equal(run.pending,false);
 });
 await check('stop during initial settlement cannot publish complete',async h=>{
  await h.api.search(trip(),h.callback);const g=gate();h.probe.run().anex=g.promise;const polling=h.tick();await flush();h.api.stop();g.resolve();await polling;
  assert(!h.events.some(e=>e.type==='complete'));
 });
 for(const type of ['progress','results'])await check(type+' callback cancellation prevents downstream work',async h=>{
  await h.api.search(trip(),h.callback);h.setEvent(e=>{if(e.type===type)h.api.stop();});await h.tick();assert(!h.events.some(e=>e.type==='complete'));
 });
 await check('TV result cap disables further TV continuation',async h=>{
  h.setRows(rows(5000));await h.api.search(trip(),h.callback);await h.tick();const last=h.events.at(-1);
  assert.equal(last.resultLimitReached,true);assert.equal(last.canContinue,false);assert.equal(last.union.hotels,5000);
  // Keep the differential record compact while retaining all other events/state.
  for(const e of h.events)if(Array.isArray(e.hotels))e.hotels={count:e.hotels.length,first:e.hotels[0],last:e.hotels.at(-1)};
  for(const item of h.log)if(item[0]==='event'&&Array.isArray(item[1].hotels))item[1].hotels={count:item[1].hotels.length,first:item[1].hotels[0],last:item[1].hotels.at(-1)};
 });
 await check('TV continuation with growth keeps exact TV receipt',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();h.setRows(rows(2));assert.equal(await h.api.continueSearch(),true);
  const last=h.events.at(-1);assert.deepEqual(last.continuationGrowth,{before:{hotels:1,offers:1},after:{hotels:2,offers:2},grew:true});
  assert.equal(last.canContinue,true);assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);
 });
 await check('TV continuation without growth becomes exhausted',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();await h.api.continueSearch();
  assert.equal(h.events.at(-1).continuationGrowth.grew,false);assert.equal(h.events.at(-1).canContinue,false);assert.equal(await h.api.continueSearch(),false);
 });
 await check('double Continue preserves single in-flight transport',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();const g=gate();h.setAPI((action)=>action==='search_continue'?g.promise:action==='search_status'?{progress:100}:rows(2));
  const first=h.api.continueSearch();assert.equal(await h.api.continueSearch(),false);g.resolve({});await first;
  assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);
 });
 await check('lost Continue response recovers by reads, not repeat start',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();let fail=true;
  h.setAPI(action=>{if(action==='search_continue'&&fail){fail=false;throw Error('lost response');}if(action==='search_status')return{progress:100};return rows(2);});
  assert.equal(await h.api.continueSearch(),false);assert.equal(h.events.at(-1).retryRead,true);
  assert.equal(await h.api.continueSearch(),true);assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);
 });
 await check('expired Continue cannot restart',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();h.setAPI(()=>{throw Object.assign(Error('expired'),{status:410});});
  assert.equal(await h.api.continueSearch(),false);assert.equal(h.probe.run().expired,true);assert.equal(await h.api.continueSearch(),false);
 });
 await check('initial provider page and explicit provider-only continuation',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();assert.deepEqual(h.http.map(c=>c.page),[1]);
  assert.equal(h.http[0].params.dateTo,'2026-10-19');await h.api.continueSearch();assert.equal(h.probe.run().tvCanContinue,false);
  assert.deepEqual(h.http.map(c=>c.page),[1,2]);
 },{providers:true});
 await check('provider-only settlement publishes union growth',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();h.probe.run().tvCanContinue=false;
  assert.equal(await h.api.continueSearch(),true);assert.deepEqual(h.http.map(c=>c.page),[1,2]);
  assert.equal(h.calls.filter(c=>c.action==='search_continue').length,0);assert.equal(h.events.at(-1).continued,true);
  assert.equal(h.events.at(-1).canContinue,false);assert.equal(h.events.at(-1).continuationGrowth.grew,false);
 },{providers:true});
 await check('provider-only pending stop cannot finalize stale generation',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();h.probe.run().tvCanContinue=false;const g=gate();h.setFetchGate(g.promise);
  const n=h.events.filter(e=>e.type==='complete').length,pending=h.api.continueSearch();await flush();h.api.stop();g.resolve();assert.equal(await pending,false);
  assert.equal(h.events.filter(e=>e.type==='complete').length,n);
 },{providers:true});
 await check('new search invalidates older pending start and callback',async h=>{
  const g=gate();let starts=0;h.setAPI(()=>++starts===1?g.promise:{searchId:33});
  const old=h.api.search(trip(),h.callback);await flush();await h.api.search({...trip(),adults:3},h.callback);g.resolve({searchId:17});await old;
  assert.equal(h.api.searchId,33);assert.equal(h.probe.run().search.adults,3);assert.equal(h.timers.size,1);
 });
 return records;
}
(async()=>{
 const options=process.argv.slice(2),sourcePath=options[0]&&options[0]!=='--compare'?path.resolve(options.shift()):target;
 const records=await characterize(fs.readFileSync(sourcePath,'utf8'));
 const digest=crypto.createHash('sha256').update(JSON.stringify(records)).digest('hex');
 // Pinned before the refactor on full data owner blob 931fb024951b6d69ce84e635e61ddd8112501623.
 assert.equal(digest,'f2a89681027398304bf0f9c4c55434e8447c01b591f0f1b07130eac0174f461a','observable orchestration trace changed from the characterized baseline');
 if(options[0]==='--compare'){
  assert(options[1],'--compare requires an original source file');const before=await characterize(fs.readFileSync(path.resolve(options[1]),'utf8'));
  assert.deepEqual(records,before,'original and refactored full data owner observable traces must match');
 }
 console.log(JSON.stringify({cases:records.length,digest,comparison:options[0]==='--compare'?'identical':'characterized',supplierHTTP:0,realLeads:0}));
})().catch(error=>{console.error(error);process.exitCode=1;});
