'use strict';

const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
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
const outputDir = path.resolve(process.env.SEARCH3_ARTIFACT_DIR || 'search3-candidate-artifacts');
const expectedStates = ['initial', 'progressive-25', 'final-100'];
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

assert.equal(fixture.schemaVersion, 1);
assert.deepEqual(fixture.viewports.map(item => item.width), [375, 430, 768, 1024, 1440]);
assert.equal(fixture.progressive.firstLimit, 25);
assert.equal(fixture.progressive.finalLimit, 100);
assert.deepEqual(fixture.visualBaseline, {
  status: 'BLOCKED_MISSING_APPROVED_DESIGN_PIXELS',
  baselineCompared: false,
  ownerVisualApproval: false,
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
        if (name === 'error-start') return responseJson(route, {});
        const id = name === 'pending-status'
          ? fixture.races.pendingStatusSearchId
          : name === 'pending-results'
            ? fixture.races.pendingResultsSearchId
            : name === 'empty'
              ? fixture.emptySearchId
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
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', error => pageErrors.push(String(error)));
  page.on('request', request => {
    if (new URL(request.url()).pathname.endsWith('/lead-adapter-v2.php')) productionLeadRequests.push(request.url());
  });
  page.on('requestfailed', request => {
    if (!/^https:\/\/mc\.yandex\.(?:ru|com)\//.test(request.url())) {
      unexpectedFailures.push(`${request.method()} ${request.url()} ${request.failure() && request.failure().errorText}`);
    }
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
  assert.equal(new URL(page.url()).pathname, fixture.route);

  const leadGuard = await page.evaluate(async () => {
    const response = await fetch(window.V2_CONFIG.leadApi, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ synthetic: true }),
    });
    return { status: response.status, body: await response.json() };
  });
  assert.deepEqual(leadGuard, {
    status: 403,
    body: { ok: false, error: 'PREVIEW_LEAD_DISABLED' },
  });

  return {
    context,
    page,
    consoleErrors,
    pageErrors,
    unexpectedFailures,
    unexpectedOutbound,
    productionLeadRequests,
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
    const cards = document.querySelectorAll('#results .hotel-card');
    const h1 = document.querySelector('h1');
    const status = document.getElementById('status');
    const documentWidth = document.documentElement.scrollWidth;
    const bodyWidth = document.body.scrollWidth;
    const clientWidth = document.documentElement.clientWidth;
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
      firstResult: rect(cards[0]),
    };
  }, { expectedWidth: width, expectedState: state });
}

async function capture(page, width, state, expectedResultCount, manifest) {
  assert.ok(expectedStates.includes(state));
  const filename = `${width}-${state}.png`;
  const absolute = path.join(outputDir, filename);
  await page.screenshot({ path: absolute, animations: 'disabled' });
  const bytes = fs.readFileSync(absolute);
  const measured = await geometry(page, width, state);
  assert.equal(measured.h1Count, 1);
  assert.equal(measured.resultCount, expectedResultCount, `${width}/${state}: result count drifted before capture`);
  assert.equal(measured.horizontalOverflow, false, `${width}/${state}: horizontal overflow`);
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
  assert.equal(new URL(harness.page.url()).pathname, fixture.route, `${label}: URL changed`);
}

async function runFiveWidthEvidence(browser, manifest) {
  for (const viewport of fixture.viewports) {
    const scenario = scenarioController('progressive');
    const harness = await createHarness(browser, viewport, scenario);
    try {
      await setSearchValues(harness.page);
      await capture(harness.page, viewport.width, 'initial', 0, manifest);

      await harness.page.evaluate(() => window.V2SearchLifecycle.submit());
      await harness.page.waitForFunction(count => document.querySelectorAll('#results .hotel-card').length === count, fixture.progressive.firstLimit, { timeout: 12000 });
      await waitFor(() => scenario.heldFinalStatus, `${viewport.width}: final status response was not held`);
      await harness.page.locator('#status').scrollIntoViewIfNeeded();
      await capture(harness.page, viewport.width, 'progressive-25', fixture.progressive.firstLimit, manifest);

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

async function runEmptyAndError(browser) {
  const emptyScenario = scenarioController('empty');
  const emptyHarness = await createHarness(browser, fixture.viewports[0], emptyScenario);
  try {
    await setSearchValues(emptyHarness.page);
    await emptyHarness.page.evaluate(() => window.V2SearchLifecycle.submit());
    await emptyHarness.page.waitForSelector('.search-progress-empty', { timeout: 12000 });
    assert.equal(await emptyHarness.page.locator('#results .hotel-card').count(), 0);
    assert.equal(await emptyHarness.page.locator('#results .empty').count(), 1);
    await assertClean(emptyHarness, 'empty');
  } finally {
    await emptyHarness.context.close();
  }

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

(async () => {
  const manifest = {
    schemaVersion: 1,
    sourceSha,
    testedSha,
    workflowRunId,
    workflowRunAttempt,
    route: fixture.route,
    canonical: fixture.canonical,
    visualBaseline: fixture.visualBaseline,
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
  };
  const candidateHost = new URL(baseUrl).hostname;
  const launchArgs = candidateHost === 'anytoour.ru'
    ? ['--host-resolver-rules=MAP anytoour.ru 127.0.0.1']
    : [];
  const browser = await chromium.launch({ headless: true, args: launchArgs });
  manifest.environment.browserVersion = browser.version();
  try {
    await runFiveWidthEvidence(browser, manifest);
    await runPendingStatusRace(browser);
    await runPendingResultsRace(browser);
    await runEmptyAndError(browser);
  } finally {
    await browser.close();
  }

  assert.equal(manifest.screenshots.length, 15);
  assert.equal(new Set(manifest.screenshots.map(item => item.file)).size, 15);
  for (const item of manifest.screenshots) {
    const bytes = fs.readFileSync(path.join(outputDir, item.file));
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), item.sha256);
  }
  fs.writeFileSync(path.join(outputDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
  console.log('SEARCH3_CANDIDATE_SCAFFOLD_OK widths=375,430,768,1024,1440 screenshots=15 races=status,results states=empty,error');
})().catch(error => {
  console.error(error);
  process.exit(1);
});
