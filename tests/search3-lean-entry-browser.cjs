/* Actual-route startup and mobile form lifecycle; no supplier or lead requests. */
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const base = process.env.SEARCH3_VISUAL_BASE;
const baseline = process.env.SEARCH3_ENTRY_BASE || process.env.SEARCH3_RUNTIME_BASE;
assert.equal(new URL(base).hostname, '127.0.0.1');
assert.match(baseline, /^[0-9a-f]{40}$/);
const oldFile = name => execFileSync('git', ['show', `${baseline}:v2/${name}`]);
const oldAssets = new Map(Object.keys(require('../src/search3/manifest.json').assets).map(name => [name, oldFile(name)]));
const oldBundles = Object.fromEntries(['css', 'js'].map(type => {
  const names = JSON.parse(execFileSync('php', ['-r', 'eval("?>".stream_get_contents(STDIN));echo json_encode(v2_bundle_files($argv[1],"search3"));', type], { input: oldFile('bundle-manifest-v1.php') }));
  return [type, names.map(name => oldFile(name).toString()).join(type === 'js' ? '\n;\n' : '\n')];
}));
async function inspect(browser, width, previous) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  const errors = [];
  page.on('pageerror', error => errors.push(String(error)));
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== new URL(base).origin || request.method() !== 'GET') return route.abort();
    if (url.pathname.endsWith('/data/departures-v1.php')) return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({ok:true,items:[{id:1,russianName:'Москва'}]})});
    if (/\/(?:api[^/]*)\.php$/.test(url.pathname) && url.searchParams.get('action') === 'countries') return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify([{id:4,russianName:'Турция'}])});
    if (/\/(?:api[^/]*)\.php$/.test(url.pathname) && url.searchParams.get('action') === 'meals') return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify([{ id: 'BB', russianName: 'Завтраки' }, { id: 'AI', russianName: 'Всё включено' }]) });
    if (/\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
    const name = url.pathname.split('/').pop();
    const type = name === 'bundle-v1.php' ? url.searchParams.get('type') : name.endsWith('.css') ? 'css' : 'js';
    const old = oldAssets.get(name) || (name === 'bundle-v1.php' && oldBundles[type]);
    if (previous && old) return route.fulfill({ status: 200, contentType: type === 'css' ? 'text/css' : 'application/javascript', body: old });
    return route.continue();
  });
  const state = () => page.evaluate(previous => {
    // The retired sticky owner's 1px boundary marker is an intentional removal.
    // Strip only that marker from the reference before comparing the live form.
    // This also prevents its late insertion from occupying a mobile grid cell.
    if (previous) document.querySelectorAll('.mobile-search-submit-sentinel').forEach(node => node.remove());
    const form = document.getElementById('tourSearch'), r = form.getBoundingClientRect();
    return { visible: r.width > 0 && r.height > 0, width: Math.round(r.width), height: Math.round(r.height), fields: [...form.elements].filter(n => n.name).map(n => [n.name, n.value]), overflow: document.documentElement.scrollWidth > innerWidth + 2 };
  }, previous);
  const emit = (name, detail = {}) => page.evaluate(({ name, detail }) => window.dispatchEvent(new CustomEvent(name, { detail })), { name, detail });
  try {
    const response = await page.goto(base + '/poisk-turov/?food=AI', { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200);
    await page.waitForFunction(() => document.getElementById('tourSearch')?.dataset.search3Ready === '1');
    // Observe the same completed catalogs in both versions: screenshot duration
    // must not race aborted departure/country fallback or deferred meal loading.
    await page.waitForFunction(() => document.getElementById('tourSearch').dataset.catalogSource === 'anytour-departures');
    await page.locator('#tourSearch details.extras').evaluate(node => new Promise(resolve => { node.addEventListener('toggle', resolve, {once:true}); node.open = true; }));
    await page.locator('[name=food]').focus();
    await page.waitForFunction(() => { const meal=document.querySelector('[name=food]'); return meal.value === 'AI' && [...meal.options].some(option => option.value === 'BB'); });
    await page.locator('#tourSearch details.extras').evaluate(node => new Promise(resolve => { node.addEventListener('toggle', resolve, {once:true}); node.open = false; }));
    await page.waitForTimeout(100); // Drain native toggle event before lifecycle snapshots.
    const initial = await state();
    if (!previous) {
      const controls = await page.locator('#tourSearch .main-fields input,#tourSearch .main-fields select').evaluateAll(nodes => nodes.map(n => ({height:n.getBoundingClientRect().height,font:parseFloat(getComputedStyle(n).fontSize)})));
      assert.ok(controls.length >= 8 && controls.every(n => n.height >= 44 && n.font >= 16), 'native primary controls stay readable and touchable');
      if (width === 375 || width === 1440) await page.screenshot({ path: path.join(process.env.SEARCH3_GEOMETRY_OUTPUT, `entry-current-${width}.png`), fullPage: true });
    }
    // The fixed historical reference predates the current shared header/footer
    // markup, so its old CSS closure can overflow that newer shell. It remains a
    // lifecycle/value reference only; the current candidate must stay overflow-free.
    assert.ok(initial.visible && (previous || !initial.overflow), 'usable initial form');
    if (!previous) {
      assert.deepEqual(await page.evaluate(() => ({
        status: [document.getElementById('status').getAttribute('role'), document.getElementById('status').getAttribute('aria-live'), document.getElementById('status').getAttribute('aria-atomic')],
        resultsBusy: document.getElementById('results').getAttribute('aria-busy'),
        selectedTabindex: document.getElementById('selectedTour').getAttribute('tabindex')
      })), { status: ['status','polite','true'], resultsBusy: 'false', selectedTabindex: '-1' }, 'static accessibility salvage is present');
      await page.locator('#tourSearch details.extras').evaluate(node => { node.open=true; const nested=node.querySelector('details'); if(nested)nested.open=true; });
    }
    if (!previous) {
      assert.equal(await page.locator('.mobile-search-sticky,.mobile-search-summary,.mobile-search-submit-sentinel').count(), 0, 'retired mobile surfaces absent');
      assert.equal(await page.locator('#tourSearch.search-params-filter-split,.result-filter-stars,.result-filter-meal').count(), 0, 'retired parameter/filter split markers absent');
    }
    await emit('v2:search-started');
    const started = await state();
    if(!previous) {
      assert.equal(await page.locator('#tourSearch details.extras').evaluate(node => node.open),false,'search start closes native extras');
      assert.equal(await page.locator('#results').getAttribute('aria-busy'),'true','search start announces busy results');
    }
    await emit('v2:search-error', { phase: 'validation' });
    const validation = await state();
    assert.ok(validation.visible, 'validation restores the form');
    if(!previous) assert.equal(await page.locator('#results').getAttribute('aria-busy'),'false','search error clears busy results');
    await emit('v2:search-started');
    await emit('v2:search-dirty');
    const dirty = await state();
    assert.ok(dirty.visible, 'changed parameters restore the form');
    await emit('v2:search-started');
    await page.setViewportSize({ width: width <= 700 ? 701 : 700, height: 1000 });
    await page.waitForTimeout(400);
    const resized = await state();
    assert.ok(resized.visible && (previous || !resized.overflow), 'crossing the collapse breakpoint restores a usable form');
    if (!previous) {
      await page.locator('#tourSearch details.extras > summary').click();
      await page.locator('[name="food"]').focus();
      await page.waitForFunction(() => [...document.querySelector('[name="food"]').options].some(option => option.value === 'BB'));
      const meal = await page.locator('[name="food"]').evaluate(select => ({
        value: select.value,
        options: [...select.options].map(option => option.value),
        ariaHidden: select.getAttribute('aria-hidden'),
        tabIndex: select.tabIndex,
        quickChoices: select.closest('.field').querySelectorAll('.meal-quick').length
      }));
      assert.deepEqual(meal.options, ['', 'BB', 'AI'], 'current catalog owner supplies meal choices');
      assert.equal(meal.value, 'AI', 'food URL value is restored after async catalogue loading');
      assert.equal(meal.ariaHidden, null, 'native meal select remains accessible');
      assert.ok(meal.tabIndex >= 0, 'native meal select remains keyboard reachable');
      assert.equal(meal.quickChoices, 0, 'retired quick-choice surface is absent');
      if(width===375||width===1440) {
        await page.evaluate(() => window.V2Results.render([{id:'edit-fixture',name:'Проверочный отель',tours:[],price:148500}]));
        assert.equal((await state()).visible,false,'results collapse the form');
        await page.locator('#resultsSearchEdit').click();
        assert.ok((await state()).visible,'result summary edit restores the native form');
        await page.evaluate(() => window.V2Results.render([]));
        assert.equal((await state()).visible,false,'new empty results close the previous editor');
        await page.locator('.empty-edit-search').click();
        assert.ok((await state()).visible,'empty-result edit restores the native form');
      }
    }
    assert.deepEqual(errors, [], 'no browser exceptions');
    return { initial, started, validation, dirty, resized };
  } finally { await page.close(); }
}
(async () => {
  const browser = await chromium.launch({ headless: true });
  const evidence = { baseline, widths: {} };
  try {
    for (const width of [375, 700, 701, 760, 761, 1440]) {
      const before = await inspect(browser, width, true), after = await inspect(browser, width, false);
      evidence.widths[width] = { before, after };
      // Compact native presentation intentionally changes dimensions. Keep exact
      // values/visibility in every phase and reject overflow at both breakpoints.
      for (const phase of Object.keys(before)) {
        assert.deepEqual(after[phase].fields, before[phase].fields, `${width} ${phase}: exact form values`);
        assert.equal(after[phase].visible, before[phase].visible, `${width} ${phase}: lifecycle visibility`);
        assert.equal(after[phase].overflow, false, `${width} ${phase}: no horizontal overflow`);
        assert.ok(after[phase].width > 0, `${width} ${phase}: bounded native form`);
      }
    }
  } finally {
    await browser.close();
    fs.writeFileSync(path.join(process.env.SEARCH3_GEOMETRY_OUTPUT, 'entry-lifecycle.json'), JSON.stringify(evidence, null, 2));
  }
  console.log('SEARCH3_LEAN_ENTRY_OK widths=375,700,701,760,761,1440 states=30');
})().catch(error => { console.error(error); process.exitCode = 1; });
