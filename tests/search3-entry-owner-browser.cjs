/* Focused exact-artifact native entry check; no supplier or lead requests. */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.SEARCH3_VISUAL_BASE, output = process.env.SEARCH3_ENTRY_OWNER_OUTPUT;
assert.ok(base && new URL(base).hostname === '127.0.0.1' && output);
fs.mkdirSync(output, { recursive: true });
async function run(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  const errors = [], blocked = [];
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== new URL(base).origin || request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) {
      blocked.push(`${request.method()} ${url.pathname}`);
      return route.abort();
    }
    return route.continue();
  });
  try {
    const response = await page.goto(base + '/poisk-turov/?count_people=3&child_count=1&child_age%5B%5D=8&daysFrom=7&daysTill=10', { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200);
    // The presentation-ready flag precedes async catalog boot and URL hydration.
    // Even the deliberately blocked catalog fixture must settle before editing.
    await page.waitForFunction(() => {
      const form = document.getElementById('tourSearch');
      return form?.dataset.search3Ready === '1' && form.dataset.catalogSource && window.V2SearchLifecycle;
    });
    const adults = page.locator('#tourSearch select[name=count_people]'), children = page.locator('#tourSearch select[name=child_count]');
    assert.equal(await adults.inputValue(), '3', 'URL adult value stays on original control');
    assert.equal(await children.inputValue(), '1', 'URL child count survives native presentation');
    assert.equal(await page.locator('#childAges select').inputValue(), '8', 'URL child age survives');
    for (const selector of ['input[type=date]', 'select[name=daysFrom]', 'select[name=daysTill]', 'select[name=count_people]', 'select[name=child_count]', '.search-submit']) {
      for (const control of await page.locator('#tourSearch ' + selector).all()) {
        assert.equal(await control.isVisible(), true, selector + ' remains directly visible');
        assert.ok((await control.boundingBox()).height >= 44, selector + ' native target >=44px');
      }
    }
    assert.equal(await page.locator('.search3-composite,.search3-direct-control,.search3-primary-grid,.search3-quality,.search3-quick,.search3-tourists__pop,.search3-tourists__summary,.search3-mobile-search-filter-button,.search3-price-calendar').count(), 0,
      'retired entry projection is absent');
    assert.equal(await page.locator('#tourSearch > details.extras').count(), 1, 'canonical advanced filters remain');
    assert.equal(await page.locator('#v2-search-title').textContent(), 'Поиск туров', 'candidate has the compact reference heading');
    assert.equal(await page.locator('#tourSearch .search-group legend span').count(), 0, 'decorative numbered steps are removed');
    const formGeometry = await page.evaluate(() => {
      const box = selector => { const r = document.querySelector(selector).getBoundingClientRect(); return { top: r.top, bottom: r.bottom, width: r.width }; };
      return { hero: box('.v2-product-hero'), form: box('#tourSearch'), ages: box('#childAges'), extras: box('#tourSearch > .extras'), submit: box('.search-submit') };
    });
    assert.ok(formGeometry.hero.bottom - formGeometry.hero.top < 140, 'compact hero leaves room for trip parameters');
    assert.ok(formGeometry.ages.width > formGeometry.form.width - 50, 'URL child ages take a full form row');
    if (width > 700) assert.ok(Math.abs(formGeometry.extras.top - formGeometry.submit.top) <= 1, 'closed extras and primary action share a desktop row');
    await page.locator('#tourSearch > .extras > summary').click();
    const openExtras = await page.locator('#tourSearch > .extras').boundingBox();
    const openSubmit = await page.locator('.search-submit').boundingBox();
    assert.ok(openExtras.width > formGeometry.form.width - 50, 'open advanced parameters take the full form width');
    assert.ok(openSubmit.y >= openExtras.y + openExtras.height, 'primary action remains below expanded fields');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'expanded form has no horizontal overflow');
    const flightTargets = page.locator('#tourSearch .toggle');
    assert.equal(await flightTargets.count(), 2, 'original two flight switches stay the only owners');
    for (const target of await flightTargets.all()) {
      assert.ok((await target.boundingBox()).height >= 44, 'flight option has a full 44px label target');
      const input = target.locator('input');
      await target.locator('span').click();
      assert.equal(await input.isChecked(), true, 'clicking the flight label changes its canonical checkbox');
      await input.focus();
      await page.keyboard.press('Space');
      assert.equal(await input.isChecked(), false, 'native keyboard toggle stays intact');
    }
    await flightTargets.first().locator('span').click();
    await page.screenshot({ path: path.join(output, `entry-expanded-${width}.png`), fullPage: true });
    await flightTargets.first().locator('span').click();
    await page.locator('#tourSearch > .extras > summary').click();
    await adults.selectOption('4');
    const editingLayout = await page.evaluate(() => {
      const results = document.querySelector('#results');
      const probe = document.createElement('div'); results.append(probe);
      document.body.classList.add('search3-editing-search');
      const display = getComputedStyle(document.querySelector('#tourSearch')).display;
      probe.remove(); document.body.classList.remove('search3-editing-search');
      return display;
    });
    assert.equal(editingLayout, 'grid', 'editing an existing search retains the same form layout');
    await children.selectOption('2');
    await page.waitForFunction(() => document.querySelectorAll('#childAges select').length === 2);
    await page.locator('#childAges select').nth(0).selectOption('8');
    await page.locator('#childAges select').nth(1).selectOption('6');
    for (const [name, value] of [['daysFrom', '7'], ['daysTill', '10']]) {
      const control = page.locator(`#tourSearch select[name=${name}]`);
      assert.equal(await control.count(), 1, 'one native nights owner');
      assert.equal(await control.inputValue(), value, 'URL nights survive on the native picker');
      assert.deepEqual(await control.locator('option').evaluateAll(options => options.map(option => option.value)), Array.from({ length: 28 }, (_, i) => String(i + 1)), 'all supported nights are selectable');
    }
    await page.locator('select[name=daysFrom]').selectOption('8');
    await page.locator('select[name=daysTill]').selectOption('9');
    const payload = await page.evaluate(() => {
      const data = new FormData(document.getElementById('tourSearch'));
      return { adults: data.get('count_people'), children: data.get('child_count'), ages: data.getAll('child_age[]'), from: data.get('daysFrom'), till: data.get('daysTill') };
    });
    assert.deepEqual(payload, { adults: '4', children: '2', ages: ['8', '6'], from: '8', till: '9' }, 'native changes retain canonical form field mapping');
    const nightsContract = await page.evaluate(() => {
      const lifecycle = window.V2SearchLifecycle, originalUrl = location.href;
      const date = new Date(); date.setDate(date.getDate() + 2);
      const future = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
      const valid = { ...lifecycle.params(), departureId: '1', countryId: '4', dateFrom: future, dateTo: future };
      const rejected = [[0, 7], [7, 29], [1.5, 7], [9, 8], [1, 12]].map(([nightsFrom, nightsTo]) => !!lifecycle.validate({ ...valid, nightsFrom, nightsTo }));
      history.replaceState(null, '', location.pathname + '?days_from=28&days_till=28');
      lifecycle.hydrateUrlState();
      const aliases = [document.forms.tourSearch.elements.daysFrom.value, document.forms.tourSearch.elements.daysTill.value];
      history.replaceState(null, '', location.pathname + '?daysFrom=0&daysTill=29');
      lifecycle.hydrateUrlState();
      const invalidUrlRejected = !!lifecycle.validate({ ...valid, nightsFrom: document.forms.tourSearch.elements.daysFrom.value, nightsTo: document.forms.tourSearch.elements.daysTill.value });
      for (const name of ['daysFrom', 'daysTill']) {
        const control = document.forms.tourSearch.elements[name];
        [...control.options].filter(option => Number(option.value) < 1 || Number(option.value) > 28).forEach(option => option.remove());
      }
      document.forms.tourSearch.elements.daysFrom.value = '8';
      document.forms.tourSearch.elements.daysTill.value = '9';
      history.replaceState(null, '', originalUrl);
      return { valid: lifecycle.validate(valid), rejected, aliases, invalidUrlRejected, pending: lifecycle.pending };
    });
    assert.deepEqual(nightsContract, { valid: '', rejected: [true, true, true, true, true], aliases: ['28', '28'], invalidUrlRejected: true, pending: false }, 'native picker retains canonical range validation and URL aliases without auto-search');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'entry has no horizontal overflow');
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(output, `entry-${width}.json`), JSON.stringify({ width, payload, blocked, lead_sent: 0 }, null, 2) + '\n');
    await page.screenshot({ path: path.join(output, `entry-${width}.png`), fullPage: true });
    const calendarItems = [{ tours: [
      { date: '2026-09-12', price: 132500 },
      { date: '2026-09-13', price: 118900 },
      { date: '2026-09-14', price: 126000 },
    ] }];
    await page.evaluate(items => window.V2CurrentPriceCalendar.render(items), calendarItems);
    const calendar = page.locator('#currentPriceCalendar'), disclosure = calendar.locator('details'), summary = disclosure.locator('summary');
    assert.equal(await calendar.count(), 1, 'one existing calendar owner');
    assert.equal(await disclosure.evaluate(node => node.open), width > 700, 'desktop opens and mobile starts compact');
    assert.ok((await summary.boundingBox()).height >= 44, 'calendar disclosure target >=44px');
    assert.ok(await summary.evaluate(node => parseFloat(getComputedStyle(node).paddingRight) >= 32), 'disclosure reserves room for its indicator');
    assert.equal(await page.evaluate(() => {
      const form = document.getElementById('tourSearch'), calendar = document.getElementById('currentPriceCalendar'), tools = document.getElementById('resultsTools');
      return !!(form.compareDocumentPosition(calendar) & Node.DOCUMENT_POSITION_FOLLOWING) && !!(calendar.compareDocumentPosition(tools) & Node.DOCUMENT_POSITION_FOLLOWING);
    }), true, 'calendar stays between the form and result toolbar');
    await page.screenshot({ path: path.join(output, `calendar-initial-${width}.png`), fullPage: true });
    await summary.focus();
    await page.keyboard.press('Space');
    assert.equal(await disclosure.evaluate(node => node.open), width <= 700, 'native keyboard toggle works');
    await page.evaluate(items => window.V2CurrentPriceCalendar.render(items), calendarItems);
    assert.equal(await disclosure.evaluate(node => node.open), width <= 700, 'rerender preserves the user disclosure choice');
    if (!(await disclosure.evaluate(node => node.open))) await summary.click();
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-13', 'existing minimum-price selection is unchanged');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'open calendar does not overflow the page');
    await page.screenshot({ path: path.join(output, `calendar-open-${width}.png`), fullPage: true });
    await page.evaluate(() => {
      window.__calendarSubmitCount = 0;
      window.__calendarOriginalSubmit = window.V2SearchLifecycle.submit;
      window.V2SearchLifecycle.submit = () => window.__calendarSubmitCount++;
    });
    await calendar.locator('[data-calendar-date="2026-09-13"]').click();
    const calendarAction = await page.evaluate(() => {
      const form = document.getElementById('tourSearch');
      window.V2SearchLifecycle.submit = window.__calendarOriginalSubmit;
      return [form.elements.dateFrom.value, form.elements.dateTo.value, window.__calendarSubmitCount];
    });
    assert.deepEqual(calendarAction, ['2026-09-13', '2026-09-13', 1], 'date action retains one canonical submit with exact dates');
    await page.evaluate(() => window.V2CurrentPriceCalendar.render([{ tours: [{ date: '2026-09-13', price: 118900 }] }]));
    assert.equal(await calendar.isVisible(), false, 'one date does not invent a price comparison');
    assert.deepEqual(errors, [], 'calendar interaction has no page errors');
  } finally { await page.close(); }
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  try { for (const width of [375, 1440]) await run(browser, width); }
  finally { await browser.close(); }
  console.log('SEARCH3_ENTRY_OWNER_BROWSER_OK widths=375,1440 lead_sent=0');
})().catch(error => { console.error(error); process.exitCode = 1; });
