'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');
const {entry, openReplay} = require('./search3-visual-first-open.cjs');
const {until} = require('./search3-visual-first-tours.cjs');
const {privateTVFixture} = require('./search3-visual-messenger-browser.cjs');
const {trip, tour} = require('./search3-visual-live-fixture.cjs');

const hotelNames = ['Laguna Garden Resort', 'Pine Bay Family Hotel', 'Coastline Beach Hotel'];
const matches = ['Детский клуб', 'Песчаный пляж'];

function observeFilterLabels() {
  const page = document;
  window.hotelMatchErrors = [];
  window.addEventListener('error', event => {
    if (event.error) window.hotelMatchErrors.push(event.message);
  });
  const observer = new MutationObserver(() => {
    if (page.querySelectorAll('#cards .hotel-match-line').length !== 2) return;
    window.firstMatchedFilterLabels = Array.from(
      page.querySelectorAll('#active-filters [data-key="amenities"]'),
      button => ({label:button.textContent.trim(), action:button.getAttribute('aria-label')}),
    );
    observer.disconnect();
  });
  observer.observe(page, {childList:true, subtree:true});
}

function matchFixture(transport) {
  const calls = [];
  transport.state.hotelCatalogue = hotelNames.map((name, index) => ({
    id:2001+index, legacyId:7001+index, name, country:'4', countryName:'Турция', images:[],
  }));
  const tv = privateTVFixture({json(address, options) {
    if (new URL(address).searchParams.get('action') === 'search_results') {
      return hotelNames.map((name, i) => ({id:7001+i, name, provider:'tourvisor', tours:[
        {...tour, id:'matching-hotel-'+i, price:128500+i*4000, roomType:'Стандартный номер с балконом'},
      ]}));
    }
    return transport.json(address, options);
  }}, 'http://127.0.0.1');

  function profile(hotel) {
    const index = hotel.id-2001;
    if (index < 0 || index > 2) return hotel;
    const tags = [{id:1, name:'Пляж', items:[{id:81, name:matches[1]}]}];
    if (index !== 2) tags.push({id:3, name:'Для детей', items:[{id:71, name:matches[0]}]});
    return {...hotel, name:hotelNames[index], rating:4.8-index*.1,
      images:[entry+'assets/photo-'+(index+1)+'.jpg'], hotelInformation:{services:{tags}}};
  }

  return {calls,async reply(url, body, method, payload) {
    const action = url.searchParams.get('action') || body.action || 'read';
    calls.push([action,url.pathname]);
    assert(!/(quote|booking|payment|handoff|lead|tv_tour|tv_flights)/i.test(action), 'read-only replay');
    if (action.startsWith('tv_')) return tv.handle(action, body, method);
    const result = await transport.json(url.href, {body:payload});
    if (url.pathname.endsWith('/hotel-details-read-v1.php')) {
      if (result.item) result.item = profile(result.item);
      if (result.items) result.items = result.items.map(profile);
    }
    if (result.data?.provider === 'anex' || result.data?.provider === 'andromeda') {
      if (Array.isArray(result.data.hotels)) result.data.hotels = [];
    }
    return result;
  }};
}

module.exports = async function checkHotelMatches(driver, engine, size, root, html, output) {
  const instrumented = html.replace(/<\/head>/i, closing => '<script>(' + observeFilterLabels.toString() + ')()</script>' + closing);
  assert.notEqual(instrumented,html);
  const replay = await openReplay(root, instrumented, 0, matchFixture);
  try {
    const original = replay.origin + entry + '?' + new URLSearchParams({...trip, amenities:'3:71|1:81'});
    const expectedChips = matches.map(label => ({label, action:'Убрать: '+label}));
    const readChips = async () => {
      const chips = await until(driver, 'window.firstMatchedFilterLabels || null', 15000);
      assert.ok(chips, JSON.stringify({calls:replay.delivery.calls,blocked:replay.blocked}));
      assert.deepEqual(chips, expectedChips, 'the first matched cards and the restored chips agree');
      assert.equal(await driver.evaluate('new URLSearchParams(location.search).get("amenities")'), '3:71|1:81');
      assert.deepEqual(await driver.evaluate('window.hotelMatchErrors'), []);
      return chips;
    };
    const readRestoredChips = async () => {
      const chips = await until(driver, `(() => {
        const items = document.querySelectorAll('#active-filters [data-key="amenities"]');
        if (items.length !== 2 || ![...items].every(button => button.getClientRects().length)) return null;
        return Array.from(items, button => ({label:button.textContent.trim(), action:button.getAttribute('aria-label')}));
      })()`, 10000);
      assert.deepEqual(chips,expectedChips,'saved labels are visible without a new search');
      assert.ok(await until(driver,'!document.querySelector(".search-submit")?.disabled',10000),'the restored form is ready');
      return chips;
    };
    const providerStarts = () => replay.delivery.calls.filter(([action]) => action==='tv_search' || action==='search').length;
    await driver.go(original);
    assert.equal(await driver.evaluate('location.pathname'), entry);
    assert.match(await driver.evaluate('document.title'), /^AnyTour/);
    assert.ok(await until(driver, 'window.__firstOpen?.readyMs > 0', 10000), 'the first search form is ready');
    replay.allowClick();
    await driver.click('.search-submit:not(:disabled)');
    await readChips();
    const shared = await driver.evaluate('location.href');
    const saved = new URL(shared);
    assert.deepEqual(JSON.parse(saved.searchParams.get('amenityLabels')),{'3:71':matches[0],'1:81':matches[1]});
    const started = providerStarts();
    assert.equal(started,2,'the original explicit search used the two loopback providers');
    await driver.go(replay.origin + '/blank');
    await driver.go(shared);
    const openedChips = await readRestoredChips();
    await driver.reload();
    assert.equal(await driver.evaluate('performance.getEntriesByType("navigation")[0]?.type'), 'reload');
    const reloadedChips = await readRestoredChips();
    assert.equal(providerStarts(),started,'opening and reloading a link use no supplier search');
    await driver.evaluate("document.querySelector('#active-filters').scrollIntoView({block:'center', behavior:'instant'})");
    await driver.screenshot(path.join(output, engine+'-saved-filters.png'));
    await driver.click('.search-submit:not(:disabled)');
    await readChips();
    const readState = 'Array.from(document.querySelectorAll("#cards .hotel-card"), c => ({id:c.id, reasons:Array.from(c.querySelectorAll(".hotel-match-line li"), e => e.textContent)}))';
    const read = () => driver.evaluate(readState);
    assert.ok(await until(driver, '('+readState+').length===2', 15000), JSON.stringify({blocked:replay.blocked,calls:replay.delivery.calls,state:await read()}));
    const matched = await read();
    assert.deepEqual(matched, [
      {id:'hotel-2001', reasons:matches}, {id:'hotel-2002', reasons:matches},
    ]);
    const layout = await driver.evaluate(`(() => {
      const card = document.querySelector('#hotel-2001');
      card.scrollIntoView({block:'start', behavior:'instant'});
      scrollBy({top:-88, behavior:'instant'});
      const panel = card.querySelector('.hotel-match-line'), price = card.querySelector('.starting-price strong');
      const action = card.querySelector('[data-card-entry]');
      const visible = el => {
        const b = el.getBoundingClientRect();
        const target = document.elementFromPoint(b.left+b.width/2,b.top+b.height/2);
        return b.top>=0 && b.bottom<=innerHeight && b.width>0 && el.contains(target);
      };
      return {panel:visible(panel), price:visible(price), action:visible(action),
        panelFits:panel.scrollWidth<=panel.clientWidth,
        beforeAction:panel.getBoundingClientRect().top<action.getBoundingClientRect().top ||
          panel.getBoundingClientRect().right<=action.getBoundingClientRect().left,
        noOverflow:document.documentElement.scrollWidth<=innerWidth};
    })()`);
    assert.ok(layout.panel && layout.price && layout.action && layout.panelFits && layout.noOverflow, JSON.stringify(layout));
    assert.ok(layout.beforeAction, 'the reason appears ahead of the primary action');
    await driver.screenshot(path.join(output, engine+'-hotel-matches.png'));

    await driver.click('#active-filters [data-action="remove-filter"][aria-label="Убрать: Детский клуб"]');
    assert.ok(await until(driver, '('+readState+').length===3', 5000), 'third hotel appears after removing the child club filter');
    const beachOnly = await read();
    assert.equal(beachOnly.length, 3);
    assert.ok(beachOnly.every(result => result.reasons.length===1 && result.reasons[0]===matches[1]));
    assert.equal(await driver.evaluate('document.querySelector("#active-filters [data-key=amenities]")?.textContent.trim()'), matches[1]);
    assert.equal(await driver.evaluate('new URLSearchParams(location.search).get("amenities")'), '1:81');
    assert.deepEqual(JSON.parse(await driver.evaluate('new URLSearchParams(location.search).get("amenityLabels")')),{'1:81':matches[1]});
    await driver.click('#active-filters [data-action="remove-filter"][data-value="1:81"]');
    assert.ok((await read()).every(result => result.reasons.length===0));
    assert.deepEqual(await driver.evaluate('window.hotelMatchErrors'), []);
    assert.deepEqual(replay.blocked, []);
    return {size,matched,openedChips,reloadedChips,providerStartsOnRestore:0,afterRemovingClub:beachOnly.length,layout};
  } finally {
    await replay.close();
  }
};
