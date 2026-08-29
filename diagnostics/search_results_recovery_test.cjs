const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class TestCustomEvent extends Event {
  constructor(type, init = {}) {
    super(type);
    this.detail = init.detail;
  }
}

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((yes, no) => {
    resolve = yes;
    reject = no;
  });
  return { promise, resolve, reject };
}

function loadProgressModule() {
  const status = {
    hidden: true,
    innerHTML: '',
    addEventListener() {},
  };
  const window = new EventTarget();
  const lifecycle = { searchId: 71, generation: 4, dirty: false };
  const calls = [];
  const renders = [];
  let pending = deferred();
  window.window = window;
  window.CustomEvent = TestCustomEvent;
  window.V2SearchLifecycle = lifecycle;
  window.V2Runtime = {
    api(action, params) {
      calls.push({ action, params });
      return pending.promise;
    },
  };
  window.V2Results = { render(items) { renders.push(items); } };
  const document = {
    getElementById(id) { return id === 'status' ? status : null; },
    querySelector() { return null; },
  };
  const context = vm.createContext({ window, document, CustomEvent: TestCustomEvent, Event, console, setTimeout, clearTimeout });
  vm.runInContext(fs.readFileSync('v2/search-progress-ux-v1.js', 'utf8'), context, { filename: 'search-progress-ux-v1.js' });
  return {
    lifecycle,
    calls,
    renders,
    status,
    retry: window.V2SearchProgressUXV1.retryFinalResults,
    resolve(value) { pending.resolve(value); },
    reject(error) { pending.reject(error); },
  };
}

(async () => {
  const current = loadProgressModule();
  // A current completed search is recovered with search_results only.
  const currentPromise = current.retry();
  current.resolve([{ id: 901, name: 'Current hotel' }]);
  assert.equal(await currentPromise, true);
  assert.equal(current.calls.length, 1);
  assert.equal(current.calls[0].action, 'search_results');
  assert.equal(current.calls[0].params.searchId, 71);
  assert.equal(current.calls[0].params.limit, 25);
  assert.equal(current.renders.length, 1);
  assert.match(current.status.innerHTML, /Поиск завершён/);

  // A response belonging to the old completed search must not overwrite a new/dirty run.
  const stale = loadProgressModule();
  const stalePromise = stale.retry();
  stale.lifecycle.generation = 5;
  stale.lifecycle.searchId = 88;
  stale.resolve([{ id: 902, name: 'Stale hotel' }]);
  assert.equal(await stalePromise, false);
  assert.equal(stale.renders.length, 0);

  // A late failure from the old request must not replace the current run's status either.
  const staleFailure = loadProgressModule();
  const failurePromise = staleFailure.retry();
  staleFailure.lifecycle.dirty = true;
  staleFailure.lifecycle.searchId = 0;
  staleFailure.reject(new Error('late failure'));
  assert.equal(await failurePromise, false);
  assert.equal(staleFailure.status.innerHTML, '');

  console.log('SEARCH_RESULTS_RECOVERY_STATE_OK');
})().catch(error => {
  console.error(error);
  process.exit(1);
});
