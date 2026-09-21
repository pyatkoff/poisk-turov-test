'use strict';
// Real LOCAL parser and prototype projection; fictional server replies, no network.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const own=4234,legacy=101,source='anytour-hotel-stay-v2';
const profile={id:own,catalog:'anytour',name:'Наш отель',category:5,country:{name:'Турция'}};
const trip={origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-02',minNights:7,maxNights:7,adults:2,ages:[]};
const concept=(kind,id,nameRu)=>({kind,id,hotelId:own,nameRu,localKey:`local-${id}`,revision:3,facts:{}});
function row(provider,id,price=133500.5){
 return{provider,legacyHotelId:legacy,price,currency:'RUB',
  listing:{schema_version:1,provider,currency:'RUB',selection_state:'refresh_required',booking_enabled:false,listingPriceReady:true,listingPrice:{amount:String(price),currency:'RUB'},
   identity:{offer_ref_digest:String(id).padStart(64,'0'),search_ref_digest:'b'.repeat(64),provider_hotel_ref_digest:'c'.repeat(64)},operator:{raw:'ANEX'},
   tour:{checkin:'2026-10-01',nights:7,party:{adults:2,children:0,child_ages:[]},meal:{raw:`native-meal-${provider}-${id}`},room:{raw:`native-room-${provider}`},placement:{raw:'DBL'}}},
  stayMatch:{source,exactScope:true,room:{status:'accepted',canonical:concept('room',901,'Семейный с видом на море')},meal:{status:'accepted',canonical:concept('meal',id,'Одинаковая подпись, разные ID')}}};
}
const rows=['tourvisor','anex','andromeda'].flatMap(p=>[row(p,501),row(p,502,153500.5)]);
function reply(items=rows){return{source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:'e'.repeat(64),selectionAuthority:false,hotels:[{anytourHotelId:own,hotel:profile,offers:items}]};}
let readCalls=0;
const window={location:{href:'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'},V2Runtime:{api(){throw Error('No supplier request allowed');}},Search3CanonicalProfilesV1:{create:()=>({})}};
const context=vm.createContext({window,URL,structuredClone,DOMException,AbortController,setTimeout,clearTimeout,fetch:async(url,options)=>{
 readCalls++;assert.match(url,/search3-local-results-read-v1.php$/);const params=JSON.parse(options.body).params;
 return{ok:true,json:async()=>({ok:true,data:{...reply(),scope:{scopeVersion:1,...params}}})};
}});
for(const file of ['search3-local-db-provider-v1.js','prototype-search/data.js'])vm.runInContext(fs.readFileSync(path.resolve(__dirname,'../v2',file),'utf8'),context,{filename:file});
const parser=window.AnyTourLocalDbProviderV1,data=window.AnyTourPrototypeData;
data.catalog.departures.push({id:1,name:'Москва'});data.catalog.countries.push({id:4,name:'Турция'});
function projected(input){const p=parser.parse(input);assert.ok(p);return data.project(p.hotels.map(g=>({...g.hotel,anytourHotelId:g.anytourHotelId,tours:g.offers.map(x=>x.tour)})),trip);}
(async()=>{
 const before=JSON.stringify(rows),parsed=parser.parse(reply());assert.equal(parsed.offerCount,6);
 for(const {tour}of parsed.hotels[0].offers){
  assert.equal(tour.localStay?.meal?.id,tour.meal.name.endsWith('501')?501:502,'Accepted local meal ID survives parsing');
  assert.equal(tour.localStay.room.id,901);assert.equal(tour.localStay.meal.hotelId,own);
  assert.equal(tour.meal.name.startsWith('native-meal-'),true,'Raw supplier label stays intact');
  assert.equal(tour.roomType,`native-room-${tour.provider}`);
  assert.equal(tour.selectionEnabled,false);assert.equal(tour.cachedListing,true);
 }
 const offers=projected(reply())[0].offers;
 assert.deepEqual(Array.from(offers,x=>x.mealId),[501,502,501,502,501,502]);
 for(const offer of offers){assert.equal(offer.roomId,901);assert.equal(offer.stayCatalog,source);assert.equal(offer.room,'Семейный с видом на море');assert.equal(offer.meal,'Одинаковая подпись, разные ID');assert.equal(offer.fuel,null);assert.equal(offer.cached,true);}
 assert.deepEqual(Array.from(offers,x=>x.total),[133500.5,153500.5,133500.5,153500.5,133500.5,153500.5]);
 assert.equal(JSON.stringify(rows),before,'Neither parser nor projection mutates source facts');
 for(const change of [
  x=>delete x.stayMatch,x=>x.stayMatch.exactScope=false,x=>x.stayMatch.source='unknown',
  x=>x.stayMatch.meal.status='pending',x=>x.stayMatch.meal.status='conflict',
  x=>x.stayMatch.meal.canonical.hotelId=own+1,x=>x.stayMatch.meal.canonical.kind='room',
  x=>x.stayMatch.meal.canonical.id='AI',x=>x.stayMatch.meal.canonical.revision=0,
  x=>x.stayMatch.meal.canonical.nameRu='',x=>x.stayMatch.meal.canonical.localKey=''
 ]){const item=structuredClone(rows[0]);change(item);const offer=projected(reply([item]))[0].offers[0];assert.equal(offer.mealId,null,'No label, foreign hotel, malformed or unreviewed fact becomes a local ID');assert.equal(offer.meal,item.listing.tour.meal.raw);}
 const wrongRoom=structuredClone(rows[0]);wrongRoom.stayMatch.room.canonical.hotelId=own+1;
 const isolated=projected(reply([wrongRoom]))[0].offers[0];assert.equal(isolated.roomId,null);assert.equal(isolated.mealId,501,'An invalid room does not erase an independent accepted meal');
 const calendar=await data.calendar(trip,trip.from,trip.to);
 assert.equal(readCalls,1);assert.deepEqual(Array.from(calendar[0].offers,x=>x.mealId),[501,502,501,502,501,502],'The real DB calendar path retains the same local IDs');
 const received=[];const owner={clearOffers(){},upsertHotel(){},upsertOffer(h,t){received.push([h,t.localStay?.meal?.id]);},refresh(){}};parser.apply(owner,reply());
 assert.deepEqual(received,[[String(own),501],[String(own),502],[String(own),501],[String(own),502],[String(own),501],[String(own),502]],'Canonical owner receives the mapped IDs');
 console.log('LOCAL parser → canonical owner/prototype/calendar: local meal/room IDs, exact hotel scope, unchanged raw facts/prices/quote guards PASS');
 console.log('Not a browser or native-request acceptance; supplier HTTP/DB writes/leads: 0.');
})().catch(e=>{console.error(e);process.exitCode=1;});
