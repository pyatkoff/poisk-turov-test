'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'v2/prototype-search/app.js'), 'utf8');

const helperStart = source.indexOf('function refreshCalendarPriceView()');
const helperEnd = source.indexOf('\nfunction monthFrame', helperStart);
assert.ok(helperStart >= 0 && helperEnd > helperStart, 'calendar async hydration helper is present');
const helperSource = source.slice(helperStart, helperEnd);
assert.match(helperSource, /datePrices\.clear\(\)/, 'derived per-day calendar memo is invalidated');
assert.match(helperSource, /refreshCalendarPrices\(\)/, 'calendar cells rerender after invalidation');
assert.ok(
  helperSource.indexOf('datePrices.clear()') < helperSource.indexOf('refreshCalendarPrices()'),
  'memo is cleared before rerender reads calendar prices'
);

const context = {
  datePrices: new Map([['2026-10-17', null], ['2026-10-18', 250000]]),
  observedSize: null,
};
context.refreshCalendarPrices = () => {
  context.observedSize = context.datePrices.size;
};
vm.createContext(context);
vm.runInContext(`${helperSource}\nthis.refreshCalendarPriceViewTest=refreshCalendarPriceView;`, context);
context.refreshCalendarPriceViewTest();
assert.equal(context.datePrices.size, 0, 'stale blank/current-only day prices are removed');
assert.equal(context.observedSize, 0, 'rerender observes the invalidated memo, not stale values');

const loadStart = source.indexOf('function loadCalendarPrices()');
const loadEnd = source.indexOf('\nfunction openDestination', loadStart);
assert.ok(loadStart >= 0 && loadEnd > loadStart, 'calendar async loader is present');
const loadSource = source.slice(loadStart, loadEnd);
assert.match(
  loadSource,
  /calendarHotels=\[\.\.\.snapshots\.values\(\)\]\.flatMap\(row=>row\.hotels\);calendarObservations=\[\.\.\.snapshots\.values\(\)\]\.flatMap\(row=>row\.observations\);refreshCalendarPriceView\(\)/,
  'every accepted async LOCAL/observation snapshot invalidates and rerenders day prices'
);
assert.match(
  loadSource,
  /calendarHotels=\[\];calendarObservations=\[\];const snapshots=new Map\(\);refreshCalendarPriceView\(\)/,
  'starting a new calendar load also clears the previous memo before rendering'
);
assert.doesNotMatch(
  loadSource,
  /calendarObservations=\[\.\.\.snapshots\.values\(\)\]\.flatMap\(row=>row\.observations\);refreshCalendarPrices\(\)/,
  'async hydration cannot rerender while retaining stale day memo values'
);

console.log('SEARCH3_CALENDAR_ASYNC_HYDRATION_OK');
