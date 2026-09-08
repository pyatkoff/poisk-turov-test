/* Booking formatter retirement: protected controller remains the sole fact renderer. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const executable = value => value.replace(/\/\*[\s\S]*?\*\//g, '').trim();

for (const name of [
  'src/search3/behavior/booking/format.js',
  'src/search3/behavior/flight-presentation.js',
  'src/search3/behavior/presentation-text.js'
]) assert.equal(executable(read(name)), '', name + ' is provenance only');

const controller = read('v2/tour-controller-v4.js');
assert.match(controller, /function esc\(v\)/, 'controller keeps escaped selected facts');
assert.match(controller, /function displayText\(v\)/, 'controller keeps structured supplier text');
assert.match(controller, /class="selected-price"/, 'controller keeps the live selected price');
assert.match(controller, /class="tour-flights"/, 'controller keeps canonical flight facts');
assert.match(controller, /form class="lead-form"/, 'controller keeps the canonical lead form');
assert.ok(!read('v2/search3-results-filters-v1.js').includes('supplierFlightLabel'));
assert.ok(!fs.existsSync(path.join(root, 'src/search3/behavior/booking/services.js')), 'duplicate services renderer remains retired');
console.log('PASS: duplicate booking format retired; canonical controller owns escaped facts, price, flights and lead form');
