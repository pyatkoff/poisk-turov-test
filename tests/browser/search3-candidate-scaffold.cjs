'use strict';

const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');

let chromium;
try {
  ({ chromium } = require('playwright'));
} catch (error) {
  const modules = process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES;
  if (!modules) throw error;
  ({ chromium } = require(path.join(modules, 'playwright')));
}

const fixturePath = path.resolve(__dirname, '../fixtures/search3-candidate-scaffold.json');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const baseUrl = String(process.env.SEARCH3_BASE_URL || 'http://anytoour.ru:18083').replace(/\/$/, '');
const serverUrl = String(process.env.SEARCH3_SERVER_URL || 'http://127.0.0.1:18083').replace(/\/$/, '');
const outputDir = path.resolve(process.env.SEARCH3_ARTIFACT_DIR || 'search3-candidate-artifacts');
const expectedStates = ['initial', 'progressive-25', 'final-100'];
const visualTierName = String(process.env.SEARCH3_VISUAL_TIER || 'candidate');
assert.ok(['pr', 'candidate'].includes(visualTierName), `unsupported visual tier ${visualTierName}`);
const visualTier = fixture.visualTiers && fixture.visualTiers[visualTierName];
assert.ok(visualTier, `missing visual tier configuration for ${visualTierName}`);
const expectedPresentationCaptures = visualTier.presentationCaptures;
const expectedEvidenceScreenshotCount = visualTier.lifecycleWidths.length * expectedStates.length
  + visualTier.finalOnlyWidths.length;
const sourceSha = String(process.env.SEARCH3_SOURCE_SHA || '');
const testedSha = String(process.env.SEARCH3_TESTED_SHA || '');
const workflowRunId = String(process.env.SEARCH3_RUN_ID || '');
const workflowRunAttempt = String(process.env.SEARCH3_RUN_ATTEMPT || '');
const contextProfile = Object.freeze({
  locale: 'ru-RU',
  timezoneId: 'Europe/Kaliningrad',
  colorScheme: 'light',
  reducedMotion: 'reduce',
  deviceScaleFactor: 1,
});

assert.equal(fixture.schemaVersion, 2);
assert.deepEqual(fixture.viewports.map(item => item.width), [375, 430, 768, 1024, 1440]);
assert.deepEqual(Object.keys(fixture.visualTiers), ['pr', 'candidate']);
for (const [name, tier] of Object.entries(fixture.visualTiers)) {
  const widths = [...tier.lifecycleWidths, ...tier.finalOnlyWidths];
  assert.equal(new Set(widths).size, widths.length, `${name}: duplicate visual width`);
  assert.deepEqual(widths.slice().sort((a, b) => a - b), fixture.viewports.map(item => item.width));
  assert.deepEqual(tier.presentationCaptures, fixture.presentation.captures);
  assert.equal(tier.runRaces, true);
  assert.equal(tier.runFailureStates, true);
}
assert.equal(fixture.progressive.firstLimit, 25);
assert.equal(fixture.progressive.finalLimit, 100);
assert.equal(fixture.leadApi, '/_preview/search3-candidate/poisk-turov/?lead=disabled');
assert.deepEqual(fixture.failureFixtures.states, [
  'search-empty',
  'search-timeout',
  'search-upstream-error',
  'flight-empty',
  'flight-timeout',
  'flight-upstream-error',
  'lead-ui-no-delivery',
]);
assert.equal(fixture.failureFixtures.flights.upstreamAttempts, 2);
assert.equal(new URL(baseUrl).hostname, 'anytoour.ru', 'browser host simulation must stay on anytoour.ru');
assert.equal(new URL(serverUrl).origin, 'http://127.0.0.1:18083', 'lead guard probe must stay on loopback');
assert.deepEqual(fixture.visualBaseline, {
  status: 'REFERENCE_PIXELS_AVAILABLE_COMPARISON_PENDING',
  referenceCount: 8,
  referenceManifestSha256: 'cbecdac4080b7a7a541a3b9b5de4a4f8448203717728f7ca7afa4ea6373f45b8',
  baselineCompared: false,
  ownerVisualApproval: false,
});
assert.deepEqual(fixture.presentation, {
  status: 'REFERENCE_IMPLEMENTATION_IN_PROGRESS',
  approvedPixelsCompared: false,
  donorCommit: 'e5baf32f455cdb0aa1a704964f28e5efbebf57ff',
  donorRunId: '33813829683',
  productionOwnedDirectTourText: 'Проверить тур',
  assets: ['search3-results-filters-v1.css', 'search3-results-filters-v1.js'],
  captures: expectedPresentationCaptures,
});
assert.match(sourceSha, /^[0-9a-f]{40}$/, 'SEARCH3_SOURCE_SHA must identify the exact candidate head');
assert.match(testedSha, /^[0-9a-f]{40}$/, 'SEARCH3_TESTED_SHA must identify the exact tested checkout');
assert.match(workflowRunId, /^[1-9][0-9]*$/, 'SEARCH3_RUN_ID must be a GitHub Actions run ID');
assert.match(workflowRunAttempt, /^[1-9][0-9]*$/, 'SEARCH3_RUN_ATTEMPT must be a GitHub Actions attempt');

fs.mkdirSync(outputDir, { recursive: true });

function hotels(count) {
  const base = fixture.hotelTemplate;
  return Array.from({ length: count }, (_, offset) => {
    const number = offset + 1;
    const price = base.basePrice + number * 1000;
    return {
      id: `fixture-hotel-${number}`,
      name: `${base.namePrefix} ${String(number).padStart(3, '0')}`,
      country: base.country,
      region: base.region,
      subRegion: base.subRegion,
      category: base.category,
      rating: base.rating,
      seaDistance: base.seaDistance,
      price,
      tours: [{
        ...base.tour,
        id: `fixture-tour-${number}`,
        price,
      }],
    };
  });
}

const resultSets = {
  25: hotels(25),
  100: hotels(100),
};

function responseJson(route, body, status = 200) {
  return route.fulfill({
    status,
    contentType: 'application/json; charset=utf-8',
    body: JSON.stringify(body),
  });
}

function scenarioController(name) {
  const calls = [];
  let statusCalls = 0;
  let heldStatus = null;
  let heldResults = null;
  let heldFinalStatus = null;

  return {
    calls,
    get heldStatus() { return heldStatus; },
    get heldResults() { return heldResults; },
    get heldFinalStatus() { return heldFinalStatus; },
    async handle(route) {
      const request = route.request();
      const url = new URL(request.url());
      const action = url.searchParams.get('action') || '';
      const limit = Number(url.searchParams.get('limit') || 0);
      calls.push({ action, limit, method: request.method() });

      if (Object.prototype.hasOwnProperty.call(fixture.catalogs, action)) {
        return responseJson(route, fixture.catalogs[action]);
      }
      if (action === 'search_start') {
        if (name === 'search-upstream-error') {
          return responseJson(route, { error: fixture.failureFixtures.search.upstreamError }, fixture.failureFixtures.search.upstreamStatus);
        }
        if (name === 'error-start') return responseJson(route, {});
        const id = name === 'pending-status'
          ? fixture.races.pendingStatusSearchId
          : name === 'pending-results'
            ? fixture.races.pendingResultsSearchId
            : name === 'empty'
              ? fixture.emptySearchId
              : name === 'search-timeout'
                ? fixture.failureFixtures.search.timeoutSearchId
                : fixture.progressive.searchId;
        return responseJson(route, { searchId: id });
      }
      if (action === 'search_status') {
        statusCalls += 1;
        if (name === 'pending-status') {
          return new Promise(resolve => {
            heldStatus = () => responseJson(route, { progress: 55, minPrice: 99000 }).then(resolve);
          });
        }
        if (name === 'empty') return responseJson(route, { progress: 100, status: 'complete' });
        if (name === 'pending-results') return responseJson(route, { progress: 20, minPrice: 99000 });
        if (statusCalls === 1) {
          return responseJson(route, { progress: fixture.progressive.firstProgress, minPrice: 101000 });
        }
        if (name === 'progressive') {
          return new Promise(resolve => {
            heldFinalStatus = () => responseJson(route, { progress: fixture.progressive.finalProgress, status: 'complete', minPrice: 101000 }).then(resolve);
          });
        }
        return responseJson(route, { progress: fixture.progressive.finalProgress, status: 'complete', minPrice: 101000 });
      }
      if (action === 'search_results') {
        if (name === 'pending-results') {
          return new Promise(resolve => {
            heldResults = () => responseJson(route, resultSets[25]).then(resolve);
          });
        }
        if (name === 'empty') return responseJson(route, []);
        return responseJson(route, limit >= 100 ? resultSets[100] : resultSets[25]);
      }
      if (action === 'tour') {
        return responseJson(route, fixture.failureFixtures.selectedTour);
      }
      if (action === 'flights') {
        if (name === 'flight-upstream-error') {
          return responseJson(route, { error: fixture.failureFixtures.flights.upstreamError }, fixture.failureFixtures.flights.upstreamStatus);
        }
        if (name === 'flight-empty') return responseJson(route, []);
      }
      throw new Error(`${name}: unexpected API action ${action}`);
    },
  };
}

async function waitFor(predicate, message, timeoutMs = 12000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    const value = predicate();
    if (value) return value;
    await new Promise(resolve => setTimeout(resolve, 20));
  }
  throw new Error(message);
}

async function injectDeterministicApiFailure(page, action, failure) {
  await page.evaluate(({ targetAction, injectedFailure }) => {
    const runtime = window.V2Runtime;
    if (!runtime || typeof runtime.api !== 'function') throw new Error('V2Runtime API unavailable');
    const original = runtime.api.bind(runtime);
    window.__search3InjectedFailureCalls = window.__search3InjectedFailureCalls || {};
    runtime.api = async (requestedAction, params, options) => {
      if (requestedAction !== targetAction) return original(requestedAction, params, options);
      window.__search3InjectedFailureCalls[targetAction] = Number(window.__search3InjectedFailureCalls[targetAction] || 0) + 1;
      const error = new Error(injectedFailure.message);
      error.code = injectedFailure.code;
      if (injectedFailure.status) error.status = injectedFailure.status;
      throw error;
    };
  }, { targetAction: action, injectedFailure: failure });
}

async function injectedFailureCalls(page, action) {
  return page.evaluate(targetAction => Number(window.__search3InjectedFailureCalls?.[targetAction] || 0), action);
}

function recordBehavior(manifest, name, details) {
  assert.ok(fixture.failureFixtures.states.includes(name), `undeclared behavior state ${name}`);
  assert.ok(!manifest.behaviorStates.some(item => item.name === name), `duplicate behavior state ${name}`);
  manifest.behaviorStates.push({ name, passed: true, ...details });
}

function presentationAssetEvidence() {
  const root = path.resolve(__dirname, '../../v2/_preview/search3-candidate/poisk-turov');
  return fixture.presentation.assets.map(file => {
    const bytes = fs.readFileSync(path.join(root, file));
    return {
      file,
      bytes: bytes.length,
      sha256: crypto.createHash('sha256').update(bytes).digest('hex'),
    };
  });
}

function assertCandidateDoesNotOwnDirectTour() {
  const script = fs.readFileSync(
    path.resolve(__dirname, '../../v2/_preview/search3-candidate/poisk-turov/search3-results-filters-v1.js'),
    'utf8',
  );
  assert.doesNotMatch(script, /\.direct-tour|Проверить тур|Выбрать тур/, 'candidate presentation must not own the production tour CTA');
}

function consumeExpectedApiHttpFailures(harness, action, status, count, label) {
  const marker = `status of ${status} (`;
  assert.equal(harness.httpErrorResponses.length, count, `${label}: unexpected HTTP error response count`);
  for (const response of harness.httpErrorResponses) {
    assert.deepEqual(response, {
      origin: new URL(baseUrl).origin,
      pathname: '/api-v2.php',
      action,
      status,
      method: 'GET',
    }, `${label}: HTTP error did not come from the intended candidate API action`);
  }
  assert.equal(harness.consoleErrors.length, count, `${label}: unexpected console error count`);
  for (const message of harness.consoleErrors) {
    assert.ok(message.startsWith('Failed to load resource:'), `${label}: unexpected console error`);
    assert.ok(message.includes(marker), `${label}: expected HTTP ${status} console evidence`);
  }
  harness.httpErrorResponses.length = 0;
  harness.consoleErrors.length = 0;
  return { responses: count, consoleErrors: count };
}

async function assertPreviewLeadGuard() {
  const payload = JSON.stringify({ synthetic: true });
  const target = new URL(fixture.leadApi, serverUrl);
  assert.equal(target.origin, new URL(serverUrl).origin, 'lead guard target escaped loopback');
  const response = await new Promise((resolve, reject) => {
    const request = http.request(target, {
      method: 'POST',
      headers: {
        Host: new URL(baseUrl).host,
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload),
      },
    }, incoming => {
      const chunks = [];
      incoming.on('data', chunk => chunks.push(chunk));
      incoming.on('end', () => resolve({
        status: incoming.statusCode,
        body: JSON.parse(Buffer.concat(chunks).toString('utf8')),
      }));
    });
    request.on('error', reject);
    request.setTimeout(5000, () => request.destroy(new Error('preview lead guard probe timed out')));
    request.end(payload);
  });
  assert.deepEqual(response, {
    status: 403,
    body: { ok: false, error: 'PREVIEW_LEAD_DISABLED' },
  });
}

async function createHarness(browser, viewport, scenario) {
  const context = await browser.newContext({
    viewport,
    ignoreHTTPSErrors: true,
    serviceWorkers: 'block',
    ...contextProfile,
  });
  const unexpectedOutbound = [];
  const candidateOrigin = new URL(baseUrl).origin;
  await context.route('**/*', route => {
    const requestUrl = new URL(route.request().url());
    if (requestUrl.origin === candidateOrigin) return route.continue();
    unexpectedOutbound.push(`${route.request().method()} ${requestUrl.href}`);
    return route.abort('blockedbyclient');
  });
  await context.route('https://app.anytoour.ru/web-consultant/widget.js', route => route.fulfill({
    status: 200,
    contentType: 'application/javascript; charset=utf-8',
    body: '/* deterministic Search3 candidate harness */',
  }));

  const page = await context.newPage();
  const consoleErrors = [];
  const pageErrors = [];
  const unexpectedFailures = [];
  const productionLeadRequests = [];
  const candidateLeadRequests = [];
  const httpErrorResponses = [];
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', error => pageErrors.push(String(error)));
  page.on('request', request => {
    const requestUrl = new URL(request.url());
    if (requestUrl.pathname.endsWith('/lead-adapter-v2.php')) productionLeadRequests.push(request.url());
    if (request.method() === 'POST' && requestUrl.pathname === fixture.route && requestUrl.searchParams.get('lead') === 'disabled') {
      candidateLeadRequests.push(request.url());
    }
  });
  page.on('requestfailed', request => {
    if (!/^https:\/\/mc\.yandex\.(?:ru|com)\//.test(request.url())) {
      unexpectedFailures.push(`${request.method()} ${request.url()} ${request.failure() && request.failure().errorText}`);
    }
  });
  page.on('response', response => {
    const status = response.status();
    if (status < 400) return;
    const responseUrl = new URL(response.url());
    httpErrorResponses.push({
      origin: responseUrl.origin,
      pathname: responseUrl.pathname,
      action: responseUrl.searchParams.get('action') || '',
      status,
      method: response.request().method(),
    });
  });

  await page.route('**/images/logo.svg', route => route.fulfill({
    status: 200,
    contentType: 'image/svg+xml',
    body: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 44" aria-hidden="true"></svg>',
  }));
  await page.route('**/favicon.php*', route => route.fulfill({ status: 204, body: '' }));
  await page.route('**/data/departures-v1.php*', route => responseJson(route, {
    ok: true,
    items: fixture.catalogs.departures,
  }));
  await page.route('**/data/destinations-v1.php*', route => responseJson(route, { ok: true, items: [] }));
  await page.route('**/data/hotels-select-v1.php*', route => responseJson(route, { ok: true, items: [] }));
  await page.route('**/data/observe-search-v1.php*', route => responseJson(route, { ok: true }));
  await page.route(/\/api-v2\.php(?:\?|$)/, route => scenario.handle(route));

  const target = baseUrl + fixture.route;
  const response = await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 30000 });
  assert.ok(response, 'candidate route did not return a response');
  assert.equal(response.status(), 200);
  assert.match(String((await response.allHeaders())['x-robots-tag'] || ''), /^noindex,\s*follow/i);
  await page.waitForFunction(() => window.V2SearchLifecycle && document.getElementById('tourSearch')?.dataset.catalogSource, null, { timeout: 15000 });
  await page.evaluate(() => document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve());

  const identity = await page.evaluate(() => ({
    h1Count: document.querySelectorAll('h1').length,
    visibleH1Count: [...document.querySelectorAll('h1')].filter(node => {
      const box = node.getBoundingClientRect();
      const css = getComputedStyle(node);
      return box.width > 0 && box.height > 0 && css.display !== 'none' && css.visibility !== 'hidden';
    }).length,
    robots: document.querySelector('meta[name="robots"]')?.content || '',
    canonical: document.querySelector('link[rel="canonical"]')?.href || '',
    api: window.V2_CONFIG?.api || '',
    leadApi: window.V2_CONFIG?.leadApi || '',
    metrikaCounter: Number(window.V2_CONFIG?.metrikaCounter || 0),
    css: document.getElementById('v2-primary-search-ux-style')?.getAttribute('href') || '',
    script: [...document.scripts].map(node => node.getAttribute('src') || '').find(src => src.includes('bundle-v1.php')) || '',
    candidateClass: document.body.classList.contains('search3-candidate'),
    tabletFilterBootstrapCount: document.querySelectorAll('#search3-tablet-filter-bootstrap').length,
    tabletFilterRestoreCount: document.querySelectorAll('#search3-tablet-filter-restore').length,
    matchMediaOverrideRestored: typeof window.__search3CandidateNativeMatchMedia === 'undefined',
    matchMediaIdentityRestored: document.documentElement.dataset.search3MatchMediaRestored || '',
    candidateCss: document.getElementById('search3-results-filters-v1-style')?.getAttribute('href') || '',
    candidateScript: document.getElementById('search3-results-filters-v1-script')?.getAttribute('src') || '',
    presentationStatus: window.Search3CandidateResultsV1?.status || '',
    approvedPixelsCompared: window.Search3CandidateResultsV1?.approvedPixelsCompared,
    selectedHandoffVersion: window.Search3CandidateSelectedHandoffV1?.version || 0,
    selectedPresentationVersion: window.Search3CandidateSelectedPresentationV1?.version || 0,
    unknownPresentationValues: [
      window.Search3CandidateResultsV1?.mealLabel('Fixture meal') || '',
      window.Search3CandidateResultsV1?.roomLabel('Fixture room') || '',
      window.Search3CandidateResultsV1?.placementLabel('Fixture placement') || '',
    ],
    partyLabels: [0, 1, 2, 5].map(children => window.Search3CandidateResultsV1?.partyLabel(2, children) || ''),
  }));
  assert.equal(identity.h1Count, 1);
  assert.equal(identity.visibleH1Count, 1);
  assert.equal(identity.robots, fixture.robots);
  assert.equal(identity.canonical, fixture.canonical);
  assert.equal(identity.api, '/api-v2.php');
  assert.equal(identity.leadApi, fixture.leadApi);
  assert.notEqual(identity.leadApi, '/lead-adapter-v2.php');
  assert.equal(identity.metrikaCounter, 0, 'candidate preview must not send production Metrika events');
  assert.match(identity.css, /^\/bundle-v1\.php\?type=css&/);
  assert.match(identity.script, /^\/bundle-v1\.php\?type=js&/);
  assert.equal(identity.candidateClass, true);
  assert.equal(identity.tabletFilterBootstrapCount, 1, 'candidate tablet filter bootstrap must be injected exactly once');
  assert.equal(identity.tabletFilterRestoreCount, 1, 'candidate tablet filter restore must be injected exactly once');
  assert.equal(identity.matchMediaOverrideRestored, true, 'candidate matchMedia bootstrap must not outlive bundle startup');
  assert.equal(identity.matchMediaIdentityRestored, '1', 'candidate must restore the exact original matchMedia function');
  assert.match(identity.candidateCss, /^\/_preview\/search3-candidate\/poisk-turov\/search3-results-filters-v1\.css\?v=[0-9a-f]{16}$/);
  assert.match(identity.candidateScript, /^\/_preview\/search3-candidate\/poisk-turov\/search3-results-filters-v1\.js\?v=[0-9a-f]{16}$/);
  const diskAssets = presentationAssetEvidence();
  assert.equal(new URL(identity.candidateCss, baseUrl).searchParams.get('v'), diskAssets.find(item => item.file.endsWith('.css')).sha256.slice(0, 16));
  assert.equal(new URL(identity.candidateScript, baseUrl).searchParams.get('v'), diskAssets.find(item => item.file.endsWith('.js')).sha256.slice(0, 16));
  assert.equal(identity.presentationStatus, fixture.presentation.status);
  assert.equal(identity.approvedPixelsCompared, false);
  assert.equal(identity.selectedHandoffVersion, 1);
  assert.equal(identity.selectedPresentationVersion, 1);
  assert.deepEqual(identity.unknownPresentationValues, ['Fixture meal', 'Fixture room', 'Fixture placement']);
  assert.deepEqual(identity.partyLabels, [
    '2 взрослых',
    '2 взрослых и 1 ребёнок',
    '2 взрослых и 2 ребёнка',
    '2 взрослых и 5 детей',
  ]);
  assert.equal(new URL(page.url()).pathname, fixture.route);

  return {
    context,
    page,
    consoleErrors,
    pageErrors,
    unexpectedFailures,
    unexpectedOutbound,
    productionLeadRequests,
    candidateLeadRequests,
    httpErrorResponses,
  };
}

async function setSearchValues(page) {
  await page.evaluate(values => {
    const form = document.getElementById('tourSearch');
    for (const [name, value] of Object.entries(values)) {
      const field = form.elements[name];
      if (field) field.value = value;
    }
    form.elements.child_count.value = '0';
  }, fixture.search);
}

async function geometry(page, width, state) {
  return page.evaluate(({ expectedWidth, expectedState }) => {
    const rect = node => {
      if (!node) return null;
      const box = node.getBoundingClientRect();
      return {
        x: Math.round(box.x * 100) / 100,
        y: Math.round(box.y * 100) / 100,
        width: Math.round(box.width * 100) / 100,
        height: Math.round(box.height * 100) / 100,
      };
    };
    const rendered = node => {
      if (!node || node.hidden) return false;
      const box = node.getBoundingClientRect();
      const css = getComputedStyle(node);
      return box.width > 0 && box.height > 0 && css.display !== 'none' && css.visibility !== 'hidden';
    };
    const clippedRect = node => {
      if (!rendered(node)) return null;
      const box = node.getBoundingClientRect();
      const left = Math.max(0, box.left);
      const top = Math.max(0, box.top);
      const right = Math.min(window.innerWidth, box.right);
      const bottom = Math.min(window.innerHeight, box.bottom);
      if (right <= left || bottom <= top) return null;
      return { left, top, right, bottom, width: right - left, height: bottom - top };
    };
    const intersection = (left, right) => {
      if (!left || !right) return { width: 0, height: 0, area: 0 };
      const width = Math.max(0, Math.min(left.right, right.right) - Math.max(left.left, right.left));
      const height = Math.max(0, Math.min(left.bottom, right.bottom) - Math.max(left.top, right.top));
      return {
        width: Math.round(width * 100) / 100,
        height: Math.round(height * 100) / 100,
        area: Math.round(width * height * 100) / 100,
      };
    };
    const cards = document.querySelectorAll('#results .hotel-card');
    const first = cards[0] || null;
    const firstPhoto = first?.querySelector('.hotel-photo') || null;
    const firstBody = first?.querySelector('.hotel-body') || null;
    const disclosure = first?.querySelector('.search3-show-tours') || null;
    const directTour = first?.querySelector('.direct-tour') || null;
    const title = first?.querySelector('.hotel-title') || null;
    const place = first?.querySelector('.hotel-place') || null;
    const factLabel = first?.querySelector('.search3-hotel-facts small') || null;
    const category = first?.querySelector('.search3-hotel-category') || null;
    const price = first?.querySelector('.hotel-price') || null;
    const priceContext = first?.querySelector('.hotel-price-context') || null;
    const factEntries = [...(first?.querySelectorAll('.search3-hotel-facts>span') || [])].map(node => [
      (node.querySelector('small')?.textContent || '').trim(),
      (node.querySelector('b')?.textContent || '').trim(),
    ]);
    const layout = document.querySelector('.results-layout');
    const rail = document.querySelector('.results-filter-rail');
    const filterReset = rail?.querySelector('.filter-reset-link') || null;
    const filterOption = rail?.querySelector('.filter-option') || null;
    const h1 = document.querySelector('h1');
    const status = document.getElementById('status');
    const mobileToolbar = document.querySelector('.search3-mobile-toolbar');
    const mobileFilter = mobileToolbar?.querySelector('.mrf-open') || null;
    const documentWidth = document.documentElement.scrollWidth;
    const bodyWidth = document.body.scrollWidth;
    const clientWidth = document.documentElement.clientWidth;
    const clippedStatus = clippedRect(status);
    const clippedToolbar = clippedRect(mobileToolbar);
    const toolbarCenter = clippedToolbar
      ? { x: clippedToolbar.left + clippedToolbar.width / 2, y: clippedToolbar.top + clippedToolbar.height / 2 }
      : null;
    const toolbarCenterNode = toolbarCenter ? document.elementFromPoint(toolbarCenter.x, toolbarCenter.y) : null;
    const visibleFilterAffordances = [rail, mobileFilter].filter(rendered);
    return {
      state: expectedState,
      viewportWidth: expectedWidth,
      clientWidth,
      documentWidth,
      bodyWidth,
      horizontalOverflow: documentWidth > clientWidth + 2 || bodyWidth > clientWidth + 2,
      h1Count: document.querySelectorAll('h1').length,
      resultCount: cards.length,
      statusText: (status?.textContent || '').replace(/\s+/g, ' ').trim(),
      h1: rect(h1),
      searchForm: rect(document.getElementById('tourSearch')),
      status: rect(status),
      resultsSummary: rect(document.querySelector('.results-search-summary')),
      resultsTools: rect(document.getElementById('resultsTools')),
      mobileToolbar: rect(mobileToolbar),
      resultsLayout: rect(layout),
      filterRail: rect(rail),
      filterAffordanceCount: visibleFilterAffordances.length,
      mobileFilter: rect(mobileFilter),
      statusPosition: status ? getComputedStyle(status).position : '',
      statusToolbarIntersection: intersection(clippedStatus, clippedToolbar),
      toolbarOwnsVisibleCenter: !!(mobileToolbar && toolbarCenterNode && mobileToolbar.contains(toolbarCenterNode)),
      firstResult: rect(first),
      firstPhoto: rect(firstPhoto),
      firstBody: rect(firstBody),
      disclosure: rect(disclosure),
      filterReset: rect(filterReset),
      filterOption: rect(filterOption),
      typography: {
        title: title ? parseFloat(getComputedStyle(title).fontSize) : 0,
        place: place ? parseFloat(getComputedStyle(place).fontSize) : 0,
        factLabel: factLabel ? parseFloat(getComputedStyle(factLabel).fontSize) : 0,
        disclosure: disclosure ? parseFloat(getComputedStyle(disclosure).fontSize) : 0,
        filterOption: filterOption ? parseFloat(getComputedStyle(filterOption).fontSize) : 0,
      },
      cardCopy: {
        category: (category?.textContent || '').trim(),
        facts: Object.fromEntries(factEntries),
        priceContext: (priceContext?.textContent || '').replace(/\s+/g, ' ').trim(),
        priceAriaLabel: price?.getAttribute('aria-label') || '',
        priceContextVisible: rendered(priceContext),
        priceContextBox: rect(priceContext),
      },
      decoratedCount: document.querySelectorAll('#results .hotel-card[data-search3-results-v1="1"]').length,
      disclosureCount: document.querySelectorAll('#results .search3-show-tours').length,
      collapsedToursCount: [...document.querySelectorAll('#results .hotel-tours')].filter(node => node.hidden).length,
      directTourText: (directTour?.textContent || '').replace(/\s+/g, ' ').trim(),
      resultsActive: document.body.classList.contains('search3-results-active'),
    };
  }, { expectedWidth: width, expectedState: state });
}

async function capture(page, width, state, expectedResultCount, manifest) {
  assert.ok(expectedStates.includes(state));
  if (expectedResultCount > 0) {
    await page.waitForFunction(count => (
      document.querySelectorAll('#results .hotel-card[data-search3-results-v1="1"]').length === count
      && document.querySelectorAll('#results .search3-show-tours').length === count
    ), expectedResultCount, { timeout: 12000 });
    if (width <= 999) await page.waitForSelector('.search3-mobile-toolbar', { state: 'visible' });
    await page.locator('#results .hotel-card').first().scrollIntoViewIfNeeded();
  }
  const filename = `${width}-${state}.png`;
  const absolute = path.join(outputDir, filename);
  await page.screenshot({ path: absolute, animations: 'disabled' });
  const bytes = fs.readFileSync(absolute);
  const measured = await geometry(page, width, state);
  assert.equal(measured.h1Count, 1);
  assert.equal(measured.resultCount, expectedResultCount, `${width}/${state}: result count drifted before capture`);
  assert.equal(measured.horizontalOverflow, false, `${width}/${state}: horizontal overflow`);
  if (expectedResultCount > 0) {
    assert.equal(measured.resultsActive, true, `${width}/${state}: candidate result state missing`);
    assert.equal(measured.decoratedCount, expectedResultCount, `${width}/${state}: undecorated result card`);
    assert.equal(measured.disclosureCount, expectedResultCount, `${width}/${state}: disclosure count`);
    assert.equal(measured.collapsedToursCount, expectedResultCount, `${width}/${state}: tour rows must start collapsed`);
    assert.equal(
      measured.directTourText,
      fixture.presentation.productionOwnedDirectTourText,
      `${width}/${state}: production-owned tour CTA copy changed`,
    );
    assert.ok(measured.disclosure.height >= 44, `${width}/${state}: disclosure touch target`);
    assert.ok(measured.typography.title >= 16, `${width}/${state}: hotel title is too small`);
    assert.ok(measured.typography.place >= 12, `${width}/${state}: hotel location is too small`);
    assert.ok(measured.typography.factLabel >= 10, `${width}/${state}: hotel fact label is too small`);
    assert.ok(measured.typography.disclosure >= 14, `${width}/${state}: disclosure label is too small`);
    assert.equal(measured.cardCopy.category, '5★', `${width}/${state}: hotel category must be visible beside the title`);
    assert.equal(measured.cardCopy.facts['Вылет'], '12 сент. 2099', `${width}/${state}: departure date must be human-readable`);
    assert.equal(measured.cardCopy.facts['Питание'], 'Всё включено', `${width}/${state}: meal code/name must be customer-readable`);
    assert.equal(measured.cardCopy.priceContext, '8 ночей · 2 взрослых Чартерный перелёт · Всё включено', `${width}/${state}: price context must explain the package`);
    assert.match(measured.cardCopy.priceAriaLabel, /за тур на 2 взрослых$/, `${width}/${state}: total price needs an accessible scope`);
    assert.equal(measured.cardCopy.priceContextVisible, true, `${width}/${state}: price context must be visibly rendered`);
    assert.ok(measured.cardCopy.priceContextBox.height >= 20, `${width}/${state}: both price-context lines must remain visible`);
    assert.equal(measured.filterAffordanceCount, 1, `${width}/${state}: expected exactly one visible filter affordance`);
    if (width === 375 && state === 'progressive-25') {
      assert.ok(
        measured.statusToolbarIntersection.height <= 1,
        `${width}/${state}: status overlaps mobile toolbar by ${measured.statusToolbarIntersection.height}px`,
      );
      assert.equal(measured.toolbarOwnsVisibleCenter, true, `${width}/${state}: status intercepts the toolbar center`);
      assert.notEqual(measured.statusPosition, 'sticky', `${width}/${state}: progressive status must stay in flow`);
    }
    if (width >= 1000) {
      assert.ok(measured.firstResult.height >= 208 && measured.firstResult.height <= 214, `${width}/${state}: desktop card height`);
      assert.ok(measured.firstPhoto.height >= 208 && measured.firstPhoto.height <= 212, `${width}/${state}: desktop photo height`);
      assert.ok(measured.filterRail.width >= 218 && measured.filterRail.width <= 222, `${width}/${state}: desktop rail width`);
      assert.ok(measured.filterReset.height >= 44, `${width}/${state}: filter reset touch target`);
      assert.ok(measured.filterOption.height >= 44, `${width}/${state}: filter option touch target`);
      assert.ok(measured.typography.filterOption >= 12, `${width}/${state}: filter option label is too small`);
      const gap = measured.firstResult.x - (measured.filterRail.x + measured.filterRail.width);
      assert.ok(gap >= 12 && gap <= 16, `${width}/${state}: desktop rail gap`);
    } else if (width > 760) {
      assert.ok(measured.firstResult.height >= 216 && measured.firstResult.height <= 222, `${width}/${state}: tablet card height`);
      assert.equal(Math.round(measured.firstResult.width), width - 48, `${width}/${state}: tablet card width`);
      assert.ok(measured.mobileFilter.height >= 44, `${width}/${state}: tablet filter touch target`);
      for (const [surface, box] of Object.entries({ summary: measured.resultsSummary, tools: measured.resultsTools, toolbar: measured.mobileToolbar, layout: measured.resultsLayout })) {
        assert.ok(Math.abs(box.x - measured.firstResult.x) <= 1, `${width}/${state}: tablet ${surface} left edge`);
        assert.ok(Math.abs(box.width - measured.firstResult.width) <= 1, `${width}/${state}: tablet ${surface} width`);
      }
    } else {
      assert.equal(Math.round(measured.firstResult.width), width - 48, `${width}/${state}: mobile card width`);
      assert.ok(measured.firstPhoto.height >= 124 && measured.firstPhoto.height <= 128, `${width}/${state}: mobile photo height`);
      for (const [surface, box] of Object.entries({ summary: measured.resultsSummary, tools: measured.resultsTools, toolbar: measured.mobileToolbar, layout: measured.resultsLayout })) {
        assert.ok(Math.abs(box.x - measured.firstResult.x) <= 1, `${width}/${state}: mobile ${surface} left edge`);
        assert.ok(Math.abs(box.width - measured.firstResult.width) <= 1, `${width}/${state}: mobile ${surface} width`);
      }
    }
  } else {
    assert.equal(measured.resultsActive, false, `${width}/${state}: empty page leaked result state`);
  }
  manifest.screenshots.push({
    file: filename,
    sha256: crypto.createHash('sha256').update(bytes).digest('hex'),
    bytes: bytes.length,
    geometry: measured,
  });
}

async function assertClean(harness, label) {
  await new Promise(resolve => setTimeout(resolve, 50));
  const layout = await harness.page.evaluate(() => ({
    h1Count: document.querySelectorAll('h1').length,
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2
      || document.body.scrollWidth > document.documentElement.clientWidth + 2,
  }));
  assert.deepEqual(layout, { h1Count: 1, overflow: false }, `${label}: document layout`);
  assert.deepEqual(harness.pageErrors, [], `${label}: page errors`);
  assert.deepEqual(harness.consoleErrors, [], `${label}: console errors`);
  assert.deepEqual(harness.unexpectedFailures, [], `${label}: unexpected request failures`);
  assert.deepEqual(harness.unexpectedOutbound, [], `${label}: unexpected outbound request attempted`);
  assert.deepEqual(harness.productionLeadRequests, [], `${label}: production lead endpoint must not be fetched`);
  assert.deepEqual(harness.candidateLeadRequests, [], `${label}: browser lead form must remain unsubmitted`);
  assert.deepEqual(harness.httpErrorResponses, [], `${label}: unexpected HTTP error response`);
  assert.equal(new URL(harness.page.url()).pathname, fixture.route, `${label}: URL changed`);
}

async function runFiveWidthEvidence(browser, manifest) {
  for (const viewport of fixture.viewports) {
    const captureLifecycle = visualTier.lifecycleWidths.includes(viewport.width);
    const captureFinalOnly = visualTier.finalOnlyWidths.includes(viewport.width);
    assert.ok(captureLifecycle || captureFinalOnly, `${visualTierName}: width ${viewport.width} is not assigned`);
    const scenario = scenarioController('progressive');
    const harness = await createHarness(browser, viewport, scenario);
    try {
      await setSearchValues(harness.page);
      if (captureLifecycle) await capture(harness.page, viewport.width, 'initial', 0, manifest);

      await harness.page.evaluate(() => window.V2SearchLifecycle.submit());
      await harness.page.waitForFunction(count => document.querySelectorAll('#results .hotel-card').length === count, fixture.progressive.firstLimit, { timeout: 12000 });
      await waitFor(() => scenario.heldFinalStatus, `${viewport.width}: final status response was not held`);
      await harness.page.locator('#status').scrollIntoViewIfNeeded();
      if (captureLifecycle) {
        await capture(harness.page, viewport.width, 'progressive-25', fixture.progressive.firstLimit, manifest);
      }

      await scenario.heldFinalStatus();

      await harness.page.waitForFunction(count => document.querySelectorAll('#results .hotel-card').length === count, fixture.progressive.finalLimit, { timeout: 12000 });
      await harness.page.waitForFunction(() => document.querySelector('.search-progress-done'));
      await harness.page.locator('#status').scrollIntoViewIfNeeded();
      await capture(harness.page, viewport.width, 'final-100', fixture.progressive.finalLimit, manifest);

      const APIResults = scenario.calls.filter(call => call.action === 'search_results');
      assert.ok(APIResults.some(call => call.limit === 25), `${viewport.width}: missing progressive limit=25`);
      assert.ok(APIResults.some(call => call.limit === 100), `${viewport.width}: missing final limit=100`);
      await assertClean(harness, `${viewport.width}/evidence`);
    } finally {
      await harness.context.close();
    }
  }
}

async function runResponsiveFilterBoundaries(browser, manifest) {
  const widths = [760, 761, 768, 999, 1000];
  manifest.presentationChecks.responsiveFilters = {};
  for (const width of widths) {
    const scenario = scenarioController(`filter-boundary-${width}`);
    const harness = await createHarness(browser, { width, height: 900 }, scenario);
    try {
      await harness.page.evaluate(items => window.V2Results.render(items), resultSets[25]);
      await harness.page.waitForFunction(count => (
        document.querySelectorAll('#results .hotel-card[data-search3-results-v1="1"]').length === count
      ), resultSets[25].length, { timeout: 12000 });
      if (width <= 999) {
        await harness.page.waitForSelector('.search3-mobile-toolbar .mrf-open', { state: 'visible' });
      }

      const closed = await harness.page.evaluate(() => {
        const visible = node => {
          if (!node || node.hidden) return false;
          const box = node.getBoundingClientRect();
          const css = getComputedStyle(node);
          return box.width > 0 && box.height > 0 && css.display !== 'none' && css.visibility !== 'hidden';
        };
        const button = document.querySelector('.search3-mobile-toolbar .mrf-open');
        const rail = document.querySelector('.results-filter-rail');
        return {
          buttonVisible: visible(button),
          buttonEnabled: !!button && !button.disabled,
          buttonHeight: button ? Math.round(button.getBoundingClientRect().height * 100) / 100 : 0,
          railVisible: visible(rail),
          visibleAffordances: [button, rail].filter(visible).length,
        };
      });
      assert.equal(closed.visibleAffordances, 1, `${width}: expected exactly one closed filter affordance`);

      if (width <= 999) {
        assert.deepEqual(
          { buttonVisible: closed.buttonVisible, buttonEnabled: closed.buttonEnabled, railVisible: closed.railVisible },
          { buttonVisible: true, buttonEnabled: true, railVisible: false },
          `${width}: compact filter ownership`,
        );
        assert.ok(closed.buttonHeight >= 44, `${width}: compact filter target is too short`);
        const button = harness.page.locator('.search3-mobile-toolbar .mrf-open');
        await button.click();
        await harness.page.waitForSelector('.mrf-sheet.is-open .mrf-panel[role="dialog"]', { state: 'visible' });
        await harness.page.waitForFunction(() => document.querySelector('.mrf-panel')?.contains(document.activeElement));
        const open = await harness.page.evaluate(() => {
          const sheet = document.querySelector('.mrf-sheet');
          const panel = document.querySelector('.mrf-panel[role="dialog"]');
          return {
            expanded: document.querySelector('.search3-mobile-toolbar .mrf-open')?.getAttribute('aria-expanded') || '',
            open: !!sheet?.classList.contains('is-open'),
            focusInside: !!panel?.contains(document.activeElement),
            role: panel?.getAttribute('role') || '',
          };
        });
        assert.deepEqual(open, { expanded: 'true', open: true, focusInside: true, role: 'dialog' }, `${width}: filter dialog open state`);
        await harness.page.keyboard.press('Shift+Tab');
        const wrappedBackward = await harness.page.evaluate(() => (
          document.activeElement?.classList.contains('mrf-apply') === true
          && document.querySelector('.mrf-panel')?.contains(document.activeElement) === true
        ));
        assert.equal(wrappedBackward, true, `${width}: filter dialog must wrap backward focus`);
        await harness.page.keyboard.press('Tab');
        const wrappedForward = await harness.page.evaluate(() => (
          document.activeElement?.classList.contains('mrf-close') === true
          && document.querySelector('.mrf-panel')?.contains(document.activeElement) === true
        ));
        assert.equal(wrappedForward, true, `${width}: filter dialog must wrap forward focus`);
        await harness.page.keyboard.press('Escape');
        await harness.page.waitForFunction(() => !document.querySelector('.mrf-sheet')?.classList.contains('is-open'));
        await harness.page.waitForFunction(() => document.activeElement?.classList.contains('mrf-open'));
        const closedAfterEscape = await button.getAttribute('aria-expanded');
        assert.equal(closedAfterEscape, 'false', `${width}: Escape did not close the filter dialog`);
        manifest.presentationChecks.responsiveFilters[String(width)] = {
          owner: 'dialog-button',
          touchHeight: closed.buttonHeight,
          dialogFocused: true,
          focusTrapped: true,
          escapeFocusReturned: true,
        };
      } else {
        assert.deepEqual(
          { buttonVisible: closed.buttonVisible, railVisible: closed.railVisible },
          { buttonVisible: false, railVisible: true },
          `${width}: desktop filter ownership`,
        );
        manifest.presentationChecks.responsiveFilters[String(width)] = { owner: 'desktop-rail' };
      }
      assert.equal(apiCallCount(scenario), 0, `${width}: responsive filter availability must not request search data`);
      await assertClean(harness, `filter-boundary-${width}`);
    } finally {
      await harness.context.close();
    }
  }
}

async function writePresentationScreenshot(page, filename, manifest, details) {
  assert.ok(expectedPresentationCaptures.includes(filename), `unexpected presentation capture ${filename}`);
  const absolute = path.join(outputDir, filename);
  await page.screenshot({ path: absolute, animations: 'disabled' });
  const bytes = fs.readFileSync(absolute);
  manifest.presentationScreenshots.push({
    file: filename,
    sha256: crypto.createHash('sha256').update(bytes).digest('hex'),
    bytes: bytes.length,
    ...details,
  });
}

function apiCallCount(scenario) {
  return scenario.calls.filter(call => call.action === 'search_start' || call.action === 'search_results').length;
}

async function loadCompleteResults(harness, scenario) {
  await setSearchValues(harness.page);
  await harness.page.evaluate(() => window.V2SearchLifecycle.submit());
  await harness.page.waitForFunction(count => (
    document.querySelectorAll('#results .hotel-card').length === count
    && document.querySelectorAll('#results .hotel-card[data-search3-results-v1="1"]').length === count
  ), fixture.progressive.finalLimit, { timeout: 12000 });
  await harness.page.locator('#results .hotel-card').first().scrollIntoViewIfNeeded();
  assert.ok(scenario.calls.some(call => call.action === 'search_results' && call.limit === 100));
}

async function runDesktopPresentationEvidence(browser, manifest) {
  const viewport = fixture.viewports.find(item => item.width === 1440);
  const scenario = scenarioController('complete-presentation');
  const harness = await createHarness(browser, viewport, scenario);
  try {
    await loadCompleteResults(harness, scenario);
    const beforeFamilyScopeCalls = apiCallCount(scenario);
    await harness.page.evaluate(() => {
      document.getElementById('tourSearch').elements.child_count.value = '1';
      window.V2Results.rerender();
    });
    await harness.page.waitForFunction(count => (
      document.querySelectorAll('#results .hotel-card[data-search3-results-v1="1"]').length === count
    ), fixture.progressive.finalLimit);
    const familyPriceScope = await harness.page.evaluate(() => {
      const card = document.querySelector('#results .hotel-card');
      const context = card?.querySelector('.hotel-price-context') || null;
      const lines = [...(context?.querySelectorAll('span') || [])];
      const familyLine = lines[1] || null;
      const familyBox = familyLine?.getBoundingClientRect() || null;
      return {
        context: (context?.textContent || '').replace(/\s+/g, ' ').trim(),
        lines: lines.map(node => (node.textContent || '').replace(/\s+/g, ' ').trim()),
        familyLineVisible: !!(familyBox && familyBox.width > 0 && familyBox.height > 0),
        ariaLabel: card?.querySelector('.hotel-price')?.getAttribute('aria-label') || '',
      };
    });
    assert.equal(familyPriceScope.context, '8 ночей · 2 взрослых и 1 ребёнок Чартерный перелёт · Всё включено');
    assert.deepEqual(familyPriceScope.lines, ['8 ночей · 2 взрослых', 'и 1 ребёнок', 'Чартерный перелёт · Всё включено']);
    assert.equal(familyPriceScope.familyLineVisible, true, 'child count must be visibly rendered, not only present in the DOM');
    assert.match(familyPriceScope.ariaLabel, /за тур на 2 взрослых и 1 ребёнок$/);
    assert.equal(apiCallCount(scenario), beforeFamilyScopeCalls, 'party-scope presentation update must remain local');
    manifest.presentationChecks.familyPriceScope = {
      children: 1,
      context: familyPriceScope.context,
      ariaLabel: familyPriceScope.ariaLabel,
      newSearchCalls: 0,
    };
    const beforeDisclosureCalls = apiCallCount(scenario);
    await harness.page.locator('#results .search3-show-tours').first().click();
    await harness.page.waitForFunction(() => (
      document.querySelectorAll('#results .hotel-card.search3-tours-open').length === 1
      && !document.querySelector('#results .hotel-card.search3-tours-open .hotel-tours')?.hidden
    ));
    const disclosure = await harness.page.evaluate(() => {
      const card = document.querySelector('#results .hotel-card.search3-tours-open');
      const button = card?.querySelector('.search3-show-tours');
      const direct = card?.querySelector('.direct-tour');
      const action = direct?.closest('.tour-action');
      const price = action?.querySelector(':scope > b');
      const priceScope = action?.querySelector('.search3-tour-price-scope');
      const listHead = card?.querySelector('.search3-tour-list-head');
      const tourDate = card?.querySelector('.tour-meta > strong');
      const tourFacts = Object.fromEntries([...(card?.querySelectorAll('.tour-fact') || [])].map(fact => [
        (fact.querySelector('small')?.textContent || '').trim(),
        (fact.querySelector('b')?.textContent || '').trim(),
      ]).filter(entry => entry[0]));
      const priceBox = price?.getBoundingClientRect();
      const directBox = direct?.getBoundingClientRect();
      const priceScopeBox = priceScope?.getBoundingClientRect();
      const priceButtonOverlap = priceBox && directBox
        ? Math.max(0, Math.min(priceBox.right, directBox.right) - Math.max(priceBox.left, directBox.left))
          * Math.max(0, Math.min(priceBox.bottom, directBox.bottom) - Math.max(priceBox.top, directBox.top))
        : -1;
      const priceButtonGap = priceBox && directBox
        ? directBox.top >= priceBox.bottom
          ? directBox.top - priceBox.bottom
          : directBox.left >= priceBox.right
            ? directBox.left - priceBox.right
            : -1
        : -1;
      return {
        openCards: document.querySelectorAll('#results .hotel-card.search3-tours-open').length,
        expanded: button?.getAttribute('aria-expanded') || '',
        buttonText: button?.textContent.replace(/\s+/g, ' ').trim() || '',
        directTourText: direct?.textContent.replace(/\s+/g, ' ').trim() || '',
        directTourCount: card?.querySelectorAll('.direct-tour').length || 0,
        tourPriceText: price?.textContent.replace(/\s+/g, ' ').trim() || '',
        tourPriceScopeText: priceScope?.textContent.replace(/\s+/g, ' ').trim() || '',
        tourPriceScopeVisible: !!(priceScopeBox && priceScopeBox.width > 0 && priceScopeBox.height > 0),
        listTitle: listHead?.querySelector('strong')?.textContent.replace(/\s+/g, ' ').trim() || '',
        listHint: listHead?.querySelector('span')?.textContent.replace(/\s+/g, ' ').trim() || '',
        listCount: listHead?.querySelector(':scope > b')?.textContent.replace(/\s+/g, ' ').trim() || '',
        tourDate: tourDate?.textContent.replace(/\s+/g, ' ').trim() || '',
        tourFacts,
        tourPriceWhiteSpace: price ? getComputedStyle(price).whiteSpace : '',
        tourActionOverflow: action ? action.scrollWidth > action.clientWidth + 1 : true,
        tourPriceButtonOverlap: Math.round(priceButtonOverlap * 100) / 100,
        tourPriceButtonGap: Math.round(priceButtonGap * 100) / 100,
      };
    });
    assert.deepEqual({
      openCards: disclosure.openCards,
      expanded: disclosure.expanded,
      buttonText: disclosure.buttonText,
      directTourText: disclosure.directTourText,
      directTourCount: disclosure.directTourCount,
    }, {
      openCards: 1,
      expanded: 'true',
      buttonText: 'Скрыть туры',
      directTourText: fixture.presentation.productionOwnedDirectTourText,
      directTourCount: 1,
    });
    assert.match(disclosure.tourPriceText, /^\d[\d ]* ₽$/, 'expanded tour price must remain readable');
    assert.equal(disclosure.tourPriceScopeText, 'За весь тур');
    assert.equal(disclosure.tourPriceScopeVisible, true);
    assert.equal(disclosure.listTitle, 'Лучшее предложение');
    assert.equal(disclosure.listHint, 'Сравните дату, номер, питание и цену');
    assert.equal(disclosure.listCount, '1 тур');
    assert.equal(disclosure.tourDate, '12 сент. 2099');
    assert.equal(disclosure.tourFacts['Питание'], 'Всё включено');
    assert.equal(disclosure.tourFacts['Номер'], 'Стандартный номер');
    assert.equal(disclosure.tourFacts['Размещение'], '2 взрослых');
    assert.equal(disclosure.tourPriceWhiteSpace, 'nowrap', 'expanded tour price must stay on one line');
    assert.equal(disclosure.tourActionOverflow, false, 'expanded tour action must not overflow');
    assert.equal(disclosure.tourPriceButtonOverlap, 0, 'expanded tour price and CTA must not overlap');
    assert.ok(disclosure.tourPriceButtonGap >= 6, 'expanded tour price and CTA need a visible gap');
    assert.equal(apiCallCount(scenario), beforeDisclosureCalls, 'card disclosure must not request search data');
    await writePresentationScreenshot(harness.page, '1440-first-hotel-expanded.png', manifest, { disclosure });

    await harness.page.locator('#results .search3-show-tours').first().click();
    const beforeFilterCalls = apiCallCount(scenario);
    await harness.page.locator('.results-filter-rail [data-ds2-price]').evaluate(node => {
      node.value = '125000';
      node.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await harness.page.waitForFunction(() => document.querySelectorAll('#results .hotel-card').length === 25);
    assert.equal(apiCallCount(scenario), beforeFilterCalls, 'desktop loaded-result filter must remain local');
    assert.equal(new URL(harness.page.url()).pathname, fixture.route);
    assert.deepEqual(await currentSearchValues(harness.page), fixture.search);
    manifest.presentationChecks.desktopLocalFilter = {
      resultCount: 25,
      newSearchCalls: 0,
      urlPreserved: true,
    };
    await assertClean(harness, 'desktop-presentation');
  } finally {
    await harness.context.close();
  }
}

async function runMobileExpandedOfferEvidence(browser, manifest) {
  const viewport = fixture.viewports.find(item => item.width === 375);
  const scenario = scenarioController('mobile-expanded-offer');
  const harness = await createHarness(browser, viewport, scenario);
  try {
    await loadCompleteResults(harness, scenario);
    const beforeDisclosureCalls = apiCallCount(scenario);
    await harness.page.locator('#results .search3-show-tours').first().click();
    await harness.page.waitForFunction(() => (
      document.querySelectorAll('#results .hotel-card.search3-tours-open').length === 1
      && !document.querySelector('#results .hotel-card.search3-tours-open .hotel-tours')?.hidden
    ));
    const expanded = await harness.page.evaluate(() => {
      const card = document.querySelector('#results .hotel-card.search3-tours-open');
      const row = card?.querySelector('.tour-row');
      const button = card?.querySelector('.direct-tour');
      const scope = card?.querySelector('.search3-tour-price-scope');
      const date = card?.querySelector('.tour-meta > strong');
      const meal = [...(card?.querySelectorAll('.tour-fact') || [])].find(fact => (
        fact.querySelector('small')?.textContent.trim().toLowerCase() === 'питание'
      ))?.querySelector('b');
      const room = [...(card?.querySelectorAll('.tour-fact') || [])].find(fact => (
        fact.querySelector('small')?.textContent.trim().toLowerCase() === 'номер'
      ))?.querySelector('b');
      const box = node => {
        const rect = node?.getBoundingClientRect();
        return rect ? { width: rect.width, height: rect.height } : null;
      };
      return {
        horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
        listTitle: card?.querySelector('.search3-tour-list-head strong')?.textContent.trim() || '',
        date: date?.textContent.trim() || '',
        meal: meal?.textContent.trim() || '',
        room: room?.textContent.trim() || '',
        priceScope: scope?.textContent.trim() || '',
        row: box(row),
        button: box(button),
        scope: box(scope),
      };
    });
    assert.equal(expanded.horizontalOverflow, false, '375 expanded offer must not overflow horizontally');
    assert.equal(expanded.listTitle, 'Лучшее предложение');
    assert.equal(expanded.date, '12 сент. 2099');
    assert.equal(expanded.meal, 'Всё включено');
    assert.equal(expanded.room, 'Стандартный номер');
    assert.equal(expanded.priceScope, 'За весь тур');
    assert.ok(expanded.row?.width > 0 && expanded.row?.height > 0, '375 expanded offer row must be visible');
    assert.ok(expanded.button?.height >= 44, '375 expanded offer CTA must keep a 44px touch target');
    assert.ok(expanded.scope?.width > 0 && expanded.scope?.height > 0, '375 total-price scope must be visible');
    assert.equal(apiCallCount(scenario), beforeDisclosureCalls, '375 disclosure must remain local');
    await writePresentationScreenshot(harness.page, '375-first-hotel-expanded.png', manifest, { expanded });
    await assertClean(harness, '375-expanded-offer-presentation');
  } finally {
    await harness.context.close();
  }
}

async function renderSelectedTourOffer(page) {
  await page.evaluate(({ result, searchId }) => {
    window.V2Runtime.setSearchId(searchId);
    window.V2Results.render([result]);
  }, {
    result: selectedTourResult(),
    searchId: fixture.failureFixtures.selectedTour.searchId,
  });
  await page.waitForFunction(() => (
    document.querySelectorAll('#results .hotel-card[data-search3-results-v1="1"]').length === 1
  ));
  await page.locator('#results .search3-show-tours').click();
  await page.waitForFunction(() => !document.querySelector('#results .hotel-tours')?.hidden);
}

async function selectedPresentationState(page) {
  return page.evaluate(() => {
    const rows = (selector, labelSelector, valueSelector) => Object.fromEntries(
      [...document.querySelectorAll(selector)].map(row => [
        row.querySelector(labelSelector)?.textContent.replace(/\s+/g, ' ').trim() || '',
        row.querySelector(valueSelector)?.textContent.replace(/\s+/g, ' ').trim() || '',
      ]).filter(entry => entry[0]),
    );
    const services = rows('#selectedTour .search3-final-services > article', 'span', 'strong');
    const roomService = [...document.querySelectorAll('#selectedTour .search3-final-services > article')].find(article => (
      article.querySelector('span')?.textContent.trim().toLowerCase() === 'номер'
    ));
    const current = window.V2TourController?.currentTour || {};
    const stepperRect = document.querySelector('#selectedTour > .search3-booking-stepper')?.getBoundingClientRect();
    const detailRect = document.querySelector('#selectedTour > .search3-tour-detail-rail')?.getBoundingClientRect();
    const overlaps = (a, b) => Boolean(a && b && a.width > 0 && b.width > 0
      && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top);
    const rawText = value => value && typeof value === 'object'
      ? String(value.russianName || value.fullRussianName || value.name || value.title || '')
      : String(value || '');
    return {
      version: window.Search3CandidateSelectedPresentationV1?.version || 0,
      marker: document.getElementById('selectedTour')?.dataset.search3SelectedPresentation || '',
      facts: rows('#selectedTour .facts > div', 'span', 'b'),
      services,
      servicePlacement: roomService?.querySelector('small')?.textContent.replace(/\s+/g, ' ').trim() || '',
      summary: rows('#selectedTour .search3-booking-summary dl > div', 'dt', 'dd'),
      detail: rows('#selectedTour .search3-tour-detail-rail dl > div', 'dt', 'dd'),
      priceScopes: {
        selected: document.querySelector('#selectedTour .selected-price > small')?.textContent.replace(/\s+/g, ' ').trim() || '',
        summary: document.querySelector('#selectedTour .search3-booking-summary__total > span')?.textContent.replace(/\s+/g, ' ').trim() || '',
        detail: document.querySelector('#selectedTour .search3-tour-detail-rail__price > span')?.textContent.replace(/\s+/g, ' ').trim() || '',
        mobile: document.querySelector('.search3-selected-mobile-bar__price small')?.textContent.replace(/\s+/g, ' ').trim() || '',
      },
      ctas: {
        detail: document.querySelector('#selectedTour .search3-tour-detail-rail__continue')?.textContent.replace(/\s+/g, ' ').trim() || '',
        mobile: document.querySelector('.search3-selected-mobile-bar [data-s3-selected-lead]')?.textContent.replace(/\s+/g, ' ').trim() || '',
      },
      rawTour: {
        date: String(current.date || ''),
        meal: rawText(current.meal),
        room: rawText(current.roomType),
        placement: rawText(current.placement),
      },
      pathname: location.pathname,
      horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      stepperRailOverlap: overlaps(stepperRect, detailRect),
    };
  });
}

function assertSelectedPresentation(state, width) {
  assert.equal(state.version, 1);
  assert.equal(state.marker, '1');
  assert.deepEqual({
    date: state.facts['Дата'],
    meal: state.facts['Питание'],
    room: state.facts['Номер'],
    placement: state.facts['Размещение'],
  }, {
    date: '12 сент. 2099',
    meal: 'Всё включено',
    room: 'Стандартный номер',
    placement: 'Двухместное',
  });
  assert.deepEqual({
    meal: state.services['Питание'],
    room: state.services['Номер'],
    placement: state.servicePlacement,
  }, {
    meal: 'Всё включено',
    room: 'Стандартный номер',
    placement: 'Двухместное',
  });
  assert.deepEqual({
    date: state.summary['Дата'],
    meal: state.summary['Питание'],
    room: state.summary['Номер'],
  }, {
    date: '12 сент. 2099',
    meal: 'Всё включено',
    room: 'Стандартный номер',
  });
  assert.deepEqual({
    date: state.detail['Дата'],
    meal: state.detail['Питание'],
    room: state.detail['Номер'],
  }, {
    date: '12 сент. 2099 · 8 ночей',
    meal: 'Всё включено',
    room: 'Стандартный номер',
  });
  assert.deepEqual(state.priceScopes, {
    selected: 'За весь тур · 2 взрослых',
    summary: 'За весь тур · 2 взрослых',
    detail: 'За весь тур · 2 взрослых',
    mobile: 'За весь тур · 2 взрослых',
  });
  assert.deepEqual(state.ctas, {
    detail: 'К выбору рейса',
    mobile: 'К выбору рейса',
  });
  assert.deepEqual(state.rawTour, {
    date: fixture.failureFixtures.selectedTour.date,
    meal: fixture.failureFixtures.selectedTour.meal.name,
    room: fixture.failureFixtures.selectedTour.roomType,
    placement: fixture.failureFixtures.selectedTour.placement,
  }, `${width}: presentation must not mutate Tourvisor-derived tour data`);
  assert.equal(state.pathname, fixture.route);
  assert.equal(state.horizontalOverflow, false, `${width}: selected presentation must not overflow`);
  if (width >= 1000) assert.equal(state.stepperRailOverlap, false, `${width}: booking steps must clear the total rail`);
}

async function waitForSelectedPresentation(page) {
  await page.waitForFunction(expected => {
    const valueFor = (rows, labelSelector, valueSelector, label) => {
      const row = [...document.querySelectorAll(rows)].find(item => (
        item.querySelector(labelSelector)?.textContent.replace(/\s+/g, ' ').trim() === label
      ));
      return row?.querySelector(valueSelector)?.textContent.replace(/\s+/g, ' ').trim() || '';
    };
    return document.getElementById('selectedTour')?.dataset.search3SelectedPresentation === '1'
      && valueFor('#selectedTour .facts > div', 'span', 'b', 'Дата') === expected.date
      && valueFor('#selectedTour .search3-booking-summary dl > div', 'dt', 'dd', 'Питание') === expected.meal
      && valueFor('#selectedTour .search3-tour-detail-rail dl > div', 'dt', 'dd', 'Номер') === expected.room;
  }, {
    date: '12 сент. 2099',
    meal: 'Всё включено',
    room: 'Стандартный номер',
  }, { timeout: 12000 });
}

async function runMobileSelectedHandoffEvidence(browser, manifest) {
  const viewport = fixture.viewports.find(item => item.width === 375);
  const scenario = scenarioController('flight-empty');
  const harness = await createHarness(browser, viewport, scenario);
  try {
    await renderSelectedTourOffer(harness.page);
    await harness.page.evaluate(tour => {
      const runtime = window.V2Runtime;
      const originalApi = runtime.api.bind(runtime);
      let resolveTour;
      const pendingTour = new Promise(resolve => { resolveTour = resolve; });
      runtime.api = (action, params, options) => (
        action === 'tour' ? pendingTour : originalApi(action, params, options)
      );
      window.__search3ResolveSelectedTour = () => resolveTour(tour);
    }, fixture.failureFixtures.selectedTour);

    const source = harness.page.locator(`button[data-tid="${fixture.failureFixtures.selectedTour.id}"]`);
    await source.click();
    await harness.page.waitForFunction(() => (
      document.getElementById('selectedTour')?.getAttribute('aria-busy') === 'true'
      && document.querySelector('#results button[data-search3-production-label]')?.disabled === true
    ));
    const loading = await harness.page.evaluate(() => {
      const selected = document.getElementById('selectedTour');
      const sourceButton = document.querySelector('#results button[data-search3-production-label]');
      return {
        ariaBusy: selected?.getAttribute('aria-busy') || '',
        sourceDisabled: !!sourceButton?.disabled,
        sourceText: sourceButton?.textContent.replace(/\s+/g, ' ').trim() || '',
        originalLabel: sourceButton?.dataset.search3ProductionLabel || '',
      };
    });
    assert.equal(loading.ariaBusy, 'true');
    assert.equal(loading.sourceDisabled, true);
    assert.notEqual(loading.sourceText, loading.originalLabel, 'loading feedback must remain production-owned');
    assert.equal(loading.originalLabel, fixture.presentation.productionOwnedDirectTourText);

    await harness.page.evaluate(() => window.__search3ResolveSelectedTour());
    await harness.page.waitForFunction(tourId => {
      const selected = document.getElementById('selectedTour');
      return window.V2TourController?.currentTour?.id === tourId
        && selected?.getAttribute('aria-busy') === 'false'
        && !!selected?.querySelector('.selected-head h2');
    }, fixture.failureFixtures.selectedTour.id, { timeout: 12000 });
    await harness.page.waitForFunction(label => {
      const sourceButton = document.querySelector('#results button[data-search3-production-label]');
      return sourceButton?.disabled === false
        && sourceButton?.textContent.replace(/\s+/g, ' ').trim() === label;
    }, fixture.presentation.productionOwnedDirectTourText, { timeout: 12000 });
    await harness.page.waitForFunction(() => {
      const selected = document.getElementById('selectedTour');
      const heading = document.querySelector('#selectedTour .selected-head h2');
      return !!heading && (document.activeElement === heading || document.activeElement === selected);
    }, null, { timeout: 12000 });
    await harness.page.waitForFunction(() => /менеджер уточнит перелёт по заявке/i.test(
      document.querySelector('.tour-flights .selected-loading')?.textContent || ''
    ), null, { timeout: 12000 });
    await waitForSelectedPresentation(harness.page);

    const selectedState = await harness.page.evaluate(() => {
      const selected = document.getElementById('selectedTour');
      const heading = selected?.querySelector('.selected-head h2');
      const headingBox = heading?.getBoundingClientRect();
      const sourceButton = document.querySelector('#results button[data-search3-production-label]');
      const labelledBy = selected?.getAttribute('aria-labelledby') || '';
      const labelledHeading = labelledBy ? document.getElementById(labelledBy) : null;
      const labelledHeadingBox = labelledHeading?.getBoundingClientRect();
      return {
        ariaBusy: selected?.getAttribute('aria-busy') || '',
        heading: heading?.textContent.replace(/\s+/g, ' ').trim() || '',
        headingFocused: document.activeElement === heading,
        selectedFocused: document.activeElement === selected,
        contextFocused: document.activeElement === heading || document.activeElement === selected,
        activeTarget: document.activeElement === heading ? 'heading' : document.activeElement === selected ? 'selected-root' : 'other',
        headingTabindex: heading?.getAttribute('tabindex') || '',
        selectedTabindex: selected?.getAttribute('tabindex') || '',
        headingVisible: !!(headingBox && headingBox.width > 0 && headingBox.height > 0),
        labelledBy,
        labelResolvesHeading: labelledHeading === heading,
        labelledHeadingVisible: !!(labelledHeadingBox && labelledHeadingBox.width > 0 && labelledHeadingBox.height > 0),
        sourceText: sourceButton?.textContent.replace(/\s+/g, ' ').trim() || '',
        horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      };
    });
    assert.deepEqual({
      ariaBusy: selectedState.ariaBusy,
      contextFocused: selectedState.contextFocused,
      headingTabindex: selectedState.headingTabindex,
      selectedTabindex: selectedState.selectedTabindex,
      headingVisible: selectedState.headingVisible,
      labelledBy: selectedState.labelledBy,
      labelResolvesHeading: selectedState.labelResolvesHeading,
      labelledHeadingVisible: selectedState.labelledHeadingVisible,
      sourceText: selectedState.sourceText,
      horizontalOverflow: selectedState.horizontalOverflow,
    }, {
      ariaBusy: 'false',
      contextFocused: true,
      headingTabindex: '-1',
      selectedTabindex: '-1',
      headingVisible: true,
      labelledBy: 'search3-selected-tour-heading',
      labelResolvesHeading: true,
      labelledHeadingVisible: true,
      sourceText: fixture.presentation.productionOwnedDirectTourText,
      horizontalOverflow: false,
    });
    assert.equal(selectedState.heading, fixture.failureFixtures.selectedTour.hotel.name);
    selectedState.presentation = await selectedPresentationState(harness.page);
    assertSelectedPresentation(selectedState.presentation, 375);
    await writePresentationScreenshot(harness.page, '375-selected-tour-handoff.png', manifest, {
      geometry: { viewportWidth: 375, state: 'selected-tour-handoff' },
      selected: selectedState,
    });

    await harness.page.locator('#selectedTour .back-results').click();
    await harness.page.waitForFunction(label => {
      const selected = document.getElementById('selectedTour');
      const sourceButton = document.querySelector('#results button[data-search3-production-label]');
      return selected?.hidden === true
        && selected?.getAttribute('aria-busy') === 'false'
        && sourceButton?.textContent.replace(/\s+/g, ' ').trim() === label;
    }, fixture.presentation.productionOwnedDirectTourText);
    await harness.page.waitForFunction(() => {
      const results = document.getElementById('results');
      const sourceButton = document.querySelector('#results button[data-search3-production-label]');
      const resumeButton = sourceButton?.closest('.hotel-card')?.querySelector('.search3-show-tours');
      return ['results', 'resume-tours'].includes(results?.dataset.search3ReturnFocus)
        && (document.activeElement === results
        || document.activeElement === sourceButton
        || document.activeElement === resumeButton);
    });
    const returned = await harness.page.evaluate(() => {
      const results = document.getElementById('results');
      const sourceButton = document.querySelector('#results button[data-search3-production-label]');
      const resumeButton = sourceButton?.closest('.hotel-card')?.querySelector('.search3-show-tours');
      const active = document.activeElement;
      return {
        selectedHidden: document.getElementById('selectedTour')?.hidden === true,
        sourceText: sourceButton?.textContent.replace(/\s+/g, ' ').trim() || '',
        returnFocused: active === results || active === sourceButton || active === resumeButton,
        focusTarget: active === resumeButton ? 'resume-tours' : active === sourceButton ? 'source-tour' : active === results ? 'results' : 'other',
        focusMarker: results?.dataset.search3ReturnFocus || '',
      };
    });
    assert.deepEqual({
      selectedHidden: returned.selectedHidden,
      sourceText: returned.sourceText,
      returnFocused: returned.returnFocused,
      focusMarker: returned.focusMarker,
    }, {
      selectedHidden: true,
      sourceText: fixture.presentation.productionOwnedDirectTourText,
      returnFocused: true,
      focusMarker: returned.focusTarget === 'results' ? 'results' : 'resume-tours',
    });
    assert.ok(['resume-tours', 'source-tour', 'results'].includes(returned.focusTarget));
    manifest.presentationChecks.selectedTourHandoff = {
      loading,
      selected: selectedState,
      returned,
      error: null,
    };
    assert.equal(harness.candidateLeadRequests.length, 0);
    assert.equal(harness.productionLeadRequests.length, 0);
    await assertClean(harness, '375-selected-tour-handoff');
  } finally {
    await harness.context.close();
  }

  const errorScenario = scenarioController('selected-tour-error');
  const errorHarness = await createHarness(browser, viewport, errorScenario);
  try {
    await renderSelectedTourOffer(errorHarness.page);
    await injectDeterministicApiFailure(errorHarness.page, 'tour', {
      code: 'FIXTURE_SELECTED_TOUR_ERROR',
      message: 'Fixture selected tour unavailable',
    });
    await errorHarness.page.locator(`button[data-tid="${fixture.failureFixtures.selectedTour.id}"]`).click();
    await errorHarness.page.waitForFunction(label => {
      const selected = document.getElementById('selectedTour');
      const sourceButton = document.querySelector('#results button[data-search3-production-label]');
      return !!selected?.querySelector('.retry-tour')
        && selected?.getAttribute('aria-busy') === 'false'
        && sourceButton?.disabled === false
        && sourceButton?.textContent.replace(/\s+/g, ' ').trim() === label;
    }, fixture.presentation.productionOwnedDirectTourText);
    const errorState = await errorHarness.page.evaluate(() => ({
      ariaBusy: document.getElementById('selectedTour')?.getAttribute('aria-busy') || '',
      retryVisible: !!document.querySelector('#selectedTour .retry-tour'),
      sourceText: document.querySelector('#results button[data-search3-production-label]')?.textContent.replace(/\s+/g, ' ').trim() || '',
      injectedTourCalls: Number(window.__search3InjectedFailureCalls?.tour || 0),
    }));
    assert.deepEqual(errorState, {
      ariaBusy: 'false',
      retryVisible: true,
      sourceText: fixture.presentation.productionOwnedDirectTourText,
      injectedTourCalls: 1,
    });
    manifest.presentationChecks.selectedTourHandoff.error = errorState;
    assert.equal(errorHarness.candidateLeadRequests.length, 0);
    assert.equal(errorHarness.productionLeadRequests.length, 0);
    await assertClean(errorHarness, 'selected-tour-error');
  } finally {
    await errorHarness.context.close();
  }
}

async function runDesktopSelectedPresentationEvidence(browser, manifest) {
  const viewport = fixture.viewports.find(item => item.width === 1440);
  const scenario = scenarioController('flight-empty');
  const harness = await createHarness(browser, viewport, scenario);
  try {
    await openSelectedFailureTour(harness.page);
    await harness.page.waitForFunction(() => /менеджер уточнит перелёт по заявке/i.test(
      document.querySelector('.tour-flights .selected-loading')?.textContent || ''
    ), null, { timeout: 12000 });
    await waitForSelectedPresentation(harness.page);
    await harness.page.locator('#selectedTour').evaluate(node => {
      node.scrollIntoView({ behavior: 'instant', block: 'start' });
    });
    await harness.page.waitForFunction(() => {
      const top = document.getElementById('selectedTour')?.getBoundingClientRect().top;
      return Number.isFinite(top) && top >= 0 && top <= 24;
    });
    const presentation = await selectedPresentationState(harness.page);
    assertSelectedPresentation(presentation, 1440);
    await writePresentationScreenshot(harness.page, '1440-selected-tour-presentation.png', manifest, {
      geometry: { viewportWidth: 1440, state: 'selected-tour-presentation' },
      selected: presentation,
    });
    manifest.presentationChecks.desktopSelectedTour = presentation;
    assert.equal(harness.candidateLeadRequests.length, 0);
    assert.equal(harness.productionLeadRequests.length, 0);
    await assertClean(harness, '1440-selected-tour-presentation');
  } finally {
    await harness.context.close();
  }
}

async function runMobileFilterEvidence(browser, manifest, width, filename, closeMode) {
  const viewport = fixture.viewports.find(item => item.width === width);
  const scenario = scenarioController(`mobile-filter-${width}`);
  const harness = await createHarness(browser, viewport, scenario);
  try {
    await loadCompleteResults(harness, scenario);
    const openButton = harness.page.locator('.search3-mobile-toolbar .mrf-open');
    await openButton.waitFor({ state: 'visible' });
    const touchHeight = await openButton.evaluate(node => node.getBoundingClientRect().height);
    assert.ok(touchHeight >= 44, `${width}: mobile filter target is too short`);
    const sortProxy = harness.page.locator('.search3-mobile-sort select');
    await sortProxy.focus();
    const sortFocus = await sortProxy.evaluate(node => {
      const label = node.closest('.search3-mobile-sort');
      const style = getComputedStyle(label);
      return {
        active: document.activeElement === node,
        outlineStyle: style.outlineStyle,
        outlineWidth: parseFloat(style.outlineWidth) || 0,
      };
    });
    assert.deepEqual(sortFocus, { active: true, outlineStyle: 'solid', outlineWidth: 3 });
    const beforeCalls = apiCallCount(scenario);
    await openButton.click();
    await harness.page.waitForSelector('.mrf-sheet.is-open .mrf-panel[role="dialog"]');
    await harness.page.waitForFunction(() => document.querySelector('.mrf-panel')?.contains(document.activeElement));
    const drawer = await harness.page.evaluate(expectedWidth => {
      const sheet = document.querySelector('.mrf-sheet');
      const panel = document.querySelector('.mrf-panel');
      const box = panel.getBoundingClientRect();
      return {
        width: Math.round(box.width),
        height: Math.round(box.height),
        viewportWidth: expectedWidth,
        open: sheet.classList.contains('is-open'),
        focusInside: panel.contains(document.activeElement),
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      };
    }, width);
    assert.ok(Math.abs(drawer.width - width) <= 1, `${width}: drawer must span the viewport width`);
    assert.ok(Math.abs(drawer.height - viewport.height) <= 2, `${width}: drawer must span the viewport height`);
    assert.equal(drawer.viewportWidth, width);
    assert.equal(drawer.open, true);
    assert.equal(drawer.focusInside, true);
    assert.equal(drawer.overflow, false);
    assert.equal(apiCallCount(scenario), beforeCalls, `${width}: opening filters must not request search data`);
    await writePresentationScreenshot(harness.page, filename, manifest, { drawer, closeMode });

    if (closeMode === 'escape') {
      await harness.page.keyboard.press('Escape');
    } else {
      await harness.page.locator('.mrf-backdrop').dispatchEvent('click');
    }
    await harness.page.waitForFunction(() => !document.querySelector('.mrf-sheet')?.classList.contains('is-open'));
    await harness.page.waitForFunction(() => document.activeElement?.classList.contains('mrf-open'));
    assert.equal(apiCallCount(scenario), beforeCalls, `${width}: closing filters must not request search data`);
    assert.deepEqual(await currentSearchValues(harness.page), fixture.search);
    manifest.presentationChecks.mobileFilters[String(width)] = {
      touchHeight,
      closeMode,
      focusReturned: true,
      sortFocusVisible: true,
      newSearchCalls: 0,
    };
    await assertClean(harness, `${width}-filter-presentation`);
  } finally {
    await harness.context.close();
  }
}

async function runPresentationEvidence(browser, manifest) {
  await runDesktopPresentationEvidence(browser, manifest);
  await runMobileExpandedOfferEvidence(browser, manifest);
  await runMobileSelectedHandoffEvidence(browser, manifest);
  await runDesktopSelectedPresentationEvidence(browser, manifest);
  await runMobileFilterEvidence(browser, manifest, 430, '430-filters-open.png', 'escape');
  await runMobileFilterEvidence(browser, manifest, 375, '375-filters-open.png', 'backdrop');
}

async function runPendingStatusRace(browser) {
  const scenario = scenarioController('pending-status');
  const harness = await createHarness(browser, fixture.viewports[0], scenario);
  try {
    await setSearchValues(harness.page);
    await harness.page.evaluate(() => window.V2SearchLifecycle.submit());
    const release = await waitFor(() => scenario.heldStatus, 'pending status response was not held');
    const changed = await harness.page.evaluate(() => window.V2SearchLifecycle.markDirty('search3_candidate_pending_status'));
    assert.equal(changed, true);
    await release();
    await harness.page.waitForTimeout(100);
    const state = await harness.page.evaluate(() => ({
      dirty: window.V2SearchLifecycle.dirty,
      pending: window.V2SearchLifecycle.pending,
      searchId: window.V2SearchLifecycle.searchId,
      cards: document.querySelectorAll('#results .hotel-card').length,
      dirtyState: document.querySelectorAll('.search-dirty-state').length,
    }));
    assert.deepEqual(state, { dirty: true, pending: false, searchId: 0, cards: 0, dirtyState: 1 });
    assert.equal(scenario.calls.filter(call => call.action === 'search_results').length, 0);
    await assertClean(harness, 'pending-status');
  } finally {
    await harness.context.close();
  }
}

async function runPendingResultsRace(browser) {
  const scenario = scenarioController('pending-results');
  const harness = await createHarness(browser, fixture.viewports[0], scenario);
  try {
    await setSearchValues(harness.page);
    await harness.page.evaluate(() => window.V2SearchLifecycle.submit());
    const release = await waitFor(() => scenario.heldResults, 'pending results response was not held');
    const changed = await harness.page.evaluate(() => window.V2SearchLifecycle.markDirty('search3_candidate_pending_results'));
    assert.equal(changed, true);
    await release();
    await harness.page.waitForTimeout(100);
    const state = await harness.page.evaluate(() => ({
      dirty: window.V2SearchLifecycle.dirty,
      pending: window.V2SearchLifecycle.pending,
      searchId: window.V2SearchLifecycle.searchId,
      cards: document.querySelectorAll('#results .hotel-card').length,
      dirtyState: document.querySelectorAll('.search-dirty-state').length,
    }));
    assert.deepEqual(state, { dirty: true, pending: false, searchId: 0, cards: 0, dirtyState: 1 });
    assert.equal(scenario.calls.filter(call => call.action === 'search_results').length, 1);
    await assertClean(harness, 'pending-results');
  } finally {
    await harness.context.close();
  }
}

async function currentSearchValues(page) {
  return page.evaluate(names => {
    const form = document.getElementById('tourSearch');
    return Object.fromEntries(names.map(name => [name, String(form.elements[name]?.value || '')]));
  }, Object.keys(fixture.search));
}

async function runSearchFailureFixtures(browser, manifest) {
  const emptyScenario = scenarioController('empty');
  const emptyHarness = await createHarness(browser, fixture.viewports[0], emptyScenario);
  try {
    await setSearchValues(emptyHarness.page);
    await emptyHarness.page.evaluate(() => window.V2SearchLifecycle.submit());
    await emptyHarness.page.waitForSelector('.search-progress-empty', { timeout: 12000 });
    assert.equal(await emptyHarness.page.locator('#results .hotel-card').count(), 0);
    assert.equal(await emptyHarness.page.locator('#results .empty').count(), 1);
    assert.deepEqual(await currentSearchValues(emptyHarness.page), fixture.search);
    const emptyActions = await emptyHarness.page.locator('.search-progress-empty-actions button').count();
    assert.ok(emptyActions >= 3, 'search-empty must expose recovery actions');
    recordBehavior(manifest, 'search-empty', {
      resultCount: 0,
      recoveryActions: emptyActions,
      parametersRetained: true,
    });
    await assertClean(emptyHarness, 'empty');
  } finally {
    await emptyHarness.context.close();
  }

  const timeoutScenario = scenarioController('search-timeout');
  const timeoutHarness = await createHarness(browser, fixture.viewports[0], timeoutScenario);
  try {
    await setSearchValues(timeoutHarness.page);
    await injectDeterministicApiFailure(timeoutHarness.page, 'search_status', {
      code: fixture.failureFixtures.search.timeoutCode,
      message: fixture.failureFixtures.search.timeoutMessage,
    });
    await timeoutHarness.page.evaluate(() => window.V2SearchLifecycle.submit());
    await timeoutHarness.page.waitForSelector('.search-progress-error[role="alert"]', { timeout: 12000 });
    const timeoutState = await timeoutHarness.page.evaluate(() => ({
      pending: window.V2SearchLifecycle.pending,
      searchId: window.V2SearchLifecycle.searchId,
      retry: !!document.querySelector('.search-progress-retry'),
      submitEnabled: !document.querySelector('#tourSearch .search-submit')?.disabled,
      resultCount: document.querySelectorAll('#results .hotel-card').length,
      text: document.querySelector('.search-progress-error')?.textContent.replace(/\s+/g, ' ').trim() || '',
    }));
    assert.equal(timeoutState.pending, false);
    assert.equal(timeoutState.searchId, fixture.failureFixtures.search.timeoutSearchId);
    assert.equal(timeoutState.retry, true);
    assert.equal(timeoutState.submitEnabled, true);
    assert.equal(timeoutState.resultCount, 0);
    assert.match(timeoutState.text, /Tourvisor отвечает дольше обычного/);
    assert.deepEqual(await currentSearchValues(timeoutHarness.page), fixture.search);
    const timeoutCalls = await injectedFailureCalls(timeoutHarness.page, 'search_status');
    assert.equal(timeoutCalls, 1, 'TIMEOUT must not be automatically retried');
    recordBehavior(manifest, 'search-timeout', {
      injectedCalls: timeoutCalls,
      retryVisible: true,
      parametersRetained: true,
    });
    await assertClean(timeoutHarness, 'search-timeout');
  } finally {
    await timeoutHarness.context.close();
  }

  const upstreamScenario = scenarioController('search-upstream-error');
  const upstreamHarness = await createHarness(browser, fixture.viewports[0], upstreamScenario);
  try {
    await setSearchValues(upstreamHarness.page);
    await upstreamHarness.page.evaluate(() => window.V2SearchLifecycle.submit());
    await upstreamHarness.page.waitForSelector('.search-progress-error[role="alert"]', { timeout: 12000 });
    const upstreamState = await upstreamHarness.page.evaluate(() => ({
      pending: window.V2SearchLifecycle.pending,
      searchId: window.V2SearchLifecycle.searchId,
      retry: !!document.querySelector('.search-progress-retry'),
      submitEnabled: !document.querySelector('#tourSearch .search-submit')?.disabled,
      text: document.querySelector('.search-progress-error')?.textContent.replace(/\s+/g, ' ').trim() || '',
    }));
    assert.deepEqual(upstreamState, {
      pending: false,
      searchId: 0,
      retry: true,
      submitEnabled: true,
      text: upstreamState.text,
    });
    assert.match(upstreamState.text, /Не удалось запустить поиск/);
    assert.deepEqual(await currentSearchValues(upstreamHarness.page), fixture.search);
    const upstreamCalls = upstreamScenario.calls.filter(call => call.action === 'search_start').length;
    assert.equal(upstreamCalls, 1, 'search_start upstream failures must not be automatically retried');
    const expectedHttpErrors = consumeExpectedApiHttpFailures(
      upstreamHarness,
      'search_start',
      fixture.failureFixtures.search.upstreamStatus,
      upstreamCalls,
      'search-upstream-error'
    );
    recordBehavior(manifest, 'search-upstream-error', {
      httpStatus: fixture.failureFixtures.search.upstreamStatus,
      actionCalls: upstreamCalls,
      expectedHttpErrorResponses: expectedHttpErrors.responses,
      expectedHttpConsoleErrors: expectedHttpErrors.consoleErrors,
      retryVisible: true,
      parametersRetained: true,
    });
    await assertClean(upstreamHarness, 'search-upstream-error');
  } finally {
    await upstreamHarness.context.close();
  }

  // Preserve the scaffold's original semantic start-error coverage as a separate guard.
  const errorScenario = scenarioController('error-start');
  const errorHarness = await createHarness(browser, fixture.viewports[0], errorScenario);
  try {
    await setSearchValues(errorHarness.page);
    await errorHarness.page.evaluate(() => window.V2SearchLifecycle.submit());
    await errorHarness.page.waitForSelector('.search-progress-error[role="alert"]');
    assert.match(await errorHarness.page.locator('.search-progress-error').innerText(), /Не получилось завершить поиск/);
    assert.equal(errorScenario.calls.filter(call => call.action === 'search_start').length, 1);
    await assertClean(errorHarness, 'error');
  } finally {
    await errorHarness.context.close();
  }
}

function selectedTourResult() {
  const tour = fixture.failureFixtures.selectedTour;
  return {
    ...tour.hotel,
    price: tour.price,
    tours: [{
      id: tour.id,
      price: tour.price,
      date: tour.date,
      nights: tour.nights,
      meal: tour.meal,
      roomType: tour.roomType,
      placement: tour.placement,
      operator: tour.operator,
      isCharter: tour.isCharter,
    }],
  };
}

async function openSelectedFailureTour(page) {
  await page.evaluate(({ result, searchId }) => {
    window.V2Runtime.setSearchId(searchId);
    window.V2Results.render([result]);
  }, {
    result: selectedTourResult(),
    searchId: fixture.failureFixtures.selectedTour.searchId,
  });
  await page.locator('.search3-show-tours').click();
  await page.waitForFunction(() => !document.querySelector('#results .hotel-tours')?.hidden);
  await page.locator(`.direct-tour[data-tid="${fixture.failureFixtures.selectedTour.id}"]`).click();
  await page.waitForFunction(tourId => (
    window.V2TourController?.currentTour?.id === tourId
    && !!document.querySelector('#selectedTour .lead-form')
  ), fixture.failureFixtures.selectedTour.id, { timeout: 12000 });
  const disclosureState = await page.evaluate(() => ({
    openCards: document.querySelectorAll('#results .hotel-card.search3-tours-open').length,
    visibleTourGroups: [...document.querySelectorAll('#results .hotel-tours')].filter(node => !node.hidden).length,
  }));
  assert.deepEqual(
    disclosureState,
    { openCards: 0, visibleTourGroups: 0 },
    'selecting a tour must collapse candidate disclosure before returning to results',
  );
}

async function selectedFailureState(page) {
  return page.evaluate(() => {
    const selected = document.getElementById('selectedTour');
    const lead = selected?.querySelector('.lead-form');
    const submit = lead?.querySelector('button[type="submit"]');
    return {
      tourId: String(window.V2TourController?.currentTour?.id || ''),
      selectedVisible: !!selected && !selected.hidden,
      leadVisible: !!lead && !lead.hidden,
      leadSubmitEnabled: !!submit && !submit.disabled,
      leadSummary: selected?.querySelector('.lead-selection-summary')?.textContent.replace(/\s+/g, ' ').trim() || '',
      fallbackText: selected?.querySelector('.tour-flights .selected-loading')?.textContent.replace(/\s+/g, ' ').trim() || '',
      flightError: selected?.querySelector('.tour-flights .flight-error')?.textContent.replace(/\s+/g, ' ').trim() || '',
      flightErrorRole: selected?.querySelector('.tour-flights .flight-error')?.getAttribute('role') || '',
      retryVisible: !!selected?.querySelector('.tour-flights .load-flights'),
      selectedPrice: selected?.querySelector('.selected-price')?.textContent.replace(/\s+/g, ' ').trim() || '',
    };
  });
}

function assertConversionShellPreserved(state) {
  assert.equal(state.tourId, fixture.failureFixtures.selectedTour.id);
  assert.equal(state.selectedVisible, true);
  assert.equal(state.leadVisible, true);
  assert.equal(state.leadSubmitEnabled, true);
  assert.match(state.selectedPrice, /101[\s\u00a0]?000/);
  assert.equal(state.retryVisible, true);
}

async function runFlightFailureFixtures(browser, manifest) {
  let leadUiDetails = null;
  const emptyScenario = scenarioController('flight-empty');
  const emptyHarness = await createHarness(browser, fixture.viewports[0], emptyScenario);
  try {
    await openSelectedFailureTour(emptyHarness.page);
    await emptyHarness.page.waitForFunction(() => /\u043cенеджер уточнит перелёт по заявке/i.test(
      document.querySelector('.tour-flights .selected-loading')?.textContent || ''
    ), null, { timeout: 12000 });
    let emptyState = await selectedFailureState(emptyHarness.page);
    assertConversionShellPreserved(emptyState);
    assert.match(emptyState.fallbackText, /менеджер уточнит перелёт по заявке/i);
    assert.match(emptyState.leadSummary, /Рейс\s*Не выбран/i);
    assert.equal(emptyScenario.calls.filter(call => call.action === 'flights').length, 1);

    await emptyHarness.page.locator('.tour-flights .load-flights').click();
    await emptyHarness.page.waitForFunction(() => /\u043cенеджер уточнит перелёт по заявке/i.test(
      document.querySelector('.tour-flights .selected-loading')?.textContent || ''
    ), null, { timeout: 12000 });
    emptyState = await selectedFailureState(emptyHarness.page);
    assertConversionShellPreserved(emptyState);
    const emptyCalls = emptyScenario.calls.filter(call => call.action === 'flights').length;
    assert.equal(emptyCalls, 2, 'empty flights retry must request flights exactly once more');
    recordBehavior(manifest, 'flight-empty', {
      actionCallsAfterRetry: emptyCalls,
      retryVisible: true,
      selectedTourPreserved: true,
      managerClarificationVisible: true,
    });
    leadUiDetails = {
      leadVisible: true,
      leadSubmitEnabled: true,
      candidateLeadRequests: emptyHarness.candidateLeadRequests.length,
      productionLeadRequests: emptyHarness.productionLeadRequests.length,
    };
    await assertClean(emptyHarness, 'flight-empty');
  } finally {
    await emptyHarness.context.close();
  }

  const timeoutScenario = scenarioController('flight-timeout');
  const timeoutHarness = await createHarness(browser, fixture.viewports[0], timeoutScenario);
  try {
    await injectDeterministicApiFailure(timeoutHarness.page, 'flights', {
      code: fixture.failureFixtures.flights.timeoutCode,
      message: fixture.failureFixtures.flights.timeoutMessage,
    });
    await openSelectedFailureTour(timeoutHarness.page);
    await timeoutHarness.page.waitForSelector('.tour-flights .flight-error[role="alert"]');
    const timeoutState = await selectedFailureState(timeoutHarness.page);
    assertConversionShellPreserved(timeoutState);
    assert.match(timeoutState.flightError, /Не удалось загрузить варианты рейсов/);
    assert.equal(timeoutState.flightErrorRole, 'alert');
    const timeoutCalls = await injectedFailureCalls(timeoutHarness.page, 'flights');
    assert.equal(timeoutCalls, 1, 'flight TIMEOUT must not be automatically retried');
    recordBehavior(manifest, 'flight-timeout', {
      injectedCalls: timeoutCalls,
      retryVisible: true,
      selectedTourPreserved: true,
      leadVisible: true,
    });
    await assertClean(timeoutHarness, 'flight-timeout');
  } finally {
    await timeoutHarness.context.close();
  }

  const upstreamScenario = scenarioController('flight-upstream-error');
  const upstreamHarness = await createHarness(browser, fixture.viewports[0], upstreamScenario);
  try {
    await openSelectedFailureTour(upstreamHarness.page);
    await upstreamHarness.page.waitForSelector('.tour-flights .flight-error[role="alert"]', { timeout: 12000 });
    const upstreamState = await selectedFailureState(upstreamHarness.page);
    assertConversionShellPreserved(upstreamState);
    assert.match(upstreamState.flightError, /Не удалось загрузить варианты рейсов/);
    assert.equal(upstreamState.flightErrorRole, 'alert');
    const upstreamCalls = upstreamScenario.calls.filter(call => call.action === 'flights').length;
    assert.equal(upstreamCalls, fixture.failureFixtures.flights.upstreamAttempts);
    const expectedHttpErrors = consumeExpectedApiHttpFailures(
      upstreamHarness,
      'flights',
      fixture.failureFixtures.flights.upstreamStatus,
      upstreamCalls,
      'flight-upstream-error'
    );
    recordBehavior(manifest, 'flight-upstream-error', {
      httpStatus: fixture.failureFixtures.flights.upstreamStatus,
      actionCalls: upstreamCalls,
      expectedHttpErrorResponses: expectedHttpErrors.responses,
      expectedHttpConsoleErrors: expectedHttpErrors.consoleErrors,
      retryVisible: true,
      selectedTourPreserved: true,
      leadVisible: true,
    });
    await assertClean(upstreamHarness, 'flight-upstream-error');
  } finally {
    await upstreamHarness.context.close();
  }

  assert.ok(leadUiDetails, 'lead UI evidence missing from flight-empty state');
  recordBehavior(manifest, 'lead-ui-no-delivery', leadUiDetails);
}

(async () => {
  await assertPreviewLeadGuard();
  const manifest = {
    schemaVersion: 2,
    visualTier: visualTierName,
    sourceSha,
    testedSha,
    workflowRunId,
    workflowRunAttempt,
    route: fixture.route,
    canonical: fixture.canonical,
    visualBaseline: fixture.visualBaseline,
    presentation: fixture.presentation,
    presentationAssets: presentationAssetEvidence(),
    widths: fixture.viewports.map(item => item.width),
    states: expectedStates,
    environment: {
      browser: 'chromium',
      browserVersion: '',
      nodeVersion: process.version,
      platform: process.platform,
      architecture: process.arch,
      runnerOS: process.env.RUNNER_OS || '',
      runnerImageOS: process.env.ImageOS || '',
      runnerImageVersion: process.env.ImageVersion || '',
      ...contextProfile,
    },
    screenshots: [],
    presentationScreenshots: [],
    presentationChecks: { desktopLocalFilter: null, mobileFilters: {} },
    behaviorStates: [],
  };
  const candidateHost = new URL(baseUrl).hostname;
  assertCandidateDoesNotOwnDirectTour();
  const launchArgs = candidateHost === 'anytoour.ru'
    ? ['--host-resolver-rules=MAP anytoour.ru 127.0.0.1']
    : [];
  const browser = await chromium.launch({ headless: true, args: launchArgs });
  manifest.environment.browserVersion = browser.version();
  try {
    await runFiveWidthEvidence(browser, manifest);
    await runResponsiveFilterBoundaries(browser, manifest);
    await runPresentationEvidence(browser, manifest);
    if (visualTier.runRaces) {
      await runPendingStatusRace(browser);
      await runPendingResultsRace(browser);
    }
    if (visualTier.runFailureStates) {
      await runSearchFailureFixtures(browser, manifest);
      await runFlightFailureFixtures(browser, manifest);
    }
  } finally {
    await browser.close();
  }

  assert.equal(manifest.screenshots.length, expectedEvidenceScreenshotCount);
  assert.equal(new Set(manifest.screenshots.map(item => item.file)).size, expectedEvidenceScreenshotCount);
  assert.equal(manifest.presentationScreenshots.length, 6);
  assert.deepEqual(manifest.presentationScreenshots.map(item => item.file), expectedPresentationCaptures);
  assert.equal(new Set(manifest.presentationScreenshots.map(item => item.file)).size, 6);
  assert.equal(manifest.presentation.status, 'REFERENCE_IMPLEMENTATION_IN_PROGRESS');
  assert.equal(manifest.presentation.approvedPixelsCompared, false);
  assert.deepEqual(
    manifest.behaviorStates.map(item => item.name),
    visualTier.runFailureStates ? fixture.failureFixtures.states : [],
  );
  assert.equal(manifest.behaviorStates.every(item => item.passed === true), true);
  for (const item of manifest.screenshots) {
    const bytes = fs.readFileSync(path.join(outputDir, item.file));
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), item.sha256);
  }
  for (const item of manifest.presentationScreenshots) {
    const bytes = fs.readFileSync(path.join(outputDir, item.file));
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), item.sha256);
  }
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
  console.log(
    `SEARCH3_CANDIDATE_SCAFFOLD_OK tier=${visualTierName} widths=375,430,768,1024,1440 `
    + `screenshots=${expectedEvidenceScreenshotCount + expectedPresentationCaptures.length} `
    + 'races=status,results behaviorStates=7 presentation=REFERENCE_IMPLEMENTATION_IN_PROGRESS',
  );
})().catch(error => {
  console.error(error);
  process.exit(1);
});
