/* Real served Search3 form: reuse the whole-site runner, never fabricate its HTML. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const base = process.env.SEARCH3_VISUAL_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'requires the isolated local artifact server');
assert.ok(process.env.SEARCH3_RESULTS_OUTPUT, 'requires retained evidence');
const output = path.join(process.env.SEARCH3_RESULTS_OUTPUT, 'native-form');
fs.mkdirSync(output, { recursive: true });
const widths = [350, 375, 430, 760, 761, 1024, 1025, 1199, 1200, 1366, 1440, 1600];
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      const blocked = [], errors = [];
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
        assert.equal((await page.goto(base + '/poisk-turov/?count_people=3&child_count=1&child_age%5B%5D=8&daysFrom=7&daysTill=10', { waitUntil: 'domcontentloaded' })).status(), 200);
        await page.waitForFunction(() => document.forms.tourSearch?.dataset.search3Ready === '1' && document.forms.tourSearch.dataset.catalogSource && window.V2SearchLifecycle);
        await page.evaluate(() => document.fonts.ready);
        const state = await page.evaluate(() => {
          const box = node => { const r = node.getBoundingClientRect(), s = getComputedStyle(node); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height, fontSize: parseFloat(s.fontSize), position: s.position }; };
          const form = document.forms.tourSearch, preferences = form.querySelector('.search-preferences');
          const partyGroup = form.querySelector('.search-group--party'), childAges = form.querySelector('#childAges');
          // Closed details descendants may retain geometry without being painted.
          const visible = nodes => [...nodes].filter(node => !node.closest('details:not([open])') && node.checkVisibility({ visibilityProperty: true }) && node.getBoundingClientRect().height > 0);
          // Fieldset may serialize repeat()/minmax() rather than resolved tracks.
          // Count the actual visible cells in each row, never spaces in CSS text.
          const columns = node => {
            const rows = [];
            for (const item of visible([...node.children].filter(child => child.tagName !== 'LEGEND'))) {
              const top = box(item).top;
              const row = rows.find(entry => Math.abs(entry.top - top) < 1);
              if (row) row.count += 1; else rows.push({ top, count: 1 });
            }
            return Math.max(0, ...rows.map(row => row.count));
          };
          return {
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            hero: box(document.querySelector('.v2-product-hero')), form: box(form),
            mainColumns: columns(form.querySelector('.main-fields')), preferenceColumns: columns(preferences), preferenceWidth: box(preferences).width,
            groupColumns: [...form.querySelectorAll('.search-group')].map(columns),
            groupTops: [...form.querySelectorAll('.search-group')].map(node => Math.round(box(node).top)),
            groupLegends: [...form.querySelectorAll('.search-group legend')].map(node => node.textContent.trim()),
            preferenceTops: [...preferences.children].map(node => Math.round(box(node).top)),
            preferenceLabels: [...preferences.querySelectorAll('.search-preference>span')].map(node => node.textContent.trim()),
            preferenceWidths: [...preferences.children].map(node => box(node).width),
            labels: visible(form.querySelectorAll('.field>span')).map(box),
            controls: visible(form.querySelectorAll('.field :is(input:not([type=checkbox]),select)')).map(node => ({ ...box(node), name: node.name })),
            dateControls: [...form.querySelectorAll('.search-group--dates input')].map(box),
            partyBox: box(partyGroup), childAgesBox: box(childAges), childAgesInsideParty: childAges.parentElement === partyGroup,
            submit: box(form.querySelector('.search-submit')), extras: box(form.querySelector('.extras')),
            operatorSecondary: !!form.querySelector('.extras select[name=operator]'),
          };
        });
        fs.writeFileSync(path.join(output, `entry-${width}.json`), JSON.stringify({ width, state }, null, 2) + '\n');
        await page.locator('#tourSearch').screenshot({ path: path.join(output, `entry-${width}.png`), animations: 'disabled' });
        assert.ok(state.overflow <= 1, `${width}: form does not widen the document`);
        assert.equal(state.hero.position, 'absolute', 'semantic hero stays outside visual flow');
        assert.ok(state.hero.width <= 1.1 && state.hero.height <= 1.1, 'hero adds no blank form header');
        assert.ok(state.labels.every(item => item.fontSize >= 12), 'visible labels stay readable');
        assert.equal(state.controls.length, 15, 'all fourteen primary native controls plus the hydrated child age are visible');
        assert.ok(state.controls.every(item => item.height >= 43.5 && item.fontSize >= 16), 'native controls retain 44px/16px');
        assert.ok(state.submit.height >= 43.5 && state.submit.fontSize >= 13, 'primary action remains readable');
        assert.deepEqual(state.groupLegends, ['Направление', 'Даты вылета', 'Продолжительность', 'Туристы']);
        assert.deepEqual(state.preferenceLabels, ['Курорт / регион', 'Конкретный отель', 'Категория отеля', 'Питание', 'Цена от', 'Цена до']);
        assert.equal(state.operatorSecondary, true, 'operator is not a primary search field');
        assert.equal(state.childAgesInsideParty, true, 'child ages stay in the canonical tourist group');
        assert.ok(state.childAgesBox.left >= state.partyBox.left - 1 && state.childAgesBox.right <= state.partyBox.right + 1, `${width}: child ages stay within the tourist group`);
        if (width === 350) {
          assert.equal(state.mainColumns, 1); assert.equal(state.preferenceColumns, 1);
          assert.deepEqual(state.groupColumns, [1, 1, 1, 1]);
          assert.equal(new Set(state.preferenceTops).size, 6, 'narrow phone has six safe preference rows');
        }
        if (width === 375 || width === 430) {
          assert.equal(state.mainColumns, 1); assert.equal(state.preferenceColumns, 2);
          assert.deepEqual(state.groupColumns, [1, 2, 2, 2]);
          assert.equal(new Set(state.preferenceTops).size, 4);
          assert.notEqual(state.preferenceTops[0], state.preferenceTops[1]);
          assert.equal(state.preferenceTops[2], state.preferenceTops[3]);
          assert.equal(state.preferenceTops[4], state.preferenceTops[5]);
          assert.ok(state.preferenceWidths.slice(0, 2).every(value => Math.abs(value - state.preferenceWidth) < 2), 'region/hotel stay full-width');
        }
        if (width <= 430) assert.ok(state.submit.width >= state.form.width - 45, 'mobile CTA spans the form');
        if (width > 700 && width < 1200) assert.equal(state.preferenceColumns, 2);
        if (width > 700) {
          assert.ok(state.childAgesBox.width <= state.partyBox.width + 1, `${width}: child ages never stretch beyond tourists`);
          assert.ok(Math.abs(state.submit.top - state.extras.top) <= 1, 'closed extras and CTA share a footer row');
        }
        if (width >= 1200) {
          assert.equal(state.mainColumns, 2); assert.equal(state.preferenceColumns, 6);
          const counts = tops => [...tops.reduce((rows, top) => rows.set(top, (rows.get(top) || 0) + 1), new Map()).values()].sort((a, b) => a - b);
          assert.deepEqual(counts(state.groupTops), [2, 2], 'trip basics retain two balanced rows');
          assert.ok(state.groupColumns.every(value => value === 2));
          assert.deepEqual(counts(state.preferenceTops), [6], 'wide desktop keeps the six primary OTA preferences on one row');
          assert.ok(state.preferenceWidths[1] >= state.preferenceWidths[0] + 40, 'exact hotel gets the widest primary track');
          assert.ok(state.preferenceWidths[1] >= state.preferenceWidths[2] + 100, 'hotel track stays materially wider than compact category');
          assert.ok(state.preferenceWidth >= state.form.width - 50);
          assert.ok(state.dateControls.every(item => item.width >= 200));
          assert.ok(state.submit.width <= 281, 'desktop CTA is not oversized');
        }
        const party = [];
        for (const count of [1, 2, 3, 0]) {
          await page.locator('[name=child_count]').selectOption(String(count));
          await page.waitForFunction(n => document.querySelectorAll('#childAges select').length === n, count);
          const ages = ['0', '17', '6'].slice(0, count);
          for (let i = 0; i < count; i++) await page.locator('#childAges select').nth(i).selectOption(ages[i]);
          const fields = await page.evaluate(() => { const form = document.forms.tourSearch, data = new FormData(form); return { adults: data.get('count_people'), count: data.get('child_count'), ages: data.getAll('child_age[]'), nights: [data.get('daysFrom'), data.get('daysTill')], visible: !form.querySelector('#childAges').hidden, insideParty: !!form.querySelector('.search-group--party > #childAges') }; });
          assert.deepEqual(fields, { adults: '3', count: String(count), ages, nights: ['7', '10'], visible: count > 0, insideParty: true });
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
          party.push(fields);
        }
        await page.locator('#tourSearch > .extras > summary').click();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
        await page.locator('#tourSearch').screenshot({ path: path.join(output, `entry-expanded-${width}.png`), animations: 'disabled' });
        assert.deepEqual(errors, []);
        fs.writeFileSync(path.join(output, `journey-${width}.json`), JSON.stringify({ width, party, blocked, errors, supplier_requests_sent: 0, lead_sent: 0, physical_safari: 'deferred' }, null, 2) + '\n');
      } finally { await page.close(); }
    }
  } finally { await browser.close(); }
  console.log(`SEARCH3_SERVED_ENTRY_GEOMETRY_OK widths=${widths.join(',')} party_states=${widths.length * 4} lead_sent=0`);
})().catch(error => { console.error(error); process.exitCode = 1; });
