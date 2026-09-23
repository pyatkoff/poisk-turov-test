'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(process.argv[2] || 'v2/prototype-search/inventory-scope-refresh-v1.js', 'utf8');
const entry = fs.readFileSync(process.argv[3] || 'v2/prototype-search/index.php', 'utf8');
assert.match(entry, /inventory-scope-refresh-v1\.js/, 'preview PHP entrypoint must load the inventory refresh module');
assert.ok(entry.indexOf('inventory-scope-refresh-v1.js') < entry.indexOf('preg_replace_callback'), 'injected module must receive the normal cache-busting pass');

const flush = async () => { await Promise.resolve(); await Promise.resolve(); await Promise.resolve(); };
function harness(search, {hidden=true, disabled=false}={}) {
  const documentListeners = {click:[], change:[]};
  const submitListeners = [];
  let submissions = 0;
  let submitDisabled = disabled;
  const form = {
    hidden,
    requestSubmit() {
      submissions++;
      for (const listener of submitListeners) listener({target:form});
    },
    addEventListener(type, listener) { if (type === 'submit') submitListeners.push(listener); }
  };
  const document = {
    getElementById(id) { return id === 'search-form' ? form : null; },
    querySelector(selector) { return selector === '.search-submit' ? {disabled:submitDisabled} : null; },
    addEventListener(type, listener) { if (documentListeners[type]) documentListeners[type].push(listener); }
  };
  const location = {search};
  const context = {window:{}, document, location, URLSearchParams, queueMicrotask, console};
  vm.runInNewContext(source, context, {filename:'inventory-scope-refresh-v1.js'});
  assert.equal(context.window.AnyTourPrototypeInventoryScopeRefreshV1, true);
  assert.equal(documentListeners.click.length, 1);
  assert.equal(documentListeners.change.length, 1);
  assert.equal(submitListeners.length, 1);
  const fire = async type => { for (const listener of documentListeners[type]) listener({}); await flush(); };
  return {
    form, location, fire,
    submit: async () => { for (const listener of submitListeners) listener({target:form}); await flush(); },
    set disabled(value) { submitDisabled = value; },
    get submissions() { return submissions; }
  };
}

(async () => {
  {
    const h = harness('?searched=1&max=600000');
    h.location.search='?searched=1&max=500000';
    await h.fire('click');
    assert.equal(h.submissions, 0, 'narrower total budget must stay inside already fetched inventory');
    h.location.search='?searched=1&max=700000';
    await h.fire('click');
    assert.equal(h.submissions, 1, 'raising the total-budget ceiling must fetch the newly eligible offers');
    h.location.search='?searched=1&max=650000';
    await h.fire('change');
    assert.equal(h.submissions, 1, 'after refresh a narrower budget must stay local');
    h.location.search='?searched=1';
    await h.fire('click');
    assert.equal(h.submissions, 2, 'removing a prior budget ceiling must fetch the missing higher-price offers');
  }

  {
    const h = harness('?searched=1&min=200000');
    h.location.search='?searched=1&min=300000';
    await h.fire('change');
    assert.equal(h.submissions, 0, 'raising a prior minimum remains a local narrowing');
    h.location.search='?searched=1&min=100000';
    await h.fire('change');
    assert.equal(h.submissions, 1, 'lowering a prior minimum must fetch newly eligible cheaper offers');
  }

  {
    const h = harness('?searched=1&stars=5');
    h.location.search='?searched=1&stars=4';
    await h.fire('click');
    assert.equal(h.submissions, 1, 'switching a single upstream star category must start a real search');
    h.location.search='?searched=1';
    await h.fire('click');
    assert.equal(h.submissions, 2, 'removing a single upstream star category must start a broad search');
    h.location.search='?searched=1&stars=3%7C4';
    await h.fire('click');
    assert.equal(h.submissions, 2, 'a broad star inventory may be narrowed locally after refresh');
  }

  {
    const broad = harness('?searched=1&stars=4%7C5&meals=AI%7CHB');
    broad.location.search='?searched=1&stars=3&meals=AI';
    await broad.fire('change');
    assert.equal(broad.submissions, 0, 'zero/multiple star or meal values are broad upstream in current Search3');

    const narrow = harness('?searched=1&meals=HB');
    narrow.location.search='?searched=1';
    await narrow.fire('click');
    assert.equal(narrow.submissions, 1, 'removing a prior single-meal restriction must refresh inventory');
  }

  {
    const h = harness('?searched=1&resorts=%D0%A1%D0%B8%D0%B4%D0%B5%7C%D0%9A%D0%B5%D0%BC%D0%B5%D1%80');
    h.location.search='?searched=1&resorts=%D0%A1%D0%B8%D0%B4%D0%B5';
    await h.fire('change');
    assert.equal(h.submissions, 0, 'a subset of previously requested resorts stays local');
    h.location.search='?searched=1&resorts=%D0%A1%D0%B8%D0%B4%D0%B5%7C%D0%91%D0%B5%D0%BB%D0%B5%D0%BA';
    await h.fire('change');
    assert.equal(h.submissions, 1, 'adding a resort outside the fetched OR-set must start a real search');
    h.location.search='?searched=1';
    await h.fire('click');
    assert.equal(h.submissions, 2, 'removing all prior resort restrictions must start a broad search');
  }

  {
    const h = harness('?searched=1&hotel=4234');
    await h.fire('click');
    assert.equal(h.submissions, 0, 'unchanged exact hotel scope must not replay');
    h.location.search='?searched=1';
    await h.fire('click');
    assert.equal(h.submissions, 1, 'leaving an exact hotel search needs broader inventory');
  }

  {
    const editing = harness('?searched=1&max=600000', {hidden:false});
    editing.location.search='?searched=1';
    const click = editing.fire('click');
    editing.form.hidden=true;
    await click;
    assert.equal(editing.submissions, 0, 'explicit Find tours click must not queue a second refresh after the form collapses');
  }

  {
    const h = harness('?searched=1&max=600000', {disabled:true});
    h.location.search='?searched=1';
    await h.fire('click');
    assert.equal(h.submissions, 0, 'disabled canonical submit must stay fail-closed');
    h.disabled=false;
    await h.fire('change');
    assert.equal(h.submissions, 1, 'a previously blocked widening remains eligible for refresh once submit is enabled');
  }

  {
    const h = harness('?max=600000');
    h.location.search='?searched=1&max=600000';
    await h.submit();
    h.form.hidden=true;
    h.location.search='?searched=1';
    await h.fire('click');
    assert.equal(h.submissions, 1, 'an explicit first submit establishes the supplier-bound scope for later result filters');
  }

  console.log('search3 prototype inventory scope refresh v1: PASS');
})().catch(error => { console.error(error); process.exitCode = 1; });
