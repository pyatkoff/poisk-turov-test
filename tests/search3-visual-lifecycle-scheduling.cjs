'use strict';
// Characterize the LIVE lifecycle used by visual-search/index.php. No DOM,
// browser, provider HTTP or real lead transport is needed for this boundary.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const sourcePath = path.resolve(__dirname, '../v2/prototype-search/search-lifecycle-v1.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const plain = value => JSON.parse(JSON.stringify(value));

function harness(code = source) {
  const microtasks = [], trace = [], requests = [];
  const window = {};
  vm.runInNewContext(code, {window, Promise, queueMicrotask: task => microtasks.push(task)}, {filename: sourcePath});
  const target = () => {
    const listeners = new Map();
    return {
      hidden: true, listeners,
      addEventListener(type, handler) {
        const values = listeners.get(type) || [];
        values.push(handler); listeners.set(type, values);
      },
      emit(type, action) {
        const event = {preventDefault() {trace.push('prevent-default');},
          target: {closest() {return action ? {dataset: {action}} : null;}}};
        for (const handler of listeners.get(type) || []) handler(event);
      }
    };
  };
  const form = target(), events = target();
  const state = {key: 'same-search', allowed: true, covered: true, scopeThrows: false,
    prepareReturnsFalse: false, commitReturnsFalse: false, runnerThrows: false, response: null};
  form.requestSubmit = () => {trace.push('request-submit'); form.emit('submit');};
  const runner = kind => function (search, receive, hotelIds, filters) {
    assert.equal(this, data);
    trace.push(['runner', kind, plain(search), plain(hotelIds), plain(filters)]);
    if (state.runnerThrows) throw new Error('synchronous failure');
    let reject;
    const pending = new Promise((resolve, fail) => {reject = fail;});
    requests.push({kind, receive, reject});
    return pending;
  };
  const data = {
    search: runner('search'), resumeCached: runner('resume'), currentSupplierScope: {initial: true},
    supplierScope(filters) {trace.push(['scope', plain(filters)]); if (state.scopeThrows) throw new Error('scope failure'); return {next: true};},
    supplierScopeCovered(previous, next) {trace.push(['covered', plain(previous), plain(next)]); return state.covered;}
  };
  const lifecycle = window.AnyTourPrototypeSearchLifecycleV1.create({form, events, data,
    canSubmit: () => state.allowed,
    currentKey: () => state.key,
    supplierFilters: () => ({stars: [4]}),
    commit: () => state.commitReturnsFalse ? false : {},
    prepare(runOptions) {
      trace.push(['prepare', plain(runOptions)]);
      if (state.prepareReturnsFalse) return false;
      state.response = {key: state.key, pending: true, phase: 'loading', canContinue: false};
      return {response: state.response, search: {country: 4}, hotelIds: [42], filters: {meals: [7]}};
    },
    onResults(event, response) {trace.push(['results', plain(event), plain(response)]);},
    afterEvent(event, response) {trace.push(['event', plain(event), plain(response)]);},
    afterFailure(error, response) {trace.push(['failure', error.message, plain(response)]);},
    afterStart(response) {trace.push(['start', plain(response)]);},
    afterSubmit(started) {trace.push(['submitted', started]);}
  });
  lifecycle.bind();
  const flushOne = () => {const task = microtasks.shift(); if (task) task();};
  const flush = () => {let budget = 100; while (microtasks.length && budget-- > 0) flushOne(); assert.equal(microtasks.length, 0, 'microtask loop');};
  const receive = (event, index = requests.length - 1) => requests[index]?.receive(event);
  const snapshot = () => plain({trace, response: state.response, queued: microtasks.length, requests: requests.map(x => x.kind)});
  return {lifecycle, form, events, data, state, trace, requests, microtasks, flushOne, flush, receive, snapshot};
}

const cases = [];
const check = (name, body) => cases.push({name, body});
check('bind is idempotent and preserves the public API', () => {
  const h = harness();
  assert.equal(h.lifecycle.bind(), false);
  assert.equal(Object.isFrozen(h.lifecycle), true);
  assert.deepEqual(Object.keys(h.lifecycle), ['run', 'bind', 'invalidate', 'requestSubmit']);
  assert.equal(h.form.listeners.get('submit').length, 1);
  assert.equal(h.events.listeners.get('click').length, 1);
  assert.equal(h.events.listeners.get('change').length, 1);
});
check('repeated submit requests coalesce into one explicit search', () => {
  const h = harness();
  for (let i = 0; i < 4; i++) assert.equal(h.lifecycle.requestSubmit(), true);
  assert.equal(h.microtasks.length, 1); h.flush();
  assert.equal(h.requests.length, 1);
  assert.equal(h.trace.filter(x => x === 'request-submit').length, 1);
});
for (const action of ['invalidate', 'stop-search', 'edit-search']) {
  check(`${action} invalidates both queued operations`, () => {
    const h = harness(); h.lifecycle.run(); h.trace.length = 0;
    h.events.emit('change'); h.lifecycle.requestSubmit();
    if (action === 'invalidate') h.lifecycle.invalidate(); else h.events.emit('click', action);
    h.flush(); assert.equal(h.requests.length, 1); assert.deepEqual(h.trace, []);
  });
}
check('an old submit task cannot clear a newer generation reservation', () => {
  const h = harness(); h.lifecycle.requestSubmit(); h.lifecycle.invalidate(); h.lifecycle.requestSubmit();
  h.flushOne(); h.lifecycle.requestSubmit(); assert.equal(h.microtasks.length, 1);
  h.flush(); assert.equal(h.requests.length, 1);
});
check('an old scope task cannot clear a newer generation reservation', () => {
  const h = harness(); h.lifecycle.run(); h.trace.length = 0;
  h.events.emit('change'); h.lifecycle.invalidate(); h.events.emit('change');
  h.flushOne(); h.events.emit('change'); assert.equal(h.microtasks.length, 1); h.flush();
  assert.equal(h.trace.filter(x => x[0] === 'scope').length, 1);
  assert.equal(h.requests.length, 1);
});
check('a direct run invalidates a previously scheduled submission', () => {
  const h = harness(); h.lifecycle.requestSubmit(); assert.equal(h.lifecycle.run(), true);
  h.flush(); assert.equal(h.requests.length, 1);
});
check('submission is rechecked at execution time', () => {
  for (const reason of ['disallowed', 'missing-method', 'commit-cancelled']) {
    const h = harness(); h.lifecycle.requestSubmit();
    if (reason === 'disallowed') h.state.allowed = false;
    if (reason === 'missing-method') delete h.form.requestSubmit;
    if (reason === 'commit-cancelled') h.state.commitReturnsFalse = true;
    h.flush(); assert.equal(h.requests.length, 0, reason);
  }
});
check('a cancelled prepare does not invalidate a queued submission', () => {
  const h = harness(); h.lifecycle.requestSubmit(); h.state.prepareReturnsFalse = true;
  assert.equal(h.lifecycle.run(), false); h.state.prepareReturnsFalse = false; h.flush();
  assert.equal(h.requests.length, 1);
});
check('supplier filter inspection coalesces and never starts another search', () => {
  const h = harness(); h.lifecycle.run(); h.receive({type: 'complete', canContinue: true});
  h.state.covered = false;
  h.events.emit('change'); h.events.emit('click', 'filter'); h.events.emit('change');
  assert.equal(h.microtasks.length, 1); h.flush();
  assert.equal(h.state.response.phase, 'coverage_gap'); assert.equal(h.state.response.canContinue, false);
  assert.equal(h.requests.length, 1);
  h.state.covered = true; h.events.emit('change'); h.flush();
  assert.equal(h.state.response.phase, 'complete'); assert.equal(h.state.response.canContinue, true);
  assert.equal(h.state.response.message, ''); assert.equal(h.requests.length, 1);
});
check('a coverage gap survives completion until filters are covered', () => {
  const h = harness(); h.lifecycle.run(); h.state.covered = false; h.events.emit('change'); h.flush();
  h.receive({type: 'complete', canContinue: true});
  assert.equal(h.state.response.phase, 'coverage_gap'); assert.equal(h.state.response.canContinue, false);
  assert.equal(h.state.response.coverageCanContinue, true);
});
check('scope inspection checks current visibility and dependencies', () => {
  for (const reason of ['visible-before', 'visible-after', 'no-scope', 'throws', 'no-helper']) {
    const h = harness(); h.lifecycle.run();
    if (reason === 'visible-before') h.form.hidden = false;
    h.events.emit('change');
    if (reason === 'visible-after') h.form.hidden = false;
    if (reason === 'no-scope') h.data.currentSupplierScope = null;
    if (reason === 'throws') h.state.scopeThrows = true;
    if (reason === 'no-helper') delete h.data.supplierScope;
    h.flush(); assert.equal(h.state.response.coverageGap, false, reason); assert.equal(h.requests.length, 1);
  }
});
check('scope and submit keep separate coalescing slots and queue order', () => {
  for (const submitFirst of [true, false]) {
    const h = harness(); h.lifecycle.run(); h.trace.length = 0;
    const submit = () => h.lifecycle.requestSubmit(), scope = () => h.events.emit('change');
    (submitFirst ? submit : scope)(); (submitFirst ? scope : submit)();
    assert.equal(h.microtasks.length, 2); h.flush();
    assert.equal(h.requests.length, 2);
    assert.equal(h.trace.filter(x => x[0] === 'scope').length, submitFirst ? 0 : 1);
  }
});
check('old results and promise failures cannot update a new run with the same key', async () => {
  const h = harness(); h.lifecycle.run(); h.lifecycle.run(); h.trace.length = 0;
  const before = plain(h.state.response);
  h.receive({type: 'results', hotels: ['old']}, 0); h.receive({type: 'error', message: 'old'}, 0);
  h.requests[0].reject(new Error('old rejection')); await Promise.resolve();
  assert.deepEqual(plain(h.state.response), before); assert.deepEqual(h.trace, []);
  h.receive({type: 'results', hotels: ['current']});
  assert.deepEqual(h.trace.map(x => x[0]), ['results', 'event']);
});
check('changing the current key rejects otherwise current events', () => {
  const h = harness(); h.lifecycle.run(); h.trace.length = 0; h.state.key = 'another-search';
  h.receive({type: 'complete', canContinue: true}); assert.deepEqual(h.trace, []);
});
check('cached resume uses only the existing resume runner', () => {
  const h = harness(); assert.equal(h.lifecycle.run({resumeOnly: true}), true);
  assert.deepEqual(h.requests.map(x => x.kind), ['resume']);
});
check('synchronous and asynchronous active failures retain error handling', async () => {
  const h = harness(); h.state.runnerThrows = true; assert.equal(h.lifecycle.run(), false);
  assert.equal(h.state.response.phase, 'error');
  assert.equal(h.trace.some(x => x[0] === 'start'), false);
  h.state.runnerThrows = false; h.lifecycle.run(); h.requests.at(-1).reject(new Error('active rejection'));
  await Promise.resolve(); assert.equal(h.state.response.message, 'active rejection');
});

// Optional local differential check against a separately retained exact source:
// node tests/search3-visual-lifecycle-scheduling.cjs --compare /path/to/baseline.js
// The baseline is never fetched by this test or committed as a second owner.
function compareWith(baseline) {
  for (let seed = 1; seed <= 128; seed++) {
    const before = harness(baseline), after = harness(source); let random = seed;
    for (let step = 0; step < 64; step++) {
      random = (Math.imul(random, 1664525) + 1013904223) >>> 0;
      const command = random % 12;
      for (const h of [before, after]) {
        switch (command) {
          case 0: h.lifecycle.requestSubmit(); break;
          case 1: h.events.emit('change'); break;
          case 2: h.lifecycle.invalidate(); break;
          case 3: h.flushOne(); break;
          case 4: h.lifecycle.run(); break;
          case 5: h.events.emit('click', 'stop-search'); break;
          case 6: h.events.emit('click', 'edit-search'); break;
          case 7: h.state.covered = !h.state.covered; break;
          case 8: h.form.hidden = !h.form.hidden; break;
          case 9: h.state.allowed = !h.state.allowed; break;
          case 10: h.receive({type: 'complete', canContinue: true}); break;
          case 11: h.receive({type: 'progress', progress: 50}, 0); break;
        }
      }
      assert.deepEqual(after.snapshot(), before.snapshot(), `behavior changed at seed ${seed}, step ${step}`);
    }
    before.flush(); after.flush(); assert.deepEqual(after.snapshot(), before.snapshot(), `flush changed at seed ${seed}`);
  }
  console.log('PASS: baseline/candidate traces identical across 8192 scheduling steps and 128 final drains.');
}

(async () => {
  for (const {name, body} of cases) {await body(); console.log('PASS:', name);}
  if (process.argv[2]) {
    assert.equal(process.argv[2], '--compare', 'expected --compare /path/to/baseline.js');
    assert.ok(process.argv[3], 'baseline path required'); compareWith(fs.readFileSync(process.argv[3], 'utf8'));
  }
  console.log(`PASS: ${cases.length} LIVE lifecycle characterizations; provider HTTP=0, real leads=0.`);
})().catch(error => {console.error(error); process.exitCode = 1;});
