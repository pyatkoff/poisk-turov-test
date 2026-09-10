/* OTA3 shortlist contract: exact local snapshots, current-projection authority,
 * resilient storage and accessible inline comparison. Supplier/lead HTTP is blocked. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const base = process.env.SEARCH3_VISUAL_BASE;
const output = process.env.SEARCH3_RESULTS_OUTPUT || process.env.SEARCH3_GEOMETRY_OUTPUT;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'fixture must use the isolated local payload');
assert.ok(output, 'shortlist evidence output required');
fs.mkdirSync(output, { recursive: true });

const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="360"><path fill="#9ac7df" d="M0 0h600v360H0z"/></svg>');
const hotel = { id: 'offer-hotel', name: 'Отель с вариантами номера', country: { name: 'Турция' }, region: { name: 'Анталья' }, category: 5, picturelink: picture };
const offer = (id, price, roomType, meal, hotelValue = hotel) => ({
  id, source: 'tourvisor', price, date: '2026-09-12', nights: 9,
  meal: { name: meal === 'AI' ? 'AI' : 'RO', fullName: meal === 'AI' ? 'Всё включено' : 'Без питания' },
  roomType, placement: 'DBL', operator: { name: 'TEST OPERATOR' }, adults: 2, childs: 0,
  hotel: hotelValue
});
const standard = offer('offer-standard', 120000, 'STANDARD', 'AI');
const family = offer('offer-family', 125000, 'FAMILY', 'AI');
const roomOnly = offer('offer-ro', 90000, 'STANDARD', 'RO');
const thirdHotel = { id: 'third-hotel', name: 'Третий отель', country: { name: 'Турция' }, region: { name: 'Кемер' }, category: 4, picturelink: picture };
const fourthHotel = { id: 'fourth-hotel', name: 'Четвёртый отель', country: { name: 'Турция' }, region: { name: 'Сиде' }, category: 4, picturelink: picture };
const third = offer('offer-third', 130000, 'DELUXE', 'AI', thirdHotel);
const fourth = offer('offer-fourth', 135000, 'SUITE', 'AI', fourthHotel);
const items = [
  { ...hotel, price: 90000, tours: [roomOnly, standard, family] },
  { ...thirdHotel, price: third.price, tours: [third] },
  { ...fourthHotel, price: fourth.price, tours: [fourth] }
];

function storageFailureScript(mode) {
  if (!mode) return () => {};
  return mode => {
    const methods = ['getItem', 'setItem', 'removeItem'];
    for (const name of methods) {
      const original = Storage.prototype[name];
      Storage.prototype[name] = function (key, ...args) {
        if (/shortlist/i.test(String(key)) && (mode === 'blocked' || (mode === 'quota' && name === 'setItem'))) {
          throw new DOMException(mode === 'quota' ? 'Quota exceeded' : 'Storage blocked', mode === 'quota' ? 'QuotaExceededError' : 'SecurityError');
        }
        return original.call(this, key, ...args);
      };
    }
  };
}

async function openPage(browser, width, mode) {
  const context = await browser.newContext({ viewport: { width, height: 1000 } });
  if (mode) await context.addInitScript(storageFailureScript(mode), mode);
  const page = await context.newPage(), errors = [], posts = [];
  page.on('pageerror', error => errors.push(String(error)));
  page.on('request', request => { if (request.method() !== 'GET') posts.push([request.method(), request.url()]); });
  await page.route('**/*', route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== new URL(base).origin || request.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
    return route.continue();
  });
  assert.equal((await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' })).status(), 200);
  await page.waitForFunction(() => window.V2Results && window.V2TourController && window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.search3Ready === '1');
  await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
  return { context, page, errors, posts };
}

async function render(page, searchId, value = items) {
  await page.evaluate(({ searchId, value }) => {
    const freeze = item => { if (item && typeof item === 'object' && !Object.isFrozen(item)) { Object.values(item).forEach(freeze); Object.freeze(item); } return item; };
    window.dispatchEvent(new CustomEvent('v2:search-started', { detail: { searchId } }));
    window.V2Runtime.state.searchId = searchId;
    window.__shortlistSource = freeze(value);
    window.__shortlistCalls = [];
    const offers = value.flatMap(item => item.tours || []);
    window.V2Runtime.api = async (action, params) => {
      window.__shortlistCalls.push([action, params && params.tourId, window.V2Runtime.state.searchId]);
      const selected = offers.filter(item => String(item.id) === String(params && params.tourId));
      if (selected.length !== 1) throw Error('ambiguous or missing shortlist selection');
      if (action === 'tour') return selected[0];
      if (action === 'flights') return [{ isDefault: true, price: { value: selected[0].price }, forward: [window.__shortlistSegment], backward: [window.__shortlistSegment] }];
      throw Error('unexpected shortlist API action ' + action);
    };
    window.__shortlistSegment = { company: { name: 'Test airline' }, number: 'AB123', departure: { name: 'Москва', time: '09:30' }, arrival: { name: 'Анталья', time: '14:00' } };
    window.V2Results.render(window.__shortlistSource);
    window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId, items: window.__shortlistSource } }));
  }, { searchId, value });
}

const compact = text => String(text || '').replace(/\s+/g, ' ').trim();
async function openFilters(page, width) {
  if (width < 1025) await page.locator('.search3-mobile-filter-panel summary').click();
}

async function addOffer(page, offerId, keyboard = false) {
  const button = page.locator(`.search3-shortlist-toggle[data-offer-id="${offerId}"]`);
  await button.waitFor();
  assert.ok((await button.boundingBox()).height >= 44, 'save action retains a 44px target');
  if (keyboard) { await button.focus(); await button.press('Enter'); } else await button.click();
  await page.waitForFunction(id => document.querySelector(`.search3-shortlist-toggle[data-offer-id="${id}"]`)?.getAttribute('aria-pressed') === 'true', offerId);
  if (keyboard) {
    await page.waitForFunction(id => document.activeElement?.matches(`.search3-shortlist-toggle[data-offer-id="${id}"]`), offerId);
    assert.equal(await button.evaluate(node => node === document.activeElement), true, 'keyboard save retains focus after the owner redraws controls');
  }
}

async function checkJourney(browser, width) {
  const { context, page, errors, posts } = await openPage(browser, width);
  try {
    await render(page, 731);
    await openFilters(page, width);
    const meal = page.locator('.search3-meal-filter select');
    await meal.selectOption('meal:all-inclusive');
    const card = page.locator('#results [data-hotel-id="offer-hotel"]');
    assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['offer-standard'], 'RO90k is excluded and STANDARD AI120k remains the representative');
    await addOffer(page, 'offer-standard', true);
    await card.locator('.tour-more-toggle').press('Enter');
    assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['offer-standard', 'offer-family'], 'expanded projection contains only complete AI offers');
    await addOffer(page, 'offer-family');
    await addOffer(page, 'offer-third');
    const shortlist = page.locator('.search3-shortlist');
    assert.equal(await shortlist.locator('.search3-shortlist-item').count(), 3, 'shortlist accepts at most three exact snapshots');
    assert.equal(await shortlist.locator('[role="dialog"]').count(), 0, 'comparison remains inline and does not create a second focus trap');
    const fourthToggle = page.locator('.search3-shortlist-toggle[data-offer-id="offer-fourth"]');
    await fourthToggle.click();
    assert.equal(await fourthToggle.getAttribute('aria-pressed'), 'false', 'rejected fourth offer remains visibly unsaved');
    await page.waitForFunction(() => document.activeElement?.matches('.search3-shortlist-toggle[data-offer-id="offer-fourth"]'));
    assert.equal(await fourthToggle.evaluate(node => node === document.activeElement), true, 'max-three rejection retains focus on the rejected offer');
    assert.equal(await shortlist.locator('.search3-shortlist-item').count(), 3, 'fourth offer cannot evict or replace an existing snapshot');
    assert.match(compact(await shortlist.locator('.search3-shortlist-status').innerText()), /3|тр[её]х|максим/i, 'max-three limit is announced');

    const snapshots = await shortlist.locator('.search3-shortlist-item').evaluateAll(nodes => nodes.map(node => ({ offerId: node.dataset.offerId, text: node.textContent.replace(/\s+/g, ' ').trim() })));
    assert.match(snapshots[0].text, /STANDARD/); assert.match(snapshots[0].text, /Всё включено/); assert.match(snapshots[0].text.replace(/\s/g, ''), /120000₽/);
    assert.match(snapshots[1].text, /FAMILY/); assert.match(snapshots[1].text, /Всё включено/); assert.match(snapshots[1].text.replace(/\s/g, ''), /125000₽/);
    assert.doesNotMatch(snapshots.slice(0, 2).map(item => item.text).join(' '), /Без питания|90000/, 'excluded RO snapshot is never synthesized into comparison');
    assert.match(compact(await shortlist.innerText()), /сохран|историч/i, 'saved price is labelled as a historical snapshot');
    const storedItems = await page.evaluate(() => window.Search3Shortlist.items());
    const whitelist = ['schemaVersion', 'savedAt', 'source', 'hotelId', 'hotelName', 'country', 'region', 'searchId', 'offerId', 'date', 'nights', 'meal', 'room', 'placement', 'operator', 'adults', 'childs', 'observedPrice', 'currency'];
    assert.equal(storedItems.length, 3, 'public shortlist state agrees with the rendered comparison');
    assert.deepEqual(storedItems.slice(0, 2).map(item => [item.source, item.searchId, item.hotelId, item.offerId]), [
      ['tourvisor', '731', 'offer-hotel', 'offer-standard'], ['tourvisor', '731', 'offer-hotel', 'offer-family']
    ], 'record identity separates source/search/offer from hotel identity');
    assert.equal(storedItems.every(item => Object.keys(item).every(key => whitelist.includes(key))), true, 'storage contains whitelist fields only');
    assert.equal(/phone|comment|consent|raw|payload|token|cookie|yclid/i.test(JSON.stringify(storedItems)), false, 'personal, attribution and raw supplier data are never retained');
    await page.locator('#sortResults').selectOption('price');
    await page.evaluate(value => {
      window.V2Results.render(value.slice().reverse());
      window.dispatchEvent(new CustomEvent('v2:search-continued', { detail: { searchId: 731, items: value } }));
    }, items);
    assert.match((await shortlist.locator('.search3-shortlist-item[data-offer-id="offer-standard"]').innerText()).replace(/\s/g, ''), /120000₽/, 'sort/progressive rerender cannot replace the saved representative');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'three-item comparison does not overflow');
    const visual = await shortlist.locator('.search3-shortlist-item').first().evaluate(node => {
      const actions = node.querySelector('.search3-shortlist-item__actions');
      const select = node.querySelector('.search3-shortlist-select');
      const remove = node.querySelector('.search3-shortlist-remove');
      const price = node.querySelector('.search3-shortlist-item__price strong');
      const box = element => { const rect = element.getBoundingClientRect(); return { x: rect.x, y: rect.y, width: rect.width, height: rect.height }; };
      const selectStyle = getComputedStyle(select), removeStyle = getComputedStyle(remove), priceStyle = getComputedStyle(price);
      return {
        actions: box(actions), select: box(select), remove: box(remove),
        selectBackground: selectStyle.backgroundColor, selectColor: selectStyle.color,
        removeBackground: removeStyle.backgroundColor, priceColor: priceStyle.color
      };
    });
    assert.equal(visual.selectBackground, 'rgb(216, 61, 0)', 'current offer verification is the orange primary comparison action');
    assert.equal(visual.selectColor, 'rgb(255, 255, 255)', 'primary comparison action keeps readable white text');
    assert.equal(visual.priceColor, 'rgb(21, 27, 36)', 'saved price uses the primary ink hierarchy rather than link blue');
    assert.ok(visual.select.height >= 44 && visual.remove.height >= 44, 'comparison actions retain 44px targets');
    assert.equal(visual.removeBackground, 'rgba(0, 0, 0, 0)', 'remove stays visually secondary');
    if (width <= 430) {
      assert.ok(visual.select.width >= visual.actions.width - 3, 'mobile primary action spans the comparison card');
      assert.ok(visual.remove.y > visual.select.y, 'mobile remove action follows the primary action instead of competing beside it');
    } else {
      assert.ok(visual.remove.x > visual.select.x, 'desktop actions retain a compact primary/secondary row');
    }
    await shortlist.screenshot({ path: path.join(output, `shortlist-${width}-three.png`), animations: 'disabled' });

    await shortlist.locator('.search3-shortlist-item[data-offer-id="offer-third"] .search3-shortlist-remove').click();
    assert.equal(await shortlist.locator('.search3-shortlist-item').count(), 2, 'remove keeps the other exact snapshots');
    const persistedBefore = await page.evaluate(() => ({ key: window.Search3Shortlist.storageKey, value: localStorage.getItem(window.Search3Shortlist.storageKey) }));
    assert.ok(persistedBefore.key && persistedBefore.value, 'validated shortlist persists locally');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.V2Results && window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.search3Ready === '1');
    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    assert.equal(await page.locator('.search3-shortlist-item').count(), 2, 'two exact snapshots survive reload');
    assert.equal(await page.locator('.search3-shortlist-select:enabled').count(), 0, 'persisted snapshots never grant selection authority before a current projection');
    assert.equal(await page.locator('.search3-shortlist-select').first().evaluate(node => getComputedStyle(node).backgroundColor), 'rgb(238, 241, 246)', 'stale comparison action is visibly disabled rather than orange');
    assert.match(compact(await page.locator('.search3-shortlist').innerText()), /сохран|историч/i);

    const staleClear = page.locator('.search3-shortlist-clear');
    await staleClear.focus(); await staleClear.press('Enter');
    await page.waitForFunction(() => document.activeElement?.id === 'tourSearch');
    assert.equal(await page.locator('.search3-shortlist-item').count(), 0, 'keyboard clear removes stale snapshots before a current projection exists');
    assert.equal(await page.evaluate(() => document.activeElement?.id), 'tourSearch', 'stale clear moves focus to the visible search form instead of body');
    assert.equal(await page.evaluate(() => localStorage.getItem(window.Search3Shortlist.storageKey)), null, 'stale clear removes persisted snapshots');
    await page.evaluate(({ key, value }) => localStorage.setItem(key, value), persistedBefore);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.V2Results && window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    assert.equal(await page.locator('.search3-shortlist-item').count(), 2, 'fixture restores persisted snapshots for the remaining selection checks');

    await render(page, 731);
    assert.equal(await page.locator('.search3-shortlist-select:enabled').count(), 2, 'unique current-generation matches restore explicit selection authority');
    const choose = page.locator('.search3-shortlist-item[data-offer-id="offer-standard"] .search3-shortlist-select');
    await choose.focus(); await choose.press('Enter');
    const selected = page.locator('#selectedTour');
    await selected.locator('.search3-flight-continue button').waitFor();
    assert.equal(await selected.locator('.facts>div').filter({ hasText: 'Номер' }).locator('b').innerText(), 'STANDARD');
    assert.equal((await selected.locator('.selected-price').innerText()).replace(/\D/g, ''), '120000', 'selection rechecks and uses the exact current offer price');
    assert.deepEqual(await page.evaluate(() => window.__shortlistCalls), [['tour', 'offer-standard', 731], ['flights', 'offer-standard', 731]], 'shortlist selection uses only the existing detail/flights path');
    await selected.locator(':scope > .back-results').click();
    await page.waitForFunction(() => document.activeElement?.matches('[data-offer-id="offer-standard"],.direct-tour[data-tid="offer-standard"]'));
    assert.equal(await page.evaluate(() => document.activeElement?.dataset.offerId === 'offer-standard' || document.activeElement?.dataset.tid === 'offer-standard'), true, 'return restores focus to the exact offer source action');

    const repriced = items.map(item => item.id === 'offer-hotel' ? { ...item, tours: item.tours.map(value => value.id === 'offer-standard' ? { ...value, price: 99000, roomType: 'CHANGED' } : value) } : item);
    await render(page, 732, repriced);
    assert.equal(await page.locator('.search3-shortlist-select:enabled').count(), 0, 'reused offer IDs in a new search generation stay stale');
    const savedStandard = page.locator('.search3-shortlist-item[data-offer-id="offer-standard"]');
    assert.match((await savedStandard.innerText()).replace(/\s/g, ''), /120000₽/, 'progressive/repriced results never replace the saved representative');
    assert.doesNotMatch(await savedStandard.innerText(), /CHANGED|99000/);

    const duplicateHotel = { ...fourthHotel, id: 'duplicate-hotel', name: 'Дубликат ID' };
    const duplicate = offer('offer-standard', 88000, 'DUPLICATE', 'AI', duplicateHotel);
    await render(page, 731, items.concat([{ ...duplicateHotel, price: duplicate.price, tours: [duplicate] }]));
    assert.equal(await savedStandard.locator('.search3-shortlist-select').isEnabled(), false, 'duplicate offer ID across current projection cannot resolve to a substitute');
    assert.equal(await page.evaluate(() => JSON.stringify(window.__shortlistSource).includes('DUPLICATE')), true, 'ambiguous fixture is present rather than silently filtered out');

    await render(page, 731);
    await page.locator('.search3-shortlist-clear').focus();
    await page.locator('.search3-shortlist-clear').press('Enter');
    await page.waitForFunction(() => document.activeElement?.matches('.search3-shortlist-toggle[data-offer-id="offer-standard"]'));
    assert.equal(await page.locator('.search3-shortlist-item').count(), 0, 'keyboard clear removes every snapshot');
    assert.equal(await page.evaluate(() => document.activeElement?.dataset.offerId), 'offer-standard', 'current-projection clear returns focus to the exact source toggle');
    assert.equal(await page.evaluate(() => { const value = localStorage.getItem(window.Search3Shortlist.storageKey); return value === null || JSON.parse(value).length === 0 || JSON.parse(value).items?.length === 0; }), true, 'clear deletes or empties persisted records');
    assert.equal(await page.evaluate(() => JSON.stringify(window.__shortlistSource[0].tours.map(item => [item.id, item.price, item.roomType]))), JSON.stringify(items[0].tours.map(item => [item.id, item.price, item.roomType])), 'source projection remains immutable');
    assert.deepEqual(posts, [], 'shortlist never sends POST or a real lead');
    assert.deepEqual(errors, [], 'shortlist journey has no page errors');
    return { width, exactOffers: ['offer-standard', 'offer-family'], prices: [120000, 125000], max: 3, reload: true, staleBlocked: true, duplicateBlocked: true, keyboard: true, focusReturn: true, posts: 0 };
  } finally { await context.close(); }
}

async function checkStorageFailure(browser, width, mode) {
  const { context, page, errors, posts } = await openPage(browser, width, mode);
  try {
    await render(page, 731);
    await openFilters(page, width);
    await page.locator('.search3-meal-filter select').selectOption('meal:all-inclusive');
    await addOffer(page, 'offer-standard', true);
    assert.equal(await page.locator('.search3-shortlist-item').count(), 1, `${mode}: in-memory fallback retains the explicit snapshot`);
    assert.match(compact(await page.locator('.search3-shortlist-status').innerText()), /не сохран|только.*сеанс|хранилищ/i, `${mode}: non-persistence is explicit`);
    assert.equal(await page.evaluate(() => window.Search3Shortlist.persistent), false, `${mode}: public state reports fallback mode`);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.V2Results && window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    assert.equal(await page.locator('.search3-shortlist-item').count(), 0, `${mode}: in-memory snapshot is truthfully absent after reload`);
    await render(page, 732);
    assert.equal(await page.locator('#results .hotel-card').count(), 3, `${mode}: storage failure never blocks a later search`);
    assert.deepEqual(posts, []); assert.deepEqual(errors, []);
    return { width, mode, inMemory: true, explicit: true, reloadDropsFallback: true, searchUsable: true, posts: 0 };
  } finally { await context.close(); }
}

async function checkCorruptStorage(browser, width) {
  const { context, page, errors, posts } = await openPage(browser, width);
  try {
    const key = await page.evaluate(() => window.Search3Shortlist.storageKey);
    assert.ok(key, 'storage key is exposed for deterministic schema verification');
    await page.evaluate(key => localStorage.setItem(key, '{not-json'), key);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.search3Ready === '1');
    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    assert.equal(await page.locator('.search3-shortlist-item').count(), 0, 'corrupt storage is discarded, never partially trusted');
    assert.match(compact(await page.locator('.search3-shortlist-status').innerText()), /повреж|сброш|не удалось/i, 'corrupt reset is explained');
    await render(page, 731);
    await openFilters(page, width);
    await page.locator('.search3-meal-filter select').selectOption('meal:all-inclusive');
    await addOffer(page, 'offer-standard');
    assert.equal(await page.locator('#results .hotel-card').count(), 3, 'corrupt storage cannot break search rendering');
    assert.deepEqual(posts, []); assert.deepEqual(errors, []);
    return { width, corruptDiscarded: true, searchUsable: true, posts: 0 };
  } finally { await context.close(); }
}

(async () => {
  const browser = await chromium.launch({ headless: true }), evidence = [];
  try {
    for (const width of [375, 1440]) {
      evidence.push(await checkJourney(browser, width));
      evidence.push(await checkStorageFailure(browser, width, 'blocked'));
      evidence.push(await checkStorageFailure(browser, width, 'quota'));
      evidence.push(await checkCorruptStorage(browser, width));
    }
  } finally { await browser.close(); }
  fs.writeFileSync(path.join(output, 'shortlist-contract.json'), JSON.stringify(evidence, null, 2));
  console.log('SEARCH3_SHORTLIST_OK ' + JSON.stringify(evidence));
})().catch(error => { console.error(error); process.exitCode = 1; });
