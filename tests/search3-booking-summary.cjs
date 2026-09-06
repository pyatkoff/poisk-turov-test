/* Event-burst regression: execute the actual summary module with a small DOM adapter. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const events = new Map(), timers = [];
let renders = 0, html = '', lead = false, review = false, desktop = false;
const layoutWrites = [];
const style = owner => ({
  removeProperty(name) { layoutWrites.push([owner, 'remove', name]); },
  setProperty(name, value, priority) { layoutWrites.push([owner, 'set', name, value, priority]); }
});
let titleWrites = 0, titleValue = '';
const title = { get textContent() { return titleValue; }, set textContent(value) { titleWrites++; titleValue = value; } }, flight = { textContent: '' };
const summary = { style: style('summary'), remove() {}, querySelector(s) { return s.includes('__title') ? title : s.includes('__flight') ? flight : null; } };
const shell = { style: style('shell'), querySelector() { return summary; }, insertAdjacentHTML(_, value) { renders++; html = value; } };
const form = { style: style('form'), closest() { return shell; } };
const root = { dataset: {}, classList: { contains(name) { return name === 'search3-lead-entry' ? lead : name === 'search3-final-review' && review; } }, querySelector() { return form; } };
const window = { addEventListener(name, fn) { events.set(name, fn); }, matchMedia() { return { matches: desktop }; }, Search3FlightPresentation: { flightLabel(v, fallback) { return v ? v.name : fallback; } } };
const bundle = fs.readFileSync(process.argv[2] || path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const bundledIife = require('./search3-bundle-iife.cjs');
const presentationSource = bundledIife(bundle, { global: 'Search3PresentationText' })
  + bundledIife(bundle, { global: 'Search3BookingSummary' });
vm.runInNewContext(presentationSource, {
  window, document: { getElementById() { return root; }, addEventListener() {} }, setTimeout(fn) { timers.push(fn); }
});
const emit = (name, detail) => events.get(name)({ detail });
const flush = () => { while (timers.length) timers.shift()(); };
emit('v2:tour-selected', { tour: { name: 'First', price: 100 } });
emit('v2:flight-selected', { flight: { name: 'Actual flight' } });
emit('v2:tour-price-updated', { price: 321 });
emit('resize');
assert.equal(timers.length, 1, 'one scheduled pass for a synchronous event burst');
flush();
assert.equal(renders, 1);
assert.ok(html.includes('Actual flight') && html.includes('321 ₽'), 'latest flight and price survive coalescing');
emit('resize');
emit('v2:tour-selected', { tour: { name: 'Second', price: 654 } });
lead = true;
emit('search3:lead-entry');
flush();
assert.equal(renders, 2, 'a render supersedes an already pending layout');
assert.ok(html.includes('Second') && html.includes('654 ₽'));
assert.ok(!html.includes('Actual flight'), 'new tour clears previous flight');
assert.equal(flight.textContent, 'Рейс уточнит менеджер');
lead = false;
emit('resize'); emit('v2:booking-review'); flush();
assert.equal(renders, 2, 'layout-only events do not rebuild the summary');
assert.equal(titleWrites, 1, 'unchanged heading does not generate new DOM mutations');
assert.equal(flight.textContent, 'Выберите рейс');
emit('v2:tour-price-updated', { price: 987 }); flush();
assert.equal(renders, 3, 'later updates are not lost');
assert.ok(html.includes('987 ₽'));
console.log('PASS: coalesced summary events preserve latest tour/flight/price and stage');

// Preserve each target, write order, value and priority across all layout branches.
const clear = [
  ...['display', 'grid-column', 'grid-template-columns', 'gap', 'align-items'].map(p => ['shell', 'remove', p]),
  ...['grid-column', 'grid-row'].map(p => ['form', 'remove', p]),
  ...['display', 'grid-column', 'grid-row'].map(p => ['summary', 'remove', p])
];
const entryLayout = [
  ['shell', 'display', 'grid'], ['shell', 'grid-column', '1 / -1'],
  ['shell', 'grid-template-columns', 'minmax(0,1fr) 320px'], ['shell', 'gap', '18px'],
  ['shell', 'align-items', 'start'], ['form', 'grid-column', '1'], ['form', 'grid-row', '1'],
  ['summary', 'display', 'block'], ['summary', 'grid-column', '2'], ['summary', 'grid-row', '1']
];
const reviewLayout = [
  ['shell', 'display', 'contents'], ['form', 'grid-column', '1 / 3'],
  ['summary', 'display', 'block'], ['summary', 'grid-column', '3'], ['summary', 'grid-row', '4 / 12']
];
for (desktop of [false, true]) for (review of [false, true]) for (lead of [false, true]) {
  layoutWrites.length = 0;
  window.Search3BookingSummary.syncLayout();
  const setters = desktop && review ? (lead ? entryLayout : reviewLayout) : [];
  assert.deepEqual(layoutWrites, clear.concat(setters.map(([owner, name, value]) => [owner, 'set', name, value, 'important'])));
  assert.equal(root.dataset.search3FinalLayout, review && !lead ? 'maket7' : undefined);
}
console.log('PASS: all eight summary layout states preserve ordered DOM style operations');
