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
const priceInput = { min: '', max: '', step: '', value: '' };
const seaSection = { hidden: false, ariaHidden: '', setAttribute(name, value) { if (name === 'aria-hidden') this.ariaHidden = value; } };
const seaInputs = ['0', '200', '500', '1000'].map(value => ({ value, checked: value === '0' }));
const charterField = { hidden: false, ariaHidden: '', setAttribute(name, value) { if (name === 'aria-hidden') this.ariaHidden = value; } };
const charterInput = { checked: false };
let railHtml = '';
let railHtmlWrites = 0;
let formSubmits = 0;
const rail = {
  dataset: {},
  get innerHTML() { return railHtml; },
  set innerHTML(value) { railHtml = value; railHtmlWrites += 1; },
  addEventListener(name, handler) { railEvents.set(name, handler); },
  querySelector(selector) {
    return {'[data-s3-count]': count, '[data-s3-word]': word,
      '[data-s3-price-label]': priceLabel, '[data-s3-price]': priceInput,
      '[data-s3-sea-section]': seaSection, '[data-s3-charter-field]': charterField,
      '[data-s3-charter-check]': charterInput}[selector] || null;
  },
  querySelectorAll(selector) { return selector === 'input[name="s3-sea"]' ? seaInputs : []; }
};
const form = { elements: {}, requestSubmit() { formSubmits += 1; } };
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
  { seaDistance: 400, tours: [{ price: 90000, isCharter: false }, { price: 120000, isCharter: false }] },
  { seaDistance: 800, tours: [{ price: 160000, isCharter: false }] }
];
assert.equal(announcements.length, 1, 'initial empty rail announces once');
assert.equal(railHtmlWrites, 1, 'initial rail is rendered once');
assert.equal(seaSection.hidden, true, 'the sea facet starts hidden without complete result data');
assert.equal(charterField.hidden, true, 'the charter facet starts hidden without complete result data');
windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
assert.equal(seaSection.hidden, false, 'complete sea-distance data reveals the facet');
assert.equal(charterField.hidden, false, 'complete charter data reveals the facet');
assert.equal(announcements.length, 2, 'new source render announces once, not twice');
assert.equal(railHtmlWrites, 1,
  'a progressive source update preserves the mounted controls instead of replacing their DOM');
assert.equal(priceInput.min, '90000');
assert.equal(priceInput.max, '160000');
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

input(95000);
assert.equal(frames.length, 1);
railEvents.get('change')({
  target: {
    name: 's3-sea', value: '500',
    matches() { return false; }
  }
});
assert.equal(renders.length, 3, 'a discrete filter immediately applies the latest price state');
while (frames.length) frames.shift()();
assert.equal(renders.length, 3, 'the superseded price frame does not render again');

input(95000);
assert.equal(frames.length, 1);
windowEvents.get('v2:search-reset')();
while (frames.length) frames.shift()();
assert.equal(renders.length, 3, 'search reset cancels a pending price render');
assert.equal(announcements.at(-1).resultCount, 0);

windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
input(95000);
while (frames.length) frames.shift()();
assert.equal(renders.length, 4);
const refreshedHotels = hotels.concat({ seaDistance: 1200, tours: [{ price: 200000, isCharter: false }] });
const announcementCount = announcements.length;
const railHtmlWritesBeforeRefresh = railHtmlWrites;
windowEvents.get('v2:results-rendered')({ detail: { items: refreshedHotels } });
assert.equal(renders.length, 5, 'an active price filter is reapplied to a fresh source');
assert.equal(renders.at(-1).length, 1, 'fresh unfiltered hotels do not leak into filtered results');
assert.equal(railHtmlWrites, railHtmlWritesBeforeRefresh,
  'an active slider keeps the same controls while progressive results refresh');
assert.equal(priceInput.max, '200000');
assert.equal(priceInput.value, '95000', 'the selected price survives the refreshed bounds');
assert.equal(announcements.length, announcementCount + 1,
  'fresh source reapplication announces only the final filtered count');
assert.equal(announcements.at(-1).resultCount, 1);

windowEvents.get('v2:search-reset')();
windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
input(95000);
assert.equal(frames.length, 1);
const rendersBeforeRefresh = renders.length;
windowEvents.get('v2:results-rendered')({ detail: { items: refreshedHotels } });
assert.equal(renders.length, rendersBeforeRefresh + 1,
  'fresh source immediately consumes the pending latest price state');
while (frames.length) frames.shift()();
assert.equal(renders.length, rendersBeforeRefresh + 1,
  'fresh source cancels the superseded pending price frame');

const announcementsBeforeSort = announcements.length;
windowEvents.get('v2:results-rendered')({ detail: { items: renders.at(-1) } });
assert.equal(announcements.length, announcementsBeforeSort,
  'rerendering the same filtered references does not announce a filter change');
assert.equal(count.textContent, '1', 'same-reference rerender keeps the established count');

windowEvents.get('v2:search-reset')();
windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
input(150000);
while (frames.length) frames.shift()();
const temporarilyNarrowedHotels = [{ seaDistance: 400, tours: [{ price: 90000 }] }];
windowEvents.get('v2:results-rendered')({ detail: { items: temporarilyNarrowedHotels } });
assert.equal(priceInput.max, '95000');
assert.equal(priceInput.value, '95000', 'the mounted slider stays inside temporary bounds');
windowEvents.get('v2:results-rendered')({ detail: { items: refreshedHotels } });
assert.equal(priceInput.value, '150000',
  'a temporary source contraction does not destroy the user-selected price limit');

Object.assign(form.elements, {
  price_from: { value: '80000' }, price_till: { value: '180000' },
  onlyDirect: { checked: true }, onlyCharter: { checked: true }
});
const announcementsBeforeReset = announcements.length;
const rendersBeforeReset = renders.length;
railEvents.get('click')({
  target: { closest(selector) { return selector === '[data-s3-reset]' ? {} : null; } }
});
assert.equal(form.elements.price_from.value, '', 'reset clears the lower budget bound');
assert.equal(form.elements.price_till.value, '', 'reset clears the upper budget bound');
assert.equal(form.elements.onlyDirect.checked, false, 'reset clears the direct-flight form filter');
assert.equal(form.elements.onlyCharter.checked, false, 'reset clears the charter form filter');
assert.equal(formSubmits, 1, 'desktop reset submits the cleared form once');
assert.equal(renders.length, rendersBeforeReset + 1, 'reset restores the unfiltered source once');
assert.equal(renders.at(-1).length, refreshedHotels.length, 'reset restores every source hotel');
assert.equal(announcements.length, announcementsBeforeReset + 1,
  'reset announces the restored result count once');

form.elements.onlyCharter.checked = true;
windowEvents.get('v2:search-reset')();
assert.equal(charterField.hidden, true, 'search reset hides the charter facet until complete data arrives');
assert.equal(rail.dataset.s3ActiveCount, '0', 'a hidden charter facet is not counted as active');
windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
assert.equal(charterField.hidden, false, 'complete results restore the charter facet');
assert.equal(charterInput.checked, true, 'complete results restore charter state from the form');
assert.equal(rail.dataset.s3ActiveCount, '1');
const announcementsBeforeEmptyCharterToggle = announcements.length;
railEvents.get('change')({
  target: {
    checked: false, name: '',
    matches(selector) { return selector === '[data-s3-charter-check]'; }
  }
});
assert.equal(form.elements.onlyCharter.checked, false,
  'changing the local charter filter keeps the form state in sync');
assert.equal(rail.dataset.s3ActiveCount, '0',
  'clearing a filter updates the active count with complete result data');
assert.equal(announcements.length, announcementsBeforeEmptyCharterToggle + 1,
  'clearing the charter filter announces its restored result count once');
assert.equal(announcements.at(-1).resultCount, hotels.length);

windowEvents.get('v2:search-reset')();
input(100000);
while (frames.length) frames.shift()();
assert.equal(rail.dataset.s3ActiveCount, '1',
  'a scheduled price change updates the active count with an empty source');
assert.equal(announcements.at(-1).resultCount, 0);

windowEvents.get('v2:search-reset')();
windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
railEvents.get('change')({
  target: {
    checked: true, name: '',
    matches(selector) { return selector === '[data-s3-charter-check]'; }
  }
});
assert.equal(renders.at(-1).length, 0, 'the charter filter can legitimately produce no matches');
const announcementsBeforeEmptyRerender = announcements.length;
windowEvents.get('v2:results-rendered')({ detail: { items: renders.at(-1) } });
assert.equal(announcements.length, announcementsBeforeEmptyRerender,
  'rerendering the same empty filtered result is not a new source');
railEvents.get('change')({
  target: {
    checked: false, name: '',
    matches(selector) { return selector === '[data-s3-charter-check]'; }
  }
});
assert.equal(renders.at(-1).length, hotels.length,
  'clearing a zero-match filter restores the original result source');

windowEvents.get('v2:search-reset')();
windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
railEvents.get('change')({
  target: {
    name: 's3-sea', value: '500',
    matches() { return false; }
  }
});
assert.equal(renders.at(-1).length, 1, 'the available sea facet filters complete data');
const incompleteSeaHotels = [
  { seaDistance: 0, tours: [{ price: 90000, isCharter: false }] },
  { seaDistance: 800, tours: [{ price: 160000, isCharter: false }] }
];
windowEvents.get('v2:results-rendered')({ detail: { items: incompleteSeaHotels } });
assert.equal(seaSection.hidden, true, 'a partial progressive source hides the incomplete sea facet');
assert.equal(seaInputs[0].checked, true, 'hiding the incomplete facet resets it to any distance');
assert.equal(rail.dataset.s3ActiveCount, '0', 'the hidden incomplete facet is not counted as active');

windowEvents.get('v2:search-reset')();
windowEvents.get('v2:results-rendered')({ detail: { items: hotels } });
railEvents.get('change')({
  target: {
    checked: true, name: '',
    matches(selector) { return selector === '[data-s3-charter-check]'; }
  }
});
const incompleteCharterHotels = [
  { seaDistance: 400, tours: [{ price: 90000, isCharter: true }, { price: 120000 }] }
];
windowEvents.get('v2:results-rendered')({ detail: { items: incompleteCharterHotels } });
assert.equal(charterField.hidden, true, 'a partial progressive source hides the incomplete charter facet');
assert.equal(charterInput.checked, false, 'hiding the incomplete charter facet clears its local control');
assert.equal(rail.dataset.s3ActiveCount, '0', 'the hidden incomplete charter facet is not counted as active');
assert.equal(form.elements.onlyCharter.checked, true,
  'hiding a local facet does not silently rewrite the primary search constraint');

windowEvents.get('v2:search-reset')();
const hotelWithoutTours = { seaDistance: 300, price: 135000 };
windowEvents.get('v2:results-rendered')({ detail: { items: [hotelWithoutTours] } });
input(100000);
while (frames.length) frames.shift()();
assert.equal(renders.at(-1).length, 0, 'price filtering still excludes a hotel without tour rows');
input(140000);
while (frames.length) frames.shift()();
assert.equal(renders.at(-1).length, 1, 'price filtering still restores a matching hotel without tour rows');
console.log('PASS: price input bursts render once per frame with latest state');
