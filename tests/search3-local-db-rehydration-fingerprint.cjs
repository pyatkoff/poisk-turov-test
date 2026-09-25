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

function canonical(kind,id,name){
 return {kind,id,hotelId:101,nameRu:name,localKey:kind+'-'+id,revision:3};
}
function stored(){
 const meal=canonical('meal',901,'Всё включено'),room=canonical('room',701,'Deluxe Room');
 return {
  provider:'andromeda',legacyHotelId:'201',price:'150000',currency:'RUB',
  listing:{
   schema_version:1,provider:'andromeda',currency:'RUB',selection_state:'refresh_required',booking_enabled:false,
   listingPriceReady:true,listingPrice:{amount:'150000',currency:'RUB'},
   identity:{offer_ref_digest:digest('a'),search_ref_digest:digest('b'),provider_hotel_ref_digest:digest('c')},
   tour:{checkin:'2026-10-15',nights:7,party:{adults:2,children:2,child_ages:[6,12]},
    meal:{raw:'Ultra All Inclusive'},room:{raw:'DELUXE SEA VIEW'},placement:{raw:'2 ADL + 2 CHD'}},
   operator:{raw:'FUN SUN',canonical_name:'FUN&SUN'}
  },
  stayMatch:{source:'anytour-hotel-stay-v2',exactScope:true,
   meal:{status:'accepted',canonical:meal},room:{status:'accepted',canonical:room}}
 };
}
function payload(row){
 return {source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:digest('d'),selectionAuthority:false,
  hotels:[{anytourHotelId:'101',hotel:{catalog:'anytour',id:'101',revision:1,name:'Fixture'},offers:[row]}]};
}

test('cached LOCAL offer exposes a durable semantic rehydration descriptor',()=>{
 const parsed=local.parse(payload(stored()));
 assert.ok(parsed);
 const tour=parsed.hotels[0].offers[0].tour;
 assert.equal(tour.cachedListing,true);
 assert.equal(tour.selectionEnabled,false);
 assert.deepEqual(JSON.parse(JSON.stringify(tour.rehydration)),{
  schema_version:1,provider:'andromeda',anytour_hotel_id:101,legacy_hotel_id:201,
  checkin:'2026-10-15',nights:7,
  party:{adults:2,children:2,child_ages:[6,12]},
  operator:{name:'FUN&SUN'},
  meal:{basis:'canonical',id:901,revision:3,local_key:'meal-901',name_ru:'Всё включено'},
  room:{basis:'canonical',id:701,revision:3,local_key:'room-701',name_ru:'Deluxe Room'},
  placement:{basis:'raw',value:'2 ADL + 2 CHD'}
 });
 const encoded=JSON.stringify(tour.rehydration);
 assert.equal(encoded.includes('150000'),false,'price is not part of rehydration identity');
 assert.equal(encoded.includes(digest('a')),false,'short-lived offer identity is excluded');
 assert.equal(encoded.includes(digest('b')),false,'short-lived search identity is excluded');
});

test('placement changes rehydration identity without affecting display authority',()=>{
 const first=stored(),second=stored();
 second.listing.tour.placement.raw='2 ADL + 1 CHD';
 const a=local.parse(payload(first)).hotels[0].offers[0].tour;
 const b=local.parse(payload(second)).hotels[0].offers[0].tour;
 assert.equal(a.cachedListing,true);assert.equal(b.cachedListing,true);
 assert.notDeepEqual(JSON.parse(JSON.stringify(a.rehydration)),JSON.parse(JSON.stringify(b.rehydration)));
 assert.deepEqual(JSON.parse(JSON.stringify(b.rehydration.placement)),{basis:'raw',value:'2 ADL + 1 CHD'});
});

test('price and short-lived supplier identities do not change rehydration identity',()=>{
 const first=stored(),second=stored();
 second.price='171500';second.listing.listingPrice.amount='171500';
 second.listing.identity.offer_ref_digest=digest('e');
 second.listing.identity.search_ref_digest=digest('f');
 const a=local.parse(payload(first)).hotels[0].offers[0].tour.rehydration;
 const b=local.parse(payload(second)).hotels[0].offers[0].tour.rehydration;
 assert.deepEqual(JSON.parse(JSON.stringify(a)),JSON.parse(JSON.stringify(b)));
});

test('bounded raw stay labels are fallback evidence when no accepted stay relation exists',()=>{
 const row=stored();delete row.stayMatch;
 const r=local.parse(payload(row)).hotels[0].offers[0].tour.rehydration;
 assert.deepEqual(JSON.parse(JSON.stringify(r.meal)),{basis:'raw',value:'Ultra All Inclusive'});
 assert.deepEqual(JSON.parse(JSON.stringify(r.room)),{basis:'raw',value:'DELUXE SEA VIEW'});
});

test('malformed party, stay metadata or operator never creates rehydration authority',()=>{
 const cases=[
  row=>{row.listing.tour.party.child_ages=[6];},
  row=>{row.listing.tour.party.child_ages=[6,18];},
  row=>{row.stayMatch.meal.status='accepted';row.stayMatch.meal.canonical.hotelId=999;},
  row=>{row.stayMatch.source='foreign';},
  row=>{row.listing.operator.canonical_name='FUN\nSUN';row.listing.operator.raw='';},
  row=>{row.listing.tour.placement={raw:''};}
 ];
 for(const mutate of cases){
  const row=stored();mutate(row);
  const parsed=local.parse(payload(row));
  assert.ok(parsed,'display-only cached row remains available');
  assert.equal(parsed.offerCount,1);
  assert.equal(parsed.hotels[0].offers[0].tour.rehydration,undefined);
 }
});
