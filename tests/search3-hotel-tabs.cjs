'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('v2/search-lifecycle-v6.js', 'utf8');
const path = '/_preview/search3-local-candidate/poisk-turov/';
const date = new Date(Date.now() + 86400000 * 7).toISOString().slice(0, 10);
const query = `from=1&country=4&dateFrom=${date}&dateTo=${date}&daysFrom=7&daysTill=10&count_people=2&child_count=0`;
async function setup(suffix = '', options = {}) {
  const fields = { ...Object.fromEntries(new URLSearchParams(query)), ...options.fields };
  const elements = Object.fromEntries(Object.entries(fields).map(([name, value]) => [name, { value, dataset: {}, removeAttribute() {} }]));
  const button = {}, calls = [], profileReads = [], renders = [], timers = [], events = [], status = {}, results = { querySelector: () => null };
  const form = { elements, querySelector: selector => selector === '.primary' ? button : null, querySelectorAll: () => [], addEventListener() {} };
  const nodes = { tourSearch: form, status, results, selectedTour: {}, resultsTools: {} };
  const location = new URL('https://anytoour.ru' + (options.path || path) + '?' + new URLSearchParams(fields) + suffix);
  const document = { body: { classList: { contains: () => true } }, getElementById: id => nodes[id] };
  const window = { location, history: { state: null, replaceState() {}, pushState() {} }, addEventListener() {}, dispatchEvent: e => events.push(e.type),
    V2Runtime: { setSearchId() {}, api: async (action, params) => { calls.push({ action, params }); if (options.fail) throw new Error('expired'); return action === 'search_start' ? { searchId: 42 } : action==='search_results'&&!options.empty ? [{id:101,tours:[{id:'exact-offer',provider:'tourvisor',price:125000}]}] : []; } },
    V2Results: { render(list) { renders.push(list); } }, V2Catalogs: { renderChildAges() {}, init: async () => {}, updateServiceCount() {} },
    Search3CanonicalProfilesV1: { current: () => options.noProfileOwner ? null : ({ readProfile: async id => { profileReads.push(id); if(options.profileRead)return options.profileRead(id); return {id}; } }) } };
  const context = { window, document, URL, URLSearchParams, Intl, console, Date, FormData: class { get(key) { return elements[key]?.value || ''; } getAll(key) { const value = this.get(key); return value ? [value] : []; } }, CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } }, setTimeout: fn => (timers.push(fn), timers.length), clearTimeout() {}, requestAnimationFrame() {} };
  vm.runInNewContext(source, context);
  for (let i = 0; i < 12; i++) await Promise.resolve();
  return { lifecycle: window.V2SearchLifecycle, calls, profileReads, renders, timers, events, status };
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
  const fullHotel={...hotel,tours:[...hotel.tours,{id:'second-exact-offer',provider:'tourvisor'}]};
  assert.equal(fresh.lifecycle.hotelDetailUrl(hotel,[fullHotel]),'','a narrowed offer group must retain its local inline selection path');
  assert.ok(fresh.lifecycle.hotelDetailUrl(fullHotel,[fullHotel]),'complete groups retain their independent hotel tab');
  assert.ok(fresh.lifecycle.hotelDetailUrl(hotel,[{...fullHotel,anytourHotelId:902},hotel]),'only the same canonical hotel controls transfer eligibility');
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
  assert.equal(detail.profileReads.length, 0, 'working search keeps its existing batch path');
  const expired = await setup('&search3_hotel=901&search3_search=42', { fail: true });
  assert.equal(expired.calls.length, 1);
  assert.match(expired.status.textContent, /больше недоступен/);
  assert.deepEqual(expired.profileReads, ['901'], 'expiry reads the canonical profile, never a supplier hotel ID');
  assert.equal(expired.lifecycle.hotelDetail.profileOnly, true);
  assert.equal(expired.renders.length, 1);
  assert.equal(expired.renders[0].length, 0, 'no old offers are handed to the renderer');
  const empty = await setup('&search3_hotel=901&search3_search=42', {empty:true});
  assert.deepEqual(empty.profileReads, ['901'], 'an empty expired response also retains the hotel profile');
  assert.equal(empty.lifecycle.hotelDetail.profileOnly, true);
  await empty.lifecycle.submit();
  assert.equal(empty.lifecycle.hotelDetail, null, 'manual refresh leaves profile-only mode');
  assert.equal(empty.calls.filter(call=>call.action==='search_start').length, 1, 'manual refresh starts exactly one new search');
  assert.equal(empty.profileReads.length, 1, 'manual refresh does not repeat the expired-profile read');
  const missing = await setup('&search3_hotel=901&search3_search=42', {fail:true,profileRead:async()=>{throw new Error('404');}});
  assert.match(missing.status.textContent, /Описание отеля сейчас недоступно/);
  assert.equal(missing.renders[0].length, 0);
  const noReader = await setup('&search3_hotel=901&search3_search=42', {fail:true,noProfileOwner:true});
  assert.match(noReader.status.textContent, /Описание отеля сейчас недоступно/);
  assert.equal(noReader.profileReads.length, 0);
  const past = new Date(Date.now()-86400000).toISOString().slice(0,10);
  const oldDates = await setup('&search3_hotel=901&search3_search=42', {fields:{dateFrom:past,dateTo:past}});
  assert.equal(oldDates.calls.length, 0, 'past dates never submit a supplier request');
  assert.deepEqual(oldDates.profileReads, ['901'], 'past trip dates do not remove the hotel profile');
  assert.match(oldDates.status.textContent, /Дата вылета не может быть в прошлом/);
  let finishProfile;
  const slow = await setup('&search3_hotel=901&search3_search=42', {fail:true,profileRead:()=>new Promise(resolve=>finishProfile=resolve)});
  slow.lifecycle.markDirty('fixture-change');const dirtyStatus=slow.status.textContent;
  finishProfile({id:901});for(let i=0;i<12;i++)await Promise.resolve();
  assert.equal(slow.renders.length, 0, 'late profile must not restore a changed search');
  assert.equal(slow.status.textContent,dirtyStatus);
  for (const marker of ['&search3_hotel=bad&search3_search=42', '&search3_search=42', '&search3_hotel=901', '&search3_hotel=901&search3_hotel=902&search3_search=42', '&search3_hotel=901&search3_search=42&search3_search=43']) {
    const invalid = await setup(marker);
    assert.equal(invalid.calls.length, 0, 'invalid or ambiguous detail identity cannot start a new search');
    assert.equal(invalid.profileReads.length, 0, 'ambiguous hotel identity cannot read a profile');
    assert.equal(invalid.timers.length, 0);
  }
  await detail.lifecycle.submit();
  assert.equal(detail.lifecycle.hotelDetail, null, 'explicit new search leaves detail mode');
  const legacy = await setup('', { path: '/poisk-turov/' });
  await legacy.timers[0]();
  assert.equal(legacy.lifecycle.hotelDetailUrl(hotel), '', 'production and other previews do not opt in');
  console.log('SEARCH3_HOTEL_TABS_OK');
})().catch(error => { console.error(error); process.exitCode = 1; });
