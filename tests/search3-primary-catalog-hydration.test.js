'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../v2/catalogs-v2.js'), 'utf8');
const lines = source.split('\n');
const line = prefix => {
  const found = lines.find(value => value.startsWith(prefix));
  assert.ok(found, `missing ${prefix}`);
  return found;
};

function primaryFixture({ advanced = false, critical = true } = {}) {
  const calls = [];
  const context = {
    active: () => true,
    criticalReady: critical,
    advancedOpen: () => advanced,
    loadRegions: async () => calls.push('regions'),
    loadMeals: async () => calls.push('meals')
  };
  vm.createContext(context);
  vm.runInContext(line('async function ensurePrimary(') + '\nthis.ensurePrimary=ensurePrimary;', context);
  return { calls, ensurePrimary: context.ensurePrimary };
}

test('closed primary form hydrates region and meal without opening advanced filters', async () => {
  const fixture = primaryFixture();
  await fixture.ensurePrimary(1);
  assert.deepEqual(fixture.calls, ['regions', 'meals']);
});

test('open advanced form leaves region hydration to its existing destination chain', async () => {
  const fixture = primaryFixture({ advanced: true });
  await fixture.ensurePrimary(1);
  assert.deepEqual(fixture.calls, ['meals']);
});

test('primary hydration stays inert before critical country catalogs are ready', async () => {
  const fixture = primaryFixture({ critical: false });
  await fixture.ensurePrimary(1);
  assert.deepEqual(fixture.calls, []);
});

test('canonical lifecycle refreshes permanent catalogs without expanding hotel discovery', () => {
  const init = line('async function init()');
  const change = line('async function handleChange(');
  const focus = line("form.addEventListener('focusin'");
  const hotels = line('async function loadHotels(');
  const primary = line('async function ensurePrimary(');

  assert.match(init, /clearDependentCatalogs\(\);await ensurePrimary\(token\);if\(active\(token\)&&advancedOpen\(\)\)await ensureAdvanced\(token,true\)/);
  assert.equal((change.match(/await ensurePrimary\(token\)/g) || []).length, 2,
    'departure and country changes both refresh the permanent catalogs');
  assert.ok(focus.indexOf("name==='food'") < focus.indexOf("if(!advancedOpen())return"),
    'meal hydration is reachable while advanced filters are closed');
  assert.equal((focus.match(/name==='food'/g) || []).length, 1, 'one meal focus owner remains');

  assert.doesNotMatch(primary, /loadHotels|loadArrivals|loadOperators|loadHotelTypes|loadHotelServices/,
    'primary hydration does not open advanced supplier/catalog work');
  assert.match(hotels, /!country\|\|!region/, 'exact-hotel list remains region-dependent');
  assert.match(hotels, /limit:100/, 'existing bounded hotel catalog remains unchanged');
  assert.match(hotels, /api\('hotels',\{countryId:country,regionId:region/,
    'hotel catalog endpoint contract remains unchanged');
});
