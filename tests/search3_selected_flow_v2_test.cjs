'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
  'v2/_preview/search3-candidate/poisk-turov/search3-selected-flow-v2.js',
  'utf8',
);
const window = {};
vm.runInNewContext(source, {
  window,
  document: { body: null, getElementById() { return null; } },
  Number,
  Object,
  String,
  Math,
});
const helpers = window.Search3SelectedFlowV2Helpers;
const plain = value => JSON.parse(JSON.stringify(value));

test('selected totals use the normalized price without adding the tour twice', () => {
  const tour = { price: 71151 };
  assert.equal(helpers.normalizedTotal({ tour }), 71151);
  assert.equal(helpers.normalizedTotal({ tour, basePrice: 71151, price: 88373, delta: 17222 }), 88373);
  assert.notEqual(helpers.normalizedTotal({ tour, price: 88373 }), tour.price + 88373);

  assert.deepEqual(
    [101000, 113600, 1234567].map(price => helpers.normalizedTotal({ tour, basePrice: tour.price, price })),
    [101000, 113600, 1234567],
  );
  assert.equal(
    helpers.normalizedTotal({ tour, basePrice: 71151, price: 0, pricePending: true }),
    71151,
  );
});

test('collapsed flight choices keep a selected option visible after return', () => {
  assert.deepEqual(plain(helpers.visibleFlightIndexes(133, 0, false)), [0, 1, 2, 3, 4, 5]);
  assert.equal(helpers.visibleFlightIndexes(133, 0, true).length, 133);
  assert.deepEqual(plain(helpers.visibleFlightIndexes(133, 132, false)), [0, 1, 2, 3, 4, 5, 132]);
  assert.deepEqual(
    plain(helpers.visibleFlightIndexes(133, 132, false)),
    plain(helpers.visibleFlightIndexes(133, 132, false)),
    'returning to the flight step must preserve the collapsed selection',
  );
});

test('no-flight state advances to review with the same CTA vocabulary', () => {
  assert.equal(helpers.noFlightMessage('Для тура варианты рейсов не найдены.'), true);
  assert.equal(helpers.noFlightMessage('Данные по рейсам пока не получены. Менеджер уточнит.'), true);
  assert.equal(helpers.noFlightMessage('Загружаем рейсы и багаж…'), false);
  assert.equal(helpers.flowLabel('flight'), 'Далее: итог тура');
  assert.equal(helpers.flowLabel('review'), 'Перейти к заявке');
  assert.equal(helpers.flowLabel('submit'), 'Отправить заявку');
});
