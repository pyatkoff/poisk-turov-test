/* Supplier placeholder display regression; no browser, API or lead transport. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const start = source.indexOf('/* Shared presentation of supplier flight details.');
const end = source.indexOf('/* donor:search3-booking-summary.js', start);
assert.ok(start >= 0 && end > start, 'shared presentation helper exists');
const context = { window: {} };
vm.runInNewContext(source.slice(start, end), context);
const helper = context.window.Search3FlightPresentation;
const segment = (changes = {}) => ({
  company: { name: 'Аэрофлот' }, number: 'SU000',
  departure: { time: '00:00' }, arrival: { time: '00:00' },
  baggage: 0, carryOn: '', ...changes
});
const variant = (forward, backward = []) => ({ forward, backward });
const placeholder = variant([segment()], [segment()]);
const unchanged = JSON.stringify(placeholder);
assert.equal(helper.flightLabel(placeholder), 'Аэрофлот рейс уточняется');
assert.equal(helper.baggage(placeholder), 'багаж уточняется · ручная кладь уточняется');
assert.equal(JSON.stringify(placeholder), unchanged, 'raw supplier fields remain unchanged');

const actual = variant([segment({ number: 'SU2130', departure: { time: '00:00' }, arrival: { time: '03:40' }, baggage: 20, carryOn: '5 кг' })]);
assert.equal(helper.flightLabel(actual), 'Аэрофлот SU2130');
assert.equal(helper.baggage(actual), 'багаж 20 кг · ручная кладь 5 кг');
assert.equal(helper.placeholder(segment({ number: 'SU1000', arrival: { time: '03:40' } })), false, '000 suffix alone cannot invalidate a flight');
assert.equal(helper.placeholder(segment({ number: 'SU2130' })), false, 'midnight values alone cannot invalidate a flight');
const noBag = variant([segment({ number: 'SU2130', baggage: '0', carryOn: '5 кг' })]);
assert.equal(helper.baggage(noBag), 'без багажа · ручная кладь 5 кг', 'confirmed real zero baggage differs from unknown');
const mixed = variant(actual.forward, placeholder.backward);
assert.equal(helper.flightLabel(mixed), 'Аэрофлот SU2130 · Аэрофлот рейс уточняется');
assert.equal(helper.flightLabel(null), 'Рейс уточняется');
assert.equal(helper.flightLabel(variant([])), 'Рейс уточняется', 'empty segment arrays do not assert a known flight');
assert.equal(helper.baggage(variant([segment({ baggage: null, carryOn: 0 })])), 'багаж уточняется · ручная кладь уточняется');
console.log('PASS: placeholder/real/mixed flight and baggage presentation; raw supplier fields preserved');
