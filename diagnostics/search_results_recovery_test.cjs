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
  let visibleItems = [];
  let sort = 'rating';
  const compared = new Set();
  window.window = window;
  window.CustomEvent = TestCustomEvent;
  window.V2SearchLifecycle = lifecycle;
  window.V2Runtime = {
    api(action, params) {
      calls.push({ action, params });
      return pending.promise;
    },
  };
  window.V2Results = {
    render(items) {
      visibleItems = items.slice();
      renders.push(visibleItems.slice());
      for (const id of Array.from(compared)) {
        if (!visibleItems.some(item => String(item.id) === id)) compared.delete(id);
      }
    },
  };
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
    dispatch(type, detail) { window.dispatchEvent(new TestCustomEvent(type, { detail })); },
    seed(items, selected = []) {
      visibleItems = items.slice();
      selected.forEach(id => compared.add(String(id)));
      window.dispatchEvent(new TestCustomEvent('v2:results-rendered', { detail: { items } }));
    },
    state() { return { visibleItems: visibleItems.slice(), sort, compared: Array.from(compared) }; },
    setSort(value) { sort = value; },
    nextRequest() { pending = deferred(); },
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
  assert.equal(current.calls[0].params.limit, 100);
  assert.equal(current.renders.length, 1);
  assert.match(current.status.innerHTML, /Поиск завершён/);

  // A progressive search_results refresh failure is non-destructive.
  const progressive = loadProgressModule();
  progressive.seed([{ id: 11 }, { id: 12 }]);
  progressive.dispatch('v2:search-progress', { searchId: 71, progress: 45 });
  const progressiveStatus = progressive.status.innerHTML;
  progressive.dispatch('v2:search-progress-results-error', { searchId: 71, progress: 45, error: new Error('temporary') });
  assert.deepEqual(progressive.state().visibleItems.map(item => item.id), [11, 12]);
  assert.equal(progressive.renders.length, 0);
  assert.equal(progressive.status.innerHTML, progressiveStatus);

  // A continuation failure may replace status copy, but not already-found hotels.
  const continuation = loadProgressModule();
  continuation.seed([{ id: 21 }, { id: 22 }]);
  continuation.dispatch('v2:search-continue-started', { searchId: 71, previousResultsCount: 2 });
  continuation.dispatch('v2:search-continue-error', { searchId: 71, error: new Error('temporary continue failure') });
  assert.deepEqual(continuation.state().visibleItems.map(item => item.id), [21, 22]);
  assert.equal(continuation.renders.length, 0);
  assert.match(continuation.status.innerHTML, /Уже найденные предложения остаются на месте/);

  // A failed completed-search retry remains results-only and preserves cards; a later retry replaces
  // the result set (rather than appending), so duplicate hotel ids cannot be introduced.
  const repeated = loadProgressModule();
  repeated.seed([{ id: 31 }, { id: 32 }]);
  repeated.dispatch('v2:search-progress', { searchId: 71, progress: 100 });
  const firstRetry = repeated.retry();
  repeated.reject(new Error('temporary final failure'));
  assert.equal(await firstRetry, false);
  assert.deepEqual(repeated.state().visibleItems.map(item => item.id), [31, 32]);
  assert.equal(repeated.calls.map(call => call.action).join(','), 'search_results');
  assert.match(repeated.status.innerHTML, /Загрузить результаты ещё раз/);
  repeated.nextRequest();
  const secondRetry = repeated.retry();
  repeated.resolve([{ id: 31 }, { id: 32 }, { id: 33 }]);
  assert.equal(await secondRetry, true);
  assert.deepEqual(repeated.state().visibleItems.map(item => item.id), [31, 32, 33]);
  assert.equal(repeated.calls.map(call => call.action).join(','), 'search_results,search_results');

  // Sorting and comparison membership survive a transient failure and remain coherent on recovery.
  const stateful = loadProgressModule();
  stateful.seed([{ id: 41 }, { id: 42 }], [41, 42]);
  stateful.setSort('stars');
  stateful.dispatch('v2:search-progress-results-error', { searchId: 71, progress: 70, error: new Error('temporary') });
  assert.deepEqual(stateful.state(), { visibleItems: [{ id: 41 }, { id: 42 }], sort: 'stars', compared: ['41', '42'] });
  const recovery = stateful.retry();
  stateful.resolve([{ id: 41 }, { id: 42 }, { id: 43 }]);
  assert.equal(await recovery, true);
  assert.deepEqual(stateful.state(), { visibleItems: [{ id: 41 }, { id: 42 }, { id: 43 }], sort: 'stars', compared: ['41', '42'] });

  // A response belonging to the old completed search must not overwrite a new/dirty run.
  const stale = loadProgressModule();
  const stalePromise = stale.retry();
  stale.lifecycle.generation = 5;
  stale.lifecycle.searchId = 88;
  stale.resolve([{ id: 902, name: 'Stale hotel' }]);
  assert.equal(await stalePromise, false);
  assert.equal(stale.renders.length, 0);
  assert.deepEqual(stale.state().visibleItems, []);

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
