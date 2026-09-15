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
  assert.doesNotMatch(html,/class="tour-row"|data-tid=|direct-tour|data-operator-brand=|16\.09\.2026|17\.09\.2026|18\.09\.2026|7 ноч\.|9 ноч\.|10 ноч\.|Завтрак|Всё включено|FUN&SUN|ANEX|Чартер|Регулярный рейс/);
}
assert.equal(api.priceContext(multi),'16.09.2026 · 7 ноч. · Завтрак');
// The collapsed aggregate reads the canonical hotel-level minimum and never invents tour conditions around it.
for(const [price,label] of [[61000,'61'],[999999,'999'],[undefined,'62'],[null,'62'],[0,'62'],[-1,'62'],['unknown','62']]){
  const html=api.toursHtml({...multi,price});
  assert.match(html,new RegExp('от '+label));
  assert.doesNotMatch(html,/data-tid=|16\.09\.2026|Завтрак|FUN&SUN|Чартер/);
}
// Readiness remains per concrete offer and is not projected onto the hotel aggregate.
for(const flags of [{selectionEnabled:false},{selection_enabled:false},{provider:'andromeda'},{provider:'ANDROMEDA',selectionEnabled:true}]){
  const blocked={...multi.tours[0],...flags},other={...multi.tours[1],price:62400};
  const collapsed=api.toursHtml({...multi,tours:[blocked,other]});
  assert.doesNotMatch(collapsed,/нужна проверка|direct-tour/);
  const blockedRow=api.tourRow(blocked);
  assert.match(blockedRow,/(?:нужна проверка|проверим цену и рейсы)/);
  assert.doesNotMatch(blockedRow,/direct-tour/);
  const otherRow=api.tourRow(other);
  assert.match(otherRow,/data-tid="b"/);
  assert.doesNotMatch(otherRow,/нужна проверка|Часть вариантов/);
}
// Exact offer rows keep decision-critical differences, but do not repeat search-level/internal context.
const compact=api.tourRow({...multi.tours[0],roomType:'STANDARD',placement:'DBL',adults:2,childs:1,provider:'tourvisor'});
assert.match(compact,/<small>Номер<\/small><b>Стандарт · DBL<\/b>/);
assert.doesNotMatch(compact,/Источник|Tourvisor/,'expanded exact offer keeps provider provenance out of customer copy');
assert.doesNotMatch(compact,/<small>(?:Туристы|Размещение)<\/small>/,'expanded rows do not repeat search party or a second placement field');
assert.doesNotMatch(compact,/<small>Оператор<\/small>/,'known operator does not repeat a caption beside its logo');
assert.match(compact,/title="Туроператор: FUN&amp;SUN"/,'operator remains named in its tooltip');
assert.match(compact,/alt="Туроператор: FUN&amp;SUN"/,'operator remains named for assistive technology');
assert.match(compact,/<small>Перелёт<\/small><b>Чартер<\/b>/);
assert.match(compact,/16\.09\.2026/);
assert.match(compact,/7 ноч\./);
assert.match(compact,/<b>Завтрак<\/b>/);
assert.match(compact,/62(?:\s| )?400/);
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
for(const [plus,base] of [['AI+','AI'],['BB+','BB'],['HB+','HB'],['FB+','FB'],['RO+','RO'],['SC+','SC']]){
  const plusIdentity=api.mealIdentity({meal:plus});
  assert.equal(plusIdentity.key,'meal:label:'+plus.toLowerCase());
  assert.equal(plusIdentity.label,plus);
  assert.notEqual(plusIdentity.key,identity(base));
}
assert.deepEqual(api.mealIdentity({meal:{name:'HB+',fullName:'Полупансион'}}),{key:'meal:label:hb+',label:'HB+'});
assert.equal(identity('Premium All Inclusive'),identity('premium all inclusive'));
assert.equal(JSON.stringify(multi),original,'renderer preserves all original offers, values and ordering');
assert.match(api.toursHtml({...multi,tours:[]}),/Нет доступных вариантов/);
const single={...multi,tours:[multi.tours[0]]};
assert.equal(api.toursHtml(single),api.tourRow(multi.tours[0]));
assert.equal(api.choiceHint(multi),'');
assert.equal(api.choiceHint(single),'1 вариант тура');
const grouped={...single,andromedaExpansion:{status:'idle',count:0}};
assert.equal(api.choiceHint(grouped),'','grouped seed does not claim that the hotel has only one tour');
assert.match(api.toursHtml(grouped),/hotel-offers-summary/);
assert.doesNotMatch(api.toursHtml(grouped),/class="tour-row"|data-andromeda-expand|provider-expansion-toggle/);
const local={id:21477,name:'Локальный отель',country:{name:'Египет'},region:{name:'Шарм-эль-Шейх'},subRegion:{name:'Наама-Бей'},category:5,rating:4.8,detailsAvailable:true,primaryImage:'https://img.example/1.jpg',images:['https://img.example/1.jpg','https://img.example/2.jpg'],description:'<b>Проверенное описание</b>',address:'Наама-Бей',infrastructure:{beach:'Песчаный пляж',territory:'Бассейн'},services:{free:'Wi-Fi'},meals:{description:'Всё включено'},roomTypes:'Стандарт, семейный'};
api.hotelDetailsCache.set('21477',local);
const localHtml=api.hotelMainHtml({id:21477,name:'Supplier name',picturelink:'https://supplier.example/photo.jpg',tours:[multi.tours[0]]});
assert.match(localHtml,/Локальный отель/,'local hotel name owns the hotel card');
assert.match(localHtml,/hotel-gallery-thumb/,'local gallery is available before offer selection');
assert.match(localHtml,/Показать фото 2/,'gallery keeps a named keyboard action');
assert.match(localHtml,/class="hotel-description-summary">&lt;b&gt;Проверенное описание&lt;\/b&gt;<\/p>/,'trusted local description is visible in the collapsed hotel presentation');
assert.match(localHtml,/Подробнее об отеле/,'trusted local details have one clearly labelled native disclosure');
assert.match(localHtml,/&lt;b&gt;Проверенное описание&lt;\/b&gt;/,'local description remains escaped text');
assert.match(localHtml,/Песчаный пляж · Бассейн[\s\S]*Wi-Fi[\s\S]*Всё включено/,'object-shaped local characteristics are presented instead of discarded');
assert.doesNotMatch(localHtml,/<b>Проверенное описание<\/b>/,'local description cannot inject markup');
const inlineImage=api.hotelMainHtml({id:'fixture',name:'Fixture',picturelink:'data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22/%3E'});
assert.match(inlineImage,/class="hotel-photo hotel-gallery"/,'bounded image data URI fixtures retain the established card geometry');
const unsafeImage=api.hotelMainHtml({id:'unsafe',name:'Unsafe',picturelink:'javascript:alert(1)'});
assert.doesNotMatch(unsafeImage,/javascript:/,'non-image and executable URL schemes never reach image markup');
console.log('SEARCH3_HOTEL_CARD_SUMMARY_OK collapsed_hotel_level=1 compact_exact_offer_rows=1 local_gallery_details=1 source_unchanged=1');
