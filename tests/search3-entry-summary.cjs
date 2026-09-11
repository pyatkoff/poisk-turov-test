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
assert.match(markup, /\$advancedFilterCount=0;/, 'server entry owns the advanced-filter count');
for (const name of ['arrival', 'region', 'subregion', 'hotel', 'operator', 'hotel_type', 'stars', 'rating', 'food', 'price_from', 'price_till', 'hotel_service']) {
  assert.ok(markup.includes(`'${name}'`), `advanced summary includes ${name}`);
}
assert.match(markup, /\['onlyDirect','only_direct'\],\['onlyCharter','only_charter'\]/, 'direct and charter aliases count once');
assert.match(markup, /\['1','true','yes'\]/, 'false-ish flight flags stay inactive');
assert.match(markup, /Активных фильтров: /, 'Search3 exposes a truthful active-filter count');
assert.match(markup, /курорт, отель, питание, цена и перелёт/, 'Search3 default hint advertises the available price filter');
assert.match(markup, /v2_search3_enabled\(\)\?'Вылет с':'С'/, 'Search3 start-date label is explicit while legacy stays unchanged');
assert.match(markup, /v2_search3_enabled\(\)\?'Вылет до':'По'/, 'Search3 end-date label is explicit while legacy stays unchanged');
assert.match(markup, /v2_search3_enabled\(\)\?e\(\$advancedFilterHint\):'курорт, отель, питание и перелёт'/, 'legacy V2 keeps its previous generic summary');
assert.match(markup, /v2_search3_enabled\(\)\):\?><section class="v2-product-hero v2-visually-hidden"/, 'Search3 keeps one semantic hero without redundant first-view chrome');
assert.match(markup, /<\?php else:\?><section class="v2-product-hero"/, 'legacy V2 keeps its visible product hero');
assert.match(catalogs, /function renderChildAges\(\)/, 'canonical child-age owner remains');
assert.match(lifecycle, /new FormData\(form\)/, 'canonical FormData owner remains');
assert.match(lifecycle, /hydrateUrlState\(\)/, 'canonical URL hydration remains');
console.log('PASS: native server form, compact Search3 hero, explicit date labels, active advanced-filter summary, catalog controls, URL hydration and FormData remain; client projection retired');
