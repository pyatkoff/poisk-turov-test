/* Native-entry contract: canonical markup/lifecycle, no client DOM projection. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const bundle = read('v2/search3-results-filters-v1.js');
const formOwner = read('src/search3/behavior/search-form.js');
const markup = read('v2/index.php');
const catalogs = read('v2/catalogs-v2.js');
const lifecycle = read('v2/search-lifecycle-v6.js');

for (const marker of ['search3-price-calendar', 'search3-entry-summary-detail', 'search3-tourists__pop',
  'search3-tourists__summary', 'search3-mobile-search-filter-button', 'search3-primary-grid',
  'search3-composite', 'search3-quality', 'search3-quick']) {
  assert.ok(!bundle.includes(marker), `retired entry projection stays absent: ${marker}`);
}
assert.match(formOwner, /dataset\.search3Ready='1'/, 'compatibility ready marker remains');
assert.match(formOwner, /dataset\.search3View=open\?'editor':'summary'/, 'one canonical form owner switches between results summary and editor states');
assert.match(formOwner, /setAttribute\('aria-expanded'/, 'the results edit action exposes the editor state');
assert.match(read('src/search3/styles/entry-native-controls.css'), /#tourSearch\[data-search3-view=summary\]\{display:none\}/, 'populated results can collapse the full editor without deleting it');
for (const name of ['from', 'country', 'dateFrom', 'dateTo', 'daysFrom', 'daysTill',
  'count_people', 'child_count', 'child_age[]', 'region', 'subregion', 'hotel', 'stars', 'rating', 'food',
  'operator', 'price_from', 'price_till']) {
  assert.ok(markup.includes(`name="${name}"`), `canonical server field remains: ${name}`);
}
assert.match(markup, /search-section-title--preferences"><span>Отель и условия<\/span>/, 'full search separates hotel preferences from trip basics without another form owner');
const partyStart = markup.indexOf('<fieldset class="search-group search-group--party">');
const partyEnd = markup.indexOf('</fieldset>', partyStart);
const childAgesStart = markup.indexOf('<div id="childAges"', partyStart);
assert.ok(partyStart > 0 && partyEnd > partyStart && childAgesStart > partyStart && childAgesStart < partyEnd,
  'child ages stay inside the canonical tourist group');
const primaryStart = markup.indexOf('<div class="search-preferences">');
const extrasStart = markup.indexOf('<details class="extras">', primaryStart);
assert.ok(primaryStart > 0 && extrasStart > primaryStart, 'primary preference grid remains bounded before extras');
const primaryMarkup = markup.slice(primaryStart, extrasStart);
for (const name of ['region', 'hotel', 'stars', 'food', 'price_from', 'price_till']) {
  assert.ok(primaryMarkup.includes(`name="${name}"`), `primary preference remains directly visible: ${name}`);
}
for (const name of ['subregion', 'rating', 'operator']) {
  assert.ok(!primaryMarkup.includes(`name="${name}"`), `secondary field is not promoted into the primary grid: ${name}`);
  assert.match(markup, new RegExp(`<details class="extras">[\\s\\S]*?name="${name}"`), `secondary field remains available under extras: ${name}`);
}
assert.match(markup, /<details class="extras">[\s\S]*?<select name="operator">[\s\S]*?<\/details>/, 'tour operator stays secondary inside the existing extras owner');
assert.match(markup, /\$advancedFilterCount=0;/, 'server entry owns the secondary-filter count');
for (const name of ['subregion', 'rating', 'arrival', 'operator', 'hotel_type', 'hotel_service']) {
  assert.ok(markup.includes(`'${name}'`), `secondary summary includes ${name}`);
}
assert.match(markup, /\['onlyDirect','only_direct'\],\['onlyCharter','only_charter'\]/, 'direct and charter aliases count once');
assert.match(markup, /\['1','true','yes'\]/, 'false-ish flight flags stay inactive');
assert.match(markup, /Активных дополнительных: /, 'Search3 exposes a truthful secondary-filter count');
assert.match(markup, /район, рейтинг, аэропорт, туроператор, тип отеля, перелёт и услуги/, 'Search3 default hint advertises the remaining secondary controls');
assert.match(markup, /v2_search3_enabled\(\)\?'Ещё фильтры':'Фильтры отдыха'/, 'Search3 labels the disclosure as secondary rather than hiding core search parameters');
assert.match(markup, /v2_search3_enabled\(\)\?'Вылет с':'С'/, 'Search3 start-date label is explicit while legacy stays unchanged');
assert.match(markup, /v2_search3_enabled\(\)\?'Вылет до':'По'/, 'Search3 end-date label is explicit while legacy stays unchanged');
assert.match(markup, /v2_search3_enabled\(\)\):\?><section class="v2-product-hero v2-visually-hidden"/, 'Search3 keeps one semantic hero without redundant first-view chrome');
assert.match(markup, /<\?php else:\?><section class="v2-product-hero"/, 'legacy V2 keeps its visible product hero');
assert.match(catalogs, /function renderChildAges\(\)/, 'canonical child-age owner remains');
assert.match(lifecycle, /new FormData\(form\)/, 'canonical FormData owner remains');
assert.match(lifecycle, /hydrateUrlState\(\)/, 'canonical URL hydration remains');

// Execute the actual form owner through the lifecycle events that preserve the
// submitted query while the user edits a draft or returns from a selected tour.
const listeners = new Map(), clicks = new Map();
const route = { textContent: '' }, details = { textContent: '' };
const trip = { hidden: true, querySelector: selector => selector === '[data-search3-trip-route]' ? route : details };
let hotelsPresent = false, snapshot = null, focused = false;
const field = options => ({ value: options[0][0], options: options.map(([value, textContent]) => ({ value, textContent })) });
const form = {
  dataset: {}, elements: {
    from: field([['1', 'Москва'], ['2', 'Санкт-Петербург']]),
    country: field([['4', 'Турция'], ['1', 'Египет']])
  }, querySelectorAll: () => [], scrollIntoView() {}
};
form.elements.from.focus = () => { focused = true; };
const results = { querySelector: () => hotelsPresent ? {} : null, setAttribute() {} };
const edit = { setAttribute() {} };
const nodes = { tourSearch: form, results, resultsSearchEdit: edit, resultsTripContext: trip };
const context = {
  document: { getElementById: id => nodes[id] || null, addEventListener: (name, handler) => clicks.set(name, handler) },
  window: { addEventListener: (name, handler) => listeners.set(name, handler), V2SearchLifecycle: { get snapshot() { return snapshot; } } }
};
vm.runInNewContext(formOwner, context);
const emit = (name, detail = {}) => listeners.get(name)?.({ detail });
const first = { departureId: '1', countryId: '4', dateFrom: '2026-10-02', dateTo: '2026-10-08', nightsFrom: '7', nightsTo: '10', adults: '2', childs: [0, 5] };
snapshot = first;
emit('v2:search-reset');
assert.equal(trip.hidden, true, 'pre-request reset never publishes an unconfirmed query');
assert.equal(details.textContent, '', 'submitted context is accepted only after search-start success');
// Matching submitted option IDs must not depend on the draft selected value.
form.elements.from.value = '2'; form.elements.country.value = '1';
emit('v2:search-started');
hotelsPresent = true; emit('v2:results-rendered');
assert.equal(form.dataset.search3View, 'summary');
assert.equal(trip.hidden, false);
assert.equal(route.textContent, 'Москва → Турция');
assert.equal(details.textContent, 'Вылет 02.10.2026 — 08.10.2026 · 7–10 ночей · 2 взрослых · 2 ребёнка');
const accepted = details.textContent;
snapshot = { ...first, dateFrom: '2026-11-01', adults: '4' };
emit('v2:results-rendered');
assert.equal(details.textContent, accepted, 'rerender and selected-tour return retain the started query, never an unsent edit');
clicks.get('click')({ target: { closest: () => edit } });
assert.equal(focused, true, 'the existing edit action keeps canonical departure focus');
assert.equal(trip.hidden, true, 'editing does not duplicate the visible full form');
snapshot = null; emit('v2:search-reset', { dirty: true }); emit('v2:results-rendered');
assert.equal(form.dataset.search3View, 'editor', 'dirty retained results do not collapse the draft editor');
assert.equal(details.textContent, accepted, 'dirty reset does not overwrite the accepted query');
hotelsPresent = false;
snapshot = { departureId: '2', countryId: '1', dateFrom: '2026-11-01', dateTo: '2026-11-01', nightsFrom: '1', nightsTo: '1', adults: '1', childs: [] };
emit('v2:search-reset'); emit('v2:search-started');
hotelsPresent = true; emit('v2:results-rendered');
assert.equal(route.textContent, 'Санкт-Петербург → Египет');
assert.equal(details.textContent, 'Вылет 01.11.2026 · 1 ночь · 1 взрослый', 'new successful search replaces dates/party and removes old children');
assert.equal(trip.hidden, false);
snapshot = { departureId: 'unknown', countryId: 'unknown', dateFrom: 'bad', dateTo: '', nightsFrom: 0, nightsTo: '', adults: null, childs: [null] };
emit('v2:search-reset'); emit('v2:search-error');
assert.equal(details.textContent, 'Вылет 01.11.2026 · 1 ночь · 1 взрослый', 'failed initiation cannot replace last accepted query');
emit('v2:search-started'); emit('v2:results-rendered');
assert.equal(route.textContent, ''); assert.equal(details.textContent, '');
assert.equal(trip.hidden, true, 'unknown context is omitted without fabricated dates, counts or route');
snapshot = first;
context.window.V2SearchLifecycle.searchId = 901;
context.window.V2SearchLifecycle.dirty = false;
vm.runInNewContext(formOwner, context);
assert.equal(trip.hidden, false, 'late presentation initialization recovers an already-started canonical search');
assert.equal(route.textContent, 'Москва → Турция');
assert.equal(details.textContent, accepted);
context.window.V2SearchLifecycle.searchId = 0;
context.window.V2SearchLifecycle.dirty = true;
vm.runInNewContext(formOwner, context);
assert.equal(trip.hidden, true, 'late initialization cannot present unsent draft criteria as an active search');
emit('v2:results-rendered');
assert.equal(form.dataset.search3View, 'editor', 'late initialization preserves the canonical dirty editor through retained-results rerenders');
console.log('PASS: native server form keeps six primary OTA hotel/price preferences visible, groups child ages with tourists, keeps tour operator under extras, and preserves URL hydration/FormData');
