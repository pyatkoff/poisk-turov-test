const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const events = new Map();
const frames = [];
const tasks = [];
const notifications = [];
const bodyClasses = new Set(['search3-candidate']);
const selectedClasses = new Set();
let flightRootReads = 0;
let flightDataPresent = true;
let flightErrorPresent = false;
let flightRetry = null;
let fallbackAction = null;
let fallbackButton = null;
let retryInsertions = 0;
let actionInsertions = 0;
let leadClicks = 0;
let leadFocuses = 0;
let nativeClick;
let fallbackDataWrites = 0;
let fallbackDataValue;
let priceVariants = [];
let observerCallback = null;
let mutationPending = false;

function mutateSelected() {
  if (observerCallback) mutationPending = true;
}

const selectedDataset = {};
Object.defineProperty(selectedDataset, 'search3FlightFallback', {
  configurable: true,
  get() { return fallbackDataValue; },
  set(value) { fallbackDataValue = value; fallbackDataWrites += 1; }
});

function removable(node, onRemove) {
  node.removed = false;
  node.remove = function () {
    node.removed = true;
    onRemove();
    mutateSelected();
  };
  return node;
}

function element(tag) {
  const attributes = new Map();
  const classes = new Set();
  const node = {
    tagName: String(tag || '').toUpperCase(),
    type: '',
    className: '',
    textContent: '',
    dataset: {},
    classList: {
      add(name) {
        if (!classes.has(name)) {
          classes.add(name);
          mutateSelected();
        }
      },
      remove(name) {
        if (classes.delete(name)) mutateSelected();
      },
      contains(name) { return classes.has(name); }
    },
    getAttribute(name) { return attributes.has(name) ? attributes.get(name) : null; },
    setAttribute(name, value) { attributes.set(name, value); }
  };
  if (tag === 'div') {
    let label = '';
    const button = fallbackButton = {
      get textContent() { return label; },
      set textContent(value) { label = value; mutateSelected(); },
      click() {
        leadClicks += 1;
        nativeClick({ target: { closest(selector) { return selector === '#selectedTour .search3-flight-continue button' ? button : null; } }, preventDefault() {} });
      }
    };
    Object.defineProperty(node, 'innerHTML', {
      set(value) {
        button.textContent = /<button[^>]*>([^<]*)<\/button>/.exec(value)?.[1] || '';
      }
    });
    node.querySelector = selector => selector === 'button' ? button : null;
  }
  return node;
}

let emptyMessageText = 'Данные по рейсам пока не получены.';
const emptyMessage = {
  get textContent() { return emptyMessageText; },
  set textContent(value) {
    if (emptyMessageText !== value) {
      emptyMessageText = value;
      mutateSelected();
    }
  },
  insertAdjacentElement(position, node) {
    assert.equal(position, 'afterend');
    flightRetry = removable(node, () => { if (flightRetry === node) flightRetry = null; });
    retryInsertions += 1;
    mutateSelected();
  }
};

const flights = {
  querySelector(selector) {
    if (selector === '.flight-variant,.flight-error') return flightDataPresent || flightErrorPresent ? {} : null;
    if (selector === '.selected-loading') return emptyMessage;
    if (selector === '.load-flights') return flightRetry;
    if (selector === '.search3-flight-continue') return fallbackAction;
    return null;
  },
  appendChild(node) {
    fallbackAction = removable(node, () => { if (fallbackAction === node) fallbackAction = null; });
    actionInsertions += 1;
    mutateSelected();
  }
};

const selected = {
  hidden: false,
  children: [{}],
  dataset: selectedDataset,
  classList: {
    add(name) {
      if (!selectedClasses.has(name)) {
        selectedClasses.add(name);
        mutateSelected();
      }
    },
    remove(...names) {
      names.forEach(name => { if (selectedClasses.delete(name)) mutateSelected(); });
    },
    contains(name) { return selectedClasses.has(name); }
  },
  querySelector(selector) {
    if (selector === '.tour-flights') {
      flightRootReads += 1;
      return flights;
    }
    if (selector === '.lead-form') return {
      scrollIntoView() {},
      querySelector(name) { return name === 'input[name="phone"]' ? { focus() { leadFocuses += 1; } } : null; }
    };
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '.flight-variant') return priceVariants;
    if (selector === '[data-search3-selected-flow-owned="1"]') {
      return [flightRetry, fallbackAction].filter(node => node && !node.removed && node.dataset.search3SelectedFlowOwned === '1');
    }
    return [];
  }
};

const document = {
  body: {
    classList: {
      contains(name) { return bodyClasses.has(name); },
      toggle(name, enabled) { enabled ? bodyClasses.add(name) : bodyClasses.delete(name); }
    }
  },
  getElementById(id) { return id === 'selectedTour' ? selected : null; },
  addEventListener(name, handler) { if (name === 'click') nativeClick = handler; },
  createElement: element
};

const window = {
  addEventListener(name, handler) {
    const previous = events.get(name);
    events.set(name, previous ? event => { previous(event); handler(event); } : handler);
  },
  dispatchEvent(event) { notifications.push(event.type); if (events.has(event.type)) events.get(event.type)(event); },
  requestAnimationFrame(handler) { frames.push(handler); }
};

const context = vm.createContext({
    document,
    window,
    MutationObserver: function (callback) {
      observerCallback = callback;
      this.observe = function () {};
    },
    Intl,
    Array,
    Number,
    String,
    Object,
    setTimeout(handler) { tasks.push(handler); },
    CustomEvent: function (type, options) { this.type = type; this.detail = options && options.detail; },
    getComputedStyle() { return { display: 'block' }; }
});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/search3/behavior/summary-cta.js'), 'utf8'), context);
const selectedSource = process.argv.includes('--source')
  ? fs.readFileSync(path.join(__dirname, '../src/search3/behavior/selected-flow-v2.js'), 'utf8')
    .replace('/* @include behavior/selected/flight-fallback.js */', fs.readFileSync(path.join(__dirname, '../src/search3/behavior/selected/flight-fallback.js'), 'utf8'))
  : fs.readFileSync(path.join(__dirname, '../v2/search3-selected-flow-v2.js'), 'utf8');
vm.runInContext(selectedSource, context);

const flush = () => {
  let rounds = 0;
  while (frames.length || tasks.length || mutationPending) {
    assert.ok(++rounds < 20, 'native handoff and selected observer settle without a mutation loop');
    while (tasks.length) tasks.shift()();
    while (frames.length) frames.shift()();
    if (mutationPending) {
      mutationPending = false;
      observerCallback();
    }
  }
};

flush();
assert.ok(bodyClasses.has('search3-selected-open'), 'selected visibility remains synchronized');
assert.equal(window.Search3SelectedFlowV2.version, 6);
assert.equal(window.Search3SelectedFlowV2.activateReview, undefined, 'duplicate review action API is retired');
assert.equal(window.Search3SelectedTourMobile, undefined, 'retired mobile presentation API is not rebuilt');
assert.equal(window.Search3CandidateSelectedPresentationV1, undefined, 'retired detail formatter API is not rebuilt');
assert.equal(window.Search3SelectedFlowV2Helpers, undefined, 'retired aggregate helper API is not rebuilt');

events.get('v2:tour-selected')({ detail: { tour: { id: 'tour-1', price: 100000 } } });
events.get('v2:tour-price-updated')({ detail: { price: 120000 } });
events.get('v2:flight-selected')({ detail: {} });
assert.equal(frames.length, 1, 'related selected and price events share one retained frame');
flush();

const bestClasses = new Set();
const best = {
  textContent: 'К минимальной цене',
  classList: {
    contains(name) { return bestClasses.has(name); },
    toggle(name, enabled) { enabled ? bestClasses.add(name) : bestClasses.delete(name); }
  }
};
const decimal = { textContent: 'К минимальной цене', classList: { contains() { return false; }, toggle() {} } };
priceVariants = [
  {
    querySelector(selector) { return selector === '.flight-choice>b' ? { textContent: 'Стоимость тура: 72 832 ₽' } : null; },
    querySelectorAll(selector) { return selector === '.flight-choice-tradeoffs span' ? [best] : []; }
  },
  {
    querySelector(selector) { return selector === '.flight-choice>b' ? { textContent: 'Стоимость тура: 90 049,6 ₽' } : null; },
    querySelectorAll(selector) { return selector === '.flight-choice-tradeoffs span' ? [decimal] : []; }
  }
];
assert.equal(window.Search3SelectedFlowV2.localizedMoneyNumber('Стоимость тура: 90 049,6 ₽'), 90049.6);
assert.equal(window.Search3SelectedFlowV2.correctFlightTradeoffs(), true);
assert.equal(best.textContent, 'Самая низкая цена');
assert.ok(bestClasses.has('is-best-price'));
assert.equal(decimal.textContent.replace(/\s/g, ' '), '+17 217,6 ₽ к минимальной');

priceVariants = [];
flightDataPresent = false;
// The controller replaces its flight markup when an asynchronous empty response arrives.
fallbackAction.remove();
const previousActionInsertions = actionInsertions;
flightRootReads = 0;
window.Search3SelectedFlowV2.sync();
window.Search3SelectedFlowV2.sync();
assert.equal(flightRootReads, 3, 'native handoff reads its flight root only while recreating a missing action');
assert.equal(fallbackDataWrites, 1, 'stable no-flight marker is written once');
assert.equal(retryInsertions, 1, 'no-flight state creates one delegated retry');
assert.equal(actionInsertions - previousActionInsertions, 1, 'no-flight state delegates exactly one missing CTA to its native owner');
assert.equal(flightRetry.getAttribute('data-tid'), 'tour-1');
assert.equal(flightRetry.textContent, 'Проверить рейсы ещё раз');
assert.match(emptyMessage.textContent, /менеджер уточнит перелёт по заявке/);
assert.equal(mutationPending, true, 'no-flight DOM changes produce one observer delivery');
mutationPending = false;
observerCallback();
assert.equal(frames.length, 1, 'the observer coalesces no-flight DOM changes into one settling frame');
flush();
assert.equal(frames.length, 0, 'settled no-flight DOM does not wake the observer again');
assert.equal(fallbackButton.textContent, 'Оставить заявку', 'fallback uses the native lead entry label');
fallbackButton.click();
assert.equal(leadClicks, 1);
assert.equal(leadFocuses, 1, 'native fallback handoff focuses the canonical phone field once');
assert.ok(selectedClasses.has('search3-lead-entry'));
assert.deepEqual(notifications, ['search3:lead-entry']);
flush();
assert.equal(actionInsertions - previousActionInsertions, 1, 'lead entry does not create a duplicate CTA');

flightErrorPresent = true;
emptyMessage.textContent = 'Не удалось загрузить рейсы';
assert.equal(window.Search3SelectedFlowV2.ensureEmptyFlightRecovery(flights), null,
  'controller-owned errors do not gain a duplicate retry');
flightErrorPresent = false;
flightDataPresent = true;
window.Search3SelectedFlowV2.sync();
assert.ok(!selectedClasses.has('search3-flight-fallback'));
assert.equal(flightRetry, null, 'owned empty-state retry is removed after recovery');
assert.ok(fallbackAction && !fallbackAction.removed, 'fallback cleanup preserves the native handoff owner');

selected.hidden = true;
window.Search3SelectedFlowV2.sync();
assert.ok(!bodyClasses.has('search3-selected-open'), 'hidden selected tour clears shared selected state');
console.log('PASS: selected owner retains state, no-flight recovery/native lead handoff, and decimal-safe price labels');

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
  const phone = { focus() {} };
  const form = { scrollIntoView() { scrolls += 1; }, querySelector(selector) { assert.equal(selector, 'input[name="phone"]'); return phone; } };
  const root = {
    children: [],
    classList: {
      contains: name => phase.has(name),
      add: name => phase.add(name),
      remove: (...names) => names.forEach(name => phase.delete(name))
    },
    querySelector(selector) {
      if (selector === '.tour-flights') return flightRoot;
      if (selector.endsWith('.section-heading strong')) return heading;
      if (selector.endsWith('.section-heading span')) return hint;
      if (selector === '.search3-lead-shell,.lead-form') return flightRoot;
      if (selector === '.search3-booking-summary') return null;
      if (selector === '.lead-form') return form;
      throw new Error('Unexpected query: ' + selector);
    }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../src/search3/behavior/summary-cta.js'), 'utf8'), {
    document: { getElementById() { return root; }, addEventListener(_name, fn) { onClick = fn; } },
    window: { addEventListener(name, fn) { callbacks.set(name, fn); }, dispatchEvent(event) { notifications.push(event.type); } },
    setTimeout(fn) { tasks.push(fn); },
    ensureLeadNote() {},
    CustomEvent: function (type) { this.type = type; }
  });
  callbacks.get('v2:flight-selected')();
  while (tasks.length) tasks.shift()();
  assert.equal(action.hidden, false);
  assert.equal(button.textContent, 'Оставить заявку');
  const click = { target: { closest(selector) { return selector === '#selectedTour .search3-flight-continue button' ? button : null; } }, preventDefault() {} };
  onClick(click);
  assert.equal(phase.has('search3-lead-entry'), true);
  assert.equal(phase.has('search3-final-review'), false);
  assert.deepEqual(notifications, ['search3:lead-entry']);
  assert.equal(heading.textContent, '', 'compact CTA does not rewrite controller heading copy');
  assert.equal(hint.textContent, '', 'compact CTA does not add duplicate flight guidance');
  assert.equal(scrolls, 1);
  callbacks.get('v2:tour-selected')();
  assert.equal(tasks.length, 1, 'tour reset shares one review/lead task');
  while (tasks.length) tasks.shift()();
  assert.equal(phase.has('search3-final-review'), false);
  assert.equal(phase.has('search3-lead-entry'), false);
  console.log('PASS: native selected CTA reaches lead form without supplier segment mutation');
}
