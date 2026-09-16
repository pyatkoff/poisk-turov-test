'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { loadSearch3Renderer } = require('./helpers/search3-renderer-bootstrap');

const root = path.resolve(__dirname, '..');
const roomPath = 'v2/search3-room-normalizer-v1.js';
const rendererPath = 'v2/results-renderer-v5.js';
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const roomSource = read(roomPath);
const rendererSource = read(rendererPath);
const sha256 = source => createHash('sha256').update(source).digest('hex');
const plain = value => JSON.parse(JSON.stringify(value));

// Read the actual PHP manifest: no duplicated list of browser dependencies.
const manifests = JSON.parse(execFileSync('php', ['-r',
  "require $argv[1]; echo json_encode([v2_bundle_files('js','full'), v2_bundle_files('js','search3'), v2_bundle_phase_files('js','search3','initial')]);",
  path.join(root, 'v2/bundle-manifest-v1.php')
], { encoding: 'utf8' }));
for (const files of manifests) {
  for (const name of [path.basename(roomPath), path.basename(rendererPath)]) {
    assert.equal(files.filter(file => file === name).length, 1, `${name} must load exactly once`);
  }
  assert.ok(files.indexOf(path.basename(roomPath)) < files.indexOf(path.basename(rendererPath)),
    'room owner precedes renderer in full, Search3 and initial bundles');
}

const compact = JSON.parse(read('v2/search3-shared-runtime.json')).entries['results-renderer-v5.js'];
assert.ok(compact, 'the shipped compact renderer exists');
assert.equal(compact.sourceSha256, sha256(rendererSource), 'compact renderer belongs to current source');
assert.equal(compact.codeSha256, sha256(compact.code), 'compact renderer code digest is valid');
function sandbox() {
  return {
    window: {},
    document: { readyState: 'loading', addEventListener() {}, querySelector() { return null; } }
  };
}
const rawContext = sandbox();
const raw = loadSearch3Renderer(rawContext);
const compactContext = vm.createContext(sandbox());
vm.runInContext(roomSource, compactContext, { filename: roomPath });
vm.runInContext(compact.code, compactContext, { filename: 'compact/results-renderer-v5.js' });
const packed = compactContext.window.V2Results;
assert.notEqual(raw, packed, 'raw and compact tests use independent real owners');

const samples = [
  ['STANDARD', 'standard', 'Стандарт'],
  ['economy room', 'economy', 'Эконом'],
  ['PROMO ROOM', 'promo', 'Промо'],
  ['Стандартный номер · вид на бассейн', 'standard-pool-view', 'Стандарт · вид на бассейн'],
  ['STANDARD LAND VIEW', 'standard-land-view', 'Стандарт · территория'],
  ['standard room with garden view', 'standard-garden-view', 'Стандарт · вид на сад'],
  ['standard garden or pool view', 'standard-garden-or-pool-view', 'Стандарт · вид на сад или бассейн'],
  ['standard pool / lagoon view', 'standard-pool-or-lagoon-view', 'Стандарт · вид на бассейн или лагуну'],
  ['standard marina view', 'standard-marina-view', 'Стандарт · вид на марину'],
  ['standard sea view room', 'standard-sea-view', 'Стандарт · море'],
  ['standard side sea view room', 'standard-side-sea-view', 'Стандарт · боковой вид на море'],
  ['superior room', 'superior', 'Улучшенный'],
  ['superior garden view room', 'superior-garden-view', 'Улучшенный · вид на сад'],
  ['superior side sea view', 'superior-side-sea-view', 'Улучшенный · боковой вид на море'],
  ['family room', 'family', 'Семейный'],
  ['family one bedroom', 'family-one-bedroom', 'Семейный · 1 спальня'],
  ['club room', 'club', 'Клубный'],
  ['premium garden view room', 'premium-garden-view', 'Премиум · вид на сад'],
  ['premium room mountain', 'premium-mountain-view', 'Премиум · вид на горы'],
  ['deluxe room', 'deluxe', 'Делюкс'],
  ['junior suite', 'junior-suite', 'Полулюкс'],
  ['suite room', 'suite', 'Люкс'],
  ['family suite', 'family-suite', 'Семейный люкс'],
  ['family suite with two bedrooms and side sea view', 'family-suite-2-bedroom-side-sea-view', 'Семейный люкс · 2 спальни · боковой вид на море']
];
for (const [name, key, label] of samples) {
  for (const value of [name, name.toLocaleUpperCase('ru-RU'), `  ${name.replace(/ /g, '  ')}\t`]) {
    const offer = Object.freeze({ id: 'room-contract', price: 123450, date: '2026-10-20', nights: 7,
      adults: 2, childs: 0, meal: 'AI', roomType: value });
    const before = JSON.stringify(offer);
    for (const results of [raw, packed]) {
      assert.deepEqual(plain(results.roomIdentity(offer)), { key: `room:${key}`, label });
      assert.equal(results.roomLabel(offer), label);
      assert.equal(results.rawRoomLabel(offer), value.replace(/\s+/g, ' ').trim());
      const html = results.tourRow(offer);
      assert.ok(html.includes(`<small>Номер</small><b>${label}</b>`), 'actual tour row uses the shared room label');
      assert.ok(html.includes('20.10.2026'), 'concrete date is preserved');
      assert.ok(html.includes('7 ноч.'), 'concrete duration is preserved');
      assert.ok(html.includes('data-tid="room-contract"'), 'selection identity is preserved');
    }
    assert.equal(raw.tourRow(offer), packed.tourRow(offer), 'raw/compact customer markup is identical');
    assert.equal(JSON.stringify(offer), before, 'display does not mutate supplier/price/party/identity fields');
  }
}

for (const name of ['STANDARD POOL VIEW WITH PRIVATE POOL', 'EXECUTIVE SEA VIEW WITH BALCONY',
  '__proto__', 'constructor', 'toString', '<img src=x onerror="bad()">']) {
  const offer = Object.freeze({ id: 'unknown-room', price: 123450, roomType: name });
  for (const results of [raw, packed]) {
    assert.ok(results.roomIdentity(offer).key.startsWith('room:label:'), 'unknowns never gain a reviewed identity');
    assert.equal(results.roomLabel(offer), name, 'qualifiers and unknown text stay verbatim');
    if (name.startsWith('<')) {
      const html = results.tourRow(offer);
      assert.ok(html.includes('&lt;img src=x onerror=&quot;bad()&quot;&gt;'));
      assert.doesNotMatch(html, /<img/);
    }
  }
  assert.equal(raw.tourRow(offer), packed.tourRow(offer));
}
for (const offer of [{}, { roomType: null }, { roomType: '' }, { roomType: { id: 7 } }]) {
  assert.equal(raw.roomIdentity(offer), null, 'unknown numeric IDs are not room labels');
  assert.equal(packed.roomIdentity(offer), null);
}
for (const offer of [
  { room: 'Standard room' },
  { roomType: { russianName: 'Стандарт', name: 'unreviewed supplier label' } },
  { roomType: { fullRussianName: 'Стандарт', name: 'unreviewed supplier label' } },
  { roomType: { name: 'Standard room' } }
]) {
  assert.equal(raw.roomLabel(offer), 'Стандарт');
  assert.equal(packed.roomLabel(offer), 'Стандарт');
}
const rooms = rawContext.window.Search3RoomNormalizerV1;
const result = rooms.identity('standard');
result.key = 'mutated'; result.label = 'mutated';
assert.deepEqual(plain(rooms.identity('standard')), { key: 'room:standard', label: 'Стандарт' },
  'returned identities cannot mutate the private lookup index');
vm.runInContext(roomSource, rawContext, { filename: roomPath });
assert.equal(rawContext.window.Search3RoomNormalizerV1, rooms, 'repeat loading preserves the existing owner');

console.log(`SEARCH3_ROOM_RUNTIME_CONTRACT_OK families=${samples.length} raw_compact=1 bundle_order=1 immutable_input=1 escaped_display=1`);
