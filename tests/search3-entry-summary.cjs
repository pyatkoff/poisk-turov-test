const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

let textWrites = 0;
let hiddenWrites = 0;
let detailText = '';
let detailHidden = true;
let calendarWrapperChecks = 0;
let currentCalendar = null;
let layoutWrites = 0;
let layoutValue = '';
const timeoutDelays = [];
const windowEvents = [];
const handlers = new Map(), formClasses = new Set();
const formEvents = [];
const mobileEvents = [];

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
const dataset = {};
Object.defineProperty(dataset, 'search3EntryLayout', {
  get() { return layoutValue; },
  set(value) { layoutValue = value; layoutWrites += 1; }
});
const form = {
  classList: { add(name) { formClasses.add(name); }, remove(name) { formClasses.delete(name); } },
  scrollIntoView() {},
  dataset,
  elements: {
    region: { closest() { return region; } }
  },
  querySelector(selector) {
    if (selector === '.search3-primary-grid') return main;
    if (selector === '.search3-quality__grid') return advanced;
    return null;
  },
  addEventListener(type) { formEvents.push(type); }
};
const values = {
  resultsSearchDates: { textContent: '10–17 сентября' },
  resultsSearchNights: { textContent: '7 ночей' },
  resultsSearchGuests: { textContent: '2 взрослых' }
};
const wrappedCalendar = () => ({
  hidden: false,
  querySelector(selector) {
    if (selector === '.search3-price-calendar') {
      calendarWrapperChecks += 1;
      return {};
    }
    return null;
  }
});
currentCalendar = wrappedCalendar();

const document = {
  readyState: 'complete',
  body: { classList: { contains(name) { return name === 'search3-candidate'; } } },
  getElementById(id) {
    if (id === 'tourSearch') return form;
    if (id === 'currentPriceCalendar') return currentCalendar;
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
  addEventListener(type) { mobileEvents.push(type); }
};
const window = {
  matchMedia() { return mobile; },
  addEventListener(type, handler) { windowEvents.push(type); handlers.set(type, handler); },
  setTimeout(callback, delay) { timeoutDelays.push(delay); return timeoutDelays.length; },
  clearTimeout() {}
};

const source = fs.readFileSync(
  path.join(__dirname, '../src/search3/behavior/search-form/entry-presentation.js'),
  'utf8'
);
vm.runInNewContext(
  `(function () {
    function field(form, name) {
      var el = form && form.elements && form.elements[name];
      return el && el.closest ? el.closest('.field') : null;
    }
    ${source}
    installEntryPresentation(document.getElementById('tourSearch'), main, advanced, region);
  })();`,
  { document, window, Object, String, Array, main, advanced, region }
);

const api = window.Search3CandidateEntryV1;
assert.ok(api, 'entry adapter initialized');
assert.deepEqual(timeoutDelays, [0, 40, 160, 320], 'initial settle schedule is preserved');
assert.deepEqual(windowEvents, ['v2:search-started', 'v2:search-dirty', 'v2:search-error', 'v2:search-reset', 'v2:results-rendered', 'v2:search-complete']);
assert.deepEqual(formEvents, ['change']);
assert.deepEqual(mobileEvents, ['change', 'change']);
assert.match(source, /mobile\.addListener\(settle\)/, 'legacy matchMedia listener fallback is preserved');

api.sync();
api.sync();

assert.equal(detailText, '10–17 сентября · 7 ночей · 2 взрослых');
assert.equal(detailHidden, false);
assert.equal(textWrites, 1, 'unchanged summary text is not rewritten');
assert.equal(hiddenWrites, 1, 'unchanged summary visibility is not rewritten');
assert.equal(calendarWrapperChecks, 1, 'adapted calendar wrapper is checked once for repeated syncs');
assert.equal(layoutValue, 'desktop');
assert.equal(layoutWrites, 1, 'unchanged desktop layout is written once');

currentCalendar = wrappedCalendar();
api.sync();

assert.equal(calendarWrapperChecks, 2, 'replacement calendar is detected and adapted independently');
assert.equal(textWrites, 1, 'calendar replacement does not rewrite unchanged summary text');
assert.equal(hiddenWrites, 1, 'calendar replacement does not rewrite unchanged summary visibility');
assert.equal(layoutWrites, 1, 'calendar replacement does not rewrite unchanged layout state');

mobile.matches = true;
api.sync();
api.sync();

assert.equal(layoutValue, 'mobile-compact');
assert.equal(layoutWrites, 2, 'real desktop-to-mobile transition writes layout once');

handlers.get('v2:search-started')();
assert.ok(formClasses.has('mobile-search-collapsed'));
handlers.get('v2:search-error')({detail:{phase:'network'}});
assert.ok(formClasses.has('mobile-search-collapsed'), 'network error keeps the existing status/retry presentation');
handlers.get('v2:search-error')({detail:{phase:'validation'}});
assert.ok(!formClasses.has('mobile-search-collapsed'), 'validation restores editable form');
handlers.get('v2:search-started')();
handlers.get('v2:search-dirty')();
assert.ok(!formClasses.has('mobile-search-collapsed'), 'dirty parameters restore editable form');
mobile.matches = false;
handlers.get('v2:search-started')();
assert.ok(!formClasses.has('mobile-search-collapsed'), 'desktop does not collapse');
console.log('PASS: entry summary/calendar and retained mobile collapse/recovery');
