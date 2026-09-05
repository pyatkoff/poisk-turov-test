const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

let textWrites = 0;
let hiddenWrites = 0;
let detailText = '';
let detailHidden = true;

const detail = {};
Object.defineProperty(detail, 'textContent', {
  get() { return detailText; },
  set(value) { detailText = value; textWrites += 1; }
});
Object.defineProperty(detail, 'hidden', {
  get() { return detailHidden; },
  set(value) { detailHidden = value; hiddenWrites += 1; }
});

const route = {
  querySelector(selector) {
    return selector === '.search3-entry-summary-detail' ? detail : null;
  },
  appendChild() {}
};
const dates = {};
const main = {
  querySelector(selector) {
    if (selector === '.search3-dates') return dates;
    return null;
  },
  insertBefore() {}
};
const advanced = {
  firstElementChild: null,
  insertBefore() {}
};
const region = {
  parentNode: main,
  nextElementSibling: dates,
  classList: { add() {} }
};
const form = {
  dataset: {},
  elements: {
    region: { closest() { return region; } }
  },
  querySelector(selector) {
    if (selector === '.search3-primary-grid') return main;
    if (selector === '.search3-quality__grid') return advanced;
    return null;
  },
  addEventListener() {}
};
const values = {
  resultsSearchDates: { textContent: '10–17 сентября' },
  resultsSearchNights: { textContent: '7 ночей' },
  resultsSearchGuests: { textContent: '2 взрослых' }
};
const document = {
  readyState: 'complete',
  body: { classList: { contains(name) { return name === 'search3-candidate'; } } },
  getElementById(id) {
    if (id === 'tourSearch') return form;
    if (id === 'currentPriceCalendar') return null;
    return values[id] || null;
  },
  querySelector(selector) {
    if (selector === '#resultsSearchSummary .results-search-summary__route') return route;
    return null;
  },
  addEventListener() {},
  createElement() { throw new Error('unexpected createElement'); }
};
const mobile = {
  matches: false,
  addEventListener() {}
};
const window = {
  matchMedia() { return mobile; },
  addEventListener() {},
  setTimeout() { return 1; },
  clearTimeout() {}
};

vm.runInNewContext(
  fs.readFileSync(path.join(__dirname, '../src/search3/behavior/entry-v1.js'), 'utf8'),
  { document, window, Object, String, Array }
);

const api = window.Search3CandidateEntryV1;
assert.ok(api, 'entry adapter initialized');

api.sync();
api.sync();

assert.equal(detailText, '10–17 сентября · 7 ночей · 2 взрослых');
assert.equal(detailHidden, false);
assert.equal(textWrites, 1, 'unchanged summary text is not rewritten');
assert.equal(hiddenWrites, 1, 'unchanged summary visibility is not rewritten');

console.log('PASS: entry summary avoids duplicate DOM writes');
