'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const rootDir = path.resolve(__dirname, '..');
const window = {
  V2Runtime: {},
  Search3CanonicalProfilesV1: { create: () => ({}) },
  location: new URL('https://anytoour.ru/_preview/search3-local-candidate/prototype-search/')
};
const sandbox = { window, URL, URLSearchParams, console };
vm.createContext(sandbox);
vm.runInContext(
  fs.readFileSync(path.join(rootDir, 'v2/prototype-search/data.js'), 'utf8'),
  sandbox,
  { filename: 'v2/prototype-search/data.js' }
);

const data = window.AnyTourPrototypeData;
const trip = {
  origin: 'Москва',
  country: '4',
  from: '2026-09-25',
  to: '2026-09-30',
  minNights: 7,
  maxNights: 7,
  adults: 2,
  ages: []
};

data.catalog.departures.push({ id: 1, name: 'Москва' });
data.catalog.countries.push({ id: 4, name: 'Турция' });
data.catalog.meals.push(
  { id: 5, name: 'AI', fullName: 'AI — Всё включено' },
  { id: 6, name: 'Все Включено' },
  { id: 7, name: 'Завтрак' },
  { id: 8, name: 'BB', fullName: 'BB - Только завтрак' },
  { id: 3, name: 'HB', fullName: 'HB — Полупансион' },
  { id: 3, name: 'Полупансион' }
);

assert.equal(
  data.params(trip, [], { meals: ['Всё включено'] }).meal,
  '',
  'canonical AI aliases with different supplier IDs must not narrow the first search to one raw ID'
);
assert.equal(
  data.params(trip, [], { meals: ['Завтраки'] }).meal,
  '',
  'canonical breakfast aliases with different supplier IDs must not narrow the first search to one raw ID'
);
assert.equal(
  data.params(trip, [], { meals: ['Полупансион'] }).meal,
  '3',
  'one distinct supplier meal ID stays an efficient upstream narrowing even when catalog rows repeat it'
);
assert.equal(
  data.params(trip, [], { meals: ['Всё включено', 'Полупансион'] }).meal,
  '',
  'the single-value upstream contract cannot encode multiple canonical meal choices'
);
assert.throws(
  () => data.params(trip, [], { meals: ['Неизвестное питание'] }),
  /Выберите питание из загруженного справочника/,
  'unknown canonical meal still fails closed instead of silently broadening'
);

console.log('search3 prototype canonical meal scope: PASS');
