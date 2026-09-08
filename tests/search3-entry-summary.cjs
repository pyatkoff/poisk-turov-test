/* Replacement contract: direct canonical controls, no retired entry decorators. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'src/search3/behavior/search-form/primary-controls.js'), 'utf8');
const bundle = fs.readFileSync(path.join(root, 'v2/search3-results-filters-v1.js'), 'utf8');
for (const marker of ['search3-price-calendar', 'search3-entry-summary-detail', 'search3-tourists__pop',
  'search3-tourists__summary', 'search3-mobile-search-filter-button', 'search3EntryLayout', 'mobile-search-collapsed']) {
  assert.ok(!bundle.includes(marker), `retired entry producer stays absent: ${marker}`);
}
assert.equal(fs.existsSync(path.join(root, 'src/search3/behavior/search-form/entry-presentation.js')), false);
function control(name, value) {
  const attributes = new Map();
  return { name, value, attributes, classList: { remove() {}, add() {} },
    setAttribute(k,v) { attributes.set(k,v); }, removeAttribute(k) { attributes.delete(k); } };
}
const adults = control('count_people', '2'), children = control('child_count', '1');
const ages = { classList: { add() {}, remove() {} } };
const nodes = [], fields = [];
const ctl = { appendChild(node) { nodes.push(node); } };
const box = { querySelector() { return ctl; } };
vm.runInNewContext(source, {
  refs: { count_people: adults, child_count: children }, childAges: ages,
  makeComposite(label, name) { assert.equal(label, 'Туристы'); assert.equal(name, 'search3-tourists'); return box; },
  main: { appendChild(node) { fields.push(node); } }
});
assert.deepEqual(nodes, [adults, children, ages], 'reuse original nodes, including existing child-age handlers');
assert.deepEqual(fields, [box]);
assert.equal(adults.value, '2'); assert.equal(children.value, '1');
assert.equal(adults.name, 'count_people'); assert.equal(children.name, 'child_count');
assert.equal(adults.attributes.get('aria-label'), 'Взрослых');
assert.equal(children.attributes.get('aria-label'), 'Детей');
assert.equal(adults.tabIndex, 0); assert.equal(children.tabIndex, 0);
console.log('PASS: canonical guest controls and child-age nodes retained; entry popup/calendar/layout decorators absent');
