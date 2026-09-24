'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(process.argv[2]||'v2/search3-canonical-profiles-v1.js','utf8');

function setup(){
 const root={location:{pathname:'/_preview/search3-local-candidate/prototype-search/'},addEventListener(){},fetch(){throw new Error('unexpected fetch');}};
 vm.runInNewContext(source,{window:root,document:{createElement:()=>({setAttribute(){},appendChild(){},addEventListener(){}})},URLSearchParams,AbortController,setTimeout,clearTimeout});
 const owner=root.Search3CanonicalProfilesV1.create(()=>{});
 owner.upsertHotel({id:500,catalog:'anytour',revision:1,name:'Hotel 500',hotelInformation:{}});
 return owner;
}
function offer(provider,digest,price,extra={}){
 return {id:`${provider}:${digest}`,provider,offerIdentityDigest:digest,price,...extra};
}
function card(owner){
 const rows=owner.read([],{});
 assert.equal(rows.length,1);
 return rows[0];
}

{
 const owner=setup();
 const cached=offer('andromeda','a'.repeat(64),100000,{cachedListing:true,selectionEnabled:false});
 const live=offer('andromeda','a'.repeat(64),103000,{cachedListing:false,selectionEnabled:false,quoteRequired:true});
 owner.upsertOffer(500,cached,{source:'local-db',legacyHotelId:900});
 owner.upsertLegacyOffer(900,live,{source:'direct-andromeda'});
 const result=card(owner);
 assert.equal(result.tours.length,1,'exact provider identity is still deduplicated');
 assert.equal(result.tours[0],live,'live exact offer must outrank cached LOCAL duplicate');
 assert.equal(result.price,103000,'hotel minimum follows the accepted live copy');
 owner.clearOffers('direct-andromeda');
 const fallback=card(owner);
 assert.equal(fallback.tours.length,1);
 assert.equal(fallback.tours[0],cached,'cached LOCAL remains fallback when live copy disappears');
 assert.equal(fallback.price,100000);
}

{
 const owner=setup();
 const cached=offer('anex','b'.repeat(64),120000,{cachedListing:true});
 const liveExact=offer('anex','b'.repeat(64),121000,{cachedListing:false,anexSessionCurrent:true});
 const liveDistinct=offer('anex','c'.repeat(64),119000,{cachedListing:false,anexSessionCurrent:true});
 owner.upsertOffer(500,cached,{source:'local-db',legacyHotelId:901});
 owner.upsertLegacyOffer(901,liveExact,{source:'direct-anex'});
 owner.upsertLegacyOffer(901,liveDistinct,{source:'direct-anex'});
 const result=card(owner);
 assert.equal(result.tours.length,2,'different native offer identities must not be semantically collapsed');
 assert.equal(result.tours.includes(cached),false,'cached exact duplicate is hidden while live exact copy exists');
 assert.equal(result.tours.includes(liveExact),true);
 assert.equal(result.tours.includes(liveDistinct),true);
 assert.equal(result.price,119000);
}

{
 const owner=setup();
 const anex=offer('anex','d'.repeat(64),130000,{cachedListing:false});
 const andromeda=offer('andromeda','d'.repeat(64),130000,{cachedListing:false});
 owner.upsertLegacyOffer(902,anex,{source:'direct-anex'});
 owner.upsertLegacyOffer(902,andromeda,{source:'direct-andromeda'});
 owner.upsertOffer(500,offer('tourvisor','e'.repeat(64),131000,{cachedListing:true}),{source:'local-db',legacyHotelId:902});
 // Stored offer establishes the canonical legacy link for the live rows above.
 const result=card(owner);
 assert.equal(result.tours.length,3,'same token across different providers stays distinct');
}

console.log('search3 canonical live offer priority: ok');
