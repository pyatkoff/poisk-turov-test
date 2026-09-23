'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(process.argv[2] || 'v2/prototype-search/source-receipt-v1.js', 'utf8');
const host = {dataset:{}};
const data = Object.freeze({
  search(search, callback) {
    assert.deepEqual(search, {country:'4'});
    callback({type:'loading'});
    callback({type:'provider', provider:'anex', status:'loading'});
    callback({type:'provider', provider:'tourvisor', status:'loading'});
    callback({
      type:'complete',
      partial:true,
      sources:{
        anex:{status:'partial',visibleHotels:2,visibleOffers:3},
        tourvisor:{status:'complete',visibleHotels:4,visibleOffers:5},
        database:{status:'skipped',visibleHotels:0,visibleOffers:0},
        ignored:{status:'not-public',visibleHotels:1,visibleOffers:1}
      },
      union:{hotels:6,offers:8,hotelsByProvider:{anex:2,tourvisor:4},offersByProvider:{anex:3,tourvisor:5},providerSets:{anex:2,tourvisor:4}}
    });
    return Promise.resolve();
  }
});
const context = {window:{AnyTourPrototypeData:data},document:{getElementById:id=>id==='results'?host:null},console};
vm.runInNewContext(source, context, {filename:'source-receipt-v1.js'});
context.window.AnyTourPrototypeData.search({country:'4'}, ()=>{});
const receipt = JSON.parse(host.dataset.searchReceipt);
assert.equal(receipt.phase, 'partial');
assert.deepEqual(receipt.providers, {
  anex:'partial',
  tourvisor:'complete',
  database:'skipped'
}, 'terminal provider status map must match sanitized source statuses instead of retaining stale loading state');
assert.deepEqual(receipt.sources.ignored, {visibleHotels:1,visibleOffers:1}, 'unsupported source status must remain fail-closed');
assert.equal('ignored' in receipt.providers, false, 'unsupported source status must not enter the provider status map');
console.log('search3 prototype source receipt terminal status: PASS');
