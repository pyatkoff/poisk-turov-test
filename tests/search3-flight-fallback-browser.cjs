const { chromium } = require('playwright');
const path = require('path');

const root = path.resolve(__dirname, '..');

function tour(id) {
  return {
    id,
    price: 148500,
    hotel: {
      name: 'Проверочный отель с длинным названием',
      country: { name: 'Турция' },
      region: { name: 'Анталья' }
    },
    departure: { name: 'Москва' },
    date: '12.09.2026',
    nights: 9,
    adults: 2,
    childs: 1,
    meal: { name: 'Всё включено' },
    roomType: 'STANDARD LAND VIEW',
    placement: 'DBL + CHD',
    operator: { name: 'TEST OPERATOR' },
    isCharter: true
  };
}

async function prepare(page, mode) {
  await page.setContent(`
    <body class="search3-candidate">
      <form id="tourSearch"><input name="sessid" value="test"></form>
      <div id="results"></div>
      <section id="selectedTour" hidden></section>
      <div class="search3-selected-mobile-bar" hidden>
        <span class="search3-selected-mobile-bar__price"><small></small><strong data-s3-selected-price></strong></span>
        <button type="button" data-s3-selected-lead></button>
      </div>
    </body>
  `);
  await page.evaluate(({ mode, tour }) => {
    let flightCalls = 0;
    let leadRequests = 0;
    window.__fallbackTest = {
      get flightCalls() { return flightCalls; },
      get leadRequests() { return leadRequests; }
    };
    window.fetch = async () => {
      leadRequests += 1;
      throw new Error('lead transport must not be called by fallback navigation');
    };
    window.V2_CONFIG = { leadApi: '/must-not-call' };
    window.V2Runtime = {
      state: { searchId: 996 },
      api(action) {
        if (action === 'tour') return Promise.resolve(tour);
        if (action !== 'flights') throw new Error('unexpected API action ' + action);
        flightCalls += 1;
        if (mode === 'recover' && flightCalls >= 2) {
          return Promise.resolve([{
            isDefault: true,
            price: { value: 149900 },
            fuelCharge: { value: 1400 },
            forward: [],
            backward: []
          }]);
        }
        return Promise.resolve([]);
      }
    };
  }, { mode, tour: tour('fallback-tour') });

  for (const file of [
    'v2/tour-controller-v4.js',
    'v2/flight-empty-recovery-v1.js',
    'v2/search3-results-filters-v1.js',
    'v2/search3-selected-flow-v2.js'
  ]) {
    await page.addScriptTag({ path: path.join(root, file) });
  }
  await page.evaluate(() => window.V2TourController.selectTour('fallback-tour'));
  await page.waitForFunction(() => (
    window.__fallbackTest.flightCalls === 1
    && document.querySelector('#selectedTour.search3-flight-fallback .load-flights')
  ));
}

async function verifyFallbackHandoff(browser) {
  const page = await browser.newPage({ viewport: { width: 375, height: 900 } });
  await prepare(page, 'empty');

  let state = await page.evaluate(() => ({
    flightCalls: window.__fallbackTest.flightCalls,
    retries: document.querySelectorAll('#selectedTour .load-flights').length,
    message: document.querySelector('#selectedTour .selected-loading')?.textContent || '',
    fallback: document.getElementById('selectedTour').dataset.search3FlightFallback,
    continueText: document.querySelector('#selectedTour .search3-flight-continue button')?.textContent || '',
    mobileText: document.querySelector('[data-s3-selected-lead]')?.textContent || '',
    mobileAction: document.querySelector('[data-s3-selected-lead]')?.dataset.search3SelectedFlowAction || ''
  }));
  if (
    state.flightCalls !== 1
    || state.retries !== 1
    || !state.message.includes('менеджер уточнит перелёт по заявке')
    || state.fallback !== '1'
    || state.continueText !== 'Далее: итог тура'
    || state.mobileText !== 'Далее: итог тура'
    || state.mobileAction !== '1'
  ) throw new Error('initial fallback state failed: ' + JSON.stringify(state));

  await page.click('#selectedTour .load-flights');
  await page.waitForFunction(() => window.__fallbackTest.flightCalls === 2);
  await page.waitForFunction(() => document.querySelectorAll('#selectedTour .load-flights').length === 1);

  await page.click('[data-s3-selected-lead]');
  await page.waitForFunction(() => document.getElementById('selectedTour').classList.contains('search3-final-review'));
  await page.waitForSelector('#selectedTour .search3-summary-submit');
  state = await page.evaluate(() => ({
    review: document.getElementById('selectedTour').classList.contains('search3-final-review'),
    summaryAction: document.querySelector('#selectedTour .search3-summary-submit')?.textContent || '',
    leadEntry: document.getElementById('selectedTour').classList.contains('search3-lead-entry'),
    leadRequests: window.__fallbackTest.leadRequests
  }));
  if (!state.review || state.summaryAction !== 'Перейти к заявке' || state.leadEntry || state.leadRequests !== 0) {
    throw new Error('fallback review handoff failed: ' + JSON.stringify(state));
  }

  await page.click('#selectedTour .search3-summary-submit');
  await page.waitForFunction(() => document.getElementById('selectedTour').classList.contains('search3-lead-entry'));
  state = await page.evaluate(() => ({
    review: document.getElementById('selectedTour').classList.contains('search3-final-review'),
    leadEntry: document.getElementById('selectedTour').classList.contains('search3-lead-entry'),
    form: !!document.querySelector('#selectedTour .lead-form'),
    submitText: document.querySelector('#selectedTour .lead-form button[type="submit"]')?.textContent || '',
    leadRequests: window.__fallbackTest.leadRequests
  }));
  if (!state.review || !state.leadEntry || !state.form || state.submitText !== 'Отправить заявку' || state.leadRequests !== 0) {
    throw new Error('fallback lead entry failed: ' + JSON.stringify(state));
  }
  console.log('SEARCH3_FLIGHT_FALLBACK_HANDOFF_OK ' + JSON.stringify(state));
  await page.close();
}

async function verifyRetryRecovery(browser) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await prepare(page, 'recover');
  await page.click('#selectedTour .load-flights');
  await page.waitForFunction(() => (
    window.__fallbackTest.flightCalls === 2
    && document.querySelector('#selectedTour .flight-variant')
    && !document.getElementById('selectedTour').classList.contains('search3-flight-fallback')
  ));
  const state = await page.evaluate(() => ({
    flightCalls: window.__fallbackTest.flightCalls,
    variants: document.querySelectorAll('#selectedTour .flight-variant').length,
    retries: document.querySelectorAll('#selectedTour .load-flights').length,
    fallback: document.getElementById('selectedTour').dataset.search3FlightFallback || '',
    mobileFallbackAction: document.querySelector('[data-s3-selected-lead]')?.dataset.search3SelectedFlowAction || '',
    leadRequests: window.__fallbackTest.leadRequests
  }));
  if (
    state.flightCalls !== 2
    || state.variants !== 1
    || state.retries !== 0
    || state.fallback
    || state.mobileFallbackAction
    || state.leadRequests !== 0
  ) throw new Error('retry recovery failed: ' + JSON.stringify(state));
  console.log('SEARCH3_FLIGHT_RETRY_RECOVERY_OK ' + JSON.stringify(state));
  await page.close();
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    await verifyFallbackHandoff(browser);
    await verifyRetryRecovery(browser);
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exit(1);
});
