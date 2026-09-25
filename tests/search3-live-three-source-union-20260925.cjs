'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const TARGET = 'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/';
const OUTPUT = process.env.SEARCH3_LIVE_OUTPUT || 'search3-live-three-source-union';

function kind(url) {
  const u = new URL(url);
  if (u.pathname === '/data/departures-v1.php') return 'departures-local';
  if (u.pathname === '/api-v2.php') return 'tourvisor:' + (u.searchParams.get('action') || 'unknown');
  if (u.pathname.endsWith('/data/search3-local-results-read-v1.php')) return 'local-db';
  if (u.pathname.endsWith('/data/search3-destination-read-v1.php')) return 'destination:' + (u.searchParams.get('action') || 'unknown');
  if (u.pathname.endsWith('/api-anex-search3-preview.php')) return 'anex';
  if (u.pathname.endsWith('/api-andromeda-search3-preview.php')) return 'andromeda';
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
  const requests = [], responses = [], pageErrors = [];

  page.on('pageerror', error => pageErrors.push(String(error)));
  page.on('request', request => {
    const k = kind(request.url());
    if (k) requests.push({ kind:k, method:request.method(), url:new URL(request.url()).pathname + new URL(request.url()).search });
  });
  page.on('response', response => {
    const k = kind(response.url());
    if (k) responses.push({ kind:k, status:response.status(), url:new URL(response.url()).pathname + new URL(response.url()).search });
  });

  const nav = await page.goto(TARGET, { waitUntil:'domcontentloaded', timeout:45000 });
  assert(nav && nav.status() === 200, 'published preview must return HTTP 200');
  await page.waitForTimeout(20000);

  const state = await page.evaluate(() => ({
    disabled: document.querySelector('.search-submit')?.disabled ?? null,
    destination: document.querySelector('#destination-label')?.textContent ?? '',
    toast: document.querySelector('#toast')?.hidden === false ? document.querySelector('#toast')?.textContent ?? '' : '',
    cards: document.querySelector('#cards')?.innerText ?? '',
    formHidden: document.querySelector('#search-form')?.hidden ?? null
  }));

  const requestCounts={}; for(const row of requests) requestCounts[row.kind]=(requestCounts[row.kind]||0)+1;
  const responseCounts={}; for(const row of responses){const key=row.kind+':'+row.status;responseCounts[key]=(responseCounts[key]||0)+1;}
  const safe={capturedAt:new Date().toISOString(),state,requestCounts,responseCounts,requests,responses,pageErrors};
  fs.writeFileSync(path.join(OUTPUT,'catalog-boot.json'),JSON.stringify(safe,null,2));
  console.log('SEARCH3_LIVE_CATALOG_BOOT '+JSON.stringify(safe));

  assert.equal(requestCounts['tourvisor:search_start']||0,0,'boot diagnostic must not start Tourvisor search');
  assert.equal(requestCounts.anex||0,0,'boot diagnostic must not call ANEX search');
  assert.equal(requestCounts.andromeda||0,0,'boot diagnostic must not call Andromeda search');
  assert.equal(requestCounts.lead||0,0,'boot diagnostic must not write leads');

  await context.close();
  await browser.close();
})().catch(error=>{console.error(error);process.exit(1);});
