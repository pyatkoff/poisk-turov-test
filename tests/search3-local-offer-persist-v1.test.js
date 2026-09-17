'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const CODE=fs.readFileSync(path.join(__dirname,'../v2/search3-local-offer-persist-v1.js'),'utf8');
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function scope(){return{departureId:'1',countryId:'4',dateFrom:'2026-10-05',dateTo:'2026-10-07',nightsFrom:'7',nightsTo:'9',adults:'2',childs:[7],meal:'',hotelCategory:'',hotelRating:'',hotelTypes:[],hotelIds:[],hotelServices:[],arrivalId:'',regionIds:[],subregionIds:[],operatorIds:[],priceFrom:'',priceTo:'',currency:'RUB',onlyCharter:'false',onlyDirect:'false'};}
function tour(overrides={}){return Object.assign({id:'anex:a',provider:'anex',searchRef:'a'.repeat(32),offerRef:'anex_online:'+'b'.repeat(64),selectionEnabled:false,quoteRequired:true,fuelIncluded:true,finalPriceReady:true,price:199390,currency:'RUB',date:'05.10.2026',nights:7,meal:{name:'AI'},roomType:'STANDARD ROOM',placement:'2AD+1CHD'},overrides);}
function env(pathname='/_preview/search3-local-candidate/poisk-turov/'){
 const listeners=new Map(),requests=[],storeEvents=[];
 class CE{constructor(type,init){this.type=type;this.detail=init&&init.detail||{};}}
 const root={location:new URL('https://anytoour.ru'+pathname),URL,AbortController,Promise,Set,Map,console,CustomEvent:CE,
  V2SearchLifecycle:{generation:1,dirty:false,snapshot:scope()},
  V2Results:{render(list){return list;}},
  addEventListener(type,fn){if(!listeners.has(type))listeners.set(type,[]);listeners.get(type).push(fn);},
  dispatchEvent(event){if(event.type==='v2:local-offer-store')storeEvents.push(event.detail);for(const fn of listeners.get(event.type)||[])fn(event);return true;},
  async fetch(url,options){requests.push({url:String(url),options,body:JSON.parse(options.body)});return{ok:true,async json(){return{ok:true,data:{stored:1,withheld:0}};}};}
 };
 root.window=root;root.globalThis=root;vm.runInContext(CODE,vm.createContext(root),{filename:'search3-local-offer-persist-v1.js'});return{root,requests,storeEvents};
}
(async()=>{
 let e=env('/poisk-turov/');assert.equal(e.root.AnyTourLocalOfferPersistV1.localCandidate(),false);assert.equal(e.root.AnyTourLocalOfferPersistV1.endpoint(),null);
 e=env();const api=e.root.AnyTourLocalOfferPersistV1;assert.equal(api.endpoint().pathname,'/_preview/search3-local-candidate/data/search3-local-offers-write-v1.php');
 assert.equal(api.isoDate('05.10.2026'),'2026-10-05');assert.equal(api.price(199390),'199390');assert.equal(api.price(0),'');
 const rows=api.collect([{id:'101',tours:[tour(),tour({offerRef:'anex_online:'+'c'.repeat(64),finalPriceReady:false}),tour({offerRef:'anex_online:'+'d'.repeat(64),fuelIncluded:false}),tour({offerRef:'anex_online:'+'e'.repeat(64),cachedListing:true})]}]);
 assert.equal(rows.length,1);assert.deepEqual(JSON.parse(JSON.stringify(rows[0])),{legacyHotelId:101,searchRef:'a'.repeat(32),offerRef:'anex_online:'+'b'.repeat(64),checkin:'2026-10-05',nights:7,meal:'AI',room:'STANDARD ROOM',placement:'2AD+1CHD',price:'199390',currency:'RUB'});
 e.root.dispatchEvent(new e.root.CustomEvent('v2:search-reset',{detail:{generation:1,dirty:false}}));e.root.V2Results.render([{id:'101',tours:[tour()]}],{});e.root.dispatchEvent(new e.root.CustomEvent('v2:provider-status',{detail:{provider:'anex',status:'complete',generation:1,readyOffers:1}}));await tick();await tick();
 assert.equal(e.requests.length,1);const request=e.requests[0];assert.equal(new URL(request.url).pathname,'/_preview/search3-local-candidate/data/search3-local-offers-write-v1.php');assert.equal(request.options.method,'POST');assert.equal(request.options.headers['X-Requested-With'],'AnyTourSearch3');assert.equal(request.body.provider,'anex');assert.equal(request.body.generation,1);assert.equal(request.body.offers.length,1);assert.equal(request.body.offers[0].price,'199390');assert.equal(e.storeEvents.at(-1).status,'complete');
 e.root.V2SearchLifecycle.generation=2;e.root.V2SearchLifecycle.dirty=true;e.root.dispatchEvent(new e.root.CustomEvent('v2:search-reset',{detail:{generation:2,dirty:true}}));e.root.V2Results.render([{id:'101',tours:[tour({offerRef:'anex_online:'+'f'.repeat(64)})]}],{});e.root.dispatchEvent(new e.root.CustomEvent('v2:provider-status',{detail:{provider:'anex',status:'complete',generation:2}}));await tick();assert.equal(e.requests.length,1,'dirty generation never persists');
 console.log('SEARCH3_LOCAL_OFFER_PERSIST_OK collect=1 post=1 dirty_blocked=1 route_isolated=1');
})().catch(error=>{console.error(error);process.exit(1);});
