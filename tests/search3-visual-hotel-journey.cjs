'use strict';
// Execute the connected owners with deferred responses, without DOM emulation
// or supplier access. Browser geometry is covered by the existing compiled gate.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const app=fs.readFileSync(path.join(__dirname,'../v2/visual-search/app.js'),'utf8');
function slice(from,to){const a=app.indexOf(from),b=app.indexOf(to,a+from.length);assert(a>=0&&b>a,from);return app.slice(a,b);}
const owners=slice('function hotelOfferCheckActive(','async function openOffer(')
 +slice('function restoreProviderView(','const flightPairFor=')
 +slice('function leadReadyOffer(','function quotePriceChangeHTML(')
 +slice('function anexApplicationReceipt(','function openAnexApplicationPreview(')
 +slice('function openAnexConcreteCurrent(','function refreshHotel(');
function deferred(){let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};}
function setup(provider='tourvisor',kind='concrete'){
 const search={origin:'Москва',country:'4',adults:2,ages:[0,8]},raw={anexKind:kind,anexSessionCurrent:true,offerRef:'anex_online:'+'a'.repeat(64),searchRef:'b'.repeat(32),anexGeneration:1,anexLocalHotelId:1};
 const listing={key:'one',hotelId:1,provider,raw,search,total:198600,room:'Family',meal:'AI',day:'2027-04-18',nights:7,adults:2,ages:[0,8]};
 const hotel={id:1,offers:[listing]},calls={quote:0,flights:0,expand:0,verify:0,additional:0,contacts:0,offerScreens:0,lists:0,rooms:0},views=new Map();
 const nodes={'#modal':{open:true},'.hotel-section-nav':{dataset:{hotelId:'1'}},'#modal-footer':{hidden:false,innerHTML:''},'#hotel-detail-min':{textContent:''}};
 const c={console,Number,Set,Map,Promise,selectedOffer:{...listing},hotelOfferDraft:{hotelId:1,key:'one'},hotelDetailsView:{id:1,checkKey:'one'},modalType:'hotel-details',selectionGeneration:0,state:{search},anexCurrentDraft:null,anexApplicationDraft:null,anexFlightViews:new WeakMap(),hotels:[hotel],
  $:selector=>nodes[selector],data:{live:true,amount:v=>Number.isFinite(Number(v))&&Number(v)>0?Number(v):null,text:v=>String(v||''),meal:v=>v,quote:async()=>{calls.quote++;return {price:198600};},flights:async()=>{calls.flights++;throw Error('flight requests are optional');},verifyAnexConcrete:async()=>{calls.verify++;return {state:'current'};},verifyAnexAdditional:async()=>{calls.additional++;return {state:'additional_prices',finalPriceVerified:false,arithmeticApplied:true,searchPrice:{amount:198600},partySurcharge:{amount:700},calculatedTotal:{amount:199300,currency:'RUB'}};},expandAnexGroup:async()=>{calls.expand++;return {offers:[{...listing,key:'room',raw:{...raw,anexKind:'concrete'}}]};}},
  offerFromKey:key=>hotel.offers.find(o=>o.key===key),selectedTourHotel:()=>hotel,searchKey:s=>JSON.stringify(s),sameSelectedTourConditions:(a,b)=>a.room===b.room&&a.meal===b.meal&&a.day===b.day&&a.adults===b.adults&&JSON.stringify(a.ages)===JSON.stringify(b.ages),needsRefresh:o=>o.provider==='anex',flightPairFor:o=>o.variants?.[Number(o.flightChoiceId)]||null,
  retainedProviderView:o=>views.get(o?.key),rememberProviderView:(o,type,result,error='',pending=false)=>views.set(o.key,{offer:o,type,result,error,pending}),
  restoreProviderView:o=>{const v=views.get(o.key);if(!v)return false;c.selectedOffer=v.offer;if(v.type==='anex-current')c.openAnexConcreteCurrent(v.offer,v.result);if(v.type==='anex-additional')c.openAnexAdditionalEstimate(v.offer,v.result);return true;},
  renderRealOffer:()=>{calls.offerScreens++;c.modalType='offer';},syncHotelOfferSelection:()=>{},openLeadPreview:o=>{calls.contacts++;calls.contactOffer=o;c.modalType='selected-tour';},openOffer:()=>{calls.offerScreens++;},loadRealFlights:async()=>{calls.flights++;},openFlightPicker:()=>{calls.flights++;},
  terminalizeSearchForVerification:()=>{},renderResults:()=>{},refreshOpenHotelRooms:()=>calls.rooms++,positionHotelRooms:()=>{},openAllOffers:()=>calls.lists++,toast:()=>{},refreshHotel:()=>{},openAnexPackageQuote:()=>{},loadAnexPackageQuote:async()=>{},renderAnexFlightInventory:()=>{},rememberUIRoute:()=>{},
  anexApplicationReceipt:(o,result)=>({price:Number(result.calculatedTotal.amount),finalPriceVerified:false,priceKind:'estimate'}),countryNames:{'4':'Турция'},mealLabel:o=>o.meal,esc:v=>v,money:v=>String(v),roomLabel:o=>o.room,displayMealLabel:o=>o.meal,dateText:v=>v,nightsText:v=>v,guestsText:()=> '2 взрослых и 2 ребёнка',showModal:()=>{calls.offerScreens++;}
 };
 vm.createContext(c);vm.runInContext(owners,c);return {c,calls,listing,hotel,views,nodes};
}
async function run(){
 {const {c,calls}=setup();await c.openLeadWithQuote('one');assert.equal(calls.quote,1);assert.equal(calls.flights,0);assert.equal(calls.offerScreens,0);assert.equal(calls.contacts,1);assert.equal(calls.contactOffer.total,198600);}
 {const {c,calls,listing}=setup();const pair={price:201000};c.selectedOffer={...listing,tour:{price:198600},quoteListingTotal:198600,total:201000,variants:[pair],flightChoiceId:'0'};await c.openLeadWithQuote('one');assert.equal(calls.quote,0);assert.equal(calls.contacts,1);assert.equal(calls.contactOffer.total,201000,'reuse the exact selected pair total');}
 for(const mismatch of ['raw','search','room','party','price']){
  const {c,calls,listing}=setup();c.selectedOffer={...listing,tour:{price:1},quoteListingTotal:198600};
  if(mismatch==='raw')c.selectedOffer.raw={};if(mismatch==='search')c.selectedOffer.search={...listing.search,origin:'Казань'};if(mismatch==='room')c.selectedOffer.room='Standard';if(mismatch==='party')c.selectedOffer.ages=[8];if(mismatch==='price')c.selectedOffer.quoteListingTotal=111;
  await c.openLeadWithQuote('one');assert.equal(calls.quote,1,mismatch+' cannot borrow an earlier price');assert.equal(calls.contactOffer.total,198600);
 }
 for(const interrupt of ['close','new-selection','new-generation']){
  const {c,calls,nodes}=setup(),gate=deferred();c.data.quote=()=>{calls.quote++;return gate.promise;};
  const pending=c.openLeadWithQuote('one');await c.openLeadWithQuote('one');assert.equal(calls.quote,1,'double action shares the current pending intent');
  if(interrupt==='close'){c.cancelHotelOfferCheck();nodes['#modal'].open=false;}if(interrupt==='new-selection')c.hotelOfferDraft={hotelId:1,key:'other'};if(interrupt==='new-generation')c.selectionGeneration++;
  gate.resolve({price:199000});await pending;assert.equal(calls.contacts,0,'late quote after '+interrupt+' cannot navigate');if(interrupt==='close'){assert.equal(c.selectedOffer.loading,false);nodes['#modal'].open=true;c.data.quote=async()=>({price:199000});await c.openLeadWithQuote('one');assert.equal(calls.contacts,1,'explicit re-entry is usable after close');}
 }
 {const {c,calls}=setup();c.data.quote=async()=>{calls.quote++;throw Object.assign(Error('expired'),{code:'offer_expired',retryable:false});};await c.openLeadWithQuote('one');await c.openLeadWithQuote('one');assert.equal(calls.quote,1,'terminal quote is not replayed');assert.equal(calls.contacts,0);assert.equal(calls.offerScreens,0);assert.equal(c.selectedOffer.quoteErrorTerminal,true);}
 for(const outcome of ['success','expired','unknown']){
  const {c,calls,listing,hotel}=setup(),gate=deferred(),quoted={price:199000};let wire=0;
  hotel.offers.push({...listing,key:'two',room:'Other',raw:{...listing.raw}});
  if(outcome==='expired'){
   const canonical=fs.readFileSync(path.join(__dirname,'../v2/prototype-search/data.js'),'utf8');
   const take=(from,to)=>{const a=canonical.indexOf(from),b=canonical.indexOf(to,a+from.length);assert(a>=0&&b>a);return canonical.slice(a,b);};
   Object.assign(listing.raw,{id:'123',operator:'Biblio-Globus',selectionEnabled:true});
   Object.assign(c,{URL,URLSearchParams,AbortController,setTimeout,clearTimeout,location:{href:'https://example.invalid/preview/'},generation:1,searchId:123,quoteReceipts:new WeakMap(),amount:c.data.amount});
   c.window={V2_CONFIG:{api:'https://example.invalid/api.php'},fetch:()=>{wire++;return gate.promise;}};
   vm.runInContext(fs.readFileSync(path.join(__dirname,'../v2/runtime-v3.js'),'utf8'),c);c.rt=c.window.V2Runtime;
   vm.runInContext(take('function assertTourvisorRecalculationSupported(','const image =')+take('function tourvisorQuoteFailure(','async function flights('),c);
   c.data.quote=o=>{calls.quote++;return c.quote(o);};
  }else c.data.quote=()=>{calls.quote++;wire++;return calls.quote===1?gate.promise:Promise.resolve(quoted);};
  vm.runInContext("function selectRoom(key){const b={dataset:{key}};switch('select-hotel-offer'){"+slice("case 'select-hotel-offer':","case 'continue-hotel-offer':")+'}}',c);
  const pending=c.openLeadWithQuote('one');assert.equal(c.selectedOffer.loading,true);
  c.selectRoom('two');assert.equal(c.selectedOffer.loading,false,'changing room cancels only abandoned presentation loading');assert.equal(calls.quote,1,'room selection does not automatically quote');
  if(outcome==='success')gate.resolve(quoted);
  else if(outcome==='expired')gate.resolve({ok:false,status:410,json:async()=>({error:'expired'}),clone(){return this;}});
  else gate.reject(Object.assign(Error('uncertain result'),{code:'quote_unknown',retryable:false}));
  await pending;assert.equal(calls.contacts,0,'old A completion cannot navigate from B');
  c.selectRoom('one');assert.equal(c.hotelOfferCheckActive('one'),true);assert.equal(c.selectedOffer.loading,false,'reselected A is not permanently disabled');assert.equal(calls.quote,1);
  c.continueHotelOffer('one');await new Promise(resolve=>setImmediate(resolve));
  assert.strictEqual(c.selectedOffer.raw,listing.raw);
  if(outcome==='success'){assert.equal(wire,2,'explicit known-success recheck is not a retained-cache or HTTP saving claim');assert.equal(calls.quote,2);assert.equal(calls.contacts,1);assert.strictEqual(calls.contactOffer.tour,quoted);assert.equal(calls.contactOffer.total,199000);}
  else{assert.equal(wire,1,'inactive terminal result cannot replay');assert.equal(calls.quote,1);assert.equal(calls.contacts,0);assert.equal(c.selectedOffer.quoteErrorTerminal,true);if(outcome==='expired')assert.equal(c.selectedOffer.quoteErrorCode,'offer_expired');c.continueHotelOffer('one');await new Promise(resolve=>setImmediate(resolve));assert.equal(calls.quote,1,'terminal/UNKNOWN remains sealed without replay');}
 }
 for(const changed of ['raw','search','price','room']){
  const {c,calls,listing,hotel,views}=setup(),gate=deferred();c.data.quote=()=>{calls.quote++;return gate.promise;};
  const pending=c.openLeadWithQuote('one');c.selectionGeneration++;
  if(changed==='raw')hotel.offers[0]={...listing,raw:{...listing.raw}};
  if(changed==='search')c.state.search={...listing.search,origin:'Казань'};
  if(changed==='price')listing.total++;
  if(changed==='room')listing.room='Other';
  gate.reject(Object.assign(Error('expired old identity'),{code:'offer_expired',retryable:false}));await pending;
  assert.equal(views.size,0,'late terminal result cannot seal changed '+changed);assert.equal(calls.contacts,0);
 }
 {const {c,calls,listing,hotel}=setup('anex'),gate=deferred();let wire=0,request;
  hotel.offers.push({...listing,key:'two',room:'Other',raw:{...listing.raw}});
  c.data.verifyAnexConcrete=()=>{calls.verify++;if(!request){wire++;request=gate.promise;}return request;};
  vm.runInContext("function selectRoom(key){const b={dataset:{key}};switch('select-hotel-offer'){"+slice("case 'select-hotel-offer':","case 'continue-hotel-offer':")+'}}',c);
  const pending=c.refreshLiveHotel(1);c.selectRoom('two');assert.equal(c.selectedOffer.loading,false);
  gate.resolve({state:'current'});await pending;assert.equal(calls.additional,0,'abandoned ANEX context cannot start additional pricing');
  c.selectRoom('one');c.continueHotelOffer('one');await new Promise(resolve=>setImmediate(resolve));
  assert.equal(wire,1);assert.equal(calls.verify,1,'the retained ANEX context is restored without another current check');assert.equal(calls.additional,0);assert.equal(c.selectedOffer.loading,false);
  await c.applyAnexAdditionalPrices();assert.equal(calls.additional,1);assert.equal(c.anexApplicationDraft.price,199300);assert.equal(c.anexApplicationDraft.finalPriceVerified,false);assert.equal(calls.offerScreens,0);
 }
 {const {c,calls}=setup();c.data.quote=async()=>{calls.quote++;if(calls.quote===1)throw Error('temporary');return {price:199000};};await c.openLeadWithQuote('one');assert.equal(calls.contacts,0);await c.openLeadWithQuote('one');assert.equal(calls.contacts,1,'explicit retry recovers');assert.equal(calls.quote,2);}
 {const {c,calls,hotel}=setup('anex','group_minimum');await c.refreshLiveHotel(1);assert.equal(calls.expand,1);assert.equal(hotel.offers.length,1);assert.equal(hotel.offers[0].raw.anexKind,'concrete');assert.equal(calls.rooms,1);assert.equal(calls.lists,0,'expansion stays in the same hotel');assert.equal(calls.offerScreens,0);assert.equal(c.modalType,'hotel-details');}
 {const {c,calls,hotel,nodes}=setup('anex','group_minimum'),gate=deferred();c.data.expandAnexGroup=()=>{calls.expand++;return gate.promise;};const pending=c.refreshLiveHotel(1);nodes['#modal'].open=false;gate.resolve({offers:[]});await pending;assert.equal(hotel.offers.length,1);assert.equal(calls.rooms,0,'closed hotel is not replaced by a late expansion');}
 {const {c,calls}=setup('anex');await c.refreshLiveHotel(1);assert.equal(calls.verify,1);assert.equal(calls.additional,1);assert.equal(calls.offerScreens,0,'current/additional checks stay inline');assert.equal(calls.flights,0);assert.equal(c.anexApplicationDraft.price,199300);assert.equal(c.anexApplicationDraft.finalPriceVerified,false,'calculated estimate never becomes verified');assert.equal(calls.contacts,0,'changed estimate needs a deliberate continue');await c.refreshLiveHotel(1);assert.equal(calls.verify,1);assert.equal(calls.additional,1,'return reuses the retained result');}
 {const {c,calls,views,listing}=setup('anex');views.set('one',{offer:listing,type:'anex-current',result:{state:'current'},error:'UNKNOWN',pending:false});await c.refreshLiveHotel(1);assert.equal(calls.verify,0);assert.equal(calls.additional,0,'an UNKNOWN current result is never retried automatically');}
 console.log('PASS hotel journey: exact price/pair reuse, foreign raw/search/room/party/price rejection, double intent, late/closed selection, A-B-A canonical TV/runtime deferred410 noReplay, explicit success recheck, terminal/UNKNOWN and retained ANEX recovery, inline group/current/estimate and no mandatory flights; supplier/lead calls 0');
}
module.exports=run;if(require.main===module)run().catch(error=>{console.error(error);process.exitCode=1;});
