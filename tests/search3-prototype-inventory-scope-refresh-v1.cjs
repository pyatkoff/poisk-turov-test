'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(process.argv[2] || 'v2/prototype-search/inventory-scope-refresh-v1.js', 'utf8');
const entry = fs.readFileSync(process.argv[3] || 'v2/prototype-search/index.php', 'utf8');
assert.match(entry, /inventory-scope-refresh-v1\.js/, 'preview PHP entrypoint must load the inventory refresh module');
assert.ok(entry.indexOf('inventory-scope-refresh-v1.js') < entry.indexOf('preg_replace_callback'), 'injected module must receive the normal cache-busting pass');
assert.match(source, /data\.supplierScope\(filters\)/, 'refresh must derive next scope through the canonical data owner');
assert.match(source, /data\.supplierScopeCovered\(previous, next\)/, 'refresh must delegate coverage policy to the canonical data owner');
assert.doesNotMatch(source, /const covered\s*=/, 'refresh must not keep a competing coverage policy');

const flush = async () => { await Promise.resolve(); await Promise.resolve(); await Promise.resolve(); };
function harness(search, {hidden=true, disabled=false, previous={kind:'previous'}, covered=true}={}) {
  const documentListeners = {click:[], change:[]};
  let submissions = 0, submitDisabled = disabled;
  const calls = {scopes:[], covered:[]};
  const data = {
    currentSupplierScope: previous,
    supplierScope(filters) { calls.scopes.push(structuredClone(filters)); return Object.freeze({kind:'next', filters:structuredClone(filters)}); },
    supplierScopeCovered(before, next) { calls.covered.push({before,next}); return typeof covered === 'function' ? covered(before,next) : covered; }
  };
  const form = {hidden, requestSubmit() { submissions++; }};
  const document = {
    getElementById(id) { return id === 'search-form' ? form : null; },
    querySelector(selector) { return selector === '.search-submit' ? {disabled:submitDisabled} : null; },
    addEventListener(type, listener) { if (documentListeners[type]) documentListeners[type].push(listener); }
  };
  const location = {search};
  const context = {window:{AnyTourPrototypeData:data}, document, location, URLSearchParams, queueMicrotask, console, structuredClone};
  vm.runInNewContext(source, context, {filename:'inventory-scope-refresh-v1.js'});
  assert.equal(context.window.AnyTourPrototypeInventoryScopeRefreshV1, true);
  assert.equal(documentListeners.click.length, 1);
  assert.equal(documentListeners.change.length, 1);
  const fire = async type => { for (const listener of documentListeners[type]) listener({}); await flush(); };
  return {
    form, location, data, calls, fire,
    set disabled(value) { submitDisabled = value; },
    get submissions() { return submissions; }
  };
}

(async () => {
  {
    const h = harness('?searched=1&hotel=4234&resorts=%D0%A1%D0%B8%D0%B4%D0%B5%7C%D0%9A%D0%B5%D0%BC%D0%B5%D1%80&stars=4%7C5&meals=AI%7CHB&min=100000&max=700000');
    await h.fire('change');
    assert.equal(h.submissions, 0, 'canonical owner may keep a covered result filter local');
    assert.deepEqual(h.calls.scopes[0], {
      hotelId:4234,
      resorts:['Кемер','Сиде'],
      stars:[4,5],
      meals:['AI','HB'],
      min:100000,
      max:700000
    }, 'URL parsing must only construct intent; supplier policy stays in data.js');
    assert.equal(h.calls.covered.length, 1);
  }

  {
    const h = harness('?searched=1&max=700000', {covered:false});
    await h.fire('click');
    assert.equal(h.submissions, 1, 'canonical owner may require a real search when prior inventory does not cover next intent');
  }

  {
    const h = harness('?searched=1&max=700000', {previous:null,covered:false});
    await h.fire('click');
    assert.equal(h.submissions, 0, 'without an actual data.search scope the patch must not invent prior supplier state');
    assert.equal(h.calls.scopes.length, 1);
    assert.equal(h.calls.covered.length, 0);
  }

  {
    const h = harness('?searched=1&max=700000', {hidden:false,covered:false});
    const pending = h.fire('click');
    h.form.hidden = true;
    await pending;
    assert.equal(h.submissions, 0, 'explicit form editing must not queue a duplicate search after the form later collapses');
  }

  {
    const h = harness('?searched=1&max=700000', {disabled:true,covered:false});
    await h.fire('change');
    assert.equal(h.submissions, 0, 'disabled canonical submit stays fail-closed');
    h.disabled=false;
    await h.fire('change');
    assert.equal(h.submissions, 1, 'the same widening can refresh once canonical submit becomes available');
  }

  {
    const h = harness('?max=700000', {covered:false});
    await h.fire('click');
    assert.equal(h.submissions, 0, 'unsubmitted URL intent must never create a supplier refresh');
  }

  console.log('search3 prototype inventory scope refresh v1: PASS');
})().catch(error => { console.error(error); process.exitCode = 1; });
