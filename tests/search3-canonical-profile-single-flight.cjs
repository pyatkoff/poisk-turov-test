'use strict';
// Real client owner, fictional profiles and controlled transport. No HTTP or DB writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../v2/search3-canonical-profiles-v1.js'), 'utf8');
const route = '/_preview/search3-local-candidate/prototype-search/';
const endpoint = '/_preview/search3-local-candidate/data/hotel-details-read-v1.php';
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
const tick = () => new Promise(resolve => setImmediate(resolve));
const profile = (id, revision = 1) => ({ id, revision, catalog: 'anytour', name: 'Fictional hotel ' + id, images: [], hotelInformation: {} });
const payload = item => ({ ok: true, source: 'anytour-canonical-catalog', catalog: 'anytour', item });
function setup() {
  const calls = [], events = {}, timers = new Map();
  let sequence = 0, changes = 0, syncFailure = false;
  const root = { location: { pathname: route }, addEventListener: (type, fn) => { events[type] = fn; } };
  root.fetch = (url, options) => {
    const call = { url, options }; calls.push(call);
    if (syncFailure) throw new Error('Fictional synchronous transport failure');
    return new Promise((resolve, reject) => { Object.assign(call, { resolve, reject }); });
  };
  vm.runInNewContext(source, {
    window: root, URLSearchParams, AbortController,
    setTimeout: fn => { timers.set(++sequence, fn); return sequence; },
    clearTimeout: key => timers.delete(key)
  });
  const owner = root.Search3CanonicalProfilesV1.create(() => changes++);
  return { root, calls, events, timers, owner, get changes() { return changes; },
    setSyncFailure(value) { syncFailure = value; } };
}
async function reply(ctx, index, value, status = 200) {
  ctx.calls[index].resolve({ ok: status === 200, status, json: async () => value }); await tick();
}
async function run() {
  // Opening/restoring the same saved hotel during startup must not multiply reads.
  let ctx = setup();
  try {
    const readers = Array.from({ length: 20 }, (_, i) => ctx.owner.readProfile(i % 2 ? '901' : 901));
    const all = Promise.all(readers);
    check(ctx.calls.length === 1, 'Twenty overlapping own-profile readers issue one catalogue request');
    const call = ctx.calls[0], url = new URL(call.url, 'https://fixture.invalid');
    check(url.pathname === endpoint && url.searchParams.toString() === 'catalog=anytour&anytourHotelId=901', 'Existing own-ID endpoint and request envelope unchanged');
    check(call.options.cache === 'no-store' && call.options.credentials === 'same-origin' && call.options.headers.Accept === 'application/json', 'Read isolation and freshness options unchanged');
    check(ctx.timers.size === 1, 'One shared read owns one timeout');
    await reply(ctx, 0, payload(profile(901)));
    const profiles = await all;
    check(profiles.every(p => p === profiles[0]) && profiles[0].id === 901, 'All consumers receive the same validated profile');
    check(ctx.changes === 0 && ctx.owner.read([], {}).length === 0, 'A profile read neither invents offers nor refreshes results');
    check(ctx.timers.size === 0, 'Completed shared read clears its timer');
    const next = ctx.owner.readProfile(901);
    check(ctx.calls.length === 2, 'Later explicit read is not served from a persistent cache');
    await reply(ctx, 1, payload(profile(901, 2)));
    check((await next).revision === 2, 'Later read observes a new revision');
  } finally { ctx.owner.reset(); }

  for (const mode of ['http', 'json', 'malformed', 'identity', 'revision', 'network', 'timeout', 'sync']) {
    ctx = setup();
    try {
      const original = ctx.owner.upsertHotel(profile(901));
      if (mode === 'sync') ctx.setSyncFailure(true);
      const all = Promise.allSettled([ctx.owner.readProfile(901), ctx.owner.readProfile('901'), ctx.owner.readProfile(901)]);
      check(ctx.calls.length === 1, mode + ': failure is shared, not requested once per reader');
      if (mode === 'network') ctx.calls[0].reject(new Error('Fictional network error'));
      else if (mode === 'json') ctx.calls[0].resolve({ ok: true, status: 200, json: async () => { throw new SyntaxError('Fictional JSON failure'); } });
      else if (mode !== 'sync') {
        if (mode === 'timeout') {
          ctx.timers.values().next().value();
          check(ctx.calls[0].options.signal.aborted, 'Timeout aborts the single shared transport');
        }
        const item = mode === 'revision' ? { ...profile(901), name: 'Conflicting same revision' } : profile(mode === 'identity' ? 902 : 901);
        await reply(ctx, 0, mode === 'malformed' ? { ok: true } : payload(item), mode === 'http' ? 503 : 200);
      }
      const outcomes = await all;
      check(outcomes.every(o => o.status === 'rejected' && o.reason === outcomes[0].reason), mode + ': every consumer observes the same failure');
      check(ctx.owner.details({ anytourHotelId: 901 }) === original && ctx.changes === 0, mode + ': error cannot change existing profile or offers');
      check(ctx.timers.size === 0, mode + ': no timer remains after rejection');
      ctx.setSyncFailure(false);
      const retry = Promise.all([ctx.owner.readProfile('901'), ctx.owner.readProfile(901)]);
      check(ctx.calls.length === 2, mode + ': explicit retry makes one fresh request after failure');
      await reply(ctx, 1, payload(profile(901, 2)));
      const recovered = await retry;
      check(recovered[0] === recovered[1] && recovered[0].revision === 2, mode + ': retry is not poisoned by an earlier rejected promise');
    } finally { ctx.owner.reset(); }
  }

  // A completion belonging to an old search must not remove a new same-ID flight.
  for (const mode of ['success', 'network', 'http']) {
    ctx = setup();
    try {
      const old = Promise.allSettled([ctx.owner.readProfile(901), ctx.owner.readProfile('901')]);
      ctx.owner.reset();
      check(ctx.calls[0].options.signal.aborted, 'Reset aborts the old shared request');
      const first = ctx.owner.readProfile(901), second = ctx.owner.readProfile('901');
      check(ctx.calls.length === 2, 'A new generation does not reuse old pending work');
      if (mode === 'network') { ctx.calls[0].reject(new Error('Old fictional request aborted')); await tick(); }
      else await reply(ctx, 0, payload(profile(901)), mode === 'http' ? 503 : 200);
      const oldResults = await old;
      check(oldResults.every(r => mode === 'success' ? r.status === 'fulfilled' && r.value === null : r.status === 'rejected'), 'Old success is discarded; old failures stay local to old consumers');
      check(ctx.owner.details({ anytourHotelId: 901 }) === null && ctx.changes === 0, 'No stale profile appears in the new generation');
      const third = ctx.owner.readProfile(901);
      check(ctx.calls.length === 2, 'Old cleanup cannot erase a current same-ID in-flight request');
      await reply(ctx, 1, payload(profile(901, 3)));
      const current = await Promise.all([first, second, third]);
      check(current.every(p => p === current[0]) && current[0].revision === 3, 'Only the new generation profile resolves current consumers');
      check(ctx.timers.size === 0, 'Reset and both settled generations leave no timers');
    } finally { ctx.owner.reset(); }
  }

  ctx = setup();
  try {
    const direct = Promise.all([ctx.owner.readProfile(901), ctx.owner.readProfile('901')]);
    const tour = { id: 'fictional-offer', provider: 'tourvisor', price: 123456 };
    const rows = [{ id: 102, provider: 'tourvisor', mappingStatus: 'resolved', tours: [tour] }];
    check(ctx.owner.read(rows, {}).length === 0, 'Results still wait for their own profile'); await tick();
    check(ctx.calls.length === 2, 'Duplicate favourite reads do not occupy both result worker slots');
    const query = new URL(ctx.calls[1].url, 'https://fixture.invalid').searchParams;
    check(query.getAll('legacyHotelIds[]').join(',') === '102' && !query.has('anytourHotelId'), 'Own-profile and legacy-result namespaces never share identities');
    await reply(ctx, 1, { ok: true, source: 'anytour-canonical-catalog', catalog: 'anytour', requestedLegacyIds: [102], items: [profile(1)], links: [{ legacyHotelId: 102, anytourHotelId: 1 }], missingLegacyIds: [] });
    const cards = ctx.owner.read(rows, {});
    check(cards.length === 1 && cards[0].tours[0] === tour && cards[0].price === 123456, 'Result hydration preserves the exact offer while favourite read is pending');
    await reply(ctx, 0, payload(profile(901))); await direct;
    check(ctx.calls.length === 2 && ctx.changes === 1, 'No duplicate refresh or extra batch after shared completion');
    const different = Promise.all([ctx.owner.readProfile(901), ctx.owner.readProfile(902)]);
    check(ctx.calls.length === 4, 'Different own IDs are not coalesced');
    await reply(ctx, 2, payload(profile(901))); await reply(ctx, 3, payload(profile(902)));
    check((await different).map(p => p.id).join(',') === '901,902', 'Each distinct ID keeps its own validated result');
    for (const invalid of ['0901', 0, '0', '901x', {}, Number.MAX_SAFE_INTEGER + 1]) await assert.rejects(ctx.owner.readProfile(invalid));
    check(ctx.calls.length === 4, 'Strict invalid ID validation remains before request sharing');
  } finally { ctx.owner.reset(); }
  console.log('SEARCH3_PROFILE_SINGLE_FLIGHT_OK checks=' + checks + ' fixture_overlap_requests=20->1 supplier_calls=0 db_writes=0');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
