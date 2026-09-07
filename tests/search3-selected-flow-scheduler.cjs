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
let fallbackClicks = 0;
const fallbackButton = { textContent: 'Далее: итог тура', click() { fallbackClicks += 1; } };
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
    assert.ok(!selector.includes('search3-tour-detail-rail'), 'retired rail is not queried during selected-flow updates');
    if (selector === '.selected-price > small') return { textContent: 'Стоимость тура' };
    if (selector === '.tour-flights') {
      flightRootReads += 1;
      return flights;
    }
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '.search3-booking-summary__total') return [priceBox];
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
  fs.readFileSync(path.join(__dirname, '../v2/search3-selected-flow-v2.js'), 'utf8'),
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

flightRootReads = 0;
window.Search3SelectedFlowV2.toggleFlightDisclosure({
  closest() { return disclosureFlights; },
  focus() {}
});
assert.equal(flightRootReads, 0, 'disclosure toggle reuses its closest flight root');

flightDataPresent = false;
flightRootReads = 0;
assert.equal(window.Search3SelectedFlowV2.activateReview(), true, 'no-flight review activation succeeds');
assert.equal(flightRootReads, 1, 'review activation reuses one flight root for state and action');
assert.equal(fallbackClicks, 1, 'no-flight path activates the primary continue button without a desktop rail');

flightRootReads = 0;
window.Search3SelectedFlowV2.sync();
window.Search3SelectedFlowV2.sync();
assert.equal(flightRootReads, 2, 'each no-flight sync reuses one flight root for all fallback work');
assert.equal(fallbackDataWrites, 1, 'stable fallback dataset marker is written only once');

console.log('PASS: selected-flow coalesces updates and reuses stable disclosure/fallback DOM state');

// The primary continue owner changes booking phase without classifying or
// rearranging supplier flight segments. Exercise it separately from fallback.
{
  const phase = new Set();
  const callbacks = new Map();
  const notifications = [];
  const tasks = [];
  let onClick;
  let scrolls = 0;
  const heading = { textContent: '' };
  const hint = { textContent: '' };
  const button = { textContent: '' };
  const action = { hidden: true, querySelector() { return button; } };
  const flightRoot = {
    querySelector() { return action; },
    scrollIntoView() { scrolls += 1; }
  };
  const root = {
    classList: {
      contains: name => phase.has(name),
      add: name => phase.add(name),
      remove: name => phase.delete(name)
    },
    querySelector(selector) {
      if (selector === '.tour-flights') return flightRoot;
      if (selector.endsWith('.section-heading strong')) return heading;
      if (selector.endsWith('.section-heading span')) return hint;
      if (selector === '.search3-final-sections,.search3-lead-shell,.lead-form') return flightRoot;
      throw new Error('Unexpected query: ' + selector);
    }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../src/search3/behavior/flight-continue.js'), 'utf8'), {
    document: { getElementById() { return root; }, addEventListener(_name, fn) { onClick = fn; } },
    window: { addEventListener(name, fn) { callbacks.set(name, fn); }, dispatchEvent(event) { notifications.push(event.type); } },
    setTimeout(fn) { tasks.push(fn); },
    CustomEvent: function (type) { this.type = type; }
  });
  callbacks.get('v2:flight-selected')();
  while (tasks.length) tasks.shift()();
  assert.equal(action.hidden, false);
  assert.equal(button.textContent, 'Далее: итог тура');
  const click = { target: { closest() { return button; } }, preventDefault() {} };
  onClick(click);
  assert.equal(phase.has('search3-final-review'), true);
  assert.deepEqual(notifications, ['v2:booking-review']);
  assert.equal(button.textContent, 'Изменить рейс');
  onClick(click);
  assert.equal(phase.has('search3-final-review'), false);
  assert.equal(heading.textContent, 'Выберите рейс');
  assert.equal(scrolls, 2);
  callbacks.get('v2:tour-selected')();
  while (tasks.length) tasks.shift()();
  assert.equal(phase.has('search3-final-review'), false);
  console.log('PASS: primary flight continue preserves review/back transitions without supplier segment mutation');
}
