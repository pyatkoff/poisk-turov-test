const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'src/search3/behavior/results/local-hotel-filter.js'), 'utf8');
const bundle = fs.readFileSync(path.join(root, 'v2/search3-results-filters-v1.js'), 'utf8');

for (const marker of ['Источник предложения', 'Все источники', 'providerKey(t)', 'providerSelect.addEventListener']) {
  assert.ok(source.includes(marker), `provider result facet source keeps ${marker}`);
}
assert.match(source, /providerKey\(t\)===provider/, 'provider selection filters exact loaded offers');
assert.match(source, /api\.providerName\(t\)/, 'provider label uses the canonical renderer naming');
assert.ok(source.indexOf('Источник предложения') < source.indexOf('Туроператор'), 'provider and tour operator remain distinct controls');
assert.ok(bundle.includes('Источник предложения') && bundle.includes('Все источники'), 'generated Search3 filter bundle contains provider facet labels');
assert.ok(!/fetch\(|XMLHttpRequest|api-v2\.php/.test(source), 'local provider facet introduces no supplier transport');

console.log('PASS: provider is a distinct local loaded-offer facet without supplier search');
