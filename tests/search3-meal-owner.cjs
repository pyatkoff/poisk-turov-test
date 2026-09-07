const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

let apiCalls = 0;
const select = { value: '', options: [] };
Object.defineProperty(select, 'innerHTML', {
  set(html) {
    this.options = [...html.matchAll(/<option value="([^"]*)">([^<]*)<\/option>/g)]
      .map(match => ({ value: match[1], textContent: match[2] }));
    this.value = '';
  }
});
const form = {
  elements: { food: select },
  querySelector() { return null; },
  addEventListener() {}
};
const window = {
  V2Runtime: {
    async api(action) {
      assert.equal(action, 'meals');
      apiCalls += 1;
      return [
        { id: 'BB', russianName: 'Завтраки' },
        { id: 'AI', russianName: 'Всё включено' }
      ];
    }
  }
};
const document = { getElementById(id) { return id === 'tourSearch' ? form : null; } };
const source = fs.readFileSync(path.join(__dirname, '../v2/catalogs-v2.js'), 'utf8');
vm.runInNewContext(source, { window, document, console, fetch, URLSearchParams, Set, Array, String, Object, Promise });

(async () => {
  const catalogs = window.V2Catalogs;
  assert.equal(typeof catalogs.loadMeals, 'function', 'current catalog owner exposes meal loading');
  assert.equal(await catalogs.loadMeals('AI'), true);
  assert.equal(select.value, 'AI', 'requested URL/current value is restored after options load');
  assert.deepEqual(select.options.map(option => option.value), ['', 'BB', 'AI']);
  assert.equal(await catalogs.loadMeals('BB'), true);
  assert.equal(apiCalls, 1, 'catalog is loaded once');

  const presentation = fs.readFileSync(path.join(__dirname, '../src/search3/behavior/search-form/secondary-controls.js'), 'utf8');
  assert.match(presentation, /new URLSearchParams\(window\.location\.search/);
  assert.match(presentation, /meal\.addEventListener\('focus',loadMeals/);
  assert.match(presentation, /closest\('\.search-filters-reset'\)/);
  assert.doesNotMatch(presentation, /meal-quick|meal-native-select|V2PrimaryMealUXV1/);
  console.log('SEARCH3_MEAL_OWNER_OK catalog=1 url_restore=1 reset_preservation=1');
})().catch(error => { console.error(error); process.exitCode = 1; });
