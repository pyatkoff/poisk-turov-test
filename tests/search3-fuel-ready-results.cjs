const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const window = {};
const document = {
  readyState: 'loading',
  addEventListener() {},
  querySelector() { return null; }
};
const source = fs.readFileSync(path.join(__dirname, '../v2/results-renderer-v5.js'), 'utf8');
vm.runInNewContext(source, { window, document, console, Number, Object, Array, Set, Map, Intl, URL });

const results = window.V2Results;
assert.equal(typeof results.customerPriceReady, 'function');
assert.equal(typeof results.customerReadyItems, 'function');

const readyZero = Object.freeze({ id: 'ready-zero', price: 120000, fuelCharge: 0 });
const readyObjectZero = Object.freeze({ id: 'ready-object-zero', price: 121000, fuelCharge: Object.freeze({ value: 0 }) });
const readyPositive = Object.freeze({ id: 'ready-positive', price: 122000, fuelCharge: Object.freeze({ value: 3500 }) });
for (const tour of [readyZero, readyObjectZero, readyPositive]) assert.equal(results.customerPriceReady(tour), true, tour.id);
for (const tour of [
  { id: 'missing', price: 100000 },
  { id: 'null', price: 100000, fuelCharge: null },
  { id: 'empty', price: 100000, fuelCharge: '' },
  { id: 'negative', price: 100000, fuelCharge: -1 },
  { id: 'invalid', price: 100000, fuelCharge: { value: 'unknown' } },
  { id: 'unpriced', price: 0, fuelCharge: 0 }
]) assert.equal(results.customerPriceReady(tour), false, tour.id);

const invalidMinimum = Object.freeze({ id: 'invalid-minimum', price: 50000 });
const mixedTours = Object.freeze([invalidMinimum, readyPositive, readyZero]);
const mixedHotel = Object.freeze({ id: 'mixed', price: 50000, tours: mixedTours });
const invalidHotel = Object.freeze({ id: 'invalid-only', price: 40000, tours: Object.freeze([{ id: 'invalid-only-tour', price: 40000 }]) });
const projected = results.customerReadyItems([mixedHotel, invalidHotel]);

assert.equal(projected.length, 1, 'a hotel with no surcharge-ready offers is absent');
assert.equal(projected[0].id, 'mixed');
assert.equal(projected[0].price, 120000, 'hotel minimum is recomputed only from eligible offers');
assert.deepEqual(Array.from(projected[0].tours, tour => tour.id), ['ready-positive', 'ready-zero'], 'ineligible offers cannot affect counts, order or downstream facets');
assert.equal(mixedHotel.price, 50000, 'the original hotel remains unchanged');
assert.equal(mixedHotel.tours, mixedTours, 'the original offer collection remains unchanged');

for (const file of ['search-lifecycle-v6.js', 'search-continue-v6.js', 'search-progress-ux-v1.js']) {
  const code = fs.readFileSync(path.join(__dirname, '../v2', file), 'utf8');
  assert.match(code, /(?:const items=renderer\.render|const items=render\(all\))/, file + ' forwards the filtered items to completion/continuation consumers');
}
const lifecycle = fs.readFileSync(path.join(__dirname, '../v2/search-lifecycle-v6.js'), 'utf8');
assert.doesNotMatch(lifecycle, /s\.minPrice/, 'unverified search-status minimum is not customer-facing');

console.log('Search3 fuel-ready results contract passed');
