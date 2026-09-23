'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(process.argv[2] || 'v2/prototype-search/source-receipt-v1.js', 'utf8');
const host = {dataset:{}};
let calls = 0;
let callbackEvents = [];
const data = Object.freeze({
  search(search, callback, ...args) {
    calls++;
    assert.deepEqual(search, {country:'4'});
    assert.deepEqual(args, [['hotel-1'], {max:200000}]);
    const events = [
      {type:'loading'},
      {type:'provider', provider:'anex', status:'loading', secret:'do-not-export'},
      {type:'results', hotels:[{offers:[{},{}]},{offers:[{}]}]},
      {type:'complete', sources:{
        anex:{status:'partial',hotels:1,offers:2,receivedHotels:3,receivedOffers:5,mappedHotels:3,mappedOffers:5,visibleHotels:1,visibleOffers:2,scopeFilteredOffers:3,dateFrom:'2026-09-25',dateTo:'2026-09-30',secret:'x'},
        database:{status:'complete',hotels:2,offers:4,storedOffers:4,providerOfferCounts:{andromeda:4,bad:null,stringy:'7'},sql:'x'}
      }, union:{hotels:2,offers:5,hotelsByProvider:{anex:1,andromeda:2,bad:null},offersByProvider:{anex:2,andromeda:3,stringy:'7'},providerSets:{'anex+andromeda':1,andromeda:1},secret:'x'}}
    ];
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
const returned = wrapped.search({country:'4'}, event=>callbackEvents.push(event), ['hotel-1'], {max:200000});
assert.equal(calls, 1);
assert.equal(callbackEvents.length, 4);
assert.equal(callbackEvents[1].secret, 'do-not-export', 'wrapper must not mutate original callback event');
const receipt = JSON.parse(host.dataset.searchReceipt);
assert.equal(receipt.schemaVersion, 1);
assert.equal(receipt.phase, 'complete');
assert.deepEqual(receipt.projection, {hotels:2,offers:3});
assert.equal(receipt.providers.anex, 'loading');
assert.deepEqual(receipt.sources.anex, {status:'partial',hotels:1,offers:2,receivedHotels:3,receivedOffers:5,mappedHotels:3,mappedOffers:5,visibleHotels:1,visibleOffers:2,scopeFilteredOffers:3,dateFrom:'2026-09-25',dateTo:'2026-09-30'});
assert.equal('secret' in receipt.sources.anex, false);
assert.deepEqual(receipt.sources.database.providerOfferCounts, {andromeda:4});
assert.equal('sql' in receipt.sources.database, false);
assert.deepEqual(receipt.union, {hotels:2,offers:5,hotelsByProvider:{anex:1,andromeda:2},offersByProvider:{anex:2,andromeda:3},providerSets:{'anex+andromeda':1,andromeda:1}});
assert.equal('secret' in receipt.union, false);
returned.then(value=>assert.equal(value,'same-return'));

host.dataset.searchReceipt = JSON.stringify({stale:true});
callbackEvents = [];
let snapshotAtCallback = null;
wrapped.search({country:'4'}, event=>{if(!snapshotAtCallback) snapshotAtCallback=JSON.parse(host.dataset.searchReceipt);}, ['hotel-1'], {max:200000});
assert.equal(snapshotAtCallback.schemaVersion,1);
assert.equal('stale' in snapshotAtCallback,false);
assert.equal(calls,2);
console.log('search3 prototype source receipt v1: PASS');
