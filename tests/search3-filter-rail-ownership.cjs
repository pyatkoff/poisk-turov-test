const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const rail = fs.readFileSync(path.join(root, 'src/search3/behavior/filter-rail.js'), 'utf8');
const stylesRoot = path.join(root, 'src/search3/styles');
const styles = fs.readFileSync(path.join(stylesRoot, 'filters.css'), 'utf8');
const mobile = fs.readFileSync(path.join(root, 'v2/mobile-results-filters-v1.js'), 'utf8');
const legacyDesktop = fs.readFileSync(path.join(root, 'v2/ds2-results-filters.js'), 'utf8');
const presentation = fs.readFileSync(path.join(root, 'src/search3/behavior/results-presentation.js'), 'utf8');

function cssFiles(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? cssFiles(full) : (entry.isFile() && entry.name.endsWith('.css') ? [full] : []);
  });
}

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

for (const file of cssFiles(stylesRoot)) {
  const source = fs.readFileSync(file, 'utf8');
  for (const marker of ['search3-filter-subpanel', 'search3-filter-open', 'search3-filter-overlay']) {
    assert.ok(!source.includes(marker), `${path.relative(root, file)} no longer contains orphan drawer marker: ${marker}`);
  }
}

assert.ok(mobile.includes("sheet.className='mrf-sheet'"), 'base mobile result-filter sheet remains the mobile owner');
assert.ok(mobile.includes('function openSheet(') && mobile.includes('function closeSheet('), 'mobile owner retains open/close lifecycle');
assert.ok(presentation.includes("document.querySelector('.mrf-bar')"), 'Search3 presentation still mounts the canonical mobile filter bar');
assert.ok(legacyDesktop.includes("t.matches('[data-ds2-price]')"),
  'the base bundle retains its legacy desktop price listener');
assert.ok(!rail.includes('data-ds2-price'),
  'the Search3 price control does not opt into the legacy desktop listener');

console.log('PASS: one mobile filter owner; orphan drawer CSS absent across Search3 styles');
