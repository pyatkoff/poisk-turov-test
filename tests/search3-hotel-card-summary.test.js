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

// The owner-reported regression: one displayed price must have one original
// departure, duration, meal and operator, even before alternatives are opened.
const freeze=value=>{if(value&&typeof value==='object'){Object.values(value).forEach(freeze);Object.freeze(value);}return value;};
freeze(multi);
const original=JSON.stringify(multi);
for(const tours of [multi.tours,[...multi.tours].reverse()]){
  const hotel={...multi,tours};
  assert.equal(api.representativeTour(hotel),multi.tours[0]);
  const html=api.toursHtml(hotel);
  assert.ok(html.startsWith(api.tourRow(multi.tours[0])),'collapsed row is the exact original cheapest offer');
  assert.equal((html.match(/class="tour-row"/g)||[]).length,1);
  assert.equal((html.match(/class="hotel-price"/g)||[]).length,1);
  assert.match(html,/data-tid="a"/);
  assert.match(html,/16\.09\.2026/);
  assert.match(html,/ · 7 ноч\./);
  assert.match(html,/Завтраки/);
  assert.match(html,/Показать варианты · 3/);
  assert.doesNotMatch(html,/7[–-]10|7[–-]9|Несколько дат|BB · AI|17\.09\.2026|18\.09\.2026|Всё включено|hotel-trip-summary|hotel-summary-total/);
  assert.doesNotMatch(html,/от 62|data-tid="[bc]"/,'no aggregate minimum or action belonging to another tour');
}
assert.equal(api.priceContext(multi),'16.09.2026 · 7 ноч. · Завтраки');
// A stale/missing hotel-level minimum never lends its price to another offer.
for(const price of [61000,999999,undefined,null,0,-1,'unknown']){
  const html=api.toursHtml({...multi,price});
  assert.ok(html.startsWith(api.tourRow(multi.tours[0])));
  assert.match(html,/62(?:\s| )?400/);
  assert.doesNotMatch(html,/61(?:\s| )?000|999(?:\s| )?999/);
}
// Equal prices do not merge readiness or conditions of distinct source offers.
for(const flags of [{selectionEnabled:false},{selection_enabled:false},{provider:'andromeda'},{provider:'ANDROMEDA',selectionEnabled:true}]){
  const blocked={...multi.tours[0],...flags},other={...multi.tours[1],price:62400};
  const html=api.toursHtml({...multi,tours:[blocked,other]});
  assert.ok(html.startsWith(api.tourRow(blocked)));
  assert.match(html,/нужна проверка/);
  assert.doesNotMatch(html,/direct-tour/);
  const reversed=api.toursHtml({...multi,tours:[other,blocked]});
  assert.ok(reversed.startsWith(api.tourRow(other)));
  assert.match(reversed,/data-tid="b"/);
  assert.doesNotMatch(reversed,/нужна проверка|Часть вариантов/);
}
// Per-offer party validation must not borrow missing counts from siblings/form.
for(const [adults,childs,label] of [[2,0,'2 взрослых'],[2,1,'2 взрослых · 1 ребёнок'],[1,2,'1 взрослый · 2 ребёнка'],['3','0','3 взрослых']]){
  const offer=Object.freeze({...multi.tours[0],adults,childs});
  assert.ok(api.toursHtml({...multi,tours:[offer,multi.tours[1]]}).includes('<small>Туристы</small><b>'+label+'</b>'));
}
for(const counts of [{adults:2},{adults:2,childs:null},{adults:2,childs:''},{adults:2,childs:false},{adults:2,childs:-1},{adults:2,childs:1.5},{adults:0,childs:0},{adults:true,childs:0},{adults:'unknown',childs:0}]){
  const offer=Object.freeze({...multi.tours[0],...counts});
  assert.doesNotMatch(api.toursHtml({...multi,tours:[offer,{...multi.tours[1],adults:2,childs:1}]}),/<small>Туристы<\/small>/);
}
const incomplete={...multi.tours[0],date:'',nights:undefined,meal:'',operator:'',isCharter:undefined};
const incompleteHtml=api.toursHtml({...multi,tours:[incomplete,multi.tours[1]]});
assert.doesNotMatch(incompleteHtml,/17\.09\.2026|10 ноч|Всё включено|ANEX|Регулярный рейс/,'missing primary facts are not filled from the next offer');
assert.match(incompleteHtml,/>Уточняется</);
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
console.log('SEARCH3_HOTEL_CARD_SUMMARY_OK exact_collapsed_offer=1 mixed_conditions_absent=1 source_unchanged=1');
