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
 const events=new Map(),calls=[];
 const root={location:new URL('https://anytoour.ru'+pathname),console,URL,AbortController,Set,Map,Promise,
  CustomEvent:class{constructor(type,init){this.type=type;this.detail=init&&init.detail||{};}},
  addEventListener(name,fn){if(!events.has(name))events.set(name,[]);events.get(name).push(fn);},
  dispatchEvent(event){for(const fn of events.get(event.type)||[])fn(event);},
  fetch:fetcher||(()=>Promise.reject(new Error('unexpected fetch'))),
  V2SearchLifecycle:{generation:1,dirty:false,snapshot:{departureId:'1',countryId:'4',dateFrom:'2026-10-05',dateTo:'2026-10-07',nightsFrom:'7',nightsTo:'9',adults:'2',childs:[],meal:'',hotelCategory:'',hotelRating:'',hotelTypes:[],hotelIds:[],hotelServices:[],arrivalId:'',regionIds:[],subregionIds:[],operatorIds:[],priceFrom:'',priceTo:'',currency:'RUB',onlyCharter:'false',onlyDirect:'false'}},
  V2Results:{render(list,options){calls.push({list,options});return list;}}
 };
 root.window=root;root.globalThis=root;
 const context=vm.createContext({window:root,globalThis:root,URL,AbortController,Set,Map,Promise,console});
 vm.runInContext(CODE,context,{filename:'search3-local-db-provider-v1.js'});
 return{root,calls,events};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 let env=fakeRoot('/poisk-turov/');
 assert.equal(env.root.AnyTourLocalDbProviderV1.localCandidate(),false);
 assert.equal(env.root.AnyTourLocalDbProviderV1.endpoint(),null);
 const original=env.root.V2Results.render;original([{id:'1'}]);assert.equal(env.calls.length,1,'non-local route stays unwrapped');

 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/');
 const api=env.root.AnyTourLocalDbProviderV1;
 assert.equal(api.localCandidate(),true);assert.equal(api.endpoint().pathname,'/_preview/search3-local-candidate/data/search3-local-results-read-v1.php');
 const projected=api.project(payload([stored('tourvisor',102,'d',120000),stored('anex',102,'e',125000)]));
 assert.equal(projected.length,1);assert.equal(projected[0].id,'102');assert.equal(projected[0].__anytourDb,true);assert.deepEqual(Array.from(projected[0].providers),['anex','tourvisor']);assert.equal(projected[0].name,'');assert.equal(projected[0].picturelink,'');assert.equal(projected[0].tours.length,2);
 for(const t of projected[0].tours){assert.equal(t.selectionEnabled,false);assert.equal(t.quoteRequired,true);assert.equal(t.finalPriceReady,true);assert.equal(t.cachedListing,true);}
 const malformed=payload([stored('anex',102,'f',125000)]);malformed.hotels[0].offers[0].listing.selection_state='enabled';assert.equal(api.project(malformed),null,'cached listing never gains selection authority');

 let requested=null;
 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/',async(url,options)=>{requested={url:String(url),options};return{ok:true,json:async()=>({ok:true,data:payload([stored('anex',102,'e',125000)])})};});
 const live={id:'102',provider:'tourvisor',mappingStatus:'resolved',name:'Supplier must not matter',providers:['tourvisor'],price:119000,tours:[{id:'tourvisor:live',provider:'tourvisor',price:119000,date:'2026-10-05',nights:7,meal:{name:'AI'},roomType:'STD',placement:'DBL',operator:{name:'Pegas'}}]};
 env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:1}}));await tick();await tick();
 assert.ok(requested);assert.equal(new URL(requested.url).pathname,'/_preview/search3-local-candidate/data/search3-local-results-read-v1.php');assert.equal(requested.options.method,'POST');assert.equal(requested.options.headers['X-Requested-With'],'AnyTourSearch3');
 let rendered=env.calls.at(-1).list;assert.equal(rendered.length,1);assert.equal(rendered[0].tours.length,1,'DB snapshot paints before live provider result');assert.equal(rendered[0].tours[0].cachedListing,true);
 env.root.V2Results.render([live],{empty:false});let merged=env.calls.at(-1).list;assert.equal(merged.length,1);assert.equal(merged[0].tours.length,2,'later live result merges with DB snapshot');assert.ok(merged[0].tours.some(t=>t.cachedListing===true));assert.ok(merged[0].tours.some(t=>t.id==='tourvisor:live'));
 env.root.V2Results.render([live],{empty:true});merged=env.calls.at(-1).list;assert.equal(merged[0].tours.length,2,'later live rerender retains DB offer');
 env.root.V2SearchLifecycle.generation=2;env.root.V2SearchLifecycle.dirty=true;env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:2,dirty:true}}));env.root.V2Results.render([live],{empty:true});assert.equal(env.calls.at(-1).list[0].tours.length,1,'dirty reset clears DB snapshot');

 let emptyFetches=0;
 env=fakeRoot('/_preview/search3-local-candidate/poisk-turov/',async()=>{emptyFetches++;return{ok:true,json:async()=>({ok:true,data:payload([])})}});const before=env.calls.length;env.root.dispatchEvent(new env.root.CustomEvent('v2:search-reset',{detail:{generation:1}}));await tick();await tick();assert.equal(emptyFetches,1);assert.equal(env.calls.length,before,'empty DB does not paint an empty replacement');env.root.V2Results.render([live],{empty:false});assert.equal(env.calls.at(-1).list[0].tours.length,1,'empty DB leaves live path unchanged');

 console.log('SEARCH3_LOCAL_DB_PROVIDER_OK project=2 db_first=1 merge=2 empty_noop=1 route_isolated=1');
})().catch(error=>{console.error(error);process.exit(1);});
