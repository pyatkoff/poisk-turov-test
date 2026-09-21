'use strict';
// Exercises the real projection, LOCAL parser and existing pure result predicates.
// Fictional offers only; no browser or live supplier acceptance is claimed.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const v2=path.resolve(__dirname,'../v2');
const window={location:new URL('https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'),V2Runtime:{api(){throw Error('Unexpected supplier call');}},Search3CanonicalProfilesV1:{create:()=>({})}};
const context=vm.createContext({window,URL,structuredClone,console});
for(const file of ['search3-local-db-provider-v1.js','prototype-search/data.js'])vm.runInContext(fs.readFileSync(path.join(v2,file),'utf8'),context,{filename:file});
const data=window.AnyTourPrototypeData;
data.catalog.meals.push({id:7,name:'AI',fullName:'Все Включено'},{id:8,name:'UAI',fullName:'Ультра Все Включено'},{id:5,name:'HB',fullName:'Полупансион'},{id:2,name:'BB',fullName:'Завтраки'});
data.catalog.departures.push({id:1,name:'Москва'});data.catalog.countries.push({id:4,name:'Турция'});
const search={origin:'Москва',country:'4',from:'2026-09-29',to:'2026-10-05',minNights:7,maxNights:7,adults:2,ages:[]};
const filters={hotelId:0,q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],min:0,max:600000,rating:false,beach:false,family:false,spa:false};
context.state={search,filters,selectedDate:null,onlyFavorites:false,favorites:[],sort:'recommended'};
context.ratingValue=h=>h.rating;
const app=fs.readFileSync(path.join(v2,'prototype-search/app.js'),'utf8');
const start=app.indexOf('function hotelMatch('),end=app.indexOf('function filterCount(',start);
assert.ok(start>=0&&end>start,'Use the actual app predicates, never copy their implementation into the test');
vm.runInContext(app.slice(start,end),context,{filename:'app.js:result-predicates'});
const base={date:'2026-09-29',nights:7,adults:2,childs:0,roomType:'STANDARD',placement:'DBL',operator:{name:'ANEX'},price:120000};
const rawHotel={id:4234,anytourHotelId:4234,canonicalLegacyIds:[101],name:'Fictional meal regression hotel',country:{name:'Турция'},region:{name:'Бодрум'},category:5,rating:4.7,tours:[]};
const aiValues=['AI','all inclusive','All Inclusive','всё включено',' ВСЕ  ВКЛЮЧЕНО ',{name:'AI',fullName:'Все включено'}];
const ultraValues=['UAI','Ultra All Inclusive','ультра всё включено',' УЛЬТРА  ВСЕ ВКЛЮЧЕНО ',{name:'UAI',fullName:'Ультра все включено'}];
rawHotel.tours=[...aiValues.map((meal,i)=>({...base,id:'ai-'+i,meal,price:120000+i})),...ultraValues.map((meal,i)=>({...base,id:'uai-'+i,meal,price:140000+i})),{...base,id:'breakfast',meal:{name:'BB'},price:80000},{...base,id:'unknown',meal:{name:'Питание уточняется'},price:70000}];
const original=JSON.stringify(rawHotel);
const h=data.project([rawHotel],search)[0];context.hotels=[h];
const choiceAI=data.meal(data.catalog.meals[0]),choiceUAI=data.meal(data.catalog.meals[1]);
filters.meals=[choiceAI,choiceUAI];
const matched=context.hotelOffers(h);
const aliasOnly={...h,offers:h.offers.filter(o=>/^(?:ai-[1-5]|uai-[1-4])$/.test(o.raw.id))};
console.log(JSON.stringify({aliasOnlyBeforeFilter:aliasOnly.offers.length,aliasOnlyAfterBoth:context.hotelOffers(aliasOnly).length}));
console.log(JSON.stringify({choices:filters.meals,projected:h.offers.map(o=>o.meal),all:h.offers.length,both:matched.length}));
assert.equal(context.hotelOffers(aliasOnly).length,9,'All raw-alias offers must not disappear when AI/UAI are selected');
assert.equal(matched.length,aiValues.length+ultraValues.length,'AI plus UAI must retain their union despite raw labels, without breakfast/unknown');
filters.meals=[choiceAI];assert.equal(context.hotelOffers(h).length,aiValues.length,'AI alone remains distinct from UAI');
filters.meals=[choiceUAI];assert.equal(context.hotelOffers(h).length,ultraValues.length,'UAI alone must not admit plain AI');
filters.meals=[];assert.equal(context.hotelOffers(h).length,rawHotel.tours.length,'Clearing meals restores all other eligible offers');
assert.equal(JSON.stringify(rawHotel),original,'Raw source labels/room/price/identity must never be rewritten');
for(const value of aiValues)assert.equal(data.meal(value),choiceAI);
for(const value of ultraValues)assert.equal(data.meal(value),choiceUAI);
for(const unknown of ['Premium AI','AI+','Ultra special','Без всё включено','NOT ALL INCLUSIVE'])assert.equal(data.meal({name:unknown}),unknown,'Do not guess unknown/premium categories from substrings');
assert.equal(data.params(search,[],{meals:[choiceAI]}).meal,'7');
assert.equal(data.params(search,[],{meals:[choiceUAI]}).meal,'8');
assert.equal(data.params(search,[],{meals:[choiceAI,choiceUAI]}).meal,'','Do not put two IDs in the existing single-meal API contract');
assert.equal(data.params(search,[],{meals:[]}).meal,'');
assert.equal(data.meal({id:7,name:'Premium AI'}),'Premium AI','A shared numeric id must not rewrite a specific unknown label');
assert.equal(data.meal({id:7,name:'AI',fullName:'Premium All Inclusive'}),'Premium All Inclusive','An explicit different fullName stays specific');
assert.equal(data.meal({id:7}),choiceAI,'An id-only record still resolves through the catalogue');
const originalMealRecords=[...data.catalog.meals];
data.catalog.meals.splice(0,data.catalog.meals.length,{id:7,name:'AI',russianName:'Всё включено',fullName:'All Inclusive'},{id:8,name:'UAI',russianName:'Ультра всё включено',fullName:'Ultra All Inclusive'});
assert.equal(data.meal('All Inclusive'),'Всё включено','Prefer catalogue Russian display text');
assert.equal(data.meal('UAI'),'Ультра всё включено');
data.catalog.meals.splice(0,data.catalog.meals.length,...originalMealRecords);

// Read each source through the actual DB parser, then the shared projection/filter/calendar.
for(const provider of ['tourvisor','anex','andromeda']){
 const tours=['all inclusive','Ultra All Inclusive','Завтраки'].map((meal,i)=>{
  const row={provider,legacyHotelId:101,price:[125000,155000,80000][i],currency:'RUB',listing:{schema_version:1,provider,currency:'RUB',selection_state:'refresh_required',booking_enabled:false,listingPriceReady:true,listingPrice:{amount:String([125000,155000,80000][i]),currency:'RUB'},identity:{search_ref_digest:'a'.repeat(64),offer_ref_digest:String(i+1).repeat(64),provider_hotel_ref_digest:'b'.repeat(64)},tour:{checkin:'2026-09-29',nights:7,meal:{raw:meal},room:{raw:'STANDARD'},placement:{raw:'DBL'},party:{adults:2,children:0,child_ages:[]}},operator:{raw:'Fictional operator'}}};
  const tour=window.AnyTourLocalDbProviderV1.offerTour(row);assert.ok(tour);assert.equal(tour.selectionEnabled,false);return tour;
 });
 const group=data.project([{...rawHotel,tours}],search)[0];context.hotels=[group];filters.meals=[choiceAI,choiceUAI];
 assert.equal(context.hotelOffers(group).length,2,provider+' stored meals join by normalized label, not raw spelling');
 assert.equal(context.minimumForDay('2026-09-29'),125000,'Calendar minimum excludes cheaper breakfast');
 filters.max=130000;filters.meals=[choiceUAI];assert.equal(context.hotelOffers(group).length,0,'Meal+budget must apply to the same offer, not two different hotel rows');
 filters.max=600000;filters.meals=[choiceAI];assert.equal(context.hotelOffers(group).length,1);
}
console.log('Prototype meals: AI, UAI, OR, reset, same-offer budget, TV/ANEX/SAMO stored rows and calendar PASS; supplier/lead/DB writes 0.');
