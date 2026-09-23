'use strict';
// Fictional inventory only. Execute the actual adapter, canonical owner and DB
// parser; every HTTP/rt.api response is controlled. No supplier or database I/O.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path'),crypto=require('node:crypto');
const rootDir=path.resolve(__dirname,'..');
const trip={origin:'Москва',country:'4',from:'2026-09-29',to:'2026-10-05',minNights:7,maxNights:7,adults:2,ages:[]};
const hash=x=>crypto.createHash('sha256').update(x).digest('hex');
const profile=id=>({id:Number(id)+400,catalog:'anytour',revision:1,name:'FICTIONAL HOTEL '+id,category:5,country:{id:4,name:'Турция'},images:[]});
function snapshot(params,providers=['tourvisor']){
 return {source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:hash('fixture'),scope:{...params,scopeVersion:1},hotelCount:providers.length?1:0,eligibleHotelCount:providers.length?1:0,offerCount:providers.length,storedOfferCount:providers.length,withheldOfferCount:0,categoryFilteredOfferCount:0,omittedHotelCount:0,omittedOfferCount:0,providerOfferCounts:Object.fromEntries(providers.map(provider=>[provider,1])),selectionAuthority:false,hotels:providers.length?[{anytourHotelId:501,hotel:profile(101),offers:providers.map(provider=>({provider,legacyHotelId:'101',currency:'RUB',price:1500000,listing:{schema_version:1,provider,currency:'RUB',selection_state:'refresh_required',booking_enabled:false,listingPrice:1500000,listingPriceState:'search_price_confirmation_required',listingPriceReady:false,priceConfirmationRequired:true,quoteState:'unknown',finalPriceVerified:false,quoteEvidenceDigest:null,identity:{offer_ref_digest:hash(provider),search_ref_digest:hash('search-'+provider),provider_hotel_ref_digest:hash('hotel-'+provider)},tour:{checkin:params.dateFrom,nights:7,meal:{raw:'AI'},room:{raw:'STANDARD'},placement:{raw:'DBL'},party:{adults:2,children:0}},operator:{raw:'FICTIONAL '+provider}}}))}]:[]};
}
const live=(n=1)=>Array.from({length:n},(_,i)=>({id:101+i,provider:'tourvisor',tours:[{id:'fictional-live-'+i,provider:'tourvisor',price:1500000+i,date:trip.from,nights:7,meal:{name:'AI'},roomType:'STANDARD',operator:{name:'FICTIONAL TV'}}]}));
const directAnex=body=>{
 const searchRef='a'.repeat(32),offerRef='anex_online:'+'b'.repeat(64);
 return {ok:true,data:{generation:body.generation,provider:'anex',date_range:{from:body.params.dateFrom,to:body.params.dateTo},
  search_ref:searchRef,external_search_pending:false,pages_read:1,first_page_only:true,hotels:[{local_id:101,name:'FICTIONAL HOTEL 101',
   category:5,rating:4.7,country:'Турция',region:'Сиде',catalog:{hotel_id:101,source:'tourvisor',image_url:null,description:'',address:'',subregion:'',sea_distance:null},
   tours:[{price:{amount:'1490000',currency:'RUB'},checkin:body.params.dateFrom,nights:7,adults:2,children:0,meal:'AI',room:'STANDARD',
    kind:'group_minimum',flight_type:'charter',final_price_verified:false,search_ref:searchRef,offer_ref:offerRef,selection_enabled:false}]}]}};
};
const directAndromeda=(body,{empty=false,offerRef='offer_'+ 'd'.repeat(64),localId=101}={})=>{
 const searchRef='c'.repeat(64),hotels=empty?[]:[{local_id:localId,mapping_status:'resolved',tours:[{
  provider:'andromeda',price:{amount:'1480000',currency:'RUB'},checkin:body.params.dateFrom,nights:7,adults:2,children:0,
  meal:'AI',room:'STANDARD',placement:'DBL',operator:{name:'FUN&SUN'},offer_ref:offerRef,
  offer_context:{provider:'andromeda',search_ref:searchRef,generation:body.generation,page:1,offer_ref:offerRef},
  listing_price_ref:'listing_'+'e'.repeat(64),selection_enabled:false
 }]}];
 return {ok:true,data:{provider:'andromeda',generation:body.generation,hotels,date_range:{from:body.params.dateFrom,to:body.params.dateTo},
  grouped:true,first_page_only:false,page:1,pages_count:1,external_search_pending:false,search_ref:searchRef,status:'complete',
  received_offers:hotels.length,mapped_offers:hotels.length,selection_enabled:false}};
};
const defer=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
const flush=async()=>{for(let i=0;i<8;i++)await new Promise(setImmediate);};
const waitFor=async(predicate,message)=>{for(let i=0;i<80;i++){if(predicate())return;await new Promise(setImmediate);}assert.fail(message);};
function harness({database,api,onEvent,native,anex,observations,clock=()=>Date.now()}={}){
 const events=[],calls=[],dbBodies=[],nativeCalls=[],anexCalls=[],observationCalls=[],mealCatalogCalls=[],timers=new Map();let timerId=0,readIndex=0,currentId=0;
 const fetch=async(url,options={})=>{
  const target=new URL(url,'https://anytoour.ru/');
  if(target.pathname==='/_preview/search3-local-candidate/data/price-calendar-read-v1.php'){
   const query=Object.fromEntries(target.searchParams);observationCalls.push(query);
   assert.ok(observations,'unexpected observation request');
   return {ok:true,json:async()=>observations(query,options.signal)};
  }
  if(target.pathname==='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'){
   assert.ok(native,'unexpected Andromeda request');
   const body=JSON.parse(options.body);nativeCalls.push(structuredClone(body));
   const result=await native(body,options.signal,nativeCalls);
   if(result&&result.response)return result.response;
   return {ok:true,json:async()=>directAndromeda(body,{empty:true})};
  }
  if(target.pathname==='/_preview/search3-anex-candidate/api-anex-search3-preview.php'){
   assert.ok(anex,'unexpected direct ANEX request');
   const body=JSON.parse(options.body);anexCalls.push(structuredClone(body));
   const result=await anex(body,options.signal,anexCalls);
   if(result&&result.response)return result.response;
   return {ok:true,json:async()=>directAnex(body)};
  }
  if(String(url).includes('search3-local-results-read')){
   const body=JSON.parse(options.body);
   if(body.action==='meal_catalog'){
    mealCatalogCalls.push({body:structuredClone(body),headers:structuredClone(options.headers||{})});
    return {ok:true,json:async()=>({ok:true,data:{
     source:'anytour-search-meal-v1',provider:'tourvisor',scopeKey:'global',available:true,revision:hash('meal-catalog-fixture'),
     plans:[
      {id:2,code:'breakfast',nameRu:'Завтраки',nativeIds:['3']},
      {id:3,code:'half-board',nameRu:'Полупансион',nativeIds:['4']},
      {id:7,code:'all-inclusive',nameRu:'Всё включено',nativeIds:['7']},
      {id:8,code:'ultra-all-inclusive',nameRu:'Ультра всё включено',nativeIds:['9']}
     ]
    }})};
   }
   const params=body.params;dbBodies.push(structuredClone(params));
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
 const win={V2Runtime:runtime,location:new URL('https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'),fetch,crypto:crypto.webcrypto,TextEncoder,setTimeout:(fn,delay)=>{const id=++timerId;timers.set(id,{fn,delay});return id;},clearTimeout:id=>timers.delete(id)};
 if(native||anex){win.V2_CONFIG={};if(native)win.V2_CONFIG.andromedaApi='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php';if(anex)win.V2_CONFIG.anexApi='/_preview/search3-anex-candidate/api-anex-search3-preview.php';}
 const bus=new EventTarget();win.addEventListener=bus.addEventListener.bind(bus);win.removeEventListener=bus.removeEventListener.bind(bus);win.dispatchEvent=bus.dispatchEvent.bind(bus);
 const sandbox={window:win,fetch,URL,URLSearchParams,AbortController,DOMException,structuredClone,console,crypto:crypto.webcrypto,TextEncoder,Date:class extends Date{static now(){return clock();}},setTimeout:win.setTimeout,clearTimeout:win.clearTimeout};
 vm.createContext(sandbox);
 for(const name of ['search3-canonical-profiles-v1.js','search3-local-db-provider-v1.js','prototype-search/data.js'])vm.runInContext(fs.readFileSync(path.join(rootDir,'v2',name),'utf8'),sandbox,{filename:name});
 const data=win.AnyTourPrototypeData;data.catalog.departures.push({id:1,name:'Москва'});data.catalog.countries.push({id:4,name:'Турция'});
 const start=async(filters={min:0,max:null})=>{await data.search(structuredClone(trip),event=>{events.push(event);onEvent?.(event,data);},[],filters);await flush();};
 const poll=async()=>{const entry=[...timers].find(([,value])=>value.delay<=2500);assert.ok(entry,'pending poll required');timers.delete(entry[0]);await entry[1].fn();await flush();};
 const latest=()=>events.filter(e=>e.type==='results').at(-1)?.hotels||[];
 const providers=()=>[...new Set(latest().flatMap(h=>h.offers.map(o=>o.provider)))].sort();
 return {data,start,poll,events,calls,dbBodies,nativeCalls,anexCalls,observationCalls,mealCatalogCalls,latest,providers,timers,get searchId(){return currentId;}};
}
const tests=[];const test=(name,fn)=>tests.push([name,fn]);
const observed=(q,price=97500)=>{const childAges=String(q.childs||'').trim()?String(q.childs).split(',').map(Number).sort((a,b)=>a-b):[];return {ok:true,source:'latest-known-exact-segments-from-anytour-first-party-observations',cachedPriceIsFinal:false,currency:'RUB',adults:Number(q.adults),childrenCount:childAges.length,childAges,childAgesSignature:childAges.join(','),departureId:Number(q.departureId),countryId:Number(q.countryId),regionId:q.regionId?Number(q.regionId):null,dateFrom:q.dateFrom,dateTo:q.dateTo,nightsFrom:Number(q.nightsFrom),nightsTo:Number(q.nightsTo),series:[{date:q.dateFrom,observed:true,minPrice:price}]};};
function canonicalMeals(h){
 h.data.catalog.meals.splice(0,h.data.catalog.meals.length,
  {id:3,name:'BB'},{id:4,name:'HB'},{id:7,name:'AI'},{id:9,name:'UAI'});
 h.data.catalog.mealPlans.splice(0,h.data.catalog.mealPlans.length,
  {id:2,code:'breakfast',nameRu:'Завтраки',nativeIds:['3']},
  {id:3,code:'half-board',nameRu:'Полупансион',nativeIds:['4']},
  {id:7,code:'all-inclusive',nameRu:'Всё включено',nativeIds:['7']},
  {id:8,code:'ultra-all-inclusive',nameRu:'Ультра всё включено',nativeIds:['9']});
 h.data.catalog.mealPlanAvailable=true;
}
test('init loads canonical meal authority through the exposed LOCAL reader action',async()=>{
 const h=harness({api:(action)=>{
  if(action==='meals')return [{id:3,name:'BB'},{id:4,name:'HB'},{id:7,name:'AI'},{id:9,name:'UAI'}];
  if(action==='countries')return [{id:4,name:'Турция'}];
 }});
 h.data.catalog.mealPlans.splice(0);h.data.catalog.mealPlanAvailable=false;h.data.catalog.mealPlanRevision=null;
 await h.data.init('Москва');
 assert.equal(h.mealCatalogCalls.length,1);
 assert.deepEqual(JSON.parse(JSON.stringify(h.mealCatalogCalls[0].body)),{action:'meal_catalog',provider:'tourvisor',scopeKey:'global'});
 assert.equal(h.mealCatalogCalls[0].headers['X-Requested-With'],'AnyTourSearch3');
 assert.equal(h.mealCatalogCalls[0].headers['Content-Type'],'application/json');
 assert.equal(h.data.catalog.mealPlanAvailable,true);
 assert.deepEqual(Array.from(h.data.catalog.mealPlans,p=>[p.id,p.code,p.nameRu,[...p.nativeIds]]),[
  [2,'breakfast','Завтраки',['3']],[3,'half-board','Полупансион',['4']],
  [7,'all-inclusive','Всё включено',['7']],[8,'ultra-all-inclusive','Ультра всё включено',['9']]
 ]);
});
test('canonical mealPlanId owns top-level taxonomy while raw meal stays detail',async()=>{
 const h=harness();canonicalMeals(h);
 const rows=h.data.project([{id:101,anytourHotelId:501,name:'FICTIONAL HOTEL 101',category:5,rating:4.7,images:[],tours:[
  {id:'mapped',provider:'tourvisor',price:150000,date:trip.from,nights:7,meal:{id:7,name:'Premium All Inclusive'},roomType:'STANDARD',operator:{name:'ANEX'}},
  {id:'unknown',provider:'tourvisor',price:160000,date:trip.from,nights:7,meal:{name:'On Request'},roomType:'STANDARD',operator:{name:'ANEX'}},
  {id:'local-plan',provider:'andromeda',price:170000,date:trip.from,nights:7,meal:{name:'Local&Healthy Ultra All Inclusive'},
   searchMealPlan:{id:8,code:'ultra-all-inclusive',nameRu:'Ультра всё включено'},roomType:'STANDARD',operator:{name:'FUN&SUN'}}
 ]}],trip);
 assert.equal(rows.length,1);
 const [mapped,unknown,localPlan]=rows[0].offers;
 assert.equal(mapped.mealPlanId,7);assert.equal(mapped.mealFacet,'Всё включено');assert.equal(mapped.meal,'Всё включено');
 assert.equal(mapped.mealRaw,'Premium All Inclusive','raw wording is retained separately from facet identity');
 assert.equal(unknown.mealPlanId,null);assert.equal(unknown.mealFacet,'');assert.equal(unknown.meal,'On Request','unknown raw fact remains detail only');
 assert.equal(localPlan.mealPlanId,8);assert.equal(localPlan.mealFacet,'Ультра всё включено');
 assert.equal(localPlan.mealRaw,'Local&Healthy Ultra All Inclusive');
 assert.equal(h.data.mealPlan({provider:'tourvisor',meal:{id:7,name:'anything'}},'tourvisor').id,7,'reviewed native ID is authoritative');
 assert.equal(h.data.mealPlan({provider:'tourvisor',meal:{name:'On Request'}},'tourvisor'),null,'raw label never invents a plan');
});
test('supplier meal scope uses reviewed canonical native IDs, not aliases',async()=>{
 const h=harness();canonicalMeals(h);
 const scope=filters=>h.data.supplierScope(filters);
 const covered=(previous,next)=>h.data.supplierScopeCovered(previous,next);
 const exact=scope({stars:[4],meals:['Полупансион']});
 assert.equal(exact.hotelCategory,'4');assert.equal(exact.meal,'4');
 assert.equal(h.data.params(trip,[],{meals:['Всё включено']}).meal,'7');
 assert.equal(h.data.params(trip,[],{meals:['Всё включено','Полупансион']}).meal,'','multi-plan OR stays broad upstream');
 h.data.catalog.mealPlans.find(p=>p.id===7).nativeIds=['7','70'];
 assert.equal(scope({meals:['Всё включено']}).meal,'','several reviewed native IDs stay broad upstream');
 assert.equal(covered(scope({stars:[4,5]}),scope({stars:[4]})),true);
 assert.equal(covered(scope({meals:['Полупансион']}),scope({})),false,'removing an exact upstream meal requires a broader search');
 assert.throws(()=>scope({meals:['Room Only']}),/канонического справочника/,'raw labels cannot enter supplier meal scope');
});
test('operator labels collapse known cross-provider aliases without touching source identity',async()=>{
 const h=harness();
 assert.equal(h.data.operator('Biblio Globus'),'Библио-Глобус');
 assert.equal(h.data.operator('Библио-Глобус'),'Библио-Глобус');
 assert.equal(h.data.operator('Intourist'),'Интурист');
 assert.equal(h.data.operator('Интурист'),'Интурист');
 assert.equal(h.data.operator('FUN SUN'),'FUN&SUN');
 assert.equal(h.data.operator('FUN&SUN (RU)'),'FUN&SUN');
 assert.equal(h.data.operator('Coral'),'Coral Travel');
 assert.equal(h.data.operator('Pegas Touristik'),'Pegas Touristik');
 assert.equal(h.data.operator('LOTI'),'LOTI');
});
test('hotel projection keeps parent region and displays the more specific subregion',async()=>{
 const h=harness();
 const rows=h.data.project([{id:101,anytourHotelId:501,name:'FICTIONAL HOTEL 101',category:5,rating:4.7,
  region:{name:'Анталья'},subRegion:{name:'Сиде'},images:[],tours:[{id:'geo-1',provider:'tourvisor',price:150000,date:trip.from,nights:7,meal:{name:'AI'},roomType:'STANDARD',operator:{name:'ANEX'}}]}],trip);
 assert.equal(rows.length,1);assert.equal(rows[0].region,'Анталья');assert.equal(rows[0].subRegion,'Сиде');assert.equal(rows[0].resort,'Сиде');
});
test('first search unions direct ANEX once and waits for it before complete',async()=>{
 const gate=defer();const h=harness({anex:async body=>{await gate.promise;return {response:{ok:true,json:async()=>directAnex(body)}};}});
 await h.start();assert.equal(h.anexCalls.length,1);assert.equal(h.anexCalls[0].action,'search');
 const completing=h.poll();await flush();assert.equal(h.events.some(e=>e.type==='complete'),false);
 gate.resolve();await completing;await flush();
 assert.deepEqual(h.providers(),['anex','tourvisor']);assert.equal(h.latest().length,1,'same canonical hotel is deduplicated');
 const final=h.events.filter(e=>e.type==='complete').at(-1);assert.equal(final.sources.anex.hotels,1);assert.equal(final.sources.anex.offers,1);
 assert.equal(final.sources.tourvisor.hotels,1);assert.equal(final.union.hotels,1);assert.ok(final.union.offers>=2);
 assert.equal(final.union.hotelsByProvider.anex,1);assert.equal(final.union.hotelsByProvider.tourvisor,1);assert.equal(final.union.providerSets['anex+tourvisor'],1);
 assert.ok(h.events.some(e=>e.type==='provider'&&e.provider==='anex'&&e.status==='complete'));
 await h.data.continueSearch();await flush();assert.equal(h.anexCalls.length,1,'Continue never replays direct ANEX');
});
test('LOCAL source accounting preserves backend losses and malformed counts fail closed',async()=>{
 const h=harness({database:(i,p)=>({...snapshot(p,['andromeda','anex']),
  storedOfferCount:8,withheldOfferCount:2,categoryFilteredOfferCount:3,eligibleHotelCount:2,
  hotelCount:1,omittedHotelCount:1,omittedOfferCount:1,offerCount:2,providerOfferCounts:{andromeda:1,anex:1}
 })});
 await h.start();await h.poll();
 const dbEvent=h.events.filter(e=>e.type==='database').at(-1);
 assert.deepEqual(JSON.parse(JSON.stringify(dbEvent)),{type:'database',status:'complete',hotels:1,offers:2,storedOffers:8,receivedOffers:8,mappedOffers:6,
  visibleOffers:2,withheldOffers:2,scopeFilteredOffers:3,eligibleHotels:2,omittedHotels:1,omittedOffers:1,
  providerOfferCounts:{andromeda:1,anex:1}});
 const final=h.events.filter(e=>e.type==='complete').at(-1);
 assert.equal(final.sources.database.receivedOffers,8);assert.equal(final.sources.database.mappedOffers,6);
 assert.equal(final.sources.database.withheldOffers,2);assert.equal(final.sources.database.scopeFilteredOffers,3);
 assert.equal(final.sources.database.eligibleHotels,2);assert.equal(final.sources.database.omittedHotels,1);
 assert.equal(final.sources.database.omittedOffers,1);assert.equal(final.sources.database.visibleOffers,2);

 const bad=harness({database:(i,p)=>({...snapshot(p),withheldOfferCount:'1'})});
 await bad.start();await bad.poll();
 assert.ok(bad.events.some(e=>e.type==='database-error'&&/Invalid LOCAL accounting/.test(e.message)));
 const badFinal=bad.events.filter(e=>e.type==='complete').at(-1);
 assert.deepEqual(badFinal.sources.database,{status:'error'});
});
test('direct ANEX and LOCAL dedupe the same normalized ANEX offer identity',async()=>{
 const offerRef='anex_online:'+'b'.repeat(64);
 const h=harness({anex:async()=>undefined,database:(i,p)=>{
  const row=snapshot(p,['anex']);row.hotels[0].offers[0].listing.identity.offer_ref_digest=hash(offerRef);return row;
 }});
 await h.start();await h.poll();
 const hotel=h.latest()[0],anexOffers=hotel.offers.filter(o=>o.provider==='anex');
 assert.equal(anexOffers.length,1,'same direct+stored ANEX offer must not duplicate inside canonical hotel');
 const final=h.events.filter(e=>e.type==='complete').at(-1);
 assert.equal(final.union.hotelsByProvider.anex,1);assert.equal(final.union.offersByProvider.anex,1);
});
test('direct ANEX failure preserves Tourvisor and LOCAL inventory',async()=>{
 const h=harness({anex:async()=>({response:{ok:false,status:503,json:async()=>({ok:false,error:'supplier_unavailable'})}})});
 await h.start();await h.poll();assert.ok(h.latest().length);assert.ok(h.providers().includes('tourvisor'));
 assert.ok(h.events.some(e=>e.type==='provider'&&e.provider==='anex'&&e.status==='error'));
 assert.ok(h.events.some(e=>e.type==='complete'));
});
test('direct ANEX rejects mismatched mapped hotel identity instead of inventing a link',async()=>{
 const h=harness({anex:async body=>{const payload=directAnex(body);payload.data.hotels[0].catalog.hotel_id=999;return {response:{ok:true,json:async()=>payload}};}});
 await h.start();await h.poll();assert.deepEqual(h.providers(),['tourvisor']);
 assert.ok(h.events.some(e=>e.type==='provider'&&e.provider==='anex'&&e.status==='error'));
});
test('first search sends selected catalogue resort IDs and preserves exact multi-star OR scope',async()=>{
 const h=harness({api:(action,p)=>action==='regions'?[{id:23,name:'Сиде',countryId:Number(p.countryId)},{id:22,name:'Кемер',countryId:Number(p.countryId)}]:undefined});
 assert.throws(()=>h.data.params(trip,[],{resorts:['Сиде']}),/справочника/);
 await h.data.regions('4');await h.data.regions('4');
 assert.equal(h.data.params(trip,[],{stars:[4]}).hotelCategory,'4');
 assert.equal(h.data.params(trip,[],{stars:[3,5]}).hotelCategory,'');
 assert.equal(h.data.params(trip,[],{stars:[4,5]}).hotelCategory,'');
 await h.start({resorts:['Сиде','Кемер'],stars:[4,5]});
 assert.equal(h.calls.filter(c=>c.action==='regions').length,1);
 const p=h.calls.find(c=>c.action==='search_start').params;
 assert.deepEqual(Array.from(p.regionIds),['23','22']);assert.equal(p.hotelCategory,'');
 assert.deepEqual(Array.from(h.dbBodies[0].regionIds),['23','22']);assert.equal(h.dbBodies[0].hotelCategory,'');
 assert.throws(()=>h.data.params(trip,[],{resorts:['Неизвестный']}),/справочника/);
});
test('foreign and ambiguous resort catalogue does not silently drop a selected condition',async()=>{
 const h=harness({api:action=>action==='regions'?[{id:23,name:'Сиде',countryId:99}]:undefined});
 await assert.rejects(h.data.regions('4'),/справочник/);assert.equal(h.data.catalog.regions['4'],undefined);
 h.data.catalog.regions['4']=[{id:'23',name:'Сиде'},{id:'22',name:'Сиде'}];
 assert.throws(()=>h.data.params(trip,[],{resorts:['Сиде']}),/справочника/);
});
test('TOP500 observation prices fill calendars even with empty normalized offer storage',async()=>{
 const h=harness({database:(i,p)=>snapshot(p,[]),observations:q=>observed(q)}),updates=[];
 const result=await h.data.calendarPrices(trip,trip.from,trip.to,new AbortController().signal,{},x=>updates.push(x));
 assert.equal(result.hotels.length,0);assert.equal(result.observations[0].price,97500);assert.equal(h.observationCalls.length,1);
 assert.equal(h.calls.length,0,'Calendar never starts supplier requests');assert.equal(h.latest().length,0,'Observation summaries are not selectable tours');
 assert.ok(updates.some(x=>x.observations.length===1));
});
test('observation prices survive failed LOCAL read and LOCAL prices survive failed observations',async()=>{
 const h=harness({database:()=>{throw Error('LOCAL unavailable');},observations:q=>observed(q)}),updates=[];
 const observationsOnly=await h.data.calendarPrices(trip,trip.from,trip.to,new AbortController().signal,{},x=>updates.push(x));
 assert.equal(observationsOnly.hotels.length,0);assert.equal(observationsOnly.observations.length,1);assert.equal(observationsOnly.partial,true);assert.ok(updates.some(x=>x.observations.length===1));
 const other=harness({observations:()=>{throw Error('observations unavailable');}}),kept=[];
 const localOnly=await other.data.calendarPrices(trip,trip.from,trip.to,new AbortController().signal,{},x=>kept.push(x));
 assert.equal(localOnly.hotels.length,1);assert.equal(localOnly.observations.length,0);assert.equal(localOnly.partial,true);assert.ok(kept.some(x=>x.hotels.length===1));
});
test('observation aggregates never substitute for unsupported hotel filters and preserve exact party scope',async()=>{
 const h=harness({observations:q=>observed(q)}),signal=new AbortController().signal;
 for(const f of [{stars:[5]},{meals:['AI']},{amenities:['3:15']},{min:1},{max:1500000},{resorts:['Сиде','Кемер']},{hotelId:501},{q:'hotel'},{flight:['regular']},{operators:['ANEX']},{rating:true}]){
  assert.equal((await h.data.observedCalendar(trip,trip.from,trip.to,signal,f)).length,0);
 }
 assert.equal(h.observationCalls.length,0);
 const family={...trip,adults:1,ages:[7,3]};
 assert.equal((await h.data.observedCalendar(family,trip.from,trip.to,signal,{})).length,1);
 assert.equal(h.observationCalls[0].adults,'1');assert.equal(h.observationCalls[0].childs,'3,7');
 h.data.catalog.regions['4']=[{id:'23',name:'Сиде'}];
 await h.data.observedCalendar(trip,trip.from,trip.to,signal,{resorts:['Сиде']});assert.equal(h.observationCalls[1].regionId,'23');
 assert.equal(h.observationCalls[1].adults,'2');assert.equal(h.observationCalls[1].childs,'');
});
test('observation response must match exact date night party and region scope',async()=>{
 for(const patch of [{countryId:99},{adults:1},{childrenCount:1},{childAges:[3]},{childAgesSignature:'3'},{nightsTo:9},{regionId:1},{dateFrom:'2026-01-01'},{cachedPriceIsFinal:true},{series:[{date:trip.from,observed:true,minPrice:0}]}]){
  const h=harness({observations:q=>({...observed(q),...patch})});
  await assert.rejects(h.data.observedCalendar(trip,trip.from,trip.to,new AbortController().signal,{}));
 }
});
test('aborted calendar cannot publish a late observation response into a new trip',async()=>{
 const gate=defer(),h=harness({database:(i,p)=>snapshot(p,[]),observations:async q=>{await gate.promise;return observed(q);}}),updates=[],controller=new AbortController();
 const pending=h.data.calendarPrices(trip,trip.from,trip.to,controller.signal,{},x=>updates.push(x));await flush();controller.abort();const n=updates.length;gate.resolve();
 await assert.rejects(pending,error=>error.name==='AbortError');assert.equal(updates.length,n);
});
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
 assert.equal(h.events.at(-1).type,'complete');
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
  native:async body=>{await gate.promise;saved=true;return {response:{ok:true,json:async()=>directAndromeda(body)}};},
  database:(i,p)=>snapshot(p,saved?['tourvisor','andromeda']:['tourvisor'])
 });
 await h.start();assert.equal(h.nativeCalls.length,1);assert.equal(h.nativeCalls[0].generation,1);
 assert.equal(JSON.stringify(h.nativeCalls[0].params),JSON.stringify(h.data.params(trip)));
 const completing=h.poll();await flush();assert.deepEqual(h.providers(),['tourvisor']);assert.equal(h.events.some(e=>e.type==='complete'),false);
 gate.resolve();await completing;await flush();assert.equal(h.nativeCalls.length,1);assert.deepEqual(h.providers(),['andromeda','tourvisor']);
 assert.equal(h.dbBodies.length,3);assert.ok(h.events.some(e=>e.type==='provider'&&e.provider==='andromeda'&&e.status==='complete'));assert.equal(h.events.at(-1).type,'complete');
});
test('native Andromeda offers are visible even when LOCAL reread fails',async()=>{
 const h=harness({native:async body=>({response:{ok:true,json:async()=>directAndromeda(body)}}),database:async()=>{throw new Error('fictional LOCAL outage');}});
 await h.start();await waitFor(()=>h.providers().includes('andromeda'),'successful native Andromeda must become visible without autosave readback');
 assert.ok(h.providers().includes('andromeda'),'successful native Andromeda must not wait for autosave readback');
 await h.poll();assert.deepEqual(h.providers(),['andromeda','tourvisor']);
 assert.ok(h.events.some(e=>e.type==='database-error'));
 const final=h.events.filter(e=>e.type==='complete').at(-1);
 assert.equal(final.sources.andromeda.status,'complete');assert.equal(final.sources.andromeda.hotels,1);assert.equal(final.sources.andromeda.offers,1);
 assert.equal(final.union.hotelsByProvider.andromeda,1);assert.equal(final.union.offersByProvider.andromeda,1);
});
test('native Andromeda and LOCAL autosave dedupe the same offer identity',async()=>{
 const offerRef='offer_'+'d'.repeat(64);
 const h=harness({native:async body=>({response:{ok:true,json:async()=>directAndromeda(body,{offerRef})}}),database:(i,p)=>{
  const row=snapshot(p,['andromeda']);row.hotels[0].offers[0].listing.identity.offer_ref_digest=hash(offerRef);return row;
 }});
 await h.start();await h.poll();
 const offers=h.latest()[0].offers.filter(o=>o.provider==='andromeda');
 assert.equal(offers.length,1,'same native+stored Andromeda offer must collapse by canonical digest');
 const final=h.events.filter(e=>e.type==='complete').at(-1);
 assert.equal(final.union.offersByProvider.andromeda,1);
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
 await h.start();const completing=h.poll();await flush();assert.equal(h.dbBodies.length,1);assert.equal(h.events.some(e=>e.type==='complete'),false);
 pending.resolve(snapshot(h.dbBodies[0],['anex']));await completing;await flush();assert.equal(h.dbBodies.length,2);assert.deepEqual(h.providers(),['andromeda','tourvisor']);assert.equal(h.events.at(-1).type,'complete');
});
test('stop rejects a late initial snapshot and never schedules its follow-up',async()=>{
 const pending=defer();const h=harness({database:()=>pending.promise});await h.start();const completing=h.poll();await flush();h.data.stop();const count=h.events.length;
 pending.resolve(snapshot(h.dbBodies[0],['anex']));await completing;await flush();assert.equal(h.events.length,count);assert.equal(h.dbBodies.length,1);assert.equal(await h.data.continueSearch(),false);
});
test('stop rejects a late completion snapshot',async()=>{
 const pending=defer();const h=harness({database:(i,p)=>i===2?pending.promise:snapshot(p)});await h.start();const completing=h.poll();await flush();assert.equal(h.dbBodies.length,2);h.data.stop();const count=h.events.length;
 pending.resolve(snapshot(h.dbBodies[1],['andromeda']));await completing;await flush();assert.equal(h.events.length,count);
});
test('continuation stops after a completed read adds no Tourvisor inventory',async()=>{
 const h=harness();await h.start();await h.poll();
 assert.equal(h.events.filter(e=>e.type==='complete').at(-1).canContinue,true);
 await h.data.continueSearch();await flush();
 const final=h.events.filter(e=>e.type==='complete').at(-1);
 assert.equal(final.continued,true);assert.equal(final.canContinue,false);
 assert.deepEqual(JSON.parse(JSON.stringify(final.continuationGrowth)),{before:{hotels:1,offers:1},after:{hotels:1,offers:1},grew:false});
 assert.equal(await h.data.continueSearch(),false);
 assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);
});
test('continuation remains available only while Tourvisor inventory grows',async()=>{
 let continued=0;
 const h=harness({api:(action)=>{
  if(action==='search_continue'){continued++;return {requestCount:1};}
  if(action==='search_results')return live(continued?2:1);
 }});
 await h.start();await h.poll();
 await h.data.continueSearch();await flush();
 let final=h.events.filter(e=>e.type==='complete').at(-1);
 assert.equal(final.canContinue,true);assert.equal(final.continuationGrowth.grew,true);
 assert.deepEqual(JSON.parse(JSON.stringify(final.continuationGrowth.before)),{hotels:1,offers:1});
 assert.deepEqual(JSON.parse(JSON.stringify(final.continuationGrowth.after)),{hotels:2,offers:2});
 await h.data.continueSearch();await flush();
 final=h.events.filter(e=>e.type==='complete').at(-1);
 assert.equal(final.canContinue,false);assert.equal(final.continuationGrowth.grew,false);
 assert.equal(await h.data.continueSearch(),false);
 assert.equal(h.calls.filter(c=>c.action==='search_continue').length,2);
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
 const h=harness({clock:()=>now,database:(i,p)=>calendarSnapshot(p,new Date(now+60000).toISOString())});canonicalMeals(h);
 const variants=[{}, {max:600000}, {max:1500000.5}, {min:1000000,max:2000000}, {stars:[4]}, {stars:[5]}, {meals:['Всё включено']}, {meals:['Ультра всё включено']}];
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