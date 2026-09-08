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
  assert.equal(window.AnyTourAnexSearch3.version, 1);
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
    this.style = { setProperty(name, value) { this[name] = value; } };
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
  matches(selector) {
    const attribute = selector.match(/\[([^\]]+)\]$/);
    if (attribute) return this.getAttribute(attribute[1]) !== null
      && (!selector.slice(0, attribute.index) || this.matches(selector.slice(0, attribute.index)));
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
}

function preview() {
  const listeners = new Map();
  const requests = [];
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
    querySelector: selector => body.querySelector(selector),
    querySelectorAll: selector => body.querySelectorAll(selector),
    addEventListener() {}
  };
  const lifecycle = { generation: 0, dirty: false, snapshot: null };
  const window = {
    location: {
      pathname: '/_preview/search3-anex-candidate/poisk-turov/',
      href: 'https://example.test/_preview/search3-anex-candidate/poisk-turov/',
      origin: 'https://example.test'
    },
    V2SearchLifecycle: lifecycle,
    addEventListener(name, listener) {
      if (!listeners.has(name)) listeners.set(name, []);
      listeners.get(name).push(listener);
    },
    dispatchEvent(event) { (listeners.get(event.type) || []).forEach(listener => listener(event)); }
  };
  const fetch = (url, options) => new Promise(resolve => {
    requests.push({ url: String(url), options, body: JSON.parse(options.body), respond: payload => resolve({ ok: true, json: async () => payload }) });
  });
  window.fetch = fetch;
  let timerId = 0;
  vm.runInNewContext(source, {
    window, document, fetch, URL, console, AbortController,
    setTimeout: () => ++timerId, clearTimeout() {},
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } }
  }, { filename });
  return {
    window, document, body, layout, results, tvCard, lifecycle, requests, tools, summary, sort,
    reset(generation, snapshot) {
      Object.assign(lifecycle, { generation, snapshot, dirty: false });
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

test('local result filters hide ANEX offers until cleared without a new request', async () => {
  const page = preview();
  page.window.DS2ResultsFilters = { state: {} };
  page.reset(1, snapshot());
  page.requests[0].respond(response(1, [hotel(), hotel({ local_id: 900 })]));
  await tick();
  const rerender = () => page.window.dispatchEvent({ type: 'v2:results-rendered', detail: {} });
  for (const [key, value] of [['stars', '5'], ['rating', '8'], ['meal', 'AI'], ['seaMax', 500]]) {
    page.window.DS2ResultsFilters.state = { [key]: value };
    rerender();
    assert.equal(page.body.querySelectorAll('[data-anex-search3-row]').length, 0, key);
    assert.equal(page.body.querySelectorAll('.anex-search3-hotel').length, 0, key);
    assert.match(page.document.getElementById('anexSearch3Results').textContent, /сбросьте фильтры/);
  }
  page.window.DS2ResultsFilters.state = {};
  rerender();
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
  assert.match(label.textContent, /Через Tourvisor/);
  assert.equal(count.textContent, '3 предложений · 2 источника');
  assert.deepEqual(items, before, 'source results remain untouched');
  box.hidden = false;
  page.sort.dispatchEvent({ type: 'change' });
  assert.equal(box.hidden, false, 'rerender preserves the original disclosure');
  assert.equal(box.querySelector('.direct-tour'), button);
  assert.equal(box.querySelectorAll('.anex-search3-offers').length, 1);
  page.window.DS2ResultsFilters = { state: { stars: 5 } };
  page.sort.dispatchEvent({ type: 'change' });
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
  assert.equal(empty.hidden, false);
  assert.equal(page.tools.hidden, true);
  assert.equal(page.body.classList.contains('search3-has-results'), false);
  page.window.DS2ResultsFilters.state = {};
  page.sort.dispatchEvent({ type: 'change' });
  page.results.replaceChildren(page.tvCard);
  page.window.dispatchEvent({ type: 'v2:results-rendered', detail: { items: [{ id: 245, price: 2000 }] } });
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
