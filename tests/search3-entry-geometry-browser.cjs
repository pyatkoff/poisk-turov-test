/* Real served Search3 form: reuse the whole-site runner, never fabricate its HTML. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, webkit } = require('playwright');
const nativeDateWebkit = process.env.SEARCH3_NATIVE_DATE_WEBKIT === '1';
const base = process.env.SEARCH3_VISUAL_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'requires the isolated local artifact server');
assert.ok(process.env.SEARCH3_RESULTS_OUTPUT, 'requires retained evidence');
const sourceSha = process.env.SEARCH3_SOURCE_SHA;
assert.match(sourceSha || '', /^[0-9a-f]{40}$/, 'requires the exact checked source SHA');
const output = path.join(process.env.SEARCH3_RESULTS_OUTPUT, nativeDateWebkit ? 'native-date-webkit' : 'native-form');
fs.mkdirSync(output, { recursive: true });
const widths = nativeDateWebkit ? [320, 350, 375, 390, 430, 760] : [320, 350, 375, 430, 760, 761, 1024, 1025, 1099, 1100, 1101, 1199, 1200, 1366, 1440, 1600];
(async () => {
  const browser = await (nativeDateWebkit ? webkit : chromium).launch({ headless: true });
  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, ...(nativeDateWebkit ? { locale:'ru-RU', isMobile:true, hasTouch:true } : {}) });
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
        if (nativeDateWebkit) {
          const dates = page.locator('#tourSearch .search-group--dates input');
          await dates.nth(0).fill('2026-09-16');
          await dates.nth(1).fill('2026-09-29');
          await dates.nth(0).focus();
          await page.keyboard.press('Tab');
          assert.equal(await dates.nth(1).evaluate(node => node === document.activeElement), true, 'native date fields remain keyboard reachable');
          const native = await dates.evaluateAll(nodes => nodes.map(node => {
            const r=node.getBoundingClientRect(),field=node.parentElement.getBoundingClientRect(),s=getComputedStyle(node);
            return {type:node.type,value:node.value,submitted:new FormData(node.form).get(node.name),appearance:s.appearance,fontSize:parseFloat(s.fontSize),height:r.height,left:r.left,right:r.right,fieldLeft:field.left,fieldRight:field.right};
          }));
          for (const [i,item] of native.entries()) {
            assert.equal(item.type,'date','native picker and ISO date semantics remain intact');
            assert.equal(item.value,i===0?'2026-09-16':'2026-09-29');
            assert.equal(item.submitted,item.value,'the form keeps exact ISO dates');
            assert.equal(item.appearance,'none','WebKit uses the controlled date box');
            assert.ok(item.height>=43.5&&item.height<=44.5&&item.fontSize>=16,`${width}: native dates keep the same readable 44px target`);
            assert.ok(item.left>=item.fieldLeft-1&&item.right<=item.fieldRight+1,`${width}: date stays inside its grid field`);
          }
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth>innerWidth+1),false,`${width}: Russian mobile WebKit form has no horizontal overflow`);
          await page.locator('#tourSearch .search-group--dates').screenshot({path:path.join(output,`dates-${width}.png`),animations:'disabled'});
          await page.locator('#tourSearch').screenshot({path:path.join(output,`form-${width}.png`),animations:'disabled'});
          assert.deepEqual(errors,[]);
          fs.writeFileSync(path.join(output,`dates-${width}.json`),JSON.stringify({source_sha:sourceSha,width,browser:'webkit',locale:'ru-RU',native,blocked,errors,supplier_requests_sent:0,lead_sent:0,physical_iphone:'not_measured'},null,2)+'\n');
          continue;
        }
        const state = await page.evaluate(() => {
          const box = node => { const r = node.getBoundingClientRect(), s = getComputedStyle(node); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height, fontSize: parseFloat(s.fontSize), position: s.position }; };
          const form = document.forms.tourSearch, preferences = form.querySelector('.search-preferences');
          const partyGroup = form.querySelector('.search-group--party'), childAges = form.querySelector('#childAges');
          const visible = nodes => [...nodes].filter(node => !node.closest('details:not([open])') && node.checkVisibility({ visibilityProperty: true }) && node.getBoundingClientRect().height > 0);
          const columns = node => {
            const rows = [];
            for (const item of visible([...node.children].filter(child => child.tagName !== 'LEGEND'))) {
              const itemBox = box(item);
              const row = rows.find(entry => {
                const overlap = Math.min(entry.bottom, itemBox.bottom) - Math.max(entry.top, itemBox.top);
                return overlap >= Math.min(entry.height, itemBox.height) * 0.5;
              });
              if (row) {
                row.count += 1;
                row.top = Math.min(row.top, itemBox.top);
                row.bottom = Math.max(row.bottom, itemBox.bottom);
                row.height = row.bottom - row.top;
              } else rows.push({ top: itemBox.top, bottom: itemBox.bottom, height: itemBox.height, count: 1 });
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
            controls: visible(form.querySelectorAll('.field :is(input:not([type=checkbox]),select)')).map(node => ({ ...box(node), name: node.name, appearance: getComputedStyle(node).appearance, tag: node.tagName })),
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
        assert.ok(state.controls.every(item => item.height >= 43.5 && item.height <= 44.5 && item.fontSize >= 16), 'all primary selects, dates, numbers and child ages share the same readable 44px box');
        assert.ok(state.controls.filter(item => item.tag === 'SELECT').every(item => item.appearance === 'none'), 'select rendering uses the canonical box while native selection behavior remains intact');
        assert.ok(state.submit.height >= 43.5 && state.submit.fontSize >= 13, 'primary action remains readable');
        assert.deepEqual(state.groupLegends, ['Направление', 'Даты вылета', 'Продолжительность', 'Туристы']);
        assert.deepEqual(state.preferenceLabels, ['Курорт / регион', 'Конкретный отель', 'Категория отеля', 'Питание', 'Цена от', 'Цена до']);
        assert.equal(state.operatorSecondary, true, 'operator is not a primary search field');
        assert.equal(state.childAgesInsideParty, true, 'child ages stay in the canonical tourist group');
        assert.ok(state.childAgesBox.left >= state.partyBox.left - 1 && state.childAgesBox.right <= state.partyBox.right + 1, `${width}: child ages stay within the tourist group`);
        if (width <= 350) {
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
        if (width > 700 && width < 1100) assert.equal(state.preferenceColumns, 2);
        if (width > 700) {
          assert.ok(state.childAgesBox.width <= state.partyBox.width + 1, `${width}: child ages never stretch beyond tourists`);
          assert.ok(Math.abs(state.submit.top - state.extras.top) <= 1, 'closed extras and CTA share a footer row');
        }
        if (width >= 1100) {
          assert.equal(state.mainColumns, 2); assert.equal(state.preferenceColumns, 6);
          const counts = tops => [...tops.reduce((rows, top) => rows.set(top, (rows.get(top) || 0) + 1), new Map()).values()].sort((a, b) => a - b);
          assert.deepEqual(counts(state.groupTops), [2, 2], 'trip basics retain two balanced rows');
          assert.deepEqual(state.groupColumns, [2, 2, 2, 3], 'one child age shares the compact tourist row on wide desktop');
          const adults = state.controls.find(item => item.name === 'count_people');
          const children = state.controls.find(item => item.name === 'child_count');
          const childAge = state.controls.find(item => item.name === 'child_age[]');
          assert.ok(adults && children && childAge, 'wide desktop exposes adults, children and hydrated child age');
          assert.ok(Math.max(adults.top, children.top, childAge.top) - Math.min(adults.top, children.top, childAge.top) <= 3, 'one child age aligns with adults and children');
          assert.ok(childAge.width <= Math.max(adults.width, children.width) + 1, 'child age remains a compact tourist control');
          assert.ok(Math.max(...state.preferenceTops) - Math.min(...state.preferenceTops) <= 3, 'wide desktop keeps the six primary OTA preferences visually aligned on one row');
          assert.ok(state.preferenceWidths[1] >= state.preferenceWidths[0] + 40, 'exact hotel gets the widest primary track');
          assert.ok(state.preferenceWidths[1] >= state.preferenceWidths[2] + 100, 'hotel track stays materially wider than compact category');
          assert.ok(state.preferenceWidth >= state.form.width - 50);
          assert.ok(state.dateControls.every(item => item.width >= 200));
          assert.ok(state.submit.width <= 281, 'desktop CTA is not oversized');
        }
        const budget = [];
        for (const bounds of [['155500', '200750'], ['0', '1'], ['', '']]) {
          await page.locator('[name=price_from]').fill(bounds[0]);
          await page.locator('[name=price_till]').fill(bounds[1]);
          const observed = await page.evaluate(() => {
            const form = document.forms.tourSearch, data = new FormData(form), params = window.V2SearchLifecycle.params();
            return {
              values: [data.get('price_from'), data.get('price_till')],
              nativeValid: [form.elements.price_from.checkValidity(), form.elements.price_till.checkValidity()],
              searchValues: [params.priceFrom, params.priceTo],
              // Catalog I/O is deliberately blocked here; use only a fixed route prerequisite for the budget validator.
              validation: window.V2SearchLifecycle.validate({ ...params, departureId: '1', countryId: '4' }),
            };
          });
          assert.deepEqual(observed.values, bounds, 'native form keeps the exact RUB budget');
          assert.deepEqual(observed.nativeValid, [true, true], 'whole-ruble budgets need not be multiples of 1000');
          assert.deepEqual(observed.searchValues, bounds, 'existing search parameters receive the typed values without rounding');
          assert.equal(observed.validation, '', 'exact, zero and unset bounds pass the current validator with a fixed route');
          budget.push(observed);
        }
        await page.locator('[name=price_from]').fill('-1');
        assert.equal(await page.locator('[name=price_from]').evaluate(node => node.checkValidity()), false, 'negative minimum remains invalid');
        await page.locator('[name=price_from]').fill('155500');
        await page.locator('[name=price_till]').fill('155499');
        assert.equal(await page.evaluate(() => window.V2SearchLifecycle.validate({ ...window.V2SearchLifecycle.params(), departureId: '1', countryId: '4' })), 'Максимальная цена не может быть меньше минимальной.', 'reversed bounds retain the current validation');
        await page.locator('[name=price_till]').fill('200750');
        if ([375, 1440].includes(width)) {
          await page.locator('#tourSearch').screenshot({ path: path.join(output, `entry-exact-budget-${width}.png`), animations: 'disabled' });
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
          if (count === 0 && width >= 1100) {
            const geometry = await page.evaluate(() => {
              const group = document.querySelector('.search-group--party'), adults = group.querySelector('[name=count_people]'), children = group.querySelector('[name=child_count]');
              const rect = node => { const b = node.getBoundingClientRect(); return { left:b.left, right:b.right, top:b.top, width:b.width, height:b.height }; };
              return { group:rect(group), adults:rect(adults), children:rect(children) };
            });
            assert.ok(Math.abs(geometry.adults.width - geometry.children.width) <= 1, 'no-child tourist controls use balanced columns');
            assert.ok(Math.abs(geometry.adults.top - geometry.children.top) <= 1, 'tourist controls share a row');
            assert.ok(geometry.children.right >= geometry.group.right - 2, 'a hidden child-age group reserves no empty third column');
            await page.locator('#tourSearch').screenshot({ path:path.join(output, `entry-no-children-${width}.png`), animations:'disabled' });
            fs.writeFileSync(path.join(output, `entry-no-children-${width}.json`), JSON.stringify({ width, geometry }, null, 2) + '\n');
          }
          if (count === 2 && ([320, 350, 375].includes(width) || width >= 1099)) {
            const geometry = await page.evaluate(() => {
              const box = node => { const r = node.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height }; };
              const ages = [...document.querySelectorAll('#childAges .child-age')].map(box), childAges = box(document.querySelector('#childAges'));
              const party = box(document.querySelector('.search-group--party')), nights = box(document.querySelector('.search-group--nights'));
              const rows = new Set(ages.map(item => Math.round(item.top)));
              return { ages, childAges, party, nights, rows: rows.size, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
            });
            fs.writeFileSync(path.join(output, `entry-party-2-${width}.json`), JSON.stringify({ width, geometry }, null, 2) + '\n');
            await page.locator('#tourSearch').screenshot({ path: path.join(output, `entry-party-2-${width}.png`), animations: 'disabled' });
            assert.ok(geometry.overflow <= 1, `${width}: two child ages keep document width bounded`);
            if (width <= 350) {
              assert.equal(geometry.rows, 2, `${width}: very narrow phones stack two child ages safely`);
              assert.ok(Math.abs(geometry.ages[0].width - geometry.ages[1].width) <= 1, `${width}: stacked child-age controls keep equal widths`);
            } else {
              assert.equal(geometry.rows, 1, `${width}: two child ages stay on one compact row`);
              assert.ok(Math.abs(geometry.ages[0].top - geometry.ages[1].top) <= 3, `${width}: child-age controls align horizontally`);
              assert.ok(Math.abs(geometry.ages[0].width - geometry.ages[1].width) <= 1, `${width}: mobile child-age columns stay balanced`);
            }
            if (width >= 1100) {
              assert.ok(geometry.ages.every(item => item.width >= 119 && item.width <= 121), `${width}: child-age controls remain compact without wrapping their labels`);
              assert.ok(geometry.party.height <= geometry.nights.height + 12, `${width}: two child ages do not create a blank desktop band beside duration`);
              assert.ok(geometry.childAges.width <= 249, `${width}: child-age group stays bounded`);
            }
          }
          if (count === 3 && ([320, 350, 375].includes(width) || width >= 1100)) {
            const geometry = await page.evaluate(() => {
              const box = node => { const r = node.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height }; };
              const ages = [...document.querySelectorAll('#childAges .child-age')].map(box), childAges = box(document.querySelector('#childAges'));
              const party = box(document.querySelector('.search-group--party')), nights = box(document.querySelector('.search-group--nights'));
              const preferences = box(document.querySelector('.search-section-title--preferences'));
              return { ages, childAges, party, nights, preferences, rows: new Set(ages.map(item => Math.round(item.top))).size, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
            });
            fs.writeFileSync(path.join(output, `entry-party-3-${width}.json`), JSON.stringify({ width, geometry }, null, 2) + '\n');
            await page.locator('#tourSearch').screenshot({ path: path.join(output, `entry-party-3-${width}.png`), animations: 'disabled' });
            if (width <= 350) {
              assert.equal(geometry.rows, 3, `${width}: very narrow phones keep three child ages in safe single-column rows`);
              assert.ok(Math.max(...geometry.ages.map(item => item.width)) - Math.min(...geometry.ages.map(item => item.width)) <= 1, `${width}: stacked child ages keep equal widths`);
            } else if (width === 375) {
              assert.equal(geometry.rows, 2, `${width}: the odd third child age wraps to its own row`);
              assert.ok(Math.abs(geometry.ages[0].top - geometry.ages[1].top) <= 3 && geometry.ages[2].top > geometry.ages[0].top, `${width}: two ages share the first row and the third follows`);
              assert.ok(geometry.ages[2].width >= geometry.childAges.width - 26, `${width}: the odd third child age spans the mobile grid`);
            } else {
              assert.equal(geometry.rows, 1, `${width}: all three child ages share one coherent tourist row`);
              assert.ok(geometry.ages.every(item => item.width >= 119 && item.width <= 121), `${width}: three child ages keep compact control widths`);
              assert.ok(geometry.party.height <= geometry.nights.height + 12, `${width}: family composition does not leave a blank band beside duration`);
              assert.ok(geometry.childAges.bottom <= geometry.party.bottom + 1, `${width}: the tourist group contains every child age`);
              assert.ok(geometry.preferences.top >= geometry.party.bottom + 8, `${width}: hotel preferences begin below the complete tourist group`);
            }
            assert.ok(geometry.overflow <= 1, `${width}: three child ages do not create horizontal overflow`);
          }
          party.push(fields);
        }
        const accessibility = await page.context().newCDPSession(page);
        const advancedNodes = async () => (await accessibility.send('Accessibility.getFullAXTree')).nodes
          .filter(node => !node.ignored && ['combobox', 'option', 'checkbox'].includes(node.role?.value))
          .map(node => ({ role: node.role.value, name: node.name?.value }));
        const closedAx = await advancedNodes();
        assert.equal(closedAx.some(node => ['Туроператор', 'Все операторы', 'Прямой', 'Чартер'].includes(node.name)), false, 'closed advanced filters expose no orphaned options or controls');
        assert.equal(await page.locator('#tourSearch > .extras > .extra-grid').evaluate(node => getComputedStyle(node).display), 'none', 'closed advanced fields have no rendered grid');
        await page.locator('#tourSearch > .extras > summary').focus();
        await page.keyboard.press('Enter');
        const openAx = await advancedNodes();
        for (const name of ['Район / субкурорт', 'Рейтинг отеля', 'Аэропорт прилёта', 'Туроператор', 'Тип отеля']) {
          assert.ok(openAx.some(node => node.role === 'combobox' && node.name === name), 'opening advanced filters restores labelled control: ' + name);
        }
        assert.equal(await page.locator('#tourSearch .service-picker').evaluate(node => node.open), false, 'nested hotel services remain independently closed');
        const advancedGeometry = await page.locator('#tourSearch .extra-grid').evaluate(grid => {
          const rect = node => { const bounds = node.getBoundingClientRect(); return { top: Math.round(bounds.top), bottom: Math.round(bounds.bottom), left: Math.round(bounds.left), right: Math.round(bounds.right), width: Math.round(bounds.width), height: Math.round(bounds.height) }; };
          const fields = [...grid.children].map(node => ({ ...rect(node), label: node.querySelector(':scope>span')?.textContent.trim(), control: rect(node.querySelector('select,.toggle-row')) }));
          const flightChoices = [...grid.querySelectorAll('.toggle-row .toggle')].map(rect);
          return { fields, flightChoices };
        });
        if (width > 700) {
          const rowTops = [...new Set(advancedGeometry.fields.map(field => field.top))];
          const columns = width < 900 ? 2 : 3;
          const expectedRows = Array(6 / columns).fill(columns);
          assert.equal(rowTops.length, expectedRows.length, `${width}: intermediate and wide advanced filters use deliberate balanced rows`);
          assert.deepEqual(rowTops.map(top => advancedGeometry.fields.filter(field => field.top === top).length), expectedRows, `${width}: every advanced row contains the same number of aligned groups`);
          assert.ok(advancedGeometry.fields.every(field => field.width >= 280 && field.height <= 80), `${width}: advanced controls stay readable without a narrow orphan or stretched cell`);
          assert.equal(new Set(advancedGeometry.flightChoices.map(choice => choice.top)).size, 1, `${width}: direct and charter choices share one row`);
          assert.ok(advancedGeometry.flightChoices.every(choice => choice.height >= 44), `${width}: flight choices retain 44px targets`);
        }
        const openedData = await page.locator('#tourSearch').evaluate(form => [...new FormData(form)]);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
        await page.locator('#tourSearch').screenshot({ path: path.join(output, `entry-expanded-${width}.png`), animations: 'disabled' });
        await page.locator('#tourSearch > .extras > summary').focus();
        await page.keyboard.press('Enter');
        assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form)]), openedData, 'closing advanced fields preserves exact FormData');
        assert.equal((await advancedNodes()).some(node => node.name === 'Все операторы'), false, 'closing removes orphaned options again');
        fs.writeFileSync(path.join(output, `entry-accessibility-${width}.json`), JSON.stringify({ width, closedAx, openAx }, null, 2) + '\n');
        await accessibility.detach();
        assert.deepEqual(errors, []);
        fs.writeFileSync(path.join(output, `journey-${width}.json`), JSON.stringify({ source_sha: sourceSha, width, party, budget, advancedGeometry, blocked, errors, supplier_requests_sent: 0, lead_sent: 0, physical_safari: 'deferred' }, null, 2) + '\n');
      } finally { await page.close(); }
    }
  } finally { await browser.close(); }
  console.log(`SEARCH3_SERVED_ENTRY_GEOMETRY_OK source=${sourceSha} browser=${nativeDateWebkit?'webkit':'chromium'} widths=${widths.join(',')} party_states=${nativeDateWebkit?0:widths.length * 4} lead_sent=0`);
})().catch(error => { console.error(error); process.exitCode = 1; });
