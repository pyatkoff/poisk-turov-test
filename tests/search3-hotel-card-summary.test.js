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
assert.match(compact,/<small>Номер<\/small><b>Стандарт · Двухместное<\/b>/);
assert.doesNotMatch(compact,/Источник|Tourvisor/,'expanded exact offer keeps provider provenance out of customer copy');
assert.doesNotMatch(compact,/<small>(?:Туристы|Размещение)<\/small>/,'expanded rows do not repeat search party or a second placement field');
assert.equal(api.roomLabel({roomType:'economy room'}),'Эконом','common supplier room code has a customer-facing label');
assert.equal(api.roomLabel({roomType:'promo room'}),'Промо','common promotional room code has a customer-facing label');
assert.equal(api.roomLabel({roomType:'standard pool view room'}),'Стандарт · вид на бассейн','actual supplier pool-view spelling uses the canonical customer label');
for (const value of ['standard garden view','standard garden view room','standard room with garden view']) assert.equal(api.roomLabel({roomType:value}),'Стандарт · вид на сад',value+' uses the canonical garden-view label');
assert.equal(api.roomLabel({roomType:'standard sea view room'}),'Стандарт · море','standard sea-view room uses the canonical customer label');
assert.equal(api.roomLabel({roomType:'standard side sea view room'}),'Стандарт · боковой вид на море','standard side-sea-view room uses the canonical customer label');
for (const value of ['superior garden view','superior garden view room','superior room garden view']) assert.equal(api.roomLabel({roomType:value}),'Улучшенный · вид на сад',value+' uses the canonical superior garden-view label');
assert.equal(api.roomLabel({roomType:'superior side sea view'}),'Улучшенный · боковой вид на море','superior side-sea-view uses the canonical customer label');
for (const [value, label] of [
  ['standard garden or pool view', 'Стандарт · вид на сад или бассейн'],
  ['standard pool / lagoon view', 'Стандарт · вид на бассейн или лагуну'],
  ['standard marina view', 'Стандарт · вид на марину'],
  ['family one bedroom', 'Семейный · 1 спальня'],
  ['club room', 'Клубный'],
  ['premium garden view room', 'Премиум · вид на сад'],
  ['premium room mountain', 'Премиум · вид на горы'],
]) assert.equal(api.roomLabel({roomType:value}), label, value+' uses its exact customer-facing label');
assert.match(api.tourRow({...multi.tours[0],roomType:'standard pool view room',placement:'DBL',provider:'tourvisor'}),/<small>Номер<\/small><b>Стандарт · вид на бассейн · Двухместное<\/b>/,'result row uses the same canonical pool-view room label');
assert.equal(api.roomLabel({roomType:'supplier special room'}),'supplier special room','unknown supplier room label remains verbatim');
assert.equal(api.placementLabel('DBL + CHD'),'Двухместное + ребёнок','common placement codes have a customer-facing label');
assert.equal(api.placementLabel('DBL + 2 CHD'),'Двухместное + 2 ребёнка','placement aliases keep exact child count');
assert.equal(api.placementLabel('Villa with private pool'),'Villa with private pool','unknown supplier placement remains verbatim');
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
const local={id:21477,name:'Локальный отель',country:{name:'Египет'},region:{name:'Шарм-эль-Шейх'},subRegion:{name:'Наама-Бей'},category:5,rating:4.8,detailsAvailable:true,primaryImage:'https://img.example/1.jpg',images:['https://img.example/1.jpg','https://img.example/2.jpg'],description:'<b>Проверенное описание</b><br>Вторая строка<script>alert(1)</script>',address:'Наама-Бей',infrastructure:{beach:'Песчаный пляж',territory:'Бассейн'},services:{free:'Wi-Fi'},meals:{description:'Всё включено'},roomTypes:'<UL><LI>Промо: 24 кв.м.</LI><LI>Стандарт: 30 кв.м.</LI></UL>'};
api.hotelDetailsCache.set('21477',local);
const localHtml=api.hotelMainHtml({id:21477,name:'Supplier name',picturelink:'https://supplier.example/photo.jpg',tours:[multi.tours[0]]});
assert.match(localHtml,/Локальный отель/,'local hotel name owns the hotel card');
assert.match(localHtml,/hotel-gallery-thumb/,'local gallery is available before offer selection');
assert.match(localHtml,/Поменять главное фото, миниатюра 1/,'gallery keeps a truthful named keyboard action after swapping photos');
assert.match(localHtml,/class="hotel-description-summary">Проверенное описание · Вторая строка<\/p>/,'legacy line markup becomes readable plain text in the collapsed hotel presentation');
assert.match(localHtml,/Подробнее об отеле/,'trusted local details have one clearly labelled native disclosure');
assert.match(localHtml,/Промо: 24 кв\.м\. · Стандарт: 30 кв\.м\./,'legacy room-list markup becomes readable plain text');
assert.match(localHtml,/Песчаный пляж · Бассейн[\s\S]*Wi-Fi[\s\S]*Всё включено/,'object-shaped local characteristics are presented instead of discarded');
assert.doesNotMatch(localHtml,/(?:<|&lt;)(?:b|br|ul|li|script)(?:\s|>|\/)|alert\(1\)/i,'stored markup cannot execute or remain as visible tag noise');
const encodedLocal={...local,id:21480,description:'Hard Rock Caf&#233; &amp; SPA&nbsp;— &#x4E2D; &#X1F334;',address:'Kavakl&#305; Caddesi',roomTypes:'&lt;UL&gt;&lt;LI&gt;Стандарт &quot;A&quot;&lt;/LI&gt;&lt;LI&gt;O&apos;Brien&lt;/LI&gt;&lt;/UL&gt;',infrastructure:{beach:'Пляж&#160;рядом'},services:{free:'Wi-Fi &amp; SPA'},meals:{description:'Завтрак &#38; ужин'}};
const encodedBefore=JSON.stringify(encodedLocal);
api.hotelDetailsCache.set('21480',encodedLocal);
const encodedHtml=api.hotelMainHtml({id:21480,tours:[multi.tours[0]]});
assert.match(encodedHtml,/Hard Rock Café &amp; SPA — 中 🌴/,'decimal, hexadecimal and common named entities become readable plain text');
assert.match(encodedHtml,/Kavaklı Caddesi/,'numeric entities in address use the same text normalization');
assert.match(encodedHtml,/Стандарт &quot;A&quot; · O&#39;Brien/,'encoded room-list markup is normalized before output escaping');
assert.match(encodedHtml,/Пляж рядом[\s\S]*Wi-Fi &amp; SPA[\s\S]*Завтрак &amp; ужин/,'detail sections share the same entity normalization');
assert.equal(JSON.stringify(encodedLocal),encodedBefore,'text presentation never changes the stored local DTO');
const hostileLocal={...local,id:21481,description:'До &lt;script&gt;alert(2)&lt;/script&gt;&#60;img src=x onerror=alert(3)&#62; после',roomTypes:'&lt;style&gt;body{display:none}&lt;/style&gt;Стандарт'};
api.hotelDetailsCache.set('21481',hostileLocal);
const hostileHtml=api.hotelMainHtml({id:21481,tours:[multi.tours[0]]});
assert.match(hostileHtml,/class="hotel-description-summary">До после<\/p>/,'decoded markup goes through the existing plain-text cleanup');
assert.doesNotMatch(hostileHtml,/alert\([23]\)|onerror|display:none|&lt;(?:script|img|style)/i,'encoded active markup cannot execute or leak as visible tag noise');
const unknownLocalText={...local,id:21482,description:'Неизвестно &custom; &#0; &#xD800; &#1114112; &#xZZ; &amp;#233;'};
api.hotelDetailsCache.set('21482',unknownLocalText);
const unknownTextHtml=api.hotelMainHtml({id:21482,tours:[multi.tours[0]]});
assert.match(unknownTextHtml,/Неизвестно &amp;custom; &amp;#0; &amp;#xD800; &amp;#1114112; &amp;#xZZ; &amp;#233;/,'unknown or invalid entities remain verbatim and decoding is exactly one layer');
const sourceFacts={id:21477,name:'Source hotel',category:3,rating:2,region:{name:'Source region'},seaDistance:150,price:90000,tours:multi.tours};
const originalFacts=JSON.stringify(sourceFacts),facts=api.hotelFacts(sourceFacts);
assert.equal(facts.category,5);
assert.equal(facts.rating,4.8);
assert.equal(facts.region,local.region);
assert.equal(facts.seaDistance,150,'numeric sea distance remains the existing normalized offer fact');
assert.equal(facts.tours,sourceFacts.tours,'hotel facts preserve exact offer identity');
assert.equal(JSON.stringify(sourceFacts),originalFacts,'reading local facts never mutates supplier results');
const unknownLocal={...local,category:null,rating:null};
api.hotelDetailsCache.set('21477',unknownLocal);
const unknownHtml=api.hotelMainHtml(sourceFacts);
assert.doesNotMatch(unknownHtml,/stars-badge|Рейтинг /,'unknown local facts cannot display stale source stars or rating');
assert.equal(api.hotelFacts(sourceFacts).rating,null);
assert.equal(api.hotelFacts(sourceFacts).category,null);
api.hotelDetailsCache.set('21477',null);
assert.equal(api.hotelFacts(sourceFacts).category,3,'a missing local hotel retains the existing source facts');
api.hotelDetailsCache.set('21477',local);
const otherFacts={id:'other',category:4,rating:4.9,price:100000};
assert.equal(api.sorted([sourceFacts,otherFacts],'rating')[0],otherFacts,'rating order follows visible local facts and preserves object identity');
assert.equal(api.sorted([otherFacts,sourceFacts],'stars')[0],sourceFacts,'category order follows the displayed local category');
const inlineImage=api.hotelMainHtml({id:'fixture',name:'Fixture',picturelink:'data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22/%3E'});
assert.match(inlineImage,/class="hotel-photo hotel-gallery"/,'bounded image data URI fixtures retain the established card geometry');
const unsafeImage=api.hotelMainHtml({id:'unsafe',name:'Unsafe',picturelink:'javascript:alert(1)'});
assert.doesNotMatch(unsafeImage,/javascript:/,'non-image and executable URL schemes never reach image markup');

(async()=>{
  const retryHotel={...local,id:21478,name:'Отель после повтора'};
  let requests=0;
  window.V2RetryPolicy={
    shouldRetry(action,error,attempt){return action==='hotel_details'&&error.code==='HTTP_ERROR'&&error.status===503&&attempt===0;},
    delayFor(){return 0;},
  };
  window.fetch=async()=>{
    requests+=1;
    if(requests===1)return{ok:false,status:503,json:async()=>({})};
    return{ok:true,status:200,json:async()=>({ok:true,item:retryHotel})};
  };
  await api.loadHotelDetails({id:21478});
  assert.equal(requests,2,'one transient hotel-details failure uses the existing bounded retry policy');
  assert.equal(api.hotelDetailsCache.get('21478'),retryHotel,'successful retry hydrates the canonical local hotel cache');

  let missingRequests=0;
  window.fetch=async()=>{missingRequests+=1;return{ok:false,status:404,json:async()=>({})};};
  await api.loadHotelDetails({id:21479});
  assert.equal(missingRequests,1,'a terminal missing hotel is not retried');
  assert.equal(api.hotelDetailsCache.get('21479'),null,'a real 404 remains a terminal negative cache entry');
  console.log('SEARCH3_HOTEL_CARD_SUMMARY_OK collapsed_hotel_level=1 compact_exact_offer_rows=1 local_gallery_details=1 transient_details_retry=1 source_unchanged=1');
})().catch(error=>{console.error(error);process.exitCode=1;});
