const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../v2/search-continue-v6.js'), 'utf8');
assert.doesNotMatch(source, /let\s+searchId\s*=/, 'continuation must not own a mirrored searchId');
assert.match(source, /window\.V2SearchLifecycle/, 'continuation must read canonical lifecycle state');

const listeners = new Map();
class CustomEvent {
  constructor(type, init = {}) {
    this.type = type;
    this.detail = init.detail || {};
  }
}

const button = {
  dataset: {},
  disabled: false,
  textContent: '',
  listeners: {},
  addEventListener(type, handler) { this.listeners[type] = handler; }
};
let more = null;
const resultsNode = {
  insertAdjacentElement(position, node) {
    assert.equal(position, 'afterend');
    more = node;
  }
};
const document = {
  getElementById(id) {
    if (id === 'results') return resultsNode;
    if (id === 'v2SearchMore') return more;
    return null;
  },
  querySelector(selector) {
    return selector === '#v2SearchMore button' && more ? button : null;
  },
  createElement(tag) {
    assert.equal(tag, 'div');
    return {
      id: '',
      className: '',
      innerHTML: '',
      remove() { if (more === this) more = null; },
      querySelector(selector) {
        assert.equal(selector, 'button');
        return button;
      }
    };
  }
};

const calls = [];
let rendered = 0;
const lifecycle = { searchId: 0 };
const window = {
  V2SearchLifecycle: lifecycle,
  V2Runtime: {
    async api(action, params) {
      calls.push({ action, params: { ...params } });
      if (action === 'search_results') return [];
      throw new Error('unexpected API action: ' + action);
    }
  },
  V2Results: {
    state: { items: [] },
    render(items, options) {
      rendered += 1;
      assert.deepEqual(items, []);
      assert.deepEqual(options, { empty: false });
    }
  },
  addEventListener(type, handler) {
    const bucket = listeners.get(type) || [];
    bucket.push(handler);
    listeners.set(type, bucket);
  },
  dispatchEvent(event) {
    for (const handler of listeners.get(event.type) || []) handler(event);
  }
};

vm.runInNewContext(source, {
  window,
  document,
  console,
  CustomEvent,
  setTimeout,
  clearTimeout,
  Date,
  Math,
  Number,
  String,
  Array,
  Error,
  Promise
});

(async () => {
  assert.equal(typeof window.V2SearchContinue.ensureMore, 'function');

  window.V2SearchContinue.ensureMore();
  assert.equal(more, null, 'no continuation control without a canonical search id');

  lifecycle.searchId = 41;
  window.V2SearchContinue.ensureMore();
  assert.ok(more, 'canonical lifecycle id enables continuation');

  lifecycle.searchId = 0;
  window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { searchId: 41 } }));
  assert.equal(more, null, 'reset removes continuation without maintaining a local search id');

  lifecycle.searchId = 42;
  window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 999 } }));
  assert.equal(more, null, 'started event only invalidates local operation state');

  window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 999 } }));
  assert.equal(more, null, 'stale completion cannot overwrite canonical lifecycle identity');

  window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 42 } }));
  assert.ok(more, 'matching canonical completion exposes continuation');

  button.dataset.retryResults = '1';
  lifecycle.searchId = 43;
  await button.listeners.click();

  const resultCall = calls.find(call => call.action === 'search_results');
  assert.equal(resultCall.params.searchId, 43, 'continuation reads the current lifecycle search id at click time');
  assert.equal(rendered, 1);
  assert.equal(more, null, 'empty continued result removes the continuation control');

  console.log('SEARCH3_CONTINUE_CANONICAL_SEARCH_ID_OK mirror=0 stale_complete=guarded current_id=43');
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
