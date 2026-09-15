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
  fuelCharge: 0,
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
const returnSegment = { ...segment, number: 'AT124', departure: { ...segment.arrival, date: '2026-10-14', time: '16:00' }, arrival: { ...segment.departure, date: '2026-10-14', time: '20:30' } };
const flights = [
  { isDefault: true, price: { value: 148500 }, forward: [segment], backward: [returnSegment] },
  { price: { value: 148500 }, fuelCharge: { value: 0 }, forward: [segment], backward: [returnSegment] },
  { price: { value: 149900 }, fuelCharge: { value: 1400 }, forward: [segment], backward: [returnSegment] }
];

const settle = page => page.evaluate(async () => {
  await document.fonts.ready;
  for (let i = 0; i < 3; i++) await new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 0)));
  await new Promise(resolve => requestAnimationFrame(resolve));
});
const columnCount = value => String(value || '').trim().split(/\s+/).filter(Boolean).length;

async function checkLeadEntryViewport(page, root, width, height) {
  await page.setViewportSize({ width, height });
  await root.locator('.search3-flight-continue button').click();
  await page.waitForFunction(() => {
    const phone = document.querySelector('#selectedTour .lead-form input[name="phone"]');
    const box = phone?.getBoundingClientRect();
    const label = phone?.closest('label').getBoundingClientRect();
    return document.activeElement === phone && box && box.top >= 0 && box.bottom <= innerHeight && label.top >= 0 && label.bottom <= innerHeight;
  });
  await settle(page);
  const geometry = await root.locator('.lead-form').evaluate(form => {
    const rect = node => {
      const box = node.getBoundingClientRect();
      return { top: box.top, bottom: box.bottom, left: box.left, right: box.right, height: box.height };
    };
    const phone = form.querySelector('input[name="phone"]');
    return { viewport: { width: innerWidth, height: innerHeight }, form: rect(form), phone: rect(phone),
      label: rect(phone.closest('label')), activeName: document.activeElement?.name,
      summary: form.querySelector('.lead-selection-summary').textContent.replace(/\s+/g, ' ').trim() };
  });
  assert.equal(geometry.activeName, 'phone', 'the canonical handoff keeps synchronous phone focus');
  assert.ok(geometry.phone.top >= 0 && geometry.phone.bottom <= height, `${width}x${height}: focused phone is completely on screen`);
  assert.ok(geometry.label.top >= 0 && geometry.label.bottom <= height, `${width}x${height}: phone label and hint are also visible`);
  assert.ok(geometry.phone.left >= 0 && geometry.phone.right <= width, 'phone stays within the viewport');
  assert.match(geometry.summary, /SUNRISE Resort & Spa/, 'scrolling retains the exact tour summary');
  await page.screenshot({ path: path.join(output, `lead-entry-viewport-${width}x${height}.png`), animations: 'disabled' });
  return geometry;
}

async function checkSelectedFacts(root, width) {
  const geometry = await root.locator('.facts').evaluate(grid => {
    const rect = node => {
      const box = node.getBoundingClientRect();
      return { x: box.x, y: box.y, right: box.right, bottom: box.bottom, width: box.width, height: box.height };
    };
    const text = node => {
      const range = document.createRange(); range.selectNodeContents(node);
      const style = getComputedStyle(node);
      return { text: node.textContent.trim(), size: parseFloat(style.fontSize), visible: style.visibility === 'visible' && node.getClientRects().length > 0,
        lines: [...range.getClientRects()].filter(box => box.width && box.height).map(box => ({ x: box.x, y: box.y, right: box.right, bottom: box.bottom })) };
    };
    return { ...rect(grid), cells: [...grid.children].map(cell => ({ ...rect(cell), label: text(cell.querySelector('span')), value: text(cell.querySelector('b')) })) };
  });
  assert.deepEqual(geometry.cells.map(cell => cell.label.text), contract.required_fact_labels, 'all selected fact labels remain visible and ordered');
  for (const cell of geometry.cells) {
    assert.ok(cell.x >= geometry.x - 1 && cell.right <= geometry.right + 1, width + ': fact cell stays inside the grid');
    for (const [kind, minimum] of [['label', 12], ['value', 15]]) {
      const value = cell[kind];
      assert.ok(value.visible && value.text && value.lines.length, width + ': ' + cell.label.text + ' keeps visible ' + kind);
      assert.ok(value.size >= minimum, width + ': compact spacing preserves readable ' + kind + ' typography');
      assert.ok(value.lines.every(line => line.x >= cell.x - 1 && line.right <= cell.right + 1 && line.y >= cell.y - 1 && line.bottom <= cell.bottom + 1),
        width + ': complete ' + cell.label.text + ' ' + kind + ' fits its cell without clipping');
    }
  }
  assert.equal(geometry.cells.find(cell => cell.label.text === 'Дата').value.lines.length, 1, 'the exact departure date stays whole');
  return geometry;
}

async function checkFlightPriceLines(root, width) {
  const prices = await root.locator('.flight-choice > b').evaluateAll(nodes => nodes.map(node => {
    const choice = node.closest('.flight-choice').getBoundingClientRect();
    const glyphs = [];
    const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT);
    for (let text = walker.nextNode(); text; text = walker.nextNode()) {
      for (let offset = 0; offset < text.length; offset++) {
        if (!/[\d,.₽]/.test(text.data[offset])) continue;
        const range = document.createRange();
        range.setStart(text, offset); range.setEnd(text, offset + 1);
        const rect = range.getBoundingClientRect();
        glyphs.push({ text: text.data[offset], x: rect.x - choice.x, y: rect.y - choice.y, right: rect.right - choice.x });
      }
    }
    return { text: node.textContent.replace(/\s+/g, ' ').trim(), glyphs, choiceWidth: choice.width, priceHeight: node.getBoundingClientRect().height };
  }));
  assert.ok(prices.length, width + ': flight prices are visible');
  for (const price of prices) {
    assert.match(price.text, /^Стоимость тура:/, 'flight total keeps its truthful whole-tour caption');
    assert.equal(price.glyphs.filter(glyph => glyph.text === '₽').length, 1, 'each flight total keeps exactly one currency marker');
    const tops = price.glyphs.map(glyph => glyph.y);
    assert.ok(Math.max(...tops) - Math.min(...tops) <= 1, width + ': complete amount and currency share one line: ' + JSON.stringify(price));
    assert.ok(price.glyphs.every(glyph => glyph.x >= -1 && glyph.right <= price.choiceWidth + 1), width + ': the complete amount fits within the flight choice');
  }
  return prices;
}

async function checkFlightRoutes(root, width) {
  const routes = await root.locator('.flight-variant.is-selected .flight-route').evaluateAll(nodes => nodes.map(node => {
    const rect = value => {
      const box = value.getBoundingClientRect();
      return { left: box.left, right: box.right, top: box.top, bottom: box.bottom, width: box.width, height: box.height };
    };
    const endpoints = [node.firstElementChild, node.lastElementChild].map(endpoint => ({ ...rect(endpoint), text: endpoint.textContent.replace(/\s+/g, ' ').trim(), clipped: endpoint.scrollWidth > endpoint.clientWidth + 1 || endpoint.scrollHeight > endpoint.clientHeight + 1 }));
    return { ...rect(node), columns: getComputedStyle(node).gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length, endpoints };
  }));
  assert.equal(routes.length, 2, width + ': selected round trip keeps outbound and return routes');
  for (const route of routes) {
    assert.equal(route.columns, 3, width + ': route keeps two side-by-side endpoints and one directional marker');
    assert.ok(route.endpoints.every(endpoint => endpoint.text && !endpoint.clipped), width + ': complete airport/date/time endpoints remain visible');
    assert.ok(route.endpoints[0].right <= route.endpoints[1].left + 1, width + ': route endpoints do not overlap');
    assert.ok(route.endpoints.every(endpoint => endpoint.left >= route.left - 1 && endpoint.right <= route.right + 1), width + ': route endpoints stay inside the flight card');
  }
  return routes;
}

async function checkLongFlightPrice(page, width) {
  const item = { ...tour, id: 'long-flight-price-' + width, price: 1234567.89,
    roomType: 'FAMILY SUITE WITH TWO BEDROOMS AND SIDE SEA VIEW',
    operator: { name: 'Туроператор с длинным составным названием для проверки переноса' } };
  const choices = [{ ...flights[0], price: { value: item.price } }];
  await page.evaluate(({ item, choices }) => {
    const calls = window.__longFlightPriceCalls = [];
    window.V2Runtime.api = async (action, params) => {
      if (params?.tourId !== item.id) throw new Error('long-price fixture must retain its offer identity');
      calls.push(action);
      if (action === 'tour') return item;
      if (action === 'flights') return choices;
      throw new Error('unexpected long-price API action');
    };
    window.V2TourController.selectTour(item.id);
  }, { item, choices });
  const root = page.locator('#selectedTour');
  await root.locator('.search3-flight-continue button').waitFor();
  await page.waitForFunction(id => window.V2TourController.currentTour?.id === id && document.querySelectorAll('#selectedTour .flight-choice').length === 1, item.id);
  await settle(page);
  const prices = await checkFlightPriceLines(root, width);
  const facts = await checkSelectedFacts(root, width);
  assert.equal(facts.cells.find(cell => cell.label.text === 'Номер').value.text, 'Семейный люкс · 2 спальни · боковой вид на море', 'reviewed long room conditions stay complete in Russian');
  assert.equal(facts.cells.find(cell => cell.label.text === 'Оператор').value.text, item.operator.name, 'long operator identity stays complete');
  if (width <= 375) assert.ok(facts.height <= (width === 320 ? 580 : 520), width + ': long facts use compact spacing while all text remains visible');
  assert.equal(prices[0].text.replace(/\s/g, ''), 'Стоимостьтура:1234567,89₽', 'single-flight decimal amount stays exact');
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'long flight price does not create page overflow');
  assert.deepEqual(await page.evaluate(() => window.__longFlightPriceCalls), ['tour', 'flights'], 'long-price case makes only the two local fixture calls');
  await root.locator('.flight-variant').screenshot({ path: path.join(output, 'flight-price-long-' + width + '.png'), animations: 'disabled' });
  await root.locator('.facts').screenshot({ path: path.join(output, 'selected-facts-long-' + width + '.png'), animations: 'disabled' });
  return { prices, facts, exactOffer: item.id, realSupplierRequests: 0 };
}

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
        flightMoments: [...node.querySelectorAll('.flight-route span')].map(item => String(item.textContent || '').trim()),
        searchVisible: !!document.querySelector('#tourSearch') && getComputedStyle(document.querySelector('#tourSearch')).display !== 'none',
        overflow: document.documentElement.scrollWidth > innerWidth + 2
      };
    });
    assert.equal(columnCount(detail.headColumns), expected.selected_head_columns, `selected header columns at ${width}`);
    assert.equal(columnCount(detail.factColumns), expected.fact_columns, `selected fact columns at ${width}`);
    assert.deepEqual(detail.facts.map(item => item.label), contract.required_fact_labels, 'selected facts stay complete and ordered');
    assert.deepEqual(detail.facts.map(item => item.value), ['Москва', '05.10.2026', '9', '2 взр. + 1 дет.', 'Всё включено',
      'Стандарт · вид на территорию', 'DBL + CHD', 'ANEX Tour', 'Чартер', 'без доплаты'], 'spacing keeps exact selected conditions with reviewed Russian display labels');
    const factGeometry = await checkSelectedFacts(root, width);
    if (width <= 375) assert.ok(factGeometry.height <= 360, width + ': mobile selected facts improve on the 411px measured baseline without shrinking or hiding text');
    await root.locator('.facts').screenshot({ path: path.join(output, 'selected-facts-' + width + '.png'), animations: 'disabled' });
    assert.equal(detail.facts.find(item => item.label === 'Топливный сбор')?.value, 'без доплаты',
      'selected summary distinguishes an explicit zero fuel charge from an unknown fee');
    assert.match(detail.price.replace(/\s/g, ''), /148500₽/, 'selected total stays visible');
    assert.equal(detail.title, 'SUNRISE Resort & Spa', 'selected hotel identity stays visible');
    assert.equal(detail.dateText, '05.10.2026', 'selected date uses the canonical renderer display');
    assert.notEqual(detail.dateText, tour.date, 'selected date never exposes the raw supplier ISO value');
    assert.ok(detail.flightMoments.some(value => value.includes('05.10.2026')), 'outbound flight uses the canonical date display');
    assert.ok(detail.flightMoments.some(value => value.includes('14.10.2026')), 'return flight uses the canonical date display');
    assert.equal(detail.flightMoments.some(value => /\b2026-10-(?:05|14)\b/.test(value)), false,
      'selected flight routes never expose raw supplier ISO dates');
    const flightToggle = root.locator('.search3-flight-toggle');
    assert.equal(await flightToggle.count(), 1, 'multiple flights use the canonical local disclosure');
    assert.equal(await flightToggle.getAttribute('aria-expanded'), 'false', 'alternative flights start collapsed');
    assert.equal(await flightToggle.innerText(), 'Показать другие рейсы (2)', 'collapsed disclosure states the exact alternative count');
    assert.equal(await root.locator('.flight-variant:visible').count(), 1, 'only the selected exact flight is initially visible');
    await flightToggle.click();
    await settle(page);
    assert.equal(await flightToggle.getAttribute('aria-expanded'), 'true', 'alternative flights expand from the canonical control');
    assert.equal(await root.locator('.flight-variant:visible').count(), 3, 'expansion exposes every original flight variant');
    assert.equal(await page.evaluate(() => document.activeElement?.name), 'v2flight', 'expansion focuses the selected flight radio');
    const flightPrices = await checkFlightPriceLines(root, width);
    const fuelLabels = await root.locator('.flight-fuel').allTextContents();
    assert.deepEqual(fuelLabels.map(value => value.replace(/\s+/g, ' ').trim()), contract.invariants.flight_fuel_display,
      'flight fee display distinguishes unknown, explicit zero and known values');
    assert.equal(fuelLabels.some(value => /:\s*₽\s*$/.test(value)), false, 'flight fee never renders a bare currency marker');
    await root.locator('input[name="v2flight"][value="2"]').check();
    await settle(page);
    assert.match((await root.locator('.selected-price').innerText()).replace(/\s/g, ''), /149900₽/, 'changing the flight retains its exact total');
    await checkFlightPriceLines(root, width);
    await root.locator('input[name="v2flight"][value="0"]').check();
    await settle(page);
    assert.match((await root.locator('.selected-price').innerText()).replace(/\s/g, ''), /148500₽/, 'returning to the original flight restores its exact total');
    await flightToggle.click();
    await settle(page);
    assert.equal(await flightToggle.getAttribute('aria-expanded'), 'false', 'disclosure returns to its compact state');
    assert.equal(await root.locator('.flight-variant:visible').count(), 1, 'collapse retains only the selected exact flight');
    const flightRoutes = await checkFlightRoutes(root, width);
    assert.equal(detail.searchVisible, contract.invariants.selected_search_form_visible, 'selected state does not duplicate the search form');
    assert.equal(detail.overflow, contract.invariants.horizontal_overflow, `selected detail has no horizontal overflow at ${width}`);
    assert.ok(detail.rootWidth <= width + 2, 'selected root is bounded by the viewport');
    for (const selector of [':scope > .back-results', '.search3-flight-continue button']) {
      const box = await root.locator(selector).boundingBox();
      assert.ok(box && box.height >= contract.invariants.minimum_touch_target_px, `${selector} keeps a full touch target`);
    }
    await root.screenshot({ path: path.join(output, `selected-current-${width}-detail.png`), animations: 'disabled' });

    const leadEntryViewports = [await checkLeadEntryViewport(page, root, width, width === 320 ? 568 : width === 375 ? 667 : 600)];
    if (width <= 375) leadEntryViewports.push(await checkLeadEntryViewport(page, root, width, 360));
    await page.setViewportSize({ width, height: 1000 });
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
        selectedHotel: node.querySelector('.selected-head h2')?.textContent?.trim() || '',
        selectedDate: [...node.querySelectorAll('.facts>div')].find(item => item.querySelector('span')?.textContent?.trim() === 'Дата')?.querySelector('b')?.textContent?.trim() || '',
        summary: [...form.querySelectorAll('.lead-selection-summary span')].map(item => ({
          label: item.querySelector('small')?.textContent?.trim() || '',
          value: item.querySelector('b')?.textContent?.trim() || ''
        })),
        overflow: document.documentElement.scrollWidth > innerWidth + 2
      };
    });
    assert.equal(columnCount(lead.leadColumns), expected.lead_columns, `lead fields columns at ${width}`);
    assert.deepEqual(lead.summary.map(item => item.label), ['Тур', 'Ваш выбор', 'Рейс'], 'lead summary keeps one compact tour/price/flight hierarchy');
    assert.equal(lead.summary[0].value, lead.selectedHotel + ' · ' + lead.selectedDate, 'lead summary repeats the exact selected hotel and departure date beside contacts');
    assert.ok(lead.selectedHotel && lead.selectedDate, 'selected hotel and exact date remain available to the lead summary');
    assert.equal(lead.phoneRequired, true, 'phone remains required');
    assert.equal(lead.consentRequired, true, 'consent remains required');
    assert.equal(lead.phoneLabel, 'Телефон (обязательно)', 'phone keeps a visible associated required label');
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
    const recovery = [375, 1440].includes(width) ? await checkLeadRecovery(page, width) : null;
    const loadingRecovery = [375, 1440].includes(width) ? await checkSelectedLoadRecovery(page, width) : null;
    const longFlightPrice = [320, 375, 1440].includes(width) ? await checkLongFlightPrice(page, width) : null;
    assert.deepEqual(posts, [], 'acceptance never sends a real lead or any POST');
    assert.deepEqual(browserErrors, [], 'acceptance fixture has no browser errors');
    return { width, detail, factGeometry, flightRoutes, lead, leadEntryViewports, recovery, loadingRecovery, flightPrices, longFlightPrice, realLeads: 0, realSupplierRequests: 0 };
  } catch (error) {
    await page.screenshot({ path: path.join(output, `selected-current-${width}-failure.png`), fullPage: true });
    fs.writeFileSync(path.join(output, `selected-current-${width}-failure.json`), JSON.stringify({ message: String(error), browserErrors }, null, 2) + '\n');
    throw error;
  } finally {
    await page.close();
  }
}

async function checkLeadRecovery(page, width) {
  const searchId = 812301;
  const hotel = { ...tour.hotel, id: 'lead-recovery-hotel', picturelink: picture };
  const first = { ...tour, hotel, source: 'tourvisor' };
  const second = { ...first, id: 'lead-recovery-second', roomType: 'FAMILY ROOM' };
  const draft = { name: 'Тестовый черновик', phone: '+7 000 000-00-00', comment: 'Локальная проверка без отправки.' };
  const root = page.locator('#selectedTour');
  await root.locator('.search3-lead-return').click();
  await page.evaluate(({ searchId, hotel, first, second, flights }) => {
    window.V2Runtime.setSearchId(searchId);
    window.__leadRecovery = { calls: [], payloads: [], pending: [], events: [] };
    const state = window.__leadRecovery;
    window.V2Runtime.api = async (action, params) => {
      state.calls.push([action, params?.tourId]);
      const selected = [first, second].find(item => item.id === params?.tourId);
      if (!selected) throw new Error('unexpected recovery offer');
      if (action === 'tour') return selected;
      if (action === 'flights') return flights;
      throw new Error('unexpected recovery API action ' + action);
    };
    const originalFetch = window.fetch.bind(window);
    const leadUrl = new URL(window.V2_CONFIG?.leadApi || '/poisk-turov-test/v2/lead-adapter.php', location.href).href;
    window.fetch = (input, options) => {
      const url = new URL(typeof input === 'string' ? input : input.url, location.href).href;
      if (url !== leadUrl || options?.method !== 'POST') return originalFetch(input, options);
      state.payloads.push(JSON.parse(options.body));
      return new Promise((resolve, reject) => state.pending.push({ resolve, reject }));
    };
    for (const name of ['lead-started', 'lead-error', 'lead-success']) {
      window.addEventListener('v2:' + name, event => state.events.push([name, event.detail?.tourId]));
    }
    window.V2Results.render([{ ...hotel, price: first.price, tours: [first, second] }]);
  }, { searchId, hotel, first, second, flights });

  await page.locator('#results .tour-more-toggle').click();
  const openOffer = async id => {
    await page.locator('#results .direct-tour[data-tid="' + id + '"]').click();
    await page.waitForFunction(id => window.V2TourController.currentTour?.id === id, id);
    await root.locator('.flight-variant').nth(2).waitFor({ state: 'attached' });
    await root.locator('.search3-flight-continue button').click();
    await page.waitForFunction(() => document.activeElement?.name === 'phone');
  };
  const readDraft = () => root.locator('.lead-form').evaluate(form => Object.fromEntries(
    ['name', 'phone', 'comment'].map(name => [name, form.elements[name].value])));
  const captureCount = () => page.evaluate(() => window.__leadRecovery.payloads.length);
  const locks = () => page.evaluate(() => {
    const root = document.getElementById('selectedTour');
    return {
      pending: window.V2LeadUiRaceGuardV1.leadPending,
      returns: [...root.querySelectorAll('.back-results,.other-hotel-offers')].map(node => ({ disabled: node.disabled, aria: node.getAttribute('aria-disabled') })),
      search: document.getElementById('tourSearch').inert,
      flights: root.querySelector('.flight-variants').inert,
      offers: [...document.querySelectorAll('#results .direct-tour')].map(node => node.disabled)
    };
  });
  const assertLocks = async locked => {
    const value = await locks();
    assert.ok(value.returns.length >= 3, 'top return, lead change and hotel alternatives are actual canonical controls');
    assert.equal(value.pending, locked);
    assert.equal(value.search, locked);
    assert.equal(value.flights, locked);
    assert.ok(value.returns.every(item => item.disabled === locked && item.aria === (locked ? 'true' : null)), 'every return action follows pending state');
    assert.deepEqual(value.offers, [locked, locked], 'exact offer buttons follow pending state');
    return value;
  };
  await openOffer(first.id);
  const form = root.locator('.lead-form');
  const submit = form.locator('button[type="submit"]');
  await form.locator('[name="phone"]').fill('123');
  await submit.click();
  assert.equal(await captureCount(), 0, 'invalid phone cannot enter lead transport');
  assert.equal(await form.locator('[name="phone"]').evaluate(node => node.validity.valid), false);
  for (const [name, value] of Object.entries(draft)) await form.locator('[name="' + name + '"]').fill(value);
  await submit.click();
  assert.equal(await captureCount(), 0, 'valid contact without consent cannot enter lead transport');
  assert.equal(await form.locator('[name="consent"]').evaluate(node => node.validity.valueMissing), true);
  await form.locator('[name="consent"]').check();
  await root.locator('.search3-flight-toggle').click();
  await root.locator('input[name="v2flight"][value="1"]').check();
  await submit.click();
  await page.waitForFunction(() => window.__leadRecovery.pending.length === 1);
  const pending = await assertLocks(true);
  assert.equal(await submit.isDisabled(), true);
  const activeIdentity = await page.evaluate(() => window.V2TourController.currentTour.id);
  for (const action of await root.locator('.back-results,.other-hotel-offers').all()) await action.evaluate(node => node.click());
  assert.equal(await root.isVisible(), true, 'disabled returns cannot leave an in-flight lead');
  assert.equal(await page.evaluate(() => window.V2TourController.currentTour.id), activeIdentity);
  await page.evaluate(({ searchId, other }) => {
    window.dispatchEvent(new CustomEvent('v2:lead-error', { detail: { searchId, tourId: other } }));
    window.dispatchEvent(new CustomEvent('v2:lead-success', { detail: { searchId, tourId: other, leadId: 999 } }));
  }, { searchId, other: second.id });
  await assertLocks(true);
  assert.equal(await root.locator('.lead-success-panel').count(), 0, 'stale success cannot replace the current draft');
  assert.equal(await form.locator('.lead-message').getAttribute('role'), 'status', 'stale error cannot overwrite pending feedback');
  await root.screenshot({ path: path.join(output, `selected-lead-pending-${width}.png`), animations: 'disabled' });

  await page.evaluate(() => window.__leadRecovery.pending.shift().reject(new Error('fixture offline')));
  await page.waitForFunction(() => !window.V2LeadUiRaceGuardV1.leadPending);
  await assertLocks(false);
  assert.deepEqual(await readDraft(), draft, 'network error preserves all contact fields');
  assert.equal(await form.locator('[name="consent"]').isChecked(), true, 'same-tour error keeps the current consent');
  assert.equal(await form.locator('.lead-message').getAttribute('role'), 'alert');
  assert.equal(await submit.innerText(), 'Повторить отправку');
  await submit.click();
  await page.waitForFunction(() => window.__leadRecovery.pending.length === 1);
  await page.evaluate(() => window.__leadRecovery.pending.shift().resolve({ ok: false, json: async () => ({ ok: false, error: 'fixture server error' }) }));
  await page.waitForFunction(() => !window.V2LeadUiRaceGuardV1.leadPending);
  assert.deepEqual(await readDraft(), draft, 'server error also preserves the draft');
  await root.locator('.search3-lead-return').click();
  await page.waitForFunction(id => document.activeElement?.dataset.tid === id, first.id);
  await openOffer(second.id);
  assert.deepEqual(await readDraft(), draft, 'changing offers retains only allowed contact fields');
  assert.equal(await form.locator('[name="consent"]').isChecked(), false, 'consent never transfers to another offer');
  assert.equal(await page.evaluate(() => window.V2TourController.currentTour.id), second.id);
  await submit.click();
  assert.equal(await captureCount(), 2, 'new offer still requires fresh consent');
  await form.locator('[name="consent"]').check();
  await submit.click();
  await page.waitForFunction(() => window.__leadRecovery.pending.length === 1);
  await page.evaluate(() => window.__leadRecovery.pending.shift().resolve({ ok: true, json: async () => ({ ok: true, writes: 1, leadId: 9990001 }) }));
  await root.locator('.lead-success-panel').waitFor();
  await assertLocks(true);
  assert.equal(await form.getAttribute('data-sent'), '1');
  await root.locator('.lead-success-back').click();
  await page.waitForFunction(id => document.activeElement?.dataset.tid === id, second.id);
  assert.equal(await page.evaluate(() => window.V2LeadUiRaceGuardV1.leadPending), false, 'explicit success return releases search and result choices');
  assert.equal(await page.locator('#tourSearch').evaluate(node => node.inert), false);
  assert.deepEqual(await page.locator('#results .direct-tour').evaluateAll(nodes => nodes.map(node => node.disabled)), [false, false]);
  await openOffer(first.id);
  assert.deepEqual(await readDraft(), { name: '', phone: '', comment: '' }, 'confirmed lead clears the reusable draft');
  assert.equal(await form.locator('[name="consent"]').isChecked(), false);
  for (const [name, value] of Object.entries(draft)) await form.locator('[name="' + name + '"]').fill(value);
  await root.locator('.search3-lead-return').click();
  await page.evaluate(({ searchId, hotel, first, second }) => {
    window.dispatchEvent(new CustomEvent('v2:search-reset'));
    window.V2Runtime.setSearchId(searchId + 1);
    window.V2Results.render([{ ...hotel, price: first.price, tours: [first, second] }]);
  }, { searchId, hotel, first, second });
  await page.locator('#results .tour-more-toggle').click();
  await openOffer(second.id);
  assert.deepEqual(await readDraft(), { name: '', phone: '', comment: '' }, 'new search clears the old contact draft');
  const evidence = await page.evaluate(() => ({
    calls: window.__leadRecovery.calls,
    identities: window.__leadRecovery.payloads.map(item => ({ tourId: item.tourId, searchId: item.searchId, consent: item.consent })),
    events: window.__leadRecovery.events,
    pendingRequests: window.__leadRecovery.pending.length
  }));
  assert.deepEqual(evidence.identities, [first.id, first.id, second.id].map(tourId => ({ tourId, searchId, consent: true })), 'the unchanged payload always carries the exact selected offer and search');
  assert.equal(evidence.calls.some(([action]) => !['tour', 'flights'].includes(action)), false);
  assert.equal(evidence.pendingRequests, 0);
  console.log('SEARCH3_LEAD_RECOVERY_OK ' + JSON.stringify({ width, validation: true, pendingReturns: pending.returns.length, staleBlocked: true, errors: ['network', 'server'], draftRetained: true, consentReset: true, successReturn: true, resetClearsDraft: true, localSubmissions: 3, realLeads: 0, realSupplierRequests: 0 }));
  return { ...evidence, validation: true, pendingReturns: pending.returns.length, staleBlocked: true, draftRetained: true, consentReset: true, successReturn: true, resetClearsDraft: true, localSubmissions: 3, realLeads: 0, realSupplierRequests: 0 };
}

async function checkSelectedLoadRecovery(page, width) {
  const root = page.locator('#selectedTour');
  await root.locator('.search3-lead-return').click();
  await page.evaluate(({ tour, flights }) => {
    const state = window.__selectedLoadRecovery = { tours: 0, flights: 0, other: 0 };
    window.V2Runtime.api = (action, params) => {
      if (params?.tourId !== tour.id) throw new Error('recovery must retain exact offer identity');
      if (action === 'tour') {
        state.tours++;
        if (state.tours === 1) return new Promise((resolve, reject) => { state.rejectTour = reject; });
        return Promise.resolve(tour);
      }
      if (action === 'flights') {
        state.flights++;
        if (state.flights === 1) return Promise.reject(new Error('fixture flight failure'));
        return Promise.resolve(flights);
      }
      state.other++;
      throw new Error('unexpected recovery action ' + action);
    };
  }, { tour, flights });
  const source = page.locator('#results .direct-tour[data-tid="' + tour.id + '"]');
  await source.click();
  await page.waitForFunction(() => typeof window.__selectedLoadRecovery.rejectTour === 'function');
  assert.equal(await root.locator('[role="status"]').evaluate(node => node.closest('[aria-busy="true"]') === null), true, 'loading status is outside a busy region that could suppress its announcement');
  assert.equal(await root.locator('.selected-loading[role="status"]').count(), 1, 'loading has one status region');
  assert.equal(await source.isDisabled(), true, 'pending source action cannot repeat the request');
  await page.evaluate(() => window.__selectedLoadRecovery.rejectTour(new Error('fixture tour failure')));
  await root.locator('.selected-loading[role="alert"]').waitFor();
  assert.equal(await root.locator('[role="alert"]').evaluate(node => node.closest('[aria-busy="true"]') === null), true, 'error alert is not suppressed by a busy ancestor');
  assert.equal(await source.isDisabled(), false, 'failed selection restores the exact source control');
  assert.equal(await root.locator('[role="alert"]').count(), 1, 'tour error has one canonical alert owner');
  assert.match(await root.locator('[role="alert"]').innerText(), /Не удалось загрузить выбранный тур/);
  const retry = root.locator('.retry-tour');
  assert.equal(await retry.getAttribute('data-tid'), tour.id, 'retry retains the failed offer identity');
  await root.screenshot({ path: path.join(output, `selected-load-error-${width}.png`), animations: 'disabled' });
  await retry.focus();
  await retry.press('Enter');
  await root.locator('.flight-error[role="alert"]').waitFor();
  assert.equal(await root.locator('.selected-loading[role="status"]').count(), 0, 'loaded tour retires the pending status even while flight recovery remains');
  assert.equal(await page.evaluate(() => window.V2TourController.currentTour.id), tour.id, 'flight error never discards the selected tour');
  assert.equal(await root.locator('.retry-tour').count(), 0, 'successful retry retires the tour error control');
  await root.locator('.load-flights').focus();
  await root.locator('.load-flights').press('Enter');
  await root.locator('.flight-variant').nth(2).waitFor({ state: 'attached' });
  assert.equal(await root.locator('[role="alert"]').count(), 0, 'successful flight retry clears stale alerts');
  const evidence = await page.evaluate(() => ({ tours: window.__selectedLoadRecovery.tours, flights: window.__selectedLoadRecovery.flights, other: window.__selectedLoadRecovery.other }));
  assert.deepEqual(evidence, { tours: 2, flights: 2, other: 0 }, 'each explicit retry makes one local request of the correct kind');
  console.log('SEARCH3_SELECTED_LOAD_RECOVERY_OK ' + JSON.stringify({ width, pendingControl: true, loadingStatus: true, tourAlert: true, keyboardRetry: true, exactIdentity: true, flightRetry: true, ...evidence, realLeads: 0, realSupplierRequests: 0 }));
  return { pendingControl: true, loadingStatus: true, tourAlert: true, keyboardRetry: true, exactIdentity: true, flightRetry: true, ...evidence, realLeads: 0, realSupplierRequests: 0 };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const widths = Object.keys(contract.widths).map(Number);
  const evidence = { source: process.env.GITHUB_SHA || '', contract, widths: {} };
  try {
    for (const width of widths) evidence.widths[width] = await run(browser, width);
  } finally {
    await browser.close();
    fs.writeFileSync(path.join(output, 'selected-lead-current.json'), JSON.stringify(evidence, null, 2) + '\n');
  }
  console.log('SEARCH3_SELECTED_LEAD_CURRENT_OK widths=' + widths.join(',') + ' screenshots=' + fs.readdirSync(output).filter(name => name.endsWith('.png')).length + ' reflow_200pct_equivalent=1440_to_720 real_leads=0 supplier_requests=0 date_display=canonical recovery_widths=375,1440 load_recovery_widths=375,1440');
})().catch(error => { console.error(error); process.exitCode = 1; });
