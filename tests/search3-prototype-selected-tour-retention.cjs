'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('v2/prototype-search/app.js', 'utf8');
const start = source.indexOf("const selectedTourKey='anytour.real.selected-tour.v1'");
const end = source.indexOf('\nlet compareView=', start);
assert.ok(start >= 0 && end > start, 'selected-tour lifecycle block is present');

const storage = new Map();
let now = Date.parse('2026-09-22T12:00:00Z');
let timer = 0;
const context = {
  Date: class extends Date { static now() { return now; } },
  JSON,
  Number,
  String,
  Array,
  Object,
  RegExp,
  structuredClone,
  hotels: [],
  data: { text: value => String(value ?? '') },
  getStored: (key, fallback) => storage.has(key) ? JSON.parse(storage.get(key)) : fallback,
  saveStored: (key, value) => storage.set(key, JSON.stringify(value)),
  clearStored: key => storage.delete(key),
  addDays: (day, count) => new Date(Date.parse(day + 'T12:00:00Z') + count * 86400000).toISOString().slice(0, 10),
  savedFlightTextPlain: offer => offer.savedFlightText || 'TK 3025 10:00 · TK 3024 18:00',
  setTimeout: () => ++timer,
  clearTimeout: () => {},
  updateNav: () => {},
  showModal: () => {},
  renderRealOffer: () => {},
  editSearch: () => {},
  closeModal: () => {},
  openLeadPreview: () => {},
};
vm.createContext(context);
vm.runInContext(source.slice(start, end) + `\nthis.selectedTourTest={
  snapshot:selectedTourOfferSnapshot, hotelSnapshot:selectedTourHotelSnapshot,
  store:storeSelectedTour, restore:restoreSelectedTour, read:readSelectedTour,
  demote:demoteSavedTour, remove:removeSelectedTour, undo:undoSelectedTour,
  same:sameSelectedTourConditions, ttl:selectedTourTTL
};`, context);

const api = context.selectedTourTest;
const hotel = {id: 77, name: 'Exact Hotel', resort: 'Side', country: '4', stars: 5, rating: 4.8, photos: ['hotel.jpg'], legacyIds: [701], raw: {arrival: 'AYT'}};
const offer = {
  key: 'tourvisor:123', hotelId: 77, day: '2026-10-05', nights: 7,
  total: 1500000, room: 'Deluxe Sea View', placement: '2 ADL', adults: 2, ages: [],
  origin: 'Москва', meal: 'Всё включено', operator: 'FUN&SUN', flight: 'charter',
  provider: 'tourvisor', raw: {id: 123, selectionEnabled: true},
  search: {origin: 'Москва', country: '4', from: '2026-10-05', to: '2026-10-11', minNights: 7, maxNights: 7, adults: 2, ages: []},
  tour: {id: 123, price: 1500000, secretSupplierContext: 'must-not-persist'},
  variants: [{forward: [{company: 'TK', number: '3025'}]}], flightChoiceId: '0'
};

context.hotels.push(hotel);
api.store(offer, hotel);
const record = JSON.parse(storage.get('anytour.real.selected-tour.v1'));
assert.equal(record.observedAt, now, '24h starts at the real selection observation');
assert.equal(record.offer.total, 1500000, 'high prices retain their exact meaning');
assert.equal(record.offer.cached, true, 'persisted selection is an observation, never a live quote');
assert.equal(record.offer.tour, null, 'live quote authorization is not persisted');
assert.deepEqual(record.offer.variants, [], 'supplier flight response is not persisted');
assert.equal(record.offer.room, offer.room);
assert.equal(record.offer.meal, offer.meal);
assert.equal(record.offer.operator, offer.operator);
assert.equal(record.offer.flight, offer.flight);

api.demote();
assert.equal(api.read().cached, true, 'a new search demotes the in-memory quote');
assert.equal(api.read().tour, null, 'a changed search cannot reuse the previous quote');
assert.equal(JSON.parse(storage.get('anytour.real.selected-tour.v1')).observedAt, now, 'demotion does not extend the 24h window');

now += api.ttl - 1;
api.restore();
assert.ok(api.read(), 'selection remains visible until the full 24 hours elapse');
now += 1;
assert.equal(api.read(), null, 'selection expires exactly at 24 hours');
assert.equal(storage.has('anytour.real.selected-tour.v1'), false, 'expired observation is removed');

now = Date.parse('2026-09-22T12:00:00Z');
api.store(offer, hotel);
api.remove();
assert.equal(api.read(), null, 'explicit removal clears the selection');
now += 1000;
api.undo();
assert.ok(api.read(), 'undo restores a non-expired selection');
assert.equal(JSON.parse(storage.get('anytour.real.selected-tour.v1')).observedAt, now - 1000, 'undo does not renew observation time');

const target = api.snapshot(offer);
const candidate = {...target, cached: false, raw: {selectionEnabled: true}};
assert.equal(api.same(candidate, target), true, 'all canonical tour conditions match');
for (const [field, value] of [['hotelId', 78], ['day', '2026-10-06'], ['nights', 8], ['room', 'Standard'], ['meal', 'Завтрак'], ['operator', 'ANEX'], ['flight', 'regular']]) {
  assert.equal(api.same({...candidate, [field]: value}, target), false, `${field} cannot be silently substituted`);
}
assert.equal(api.same({...candidate, adults: 3}, target), false, 'party size cannot be silently substituted');
assert.equal(api.same({...candidate, ages: [7]}, target), false, 'child composition cannot be silently substituted');

const runSearch = source.slice(source.indexOf('function runSearch(options={})'), source.indexOf('\nfunction search(){'));
assert.match(runSearch, /demoteSavedTour\(\)/, 'new search retains a demoted observation');
assert.doesNotMatch(runSearch, /savedSelection=null/, 'new search no longer deletes the saved tour');
assert.match(source, /exactRefreshTarget:selectedTourOfferSnapshot\(o\)/, 'refresh carries the immutable canonical target');
assert.match(source, /sameSelectedTourConditions\(o,r\.exactRefreshTarget\)/, 'completion requires the same tour conditions');

console.log('search3 prototype selected-tour retention: ok');
