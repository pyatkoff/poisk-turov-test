const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const rail = fs.readFileSync(path.join(root, 'src/search3/behavior/filter-rail.js'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'src/search3/styles/filters.css'), 'utf8');
const mobile = fs.readFileSync(path.join(root, 'v2/mobile-results-filters-v1.js'), 'utf8');
const presentation = fs.readFileSync(path.join(root, 'src/search3/behavior/results-presentation.js'), 'utf8');

for (const marker of [
  'search3:filters-opened',
  'search3:filters-cancelled',
  'search3:filters-committed',
  'search3-filter-subpanel',
  'data-s3-subpanel',
  'search3-filter-open',
  'search3-filter-overlay',
  'data-s3-close-filters',
  'data-s3-commit-filters'
]) {
  assert.ok(!rail.includes(marker), `filter rail no longer owns orphan mobile lifecycle: ${marker}`);
}

assert.match(
  rail,
  /var panel=e\.target\.closest\('\[data-s3-panel\]'\);if\(panel\)\{editSearch\(\);return;\}/,
  'desktop result-filter edit rows keep their existing edit-search handoff'
);
assert.match(styles, /@media\(max-width:999px\)\{[\s\S]*?\.results-filter-rail\{display:none!important\}/, 'desktop rail is explicitly absent under the mobile ownership boundary');
assert.ok(!styles.includes('search3-filter-subpanel'), 'orphan subpanel CSS removed');
assert.ok(!styles.includes('search3-filter-open'), 'orphan drawer-open CSS removed');
assert.ok(!styles.includes('search3-filter-overlay'), 'orphan overlay CSS removed');

assert.ok(mobile.includes('class="mrf-sheet"'), 'base mobile result-filter sheet remains the mobile owner');
assert.ok(mobile.includes('function openSheet(') && mobile.includes('function closeSheet('), 'mobile owner retains open/close lifecycle');
assert.ok(presentation.includes("document.querySelector('.mrf-bar')"), 'Search3 presentation still mounts the canonical mobile filter bar');

console.log('PASS: one mobile filter owner; desktop rail keeps edit-search handoff');
