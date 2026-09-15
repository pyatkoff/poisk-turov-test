'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

const source=fs.readFileSync(path.join(__dirname,'../src/search3/behavior/results/price-readiness.js'),'utf8');
const window={};
vm.runInNewContext(source,{window,Number,String,Object,Array,Set,Map});
const api=window.Search3PriceReadiness;
assert.ok(api,'price readiness API is exposed');
assert.equal(api.version,1);

const ready=(price,extra={})=>Object.assign({id:'offer',price,finalPriceReady:true,finalPrice:price,currency:'RUB'},extra);
const unknown=(price,extra={})=>Object.assign({id:'offer',price,currency:'RUB'},extra);

assert.equal(api.offerReady(ready(100000)),true,'explicit verified final price is customer-visible');
assert.equal(api.offerReady(ready(100000,{finalPriceReady:false})),false,'explicit not-ready state fails closed');
assert.equal(api.offerReady(unknown(100000)),false,'missing readiness fails closed');
assert.equal(api.offerReady(ready(100000,{finalPrice:0})),false,'zero final price is not ready');
assert.equal(api.offerReady(ready(100000,{currency:'USD'})),false,'Search3 customer final price must be RUB');
assert.equal(api.offerReady(ready(100000,{finalPrice:100001})),false,'display price cannot diverge from verified final price');

const hotels=[
 {id:1,name:'A',price:90000,tours:[unknown(90000,{id:'a-unknown'}),ready(120000,{id:'a-ready'})]},
 {id:2,name:'B',price:80000,tours:[unknown(80000,{id:'b-unknown'})]},
 {id:3,name:'C',price:130000,tours:[ready(130000,{id:'c-ready'}),ready(140000,{id:'c-ready-2'})]}
];
const filtered=api.filterHotels(hotels);
assert.deepEqual(JSON.parse(JSON.stringify(filtered)).map(h=>({id:h.id,price:h.price,tours:h.tours.map(t=>t.id)})),[
 {id:1,price:120000,tours:['a-ready']},
 {id:3,price:130000,tours:['c-ready','c-ready-2']}
],'unknown offers disappear before hotel minima are calculated');
assert.equal(hotels[0].price,90000,'filtering does not mutate supplier payload');
assert.equal(hotels[0].tours.length,2,'filtering preserves original offer arrays');

const andromedaVerified=ready(155079,{id:'andromeda:verified',provider:'andromeda'});
const andromedaListing=unknown(150000,{id:'andromeda:listing',provider:'andromeda',quoteRequired:true});
assert.equal(api.offerReady(andromedaListing),false,'Andromeda listing price is not treated as final');
assert.equal(api.offerReady(andromedaVerified),true,'verified Andromeda final price can use the same provider-neutral contract');

console.log('search3 price readiness fail-closed: ok');
