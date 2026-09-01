'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class EventHub {
  constructor() { this.listeners = new Map(); }
  addEventListener(type, fn) {
    if (!this.listeners.has(type)) this.listeners.set(type, []);
    this.listeners.get(type).push(fn);
  }
  dispatchEvent(event) {
    for (const fn of this.listeners.get(event.type) || []) fn(event);
    return true;
  }
}

class CustomEventMock {
  constructor(type, init = {}) { this.type = type; this.detail = init.detail; }
}

const hub = new EventHub();
const status = {
  hidden: true,
  innerHTML: '',
  addEventListener() {},
};

const documentMock = {
  getElementById(id) { return id === 'status' ? status : null; },
  querySelector() { return null; },
};

const windowMock = {
  addEventListener: hub.addEventListener.bind(hub),
  dispatchEvent: hub.dispatchEvent.bind(hub),
};

global.window = windowMock;
global.document = documentMock;
global.CustomEvent = CustomEventMock;

global.getComputedStyle = () => ({ display: 'none', visibility: 'hidden' });

const source = fs.readFileSync('v2/search-progress-ux-v1.js', 'utf8');
vm.runInThisContext(source, { filename: 'v2/search-progress-ux-v1.js' });

assert.ok(window.V2SearchProgressUXV1, 'search progress module should initialize');

let resolveApi;
let renderCount = 0;
let completed = 0;
hub.addEventListener('v2:search-complete', () => { completed += 1; });

window.V2SearchLifecycle = { searchId: 101 };
window.V2Runtime = {
  api(name, params) {
    assert.equal(name, 'search_results');
    assert.equal(params.searchId, 101);
    return new Promise(resolve => { resolveApi = resolve; });
  },
};
window.V2Results = { render() { renderCount += 1; } };

(async () => {
  const staleAttempt = window.V2SearchProgressUXV1.retryFinalResults({ disabled: false, textContent: '' });
  window.V2SearchLifecycle.searchId = 202;
  resolveApi([{ id: 'old-result' }]);
  assert.equal(await staleAttempt, false, 'stale retry must be ignored');
  assert.equal(renderCount, 0, 'stale retry must not render old results');
  assert.equal(completed, 0, 'stale retry must not dispatch search-complete');

  window.V2Runtime.api = async (name, params) => {
    assert.equal(name, 'search_results');
    assert.equal(params.searchId, 202);
    return [{ id: 'current-result' }];
  };
  const currentAttempt = await window.V2SearchProgressUXV1.retryFinalResults({ disabled: false, textContent: '' });
  assert.equal(currentAttempt, true, 'current retry should render');
  assert.equal(renderCount, 1, 'current retry should render exactly once');
  assert.equal(completed, 1, 'current retry should dispatch search-complete once');

  console.log('search_results_recovery_test: PASS');
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
