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
function storedOffer({offerDigest=digest('b')}={}){
 return {
  provider:'tourvisor',legacyHotelId:'201',currency:'RUB',price:'100000',
  listing:{
   schema_version:1,provider:'tourvisor',currency:'RUB',selection_state:'refresh_required',booking_enabled:false,
   listingPriceState:'search_price_confirmation_required',listingPriceReady:false,priceConfirmationRequired:true,
   quoteState:'unknown',finalPriceVerified:false,quoteEvidenceDigest:null,listingPrice:'100000',
   identity:{offer_ref_digest:offerDigest,search_ref_digest:digest('c'),provider_hotel_ref_digest:digest('d')},
   tour:{checkin:'2026-10-01',nights:7,meal:{raw:'AI'},room:{raw:'Standard'},placement:{raw:'2 ADL'},party:{adults:2,children:0}},
   operator:{canonical_name:'ANEX'}
  }
 };
}
function payload(offers){
 return {
  source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:digest('a'),selectionAuthority:false,
  hotels:[{anytourHotelId:'101',hotel:{catalog:'anytour',id:'101',name:'Valid hotel'},offers}]
 };
}

test('LOCAL parser withholds one malformed offer and keeps valid siblings in the first union',()=>{
 const data=payload([storedOffer(),storedOffer({offerDigest:'not-a-digest'})]);
 const parsed=local.parse(data);
 assert.ok(parsed,'mixed snapshot stays usable');
 assert.equal(parsed.hotels.length,1);
 assert.equal(parsed.offerCount,1);
 assert.equal(parsed.withheldOfferCount,1);
 assert.equal(parsed.hotels[0].offers.length,1);
 assert.equal(parsed.hotels[0].offers[0].tour.offerIdentityDigest,digest('b'));

 const calls=[];
 const owner={
  clearOffers(source){calls.push(['clear',source]);},
  upsertHotel(hotel){calls.push(['hotel',hotel.id]);},
  upsertOffer(hotelId,tour,meta){calls.push(['offer',hotelId,tour.offerIdentityDigest,meta]);},
  refresh(){calls.push(['refresh']);}
 };
 const applied=local.apply(owner,data);
 assert.equal(applied.offerCount,1);
 assert.deepEqual(calls.map(row=>row[0]),['clear','hotel','offer','refresh']);
 assert.equal(calls[2][2],digest('b'));
 assert.deepEqual(calls[2][3],{source:'local-db',legacyHotelId:'201'});
});

test('LOCAL parser still rejects malformed canonical group structure fail closed',()=>{
 const data=payload([storedOffer()]);
 data.hotels[0].hotel.id='999';
 assert.equal(local.parse(data),null);
});
