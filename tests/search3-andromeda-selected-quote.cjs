'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

const source=fs.readFileSync(path.join(__dirname,'../v2/search3-selected-flow-v2.js'),'utf8');
const hex='a'.repeat(64),offer='offer_'+hex;
const offerContext={provider:'andromeda',search_ref:hex,generation:7,page:1,offer_ref:offer};
const snapshot={departureId:'1',countryId:'1',dateFrom:'2026-09-20',dateTo:'2026-09-20',nightsFrom:'7',nightsTo:'7',adults:'2',childs:[],currency:'RUB'};
const tour={provider:'andromeda',offerRef:offer,offerContext,providerDetail:{status:'complete',open:true}};
const listeners=new Map(),requests=[];
const document={readyState:'complete',querySelectorAll(){return[];},addEventListener(){},createElement(){throw new Error('not needed in pure test');},getElementById(){return null;}};
const window={
  __ANYTOOUR_SEARCH3_ENTRY__:'AnyTourSearch3',AnyTourSearch3:{modules:{}},
  location:{href:'https://anytoour.ru/_preview/search3-site-candidate/poisk-turov/',origin:'https://anytoour.ru'},
  V2_CONFIG:{andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'},
  V2SearchLifecycle:{generation:7,dirty:false,snapshot},
  V2Results:{state:{items:[{id:6319,tours:[tour]}]}},
  AnyTourAndromedaProvider:{context(value){return value&&value.provider==='andromeda'&&value.offer_ref===offer?JSON.parse(JSON.stringify(value)):null;}},
  addEventListener(name,listener){listeners.set(name,listener);},
  requestAnimationFrame(fn){fn();},setTimeout,clearTimeout,
  async fetch(url,options){
    requests.push({url,options,body:JSON.parse(options.body)});
    return{ok:true,status:200,async json(){return{ok:true,data:{provider:'andromeda',state:'quote_verified',quote_state:'verified',search_price:{amount:'119114',currency:'RUB'},package_price:{amount:'124864',currency:'RUB'},final_price:{amount:'135643',currency:'RUB'},final_price_verified:true,flight_selection_required:false,booking_enabled:false,flights:[{direction:'0',name:'OUT 101',datebeg:'2026-09-20',class:'ECONOM',departure:{town:'Москва',port:'SVO'},arrival:{town:'Шарм-эль-Шейх',port:'SSH'}},{direction:'1',name:'BACK 102',datebeg:'2026-09-27',class:'ECONOM',departure:{town:'Шарм-эль-Шейх',port:'SSH'},arrival:{town:'Москва',port:'SVO'}}]}};}};
  }
};
vm.runInNewContext(source,{window,document,URL,Intl,Map,Set,Array,Number,String,Object,RegExp,JSON,AbortController,setTimeout,clearTimeout});
const api=window.AnyTourSearch3.modules['search3-selected-flow-v2'];
assert.equal(api.version,2);
assert.equal(api.quoteEndpoint().href,'https://anytoour.ru/_preview/search3-anex-candidate/api-andromeda-quote-preview.php');
assert.equal(api.quoteEndpoint('https://evil.example/api-andromeda-search3-preview.php'),null,'quote endpoint remains same-origin');
assert.equal(api.findTour(offer).tour,tour);
assert.deepEqual(JSON.parse(JSON.stringify(api.requestBody(api.findTour(offer)))),{action:'quote',generation:7,page:1,params:snapshot,offer_context:offerContext});
const verified=api.normalizeQuote({provider:'andromeda',state:'quote_verified',final_price:{amount:'135643',currency:'RUB'},final_price_verified:true,flight_selection_required:false,booking_enabled:false,flights:[]});
assert.equal(verified.finalPrice.amount,135643);
assert.equal(api.normalizeQuote({provider:'andromeda',state:'quote_verified',final_price:{amount:'1',currency:'RUB'},final_price_verified:true,flight_selection_required:false,booking_enabled:true}),null,'booking-enabled response is rejected');
const ambiguous=api.normalizeQuote({provider:'andromeda',state:'flight_selection_required',final_price:null,final_price_verified:false,flight_selection_required:true,booking_enabled:false,flights:[{direction:'0',name:'A'}]});
assert.equal(ambiguous.state,'flight_selection_required');
assert.equal(ambiguous.finalPrice,null);

(async()=>{
  const data=await api.verifyQuote(offer);
  assert.equal(data.state,'quote_verified');
  assert.equal(data.finalPrice.amount,135643);
  assert.equal(data.flights.length,2);
  assert.equal(requests.length,1,'verified quote makes one browser endpoint request');
  assert.equal(requests[0].url,'https://anytoour.ru/_preview/search3-anex-candidate/api-andromeda-quote-preview.php');
  assert.equal(requests[0].options.method,'POST');
  assert.equal(requests[0].options.credentials,'same-origin');
  assert.equal(requests[0].options.headers['X-Requested-With'],'AnyTourSearch3');
  assert.equal(requests[0].body.action,'quote');
  assert.deepEqual(requests[0].body.params,snapshot);
  assert.deepEqual(requests[0].body.offer_context,offerContext);
  await api.verifyQuote(offer);
  assert.equal(requests.length,1,'completed quote is cached and not replayed');
  assert.equal(api.states.get(offer).status,'complete');
  listeners.get('v2:search-reset')();
  assert.equal(api.states.size,0,'new search clears retained quote state');
  assert.doesNotMatch(source,/data-direct-tour|leadApi|action\s*:\s*['"]bron['"]|booking_enabled\s*[:=]\s*true/,'selected quote UI contains no booking/lead path');
  console.log('SEARCH3_ANDROMEDA_SELECTED_QUOTE_OK endpoint=1 verified=1 flights=2 cache=1 reset=1 booking=0 lead=0');
})().catch(error=>{console.error(error);process.exitCode=1;});
