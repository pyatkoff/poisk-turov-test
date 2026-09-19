'use strict';

const assert=require('assert');
const fs=require('fs');
const path=require('path');
const vm=require('vm');

const listeners={};
const root={
  location:{pathname:'/_preview/search3-local-candidate/poisk-turov/',protocol:'https:',hostname:'anytoour.ru',href:'https://anytoour.ru/_preview/search3-local-candidate/poisk-turov/'},
  addEventListener:(name,fn)=>{(listeners[name]||(listeners[name]=[])).push(fn);},
  dispatchEvent:()=>{},
  CustomEvent:function(){},
  V2SearchLifecycle:{generation:1,dirty:false,snapshot:null},
  fetch:async()=>{throw new Error('unexpected fetch');},
  V2Results:{render(){return 'sentinel';}},
  AbortController,URL,URLSearchParams,setTimeout,clearTimeout,Promise,Map,Set,Array,Object,String,Number,JSON,Error,TypeError,RegExp,console
};
root.window=root;
root.globalThis=root;

const context=vm.createContext(root);
const repoRoot=path.resolve(__dirname,'..');
vm.runInContext(fs.readFileSync(path.join(repoRoot,'v2/search3-canonical-profiles-v1.js'),'utf8'),context);
let refreshes=0;
const owner=root.Search3CanonicalProfilesV1.create(()=>{refreshes++;});
const originalRender=root.V2Results.render;
vm.runInContext(fs.readFileSync(path.join(repoRoot,'v2/search3-local-db-provider-v1.js'),'utf8'),context);

assert.strictEqual(root.V2Results.render,originalRender,'LOCAL provider must not monkey-patch renderer');

const offerDigest='a'.repeat(64),searchDigest='b'.repeat(64),hotelDigest='c'.repeat(64);
const data={source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:'d'.repeat(64),selectionAuthority:false,hotels:[{
  anytourHotelId:9001,
  hotel:{catalog:'anytour',id:9001,revision:3,name:'Canonical Hotel',primaryImage:'https://img.test/1.jpg',hotelInformation:{}},
  offers:[{provider:'tourvisor',legacyHotelId:101,price:'120000',currency:'RUB',listing:{schema_version:1,provider:'tourvisor',listingPriceReady:true,listingPrice:{amount:'120000'},currency:'RUB',selection_state:'refresh_required',booking_enabled:false,identity:{offer_ref_digest:offerDigest,search_ref_digest:searchDigest,provider_hotel_ref_digest:hotelDigest},tour:{checkin:'2026-10-05',nights:7,meal:{raw:'AI'},room:{raw:'STANDARD'},placement:{raw:'2AD'},party:{adults:2,children:0}},operator:{raw:'Pegas Touristik',canonical_name:null}}}]
}]};

const applied=root.AnyTourLocalDbProviderV1.apply(owner,data);
assert(applied&&applied.offerCount===1,'snapshot applies');
assert.strictEqual(refreshes,1,'apply requests one rerender');

let cards=owner.read([],{});
assert.strictEqual(cards.length,1,'cached-only canonical hotel remains visible');
assert.strictEqual(String(cards[0].anytourHotelId),'9001');
assert.strictEqual(String(cards[0].id),'101','legacy id exists only as renderer compatibility anchor');
assert.strictEqual(cards[0].tours.length,1);

const live={id:101,provider:'tourvisor',mappingStatus:'resolved',providers:['tourvisor'],tours:[{id:'live-tv',provider:'tourvisor',offerIdentityDigest:offerDigest,price:120000,date:'2026-10-05',nights:7}]};
cards=owner.read([live],{});
assert.strictEqual(cards.length,1);
assert.strictEqual(cards[0].tours.length,1,'same cached/live offer is deduplicated in canonical state');

owner.clearOffers('local-db');
cards=owner.read([],{});
assert.strictEqual(cards.length,0,'clearing local-db source removes cached-only offer');

console.log('SEARCH3_CANONICAL_LOCAL_STATE_OK');

// Search start follows reset asynchronously. It must not erase faster providers.
function searchEvent(type,generation,detail={}){
  const event={type,detail:Object.assign({generation},detail)};
  for(const listener of listeners[type]||[])listener(event);
}
const multi=JSON.parse(JSON.stringify(data));
multi.hotels[0].offers=['tourvisor','anex','andromeda'].map((provider,index)=>{
  const row=JSON.parse(JSON.stringify(data.hotels[0].offers[0]));
  row.provider=provider;row.listing.provider=provider;
  row.listing.identity.offer_ref_digest=['a','b','c'][index].repeat(64);
  return row;
});
const multiBefore=JSON.stringify(multi);
root.V2SearchLifecycle.generation=2;
searchEvent('v2:search-reset',2);
root.AnyTourLocalDbProviderV1.apply(owner,multi);
owner.read([live],{empty:false,retainFixture:'keep'});
const rawBefore=owner.source(),optionsBefore=owner.options(),refreshesBefore=refreshes;
searchEvent('v2:search-started',2,{searchId:731});
assert.strictEqual(owner.source(),rawBefore,'same-generation started retains raw input');
assert.strictEqual(owner.options(),optionsBefore,'same-generation started retains view options');
cards=owner.read(owner.source(),owner.options());
assert.strictEqual(cards.length,1,'early canonical hotel survives started');
assert.strictEqual(cards[0].tours.length,3,'all three early provider cohorts survive started');
assert.strictEqual(refreshes,refreshesBefore,'started does not trigger extra rendering');
assert.strictEqual(JSON.stringify(multi),multiBefore,'provider facts and quote flags remain untouched');
searchEvent('v2:search-started',2,{searchId:731});
searchEvent('v2:search-started',1,{searchId:730});
searchEvent('v2:search-reset',1);
assert.strictEqual(owner.read([],{}).length,1,'duplicate or stale events do not erase current offers');
root.V2SearchLifecycle.generation=3;
searchEvent('v2:search-started',3,{searchId:732});
assert.strictEqual(owner.read([],{}).length,0,'new generation without a preceding reset still clears old offers');
root.AnyTourLocalDbProviderV1.apply(owner,multi);
searchEvent('v2:search-started',3,{searchId:732});
assert.strictEqual(owner.read([],{}).length,1,'repeated started is idempotent');
root.V2SearchLifecycle.generation=4;root.V2SearchLifecycle.dirty=true;
searchEvent('v2:search-reset',4,{dirty:true});
assert.strictEqual(owner.read([],{}).length,0,'dirty search clears previous offers');
searchEvent('v2:search-started',3,{searchId:732});
assert.strictEqual(owner.read([],{}).length,0,'late started cannot restore a dirty search');
root.V2SearchLifecycle.dirty=false;
root.AnyTourLocalDbProviderV1.apply(owner,multi);
searchEvent('v2:search-reset',undefined);
assert.strictEqual(owner.read([],{}).length,0,'untagged legacy reset retains previous behavior');
root.AnyTourLocalDbProviderV1.apply(owner,multi);owner.reset();
assert.strictEqual(owner.read([],{}).length,0,'explicit owner.reset remains unconditional');

// Use deferred local transport to test pending canonical profiles, including a
// transport that ignores abort. No supplier or network access is performed.
async function pendingProfiles(){
  const handlers={},requests=[];
  const fixture={location:root.location,V2SearchLifecycle:{generation:1,dirty:false},
    addEventListener(type,fn){(handlers[type]||(handlers[type]=[])).push(fn);},
    fetch(url,options){return new Promise(resolve=>requests.push({url,options,resolve}));},
    AbortController,URLSearchParams,Promise,Map,Set,console,setTimeout,clearTimeout};
  fixture.window=fixture;
  vm.runInNewContext(fs.readFileSync(path.join(repoRoot,'v2/search3-canonical-profiles-v1.js'),'utf8'),fixture);
  const state=fixture.Search3CanonicalProfilesV1.create(()=>{});
  const send=(type,generation)=>{for(const handler of handlers[type]||[])handler({type,detail:{generation}});};
  const input=id=>[{id,provider:'tourvisor',tours:[{id:'tour-'+id,provider:'tourvisor',price:120000}]}];
  const response=(legacy,own)=>({ok:true,json:async()=>({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:[String(legacy)],items:[{id:own,catalog:'anytour',revision:1,name:'Fixture '+own}],links:[{legacyHotelId:legacy,anytourHotelId:own}],missingLegacyIds:[]})});
  const tick=()=>new Promise(resolve=>setImmediate(resolve));
  send('v2:search-reset',1);state.read(input(201),{});await tick();
  assert.strictEqual(requests.length,1,'one profile request for early input');
  send('v2:search-started',1);
  assert.strictEqual(requests[0].options.signal.aborted,false,'same-generation started does not abort pending profiles');
  requests[0].resolve(response(201,9201));await tick();await tick();
  assert.strictEqual(state.read(state.source(),{}).length,1,'early profile response remains usable after started');
  fixture.V2SearchLifecycle.generation=2;send('v2:search-reset',2);state.read(input(202),{});await tick();
  assert.strictEqual(requests.length,2);
  fixture.V2SearchLifecycle.generation=3;send('v2:search-reset',3);
  assert.strictEqual(requests[1].options.signal.aborted,true,'a genuinely new search aborts previous profile work');
  state.read(input(203),{});await tick();assert.strictEqual(requests.length,3);
  requests[1].resolve(response(202,9202));requests[2].resolve(response(203,9203));await tick();await tick();
  const currentCards=state.read(state.source(),{});
  assert.strictEqual(currentCards.length,1);assert.strictEqual(String(currentCards[0].anytourHotelId),'9203','late old response never contaminates new results');
  send('v2:search-started',2);
  assert.strictEqual(state.read(state.source(),{}).length,1,'stale started does not clear the newer completed profile');
  state.reset();
  console.log('SEARCH3_CANONICAL_EARLY_RESULTS_OK providers=3 same_generation=1 stale_events=1 pending_profiles=1 real_reset=1');
}
pendingProfiles().catch(error=>{console.error(error);process.exitCode=1;});
