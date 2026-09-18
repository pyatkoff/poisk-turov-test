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
function stateRow(state,provider='anex',digestChar='e'){
 const row=stored(provider,102,digestChar,125000),verified=state==='final_verified',confirmation=state==='search_price_confirmation_required';
 Object.assign(row.listing,{listingPriceState:state,listingPriceReady:!confirmation,priceConfirmationRequired:confirmation,quoteState:verified?'verified':'unknown',finalPriceVerified:verified,quoteEvidenceDigest:verified?'f'.repeat(64):null});
 return row;
}
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

 const states=['final_ready_estimate','final_verified','search_price_confirmation_required'];
 const mixed=payload([stored('tourvisor',102,'d',120000),...states.map((state,i)=>stateRow(state,['anex','andromeda','anex'][i],['e','f','a'][i]))]);
 const mixedBefore=JSON.stringify(mixed),mixedParsed=api.parse(mixed);
 assert.ok(mixedParsed,'one valid confirmation-required offer must not discard the whole DB response');
 assert.equal(mixedParsed.offerCount,4);assert.equal(JSON.stringify(mixed),mixedBefore,'supplier facts and amounts stay untouched');
 for(const {tour} of mixedParsed.hotels[0].offers){
  assert.equal(tour.selectionEnabled,false);assert.equal(tour.quoteRequired,true);
  assert.equal(tour.finalPriceReady,tour.listingPriceState!=='search_price_confirmation_required');
  assert.equal(tour.priceNeedsConfirmation,tour.listingPriceState==='search_price_confirmation_required');
  assert.equal(tour.finalPriceVerified,undefined,'historical verified cache never supplies current quote authority');
  assert.equal(tour.offerRef,undefined);assert.equal(tour.offerContext,undefined);
 }
 const confirmation=mixedParsed.hotels[0].offers[3].tour;
 assert.equal(confirmation.price,125000);assert.equal(confirmation.roomType,'STANDARD ROOM');assert.equal(confirmation.meal.name,'AI');assert.equal(confirmation.date,'2026-10-05');assert.equal(confirmation.nights,7);
 assert.equal(confirmation.id,'cached:anex:'+ 'a'.repeat(64));assert.equal(confirmation.isCharter,undefined,'price readiness does not imply flight type');
 for(const state of states){
  const row=stateRow(state);
  const mutations=[{listingPriceState:'unknown'},{listingPriceReady:!row.listing.listingPriceReady},{priceConfirmationRequired:!row.listing.priceConfirmationRequired},{quoteState:'bookable'},{finalPriceVerified:!row.listing.finalPriceVerified},{quoteEvidenceDigest:state==='final_verified'?null:'a'.repeat(64)},{booking_enabled:true},{selection_state:'enabled'}];
  for(const mutation of mutations){const bad=structuredClone(row);Object.assign(bad.listing,mutation);assert.equal(api.parse(payload([stored('tourvisor',102,'d',120000),bad])),null,JSON.stringify({state,mutation}));}
 }
 const invalidCalls=JSON.stringify(env.ownerCalls),invalid=stateRow('search_price_confirmation_required');invalid.listing.quoteState='verified';
 assert.equal(api.apply(env.owner,payload([invalid])),null);assert.equal(JSON.stringify(env.ownerCalls),invalidCalls,'invalid payload cannot partially replace canonical offers');

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


 // Identity-less legacy listings are unsupported rows, not authority for new IDs.
 const legacy=stored('tourvisor',102,'b',100000);delete legacy.listing.identity;
 const withLegacy=payload([legacy,stored('tourvisor',102,'d',120000),stored('anex',102,'e',125000),stored('andromeda',102,'f',126000)]);
 const legacyOnlyGroup=structuredClone(withLegacy.hotels[0]);
 legacyOnlyGroup.anytourHotelId=88;legacyOnlyGroup.hotel.id=88;legacyOnlyGroup.hotel.name='Unsupported legacy hotel';legacyOnlyGroup.offers=[structuredClone(legacy)];
 withLegacy.hotels.push(legacyOnlyGroup);withLegacy.hotelCount=2;withLegacy.offerCount=5;withLegacy.storedOfferCount=7;withLegacy.withheldOfferCount=2;
 const legacyBefore=JSON.stringify(withLegacy),supported=api.parse(withLegacy);
 assert.ok(supported,'identity-less legacy row must not hide valid independent offers');
 assert.equal(supported.hotels.length,1,'no empty legacy-only hotel is projected');
 assert.equal(supported.offerCount,3,'only admitted rows are counted');
 assert.equal(supported.withheldOfferCount,2,'unsupported legacy rows are counted separately');
 assert.equal(JSON.stringify(withLegacy),legacyBefore,'legacy and supported source rows remain immutable');
 assert.deepEqual(Array.from(supported.hotels[0].offers,item=>item.tour.provider),['tourvisor','anex','andromeda']);
 for(const {tour} of supported.hotels[0].offers){assert.equal(tour.selectionEnabled,false);assert.equal(tour.quoteRequired,true);assert.notEqual(tour.price,100000,'legacy minimum never supplies displayed price');}
 assert.equal(api.offerTour(legacy),null,'no identity is synthesized for the unsupported row');
 assert.equal(api.parse(payload([legacy])).offerCount,0);
 assert.equal(api.parse(payload([legacy])).hotels.length,0);
 assert.equal(api.parse(payload([])).withheldOfferCount,0);
 for(const identity of [null,{},[],{offer_ref_digest:'bad'}]){
  const bad=structuredClone(legacy);bad.listing.identity=identity;
  assert.equal(api.parse(payload([stored('anex',102,'e',125000),bad])),null,'present malformed identity is not treated as legacy');
 }
 for(const mutation of [{selection_state:'enabled'},{booking_enabled:true},{schema_version:9},{provider:'foreign'},{listingPriceReady:false},{listingPrice:99999}]){
  const bad=structuredClone(legacy);Object.assign(bad.listing,mutation);
  assert.equal(api.parse(payload([stored('anex',102,'e',125000),bad])),null,'legacy exception cannot bypass listing safety: '+JSON.stringify(mutation));
 }
 const wrongHotel=structuredClone(withLegacy);wrongHotel.hotels[1].hotel.id=999;
 assert.equal(api.parse(wrongHotel),null,'canonical group identity remains mandatory even for a withheld-only group');
 const atLimit=payload(Array(20000).fill(legacy));assert.ok(api.parse(atLimit),'row budget includes legacy rows');
 atLimit.hotels[0].offers.push(legacy);assert.equal(api.parse(atLimit),null,'withholding does not bypass maximum scan budget');
 const complete=[];
 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/',async()=>({ok:true,json:async()=>({ok:true,data:withLegacy})}));
 env.root.addEventListener('v2:provider-status',event=>complete.push(event.detail));
 env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:1}}));await tick();await tick();
 assert.equal(env.ownerCalls.hotels.length,1);assert.equal(env.ownerCalls.offers.length,3);assert.equal(env.ownerCalls.refreshes,1,'admitted snapshot is applied once through existing owner');
 assert.equal(complete.at(-1).status,'complete');assert.equal(complete.at(-1).hotels,1);assert.equal(complete.at(-1).offers,3);
 assert.equal(complete.at(-1).storedOffers,7);assert.equal(complete.at(-1).withheldOffers,4,'server-withheld plus client-unsupported counts are explicit');
 let resolveOld;
 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/',()=>new Promise(resolve=>{resolveOld=resolve;}));
 env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:1}}));
 env.root.V2SearchLifecycle.generation=2;env.root.V2SearchLifecycle.dirty=true;
 env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:2,dirty:true}}));
 resolveOld({ok:true,json:async()=>({ok:true,data:withLegacy})});await tick();await tick();
 assert.equal(env.ownerCalls.offers.length,0);assert.equal(env.ownerCalls.refreshes,0,'late legacy-containing response cannot repaint a dirty generation');
 console.log('SEARCH3_LOCAL_LEGACY_ROW_ISOLATION_OK providers=3 withheld=2 malformed_rejected=1 truthful_counts=1 immutable=1 generation_guard=1');

 console.log('SEARCH3_LOCAL_DB_PROVIDER_OK parse=2 apply=1 lifecycle=1 empty_clear=1 error_clear=1 route_isolated=1 no_renderer_patch=1');
})().catch(error=>{console.error(error);process.exit(1);});
