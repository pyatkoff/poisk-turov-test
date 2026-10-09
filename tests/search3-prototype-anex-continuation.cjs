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
 let clock=Date.now();class ClockDate extends Date{static now(){return clock;}}
 const profile={clearOffers(src){for(const [k,v] of stored)if(v.source===src)stored.delete(k);},upsertLegacyOffer(id,tour,{source}){stored.set(source+':'+tour.offerRef,{id,tour,source});},refresh(){},reset(){stored.clear();},
  read(){const hotels=new Map();for(const v of stored.values()){if(!hotels.has(v.id))hotels.set(v.id,{id:v.id,name:'Hotel',canonicalLegacyIds:[v.id],tours:[]});hotels.get(v.id).tours.push(v.tour);}return [...hotels.values()];}};
 let fetchImpl=async (url,body)=>({ok:true,data:url.includes('api-andromeda-')?samoPage(body.page):page(body.page)});
 let apiImpl=async(action)=>action==='search_status'?{progress:100,status:'complete'}:action==='search_results'?[]:{};
 const sandbox={console,URL,Map,Set,WeakMap,AbortController,TextEncoder,Date:ClockDate,structuredClone,Uint8Array,setTimeout,clearTimeout,DOMException,
  fetch:async(url,options)=>{const body=JSON.parse(options.body);calls.push({url,body});const result=await fetchImpl(url,body);return {ok:result.ok!==false,status:result.status||200,json:async()=>result};}};
 const root={location:{origin,href:origin+'/_preview/search3-next-candidate/visual-search/'},crypto:webcrypto,TextEncoder,
  V2_CONFIG:{anexApi:endpoint,andromedaApi:origin+'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'},
  V2Runtime:{api:async(action,p)=>{apiCalls.push({action,params:p});return apiImpl(action,p);},setSearchId(){}},
  Search3CanonicalProfilesV1:{create:()=>profile}};
 sandbox.window=root;
 const anchor='  root.AnyTourPrototypeData=Object.freeze({';
 assert.equal(source.split(anchor).length,2,'canonical export anchor');
 const instrument=`  root.__test={applyDirectAnex,applyDirectAndromeda,enrichAnex,continueSearch,pollSearch,stop,
 available:typeof anexContinuationAvailable==='function'?anexContinuationAvailable:()=>false,
set(run,callback){activeSearch=run;generation=run.generation;searchId=run.searchId;raw=[];context=${JSON.stringify({country:'4',adults:2,ages:[],origin:'Москва',from:params.dateFrom,to:params.dateTo,minNights:7,maxNights:7})};notify=callback;},
offers(){return project(owner.read(raw,{}),context).flatMap(h=>h.offers);},
receipt(o){const key=anexConcreteKey(o);return key&&anexPackageReceipts.get(key.key);},
applied(value){context=structuredClone(value);},
epoch(value){generation=value;activeSearch.generation=value;},
search(value){searchId=value;activeSearch.searchId=value;},
snapshot(){return JSON.stringify({generation,searchId,context,current:[...anexCurrentReceipts],receipts:[...anexPackageReceipts].map(([key,r])=>({key,...r,pending:Boolean(r.pending),quotes:[...r.quotes]})),active:Boolean(activeVerification),deadline:activeSearch.deadline});},
 continuation:typeof anexPageContinuation==='function'?anexPageContinuation:()=>null};\n`;
 vm.runInNewContext(source.replace(anchor,instrument+anchor),sandbox,{filename:'actual-data.js'});
 const run={generation:1,searchId:7,controller:new AbortController(),filters:{},pending:false,canContinue:true,tvCanContinue:false,resumeOnly:false,
  continued:false,expired:false,sourceCounts:{},lastProgress:-10,lastRead:0,deadline:0};
 let notify=event=>events.push(event);
 root.__test.set(run,event=>notify(event));
 return {run,calls,events,apiCalls,stored,t:root.__test,root,advance(ms){clock+=ms;},
  setFetch(fn){fetchImpl=fn;},setApi(fn){apiImpl=fn;},setNotify(fn){notify=fn;},
  initial(data=page(),index=0,total=1){return root.__test.applyDirectAnex(run,data,params,index,total);},
  samo(data=samoPage(),index=0,total=1){return root.__test.applyDirectAndromeda(run,data,params,index,total);}};
}
const tests=[];function test(name,fn){tests.push([name,fn]);}
test('initial endpoint rejection retains only its public code and HTTP status',async()=>{
 for(const [code,status] of [['invalid_request',400],['forbidden',403],['search_not_supported',422],['rate_limited',429],['supplier_timeout',502],['temporarily_unavailable',503]]){
  const h=harness();h.setFetch(async()=>({ok:false,status,error:code,message:'PRIVATE_MESSAGE',url:'PRIVATE_URL',diagnostic:{token:'PRIVATE_TOKEN'}}));
  await h.t.enrichAnex(h.run,params);
  const result=JSON.parse(JSON.stringify(h.run.sourceCounts.anex));
  assert.deepEqual(result,{status:'error',hotels:0,offers:0,failure:{stage:'search',code,httpStatus:status}});
  assert.deepEqual(JSON.parse(JSON.stringify(h.events.at(-1))),{type:'provider',provider:'anex',...result});
  await h.t.pollSearch(h.run);
  assert.deepEqual(JSON.parse(JSON.stringify(h.events.at(-1).sources.anex)),result,'terminal snapshot retains the failure');
  assert.equal(h.calls.length,1,'observation never retries the initial request');
  assert(!JSON.stringify(h.events).includes('PRIVATE_'),'private response fields never reach subscribers');
 }
});
test('unknown rejection code and invalid HTTP status stay unclassified without leaking text',async()=>{
 const h=harness();h.setFetch(async()=>({ok:false,status:999,error:'PRIVATE_CODE',message:'PRIVATE_MESSAGE'}));
 await h.t.enrichAnex(h.run,params);
 assert.deepEqual(JSON.parse(JSON.stringify(h.run.sourceCounts.anex.failure)),{stage:'search',code:'unknown'});
 assert(!JSON.stringify(h.events).includes('PRIVATE_'));assert.equal(h.calls.length,1);
});
test('transport exception is observable without exporting its message or guessing HTTP status',async()=>{
 const h=harness();h.setFetch(async()=>{throw new Error('PRIVATE_TRANSPORT_URL_AND_TOKEN');});
 await h.t.enrichAnex(h.run,params);
 assert.deepEqual(JSON.parse(JSON.stringify(h.run.sourceCounts.anex.failure)),{stage:'search',code:'unknown'});
 assert(!JSON.stringify(h.events).includes('PRIVATE_'));assert.equal(h.calls.length,1);
});
test('continued endpoint rejection retains diagnostics and the consumed page fence',async()=>{
 const h=harness();await h.initial();h.setFetch(async()=>({ok:false,status:502,error:'supplier_timeout',message:'PRIVATE_MESSAGE'}));
 await h.t.continueSearch();
 assert.deepEqual(JSON.parse(JSON.stringify(h.run.sourceCounts.anex.failure)),{stage:'continue',code:'supplier_timeout',httpStatus:502});
 assert.equal(h.stored.size,1);assert.equal(h.run.sourceCounts.anex.status,'partial');
 assert.deepEqual(JSON.parse(JSON.stringify(h.events.at(-1).sources.anex.failure)),{stage:'continue',code:'supplier_timeout',httpStatus:502});
 assert.equal(await h.t.continueSearch(),false);assert.equal(h.calls.length,1);assert(!JSON.stringify(h.events).includes('PRIVATE_'));
});
test('stopped initial search cannot publish a late rejected response',async()=>{
 const h=harness(),d=deferred();h.setFetch(()=>d.promise);
 const pending=h.t.enrichAnex(h.run,params);h.t.stop();
 d.resolve({ok:false,status:429,error:'rate_limited'});await pending;
 assert.equal(h.events.filter(event=>event.status==='error').length,0);assert.equal(h.run.sourceCounts.anex,undefined);assert.equal(h.calls.length,1);
});
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
// Fictional supplier envelopes exercise the real receipt owner and explicit
// verification path. A comparison read never executes this transport.
const pairRef=n=>'anex_quote:'+String(n).repeat(64);
async function pricedHarness({legacy=false,ids=[1]}={}){
 const h=harness(),used=new Map(),expires=Math.floor(Date.now()/1000)+600;
 h.run.anexSessionCurrent=true;await h.initial(page(1,'available',ids));
 const offers=h.t.offers(),choices=[1,2,3,4].map(n=>({choice_ref:pairRef(n),legs:[{label:'TEST OUT '+n},{label:'TEST BACK '+n}],current:false}));
 h.setFetch(async(url,body)=>{
  const identity={provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref};
  if(body.action==='offer')return{ok:true,data:{...identity,status:'current',selection_state:'disabled',offer:{final_price_verified:false},context:{status:'current',current_context_verified:true,selection_state:'disabled'},finalPriceReady:false}};
  let pairs=used.get(body.offer_ref);if(!pairs)used.set(body.offer_ref,pairs=new Set());
  const capability=()=>legacy?{}:{repricing:{enabled:true,max_pairs:3,used_pairs:pairs.size,remaining_pairs:3-pairs.size}};
  if(body.action==='quote_start')return{ok:true,data:{...identity,status:'quote_choices',selection_state:'disabled',final_price_verified:false,choices,...capability()}};
  assert.equal(body.action,'quote_calculate');pairs.add(body.choice_ref);
  const n=choices.findIndex(c=>c.choice_ref===body.choice_ref)+1;
  return{ok:true,data:{...identity,status:'quote_verified',selection_state:'preview_only',final_price_verified:true,choice:choices[n-1],choices,
   price:{amount:String(100000+n*1000),currency:'RUB',basis:'supplier_gross_package'},verified_at:Math.floor(Date.now()/1000),expires_at:expires,...capability()}};
 });
 for(const o of offers){await h.root.AnyTourPrototypeData.verifyAnexConcrete(o);await h.root.AnyTourPrototypeData.verifyAnexPackage(o);}
 return{...h,offers,o:offers[0],choices,used,calculate(o,n){return h.root.AnyTourPrototypeData.verifyAnexPackage(o,pairRef(n));},prices(o=offers[0]){return h.root.AnyTourPrototypeData.anexPackagePrices(o);},count(){return h.calls.filter(c=>c.body.action==='quote_calculate').length;}};
}
const facts=rows=>JSON.parse(JSON.stringify(rows.map(row=>({choice:row.choiceRef,amount:row.finalPrice.amount,currency:row.finalPrice.currency}))));
test('A/B/cached A compares two exact whole-party prices with zero read effects and no fourth operation',async()=>{
 const h=await pricedHarness();assert.deepEqual(facts(h.prices()),[],'uncalculated choices stay unknown');
 await h.calculate(h.o,1);await h.calculate(h.o,2);const cachedA=await h.calculate(h.o,1);assert.equal(cachedA.choice.choiceRef,pairRef(1));assert.equal(h.count(),2);
 const before=h.t.snapshot(),beforeCalls=h.calls.length,beforeApi=h.apiCalls.length,beforeEvents=h.events.length;
 for(let n=0;n<20;n++){
  const rows=h.prices();assert.deepEqual(facts(rows),[{choice:pairRef(1),amount:'101000',currency:'RUB'},{choice:pairRef(2),amount:'102000',currency:'RUB'}]);
  assert(Object.isFrozen(rows)&&rows.every(row=>Object.isFrozen(row)&&Object.isFrozen(row.finalPrice)));
  assert.throws(()=>{rows[0].finalPrice.amount='1';},TypeError);
 }
 assert.equal(h.t.snapshot(),before,'maps, chosen, queue, budget, expiry and applied context are unchanged');assert.equal(h.calls.length,beforeCalls);assert.equal(h.apiCalls.length,beforeApi);assert.equal(h.events.length,beforeEvents);
 await h.calculate(h.o,2);assert.equal(h.count(),2,'explicit cached B is also free');await h.calculate(h.o,3);assert.equal(h.count(),3);
 await assert.rejects(h.calculate(h.o,4),error=>error.code==='quote_pair_budget_exhausted');assert.equal(h.count(),3);
 assert.equal(h.prices().length,3,'healthy remaining0 preserves all known pairs');await h.calculate(h.o,1);assert.equal(h.count(),3);
});
test('comparison cannot create receipts or borrow another offer, search, trip or party',async()=>{
 const h=await pricedHarness();await h.calculate(h.o,1);const applied=structuredClone(h.o.search);
 const variants=[{key:'another-key'},{hotelId:2},{day:'2026-10-11'},{returnDay:'2026-10-18'},{nights:8},{adults:3},{ages:[0,8]},{origin:'Калининград'},
  {raw:{...h.o.raw,offerRef:offerRef(9)}},{raw:{...h.o.raw,searchRef:'9'.repeat(32)}},{raw:{...h.o.raw,anexLocalHotelId:2}},{raw:{...h.o.raw,anexGeneration:2}},
  {search:{...applied,to:'2026-10-15'}},{search:{...applied,country:'5'}},{search:{...applied,ages:[8,0]}},{search:{...applied,minNights:undefined}}];
 for(const change of variants){const before=h.t.snapshot(),calls=h.calls.length;assert.deepEqual(facts(h.prices({...h.o,...change})),[],JSON.stringify(change));assert.equal(h.t.snapshot(),before);assert.equal(h.calls.length,calls);}
 for(const change of [{country:'5'},{from:'2026-10-09'},{to:'2026-10-17'},{adults:3},{ages:[0,8]},{origin:'Калининград'},{minNights:undefined}]){
  h.t.applied({...applied,...change});const before=h.t.snapshot();assert.deepEqual(facts(h.prices()),[]);assert.equal(h.t.snapshot(),before);
 }
 h.t.applied(applied);assert.equal(h.prices().length,1);h.t.search(8);assert.deepEqual(facts(h.prices()),[]);h.t.search(7);h.t.epoch(2);assert.deepEqual(facts(h.prices()),[]);
});
test('own pending or UNKNOWN hides comparisons while an independent healthy offer stays intact',async()=>{
 const h=await pricedHarness({ids:[1,2]}),other=h.offers[1];await h.calculate(h.o,1);await h.calculate(other,1);
 const d=deferred();h.setFetch(()=>d.promise);const pending=h.calculate(h.o,2);assert.equal(h.count(),3);
 const before=h.t.snapshot(),calls=h.calls.length;assert.deepEqual(facts(h.prices()),[]);assert.equal(h.prices(other).length,1);assert.equal(h.t.snapshot(),before);assert.equal(h.calls.length,calls);
 d.resolve({ok:false,status:502,data:{status:'quote_unknown'}});await assert.rejects(pending);
 const failed=h.t.snapshot();assert.deepEqual(facts(h.prices()),[]);assert.equal(h.prices(other).length,1);assert.equal(h.t.snapshot(),failed);
 await assert.rejects(h.calculate(h.o,1));assert.equal(h.calls.length,calls,'UNKNOWN cannot replay even a former cached pair');
});
test('expired, revoked, queued or mismatched receipt facts expose no stale amount',async()=>{
 const h=await pricedHarness();await h.calculate(h.o,1);const receipt=h.t.receipt(h.o),original={...receipt};
 const faults=[{queuedChoice:pairRef(2)},{pendingChoice:pairRef(2)},{sealed:true},{revoked:true},{binding:null},{repricing:null},
  {inventory:{...receipt.inventory,choices:receipt.inventory.choices.map((c,i)=>i?c:{...c,legs:[{label:'OTHER OUT'},c.legs[1]]})}}];
 const restore=()=>{for(const key of Object.keys(receipt))if(!(key in original))delete receipt[key];Object.assign(receipt,original);};
 for(const fault of faults){restore();Object.assign(receipt,fault);const before=h.t.snapshot();assert.deepEqual(facts(h.prices()),[]);assert.equal(h.t.snapshot(),before);}
 restore();assert.equal(h.prices().length,1);h.advance(601000);const before=h.t.snapshot();assert.deepEqual(facts(h.prices()),[]);assert.equal(h.t.snapshot(),before);assert.equal(h.count(),1);
});
test('healthy pending B then queued cached A settles without another mutation or changing its retained queue',async()=>{
 const h=await pricedHarness();await h.calculate(h.o,1);const d=deferred(),transport=h.calls.length;
 h.setFetch(async(url,body)=>{await d.promise;return{ok:true,data:{provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'quote_verified',selection_state:'preview_only',final_price_verified:true,
  choice:h.choices[1],choices:h.choices,price:{amount:'102000',currency:'RUB',basis:'supplier_gross_package'},verified_at:Math.floor(Date.now()/1000),expires_at:h.t.receipt(h.o).expiresAt,repricing:{enabled:true,max_pairs:3,used_pairs:2,remaining_pairs:1}}};});
 const b=h.calculate(h.o,2),a=h.calculate(h.o,1);assert.deepEqual(facts(h.prices()),[],'pending cached draft has no authority');d.resolve();await b;const settled=await a;
 assert.equal(settled.choice.choiceRef,pairRef(1));assert.equal(h.calls.length,transport+1);assert.equal(h.t.receipt(h.o).queuedChoice,pairRef(1),'reader must not clear the existing retained queue field');
 const before=h.t.snapshot();assert.deepEqual(facts(h.prices()),[{choice:pairRef(1),amount:'101000',currency:'RUB'},{choice:pairRef(2),amount:'102000',currency:'RUB'}]);assert.equal(h.t.snapshot(),before);
});
test('comparison rejects absent gross/RUB/final/pair provenance without changing normalized results',async()=>{
 const h=await pricedHarness();await h.calculate(h.o,1);const receipt=h.t.receipt(h.o),result=receipt.quotes.get(pairRef(1));
 for(const fault of [{priceBasis:undefined},{finalPriceVerified:false},{finalPrice:{amount:'101000',currency:'USD'}},{verifiedAt:0},
  {choice:{...result.choice,choiceRef:pairRef(2)}},{choice:{...result.choice,legs:[{label:'OTHER OUT'},result.choice.legs[1]]}},{choices:undefined}]){
  receipt.quotes.set(pairRef(1),Object.freeze({...result,...fault}));const before=h.t.snapshot();assert.deepEqual(facts(h.prices()),[]);assert.equal(h.t.snapshot(),before);
 }
 receipt.quotes.set(pairRef(1),result);assert.equal(h.prices().length,1);assert.equal(h.count(),1);
});
test('legacy one-choice lock stays unchanged and has no comparison authority',async()=>{
 const h=await pricedHarness({legacy:true});await h.calculate(h.o,1);assert.deepEqual(facts(h.prices()),[]);const before=h.t.snapshot();await h.calculate(h.o,1);assert.equal(h.count(),1);assert.equal(h.t.snapshot(),before);
 await assert.rejects(h.calculate(h.o,2),/уже выполнен/);assert.equal(h.count(),1);assert.deepEqual(facts(h.prices()),[]);
});
(async()=>{let failed=0;for(const [name,fn] of tests){try{await fn();console.log('PASS '+name);}catch(e){failed++;console.error('FAIL '+name+'\n'+e.stack);}}console.log(JSON.stringify({suite:'actual-canonical-adapter-anex-continuation',passed:tests.length-failed,failed,tests:tests.length,network:'mocked',realSupplierRequests:0}));if(failed)process.exitCode=1;})();
