'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');

function environment(file) {
  const listeners = new Map();
  const statusListeners = new Map();
  const fields = {
    dateFrom: { value: '2030-05-10', events: [] },
    dateTo: { value: '2030-05-20', events: [] },
    daysFrom: { value: '7', min: '1', events: [] },
    daysTill: { value: '10', max: '28', events: [] }
  };
  Object.values(fields).forEach(field => {
    field.dispatchEvent = event => field.events.push(event.type);
  });
  const submit = { focused: 0, focus() { this.focused += 1; } };
  const extras = { open: false };
  const form = {
    scrolled: 0,
    scrollIntoView() { this.scrolled += 1; },
    querySelector(selector) {
      const match = selector.match(/^\[name="(.+)"\]$/);
      if (match) return fields[match[1]] || null;
      if (selector === '.search-submit') return submit;
      if (selector === 'details.extras') return extras;
      return null;
    }
  };
  const status = {
    hidden: true,
    innerHTML: '',
    addEventListener(type, handler) { statusListeners.set(type, handler); }
  };
  const events = [];
  const window = {
    addEventListener(type, handler) {
      if (!listeners.has(type)) listeners.set(type, []);
      listeners.get(type).push(handler);
    },
    dispatchEvent(event) {
      events.push(event);
      (listeners.get(event.type) || []).forEach(handler => handler(event));
    }
  };
  class CustomEvent {
    constructor(type, options = {}) { this.type = type; this.detail = options.detail; }
  }
  class Event {
    constructor(type) { this.type = type; }
  }
  const document = {
    getElementById(id) { return id === 'status' ? status : id === 'tourSearch' ? form : null; },
    querySelector() { return null; }
  };
  const sandbox = { window, document, CustomEvent, Event, Date, Number, String, Array, Math, Promise,
    getComputedStyle() { return { display: 'none', visibility: 'hidden' }; } };
  vm.runInNewContext(fs.readFileSync(path.join(root, file), 'utf8'), sandbox, { filename: file });
  return {
    status, fields, submit, extras, form, events,
    emit(type, detail = {}) { window.dispatchEvent(new CustomEvent(type, { detail })); },
    click(selector) {
      const target = { disabled: false, textContent: '', closest(candidate) { return candidate === selector ? this : null; } };
      statusListeners.get('click')({ target });
      return target;
    },
    window
  };
}

function snapshot(env) {
  return { hidden: env.status.hidden, html: env.status.innerHTML };
}

async function settle() {
  await new Promise(resolve => setImmediate(resolve));
}

async function main() {
  const legacy = environment('v2/search-progress-ux-v1.js');
  const current = environment('src/search3/behavior/search-progress.js');
  const scenarios = [
    ['v2:search-started', {}],
    ['v2:results-rendered', { items: [{}, {}] }],
    ['v2:search-progress', { progress: 42 }],
    ['v2:search-complete', { items: [{}, {}, {}] }],
    ['v2:search-error', { phase: 'status', error: { code: 'NETWORK' } }],
    ['v2:search-dirty', {}],
    ['v2:search-complete', { items: [] }],
    ['v2:search-continue-started', { previousResultsCount: 3 }],
    ['v2:search-continue-requested', { requestCount: 2 }],
    ['v2:search-continue-progress', { progress: 57 }],
    ['v2:search-continued', { addedResultsCount: 2, items: [{}, {}] }],
    ['v2:search-continue-error', { retryResultsOnly: false, error: { message: 'Сбой продолжения' } }],
    ['v2:search-continue-error', { retryResultsOnly: true }]
  ];
  for (const [type, detail] of scenarios) {
    legacy.emit(type, detail);
    current.emit(type, detail);
    assert.deepEqual(snapshot(current), snapshot(legacy), `presentation drift after ${type}`);
  }

  const validationSnapshot = snapshot(current);
  current.emit('v2:search-error', { phase: 'validation', error: { code: 'INVALID' } });
  assert.deepEqual(snapshot(current), validationSnapshot, 'validation error must remain owned by the form');

  legacy.emit('v2:search-complete', { items: [] });
  current.emit('v2:search-complete', { items: [] });
  legacy.click('.search-progress-relax-dates');
  current.click('.search-progress-relax-dates');
  assert.deepEqual(
    Object.fromEntries(Object.entries(current.fields).map(([name, field]) => [name, [field.value, field.events]])),
    Object.fromEntries(Object.entries(legacy.fields).map(([name, field]) => [name, [field.value, field.events]])),
    'date recovery drifted'
  );
  assert.deepEqual(snapshot(current), snapshot(legacy), 'date recovery presentation drifted');
  assert.equal(current.submit.focused, legacy.submit.focused, 'date recovery focus drifted');
  assert.equal(current.form.scrolled, legacy.form.scrolled, 'date recovery scroll drifted');
  assert.ok(current.events.some(event => event.type === 'v2:search-dirty' && event.detail.source === 'recovery'),
    'date recovery must notify current Search3 consumers');

  const legacyNights = environment('v2/search-progress-ux-v1.js');
  const currentNights = environment('src/search3/behavior/search-progress.js');
  legacyNights.click('.search-progress-relax-nights');
  currentNights.click('.search-progress-relax-nights');
  assert.deepEqual([currentNights.fields.daysFrom.value, currentNights.fields.daysTill.value],
    [legacyNights.fields.daysFrom.value, legacyNights.fields.daysTill.value], 'night recovery drifted');
  assert.deepEqual(snapshot(currentNights), snapshot(legacyNights), 'night recovery presentation drifted');

  const actions = environment('src/search3/behavior/search-progress.js');
  actions.click('.search-progress-filters');
  assert.equal(actions.extras.open, true, 'filter recovery must open additional parameters');
  actions.click('.search-progress-edit');
  assert.equal(actions.form.scrolled, 2, 'edit and filter recovery must return to the form');
  let submits = 0;
  actions.window.V2SearchLifecycle = { submit() { submits += 1; } };
  const retryButton = actions.click('.search-progress-retry');
  assert.equal(retryButton.disabled, true, 'normal retry must prevent duplicate clicks');
  assert.equal(submits, 1, 'normal retry must use the current lifecycle');

  const recovered = environment('src/search3/behavior/search-progress.js');
  recovered.window.V2SearchLifecycle = { searchId: 41, generation: 7, dirty: false };
  let apiCall;
  recovered.window.V2Runtime = { async api(name, payload) { apiCall = [name, payload]; return [{ id: 1 }]; } };
  let rendered;
  recovered.window.V2Results = { render(items) { rendered = items; } };
  recovered.emit('v2:search-progress', { progress: 100 });
  recovered.emit('v2:search-error', { phase: 'status', error: { code: 'NETWORK' } });
  assert.match(recovered.status.innerHTML, /search-progress-retry-results/, 'completed search needs results-only retry');
  const resultsButton = recovered.click('.search-progress-retry-results');
  await settle();
  assert.equal(apiCall[0], 'search_results', 'retry must not restart search');
  assert.equal(apiCall[1].searchId, 41, 'retry must preserve the active search id');
  assert.equal(apiCall[1].limit, 100, 'retry must preserve the result limit');
  assert.deepEqual(rendered, [{ id: 1 }], 'recovered results were not rendered');
  assert.equal(resultsButton.disabled, true, 'result retry must prevent duplicate clicks');
  assert.ok(recovered.events.some(event => event.type === 'v2:search-complete'
    && event.detail.recoveredResults === true && event.detail.searchId === 41),
  'result recovery must notify analytics and current result consumers');

  const stale = environment('src/search3/behavior/search-progress.js');
  stale.window.V2SearchLifecycle = { searchId: 8, generation: 2, dirty: false };
  let resolveStale;
  stale.window.V2Runtime = { api() { return new Promise(resolve => { resolveStale = resolve; }); } };
  let staleRender = 0;
  stale.window.V2Results = { render() { staleRender += 1; } };
  stale.click('.search-progress-retry-results');
  stale.window.V2SearchLifecycle.generation = 3;
  resolveStale([{}]);
  await settle();
  assert.equal(staleRender, 0, 'stale generation must not overwrite current results');
  assert.equal(stale.events.filter(event => event.type === 'v2:search-complete').length, 0,
    'stale retry must not emit completion');

  const failed = environment('src/search3/behavior/search-progress.js');
  failed.window.V2SearchLifecycle = { searchId: 9, generation: 1, dirty: false };
  failed.window.V2Runtime = { async api() { throw new Error('network'); } };
  failed.window.V2Results = { render() { assert.fail('failed retry rendered results'); } };
  failed.emit('v2:search-progress', { progress: 100 });
  failed.click('.search-progress-retry-results');
  await settle();
  assert.match(failed.status.innerHTML, /Поиск завершён — результаты не загрузились/, 'retry failure lost recovery UI');

  assert.equal(current.window.V2SearchProgressUXV1, undefined, 'Search3 owner must not recreate legacy global');
  assert.ok(legacy.window.V2SearchProgressUXV1, 'legacy fixture no longer exposes its compatibility global');

  console.log('SEARCH3_PROGRESS_OWNER_OK scenarios=' + scenarios.length
    + ' recovery=dates,nights,edit,filters,retry,retry-results,stale,failure');
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
