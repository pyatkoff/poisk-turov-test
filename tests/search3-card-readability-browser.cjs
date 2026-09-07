const { chromium } = require('playwright');
const declarationAudit = require('../docs/project/search3-active-css-declarations.json');

const base = process.env.SEARCH3_VISUAL_BASE || 'http://127.0.0.1:8099';

const cardHtml = `
  <article class="hotel-card" data-hotel-id="readability-fixture">
    <div class="hotel-main">
      <div class="hotel-photo"><img alt="" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='675'%3E%3Crect width='1200' height='675' fill='%239ac7df'/%3E%3Cpath d='M0 440L260 260 430 390 690 150 1200 470V675H0Z' fill='%233f7f75'/%3E%3Crect x='410' y='270' width='430' height='250' rx='18' fill='%23f5efe0'/%3E%3C/svg%3E"></div>
      <div class="hotel-body">
        <div class="search3-hotel-heading"><h3 class="hotel-title">LONG BEACH RESORT HOTEL WITH A LONG NAME</h3><span class="search3-hotel-category">5★</span></div>
        <p class="hotel-place">Турция · Анталья · Сиде</p>
        <div class="hotel-decision-line"><span class="hotel-decision-rating">★ 4,7/5</span><span class="hotel-decision-sea">До моря 350 м</span></div>
        <div class="hotel-bottom"><div class="hotel-best-offer"><small>За весь тур</small><strong class="hotel-price">от 148 500 ₽</strong><small class="hotel-price-context"><span>2 взрослых</span></small></div></div>
        <div class="search3-hotel-facts">
          <span><small>Вылет</small><b>12 сент. 2026</b></span>
          <span><small>Ночей</small><b>9</b></span>
          <span><small>Питание</small><b>Всё включено</b></span>
          <span><small>Рейс</small><b>Чартер</b></span>
        </div>
        <div class="search3-hotel-action"><button class="search3-show-tours" type="button" data-search3-show-label="Показать 16 туров">Показать 16 туров</button></div>
      </div>
    </div>
  </article>`;

async function verifyToolbarBoundary(browser) {
  const page = await browser.newPage({ viewport: { width: 375, height: 900 } });
  // This fixture never talks to production search, lead delivery or analytics.
  await page.route('**/*', route => {
    const url = route.request().url();
    if (!url.startsWith(base + '/') || /\/(?:api[^/]*|lead[^/]*)\.php/.test(url)) return route.abort();
    return route.continue();
  });
  try {
    await page.goto(base + '/ci-search3.php', { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.Search3CandidateResultsV1 && window.V2MobileResultsFiltersV1);
    // Aborted catalogue requests finish asynchronously and clear loading options.
    // Wait for that fixture-only failure before capturing parameters for resize.
    await page.waitForFunction(() => document.getElementById('tourSearch').dataset.catalogSource === 'partial');
    await page.evaluate(() => {
      const form = document.getElementById('tourSearch');
      for (const [name, value, label] of [['from','1','Fixture departure'], ['country','4','Fixture country']]) {
        const select = form.elements[name];
        if (![...select.options].some(option => option.value === value)) select.add(new Option(label, value));
        select.value = value;
      }
    });
    await page.evaluate(html => {
      document.getElementById('results').innerHTML = html;
      document.getElementById('results').hidden = false;
      document.getElementById('resultsTools').hidden = false;
      document.body.classList.add('search3-has-results');
      window.dispatchEvent(new CustomEvent('v2:results-rendered', { detail: { items: [
        { id: 'readability-fixture', name: 'Toolbar fixture', price: 148500, tours: [] }
      ] } }));
    }, cardHtml);
    await page.waitForSelector('.search3-mobile-toolbar');
    await page.evaluate(() => { window.__toolbarBoundaryNode = document.querySelector('.search3-mobile-toolbar'); });
    const formValues = () => page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()].sort((a,b) => a[0].localeCompare(b[0])));
    const before = JSON.stringify(await formValues());
    for (const width of [999,1000,1348,1440,999,430]) {
      await page.setViewportSize({ width, height: 900 });
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      const toolbar = page.locator('.search3-mobile-toolbar');
      const geometry = await toolbar.evaluate(node => ({ display: getComputedStyle(node).display, height: node.getBoundingClientRect().height, sameNode: node === window.__toolbarBoundaryNode }));
      if (!geometry.sameNode || await toolbar.count() !== 1) throw new Error('TOOLBAR_OWNER_REPLACED ' + width);
      if (width >= 1000) {
        if (geometry.display !== 'none' || geometry.height !== 0 || await page.locator('.search3-mobile-sort select').isVisible()) throw new Error('TOOLBAR_DESKTOP_LEAK ' + width + ' ' + JSON.stringify(geometry));
        if (!await page.locator('#sortResults').isVisible()) throw new Error('TOOLBAR_NATIVE_SORT_HIDDEN ' + width);
      } else {
        if (!await toolbar.isVisible() || !await page.locator('.search3-mobile-filter-slot > .mrf-bar').isVisible()) throw new Error('TOOLBAR_COMPACT_MISSING ' + width);
        await page.locator('.mrf-open').click();
        await page.waitForFunction(() => document.querySelector('.mrf-sheet').classList.contains('is-open'));
        await page.keyboard.press('Escape');
        if (!await page.locator('.mrf-open').evaluate(node => node === document.activeElement)) throw new Error('TOOLBAR_FOCUS_RETURN ' + width);
      }
      if (JSON.stringify(await formValues()) !== before) throw new Error('TOOLBAR_RESIZE_CHANGED_FORM ' + width + ' ' + JSON.stringify({ before: JSON.parse(before), after: await formValues() }));
      if (width === 1348 || width === 430) await page.screenshot({ path: 'standalone-content-artifacts/toolbar-boundary-' + width + '.png', fullPage: true, animations: 'disabled' });
      console.log('SEARCH3_TOOLBAR_BOUNDARY_OK ' + width + ' ' + JSON.stringify(geometry));
    }
    const native = page.locator('#sortResults'), proxy = page.locator('.search3-mobile-sort select');
    const values = await native.locator('option').evaluateAll(nodes => nodes.map(node => node.value));
    await proxy.selectOption(values[1], { force: true });
    if (await native.inputValue() !== values[1]) throw new Error('TOOLBAR_PROXY_SORT_HANDOFF');
    await native.selectOption(values[0], { force: true });
    if (await proxy.inputValue() !== values[0]) throw new Error('TOOLBAR_NATIVE_SORT_HANDOFF');
  } finally { await page.close(); }
}

function px(value) {
  return Number.parseFloat(String(value || '0')) || 0;
}

async function verifyEmptyLocalRail(browser) {
  const page = await browser.newPage({ viewport: { width: 1348, height: 900 } });
  await page.route('**/*', route => {
    const url = route.request().url();
    return !url.startsWith(base + '/') || /\/(?:api[^/]*|lead[^/]*)\.php/.test(url)
      ? route.abort() : route.continue();
  });
  try {
    await page.goto(base + '/ci-search3.php', { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.Search3CandidateResultsV1 && window.V2Results &&
      document.getElementById('tourSearch').dataset.catalogSource === 'partial');
    // Reuse the existing deterministic card fixture and execute all real Search3
    // presentation subscribers. The adapter never invokes external search/lead.
    await page.evaluate(html => {
      window.V2Results.render = items => {
        const results = document.getElementById('results');
        results.hidden = false;
        results.innerHTML = items.length ? html : '';
        document.getElementById('resultsTools').hidden = !items.length;
        document.getElementById('resultsSearchSummary').hidden = !items.length;
        window.dispatchEvent(new CustomEvent('v2:results-rendered', { detail: { items } }));
      };
      window.V2Results.render([{ id: 'readability-fixture', name: 'Empty-filter fixture', price: 148500, tours: [] }]);
    }, cardHtml);
    const slider = page.locator('[data-s3-price]');
    await slider.waitFor({ state: 'visible' });
    await slider.press('Home');
    await page.waitForFunction(() => !document.querySelector('#results .hotel-card'));
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    if (!await slider.isVisible() || !await page.locator('.results-filter-rail').isVisible()) throw new Error('EMPTY_LOCAL_RAIL_HIDDEN');
    if (await page.locator('[data-s3-count]').textContent() !== '0') throw new Error('EMPTY_LOCAL_COUNT');
    for (const width of [1000,1440]) {
      await page.setViewportSize({ width, height: 900 });
      if (!await slider.isVisible()) throw new Error('EMPTY_LOCAL_RESIZE_HIDDEN ' + width);
    }
    await page.screenshot({ path: 'standalone-content-artifacts/empty-local-filter-1440.png', fullPage: true });
    await slider.press('End');
    await page.waitForSelector('#results .hotel-card');
    if (await page.locator('[data-s3-count]').textContent() !== '1') throw new Error('EMPTY_LOCAL_RESTORE_COUNT');
    await page.evaluate(() => { document.getElementById('results').innerHTML = ''; window.dispatchEvent(new CustomEvent('v2:search-reset')); });
    if (await page.locator('.results-filter-rail').getAttribute('data-s3-empty-results') !== '') throw new Error('EMPTY_LOCAL_RESET_MARKER');
    console.log('SEARCH3_EMPTY_LOCAL_RAIL_OK 1348/1000/1440 zero matches, resize, restore, reset');
  } finally { await page.close(); }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [375, 430, 1024, 1348, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const response = await page.goto(base + '/ci-search3.php', { waitUntil: 'domcontentloaded', timeout: 30000 });
      if (!response || response.status() !== 200) throw new Error(width + ': Search3 fixture HTTP failure');
      await page.waitForSelector('body.search3-candidate');
      if (width === 375) {
        const witnesses = declarationAudit.assets.flatMap(asset => asset.rows);
        const unsupported = await page.evaluate(rows => rows.filter(row =>
          !CSS.supports(row.property, row.later_value)), witnesses);
        if (unsupported.length) throw new Error('CSS_OVERRIDE_FALLBACK_REQUIRED ' + JSON.stringify(unsupported));
        console.log('SEARCH3_CSS_OVERRIDE_WITNESSES_SUPPORTED ' + witnesses.length);
      }
      await page.evaluate(html => {
        const body = document.body;
        body.classList.add('search3-results-active', 'search3-has-results');
        const results = document.getElementById('results');
        results.hidden = false;
        results.innerHTML = html;
        const tools = document.getElementById('resultsTools');
        if (tools) tools.hidden = false;
      }, cardHtml);
      await page.waitForTimeout(100);
      const state = await page.evaluate(() => {
        const q = selector => document.querySelector(selector);
        const box = selector => {
          const node = q(selector);
          if (!node) return null;
          const value = node.getBoundingClientRect();
          return { width: value.width, height: value.height, right: value.right };
        };
        const font = selector => getComputedStyle(q(selector)).fontSize;
        return {
          viewport: document.documentElement.clientWidth,
          documentWidth: document.documentElement.scrollWidth,
          card: box('.hotel-card'),
          photo: box('.hotel-photo'),
          placeFont: font('.hotel-place'),
          badgeFont: font('.hotel-decision-line>span'),
          factLabelFont: font('.search3-hotel-facts small'),
          factValueFont: font('.search3-hotel-facts b'),
          priceContextFont: font('.hotel-price-context'),
          actionFont: font('.search3-show-tours'),
          priceContext: q('.hotel-price-context').textContent.replace(/\s+/g, ' ').trim(),
          factLabels: [...document.querySelectorAll('.search3-hotel-facts small')].map(node => node.textContent.trim())
        };
      });
      await page.screenshot({ path: `standalone-content-artifacts/readability-${width}.png`, fullPage: true, animations: 'disabled' });
      console.log('SEARCH3_CARD_READABILITY_STATE ' + width + ' ' + JSON.stringify(state));
      if (!state.card || !state.photo) throw new Error(width + ': card fixture missing');
      if (state.documentWidth > state.viewport + 2 || state.card.right > state.viewport + 2) {
        throw new Error(width + ': card overflows viewport ' + JSON.stringify(state));
      }
      if (state.priceContext !== '2 взрослых') throw new Error(width + ': duplicate price context ' + state.priceContext);
      if (new Set(state.factLabels).size !== 4) throw new Error(width + ': duplicated facts ' + JSON.stringify(state.factLabels));
      const mobile = width <= 760;
      if (mobile && (state.photo.height < 180 || state.photo.height > 241)) throw new Error(width + ': mobile photo height ' + state.photo.height);
      const minima = mobile
        ? { placeFont: 12, badgeFont: 12, factLabelFont: 11, factValueFont: 14, priceContextFont: 12, actionFont: 14 }
        : { placeFont: 12, badgeFont: 11, factLabelFont: 11, factValueFont: 13, priceContextFont: 12, actionFont: 14 };
      for (const [key, minimum] of Object.entries(minima)) {
        if (px(state[key]) + 0.01 < minimum) throw new Error(width + ': ' + key + ' below ' + minimum + 'px: ' + state[key]);
      }
      console.log('SEARCH3_CARD_READABILITY_OK ' + width + ' ' + JSON.stringify(state));

      // Label recovery belongs to result mutations, including enabling a button.
      await page.evaluate(() => {
        const button = document.createElement('button');
        button.id = 'label-recovery-fixture';
        button.hidden = true;
        button.dataset.search3ProductionLabel = 'Проверить тур';
        button.textContent = 'Проверяем…';
        button.disabled = true;
        document.getElementById('results').appendChild(button);
      });
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
      if (await page.locator('#label-recovery-fixture').textContent() !== 'Проверяем…') throw new Error(width + ': disabled loading label overwritten');
      await page.locator('#label-recovery-fixture').evaluate(button => { button.disabled = false; });
      await page.waitForFunction(() => document.getElementById('label-recovery-fixture').textContent === 'Проверить тур');
      await page.locator('#label-recovery-fixture').evaluate(button => button.remove());
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));

      // A real viewport resize must retain an explicitly opened search editor.
      await page.locator('#resultsSearchEdit').click();
      await page.waitForFunction(() => document.body.classList.contains('search3-editing-search'));
      // Decorators may reparent fields; compare named values, keeping repeated-name order.
      const editingFields = await page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()].sort((a, b) => a[0].localeCompare(b[0])));
      await page.setViewportSize({ width: width + 1, height: 860 });
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      if (!await page.evaluate(() => document.body.classList.contains('search3-editing-search'))) throw new Error(width + ': resize closed search editor');
      if (!await page.locator('#tourSearch').isVisible()) throw new Error(width + ': editor hidden after resize');
      const afterResize = await page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()].sort((a, b) => a[0].localeCompare(b[0])));
      if (JSON.stringify(editingFields) !== JSON.stringify(afterResize)) throw new Error(width + ': resize changed search parameters ' + JSON.stringify({ before: editingFields, after: afterResize }));
      await page.setViewportSize({ width, height: 900 });
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      const editorGeometry = await page.evaluate(() => {
        const box = selector => document.querySelector('#tourSearch ' + selector).getBoundingClientRect().toJSON();
        return {
          dates: box('.search3-dates'), nights: box('.search3-nights'),
          dateInput: box('.search3-dates .search3-direct-control'),
          nightInput: box('.search3-nights .search3-direct-control'),
          viewport: innerWidth, documentWidth: document.documentElement.scrollWidth,
          bodyClass: document.body.className,
          grid: getComputedStyle(document.querySelector('#tourSearch .search3-primary-grid')).gridTemplateColumns,
          dateColumn: getComputedStyle(document.querySelector('#tourSearch .search3-dates')).gridColumn,
          dateStyle: document.querySelector('#tourSearch .search3-dates').getAttribute('style')
        };
      });
      if (editorGeometry.documentWidth > editorGeometry.viewport + 2) throw new Error(width + ': editor overflows viewport');
      console.log('SEARCH3_EDITOR_GEOMETRY_STATE ' + width + ' ' + JSON.stringify(editorGeometry));
      await page.screenshot({ path: `standalone-content-artifacts/editor-${width}.png`, fullPage: true, animations: 'disabled' });
      if (width >= 761 && editorGeometry.dates.width < editorGeometry.nights.width * 1.8) throw new Error(width + ': date range lost its wide column');
      if (editorGeometry.dateInput.width < 120 || editorGeometry.nightInput.width < 48) throw new Error(width + ': native date/night value clipped ' + JSON.stringify(editorGeometry));
      console.log('SEARCH3_EDITOR_GEOMETRY_OK ' + width + ' ' + JSON.stringify(editorGeometry));
      // Restore the result fixture for the independent calendar checks below.
      await page.evaluate(() => document.body.classList.remove('search3-editing-search'));
      await page.waitForFunction(() => !document.body.classList.contains('search3-editing-search'));

      // Exercise the production calendar module inside the actual Search3 shell.
      // Capture submissions locally: no Tourvisor search or lead is sent by this fixture.
      await page.evaluate(() => {
        window.__calendarSubmissions = [];
        window.V2SearchLifecycle.submit = () => {
          window.__calendarSubmissions.push([...new FormData(document.getElementById('tourSearch')).entries()].sort((a, b) => a[0].localeCompare(b[0])));
        };
        window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items: [
          { tours: [{ date: '10.09.2026', price: 1350000 }, { date: '2026-09-11', price: 1234567 }] },
          { tours: [{ date: '10.09.2026', price: 1290000 }, { date: '2026-09-12', price: 0 }] }
        ] } }));
      });
      await page.waitForSelector('#currentPriceCalendar .search3-price-calendar');
      const calendar = page.locator('#currentPriceCalendar');
      if (!await calendar.isVisible()) throw new Error(width + ': Search3 hides the production price calendar');
      const expanded = await calendar.locator('details').getAttribute('open');
      if ((expanded !== null) === mobile) throw new Error(width + ': wrong responsive calendar default');
      if (mobile) await calendar.locator('summary').click();
      const dates = await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.map(node => node.dataset.calendarDate));
      if (JSON.stringify(dates) !== '["2026-09-10","2026-09-11"]') throw new Error(width + ': unpriced date is offered');
      const minimum = await calendar.locator('.is-best strong').innerText();
      if (minimum.replace(/\s/g, '') !== '1234567₽') throw new Error(width + ': daily minimum changed');
      await calendar.scrollIntoViewIfNeeded();
      await page.screenshot({ path: `standalone-content-artifacts/calendar-${width}.png`, fullPage: false, animations: 'disabled' });
      const geometry = await calendar.evaluate(node => ({
        width: document.documentElement.clientWidth,
        documentWidth: document.documentElement.scrollWidth,
        right: node.getBoundingClientRect().right,
        targets: [...node.querySelectorAll('[data-calendar-date]')].every(button => button.getBoundingClientRect().height >= 44)
      }));
      if (geometry.documentWidth > geometry.width + 2 || geometry.right > geometry.width + 2 || !geometry.targets) {
        throw new Error(width + ': calendar geometry ' + JSON.stringify(geometry));
      }
      // Responsive controls can move in the DOM while retaining the same form
      // values. Compare named fields; retain the order of repeated child ages.
      const unchangedFields = entries => {
        const fields = new Map();
        for (const [name, value] of entries) {
          if (['dateFrom', 'dateTo'].includes(name)) continue;
          if (!fields.has(name)) fields.set(name, []);
          fields.get(name).push(value);
        }
        return [...fields.entries()].sort(([a], [b]) => a.localeCompare(b));
      };
      const before = await page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()].sort((a, b) => a[0].localeCompare(b[0])));
      await calendar.locator('[data-calendar-date="2026-09-11"]').click();
      const submissions = await page.evaluate(() => window.__calendarSubmissions);
      if (submissions.length !== 1) throw new Error(width + ': calendar must submit exactly once');
      const submitted = Object.fromEntries(submissions[0]);
      if (submitted.dateFrom !== '2026-09-11' || submitted.dateTo !== '2026-09-11') throw new Error(width + ': selected departure date lost');
      const preservedBefore = unchangedFields(before), preservedAfter = unchangedFields(submissions[0]);
      if (JSON.stringify(preservedBefore) !== JSON.stringify(preservedAfter)) throw new Error(width + ': calendar changed other search parameters ' + JSON.stringify({ before: preservedBefore, after: preservedAfter }));
      const resultCountBeforeTour = await page.locator('#results .hotel-card').count();
      const searchValuesBeforeTour = await page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()].sort((a, b) => a[0].localeCompare(b[0])));
      await page.evaluate(() => {
        const selected = document.getElementById('selectedTour');
        selected.innerHTML = '<div class="selected-loading">Загружаем тур…</div>';
        selected.hidden = false;
      });
      await page.waitForFunction(() => document.getElementById('selectedTour').getAttribute('aria-busy') === 'true');
      // Let the canonical selected-tour observer derive the shell state. A body
      // class alone races its next sync because an empty/hidden tour is closed.
      await page.evaluate(() => {
        const selected = document.getElementById('selectedTour');
        selected.innerHTML = '<div class="selected-head"><h2>Проверочный выбранный тур</h2></div>';
        selected.hidden = false;
        window.dispatchEvent(new CustomEvent('v2:selected-tour-opened'));
      });
      await page.waitForSelector('#selectedTour');
      await page.waitForFunction(() => document.getElementById('selectedTour').getAttribute('aria-busy') === 'false');
      await page.waitForFunction(() => document.body.classList.contains('search3-selected-open'));
      if (await calendar.isVisible()) throw new Error(width + ': results calendar leaks into selected tour');
      for (const selector of ['.results-layout', '#resultsSearchSummary', '#resultsTools']) {
        if (await page.locator(selector).isVisible()) throw new Error(width + ': search controls leak into selected tour: ' + selector);
      }
      await page.evaluate(() => {
        const selected = document.getElementById('selectedTour');
        selected.hidden = true;
        // Real Back retains both tour DOM and final-review presentation markers.
        selected.classList.add("search3-final-review");
        selected.setAttribute("data-search3-final-layout", "maket7");
        window.dispatchEvent(new CustomEvent('v2:selected-tour-closed'));
      });
      await page.waitForFunction(() => !document.body.classList.contains('search3-selected-open'));
      await page.waitForSelector('.results-layout');
      if (await page.locator('#selectedTour').isVisible()) throw new Error(width + ': closed tour remains visible below results');
      if (!await page.locator('#selectedTour').evaluate(node => node.hidden && node.children.length > 0 && node.getBoundingClientRect().height === 0)) throw new Error(width + ': hidden retained tour occupies layout');
      await page.screenshot({ path: 'standalone-content-artifacts/return-' + width + '.png', fullPage: true, animations: 'disabled' });
      if (await page.locator('#results .hotel-card').count() !== resultCountBeforeTour) throw new Error(width + ': Back lost results');
      const searchValuesAfterTour = await page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()].sort((a, b) => a[0].localeCompare(b[0])));
      if (JSON.stringify(unchangedFields(searchValuesBeforeTour)) !== JSON.stringify(unchangedFields(searchValuesAfterTour))) throw new Error(width + ': Back changed search parameters');
      await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset')));
      if (await calendar.isVisible()) throw new Error(width + ': stale calendar remains after reset');
      if (!await calendar.evaluate(node => node.hidden && node.children.length === 0)) throw new Error(width + ': reset did not clear calendar data');
      console.log('SEARCH3_CALENDAR_OK ' + width + ' daily minima, date handoff, responsive display and reset');
      await page.close();
    }
    await verifyToolbarBoundary(browser);
    await verifyEmptyLocalRail(browser);
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exit(1);
});
