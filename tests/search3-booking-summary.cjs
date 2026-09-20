/* Duplicate booking card is absent; canonical price and lead owners remain. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');

assert.ok(!fs.existsSync(path.join(root, 'src/search3/behavior/booking-summary.js')), 'booking summary provenance stub remains retired');
const bundle = read('v2/search3-results-filters-v1.js');
for (const marker of ['Search3BookingSummary', 'search3-booking-summary', 'Перед оплатой менеджер подтвердит']) {
  assert.ok(!bundle.includes(marker), 'duplicate summary marker stays absent: ' + marker);
}
const price = read('v2/flight-price-sync-v1.js');
assert.match(price, /function valueOfPrice\(v\)/, 'canonical price owner retains numeric extraction');
assert.match(price, /new CustomEvent\('v2:tour-price-updated'/, 'canonical price owner retains update event');
assert.match(price, /Стоимость с выбранным рейсом/, 'selected price keeps confirmed flight total');
assert.match(price, /function confirmedVariantChoice\(index\)/, 'canonical flight owner distinguishes confirmed details from placeholders');
assert.match(price, /Рейс и время уточнит менеджер/, 'placeholder flight never claims a confirmed selected flight');
assert.match(price, /return value\?money\(value\)\+' ₽':'без доплаты'/, 'selected flight fee distinguishes explicit zero from a missing fee');
const controller = read('v2/tour-controller-v4.js');
assert.match(controller, /leadPayload\(new FormData\(form\)\)/, 'canonical controller retains lead payload');
const lead = read('v2/lead-form-guard-v1.js');
assert.match(lead, /function directText\(node\)/, 'shared lead summary retains its protected direct-text owner');
const summary = read('src/search3/behavior/summary-cta.js');
assert.match(summary, /function syncLeadFlight\(\)/, 'Search3 handoff enriches the protected shared summary');
assert.match(summary, /route=directText\(choice\.querySelector\('\.flight-choice-summary'\)\)/, 'Search3 handoff includes route and time but excludes tradeoffs');

class FixtureNode {
  constructor(tag) { this.tag = tag; this.children = []; this.attributes = {}; this.textContent = ''; this.className = ''; }
  append(...nodes) { this.children.push(...nodes); }
  appendChild(node) { this.children.push(node); return node; }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  remove() {}
  insertAdjacentElement(position, node) { assert.equal(position, 'afterend'); this.inserted = node; }
}
const document = { createElement(tag) { return new FixtureNode(tag); }, getElementById() { return null; } };
const window = { addEventListener() {} };
vm.runInNewContext(read('v2/selected-tour-description-v1.js'), { document, window, Intl, Number, String, Array, Object });
function selectedRating(rating) {
  const facts = new FixtureNode('div');
  const scope = { querySelector(selector) { return selector === '.facts' ? facts : null; } };
  window.V2SelectedTourDescription.decorateDecisionSummary(scope, {
    hotel: { category: 5, rating, seaDistance: 250 }, meal: 'AI', roomType: 'STANDARD'
  });
  const items = facts.inserted.children[1].children;
  return items.map(item => [item.children[0].textContent, item.children[1].textContent]);
}
for (const [rating, value] of [[4, '4 из 5'], [4.5, '4,5 из 5'], [5, '5 из 5']]) {
  assert.deepEqual(selectedRating(rating)[1], ['Рейтинг отеля', value],
    'selected-tour summary uses the same inclusive five-point scale as hotel cards');
}
for (const invalid of [undefined, null, 0, 5.1, 7, -1, Number.NaN]) {
  assert.equal(selectedRating(invalid).some(([label]) => label === 'Рейтинг отеля'), false,
    'out-of-scale selected rating is not reinterpreted');
}
console.log('PASS: duplicate booking card retired; canonical selected price and lead payload remain');
