'use strict';
// New recorder-only preflight. Every browser request is fulfilled in memory.
// No Search3 page, provider, production route, login or saved browser profile is used.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { chromium } = require('playwright');
const { attachRecorder } = require('./recorder.cjs');
const origin = 'https://capture.invalid';
const sourceSha = '041b339df4fcddbea8d015919a9de5a0bf3039aa';
let root;
const executablePath = process.env.RECORDER_CHROMIUM_EXECUTABLE;
if (executablePath && !path.isAbsolute(executablePath)) throw new Error('Browser executable must be an absolute path');
const html = '<!doctype html><meta charset="utf-8"><button id="run">Synthetic sequence</button>' +
  '<script>document.querySelector("#run").onclick=async()=>{' +
  'for(const query of ["action=search_start","action=search_status&searchId=42","action=search_results&searchId=42","action=tour&tourId=101","action=flights&tourId=101"])' +
  '{await fetch("/api-v2.php?"+query).then(r=>r.json());}document.body.dataset.done="yes";};</script>';
const payloads = {
  search_start: { searchId: 42 },
  search_continue: { searchId: 42 },
  search_status: { progress: 100, status: 'complete' },
  search_results: [{ id: 7, tours: [{ id: 101, price: 100 }] }],
  tour: { id: 101, price: 100, currency: 'RUB', fuelCharge: null, token: 'SYNTHETIC_PRIVATE_VALUE' },
  flights: [{ isDefault: true, price: { value: 150, currency: 'RUB' }, fuelCharge: 0,
    forward: [{ number: 'FIXTURE001' }], backward: [{ number: 'FIXTURE002' }] }]
};
(async () => {
  const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
  root = fs.mkdtempSync(path.join(__dirname, 'offline-evidence-'));
  const cases = [];
  try {
    for (const name of ['complete', 'duplicate_start', 'wrong_id', 'http_failure', 'deadline', 'unexpected_continue']) {
      const context = await browser.newContext({ viewport: { width: 430, height: 900 }, serviceWorkers: 'block' });
      let browserRequests = 0;
      // No route.continue/fetch/fallback exists: even an unexpected URL cannot escape.
      await context.route('**/*', async route => {
        browserRequests++;
        const url = new URL(route.request().url());
        if (url.origin !== origin) return route.abort();
        if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
        if (url.pathname !== '/api-v2.php') return route.abort();
        const action = url.searchParams.get('action');
        if (!Object.hasOwn(payloads, action)) return route.abort();
        if (name === 'http_failure' && action === 'search_start') {
          return route.fulfill({ status: 502, contentType: 'application/json', body: '{"error":"synthetic"}' });
        }
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify(payloads[action]) });
      });
      const page = await context.newPage();
      const recorder = attachRecorder(page, {
        directory: path.join(root, name), apiOrigin: origin, sourceSha,
        deadlineMs: name === 'deadline' ? 100 : 10000
      });
      try {
        await page.goto(origin + '/');
        if (name === 'deadline') {
          const closed = context.waitForEvent('close');
          recorder.arm();
          await closed;
        } else {
          recorder.arm();
          await page.locator('#run').click();
          await page.waitForFunction(() => document.body.dataset.done === 'yes');
          recorder.checkpoint('viewport', { width: 430, synthetic: true });
          await page.setViewportSize({ width: 1280, height: 900 });
          recorder.checkpoint('viewport', { width: 1280, synthetic: true });
          if (name === 'duplicate_start') {
            await page.evaluate(() => fetch('/api-v2.php?action=search_start').then(r => r.json()));
          }
          if (name === 'wrong_id') {
            await page.evaluate(() => fetch('/api-v2.php?action=search_status&searchId=99').then(r => r.json()));
          }
          if (name === 'unexpected_continue') {
            await page.evaluate(() => fetch('/api-v2.php?action=search_continue&searchId=42').then(r => r.json()));
          }
        }
        const result = await recorder.finish();
        if (name === 'complete') {
          assert.equal(result.oneSuccessfulSearchStart, true);
          assert.equal(result.sameIdUsedAfterStart, true);
          assert.equal(result.terminalStatusCaptured, true);
          assert.equal(result.originalTourAndFlightsPairCaptured, true);
          assert.equal(result.firstSearchId, '42');
          const tour = JSON.parse(fs.readFileSync(path.join(root, name, '004-tour.json')));
          const flights = JSON.parse(fs.readFileSync(path.join(root, name, '005-flights.json')));
          assert.equal(tour.price, 100);
          assert.equal(tour.fuelCharge, null);
          assert.equal(Object.hasOwn(tour, 'token'), false);
          assert.equal(flights[0].price.value, 150);
          assert.equal(flights[0].fuelCharge, 0);
        }
        if (name === 'duplicate_start') {
          assert.equal(result.requestCounts.search_start, 2);
          assert.equal(result.oneSuccessfulSearchStart, false);
        }
        if (name === 'wrong_id') assert.equal(result.sameIdUsedAfterStart, false);
        if (name === 'unexpected_continue') {
          assert.equal(result.requestCounts.search_continue, 1);
          assert.equal(result.noSearchContinueObserved, false);
        }
        if (name === 'http_failure') assert.equal(result.oneSuccessfulSearchStart, false);
        if (name === 'deadline') {
          assert.equal(result.stopReason, 'deadline');
          assert.equal(result.requestCounts.search_start, 0);
          assert.equal(result.oneSuccessfulSearchStart, false);
        }
        assert.equal(result.responseCaptureErrors, 0);
        cases.push({ name, passed: true, browserRequests, externallyForwardedRequests: 0 });
      } finally { await context.close(); }
    }
  } finally { await browser.close(); }
  const report = {
    generatedAt: new Date().toISOString(), status: 'OFFLINE_RECORDER_PREFLIGHT_PASSED',
    playwrightVersion: require('playwright/package.json').version,
    toolSourceSha: process.env.CAPTURE_TOOL_SOURCE_SHA || null,
    recorderSha256: crypto.createHash('sha256').update(fs.readFileSync(path.join(__dirname, 'recorder.cjs'))).digest('hex'),
    verifierSha256: crypto.createHash('sha256').update(fs.readFileSync(__filename)).digest('hex'),
    browserExecutable: executablePath || 'Playwright-managed Chromium',
    browserVersion: browser.version(),
    sourceSha, cases, supplierCalls: 0, productionRequests: 0, Search3ChecksRepeated: 0,
    liveCaptureValidated: false, policyBypass: false,
    scope: 'Synthetic pages only; fulfilled in memory. This is not Search3 acceptance.'
  };
  fs.writeFileSync(path.join(root, 'preflight.json'), JSON.stringify(report, null, 2) + '\n');
  console.log(JSON.stringify(report));
})().catch(error => { console.error(error.message); process.exitCode = 1; });
