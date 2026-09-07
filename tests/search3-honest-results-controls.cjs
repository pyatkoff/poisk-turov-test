const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'v2/index.php'), 'utf8');
const redesign = fs.readFileSync(path.join(root, 'v2/search-redesign-v2.js'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'v2/ds2-search.css'), 'utf8');
const renderer = fs.readFileSync(path.join(root, 'v2/results-renderer-v5.js'), 'utf8');

const select = page.match(/<select id="sortResults">([\s\S]*?)<\/select>/);
assert.ok(select, 'results sort remains available');
assert.deepEqual(
  [...select[1].matchAll(/<option value="([^"]+)">/g)].map(match => match[1]),
  ['price', 'rating', 'stars'],
  'only implemented result sorting modes are offered'
);
assert.doesNotMatch(page, /Ближе к морю|data-results-map|results-map-button/);
assert.doesNotMatch(redesign, /results-map-requested|data-results-map/);
assert.doesNotMatch(styles, /results-map-button/);
assert.match(renderer, /if\(m==='rating'\)/);
assert.match(renderer, /if\(m==='stars'\)/);
assert.match(
  page,
  /<\?php if\(!v2_search3_enabled\(\)\):\?><div class="results-view-switch"[\s\S]*?<\?php endif;\?>/,
  'list/grid controls are emitted only for the legacy presentation'
);

console.log('PASS: Search3 exposes implemented sorting without a false view switch; legacy views remain');
