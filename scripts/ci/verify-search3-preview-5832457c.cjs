'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { chromium } = require('playwright');
const base = 'https://anytoour.ru/_preview/search3-site-candidate/';
const source = '5832457c6d43270290967a49f815a12463b19c18';
const out = process.env.EVIDENCE_DIR;
assert.ok(out, 'EVIDENCE_DIR is required');
fs.mkdirSync(out, { recursive: true });
const report = { source_sha: source, preview_url: base, real_lead_submitted: false, physical_safari_tested: false, assets: [], widths: [], completed: [] };
const sha256 = data => crypto.createHash('sha256').update(data).digest('hex');
const paint = page => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  let page;
  try {
    // No lead endpoint, analytics collector, consultant widget or external write is reachable.
    await context.route('**/*', route => {
      const request = route.request(), url = new URL(request.url());
      if (/\/(?:lead[^/]*|preview-lead-disabled)\.php/.test(url.pathname) || /mc\.yandex|metrika|web-consultant\/widget/.test(url.href)) return route.abort();
      if (!['GET', 'HEAD'].includes(request.method()) && !(url.origin === 'https://anytoour.ru' && url.pathname === '/api-v2.php')) return route.abort();
      return route.continue();
    });
    const manifest = JSON.parse(fs.readFileSync('docs/project/search3-production-import.json', 'utf8'));
    for (const [name, expected] of Object.entries(manifest.assets)) {
      const response = await context.request.get(base + name + '?verify=' + source, { timeout: 30000 });
      assert.equal(response.status(), 200, name);
      const digest = sha256(await response.body());
      assert.equal(digest, expected.productionSha256, 'Published asset mismatch: ' + name);
      report.assets.push({ name, sha256: digest });
    }
    assert.equal(report.assets.length, 8);
    report.completed.push('all eight published assets match exact source');
    page = await context.newPage();
    page.setDefaultTimeout(20000);
    report.page_errors = [];
    page.on('pageerror', error => report.page_errors.push(String(error.message)));
    let response = await page.goto(base, { waitUntil: 'domcontentloaded', timeout: 45000 });
    assert.equal(response.status(), 200);
    await page.locator('.at-global-header__nav a[href="/_preview/search3-site-candidate/poisk-turov/"]').first().click();
    await page.waitForURL(base + 'poisk-turov/');
    await page.waitForFunction(() => window.Search3CandidateResultsV1 && document.querySelector('body.search3-candidate'));
    await page.waitForFunction(() => ['from','country'].every(name => {
      const el = document.querySelector('#tourSearch [name="' + name + '"]');
      return el && !el.disabled && el.options.length > 1 && !/Загружаем|Не удалось/.test(el.options[el.selectedIndex]?.textContent || '');
    }), null, { timeout: 45000 });
    for (const [name, pattern] of [['from', 'Москва'], ['country', 'Турция']]) {
      const value = await page.locator('#tourSearch [name="' + name + '"]').evaluate((el, text) => [...el.options].find(option => option.textContent.trim() === text)?.value, pattern);
      assert.ok(value, 'Missing real catalogue option: ' + pattern);
      await page.locator('#tourSearch [name="' + name + '"]').selectOption(value);
    }
    report.search_parameters = await page.locator('#tourSearch').evaluate(form => Object.fromEntries([...new FormData(form)].filter(([name]) => ['from','country','dateFrom','dateTo','daysFrom','daysTill','count_people','child_count'].includes(name))));
    await page.locator('#tourSearch .search-submit:visible').click();
    await page.waitForSelector('#results .hotel-card .search3-show-tours', { timeout: 150000 });
    await paint(page);
    report.hotel_count = await page.locator('#results .hotel-card').count();
    report.completed.push('live homepage to search to real hotel results');
    await page.screenshot({ path: path.join(out, 'live-results-1440.png'), fullPage: true, animations: 'disabled' });
    const native = page.locator('#sortResults');
    const before = await page.locator('#tourSearch').evaluate(form => JSON.stringify([...new FormData(form)]));
    for (const width of [375, 999, 1000, 1348, 430]) {
      await page.setViewportSize({ width, height: 1000 });
      await paint(page);
      if (width < 1000) await page.locator('.search3-mobile-toolbar').waitFor({ state: 'visible' });
      const geometry = await page.locator('.search3-mobile-toolbar').evaluate(node => ({ display: getComputedStyle(node).display, height: node.getBoundingClientRect().height, count: document.querySelectorAll('.search3-mobile-toolbar').length, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2 }));
      assert.equal(geometry.count, 1);
      assert.equal(geometry.overflow, false, 'horizontal overflow at ' + width);
      if (width >= 1000) {
        assert.equal(geometry.display, 'none');
        assert.equal(geometry.height, 0);
        assert.equal(await native.isVisible(), true);
      } else {
        assert.equal(await page.locator('.mrf-open').isVisible(), true);
        await page.locator('.mrf-open').click();
        await page.waitForFunction(() => document.querySelector('.mrf-sheet').classList.contains('is-open'));
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('.mrf-open').evaluate(node => node === document.activeElement), true);
      }
      assert.equal(await page.locator('#tourSearch').evaluate(form => JSON.stringify([...new FormData(form)])), before, 'resize changed form');
      report.widths.push({ width, ...geometry });
    }
    const proxy = page.locator('.search3-mobile-sort select');
    const values = await native.locator('option').evaluateAll(nodes => nodes.map(node => node.value));
    assert.ok(values.length >= 2);
    await proxy.selectOption(values[1], { force: true });
    assert.equal(await native.inputValue(), values[1]);
    await native.selectOption(values[0], { force: true });
    assert.equal(await proxy.inputValue(), values[0]);
    await paint(page);
    report.completed.push('live responsive toolbar, filter open/Escape/focus, unchanged parameters and two-way sort');
    await page.screenshot({ path: path.join(out, 'live-results-430.png'), fullPage: true, animations: 'disabled' });
    // One real tour only; do not submit, fabricate data, or retry unavailable inventory in a loop.
    const card = page.locator('#results .hotel-card').first();
    report.hotel = (await card.locator('.hotel-title').innerText()).trim();
    await card.locator('.search3-show-tours').click();
    const choice = card.locator('.hotel-tours button[data-tid]:visible').first();
    await choice.waitFor({ state: 'visible', timeout: 30000 });
    report.tour_id = await choice.getAttribute('data-tid');
    await choice.click();
    await page.waitForSelector('#selectedTour .lead-form', { state: 'attached', timeout: 60000 });
    await page.waitForSelector('#selectedTour .search3-flight-continue button', { state: 'attached', timeout: 60000 });
    const flight = page.locator('#selectedTour input[name="v2flight"]:visible').first();
    if (await flight.count()) await flight.check();
    report.flight_variants = await page.locator('#selectedTour .flight-variant').count();
    // These controls only advance the presentation stages, never the lead submit button.
    await page.setViewportSize({ width: 1440, height: 1000 });
    await paint(page);
    await page.locator('#selectedTour .search3-flight-continue button:visible').click();
    await page.locator('#selectedTour .search3-summary-submit:visible').click();
    await page.locator('#selectedTour .lead-form input[name="phone"]').waitFor({ state: 'visible' });
    assert.equal(await page.locator('#selectedTour .lead-form').getAttribute('data-sent'), null);
    report.completed.push('real hotel to tour to review to visible lead form without submission');
    await page.screenshot({ path: path.join(out, 'live-lead-1440.png'), fullPage: true, animations: 'disabled' });
    report.status = 'passed';
  } catch (error) {
    report.status = 'failed';
    report.error = String(error.stack || error);
    if (page) {
      report.last_url = page.url();
      await page.screenshot({ path: path.join(out, 'failure.png'), fullPage: true, animations: 'disabled' }).catch(() => {});
    }
    process.exitCode = 1;
  } finally {
    fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2) + '\n');
    console.log(JSON.stringify(report, null, 2));
    await browser.close();
  }
})();
