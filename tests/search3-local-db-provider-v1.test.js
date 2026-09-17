'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const CODE=fs.readFileSync(require('node:path').join(__dirname,'../v2/search3-local-db-provider-v1.js'),'utf8');

function stored(provider,legacy,digestChar,price){
 const d=digestChar.repeat(64);
 return{provider,legacyHotelId:legacy,price,currency:'RUB',observedAt:'2026-10-06T10:00:00Z',lastSeenAt:'2026-10-06T10:00:00Z',expiresAt:'2026-10-06T12:00:00Z',listing:{schema_version:1,provider,operator:{raw:provider==='anex'?'ANEX':'Pegas Touristik',canonical_name:provider==='anex'?'ANEX':null},identity:{search_ref_digest:'a'.repeat(64),offer_ref_digest:d,provider_hotel_ref_digest:'b'.repeat(64)},tour:{checkin:'2026-10-05',nights:7,party:{adults:2,children:0,child_ages:[]},meal:{raw:'AI'},room:{raw:'STANDARD ROOM'},placement:{raw:'DBL'}},listingPriceReady:true,listingPrice:{amount:String(price),currency:'RUB'},currency:'RUB',selection_state:'refresh_required',booking_enabled:false}};
}
function payload(offers){return{source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:'c'.repeat(64),selectionAuthority:false,hotelCount:offers.length?1:0,offerCount:offers.length,storedOfferCount:offers.length,withheldOfferCount:0,hotels:offers.length?[{anytourHotelId:77,hotel:{id:77,catalog:'anytour',revision:1,name:'Own Hotel'},offers}]:[]};}
function fakeRoot(pathname,fetcher){
 const events=new Map(),renders=[],ownerCalls={clear:[],hotels:[],offers:[],refreshes:0};
 const owner={
  clearOffers(source){ownerCalls.clear.push(source);},
  upsertHotel(hotel){ownerCalls.hotels.push(hotel);},
  upsertOffer(anytourHotelId,tour,meta){ownerCalls.offers.push({anytourHotelId,tour,meta});},
  refresh(){ownerCalls.refreshes++;}
 };
 const root={location:new URL('https://anytoour.ru'+pathname),console,URL,AbortController,Set,Map,Promise,
  CustomEvent:class{constructor(type,init){this.type=type;this.detail=init&&init.detail||{};}},
  addEventListener(name,fn){if(!events.has(name))events.set(name,[]);events.get(name).push(fn);},
  dispatchEvent(event){for(const fn of events.get(event.type)||[])fn(event);},
  fetch:fetcher||(()=>Promise.reject(new Error('unexpected fetch'))),
  Search3CanonicalProfilesV1:{current:()=>owner},
  V2SearchLifecycle:{generation:1,dirty:false,snapshot:{departureId:'1',countryId:'4',dateFrom:'2026-10-05',dateTo:'2026-10-07',nightsFrom:'7',nightsTo:'9',adults:'2',childs:[],meal:'',hotelCategory:'',hotelRating:'',hotelTypes:[],hotelIds:[],hotelServices:[],arrivalId:'',regionIds:[],subregionIds:[],operatorIds:[],priceFrom:'',priceTo:'',currency:'RUB',onlyCharter:'false',onlyDirect:'false'}},
  V2Results:{render(list,options){renders.push({list,options});return list;}}
 };
 root.window=root;root.globalThis=root;
 const originalRender=root.V2Results.render;
 const context=vm.createContext({window:root,globalThis:root,URL,AbortController,Set,Map,Promise,console});
 vm.runInContext(CODE,context,{filename:'search3-local-db-provider-v1.js'});
 return{root,renders,events,ownerCalls,owner,originalRender};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 let env=fakeRoot('/poisk-turov/');
 assert.equal(env.root.AnyTourLocalDbProviderV1.localCandidate(),false);
 assert.equal(env.root.AnyTourLocalDbProviderV1.endpoint(),null);
 assert.equal(env.root.V2Results.render,env.originalRender,'non-local route stays unwrapped');
 env.root.V2Results.render([{id:'1'}]);assert.equal(env.renders.length,1);

 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/');
 const api=env.root.AnyTourLocalDbProviderV1;
 assert.equal(api.localCandidate(),true);assert.equal(api.endpoint().pathname,'/_preview/search3-local-candidate/data/search3-local-results-read-v1.php');
 assert.equal(env.root.V2Results.render,env.originalRender,'LOCAL provider must not monkey-patch renderer');
 const parsed=api.parse(payload([stored('tourvisor',102,'d',120000),stored('anex',102,'e',125000)]));
 assert.ok(parsed);assert.equal(parsed.hotels.length,1);assert.equal(parsed.hotels[0].anytourHotelId,'77');assert.equal(parsed.hotels[0].hotel.name,'Own Hotel');assert.equal(parsed.hotels[0].offers.length,2);
 for(const item of parsed.hotels[0].offers){assert.equal(item.legacyHotelId,'102');assert.equal(item.tour.selectionEnabled,false);assert.equal(item.tour.quoteRequired,true);assert.equal(item.tour.finalPriceReady,true);assert.equal(item.tour.cachedListing,true);}
 assert.equal(api.project,undefined,'retired fake Tourvisor projection stays deleted');assert.equal(api.merge,undefined,'retired renderer merge stays deleted');
 const malformed=payload([stored('anex',102,'f',125000)]);malformed.hotels[0].offers[0].listing.selection_state='enabled';assert.equal(api.parse(malformed),null,'cached listing never gains selection authority');

 const direct=api.apply(env.owner,payload([stored('anex',102,'e',125000)]));
 assert.ok(direct);assert.deepEqual(env.ownerCalls.clear,['local-db']);assert.equal(env.ownerCalls.hotels.length,1);assert.equal(env.ownerCalls.offers.length,1);assert.equal(env.ownerCalls.offers[0].anytourHotelId,'77');assert.equal(env.ownerCalls.offers[0].meta.source,'local-db');assert.equal(env.ownerCalls.refreshes,1);assert.equal(env.renders.length,0,'apply only invalidates canonical owner');

 let requested=null;
 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/',async(url,options)=>{requested={url:String(url),options};return{ok:true,json:async()=>({ok:true,data:payload([stored('anex',102,'e',125000)])})};});
 env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:1}}));await tick();await tick();
 assert.ok(requested);assert.equal(new URL(requested.url).pathname,'/_preview/search3-local-candidate/data/search3-local-results-read-v1.php');assert.equal(requested.options.method,'POST');assert.equal(requested.options.headers['X-Requested-With'],'AnyTourSearch3');
 assert.deepEqual(env.ownerCalls.clear,['local-db']);assert.equal(env.ownerCalls.hotels.length,1);assert.equal(env.ownerCalls.offers.length,1);assert.equal(env.ownerCalls.refreshes,1);assert.equal(env.renders.length,0,'lifecycle fetch never paints outside canonical owner');
 const beforeDirty={clear:env.ownerCalls.clear.length,refresh:env.ownerCalls.refreshes};env.root.V2SearchLifecycle.generation=2;env.root.V2SearchLifecycle.dirty=true;env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:2,dirty:true}}));await tick();assert.equal(env.ownerCalls.clear.length,beforeDirty.clear);assert.equal(env.ownerCalls.refreshes,beforeDirty.refresh,'canonical owner owns reset state');

 let emptyFetches=0;
 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/',async()=>{emptyFetches++;return{ok:true,json:async()=>({ok:true,data:payload([])})}});env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:1}}));await tick();await tick();assert.equal(emptyFetches,1);assert.deepEqual(env.ownerCalls.clear,['local-db']);assert.equal(env.ownerCalls.hotels.length,0);assert.equal(env.ownerCalls.offers.length,0);assert.equal(env.ownerCalls.refreshes,1,'empty DB snapshot clears only local-db canonical offers and rerenders source state');assert.equal(env.renders.length,0);

 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/',async()=>({ok:false,json:async()=>({ok:false,error:'fixture_failure'})}));env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:1}}));await tick();await tick();assert.deepEqual(env.ownerCalls.clear,['local-db']);assert.equal(env.ownerCalls.refreshes,1,'failed DB refresh removes stale local-db offers through canonical owner');assert.equal(env.renders.length,0);

 console.log('SEARCH3_LOCAL_DB_PROVIDER_OK parse=2 apply=1 lifecycle=1 empty_clear=1 error_clear=1 route_isolated=1 no_renderer_patch=1');
})().catch(error=>{console.error(error);process.exit(1);});
