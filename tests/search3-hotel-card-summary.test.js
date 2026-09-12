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
  meal: 'BB · AI',
  operators: 'FUN&SUN · ANEX',
  flight: '',
  count: 3,
});
assert.equal(api.priceContext(multi), '16.09.2026 · 7–10 ноч. · BB · AI');
const collapsed = api.toursHtml(multi);
assert.match(collapsed, /Доступные варианты/);
assert.match(collapsed, /7–10 ноч\./);
assert.match(collapsed, /BB · AI/);
assert.match(collapsed, /FUN&amp;SUN · ANEX/);
assert.match(collapsed, /от 62(?:\s| )?400/);
assert.match(collapsed, /Показать 3 варианта/);
assert.doesNotMatch(collapsed, /Завтраки/);
assert.doesNotMatch(collapsed, /Чартер/);
assert.doesNotMatch(collapsed, /2 взрослых/);
assert.doesNotMatch(collapsed, /direct-tour/);

const allCharter = {
  ...multi,
  tours: multi.tours.map((tour, index) => ({ ...tour, id: 'charter-' + index, isCharter: true })),
};
assert.equal(api.hotelSummary(allCharter).flight, 'Чартер');
assert.match(api.toursHtml(allCharter), /Чартер/);

const differentDates = {
  ...multi,
  tours: [multi.tours[0], { ...multi.tours[1], date: '2026-09-18' }],
};
assert.equal(api.hotelSummary(differentDates).date, 'Несколько дат вылета');

const single = { id: 'hotel-2', price: 90000, tours: [{ ...multi.tours[0], id: 'single', price: 90000 }] };
const singleHtml = api.toursHtml(single);
assert.match(singleHtml, /direct-tour/);
assert.match(singleHtml, /Завтраки/);
assert.doesNotMatch(singleHtml, /Доступные варианты/);

assert.equal(api.choiceHint(multi), '3 варианта для сравнения');
assert.equal(api.choiceHint(single), '1 вариант тура');
console.log('SEARCH3_HOTEL_CARD_SUMMARY_OK');
