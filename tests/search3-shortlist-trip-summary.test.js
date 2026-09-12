'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const file = path.join(root, 'src/search3/behavior/results/shortlist.js');
const source = fs.readFileSync(file, 'utf8');

function functionLine(name) {
  const prefix = `function ${name}(`;
  const line = source.split('\n').find(value => value.startsWith(prefix));
  assert.ok(line, `${name} must remain a focused helper in the canonical shortlist owner`);
  return line;
}

const displayDateSource = functionLine('displayDate');
assert.doesNotMatch(displayDateSource, /new\s+Date\s*\(|Date\.parse/, 'customer-facing departure formatting must stay date-only and timezone-free');
assert.match(source, /date:tour\.date/, 'the exact saved snapshot must retain the original provider date value');
assert.match(source, /\['Вылет',displayDate\(item\.date\)\]/, 'rendered comparison must format the saved departure without mutating it');
assert.match(source, /\['Туристы',partyLabel\(item\)\]/, 'rendered comparison must expose the saved party composition');

const dateSandbox = {
  text(value) {
    return String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
  }
};
vm.createContext(dateSandbox);
vm.runInContext(`${displayDateSource}; this.values = [
  displayDate('2026-09-12'),
  displayDate('2026-09-12T04:30:00Z'),
  displayDate('12.09.2026'),
  displayDate('12 Sep 2026'),
  displayDate('')
];`, dateSandbox);
assert.deepEqual(Array.from(dateSandbox.values), ['12.09.2026', '12.09.2026', '12.09.2026', '12 Sep 2026', '']);

const partySandbox = {};
vm.createContext(partySandbox);
vm.runInContext(`${functionLine('plural')}; ${functionLine('partyLabel')}; this.values = [
  partyLabel({ adults: 2, childs: 0 }),
  partyLabel({ adults: 2, childs: 1 }),
  partyLabel({ adults: 1, childs: 2 }),
  partyLabel({ adults: 21, childs: 5 }),
  partyLabel({ adults: 0, childs: 0 })
];`, partySandbox);
assert.deepEqual(Array.from(partySandbox.values), [
  '2 взрослых',
  '2 взрослых · 1 ребёнок',
  '1 взрослый · 2 ребёнка',
  '21 взрослый · 5 детей',
  'Уточняется'
]);

assert.doesNotMatch(source, /window\.Search3Shortlist=.*displayDate|window\.Search3Shortlist=.*partyLabel/, 'presentation helpers stay private; shortlist public API is not expanded');
console.log('search3 shortlist trip summary: ok');
