'use strict';

const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { createHash } = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const baseline = {
  revision: '458f748caf761408db2178e66ec875ceee34a3fa',
  sourceHash: '462027457cb7ab7aab5f378ceca9565dd7cb989819599c2079790d3cd6a9f9b4',
  servedHash: 'ecc94153309c74433864d549c53d6abe3e50fdb3b54623130dde86058c13b8e2'
};
const candidateSourceHash = '140281b958ddc8b91f22e57ce5e0db402ea8772950bfb4cffdabc51709667ac9';
const appURL = '/visual-search/app.js';
const hash = value => createHash('sha256').update(value).digest('hex');

function instrument(source) {
  const marks = [
    ["'use strict';\n(() => {\n", "app-start"],
    ["messengerReturn=readMessengerReturn(initialUIRoute);", "form-mount"],
    ["restoreURL(true);", "form-mounted"],
  ];
  for (const [anchor, name] of marks) {
    assert.equal(source.split(anchor).length, 2, name + ' boundary is unique');
    source = source.replace(anchor, () => anchor + '\nperformance.mark("search3-' + name + '");');
  }
  const boot = 'bootRealData();\nif(initialUIRoute';
  assert.equal(source.split(boot).length, 2, 'initial boot call is unique');
  return source.replace(boot, () => 'performance.mark("search3-before-catalogue");' + boot);
}

async function prepareHomeStartupPair(servedCandidateHash) {
  const repo = path.resolve(__dirname, '..');
  const asset = 'v2' + appURL;
  const candidate = fs.readFileSync(path.join(repo, asset), 'utf8');
  // This comparison is specific to the two exact owners. Later revisions
  // continue through the ordinary browser checks.
  if (hash(candidate) !== candidateSourceHash) return null;
  const former = execFileSync('git', ['show', baseline.revision + ':' + asset], { cwd: repo, encoding: 'utf8', maxBuffer: 2 ** 20 });
  assert.equal(hash(former), baseline.sourceHash);
  const { compile } = require('../scripts/build/search3-js/visual-entry.cjs');
  const variants = [];
  for (const [name, source, pin] of [['before', former, baseline.servedHash], ['after', candidate, servedCandidateHash]]) {
    const options = { privateFunctions: true };
    const plain = await compile(source, options);
    assert.equal(hash(plain), pin, name + ' matches the compiled acceptance asset before measurement marks');
    variants.push({ name, sourceHash: hash(source), servedHash: pin, app: await compile(instrument(source), options) });
  }
  return variants;
}

function observeHomeStartup() {
  if (location.pathname !== '/') return;
  const paints = [];
  let panelOwner;
  Object.defineProperty(window, 'AnyTourFilterPanelV1', {
    configurable: true,
    get: () => panelOwner,
    set(owner) {
      panelOwner = Object.freeze({
        ...owner,
        create(options) {
          const panel = owner.create(options);
          return Object.freeze({
            ...panel,
            renderFilters(...args) {
              const start = performance.now();
              try { return panel.renderFilters(...args); }
              finally { paints.push({ start, end: performance.now() }); }
            }
          });
        }
      });
    }
  });
  let frame = 0;
  const observer = new MutationObserver(schedule);
  function read() {
    frame = 0;
    const button = document.querySelector('.home-hero #search-form .search-submit');
    if (!button || button.disabled || button.form.getAttribute('aria-busy') !== 'false') return;
    const { x, y, width, height, right, bottom } = button.getBoundingClientRect();
    const hit = document.elementFromPoint(x + width / 2, y + height / 2);
    if (!height || !width || x < 0 || y < 0 || right > innerWidth + 1 || bottom > innerHeight + 1 || !button.contains(hit)) return;
    const ready = performance.now();
    const marks = Object.fromEntries(performance.getEntriesByType('mark')
      .filter(entry => entry.name.startsWith('search3-')).map(entry => [entry.name.slice(8), entry.startTime]));
    const resources = performance.getEntriesByType('resource');
    const requests = resources.filter(entry => ['fetch', 'xmlhttprequest'].includes(entry.initiatorType))
      .map(entry => ({ path: new URL(entry.name).pathname, start: entry.startTime, end: entry.responseEnd }));
    const app = resources.find(entry => new URL(entry.name).pathname === '/visual-search/app.js');
    window.__homeStartup = {
      ready, marks, requests, app: app ? { start: app.startTime, end: app.responseEnd } : null,
      navigationResponse: performance.getEntriesByType('navigation')[0]?.responseEnd ?? null, paints: paints.slice()
    };
    observer.disconnect();
  }
  function schedule() {
    if (!frame) frame = requestAnimationFrame(read);
  }
  observer.observe(document, { subtree: true, childList: true, attributes: true });
  schedule();
}

module.exports = { appURL, baseline, prepareHomeStartupPair, observeHomeStartup };
