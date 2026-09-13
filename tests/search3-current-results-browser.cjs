/* Current reset UI: actual renderer, native header, and raw/served JS parity.
 * Retired custom disclosures, drawers and pixel dimensions are not fabricated. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.SEARCH3_VISUAL_BASE, output = process.env.SEARCH3_RESULTS_OUTPUT;
const sourceSha = process.env.SEARCH3_SOURCE_SHA;
assert.ok(base && new URL(base).hostname === '127.0.0.1' && output);
assert.match(sourceSha || '', /^[a-f0-9]{40}$/, 'current results evidence requires the exact source SHA');
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
async function checkPrimaryForm(page, state) {
  const form = page.locator('#tourSearch');
  assert.equal(await form.count(), 1, state + ': one canonical form owner');
  assert.equal(await form.isVisible(), true, state + ': primary form stays visible without an edit action');
  for (const name of ['from', 'country', 'dateFrom', 'dateTo', 'daysFrom', 'daysTill', 'count_people', 'child_count', 'region', 'hotel', 'stars', 'food', 'price_from', 'price_till']) {
    assert.equal(await form.locator(`[name="${name}"]`).isVisible(), true, state + ': primary control ' + name + ' remains visible');
  }
  assert.equal(await form.locator('[name=operator]').isVisible(), false, state + ': supplier operator remains secondary');
}
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
async function checkToolbarLayout(page, width, previous) {
  const tools = page.locator('#resultsTools'), edit = tools.locator('#resultsSearchEdit');
  const sort = tools.locator('#sortResults'), panel = tools.locator('.search3-mobile-filter-panel');
  const measure = () => tools.evaluate(node => {
    const origin = node.getBoundingClientRect();
    const box = element => { const r = element.getBoundingClientRect(); return { x: Math.round((r.x-origin.x)*100)/100, y: Math.round((r.y-origin.y)*100)/100, width: Math.round(r.width*100)/100, height: Math.round(r.height*100)/100 }; };
    const disclosure = node.querySelector('.search3-mobile-filter-panel:not([hidden])');
    return { width: origin.width, height: origin.height, actions: box(node.querySelector('.results-tools__actions')), edit: box(node.querySelector('#resultsSearchEdit')), sort: box(node.querySelector('#sortResults')), panel: disclosure && box(disclosure), summary: disclosure && box(disclosure.querySelector('summary')) };
  });
  await page.evaluate(() => document.fonts.ready);
  const closed = await measure();
  for (const control of [closed.edit, closed.sort, closed.summary].filter(Boolean)) {
    assert.ok(control.height >= 44 && control.width > 0, 'toolbar retains visible 44px controls');
    assert.ok(control.x >= 0 && control.x + control.width <= closed.width + 1, 'toolbar controls stay inside their container');
  }
  if (width > 600) {
    const controls = width >= 760 && width <= 1024 ? [closed.edit, closed.sort, closed.summary] : [closed.edit, closed.sort];
    const bottom = controls[0].y + controls[0].height;
    assert.ok(controls.every(control => Math.abs(control.y + control.height - bottom) < 3), 'toolbar actions align in one usable row');
    if (width >= 760 && width <= 1024) assert.ok(closed.actions.height <= 76, 'tablet toolbar has no empty edit row or separate filter/sort rows');
  } else {
    const positioning = await tools.evaluate(node => ({ position: getComputedStyle(node).position, top: getComputedStyle(node).top }));
    assert.deepEqual(positioning, { position: 'static', top: 'auto' }, 'mobile results toolbar stays in document flow instead of entering the physical safe area');
    assert.ok(closed.edit.width < closed.actions.width - 24, 'mobile edit remains a compact secondary action');
    assert.ok(closed.sort.y >= closed.edit.y + closed.edit.height && closed.summary.y >= closed.sort.y + closed.sort.height, 'mobile controls follow their readable visual order');
  }
  if (!previous && [375, 1024, 1025, 1440].includes(width)) await tools.screenshot({ path: path.join(output, `toolbar-${width}-closed.png`), animations: 'disabled' });
  await edit.focus(); await edit.press('Tab');
  assert.equal(await sort.evaluate(node => node === document.activeElement), true, 'keyboard moves from edit directly to the adjacent sort control');
  let opened = null;
  if (width <= 1024) {
    const summary = panel.locator('summary');
    await sort.press('Tab');
    assert.equal(await summary.evaluate(node => node === document.activeElement), true, 'filter disclosure follows sort in both DOM and visual order');
    await summary.press('Enter');
    assert.equal(await panel.evaluate(node => node.open), true);
    opened = await measure();
    assert.ok(Math.abs(opened.panel.width-opened.actions.width) < 2 && Math.abs(opened.panel.x-opened.actions.x) < 2, 'opened filters use the full toolbar width');
    assert.ok(opened.panel.y >= Math.max(opened.edit.y+opened.edit.height, opened.sort.y+opened.sort.height), 'opened filter body follows the toolbar controls');
    if (!previous && width === 1024) await tools.screenshot({ path: path.join(output, 'toolbar-1024-open.png'), animations: 'disabled' });
    await summary.press('Enter');
    assert.equal(await panel.evaluate(node => node.open), false, 'inspection restores the closed disclosure');
  }
  if (!previous && width === 375) {
    const toolbarDocumentBottom = await tools.evaluate(node => node.getBoundingClientRect().bottom + scrollY);
    await page.evaluate(y => scrollTo({ top: y, left: 0, behavior: 'instant' }), toolbarDocumentBottom + 20);
    await page.waitForFunction(() => scrollY > 0);
    assert.ok((await tools.boundingBox()).y < 0, 'mobile results toolbar scrolls away instead of sticking below the browser chrome');
    await page.screenshot({ path: path.join(output, 'toolbar-375-scrolled.png'), animations: 'disabled' });
    await page.evaluate(() => scrollTo({ top: 0, left: 0, behavior: 'instant' }));
    await page.waitForFunction(() => scrollY === 0);
  }
  return { closed, opened };
}
async function checkMinimumReadiness(page, width, previous) {
  const makeTour = (id, price, extra = {}) => ({ ...tour, id, price, ...extra });
  const items = [
    { ...hotels[0], id: 'minimum-check', name: 'Минимальная цена с проверкой', price: 80000, tours: [makeTour('minimum-andromeda', 80000, { provider: 'andromeda' }), makeTour('minimum-selectable', 95000)] },
    { ...hotels[0], id: 'minimum-mixed', name: 'Одна цена — разные условия выбора', price: 85000, tours: [makeTour('mixed-andromeda', 85000, { provider: 'andromeda' }), makeTour('mixed-selectable', 85000)] },
    { ...hotels[0], id: 'minimum-ready', name: 'Вариант с доступным выбором', price: 90000, tours: [makeTour('ready-minimum', 90000), makeTour('expensive-check', 110000, { selectionEnabled: false })] }
  ];
  const requests = [];
  const record = request => { if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)) requests.push(request.url()); };
  page.on('request', record);
  try {
    await page.evaluate(items => {
      window.Search3LocalHotelFilter.reset();
      window.__minimumReadinessSource = items;
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items } }));
    }, items);
    const cards = page.locator('#results .hotel-card');
    const checked = page.locator('.hotel-card[data-hotel-id="minimum-check"]');
    const mixed = page.locator('.hotel-card[data-hotel-id="minimum-mixed"]');
    const ready = page.locator('.hotel-card[data-hotel-id="minimum-ready"]');
    assert.equal(await checked.locator('.hotel-trip-summary .tour-selection-note').textContent(), 'Минимальная цена требует проверки перед выбором');
    assert.equal(await mixed.locator('.hotel-trip-summary .tour-selection-note').textContent(), 'Часть вариантов по минимальной цене требует проверки');
    assert.equal(await ready.locator('.hotel-trip-summary .tour-selection-note').count(), 0, 'a more expensive blocked offer does not label the minimum blocked');
    for (const card of [checked, mixed, ready]) {
      const geometry = await card.evaluate(node => {
        const r = node.getBoundingClientRect(), note = node.querySelector('.hotel-trip-summary .tour-selection-note'), n = note && note.getBoundingClientRect();
        return { inside: !n || n.width > 0 && n.left >= r.left && n.right <= r.right + 1, overflow: node.scrollWidth > node.clientWidth + 1 };
      });
      assert.equal(geometry.inside, true, 'minimum note remains readable within its card');
      assert.equal(geometry.overflow, false);
    }
    if (!previous) await (width === 375 ? mixed : checked).screenshot({ path: path.join(output, `minimum-readiness-${width}.png`), animations: 'disabled' });
    const labels = await cards.evaluateAll(nodes => nodes.map(node => ({ id: node.dataset.hotelId, price: node.querySelector('.hotel-summary-total .hotel-price').textContent, note: node.querySelector('.hotel-trip-summary .tour-selection-note')?.textContent || '' })));
    const toggle = checked.locator('.tour-more-toggle');
    await toggle.focus(); await toggle.press('Enter');
    assert.equal(await checked.locator('[data-tid="minimum-andromeda"]').count(), 0, 'unverified minimum still cannot create a select action');
    assert.equal(await checked.locator('[data-tid="minimum-selectable"]').isVisible(), true, 'more expensive selectable offer keeps its existing action');
    assert.match(await checked.locator('.tour-row').first().innerText(), /перед выбором нужна проверка/);
    await checked.locator('.tour-more-toggle').press('Enter');
    assert.equal(await checked.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'collapse retains the existing disclosure focus');
    assert.deepEqual(await page.evaluate(() => window.__minimumReadinessSource), items, 'presentation does not mutate supplier prices or readiness');
    assert.deepEqual(requests, [], 'local summary and disclosure cause no supplier or lead request');
    return labels;
  } finally { page.off('request', record); }
}

async function checkExpandedDensity(page, width, previous) {
  // The owner supplied a physical-iPhone capture with ten offers. Reproduce
  // that density in the current owner without calling a supplier or a lead.
  const item = { ...hotels[0], id: 'density-ten', price: 61372, tours: Array.from({ length: 10 }, (_, index) => ({ ...tour, id: 'density-' + index, price: 61372 + index * 1000, adults: 2, childs: 0, isCharter: true })) };
  const requests = [], record = request => { if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)) requests.push(request.url()); };
  page.on('request', record);
  const measurements = [];
  try {
    await page.evaluate(item => {
      window.Search3LocalHotelFilter.reset();
      window.__densityOriginal = item;
      window.V2Results.render([item]);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items: [item] } }));
    }, item);
    const card = page.locator('.hotel-card[data-hotel-id="density-ten"]');
    assert.equal(await card.locator('.hotel-choice-hint').count(), 0, 'hotel identity does not repeat the loaded count');
    assert.equal(await card.locator('.hotel-price').count(), 1, 'collapsed state contains one aggregate price');
    assert.equal(await card.locator('.tour-more-toggle').innerText(), 'Показать варианты · 10');
    await card.locator('.tour-more-toggle').focus();
    await card.locator('.tour-more-toggle').press('Enter');
    assert.equal(await card.locator('.hotel-trip-summary,.hotel-summary-total').count(), 0, 'expanded comparison replaces the aggregate facts and total');
    assert.equal(await card.locator('.hotel-offers-heading>strong').innerText(), '10 вариантов тура', 'one count belongs to the comparison header');
    assert.equal(await card.locator('.hotel-price').count(), 10, 'one exact price per offer, no duplicate minimum');
    assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), item.tours.map(value => value.id), 'all exact offer actions retain their original identity and order');
    assert.deepEqual(await card.locator('.tour-action>.hotel-price').allTextContents().then(values => values.map(value => Number(value.replace(/\D/g, '')))), item.tours.map(value => value.price), 'each displayed price remains the original supplier amount');
    for (const inspectedWidth of width === 375 ? [375, 390] : [width]) {
      if (inspectedWidth !== width) await page.setViewportSize({ width: inspectedWidth, height: page.viewportSize().height });
      await page.evaluate(() => document.fonts.ready);
      const geometry = await card.evaluate(node => {
        const origin = node.getBoundingClientRect(), rect = element => { const r = element.getBoundingClientRect(); return { x: Math.round(r.x-origin.x), y: Math.round(r.y-origin.y), width: Math.round(r.width), height: Math.round(r.height) }; };
        const heading = node.querySelector('.hotel-offers-heading'), collapse = heading.querySelector('button'), first = node.querySelector('.tour-row'), action = first.querySelector('.direct-tour');
        return { width: Math.round(origin.width), heading: rect(heading), collapse: rect(collapse), first: rect(first), action: rect(action), collapseColor: getComputedStyle(collapse).backgroundColor, actionColor: getComputedStyle(action).backgroundColor, pageOverflow: document.documentElement.scrollWidth > innerWidth + 1 };
      });
      assert.ok(geometry.heading.height <= 80, 'count and collapse fit one compact header instead of two stacked rows');
      assert.ok(geometry.collapse.height >= 44 && geometry.collapse.width < geometry.heading.width * 0.6, 'secondary collapse stays touch-sized without becoming a full-width CTA');
      assert.ok(geometry.collapse.x >= 0 && geometry.collapse.x + geometry.collapse.width <= geometry.width, 'collapse stays inside the card');
      assert.ok(Math.abs(geometry.first.y - geometry.heading.y - geometry.heading.height) <= 1, 'the first actual offer immediately follows its header');
      assert.notEqual(geometry.collapseColor, geometry.actionColor, 'collapse is visually secondary to choosing a tour');
      assert.equal(geometry.pageOverflow, false);
      if (inspectedWidth <= 390) assert.ok(geometry.action.y + geometry.action.height <= 760, 'first exact selection is reachable within the initial expanded card viewport');
      measurements.push({ viewportWidth: inspectedWidth, ...geometry });
      if (!previous) {
        await card.evaluate(node => scrollTo({ top: node.getBoundingClientRect().top + scrollY, behavior: 'instant' }));
        await page.screenshot({ path: path.join(output, `card-density-${inspectedWidth}.png`), animations: 'disabled' });
      }
    }
    await card.locator('.tour-more-toggle').focus();
    await card.locator('.tour-more-toggle').press('Space');
    assert.equal(await card.locator('.tour-row').count(), 0);
    assert.equal(await card.locator('.hotel-price').count(), 1);
    assert.equal(await card.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'replacement disclosure keeps keyboard focus');
    assert.deepEqual(await page.evaluate(() => window.__densityOriginal), item, 'density changes never mutate the input offers');
    assert.deepEqual(requests, [], 'local expansion and collapse make no supplier or lead request');
    return measurements;
  } finally {
    if (page.viewportSize().width !== width) await page.setViewportSize({ width, height: page.viewportSize().height });
    page.off('request', record);
  }
}

async function checkMealFacet(page, width, previous) {
  const sample = (id, price, meal, date) => ({ ...tour, id, price, meal, date });
  const items = [
    { id: 'meal-a', name: 'Отель А', price: 90000, rating: 5, category: 5, tours: [sample('a-ro', 90000, { name: 'RO', fullName: 'Без питания' }, '2026-09-10'), sample('a-bb', 140000, { fullName: 'Только завтрак' }, '2026-09-15'), sample('a-hb', 145000, { fullName: 'Полупансион' }, '2026-09-16'), sample('a-fb', 150000, { fullName: 'Full Board' }, '2026-09-17'), sample('a-sc', 155000, { fullName: 'Self Catering' }, '2026-09-18'), sample('a-request', 160000, { fullName: 'По запросу' }, '2026-09-19'), sample('a-ai-extra', 125000, { fullName: 'Всё включено' }, '2026-09-14'), sample('a-ai', 120000, { name: 'AI', fullName: 'Всё включено' }, '2026-09-12'), sample('a-uai', 135000, { name: 'UAI', fullName: 'Ультра всё включено' }, '2026-09-14'), sample('a-soft-ai', 138000, { name: 'Soft AI', fullName: 'Мягкое всё включено' }, '2026-09-14'), { ...sample('a-andromeda-ai', 130000, { name: 'AI' }, '2026-09-14'), provider: 'andromeda', selectionEnabled: false }] },
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
      ['meal:half-board', 'Полупансион'], ['meal:self-catering', 'Самообслуживание'],
      ['meal:ultra-all-inclusive', 'Ультра всё включено'], ['meal:soft-all-inclusive', 'Soft AI']
    ], 'supplier synonyms collapse while AI, UAI and Soft AI remain separate customer-facing choices');
    assert.equal(await mealPreset.isVisible(), true, 'a truthful existing all-inclusive option exposes one quick choice');
    assert.ok((await mealPreset.boundingBox()).height >= 44, 'meal quick choice keeps a full touch target');
    assert.deepEqual(await visible(), ['meal-c', 'meal-a', 'meal-b']);
    await select.selectOption('meal:ultra-all-inclusive');
    assert.deepEqual(await visible(), ['meal-a'], 'UAI remains independently selectable instead of entering the AI bucket');
    assert.equal(await page.locator('#results [data-hotel-id=meal-a] .direct-tour').getAttribute('data-tid'), 'a-uai');
    await select.selectOption('meal:soft-all-inclusive');
    assert.deepEqual(await visible(), ['meal-a'], 'Soft AI remains independently selectable instead of entering the AI bucket');
    assert.equal(await page.locator('#results [data-hotel-id=meal-a] .direct-tour').getAttribute('data-tid'), 'a-soft-ai');
    await select.selectOption('');
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
    assert.equal(await a.locator('.hotel-trip-summary').count(), 1, 'multiple matching offers have one aggregate summary');
    assert.equal(await a.locator('.direct-tour').count(), 0, 'a collapsed aggregate does not select an undisclosed offer');
    assert.equal(await a.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), 'от120000₽', 'selected meal sets the actual matching minimum, explicitly labelled from');
    assert.match(await a.locator('.hotel-trip-summary').innerText(), /Несколько дат вылета/, 'different matching departures are not presented as one representative date');
    assert.equal(await a.locator('.tour-more-toggle').innerText(), 'Показать варианты · 3', 'counts only matching AI aliases across providers');
    assert.ok((await a.locator('.tour-more-toggle').boundingBox()).height >= 44, 'matching-offer disclosure keeps a full touch target');
    await a.locator('.tour-more-toggle').focus();
    await a.locator('.tour-more-toggle').press('Enter');
    assert.equal(await a.locator('.hotel-offers-heading>strong').innerText(), '3 варианта тура', 'expanded count also describes only matching AI aliases');
    assert.equal(await a.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'meal disclosure keeps keyboard focus after replacing its contents');
    assert.equal(await a.locator('.direct-tour').first().getAttribute('data-tid'), 'a-ai', 'expanded representative choice keeps its original tour ID');
    assert.deepEqual(await a.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['a-ai', 'a-ai-extra'], 'expansion keeps AI aliases without reintroducing UAI or Soft AI');
    assert.equal(await a.locator('.tour-selection-note').count(), 1, 'equivalent Andromeda AI remains visible but cannot enter the Tourvisor selection controller');
    assert.doesNotMatch(await a.locator('.hotel-tours').innerText(), /Без питания|90000/);
    assert.equal(await a.locator('.hotel-price').first().innerText().then(text => text.replace(/\s/g, '')), '120000₽', 'expanded meal offers start with the same matching price');
    await a.locator('.tour-more-toggle').press('Space');
    assert.equal(await a.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'meal collapse keeps focus on the replacement disclosure');
    assert.equal(await a.locator('.direct-tour').count(), 0, 'collapse restores an aggregate rather than an implicit offer selection');
    assert.equal(await a.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), 'от120000₽', 'collapse retains the selected meal minimum');
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
    await a.locator('.tour-more-toggle').click();
    assert.equal(await a.locator('[data-tid=a-ro]').count(), 1, 'clear restores original tours, including earlier excluded meals, on explicit expansion');
    await a.locator('.tour-more-toggle').click();
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
    const boundaryItems = [
      { id: 'meal-boundary-a', name: 'Границы питания А', price: 101000, tours: [sample('boundary-hb-plus', 101000, 'HB+', '2026-09-20'), sample('boundary-premium', 102000, 'Premium All Inclusive', '2026-09-21')] },
      { id: 'meal-boundary-b', name: 'Границы питания Б', price: 103000, tours: [sample('boundary-breakfast-dinner', 103000, 'Breakfast and dinner', '2026-09-22'), sample('boundary-not-ai', 104000, 'Not all inclusive', '2026-09-23')] }
    ];
    await page.evaluate(items => { window.V2Results.render(items); window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 102, items } })); }, boundaryItems);
    assert.deepEqual(Object.fromEntries(await select.locator('option').evaluateAll(nodes => nodes.map(node => [node.value, node.textContent]))), {
      '': 'Любое питание',
      'meal:half-board': 'Полупансион',
      'meal:label:premium all inclusive': 'Premium All Inclusive',
      'meal:label:breakfast and dinner': 'Breakfast and dinner',
      'meal:label:not all inclusive': 'Not all inclusive'
    }, 'the actual result facet keeps one reviewed code family and three ambiguous supplier labels distinct');
    await select.selectOption('meal:label:not all inclusive');
    assert.deepEqual(await visible(), ['meal-boundary-b']);
    assert.equal(await page.locator('[data-hotel-id=meal-boundary-b] .direct-tour').getAttribute('data-tid'), 'boundary-not-ai', 'a negated label cannot enter the ordinary all-inclusive result bucket');
    await select.selectOption('meal:label:breakfast and dinner');
    assert.equal(await page.locator('[data-hotel-id=meal-boundary-b] .direct-tour').getAttribute('data-tid'), 'boundary-breakfast-dinner', 'an extended label cannot enter the reviewed breakfast result bucket');
    await page.evaluate(items => { window.V2Results.render(items); window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 103, items } })); }, items);
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
      assert.equal(await page.locator('.results-filter-rail').isVisible(), true, 'cross-provider offers expose the useful provider/source facet in the canonical desktop rail');
      assert.ok((await card.boundingBox()).width >= 700, 'desktop single-hotel results remain readable beside the truthful provider/source facet');
    }
    assert.equal(await card.locator('.tour-row').count(), 0, 'a grouped hotel summary is not a concrete provider offer');
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
  const catalog = { recover: false, requests: [] };
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
    if (catalog.recover && url.pathname.endsWith('/data/departures-v1.php')) {
      catalog.requests.push('departures');
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, items: [{ id: 1, russianName: 'Москва' }] }) });
    }
    if (catalog.recover && /\/(?:api[^/]*)\.php$/.test(url.pathname) && url.searchParams.get('action') === 'countries') {
      catalog.requests.push('countries');
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify([{ id: 4, russianName: 'Турция' }]) });
    }
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
    await page.locator('[name=dateFrom]').fill('2026-09-21');
    await page.locator('[name=count_people]').selectOption('3');
    catalog.recover = true;
    const catalogRetry = page.locator('.catalog-retry');
    await catalogRetry.focus();
    await catalogRetry.press('Enter');
    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'anytour-departures' && !document.querySelector('.catalog-recovery'));
    await page.waitForFunction(() => document.activeElement === document.querySelector('[name=from]'));
    assert.deepEqual(catalog.requests, ['departures', 'countries'], 'catalog retry uses only the existing departures and countries requests');
    assert.equal(await page.locator('[name=dateFrom]').inputValue(), '2026-09-21', 'catalog retry preserves the chosen departure date');
    assert.equal(await page.locator('[name=count_people]').inputValue(), '3', 'catalog retry preserves the party size');
    assert.equal(await page.locator('[name=from]').evaluate(node => node === document.activeElement), true, 'successful keyboard retry returns focus to the populated departure control');
    catalog.recover = false;
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
    const primaryParameters = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]);
    await checkPrimaryForm(page, 'first results');
    const toolbarLayout = await checkToolbarLayout(page, width, previous);
    if (width === 760) {
      await page.setViewportSize({ width: 759, height: 1000 });
      toolbarLayout.belowTablet = await checkToolbarLayout(page, 759, previous);
      await page.setViewportSize({ width, height: 1000 });
    }
    if (!previous && [375, 1024, 1440].includes(width)) {
      await snapshot(page);
      await page.screenshot({ path: path.join(output, `primary-with-results-${width}.png`), fullPage: true });
      if (width === 1440) {
        await page.setViewportSize({ width, height: 700 });
        await checkPrimaryForm(page, 'short desktop results');
        await snapshot(page);
        await page.screenshot({ path: path.join(output, 'primary-with-results-1440-short.png') });
        await page.setViewportSize({ width, height: 1000 });
      }
    }
    await page.evaluate(items => {
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items } }));
    }, calendarHotels);
    const calendar = page.locator('#currentPriceCalendar');
    await checkPrimaryForm(page, 'terminal results with calendar');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]), primaryParameters, 'render and completion preserve every primary/advanced value');
    assert.equal(await calendar.isVisible(), true, 'current price calendar is visible after a terminal result set');
    const calendarDisclosure = calendar.locator('details');
    assert.equal(await calendarDisclosure.evaluate(node => node.open), true, 'Search3 calendar starts expanded on first render at every responsive width');
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
    if (!(await calendarDisclosure.evaluate(node => node.open))) await calendar.locator('summary').click();
    await calendar.locator('[data-calendar-date="2026-09-14"]').click();
    assert.equal(await page.evaluate(() => window.__calendarSubmits), 1, 'calendar date submits through the canonical lifecycle exactly once');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [form.elements.dateFrom.value, form.elements.dateTo.value]), ['2026-09-14', '2026-09-14'], 'calendar applies the exact selected day');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()].filter(([name]) => !['dateFrom', 'dateTo'].includes(name))), preservedBeforeCalendar, 'calendar preserves every non-date search parameter');
    const parameters = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]);
    assert.equal(await page.locator('#resultsTools #resultsSearchEdit').count(), 1, 'native results tools retain one search edit action');
    await page.locator('#resultsSearchEdit').focus();
    await page.locator('#resultsSearchEdit').press('Enter');
    assert.equal(await page.locator('[name=from]').evaluate(node => node === document.activeElement), true, 'keyboard edit action focuses the permanently available primary form');
    await checkPrimaryForm(page, 'keyboard edit');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]), parameters, 'editing preserves all current search parameters');
    await page.evaluate(items => window.V2Results.render(items), hotels);
    await checkPrimaryForm(page, 'results rerender after edit');
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
    await localOperatorSelect.selectOption('name:test operator');
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
    await checkPrimaryForm(page, 'zero local matches');
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
    await localOperatorSelect.selectOption('name:test operator');
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
    assert.equal(await card.locator('.tour-row').count(), 0, 'collapsed hotel contains no concrete offer rows');
    assert.equal(await card.locator('.hotel-trip-summary').count(), 1, 'collapsed card uses the canonical aggregate summary');
    assert.equal(await card.locator('.direct-tour').count(), 0, 'an aggregate has no misleading implicit offer selection');
    assert.ok((await card.locator('.tour-more-toggle').boundingBox()).height >= 44, 'real disclosure action retains a full touch target');
    assert.equal(await card.locator('.tour-more-toggle').innerText(), 'Показать варианты · 3', 'disclosure states the total loaded offer count');
    assert.equal(await card.locator('.tour-meta').count(), 0, 'hotel summary never uses the concrete offer owner');
    assert.deepEqual(await card.locator('.tour-facts .tour-fact').evaluateAll(nodes => nodes.map(node => [node.querySelector('small').textContent, node.querySelector('b').textContent])), [['Вылет', '12.09.2026'], ['Длительность', '9 ноч.'], ['Питание', 'Всё включено'], ['Перелёт', 'Уточняется по варианту']], 'aggregate facts use the same canonical meal identity as the result facet');
    assert.deepEqual(await card.locator('.hotel-operators .hotel-operator-name').allTextContents(), ['OTHER OPERATOR', 'TEST OPERATOR'], 'summary contains actual operator names without guessing a brand');
    assert.equal(await card.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), 'от148500,6₽', 'aggregate minimum is distinct from an exact offer price');
    const single = page.locator('#results [data-hotel-id=cheap].hotel-card');
    assert.equal(await single.locator('.hotel-trip-summary,.tour-more-toggle').count(), 0, 'single-offer hotel needs no redundant aggregate or disclosure');
    assert.equal(await single.locator('.direct-tour').getAttribute('data-tid'), 'cheap-tour', 'single-offer hotel retains immediate exact selection');
    assert.ok((await single.locator('.direct-tour').boundingBox()).height >= 44, 'single-offer selection retains a full touch target');
    assert.equal(await single.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), '90000₽', 'single offer exposes its exact price without a minimum prefix');
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
    const collapsed = await snapshot(page);
    if (collapsed.overflow) console.error(JSON.stringify({width,previous,offenders:collapsed.offenders}));
    assert.equal(collapsed.overflow, false, width + ': results fit viewport');
    if (!previous && [375, 1440].includes(width)) await page.screenshot({ path: path.join(output, `results-collapsed-${width}.png`), fullPage: true });
    await card.locator('.tour-more-toggle').focus();
    await card.locator('.tour-more-toggle').press('Enter');
    assert.equal(await card.locator('.tour-row').count(), 3, 'actual toggle reveals all tours');
    assert.equal(await card.locator('.hotel-trip-summary').count(), 0, 'expanded exact offers replace the aggregate without duplication');
    assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['current-tour', 'other-tour', 'third-tour'], 'expansion pins the nonfirst representative and preserves the order of all remaining offers');
    const primary = card.locator('.tour-row').first();
    assert.ok((await primary.locator('.direct-tour').boundingBox()).height >= 44, 'expanded real selection action retains a full touch target');
    assert.equal(await primary.locator('.direct-tour').getAttribute('data-tid'), tour.id, 'selection identity retained');
    assert.equal(await primary.locator('.direct-tour').innerText(), 'Выбрать тур', 'selection action identifies its target');
    assert.match(await primary.locator('.tour-facts').innerText(), /Всё включено/, 'supplier fullName expands the abbreviation in offer facts');
    assert.equal(await primary.locator('.tour-meta>small').innerText(), 'Дата вылета · 9 ноч.', 'departure context states the duration beside the date');
    assert.equal(await primary.locator('.tour-meta>strong').innerText(), '12.09.2026', 'compact facts format the actual departure date for display');
    assert.deepEqual(await primary.locator('.tour-facts .tour-fact').evaluateAll(nodes => nodes.map(node => [node.querySelector('small').textContent, node.querySelector('b').textContent])), [['Питание', 'Всё включено'], ['Номер', 'STANDARD LAND VIEW']], 'primary comparison facts keep their labels and original values');
    assert.deepEqual(await primary.locator('.tour-secondary-facts .tour-fact').evaluateAll(nodes => nodes.map(node => [node.querySelector('small').textContent, node.querySelector('b').textContent])), [['Источник', 'Tourvisor'], ['Оператор', 'TEST OPERATOR'], ['Размещение', 'DBL']], 'source and operator remain distinct while secondary facts keep unambiguous labels');
    assert.equal(await primary.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), '148500,6₽', 'expanded representative keeps the precise original price');
    assert.equal(await card.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'keyboard expansion retains focus on the replacement disclosure');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'true');
    const expanded = await snapshot(page);
    assert.equal(expanded.overflow, false, width + ': expanded results fit viewport');
    if (!previous && [375, 1440].includes(width)) await page.screenshot({ path: path.join(output, `results-expanded-${width}.png`), fullPage: true });
    assert.equal(await card.locator('.tour-meta>strong').first().evaluate(node => getComputedStyle(node, '::before').content), 'none', 'result dates have no duplicate generated label');
    assert.match(await card.innerText(), /148[\s\u00a0]*500,6/, 'decimal price remains visible');
    await card.locator('.tour-more-toggle').press('Space');
    assert.equal(await card.locator('.tour-row').count(), 0, 'actual toggle collapses back to hotel summary, not an offer');
    assert.equal(await card.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'keyboard collapse retains focus on the replacement disclosure');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'false');
    assert.equal(await card.locator('.direct-tour').count(), 0, 'collapse restores the aggregate without an implicit representative action');
    assert.equal(await card.locator('.hotel-trip-summary').count(), 1, 'collapse restores exactly one aggregate');
    assert.equal(await page.evaluate(() => window.V2Results.state.items.every((hotel, i) => hotel === window.__decisionOriginal[i]) && window.V2Results.representativeTour(window.__decisionOriginal[0]) === window.__decisionOriginal[0].tours[1]), true, 'render and disclosure preserve original hotel and representative tour objects');
    assert.equal(await page.evaluate(() => JSON.stringify(window.V2Results.state.items)), JSON.stringify(hotels), 'disclosure leaves frozen source prices, tour order and contents unchanged');
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await page.locator('#results .hotel-card').first().getAttribute('data-hotel-id'), 'expensive', 'rating sorting retained');
    await page.evaluate(items => window.V2Results.render([{ ...items[0], id: 'no-photo', name: 'Отель без фотографии', picturelink: '' }]), hotels);
    const noPhoto = page.locator('#results [data-hotel-id=no-photo].hotel-card');
    const noPhotoGeometry = await noPhoto.evaluate(node => { const box = element => { const r = element.getBoundingClientRect(); return { x:r.x, y:r.y, width:r.width, height:r.height }; }; return { main:box(node.querySelector('.hotel-main')), media:box(node.querySelector('.hotel-photo')), body:box(node.querySelector('.hotel-body')), placeholder:getComputedStyle(node.querySelector('.photo-placeholder')).display, stars:getComputedStyle(node.querySelector('.stars-badge')).display }; });
    assert.equal(noPhotoGeometry.placeholder, 'none', 'missing photo never reserves a branded fake image');
    assert.notEqual(noPhotoGeometry.stars, 'none', 'hotel category stays visible when photo is absent');
    assert.ok(noPhotoGeometry.media.height < 64, 'missing-photo header stays compact instead of reserving media height: '+JSON.stringify({width,previous,noPhotoGeometry}));
    assert.ok(noPhotoGeometry.body.width >= noPhotoGeometry.main.width - 2, 'missing-photo facts use the card content width');
    assert.equal((await snapshot(page)).overflow, false, width + ': missing-photo card fits the viewport');
    if (!previous && [375,1024,1440].includes(width)) await page.screenshot({ path: path.join(output, `results-no-photo-${width}.png`), fullPage: true });
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 44 } })));
    await checkPrimaryForm(page, 'loading with retained results');
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
    await checkPrimaryForm(page, 'search error');
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
    assert.equal(await page.locator('#serviceCount').textContent(), '2 выбрано', 'empty recovery starts from the actual selected-service count');
    const serviceRelax = page.locator('.empty-relax[data-relax="hotel_service[]"]');
    await serviceRelax.focus();
    await serviceRelax.press('Enter');
    assert.equal(await page.evaluate(() => window.__resultsRetrySubmits), 2, 'keyboard service relaxation submits exactly once');
    assert.equal(await page.locator('input[name="hotel_service[]"]:checked').count(), 0, 'service relaxation clears every selected service');
    assert.equal(await page.locator('#serviceCount').textContent(), 'не выбраны', 'service relaxation synchronizes the count stored for the advanced-filter summary');
    await page.waitForFunction(() => document.activeElement === document.getElementById('tourSearch'));
    assert.equal(await page.locator('#tourSearch').evaluate(node => node === document.activeElement), true, 'service relaxation keeps focus on a stable recovery target through reset');
    await page.evaluate(() => window.__releaseRetryStart());
    await page.waitForFunction(() => document.activeElement === document.getElementById('status'));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'service relaxation moves focus to the visible loading status');
    await page.evaluate(() => {
      const form = document.getElementById('tourSearch'), arrival = form.elements.arrival;
      form.elements.country.value = '';
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
    await page.evaluate(() => {
      window.V2Results.render([]);
      window.V2Results.showPlainStatus('Поиск завершён · предложения актуальны на сейчас');
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items: [] } }));
    });
    await page.waitForFunction(() => document.activeElement && document.activeElement.closest && document.activeElement.closest('.empty-actionable'));
    assert.equal(await page.locator('#status').isVisible(), false, 'actionable empty result owns the empty state without duplicate status copy');
    assert.equal(await page.locator('.empty-actionable').evaluate(node => node.contains(document.activeElement)), true, 'terminal empty recovery moves status focus to an available action');
    await page.evaluate(() => {
      const stable = document.createElement('button');
      stable.id = 'ordinary-terminal-focus';
      stable.type = 'button';
      stable.textContent = 'Проверочный независимый элемент';
      document.body.append(stable);
      stable.focus({ preventScroll: true });
      window.V2Results.render([]);
      window.V2Results.showPlainStatus('Поиск завершён · предложения актуальны на сейчас');
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items: [] } }));
    });
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    assert.equal(await page.locator('#ordinary-terminal-focus').evaluate(node => node === document.activeElement), true, 'ordinary terminal empty result does not steal unrelated user focus');
    await page.locator('#ordinary-terminal-focus').evaluate(node => node.remove());
    assert.equal(await page.locator('#status').isVisible(), false, 'ordinary terminal empty result also keeps a single final state');
    await checkPrimaryForm(page, 'terminal empty results');
    await page.locator('.empty-edit-search').click();
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'empty results return to native search form');
    assert.equal(await page.locator('[name=from]').evaluate(node => node === document.activeElement), true, 'empty edit action focuses the existing departure control');
    let minimumReadiness = null, expandedDensity = null;
    if ([375, 1440].includes(width)) {
      await checkMealFacet(page, width, previous);
      minimumReadiness = await checkMinimumReadiness(page, width, previous);
      expandedDensity = await checkExpandedDensity(page, width, previous);
      await checkAndromedaExpansion(page, width, previous, andromeda);
      await require('./search3-hotel-operator-card-browser.cjs')(page, width, output);
    }
    assert.deepEqual(errors, [], 'no runtime errors');
    if (!previous) await page.screenshot({ path: path.join(output, `current-${width}.png`), fullPage: true });
    return { sourceSha, primaryForm: 'visible-through-results-calendar-loading-local-empty-error', toolbarLayout, minimumReadiness, expandedDensity, collapsed, expanded, logoSource };
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
