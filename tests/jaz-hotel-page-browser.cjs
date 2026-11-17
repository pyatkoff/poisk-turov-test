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
// Existing AnyTour99 gallery, checked 2026-11-16; these are not category-specific photos.
const interiors = [
  { id: '19573321', sha256: '741f915e2485a645cea442534213dbe32931373d1bc1ddd3a55cd85cf0884ab7' },
  { id: '19573324', sha256: '9f01e75f1e39679bf861dfdab80015b1c7be2504d9258064058d6530608fc4e8' },
  { id: '19573318', sha256: '52daec89203df841421e05b85cfa019933730c9a0bed8ea810a77029833cc1d4' }
].map(asset => ({ ...asset, url: `https://static.tourvisor.ru/hotel_pics/verybig/1/iberotel-makadi-saraya-resort-${asset.id}.jpg` }));
const officialMedia = [
  { caption: 'One-Bedroom Apartment', url: 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010907One%20Bedroom%20Apartment%20-%20Living%20Room%20Garden%20View.png' },
  { caption: 'Two-Bedroom Apartment', url: 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010906Two%20Bedroom%20Apartment%20-%20Living%20Garden%20View%20(2).png' },
  { caption: 'Family Deluxe', url: 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/000957Family%20Deluxe%20-%20Queen%20Garden%20View%20Living.png' },
  { caption: 'Бассейны', url: 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010900Drone%20-%20Saraya%20Resort%20Pool%20Overview.png' },
  { caption: 'JAZY Kids Club', url: 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010943Jazy%20Kids%20Club.png' },
  { caption: 'Sunflower', url: 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010908Sunflower%20Main%20Restaurant%20-%20Indoor.png' },
];
const photoAssets = new Map([[photoUrl, photo]]);
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
  assets: { photo: { url: photoUrl, sha256: sha256(photo) }, interiors, officialMedia, logo: { url: 'https://anytoour.ru/images/logo.svg', sha256: sha256(logo) } },
  cases: [], passed: false
};


function priceObservationFixture(url, mode) {
  const date = futureDate(65);
  const now = new Date();
  const offer = {
    tourvisorHotelId: 268, tourId: '16307652173334', searchId: '14177077157',
    observationId: '4360819', departureId: 1, countryId: 1,
    departureDate: date, nights: 5, adults: 2, childrenCount: 0,
    mealId: 7, roomId: 813214, roomType: 'superior room garden view',
    operatorId: 16, price: 109971, fuelCharge: 14011, currency: 'RUB',
    observedAtRaw: new Date(Date.now() - 120000).toISOString().replace('T', ' ').slice(0, 19),
    source: 'scheduled_monitor', priceKind: 'listing_observation',
    recordId: 'a'.repeat(64), ageSecondsAtExport: 120,
    expiresAt: new Date(Date.now() + (mode === 'expired' ? -60000 : 550000)).toISOString(),
    currentPriceVerified: false, fullPackagePriceVerified: false,
    fuelIncluded: null, availabilityVerified: false
  };
  return {
    ok: true, format: 'anytour-hotel-monitor-prices-v1',
    generatedAt: now.toISOString(), identityNamespace: 'tourvisor',
    source: 'scheduled_monitor', observationWindowHours: 36,
    supplierRequests: 0, writes: 0, automaticDispatch: false,
    scope: {
      tourvisorHotelIds: [268], departureId: 1,
      dateFrom: url.searchParams.get('dateFrom'), dateTo: url.searchParams.get('dateTo'),
      nightsFrom: 5, nightsTo: 14, adults: 2,
      childrenCount: mode === 'wrong-party' ? 1 : 0, currency: 'RUB'
    },
    items: [{
      tourvisorHotelId: mode === 'wrong-hotel' ? 269 : 268,
      state: mode === 'empty' ? 'no_fresh_observation' : 'observed',
      offers: mode === 'empty' ? [] : [offer]
    }]
  };
}

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
    // Only these three fixed, previously inspected image URLs are fetched outside the browser.
    // The browser still cannot reach any live catalogue, supplier or lead endpoint.
    await Promise.all(interiors.map(async asset => {
      const response = await fetch(asset.url, { signal: AbortSignal.timeout(30000) });
      assert.equal(response.status, 200, `interior photo ${asset.id} is unavailable`);
      const bytes = Buffer.from(await response.arrayBuffer());
      assert.equal(sha256(bytes), asset.sha256, `interior photo ${asset.id} changed since visual acceptance`);
      fs.writeFileSync(path.join(output, `jaz-${asset.id}.jpg`), bytes);
      photoAssets.set(asset.url, bytes);
    }));
    await Promise.all(officialMedia.map(async asset => {
      const response = await fetch(asset.url, { signal: AbortSignal.timeout(30000) });
      assert.equal(response.status, 200, 'official photo unreachable: ' + asset.caption);
      const bytes = Buffer.from(await response.arrayBuffer());
      assert.ok(bytes.length > 1000 && bytes.length < 8000000, 'official photo size unexpected');
      asset.sha256 = sha256(bytes);
      asset.size = bytes.length;
      fs.writeFileSync(path.join(output, 'jaz-official-' + officialMedia.indexOf(asset) + '.png'), bytes);
      photoAssets.set(asset.url, bytes);
    }));
    browser = await chromium.launch({ headless: true });
    for (const viewport of [{ width: 430, height: 932 }, { width: 1280, height: 720 }]) {
      const page = await browser.newPage({ viewport, hasTouch: viewport.width === 430 });
      const record = { viewport, passed: false, searchStartAttempts: 0, errors: [], requests: [] };
      let failedPhotoUrl = null, failedPhotoRequests = 0;
      let priceScenario = 'observed';
      record.priceRequests = [];
      report.cases.push(record);
      page.on('pageerror', error => record.errors.push(String(error)));
      await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (photoAssets.has(url.href)) {
          if (url.href === failedPhotoUrl) { failedPhotoRequests++; return route.abort('failed'); }
          return route.fulfill({ status: 200, contentType: url.pathname.endsWith('.png') ? 'image/png' : 'image/jpeg', body: photoAssets.get(url.href) });
        }
        if (url.origin === baseUrl.origin && url.pathname === '/images/logo.svg') {
          return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: logo });
        }
        // Never permit the browser to reach a live catalogue, supplier or lead endpoint.
        if (url.origin !== baseUrl.origin || request.method() !== 'GET') return route.abort();
        const action = url.searchParams.get('action');
        if (url.pathname === '/data/hotel-monitor-prices-read-v1.php') {
          record.priceRequests.push({
            method: request.method(),
            hotelIds: url.searchParams.getAll('tourvisorHotelIds[]'),
            departure: url.searchParams.get('departureId'),
            from: url.searchParams.get('dateFrom'),
            to: url.searchParams.get('dateTo'),
            mode: priceScenario
          });
          assert.equal(request.method(), 'GET', 'price observer must be GET-only');
          if (priceScenario === 'error') {
            return route.fulfill({ status: 503, contentType: 'application/json', body: '{"ok":false}' });
          }
          return route.fulfill({
            status: 200, contentType: 'application/json',
            body: JSON.stringify(priceObservationFixture(url, priceScenario))
          });
        }
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
        const serverHtml = await response.text();
        record.stay = await page.locator('#stay').innerText();
        const quickNav = await page.locator('.jaz-quick-nav a').evaluateAll(elements => elements.map(element => element.getAttribute('href')));
        assert.deepEqual(quickNav, ['#price-example', '#interiors', '#rooms', '#stay', '#location', '#questions']);
        assert.match(serverHtml, /Частые вопросы о JAZ Makadi Saraya/, 'FAQ must be in server-rendered HTML');
        assert.match(serverHtml, /Семейный отдых в Макади-Бей/, 'main benefit must be in server-rendered HTML');
        assert.equal(await page.locator('#questions details').count(), 6);
        const firstQuestion = page.locator('#questions details').first();
        await firstQuestion.locator('summary').click();
        assert.equal(await firstQuestion.evaluate(element => element.open), true);
        assert.match(await firstQuestion.innerText(), /300–500 м/);
        await page.locator('#questions').screenshot({ path: path.join(output, `jaz-${viewport.width}-questions.png`), animations: 'disabled' });
        await firstQuestion.locator('summary').click();
        assert.equal(await firstQuestion.evaluate(element => element.open), false);
        assert.equal(await page.locator('.jaz-room-photos img').count(), 3, 'room images must remain but long comparison is removed');
        assert.equal(await page.locator('.jaz-room dl').count(), 0, 'room spec cards are no longer primary');
        record.wayfinding = { nav: quickNav, faqQuestions: 6, faqToggle: true, searchCtAs: 2, compactRooms: true };
        for (const fact of [/JAZY/, /Sunflower/, /Al Nakhil/]) {
          assert.match(serverHtml, fact, 'hotel stay facts must be in the server HTML');
          assert.match(record.stay, fact, 'hotel stay facts must be visible in their section');
        }
        await page.locator('.jaz-hero__photo img').evaluate(img => img.decode());
        for (const img of await page.locator('.jaz-gallery img').all()) {
          await img.scrollIntoViewIfNeeded();
          await img.evaluate(element => element.decode());
        }
        const officialImgs = page.locator('.jaz-official-photo img');
        assert.equal(await officialImgs.count(), officialMedia.length, 'must show exactly 6 official media candidates');
        assert.equal(await page.locator('.jaz-official-photo figcaption').count(), 0, 'source/rights boilerplate must not clutter commercial cards');
        assert.match(await page.locator('.jaz-photo-hint').innerText(), /10 фото/, 'hero photo count must include new images');
        for (const img of await officialImgs.all()) {
          await img.scrollIntoViewIfNeeded();
          await img.evaluate(element => element.decode());
        }
        record.officialMedia = await officialImgs.evaluateAll(images => images.map(image => ({
          src: image.src, width: image.naturalWidth, height: image.naturalHeight
        })));
        assert.deepEqual(record.officialMedia.map(x => x.src), officialMedia.map(x => x.url));
        assert.ok(record.officialMedia.every(x => x.width >= 300 && x.height >= 200), 'official photos did not decode');
        assert.match(serverHtml, /partner.jazhotels.com\/hotel\/20/, 'exact-hotel source missing');
        record.interiors = await page.locator('.jaz-gallery img').evaluateAll(images => images.map(img => ({
          url: img.src, width: img.naturalWidth, height: img.naturalHeight
        })));
        assert.deepEqual(record.interiors, interiors.map(asset => ({ url: asset.url, width: 1024, height: 683 })));
        await page.evaluate(() => scrollTo({ top: 0, left: 0, behavior: 'instant' }));
        assert.equal(await page.evaluate(() => scrollY), 0, 'hero capture must start at the top after lazy-image checks');
        record.geometry = await page.evaluate(() => ({
          width: innerWidth, scrollWidth: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight,
          photoWidth: document.querySelector('.jaz-hero__photo img').naturalWidth,
          cta: document.querySelector('[data-hotel-search]').getBoundingClientRect().toJSON()
        }));
        assert.ok(record.geometry.scrollWidth <= viewport.width + 1, 'horizontal overflow on hotel page');
        assert.equal(record.geometry.photoWidth, 1024, 'accepted photo did not load');
        assert.ok(record.geometry.cta.height >= 44, 'primary CTA is too short for ordinary taps');
        const links = await page.locator('[data-hotel-search]').evaluateAll(elements => elements.map(element => element.getAttribute('href')));
        assert.equal(links.length, 2, 'both official hotel search links must be retained');
        assert.ok(links.every(link => link === links[0]), 'hotel CTAs must share the same validated trip context');
        const searchUrl = new URL(links[0], baseUrl.origin);
        assert.equal(searchUrl.pathname, baseUrl.pathname + '/poisk-turov/');
        assert.equal(searchUrl.searchParams.get('search3_restore'), '1');
        assert.deepEqual(searchUrl.searchParams.getAll('child_age[]'), ['0', '17']);
        await page.screenshot({ path: path.join(output, `jaz-${viewport.width}-full.png`), fullPage: true, animations: 'disabled' });
        await page.screenshot({ path: path.join(output, `jaz-${viewport.width}-hero.png`), animations: 'disabled' });
        await page.locator('#interiors').screenshot({ path: path.join(output, `jaz-${viewport.width}-interiors.png`), animations: 'disabled' });
        await page.locator('#rooms').screenshot({ path: path.join(output, `jaz-${viewport.width}-rooms.png`), animations: 'disabled' });
        await page.locator('#stay').screenshot({ path: path.join(output, `jaz-${viewport.width}-stay.png`), animations: 'disabled' });
        // Price observation is opt-in. A page visit never reads the monitor or starts a supplier search.
        const pricePanel = page.locator('[data-jaz-price-example]');
        assert.equal(await pricePanel.count(), 1);
        assert.ok(await pricePanel.evaluate(element => !!element.closest('.jaz-hero')), 'lowest price must be in hero');
        await page.waitForFunction(() => document.querySelector('[data-jaz-price-example]')?.dataset.priceState === 'observed');
        assert.equal(record.priceRequests.length, 1, 'one automatic read-only monitor export per page view');
        assert.ok(await pricePanel.locator('[data-price-result]').isVisible());
        assert.match(serverHtml, /Найдённая минимальная цена/);
        const priceTop = await pricePanel.evaluate(element => element.getBoundingClientRect().top);
        if (viewport.width === 430) assert.ok(priceTop < viewport.height, 'minimum price block must be in first mobile viewport');
        const originalSearchLinks = await page.locator('[data-hotel-search]').evaluateAll(elements => elements.map(element => element.getAttribute('href')));
        assert.equal(originalSearchLinks.length, 2);
        assert.deepEqual(record.priceRequests[0].hotelIds, ['268']);
        assert.equal(record.priceRequests[0].departure, '1');
        assert.match(await pricePanel.locator('[data-price-amount]').innerText(), /^от\s/);
        assert.match((await pricePanel.locator('[data-price-amount]').innerText()).replace(/\D/g, ''), /^109971$/);
        const priceDetails = await pricePanel.locator('[data-price-terms]').innerText();
        assert.match(priceDetails, /Москва/);
        assert.match(priceDetails, /superior room garden view/);
        const priceWarnings = await pricePanel.locator('[data-price-extra]').innerText();
        assert.match(priceWarnings, /14/);
        assert.match(priceWarnings, /Наличие не проверено/);
        assert.match(await pricePanel.locator('.jaz-price__tag').innerText(), /не итоговая стоимость/);
        const priceLink = new URL(await pricePanel.locator('[data-price-search-link]').getAttribute('href'), baseUrl.origin);
        assert.equal(priceLink.pathname, baseUrl.pathname + '/poisk-turov/');
        assert.equal(priceLink.searchParams.get('hotel'), '268');
        assert.equal(priceLink.searchParams.get('country'), '1');
        assert.equal(priceLink.searchParams.get('region'), '5');
        assert.equal(priceLink.searchParams.get('from'), '1');
        assert.equal(priceLink.searchParams.get('count_people'), '2');
        assert.equal(priceLink.searchParams.get('child_count'), '0');
        assert.equal(priceLink.searchParams.get('dateFrom'), futureDate(65));
        assert.equal(priceLink.searchParams.get('dateTo'), futureDate(65));
        assert.equal(priceLink.searchParams.get('daysFrom'), '5');
        assert.equal(priceLink.searchParams.get('daysTill'), '5');
        assert.equal(priceLink.searchParams.get('food'), '7');
        assert.equal(priceLink.searchParams.get('search3_restore'), '1');
        assert.equal(priceLink.searchParams.has('child_age[]'), false);
        assert.equal(priceLink.searchParams.has('price_from'), false);
        await pricePanel.screenshot({ path: path.join(output, `jaz-${viewport.width}-price.png`), animations: 'disabled' });
        record.priceObserved = { amount: 109971, fuel: 14011, link: priceLink.href, requests: record.priceRequests.length };
        // The example link must restore the example's own criteria in Search3,
        // never the original family trip, and must not auto-start supplier search.
        await pricePanel.locator('[data-price-search-link]').click();
        await page.waitForURL(url => url.pathname === baseUrl.pathname + '/poisk-turov/');
        await page.waitForFunction(() => document.forms.tourSearch?.dataset.search3Ready === '1');
        await page.waitForFunction(() => document.getElementById('status')?.textContent.includes('Параметры сохранённого поиска восстановлены'));
        const exampleForm = await page.locator('#tourSearch').evaluate(form => ({
          from: form.elements.from.value, country: form.elements.country.value,
          region: form.elements.region.value, hotel: form.elements.hotel.value,
          dateFrom: form.elements.dateFrom.value, dateTo: form.elements.dateTo.value,
          nightsFrom: form.elements.daysFrom.value, nightsTill: form.elements.daysTill.value,
          adults: form.elements.count_people.value, children: form.elements.child_count.value,
          food: form.elements.food.value,
          ages: [...form.querySelectorAll('[name="child_age[]"]')].map(x => x.value)
        }));
        assert.deepEqual(exampleForm, {
          from: '1', country: '1', region: '5', hotel: '268',
          dateFrom: futureDate(65), dateTo: futureDate(65),
          nightsFrom: '5', nightsTill: '5', adults: '2',
          children: '0', food: '7', ages: []
        });
        assert.equal(record.searchStartAttempts, 0, 'observed-price handoff auto-started supplier search');
        await page.goBack({ waitUntil: 'networkidle' });
        assert.equal(page.url(), url, 'price example Back lost original trip URL');
        assert.deepEqual(await page.locator('[data-hotel-search]').evaluateAll(elements => elements.map(element => element.getAttribute('href'))), originalSearchLinks);
        record.priceHandoff = { form: exampleForm, backRetained: true, supplierStarts: 0 };
        const readsBeforeFallbacks = record.priceRequests.length;
        priceScenario = 'expired';
        await pricePanel.locator('[data-price-load]').click();
        await page.waitForFunction(() => document.querySelector('[data-jaz-price-example]')?.dataset.priceState === 'empty');
        assert.equal(await pricePanel.locator('[data-price-result]').isVisible(), false, 'expired offer still shown');
        priceScenario = 'wrong-party';
        await pricePanel.locator('[data-price-load]').click();
        await page.waitForFunction(() => document.querySelector('[data-jaz-price-example]')?.dataset.priceState === 'empty');
        assert.equal(await pricePanel.locator('[data-price-result]').isVisible(), false, 'wrong-party offer shown');
        priceScenario = 'wrong-hotel';
        await pricePanel.locator('[data-price-load]').click();
        await page.waitForFunction(() => document.querySelector('[data-jaz-price-example]')?.dataset.priceState === 'empty');
        assert.equal(await pricePanel.locator('[data-price-result]').isVisible(), false, 'different hotel price shown');
        priceScenario = 'empty';
        await pricePanel.locator('[data-price-load]').click();
        await page.waitForFunction(() => document.querySelector('[data-jaz-price-example]')?.dataset.priceState === 'empty');
        assert.match(await pricePanel.locator('[data-price-status]').innerText(), /не означает, что туров нет/);
        priceScenario = 'error';
        await pricePanel.locator('[data-price-load]').click();
        await page.waitForFunction(() => document.querySelector('[data-jaz-price-example]')?.dataset.priceState === 'error');
        assert.equal(await pricePanel.locator('[data-price-result]').isVisible(), false);
        assert.equal(record.priceRequests.length, readsBeforeFallbacks + 5, 'five distinct fallback refreshes required');
        assert.deepEqual(await page.locator('[data-hotel-search]').evaluateAll(elements => elements.map(element => element.getAttribute('href'))), originalSearchLinks, 'price example replaced visitor search links');
        assert.equal(record.searchStartAttempts, 0);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
        record.priceFallbacks = { expired: true, wrongParty: true, wrongHotel: true, missing: true, error: true };

        const photoLinks = page.locator('[data-jaz-photo]');
        const expectedPhotos = [photoUrl, ...interiors.map(asset => asset.url), ...officialMedia.map(asset => asset.url)];
        const expectedCaptions = ['Территория отеля', 'Гостиная', 'Обеденная зона', 'Спальня', ...officialMedia.map(asset => asset.caption)];
        const photos = await photoLinks.evaluateAll(elements => elements.map(element => ({
          href: element.href, caption: element.dataset.photoCaption, note: element.dataset.photoNote,
          alt: element.querySelector('img').alt, target: element.target,
          safeOriginal: element.relList.contains('noopener') && element.relList.contains('noreferrer')
        })));
        assert.deepEqual(photos.map(item => item.href), expectedPhotos);
        assert.equal(photos.length, 10, 'all four accepted and six official assets must be viewable in one dialog');
        assert.deepEqual(photos.map(item => item.caption), expectedCaptions);
        assert.deepEqual(photos.map(item => item.note), [
          'Фото из каталога Tourvisor',
          ...interiors.map(() => 'Интерьер отеля; категория номера не указана'),
          ...officialMedia.map((_, index) => index < 3 ? 'Официальная фотография категории; конкретный номер, площадь и вид при бронировании не подтверждены' : 'Официальная фотография инфраструктуры; доступность и включение услуг зависят от тарифа и дат')
        ]);
        assert.ok(photos.every(item => item.target === '_blank' && item.safeOriginal), 'original photo fallbacks must remain available');
        const viewer = page.locator('dialog.jaz-photo-viewer');
        const activate = locator => viewport.width === 430 ? locator.tap() : locator.click();
        let photoPopups = 0;
        page.on('popup', popup => { photoPopups++; popup.close().catch(() => {}); });
        const checkPhoto = async index => {
          await page.waitForFunction(expected => {
            const dialog = document.querySelector('dialog.jaz-photo-viewer');
            const image = dialog?.querySelector('img');
            return dialog?.open && dialog.dataset.photoState === 'loaded' && image?.src === expected && image.naturalWidth > 0 && !image.hidden;
          }, expectedPhotos[index]);
          const state = await viewer.evaluate(dialog => ({
            src: dialog.querySelector('img').src, alt: dialog.querySelector('img').alt,
            title: dialog.querySelector('h2').textContent, note: dialog.querySelector('.jaz-viewer-note').textContent,
            counter: dialog.querySelector('.hotel-photo-viewer__counter').textContent,
            original: dialog.querySelector('.hotel-photo-viewer__original').href,
            box: dialog.getBoundingClientRect().toJSON(), scrollWidth: dialog.scrollWidth, clientWidth: dialog.clientWidth
          }));
          assert.equal(state.src, expectedPhotos[index]);
          assert.equal(state.original, expectedPhotos[index]);
          assert.equal(state.alt, photos[index].alt);
          assert.equal(state.title, photos[index].caption);
          assert.equal(state.note, photos[index].note);
          assert.equal(state.counter, `Фото ${index + 1} из ${expectedPhotos.length}`);
          assert.ok(state.box.left >= 0 && state.box.top >= 0 && state.box.right <= viewport.width + 1 && state.box.bottom <= viewport.height + 1, 'photo viewer must fit the viewport');
          assert.ok(state.scrollWidth <= state.clientWidth + 1, 'horizontal overflow in the photo viewer');
          assert.equal(page.url(), url, 'photo viewer must retain the hotel page query');
          return state;
        };
        record.viewer = [];
        for (const index of [0, 2, 4, 7, 9]) {
          const opener = photoLinks.nth(index);
          await opener.evaluate(element => element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' }));
          const before = await page.evaluate(() => ({ x: scrollX, y: scrollY }));
          await activate(opener);
          const selected = await checkPhoto(index);
          await activate(viewer.locator('[data-photo-step="1"]'));
          await checkPhoto((index + 1) % expectedPhotos.length);
          await activate(viewer.locator('[data-photo-step="-1"]'));
          await checkPhoto(index);
          if (index === 0) {
            await activate(viewer.locator('[data-photo-step="-1"]'));
            await checkPhoto(expectedPhotos.length - 1);
            await activate(viewer.locator('[data-photo-step="1"]'));
            await checkPhoto(0);
          } else {
            await page.screenshot({ path: path.join(output, `jaz-${viewport.width}-viewer.png`), animations: 'disabled' });
          }
          await activate(viewer.locator('.hotel-photo-viewer__close'));
          await viewer.waitFor({ state: 'hidden' });
          const after = await page.evaluate(() => ({ x: scrollX, y: scrollY, locked: document.documentElement.classList.contains('hotel-photo-viewer-open') }));
          assert.ok(Math.abs(after.x - before.x) <= 1 && Math.abs(after.y - before.y) <= 1, 'closing photos must restore the page position');
          assert.equal(after.locked, false, 'closing photos must release the page scroll');
          assert.ok(await opener.evaluate(element => document.activeElement === element), 'closing photos must return to the opener');
          assert.equal(page.url(), url);
          assert.deepEqual(await page.locator('[data-hotel-search]').evaluateAll(elements => elements.map(element => element.getAttribute('href'))), links);
          record.viewer.push({ openedIndex: index, selected, closed: true, contextRetained: true, scrollRestored: true });
        }
        assert.equal(photoPopups, 0, 'ordinary photo activation must stay in the page');
        if (viewport.width === 430) {
          // One transport-failure fixture. Reload the same query so a decoded
          // thumbnail cannot hide the failed request; do not fabricate error events.
          failedPhotoUrl = expectedPhotos[1];
          await page.reload({ waitUntil: 'networkidle' });
          const opener = photoLinks.nth(1);
          await opener.evaluate(element => element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' }));
          const before = await page.evaluate(() => ({ x: scrollX, y: scrollY }));
          await activate(opener);
          await page.waitForFunction(() => {
            const dialog = document.querySelector('dialog.jaz-photo-viewer');
            return dialog?.open && dialog.dataset.photoState === 'error';
          });
          assert.ok(failedPhotoRequests > 0, 'the failed image request must actually be exercised');
          assert.equal(await viewer.locator('img').isVisible(), false, 'a failed image must not leave the previous photo visible');
          assert.match(await viewer.locator('.hotel-photo-viewer__status').innerText(), /Не удалось загрузить фото/);
          assert.equal(await viewer.locator('.hotel-photo-viewer__original').getAttribute('href'), expectedPhotos[1]);
          assert.equal(await viewer.locator('.hotel-photo-viewer__counter').innerText(), `Фото 2 из ${expectedPhotos.length}`);
          assert.equal(page.url(), url);
          await page.screenshot({ path: path.join(output, 'jaz-430-viewer-error.png'), animations: 'disabled' });
          failedPhotoUrl = null;
          await activate(viewer.locator('[data-photo-step="1"]'));
          await checkPhoto(2);
          await activate(viewer.locator('.hotel-photo-viewer__close'));
          await viewer.waitFor({ state: 'hidden' });
          const after = await page.evaluate(() => ({ x: scrollX, y: scrollY, locked: document.documentElement.classList.contains('hotel-photo-viewer-open') }));
          assert.ok(Math.abs(after.x - before.x) <= 1 && Math.abs(after.y - before.y) <= 1, 'photo failure recovery must retain the page position');
          assert.equal(after.locked, false);
          assert.ok(await opener.evaluate(element => document.activeElement === element));
          assert.equal(page.url(), url);
          assert.equal(photoPopups, 0);
          record.viewerRecovery = { failedUrl: expectedPhotos[1], failedRequests: failedPhotoRequests, recoveredIndex: 2, contextRetained: true, passed: true };
        }
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
