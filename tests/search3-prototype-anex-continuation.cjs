'use strict';
// Executes the actual canonical adapter. HTTP, profile storage and UI callbacks
// are test dependencies; no alternative continuation implementation is embedded.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const {webcrypto}=require('node:crypto');
const source=fs.readFileSync(process.env.SOURCE_PATH||path.join(__dirname,'../v2/prototype-search/data.js'),'utf8');
const origin='https://anytoour.ru';
const endpoint=origin+'/_preview/search3-anex-candidate/api-anex-search3-preview.php';
const params={dateFrom:'2026-10-10',dateTo:'2026-10-16',nightsFrom:7,nightsTo:7,adults:2,childs:[],countryId:'4'};
const ref='1'.repeat(32);
const offerRef=n=>'anex_online:'+n.toString(16).padStart(64,'0');
const deferred=()=>{let resolve;const promise=new Promise(r=>resolve=r);return {promise,resolve};};
function page(number=1,state='available',ids=[number],searchRef=ref){
 return {provider:'anex',generation:1,date_range:{from:params.dateFrom,to:params.dateTo},search_ref:searchRef,
  hotels:ids.length?[{local_id:1,catalog:{source:'tourvisor',hotel_id:1},tours:ids.map(id=>({price:{amount:'100000.00',currency:'RUB'},
   checkin:params.dateFrom,nights:7,adults:2,children:0,search_ref:searchRef,offer_ref:offerRef(id),selection_enabled:false,
   final_price_verified:false,meal:'AI',room:'Standard',kind:'concrete',flight_type:'charter'}))}]:[],
  pages_read:1,first_page_only:number===1,...(number>1?{page:number}:{}),
  continuation:{state,pages_read:number,next_page:state==='available'?number+1:null}};
}
function samoPage(number=1,total=2){return {provider:'andromeda',generation:1,search_ref:'2'.repeat(64),page:number,pages_count:total,
 date_range:{from:params.dateFrom,to:params.dateTo},hotels:[],status:'complete',selection_enabled:false,first_page_only:false};}
function harness(){
 const stored=new Map(),calls=[],events=[],apiCalls=[];
 const profile={clearOffers(src){for(const [k,v] of stored)if(v.source===src)stored.delete(k);},upsertLegacyOffer(id,tour,{source}){stored.set(source+':'+tour.offerRef,{id,tour,source});},refresh(){},reset(){stored.clear();},
  read(){const hotels=new Map();for(const v of stored.values()){if(!hotels.has(v.id))hotels.set(v.id,{id:v.id,name:'Hotel',canonicalLegacyIds:[v.id],tours:[]});hotels.get(v.id).tours.push(v.tour);}return [...hotels.values()];}};
 let fetchImpl=async (url,body)=>({ok:true,data:url.includes('api-andromeda-')?samoPage(body.page):page(body.page)});
 let apiImpl=async(action)=>action==='search_status'?{progress:100,status:'complete'}:action==='search_results'?[]:{};
 const sandbox={console,URL,Map,Set,WeakMap,AbortController,TextEncoder,Date,structuredClone,Uint8Array,setTimeout,clearTimeout,DOMException,
  fetch:async(url,options)=>{const body=JSON.parse(options.body);calls.push({url,body});const result=await fetchImpl(url,body);return {ok:result.ok!==false,status:result.status||200,json:async()=>result};}};
 const root={location:{origin,href:origin+'/_preview/search3-next-candidate/visual-search/'},crypto:webcrypto,TextEncoder,
  V2_CONFIG:{anexApi:endpoint,andromedaApi:origin+'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'},
  V2Runtime:{api:async(action,p)=>{apiCalls.push({action,params:p});return apiImpl(action,p);},setSearchId(){}},
  Search3CanonicalProfilesV1:{create:()=>profile}};
 sandbox.window=root;
 const anchor='  root.AnyTourPrototypeData=Object.freeze({';
 assert.equal(source.split(anchor).length,2,'canonical export anchor');
 const instrument=`  root.__test={applyDirectAnex,applyDirectAndromeda,continueSearch,pollSearch,stop,
 available:typeof anexContinuationAvailable==='function'?anexContinuationAvailable:()=>false,
 set(run,callback){activeSearch=run;generation=run.generation;searchId=run.searchId;raw=[];context={country:'4',adults:2,ages:[],origin:'Москва'};notify=callback;},
 continuation:typeof anexPageContinuation==='function'?anexPageContinuation:()=>null};\n`;
 vm.runInNewContext(source.replace(anchor,instrument+anchor),sandbox,{filename:'actual-data.js'});
 const run={generation:1,searchId:7,controller:new AbortController(),filters:{},pending:false,canContinue:true,tvCanContinue:false,resumeOnly:false,
  continued:false,expired:false,sourceCounts:{},lastProgress:-10,lastRead:0,deadline:0};
 let notify=event=>events.push(event);
 root.__test.set(run,event=>notify(event));
 return {run,calls,events,apiCalls,stored,t:root.__test,root,
  setFetch(fn){fetchImpl=fn;},setApi(fn){apiImpl=fn;},setNotify(fn){notify=fn;},
  initial(data=page(),index=0,total=1){return root.__test.applyDirectAnex(run,data,params,index,total);},
  samo(data=samoPage(),index=0,total=1){return root.__test.applyDirectAndromeda(run,data,params,index,total);}};
}
const tests=[];function test(name,fn){tests.push([name,fn]);}
test('legacy endpoint: retain first page; do not invent continuation',async()=>{const h=harness(),p=page();delete p.continuation;await h.initial(p);assert.equal(h.stored.size,1);assert.equal(h.t.available(h.run),false);assert.equal(h.calls.length,0);assert.equal(await h.t.continueSearch(),false);});
test('initial available page is immediate and does not auto-drain',async()=>{const h=harness();await h.initial();assert.equal(h.t.available(h.run),true);assert.equal(h.calls.length,0);assert.equal(h.run.sourceCounts.anex.status,'partial');});
test('ANEX-only click sends one next-page action and preserves previous object',async()=>{const h=harness();await h.initial();const original=[...h.stored.values()][0].tour;await h.t.continueSearch();assert.equal(h.calls.length,1);assert.deepEqual(h.calls[0].body,{action:'continue',generation:1,search_ref:ref,page:2});assert.equal(h.stored.size,2);assert.strictEqual([...h.stored.values()][0].tour,original);assert.equal(h.run.canContinue,true);assert.equal(h.run.sourceCounts.anex.offers,2);});
test('exhausted empty next page ends continuation without erasing offers',async()=>{const h=harness();await h.initial();h.setFetch(async()=>({ok:true,data:page(2,'exhausted',[])}));await h.t.continueSearch();assert.equal(h.stored.size,1);assert.equal(h.run.canContinue,false);assert.equal(h.run.sourceCounts.anex.status,'complete');});
test('late HTTP failure is sticky and cannot repeat consumed page',async()=>{const h=harness();await h.initial();h.setFetch(async()=>({ok:false,status:502}));await h.t.continueSearch();assert.equal(h.stored.size,1);assert.equal(h.run.sourceCounts.anex.continuationFailed,true);assert.equal(h.t.available(h.run),false);await h.t.continueSearch();assert.equal(h.calls.length,1);});
test('bad page metadata keeps the entire previous page',async()=>{const h=harness();await h.initial();h.setFetch(async()=>({ok:true,data:page(3)}));await h.t.continueSearch();assert.equal(h.stored.size,1);assert.equal(h.run.anexWindows.get(0).pagesRead,1);assert.equal(h.run.sourceCounts.anex.status,'partial');});
test('different search reference cannot append',async()=>{const h=harness();await h.initial();h.setFetch(async()=>({ok:true,data:page(2,'available',[2],'9'.repeat(32))}));await h.t.continueSearch();assert.equal(h.stored.size,1);assert.equal(h.run.sourceCounts.anex.continuationFailed,true);});
test('different generation cannot append',async()=>{const h=harness();await h.initial();h.setFetch(async()=>({ok:true,data:{...page(2),generation:2}}));await h.t.continueSearch();assert.equal(h.stored.size,1);assert.equal(h.run.sourceCounts.anex.continuationFailed,true);});
test('double click does not issue a second request',async()=>{const h=harness();await h.initial();const d=deferred();h.setFetch(()=>d.promise);const first=h.t.continueSearch();assert.equal(await h.t.continueSearch(),false);assert.equal(h.calls.length,1);d.resolve({ok:true,data:page(2)});await first;assert.equal(h.stored.size,2);});
test('stopped generation cannot publish an in-flight page',async()=>{const h=harness();await h.initial();const d=deferred();h.setFetch(()=>d.promise);const first=h.t.continueSearch();h.t.stop();const before=h.events.length;d.resolve({ok:true,data:page(2)});await first;assert.equal(h.stored.size,1);assert.equal(h.events.length,before);});
test('stop from loading callback prevents the request',async()=>{const h=harness();await h.initial();h.setNotify(e=>{h.events.push(e);if(e.type==='loading')h.t.stop();});assert.equal(await h.t.continueSearch(),false);assert.equal(h.calls.length,0);});
test('duplicate cross-page offers keep first price/object and one canonical copy',async()=>{const h=harness();await h.initial();const original=[...h.stored.values()][0].tour;h.setFetch(async()=>({ok:true,data:page(2,'exhausted',[1,2])}));await h.t.continueSearch();assert.equal(h.stored.size,2);assert.strictEqual([...h.stored.values()][0].tour,original);assert.equal(h.run.sourceCounts.anex.deduplicatedOffers,1);});
test('invalid initial cursor cannot discard a valid initial offer',async()=>{const h=harness(),p=page();p.continuation.next_page=99;await h.initial(p);assert.equal(h.stored.size,1);assert.equal(h.t.available(h.run),false);assert.equal(h.run.sourceCounts.anex.continuationFailed,true);});
test('each destination receives one page; one failed branch cannot block another',async()=>{const h=harness(),other='3'.repeat(32);await h.initial(page(),0,2);await h.initial(page(1,'available',[2],other),1,2);h.setFetch(async(u,b)=>b.search_ref===ref?{ok:false,status:502}:{ok:true,data:page(2,'exhausted',[3],other)});await h.t.continueSearch();assert.equal(h.calls.length,2);assert.equal(h.stored.size,3);assert.equal(h.run.sourceCounts.anex.continuationFailed,true);assert.equal(h.run.sourceCounts.anex.status,'partial');});
test('missing initial destination stays partial after a successful later page',async()=>{const h=harness();await h.initial(page(),0,2);h.setFetch(async()=>({ok:true,data:page(2,'exhausted',[2])}));await h.t.continueSearch();assert.equal(h.run.sourceCounts.anex.destinationBranchFailed,true);assert.equal(h.run.sourceCounts.anex.status,'partial');});
test('all three branches use the same click without replaying initial ANEX',async()=>{const h=harness();await h.initial();await h.samo();h.run.tvCanContinue=true;await h.t.continueSearch();assert.equal(h.calls.length,2);assert.equal(h.calls.filter(x=>x.url===endpoint).length,1);assert.equal(h.calls.find(x=>x.url===endpoint).body.action,'continue');assert.equal(h.apiCalls.filter(x=>x.action==='search_continue').length,1);assert.equal(h.run.andromedaBranches.get(0).pages.size,2);assert.equal(h.stored.size,2);assert.equal(h.run.pending,false);assert.equal(h.run.canContinue,true);});
test('Tourvisor-only behaviour does not call ANEX',async()=>{const h=harness();h.run.tvCanContinue=true;await h.t.continueSearch();assert.equal(h.calls.length,0);assert.equal(h.apiCalls.filter(x=>x.action==='search_continue').length,1);});
test('SAMO-only behaviour does not call ANEX',async()=>{const h=harness();await h.samo();await h.t.continueSearch();assert.equal(h.calls.length,1);assert.ok(h.calls[0].url.includes('api-andromeda-'));assert.equal(h.run.canContinue,false);});
test('read-only Tourvisor recovery does not spend another direct-provider page',async()=>{const h=harness();await h.initial();await h.samo();h.run.tvCanContinue=true;h.run.resumeOnly=true;await h.t.continueSearch();assert.equal(h.calls.length,0);assert.equal(h.apiCalls.filter(x=>x.action==='search_continue').length,0);assert.equal(h.run.canContinue,true);});
test('Tourvisor error waits for the independent ANEX page before unlocking',async()=>{const h=harness();await h.initial();h.run.tvCanContinue=true;h.setApi(async()=>{throw new Error('TV unavailable');});const d=deferred();h.setFetch(()=>d.promise);const first=h.t.continueSearch();await new Promise(setImmediate);assert.equal(h.run.pending,true);assert.equal(await h.t.continueSearch(),false);d.resolve({ok:true,data:page(2)});await first;assert.equal(h.run.pending,false);assert.equal(h.stored.size,2);assert.equal(h.calls.length,1);});
test('no more than twelve pages and limit is not exhaustion',async()=>{const h=harness();await h.initial();h.setFetch(async(u,b)=>({ok:true,data:page(b.page,b.page===12?'limit':'available',[b.page])}));for(let n=2;n<=12;n++)await h.t.continueSearch();assert.equal(h.calls.length,11);assert.equal(h.stored.size,12);assert.equal(h.run.canContinue,false);assert.equal(h.run.sourceCounts.anex.status,'partial');await h.t.continueSearch();assert.equal(h.calls.length,11);});
test('malformed cursor fields never grant authority',async()=>{const h=harness();for(const c of [null,[],{state:'available',pages_read:1,next_page:'2'},{state:'available',pages_read:12,next_page:13},{state:'limit',pages_read:1,next_page:null},{state:'exhausted',pages_read:1,next_page:2},{state:'available',pages_read:1,next_page:2,params:{}}])assert.equal(h.t.continuation({continuation:c},1),null);});
test('background or inconsistent first-page envelope cannot create a cursor',async()=>{for(const change of [{pages_read:2},{first_page_only:false}]){const h=harness();await h.initial({...page(),...change});assert.equal(h.t.available(h.run),false);assert.equal(h.stored.size,1);}});
test('excluded initial scope cannot create a cursor without a search reference',async()=>{const h=harness(),p=page();delete p.search_ref;p.hotels=[];p.pages_read=0;await h.initial(p);assert.equal(h.t.available(h.run),false);assert.equal(h.calls.length,0);});
test('invalid last offer cannot partially append the preceding valid offers',async()=>{const h=harness();await h.initial();const p=page(2,'exhausted',[2,3]);p.hotels[0].tours[1].price.amount='NaN';h.setFetch(async()=>({ok:true,data:p}));await h.t.continueSearch();assert.equal(h.stored.size,1);assert.equal(h.run.anexWindows.get(0).pagesRead,1);assert.equal(h.run.sourceCounts.anex.continuationFailed,true);});
(async()=>{let failed=0;for(const [name,fn] of tests){try{await fn();console.log('PASS '+name);}catch(e){failed++;console.error('FAIL '+name+'\n'+e.stack);}}console.log(JSON.stringify({suite:'actual-canonical-adapter-anex-continuation',passed:tests.length-failed,failed,tests:tests.length,network:'mocked',realSupplierRequests:0}));if(failed)process.exitCode=1;})();
