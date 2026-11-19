'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');

async function compareLongRoomNames({driver, url, evidence, documentGuard}) {
  const prefix = 'Семейный люкс с отдельной гостиной и просторной террасой с видом на ';
  const rates = [
    {room: prefix + 'сад', price: 154800, key: 'long-room-garden'},
    {room: prefix + 'море', price: 155100, key: 'long-room-sea'},
  ];
  await driver.go(url);
  await driver.wait('.search-submit:not(:disabled)');
  await driver.evaluate(`(async () => {
    const data = window.AnyTourPrototypeData, search = data.initialSearch;
    let hotel;
    await data.search(search, event => {
      if (event.type === 'results') hotel = event.hotels.find(item => item.id === 900002);
    });
    const template = hotel.offers[0], entries = [];
    hotel.offers = [];
    for (const {room, price, key} of ${JSON.stringify(rates)}) {
      const offer = {...template, room, total: price, key};
      const quote = await data.quote(offer);
      entries.push({offerKey: key, quote, flights: (await data.flights(quote)).slice(0, 1)});
      hotel.offers.push(offer);
    }
    window.AnyTourRecording.load({schemaVersion: 1, kind: 'demo', currency: 'RUB',
      capturedAt: new Date().toISOString(), countryName: 'Турция', source: 'Firefox room comparison',
      search, hotels: [hotel], entries});
    await data.setScenario('recorded');
  })()`);
  await driver.click('.search-submit');
  await driver.click('#hotel-900002 [data-action="hotel-details"]');
  const roomsTab = '[data-target="hotel-rooms-heading"]';
  await driver.click(roomsTab);
  await driver.wait(roomsTab + '[aria-current="location"]');
  for (const {room, price, key} of rates) {
    const card = '.hotel-room-card[data-room="' + room + '"]';
    const choice = card + ' [data-action="select-hotel-offer"][data-key="' + key + '"]';
    await driver.wait(choice);
    assert.equal(await driver.text(card + ' h4'), room);
    assert.equal(Number((await driver.text(card + ' .hotel-room-price strong')).replace(/\D/g, '')), price);
    if(price>rates[0].price)assert.equal(await driver.text(card + ' .room-price-difference'), 'На 300 ₽ выше минимума');
    const fits = await driver.evaluate(`(() => {
      const card = document.querySelector(${JSON.stringify(card)}), title = card.querySelector('h4');
      const range = document.createRange(), text = title.firstChild;
      range.setStart(text, text.length - ${room.length - prefix.length});
      range.setEnd(text, text.length);
      const word = range.getBoundingClientRect(), box = title.getBoundingClientRect();
      const hit = document.elementFromPoint(word.x + word.width / 2, word.y + word.height / 2);
      return word.width > 0 && word.height > 0 && word.left >= box.left - 1 &&
        word.right <= box.right + 1 && word.bottom <= box.bottom + 1 &&
        title.contains(hit) && card.scrollWidth <= card.clientWidth + 1;
    })()`);
    assert.ok(fits, 'the distinguishing word is visible inside its own room card: ' + room);
  }
  await documentGuard('similar-long-rooms');
  const file = 'similar-long-rooms-430.png';
  await driver.screenshot(path.join(evidence, file));
  await driver.click('[data-action="select-hotel-offer"][data-key="' + rates[1].key + '"]');
  await driver.wait('#prototype-lead-form');
  assert.ok((await driver.text('.application-review-heading')).includes(rates[1].room));
  assert.equal(Number((await driver.text('#modal-footer .footer-total > strong')).replace(/\D/g, '')), rates[1].price);
  return file;
}

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

  async function checkReturnedRoomPrices() {
    const standard = '.hotel-room-card[data-room="Стандартный номер"]';
    const breakfast = standard + ' [data-action="select-hotel-offer"][data-key="journey-900002-0"]';
    const allInclusive = standard + ' [data-offer-key="journey-900002-1"] .room-price-difference';
    await driver.go(url);
    await driver.click('.search-submit:not(:disabled)');
    await driver.click(card + ' [data-card-entry]');
    await driver.wait(breakfast);
    assert.equal(await text(allInclusive), 'На 6 800 ₽ выше минимума');
    await driver.click(breakfast);
    await driver.wait(contact);
    assert.equal(await text(total), '154 800 ₽');
    await driver.click('[data-action="contact-flights"]');
    await driver.click(pair);
    await driver.wait(pair + ':checked');
    await driver.click('[data-action="apply-flight"]');
    await driver.wait(contact);
    assert.equal(await text(total), '156 100 ₽');
    await driver.click('#modal-back');
    await driver.wait(standard + '[open]');
    assert.equal(await text('#hotel-detail-min'), '156 100 ₽');
    assert.equal(await text(allInclusive), 'На 5 500 ₽ выше минимума');
    assert.equal(await text(family + ' .room-price-difference'), 'На 13 400 ₽ выше минимума');
    await driver.click(standard + ' > summary');
    await driver.wait(standard + ':not([open]) .room-overview-price');
    assert.equal(await text(standard + ' .room-overview-price'), 'от 156 100 ₽');
    await driver.click('[data-target="hotel-rooms-heading"]');
    await driver.wait('[data-target="hotel-rooms-heading"][aria-current="location"]');
    await documentGuard('returned-room-minimum');
    const file = 'returned-room-minimum-430.png';
    await driver.screenshot(path.join(evidence, file));
    return file;
  }

  if (await driver.evaluate('location.href') !== url) await driver.go(url);
  await driver.wait('.search-submit:not(:disabled)');
  await documentGuard('initial');
  assert.equal(await driver.count('.hotel-card'), 0, 'Blank search initially');
  await driver.click(stars);
  await driver.wait(stars + '[aria-pressed="true"]');
  assert.equal(await driver.count('#cards:empty'), 1);
  assert.ok(await driver.evaluate('document.querySelector("body > .footer").getBoundingClientRect().bottom >= innerHeight - 1'),
    'the short search form fills the mobile viewport through its footer');
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
  await driver.wait('[data-target="hotel-rooms-heading"][aria-current="location"]');
  const headingGap = await driver.evaluate('document.querySelector("#hotel-rooms-heading").getBoundingClientRect().top - document.querySelector(".hotel-section-nav").getBoundingClientRect().bottom');
  assert.ok(headingGap >= -1 && headingGap <= 32, 'room heading starts immediately below the tabs on entry: ' + headingGap);
  const visibleRates = await driver.evaluate(`Array.from(document.querySelectorAll('.hotel-room-card[data-room="Стандартный номер"] .hotel-room-price > strong')).filter(price=>{
    const box=price.getBoundingClientRect(),nav=document.querySelector('.hotel-section-nav').getBoundingClientRect(),body=document.querySelector('#modal-body').getBoundingClientRect();
    return box.height>0 && box.top>=nav.bottom-1 && box.bottom<=body.bottom+1;
  }).map(el=>el.textContent.replace(/\\s+/g,' ').trim())`);
  assert.deepEqual(visibleRates, ['154 800 ₽', '161 600 ₽'], 'both standard room prices are visible as soon as the room comparison opens');
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
  const flightSummary = '.application-flight-overview .summary-flight-details:not([open]) > summary';
  await driver.wait(flightSummary);
  assert.match(await text(flightSummary), /Багаж 20 кг/i, 'selected baggage is visible before opening flight details');
  assert.equal(await text(total), '170 800 ₽');
  await frame('05-contact');

  await driver.fill(phone, '70000000000');
  assert.equal(await driver.value(phone), '70000000000');
  await driver.click('[data-action="contact-flights"]');
  await driver.wait(pair + ':checked');
  assert.equal(await text('[data-flight-index="0"] [data-flight-difference]'), 'Без доплаты');
  assert.equal(await text('[data-flight-index="1"] [data-flight-difference]'), '+1 300 ₽ к туру');
  assert.equal(await text('#flight-total'), '170 800 ₽');
  await driver.click('[data-action="apply-flight"]');
  await driver.wait(contact);
  assert.equal(await text(total), '170 800 ₽');
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
  const returnedRoomFrame = await checkReturnedRoomPrices();
  const similarRoomsFrame = await compareLongRoomNames({driver, url, evidence, documentGuard});
  return {frames, similarRoomsFrame, returnedRoomFrame, hotels: names, listing: 154800, room: 169500, flightSupplement: 1300,
    total: 170800, draftRestored: true, submitted: false};
};
