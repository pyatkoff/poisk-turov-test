/* Search3 known-hotel lookup on the real served form and scoped bundle. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const base = process.env.SEARCH3_VISUAL_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'requires the isolated local artifact server');
const output = process.env.SEARCH3_KNOWN_HOTEL_OUTPUT;
assert.ok(output, 'requires retained evidence output');
fs.mkdirSync(output, { recursive: true });

const hotels = [
  { id: 41001, name: 'RIXOS PREMIUM BELEK', country: { id: 4, name: 'Турция' }, region: { id: 401, name: 'Белек' }, subRegion: { id: 4011, name: 'Илерибаши' }, category: 5, rating: 4.8 },
  { id: 41002, name: 'RIXOS SUNGATE', country: { id: 4, name: 'Турция' }, region: { id: 402, name: 'Кемер' }, subRegion: { id: 4021, name: 'Бельдиби' }, category: 5, rating: 4.7 }
];
const viewports = [
  { width: 375, height: 812 },
  { width: 390, height: 500 },
  { width: 430, height: 932 },
  { width: 1440, height: 980 }
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const evidence = [];
  try {
    for (const viewport of viewports) {
      const page = await browser.newPage({ viewport, hasTouch: viewport.width < 768 });
      const searchStarts = [], hotelQueries = [], errors = [];
      let pauseLookup = null, nextLookupResponse = null;
      page.on('pageerror', error => errors.push(String(error)));
      await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.pathname === '/data/hotel-search-v1.php') {
          hotelQueries.push(Object.fromEntries(url.searchParams));
          const reply = nextLookupResponse;
          nextLookupResponse = null;
          const pause = pauseLookup;
          if (pause) { pauseLookup = null; pause.started(); await pause.wait; }
          const items = url.searchParams.get('q') === 'NoSuchHotel' ? [] : url.searchParams.get('q') === 'Marriott' ? [{ ...hotels[0], id: 42001, name: 'MARRIOTT HOTEL' }] : hotels;
          try {
            if (reply?.abort) await route.abort();
            else await route.fulfill(reply || { status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, source: 'anytour-catalog', items }) });
          } finally { if (pause) pause.finished(); }
          return;
        }
        if (url.pathname === '/data/departures-v1.php') {
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, items: [{ id: 1, name: 'Москва' }] }) });
        }
        if (/\/api-v2\.php$/.test(url.pathname)) {
          const action = url.searchParams.get('action');
          if (action === 'search_start') searchStarts.push(request.url());
          const payload = action === 'countries' ? [{ id: 4, name: 'Турция' }]
            : action === 'regions' ? [{ id: 401, name: 'Белек' }, { id: 402, name: 'Кемер' }]
            : action === 'meals' ? [{ id: 7, name: 'Всё включено' }]
            : [];
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payload) });
        }
        if (url.origin !== new URL(base).origin || request.method() !== 'GET') return route.abort();
        return route.continue();
      });

      const response = await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
      assert.equal(response.status(), 200);
      const form = page.locator('#tourSearch');
      const tripContext = () => form.evaluate(node => ({
        country: node.elements.country.value,
        region: node.elements.region.value,
        dateFrom: node.elements.dateFrom.value,
        dateTo: node.elements.dateTo.value,
        daysFrom: node.elements.daysFrom.value,
        daysTill: node.elements.daysTill.value,
        adults: node.elements.count_people.value,
        children: node.elements.child_count.value
      }));
      await page.waitForFunction(() => document.forms.tourSearch?.dataset.search3Ready === '1');
      await page.waitForFunction(() => document.forms.tourSearch?.dataset.catalogSource === 'anytour-departures');
      const input = form.locator('[data-v2-hotel-query]');
      const select = form.locator('select[name="hotel"]');
      assert.equal(await input.count(), 1, 'Search3 exposes one known-hotel query');
      assert.equal(await input.isVisible(), true, 'known-hotel query is visible');
      assert.equal(await select.isVisible(), false, 'exact hotel ID remains in one hidden native owner');
      assert.equal(await input.getAttribute('placeholder'), 'Например Rixos Premium Belek');

      await input.fill('Rixos');
      const list = form.locator('#hotelAutocompleteList');
      const waitForOptions = () => list.locator('[role="option"]').first().waitFor({ state: 'visible', timeout: 3000 });
      await waitForOptions();
      assert.equal(await list.locator('[role="option"]').count(), 2, 'country-wide lookup keeps hotels from different resorts');
      assert.match(await list.innerText(), /RIXOS PREMIUM BELEK[\s\S]*Белек[\s\S]*Илерибаши[\s\S]*5★/);
      assert.match(await list.innerText(), /RIXOS SUNGATE[\s\S]*Кемер[\s\S]*Бельдиби[\s\S]*5★/);
      assert.equal(hotelQueries.length, 1);
      assert.deepEqual(hotelQueries[0], { q: 'Rixos', limit: '10', countryId: '4' }, 'lookup is country-scoped without requiring a resort');
      assert.equal(await input.getAttribute('aria-activedescendant'), null, 'query starts without a misleading active option');
      assert.deepEqual(await list.locator('[aria-selected="true"]').allTextContents(), []);
      assert.deepEqual(await list.locator('[role="option"]').evaluateAll(nodes => nodes.map(node => node.tabIndex)), [-1, -1], 'arrow navigation owns options without adding hidden tab stops');

      const geometry = await page.evaluate(() => {
        const input = document.querySelector('[data-v2-hotel-query]');
        const options = [...document.querySelectorAll('.hotel-autocomplete__option')];
        const rect = node => { const r = node.getBoundingClientRect(), s = getComputedStyle(node); return { x: r.x, y: r.y, width: r.width, height: r.height, fontSize: parseFloat(s.fontSize) }; };
        return { input: rect(input), options: options.map(rect), viewportHeight: innerHeight, overflow: document.documentElement.scrollWidth > innerWidth + 1 };
      });
      assert.ok(geometry.input.height >= 44 && geometry.input.fontSize >= 16, 'known-hotel query remains touch and zoom safe');
      assert.ok(geometry.options.every(item => item.height >= 44), 'every hotel result is a full touch target');
      assert.ok(geometry.input.y >= 0 && geometry.input.y + geometry.input.height <= geometry.viewportHeight + 1, 'focused hotel query stays visible while suggestions are open');
      assert.ok(geometry.options.every(item => item.y >= 0 && item.y + item.height <= geometry.viewportHeight + 1), 'suggestions stay inside the short viewport');
      assert.equal(geometry.overflow, false, 'lookup has no horizontal overflow');

      await input.press('ArrowDown');
      assert.equal(await input.getAttribute('aria-activedescendant'), 'hotelAutocompleteOption0');
      assert.equal(await list.locator('[role="option"]').nth(0).getAttribute('aria-selected'), 'true');
      assert.equal(await list.locator('[role="option"]').nth(0).evaluate(node => getComputedStyle(node).backgroundColor), 'rgb(244, 247, 249)', 'keyboard-active option is visibly highlighted');
      await input.press('ArrowDown');
      assert.equal(await input.getAttribute('aria-activedescendant'), 'hotelAutocompleteOption1');
      assert.equal(await list.locator('[role="option"]').nth(1).getAttribute('aria-selected'), 'true');
      await input.press('ArrowDown');
      assert.equal(await input.getAttribute('aria-activedescendant'), 'hotelAutocompleteOption0', 'ArrowDown wraps to the first option');
      await input.press('ArrowUp');
      assert.equal(await input.getAttribute('aria-activedescendant'), 'hotelAutocompleteOption1', 'ArrowUp wraps to the last option');
      await input.press('Enter');
      assert.equal(await select.inputValue(), '41002', 'keyboard selection stores the active canonical hotel ID');
      assert.equal(await input.inputValue(), 'RIXOS SUNGATE');
      assert.equal(await list.isVisible(), false);
      assert.equal(await input.getAttribute('aria-activedescendant'), null);
      assert.deepEqual(searchStarts, [], 'typing/selecting a hotel does not start a supplier search');
      const selectedQueries = hotelQueries.length;
      await form.locator('input[name="price_from"]').focus();
      await input.click();
      await page.waitForTimeout(250);
      assert.equal(hotelQueries.length, selectedQueries, 'returning to a selected hotel does not repeat its lookup');
      assert.equal(await select.inputValue(), '41002', 'returning preserves the selected canonical identity');
      assert.equal(await list.isVisible(), false);

      await form.locator('select[name="region"]').selectOption('402');
      assert.equal(await select.inputValue(), '', 'changing geography clears an incompatible exact hotel');
      assert.equal(await input.inputValue(), '');
      assert.deepEqual(searchStarts, [], 'geographic clarification still does not submit a tour search');

      await input.fill('Rixos');
      await waitForOptions();
      await input.press('ArrowDown');
      await input.press('Escape');
      assert.equal(await list.isVisible(), false, 'Escape closes suggestions');
      assert.equal(await input.inputValue(), 'Rixos', 'Escape keeps the visitor query');
      assert.equal(await select.inputValue(), '');
      assert.equal(await input.getAttribute('aria-activedescendant'), null);
      const tripBeforeReopening = await tripContext();
      async function checkReopen(action, label) {
        const before = hotelQueries.length;
        await action();
        await waitForOptions();
        await page.waitForTimeout(220);
        assert.equal(hotelQueries.length, before + 1, label + ' performs exactly one current catalog lookup');
        assert.deepEqual(hotelQueries.at(-1), { q: 'Rixos', limit: '10', countryId: '4', regionId: '402' }, label + ' uses the current query and geography');
        assert.equal(await input.inputValue(), 'Rixos', label + ' does not require editing the name');
        assert.equal(await select.inputValue(), '', label + ' waits for explicit hotel selection');
        assert.equal(await input.getAttribute('aria-activedescendant'), null);
        assert.deepEqual(await tripContext(), tripBeforeReopening, label + ' preserves the trip');
        assert.deepEqual(searchStarts, [], label + ' cannot start an unrestricted supplier search');
        await input.press('Escape');
      }
      await checkReopen(() => input.press('ArrowDown'), 'ArrowDown after Escape');
      await checkReopen(() => input.press('ArrowUp'), 'ArrowUp after Escape');
      await checkReopen(async () => {
        await form.locator('input[name="price_from"]').focus();
        await input.focus();
      }, 'Keyboard return from another field');
      await checkReopen(async () => {
        await form.locator('input[name="price_from"]').focus();
        if (viewport.width < 768) await input.tap(); else await input.click();
      }, 'Pointer return coalesces focus and click');
      await checkReopen(() => viewport.width < 768 ? input.tap() : input.click(), 'Click on an already focused dismissed query');
      await checkReopen(() => input.press('Enter'), 'Enter after Escape reopens instead of submitting');
      await input.press('ArrowUp');
      await waitForOptions();
      await input.press('ArrowUp');
      assert.equal(await input.getAttribute('aria-activedescendant'), 'hotelAutocompleteOption1', 'first ArrowUp from no active option chooses the last suggestion');
      assert.equal(await select.inputValue(), '', 'arrow navigation alone cannot select a canonical hotel');
      assert.deepEqual(searchStarts, []);
      await input.press('Escape');

      const beforeShortQuery = hotelQueries.length;
      await input.fill('R');
      await input.press('Enter');
      await list.getByText('Введите хотя бы 2 символа', { exact: true }).waitFor({ state: 'visible' });
      await page.waitForTimeout(220);
      assert.equal(hotelQueries.length, beforeShortQuery, 'one-character input does not request a catalog lookup');
      assert.deepEqual(searchStarts, [], 'one-character Enter does not silently submit a broad tour search');
      assert.equal(await input.inputValue(), 'R');
      assert.equal(await list.locator('[role="option"],button').count(), 0, 'short query has guidance, not a misleading hotel or submit action');
      assert.equal(await list.evaluate(node => node.scrollWidth > node.clientWidth + 1), false);
      await page.screenshot({ path: path.join(output, `known-hotel-short-${viewport.width}x${viewport.height}.png`), animations: 'disabled' });
      await input.press('Escape');

      await input.fill('Rixos');
      await waitForOptions();
      await form.locator('input[name="price_from"]').focus();
      const beforeReturnEdit = hotelQueries.length;
      await input.fill('Marriott');
      await waitForOptions();
      await page.waitForTimeout(220);
      assert.equal(hotelQueries.length, beforeReturnEdit + 1, 'return followed by editing coalesces into one lookup');
      assert.equal(hotelQueries.at(-1).q, 'Marriott', 'return cannot send the previous query before an immediate edit');
      assert.match(await list.innerText(), /MARRIOTT HOTEL/);
      assert.deepEqual(searchStarts, []);
      await input.fill('Rixos');
      await waitForOptions();
      await input.press('ArrowDown');
      await page.screenshot({ path: path.join(output, `known-hotel-${viewport.width}x${viewport.height}.png`), animations: 'disabled' });
      if (viewport.width < 768) await list.locator('[role="option"]').first().tap();
      else await list.locator('[role="option"]').first().click();
      assert.equal(await select.inputValue(), '41001', 'pointer selection keeps the exact canonical hotel ID');

      await input.fill('Rixos');
      await waitForOptions();
      await input.fill('Marriott');
      await input.press('Enter');
      assert.equal(await select.inputValue(), '', 'Enter during replacement query cannot select a previous Rixos hotel');
      assert.equal(await input.inputValue(), 'Marriott');
      await waitForOptions();
      assert.match(await list.innerText(), /MARRIOTT HOTEL/);
      assert.doesNotMatch(await list.innerText(), /RIXOS/);
      await input.press('Enter');
      assert.equal(await select.inputValue(), '42001', 'new query selects its own canonical identity');

      async function delayLookup(query) {
        let started, release, finished;
        const begun = new Promise(resolve => { started = resolve; });
        const wait = new Promise(resolve => { release = resolve; });
        const done = new Promise(resolve => { finished = resolve; });
        pauseLookup = { started, wait, finished };
        await input.fill(query);
        await Promise.race([begun, new Promise((_, reject) => setTimeout(() => reject(new Error('catalog lookup did not start')), 3000))]);
        return async () => { release(); await done; await page.waitForTimeout(100); };
      }

      const finishOld = await delayLookup('Rixos');
      assert.match(await list.innerText(), /Ищем отели…/, 'pending catalog request is visible');
      assert.equal(await input.getAttribute('aria-busy'), 'true');
      assert.equal(await list.locator('[role="option"]').count(), 0, 'loading cannot expose stale options');
      await input.press('Enter');
      assert.deepEqual(searchStarts, [], 'Enter during visible loading cannot submit a supplier search');
      await page.screenshot({ path: path.join(output, `known-hotel-loading-${viewport.width}x${viewport.height}.png`), animations: 'disabled' });
      await input.fill('Marriott');
      await waitForOptions();
      await finishOld();
      assert.equal(await input.getAttribute('aria-busy'), null);
      assert.match(await list.innerText(), /MARRIOTT HOTEL/, 'late old response cannot overwrite the replacement query');
      assert.doesNotMatch(await list.innerText(), /RIXOS/);

      for (const dismissal of ['clear', 'escape', 'geography', 'blur']) {
        const finish = await delayLookup('Rixos');
        if (dismissal === 'clear') await input.fill('R');
        if (dismissal === 'escape') await input.press('Escape');
        if (dismissal === 'geography') await form.locator('select[name="region"]').selectOption('401');
        if (dismissal === 'blur') await form.locator('input[name="price_from"]').focus();
        await finish();
        assert.equal(await list.isVisible(), false, dismissal + ' invalidates pending suggestions');
        assert.equal(await select.inputValue(), '', dismissal + ' does not select an old hotel');
        assert.equal(await input.getAttribute('aria-activedescendant'), null);
        assert.equal(await input.inputValue(), dismissal === 'clear' ? 'R' : 'Rixos', 'dismissal keeps the current query');
      }
      const lookupsBeforeEscape = hotelQueries.length;
      await input.fill('Marriott');
      await input.press('Escape');
      await page.waitForTimeout(250);
      assert.equal(hotelQueries.length, lookupsBeforeEscape, 'Escape also cancels a lookup waiting for debounce');
      assert.equal(await list.isVisible(), false);
      assert.deepEqual(searchStarts, [], 'pending Enter and cancelled suggestions never start a broad supplier search');

      const tripBeforeRetry = await tripContext();
      const retry = list.getByRole('button', { name: 'Повторить поиск отеля' });
      const failures = [
        { status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, items: [] }) },
        { status: 200, contentType: 'application/json', body: JSON.stringify({ ok: false, items: [] }) },
        { status: 200, contentType: 'application/json', body: '{broken' },
        { abort: true }
      ];
      for (const [index, failure] of failures.entries()) {
        nextLookupResponse = failure;
        await input.fill('Rixos');
        await retry.waitFor({ state: 'visible' });
        assert.match(await list.innerText(), /Не удалось загрузить отели[\s\S]*Название сохранено/);
        assert.doesNotMatch(await list.innerText(), /По названию ничего не нашли/, 'catalog failure is not disguised as no matching hotel');
        assert.equal(await input.getAttribute('aria-busy'), null);
        assert.equal(await input.inputValue(), 'Rixos');
        assert.equal(await select.inputValue(), '');
        await input.press('Enter');
        assert.deepEqual(searchStarts, [], 'Enter after catalog failure cannot broaden the search');
        await input.press('Tab');
        await page.waitForTimeout(200);
        assert.equal(await retry.evaluate(node => node === document.activeElement), true, 'retry retains focus after the old input-blur deadline');
        assert.equal(await retry.isVisible(), true);
        assert.notEqual(await retry.evaluate(node => getComputedStyle(node).outlineStyle), 'none', 'keyboard retry has a visible focus indicator');
        const retryBox = await retry.boundingBox();
        assert.ok(retryBox.height >= 44 && retryBox.y >= 0 && retryBox.y + retryBox.height <= viewport.height + 1, 'retry is touch-sized and reachable in the short viewport');
        assert.ok(await retry.evaluate(node => parseFloat(getComputedStyle(node).fontSize) >= 14), 'recovery action stays readable and does not inherit small helper text');
        if (index === 0) await page.screenshot({ path: path.join(output, `known-hotel-error-${viewport.width}x${viewport.height}.png`), animations: 'disabled' });
        if (index === 1) {
          const beforeEscape = hotelQueries.length;
          await retry.press('Escape');
          assert.equal(await list.isVisible(), false, 'Escape dismisses recovery from its button');
          assert.equal(await input.evaluate(node => node === document.activeElement), true, 'dismissed recovery returns focus to the query');
          assert.equal(await input.inputValue(), 'Rixos');
          assert.equal(hotelQueries.length, beforeEscape, 'dismissing recovery does not retry');
          continue;
        }
        const beforeRetry = hotelQueries.length;
        const failedQuery = hotelQueries.at(-1);
        if (index === 2 && viewport.width < 768) await retry.tap();
        else await retry.press('Enter');
        await waitForOptions();
        assert.equal(hotelQueries.length, beforeRetry + 1, 'explicit retry performs exactly one catalog request');
        assert.deepEqual(hotelQueries.at(-1), failedQuery, 'retry keeps exact query and geography');
        assert.deepEqual(await tripContext(), tripBeforeRetry, 'retry preserves trip parameters');
        assert.equal(await input.evaluate(node => node === document.activeElement), true);
        assert.deepEqual(searchStarts, [], 'catalog retry never starts a supplier search');
      }

      nextLookupResponse = failures[0];
      const finishOldFailure = await delayLookup('Rixos');
      await input.fill('Marriott');
      await waitForOptions();
      await finishOldFailure();
      assert.match(await list.innerText(), /MARRIOTT HOTEL/, 'late failure cannot replace a newer successful query');
      assert.equal(await retry.count(), 0);

      let keyboardViewport = null;
      if (viewport.width < 768) {
        const longHotels = Array.from({ length: 10 }, (_, i) => ({ ...hotels[0], id: 43000 + i, name: `RIXOS PREMIUM FAMILY RESORT THE LAND OF LEGENDS ${i + 1}` }));
        nextLookupResponse = { status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, items: longHotels }) };
        await input.fill('Rixos');
        await waitForOptions();
        assert.equal(await list.locator('[role="option"]').count(), 10);
        const lookupCount = hotelQueries.length;
        const keyboardTrip = await tripContext();
        // Model a keyboard shrinking/panning only VisualViewport. Changing the
        // Playwright layout size alone misses the fixed-bottom keyboard defect.
        await page.evaluate(() => {
          const viewport = window.visualViewport;
          const state = { height: 320, width: innerWidth, offsetTop: 0, offsetLeft: 0 };
          const saved = Object.fromEntries(Object.keys(state).map(key => [key, Object.getOwnPropertyDescriptor(viewport, key)]));
          window.__hotelViewportFixture = { viewport, state, saved };
          for (const key of Object.keys(state)) Object.defineProperty(viewport, key, { configurable: true, get: () => state[key] });
          viewport.dispatchEvent(new Event('resize'));
        });
        const settled = () => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        async function visiblePopup(label) {
          await settled();
          const value = await page.evaluate(() => {
            const bounds = node => { const r = node.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, height: r.height }; };
            const v = window.visualViewport;
            const panel = document.getElementById('hotelAutocompleteList');
            return { input: bounds(document.querySelector('[data-v2-hotel-query]')), panel: bounds(panel), viewport: { top: v?.offsetTop || 0, left: v?.offsetLeft || 0, width: v?.width || innerWidth, height: v?.height || innerHeight }, scrollY, scrollable: panel.scrollHeight > panel.clientHeight, overflow: panel.scrollWidth > panel.clientWidth + 1 };
          });
          const { panel, input: query, viewport: visible } = value;
          assert.ok(panel.top >= visible.top + 11 && panel.bottom <= visible.top + visible.height - 11, label + ': panel stays above the keyboard');
          assert.ok(panel.left >= visible.left + 11 && panel.right <= visible.left + visible.width - 11, label + ': panel follows horizontal viewport panning');
          assert.ok(query.bottom <= panel.top - 7 || query.top >= panel.bottom + 7, label + ': suggestions do not cover the query');
          assert.equal(value.overflow, false, label + ': long names do not overflow the panel');
          return value;
        }
        const shrunk = await visiblePopup('keyboard opens');
        assert.ok(shrunk.input.top >= shrunk.viewport.top && shrunk.input.bottom <= shrunk.viewport.top + shrunk.viewport.height, 'keyboard opening preserves a visible query');
        assert.equal(shrunk.scrollable, true, 'long results scroll inside the available space');
        await page.evaluate(() => {
          const fixture = window.__hotelViewportFixture;
          Object.assign(fixture.state, { offsetTop: 24, offsetLeft: 10, width: innerWidth - 20 });
          for (let i = 0; i < 4; i++) fixture.viewport.dispatchEvent(new Event('scroll'));
        });
        const panned = await visiblePopup('keyboard viewport pans');
        assert.equal(panned.scrollY, shrunk.scrollY, 'viewport panning does not force a page-scroll loop');
        assert.equal(hotelQueries.length, lookupCount, 'viewport updates do not repeat catalog lookup');
        assert.deepEqual(await tripContext(), keyboardTrip);
        await input.press('ArrowUp');
        assert.equal(await input.getAttribute('aria-activedescendant'), 'hotelAutocompleteOption9');
        const last = await list.locator('[role="option"]').last().boundingBox();
        const activePanel = await visiblePopup('last keyboard result');
        assert.ok(last.y >= activePanel.panel.top && last.y + last.height <= activePanel.panel.bottom, 'last option is revealed within the scrolled panel');
        await page.screenshot({ path: path.join(output, `known-hotel-keyboard-model-${viewport.width}x${viewport.height}.png`), animations: 'disabled' });
        if (viewport.width === 390) await list.locator('[role="option"]').last().tap();
        else await input.press('Enter');
        assert.equal(await select.inputValue(), '43009', 'selection under the keyboard keeps the exact hotel identity');
        assert.equal(await list.getAttribute('style'), null, 'closing releases transient mobile geometry');

        nextLookupResponse = failures[0];
        await input.fill('Rixos');
        await retry.waitFor({ state: 'visible' });
        await input.press('Tab');
        await page.evaluate(() => {
          const fixture = window.__hotelViewportFixture;
          fixture.state.height = 280;
          fixture.viewport.dispatchEvent(new Event('resize'));
        });
        const failed = await visiblePopup('recovery with shorter keyboard viewport');
        const action = await retry.boundingBox();
        assert.ok(action.y >= failed.panel.top && action.y + action.height <= failed.panel.bottom, 'focused retry stays reachable after another keyboard resize');
        assert.equal(await retry.evaluate(node => node === document.activeElement), true);
        await page.screenshot({ path: path.join(output, `known-hotel-keyboard-error-${viewport.width}x${viewport.height}.png`), animations: 'disabled' });
        const beforeRetry = hotelQueries.length;
        await retry.press('Enter');
        await waitForOptions();
        assert.equal(hotelQueries.length, beforeRetry + 1, 'keyboard-safe recovery performs exactly one retry');
        assert.deepEqual(await tripContext(), keyboardTrip);
        assert.deepEqual(searchStarts, [], 'keyboard geometry and recovery never start a supplier search');

        await page.evaluate(() => {
          const fixture = window.__hotelViewportFixture;
          for (const [key, descriptor] of Object.entries(fixture.saved)) {
            if (descriptor) Object.defineProperty(fixture.viewport, key, descriptor);
            else delete fixture.viewport[key];
          }
          fixture.viewport.dispatchEvent(new Event('resize'));
          delete window.__hotelViewportFixture;
        });
        await visiblePopup('keyboard closes');
        await page.evaluate(() => {
          window.__hotelViewportDescriptor = Object.getOwnPropertyDescriptor(window, 'visualViewport');
          Object.defineProperty(window, 'visualViewport', { configurable: true, value: undefined });
          window.dispatchEvent(new Event('resize'));
        });
        const fallback = await visiblePopup('visual viewport unavailable');
        assert.equal(fallback.viewport.height, viewport.height);
        await page.evaluate(() => { if (window.__hotelViewportDescriptor) Object.defineProperty(window, 'visualViewport', window.__hotelViewportDescriptor); else delete window.visualViewport; delete window.__hotelViewportDescriptor; window.dispatchEvent(new Event('resize')); });
        await page.setViewportSize({ width: 1000, height: 800 });
        await settled();
        assert.equal(await list.getAttribute('style'), null, 'desktop breakpoint restores the existing anchored dropdown');
        await page.setViewportSize(viewport);
        await visiblePopup('return to mobile');
        keyboardViewport = { model: 'controlled VisualViewport shrink/pan; no physical keyboard', shrunk, panned, failed, lastOptionSelected: '43009', retryVisible: true, fallback: true, desktopReset: true, tripPreserved: true };
        await input.press('Escape');
      }

      const tripBeforeRecovery = await form.evaluate(node => ({
        country: node.elements.country.value,
        dateFrom: node.elements.dateFrom.value,
        dateTo: node.elements.dateTo.value,
        daysFrom: node.elements.daysFrom.value,
        daysTill: node.elements.daysTill.value,
        adults: node.elements.count_people.value,
        children: node.elements.child_count.value
      }));
      await input.fill('NoSuchHotel');
      const recovery = list.locator('.hotel-autocomplete__empty');
      const searchAll = recovery.getByRole('button', { name: 'Искать туры по всем отелям' });
      await searchAll.waitFor({ state: 'visible' });
      assert.match(await recovery.innerText(), /По названию ничего не нашли[\s\S]*Проверьте написание или найдите тур среди всех отелей/);
      assert.equal(await list.getAttribute('role'), 'group', 'actionable empty recovery is not exposed as a listbox with invalid children');
      assert.equal(await list.locator('[role="option"]').count(), 0, 'an empty canonical lookup cannot expose a selectable hotel');
      const recoveryButtonBox = await searchAll.boundingBox();
      assert.ok(recoveryButtonBox.height >= 44, 'all-hotels recovery is a full touch target');
      assert.ok(await searchAll.evaluate(node => parseFloat(getComputedStyle(node).fontSize) >= 14), 'empty recovery action remains readable');
      assert.ok(recoveryButtonBox.y >= 0 && recoveryButtonBox.y + recoveryButtonBox.height <= viewport.height + 1, 'all-hotels recovery remains reachable in the short viewport');
      assert.equal(await recovery.evaluate(node => node.scrollWidth > node.clientWidth + 1), false, 'empty recovery does not overflow its lookup panel');
      await input.press('Enter');
      assert.deepEqual(searchStarts, [], 'Enter on unmatched text cannot silently start an unrestricted search');
      await input.press('Tab');
      await page.waitForTimeout(200);
      assert.equal(await searchAll.evaluate(node => node === document.activeElement), true, 'all-hotels action remains focused after input blur');
      assert.equal(await searchAll.isVisible(), true);
      await page.screenshot({ path: path.join(output, `known-hotel-empty-${viewport.width}x${viewport.height}.png`), animations: 'disabled' });
      const recoverySearch = page.waitForRequest(request => new URL(request.url()).searchParams.get('action') === 'search_start');
      await Promise.all([recoverySearch, searchAll.press('Enter')]);
      assert.equal(await input.inputValue(), '', 'all-hotels recovery clears only the unmatched hotel query');
      assert.equal(await select.inputValue(), '', 'all-hotels recovery cannot retain an exact hotel identity');
      assert.equal(await list.isVisible(), false);
      assert.deepEqual(await form.evaluate(node => ({
        country: node.elements.country.value,
        dateFrom: node.elements.dateFrom.value,
        dateTo: node.elements.dateTo.value,
        daysFrom: node.elements.daysFrom.value,
        daysTill: node.elements.daysTill.value,
        adults: node.elements.count_people.value,
        children: node.elements.child_count.value
      })), tripBeforeRecovery, 'all-hotels recovery preserves the complete trip context');
      assert.equal(searchStarts.length, 1, 'explicit all-hotels recovery starts exactly one normal search');
      assert.equal(new URL(searchStarts[0]).searchParams.has('hotel'), false, 'recovery search does not send a stale hotel restriction');
      evidence.push({ viewport, geometry, keyboardViewport, catalogFailureCases: failures.length, keyboardRecovery: true, queryReopening: true, dismissedEnterRecovery: true, shortQueryGuidance: true, coalescedReturnEdit: true, selectedReturnPreserved: true, touchSelection: viewport.width < 768, hotelQueries: hotelQueries.slice(), selectedHotelId: '41001', supplierSearches: searchStarts.length, errors });
      assert.deepEqual(errors, []);
      await page.close();
    }
    fs.writeFileSync(path.join(output, 'known-hotel.json'), JSON.stringify({ sourceSha: process.env.SEARCH3_SOURCE_SHA || '', evidence }, null, 2) + '\n');
    console.log('SEARCH3_KNOWN_HOTEL_OK viewports=' + viewports.length + ' supplier_searches=0');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
