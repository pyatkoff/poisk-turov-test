'use strict';
const assert=require('assert');

global.window={
  location:{pathname:'/_preview/search3-local-candidate/poisk-turov/'},
  addEventListener(){},
  dispatchEvent(){},
  requestAnimationFrame(fn){if(typeof fn==='function')fn();}
};
global.document={
  readyState:'loading',
  documentElement:{dataset:{}},
  body:null,
  addEventListener(){},
  getElementById(){return null;},
  querySelector(){return null;}
};
global.CustomEvent=function CustomEvent(){};

require('../v2/results-renderer-v5.js');
const renderer=global.window.V2ResultsV5;
assert(renderer,'renderer API must load');

const cases=[
  ['2026-10-05','05.10.2026'],
  ['2026-10-05T23:30:00Z','05.10.2026'],
  ['2026-10-05 23:30:00','05.10.2026'],
  ['05.10.2026','05.10.2026'],
  ['2026/10/05','2026/10/05'],
  ['supplier-date','supplier-date'],
  ['',''],
  [null,'']
];
for(const [input,expected] of cases){
  assert.strictEqual(renderer.formatTourDate(input),expected,`display date mismatch for ${String(input)}`);
}

const tour={
  id:'date-test',
  date:'2026-10-05',
  nights:7,
  meal:'AI',
  roomType:'Standard',
  price:123456
};
const row=renderer.tourRow(tour);
assert(row.includes('<strong>05.10.2026</strong>'),'tour row must use normalized display date');
assert(!row.includes('<strong>2026-10-05</strong>'),'tour row must not expose raw ISO date when it is safely display-normalizable');
assert.strictEqual(tour.date,'2026-10-05','renderer must not mutate provider date value');

const context=renderer.priceContext({price:123456,tours:[tour]});
assert(context.startsWith('05.10.2026 · 7 ноч.'),'price context must use the same normalized display date');
assert.strictEqual(tour.date,'2026-10-05','price context must not mutate provider date value');

const unknown={id:'unknown-date',date:'supplier-date',price:100000};
assert(renderer.tourRow(unknown).includes('<strong>supplier-date</strong>'),'unknown formats must pass through unchanged');
assert(renderer.tourRow({id:'blank-date',date:'',price:100000}).includes('<strong>Уточняется</strong>'),'blank row date must keep the existing fallback');

const charter=renderer.flightBadge({isCharter:true});
assert(charter.includes('Чартерный рейс'),'charter flight must use the charter badge label');
assert(!charter.includes('Возможна доплата'),'charter badge must not show regular-flight surcharge disclosure');
const regular=renderer.flightBadge({isCharter:false});
assert(regular.includes('Регулярный рейс'),'regular flight must use the regular badge label');
assert(regular.includes('Возможна доплата за регулярный рейс. Точную стоимость необходимо уточнить.'),'regular badge must disclose possible surcharge');
assert(regular.includes('tabindex="0"'),'regular disclosure must be keyboard-focusable');
assert.strictEqual(renderer.flightBadge({}),'','unknown flight type must remain unlabeled');
const regularRow=renderer.tourRow({...tour,isCharter:false,priceNeedsConfirmation:true});
assert(regularRow.includes('Регулярный рейс'),'tour row must render regular flight badge');
assert(regularRow.includes('Возможна доплата за регулярный рейс. Точную стоимость необходимо уточнить.'),'tour row must retain regular-flight disclosure');
assert(regularRow.includes('Цена из поиска'),'regular flight with possible surcharge must not be labelled as final total');
assert(!regularRow.includes('Итого за тур'),'regular flight with possible surcharge must not claim final total');

console.log('SEARCH3_RENDERER_DATE_DISPLAY_OK');
