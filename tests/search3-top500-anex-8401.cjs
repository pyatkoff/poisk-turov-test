'use strict';

// Offline end-to-end boundary: accepted ANEX projection -> TV plan -> local card.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {spawnSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
const php = spawnSync(process.env.PHP_BINARY || 'php', [
  '-n', path.join(__dirname, 'top500-anex-8401-regression.php'),
], {cwd: root, encoding: 'utf8', timeout: 10000});
assert.ifError(php.error);
assert.equal(php.status, 0, `${php.stdout}\n${php.stderr}`);
const result = JSON.parse(php.stdout);
assert.equal(result.state, 'TOP500_ANEX_8401_OK');

const source = fs.readFileSync(path.join(root, 'v2/prototype-search/hotel-popularity-v1.js'), 'utf8');
function popularity(ids) {
  const window = {AnyTourTopHotelLegacyIds: ids};
  vm.runInNewContext(source, {window}, {timeout: 1000});
  return window.AnyTourHotelPopularityV1;
}
const unresolved = popularity(result.unmapped_targets);
assert.equal(unresolved.isPopular({id: 8401, legacyIds: ['8401']}), false,
  'a TV 8401 edge on a canonical card is unrelated to an unresolved owner ANEX 8401');

const mapped = popularity(result.resolved_targets);
assert.equal(mapped.isPopular({id: 8401, legacyIds: []}), false,
  'a local canonical ID equal to ANEX 8401 does not establish popularity');
assert.equal(mapped.isPopular({id: 900076, legacyIds: []}), false,
  'a local canonical ID equal to the mapped TV number still needs its explicit bridge');
assert.equal(mapped.isPopular({id: 123456, legacyIds: ['8401']}), false,
  'the same-number TV hotel does not inherit popularity from ANEX 8401');
const linked = {id: 123456, legacyIds: ['900076']};
assert.equal(mapped.rank(linked), 2, 'the explicit canonical bridge inherits the mapped owner order');
assert.equal(mapped.badge(linked), 'Популярный отель');
assert.equal(mapped.boost(linked), 0.5);
console.log(`SEARCH3_TOP500_ANEX_8401_OK php_checks=${result.checks} popularity_checks=7 synthetic_pairs_only=true`);
