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
    { id: 'b', price: 70100, date: '2026-09-16', nights: 10, meal: { name: 'AI', fullName: 'Всё включено' }, operator: { name: 'ANEX' }, isCharter: false },
    { id: 'c', price: 71500, date: '2026-09-16', nights: 9, meal: { name: 'AI', fullName: 'Всё включено' }, operator: { name: 'FUN&SUN' } },
  ],
};

assert.deepEqual(api.hotelSummary(multi), {
  date: '16.09.2026',
  nights: '7–10 ноч.',
  meal: 'Завтрак · Всё включено',
  operators: 'FUN&SUN · ANEX',
  flight: 'Возможны чартеры',
  party: '',
  count: 3,
});
assert.equal(api.priceContext(multi), '16.09.2026 · 7–10 ноч. · Завтрак · Всё включено');
const collapsed = api.toursHtml(multi);
assert.match(collapsed, /class="hotel-trip-summary"/);
assert.doesNotMatch(collapsed, /Доступные варианты/, 'collapsed hotel has one current aggregate summary');
assert.match(collapsed, /7–10 ноч\./);
assert.match(collapsed, /Завтрак · Всё включено/);
assert.match(collapsed, /data-operator-brand="funsun"/);
assert.match(collapsed, /data-operator-brand="anex"/);
assert.match(collapsed, /от 62(?:\s| )?400/);
assert.match(collapsed, /Показать варианты · 3/);
assert.doesNotMatch(collapsed, /Завтраки/);
assert.match(collapsed, /Возможны чартеры/);
assert.doesNotMatch(collapsed, />Чартер</, 'mixed offers cannot promise a charter on every variant');
assert.doesNotMatch(collapsed, /2 взрослых/);
assert.doesNotMatch(collapsed, /direct-tour/);

// The displayed minimum belongs to its own exact-price offers, never to a
// different, selectable offer elsewhere in the same hotel.
const checkNote = 'Минимальная цена требует проверки перед выбором';
const mixedNote = 'Часть вариантов по минимальной цене требует проверки';
const withMinimum = cheapest => ({ ...multi, tours: [cheapest, multi.tours[1]] });
for (const flags of [{ selectionEnabled: false }, { selection_enabled: false }, { provider: 'andromeda' }, { provider: 'ANDROMEDA', selectionEnabled: true }]) {
  const sample = withMinimum({ ...multi.tours[0], ...flags });
  const original = JSON.stringify(sample);
  const html = api.toursHtml(sample);
  assert.ok(html.includes(checkNote), 'collapsed minimum inherits the exact cheapest offer readiness');
  assert.match(html, /от 62(?:\s| )?400/);
  assert.doesNotMatch(html, /direct-tour/, 'a summary does not create a new select action');
  assert.equal(JSON.stringify(sample), original, 'readiness presentation preserves source offers and prices');
  assert.doesNotMatch(api.tourAction(sample.tours[0]), /direct-tour/, 'expanded action agrees with the summary');
}
const mixedMinimum = { ...multi, tours: [{ ...multi.tours[0], provider: 'andromeda' }, { ...multi.tours[1], price: multi.price }] };
assert.ok(api.toursHtml(mixedMinimum).includes(mixedNote), 'equal-price mixed readiness is not reported as uniformly blocked');
assert.ok(api.toursHtml({ ...mixedMinimum, tours: [...mixedMinimum.tours].reverse() }).includes(mixedNote), 'equal-price readiness does not depend on supplier order');
assert.doesNotMatch(api.toursHtml(withMinimum(multi.tours[0])), /tour-selection-note/, 'normal selectable minima receive no invented confirmation or extra warning');
assert.doesNotMatch(api.toursHtml({ ...multi, tours: [multi.tours[0], { ...multi.tours[1], selectionEnabled: false }] }), /tour-selection-note/, 'a blocked expensive offer does not mark the minimum blocked');
assert.match(api.toursHtml({ ...multi, price: 61000 }), /Условия минимальной цены уточняются/, 'unmatched summary price never borrows another offer readiness');
for (const price of [undefined, null, 0, -1, 'unknown']) {
  assert.match(api.toursHtml({ ...multi, price }), /Цена и условия уточняются/, 'unknown or invalid minimum stays explicitly unknown');
}
assert.match(api.toursHtml({ ...multi, price: '62400', tours: [{ ...multi.tours[0], selectionEnabled: false }, multi.tours[1]] }), /Минимальная цена требует проверки/, 'numeric supplier prices follow the existing renderer price normalization');

const allCharter = {
  ...multi,
  tours: multi.tours.map((tour, index) => ({ ...tour, id: 'charter-' + index, isCharter: true })),
};
assert.equal(api.hotelSummary(allCharter).flight, 'Чартер');
assert.match(api.toursHtml(allCharter), /Чартер/);

// Unknown or mixed package facts must not become a uniform hotel promise.
const noCharterFact = { ...multi, tours: multi.tours.map(({ isCharter, ...tour }) => tour) };
assert.equal(api.hotelSummary(noCharterFact).flight, 'Уточняется по варианту');
const family = { ...multi, tours: multi.tours.map(tour => ({ ...tour, adults: 2, childs: 1 })) };
assert.equal(api.hotelSummary(family).party, '2 взрослых · 1 ребёнок');
const mixedParty = { ...family, tours: [family.tours[0], { ...family.tours[1], childs: 0 }] };
assert.equal(api.hotelSummary(mixedParty).party, '', 'different placements are described only on their exact offer');
assert.doesNotMatch(api.toursHtml(mixedParty), /2 взрослых|1 ребёнок/);
assert.equal(api.hotelSummary({ ...multi, tours: [] }).count, 0);
assert.match(api.toursHtml({ ...multi, tours: [] }), /Нет доступных вариантов/);

const differentDates = {
  ...multi,
  tours: [multi.tours[0], { ...multi.tours[1], date: '2026-09-18' }],
};
assert.equal(api.hotelSummary(differentDates).date, 'Несколько дат вылета');

const equivalentMeals = {
  ...multi,
  tours: [
    { ...multi.tours[0], meal: { name: 'AI', fullName: 'Всё включено' } },
    { ...multi.tours[1], meal: { fullName: 'All Inclusive' } },
    { ...multi.tours[2], meal: 'Всё включено' },
  ],
};
assert.equal(api.hotelSummary(equivalentMeals).meal, 'Всё включено', 'supplier aliases do not invent several meal variants');
const distinctInclusiveMeals = {
  ...multi,
  tours: [
    { ...multi.tours[0], meal: { name: 'AI', fullName: 'Всё включено' } },
    { ...multi.tours[1], meal: { name: 'UAI', fullName: 'Ультра всё включено' } },
    { ...multi.tours[2], meal: { name: 'Soft AI', fullName: 'Мягкое всё включено' } },
  ],
};
assert.equal(api.hotelSummary(distinctInclusiveMeals).meal, '3 варианта питания', 'AI, UAI and Soft AI remain distinct choices');
const ambiguousMeals = {
  ...multi,
  tours: [
    { ...multi.tours[0], meal: 'Premium All Inclusive' },
    { ...multi.tours[1], meal: 'Not all inclusive' },
  ],
};
assert.equal(api.hotelSummary(ambiguousMeals).meal, 'Premium All Inclusive · Not all inclusive', 'custom and negated supplier labels remain separate in the hotel summary');
const equivalentUnknownLabels = {
  ...multi,
  tours: [
    { ...multi.tours[0], meal: 'Premium All Inclusive' },
    { ...multi.tours[1], meal: 'premium all inclusive' },
  ],
};
assert.equal(api.hotelSummary(equivalentUnknownLabels).meal, 'Premium All Inclusive', 'one unknown identity is deduplicated by its canonical key, not display casing');
const extendedBreakfast = {
  ...multi,
  tours: [
    { ...multi.tours[0], meal: 'Breakfast' },
    { ...multi.tours[1], meal: 'Breakfast and dinner' },
  ],
};
assert.equal(api.hotelSummary(extendedBreakfast).meal, 'Завтрак · Breakfast and dinner', 'an extended meal label does not collapse into the reviewed breakfast alias');

const single = { id: 'hotel-2', price: 90000, tours: [{ ...multi.tours[0], id: 'single', price: 90000 }] };
const singleHtml = api.toursHtml(single);
assert.match(singleHtml, /direct-tour/);
assert.match(singleHtml, /Завтраки/);
assert.doesNotMatch(singleHtml, /Доступные варианты/);

assert.equal(api.choiceHint(multi), '', 'the disclosure or expanded heading owns the multi-offer count, not the hotel identity header');
assert.equal(api.choiceHint(single), '1 вариант тура');
console.log('SEARCH3_HOTEL_CARD_SUMMARY_OK');
