/* Execute the shipped DS2 desktop owner plus the compiled Search3 empty-result bridge. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const bundledIife = require('./search3-bundle-iife.cjs');

const listeners = new Map();
const railListeners = new Map();
const formListeners = new Map();
const fields = Object.fromEntries(['meal', 'stars', 'rating', 'sea'].map(name =>
  [name, { hidden: true, ariaHidden: 'true', setAttribute(key, value) { if (key === 'aria-hidden') this.ariaHidden = value; } }]));
const inputs = {
  'ds2-meal': ['', 'ai', 'hb'], 'ds2-stars': ['0', '5', '4', '3'],
  'ds2-rating': ['0', '4.5', '4'], 'ds2-sea': ['0', '200', '500', '1000']
};
const choices = Object.fromEntries(Object.entries(inputs).map(([name, values]) =>
  [name, values.map(value => ({ name, value, checked: value === '0' || value === '', matches(s) { return s === '[data-ds2-choice]'; } }))]));
const price = { min: '', max: '', value: '250000', matches(s) { return s === '[data-ds2-price]'; } };
const priceLabel = { textContent: '' }, count = { textContent: '' }, word = { textContent: '' };
const rail = {
  dataset: {}, innerHTML: '',
  addEventListener(name, fn) { railListeners.set(name, fn); },
  querySelector(selector) {
    return ({ '[data-ds2-price]': price, '[data-ds2-price-label]': priceLabel,
      '[data-ds2-filter-count]': count, '[data-ds2-filter-word]': word,
      '[data-ds2-meal-fieldset]': fields.meal, '[data-ds2-stars-fieldset]': fields.stars,
      '[data-ds2-rating-fieldset]': fields.rating, '[data-ds2-sea-fieldset]': fields.sea })[selector] || null;
  },
  querySelectorAll(selector) {
    if (selector === 'input[data-ds2-choice]') return Object.values(choices).flat();
    const match = selector.match(/^input\[name="([^"]+)"\]$/);
    return match ? choices[match[1]] || [] : [];
  }
};
const results = {}, summary = { textContent: '' }, heading = { textContent: '' };
const formValues = { stars: '', rating: '', price_from: '', price_till: '', region: '', subregion: '' };
const form = {
  elements: { subregion: { value: formValues.subregion } },
  addEventListener(name, fn, capture) { assert.equal(capture, true); formListeners.set(name, fn); }
};
class FormData {
  get(name) { return name === 'subregion' ? form.elements.subregion.value : formValues[name] || ''; }
}
let catalogChanges = 0;
const window = {
  matchMedia() { return { matches: true }; },
  addEventListener(name, fn) { const group = listeners.get(name) || []; group.push(fn); listeners.set(name, group); },
  dispatchEvent(event) { emit(event.type, event.detail); },
  V2SearchLifecycle: { snapshot: {} },
  V2Catalogs: { handleChange() { catalogChanges += 1; } }
};
function emit(name, detail) { for (const fn of listeners.get(name) || []) fn({ detail }); }
const renders = [];
window.V2Results = { render(items) { renders.push(items); emit('v2:results-rendered', { items }); } };
const document = {
  querySelector(selector) { return selector === '.results-filter-rail' ? rail : selector === '#resultsTools strong' ? heading : null; },
  getElementById(id) { return ({ tourSearch: form, results, resultSummary: summary })[id] || null; },
  body: { classList: { contains(name) { return name === 'search3-candidate'; } } }
};
const context = { window, document, FormData, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } }, Intl, Number, Object, Array, Math, String, Promise };
const root = path.join(__dirname, '..');
vm.runInNewContext(fs.readFileSync(path.join(root, 'v2/ds2-results-filters.js'), 'utf8'), context);
const searchBundle = fs.readFileSync(path.join(root, 'v2/search3-results-filters-v1.js'), 'utf8');
vm.runInNewContext(bundledIife(searchBundle, { literal: '.results-filter-rail' }), context);

const hotels = [
  { id: 'four', category: 4, rating: 4.2, seaDistance: 400, region: { id: 9 }, subRegion: { id: 5 }, price: 90000,
    tours: [{ price: 90000, meal: { name: 'AI' } }] },
  { id: 'three', category: 3, rating: 3.8, seaDistance: 800, region: { id: 8 }, subRegion: { id: 6 }, price: 120000,
    tours: [{ price: 120000, meal: { name: 'HB' } }] }
];
emit('v2:results-rendered', { items: hotels });
assert.deepEqual(Object.values(fields).map(field => field.hidden), [false, false, false, false],
  'complete source exposes all trustworthy DS2 facets');
assert.equal(price.min, '90000');
assert.equal(price.max, '120000');

function changeForm(name, value) {
  if (name === 'subregion') form.elements.subregion.value = value;
  else formValues[name] = value;
  let stopped = 0;
  const target = { name, removeAttribute() {}, matches() { return true; } };
  formListeners.get('change')({ target, stopImmediatePropagation() { stopped += 1; } });
  return stopped;
}
assert.equal(changeForm('stars', '4'), 1, 'narrower form category stays local');
assert.deepEqual(renders.at(-1).map(hotel => hotel.id), ['four']);
assert.equal(summary.textContent, 'Подходит 1 из 2 отелей');
assert.equal(changeForm('stars', ''), 1, 'clearing a locally added category restores the supplier source');
assert.equal(renders.at(-1).length, 2);
form.elements.subregion.value = '5';
assert.equal(changeForm('region', '9'), 1, 'region narrowing stays local');
assert.equal(form.elements.subregion.value, '', 'region change clears the dependent subregion');
assert.equal(catalogChanges, 1, 'region change refreshes dependent catalogs once');
assert.deepEqual(renders.at(-1).map(hotel => hotel.id), ['four']);
formValues.region = '';
assert.equal(changeForm('price_from', '100000'), 1, 'price narrowing prunes tours locally');
assert.deepEqual(renders.at(-1).map(hotel => hotel.id), ['three']);
assert.equal(renders.at(-1)[0].price, 120000, 'hotel price is recalculated from retained tours');
window.V2SearchLifecycle.snapshot = { priceTo: 100000 };
const renderCount = renders.length;
assert.equal(changeForm('price_till', '120000'), 0, 'broadening beyond the supplier snapshot reaches lifecycle');
assert.equal(renders.length, renderCount, 'unsafe broadening does not render a partial local result');
window.V2SearchLifecycle.snapshot = {};
formValues.price_till = '';
assert.equal(changeForm('price_from', ''), 1, 'clearing the last local form filter restores the supplier source');
assert.equal(renders.at(-1).length, 2);

function choose(name, value) {
  const target = choices[name].find(input => input.value === value);
  target.checked = true;
  railListeners.get('change')({ target });
}
choose('ds2-stars', '5');
assert.equal(renders.at(-1).length, 0, '5-star filter can produce a legitimate empty result');
assert.equal(rail.dataset.s3EmptyResults, '1', 'Search3 keeps the shell for a DS2 zero-match render');
assert.equal(heading.textContent, 'Ничего не найдено', 'zero matches use an explicit visible heading');
assert.equal(summary.textContent, 'Сбросьте фильтры или измените параметры', 'zero matches explain how to recover');
choose('ds2-stars', '0');
assert.equal(renders.at(-1).length, 2, 'Any category restores the original hotels');
assert.equal(rail.dataset.s3EmptyResults, '', 'restored results clear the bridge marker');
assert.equal(heading.textContent, 'Найдено 2 отеля', 'recovered results restore the visible count');

choose('ds2-meal', 'ai');
assert.equal(renders.at(-1).length, 1, 'meal filtering uses the loaded result data');
assert.equal(renders.at(-1)[0].id, 'four');
choose('ds2-sea', '500');
assert.equal(renders.at(-1).length, 1, 'sea-distance filtering remains instant and local');

emit('v2:results-rendered', { items: [{ id: 'partial', category: 0, rating: 0, seaDistance: 0, tours: [] }] });
assert.deepEqual(Object.values(fields).map(field => field.hidden), [true, true, true, true],
  'partial data hides facets instead of filtering silently');
emit('v2:search-reset', {});
assert.equal(rail.dataset.s3EmptyResults, '', 'search reset clears the Search3 empty marker');
assert.match(rail.innerHTML, /role="status" aria-live="polite" aria-atomic="true"/, 'filter count announces local changes without taking focus');
assert.equal(window.DS2ResultsFilters.version, 15);

console.log('PASS: DS2 desktop facets filter, recover, reset and preserve the Search3 empty shell');
