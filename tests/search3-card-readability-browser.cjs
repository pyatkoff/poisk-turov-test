const { chromium } = require('playwright');

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
        <div class="search3-hotel-action"><div class="search3-hotel-action__copy"><strong>16 туров</strong><span>доступно по выбранным датам</span></div><button class="search3-show-tours" type="button">Показать туры</button></div>
      </div>
    </div>
  </article>`;

function px(value) {
  return Number.parseFloat(String(value || '0')) || 0;
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [375, 430, 1024, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const response = await page.goto(base + '/ci-search3.php', { waitUntil: 'domcontentloaded', timeout: 30000 });
      if (!response || response.status() !== 200) throw new Error(width + ': Search3 fixture HTTP failure');
      await page.waitForSelector('body.search3-candidate');
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

      // Exercise the production calendar module inside the actual Search3 shell.
      // Capture submissions locally: no Tourvisor search or lead is sent by this fixture.
      await page.evaluate(() => {
        window.__calendarSubmissions = [];
        window.V2SearchLifecycle.submit = () => {
          window.__calendarSubmissions.push([...new FormData(document.getElementById('tourSearch')).entries()]);
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
      const before = await page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()]);
      await calendar.locator('[data-calendar-date="2026-09-11"]').click();
      const submissions = await page.evaluate(() => window.__calendarSubmissions);
      if (submissions.length !== 1) throw new Error(width + ': calendar must submit exactly once');
      const submitted = Object.fromEntries(submissions[0]);
      if (submitted.dateFrom !== '2026-09-11' || submitted.dateTo !== '2026-09-11') throw new Error(width + ': selected departure date lost');
      const preservedBefore = unchangedFields(before), preservedAfter = unchangedFields(submissions[0]);
      if (JSON.stringify(preservedBefore) !== JSON.stringify(preservedAfter)) throw new Error(width + ': calendar changed other search parameters ' + JSON.stringify({ before: preservedBefore, after: preservedAfter }));
      const resultCountBeforeTour = await page.locator('#results .hotel-card').count();
      const searchValuesBeforeTour = await page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()]);
      // Let the canonical selected-tour observer derive the shell state. A body
      // class alone races its next sync because an empty/hidden tour is closed.
      await page.evaluate(() => {
        const selected = document.getElementById('selectedTour');
        selected.innerHTML = '<div class="selected-head"><h2>Проверочный выбранный тур</h2></div>';
        selected.hidden = false;
        window.dispatchEvent(new CustomEvent('v2:selected-tour-opened'));
      });
      await page.waitForSelector('#selectedTour');
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
      const searchValuesAfterTour = await page.evaluate(() => [...new FormData(document.getElementById('tourSearch')).entries()]);
      if (JSON.stringify(unchangedFields(searchValuesBeforeTour)) !== JSON.stringify(unchangedFields(searchValuesAfterTour))) throw new Error(width + ': Back changed search parameters');
      await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset')));
      if (await calendar.isVisible()) throw new Error(width + ': stale calendar remains after reset');
      if (!await calendar.evaluate(node => node.hidden && node.children.length === 0)) throw new Error(width + ': reset did not clear calendar data');
      console.log('SEARCH3_CALENDAR_OK ' + width + ' daily minima, date handoff, responsive display and reset');
      await page.close();
    }
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exit(1);
});
