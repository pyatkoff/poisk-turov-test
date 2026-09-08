const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const events = new Map();
const frames = [];
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
let reviewClicks = 0;
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
    const button = fallbackButton = { textContent: '', click() { reviewClicks += 1; } };
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
    remove(name) {
      if (selectedClasses.delete(name)) mutateSelected();
    },
    contains(name) { return selectedClasses.has(name); }
  },
  querySelector(selector) {
    if (selector === '.tour-flights') {
      flightRootReads += 1;
      return flights;
    }
    if (selector === '.search3-flight-continue--fallback') return fallbackAction;
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '.flight-variant') return priceVariants;
    if (selector === '[data-search3-selected-flow-owned="1"]') {
      return [flightRetry, fallbackAction].filter(node => node && !node.removed);
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
  createElement: element
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
    MutationObserver: function (callback) {
      observerCallback = callback;
      this.observe = function () {};
    },
    Intl,
    Array,
    Number,
    String,
    Object,
    getComputedStyle() { return { display: 'block' }; }
  }
);

const flush = () => {
  while (frames.length || mutationPending) {
    while (frames.length) frames.shift()();
    if (mutationPending) {
      mutationPending = false;
      observerCallback();
    }
  }
};

flush();
assert.ok(bodyClasses.has('search3-selected-open'), 'selected visibility remains synchronized');
assert.equal(window.Search3SelectedFlowV2.version, 5);
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
flightRootReads = 0;
window.Search3SelectedFlowV2.sync();
window.Search3SelectedFlowV2.sync();
assert.equal(flightRootReads, 2, 'each sync reads the flight root once');
assert.equal(fallbackDataWrites, 1, 'stable no-flight marker is written once');
assert.equal(retryInsertions, 1, 'no-flight state creates one delegated retry');
assert.equal(actionInsertions, 1, 'no-flight state creates one review action');
assert.equal(flightRetry.getAttribute('data-tid'), 'tour-1');
assert.equal(flightRetry.textContent, 'Проверить рейсы ещё раз');
assert.match(emptyMessage.textContent, /менеджер уточнит перелёт по заявке/);
assert.equal(mutationPending, true, 'no-flight DOM changes produce one observer delivery');
mutationPending = false;
observerCallback();
assert.equal(frames.length, 1, 'the observer coalesces no-flight DOM changes into one settling frame');
flush();
assert.equal(frames.length, 0, 'settled no-flight DOM does not wake the observer again');
selectedClasses.add('search3-final-review');
window.Search3SelectedFlowV2.sync();
assert.equal(fallbackButton.textContent, 'Изменить рейс', 'fallback sync preserves the review exit label');
selectedClasses.delete('search3-final-review');
window.Search3SelectedFlowV2.sync();
assert.equal(fallbackButton.textContent, 'Оставить заявку', 'fallback sync restores the native lead entry label');
assert.equal(window.Search3SelectedFlowV2.activateReview(), true);
assert.equal(reviewClicks, 1, 'fallback lead entry delegates to the primary continue action');

flightErrorPresent = true;
emptyMessage.textContent = 'Не удалось загрузить рейсы';
assert.equal(window.Search3SelectedFlowV2.ensureEmptyFlightRecovery(flights), null,
  'controller-owned errors do not gain a duplicate retry');
flightErrorPresent = false;
flightDataPresent = true;
window.Search3SelectedFlowV2.sync();
assert.ok(!selectedClasses.has('search3-flight-fallback'));
assert.equal(flightRetry, null, 'owned empty-state retry is removed after recovery');
assert.equal(fallbackAction, null, 'owned empty-state continue action is removed after recovery');

selected.hidden = true;
window.Search3SelectedFlowV2.sync();
assert.ok(!bodyClasses.has('search3-selected-open'), 'hidden selected tour clears shared selected state');
console.log('PASS: selected owner retains state, no-flight recovery/review, and decimal-safe price labels');

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
