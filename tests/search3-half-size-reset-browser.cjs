/* Owner-authorized reset smoke: core journey only, with every external request blocked. */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.SEARCH3_VISUAL_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'isolated local preview required');
const output = process.env.SEARCH3_RESET_OUTPUT;
assert.ok(output, 'evidence output required');
fs.mkdirSync(output, { recursive: true });

const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><path fill="#9ac7df" d="M0 0h900v600H0z"/></svg>');
const tour = {
  id: 'reset-smoke-tour', price: 148500, date: '2026-09-12', nights: 9, adults: 2, childs: 1,
  hotel: { name: 'Проверочный отель', country: { name: 'Турция' }, region: { name: 'Анталья' } },
  departure: { name: 'Москва' }, meal: { name: 'Всё включено' }, roomType: 'STANDARD',
  placement: 'DBL + CHD', operator: { name: 'TEST OPERATOR' }, picture
};
const segment = {
  company: { name: 'Test airline' }, number: 'AB123', baggage: 20, carryOn: '5 кг',
  departure: { name: 'Москва', airport: { name: 'Шереметьево', code: 'SVO' }, time: '09:30' },
  arrival: { name: 'Анталья', airport: { name: 'Анталья', code: 'AYT' }, time: '14:00' }
};
const flights = [{ isDefault: true, price: { value: 148500 }, forward: [segment], backward: [{ ...segment, number: 'AB124' }] }];

async function settle(page) {
  await page.evaluate(async () => {
    await document.fonts.ready;
    for (let i = 0; i < 3; i++) await new Promise(resolve => requestAnimationFrame(resolve));
  });
}

async function run(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  const errors = [];
  const blocked = [];
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request();
    const url = new URL(request.url());
    const local = url.origin === new URL(base).origin;
    const forbidden = request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname);
    if (!local || forbidden) {
      blocked.push(`${request.method()} ${url.pathname}`);
      return route.abort();
    }
    return route.continue();
  });
  try {
    const response = await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200, 'isolated Search3 route loads');
    assert.equal(await page.locator('body').evaluate(node => node.classList.contains('search3-candidate')), true);
    await page.waitForFunction(() => window.V2Runtime && window.V2Results && window.V2TourController && window.Search3SummaryCta);

    for (const selector of ['#tourSearch input[type=date]', '#tourSearch select.search3-direct-control', '#tourSearch .search-submit']) {
      const control = page.locator(selector).first();
      assert.equal(await control.isVisible(), true, `${selector} stays visible`);
      assert.ok((await control.boundingBox()).height >= 44, `${selector} keeps a native tap target`);
    }
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'entry has no horizontal overflow');

    await page.evaluate(({ tour, flights, picture }) => {
      window.__resetSmokeCalls = { tour: 0, flights: 0, other: 0 };
      window.V2Runtime.api = async action => {
        if (action === 'tour') { window.__resetSmokeCalls.tour++; return tour; }
        if (action === 'flights') { window.__resetSmokeCalls.flights++; return flights; }
        window.__resetSmokeCalls.other++;
        throw new Error('unexpected fixture API action ' + action);
      };
      window.V2Results.render([{
        id: 'reset-smoke-hotel', name: tour.hotel.name, country: tour.hotel.country,
        region: tour.hotel.region, price: tour.price, picturelink: picture, tours: [tour]
      }]);
    }, { tour, flights, picture });
    const directTour = page.locator('#results .direct-tour').first();
    await directTour.waitFor();
    await directTour.click();
    await page.waitForFunction(() => window.V2FlightEmptyRecoveryV1);
    await page.waitForSelector('#selectedTour .search3-flight-continue button');
    assert.match(await page.locator('#selectedTour').innerText(), /148[\s\u00a0]*500/, 'selected price remains visible');
    await page.locator('#selectedTour .search3-flight-continue button').click();
    await page.waitForSelector('#selectedTour.search3-final-review .search3-summary-submit');
    await page.locator('#selectedTour .search3-summary-submit').click();
    await page.waitForSelector('#selectedTour.search3-lead-entry .lead-form input[name="phone"]');
    assert.equal(await page.locator('#selectedTour .lead-form button[type=submit]').isVisible(), true, 'lead submit remains reachable');
    await settle(page);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'core journey has no horizontal overflow');
    assert.deepEqual(await page.evaluate(() => window.__resetSmokeCalls), { tour: 1, flights: 1, other: 0 });
    assert.deepEqual(errors, [], 'core journey has no browser errors');

    const evidence = { width, blocked, body: await page.locator('body').getAttribute('class'), coreCalls: { tour: 1, flights: 1, other: 0 } };
    fs.writeFileSync(path.join(output, `reset-${width}.json`), JSON.stringify(evidence, null, 2) + '\n');
    await page.screenshot({ path: path.join(output, `reset-${width}.png`), fullPage: true });
  } finally {
    await page.close();
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [375, 1440]) await run(browser, width);
  } finally {
    await browser.close();
  }
  console.log('SEARCH3_HALF_SIZE_RESET_BROWSER_OK states=entry,detail,review,lead widths=375,1440 lead_sent=0');
})().catch(error => { console.error(error); process.exitCode = 1; });
