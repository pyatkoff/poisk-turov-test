const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'v2/index.php'), 'utf8');
const redesign = fs.readFileSync(path.join(root, 'v2/search-redesign-v2.js'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'v2/ds2-search.css'), 'utf8');
const renderer = fs.readFileSync(path.join(root, 'v2/results-renderer-v5.js'), 'utf8');
const localFilters = fs.readFileSync(path.join(root, 'src/search3/behavior/results/local-hotel-filter.js'), 'utf8');
const priceCalendar = fs.readFileSync(path.join(root, 'v2/current-price-calendar-v1.js'), 'utf8');

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
assert.ok(renderer.includes("return tours.length===1?'1 вариант тура':tours.length>1?tourCountLabel(tours.length)+' для сравнения':'';"),
  'one loaded offer is described as a tour option, while multiple offers retain comparison wording');
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

{
  const listeners = new Map();
  let details = null, markup = '';
  const box = {
    hidden: true,
    querySelector(selector) { assert.equal(selector, 'details'); return details; },
    get innerHTML() { return markup; },
    set innerHTML(value) { markup = value; details = value.includes('<details') ? { open: value.includes('<details open>') } : null; }
  };
  const window = { addEventListener(name, fn) { listeners.set(name, fn); }, matchMedia() { return { matches: false }; } };
  const document = { getElementById(id) { assert.equal(id, 'currentPriceCalendar'); return box; }, body: { classList: { contains() { return true; } } }, addEventListener() {} };
  vm.runInNewContext(priceCalendar, { window, document, Intl });
  const api = window.V2CurrentPriceCalendar;
  const items = [{ tours: [{ date: '2099-09-01', price: 150000 }, { date: '2099-09-02', price: 140000 }, { date: '2099-09-03', price: 145000 }] }];
  listeners.get('v2:search-complete')({ detail: { items } });
  assert.equal(details.open, true);
  for (const open of [false, true]) {
    details.open = open;
    for (const filtered of [[], [{ tours: items[0].tours.slice(0, 1) }]]) {
      listeners.get('search3:local-results-filtered')({ detail: { items: filtered } });
      assert.equal(box.hidden, true);
      assert.equal(markup, '');
      listeners.get('search3:local-results-filtered')({ detail: { items } });
      assert.equal(details.open, open, `disclosure ${open} survives ${filtered.length ? 'one date' : 'zero dates'}`);
    }
    listeners.get('v2:search-continued')({ detail: { items } });
    assert.equal(details.open, open);
  }
  details.open = false;
  listeners.get('v2:search-started')({ detail: {} });
  listeners.get('v2:search-complete')({ detail: { items } });
  assert.equal(details.open, true, 'new search restores initial expanded contract');
  assert.equal(api.collect(items).length, 3);
}
assert.ok(priceCalendar.includes("head=compact?'summary':'div'"),
  'Search3 keeps one native disclosure owner instead of creating a second mobile calendar UI');
assert.ok(priceCalendar.includes('window.V2CurrentPriceCalendar={collect,render,clear,dateValue,version:3}'),
  'price calendar public presentation contract is versioned with the Search3 visibility change');

for (const forbidden of ['fetch(', 'XMLHttpRequest', 'V2SearchLifecycle', 'startSearch(']) {
  assert.ok(!localFilters.includes(forbidden), `local result facets do not start supplier transport: ${forbidden}`);
}

console.log('PASS: Search3 exposes honest result controls and an immediately visible current-price calendar');