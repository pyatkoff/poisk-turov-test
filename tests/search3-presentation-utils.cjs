const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const utilPath = path.join(root, 'src/search3/behavior/presentation-text.js');
assert.ok(fs.existsSync(utilPath), 'shared presentation text owner must exist');

const context = { window: {} };
vm.runInNewContext(fs.readFileSync(utilPath, 'utf8'), context, { filename: 'presentation-text.js' });
const format = context.window.Search3PresentationText;
assert.ok(Object.isFrozen(format), 'shared presentation text API is immutable');
assert.equal(format.esc(`<a title="x">Tom & Jerry's</a>`), '&lt;a title=&quot;x&quot;&gt;Tom &amp; Jerry&#39;s&lt;/a&gt;');
assert.equal(format.text({ russianName: '', fullRussianName: 'Полное название', name: 'Fallback' }), 'Полное название');
assert.equal(format.text([{ name: 'DBL' }, { title: 'SEA VIEW' }, null]), 'DBL, SEA VIEW');
assert.equal(format.text(0), '0');
assert.equal(format.people({ adults: 2, childs: 1 }), '2 взр. + 1 дет.');
assert.equal(format.people({ adults: 0, childs: 0 }), '—');
assert.equal(format.place({ hotel: { country: { russianName: 'Турция' }, region: 'Анталья', subRegion: { title: 'Сиде' } } }), 'Турция, Анталья, Сиде');
assert.equal(format.place(null), '—');

const manifest = JSON.parse(fs.readFileSync(path.join(root, 'src/search3/manifest.json'), 'utf8'));
const modules = manifest.assets['search3-results-filters-v1.js'];
const ownerIndex = modules.indexOf('behavior/presentation-text.js');
for (const consumer of ['behavior/booking-summary.js', 'behavior/final-sections.js', 'behavior/tour-detail-rail.js']) {
  assert.ok(ownerIndex >= 0 && ownerIndex < modules.indexOf(consumer), `text owner loads before ${consumer}`);
}

const sources = Object.fromEntries(['booking-summary.js', 'final-sections.js', 'tour-detail-rail.js'].map((name) => [
  name,
  fs.readFileSync(path.join(root, 'src/search3/behavior', name), 'utf8')
]));
for (const name of Object.keys(sources)) {
  assert.ok(sources[name].includes('window.Search3PresentationText'), `${name} consumes the shared text owner`);
  assert.ok(!sources[name].includes('function esc('), `${name} has no private HTML escaper`);
}
assert.ok(!sources['booking-summary.js'].includes('function text('));
assert.ok(!sources['booking-summary.js'].includes('function people('));
assert.ok(!sources['booking-summary.js'].includes('function place('));
assert.ok(!sources['final-sections.js'].includes('function text('));
assert.ok(!sources['tour-detail-rail.js'].includes('function txt('));
assert.ok(!sources['tour-detail-rail.js'].includes('function people('));
assert.ok(!sources['tour-detail-rail.js'].includes('function place('));

console.log('PASS: one ordered presentation text owner preserves escaping, supplier text, compact party and place labels');
