/* Exact isolated artifact: native results disclosure, selection/return and recovery. */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.SEARCH3_VISUAL_BASE;
const output = process.env.SEARCH3_RESULTS_OWNER_OUTPUT;
assert.ok(base && new URL(base).hostname === '127.0.0.1' && output, 'local isolated preview and evidence required');
fs.mkdirSync(output, { recursive: true });
const tour = {
  id: 'owner-tour', price: 148500, date: '2026-09-12', nights: 9, adults: 2, childs: 1,
  hotel: { name: 'Проверочный отель', country: { name: 'Турция' }, region: { name: 'Анталья' } },
  departure: { name: 'Москва' }, meal: { name: 'AI' }, roomType: 'STANDARD',
  placement: 'DBL', operator: { name: 'TEST' }
};
const hotels = [
  { id: 'owner-hotel', name: 'Проверочный отель', price: 148500, category: 4, rating: 4.2,
    tours: [tour, { ...tour, id: 'owner-tour-2', price: 155000 }] },
  { id: 'second-hotel', name: 'Второй отель', price: 190000, category: 5, rating: 4.8,
    tours: [{ ...tour, id: 'second-tour', price: 190000 }] }
];
async function run(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  const errors = [], blocked = [];
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== new URL(base).origin || request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) {
      blocked.push(`${request.method()} ${url.pathname}`);
      return route.abort();
    }
    return route.continue();
  });
  try {
    assert.equal((await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' })).status(), 200);
    await page.waitForFunction(() => window.V2Results && window.V2TourController && window.Search3CandidateResultsV1);
    await page.evaluate(({ hotels, tour }) => {
      window.__ownerCalls = { tour: 0, flights: 0, submit: 0 };
      window.V2Runtime.api = async action => {
        if (action === 'tour') { window.__ownerCalls.tour++; return tour; }
        if (action === 'flights') { window.__ownerCalls.flights++; return []; }
        throw new Error('unexpected fixture API action ' + action);
      };
      window.V2SearchLifecycle.submit = () => { window.__ownerCalls.submit++; };
      window.V2Results.render(hotels);
    }, { hotels, tour });
    const card = page.locator('.hotel-card[data-hotel-id="owner-hotel"]');
    assert.equal(await card.locator('.direct-tour').count(), 1, 'core immediately exposes the representative offer');
    assert.equal(await card.locator('.direct-tour').isVisible(), true, 'no second disclosure hides selection');
    assert.equal(await page.locator('.search3-show-tours,.search3-hotel-facts,.search3-mobile-toolbar').count(), 0, 'retired decorators stay absent');
    await card.locator('.tour-more-toggle').click();
    assert.equal(await card.locator('.direct-tour').count(), 2, 'core disclosure exposes all supplier offers');
    await page.waitForFunction(() => [...document.querySelectorAll('.direct-tour')].every(node => node.textContent === 'Проверить тур'));
    assert.match(await card.innerText(), /Всё включено/, 'retained offer translation remains');
    await page.selectOption('#sortResults', 'rating');
    assert.equal(await page.locator('.hotel-card').first().getAttribute('data-hotel-id'), 'second-hotel', 'native sort remains owned by core renderer');
    const choice = card.locator('.direct-tour[data-tid="owner-tour"]');
    await choice.click();
    await page.waitForSelector('#selectedTour .back-results');
    assert.match(await page.locator('#selectedTour').innerText(), /148[\s\u00a0]*500/, 'selected price is unchanged');
    await page.locator('#selectedTour .back-results').click();
    await page.waitForFunction(() => document.getElementById('selectedTour').hidden && document.activeElement?.dataset.tid === 'owner-tour');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-dirty')));
    assert.equal(await page.locator('.search-stale-update').isVisible(), true, 'dirty results retain explicit recovery');
    await page.locator('.search-stale-update').click();
    assert.equal(await page.evaluate(() => window.__ownerCalls.submit), 1);
    await page.locator('#resultsSearchEdit').click();
    assert.equal(await page.locator('#tourSearch .search-submit').isVisible(), true, 'edit reopens the search form');
    await page.evaluate(() => {
      window.dispatchEvent(new CustomEvent('v2:search-started'));
      window.V2Results.render([]);
    });
    assert.equal(await page.locator('.empty-actionable').isVisible(), true, 'supplier zero results remain recoverable, not hidden with the shell');
    assert.equal(await page.locator('.empty-edit-search').isVisible(), true);
    assert.deepEqual(await page.evaluate(() => window.__ownerCalls), { tour: 1, flights: 1, submit: 1 });
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(output, `results-${width}.json`), JSON.stringify({ width, blocked, lead_sent: 0,
      states: ['results', 'more', 'sort', 'select', 'return-focus', 'dirty-update', 'edit', 'empty'] }, null, 2) + '\n');
    await page.screenshot({ path: path.join(output, `results-${width}.png`), fullPage: true });
  } finally { await page.close(); }
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  try { for (const width of [375, 1440]) await run(browser, width); }
  finally { await browser.close(); }
  console.log('SEARCH3_RESULTS_OWNER_BROWSER_OK widths=375,1440 lead_sent=0');
})().catch(error => { console.error(error); process.exitCode = 1; });
