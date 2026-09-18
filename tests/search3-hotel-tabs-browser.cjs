'use strict';
// CI-only acceptance: served exact artifact, fictional catalog/offers, all remote
// traffic intercepted. This is Chromium/WebKit evidence, not physical Safari.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, webkit } = require('playwright');
const base = new URL(process.env.SEARCH3_VISUAL_BASE);
const sourceSha = process.env.SEARCH3_SOURCE_SHA;
assert.equal(base.hostname, '127.0.0.1');
assert.match(sourceSha || '', /^[a-f0-9]{40}$/);
const output = path.join(process.env.SEARCH3_RESULTS_OUTPUT, 'hotel-tabs');
fs.mkdirSync(output, { recursive: true });
const prefix = '/_preview/search3-local-candidate';
const origin = 'https://anytoour.ru';
const entry = origin + prefix + '/poisk-turov/';
const date = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
const query = new URLSearchParams({ from: '1', country: '4', dateFrom: date, dateTo: date, daysFrom: '7', daysTill: '10', count_people: '2', child_count: '0', utm_source: 'tabs-fixture' });
const svg = color => `<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450"><path fill="${color}" d="M0 0h800v450H0z"/><text x="30" y="240" font-size="28">Вымышленный отель · проверка вкладок</text></svg>`;
const profiles = [1, 2, 3].map(n => ({ id: 900 + n, catalog: 'anytour', revision: 1, name: `Проверочный отель ${n} с длинным названием`, category: 5, rating: 4.5, country: { name: 'Турция' }, region: { name: 'Кемер' }, description: 'Единственное описание из проверочного каталога.', detailsAvailable: true, primaryImage: origin + `/fixture-${n}-a.svg`, images: [origin + `/fixture-${n}-a.svg`, origin + `/fixture-${n}-b.svg`], hotelInformation: { services: ['Детский клуб'], roomTypes: 'Описание номеров отеля' } }));
const hotels = profiles.map((hotel, i) => ({ id: 101 + i, name: 'Supplier label must not replace catalog', provider: 'tourvisor', tours: [0, 1, 2, 3].map(n => ({ id: `hotel-${i + 1}-offer-${n}`, provider: 'tourvisor', price: 125000 + i * 10000 + n * 2500, date, nights: 7 + n, adults: 2, childs: 0, roomType: n ? 'STANDARD SEA VIEW' : 'STANDARD', meal: { name: n ? 'HB' : 'BB' }, placement: 'DBL', operator: { name: 'TEST OPERATOR' }, isCharter: true })) }));
const reports = [];
async function run(engine, width, height) {
  const browser = await ({ chromium, webkit }[engine]).launch();
  const context = await browser.newContext({ viewport: { width, height }, serviceWorkers: 'block' });
  await context.addInitScript(() => {
    const property = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'hidden');
    window.hotelTabRailTrace = [];
    Object.defineProperty(HTMLElement.prototype, 'hidden', {
      ...property,
      set(value) {
        if (this.classList.contains('results-filter-rail')) window.hotelTabRailTrace.push({ value, stack: new Error().stack });
        property.set.call(this, value);
      }
    });
  });
  const calls = [], failures = [], errors = [];
  const scenarioQuery = new URLSearchParams(query);
  if (width === 390) {
    scenarioQuery.set('child_count', '3');
    for (const age of ['0', '7', '17']) scenarioQuery.append('child_age[]', age);
  }
  context.on('page', page => { page.setDefaultTimeout(15000); page.on('pageerror', error => errors.push(String(error))); });
  await context.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    const json = (body, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
    if (url.origin !== origin) return route.abort();
    if (/^\/fixture-\d-[ab]\.svg$/.test(url.pathname)) return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: svg(url.pathname.endsWith('b.svg') ? '#dfc5a8' : '#badbea') });
    if (url.pathname.endsWith('/data/hotel-details-read-v1.php')) {
      const ids = url.searchParams.getAll('legacyHotelIds[]');
      return json({ ok: true, source: 'anytour-canonical-catalog', catalog: 'anytour', requestedLegacyIds: ids, items: ids.map(id => profiles[Number(id) - 101]), links: ids.map(id => ({ legacyHotelId: id, anytourHotelId: Number(id) + 800 })), missingLegacyIds: [] });
    }
    if (url.pathname.endsWith('/data/departures-v1.php')) return json({ ok: true, items: [{ id: 1, russianName: 'Москва' }] });
    if (url.pathname === '/data/observe-search-v1.php' && request.method() === 'POST') {
      // Existing passive price observation is not a lead. Keep it fully mocked
      // and prove that opening hotel tabs does not duplicate it.
      calls.push({ page: request.frame().page(), action: 'price-observation' });
      return json({ ok: true });
    }
    if (url.pathname.endsWith('/data/search3-local-results-read-v1.php')) {
      calls.push({ page: request.frame().page(), action: 'local-db' });
      return json({ ok: true, data: { source: 'anytour-db-first-results-v1', scopeVersion: 1, scopeDigest: 'a'.repeat(64), selectionAuthority: false, hotels: [], hotelCount: 0, offerCount: 0 } });
    }
    if (url.pathname.includes('api-andromeda')) {
      calls.push({ page: request.frame().page(), action: 'andromeda' });
      const body = JSON.parse(request.postData() || '{}');
      return json({ ok: true, data: { provider: 'andromeda', generation: body.generation, page: 1, pages_count: 1, hotels: [] } });
    }
    if (/\/(?:api[^/]*)\.php$/.test(url.pathname)) {
      const action = url.searchParams.get('action');
      calls.push({ page: request.frame().page(), action, params: Object.fromEntries(url.searchParams) });
      if (action === 'countries') return json([{ id: 4, russianName: 'Турция' }]);
      if (action === 'search_start') return json({ searchId: 42 });
      if (action === 'search_status') return json({ status: 'complete', progress: 100 });
      if (action === 'search_results') return url.searchParams.get('searchId') === '42' ? json(hotels) : json({ error: 'Expired fixture' }, 410);
      if (action === 'tour') {
        const id = url.searchParams.get('tourId'), hotel = hotels.find(h => h.tours.some(t => t.id === id));
        assert.ok(hotel, 'exact offer, never canonical hotel ID, is sent to tour API');
        return json({ ...hotel.tours.find(t => t.id === id), hotel: { id: hotel.id, name: 'Supplier label must not replace catalog' } });
      }
      if (action === 'flights') return json([{ price: { value: 125000 }, isDefault: true, forward: [], backward: [] }]);
      return json([]);
    }
    if (request.method() !== 'GET' || /lead|quote-preview/.test(url.pathname)) { failures.push(url.pathname); return route.abort(); }
    if (!url.pathname.startsWith(prefix + '/')) return route.abort();
    // The built artifact is served on its existing CI mount. Only the fixture
    // response paths are mapped to the authorized local-preview mount.
    const response = await route.fetch({ url: base.origin + url.pathname.replace(prefix, base.pathname) + url.search });
    const contentType = response.headers()['content-type'] || '';
    if (/text|javascript|json/.test(contentType)) return route.fulfill({ response, body: (await response.text()).replaceAll(base.pathname, prefix) });
    return route.fulfill({ response });
  });
  const page = await context.newPage();
  try {
    await page.goto(entry + '?' + scenarioQuery, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#results .hotel-card:nth-of-type(3)');
    await page.waitForFunction(() => document.querySelectorAll('#results a[target="_blank"]').length === 3);
    await page.locator('#sortResults').selectOption('rating');
    if (width < 1025) await page.locator('.search3-mobile-filter-panel > summary').click();
    await page.getByRole('searchbox', { name: /Название отеля/ }).fill('Проверочный');
    if (width < 1025) await page.locator('.search3-mobile-filter-panel > summary').click();
    // Mobile shows gallery controls when the existing hotel details are open.
    await page.locator('.hotel-card').first().locator('.hotel-details > summary').click();
    const firstPhoto = page.locator('.hotel-card').first().locator('.hotel-gallery-main');
    const previousPhoto = await firstPhoto.getAttribute('src');
    await page.locator('.hotel-card').first().getByRole('button', { name: 'Поменять главное фото, миниатюра 1' }).click();
    assert.notEqual(await firstPhoto.getAttribute('src'), previousPhoto, 'gallery really switches the main photo');
    const state = () => page.evaluate(() => ({ url: location.href, scroll: scrollY, filter: document.querySelector('input[placeholder="Введите название"]')?.value, sort: document.querySelector('#sortResults').value, photo: document.querySelector('.hotel-card .hotel-photo img')?.src, open: document.querySelector('.hotel-card .hotel-details')?.open, ids: [...document.querySelectorAll('.hotel-card')].map(n => n.dataset.hotelId) }));
    const children = [];
    for (let index = 0; index < 3; index++) {
      const action = page.locator('.hotel-card').nth(index).locator('a.tour-more-toggle');
      await action.scrollIntoViewIfNeeded();
      const box = await action.boundingBox();
      assert.ok(box.height >= 44, 'new-tab action retains full touch target');
      assert.equal(await action.getAttribute('rel'), 'noopener');
      assert.match(await action.getAttribute('aria-label'), /новой вкладке/);
      const before = await state();
      const opened = context.waitForEvent('page');
      await action.click();
      const child = await opened;
      children.push(child);
      await child.waitForSelector('#results .hotel-card .direct-tour');
      assert.equal(await child.locator('#results .hotel-card').count(), 1, 'one canonical hotel per detail tab');
      assert.equal(await child.locator('#results .hotel-card h3').innerText(), profiles[index].name);
      assert.equal(await child.evaluate(() => window.opener === null), true, 'detail has no opener dependency');
      assert.equal(new URL(child.url()).searchParams.get('search3_hotel'), String(901 + index));
      assert.equal(new URL(child.url()).searchParams.get('utm_source'), 'tabs-fixture');
      assert.equal(new URL(child.url()).searchParams.get('child_count'), scenarioQuery.get('child_count'));
      assert.deepEqual(new URL(child.url()).searchParams.getAll('child_age[]'), scenarioQuery.getAll('child_age[]'), 'all child ages survive a new tab, including 0 and 17');
      const after = await state();
      assert.ok(Math.abs(after.scroll - before.scroll) <= 2, 'opening hotel does not move original results');
      delete before.scroll; delete after.scroll;
      assert.deepEqual(after, before, 'URL/filter/sort/photo/expanded detail remain in original tab');
      assert.equal(calls.filter(c => c.page === child && ['search_start', 'local-db', 'andromeda', 'price-observation'].includes(c.action)).length, 0, 'detail never starts a parallel search cohort or observation');
      const trip = await child.locator('#results > .results-state').innerText();
      assert.match(trip, /7–10 ноч\. · 2 взр\./, 'trip context remains visible in the hotel tab');
      if (width === 390) assert.match(trip, /Возраст детей: 0, 7, 17/);
      assert.equal(await child.locator('.results-filter-rail').isVisible(), false, 'hotel detail starts without the search filter rail');
      assert.equal(await child.locator('#results a[target="_blank"]').count(), 0, 'internal hotel actions do not spawn more tabs');
    }
    assert.equal(calls.filter(c => c.action === 'search_start').length, 1, 'three tabs reuse the original search');
    const child = children[0], initialDetailUrl = child.url();
    await child.locator('.tour-list-more').click();
    assert.equal(await child.locator('.direct-tour').count(), 4, 'all exact offers remain reachable');
    await child.locator('.search3-shortlist-toggle').first().click();
    const saved = await child.locator('.search3-shortlist-toggle').first().getAttribute('aria-pressed');
    assert.equal(saved, 'true', 'exact offer is actually saved before testing Back');
    const exact = await child.locator('.direct-tour').first().getAttribute('data-tid');
    await child.locator('.direct-tour').first().click();
    await child.waitForSelector('#selectedTour .flight-variant');
    assert.equal(context.pages().length, 4, 'selecting a tour uses the hotel tab');
    assert.equal(calls.filter(c => c.page === child && c.action === 'tour').at(-1).params.tourId, exact);
    assert.equal(calls.filter(c => c.page === child && c.action === 'flights').at(-1).params.tourId, exact);
    assert.match(await child.locator('#selectedTour').innerText(), /Проверочный отель 1/);
    await child.getByRole('button', { name: '← Вернуться к предложениям', exact: true }).click();
    await child.waitForFunction(() => document.querySelector('#selectedTour').hidden);
    assert.equal(child.url(), initialDetailUrl, 'working same-tab Back retains the addressable hotel');
    assert.equal(await child.locator('.direct-tour').count(), 4);
    assert.equal(await child.locator('.search3-shortlist-toggle').first().getAttribute('aria-pressed'), saved);
    assert.equal(await child.locator('.results-filter-rail').isVisible(), false, 'single hotel does not occupy a results filter rail');
    assert.equal(await child.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'no horizontal overflow');
    for (const action of await child.locator('#results .direct-tour, #results .tour-list-more, #results .results-state a').all()) assert.ok((await action.boundingBox()).height >= 44);
    await child.locator('.hotel-card').scrollIntoViewIfNeeded();
    await child.screenshot({ path: path.join(output, `${engine}-${width}x${height}-detail.png`), fullPage: true, animations: 'disabled' });
    assert.equal(await child.locator('.results-filter-rail').isVisible(), false, 'detail layout remains stable after screenshots and scroll');
    await page.screenshot({ path: path.join(output, `${engine}-${width}x${height}-results.png`), fullPage: true, animations: 'disabled' });
    await child.reload({ waitUntil: 'domcontentloaded' });
    await child.waitForSelector('#results .hotel-card .direct-tour');
    assert.equal(calls.filter(c => c.action === 'search_start').length, 1, 'detail reload only reloads the existing search result');
    const direct = await context.newPage();
    await direct.goto(initialDetailUrl, { waitUntil: 'domcontentloaded' });
    await direct.waitForSelector('#results .hotel-card .direct-tour');
    assert.equal(await direct.evaluate(() => window.opener === null), true);
    const expiredUrl = new URL(initialDetailUrl); expiredUrl.searchParams.set('search3_search', '43');
    await direct.goto(expiredUrl.href, { waitUntil: 'domcontentloaded' });
    await direct.getByText('Этот поиск больше недоступен.', { exact: false }).waitFor();
    assert.equal(await direct.locator('#tourSearch').isVisible(), true, 'expired search keeps explicit recovery available');
    assert.equal(calls.filter(c => c.action === 'search_start').length, 1, 'expiry never silently launches a full search');
    await direct.screenshot({ path: path.join(output, `${engine}-${width}x${height}-expired.png`), fullPage: true, animations: 'disabled' });
    assert.deepEqual(failures, [], 'no lead/booking/unknown writes');
    assert.deepEqual(errors, [], 'no browser exceptions');
    reports.push({ engine, width, height, sourceSha, passed: true, searchStarts: 1, detailTabs: 3, directReload: true, expiredManualRecovery: true, exactOffer: exact });
  } catch (error) {
    for (const [index, current] of context.pages().entries()) {
      fs.writeFileSync(path.join(output, `${engine}-${width}-failure-${index}-layout.json`), JSON.stringify(await current.evaluate(() => ({ rail: document.querySelector('.results-filter-rail')?.outerHTML, trace: window.hotelTabRailTrace })), null, 2));
      await current.screenshot({ path: path.join(output, `${engine}-${width}-failure-${index}.png`), fullPage: true }).catch(() => {});
      fs.writeFileSync(path.join(output, `${engine}-${width}-failure-${index}.html`), await current.content().catch(() => ''));
    }
    fs.writeFileSync(path.join(output, `${engine}-${width}-failure.json`), JSON.stringify({ error: String(error), errors, failures, calls: calls.map(({ page: _, ...call }) => call) }, null, 2));
    throw error;
  } finally { await browser.close(); }
}
(async () => {
  for (const width of [1440, 375, 390, 430]) await run('chromium', width, width === 390 ? 500 : 900);
  await run('webkit', 375, 667);
  fs.writeFileSync(path.join(output, 'hotel-tabs.json'), JSON.stringify({ sourceSha, tests: reports }, null, 2));
  console.log('SEARCH3_HOTEL_TABS_BROWSER_OK', reports.length);
})().catch(error => { console.error(error); process.exitCode = 1; });
