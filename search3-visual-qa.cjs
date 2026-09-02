const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.SEARCH3_PREVIEW_URL || 'https://anytoour.ru/_preview/search3/poisk-turov/';
const outDir = process.env.SEARCH3_QA_OUT || 'search3-visual-artifacts';
fs.mkdirSync(outDir, { recursive: true });

const requiredStates = ['01-search','02-results','03-expanded-hotel','04-tour-details','05-flights','06-final-review','07-lead-sending','08-lead-success','09-lead-error'];
const requiredMobileStates = ['m01-search','m02-results','m03-tour-details','m04-flights','m05-final-review','m06-lead-entry','m07-lead-success'];
const report = { url: baseUrl, startedAt: new Date().toISOString(), states: [], mobileStates: [], errors: [] };
const sleep = ms => new Promise(r => setTimeout(r, ms));

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1, ignoreHTTPSErrors: true });
  const page = await context.newPage();
  page.on('pageerror', e => report.errors.push(`pageerror: ${String(e)}`));
  page.on('console', m => { if (m.type() === 'error') report.errors.push(`console: ${m.text()}`); });

  async function snap(name, anchor) {
    try {
      if (anchor) {
        const loc = page.locator(anchor).first();
        if (await loc.count()) {
          await loc.evaluate(el => {
            const y = el.getBoundingClientRect().top + window.scrollY - 12;
            window.scrollTo(0, Math.max(0, y));
          }).catch(() => {});
        }
        await sleep(250);
      } else {
        await page.evaluate(() => window.scrollTo(0, 0));
        await sleep(100);
      }
      const file = path.join(outDir, `${name}.png`);
      await page.screenshot({ path: file, fullPage: false, animations: 'disabled' });
      report.states.push({ name, ok: true, file, url: page.url() });
      return true;
    } catch (e) { report.states.push({ name, ok: false, error: String(e) }); return false; }
  }

  async function waitVisible(selector, timeout = 90000) {
    try { await page.locator(selector).first().waitFor({ state: 'visible', timeout }); return true; }
    catch (_) { return false; }
  }

  async function waitAttached(selector, timeout = 90000) {
    try { await page.locator(selector).first().waitFor({ state: 'attached', timeout }); return true; }
    catch (_) { return false; }
  }

  async function settleImages(selector, timeout = 8000) {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
      const pending = await page.locator(selector).evaluateAll(images => images.filter(img => img && img.tagName === 'IMG' && img.src && !img.complete).length).catch(() => 0);
      if (!pending) return true;
      await sleep(180);
    }
    return false;
  }

  async function forceLeadState(state, detail = {}) {
    const result = await page.evaluate(({ state, detail }) => {
      const form = document.querySelector('#selectedTour .lead-form');
      if (!form) return { ok: false, actual: null, reason: 'form-missing' };
      delete form.dataset.search3LeadState;
      window.dispatchEvent(new CustomEvent('search3:preview-lead-state', {
        detail: Object.assign({ previewSimulation: true, state }, detail)
      }));
      return {
        ok: form.dataset.search3LeadState === state,
        actual: form.dataset.search3LeadState || null,
        visible: !!form.querySelector('.search3-lead-status:not([hidden])')
      };
    }, { state, detail });
    if (!result.ok) {
      report.errors.push(`lead state mismatch: expected ${state}, got ${result.actual || 'none'}${result.reason ? ` (${result.reason})` : ''}`);
      return false;
    }
    if (!result.visible) {
      report.errors.push(`lead state ${state} marker changed but status UI is hidden`);
      return false;
    }
    try {
      await page.locator(`#selectedTour .lead-form[data-search3-lead-state="${state}"] .search3-lead-status`).first().waitFor({ state: 'visible', timeout: 3000 });
      return true;
    } catch (_) {
      report.errors.push(`lead state ${state} marker changed but status UI is not visible`);
      return false;
    }
  }

  async function runMobileQa() {
    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true, ignoreHTTPSErrors: true });
    const mobile = await mobileContext.newPage();
    mobile.on('pageerror', e => report.errors.push(`mobile pageerror: ${String(e)}`));
    mobile.on('console', m => { if (m.type() === 'error') report.errors.push(`mobile console: ${m.text()}`); });
    const mWaitVisible = async (selector, timeout = 90000) => { try { await mobile.locator(selector).first().waitFor({ state:'visible', timeout }); return true; } catch (_) { return false; } };
    const mWaitAttached = async (selector, timeout = 90000) => { try { await mobile.locator(selector).first().waitFor({ state:'attached', timeout }); return true; } catch (_) { return false; } };
    const mSettleImages = async (selector, timeout = 6000) => {
      const deadline = Date.now() + timeout;
      while (Date.now() < deadline) {
        const pending = await mobile.locator(selector).evaluateAll(images => images.filter(img => img && img.tagName === 'IMG' && img.src && !img.complete).length).catch(() => 0);
        if (!pending) return true;
        await sleep(160);
      }
      return false;
    };
    const mOverflowOk = async name => {
      const state = await mobile.evaluate(() => ({ width: innerWidth, scrollWidth: document.documentElement.scrollWidth, bodyWidth: document.body ? document.body.scrollWidth : 0 }));
      const ok = Math.max(state.scrollWidth, state.bodyWidth) <= state.width + 2;
      if (!ok) report.errors.push(`mobile horizontal overflow at ${name}: viewport=${state.width}, document=${state.scrollWidth}, body=${state.bodyWidth}`);
      return ok;
    };
    const mSnap = async (name, anchor) => {
      try {
        if (anchor) {
          const loc = mobile.locator(anchor).first();
          if (await loc.count()) await loc.evaluate(el => { const y=el.getBoundingClientRect().top+scrollY-8; scrollTo(0,Math.max(0,y)); }).catch(()=>{});
          await sleep(220);
        } else {
          await mobile.evaluate(() => scrollTo(0,0));
          await sleep(100);
        }
        await mOverflowOk(name);
        const file = path.join(outDir, `${name}.png`);
        await mobile.screenshot({ path:file, fullPage:false, animations:'disabled' });
        report.mobileStates.push({ name, ok:true, file, url:mobile.url() });
        return true;
      } catch (e) { report.mobileStates.push({ name, ok:false, error:String(e) }); return false; }
    };
    try {
      const response = await mobile.goto(baseUrl, { waitUntil:'domcontentloaded', timeout:60000 });
      report.mobileHttp = response ? response.status() : 0;
      await mobile.waitForSelector('body.search3-preview, #tourSearch', { timeout:20000 });
      await mobile.addStyleTag({ content:'*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}' });
      await sleep(900);
      await mSnap('m01-search');

      const submit = mobile.locator('#tourSearch .search-submit').first();
      if (!await submit.count()) throw new Error('mobile search submit missing');
      await submit.click();
      const hasResults = await mWaitVisible('#results .hotel-card', 120000);
      const complete = hasResults ? await mWaitAttached('#status .search-progress-done', 120000) : false;
      report.mobileSearch = { submitted:true, hasResults, complete };
      if (!hasResults || !complete) throw new Error('mobile search did not reach completed results state');
      await mSettleImages('#results .hotel-card img', 6000);
      await mSnap('m02-results', '#resultsTools');

      const showTours = mobile.locator('#results .search3-show-tours').first();
      if (await showTours.count()) {
        await showTours.click();
        await mWaitVisible('#results .hotel-card .hotel-tours:not([hidden])', 30000);
      }
      const direct = mobile.locator('#results .hotel-tours:not([hidden]) .direct-tour').first();
      if (!await direct.count()) throw new Error('mobile direct-tour action missing');
      await direct.click();
      if (!await mWaitVisible('#selectedTour:not([hidden])', 60000)) throw new Error('mobile selected tour did not open');
      if (!await mWaitVisible('#selectedTour .selected-head h2, #selectedTour .selected-picture', 60000)) throw new Error('mobile tour details stayed loading');
      await mSettleImages('#selectedTour img', 5000);
      await mSnap('m03-tour-details', '#selectedTour');

      if (!await mWaitVisible('#selectedTour .tour-flights', 45000)) throw new Error('mobile flights missing');
      await sleep(500);
      await mSnap('m04-flights', '#selectedTour .tour-flights');

      const cont = mobile.locator('#selectedTour .search3-flight-continue button').first();
      if (!await cont.count()) throw new Error('mobile flight continue missing');
      await cont.click();
      if (!await mWaitVisible('#selectedTour.search3-final-review', 20000)) throw new Error('mobile final review did not open');
      await sleep(350);
      await mSnap('m05-final-review', '#selectedTour');

      const summarySubmit = mobile.locator('#selectedTour .search3-summary-submit').first();
      if (await summarySubmit.count()) await summarySubmit.click();
      else await mobile.evaluate(() => window.Search3SummaryCta && window.Search3SummaryCta.enterLead && window.Search3SummaryCta.enterLead('mobile-qa'));
      if (!await mWaitVisible('#selectedTour.search3-lead-entry .lead-form', 5000)) throw new Error('mobile lead entry did not open from final review');
      await mSnap('m06-lead-entry', '#selectedTour .lead-form');

      const leadState = await mobile.evaluate(() => {
        const form=document.querySelector('#selectedTour .lead-form');
        if(!form)return false;
        window.dispatchEvent(new CustomEvent('search3:preview-lead-state',{detail:{previewSimulation:true,state:'success',leadId:'PREVIEW'}}));
        return form.dataset.search3LeadState==='success';
      });
      if (!leadState || !await mWaitVisible('#selectedTour .lead-form[data-search3-lead-state="success"] .search3-lead-status', 5000)) throw new Error('mobile success state missing');
      await mSnap('m07-lead-success', '#selectedTour .lead-form');
      report.mobileFlow = true;
    } catch (e) {
      report.errors.push(`mobile: ${String(e)}`);
      try { await mSnap('m99-failure'); } catch (_) {}
    } finally {
      await mobileContext.close();
    }
  }

  try {
    const response = await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    report.http = response ? response.status() : 0;
    await page.waitForSelector('body.search3-preview, #tourSearch', { timeout: 20000 });
    await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}' });
    await sleep(1200);
    await snap('01-search');

    const submit = page.locator('#tourSearch .search-submit').first();
    if (await submit.count()) {
      await submit.click();
      const hasResults = await waitVisible('#results .hotel-card', 120000);
      const searchComplete = hasResults ? await waitAttached('#status .search-progress-done', 120000) : false;
      report.search = { submitted: true, hasResults, complete: searchComplete };
      if (hasResults && !searchComplete) report.errors.push('results became visible but search never reached completed state before screenshots');
      if (hasResults) await settleImages('#results .hotel-card img', 8000);
      await sleep(350);
      await snap('02-results', '#resultsTools');

      if (hasResults && searchComplete) {
        const showTours = page.locator('#results .search3-show-tours').first();
        if (await showTours.count()) {
          await showTours.click();
          await waitVisible('#results .hotel-card .hotel-tours:not([hidden])', 30000);
          await sleep(500);
          await snap('03-expanded-hotel', '#results .hotel-card .hotel-tours:not([hidden])');
        }

        const direct = page.locator('#results .hotel-tours:not([hidden]) .direct-tour').first();
        if (await direct.count()) {
          await direct.click();
          const selected = await waitVisible('#selectedTour:not([hidden])', 60000);
          report.selectedTour = selected;
          if (selected) {
            const detailReady = await waitVisible('#selectedTour .selected-head h2, #selectedTour .selected-picture', 60000);
            report.tourDetailsReady = detailReady;
            if (!detailReady) report.errors.push('tour details stayed in loading state before screenshot');
            await settleImages('#selectedTour img', 5000);
            await sleep(500);
            await snap('04-tour-details', '#selectedTour');

            await waitVisible('#selectedTour .tour-flights', 45000);
            await sleep(700);
            await snap('05-flights', '#selectedTour .tour-flights');

            let finalMode = 'preview-simulation';
            const cont = page.locator('#selectedTour .search3-flight-continue button').first();
            if (await cont.count() && await cont.isVisible().catch(() => false)) {
              await cont.click();
              if (await waitVisible('#selectedTour.search3-final-review', 20000)) finalMode = 'real-continue';
            }
            if (finalMode !== 'real-continue') {
              await page.evaluate(() => {
                const root = document.getElementById('selectedTour');
                if (root) root.classList.add('search3-final-review');
                window.dispatchEvent(new CustomEvent('v2:booking-review', { detail: { active: true, previewSimulation: true } }));
              });
              await waitVisible('#selectedTour.search3-final-review', 5000);
            }
            report.finalReviewMode = finalMode;
            await settleImages('#selectedTour img', 3000);
            await sleep(400);
            await snap('06-final-review', '#selectedTour');

            const leadForm = page.locator('#selectedTour .lead-form').first();
            if (await leadForm.count()) {
              report.leadStates = {};
              report.leadStates.sending = await forceLeadState('sending');
              await sleep(250); await snap('07-lead-sending', '#selectedTour .lead-form');
              report.leadStates.success = await forceLeadState('success', { leadId: 'PREVIEW' });
              await sleep(250); await snap('08-lead-success', '#selectedTour .lead-form');
              report.leadStates.error = await forceLeadState('error');
              await sleep(250); await snap('09-lead-error', '#selectedTour .lead-form');
            } else report.errors.push('lead form missing after final review');
          }
        }
      }
    } else report.search = { submitted: false, reason: 'search submit missing' };

    await runMobileQa();
  } catch (e) {
    report.errors.push(String(e));
    await snap('99-failure').catch(() => {});
  } finally {
    report.finishedAt = new Date().toISOString();
    report.captureComplete = requiredStates.every(name => report.states.some(s => s.name === name && s.ok));
    report.mobileCaptureComplete = requiredMobileStates.every(name => report.mobileStates.some(s => s.name === name && s.ok));
    fs.writeFileSync(path.join(outDir, 'report.json'), JSON.stringify(report, null, 2));
    await browser.close();
  }

  const lifecycleComplete = report.leadStates && report.leadStates.sending === true && report.leadStates.success === true && report.leadStates.error === true;
  const searchComplete = report.search && report.search.submitted === true && report.search.hasResults === true && report.search.complete === true;
  const mobileSearchComplete = report.mobileSearch && report.mobileSearch.submitted === true && report.mobileSearch.hasResults === true && report.mobileSearch.complete === true;
  const strictPass = !!report.http && report.http < 400 && searchComplete && report.captureComplete === true && report.tourDetailsReady === true && lifecycleComplete && !!report.mobileHttp && report.mobileHttp < 400 && mobileSearchComplete && report.mobileFlow === true && report.mobileCaptureComplete === true && report.errors.length === 0;
  if (!strictPass) {
    console.error('Search3 strict visual QA failed:', JSON.stringify({
      http: report.http,
      search: report.search || null,
      captureComplete: report.captureComplete,
      tourDetailsReady: report.tourDetailsReady,
      leadStates: report.leadStates || null,
      mobileHttp: report.mobileHttp || null,
      mobileSearch: report.mobileSearch || null,
      mobileCaptureComplete: report.mobileCaptureComplete,
      mobileFlow: report.mobileFlow || false,
      errors: report.errors
    }));
    process.exit(2);
  }
})().catch(e => { console.error(e); process.exit(1); });