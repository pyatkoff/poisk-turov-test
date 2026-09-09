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
  { id: 'expensive', name: 'Проверочный отель с длинным названием', country: { name: 'Турция' }, region: { name: 'Анталья' }, price: tour.price, rating: 5, category: 5, seaDistance: 100, picturelink: picture, tours: [{ ...tour, id: 'other-tour', price: 159000 }, tour, { ...tour, id: 'third-tour', price: 155000 }] },
  { id: 'cheap', name: 'Второй отель', price: 90000, rating: 4, category: 4, seaDistance: 800, picturelink: picture, tours: [{ ...tour, id: 'cheap-tour', price: 90000 }] }
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
    { id: 'meal-a', name: 'Отель А', price: 90000, rating: 5, category: 5, tours: [sample('a-ro', 90000, { name: 'RO', fullName: 'Без питания' }, '2026-09-10'), sample('a-ai-extra', 125000, { fullName: 'Всё включено' }, '2026-09-14'), sample('a-ai', 120000, { name: 'AI', fullName: 'Всё включено' }, '2026-09-12')] },
    { id: 'meal-b', name: 'Отель Б', price: 100000, rating: 4, category: 4, tours: [sample('b-ai', 100000, { fullName: 'Всё включено' }, '2026-09-11')] },
    { id: 'meal-c', name: 'Отель В', price: 80000, rating: 3, category: 3, tours: [sample('c-ro', 80000, { fullName: 'Без питания' }, '2026-09-13')] }
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
    const name = page.locator('.search3-hotel-filter input'), category = page.locator('.search3-category-filter select');
    const visible = () => page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId));
    assert.equal(await field.isVisible(), true, 'complete loaded meals expose the local facet');
    assert.deepEqual(await visible(), ['meal-c', 'meal-a', 'meal-b']);
    const calendar = page.locator('#currentPriceCalendar');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-13', 'calendar starts from the lowest offer in the terminal result set');
    const eventCount = await page.evaluate(() => window.__mealEvents.length);
    await select.selectOption('всё включено');
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
    assert.match(await a.locator('.hotel-choice-hint').innerText(), /2 варианта/, 'counts only matching offers');
    await a.locator('.tour-more-toggle').focus();
    await a.locator('.tour-more-toggle').press('Enter');
    assert.equal(await a.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'meal disclosure keeps keyboard focus after replacing its contents');
    assert.deepEqual(await a.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['a-ai', 'a-ai-extra'], 'expansion cannot reintroduce an excluded meal');
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
    assert.equal(await select.inputValue(), 'всё включено');
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
    assert.equal(await select.inputValue(), 'всё включено', 'dirty edit preserves the retained result projection');
    assert.deepEqual(await visible(), ['meal-b', 'meal-a'], 'dirty event does not reveal excluded stale offers');
    await page.evaluate(() => window.V2Results.rerender());
    assert.equal(await field.isVisible(), true);
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-11', 'returning to completed results restores the meal-filtered calendar');
    assert.deepEqual(await visible(), ['meal-b', 'meal-a'], 'returning to the retained results preserves meal selection');
    await select.selectOption('');
    assert.deepEqual(await visible(), ['meal-c', 'meal-a', 'meal-b'], 'clear restores every loaded hotel and original ordering');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-13', 'clearing all local filters restores the full calendar minimum');
    assert.equal(await a.locator('[data-tid=a-ro]').count(), 1, 'clear restores original tours, including earlier excluded meals');
    await select.selectOption('всё включено');
    await page.evaluate(items => window.V2Results.render(items.concat([{ id: 'meal-incomplete', name: 'Неполные данные', price: 70000, tours: [{ id: 'unknown', price: 70000, meal: { id: 7 } }] }])), items);
    assert.equal(await field.isVisible(), false, 'incomplete progressive set hides the facet');
    assert.equal(await select.inputValue(), '', 'incomplete set resets selection before rendering prices');
    assert.equal((await visible()).length, 4, 'no silent filtering remains on incomplete data');
    await page.evaluate(items => window.V2Results.render(items), items);
    await select.selectOption('всё включено');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 101 } })));
    assert.equal(await select.inputValue(), '', 'a real new search clears the local meal');
    assert.equal(await field.isVisible(), false);
    await page.evaluate(items => window.V2Results.render(items), items);
    assert.deepEqual(await visible(), ['meal-c', 'meal-a', 'meal-b'], 'new search starts without inherited local selection');
    assert.equal(await calendar.isVisible(), false, 'new search cannot show a calendar before its terminal event');
    await select.selectOption('всё включено');
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
async function run(browser, width, previous) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } }), errors = [];
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
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
    const localBudgetFilter = page.locator('.search3-budget-filter');
    const localBudgetInput = localBudgetFilter.locator('input');
    const localRatingFilter = page.locator('.search3-rating-filter');
    const localRatingSelect = localRatingFilter.locator('select');
    const localSeaFilter = page.locator('.search3-sea-filter');
    const localSeaSelect = localSeaFilter.locator('select');
    const localReset = page.locator('.search3-filter-reset');
    const mobilePanel = page.locator('.search3-mobile-filter-panel');
    if (width < 1025) {
      assert.equal(await mobilePanel.isVisible(), true, 'tablet and mobile expose one compact current filter disclosure');
      assert.equal(await mobilePanel.getAttribute('open'), null, 'mobile disclosure starts compact');
      const mobileSummary = mobilePanel.locator('summary');
      await mobileSummary.focus();
      await page.keyboard.press('Enter');
      assert.notEqual(await mobilePanel.getAttribute('open'), null, 'native summary opens current filters from the keyboard');
    } else assert.equal(await mobilePanel.isVisible(), false, 'desktop does not expose the mobile disclosure');
    assert.equal(await localHotelFilter.isVisible(), true, 'one local hotel filter appears for multiple loaded hotels');
    assert.equal(await localBudgetFilter.isVisible(), true, 'complete loaded offer prices expose a budget facet');
    assert.equal(await localCategoryFilter.isVisible(), true, 'category facet appears when every loaded hotel has a category');
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
    await localBudgetInput.evaluate(node => { node.value = '100000'; node.dispatchEvent(new Event('input', { bubbles: true })); });
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['cheap'], 'budget filters only offers within the selected loaded total');
    assert.equal(await page.locator('#results [data-hotel-id=cheap] .hotel-price').innerText().then(text => text.replace(/\s/g, '')), '90000₽', 'budget retains the exact qualifying offer price');
    await localBudgetInput.evaluate(node => { node.value = node.max; node.dispatchEvent(new Event('input', { bubbles: true })); });
    await localRatingSelect.selectOption('4.5');
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'rating threshold filters the loaded hotels locally');
    await localRatingSelect.selectOption('0');
    await localSeaSelect.selectOption('200');
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'sea threshold filters only complete loaded distance data');
    await localSeaSelect.selectOption('0');
    await localCategorySelect.selectOption('5');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 1, 'category facet filters only the already loaded hotels');
    assert.equal(await localReset.isVisible(), true, 'an active local facet exposes one reset action at every responsive width');
    assert.equal(await localReset.evaluate(node => node.parentElement.className), width >= 1025 ? 'results-filter-rail' : 'search3-mobile-filter-panel__body', 'reset action follows the current responsive filter owner');
    if (width < 1025) assert.match(await mobilePanel.locator('summary').innerText(), /Подходит: 1 · выбрано: 1/, 'compact summary exposes the current result and active-filter counts');
    assert.match(await localHotelFilter.locator('small').innerText(), /Показано 1 из 2 загруженных отелей/, 'category facet reports a truthful loaded-card count');
    await localHotelInput.fill('  ВТОРОЙ  ');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 0, 'hotel name and category filters combine locally');
    assert.match(await localHotelFilter.locator('small').innerText(), /Показано 0 из 2 загруженных отелей/, 'combined filters report their truthful loaded-card count');
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await localHotelInput.inputValue(), '  ВТОРОЙ  ', 'sorting preserves the local hotel query');
    assert.equal(await localCategorySelect.inputValue(), '5', 'sorting preserves the local category');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 0, 'sorting reapplies both local filters to rerendered cards');
    await localHotelInput.fill('');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 1, 'clearing the name keeps the active category');
    await localReset.click();
    assert.equal(await localCategorySelect.inputValue(), '0', 'one reset clears the active category');
    assert.equal(await localHotelInput.inputValue(), '', 'one reset clears the hotel query');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'one reset restores every loaded card');
    assert.equal(await localReset.isVisible(), false, 'reset action hides when no local filter remains active');
    await page.evaluate(items => window.V2Results.render(items), [hotels[0], { ...hotels[1], category: 0 }]);
    assert.equal(await localCategoryFilter.isVisible(), false, 'category facet hides when any loaded hotel lacks category data');
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
    assert.deepEqual(await card.locator('.tour-secondary-facts .tour-fact').evaluateAll(nodes => nodes.map(node => [node.querySelector('small').textContent, node.querySelector('b').textContent])), [['Размещение', 'DBL'], ['Оператор', 'TEST OPERATOR']], 'secondary facts remain available with unambiguous labels');
    const photo = await card.locator('.hotel-photo').boundingBox();
    const body = await card.locator('.hotel-body').boundingBox();
    assert.ok(photo.height >= 150, 'hotel photo remains legible at the current width');
    if (width <= 760) assert.ok(body.y >= photo.y + photo.height - 1, 'mobile hotel content follows the photo without overlap');
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
      window.V2SearchLifecycle.submit = () => { window.__resultsRetrySubmits += 1; };
      window.dispatchEvent(new CustomEvent('v2:search-error', { detail: { phase: 'status' } }));
    });
    assert.equal(await page.locator('#status .results-state--error').isVisible(), true, 'search failure exposes a distinct error state');
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'search failure leaves parameters editable even with retained results');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]), errorParameters, 'error recovery preserves every search parameter');
    if (!previous && [375, 1440].includes(width)) await page.screenshot({ path: path.join(output, `search-error-edit-${width}.png`), fullPage: true });
    await page.locator('#status .results-state-retry').click();
    assert.equal(await page.evaluate(() => window.__resultsRetrySubmits), 1, 'retry reuses the canonical search lifecycle');
    assert.equal(await page.locator('#results .hotel-card').count(), 2, 'error state preserves already rendered cards');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-continue-error', { detail: {} })));
    assert.match(await page.locator('#status').innerText(), /Уже найденные отели сохранены/, 'continue failure truthfully preserves prior results');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })));
    assert.match(await page.locator('#status').innerText(), /Параметры поиска изменены/, 'dirty reset retains its actionable explanation');
    assert.equal(await localHotelInput.inputValue(), '', 'search reset clears the local hotel query');
    assert.equal(await localHotelFilter.isVisible(), false, 'search reset hides the stale local hotel filter');
    assert.equal(await localCategorySelect.inputValue(), '0', 'search reset clears the local category');
    assert.equal(await localCategoryFilter.isVisible(), false, 'search reset hides the stale local category facet');
    assert.equal(await calendar.isVisible(), false, 'search reset hides stale calendar data');
    assert.equal(await calendar.locator('[data-calendar-date]').count(), 0, 'search reset clears stale calendar dates');
    await page.evaluate(() => window.V2Results.render([]));
    assert.equal(await page.locator('#status').isVisible(), false, 'actionable empty result owns the empty state without duplicate status copy');
    await page.locator('.empty-edit-search').click();
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'empty results return to native search form');
    if ([375, 1440].includes(width)) await checkMealFacet(page, width, previous);
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
