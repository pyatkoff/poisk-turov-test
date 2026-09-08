/* Actual isolated page: one chunk, failed download retry, reset cancellation and first click. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const base = process.env.SEARCH3_VISUAL_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const evidence = [];
  try {
    for (const scenario of ['retry', 'reset', 'tour-retry']) {
      const page = await browser.newPage({ viewport: { width: 375, height: 900 } });
      const errors = [];
      let chunks = 0, release;
      page.on('pageerror', error => errors.push(String(error)));
      await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (url.origin !== new URL(base).origin || request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
        if (url.pathname.endsWith('/bundle-v1.php') && url.searchParams.get('phase') === 'selected') {
          chunks++;
          if (scenario === 'retry' && chunks === 1) return route.abort('failed');
          if (scenario === 'reset' && chunks === 1) await new Promise(resolve => { release = resolve; });
        }
        return route.continue();
      });
      await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(() => window.V2Results && window.V2TourController && window.Search3SummaryCta);
      assert.equal(chunks, 0, 'no selected chunk before selection');
      assert.equal(await page.evaluate(() => typeof window.V2FlightPriceSync), 'undefined', 'flight-price code is deferred');
      assert.equal(await page.evaluate(() => typeof window.V2LeadFormGuard), 'undefined', 'lead presentation is deferred');
      assert.equal(await page.evaluate(() => !!window.V2LeadUiRaceGuardV1), true, 'stale lead protection is eager');
      await page.evaluate(scenario => {
        const tour = { id: 'lazy-tour', price: 148500.6, hotel: { name: 'Проверочный отель' }, adults: 2, nights: 9 };
        window.__lazyCalls = [];
        let failTour = scenario === 'tour-retry';
        window.V2Runtime.api = async (action, payload) => {
          window.__lazyCalls.push([action, payload && payload.tourId]);
          if (action === 'tour') { if (failTour) { failTour = false; throw Error('Fixture tour unavailable'); } return tour; }
          if (action === 'flights') return [{ price: { value: 150001.2 }, isDefault: true, forward: [], backward: [] }];
          throw Error('Unexpected API action ' + action);
        };
        window.__lazyHotels = [{ id: 'lazy-hotel', name: 'Проверочный отель', price: tour.price, tours: [tour] }];
        window.V2Results.render(window.__lazyHotels);
      }, scenario);
      const action = page.locator('#results .direct-tour');
      await action.click();
      if (scenario === 'retry') {
        await page.waitForFunction(() => document.querySelector('.direct-tour')?.textContent.includes('Повторить'));
        assert.deepEqual(await page.evaluate(() => window.__lazyCalls), [], 'download failure makes no tour request');
        await action.click();
      } else if (scenario === 'reset') {
        await page.waitForFunction(() => document.querySelector('.direct-tour')?.getAttribute('aria-busy') === 'true');
        // Wait for the intercepted resource without polling the external API.
        while (!release) await new Promise(resolve => setTimeout(resolve, 10));
        await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset')));
        release();
        await page.waitForFunction(() => window.V2FlightPriceSync && !document.querySelector('.direct-tour')?.disabled);
        assert.deepEqual(await page.evaluate(() => window.__lazyCalls), [], 'reset cancels pending selection');
        await page.evaluate(() => window.V2Results.render(window.__lazyHotels));
        await action.click();
      }
      if (scenario === 'tour-retry') {
        await page.waitForSelector('#selectedTour .tour-load-retry');
        await page.locator('#selectedTour .tour-load-retry').click();
      }
      await page.waitForSelector('#selectedTour .flight-variant');
      await page.waitForSelector('#selectedTour .search3-flight-continue button');
      const expectedCalls = [['tour', 'lazy-tour'], ['flights', 'lazy-tour']];
      if (scenario === 'tour-retry') expectedCalls.unshift(['tour', 'lazy-tour']);
      assert.deepEqual(await page.evaluate(() => window.__lazyCalls), expectedCalls, 'only explicit retry repeats a tour request');
      const price = await page.locator('#selectedTour .selected-price').innerText();
      assert.match(price, /150[\s\u00a0]*001,2/, 'decimal selected price retained');
      assert.equal(await page.locator('#selectedTour .search3-flight-continue button').innerText(), 'Оставить заявку', 'lazy chunk exposes native lead handoff');
      await page.locator('#selectedTour .search3-flight-continue button').click();
      await page.waitForSelector('#selectedTour.search3-lead-entry .lead-form input[name="phone"]');
      const phone = page.locator('#selectedTour .lead-form input[name="phone"]');
      await phone.fill('12');
      assert.equal(await phone.evaluate(input => input.validity.customError), true, 'late guard validates phone');
      await phone.fill('+7 999 123 45 67');
      assert.equal(await phone.evaluate(input => input.validity.customError), false, 'late guard clears corrected phone validity');
      const leadState = await page.evaluate(() => {
        const root = document.getElementById('selectedTour'), back = root.querySelector('.back-results');
        window.V2Runtime.state.searchId = 41;
        window.dispatchEvent(new CustomEvent('v2:lead-started'));
        const locked = back.disabled;
        window.dispatchEvent(new CustomEvent('v2:lead-error', { detail: { tourId: 'stale-tour', searchId: 41 } }));
        const staleBlocked = back.disabled && root.querySelector('.lead-message').dataset.state === 'sending';
        window.dispatchEvent(new CustomEvent('v2:lead-error', { detail: { tourId: 'lazy-tour', searchId: 41 } }));
        return { locked, staleBlocked, unlocked: !back.disabled, error: root.querySelector('.lead-message').dataset.state };
      });
      assert.deepEqual(leadState, { locked: true, staleBlocked: true, unlocked: true, error: 'error' }, 'eager race guard runs before late lead presentation');
      assert.equal(await page.locator('.search3-booking-summary,.search3-summary-submit').count(), 0, 'duplicate booking review remains absent');
      await page.locator('#selectedTour .back-results').click();
      await page.waitForFunction(() => document.activeElement?.matches('.direct-tour'));
      assert.equal(chunks, scenario === 'retry' ? 2 : 1, 'loaded chunk is reused');
      assert.deepEqual(errors, []);
      evidence.push({ scenario, chunks, tourRequests: scenario === 'tour-retry' ? 2 : 1, flightRequests: 1, nativeLeadHandoff: true, phoneValidation: true, staleLeadBlocked: true, returnFocus: true, price });
      await page.close();
    }
  } finally { await browser.close(); }
  if (process.env.SEARCH3_GEOMETRY_OUTPUT) fs.writeFileSync(path.join(process.env.SEARCH3_GEOMETRY_OUTPUT, 'lazy-selected.json'), JSON.stringify(evidence, null, 2));
  console.log('SEARCH3_LAZY_SELECTED_OK ' + JSON.stringify(evidence));
})().catch(error => { console.error(error); process.exitCode = 1; });
