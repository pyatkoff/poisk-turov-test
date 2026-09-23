'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');

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

const priceCopyStart = source.indexOf('const needsRefresh=');
const priceCopyEnd = source.indexOf('const mealLabel=', priceCopyStart);
assert.ok(priceCopyStart >= 0 && priceCopyEnd > priceCopyStart, 'price provenance helpers are present');
const priceCopyContext = {};
vm.createContext(priceCopyContext);
vm.runInContext(source.slice(priceCopyStart, priceCopyEnd) + '\nthis.priceCopyTest={needsRefresh,offerActionLabel,priceNote,offerMetaNote};', priceCopyContext);
const priceCopy = priceCopyContext.priceCopyTest;
const cachedLocal = {cached:true,provider:'local',raw:{selectionEnabled:false}};
const directAnex = {cached:false,provider:'anex',raw:{selectionEnabled:false}};
const directAndromeda = {cached:false,provider:'andromeda',raw:{selectionEnabled:false}};
const freshTourvisor = {cached:false,provider:'tourvisor',raw:{selectionEnabled:true}};
assert.equal(priceCopy.priceNote(cachedLocal),'Сохранённая цена · требует проверки','cached LOCAL price keeps saved provenance');
assert.equal(priceCopy.priceNote(directAnex),'Цена из текущего поиска · требует подтверждения','fresh direct ANEX is not mislabeled as saved');
assert.equal(priceCopy.priceNote(directAndromeda),'Цена из текущего поиска · требует подтверждения','fresh direct Andromeda is not mislabeled as saved');
assert.equal(priceCopy.priceNote(freshTourvisor),'Цена предложения · сборы уточняются','fresh selectable Tourvisor keeps current offer copy');
assert.equal(priceCopy.offerMetaNote(directAnex),'Цена из текущего поиска · требует подтверждения','compact direct-provider row uses truthful provenance');
assert.equal(priceCopy.offerMetaNote(freshTourvisor),'Рейсы и багаж — при выборе','fresh Tourvisor compact row keeps selection detail hint');
assert.equal(priceCopy.offerActionLabel(directAndromeda),'Смотреть условия','direct-provider selection authority remains unchanged');
assert.equal(priceCopy.offerActionLabel(freshTourvisor),'Выбрать тур','Tourvisor selection CTA remains unchanged');

const prepareSearch = source.slice(source.indexOf('function prepareSearchRun(options={})'), source.indexOf('\nfunction mergeSearchResults'));
assert.match(prepareSearch, /demoteSavedTour\(\)/, 'new search retains a demoted observation before lifecycle orchestration');
assert.doesNotMatch(prepareSearch, /savedSelection=null/, 'new search no longer deletes the saved tour');
assert.match(source, /function runSearch\(options=\{\}\)\{return searchLifecycle\.run\(options\);\}/, 'legacy internal runSearch entrypoint delegates to the canonical lifecycle owner');
assert.match(source, /exactRefreshTarget:selectedTourOfferSnapshot\(o\)/, 'refresh carries the immutable canonical target');
assert.match(source, /sameSelectedTourConditions\(o,r\.exactRefreshTarget\)/, 'completion requires the same tour conditions');

execFileSync(process.execPath,['tests/search3-prototype-search-lifecycle-v1.cjs'],{stdio:'inherit'});
console.log('search3 prototype selected-tour retention: ok');
