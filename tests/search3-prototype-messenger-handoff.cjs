'use strict';
// Actual canonical dispatcher functions, synthetic fetch only; no supplier/bot IO.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/data.js'),'utf8');
const nativeStart=source.indexOf('  function nativeEndpoint('),nativeEnd=source.indexOf('\n  const anexSearchFailureCodes',nativeStart);
const start=source.indexOf('  async function messengerHandoff('),end=source.indexOf('  async function verifyAnexFollowUp(',start);
assert.ok(nativeStart>=0&&nativeEnd>nativeStart&&start>=0&&end>start);
assert.match(source,/root\.AnyTourPrototypeData=Object\.freeze\(\{[^\n]*\bmessengerHandoff\b/);
const endpoint='https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php',csrf='a'.repeat(64),operation_id='b'.repeat(32);
const selector={generation:7,search_ref:'c'.repeat(32),offer_ref:'anex_online:'+'d'.repeat(64),local_hotel_id:3634,choice_ref:'anex_quote:'+'e'.repeat(64),csrf};
function setup(handler){
  const calls=[],timers=new Map(),cleared=[],io=[];let timerId=0;
  const root={location:{href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/',origin:'https://anytoour.ru'},V2_CONFIG:{anexApi:endpoint}};
  const ctx=vm.createContext({root,URL,URLSearchParams,AbortController,TextEncoder,
    setTimeout(fn,ms){const id=++timerId;timers.set(id,{fn,ms});return id;},clearTimeout(id){cleared.push(id);timers.delete(id);},
    fetch:async(url,options)=>{calls.push({url,options});if(handler)return handler(url,options);return {ok:true,text:async()=>JSON.stringify(options.method==='GET'?{ok:true,csrf}:{ok:true,data:{operation_id,state:'prepared'}})};},
    document:new Proxy({},{get(){io.push('DOM');throw Error('unexpected DOM');}}),localStorage:new Proxy({},{get(){io.push('storage');throw Error('unexpected storage');}})});
  vm.runInContext(source.slice(nativeStart,nativeEnd)+source.slice(start,end)+'\nglobalThis.dispatch=messengerHandoff;',ctx);
  return {root,dispatch:ctx.dispatch,calls,timers,cleared,io};
}
const plain=x=>JSON.parse(JSON.stringify(x)),tests=[];const test=(name,fn)=>tests.push({name,fn});
test('Token uses the exact same-origin GET with private credential/cache/referrer policy',async()=>{
  const x=setup(),result=await x.dispatch('handoff_token');assert.deepEqual(plain(result),{ok:true,csrf});assert.equal(Object.isFrozen(result),true);
  assert.equal(x.calls.length,1);const {url,options}=x.calls[0];assert.equal(url,endpoint+'?action=handoff_token');assert.equal(options.method,'GET');assert.equal(options.body,undefined);
  for(const [key,value] of Object.entries({credentials:'same-origin',mode:'same-origin',cache:'no-store',redirect:'error',referrerPolicy:'no-referrer'}))assert.equal(options[key],value);
  assert.equal(x.cleared.length,1);assert.equal(x.timers.size,0);assert.deepEqual(x.io,[]);
});
test('Freeze sends only the accepted exact selector and token, with no browser observation or price',async()=>{
  const x=setup();await x.dispatch('handoff_freeze',selector);const {url,options}=x.calls[0];assert.equal(url,endpoint);assert.equal(options.method,'POST');
  assert.deepEqual(JSON.parse(options.body),{action:'handoff_freeze',...selector});assert.equal(options.headers['Content-Type'],'application/json');assert.equal(options.headers['X-Requested-With'],'AnyTourSearch3');
  assert.doesNotMatch(options.body,/"(?:amount|price|observation|phone|party|signature|key_id|url)"/);
});
test('Create/status/revoke retain the exact original operation body',async()=>{
  for(const action of ['handoff_create','handoff_status','handoff_revoke']){
    const x=setup();await x.dispatch(action,{operation_id,csrf});assert.equal(x.calls.length,1);assert.deepEqual(JSON.parse(x.calls[0].options.body),{action,operation_id,csrf});
  }
});
test('Unknown actions, extra fields, malformed selector/token and arbitrary destinations never fetch',async()=>{
  const variants=[['create',{}],['toString',{}],['__proto__',{}],['handoff_token',{url:endpoint}],['handoff_create',{operation_id,csrf,observation:{}}],['handoff_freeze',{...selector,choice_ref:'0'}],['handoff_freeze',{...selector,generation:'7'}],['handoff_freeze',{...selector,local_hotel_id:0}],['handoff_create',{operation_id,csrf:'bad'}],['handoff_status',[]]];
  for(const [action,fields] of variants){const x=setup();await assert.rejects(x.dispatch(action,fields),e=>e.code==='invalid_request');assert.equal(x.calls.length,0);assert.equal(x.timers.size,0);}
});
test('Existing native endpoint owner blocks foreign origin/path/query/hash and URL credentials',async()=>{
  for(const url of [endpoint.replace('anytoour.ru','evil.test'),endpoint.replace('api-anex-search3-preview.php','other.php'),endpoint+'?x=1',endpoint+'#x',endpoint.replace('https://','https://user:pass@')]){
    const x=setup();x.root.V2_CONFIG.anexApi=url;await assert.rejects(x.dispatch('handoff_token'),e=>e.code==='handoff_unavailable');assert.equal(x.calls.length,0);
  }
});
test('Allowed server failures retain only a fixed code, without private diagnostics',async()=>{
  const x=setup(()=>({ok:false,text:async()=>JSON.stringify({ok:false,error:'handoff_stale',detail:'PRIVATE_RECEIPT_REF'})}));
  await assert.rejects(x.dispatch('handoff_create',{operation_id,csrf}),e=>e.code==='handoff_stale'&&!e.message.includes('PRIVATE'));assert.equal(x.calls.length,1);assert.equal(x.timers.size,0);
  const y=setup(()=>({ok:false,text:async()=>JSON.stringify({ok:false,error:'PRIVATE_RECEIPT_REF'})}));await assert.rejects(y.dispatch('handoff_status',{operation_id,csrf}),e=>e.code==='handoff_unavailable'&&!e.message.includes('PRIVATE'));
});
test('Lost create response has one attempt and no automatic retry or new freeze',async()=>{
  const x=setup(()=>{throw Error('PRIVATE_TRANSPORT_DIAGNOSTIC');});await assert.rejects(x.dispatch('handoff_create',{operation_id,csrf}),e=>e.code==='handoff_unknown'&&!e.message.includes('PRIVATE'));
  assert.equal(x.calls.length,1);assert.equal(x.timers.size,0);assert.deepEqual(x.io,[]);
});
test('Malformed, oversized UTF-8 and incomplete successful responses are unavailable',async()=>{
  for(const raw of ['not-json',JSON.stringify({ok:true,data:{text:'я'.repeat(18000)}}),JSON.stringify({ok:true,data:[]}),JSON.stringify({ok:true})]){
    const x=setup(()=>({ok:true,text:async()=>raw}));await assert.rejects(x.dispatch('handoff_status',{operation_id,csrf}),e=>e.code==='handoff_unavailable');assert.equal(x.calls.length,1);assert.equal(x.timers.size,0);
  }
  const x=setup(()=>({ok:true,text:async()=>JSON.stringify({ok:true,csrf:'bad'})}));await assert.rejects(x.dispatch('handoff_token'),e=>e.code==='handoff_unavailable');
});
test('The 15s request deadline aborts its own request and clears the timer without replay',async()=>{
  const x=setup((url,options)=>new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(Error('aborted'))))),pending=x.dispatch('handoff_create',{operation_id,csrf});
  assert.equal(x.timers.size,1);const timer=[...x.timers.values()][0];assert.equal(timer.ms,15000);timer.fn();
  await assert.rejects(pending,e=>e.code==='handoff_unknown');assert.equal(x.calls[0].options.signal.aborted,true);assert.equal(x.calls.length,1);assert.equal(x.timers.size,0);
});
// Exercise the complete real data owner, including search/poll/projection/quote.
// Only transports, canonical-profile reads and timers are fictional here.
function tvSetup(change){
 const crypto=require('node:crypto'),calls=[],rootCalls=[],events=[],timers=new Map();let timer=0;
 const search={origin:'Москва',country:'4',from:'2027-03-29',to:'2027-03-29',minNights:7,maxNights:7,adults:3,ages:[0,12]};
 const tour={id:'tv-current-1',price:{value:200000,currency:'RUB'},date:search.from,nights:7,departure:'Москва',roomType:'FAMILY',meal:{name:'AI'},operator:{name:'ANEX'}};
 const leg=n=>({number:n,departure:{date:search.from,port:{id:'SVO'}},arrival:{port:{id:'AYT'}}});
 const pairs=[{price:{value:200000,currency:'RUB'},forward:[leg('A1')],backward:[leg('A2')]},{price:{value:231456,currency:'RUB'},forward:[leg('B1')],backward:[leg('B2')]}];
 const ref='tourvisor:'+crypto.createHash('sha256').update('tourvisor:tour:'+tour.id).digest('hex'),choice='tourvisor_pair:'+'e'.repeat(64);
 const pins={provider:'tourvisor',generation:23,search_ref:'d'.repeat(32),offer_ref:ref,local_hotel_id:101,choice_ref:'manager-deferred'};
 const window={location:{href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/',origin:'https://anytoour.ru'},crypto:crypto.webcrypto,TextEncoder,
  V2_CONFIG:{anexApi:endpoint,search3TVSessionEnabled:true},V2Runtime:{setSearchId(){},api:async(action,body)=>{rootCalls.push({action,body});throw Error('Root TV fallback is forbidden');}},
  V2TourController:{createLeadSession:()=>({payload:()=>({ok:true}),submit:()=>({ok:true})})},
  Search3CanonicalProfilesV1:{create(){return {reset(){},clearOffers(){},refresh(){},read:rows=>rows.map(h=>({...h,anytourHotelId:501,canonicalLegacyIds:[101],name:'Canonical family hotel'}))};}}};
 const reply=(data,status=200)=>({ok:status===200,status,text:async()=>JSON.stringify(data),json:async()=>data});
 const ctx=vm.createContext({window,URL,URLSearchParams,AbortController,TextEncoder,structuredClone,
  setTimeout(fn,ms){const id=++timer;timers.set(id,{fn,ms});return id;},clearTimeout:id=>timers.delete(id),
  fetch:async(url,options)=>{
   assert.equal(new URL(url).pathname,new URL(endpoint).pathname);const body=options.body?JSON.parse(options.body):{},action=new URL(url).searchParams.get('action')||body.action;
   const call={action,body,options};calls.push(call);if(change){const value=await change(call);if(value!==undefined)return value;}
   if(action==='tv_token')return reply({ok:true,csrf,provider:'tourvisor'});
   if(!action.startsWith('tv_')&&!action.startsWith('handoff_'))return reply({ok:false,error:'temporarily_unavailable'},503);
   if(action==='handoff_freeze')return reply({ok:true,data:{operation_id,state:'prepared'}});
   assert.equal(body.provider,'tourvisor');assert.equal(body.csrf,csrf);assert.match(body.request_id,/^[a-f0-9]{32}$/);
   if(action!=='tv_search'){assert.equal(body.generation,pins.generation);assert.equal(body.search_ref,pins.search_ref);}
   let data=null,extra={};
   if(action==='tv_search')data={searchId:88};
   else if(action==='tv_status')data={progress:100,status:'complete'};
   else if(action==='tv_results'){data=[{id:'101',provider:'tourvisor',tours:[tour]}];extra.offers=[{offer_ref:ref,local_hotel_id:101}];}
   else if(action==='tv_tour'){assert.equal(body.offer_ref,ref);data=plain(tour);extra={selector:pins,record_sha256:'a'.repeat(64)};}
   else if(action==='tv_flights'){data=plain(pairs);extra.choices=[{index:1,choice_ref:choice}];}
   else if(action==='tv_select'){assert.equal(body.offer_ref,ref);extra={selector:{...pins,choice_ref:body.choice_ref},record_sha256:'b'.repeat(64)};}
   else if(action!=='tv_continue')throw Error('Unexpected private action '+action);
   return reply({ok:true,data:{provider:'tourvisor',generation:pins.generation,search_ref:pins.search_ref,data,...extra}});
  }});
 vm.runInContext(source,ctx);const api=window.AnyTourPrototypeData;
 api.catalog.departures=[{id:1,name:'Москва'}];api.catalog.countries=[{id:4,name:'Турция',tourvisorIds:['4']}];
 const start=async()=>{await api.search(search,event=>events.push(event));const poll=[...timers].find(([,value])=>value.ms===1000);if(poll){timers.delete(poll[0]);await poll[1].fn();}return events.filter(event=>event.type==='results').at(-1)?.hotels[0];};
 const checked=async h=>{const o=h.offers[0],tour=await api.quote(o);return {...o,tour,total:api.amount(tour.price),room:api.text(tour.roomType),meal:api.meal(tour.meal)};};
 return {window,api,calls,rootCalls,events,timers,search,pins,ref,choice,tour,start,checked,reply};
}
test('Full TV search uses one private token before parallel providers and never calls root',async()=>{
 const x=tvSetup(),h=await x.start(),o=await x.checked(h);
 assert.equal(x.calls[0].action,'tv_token');assert.equal(x.calls.filter(c=>c.action==='tv_token').length,1);assert.equal(x.api.searchId,88);
 assert.equal(h.id,501);assert.deepEqual(plain(h.legacyIds),['101']);assert.deepEqual(plain(x.api.tourvisorHandoffSelector(o,h)),x.pins);
 await Promise.all([x.api.quote(o),x.api.quote(o)]);assert.equal(x.calls.filter(c=>c.action==='tv_tour').length,1,'repeat quote consumers share the original operation');
 await x.api.messengerHandoff('handoff_freeze',{...x.pins,csrf});await x.api.messengerHandoff('handoff_freeze',{...x.pins,csrf});
 assert.equal(x.calls.filter(c=>c.action==='tv_select').length,1,'reopen reuses its retained local selection');
 assert.equal(x.calls.filter(c=>c.action==='tv_token').length,1);assert.deepEqual(x.rootCalls,[]);
 for(const c of x.calls.filter(c=>c.action==='tv_tour'||c.action==='tv_select'||c.action==='handoff_freeze'))assert.doesNotMatch(JSON.stringify(c.body),/"(?:price|amount|party|phone|observation|tourId)"/);
 const ids=x.calls.filter(c=>c.body.request_id).map(c=>c.body.request_id);assert.equal(new Set(ids).size,ids.length);
});
test('Only a server-returned exact pair with its own total can supply TV pins',async()=>{
 const x=tvSetup(),h=await x.start(),o=await x.checked(h),pairs=await x.api.flights(o.tour);
 const chosen={...o,variants:pairs,flightChoiceId:'1',total:231456};
 assert.equal(x.api.tourvisorHandoffSelector(chosen,h).choice_ref,x.choice);
 assert.equal(x.api.tourvisorHandoffSelector({...chosen,total:200000},h),null);
 assert.equal(x.api.tourvisorHandoffSelector({...chosen,flightChoiceId:'0',total:200000},h),null,'complete but unretained pair is not an authority');
 assert.equal(x.api.tourvisorHandoffSelector({...chosen,variants:plain(pairs)},h),null);
 assert.equal(x.api.tourvisorHandoffSelector({...chosen,raw:{...chosen.raw}},h),null);
 assert.equal(x.api.tourvisorHandoffSelector(chosen,{...h,legacyIds:[]}),null);
 const count=x.calls.length;x.api.stop();assert.equal(x.api.tourvisorHandoffSelector(chosen,h),null);
 await assert.rejects(x.api.messengerHandoff('handoff_freeze',{...x.pins,csrf}),e=>e.code==='tourvisor_session_unavailable');assert.equal(x.calls.length,count);
});
test('A lost TV quote or flight response is retained without a new ID, retry or root fallback',async()=>{
 for(const action of ['tv_tour','tv_flights']){
  const x=tvSetup(call=>{if(call.action===action)throw Error('PRIVATE_TRANSPORT_DIAGNOSTIC');}),h=await x.start();
  let request;if(action==='tv_tour')request=()=>x.api.quote(h.offers[0]);else{const o=await x.checked(h);request=()=>x.api.flights(o.tour);}
  for(let i=0;i<2;i++)await assert.rejects(request(),e=>e.retryable===false&&!e.message.includes('PRIVATE'));
  assert.equal(x.calls.filter(c=>c.action===action).length,1);assert.deepEqual(x.rootCalls,[]);
 }
});
test('Unreturned or foreign result membership cannot quote, and late replies cannot freeze a new search',async()=>{
 let x;x=tvSetup(call=>call.action==='tv_results'?x.reply({ok:true,data:{provider:'tourvisor',generation:23,search_ref:'d'.repeat(32),data:[{id:101,tours:[x.tour]}],offers:[]}}):undefined);
 const h=await x.start();await assert.rejects(x.api.quote(h.offers[0]),e=>e.retryable===false);assert.equal(x.calls.some(c=>c.action==='tv_tour'),false);
 const y=tvSetup(),hotel=await y.start(),old=await y.checked(hotel);await y.start();
 assert.equal(y.api.tourvisorHandoffSelector(old,hotel),null);assert.deepEqual(y.rootCalls,[]);
});
test('Private TV410 revokes the exact checked receipt and already-created contact session without replay',async()=>{
 let x;x=tvSetup(call=>call.action==='tv_flights'?x.reply({ok:false,error:'tv_offer_expired'},410):undefined);
 const h=await x.start(),o=await x.checked(h),session=x.api.leadSession(o);assert(x.api.tourvisorHandoffSelector(o,h));
 await assert.rejects(x.api.flights(o.tour),e=>e.code==='offer_expired'&&e.status===410&&e.retryable===false);
 assert.equal(x.api.tourvisorHandoffSelector(o,h),null);
 for(const fn of [()=>x.api.leadSession(o),()=>session.payload({}),()=>session.submit({})])assert.throws(fn,e=>e.code==='offer_expired');
 await assert.rejects(x.api.quote(o),e=>e.code==='offer_expired');await assert.rejects(x.api.flights(o.tour),e=>e.code==='offer_expired');
 assert.equal(x.calls.filter(c=>c.action==='tv_flights').length,1);assert.equal(x.calls.filter(c=>c.action==='tv_tour').length,1);assert.deepEqual(x.rootCalls,[]);
});
test('Private token failure is terminal on this page and does not send supplier search or fallback',async()=>{
 const x=tvSetup(call=>{if(call.action==='tv_token')throw Error('private failure');});await x.start();await x.start();
 assert.deepEqual(x.calls.map(c=>c.action),['tv_token']);assert.deepEqual(x.rootCalls,[]);assert.equal(x.events.at(-1).canContinue,false);
});
(async()=>{for(const {name,fn} of tests){await fn();console.log('PASS '+name);}console.log(JSON.stringify({groups:tests.length,result:'PASS',supplierRequests:0,botMessages:0,storageWrites:0,runtimeConnected:false}));})().catch(e=>{console.error(e);process.exitCode=1;});
