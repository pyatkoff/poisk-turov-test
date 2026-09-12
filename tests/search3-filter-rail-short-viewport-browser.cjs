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
    await page.goto(`${base}/poisk-turov/`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.V2Results && typeof window.V2Results.render === 'function');
    await page.evaluate(items => {
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 901, items } }));
    }, hotels);
    await page.locator('.results-filter-rail').waitFor({ state: 'visible' });

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
        viewportHeight: innerHeight
      };
    });
    assert.equal(initial.overflowY, 'auto', `${width}: rail owns vertical overflow`);
    assert.ok(initial.clientHeight <= height - 34, `${width}: rail is bounded inside the short viewport: ${JSON.stringify(initial)}`);
    assert.ok(initial.scrollHeight > initial.clientHeight, `${width}: fixture actually exercises an overflowing rail: ${JSON.stringify(initial)}`);
    assert.ok(initial.rectTop >= 16 && initial.rectBottom <= height + 1, `${width}: sticky rail stays inside the viewport: ${JSON.stringify(initial)}`);
    assert.equal(initial.overflowX, false, `${width}: page has no horizontal overflow before keyboard traversal`);

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

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    await exercise(browser, 1025, 520);
    await exercise(browser, 1440, 560);
  } finally {
    await browser.close();
  }
  console.log('SEARCH3_FILTER_RAIL_SHORT_VIEWPORT_OK widths=1025x520,1440x560 keyboard=1 rail_scroll=1 page_scroll=0 horizontal_overflow=0 supplier_calls_on_reset=0');
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
