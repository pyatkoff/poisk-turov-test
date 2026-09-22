'use strict';
// Fictional inventory only. Execute the actual adapter, canonical owner and DB
// parser; every HTTP/rt.api response is controlled. No supplier or database I/O.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path'),crypto=require('node:crypto');
const rootDir=path.resolve(__dirname,'..');
const trip={origin:'Москва',country:'4',from:'2026-09-29',to:'2026-10-05',minNights:7,maxNights:7,adults:2,ages:[]};
const hash=x=>crypto.createHash('sha256').update(x).digest('hex');
const profile=id=>({id:Number(id)+400,catalog:'anytour',revision:1,name:'FICTIONAL HOTEL '+id,category:5,country:{id:4,name:'Турция'},images:[]});
function snapshot(params,providers=['tourvisor']){
 return {source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:hash('fixture'),scope:{...params,scopeVersion:1},selectionAuthority:false,hotels:providers.length?[{anytourHotelId:501,hotel:profile(101),offers:providers.map(provider=>({provider,legacyHotelId:'101',currency:'RUB',price:1500000,listing:{schema_version:1,provider,currency:'RUB',selection_state:'refresh_required',booking_enabled:false,listingPrice:1500000,listingPriceState:'search_price_confirmation_required',listingPriceReady:false,priceConfirmationRequired:true,quoteState:'unknown',finalPriceVerified:false,quoteEvidenceDigest:null,identity:{offer_ref_digest:hash(provider),search_ref_digest:hash('search-'+provider),provider_hotel_ref_digest:hash('hotel-'+provider)},tour:{checkin:params.dateFrom,nights:7,meal:{raw:'AI'},room:{raw:'STANDARD'},placement:{raw:'DBL'},party:{adults:2,children:0}},operator:{raw:'FICTIONAL '+provider}}}))}]:[]};
}
const live=(n=1)=>Array.from({length:n},(_,i)=>({id:101+i,provider:'tourvisor',tours:[{id:'fictional-live-'+i,provider:'tourvisor',price:1500000+i,date:trip.from,nights:7,meal:{name:'AI'},roomType:'STANDARD',operator:{name:'FICTIONAL TV'}}]}));
const defer=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
const flush=async()=>{for(let i=0;i<8;i++)await new Promise(setImmediate);};
function harness({database,api,onEvent,native,clock=()=>Date.now()}={}){
 const events=[],calls=[],dbBodies=[],nativeCalls=[],timers=new Map();let timerId=0,readIndex=0,currentId=0;
 const fetch=async(url,options={})=>{
  const target=new URL(url,'https://anytoour.ru/');
  if(target.pathname==='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'){
   assert.ok(native,'unexpected Andromeda request');
   const body=JSON.parse(options.body);nativeCalls.push(structuredClone(body));
   const result=await native(body,options.signal,nativeCalls);
   if(result&&result.response)return result.response;
   return {ok:true,json:async()=>({ok:true,data:{provider:'andromeda',generation:body.generation,hotels:[]}})};
  }
  if(String(url).includes('search3-local-results-read')){
   const params=JSON.parse(options.body).params;dbBodies.push(structuredClone(params));
   const data=database?await database(++readIndex,params,options.signal):snapshot(params);
   return {ok:true,json:async()=>({ok:true,data})};
  }
  const ids=target.searchParams.getAll('legacyHotelIds[]');
  assert.ok(target.pathname.endsWith('/data/hotel-details-read-v1.php'),'unexpected request '+url);
  return {ok:true,json:async()=>({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:ids,items:ids.map(profile),links:ids.map(id=>({legacyHotelId:id,anytourHotelId:Number(id)+400})),missingLegacyIds:[]})};
 };
 const runtime={setSearchId:id=>currentId=id,fetch,api:async(action,params)=>{
  calls.push({action,params:structuredClone(params)});
  if(api){const result=await api(action,params,calls);if(result!==undefined)return result;}
  if(action==='search_start')return {searchId:9001};
  if(action==='search_continue')return {requestCount:1};
  if(action==='search_status')return {searchId:9001,status:'complete',progress:100};
  if(action==='search_results')return live();
  throw Error('unexpected API '+action);
 }};
 const win={V2Runtime:runtime,location:new URL('https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'),fetch,setTimeout:(fn,delay)=>{const id=++timerId;timers.set(id,{fn,delay});return id;},clearTimeout:id=>timers.delete(id)};
 if(native)win.V2_CONFIG={andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'};
 const bus=new EventTarget();win.addEventListener=bus.addEventListener.bind(bus);win.removeEventListener=bus.removeEventListener.bind(bus);win.dispatchEvent=bus.dispatchEvent.bind(bus);
 const sandbox={window:win,fetch,URL,URLSearchParams,AbortController,DOMException,structuredClone,console,Date:class extends Date{static now(){return clock();}},setTimeout:win.setTimeout,clearTimeout:win.clearTimeout};
 vm.createContext(sandbox);
 for(const name of ['search3-canonical-profiles-v1.js','search3-local-db-provider-v1.js','prototype-search/data.js'])vm.runInContext(fs.readFileSync(path.join(rootDir,'v2',name),'utf8'),sandbox,{filename:name});
 const data=win.AnyTourPrototypeData;data.catalog.departures.push({id:1,name:'Москва'});data.catalog.countries.push({id:4,name:'Турция'});
 const start=async(filters={min:0,max:null})=>{await data.search(structuredClone(trip),event=>{events.push(event);onEvent?.(event,data);},[],filters);await flush();};
 const poll=async()=>{const entry=[...timers].find(([,value])=>value.delay<=2500);assert.ok(entry,'pending poll required');timers.delete(entry[0]);await entry[1].fn();await flush();};
 const latest=()=>events.filter(e=>e.type==='results').at(-1)?.hotels||[];
 const providers=()=>[...new Set(latest().flatMap(h=>h.offers.map(o=>o.provider)))].sort();
 return {data,start,poll,events,calls,dbBodies,nativeCalls,latest,providers,timers,get searchId(){return currentId;}};
}
const tests=[];const test=(name,fn)=>tests.push([name,fn]);
test('hotel projection exposes saved structured amenities without inventing missing facts',()=>{
 const h=harness(),raw={...profile(101),hotelInformation:{services:{tags:[
  {id:5,name:'Услуги',items:[{id:23,name:'Бассейн'},{id:23,name:'Бассейн'},null]},
  {id:3,name:'Пляж',items:[{id:15,name:'Первая линия'}]},
  {id:7,name:'Доп.фильтры',items:[{id:46,name:'Гарантия мест'}]}
 ]}},tours:live()[0].tours};
 const projected=h.data.project([raw],trip)[0];
 assert.deepEqual(Array.from(projected.amenities,a=>a.key),['5:23','3:15']);
 assert.equal(projected.amenities[0].label,'Бассейн');assert.equal(projected.beach,null,'First line is not a measured distance');
 const missing=h.data.project([{...profile(102),description:'There might be a pool',tours:live()[0].tours}],trip)[0];
 assert.equal(missing.amenities.length,0,'No inference from prose or missing details');
 const app=fs.readFileSync(path.join(rootDir,'v2/prototype-search/app.js'),'utf8');
 const source=app.slice(app.indexOf('function hotelMatch('),app.indexOf('\nfunction hotelOffers('));
 const match=vm.runInNewContext('('+source+')',{ratingValue:h=>h.rating});
 const filters={hotelId:0,q:'',stars:[],resorts:[],amenities:['5:23','3:15']};
 assert.equal(match(projected,filters,trip,false),true);
 assert.equal(match({...projected,amenities:projected.amenities.slice(0,1)},filters,trip,false),false,'Selected amenities use AND on the same hotel');
 assert.equal(match(missing,filters,trip,false),false,'Unknown amenities are not positive matches');
 assert.equal(match(missing,{...filters,amenities:[]},trip,false),true,'Missing amenities do not hide unfiltered hotels');
});
test('unlimited and explicit premium budgets use the exact request',async()=>{
 const h=harness();assert.equal(h.data.params(trip).priceTo,'');
 for(const max of [600000,1500000,25000000])assert.equal(h.data.params(trip,[],{min:0,max}).priceTo,String(max));
 assert.equal(h.data.params(trip,[],{min:0,max:0}).priceTo,'0');
});
test('initial three-provider DB inventory survives TV and completion reread',async()=>{
 const h=harness({database:(i,p)=>snapshot(p,['tourvisor','anex','andromeda'])});await h.start();await h.poll();
 assert.deepEqual(h.providers(),['andromeda','anex','tourvisor']);assert.equal(h.dbBodies.length,2);
 assert.ok(h.latest()[0].offers.every(o=>o.total>=1500000));
 assert.equal(h.events.at(-1).type,'database');
 assert.ok(h.events.some(e=>e.type==='complete'&&e.canContinue));
});
test('offers stored during a search are loaded without another search start',async()=>{
 const h=harness({database:(i,p)=>snapshot(p,i===1?['tourvisor']:['tourvisor','anex','andromeda'])});
 await h.start();assert.deepEqual(h.providers(),['tourvisor']);await h.poll();assert.deepEqual(h.providers(),['andromeda','anex','tourvisor']);
 assert.equal(h.calls.filter(c=>c.action==='search_start').length,1);
});
test('one user search invokes Andromeda autosave once and rereads LOCAL after it completes',async()=>{
 let saved=false;const gate=defer();
 const h=harness({
  native:async body=>{await gate.promise;saved=true;return {response:{ok:true,json:async()=>({ok:true,data:{provider:'andromeda',generation:body.generation,hotels:[{local_id:777}]}})}};},
  database:(i,p)=>snapshot(p,saved?['tourvisor','andromeda']:['tourvisor'])
 });
 await h.start();assert.equal(h.nativeCalls.length,1);assert.equal(h.nativeCalls[0].generation,1);
 assert.equal(JSON.stringify(h.nativeCalls[0].params),JSON.stringify(h.data.params(trip)));await h.poll();assert.deepEqual(h.providers(),['tourvisor']);
 gate.resolve();await flush();assert.equal(h.nativeCalls.length,1);assert.deepEqual(h.providers(),['andromeda','tourvisor']);
 assert.equal(h.dbBodies.length,3);assert.ok(h.events.some(e=>e.type==='provider'&&e.provider==='andromeda'&&e.status==='complete'));
});
test('Andromeda failure is isolated from TV and LOCAL inventory',async()=>{
 const h=harness({native:async()=>({response:{ok:false,status:503,json:async()=>({ok:false,error:'supplier_unavailable'})}})});
 await h.start();await flush();await h.poll();assert.ok(h.latest().length);
 assert.ok(h.events.some(e=>e.type==='provider'&&e.provider==='andromeda'&&e.status==='error'));
 assert.ok(h.events.some(e=>e.type==='complete'));assert.equal(h.nativeCalls.length,1);
});
test('stopped generation ignores a late Andromeda completion and does not reread LOCAL',async()=>{
 const gate=defer();const h=harness({native:async body=>{await gate.promise;return {response:{ok:true,json:async()=>({ok:true,data:{provider:'andromeda',generation:body.generation,hotels:[]}})}};}});
 await h.start();assert.equal(h.nativeCalls.length,1);const before=h.dbBodies.length;h.data.stop();gate.resolve();await flush();
 assert.equal(h.dbBodies.length,before);assert.equal(h.events.some(e=>e.type==='provider'&&e.status==='complete'),false);
});
test('127 hotels are requested and retained, not the former 100',async()=>{
 const h=harness({api:action=>action==='search_results'?live(127):undefined});await h.start();await h.poll();
 assert.equal(h.calls.find(c=>c.action==='search_results').params.limit,5000);assert.equal(h.latest().length,127);
});
test('failed DB refresh retains valid native rows and does not create authority',async()=>{
 const h=harness({database:(i,p)=>{if(i===2)throw Error('fixture DB unavailable');return snapshot(p,['anex','andromeda']);}});
 await h.start();await h.poll();assert.deepEqual(h.providers(),['andromeda','anex','tourvisor']);
 assert.ok(h.events.some(e=>e.type==='database-error'));
 for(const o of h.latest()[0].offers.filter(o=>o.cached))await assert.rejects(h.data.quote(o),/обновите/);
});
test('valid empty DB refresh clears DB rows, unlike a read failure',async()=>{
 const h=harness({database:(i,p)=>snapshot(p,i===1?['anex','andromeda']:[])});await h.start();await h.poll();
 assert.deepEqual(h.providers(),['tourvisor']);
});
test('late initial read finishes before the completion snapshot',async()=>{
 const pending=defer();const h=harness({database:(i,p)=>i===1?pending.promise:snapshot(p,['andromeda'])});
 await h.start();await h.poll();assert.equal(h.dbBodies.length,1);
 pending.resolve(snapshot(h.dbBodies[0],['anex']));await flush();assert.equal(h.dbBodies.length,2);assert.deepEqual(h.providers(),['andromeda','tourvisor']);
});
test('stop rejects a late initial snapshot and never schedules its follow-up',async()=>{
 const pending=defer();const h=harness({database:()=>pending.promise});await h.start();await h.poll();h.data.stop();const count=h.events.length;
 pending.resolve(snapshot(h.dbBodies[0],['anex']));await flush();assert.equal(h.events.length,count);assert.equal(h.dbBodies.length,1);assert.equal(await h.data.continueSearch(),false);
});
test('stop rejects a late completion snapshot',async()=>{
 const pending=defer();const h=harness({database:(i,p)=>i===2?pending.promise:snapshot(p)});await h.start();await h.poll();h.data.stop();const count=h.events.length;
 pending.resolve(snapshot(h.dbBodies[1],['andromeda']));await flush();assert.equal(h.events.length,count);
});
test('continuation keeps search identity and synchronously rejects double click',async()=>{
 const pending=defer();const h=harness({api:action=>action==='search_continue'?pending.promise:undefined});await h.start();await h.poll();
 const first=h.data.continueSearch();assert.equal(await h.data.continueSearch(),false);pending.resolve({requestCount:1});await first;await flush();
 assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);assert.equal(h.calls.filter(c=>c.action==='search_start').length,1);assert.equal(h.searchId,9001);
 assert.equal(h.dbBodies.length,3);assert.ok(h.events.some(e=>e.type==='loading'&&e.continued));
});
test('uncertain continuation response retries reads, not the supplier continuation',async()=>{
 const h=harness({api:action=>{if(action==='search_continue')throw Error('fixture response lost');}});await h.start();await h.poll();
 const before=JSON.stringify(h.latest());await h.data.continueSearch();assert.equal(JSON.stringify(h.latest()),before);
 assert.ok(h.events.at(-1).retryRead);await h.data.continueSearch();await flush();assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);
});
test('failed final results read is retried without replaying continuation',async()=>{
 let fail=false;const h=harness({api:action=>{if(action==='search_results'&&fail){fail=false;throw Error('fixture results unavailable');}}});
 await h.start();await h.poll();fail=true;await h.data.continueSearch();assert.ok(h.events.at(-1).retryRead);
 await h.data.continueSearch();await flush();assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);assert.ok(h.latest().length);
});
test('expired continuation retains inventory but blocks another request',async()=>{
 const h=harness({api:action=>{if(action==='search_continue')throw Object.assign(Error('expired'),{status:410});}});await h.start();await h.poll();
 await h.data.continueSearch();assert.equal(h.events.at(-1).canContinue,false);assert.equal(await h.data.continueSearch(),false);assert.ok(h.latest().length);
});
test('new generation rejects a previous pending continuation',async()=>{
 const pending=defer();const h=harness({api:action=>action==='search_continue'?pending.promise:undefined});await h.start();await h.poll();
 const old=h.data.continueSearch();await h.start();const count=h.events.length;pending.resolve({requestCount:1});await old;await flush();assert.equal(h.events.length,count);
 assert.equal(h.calls.filter(c=>c.action==='search_start').length,2);
});
test('stopping synchronously on loading starts no requests',async()=>{
 const h=harness({onEvent:(e,data)=>{if(e.type==='loading')data.stop();}});await h.start();
 assert.equal(h.calls.length,0);assert.equal(h.dbBodies.length,0);assert.equal(h.events.length,1);
});
test('stopping on progress starts no result read or completion',async()=>{
 const h=harness({onEvent:(e,data)=>{if(e.type==='progress')data.stop();}});await h.start();await h.poll();
 assert.equal(h.calls.filter(c=>c.action==='search_results').length,0);assert.equal(h.dbBodies.length,1);
 assert.equal(h.events.some(e=>e.type==='complete'),false);
});
test('stopping from a canonical render suppresses later callbacks',async()=>{
 const h=harness({onEvent:(e,data)=>{if(e.type==='results')data.stop();}});await h.start();
 const count=h.events.length;await flush();assert.equal(h.events.length,count);assert.equal(h.events.at(-1).type,'results');
 assert.equal(h.events.some(e=>e.type==='database'),false);
});
// Calendar reuse is tested on the same real adapter/parser, with a controlled
// clock and explicit stored-listing expiries. These are not live price records.
const calendarFrom='2026-10-01',calendarTo='2026-10-31';
const calendarSnapshot=(params,expiresAt)=>{
 const data=snapshot(params,['tourvisor','anex','andromeda']);
 for(const group of data.hotels)for(const row of group.offers)row.expiresAt=expiresAt;
 return data;
};
const calendarRead=(h,signal,filters={},s=trip,from=calendarFrom,to=calendarTo)=>h.data.calendar(s,from,to,signal,filters);
test('calendar revisit reuses both successful windows without changing money or authority',async()=>{
 const now=Date.parse('2026-09-22T16:00:00Z');
 const h=harness({clock:()=>now,database:(i,p)=>calendarSnapshot(p,new Date(now+60000).toISOString())});
 const first=await calendarRead(h);assert.equal(h.dbBodies.length,2);const original=JSON.stringify(first);
 first[0].name='consumer mutation';first[0].offers[0].total=1;first[0].offers[0].raw.selectionEnabled=true;
 const second=await calendarRead(h);assert.equal(h.dbBodies.length,2,'Reopening the same month must not repeat successful DB reads');
 assert.equal(JSON.stringify(second),original);assert.equal(h.calls.length,0);assert.equal(h.nativeCalls.length,0);
 assert.ok(second.flatMap(hotel=>hotel.offers).every(o=>o.cached&&o.raw.selectionEnabled===false&&o.total===1500000));
});
test('failed second calendar window stays an error and retry reuses the first window only',async()=>{
 const now=Date.parse('2026-09-22T16:00:00Z');let fail=true;
 const h=harness({clock:()=>now,database:(i,p)=>{if(p.dateFrom==='2026-10-23'&&fail){fail=false;throw Error('fixture second window unavailable');}return calendarSnapshot(p,new Date(now+60000).toISOString());}});
 const firstWindows=[];
 await assert.rejects(h.data.calendar(trip,calendarFrom,calendarTo,undefined,{},(rows,window)=>firstWindows.push({rows,window})),/second window unavailable/);assert.equal(h.dbBodies.length,2);
 assert.equal(firstWindows.length,1,'The successful first window is delivered before a later window fails');
 assert.equal(firstWindows[0].rows.length,1);assert.equal(JSON.stringify(firstWindows[0].window),JSON.stringify({from:'2026-10-01',to:'2026-10-22',cached:false}));
 firstWindows[0].rows[0].name='consumer mutation';
 const retryWindows=[];
 const rows=await h.data.calendar(trip,calendarFrom,calendarTo,undefined,{},(windowRows,window)=>retryWindows.push({rows:windowRows,window}));assert.equal(h.dbBodies.length,3);assert.equal(rows.length,2);
 assert.equal(JSON.stringify(retryWindows.map(item=>item.window)),JSON.stringify([{from:'2026-10-01',to:'2026-10-22',cached:true},{from:'2026-10-23',to:'2026-10-31',cached:false}]));
 assert.notEqual(rows[0].name,'consumer mutation','Progressive consumer mutation cannot alter retained or returned rows');
 assert.deepEqual(h.dbBodies.map(p=>p.dateFrom),['2026-10-01','2026-10-23','2026-10-23']);
});
test('calendar reuse expires at the earliest listing expiry and at the thirty-second freshness bound',async()=>{
 for(const duration of [5000,86400000]){
  let now=Date.parse('2026-09-22T16:00:00Z');
  const h=harness({clock:()=>now,database:(i,p)=>{const data=calendarSnapshot(p,new Date(now+86400000).toISOString());data.hotels[0].offers[1].expiresAt=new Date(now+duration).toISOString();return data;}});
  await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);
  now+=Math.min(duration,30000)-1;await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);assert.equal(h.dbBodies.length,1);
  now++;await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);assert.equal(h.dbBodies.length,2,'The exact expiry boundary must reread LOCAL');
 }
});
test('unknown expired malformed and empty calendar responses are never reused',async()=>{
 const now=Date.parse('2026-09-22T16:00:00Z');
 for(const expiry of [undefined,'not-a-time','2026-10-01T00:00:00',new Date(now).toISOString(),'empty']){
  const h=harness({clock:()=>now,database:(i,p)=>expiry==='empty'?snapshot(p,[]):calendarSnapshot(p,expiry)});
  await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);
  assert.equal(h.dbBodies.length,2,String(expiry));
 }
});
test('calendar reuse is isolated by exact request criteria and retains fractional premium budgets',async()=>{
 const now=Date.parse('2026-09-22T16:00:00Z');
 const h=harness({clock:()=>now,database:(i,p)=>calendarSnapshot(p,new Date(now+60000).toISOString())});
 h.data.catalog.meals.push({id:7,name:'AI'},{id:8,name:'UAI'});
 const variants=[{}, {max:600000}, {max:1500000.5}, {min:1000000,max:2000000}, {stars:[4]}, {stars:[5]}, {meals:['AI']}, {meals:['UAI']}];
 for(const filters of variants){await calendarRead(h,undefined,filters,trip,calendarFrom,calendarFrom);await calendarRead(h,undefined,filters,trip,calendarFrom,calendarFrom);}
 assert.equal(h.dbBodies.length,variants.length);assert.equal(h.dbBodies[2].priceTo,'1500000.5');
 for(const s of [{...trip,adults:3},{...trip,ages:[5]},{...trip,minNights:10,maxNights:10}])await calendarRead(h,undefined,{},s,calendarFrom,calendarFrom);
 assert.equal(h.dbBodies.length,variants.length+3);assert.equal(h.calls.length,0);
});
test('new search stop and successful completion readback invalidate calendar reuse',async()=>{
 const now=Date.parse('2026-09-22T16:00:00Z');
 const h=harness({clock:()=>now,database:(i,p)=>calendarSnapshot(p,new Date(now+60000).toISOString())});
 const read=()=>calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);
 await read();await read();assert.equal(h.dbBodies.length,1);
 h.data.stop();await read();assert.equal(h.dbBodies.length,2);
 await h.start();const started=h.dbBodies.length;await read();assert.equal(h.dbBodies.length,started+1);
 await h.poll();const completed=h.dbBodies.length;await read();assert.equal(h.dbBodies.length,completed+1);
});
test('native completion invalidates a calendar window without a duplicate provider search',async()=>{
 const now=Date.parse('2026-09-22T16:00:00Z'),gate=defer();
 const h=harness({clock:()=>now,native:()=>gate.promise,database:(i,p)=>calendarSnapshot(p,new Date(now+60000).toISOString())});
 await h.start();await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);const before=h.dbBodies.length;
 gate.resolve();await flush();assert.equal(h.dbBodies.length,before+1);
 await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);assert.equal(h.dbBodies.length,before+2);assert.equal(h.nativeCalls.length,1);
});
test('aborted or invalidated calendar requests cannot populate reusable results',async()=>{
 const now=Date.parse('2026-09-22T16:00:00Z');
 for(const abort of [true,false]){
 const gate=defer(),controller=new AbortController();
  const h=harness({clock:()=>now,database:(i,p)=>i===1?gate.promise:calendarSnapshot(p,new Date(now+60000).toISOString())});
  const delivered=[];
  const pending=h.data.calendar(trip,calendarFrom,calendarFrom,controller.signal,{},rows=>delivered.push(rows));await flush();
  if(abort)controller.abort();else h.data.stop();
  gate.resolve(calendarSnapshot(h.dbBodies[0],new Date(now+60000).toISOString()));
  if(abort)await assert.rejects(pending,{name:'AbortError'});else await pending;
  assert.equal(delivered.length,0,'Aborted or invalidated requests cannot progressively render stale rows');
  await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);assert.equal(h.dbBodies.length,2);
  const cancelled=new AbortController();cancelled.abort();await assert.rejects(calendarRead(h,cancelled.signal,{},trip,calendarFrom,calendarFrom),{name:'AbortError'});
  assert.equal(h.dbBodies.length,2);
 }
});
test('calendar reuse has bounded entry and payload memory rather than an inventory cutoff',async()=>{
 const now=Date.parse('2026-09-22T16:00:00Z');
 const h=harness({clock:()=>now,database:(i,p)=>calendarSnapshot(p,new Date(now+60000).toISOString())});
 for(let day=1;day<=9;day++){const date='2026-10-'+String(day).padStart(2,'0');await calendarRead(h,undefined,{},trip,date,date);}
 await calendarRead(h,undefined,{},trip,'2026-10-09','2026-10-09');assert.equal(h.dbBodies.length,9);
 await calendarRead(h,undefined,{},trip,calendarFrom,calendarFrom);assert.equal(h.dbBodies.length,10,'The least recently used entry was evicted');
 const large=harness({clock:()=>now,database:(i,p)=>{const data=calendarSnapshot(p,new Date(now+60000).toISOString());data.hotels[0].hotel.description='x'.repeat(2200000);return data;}});
 const rows=await calendarRead(large,undefined,{},trip,calendarFrom,calendarFrom);assert.equal(rows.length,1,'Oversize valid results still return');
 await calendarRead(large,undefined,{},trip,calendarFrom,calendarFrom);assert.equal(large.dbBodies.length,2,'Oversize results are not retained in memory');
});
(async()=>{for(const [name,fn]of tests){await fn();console.log('PASS',name);}console.log('SEARCH3_PROTOTYPE_INVENTORY_LIFECYCLE_OK',tests.length);})().catch(error=>{console.error(error);process.exitCode=1;});
