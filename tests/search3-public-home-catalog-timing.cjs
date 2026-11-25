'use strict';

const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { createHash } = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const baseline = {
  revision: '458f748caf761408db2178e66ec875ceee34a3fa',
  sourceHash: '91fabf12b53747de35e9ffa7850928aa4ac2e1f2ed07949868272a128113d5d3',
  servedHash: '49eb63a39382ecf987edbac6ebb7a8482758113e61f739e3bde5685dcc2cbe5f'
};
const candidateSourceHash = 'cc53bf6e0d3d8fa3963848d97e54bbf2ddabaded7552188735178c44a8523354';
const dataURL = '/prototype-search/data.js';
const hash = value => createHash('sha256').update(value).digest('hex');
const delays = { departures: 20, countries: 30, tourvisor: 35, anex: 90, legacy: 120 };
const catalogueNames = Object.keys(delays).sort();

async function prepareCataloguePair(servedCandidateHash) {
  const repo = path.resolve(__dirname, '..');
  const asset = 'v2' + dataURL;
  const candidate = fs.readFileSync(path.join(repo, asset), 'utf8');
  // The comparison belongs to these two revisions. Subsequent revisions
  // continue through the standard HOME and receiver journey checks.
  if (hash(candidate) !== candidateSourceHash) return null;
  const former = execFileSync('git', ['show', baseline.revision + ':' + asset], { cwd: repo, encoding: 'utf8' });
  assert.equal(hash(former), baseline.sourceHash);
  const { compile } = require('../scripts/build/search3-js/visual-entry.cjs');
  const options = { privateFunctions: true, preserveFunctions: ['andromedaPoint', 'normalizeAndromedaQuote'] };
  return Promise.all([
    ['before', former, baseline.servedHash],
    ['after', candidate, servedCandidateHash]
  ].map(async ([name, source, servedHash]) => {
    const code = await compile(source, options);
    assert.equal(hash(code), servedHash, name + ' is the exact compiled asset');
    return { name, sourceHash: hash(source), servedHash, code };
  }));
}

async function servePinnedAsset(route, variant, candidateHash) {
  const response = await route.fetch();
  assert.equal(response.status(), 200);
  assert.equal(hash(await response.body()), candidateHash, 'the fixture serves the candidate compiler output');
  await route.fulfill({ status: 200, contentType: 'application/javascript; charset=utf-8', body: variant.code });
}

function catalogueRequest(pathname, action, provider) {
  if (pathname === '/data/departures-v1.php') return 'departures';
  if (pathname.endsWith('/search3-destination-read-v1.php') && action === 'countries') return 'countries';
  if (pathname.endsWith('/search3-local-results-read-v1.php') && action === 'meal_catalog') return provider;
  if (pathname === '/api-v2.php' && action === 'meals') return 'legacy';
  return null;
}

function observeReadyHome() {
  if (location.pathname !== '/') return;
  let frame = 0;
  const observer = new MutationObserver(schedule);
  function read() {
    frame = 0;
    const button = document.querySelector('.home-hero #search-form .search-submit');
    if (!button || button.disabled || button.form.getAttribute('aria-busy') !== 'false') return;
    const { x, y, width, height, right, bottom } = button.getBoundingClientRect();
    const hit = document.elementFromPoint(x + width / 2, y + height / 2);
    if (!width || !height || x < 0 || y < 0 || right > innerWidth + 1 || bottom > innerHeight + 1 || !button.contains(hit)) return;
    window.__homeCatalogueReady = {
      ready: performance.now(),
      navigationResponse: performance.getEntriesByType('navigation')[0]?.responseEnd ?? null,
      resources: performance.getEntriesByType('resource').filter(entry => ['fetch', 'xmlhttprequest'].includes(entry.initiatorType))
        .map(entry => ({ path: new URL(entry.name).pathname, start: entry.startTime, end: entry.responseEnd }))
    };
    observer.disconnect();
  }
  function schedule() {
    if (!frame) frame = requestAnimationFrame(read);
  }
  observer.observe(document, { subtree: true, childList: true, attributes: true });
  schedule();
}

module.exports = { baseline, prepareCataloguePair, dataURL, servePinnedAsset, catalogueRequest, catalogueNames, observeReadyHome, delays };
