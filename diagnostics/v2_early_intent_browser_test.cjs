'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const origin = 'https://search3-intent-fixture.invalid';
const scripts = ['runtime-v3.js', 'analytics-v4.js', 'tour-controller-v4.js', 'search-lifecycle-v6.js'];
const observerSource = fs.readFileSync(path.join(__dirname, 'v2_early_intent_shadow.cjs'), 'utf8');
const searchForm = {
  from: '1', country: '4', dateFrom: '2099-06-01', dateTo: '2099-06-03',
  daysFrom: '7', daysTill: '7', count_people: '2',
};
const html = `<!doctype html>
<form id="tourSearch">
  ${Object.entries(searchForm).map(([name, value]) => `<input name="${name}" value="${value}" type="hidden">`).join('')}
  <button class="primary" type="submit">Найти туры</button>
</form>
<div id="status"></div><div id="results"></div><div id="resultsTools"></div>
<section id="selectedTour" hidden></section>
${scripts.map(file => `<script src="/${file}"></script>`).join('')}`;

async function checkWidth(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  const unexpectedRequests = [];
  let searchId = 0;
  try {
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      if (url.origin === origin) {
        if (url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
        const script = url.pathname.slice(1);
        if (scripts.includes(script)) return route.fulfill({
          contentType: 'application/javascript', body: fs.readFileSync(path.join(__dirname, '..', 'v2', script), 'utf8'),
        });
        if (url.pathname === '/api-v2.php') {
          const action = url.searchParams.get('action');
          const data = {
            search_start: () => ({ searchId: ++searchId }),
            search_status: () => ({ progress: 100 }),
            search_results: () => [],
            tour: () => ({ id: 'fixture-tour', price: 100000 }),
            flights: () => [{ isDefault: true, price: 100000 }, { price: 105000 }],
          }[action];
          if (data) return route.fulfill({ json: data() });
        }
      }
      unexpectedRequests.push(url.href);
      return route.abort();
    });
    await page.addInitScript({ content: `(() => {
      const module = { exports: {} };
      ${observerSource}
      window.intentDiagnostic = module.exports.observeEarlyIntent(window);
      window.V2_CONFIG = { api: '/api-v2.php', metrikaCounter: 98615635 };
      window.dataLayer = [];
      window.fixtureGoals = [];
      window.ym = (_counter, method, name, params) => {
        if (method === 'reachGoal') window.fixtureGoals.push({ name, params });
      };
      window.V2Catalogs = { renderChildAges() {}, async init() {} };
      window.V2Results = {
        render() {
          document.getElementById('results').innerHTML =
            '<button type="button" class="direct-tour" data-tid="fixture-tour">Выбрать</button>';
        },
      };
    })();` });
    await page.goto(`${origin}/?from=1&country=4&date_from=2099-06-01`);
    await page.locator('.direct-tour').click();
    await page.waitForFunction(() => window.intentDiagnostic.read().evidence.defaultFlightAssignment);
    const automatic = await page.evaluate(() => window.intentDiagnostic.read());
    assert.equal(automatic.intent, 'unknown');
    assert.equal(automatic.automation, 'unknown');
    assert.deepEqual(automatic.evidence, {
      formSubmit: false, flightControl: false, urlHydration: true, defaultFlightAssignment: true,
    });
    const cutoff = await page.evaluate(() => window.V2Runtime.pageObservation());

    await page.locator('#tourSearch .primary').click();
    await page.locator('.direct-tour').click();
    await page.locator('input[name="v2flight"][value="1"]').check();
    await page.waitForFunction(() => window.intentDiagnostic.read().evidence.flightControl);
    const manual = await page.evaluate(() => window.intentDiagnostic.read());
    assert.equal(manual.intent, 'manual_action_observed');
    assert.equal(manual.automation, 'unknown');
    assert.equal(manual.evidence.formSubmit, true);
    assert.deepEqual(await page.evaluate(offset => window.intentDiagnostic.read(offset), cutoff.offsetMs), automatic);
    const analytics = await page.evaluate(() => ({ goals: window.fixtureGoals, events: window.dataLayer }));
    assert.ok(analytics.goals.some(goal => goal.name === 'V2_FLIGHT_SELECTED'));
    assert.ok(!JSON.stringify({ manual, analytics }).includes(cutoff.pageRef));
    assert.ok(!JSON.stringify(analytics).includes('flight_control'));

    await page.goto(origin);
    assert.notEqual(await page.evaluate(() => window.V2Runtime.pageObservation().pageRef), cutoff.pageRef);
    await page.evaluate(previous => window.dispatchEvent(new CustomEvent('v2:search-started', {
      detail: { searchId: 1, generation: 1, origin: 'form_submit', pageObservation: previous },
    })), cutoff);
    assert.equal(await page.evaluate(() => window.intentDiagnostic.read().intent), 'unknown');
    await page.evaluate(() => window.intentDiagnostic.stop());
    await page.locator('#tourSearch .primary').click();
    await page.waitForFunction(() => window.V2SearchLifecycle.searchId > 0);
    assert.equal(await page.evaluate(() => window.intentDiagnostic.read().intent), 'unknown');
    assert.deepEqual(unexpectedRequests, []);
    console.log(`V2_EARLY_INTENT_BROWSER_OK width=${width}`);
  } finally {
    await page.close();
  }
}

(async () => {
  const browser = await chromium.launch();
  try {
    for (const width of [430, 1280]) await checkWidth(browser, width);
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
