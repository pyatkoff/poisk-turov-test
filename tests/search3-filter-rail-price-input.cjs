/* Execute the shipped DS2 desktop owner plus the compiled Search3 empty-result bridge. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const bundledIife = require('./search3-bundle-iife.cjs');

const listeners = new Map();
const railListeners = new Map();
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
const window = {
  matchMedia() { return { matches: true }; },
  addEventListener(name, fn) { const group = listeners.get(name) || []; group.push(fn); listeners.set(name, group); }
};
function emit(name, detail) { for (const fn of listeners.get(name) || []) fn({ detail }); }
const renders = [];
window.V2Results = { render(items) { renders.push(items); emit('v2:results-rendered', { items }); } };
const document = {
  querySelector(selector) { return selector === '.results-filter-rail' ? rail : selector === '#resultsTools strong' ? heading : null; },
  getElementById(id) { return ({ results, resultSummary: summary })[id] || null; }
};
const context = { window, document, Intl, Number, Object, Array, Math, String };
const root = path.join(__dirname, '..');
vm.runInNewContext(fs.readFileSync(path.join(root, 'v2/ds2-results-filters.js'), 'utf8'), context);
const searchBundle = fs.readFileSync(path.join(root, 'v2/search3-results-filters-v1.js'), 'utf8');
vm.runInNewContext(bundledIife(searchBundle, { literal: '.results-filter-rail' }), context);

const hotels = [
  { id: 'four', category: 4, rating: 4.2, seaDistance: 400, price: 90000,
    tours: [{ price: 90000, meal: { name: 'AI' } }] },
  { id: 'three', category: 3, rating: 3.8, seaDistance: 800, price: 120000,
    tours: [{ price: 120000, meal: { name: 'HB' } }] }
];
emit('v2:results-rendered', { items: hotels });
assert.deepEqual(Object.values(fields).map(field => field.hidden), [false, false, false, false],
  'complete source exposes all trustworthy DS2 facets');
assert.equal(price.min, '90000');
assert.equal(price.max, '120000');

function choose(name, value) {
  const target = choices[name].find(input => input.value === value);
  target.checked = true;
  railListeners.get('change')({ target });
}
choose('ds2-stars', '5');
assert.equal(renders.at(-1).length, 0, '5-star filter can produce a legitimate empty result');
assert.equal(rail.dataset.s3EmptyResults, '1', 'Search3 keeps the shell for a DS2 zero-match render');
choose('ds2-stars', '0');
assert.equal(renders.at(-1).length, 2, 'Any category restores the original hotels');
assert.equal(rail.dataset.s3EmptyResults, '', 'restored results clear the bridge marker');

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
assert.equal(window.DS2ResultsFilters.version, 13);

console.log('PASS: DS2 desktop facets filter, recover, reset and preserve the Search3 empty shell');
