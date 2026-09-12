'use strict';

const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'v2', 'results-renderer-v5.js'), 'utf8');
const context = {
  window: {},
  document: {
    readyState: 'loading',
    addEventListener() {},
    getElementById() { return null; },
    documentElement: { dataset: {} }
  },
  CustomEvent: function CustomEvent(name, options) { this.type = name; this.detail = options && options.detail; },
  Event: function Event(name, options) { this.type = name; this.bubbles = options && options.bubbles; },
  Intl,
  Number,
  String,
  Array,
  Set,
  Map,
  console
};
context.window.window = context.window;
vm.createContext(context);
vm.runInContext(source, context, { filename: 'results-renderer-v5.js' });

const renderer = context.window.V2ResultsV5;
assert(renderer, 'renderer API must load');

const hotel = {
  id: 6319,
  price: 62400,
  tours: [
    { id: 'a', date: '2026-09-16', nights: 7, price: 62400, meal: { name: 'BB', fullName: 'Завтраки' }, operator: 'Fun&Sun (RU)', isCharter: true },
    { id: 'b', date: '2026-09-15', nights: 9, price: 70564, meal: { name: 'AI', fullName: 'Все включено' }, operator: 'Анекс Тур', isCharter: true },
    { id: 'c', date: '2026-09-16', nights: 10, price: 73000, meal: { name: 'BB', fullName: 'Завтраки' }, operator: 'Библио Глобус', isCharter: false }
  ]
};

const summary = renderer.hotelSummary(hotel);
assert.strictEqual(summary.date, 'Несколько дат вылета');
assert.strictEqual(summary.nights, '7–10');
assert.strictEqual(summary.meal, 'BB · AI');
assert.strictEqual(summary.flight, '', 'mixed charter/non-charter must not claim charter at hotel level');
assert.strictEqual(summary.operators, 'Fun&Sun (RU) · Анекс Тур · Библио Глобус');

const contextText = renderer.priceContext(hotel);
assert(contextText.includes('Несколько дат вылета'));
assert(contextText.includes('7–10 ноч.'));
assert(contextText.includes('BB · AI'));
assert(!contextText.includes('16.09.2026 · 7 ноч.'), 'representative offer must not masquerade as hotel summary');

const collapsed = renderer.toursHtml(hotel);
assert(collapsed.includes('Доступные варианты'));
assert(collapsed.includes('Несколько дат вылета'));
assert(collapsed.includes('7–10'));
assert(collapsed.includes('BB · AI'));
assert(collapsed.includes('Показать 3 варианта'));
assert(collapsed.includes('Туроператоры'));

const one = {
  id: 1,
  price: 62400,
  tours: [{ id: 'only', date: '2026-09-16', nights: 7, price: 62400, meal: { name: 'BB' }, operator: 'Fun&Sun (RU)', isCharter: true }]
};
assert(renderer.priceContext(one).includes('16.09.2026'));
assert(renderer.toursHtml(one).includes('BB'));

console.log('search3 current card aggregate summary: ok');
