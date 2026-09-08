/* Current reset UI: actual renderer, native header, and raw/served JS parity.
 * Retired custom disclosures, drawers and pixel dimensions are not fabricated. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.SEARCH3_VISUAL_BASE, output = process.env.SEARCH3_RESULTS_OUTPUT;
assert.ok(base && new URL(base).hostname === '127.0.0.1' && output);
fs.mkdirSync(output, { recursive: true });
const names = JSON.parse(execFileSync('php', ['-r', 'require "v2/bundle-manifest-v1.php"; echo json_encode(v2_bundle_files("js", "search3"));'], { cwd: root, encoding: 'utf8' }));
const raw = names.map(name => fs.readFileSync(path.join(root, 'v2', name), 'utf8')).join('\n;\n');
const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="300"><path fill="#9ac7df" d="M0 0h600v300H0z"/></svg>');
const tour = { id: 'current-tour', price: 148500.6, date: '2026-09-12', nights: 9, meal: { name: 'Всё включено' }, roomType: 'STANDARD LAND VIEW', placement: 'DBL', operator: { name: 'TEST OPERATOR' } };
const hotels = [
  { id: 'expensive', name: 'Проверочный отель с длинным названием', country: { name: 'Турция' }, region: { name: 'Анталья' }, price: tour.price, rating: 5, picturelink: picture, tours: [tour, { ...tour, id: 'other-tour', price: 159000 }] },
  { id: 'cheap', name: 'Второй отель', price: 90000, rating: 4, picturelink: picture, tours: [{ ...tour, id: 'cheap-tour', price: 90000 }] }
];
async function snapshot(page) {
  await page.evaluate(async () => {
    await document.fonts.ready;
    for (let i = 0; i < 3; i++) await new Promise(r => requestAnimationFrame(() => setTimeout(r, 0)));
    scrollTo({ top: 0, left: 0, behavior: 'instant' });
  });
  return page.evaluate(() => {
    const result = document.getElementById('results');
    const nodes = [...result.querySelectorAll('*')].map(node => {
      const r = node.getBoundingClientRect(), s = getComputedStyle(node);
      return { tag: node.tagName, classes: node.className, text: node.children.length ? '' : node.textContent,
        rect: [r.x, r.y, r.width, r.height].map(n => Math.round(n * 100) / 100), display: s.display, visibility: s.visibility };
    });
    return { html: result.innerHTML, nodes, overflow: document.documentElement.scrollWidth > innerWidth + 2 };
  });
}
async function run(browser, width, previous) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } }), errors = [];
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== new URL(base).origin || request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
    if (previous && url.pathname.endsWith('/bundle-v1.php') && url.searchParams.get('type') === 'js') return route.fulfill({ status: 200, contentType: 'application/javascript', body: raw });
    return route.continue();
  });
  try {
    assert.equal((await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' })).status(), 200);
    await page.waitForFunction(() => window.V2Results && window.V2TourController && document.querySelector('#tourSearch')?.dataset.search3Ready === '1');
    const logo = page.locator('.at-global-header__logo img');
    assert.equal(await logo.isVisible(), true, 'canonical logo remains visible');
    const logoSource = await logo.getAttribute('src');
    const menu = page.locator('.at-global-header__mobile');
    await menu.locator('summary').click();
    assert.equal(await menu.evaluate(node => node.open), true, 'native header opens');
    await menu.locator('summary').click();
    assert.equal(await menu.evaluate(node => node.open), false, 'native header closes');
    await page.evaluate(items => window.V2Results.render(items), hotels);
    await page.waitForSelector('#results .direct-tour');
    assert.equal(await page.locator('#results .hotel-card').first().getAttribute('data-hotel-id'), 'cheap', 'price sorting retained');
    const card = page.locator('#results [data-hotel-id=expensive].hotel-card');
    assert.equal(await card.locator('.tour-row').count(), 1, 'representative tour shown immediately');
    assert.ok((await card.locator('.direct-tour').boundingBox()).height >= 35.5, 'real selection action remains usable');
    assert.equal(await card.locator('.direct-tour').getAttribute('data-tid'), tour.id, 'selection identity retained');
    const collapsed = await snapshot(page);
    assert.equal(collapsed.overflow, false, width + ': results fit viewport');
    await card.locator('.tour-more-toggle').click();
    assert.equal(await card.locator('.tour-row').count(), 2, 'actual toggle reveals all tours');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'true');
    const expanded = await snapshot(page);
    assert.equal(expanded.overflow, false, width + ': expanded results fit viewport');
    assert.match(await card.innerText(), /148[\s\u00a0]*500,6/, 'decimal price remains visible');
    await card.locator('.tour-more-toggle').click();
    assert.equal(await card.locator('.tour-row').count(), 1, 'actual toggle collapses');
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await page.locator('#results .hotel-card').first().getAttribute('data-hotel-id'), 'expensive', 'rating sorting retained');
    await page.evaluate(() => window.V2Results.render([]));
    await page.locator('.empty-edit-search').click();
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'empty results return to native search form');
    assert.deepEqual(errors, [], 'no runtime errors');
    if (!previous) await page.screenshot({ path: path.join(output, `current-${width}.png`), fullPage: true });
    return { collapsed, expanded, logoSource };
  } finally { await page.close(); }
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [375, 760, 761, 999, 1000, 1440]) {
      const rawState = await run(browser, width, true), servedState = await run(browser, width, false);
      assert.deepEqual(servedState, rawState, width + ': served compact JS preserves actual result DOM and geometry');
      fs.writeFileSync(path.join(output, `current-${width}.json`), JSON.stringify(servedState, null, 2) + '\n');
    }
  } finally { await browser.close(); }
  console.log('SEARCH3_CURRENT_RESULTS_OK states=12 widths=375,760,761,999,1000,1440 raw_served_parity=1 native_header=1 external_calls=0 lead_sent=0');
})().catch(error => { console.error(error); process.exitCode = 1; });
