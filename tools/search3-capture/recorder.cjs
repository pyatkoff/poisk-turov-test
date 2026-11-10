'use strict';
// An observer for an already authorized, isolated Playwright page.
// This module never launches a browser, navigates, submits, retries or calls an API.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const ACTIONS = new Set(['search_start', 'search_continue', 'search_status', 'search_results', 'tour', 'flights']);
const LIMIT = 2 * 1024 * 1024;
const PRIVATE_KEY = /authorization|cookie|token|password|secret|csrf|session|email|phone|contact|booking/i;
const sha256 = bytes => crypto.createHash('sha256').update(bytes).digest('hex');

function scrub(value, removed, trail = '$', depth = 0) {
  if (depth > 40) { removed.push(trail); return '[depth limit]'; }
  if (Array.isArray(value)) return value.map((v, i) => scrub(v, removed, trail + '[' + i + ']', depth + 1));
  if (value && typeof value === 'object') {
    const out = Object.create(null);
    for (const [key, item] of Object.entries(value)) {
      if (PRIVATE_KEY.test(key)) { removed.push(trail + '.' + key); continue; }
      out[key] = scrub(item, removed, trail + '.' + key, depth + 1);
    }
    return out;
  }
  return value;
}

function identifier(value) {
  if (typeof value === 'number') return Number.isSafeInteger(value) && value > 0 ? String(value) : null;
  return typeof value === 'string' && /^[1-9][0-9]*$/.test(value) ? value : null;
}

function describe(request, apiOrigin, apiPath) {
  let url;
  try { url = new URL(request.url()); } catch { return null; }
  const action = url.searchParams.get('action');
  if (url.origin !== apiOrigin || url.pathname !== apiPath || !ACTIONS.has(action)) return null;
  return {
    action, method: request.method(), endpoint: url.pathname,
    searchId: identifier(url.searchParams.get('searchId')),
    tourId: identifier(url.searchParams.get('tourId'))
  };
}

function attachRecorder(page, options) {
  const { directory, apiOrigin, apiPath = '/api-v2.php', sourceSha, deadlineMs = 300000 } = options;
  if (!/^[a-f0-9]{40}$/.test(sourceSha || '')) throw new Error('Exact source SHA required');
  if (new URL(apiOrigin).origin !== apiOrigin) throw new Error('API origin must be exact');
  if (!Number.isInteger(deadlineMs) || deadlineMs < 1 || deadlineMs > 300000) throw new Error('Deadline must not exceed five minutes');
  // Refuse to overwrite a prior run. Run directories must be distinct, never reused.
  fs.mkdirSync(directory, { mode: 0o700 });
  const records = [], bodies = new Map(), requestIds = new WeakMap(), pending = new Set();
  const context = page.context();
  let armedAt = null, timer = null, stopReason = null, sealed = false, finishPromise = null;
  let closePromise = null, closeRequestedAt = null, closedAt = null, contextCloseFailed = false;
  let responseCaptureErrors = 0, unmatchedResponses = 0, lateEvents = 0;
  const journal = path.join(directory, 'network.ndjson');
  fs.writeFileSync(journal, '', { flag: 'wx', mode: 0o600 });
  function append(event) {
    if (sealed) { lateEvents++; return; }
    fs.appendFileSync(journal, JSON.stringify({ at: new Date().toISOString(), ...event }) + '\n');
  }
  function closeContext(reason) {
    if (sealed) return Promise.resolve();
    if (!stopReason) stopReason = reason;
    if (!closePromise) {
      closeRequestedAt = Date.now();
      append({ kind: 'context_close_requested', reason });
      closePromise = Promise.resolve().then(() => context.close()).then(() => {
        closedAt = Date.now();
        append({ kind: 'context_closed' });
      }, () => {
        contextCloseFailed = true;
        append({ kind: 'context_close_failed' });
      });
    }
    return closePromise;
  }
  async function bounded(promise, milliseconds) {
    let timeout;
    try {
      await Promise.race([promise, new Promise(resolve => { timeout = setTimeout(resolve, milliseconds); })]);
    } finally { clearTimeout(timeout); }
  }
  function arm() {
    if (armedAt !== null) return;
    armedAt = Date.now();
    append({ kind: 'armed', deadlineMs });
    timer = setTimeout(() => {
      stopReason = 'deadline';
      append({ kind: 'deadline', elapsedMs: Date.now() - armedAt });
      // Closing the context stops this run, not another user's browser.
      void closeContext('deadline');
    }, deadlineMs);
  }
  function onRequest(request) {
    const meta = describe(request, apiOrigin, apiPath);
    if (!meta) return;
    const record = { seq: records.length + 1, ...meta, httpStatus: null, responseCaptured: false, failed: false };
    records.push(record);
    requestIds.set(request, record);
    append({ kind: 'request', ...record });
    if (meta.action === 'search_start') arm();
  }
  function onFailed(request) {
    const record = requestIds.get(request);
    if (!record) return;
    record.failed = true;
    // Do not persist arbitrary browser error text, headers or URLs.
    append({ kind: 'requestfailed', seq: record.seq, action: record.action });
  }
  function onResponse(response) {
    const request = response.request();
    if (!describe(request, apiOrigin, apiPath)) return;
    const record = requestIds.get(request);
    if (!record) { unmatchedResponses++; append({ kind: 'unmatched_response' }); return; }
    record.httpStatus = response.status();
    const work = (async () => {
      if (record.httpStatus < 200 || record.httpStatus >= 300) {
        append({ kind: 'http_error', seq: record.seq, status: record.httpStatus });
        return;
      }
      try {
        const size = Number(await response.headerValue('content-length'));
        if (Number.isFinite(size) && size > LIMIT) throw new Error('oversize');
        const bytes = await response.body();
        if (sealed) return;
        if (bytes.length > LIMIT) throw new Error('oversize');
        const body = JSON.parse(bytes.toString('utf8'));
        const removed = [], clean = scrub(body, removed);
        const file = String(record.seq).padStart(3, '0') + '-' + record.action + '.json';
        fs.writeFileSync(path.join(directory, file), JSON.stringify(clean, null, 2) + '\n', { flag: 'wx', mode: 0o600 });
        bodies.set(record.seq, body);
        record.responseCaptured = true;
        append({ kind: 'response', seq: record.seq, action: record.action, status: record.httpStatus,
          bytes: bytes.length, originalBodySha256: sha256(bytes), file, redactedPaths: removed });
      } catch {
        if (sealed) return;
        responseCaptureErrors++;
        append({ kind: 'capture_failed', seq: record.seq, action: record.action });
      }
    })();
    pending.add(work);
    void work.finally(() => pending.delete(work));
  }
  function checkpoint(label, facts) {
    if (!['viewport', 'selected_tour', 'catalogs_released'].includes(label)) throw new Error('Unknown checkpoint');
    append({ kind: 'checkpoint', label, facts: scrub(facts, []) });
  }
  function summary() {
    const starts = records.filter(r => r.action === 'search_start');
    const firstSearchId = starts.length ? identifier(bodies.get(starts[0].seq)?.searchId) : null;
    const uses = records.filter(r => ['search_continue', 'search_status', 'search_results'].includes(r.action));
    const unexpectedSearchIds = firstSearchId ? uses.filter(r => r.searchId !== firstSearchId).map(r => r.seq) : [];
    const terminal = records.filter(r => r.action === 'search_status' && r.searchId === firstSearchId
      && r.responseCaptured && (Number(bodies.get(r.seq)?.progress) >= 100
        || String(bodies.get(r.seq)?.status || '').toLowerCase() === 'complete'));
    const tours = records.filter(r => r.action === 'tour');
    const flights = records.filter(r => r.action === 'flights');
    const ownTourId = tours.length === 1 ? tours[0].tourId : null;
    const pair = ownTourId && flights.length === 1 && flights[0].tourId === ownTourId
      && tours[0].responseCaptured && flights[0].responseCaptured
      && identifier(bodies.get(tours[0].seq)?.id) === ownTourId;
    return {
      kind: 'Search3 network observation', sourceSha, apiOrigin, apiPath,
      stopReason: stopReason || 'explicit_finish', deadlineMs, armedAt,
      closeRequestedAt, closedAt, contextCloseFailed,
      elapsedMs: armedAt === null ? null : Date.now() - armedAt,
      requestCounts: Object.fromEntries([...ACTIONS].map(a => [a, records.filter(r => r.action === a).length])),
      firstSearchId, unexpectedSearchIdRequests: unexpectedSearchIds,
      oneSuccessfulSearchStart: starts.length === 1 && starts[0].responseCaptured && firstSearchId !== null,
      noSearchContinueObserved: !records.some(r => r.action === 'search_continue'),
      sameIdUsedAfterStart: firstSearchId !== null && uses.length > 0 && unexpectedSearchIds.length === 0,
      terminalStatusCaptured: terminal.length > 0,
      selectedTourId: ownTourId, originalTourAndFlightsPairCaptured: Boolean(pair),
      responseCaptureErrors, unmatchedResponses, lateEvents, pendingBodies: pending.size,
      limitations: [
        'Browser request events do not measure upstream retries, billing or all provider traffic.',
        'Missing fields, null and zero are preserved; capture does not validate supplier price semantics.',
        'No late-catalog reproduction, viewport or UI result is implied without its explicit evidence.',
        'Price comparison must use the selected variant from the captured response, not an unrelated cached offer.'
      ]
    };
  }
  async function finish() {
    if (finishPromise) return finishPromise;
    finishPromise = (async () => {
      // Keep the deadline active while draining bodies. A stuck body or browser
      // close must produce an incomplete receipt, never an unbounded wait.
      // Keep observing until close finishes: requests can begin while earlier
      // response bodies drain or while the context is still closing.
      await bounded(Promise.allSettled([...pending]), 2000);
      await bounded(closeContext('explicit_finish'), 2000);
      await bounded(Promise.allSettled([...pending]), 100);
      page.off('request', onRequest);
      page.off('response', onResponse);
      page.off('requestfailed', onFailed);
      clearTimeout(timer);
      const result = summary();
      append({ kind: 'finished', summary: result });
      fs.writeFileSync(path.join(directory, 'summary.json'), JSON.stringify(result, null, 2) + '\n', { flag: 'wx', mode: 0o600 });
      sealed = true;
      return result;
    })();
    return finishPromise;
  }
  page.on('request', onRequest);
  page.on('response', onResponse);
  page.on('requestfailed', onFailed);
  return { arm, checkpoint, finish };
}
module.exports = { attachRecorder };
