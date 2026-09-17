'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const roomPath = 'v2/search3-room-normalizer-v1.js';
const rendererPath = 'v2/results-renderer-v5.js';
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const rendererSource = read(rendererPath);
const sha256 = source => createHash('sha256').update(source).digest('hex');

// Read the actual PHP manifest: the normalizer remains a legacy/full owner but
// Search3 intentionally renders the supplier room fact without semantic aliases.
const [fullJs, search3Js, initialJs] = JSON.parse(execFileSync('php', ['-r',
  "require $argv[1]; echo json_encode([v2_bundle_files('js','full'), v2_bundle_files('js','search3'), v2_bundle_phase_files('js','search3','initial')]);",
  path.join(root, 'v2/bundle-manifest-v1.php')
], { encoding: 'utf8' }));
const roomName = path.basename(roomPath);
const rendererName = path.basename(rendererPath);
assert.equal(fullJs.filter(file => file === roomName).length, 1, `${roomName} must remain in the full bundle`);
assert.equal(fullJs.filter(file => file === rendererName).length, 1, `${rendererName} must remain in the full bundle`);
assert.ok(fullJs.indexOf(roomName) < fullJs.indexOf(rendererName), 'legacy room owner still precedes the shared renderer');
for (const [label, files] of [['Search3', search3Js], ['Search3 initial', initialJs]]) {
  assert.equal(files.filter(file => file === roomName).length, 0, `${roomName} must not load in ${label}`);
  assert.equal(files.filter(file => file === rendererName).length, 1, `${rendererName} must load exactly once in ${label}`);
}

const compact = JSON.parse(read('v2/search3-shared-runtime.json')).entries[rendererName];
assert.ok(compact, 'the shipped compact renderer exists');
assert.equal(compact.sourceSha256, sha256(rendererSource), 'compact renderer belongs to current source');
assert.equal(compact.codeSha256, sha256(compact.code), 'compact renderer code digest is valid');
function sandbox() {
  return {
    window: {},
    document: { readyState: 'loading', addEventListener() {}, querySelector() { return null; } }
  };
}
function loadRenderer(source, filename) {
  const context = vm.createContext(sandbox());
  vm.runInContext(source, context, { filename });
  return { context, results: context.window.V2Results };
}
const rawRuntime = loadRenderer(rendererSource, rendererPath);
const packedRuntime = loadRenderer(compact.code, 'compact/results-renderer-v5.js');
const raw = rawRuntime.results;
const packed = packedRuntime.results;
assert.notEqual(raw, packed, 'raw and compact tests use independent real owners');
assert.equal(rawRuntime.context.window.Search3RoomNormalizerV1, undefined, 'raw Search3 renderer has no room normalizer owner');
assert.equal(packedRuntime.context.window.Search3RoomNormalizerV1, undefined, 'compact Search3 renderer has no room normalizer owner');

const samples = [
  ['STANDARD', 'STANDARD'],
  ['economy room', 'economy room'],
  ['  PROMO   ROOM\t', 'PROMO ROOM'],
  ['Стандартный номер · вид на бассейн', 'Стандартный номер · вид на бассейн'],
  ['STANDARD LAND VIEW', 'STANDARD LAND VIEW'],
  ['family suite with two bedrooms and side sea view', 'family suite with two bedrooms and side sea view'],
  ['EXECUTIVE SEA VIEW WITH BALCONY', 'EXECUTIVE SEA VIEW WITH BALCONY']
];
for (const [value, expected] of samples) {
  const offer = Object.freeze({ id: 'room-contract', price: 123450, date: '2026-10-20', nights: 7,
    adults: 2, childs: 0, meal: 'AI', roomType: value });
  const before = JSON.stringify(offer);
  for (const results of [raw, packed]) {
    assert.equal(results.roomIdentity(offer), null, 'Search3 does not infer a canonical room identity at render time');
    assert.equal(results.roomLabel(offer), expected, 'Search3 displays the exact supplier room fact');
    assert.equal(results.rawRoomLabel(offer), expected, 'raw supplier room fact only collapses transport whitespace');
    const html = results.tourRow(offer);
    assert.ok(html.includes(`<small>Номер</small><b>${expected}</b>`), 'actual tour row uses the supplier room fact');
    assert.ok(html.includes('20.10.2026'), 'concrete date is preserved');
    assert.ok(html.includes('7 ноч.'), 'concrete duration is preserved');
    assert.ok(html.includes('data-tid="room-contract"'), 'selection identity is preserved');
  }
  assert.equal(raw.tourRow(offer), packed.tourRow(offer), 'raw/compact customer markup is identical');
  assert.equal(JSON.stringify(offer), before, 'display does not mutate supplier/price/party/identity fields');
}

const unsafe = Object.freeze({ id: 'unknown-room', price: 123450, roomType: '<img src=x onerror="bad()">' });
for (const results of [raw, packed]) {
  assert.equal(results.roomIdentity(unsafe), null, 'untrusted text never gains a rendered room identity');
  assert.equal(results.roomLabel(unsafe), '<img src=x onerror="bad()">', 'untrusted supplier text remains verbatim before escaping');
  const html = results.tourRow(unsafe);
  assert.ok(html.includes('&lt;img src=x onerror=&quot;bad()&quot;&gt;'));
  assert.doesNotMatch(html, /<img/);
}
assert.equal(raw.tourRow(unsafe), packed.tourRow(unsafe));
for (const offer of [{}, { roomType: null }, { roomType: '' }, { roomType: { id: 7 } }]) {
  assert.equal(raw.roomIdentity(offer), null, 'missing/opaque room facts do not gain identity');
  assert.equal(packed.roomIdentity(offer), null);
}
for (const [offer, expected] of [
  [{ room: 'Standard room' }, 'Standard room'],
  [{ roomType: { russianName: 'Стандарт', name: 'unreviewed supplier label' } }, 'Стандарт'],
  [{ roomType: { fullRussianName: 'Стандарт', name: 'unreviewed supplier label' } }, 'Стандарт'],
  [{ roomType: { name: 'Standard room' } }, 'Standard room']
]) {
  assert.equal(raw.roomLabel(offer), expected);
  assert.equal(packed.roomLabel(offer), expected);
}

const meals = [
  [{ meal: 'AI' }, 'AI', 'meal:label:ai'],
  [{ meal: { name: 'AI' } }, 'AI', 'meal:label:ai'],
  [{ meal: { name: 'AI', fullName: 'Всё включено' } }, 'Всё включено', 'meal:label:всё включено'],
  [{ meal: { name: 'UAI', fullName: 'Ultra All Inclusive без алкоголя' } }, 'Ultra All Inclusive без алкоголя', 'meal:label:ultra all inclusive без алкоголя'],
  [{ meal: '  Half   Board\t' }, 'Half Board', 'meal:label:half board']
];
for (const [offer, expectedLabel, expectedKey] of meals) {
  const before = JSON.stringify(offer);
  for (const results of [raw, packed]) {
    assert.equal(results.rawMealLabel(offer), expectedLabel, 'meal display preserves the supplier fact and only collapses whitespace');
    assert.equal(results.mealLabel(offer), expectedLabel, 'Search3 does not translate meal codes at render time');
    const identity = results.mealIdentity(offer);
    assert.equal(identity && identity.key, expectedKey, 'meal filtering uses the exact displayed supplier fact');
    assert.equal(identity && identity.label, expectedLabel, 'meal identity label matches customer-visible supplier fact');
  }
  assert.equal(JSON.stringify(offer), before, 'meal display does not mutate supplier facts');
}
assert.equal(raw.mealLabel({ meal: 'AI' }), 'AI', 'AI without supplier fullName never becomes Всё включено');
assert.equal(packed.mealLabel({ meal: 'AI' }), 'AI');
assert.equal(raw.mealLabel({ meal: { name: 'AI', fullName: 'Всё включено' } }), 'Всё включено', 'supplier fullName is shown exactly');
assert.equal(packed.mealLabel({ meal: { name: 'AI', fullName: 'Всё включено' } }), 'Всё включено');

console.log(`SEARCH3_ROOM_RUNTIME_CONTRACT_OK raw_supplier_facts=${samples.length} exact_meal_facts=${meals.length} no_search3_normalizer=1 raw_compact=1 immutable_input=1 escaped_display=1`);
