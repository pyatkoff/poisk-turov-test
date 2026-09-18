/* Current reset UI: actual renderer, native header, and raw/served JS parity.
 * Retired custom disclosures, drawers and pixel dimensions are not fabricated. */
const assert = require('node:assert/strict');
const withFuel = require('./fixtures/search3-andromeda-fuel.cjs');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.SEARCH3_VISUAL_BASE, output = process.env.SEARCH3_RESULTS_OUTPUT;
const sourceSha = process.env.SEARCH3_SOURCE_SHA;
assert.ok(base && new URL(base).hostname === '127.0.0.1' && output);
assert.match(sourceSha || '', /^[a-f0-9]{40}$/, 'current results evidence requires the exact source SHA');
fs.mkdirSync(output, { recursive: true });
const names = JSON.parse(execFileSync('php', ['-r', 'require "v2/bundle-manifest-v1.php"; echo json_encode(v2_bundle_files("js", "search3"));'], { cwd: root, encoding: 'utf8' }));
const raw = names.map(name => fs.readFileSync(path.join(root, 'v2', name), 'utf8')).join('\n;\n');
const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="300"><path fill="#9ac7df" d="M0 0h600v300H0z"/></svg>');
const tour = { id: 'current-tour', price: 148500.6, date: '2026-09-12', nights: 9, meal: { name: 'AI', fullName: 'Всё включено' }, roomType: 'STANDARD LAND VIEW', placement: 'DBL', operator: { name: 'TEST OPERATOR' } };
const hotels = [
  { id: 'expensive', name: 'Проверочный отель с длинным названием', country: { name: 'Турция' }, region: { name: 'Анталья' }, price: tour.price, rating: 5, category: 5, seaDistance: 100, picturelink: picture, tours: [{ ...tour, id: 'other-tour', price: 159000, operator: { name: 'OTHER OPERATOR' } }, tour, { ...tour, id: 'third-tour', price: 155000, operator: { name: 'OTHER OPERATOR' } }] },
  { id: 'cheap', name: 'Второй отель', price: 90000, rating: 4, category: 4, seaDistance: 800, picturelink: picture, tours: [{ ...tour, id: 'cheap-tour', price: 90000, operator: { name: 'OTHER OPERATOR' } }] }
];
const calendarHotels = [
  { id: 'calendar-a', tours: [
    { ...tour, id: 'calendar-a1', date: '2026-09-10', price: 105000 },
    { ...tour, id: 'calendar-a2', date: '2026-09-10', price: 99000 },
    { ...tour, id: 'calendar-zero', date: '2026-09-13', price: 0 }
  ] },
  { id: 'calendar-b', tours: [{ ...tour, id: 'calendar-b1', date: '2026-09-12', price: 148500 }] }
];
async function checkPrimaryForm(page, state, visible = false) {
  const form = page.locator('#tourSearch');
  assert.equal(await form.count(), 1, state + ': one canonical form owner');
  assert.equal(await form.isVisible(), visible, state + ': canonical editor visibility follows the results state');
  for (const name of ['from', 'country', 'dateFrom', 'dateTo', 'daysFrom', 'daysTill', 'count_people', 'child_count', 'region', 'hotel', 'stars', 'food', 'price_from', 'price_till']) {
    assert.equal(await form.locator(`[name="${name}"]`).count(), 1, state + ': primary control ' + name + ' remains owned by the canonical form');
    if (visible) assert.equal(await form.locator(`[name="${name}"]`).isVisible(), true, state + ': primary control ' + name + ' is editable');
  }
  if (visible) assert.equal(await form.locator('[name=operator]').isVisible(), false, state + ': supplier operator remains secondary');
}
async function snapshot(page) {
  await page.evaluate(async () => {
    await document.fonts.ready;
    for (let i = 0; i < 3; i++) await new Promise(r => requestAnimationFrame(() => setTimeout(r, 0)));
    scrollTo({ top: 0, left: 0, behavior: 'instant' });
    // Focus/filter actions can leave a pending scroll-anchor adjustment. Measure
    // both representations at a settled document origin, not during that scroll.
    for (let i = 0; i < 2; i++) await new Promise(r => requestAnimationFrame(r));
    scrollTo({ top: 0, left: 0, behavior: 'instant' });
  });
  await page.waitForFunction(() => scrollX === 0 && scrollY === 0);
  return page.evaluate(() => {
    const result = document.getElementById('results');
    const nodes = [...result.querySelectorAll('*')].map(node => {
      const r = node.getBoundingClientRect(), s = getComputedStyle(node);
      return { tag: node.tagName, classes: node.className, text: node.children.length ? '' : node.textContent,
        rect: [r.x, r.y, r.width, r.height].map(n => Math.round(n * 100) / 100), display: s.display, visibility: s.visibility };
    });
    const overflow = document.documentElement.scrollWidth > innerWidth + 2;
    const offenders = overflow ? [...document.querySelectorAll('body *')].filter(node => {
      const r = node.getBoundingClientRect(); return r.width > 0 && r.right > innerWidth + 2;
    }).slice(0,12).map(node => ({tag:node.tagName,id:node.id,classes:node.className,rect:node.getBoundingClientRect().toJSON()})) : [];
    return { html: result.innerHTML, nodes, overflow, offenders };
  });
}
async function checkToolbarLayout(page, width, previous) {
  const tools = page.locator('#resultsTools'), edit = tools.locator('#resultsSearchEdit');
  const sort = tools.locator('#sortResults'), panel = tools.locator('.search3-mobile-filter-panel');
  const measure = () => tools.evaluate(node => {
    const origin = node.getBoundingClientRect();
    const box = element => { const r = element.getBoundingClientRect(); return { x: Math.round((r.x-origin.x)*100)/100, y: Math.round((r.y-origin.y)*100)/100, width: Math.round(r.width*100)/100, height: Math.round(r.height*100)/100 }; };
    const disclosure = node.querySelector('.search3-mobile-filter-panel:not([hidden])');
    return { width: origin.width, height: origin.height, actions: box(node.querySelector('.results-tools__actions')), edit: box(node.querySelector('#resultsSearchEdit')), sort: box(node.querySelector('#sortResults')), panel: disclosure && box(disclosure), summary: disclosure && box(disclosure.querySelector('summary')) };
  });
  await page.evaluate(() => document.fonts.ready);
  const closed = await measure();
  assert.equal(await sort.evaluate(node => getComputedStyle(node).appearance), 'none', 'native result selector uses the same controllable geometry as the search form');
  assert.ok(Math.abs(closed.edit.height - closed.sort.height) <= 2, 'sorting and edit actions have consistent heights');
  for (const control of [closed.edit, closed.sort, closed.summary].filter(Boolean)) {
    assert.ok(control.height >= 44 && control.width > 0, 'toolbar retains visible 44px controls');
    assert.ok(control.x >= 0 && control.x + control.width <= closed.width + 1, 'toolbar controls stay inside their container');
  }
  if (width > 600) {
    const controls = width >= 760 && width <= 1024 ? [closed.edit, closed.sort, closed.summary] : [closed.edit, closed.sort];
    const bottom = controls[0].y + controls[0].height;
    assert.ok(controls.every(control => Math.abs(control.y + control.height - bottom) < 3), 'toolbar actions align in one usable row');
    if (width >= 760 && width <= 1024) assert.ok(closed.actions.height <= 76, 'tablet toolbar has no empty edit row or separate filter/sort rows');
  } else {
    const positioning = await tools.evaluate(node => ({ position: getComputedStyle(node).position, top: getComputedStyle(node).top }));
    assert.deepEqual(positioning, { position: 'static', top: 'auto' }, 'mobile results toolbar stays in document flow instead of entering the physical safe area');
    assert.ok(closed.edit.width < closed.actions.width - 24, 'mobile edit remains a compact secondary action');
    assert.ok(closed.sort.y >= closed.edit.y + closed.edit.height && closed.summary.y >= closed.sort.y + closed.sort.height, 'mobile controls follow their readable visual order');
  }
  if (!previous && [375, 720, 1024, 1025, 1440].includes(width)) await tools.screenshot({ path: path.join(output, `toolbar-${width}-closed.png`), animations: 'disabled' });
  await edit.focus(); await edit.press('Tab');
  assert.equal(await sort.evaluate(node => node === document.activeElement), true, 'keyboard moves from edit directly to the adjacent sort control');
  let opened = null;
  if (width <= 1024) {
    const summary = panel.locator('summary');
    await sort.press('Tab');
    assert.equal(await summary.evaluate(node => node === document.activeElement), true, 'filter disclosure follows sort in both DOM and visual order');
    await summary.press('Enter');
    assert.equal(await panel.evaluate(node => node.open), true);
    opened = await measure();
    assert.ok(Math.abs(opened.panel.width-opened.actions.width) < 2 && Math.abs(opened.panel.x-opened.actions.x) < 2, 'opened filters use the full toolbar width');
    assert.ok(opened.panel.y >= Math.max(opened.edit.y+opened.edit.height, opened.sort.y+opened.sort.height), 'opened filter body follows the toolbar controls');
    if (!previous && width === 1024) await tools.screenshot({ path: path.join(output, 'toolbar-1024-open.png'), animations: 'disabled' });
    await summary.press('Enter');
    assert.equal(await panel.evaluate(node => node.open), false, 'inspection restores the closed disclosure');
  }
  if (!previous && width === 375) {
    const toolbarDocumentBottom = await tools.evaluate(node => node.getBoundingClientRect().bottom + scrollY);
    await page.evaluate(y => scrollTo({ top: y, left: 0, behavior: 'instant' }), toolbarDocumentBottom + 20);
    await page.waitForFunction(() => scrollY > 0);
    assert.ok((await tools.boundingBox()).y < 0, 'mobile results toolbar scrolls away instead of sticking below the browser chrome');
    await page.screenshot({ path: path.join(output, 'toolbar-375-scrolled.png'), animations: 'disabled' });
    await page.evaluate(() => scrollTo({ top: 0, left: 0, behavior: 'instant' }));
    await page.waitForFunction(() => scrollY === 0);
  }
  return { closed, opened };
}
async function checkOfferFacets(page, width, previous) {
  const items = [
    { ...hotels[0], id:'facet-a', name:'Первый отель', tours:[{...tour,id:'a7',nights:7,isCharter:true,meal:'BB',price:90000},{...tour,id:'a9',nights:9,isCharter:false,meal:'AI',price:120000}] },
    { ...hotels[1], id:'facet-b', name:'Второй отель', tours:[{...tour,id:'b7',nights:7,isCharter:false,meal:'AI',price:130000},{...tour,id:'b9',nights:9,isCharter:true,meal:'BB',price:95000}] },
    { ...hotels[1], id:'facet-c', name:'Третий отель', tours:[{...tour,id:'c7',nights:7,isCharter:false,meal:'BB',price:125000},{...tour,id:'c9',nights:9,isCharter:true,meal:'AI',price:150000}] }
  ];
  const requests=[],record=request=>{if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname))requests.push(request.url());};
  page.on('request',record);
  const render=async list=>page.evaluate(items=>{window.V2Results.render(items);window.dispatchEvent(new CustomEvent('v2:search-complete',{detail:{items}}));},list);
  const reset=async()=>page.evaluate(()=>window.Search3LocalHotelFilter.reset());
  const visible=async()=>page.locator('#results .hotel-card:visible').evaluateAll(nodes=>nodes.map(node=>node.dataset.hotelId).sort());
  const panel=page.locator('.search3-mobile-filter-panel'),nights=page.locator('.search3-nights-filter select'),flight=page.locator('.search3-flight-filter select'),meal=page.locator('.search3-meal-filter select'),upper=page.locator('.search3-budget-max');
  try {
    await reset();await render(items);
    if(width<1025&&!await panel.evaluate(node=>node.open))await panel.locator('summary').click();
    assert.equal(await nights.isVisible(),true);assert.equal(await flight.isVisible(),true);
    for(const select of [nights,flight])assert.ok((await select.boundingBox()).height>=44,'new filters retain a native touch target');
    await nights.selectOption('7');await flight.selectOption('regular');
    assert.deepEqual(await visible(),['facet-b','facet-c'],'nights and flight must match the same tour, not different offers at the same hotel');
    await meal.selectOption('meal:label:ai');
    assert.deepEqual(await visible(),['facet-b'],'meal joins the same exact seven-night regular-flight tour');
    assert.equal((await page.locator('#results [data-hotel-id=facet-b] .hotel-price').innerText()).replace(/\s/g,''),'130000₽');
    assert.equal(await page.locator('#resultSummary').innerText(),'Показано отелей: 1 из 3 · цены из текущего поиска');
    await upper.fill('125000');await upper.press('Tab');
    assert.deepEqual(await visible(),[],'a cheaper different offer cannot satisfy the budget');
    await page.locator('.search3-active-filters [data-filter-key=budget]').click();
    assert.deepEqual(await visible(),['facet-b']);
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await nights.inputValue(),'7');assert.equal(await flight.inputValue(),'regular');
    assert.deepEqual(await visible(),['facet-b'],'sort preserves both exact-offer facets');
    assert.equal(await page.locator('.search3-active-filters [data-filter-key=nights]').count(),1);
    assert.equal(await page.locator('.search3-active-filters [data-filter-key=flight]').count(),1);
    if(!previous&&[320,375,720,1440].includes(width))await (width<1025?panel:page.locator('.results-filter-rail')).screenshot({path:path.join(output,`offer-filters-${width}.png`),animations:'disabled'});
    await nights.selectOption('9');assert.deepEqual(await visible(),['facet-a']);
    const continued=items.concat({...items[0],id:'facet-d',name:'Новый отель',tours:[{...tour,id:'d9',nights:9,isCharter:false,meal:'AI',price:110000}]});
    await render(continued);assert.deepEqual(await visible(),['facet-a','facet-d'],'progressive results retain the selected conjunction');
    assert.equal(await nights.inputValue(),'9');assert.equal(await flight.inputValue(),'regular');
    await page.locator('.search3-active-filters [data-filter-key=flight]').click();
    assert.deepEqual(await visible(),['facet-a','facet-c','facet-d'],'removing only flight retains nights and meal');
    await page.locator('.search3-active-filters [data-filter-key=nights]').click();
    assert.deepEqual(await visible(),['facet-a','facet-b','facet-c','facet-d']);
    await reset();await render(items);await nights.selectOption('7');await flight.selectOption('regular');
    const incomplete=items.concat({...items[0],id:'unknown-facet',tours:[{...tour,id:'unknown-facet-tour',nights:0,isCharter:'false'}]});
    await render(incomplete);
    assert.equal(await nights.isVisible(),false);assert.equal(await flight.isVisible(),false);
    assert.equal(await nights.inputValue(),'0');assert.equal(await flight.inputValue(),'');
    assert.equal((await visible()).length,4,'unknown facts hide/reset facets and do not silently exclude hotels');
    await render(items);await nights.selectOption('7');await flight.selectOption('regular');
    await page.evaluate(()=>window.dispatchEvent(new CustomEvent('v2:search-started')));
    await render(items);
    assert.equal(await nights.inputValue(),'0');assert.equal(await flight.inputValue(),'');
    assert.equal((await visible()).length,3,'new search clears both facets');
    await render([items[0]]);
    if(width<1025&&!await panel.evaluate(node=>node.open))await panel.locator('summary').click();
    assert.equal(await nights.isVisible(),true);assert.equal(await flight.isVisible(),true,'multiple offers at one hotel still allow useful choices');
    assert.deepEqual(requests,[],'local nights/flight interactions never call supplier or lead endpoints');
  } finally {page.off('request',record);await reset();await render(hotels);if(width<1025&&await panel.evaluate(node=>node.open))await panel.locator('summary').click();}
}
async function checkBudgetRange(page, width, previous) {
  const requests=[];
  const record=request=>{if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)) requests.push(request.url());};
  page.on('request',record);
  try {
    await page.evaluate(items=>{window.Search3LocalHotelFilter.reset();window.V2Results.render(items);window.dispatchEvent(new CustomEvent('v2:search-complete',{detail:{items}}));},hotels);
    const panel=page.locator('.search3-mobile-filter-panel'), lower=page.locator('.search3-budget-min'), upper=page.locator('.search3-budget-max'), summary=page.locator('#resultSummary');
    const expectCount=async (shown,total)=>assert.equal(await summary.innerText(),`Показано отелей: ${shown} из ${total} · цены из текущего поиска`,'header count follows the same visible hotel projection');
    assert.equal(await summary.innerText(),'Найдено отелей: 2 · цены из текущего поиска','unfiltered count retains the current-search scope');
    if(width<1025&&!await panel.evaluate(node=>node.open)) await panel.locator('summary').click();
    const set=async (input,value)=>{await input.fill(value);await input.press('Tab');};
    await set(lower,'148501');
    await set(upper,'155000');
    const card=page.locator('#results .hotel-card:visible');
    assert.equal(await card.count(),1,'range excludes the cheaper hotel');
    await expectCount(1,2);
    assert.equal(await card.getAttribute('data-hotel-id'),'expensive');
    assert.equal(await card.locator('.direct-tour').getAttribute('data-tid'),'third-tour','both inclusive bounds retain the exact 155000 offer, not the cheaper hotel representative');
    assert.match(await card.locator('.hotel-price').innerText(),/155\s*000/,'hotel price is derived from the offer inside the range');
    assert.equal(await page.locator('.search3-active-filters [data-filter-key=budget]').count(),1,'one removable chip represents both bounds');
    await page.locator('.search3-operator-filter select').selectOption('name:test operator');
    assert.equal(await card.count(),0,'operator and both price bounds must match one offer');
    await expectCount(0,2);
    await page.locator('.search3-operator-filter select').selectOption('');
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await lower.inputValue(),'148501','sorting preserves the lower bound');
    assert.equal(await upper.inputValue(),'155000','sorting preserves the upper bound');
    await expectCount(1,2);
    await page.evaluate(items=>window.V2Results.render(items),hotels);
    assert.equal(await lower.inputValue(),'148501','results refresh preserves the lower bound');
    assert.equal(await card.locator('.direct-tour').getAttribute('data-tid'),'third-tour');
    assert.deepEqual(await page.evaluate(()=>window.V2Results.state.items),hotels,'range filtering leaves source tour objects and prices unchanged');
    const continued=hotels.concat({...hotels[0],id:'continued-budget',name:'Дополнительный отель',tours:[{...tour,id:'continued-budget-tour',price:150000}]});
    await page.evaluate(items=>{window.V2Results.render(items);window.dispatchEvent(new CustomEvent('v2:search-continued',{detail:{items}}));},continued);
    await expectCount(2,3);
    assert.equal(await card.count(),2,'progressive results update both the visible and loaded hotel counts');
    await page.evaluate(items=>window.V2Results.render(items),hotels);
    await expectCount(1,2);
    if(!previous&&[320,375,720,1440].includes(width)) await page.locator('#resultsTools').screenshot({path:path.join(output,`filtered-count-${width}.png`),animations:'disabled'});
    if(!previous&&[320,375,390,720,1025,1440].includes(width)) await (width<1025?panel:page.locator('.results-filter-rail')).screenshot({path:path.join(output,`budget-range-${width}.png`),animations:'disabled'});
    if(width<1025){
      const done=panel.locator('.search3-filter-results');
      assert.equal(await done.innerText(),'Показать отели · 1');
      await done.click();
      await page.waitForFunction(()=>document.activeElement?.matches('#results .hotel-title'));
      assert.equal(await panel.getAttribute('open'),null,'bottom action closes the same native filter disclosure');
      const heading=await card.locator('.hotel-title').boundingBox();
      assert.ok(heading.y>=0&&heading.y+heading.height<=await page.evaluate(()=>innerHeight),'return shows the matching hotel in the viewport: '+JSON.stringify({width,previous,heading}));
      if(!previous&&[375,390,720].includes(width)) await page.screenshot({path:path.join(output,`budget-results-${width}.png`),animations:'disabled'});
      await panel.locator('summary').click();
    }
    await set(lower,'155001');
    assert.equal(await card.count(),0,'reversed bounds do not silently swap or show an out-of-range offer');
    await expectCount(0,2);
    if(!previous&&[375,1440].includes(width)) await page.locator('#resultsTools').screenshot({path:path.join(output,`empty-count-${width}.png`),animations:'disabled'});
    assert.match(await page.locator('#search3BudgetHint').innerText(),/Цена «от» больше цены «до»/);
    if(width<1025){
      await panel.locator('.search3-filter-results').click();
      await page.waitForFunction(()=>document.activeElement?.matches('.search3-local-empty'));
      assert.equal(await page.locator('.search3-local-empty-reset').isVisible(),true,'zero-result return exposes the existing recovery action');
      await panel.locator('summary').click();
    }
    await page.locator('.search3-active-filters [data-filter-key=budget]').click();
    assert.equal(await lower.inputValue(),'','removing the budget chip clears both ends');
    assert.equal(await card.count(),2);
    assert.equal(await summary.innerText(),'Найдено отелей: 2 · цены из текущего поиска','clearing the budget restores the unfiltered header count');
    await set(upper,'0');
    assert.equal(await card.count(),0,'an explicit zero upper limit never disables the budget filter');
    await set(upper,'');
    assert.equal(await card.count(),2,'empty upper limit restores the available range');
    await set(lower,'90000');
    await set(upper,'90000');
    assert.equal(await card.getAttribute('data-hotel-id'),'cheap','equal bounds are inclusive');
    await page.evaluate(()=>window.dispatchEvent(new CustomEvent('v2:search-started',{detail:{searchId:711}})));
    assert.equal(await lower.inputValue(),'','new search clears the old budget floor');
    await page.evaluate(items=>window.V2Results.render(items),hotels.slice(0,1));
    assert.equal(await summary.innerText(),'Найдено отелей: 1 · цены из текущего поиска','new search does not inherit the previous denominator or filtered count');
    assert.deepEqual(requests,[],'budget and mobile return never call supplier or lead endpoints');
  } finally {page.off('request',record);await page.evaluate(()=>window.Search3LocalHotelFilter.reset());}
}
async function checkMinimumReadiness(page, width, previous) {
  // Generic selection readiness; real SAMO admission is exercised through its HTTP projection below.
  const makeTour = (id, price, extra = {}) => ({ ...tour, id, price, ...extra });
  const items = [
    { ...hotels[0], id: 'minimum-check', name: 'Минимальная цена с проверкой', price: 80000, tours: [makeTour('minimum-check-offer', 80000, { selectionEnabled: false }), makeTour('minimum-selectable', 95000)] },
    { ...hotels[0], id: 'minimum-mixed', name: 'Одна цена — разные условия выбора', price: 85000, tours: [makeTour('mixed-check-offer', 85000, { selectionEnabled: false }), makeTour('mixed-selectable', 85000)] },
    { ...hotels[0], id: 'minimum-ready', name: 'Вариант с доступным выбором', price: 90000, tours: [makeTour('ready-minimum', 90000), makeTour('expensive-check', 110000, { selectionEnabled: false })] }
  ];
  const requests = [];
  const record = request => { if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)) requests.push(request.url()); };
  page.on('request', record);
  try {
    await page.evaluate(items => {
      window.Search3LocalHotelFilter.reset();
      window.__minimumReadinessSource = items;
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items } }));
    }, items);
    const cards = page.locator('#results .hotel-card');
    const checked = page.locator('.hotel-card[data-hotel-id="minimum-check"]');
    const mixed = page.locator('.hotel-card[data-hotel-id="minimum-mixed"]');
    const ready = page.locator('.hotel-card[data-hotel-id="minimum-ready"]');
    for (const card of [checked, mixed, ready]) {
      assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle,.tour-selection-note').count(), 0, 'collapsed hotel does not project one offer readiness or action');
      assert.equal(await card.locator('.hotel-offers-summary').count(), 1, 'collapsed hotel exposes one neutral minimum summary');
      const geometry = await card.evaluate(node => {
        const r = node.getBoundingClientRect(), summary = node.querySelector('.hotel-offers-summary'), s = summary && summary.getBoundingClientRect();
        return { inside: !s || s.width > 0 && s.left >= r.left && s.right <= r.right + 1, overflow: node.scrollWidth > node.clientWidth + 1 };
      });
      assert.equal(geometry.inside, true, 'hotel-level minimum remains readable within its card');
      assert.equal(geometry.overflow, false);
    }
    const labels = await cards.evaluateAll(nodes => nodes.map(node => ({ id: node.dataset.hotelId, price: node.querySelector('.hotel-offers-summary .hotel-price').textContent, note: node.querySelector('.tour-selection-note')?.textContent || '' })));
    const toggle = checked.locator('.tour-more-toggle');
    await toggle.focus(); await toggle.press('Enter');
    assert.equal(await checked.locator('[data-tid="minimum-check-offer"]').count(), 0, 'unverified minimum still cannot create a select action');
    assert.equal(await checked.locator('[data-tid="minimum-selectable"]').isVisible(), true, 'more expensive selectable offer keeps its existing action');
    assert.match(await checked.locator('.tour-row').first().innerText(), /перед выбором нужна проверка/i);
    if (!previous) await checked.screenshot({ path: path.join(output, `minimum-readiness-${width}.png`), animations: 'disabled' });
    await checked.locator('.tour-more-toggle').press('Enter');
    assert.equal(await checked.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'collapse retains the existing disclosure focus');
    assert.equal(await checked.locator('.tour-selection-note').count(), 0, 'collapse removes concrete readiness copy with the concrete offer');
    assert.deepEqual(await page.evaluate(() => window.__minimumReadinessSource), items, 'presentation does not mutate supplier prices or readiness');
    assert.deepEqual(requests, [], 'local summary and disclosure cause no supplier or lead request');
    return labels;
  } finally { page.off('request', record); }
}

async function checkExactOfferParty(page, width, previous) {
  const parties=[{adults:2,childs:0},{adults:2,childs:1},{adults:1,childs:2},{adults:2,childs:null}];
  const item={...hotels[0],id:'party-hotel',price:71000,tours:parties.map((party,index)=>({...tour,...party,id:'party-'+index,price:71000+index*1000}))};
  await page.evaluate(item=>{
    window.Search3LocalHotelFilter.reset();
    const freeze=value=>{if(value&&typeof value==='object'){Object.values(value).forEach(freeze);Object.freeze(value);}return value;};
    window.__partyOriginal=freeze(item);window.V2Results.render([window.__partyOriginal]);
  },item);
  const card=page.locator('.hotel-card[data-hotel-id="party-hotel"]');
  assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle').count(),0,'collapsed multi-offer hotel does not borrow one party or offer action');
  assert.equal(await card.locator('.hotel-offers-summary').count(),1,'collapsed multi-offer hotel exposes one hotel-level summary');
  assert.equal(await card.locator('.hotel-price').innerText().then(text=>text.replace(/\s/g,'')),'от71000₽','collapsed party sample exposes only the hotel minimum');
  assert.doesNotMatch(await card.locator('.hotel-tours').innerText(),/2 взрослых|1 ребёнок|2 ребёнка/,'collapsed hotel does not borrow an exact offer party');
  await card.locator('.tour-more-toggle').click();
  assert.equal(await card.locator('.tour-row').count(),3,'the first party examples follow the bounded initial disclosure');
  await card.locator('.tour-list-more').click();
  assert.equal(await card.locator('.tour-row').count(),parties.length,'all party examples remain reachable through the next disclosure');
  const rows=card.locator('.tour-row');
  for(let index=0;index<parties.length;index++){
    const row=rows.nth(index);
    assert.equal(await row.locator('.direct-tour').getAttribute('data-tid'),'party-'+index);
    assert.equal(await row.locator('.hotel-price').innerText().then(text=>Number(text.replace(/\D/g,''))),item.tours[index].price);
    assert.equal(await row.getByText('Туристы',{exact:true}).count(),0,'exact offer does not repeat the search-level party');
    assert.doesNotMatch(await row.innerText(),/взросл|ребён/,'search party stays outside offer comparison rows');
    assert.equal(await row.evaluate(node=>node.scrollWidth>node.clientWidth+1),false);
  }
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
  assert.equal(await page.evaluate(()=>JSON.stringify(window.__partyOriginal)),JSON.stringify(item),'party display does not mutate the supplier offer');
  if(!previous)await card.screenshot({path:path.join(output,`exact-offer-party-${width}.png`),animations:'disabled'});
}

async function checkExpandedDensity(page, width, previous) {
  // The owner supplied a physical-iPhone capture with 260 offers. Ten cover
  // initial, intermediate and final disclosure steps without supplier calls.
  const item = { ...hotels[0], id: 'density-ten', price: 61372, tours: Array.from({ length: 10 }, (_, index) => ({ ...tour, id: 'density-' + index, price: 61372 + index * 1000, roomType: index === 1 ? 'promo room' : tour.roomType, adults: 2, childs: 0, isCharter: true })) };
  const requests = [], record = request => { if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)) requests.push(request.url()); };
  page.on('request', record);
  const measurements = [];
  try {
    await page.evaluate(item => {
      window.Search3LocalHotelFilter.reset();
      window.__densityOriginal = item;
      window.__densitySearchId = window.V2Runtime.state.searchId;
      window.V2Runtime.setSearchId(707);
      window.V2Results.render([item]);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items: [item] } }));
    }, item);
    const card = page.locator('.hotel-card[data-hotel-id="density-ten"]');
    assert.equal(await card.locator('.hotel-choice-hint').count(), 0, 'hotel identity does not repeat the loaded count');
    assert.equal(await card.locator('.hotel-price').count(), 1, 'collapsed state contains one hotel-level minimum');
    assert.equal(await card.locator('.tour-more-toggle').innerText(), 'Показать варианты · 10');
    await card.locator('.tour-more-toggle').focus();
    await card.locator('.tour-more-toggle').press('Enter');
    assert.equal(await card.locator('.hotel-trip-summary,.hotel-summary-total').count(), 0, 'expanded comparison has no aggregate facts or total');
    assert.equal(await card.locator('.hotel-offers-heading>strong').innerText(), '10 вариантов', 'one grammatically correct count belongs to the comparison header');
    assert.equal(await card.locator('.hotel-price').count(), 3, 'the first decision view shows three exact offers instead of the whole long list');
    assert.equal(await card.locator('.tour-row').nth(1).locator('.tour-fact').filter({ hasText: 'Номер' }).locator('b').innerText(), 'promo room · Двухместное', 'the visible promotional room preserves the exact supplier fact beside the reviewed placement label');
    assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), item.tours.slice(0, 3).map(value => value.id), 'the representative offer and first alternatives retain their identity and order');
    assert.deepEqual(await card.locator('.tour-action>.hotel-price').allTextContents().then(values => values.map(value => Number(value.replace(/\D/g, '')))), item.tours.slice(0, 3).map(value => value.price), 'the first displayed prices remain the original supplier amounts');
    assert.equal(await card.locator('.tour-list-more').innerText(), 'Показать ещё 3');
    assert.equal(await card.locator('.hotel-offers-more small').innerText(), 'Показано 3 из 10');
    await card.locator('.search3-shortlist-toggle').nth(2).waitFor();
    assert.equal(await card.locator('.search3-shortlist-toggle').count(), 3, 'the initial alternatives remain available for comparison');
    assert.ok((await card.locator('.tour-list-more').boundingBox()).height >= 44, 'progressive disclosure keeps a full touch target');
    await page.mouse.move(0, 0);
    for (const inspectedWidth of width === 375 ? [375, 390] : [width]) {
      if (inspectedWidth !== width) await page.setViewportSize({ width: inspectedWidth, height: page.viewportSize().height });
      await page.evaluate(() => document.fonts.ready);
      const geometry = await card.evaluate(node => {
        const origin = node.getBoundingClientRect(), rect = element => { const r = element.getBoundingClientRect(); return { x: Math.round(r.x-origin.x), y: Math.round(r.y-origin.y), width: Math.round(r.width), height: Math.round(r.height) }; };
        const heading = node.querySelector('.hotel-offers-heading'), collapse = heading.querySelector('button'), first = node.querySelector('.tour-row'), action = first.querySelector('.direct-tour');
        return { width: Math.round(origin.width), hotelHeader: rect(node.querySelector('.hotel-main')), heading: rect(heading), collapse: rect(collapse), first: rect(first), action: rect(action), collapseColor: getComputedStyle(collapse).backgroundColor, actionColor: getComputedStyle(action).backgroundColor, pageOverflow: document.documentElement.scrollWidth > innerWidth + 1 };
      });
      assert.ok(geometry.heading.height <= 80, 'count and collapse fit one compact header instead of two stacked rows');
      assert.ok(geometry.collapse.height >= 44 && geometry.collapse.width < geometry.heading.width * 0.6, 'secondary collapse stays touch-sized without becoming a full-width CTA');
      assert.ok(geometry.collapse.x >= 0 && geometry.collapse.x + geometry.collapse.width <= geometry.width, 'collapse stays inside the card');
      assert.ok(Math.abs(geometry.first.y - geometry.heading.y - geometry.heading.height) <= 1, 'the first actual offer immediately follows its header');
      assert.notEqual(geometry.collapseColor, geometry.actionColor, 'collapse is visually secondary to choosing a tour');
      assert.equal(geometry.pageOverflow, false);
      if (inspectedWidth >= 1200) assert.ok(geometry.hotelHeader.height >= 140 && geometry.hotelHeader.height <= 160, 'expanded hotel keeps a readable compact identity header above the offers');
      if (inspectedWidth <= 390) assert.ok(geometry.action.y + geometry.action.height <= 760, 'first exact selection is reachable within the initial expanded card viewport');
      measurements.push({ viewportWidth: inspectedWidth, ...geometry });
      if (!previous) {
        await card.evaluate(node => scrollTo({ top: node.getBoundingClientRect().top + scrollY, behavior: 'instant' }));
        await page.screenshot({ path: path.join(output, `card-density-${inspectedWidth}.png`), animations: 'disabled' });
      }
    }
    for (const expected of [6, 9, 10]) {
      const more = card.locator('.tour-list-more');
      await more.focus();
      await more.press('Enter');
      assert.equal(await card.locator('.tour-row').count(), expected, 'each local disclosure adds one bounded offer step');
      await card.locator('.search3-shortlist-toggle').nth(expected - 1).waitFor();
      assert.deepEqual(await card.locator('.search3-shortlist-toggle').evaluateAll(nodes => nodes.map(node => node.dataset.offerId)), item.tours.slice(0, expected).map(value => value.id), 'newly revealed offers retain their exact Compare actions');
      assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), item.tours.slice(0, expected).map(value => value.id), 'progressive disclosure retains exact offer identity and order');
      assert.deepEqual(await card.locator('.tour-action>.hotel-price').allTextContents().then(values => values.map(value => Number(value.replace(/\D/g, '')))), item.tours.slice(0, expected).map(value => value.price), 'progressive disclosure retains exact supplier prices');
      if (expected < 10) {
        assert.equal(await card.locator('.tour-list-more').evaluate(node => node === document.activeElement), true, `disclosure at ${expected} offers retains keyboard focus; active=${await page.evaluate(() => document.activeElement?.outerHTML.slice(0,250))}`);
        assert.equal(await card.locator('.hotel-offers-more small').innerText(), `Показано ${expected} из 10`);
      } else {
        assert.equal(await card.locator('.tour-list-more').count(), 0, 'the disclosure is removed when every offer is visible');
        assert.equal(await card.locator('.tour-row').nth(9).locator('.direct-tour').evaluate(node => node === document.activeElement), true, 'the final disclosure moves focus to the first newly revealed offer');
      }
      if (expected === 6) {
        const compare = card.locator('.search3-shortlist-toggle').nth(5);
        await compare.click();
        const saved = await page.evaluate(() => window.Search3Shortlist.items().find(item => item.offerId === 'density-5'));
        assert.ok(saved, 'a newly revealed offer can be added to comparison');
        assert.equal(saved.observedPrice, item.tours[5].price, 'comparison retains the exact newly revealed offer price');
        await card.locator('.search3-shortlist-toggle[data-offer-id="density-5"]').click();
        assert.equal(await page.evaluate(() => window.Search3Shortlist.items().some(item => item.offerId === 'density-5')), false, 'comparison can remove the newly revealed offer');
        await page.waitForFunction(() => document.activeElement?.matches('.search3-shortlist-toggle[data-offer-id="density-5"]'));
      }
    }
    await card.locator('.tour-more-toggle').focus();
    await card.locator('.tour-more-toggle').press('Space');
    assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle').count(), 0, 'collapse returns to hotel-level choice without a borrowed concrete offer');
    assert.equal(await card.locator('.hotel-offers-summary .hotel-price').count(), 1);
    assert.match(await card.locator('.hotel-offers-summary .hotel-price').innerText(), /^от\s/);
    assert.equal(await card.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'replacement disclosure keeps keyboard focus');
    assert.equal(await page.evaluate(() => window.V2Results.revealOfferAlternatives('density-8')?.dataset.tid), 'density-8', 'saved exact offer reveal opens far enough to expose its original action');
    assert.equal(await card.locator('.tour-row').count(), 9, 'saved offer reveal does not expose unrelated trailing offers');
    assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), item.tours.slice(0, 9).map(value => value.id));
    await card.locator('.tour-more-toggle').click();
    assert.deepEqual(await page.evaluate(() => window.__densityOriginal), item, 'density changes never mutate the input offers');
    assert.deepEqual(requests, [], 'local expansion and collapse make no supplier or lead request');
    return measurements;
  } finally {
    if (page.viewportSize().width !== width) await page.setViewportSize({ width, height: page.viewportSize().height });
    await page.evaluate(() => window.V2Runtime.setSearchId(window.__densitySearchId));
    page.off('request', record);
  }
}

async function checkMealFacet(page, width, previous) {
  const sample = (id, price, meal, date) => ({ ...tour, id, price, meal, date });
  const items = [
    { id: 'meal-a', name: 'Отель А', price: 90000, rating: 5, category: 5, tours: [
      sample('a-ai', 120000, { name: 'AI', fullName: 'Всё включено' }, '2026-09-12'),
      sample('a-ai-extra', 125000, { fullName: 'Всё включено' }, '2026-09-14'),
      sample('a-raw-ai', 130000, 'AI', '2026-09-14'),
      sample('a-bb', 140000, { name: 'BB', fullName: 'BB - Только завтрак' }, '2026-09-15')
    ] },
    { id: 'meal-b', name: 'Отель Б', price: 100000, rating: 4, category: 4, tours: [
      sample('b-ai', 100000, { fullName: 'Всё включено' }, '2026-09-11'),
      sample('b-hb', 147000, { fullName: 'Half Board' }, '2026-09-16')
    ] },
    { id: 'meal-c', name: 'Отель В', price: 80000, rating: 3, category: 3, tours: [
      sample('c-ro', 80000, { fullName: 'Room only' }, '2026-09-13'),
      sample('c-hb', 152000, { fullName: 'Полупансион' }, '2026-09-17')
    ] }
  ];
  const supplierRequests = [];
  const record = request => { if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)) supplierRequests.push(request.url()); };
  const visible = () => page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId));
  const panel = page.locator('.search3-mobile-filter-panel');
  page.on('request', record);
  try {
    await page.evaluate(items => {
      const freeze = value => { if (value && typeof value === 'object') { Object.values(value).forEach(freeze); Object.freeze(value); } return value; };
      window.__mealOriginal = freeze(items);
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 100, items } }));
    }, items);
    await page.locator('#sortResults').selectOption('price');
    if (width < 1025 && !await panel.evaluate(node => node.open)) await panel.locator('summary').click();
    const field = page.locator('.search3-meal-filter'), select = field.locator('select'), presets = field.locator('.search3-filter-presets');
    assert.equal(await field.isVisible(), true, 'complete loaded meal facts expose the local facet');
    const options = Object.fromEntries(await select.locator('option').evaluateAll(nodes => nodes.map(node => [node.value, node.textContent])));
    assert.equal(options['meal:label:всё включено'], 'Всё включено');
    assert.equal(options['meal:label:ai'], 'AI', 'raw supplier AI remains a separate exact fact');
    assert.equal(options['meal:label:bb - только завтрак'], 'BB - Только завтрак');
    assert.equal(options['meal:label:half board'], 'Half Board');
    assert.equal(options['meal:all-inclusive'], undefined, 'retired semantic family identity is absent');
    assert.equal(options['meal:breakfast'], undefined, 'retired breakfast family identity is absent');
    assert.equal(await presets.locator('button').count(), 0, 'SEARCH does not invent semantic meal quick presets from supplier text');

    await select.selectOption('meal:label:всё включено');
    assert.equal(await select.inputValue(), 'meal:label:всё включено');
    assert.deepEqual(await visible(), ['meal-b', 'meal-a'], 'exact identical supplier labels form the selected projection');
    const calendar = page.locator('#currentPriceCalendar');
    assert.deepEqual(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.map(node => node.dataset.calendarDate)), ['2026-09-11', '2026-09-12', '2026-09-14']);
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-11', 'calendar follows the exact selected meal projection');

    const a = page.locator('#results [data-hotel-id=meal-a]');
    assert.equal(await a.locator('.tour-more-toggle').innerText(), 'Показать варианты · 2', 'raw AI is not merged into the localized all-inclusive label');
    await a.locator('.tour-more-toggle').click();
    assert.deepEqual(await a.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['a-ai', 'a-ai-extra']);
    assert.deepEqual(await a.locator('.tour-facts').evaluateAll(nodes => nodes.map(node => Array.from(node.querySelectorAll('.tour-fact')).find(fact => fact.querySelector('small')?.textContent === 'Питание')?.querySelector('b')?.textContent || '')), ['Всё включено', 'Всё включено']);
    assert.deepEqual(await a.locator('.tour-facts').evaluateAll(nodes => nodes.map(node => Array.from(node.querySelectorAll('.tour-fact')).find(fact => fact.querySelector('small')?.textContent === 'Номер')?.querySelector('b')?.textContent || '')), ['STANDARD LAND VIEW · Двухместное', 'STANDARD LAND VIEW · Двухместное'], 'offer rows keep exact supplier room facts while placement remains readable');
    assert.equal(await a.locator('[data-tid=a-raw-ai]').count(), 0, 'raw AI stays outside a localized exact-label selection');
    await a.locator('.tour-more-toggle').click();

    await page.evaluate(items => {
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-continued', { detail: { items } }));
    }, items);
    assert.equal(await select.inputValue(), 'meal:label:всё включено', 'progressive rerender preserves the exact selected identity');
    assert.deepEqual(await visible(), ['meal-b', 'meal-a']);

    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })));
    assert.equal(await field.isVisible(), false, 'dirty edit hides stale result filters');
    assert.equal(await select.inputValue(), 'meal:label:всё включено', 'dirty edit preserves the retained projection');
    await page.evaluate(() => window.V2Results.rerender());
    if (width < 1025 && !await panel.evaluate(node => node.open)) await panel.locator('summary').click();
    assert.equal(await field.isVisible(), true);
    assert.deepEqual(await visible(), ['meal-b', 'meal-a']);

    await page.evaluate(items => window.V2Results.render(items.concat([{ id: 'meal-incomplete', name: 'Неполные данные', price: 70000, tours: [{ id: 'unknown', price: 70000, meal: { id: 7 } }] }])), items);
    assert.equal(await field.isVisible(), false, 'unknown meal identity hides the facet instead of guessing');
    assert.equal(await select.inputValue(), '', 'coverage loss clears the stale meal selection');
    assert.equal((await visible()).length, 4, 'unknown meal facts fail open and keep loaded hotels visible');

    await page.evaluate(items => { window.V2Results.render(items); window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 101, items } })); }, items);
    if (width < 1025 && !await panel.evaluate(node => node.open)) await panel.locator('summary').click();
    await select.selectOption('meal:label:всё включено');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 102 } })));
    assert.equal(await select.inputValue(), '', 'a real new search clears the local meal selection');
    assert.equal(await field.isVisible(), false);

    const boundaryItems = [
      { id: 'meal-boundary-a', name: 'Границы питания А', price: 101000, tours: [sample('boundary-hb-plus', 101000, 'HB+', '2026-09-20'), sample('boundary-premium', 102000, 'Premium All Inclusive', '2026-09-21')] },
      { id: 'meal-boundary-b', name: 'Границы питания Б', price: 103000, tours: [sample('boundary-breakfast-dinner', 103000, 'Breakfast and dinner', '2026-09-22'), sample('boundary-not-ai', 104000, 'Not all inclusive', '2026-09-23')] }
    ];
    await page.evaluate(items => { window.V2Results.render(items); window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 103, items } })); }, boundaryItems);
    if (width < 1025 && !await panel.evaluate(node => node.open)) await panel.locator('summary').click();
    const boundaryOptions = Object.fromEntries(await select.locator('option').evaluateAll(nodes => nodes.map(node => [node.value, node.textContent])));
    assert.equal(boundaryOptions['meal:label:hb+'], 'HB+');
    assert.equal(boundaryOptions['meal:label:premium all inclusive'], 'Premium All Inclusive');
    assert.equal(boundaryOptions['meal:label:breakfast and dinner'], 'Breakfast and dinner');
    assert.equal(boundaryOptions['meal:label:not all inclusive'], 'Not all inclusive');
    await select.selectOption('meal:label:not all inclusive');
    assert.deepEqual(await visible(), ['meal-boundary-b']);
    assert.equal(await page.locator('[data-hotel-id=meal-boundary-b] .direct-tour').getAttribute('data-tid'), 'boundary-not-ai', 'negated supplier label remains its own identity');

    const longLabel = '<img src=x onerror=bad()> Очень длинное описание питания от поставщика без сокращений';
    await page.evaluate(({ items, longLabel }) => {
      const unsafe = items.map((hotel, hi) => ({ ...hotel, tours: hotel.tours.map((offer, ti) => hi === 0 && ti === 0 ? { ...offer, meal: { fullName: longLabel } } : { ...offer }) }));
      window.V2Results.render(unsafe);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: 104, items: unsafe } }));
    }, { items, longLabel });
    if (width < 1025 && !await panel.evaluate(node => node.open)) await panel.locator('summary').click();
    assert.equal(await select.locator('img').count(), 0, 'supplier labels are rendered as text, never HTML');
    assert.equal(await select.locator('option').filter({ hasText: '<img src=x onerror=bad()>' }).count(), 1, 'untrusted supplier label remains literal text');
    assert.equal((await snapshot(page)).overflow, false, 'long exact supplier label does not widen the toolbar');
    assert.equal(await page.evaluate(() => JSON.stringify(window.__mealOriginal)), JSON.stringify(items), 'meal filtering never mutates the frozen source result set');
    assert.deepEqual(supplierRequests, [], 'local meal filtering issues no supplier or lead requests');
    if (!previous) await page.screenshot({ path: path.join(output, `meal-filter-${width}.png`), fullPage: true });
  } finally {
    page.off('request', record);
    await page.evaluate(items => { window.Search3LocalHotelFilter.reset(); window.V2Results.render(items); }, hotels);
  }
}
async function checkHydratedHotelFacets(page, width, previous, details) {
  const items = [
    {...hotels[0],id:999991,name:'Исходное название',category:3,rating:4.9,region:{name:'Анталья'},price:90000,tours:[{...tour,id:'facts-a',price:90000}]},
    {...hotels[0],id:'facts-b',name:'Второй отель',category:4,rating:4.2,region:{name:'Анталья'},price:100000,tours:[{...tour,id:'facts-b',price:100000}]},
    {...hotels[0],id:'facts-c',name:'Третий отель',category:5,rating:4.8,region:{name:'Кемер'},price:110000,tours:[{...tour,id:'facts-c',price:110000}]}
  ];
  const panel=page.locator('.search3-mobile-filter-panel'),rating=page.locator('.search3-rating-filter select'),category=page.locator('.search3-category-filter select'),region=page.locator('.search3-region-filter select');
  const visible=()=>page.locator('#results .hotel-card:visible').evaluateAll(nodes=>nodes.map(node=>node.dataset.hotelId));
  const requests=[],record=request=>{if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname))requests.push(request.url());};
  const render=async list=>page.evaluate(items=>{window.Search3LocalHotelFilter.reset();window.V2Results.render(items);window.dispatchEvent(new CustomEvent('v2:search-complete',{detail:{items}}));},list);
  page.on('request',record);
  let release;
  details.facts={item:{id:999991,name:'Локальный отель',category:5,rating:2.5,region:{name:'Сиде'},description:'Описание из локального справочника',detailsAvailable:true},ready:new Promise(resolve=>{release=resolve;})};
  try {
    await page.locator('#sortResults').selectOption('price');
    await render(items);
    await page.locator('[data-hotel-id="999991"] .hotel-title').scrollIntoViewIfNeeded();
    if(width<1025&&!await panel.evaluate(node=>node.open))await panel.locator('summary').click();
    await rating.selectOption('4.5');
    assert.deepEqual(await visible(),['999991','facts-c']);
    release();
    await page.waitForFunction(()=>document.querySelector('[data-hotel-id="999991"] .hotel-title')?.textContent==='Локальный отель');
    assert.deepEqual(await visible(),['facts-c'],'late local rating immediately updates the active facet and count');
    assert.equal(await page.locator('#resultSummary').innerText(),'Показано отелей: 1 из 3 · цены из текущего поиска');
    assert.deepEqual(await category.locator('option').evaluateAll(nodes=>nodes.map(node=>node.value)),['0','5','4'],'local stars replace the stale source category');
    await rating.selectOption('0');await region.selectOption({label:'Сиде'});
    assert.deepEqual(await visible(),['999991'],'region filter matches the hydrated card geography');
    await category.selectOption('5');
    assert.deepEqual(await visible(),['999991']);
    assert.match(await page.locator('[data-hotel-id="999991"]').innerText(),/Рейтинг 2,5/);
    assert.equal((await page.locator('[data-hotel-id="999991"] .hotel-price').innerText()).replace(/\s/g,''),'90000₽','hydration does not change the exact offer price');
    assert.equal(await page.locator('[data-hotel-id="999991"] .direct-tour').getAttribute('data-tid'),'facts-a');
    assert.equal((await snapshot(page)).overflow,false);
    if(!previous&&[375,1440].includes(width))await page.screenshot({path:path.join(output,`hotel-facts-${width}.png`),fullPage:true});
    await page.evaluate(()=>window.Search3LocalHotelFilter.reset());
    await page.locator('#sortResults').selectOption('rating');
    assert.deepEqual(await visible(),['facts-c','facts-b','999991'],'rating sorting uses the visible local rating');
    await page.locator('#sortResults').selectOption('stars');
    assert.deepEqual(await visible(),['999991','facts-c','facts-b'],'star sorting uses local category and retains price tie-break');
    details.facts={item:{id:999992,name:'Отель без оценки',category:null,rating:null,detailsAvailable:true,description:'Описание без категории и рейтинга'},ready:new Promise(resolve=>{release=resolve;})};
    await page.locator('#sortResults').selectOption('price');
    await render([{...items[0],id:999992},...items.slice(1)]);
    await page.locator('[data-hotel-id="999992"] .hotel-title').scrollIntoViewIfNeeded();
    if(width<1025&&!await panel.evaluate(node=>node.open))await panel.locator('summary').click();
    await rating.selectOption('4.5');release();
    await page.waitForFunction(()=>document.querySelector('[data-hotel-id="999992"] .hotel-title')?.textContent==='Отель без оценки');
    assert.equal(await rating.inputValue(),'0','incomplete hydrated ratings reset an unavailable facet');
    assert.equal(await rating.isVisible(),false,'coverage is not relaxed for local details');
    assert.equal(await category.isVisible(),false,'an unknown local category cannot masquerade as the old source category');
    assert.equal((await visible()).length,3,'unknown facts do not leave hotels silently filtered out');
    const coverageItems=Array.from({length:100},(_,index)=>({...items[index%items.length],id:'category-coverage-'+index,name:'Отель покрытия '+index,category:index===99?null:3+(index%3),rating:4+(index%2)*.5,tours:[{...tour,id:'category-coverage-tour-'+index,price:90000+index}]}));
    await render(coverageItems);
    assert.equal(await category.isVisible(),true,'99 known categories out of 100 expose the useful category facet');
    assert.equal(await category.locator('option').first().innerText(),'Любая категория · 99/100','partial coverage is explicit instead of pretending to be complete');
    assert.deepEqual(await category.locator('option').evaluateAll(nodes=>nodes.map(node=>node.value)),['0','5','4','3']);
    assert.equal((await visible()).length,100,'the unknown-category hotel remains visible until the visitor selects a category');
    await category.selectOption('5');
    assert.equal((await visible()).length,33,'an explicit category selection matches only hotels with that known category');
    assert.equal(await page.locator('[data-hotel-id="category-coverage-99"]').isVisible(),false,'unknown category is never guessed into a selected star bucket');
    assert.equal(await page.locator('#resultSummary').innerText(),'Показано отелей: 33 из 100 · цены из текущего поиска');
    assert.equal((await snapshot(page)).overflow,false);
    if(!previous&&[375,1440].includes(width))await page.screenshot({path:path.join(output,`category-coverage-${width}.png`),fullPage:true});
    assert.deepEqual(requests,[],'local hydration/filtering/sorting never requests supplier or lead endpoints');
  } finally {release();details.facts=null;page.off('request',record);await page.evaluate(()=>window.Search3LocalHotelFilter.reset());await page.locator('#sortResults').selectOption('price');}
}

async function checkAndromedaExpansion(page, width, previous, control, hotelDetails) {
  let offerComposition, selectedQuote;
  const tvHotel = {
    id: 21477,
    name: 'Movenpick Resort',
    country: { name: 'Египет' },
    region: { name: 'Шарм-эль-Шейх' },
    category: 4,
    rating: 4.7,
    price: 165000,
    picturelink: picture,
    tours: [{ ...tour, id: 'tv-andromeda-control', price: 165000, operator: { name: 'TEST OPERATOR' } }]
  };
  const searchParams = { departureId: '1', countryId: '1', dateFrom: '2026-09-18', dateTo: '2026-09-18', nightsFrom: '8', nightsTo: '8', adults: '2', childs: [], currency: 'RUB' };
  const start = async (generation, baseHotels = [tvHotel]) => {
    await page.evaluate(({ generation, searchParams, baseHotels, unconfirmedOnly }) => {
      if (unconfirmedOnly) { const done = event => { if (event.detail.provider === 'andromeda' && event.detail.generation === generation && event.detail.status === 'complete') { window.__fuelProviderComplete = generation; window.removeEventListener('v2:provider-status', done); } }; window.addEventListener('v2:provider-status', done); }
      Object.defineProperty(window.V2SearchLifecycle, 'generation', { configurable: true, get: () => generation });
      Object.defineProperty(window.V2SearchLifecycle, 'snapshot', { configurable: true, get: () => ({ ...searchParams }) });
      window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { generation } }));
      window.V2Results.render(baseHotels, { empty: true });
    }, { generation, searchParams, baseHotels, unconfirmedOnly: control.unconfirmedOnly });
    if (control.unconfirmedOnly) await page.waitForFunction(generation => window.__fuelProviderComplete === generation, generation);
    else await page.locator('#results .hotel-card[data-hotel-id="21477"] .tour-more-toggle').waitFor();
  };
  const waitForExpansion = status => page.waitForFunction(status => window.V2Results.state.items.some(hotel => String(hotel.id) === '21477' && hotel.andromedaExpansion?.status === status), status);
  control.enabled = true;
  control.requests.length = 0;
  control.quoteRequests.length = 0;
  control.failSecond = false;
  try {
    control.unconfirmedOnly = true;
    await start(71);
    const rejectedCard = page.locator('#results .hotel-card[data-hotel-id="21477"]');
    assert.equal(await rejectedCard.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')), '165000₽', 'unconfirmed cheap SAMO offer cannot reduce the visible hotel minimum');
    assert.equal(await rejectedCard.locator('[data-andromeda-detail],.tour-more-toggle').count(), 0, 'unconfirmed offers cannot expose tour/detail actions');
    assert.equal(await rejectedCard.locator('.direct-tour').getAttribute('data-tid'), 'tv-andromeda-control', 'Tourvisor offer remains intact');
    assert.doesNotMatch(await rejectedCard.innerText(), /НЕПОДТВЕРЖДЁННЫЙ/);
    assert.equal((await snapshot(page)).overflow, false);
    if (!previous) await rejectedCard.screenshot({path:path.join(output,`fuel-unconfirmed-hidden-${width}.png`),animations:'disabled'});
    await page.evaluate(() => window.V2Results.render([], {empty:true}));
    assert.equal(await page.locator('#results .hotel-card').count(), 0, 'SAMO-only hotel with no fuel evidence stays out of results');
    assert.equal(control.quoteRequests.length, 0, 'rejection never probes or calculates a quote');
    control.unconfirmedOnly = false;
    control.requests.length = 0;
    await start(73);
    const card = page.locator('#results .hotel-card[data-hotel-id="21477"]');
    await page.evaluate(() => window.V2Results.render([], { empty: true }));
    assert.equal(await page.locator('#results .hotel-card').count(), 1, 'prepared local hotel remains visible without a Tourvisor offer');
    assert.equal(await card.locator('.tour-more-toggle').count(), 1, 'a single grouped provider seed uses the same hotel offer disclosure');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'false', 'the grouped seed begins at hotel level');
    assert.equal(await card.locator('.tour-row,.direct-tour,[data-andromeda-expand]').count(), 0, 'a grouped seed does not expose a premature exact row or a separate source button');
    await card.locator('.hotel-details').waitFor();
    assert.deepEqual(hotelDetails.requests, ['21477'], 'one local hotel id loads its trusted details exactly once');
    assert.equal(await card.locator('.hotel-gallery-main').getAttribute('src'), 'https://catalog.example/hotel-21477.svg', 'supplier-only offer uses the exact-ID local catalog photo');
    assert.match(await card.locator('.hotel-place').innerText(), /Наама-Бей/, 'local subregion reaches the card');
    assert.equal(await card.locator('.hotel-gallery-thumb').count(), 2, 'the gallery exposes only alternate photos and never repeats the active main photo');
    const initialGallerySources = await card.locator('.hotel-gallery img').evaluateAll(images => images.map(image => image.getAttribute('src')));
    assert.equal(new Set(initialGallerySources).size, initialGallerySources.length, 'every initially visible gallery image is unique');
    assert.equal(initialGallerySources.every(source => source.startsWith('https://catalog.example/')), true, 'a populated local gallery never mixes in the supplier result photo');
    const hotelInfo = card.locator('.hotel-details');
    assert.match(await card.locator('.hotel-description-summary').innerText(), /Локальное описание отеля: Hard Rock Café & SPA/, 'encoded local description is readable before opening the hotel disclosure');
    assert.equal(await hotelInfo.locator('summary').innerText(), 'Подробнее об отеле', 'hotel disclosure promises complete local details');
    await hotelInfo.locator('summary').press('Enter');
    assert.equal(await hotelInfo.evaluate(node => node.open), true, 'hotel description opens through the native keyboard disclosure');
    assert.equal(await card.locator('.hotel-description-summary').isVisible(), false, 'the clamped summary does not duplicate the open full description');
    assert.match(await hotelInfo.innerText(), /Локальное описание отеля[\s\S]*Наама-Бей[\s\S]*Открытый бассейн[\s\S]*Wi-Fi/, 'trusted local description and characteristics are available before choosing an offer');
    assert.match(await hotelInfo.innerText(), /Kavaklı[\s\S]*Стандарт · Семейный номер/, 'numeric address entities and encoded room-list markup become readable text');
    assert.doesNotMatch(await hotelInfo.innerText(), /&#|&(?:amp|lt|gt|nbsp);|alert\(1\)/, 'hotel details contain neither entity noise nor encoded active markup');
    assert.equal(await hotelInfo.locator('script,img,style,iframe').count(), 0, 'encoded markup creates no active content in hotel details');
    assert.ok((await hotelInfo.locator('summary').boundingBox()).height >= 44, 'hotel details disclosure keeps a full touch target');
    if (width > 760) {
      const openDetailsGeometry = await card.evaluate(node => {
        const rect = selector => {
          const box = node.querySelector(selector).getBoundingClientRect();
          return { width: box.width, height: box.height };
        };
        return { photo: rect('.hotel-photo'), body: rect('.hotel-body') };
      });
      assert.ok(openDetailsGeometry.photo.height <= 360, 'desktop hotel details keep the photo bounded to its media region: ' + JSON.stringify(openDetailsGeometry));
      assert.ok(openDetailsGeometry.photo.height < openDetailsGeometry.body.height, 'long desktop hotel details do not stretch the photo to the copy height: ' + JSON.stringify(openDetailsGeometry));
    }
    assert.equal((await snapshot(page)).overflow, false, width + ': decoded hotel details fit the viewport');
    if (!previous && [375,1440].includes(width)) await card.screenshot({ path: path.join(output, `hotel-details-entities-${width}.png`), animations: 'disabled' });
    await card.locator('.hotel-gallery-thumb').first().click();
    assert.equal(await card.locator('.hotel-gallery-main').getAttribute('src'), 'https://catalog.example/hotel-21477-2.svg', 'gallery changes the main local photo without changing the offer');
    assert.equal(await card.locator('.hotel-gallery-thumb').first().locator('img').getAttribute('src'), 'https://catalog.example/hotel-21477.svg', 'the clicked thumbnail retains the previous main photo so it remains reachable');
    assert.equal(await card.locator('.hotel-gallery-thumb[aria-pressed]').count(), 0, 'swap actions do not expose a false selected-thumbnail state');
    const swappedGallerySources = await card.locator('.hotel-gallery img').evaluateAll(images => images.map(image => image.getAttribute('src')));
    assert.equal(new Set(swappedGallerySources).size, swappedGallerySources.length, 'gallery swapping preserves unique visible images');
    await hotelInfo.locator('summary').press('Enter');
    assert.equal(await card.locator('.hotel-description-summary').isVisible(), width > 760, width > 760 ? 'closing details restores the concise hotel summary' : 'closed mobile keeps canonical description behind disclosure');
    await card.locator('.hotel-gallery-main').scrollIntoViewIfNeeded();
    await page.waitForFunction(() => { const img = document.querySelector('[data-hotel-id="21477"] .hotel-gallery-main'); return img && img.complete && img.naturalWidth > 0; });
    await card.locator('.hotel-gallery-main').evaluate(img => img.decode());
    if (!previous) await card.screenshot({ path: path.join(output, `catalog-hotel-${width}.png`), animations: 'disabled' });
    await page.evaluate(hotel => window.V2Results.render([hotel], { empty: true }), tvHotel);
    if (width >= 1025) {
      assert.equal(await page.locator('.results-filter-rail').isVisible(), true, 'cross-provider offers expose the useful provider/source facet in the canonical desktop rail');
      assert.ok((await card.boundingBox()).width >= 700, 'desktop single-hotel results remain readable beside the truthful provider/source facet');
    }
    assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle').count(), 0, 'cross-provider hotel stays hotel-level before exact variants are disclosed');
    assert.equal(await card.locator('.hotel-offers-summary').count(), 1, 'cross-provider idle state exposes one truthful hotel minimum');
    assert.equal(await card.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), 'от155079₽', 'provider discovery exposes its actual hotel minimum without turning it into a selectable quote');
    const expansionToggle = card.locator('.tour-more-toggle');
    assert.equal(await expansionToggle.count(), 1, 'the hotel exposes one common offer disclosure for all sources');
    assert.equal(await expansionToggle.innerText(), 'Показать варианты · 2', 'the common action describes the hotel offers without asking the user to choose an API source');
    assert.equal(await card.locator('[data-andromeda-expand],.provider-expansion,.provider-expansion-status').count(), 0, 'the collapsed hotel has no separate source action or loading status');
    assert.doesNotMatch(await card.innerText(), /Ещё варианты из Андромеды|Дополнительные предложения/);
    assert.ok((await expansionToggle.boundingBox()).height >= 44, 'the common disclosure keeps a full touch target');
    assert.equal(await expansionToggle.evaluate(node => node.scrollWidth <= node.clientWidth + 1), true, 'the common disclosure stays inside the card');
    assert.equal((await snapshot(page)).overflow, false, width + ': collapsed hotel disclosure fits the viewport');
    if (!previous) await page.screenshot({ path: path.join(output, `andromeda-idle-${width}.png`), fullPage: true });
    await expansionToggle.click();
    await waitForExpansion('complete');
    assert.deepEqual(control.requests.map(request => [request.action || 'search', request.page]), [['search', 1], ['hotel_offers', 1], ['hotel_offers', 2]], 'one discovery and two scoped provider pages load sequentially');
    const expectedScope = { local_id: 21477, seed: { provider: 'andromeda', search_ref: 'd'.repeat(64), generation: 73, page: 1, offer_ref: 'offer_' + '9'.repeat(64) } };
    for (const request of control.requests.slice(1)) {
      assert.deepEqual(request.hotel_scope, expectedScope, 'the common disclosure retains the exact saved hotel and seed scope');
      assert.equal(request.generation, 73, 'scoped requests retain the current search generation');
      assert.deepEqual(request.params, searchParams, 'scoped requests retain the original search parameters');
    }
    assert.equal(await expansionToggle.getAttribute('aria-expanded'), 'true', 'one click opens the hotel and retains its expanded state throughout loading');
    assert.equal(await expansionToggle.evaluate(node => node === document.activeElement), true, 'scoped-page rerenders retain focus on the common disclosure');
    assert.equal(await card.locator('.provider-expansion-status,[data-andromeda-expand]').count(), 0, 'completed results need no duplicate source-specific completion status');
    assert.equal((await snapshot(page)).overflow, false, width + ': completed hotel offers fit the viewport');
    if (!previous) await page.screenshot({ path: path.join(output, `andromeda-complete-${width}.png`), fullPage: true });
    assert.equal(await card.locator('.tour-row').count(), 3, 'one common disclosure replaces the grouped representative with exact provider variants and retains Tourvisor');
    const expandedMeals = await card.locator('.tour-facts').evaluateAll(nodes => nodes.map(node => Array.from(node.querySelectorAll('.tour-fact')).find(fact => fact.querySelector('small')?.textContent === 'Питание')?.querySelector('b')?.textContent || ''));
    assert.deepEqual([...expandedMeals].sort(), ['AI', 'AI', 'Всё включено'].sort(), 'provider offers preserve exact supplier meal facts instead of sharing an invented identity');
    assert.deepEqual((await card.locator('.tour-row .hotel-price').allTextContents()).map(text => Number(text.replace(/[^\d]/g, ''))).sort((a, b) => a - b), [155000, 156000, 165000], 'the unified presentation preserves each exact offer price');
    assert.equal(await card.locator('.direct-tour').count(), 1, 'only the existing Tourvisor offer remains selectable');
    assert.equal(await card.locator('.tour-row').filter({ hasText: 'Андромеда' }).count(), 0, 'expanded offers keep internal provider provenance out of customer copy');
    assert.equal(await card.locator('.tour-selection-note').filter({ hasText: 'Перед выбором проверим цену и рейсы' }).count(), 2, 'every quote-required offer keeps a provider-neutral readiness boundary');
    const detailToggle = card.locator('[data-andromeda-detail]').first();
    assert.ok((await detailToggle.boundingBox()).height >= 44, 'provider detail action keeps a full touch target');
    offerComposition = await card.locator('.tour-row:has(.provider-detail-toggle)').evaluateAll(rows => rows.map(row => {
      const origin = row.getBoundingClientRect();
      const rect = node => { const r = node.getBoundingClientRect(); return { x:r.x-origin.x, y:r.y-origin.y, width:r.width, height:r.height }; };
      const price = row.querySelector('.hotel-price'), button = row.querySelector('.provider-detail-toggle'), note = row.querySelector('.tour-selection-note');
      return { height:origin.height, width:origin.width, price:rect(price), button:rect(button), note:rect(note), noteFont:parseFloat(getComputedStyle(note).fontSize), clipped:[price,button,note].some(n => n.scrollWidth > n.clientWidth + 1 || n.scrollHeight > n.clientHeight + 1) };
    }));
    for (const offer of offerComposition) {
      if (width <= 375 || width >= 1200) assert.ok(offer.height <= (width <= 320 ? 240 : width <= 375 ? 190 : 135), 'provider rows stay compact with the full price warning: '+JSON.stringify({width,offer}));
      assert.ok(offer.button.height >= 44 && offer.noteFont >= 13 && !offer.clipped, 'provider action and full price warning remain readable');
      for (const box of [offer.price, offer.button, offer.note]) assert.ok(box.x >= 0 && box.x + box.width <= offer.width + 1, 'provider price, action and warning stay inside the offer');
      assert.ok(offer.price.x + offer.price.width <= offer.button.x + 1 || offer.price.y + offer.price.height <= offer.button.y + 1, 'price and detail action never overlap');
      assert.ok(offer.note.y >= offer.button.y + offer.button.height - 1, 'the full quote warning follows the action without a tall interruption');
    }
    if (!previous) {
      await page.locator('#resultsTools').evaluate(node => scrollTo({ top:node.getBoundingClientRect().top + scrollY - 12, behavior:'instant' }));
      await page.screenshot({ path:path.join(output, `offers-composition-${width}.png`), animations:'disabled' });
    }
    await detailToggle.click();
    await card.locator('.provider-detail').filter({ hasText: 'Подтверждённый тестовый отель' }).waitFor();
    assert.equal(await detailToggle.getAttribute('aria-expanded'), 'true', 'provider detail disclosure exposes its open state');
    assert.equal(await detailToggle.evaluate(node => node === document.activeElement), true, 'provider detail keeps keyboard focus after rerender');
    assert.match(await card.locator('.provider-detail').innerText(), /ANEX · 2026-09-18 · 8 ноч\. · 2 взр\. · AI · <script>номер<\/script> · Двухместное/);
    assert.equal(await card.locator('.provider-detail script').count(), 0, 'supplier detail strings are escaped instead of becoming markup');
    assert.match(await card.locator('.provider-detail').innerText(), /155[\u00a0 ]000 ₽/, 'details retain the accepted inclusive listing amount instead of an endpoint base amount');
    assert.match(await card.locator('.provider-detail').innerText(), /Топливный сбор учтён/);
    assert.doesNotMatch(await card.locator('.provider-detail').innerText(), /может потребовать доплаты/);
    assert.match(await card.locator('.provider-detail').innerText(), /Перед выбором проверим актуальную стоимость и рейсы/);
    assert.doesNotMatch(await card.locator('.provider-detail').innerText(), /Андромед|Источник|Бронирование пока недоступно/);
    assert.deepEqual(control.requests.map(request => request.action || 'search'), ['search', 'hotel_offers', 'hotel_offers', 'offer_detail'], 'details add one explicit saved-offer request only');
    const quoteButton = card.locator('[data-andromeda-quote]').first();
    await quoteButton.waitFor();
    assert.ok((await quoteButton.boundingBox()).height >= 44, 'explicit quote verification keeps a full touch target');
    await quoteButton.click();
    const selectButton = card.locator('[data-andromeda-select]').first();
    await selectButton.waitFor();
    assert.match(await card.locator('[data-andromeda-quote-panel]').innerText(), /Цена изменилась и подтверждена: 157[\u00a0 ]345,25[\u00a0 ]₽/,
      'the changed supplier price is explicit before selection');
    assert.equal(control.quoteRequests.length, 1, 'one explicit action performs one authoritative quote request');
    assert.equal(control.quoteRequests[0].listing_price_ref, 'listing_' + 'e'.repeat(64), 'quote retains the exact listed-price receipt');
    assert.equal(control.quoteRequests[0].offer_context.offer_ref, 'offer_' + '1'.repeat(64), 'quote retains the exact offer identity');
    await page.evaluate(() => {
      window.__andromedaSelectedRuntimeCalls = [];
      window.V2Runtime.api = (...args) => { window.__andromedaSelectedRuntimeCalls.push(args); throw new Error('Andromeda selection must not call Tourvisor'); };
    });
    await selectButton.click();
    const selected = page.locator('#selectedTour');
    await selected.locator('.provider-lead-handoff').waitFor();
    selectedQuote = await selected.evaluate(node => ({
      text: node.innerText.replace(/\s+/g, ' ').trim(),
      price: node.querySelector('.selected-price')?.textContent.replace(/\s+/g, ' ').trim(),
      flights: node.querySelectorAll('.flight-segment').length,
      leadForms: node.querySelectorAll('.lead-form').length,
      continueActions: node.querySelectorAll('.search3-flight-continue').length,
      overflow: document.documentElement.scrollWidth > innerWidth + 2,
      current: {
        provider: window.V2TourController.currentTour?.provider,
        price: window.V2TourController.currentTour?.price,
        localId: window.V2TourController.currentTour?.providerSelection?.localId,
        generation: window.V2TourController.currentTour?.providerSelection?.generation,
        page: window.V2TourController.currentTour?.providerSelection?.page,
        offerRef: window.V2TourController.currentTour?.providerSelection?.offerRef,
        listingPriceRef: window.V2TourController.currentTour?.providerSelection?.listingPriceRef
      },
      tourvisorCalls: window.__andromedaSelectedRuntimeCalls
    }));
    assert.match(selectedQuote.price, /157 345,25 ₽/, 'canonical selected header uses the verified final price');
    assert.match(selectedQuote.text, /AT 101 · класс ECONOM/);
    assert.match(selectedQuote.text, /Передача менеджеру пока недоступна для этого источника/);
    assert.equal(selectedQuote.flights, 2, 'verified outbound and return flights use the canonical selected presentation');
    assert.equal(selectedQuote.leadForms, 0, 'unsafe Tourvisor lead form is absent for an Andromeda receipt');
    assert.equal(selectedQuote.continueActions, 0, 'no false continue-to-lead action is added');
    assert.equal(selectedQuote.overflow, false, width + ': verified provider selection fits the viewport');
    assert.deepEqual(selectedQuote.current, { provider: 'andromeda', price: 157345.25, localId: 21477, generation: 73, page: 1,
      offerRef: 'offer_' + '1'.repeat(64), listingPriceRef: 'listing_' + 'e'.repeat(64) }, 'canonical selected state retains the frozen provider receipt');
    assert.deepEqual(selectedQuote.tourvisorCalls, [], 'provider selection calls neither Tourvisor tour nor flights');
    assert.equal(control.quoteRequests.length, 1, 'selected presentation does not replay the quote');
    if (!previous) await selected.screenshot({ path: path.join(output, `andromeda-selected-${width}.png`), animations: 'disabled' });
    await selected.locator('.back-results').click();
    await page.waitForFunction(() => document.activeElement?.matches('[data-andromeda-select]'));
    assert.equal(await selectButton.evaluate(node => node === document.activeElement), true, 'return restores focus to the exact provider selection action');
    await detailToggle.click();
    assert.equal(await card.locator('.provider-detail').count(), 0, 'detail action closes the disclosure');
    await detailToggle.click();
    assert.equal(await card.locator('.provider-detail').count(), 1, 'cached detail reopens without a new request');
    assert.equal(control.requests.length, 4, 'close and cached reopen do not replay detail or provider pages');
    if (width <= 760) {
      const providerPrice = await card.locator('.tour-row').first().locator('.hotel-price').boundingBox();
      assert.ok(providerPrice.width >= 90 && providerPrice.height <= 45, 'mobile unquoted provider price stays readable instead of wrapping digit by digit');
    }
    assert.equal((await snapshot(page)).overflow, false, width + ': complete provider expansion fits the viewport');
    if (!previous) await page.screenshot({ path: path.join(output, `andromeda-details-${width}.png`), fullPage: true });
    await page.evaluate(() => window.AnyTourAndromedaProvider.expandHotel('21477'));
    assert.equal(control.requests.length, 4, 'rerender or repeated expansion cannot replay provider pages');
    await expansionToggle.click();
    assert.equal(await expansionToggle.getAttribute('aria-expanded'), 'false', 'the same action collapses the complete hotel offers');
    assert.equal(await card.locator('.tour-row,.provider-expansion-status').count(), 0, 'collapsed results hide both exact offers and their status');
    await expansionToggle.click();
    assert.equal(await card.locator('.tour-row').count(), 3, 'the same action reopens the received offers');
    assert.equal(control.requests.length, 4, 'collapse and reopen do not replay scoped pages or offer details');
    assert.equal(await expansionToggle.evaluate(node => node === document.activeElement), true, 'collapse and reopen retain keyboard focus on the common action');
    assert.equal(await page.locator('.search3-provider-filter').count(), 0, 'provider provenance is not exposed as a customer filter');
    assert.equal(await card.locator('.tour-row').count(), 3, 'all received offers remain available for operator and product decisions');
    assert.equal(await card.locator('.direct-tour').getAttribute('data-tid'), 'tv-andromeda-control', 'the selectable offer retains its original identity without a provider control');
    assert.equal(await expansionToggle.count(), 1, 'the hotel keeps one common offer disclosure');
    await expansionToggle.click();
    assert.equal(await card.locator('.tour-row').count(), 0, 'the common offer list can be collapsed');
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await expansionToggle.getAttribute('aria-expanded'), 'false', 'local sorting preserves the collapsed hotel');
    await expansionToggle.click();
    assert.equal(await card.locator('.tour-row').count(), 3, 'reopening restores all already loaded offers');
    assert.equal(await card.locator('.provider-detail-toggle').count(), 2, 'provider-specific verification actions stay attached to exact offers without exposing provenance as a filter');
    assert.equal(control.requests.length, 4, 'local sorting and disclosure do not replay supplier requests');
    await page.locator('#sortResults').selectOption('price');
    assert.equal(await card.locator('.tour-row').count(), 3, 'sorting keeps the already loaded common offers');

    control.failSecond = true;
    control.requests.length = 0;
    await start(74);
    const partialCard = page.locator('#results .hotel-card[data-hotel-id="21477"]');
    await partialCard.locator('.tour-more-toggle').click();
    await partialCard.locator('.provider-expansion-status[role=status]').filter({ hasText: 'Не все варианты загрузились. Полученные предложения сохранены.' }).waitFor();
    assert.deepEqual(control.requests.map(request => [request.action || 'search', request.page]), [['search', 1], ['hotel_offers', 1], ['hotel_offers', 2]], 'partial expansion stops after the failed scoped page without background replay');
    assert.equal(await partialCard.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'true', 'a partial response keeps the requested hotel offers open');
    assert.equal(await partialCard.locator('.tour-row').count(), 3, 'partial failure retains Tourvisor, grouped representative and received exact variant');
    assert.equal(await partialCard.locator('.direct-tour').count(), 1, 'partial provider data cannot enter the selection controller');
    assert.equal(await partialCard.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'partial-page rerenders retain disclosure focus');
    assert.equal((await snapshot(page)).overflow, false, width + ': partial provider status fits the viewport');
    if (!previous) await page.screenshot({ path: path.join(output, `andromeda-partial-${width}.png`), fullPage: true });
    await partialCard.locator('.tour-more-toggle').click();
    assert.equal(await partialCard.locator('.tour-row,.provider-expansion-status').count(), 0, 'closing partial results hides the status with the offers');
    await partialCard.locator('.tour-more-toggle').click();
    assert.equal(await partialCard.locator('.tour-row').count(), 3, 'reopening partial results retains every received offer');
    assert.equal(control.requests.length, 3, 'partial collapse and reopen do not retry failed provider pages');

    control.failSecond = false;
    control.requests.length = 0;
    let pageStarted;
    const held = { page: 1, started: new Promise(resolve => { pageStarted = resolve; }), onStart: () => pageStarted(), release: null };
    control.held = held;
    await start(75, []);
    const loadingCard = page.locator('#results .hotel-card[data-hotel-id="21477"]');
    const loadingToggle = loadingCard.locator('.tour-more-toggle');
    assert.equal(await loadingToggle.innerText(), 'Показать варианты · 1', 'a single grouped seed has the common hotel action');
    const firstPageRequest = page.waitForRequest(request => {
      if (!new URL(request.url()).pathname.endsWith('/api-andromeda-search3-preview.php')) return false;
      const input = JSON.parse(request.postData() || '{}');
      return input.action === 'hotel_offers' && input.generation === 75 && input.page === 1;
    });
    await loadingToggle.click();
    await firstPageRequest;
    await held.started;
    await loadingCard.locator('.provider-expansion-status[role=status]').filter({ hasText: 'Загружаем варианты тура…' }).waitFor();
    assert.equal(await loadingToggle.getAttribute('aria-expanded'), 'true', 'the first click on a grouped seed opens the hotel while scoped pages load');
    assert.equal(await loadingToggle.evaluate(node => node === document.activeElement), true, 'loading retains focus on the common disclosure');
    assert.equal(await loadingCard.locator('.direct-tour').count(), 0, 'the grouped provider seed never becomes directly selectable');
    assert.equal((await snapshot(page)).overflow, false, width + ': loading status fits the expanded hotel');
    if (!previous) await page.screenshot({ path: path.join(output, `andromeda-loading-${width}.png`), fullPage: true });
    await loadingToggle.click();
    assert.equal(await loadingToggle.getAttribute('aria-expanded'), 'false', 'the user can collapse a hotel while its scoped request is pending');
    assert.equal(await loadingCard.locator('.tour-row,.provider-expansion-status').count(), 0, 'pending status is hidden with the collapsed offers');
    held.release();
    await waitForExpansion('complete');
    assert.equal(await loadingToggle.getAttribute('aria-expanded'), 'false', 'a late completion preserves the user’s collapsed state');
    assert.equal(await loadingCard.locator('.tour-row,.provider-expansion-status').count(), 0, 'late completion does not reopen offers or expose a separate status');
    assert.equal(await loadingToggle.evaluate(node => node === document.activeElement), true, 'late completion retains focus on the collapsed hotel action');
    await loadingToggle.click();
    assert.equal(await loadingCard.locator('.tour-row').count(), 2, 'the same action reveals the two completed exact offers from a single grouped seed');
    assert.equal(await loadingCard.locator('.direct-tour').count(), 0, 'loading through the common action preserves provider selection guards');
    assert.deepEqual(control.requests.map(request => [request.action || 'search', request.page]), [['search', 1], ['hotel_offers', 1], ['hotel_offers', 2]], 'opening, closing during loading and reopening use only the original scoped requests');
    return { offers: offerComposition, selectedQuote };
  } finally {
    if (control.held?.release) control.held.release();
    control.held = null;
    control.enabled = false;
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })));
  }
}
async function run(browser, width, previous) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } }), errors = [];
  const andromeda = { enabled: false, failSecond: false, requests: [], quoteRequests: [], held: null };
  const catalog = { recover: false, requests: [] };
  const hotelDetails = { requests: [] };
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    if (/^https:\/\/catalog\.example\/hotel-21477(?:-[23])?\.svg$/.test(url.href)) return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: decodeURIComponent(picture.split(',')[1]) });
    if (url.pathname.endsWith('/data/hotel-details-read-v1.php')) {
      const hotelId = url.searchParams.get('hotelId');
      hotelDetails.requests.push(hotelId);
      if (hotelDetails.facts && hotelId === String(hotelDetails.facts.item.id)) { const facts=hotelDetails.facts; await facts.ready; return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({ok:true,item:facts.item})}); }
      if (hotelId !== '21477') return route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ ok: false, error: 'Hotel not found' }) });
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, item: {
        id: 21477, name: 'Movenpick Resort', country: { name: 'Египет' }, region: { name: 'Шарм-эль-Шейх' }, subRegion: { name: 'Наама-Бей' },
        category: 4, rating: 4.7, detailsAvailable: true,
        primaryImage: 'https://catalog.example/hotel-21477.svg', images: ['https://catalog.example/hotel-21477.svg','https://catalog.example/hotel-21477-2.svg','https://catalog.example/hotel-21477-3.svg'],
        description: 'Локальное описание отеля: Hard Rock Caf&#233; &amp; SPA&nbsp;&lt;script&gt;alert(1)&lt;/script&gt;', address: 'Наама-Бей, Kavakl&#x131;', repair: 'Реновация 2025', roomTypes: '&lt;UL&gt;&lt;LI&gt;Стандарт&lt;/LI&gt;&lt;LI&gt;Семейный номер&lt;/LI&gt;&lt;/UL&gt;',
        infrastructure: [{ name: 'Открытый бассейн' }, { name: 'Ресторан' }], services: ['Wi-Fi', 'Детский клуб'], meals: [{ name: 'Всё включено' }]
      } }) });
    }
    if (catalog.recover && url.pathname.endsWith('/data/departures-v1.php')) {
      catalog.requests.push('departures');
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, items: [{ id: 1, russianName: 'Москва' }] }) });
    }
    if (catalog.recover && /\/(?:api[^/]*)\.php$/.test(url.pathname) && url.searchParams.get('action') === 'countries') {
      catalog.requests.push('countries');
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify([{ id: 4, russianName: 'Турция' }]) });
    }
    if (andromeda.enabled && url.pathname.endsWith('/api-andromeda-quote-preview.php')) {
      const input = JSON.parse(request.postData() || '{}');
      andromeda.quoteRequests.push(input);
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: {
        schema_version: 1, provider: 'andromeda', local_id: 21477, selection_enabled: true, booking_enabled: false,
        state: 'quote_verified', quote_state: 'verified', final_price: { amount: '157345.25', currency: 'RUB' },
        final_price_verified: true, flight_selection_required: false,
        flights: [
          { direction: '0', name: 'AT 101', datebeg: '2026-09-18 09:30', dateend: '2026-09-18 14:00', class: 'ECONOM', departure: { town: 'Москва', port: 'SVO' }, arrival: { town: 'Шарм-эль-Шейх', port: 'SSH' } },
          { direction: '1', name: 'AT 102', datebeg: '2026-09-26 16:00', dateend: '2026-09-26 20:30', class: 'ECONOM', departure: { town: 'Шарм-эль-Шейх', port: 'SSH' }, arrival: { town: 'Москва', port: 'SVO' } }
        ]
      } }) });
    }
    if (andromeda.enabled && url.pathname.endsWith('/api-andromeda-search3-preview.php')) {
      const input = JSON.parse(request.postData() || '{}');
      andromeda.requests.push(input);
      if (input.action === 'hotel_offers' && andromeda.held?.page === input.page) {
        await new Promise(resolve => { andromeda.held.release = resolve; andromeda.held.onStart(); });
      }
      if (input.action === 'hotel_offers' && andromeda.failSecond && input.page === 2) return route.abort('failed');
      if (input.action === 'offer_detail') {
        assert.deepEqual(Object.keys(input.offer_context).sort(), ['generation', 'offer_ref', 'page', 'provider', 'search_ref'], 'saved detail uses the strict public identity, not the browser scope envelope');
        assert.equal(input.hotel_scope.local_id, 21477, 'expanded detail keeps its local hotel scope at the request root');
        assert.equal(input.hotel_scope.seed.generation, input.generation);
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { provider: 'andromeda', local_id: 21477, offer_context: input.offer_context, hotel: 'Подтверждённый тестовый отель', operator: 'ANEX', room: '<script>номер<\/script>', placement: 'DBL', checkin: '2026-09-18', nights: 8, adults: 2, children: 0, meal: 'AI', price: { amount: '155079.00', currency: 'RUB' } } }) });
      }
      const offerRef = 'offer_' + String(input.action === 'hotel_offers' ? input.page : 9).repeat(64);
      const seed = { provider: 'andromeda', search_ref: 'd'.repeat(64), generation: input.generation, page: 1, offer_ref: 'offer_' + '9'.repeat(64) };
      const context = input.action === 'hotel_offers'
        ? { ...seed, page: input.page, offer_ref: offerRef, hotel_scope: input.hotel_scope }
        : seed;
      const hotel = { local_id: 21477, name: 'Movenpick Resort', provider: 'andromeda', mapping_status: 'resolved', country: 'Египет', region: 'Шарм-эль-Шейх', category: 4, rating: 4.7,
        catalog: { hotel_id: 21477, source: 'tourvisor', image_url: 'https://catalog.example/hotel-21477.svg', subregion: 'Наама-Бей', sea_distance: null },
        andromeda_content: { source: 'andromeda', region: 'Шарм-эль-Шейх' },
        tours: [{ provider: 'andromeda', offer_ref: offerRef, offer_context: context, listing_price_ref: 'listing_' + 'e'.repeat(64), price: { amount: input.action === 'hotel_offers' ? String(154000 + input.page * 1000) : '155079.00', currency: 'RUB' }, checkin: '2026-09-18', nights: 8, meal: 'AI', room: input.action === 'hotel_offers' ? 'ROOM ' + input.page : 'GROUPED ROOM', placement: '2 ADL', operator: 'ANEX' }] };
      const unconfirmed = {...hotel.tours[0], offer_ref:'offer_'+'f'.repeat(64), offer_context:{...context,offer_ref:'offer_'+'f'.repeat(64)}, price:{amount:'1',currency:'RUB'}, room:'НЕПОДТВЕРЖДЁННЫЙ'};
      hotel.tours = andromeda.unconfirmedOnly ? [unconfirmed] : [withFuel(hotel.tours[0]),unconfirmed];
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { provider: 'andromeda', generation: input.generation, page: input.page, pages_count: input.action === 'hotel_offers' ? 2 : 1, grouped: input.action === 'hotel_offers' ? false : true, hotels: [hotel] } }) });
    }
    if (url.origin !== new URL(base).origin || request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
    if (previous && url.pathname.endsWith('/bundle-v1.php') && url.searchParams.get('type') === 'js') return route.fulfill({ status: 200, contentType: 'application/javascript', body: raw });
    return route.continue();
  });
  try {
    assert.equal((await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' })).status(), 200);
    await page.waitForFunction(() => window.V2Results && window.V2TourController && document.querySelector('#tourSearch')?.dataset.search3Ready === '1');
    // This offline fixture aborts every supplier/catalog request. Wait for its
    // real recovery UI before comparing geometry; otherwise that asynchronous
    // sibling can be inserted between raw/served snapshots (160px at 375px).
    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    assert.equal(await page.locator('.catalog-recovery').isVisible(), true, 'blocked catalogs expose their canonical recovery before result measurement');
    await page.locator('[name=dateFrom]').fill('2026-09-21');
    await page.locator('[name=count_people]').selectOption('3');
    catalog.recover = true;
    const catalogRetry = page.locator('.catalog-retry');
    await catalogRetry.focus();
    await catalogRetry.press('Enter');
    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'anytour-departures' && !document.querySelector('.catalog-recovery'));
    await page.waitForFunction(() => document.activeElement === document.querySelector('[name=from]'));
    assert.deepEqual(catalog.requests, ['departures', 'countries'], 'catalog retry uses only the existing departures and countries requests');
    assert.equal(await page.locator('[name=dateFrom]').inputValue(), '2026-09-21', 'catalog retry preserves the chosen departure date');
    assert.equal(await page.locator('[name=count_people]').inputValue(), '3', 'catalog retry preserves the party size');
    assert.equal(await page.locator('[name=from]').evaluate(node => node === document.activeElement), true, 'successful keyboard retry returns focus to the populated departure control');
    catalog.recover = false;
    assert.equal(await page.locator('#resultsSearchSummary').count(), 0, 'Search3 does not render the retired placeholder summary');
    const logo = page.locator('.at-global-header__logo img');
    assert.equal(await logo.isVisible(), true, 'canonical logo remains visible');
    const logoSource = await logo.getAttribute('src');
    const menu = page.locator('.at-global-header__mobile');
    const nav = page.locator('.at-global-header__nav');
    if (width <= 1024) {
      assert.equal(await nav.isVisible(), false, 'mobile uses the native disclosure');
      await menu.locator('summary').click();
      assert.equal(await menu.evaluate(node => node.open), true, 'native header opens');
      assert.equal(await menu.locator('[aria-current=page]').isVisible(), true, 'active search link is reachable');
      await menu.locator('summary').click();
      assert.equal(await menu.evaluate(node => node.open), false, 'native header closes');
    } else {
      assert.equal(await menu.isVisible(), false, 'desktop does not duplicate navigation');
      assert.equal(await nav.isVisible(), true, 'desktop navigation is directly available');
      assert.equal(await nav.locator('a').count(), 6, 'all canonical destinations remain');
      assert.equal(await nav.locator('[aria-current=page]').isVisible(), true, 'active search link is reachable');
    }
    await page.evaluate(items => window.V2Results.render(items), hotels);
    await page.waitForSelector('#results .direct-tour');
    const primaryParameters = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]);
    await checkPrimaryForm(page, 'first results');
    const toolbarLayout = await checkToolbarLayout(page, width, previous);
    if (width === 760) {
      await page.setViewportSize({ width: 759, height: 1000 });
      toolbarLayout.belowTablet = await checkToolbarLayout(page, 759, previous);
      await page.setViewportSize({ width, height: 1000 });
    }
    if (!previous && [375, 390, 720, 1024, 1200, 1440].includes(width)) {
      await snapshot(page);
      await page.screenshot({ path: path.join(output, `primary-with-results-${width}.png`), fullPage: true });
      if (width === 1440) {
        await page.setViewportSize({ width, height: 700 });
        await checkPrimaryForm(page, 'short desktop results');
        await snapshot(page);
        await page.screenshot({ path: path.join(output, 'primary-with-results-1440-short.png') });
        await page.setViewportSize({ width, height: 1000 });
      }
    }
    await page.evaluate(items => {
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items } }));
    }, calendarHotels);
    const calendar = page.locator('#currentPriceCalendar');
    await checkPrimaryForm(page, 'terminal results with calendar');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]), primaryParameters, 'render and completion preserve every primary/advanced value');
    assert.equal(await calendar.isVisible(), true, 'current price calendar is visible after a terminal result set');
    const calendarDisclosure = calendar.locator('details');
    assert.equal(await calendarDisclosure.evaluate(node => node.open), true, 'Search3 calendar starts expanded on first render at every responsive width');
    assert.deepEqual(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.map(node => [node.dataset.calendarDate, node.querySelector('strong').textContent.replace(/\s/g, '')])), [
      ['2026-09-10', '99000₽'], ['2026-09-12', '148500₽']
    ], 'calendar exposes per-day minima and ignores unpriced tours');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-10', 'lowest observed day is highlighted');
    assert.equal(await calendar.locator('[data-calendar-date]').evaluateAll(nodes => nodes.every(node => node.getBoundingClientRect().height >= 44)), true, 'calendar dates retain accessible touch targets');
    await page.evaluate(items => {
      window.V2Results.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-continued', { detail: { items } }));
    }, calendarHotels.concat([
      { id: 'calendar-c', tours: [{ ...tour, id: 'calendar-c1', date: '2026-09-14', price: 88000 }] }
    ]));
    assert.equal(await calendar.locator('[data-calendar-date]').count(), 3, 'continued results refresh the calendar instead of leaving stale dates');
    assert.equal(await calendar.locator('.is-best').getAttribute('data-calendar-date'), '2026-09-14', 'continued results refresh the highlighted minimum');
    const preservedBeforeCalendar = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()].filter(([name]) => !['dateFrom', 'dateTo'].includes(name)));
    await page.evaluate(() => {
      window.__calendarSubmits = 0;
      window.V2SearchLifecycle.submit = () => {
        window.__calendarSubmits += 1;
        window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { dirty: false } }));
      };
    });
    if (!(await calendarDisclosure.evaluate(node => node.open))) await calendar.locator('summary').click();
    await calendar.locator('[data-calendar-date="2026-09-14"]').focus();
    await calendar.locator('[data-calendar-date="2026-09-14"]').press('Enter');
    assert.equal(await page.evaluate(() => window.__calendarSubmits), 1, 'calendar date submits through the canonical lifecycle exactly once');
    assert.equal(await calendar.isVisible(), false, 'calendar clears when the replacement search starts');
    assert.equal(await page.locator('#resultsSearchEdit').evaluate(node => node === document.activeElement), true, 'keyboard calendar selection restores focus before removing its date button');
    await page.evaluate(() => {
      const edit = document.getElementById('resultsSearchEdit');
      edit.replaceWith(edit.cloneNode(true));
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items: [{ tours: [{ date: '2026-09-14', price: 88000 }] }] } }));
    });
    assert.equal(await page.locator('#resultsSearchEdit').evaluate(node => node === document.activeElement), true, 'terminal completion restores focus after the asynchronous results toolbar is replaced');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [form.elements.dateFrom.value, form.elements.dateTo.value]), ['2026-09-14', '2026-09-14'], 'calendar applies the exact selected day');
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()].filter(([name]) => !['dateFrom', 'dateTo'].includes(name))), preservedBeforeCalendar, 'calendar preserves every non-date search parameter');
    const parameters = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]);
    assert.equal(await page.locator('#resultsTools #resultsSearchEdit').count(), 1, 'native results tools retain one search edit action');
    await page.locator('#resultsSearchEdit').focus();
    await page.locator('#resultsSearchEdit').press('Enter');
    assert.equal(await page.locator('[name=from]').evaluate(node => node === document.activeElement), true, 'keyboard edit action focuses the permanently available primary form');
    await checkPrimaryForm(page, 'keyboard edit', true);
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]), parameters, 'editing preserves all current search parameters');
    if (!previous && [375, 390, 720, 1200, 1440].includes(width)) {
      await snapshot(page);
      await page.screenshot({ path: path.join(output, `primary-editor-${width}.png`), fullPage: true });
    }
    await page.evaluate(items => window.V2Results.render(items), hotels);
    await checkPrimaryForm(page, 'results rerender after edit', true);
    assert.equal(await page.locator('#results .hotel-card').first().getAttribute('data-hotel-id'), 'cheap', 'price sorting retained');
    const localHotelFilter = page.locator('.search3-hotel-filter');
    const localHotelInput = localHotelFilter.locator('input');
    const localCategoryFilter = page.locator('.search3-category-filter');
    const localCategorySelect = localCategoryFilter.locator('select');
    const localCategoryPresets = localCategoryFilter.locator('.search3-filter-presets');
    const localBudgetFilter = page.locator('.search3-budget-filter');
    const localBudgetInput = localBudgetFilter.locator('.search3-budget-max');
    const localOperatorFilter = page.locator('.search3-operator-filter');
    const localOperatorSelect = localOperatorFilter.locator('select');
    const localRatingFilter = page.locator('.search3-rating-filter');
    const localRatingSelect = localRatingFilter.locator('select');
    const localRatingCoverage = localRatingFilter.locator('[data-search3-rating-coverage]');
    const localSeaFilter = page.locator('.search3-sea-filter');
    const localSeaSelect = localSeaFilter.locator('select');
    const localReset = page.locator('.search3-filter-reset');
    const mobilePanel = page.locator('.search3-mobile-filter-panel');
    const mobileSummary = mobilePanel.locator('summary');
    const mobileSummaryText = mobilePanel.locator('[data-search3-mobile-filter-summary]');
    if (width < 1025) {
      assert.equal(await mobilePanel.isVisible(), true, 'tablet and mobile expose one compact current filter disclosure');
      assert.equal(await mobilePanel.getAttribute('open'), null, 'mobile disclosure starts compact');
      await mobileSummary.focus();
      await page.keyboard.press('Enter');
      assert.notEqual(await mobilePanel.getAttribute('open'), null, 'native summary opens current filters from the keyboard');
    } else assert.equal(await mobilePanel.isVisible(), false, 'desktop does not expose the mobile disclosure');
    assert.equal(await localHotelFilter.isVisible(), true, 'one local hotel filter appears for multiple loaded hotels');
    assert.equal(await localBudgetFilter.isVisible(), true, 'complete loaded offer prices expose a budget facet');
    assert.equal(await localOperatorFilter.isVisible(), true, 'complete loaded offer operators expose one local operator facet');
    assert.deepEqual(await localOperatorSelect.locator('option').allTextContents(), ['Все туроператоры', 'OTHER OPERATOR', 'TEST OPERATOR'], 'operator choices keep the original labels and do not confuse operator with provider');
    assert.equal(await localCategoryFilter.isVisible(), true, 'category facet appears when every loaded hotel has a category');
    assert.deepEqual(await localCategoryPresets.locator('button').allTextContents(), ['5★', '4★'], 'complete category values expose quick exact choices without inventing a threshold');
    assert.ok((await localCategoryPresets.locator('button').first().boundingBox()).height >= 44, 'category quick choice keeps a full touch target');
    assert.equal(await localRatingFilter.isVisible(), true, 'rating facet appears when every loaded hotel has a rating');
    assert.equal(await localRatingCoverage.innerText(), 'Рейтинг указан у 2 из 2 отелей', 'rating facet discloses exact loaded-data coverage');
    assert.equal(await localSeaFilter.isVisible(), true, 'sea facet appears when every loaded hotel has a distance');
    const rail = page.locator('.results-filter-rail'), actions = page.locator('#resultsTools .results-tools__actions');
    if (width >= 1025) {
      assert.equal(await rail.isVisible(), true, 'desktop exposes one canonical left filter rail');
      assert.equal(await localHotelFilter.evaluate(node => node.parentElement.className), 'results-filter-rail', 'desktop moves current filter owners into the rail');
      const railBox = await rail.boundingBox(), resultsBox = await page.locator('#results').boundingBox();
      assert.ok(railBox.width >= 220 && resultsBox.x >= railBox.x + railBox.width - 1, 'desktop rail and cards use separate readable columns');
    } else {
      assert.equal(await rail.isVisible(), false, 'tablet and mobile do not reserve an empty rail');
      assert.equal(await localHotelFilter.evaluate(node => node.parentElement.className), 'search3-mobile-filter-panel__body', 'tablet and mobile reuse the current controls inside one disclosure');
      assert.equal(await actions.isVisible(), true);
    }
    await localOperatorSelect.selectOption('name:test operator');
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'operator facet narrows already loaded offers without using the provider label');
    await localBudgetInput.evaluate(node => { node.value = '100000'; node.dispatchEvent(new Event('change', { bubbles: true })); });
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), [], 'operator and budget must match the same exact loaded offer');
    if (!previous && [320, 375, 720, 1025, 1440].includes(width)) {
      await page.screenshot({ path: path.join(output, `exact-budget-${width}.png`), fullPage: true });
    }
    if (width < 1025) {
      assert.match(await mobileSummaryText.innerText(), /Подходит: 0 · до 100[\s\u00a0]*000 ₽ · TEST OPERATOR/, 'compact summary names active exact-offer filters instead of exposing only their count');
      assert.match(await mobileSummaryText.getAttribute('aria-label'), /активные фильтры: до 100[\s\u00a0]*000 ₽; TEST OPERATOR/, 'compact summary exposes the full active-filter meaning accessibly');
      await mobileSummary.click();
      assert.equal(await mobilePanel.getAttribute('open'), null, 'active values remain visible while the native filter disclosure is collapsed');
      assert.equal((await snapshot(page)).overflow, false, 'active filter values safely fit the compact toolbar');
      await mobileSummary.click();
    }
    await localBudgetInput.evaluate(node => { node.value = node.max; node.dispatchEvent(new Event('change', { bubbles: true })); });
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'restoring budget keeps the active operator projection');
    await localOperatorSelect.selectOption('');
    await localBudgetInput.evaluate(node => { node.value = '100000'; node.dispatchEvent(new Event('change', { bubbles: true })); });
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['cheap'], 'clearing operator restores the exact qualifying budget offer');
    assert.equal(await page.locator('#results [data-hotel-id=cheap] .hotel-price').innerText().then(text => text.replace(/\s/g, '')), '90000₽', 'budget retains the exact qualifying offer price');
    await localBudgetInput.evaluate(node => { node.value = node.max; node.dispatchEvent(new Event('change', { bubbles: true })); });
    await localRatingSelect.selectOption('4.5');
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'rating threshold filters the loaded hotels locally');
    await localRatingSelect.selectOption('0');
    await localSeaSelect.selectOption('200');
    assert.deepEqual(await page.locator('#results .hotel-card:visible').evaluateAll(nodes => nodes.map(node => node.dataset.hotelId)), ['expensive'], 'sea threshold filters only complete loaded distance data');
    await localSeaSelect.selectOption('0');
    const categoryFive = localCategoryPresets.locator('button[data-value="5"]');
    await categoryFive.click();
    assert.equal(await categoryFive.getAttribute('aria-pressed'), 'true', 'category quick choice exposes its selected state');
    assert.equal(await categoryFive.evaluate(node => node === document.activeElement), true, 'category quick choice keeps focus after filtering');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 1, 'category facet filters only the already loaded hotels');
    assert.equal(await localReset.isVisible(), true, 'an active local facet exposes one reset action at every responsive width');
    assert.equal(await localReset.evaluate(node => node.parentElement.className), width >= 1025 ? 'results-filter-rail' : 'search3-mobile-filter-panel__body', 'reset action follows the current responsive filter owner');
    if (width < 1025) assert.match(await mobileSummaryText.innerText(), /Подходит: 1 · 5★/, 'compact summary exposes the current result and active filter value');
    assert.match(await localHotelFilter.locator('small').innerText(), /Показано 1 из 2 загруженных отелей/, 'category facet reports a truthful loaded-card count');
    await localHotelInput.fill('  ВТОРОЙ  ');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 0, 'hotel name and category filters combine locally');
    assert.match(await localHotelFilter.locator('small').innerText(), /Показано 0 из 2 загруженных отелей/, 'combined filters report their truthful loaded-card count');
    const localEmpty = page.locator('#results .search3-local-empty');
    const localEmptyReset = localEmpty.locator('.search3-local-empty-reset');
    assert.equal(await localEmpty.count(), 1, 'zero matching local filters expose one actionable empty state');
    assert.equal(await localEmpty.isVisible(), true, 'local empty state is visible above the hidden loaded cards');
    await checkPrimaryForm(page, 'zero local matches', true);
    assert.match(await localEmpty.innerText(), /По выбранным фильтрам ничего не подошло[\s\S]*Сбросить фильтры/, 'local empty state explains the recoverable filter result');
    assert.ok((await localEmptyReset.boundingBox()).height >= 44, 'local empty reset keeps a full touch target');
    if (!previous && [375, 720, 1440].includes(width)) await page.screenshot({ path: path.join(output, `local-empty-${width}.png`), fullPage: true });
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await localHotelInput.inputValue(), '  ВТОРОЙ  ', 'sorting preserves the local hotel query');
    assert.equal(await localCategorySelect.inputValue(), '5', 'sorting preserves the local category');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 0, 'sorting reapplies both local filters to rerendered cards');
    assert.equal(await localEmpty.count(), 1, 'sorting keeps exactly one local empty state');
    if (width < 1025) assert.match(await mobileSummaryText.innerText(), /Подходит: 0 · 5★ · Отель: ВТОРОЙ/, 'sorting preserves the visible active-filter summary');
    await localEmptyReset.focus();
    await localEmptyReset.press('Enter');
    await page.waitForFunction(() => document.activeElement?.matches('#results .hotel-card:not([hidden]) .hotel-title'));
    assert.equal(await localCategorySelect.inputValue(), '0', 'one reset clears the active category');
    assert.equal(await localHotelInput.inputValue(), '', 'one reset clears the hotel query');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'one reset restores every loaded card');
    assert.equal(await localEmpty.count(), 0, 'one reset removes the local empty state');
    assert.equal(await localReset.isVisible(), false, 'reset action hides when no local filter remains active');
    assert.equal(await categoryFive.getAttribute('aria-pressed'), 'false', 'common reset clears the mirrored quick choice state');
    assert.equal(await page.locator('#results .hotel-card:visible .hotel-title').first().evaluate(node => node === document.activeElement), true, 'zero-match reset moves keyboard focus to the first restored hotel');
    if (width < 1025) {
      assert.equal(await mobileSummaryText.innerText(), 'Подходит: 2', 'reset removes stale active values from the compact summary');
      assert.equal(await mobileSummaryText.getAttribute('aria-label'), 'Подходит: 2; активных фильтров нет', 'reset exposes an accurate accessible empty-filter state');
    }
    await categoryFive.focus();
    await categoryFive.press('Enter');
    await localReset.focus();
    await localReset.press('Enter');
    await page.waitForFunction(() => document.activeElement?.matches('.search3-hotel-filter input'));
    assert.equal(await localCategorySelect.inputValue(), '0', 'common keyboard reset clears the active category');
    assert.equal(await localHotelInput.evaluate(node => node === document.activeElement), true, 'common keyboard reset returns focus to the first visible filter');
    await localOperatorSelect.selectOption('name:test operator');
    await page.evaluate(items => window.V2Results.render(items), [hotels[0], { ...hotels[1], tours: [{ ...hotels[1].tours[0], operator: null }] }]);
    assert.equal(await localOperatorFilter.isVisible(), false, 'operator facet hides when any loaded offer lacks its operator label');
    assert.equal(await localOperatorSelect.inputValue(), '', 'incomplete operator data resets the local choice');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'an incomplete operator facet never silently removes a loaded hotel');
    if (width < 1025) assert.doesNotMatch(await mobileSummaryText.innerText(), /TEST OPERATOR/, 'incomplete operator data also removes its stale compact summary value');
    await page.evaluate(items => window.V2Results.render(items), [hotels[0], { ...hotels[1], category: 0 }]);
    assert.equal(await localCategoryFilter.isVisible(), false, 'category facet hides when any loaded hotel lacks category data');
    assert.equal(await localCategoryPresets.isVisible(), false, 'incomplete category data also hides its quick choices');
    assert.equal(await localCategorySelect.inputValue(), '0', 'incomplete category data resets the local choice');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 2, 'an incomplete facet never silently removes a loaded hotel');
    await page.evaluate(items => {
      const base=items[0];
      window.V2Results.render(Array.from({length:20},(_,index)=>({...base,id:'rating-'+index,name:'Отель рейтинга '+index,rating:index===19?0:index%2?4.6:3.8,seaDistance:0,tours:base.tours.map(tour=>({...tour,id:tour.id+'-'+index}))})));
    }, hotels);
    assert.equal(await localRatingFilter.isVisible(), true, '95% known ratings keep the useful local facet available');
    assert.equal(await localRatingCoverage.innerText(), 'Рейтинг указан у 19 из 20 отелей', 'partial high coverage is disclosed instead of presented as complete');
    await localRatingSelect.selectOption('4.5');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 9, 'rating threshold excludes unknown and below-threshold cards locally');
    if (!previous && [375,720,1440].includes(width)) await page.screenshot({ path: path.join(output, `rating-coverage-${width}.png`), fullPage: true });
    await page.evaluate(items => {
      const base=items[0];
      window.V2Results.render(Array.from({length:20},(_,index)=>({...base,id:'rating-low-'+index,name:'Отель рейтинга '+index,rating:index>=18?0:index%2?4.6:3.8,seaDistance:0,tours:base.tours.map(tour=>({...tour,id:tour.id+'-low-'+index}))})));
    }, hotels);
    assert.equal(await localRatingFilter.isVisible(), false, 'rating facet hides below the explicit 95% coverage policy');
    assert.equal(await localRatingSelect.inputValue(), '0', 'coverage loss resets the active rating threshold');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 20, 'coverage loss cannot silently retain a filter or hide unknown cards');
    await page.evaluate(items => {
      const base=items[0];
      window.V2Results.render(Array.from({length:20},(_,index)=>({...base,id:'sea-'+index,name:'Отель у моря '+index,rating:4.7,seaDistance:index<16?(index%2?150:700):0,tours:base.tours.map(tour=>({...tour,id:tour.id+'-sea-'+index}))})));
    }, hotels);
    assert.equal(await localSeaFilter.isVisible(), true, '80% known sea distances keep the useful local facet available');
    assert.equal(await localSeaSelect.locator('option').first().innerText(), 'Любое расстояние · 16/20', 'sea facet discloses exact loaded-data coverage');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 20, 'unknown sea distances stay visible before a threshold is selected');
    await localSeaSelect.selectOption('500');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 8, 'sea threshold excludes unknown and above-threshold cards locally');
    if (!previous && [375,720,1440].includes(width)) await page.screenshot({ path: path.join(output, `sea-coverage-${width}.png`), fullPage: true });
    await page.evaluate(items => {
      const base=items[0];
      window.V2Results.render(Array.from({length:20},(_,index)=>({...base,id:'sea-low-'+index,name:'Отель у моря '+index,rating:4.7,seaDistance:index<15?150:0,tours:base.tours.map(tour=>({...tour,id:tour.id+'-sea-low-'+index}))})));
    }, hotels);
    assert.equal(await localSeaFilter.isVisible(), false, 'sea facet hides below the explicit 80% coverage policy');
    assert.equal(await localSeaSelect.inputValue(), '0', 'coverage loss resets the active sea threshold');
    assert.equal(await page.locator('#results .hotel-card:visible').count(), 20, 'sea coverage loss cannot silently retain a filter or hide unknown cards');
    await page.evaluate(items => {
      const freeze = value => { if (value && typeof value === 'object') { Object.values(value).forEach(freeze); Object.freeze(value); } return value; };
      window.__decisionOriginal = freeze(items);
      window.V2Results.render(window.__decisionOriginal);
    }, hotels);
    await page.locator('#sortResults').selectOption('price');
    const card = page.locator('#results [data-hotel-id=expensive].hotel-card');
    assert.equal(await card.locator('.hotel-title').evaluate(node => node.tagName), 'H3', 'hotel name keeps a semantic card heading');
    assert.equal(await card.locator('.hotel-best-offer').count(), 0, 'card does not repeat the representative tour price');
    assert.equal(await card.locator('.hotel-price').count(), 1, 'collapsed card exposes one authoritative total');
    assert.match(await card.locator('.hotel-decision-rating').innerText(), /Рейтинг 5/, 'hotel score is not confused with star category');
    assert.match(await page.locator('#resultSummary').innerText(), /цены из текущего поиска/, 'result summary does not promise universal final-price readiness');
    assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle').count(), 0, 'collapsed multi-offer hotel has no concrete row, Select, or Compare');
    assert.equal(await card.locator('.hotel-offers-summary').count(), 1, 'collapsed multi-offer hotel has one hotel-level summary owner');
    assert.ok((await card.locator('.tour-more-toggle').boundingBox()).height >= 44, 'real disclosure action retains a full touch target');
    assert.equal(await card.locator('.tour-more-toggle').innerText(), 'Показать варианты · 3', 'disclosure states the total loaded offer count');
    assert.equal(await card.locator('.tour-meta').count(), 0, 'exact offer owner stays absent before disclosure');
    assert.match(await card.locator('.hotel-trip-summary').innerText(), /12\.09\.2026/,'collapsed conditions belong to the offer matching the shown minimum');
    assert.match(await card.locator('.hotel-trip-summary').innerText(), /Ночей\s+9/);
    assert.match(await card.locator('.hotel-trip-summary').innerText(), /Всё включено/);
    assert.doesNotMatch(await card.locator('.hotel-tours').innerText(), /STANDARD LAND VIEW|TEST OPERATOR|Tourvisor|DBL|Двухместное/, 'collapsed minimum facts exclude unrelated room, placement and operator details');
    assert.equal(await card.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), 'от148500,6₽', 'collapsed hotel exposes only its precise minimum with a truthful prefix');
    const single = page.locator('#results [data-hotel-id=cheap].hotel-card');
    assert.equal(await single.locator('.hotel-trip-summary,.tour-more-toggle').count(), 0, 'single-offer hotel needs no redundant aggregate or disclosure');
    assert.equal(await single.locator('.direct-tour').getAttribute('data-tid'), 'cheap-tour', 'single-offer hotel retains immediate exact selection');
    assert.ok((await single.locator('.direct-tour').boundingBox()).height >= 44, 'single-offer selection retains a full touch target');
    assert.equal(await single.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), '90000₽', 'single offer exposes its exact price without a minimum prefix');
    const { photo, body } = await card.evaluate(node => {
      const rect = element => {
        const box = element.getBoundingClientRect();
        return { x: box.x, y: box.y, width: box.width, height: box.height };
      };
      return { photo: rect(node.querySelector('.hotel-photo')), body: rect(node.querySelector('.hotel-body')) };
    });
    assert.ok(photo.height >= 150, 'hotel photo remains legible at the current width');
    if (width >= 1200) assert.ok(photo.height >= 200, 'wide desktop gives the actual hotel photo a useful area');
    if (width <= 760) assert.ok(body.y >= photo.y + photo.height - 1, 'mobile hotel content follows the photo without overlap: '+JSON.stringify({width,previous,photo,body}));
    else assert.ok(body.x >= photo.x + photo.width - 1, 'desktop hotel content sits beside the photo without overlap');
    const collapsed = await snapshot(page);
    if (collapsed.overflow) console.error(JSON.stringify({width,previous,offenders:collapsed.offenders}));
    assert.equal(collapsed.overflow, false, width + ': results fit viewport');
    if (!previous && [375, 720, 1200, 1440].includes(width)) await page.screenshot({ path: path.join(output, `results-collapsed-${width}.png`), fullPage: true });
    await card.locator('.tour-more-toggle').focus();
    await card.locator('.tour-more-toggle').press('Enter');
    assert.equal(await card.locator('.tour-row').count(), 3, 'actual toggle reveals all tours');
    assert.equal(await card.locator('.hotel-trip-summary').count(), 0, 'expanded exact offers replace the aggregate without duplication');
    assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['current-tour', 'other-tour', 'third-tour'], 'expansion pins the nonfirst representative and preserves the order of all remaining offers');
    const primary = card.locator('.tour-row').first();
    assert.ok((await primary.locator('.direct-tour').boundingBox()).height >= 44, 'expanded real selection action retains a full touch target');
    assert.equal(await primary.locator('.direct-tour').getAttribute('data-tid'), tour.id, 'selection identity retained');
    assert.equal(await primary.locator('.direct-tour').innerText(), 'Выбрать тур', 'selection action identifies its target');
    assert.match(await primary.locator('.tour-facts').innerText(), /Всё включено/, 'supplier fullName expands the abbreviation in offer facts');
    assert.equal(await primary.locator('.tour-meta>small').innerText(), 'Дата вылета · 9 ноч.', 'departure context states the duration beside the date');
    assert.equal(await primary.locator('.tour-meta>strong').innerText(), '12.09.2026', 'compact facts format the actual departure date for display');
    assert.deepEqual(await primary.locator('.tour-facts .tour-fact').evaluateAll(nodes => nodes.map(node => [node.querySelector('small').textContent, node.querySelector('b').textContent])), [['Питание', 'Всё включено'], ['Номер', 'STANDARD LAND VIEW · Двухместное']], 'primary comparison facts preserve exact supplier meal/room facts and the reviewed placement display');
    assert.deepEqual(await primary.locator('.tour-secondary-facts .tour-fact:not(.tour-operator)').evaluateAll(nodes => nodes.map(node => [node.querySelector('small').textContent, node.querySelector('b').textContent])), [], 'provider provenance stays out of customer-facing offer facts');
    assert.equal(await primary.locator('.hotel-operator').innerText(), 'TEST OPERATOR', 'unknown operator keeps its visible name');
    assert.equal(await primary.locator('.hotel-operator').getAttribute('title'), 'Туроператор: TEST OPERATOR', 'tooltip explains the operator identity');
    assert.equal(await primary.locator('.tour-operator>small').count(), 0, 'redundant operator caption is removed');
    assert.equal(await primary.locator('.hotel-price').innerText().then(text => text.replace(/\s/g, '')), '148500,6₽', 'expanded representative keeps the precise original price');
    assert.equal(await card.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'keyboard expansion retains focus on the replacement disclosure');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'true');
    const expanded = await snapshot(page);
    assert.equal(expanded.overflow, false, width + ': expanded results fit viewport');
    if (!previous && [375, 720, 1200, 1440].includes(width)) await page.screenshot({ path: path.join(output, `results-expanded-${width}.png`), fullPage: true });
    assert.equal(await card.locator('.tour-meta>strong').first().evaluate(node => getComputedStyle(node, '::before').content), 'none', 'result dates have no duplicate generated label');
    assert.match(await card.innerText(), /148[\s\u00a0]*500,6/, 'decimal price remains visible');
    await card.locator('.tour-more-toggle').press('Space');
    assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle').count(), 0, 'actual toggle restores the hotel-level collapsed choice');
    assert.equal(await card.locator('.tour-more-toggle').evaluate(node => node === document.activeElement), true, 'keyboard collapse retains focus on the replacement disclosure');
    assert.equal(await card.locator('.tour-more-toggle').getAttribute('aria-expanded'), 'false');
    assert.equal(await card.locator('.hotel-offers-summary').count(), 1, 'collapse restores exactly one hotel-level summary');
    assert.match(await card.locator('.hotel-trip-summary').innerText(), /12\.09\.2026/,'keyboard collapse restores conditions of the same minimum offer');
    assert.match(await card.locator('.hotel-trip-summary').innerText(), /Ночей\s+9/);
    assert.match(await card.locator('.hotel-trip-summary').innerText(), /Всё включено/);
    assert.doesNotMatch(await card.locator('.hotel-tours').innerText(), /STANDARD LAND VIEW|TEST OPERATOR|Tourvisor|DBL|Двухместное/, 'collapse never adds room, placement or operator from an arbitrary offer');
    assert.equal(await page.evaluate(() => window.V2Results.state.items.every((hotel, i) => hotel === window.__decisionOriginal[i]) && window.V2Results.representativeTour(window.__decisionOriginal[0]) === window.__decisionOriginal[0].tours[1]), true, 'render and disclosure preserve original hotel and representative tour objects');
    assert.equal(await page.evaluate(() => JSON.stringify(window.V2Results.state.items)), JSON.stringify(hotels), 'disclosure leaves frozen source prices, tour order and contents unchanged');
    await page.locator('#sortResults').selectOption('rating');
    assert.equal(await page.locator('#results .hotel-card').first().getAttribute('data-hotel-id'), 'expensive', 'rating sorting retained');
    await page.evaluate(items => window.V2Results.render([{ ...items[0], id: 'no-photo', name: 'Отель без фотографии', picturelink: '' }]), hotels);
    const noPhoto = page.locator('#results [data-hotel-id=no-photo].hotel-card');
    const noPhotoGeometry = await noPhoto.evaluate(node => { const box = element => { const r = element.getBoundingClientRect(); return { x:r.x, y:r.y, width:r.width, height:r.height }; }; return { main:box(node.querySelector('.hotel-main')), media:box(node.querySelector('.hotel-photo')), body:box(node.querySelector('.hotel-body')), placeholder:getComputedStyle(node.querySelector('.photo-placeholder')).display, stars:getComputedStyle(node.querySelector('.stars-badge')).display }; });
    assert.equal(noPhotoGeometry.placeholder, 'none', 'missing photo never reserves a branded fake image');
    assert.notEqual(noPhotoGeometry.stars, 'none', 'hotel category stays visible when photo is absent');
    assert.ok(noPhotoGeometry.media.height < 64, 'missing-photo header stays compact instead of reserving media height: '+JSON.stringify({width,previous,noPhotoGeometry}));
    assert.ok(noPhotoGeometry.body.width >= noPhotoGeometry.main.width - 2, 'missing-photo facts use the card content width');
    assert.equal((await snapshot(page)).overflow, false, width + ': missing-photo card fits the viewport');
    if (!previous && [375,720,1024,1440].includes(width)) await page.screenshot({ path: path.join(output, `results-no-photo-${width}.png`), fullPage: true });
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 44 } })));
    await checkPrimaryForm(page, 'loading with retained results');
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'search start exposes a truthful loading state');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-progress', { detail: { progress: 47 } })));
    assert.equal(await page.locator('#status .results-state-progress span').evaluate(node => node.style.width), '47%', 'progress state reflects the reported percentage');
    assert.equal(await page.locator('#status .results-state-progress').getAttribute('aria-valuenow'), '47', 'progress exposes its value to assistive technology');
    assert.doesNotMatch(await page.locator('#status').innerText(), /Уже найдено отелей: 2/, 'new search does not inherit the previous result count');
    await page.evaluate(() => window.V2Results.render([], { empty: false }));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'intermediate empty response keeps the active loading state');
    assert.equal(await page.locator('#results .empty-actionable').count(), 0, 'intermediate empty response is not presented as final');
    await page.evaluate(items => window.V2Results.render(items), hotels);
    const errorParameters = await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]);
    await page.evaluate(() => {
      window.__resultsRetrySubmits = 0;
      window.V2SearchLifecycle.submit = () => {
        window.__resultsRetrySubmits += 1;
        if (window.__replaceResultsOnRecovery) document.getElementById('results').innerHTML = '<div class="skeleton-grid"><div class="skeleton-card"></div></div>';
        window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { generation: 45 } }));
        window.__releaseRetryStart = () => window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId: 45 } }));
      };
      window.dispatchEvent(new CustomEvent('v2:search-error', { detail: { phase: 'status' } }));
    });
    assert.equal(await page.locator('#status .results-state--error').isVisible(), true, 'search failure exposes a distinct error state');
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'search failure leaves parameters editable even with retained results');
    await checkPrimaryForm(page, 'search error', true);
    assert.deepEqual(await page.locator('#tourSearch').evaluate(form => [...new FormData(form).entries()]), errorParameters, 'error recovery preserves every search parameter');
    if (!previous && [375, 720, 1440].includes(width)) await page.screenshot({ path: path.join(output, `search-error-edit-${width}.png`), fullPage: true });
    const retrySearch = page.locator('#status .results-state-retry');
    await retrySearch.focus();
    await retrySearch.press('Enter');
    assert.equal(await page.evaluate(() => window.__resultsRetrySubmits), 1, 'retry reuses the canonical search lifecycle');
    assert.equal(await page.locator('#tourSearch').evaluate(node => node === document.activeElement), true, 'retry reset keeps focus on a stable recovery target');
    await page.evaluate(() => window.__releaseRetryStart());
    await page.waitForFunction(() => document.activeElement === document.getElementById('status'));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'retry moves focus to the visible loading status');
    assert.equal(await page.locator('#results .hotel-card').count(), 2, 'error state preserves already rendered cards');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-continue-error', { detail: {} })));
    assert.match(await page.locator('#status').innerText(), /Уже найденные отели сохранены/, 'continue failure truthfully preserves prior results');
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })));
    assert.match(await page.locator('#status').innerText(), /Параметры поиска изменены/, 'dirty reset retains its actionable explanation');
    assert.equal(await localHotelInput.inputValue(), '', 'search reset clears the local hotel query');
    assert.equal(await localHotelFilter.isVisible(), false, 'search reset hides the stale local hotel filter');
    assert.equal(await localCategorySelect.inputValue(), '0', 'search reset clears the local category');
    assert.equal(await localCategoryFilter.isVisible(), false, 'search reset hides the stale local category facet');
    assert.equal(await localOperatorSelect.inputValue(), '', 'search reset clears the local operator');
    assert.equal(await localOperatorFilter.isVisible(), false, 'search reset hides the stale local operator facet');
    assert.equal(await page.locator('.search3-provider-filter').count(),0,'reset cannot recreate the removed provider facet');
    assert.equal(await page.locator('.search3-region-filter').evaluate(node => node.hidden && getComputedStyle(node).display === 'none'), true, 'reset removes the stale region facet from layout even inside a grid');
    assert.equal(await calendar.isVisible(), false, 'search reset hides stale calendar data');
    assert.equal(await calendar.locator('[data-calendar-date]').count(), 0, 'search reset clears stale calendar dates');
    await page.evaluate(() => {
      window.__replaceResultsOnRecovery = true;
      document.getElementById('hotelServices').innerHTML = '<label><input type="checkbox" name="hotel_service[]" value="1" checked>Бассейн</label><label><input type="checkbox" name="hotel_service[]" value="2" checked>Пляж</label>';
      window.V2Catalogs.updateServiceCount();
      window.V2Results.render([]);
    });
    assert.equal(await page.locator('#serviceCount').textContent(), '2 выбрано', 'empty recovery starts from the actual selected-service count');
    const serviceRelax = page.locator('.empty-relax[data-relax="hotel_service[]"]');
    await serviceRelax.focus();
    await serviceRelax.press('Enter');
    assert.equal(await page.evaluate(() => window.__resultsRetrySubmits), 2, 'keyboard service relaxation submits exactly once');
    assert.equal(await page.locator('input[name="hotel_service[]"]:checked').count(), 0, 'service relaxation clears every selected service');
    assert.equal(await page.locator('#serviceCount').textContent(), 'не выбраны', 'service relaxation synchronizes the count stored for the advanced-filter summary');
    await page.waitForFunction(() => document.activeElement === document.getElementById('tourSearch'));
    assert.equal(await page.locator('#tourSearch').evaluate(node => node === document.activeElement), true, 'service relaxation keeps focus on a stable recovery target through reset');
    await page.evaluate(() => window.__releaseRetryStart());
    await page.waitForFunction(() => document.activeElement === document.getElementById('status'));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'service relaxation moves focus to the visible loading status');
    await page.evaluate(() => {
      const form = document.getElementById('tourSearch'), arrival = form.elements.arrival;
      form.elements.country.value = '';
      arrival.innerHTML = '<option value="77" selected>Тестовый аэропорт</option>';
      arrival.addEventListener('change', () => window.dispatchEvent(new CustomEvent('v2:search-reset', { detail: { dirty: true } })), { once: true });
      document.getElementById('hotelServices').innerHTML = '<label><input type="checkbox" name="hotel_service[]" value="1" checked>Бассейн</label>';
      window.V2Catalogs.updateServiceCount();
      window.V2Results.render([]);
    });
    const arrivalRelax = page.locator('.empty-relax[data-relax="arrival"]');
    await arrivalRelax.focus();
    await arrivalRelax.press('Enter');
    assert.equal(await page.evaluate(() => window.__resultsRetrySubmits), 3, 'keyboard dependent relaxation submits exactly once');
    assert.equal(await page.locator('input[name="hotel_service[]"]:checked').count(), 0, 'dependent arrival relaxation clears incompatible hotel services');
    assert.equal(await page.locator('#serviceCount').textContent(), 'не выбраны', 'dependent relaxation also synchronizes the advanced-filter summary count');
    await page.waitForFunction(() => document.activeElement === document.getElementById('tourSearch'));
    assert.equal(await page.locator('#tourSearch').evaluate(node => node === document.activeElement), true, 'dependent relaxation keeps focus on a stable recovery target through reset');
    await page.evaluate(() => window.__releaseRetryStart());
    await page.waitForFunction(() => document.activeElement === document.getElementById('status'));
    assert.equal(await page.locator('#status .results-state--loading').isVisible(), true, 'dependent relaxation also moves focus to the visible loading status');
    await page.evaluate(() => {
      window.V2Results.render([]);
      window.V2Results.showPlainStatus('Поиск завершён · предложения актуальны на сейчас');
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items: [] } }));
    });
    await page.waitForFunction(() => document.activeElement && document.activeElement.closest && document.activeElement.closest('.empty-actionable'));
    assert.equal(await page.locator('#status').isVisible(), false, 'actionable empty result owns the empty state without duplicate status copy');
    assert.equal(await page.locator('.empty-actionable').evaluate(node => node.contains(document.activeElement)), true, 'terminal empty recovery moves status focus to an available action');
    await page.evaluate(() => {
      const stable = document.createElement('button');
      stable.id = 'ordinary-terminal-focus';
      stable.type = 'button';
      stable.textContent = 'Проверочный независимый элемент';
      document.body.append(stable);
      stable.focus({ preventScroll: true });
      window.V2Results.render([]);
      window.V2Results.showPlainStatus('Поиск завершён · предложения актуальны на сейчас');
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { items: [] } }));
    });
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    assert.equal(await page.locator('#ordinary-terminal-focus').evaluate(node => node === document.activeElement), true, 'ordinary terminal empty result does not steal unrelated user focus');
    await page.locator('#ordinary-terminal-focus').evaluate(node => node.remove());
    assert.equal(await page.locator('#status').isVisible(), false, 'ordinary terminal empty result also keeps a single final state');
    await checkPrimaryForm(page, 'terminal empty results', true);
    const continuation = await page.locator('#v2SearchMore').evaluate(node => {
      const results = document.getElementById('results'), origin = results.getBoundingClientRect();
      // Compare local layout; keyboard recovery and screenshots may scroll the page.
      const box = element => { const r = element.getBoundingClientRect(); return {x:r.x-origin.x,y:r.y-origin.y,width:r.width,height:r.height,right:r.right-origin.x,bottom:r.bottom-origin.y}; };
      return {group:box(node),button:box(node.querySelector('button')),helper:box(node.querySelector('small')),results:box(results)};
    });
    assert.ok(continuation.button.height >= 44, 'the current continuation action retains a full touch target');
    assert.ok(continuation.group.y >= continuation.results.bottom && Math.abs(continuation.group.x-continuation.results.x) < 1, 'continuation follows and aligns with the actual results column');
    assert.ok(continuation.button.right <= continuation.group.right+1 && continuation.helper.right <= continuation.group.right+1, 'continuation action and helper stay inside the results width');
    assert.ok(continuation.helper.y >= continuation.button.bottom || continuation.helper.x >= continuation.button.right, 'continuation helper has its own readable space');
    if (!previous) await page.locator('#v2SearchMore').screenshot({path:path.join(output, `continuation-${width}.png`),animations:'disabled'});
    await page.locator('.empty-edit-search').click();
    assert.equal(await page.locator('#tourSearch').isVisible(), true, 'empty results return to native search form');
    assert.equal(await page.locator('[name=from]').evaluate(node => node === document.activeElement), true, 'empty edit action focuses the existing departure control');
    let minimumReadiness = null, expandedDensity = null, offerComposition = null;
    if ([320, 375, 390, 720, 1200, 1363, 1440].includes(width)) {
      await checkMealFacet(page, width, previous);
      await checkBudgetRange(page, width, previous);
      await checkOfferFacets(page, width, previous);
      minimumReadiness = await checkMinimumReadiness(page, width, previous);
      await checkExactOfferParty(page, width, previous);
      expandedDensity = await checkExpandedDensity(page, width, previous);
      offerComposition = await checkAndromedaExpansion(page, width, previous, andromeda, hotelDetails);
      await require('./search3-hotel-operator-card-browser.cjs')(page, width, output);
      await checkHydratedHotelFacets(page, width, previous, hotelDetails);
    }
    assert.deepEqual(errors, [], 'no runtime errors');
    if (!previous) await page.screenshot({ path: path.join(output, `current-${width}.png`), fullPage: true });
    return { sourceSha, primaryForm: 'collapsed-with-results-editable-on-demand-and-error', toolbarLayout, minimumReadiness, expandedDensity, offerComposition, continuation, collapsed, expanded, logoSource };
  } finally { await page.close(); }
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    // 720 CSS px is the 200% reflow equivalent of the 1440px desktop viewport.
    for (const width of [320, 375, 390, 720, 760, 761, 999, 1000, 1024, 1025, 1200, 1363, 1440]) {
      const rawState = await run(browser, width, true), servedState = await run(browser, width, false);
      assert.deepEqual(servedState, rawState, width + ': served compact JS preserves actual result DOM and geometry');
      fs.writeFileSync(path.join(output, `current-${width}.json`), JSON.stringify(servedState, null, 2) + '\n');
    }
  } finally { await browser.close(); }
  console.log('SEARCH3_CURRENT_RESULTS_OK states=26 widths=320,375,390,720,760,761,999,1000,1024,1025,1200,1363,1440 reflow_200pct_equivalent=1440_to_720 raw_served_parity=1 native_header=1 external_calls=0 lead_sent=0');
})().catch(error => { console.error(error); process.exitCode = 1; });
