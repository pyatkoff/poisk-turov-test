'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const withFuel=require('./fixtures/search3-andromeda-fuel.cjs');

const canonicalSource=fs.readFileSync(path.join(__dirname,'../v2/search3-canonical-profiles-v1.js'),'utf8');
const providerSource=fs.readFileSync(path.join(__dirname,'../v2/andromeda-provider-v1.js'),'utf8');
const hex='a'.repeat(64),offerRef='offer_'+hex;
const offerContext={provider:'andromeda',search_ref:hex,generation:11,page:1,offer_ref:offerRef};
const rawHotel={
 local_id:21477,name:'Supplier Hotel Name',provider:'andromeda',mapping_status:'resolved',country:'Египет',region:'Шарм-эль-Шейх',category:4,rating:4.7,
 catalog:{hotel_id:21477,source:'tourvisor',image_url:'https://catalog.example/legacy.jpg',subregion:'Наама-Бей',description:'Legacy description',address:'Legacy address',sea_distance:200},
 andromeda_content:{source:'andromeda',image_url:'https://cdn.samo.ru/img/5.5844.3414.jpg',hotel_url:'https://operator.example/hotels/movenpick'},
 tours:[withFuel({provider:'andromeda',offer_ref:offerRef,offer_context:offerContext,price:{amount:'155079.00',currency:'RUB'},checkin:'2026-09-18',nights:8,meal:'AI',room:'STANDARD',placement:'2 ADL',operator:'ANEX'})]
};
const unresolved={...rawHotel,local_id:null,card_key:'andromeda:andromeda_catalog:999',name:'Unresolved Supplier Hotel',mapping_status:'unresolved',catalog:null,tours:[withFuel({...rawHotel.tours[0],offer_ref:'offer_'+'b'.repeat(64),offer_context:{...offerContext,offer_ref:'offer_'+'b'.repeat(64)}})]};

const listeners=new Map(),providerEvents=[],requests=[];
const renderer={state:{items:[]},render(list){renderer.state.items=Array.isArray(list)?list:[];return renderer.state.items;}};
const originalRender=renderer.render;
const lifecycle={generation:11,dirty:false,snapshot:{departureId:'1',countryId:'4',dateFrom:'2026-09-18',dateTo:'2026-09-18',nightsFrom:'8',nightsTo:'8',adults:'2',childs:[],currency:'RUB'}};
const document={activeElement:null,addEventListener(){},querySelectorAll(){return[];}};
class FixtureEvent{constructor(type,init){this.type=type;this.detail=init&&init.detail||{};}}
const root={
 location:new URL('https://anytoour.ru/_preview/search3-local-candidate/poisk-turov/'),
 V2_CONFIG:{andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'},
 V2SearchLifecycle:lifecycle,V2Results:renderer,document,setTimeout,clearTimeout,
 addEventListener(name,fn){if(!listeners.has(name))listeners.set(name,[]);listeners.get(name).push(fn);},
 dispatchEvent(event){if(event.type==='v2:provider-status')providerEvents.push(event.detail);for(const fn of listeners.get(event.type)||[])fn(event);},
 async fetch(url,options={}){
  const parsed=new URL(String(url),this.location.href);requests.push({url:parsed.href,options});
  if(parsed.pathname.endsWith('/data/hotel-details-read-v1.php')){
   const ids=parsed.searchParams.getAll('legacyHotelIds[]');
   assert.deepEqual(ids,['21477']);
   return{ok:true,async json(){return{ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:ids,items:[{id:9001,catalog:'anytour',revision:3,name:'Собственный AnyTour Hotel',primaryImage:'https://anytoour.example/own.jpg',country:{name:'Египет'},region:{name:'Шарм-эль-Шейх'},subRegion:{name:'Наама-Бей'},hotelInformation:{infrastructure:['Бассейн'],services:['Wi-Fi'],meals:['AI'],roomTypes:['STANDARD']}}],links:[{legacyHotelId:21477,anytourHotelId:9001}],missingLegacyIds:[]};}};
  }
  if(parsed.pathname.endsWith('/api-andromeda-search3-preview.php')){
   assert.equal(options.method,'POST');assert.equal(options.headers['X-Requested-With'],'AnyTourSearch3');
   const body=JSON.parse(options.body);assert.equal(body.generation,11);assert.equal(body.page,1);assert.equal(body.action,undefined);
   return{ok:true,async json(){return{ok:true,data:{provider:'andromeda',generation:11,page:1,pages_count:1,hotels:[rawHotel,unresolved]}};}};
  }
  throw new Error('Unexpected fetch '+parsed.href);
 }
};
root.window=root;root.globalThis=root;
const context=vm.createContext({window:root,globalThis:root,document,URL,URLSearchParams,AbortController,Map,Set,Array,Number,String,Object,RegExp,Promise,JSON,decodeURIComponent,CustomEvent:FixtureEvent,setTimeout,clearTimeout,console});
vm.runInContext(canonicalSource,context,{filename:'search3-canonical-profiles-v1.js'});
let owner,refreshCount=0;
owner=root.Search3CanonicalProfilesV1.create(()=>{refreshCount++;const items=owner.read(owner.source(),owner.options());renderer.state.items=items;return items;});
assert.ok(owner);owner.read([],{empty:true});
vm.runInContext(providerSource,context,{filename:'andromeda-provider-v1.js'});
assert.equal(root.V2Results.render,originalRender,'canonical local-candidate must not install the Andromeda renderer wrapper');
assert.equal(typeof owner.upsertLegacyOffer,'function');assert.equal(typeof owner.setLegacyHotelState,'function');

const settle=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 root.dispatchEvent(new FixtureEvent('v2:search-reset',{detail:{generation:11}}));
 for(let i=0;i<8;i++)await settle();
 const cards=renderer.state.items;
 assert.equal(cards.length,1,'resolved provider-only offer hydrates one own AnyTour hotel card; unresolved provider hotel stays out');
 const card=cards[0];
 assert.equal(card.catalog,'anytour');assert.equal(card.anytourHotelId,9001);assert.equal(card.id,'21477');
 assert.equal(card.name,'Собственный AnyTour Hotel','supplier hotel name never becomes canonical presentation');
 assert.equal(card.picturelink,'https://anytoour.example/own.jpg','own AnyTour image remains presentation authority');
 assert.deepEqual(Array.from(card.providers),['andromeda']);assert.equal(card.tours.length,1);assert.equal(card.tours[0].offerRef,offerRef);assert.equal(card.tours[0].fuelIncluded,true);
 assert.equal(card.tours[0].providerDetail.eligible,true,'detail eligibility survives canonical offer projection');
 assert.equal(card.andromedaExpansion.status,'idle','provider expansion state is projected only as temporary canonical compatibility metadata');
 assert.deepEqual(Array.from(card.canonicalLegacyIds),['21477']);assert.equal(card.canonicalOfferLinks.length,1);assert.equal(card.canonicalOfferLinks[0].tour,card.tours[0]);assert.equal(card.canonicalOfferLinks[0].legacyHotelId,'21477');
 const selection=root.AnyTourAndromedaProvider.prepareQuote(offerRef);assert.ok(selection,'quote selection resolves through canonical offer origin');assert.equal(selection.localId,21477);assert.equal(selection.hotel.anytourHotelId,9001);assert.equal(selection.tour.offerRef,offerRef);
 assert.equal(root.V2Results.render,originalRender,'provider stays off renderer ownership after progressive completion');
 assert.ok(refreshCount>=2,'provider progress and canonical profile hydration rerender through the owner');
 assert.deepEqual(providerEvents.map(item=>item.status),['loading','progress','complete']);
 assert.ok(requests.some(item=>new URL(item.url).pathname.endsWith('/data/hotel-details-read-v1.php')),'legacy provider ID is hydrated by the canonical catalog owner');
 console.log('SEARCH3_CANONICAL_ANDROMEDA_STATE_OK cards=1 offers=1 unresolved=0 renderer_patch=0 quote_origin=1');
})().catch(error=>{console.error(error);process.exit(1);});
