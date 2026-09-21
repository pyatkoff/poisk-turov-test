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
 vm.runInNewContext(source,{window,document,URL,FormData:FormDataFixture});
 function mount(){
  const listeners=new Map(),button={disabled:false},message={textContent:'',role:'status',scrolls:0,setAttribute(name,value){this[name]=value;},scrollIntoView(){this.scrolls++;}};
  const elements=Object.fromEntries(['name','phone','comment','consent'].map(name=>[name,{value:name==='consent'?'1':'',checked:false,reportValidity(){return phoneValid;}}]));
  const form={elements,dataset:{},querySelector(){return message;},addEventListener(type,fn){const list=listeners.get(type)||[];list.push(fn);listeners.set(type,list);},reportValidity(){return elements.consent.checked;}};
  async function fire(type){let prevented=false;for(const fn of listeners.get(type)||[])await fn({preventDefault(){prevented=true;}});return prevented;}
  current={form,button,message,fire};return current;
 }
 return{api:window.AnyTourPrototypeLead,mount,payloads,submissions,receivedOffers,
  setSessionError(value){sessionError=value;},setPayloadError(value){payloadError=value;},setPhoneValid(value){phoneValid=value;},setSubmitResult(value){submitResult=value;}};
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
 console.log('Unit harness only; real browser/supplier/network/delivery calls: 0.');
})().catch(error=>{console.error(error);process.exitCode=1;});
