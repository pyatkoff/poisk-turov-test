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
(async()=>{
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
