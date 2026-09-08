/* Duplicate booking card is absent; canonical price and lead owners remain. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const executable = value => value.replace(/\/\*[\s\S]*?\*\//g, '').trim();

assert.equal(executable(read('src/search3/behavior/booking-summary.js')), '', 'booking summary is provenance only');
const bundle = read('v2/search3-results-filters-v1.js');
for (const marker of ['Search3BookingSummary', 'search3-booking-summary', 'Перед оплатой менеджер подтвердит']) {
  assert.ok(!bundle.includes(marker), 'duplicate summary marker stays absent: ' + marker);
}
const price = read('v2/flight-price-sync-v1.js');
assert.match(price, /function valueOfPrice\(v\)/, 'canonical price owner retains numeric extraction');
assert.match(price, /new CustomEvent\('v2:tour-price-updated'/, 'canonical price owner retains update event');
assert.match(price, /Стоимость с выбранным рейсом/, 'selected price keeps confirmed flight total');
const controller = read('v2/tour-controller-v4.js');
assert.match(controller, /leadPayload\(new FormData\(form\)\)/, 'canonical controller retains lead payload');
console.log('PASS: duplicate booking card retired; canonical selected price and lead payload remain');
