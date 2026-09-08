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
const format = { esc: context.esc, text: context.text };
const flight = { placeholder: context.placeholder, flightLabel: context.supplierFlightLabel };
assert.equal(format.esc(`<a title="x">Tom & Jerry's</a>`), '&lt;a title=&quot;x&quot;&gt;Tom &amp; Jerry&#39;s&lt;/a&gt;');
assert.equal(format.text({ russianName: '', fullRussianName: 'Полное название', name: 'Fallback' }), 'Полное название');
assert.equal(format.text([{ name: 'DBL' }, { title: 'SEA VIEW' }, null]), 'DBL, SEA VIEW');
assert.equal(format.text(0), '0');
assert.equal(flight.placeholder({ number: 'SU000', departure: { time: '00:00' }, arrival: { time: '00:00' } }), true);
assert.equal(flight.flightLabel({ forward: [{ company: { name: 'SU' }, number: 'SU123' }] }), 'SU SU123');

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
assert.ok(!fs.existsSync(path.join(root, 'src/search3/behavior/booking/services.js')), 'duplicate services renderer is retired');
assert.equal(fs.readFileSync(path.join(root, 'src/search3/behavior/final-sections.js'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').trim(), '', 'standalone services owner is retired');

console.log('PASS: compact booking format owner preserves escaping and supplier flight labels');
