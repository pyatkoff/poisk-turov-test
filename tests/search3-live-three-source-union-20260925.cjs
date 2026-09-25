'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const TARGET = 'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/';
const OUTPUT = process.env.SEARCH3_LIVE_OUTPUT || 'search3-live-three-source-union';
const CONTROL = {
  origin: 'Москва',
  country: '4',
  from: '2026-09-29',
  to: '2026-10-05',
  minNights: '7',
  maxNights: '7',
  adults: '2',
  ages: ''
};

function kind(url) {
  const u = new URL(url);
  if (u.pathname === '/api-v2.php') return 'tourvisor:' + (u.searchParams.get('action') || 'unknown');
  if (u.pathname.endsWith('/api-anex-search3-preview.php')) return 'anex';
  if (u.pathname.endsWith('/api-andromeda-search3-preview.php')) return 'andromeda';
  if (u.pathname.endsWith('/data/search3-local-results-read-v1.php')) return 'local-db';
  if (u.pathname.endsWith('/api-andromeda-quote-preview.php')) return 'andromeda-quote';
  if (/lead/i.test(u.pathname)) return 'lead';
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

  page.on('pageerror', error => pageErrors.push(String(error)));
  page.on('request', request => {
    const requestKind = kind(request.url());
    if (requestKind) requests.push({ kind: requestKind, method: request.method() });
    if (request.method() === 'POST' && requestKind === 'lead') {
      leadWrites.push({ method: request.method(), path: new URL(request.url()).pathname });
    }
  });
  page.on('response', response => {
    const responseKind = kind(response.url());
    if (responseKind) responses.push({ kind: responseKind, status: response.status() });
  });

  const url = new URL(TARGET);
  for (const [key, value] of Object.entries(CONTROL)) url.searchParams.set(key, value);

  const nav = await page.goto(url.toString(), { waitUntil: 'domcontentloaded', timeout: 45000 });
  assert(nav && nav.status() === 200, 'published preview must return HTTP 200');
  await page.locator('.search-submit:not([disabled])').waitFor({ timeout: 45000 });

  page.locator('.search-submit').click();

  await page.waitForFunction(() => {
    const raw = document.querySelector('#results')?.dataset.searchReceipt;
    if (!raw) return false;
    try {
      const receipt = JSON.parse(raw);
      return ['complete', 'partial', 'error'].includes(receipt.phase);
    } catch {
      return false;
    }
  }, null, { timeout: 180000 });

  const result = await page.evaluate(() => {
    const receipt = JSON.parse(document.querySelector('#results').dataset.searchReceipt);
    const owner = window.Search3CanonicalProfilesV1?.current?.();
    const rows = owner?.read?.(owner.source(), {}) || [];
    const tours = rows.flatMap(hotel => Array.isArray(hotel.tours) ? hotel.tours : []);
    const providers = [...new Set(tours.map(tour => String(tour.provider || '').toLowerCase()).filter(Boolean))].sort();
    const providerOffers = {};
    for (const tour of tours) {
      const provider = String(tour.provider || '').toLowerCase() || 'unknown';
      providerOffers[provider] = (providerOffers[provider] || 0) + 1;
    }
    return { receipt, canonicalHotels: rows.length, canonicalOffers: tours.length, providers, providerOffers };
  });

  const cardCount = await page.locator('.hotel-card').count();
  const summary = await page.locator('#results-summary').innerText().catch(() => '');
  const statusText = await page.locator('#search-status').innerText().catch(() => '');

  const requestCounts = {};
  for (const row of requests) requestCounts[row.kind] = (requestCounts[row.kind] || 0) + 1;
  const responseCounts = {};
  for (const row of responses) {
    const key = row.kind + ':' + row.status;
    responseCounts[key] = (responseCounts[key] || 0) + 1;
  }

  const safe = {
    capturedAt: new Date().toISOString(),
    target: url.toString(),
    phase: result.receipt?.phase ?? null,
    receipt: result.receipt,
    cardCount,
    canonicalHotels: result.canonicalHotels,
    canonicalOffers: result.canonicalOffers,
    providers: result.providers,
    providerOffers: result.providerOffers,
    requestCounts,
    responseCounts,
    summary,
    statusText,
    pageErrors,
    leadWrites
  };

  fs.writeFileSync(path.join(OUTPUT, 'result.json'), JSON.stringify(safe, null, 2));
  await page.screenshot({ path: path.join(OUTPUT, 'first-search-1440.png'), fullPage: false, animations: 'disabled' });
  console.log('SEARCH3_LIVE_THREE_SOURCE_UNION ' + JSON.stringify(safe));

  assert.equal(pageErrors.length, 0, 'page errors: ' + pageErrors.join(' | '));
  assert.deepEqual(leadWrites, [], 'control must not submit a lead');
  assert.equal(requestCounts['tourvisor:search_start'], 1, 'exactly one Tourvisor search may start');
  assert((requestCounts.anex || 0) >= 1, 'direct ANEX must be requested');
  assert((requestCounts.andromeda || 0) >= 1, 'direct Andromeda must be requested');
  assert.equal(requestCounts['andromeda-quote'] || 0, 0, 'search control must not quote/select a tour');
  assert(!result.providers.includes('local'), 'LOCAL stored offers must never enter live canonical inventory');
  assert(result.providers.every(provider => ['tourvisor', 'anex', 'andromeda'].includes(provider)),
    'live canonical providers must be Tourvisor/direct ANEX/Andromeda only: ' + result.providers.join(','));
  assert(cardCount > 0, 'published live search must render at least one hotel card');

  await context.close();
  await browser.close();
})().catch(error => {
  console.error(error);
  process.exit(1);
});
