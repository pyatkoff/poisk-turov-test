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

assert.match(page, /<\?php if\(!v2_search3_enabled\(\)\):\?><strong>Предложения<\/strong><\?php endif;\?><span id="resultSummary">/,
  'Search3 uses the informative result count without a duplicate heading; legacy keeps its title');
assert.match(page, /id="resultsTripContext"[^>]*aria-label="Параметры поиска"[\s\S]*?data-search3-trip-route[\s\S]*?data-search3-trip-details/,
  'compact result header retains the canonical submitted route and trip details');

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
assert.ok(renderer.includes("return tours.length===1&&!h.andromedaExpansion?'1 вариант тура':'';"),
  'one complete offer keeps its label; grouped seeds and multi-offer counts belong to the common disclosure');
assert.ok(renderer.includes("'Показать варианты · '+tours.length") && renderer.includes("esc(tourCountLabel(tours.length))+'</strong>'"),
  'collapsed and expanded states each retain one grammatically correct loaded-offer count');
assert.match(
  page,
  /<\?php if\(!v2_search3_enabled\(\)\):\?><div class="results-view-switch"[\s\S]*?<\?php endif;\?>/,
  'list/grid controls are emitted only for the legacy presentation'
);

assert.ok(localFilters.includes('Туроператор') && localFilters.includes('Все туроператоры'),
  'tour operator remains the customer-facing source decision facet');
for (const technicalCopy of ['Источник предложения','Все источники','Источник: ','search3-provider-filter','providerSelect','providerKey']) {
  assert.equal(localFilters.includes(technicalCopy),false,
    `customer filters do not expose provider provenance or retain its obsolete handler: ${technicalCopy}`);
}

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
assert.ok(localFilters.includes('function fields(){return[field,regionField,categoryField,mealField,nightsField,flightField,budgetField,operatorField,ratingField,seaField];}'),
  'desktop and mobile share decision-first filter order without a provider/source control');
assert.ok(localFilters.includes('known/total>=minimum') && localFilters.includes('numericCoverage(ratings,.95)'),
  'guest-rating availability uses an explicit high-coverage policy instead of requiring every hotel to be complete');
assert.ok(localFilters.includes("'Рейтинг указан у '+r.k+' из '+r.t+' отелей'"),
  'a partially available rating facet discloses exact loaded-data coverage');

{
  const listeners = new Map();
  const strip = { scrollLeft: 0, scrollWidth: 300, clientWidth: 300, addEventListener() {} };
  const navigation = {
    hidden: true,
    contains() { return false; },
    querySelector(selector) {
      assert.ok(['[data-calendar-move="previous"]', '[data-calendar-move="next"]'].includes(selector));
      return { setAttribute(name, value) { assert.equal(name, 'aria-disabled'); assert.ok(['true', 'false'].includes(value)); } };
    }
  };
  const actions = { hidden: true };
  let details = null, markup = '';
  const box = {
    hidden: true,
    contains(node) { return node === details; },
    querySelector(selector) {
      if (selector === 'details') return details;
      if (selector === '.current-price-calendar__days') return markup.includes('current-price-calendar__days') ? strip : null;
      if (selector === '.current-price-calendar__actions') return markup.includes('current-price-calendar__actions') ? actions : null;
      if (selector === '.current-price-calendar__navigation') return markup.includes('current-price-calendar__navigation') ? navigation : null;
      assert.fail(`unexpected calendar selector: ${selector}`);
    },
    querySelectorAll(selector) { assert.equal(selector, '[data-calendar-date]'); return []; },
    get innerHTML() { return markup; },
    set innerHTML(value) { markup = value; details = value.includes('<details') ? { open: value.includes('<details open>'), addEventListener() {} } : null; }
  };
  const window = { addEventListener(name, fn) { listeners.set(name, fn); }, matchMedia() { return { matches: false }; } };
  const document = { activeElement: null, getElementById(id) { assert.equal(id, 'currentPriceCalendar'); return box; }, body: { classList: { contains() { return true; } } }, addEventListener() {} };
  vm.runInNewContext(priceCalendar, { window, document, Intl });
  const api = window.V2CurrentPriceCalendar;
  const items = [{ tours: [{ date: '2099-09-01', price: 150000 }, { date: '2099-09-02', price: 140000 }, { date: '2099-09-03', price: 145000 }] }];
  assert.equal(api.version, 5, 'DB-first calendar contract has an explicit version');
  listeners.get('v2:search-started')({ detail: {} });
  listeners.get('search3:local-results-filtered')({ detail: { items } });
  assert.equal(box.hidden, true, 'filtered projection alone does not reveal a pre-terminal calendar');
  listeners.get('v2:provider-status')({ detail: { provider: 'tourvisor', status: 'complete' } });
  assert.equal(box.hidden, true, 'a non-local provider cannot unlock the DB-first calendar');
  listeners.get('v2:provider-status')({ detail: { provider: 'local-db', status: 'complete' } });
  assert.equal(box.hidden, false, 'local DB completion reveals the already-rendered price dates before supplier terminal');
  assert.equal(details.open, true, 'early DB-first calendar uses the same disclosure contract');
  listeners.get('v2:search-reset')({ detail: {} });
  assert.equal(box.hidden, true, 'a new search hides the previous DB-first calendar');
  listeners.get('search3:local-results-filtered')({ detail: { items } });
  assert.equal(box.hidden, true, 'new filtered results wait for the new local DB completion');
  listeners.get('v2:provider-status')({ detail: { provider: 'local-db', status: 'loading' } });
  assert.equal(box.hidden, true, 'local DB loading does not unlock stale dates');
  listeners.get('v2:provider-status')({ detail: { provider: 'local-db', status: 'complete' } });
  assert.equal(box.hidden, false, 'the new local DB completion unlocks the new search calendar');
  listeners.get('v2:search-complete')({ detail: { items } });
  assert.equal(details.open, true);
  assert.equal(actions.hidden, true, 'confirmation stays hidden until the user chooses a date');
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
assert.ok(priceCalendar.includes('window.V2CurrentPriceCalendar={collect,render,clear,dateValue,version:5}'),
  'price calendar public presentation contract is versioned with DB-first visibility and explicit date confirmation');

for (const forbidden of ['fetch(', 'XMLHttpRequest', 'V2SearchLifecycle', 'startSearch(']) {
  assert.ok(!localFilters.includes(forbidden), `local result facets do not start supplier transport: ${forbidden}`);
}

{
  const mount = localFilters.slice(localFilters.indexOf('function mount(){'), localFilters.indexOf('function option('));
  let moves = 0;
  const container = () => ({ appendChild(node) { moves++; node.parentNode = this; }, append(node) { this.appendChild(node); } });
  const rail = container(), actions = container(), mobileBody = container();
  const controls = [{}, {}], activeList = {}, resetButton = {}, mobilePanel = { open: true };
  const context = { field: controls[0], desktop: { matches: false }, rail, actions, mobileBody, mobilePanel,
    activeList, resetButton, fields: () => controls, count: { textContent: '2' }, syncContainers() {} };
  vm.createContext(context);vm.runInContext(mount, context);
  const run = () => vm.runInContext('mount()', context);
  run();assert.equal(moves, 5, 'initial mobile mount attaches one panel and each control once');
  moves = 0;run();assert.equal(moves, 0, 'filter/progressive rerenders never detach already-mounted controls');
  assert.equal(mobilePanel.open, true, 'mobile disclosure stays open during local filtering');
  context.desktop.matches = true;run();assert.equal(moves, 4, 'breakpoint moves the existing controls to desktop exactly once');
  assert.equal(mobilePanel.open, false);
  moves = 0;run();assert.equal(moves, 0, 'desktop rerenders leave the focused subtree connected');
  context.desktop.matches = false;run();assert.equal(moves, 4, 'returning to mobile reuses the same controls and panel');
  assert.ok([activeList, ...controls, resetButton].every(node => node.parentNode === mobileBody));
}

{
  const source = localFilters.slice(localFilters.indexOf('function focusAfterReset('), localFilters.indexOf('function reset('));
  let focused = null;
  const node = (name, native = true) => ({ name, hidden: false, visible: true, attributes: {},
    focus() { if (this.visible) focused = this; }, getClientRects() { return this.visible ? [{}] : []; },
    matches() { return native; }, hasAttribute(key) { return key in this.attributes; },
    setAttribute(key, value) { this.attributes[key] = value; }, removeAttribute(key) { delete this.attributes[key]; },
    addEventListener(type, handler) { this.onBlur = handler; } });
  const input = node('hotel'), meal = node('meal'), title = node('title', false), results = node('results', false);
  const field = { hidden: true, querySelector: () => input }, mealField = { hidden: false, querySelector: () => meal };
  input.visible = false;
  const context = { input, field, results, fields: () => [field, mealField],
    cards: () => [{ hidden: false, querySelector: () => title }], requestAnimationFrame: fn => fn() };
  vm.createContext(context);vm.runInContext(source, context);
  const reset = empty => { context.trigger = { classList: { contains: () => empty } };vm.runInContext('focusAfterReset(trigger)', context); };
  reset(false);assert.equal(focused, meal, 'one-hotel panel reset focuses its visible meal control, never the hidden hotel input');
  assert.equal(meal.hasAttribute('tabindex'), false, 'native filter remains in the natural tab order');
  field.hidden = false;input.visible = true;
  reset(false);assert.equal(focused, input, 'multi-hotel reset retains the name-input target');
  reset(true);assert.equal(focused, title, 'empty-result reset retains the restored hotel target');
  assert.equal(title.attributes.tabindex, '-1');title.onBlur();assert.equal(title.hasAttribute('tabindex'), false);
  field.hidden = true;mealField.hidden = true;input.visible = false;meal.visible = false;context.cards = () => [];
  reset(false);assert.equal(focused, results, 'a disappearing facet set falls back to the result region');
}

console.log('PASS: Search3 exposes honest result controls and an immediately visible current-price calendar');
