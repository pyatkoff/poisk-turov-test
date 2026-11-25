'use strict';

const assert = require('node:assert/strict');
const { createHash } = require('node:crypto');
const { setTimeout: wait } = require('node:timers/promises');

const resultsOnly = [
  '/prototype-search/hotel-popularity-v1.js',
  '/visual-search/flight-picker-v18.js',
  '/prototype-search/messenger-context-v1.js',
  '/prototype-search/messenger-flow-v1.js'
];
const appPath = '/visual-search/app.js';
const base = 'https://compiled-fixture.invalid/visual-search/';
const assetPath = url => new URL(url, base).pathname;
const hash = bytes => createHash('sha256').update(bytes).digest('hex');

function restoreBeforeGraph(html, initial, files) {
  const graph = initial.map(assetPath);
  const tags = [...html.matchAll(/<script\b[^>]*\bsrc="([^"]+)"[^>]*><\/script>/g)]
    .map(match => ({ path: assetPath(match[1]), text: match[0] }));
  const byPath = new Map(tags.map(tag => [tag.path, tag.text]));
  assert.deepEqual(tags.filter(tag => graph.includes(tag.path)).map(tag => tag.path),
    graph.filter(path => !resultsOnly.includes(path)), 'exact result-only owners are absent from the ordinary home document');
  let omitted = [];
  for (const path of graph) {
    if (resultsOnly.includes(path)) {
      const pin = files.get(path)?.sha256;
      assert(/^[a-f0-9]{64}$/.test(pin), 'results script has compiled bytes inside the existing graph');
      omitted.push('<script src="' + path + '?v=' + pin.slice(0, 12) + '" defer></script>');
    } else if (omitted.length) {
      const tag = byPath.get(path);
      assert.equal(html.split(tag).length, 2, 'one exact insertion anchor');
      html = html.replace(tag, () => omitted.join('\n  ') + '\n  ' + tag);
      omitted = [];
    }
  }
  assert.equal(omitted.length, 0);
  return html;
}

async function serveStartupResource(route, url, manifest, files, sample) {
  if (url.pathname === '/' && route.request().isNavigationRequest()) {
    const response = await route.fetch();
    const html = await response.text();
    await route.fulfill({ response, body: sample.variant === 'before' ? restoreBeforeGraph(html, manifest.graph.initial, files) : html });
    return true;
  }
  if (url.pathname === appPath) {
    const response = await route.fetch();
    const bytes = await response.body(), code = bytes.toString();
    assert.equal(hash(bytes), files.get(appPath).sha256);
    assert(/^(['"])use strict\1;/.test(code));
    const instrumented = code.replace(/^(['"])use strict\1;/, head => head + 'performance.mark("home-app-start");');
    await route.fulfill({ response, body: instrumented });
    return true;
  }
  if (sample.delayMs && resultsOnly.includes(url.pathname)) await wait(sample.delayMs);
  return false;
}

function observeHomeAssets() {
  if (location.pathname !== '/') return;
  const completed = [];
  document.addEventListener('load', ({ target }) => {
    if (target instanceof HTMLScriptElement && target.src) {
      completed.push({ path: new URL(target.src).pathname, at: performance.now() });
    }
  }, true);
  let frame = 0;
  function snapshot() {
    frame = 0;
    const button = document.querySelector('#search-form .search-submit');
    if (!button || button.disabled || button.form.getAttribute('aria-busy') !== 'false') return;
    const rect = button.getBoundingClientRect();
    const hit = document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2);
    if (!rect.width || !rect.height || rect.y < 0 || rect.bottom > innerHeight + 1 || !button.contains(hit)) return;
    const firstFrame = performance.now();
    const appStart = performance.getEntriesByName('home-app-start', 'mark')[0]?.startTime;
    if (!Number.isFinite(appStart) || appStart > firstFrame) return;
    window.__homeAssets = {
      firstFrame, appStart, completed: completed.slice(),
      resources: performance.getEntriesByType('resource').map(entry => ({
        path: new URL(entry.name).pathname, kind: entry.initiatorType,
        start: entry.startTime, end: entry.responseEnd, bytes: entry.decodedBodySize
      }))
    };
    observer.disconnect();
  }
  function schedule() {
    if (!frame) frame = requestAnimationFrame(snapshot);
  }
  const observer = new MutationObserver(schedule);
  observer.observe(document, { attributes: true, childList: true, subtree: true });
  schedule();
}

module.exports = { resultsOnly, restoreBeforeGraph, serveStartupResource, observeHomeAssets };
