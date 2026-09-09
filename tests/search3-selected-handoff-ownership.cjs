const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const bundledIife = require('./search3-bundle-iife.cjs');

const root = path.join(__dirname, '..');
const bundle = fs.readFileSync(process.argv[2] || path.join(root, 'v2/search3-results-filters-v1.js'), 'utf8');
const owner = bundledIife(bundle, { global: 'Search3CandidateSelectedHandoffV1' });
const canonicalReturn = fs.readFileSync(path.join(root, 'v2/tour-controller-v4.js'), 'utf8');
const events = new Map();
const frames = [];
const attributes = new Map();
let selectedFocuses = 0;
let loading = false;

const heading = {
  id: '',
  isConnected: true,
  setAttribute() {}
};
const loadingChild = {
  classList: { contains(name) { return loading && name === 'selected-loading'; } },
  querySelector() { return null; }
};
const selected = {
  hidden: false,
  children: [loadingChild],
  firstElementChild: loadingChild,
  querySelector(selector) {
    if (selector === '.selected-head h2') return heading;
    return null;
  },
  getAttribute(name) { return attributes.has(name) ? attributes.get(name) : null; },
  setAttribute(name, value) { attributes.set(name, value); },
  focus() { selectedFocuses += 1; }
};
const button = {
  disabled: false,
  dataset: { search3ProductionLabel: 'Выбрать тур' },
  textContent: 'Загрузка'
};
const results = {
  querySelector() { return null; },
  querySelectorAll(selector) { return selector === 'button[data-search3-production-label]' ? [button] : []; },
  contains() { return false; }
};
const tools = { querySelector() { return null; } };
const document = {
  activeElement: null,
  body: { classList: {
    contains(name) { return name === 'search3-candidate'; },
    toggle() {},
    remove() {}
  } },
  getElementById(id) { return id === 'selectedTour' ? selected : id === 'results' ? results : id === 'resultsTools' ? tools : null; },
  querySelector() { return null; },
  addEventListener() {}
};
const window = {
  addEventListener(name, handler) { events.set(name, handler); },
  requestAnimationFrame(handler) { frames.push(handler); }
};

vm.runInNewContext(owner, {
  document,
  window,
  MutationObserver: function () { this.observe = function () {}; },
  requestAnimationFrame(handler) { frames.push(handler); },
  Object,
  String
});

assert.equal(button.textContent, 'Выбрать тур', 'enabled production label is restored');
assert.equal(attributes.get('aria-busy'), 'false', 'initial ready state is exposed');
events.get('v2:tour-selected')();
assert.equal(frames.length, 1, 'complete selected DOM queues one focus frame');
frames.shift()();
assert.equal(selectedFocuses, 1, 'selected context receives focus once');
assert.equal(heading.id, 'search3-selected-tour-heading');
assert.equal(attributes.get('aria-labelledby'), heading.id);

loading = true;
window.Search3CandidateSelectedHandoffV1.syncBusy();
assert.equal(attributes.get('aria-busy'), 'true', 'loading state remains observable');
loading = false;
window.Search3CandidateSelectedHandoffV1.syncBusy();
assert.equal(attributes.get('aria-busy'), 'false', 'ready state is restored');

assert.ok(!events.has('v2:tour-returned'), 'Search3 no longer duplicates canonical return focus');
assert.ok(!owner.includes('data.search3ReturnFocus'));
assert.ok(!owner.includes('getClientRects'));
assert.ok(!Object.hasOwn(window.Search3CandidateSelectedHandoffV1, 'focusReturnedContext'));
assert.match(canonicalReturn, /requestAnimationFrame\(\(\)=>\{if\(target\)focusAndReveal\(target\)/,
  'current controller keeps the exact initiating tour focus');
assert.match(canonicalReturn, /closest\('\.back-results,\.lead-success-back'\)/,
  'current controller owns every selected-tour return action');

console.log('PASS: selected handoff owns one-frame entry focus; current controller owns return focus');
