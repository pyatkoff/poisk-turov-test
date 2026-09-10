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
    // Exercise the actual served shared header; retired fabricated fixtures stay retired.
    await page.evaluate(() => document.fonts.ready);
    assert.equal(await page.locator('.at-global-header').count(), 1, 'one current shared header');
    const headerGeometry = await page.evaluate(() => {
      const box = node => { const r = node.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, width: r.width, height: r.height }; };
      return {
        inner: box(document.querySelector('.at-global-header__inner')),
        actions: box(document.querySelector('.at-global-header__actions')),
        menu: box(document.querySelector('.at-global-header__mobile > summary')),
        nav: box(document.querySelector('.at-global-header__nav')),
        links: [...document.querySelectorAll('.at-global-header__nav a')].map(box),
      };
    });
    assert.equal(headerGeometry.links.length, 6, 'all current navigation destinations remain');
    if (width > 1024) {
      assert.ok(headerGeometry.links.every(link => link.height >= 44), 'desktop menu targets stay >=44px');
      assert.ok(headerGeometry.links.every(link => link.right <= headerGeometry.actions.left - 8), 'desktop menu stays clear of phone/actions');
      assert.ok(headerGeometry.actions.right <= headerGeometry.inner.right + 1, 'desktop actions remain inside the shell');
      assert.equal(new Set(headerGeometry.links.map(link => Math.round(link.top))).size, 1, 'ordinary desktop navigation stays in one row');
      assert.equal(headerGeometry.menu.width, 0, 'no second visible desktop menu');
    } else {
      assert.equal(headerGeometry.nav.width, 0, 'native menu replaces the desktop links');
      assert.ok(headerGeometry.menu.width >= 44 && headerGeometry.menu.height >= 44, 'native menu target stays >=44px');
      const menu = page.locator('.at-global-header__mobile'), toggle = menu.locator('summary');
      await toggle.focus();
      assert.ok(parseFloat(await toggle.evaluate(node => getComputedStyle(node).outlineWidth)) >= 3, 'menu keyboard focus stays visible');
      await page.keyboard.press('Space');
      assert.equal(await menu.evaluate(node => node.open), true, 'native menu opens with the keyboard');
      const panel = await menu.locator('.at-global-header__mobile-panel').boundingBox();
      assert.ok(panel.x >= 0 && panel.x + panel.width <= width + 1, 'menu panel stays inside the viewport');
      for (const link of await menu.locator('.at-global-header__mobile-panel > a').all()) assert.ok((await link.boundingBox()).height >= 44, 'menu links keep full targets');
      await page.screenshot({ path: path.join(output, `header-menu-${width}.png`), fullPage: true });
      await page.keyboard.press('Space');
      assert.equal(await menu.evaluate(node => node.open), false, 'native menu closes without another handler');
      // A short visual viewport must still expose the last contact destination.
      await page.setViewportSize({ width, height: 320 });
      await toggle.focus();
      await page.keyboard.press('Space');
      const links = menu.locator('.at-global-header__mobile-panel > a');
      for (let i = 0; i < await links.count(); i++) await page.keyboard.press('Tab');
      assert.equal(await links.last().evaluate(node => node === document.activeElement), true, 'keyboard reaches the last menu destination');
      const shortMenu = await menu.locator('.at-global-header__mobile-panel').evaluate(node => {
        const panel = node.getBoundingClientRect(), last = node.lastElementChild.getBoundingClientRect();
        return { top: panel.top, bottom: panel.bottom, scrollTop: node.scrollTop, lastTop: last.top, lastBottom: last.bottom, viewport: innerHeight, pageScroll: scrollY };
      });
      assert.ok(shortMenu.bottom <= shortMenu.viewport && shortMenu.top >= 0, 'short-screen menu fits vertically');
      assert.ok(shortMenu.scrollTop > 0, 'overflow scroll belongs to the existing menu');
      assert.ok(shortMenu.lastTop >= shortMenu.top && shortMenu.lastBottom <= shortMenu.bottom, 'last focused destination is fully visible');
      assert.equal(shortMenu.pageScroll, 0, 'menu keyboard navigation does not move the background page');
      fs.writeFileSync(path.join(output, `header-short-${width}.json`), JSON.stringify(shortMenu, null, 2) + '\n');
      await page.screenshot({ path: path.join(output, `header-short-${width}.png`), fullPage: false });
      await toggle.focus();
      await page.keyboard.press('Space');
      assert.equal(await menu.evaluate(node => node.open), false, 'short-screen menu closes natively');
      await page.setViewportSize({ width, height: 1000 });
    }
    fs.writeFileSync(path.join(output, `header-${width}.json`), JSON.stringify(headerGeometry, null, 2) + '\n');
    await page.screenshot({ path: path.join(output, `header-${width}.png`), fullPage: true });
    const recovery = page.locator('.catalog-recovery'), recoveryCopy = recovery.locator('.search-progress-error-copy'), recoveryRetry = recovery.locator('.catalog-retry');
    assert.equal(await recovery.isVisible(), true, 'blocked catalog fixture exposes the existing recovery owner');
    const recoveryGeometry = await page.evaluate(() => {
      const root = document.querySelector('.catalog-recovery'), copy = root.querySelector('.search-progress-error-copy'), title = copy.querySelector('strong'), message = copy.querySelector('span'), retry = root.querySelector('.catalog-retry');
      const box = node => { const r = node.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, width: r.width, height: r.height }; };
      return { root: box(root), title: box(title), message: box(message), retry: box(retry), copyDisplay: getComputedStyle(copy).display };
    });
    assert.equal(recoveryGeometry.copyDisplay, 'grid', 'recovery heading and explanation use one readable copy owner');
    assert.ok(recoveryGeometry.message.top >= recoveryGeometry.title.bottom, 'recovery explanation starts below its heading');
    assert.ok(recoveryGeometry.retry.height >= 44, 'recovery action keeps a full touch target');
    assert.ok(recoveryGeometry.root.width <= width, 'recovery card stays inside the viewport');
    if (width <= 560) assert.ok(recoveryGeometry.retry.width >= recoveryGeometry.root.width - 30, 'mobile recovery action spans the card');
    await page.screenshot({ path: path.join(output, `catalog-recovery-${width}.png`), fullPage: true });
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
    if (width >= 1199) {
      const rows = await page.locator('#tourSearch .search-group').evaluateAll(nodes => new Set(nodes.map(node => Math.round(node.getBoundingClientRect().top))).size);
      assert.equal(rows, width >= 1200 ? 1 : 2, 'served form changes group rows at the actual desktop boundary');
      if (width >= 1200) for (const input of await page.locator('#tourSearch input[type=date]').all()) {
        assert.ok((await input.boundingBox()).width >= 125, 'desktop dates retain readable native values');
      }
    }
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
// Existing homepage controls, offline catalogs, no real search/lead navigation.
async function runHomeRanges(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  const errors = [], handoffs = [], unexpected = [], catalogs = [];
  const origin = new URL(base).origin, target = new URL(base + '/poisk-turov/').pathname;
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== origin) return route.abort();
    if (request.method() !== 'GET') { unexpected.push(request.method() + ' ' + url.pathname); return route.abort(); }
    if (url.pathname === '/api-v2.php') {
      const action = url.searchParams.get('action');
      const rows = action === 'departures' ? [{ id: 1, name: 'Москва' }] : action === 'countries' ? [{ id: 4, name: 'Турция' }] : null;
      if (!rows) { unexpected.push(action); return route.abort(); }
      catalogs.push(action);
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(rows) });
    }
    if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) { unexpected.push(url.pathname); return route.abort(); }
    if (request.isNavigationRequest() && url.pathname === target) {
      handoffs.push([...url.searchParams]);
      return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>Captured handoff fixture</title>' });
    }
    return route.continue();
  });
  const initial = base + '/?from=1&country=4&dateFrom=2099-09-10&dateTo=2099-09-20&daysFrom=7&daysTill=10&count_people=3&child_age%5B%5D=8&child_age%5B%5D=6';
  try {
    assert.equal((await page.goto(initial, { waitUntil: 'domcontentloaded' })).status(), 200);
    await page.waitForFunction(() => document.querySelector('[data-home-search]')?.dataset.countriesBusy === 'false');
    const form = page.locator('[data-home-search]'), feedback = form.locator('[data-home-range-feedback]');
    const dateFrom = form.locator('[name=dateFrom]'), dateTo = form.locator('[name=dateTo]');
    const daysFrom = form.locator('[name=daysFrom]'), daysTill = form.locator('[name=daysTill]');
    const submit = form.locator('[type=submit]'), more = form.locator('.at-home-search__more');
    assert.deepEqual(catalogs, ['departures', 'countries']);
    assert.equal(await feedback.isVisible(), false, 'valid initial form has no error');
    await dateFrom.fill('2099-09-21');
    assert.equal(await dateTo.inputValue(), '2099-09-20', 'end date is not silently rewritten');
    assert.equal(await dateTo.getAttribute('min'), '2099-09-21');
    assert.equal(await dateTo.getAttribute('aria-invalid'), 'true');
    assert.equal(await dateTo.getAttribute('aria-describedby'), 'home-range-feedback');
    assert.equal(await feedback.isVisible(), true);
    await submit.click();
    assert.equal(await dateTo.evaluate(node => node === document.activeElement), true, 'native invalid submit focuses the date to repair');
    await more.click();
    assert.equal(page.url(), initial, 'both invalid handoffs stay on the original page');
    assert.equal(handoffs.length, 0);
    await daysFrom.selectOption('11');
    assert.equal(await daysTill.inputValue(), '10', 'night range is never silently rewritten');
    assert.equal(await daysTill.getAttribute('aria-invalid'), 'true');
    const text = await feedback.textContent();
    assert.match(text, /Вылет до/); assert.match(text, /ночей/);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
    await form.screenshot({ path: path.join(output, `home-range-errors-${width}.png`) });
    await dateFrom.fill('2099-09-10');
    assert.equal(await dateTo.getAttribute('aria-invalid'), null);
    assert.match(await feedback.textContent(), /ночей/, 'date repair retains the night warning');
    await daysFrom.selectOption('7');
    assert.equal(await feedback.isVisible(), false);
    assert.equal(await daysTill.getAttribute('aria-invalid'), null);
    assert.equal(await form.evaluate(node => node.checkValidity()), true);
    for (const control of await form.locator('input,select,button[type=submit]').all()) {
      assert.ok((await control.boundingBox()).height >= 44, 'home native controls retain full targets');
    }
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
    const expected = await form.evaluate(node => [...new FormData(node)]);
    assert.deepEqual(expected, [['from','1'],['country','4'],['dateFrom','2099-09-10'],['dateTo','2099-09-20'],['daysFrom','7'],['daysTill','10'],['count_people','3'],['child_age[]','8'],['child_age[]','6']]);
    // Observe one valid native submit without leaving the fixture or sending a search.
    await form.evaluate(node => {
      window.__homeNative = null;
      node.addEventListener('submit', event => { window.__homeNative = { blocked: event.defaultPrevented, fields: [...new FormData(node)] }; event.preventDefault(); }, { once: true });
    });
    await submit.click();
    assert.deepEqual(await page.evaluate(() => window.__homeNative), { blocked: false, fields: expected });
    await form.screenshot({ path: path.join(output, `home-range-repaired-${width}.png`) });
    await more.click();
    await page.waitForURL(url => url.pathname === target);
    assert.deepEqual(handoffs, [expected], 'advanced GET retains exact original field mapping');
    assert.deepEqual(catalogs, ['departures', 'countries'], 'range editing makes no new catalog request');
    assert.deepEqual(unexpected, []); assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(output, `home-range-${width}.json`), JSON.stringify({ width, catalogs, handoffs, unexpected, errors, supplier_requests: 0, lead_sent: 0 }, null, 2) + '\n');
  } finally { await page.close(); }
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [375, 1024, 1025, 1101, 1199, 1200, 1440]) await run(browser, width);
    for (const width of [375, 768, 1440]) await runHomeRanges(browser, width);
  } finally { await browser.close(); }
  console.log('SEARCH3_ENTRY_OWNER_BROWSER_OK widths=375,1024,1025,1101,1199,1200,1440 home_ranges=375,768,1440 lead_sent=0');
})().catch(error => { console.error(error); process.exitCode = 1; });
