/* Served, copied SITE payload; catalogue fixtures only, no supplier search. */
'use strict';
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const base = process.env.SEARCH3_VISUAL_BASE;
assert.ok(base, 'requires the isolated SITE artifact server');
const baseUrl = new URL(base);
assert.ok(baseUrl.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(baseUrl.hostname)
  && baseUrl.pathname === '/_preview/search3-site-candidate' && !baseUrl.search && !baseUrl.hash
  && !baseUrl.username && !baseUrl.password, 'requires loopback and the exact SITE preview prefix');
const output = process.env.JAZ_HOTEL_OUTPUT;
assert.ok(output, 'requires retained JAZ page evidence output');
fs.mkdirSync(output, { recursive: true });
const photoUrl = 'https://static.tourvisor.ru/hotel_pics/verybig/1/iberotel-makadi-saraya-resort-19573305.jpg';
const photo = fs.readFileSync(path.join(output, 'jaz-real-photo.jpg'));
const logo = fs.readFileSync(path.join(output, 'anytour-logo.svg'));
const sha256 = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const futureDate = days => new Date(Date.now() + days * 86400000).toISOString().slice(0, 10);
const dateFrom = futureDate(90), dateTo = futureDate(97);
const hotel = {
  id: 268, name: 'JAZ MAKADI SARAYA RESORT (EX. IBEROTEL MAKADI SARAYA RESORT)',
  country: { id: 1, name: 'Египет' }, region: { id: 5, name: 'Макади-Бей' }
};
const trip = new URLSearchParams({
  from: '2', dateFrom, dateTo, daysFrom: '8', daysTill: '12', count_people: '3',
  child_count: '2', food: '7', price_from: '150000', price_till: '250000'
});
trip.append('child_age[]', '0');
trip.append('child_age[]', '17');
const report = {
  sourceSha: process.env.SEARCH3_SOURCE_SHA || null,
  scope: 'served copied SITE payload with catalogue/API fixtures; no live supplier or physical-device claim',
  assets: { photo: { url: photoUrl, sha256: sha256(photo) }, logo: { url: 'https://anytoour.ru/images/logo.svg', sha256: sha256(logo) } },
  cases: [], passed: false
};

function catalogueItems(key) {
  return key === 'countries' ? [{ id: 1, name: 'Египет' }]
    : key === 'regions' ? [{ id: 5, name: 'Макади-Бей' }]
    : key === 'hotels' ? [hotel]
    : key === 'meals' ? [{ id: 7, name: 'Всё включено' }]
    : key === 'departures' ? [{ id: 1, name: 'Москва' }, { id: 2, name: 'Тестовый вылет №2' }] : [];
}

(async () => {
  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    for (const viewport of [{ width: 430, height: 932 }, { width: 1280, height: 720 }]) {
      const page = await browser.newPage({ viewport, hasTouch: viewport.width === 430 });
      const record = { viewport, passed: false, searchStartAttempts: 0, errors: [], requests: [] };
      report.cases.push(record);
      page.on('pageerror', error => record.errors.push(String(error)));
      await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (url.href === photoUrl) return route.fulfill({ status: 200, contentType: 'image/jpeg', body: photo });
        if (url.origin === baseUrl.origin && url.pathname === '/images/logo.svg') {
          return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: logo });
        }
        // Never permit the browser to reach a live catalogue, supplier or lead endpoint.
        if (url.origin !== baseUrl.origin || request.method() !== 'GET') return route.abort();
        const action = url.searchParams.get('action');
        if (url.pathname.startsWith('/data/')) {
          const key = url.pathname.includes('departures') ? 'departures' : url.pathname.includes('hotel') ? 'hotels' : action;
          record.requests.push({ type: 'catalogue-fixture', path: url.pathname, action: key });
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, source: 'anytour-catalog', items: catalogueItems(key) }) });
        }
        if (/\/api(?:-v2)?\.php$/.test(url.pathname)) {
          record.requests.push({ type: 'api-fixture', action });
          if (action === 'search_start') record.searchStartAttempts++;
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(catalogueItems(action)) });
        }
        if (!['document', 'stylesheet', 'script', 'image', 'font'].includes(request.resourceType())) return route.abort();
        return route.continue();
      });
      try {
        const url = base + '/jaz-makadi-saraya/?' + trip;
        const response = await page.goto(url, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200);
        assert.match(response.headers()['x-robots-tag'] || '', /noindex/);
        assert.match(await page.locator('meta[name="robots"]').getAttribute('content'), /noindex/);
        assert.equal(await page.locator('main h1').count(), 1);
        await page.locator('.jaz-hero__photo img').evaluate(img => img.decode());
        record.geometry = await page.evaluate(() => ({
          width: innerWidth, scrollWidth: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight,
          photoWidth: document.querySelector('.jaz-hero__photo img').naturalWidth,
          cta: document.querySelector('[data-hotel-search]').getBoundingClientRect().toJSON()
        }));
        assert.ok(record.geometry.scrollWidth <= viewport.width + 1, 'horizontal overflow on hotel page');
        assert.equal(record.geometry.photoWidth, 1024, 'accepted photo did not load');
        assert.ok(record.geometry.cta.height >= 44, 'primary CTA is too short for ordinary taps');
        const links = await page.locator('[data-hotel-search]').evaluateAll(elements => elements.map(element => element.getAttribute('href')));
        assert.equal(links.length, 2);
        assert.equal(links[0], links[1], 'hero and lower CTA lost their common trip context');
        const searchUrl = new URL(links[0], baseUrl.origin);
        assert.equal(searchUrl.pathname, baseUrl.pathname + '/poisk-turov/');
        assert.equal(searchUrl.searchParams.get('search3_restore'), '1');
        assert.deepEqual(searchUrl.searchParams.getAll('child_age[]'), ['0', '17']);
        await page.screenshot({ path: path.join(output, `jaz-${viewport.width}-full.png`), fullPage: true, animations: 'disabled' });
        await page.screenshot({ path: path.join(output, `jaz-${viewport.width}-hero.png`), animations: 'disabled' });
        await page.locator('#rooms').screenshot({ path: path.join(output, `jaz-${viewport.width}-rooms.png`), animations: 'disabled' });
        await page.locator('.jaz-sources summary').click();
        assert.ok(await page.locator('.jaz-sources details').evaluate(element => element.open));
        await page.locator('.jaz-sources summary').click();
        await page.locator('[data-hotel-search]').first().click();
        await page.waitForURL(url => url.pathname === baseUrl.pathname + '/poisk-turov/');
        await page.waitForFunction(() => document.forms.tourSearch?.dataset.search3Ready === '1');
        await page.waitForFunction(() => document.forms.tourSearch?.dataset.catalogSource === 'anytour-departures');
        await page.waitForFunction(() => document.getElementById('status')?.textContent.includes('Параметры сохранённого поиска восстановлены'));
        record.form = await page.locator('#tourSearch').evaluate(element => ({
          from: element.elements.from.value, country: element.elements.country.value, region: element.elements.region.value,
          hotel: element.elements.hotel.value, hotelLabel: element.elements.hotel.selectedOptions[0]?.textContent,
          dateFrom: element.elements.dateFrom.value, dateTo: element.elements.dateTo.value,
          daysFrom: element.elements.daysFrom.value, daysTill: element.elements.daysTill.value,
          adults: element.elements.count_people.value, children: element.elements.child_count.value,
          ages: [...element.querySelectorAll('[name="child_age[]"]')].map(input => input.value),
          food: element.elements.food.value, priceFrom: element.elements.price_from.value, priceTo: element.elements.price_till.value
        }));
        assert.deepEqual(record.form, {
          from: '2', country: '1', region: '5', hotel: '268', hotelLabel: hotel.name,
          dateFrom, dateTo, daysFrom: '8', daysTill: '12', adults: '3', children: '2', ages: ['0', '17'],
          food: '7', priceFrom: '150000', priceTo: '250000'
        });
        assert.equal(record.searchStartAttempts, 0, 'context handoff auto-started a supplier search');
        await page.screenshot({ path: path.join(output, `jaz-${viewport.width}-search.png`), fullPage: true, animations: 'disabled' });
        await page.goBack({ waitUntil: 'networkidle' });
        assert.equal(page.url(), url, 'Back lost the hotel page context');
        assert.equal(await page.locator('[data-hotel-search]').first().getAttribute('href'), links[0]);
        await page.locator('[data-hotel-search]').last().scrollIntoViewIfNeeded();
        assert.ok(await page.locator('[data-hotel-search]').last().isVisible());
        assert.deepEqual(record.errors, []);
        Object.assign(record, { ctaEqual: true, backRetained: true, passed: true });
      } catch (error) {
        record.failure = String(error.stack || error);
        await page.screenshot({ path: path.join(output, `jaz-${viewport.width}-failure.png`), fullPage: true }).catch(() => {});
        throw error;
      } finally {
        await page.close();
      }
    }
    report.passed = true;
    console.log('JAZ_HOTEL_PAGE_BROWSER_OK');
  } catch (error) {
    report.failure = String(error.stack || error);
    throw error;
  } finally {
    fs.writeFileSync(path.join(output, 'jaz-browser-check.json'), JSON.stringify(report, null, 2) + '\n');
    if (browser) await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
