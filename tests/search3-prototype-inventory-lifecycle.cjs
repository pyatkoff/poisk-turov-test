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
const directAnex=(body,{offerRef='anex_online:'+'b'.repeat(64),localId=101,searchRef='a'.repeat(32),empty=false}={})=>{
 const hotels=empty?[]:[{local_id:localId,name:'FICTIONAL HOTEL '+localId,
  category:5,rating:4.7,country:'Турция',region:'Сиде',catalog:{hotel_id:localId,source:'tourvisor',image_url:null,description:'',address:'',subregion:'',sea_distance:null},
  tours:[{price:{amount:'1490000',currency:'RUB'},checkin:body.params.dateFrom,nights:7,adults:2,children:0,meal:'AI',room:'STANDARD',
   kind:'group_minimum',flight_type:'charter',final_price_verified:false,search_ref:searchRef,offer_ref:offerRef,selection_enabled:false}]}];
 return {ok:true,data:{generation:body.generation,provider:'anex',date_range:{from:body.params.dateFrom,to:body.params.dateTo},
  search_ref:searchRef,external_search_pending:false,pages_read:1,first_page_only:true,hotels}};
};
const expandedAnex=(body,{groupRef='anex_online:'+'b'.repeat(64),searchRef='c'.repeat(32),localId=101}={})=>({ok:true,data:{
 provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:groupRef,status:'expanded',offer:null,selection_state:'disabled',
 external_search_pending:false,first_page_only:true,hotels:[{local_id:localId,name:'FICTIONAL HOTEL '+localId,category:5,rating:4.7,country:'Турция',region:'Сиде',
 catalog:{hotel_id:localId,source:'tourvisor',image_url:null,description:'',address:'',subregion:'',sea_distance:null},tours:[
  {price:{amount:'1510000',currency:'RUB'},checkin:trip.from,nights:7,adults:2,children:0,meal:'AI',room:'STANDARD SEA VIEW',kind:'concrete',
   flight_type:'charter',final_price_verified:false,search_ref:searchRef,offer_ref:'anex_online:'+'1'.repeat(64),selection_enabled:false},
  {price:{amount:'1520000',currency:'RUB'},checkin:trip.from,nights:7,adults:2,children:0,meal:'AI',room:'DELUXE SEA VIEW',kind:'concrete',
   flight_type:'charter',final_price_verified:false,search_ref:searchRef,offer_ref:'anex_online:'+'2'.repeat(64),selection_enabled:false}
 ]}]
}});
const currentAnexConcrete=(body,{ready=false}={})=>({ok:true,data:{
 provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'current',selection_state:'disabled',
 finalPriceReady:ready,finalPrice:ready?'1530000':null,price:ready?'1530000':null,
 offer:{final_price_verified:false,context:{current_context_verified:true}}
}});
const additionalAnexConcrete=(body,{search='1510000',surcharge='20000',total='1530000'}={})=>({ok:true,data:{
 provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'additional_prices',selection_state:'disabled',
 additional_prices:{source:'anex_b2b_additional_prices_daily',application_state:'applied',converted_currency:'RUB',
  per_person_or_package:'per_person_by_party_type',included_in_search_price:false,arithmetic_applied:true,final_price_verified:false,
  party_surcharge:{amount:surcharge,currency:'RUB',source:'anex_b2b_additional_prices_daily'},
  search_price:{amount:search,currency:'RUB',source:'direct_anex_search'},
  search_plus_additional:{amount:total,currency:'RUB',formula:'search_price_plus_program_date_party_additional'}}
}});
const directAndromeda=(body,{empty=false,offerRef='offer_'+ 'd'.repeat(64),localId=101,pagesCount=1,status='complete',searchRef='c'.repeat(64)}={})=>{
 const page=Number(body.page),hotels=empty?[]:[{local_id:localId,mapping_status:'resolved',tours:[{
  provider:'andromeda',price:{amount:'1480000',currency:'RUB'},checkin:body.params.dateFrom,nights:7,adults:2,children:0,
  meal:'AI',room:'STANDARD',placement:'DBL',operator:{name:'FUN&SUN'},offer_ref:offerRef,
  offer_context:{provider:'andromeda',search_ref:searchRef,generation:body.generation,page,offer_ref:offerRef},
  listing_price_ref:'listing_'+'e'.repeat(64),selection_enabled:false
 }]}];
 return {ok:true,data:{provider:'andromeda',generation:body.generation,hotels,date_range:{from:body.params.dateFrom,to:body.params.dateTo},
  grouped:true,first_page_only:false,page,pages_count:pagesCount,external_search_pending:false,search_ref:searchRef,status,
  received_offers:hotels.length,mapped_offers:hotels.length,selection_enabled:false}};
};
const andromedaVerified=(localId=101,amount='1499000')=>({schema_version:1,provider:'andromeda',local_id:localId,selection_enabled:true,booking_enabled:false,
 state:'quote_verified',quote_state:'verified',final_price:{amount,currency:'RUB'},final_price_verified:true,flight_selection_required:false,
 flights:[{direction:'0',name:'OUT 101',datebeg:trip.from,class:'ECONOM',departure:{state:'Россия',town:'Москва',port:'SVO'},arrival:{state:'Турция',town:'Анталья',port:'AYT'}},
          {direction:'1',name:'BACK 102',datebeg:'2026-10-06',class:'ECONOM',departure:{state:'Турция',town:'Анталья',port:'AYT'},arrival:{state:'Россия',town:'Москва',port:'SVO'}}]});
const andromedaChoice=(localId=101)=>({schema_version:1,provider:'andromeda',local_id:localId,selection_enabled:true,booking_enabled:false,
 state:'flight_selection_required',quote_state:'unverified',final_price:null,final_price_verified:false,flight_selection_required:true,
 flights:[
  {direction:'0',flight_ref:'flight_'+'1'.repeat(32),name:'OUT A',datebeg:trip.from,class:'ECONOM',departure:{town:'Москва',port:'SVO'},arrival:{town:'Анталья',port:'AYT'}},
  {direction:'0',flight_ref:'flight_'+'2'.repeat(32),name:'OUT B',datebeg:trip.from,class:'ECONOM',departure:{town:'Москва',port:'VKO'},arrival:{town:'Анталья',port:'AYT'}},
  {direction:'1',flight_ref:'flight_'+'3'.repeat(32),name:'BACK A',datebeg:'2026-10-06',class:'ECONOM',departure:{town:'Анталья',port:'AYT'},arrival:{town:'Москва',port:'SVO'}}
 ]});
function rehydratableSnapshot(params,provider){
 const data=snapshot(params,[provider]),row=data.hotels[0].offers[0],name=provider==='anex'?'ANEX':'FUN&SUN';
 row.listing.tour.party.child_ages=[];
 row.listing.operator={raw:name,canonical_name:name};
 return data;
}
const defer=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
const flush=async()=>{for(let i=0;i<8;i++)await new Promise(setImmediate);};
const waitFor=async(predicate,message)=>{for(let i=0;i<80;i++){if(predicate())return;await new Promise(setImmediate);}assert.fail(message);};
function harness({database,api,onEvent,native,anex,andromedaQuote,observations,clock=()=>Date.now()}={}){
 const events=[],calls=[],dbBodies=[],nativeCalls=[],anexCalls=[],andromedaQuoteCalls=[],observationCalls=[],mealCatalogCalls=[],timers=new Map();let timerId=0,readIndex=0,currentId=0;
 const fetch=async(url,options={})=>{
  const target=new URL(url,'https://anytoour.ru/');
  if(target.pathname==='/_preview/search3-anex-candidate/api-andromeda-quote-preview.php'){
   assert.ok(andromedaQuote,'unexpected Andromeda quote request');
   assert.equal(options.credentials,'same-origin');assert.equal(options.headers?.['X-Requested-With'],'AnyTourSearch3');
   const body=JSON.parse(options.body);andromedaQuoteCalls.push(structuredClone(body));
   const result=await andromedaQuote(body,options.signal,andromedaQuoteCalls);
   if(result&&result.response)return result.response;
   return {ok:true,status:200,json:async()=>({ok:true,data:andromedaVerified()})};
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
   if(body.action==='price_calendar'){
    const childAges=[...(body.childs||[])].map(Number).sort((a,b)=>a-b);
    const regionIds=[...new Set((Array.isArray(body.regionIds)?body.regionIds:Number(body.regionId)>0?[body.regionId]:[]).map(Number))].sort((a,b)=>a-b);
    const query={departureId:String(body.departureId),countryId:String(body.countryId),dateFrom:body.dateFrom,dateTo:body.dateTo,
      nightsFrom:String(body.nightsFrom),nightsTo:String(body.nightsTo),adults:String(body.adults),childs:childAges.join(','),regionIds:regionIds.map(String)};
    if(regionIds.length===1)query.regionId=String(regionIds[0]);
    observationCalls.push(query);assert.ok(observations,'unexpected observation request');
    return {ok:true,json:async()=>({ok:true,data:await observations(query,options.signal)})};
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
 if(native||anex||andromedaQuote){win.V2_CONFIG={};if(native)win.V2_CONFIG.andromedaApi='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php';if(anex)win.V2_CONFIG.anexApi='/_preview/search3-anex-candidate/api-anex-search3-preview.php';if(andromedaQuote)win.V2_CONFIG.andromedaQuoteApi='/_preview/search3-anex-candidate/api-andromeda-quote-preview.php';}
 const bus=new EventTarget();win.addEventListener=bus.addEventListener.bind(bus);win.removeEventListener=bus.removeEventListener.bind(bus);win.dispatchEvent=bus.dispatchEvent.bind(bus);
 const sandbox={window:win,fetch,URL,URLSearchParams,AbortController,DOMException,structuredClone,console,crypto:crypto.webcrypto,TextEncoder,Date:class extends Date{static now(){return clock();}},setTimeout:win.setTimeout,clearTimeout:win.clearTimeout};
 vm.createContext(sandbox);
 for(const name of ['search3-canonical-profiles-v1.js','search3-local-db-provider-v1.js','prototype-search/data.js'])vm.runInContext(fs.readFileSync(path.join(rootDir,'v2',name),'utf8'),sandbox,{filename:name});
 const data=win.AnyTourPrototypeData;data.catalog.departures.push({id:1,name:'Москва'});data.catalog.countries.push({id:4,name:'Турция',tourvisorIds:['4']});
 const start=async(filters={min:0,max:null})=>{await data.search(structuredClone(trip),event=>{events.push(event);onEvent?.(event,data);},[],filters);await flush();};
 const resume=async(filters={min:0,max:null})=>{await data.resumeCached(structuredClone(trip),event=>{events.push(event);onEvent?.(event,data);},[],filters);await flush();};
 const poll=async()=>{const entry=[...timers].find(([,value])=>value.delay<=2500);assert.ok(entry,'pending poll required');timers.delete(entry[0]);await entry[1].fn();await flush();};
 const latest=()=>events.filter(e=>e.type==='results').at(-1)?.hotels||[];
 const providers=()=>[...new Set(latest().flatMap(h=>h.offers.map(o=>o.provider)))].sort();
 return {data,start,resume,poll,events,calls,dbBodies,nativeCalls,anexCalls,andromedaQuoteCalls,observationCalls,mealCatalogCalls,latest,providers,timers,get searchId(){return currentId;}};
}
const tests=[];const test=(name,fn)=>tests.push([name,fn]);
const observed=(q,price=97500)=>{const childAges=String(q.childs||'').trim()?String(q.childs).split(',').map(Number).sort((a,b)=>a-b):[],regionIds=[...new Set((q.regionIds||[]).map(Number))].sort((a,b)=>a-b);return {ok:true,source:'latest-known-exact-segments-from-anytour-first-party-observations',cachedPriceIsFinal:false,currency:'RUB',adults:Number(q.adults),childrenCount:childAges.length,childAges,childAgesSignature:childAges.join(','),departureId:Number(q.departureId),countryId:Number(q.countryId),regionId:regionIds.length===1?regionIds[0]:null,regionIds,dateFrom:q.dateFrom,dateTo:q.dateTo,nightsFrom:Number(q.nightsFrom),nightsTo:Number(q.nightsTo),series:[{date:q.dateFrom,observed:true,minPrice:price}]};};
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
test('cached ANEX rehydrates with a fresh same-provider search identity and never sends stale refs',async()=>{
 const staleOffer=hash('anex'),staleSearch=hash('search-anex');
 const h=harness({
  database:async(_n,p)=>rehydratableSnapshot(p,'anex'),
  anex:async body=>({response:{ok:true,status:200,json:async()=>directAnex(body,{offerRef:'anex_online:'+'9'.repeat(64),localId:101,searchRef:'f'.repeat(32)})}})
 });
 canonicalMeals(h);await h.resume();
 const cached=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');
 assert.ok(cached?.cached);assert.ok(cached.raw?.rehydration,'LOCAL cached offer carries durable descriptor');
 const result=await h.data.rehydrateCached(cached);
 assert.equal(result.state,'current');assert.equal(result.provider,'anex');assert.equal(result.hotelId,501);assert.equal(result.offers.length,1);
 const current=result.offers[0];assert.equal(current.cached,false);assert.equal(current.provider,'anex');assert.equal(current.raw.anexSessionCurrent,true);
 assert.equal(current.raw.searchRef,'f'.repeat(32));assert.equal(current.raw.offerRef,'anex_online:'+'9'.repeat(64));
 assert.equal(h.anexCalls.length,1);assert.equal(h.anexCalls[0].action,'search');
 const sent=JSON.stringify(h.anexCalls[0]);assert.equal(sent.includes(staleOffer),false);assert.equal(sent.includes(staleSearch),false);
 assert.equal(h.anexCalls[0].params.dateFrom,trip.from);assert.equal(h.anexCalls[0].params.dateTo,trip.from);
 assert.equal(h.anexCalls[0].params.nightsFrom,7);assert.equal(h.anexCalls[0].params.nightsTo,7);
 assert.deepEqual(Array.from(h.anexCalls[0].params.hotelIds||[]),['101']);
});
test('cached Andromeda rehydrates in the same provider and retains exact scope for quote follow-up',async()=>{
 const h=harness({
  database:async(_n,p)=>rehydratableSnapshot(p,'andromeda'),
  native:async body=>({response:{ok:true,status:200,json:async()=>directAndromeda(body,{localId:101,offerRef:'offer_'+'8'.repeat(64),searchRef:'7'.repeat(64)})}}),
  andromedaQuote:async body=>{
   assert.equal(body.params.dateFrom,trip.from);assert.equal(body.params.dateTo,trip.from);
   assert.equal(body.params.nightsFrom,7);assert.equal(body.params.nightsTo,7);
   assert.deepEqual(Array.from(body.params.hotelIds||[]),['101']);
   return {response:{ok:true,status:200,json:async()=>({ok:true,data:andromedaVerified()})}};
  }
 });
 canonicalMeals(h);await h.resume();
 const cached=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='andromeda');
 assert.ok(cached?.cached);assert.ok(cached.raw?.rehydration);
 const result=await h.data.rehydrateCached(cached);
 assert.equal(result.state,'current');assert.equal(result.offers.length,1);
 const current=result.offers[0];assert.equal(current.cached,false);assert.equal(current.provider,'andromeda');
 assert.equal(current.raw.offerRef,'offer_'+'8'.repeat(64));assert.ok(current.raw.rehydrationParams);
 const quote=await h.data.verifyAndromeda(current);
 assert.equal(quote.state,'quote_verified');assert.equal(h.andromedaQuoteCalls.length,1);
});
test('cached same-provider rehydration returns empty without falling through to another provider',async()=>{
 const h=harness({
  database:async(_n,p)=>rehydratableSnapshot(p,'anex'),
  anex:async body=>({response:{ok:true,status:200,json:async()=>directAnex(body,{empty:true})}})
 });
 canonicalMeals(h);await h.resume();
 const cached=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');
 const result=await h.data.rehydrateCached(cached);
 assert.equal(result.state,'empty');assert.equal(result.provider,'anex');assert.equal(result.offers.length,0);
 assert.equal(h.nativeCalls.length,0);assert.equal(h.calls.length,0,'rehydration does not start Tourvisor');
});
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
test('direct ANEX group verification re-searches exact scope and expands without Tourvisor fallback',async()=>{
 const groupRef='anex_online:'+'b'.repeat(64),verifyRef='c'.repeat(32);let verification=false;
 const h=harness({anex:async body=>{
  if(body.action==='search'&&verification){
   return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101,searchRef:verifyRef})}};
  }
  if(body.action==='expand')return {response:{ok:true,json:async()=>expandedAnex(body,{groupRef,searchRef:verifyRef,localId:101})}};
  return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101})}};
 }});
 canonicalMeals(h);await h.start();await h.poll();
 const offer=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');assert.ok(offer);assert.equal(offer.raw.anexKind,'group_minimum');
 verification=true;const beforeTourvisor=h.calls.filter(call=>call.action==='search_start').length;
 const result=await h.data.expandAnexGroup(offer);
 assert.equal(h.calls.filter(call=>call.action==='search_start').length,beforeTourvisor,'verification must not launch Tourvisor');
 const verifyCalls=h.anexCalls.slice(-2);assert.deepEqual(verifyCalls.map(call=>call.action),['search','expand']);
 assert.equal(verifyCalls[0].params.dateFrom,trip.from);assert.equal(verifyCalls[0].params.dateTo,trip.from);
 assert.equal(verifyCalls[0].params.nightsFrom,7);assert.equal(verifyCalls[0].params.nightsTo,7);
 assert.deepEqual(verifyCalls[0].params.hotelIds,['101']);assert.equal(verifyCalls[0].params.meal,'7');
 assert.equal(verifyCalls[1].offer_ref,groupRef);assert.equal(verifyCalls[1].search_ref,verifyRef);assert.equal(verifyCalls[1].local_hotel_id,101);
 assert.equal(result.hotelId,offer.hotelId);assert.equal(result.offers.length,2);
 assert.ok(result.offers.every(item=>item.provider==='anex'&&item.raw.anexKind==='concrete'&&item.raw.anexLocalHotelId===101));
});
test('expanded concrete ANEX offer verifies in the same provider session without Tourvisor fallback',async()=>{
 const groupRef='anex_online:'+'b'.repeat(64),verifyRef='c'.repeat(32);let verification=false;
 const h=harness({anex:async body=>{
  if(body.action==='search'&&verification)return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101,searchRef:verifyRef})}};
  if(body.action==='expand')return {response:{ok:true,json:async()=>expandedAnex(body,{groupRef,searchRef:verifyRef,localId:101})}};
  if(body.action==='offer')return {response:{ok:true,status:200,json:async()=>currentAnexConcrete(body)}};
  return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101})}};
 }});
 canonicalMeals(h);await h.start();await h.poll();
 const group=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');verification=true;
 const expanded=await h.data.expandAnexGroup(group),concrete=expanded.offers[0];assert.ok(concrete);
 assert.equal(concrete.raw.anexKind,'concrete');assert.equal(concrete.raw.anexSessionCurrent,true);
 const beforeTourvisor=h.calls.filter(call=>call.action==='search_start').length;
 const current=await h.data.verifyAnexConcrete(concrete);
 assert.equal(h.calls.filter(call=>call.action==='search_start').length,beforeTourvisor,'concrete verification never launches Tourvisor');
 const request=h.anexCalls.at(-1);assert.equal(request.action,'offer');assert.equal(request.generation,concrete.raw.anexGeneration);
 assert.equal(request.search_ref,verifyRef);assert.equal(request.offer_ref,concrete.raw.offerRef);assert.equal(request.local_hotel_id,101);
 assert.equal(current.state,'current');assert.equal(current.currentContextVerified,true);assert.equal(current.finalPriceReady,false);
 assert.equal(current.finalPrice,null);assert.equal(current.finalPriceVerified,false);
});
test('ANEX AdditionalPrices requires a current concrete receipt and is explicit no-replay',async()=>{
 const groupRef='anex_online:'+'b'.repeat(64),verifyRef='c'.repeat(32);let verification=false;
 const h=harness({anex:async body=>{
  if(body.action==='search'&&verification)return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101,searchRef:verifyRef})}};
  if(body.action==='expand')return {response:{ok:true,json:async()=>expandedAnex(body,{groupRef,searchRef:verifyRef,localId:101})}};
  if(body.action==='offer')return {response:{ok:true,status:200,json:async()=>currentAnexConcrete(body)}};
  if(body.action==='additional_prices')return {response:{ok:true,status:200,json:async()=>additionalAnexConcrete(body)}};
  return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101})}};
 }});
 canonicalMeals(h);await h.start();await h.poll();const group=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');verification=true;
 const concrete=(await h.data.expandAnexGroup(group)).offers[0];
 const before=h.anexCalls.length;
 await assert.rejects(h.data.verifyAnexAdditional(concrete),/Сначала подтвердите/);
 assert.equal(h.anexCalls.length,before,'AdditionalPrices cannot reach HTTP before current-offer receipt');
 await h.data.verifyAnexConcrete(concrete);
 const result=await h.data.verifyAnexAdditional(concrete);
 const request=h.anexCalls.at(-1);
 assert.deepEqual(Object.keys(request).sort(),['action','generation','local_hotel_id','offer_ref','search_ref']);
 assert.equal(request.action,'additional_prices');assert.equal(request.generation,concrete.raw.anexGeneration);
 assert.equal(request.search_ref,verifyRef);assert.equal(request.offer_ref,concrete.raw.offerRef);assert.equal(request.local_hotel_id,101);
 assert.equal(result.state,'additional_prices');assert.equal(result.finalPriceVerified,false);assert.equal(result.arithmeticApplied,true);
 assert.deepEqual(JSON.parse(JSON.stringify(result.searchPrice)),{amount:'1510000',currency:'RUB'});
 assert.deepEqual(JSON.parse(JSON.stringify(result.partySurcharge)),{amount:'20000',currency:'RUB'});
 assert.deepEqual(JSON.parse(JSON.stringify(result.calculatedTotal)),{amount:'1530000',currency:'RUB'});
 const sent=h.anexCalls.length;
 await assert.rejects(h.data.verifyAnexAdditional(concrete),/уже запрашивались/);
 assert.equal(h.anexCalls.length,sent,'same AdditionalPrices request cannot replay in one browser generation');
});
test('ANEX AdditionalPrices rejects inconsistent server arithmetic',async()=>{
 const groupRef='anex_online:'+'b'.repeat(64),verifyRef='c'.repeat(32);let verification=false;
 const h=harness({anex:async body=>{
  if(body.action==='search'&&verification)return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101,searchRef:verifyRef})}};
  if(body.action==='expand')return {response:{ok:true,json:async()=>expandedAnex(body,{groupRef,searchRef:verifyRef,localId:101})}};
  if(body.action==='offer')return {response:{ok:true,status:200,json:async()=>currentAnexConcrete(body)}};
  if(body.action==='additional_prices')return {response:{ok:true,status:200,json:async()=>additionalAnexConcrete(body,{search:'1510000',surcharge:'20000',total:'1540000'})}};
  return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101})}};
 }});
 canonicalMeals(h);await h.start();await h.poll();const group=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');verification=true;
 const concrete=(await h.data.expandAnexGroup(group)).offers[0];await h.data.verifyAnexConcrete(concrete);
 await assert.rejects(h.data.verifyAnexAdditional(concrete),/применимый расчёт/);
 assert.equal(h.anexCalls.filter(call=>call.action==='additional_prices').length,1);
});
test('Stop aborts pending ANEX AdditionalPrices and invalidates its receipt',async()=>{
 const groupRef='anex_online:'+'b'.repeat(64),verifyRef='c'.repeat(32),gate=defer();let verification=false,additionalSignal=null;
 const h=harness({anex:async(body,signal)=>{
  if(body.action==='search'&&verification)return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101,searchRef:verifyRef})}};
  if(body.action==='expand')return {response:{ok:true,json:async()=>expandedAnex(body,{groupRef,searchRef:verifyRef,localId:101})}};
  if(body.action==='offer')return {response:{ok:true,status:200,json:async()=>currentAnexConcrete(body)}};
  if(body.action==='additional_prices'){additionalSignal=signal;await gate.promise;return {response:{ok:true,status:200,json:async()=>additionalAnexConcrete(body)}};}
  return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101})}};
 }});
 canonicalMeals(h);await h.start();await h.poll();const group=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');verification=true;
 const concrete=(await h.data.expandAnexGroup(group)).offers[0];await h.data.verifyAnexConcrete(concrete);
 const pending=h.data.verifyAnexAdditional(concrete);await waitFor(()=>additionalSignal!==null,'pending AdditionalPrices request required');
 assert.equal(additionalSignal.aborted,false);h.data.stop();assert.equal(additionalSignal.aborted,true);
 gate.resolve();await assert.rejects(pending,/Условия поиска изменились/);
 await assert.rejects(h.data.verifyAnexAdditional(concrete),/Сначала подтвердите/);
 assert.equal(h.anexCalls.filter(call=>call.action==='additional_prices').length,1,'stale AdditionalPrices is never replayed');
});
test('only exact-expanded session-current ANEX concrete rows can use provider follow-up',async()=>{
 const h=harness();const raw={selectionEnabled:false,anexKind:'concrete',anexLocalHotelId:101,anexGeneration:1,
  searchRef:'c'.repeat(32),offerRef:'anex_online:'+'1'.repeat(64),anexSessionCurrent:false};
 await assert.rejects(h.data.verifyAnexConcrete({cached:false,provider:'anex',raw}),/устарело/);
 assert.equal(h.anexCalls.length,0,'non-current concrete row cannot reach ANEX follow-up');
});
test('Stop aborts pending concrete ANEX verification and rejects stale response',async()=>{
 const groupRef='anex_online:'+'b'.repeat(64),verifyRef='c'.repeat(32),gate=defer();let verification=false,offerSignal=null;
 const h=harness({anex:async(body,signal)=>{
  if(body.action==='search'&&verification)return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101,searchRef:verifyRef})}};
  if(body.action==='expand')return {response:{ok:true,json:async()=>expandedAnex(body,{groupRef,searchRef:verifyRef,localId:101})}};
  if(body.action==='offer'){offerSignal=signal;await gate.promise;return {response:{ok:true,status:200,json:async()=>currentAnexConcrete(body)}};}
  return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101})}};
 }});
 canonicalMeals(h);await h.start();await h.poll();const group=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');verification=true;
 const concrete=(await h.data.expandAnexGroup(group)).offers[0],pending=h.data.verifyAnexConcrete(concrete);
 await waitFor(()=>offerSignal!==null,'pending concrete ANEX request required');assert.equal(offerSignal.aborted,false);
 h.data.stop();assert.equal(offerSignal.aborted,true);gate.resolve();await assert.rejects(pending,/Условия поиска изменились/);
 assert.equal(h.anexCalls.filter(call=>call.action==='offer').length,1,'stale concrete follow-up is never replayed');
});
test('Stop invalidates and aborts a pending direct ANEX group verification',async()=>{
 const groupRef='anex_online:'+'b'.repeat(64),verifyRef='c'.repeat(32),gate=defer();let verification=false,expandSignal=null;
 const h=harness({anex:async(body,signal)=>{
  if(body.action==='search'&&verification)return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101,searchRef:verifyRef})}};
  if(body.action==='expand'){expandSignal=signal;await gate.promise;return {response:{ok:true,json:async()=>expandedAnex(body,{groupRef,searchRef:verifyRef,localId:101})}};}
  return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101})}};
 }});
 canonicalMeals(h);await h.start();await h.poll();
 const offer=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');assert.ok(offer);
 const before=JSON.stringify(h.latest());
 verification=true;const pending=h.data.expandAnexGroup(offer);
 await waitFor(()=>h.anexCalls.at(-1)?.action==='expand','pending ANEX expand request required');
 assert.ok(expandSignal);assert.equal(expandSignal.aborted,false);
 h.data.stop();assert.equal(expandSignal.aborted,true,'Stop aborts exact-provider verification immediately');
 gate.resolve();
 await assert.rejects(pending,/Условия поиска изменились/);
 assert.equal(JSON.stringify(h.latest()),before,'stale verification cannot mutate canonical result inventory');
 assert.equal(h.anexCalls.filter(call=>call.action==='expand').length,1,'stale verification is never replayed');
});
test('direct ANEX group verification fails closed when exact group identity is no longer returned',async()=>{
 const groupRef='anex_online:'+'b'.repeat(64),otherRef='anex_online:'+'d'.repeat(64);let verification=false;
 const h=harness({anex:async body=>{
  if(body.action==='search'&&verification)return {response:{ok:true,json:async()=>directAnex(body,{offerRef:otherRef,localId:101,searchRef:'e'.repeat(32)})}};
  return {response:{ok:true,json:async()=>directAnex(body,{offerRef:groupRef,localId:101})}};
 }});
 canonicalMeals(h);await h.start();await h.poll();const offer=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');
 verification=true;await assert.rejects(h.data.expandAnexGroup(offer),/предложение ANEX изменилось/);
 assert.equal(h.anexCalls.at(-1).action,'search');assert.equal(h.anexCalls.some(call=>call.action==='expand'),false);
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
test('observation aggregates preserve exact party and canonical multi-resort OR scope',async()=>{
 const h=harness({observations:q=>observed(q)}),signal=new AbortController().signal;
 for(const f of [{stars:[5]},{meals:['AI']},{amenities:['3:15']},{min:1},{max:1500000},{hotelId:501},{q:'hotel'},{flight:['regular']},{operators:['ANEX']},{rating:true}]){
  assert.equal((await h.data.observedCalendar(trip,trip.from,trip.to,signal,f)).length,0);
 }
 assert.equal(h.observationCalls.length,0);
 const family={...trip,adults:1,ages:[7,3]};
 assert.equal((await h.data.observedCalendar(family,trip.from,trip.to,signal,{})).length,1);
 assert.equal(h.observationCalls[0].adults,'1');assert.equal(h.observationCalls[0].childs,'3,7');assert.deepEqual(h.observationCalls[0].regionIds,[]);
 h.data.catalog.regions['4']=[{id:'23',name:'Сиде'},{id:'22',name:'Кемер'}];
 await h.data.observedCalendar(trip,trip.from,trip.to,signal,{resorts:['Сиде']});
 assert.equal(h.observationCalls[1].regionId,'23');assert.deepEqual(h.observationCalls[1].regionIds,['23']);
 assert.equal(h.observationCalls[1].adults,'2');assert.equal(h.observationCalls[1].childs,'');
 await h.data.observedCalendar(trip,trip.from,trip.to,signal,{resorts:['Сиде','Кемер']});
 assert.deepEqual(h.observationCalls[2].regionIds,['22','23'],'multi-resort calendar sends canonical normalized OR scope');
 assert.equal(Object.hasOwn(h.observationCalls[2],'regionId'),false,'multi-resort request cannot collapse to one region');
});
test('observation response must match exact date night party and region scope',async()=>{
 for(const patch of [{countryId:99},{adults:1},{childrenCount:1},{childAges:[3]},{childAgesSignature:'3'},{nightsTo:9},{regionId:1},{regionIds:[1]},{dateFrom:'2026-01-01'},{cachedPriceIsFinal:true},{series:[{date:trip.from,observed:true,minPrice:0}]}]){
  const h=harness({observations:q=>({...observed(q),...patch})});
  await assert.rejects(h.data.observedCalendar(trip,trip.from,trip.to,new AbortController().signal,{}));
 }
 const scoped=harness({observations:q=>({...observed(q),regionIds:[22],regionId:22})});
 scoped.data.catalog.regions['4']=[{id:'23',name:'Сиде'},{id:'22',name:'Кемер'}];
 await assert.rejects(scoped.data.observedCalendar(trip,trip.from,trip.to,new AbortController().signal,{resorts:['Сиде','Кемер']}),
  /не соответствуют/,'a partial region echo cannot stand in for the selected OR scope');
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
test('cached URL resume reads LOCAL only and never starts supplier searches',async()=>{
 const h=harness({
  database:(i,p)=>snapshot(p,['tourvisor','anex','andromeda']),
  native:async()=>assert.fail('cached resume must not call Andromeda'),
  anex:async()=>assert.fail('cached resume must not call ANEX')
 });
 await h.resume();
 assert.equal(h.dbBodies.length,1,'cached resume performs one LOCAL read');
 assert.equal(h.calls.length,0,'cached resume must not call Tourvisor runtime APIs');
 assert.equal(h.nativeCalls.length,0,'cached resume must not call Andromeda');
 assert.equal(h.anexCalls.length,0,'cached resume must not call ANEX');
 assert.equal(h.searchId,0,'cached resume never creates a supplier search id');
 assert.deepEqual(h.providers(),['andromeda','anex','tourvisor'],'cached LOCAL inventory keeps its provider provenance');
 assert.ok(h.events.some(e=>e.type==='loading'&&e.cachedResume===true),'cached resume is explicitly labelled');
 const complete=h.events.filter(e=>e.type==='complete').at(-1);
 assert.ok(complete,'cached resume reaches a terminal state');
 assert.equal(complete.cachedResume,true);assert.equal(complete.canContinue,false);
 for(const provider of ['tourvisor','anex','andromeda']){
  assert.equal(complete.sources[provider].status,'skipped',provider+' is explicitly skipped during cached resume');
  assert.equal(complete.sources[provider].offers,0);
 }
 assert.equal(complete.sources.database.status,'complete');
});
test('cached URL resume fails closed to LOCAL without supplier fallback',async()=>{
 const h=harness({
  database:()=>{throw new Error('synthetic LOCAL failure');},
  native:async()=>assert.fail('LOCAL failure must not fall back to Andromeda'),
  anex:async()=>assert.fail('LOCAL failure must not fall back to ANEX')
 });
 await h.resume();
 assert.equal(h.calls.length,0);assert.equal(h.nativeCalls.length,0);assert.equal(h.anexCalls.length,0);
 assert.ok(h.events.some(e=>e.type==='database-error'),'LOCAL failure is surfaced');
 const complete=h.events.filter(e=>e.type==='complete').at(-1);
 assert.equal(complete.cachedResume,true);assert.equal(complete.sources.database.status,'error');
 assert.equal(complete.canContinue,false,'cached failure requires an explicit fresh retry');
});
test('direct ANEX initial search is limited to the first seven days of a wider user range',async()=>{
 const search={...trip,to:'2026-10-19'};
 const h=harness({
  anex:async body=>({response:{ok:true,json:async()=>directAnex(body,{offerRef:'anex_online:'+'1'.repeat(64),localId:301,searchRef:'1'.repeat(32)})}}),
  database:(i,p)=>snapshot(p,[])
 });
 await h.data.search(structuredClone(search),event=>h.events.push(event),[],{min:0,max:null});await flush();
 await h.poll();await flush();
 assert.equal(h.anexCalls.length,1,'one user search must schedule only one direct ANEX supplier window');
 assert.deepEqual([h.anexCalls[0].params.dateFrom,h.anexCalls[0].params.dateTo],['2026-09-29','2026-10-05']);
 const receipt=h.events.filter(e=>e.type==='provider'&&e.provider==='anex'&&e.windowsLoaded===1).at(-1);
 assert.ok(receipt);
 assert.equal(receipt.status,'complete');assert.equal(receipt.windowsTotal,1);assert.equal(receipt.offers,1);
 const complete=h.events.filter(e=>e.type==='complete').at(-1);assert.ok(complete);
 assert.equal(complete.sources.anex.status,'complete');assert.equal(complete.sources.anex.windowsLoaded,1);assert.equal(complete.sources.anex.windowsTotal,1);
 assert.equal(h.latest().flatMap(hotel=>hotel.offers).filter(offer=>offer.provider==='anex').length,1);
});
test('direct ANEX keeps a shorter user range unchanged',async()=>{
 const search={...trip,to:'2026-10-03'};
 const h=harness({
  anex:async body=>({response:{ok:true,json:async()=>directAnex(body,{offerRef:'anex_online:'+'2'.repeat(64),localId:302,searchRef:'2'.repeat(32)})}}),
  database:(i,p)=>snapshot(p,[])
 });
 await h.data.search(structuredClone(search),event=>h.events.push(event),[],{min:0,max:null});await flush();
 await h.poll();await flush();
 assert.equal(h.anexCalls.length,1);
 assert.deepEqual([h.anexCalls[0].params.dateFrom,h.anexCalls[0].params.dateTo],[search.from,search.to]);
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
 await h.start();assert.equal(h.nativeCalls.length,1);assert.equal(h.nativeCalls[0].generation,1);assert.equal(h.nativeCalls[0].page,1);
 assert.deepEqual(Object.keys(h.nativeCalls[0]).sort(),['generation','page','params']);
 assert.equal(JSON.stringify(h.nativeCalls[0].params),JSON.stringify(h.data.params(trip)));
 const completing=h.poll();await flush();assert.deepEqual(h.providers(),['tourvisor']);assert.equal(h.events.some(e=>e.type==='complete'),false);
 gate.resolve();await completing;await flush();assert.equal(h.nativeCalls.length,1);assert.deepEqual(h.providers(),['andromeda','tourvisor']);
 assert.equal(h.dbBodies.length,3);assert.ok(h.events.some(e=>e.type==='provider'&&e.provider==='andromeda'&&e.status==='complete'));assert.equal(h.events.at(-1).type,'complete');
});
test('remaining Andromeda pages join the first union before overall completion',async()=>{
 const page2=defer(),ref=page=>'offer_'+String(page).repeat(64);
 const h=harness({
  native:async body=>{
   if(body.page===2)await page2.promise;
   return {response:{ok:true,json:async()=>directAndromeda(body,{offerRef:ref(body.page),localId:200+body.page,pagesCount:3,status:'complete'})}};
  },
  database:(i,p)=>snapshot(p,[])
 });
 await h.start();await flush();
 await waitFor(()=>h.nativeCalls.length===2,'Andromeda page 2 must start while Tourvisor is still running');
 assert.deepEqual(h.nativeCalls.map(call=>call.page),[1,2]);
 const andromedaLoading=h.events.filter(e=>e.type==='provider'&&e.provider==='andromeda'&&e.status==='loading'&&e.background===true).at(-1);
 assert.ok(andromedaLoading,'in-progress Andromeda pagination remains visible as background loading');
 assert.equal(andromedaLoading.pagesLoaded,1);assert.equal(andromedaLoading.pagesTotal,3);assert.equal(andromedaLoading.offers,1);
 const completing=h.poll();await flush();
 assert.equal(h.events.some(e=>e.type==='complete'),false,'first overall completion waits for terminal Andromeda pagination');
 page2.resolve();await completing;await flush();
 assert.deepEqual(h.nativeCalls.map(call=>call.page),[1,2,3]);
 const receipt=h.events.filter(e=>e.type==='provider'&&e.provider==='andromeda'&&e.pagesLoaded===3).at(-1);
 assert.equal(receipt.status,'complete');assert.equal(receipt.pagesTotal,3);assert.equal(receipt.offers,3);
 const complete=h.events.filter(e=>e.type==='complete').at(-1);assert.ok(complete);
 assert.equal(complete.sources.andromeda.status,'complete');assert.equal(complete.sources.andromeda.pagesLoaded,3);assert.equal(complete.sources.andromeda.pagesTotal,3);
 assert.equal(h.latest().flatMap(hotel=>hotel.offers).filter(offer=>offer.provider==='andromeda').length,3,'all mapped Andromeda pages join the first completed union');
 assert.ok(h.dbBodies.length>=4,'completed initial pagination performs serial LOCAL readback before first complete');
});
test('late Andromeda page failure makes the first union partial while preserving accepted pages',async()=>{
 const ref=page=>'offer_'+String(page).repeat(64);
 const h=harness({
  native:async body=>body.page===1
   ?{response:{ok:true,json:async()=>directAndromeda(body,{offerRef:ref(1),localId:211,pagesCount:3,status:'complete'})}}
   :{response:{ok:false,status:503,json:async()=>({ok:false,error:'supplier_unavailable'})}},
  database:(i,p)=>snapshot(p,[])
 });
 await h.start();await flush();
 await waitFor(()=>h.events.some(e=>e.type==='provider'&&e.provider==='andromeda'&&e.continuationFailed===true),'late page failure must produce a partial retained receipt');
 await h.poll();
 const receipt=h.events.filter(e=>e.type==='provider'&&e.provider==='andromeda'&&e.continuationFailed===true).at(-1);
 assert.deepEqual(h.nativeCalls.map(call=>call.page),[1,2]);assert.equal(receipt.status,'partial');
 assert.equal(receipt.pagesLoaded,1);assert.equal(receipt.pagesTotal,3);assert.equal(receipt.offers,1);
 assert.ok(h.latest().flatMap(hotel=>hotel.offers).some(offer=>offer.provider==='andromeda'),'a late page failure must not clear the accepted first page');
 const complete=h.events.filter(e=>e.type==='complete').at(-1);assert.ok(complete);
 assert.equal(complete.sources.andromeda.status,'partial');assert.equal(complete.sources.andromeda.continuationFailed,true,'first completion discloses retained partial Andromeda coverage');
});
test('stop aborts a pending Andromeda first-union continuation before another page is applied',async()=>{
 const page2=defer(),ref=page=>'offer_'+String(page).repeat(64);
 const h=harness({
  native:async body=>{
   if(body.page===2)await page2.promise;
   return {response:{ok:true,json:async()=>directAndromeda(body,{offerRef:ref(body.page),localId:220+body.page,pagesCount:3,status:'complete'})}};
  },
  database:(i,p)=>snapshot(p,[])
 });
 await h.start();await flush();
 await waitFor(()=>h.nativeCalls.length===2,'Andromeda page 2 must already be pending before Tourvisor completion');
 const before=h.events.length;h.data.stop();page2.resolve();await flush();
 assert.deepEqual(h.nativeCalls.map(call=>call.page),[1,2],'stopped generation must never request page 3');
 assert.equal(h.events.length,before,'stopped generation must ignore the late Andromeda page');
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
test('direct Andromeda verification uses exact same-provider quote without Tourvisor fallback',async()=>{
 const h=harness({
  native:async body=>({response:{ok:true,status:200,json:async()=>directAndromeda(body)}}),
  andromedaQuote:async body=>({response:{ok:true,status:200,json:async()=>({ok:true,data:andromedaVerified(101,'1499000')})}})
 });
 await h.start();await h.poll();
 const offer=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='andromeda');assert.ok(offer);
 const beforeTourvisor=h.calls.filter(call=>call.action==='search_start').length;
 const quote=await h.data.verifyAndromeda(offer);
 assert.equal(h.calls.filter(call=>call.action==='search_start').length,beforeTourvisor,'Andromeda verification never launches Tourvisor');
 assert.equal(h.andromedaQuoteCalls.length,1);
 const body=h.andromedaQuoteCalls[0],context=offer.raw.offer_context;
 assert.equal(body.action,'quote');assert.equal(body.generation,context.generation);assert.equal(body.page,context.page);
 assert.deepEqual(body.offer_context,{provider:'andromeda',search_ref:context.search_ref,generation:context.generation,page:context.page,offer_ref:context.offer_ref});
 assert.equal(body.listing_price_ref,'listing_'+'e'.repeat(64));assert.deepEqual(body.params,h.nativeCalls[0].params);
 assert.equal(Object.hasOwn(body,'price'),false);assert.equal(Object.hasOwn(body,'local_hotel_id'),false);
 assert.equal(quote.state,'quote_verified');assert.deepEqual(JSON.parse(JSON.stringify(quote.finalPrice)),{amount:'1499000',currency:'RUB'});
 assert.equal(quote.finalPriceVerified,true);assert.equal(quote.flightSelectionRequired,false);assert.equal(quote.flights.length,2);
 assert.ok(quote.flights.every(row=>!Object.hasOwn(row,'flightRef')),'verified public flights never invent continuation refs');
});
test('Andromeda flight choice continuation accepts only retained outbound and return refs',async()=>{
 let first=true;
 const h=harness({
  native:async body=>({response:{ok:true,status:200,json:async()=>directAndromeda(body)}}),
  andromedaQuote:async body=>{
   if(first){first=false;return {response:{ok:true,status:200,json:async()=>({ok:true,data:andromedaChoice(101)})}};}
   return {response:{ok:true,status:200,json:async()=>({ok:true,data:andromedaVerified(101,'1512345')})}};
  }
 });
 await h.start();await h.poll();
 const offer=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='andromeda');
 const pending=await h.data.verifyAndromeda(offer);
 assert.equal(pending.state,'flight_selection_required');assert.equal(pending.finalPrice,null);assert.equal(pending.flightSelectionRequired,true);
 assert.deepEqual(JSON.parse(JSON.stringify(pending.flights.map(row=>[row.direction,row.flightRef]))),[
  ['0','flight_'+'1'.repeat(32)],['0','flight_'+'2'.repeat(32)],['1','flight_'+'3'.repeat(32)]
 ]);
 await assert.rejects(h.data.verifyAndromeda(offer,{provider:'andromeda',outbound_ref:'flight_'+'9'.repeat(32),return_ref:'flight_'+'3'.repeat(32)}),/устарело/);
 assert.equal(h.andromedaQuoteCalls.length,1,'unknown opaque ref is rejected before HTTP');
 const verified=await h.data.verifyAndromeda(offer,{provider:'andromeda',outbound_ref:'flight_'+'2'.repeat(32),return_ref:'flight_'+'3'.repeat(32)});
 assert.equal(h.andromedaQuoteCalls.length,2);const continuation=h.andromedaQuoteCalls[1];
 assert.equal(continuation.action,'quote_select_flights');
 assert.deepEqual(JSON.parse(JSON.stringify(continuation.flight_selection)),{provider:'andromeda',outbound_ref:'flight_'+'2'.repeat(32),return_ref:'flight_'+'3'.repeat(32)});
 assert.equal(verified.state,'quote_verified');assert.equal(verified.finalPrice.amount,'1512345');
 await assert.rejects(h.data.verifyAndromeda(offer,{provider:'andromeda',outbound_ref:'flight_'+'2'.repeat(32),return_ref:'flight_'+'3'.repeat(32)}),/устарело/);
 assert.equal(h.andromedaQuoteCalls.length,2,'verified continuation cannot be replayed from cleared retained refs');
});
test('Stop aborts pending Andromeda quote and stale result cannot be accepted',async()=>{
 const gate=defer();let quoteSignal=null;
 const h=harness({
  native:async body=>({response:{ok:true,status:200,json:async()=>directAndromeda(body)}}),
  andromedaQuote:async(body,signal)=>{quoteSignal=signal;await gate.promise;return {response:{ok:true,status:200,json:async()=>({ok:true,data:andromedaVerified(101)})}};}
 });
 await h.start();await h.poll();
 const offer=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='andromeda');
 const pending=h.data.verifyAndromeda(offer);await waitFor(()=>quoteSignal!==null,'pending Andromeda quote required');
 assert.equal(quoteSignal.aborted,false);h.data.stop();assert.equal(quoteSignal.aborted,true);
 gate.resolve();await assert.rejects(pending,/Условия поиска изменились/);
});
test('Andromeda quote response fails closed on price, identity and flight-ref corruption',async()=>{
 const invalid=[
  {...andromedaVerified(102)},
  {...andromedaVerified(101),booking_enabled:true},
  {...andromedaVerified(101),final_price_verified:false},
  {...andromedaVerified(101),final_price:{amount:'0',currency:'RUB'}},
  {...andromedaVerified(101),final_price:{amount:'1000',currency:'USD'}},
  {...andromedaChoice(101),flights:[{...andromedaChoice(101).flights[0],flight_ref:'bad'}]},
  {...andromedaChoice(101),flights:andromedaChoice(101).flights.filter(row=>row.direction==='0')}
 ];
 for(const payload of invalid){
  const h=harness({
   native:async body=>({response:{ok:true,status:200,json:async()=>directAndromeda(body)}}),
   andromedaQuote:async()=>({response:{ok:true,status:200,json:async()=>({ok:true,data:payload})}})
  });
  await h.start();await h.poll();const offer=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='andromeda');
  await assert.rejects(h.data.verifyAndromeda(offer),/некорректное подтверждение/);
 }
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

function cachedProviderSnapshot(params,provider,operatorName){
 const data=snapshot(params,[provider]),row=data.hotels[0].offers[0];
 row.listing.tour.party.child_ages=[];
 row.listing.operator={raw:operatorName,canonical_name:operatorName};
 return data;
}
test('cached ANEX rehydration performs a fresh exact same-provider search without stale supplier refs',async()=>{
 const staleOffer=hash('stale-anex-offer'),staleSearch=hash('stale-anex-search');
 const h=harness({
  database:(i,p)=>{const data=cachedProviderSnapshot(p,'anex','ANEX');data.hotels[0].offers[0].listing.identity.offer_ref_digest=staleOffer;data.hotels[0].offers[0].listing.identity.search_ref_digest=staleSearch;return data;},
  anex:async body=>({response:{ok:true,status:200,json:async()=>directAnex(body,{localId:101,searchRef:'f'.repeat(32),offerRef:'anex_online:'+'9'.repeat(64)})}})
 });
 canonicalMeals(h);await h.resume();
 const cached=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');assert.ok(cached?.cached);assert.ok(cached.raw.rehydration);
 const result=await h.data.rehydrateCached(cached);
 assert.equal(result.state,'current');assert.equal(result.provider,'anex');assert.equal(result.hotelId,501);assert.equal(result.offers.length,1);
 const request=h.anexCalls.at(-1);assert.equal(request.action,'search');assert.equal(request.params.dateFrom,trip.from);assert.equal(request.params.dateTo,trip.from);
 assert.equal(request.params.nightsFrom,7);assert.equal(request.params.nightsTo,7);assert.deepEqual(request.params.hotelIds,['101']);
 const sent=JSON.stringify(request);assert.equal(sent.includes(staleOffer),false);assert.equal(sent.includes(staleSearch),false,'cached supplier session identity is never replayed');
 const live=result.offers[0];assert.equal(live.cached,false);assert.equal(live.provider,'anex');assert.equal(live.raw.anexSessionCurrent,true);assert.equal(live.raw.anexLocalHotelId,101);
});
test('cached Andromeda rehydration carries its fresh exact scope into quote verification',async()=>{
 const h=harness({
  database:(i,p)=>cachedProviderSnapshot(p,'andromeda','FUN&SUN'),
  native:async body=>({response:{ok:true,status:200,json:async()=>directAndromeda(body,{localId:101,offerRef:'offer_'+'8'.repeat(64),searchRef:'7'.repeat(64)})}}),
  andromedaQuote:async body=>({response:{ok:true,status:200,json:async()=>({ok:true,data:andromedaVerified(101,'1499000')})}})
 });
 canonicalMeals(h);await h.resume();
 const cached=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='andromeda');assert.ok(cached?.cached);assert.ok(cached.raw.rehydration);
 const result=await h.data.rehydrateCached(cached);assert.equal(result.state,'current');assert.equal(result.offers.length,1);
 const live=result.offers[0];assert.equal(live.cached,false);assert.equal(live.provider,'andromeda');
 assert.equal(h.nativeCalls.length,1);assert.equal(h.nativeCalls[0].params.dateFrom,trip.from);assert.equal(h.nativeCalls[0].params.dateTo,trip.from);assert.deepEqual(h.nativeCalls[0].params.hotelIds,['101']);
 const quote=await h.data.verifyAndromeda(live);assert.equal(quote.state,'quote_verified');assert.equal(h.andromedaQuoteCalls.length,1);
 assert.deepEqual(h.andromedaQuoteCalls[0].params,h.nativeCalls[0].params,'quote uses the exact fresh scope that created the rehydrated offer context');
});
test('Stop aborts pending cached same-provider rehydration and stale response cannot become current',async()=>{
 const gate=defer();let signal=null;
 const h=harness({
  database:(i,p)=>cachedProviderSnapshot(p,'anex','ANEX'),
  anex:async(body,s)=>{signal=s;await gate.promise;return {response:{ok:true,status:200,json:async()=>directAnex(body,{localId:101})}};}
 });
 canonicalMeals(h);await h.resume();const cached=h.latest().flatMap(row=>row.offers).find(item=>item.provider==='anex');
 const pending=h.data.rehydrateCached(cached);await waitFor(()=>signal!==null,'pending cached rehydration required');assert.equal(signal.aborted,false);
 h.data.stop();assert.equal(signal.aborted,true);gate.resolve();await assert.rejects(pending,/Условия поиска изменились/);
 assert.equal(h.anexCalls.filter(call=>call.action==='search').length,1,'stale cached rehydration is never replayed');
});
(async()=>{for(const [name,fn]of tests){await fn();console.log('PASS',name);}console.log('SEARCH3_PROTOTYPE_INVENTORY_LIFECYCLE_OK',tests.length);})().catch(error=>{console.error(error);process.exitCode=1;});