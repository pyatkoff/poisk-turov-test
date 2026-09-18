'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('v2/search-lifecycle-v6.js', 'utf8');
const path = '/_preview/search3-local-candidate/poisk-turov/';
const date = new Date(Date.now() + 86400000 * 7).toISOString().slice(0, 10);
const query = `from=1&country=4&dateFrom=${date}&dateTo=${date}&daysFrom=7&daysTill=10&count_people=2&child_count=0`;
async function setup(suffix = '', options = {}) {
  const fields = Object.fromEntries(new URLSearchParams(query));
  const elements = Object.fromEntries(Object.entries(fields).map(([name, value]) => [name, { value, dataset: {}, removeAttribute() {} }]));
  const button = {}, calls = [], timers = [], events = [], status = {}, results = { querySelector: () => null };
  const form = { elements, querySelector: () => button, querySelectorAll: () => [], addEventListener() {} };
  const nodes = { tourSearch: form, status, results, selectedTour: {}, resultsTools: {} };
  const location = new URL('https://anytoour.ru' + (options.path || path) + '?' + query + suffix);
  const document = { body: { classList: { contains: () => true } }, getElementById: id => nodes[id] };
  const window = { location, history: { state: null, replaceState() {}, pushState() {} }, addEventListener() {}, dispatchEvent: e => events.push(e.type),
    V2Runtime: { setSearchId() {}, api: async (action, params) => { calls.push({ action, params }); if (options.fail) throw new Error('expired'); return action === 'search_start' ? { searchId: 42 } : []; } },
    V2Results: { render() {} }, V2Catalogs: { renderChildAges() {}, init: async () => {}, updateServiceCount() {} } };
  const context = { window, document, URL, URLSearchParams, Intl, console, Date, FormData: class { get(key) { return elements[key]?.value || ''; } getAll(key) { const value = this.get(key); return value ? [value] : []; } }, CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } }, setTimeout: fn => (timers.push(fn), timers.length), clearTimeout() {}, requestAnimationFrame() {} };
  vm.runInNewContext(source, context);
  for (let i = 0; i < 12; i++) await Promise.resolve();
  return { lifecycle: window.V2SearchLifecycle, calls, timers, events, status };
}
(async () => {
  const fresh = await setup('&utm_source=owner&yclid=example');
  assert.equal(fresh.timers.length, 1, 'first direct URL retains its legitimate initial submit');
  await fresh.timers[0]();
  const hotel = { anytourHotelId: 901, tours: [{ id: 'exact-offer', provider: 'tourvisor' }] };
  const link = new URL(fresh.lifecycle.hotelDetailUrl(hotel));
  assert.equal(link.pathname, path);
  assert.equal(link.searchParams.get('search3_hotel'), '901');
  assert.equal(link.searchParams.get('search3_search'), '42');
  assert.equal(link.searchParams.get('utm_source'), 'owner');
  assert.equal(link.searchParams.get('yclid'), 'example');
  assert.equal(link.searchParams.get('dateFrom'), date);
  for (const patch of [{ cachedListing: true }, { provider: 'andromeda' }, { selectionEnabled: false }]) {
    assert.equal(fresh.lifecycle.hotelDetailUrl({ ...hotel, tours: [{ ...hotel.tours[0], ...patch }] }), '', 'unsupported group keeps all existing offers instead of lossy transfer');
  }
  assert.equal(fresh.lifecycle.hotelDetailUrl({ ...hotel, anytourHotelId: 'javascript:1' }), '');
  const detail = await setup('&search3_hotel=901&search3_search=42');
  assert.deepEqual(detail.calls.map(call => call.action), ['search_results']);
  assert.equal(detail.calls[0].params.searchId, 42);
  assert.equal(detail.timers.length, 0, 'detail does not launch another search or polling loop');
  assert.equal(detail.events.includes('v2:search-reset'), false, 'detail does not launch provider cohorts');
  assert.equal(detail.lifecycle.hotelDetail.hotelId, '901');
  assert.equal(new URL(detail.lifecycle.hotelDetail.returnUrl).searchParams.get('search3_restore'), '1');
  assert.equal(detail.lifecycle.hotelDetailUrl(hotel), '', 'internal hotel actions stay in this tab');
  const expired = await setup('&search3_hotel=901&search3_search=42', { fail: true });
  assert.equal(expired.calls.length, 1);
  assert.match(expired.status.textContent, /больше недоступен/);
  for (const marker of ['&search3_hotel=bad&search3_search=42', '&search3_search=42', '&search3_hotel=901', '&search3_hotel=901&search3_hotel=902&search3_search=42', '&search3_hotel=901&search3_search=42&search3_search=43']) {
    const invalid = await setup(marker);
    assert.equal(invalid.calls.length, 0, 'invalid or ambiguous detail identity cannot start a new search');
    assert.equal(invalid.timers.length, 0);
  }
  await detail.lifecycle.submit();
  assert.equal(detail.lifecycle.hotelDetail, null, 'explicit new search leaves detail mode');
  const legacy = await setup('', { path: '/poisk-turov/' });
  await legacy.timers[0]();
  assert.equal(legacy.lifecycle.hotelDetailUrl(hotel), '', 'production and other previews do not opt in');
  console.log('SEARCH3_HOTEL_TABS_OK');
})().catch(error => { console.error(error); process.exitCode = 1; });
