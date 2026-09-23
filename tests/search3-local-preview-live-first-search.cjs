'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const TARGET = process.env.SEARCH3_LOCAL_LIVE_URL
  || 'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/';
const OUTPUT = process.env.SEARCH3_LOCAL_LIVE_OUTPUT || 'search3-local-live-first-search';

function endpointKind(url) {
  const u = new URL(url);
  if (u.pathname === '/api-v2.php') return 'tourvisor:' + (u.searchParams.get('action') || 'unknown');
  if (u.pathname.endsWith('/api-anex-search3-preview.php')) return 'anex';
  if (u.pathname.endsWith('/api-andromeda-search3-preview.php')) return 'andromeda';
  if (u.pathname.endsWith('/data/search3-local-results-read-v1.php')) return 'local';
  return null;
}

(async () => {
  fs.mkdirSync(OUTPUT, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    extraHTTPHeaders: { 'X-AnyTour-CI': '1' }
  });
  await context.route(/https?:\/\/mc\.yandex\.(ru|com)\//, route => route.abort());

  const page = await context.newPage();
  const requests = [];
  const responses = [];
  const pageErrors = [];
  const leadWrites = [];

  page.on('pageerror', e => pageErrors.push(String(e)));
  page.on('request', request => {
    const kind = endpointKind(request.url());
    if (kind) requests.push({ kind, method: request.method(), url: request.url() });
    if (request.method() === 'POST' && /lead/i.test(new URL(request.url()).pathname)) {
      leadWrites.push({ method: request.method(), url: request.url() });
    }
  });
  page.on('response', response => {
    const kind = endpointKind(response.url());
    if (kind) responses.push({ kind, status: response.status(), url: response.url() });
  });

  const url = new URL(TARGET);
  for (const [k,v] of Object.entries({
    origin:'Москва',
    country:'4',
    from:'2026-09-29',
    to:'2026-10-05',
    minNights:'7',
    maxNights:'7',
    adults:'2',
    ages:''
  })) url.searchParams.set(k,v);

  const nav = await page.goto(url.toString(), { waitUntil:'domcontentloaded', timeout:45000 });
  assert(nav && nav.status() === 200, 'preview page must return HTTP200');
  await page.locator('.search-submit:not([disabled])').waitFor({ timeout:45000 });

  await page.locator('.search-submit').click();

  await page.waitForFunction(() => {
    const raw = document.querySelector('#results')?.dataset.searchReceipt;
    if (!raw) return false;
    try {
      const receipt = JSON.parse(raw);
      return ['complete','partial','error'].includes(receipt.phase);
    } catch {
      return false;
    }
  }, { timeout: 150000 });

  const receipt = await page.evaluate(() => JSON.parse(document.querySelector('#results').dataset.searchReceipt));
  const cardCount = await page.locator('.hotel-card').count();
  const summary = await page.locator('#results-summary').innerText().catch(()=>'');
  const statusText = await page.locator('#search-status').innerText().catch(()=>'');
  await page.screenshot({ path:path.join(OUTPUT,'first-search-1440.png'), fullPage:true, animations:'disabled' });

  const compactRequests = {};
  for (const row of requests) compactRequests[row.kind] = (compactRequests[row.kind] || 0) + 1;
  const compactResponses = {};
  for (const row of responses) {
    const key = row.kind + ':' + row.status;
    compactResponses[key] = (compactResponses[key] || 0) + 1;
  }

  const result = {
    ok: true,
    target: url.toString(),
    capturedAt: new Date().toISOString(),
    receipt,
    cardCount,
    summary,
    statusText,
    requestCounts: compactRequests,
    responseCounts: compactResponses,
    pageErrors,
    leadWrites
  };
  fs.writeFileSync(path.join(OUTPUT,'result.json'), JSON.stringify(result,null,2));
  console.log('SEARCH3_LIVE_FIRST_SEARCH_RESULT ' + JSON.stringify(result));
  assert.deepEqual(leadWrites, [], 'live diagnostic must not submit leads');
  assert.equal(pageErrors.length, 0, 'page errors: ' + pageErrors.join(' | '));

  await context.close();
  await browser.close();
})().catch(async error => {
  console.error(error);
  process.exit(1);
});
