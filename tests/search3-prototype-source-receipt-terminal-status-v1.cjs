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

function receiptFor(events){
  const target={dataset:{}},state={window:{AnyTourPrototypeData:Object.freeze({search(search,callback){events.forEach(callback);}})},document:{getElementById:id=>id==='results'?target:null},console};
  vm.runInNewContext(source,state,{filename:'actual-source-receipt-v1.js'});
  state.window.AnyTourPrototypeData.search({},()=>{});
  return JSON.parse(target.dataset.searchReceipt);
}
const partial=receiptFor([{type:'complete',sources:{
  tourvisor:{status:'complete',hotels:1,offers:40},
  anex:{status:'error',hotels:0,offers:0,failure:{stage:'search',code:'rate_limited',httpStatus:429,message:'PRIVATE_MESSAGE',url:'PRIVATE_URL',token:'PRIVATE_TOKEN'}},
  database:{status:'skipped',hotels:0,offers:0},andromeda:{status:'skipped',hotels:0,offers:0}
},union:{hotels:1,offers:40,hotelsByProvider:{tourvisor:1},offersByProvider:{tourvisor:40},providerSets:{tourvisor:1}}}]);
assert.equal(partial.phase,'complete','preserve the existing completed-poll phase contract');
assert.equal(partial.coverage,'partial','a completed TV poll does not imply complete provider coverage');
assert.deepEqual(partial.sources.anex.failure,{stage:'search',code:'rate_limited',httpStatus:429});
assert(!JSON.stringify(partial).includes('PRIVATE_'),'only bounded public failure facts are written to the DOM');
assert.equal(partial.union.offers,40);assert.equal(partial.dedupe.dedupedOffers,0);
const invalid=receiptFor([{type:'complete',sources:{anex:{status:'partial',failure:{stage:'PRIVATE_STAGE',code:'PRIVATE_CODE',httpStatus:999,body:'PRIVATE_BODY'}}}}]);
assert.equal(invalid.phase,'complete');assert.equal(invalid.coverage,'partial');assert(!('failure' in invalid.sources.anex));assert(!JSON.stringify(invalid).includes('PRIVATE_'));
const late=receiptFor([{type:'complete',sources:{anex:{status:'complete'}}},{type:'provider',provider:'anex',status:'partial',failure:{stage:'continue',code:'supplier_timeout',httpStatus:502}}]);
assert.equal(late.phase,'complete');assert.equal(late.coverage,'partial');assert.deepEqual(late.sources.anex.failure,{stage:'continue',code:'supplier_timeout',httpStatus:502});
const loading=receiptFor([{type:'complete',sources:{anex:{status:'partial'}}},{type:'loading',continued:true},{type:'provider',provider:'anex',status:'partial'}]);
assert.equal(loading.phase,'loading','diagnostic updates never terminate a pending continuation');
assert.equal(loading.coverage,'pending');
assert.equal(receiptFor([{type:'complete',sources:{tourvisor:{status:'complete'},anex:{status:'complete'},andromeda:{status:'skipped'}}}]).coverage,'complete');
assert.equal(receiptFor([{type:'complete',sources:{tourvisor:{status:'skipped'},anex:{status:'skipped'}}}]).coverage,'unknown','cached or skipped sources do not prove current provider coverage');
console.log('search3 partial source failure receipt: PASS');
