'use strict';
const assert=require('node:assert/strict');
const withFuel=require('./fixtures/search3-andromeda-fuel.cjs');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../v2/andromeda-provider-v1.js'),'utf8');
const copy=value=>JSON.parse(JSON.stringify(value)),settle=()=>new Promise(resolve=>setImmediate(resolve));
const hex='a'.repeat(64),offer='offer_'+hex,reference='listing_'+'b'.repeat(64),laterReference='listing_'+'c'.repeat(64);
const context={provider:'andromeda',search_ref:hex,generation:7,page:1,offer_ref:offer};
const params={departureId:'1',countryId:'1',dateFrom:'2026-09-20',dateTo:'2026-09-20',nightsFrom:'7',nightsTo:'7',adults:'2',childs:[],currency:'RUB'};
const rawHotel={local_id:6319,name:'Локальный отель',provider:'andromeda',mapping_status:'resolved',country:'Египет',region:'Шарм-эль-Шейх',catalog:{hotel_id:6319,source:'tourvisor',image_url:'https://catalog.example/hotel.jpg'},tours:[withFuel({provider:'andromeda',offer_ref:offer,offer_context:context,listing_price_ref:reference,price:{amount:'119114',currency:'RUB'},checkin:'2026-09-20',nights:7,meal:'BB',room:'STANDARD',placement:'2 ADL',operator:'ANEX'})]};
const verified={schema_version:1,provider:'andromeda',local_id:6319,selection_enabled:true,booking_enabled:false,state:'quote_verified',quote_state:'verified',final_price:{amount:'135643.25',currency:'RUB'},final_price_verified:true,flight_selection_required:false,flights:[{direction:'0',name:'OUT 101',datebeg:'2026-09-20',class:'ECONOM',departure:{town:'Москва',port:'SVO'},arrival:{town:'Шарм-эль-Шейх',port:'SSH'}},{direction:'1',name:'BACK 102',datebeg:'2026-09-27',class:'ECONOM',departure:{town:'Шарм-эль-Шейх',port:'SSH'},arrival:{town:'Москва',port:'SVO'}}]};
const response=data=>({ok:true,status:200,json:async()=>({ok:true,data})});
async function harness(){
 const listeners=new Map(),requests=[],searches=[],lifecycle={generation:7,dirty:false,snapshot:copy(params)};
 const renderer={state:{items:[]},render(items){this.state.items=items;return items;}};
 const window={location:{href:'https://anytoour.ru/_preview/search3-site-candidate/poisk-turov/',origin:'https://anytoour.ru'},document:{},V2_CONFIG:{andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'},V2SearchLifecycle:lifecycle,V2Results:renderer,setTimeout,clearTimeout,
  addEventListener(name,fn){listeners.set(name,fn);},dispatchEvent(){},
  fetch:async(url,options)=>{const body=JSON.parse(options.body);if(body.action==='quote'){requests.push({url,options,body});return window.quoteResponse();}searches.push(body);return response({provider:'andromeda',generation:7,page:1,pages_count:1,hotels:[copy(rawHotel)]});},
  quoteResponse:async()=>response(copy(verified))};
 class Event{constructor(name,options){this.type=name;this.detail=options.detail;}}
 vm.runInNewContext(source,{window,URL,Map,Set,Array,Number,String,Object,RegExp,JSON,decodeURIComponent,AbortController,CustomEvent:Event,globalThis:window});
 listeners.get('v2:search-reset')({detail:{generation:7}});await settle();
 return{window,api:window.AnyTourAndromedaProvider,renderer,lifecycle,listeners,requests,searches};
}
(async()=>{
 const h=await harness(),{api}=h;
 assert.equal(h.searches.length,1);assert.equal(h.requests.length,0,'search/render does not actualize any offer');
 assert.equal(api.quoteEndpoint(h.window.V2_CONFIG.andromedaApi).href,'https://anytoour.ru/_preview/search3-anex-candidate/api-andromeda-quote-preview.php');
 for(const url of ['https://evil.example/api-andromeda-search3-preview.php','https://user:pass@anytoour.ru/api-andromeda-search3-preview.php','/api-andromeda-search3-preview.php?sid=secret','/api-andromeda-search3-preview.php#x'])assert.equal(api.quoteEndpoint(url),null);
 const selection=api.prepareQuote(offer);
 assert.equal(selection.tour.listingPriceRef,reference);
 assert.equal(Object.isFrozen(selection.tour),true,'captured conditions are immutable');
 assert.deepEqual(copy(api.quoteRequest(selection)),{action:'quote',generation:7,page:1,params,offer_context:context,listing_price_ref:reference});
 for(const invalid of [undefined,null,'','listing_short',{},123,'listing_'+'b'.repeat(65)]){
  const raw=copy(rawHotel);raw.tours[0].listing_price_ref=invalid;
  const tour=api.normalizeHotel(raw).tours[0];assert.equal(tour.listingPriceRef,null);
  assert.equal(Object.hasOwn(api.quoteRequest({...selection,tour}),'listing_price_ref'),false,'no invented reference or price substitute');
 }
 const scoped=copy(selection);scoped.tour.offerContext.hotel_scope={local_id:6319,seed:context};
 const body=copy(api.quoteRequest(scoped));
 assert.deepEqual(body.offer_context,context,'private SelectedOffer identity receives only its five public identity fields');
 assert.deepEqual(body.hotel_scope,scoped.tour.offerContext.hotel_scope,'HTTP criteria keep the original public hotel scope');
 assert.equal(body.listing_price_ref,reference);assert.equal(Object.hasOwn(body.offer_context,'listing_price_ref'),false);
 assert.deepEqual(scoped.tour.offerContext.hotel_scope,{local_id:6319,seed:context},'captured full context is unchanged');
 scoped.localId=6320;assert.equal(api.quoteRequest(scoped),null,'scope cannot be attached to another local hotel');
 assert.equal(api.quoteRequest({...selection,generation:8}),null,'search generation must match');
 const normalized=api.normalizeQuote(verified,6319);
 assert.equal(normalized.finalPrice.amount,'135643.25','decimal amount remains original');
 assert.equal(normalized.flights.length,2);
 for(const delta of [{local_id:6320},{provider:'tourvisor'},{schema_version:2},{selection_enabled:false},{booking_enabled:true},{quote_state:'unverified'},{final_price_verified:false},{flight_selection_required:true},{flights:[{direction:'2'}]},{final_price:{amount:'0',currency:'RUB'}},{final_price:{amount:'135643.25',currency:'USD'}},{final_price:{amount:'1e5',currency:'RUB'}}])assert.equal(api.normalizeQuote({...verified,...delta},6319),null,JSON.stringify(delta));
 const pending={...verified,state:'flight_selection_required',quote_state:'unverified',final_price:null,final_price_verified:false,flight_selection_required:true};
 assert.equal(api.normalizeQuote(pending,6319).finalPrice,null,'flight alternatives never become a final price');
 let release;h.window.quoteResponse=()=>new Promise(resolve=>{release=resolve;});
 const first=api.verifyQuote(offer),second=api.verifyQuote(offer);await settle();
 assert.equal(h.requests.length,1,'double click shares the pending quote');
 const visible=h.renderer.state.items[0].tours[0];visible.listingPriceRef=laterReference;visible.price=124864;
 assert.equal(h.requests[0].body.listing_price_ref,reference,'a later emitted price cannot replace the selected receipt');
 release(response(copy(verified)));
 const [a,b]=await Promise.all([first,second]);
 assert.equal(a,b);assert.equal(a.selection.tour.price,119114);assert.equal(a.selection.tour.listingPriceRef,reference);
 assert.equal(a.quote.finalPrice.amount,'135643.25');assert.equal(Object.isFrozen(a.quote),true);
 await api.verifyQuote(offer);assert.equal(h.requests.length,1,'completed quote is reused without replay');
 assert.equal(h.requests[0].options.credentials,'same-origin');assert.equal(h.requests[0].options.headers['X-Requested-With'],'AnyTourSearch3');
 assert.equal(h.requests[0].url,'https://anytoour.ru/_preview/search3-anex-candidate/api-andromeda-quote-preview.php');
 assert.equal(Object.hasOwn(h.requests[0].body,'price'),false);
 const foreign=await harness();foreign.renderer.state.items[0].tours[0].offerContext.search_ref='d'.repeat(64);
 assert.equal(foreign.api.prepareQuote(offer),null);await assert.rejects(foreign.api.verifyQuote(offer),/stale/);assert.equal(foreign.requests.length,0);
 const reassigned=await harness();reassigned.renderer.state.items[0].id='6320';assert.equal(reassigned.api.prepareQuote(offer),null);
 for(const kind of ['network','invalid','http','timeout']){
  const f=await harness();
  if(kind==='timeout')f.window.setTimeout=fn=>{fn();return 0;};
  f.window.quoteResponse=async()=>{if(kind==='network')throw new Error('network');if(kind==='http')return{ok:false,status:502,json:async()=>({ok:false})};return response(kind==='invalid'?{...verified,local_id:6320}:copy(verified));};
  await assert.rejects(f.api.verifyQuote(offer));await assert.rejects(f.api.verifyQuote(offer));
  assert.equal(f.requests.length,1,kind+' is terminal in this search, not an automatic supplier retry');
 }
 const stale=await harness();let finish;stale.window.quoteResponse=()=>new Promise(resolve=>{finish=resolve;});
 const old=stale.api.verifyQuote(offer);await settle();
 stale.lifecycle.dirty=true;stale.listeners.get('v2:search-reset')({detail:{dirty:true,generation:8}});
 assert.equal(stale.requests[0].options.signal.aborted,true);
 finish(response(copy(verified)));await assert.rejects(old,/stale/);assert.equal(stale.api.prepareQuote(offer),null);
 assert.doesNotMatch(source,/leadApi|action\s*:\s*['"]bron['"]|booking_enabled\s*[:=]\s*true/);
 console.log('SEARCH3_ANDROMEDA_SELECTED_QUOTE_OK retained_reference=1 scope=1 verified=1 flights=2 pending_cache=1 complete_cache=1 no_retry_unknown=1 reset=1 foreign_context=1 booking=0 lead=0');
})().catch(error=>{console.error(error);process.exitCode=1;});
