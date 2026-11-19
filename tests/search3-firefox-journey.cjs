'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');

module.exports = async function firefoxJourney({driver, origin, base, evidence, documentGuard}) {
  const url = new URL(base + 'visual-search/?scenario=journey', origin).href;
  const card = '#hotel-900002';
  const stars = '#quick-stars [data-value="4"]';
  const family = '.hotel-room-card[data-room="Семейный номер"]';
  const pair = 'input[name="flight-pair"][value="1"]';
  const contact = '#prototype-lead-form';
  const phone = contact + ' [name="phone"]';
  const total = '#modal-footer .footer-total > strong';
  const selected = card + ' .current-card-selection';
  const frames = [];
  const text = async selector => (await driver.text(selector)).replace(/\s+/g, ' ').trim();

  async function align(selector, block) {
    await driver.evaluate(`document.querySelector(${JSON.stringify(selector)}).scrollIntoView({block:${JSON.stringify(block)}, behavior:"instant"})`);
  }

  async function frame(name) {
    await documentGuard(name);
    const file = name + '.png';
    await driver.screenshot(path.join(evidence, file));
    frames.push(file);
  }

  if (await driver.evaluate('location.href') !== url) await driver.go(url);
  await driver.wait('.search-submit:not(:disabled)');
  await documentGuard('initial');
  assert.equal(await driver.count('.hotel-card'), 0, 'Blank search initially');
  await driver.click(stars);
  await driver.wait(stars + '[aria-pressed="true"]');
  assert.equal(await driver.count('#cards:empty'), 1);
  await frame('01-search');

  await driver.click('.search-submit');
  await driver.wait(card + ' [data-card-entry]');
  assert.equal(await driver.count('.hotel-card'), 2);
  const names = await driver.evaluate(`Array.from(document.querySelectorAll('.hotel-card-name'), el => el.textContent.trim()).sort()`);
  assert.deepEqual(names, ['Coast Club · демо', 'Palm Garden · демо']);
  assert.equal(await text(card + ' .starting-price strong'), '154 800 ₽');
  assert.equal(await text(card + ' .starting-from'), 'от');
  assert.equal(await text(card + ' [data-card-entry]'), 'Номера и цены');
  await align(card, 'start');
  await frame('02-results');

  await driver.click(card + ' [data-card-entry]');
  const chooseRoom = family + ' [data-action="select-hotel-offer"]';
  await driver.wait(chooseRoom);
  assert.match(await text(family), /Всё включено/i);
  assert.equal(await text(family + ' .hotel-room-price > strong'), '169 500 ₽');
  await align(family, 'start');
  await driver.wait('[data-target="hotel-rooms-heading"][aria-current="location"]');
  assert.equal(await driver.evaluate('document.querySelector("#hotel-detail-offers").getClientRects().length'), 0);
  await frame('03-rooms');

  await driver.click(chooseRoom);
  await driver.wait(contact);
  assert.equal(await text(total), '169 500 ₽');
  await driver.click('[data-action="contact-flights"]');
  await driver.wait(pair);
  await driver.click(pair);
  await driver.wait(pair + ':checked');
  assert.match(await text('[data-flight-index="1"] .flight-comparison'), /1\s?300.*06:05.*20\s?кг/);
  assert.match(await text('#flight-price-change'), /1 300 ₽.*20 кг.*Шереметьево.*06:05/);
  await align('[data-flight-index="1"]', 'center');
  await frame('04-flights');

  await driver.click('[data-action="apply-flight"]');
  await driver.wait(contact);
  const heading = await text('.application-review-heading');
  assert.match(heading, /Palm Garden/);
  assert.match(heading, /Москва.*Кемер.*Турция/);
  assert.match(heading, /Семейный номер.*Всё включено/);
  assert.equal(await text(total), '170 800 ₽');
  await frame('05-contact');

  await driver.fill(phone, '70000000000');
  assert.equal(await driver.value(phone), '70000000000');
  await driver.click('#modal-back');
  await driver.wait('#hotel-detail-offers');
  assert.equal(await text('#hotel-detail-min'), '170 800 ₽');
  await driver.click('#hotel-detail-offers');
  await driver.wait(contact);
  assert.equal(await driver.value(phone), '70000000000');
  assert.equal(await text(total), '170 800 ₽');
  await driver.click('[data-action="close-modal"]');
  await driver.wait(selected + ' [data-card-selection]');
  assert.equal(await driver.count('.hotel-card'), 2);
  assert.equal(await driver.evaluate('new URL(location.href).searchParams.get("stars")'), '4');
  assert.equal(await text(card + ' .starting-price strong'), '154 800 ₽');
  assert.equal(await text(selected + ' .card-selection-price strong'), '170 800 ₽');
  assert.match(await text(selected), /Семейный номер.*Всё включено/);
  assert.match(await text(selected), /06:05/);
  await driver.click(selected + ' [data-card-selection]');
  await driver.wait('#detail-total');
  assert.equal(await text('#detail-total'), '170 800 ₽');
  assert.match(await text('#modal-footer .tour-price-history'), /Этот тур в поиске 169 500 ₽/i);
  await frame('06-return');
  await driver.click('#modal-footer [data-action="confirm-tour"]');
  await driver.wait(contact);
  assert.equal(await driver.value(phone), '70000000000');
  assert.equal(await text(total), '170 800 ₽');
  await documentGuard('restored-contact');
  return {frames, hotels: names, listing: 154800, room: 169500, flightSupplement: 1300,
    total: 170800, draftRestored: true, submitted: false};
};
