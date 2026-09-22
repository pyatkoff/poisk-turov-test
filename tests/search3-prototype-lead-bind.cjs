'use strict';
// Unit event harness for the real binder; this is not browser or supplier acceptance.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/lead.js'),'utf8');
const values={name:'Тестовый турист',phone:'+7 999 000-00-00',comment:'Пожелания, введённые после ошибки рейсов'};
function fixture(preview){
 let current=null,sessionError=null,payloadError=null,phoneValid=true,submitResult=false;
 const payloads=[],submissions=[],receivedOffers=[];
 class FormDataFixture{
  constructor(form){this.fields=new Map(Object.entries(form.elements).filter(([name,el])=>name!=='consent'||el.checked).map(([name,el])=>[name,el.value]));}
  get(name){return this.fields.get(name)||null;}
 }
 const window={location:{href:'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'},V2_CONFIG:{leadApi:preview?'/_preview/search3-local-candidate/preview-lead-disabled.php':'/lead-adapter-v2.php'},
  AnyTourPrototypeData:{leadSession(offer){receivedOffers.push(offer);if(sessionError)throw sessionError;return{
   payload(formData){if(payloadError)throw payloadError;payloads.push(formData);return{flight:offer.flight};},
   async submit(form,options){submissions.push({form,options});return submitResult;}
  };}},V2LeadFormGuard:{validatePhone(){return phoneValid;}}};
 const document={getElementById(){return current?.form||null;},querySelector(){return current?.button||null;}};
 const sandbox=vm.createContext({window,document,URL,FormData:FormDataFixture});
 vm.runInContext(source,sandbox);
 function mount(){
  const listeners=new Map(),button={disabled:false},message={textContent:'',role:'status',scrolls:0,setAttribute(name,value){this[name]=value;},scrollIntoView(){this.scrolls++;}};
  const elements=Object.fromEntries(['name','phone','comment','consent'].map(name=>[name,{value:name==='consent'?'1':'',checked:false,reportValidity(){return phoneValid;}}]));
  const form={elements,dataset:{},querySelector(){return message;},addEventListener(type,fn){const list=listeners.get(type)||[];list.push(fn);listeners.set(type,list);},reportValidity(){return elements.consent.checked;}};
  async function fire(type){let prevented=false;for(const fn of listeners.get(type)||[])await fn({preventDefault(){prevented=true;}});return prevented;}
  current={form,button,message,fire};return current;
 }
 return{api:window.AnyTourPrototypeLead,mount,payloads,submissions,receivedOffers,window,document,sandbox,FormDataFixture,
  setSessionError(value){sessionError=value;},setPayloadError(value){payloadError=value;},setPhoneValid(value){phoneValid=value;},setSubmitResult(value){submitResult=value;}};
}
async function lateSubmissionDrafts(){
 const newer={name:'Другой тестовый турист',phone:'+7 999 000-00-02',comment:'Новый черновик после начала отправки'};
 for(const scenario of ['new-form','same-form-input','reopen-only','reset-new-form','reset-only','late-failure','late-rejection','current-success']){
  const f=fixture(false),offer={flight:{price:{value:133500.5}}};
  const first=f.mount();f.api.bind(offer);
  for(const [name,value]of Object.entries(values))first.form.elements[name].value=value;
  await first.fire('input');first.form.elements.consent.checked=true;
  let resolve,reject;f.setSubmitResult(new Promise((yes,no)=>{resolve=yes;reject=no;}));
  const pending=first.fire('submit');
  assert.equal(f.submissions.length,1,'Only the existing delivery STUB starts');
  let active=first,expected=values;
  if(scenario.startsWith('reset-')){f.api.reset();expected={name:'',phone:'',comment:''};}
  if(['new-form','reopen-only','reset-new-form','late-failure','late-rejection'].includes(scenario)){
   active=f.mount();f.api.bind({flight:{price:{value:140000}}});
  }
  if(['new-form','same-form-input','reset-new-form','late-failure','late-rejection'].includes(scenario)){
   for(const [name,value]of Object.entries(newer))active.form.elements[name].value=value;
   await active.fire('input');expected=newer;
  }
  if(scenario==='late-rejection')reject(new Error('Предыдущая отправка не завершена'));
  else resolve(scenario!=='late-failure');
  await pending;
  if(scenario==='current-success')expected={name:'',phone:'',comment:''};
  if(scenario==='late-rejection')assert.equal(active.message.textContent,'','An older error does not target the new form');
  const restored=f.mount();f.api.bind(offer);
  for(const [name,value]of Object.entries(expected))assert.equal(restored.form.elements[name].value,value,scenario+': late completion must not erase a newer draft');
  assert.equal(restored.form.elements.consent.checked,false,'Draft retention never restores consent');
  assert.equal(f.submissions.length,1);assert.equal(f.payloads.length,1);
  assert.equal(f.submissions[0].form,first.form);assert.equal(f.submissions[0].options.button,first.button);
 }
 console.log('8 late-completion draft cases PASS: new form, ongoing edit, reopen, reset/new draft, reset-only, failure, rejection and current success; delivery is stubbed');
}
async function nativeRecovery(){
 const f=fixture(true),{window,document,sandbox,FormDataFixture}=f;
 const calls=[],requests=[],flight={price:{value:133500.5},fuelCharge:0,forward:[{number:'TT 211'}],backward:[{number:'TT 212'}]};
 const tour={id:'exact-second-room',provider:'tourvisor',price:120000,hotel:{name:'Тестовый отель'},date:'2026-10-01',nights:7,adults:2,childs:2,meal:{name:'AI'},roomType:'FAMILY SEA VIEW',placement:'DBL+2CH',operator:{name:'ANEX'}};
 let flightReply={},nextSearch=321;
 window.location.search='';window.addEventListener=()=>{};
 document.cookie='';document.addEventListener=()=>{};
 const query=document.querySelector;document.querySelector=selector=>selector==='#tourSearch input[name="sessid"]'?null:query();
 window.fetch=async(url,options)=>{requests.push({url,options});assert.match(String(url),/\/data\/search3-local-results-read-v1\.php$/,'Only the fictional LOCAL read is permitted');return{ok:false,status:503};};
 window.V2Runtime={state:{searchId:0},setSearchId(id){this.state.searchId=id;},async api(action,params){
  calls.push({action,params});
  if(action==='search_start')return{searchId:nextSearch++};
  if(action==='tour')return structuredClone(tour);
  if(action==='flights')return structuredClone(flightReply);
  throw Error('Unexpected fixture action '+action);
 }};
 window.Search3CanonicalProfilesV1={create:()=>({reset(){},read:()=>[]})};
 Object.assign(sandbox,{location:window.location,URLSearchParams,structuredClone,fetch:(...args)=>window.fetch(...args),setTimeout:()=>0,clearTimeout(){}});
 for(const file of ['lead-search-context.js','tour-controller-v4.js','prototype-search/data.js'])vm.runInContext(fs.readFileSync(path.resolve(__dirname,'../v2',file),'utf8'),sandbox,{filename:file});
 const data=window.AnyTourPrototypeData;data.catalog.departures.push({id:1,name:'Москва'});data.catalog.countries.push({id:4,name:'Турция'});
 const trip={origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-01',minNights:7,maxNights:7,adults:2,ages:[17,0]};
 await data.search(trip,()=>{});
 const offer={raw:{id:tour.id},provider:'tourvisor',cached:false,flightChoiceId:null};offer.tour=await data.quote(offer);
 await assert.rejects(data.flights(offer.tour),/рейс/,'The real adapter rejects malformed flights');
 offer.flightsError='Не удалось загрузить рейсы.';
 const blocked=f.mount();f.api.bind(offer);
 assert.equal(blocked.button.disabled,true);assert.match(blocked.message.textContent,/рейс/);
 for(const [name,value]of Object.entries(values))blocked.form.elements[name].value=value;
 await blocked.fire('input');
 const beforeBlocked=[calls.length,requests.length];await blocked.fire('submit');assert.deepEqual([calls.length,requests.length],beforeBlocked);
 flightReply=[flight];const beforeRetry=calls.length;offer.variants=await data.flights(offer.tour);offer.flightsError='';offer.flightChoiceId='0';
 assert.deepEqual(calls.slice(beforeRetry).map(c=>c.action),['flights'],'Recovery repeats flights only, not the search or exact-tour quote');
 const recovered=f.mount();f.api.bind(offer);
 for(const [name,value]of Object.entries(values))assert.equal(recovered.form.elements[name].value,value,'Real controller handoff retains contacts after failure');
 assert.equal(recovered.form.elements.consent.checked,false);assert.equal(recovered.button.disabled,false);
 recovered.form.elements.consent.checked=true;
 const session=data.leadSession(offer),payload=session.payload(new FormDataFixture(recovered.form));
 assert.deepEqual([payload.tourId,payload.searchId,payload.roomType,payload.meal,payload.placement,payload.date,payload.nights],['exact-second-room',321,'FAMILY SEA VIEW','AI','DBL+2CH','2026-10-01',7]);
 assert.equal(payload.price,120000,'The original base-price field is not rewritten');assert.equal(payload.flightPrice,133500.5,'The selected whole-tour flight price is not added to the base or rounded');
 assert.match(payload.flight,/TT 211/);assert.match(payload.flight,/TT 212/);
 assert.deepEqual(Array.from(payload.childAges),[0,17]);assert.equal(payload.childs,2);assert.equal(payload.adults,2);
 for(const [name,value]of Object.entries(values))assert.equal(payload[name],value);
 const beforeValidation=[calls.length,requests.length];await recovered.fire('submit');
 assert.equal(recovered.form.dataset.checked,'1');assert.match(recovered.message.textContent,/не отправлена/);assert.deepEqual([calls.length,requests.length],beforeValidation,'Real preview validation does not call HTTP or delivery');
 const quoteCalls=calls.length;await assert.rejects(data.quote({...offer,cached:true}),/обновите/);assert.equal(calls.length,quoteCalls,'Cached listings cannot obtain authority from contact recovery');
 flightReply=[];offer.variants=await data.flights(offer.tour);offer.flightChoiceId=null;
 assert.equal(data.leadSession(offer).payload(new FormDataFixture(recovered.form)).flight,'','Genuine empty flights keep the original fallback');
 data.stop();assert.throws(()=>session.payload(new FormDataFixture(recovered.form)),/изменились/);
 const beforeStale=[calls.length,requests.length];await recovered.fire('submit');assert.equal(recovered.message.role,'alert');assert.match(recovered.message.textContent,/изменились/);assert.deepEqual([calls.length,requests.length],beforeStale);
 console.log('Native data adapter + tour controller + search-context + binder: exact room/flight/price/child ages, failed-to-valid recovery, cached/stale refusal and preview no-delivery PASS');
}
(async()=>{
 for(const preview of [true,false]){
  const f=fixture(preview),offer={flight:{price:{value:133500.5}}};
  // Missing modal is harmless; mounting an unverified offer must still bind the form events.
  f.api.bind(offer);
  for(const errorText of ['Не удалось загрузить рейсы. Повторите загрузку перед выбором тура.','Дождитесь загрузки рейсов.','Предложение устарело.']){
   f.api.reset();f.setSessionError(new Error(errorText));
   const rejected=f.mount();f.api.bind(offer);
   assert.equal(rejected.button.disabled,true);assert.equal(rejected.message.role,'alert');
   assert.equal(rejected.message.textContent,errorText);
   for(const [name,value]of Object.entries(values))rejected.form.elements[name].value=value;
   rejected.form.elements.consent.checked=true;
   await rejected.fire('input');
   assert.equal(rejected.message.textContent,errorText,'Editing blocked contacts must not erase the reason that the tour cannot be sent');
   f.setSessionError(null);
   const recovered=f.mount();f.api.bind(offer);
   for(const [name,value]of Object.entries(values))assert.equal(recovered.form.elements[name].value,value,'Contact draft entered after a session error survives returning to the tour and reopening contacts');
   assert.equal(recovered.form.elements.consent.checked,false,'Consent is never restored from the draft');
   const before=[f.payloads.length,f.submissions.length];
   assert.equal(await rejected.fire('submit'),true,'A rejected binding still cancels a dispatched native submit event');
   rejected.button.disabled=false;
   assert.equal(await rejected.fire('submit'),true,'Missing session remains a guard independent of button state');
   assert.deepEqual([f.payloads.length,f.submissions.length],before,'Blocked form events do not reach payload or delivery');
  }
  const form=f.mount();f.api.bind(offer);form.form.elements.consent.checked=true;
  f.setPhoneValid(false);await form.fire('submit');assert.equal(f.payloads.length,0,'Invalid phone never constructs a request');
  f.setPhoneValid(true);form.form.elements.consent.checked=false;await form.fire('submit');assert.equal(f.payloads.length,0,'Missing consent never constructs a request');
  form.form.elements.consent.checked=true;assert.equal(await form.fire('submit'),true);
  assert.equal(f.payloads.length,1);assert.equal(f.payloads[0].get('phone'),values.phone);
  assert.equal(f.receivedOffers.at(-1),offer,'The same selected offer enters the unchanged session owner');
  if(preview){
   assert.equal(f.submissions.length,0,'Preview never calls delivery');assert.equal(form.form.dataset.checked,'1');assert.match(form.message.textContent,/не отправлена/);
   await form.fire('input');assert.equal(form.message.textContent,'');assert.equal(form.form.dataset.checked,undefined);
  }else{
   assert.equal(f.submissions.length,1);assert.equal(f.submissions[0].form,form.form);assert.equal(f.submissions[0].options.button,form.button);
   const retry=f.mount();f.api.bind(offer);assert.equal(retry.form.elements.phone.value,values.phone,'An unsuccessful delivery keeps the draft');
   retry.form.elements.consent.checked=true;f.setSubmitResult(true);await retry.fire('submit');
   const cleared=f.mount();f.api.bind(offer);assert.equal(cleared.form.elements.phone.value,'','Successful canonical delivery still clears the draft');
  }
  const stale=f.mount();f.api.bind(offer);stale.form.elements.phone.value=values.phone;stale.form.elements.consent.checked=true;
  f.setPayloadError(new Error('Условия поиска изменились.'));
  const beforeSubmit=f.submissions.length;assert.equal(await stale.fire('submit'),true);
  assert.equal(stale.message.role,'alert');assert.match(stale.message.textContent,/изменились/);assert.equal(f.submissions.length,beforeSubmit);
  stale.form.dataset.sent='1';const before=f.payloads.length;await stale.fire('submit');assert.equal(f.payloads.length,before);
  f.api.reset();const reset=f.mount();f.api.bind(offer);for(const name of Object.keys(values))assert.equal(reset.form.elements[name].value,'');
  console.log(`Lead binder ${preview?'preview':'delivery-stub'}: rejected/pending/stale draft, submit containment, recovery, consent, validation and reset PASS`);
 }
 await lateSubmissionDrafts();
 await nativeRecovery();
 console.log('Unit harness only; real browser/supplier/network/delivery calls: 0.');
})().catch(error=>{console.error(error);process.exitCode=1;});
