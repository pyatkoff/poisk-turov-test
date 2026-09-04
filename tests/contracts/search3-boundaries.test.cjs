'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const fixture = JSON.parse(fs.readFileSync('tests/contracts/search3-boundaries.fixture.json', 'utf8'));

class TestCustomEvent extends Event {
  constructor(type, init = {}) {
    super(type);
    this.detail = init.detail;
  }
}

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((yes, no) => {
    resolve = yes;
    reject = no;
  });
  return { promise, resolve, reject };
}

function read(relativePath) {
  return fs.readFileSync(relativePath, 'utf8');
}

function matchingEnd(source, start, open, close) {
  assert.equal(source[start], open, `expected ${open} at ${start}`);
  let depth = 0;
  let quote = '';
  let lineComment = false;
  let blockComment = false;
  for (let i = start; i < source.length; i += 1) {
    const char = source[i];
    const next = source[i + 1] || '';
    if (lineComment) {
      if (char === '\n') lineComment = false;
      continue;
    }
    if (blockComment) {
      if (char === '*' && next === '/') {
        blockComment = false;
        i += 1;
      }
      continue;
    }
    if (quote) {
      if (char === '\\') {
        i += 1;
      } else if (char === quote) {
        quote = '';
      }
      continue;
    }
    if (char === '/' && next === '/') {
      lineComment = true;
      i += 1;
      continue;
    }
    if (char === '/' && next === '*') {
      blockComment = true;
      i += 1;
      continue;
    }
    if (char === "'" || char === '"' || char === '`') {
      quote = char;
      continue;
    }
    if (char === open) depth += 1;
    if (char === close) {
      depth -= 1;
      if (depth === 0) return i;
    }
  }
  throw new Error(`unbalanced ${open}${close} expression`);
}

function splitTopLevel(source, delimiter = ',') {
  const parts = [];
  let start = 0;
  let quote = '';
  const stack = [];
  const closes = { '(': ')', '[': ']', '{': '}' };
  for (let i = 0; i < source.length; i += 1) {
    const char = source[i];
    if (quote) {
      if (char === '\\') i += 1;
      else if (char === quote) quote = '';
      continue;
    }
    if (char === "'" || char === '"' || char === '`') {
      quote = char;
      continue;
    }
    if (closes[char]) stack.push(closes[char]);
    else if (stack.length && char === stack[stack.length - 1]) stack.pop();
    else if (!stack.length && source.startsWith(delimiter, i)) {
      parts.push(source.slice(start, i).trim());
      start = i + delimiter.length;
      i += delimiter.length - 1;
    }
  }
  parts.push(source.slice(start).trim());
  return parts.filter(Boolean);
}

function compactCode(source) {
  let out = '';
  let quote = '';
  for (let i = 0; i < source.length; i += 1) {
    const char = source[i];
    if (quote) {
      out += char;
      if (char === '\\') out += source[++i] || '';
      else if (char === quote) quote = '';
      continue;
    }
    if (char === "'" || char === '"') {
      quote = char;
      out += char;
    } else if (!/\s/.test(char)) {
      out += char;
    }
  }
  return out;
}

function phpCaseBlock(source, action) {
  const marker = `case '${action}':`;
  const start = source.indexOf(marker);
  assert.notEqual(start, -1, `missing PHP action ${action}`);
  const rest = source.slice(start + marker.length);
  const boundary = rest.search(/\n\s*(?:case\s+'[^']+'|default)\s*:/);
  return boundary < 0 ? rest : rest.slice(0, boundary);
}

function callArguments(source, callName) {
  const marker = `${callName}(`;
  const start = source.indexOf(marker);
  assert.notEqual(start, -1, `missing ${callName} call`);
  const open = start + marker.length - 1;
  return splitTopLevel(source.slice(open + 1, matchingEnd(source, open, '(', ')')));
}

function phpArrayKeys(expression) {
  const start = expression.indexOf('[');
  if (start < 0) return [];
  const body = expression.slice(start + 1, matchingEnd(expression, start, '[', ']'));
  return splitTopLevel(body).map(entry => {
    const match = entry.match(/^\s*['"]([^'"]+)['"]\s*=>/);
    assert.ok(match, `expected associative PHP entry: ${entry}`);
    return match[1];
  });
}

function assignmentArrayKeys(source, marker) {
  const index = source.indexOf(marker);
  assert.notEqual(index, -1, `missing assignment ${marker}`);
  const start = source.indexOf('[', index + marker.length - 1);
  return phpArrayKeys(source.slice(start, matchingEnd(source, start, '[', ']') + 1));
}

function jsObjectKeysFromFunction(source, functionName) {
  const marker = `function ${functionName}(`;
  const functionStart = source.indexOf(marker);
  assert.notEqual(functionStart, -1, `missing JS function ${functionName}`);
  const blockStart = source.indexOf('{', functionStart + marker.length);
  const block = source.slice(blockStart + 1, matchingEnd(source, blockStart, '{', '}'));
  const returnedObject = /return\s*(?:Object\.assign\s*\()?\s*\{/.exec(block);
  assert.ok(returnedObject, `missing returned object in ${functionName}`);
  const objectStart = returnedObject.index + returnedObject[0].lastIndexOf('{');
  const body = block.slice(objectStart + 1, matchingEnd(block, objectStart, '{', '}'));
  return splitTopLevel(body).map(entry => {
    const match = entry.match(/^\s*(?:['"]([^'"]+)['"]|([A-Za-z_$][\w$]*))\s*:/);
    assert.ok(match, `expected JS property in ${functionName}: ${entry}`);
    return match[1] || match[2];
  });
}

function stringLiterals(source) {
  return Array.from(source.matchAll(/['"]([^'"]+)['"]/g), match => match[1]);
}

function normalized(value) {
  return JSON.parse(JSON.stringify(value));
}

function loadRuntime() {
  const fetchCalls = [];
  const window = new EventTarget();
  window.window = window;
  window.V2_CONFIG = { api: '/api-v2.php' };
  window.fetch = async (input, init) => {
    fetchCalls.push({ input, init });
    return { ok: true, clone() { return this; }, async json() { return {}; } };
  };
  const context = vm.createContext({
    window,
    location: { href: 'https://anytoour.ru/poisk-turov/' },
    URL,
    URLSearchParams,
    AbortController,
    Error,
    Promise,
    setTimeout,
    clearTimeout,
    console,
  });
  vm.runInContext(read('v2/runtime-v3.js'), context, { filename: 'runtime-v3.js' });
  return { runtime: window.V2Runtime, fetchCalls };
}

function lifecycleValues() {
  return {
    from: '1', country: '4', dateFrom: '2099-09-10', dateTo: '2099-09-17',
    daysFrom: '7', daysTill: '10', count_people: '2', 'child_age[]': ['6', '9'],
    food: '7', stars: '4', rating: '5', hotel_type: '2', hotel: '88',
    'hotel_service[]': ['wifi', 'pool'], arrival: '11', region: '21', subregion: '31',
    operator: '41', price_from: '80000', price_till: '200000', onlyCharter: '1', onlyDirect: '',
  };
}

function loadLifecycle(responder) {
  const calls = [];
  const renders = [];
  const timers = [];
  const events = [];
  const values = lifecycleValues();
  const button = { disabled: false };
  const formListeners = {};
  const form = {
    values,
    elements: { child_count: { value: '2', dataset: {} } },
    querySelector(selector) { return selector === '.primary' || selector === '.search-submit' ? button : null; },
    querySelectorAll() { return []; },
    addEventListener(name, listener) { formListeners[name] = listener; },
  };
  const status = { hidden: true, textContent: '', innerHTML: '' };
  const results = { innerHTML: '', querySelector() { return null; } };
  const selected = { hidden: true, innerHTML: '' };
  const tools = { hidden: true };
  class FakeFormData {
    constructor(target) { this.values = target.values; }
    get(name) {
      const value = this.values[name];
      return Array.isArray(value) ? (value[0] ?? null) : (value === undefined ? null : value);
    }
    getAll(name) {
      const value = this.values[name];
      if (value === undefined || value === null || value === '') return [];
      return Array.isArray(value) ? value.slice() : [value];
    }
  }
  const document = {
    getElementById(id) { return ({ tourSearch: form, status, results, selectedTour: selected, resultsTools: tools })[id] || null; },
    createElement() { return { value: '', textContent: '', dataset: {} }; },
  };
  const window = new EventTarget();
  window.window = window;
  window.location = { search: '' };
  window.V2Runtime = {
    state: { searchId: 0 },
    setSearchId(id) { this.state.searchId = Number(id || 0); },
    api(action, params, options) {
      calls.push({ action, params: normalized(params), options: normalized(options || {}) });
      return responder(action, params, calls.length);
    },
  };
  window.V2Results = { render(items) { renders.push(normalized(items)); } };
  window.V2Catalogs = {
    renderChildAges() {}, init() { return Promise.resolve(); }, handleChange() { return Promise.resolve(); },
    updateServiceCount() {},
  };
  for (const name of ['reset', 'started', 'progress', 'complete', 'error', 'dirty', 'progress-results-error']) {
    window.addEventListener(`v2:search-${name}`, event => events.push({ type: event.type, detail: normalized(event.detail) }));
  }
  const context = vm.createContext({
    window,
    document,
    location: window.location,
    URLSearchParams,
    FormData: FakeFormData,
    CustomEvent: TestCustomEvent,
    Event,
    Date,
    Intl,
    Number,
    Array,
    Object,
    Promise,
    console,
    setTimeout(fn, delay) {
      const timer = { fn, delay, active: true };
      timers.push(timer);
      return timers.length;
    },
    clearTimeout(id) { if (timers[id - 1]) timers[id - 1].active = false; },
  });
  vm.runInContext(read('v2/search-lifecycle-v6.js'), context, { filename: 'search-lifecycle-v6.js' });
  return { window, form, status, results, button, calls, renders, timers, events, lifecycle: window.V2SearchLifecycle };
}

async function nextActiveTimer(harness) {
  const timer = harness.timers.find(item => item.active && !item.used);
  assert.ok(timer, 'expected an active lifecycle timer');
  timer.used = true;
  await timer.fn();
}

async function testTourvisorRuntimeAndLifecycle() {
  const { runtime } = loadRuntime();
  const built = new URL(runtime.build('search_start', {
    departureId: 1,
    childs: [6, 9],
    onlyDirect: false,
    omitted: '',
    nil: null,
  }), 'https://anytoour.ru');
  assert.equal(built.pathname, '/api-v2.php');
  assert.equal(built.searchParams.get('action'), 'search_start');
  assert.deepEqual(built.searchParams.getAll('childs[]'), ['6', '9']);
  assert.equal(built.searchParams.get('onlyDirect'), 'false');
  assert.equal(built.searchParams.has('omitted'), false);
  assert.equal(built.searchParams.has('nil'), false);

  const responseQueues = {
    search_start: [{ searchId: 71 }],
    search_status: [
      { progress: 20, minPrice: 99000 },
      { progress: 25, minPrice: 98000 },
      { progress: 30, minPrice: 97000 },
      { progress: 100, status: 'complete' },
    ],
    search_results: [
      Array.from({ length: 25 }, (_, id) => ({ id })),
      Array.from({ length: 27 }, (_, id) => ({ id })),
      Array.from({ length: 30 }, (_, id) => ({ id })),
    ],
  };
  const lifecycle = loadLifecycle((action) => Promise.resolve(responseQueues[action].shift()));
  assert.deepEqual(normalized(lifecycle.lifecycle.params()), fixture.tourvisor.clientSearchParams);
  await lifecycle.lifecycle.submit();
  assert.equal(lifecycle.calls[0].action, 'search_start');
  assert.deepEqual(lifecycle.calls[0].params, fixture.tourvisor.clientSearchParams);
  await nextActiveTimer(lifecycle);
  assert.deepEqual(lifecycle.calls.slice(1, 3).map(call => [call.action, call.params.limit || null]), [
    ['search_status', null], ['search_results', fixture.tourvisor.progressive.initialLimit],
  ]);
  assert.equal(lifecycle.renders[0].length, 25);
  await nextActiveTimer(lifecycle);
  assert.equal(lifecycle.calls[3].action, 'search_status');
  assert.equal(lifecycle.renders.length, 1, 'progress below the refresh step must not refetch results');
  await nextActiveTimer(lifecycle);
  assert.deepEqual(lifecycle.calls.slice(4, 6).map(call => [call.action, call.params.limit || null]), [
    ['search_status', null], ['search_results', fixture.tourvisor.progressive.initialLimit],
  ]);
  assert.equal(lifecycle.renders[1].length, 27);
  await nextActiveTimer(lifecycle);
  assert.deepEqual(lifecycle.calls.slice(6, 8).map(call => [call.action, call.params.limit || null]), [
    ['search_status', null], ['search_results', fixture.tourvisor.progressive.finalLimit],
  ]);
  assert.equal(lifecycle.renders[2].length, 30);
  assert.equal(lifecycle.events.filter(event => event.type === 'v2:search-complete').length, 1);

  const firstStart = deferred();
  const secondStart = deferred();
  let startIndex = 0;
  const race = loadLifecycle(() => [firstStart, secondStart][startIndex++].promise);
  const oldRun = race.lifecycle.submit();
  const currentRun = race.lifecycle.submit();
  firstStart.resolve({ searchId: 70 });
  await oldRun;
  assert.equal(race.events.some(event => event.type === 'v2:search-started'), false);
  secondStart.resolve({ searchId: 71 });
  await currentRun;
  assert.deepEqual(race.events.filter(event => event.type === 'v2:search-started').map(event => event.detail.searchId), [71]);

  const statusResponse = deferred();
  const stalePoll = loadLifecycle(action => {
    if (action === 'search_start') return Promise.resolve({ searchId: 91 });
    if (action === 'search_status') return statusResponse.promise;
    throw new Error(`stale poll unexpectedly called ${action}`);
  });
  await stalePoll.lifecycle.submit();
  const polling = nextActiveTimer(stalePoll);
  stalePoll.lifecycle.markDirty('contract_test');
  statusResponse.resolve({ progress: 50 });
  await polling;
  assert.deepEqual(stalePoll.calls.map(call => call.action), ['search_start', 'search_status']);
  assert.equal(stalePoll.renders.length, 0);
  assert.equal(stalePoll.lifecycle.searchId, 0);
  assert.equal(stalePoll.lifecycle.dirty, true);

  const resultsResponse = deferred();
  const staleResults = loadLifecycle(action => {
    if (action === 'search_start') return Promise.resolve({ searchId: 92 });
    if (action === 'search_status') return Promise.resolve({ progress: 20 });
    if (action === 'search_results') return resultsResponse.promise;
    throw new Error(`unexpected action ${action}`);
  });
  await staleResults.lifecycle.submit();
  const refreshing = nextActiveTimer(staleResults);
  await new Promise(resolve => setImmediate(resolve));
  assert.deepEqual(staleResults.calls.map(call => call.action), ['search_start', 'search_status', 'search_results']);
  staleResults.lifecycle.markDirty('contract_test_results');
  resultsResponse.resolve([{ id: 'old-hotel' }]);
  await refreshing;
  assert.equal(staleResults.renders.length, 0);
}

async function testResultsDepthStaleGuard() {
  const window = new EventTarget();
  window.window = window;
  const lifecycle = { searchId: 71, dirty: false, pending: false };
  const requests = [];
  const renders = [];
  const expanded = [];
  window.V2SearchLifecycle = lifecycle;
  window.V2Runtime = { api(action, params) { const request = deferred(); requests.push({ action, params, request }); return request.promise; } };
  window.V2Results = { render(items) { renders.push(normalized(items)); } };
  window.addEventListener('v2:results-depth-expanded', event => expanded.push(normalized(event.detail)));
  const context = vm.createContext({ window, CustomEvent: TestCustomEvent, Event, Number, Array, Promise, console });
  vm.runInContext(read('v2/results-depth-v1.js'), context, { filename: 'results-depth-v1.js' });
  const initial = Array.from({ length: window.V2ResultsDepthV1.initialLimit }, (_, id) => ({ id }));
  window.dispatchEvent(new TestCustomEvent('v2:search-complete', { detail: { searchId: 71, items: initial } }));
  assert.equal(requests[0].params.limit, window.V2ResultsDepthV1.expandedLimit);
  window.dispatchEvent(new TestCustomEvent('v2:search-reset'));
  lifecycle.searchId = 72;
  requests[0].request.resolve(Array.from({ length: 40 }, (_, id) => ({ id })));
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(renders.length, 0);

  lifecycle.searchId = 72;
  window.dispatchEvent(new TestCustomEvent('v2:search-complete', { detail: { searchId: 72, items: initial } }));
  requests[1].request.resolve(Array.from({ length: 35 }, (_, id) => ({ id })));
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(renders[0].length, 35);
  assert.deepEqual(expanded, [{ searchId: 72, from: 25, to: 35, limit: 100 }]);
}

function testGatewayStructure() {
  const source = read('v2/api-v2.php');
  for (const [action, expected] of Object.entries(fixture.tourvisor.gatewayActions)) {
    const block = phpCaseBlock(source, action);
    const args = callArguments(block, expected.transport);
    assert.equal(compactCode(args[0]), expected.path, `${action} upstream path changed`);
    assert.deepEqual(args[1] ? phpArrayKeys(args[1]) : [], expected.params, `${action} upstream params changed`);
  }
  assert.match(source, /https:\/\/api\.tourvisor\.ru\/search\/api\/v1/);
  assert.match(source, /Authorization: Bearer /);
}

function loadPriceModules() {
  const priceBox = { innerHTML: '', closest() { return null; } };
  const leadNote = { textContent: '' };
  const styles = [];
  const document = {
    querySelector(selector) {
      if (selector === '#selectedTour .selected-price') return priceBox;
      if (selector === '#selectedTour .lead-form .section-heading span') return leadNote;
      return null;
    },
    querySelectorAll() { return []; },
    getElementById() { return null; },
    createElement() { return { id: '', textContent: '' }; },
    head: { appendChild(node) { styles.push(node); } },
  };
  const window = new EventTarget();
  window.window = window;
  const priceEvents = [];
  window.addEventListener('v2:tour-price-updated', event => priceEvents.push(normalized(event.detail)));
  const context = vm.createContext({ window, document, CustomEvent: TestCustomEvent, Event, Intl, Number, Array, Object, String });
  vm.runInContext(read('v2/flight-price-sync-v1.js'), context, { filename: 'flight-price-sync-v1.js' });
  vm.runInContext(read('v2/unpriced-flight-price-reset-v1.js'), context, { filename: 'unpriced-flight-price-reset-v1.js' });
  return { window, document, priceBox, leadNote, priceEvents };
}

function testPricingSemantics() {
  const prices = fixture.pricing;
  const harness = loadPriceModules();
  const tour = { id: 'tour-1', price: prices.basePrice, fuelCharge: { value: 2000 } };
  const flight = { price: { value: prices.selectedFlightPrice }, fuelCharge: { value: prices.selectedFlightFuel } };
  harness.window.V2FlightPriceSync.sync(tour, flight, 2);
  const selected = harness.priceEvents.at(-1);
  assert.equal(selected.basePrice, prices.basePrice);
  assert.equal(selected.price, prices.selectedFlightPrice);
  assert.equal(selected.delta, prices.selectedDelta);
  assert.equal(selected.fuelCharge, prices.selectedFlightFuel);
  assert.match(harness.priceBox.innerHTML, /112(?:\s| )500 ₽/);
  assert.match(harness.leadNote.textContent, /выбранный рейс/);
  assert.equal(harness.window.V2FlightPriceSync.selectedFuelValue(tour, {}), 2000);

  const pending = { price: { value: 0 }, fuelCharge: { value: prices.pendingFlightFuel } };
  assert.equal(harness.window.V2UnpricedFlightPriceResetV1.apply({ detail: { tour, flight: pending, index: 1 } }), true);
  const pendingEvent = harness.priceEvents.at(-1);
  assert.deepEqual({
    basePrice: pendingEvent.basePrice,
    price: pendingEvent.price,
    delta: pendingEvent.delta,
    fuelCharge: pendingEvent.fuelCharge,
    pricePending: pendingEvent.pricePending,
  }, {
    basePrice: prices.basePrice, price: 0, delta: 0, fuelCharge: prices.pendingFlightFuel, pricePending: true,
  });
  assert.match(harness.priceBox.innerHTML, /100(?:\s| )000 ₽/);

  const document = {
    readyState: 'loading',
    getElementById() { return { value: 'price', dataset: {}, addEventListener() {} }; },
    addEventListener() {},
  };
  const window = { addEventListener() {}, dispatchEvent() {} };
  vm.runInNewContext(read('v2/results-renderer-v5.js'), { window, document, CustomEvent: TestCustomEvent, Intl, Number, Array, Set, String }, { filename: 'results-renderer-v5.js' });
  const renderer = window.V2ResultsV5;
  assert.equal(renderer.priceRank(0), Number.POSITIVE_INFINITY);
  assert.equal(renderer.representativeTour({ price: 120000, tours: [{ id: 'a', price: 130000 }, { id: 'b', price: 120000 }] }).id, 'b');
  assert.match(renderer.tourRow({ id: 'unknown', price: 0 }), /Цена уточняется/);
}

async function testLeadContextRuntime() {
  const calls = [];
  const window = {};
  window.window = window;
  window.V2_CONFIG = { leadApi: '/lead-adapter-v2.php' };
  window.V2SearchLifecycle = { snapshot: { adults: '3', childs: [5, 20, 0] } };
  window.fetch = async (input, init) => { calls.push({ input, init }); return { ok: true }; };
  const location = { href: 'https://anytoour.ru/poisk-turov/?utm_source=test' };
  vm.runInNewContext(read('v2/lead-search-context.js'), { window, location, URL, JSON, Object, Array, Number, String }, { filename: 'lead-search-context.js' });
  await window.fetch('/lead-adapter-v2.php?attempt=1', { method: 'POST', body: JSON.stringify({ tourId: 'T1', childs: 99 }) });
  assert.deepEqual(JSON.parse(calls[0].init.body), { tourId: 'T1', childs: 2, childAges: [5, 0], adults: 3 });
  await window.fetch('/api-v2.php', { method: 'POST', body: JSON.stringify({ untouched: true }) });
  assert.deepEqual(JSON.parse(calls[1].init.body), { untouched: true });
}

function testLeadStructureAndDelivery() {
  const controller = read('v2/tour-controller-v4.js');
  const payloadKeys = [
    ...jsObjectKeysFromFunction(controller, 'leadPayload'),
    ...jsObjectKeysFromFunction(controller, 'selectedFlightData'),
    ...jsObjectKeysFromFunction(controller, 'attribution'),
  ];
  assert.deepEqual(Array.from(new Set(payloadKeys)), fixture.lead.browserPayloadKeys);
  assert.match(controller, /window\.fetch\(leadApi,\{method:'POST',headers:\{'Content-Type':'application\/json','X-Requested-With':'XMLHttpRequest'\},credentials:'same-origin',body:JSON\.stringify\(payload\)\}\)/);
  assert.match(controller, /!d\.writes&&!d\.duplicate/);

  const adapter = read('v2/lead-adapter-v2.php');
  const adapterKeys = assignmentArrayKeys(adapter, '$lead=[');
  for (const match of adapter.matchAll(/\$lead\[['"]([^'"]+)['"]\]\s*=/g)) adapterKeys.push(match[1]);
  assert.deepEqual(Array.from(new Set(adapterKeys)), fixture.lead.adapterLeadKeys);
  for (const [name, expected] of Object.entries(fixture.lead.bitrixConstants)) {
    const match = adapter.match(new RegExp(`const\\s+${name}\\s*=\\s*(\\d+)\\s*;`));
    assert.ok(match, `missing ${name}`);
    assert.equal(Number(match[1]), expected);
  }
  const propertyKeys = assignmentArrayKeys(adapter, '$properties=[');
  for (const match of adapter.matchAll(/\$properties\[['"]([^'"]+)['"]\]\s*=/g)) propertyKeys.push(match[1]);
  if (/PROPERTY_VALUES'\]\[['"]IS_ANYTOUR_ONLINE['"]\]/.test(adapter)) propertyKeys.push('IS_ANYTOUR_ONLINE');
  assert.deepEqual(Array.from(new Set(propertyKeys)), fixture.lead.bitrixPropertyKeys);
  assert.match(adapter, /new \\CIBlockElement\(\)/);
  assert.match(adapter, /\$el->Add\(\$built\['element'\]\)/);

  const index = read('v2/index.php');
  assert.ok(index.includes(`leadApi:<?=json_encode(v2_public_path('${fixture.lead.publicEndpoint}')`));
  const bridge = read('v2/lead-bridge-v1.php');
  assert.ok(bridge.includes(`const V2_BRIDGE_RECEIVER = '${fixture.lead.bridgeReceiver}';`));
  assert.ok(bridge.includes(`'${fixture.lead.signatureHeader}: ' . $signature`));
  assert.match(bridge, /hash_hmac\('sha256', \$raw, \$secret\)/);
  const receiver = read('v2/lead-receiver-v1.php');
  assert.ok(receiver.includes(`$_SERVER['HTTP_X_ANYTOOUR_SIGNATURE']`));
  assert.ok(receiver.includes(`require __DIR__ . '/lead-adapter-v2.php';`));
  const deploy = read('.github/workflows/deploy-anytoour.yml');
  assert.ok(deploy.includes('cp "$root/lead-bridge-v1.php" "$root/lead-adapter-v2.php"'));
}

function loadAnalytics() {
  const insertedScripts = [];
  const firstScript = { parentNode: { insertBefore(node) { insertedScripts.push(node); } } };
  const document = new EventTarget();
  document.createElement = () => ({ async: false, src: '' });
  document.getElementsByTagName = () => [firstScript];
  document.head = { appendChild(node) { insertedScripts.push(node); } };
  const window = new EventTarget();
  window.window = window;
  window.V2_CONFIG = { metrikaCounter: 12345 };
  window.dataLayer = [];
  const context = vm.createContext({
    window,
    document,
    CustomEvent: TestCustomEvent,
    Event,
    Date,
    Map,
    Set,
    Object,
    Array,
    Number,
    String,
    performance: { now: () => 250 },
    console,
  });
  vm.runInContext(read('v2/analytics-v4.js'), context, { filename: 'analytics-v4.js' });
  return { window, document, insertedScripts };
}

function testAnalyticsRuntimeAndConfig() {
  const source = read('v2/analytics-v4.js');
  const setMarker = 'const SAFE_KEYS=new Set(';
  const setStart = source.indexOf('[', source.indexOf(setMarker));
  const safeKeys = stringLiterals(source.slice(setStart, matchingEnd(source, setStart, '[', ']') + 1));
  assert.deepEqual(safeKeys, fixture.analytics.safeKeys);

  const harness = loadAnalytics();
  assert.equal(harness.insertedScripts.length, 1);
  assert.equal(harness.insertedScripts[0].src, 'https://mc.yandex.ru/metrika/tag.js');
  const ymCalls = () => (harness.window.ym.a || []).map(args => Array.from(args));
  const init = ymCalls().find(args => args[1] === 'init');
  assert.equal(init[0], 12345);
  assert.deepEqual(normalized(init[2]), fixture.analytics.metrikaInit);

  const details = {
    'v2:search-started': { searchId: 77 },
    'v2:search-complete': { searchId: 77, items: [{ id: 1 }, { id: 2 }] },
    'v2:search-error': { searchId: 77, phase: 'status', error: { code: 'TIMEOUT' } },
    'v2:search-continued': { searchId: 77, previousResultsCount: 25, addedResultsCount: 10 },
    'v2:tour-selected': { tour: { id: 'T1', price: 100000, hotel: { id: 4, name: 'Fixture', region: { name: 'Side' }, country: { name: 'Turkey' } } } },
    'v2:flight-selected': { tour: { id: 'T1', price: 100000 }, flight: { price: { value: 112500 }, isDefault: true, forward: [{ number: 'FV1' }] }, index: 2 },
    'v2:lead-started': { searchId: 77, tourId: 'T1' },
    'v2:lead-submitted': { searchId: 77, tourId: 'T1', leadId: 42, deduplicated: false },
    'v2:lead-error': { searchId: 77, tourId: 'T1', error: { name: 'Error' } },
  };
  for (const [eventName] of Object.entries(fixture.analytics.eventGoals)) {
    harness.window.dispatchEvent(new TestCustomEvent(eventName, { detail: details[eventName] }));
  }
  const goals = ymCalls().filter(args => args[1] === 'reachGoal').map(args => args[2]);
  assert.deepEqual(normalized(goals), Object.values(fixture.analytics.eventGoals));
  assert.deepEqual(normalized(harness.window.dataLayer.map(event => event.event)), Object.values(fixture.analytics.eventGoals).map(goal => goal.toLowerCase()));
  const safe = harness.window.V2Analytics.track('fixture', { searchId: 77, hotel: 'x'.repeat(200), phone: '+79990000000', nested: { secret: true } });
  assert.deepEqual(normalized(Object.keys(safe)), ['searchId', 'hotel']);
  assert.equal(safe.hotel.length, 160);

  const config = read('v2/analytics-config.php');
  assert.ok(config.includes(`defined('${fixture.analytics.counterConstant}')`));
  assert.ok(config.includes(`filter_var(${fixture.analytics.counterConstant}, FILTER_VALIDATE_INT`));
  const index = read('v2/index.php');
  assert.ok(index.includes(`${fixture.analytics.counterConfigKey}:<?=json_encode($metrikaCounter`));
}

(async () => {
  assert.equal(fixture.schemaVersion, 1);
  testGatewayStructure();
  await testTourvisorRuntimeAndLifecycle();
  await testResultsDepthStaleGuard();
  testPricingSemantics();
  await testLeadContextRuntime();
  testLeadStructureAndDelivery();
  testAnalyticsRuntimeAndConfig();
  console.log('SEARCH3_CONTRACT_BOUNDARIES_OK');
})().catch(error => {
  console.error(error);
  process.exit(1);
});
