/* Execute the compiled booking/services owners against an event-driven DOM adapter. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const crypto = require('node:crypto');
const path = require('node:path');
const iife = require('./search3-bundle-iife.cjs');
const bundle = fs.readFileSync(process.argv[2] || path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');

function fixture() {
  const events = new Map(), timers = [];
  let tourRoot = true, hasForm = true, review = false, lead = false;
  let summaryHTML = '', services = null, summaries = 0, serviceRenders = 0;
  const title = { textContent: '' }, flight = { textContent: '' };
  const summary = { remove() {}, querySelector(s) { return s.endsWith('__title') ? title : flight; } };
  const shell = {
    parentNode: { insertBefore(node) { services = node; serviceRenders++; } },
    querySelector() { return summary; },
    insertAdjacentHTML(_, value) { summaryHTML = value; summaries++; }
  };
  const form = { closest() { return shell; }, parentNode: shell.parentNode };
  const root = {
    dataset: {},
    classList: { contains(s) { return s === 'search3-final-review' ? review : lead; } },
    querySelector(s) {
      if (s === '.lead-form') return hasForm ? form : null;
      if (s === '.search3-final-sections') return services;
      if (s === '.search3-lead-shell,.lead-form') return hasForm ? shell : null;
      throw Error('unexpected root selector: ' + s);
    },
    appendChild(node) { services = node; serviceRenders++; }
  };
  const window = {
    addEventListener(name, fn) { if (!events.has(name)) events.set(name, []); events.get(name).push(fn); },
    matchMedia() { return { matches: true }; }
  };
  const document = {
    addEventListener() {},
    getElementById(id) { assert.equal(id, 'selectedTour'); return tourRoot ? root : null; },
    createElement(tag) { assert.equal(tag, 'div'); return { className: '', innerHTML: '', remove() { services = null; } }; }
  };
  vm.runInNewContext(iife(bundle, { global: 'Search3BookingSummary' }), { window, document, setTimeout(fn) { timers.push(fn); } });
  return {
    window,
    emit(name, detail) { for (const fn of events.get(name) || []) fn({ detail }); },
    flush() { let count = 0; while (timers.length) { assert.ok(++count < 100, 'queue settles'); timers.shift()(); } },
    get pending() { return timers.length; },
    get counts() { return [summaries, serviceRenders]; },
    mode(options) { ({ tourRoot = true, hasForm = true, review = false, lead = false } = options); },
    snapshot() { return { summaryHTML, servicesHTML: services && services.innerHTML, title: title.textContent, flight: flight.textContent, layout: root.dataset.search3FinalLayout || null }; }
  };
}

const tour = {
  name: 'Fallback name', date: '2026-09-10', nights: 7, price: { value: 70000 }, adults: 2, childs: 1,
  picture: 'https://example.test/a?x="<&', hotel: { name: 'A < B & "Hotel"', country: { russianName: 'Турция' }, region: ['Сиде', { title: 'Центр' }] },
  roomType: { name: 'STD & ROOM' }, placement: { title: 'DBL + CHD' }, meal: { russianName: 'Всё включено' },
  operator: { name: 'Operator <One>' }, fuelCharge: { value: 350 }
};
const selectedFlight = { forward: [{ company: 'SU', number: 'SU123', baggage: 20, carryOn: '5 кг' }], backward: [], fuelCharge: { value: 150 } };
const placeholders = { forward: [{ company: { name: 'SU' }, number: 'SU000', departure: { time: '00:00' }, arrival: { time: '00:00' }, baggage: 0 }] };
const f = fixture(), snapshots = [], pending = [];
const record = label => { f.flush(); snapshots.push([label, f.snapshot()]); };
f.emit('v2:tour-selected', { tour });
f.emit('v2:flight-selected', { flight: selectedFlight });
f.emit('v2:tour-price-updated', { price: 72150 });
pending.push(f.pending);
record('tour-flight-price burst');
assert.match(f.snapshot().summaryHTML, /72 150 ₽/);
assert.match(f.snapshot().servicesHTML, /Топливный сбор тура/);
assert.match(f.snapshot().servicesHTML, /Багаж/);
const renders = f.counts;
f.emit('v2:tour-price-updated', { pricePending: true, basePrice: 69500 });
record('pending price');
assert.equal(f.counts[1], renders[1], 'price-only changes do not rebuild unchanged services');
f.emit('v2:flight-selected', { flight: placeholders });
record('placeholder flight');
assert.match(f.snapshot().servicesHTML, /багаж уточняется/);
assert.ok(!f.snapshot().summaryHTML.includes('SU000'));
f.mode({ review: true }); f.emit('v2:booking-review'); record('review');
f.mode({ review: true, lead: true }); f.emit('search3:lead-entry'); record('lead');
f.emit('v2:tour-selected', { tour: { name: 'Second', price: 50000, adults: 1, childs: 0 } });
record('new tour clears flight');
assert.ok(!f.snapshot().servicesHTML.includes('Багаж'));
assert.match(f.snapshot().summaryHTML, /Рейс уточнит менеджер/);
assert.ok(!f.snapshot().servicesHTML.includes('Operator'));
f.emit('v2:lead-success'); record('lead success');
f.emit('v2:tour-selected', {}); record('missing tour retains DOM');
f.mode({ hasForm: false });
f.emit('v2:tour-selected', { tour });
f.emit('v2:flight-selected', { flight: selectedFlight });
record('services without lead form');
f.mode({ tourRoot: false }); f.emit('v2:flight-selected', {}); record('missing selected root');
// The compatibility party formatter is resolved when rendering, even if installed later.
f.mode({});
f.window.Search3CandidateResultsV1 = { partyLabel: (adults, children) => `${adults}/${children} туристов` };
f.emit('v2:tour-selected', { tour }); record('late party formatter');
assert.match(f.snapshot().servicesHTML, /2\/1 туристов/);

const digest = crypto.createHash('sha256').update(JSON.stringify(snapshots)).digest('hex');
console.log(JSON.stringify({ digest, snapshots: snapshots.length, pending, renders: f.counts }));
if (process.env.SEARCH3_CAPTURE_BASELINE !== '1') {
  assert.equal(digest, 'cc860066f8dbf857a76b926ac4bf5cae2b54e84981b86e319ebd4c9646a10234', 'all eleven settled markup/copy/layout snapshots match the previous published owner');
  assert.deepEqual(pending, [1], 'one queued update replaces the three summary/services timers');
  assert.equal(renders[1], 1, 'services render once for a synchronous tour/flight burst');
}
console.log('PASS: booking and services preserve selected data, escaped markup and lifecycle states');
