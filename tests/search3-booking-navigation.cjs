/* Native selected-tour CTA scrolls directly to the canonical lead form. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const iife = require('./search3-bundle-iife.cjs');
const bundle = fs.readFileSync(process.argv[2] || path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const events = new Map(), clicks = [], timers = [], trace = [];
const classes = new Set();
const phone = { focus(options) { trace.push(['focus', options]); } };
const button = { textContent: '', disabled: false };
const action = { hidden: true, querySelector(selector) { assert.equal(selector, 'button'); return button; } };
const flights = { querySelector(selector) { assert.equal(selector, '.search3-flight-continue'); return action; } };
const form = {
  scrollIntoView(options) { trace.push(['scroll', options]); },
  querySelector(selector) { assert.equal(selector, 'input[name="phone"]'); return phone; }
};
const selected = {
  classList: { add: (...names) => names.forEach(name => classes.add(name)), remove: (...names) => names.forEach(name => classes.delete(name)) },
  querySelector(selector) { return selector === '.tour-flights' ? flights : selector === '.lead-form' ? form : null; }
};
const window = {
  addEventListener(name, fn) { if (!events.has(name)) events.set(name, []); events.get(name).push(fn); },
  dispatchEvent(event) { trace.push(['event', event.type, event.detail]); }
};
vm.runInNewContext(iife(bundle, { global: 'Search3SummaryCta' }), {
  window,
  document: {
    getElementById(id) { assert.equal(id, 'selectedTour'); return selected; },
    addEventListener(name, fn) { assert.equal(name, 'click'); clicks.push(fn); }
  },
  setTimeout(fn) { timers.push(fn); },
  CustomEvent: function(type, options) { this.type = type; this.detail = options.detail; }
});
(events.get('v2:tour-selected') || []).forEach(fn => fn({ detail: {} }));
while (timers.length) timers.shift()();
assert.equal(action.hidden, false);
assert.equal(button.textContent, 'Оставить заявку');
const click = { target: { closest: selector => selector === '#selectedTour .search3-flight-continue button' ? button : null }, preventDefault() { trace.push(['prevent']); } };
clicks.forEach(fn => fn(click));
assert.ok(classes.has('search3-lead-entry'));
assert.ok(!classes.has('search3-final-review'));
assert.deepEqual(trace.map(row => row[0]), ['prevent', 'event', 'scroll', 'focus']);
assert.equal(window.Search3SummaryCta.version, 10);
console.log('PASS: native CTA enters the canonical lead form with one click');
