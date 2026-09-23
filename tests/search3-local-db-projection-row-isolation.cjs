'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

const source=fs.readFileSync(path.join(__dirname,'..','v2','search3-local-db-provider-v1.js'),'utf8');
const sandbox={};
vm.runInNewContext(source,sandbox,{filename:'search3-local-db-provider-v1.js'});
const local=sandbox.AnyTourLocalDbProviderV1;
const digest=char=>char.repeat(64);

function stored(char='b'){
 return {
  provider:'tourvisor',legacyHotelId:'201',price:'100000',currency:'RUB',
  listing:{
   schema_version:1,provider:'tourvisor',currency:'RUB',selection_state:'refresh_required',booking_enabled:false,
   listingPriceReady:true,listingPrice:{amount:'100000',currency:'RUB'},
   identity:{offer_ref_digest:digest(char),search_ref_digest:digest('c'),provider_hotel_ref_digest:digest('d')},
   tour:{checkin:'2026-10-01',nights:7,party:{adults:2,children:0},meal:{raw:'AI'},room:{raw:'Standard'},placement:{raw:'2 ADL'}},
   operator:{raw:'ANEX',canonical_name:'ANEX'}
  }
 };
}
function payload(offers){
 return {source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:digest('a'),selectionAuthority:false,
  hotels:[{anytourHotelId:'101',hotel:{catalog:'anytour',id:'101',name:'Valid hotel'},offers}]};
}

test('projection-local malformed row is withheld while a valid DB-first sibling survives',()=>{
 const valid=stored('b'),bad=stored('e');bad.listing.tour.checkin='bad-date';
 const data=payload([valid,bad]);
 const parsed=local.parse(data);
 assert.ok(parsed);
 assert.equal(parsed.hotels.length,1);
 assert.equal(parsed.offerCount,1);
 assert.equal(parsed.withheldOfferCount,1);
 assert.equal(parsed.hotels[0].offers[0].tour.offerIdentityDigest,digest('b'));

 const calls=[];
 const owner={
  clearOffers(source){calls.push(['clear',source]);},
  upsertHotel(hotel){calls.push(['hotel',hotel.id]);},
  upsertOffer(hotelId,tour,meta){calls.push(['offer',hotelId,tour.offerIdentityDigest,meta.source]);},
  refresh(){calls.push(['refresh']);}
 };
 const applied=local.apply(owner,data);
 assert.ok(applied);
 assert.deepEqual(calls.map(row=>row[0]),['clear','hotel','offer','refresh']);
 assert.equal(calls[2][2],digest('b'));
});

test('listing and identity authority stay snapshot-fail-closed',()=>{
 const badIdentity=stored('e');badIdentity.listing.identity.offer_ref_digest='bad';
 assert.equal(local.parse(payload([stored('b'),badIdentity])),null);
 const badListing=stored('e');badListing.listing.selection_state='enabled';
 assert.equal(local.parse(payload([stored('b'),badListing])),null);
});
