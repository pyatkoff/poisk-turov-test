const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const utilPath = path.join(root, 'src/search3/behavior/booking/format.js');
assert.ok(fs.existsSync(utilPath), 'private booking format owner must exist');
assert.equal(
  fs.readFileSync(path.join(root, 'src/search3/behavior/flight-presentation.js'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').trim(),
  '',
  'standalone flight presentation owner is retired'
);
assert.equal(
  fs.readFileSync(path.join(root, 'src/search3/behavior/presentation-text.js'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').trim(),
  '',
  'standalone text presentation owner is retired'
);

const context = {};
vm.runInNewContext(fs.readFileSync(utilPath, 'utf8'), context, { filename: 'presentation-text.js' });
const format = { esc: context.esc, text: context.text, people: context.people, place: context.place };
const flight = { placeholder: context.placeholder, flightLabel: context.supplierFlightLabel, baggage: context.flightBaggage };
assert.equal(format.esc(`<a title="x">Tom & Jerry's</a>`), '&lt;a title=&quot;x&quot;&gt;Tom &amp; Jerry&#39;s&lt;/a&gt;');
assert.equal(format.text({ russianName: '', fullRussianName: 'Полное название', name: 'Fallback' }), 'Полное название');
assert.equal(format.text([{ name: 'DBL' }, { title: 'SEA VIEW' }, null]), 'DBL, SEA VIEW');
assert.equal(format.text(0), '0');
assert.equal(format.people({ adults: 2, childs: 1 }), '2 взр. + 1 дет.');
assert.equal(format.people({ adults: 0, childs: 0 }), '—');
assert.equal(format.place({ hotel: { country: { russianName: 'Турция' }, region: 'Анталья', subRegion: { title: 'Сиде' } } }), 'Турция, Анталья, Сиде');
assert.equal(format.place(null), '—');

const sources = Object.fromEntries(['booking-summary.js'].map((name) => [
  name,
  fs.readFileSync(path.join(root, 'src/search3/behavior', name), 'utf8')
]));
const served = fs.readFileSync(path.join(root, 'v2/search3-results-filters-v1.js'), 'utf8');
assert.ok(!served.includes('Search3PresentationText'));
assert.ok(!served.includes('Search3FlightPresentation'));
for (const name of Object.keys(sources)) {
  assert.ok(sources[name].includes('/* @include behavior/booking/format.js */'), `${name} includes its private format owner`);
  assert.ok(!sources[name].includes('window.Search3PresentationText'), `${name} needs no global text adapter`);
  assert.ok(!sources[name].includes('window.Search3FlightPresentation'), `${name} needs no global flight adapter`);
}
assert.match(fs.readFileSync(path.join(root, 'src/search3/behavior/booking/services.js'), 'utf8'), /function renderServices/);
assert.equal(fs.readFileSync(path.join(root, 'src/search3/behavior/final-sections.js'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').trim(), '', 'standalone services owner is retired');

console.log('PASS: private booking format owner preserves escaping, supplier text, compact party and place labels');
