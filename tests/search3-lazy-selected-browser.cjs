/* Actual isolated page: eager canonical selected runtime, reset cancellation and tour retry. */
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
    for (const scenario of ['direct', 'reset', 'tour-retry']) {
      const page = await browser.newPage({ viewport: { width: 375, height: 900 } });
      const errors = [];
      let selectedPhaseRequests = 0;
      page.on('pageerror', error => errors.push(String(error)));
      await page.route('**/*', route => {
        const request = route.request(), url = new URL(request.url());
        if (url.origin !== new URL(base).origin || request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
        if (url.pathname.endsWith('/bundle-v1.php') && url.searchParams.get('phase') === 'selected') selectedPhaseRequests++;
        return route.continue();
      });
      await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(() => window.V2Results && window.V2TourController && window.V2FlightPriceSync && window.V2LeadFormGuard && window.Search3SummaryCta);
      assert.equal(selectedPhaseRequests, 0, 'page does not request a second selected-runtime chunk');
      assert.equal(await page.evaluate(() => !!window.V2LeadUiRaceGuardV1), true, 'stale lead protection remains eager');
      await page.evaluate(scenario => {
        const tour = { id: 'eager-tour', price: 148500.6, hotel: { name: 'Проверочный отель' }, adults: 2, nights: 9 };
        window.__eagerCalls = [];
        window.__resolveFirstTour = null;
        let failTour = scenario === 'tour-retry';
        let holdTour = scenario === 'reset';
        window.V2Runtime.api = async (action, payload) => {
          window.__eagerCalls.push([action, payload && payload.tourId]);
          if (action === 'tour') {
            if (failTour) { failTour = false; throw Error('Fixture tour unavailable'); }
            if (holdTour) {
              holdTour = false;
              await new Promise(resolve => { window.__resolveFirstTour = resolve; });
            }
            return tour;
          }
          if (action === 'flights') return [{ price: { value: 150001.2 }, isDefault: true, forward: [], backward: [] }];
          throw Error('Unexpected API action ' + action);
        };
        window.__eagerHotels = [{ id: 'eager-hotel', name: 'Проверочный отель', price: tour.price, tours: [tour] }];
        window.V2Results.render(window.__eagerHotels);
      }, scenario);
      const action = page.locator('#results .direct-tour');
      await action.click();
      if (scenario === 'reset') {
        await page.waitForFunction(() => window.__eagerCalls.length === 1 && typeof window.__resolveFirstTour === 'function');
        await page.evaluate(() => {
          window.dispatchEvent(new CustomEvent('v2:search-reset'));
          window.__resolveFirstTour();
        });
        await page.waitForFunction(() => !document.querySelector('.direct-tour')?.disabled);
        assert.deepEqual(await page.evaluate(() => window.__eagerCalls), [['tour', 'eager-tour']], 'reset cancels the pending tour before flights');
        await page.evaluate(() => window.V2Results.render(window.__eagerHotels));
        await action.click();
      }
      if (scenario === 'tour-retry') {
        await page.waitForSelector('#selectedTour .retry-tour');
        await page.locator('#selectedTour .retry-tour').click();
      }
      await page.waitForSelector('#selectedTour .flight-variant');
      await page.waitForSelector('#selectedTour .search3-flight-continue button');
      const expectedCalls = [['tour', 'eager-tour'], ['flights', 'eager-tour']];
      if (scenario === 'reset' || scenario === 'tour-retry') expectedCalls.unshift(['tour', 'eager-tour']);
      assert.deepEqual(await page.evaluate(() => window.__eagerCalls), expectedCalls, 'only an explicit reset or retry repeats the tour request');
      const price = await page.locator('#selectedTour .selected-price').innerText();
      assert.match(price, /150[\s\u00a0]*001,2/, 'decimal selected price retained');
      assert.equal(await page.locator('#selectedTour .search3-flight-continue button').innerText(), 'Оставить заявку', 'native lead handoff remains available');
      await page.locator('#selectedTour .search3-flight-continue button').click();
      await page.waitForSelector('#selectedTour.search3-lead-entry .lead-form input[name="phone"]');
      const phone = page.locator('#selectedTour .lead-form input[name="phone"]');
      await phone.fill('12');
      assert.equal(await phone.evaluate(input => input.validity.customError), true, 'eager guard validates phone');
      await phone.fill('+7 999 123 45 67');
      assert.equal(await phone.evaluate(input => input.validity.customError), false, 'eager guard clears corrected phone validity');
      const leadState = await page.evaluate(() => {
        const root = document.getElementById('selectedTour'), back = root.querySelector('.back-results');
        window.V2Runtime.state.searchId = 41;
        window.dispatchEvent(new CustomEvent('v2:lead-started'));
        const locked = back.disabled;
        window.dispatchEvent(new CustomEvent('v2:lead-error', { detail: { tourId: 'stale-tour', searchId: 41 } }));
        const staleBlocked = back.disabled && root.querySelector('.lead-message').dataset.state === 'sending';
        window.dispatchEvent(new CustomEvent('v2:lead-error', { detail: { tourId: 'eager-tour', searchId: 41 } }));
        return { locked, staleBlocked, unlocked: !back.disabled, error: root.querySelector('.lead-message').dataset.state };
      });
      assert.deepEqual(leadState, { locked: true, staleBlocked: true, unlocked: true, error: 'error' }, 'race guard still blocks stale lead state');
      assert.equal(await page.locator('.search3-booking-summary,.search3-summary-submit').count(), 0, 'duplicate booking review remains absent');
      await page.locator('#selectedTour .back-results').click();
      await page.waitForFunction(() => document.activeElement?.matches('.direct-tour'));
      assert.equal(selectedPhaseRequests, 0, 'selected runtime remains single-request after return');
      assert.deepEqual(errors, []);
      evidence.push({ scenario, selectedPhaseRequests, tourRequests: scenario === 'direct' ? 1 : 2, flightRequests: 1, nativeLeadHandoff: true, phoneValidation: true, staleLeadBlocked: true, returnFocus: true, price });
      await page.close();
    }
  } finally { await browser.close(); }
  if (process.env.SEARCH3_GEOMETRY_OUTPUT) fs.writeFileSync(path.join(process.env.SEARCH3_GEOMETRY_OUTPUT, 'eager-selected.json'), JSON.stringify(evidence, null, 2));
  console.log('SEARCH3_EAGER_SELECTED_OK ' + JSON.stringify(evidence));
})().catch(error => { console.error(error); process.exitCode = 1; });
