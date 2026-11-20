'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {entry, openReplay, liveEntry, measureEngines, emitFirstOpenFile} = require('./search3-visual-first-open.cjs');
const {until} = require('./search3-visual-first-tours.cjs');
const {trip, profile} = require('./search3-visual-live-fixture.cjs');
const {familyTransport, offers} = require('./search3-visual-family-250.cjs');
const checkHotelMatches = require('./search3-visual-hotel-match.cjs');

async function checkPickers(driver, engine, size, root, html, output) {
  const replay = await openReplay(root, html, 0, familyTransport);
  const query = selector => 'document.querySelector(' + JSON.stringify(selector) + ')';
  const visible = selector => query(selector) + '?.getClientRects().length > 0';
  const wait = async expression => assert.ok(await until(driver, expression, 12000), expression);
  async function click(selector) {
    await wait(visible(selector));
    await driver.click(selector);
  }
  async function input(selector, value) {
    await wait(visible(selector));
    await driver.evaluate(`(() => {
      const field = ${query(selector)};
      field.value = ${JSON.stringify(value)};
      field.dispatchEvent(new Event('input', {bubbles:true}));
    })()`);
  }

  try {
    const address = replay.origin + entry + '?' + new URLSearchParams({...trip, adults:2, ages:'7'});
    await driver.go(address);
    await wait('window.__firstOpen?.readyMs > 0');
    await click('#quick-meal');
    await input('#meal-query', 'bb');
    const meals = await driver.evaluate("Array.from(document.querySelectorAll('.meal-option:not([hidden]) input'), e => e.value).filter(Boolean)");
    assert.deepEqual(meals, ['Завтраки']);
    await driver.screenshot(path.join(output, engine + '-meal.png'));
    await click('.meal-option:not([hidden]) input[value="Завтраки"]');
    await click('[data-action="apply-meals"]');
    await wait(query('#meal-label') + '.textContent.trim() === "Завтраки"');
    replay.allowClick();
    await click('.search-submit:not(:disabled)');
    const hotel = '#hotel-' + profile.id;
    await wait(visible(hotel));
    await wait('!' + query('.search-submit') + '.disabled');
    await click(size[0] === 430 ? '#compact-search .secondary[data-action="top"]' : '#applied-search [data-action="edit-search"]');
    if (!await driver.evaluate(visible('#quick-budget'))) {
      await click('#search-options-toggle[aria-expanded="false"]');
    }
    await click('#quick-budget');
    await input('#budget-max', '2000');
    await wait(visible('#budget-recovery:not([hidden])'));
    const minimum = Math.min(...offers.filter(o => o.meal.name === 'BB').map(o => o.price));
    const proposed = await driver.evaluate('Number(' + query('#budget-recovery') + '.dataset.value)');
    assert.equal(proposed, minimum);
    await driver.screenshot(path.join(output, engine + '-budget.png'));
    const searches = () => replay.delivery.requests.filter(action => action === 'tv_search').length;
    const started = searches();
    await click('#budget-recovery');
    await wait(visible(hotel) + ' && ' + query('#search-form') + '.hidden && new URLSearchParams(location.search).get("max") === "' + minimum + '"');
    const applied = await driver.evaluate('Object.fromEntries(new URLSearchParams(location.search))');
    assert.equal(applied.meals, 'Завтраки');
    assert.equal(applied.ages, '7');
    assert.equal(applied.adults, '2');
    assert.equal(searches(), started);
    await driver.go(replay.origin + entry + '?' + new URLSearchParams({...trip, adults:2, ages:'1'}));
    await wait('window.__firstOpen?.readyMs > 0');
    await click('#search-form [data-action="dates"]');
    const units = '#modal .date-choice-tools .calendar-units';
    await wait(visible(units));
    const calendarPriceLabel = await driver.evaluate(query(units) + '.textContent.trim()');
    assert.match(calendarPriceLabel, /доплата за младенца уточняется/i);
    await driver.screenshot(path.join(output, engine + '-infant.png'));
    assert.deepEqual(replay.blocked, []);
    return {meals, proposed, appliedMaximum:applied.max, retainedMeal:applied.meals, additionalSearches:searches()-started, calendarPriceLabel};
  } finally {
    await replay.close();
  }
}

async function main() {
  const root = fs.realpathSync(process.argv[2]), output = path.resolve(process.argv[3]);
  fs.mkdirSync(output, {recursive:true});
  const {source, html} = liveEntry(root);
  const engines = await measureEngines(root, html, async (driver, engine, size) => {
    const pickers = await checkPickers(driver, engine, size, root, html, output);
    const hotelMatches = await checkHotelMatches(driver, engine, size, root, html, output);
    return {...pickers, hotelMatches};
  });
  fs.writeFileSync(path.join(output, 'pickers.json'), JSON.stringify({source, engines}, null, 2));
  for (const name of ['pickers.json', 'chromium-1280-meal.png', 'chromium-1280-budget.png', 'chromium-1280-infant.png', 'firefox-rdm-430-meal.png', 'firefox-rdm-430-budget.png', 'firefox-rdm-430-infant.png', 'chromium-1280-hotel-matches.png', 'firefox-rdm-430-hotel-matches.png', 'chromium-1280-saved-filters.png', 'firefox-rdm-430-saved-filters.png']) {
    emitFirstOpenFile(output, name, 'SEARCH3_PICKERS_FRAME');
  }
}

if (require.main === module) main().catch(error => { console.error(error); process.exitCode = 1; });
