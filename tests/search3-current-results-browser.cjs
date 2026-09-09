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
const tour = { id: 'current-tour', price: 148500.6, date: '2026-09-12', nights: 9, meal: { fullName: 'Всё включено' }, roomType: 'STANDARD LAND VIEW', placement: 'DBL', operator: { name: 'TEST OPERATOR' } };
const hotels = [
  { id: 'expensive', name: 'Проверочный отель с длинным названием', country: { name: 'Турция' }, region: { name: 'Анталья' }, price: tour.price, rating: 5, category: 5, picturelink: picture, tours: [tour, { ...tour, id: 'other-tour', price: 159000 }] },
  { id: 'cheap', name: 'Второй отель', price: 90000, rating: 4, category: 4, picturelink: picture, tours: [{ ...tour, id: 'cheap-tour', price: 90000 }] }
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
    await page.evaluate(items => window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items } })), calendarHotels);
    const calendar = page.locator('#currentPriceCalendar');
    assert.equal(await calendar.isVisible(), true, 'current price calendar is visible after a terminal result set');
    assert.deepEqual(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.map(node => [node.dataset.calendarDate, node.querySelector('strong').textContent.replace(/\s/g, '')])), [
      ['2026-09-10', '99000₽'], ['2026-09-12', '148500₽']
    ], 'calendar exposes per-day minima and ignores unpriced tours');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-10', 'lowest observed day is highlighted');
    assert.equal(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.every(node => node.getBoundingClientRect().height >= 44)), true, 'calendar dates retain accessible touch targets');
    await page.evaluate(items => window.dispatchEvent(new CustomEvent('v2:search-continued', { detail: { items } })), calendarHotels.concat([
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
    assert.equal(await localHotelFilter.isVisible(), true, 'one local hotel filter appears for multiple loaded hotels');
    assert.equal(await localCategoryFilter.isVisible(), true, 'category facet appears when every loaded hotel has a category');
    await localCategorySelect.selectOption('5');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 1, 'category facet filters only the already loaded hotels');
    assert.match(await localHotelFilter.locator('small').innerText(), /Показано 1 из 2 загруженных отелей/, 'category facet reports a truthful loaded-card count');
    await localHotelInput.fill('  ВТОРОЙ  ');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 0, 'hotel name and rating filters combine locally');
    assert.match(await localHotelFilter.locator('small').innerText(), /Показано 0 из 2 загруженных отелей/, 'combined filters report their truthful loaded-card count');
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await localHotelInput.inputValue(), '  ВТОРОЙ  ', 'sorting preserves the local hotel query');
    assert.equal(await localCategorySelect.inputValue(), '5', 'sorting preserves the local category');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 0, 'sorting reapplies both local filters to rerendered cards');
    await localHotelInput.fill('');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 1, 'clearing the name keeps the active category');
    await localCategorySelect.selectOption('0');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'clearing the local query restores every loaded card');
    await page.evaluate(items => window.V2Results.render(items), [hotels[0], { ...hotels[1], category: 0 }]);
    assert.equal(await localCategoryFilter.isVisible(), false, 'category facet hides when any loaded hotel lacks category data');
    assert.equal(await localCategorySelect.inputValue(), '0', 'incomplete category data resets the local choice');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'an incomplete facet never silently removes a loaded hotel');
    await page.evaluate(items => window.V2Results.render(items), hotels);
    await page.locator('#sortResults').selectOption('price');
    const card = page.locator('#results [data-hotel-id=expensive].hotel-card');
    assert.equal(await card.locator('.hotel-title').evaluate(node => node.tagName), 'H3', 'hotel name keeps a semantic card heading');
    assert.equal(await card.locator('.hotel-best-offer').count(), 0, 'card does not repeat the representative tour price');
    assert.equal(await card.locator('.hotel-price').count(), 1, 'collapsed card exposes one authoritative total');
    assert.match(await card.locator('.hotel-decision-rating').innerText(), /Рейтинг 5/, 'hotel score is not confused with star category');
    assert.match(await page.locator('#resultSummary').innerText(), /цены указаны за весь тур/, 'result summary explains price scope');
    assert.equal(await card.locator('.tour-row').count(), 1, 'representative tour shown immediately');
    assert.ok((await card.locator('.direct-tour').boundingBox()).height >= 44, 'real selection action retains a full touch target');
    assert.match(await card.locator('.tour-facts').innerText(), /Всё включено/, 'supplier fullName-only meal is visible in the offer facts');
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
    await card.locator('.tour-more-toggle').click();
    assert.equal(await card.locator('.tour-row').count(), 2, 'actual toggle reveals all tours');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'true');
    const expanded = await snapshot(page);
    assert.equal(expanded.overflow, false, width + ': expanded results fit viewport');
    if (!previous && [375, 1440].includes(width)) await page.screenshot({ path: path.join(output, `results-expanded-${width}.png`), fullPage: true });
    assert.equal(await card.locator('.tour-meta>strong').first().evaluate(node => getComputedStyle(node, '::before').content), 'none', 'result dates have no duplicate generated label');
    assert.match(await card.innerText(), /148[\s\u00a0]*500,6/, 'decimal price remains visible');
    await card.locator('.tour-more-toggle').click();
    assert.equal(await card.locator('.tour-row').count(), 1, 'actual toggle collapses');
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
    await page.evaluate(() => {
      window.__resultsRetrySubmits = 0;
      window.V2SearchLifecycle.submit = () => { window.__resultsRetrySubmits += 1; };
      window.dispatchEvent(new CustomEvent('v2:search-error', { detail: { phase: 'status' } }));
    });
    assert.equal(await page.locator('#status .results-state--error').isVisible(), true, 'search failure exposes a distinct error state');
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
