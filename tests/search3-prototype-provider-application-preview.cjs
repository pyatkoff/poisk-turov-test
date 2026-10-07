'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

const leadSource=fs.readFileSync('v2/prototype-search/lead.js','utf8');
const appSource=fs.readFileSync('v2/prototype-search/app.js','utf8');

function leadApi(leadApi){
 const root={V2_CONFIG:{leadApi},location:{href:'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'}};
 const context={window:root,URL,structuredClone,Intl,document:{},FormData:function(){}};
 vm.createContext(context);vm.runInContext(leadSource,context,{filename:'lead.js'});return root.AnyTourPrototypeLead;
}
const receipt={
 provider:'andromeda',offerRef:'offer_'+'a'.repeat(64),hotel:'Fixture Hotel',country:'Турция',resort:'Сиде',
 day:'2026-10-05',nights:7,adults:2,ages:[6,6],room:'Deluxe Sea View',meal:'Всё включено',
 expiresAt:Math.floor(Date.now()/1000)+900,operator:'FUN&SUN',price:199900,currency:'RUB',flights:[
  {direction:'0',name:'ZF 3001',datebeg:'2026-10-05 09:10',dateend:'2026-10-05 13:30',class:'ECONOM',departure:{town:'Москва',port:'VKO'},arrival:{town:'Анталья',port:'AYT'}},
  {direction:'1',name:'ZF 3002',datebeg:'2026-10-12 15:20',dateend:'2026-10-12 19:40',class:'ECONOM',departure:{town:'Анталья',port:'AYT'},arrival:{town:'Москва',port:'VKO'}}
 ]
};
const values={name:'Иван',phone:'+7 999 111-22-33',comment:'Тихий номер',consent:'1'};
const fd={get:key=>values[key]??null};

const preview=leadApi('/_preview/search3-local-candidate/preview-lead-disabled.php');
const payload=preview.providerPreviewPayload(receipt,fd);
assert.equal(payload.provider,'andromeda');
assert.equal(payload.providerOfferRef,receipt.offerRef);
assert.equal(payload.hotel,'Fixture Hotel');
assert.equal(payload.date,'2026-10-05');
assert.equal(payload.nights,7);
assert.equal(payload.adults,2);
assert.deepEqual(Array.from(payload.childAges),[6,6],'duplicate child ages are preserved');
assert.equal(payload.roomType,'Deluxe Sea View');
assert.equal(payload.meal,'Всё включено');
assert.equal(payload.operator,'FUN&SUN');
assert.equal(payload.price,199900);
assert.equal(payload.currency,'RUB');
assert.match(payload.flight,/ZF 3001/);
assert.match(payload.flight,/ZF 3002/);
assert.equal(payload.delivery,'preview-disabled');
assert.equal(payload.consent,true);

for(const mutate of [
 r=>{r.expiresAt=Math.floor(Date.now()/1000);},
 r=>{r.expiresAt='9999999999';},
 r=>{r.provider='tourvisor';},
 r=>{r.offerRef='bad';},
 r=>{r.price=0;},
 r=>{r.currency='USD';},
 r=>{r.ages=[18];},
 r=>{r.flights=[{direction:'x'}];}
]){
 const bad=structuredClone(receipt);mutate(bad);
 assert.throws(()=>preview.providerPreviewPayload(bad,fd),/Подтверждён|рейсы|Условия тура|Срок/i);
}

const anexReceipt={provider:'anex',offerRef:'anex_online:'+'b'.repeat(64),searchRef:'c'.repeat(32),generation:7,localHotelId:501,
 priceKind:'estimate',finalPriceVerified:false,hotel:'Fixture Hotel',country:'Турция',resort:'Белек',day:'2026-10-05',nights:7,
 adults:2,ages:[],room:'ANEX CONCRETE',meal:'Всё включено',operator:'ANEX',price:123000,currency:'RUB',flights:[]};
const anexPayload=preview.providerPreviewPayload(anexReceipt,fd);
assert.equal(anexPayload.provider,'anex');assert.equal(anexPayload.price,123000);assert.equal(anexPayload.priceKind,'estimate');
assert.equal(anexPayload.finalPriceVerified,false);assert.equal(anexPayload.flight,'');
for(const mutate of [
 r=>{r.offerRef='bad';},r=>{r.searchRef='bad';},r=>{r.generation=0;},r=>{r.localHotelId=0;},
 r=>{r.priceKind='verified';},r=>{r.finalPriceVerified=true;}
]){const bad=structuredClone(anexReceipt);mutate(bad);assert.throws(()=>preview.providerPreviewPayload(bad,fd),/ANEX|непол/i);}

const production=leadApi('/lead-adapter-v2.php');
assert.throws(()=>production.providerPreviewPayload(receipt,fd),/только в изолированной preview-версии/);assert.throws(()=>production.providerPreviewPayload(anexReceipt,fd),/только в изолированной preview-версии/);

const bindStart=leadSource.indexOf('function bindProviderPreview(receipt)');
const bindEnd=leadSource.indexOf('\n  function reset()',bindStart);
assert.ok(bindStart>=0&&bindEnd>bindStart,'provider preview binder exists');
const bindSource=leadSource.slice(bindStart,bindEnd);
assert.doesNotMatch(bindSource,/fetch\s*\(|session\.submit|leadSession\(/,'provider preview binder cannot send a lead');
assert.match(bindSource,/form\.dataset\.checked='1'/);
assert.match(bindSource,/Заявка не отправлена/);

const receiptStart=appSource.indexOf('function andromedaApplicationReceipt(o,quote,h)');
const verifiedStart=appSource.indexOf('function openAndromedaVerified(o,quote)');
const applicationStart=appSource.indexOf('function openAndromedaApplicationPreview()');
const applyStart=appSource.indexOf('async function applyAndromedaFlightChoice()',applicationStart);
assert.ok(receiptStart>=0&&verifiedStart>receiptStart&&applicationStart>verifiedStart&&applyStart>applicationStart);
const receiptSource=appSource.slice(receiptStart,verifiedStart);
assert.match(receiptSource,/quote\?\.state!=='quote_verified'/);
assert.match(receiptSource,/quote\?\.finalPriceVerified!==true/);
assert.match(receiptSource,/quote\?\.flightSelectionRequired!==false/);
assert.match(receiptSource,/quote\?\.finalPrice\?\.currency!=='RUB'/);
assert.match(receiptSource,/offer_[a-f0-9]/,'opaque Andromeda offer identity is retained');

const verifiedSource=appSource.slice(verifiedStart,applicationStart);
assert.match(verifiedSource,/data-action="andromeda-application-preview"/);
assert.match(verifiedSource,/реальная отправка здесь отключена/);
const applicationSource=appSource.slice(applicationStart,applyStart);
assert.match(applicationSource,/AnyTourPrototypeLead\.markup\(\)/);
assert.match(applicationSource,/AnyTourPrototypeLead\.action\(\)/);
assert.match(applicationSource,/AnyTourPrototypeLead\.bindProviderPreview\(receipt\)/);
assert.doesNotMatch(applicationSource,/fetch\s*\(|leadSession\(/);
assert.match(appSource,/case 'andromeda-application-preview':openAndromedaApplicationPreview\(\)/);

const anexStart=appSource.indexOf('function openAnexConcreteCurrent(o,current)');
const anexEnd=appSource.indexOf('\nasync function refreshHotel(id)',anexStart);
assert.ok(anexStart>=0&&anexEnd>anexStart,'ANEX provider-current block exists');
const anexSource=appSource.slice(anexStart,anexEnd);
assert.doesNotMatch(anexSource,/bindProviderPreview|andromeda-application-preview/,'ANEX non-final money cannot enter the verified Andromeda application preview path');

// A receipt can expire after the contact form opens. Its recovery instruction
// must be revealed without losing the draft or attempting any delivery.
function previewForm(receipt){
 let now=Date.now(),requests=0;
 const listeners={},scrolls=[],attributes={};
 const message={textContent:'',setAttribute:(key,value)=>{attributes[key]=value;},scrollIntoView:options=>scrolls.push(options)};
 const elements={name:{value:''},phone:{value:''},comment:{value:''},consent:{checked:false}};
 const form={elements,dataset:{},querySelector:()=>message,reportValidity:()=>true,addEventListener:(name,handler)=>{listeners[name]=handler;}};
 const button={disabled:false};
 const root={V2_CONFIG:{leadApi:'/_preview/search3-next-candidate/preview-lead-disabled.php'},location:{href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/'},
  V2LeadFormGuard:{validatePhone:input=>input.value.replace(/\D/g,'').length===11}};
 class Clock extends Date{static now(){return now;}}
 const context={window:root,URL,structuredClone,Intl,Date:Clock,
  document:{getElementById:()=>form,querySelector:()=>button},
  fetch(){requests++;throw new Error('Preview feedback must not deliver a lead');},
  FormData:function(){this.get=key=>key==='consent'?(elements.consent.checked?'1':null):elements[key]?.value??null;}};
 vm.createContext(context);vm.runInContext(leadSource,context,{filename:'lead.js'});
 const accepted={...structuredClone(receipt),expiresAt:Math.floor(now/1000)+60};
 root.AnyTourPrototypeLead.bindProviderPreview(accepted);
 for(const key of ['name','phone','comment'])elements[key].value=values[key];
 elements.consent.checked=true;listeners.input();
 return {form,elements,message,attributes,scrolls,advance:()=>{now+=61000;},submit:()=>listeners.submit({preventDefault(){}}),requests:()=>requests};
}
const verifiedAnex={...anexReceipt,priceKind:'verified',finalPriceVerified:true,choiceRef:'anex_quote:'+'d'.repeat(64),
 flights:[{direction:'0',name:'ANEX fixture outbound'},{direction:'1',name:'ANEX fixture return'}]};
for(const r of [receipt,verifiedAnex]){
 const expired=previewForm(r);expired.advance();expired.submit();
 assert.equal(expired.attributes.role,'alert');
 assert.match(expired.message.textContent,/Срок|неполный/);
 assert.equal(expired.form.dataset.checked,undefined,'expired price cannot be accepted');
 assert.equal(expired.scrolls.length,1,'late-expiry recovery message must be revealed above the mobile footer');
 assert.equal(expired.scrolls[0].block,'nearest','reveal the message without an unnecessary page jump');
 for(const key of ['name','phone','comment'])assert.equal(expired.elements[key].value,values[key],'expiry preserves '+key);
 assert.equal(expired.elements.consent.checked,true,'failed preview validation keeps the current form draft');
 assert.equal(expired.requests(),0,'expiry feedback does not invoke lead transport');
 const current=previewForm(r);current.submit();
 assert.equal(current.attributes.role,'status');assert.equal(current.form.dataset.checked,'1');
 assert.match(current.message.textContent,/Заявка не отправлена/);
 assert.equal(current.scrolls.length,1);assert.equal(current.requests(),0);
}

console.log('SEARCH3_PROVIDER_APPLICATION_PREVIEW_OK');
