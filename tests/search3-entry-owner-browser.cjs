/* Focused exact-artifact native entry check; no supplier or lead requests. */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.SEARCH3_VISUAL_BASE, output = process.env.SEARCH3_ENTRY_OWNER_OUTPUT;
assert.ok(base && new URL(base).hostname === '127.0.0.1' && output);
fs.mkdirSync(output, { recursive: true });
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
    const response = await page.goto(base + '/poisk-turov/?count_people=3&child_count=1&child_age%5B%5D=8', { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200);
    await page.waitForFunction(() => document.getElementById('tourSearch')?.dataset.search3Ready === '1' && window.V2SearchLifecycle);
    const adults = page.locator('#tourSearch select[name=count_people]'), children = page.locator('#tourSearch select[name=child_count]');
    assert.equal(await adults.inputValue(), '3', 'URL adult value stays on original control');
    assert.equal(await children.inputValue(), '1', 'URL child count survives native presentation');
    assert.equal(await page.locator('#childAges select').inputValue(), '8', 'URL child age survives');
    for (const selector of ['input[type=date]', 'input[name=daysFrom]', 'input[name=daysTill]', 'select[name=count_people]', 'select[name=child_count]', '.search-submit']) {
      for (const control of await page.locator('#tourSearch ' + selector).all()) {
        assert.equal(await control.isVisible(), true, selector + ' remains directly visible');
        assert.ok((await control.boundingBox()).height >= 44, selector + ' native target >=44px');
      }
    }
    assert.equal(await page.locator('.search3-composite,.search3-direct-control,.search3-primary-grid,.search3-quality,.search3-quick,.search3-tourists__pop,.search3-tourists__summary,.search3-mobile-search-filter-button,.search3-price-calendar').count(), 0,
      'retired entry projection is absent');
    assert.equal(await page.locator('#tourSearch > details.extras').count(), 1, 'canonical advanced filters remain');
    await adults.selectOption('4');
    await children.selectOption('2');
    await page.waitForFunction(() => document.querySelectorAll('#childAges select').length === 2);
    await page.locator('#childAges select').nth(0).selectOption('8');
    await page.locator('#childAges select').nth(1).selectOption('6');
    await page.locator('input[name=daysFrom]').fill('8');
    await page.locator('input[name=daysTill]').fill('9');
    const payload = await page.evaluate(() => {
      const data = new FormData(document.getElementById('tourSearch'));
      return { adults: data.get('count_people'), children: data.get('child_count'), ages: data.getAll('child_age[]'), from: data.get('daysFrom'), till: data.get('daysTill') };
    });
    assert.deepEqual(payload, { adults: '4', children: '2', ages: ['8', '6'], from: '8', till: '9' }, 'native changes retain canonical form field mapping');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'entry has no horizontal overflow');
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(output, `entry-${width}.json`), JSON.stringify({ width, payload, blocked, lead_sent: 0 }, null, 2) + '\n');
    await page.screenshot({ path: path.join(output, `entry-${width}.png`), fullPage: true });
  } finally { await page.close(); }
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  try { for (const width of [375, 1440]) await run(browser, width); }
  finally { await browser.close(); }
  console.log('SEARCH3_ENTRY_OWNER_BROWSER_OK widths=375,1440 lead_sent=0');
})().catch(error => { console.error(error); process.exitCode = 1; });
