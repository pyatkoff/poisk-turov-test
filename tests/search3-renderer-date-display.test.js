'use strict';
const assert=require('assert');

global.window={
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

console.log('SEARCH3_RENDERER_DATE_DISPLAY_OK');
