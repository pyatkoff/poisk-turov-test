const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const events = new Map();
const frames = [];
let flightRootReads = 0;
let priceWrites = 0;
let priceAttributeWrites = 0;
let priceAriaLabel = '';
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
  getAttribute(name) {
    return name === 'aria-label' ? priceAriaLabel : null;
  },
  setAttribute(name, value) {
    if (name === 'aria-label') {
      priceAriaLabel = value;
      priceAttributeWrites += 1;
    }
  }
};
let flightDataPresent = true;
let fallbackDataWrites = 0;
let fallbackDataValue;
const selectedDataset = {};
Object.defineProperty(selectedDataset, 'search3FlightFallback', {
  configurable: true,
  get() { return fallbackDataValue; },
  set(value) { fallbackDataValue = value; fallbackDataWrites += 1; }
});
const fallbackButton = { textContent: 'Далее: итог тура' };
const fallbackAction = {
  classList: { add() {} },
  querySelector(selector) { return selector === 'button' ? fallbackButton : null; }
};
const flights = {
  querySelector(selector) {
    if (selector === '.flight-variant,.flight-error') return flightDataPresent ? {} : null;
    if (selector === '.selected-loading') return { textContent: 'Данные по рейсам пока не получены.' };
    if (selector === '.search3-flight-continue') return fallbackAction;
    if (selector === '.flight-variants' || selector === '.search3-flight-show-all') return null;
    return null;
  }
};
const selected = {
  dataset: selectedDataset,
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
priceAttributeWrites = 0;
priceAriaLabel = '';

events.get('v2:tour-price-updated')({ detail: { price: 100000 } });
events.get('v2:tour-price-updated')({ detail: { price: 120000 } });

assert.equal(frames.length, 1, 'rapid price updates share one selected-flow frame');
assert.equal(priceWrites, 0, 'price DOM write is deferred to the shared frame');

flush();

assert.equal(priceWrites, 1, 'latest price is written once');
assert.equal(priceAttributeWrites, 1, 'latest price aria-label is written once');
assert.match(strongText, /120[\s\u00a0]?000/, 'latest queued price wins');
window.Search3SelectedFlowV2.syncDisplayedPrice();
window.Search3SelectedFlowV2.syncDisplayedPrice();
assert.equal(priceWrites, 1, 'unchanged price text is not rewritten');
assert.equal(priceAttributeWrites, 1, 'unchanged price aria-label is not rewritten');
assert.equal(
  flightRootReads,
  1,
  'one sync reuses one flight root lookup for disclosure and no-flight state'
);

let disclosureLookups = 0;
let disclosureAttributeWrites = 0;
let disclosureHiddenWrites = 0;
const disclosureAttributes = new Map();
const variants = Array.from({ length: 7 }, () => ({
  hidden: false,
  classList: { contains() { return false; } },
  querySelector() { return null; }
}));
let disclosureDataWrites = 0;
let disclosureDataValue;
const variantsDataset = {};
Object.defineProperty(variantsDataset, 'search3FlightDisclosure', {
  configurable: true,
  get() { return disclosureDataValue; },
  set(value) { disclosureDataValue = value; disclosureDataWrites += 1; }
});
const variantsBox = {
  dataset: variantsDataset,
  id: 'flightVariants',
  querySelectorAll() { return variants; },
  removeAttribute() {}
};
let disclosureHidden = false;
const disclosure = {
  textContent: '',
  get hidden() { return disclosureHidden; },
  set hidden(value) { disclosureHidden = value; disclosureHiddenWrites += 1; },
  getAttribute(name) { return disclosureAttributes.has(name) ? disclosureAttributes.get(name) : null; },
  hasAttribute(name) { return disclosureAttributes.has(name); },
  setAttribute(name, value) {
    disclosureAttributes.set(name, value);
    disclosureAttributeWrites += 1;
  },
  removeAttribute(name) {
    disclosureAttributes.delete(name);
    disclosureAttributeWrites += 1;
  }
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
window.Search3SelectedFlowV2.syncFlightDisclosure(disclosureFlights);

assert.equal(disclosureLookups, 2, 'each disclosure sync performs one show-all lookup');
assert.equal(disclosureHiddenWrites, 0, 'stable disclosure visibility is not rewritten');
assert.equal(disclosureAttributeWrites, 2, 'stable disclosure aria attributes are written only once');
assert.equal(disclosureDataWrites, 1, 'stable disclosure dataset marker is written only once');

flightDataPresent = false;
flightRootReads = 0;
window.Search3SelectedFlowV2.sync();
window.Search3SelectedFlowV2.sync();
assert.equal(flightRootReads, 2, 'each no-flight sync reuses one flight root for all fallback work');
assert.equal(fallbackDataWrites, 1, 'stable fallback dataset marker is written only once');

console.log('PASS: selected-flow coalesces updates and reuses stable disclosure/fallback DOM state');
