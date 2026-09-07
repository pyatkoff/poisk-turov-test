const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const bundledIife = require('./search3-bundle-iife.cjs');

const root = path.join(__dirname, '..');
const bundle = fs.readFileSync(path.join(root, 'v2/search3-results-filters-v1.js'), 'utf8');
const bridge = bundledIife(bundle, { literal: '.results-filter-rail' });
const desktop = fs.readFileSync(path.join(root, 'v2/ds2-results-filters.js'), 'utf8');
const mobile = fs.readFileSync(path.join(root, 'v2/mobile-results-filters-v1.js'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'v2/search3-results-filters-v1.css'), 'utf8');
const presentation = require('./search3-bundled-results.cjs');

assert.match(bridge, /window\.DS2ResultsFilters/, 'Search3 bridge requires the loaded DS2 owner');
assert.match(bridge, /window\.__DS2ResultsRailApplying/, 'bridge scopes the zero-result marker to DS2 renders');
assert.match(bridge, /dataset\.s3EmptyResults/, 'bridge preserves the Search3 empty-shell contract');
for (const marker of ['data-s3-price', 'data-s3-panel', 'data-s3-reset', 'data-s3-charter-check']) {
  assert.ok(!bridge.includes(marker), `retired duplicate desktop control is absent: ${marker}`);
}

assert.match(desktop, /window\.DS2ResultsFilters=\{/, 'DS2 is the sole desktop filter owner');
for (const marker of ['data-ds2-price', 'data-ds2-meal-fieldset', 'data-ds2-stars-fieldset',
  'data-ds2-rating-fieldset', 'data-ds2-sea-fieldset', 'data-ds2-reset']) {
  assert.ok(desktop.includes(marker), `DS2 owner retains ${marker}`);
}
assert.ok(desktop.includes('window.__DS2ResultsRailApplying=true'), 'DS2 exposes only its synchronous render boundary');
for (const selector of ['search3-filter-section', 'search3-filter-edit-row', 'input[type=radio]']) {
  assert.ok(!styles.includes(selector), `retired desktop rail presentation is absent: ${selector}`);
}
assert.match(mobile, /sheet\.className=(['"])mrf-sheet\1/, 'mobile sheet remains independently owned');
assert.ok(mobile.includes('function openSheet(') && mobile.includes('function closeSheet('),
  'mobile open/close lifecycle remains live');
assert.match(presentation, /document\.querySelector\((['"])\.mrf-bar\1\)/,
  'Search3 still mounts the canonical mobile filter bar');

console.log('PASS: one DS2 desktop filter owner, scoped Search3 empty bridge, independent mobile owner');
