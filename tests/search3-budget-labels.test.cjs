'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../v2/index.php'), 'utf8');
const primary = source.slice(source.indexOf('<div class="search-preferences">'), source.indexOf('<details class="extras">'));
for (const [name, caption, placeholder] of [['price_from', 'Цена от', '80 000'], ['price_till', 'Цена до', '180 000']]) {
  const labels = [...primary.matchAll(/<label\b[^>]*>[\s\S]*?<\/label>/g)].map(match => match[0]);
  const matching = labels.filter(label => label.includes(`name="${name}"`));
  assert.equal(matching.length, 1, `${name}: one primary budget control`);
  const label = matching[0];
  assert.ok(label.includes(`<span>${caption}</span><?php if(v2_search3_enabled()):?><small>₽ за весь тур</small><?php endif;?>`), `${name}: total-trip ruble scope belongs to the associated Search3 label`);
  assert.ok(label.includes(`<input type="number" min="0" step="<?=v2_search3_enabled()?'1':'1000'?>" name="${name}" placeholder="${placeholder}">`), `${name}: original value mapping and native constraints unchanged`);
}
assert.equal((primary.match(/₽ за весь тур/g) || []).length, 2, 'both bounds explain currency and total-trip scope');
console.log('SEARCH3_BUDGET_LABELS_OK: total-trip rubles; original controls and legacy labels retained');
