const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const retired = [
  'search3-mobile-actions',
  'search3-mobile-filter-button',
  'search3-mobile-sort-button',
  'search3-active-chips',
  'search3-chip'
];

function files(dir, extension) {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? files(full, extension) : (entry.isFile() && entry.name.endsWith(extension) ? [full] : []);
  });
}

function hits(fileList) {
  return fileList.flatMap((file) => {
    const source = fs.readFileSync(file, 'utf8');
    return retired.filter((marker) => source.includes(marker)).map((marker) => `${path.relative(root, file)}:${marker}`);
  });
}

const presentation = require('./search3-bundled-results.cjs');
const selectedPresentation = fs.readFileSync(path.join(root, 'src/search3/behavior/tour-presentation.js'), 'utf8');
const searchForm = fs.readFileSync(path.join(root, 'src/search3/behavior/search-form.js'), 'utf8');
const mobile = fs.readFileSync(path.join(root, 'v2/mobile-results-filters-v1.js'), 'utf8');

assert.match(presentation, /(?:[A-Za-z_$][\w$]*|\([A-Za-z_$][\w$]*=document\.createElement\((['"])div\1\)\))\.className\s*=\s*(['"])search3-mobile-toolbar\2/, 'Search3 presentation owns the mobile toolbar shell');
assert.ok(presentation.includes('search3-mobile-filter-slot'), 'Search3 presentation owns the mobile filter slot');
assert.ok(presentation.includes('search3-mobile-sort'), 'Search3 presentation owns the mobile sort control');
assert.match(presentation, /document\.querySelector\((['"])\.mrf-bar\1\)/, 'Search3 presentation mounts the canonical mrf filter bar');
assert.match(mobile, /sheet\.className\s*=\s*(['"])mrf-sheet\1/, 'base mobile results filter sheet remains canonical');
assert.ok(mobile.includes('function openSheet(') && mobile.includes('function closeSheet('), 'canonical mobile filter lifecycle remains intact');
assert.ok(!selectedPresentation.includes('function plural('), 'selected-tour presentation reuses the canonical inflection owner');
assert.ok(selectedPresentation.includes('format.plural('), 'selected-tour presentation consumes the canonical inflection owner');
assert.ok(!selectedPresentation.includes('function text('), 'unused selected-tour text normalizer stays retired');
assert.ok(!searchForm.includes('function formatDate('), 'unused private date formatter stays retired from the search form');

const runtimeHits = hits([
  ...files(path.join(root, 'src/search3/behavior'), '.js'),
  ...files(path.join(root, 'v2'), '.js'),
  ...files(path.join(root, 'v2'), '.php')
]);
assert.deepEqual(runtimeHits, [], `retired mobile-toolbar classes have no runtime producer: ${runtimeHits.join(', ')}`);

const cssHits = hits(files(path.join(root, 'src/search3/styles'), '.css'));
assert.deepEqual(cssHits, [], `retired mobile-toolbar CSS must be absent: ${cssHits.join(', ')}`);

console.log('PASS: canonical Search3 mobile toolbar owner only; retired action/chip selectors absent');
