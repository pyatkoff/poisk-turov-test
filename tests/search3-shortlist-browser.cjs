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
const sourceSha = process.env.SEARCH3_SOURCE_SHA;
assert.match(sourceSha || '', /^[a-f0-9]{40}$/, 'comparison geometry requires exact source provenance');

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

async function discloseMatchingOffers(page) {
  const card = page.locator('#results [data-hotel-id="offer-hotel"]');
  assert.equal(await card.locator('.hotel-trip-summary').count(), 1, 'matching multi-offer hotel starts with an aggregate');
  assert.equal(await card.locator('.direct-tour,.search3-shortlist-toggle').count(), 0, 'an undisclosed aggregate cannot select or save a fabricated representative');
  assert.equal((await card.locator('.hotel-price').innerText()).replace(/\s/g, ''), 'от120000₽', 'RO90k is excluded from the matching aggregate minimum');
  const disclosure = card.locator('.tour-more-toggle');
  assert.ok((await disclosure.boundingBox()).height >= 44, 'offer disclosure retains a 44px target');
  await disclosure.focus();
  await disclosure.press('Enter');
  assert.equal(await disclosure.getAttribute('aria-expanded'), 'true', 'keyboard disclosure exposes its expanded state');
  assert.equal(await disclosure.evaluate(node => node === document.activeElement), true, 'disclosure retains focus before saving a particular offer');
  assert.deepEqual(await card.locator('.direct-tour').evaluateAll(nodes => nodes.map(node => node.dataset.tid)), ['offer-standard', 'offer-family'], 'explicit expansion contains only the exact matching AI offers, representative first');
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

async function openComparison(page, width, options = {}) {
  const shortlist = page.locator('.search3-shortlist');
  const body = shortlist.locator('.search3-shortlist__body');
  const disclosure = shortlist.locator('.search3-shortlist-disclosure');
  if (width <= 600) {
    assert.equal(await disclosure.isVisible(), true, 'mobile comparison exposes one explicit disclosure');
    assert.ok((await disclosure.boundingBox()).height >= 44, 'mobile comparison disclosure retains a 44px target');
    if (options.assertCollapsed) {
      assert.equal(await disclosure.getAttribute('aria-expanded'), 'false', 'mobile comparison starts collapsed');
      assert.equal(await body.isVisible(), false, 'collapsed mobile comparison does not dominate the results flow');
    }
    if ((await disclosure.getAttribute('aria-expanded')) !== 'true') await disclosure.click();
    assert.equal(await disclosure.getAttribute('aria-expanded'), 'true', 'mobile comparison expands only on explicit request');
    assert.equal(await body.isVisible(), true, 'explicit mobile disclosure reveals comparison content');
    const geometry = await shortlist.locator('.search3-shortlist-item').evaluateAll(nodes => nodes.map(node => {
      const rect = node.getBoundingClientRect();
      const parent = node.parentElement.getBoundingClientRect();
      return { left: rect.left, right: rect.right, width: rect.width, parentLeft: parent.left, parentRight: parent.right, parentWidth: parent.width };
    }));
    geometry.forEach(item => {
      assert.ok(item.width >= item.parentWidth - 3, 'opened mobile comparison cards use the full comparison width');
      assert.ok(item.left >= item.parentLeft - 2 && item.right <= item.parentRight + 2, 'opened mobile cards are not clipped horizontally');
    });
  } else {
    assert.equal(await disclosure.isVisible(), false, 'desktop comparison stays expanded without a redundant mobile disclosure');
    assert.equal(await body.isVisible(), true, 'desktop comparison remains directly visible');
  }
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'comparison creates no page-level horizontal overflow');
}

async function checkComparisonGeometry(page, width, count) {
  const shortlist = page.locator('.search3-shortlist'), disclosure = shortlist.locator('.search3-shortlist-disclosure');
  const wasClosed = width <= 600 && await disclosure.getAttribute('aria-expanded') === 'false';
  await openComparison(page, width);
  await page.evaluate(() => document.fonts.ready);
  const geometry = await shortlist.locator('.search3-shortlist__items').evaluate(list => {
    const rect = node => { const r = node.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right }; };
    return { grid: rect(list), gap: parseFloat(getComputedStyle(list).columnGap), cards: [...list.children].map(node => ({ ...rect(node), actions: [...node.querySelectorAll('button')].map(rect) })) };
  });
  assert.equal(geometry.cards.length, count);
  for (const card of geometry.cards) {
    assert.ok(card.x >= geometry.grid.x - 1 && card.right <= geometry.grid.right + 1, 'every comparison card stays inside its grid');
    assert.ok(card.actions.every(action => action.height >= 44 && action.x >= card.x && action.right <= card.right), 'actions remain visible, contained and touch-sized');
  }
  if (count === 1) {
    assert.ok(geometry.cards[0].width <= 641, 'one saved offer stays bounded instead of becoming a giant card');
    assert.ok(geometry.cards[0].width >= Math.min(640, geometry.grid.width) - 2, 'one saved offer uses the available bounded width');
  } else if (width <= 600) {
    assert.ok(geometry.cards.every(card => Math.abs(card.width - geometry.grid.width) < 2), 'mobile cards use one full-width column');
    assert.ok(geometry.cards.every((card, index, cards) => !index || card.y >= cards[index - 1].y + cards[index - 1].height), 'mobile comparison stacks cards without overlap');
  } else if (count === 2 && width >= 768 || count === 3 && width >= 1200) {
    assert.ok(geometry.cards.every(card => Math.abs(card.y - geometry.cards[0].y) < 2), 'available desktop columns stay aligned');
    assert.ok(Math.abs(geometry.cards.reduce((sum, card) => sum + card.width, 0) + geometry.gap * (count - 1) - geometry.grid.width) < 3, 'saved cards fill the row without a phantom empty column');
  }
  await shortlist.screenshot({ path: path.join(output, `shortlist-${width}-${['', 'one', 'two', 'three'][count]}.png`), animations: 'disabled' });
  if (wasClosed) await disclosure.click();
  return { sourceSha, width, count, ...geometry };
}
async function checkJourney(browser, width) {
  const { context, page, errors, posts } = await openPage(browser, width);
  try {
    await render(page, 731);
    await openFilters(page, width);
    const meal = page.locator('.search3-meal-filter select');
    await meal.selectOption('meal:all-inclusive');
    await discloseMatchingOffers(page);
    await addOffer(page, 'offer-standard', true);
    const one = await checkComparisonGeometry(page, width, 1);
    await addOffer(page, 'offer-family');
    const two = await checkComparisonGeometry(page, width, 2);
    await addOffer(page, 'offer-third');
    await openComparison(page, width, { assertCollapsed: true });
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
    const three = await checkComparisonGeometry(page, width, 3);

    await shortlist.locator('.search3-shortlist-item[data-offer-id="offer-third"] .search3-shortlist-remove').click();
    assert.equal(await shortlist.locator('.search3-shortlist-item').count(), 2, 'remove keeps the other exact snapshots');
    const persistedBefore = await page.evaluate(() => ({ key: window.Search3Shortlist.storageKey, value: localStorage.getItem(window.Search3Shortlist.storageKey) }));
    assert.ok(persistedBefore.key && persistedBefore.value, 'validated shortlist persists locally');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.V2Results && window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.search3Ready === '1');
    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    await openComparison(page, width);
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
    await openComparison(page, width);
    assert.equal(await page.locator('.search3-shortlist-item').count(), 2, 'fixture restores persisted snapshots for the remaining selection checks');

    await render(page, 731);
    await openComparison(page, width);
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
    await openComparison(page, width);
    await page.locator('.search3-shortlist-clear').focus();
    await page.locator('.search3-shortlist-clear').press('Enter');
    await page.waitForFunction(() => document.activeElement?.matches('.search3-shortlist-toggle[data-offer-id="offer-standard"]'));
    assert.equal(await page.locator('.search3-shortlist-item').count(), 0, 'keyboard clear removes every snapshot');
    assert.equal(await page.evaluate(() => document.activeElement?.dataset.offerId), 'offer-standard', 'current-projection clear returns focus to the exact source toggle');
    assert.equal(await page.evaluate(() => { const value = localStorage.getItem(window.Search3Shortlist.storageKey); return value === null || JSON.parse(value).length === 0 || JSON.parse(value).items?.length === 0; }), true, 'clear deletes or empties persisted records');
    assert.equal(await page.evaluate(() => JSON.stringify(window.__shortlistSource[0].tours.map(item => [item.id, item.price, item.roomType]))), JSON.stringify(items[0].tours.map(item => [item.id, item.price, item.roomType])), 'source projection remains immutable');
    assert.deepEqual(posts, [], 'shortlist never sends POST or a real lead');
    assert.deepEqual(errors, [], 'shortlist journey has no page errors');
    return { sourceSha, width, geometry: [one, two, three], exactOffers: ['offer-standard', 'offer-family'], prices: [120000, 125000], max: 3, reload: true, staleBlocked: true, duplicateBlocked: true, keyboard: true, focusReturn: true, posts: 0 };
  } finally { await context.close(); }
}

async function checkIntermediateGeometry(browser, width) {
  const { context, page, errors, posts } = await openPage(browser, width);
  try {
    await render(page, 731);
    await openFilters(page, width);
    await page.locator('.search3-meal-filter select').selectOption('meal:all-inclusive');
    await discloseMatchingOffers(page);
    const geometry = [];
    for (const [index, id] of ['offer-standard', 'offer-family', 'offer-third'].entries()) {
      await addOffer(page, id);
      geometry.push(await checkComparisonGeometry(page, width, index + 1));
    }
    assert.deepEqual(await page.evaluate(() => window.__shortlistCalls), [], 'comparison geometry never requests tour or flight data');
    assert.deepEqual(posts, []); assert.deepEqual(errors, []);
    return { sourceSha, width, geometry, posts: 0 };
  } finally { await context.close(); }
}
async function checkSearchRecovery(browser, width) {
  const { context, page, errors, posts } = await openPage(browser, width);
  const supplierRequests = [];
  const record = request => {
    const action = new URL(request.url()).searchParams.get('action');
    if (['search_start', 'search_status', 'search_results', 'tour', 'flights'].includes(action)) supplierRequests.push(action);
  };
  page.on('request', record);
  try {
    const date = offset => { const value = new Date(); value.setUTCDate(value.getUTCDate() + offset); return value.toISOString().slice(0, 10); };
    const query = new URLSearchParams({ from: '1', country: '4', dateFrom: date(30), dateTo: date(33), daysFrom: '7', daysTill: '9', count_people: '2', child_count: '2', arrival: '2', region: '99', subregion: '101', hotel: '555', hotel_type: '2', stars: '5', rating: '4', food: '7', price_from: '100000', price_till: '200000', onlyDirect: '1', onlyCharter: '1', operator: '88', utm_campaign: 'original', yclid: 'original-click' });
    query.append('child_age[]', '0'); query.append('child_age[]', '17');
    query.append('hotel_service[]', '11'); query.append('hotel_service[]', '22');
    const recoveryHotel = { ...hotel, id: '555' };
    const recoveryItems = [{ ...recoveryHotel, price: 120000, tours: [standard, family].map(value => ({ ...value, hotel: recoveryHotel, date: query.get('dateFrom'), childs: 2, operator: { id: 88, name: 'TEST OPERATOR' } })) }];
    const initial = await page.evaluate(async ({ query, items }) => {
      history.replaceState(null, '', location.pathname + '?' + query);
      window.V2SearchLifecycle.hydrateUrlState();
      window.__recoveryFixtureCalls = [];
      window.V2Runtime.api = async (action, params) => {
        window.__recoveryFixtureCalls.push(action);
        if (action === 'search_start') return { searchId: 981 };
        if (action === 'search_status') return { progress: 100, status: 'complete' };
        if (action === 'search_results') return items;
        throw new Error('unexpected recovery fixture action: ' + action);
      };
      await window.V2SearchLifecycle.submit();
      return window.V2SearchLifecycle.snapshot;
    }, { query: query.toString(), items: recoveryItems });
    await page.waitForFunction(() => window.V2SearchLifecycle.searchId === 981 && !window.V2SearchLifecycle.pending && document.querySelector('#results .hotel-card'));
    assert.deepEqual(await page.evaluate(() => window.__recoveryFixtureCalls), ['search_start', 'search_status', 'search_results'], 'fixture enters the actual validated lifecycle once');
    await page.locator('#results .tour-more-toggle').click();
    await addOffer(page, 'offer-standard');
    const saved = (await page.evaluate(() => window.Search3Shortlist.items()))[0];
    const storedQuery = new URLSearchParams(saved.searchQuery);
    assert.ok(saved.searchQuery, 'validated search conditions are saved with the exact offer');
    assert.equal(storedQuery.get('operator'), '88', 'explicit secondary operator survives recovery without becoming a default primary criterion');
    assert.deepEqual(storedQuery.getAll('child_age[]'), ['0', '17']);
    assert.deepEqual(storedQuery.getAll('hotel_service[]'), ['11', '22']);
    assert.equal(/utm_|yclid|phone|consent|token|cookie|payload/i.test(saved.searchQuery), false, 'persistent search conditions exclude attribution, contacts and raw data');
    await page.locator('#tourSearch [name=count_people]').selectOption('3');
    await openComparison(page, width);
    const restore = page.locator('.search3-shortlist-restore');
    assert.equal(await restore.textContent(), 'Восстановить поиск');
    assert.equal((await page.evaluate(() => window.Search3Shortlist.items()))[0].searchQuery, saved.searchQuery, 'editing the current form cannot overwrite the saved search');
    const geometry = await restore.evaluate(node => { const r = node.getBoundingClientRect(); return { width: r.width, height: r.height, overflow: document.documentElement.scrollWidth > innerWidth + 2 }; });
    assert.ok(geometry.height >= 44 && geometry.width > 0);
    assert.equal(geometry.overflow, false);
    await page.locator('.search3-shortlist').screenshot({ path: path.join(output, `shortlist-search-recovery-${width}.png`), animations: 'disabled' });
    await page.evaluate(() => {
      const url = new URL(location.href); url.searchParams.set('utm_campaign', 'current'); url.searchParams.set('yclid', 'current-click'); history.replaceState(null, '', url);
    });
    // The deliberate fixture submit above exercises normal search listeners.
    // openPage aborts their POSTs; measure the recovery action separately.
    const bootstrapBlockedPosts = posts.length;
    assert.equal(posts.some(([, url]) => /lead/i.test(new URL(url).pathname)), false, 'fixture setup never invokes lead delivery');
    await restore.focus(); await restore.press('Enter');
    await page.waitForURL(url => url.searchParams.get('search3_restore') === '1');
    await page.waitForFunction(() => window.Search3Shortlist && window.V2SearchLifecycle && document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    await page.waitForFunction(() => document.activeElement === document.querySelector('#tourSearch [name=from]'));
    const restored = await page.evaluate(() => ({ params: window.V2SearchLifecycle.params(), pending: window.V2SearchLifecycle.pending, searchId: window.V2SearchLifecycle.searchId }));
    assert.deepEqual(restored.params, initial, 'canonical hydration recovers the exact original primary/advanced values and child ages');
    assert.equal(restored.searchId, 0); assert.equal(restored.pending, false, 'restoring conditions does not automatically submit a supplier search');
    assert.equal(new URL(page.url()).origin, new URL(base).origin);
    assert.equal(new URL(page.url()).pathname, new URL(base + '/poisk-turov/').pathname, 'recovery remains on the same isolated search route');
    assert.equal(new URL(page.url()).searchParams.get('utm_campaign'), 'current');
    assert.equal(new URL(page.url()).searchParams.get('yclid'), 'current-click', 'navigation preserves current attribution without replaying the saved click');
    assert.equal(await page.locator('#results .direct-tour').count(), 0, 'a restored form cannot select the historical offer');
    const rawSaved = await page.evaluate(() => ({ key: window.Search3Shortlist.storageKey, items: window.Search3Shortlist.items() }));
    const past = new URLSearchParams(saved.searchQuery); past.set('dateFrom', '2000-01-01'); past.set('dateTo', '2000-01-02');
    await page.evaluate(({ rawSaved, query }) => { rawSaved.items[0].searchQuery = query; localStorage.setItem(rawSaved.key, JSON.stringify(rawSaved.items)); }, { rawSaved, query: past.toString() });
    await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
    await openComparison(page, width); await page.locator('.search3-shortlist-restore').click();
    await page.waitForFunction(() => document.activeElement === document.querySelector('#tourSearch [name=dateFrom]') && document.activeElement.getAttribute('aria-invalid') === 'true');
    assert.equal(await page.locator('#tourSearch [name=dateFrom]').inputValue(), '2000-01-01', 'past dates remain explicit for user correction');
    assert.equal(await page.evaluate(() => window.V2SearchLifecycle.searchId), 0, 'past saved dates cannot initiate a search');
    for (const invalid of [saved.searchQuery + '&phone=not-allowed', saved.searchQuery + '&country=5', 'https://example.invalid/?from=1', 'x'.repeat(4097)]) {
      await page.evaluate(({ rawSaved, invalid }) => { rawSaved.items[0].searchQuery = invalid; localStorage.setItem(rawSaved.key, JSON.stringify(rawSaved.items)); }, { rawSaved, invalid });
      await page.goto(base + '/poisk-turov/', { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(() => window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');
      await openComparison(page, width);
      assert.equal(await page.locator('.search3-shortlist-item').count(), 1, 'invalid recovery metadata preserves the historical comparison record');
      assert.equal(await page.locator('.search3-shortlist-restore').count(), 0, 'invalid recovery query cannot create navigation or search authority');
      assert.equal((await page.evaluate(() => window.Search3Shortlist.items()))[0].searchQuery, undefined);
    }
    assert.deepEqual(supplierRequests, []);
    assert.deepEqual(posts.slice(bootstrapBlockedPosts), [], 'restoring saved conditions never initiates provider, observation or lead POSTs');
    assert.deepEqual(errors, []);
    return { sourceSha, width, restore: true, exactConditions: true, childAges: [0, 17], draftPreserved: true, attributionNotStored: true, currentAttributionPreserved: true, pastDateFocus: true, invalidQueries: 4, geometry, bootstrapBlockedPosts, supplierRequests: 0, posts: 0 };
  } finally { page.off('request', record); await context.close(); }
}

async function checkStorageFailure(browser, width, mode) {
  const { context, page, errors, posts } = await openPage(browser, width, mode);
  try {
    await render(page, 731);
    await openFilters(page, width);
    await page.locator('.search3-meal-filter select').selectOption('meal:all-inclusive');
    await discloseMatchingOffers(page);
    await addOffer(page, 'offer-standard', true);
    await openComparison(page, width);
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
    await discloseMatchingOffers(page);
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
      evidence.push(await checkSearchRecovery(browser, width));
      evidence.push(await checkStorageFailure(browser, width, 'blocked'));
      evidence.push(await checkStorageFailure(browser, width, 'quota'));
      evidence.push(await checkCorruptStorage(browser, width));
    }
    for (const width of [600, 601, 768, 1024]) evidence.push(await checkIntermediateGeometry(browser, width));
  } finally { await browser.close(); }
  fs.writeFileSync(path.join(output, 'shortlist-contract.json'), JSON.stringify(evidence, null, 2));
  console.log('SEARCH3_SHORTLIST_OK ' + JSON.stringify(evidence));
})().catch(error => { console.error(error); process.exitCode = 1; });
