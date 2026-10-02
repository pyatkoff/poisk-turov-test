'use strict';
// Pure rendering characterization. Fictional pairs, no browser/provider/lead I/O.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');
const sourcePath = path.resolve(__dirname, '../v2/visual-search/flight-picker-v18.js');
const source = fs.readFileSync(sourcePath, 'utf8')+'\n'+fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/flight-picker-ui-v1.js'),'utf8');
const esc = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const text = value => value == null ? '' : String(value);
function freeze(value) {
  if (value && typeof value === 'object') {Object.values(value).forEach(freeze); Object.freeze(value);}
  return value;
}
const segment = (extra = {}) => ({company: 'Example Air', number: 'EX 101', baggage: 20, carryOn: '5 кг',
  ...extra, departure: {port: 'AAA', time: '09:10', date: '2026-10-01', ...extra.departure},
  arrival: {port: 'BBB', time: '12:20', date: '2026-10-01', ...extra.arrival}});
const pair = (forward, backward, total = 123456.5) => ({forward, backward, testTotal: total});
const cases = freeze([
  ['same-carrier', pair([segment()], [segment()])],
  ['different-carriers', pair([segment()], [segment({company: 'Return Air'})])],
  ['missing-carriers', pair([segment({company: ''})], [segment({company: null})])],
  ['missing-routes', {testTotal: null}],
  ['empty-routes', pair([], [], null)],
  ['one-direction', pair([segment()], [], 110000)],
  ['connections', pair([segment(), segment({departure: {port: 'BBB'}, arrival: {port: 'CCC'}})], [segment()])],
  ['airport-change', pair([segment(), segment({departure: {port: 'XXX'}, arrival: {port: 'CCC'}})], [segment()])],
  ['placeholder', pair([segment({number: 'EX000', baggage: 0, carryOn: '0', departure: {time: '00:00'}, arrival: {time: '00:00'}})], [segment()])],
  ['overnight', pair([segment({departure: {time: '23:10'}, arrival: {time: '03:20', date: '2026-10-02'}})], [segment()])],
  ['unknown-facts', pair([segment({baggage: null, carryOn: null, departure: {port: '', time: '', date: ''}})], [segment({baggage: -1, arrival: {port: '', date: 'not-a-date'}})])],
  ['mixed-allowances', pair([segment({baggage: 0, carryOn: '0'}), segment({baggage: 15})], [segment({baggage: 23})])],
  ['escaped-labels', pair([segment({company: '<script>"A"&B</script>', departure: {port: '<AAA>'}})], [segment({company: '<script>"A"&B</script>', arrival: {port: 'B&"C"'}})])]
]);
const alternative = freeze(pair([segment({company: 'Alternative Air'})], [segment({company: 'Alternative Air'})], 130000));
const routes = (html, end) => {
  const start = html.indexOf('<div class="flight-compact-routes">');
  const stop = html.indexOf(end, start);
  assert.ok(start >= 0 && stop > start, 'expected existing route wrapper');
  return html.slice(start, stop);
};
function collect(code) {
  const window = {};
  vm.runInNewContext(code, {window}, {filename: sourcePath});
  const api = window.AnyTourFlightPickerV18;
  assert.equal(Object.isFrozen(api), true);
  assert.deepEqual(Object.keys(api), ['render', 'bind', 'pairSummary', 'selectionSummary', 'allowanceValue', 'directionAllowance']);
  const output = [];
  for (const [name, candidate] of cases) {
    const priceCalls = [], fuelCalls = [];
    const helpers = {esc, text, money: n => Number(n).toFixed(2) + ' ₽',
      price(tour, variant) {priceCalls.push([tour.id, variant.testTotal]); return variant.testTotal;},
      legHTML: (segments, label) => `<p>${esc(label)}: ${esc(JSON.stringify(segments || []))}</p>`,
      fuelText(offer) {fuelCalls.push([offer.tour.id, offer.flightChoiceId]); return 'уточняется';}
    };
    const summary = api.pairSummary(candidate, helpers);
    const summaryRoutes = routes(summary, '<div class="chosen-flight-allowances">');
    assert.equal(priceCalls.length, 0, 'summary cannot calculate a price');
    assert.equal(fuelCalls.length, 0, 'summary cannot query fuel');
    if (name === 'same-carrier') {
      assert.equal((summary.match(/flight-pair-carriers/g) || []).length, 1);
      assert.equal((summary.match(/flight-route-carrier/g) || []).length, 0);
    }
    if (name === 'different-carriers') {
      assert.equal((summary.match(/flight-route-carrier/g) || []).length, 2);
      assert.equal(summary.includes('flight-pair-carriers'), false);
    }
    if (name === 'missing-routes' || name === 'empty-routes') assert.equal((summary.match(/Расписание уточняется/g) || []).length, 2);
    if (name === 'connections') assert.ok(summary.includes('Пересадка: BBB'));
    if (name === 'airport-change') assert.ok(summary.includes('Пересадка: BBB / XXX'));
    if (name === 'placeholder') assert.ok(summary.includes('Время уточняется'));
    if (name === 'escaped-labels') {assert.ok(summary.includes('&lt;script&gt;')); assert.ok(!summary.includes('<script>'));}
    output.push({name, summary});
    for (const selected of [null, '0', '1', '12', '99']) {
      priceCalls.length = 0; fuelCalls.length = 0;
      const offer = freeze({tour: {id: name}, variants: [candidate, alternative]});
      const html = api.render(offer, selected, helpers);
      assert.equal(routes(html, '<div class="flight-price">'), summaryRoutes, `${name}: picker/summary route parity`);
      assert.equal((html.match(/class="flight-option flight-option-compact"/g) || []).length, 2);
      assert.ok(html.includes('data-flight-index="0"')); assert.ok(html.includes('data-flight-index="1"'));
      assert.equal(html.includes('value="0" checked'), selected === '0');
      assert.equal(html.includes('value="1" checked'), selected === '1');
      assert.equal(priceCalls.length, selected === '0' || selected === '1' ? 3 : 2);
      assert.deepEqual(fuelCalls, [[name, '0'], [name, '1']]);
      if (name === 'escaped-labels') assert.ok(!html.includes('<script>'));
      output.push({name, selected, html, selection: api.selectionSummary(offer, selected), priceCalls: [...priceCalls], fuelCalls: [...fuelCalls]});
    }
  }
  return output;
}
const output = collect(source);
if (process.argv[2]) {
  assert.equal(process.argv[2], '--compare', 'expected --compare /path/to/exact-baseline.js');
  assert.ok(process.argv[3], 'baseline path required');
  const baseline = collect(fs.readFileSync(process.argv[3], 'utf8'));
  assert.deepEqual(output, baseline, 'rendered bytes or price/fuel invocation facts changed');
  console.log(`PASS: all ${output.length} rendering records byte-identical to the exact baseline.`);
}
console.log(`PASS: ${cases.length} pair cases / ${output.length} rendering records; picker/summary route parity, indices, escaping, price/fuel invocation facts; provider HTTP=0, real leads=0.`);
console.log('SHA256:', crypto.createHash('sha256').update(JSON.stringify(output)).digest('hex'));
