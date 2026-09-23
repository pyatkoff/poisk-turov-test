'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const TARGET = process.env.SEARCH3_LOCAL_LIVE_URL
  || 'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/';
const OUTPUT = process.env.SEARCH3_LOCAL_LIVE_OUTPUT || 'search3-local-live-readonly-evidence';

function isoDay(offset) {
  const d = new Date();
  d.setUTCHours(12, 0, 0, 0);
  d.setUTCDate(d.getUTCDate() + offset);
  return d.toISOString().slice(0, 10);
}

function isProviderSearch(request) {
  const url = new URL(request.url());
  const path = url.pathname.toLowerCase();
  const body = request.postData() || '';
  return path.includes('api-anex-search3-preview.php')
    || path.includes('api-andromeda-search3-preview.php')
    || /(?:^|[=&"])search_(?:start|continue|results|status)(?:[=&"]|$)/i.test(url.search + '&' + body);
}

function isLeadWrite(request) {
  if (request.method() !== 'POST') return false;
  const path = new URL(request.url()).pathname.toLowerCase();
  return path.includes('/lead-') || path.includes('/lead/');
}

async function auditWidth(browser, width) {
  fs.mkdirSync(OUTPUT, { recursive: true });
  const from = isoDay(8);
  const to = isoDay(14);
  const url = new URL(TARGET);
  for (const [key, value] of Object.entries({
    origin: 'Москва',
    country: '4',
    from,
    to,
    minNights: '7',
    maxNights: '7',
    adults: '1',
    ages: '7,3'
  })) url.searchParams.set(key, value);

  const providerSearches = [];
  const leadWrites = [];
  const observationRequests = [];
  const pageErrors = [];
  const context = await browser.newContext({
    viewport: { width, height: 900 },
    extraHTTPHeaders: { 'X-AnyTour-CI': '1' }
  });
  await context.route(/https?:\/\/mc\.yandex\.(ru|com)\//, route => route.abort());

  const page = await context.newPage();
  page.on('pageerror', error => pageErrors.push(String(error)));
  page.on('request', request => {
    if (isProviderSearch(request)) providerSearches.push({ method: request.method(), url: request.url() });
    if (isLeadWrite(request)) leadWrites.push({ method: request.method(), url: request.url() });
    const u = new URL(request.url());
    if (u.pathname === '/data/price-calendar-read-v1.php') {
      observationRequests.push(Object.fromEntries(u.searchParams.entries()));
    }
  });

  const response = await page.goto(url.toString(), { waitUntil: 'domcontentloaded', timeout: 45000 });
  assert(response && response.status() === 200, 'live preview must return HTTP 200');
  await page.locator('.search-submit:not([disabled])').waitFor({ timeout: 45000 });

  const catalogueEndpoint = new URL('/_preview/search3-local-candidate/data/search3-local-results-read-v1.php', TARGET);
  const directCatalogueResponse = await context.request.post(catalogueEndpoint.toString(), {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'AnyTourSearch3',
      'Cache-Control': 'no-cache'
    },
    data: { action: 'meal_catalog', provider: 'tourvisor', scopeKey: 'global' }
  });
  let directCatalogueBody = null;
  try { directCatalogueBody = await directCatalogueResponse.json(); } catch { directCatalogueBody = { raw: await directCatalogueResponse.text() }; }
  const directCatalogue = directCatalogueBody?.data || null;
  const catalogue = await page.evaluate(() => ({
    available: window.AnyTourPrototypeData?.catalog?.mealPlanAvailable,
    revision: window.AnyTourPrototypeData?.catalog?.mealPlanRevision,
    plans: (window.AnyTourPrototypeData?.catalog?.mealPlans || []).map(p => ({
      id: p.id, code: p.code, nameRu: p.nameRu, nativeIds: [...p.nativeIds]
    }))
  }));
  fs.writeFileSync(path.join(OUTPUT, 'catalogue-' + width + '.json'), JSON.stringify({
    endpointStatus: directCatalogueResponse.status(),
    endpointBody: directCatalogueBody,
    browserCatalogue: catalogue,
    pageErrors
  }, null, 2));
  assert.equal(directCatalogueResponse.status(), 200, 'live meal catalogue action HTTP status');
  assert.equal(directCatalogueBody?.ok, true, 'live meal catalogue action must return ok=true');
  assert.equal(directCatalogue?.available, true, 'live meal catalogue action must expose an installed read authority');
  assert.equal(catalogue.available, true, 'browser canonical meal catalogue must be available');
  const nativeBacked = catalogue.plans.filter(p => p.nativeIds.length).map(p => ({
    id: p.id, code: p.code, nameRu: p.nameRu, nativeIds: p.nativeIds
  }));
  assert.deepEqual(nativeBacked, [
    { id: 2, code: 'breakfast', nameRu: 'Завтраки', nativeIds: ['3'] },
    { id: 3, code: 'half-board', nameRu: 'Полупансион', nativeIds: ['4'] },
    { id: 7, code: 'all-inclusive', nameRu: 'Всё включено', nativeIds: ['7'] },
    { id: 8, code: 'ultra-all-inclusive', nameRu: 'Ультра всё включено', nativeIds: ['9'] }
  ], 'live reviewed Tourvisor native meal mappings must be exactly the four proven values');

  await page.locator('#quick-meal').click();
  await page.locator('#modal-body input[data-meal-choice]').first().waitFor();
  const choices = await page.locator('#modal-body input[data-meal-choice]').evaluateAll(
    nodes => nodes.map(node => node.value)
  );
  const canonicalChoices = choices.filter(Boolean).sort((a, b) => a.localeCompare(b, 'ru'));
  assert.deepEqual(canonicalChoices, ['Всё включено', 'Завтраки', 'Полупансион', 'Ультра всё включено'].sort((a,b)=>a.localeCompare(b,'ru')));
  assert.equal(choices.filter(v => v === '').length, 1, 'exactly one "any meal" choice expected');

  const forbidden = [
    'Room Only', 'Room only', 'AO', 'breakfast', 'ULTRA ALL', 'On Request',
    'Fame Style All Inclusive', 'Ultimate All Inclusive', 'Soft All Inclusive',
    'Premium Ultra All Inclusive', 'Ultra All Exclusive', 'Premium All Inclusive',
    'High Class All Inclusive', 'Luxury All Inclusive', 'Local&Healthy Ultra All Inclusive'
  ];
  for (const raw of forbidden) {
    assert.equal(choices.includes(raw), false, 'raw/provider meal leaked into top-level facet: ' + raw);
  }

  fs.mkdirSync(OUTPUT, { recursive: true });
  await page.screenshot({ path: path.join(OUTPUT, 'meal-' + width + '.png'), fullPage: true, animations: 'disabled' });
  await page.locator('[data-action="close-modal"]').click();

  const observationResponse = page.waitForResponse(resp => {
    try {
      const request = resp.request();
      const body = request.postDataJSON();
      return new URL(resp.url()).pathname.endsWith('/data/search3-local-results-read-v1.php')
        && request.method() === 'POST' && body?.action === 'price_calendar';
    } catch { return false; }
  }, { timeout: 30000 });
  await page.locator('[data-action="dates"]').first().click();
  const observed = await observationResponse;
  assert.equal(observed.status(), 200, 'live guarded observation calendar action must return HTTP 200');
  const observedRequest = observed.request().postDataJSON();
  assert.equal(observedRequest.adults, 1);
  assert.deepEqual(observedRequest.childs, [3, 7]);
  const observedPayload = await observed.json();
  assert.equal(observedPayload.ok, true);
  const observedBody = observedPayload.data;
  assert.equal(observedBody.ok, true);
  assert.equal(observedBody.adults, 1);
  assert.equal(observedBody.childrenCount, 2);
  assert.deepEqual(observedBody.childAges, [3, 7]);
  assert.equal(observedBody.childAgesSignature, '3,7');

  await page.screenshot({ path: path.join(OUTPUT, 'calendar-' + width + '.png'), fullPage: true, animations: 'disabled' });
  await page.waitForTimeout(500);

  assert.deepEqual(providerSearches, [], 'read-only live acceptance must not start a supplier search');
  assert.deepEqual(leadWrites, [], 'read-only live acceptance must not submit a lead');
  assert.equal(pageErrors.length, 0, 'live page errors: ' + pageErrors.join(' | '));

  await context.close();
  return {
    width,
    url: url.toString(),
    canonicalChoices,
    nativeBacked,
    observationRequestCount: observationRequests.length,
    observationRequest: observationRequests[0] || null,
    observationParty: {
      adults: observedBody.adults,
      childrenCount: observedBody.childrenCount,
      childAges: observedBody.childAges,
      childAgesSignature: observedBody.childAgesSignature
    },
    providerSearches: 0,
    leadWrites: 0
  };
}

(async () => {
  fs.mkdirSync(OUTPUT, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    const results = [];
    for (const width of [390, 1440]) results.push(await auditWidth(browser, width));
    fs.writeFileSync(path.join(OUTPUT, 'result.json'), JSON.stringify({
      ok: true,
      target: TARGET,
      generatedAt: new Date().toISOString(),
      results
    }, null, 2));
    console.log('SEARCH3_LOCAL_LIVE_READONLY_OK widths=390,1440 supplier_searches=0 real_leads=0');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exit(1);
});
