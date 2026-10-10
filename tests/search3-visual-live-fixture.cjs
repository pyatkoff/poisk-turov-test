'use strict';
// Fictional responses for the real canonical adapter. Never issue network requests.
const day=new Date(Date.now()+14*86400000).toISOString().slice(0,10);
const back=new Date(Date.parse(day+'T12:00:00Z')+7*86400000).toISOString().slice(0,10);
const trip={origin:'Москва',country:'4',from:day,to:day,minNights:7,maxNights:7,adults:2,ages:[]};
const profile={id:501,catalog:'anytour',revision:1,name:'Вымышленный отель · интеграционная проверка',category:5,rating:4.7,country:{id:4,name:'Турция'},region:{name:'Белек'},description:'Вымышленный отель для проверки переноса интерфейса.',address:'Тестовая улица, 1',images:['/test-photo.svg'],hotelInformation:{services:{child:'Мини-клуб',free:'Тестовая услуга','7':'Wi-Fi из локального профиля'},infrastructure:{beach:'Песчаный пляж'}}};
const tour={id:'visual-tv-101',price:120000,date:day,nights:7,adults:2,childs:0,meal:{id:7,name:'AI'},roomType:'STANDARD SEA VIEW',placement:'DBL',operator:{name:'ANEX'},fuelCharge:0};
const segment=(number,returning)=>({company:{name:'Тестовая авиакомпания'},number,departure:{date:returning?back:day,time:'10:00',port:{name:returning?'Анталья':'Москва',id:returning?'AYT':'SVO'}},arrival:{date:returning?back:day,time:'14:00',port:{name:returning?'Москва':'Анталья',id:returning?'SVO':'AYT'}},baggage:20,carryOn:'5 кг'});
const flights=[{isDefault:true,price:{value:120000},fuelCharge:0,forward:[segment('TEST101',false)],backward:[segment('TEST102',true)]},{price:{value:133500.5},fuelCharge:0,forward:[segment('TEST201',false)],backward:[segment('TEST202',true)]}];
const searchRef='a'.repeat(32),offerRef='anex_online:'+'b'.repeat(64),andromedaRef='offer_'+'d'.repeat(64);
function hotelCatalogue(){return [...Array.from({length:10},(_,i)=>({id:2001+i,legacyId:7001+i,name:'Rixos Fictional Belek '+String(i+1).padStart(2,'0'),country:'4',countryName:'Турция',images:i===2?[]:i===3?['/test-missing-photo.svg']:['/test-photo.svg']})),...Array.from({length:3},(_,i)=>({id:3001+i,legacyId:8001+i,name:'Rixos Fictional Egypt '+(i+1),country:'100',countryName:'Египет',images:['/test-photo.svg']}))];}
function fixture({tvFuel=0,anexZeroSurcharge=false,anexEmptyAdditional=false}={}){
 const anexWindows=new Map();
 const calls=[],state={hold:false,failAnex:false,extended:false,anexCurrentAdditional:false,anexPackageChoiceCount:2,samoFailure:null,samoFlightChoice:false,samoSolePair:false,samoSearchGate:null,samoMeal:'AI',samoRoom:'SAMO STANDARD',wideFacets:false,countriesFailure:'',countryGates:{},regionsGate:null,tvFlightFuel:tvFuel,hotelCatalogue:[],catalogueCountries:[],catalogueAliases:{},hotelLookupGates:{},hotelLookupError:false,repricingEnabled:false,repricingFailure:''};
 const repriceContexts=new Map();
 const repriceMeta=context=>({enabled:!context.sealed,max_pairs:3,used_pairs:context.used,remaining_pairs:context.sealed?0:3-context.used});
 function reprice(value,provider,body,pairKey=null){
  if(!state.repricingEnabled)return value;
  const key=provider+'|'+body.generation+'|'+JSON.stringify(body.offer_context||[body.search_ref,body.offer_ref,body.local_hotel_id]);
  let context=repriceContexts.get(key);
  if(!context){context={used:0,sealed:false,quotes:new Map(),initial:structuredClone(value),expiresAt:value.expires_at||Math.floor(Date.now()/1000)+600};repriceContexts.set(key,context);}
  const unverified=budget=>{
   const result=structuredClone(context.initial);
   if(provider==='anex')Object.assign(result,{status:budget?'quote_selection_locked':'quote_unknown',final_price_verified:false,selection_state:'disabled',price:null});
   else Object.assign(result,{state:budget?'flight_selection_required':'quote_unknown',quote_state:'unverified',final_price:null,final_price_verified:false,
    flight_selection_required:budget,failure_reason:budget?'ANDROMEDA_FLIGHT_REPRICE_BUDGET':'ANDROMEDA_FLIGHT_REPRICE_STATE_UNKNOWN',failure_category:'quote_state'});
   result.repricing=repriceMeta(context);return result;
  };
  if(context.sealed)return unverified(false);
  if(!pairKey)return {...value,repricing:repriceMeta(context)};
  if(context.quotes.has(pairKey))return {...structuredClone(context.quotes.get(pairKey)),repricing:repriceMeta(context)};
  if(context.used===3)return unverified(true);
  context.used++;
  if(state.repricingFailure==='unknown'){context.sealed=true;return unverified(false);}
  const result=structuredClone(value),now=Math.floor(Date.now()/1000);
  const number=provider==='anex'?Number(body.choice_ref.slice(-1)):(Number(body.flight_selection.outbound_ref.slice(-1))+1)/2;
  if(provider==='anex'){result.choices=structuredClone(context.initial.choices);result.price.amount=String(100000+number*1000);}
  else{
   result.final_price.amount=String(100000+number*1000);
   result.flights=['0','1'].map(direction=>structuredClone(context.initial.flights.find(f=>f.direction===direction
    &&f.flight_ref===(direction==='0'?body.flight_selection.outbound_ref:body.flight_selection.return_ref))));
  }
  result.verified_at=now;result.expires_at=context.expiresAt;result.repricing=repriceMeta(context);
  context.quotes.set(pairKey,structuredClone(result));return result;
 }
 const json=async(url,options={})=>{
  const u=new URL(url,'https://anytoour.ru'),body=options.body?JSON.parse(options.body):{},q=u.searchParams,action=q.get('action')||body.action;
  calls.push({url:u.pathname,action,body,query:Object.fromEntries(q)});
  if(/^\/_preview\/search3-(?:next|local)-candidate\/data\/local-tv-catalog-read-v1\.php$/.test(u.pathname)){
   const values=q.getAll('oldLocalHotelIds[]');
   if(options.body||!values.length||values.length>100||[...q.keys()].some(key=>key!=='oldLocalHotelIds[]')||values.some(value=>!Number.isSafeInteger(Number(value))||Number(value)<1))throw Error('Invalid fictional LOCAL content request');
   const ids=[...new Set(values.map(Number))];
   return {ok:true,source:'anytour-local-tv-catalog-v1',catalog:'local-tv',items:[],requestedIds:ids,missingIds:ids,links:[]};
  }
  if(u.pathname.endsWith('/api-andromeda-search3-preview.php')&&state.samoSearchGate)await state.samoSearchGate;
  if(u.pathname==='/data/departures-v1.php')return {ok:true,items:[{id:1,name:'Москва'},{id:2,name:'Казань'},{id:3,name:'Екатеринбург'}]};
  if(u.pathname.endsWith('/search3-destination-read-v1.php')){
   const departure=q.get('departureId')||String(body.departureId||''),fail=action==='countries'&&state.countriesFailure===departure,gate=action==='countries'?state.countryGates[departure]:state.regionsGate;
   if(gate)await gate;if(fail)return {ok:false,error:'Fictional country catalogue unavailable'};
   return {ok:true,source:'anytour-destination-identities-v1',provider:'tourvisor',kind:action==='countries'?'country':'region',...(action==='regions'?{parentId:4}:{}),items:action==='countries'?[{id:4,kind:'country',parentId:null,name:'Турция',slug:'turkey',revision:1,tourvisorIds:['4']},...state.catalogueCountries]:[{id:21,kind:'region',parentId:4,name:'Белек',slug:'belek',revision:1,tourvisorIds:['21']}]};
  }
  if(u.pathname==='/data/hotel-search-v1.php'){
   const country=q.get('countryId'),query=q.get('q').toLowerCase(),rows=state.hotelCatalogue.filter(h=>h.country===country&&(h.name.toLowerCase().includes(query)||state.catalogueAliases[h.id]?.includes(query))).slice(0,10),gate=state.hotelLookupGates[country];
   if(gate)await gate;if(state.hotelLookupError)return {ok:false,error:'Fictional hotel lookup unavailable'};
   return {ok:true,items:rows.map(h=>({id:h.legacyId,name:h.name,country:{id:Number(h.country),name:h.countryName}}))};
  }
  if(u.pathname.endsWith('/hotel-details-read-v1.php')){
   if(q.has('anytourHotelId')){const h=state.hotelCatalogue.find(h=>String(h.id)===q.get('anytourHotelId'));return {ok:!!h,source:'anytour-canonical-catalog',catalog:'anytour',item:h?{...profile,id:h.id,name:h.name,country:{id:Number(h.country),name:h.countryName},images:h.images}:null};}
   const ids=q.getAll('legacyHotelIds[]'),rows=state.hotelCatalogue.filter(h=>ids.includes(String(h.legacyId)));return {ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:ids,missingLegacyIds:[],items:rows.length?rows.map(h=>({...profile,id:h.id,name:h.name,country:{id:Number(h.country),name:h.countryName},images:h.images})):[profile],links:rows.length?rows.map(h=>({legacyHotelId:String(h.legacyId),anytourHotelId:h.id})):ids.map(id=>({legacyHotelId:id,anytourHotelId:501}))};
  }
  if(u.pathname.endsWith('/search3-local-results-read-v1.php')){
   if(action==='meal_catalog')return {ok:true,data:{source:'anytour-search-meal-v1',provider:'tourvisor',scopeKey:'global',available:true,revision:'a'.repeat(64),plans:[{id:7,code:'all-inclusive',nameRu:'Всё включено',nativeIds:['7']},{id:2,code:'breakfast',nameRu:'Завтраки',nativeIds:['3']}]}};
   if(action==='price_calendar'){
    const priceFrom=body.priceFrom??null,priceTo=body.priceTo??null;
    const minPrice=[97500,167500.5,220000].find(price=>(priceFrom===null||price>=priceFrom)&&(priceTo===null||price<=priceTo))??null;
    return {ok:true,data:{ok:true,source:'latest-known-exact-segments-from-anytour-first-party-observations',cachedPriceIsFinal:false,currency:'RUB',mealPlanIds:body.mealPlanIds||[],priceFrom,priceTo,adults:body.adults,childrenCount:0,childAges:[],childAgesSignature:'',departureId:body.departureId,countryId:body.countryId,regionId:null,regionIds:[],dateFrom:body.dateFrom,dateTo:body.dateTo,nightsFrom:body.nightsFrom,nightsTo:body.nightsTo,series:[{date:day,observed:minPrice!==null,minPrice}]}};
   }
   if(!body.params)throw Error('Unexpected DB action');
   return {ok:true,data:{source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:'e'.repeat(64),scope:{...body.params,scopeVersion:1},hotelCount:0,eligibleHotelCount:0,offerCount:0,storedOfferCount:0,withheldOfferCount:0,categoryFilteredOfferCount:0,omittedHotelCount:0,omittedOfferCount:0,providerOfferCounts:{},selectionAuthority:false,hotels:[]}};
  }
  if(u.pathname.endsWith('/api-anex-search3-preview.php')){
   if(state.failAnex)throw Error('Test provider unavailable');
   if(['quote_start','quote_calculate'].includes(action)){
    const common={provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref};
    const choices=(state.repricingEnabled?[1,2,3,4]:[1,2]).slice(0,state.anexPackageChoiceCount).map(n=>({choice_ref:'anex_quote:'+String(n).repeat(64),current:n===1,
     legs:[{label:`TEST ANEX PACKAGE ${n} OUT · Москва SVO → Анталья AYT · ${day} 10:00`},{label:`TEST ANEX PACKAGE ${n} BACK · Анталья AYT → Москва SVO · ${back} 14:00`}]}));
    if(action==='quote_start')return {ok:true,data:reprice({...common,status:'quote_choices',final_price_verified:false,selection_state:'disabled',choices},'anex',body)};
    if(state.anexQuoteFailure)return {ok:true,data:{...common,status:'quote_failed',final_price_verified:false,selection_state:'disabled'}};
    const choice=choices.find(c=>c.choice_ref===body.choice_ref);if(!choice)throw Error('Foreign fixture ANEX pair');
    return {ok:true,data:reprice({...common,status:'quote_verified',final_price_verified:true,selection_state:'preview_only',choice,
     price:{amount:'135678.90',currency:'RUB',basis:'supplier_gross_package'},verified_at:Math.floor(Date.now()/1000),expires_at:Math.floor(Date.now()/1000)+600},'anex',body,body.choice_ref)};
   }
   const nativeTour={price:{amount:'121000',currency:'RUB'},checkin:day,nights:7,adults:2,children:0,meal:'AI',room:'ANEX STANDARD',kind:'group_minimum',flight_type:'charter',final_price_verified:false,search_ref:searchRef,offer_ref:offerRef,selection_enabled:false};
   const h={local_id:101,name:profile.name,category:5,country:'Турция',region:'Белек',catalog:{hotel_id:101,source:'tourvisor'},tours:[nativeTour]};
   if(action==='expand')return {ok:true,data:{provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'expanded',selection_state:'disabled',external_search_pending:false,first_page_only:true,hotels:[{...h,tours:[{...nativeTour,room:'ANEX CONCRETE',kind:'concrete',offer_ref:'anex_online:'+'1'.repeat(64)}]}]}};
   const additionalPrices=anexEmptyAdditional?{application_state:'unknown',arithmetic_applied:false,final_price_verified:false,rows:[],row_count:0}: {source:'anex_b2b_additional_prices_daily',application_state:'applied',converted_currency:'RUB',per_person_or_package:'per_person_by_party_type',included_in_search_price:false,arithmetic_applied:true,final_price_verified:false,party_surcharge:{amount:anexZeroSurcharge?'0':'2000',currency:'RUB',source:'anex_b2b_additional_prices_daily'},search_price:{amount:'121000',currency:'RUB',source:'direct_anex_search'},search_plus_additional:{amount:anexZeroSurcharge?'121000':'123000',currency:'RUB',formula:'search_price_plus_program_date_party_additional'}};
   if(action==='offer')return {ok:true,data:{provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'current',selection_state:'disabled',finalPriceReady:state.anexCurrentAdditional,finalPrice:state.anexCurrentAdditional?additionalPrices.search_plus_additional?.amount:null,price:state.anexCurrentAdditional?additionalPrices.search_plus_additional?.amount:null,additional_prices:state.anexCurrentAdditional?additionalPrices:null,offer:{final_price_verified:false},context:{status:'current',current_context_verified:true,selection_state:'disabled'}}};
   if(action==='flights')return {ok:true,data:{provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'flights',selection_state:'disabled',flights:{provider:'anex',selected:false,final_price_verified:false,included_in_search_price_verified:false,truncated:false,routes:[{date:day,from:'Москва',to:'Анталья',options:[{name:'TEST ANEX 101',carrier:'Тестовая авиакомпания',transport_type:null,departure:{airport:'Шереметьево',airport_code:'SVO',time:'10:00'},arrival:{airport:'Анталья',airport_code:'AYT',time:'14:00'},classes:[{name:'Эконом',availability:'Y',baggage:'20 кг',hand_baggage:null}]}]},{date:back,from:'Анталья',to:'Москва',options:[{name:'Регулярный',carrier:null,transport_type:null,departure:{airport:null,airport_code:null,time:null},arrival:{airport:null,airport_code:null,time:null},classes:[]}]}]}}};
   if(action==='additional_prices')return {ok:true,data:{provider:'anex',generation:body.generation,search_ref:body.search_ref,offer_ref:body.offer_ref,status:'additional_prices',selection_state:'disabled',additional_prices:additionalPrices}};
   if(action!=='search')throw Error('Unexpected ANEX action '+action);
   const window=body.params.dateFrom+'|'+body.params.dateTo,initial=anexWindows.get(body.generation);
   if(initial&&initial!==window)return {ok:true,data:{generation:body.generation,provider:'anex',date_range:{from:body.params.dateFrom,to:body.params.dateTo},hotels:[],skipped:true,skip_reason:'initial_week_only',pages_read:0,first_page_only:true}};
   anexWindows.set(body.generation,window);
   return {ok:true,data:{generation:body.generation,provider:'anex',date_range:{from:body.params.dateFrom,to:body.params.dateTo},search_ref:searchRef,external_search_pending:false,pages_read:1,first_page_only:true,hotels:[h]}};
  }
  if(u.pathname.endsWith('/api-andromeda-search3-preview.php'))return {ok:true,data:{provider:'andromeda',generation:body.generation,date_range:{from:body.params.dateFrom,to:body.params.dateTo},grouped:true,first_page_only:false,page:1,pages_count:1,external_search_pending:false,search_ref:'c'.repeat(64),status:'complete',received_offers:1,mapped_offers:1,selection_enabled:false,hotels:[{local_id:101,mapping_status:'resolved',tours:[{provider:'andromeda',price:{amount:'119000',currency:'RUB'},checkin:day,nights:7,adults:2,children:0,meal:state.samoMeal,room:state.samoRoom,placement:'DBL',operator:{name:'FUN&SUN'},offer_ref:andromedaRef,offer_context:{provider:'andromeda',search_ref:'c'.repeat(64),generation:body.generation,page:1,offer_ref:andromedaRef},listing_price_ref:'listing_'+'e'.repeat(64),selection_enabled:false}]}]}};
  if(u.pathname.endsWith('/api-andromeda-quote-preview.php')){
   if(state.samoFailure&&(!state.samoFlightChoice||action==='quote_select_flights'))return {ok:false,error:'supplier_unavailable',failure_category:state.samoFailure};
   const result={ok:true,data:{schema_version:1,provider:'andromeda',expires_at:Math.floor(Date.now()/1000)+900,local_id:101,selection_enabled:true,booking_enabled:false,state:'quote_verified',quote_state:'verified',final_price:{amount:'125500',currency:'RUB'},final_price_verified:true,flight_selection_required:false,flights:[{direction:'0',name:'TEST SAMO OUT',datebeg:day,class:'ECONOM',departure:{town:'Москва',port:'SVO'},arrival:{town:'Анталья',port:'AYT'}},{direction:'1',name:'TEST SAMO BACK',datebeg:back,class:'ECONOM',departure:{town:'Анталья',port:'AYT'},arrival:{town:'Москва',port:'SVO'}}]}};
   if((state.samoFlightChoice||state.repricingEnabled)&&action==='quote'){
    const originals=result.data.flights;
    Object.assign(result.data,{state:'flight_selection_required',quote_state:'unverified',final_price:null,final_price_verified:false,flight_selection_required:true,
     flights:(state.repricingEnabled?[1,3,5,7,2]:[1,2]).map(n=>({...originals[n===2?1:0],...(state.repricingEnabled?{name:'TEST SAMO '+n}:{}),flight_ref:'flight_'+String(n).repeat(32),
      transport_markup_reported:{amount:'2000',currency:'RUB',source:'andromeda_transport_detail',aggregation:'unknown'}}))});
   }
   if(action==='quote_select_flights'&&state.samoVerifiedPair!=='legacy'){
    const pair=body.flight_selection;
    result.data.flights=result.data.flights.map(f=>({...f,flight_ref:state.samoVerifiedPair==='swapped'
      ?f.direction==='0'?pair.return_ref:pair.outbound_ref
      :state.samoVerifiedPair==='foreign'?'flight_'+(f.direction==='0'?'9':'8').repeat(32)
      :f.direction==='0'?pair.outbound_ref:pair.return_ref}));
   }
   result.data=reprice(result.data,'andromeda',body,action==='quote_select_flights'?body.flight_selection.outbound_ref+'|'+body.flight_selection.return_ref:null);
   return result;
  }
  if(u.pathname==='/api-v2.php'){
   if(action==='meals')return [{id:7,name:'AI'},{id:3,name:'BB'}];
   if(action==='search_start')return {searchId:123};
   if(action==='search_status')return {searchId:123,status:'complete',progress:100};
   if(action==='search_continue'){state.extended=true;return {requestCount:1};}
   if(action==='search_results')return [{id:101,provider:'tourvisor',tours:[tour,...(state.wideFacets?Array.from({length:8},(_,i)=>({...tour,id:'operator-tour-'+i,operator:{name:'Тестовый оператор '+(i+1)}})):[])]}];
   if(action==='tour')return {...tour,fuelCharge:tvFuel,hotel:{id:101,name:profile.name}};
   if(action==='flights')return flights.map(pair=>({...pair,fuelCharge:state.tvFlightFuel}));
  }
  throw Error('Forbidden fixture request: '+url);
 };
 return {json,calls,state};
}
module.exports={fixture,trip,day,back,tour,flights,profile,hotelCatalogue};
