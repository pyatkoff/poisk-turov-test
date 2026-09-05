const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const events = new Map();
const frames = [];
let flightRootReads = 0;
let priceWrites = 0;
let strongText = '';

const label = { textContent: '' };
const strong = {};
Object.defineProperty(strong, 'textContent', {
  get() { return strongText; },
  set(value) { strongText = value; priceWrites += 1; }
});
const priceBox = {
  querySelector(selector) {
    if (selector === ':scope > span') return label;
    if (selector === ':scope > strong') return strong;
    return null;
  },
  setAttribute() {}
};
const flights = {
  querySelector(selector) {
    if (selector === '.flight-variant,.flight-error') return {};
    return null;
  }
};
const selected = {
  dataset: {},
  classList: {
    add() {},
    remove() {},
    contains() { return false; }
  },
  querySelector(selector) {
    if (selector === '.selected-price > small') return { textContent: 'Стоимость тура' };
    if (selector === '.tour-flights') {
      flightRootReads += 1;
      return flights;
    }
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '.search3-booking-summary__total,.search3-tour-detail-rail__price') return [priceBox];
    return [];
  },
  contains() { return true; }
};
const document = {
  body: { classList: { contains(name) { return name === 'search3-candidate'; } } },
  getElementById(id) { return id === 'selectedTour' ? selected : null; },
  querySelector() { return null; },
  addEventListener() {},
  createElement() { throw new Error('unexpected createElement'); }
};
const window = {
  addEventListener(name, handler) { events.set(name, handler); },
  requestAnimationFrame(handler) { frames.push(handler); }
};

vm.runInNewContext(
  fs.readFileSync(path.join(__dirname, '../src/search3/behavior/selected-flow-v2.js'), 'utf8'),
  {
    document,
    window,
    MutationObserver: function () { this.observe = function () {}; },
    Intl,
    Set,
    Array,
    Number,
    String,
    Object
  }
);

const flush = () => {
  while (frames.length) frames.shift()();
};

flush();
flightRootReads = 0;
priceWrites = 0;

events.get('v2:tour-price-updated')({ detail: { price: 100000 } });
events.get('v2:tour-price-updated')({ detail: { price: 120000 } });

assert.equal(frames.length, 1, 'rapid price updates share one selected-flow frame');
assert.equal(priceWrites, 0, 'price DOM write is deferred to the shared frame');

flush();

assert.equal(priceWrites, 1, 'latest price is written once');
assert.match(strongText, /120[\s\u00a0]?000/, 'latest queued price wins');
assert.equal(
  flightRootReads,
  1,
  'one sync reuses one flight root lookup for disclosure and no-flight state'
);

let disclosureLookups = 0;
const variants = Array.from({ length: 7 }, () => ({
  hidden: false,
  classList: { contains() { return false; } },
  querySelector() { return null; }
}));
const variantsBox = {
  dataset: {},
  id: 'flightVariants',
  querySelectorAll() { return variants; },
  removeAttribute() {}
};
const disclosure = {
  hidden: false,
  textContent: '',
  setAttribute() {},
  removeAttribute() {}
};
const disclosureFlights = {
  querySelector(selector) {
    if (selector === '.flight-variants') return variantsBox;
    if (selector === '.search3-flight-show-all') {
      disclosureLookups += 1;
      return disclosure;
    }
    return null;
  },
  insertBefore() {},
  appendChild() {}
};

window.Search3SelectedFlowV2.syncFlightDisclosure(disclosureFlights);

assert.equal(disclosureLookups, 1, 'expanded disclosure reuses a single show-all lookup');

console.log('PASS: selected-flow coalesces updates and avoids duplicate flight disclosure lookups');
