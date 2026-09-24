'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'v2/prototype-search/app.js'), 'utf8');

function extractFunction(name) {
  const marker = `function ${name}(`;
  const start = source.indexOf(marker);
  assert.notEqual(start, -1, `${name} must exist in app.js`);
  const open = source.indexOf('{', start);
  assert.notEqual(open, -1);
  let depth = 0;
  for (let i = open; i < source.length; i++) {
    if (source[i] === '{') depth++;
    if (source[i] === '}') {
      depth--;
      if (depth === 0) return source.slice(start, i + 1);
    }
  }
  throw new Error(`unterminated ${name}`);
}

const loadResultCalendarSource = extractFunction('loadResultCalendar');
const trip = {
  origin: 'Москва',
  country: '4',
  from: '2026-10-01',
  to: '2026-10-07',
  minNights: 7,
  maxNights: 7,
  adults: 2,
  ages: []
};
const fullFilters = {
  hotelId: 501,
  q: 'rixos',
  stars: [5],
  meals: ['AI'],
  resorts: ['Анталья'],
  operators: ['ANEX'],
  flight: ['charter'],
  amenities: ['1:7'],
  min: 100000,
  max: 500000,
  rating: true,
  beach: true,
  family: true,
  spa: true
};

function harness() {
  const calls = [];
  const context = {
    catalogReady: true,
    state: {
      hasSearched: true,
      search: structuredClone(trip),
      filters: structuredClone(fullFilters)
    },
    structuredClone,
    AbortController,
    resultCalendar: {key: null, hotels: [], observations: [], phase: 'idle', controller: null},
    renderCalendarStrip() {},
    data: {
      calendarPrices(search, from, to, signal, filters, show) {
        calls.push({
          search: structuredClone(search),
          from,
          to,
          filters: structuredClone(filters),
          aborted: signal.aborted,
          hasShow: typeof show === 'function'
        });
        return Promise.resolve({hotels: [], observations: [], partial: false});
      }
    }
  };
  vm.createContext(context);
  const loadResultCalendar = vm.runInContext(`(${loadResultCalendarSource})`, context);
  return {context, calls, loadResultCalendar};
}

const flush = () => new Promise(resolve => setImmediate(resolve));

test('result-strip calendar forwards the complete active filter scope', async () => {
  const h = harness();
  h.loadResultCalendar();
  await flush();

  assert.equal(h.calls.length, 1);
  assert.deepEqual(h.calls[0].search, trip);
  assert.deepEqual(h.calls[0].filters, fullFilters);
  assert.equal(h.calls[0].filters.hotelId, 501);
  assert.deepEqual(h.calls[0].filters.operators, ['ANEX']);
  assert.deepEqual(h.calls[0].filters.flight, ['charter']);
  assert.deepEqual(h.calls[0].filters.amenities, ['1:7']);
  assert.equal(h.calls[0].filters.rating, true);
  assert.equal(h.calls[0].filters.beach, true);
  assert.equal(h.calls[0].filters.family, true);
  assert.equal(h.calls[0].filters.spa, true);
});

test('calendar cache key changes when previously dropped filters change', async () => {
  const h = harness();

  h.loadResultCalendar();
  await flush();
  assert.equal(h.calls.length, 1);

  h.context.state.filters.operators = ['Библио-Глобус'];
  h.loadResultCalendar();
  await flush();
  assert.equal(h.calls.length, 2, 'operator-only change must invalidate result calendar cache');
  assert.deepEqual(h.calls[1].filters.operators, ['Библио-Глобус']);

  h.context.state.filters.hotelId = 777;
  h.loadResultCalendar();
  await flush();
  assert.equal(h.calls.length, 3, 'exact-hotel change must invalidate result calendar cache');
  assert.equal(h.calls[2].filters.hotelId, 777);
  assert.equal(h.calls[1].aborted, false);
  assert.equal(h.calls[2].hasShow, true);
});
