/* Native-entry contract: canonical markup/lifecycle, no client DOM projection. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const executable = value => value.replace(/\/\*[\s\S]*?\*\//g, '').trim();
const bundle = read('v2/search3-results-filters-v1.js');
const formOwner = read('src/search3/behavior/search-form.js');
const markup = read('v2/index.php');
const catalogs = read('v2/catalogs-v2.js');
const lifecycle = read('v2/search-lifecycle-v6.js');

for (const part of ['primary-controls.js', 'secondary-controls.js']) {
  assert.equal(executable(read('src/search3/behavior/search-form/' + part)), '', part + ' is provenance only');
}
for (const marker of ['search3-price-calendar', 'search3-entry-summary-detail', 'search3-tourists__pop',
  'search3-tourists__summary', 'search3-mobile-search-filter-button', 'search3-primary-grid',
  'search3-composite', 'search3-quality', 'search3-quick']) {
  assert.ok(!bundle.includes(marker), `retired entry projection stays absent: ${marker}`);
}
assert.match(formOwner, /dataset\.search3Ready='1'/, 'compatibility ready marker remains');
for (const name of ['from', 'country', 'dateFrom', 'dateTo', 'daysFrom', 'daysTill',
  'count_people', 'child_count', 'child_age[]', 'food', 'price_from', 'price_till']) {
  assert.ok(markup.includes(`name="${name}"`), `canonical server field remains: ${name}`);
}
assert.match(catalogs, /function renderChildAges\(\)/, 'canonical child-age owner remains');
assert.match(lifecycle, /new FormData\(form\)/, 'canonical FormData owner remains');
assert.match(lifecycle, /hydrateUrlState\(\)/, 'canonical URL hydration remains');
console.log('PASS: native server form, catalog controls, URL hydration and FormData remain; client projection retired');
