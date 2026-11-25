'use strict';
// One separately authorized static GET. No browser or supplier endpoint.
const fs = require('node:fs'), crypto = require('node:crypto');
const url = 'https://anytoour.ru/_preview/search3-early-intent-candidate/search-lifecycle-v6.js';
const expected = '06a5697a5335457f9b217bb2f7a2d43f7b41ee6a8960081ab778e8427e55fdde';
const safe = x => typeof x === 'string' && /^[A-Za-z0-9_.:-]{1,100}$/.test(x) ? x : null;
(async () => {
  if (process.env.SEARCH3_STATIC_OPERATION !== 'search3-static-read-20261110-v1' || process.env.GITHUB_RUN_ATTEMPT !== '1') throw Error('ONE_ORIGINAL_ATTEMPT_REQUIRED');
  if (fs.existsSync('static-read-diagnostic.json')) throw Error('EXISTING_RECEIPT_NO_REPLAY');
  const report = { scope: 'ONE_STATIC_GET_ONLY', url, startedAt: new Date().toISOString(),
    redirectsFollowed: 0, browserLaunches: 0, supplierEndpointsCalled: 0,
    operation: process.env.SEARCH3_STATIC_OPERATION, runId: process.env.GITHUB_RUN_ID,
    headSha: process.env.DIAGNOSTIC_HEAD_SHA, nodeVersion: process.version, platform: process.platform, phase: 'fetch' };
  try {
    const response = await fetch(url, { redirect: 'manual', signal: AbortSignal.timeout(10000) });
    report.httpStatus = response.status;
    report.phase = 'response_headers';
    report.contentType = response.headers.get('content-type')?.slice(0,120) || null;
    const location = response.headers.get('location');
    if (location) {
      try { const u = new URL(location, url); report.redirect = { origin: u.origin, pathname: u.pathname, hasQuery: !!u.search }; }
      catch { report.redirect = 'unparseable'; }
    }
    if (response.status === 200) {
      report.phase = 'response_body';
      const bytes = Buffer.from(await response.arrayBuffer());
      report.bytes = bytes.length;
      report.sha256 = crypto.createHash('sha256').update(bytes).digest('hex');
      report.matchesAcceptedSource = report.sha256 === expected;
    } else if (response.body) await response.body.cancel();
  } catch (e) {
    report.error = { name: safe(e.name), code: safe(e.code), causeName: safe(e.cause?.name), causeCode: safe(e.cause?.code) };
  }
  report.status = report.error ? 'STATIC_READ_FAILED' : report.matchesAcceptedSource ? 'STATIC_SOURCE_MATCH' : 'STATIC_SOURCE_UNCONFIRMED';
  report.finishedAt = new Date().toISOString();
  fs.writeFileSync('static-read-diagnostic.json', JSON.stringify(report,null,2)+'\n',{flag:'wx',mode:0o600});
  console.log(JSON.stringify(report));
  if (report.status !== 'STATIC_SOURCE_MATCH') process.exitCode = 1;
})().catch(() => { console.error('DIAGNOSTIC_RECEIPT_WRITE_FAILED'); process.exitCode=1; });

