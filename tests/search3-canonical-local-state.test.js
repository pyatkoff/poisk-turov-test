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
