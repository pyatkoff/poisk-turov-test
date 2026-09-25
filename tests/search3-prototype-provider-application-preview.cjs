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
 operator:'FUN&SUN',price:199900,currency:'RUB',flights:[
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
 r=>{r.provider='tourvisor';},
 r=>{r.offerRef='bad';},
 r=>{r.price=0;},
 r=>{r.currency='USD';},
 r=>{r.ages=[18];},
 r=>{r.flights=[{direction:'x'}];}
]){
 const bad=structuredClone(receipt);mutate(bad);
 assert.throws(()=>preview.providerPreviewPayload(bad,fd),/Подтверждён|рейсы/i);
}
const production=leadApi('/lead-adapter-v2.php');
assert.throws(()=>production.providerPreviewPayload(receipt,fd),/только в изолированной preview-версии/);

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
const anexEnd=appSource.indexOf('\nfunction andromedaApplicationReceipt',anexStart);
const anexSource=appSource.slice(anexStart,anexEnd);
assert.doesNotMatch(anexSource,/bindProviderPreview|andromeda-application-preview/,'ANEX non-final money cannot enter the verified Andromeda application preview path');

console.log('SEARCH3_PROVIDER_APPLICATION_PREVIEW_OK');
