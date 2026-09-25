'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'v2/prototype-search/rehydration-retention-v1.js'), 'utf8');
const lifecycleSource = fs.readFileSync(path.join(root, 'v2/prototype-search/search-lifecycle-v1.js'), 'utf8');
const entry = fs.readFileSync(path.join(root, 'v2/prototype-search/index.php'), 'utf8');
const clone = value => JSON.parse(JSON.stringify(value));

function cached(key = 'cached') {
  return {key, hotelId: 101, day: '2026-10-15', nights: 7, adults: 2, ages: [6], provider: 'anex',
    operator: 'ANEX', room: 'Deluxe', placement: 'DBL', meal: 'Всё включено', mealRaw: 'AI', flight: 'charter', cached: true};
}
function fresh(key = 'fresh', overrides = {}) { return {...cached(key), cached: false, ...overrides}; }
function union(row = cached()) {
  return [
    {id: 101, name: 'One', offers: [row, {...fresh('other'), room: 'Family', meal: 'Завтраки', mealRaw: 'BB'}]},
    {id: 202, name: 'Two', offers: [{...fresh('second'), hotelId: 202}]}
  ];
}
function harness(result) {
  const calls = {search: 0, resume: 0, rehydrate: 0, stop: 0}, listeners = {};
  let callback = null;
  const base = {
    marker: 'base',
    get currentSupplierScope() { return {hotel: 'supplier:101'}; },
    catalog: {mealPlans: []},
    search(_search, cb) { calls.search++; callback = cb; return true; },
    resumeCached(_search, cb) { calls.resume++; callback = cb; return true; },
    stop() { calls.stop++; return 9; },
    async rehydrateCached() { calls.rehydrate++; return clone(result); }
  };
  Object.freeze(base);
  const document = {addEventListener(type, fn, capture) { listeners[type] = {fn, capture}; }};
  const sandbox = {window: {AnyTourPrototypeData: base}, document, structuredClone: clone, JSON, Object, String, Number, Array, RegExp};
  vm.createContext(sandbox);
  vm.runInContext(source, sandbox);
  vm.runInContext(lifecycleSource, sandbox);
  return {
    data: sandbox.window.AnyTourPrototypeData,
    lifecycle: sandbox.window.AnyTourPrototypeSearchLifecycleV1,
    calls,
    listeners,
    emit: event => callback(event)
  };
}

test('served prototype injects retention bridge immediately after data owner', () => {
  assert.match(entry, /\$rehydrationNeedle = '<script src="\.\/data\.js" defer><\/script>';/);
  assert.match(entry, /\$rehydrationNeedle \. "\\n  <script src=\\"\.\/rehydration-retention-v1\.js\\" defer><\/script>"/);
  assert.match(entry, /substr_count\(\$html, \$rehydrationNeedle\) !== 1/);
});

test('bridge preserves the frozen data API and current scope getter', () => {
  const h = harness({state: 'empty', offers: []});
  assert.equal(h.data.marker, 'base');
  assert.deepEqual(clone(h.data.currentSupplierScope), {hotel: 'supplier:101'});
  assert.equal(h.data.catalog.mealPlans.length, 0);
  assert.ok(Object.getOwnPropertyDescriptor(h.data, 'currentSupplierScope'), 'downstream wrappers can copy the complete descriptor API');
  const downstream = Object.freeze(Object.defineProperties({}, Object.getOwnPropertyDescriptors(h.data)));
  assert.deepEqual(clone(downstream.currentSupplierScope), {hotel: 'supplier:101'});
  assert.equal(typeof downstream.rehydrateCached, 'function');
});

test('exact same-provider rehydration replaces only the cached row in the full current union', async () => {
  const current = fresh('live'), h = harness({state: 'current', offers: [current, {...fresh('alt'), room: 'Family'}]}), seen = [];
  h.data.search({}, event => seen.push(clone(event)));
  h.emit({type: 'results', hotels: union()});
  const result = await h.data.rehydrateCached(cached());
  assert.equal(result.offers[0].key, 'live');
  assert.equal(h.calls.rehydrate, 1, 'bridge never starts an extra supplier request');
  const replacement = seen.at(-1);
  assert.equal(replacement.rehydrationRetention, true);
  assert.equal(replacement.hotels.length, 2, 'unrelated hotels are retained');
  const first = replacement.hotels.find(row => row.id === 101);
  assert.deepEqual(first.offers.map(row => row.key).sort(), ['live', 'other']);
  assert.equal(replacement.hotels.find(row => row.id === 202).offers[0].key, 'second');
});

test('rehydration replacement keeps the validated first-union receipt in lifecycle state', async () => {
  const h = harness({state: 'current', offers: [fresh('live')]}), response = {key: 'search:1', union: null};
  const form = {addEventListener() {}};
  const lifecycle = h.lifecycle.create({
    form,
    data: h.data,
    prepare() { return {response, search: {country: 4}}; },
    currentKey() { return response.key; },
    onResults(event, target) { target.hotels = clone(event.hotels); }
  });
  assert.equal(lifecycle.run(), true);
  const receipt = {
    hotels: 2,
    offers: 3,
    hotelsByProvider: {tourvisor: 1, anex: 2},
    offersByProvider: {tourvisor: 1, anex: 2},
    providerSets: {anex: 1, 'anex+tourvisor': 1}
  };
  h.emit({type: 'results', hotels: union()});
  h.emit({type: 'complete', union: receipt});
  assert.deepEqual(clone(response.union), receipt);
  await h.data.rehydrateCached(cached());
  assert.deepEqual(clone(response.union), receipt, 'a retention-only results event must not erase the completed first-union receipt');
  const first = response.hotels.find(row => row.id === 101);
  assert.deepEqual(first.offers.map(row => row.key).sort(), ['live', 'other']);
  assert.equal(response.hotels.find(row => row.id === 202).offers[0].key, 'second');
});

test('changed variants wait for the explicit same-provider choice before replacing the cached row', async () => {
  const a = {...fresh('a'), room: 'Family'}, b = {...fresh('b'), room: 'Suite'};
  const h = harness({state: 'current', offers: [a, b]}), seen = [];
  h.data.resumeCached({}, event => seen.push(clone(event)));
  h.emit({type: 'results', hotels: union()});
  await h.data.rehydrateCached(cached());
  assert.equal(seen.length, 1, 'no replacement happens before the user chooses a changed variant');
  const target = {dataset: {key: 'b'}, closest(selector) { return selector === '[data-action="rehydrated-offer"]' ? this : null; }};
  h.listeners.click.fn({target});
  const replacement = seen.at(-1);
  assert.equal(replacement.rehydrationRetention, true);
  assert.deepEqual(replacement.hotels[0].offers.map(row => row.key).sort(), ['b', 'other']);
  assert.equal(h.listeners.click.capture, true, 'replacement lands before the existing app click handler continues verification');
});

test('placement mismatch remains an explicit alternative instead of replacing the cached row', async () => {
  const h = harness({state: 'current', offers: [{...fresh('changed-placement'), placement: 'SGL'}]}), seen = [];
  h.data.search({}, event => seen.push(clone(event)));
  h.emit({type: 'results', hotels: union()});
  await h.data.rehydrateCached(cached());
  assert.equal(seen.length, 1, 'placement change must not silently publish a replacement');
  assert.equal(seen[0].hotels[0].offers[0].key, 'cached');
});

test('multiple semantic matches require an explicit choice instead of taking the first match', async () => {
  const a = fresh('same-a'), b = fresh('same-b');
  const h = harness({state: 'current', offers: [a, b]}), seen = [];
  h.data.search({}, event => seen.push(clone(event)));
  h.emit({type: 'results', hotels: union()});
  await h.data.rehydrateCached(cached());
  assert.equal(seen.length, 1, 'ambiguous exact matches must not replace before user choice');
  assert.equal(seen[0].hotels[0].offers[0].key, 'cached');
  const target = {dataset: {key: 'same-b'}, closest(selector) { return selector === '[data-action="rehydrated-offer"]' ? this : null; }};
  h.listeners.click.fn({target});
  const replacement = seen.at(-1);
  assert.equal(replacement.rehydrationRetention, true);
  assert.deepEqual(replacement.hotels[0].offers.map(row => row.key).sort(), ['other', 'same-b']);
});

test('semantic mismatch does not silently replace the cached selection', async () => {
  const h = harness({state: 'current', offers: [{...fresh('wrong'), operator: 'Other'}]}), seen = [];
  h.data.search({}, event => seen.push(clone(event)));
  h.emit({type: 'results', hotels: union()});
  await h.data.rehydrateCached(cached());
  assert.equal(seen.length, 1);
  assert.equal(seen[0].hotels[0].offers[0].key, 'cached');
});

test('stop clears retained variants so a stale click cannot republish an old result', async () => {
  const h = harness({state: 'current', offers: [{...fresh('a'), room: 'Family'}]}), seen = [];
  h.data.search({}, event => seen.push(clone(event)));
  h.emit({type: 'results', hotels: union()});
  await h.data.rehydrateCached(cached());
  assert.equal(h.data.stop(), 9);
  const target = {dataset: {key: 'a'}, closest() { return this; }};
  h.listeners.click.fn({target});
  assert.equal(seen.length, 1);
  assert.equal(h.calls.stop, 1);
});
