'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const file = path.join(root, 'src/search3/behavior/results/shortlist.js');
const stylesFile = path.join(root, 'src/search3/styles/results-layout.css');
const source = fs.readFileSync(file, 'utf8');
const styles = fs.readFileSync(stylesFile, 'utf8');

function functionLine(name) {
  const prefix = `function ${name}(`;
  const line = source.split('\n').find(value => value.startsWith(prefix));
  assert.ok(line, `${name} must remain a focused helper in the canonical shortlist owner`);
  return line;
}

const displayDateSource = functionLine('displayDate');
assert.doesNotMatch(displayDateSource, /new\s+Date\s*\(|Date\.parse/, 'customer-facing departure formatting must stay date-only and timezone-free');
assert.match(source, /date:tour\.date/, 'the exact saved snapshot must retain the original provider date value');
assert.match(source, /\['date','Вылет',displayDate\(item\.date\)\]/, 'rendered comparison must format the saved departure without mutating it');
assert.match(source, /\['party','Туристы',partyLabel\(item\)\]/, 'rendered comparison must expose the saved party composition');

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

const rendererSandbox = {
  window: { addEventListener() {}, dispatchEvent() {} },
  document: { readyState: 'loading', documentElement: { dataset: {} }, body: null, addEventListener() {}, getElementById() { return null; }, querySelector() { return null; } }
};
vm.createContext(rendererSandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'v2/results-renderer-v5.js'), 'utf8'), rendererSandbox);
const compareSandbox = {
  window: rendererSandbox.window,
  text(value) {
    return String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
  },
  saved: [
    { hotelName: 'Отель с вариантами номера', country: 'Турция', region: 'Анталья', date: '2026-09-12', nights: 9, adults: 2, childs: 0, meal: 'Всё включено', room: 'STANDARD', placement: 'DBL', operator: 'TEST OPERATOR', observedPrice: 120000 },
    { hotelName: 'Отель с вариантами номера', country: 'Турция', region: 'Анталья', date: '2026-09-12', nights: 9, adults: 2, childs: 0, meal: 'Всё включено', room: 'FAMILY', placement: 'DBL', operator: 'TEST OPERATOR', observedPrice: 125000 },
    { hotelName: 'Третий отель', country: 'Турция', region: 'Кемер', date: '2026-09-12', nights: 9, adults: 2, childs: 0, meal: 'Всё включено', room: 'DELUXE', placement: 'DBL', operator: 'TEST OPERATOR', observedPrice: 130000 }
  ]
};
vm.createContext(compareSandbox);
vm.runInContext(`${displayDateSource}; ${functionLine('comparisonIdentity')}; ${functionLine('compareState')}; const state = compareState(); this.values = { labels: state.labels, keys: Array.from(state.different), minimumPrice: state.minimumPrice };`, compareSandbox);
assert.deepEqual(Array.from(compareSandbox.values.labels), ['отель', 'курорт', 'номер', 'цена при сохранении'], 'comparison summary names only saved dimensions that actually differ');
assert.deepEqual(Array.from(compareSandbox.values.keys), ['hotel', 'region', 'room', 'price'], 'common meal/date/night/party/operator values are not falsely marked as differences');
assert.equal(compareSandbox.values.minimumPrice, 120000, 'minimum comparison price is derived only from saved historical snapshots');
const same = { ...compareSandbox.saved[0], meal: 'AI', operator: 'ANEX TOUR' };
const compare = records => {
  compareSandbox.saved = records;
  const before = JSON.stringify(records);
  const result = vm.runInContext('compareState()', compareSandbox);
  assert.equal(JSON.stringify(records), before, 'display comparison never rewrites saved records or prices');
  return { keys: Array.from(result.different), common: Array.from(result.commonKeys) };
};
assert.deepEqual(compare([
  same,
  { ...same, date: '12.09.2026', meal: 'Всё включено', operator: 'Анекс' },
  { ...same, date: '2026-09-12T04:30:00Z', meal: 'All Inclusive', operator: 'ANEX' }
]), { keys: [], common: ['date', 'nights', 'party', 'meal', 'room', 'placement', 'operator'] }, 'equivalent displayed dates, reviewed meal and operator aliases do not create false differences');
assert.deepEqual(compare([same, { ...same, room: 'Standard room' }, { ...same, room: 'Стандартный номер' }]).keys, [], 'reviewed room aliases do not create false differences');
assert.deepEqual(compare([same, { ...same, room: 'EXECUTIVE SEA VIEW WITH BALCONY' }]).keys, ['room'], 'unknown extended room names remain distinct');
for (const meal of ['UAI', 'Soft AI', 'Not all inclusive', 'Premium All Inclusive']) {
  assert.deepEqual(compare([same, { ...same, meal }]).keys, ['meal'], meal + ' retains its distinct conditions');
}
assert.deepEqual(compare([same, { ...same, operator: 'ANEX SERVICES' }]).keys, ['operator'], 'similar unknown operator names are never guessed to be the reviewed brand');
assert.deepEqual(compare([same, { ...same, operator: '' }]).keys, ['operator'], 'missing operator is not filled from another record');
assert.deepEqual(compare([same, { ...same, date: '2026-09-13', meal: 'BB' }]).keys, ['date', 'meal'], 'different dates and meals remain visible');
assert.match(source, /Различаются: /, 'comparison renders a concise saved-difference summary');
assert.match(source, /Цена при сохранении · минимум среди сохранённых/, 'lowest saved price is explicitly historical comparison context');
assert.match(source, /compare\.different\.has\(fact\[0\]\).*' · отличается'/, 'differing saved fact rows are labelled semantically without a CSS-only cue');
for (const forbidden of ['fetch(', 'XMLHttpRequest', 'searchTour', 'loadOffers']) assert.equal(source.includes(forbidden), false, `comparison must not supplier re-query via ${forbidden}`);

assert.match(source, /mobileOpen=false/, 'mobile comparison starts collapsed');
assert.match(source, /className='search3-shortlist-disclosure'|node\('button','search3-shortlist-disclosure'/, 'canonical shortlist owns one explicit mobile disclosure');
assert.match(source, /aria-controls','search3ShortlistBody'/, 'mobile disclosure references the comparison body');
assert.match(source, /setMobileDisclosure\(!mobileOpen,true\)/, 'the disclosure toggles one canonical state and restores button focus');
assert.match(source, /v2:search-reset'.*mobileOpen=false/, 'new search/reset collapses the mobile comparison state');
assert.match(styles, /\.search3-shortlist-disclosure\{display:none\}/, 'desktop keeps the mobile disclosure out of the expanded comparison');
assert.match(styles, /@media\(max-width:600px\)[\s\S]*\.search3-shortlist-disclosure\{display:inline-flex/, 'mobile exposes the disclosure control');
assert.match(styles, /data-mobile-open=false[\s\S]*\.search3-shortlist__body\{display:none\}/, 'collapsed mobile comparison removes the large body from the result flow');
assert.match(styles, /\.search3-shortlist__items\{grid-template-columns:1fr;gap:12px;padding:0;overflow:visible\}/, 'opened mobile comparison uses readable full-width cards');
assert.doesNotMatch(styles, /grid-auto-columns:88%|scroll-snap-type:x|overflow-x:auto/, 'retired clipped mobile shortlist carousel rules stay deleted');

assert.doesNotMatch(source, /window\.Search3Shortlist=.*displayDate|window\.Search3Shortlist=.*partyLabel|window\.Search3Shortlist=.*compareState/, 'presentation helpers stay private; shortlist public API is not expanded');
console.log('search3 shortlist trip summary: ok');
