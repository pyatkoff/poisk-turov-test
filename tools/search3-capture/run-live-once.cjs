'use strict';
// One owner-authorized native Search3 session. No API calls are synthesized.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { chromium } = require('playwright');
const { attachRecorder } = require('./recorder.cjs');
const operation = 'search3-live-capture-20261110-v2';
const origin = 'https://anytoour.ru';
const prefix = '/_preview/search3-early-intent-candidate/';
const pageURL = origin + prefix + 'poisk-turov/';
const sourceSha = '041b339df4fcddbea8d015919a9de5a0bf3039aa';
const pins = {
  'search-lifecycle-v6.js': '06a5697a5335457f9b217bb2f7a2d43f7b41ee6a8960081ab778e8427e55fdde',
  'catalogs-v2.js': 'e64de8426f95b6d199a8c4370a1228bd963f09f7e5b50acaac2e5bb78b55d961',
  'tour-controller-v4.js': '0e16a6bda3fdaeb765b4ea99c7c7a8ee66a4ceff416ffd5c821c7762733213e8'
};
const trip = { from: '1', country: '4', dateFrom: '2026-11-18', dateTo: '2026-11-24',
  daysFrom: '7', daysTill: '7', count_people: '2', child_count: '0' };
const actions = ['search_start', 'search_continue', 'search_status', 'search_results', 'tour', 'flights'];
const started = Date.now();
let browser, context, page, recorder, root, failure = null, failureDetails = null, phase = 'admission';
let firstId = null, selectedId = null, terminal = false, browserStartedAt = null, browserClosedAt = null;
let submitClicks = 0, tourClicks = 0;
const counts = Object.fromEntries(actions.map(a => [a, 0]));
const captured = new Set(), sourceReads = [], viewports = [], observers = new Set();
const hash = b => crypto.createHash('sha256').update(b).digest('hex');
const save = (name, data) => fs.writeFileSync(path.join(root, name), JSON.stringify(data, null, 2) + '\n', { flag: 'wx', mode: 0o600 });
const fixedError = code => { const e = new Error(code); e.code = code; return e; };
function stop(code) { if (!failure) failure = code; if (context) void context.close().catch(() => {}); }
function describe(request) {
  let u; try { u = new URL(request.url()); } catch { return null; }
  const action = u.searchParams.get('action');
  return u.origin === origin && u.pathname === '/api-v2.php' && actions.includes(action)
    ? { action, searchId: u.searchParams.get('searchId'), tourId: u.searchParams.get('tourId') } : null;
}
function onRequest(request) {
  const d = describe(request); if (!d) return;
  counts[d.action]++;
  if (d.action === 'search_start' && (phase !== 'submitted' || counts.search_start !== 1)) stop('UNEXPECTED_SEARCH_START');
  if (d.action === 'search_continue') stop('UNEXPECTED_SEARCH_CONTINUE');
  if (['tour', 'flights'].includes(d.action) && (counts[d.action] > 1 || !selectedId || d.tourId !== selectedId)) stop('UNEXPECTED_TOUR_REQUEST');
  if (firstId && ['search_status', 'search_results'].includes(d.action) && d.searchId !== firstId) stop('SEARCH_ID_CHANGED');
}
function onResponse(response) {
  const d = describe(response.request()); if (!d) return;
  const work = (async () => {
    if (!response.ok()) { stop('API_HTTP_FAILURE'); return; }
    let body; try { body = await response.json(); } catch { stop('API_BODY_UNREADABLE'); return; }
    if (d.action === 'search_start') {
      const id = String(body?.searchId || '');
      if (!/^[1-9][0-9]*$/.test(id)) { stop('SEARCH_ID_MISSING'); return; }
      if (firstId && firstId !== id) { stop('SEARCH_ID_CHANGED'); return; }
      firstId = id;
    }
    if (d.action === 'search_status' && d.searchId === firstId &&
        (Number(body?.progress) >= 100 || String(body?.status || '').toLowerCase() === 'complete')) terminal = true;
    if (['tour', 'flights'].includes(d.action) && d.tourId === selectedId) captured.add(d.action);
  })().catch(() => stop('OBSERVER_FAILURE'));
  observers.add(work); void work.finally(() => observers.delete(work));
}
async function until(predicate) {
  while (!predicate()) {
    if (failure) throw fixedError(failure);
    if (!context || page.isClosed()) throw fixedError('CONTEXT_CLOSED_BEFORE_COMPLETION');
    if (Date.now() - started > 270000) throw fixedError('SESSION_BUDGET_EXHAUSTED');
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  if (failure) throw fixedError(failure);
}
async function setField(name, value) {
  await page.evaluate(({ name, value }) => {
    const form = document.getElementById('tourSearch'), field = form?.elements.namedItem(name);
    if (!field || !('value' in field)) throw new Error('Native form field unavailable');
    if (String(field.value) === value) return;
    field.value = value;
    if (String(field.value) !== value) throw new Error('Native form option unavailable');
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }, { name, value });
}
async function viewport(width) {
  await page.setViewportSize({ width, height: width === 430 ? 932 : 720 });
  const facts = await page.evaluate(() => ({ width: innerWidth,
    documentWidth: document.documentElement.scrollWidth,
    cards: document.querySelectorAll('#results .hotel-card').length,
    selectedVisible: !!document.getElementById('selectedTour') && !document.getElementById('selectedTour').hidden,
    searchId: String(window.V2SearchLifecycle?.searchId || '') }));
  viewports.push(facts); recorder.checkpoint('viewport', facts);
}
(async () => {
  if (process.env.SEARCH3_LIVE_OPERATION !== operation || process.env.GITHUB_RUN_ATTEMPT !== '1' ||
      !/^[a-f0-9]{40}$/.test(process.env.CAPTURE_TOOL_SOURCE_SHA || '')) throw fixedError('LIVE_ADMISSION_MISSING');
  if (Date.now() > Date.parse('2026-11-11T23:59:00Z')) throw fixedError('DATED_AUTHORIZATION_EXPIRED');
  root = process.env.LIVE_EVIDENCE_DIR;
  if (!root || !path.isAbsolute(root)) throw fixedError('PRIVATE_EVIDENCE_PATH_REQUIRED');
  fs.mkdirSync(root, { mode: 0o700 });
  save('invocation.json', { operation, startedAt: new Date(started).toISOString(), sourceSha,
    toolSourceSha: process.env.CAPTURE_TOOL_SOURCE_SHA, runId: process.env.GITHUB_RUN_ID,
    runAttempt: 1, trip, pageURL, providerRulesChanged: false });
  process.once('SIGTERM', () => stop('EXTERNAL_TIME_LIMIT'));
  try {
    phase = 'published_source_read';
    for (const [file, expected] of Object.entries(pins)) {
      const response = await fetch(origin + prefix + file, { redirect: 'error', signal: AbortSignal.timeout(10000) });
      if (!response.ok) throw fixedError('PUBLISHED_SOURCE_HTTP_FAILURE');
      const bytes = Buffer.from(await response.arrayBuffer());
      const actual = hash(bytes); sourceReads.push({ file, status: response.status, sha256: actual });
      if (actual !== expected) throw fixedError('PUBLISHED_SOURCE_PIN_MISMATCH');
    }
    save('published-source-read.json', sourceReads);
    phase = 'browser_start'; browserStartedAt = Date.now();
    browser = await chromium.launch({ headless: true, timeout: 15000 });
    context = await browser.newContext({ viewport: { width: 430, height: 932 }, serviceWorkers: 'block' });
    page = await context.newPage(); page.setDefaultTimeout(10000); page.setDefaultNavigationTimeout(25000);
    recorder = attachRecorder(page, { directory: path.join(root, 'network'), apiOrigin: origin, sourceSha, deadlineMs: 240000 });
    recorder.arm(); page.on('request', onRequest); page.on('response', onResponse);
    phase = 'catalogs'; await page.goto(pageURL, { waitUntil: 'domcontentloaded' });
    if (!page.url().startsWith(pageURL) || new URL(page.url()).origin !== origin) throw fixedError('UNEXPECTED_PAGE_ROUTE');
    await page.waitForFunction(() => {
      const f = document.getElementById('tourSearch');
      return !!window.V2SearchLifecycle && !!window.V2Catalogs && f &&
        [...f.elements.namedItem('from').options].some(x => x.value === '1' && x.textContent.includes('Москва'));
    }, null, { timeout: 30000 });
    await setField('from', trip.from);
    await page.waitForFunction(() => {
      const f = document.getElementById('tourSearch'), c = f?.elements.namedItem('country');
      return c && !c.disabled && [...c.options].some(x => x.value === '4' && x.textContent.includes('Турция'));
    }, null, { timeout: 30000 });
    await setField('country', trip.country);
    for (const [name, value] of Object.entries(trip)) if (!['from', 'country'].includes(name)) await setField(name, value);
    await page.waitForFunction(() => {
      const f = document.getElementById('tourSearch'), b = f?.querySelector('button[type="submit"]');
      return f?.elements.namedItem('country').value === '4' && b && !b.disabled;
    }, null, { timeout: 15000 });
    const actualFields = await page.evaluate(names => Object.fromEntries(names.map(name =>
      [name, String(document.getElementById('tourSearch').elements.namedItem(name).value)])), Object.keys(trip));
    if (JSON.stringify(actualFields) !== JSON.stringify(trip) || counts.search_start !== 0) throw fixedError('FORM_PREFLIGHT_MISMATCH');
    save('form-before-submit.json', actualFields);
    if (failure) throw fixedError(failure);
    phase = 'submitted'; submitClicks++;
    await page.locator('#tourSearch button[type="submit"]').click();
    await until(() => firstId && terminal && counts.search_results > 0);
    await page.locator('#results .direct-tour[data-tid]').first().waitFor({ state: 'visible' });
    await viewport(430); await viewport(1280);
    selectedId = await page.locator('#results .direct-tour[data-tid]').first().getAttribute('data-tid');
    if (!/^[1-9][0-9]*$/.test(selectedId || '')) throw fixedError('NO_NUMERIC_TOURVISOR_OFFER');
    recorder.checkpoint('selected_tour', { tourId: selectedId });
    phase = 'selected_tour'; tourClicks++;
    await page.locator('#results .direct-tour[data-tid="' + selectedId + '"]').first().click();
    await until(() => captured.has('tour') && captured.has('flights'));
    await page.locator('#selectedTour').waitFor({ state: 'visible' });
    await viewport(1280); await viewport(430);
    const state = await page.evaluate(() => ({
      searchId: String(window.V2SearchLifecycle?.searchId || ''),
      fields: Object.fromEntries(['from', 'country', 'dateFrom', 'dateTo', 'daysFrom', 'daysTill', 'count_people', 'child_count']
        .map(n => [n, String(document.getElementById('tourSearch').elements.namedItem(n).value)])),
      selectedText: document.getElementById('selectedTour')?.innerText.slice(0,10000) || null
    }));
    save('selected-ui.json', state);
    if (state.searchId !== firstId || JSON.stringify(state.fields) !== JSON.stringify(trip)) throw fixedError('SEARCH_CONTEXT_CHANGED');
    phase = 'captured';
  } catch (error) {
    const safe = value => typeof value === 'string' && /^[A-Za-z0-9_.:-]{1,100}$/.test(value) ? value : null;
    failureDetails = { phase, name: safe(error.name), code: safe(error.code), causeName: safe(error.cause?.name), causeCode: safe(error.cause?.code) };
    if (!failure) failure = safe(error.code) || 'SESSION_INCOMPLETE_AT_' + phase.toUpperCase();
  }
  finally {
    let summary = null;
    if (recorder) { try { summary = await recorder.finish(); } catch { failure ||= 'RECORDER_FINISH_FAILED'; } }
    if (browser) {
      let timer;
      try { await Promise.race([browser.close().then(() => { browserClosedAt = Date.now(); }),
        new Promise((_, reject) => { timer = setTimeout(() => reject(fixedError('BROWSER_CLOSE_TIMEOUT')), 3000); })]); }
      catch { failure ||= 'BROWSER_CLOSE_FAILED'; } finally { clearTimeout(timer); }
    }
    if (!summary?.oneSuccessfulSearchStart || !summary?.sameIdUsedAfterStart || !summary?.noSearchContinueObserved ||
        !summary?.terminalStatusCaptured || !summary?.originalTourAndFlightsPairCaptured || summary?.responseCaptureErrors ||
        summary?.pendingBodies || !summary?.closedAt || summary?.contextCloseFailed) failure ||= 'INCOMPLETE_NETWORK_EVIDENCE';
    if (browserStartedAt && (!browserClosedAt || browserClosedAt - browserStartedAt > 300000)) failure ||= 'BROWSER_TIME_BOUND_UNPROVEN';
    const report = { status: failure ? 'LIVE_CAPTURE_INCOMPLETE' : 'LIVE_CAPTURE_COMPLETED', failure, operation,
      phase, failureDetails, generatedAt: new Date().toISOString(), sourceSha, toolSourceSha: process.env.CAPTURE_TOOL_SOURCE_SHA,
      startedAt: new Date(started).toISOString(), browserStartedAt, browserClosedAt, elapsedMs: Date.now() - started,
      browserElapsedMs: browserClosedAt && browserStartedAt ? browserClosedAt - browserStartedAt : null,
      submitClicks, tourClicks, browserRequestCounts: counts, firstSearchId: firstId, selectedTourId: selectedId,
      trip, sourceReads, viewports, recorderSummary: summary,
      limitations: ['Browser counts are not upstream provider calls or billing.',
        'No late-catalog delay was injected; the accepted race regression was not replayed.',
        'Raw tour/flights capture does not by itself validate supplier price semantics.',
        'Static source pins do not independently prove every executed asset.',
        'Closing a browser does not cancel already accepted upstream supplier work.'] };
    save('live-report.json', report);
    console.log(JSON.stringify({ status: report.status, phase, failure, submitClicks, tourClicks,
      elapsedMs: report.elapsedMs, browserElapsedMs: report.browserElapsedMs, evidence: 'private-encrypted-artifact' }));
    if (failure) process.exitCode = 1;
  }
})().catch(error => { console.error(JSON.stringify({ status: 'LIVE_ADMISSION_STOP', code: error.code || 'FIXED_PREFLIGHT_FAILURE' })); process.exitCode = 1; });
