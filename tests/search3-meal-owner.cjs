const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { loadSearch3Renderer } = require('./helpers/search3-renderer-bootstrap');

// SEARCH owns exact visible meal facts here; semantic equivalence belongs to LOCAL evidence.
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
const catalogSource = fs.readFileSync(path.join(__dirname, '../v2/catalogs-v2.js'), 'utf8');
vm.runInNewContext(catalogSource, { window, document, console, fetch, URLSearchParams, Set, Array, String, Object, Promise });

(async () => {
  const catalogs = window.V2Catalogs;
  assert.equal(typeof catalogs.loadMeals, 'function', 'catalog owner exposes meal loading');
  assert.equal(await catalogs.loadMeals('AI'), true);
  assert.equal(select.value, 'AI', 'requested exact meal value is restored after options load');
  assert.deepEqual(select.options.map(option => option.value), ['', 'BB', 'AI']);
  assert.equal(await catalogs.loadMeals('BB'), true);
  assert.equal(apiCalls, 1, 'meal catalog is loaded once');

  const presentation = fs.readFileSync(path.join(__dirname, '../src/search3/behavior/search-form/secondary-controls.js'), 'utf8');
  const lifecycle = fs.readFileSync(path.join(__dirname, '../v2/search-lifecycle-v6.js'), 'utf8');
  const markup = fs.readFileSync(path.join(__dirname, '../v2/index.php'), 'utf8');
  assert.equal(presentation.replace(/\/\*[\s\S]*?\*\//g, '').trim(), '', 'retired meal projection stays provenance-only');
  assert.match(catalogSource, /name==='food'.*loadMeals\(token\)/, 'catalog owner lazily loads meals on native focus');
  assert.match(lifecycle, /new URLSearchParams\(window\.location\.search\|\|''\)/, 'lifecycle owns URL hydration');
  assert.match(lifecycle, /'food'.*setField\(name,queryValue/, 'lifecycle restores the exact food URL state');
  assert.match(lifecycle, /meal:f\.get\('food'\)\|\|''/, 'FormData keeps the exact meal search value');
  assert.match(markup, /<select name="food">/, 'server markup keeps the canonical meal control');
  assert.doesNotMatch(presentation, /meal-quick|meal-native-select|V2PrimaryMealUXV1/, 'retired duplicate meal controls stay retired');

  const rendererWindow = {};
  loadSearch3Renderer({
    window: rendererWindow,
    document: { readyState: 'loading', addEventListener() {}, querySelector() { return null; } }
  });
  const results = rendererWindow.V2Results;
  assert.equal(typeof results.mealIdentity, 'function', 'renderer exposes exact meal identity for result facets');
  assert.equal(typeof results.mealLabel, 'function', 'renderer exposes one exact meal display helper');

  const cases = [
    [{ meal: 'AI' }, 'AI', 'meal:label:ai'],
    [{ meal: { name: 'AI' } }, 'AI', 'meal:label:ai'],
    [{ meal: { name: 'AI', fullName: 'Всё включено' } }, 'Всё включено', 'meal:label:всё включено'],
    [{ meal: { fullName: 'All Inclusive' } }, 'All Inclusive', 'meal:label:all inclusive'],
    [{ meal: 'AI-WITHOUT ALCOHOL' }, 'AI-WITHOUT ALCOHOL', 'meal:label:ai-without alcohol'],
    [{ meal: 'Soft AI' }, 'Soft AI', 'meal:label:soft ai'],
    [{ meal: 'UAI' }, 'UAI', 'meal:label:uai'],
    [{ meal: '  Half   Board\t' }, 'Half Board', 'meal:label:half board'],
    [{ meal: { russianName: 'Завтраки', name: 'BB', fullName: 'Bed & Breakfast' } }, 'Завтраки', 'meal:label:завтраки'],
    [{ meal: null }, '', null]
  ];
  for (const [offer, label, key] of cases) {
    const frozenMeal = offer.meal && typeof offer.meal === 'object' ? Object.freeze({ ...offer.meal }) : offer.meal;
    const sample = Object.freeze({ ...offer, meal: frozenMeal });
    const before = JSON.stringify(sample);
    assert.equal(results.mealLabel(sample), label, 'Search3 preserves the supplied meal fact and only normalizes whitespace');
    const identity = results.mealIdentity(sample);
    assert.equal(identity && identity.key, key, 'meal facet identity follows the exact visible label');
    assert.equal(identity && identity.label, key ? label : null, 'meal identity label equals the visible supplier/local fact');
    assert.equal(JSON.stringify(sample), before, 'meal presentation never mutates the source offer');
  }

  assert.notEqual(results.mealIdentity({ meal: 'AI' }).key, results.mealIdentity({ meal: 'All Inclusive' }).key,
    'supplier aliases are not silently merged by Search3');
  assert.notEqual(results.mealIdentity({ meal: 'AI' }).key, results.mealIdentity({ meal: 'Всё включено' }).key,
    'supplier code and localized label remain different facts until LOCAL mapping proves equivalence');

  const tour = Object.freeze({
    id: 'meal-shape-check', price: 125000, date: '2026-10-05', nights: 7,
    meal: Object.freeze({ name: 'AI', fullName: 'Всё включено' }), roomType: 'Fixture room'
  });
  assert.match(results.tourRow(tour), /<small>Питание<\/small><b>Всё включено<\/b>/,
    'offer row renders an explicitly supplied readable fullName');
  assert.equal(results.priceContext({ price: tour.price, tours: [tour] }), '05.10.2026 · 7 ноч. · Всё включено',
    'collapsed context reuses the same exact meal display fact');
  assert.doesNotMatch(results.tourRow({ ...tour, meal: { id: 7 } }), /<small>Питание<\/small>/,
    'unknown numeric meal IDs are never invented as customer-facing labels');

  const unsafe = Object.freeze({ ...tour, meal: Object.freeze({ fullName: '<img src=x onerror="bad()">' }) });
  const unsafeRow = results.tourRow(unsafe);
  assert.ok(unsafeRow.includes('&lt;img src=x onerror=&quot;bad()&quot;&gt;'), 'supplier/local meal label is escaped');
  assert.doesNotMatch(unsafeRow, /<img/);
  assert.equal(JSON.stringify(tour.meal), JSON.stringify({ name: 'AI', fullName: 'Всё включено' }), 'rendering keeps source meal data intact');

  console.log('SEARCH3_MEAL_OWNER_OK catalog=1 url_restore=1 exact_identity=1 no_semantic_guess=1 escaped_display=1 immutable_input=1');
})().catch(error => { console.error(error); process.exitCode = 1; });
