'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(process.argv[2] || 'v2/prototype-search/results-date-refresh-v1.js', 'utf8');
const entry = fs.readFileSync(process.argv[3] || 'v2/prototype-search/index.php', 'utf8');
assert.match(entry, /results-date-refresh-v1\.js/, 'preview PHP entrypoint must load the date refresh module');
assert.ok(entry.indexOf('results-date-refresh-v1.js') < entry.indexOf('preg_replace_callback'), 'injected script must receive the normal cache-busting pass');

const clickListeners = [];
const popListeners = [];
let submissions = 0;
let disabled = false;
const form = {requestSubmit(){ submissions++; }};
const document = {
  getElementById(id){ return id === 'search-form' ? form : null; },
  querySelector(selector){ return selector === '.search-submit' ? {disabled} : null; },
  addEventListener(type, listener){ if (type === 'click') clickListeners.push(listener); }
};
const location = {search:'?from=2026-09-25&to=2026-09-30'};
const history = {state:null};
const context = {
  window:{}, document, location, history, URLSearchParams, console,
  queueMicrotask,
  addEventListener(type, listener){ if (type === 'popstate') popListeners.push(listener); }
};
vm.runInNewContext(source, context, {filename:'results-date-refresh-v1.js'});
assert.equal(context.window.AnyTourPrototypeResultsDateRefreshV1, true);
assert.equal(clickListeners.length, 1);
assert.equal(popListeners.length, 1);

const target = action => ({closest: selector => selector === '[data-action]' ? {dataset:{action}} : null});
const click = action => clickListeners[0]({target:target(action)});
const flush = async () => { await Promise.resolve(); await Promise.resolve(); };

(async () => {
  click('calendar');
  location.search='?from=2026-09-26&to=2026-10-01';
  click('apply-dates');
  await flush();
  assert.equal(submissions, 1, 'changed results dates must resubmit the canonical search form');

  click('calendar');
  click('apply-dates');
  await flush();
  assert.equal(submissions, 1, 'unchanged results dates must not spend another supplier search');

  click('dates');
  location.search='?from=2026-10-02&to=2026-10-08';
  click('apply-dates');
  await flush();
  assert.equal(submissions, 1, 'main form date editing must still wait for explicit Find tours');

  click('calendar');
  location.search='?from=2026-10-03&to=2026-10-09';
  click('close-modal');
  click('apply-dates');
  await flush();
  assert.equal(submissions, 1, 'closed modal context must not leak into a later action');

  history.state={anything:{type:'dates',source:'results'}};
  popListeners[0]();
  await flush();
  location.search='?from=2026-10-04&to=2026-10-10';
  click('apply-dates');
  await flush();
  assert.equal(submissions, 2, 'history-restored results date modal must preserve refresh semantics');

  click('calendar');
  location.search='?from=2026-10-05&to=2026-10-11';
  disabled=true;
  click('apply-dates');
  await flush();
  assert.equal(submissions, 2, 'disabled canonical submit must stay fail-closed');

  console.log('search3 prototype results date refresh v1: PASS');
})().catch(error=>{console.error(error);process.exitCode=1;});
