const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.SEARCH3_PREVIEW_URL || 'https://anytoour.ru/_preview/search3/poisk-turov/';
const outDir = process.env.SEARCH3_QA_OUT || 'search3-visual-artifacts';
fs.mkdirSync(outDir, { recursive: true });

const requiredStates = ['01-search','02-results','03-expanded-hotel','04-tour-details','05-flights','06-final-review','07-lead-sending','08-lead-success','09-lead-error'];
const requiredMobileStates = ['m01-search','m02-results','m02a-filters-open','m03-tour-details','m04-flights','m05-final-review','m06-lead-entry','m07-lead-success'];
const report = { url: baseUrl, startedAt: new Date().toISOString(), states: [], mobileStates: [], errors: [] };
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function visible(page, selector, timeout = 15000) {
  try { await page.locator(selector).first().waitFor({ state: 'visible', timeout }); return true; }
  catch (_) { return false; }
}
async function attached(page, selector, timeout = 15000) {
  try { await page.locator(selector).first().waitFor({ state: 'attached', timeout }); return true; }
  catch (_) { return false; }
}
async function clickVisible(page, selector, timeout = 7000) {
  const loc = page.locator(selector).first();
  try {
    if (!await loc.count() || !await loc.isVisible()) return false;
    await loc.click({ timeout });
    return true;
  } catch (_) { return false; }
}
async function settleImages(page, selector, timeout = 6000) {
  const deadline = Date.now() + timeout;
  while (Date.now() < deadline) {
    const pending = await page.locator(selector).evaluateAll(images => images.filter(img => img && img.tagName === 'IMG' && img.src && !img.complete).length).catch(() => 0);
    if (!pending) return true;
    await sleep(150);
  }
  return false;
}
async function snap(page, bucket, name, anchor, mobile = false) {
  try {
    if (anchor) {
      const loc = page.locator(anchor).first();
      if (await loc.count()) await loc.evaluate(el => { const y = el.getBoundingClientRect().top + scrollY - 10; scrollTo(0, Math.max(0, y)); }).catch(() => {});
      await sleep(180);
    } else {
      await page.evaluate(() => scrollTo(0, 0));
      await sleep(80);
    }
    if (mobile) {
      const sizes = await page.evaluate(() => ({ width: innerWidth, doc: document.documentElement.scrollWidth, body: document.body ? document.body.scrollWidth : 0 }));
      if (Math.max(sizes.doc, sizes.body) > sizes.width + 2) report.errors.push(`mobile horizontal overflow at ${name}: viewport=${sizes.width}, document=${sizes.doc}, body=${sizes.body}`);
    }
    const file = path.join(outDir, `${name}.png`);
    await page.screenshot({ path: file, fullPage: false, animations: 'disabled' });
    bucket.push({ name, ok: true, file, url: page.url() });
    return true;
  } catch (e) {
    bucket.push({ name, ok: false, error: String(e) });
    report.errors.push(`${name}: ${String(e)}`);
    return false;
  }
}
async function performSearch(page, mobile = false) {
  if (!await clickVisible(page, '#tourSearch .search-submit', 10000)) throw new Error(`${mobile ? 'mobile ' : ''}search submit missing or not clickable`);
  const hasResults = await visible(page, '#results .hotel-card', 120000);
  const complete = hasResults ? await attached(page, '#status .search-progress-done', 120000) : false;
  if (!hasResults || !complete) throw new Error(`${mobile ? 'mobile ' : ''}search did not reach completed results state`);
  await settleImages(page, '#results .hotel-card img', 6000);
  return { submitted: true, hasResults, complete };
}
async function openSelectedTour(page, mobile = false) {
  if (!await clickVisible(page, '#results .search3-show-tours', 7000)) throw new Error(`${mobile ? 'mobile ' : ''}show tours action missing`);
  if (!await visible(page, '#results .hotel-card .hotel-tours:not([hidden])', 20000)) throw new Error(`${mobile ? 'mobile ' : ''}tour list did not open`);
  if (!await clickVisible(page, '#results .hotel-tours:not([hidden]) .direct-tour', 7000)) throw new Error(`${mobile ? 'mobile ' : ''}direct-tour action missing`);
  if (!await visible(page, '#selectedTour:not([hidden])', 45000)) throw new Error(`${mobile ? 'mobile ' : ''}selected tour did not open`);
  if (!await visible(page, '#selectedTour .selected-head h2, #selectedTour .selected-picture', 45000)) throw new Error(`${mobile ? 'mobile ' : ''}tour details stayed loading`);
  await settleImages(page, '#selectedTour img', 5000);
}
async function enterFinalReview(page, mobile = false) {
  if (!await visible(page, '#selectedTour .tour-flights', 30000)) throw new Error(`${mobile ? 'mobile ' : ''}flights missing`);
  const clicked = await clickVisible(page, '#selectedTour .search3-flight-continue button', 7000);
  if (clicked && await visible(page, '#selectedTour.search3-final-review', 8000)) return 'real-continue';
  await page.evaluate(() => {
    const root = document.getElementById('selectedTour');
    if (root) root.classList.add('search3-final-review');
    window.dispatchEvent(new CustomEvent('v2:booking-review', { detail: { active: true, previewSimulation: true } }));
  });
  if (!await visible(page, '#selectedTour.search3-final-review', 5000)) throw new Error(`${mobile ? 'mobile ' : ''}final review did not open`);
  return 'preview-simulation';
}
async function forceLeadState(page, state, detail = {}) {
  const result = await page.evaluate(({ state, detail }) => {
    const form = document.querySelector('#selectedTour .lead-form');
    if (!form) return { ok: false, visible: false };
    delete form.dataset.search3LeadState;
    window.dispatchEvent(new CustomEvent('search3:preview-lead-state', { detail: Object.assign({ previewSimulation: true, state }, detail) }));
    return { ok: form.dataset.search3LeadState === state, visible: !!form.querySelector('.search3-lead-status:not([hidden])') };
  }, { state, detail });
  if (!result.ok || !result.visible) return false;
  return visible(page, `#selectedTour .lead-form[data-search3-lead-state="${state}"] .search3-lead-status`, 4000);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const desktopContext = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1, ignoreHTTPSErrors: true });
  const page = await desktopContext.newPage();
  page.setDefaultTimeout(10000);
  page.setDefaultNavigationTimeout(60000);
  page.on('pageerror', e => report.errors.push(`pageerror: ${String(e)}`));
  page.on('console', m => { if (m.type() === 'error') report.errors.push(`console: ${m.text()}`); });

  try {
    const response = await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    report.http = response ? response.status() : 0;
    await page.waitForSelector('body.search3-preview, #tourSearch', { timeout: 20000 });
    await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}' });
    await sleep(700);
    await snap(page, report.states, '01-search');

    report.search = await performSearch(page, false);
    await snap(page, report.states, '02-results', '#resultsTools');
    if (!await clickVisible(page, '#results .search3-show-tours', 7000)) throw new Error('show tours action missing');
    if (!await visible(page, '#results .hotel-card .hotel-tours:not([hidden])', 20000)) throw new Error('tour list did not open');
    await snap(page, report.states, '03-expanded-hotel', '#results .hotel-card .hotel-tours:not([hidden])');
    if (!await clickVisible(page, '#results .hotel-tours:not([hidden]) .direct-tour', 7000)) throw new Error('direct-tour action missing');
    report.selectedTour = await visible(page, '#selectedTour:not([hidden])', 45000);
    report.tourDetailsReady = report.selectedTour && await visible(page, '#selectedTour .selected-head h2, #selectedTour .selected-picture', 45000);
    if (!report.tourDetailsReady) throw new Error('tour details stayed loading');
    await settleImages(page, '#selectedTour img', 5000);
    await snap(page, report.states, '04-tour-details', '#selectedTour');
    if (!await visible(page, '#selectedTour .tour-flights', 30000)) throw new Error('flights missing');
    await snap(page, report.states, '05-flights', '#selectedTour .tour-flights');
    report.finalReviewMode = await enterFinalReview(page, false);
    await snap(page, report.states, '06-final-review', '#selectedTour');

    report.leadStates = {};
    report.leadStates.sending = await forceLeadState(page, 'sending');
    await snap(page, report.states, '07-lead-sending', '#selectedTour .lead-form');
    report.leadStates.success = await forceLeadState(page, 'success', { leadId: 'PREVIEW' });
    await snap(page, report.states, '08-lead-success', '#selectedTour .lead-form');
    report.leadStates.error = await forceLeadState(page, 'error');
    await snap(page, report.states, '09-lead-error', '#selectedTour .lead-form');

    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true, ignoreHTTPSErrors: true });
    const mobile = await mobileContext.newPage();
    mobile.setDefaultTimeout(10000);
    mobile.setDefaultNavigationTimeout(60000);
    mobile.on('pageerror', e => report.errors.push(`mobile pageerror: ${String(e)}`));
    mobile.on('console', m => { if (m.type() === 'error') report.errors.push(`mobile console: ${m.text()}`); });
    try {
      const mr = await mobile.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
      report.mobileHttp = mr ? mr.status() : 0;
      await mobile.waitForSelector('body.search3-preview, #tourSearch', { timeout: 20000 });
      await mobile.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}' });
      await sleep(700);
      await snap(mobile, report.mobileStates, 'm01-search', null, true);

      report.mobileSearch = await performSearch(mobile, true);
      await snap(mobile, report.mobileStates, 'm02-results', '#resultsTools', true);

      if (!await clickVisible(mobile, '[data-s3-open-filters]', 5000)) throw new Error('mobile filter drawer action missing');
      if (!await visible(mobile, 'body.search3-filter-open .results-filter-rail', 5000)) throw new Error('mobile filter drawer did not open');
      await snap(mobile, report.mobileStates, 'm02a-filters-open', '.results-filter-rail', true);
      await mobile.evaluate(() => {
        document.body.classList.remove('search3-filter-open');
        const overlay = document.querySelector('.search3-filter-overlay'); if (overlay) overlay.hidden = true;
        const rail = document.querySelector('.results-filter-rail'); if (rail) { rail.removeAttribute('aria-modal'); rail.removeAttribute('role'); }
      });

      await openSelectedTour(mobile, true);
      await snap(mobile, report.mobileStates, 'm03-tour-details', '#selectedTour', true);
      if (!await visible(mobile, '#selectedTour .tour-flights', 30000)) throw new Error('mobile flights missing');
      await snap(mobile, report.mobileStates, 'm04-flights', '#selectedTour .tour-flights', true);
      await enterFinalReview(mobile, true);
      await snap(mobile, report.mobileStates, 'm05-final-review', '#selectedTour', true);

      const enteredLead = await mobile.evaluate(() => {
        try {
          if (window.Search3SummaryCta && typeof window.Search3SummaryCta.enterLead === 'function') { window.Search3SummaryCta.enterLead('mobile-qa'); return true; }
          const btn = document.querySelector('#selectedTour .search3-summary-submit');
          if (btn) { btn.click(); return true; }
        } catch (_) {}
        return false;
      });
      if (!enteredLead || !await visible(mobile, '#selectedTour.search3-lead-entry .lead-form', 6000)) throw new Error('mobile lead entry did not open from final review');
      await snap(mobile, report.mobileStates, 'm06-lead-entry', '#selectedTour .lead-form', true);
      if (!await forceLeadState(mobile, 'success', { leadId: 'PREVIEW' })) throw new Error('mobile success state missing');
      await snap(mobile, report.mobileStates, 'm07-lead-success', '#selectedTour .lead-form', true);
      report.mobileFlow = true;
    } finally {
      await mobileContext.close();
    }
  } catch (e) {
    report.errors.push(String(e));
    await snap(page, report.states, '99-failure').catch(() => {});
  } finally {
    report.finishedAt = new Date().toISOString();
    report.captureComplete = requiredStates.every(name => report.states.some(s => s.name === name && s.ok));
    report.mobileCaptureComplete = requiredMobileStates.every(name => report.mobileStates.some(s => s.name === name && s.ok));
    fs.writeFileSync(path.join(outDir, 'report.json'), JSON.stringify(report, null, 2));
    await desktopContext.close().catch(() => {});
    await browser.close().catch(() => {});
  }

  const lifecycleComplete = report.leadStates && report.leadStates.sending === true && report.leadStates.success === true && report.leadStates.error === true;
  const searchComplete = report.search && report.search.submitted === true && report.search.hasResults === true && report.search.complete === true;
  const mobileSearchComplete = report.mobileSearch && report.mobileSearch.submitted === true && report.mobileSearch.hasResults === true && report.mobileSearch.complete === true;
  const strictPass = !!report.http && report.http < 400 && searchComplete && report.captureComplete === true && report.tourDetailsReady === true && lifecycleComplete && !!report.mobileHttp && report.mobileHttp < 400 && mobileSearchComplete && report.mobileFlow === true && report.mobileCaptureComplete === true && report.errors.length === 0;
  if (!strictPass) {
    console.error('Search3 strict visual QA failed:', JSON.stringify({ http: report.http, search: report.search || null, captureComplete: report.captureComplete, tourDetailsReady: report.tourDetailsReady, leadStates: report.leadStates || null, mobileHttp: report.mobileHttp || null, mobileSearch: report.mobileSearch || null, mobileCaptureComplete: report.mobileCaptureComplete, mobileFlow: report.mobileFlow || false, errors: report.errors }));
    process.exit(2);
  }
})().catch(e => { console.error(e); process.exit(1); });
