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
const definitions=[
 ['no-meals','Без питания'],['breakfast','Завтраки'],['half-board','Полупансион'],['half-board-plus','Полупансион плюс'],
 ['full-board','Полный пансион'],['full-board-plus','Полный пансион плюс'],['all-inclusive','Всё включено'],
 ['ultra-all-inclusive','Ультра всё включено'],['soft-all-inclusive','Мягкое всё включено'],['alcohol-free-all-inclusive','Всё включено без алкоголя']
];
const plans=definitions.map(([code,nameRu],index)=>Object.freeze({id:index+1,code,nameRu,nativeIds:Object.freeze(['native-'+index])}));
Object.freeze(plans);
const plain=value=>JSON.parse(JSON.stringify(value)),before=JSON.stringify(plans),groups=plain(api.mealFilterGroups(plans));
assert.equal(api.version,4,'the additive presentation helper does not change listing version behavior');
assert.deepEqual(groups.map(group=>[group.key,group.nameRu,group.members.map(member=>member.id)]),[
 ['meal-family:no-meals','Без питания',[1]],['meal-family:breakfast','Завтраки',[2]],
 ['meal-family:half-board','Полупансион',[3,4]],['meal-family:full-board','Полный пансион',[5,6]],
 ['meal-family:all-inclusive','Всё включено',[7,8,9,10]]
],'LOCAL families combine only the explicit canonical taxonomy');
assert.deepEqual(groups.at(-1).members,plans.slice(6).map(({id,code,nameRu})=>({id,code,nameRu})),
 'ultra/soft/alcohol-free qualifiers retain their exact canonical IDs, codes and names');
assert.equal(JSON.stringify(plans),before,'frozen source plans and provider native IDs are untouched');
groups.at(-1).members[0].nameRu='changed output';
assert.equal(plans[6].nameRu,'Всё включено','output contains fresh presentation descriptors');
const custom={id:99,code:'hotel-premium-ai',nameRu:'Питание отеля',nativeIds:['AI']};
assert.deepEqual(plain(api.mealFilterGroups([plans[6],custom])),[
 {key:'meal-family:all-inclusive',nameRu:'Всё включено',members:[{id:7,code:'all-inclusive',nameRu:'Всё включено'}]},
 {key:'meal-plan:99',nameRu:'Питание отеля',members:[{id:99,code:'hotel-premium-ai',nameRu:'Питание отеля'}]}
],'a custom canonical code is not guessed from its name or native aliases');
assert.deepEqual(plain(api.mealFilterGroups([plans[7],plans[8],plans[9]])),plans.slice(7).map(({id,code,nameRu})=>({
 key:'meal-plan:'+id,nameRu,members:[{id,code,nameRu}]
})),'without the canonical base there is no invented family label or broadening');
const renamed=plans.map(plan=>plan.id===7?{...plan,nameRu:'Название из LOCAL'}:plan);
assert.equal(api.mealFilterGroups(renamed).at(-1).nameRu,'Название из LOCAL','family labels come from canonical base content');
const sameNames=[{id:101,code:'custom-a',nameRu:'Одинаковое название'},{id:102,code:'custom-b',nameRu:'Одинаковое название'}];
assert.deepEqual(plain(api.mealFilterGroups(sameNames)).map(group=>group.key),['meal-plan:101','meal-plan:102'],
 'equal labels never establish canonical identity');
for(const invalid of [
 null,{},'AI',[{meal:'AI'}],[null],[{id:'7',code:'all-inclusive',nameRu:'Всё включено'}],
 [{id:0,code:'all-inclusive',nameRu:'Всё включено'}],[{id:7,code:'AI',nameRu:'Всё включено'}],
 [{id:7,code:'all-inclusive',nameRu:''}],[{id:7,code:'all-inclusive',nameRu:'Bad\u0000name'}],
 [plans[6],plans[6]],[plans[6],{...plans[6],nameRu:'Conflict'}],
 [plans[6],{id:88,code:'all-inclusive',nameRu:'Another plan'}],
 [plans[6],{id:7,code:'another-code',nameRu:'Another plan'}]
])assert.deepEqual(plain(api.mealFilterGroups(invalid)),[],'malformed or ambiguous catalogues cannot create filter groups');
assert.deepEqual(plain(api.mealFilterGroups([])),[]);
console.log('SEARCH3_LOCAL_DB_MEAL_PLAN_OK accepted_relation=1 raw_detail_preserved=1 stale_rejected=1 on_request_not_promoted=1 canonical_filter_families=1 exact_qualifiers_preserved=1 ambiguous_catalogue_rejected=1');
