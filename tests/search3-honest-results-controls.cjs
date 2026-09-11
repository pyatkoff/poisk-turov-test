const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'v2/index.php'), 'utf8');
const redesign = fs.readFileSync(path.join(root, 'v2/search-redesign-v2.js'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'v2/ds2-search.css'), 'utf8');
const renderer = fs.readFileSync(path.join(root, 'v2/results-renderer-v5.js'), 'utf8');
const localFilters = fs.readFileSync(path.join(root, 'src/search3/behavior/results/local-hotel-filter.js'), 'utf8');
const resultsStyles = fs.readFileSync(path.join(root, 'src/search3/styles/results-layout.css'), 'utf8');

const select = page.match(/<select id="sortResults">([\s\S]*?)<\/select>/);
assert.ok(select, 'results sort remains available');
assert.deepEqual(
  [...select[1].matchAll(/<option value="([^"]+)">/g)].map(match => match[1]),
  ['price', 'rating', 'stars'],
  'only implemented result sorting modes are offered'
);
assert.doesNotMatch(page, /Ближе к морю|data-results-map|results-map-button/);
assert.doesNotMatch(redesign, /results-map-requested|data-results-map/);
assert.doesNotMatch(styles, /results-map-button/);
assert.match(renderer, /if\(m==='rating'\)/);
assert.match(renderer, /if\(m==='stars'\)/);
assert.ok(renderer.includes("tours.length===1?'1 вариант тура'"),
  'single-offer hotels are not misleadingly described as a comparison set');
assert.ok(resultsStyles.includes('max-height:calc(100vh - 36px);overflow-y:auto;overscroll-behavior:contain'),
  'desktop filter rail remains reachable when the truthful facet set exceeds a short viewport');
assert.match(
  page,
  /<\?php if\(!v2_search3_enabled\(\)\):\?><div class="results-view-switch"[\s\S]*?<\?php endif;\?>/,
  'list/grid controls are emitted only for the legacy presentation'
);

assert.ok(localFilters.includes('Источник предложения') && localFilters.includes('Все источники'),
  'Search3 exposes provider/source as an already-loaded result facet');
assert.ok(localFilters.includes('Туроператор') && localFilters.includes('Все туроператоры'),
  'provider/source remains distinct from tour operator');
assert.match(localFilters, /function providerKey\(t\)\{const value=String\(t&&t\.provider\|\|'tourvisor'\)/,
  'local provider facet normalizes only retained offer source identity');
assert.match(localFilters, /key=providerKey\(t\),label=api\.providerName\(t\)/,
  'provider labels reuse canonical renderer display semantics');
assert.match(localFilters, /providerKey\(t\)===provider/,
  'provider selection intersects on the retained offer');
assert.match(localFilters, /providerSelect\.addEventListener\('change',\(\)=>window\.V2Results\.rerender\(\)\)/,
  'provider changes rerender already-loaded results locally');

assert.ok(localFilters.includes('Курорт / регион') && localFilters.includes('Все курорты'),
  'Search3 exposes a resort/region decision facet when loaded hotel geography is complete');
assert.ok(localFilters.includes("cardTextValues('region')"),
  'region choices come from canonical already-loaded hotel region data');
assert.ok(localFilters.includes('const available=list.length>1&&list.every(item=>item.key)&&labels.size>1;'),
  'region facet stays hidden when geography is incomplete or has no meaningful choice');
assert.ok(localFilters.includes('matchesRegion=!facets.region||facets.regions[index].key===facets.region'),
  'region selection filters only the current loaded hotel set');
assert.ok(localFilters.includes("regionSelect.addEventListener('change',apply)"),
  'region changes stay inside the local result filter owner');
assert.ok(localFilters.includes('function fields(){return[field,regionField,categoryField,mealField,budgetField,operatorField,providerField,ratingField,seaField];}'),
  'desktop and mobile share decision-first filter order: hotel, resort, stars, meal, budget, operator, source, rating, sea');

for (const forbidden of ['fetch(', 'XMLHttpRequest', 'V2SearchLifecycle', 'startSearch(']) {
  assert.ok(!localFilters.includes(forbidden), `local result facets do not start supplier transport: ${forbidden}`);
}

console.log('PASS: Search3 exposes honest sorting plus decision-first local provider/operator/region result facets');