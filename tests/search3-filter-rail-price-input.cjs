/* Price-drag regression: execute the actual filter rail with a small DOM adapter. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const windowEvents = new Map();
const railEvents = new Map();
const frames = [];
const announcements = [];
const count = { textContent: '' };
const word = { textContent: '' };
const priceLabel = { textContent: '' };
const rail = {
  dataset: {}, innerHTML: '',
  addEventListener(name, handler) { railEvents.set(name, handler); },
  querySelector(selector) {
    return {'[data-s3-count]': count, '[data-s3-word]': word,
      '[data-s3-price-label]': priceLabel}[selector] || null;
  }
};
const form = { elements: {} };
const renders = [];
const window = {
  innerWidth: 1440,
  V2Results: { render(items) { renders.push(items); } },
  addEventListener(name, handler) { windowEvents.set(name, handler); },
  dispatchEvent(event) { announcements.push(event.detail); },
  requestAnimationFrame(handler) { frames.push(handler); }
};
const document = {
  querySelector(selector) { return selector === '.results-filter-rail' ? rail : null; },
  getElementById(id) { return id === 'tourSearch' ? form : null; }
};
function CustomEvent(name, options) { this.type = name; this.detail = options.detail; }

vm.runInNewContext(
  fs.readFileSync(path.join(__dirname, '../src/search3/behavior/filter-rail.js'), 'utf8'),
  { window, document, CustomEvent, Intl, Number, Object, Array, Math, String }
);

const hotels = [
  { tours: [{ price: 90000 }, { price: 120000 }] },
  { tours: [{ price: 160000 }] }
];
assert.equal(announcements.length, 1, 'initial empty rail announces once');
windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
assert.equal(announcements.length, 2, 'new source render announces once, not twice');
const input = value => railEvents.get('input')({
  target: { value: String(value), matches(selector) { return selector === '[data-s3-price]'; } }
});

input(150000); input(110000); input(95000);
assert.equal(frames.length, 1, 'a price drag schedules one frame');
assert.equal(renders.length, 0, 'filtering waits for the scheduled frame');
while (frames.length) frames.shift()();
assert.equal(renders.length, 1, 'the event burst renders once');
assert.equal(renders[0].length, 1, 'the latest slider value is applied');
assert.equal(renders[0][0].tours.length, 1);
assert.equal(renders[0][0].tours[0].price, 90000);
assert.match(priceLabel.textContent, /95.000|95\s000/u,
  'visible label follows the latest input immediately');

input(170000);
assert.equal(frames.length, 1, 'a later interaction still schedules work');
while (frames.length) frames.shift()();
assert.equal(renders.length, 2);
assert.equal(renders[1].length, 2);
console.log('PASS: price input bursts render once per frame with latest state');
