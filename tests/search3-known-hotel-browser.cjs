/* Search3 known-hotel lookup on the real served form and scoped bundle. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const base = process.env.SEARCH3_VISUAL_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'requires the isolated local artifact server');
const output = process.env.SEARCH3_KNOWN_HOTEL_OUTPUT;
assert.ok(output, 'requires retained evidence output');
fs.mkdirSync(output, { recursive: true });

const hotels = [
  { id: 41001, name: 'RIXOS PREMIUM BELEK', country: { id: 4, name: 'Турция' }, region: { id: 401, name: 'Белек' }, subRegion: { id: 4011, name: 'Илерибаши' }, category: 5, rating: 4.8 },
  { id: 41002, name: 'RIXOS SUNGATE', country: { id: 4, name: 'Турция' }, region: { id: 402, name: 'Кемер' }, subRegion: { id: 4021, name: 'Бельдиби' }, category: 5, rating: 4.7 }
];
const viewports = [
  { width: 375, height: 812 },
  { width: 390, height: 500 },
  { width: 430, height: 932 },
  { width: 1440, height: 980 }
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const evidence = [];
  try {
    for (const viewport of viewports) {
      const page = await browser.newPage({ viewportSize: viewport });
      const searchStarts = [], hotelQueries = [], errors = [];
      page.on('pageerror', error => errors.push(String(error)));
      await page.route('**/*', route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.pathname === '/data/hotel-search-v1.php') {
          hotelQueries.push(Object.fromEntries(url.searchParams));
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, source: 'anytour-catalog', items: hotels }) });
        }
        if (url.pathname === '/data/departures-v1.php') {
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, items: [{ id: 1, name: 'Москва' }] }) });
        }
        if (/\/api-v2\.php$/.test(url.pathname)) {
          const action = url.searchParams.get('action');
          if (action === 'search_start') searchStarts.push(request.url());
          const payload = action === 'countries' ? [{ id: 4, name: 'Турция' }]
            : action === 'regions' ? [{ id: 401, name: 'Белек' }, { id: 402, name: 'Кемер' }]
            : action === 'meals' ? [{ id: 7, name: 'Всё включено' }]
            : [];
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payload) });
        }
        if (url.origin !== new URL(base).origin || request.method() !== 'GET') return route.abort();
        return route.continue();
      });

      const response = await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
      assert.equal(response.status(), 200);
      const form = page.locator('#tourSearch');
      await page.waitForFunction(() => document.forms.tourSearch?.dataset.search3Ready === '1');
      await page.waitForFunction(() => document.forms.tourSearch?.dataset.catalogSource === 'anytour-departures');
      const input = form.locator('[data-v2-hotel-query]');
      const select = form.locator('select[name="hotel"]');
      assert.equal(await input.count(), 1, 'Search3 exposes one known-hotel query');
      assert.equal(await input.isVisible(), true, 'known-hotel query is visible');
      assert.equal(await select.isVisible(), false, 'exact hotel ID remains in one hidden native owner');
      assert.equal(await input.getAttribute('placeholder'), 'Например Rixos Premium Belek');

      await input.fill('Rixos');
      const list = form.locator('#hotelAutocompleteList');
      await list.waitFor({ state: 'visible', timeout: 3000 });
      assert.equal(await list.locator('[role="option"]').count(), 2, 'country-wide lookup keeps hotels from different resorts');
      assert.match(await list.innerText(), /RIXOS PREMIUM BELEK[\s\S]*Белек[\s\S]*Илерибаши[\s\S]*5★/);
      assert.match(await list.innerText(), /RIXOS SUNGATE[\s\S]*Кемер[\s\S]*Бельдиби[\s\S]*5★/);
      assert.equal(hotelQueries.length, 1);
      assert.deepEqual(hotelQueries[0], { q: 'Rixos', limit: '10', countryId: '4' }, 'lookup is country-scoped without requiring a resort');

      const geometry = await page.evaluate(() => {
        const input = document.querySelector('[data-v2-hotel-query]');
        const options = [...document.querySelectorAll('.hotel-autocomplete__option')];
        const rect = node => { const r = node.getBoundingClientRect(), s = getComputedStyle(node); return { x: r.x, y: r.y, width: r.width, height: r.height, fontSize: parseFloat(s.fontSize) }; };
        return { input: rect(input), options: options.map(rect), overflow: document.documentElement.scrollWidth > innerWidth + 1 };
      });
      assert.ok(geometry.input.height >= 44 && geometry.input.fontSize >= 16, 'known-hotel query remains touch and zoom safe');
      assert.ok(geometry.options.every(item => item.height >= 44), 'every hotel result is a full touch target');
      assert.equal(geometry.overflow, false, 'lookup has no horizontal overflow');

      await input.press('Enter');
      assert.equal(await select.inputValue(), '41001', 'keyboard selection stores the exact canonical hotel ID');
      assert.equal(await input.inputValue(), 'RIXOS PREMIUM BELEK');
      assert.equal(await list.isVisible(), false);
      assert.deepEqual(searchStarts, [], 'typing/selecting a hotel does not start a supplier search');

      await form.locator('select[name="region"]').selectOption('402');
      assert.equal(await select.inputValue(), '', 'changing geography clears an incompatible exact hotel');
      assert.equal(await input.inputValue(), '');
      assert.deepEqual(searchStarts, [], 'geographic clarification still does not submit a tour search');

      await input.fill('Rixos');
      await list.waitFor({ state: 'visible' });
      await page.screenshot({ path: path.join(output, `known-hotel-${viewport.width}x${viewport.height}.png`), animations: 'disabled' });
      evidence.push({ viewport, geometry, hotelQueries: hotelQueries.slice(), selectedHotelId: '41001', supplierSearches: searchStarts.length, errors });
      assert.deepEqual(errors, []);
      await page.close();
    }
    fs.writeFileSync(path.join(output, 'known-hotel.json'), JSON.stringify({ sourceSha: process.env.SEARCH3_SOURCE_SHA || '', evidence }, null, 2) + '\n');
    console.log('SEARCH3_KNOWN_HOTEL_OK viewports=' + viewports.length + ' supplier_searches=0');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
