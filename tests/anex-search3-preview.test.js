'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const filename = path.join(__dirname, '../v2/anex-search3-preview-v1.js');
const source = fs.readFileSync(filename, 'utf8');
const plain = value => JSON.parse(JSON.stringify(value));

function hotel(overrides = {}) {
  return {
    local_id: 245, name: 'Mapped ANEX hotel', category: 4,
    country: 'Turkey', region: 'Antalya',
    tours: [{
      price: { amount: '1234.50', currency: 'RUB' }, checkin: '2027-02-01',
      nights: 7, adults: 2, children: 0, meal: 'AI', room: 'Standard',
      kind: 'group_minimum', final_price_verified: false
    }],
    ...overrides
  };
}

function helpers() {
  const window = { location: { pathname: '/poisk-turov/' } };
  const document = { currentScript: null, readyState: 'complete' };
  vm.runInNewContext(source, { window, document, URL, console }, { filename });
  assert.equal(window.AnyTourAnexSearch3.version, 2);
  return window.AnyTourAnexSearch3;
}

test('ANEX displays its actual week without claiming the full shared range', () => {
  const label = helpers().dateRangeLabel;
  assert.equal(label({ from: '2026-09-09', to: '2026-09-15' }), 'Вылеты ANEX: 09.09.2026 — 15.09.2026');
  assert.equal(label({ from: '2026-09-09', to: '2026-09-09' }), 'Вылеты ANEX: 09.09.2026');
  for (const value of [null, {}, { from: '2026-09-09', to: '2026-09-22' },
    { from: '2026-02-30', to: '2026-03-01' }, { from: '<script>', to: '2026-09-15' }]) assert.equal(label(value), '');
});

class FakeElement {
  constructor(tagName) {
    this.tagName = tagName.toUpperCase();
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.dataset = {};
    this.style = {
      priorities: {},
      setProperty(name, value, priority = '') { this[name] = value; this.priorities[name] = priority; },
      getPropertyValue(name) { return this[name] || ''; },
      getPropertyPriority(name) { return this.priorities[name] || ''; },
      removeProperty(name) { delete this[name]; delete this.priorities[name]; }
    };
    this.id = '';
    this.className = '';
    this.hidden = false;
    this._text = '';
    this._html = '';
    this.listeners = new Map();
    this.classList = {
      add: (...names) => { this.className += ' ' + names.join(' '); },
      remove: (...names) => { this.className = this.className.split(/\s+/).filter(name => !names.includes(name)).join(' '); },
      contains: name => this.className.split(/\s+/).includes(name)
    };
  }
  appendChild(child) {
    child.remove();
    child.parentNode = this;
    this.children.push(child);
    return child;
  }
  append(...children) { children.forEach(child => this.appendChild(child)); }
  insertBefore(child, next) {
    child.remove();
    child.parentNode = this;
    const index = this.children.indexOf(next);
    this.children.splice(index < 0 ? this.children.length : index, 0, child);
    return child;
  }
  insertAdjacentElement(position, child) {
    assert.equal(position, 'afterend');
    return this.parentNode.insertBefore(child, this.nextSibling);
  }
  get nextSibling() { return this.parentNode?.children[this.parentNode.children.indexOf(this) + 1] || null; }
  get parentElement() { return this.parentNode; }
  get firstElementChild() { return this.children[0] || null; }
  remove() {
    if (this.parentNode) this.parentNode.children.splice(this.parentNode.children.indexOf(this), 1);
    this.parentNode = null;
  }
  replaceChildren(...children) {
    this.children.forEach(child => { child.parentNode = null; });
    this.children = [];
    this._text = '';
    this._html = '';
    this.append(...children);
  }
  set textContent(value) { this.replaceChildren(); this._text = String(value); }
  get textContent() { return this._text + this._html + this.children.map(child => child.textContent).join(''); }
  set innerHTML(value) { this.replaceChildren(); this._html = String(value); }
  get innerHTML() { return this._html + this._text + this.children.map(child => child.outerHTML).join(''); }
  get outerHTML() { return '<' + this.tagName + ' id="' + this.id + '" class="' + this.className + '">' + this.innerHTML + '</' + this.tagName + '>'; }
  setAttribute(name, value) {
    this.attributes[name] = String(value);
    if (name === 'id') this.id = String(value);
    if (name === 'class') this.className = String(value);
    if (name.startsWith('data-')) this.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = String(value);
  }
  getAttribute(name) {
    if (name.startsWith('data-')) return this.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] ?? null;
    return this.attributes[name] ?? null;
  }
  addEventListener(name, listener) {
    if (!this.listeners.has(name)) this.listeners.set(name, []);
    this.listeners.get(name).push(listener);
  }
  dispatchEvent(event) { (this.listeners.get(event.type) || []).forEach(listener => listener(event)); }
  focus(options) { this.focusOptions = options; this.focused = true; }
  matches(selector) {
    const attribute = selector.match(/\[([^\]]+)\]$/);
    if (attribute) {
      const parts = attribute[1].split('=');
      return this.getAttribute(parts[0]) !== null && (parts.length === 1 || this.getAttribute(parts[0]) === parts[1].replace(/^"|"$/g, ''))
        && (!selector.slice(0, attribute.index) || this.matches(selector.slice(0, attribute.index)));
    }
    if (selector.startsWith('#')) return this.id === selector.slice(1);
    if (selector.startsWith('.')) return this.classList.contains(selector.slice(1));
    return this.tagName.toLowerCase() === selector.toLowerCase();
  }
  querySelectorAll(selector) {
    return this.children.flatMap(child => [
      ...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)
    ]);
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  closest(selector) { return this.matches(selector) ? this : this.parentNode?.closest(selector) || null; }
  contains(element) { return element === this || this.children.some(child => child.contains(element)); }
}

function preview(withFilters = false, withAndromeda = false) {
  const listeners = new Map();
  const documentListeners = new Map();
  const requests = [];
  const observers = [];
  const body = new FakeElement('body');
  const tools = body.appendChild(new FakeElement('div'));
  tools.id = 'resultsTools';
  tools.appendChild(new FakeElement('strong')).textContent = 'Найдено 0 туров';
  const summary = tools.appendChild(new FakeElement('span'));
  summary.id = 'resultSummary';
  summary.textContent = 'Актуальные варианты';
  const sort = tools.appendChild(new FakeElement('select'));
  sort.id = 'sortResults';
  sort.value = 'price';
  const rail = withFilters ? body.appendChild(new FakeElement('aside')) : null;
  if (rail) rail.className = 'results-filter-rail';
  const layout = body.appendChild(new FakeElement('div'));
  layout.className = 'results-layout';
  const results = layout.appendChild(new FakeElement('div'));
  results.id = 'results';
  const tvCard = results.appendChild(new FakeElement('article'));
  tvCard.className = 'hotel-card';
  tvCard.dataset.hotelId = '245';
  tvCard.textContent = 'Existing Tourvisor hotel';
  const form = body.appendChild(new FakeElement('form'));
  form.id = 'tourSearch';
  form.elements = Object.fromEntries([
    ['from', '1', 'Москва'], ['country', '4', 'Турция']
  ].map(([name, value, text]) => [name, {
    value, selectedIndex: 0, options: [{ textContent: text, text, value }],
    selectedOptions: [{ textContent: text, text, value }]
  }]));
  const document = {
    body, head: new FakeElement('head'), documentElement: new FakeElement('html'),
    readyState: 'complete',
    currentScript: { src: 'https://example.test/_preview/search3-anex-candidate/v2/anex-search3-preview-v1.js?v=test' },
    createElement: tag => new FakeElement(tag),
    getElementById: id => body.querySelector('#' + id),
    querySelector: selector => selector === '#resultsTools strong' ? tools.querySelector('strong') : body.querySelector(selector),
    querySelectorAll: selector => body.querySelectorAll(selector),
    addEventListener(name, listener) {
      if (!documentListeners.has(name)) documentListeners.set(name, []);
      documentListeners.get(name).push(listener);
    },
    dispatchEvent(event) { (documentListeners.get(event.type) || []).forEach(listener => listener(event)); },
    contains(element) { return body.contains(element); }
  };
  const lifecycle = { generation: 0, dirty: false, snapshot: null, searchId: 0, pending: false };
  const window = {
    location: {
      pathname: '/_preview/search3-anex-candidate/poisk-turov/',
      href: 'https://example.test/_preview/search3-anex-candidate/poisk-turov/',
      origin: 'https://example.test'
    },
    V2SearchLifecycle: lifecycle,
    V2Runtime: {
      state: { searchId: 777 },
      api() { throw new Error('Point search must not call observed runtime.api'); },
      setSearchId() { throw new Error('Point search must not replace the broad search ID'); },
      build(action, params) {
        const query = new URLSearchParams({ action });
        Object.entries(params || {}).forEach(([key, value]) => {
          if (value === '' || value === null || value === undefined) return;
          if (Array.isArray(value)) value.forEach(item => query.append(key + '[]', String(item)));
          else query.append(key, String(value));
        });
        return 'https://example.test/api-v2.php?' + query;
      }
    },
    matchMedia() { return { matches: true }; },
    addEventListener(name, listener) {
      if (!listeners.has(name)) listeners.set(name, []);
      listeners.get(name).push(listener);
    },
    dispatchEvent(event) { (listeners.get(event.type) || []).forEach(listener => listener(event)); }
  };
  const sourceRenders = [], controls = {};
  if (withFilters) {
    window.V2Results = { state: { items: [] }, render(items) {
      this.state.items = items;
      sourceRenders.push(items);
      results.replaceChildren(...items.map(item => {
        const card = new FakeElement('article');
        card.className = 'hotel-card'; card.setAttribute('data-hotel-id', String(item.id));
        card.textContent = 'Tourvisor hotel ' + item.id;
        return card;
      }));
      if (!items.length) { const empty = results.appendChild(new FakeElement('div')); empty.className = 'empty'; }
      window.dispatchEvent({ type: 'v2:results-rendered', detail: { items } });
    } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../v2/ds2-results-filters.js'), 'utf8'), { window, document }, { filename: 'ds2-results-filters.js' });
    rail.textContent = '';
    controls.price = rail.appendChild(new FakeElement('input'));
    controls.price.setAttribute('data-ds2-price', '');
    Object.assign(controls.price, { min: '40000', max: '250000', value: '250000' });
    const priceLabel = rail.appendChild(new FakeElement('small'));
    priceLabel.setAttribute('data-ds2-price-label', '');
    for (const [key, options] of Object.entries({ meal: ['', 'ai', 'hb'], stars: ['0', '3', '4', '5'], rating: ['0', '4', '4.5'], sea: ['0', '500'] })) {
      const field = rail.appendChild(new FakeElement('fieldset'));
      field.setAttribute('data-ds2-' + key + '-fieldset', '');
      controls[key] = options.map(value => {
        const input = field.appendChild(new FakeElement('input'));
        input.setAttribute('data-ds2-choice', ''); input.setAttribute('name', 'ds2-' + key);
        input.name = 'ds2-' + key; input.value = value; return input;
      });
    }
    controls.reset = rail.appendChild(new FakeElement('button'));
    controls.reset.setAttribute('data-ds2-reset', '');
    rail.appendChild(new FakeElement('b')).setAttribute('data-ds2-filter-count', '');
    rail.appendChild(new FakeElement('span')).setAttribute('data-ds2-filter-word', '');
  }
  const fetch = (url, options = {}) => new Promise((resolve, reject) => {
    if (!withAndromeda && String(url).includes('api-andromeda-search3-preview.php')) {
      resolve({ ok: true, status: 200, json: async () => ({ ok: true, data: {
        provider: 'andromeda', generation: JSON.parse(options.body).generation, hotels: [] } }) });
      return;
    }
    const query = new URL(String(url), window.location.href).searchParams;
    requests.push({ url: String(url), options, query, action: query.get('action'),
      body: typeof options.body === 'string' ? JSON.parse(options.body) : null,
      respond: (payload, meta = {}) => resolve({ ok: true, status: 200, ...meta, json: async () => payload }), reject });
  });
  window.fetch = fetch;
  let timerId = 0;
  const timers = new Map();
  const setTimer = (callback, delay = 0) => { const id = ++timerId; timers.set(id, { callback, delay }); return id; };
  const clearTimer = id => timers.delete(id);
  window.setTimeout = setTimer; window.clearTimeout = clearTimer;
  vm.runInNewContext(source, {
    window, document, fetch, URL, URLSearchParams, console, AbortController,
    MutationObserver: class {
      constructor(callback) { this.callback = callback; observers.push(this); }
      observe(element) { this.element = element; }
      disconnect() { this.element = null; }
    },
    setTimeout: setTimer, clearTimeout: clearTimer,
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } }
  }, { filename });
  return {
    window, document, body, layout, results, tvCard, lifecycle, requests, tools, summary, sort, rail, controls, sourceRenders, observers, timers,
    runTimer(delay) {
      const entry = Array.from(timers).find(([, timer]) => timer.delay === delay);
      if (!entry) return false;
      timers.delete(entry[0]); entry[1].callback(); return true;
    },
    click(element) {
      assert.ok(element, 'requested button exists');
      const event = { type: 'click', target: element, preventDefault() {},
        stopPropagation() { this.stopped = true; }, stopImmediatePropagation() { this.stopped = true; } };
      element.dispatchEvent(event);
      if (!event.stopped) document.dispatchEvent(event);
    },
    complete(items, searchId = 1000 + lifecycle.generation) {
      Object.assign(lifecycle, { searchId, pending: false });
      window.dispatchEvent({ type: 'v2:search-complete', detail: { searchId, progress: 100, items } });
    },
    reset(generation, snapshot) {
      Object.assign(lifecycle, { generation, snapshot, dirty: false, searchId: 0, pending: !!snapshot });
      window.dispatchEvent({ type: 'v2:search-reset', detail: { generation } });
    }
  };
}

const tick = () => new Promise(resolve => setImmediate(resolve));
const snapshot = () => ({
  departureId: '1', countryId: '4', dateFrom: '2027-02-01', dateTo: '2027-02-04',
  nightsFrom: '7', nightsTo: '10', adults: '2', childs: [4, 11], currency: 'RUB'
});
const response = (generation, hotels) => ({
  ok: true,
  data: { generation, provider: 'anex', hotels, external_search_pending: false, first_page_only: true }
});

const pointHotel = (id = 900, tours = [{ id: 'tv-900-a', date: '2027-02-01', nights: 7, price: 50000, meal: 'AI' }]) => ({
  id, name: 'Verified Tourvisor hotel ' + id, country: { id: 4, name: 'Турция' }, category: 4, rating: 4.5,
  price: Math.min(...tours.map(tour => tour.price)), tours
});
const pointButton = (page, id = 900) => page.results.querySelector('[data-anex-search3-card="' + id + '"]')?.querySelector('.anex-search3-tv-check');
const actionRequests = (page, action) => page.requests.filter(request => request.action === action);
async function pointReady(page = preview(), items = [hotel({ local_id: 900 })], criteria = snapshot(), broad = [{ id: 245 }]) {
  page.lifecycle.params = () => plain(page.lifecycle.snapshot || {});
  page.reset(1, criteria);
  page.requests[0].respond(response(1, items));
  page.complete(broad);
  await tick();
  return page;
}
async function pointFinish(page, returned = [pointHotel()], searchId = 9900) {
  const start = actionRequests(page, 'search_start').at(-1);
  assert.ok(start, 'one point search has started');
  start.respond({ searchId });
  await tick();
  const status = actionRequests(page, 'search_status').at(-1);
  assert.ok(status, 'point status request exists');
  assert.equal(status.query.get('searchId'), String(searchId));
  status.respond({ progress: 100, status: 'complete' });
  await tick();
  const result = actionRequests(page, 'search_results').at(-1);
  assert.ok(result, 'completed point search reads its results');
  assert.equal(result.query.get('limit'), '100');
  result.respond(returned);
  await tick();
}

test('point request retains the full captured contract and changes only hotelIds', () => {
  const api = helpers(), params = { ...snapshot(), meal: '7', hotelCategory: '5', hotelRating: '4',
    hotelTypes: ['family'], hotelServices: ['pool', 'beach'], arrivalId: '8', regionIds: ['6'], subregionIds: ['12'],
    operatorIds: ['9'], priceFrom: '20000', priceTo: '180000', onlyDirect: 'true', onlyCharter: 'false', hotelIds: [] };
  const before = plain(params), run = api.capture(params, 2, {});
  const query = api.pointSearchParams(run, 900);
  assert.deepEqual(plain(query), { ...before, hotelIds: [900] });
  params.childs[0] = 16; query.regionIds.push('77'); query.hotelServices.push('spa');
  assert.deepEqual(plain(run.params), before, 'form edits and request construction do not alter the captured request');
  for (const id of [null, '900', 0, -1, 1.5]) assert.equal(api.pointSearchParams(run, id), null);
  assert.equal(api.pointSearchParams({ ...run, params: { ...run.params, currency: 'USD' } }, 900), null);
  assert.equal(api.pointSearchParams({ ...run, params: { ...run.params, hotelIds: ['245'] } }, 900), null);
});

test('point response validates hotel identity and tour dates, nights and RUB without inventing offers', () => {
  const api = helpers(), params = snapshot();
  const tours = [
    { id: 'expensive', date: '2027-02-04', nights: 10, price: 60000, currency: 'RUB', meal: 'AI' },
    { id: 'cheap', date: '2027-02-01', nights: 7, price: 50000, meal: 'HB' }
  ];
  const input = [pointHotel(900, tours)], original = plain(input);
  const kept = api.pointSearchHotel(input, 900, params);
  assert.equal(kept.id, 900); assert.equal(kept.price, 50000);
  assert.deepEqual(plain(kept.tours.map(tour => tour.id)), ['cheap', 'expensive']);
  assert.deepEqual(input, original);
  assert.equal(api.pointSearchHotel([], 900, params), null);
  assert.equal(api.pointSearchHotel([pointHotel(900, [])], 900, params), null);
  for (const list of [null, {}, [pointHotel(901)], [pointHotel(), pointHotel()],
    [{ ...pointHotel(), country: { id: 1 } }],
    ...[{ price: 0 }, { price: -1 }, { price: true }, { price: 'NaN' }, { currency: 'USD' }, { date: '2027-01-31' },
      { date: '2027-02-30' }, { nights: 6 }, { nights: 11 }].map(overrides => [pointHotel(900, [{ ...tours[0], ...overrides }])])]) {
    assert.throws(() => api.pointSearchHotel(list, 900, params), 'malformed or incompatible response is rejected');
  }
  assert.throws(() => api.pointSearchHotel([{ ...pointHotel(), id: true }], 1, params));
});

test('point button needs completed raw broad absence and unchanged criteria, never absence from local filtering', async () => {
  const page = preview();
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel({ local_id: 900 })]));
  await tick();
  assert.equal(pointButton(page), null, 'a pending broad search cannot trigger point searches');
  page.complete([{ id: 245 }, { id: 900 }]);
  await tick();
  assert.equal(pointButton(page), null, 'raw broad ID remains known even if its native card is absent');
  page.reset(2, snapshot());
  page.requests[1].respond(response(2, [hotel({ local_id: 900 })]));
  page.complete([{ id: 245 }]);
  await tick();
  const button = pointButton(page);
  assert.match(button.textContent, /Проверить предложения Tourvisor/);
  page.lifecycle.params = () => ({ ...snapshot(), adults: '3' });
  page.click(button);
  await tick();
  assert.equal(actionRequests(page, 'search_start').length, 0, 'changed form is checked again at click time');
});

test('one explicit point click preserves broad state and renders readonly Tourvisor offers in the same card', async () => {
  const page = preview();
  const broad = [{ id: 245, price: 80000, tours: [{ id: 'original', price: 80000 }] }];
  const original = plain(broad);
  page.window.V2Results = { state: { items: broad }, render() { throw new Error('point must not call global renderer'); } };
  const criteria = { ...snapshot(), meal: '7', hotelServices: ['pool'], regionIds: ['6'], operatorIds: ['9'], hotelIds: [], onlyDirect: 'true' };
  await pointReady(page, [hotel({ local_id: 900 })], criteria, broad);
  const button = pointButton(page);
  page.click(button); page.click(button);
  await tick();
  assert.equal(actionRequests(page, 'search_start').length, 1);
  const request = actionRequests(page, 'search_start')[0];
  assert.deepEqual(request.query.getAll('hotelIds[]'), ['900']);
  assert.deepEqual(request.query.getAll('childs[]'), ['4', '11']);
  assert.deepEqual(request.query.getAll('operatorIds[]'), ['9']);
  assert.deepEqual(request.query.getAll('hotelServices[]'), ['pool']);
  assert.equal(request.query.get('onlyDirect'), 'true');
  assert.equal(request.query.get('dateFrom'), criteria.dateFrom);
  assert.equal(request.options.credentials, 'same-origin');
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  if (pointButton(page)) page.click(pointButton(page));
  assert.equal(actionRequests(page, 'search_start').length, 1, 'rerender does not restart a pending request');
  await pointFinish(page);
  const offers = page.results.querySelector('.anex-search3-tv-offers');
  assert.ok(offers); assert.match(offers.textContent, /Tourvisor.*50\s*000/);
  assert.equal(page.results.querySelectorAll('[data-hotel-id="900"]').length, 1);
  assert.equal(page.results.querySelector('.direct-tour'), null);
  assert.equal(page.results.querySelector('[data-tid]'), null);
  assert.equal(page.tvCard.parentNode, page.results);
  assert.equal(page.window.V2Runtime.state.searchId, 777);
  assert.equal(page.lifecycle.searchId, 1001);
  assert.deepEqual(page.window.V2Results.state.items, original);
  assert.equal(page.requests.filter(item => item.body).length, 1, 'point check never repeats ANEX');
  assert.equal(actionRequests(page, 'search_continue').length, 0);
  const counts = page.document.getElementById('anexSearch3SourceFilter').children.map(option => option.textContent.split(' · ').at(-1));
  assert.deepEqual(counts, ['2', '1', '0', '2', '1']);
  offers.open = true;
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  assert.equal(page.results.querySelector('.anex-search3-tv-offers').open, true);
});

test('all retained point offers can be revealed locally in price order and survive rerender', async () => {
  const page = await pointReady();
  const tours = Array.from({ length: 53 }, (_, i) => ({ id: 'tv-' + i, date: '2027-02-01', nights: 7,
    price: 50000 + i, meal: 'AI', roomType: 'Room ' + i }));
  const returned = [pointHotel(900, tours.slice().reverse())], original = plain(returned);
  page.click(pointButton(page)); await tick(); await pointFinish(page, returned);
  const offers = () => page.results.querySelector('.anex-search3-tv-offers');
  const rows = () => offers().querySelectorAll('.anex-search3-offer');
  const more = () => offers().querySelector('.anex-search3-tv-more');
  const requests = page.requests.length;
  assert.equal(rows().length, 20); assert.match(more().textContent, /ещё 20/);
  page.click(more());
  assert.equal(rows().length, 40); assert.match(more().textContent, /ещё 13/);
  assert.equal(offers().open, true);
  page.sort.dispatchEvent({ type: 'change' }); await tick();
  assert.equal(rows().length, 40, 'revealed count is preserved when cards are sorted');
  const lastButton = more(); page.document.activeElement = lastButton;
  page.click(lastButton);
  assert.equal(rows().length, 53); assert.equal(more(), null);
  assert.match(rows()[0].textContent, /Room 0/); assert.match(rows()[52].textContent, /Room 52/);
  assert.match(offers().querySelector('.anex-search3-tv-visible').textContent, /53 из 53/);
  assert.equal(offers().querySelector('.anex-search3-tv-visible').tabIndex, -1, 'final reveal leaves a focus target');
  assert.equal(offers().querySelector('.anex-search3-tv-visible').focused, true);
  assert.equal(page.requests.length, requests, 'revealing and sorting never request another supplier result');
  assert.equal(page.lifecycle.searchId, 1001); assert.equal(page.window.V2Runtime.state.searchId, 777);
  assert.deepEqual(returned, original);
});

test('point reveal is per hotel and resets only with a new search generation', async () => {
  const page = await pointReady(preview(), [hotel({ local_id: 900 }), hotel({ local_id: 901 })]);
  const tours = Array.from({ length: 21 }, (_, i) => ({ date: '2027-02-01', nights: 7, price: 50000 + i, meal: 'AI' }));
  const box = id => page.results.querySelector('[data-anex-search3-card="' + id + '"]').querySelector('.anex-search3-tv-offers');
  page.click(pointButton(page, 900)); await tick(); await pointFinish(page, [pointHotel(900, tours)]);
  const oldMore = box(900).querySelector('.anex-search3-tv-more');
  page.click(oldMore);
  page.click(pointButton(page, 901)); await tick(); await pointFinish(page, [pointHotel(901, tours)], 9901);
  assert.equal(box(900).querySelectorAll('.anex-search3-offer').length, 21);
  assert.equal(box(901).querySelectorAll('.anex-search3-offer').length, 20);
  page.reset(2, snapshot());
  page.requests.at(-1).respond(response(2, [hotel({ local_id: 900 })]));
  page.complete([{ id: 245 }]); await tick();
  page.click(oldMore);
  page.click(pointButton(page, 900)); await tick(); await pointFinish(page, [pointHotel(900, tours)], 9910);
  assert.equal(box(900).querySelectorAll('.anex-search3-offer').length, 20, 'old reveal does not leak into the new search');
});

test('revealing point offers obeys current meal and budget and retains offers after reset', async () => {
  const page = preview(true), broad = [{ id: 245, price: 80000, tours: [{ price: 80000, meal: 'AI' }] }];
  page.lifecycle.params = () => plain(page.lifecycle.snapshot || {});
  page.reset(1, snapshot()); page.window.V2Results.render(broad);
  page.requests[0].respond(response(1, [hotel({ local_id: 900 })])); page.complete(broad); await tick();
  const tours = Array.from({ length: 63 }, (_, i) => ({ id: 'tv-'+i, date: '2027-02-01', nights: 7,
    price: i < 10 ? 40000+i : 50000+i*100, meal: i < 10 ? 'HB' : 'AI' }));
  page.click(pointButton(page)); await tick(); await pointFinish(page, [pointHotel(900, tours)]);
  const box = () => page.results.querySelector('.anex-search3-tv-offers');
  page.click(box().querySelector('.anex-search3-tv-more'));
  const requests = page.requests.length;
  page.controls.price.value = '55000';
  page.rail.dispatchEvent({ type: 'input', target: page.controls.price });
  page.rail.dispatchEvent({ type: 'change', target: page.controls.meal.find(input => input.value === 'ai') }); await tick();
  assert.match(box().querySelector('.anex-search3-tv-visible').textContent, /40 из 41/);
  page.click(box().querySelector('.anex-search3-tv-more'));
  assert.equal(box().querySelectorAll('.anex-search3-offer').length, 41);
  assert.doesNotMatch(box().textContent, /HB|55\s*100/);
  page.rail.dispatchEvent({ type: 'click', target: page.controls.reset }); await tick();
  assert.match(box().querySelector('.anex-search3-tv-visible').textContent, /41 из 63/);
  page.click(box().querySelector('.anex-search3-tv-more')); page.click(box().querySelector('.anex-search3-tv-more'));
  assert.equal(box().querySelectorAll('.anex-search3-offer').length, 63);
  assert.equal(page.requests.length, requests);
});

test('point starts are one-shot after unknown or empty outcomes and limited to three unique hotels', async () => {
  const page = await pointReady(preview(), [900, 901, 902, 903].map(local_id => hotel({ local_id })));
  const first = pointButton(page, 900);
  page.click(first); await tick();
  const second = pointButton(page, 901);
  if (second) page.click(second);
  assert.equal(actionRequests(page, 'search_start').length, 1, 'only one point request may be active');
  actionRequests(page, 'search_start')[0].reject(new Error('network outcome unknown'));
  await tick();
  page.click(first); await tick();
  assert.equal(actionRequests(page, 'search_start').length, 1, 'unknown start is not replayed');
  page.click(pointButton(page, 901)); await tick();
  await pointFinish(page, [], 9901);
  const empty = page.results.querySelector('[data-hotel-id="901"]').querySelector('.anex-search3-tv-status');
  assert.match(empty.textContent, /не найден|не наш|нет|не вернул/i);
  const completed = pointButton(page, 901);
  if (completed) page.click(completed);
  assert.equal(actionRequests(page, 'search_start').length, 2, 'empty completion is not repeated');
  page.click(pointButton(page, 902)); await tick();
  await pointFinish(page, [], 9902);
  const fourth = pointButton(page, 903);
  if (fourth) page.click(fourth);
  await tick();
  assert.equal(actionRequests(page, 'search_start').length, 3);
});

test('new generation aborts a point request and ignores a late response without a status call', async () => {
  const page = await pointReady();
  page.click(pointButton(page)); await tick();
  const request = actionRequests(page, 'search_start')[0];
  page.reset(2, snapshot());
  assert.equal(request.options.signal.aborted, true);
  request.respond({ searchId: 9999 });
  await tick();
  assert.equal(actionRequests(page, 'search_status').length, 0);
  assert.equal(page.results.querySelector('.anex-search3-tv-offers'), null);
  assert.equal(page.requests.filter(item => item.body).length, 2, 'only the new user search starts ANEX again');
});

test('point polling is bounded and never loads incomplete results or creates a replacement search', async () => {
  const page = await pointReady();
  page.click(pointButton(page)); await tick();
  actionRequests(page, 'search_start')[0].respond({ searchId: 9900 });
  await tick();
  for (let index = 0; index < 8; index++) {
    const request = actionRequests(page, 'search_status')[index];
    assert.ok(request, 'status poll ' + index);
    request.respond({ progress: 20 });
    await tick();
    if (index < 7) {
      assert.equal(page.runTimer(2500), false, 'the eight-read budget must not expire after only 17.5 seconds');
      assert.equal(page.runTimer(7500), true); await tick();
    }
  }
  assert.equal(actionRequests(page, 'search_status').length, 8);
  assert.equal(actionRequests(page, 'search_results').length, 0);
  assert.equal(actionRequests(page, 'search_start').length, 1);
  assert.equal(page.runTimer(7500), false);
});

test('point polling accepts a slow completed search within the unchanged one-minute deadline', async () => {
  const page = await pointReady();
  page.click(pointButton(page)); await tick();
  actionRequests(page, 'search_start')[0].respond({ searchId: 9900 }); await tick();
  for (let index = 0; index < 4; index++) {
    actionRequests(page, 'search_status')[index].respond({ progress: 20 }); await tick();
    assert.equal(page.runTimer(7500), true); await tick();
  }
  actionRequests(page, 'search_status')[4].respond({ progress: 100 }); await tick();
  actionRequests(page, 'search_results')[0].respond([pointHotel()]); await tick();
  assert.ok(page.results.querySelector('.anex-search3-tv-offers'), 'a completion at 30 seconds is displayed');
  assert.equal(actionRequests(page, 'search_start').length, 1);
  assert.equal(actionRequests(page, 'search_status').length, 5);
  assert.equal(page.runTimer(60000), false, 'completion cancels the deadline');
});

test('point deadline stops a waiting poll without starting another request', async () => {
  const page = await pointReady();
  page.click(pointButton(page)); await tick();
  actionRequests(page, 'search_start')[0].respond({ searchId: 9900 }); await tick();
  actionRequests(page, 'search_status')[0].respond({ progress: 20 }); await tick();
  assert.equal(page.runTimer(60000), true); await tick();
  assert.equal(actionRequests(page, 'search_start').length, 1);
  assert.equal(actionRequests(page, 'search_status').length, 1);
  assert.equal(actionRequests(page, 'search_results').length, 0);
  assert.equal(page.runTimer(7500), false);
  assert.match(page.results.textContent, /Tourvisor не завершил расчёт/);
});

test('point offers obey local filters even when ANEX drops out, and a later native TV card wins without duplicates', async () => {
  const page = preview(true), broad = [{ id: 245, category: 4, price: 80000, tours: [{ price: 80000, meal: 'AI' }] }];
  page.lifecycle.params = () => plain(page.lifecycle.snapshot || {});
  page.reset(1, snapshot()); page.window.V2Results.render(broad);
  page.requests[0].respond(response(1, [hotel({ local_id: 900,
    tours: [{ ...hotel().tours[0], meal: 'HB', price: { amount: '70000', currency: 'RUB' } }] })]));
  page.complete(broad); await tick();
  page.click(pointButton(page)); await tick();
  await pointFinish(page);
  page.controls.price.value = '60000';
  page.rail.dispatchEvent({ type: 'input', target: page.controls.price });
  page.rail.dispatchEvent({ type: 'change', target: page.controls.meal.find(input => input.value === 'ai') });
  await tick();
  const card = page.results.querySelector('[data-hotel-id="900"]');
  assert.ok(card, 'matching Tourvisor point offer keeps its hotel visible');
  assert.match(card.textContent, /50\s*000/);
  assert.doesNotMatch(card.textContent, /70\s*000|Полупансион/);
  assert.equal(card.querySelectorAll('.anex-search3-source').some(badge => /ANEX API/.test(badge.textContent)), false,
    'filtered-out ANEX is not counted as an available offer');
  const counts = page.document.getElementById('anexSearch3SourceFilter').children.map(option => option.textContent.split(' · ').at(-1));
  assert.deepEqual(counts, ['1', '0', '0', '1', '0']);
  page.rail.dispatchEvent({ type: 'click', target: page.controls.reset });
  await tick();
  page.window.V2Results.render(broad.concat(pointHotel()));
  page.window.dispatchEvent({ type: 'v2:search-continued', detail: { searchId: 1001, items: broad.concat(pointHotel()) } });
  await tick();
  assert.equal(page.results.querySelectorAll('[data-hotel-id="900"]').length, 1);
  assert.equal(page.results.querySelector('[data-anex-search3-card="900"]'), null);
  assert.equal(page.results.querySelector('[data-hotel-id="900"]').querySelectorAll('.anex-search3-offers').length, 1);
  assert.equal(actionRequests(page, 'search_start').length, 1);
});

test('point checks reject unsafe gateway URLs and malformed returned identities without broad side effects', async () => {
  for (const url of ['http://[', 'https://attacker.example/api-v2.php', 'https://example.test/v2/api-v2.php',
    'https://example.test/_preview/search3-site-candidate/v2/api-v2.php']) {
    const page = await pointReady();
    page.window.V2Runtime.build = () => url;
    const button = pointButton(page);
    if (button) page.click(button);
    await tick();
    assert.equal(actionRequests(page, 'search_start').length, 0);
    assert.equal(page.requests.length, 1, 'invalid gateway does not send an extra fetch');
  }
  const page = await pointReady();
  page.click(pointButton(page)); await tick();
  await pointFinish(page, [pointHotel(901)]);
  assert.equal(page.results.querySelector('.anex-search3-tv-offers'), null);
  assert.equal(page.results.querySelector('[data-hotel-id="901"]'), null);
  assert.equal(page.tvCard.parentNode, page.results);
  assert.equal(page.window.V2Runtime.state.searchId, 777);
});

test('mapped ANEX-only hotel uses its catalog photo and text while keeping ANEX offers and source counts', async () => {
  const page = preview();
  const item = hotel({ local_id: 21477, name: 'MOVENPICK WATERPARK RESORT & SPA SOMA BAY', category: 5,
    country: 'Египет', region: 'Хургада', rating: 4.6,
    catalog: { hotel_id: 21477, source: 'tourvisor', image_url: 'https://images.example.com/movenpick.jpg',
      description: 'Пляжный отель\n<script>not markup</script>', address: 'Soma Bay <b>address</b>',
      subregion: 'Сома-Бэй', sea_distance: 50 },
    tours: [{ ...hotel().tours[0], price: { amount: '165578', currency: 'RUB' } }] });
  const original = plain(item);
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [item]));
  await tick();
  const card = () => page.results.querySelector('[data-anex-search3-card="21477"]');
  assert.equal(card().querySelector('img').src, item.catalog.image_url);
  assert.equal(card().querySelector('img').alt, item.name);
  assert.equal(card().querySelector('img').loading, 'lazy');
  assert.equal(card().querySelector('figcaption').textContent, 'Фото: Tourvisor');
  assert.match(card().textContent, /MOVENPICK.*5★/);
  assert.match(card().textContent, /Египет · Хургада · Сома-Бэй/);
  assert.match(card().textContent, /Рейтинг 4,6 · До моря: 50 м/);
  assert.match(card().querySelector('.anex-search3-source').textContent, /ANEX APIот 165\s*578 ₽/);
  assert.equal(card().querySelector('.anex-search3-about').querySelector('p').textContent, item.catalog.description);
  assert.equal(card().querySelector('script'), null);
  assert.equal(card().querySelector('b'), null);
  assert.equal(card().querySelector('.anex-search3-tv-source'), null);
  assert.match(page.summary.textContent, /^Tourvisor: 1 · Другие API: 1$/);
  card().querySelector('.anex-search3-about').open = true;
  card().querySelector('.anex-search3-offers').open = true;
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  assert.equal(card().querySelector('.anex-search3-about').open, true);
  assert.equal(card().querySelector('.anex-search3-offers').open, true);
  assert.deepEqual(item, original);

  const tv = page.results.appendChild(new FakeElement('article'));
  tv.className = 'hotel-card'; tv.setAttribute('data-hotel-id', '21477');
  tv.textContent = 'Tourvisor Movenpick';
  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: { items: [{ id: 21477, price: 180188 }] } });
  await tick();
  assert.equal(card(), null, 'one source-owned card remains when Tourvisor later returns the hotel');
  assert.equal(tv.querySelectorAll('.anex-search3-offers').length, 1);
  assert.match(tv.textContent, /165\s*578/);
  assert.equal(page.results.querySelectorAll('[data-hotel-id="21477"]').length, 1);
  assert.equal(page.requests.length, 1, 'catalog presentation adds no supplier request');
});

test('untrusted catalog identities and photo URLs cannot suppress valid ANEX offers', async () => {
  const page = preview();
  const catalog = { hotel_id: 900, source: 'tourvisor', image_url: 'https://images.example.com/hotel.jpg',
    description: 'Verified description', address: 'Verified address', sea_distance: 100 };
  const inputs = [
    { ...catalog, hotel_id: 901 }, { ...catalog, hotel_id: '901' }, { ...catalog, source: 'anex' },
    ...['javascript:alert(1)', 'data:image/svg+xml,<svg/>', '//images.example.com/photo',
      'http://images.example.com/photo', 'https://user:password@images.example.com/photo',
      'https://images.example.com:8443/photo', 'https://127.0.0.1/photo'].map(image_url => ({ ...catalog, image_url }))
  ].map((data, index) => hotel({ local_id: 900 + index,
    catalog: index < 3 ? data : { ...data, hotel_id: 900 + index } }));
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, inputs));
  await tick();
  const cards = page.results.querySelectorAll('.anex-search3-hotel');
  assert.equal(cards.length, inputs.length);
  cards.forEach((card, index) => {
    assert.equal(card.querySelector('img'), null, 'unsafe image ' + index);
    assert.match(card.textContent, /Фото пока нет/);
    assert.equal(card.querySelectorAll('.anex-search3-offer').length, 1);
    if (index < 3) {
      assert.equal(card.querySelector('.anex-search3-about'), null);
      assert.doesNotMatch(card.textContent, /Verified|До моря/);
    }
  });
  assert.equal(page.requests.length, 1);
});

test('a failed catalog photo falls back locally and is not retried by sorting', async () => {
  const page = preview();
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel({ local_id: 900,
    catalog: { hotel_id: 900, source: 'tourvisor', image_url: 'https://images.example.com/broken.jpg' } })]));
  await tick();
  const card = () => page.results.querySelector('.anex-search3-hotel');
  card().querySelector('img').dispatchEvent({ type: 'error' });
  assert.equal(card().querySelector('img'), null);
  assert.equal(card().querySelector('figcaption'), null);
  assert.match(card().textContent, /Фото пока нет/);
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  assert.equal(card().querySelector('img'), null);
  assert.equal(card().querySelectorAll('.anex-search3-offer').length, 1);
  assert.equal(page.requests.length, 1);
});

test('verified catalog sea distance participates in existing shared filtering and sorting', async () => {
  const page = preview(true);
  page.reset(1, snapshot());
  page.window.V2Results.render([{ id: 245, category: 4, seaDistance: 400, price: 100000,
    tours: [{ price: 100000, meal: 'AI' }] }]);
  page.requests[0].respond(response(1, [50, 1000].map((sea_distance, index) => hotel({ local_id: 900 + index,
    catalog: { hotel_id: 900 + index, source: 'tourvisor', sea_distance } }))));
  await tick();
  assert.equal(page.rail.querySelector('[data-ds2-sea-fieldset]').hidden, false);
  page.sort.value = 'sea'; page.sort.dispatchEvent({ type: 'change' });
  await tick();
  const ids = () => page.results.querySelectorAll('.hotel-card').map(card => card.dataset.hotelId);
  assert.deepEqual(ids(), ['900', '245', '901']);
  page.rail.dispatchEvent({ type: 'change', target: page.controls.sea.find(input => input.value === '500') });
  await tick();
  assert.deepEqual(ids(), ['900', '245']);
  page.rail.dispatchEvent({ type: 'click', target: page.controls.reset });
  await tick();
  assert.deepEqual(ids(), ['900', '245', '901']);
  assert.equal(page.requests.length, 1);
  const filter = helpers().filterItem;
  for (const sea_distance of [null, '', '100', -1, Infinity, 100001]) {
    assert.equal(filter(hotel({ catalog: { hotel_id: 245, source: 'tourvisor', sea_distance } })).seaDistance, null);
  }
});

test('shared filters require meal and budget on the same ANEX tour, then reset both sources locally', async () => {
  const page = preview(true);
  page.reset(1, snapshot());
  const tv = [{ id: 245, category: 4, rating: 4.2, seaDistance: 400, price: 100000,
    tours: [{ price: 100000, meal: { name: 'AI' } }] }];
  const original = plain(tv);
  page.window.V2Results.render(tv);
  const anex = hotel({ local_id: 900, rating: 4.5, tours: [
    ['40000', 'HB'], ['80000', 'AI'], ['110000', 'UAI']
  ].map(([amount, meal]) => ({ ...hotel().tours[0], price: { amount, currency: 'RUB' }, meal })) });
  page.requests[0].respond(response(1, [anex]));
  await tick();
  assert.equal(page.controls.price.min, '40000');
  assert.equal(page.controls.price.max, '110000');
  assert.equal(page.rail.querySelector('[data-ds2-sea-fieldset]').hidden, true);
  assert.equal(page.rail.querySelector('[data-ds2-rating-fieldset]').hidden, false);
  const budget = async value => {
    page.controls.price.value = String(value);
    page.rail.dispatchEvent({ type: 'input', target: page.controls.price });
    await tick();
  };
  await budget(60000);
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 1);
  assert.equal(page.results.querySelectorAll('[data-anex-search3-card]').length, 1);
  page.rail.dispatchEvent({ type: 'change', target: page.controls.meal.find(input => input.value === 'ai') });
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 0);
  assert.match(page.body.textContent, /По выбранным фильтрам предложений других поставщиков нет/);
  await budget(90000);
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 1);
  assert.equal(page.rail.querySelector('[data-ds2-filter-count]').textContent, '1');
  assert.match(page.results.textContent, /80\s*000/);
  assert.doesNotMatch(page.results.textContent, /40\s*000|110\s*000/);
  page.rail.dispatchEvent({ type: 'click', target: page.controls.reset });
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 2);
  assert.equal(page.rail.querySelector('[data-ds2-filter-count]').textContent, '2');
  assert.equal(page.requests.length, 1);
  assert.deepEqual(tv, original);
  assert.ok(page.sourceRenders.every(items => items.every(item => item.id === 245 && item.tours.every(tour => !tour.anex))));
});

test('late supplemental metadata keeps incomplete facets hidden and never activates outside ANEX preview', async () => {
  const page = preview(true);
  page.reset(1, snapshot());
  page.window.V2Results.render([{ id: 245, category: 4, rating: 4.2, seaDistance: 400, price: 100000,
    tours: [{ price: 100000, meal: { name: 'AI' } }] }]);
  page.rail.dispatchEvent({ type: 'change', target: page.controls.rating.find(input => input.value === '4.5') });
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 0);
  page.requests[0].respond(response(1, [hotel({ local_id: 900, rating: 0 })]));
  await tick();
  assert.equal(page.rail.querySelector('[data-ds2-rating-fieldset]').hidden, true);
  assert.equal(page.window.DS2ResultsFilters.state.rating, 0);
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 2);
  const state = plain(page.window.DS2ResultsFilters.state);
  page.window.location.pathname = '/poisk-turov/';
  assert.equal(page.window.DS2ResultsFilters.setSupplementalItems([]), false);
  assert.deepEqual(plain(page.window.DS2ResultsFilters.state), state);
  assert.equal(page.requests.length, 1);
});

test('local meal and budget filters retain an ANEX offer beyond the first five prices', async () => {
  const page = preview(true);
  page.reset(1, snapshot());
  page.window.V2Results.render([{ id: 245, category: 4, price: 100000,
    tours: [{ price: 100000, meal: 'AI' }] }]);
  const anex = hotel({ local_id: 900, tours: [
    ['40000', 'HB'], ['41000', 'HB'], ['42000', 'HB'], ['43000', 'HB'], ['44000', 'HB'], ['80000', 'AI']
  ].map(([amount, meal]) => ({ ...hotel().tours[0], price: { amount, currency: 'RUB' }, meal })) });
  const original = plain(anex);
  page.requests[0].respond(response(1, [anex]));
  await tick();
  assert.equal(page.results.querySelectorAll('.anex-search3-offer').length, 6);
  page.controls.price.value = '90000';
  page.rail.dispatchEvent({ type: 'input', target: page.controls.price });
  page.rail.dispatchEvent({ type: 'change', target: page.controls.meal.find(input => input.value === 'ai') });
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 1);
  assert.equal(page.results.querySelectorAll('.anex-search3-offer').length, 1);
  assert.match(page.results.textContent, /80\s*000/);
  assert.doesNotMatch(page.results.textContent, /Полупансион/);
  page.controls.price.value = '60000';
  page.rail.dispatchEvent({ type: 'input', target: page.controls.price });
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 0);
  page.rail.dispatchEvent({ type: 'click', target: page.controls.reset });
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 2);
  assert.equal(page.results.querySelectorAll('.anex-search3-offer').length, 6);
  assert.equal(page.requests.length, 1);
  assert.deepEqual(anex, original);
});

test('ANEX hotel validation accepts the bounded full supplier page and rejects oversized tours', () => {
  const valid = helpers().validHotel;
  const tour = hotel().tours[0];
  assert.equal(valid(hotel({ tours: Array(300).fill(tour) })), true);
  assert.equal(valid(hotel({ tours: Array(301).fill(tour) })), false);
});

test('text meals and ANEX aliases share the same filter without inventing missing meals', async () => {
  const page = preview(true);
  page.reset(1, snapshot());
  const tv = [{ id: 245, category: 4, rating: 4.2, price: 100000,
    tours: [{ price: 100000, meal: '  Всё включено  ' }, { price: 80000, meal: { name: 'HB' } }] }];
  const original = plain(tv);
  page.window.V2Results.render(tv);
  const anex = hotel({ local_id: 900, rating: 4.5, tours: ['AI-WITHOUT ALCOHOL', 'UAI', 'HB'].map(meal => ({ ...hotel().tours[0], meal })) });
  page.requests[0].respond(response(1, [anex]));
  await tick();
  assert.equal(page.rail.querySelector('[data-ds2-meal-fieldset]').hidden, false);
  page.rail.dispatchEvent({ type: 'change', target: page.controls.meal.find(input => input.value === 'ai') });
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 2);
  assert.equal(page.sourceRenders.at(-1)[0].tours.length, 1);
  assert.match(page.results.textContent, /Всё включено без алкоголя/);
  assert.match(page.results.textContent, /Ультра всё включено/);
  assert.doesNotMatch(page.results.textContent, /Полупансион/);
  assert.deepEqual(tv, original);
  for (const meal of ['', '  ', null, 7, {}, { id: 7 }]) {
    assert.equal(page.window.DS2ResultsFilters.hasMealData([{ tours: [{ meal }] }]), false);
  }
  const label = page.window.AnyTourAnexSearch3.mealLabel;
  assert.equal(label('RO'), 'Без питания');
  assert.equal(label('BB'), 'Завтраки');
  assert.equal(label('Supplier Special'), 'Supplier Special');
  assert.equal(label(null), '');
  assert.equal(page.requests.length, 1);
});

test('source counts explain overlap and Tourvisor progress never claims ANEX has finished', async () => {
  const page = preview();
  const progress = page.body.appendChild(new FakeElement('section'));
  progress.id = 'status';
  const head = progress.appendChild(new FakeElement('div'));
  head.className = 'search-progress-head';
  const title = head.appendChild(new FakeElement('strong'));
  title.textContent = 'Поиск завершён';
  page.reset(1, snapshot());
  page.window.dispatchEvent({ type: 'v2:search-complete', detail: { items: [{ id: 245 }] } });
  await tick();
  assert.equal(title.textContent, 'Tourvisor · Поиск завершён');
  assert.match(page.body.textContent, /ANEX: поиск/);
  page.requests[0].respond(response(1, [hotel(), hotel({ local_id: 900 })]));
  await tick();
  assert.match(page.document.getElementById('anexSearch3Results').textContent,
    /Отелей в выдаче: 2\. Через Tourvisor: 1, через ANEX\/Андромеду: 2\. В обоих источниках: 1/);
  assert.equal(page.summary.textContent, 'Tourvisor: 1 · Другие API: 2');
  page.window.dispatchEvent({ type: 'v2:search-complete', detail: { items: [{ id: 245 }] } });
  await tick();
  assert.equal(title.textContent, 'Tourvisor · Поиск завершён', 'no duplicate source label');
  const error = new FakeElement('div'); error.className = 'search-progress-error-copy';
  const errorTitle = error.appendChild(new FakeElement('strong')); errorTitle.textContent = 'Не получилось завершить поиск';
  const retry = error.appendChild(new FakeElement('button')); retry.textContent = 'Повторить поиск';
  progress.replaceChildren(error);
  page.window.dispatchEvent({ type: 'v2:search-error', detail: { phase: 'status' } });
  await tick();
  assert.equal(errorTitle.textContent, 'Tourvisor · Не получилось завершить поиск');
  assert.equal(error.querySelector('button'), retry);
  assert.equal(page.results.querySelectorAll('.anex-search3-offers').length, 2);
  page.reset(2, null);
  assert.equal(errorTitle.textContent, 'Не получилось завершить поиск');
  assert.equal(page.requests.length, 1);
});

test('late calendar presentation keeps the Tourvisor label without observing the whole page', async () => {
  const page = preview();
  page.reset(1, snapshot());
  const calendar = page.body.appendChild(new FakeElement('section')); calendar.id = 'currentPriceCalendar';
  const original = calendar.appendChild(new FakeElement('strong')); original.id = 'currentPriceCalendarTitle';
  original.textContent = 'Когда дешевле вылететь';
  page.window.dispatchEvent({ type: 'v2:search-complete' });
  await tick();
  assert.equal(original.textContent, 'Календарь цен Tourvisor');
  assert.equal(page.observers.length, 1);
  assert.equal(page.observers[0].element, calendar);
  const wrapped = calendar.appendChild(new FakeElement('strong')); wrapped.id = 'search3PriceCalendarTitle';
  wrapped.textContent = 'Календарь цен';
  page.observers[0].callback();
  page.observers[0].callback();
  assert.equal(wrapped.textContent, 'Календарь цен Tourvisor');
  page.reset(2, null);
  page.observers[0].callback();
  assert.equal(wrapped.textContent, 'Календарь цен');
  assert.equal(original.textContent, 'Когда дешевле вылететь');
});

test('capture keeps submitted parameters and labels independent of later form edits', () => {
  const api = helpers();
  const snapshot = {
    departureId: '1', countryId: '4', dateFrom: '2027-02-01', dateTo: '2027-02-04',
    nightsFrom: '7', nightsTo: '10', adults: '2', childs: [4, 11],
    hotelIds: ['245'], regionIds: [], currency: 'RUB'
  };
  const labels = { departure: 'Москва', country: 'Турция' };
  const expected = plain(snapshot);
  const run = api.capture(snapshot, 7, labels);

  snapshot.dateFrom = '2027-03-01';
  snapshot.childs[0] = 17;
  snapshot.hotelIds.push('999');
  labels.departure = 'Казань';
  assert.equal(run.generation, 7);
  assert.deepEqual(plain(run.params), expected);
  assert.deepEqual(plain(run.labels), { departure: 'Москва', country: 'Турция' });

  run.params.childs.push(2);
  assert.deepEqual(snapshot.childs, [17, 11]);
});

test('only the unchanged active generation can accept an ANEX response', () => {
  const api = helpers();
  const run = api.capture({ childs: [] }, 7, {});
  assert.equal(api.isCurrent(run, { generation: 7, dirty: false }), true);
  assert.equal(api.isCurrent(run, { generation: 8, dirty: false }), false);
  assert.equal(api.isCurrent(run, { generation: 7, dirty: true }), false);
  assert.equal(api.isCurrent(run, null), false);
  assert.equal(api.isCurrent(null, { generation: 7, dirty: false }), false);
  assert.equal(api.capture(null, 7, {}), null);
});

test('render eligibility requires a trusted positive catalog ID and a safe priced tour', () => {
  const api = helpers();
  assert.equal(api.validHotel(hotel()), true);
  for (const local_id of [undefined, null, false, '', '245', 0, -1, 1.5, NaN, Infinity]) {
    assert.equal(api.validHotel(hotel({ local_id })), false, 'local_id=' + String(local_id));
  }
  assert.equal(api.validHotel({ external_id: 245, ...hotel({ local_id: null }) }), false);
  for (const tours of [undefined, null, {}, [], [null], [{}]]) {
    assert.equal(api.validHotel(hotel({ tours })), false, 'tours=' + JSON.stringify(tours));
  }
  for (const amount of [undefined, null, '', 'NaN', 'Infinity', '-1.00', '0.00', '1e6', '<b>1</b>']) {
    const item = hotel();
    item.tours[0].price.amount = amount;
    assert.equal(api.validHotel(item), false, 'amount=' + String(amount));
  }
});

test('preview never introduces Tourvisor selection identifiers or calls its renderer', () => {
  assert.doesNotMatch(source, /\bdirect-tour\b|\bdata-tid\b/);
  assert.doesNotMatch(source, /\bV2Results(?:V5)?\s*\.\s*render\s*\(/);
  assert.doesNotMatch(source, /\bV2Runtime\s*\.\s*api\s*\(/);
});

test('production and neighboring previews have no network or DOM side effects', () => {
  for (const pathname of [
    '/', '/poisk-turov/', '/_preview/search3-site-candidate/poisk-turov/',
    '/_preview/search3-anex-candidate-other/poisk-turov/'
  ]) {
    let touches = 0;
    const document = new Proxy({ currentScript: null, readyState: 'complete' }, {
      get(target, name) {
        if (name in target) return target[name];
        return () => { touches++; return null; };
      }
    });
    const window = {
      location: { pathname, href: 'https://example.test' + pathname },
      addEventListener() { touches++; },
      fetch() { touches++; throw new Error('Unexpected fetch outside isolated preview'); }
    };
    vm.runInNewContext(source, {
      window, document, URL, console,
      fetch() { touches++; throw new Error('Unexpected fetch outside isolated preview'); }
    }, { filename });
    assert.equal(touches, 0, pathname);
  }
});

test('a generation submits one captured request and duplicate reset keeps it alive', async () => {
  const page = preview();
  const submitted = snapshot();
  const expected = plain(submitted);
  page.reset(1, submitted);
  assert.equal(page.requests.length, 1);
  const request = page.requests[0];
  assert.equal(request.url, 'https://example.test/_preview/search3-anex-candidate/v2/api-anex-search3-preview.php');
  assert.equal(request.options.method, 'POST');
  assert.equal(request.options.credentials, 'same-origin');
  assert.deepEqual(request.body, { generation: 1, params: expected, labels: { from: 'Москва', country: 'Турция' } });

  submitted.dateFrom = '2027-03-01';
  submitted.childs[0] = 17;
  page.window.dispatchEvent({ type: 'v2:search-reset', detail: { generation: 1 } });
  assert.equal(page.requests.length, 1);
  assert.equal(request.options.signal.aborted, false, 'duplicate reset must not cancel the only request');
  assert.deepEqual(request.body.params, expected);
  request.respond(response(1, [hotel({ local_id: 900, name: 'Captured search hotel' })]));
  await tick();
  assert.match(page.body.textContent, /Captured search hotel/);
});

test('new generation and dirty criteria discard late responses', async () => {
  const page = preview();
  page.reset(1, snapshot());
  page.reset(2, { ...snapshot(), dateFrom: '2027-03-01' });
  assert.equal(page.requests.length, 2);
  assert.equal(page.requests[0].options.signal.aborted, true);
  page.requests[1].respond(response(2, [hotel({ local_id: 900, name: 'Current generation hotel' })]));
  await tick();
  page.requests[0].respond(response(1, [hotel({ local_id: 901, name: 'Stale generation hotel' })]));
  await tick();
  assert.match(page.body.textContent, /Current generation hotel/);
  assert.doesNotMatch(page.body.textContent, /Stale generation hotel/);

  page.reset(3, snapshot());
  Object.assign(page.lifecycle, { generation: 4, dirty: true, snapshot: null });
  page.window.dispatchEvent({ type: 'v2:search-reset', detail: { generation: 4, dirty: true } });
  page.requests[2].respond(response(3, [hotel({ local_id: 902, name: 'Dirty criteria hotel' })]));
  await tick();
  assert.equal(page.requests.length, 3);
  assert.equal(page.document.getElementById('anexSearch3Results'), null);
  assert.doesNotMatch(page.body.textContent, /Dirty criteria hotel/);
});

test('mapped offers coexist with original TV cards and untrusted IDs never render', async () => {
  const page = preview();
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [
    hotel(), hotel({ local_id: 900, name: 'Separate mapped hotel' }),
    hotel({ local_id: null, name: 'Unmapped supplier hotel', external_id: 777 }),
    hotel({ local_id: '901', name: 'String ID hotel' }),
    hotel({ local_id: 0, name: 'Zero ID hotel' }),
    hotel({ local_id: 900, name: 'Duplicate local hotel' })
  ]));
  await tick();
  assert.equal(page.results.children[0], page.tvCard);
  assert.match(page.tvCard.textContent, /Existing Tourvisor hotel/);
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 2);
  assert.equal(page.tvCard.querySelectorAll('.anex-search3-offers').length, 1);
  assert.match(page.body.textContent, /Separate mapped hotel/);
  assert.doesNotMatch(page.body.textContent, /Unmapped supplier hotel|String ID hotel|Zero ID hotel|Duplicate local hotel/);
  const separate = page.document.getElementById('anexSearch3Results');
  assert.equal(separate.querySelectorAll('.hotel-card').length, 0);
  assert.equal(separate.querySelectorAll('.direct-tour').length, 0);
  assert.equal(separate.querySelectorAll('[data-tid]').length, 0);

  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: {} });
  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: {} });
  assert.equal(page.results.children[0], page.tvCard);
  assert.equal(page.tvCard.querySelectorAll('.anex-search3-offers').length, 1);
  assert.equal(page.body.querySelectorAll('#anexSearch3Results').length, 1);
});

test('source summary precedes the layout while ANEX-only cards join the common list', async () => {
  const page = preview();
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel({ local_id: 900 })]));
  await tick();
  const panel = page.document.getElementById('anexSearch3Results');
  assert.equal(page.results.closest('.results-layout'), page.layout);
  assert.equal(panel.parentNode, page.layout.parentNode);
  assert.equal(panel.nextSibling, page.layout);
  assert.equal(panel.closest('.results-layout'), null);
  assert.equal(page.results.querySelectorAll('.anex-search3-hotel').length, 1);
  assert.equal(panel.querySelectorAll('.hotel-card').length, 0);
});

test('missing filter implementation keeps the compatibility guard without a new request', async () => {
  const page = preview();
  page.window.DS2ResultsFilters = { state: {} };
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel(), hotel({ local_id: 900 })]));
  await tick();
  const rerender = () => page.window.dispatchEvent({ type: 'v2:results-rendered', detail: {} });
  for (const [key, value] of [['stars', '5'], ['rating', '8'], ['meal', 'AI'], ['seaMax', 500]]) {
    page.window.DS2ResultsFilters.state = { [key]: value };
    rerender();
    await tick();
    assert.equal(page.body.querySelectorAll('[data-anex-search3-row]').length, 0, key);
    assert.equal(page.body.querySelectorAll('.anex-search3-hotel').length, 0, key);
    assert.match(page.document.getElementById('anexSearch3Results').textContent, /сбросьте фильтры/);
  }
  page.window.DS2ResultsFilters.state = {};
  rerender();
  await tick();
  assert.equal(page.body.querySelectorAll('.anex-search3-offers').length, 2);
  assert.equal(page.results.children[0], page.tvCard);
  assert.equal(page.requests.length, 1);
});

test('changed form parameters hide ANEX even before lifecycle becomes dirty', async () => {
  const page = preview();
  let current = snapshot();
  page.lifecycle.params = () => current;
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel(), hotel({ local_id: 900 })]));
  await tick();
  assert.equal(page.body.querySelectorAll('.anex-search3-offers').length, 2);
  current = { ...current, childs: [5, 11] };
  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: {} });
  await tick();
  assert.equal(page.lifecycle.dirty, false);
  assert.equal(page.body.querySelectorAll('[data-anex-search3-row]').length, 0);
  assert.match(page.document.getElementById('anexSearch3Results').textContent, /Обновите поиск/);
  assert.equal(page.requests.length, 1);
});


test('supplier rejection, timeout and rate limit have distinct safe messages', () => {
  const api = helpers();
  assert.match(api.errorMessage('supplier_conditions_rejected'), /не принял/);
  assert.match(api.errorMessage('rate_limited'), /лимит/);
  assert.match(api.errorMessage('supplier_timeout'), /слишком много времени/);
  assert.match(api.errorMessage('invalid_request'), /Проверьте даты/);
  assert.doesNotMatch(api.errorMessage('secret <script>'), /secret|script/);
});

test('one mapped card retains TV controls and groups both sources inside its disclosure', async () => {
  const page = preview();
  const body = page.tvCard.appendChild(new FakeElement('div'));
  body.className = 'hotel-body';
  const best = body.appendChild(new FakeElement('div'));
  best.className = 'hotel-best-offer';
  const label = best.appendChild(new FakeElement('small'));
  label.textContent = 'За весь тур';
  const copy = body.appendChild(new FakeElement('div'));
  copy.className = 'search3-hotel-action__copy';
  const count = copy.appendChild(new FakeElement('strong'));
  count.textContent = '2 тура';
  const box = page.tvCard.appendChild(new FakeElement('div'));
  box.className = 'hotel-tours';
  box.hidden = true;
  const button = box.appendChild(new FakeElement('button'));
  button.className = 'direct-tour';
  button.setAttribute('data-tid', 'tv-original');
  const items = [{ id: 245, price: 2000, category: 4, rating: 4.5, tours: [{ id: 'a' }, { id: 'b' }] }];
  const before = plain(items);
  page.reset(1, snapshot());
  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: { items } });
  page.requests[0].respond(response(1, [hotel()]));
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 1);
  assert.equal(box.querySelector('.direct-tour'), button);
  assert.equal(button.getAttribute('data-tid'), 'tv-original');
  assert.equal(box.hidden, true);
  assert.equal(box.querySelector('.anex-search3-offers').tagName, 'SECTION');
  assert.match(box.textContent, /через Tourvisor.*ANEX API/);
  assert.match(best.querySelector('.anex-search3-tv-price-label').textContent, /Через Tourvisor/);
  assert.equal(label.textContent, 'За весь тур');
  assert.equal(count.textContent, 'Предложений: 3 ');
  assert.deepEqual(items, before, 'source results remain untouched');
  box.hidden = false;
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  assert.equal(box.hidden, false, 'rerender preserves the original disclosure');
  assert.equal(box.querySelector('.direct-tour'), button);
  assert.equal(box.querySelectorAll('.anex-search3-offers').length, 1);
  page.window.DS2ResultsFilters = { state: { stars: 5 } };
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  assert.equal(label.textContent, 'За весь тур');
  assert.equal(count.textContent, '2 тура');
  assert.equal(box.querySelector('.anex-search3-offers'), null);
  assert.equal(page.requests.length, 1);
});

test('combined sorting uses source minima and catalog rating, preserving open ANEX details', async () => {
  const page = preview();
  page.reset(1, snapshot());
  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: { items: [
    { id: 245, price: 5000, rating: 5, category: 4, seaDistance: 100 }
  ] } });
  const cheap = hotel({ local_id: 900, rating: 3, category: 3 });
  const mapped = hotel({ tours: [{ ...hotel().tours[0], price: { amount: '3000', currency: 'RUB' } }] });
  page.requests[0].respond(response(1, [mapped, cheap]));
  await tick();
  const ids = () => page.results.querySelectorAll('.hotel-card').map(card => card.dataset.hotelId);
  assert.deepEqual(ids(), ['900', '245']);
  page.results.querySelector('.anex-search3-hotel').querySelector('details').open = true;
  page.sort.value = 'rating';
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  assert.deepEqual(ids(), ['245', '900']);
  assert.equal(page.results.querySelector('.anex-search3-hotel').querySelector('details').open, true);
  assert.equal(page.requests.length, 1);
  const compare = helpers().compareCards;
  assert.ok(compare({ id: 1, seaDistance: 0 }, { id: 2, seaDistance: null }, 'sea') < 0);
  assert.ok(compare({ id: 1, price: 0 }, { id: 2, price: 100 }, 'price') > 0);
});

test('ANEX-only results clear the TV empty state and merge once when the TV card arrives later', async () => {
  const page = preview();
  page.tvCard.remove();
  page.tools.hidden = true;
  const empty = page.results.appendChild(new FakeElement('div'));
  empty.className = 'empty';
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel()]));
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 1);
  assert.equal(empty.hidden, true);
  assert.equal(page.tools.hidden, false);
  assert.equal(page.tools.querySelector('strong').textContent, 'Найдено отелей: 1');
  page.window.DS2ResultsFilters = { state: { stars: 5 } };
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  assert.equal(empty.hidden, false);
  assert.equal(page.tools.hidden, true);
  assert.equal(page.body.classList.contains('search3-has-results'), false);
  page.window.DS2ResultsFilters.state = {};
  page.sort.dispatchEvent({ type: 'change' });
  await tick();
  page.results.replaceChildren(page.tvCard);
  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: { items: [{ id: 245, price: 2000 }] } });
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 1);
  assert.equal(page.results.querySelectorAll('.anex-search3-hotel').length, 0);
  assert.equal(page.tvCard.querySelectorAll('.anex-search3-offers').length, 1);
  assert.equal(page.requests.length, 1);
});

test('duplicate TV identities are not silently merged', async () => {
  const page = preview();
  const duplicate = page.results.appendChild(new FakeElement('article'));
  duplicate.className = 'hotel-card';
  duplicate.dataset.hotelId = '245';
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel()]));
  await tick();
  assert.equal(page.results.querySelectorAll('.hotel-card').length, 2);
  assert.equal(page.results.querySelectorAll('.anex-search3-offers').length, 0);
});

test('source filter selects loaded hotels, preserves shared offers, and combines with budget/reset', async () => {
  const page = preview(true);
  const tv = [245, 500].map(id => ({ id, price: 100000, category: 4,
    tours: [{ price: 100000, meal: 'AI' }] }));
  const original = plain(tv);
  const visible = () => page.results.querySelectorAll('.hotel-card')
    .filter(card => !card.classList.contains('anex-search3-source-hidden')).map(card => card.dataset.hotelId);
  const choose = async value => {
    const select = page.document.getElementById('anexSearch3SourceFilter');
    select.value = value; select.dispatchEvent({ type: 'change' }); await tick();
  };
  page.reset(1, snapshot());
  page.window.V2Results.render(tv);
  await tick();
  await choose('anex');
  assert.deepEqual(visible(), []);
  assert.match(page.document.getElementById('anexSearch3Results').textContent, /ANEX: поиск/);
  page.requests[0].respond(response(1, [hotel(), hotel({ local_id: 900 })]));
  await tick();
  assert.deepEqual(visible(), ['245', '900']);
  assert.equal(page.document.getElementById('anexSearch3SourceFilter').value, 'anex');
  await choose('both');
  assert.deepEqual(visible(), ['245']);
  assert.match(page.results.querySelector('[data-hotel-id="245"]').textContent, /Tourvisor hotel 245.*ANEX API/);
  assert.equal(page.tools.querySelector('strong').textContent, 'Найдено отелей: 1');
  page.controls.price.value = '50000';
  page.rail.dispatchEvent({ type: 'input', target: page.controls.price });
  await tick();
  assert.deepEqual(visible(), []);
  assert.match(page.document.getElementById('anexSearch3Results').textContent, /Для выбранного источника отелей нет/);
  assert.doesNotMatch(page.document.getElementById('anexSearch3Results').textContent, /Найдено отелей:/);
  await choose('anex');
  assert.deepEqual(visible(), ['245', '900']);
  await choose('tourvisor');
  assert.deepEqual(visible(), []);
  page.rail.dispatchEvent({ type: 'click', target: page.controls.reset });
  await tick();
  assert.equal(page.document.getElementById('anexSearch3SourceFilter').value, 'all');
  assert.deepEqual(visible(), ['245', '900', '500']);
  assert.equal(page.tools.querySelector('strong').textContent, 'Найдено отелей: 3');
  assert.equal(page.requests.length, 1);
  assert.deepEqual(tv, original);
});

test('source selection survives source rerender and sorting, then resets for a new search', async () => {
  const page = preview();
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel({ local_id: 900 })]));
  await tick();
  const select = page.document.getElementById('anexSearch3SourceFilter');
  page.tvCard.style.setProperty('display', 'grid', 'important');
  select.value = 'tourvisor'; select.dispatchEvent({ type: 'change' });
  await tick();
  assert.equal(page.tvCard.classList.contains('anex-search3-source-hidden'), false);
  assert.equal(page.results.querySelector('.anex-search3-hotel').classList.contains('anex-search3-source-hidden'), true);
  assert.equal(page.results.querySelector('.anex-search3-hotel').style.getPropertyValue('display'), 'none');
  assert.equal(page.results.querySelector('.anex-search3-hotel').style.getPropertyPriority('display'), 'important');
  page.sort.dispatchEvent({ type: 'change' });
  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: {} });
  await tick();
  assert.equal(page.document.getElementById('anexSearch3SourceFilter'), select);
  assert.equal(select.value, 'tourvisor');
  page.reset(2, snapshot());
  assert.equal(select.value, 'all');
  assert.equal(page.tvCard.classList.contains('anex-search3-source-hidden'), false);
  page.requests[1].respond({ ok: false, error: 'rate_limited' });
  await tick();
  select.value = 'both'; select.dispatchEvent({ type: 'change' });
  await tick();
  assert.match(page.document.getElementById('anexSearch3Results').textContent, /лимит запросов ANEX/);
  assert.equal(page.tvCard.classList.contains('anex-search3-source-hidden'), true);
  assert.equal(page.tvCard.style.getPropertyValue('display'), 'none');
  page.reset(3, null);
  assert.equal(page.tvCard.classList.contains('anex-search3-source-hidden'), false);
  assert.equal(page.tvCard.style.getPropertyValue('display'), 'grid');
  assert.equal(page.tvCard.style.getPropertyPriority('display'), 'important');
  assert.equal(page.tools.querySelector('strong').textContent, 'Найдено 0 туров');
  assert.equal(page.requests.length, 2);
});

test('continued Tourvisor results preserve local ANEX-preview budget, meal and source selection', async () => {
  const page = preview(true);
  const tvHotel = (id, price, meal) => ({ id, category: 4, price, tours: [{ price, meal }] });
  const initial = [
    { ...tvHotel(245, 40000, 'HB'), tours: [{ price: 40000, meal: 'HB' }, { price: 90000, meal: 'AI' }] },
    tvHotel(500, 60000, 'AI'), tvHotel(600, 45000, 'HB'), tvHotel(700, 120000, 'AI')
  ];
  const continued = initial.concat(tvHotel(800, 65000, 'AI'), tvHotel(901, 30000, 'HB'), tvHotel(902, 150000, 'AI'));
  const original = plain(continued);
  const visible = () => page.results.querySelectorAll('.hotel-card')
    .filter(card => !card.classList.contains('anex-search3-source-hidden')).map(card => card.dataset.hotelId);
  const chooseSource = async value => {
    const select = page.document.getElementById('anexSearch3SourceFilter');
    select.value = value; select.dispatchEvent({ type: 'change' }); await tick();
  };
  page.reset(1, snapshot());
  page.window.V2Results.render(initial);
  page.requests[0].respond(response(1, [245, 900].map((local_id, index) => hotel({ local_id,
    tours: [{ ...hotel().tours[0], price: { amount: String(70000 + 5000 * index), currency: 'RUB' } }]
  }))));
  await tick();
  page.controls.price.value = '100000';
  page.rail.dispatchEvent({ type: 'input', target: page.controls.price });
  page.rail.dispatchEvent({ type: 'change', target: page.controls.meal.find(input => input.value === 'ai') });
  await tick();
  assert.deepEqual(visible(), ['500', '245', '900']);
  await chooseSource('both');
  page.window.V2Results.render(continued);
  await tick();
  assert.equal(page.controls.price.value, '100000', 'continuation does not widen the chosen budget');
  assert.equal(page.window.DS2ResultsFilters.state.meal, 'ai');
  assert.equal(page.document.getElementById('anexSearch3SourceFilter').value, 'both');
  assert.deepEqual(visible(), ['245']);
  assert.equal(page.tools.querySelector('strong').textContent, 'Найдено отелей: 1');
  const filtered = page.sourceRenders.at(-1);
  assert.deepEqual(filtered.map(item => item.id), [245, 500, 800]);
  assert.equal(filtered[0].price, 90000, 'hotel minimum excludes the cheaper nonmatching meal');
  assert.equal(filtered[0].tours.length, 1);
  await chooseSource('all');
  assert.deepEqual(visible(), ['500', '800', '245', '900'], 'combined sorting uses the filtered TV minimum');
  assert.equal(page.tools.querySelector('strong').textContent, 'Найдено отелей: 4');
  const choices = page.document.getElementById('anexSearch3SourceFilter').children.map(option => option.textContent);
  assert.deepEqual(choices.map(label => label.split(' · ').at(-1)), ['4', '2', '0', '3', '1']);
  assert.equal(page.requests.length, 1, 'filtering continued results sends no new ANEX request');
  assert.deepEqual(continued, original);
  page.reset(2, snapshot());
  page.window.V2Results.render(continued);
  await tick();
  assert.equal(page.window.DS2ResultsFilters.state.meal, '');
  assert.equal(page.controls.price.value, page.controls.price.max);
  assert.equal(page.document.getElementById('anexSearch3SourceFilter').value, 'all');
  assert.equal(visible().length, continued.length, 'a new search clears the previous local restrictions');
});


test('Andromeda renders independently, preserves its operator and ignores stale generations', async () => {
  const page = preview(false, true);
  page.reset(1, snapshot());
  assert.equal(page.requests.length, 2);
  const andromeda = page.requests.find(r => r.url.includes('api-andromeda-'));
  andromeda.respond({ ok: true, data: { generation: 1, provider: 'andromeda', hotels: [hotel({local_id:900,
    tours: [Object.assign({}, hotel().tours[0], {operator:'Test Operator'})]})] } });
  await tick();
  assert.match(page.results.textContent, /Андромеда/);
  assert.match(page.results.textContent, /Test Operator/);
  page.requests[0].respond({ok:false,error:'supplier_timeout'});
  await tick();
  assert.ok(page.results.querySelector('[data-hotel-id="900"]'));
  const select=page.document.getElementById('anexSearch3SourceFilter');
  select.value='andromeda';select.dispatchEvent({type:'change'});await tick();
  assert.equal(page.results.querySelector('[data-hotel-id="900"]').classList.contains('anex-search3-source-hidden'),false);
  page.reset(2,snapshot());
  assert.equal(page.results.querySelector('[data-anex-search3-card="900"]'),null);
});

test('Andromeda keeps unresolved namespaces, supplier content and loaded pages after a later failure', async () => {
  const page = preview(false, true);page.reset(1,snapshot());
  const tour=Object.assign({},hotel().tours[0],{provider:'andromeda',operator:'Intourist',offer_ref:'offer_a'});
  const unresolved=hotel({local_id:null,card_key:'andromeda:operator_5:900',provider:'andromeda',mapping_status:'unresolved',
    name:'Operator hotel',catalog:null,tours:[tour],andromeda_content:{source:'andromeda',image_url:'https://images.example.com/hotel.jpg',hotel_url:'https://operator.example.com/hotel'}});
  const request=page.requests.find(r=>r.url.includes('api-andromeda-'));
  request.respond({ok:true,data:{provider:'andromeda',generation:1,page:1,pages_count:3,hotels:[unresolved,hotel({local_id:900,tours:[tour]})]}});
  await tick();
  assert.ok(page.results.querySelector('[data-hotel-id="andromeda:operator_5:900"]'));
  assert.ok(page.results.querySelector('[data-hotel-id="900"]'));
  const second=page.requests.find(r=>r.body?.page===2);
  assert.ok(second);
  second.respond({ok:true,data:{provider:'andromeda',generation:1,page:2,pages_count:3,hotels:[Object.assign({},unresolved,{tours:[tour,Object.assign({},tour,{offer_ref:'offer_b',price:{amount:'200000',currency:'RUB'}})]})]}});
  await tick();
  const card=page.results.querySelector('[data-hotel-id="andromeda:operator_5:900"]');
  assert.equal(card.querySelectorAll('.anex-search3-offer').length,2);
  assert.match(card.textContent,/Фото: Андромеда/);
  assert.ok(card.querySelector('a'));
  const third=page.requests.find(r=>r.body?.page===3);assert.ok(third);
  third.respond({ok:false,error:'supplier_unavailable'});await tick();
  assert.ok(page.results.querySelector('[data-hotel-id="andromeda:operator_5:900"]'));
  assert.equal(page.requests.filter(r=>r.body?.page===3).length,1);
});


test('late Andromeda content survives ANEX-first merging and failed photos advance once', async () => {
  const page=preview(false,true);page.reset(1,snapshot());
  const catalog={hotel_id:900,source:'tourvisor',image_url:'https://images.example.com/catalog.jpg'};
  page.requests.find(r=>r.url.includes('api-anex-')).respond(response(1,[hotel({local_id:900,catalog})]));
  await tick();
  const content={source:'andromeda',image_url:'https://images.example.com/supplier.jpg',hotel_url:'https://operator.example.com/hotel'};
  page.requests.find(r=>r.url.includes('api-andromeda-')).respond({ok:true,data:{provider:'andromeda',generation:1,page:1,pages_count:1,
    hotels:[hotel({local_id:900,andromeda_content:content})]}});
  await tick();
  const card=()=>page.results.querySelector('[data-hotel-id="900"]');
  assert.match(card().textContent,/Описание отеля у поставщика/);
  assert.equal(card().querySelector('img').src,catalog.image_url);
  card().querySelector('img').dispatchEvent({type:'error'});
  assert.equal(card().querySelector('img').src,content.image_url);
  assert.equal(card().querySelector('figcaption').textContent,'Фото: Андромеда');
  page.sort.dispatchEvent({type:'change'});await tick();
  assert.equal(card().querySelector('img').src,content.image_url,'failed catalog image is not retried');
  card().querySelector('img').dispatchEvent({type:'error'});
  assert.equal(card().querySelector('img'),null);
  assert.equal(card().querySelectorAll('.anex-search3-photo-empty').length,1);
  assert.equal(page.requests.length,2,'image fallback does not request more offers');
});

test('a later Andromeda page fills missing content without changing accepted hotel identity', () => {
  const api=helpers(), first=hotel({local_id:900,andromeda_content:{source:'andromeda',image_url:null,hotel_url:null}});
  const next=hotel({local_id:900,andromeda_content:{source:'andromeda',image_url:'https://images.example.com/later.jpg',hotel_url:'https://operator.example.com/hotel'}});
  const original=JSON.stringify(first);
  const rows=api.combineSources({andromeda:[first,next]});
  assert.equal(rows.length,1);assert.equal(rows[0].local_id,900);
  assert.equal(rows[0].andromeda_content.image_url,next.andromeda_content.image_url);
  assert.equal(JSON.stringify(first),original,'earlier saved page remains unchanged');
});


test('ANEX-only Andromeda correlation link scopes supplier requests without altering ANEX criteria', async () => {
  const page=preview(false,true);
  page.window.location.href='https://anytoour.ru/_preview/search3-anex-candidate/poisk-turov/?andromeda_operator=5';
  page.reset(1,snapshot());
  const anex=page.requests.find(r=>r.url.includes('api-anex-'));
  const andromeda=page.requests.find(r=>r.url.includes('api-andromeda-'));
  assert.deepEqual(plain(andromeda.body.andromeda_operator_ids),['5']);
  assert.equal(anex.body.andromeda_operator_ids,undefined);
  andromeda.respond({ok:true,data:{provider:'andromeda',generation:1,page:1,pages_count:2,hotels:[hotel()]}});
  await tick();
  const next=page.requests.find(r=>r.body.page===2);
  assert.deepEqual(plain(next.body.andromeda_operator_ids),['5']);
  assert.match(page.document.getElementById('anexSearch3Results').textContent,/Андромеда \(ANEX\)/);
});
