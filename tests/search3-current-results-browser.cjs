/* Current reset UI: actual renderer, native header, and raw/served JS parity.
 * Retired custom disclosures, drawers and pixel dimensions are not fabricated. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.SEARCH3_VISUAL_BASE, output = process.env.SEARCH3_RESULTS_OUTPUT;
assert.ok(base && new URL(base).hostname === '127.0.0.1' && output);
fs.mkdirSync(output, { recursive: true });
const names = JSON.parse(execFileSync('php', ['-r', 'require "v2/bundle-manifest-v1.php"; echo json_encode(v2_bundle_files("js", "search3"));'], { cwd: root, encoding: 'utf8' }));
const raw = names.map(name => fs.readFileSync(path.join(root, 'v2', name), 'utf8')).join('\n;\n');
const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="300"><path fill="#9ac7df" d="M0 0h600v300H0z"/></svg>');
const tour = { id: 'current-tour', price: 148500.6, date: '2026-09-12', nights: 9, meal: { name: 'AI', fullName: 'Всё включено' }, roomType: 'STANDARD LAND VIEW', placement: 'DBL', operator: { name: 'TEST OPERATOR' } };
const hotels = [
  { id: 'expensive', name: 'Проверочный отель с длинным названием', country: { name: 'Турция' }, region: { name: 'Анталья' }, price: tour.price, rating: 5, category: 5, seaDistance: 100, picturelink: picture, tours: [{ ...tour, id: 'other-tour', price: 159000, operator: { name: 'OTHER OPERATOR' } }, tour, { ...tour, id: 'third-tour', price: 155000, operator: { name: 'OTHER OPERATOR' } }] },
  { id: 'cheap', name: 'Второй отель', price: 90000, rating: 4, category: 4, seaDistance: 800, picturelink: picture, tours: [{ ...tour, id: 'cheap-tour', price: 90000, operator: { name: 'OTHER OPERATOR' } }] }
];
const calendarHotels = [
  { id: 'calendar-a', tours: [
    { ...tour, id: 'calendar-a1', date: '2026-09-10', price: 105000 },
    { ...tour, id: 'calendar-a2', date: '2026-09-10', price: 99000 },
    { ...tour, id: 'calendar-zero', date: '2026-09-13', price: 0 }
  ] },
  { id: 'calendar-b', tours: [{ ...tour, id: 'calendar-b1', date: '2026-09-12', price: 148500 }] }
];
async function snapshot(page) {
  await page.evaluate(async () => {
    await document.fonts.ready;
    for (let i = 0; i < 3; i++) await new Promise(r => requestAnimationFrame(() => setTimeout(r, 0)));
    scrollTo({ top: 0, left: 0, behavior: 'instant' });
    // Focus/filter actions can leave a pending scroll-anchor adjustment. Measure
    // both representations at a settled document origin, not during that scroll.
    for (let i = 0; i < 2; i++) await new Promise(r => requestAnimationFrame(r));
    scrollTo({ top: 0, left: 0, behavior: 'instant' });
  });
  await page.waitForFunction(() => scrollX === 0 && scrollY === 0);
  return page.evaluate(() => {
    const result = document.getElementById('results');
    const nodes = [...result.querySelectorAll('*')].map(node => {
      const r = node.getBoundingClientRect(), s = getComputedStyle(node);
      return { tag: node.tagName, classes: node.className, text: node.children.length ? '' : node.textContent,
        rect: [r.x, r.y, r.width, r.height].map(n => Math.round(n * 100) / 100), display: s.display, visibility: s.visibility };
    });
    const overflow = document.documentElement.scrollWidth > innerWidth + 2;
    const offenders = overflow ? [...document.querySelectorAll('body *')].filter(node => {
      const r = node.getBoundingClientRect(); return r.width > 0 && r.right > innerWidth + 2;
    }).slice(0,12).map(node => ({tag:node.tagName,id:node.id,classes:node.className,rect:node.getBoundingClientRect().toJSON()})) : [];
    return { html: result.innerHTML, nodes, overflow, offenders };
  });
}
async function checkMealFacet(page, width, previous) {
  const sample = (id, price, meal, date) => ({ ...tour, id, price, meal, date });
  const items = [
    { id: 'meal-a', name: 'Отель А', price: 90000, rating: 5, category: 5, tours: [sample('a-ro', 90000, { name: 'RO', fullName: 'Без питания' }, '2026-09-10'), sample('a-bb', 140000, { fullName: 'Только завтрак' }, '2026-09-15'), sample('a-hb', 145000, { fullName: 'Полупансион' }, '2026-09-16'), sample('a-fb', 150000, { fullName: 'Full Board' }, '2026-09-17'), sample('a-sc', 155000, { fullName: 'Self Catering' }, '2026-09-18'), sample('a-request', 160000, { fullName: 'По запросу' }, '2026-09-19'), sample('a-ai-extra', 125000, { fullName: 'Всё включено' }, '2026-09-14'), sample('a-ai', 120000, { name: 'AI', fullName: 'Всё включено' }, '2026-09-12'), sample('a-uai', 135000, { name: 'UAI', fullName: 'Ультра всё включено' }, '2026-09-14'), { ...sample('a-andromeda-ai', 130000, { name: 'AI' }, '2026-09-14'), provider: 'andromeda', selectionEnabled: false }] },
    { id: 'meal-b', name: 'Отель Б', price: 100000, rating: 4, category: 4, tours: [sample('b-ai', 100000, { fullName: 'Всё включено' }, '2026-09-11'), sample('b-bb', 142000, { fullName: 'Breakfast' }, '2026-09-15'), sample('b-hb', 147000, { fullName: 'Half Board' }, '2026-09-16'), sample('b-request', 162000, { fullName: 'On Request' }, '2026-09-19')] },
    { id: 'meal-c', name: 'Отель В', price: 80000, rating: 3, category: 3, tours: [sample('c-ro', 80000, { fullName: 'Room only' }, '2026-09-13'), sample('c-fb', 152000, { fullName: 'Полный пансион' }, '2026-09-17'), sample('c-sc', 157000, { fullName: 'Самообслуживание' }, '2026-09-18')] }
  ];
  const supplierRequests = [];
  const record = request => { if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)) supplierRequests.push(request.url()); };
  page.on('request', record);
  try {
    await page.evaluate(items => {
      const freeze = value => { if (value && typeof value === 'object') { Object.values(value).forEach(freeze); Object.freeze(value); } return value; };
      window.__mealOriginal = freeze(items);
      window.__mealEvents = [];
      window.addEventListener('v2:results-rendered', event => window.__mealEvents.push(event.detail.items));
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 100, items } }));
    }, items);
    await page.locator('#sortResults').selectOption('price');
    const field = page.locator('.search3-meal-filter'), select = field.locator('select');
    const mealPreset = field.locator('.search3-filter-presets button', { hasText: 'Всё включено' });
    const name = page.locator('.search3-hotel-filter input'), category = page.locator('.search3-category-filter select');
    const visible = () => page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId));
    if (width < 1025) await page.locator('.search3-mobile-filter-panel summary').click();
    assert.equal(await field.isVisible(), true, 'complete loaded meals expose the local facet');
    assert.deepEqual(await select.locator('option').evaluateAll(nodes => nodes.map(node => [node.value, node.textContent])), [
      ['', 'Любое питание'], ['meal:room-only', 'Без питания'], ['meal:all-inclusive', 'Всё включено'],
      ['meal:breakfast', 'Завтрак'], ['meal:on-request', 'По запросу'], ['meal:full-board', 'Полный пансион'],
      ['meal:half-board', 'Полупансион'], ['meal:self-catering', 'Самообслуживание']
    ], 'Russian and English supplier synonyms collapse into one customer-facing choice per meal family');
    assert.equal(await mealPreset.isVisible(), true, 'a truthful existing all-inclusive option exposes one quick choice');
    assert.ok((await mealPreset.boundingBox()).height >= 44, 'meal quick choice keeps a full touch target');
    assert.deepEqual(await visible(), ['meal-c', 'meal-a', 'meal-b']);
    const calendar = page.locator('#currentPriceCalendar');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-13', 'calendar starts from the lowest offer in the terminal result set');
    const eventCount = await page.evaluate(() => window.__mealEvents.length);
    await mealPreset.click();
    assert.equal(await select.inputValue(), 'meal:all-inclusive', 'quick choice drives the canonical cross-provider meal value');
    assert.equal(await mealPreset.getAttribute('aria-pressed'), 'true', 'quick choice exposes its selected state');
    assert.equal(await mealPreset.evaluate(node => node === document.activeElement), true, 'quick choice keeps keyboard focus across the canonical rerender');
    assert.equal(await page.evaluate(() => window.__mealEvents.length), eventCount + 1, 'one local projection, no render loop');
    assert.deepEqual(await visible(), ['meal-b', 'meal-a'], 'sort uses matching offer prices, not excluded cheaper meals');
    assert.deepEqual(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.map(node => node.dataset.calendarDate)), ['2026-09-11', '2026-09-12', '2026-09-14'], 'meal facet removes excluded offers from the current price calendar');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-11', 'calendar best date follows the cheapest matching meal');
    const budget = page.locator('.search3-budget-filter input');
    await budget.evaluate(node => { node.value = '110000'; node.dispatchEvent(new Event('input', { bubbles: true })); });
    assert.deepEqual(await visible(), ['meal-b'], 'budget and meal must match the same loaded offer');
    assert.equal(await calendar.isVisible(), false, 'one matching departure hides a calendar that has no dates left to compare');
    await budget.evaluate(node => { node.value = node.max; node.dispatchEvent(new Event('input', { bubbles: true })); });
    assert.deepEqual(await visible(), ['meal-b', 'meal-a'], 'restoring the budget keeps the active meal projection');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-11', 'restoring the budget restores the matching meal calendar minimum');
    await page.evaluate(() => {
      const items = window.__mealOriginal;
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-continued', { detail: { items } }));
    });
    assert.deepEqual(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.map(node => node.dataset.calendarDate)), ['2026-09-11', '2026-09-12', '2026-09-14'], 'raw continuation event cannot overwrite the matching meal projection');
    const a = page.locator('#results [data-hotel-id=meal-a]');
    assert.equal(await a.locator('.direct-tour').getAttribute('data-tid'), 'a-ai', 'representative choice keeps its original tour ID');
    assert.equal(await a.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), '120000₽', 'selected meal sets the actual displayed offer price');
    assert.match(await a.locator('.hotel-choice-hint').innerText(), /4 варианта/, 'counts all matching AI and UAI offers across providers');
    await a.locator('.tour-more-toggle').focus();
    await a.locator('.tour-more-toggle').press('Enter');
    assert.equal(await a.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'meal disclosure keeps keyboard focus after replacing its contents');
    assert.deepEqual(await a.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['a-ai', 'a-ai-extra', 'a-uai'], 'expansion keeps selectable AI and UAI offers without reintroducing an excluded meal');
    assert.equal(await a.locator('.tour-selection-note').count(), 1, 'equivalent Andromeda AI remains visible but cannot enter the Tourvisor selection controller');
    assert.doesNotMatch(await a.locator('.hotel-tours').innerText(), /Без питания|90000/);
    assert.equal(await a.locator('.hotel-price').first().innerText().then(text => text.replace(/\s/g, '')), '120000₽', 'expanded meal offers start with the same matching price');
    await a.locator('.tour-more-toggle').press('Space');
    assert.equal(await a.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'meal collapse keeps focus on the replacement disclosure');
    assert.deepEqual(await a.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['a-ai'], 'collapse returns to the same nonfirst matching offer');
    assert.equal(await a.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), '120000₽', 'collapse retains the selected meal price');
    assert.equal(await page.evaluate(() => window.V2Results.state.items.length === 3 && window.V2Results.state.items.every((h, i) => h === window.__mealOriginal[i]) && window.__mealEvents.every(list => list.length === 3 && list.every((h, i) => h === window.__mealOriginal[i]))), true, 'original result state, event items and continuation count remain intact');
    assert.equal(await page.evaluate(() => JSON.stringify(window.V2Results.state.items)), JSON.stringify(items), 'frozen source tours and prices are unchanged');
    await name.fill('Отель А');
    await category.selectOption('4');
    assert.deepEqual(await visible(), [], 'name/category/meal combine through one hidden-state owner');
    assert.equal(await calendar.isVisible(), false, 'zero local matches hide the stale price calendar');
    await page.evaluate(() => {
      const items = window.__mealOriginal;
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-continued', { detail: { items } }));
    });
    assert.equal(await calendar.isVisible(), false, 'empty local projection remains empty after raw continuation');
    assert.match(await page.locator('#search3HotelFilterStatus').innerText(), /Показано 0 из 3/);
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await select.inputValue(), 'meal:all-inclusive');
    assert.equal(await name.inputValue(), 'Отель А');
    assert.equal(await category.inputValue(), '4');
    assert.deepEqual(await visible(), [], 'sort preserves all local choices, including zero matches');
    await name.fill(''); await category.selectOption('0');
    assert.deepEqual(await visible(), ['meal-a', 'meal-b']);
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-11', 'clearing name and category restores the meal-filtered calendar');
    await page.locator('#sortResults').selectOption('price');
    assert.equal((await snapshot(page)).overflow, false, 'meal controls and projected cards fit the viewport');
    assert.ok((await select.boundingBox()).height >= 44, 'meal selector keeps a usable touch target');
    if (!previous) await page.screenshot({ path: path.join(output, `meal-filter-${width}.png`), fullPage: true });
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })));
    assert.equal(await field.isVisible(), false, 'dirty edit hides stale controls');
    assert.equal(await calendar.isVisible(), false, 'dirty edit hides the calendar until retained results are shown again');
    assert.equal(await select.inputValue(), 'meal:all-inclusive', 'dirty edit preserves the retained result projection');
    assert.deepEqual(await visible(), ['meal-b', 'meal-a'], 'dirty event does not reveal excluded stale offers');
    await page.evaluate(() => window.V2Results.rerender());
    if (width < 1025) await page.locator('.search3-mobile-filter-panel summary').click();
    assert.equal(await field.isVisible(), true);
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-11', 'returning to completed results restores the meal-filtered calendar');
    assert.deepEqual(await visible(), ['meal-b', 'meal-a'], 'returning to the retained results preserves meal selection');
    await select.selectOption('');
    assert.deepEqual(await visible(), ['meal-c', 'meal-a', 'meal-b'], 'clear restores every loaded hotel and original ordering');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-13', 'clearing all local filters restores the full calendar minimum');
    assert.equal(await a.locator('[data-tid=a-ro]').count(), 1, 'clear restores original tours, including earlier excluded meals');
    await select.selectOption('meal:all-inclusive');
    await page.evaluate(items => window.V2Results.render(items.concat([{ id: 'meal-incomplete', name: 'Неполные данные', price: 70000, tours: [{ id: 'unknown', price: 70000, meal: { id: 7 } }] }])), items);
    assert.equal(await field.isVisible(), false, 'incomplete progressive set hides the facet');
    assert.equal(await select.inputValue(), '', 'incomplete set resets selection before rendering prices');
    assert.equal((await visible()).length, 4, 'no silent filtering remains on incomplete data');
    await page.evaluate(items => window.V2Results.render(items), items);
    await select.selectOption('meal:all-inclusive');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 101 } })));
    assert.equal(await select.inputValue(), '', 'a real new search clears the local meal');
    assert.equal(await field.isVisible(), false);
    await page.evaluate(items => window.V2Results.render(items), items);
    if (width < 1025) await page.locator('.search3-mobile-filter-panel summary').click();
    assert.deepEqual(await visible(), ['meal-c', 'meal-a', 'meal-b'], 'new search starts without inherited local selection');
    assert.equal(await calendar.isVisible(), false, 'new search cannot show a calendar before its terminal event');
    await select.selectOption('meal:all-inclusive');
    assert.equal(await calendar.isVisible(), false, 'a facet chosen during progressive results waits for completion');
    await page.evaluate(items => window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items } })), items);
    assert.deepEqual(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.map(node => node.dataset.calendarDate)), ['2026-09-11', '2026-09-12', '2026-09-14'], 'completion uses the facet chosen before the terminal event');
    const longLabel = '<img src=x onerror=bad()> Очень длинное описание питания от поставщика без сокращений';
    await page.evaluate(({ items, longLabel }) => { items[0].tours[0].meal = { fullName: longLabel }; window.V2Results.render(items); }, { items, longLabel });
    assert.equal(await select.locator('img').count(), 0, 'supplier labels are rendered as text, never HTML');
    assert.equal((await snapshot(page)).overflow, false, 'long supplier label does not widen the toolbar');
    assert.deepEqual(supplierRequests, [], 'local filtering issues no supplier or lead requests');
  } finally { page.off('request', record); }
}
async function checkAndromedaExpansion(page, width, previous, control) {
  const tvHotel = {
    id: 21477,
    name: 'Movenpick Resort',
    country: { name: 'Египет' },
    region: { name: 'Шарм-эль-Шейх' },
    category: 4,
    rating: 4.7,
    price: 165000,
    tours: [{ ...tour, id: 'tv-andromeda-control', price: 165000, operator: { name: 'TEST OPERATOR' } }]
  };
  const searchParams = { departureId: '1', countryId: '1', dateFrom: '2026-09-18', dateTo: '2026-09-18', nightsFrom: '8', nightsTo: '8', adults: '2', childs: [], currency: 'RUB' };
  const start = async generation => {
    await page.evaluate(({ generation, searchParams, tvHotel }) => {
      Object.defineProperty(window.V2SearchLifecycle, 'generation', { configurable: true, get: () => generation });
      Object.defineProperty(window.V2SearchLifecycle, 'snapshot', { configurable: true, get: () => ({ ...searchParams }) });
      window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { generation } }));
      window.V2Results.render([tvHotel], { empty: true });
    }, { generation, searchParams, tvHotel });
    await page.locator('[data-andromeda-expand="21477"]').waitFor();
  };
  control.enabled = true;
  control.requests.length = 0;
  control.failSecond = false;
  try {
    await start(73);
    const card = page.locator('#results .hotel-card[data-hotel-id="21477"]');
    if (width >= 1025) {
      assert.equal(await page.locator('.results-filter-rail').isVisible(), false, 'one loaded hotel does not expose unusable local facets');
      assert.ok((await card.boundingBox()).width >= 700, 'desktop single-hotel results reclaim the hidden filter-rail track');
    }
    assert.equal(await card.locator('.tour-row').count(), 1, 'accepted grouped Andromeda offer keeps one compact representative');
    assert.equal(await card.locator('.direct-tour').count(), 0, 'an unquoted provider representative cannot enter the selection controller');
    assert.equal(await card.locator('[data-andromeda-expand]').innerText(), 'Все варианты Андромеды', 'current card exposes one explicit provider expansion action');
    await card.locator('[data-andromeda-expand]').click();
    await card.locator('.tour-selection-note[role=status]').filter({ hasText: 'Варианты Андромеды загружены: 2' }).waitFor();
    assert.deepEqual(control.requests.map(request => [request.action || 'search', request.page]), [['search', 1], ['hotel_offers', 1], ['hotel_offers', 2]], 'one discovery and two scoped provider pages load sequentially');
    await card.locator('.tour-more-toggle').click();
    assert.equal(await card.locator('.tour-row').count(), 3, 'complete expansion replaces the grouped representative with exact provider variants and retains Tourvisor');
    assert.equal(await card.locator('.direct-tour').count(), 1, 'only the existing Tourvisor offer remains selectable');
    assert.equal(await card.locator('.tour-secondary-facts').filter({ hasText: 'Андромеда' }).count(), 2, 'expanded provider variants remain visibly attributed');
    assert.equal(await card.locator('.tour-selection-note').filter({ hasText: 'перед выбором нужна проверка' }).count(), 2, 'every Andromeda variant keeps the quote-required boundary');
    const detailToggle = card.locator('[data-andromeda-detail]').first();
    assert.ok((await detailToggle.boundingBox()).height >= 44, 'provider detail action keeps a full touch target');
    await detailToggle.click();
    await card.locator('.provider-detail').filter({ hasText: 'Подтверждённый тестовый отель' }).waitFor();
    assert.equal(await detailToggle.getAttribute('aria-expanded'), 'true', 'provider detail disclosure exposes its open state');
    assert.equal(await detailToggle.evaluate(node => node === document.activeElement), true, 'provider detail keeps keyboard focus after rerender');
    assert.match(await card.locator('.provider-detail').innerText(), /ANEX · 2026-09-18 · 8 ноч\. · 2 взр\. · AI · <script>номер<\/script> · DBL/);
    assert.equal(await card.locator('.provider-detail script').count(), 0, 'supplier detail strings are escaped instead of becoming markup');
    assert.match(await card.locator('.provider-detail').innerText(), /155[\u00a0 ]079 ₽/);
    assert.match(await card.locator('.provider-detail').innerText(), /Бронирование пока недоступно/);
    assert.deepEqual(control.requests.map(request => request.action || 'search'), ['search', 'hotel_offers', 'hotel_offers', 'offer_detail'], 'details add one explicit saved-offer request only');
    await detailToggle.click();
    assert.equal(await card.locator('.provider-detail').count(), 0, 'detail action closes the disclosure');
    await detailToggle.click();
    assert.equal(await card.locator('.provider-detail').count(), 1, 'cached detail reopens without a new request');
    assert.equal(control.requests.length, 4, 'close and cached reopen do not replay detail or provider pages');
    if (width <= 760) {
      const providerPrice = await card.locator('.tour-row').first().locator('.hotel-price').boundingBox();
      assert.ok(providerPrice.width >= 90 && providerPrice.height <= 45, 'mobile unquoted provider price stays readable instead of wrapping digit by digit');
    }
    assert.equal((await snapshot(page)).overflow, false, width + ': complete provider expansion fits the viewport');
    if (!previous) await page.screenshot({ path: path.join(output, `andromeda-details-${width}.png`), fullPage: true });
    await page.evaluate(() => window.AnyTourAndromedaProvider.expandHotel('21477'));
    assert.equal(control.requests.length, 4, 'rerender or repeated expansion cannot replay provider pages');

    control.failSecond = true;
    control.requests.length = 0;
    await start(74);
    const partialCard = page.locator('#results .hotel-card[data-hotel-id="21477"]');
    await partialCard.locator('[data-andromeda-expand]').click();
    await partialCard.locator('.tour-selection-note[role=status]').filter({ hasText: 'Не все варианты Андромеды загрузились' }).waitFor();
    assert.deepEqual(control.requests.map(request => [request.action || 'search', request.page]), [['search', 1], ['hotel_offers', 1], ['hotel_offers', 2]], 'partial expansion stops after the failed scoped page without background replay');
    await partialCard.locator('.tour-more-toggle').click();
    assert.equal(await partialCard.locator('.tour-row').count(), 3, 'partial failure retains Tourvisor, grouped representative and received exact variant');
    assert.equal(await partialCard.locator('.direct-tour').count(), 1, 'partial provider data cannot enter the selection controller');
    assert.equal((await snapshot(page)).overflow, false, width + ': partial provider status fits the viewport');
  } finally {
    control.enabled = false;
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })));
  }
}
async function run(browser, width, previous) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } }), errors = [];
  const andromeda = { enabled: false, failSecond: false, requests: [] };
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
    if (andromeda.enabled && url.pathname.endsWith('/api-andromeda-search3-preview.php')) {
      const input = JSON.parse(request.postData() || '{}');
      andromeda.requests.push(input);
      if (input.action === 'hotel_offers' && andromeda.failSecond && input.page === 2) return route.abort('failed');
      if (input.action === 'offer_detail') return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { provider: 'andromeda', offer_context: input.offer_context, hotel: 'Подтверждённый тестовый отель', operator: 'ANEX', room: '<script>номер<\/script>', placement: 'DBL', checkin: '2026-09-18', nights: 8, adults: 2, children: 0, meal: 'AI', price: { amount: '155079.00', currency: 'RUB' } } }) });
      const offerRef = 'offer_' + String(input.action === 'hotel_offers' ? input.page : 9).repeat(64);
      const seed = { provider: 'andromeda', search_ref: 'd'.repeat(64), generation: input.generation, page: 1, offer_ref: 'offer_' + '9'.repeat(64) };
      const context = input.action === 'hotel_offers'
        ? { ...seed, page: input.page, offer_ref: offerRef, hotel_scope: input.hotel_scope }
        : seed;
      const hotel = { local_id: 21477, name: 'Movenpick Resort', provider: 'andromeda', mapping_status: 'resolved', country: 'Египет', category: 4,
        andromeda_content: { source: 'andromeda', region: 'Шарм-эль-Шейх' },
        tours: [{ provider: 'andromeda', offer_ref: offerRef, offer_context: context, price: { amount: input.action === 'hotel_offers' ? String(154000 + input.page * 1000) : '155079.00', currency: 'RUB' }, checkin: '2026-09-18', nights: 8, meal: 'AI', room: input.action === 'hotel_offers' ? 'ROOM ' + input.page : 'GROUPED ROOM', placement: '2 ADL', operator: 'ANEX' }] };
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { provider: 'andromeda', generation: input.generation, page: input.page, pages_count: input.action === 'hotel_offers' ? 2 : 1, grouped: input.action === 'hotel_offers' ? false : true, hotels: [hotel] } }) });
    }
    if (url.origin !== new URL(base).origin || request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
    if (previous && url.pathname.endsWith('/bundle-v1.php') && url.searchParams.get('type') === 'js') return route.fulfill({ status: 200, contentType: 'application/javascript', body: raw });
    return route.continue();
  });
  try {
    assert.equal((await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' })).status(), 200);
    await page.waitForFunction(() => window.V2Results && window.V2TourController && document.querySelector('#tourSearch')?.dataset.search3Ready === '1');
    // This offline fixture aborts every supplier/catalog request. Wait for its
    // real recovery UI before comparing geometry; otherwise that asynchronous
    // sibling can be inserted between raw/served snapshots (160px at 375px).
    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    assert.equal(await page.locator('.catalog-recovery').isVisible(), true, 'blocked catalogs expose their canonical recovery before result measurement');
    assert.equal(await page.locator('#resultsSearchSummary').count(), 0, 'Search3 does not render the retired placeholder summary');
    const logo = page.locator('.at-global-header__logo img');
    assert.equal(await logo.isVisible(), true, 'canonical logo remains visible');
    const logoSource = await logo.getAttribute('src');
    const menu = page.locator('.at-global-header__mobile');
    const nav = page.locator('.at-global-header__nav');
    if (width <= 1024) {
      assert.equal(await nav.isVisible(), false, 'mobile uses the native disclosure');
      await menu.locator('summary').click();
      assert.equal(await menu.evaluate(node => node.open), true, 'native header opens');
      assert.equal(await menu.locator('[aria-current=page]').isVisible(), true, 'active search link is reachable');
      await menu.locator('summary').click();
      assert.equal(await menu.evaluate(node => node.open), false, 'native header closes');
    } else {
      assert.equal(await menu.isVisible(), false, 'desktop does not duplicate navigation');
      assert.equal(await nav.isVisible(), true, 'desktop navigation is directly available');
      assert.equal(await nav.locator('a').count(), 6, 'all canonical destinations remain');
      assert.equal(await nav.locator('[aria-current=page]').isVisible(), true, 'active search link is reachable');
    }
    await page.evaluate(items => window.V2Results.render(items), hotels);
    await page.waitForSelector('#results .direct-tour');
    await page.evaluate(items => {
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items } }));
    }, calendarHotels);
    const calendar = page.locator('#currentPriceCalendar');
    assert.equal(await calendar.isVisible(), true, 'current price calendar is visible after a terminal result set');
    assert.deepEqual(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.map(node => [node.dataset.calendarDate, node.querySelector('strong').textContent.replace(/\s/g, '')])), [
      ['2026-09-10', '99000₽'], ['2026-09-12', '148500₽']
    ], 'calendar exposes per-day minima and ignores unpriced tours');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-10', 'lowest observed day is highlighted');
    assert.equal(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.every(node => node.getBoundingClientRect().height >= 44)), true, 'calendar dates retain accessible touch targets');
    await page.evaluate(items => {
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-continued', { detail: { items } }));
    }, calendarHotels.concat([
      { id: 'calendar-c', tours: [{ ...tour, id: 'calendar-c1', date: '2026-09-14', price: 88000 }] }
    ]));
    assert.equal(await calendar.locator('[data-calendar-date]').count(), 3, 'continued results refresh the calendar instead of leaving stale dates');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-14', 'continued results refresh the highlighted minimum');
    const preservedBeforeCalendar = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()].filter(([name]) => !['dateFrom', 'dateTo'].includes(name)));
    await page.evaluate(() => {
      window.__calendarSubmits = 0;
      window.V2SearchLifecycle.submit = () => { window.__calendarSubmits += 1; };
    });
    await calendar.locator('[data-calendar-date="2026-09-14"]').click();
    assert.equal(await page.evaluate(() => window.__calendarSubmits), 1, 'calendar date submits through the canonical lifecycle exactly once');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [form.elements.dateFrom.value, form.elements.dateTo.value]), ['2026-09-14', '2026-09-14'], 'calendar applies the exact selected day');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()].filter(([name]) => !['dateFrom', 'dateTo'].includes(name))), preservedBeforeCalendar, 'calendar preserves every non-date search parameter');
    const parameters = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]);
    assert.equal(await page.locator('#resultsTools #resultsSearchEdit').count(), 1, 'native results tools retain one search edit action');
    await page.locator('#resultsSearchEdit').click();
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'results edit action reveals the canonical search form');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]), parameters, 'editing preserves all current search parameters');
    await page.evaluate(items => window.V2Results.render(items), hotels);
    assert.equal(await page.locator('#results .hotel-card').first().getAttribute('data-hotel-id'), 'cheap', 'price sorting retained');
    const localHotelFilter = page.locator('.search3-hotel-filter');
    const localHotelInput = localHotelFilter.locator('input');
    const localCategoryFilter = page.locator('.search3-category-filter');
    const localCategorySelect = localCategoryFilter.locator('select');
    const localCategoryPresets = localCategoryFilter.locator('.search3-filter-presets');
    const localBudgetFilter = page.locator('.search3-budget-filter');
    const localBudgetInput = localBudgetFilter.locator('input');
    const localOperatorFilter = page.locator('.search3-operator-filter');
    const localOperatorSelect = localOperatorFilter.locator('select');
    const localRatingFilter = page.locator('.search3-rating-filter');
    const localRatingSelect = localRatingFilter.locator('select');
    const localSeaFilter = page.locator('.search3-sea-filter');
    const localSeaSelect = localSeaFilter.locator('select');
    const localReset = page.locator('.search3-filter-reset');
    const mobilePanel = page.locator('.search3-mobile-filter-panel');
    const mobileSummary = mobilePanel.locator('summary');
    const mobileSummaryText = mobilePanel.locator('[data-search3-mobile-filter-summary]');
    if (width < 1025) {
      assert.equal(await mobilePanel.isVisible(), true, 'tablet and mobile expose one compact current filter disclosure');
      assert.equal(await mobilePanel.getAttribute('open'), null, 'mobile disclosure starts compact');
      await mobileSummary.focus();
      await page.keyboard.press('Enter');
      assert.notEqual(await mobilePanel.getAttribute('open'), null, 'native summary opens current filters from the keyboard');
    } else assert.equal(await mobilePanel.isVisible(), false, 'desktop does not expose the mobile disclosure');
    assert.equal(await localHotelFilter.isVisible(), true, 'one local hotel filter appears for multiple loaded hotels');
    assert.equal(await localBudgetFilter.isVisible(), true, 'complete loaded offer prices expose a budget facet');
    assert.equal(await localOperatorFilter.isVisible(), true, 'complete loaded offer operators expose one local operator facet');
    assert.deepEqual(await localOperatorSelect.locator('option').allTextContents(), ['Все туроператоры', 'OTHER OPERATOR', 'TEST OPERATOR'], 'operator choices keep the original labels and do not confuse operator with provider');
    assert.equal(await localCategoryFilter.isVisible(), true, 'category facet appears when every loaded hotel has a category');
    assert.deepEqual(await localCategoryPresets.locator('button').allTextContents(), ['5★', '4★'], 'complete category values expose quick exact choices without inventing a threshold');
    assert.ok((await localCategoryPresets.locator('button').first().boundingBox()).height >= 44, 'category quick choice keeps a full touch target');
    assert.equal(await localRatingFilter.isVisible(), true, 'rating facet appears when every loaded hotel has a rating');
    assert.equal(await localSeaFilter.isVisible(), true, 'sea facet appears when every loaded hotel has a distance');
    const rail = page.locator('.results-filter-rail'), actions = page.locator('#resultsTools .results-tools__actions');
    if (width >= 1025) {
      assert.equal(await rail.isVisible(), true, 'desktop exposes one canonical left filter rail');
      assert.equal(await localHotelFilter.evaluate(node => node.parentElement.className), 'results-filter-rail', 'desktop moves current filter owners into the rail');
      const railBox = await rail.boundingBox(), resultsBox = await page.locator('#results').boundingBox();
      assert.ok(railBox.width >= 220 && resultsBox.x >= railBox.x + railBox.width - 1, 'desktop rail and cards use separate readable columns');
    } else {
      assert.equal(await rail.isVisible(), false, 'tablet and mobile do not reserve an empty rail');
      assert.equal(await localHotelFilter.evaluate(node => node.parentElement.className), 'search3-mobile-filter-panel__body', 'tablet and mobile reuse the current controls inside one disclosure');
      assert.equal(await actions.isVisible(), true);
    }
    await localOperatorSelect.selectOption('test operator');
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'operator facet narrows already loaded offers without using the provider label');
    await localBudgetInput.evaluate(node => { node.value = '100000'; node.dispatchEvent(new Event('input', { bubbles: true })); });
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), [], 'operator and budget must match the same exact loaded offer');
    if (width < 1025) {
      assert.match(await mobileSummaryText.innerText(), /Подходит: 0 · до 100[\s\u00a0]*000 ₽ · TEST OPERATOR/, 'compact summary names active exact-offer filters instead of exposing only their count');
      assert.match(await mobileSummaryText.getAttribute('aria-label'), /активные фильтры: до 100[\s\u00a0]*000 ₽; TEST OPERATOR/, 'compact summary exposes the full active-filter meaning accessibly');
      await mobileSummary.click();
      assert.equal(await mobilePanel.getAttribute('open'), null, 'active values remain visible while the native filter disclosure is collapsed');
      assert.equal((await snapshot(page)).overflow, false, 'active filter values safely fit the compact toolbar');
      await mobileSummary.click();
    }
    await localBudgetInput.evaluate(node => { node.value = node.max; node.dispatchEvent(new Event('input', { bubbles: true })); });
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'restoring budget keeps the active operator projection');
    await localOperatorSelect.selectOption('');
    await localBudgetInput.evaluate(node => { node.value = '100000'; node.dispatchEvent(new Event('input', { bubbles: true })); });
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['cheap'], 'clearing operator restores the exact qualifying budget offer');
    assert.equal(await page.locator('#results [data-hotel-id=cheap] .hotel-price').innerText().then(text => text.replace(/\s/g, '')), '90000₽', 'budget retains the exact qualifying offer price');
    await localBudgetInput.evaluate(node => { node.value = node.max; node.dispatchEvent(new Event('input', { bubbles: true })); });
    await localRatingSelect.selectOption('4.5');
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'rating threshold filters the loaded hotels locally');
    await localRatingSelect.selectOption('0');
    await localSeaSelect.selectOption('200');
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'sea threshold filters only complete loaded distance data');
    await localSeaSelect.selectOption('0');
    const categoryFive = localCategoryPresets.locator('button[data-value="5"]');
    await categoryFive.click();
    assert.equal(await categoryFive.getAttribute('aria-pressed'), 'true', 'category quick choice exposes its selected state');
    assert.equal(await categoryFive.evaluate(node => node === document.activeElement), true, 'category quick choice keeps focus after filtering');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 1, 'category facet filters only the already loaded hotels');
    assert.equal(await localReset.isVisible(), true, 'an active local facet exposes one reset action at every responsive width');
    assert.equal(await localReset.evaluate(node => node.parentElement.className), width >= 1025 ? 'results-filter-rail' : 'search3-mobile-filter-panel__body', 'reset action follows the current responsive filter owner');
    if (width < 1025) assert.match(await mobileSummaryText.innerText(), /Подходит: 1 · 5★/, 'compact summary exposes the current result and active filter value');
    assert.match(await localHotelFilter.locator('small').innerText(), /Показано 1 из 2 загруженных отелей/, 'category facet reports a truthful loaded-card count');
    await localHotelInput.fill('  ВТОРОЙ  ');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 0, 'hotel name and category filters combine locally');
    assert.match(await localHotelFilter.locator('small').innerText(), /Показано 0 из 2 загруженных отелей/, 'combined filters report their truthful loaded-card count');
    const localEmpty = page.locator('#results .search3-local-empty');
    const localEmptyReset = localEmpty.locator('.search3-local-empty-reset');
    assert.equal(await localEmpty.count(), 1, 'zero matching local filters expose one actionable empty state');
    assert.equal(await localEmpty.isVisible(), true, 'local empty state is visible above the hidden loaded cards');
    assert.match(await localEmpty.innerText(), /По выбранным фильтрам ничего не подошло[\s\S]*Сбросить фильтры/, 'local empty state explains the recoverable filter result');
    assert.ok((await localEmptyReset.boundingBox()).height >= 44, 'local empty reset keeps a full touch target');
    if (!previous && [375, 1440].includes(width)) await page.screenshot({ path: path.join(output, `local-empty-${width}.png`), fullPage: true });
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await localHotelInput.inputValue(), '  ВТОРОЙ  ', 'sorting preserves the local hotel query');
    assert.equal(await localCategorySelect.inputValue(), '5', 'sorting preserves the local category');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 0, 'sorting reapplies both local filters to rerendered cards');
    assert.equal(await localEmpty.count(), 1, 'sorting keeps exactly one local empty state');
    if (width < 1025) assert.match(await mobileSummaryText.innerText(), /Подходит: 0 · 5★ · Отель: ВТОРОЙ/, 'sorting preserves the visible active-filter summary');
    await localEmptyReset.focus();
    await localEmptyReset.press('Enter');
    await page.waitForFunction(() => document.activeElement?.matches('#results .hotel-card:not([hidden]) .hotel-title'));
    assert.equal(await localCategorySelect.inputValue(), '0', 'one reset clears the active category');
    assert.equal(await localHotelInput.inputValue(), '', 'one reset clears the hotel query');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'one reset restores every loaded card');
    assert.equal(await localEmpty.count(), 0, 'one reset removes the local empty state');
    assert.equal(await localReset.isVisible(), false, 'reset action hides when no local filter remains active');
    assert.equal(await categoryFive.getAttribute('aria-pressed'), 'false', 'common reset clears the mirrored quick choice state');
    assert.equal(await page.locator('#results .hotel-card:visible .hotel-title').first().evaluate(node => node === document.activeElement), true, 'zero-match reset moves keyboard focus to the first restored hotel');
    if (width < 1025) {
      assert.equal(await mobileSummaryText.innerText(), 'Подходит: 2', 'reset removes stale active values from the compact summary');
      assert.equal(await mobileSummaryText.getAttribute('aria-label'), 'Подходит: 2; активных фильтров нет', 'reset exposes an accurate accessible empty-filter state');
    }
    await categoryFive.focus();
    await categoryFive.press('Enter');
    await localReset.focus();
    await localReset.press('Enter');
    await page.waitForFunction(() => document.activeElement?.matches('.search3-hotel-filter input'));
    assert.equal(await localCategorySelect.inputValue(), '0', 'common keyboard reset clears the active category');
    assert.equal(await localHotelInput.evaluate(node => node === document.activeElement), true, 'common keyboard reset returns focus to the first visible filter');
    await localOperatorSelect.selectOption('test operator');
    await page.evaluate(items => window.V2Results.render(items), [hotels[0], { ...hotels[1], tours: [{ ...hotels[1].tours[0], operator: null }] }]);
    assert.equal(await localOperatorFilter.isVisible(), false, 'operator facet hides when any loaded offer lacks its operator label');
    assert.equal(await localOperatorSelect.inputValue(), '', 'incomplete operator data resets the local choice');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'an incomplete operator facet never silently removes a loaded hotel');
    if (width < 1025) assert.doesNotMatch(await mobileSummaryText.innerText(), /TEST OPERATOR/, 'incomplete operator data also removes its stale compact summary value');
    await page.evaluate(items => window.V2Results.render(items), [hotels[0], { ...hotels[1], category: 0 }]);
    assert.equal(await localCategoryFilter.isVisible(), false, 'category facet hides when any loaded hotel lacks category data');
    assert.equal(await localCategoryPresets.isVisible(), false, 'incomplete category data also hides its quick choices');
    assert.equal(await localCategorySelect.inputValue(), '0', 'incomplete category data resets the local choice');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'an incomplete facet never silently removes a loaded hotel');
    await page.evaluate(items => {
      const freeze = value => { if (value && typeof value === 'object') { Object.values(value).forEach(freeze); Object.freeze(value); } return value; };
      window.__decisionOriginal = freeze(items);
      window.V2Results.render(window.__decisionOriginal);
    }, hotels);
    await page.locator('#sortResults').selectOption('price');
    const card = page.locator('#results [data-hotel-id=expensive].hotel-card');
    assert.equal(await card.locator('.hotel-title').evaluate(node => node.tagName), 'H3', 'hotel name keeps a semantic card heading');
    assert.equal(await card.locator('.hotel-best-offer').count(), 0, 'card does not repeat the representative tour price');
    assert.equal(await card.locator('.hotel-price').count(), 1, 'collapsed card exposes one authoritative total');
    assert.match(await card.locator('.hotel-decision-rating').innerText(), /Рейтинг 5/, 'hotel score is not confused with star category');
    assert.match(await page.locator('#resultSummary').innerText(), /цены указаны за весь тур/, 'result summary explains price scope');
    assert.equal(await card.locator('.tour-row').count(), 1, 'representative tour shown immediately');
    assert.ok((await card.locator('.direct-tour').boundingBox()).height >= 44, 'real selection action retains a full touch target');
    assert.match(await card.locator('.tour-facts').innerText(), /Всё включено/, 'supplier fullName expands the abbreviation in offer facts');
    assert.equal(await card.locator('.tour-meta>small').innerText(), 'Дата вылета · 9 ноч.', 'departure context states the duration beside the date');
    assert.equal(await card.locator('.tour-meta>strong').innerText(), tour.date, 'compact facts preserve the actual departure date');
    assert.deepEqual(await card.locator('.tour-facts .tour-fact').evaluateAll(nodes => nodes.map(node => [node.querySelector('small').textContent, node.querySelector('b').textContent])), [['Питание', 'Всё включено'], ['Номер', 'STANDARD LAND VIEW']], 'primary comparison facts keep their labels and original values');
    assert.deepEqual(await card.locator('.tour-secondary-facts .tour-fact').evaluateAll(nodes => nodes.map(node => [node.querySelector('small').textContent, node.querySelector('b').textContent])), [['Источник', 'Tourvisor'], ['Оператор', 'TEST OPERATOR'], ['Размещение', 'DBL']], 'source and operator remain distinct while secondary facts keep unambiguous labels');
    const { photo, body } = await card.evaluate(node => {
      const rect = element => {
        const box = element.getBoundingClientRect();
        return { x: box.x, y: box.y, width: box.width, height: box.height };
      };
      return { photo: rect(node.querySelector('.hotel-photo')), body: rect(node.querySelector('.hotel-body')) };
    });
    assert.ok(photo.height >= 150, 'hotel photo remains legible at the current width');
    if (width <= 760) assert.ok(body.y >= photo.y + photo.height - 1, 'mobile hotel content follows the photo without overlap: '+JSON.stringify({width,previous,photo,body}));
    else assert.ok(body.x >= photo.x + photo.width - 1, 'desktop hotel content sits beside the photo without overlap');
    assert.equal(await card.locator('.direct-tour').getAttribute('data-tid'), tour.id, 'selection identity retained');
    assert.equal(await card.locator('.direct-tour').innerText(), 'Выбрать тур', 'selection action identifies its target');
    const collapsed = await snapshot(page);
    if (collapsed.overflow) console.error(JSON.stringify({width,previous,offenders:collapsed.offenders}));
    assert.equal(collapsed.overflow, false, width + ': results fit viewport');
    if (!previous && [375, 1440].includes(width)) await page.screenshot({ path: path.join(output, `results-collapsed-${width}.png`), fullPage: true });
    await card.locator('.tour-more-toggle').focus();
    await card.locator('.tour-more-toggle').press('Enter');
    assert.equal(await card.locator('.tour-row').count(), 3, 'actual toggle reveals all tours');
    assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['current-tour', 'other-tour', 'third-tour'], 'expansion pins the nonfirst representative and preserves the order of all remaining offers');
    assert.equal(await card.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'keyboard expansion retains focus on the replacement disclosure');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'true');
    const expanded = await snapshot(page);
    assert.equal(expanded.overflow, false, width + ': expanded results fit viewport');
    if (!previous && [375, 1440].includes(width)) await page.screenshot({ path: path.join(output, `results-expanded-${width}.png`), fullPage: true });
    assert.equal(await card.locator('.tour-meta>strong').first().evaluate(node => getComputedStyle(node, '::before').content), 'none', 'result dates have no duplicate generated label');
    assert.match(await card.innerText(), /148[\s\u00a0]*500,6/, 'decimal price remains visible');
    await card.locator('.tour-more-toggle').press('Space');
    assert.equal(await card.locator('.tour-row').count(), 1, 'actual toggle collapses');
    assert.equal(await card.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'keyboard collapse retains focus on the replacement disclosure');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'false');
    assert.equal(await card.locator('.direct-tour').getAttribute('data-tid'), tour.id, 'collapse retains the representative identity');
    assert.equal(await page.evaluate(() => window.V2Results.state.items.every((hotel, i) => hotel === window.__decisionOriginal[i]) && window.V2Results.representativeTour(window.__decisionOriginal[0]) === window.__decisionOriginal[0].tours[1]), true, 'render and disclosure preserve original hotel and representative tour objects');
    assert.equal(await page.evaluate(() => JSON.stringify(window.V2Results.state.items)), JSON.stringify(hotels), 'disclosure leaves frozen source prices, tour order and contents unchanged');
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await page.locator('#results .hotel-card').first().getAttribute('data-hotel-id'), 'expensive', 'rating sorting retained');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 44 } })));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'search start exposes a truthful loading state');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-progress', { detail: { progress: 47 } })));
    assert.equal(await page.locator('#status .results-state-progress span').evaluate(node => node.style.width), '47%', 'progress state reflects the reported percentage');
    assert.equal(await page.locator('#status .results-state-progress').getAttribute('aria-valuenow'), '47', 'progress exposes its value to assistive technology');
    assert.doesNotMatch(await page.locator('#status').innerText(), /Уже найдено отелей: 2/, 'new search does not inherit the previous result count');
    await page.evaluate(() => window.V2Results.render([], { empty: false }));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'intermediate empty response keeps the active loading state');
    assert.equal(await page.locator('#results .empty-actionable').count(), 0, 'intermediate empty response is not presented as final');
    await page.evaluate(items => window.V2Results.render(items), hotels);
    const errorParameters = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]);
    await page.evaluate(() => {
      window.__resultsRetrySubmits = 0;
      window.V2SearchLifecycle.submit = () => {
        window.__resultsRetrySubmits += 1;
        if (window.__replaceResultsOnRecovery) document.getElementById('results').innerHTML = '<div class="skeleton-grid"><div class="skeleton-card"></div></div>';
        window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { generation: 45 } }));
        window.__releaseRetryStart = () => window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 45 } }));
      };
      window.dispatchEvent(new CustomEvent('v2:search-error', { detail: { phase: 'status' } }));
    });
    assert.equal(await page.locator('#status .results-state--error').isVisible(), true, 'search failure exposes a distinct error state');
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'search failure leaves parameters editable even with retained results');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]), errorParameters, 'error recovery preserves every search parameter');
    if (!previous && [375, 1440].includes(width)) await page.screenshot({ path: path.join(output, `search-error-edit-${width}.png`), fullPage: true });
    const retrySearch = page.locator('#status .results-state-retry');
    await retrySearch.focus();
    await retrySearch.press('Enter');
    assert.equal(await page.evaluate(() => window.__resultsRetrySubmits), 1, 'retry reuses the canonical search lifecycle');
    assert.equal(await page.locator('#tourSearch').evaluate(node => node === document.activeElement), true, 'retry reset keeps focus on a stable recovery target');
    await page.evaluate(() => window.__releaseRetryStart());
    await page.waitForFunction(() => document.activeElement === document.getElementById('status'));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'retry moves focus to the visible loading status');
    assert.equal(await page.locator('#results .hotel-card').count(), 2, 'error state preserves already rendered cards');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-continue-error', { detail: {} })));
    assert.match(await page.locator('#status').innerText(), /Уже найденные отели сохранены/, 'continue failure truthfully preserves prior results');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })));
    assert.match(await page.locator('#status').innerText(), /Параметры поиска изменены/, 'dirty reset retains its actionable explanation');
    assert.equal(await localHotelInput.inputValue(), '', 'search reset clears the local hotel query');
    assert.equal(await localHotelFilter.isVisible(), false, 'search reset hides the stale local hotel filter');
    assert.equal(await localCategorySelect.inputValue(), '0', 'search reset clears the local category');
    assert.equal(await localCategoryFilter.isVisible(), false, 'search reset hides the stale local category facet');
    assert.equal(await localOperatorSelect.inputValue(), '', 'search reset clears the local operator');
    assert.equal(await localOperatorFilter.isVisible(), false, 'search reset hides the stale local operator facet');
    assert.equal(await calendar.isVisible(), false, 'search reset hides stale calendar data');
    assert.equal(await calendar.locator('[data-calendar-date]').count(), 0, 'search reset clears stale calendar dates');
    await page.evaluate(() => {
      window.__replaceResultsOnRecovery = true;
      document.getElementById('hotelServices').innerHTML = '<label><input type="checkbox" name="hotel_service[]" value="1" checked>Бассейн</label><label><input type="checkbox" name="hotel_service[]" value="2" checked>Пляж</label>';
      window.V2Catalogs.updateServiceCount();
      window.V2Results.render([]);
    });
    assert.equal(await page.locator('#serviceCount').innerText(), '2 выбрано', 'empty recovery starts from the actual selected-service count');
    await page.evaluate(() => {
      window.__relaxFocusTrace = [];
      document.addEventListener('focusin', event => window.__relaxFocusTrace.push({ at: Math.round(performance.now()), tag: event.target.tagName, id: event.target.id, classes: event.target.className }), { capture: true });
    });
    const serviceRelax = page.locator('.empty-relax[data-relax="hotel_service[]"]');
    await serviceRelax.focus();
    await serviceRelax.press('Enter');
    assert.equal(await page.evaluate(() => window.__resultsRetrySubmits), 2, 'keyboard service relaxation submits exactly once');
    assert.equal(await page.locator('input[name="hotel_service[]"]:checked').count(), 0, 'service relaxation clears every selected service');
    assert.equal(await page.locator('#serviceCount').textContent(), 'не выбраны', 'service relaxation synchronizes the count stored for the advanced-filter summary');
    await page.waitForTimeout(200);
    const serviceRelaxFocus = await page.evaluate(() => ({ active: document.activeElement && { tag: document.activeElement.tagName, id: document.activeElement.id, classes: document.activeElement.className }, formTabindex: document.getElementById('tourSearch').getAttribute('tabindex'), trace: window.__relaxFocusTrace }));
    assert.equal(serviceRelaxFocus.active && serviceRelaxFocus.active.id, 'tourSearch', 'service relaxation keeps focus on a stable recovery target through reset: '+JSON.stringify(serviceRelaxFocus));
    await page.evaluate(() => window.__releaseRetryStart());
    await page.waitForFunction(() => document.activeElement === document.getElementById('status'));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'service relaxation moves focus to the visible loading status');
    await page.evaluate(() => {
      const form = document.getElementById('tourSearch'), arrival = form.elements.arrival;
      arrival.innerHTML = '<option value="77" selected>Тестовый аэропорт</option>';
      arrival.addEventListener('change', () => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })), { once: true });
      document.getElementById('hotelServices').innerHTML = '<label><input type="checkbox" name="hotel_service[]" value="1" checked>Бассейн</label>';
      window.V2Catalogs.updateServiceCount();
      window.V2Results.render([]);
    });
    const arrivalRelax = page.locator('.empty-relax[data-relax="arrival"]');
    await arrivalRelax.focus();
    await arrivalRelax.press('Enter');
    assert.equal(await page.evaluate(() => window.__resultsRetrySubmits), 3, 'keyboard dependent relaxation submits exactly once');
    assert.equal(await page.locator('input[name="hotel_service[]"]:checked').count(), 0, 'dependent arrival relaxation clears incompatible hotel services');
    assert.equal(await page.locator('#serviceCount').textContent(), 'не выбраны', 'dependent relaxation also synchronizes the advanced-filter summary count');
    await page.waitForFunction(() => document.activeElement === document.getElementById('tourSearch'));
    assert.equal(await page.locator('#tourSearch').evaluate(node => node === document.activeElement), true, 'dependent relaxation keeps focus on a stable recovery target through reset');
    await page.evaluate(() => window.__releaseRetryStart());
    await page.waitForFunction(() => document.activeElement === document.getElementById('status'));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'dependent relaxation also moves focus to the visible loading status');
    await page.evaluate(() => window.V2Results.render([]));
    assert.equal(await page.locator('#status').isVisible(), false, 'actionable empty result owns the empty state without duplicate status copy');
    await page.locator('.empty-edit-search').click();
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'empty results return to native search form');
    if ([375, 1440].includes(width)) {
      await checkMealFacet(page, width, previous);
      await checkAndromedaExpansion(page, width, previous, andromeda);
    }
    assert.deepEqual(errors, [], 'no runtime errors');
    if (!previous) await page.screenshot({ path: path.join(output, `current-${width}.png`), fullPage: true });
    return { collapsed, expanded, logoSource };
  } finally { await page.close(); }
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [375, 760, 761, 999, 1000, 1024, 1025, 1440]) {
      const rawState = await run(browser, width, true), servedState = await run(browser, width, false);
      assert.deepEqual(servedState, rawState, width + ': served compact JS preserves actual result DOM and geometry');
      fs.writeFileSync(path.join(output, `current-${width}.json`), JSON.stringify(servedState, null, 2) + '\n');
    }
  } finally { await browser.close(); }
  console.log('SEARCH3_CURRENT_RESULTS_OK states=16 widths=375,760,761,999,1000,1024,1025,1440 raw_served_parity=1 native_header=1 external_calls=0 lead_sent=0');
})().catch(error => { console.error(error); process.exitCode = 1; });
