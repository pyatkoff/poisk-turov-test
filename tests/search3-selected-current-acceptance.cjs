const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.SEARCH3_SELECTED_ACCEPTANCE_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'acceptance must use the isolated local preview');
const output = process.env.SEARCH3_SELECTED_ACCEPTANCE_OUTPUT;
assert.ok(output, 'evidence output required');
fs.mkdirSync(output, { recursive: true });
const contract = JSON.parse(fs.readFileSync(path.join(__dirname, '../src/search3/acceptance/selected-lead-current.json'), 'utf8'));

const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="750"><rect width="1200" height="750" fill="#dce8f5"/><rect x="160" y="120" width="880" height="630" rx="40" fill="#f4e7d2"/></svg>');
const tour = {
  id: 'selected-current-acceptance-tour',
  price: 148500,
  hotel: { name: 'SUNRISE Resort & Spa', country: { name: 'Турция' }, region: { name: 'Анталья' }, category: 5 },
  departure: { name: 'Москва' },
  date: '2026-10-05',
  nights: 9,
  adults: 2,
  childs: 1,
  meal: { name: 'AI', fullName: 'Всё включено' },
  roomType: 'STANDARD LAND VIEW',
  placement: 'DBL + CHD',
  operator: { name: 'ANEX Tour' },
  isCharter: true,
  picture,
  hotelDescription: 'Проверочное описание выбранного отеля. '.repeat(14)
};
const port = (name, code) => ({ name, shortName: code, id: code });
const segment = {
  company: { name: 'Test Airline' },
  number: 'AT123',
  departure: { port: port('Шереметьево', 'SVO'), date: '2026-10-05', time: '09:30' },
  arrival: { port: port('Анталья', 'AYT'), date: '2026-10-05', time: '14:00' },
  baggage: 20,
  carryOn: '5 кг'
};
const flights = [{ isDefault: true, price: { value: 148500 }, forward: [segment], backward: [{ ...segment, number: 'AT124', departure: { ...segment.arrival, date: '2026-10-14', time: '16:00' }, arrival: { ...segment.departure, date: '2026-10-14', time: '20:30' } }] }];

const settle = page => page.evaluate(async () => {
  await document.fonts.ready;
  for (let i = 0; i < 3; i++) await new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 0)));
  await new Promise(resolve => requestAnimationFrame(resolve));
});
const columnCount = value => String(value || '').trim().split(/\s+/).filter(Boolean).length;

async function run(browser, width) {
  const expected = contract.widths[String(width)];
  assert.ok(expected, `missing acceptance contract for ${width}`);
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  const posts = [];
  const browserErrors = [];
  page.on('pageerror', error => browserErrors.push(String(error)));
  page.on('request', request => { if (request.method() !== 'GET') posts.push({ method: request.method(), url: request.url() }); });
  await page.route('**/*', route => {
    const request = route.request();
    const url = new URL(request.url());
    if (url.origin !== new URL(base).origin) return route.abort();
    if (request.method() !== 'GET') return route.abort();
    if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
    return route.continue();
  });
  try {
    const response = await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200, 'isolated Search3 route must load');
    await page.waitForFunction(() => window.V2Runtime && window.V2TourController && window.Search3SummaryCta);
    await page.evaluate(({ tour, flights }) => {
      window.__selectedAcceptanceCalls = { tour: 0, flights: 0, other: 0 };
      window.V2Runtime.api = async action => {
        if (action === 'tour') { window.__selectedAcceptanceCalls.tour++; return tour; }
        if (action === 'flights') { window.__selectedAcceptanceCalls.flights++; return flights; }
        window.__selectedAcceptanceCalls.other++;
        throw new Error('unexpected acceptance API action ' + action);
      };
      window.V2TourController.selectTour(tour.id);
    }, { tour, flights });

    const root = page.locator('#selectedTour');
    await root.locator('.search3-flight-continue button').waitFor();
    await page.waitForFunction(() => document.body.classList.contains('search3-selected-open'));
    await settle(page);

    const detail = await root.evaluate(node => {
      const style = selector => getComputedStyle(node.querySelector(selector));
      const text = selector => String(node.querySelector(selector)?.textContent || '').replace(/\s+/g, ' ').trim();
      const facts = [...node.querySelectorAll('.facts > div')].map(item => ({
        label: String(item.querySelector('span')?.textContent || '').trim(),
        value: String(item.querySelector('b')?.textContent || '').trim()
      }));
      const rect = node.getBoundingClientRect();
      return {
        rootWidth: rect.width,
        headColumns: style('.selected-head').gridTemplateColumns,
        factColumns: style('.facts').gridTemplateColumns,
        price: text('.selected-price'),
        title: text('.selected-head h2'),
        facts,
        dateText: facts.find(item => item.label === 'Дата')?.value || '',
        searchVisible: !!document.querySelector('#tourSearch') && getComputedStyle(document.querySelector('#tourSearch')).display !== 'none',
        overflow: document.documentElement.scrollWidth > innerWidth + 2
      };
    });
    assert.equal(columnCount(detail.headColumns), expected.selected_head_columns, `selected header columns at ${width}`);
    assert.equal(columnCount(detail.factColumns), expected.fact_columns, `selected fact columns at ${width}`);
    assert.deepEqual(detail.facts.map(item => item.label), contract.required_fact_labels, 'selected facts stay complete and ordered');
    assert.match(detail.price.replace(/\s/g, ''), /148500₽/, 'selected total stays visible');
    assert.equal(detail.title, 'SUNRISE Resort & Spa', 'selected hotel identity stays visible');
    assert.equal(detail.searchVisible, contract.invariants.selected_search_form_visible, 'selected state does not duplicate the search form');
    assert.equal(detail.overflow, contract.invariants.horizontal_overflow, `selected detail has no horizontal overflow at ${width}`);
    assert.ok(detail.rootWidth <= width + 2, 'selected root is bounded by the viewport');
    for (const selector of [':scope > .back-results', '.search3-flight-continue button']) {
      const box = await root.locator(selector).boundingBox();
      assert.ok(box && box.height >= contract.invariants.minimum_touch_target_px, `${selector} keeps a full touch target`);
    }
    await root.screenshot({ path: path.join(output, `selected-current-${width}-detail.png`), animations: 'disabled' });

    await root.locator('.search3-flight-continue button').click();
    await page.waitForSelector('#selectedTour.search3-lead-entry .lead-form input[name="phone"]');
    await page.waitForFunction(() => document.activeElement?.name === 'phone');
    await settle(page);
    const lead = await root.evaluate(node => {
      const form = node.querySelector('.lead-form');
      const rect = form.getBoundingClientRect();
      const phone = form.querySelector('[name="phone"]');
      const submit = form.querySelector('button[type="submit"]');
      return {
        leadColumns: getComputedStyle(form.querySelector('.lead-fields')).gridTemplateColumns,
        formWidth: rect.width,
        phoneHeight: phone.getBoundingClientRect().height,
        submitWidth: submit.getBoundingClientRect().width,
        submitHeight: submit.getBoundingClientRect().height,
        submitText: submit.textContent.trim(),
        phoneRequired: phone.required,
        phoneLabel: phone.closest('label')?.childNodes[0]?.textContent?.trim() || '',
        consentRequired: !!form.querySelector('[name="consent"]')?.required,
        activeName: document.activeElement?.name || '',
        overflow: document.documentElement.scrollWidth > innerWidth + 2
      };
    });
    assert.equal(columnCount(lead.leadColumns), expected.lead_columns, `lead fields columns at ${width}`);
    assert.equal(lead.phoneRequired, true, 'phone remains required');
    assert.equal(lead.consentRequired, true, 'consent remains required');
    assert.equal(lead.phoneLabel, 'Телефон', 'phone keeps a visible associated label');
    assert.equal(lead.submitText, 'Отправить заявку', 'one clear lead action remains');
    assert.equal(lead.activeName, contract.invariants.lead_focus_target, 'lead handoff focuses the phone field');
    assert.ok(lead.phoneHeight >= contract.invariants.minimum_touch_target_px, 'phone keeps a full touch target');
    assert.ok(lead.submitHeight >= contract.invariants.minimum_touch_target_px, 'submit keeps a full touch target');
    if (expected.mobile_submit) assert.ok(lead.submitWidth >= lead.formWidth * 0.8, 'mobile submit is a clear full-width action');
    else assert.ok(lead.submitWidth <= 280, 'non-mobile submit stays bounded instead of becoming a giant CTA');
    assert.equal(lead.overflow, contract.invariants.horizontal_overflow, `lead state has no horizontal overflow at ${width}`);
    assert.equal(await root.locator('.lead-form button[type="submit"]:visible').count(), 1, 'one lead submit is visible');
    assert.equal(await root.locator('.search3-booking-summary,.search3-summary-submit').count(), 0, 'retired review layer stays absent');
    assert.equal(await root.locator('.lead-message[aria-live="polite"]').count(), 1, 'lead state keeps an aria-live result channel');
    await root.screenshot({ path: path.join(output, `selected-current-${width}-lead.png`), animations: 'disabled' });

    assert.deepEqual(await page.evaluate(() => window.__selectedAcceptanceCalls), { tour: 1, flights: 1, other: 0 }, 'fixture performs only the expected local tour/flights calls');
    assert.deepEqual(posts, [], 'acceptance never sends a real lead or any POST');
    assert.deepEqual(browserErrors, [], 'acceptance fixture has no browser errors');
    return { width, detail, lead, realLeads: 0, realSupplierRequests: 0 };
  } catch (error) {
    await page.screenshot({ path: path.join(output, `selected-current-${width}-failure.png`), fullPage: true });
    fs.writeFileSync(path.join(output, `selected-current-${width}-failure.json`), JSON.stringify({ message: String(error), browserErrors }, null, 2) + '\n');
    throw error;
  } finally {
    await page.close();
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const evidence = { source: process.env.GITHUB_SHA || '', contract, widths: {} };
  try {
    for (const width of Object.keys(contract.widths).map(Number)) evidence.widths[width] = await run(browser, width);
  } finally {
    await browser.close();
    fs.writeFileSync(path.join(output, 'selected-lead-current.json'), JSON.stringify(evidence, null, 2) + '\n');
  }
  console.log('SEARCH3_SELECTED_LEAD_CURRENT_OK widths=' + Object.keys(contract.widths).join(',') + ' screenshots=8 real_leads=0 supplier_requests=0 date_display=diagnostic');
})().catch(error => { console.error(error); process.exitCode = 1; });
