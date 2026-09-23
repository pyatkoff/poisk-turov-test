'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const window={location:{protocol:'https:',hostname:'anytoour.ru',pathname:'/_preview/search3-local-candidate/prototype-search/',href:'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'}};
vm.runInContext(fs.readFileSync(path.resolve(__dirname,'../v2/search3-local-db-provider-v1.js'),'utf8'),vm.createContext({window,URL,AbortController,DOMException,console}));
const api=window.AnyTourLocalDbProviderV1,hotelId=4234;
const digest=c=>String(c).repeat(64);
const concept=(id,name='Премиальное питание')=>({kind:'meal',id,hotelId,nameRu:name,localKey:'meal-'+id,revision:3});
function stored(rawMeal='Premium Ultra All Inclusive'){
  const c=concept(901);
  return {provider:'andromeda',legacyHotelId:77,price:150000,currency:'RUB',
    listing:{schema_version:1,provider:'andromeda',currency:'RUB',selection_state:'refresh_required',booking_enabled:false,
      listingPriceReady:false,listingPriceState:'search_price_confirmation_required',priceConfirmationRequired:true,quoteState:'unknown',finalPriceVerified:false,
      listingPrice:{amount:'150000',currency:'RUB'},
      identity:{offer_ref_digest:digest('a'),search_ref_digest:digest('b'),provider_hotel_ref_digest:digest('c')},
      operator:{raw:'FUN&SUN'},tour:{checkin:'2026-10-01',nights:7,party:{adults:2,children:0},meal:{raw:rawMeal},room:{raw:'DBL'},placement:{raw:'2 ADL'}}},
    stayMatch:{source:'anytour-hotel-stay-v2',exactScope:true,meal:{status:'accepted',canonical:c}},
    searchMeal:{source:'anytour-search-meal-v1',hotelId,conceptId:c.id,conceptRevision:c.revision,plan:{id:7,code:'all-inclusive',nameRu:'Всё включено'}}
  };
}
function reply(row){return{source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:digest('d'),selectionAuthority:false,hotels:[{anytourHotelId:hotelId,hotel:{id:hotelId,catalog:'anytour',name:'Fixture'},offers:[row]}]};}
let parsed=api.parse(reply(stored()));assert.ok(parsed);let tour=parsed.hotels[0].offers[0].tour;
assert.equal(tour.meal.name,'Premium Ultra All Inclusive','raw branded wording remains offer detail');
assert.deepEqual({...tour.localStay.meal},{kind:'meal',id:901,hotelId,nameRu:'Премиальное питание',localKey:'meal-901',revision:3});
assert.deepEqual({...tour.searchMealPlan},{id:7,code:'all-inclusive',nameRu:'Всё включено'},'accepted membership carries canonical top-level plan');
for(const mutate of [
 r=>r.searchMeal.source='bad',
 r=>r.searchMeal.hotelId=999,
 r=>r.searchMeal.conceptRevision=4,
 r=>r.searchMeal.plan.id='AI',
 r=>r.searchMeal.plan.nameRu='',
 r=>r.stayMatch.meal.status='pending'
]){
 const row=stored();mutate(row);const result=api.parse(reply(row));assert.ok(result);const t=result.hotels[0].offers[0].tour;
 assert.equal(t.searchMealPlan,undefined,'unaccepted/stale membership never becomes canonical meal facet authority');
}
const request=stored('On Request');delete request.searchMeal;parsed=api.parse(reply(request));tour=parsed.hotels[0].offers[0].tour;
assert.equal(tour.meal.name,'On Request','raw provider fact is preserved');
assert.equal(tour.searchMealPlan,undefined,'On Request is not promoted to a meal plan without an accepted relation');
console.log('SEARCH3_LOCAL_DB_MEAL_PLAN_OK accepted_relation=1 raw_detail_preserved=1 stale_rejected=1 on_request_not_promoted=1');
