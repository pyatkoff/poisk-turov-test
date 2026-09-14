const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const listeners = new Map();
global.window = {
  addEventListener(type, fn) { listeners.set(type, fn); },
  dispatchEvent() {},
  requestAnimationFrame(fn) { return fn(); },
  setTimeout,
  clearTimeout,
};
global.document = {
  readyState: 'loading',
  addEventListener() {},
  getElementById() { return null; },
  querySelector() { return null; },
  documentElement: { dataset: {} },
  body: { classList: { contains() { return true; }, add() {} } },
};
global.CustomEvent = class CustomEvent {
  constructor(type, init) { this.type = type; this.detail = init && init.detail; }
};

vm.runInThisContext(fs.readFileSync('v2/results-renderer-v5.js', 'utf8'), { filename: 'v2/results-renderer-v5.js' });
const api = window.V2Results;
assert.ok(api, 'renderer API is available');

const multi = {
  id: 'hotel-1',
  price: 62400,
  tours: [
    { id: 'a', price: 62400, date: '2026-09-16', nights: 7, meal: { name: 'BB', fullName: 'Завтраки' }, operator: { name: 'FUN&SUN' }, isCharter: true },
    { id: 'b', price: 70100, date: '2026-09-17', nights: 10, meal: { name: 'AI', fullName: 'Всё включено' }, operator: { name: 'ANEX' }, isCharter: false },
    { id: 'c', price: 71500, date: '2026-09-18', nights: 9, meal: { name: 'AI', fullName: 'Всё включено' }, operator: { name: 'FUN&SUN' } },
  ],
};

// A HOTEL aggregate must not borrow one concrete tour's conditions before disclosure.
const freeze=value=>{if(value&&typeof value==='object'){Object.values(value).forEach(freeze);Object.freeze(value);}return value;};
freeze(multi);
const original=JSON.stringify(multi);
for(const tours of [multi.tours,[...multi.tours].reverse()]){
  const hotel={...multi,tours};
  assert.equal(api.representativeTour(hotel),multi.tours[0]);
  const html=api.toursHtml(hotel);
  assert.match(html,/hotel-offers-summary/);
  assert.match(html,/от 62(?:\s| )?400/);
  assert.match(html,/Показать варианты · 3/);
  assert.doesNotMatch(html,/class="tour-row"|data-tid=|direct-tour|data-operator-brand=|16\.09\.2026|17\.09\.2026|18\.09\.2026|7 ноч\.|9 ноч\.|10 ноч\.|Завтраки|Всё включено|FUN&SUN|ANEX|Чартер|Регулярный рейс/);
}
assert.equal(api.priceContext(multi),'16.09.2026 · 7 ноч. · Завтраки');
// The collapsed aggregate reads the canonical hotel-level minimum and never invents tour conditions around it.
for(const [price,label] of [[61000,'61'],[999999,'999'],[undefined,'62'],[null,'62'],[0,'62'],[-1,'62'],['unknown','62']]){
  const html=api.toursHtml({...multi,price});
  assert.match(html,new RegExp('от '+label));
  assert.doesNotMatch(html,/data-tid=|16\.09\.2026|Завтраки|FUN&SUN|Чартер/);
}
// Readiness remains per concrete offer and is not projected onto the hotel aggregate.
for(const flags of [{selectionEnabled:false},{selection_enabled:false},{provider:'andromeda'},{provider:'ANDROMEDA',selectionEnabled:true}]){
  const blocked={...multi.tours[0],...flags},other={...multi.tours[1],price:62400};
  const collapsed=api.toursHtml({...multi,tours:[blocked,other]});
  assert.doesNotMatch(collapsed,/нужна проверка|direct-tour/);
  const blockedRow=api.tourRow(blocked);
  assert.match(blockedRow,/нужна проверка/);
  assert.doesNotMatch(blockedRow,/direct-tour/);
  const otherRow=api.tourRow(other);
  assert.match(otherRow,/data-tid="b"/);
  assert.doesNotMatch(otherRow,/нужна проверка|Часть вариантов/);
}
// Per-offer party validation stays exact inside offer rows, never on the collapsed hotel aggregate.
for(const [adults,childs,label] of [[2,0,'2 взрослых'],[2,1,'2 взрослых · 1 ребёнок'],[1,2,'1 взрослый · 2 ребёнка'],['3','0','3 взрослых']]){
  const offer=Object.freeze({...multi.tours[0],adults,childs});
  assert.ok(api.tourRow(offer).includes('<small>Туристы</small><b>'+label+'</b>'));
  assert.doesNotMatch(api.toursHtml({...multi,tours:[offer,multi.tours[1]]}),/<small>Туристы<\/small>/);
}
for(const counts of [{adults:2},{adults:2,childs:null},{adults:2,childs:''},{adults:2,childs:false},{adults:2,childs:-1},{adults:2,childs:1.5},{adults:0,childs:0},{adults:true,childs:0},{adults:'unknown',childs:0}]){
  const offer=Object.freeze({...multi.tours[0],...counts});
  assert.doesNotMatch(api.tourRow(offer),/<small>Туристы<\/small>/);
}
const incomplete={...multi.tours[0],date:'',nights:undefined,meal:'',operator:'',isCharter:undefined};
const incompleteCollapsed=api.toursHtml({...multi,tours:[incomplete,multi.tours[1]]});
assert.doesNotMatch(incompleteCollapsed,/17\.09\.2026|10 ноч|Всё включено|ANEX|Регулярный рейс|Уточняется/,'collapsed hotel does not fill or expose exact-offer gaps');
assert.match(api.tourRow(incomplete),/>Уточняется</);
// Meal facet identities remain tested independently of the removed aggregate UI.
for(const meal of [{name:'AI',fullName:'Всё включено'},{fullName:'All Inclusive'},'Всё включено'])assert.equal(api.mealIdentity({meal}).key,'meal:all-inclusive');
const identity=meal=>api.mealIdentity({meal}).key;
assert.notEqual(identity('UAI'),identity('AI'));
assert.notEqual(identity('Soft AI'),identity('AI'));
assert.notEqual(identity('Premium All Inclusive'),identity('AI'));
assert.notEqual(identity('Not all inclusive'),identity('AI'));
assert.notEqual(identity('Breakfast and dinner'),identity('Breakfast'));
assert.equal(identity('Premium All Inclusive'),identity('premium all inclusive'));
assert.equal(JSON.stringify(multi),original,'renderer preserves all original offers, values and ordering');
assert.match(api.toursHtml({...multi,tours:[]}),/Нет доступных вариантов/);
const single={...multi,tours:[multi.tours[0]]};
assert.equal(api.toursHtml(single),api.tourRow(multi.tours[0]));
assert.equal(api.choiceHint(multi),'');
assert.equal(api.choiceHint(single),'1 вариант тура');
console.log('SEARCH3_HOTEL_CARD_SUMMARY_OK collapsed_hotel_level=1 exact_offer_details_disclosed=1 source_unchanged=1');
