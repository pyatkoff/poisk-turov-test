'use strict';
// Real binder + controller, intercepted transport; never reaches CRM/suppliers.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const root=path.resolve(__dirname,'../v2');
function fixture(preview=false){
 let mounted=null,response={ok:true,writes:true,leadId:123},gate=null;const requests=[],events=[];
 const document={cookie:'',body:{classList:{contains:()=>false}},getElementById:id=>id==='prototype-lead-form'?mounted?.form:null,querySelector:s=>s.includes('submit')?mounted?.button:null,addEventListener(){}};
 class FormData{constructor(form){this.f=form;}get(k){const el=this.f.elements[k];return k==='consent'&&!el.checked?null:el.value;}}
 const window={location:{href:'https://example.test/visual-search/'},V2_CONFIG:{leadApi:preview?'../preview-lead-disabled.php':'/lead-adapter-v2.php'},V2Runtime:{state:{searchId:1}},V2LeadFormGuard:{validatePhone:p=>/\d{10}/.test(p.value)},addEventListener(){},dispatchEvent:e=>events.push(e),
 fetch:async(url,options)=>{requests.push({url,options,payload:JSON.parse(options.body)});if(gate)await gate;if(response instanceof Error)throw response;return{ok:true,json:async()=>response};}};
 const context=vm.createContext({window,document,location:window.location,URL,URLSearchParams,FormData,structuredClone,CustomEvent:class{constructor(type,{detail}){this.type=type;this.detail=detail;}},Date});
 for(const file of ['tour-controller-v4.js','prototype-search/lead.js'])vm.runInContext(fs.readFileSync(path.join(root,file),'utf8'),context);
 function mount(receipt){
  if(mounted)mounted.form.isConnected=false;
  const listeners={},message={textContent:'',setAttribute(k,v){this[k]=v;},scrollIntoView(){}},button={disabled:false};
  const form={isConnected:true,elements:Object.fromEntries(['name','phone','comment','consent'].map(k=>[k,{value:k==='consent'?'1':'',checked:false}])),dataset:{},querySelector:()=>message,addEventListener:(type,fn)=>listeners[type]=fn,reportValidity(){return this.elements.consent.checked;}};
  mounted={form,message,button,fire:type=>listeners[type]({preventDefault(){}})};
  window.AnyTourPrototypeLead.bindProviderApplication(receipt);
  form.elements.phone.value='79990000000';form.elements.consent.checked=true;
  return mounted;
 }
 return{api:window.AnyTourPrototypeLead,mount,requests,events,setResponse:r=>response=r,setGate:g=>gate=g};
}
const receipt=provider=>({provider,offerRef:provider==='anex'?'anex_online:'+'a'.repeat(64):'offer_'+'b'.repeat(64),searchRef:'c'.repeat(32),generation:1,localHotelId:22,choiceRef:'anex_quote:'+'d'.repeat(64),expiresAt:Math.floor(Date.now()/1000)+600,priceKind:'verified',finalPriceVerified:true,price:101069.5,currency:'RUB',departure:'Москва',hotel:'Точный отель',country:'Турция',resort:'Анталья',day:'2026-10-13',nights:7,adults:2,ages:[4,12],room:'Family',meal:'AI',operator:provider==='anex'?'ANEX':'FUN&SUN',flights:[{direction:'0',name:'S7 3749',datebeg:'2026-10-13 12:00',class:'Economy',departure:{town:'Москва',port:'DME'},arrival:{town:'Анталья',port:'AYT'}},{direction:'1',name:'S7 3750',datebeg:'2026-10-20 14:00'}]});
// Exercise the actual visual-search receipt constructors, not parallel hand-built
// receipts. Rendering is stubbed: these are supplier-free handoff tests, not a
// browser/device or fresh-supplier acceptance run.
function visualOwner(){
 const source=fs.readFileSync(path.join(root,'visual-search/app.js'),'utf8');
 const context=vm.createContext({Date,structuredClone,state:{search:{origin:'Москва'}},countryNames:{4:'Турция'},
  mealLabel:o=>o.meal,selectedTourHotel:o=>o.testHotel,rememberProviderView(){},retainedProviderView:()=>null,
  showModal(){},$:()=>({}),esc:String,dateText:String,nightsText:String,guestsText:()=>'',money:String,
  anexApplicationDraft:null,selectedOffer:null});
 for(const name of ['andromedaQuoteCurrent','andromedaApplicationReceipt','anexApplicationReceipt','openAnexPackageQuote']){
  const marker='function '+name+'(',start=source.indexOf(marker);
  assert(start>=0&&source.indexOf(marker,start+marker.length)===-1,'one real visual owner for '+name);
  const next=source.slice(start+marker.length).search(/^\s*(?:async )?function /m);
  assert(next>=0,'next function boundary for '+name);
  vm.runInContext(source.slice(start,start+marker.length+next),context,{filename:'visual-search/app.js:'+name});
 }
 return context;
}
async function visualHandoff(){
 const ui=visualOwner(),hotel={id:22,name:'Выбранный отель',country:4,resort:'Белек'};
 const selected={provider:'andromeda',origin:'Казань',day:'2026-11-14',nights:9,adults:2,ages:[6],
  room:'Selected family room',meal:'Всё включено',operator:'FUN&SUN',price:88000,testHotel:hotel,
  raw:{offerRef:'offer_'+'e'.repeat(64)}};
 const quote={state:'quote_verified',finalPriceVerified:true,flightSelectionRequired:false,
  expiresAt:Math.floor(Date.now()/1000)+600,finalPrice:{amount:137246.75,currency:'RUB'},
  flights:[{direction:'0',name:'SELECTED OUT',departure:{town:'Казань',port:'KZN'},arrival:{town:'Анталья',port:'AYT'}},
   {direction:'1',name:'SELECTED BACK'}]};
 const samo=ui.andromedaApplicationReceipt(selected,quote,hotel);
 assert(samo,'actual visual SAMO receipt is constructible');
 const anexOffer={...selected,provider:'anex',operator:'ANEX',price:77000,
  raw:{anexKind:'concrete',anexSessionCurrent:true,offerRef:'anex_online:'+'f'.repeat(64),searchRef:'a'.repeat(32),anexGeneration:3,anexLocalHotelId:22}};
 const anexQuote={...quote,finalPrice:{amount:156789.25,currency:'RUB'},choice:{choiceRef:'anex_quote:'+'b'.repeat(64),
  legs:[{label:'ANEX SELECTED OUT'},{label:'ANEX SELECTED BACK'}]}};
 ui.openAnexPackageQuote(anexOffer,anexQuote);
 const anex=ui.anexApplicationDraft;assert(anex,'actual visual ANEX receipt is constructible');
 for(const [r,offer,expectedPrice,out,back] of [[samo,selected,137246.75,'SELECTED OUT','SELECTED BACK'],[anex,anexOffer,156789.25,'ANEX SELECTED OUT','ANEX SELECTED BACK']]){
  const f=fixture(),m=f.mount(r);m.form.elements.name.value='Тест';await m.fire('submit');
  assert.equal(f.requests.length,1,'actual receipt reaches exactly one intercepted controller transport');
  const p=f.requests[0].payload;
  assert.equal(p.provider,offer.provider);assert.equal(p.operator,offer.operator);
  assert.equal(p.providerOfferRef,offer.raw.offerRef);assert.equal(p.tourId,offer.provider+':'+offer.raw.offerRef);
  assert.equal(p.price,expectedPrice);assert.equal(p.flightPrice,expectedPrice);
  assert.notEqual(p.price,offer.price,'quote total, not old search-card price, is handed off');
  assert.equal(p.flightFuel,null,'unknown fuel is not zero and is not added again');
  assert.equal(p.hotel,hotel.name);assert.equal(p.country,'Турция');assert.equal(p.region,hotel.resort);
  assert.equal(p.departure,'Казань');assert.equal(p.date,offer.day);assert.equal(p.nights,9);
  assert.equal(p.adults,2);assert.deepEqual(p.childAges,[6]);assert.equal(p.roomType,offer.room);assert.equal(p.meal,offer.meal);
  assert.equal(p.currency,'RUB');assert.equal(p.priceKind,'verified');assert.equal(p.finalPriceVerified,true);
  assert.equal(p.providerQuoteExpiresAt,quote.expiresAt);assert(p.flight.includes(out));assert(p.flight.includes(back));
  if(offer.provider==='anex')assert.equal(p.providerChoiceRef,anexQuote.choice.choiceRef);
  const pv=fixture(true),pm=pv.mount(r);await pm.fire('submit');assert.equal(pm.form.dataset.checked,'1');assert.equal(pv.requests.length,0);
 }
 // The selected receipt is a snapshot, not an alias of the result/quote arrays.
 selected.ages[0]=15;quote.flights[0].name='OTHER FLIGHT';quote.flights[0].departure.town='Другой город';
 assert.equal(samo.ages[0],6);assert.equal(samo.flights[0].name,'SELECTED OUT');assert.equal(samo.flights[0].departure.town,'Казань');
 for(const patch of [{finalPriceVerified:false},{flightSelectionRequired:true},{expiresAt:1},
  {finalPrice:{amount:0,currency:'RUB'}},{finalPrice:{amount:137246.75,currency:'EUR'}}]){
  assert.equal(ui.andromedaApplicationReceipt(selected,{...quote,...patch},hotel),null,'unconfirmed/expired/non-RUB quote cannot become a visual verified receipt');
 }
 ui.openAnexPackageQuote(anexOffer,{...anexQuote,expiresAt:1});assert.equal(ui.anexApplicationDraft,null,'expired ANEX quote clears the previous receipt');
 // Preview validation is deliberately NOT proof of deliverability. Preserve the
 // protected sender guard; a manager-inquiry contract is a separate owner decision.
 const estimate=ui.anexApplicationReceipt(anexOffer,{state:'additional_prices',finalPriceVerified:false,arithmeticApplied:true,
  calculatedTotal:{amount:119345.5,currency:'RUB'}},hotel);
 assert(estimate);assert.equal(estimate.priceKind,'estimate');assert.equal(estimate.finalPriceVerified,false);
 const noFlights=ui.andromedaApplicationReceipt(selected,{...quote,flights:[]},hotel);assert(noFlights);
 for(const r of [estimate,noFlights]){
  const pv=fixture(true),pm=pv.mount(r);await pm.fire('submit');assert.equal(pm.form.dataset.checked,'1');assert.equal(pv.requests.length,0);
  const f=fixture(),m=f.mount(r);assert.equal(m.button.disabled,true);await m.fire('submit');assert.equal(f.requests.length,0);
  assert.match(m.message.textContent,/подтвердите итоговую цену и рейсы/);
 }
 const estimatePreview=fixture(true),fd=new Map([['name','Тест'],['phone','79990000000'],['consent','1']]);
 const p=estimatePreview.api.providerPreviewPayload(estimate,fd);
 assert.equal(p.price,119345.5);assert.equal(p.finalPriceVerified,false);assert.equal(p.priceKind,'estimate');assert.equal(p.flight,'');
 console.log('Visual selected-offer handoff: actual SAMO/ANEX constructors → binder → intercepted controller; identity, quote total, flights, expiry, preview/delivery boundary PASS');
}
(async()=>{
 await visualHandoff();
 for(const provider of ['andromeda','anex']){
  const f=fixture(),r=receipt(provider),form=f.mount(r);let release;f.setGate(new Promise(resolve=>release=resolve));
  const first=form.fire('submit');await form.fire('submit');assert.equal(f.requests.length,1,'double click sends once');release();await first;
  const p=f.requests[0].payload;assert.equal(p.provider,provider);assert.equal(p.providerOfferRef,r.offerRef);assert.equal(p.tourId,provider+':'+r.offerRef);assert.equal(p.flightPrice,101069.5);assert.equal(p.price,101069.5);assert.equal(p.flightFuel,null);assert.deepEqual(p.childAges,[4,12]);assert.equal(p.departure,'Москва');assert.equal(p.roomType,'Family');assert.match(p.flight,/S7 3749/);assert.match(p.flight,/S7 3750/);assert.equal(p.providerFlights.length,2);
  assert.equal(f.requests[0].url,'/lead-adapter-v2.php');assert.equal(f.requests[0].options.credentials,'same-origin');assert.equal(form.form.dataset.sent,'1');await form.fire('submit');assert.equal(f.requests.length,1);assert.match(form.message.textContent,/123/);
  for(const reply of [{ok:true,writes:false},{ok:true,writes:true,leadId:0},new Error('offline')]){
   const x=fixture(),m=x.mount(r);x.setResponse(reply);await m.fire('submit');assert.equal(m.form.dataset.sent,undefined);assert.equal(m.button.disabled,false);assert.match(m.message.textContent,/Не удалось/);
   x.setResponse({ok:true,writes:false,duplicate:true,leadId:456});await m.fire('submit');assert.equal(m.form.dataset.sent,'1');assert.match(m.message.textContent,/456/);assert.equal(x.requests.length,2);
  }
  for(const invalid of [{expiresAt:1},{flights:[{direction:'0',name:'only one'}]},{priceKind:'estimate',finalPriceVerified:false},{departure:''}]){
   const x=fixture(),m=x.mount({...r,...invalid});assert.equal(m.button.disabled,true);await m.fire('submit');assert.equal(x.requests.length,0);
  }
  const stale=fixture(),old=stale.mount(r);stale.api.reset();await old.fire('submit');assert.equal(stale.requests.length,0);assert.match(old.message.textContent,/изменились/);
  const detached=fixture(),previous=detached.mount(r);detached.mount(r);await previous.fire('submit');assert.equal(detached.requests.length,0);
  const pv=fixture(true),m=pv.mount(r);await m.fire('submit');assert.equal(m.form.dataset.checked,'1');assert.equal(pv.requests.length,0);
 }
 const expires=fixture(),r=receipt('anex');r.expiresAt=Math.floor(Date.now()/1000)+1;const m=expires.mount(r);
 const originalNow=Date.now;try{Date.now=()=>r.expiresAt*1000+1;await m.fire('submit');assert.equal(expires.requests.length,0);}finally{Date.now=originalNow;}
 const long=fixture(),lr=receipt('anex');lr.flights[0].name='leg '.repeat(700);assert.equal(long.mount(lr).button.disabled,true);
 console.log('Provider delivery: exact SAMO/ANEX mapping, one transport, duplicate/success/failure/retry, expiry/reset/rebind, preview no-write PASS');
})().catch(e=>{console.error(e);process.exitCode=1;});
