'use strict';
// Fictional responses for the real canonical adapter. Never issue network requests.
const day=new Date(Date.now()+14*86400000).toISOString().slice(0,10);
const back=new Date(Date.parse(day+'T12:00:00Z')+7*86400000).toISOString().slice(0,10);
const trip={origin:'Москва',country:'4',from:day,to:day,minNights:7,maxNights:7,adults:2,ages:[]};
const profile={id:501,catalog:'anytour',revision:1,name:'Вымышленный отель · интеграционная проверка',category:5,rating:4.7,country:{id:4,name:'Турция'},region:{name:'Белек'},description:'Вымышленный отель для проверки переноса интерфейса.',address:'Тестовая улица, 1',images:['/test-photo.svg'],hotelInformation:{services:{child:'Мини-клуб',free:'Тестовая услуга'},infrastructure:{beach:'Песчаный пляж'}}};
const tour={id:'visual-tv-101',price:120000,date:day,nights:7,adults:2,childs:0,meal:{id:7,name:'AI'},roomType:'STANDARD SEA VIEW',placement:'DBL',operator:{name:'ANEX'},fuelCharge:0};
const segment=(number,returning)=>({company:{name:'Тестовая авиакомпания'},number,departure:{date:returning?back:day,time:'10:00',port:{name:returning?'Анталья':'Москва',id:returning?'AYT':'SVO'}},arrival:{date:returning?back:day,time:'14:00',port:{name:returning?'Москва':'Анталья',id:returning?'SVO':'AYT'}},baggage:20,carryOn:'5 кг'});
const flights=[{isDefault:true,price:{value:120000},fuelCharge:0,forward:[segment('TEST101',false)],backward:[segment('TEST102',true)]},{price:{value:133500.5},fuelCharge:0,forward:[segment('TEST201',false)],backward:[segment('TEST202',true)]}];
const searchRef='a'.repeat(32),offerRef='anex_online:'+'b'.repeat(64),andromedaRef='offer_'+'d'.repeat(64);
function fixture({tvFuel=0}={}){
 const calls=[],state={hold:false,failAnex:false,extended:false,samoFailure:null,samoFlightChoice:false};
 const json=async(url,options={})=>{
  const u=new URL(url,'https://anytoour.ru'),body=options.body?JSON.parse(options.body):{},q=u.searchParams,action=q.get('action')||body.action;
  calls.push({url:u.pathname,action,body,query:Object.fromEntries(q)});
  if(u.pathname==='/data/departures-v1.php')return {ok:true,items:[{id:1,name:'Москва'}]};
  if(u.pathname.endsWith('/search3-destination-read-v1.php'))return {ok:true,source:'anytour-destination-identities-v1',provider:'tourvisor',kind:action==='countries'?'country':'region',...(action==='regions'?{parentId:4}:{}),items:action==='countries'?[{id:4,kind:'country',parentId:null,name:'Турция',slug:'turkey',revision:1,tourvisorIds:['4']}]:[{id:21,kind:'region',parentId:4,name:'Белек',slug:'belek',revision:1,tourvisorIds:['21']}]};
  if(u.pathname.endsWith('/hotel-details-read-v1.php')){const ids=q.getAll('legacyHotelIds[]');return {ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:ids,missingLegacyIds:[],items:[profile],links:ids.map(id=>({legacyHotelId:id,anytourHotelId:501}))};}
  if(u.pathname.endsWith('/search3-local-results-read-v1.php')){
   if(action==='meal_catalog')return {ok:true,data:{source:'anytour-search-meal-v1',provider:'tourvisor',scopeKey:'global',available:true,revision:'a'.repeat(64),plans:[{id:7,code:'all-inclusive',nameRu:'Всё включено',nativeIds:['7']},{id:2,code:'breakfast',nameRu:'Завтраки',nativeIds:['3']}]}};
   if(action==='price_calendar')return {ok:true,data:{ok:true,source:'latest-known-exact-segments-from-anytour-first-party-observations',cachedPriceIsFinal:false,currency:'RUB',adults:body.adults,childrenCount:0,childAges:[],childAgesSignature:'',departureId:body.departureId,countryId:body.countryId,regionId:null,regionIds:[],dateFrom:body.dateFrom,dateTo:body.dateTo,nightsFrom:body.nightsFrom,nightsTo:body.nightsTo,series:[{date:day,observed:true,minPrice:97500}]}};
   if(!body.params)throw Error('Unexpected DB action');
   return {ok:true,data:{source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:'e'.repeat(64),scope:{...body.params,scopeVersion:1},hotelCount:0,eligibleHotelCount:0,offerCount:0,storedOfferCount:0,withheldOfferCount:0,categoryFilteredOfferCount:0,omittedHotelCount:0,omittedOfferCount:0,providerOfferCounts:{},selectionAuthority:false,hotels:[]}};
  }
  if(u.pathname.endsWith('/api-anex-search3-preview.php')){
   if(state.failAnex)throw Error('Test provider unavailable');
   const nativeTour={price:{amount:'121000',currency:'RUB'},checkin:day,nights:7,adults:2,children:0,meal:'AI',room:'ANEX STANDARD',kind:'group_minimum',flight_type:'charter',final_price_verified:false,search_ref:searchRef,offer_ref:offerRef,selection_enabled:false};
   const h={local_id:101,name:profile.name,category:5,country:'Турция',region:'Белек',catalog:{hotel_id:101,source:'tourvisor'},tours:[nativeTour]};
   if(action==='expand')return {ok:true,data:{provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'expanded',selection_state:'disabled',external_search_pending:false,first_page_only:true,hotels:[{...h,tours:[{...nativeTour,room:'ANEX CONCRETE',kind:'concrete',offer_ref:'anex_online:'+'1'.repeat(64)}]}]}};
   if(action==='offer')return {ok:true,data:{provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'current',selection_state:'disabled',finalPriceReady:false,offer:{final_price_verified:false,context:{current_context_verified:true}}}};
   if(action==='additional_prices')return {ok:true,data:{provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'additional_prices',selection_state:'disabled',additional_prices:{source:'anex_b2b_additional_prices_daily',application_state:'applied',converted_currency:'RUB',per_person_or_package:'per_person_by_party_type',included_in_search_price:false,arithmetic_applied:true,final_price_verified:false,party_surcharge:{amount:'2000',currency:'RUB',source:'anex_b2b_additional_prices_daily'},search_price:{amount:'121000',currency:'RUB',source:'direct_anex_search'},search_plus_additional:{amount:'123000',currency:'RUB',formula:'search_price_plus_program_date_party_additional'}}}};
   if(action!=='search')throw Error('Unexpected ANEX action '+action);
   return {ok:true,data:{generation:body.generation,provider:'anex',date_range:{from:body.params.dateFrom,to:body.params.dateTo},search_ref:searchRef,external_search_pending:false,pages_read:1,first_page_only:true,hotels:[h]}};
  }
  if(u.pathname.endsWith('/api-andromeda-search3-preview.php'))return {ok:true,data:{provider:'andromeda',generation:body.generation,date_range:{from:body.params.dateFrom,to:body.params.dateTo},grouped:true,first_page_only:false,page:1,pages_count:1,external_search_pending:false,search_ref:'c'.repeat(64),status:'complete',received_offers:1,mapped_offers:1,selection_enabled:false,hotels:[{local_id:101,mapping_status:'resolved',tours:[{provider:'andromeda',price:{amount:'119000',currency:'RUB'},checkin:day,nights:7,adults:2,children:0,meal:'AI',room:'SAMO STANDARD',placement:'DBL',operator:{name:'FUN&SUN'},offer_ref:andromedaRef,offer_context:{provider:'andromeda',search_ref:'c'.repeat(64),generation:body.generation,page:1,offer_ref:andromedaRef},listing_price_ref:'listing_'+'e'.repeat(64),selection_enabled:false}]}]}};
  if(u.pathname.endsWith('/api-andromeda-quote-preview.php')){
   if(state.samoFailure&&(!state.samoFlightChoice||action==='quote_select_flights'))return {ok:false,error:'supplier_unavailable',failure_category:state.samoFailure};
   const result={ok:true,data:{schema_version:1,provider:'andromeda',local_id:101,selection_enabled:true,booking_enabled:false,state:'quote_verified',quote_state:'verified',final_price:{amount:'125500',currency:'RUB'},final_price_verified:true,flight_selection_required:false,flights:[{direction:'0',name:'TEST SAMO OUT',datebeg:day,class:'ECONOM',departure:{town:'Москва',port:'SVO'},arrival:{town:'Анталья',port:'AYT'}},{direction:'1',name:'TEST SAMO BACK',datebeg:back,class:'ECONOM',departure:{town:'Анталья',port:'AYT'},arrival:{town:'Москва',port:'SVO'}}]}};
   if(state.samoFlightChoice&&action==='quote')Object.assign(result.data,{state:'flight_selection_required',quote_state:'unverified',final_price:null,final_price_verified:false,flight_selection_required:true,flights:result.data.flights.map((f,i)=>({...f,flight_ref:'flight_'+String(i+1).repeat(32)}))});
   return result;
  }
  if(u.pathname==='/api-v2.php'){
   if(action==='meals')return [{id:7,name:'AI'},{id:3,name:'BB'}];
   if(action==='search_start')return {searchId:123};
   if(action==='search_status')return {searchId:123,status:'complete',progress:100};
   if(action==='search_continue'){state.extended=true;return {requestCount:1};}
   if(action==='search_results')return [{id:101,provider:'tourvisor',tours:[tour]}];
   if(action==='tour')return {...tour,fuelCharge:tvFuel,hotel:{id:101,name:profile.name}};
   if(action==='flights')return flights.map(pair=>({...pair,fuelCharge:tvFuel}));
  }
  throw Error('Forbidden fixture request: '+url);
 };
 return {json,calls,state};
}
module.exports={fixture,trip,day,back,tour,flights,profile};
