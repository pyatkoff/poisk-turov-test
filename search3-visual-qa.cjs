const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.SEARCH3_PREVIEW_URL || 'https://anytoour.ru/_preview/search3/poisk-turov/';
const outDir = process.env.SEARCH3_QA_OUT || 'search3-visual-artifacts';
fs.mkdirSync(outDir, { recursive: true });

const report = { url: baseUrl, startedAt: new Date().toISOString(), states: [], errors: [] };
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
        if (await loc.count()) await loc.scrollIntoViewIfNeeded().catch(() => {});
        await sleep(250);
      } else await page.evaluate(() => scrollTo(0, 0));
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

  async function forceLeadState(state, detail = {}) {
    const event = state === 'sending' ? 'lead-started' : state === 'success' ? 'lead-success' : 'lead-error';
    await page.evaluate(({ event, detail }) => window.dispatchEvent(new CustomEvent('v2:' + event, { detail: Object.assign({ previewSimulation: true }, detail) })), { event, detail });
    try {
      await page.waitForFunction(expected => {
        const form = document.querySelector('#selectedTour .lead-form');
        return !!form && form.dataset.search3LeadState === expected;
      }, state, { timeout: 3000 });
      return true;
    } catch (_) {
      const actual = await page.locator('#selectedTour .lead-form').first().getAttribute('data-search3-lead-state').catch(() => null);
      report.errors.push(`lead state mismatch: expected ${state}, got ${actual || 'none'}`);
      return false;
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
      report.search = { submitted: true, hasResults };
      await sleep(800);
      await snap('02-results', '#resultsTools');

      if (hasResults) {
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
  } catch (e) {
    report.errors.push(String(e));
    await snap('99-failure').catch(() => {});
  } finally {
    report.finishedAt = new Date().toISOString();
    report.captureComplete = ['01-search','02-results','03-expanded-hotel','04-tour-details','05-flights','06-final-review','07-lead-sending','08-lead-success','09-lead-error'].every(name => report.states.some(s => s.name === name && s.ok));
    fs.writeFileSync(path.join(outDir, 'report.json'), JSON.stringify(report, null, 2));
    await browser.close();
  }
  if (!report.http || report.http >= 400) process.exit(2);
})().catch(e => { console.error(e); process.exit(1); });
