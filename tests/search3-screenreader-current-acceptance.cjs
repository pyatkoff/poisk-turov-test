const { chromium } = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.SEARCH3_SELECTED_ACCEPTANCE_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'acceptance must use isolated local preview');

const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="750"><rect width="1200" height="750" fill="#ddd"/></svg>');
const tour = {
  id: 'screenreader-current-tour', price: 148500,
  hotel: { name: 'SUNRISE Resort & Spa', country: { name: 'Турция' }, region: { name: 'Анталья' }, category: 5 },
  departure: { name: 'Москва' }, date: '2026-10-05', nights: 9, adults: 2, childs: 1,
  meal: { name: 'AI', fullName: 'Всё включено' }, roomType: 'STANDARD LAND VIEW', placement: 'DBL + CHD',
  operator: { name: 'ANEX Tour' }, isCharter: true, picture
};
const port = (name, code) => ({ name, shortName: code, id: code });
const segment = {
  company: { name: 'Test Airline' }, number: 'AT123',
  departure: { port: port('Шереметьево', 'SVO'), date: '2026-10-05', time: '09:30' },
  arrival: { port: port('Анталья', 'AYT'), date: '2026-10-05', time: '14:00' }, baggage: 20, carryOn: '5 кг'
};
const back = { ...segment, number: 'AT124', departure: { ...segment.arrival, date: '2026-10-14', time: '16:00' }, arrival: { ...segment.departure, date: '2026-10-14', time: '20:30' } };
const flights = [
  { isDefault: true, price: { value: 148500 }, forward: [segment], backward: [back] },
  { price: { value: 149900 }, fuelCharge: { value: 1400 }, forward: [segment], backward: [back] }
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 375, height: 1000 } });
  const posts = [];
  const browserErrors = [];
  page.on('pageerror', error => browserErrors.push(String(error)));
  page.on('request', request => { if (request.method() !== 'GET') posts.push({ method: request.method(), url: request.url() }); });
  await page.route('**/*', route => {
    const request = route.request();
    const url = new URL(request.url());
    if (url.origin !== new URL(base).origin || request.method() !== 'GET') return route.abort();
    if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
    return route.continue();
  });
  try {
    const response = await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200);
    await page.waitForFunction(() => window.V2Runtime && window.V2TourController);
    await page.evaluate(({ tour, flights }) => {
      window.V2Runtime.api = async action => {
        if (action === 'tour') return tour;
        if (action === 'flights') return flights;
        throw new Error('unexpected screen-reader fixture action ' + action);
      };
      window.V2TourController.selectTour(tour.id);
    }, { tour, flights });

    const root = page.locator('#selectedTour');
    await root.locator('.search3-flight-continue button').waitFor();
    assert.equal(await root.getByRole('heading', { name: 'SUNRISE Resort & Spa' }).count(), 1, 'selected hotel has one accessible heading');
    assert.equal(await root.getByRole('radio').count(), 2, 'flight choices expose native radio semantics');
    assert.equal(await root.getByRole('radio', { name: /Вариант 1/ }).count(), 1, 'recommended flight has an accessible name');
    assert.equal(await root.getByRole('radio', { name: /Вариант 2/ }).count(), 1, 'alternate flight has an accessible name');
    assert.equal(await root.locator('input[name="v2flight"]:checked').count(), 1, 'one flight choice is exposed as selected');
    const continueButton = root.locator('.search3-flight-continue button');
    assert.ok((await continueButton.innerText()).trim(), 'continue action has a visible accessible name');

    await continueButton.click();
    await page.waitForSelector('#selectedTour.search3-lead-entry .lead-form input[name="phone"]');
    assert.equal(await root.getByRole('textbox', { name: 'Телефон (обязательно)' }).count(), 1, 'phone has an associated accessible label');
    assert.equal(await root.getByRole('checkbox', { name: /Согласен на обработку персональных данных/ }).count(), 1, 'consent has an associated accessible label');
    assert.equal(await root.getByRole('button', { name: 'Отправить заявку' }).count(), 1, 'lead submit has one accessible action name');
    assert.equal(await root.locator('.lead-message[aria-live="polite"]').count(), 1, 'lead feedback is exposed as a polite live region');
    assert.equal(await page.evaluate(() => document.activeElement?.name), 'phone', 'lead transition moves focus to the required phone field');
    assert.deepEqual(posts, [], 'screen-reader acceptance sends no POST requests');
    assert.deepEqual(browserErrors, [], 'screen-reader fixture has no browser errors');
    console.log('SEARCH3_SCREENREADER_CURRENT_OK heading=1 radios=2 phone=labelled consent=labelled submit=labelled live=polite focus=phone real_leads=0 supplier_requests=0');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
