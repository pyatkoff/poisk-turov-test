const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const base = process.env.SEARCH3_VISUAL_BASE;
const output = process.env.SEARCH3_FILTER_RAIL_OUTPUT;
assert.ok(base && new URL(base).hostname === '127.0.0.1' && output);
fs.mkdirSync(output, { recursive: true });

const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360"><rect width="640" height="360" fill="#dce8f4"/></svg>');
const meal = { name: 'AI', fullName: 'Всё включено' };
const makeTour = (id, price, operator, date) => ({
  id, price, date, nights: 8, meal, roomType: 'STANDARD', placement: 'DBL', operator: { name: operator }
});
const hotels = [
  {
    id: 'rail-a', name: 'Проверочный отель Альфа', country: { name: 'Турция' }, region: { name: 'Анталья' },
    price: 118000, rating: 4.8, category: 5, seaDistance: 120, picturelink: picture,
    tours: [makeTour('rail-a-1', 118000, 'ANEX TOUR', '2026-10-10'), makeTour('rail-a-2', 125000, 'FUN&SUN', '2026-10-11')]
  },
  {
    id: 'rail-b', name: 'Проверочный отель Бета', country: { name: 'Турция' }, region: { name: 'Кемер' },
    price: 96000, rating: 4.3, category: 4, seaDistance: 650, picturelink: picture,
    tours: [makeTour('rail-b-1', 96000, 'BIBLIO GLOBUS', '2026-10-12')]
  }
];

async function renderFixture(page) {
  await page.goto(`${base}/poisk-turov/`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => window.V2Results && typeof window.V2Results.render === 'function');
  await page.evaluate(items => {
    window.V2Results.render(items);
    window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 901, items } }));
  }, hotels);
}

async function exerciseActiveChips(page, container, requestCount) {
  const hotelInput = page.locator(`${container} .search3-hotel-filter input`);
  await hotelInput.fill('Альфа');
  const hotelChip = page.locator(`${container} .search3-active-filters button[data-filter-key="hotel"]`);
  await hotelChip.waitFor({ state: 'visible' });
  assert.match(await hotelChip.getAttribute('aria-label'), /^Убрать фильтр: Отель: Альфа$/);
  assert.equal(await page.locator('#results .hotel-card:visible').count(), 1, 'hotel chip reflects the local hotel predicate');
  let before = requestCount();
  await hotelChip.click();
  assert.equal(requestCount(), before, 'removing hotel chip never launches supplier/lead transport');
  assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'removing one chip restores only that predicate');
  await page.waitForFunction(() => document.activeElement?.matches('.search3-hotel-filter input'));

  const budget = page.locator(`${container} .search3-budget-filter input`);
  await budget.waitFor({ state: 'visible' });
  const bounds = await budget.evaluate(node => ({ min: Number(node.min), max: Number(node.max) }));
  const budgetValue = Math.max(bounds.min, bounds.max - 5000);
  await budget.fill(String(budgetValue));
  await budget.dispatchEvent('input');
  const budgetChip = page.locator(`${container} .search3-active-filters button[data-filter-key="budget"]`);
  await budgetChip.waitFor({ state: 'visible' });
  assert.ok((await budgetChip.boundingBox()).height >= 43.5, 'active filter chip keeps an accessible hit target');

  const operator = page.locator(`${container} .search3-operator-filter select`);
  await operator.waitFor({ state: 'visible' });
  const operatorValue = await operator.locator('option').nth(1).getAttribute('value');
  await operator.selectOption(operatorValue);
  const operatorChip = page.locator(`${container} .search3-active-filters button[data-filter-key="operator"]`);
  await operatorChip.waitFor({ state: 'visible' });
  assert.equal(await budgetChip.count(), 1, 'budget remains active when operator is added');
  before = requestCount();
  await operatorChip.click();
  assert.equal(requestCount(), before, 'removing projected operator chip stays local');
  assert.equal(await budgetChip.count(), 1, 'removing operator preserves budget');
  assert.equal(await operator.inputValue(), '', 'operator alone is cleared');
  await page.waitForFunction(() => document.activeElement?.matches('.search3-operator-filter select'));

  before = requestCount();
  await budgetChip.click();
  assert.equal(requestCount(), before, 'removing budget chip stays local');
  assert.equal(await page.locator(`${container} .search3-active-filters button`).count(), 0, 'all individual chips are gone after their own predicates are cleared');
  await page.waitForFunction(() => document.activeElement?.matches('.search3-budget-filter input'));
}

async function exercise(browser, width, height) {
  const page = await browser.newPage({ viewport: { width, height } });
  const runtimeErrors = [];
  page.on('pageerror', error => runtimeErrors.push(String(error)));
  let supplierLikeRequests = 0;
  page.on('request', request => {
    const pathname = new URL(request.url()).pathname;
    if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(pathname)) supplierLikeRequests += 1;
  });
  try {
    await renderFixture(page);
    await page.locator('.results-filter-rail').waitFor({ state: 'visible' });

    await exerciseActiveChips(page, '.results-filter-rail', () => supplierLikeRequests);

    const hotelInput = page.locator('.results-filter-rail .search3-hotel-filter input');
    await hotelInput.fill('Проверочный');
    const reset = page.locator('.results-filter-rail .search3-filter-reset');
    await reset.waitFor({ state: 'visible' });

    await page.evaluate(() => {
      const layout = document.querySelector('.results-layout');
      const top = layout.getBoundingClientRect().top + scrollY - 18;
      scrollTo({ top: Math.max(0, top), left: 0, behavior: 'instant' });
      const rail = document.querySelector('.results-filter-rail');
      rail.scrollTop = 0;
    });
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));

    const initial = await page.evaluate(() => {
      const rail = document.querySelector('.results-filter-rail');
      const style = getComputedStyle(rail);
      const rect = rail.getBoundingClientRect();
      return {
        windowY: scrollY,
        railScrollTop: rail.scrollTop,
        clientHeight: rail.clientHeight,
        scrollHeight: rail.scrollHeight,
        rectTop: rect.top,
        rectBottom: rect.bottom,
        overflowY: style.overflowY,
        overflowX: document.documentElement.scrollWidth > innerWidth + 2,
        viewportHeight: innerHeight,
        shellWidth: document.querySelector('.v2-shell').getBoundingClientRect().width,
        layoutWidth: document.querySelector('.results-layout').getBoundingClientRect().width,
        railWidth: rect.width,
        resultsWidth: document.querySelector('#results').getBoundingClientRect().width,
        workspaceGap: document.querySelector('#results').getBoundingClientRect().left - rect.right,
        activeFiltersParent: document.querySelector('.search3-active-filters')?.parentElement?.className || '',
        card: (() => {
          const card = document.querySelector('#results .hotel-card');
          const main = card.querySelector('.hotel-main');
          const photo = card.querySelector('.hotel-photo');
          const body = card.querySelector('.hotel-body');
          const row = card.querySelector('.tour-row');
          const meta = row.querySelector('.tour-meta');
          const action = row.querySelector('.tour-action');
          const cta = action.querySelector('.direct-tour');
          const box = node => node.getBoundingClientRect();
          return {
            width: box(card).width,
            mainWidth: box(main).width,
            photoWidth: box(photo).width,
            bodyWidth: box(body).width,
            rowWidth: box(row).width,
            metaWidth: box(meta).width,
            actionWidth: box(action).width,
            ctaHeight: box(cta).height
          };
        })()
      };
    });
    assert.match(initial.activeFiltersParent, /results-filter-rail/, `${width}: desktop active conditions live in the canonical rail`);
    assert.equal(initial.overflowY, 'auto', `${width}: rail owns vertical overflow`);
    assert.ok(initial.clientHeight <= height - 34, `${width}: rail is bounded inside the short viewport: ${JSON.stringify(initial)}`);
    assert.ok(initial.scrollHeight > initial.clientHeight, `${width}: fixture actually exercises an overflowing rail: ${JSON.stringify(initial)}`);
    assert.ok(initial.rectTop >= 16 && initial.rectBottom <= height + 1, `${width}: sticky rail stays inside the viewport: ${JSON.stringify(initial)}`);
    assert.equal(initial.overflowX, false, `${width}: page has no horizontal overflow before keyboard traversal`);
    if (width >= 1200) {
      assert.ok(initial.shellWidth >= Math.min(width - 8, 1350), `${width}: wide desktop uses the available workspace: ${JSON.stringify(initial)}`);
      assert.ok(initial.railWidth >= 245 && initial.railWidth <= 255, `${width}: OTA filter rail stays intentionally scannable: ${JSON.stringify(initial)}`);
      assert.ok(initial.workspaceGap >= 20 && initial.workspaceGap <= 28, `${width}: rail/results gap stays balanced: ${JSON.stringify(initial)}`);
      assert.ok(initial.resultsWidth >= initial.railWidth * 3.4, `${width}: results remain the dominant decision surface: ${JSON.stringify(initial)}`);
      const card = initial.card;
      assert.ok(card.photoWidth / card.mainWidth >= 0.24 && card.photoWidth / card.mainWidth <= 0.29, `${width}: hotel media is supportive rather than dominant on wide desktop: ${JSON.stringify(card)}`);
      assert.ok(card.bodyWidth >= card.photoWidth * 2.35, `${width}: hotel copy owns the wide desktop summary surface: ${JSON.stringify(card)}`);
      assert.ok(card.actionWidth >= 228 && card.actionWidth <= 252, `${width}: price/CTA decision column stays bounded: ${JSON.stringify(card)}`);
      assert.ok(card.metaWidth >= card.actionWidth * 2, `${width}: offer facts remain the dominant row surface: ${JSON.stringify(card)}`);
      assert.ok(card.ctaHeight >= 44, `${width}: primary tour action keeps its accessible target: ${JSON.stringify(card)}`);
    }

    const focusables = page.locator('.results-filter-rail :is(input,select,button):visible:not([disabled])');
    assert.ok(await focusables.count() >= 5, `${width}: rail exposes a real keyboard path`);
    await focusables.first().focus();
    let reachedReset = false;
    for (let step = 0; step < 24; step += 1) {
      if (await reset.evaluate(node => node === document.activeElement)) {
        reachedReset = true;
        break;
      }
      await page.keyboard.press('Tab');
    }
    if (!reachedReset) reachedReset = await reset.evaluate(node => node === document.activeElement);
    assert.equal(reachedReset, true, `${width}: Tab traversal reaches the lower reset action`);

    const after = await page.evaluate(() => {
      const rail = document.querySelector('.results-filter-rail');
      const active = document.activeElement;
      const activeRect = active.getBoundingClientRect();
      return {
        windowY: scrollY,
        railScrollTop: rail.scrollTop,
        overflowX: document.documentElement.scrollWidth > innerWidth + 2,
        activeTop: activeRect.top,
        activeBottom: activeRect.bottom,
        activeClass: active.className
      };
    });
    assert.ok(after.railScrollTop > 0, `${width}: keyboard traversal scrolls the rail itself`);
    assert.ok(Math.abs(after.windowY - initial.windowY) <= 2, `${width}: keyboard traversal does not scroll the page/background: ${JSON.stringify({ initial, after })}`);
    assert.equal(after.overflowX, false, `${width}: rail scrolling never creates horizontal page overflow`);
    assert.ok(after.activeTop >= 14 && after.activeBottom <= height - 4, `${width}: focused reset stays visible in the short viewport: ${JSON.stringify(after)}`);
    assert.deepEqual(runtimeErrors, [], `${width}: no runtime errors`);

    await page.screenshot({ path: path.join(output, `filter-rail-${width}x${height}.png`), fullPage: false });
    fs.writeFileSync(path.join(output, `filter-rail-${width}x${height}.json`), JSON.stringify({ initial, after }, null, 2) + '\n');

    const beforeResetRequests = supplierLikeRequests;
    await reset.press('Enter');
    assert.equal(supplierLikeRequests, beforeResetRequests, `${width}: local reset does not launch supplier or lead transport`);
  } finally {
    await page.close();
  }
}

async function exerciseMobile(browser) {
  const page = await browser.newPage({ viewport: { width: 375, height: 812 } });
  let supplierLikeRequests = 0;
  const runtimeErrors = [];
  page.on('pageerror', error => runtimeErrors.push(String(error)));
  page.on('request', request => {
    const pathname = new URL(request.url()).pathname;
    if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(pathname)) supplierLikeRequests += 1;
  });
  try {
    await renderFixture(page);
    const panel = page.locator('.search3-mobile-filter-panel');
    await panel.waitFor({ state: 'visible' });
    await panel.locator('summary').click();
    await exerciseActiveChips(page, '.search3-mobile-filter-panel__body', () => supplierLikeRequests);
    await page.locator('.search3-mobile-filter-panel__body .search3-hotel-filter input').fill('Альфа');
    const mobileChip = page.locator('.search3-mobile-filter-panel__body .search3-active-filters button[data-filter-key="hotel"]');
    await mobileChip.waitFor({ state: 'visible' });
    const mobile = await page.evaluate(() => ({
      chipParent: document.querySelector('.search3-active-filters')?.parentElement?.className || '',
      overflowX: document.documentElement.scrollWidth > innerWidth + 2,
      summary: document.querySelector('[data-search3-mobile-filter-summary]')?.textContent || '',
      chipHeight: document.querySelector('.search3-active-filters button')?.getBoundingClientRect().height || 0
    }));
    assert.match(mobile.chipParent, /search3-mobile-filter-panel__body/, 'mobile active conditions move into the canonical disclosure');
    assert.equal(mobile.overflowX, false, 'mobile active filter chips do not widen the page');
    assert.match(mobile.summary, /Отель: Альфа/, 'mobile summary remains consistent with visible active condition');
    assert.ok(mobile.chipHeight >= 43.5, 'mobile chip remains touch accessible');
    await page.screenshot({ path: path.join(output, 'filter-chips-375x812.png'), fullPage: false });
    assert.deepEqual(runtimeErrors, [], 'mobile active chips have no runtime errors');
  } finally {
    await page.close();
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    await exerciseMobile(browser);
    await exercise(browser, 1025, 520);
    await exercise(browser, 1200, 700);
    await exercise(browser, 1366, 768);
    await exercise(browser, 1440, 560);
    await exercise(browser, 1600, 700);
  } finally {
    await browser.close();
  }
  console.log('SEARCH3_FILTER_RAIL_SHORT_VIEWPORT_OK mobile=375x812 widths=1025x520,1200x700,1366x768,1440x560,1600x700 active_chips=1 individual_remove=1 keyboard=1 rail_scroll=1 page_scroll=0 horizontal_overflow=0 supplier_calls_on_filter_remove=0 supplier_calls_on_reset=0');
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});