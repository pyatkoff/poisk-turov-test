'use strict';
// CI-only acceptance: served exact artifact, fictional catalog/offers, all remote
// traffic intercepted. This is Chromium/WebKit evidence, not physical Safari.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, webkit } = require('playwright');
const base = new URL(process.env.SEARCH3_VISUAL_BASE);
const sourceSha = process.env.SEARCH3_SOURCE_SHA;
assert.equal(base.hostname, '127.0.0.1');
assert.match(sourceSha || '', /^[a-f0-9]{40}$/);
const output = path.join(process.env.SEARCH3_RESULTS_OUTPUT, 'hotel-tabs');
fs.mkdirSync(output, { recursive: true });
const prefix = '/_preview/search3-local-candidate';
const origin = 'https://anytoour.ru';
const entry = origin + prefix + '/poisk-turov/';
const date = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
const query = new URLSearchParams({ from: '1', country: '4', dateFrom: date, dateTo: date, daysFrom: '7', daysTill: '10', count_people: '2', child_count: '0', utm_source: 'tabs-fixture' });
const svg = color => `<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450"><path fill="${color}" d="M0 0h800v450H0z"/><text x="30" y="240" font-size="28">Вымышленный отель · проверка вкладок</text></svg>`;
const profiles = [1, 2, 3].map(n => ({ id: 900 + n, catalog: 'anytour', revision: 1, name: `Проверочный отель ${n} с длинным названием`, category: 5, rating: 4.5, country: { name: 'Турция' }, region: { name: 'Кемер' }, description: 'Единственное описание из проверочного каталога.', detailsAvailable: true, primaryImage: origin + `/fixture-${n}-a.svg`, images: [origin + `/fixture-${n}-a.svg`, origin + `/fixture-${n}-b.svg`], hotelInformation: { services: ['Детский клуб'], roomTypes: 'Описание номеров отеля' } }));
const hotels = profiles.map((hotel, i) => ({ id: 101 + i, name: 'Supplier label must not replace catalog', provider: 'tourvisor', tours: [0, 1, 2, 3].map(n => ({ id: `hotel-${i + 1}-offer-${n}`, provider: 'tourvisor', price: 125000 + i * 10000 + n * 2500, date, nights: 7 + n, adults: 2, childs: 0, roomType: n ? 'STANDARD SEA VIEW' : 'STANDARD', meal: { name: n ? 'HB' : 'BB' }, placement: 'DBL', operator: { name: 'TEST OPERATOR' }, isCharter: true })) }));
const reports = [];
async function run(engine, width, height) {
  const browser = await ({ chromium, webkit }[engine]).launch();
  const context = await browser.newContext({ viewport: { width, height }, hasTouch: width < 768, serviceWorkers: 'block' });
  const calls = [], failures = [], errors = [];
  const recoveryImages = { missing: origin + '/fixture-gallery-missing.svg', slow: origin + '/fixture-gallery-slow.svg', good: origin + '/fixture-gallery-good.svg' };
  let releaseSlowPhoto, slowRequests = 0, slowPhotoTimer, slowPhotoExpired = false;
  const slowPhotoGate = new Promise(resolve => { releaseSlowPhoto = resolve; }), slowReplies = [];
  const scenarioQuery = new URLSearchParams(query);
  if (width === 390) {
    scenarioQuery.set('child_count', '3');
    for (const age of ['0', '7', '17']) scenarioQuery.append('child_age[]', age);
  }
  context.on('page', page => { page.setDefaultTimeout(15000); page.on('pageerror', error => errors.push(String(error))); });
  await context.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    const json = (body, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
    if (url.origin !== origin) return route.abort();
    if (url.href === recoveryImages.missing) return route.fulfill({ status: 404, body: 'Missing fictional photo' });
    if (url.href === recoveryImages.good) return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: svg('#c7dfb5') });
    if (url.href === recoveryImages.slow) {
      slowRequests++;
      if (!slowPhotoTimer) slowPhotoTimer = setTimeout(() => { slowPhotoExpired = true; releaseSlowPhoto(); }, 20000);
      const reply = slowPhotoGate.then(() => route.fulfill({ status: 200, contentType: 'image/svg+xml', body: svg('#edd0e5') }));
      slowReplies.push(reply);
      return reply;
    }
    if (/^\/fixture-\d-[ab]\.svg$/.test(url.pathname)) return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: svg(url.pathname.endsWith('b.svg') ? '#dfc5a8' : '#badbea') });
    if (url.pathname.endsWith('/data/hotel-details-read-v1.php')) {
      const ownId=url.searchParams.get('anytourHotelId');
      if(ownId!==null){
        calls.push({page:request.frame().page(),action:'own-profile',anytourHotelId:ownId});
        assert.equal(url.searchParams.get('catalog'),'anytour');
        assert.equal(url.searchParams.has('hotelId')||url.searchParams.has('legacyHotelIds[]'),false,'Canonical identity never enters the supplier namespace');
        const item=profiles.find(profile=>String(profile.id)===ownId);
        return item?json({ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item}):json({ok:false,error:'Hotel not found'},404);
      }
      const ids = url.searchParams.getAll('legacyHotelIds[]');
      const recovery = new URL(request.frame().page().url()).searchParams.get('utm_source') === 'photo-recovery-fixture';
      const items = ids.map(id => { const profile = profiles[Number(id) - 101]; return recovery && profile.id === 901 ? { ...profile, images: [profile.primaryImage, recoveryImages.missing, recoveryImages.slow, recoveryImages.good] } : profile; });
      return json({ ok: true, source: 'anytour-canonical-catalog', catalog: 'anytour', requestedLegacyIds: ids, items, links: ids.map(id => ({ legacyHotelId: id, anytourHotelId: Number(id) + 800 })), missingLegacyIds: [] });
    }
    if (url.pathname.endsWith('/data/departures-v1.php')) return json({ ok: true, items: [{ id: 1, russianName: 'Москва' }] });
    if (url.pathname === '/data/observe-search-v1.php' && request.method() === 'POST') {
      // Existing passive price observation is not a lead. Keep it fully mocked
      // and prove that opening hotel tabs does not duplicate it.
      calls.push({ page: request.frame().page(), action: 'price-observation' });
      return json({ ok: true });
    }
    if (url.pathname.endsWith('/data/search3-local-results-read-v1.php')) {
      calls.push({ page: request.frame().page(), action: 'local-db' });
      return json({ ok: true, data: { source: 'anytour-db-first-results-v1', scopeVersion: 1, scopeDigest: 'a'.repeat(64), selectionAuthority: false, hotels: [], hotelCount: 0, offerCount: 0 } });
    }
    if (url.pathname.includes('api-andromeda')) {
      calls.push({ page: request.frame().page(), action: 'andromeda' });
      const body = JSON.parse(request.postData() || '{}');
      return json({ ok: true, data: { provider: 'andromeda', generation: body.generation, page: 1, pages_count: 1, hotels: [] } });
    }
    if (/\/(?:api[^/]*)\.php$/.test(url.pathname)) {
      const action = url.searchParams.get('action');
      calls.push({ page: request.frame().page(), action, params: Object.fromEntries(url.searchParams) });
      if (action === 'countries') return json([{ id: 4, russianName: 'Турция' }]);
      if (action === 'search_start') return json({ searchId: 42 });
      if (action === 'search_status') return json({ status: 'complete', progress: 100 });
      if (action === 'search_results') return url.searchParams.get('searchId') === '42' ? json(hotels) : json({ error: 'Expired fixture' }, 410);
      if (action === 'tour') {
        const id = url.searchParams.get('tourId'), hotel = hotels.find(h => h.tours.some(t => t.id === id));
        assert.ok(hotel, 'exact offer, never canonical hotel ID, is sent to tour API');
        return json({ ...hotel.tours.find(t => t.id === id), hotel: { id: hotel.id, name: 'Supplier label must not replace catalog' } });
      }
      if (action === 'flights') return json([{ price: { value: 125000 }, isDefault: true, forward: [], backward: [] }]);
      return json([]);
    }
    if (request.method() !== 'GET' || /lead|quote-preview/.test(url.pathname)) { failures.push(url.pathname); return route.abort(); }
    if (!url.pathname.startsWith(prefix + '/')) return route.abort();
    // The built artifact is served on its existing CI mount. Only the fixture
    // response paths are mapped to the authorized local-preview mount.
    const response = await route.fetch({ url: base.origin + url.pathname.replace(prefix, base.pathname) + url.search });
    const contentType = response.headers()['content-type'] || '';
    if (/text|javascript|json/.test(contentType)) return route.fulfill({ response, body: (await response.text()).replaceAll(base.pathname, prefix) });
    return route.fulfill({ response });
  });
  const page = await context.newPage();
  try {
    await page.goto(entry + '?' + scenarioQuery, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#results .hotel-card:nth-of-type(3)');
    await page.waitForFunction(() => document.querySelectorAll('#results a.tour-more-toggle[target="_blank"]').length === 3);
    await page.locator('#sortResults').selectOption('rating');
    if (width < 1025) await page.locator('.search3-mobile-filter-panel > summary').click();
    await page.getByRole('searchbox', { name: /Название отеля/ }).fill('Проверочный');
    if (width < 1025) await page.locator('.search3-mobile-filter-panel > summary').click();
    // Mobile shows gallery controls when the existing hotel details are open.
    await page.locator('.hotel-card').first().locator('.hotel-details > summary').click();
    const firstPhoto = page.locator('.hotel-card').first().locator('.hotel-gallery-main');
    const previousPhoto = await firstPhoto.getAttribute('src');
    await page.locator('.hotel-card').first().getByRole('button', { name: 'Поменять главное фото, миниатюра 1' }).click();
    assert.notEqual(await firstPhoto.getAttribute('src'), previousPhoto, 'gallery really switches the main photo');
    const state = () => page.evaluate(() => ({ url: location.href, scroll: scrollY, filter: document.querySelector('input[placeholder="Введите название"]')?.value, sort: document.querySelector('#sortResults').value, photo: document.querySelector('.hotel-card .hotel-photo img')?.src, open: document.querySelector('.hotel-card .hotel-details')?.open, ids: [...document.querySelectorAll('.hotel-card')].map(n => n.dataset.hotelId) }));
    const photoLink = page.locator('.hotel-card').first().locator('.search3-hotel-photo-link');
    await photoLink.scrollIntoViewIfNeeded();
    const beforeViewer = await state(), beforeCalls = calls.length;
    if (width < 768) await photoLink.tap(); else await photoLink.click();
    const viewer = page.getByRole('dialog', { name: profiles[0].name, exact: true });
    await viewer.waitFor({ state: 'visible' });
    assert.equal(await viewer.locator('img').getAttribute('src'), beforeViewer.photo, 'dialog starts from the currently displayed thumbnail');
    await viewer.getByRole('button', { name: 'Следующее фото', exact: true }).click();
    assert.equal(await viewer.locator('img').getAttribute('src'), previousPhoto, 'dialog uses the actual second rendered photo');
    assert.equal(await viewer.locator('.hotel-photo-viewer__counter').innerText(), 'Фото 2 из 2');
    await page.keyboard.press('ArrowLeft');
    assert.equal(await viewer.locator('img').getAttribute('src'), beforeViewer.photo);
    await viewer.locator('img').waitFor({ state: 'visible' });
    for (const action of await viewer.locator('button:visible, a:visible').all()) {
      const box = await action.boundingBox();
      assert.ok(box.height >= 44 && box.x >= 0 && box.y >= 0 && box.x + box.width <= width && box.y + box.height <= height, 'photo actions remain reachable on this viewport');
    }
    await page.screenshot({ path: path.join(output, `${engine}-${width}x${height}-photos.png`), animations: 'disabled' });
    await page.keyboard.press('Escape');
    await viewer.waitFor({ state: 'hidden' });
    await page.waitForFunction(() => document.activeElement?.classList.contains('search3-hotel-photo-link'));
    const afterViewer = await state();
    assert.ok(Math.abs(afterViewer.scroll - beforeViewer.scroll) <= 2, 'photo close restores the same scroll position');
    delete beforeViewer.scroll; delete afterViewer.scroll;
    assert.deepEqual(afterViewer, beforeViewer, 'photo viewing preserves URL/filter/sort/current thumbnail/expanded result');
    assert.equal(calls.length, beforeCalls, 'photos do not trigger search/tour/observation calls');
    const children = [];
    for (let index = 0; index < 3; index++) {
      const action = page.locator('.hotel-card').nth(index).locator('a.tour-more-toggle');
      await action.scrollIntoViewIfNeeded();
      const box = await action.boundingBox();
      assert.ok(box.height >= 44, 'new-tab action retains full touch target');
      assert.equal(await action.getAttribute('rel'), 'noopener');
      assert.match(await action.getAttribute('aria-label'), /новой вкладке/);
      const before = await state();
      const opened = context.waitForEvent('page');
      await action.click();
      const child = await opened;
      children.push(child);
      await child.waitForSelector('#results .hotel-card .direct-tour');
      assert.equal(await child.locator('#results .hotel-card').count(), 1, 'one canonical hotel per detail tab');
      assert.equal(await child.locator('#results .hotel-card h3').innerText(), profiles[index].name);
      assert.equal(await child.evaluate(() => window.opener === null), true, 'detail has no opener dependency');
      assert.equal(new URL(child.url()).searchParams.get('search3_hotel'), String(901 + index));
      assert.equal(new URL(child.url()).searchParams.get('utm_source'), 'tabs-fixture');
      assert.equal(new URL(child.url()).searchParams.get('child_count'), scenarioQuery.get('child_count'));
      assert.deepEqual(new URL(child.url()).searchParams.getAll('child_age[]'), scenarioQuery.getAll('child_age[]'), 'all child ages survive a new tab, including 0 and 17');
      const after = await state();
      assert.ok(Math.abs(after.scroll - before.scroll) <= 2, 'opening hotel does not move original results');
      delete before.scroll; delete after.scroll;
      assert.deepEqual(after, before, 'URL/filter/sort/photo/expanded detail remain in original tab');
      assert.equal(calls.filter(c => c.page === child && ['search_start', 'local-db', 'andromeda', 'price-observation'].includes(c.action)).length, 0, 'detail never starts a parallel search cohort or observation');
      const trip = await child.locator('#results > .results-state').innerText();
      assert.match(trip, /7–10 ноч\. · 2 взр\./, 'trip context remains visible in the hotel tab');
      if (width === 390) assert.match(trip, /Возраст детей: 0, 7, 17/);
      assert.equal(await child.locator('.results-filter-rail').isVisible(), false, 'hotel detail starts without the search filter rail');
      assert.equal(await child.locator('#results a.tour-more-toggle[target="_blank"]').count(), 0, 'internal hotel actions do not spawn more tabs');
    }
    assert.equal(calls.filter(c => c.action === 'search_start').length, 1, 'three tabs reuse the original search');
    const child = children[0], initialDetailUrl = child.url();
    await child.locator('.tour-list-more').click();
    assert.equal(await child.locator('.direct-tour').count(), 4, 'all exact offers remain reachable');
    await child.locator('.search3-shortlist-toggle').first().click();
    const saved = await child.locator('.search3-shortlist-toggle').first().getAttribute('aria-pressed');
    assert.equal(saved, 'true', 'exact offer is actually saved before testing Back');
    const exact = await child.locator('.direct-tour').first().getAttribute('data-tid');
    const comparisonBefore = await child.locator('.search3-shortlist-item').evaluateAll(nodes => nodes.map(el => ({offer:el.dataset.offerId,search:el.dataset.searchId,price:el.querySelector('.search3-shortlist-item__price strong').textContent})));
    await child.locator('.direct-tour').first().click();
    await child.waitForSelector('#selectedTour .flight-variant');
    assert.equal(context.pages().length, 4, 'selecting a tour uses the hotel tab');
    assert.equal(calls.filter(c => c.page === child && c.action === 'tour').at(-1).params.tourId, exact);
    assert.equal(calls.filter(c => c.page === child && c.action === 'flights').at(-1).params.tourId, exact);
    assert.match(await child.locator('#selectedTour').innerText(), /Проверочный отель 1/);
    assert.equal(await child.locator('.search3-shortlist').isVisible(),false,'comparison must not displace or compete with the selected tour');
    assert.equal(await child.locator('.search3-shortlist button:visible').count(),0,'hidden comparison controls leave the focus order');
    await child.screenshot({path:path.join(output,`${engine}-${width}x${height}-selected-comparison-hidden.png`),fullPage:true,animations:'disabled'});
    await child.getByRole('button',{name:'Продолжить к заявке',exact:true}).click();
    assert.equal(await child.getByRole('textbox',{name:/Телефон/}).isVisible(),true,'selected tour reaches the existing contact form without sending a lead');
    assert.equal(await child.locator('.search3-shortlist').isVisible(),false,'comparison stays hidden at lead entry');
    await child.getByRole('button', { name: '← Вернуться к предложениям', exact: true }).click();
    await child.waitForFunction(() => document.querySelector('#selectedTour').hidden);
    assert.equal(child.url(), initialDetailUrl, 'working same-tab Back retains the addressable hotel');
    assert.equal(await child.locator('.search3-shortlist').isVisible(),true,'canonical return restores the saved comparison');
    assert.deepEqual(await child.locator('.search3-shortlist-item').evaluateAll(nodes => nodes.map(el => ({offer:el.dataset.offerId,search:el.dataset.searchId,price:el.querySelector('.search3-shortlist-item__price strong').textContent}))),comparisonBefore,'hiding comparison never deletes or changes saved offers/prices');
    assert.equal(await child.locator('.direct-tour').count(), 4);
    assert.equal(await child.locator('.search3-shortlist-toggle').first().getAttribute('aria-pressed'), saved);
    assert.equal(await child.locator('.results-filter-rail').isVisible(), false, 'single hotel does not occupy a results filter rail');
    assert.equal(await child.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'no horizontal overflow');
    for (const action of await child.locator('#results .direct-tour, #results .tour-list-more, #results .results-state a').all()) assert.ok((await action.boundingBox()).height >= 44);
    await child.locator('.hotel-card').scrollIntoViewIfNeeded();
    await child.screenshot({ path: path.join(output, `${engine}-${width}x${height}-detail.png`), fullPage: true, animations: 'disabled' });
    assert.equal(await child.locator('.results-filter-rail').isVisible(), false, 'detail layout remains stable after screenshots and scroll');
    await page.screenshot({ path: path.join(output, `${engine}-${width}x${height}-results.png`), fullPage: true, animations: 'disabled' });
    await child.reload({ waitUntil: 'domcontentloaded' });
    await child.waitForSelector('#results .hotel-card .direct-tour');
    assert.equal(calls.filter(c => c.action === 'search_start').length, 1, 'detail reload only reloads the existing search result');
    const direct = await context.newPage();
    await direct.goto(initialDetailUrl, { waitUntil: 'domcontentloaded' });
    await direct.waitForSelector('#results .hotel-card .direct-tour');
    assert.equal(await direct.evaluate(() => window.opener === null), true);
    assert.equal(await direct.locator('#resultSummary').innerText(),'Найдено отелей: 1 · цены из текущего поиска','Active hotel detail keeps the current-search price scope');
    const selectionCalls=()=>calls.filter(c=>c.action==='tour'||c.action==='flights').length;
    const shortlistBeforeRollover=await direct.evaluate(()=>localStorage.getItem('anytour.search3.shortlist.v1'));
    const afterDeparture=new Date(date+'T12:00:00').getTime()+86400000;
    const fixClock=async current=>current.evaluate(now=>{const NativeDate=Date;class FixedDate extends NativeDate{constructor(...args){super(...(args.length?args:[now]));}static now(){return now;}}window.Date=FixedDate;},afterDeparture);
    const beforeClickRollover=selectionCalls();
    await fixClock(direct);
    await direct.evaluate(()=>document.querySelector('.direct-tour').click());
    await direct.getByText('Дата вылета уже прошла.',{exact:false}).waitFor();
    await direct.waitForSelector('#results .hotel-card .hotel-gallery-main');
    assert.equal(selectionCalls(),beforeClickRollover,'Past offer click is stopped before tour/flights requests');
    assert.equal(await direct.locator('#results .direct-tour, #results .hotel-price, #results .search3-shortlist-toggle').count(),0,'Rollover removes stale offer and price authority');
    assert.equal(await direct.locator('#resultSummary').innerText(),'Найдено отелей: 1 · сохранённая выдача','Rollover summary revokes the current-price claim with the offers');
    assert.equal(await direct.locator('#results .hotel-card h3').innerText(),profiles[0].name,'Rollover keeps the canonical hotel profile');
    assert.equal(await direct.locator('.search3-shortlist-select:not(.search3-shortlist-restore)').count(),0,'Historical comparison cannot select an expired offer');
    assert.equal(await direct.locator('.search3-shortlist-restore').count(),1,'Historical comparison remains available only through explicit search restoration');
    assert.equal(await direct.evaluate(()=>localStorage.getItem('anytour.search3.shortlist.v1')),shortlistBeforeRollover,'Rollover does not rewrite historical comparison records');
    assert.equal(await direct.evaluate(()=>window.V2SearchLifecycle.searchId),0,'Rollover revokes the active search ID');
    await direct.reload({waitUntil:'domcontentloaded'});
    await direct.waitForSelector('#results .hotel-card .direct-tour');
    const beforeFocusRollover=selectionCalls();
    await fixClock(direct);
    await direct.evaluate(()=>window.dispatchEvent(new Event('focus')));
    await direct.getByText('Дата вылета уже прошла.',{exact:false}).waitFor();
    assert.equal(selectionCalls(),beforeFocusRollover,'Focus rollover never selects or refreshes a tour');
    assert.equal(await direct.locator('#results .direct-tour, #results .hotel-price').count(),0);
    const timer = await context.newPage(),timerBase=new Date(date+'T23:59:59').getTime();
    await timer.addInitScript(base=>{const NativeDate=Date,started=NativeDate.now();const clock=()=>base+NativeDate.now()-started;class MovingDate extends NativeDate{constructor(...args){super(...(args.length?args:[clock()]));}static now(){return clock();}}window.Date=MovingDate;},timerBase);
    const beforeTimerRollover=selectionCalls();
    await timer.goto(initialDetailUrl,{waitUntil:'domcontentloaded'});
    await timer.waitForSelector('#results .hotel-card .direct-tour');
    await timer.getByText('Дата вылета уже прошла.',{exact:false}).waitFor();
    await timer.waitForSelector('#results .hotel-card .hotel-gallery-main');
    assert.equal(selectionCalls(),beforeTimerRollover,'Midnight timer never selects or refreshes a tour');
    assert.equal(await timer.locator('#results .direct-tour, #results .hotel-price').count(),0,'Midnight timer revokes expired offers');
    assert.equal(await timer.locator('#results .hotel-card h3').innerText(),profiles[0].name);
    const expiredUrl = new URL(initialDetailUrl); expiredUrl.searchParams.set('search3_search', '43');
    const profileReadsBeforeExpired=calls.filter(c=>c.page===direct&&c.action==='own-profile').length;
    await direct.goto(expiredUrl.href, { waitUntil: 'domcontentloaded' });
    await direct.getByText('Этот поиск больше недоступен.', { exact: false }).waitFor();
    await direct.waitForSelector('#results .hotel-card .hotel-gallery-main');
    assert.equal(await direct.locator('#results .hotel-card').count(),1);
    assert.equal(await direct.locator('#results .hotel-card h3').innerText(),profiles[0].name,'Expired search preserves the canonical hotel');
    assert.equal(await direct.locator('#results .hotel-description-summary').innerText(),profiles[0].description);
    assert.equal(await direct.locator('#results .direct-tour, #results .hotel-price, #results .search3-shortlist-toggle').count(),0,'No stale price, offer selection or shortlist authority');
    assert.equal(await direct.locator('#resultSummary').innerText(),'Найдено отелей: 1 · сохранённая выдача','Expired profile-only summary does not claim a current-search price');
    assert.doesNotMatch(await direct.locator('#results > .results-state').innerText(),/Поисковая цена/,'Profile-only mode does not claim a current search price');
    assert.equal(await direct.locator('.hotel-gallery-main').getAttribute('src'),profiles[0].primaryImage);
    assert.equal(calls.filter(c=>c.page===direct&&c.action==='own-profile').length,profileReadsBeforeExpired+1,'Expired search performs one explicit canonical read');
    assert.equal(await direct.locator('#tourSearch').isVisible(), true, 'expired search keeps explicit recovery available');
    assert.equal(await direct.locator('.search-editor-collapse').isVisible(), false, 'profile-only mode cannot hide the recovery form behind unavailable result controls');
    if(width<768){
      const recoverySubmit=direct.locator('#tourSearch .search-submit');
      await recoverySubmit.scrollIntoViewIfNeeded();
      const recoveryGeometry=await direct.evaluate(()=>{
        const form=document.querySelector('#tourSearch'),title=form?.querySelector(':scope>.search-section-title:first-child'),submit=form?.querySelector('.search-submit');
        if(!title||!submit)return null;
        const titleBox=title.getBoundingClientRect(),submitBox=submit.getBoundingClientRect();
        return {titlePosition:getComputedStyle(title).position,overlap:!(titleBox.right<=submitBox.left||titleBox.left>=submitBox.right||titleBox.bottom<=submitBox.top||titleBox.top>=submitBox.bottom)};
      });
      assert.deepEqual(recoveryGeometry,{titlePosition:'static',overlap:false},'expired mobile recovery keeps the full Find tours CTA clear of the form title');
      await recoverySubmit.click({trial:true});
    }
    assert.equal(calls.filter(c => c.action === 'search_start').length, 1, 'expiry never silently launches a full search');
    await direct.locator('.hotel-details > summary').click();
    const beforeExpiredPhotos=calls.length;
    await direct.locator('.search3-hotel-photo-link').click();
    const expiredViewer=direct.getByRole('dialog',{name:profiles[0].name,exact:true});
    await expiredViewer.waitFor({state:'visible'});
    await expiredViewer.getByRole('button',{name:'Следующее фото',exact:true}).click();
    assert.equal(await expiredViewer.locator('img').getAttribute('src'),profiles[0].images[1]);
    await direct.keyboard.press('Escape');
    assert.equal(calls.length,beforeExpiredPhotos,'Expired hotel gallery does not refresh offers or call a supplier');
    assert.equal(await direct.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
    await direct.screenshot({ path: path.join(output, `${engine}-${width}x${height}-expired.png`), fullPage: true, animations: 'disabled' });
    const pastUrl=new URL(expiredUrl),pastDate=new Date(Date.now()-86400000).toISOString().slice(0,10);
    pastUrl.searchParams.set('dateFrom',pastDate);pastUrl.searchParams.set('dateTo',pastDate);
    const resultsBeforePast=calls.filter(c=>c.action==='search_results').length;
    await direct.goto(pastUrl.href,{waitUntil:'domcontentloaded'});
    await direct.waitForSelector('#results .hotel-card .hotel-gallery-main');
    assert.equal(calls.filter(c=>c.action==='search_results').length,resultsBeforePast,'Past dates read the hotel without querying old offers');
    assert.equal(await direct.locator('#results .direct-tour, #results .hotel-price').count(),0);
    assert.equal(await direct.locator('#resultSummary').innerText(),'Найдено отелей: 1 · сохранённая выдача','Past-date profile summary remains truthful without offers');
    const missingUrl=new URL(expiredUrl);missingUrl.searchParams.set('search3_hotel','999');
    await direct.goto(missingUrl.href,{waitUntil:'domcontentloaded'});
    await direct.getByText('Описание отеля сейчас недоступно.',{exact:false}).waitFor();
    assert.equal(await direct.locator('#results .hotel-card').count(),0,'Missing profile is not invented');
    assert.equal(await direct.locator('#tourSearch').isVisible(),true);
    assert.equal(calls.filter(c=>c.action==='search_start').length,1,'All recovery remains manual');
    assert.deepEqual(failures, [], 'no lead/booking/unknown writes');
    assert.deepEqual(errors, [], 'no browser exceptions');

    // A separate existing-search hotel tab exercises image transport failures.
    // Delay actual image responses; do not stub the viewer or dispatch load events.
    const recovery = await context.newPage(), recoveryUrl = new URL(initialDetailUrl);
    recoveryUrl.searchParams.set('utm_source', 'photo-recovery-fixture');
    await recovery.goto(recoveryUrl.href, { waitUntil: 'domcontentloaded' });
    await recovery.waitForSelector('#results .hotel-card .direct-tour');
    await recovery.locator('.hotel-details > summary').click();
    const recoveryLink = recovery.locator('.search3-hotel-photo-link');
    await recoveryLink.scrollIntoViewIfNeeded();
    const recoveryState = () => recovery.evaluate(() => ({ url: location.href, scroll: scrollY, hotel: document.querySelector('.hotel-card')?.dataset.anytourHotelId, offers: [...document.querySelectorAll('.direct-tour')].map(el => el.dataset.tid), prices: [...document.querySelectorAll('.hotel-price')].map(el => el.textContent), photo: document.querySelector('.hotel-gallery-main')?.getAttribute('src'), expanded: document.querySelector('.hotel-details')?.open }));
    const beforeRecovery = await recoveryState(), beforeRecoveryCalls = calls.length;
    await recoveryLink.click();
    const recoveryViewer = recovery.getByRole('dialog', { name: profiles[0].name, exact: true });
    const recoveryImage = recoveryViewer.locator('img'), recoveryStatus = recoveryViewer.locator('.hotel-photo-viewer__status');
    const closePhotos = recoveryViewer.getByRole('button', { name: 'Закрыть фотографии', exact: true });
    const nextPhoto = recoveryViewer.getByRole('button', { name: 'Следующее фото', exact: true });
    const originalPhoto = recoveryViewer.getByRole('link', { name: 'Открыть оригинал' });
    await recoveryImage.waitFor({ state: 'visible' });
    await closePhotos.focus();
    await recovery.keyboard.press('Shift+Tab');
    assert.equal(await originalPhoto.evaluate(el => el === document.activeElement), true, 'reverse Tab stays inside the photo dialog');
    await recovery.keyboard.press('Tab');
    assert.equal(await closePhotos.evaluate(el => el === document.activeElement), true, 'forward Tab wraps to the dialog close action');
    await nextPhoto.click();
    await recoveryStatus.getByText('Не удалось загрузить фото.', { exact: false }).waitFor();
    assert.equal(await recoveryImage.isVisible(), false, 'a failed photo does not leave a misleading old image');
    assert.equal(await originalPhoto.getAttribute('href'), recoveryImages.missing, 'original link belongs to the failed current image');
    for (const action of await recoveryViewer.locator('button:visible, a:visible').all()) {
      const box = await action.boundingBox();
      assert.ok(box.height >= 44 && box.x >= 0 && box.y >= 0 && box.x + box.width <= width && box.y + box.height <= height, 'photo recovery actions remain reachable');
    }
    await nextPhoto.click();
    assert.equal(await recoveryStatus.innerText(), 'Загружаем фото…');
    assert.equal(await recoveryImage.isVisible(), false);
    await nextPhoto.click();
    await recoveryImage.waitFor({ state: 'visible' });
    assert.equal(await recoveryImage.getAttribute('src'), recoveryImages.good, 'a slow photo never blocks moving to another image');
    assert.equal(await recoveryViewer.locator('.hotel-photo-viewer__counter').innerText(), 'Фото 4 из 4');
    assert.ok(slowRequests > 0, 'the image delay was actually exercised');
    await closePhotos.click();
    await recoveryViewer.waitFor({ state: 'hidden' });
    assert.equal(await recoveryLink.evaluate(el => el === document.activeElement), true, 'recovery close returns focus to the same hotel');
    await recoveryLink.click();
    await recoveryImage.waitFor({ state: 'visible' });
    assert.equal(slowPhotoExpired, false, 'the controlled delay must be released by the scenario, not its watchdog');
    clearTimeout(slowPhotoTimer);releaseSlowPhoto();
    await Promise.all(slowReplies);
    await recovery.waitForFunction(url => [...document.querySelectorAll('.hotel-gallery-thumb img')].some(img => img.getAttribute('src') === url && img.complete && img.naturalWidth > 0), recoveryImages.slow);
    assert.equal(await recoveryImage.getAttribute('src'), beforeRecovery.photo, 'late response cannot replace the image in a reopened dialog');
    assert.equal(await recoveryImage.isVisible(), true);
    assert.equal(await recoveryStatus.isVisible(), false);
    assert.equal(await recoveryViewer.locator('.hotel-photo-viewer__counter').innerText(), 'Фото 1 из 4');
    await recovery.screenshot({ path: path.join(output, `${engine}-${width}x${height}-photo-recovered.png`), animations: 'disabled' });
    // Capture the error after releasing all held image requests, so screenshot
    // readiness cannot become part of the controlled network delay.
    await nextPhoto.click();
    await recoveryStatus.getByText('Не удалось загрузить фото.', { exact: false }).waitFor();
    await recovery.screenshot({ path: path.join(output, `${engine}-${width}x${height}-photo-error.png`), animations: 'disabled' });
    await recovery.keyboard.press('Escape');
    await recoveryViewer.waitFor({ state: 'hidden' });
    const afterRecovery = await recoveryState();
    assert.ok(Math.abs(afterRecovery.scroll - beforeRecovery.scroll) <= 2);
    delete beforeRecovery.scroll; delete afterRecovery.scroll;
    assert.deepEqual(afterRecovery, beforeRecovery, 'image errors and late responses preserve exact hotel/offers/prices/URL');
    assert.equal(calls.length, beforeRecoveryCalls, 'image recovery never starts search/tour/observation calls');
    assert.equal(calls.filter(c => c.action === 'search_start').length, 1);
    assert.deepEqual(failures, [], 'no lead/booking/unknown writes');
    assert.deepEqual(errors, [], 'no browser exceptions');
    await direct.goto(expiredUrl.href, { waitUntil: 'domcontentloaded' });
    await direct.waitForSelector('#results .hotel-card .hotel-gallery-main');
    const profileReadsBeforeRefresh=calls.filter(c=>c.action==='own-profile').length;
    await direct.locator('#tourSearch button.primary').click();
    await direct.waitForSelector('#results .hotel-card:nth-of-type(3)');
    assert.equal(calls.filter(c=>c.action==='search_start').length,2,'Explicit refresh starts exactly one new search');
    assert.equal(calls.filter(c=>c.action==='own-profile').length,profileReadsBeforeRefresh,'New search does not repeat the old profile read');
    assert.equal(new URL(direct.url()).searchParams.has('search3_hotel'),false,'Manual refresh leaves the expired hotel URL');
    assert.equal(await direct.locator('#results a.tour-more-toggle[target="_blank"]').count(),3,'Fresh exact hotel offers are reachable again');
    assert.match((await direct.locator('.hotel-card').first().locator('.hotel-price').innerText()).replace(/\s/g,''),/125000/,'Fresh offer price returns only with the new search');
    assert.deepEqual(failures, [], 'manual recovery adds no lead/booking/unknown writes');
    assert.deepEqual(errors, [], 'manual recovery has no browser exceptions');
    // Real same-origin pages share storage; each keeps its own offer authority.
    // No app-state injection or synthetic storage event supplies synchronization.
    const storageKey='anytour.search3.shortlist.v1', sibling=children[1], thirdTab=children[2], beforeSyncCalls=calls.length;
    for(const current of [page,...children])await current.evaluate(key=>{
      window.__shortlistWrites=[];window.__shortlistStorageEvents=[];
      for(const method of ['setItem','removeItem']){const original=Storage.prototype[method];Storage.prototype[method]=function(name,...args){if(this===localStorage&&name===key)window.__shortlistWrites.push(method);return original.call(this,name,...args);};}
      window.addEventListener('storage',event=>window.__shortlistStorageEvents.push({key:event.key,value:event.newValue}));
    },storageKey);
    const waitCount=(current,count)=>current.waitForFunction(n=>document.querySelectorAll('.search3-shortlist-item').length===n,count);
    const savedRows=current=>current.locator('.search3-shortlist-item').evaluateAll(nodes=>nodes.map(item=>({offer:item.dataset.offerId,search:item.dataset.searchId,price:item.querySelector('.search3-shortlist-item__price strong').textContent.replace(/\s/g,'')})));
    await waitCount(page,1);
    await page.locator('#sortResults').focus();
    await sibling.locator('.search3-shortlist-toggle').first().click();
    for(const current of [page,...children])await waitCount(current,2);
    assert.equal(await page.locator('#sortResults').evaluate(el=>el===document.activeElement),true,'remote comparison update never steals focus outside its controls');
    assert.deepEqual(await savedRows(page),[{offer:'hotel-1-offer-0',search:'42',price:'125000₽'},{offer:'hotel-2-offer-0',search:'42',price:'135000₽'}],'original results receive both exact saved offers and historical prices');
    assert.deepEqual(await child.evaluate(()=>window.__shortlistWrites),[],'receiving a storage update does not echo-write');
    assert.deepEqual(await page.evaluate(()=>window.__shortlistWrites),[]);
    assert.deepEqual(await sibling.evaluate(()=>window.__shortlistWrites),['setItem']);
    if(width<=600)await page.locator('.search3-shortlist-disclosure').click();
    await page.locator('.search3-shortlist').scrollIntoViewIfNeeded();
    await page.screenshot({path:path.join(output,`${engine}-${width}x${height}-comparison-synced.png`),fullPage:true,animations:'disabled'});
    if(width<=600)await child.locator('.search3-shortlist-disclosure').click();
    await child.locator('.search3-shortlist-remove').first().focus();
    await thirdTab.locator('.search3-shortlist-toggle').first().click();
    for(const current of [page,...children])await waitCount(current,3);
    await child.waitForFunction(()=>document.activeElement?.classList.contains('search3-shortlist-remove')&&document.activeElement.closest('.search3-shortlist-item').dataset.offerId==='hotel-1-offer-0');
    assert.equal(await sibling.locator('.search3-shortlist-item[data-offer-id="hotel-1-offer-0"] .search3-shortlist-restore').count(),1,'a sibling snapshot does not gain selection authority from matching storage alone');
    // A write in this page deliberately leaves its in-memory view stale until
    // the next user action, exercising the pending-notification write boundary.
    await child.evaluate(key=>localStorage.setItem(key,JSON.stringify(JSON.parse(localStorage.getItem(key)).filter(item=>item.offerId!=='hotel-3-offer-0'))),storageKey);
    await child.locator('.search3-shortlist-toggle[data-offer-id="hotel-1-offer-1"]').click();
    for(const current of [page,...children])await waitCount(current,3);
    await page.waitForFunction(()=>!!document.querySelector('.search3-shortlist-item[data-offer-id="hotel-1-offer-1"]'));
    assert.deepEqual((await savedRows(page)).map(item=>item.offer),['hotel-1-offer-0','hotel-2-offer-0','hotel-1-offer-1'],'mutation re-reads current storage; removed sibling offer is not resurrected or overwritten');
    const valid=await child.evaluate(key=>localStorage.getItem(key),storageKey);
    await sibling.evaluate(key=>localStorage.setItem(key,'{"invalid":true}'),storageKey);
    await page.waitForFunction(()=>window.__shortlistStorageEvents.some(event=>event.value==='{"invalid":true}'));
    assert.equal(await page.locator('.search3-shortlist-item').count(),3,'invalid remote storage cannot replace validated in-memory snapshots');
    assert.equal(await page.evaluate(key=>localStorage.getItem(key),storageKey),'{"invalid":true}','receiving malformed storage does not delete or rewrite another tab');
    await sibling.evaluate(({key,valid})=>{localStorage.setItem(key,valid);localStorage.setItem('search3.fixture.unrelated','1');},{key:storageKey,valid});
    await page.waitForFunction(()=>window.__shortlistStorageEvents.some(event=>event.key==='search3.fixture.unrelated'));
    assert.deepEqual(await savedRows(page),await savedRows(child),'unrelated storage keys preserve the comparison');
    if(width<=600)await sibling.locator('.search3-shortlist-disclosure').click();
    await sibling.locator('.search3-shortlist-item[data-offer-id="hotel-1-offer-0"] .search3-shortlist-remove').click();
    for(const current of [page,...children])await waitCount(current,2);
    assert.equal(await child.locator('.search3-shortlist-toggle[data-offer-id="hotel-1-offer-0"]').getAttribute('aria-pressed'),'false','remote removal updates the original offer button');
    await sibling.locator('.search3-shortlist-clear').click();
    for(const current of [page,...children]){await waitCount(current,0);assert.equal(await current.locator('.search3-shortlist').isVisible(),false,'clearing comparison propagates without stale status or resurrection');}
    assert.equal(calls.length,beforeSyncCalls,'cross-tab comparison never searches, selects a tour or contacts a supplier');
    assert.deepEqual(failures,[]);assert.deepEqual(errors,[]);
    // A hotel tab reconstructs all offers, so a narrowed group must use the
    // existing inline path instead of promising N variants then opening more.
    const beforeFilteredCalls=calls.length, beforeFilteredPages=context.pages().length;
    const panel=page.locator('.search3-mobile-filter-panel');
    const openFilters=async()=>{if(width<1025&&!await panel.evaluate(node=>node.open))await panel.locator(':scope > summary').click();};
    const closeFilters=async()=>{if(width<1025&&await panel.evaluate(node=>node.open))await page.locator('.search3-filter-results').click();};
    const meal=page.locator('.search3-meal-filter select'), nights=page.locator('.search3-nights-filter select'), cap=page.locator('.search3-budget-max');
    const first=page.locator('.hotel-card[data-anytour-hotel-id="901"]');
    const offerIds=()=>first.locator('.direct-tour').evaluateAll(nodes=>nodes.map(node=>node.dataset.tid));
    await openFilters();
    await meal.selectOption({label:'HB'});
    await cap.fill('130000');
    await cap.press('Tab'); // Native change commits the existing price input.
    await closeFilters();
    assert.equal(await first.locator('a.tour-more-toggle, a.search3-hotel-title-link').count(),0,'filtered count must not link to a broader hotel group');
    const filteredToggle=first.locator('button.tour-more-toggle');
    assert.equal(await filteredToggle.innerText(),'Показать варианты · 2','the action count describes the narrowed group');
    await filteredToggle.click();
    assert.deepEqual(await offerIds(),['hotel-1-offer-1','hotel-1-offer-2'],'inline expansion retains both exact meal/budget matches');
    if(!await first.locator('.hotel-details').evaluate(node=>node.open))await first.locator('.hotel-details > summary').click();
    await first.locator('.search3-hotel-photo-link').click();
    await page.getByRole('dialog',{name:profiles[0].name,exact:true}).waitFor({state:'visible'});
    await page.keyboard.press('Escape');
    await page.waitForFunction(()=>document.activeElement?.classList.contains('search3-hotel-photo-link'));
    assert.deepEqual(await offerIds(),['hotel-1-offer-1','hotel-1-offer-2'],'photo return keeps the narrowed offers');
    await first.scrollIntoViewIfNeeded();
    await page.screenshot({path:path.join(output,`${engine}-${width}x${height}-filtered-variants.png`),fullPage:true,animations:'disabled'});
    await openFilters();
    await nights.selectOption('8');
    await cap.fill('127499');
    await cap.press('Tab');
    await closeFilters();
    assert.equal(await page.locator('.hotel-card:visible').count(),0,'one rouble below the exact matching offer gives an actionable empty result');
    assert.equal(await page.locator('.search3-local-empty-reset').isVisible(),true);
    await openFilters();
    await cap.fill('127500');
    await cap.press('Tab');
    await closeFilters();
    assert.deepEqual(await offerIds(),['hotel-1-offer-1'],'inclusive cap returns only the exact eight-night offer');
    await page.locator('#sortResults').selectOption('stars');
    assert.deepEqual(await offerIds(),['hotel-1-offer-1'],'sorting does not widen the offer projection');
    assert.equal(calls.length,beforeFilteredCalls,'filters, expansion, photos and sorting never call a supplier');
    const filteredState=()=>page.evaluate(()=>({meal:document.querySelector('.search3-meal-filter select').value,nights:document.querySelector('.search3-nights-filter select').value,cap:document.querySelector('.search3-budget-max').value,sort:document.querySelector('#sortResults').value}));
    const beforeFilteredSelection=await filteredState(), selectedId='hotel-1-offer-1';
    await first.locator('.direct-tour').click();
    await page.waitForSelector('#selectedTour .flight-variant');
    assert.equal(context.pages().length,beforeFilteredPages,'filtered choice stays in the current tab');
    assert.equal(calls.filter(c=>c.page===page&&c.action==='tour').at(-1).params.tourId,selectedId);
    assert.equal(calls.filter(c=>c.page===page&&c.action==='flights').at(-1).params.tourId,selectedId);
    await page.getByRole('button',{name:'Продолжить к заявке',exact:true}).click();
    assert.equal(await page.getByRole('textbox',{name:/Телефон/}).isVisible(),true);
    await page.getByRole('button',{name:'← Вернуться к предложениям',exact:true}).click();
    await page.waitForFunction(()=>document.querySelector('#selectedTour').hidden);
    assert.deepEqual(await filteredState(),beforeFilteredSelection,'return restores the same meal, nights, budget and sort');
    assert.deepEqual(await offerIds(),[selectedId]);
    await page.waitForFunction(id=>document.activeElement?.classList.contains('direct-tour')&&document.activeElement.dataset.tid===id,selectedId);
    assert.match((await first.locator('.tour-row').innerText()).replace(/\s/g,''),/127500₽/,'return preserves the concrete search-offer price');
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
    await first.scrollIntoViewIfNeeded();
    await page.screenshot({path:path.join(output,`${engine}-${width}x${height}-filtered-return.png`),fullPage:true,animations:'disabled'});
    await openFilters();
    await page.locator('.search3-filter-reset').click();
    await closeFilters();
    await page.waitForFunction(()=>document.querySelectorAll('#results a.tour-more-toggle[target="_blank"]').length===3);
    assert.equal(await page.locator('#results a.search3-hotel-title-link').count(),3,'reset restores the complete-group hotel links');
    assert.equal(await page.locator('.hotel-card:visible').count(),3);
    assert.equal(calls.filter(c=>c.action==='search_start').length,2,'filtered journey never starts an extra search');
    assert.deepEqual(failures,[]);assert.deepEqual(errors,[]);
    reports.push({ engine, width, height, sourceSha, passed: true, photoViewer: true, photoContextPreserved: true, photoErrorRecovery: true, photoSlowResponseRecovery: true, photoFocusContainment: true, searchStarts: 2, detailTabs: 3, directReload: true, openTabDateRollover:true, rolloverClickGuard:true, rolloverFocus:true, rolloverTimer:true, expiredManualRecovery: true, expiredCanonicalProfile:true, expiredPhotoViewer:true, pastDateProfile:true, missingProfileRecovery:true, explicitRefresh:true, shortlistSelectedIsolation:true,shortlistCrossTab:true, shortlistStorageValidation:true, shortlistFocusPreserved:true, filteredInlineVariants:true, filteredExactSelectionReturn:true, filteredResetTabs:true, exactOffer: exact });
  } catch (error) {
    for (const [index, current] of context.pages().entries()) {
      await current.screenshot({ path: path.join(output, `${engine}-${width}-failure-${index}.png`), fullPage: true }).catch(() => {});
      fs.writeFileSync(path.join(output, `${engine}-${width}-failure-${index}.html`), await current.content().catch(() => ''));
    }
    fs.writeFileSync(path.join(output, `${engine}-${width}-failure.json`), JSON.stringify({ error: String(error), errors, failures, calls: calls.map(({ page: _, ...call }) => call) }, null, 2));
    throw error;
  } finally { clearTimeout(slowPhotoTimer); releaseSlowPhoto(); await browser.close(); }
}
(async () => {
  for (const width of [1440, 375, 390, 430]) await run('chromium', width, width === 390 ? 500 : 900);
  await run('webkit', 375, 667);
  fs.writeFileSync(path.join(output, 'hotel-tabs.json'), JSON.stringify({ sourceSha, tests: reports }, null, 2));
  console.log('SEARCH3_HOTEL_TABS_BROWSER_OK', reports.length);
})().catch(error => { console.error(error); process.exitCode = 1; });
