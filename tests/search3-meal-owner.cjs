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
  const lifecycle = fs.readFileSync(path.join(__dirname, '../v2/search-lifecycle-v6.js'), 'utf8');
  const markup = fs.readFileSync(path.join(__dirname, '../v2/index.php'), 'utf8');
  assert.equal(presentation.replace(/\/\*[\s\S]*?\*\//g, '').trim(), '', 'retired meal projection is provenance only');
  assert.match(source, /name==='food'.*loadMeals\(token\)/, 'catalog owner lazily loads meals on native focus');
  assert.match(lifecycle, /new URLSearchParams\(window\.location\.search\|\|''\)/, 'lifecycle owns URL hydration');
  assert.match(lifecycle, /'food'.*setField\(name,queryValue/, 'lifecycle restores food URL state');
  assert.match(lifecycle, /meal:f\.get\('food'\)\|\|''/, 'FormData keeps meal in the Tourvisor payload');
  assert.match(markup, /<select name="food">/, 'server markup keeps the canonical meal control');
  assert.doesNotMatch(presentation, /meal-quick|meal-native-select|V2PrimaryMealUXV1/);

  const rendererWindow = {};
  const rendererSource = fs.readFileSync(path.join(__dirname, '../v2/results-renderer-v5.js'), 'utf8');
  vm.runInNewContext(rendererSource, {
    window: rendererWindow,
    document: { readyState: 'loading', addEventListener() {} }
  });
  const results = rendererWindow.V2Results;
  const tour = { id: 'meal-shape-check', price: 125000, meal: { id: 7, fullName: 'All Inclusive' } };
  assert.match(results.tourRow(tour), /<small>Питание<\/small><b>All Inclusive<\/b>/,
    'supplier fullName-only meal appears in the tour facts');
  assert.equal(results.priceContext({ price: tour.price, tours: [tour] }), 'All Inclusive',
    'representative tour context uses the same meal normalization');
  for (const meal of ['Всё включено', { russianName: 'Всё включено', fullName: 'All Inclusive' },
    { fullRussianName: 'Всё включено', fullName: 'All Inclusive' },
    { name: 'Всё включено', fullName: 'All Inclusive' }]) {
    assert.match(results.tourRow({ ...tour, meal }), /<small>Питание<\/small><b>Всё включено<\/b>/,
      'existing meal names and Russian-label priority remain intact');
  }
  assert.doesNotMatch(results.tourRow({ ...tour, meal: { id: 7 } }), /<small>Питание<\/small>/,
    'unknown meal IDs are not invented as customer-facing labels');
  const unsafeRow = results.tourRow({ ...tour, meal: { fullName: '<img src=x onerror="bad()">' } });
  assert.ok(unsafeRow.includes('&lt;img src=x onerror=&quot;bad()&quot;&gt;'), 'supplier label is escaped');
  assert.doesNotMatch(unsafeRow, /<img/);
  assert.equal(results.textValue({ fullName: 'Not a meal' }), '', 'generic object display semantics are unchanged');
  assert.equal(tour.meal.fullName, 'All Inclusive', 'rendering leaves supplier data unchanged');

  const controllerSource = fs.readFileSync(path.join(__dirname, '../v2/tour-controller-v4.js'), 'utf8');
  async function selectedMeal(sample, withRenderer = true) {
    const events = new Map(), button = {}, message = {};
    let payload = null;
    const selected = {
      innerHTML: '', hidden: true, removeAttribute() {}, scrollIntoView() {},
      querySelector() { return null; }
    };
    const leadForm = {
      dataset: {},
      closest(selector) { return selector === '#selectedTour .lead-form' ? this : null; },
      querySelector(selector) { return selector === 'button[type="submit"]' ? button : message; }
    };
    const selectedWindow = {
      V2_CONFIG: { leadApi: '/mock-lead' },
      V2Results: withRenderer ? results : undefined,
      V2Runtime: {
        state: { searchId: 'meal-search' },
        async api(action) {
          assert.ok(action === 'tour' || action === 'flights', 'only fixture tour/flight reads are requested');
          return action === 'tour' ? sample : [];
        }
      },
      addEventListener() {}, dispatchEvent() {},
      async fetch(url, options) {
        assert.equal(url, '/mock-lead');
        assert.equal(options.method, 'POST');
        payload = JSON.parse(options.body);
        return { ok: true, async json() { return { ok: true, writes: 1 }; } };
      }
    };
    vm.runInNewContext(controllerSource, {
      window: selectedWindow,
      document: {
        cookie: '',
        getElementById(id) { return id === 'selectedTour' ? selected : null; },
        querySelector() { return null; },
        addEventListener(name, listener) { events.set(name, listener); }
      },
      CustomEvent: function () {},
      URLSearchParams,
      location: { search: '', href: 'https://example.test/poisk-turov/' },
      FormData: function () { this.get = name => ({ name: 'Fixture', phone: '+70000000000', consent: '1' }[name] || ''); }
    });
    await selectedWindow.V2TourController.selectTour(sample.id);
    const html = selected.innerHTML;
    events.get('submit')({ target: leadForm, preventDefault() {}, stopPropagation() {} });
    await new Promise(resolve => setImmediate(resolve));
    assert.ok(payload, 'canonical controller produces the captured fixture payload without network');
    return { html, payload };
  }

  const cases = [
    { meal: { name: 'RO', fullName: 'Без питания' }, label: 'Без питания', payload: 'RO' },
    { meal: { name: 'HB+', fullName: 'Полупансион плюс' }, label: 'Полупансион плюс', payload: 'HB+' },
    { meal: { fullName: 'Всё включено' }, label: 'Всё включено', payload: '' },
    { meal: { russianName: 'Завтраки', name: 'BB', fullName: 'Bed & Breakfast' }, label: 'Завтраки', payload: 'Завтраки' },
    { meal: { fullRussianName: 'Завтраки', name: 'BB', fullName: 'Bed & Breakfast' }, label: 'Завтраки', payload: 'Завтраки' },
    { meal: { name: 'Всё включено', fullName: 'All Inclusive' }, label: 'Всё включено', payload: 'Всё включено' },
    { meal: { name: 'Lunch', fullName: 'Другой текст поставщика' }, label: 'Lunch', payload: 'Lunch' },
    { meal: { name: 'RO', fullName: '   ' }, label: 'RO', payload: 'RO' },
    { meal: { id: 7 }, label: '', payload: '' },
    { meal: null, label: '', payload: '' },
    { meal: 'Всё включено', label: 'Всё включено', payload: '' }
  ];
  assert.equal(typeof results.mealLabel, 'function', 'one canonical display helper is exposed');
  for (const item of cases) {
    const sample = Object.freeze({ ...tour, meal: item.meal && typeof item.meal === 'object' ? Object.freeze(item.meal) : item.meal });
    const before = JSON.stringify(sample);
    assert.equal(results.mealLabel(sample), item.label, 'display normalization uses supplier labels without inventing meal IDs');
    assert.equal(results.priceContext({ price: sample.price, tours: [sample] }), item.label);
    const current = await selectedMeal(sample), standalone = await selectedMeal(sample, false);
    assert.ok(current.html.includes('<span>Питание</span><b>' + (item.label || '—') + '</b>'), 'selected fact uses the same visible label');
    assert.equal(current.payload.meal, item.payload, 'existing lead meal value remains unchanged');
    assert.deepEqual(current.payload, standalone.payload, 'display helper does not change any lead field');
    assert.equal(current.payload.price, tour.price, 'display normalization preserves price');
    assert.equal(current.payload.tourId, tour.id, 'display normalization preserves selected identity');
    assert.equal(JSON.stringify(sample), before, 'both display owners leave supplier data unchanged');
  }
  const unsafe = Object.freeze({ ...tour, meal: Object.freeze({ name: 'RO', fullName: '<img src=x onerror="bad()">' }) });
  const escaped = await selectedMeal(unsafe);
  assert.ok(escaped.html.includes('&lt;img src=x onerror=&quot;bad()&quot;&gt;'), 'expanded supplier label is escaped in selected facts');
  assert.doesNotMatch(escaped.html, /<img/);
  assert.ok(results.tourRow(unsafe).includes('&lt;img src=x onerror=&quot;bad()&quot;&gt;'), 'expanded supplier label is escaped in result facts');
  assert.equal(escaped.payload.meal, 'RO', 'unsafe display label never enters the lead mapping');
  console.log('SEARCH3_MEAL_OWNER_OK catalog=1 url_restore=1 reset_preservation=1 renderer_full_name=1 shared_display=1 selected_display=1 payload_unchanged=1');
})().catch(error => { console.error(error); process.exitCode = 1; });
