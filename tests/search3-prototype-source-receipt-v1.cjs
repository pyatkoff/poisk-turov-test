'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(process.argv[2] || 'v2/prototype-search/source-receipt-v1.js', 'utf8');
const host = {dataset:{}};
let calls = 0;
let callbackEvents = [];
let scenario = 'late-projection';
const completeEvent = {type:'complete', sources:{
  anex:{status:'partial',hotels:1,offers:2,receivedHotels:3,receivedOffers:5,mappedHotels:3,mappedOffers:5,visibleHotels:1,visibleOffers:2,scopeFilteredOffers:2,deduplicatedOffers:1,windowsLoaded:1,windowsTotal:2,dateFrom:'2026-09-25',dateTo:'2026-09-30',secret:'x'},
  andromeda:{status:'partial',hotels:0,offers:0,receivedHotels:2,receivedOffers:7,mappedHotels:2,mappedOffers:6,projectedOffers:5,visibleHotels:0,visibleOffers:0,scopeFilteredOffers:4,deduplicatedOffers:1,pagesLoaded:1,pagesTotal:3,dateFrom:'2026-09-25',dateTo:'2026-09-30',privatePayload:'x'},
  database:{status:'complete',hotels:2,offers:4,storedOffers:9,receivedOffers:9,mappedOffers:8,visibleOffers:4,withheldOffers:1,scopeFilteredOffers:2,eligibleHotels:3,omittedHotels:1,omittedOffers:2,providerOfferCounts:{andromeda:4,bad:null,stringy:'7',fractional:1.5},sql:'x'}
}, union:{hotels:2,offers:5,hotelsByProvider:{anex:1,andromeda:2,bad:null},offersByProvider:{anex:2,andromeda:3,stringy:'7'},providerSets:{'anex+andromeda':1,andromeda:1},secret:'x'}};
const data = Object.freeze({
  search(search, callback, ...args) {
    calls++;
    assert.deepEqual(search, {country:'4'});
    assert.deepEqual(args, [['hotel-1'], {max:200000}]);
    const events = scenario === 'late-projection' ? [
      {type:'loading'},
      {type:'provider', provider:'anex', status:'loading', secret:'do-not-export'},
      {type:'results', hotels:[
        {offers:[{provider:'anex'},{provider:'andromeda'}]},
        {offers:[{provider:'andromeda'}]}
      ]},
      {...completeEvent, union:{hotels:1,offers:1,hotelsByProvider:{anex:1},offersByProvider:{anex:1},providerSets:{anex:1},secret:'stale'}},
      {type:'results', hotels:[
        {offers:[{provider:'anex'},{provider:'andromeda'}]},
        {offers:[{provider:'andromeda'}]},
        {offers:[{provider:'tourvisor'},{provider:'tourvisor'}]}
      ]},
      {type:'provider',provider:'anex',status:'complete',hotels:2,offers:3,receivedHotels:4,receivedOffers:8,mappedHotels:4,mappedOffers:8,
        visibleHotels:2,visibleOffers:3,scopeFilteredOffers:3,deduplicatedOffers:2,windowsLoaded:2,windowsTotal:2,dateFrom:'2026-09-25',dateTo:'2026-10-02',secret:'late'},
      {type:'database',status:'complete',hotels:3,offers:5,storedOffers:11,receivedOffers:11,mappedOffers:10,visibleHotels:3,visibleOffers:5,
        withheldOffers:1,scopeFilteredOffers:2,eligibleHotels:4,omittedHotels:1,omittedOffers:2,providerOfferCounts:{anex:2,andromeda:3},sql:'late'}
    ] : scenario === 'invalid-dedupe' ? [
      {type:'loading'},
      {...completeEvent, union:{hotels:4,offers:7,hotelsByProvider:{anex:2,andromeda:2},offersByProvider:{anex:3,andromeda:4},providerSets:{anex:2,andromeda:2}}}
    ] : [{type:'loading'}, completeEvent];
    for (const event of events) callback(event);
    return Promise.resolve('same-return');
  },
  get searchId(){return 42;}
});
const context = {window:{AnyTourPrototypeData:data},document:{getElementById:id=>id==='results'?host:null},console};
vm.runInNewContext(source, context, {filename:'source-receipt-v1.js'});
const wrapped = context.window.AnyTourPrototypeData;
assert.notEqual(wrapped, data, 'frozen data API must be wrapped, not mutated');
assert.equal(Object.isFrozen(wrapped), true);
assert.equal(Object.isFrozen(data), true);
assert.equal(wrapped.__searchReceiptV1, true);
assert.equal(wrapped.searchId, 42, 'getters from the frozen data API must survive wrapping');
let receiptAtComplete = null;
const returned = wrapped.search({country:'4'}, event=>{
  callbackEvents.push(event);
  if (event.type === 'complete') receiptAtComplete = JSON.parse(host.dataset.searchReceipt);
}, ['hotel-1'], {max:200000});
assert.equal(calls, 1);
assert.equal(callbackEvents.length, 7);
assert.equal(callbackEvents[1].secret, 'do-not-export', 'wrapper must not mutate original callback event');
assert.deepEqual(receiptAtComplete.projection, {hotels:2,offers:3});
assert.deepEqual(receiptAtComplete.union, {
  hotels:2,offers:3,
  hotelsByProvider:{anex:1,andromeda:2},
  offersByProvider:{anex:1,andromeda:2},
  providerSets:{'andromeda+anex':1,andromeda:1}
}, 'complete must not replace a newer projected canonical union with a stale completion snapshot');
assert.deepEqual(receiptAtComplete.dedupe, {
  sourceVisibleHotels:3,sourceVisibleOffers:6,unionHotels:2,unionOffers:3,dedupedHotels:1,dedupedOffers:3
}, 'complete exposes the exact loss from source-visible rows to the canonical union');
assert.deepEqual(receiptAtComplete.providers, {anex:'partial',andromeda:'partial',database:'complete'}, 'terminal provider statuses must match sanitized source statuses');
const receipt = JSON.parse(host.dataset.searchReceipt);
assert.equal(receipt.schemaVersion, 1);
assert.equal(receipt.phase, 'complete');
assert.deepEqual(receipt.projection, {hotels:3,offers:5});
assert.deepEqual(receipt.providers, {anex:'complete',andromeda:'partial',database:'complete'}, 'late provider status advances after terminal completion');
assert.deepEqual(receipt.sources.anex, {status:'complete',hotels:2,offers:3,receivedHotels:4,receivedOffers:8,mappedHotels:4,mappedOffers:8,visibleHotels:2,visibleOffers:3,scopeFilteredOffers:3,deduplicatedOffers:2,windowsLoaded:2,windowsTotal:2,dateFrom:'2026-09-25',dateTo:'2026-10-02'});
assert.equal('secret' in receipt.sources.anex, false, 'late provider secrets stay sanitized');
assert.deepEqual(receipt.sources.andromeda, {status:'partial',hotels:0,offers:0,receivedHotels:2,receivedOffers:7,mappedHotels:2,mappedOffers:6,projectedOffers:5,visibleHotels:0,visibleOffers:0,scopeFilteredOffers:4,deduplicatedOffers:1,pagesLoaded:1,pagesTotal:3,dateFrom:'2026-09-25',dateTo:'2026-09-30'});
assert.equal('privatePayload' in receipt.sources.andromeda, false);
assert.deepEqual(receipt.sources.database, {status:'complete',hotels:3,offers:5,storedOffers:11,receivedOffers:11,mappedOffers:10,visibleHotels:3,visibleOffers:5,
  withheldOffers:1,scopeFilteredOffers:2,eligibleHotels:4,omittedHotels:1,omittedOffers:2,providerOfferCounts:{anex:2,andromeda:3}});
assert.equal('sql' in receipt.sources.database, false, 'late database payload stays sanitized');
assert.deepEqual(receipt.union, {
  hotels:3,offers:5,
  hotelsByProvider:{anex:1,andromeda:2,tourvisor:1},
  offersByProvider:{anex:1,andromeda:2,tourvisor:2},
  providerSets:{'andromeda+anex':1,andromeda:1,tourvisor:1}
}, 'late canonical results must advance the union receipt with the visible projection');
assert.deepEqual(receipt.dedupe, {
  sourceVisibleHotels:5,sourceVisibleOffers:8,unionHotels:3,unionOffers:5,dedupedHotels:2,dedupedOffers:3
}, 'late provider/database accounting recomputes dedupe against the already-current canonical union');
returned.then(value=>assert.equal(value,'same-return'));

host.dataset.searchReceipt = JSON.stringify({stale:true});
callbackEvents = [];
scenario = 'fallback-complete';
let snapshotAtCallback = null;
wrapped.search({country:'4'}, event=>{if(!snapshotAtCallback) snapshotAtCallback=JSON.parse(host.dataset.searchReceipt);}, ['hotel-1'], {max:200000});
assert.equal(snapshotAtCallback.schemaVersion,1);
assert.equal(snapshotAtCallback.dedupe,null);
assert.equal('stale' in snapshotAtCallback,false);
assert.equal(calls,2);
const fallback = JSON.parse(host.dataset.searchReceipt);
assert.deepEqual(fallback.projection,{hotels:0,offers:0});
assert.deepEqual(fallback.union,{hotels:2,offers:5,hotelsByProvider:{anex:1,andromeda:2},offersByProvider:{anex:2,andromeda:3},providerSets:{'anex+andromeda':1,andromeda:1}});
assert.deepEqual(fallback.dedupe,{sourceVisibleHotels:3,sourceVisibleOffers:6,unionHotels:2,unionOffers:5,dedupedHotels:1,dedupedOffers:1});
assert.equal('secret' in fallback.union,false);

scenario = 'invalid-dedupe';
wrapped.search({country:'4'}, ()=>{}, ['hotel-1'], {max:200000});
assert.equal(calls,3);
const invalid = JSON.parse(host.dataset.searchReceipt);
assert.equal(invalid.dedupe,null,'inconsistent source-visible totals must fail closed instead of inventing negative dedupe');
console.log('search3 prototype source receipt v1: PASS');
