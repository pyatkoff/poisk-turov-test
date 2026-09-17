(function(root){'use strict';
if(root.AnyTourLocalDbProviderV1)return;
const PROVIDERS=new Set(['tourvisor','anex','andromeda']),MAX_HOTELS=5000,MAX_OFFERS=20000;
function localCandidate(){const l=root&&root.location;return !!(l&&l.protocol==='https:'&&l.hostname==='anytoour.ru'&&/^\/_preview\/search3-local-candidate(?:\/|$)/.test(String(l.pathname||'')));}
function endpoint(){return localCandidate()?new URL('/_preview/search3-local-candidate/data/search3-local-results-read-v1.php',root.location.href):null;}
function positiveId(value){const s=String(value??'');return /^(?:[1-9][0-9]*)$/.test(s)&&Number.isSafeInteger(Number(s))?s:'';}
function digest(value){return typeof value==='string'&&/^[a-f0-9]{64}$/.test(value)?value:'';}
function amount(value){const raw=value&&typeof value==='object'?value.amount:value,n=Number(raw);return Number.isFinite(n)&&n>0&&n<=1e12?n:0;}
function safeText(value,limit){return typeof value==='string'&&value.length<=limit&&!/[\u0000-\u001f\u007f]/.test(value)?value.trim():'';}
function offerTour(row){
 if(!row||!PROVIDERS.has(row.provider)||!positiveId(row.legacyHotelId)||row.currency!=='RUB'||!amount(row.price))return null;
 const listing=row.listing;if(!listing||listing.schema_version!==1||listing.provider!==row.provider||listing.listingPriceReady!==true||listing.currency!=='RUB'||listing.selection_state!=='refresh_required'||listing.booking_enabled!==false)return null;
 const identity=listing.identity,tour=listing.tour,operator=listing.operator;if(!identity||!digest(identity.offer_ref_digest)||!digest(identity.search_ref_digest)||!digest(identity.provider_hotel_ref_digest)||!tour||!operator)return null;
 const price=amount(listing.listingPrice);if(!price||price!==amount(row.price)||!/^\d{4}-\d{2}-\d{2}$/.test(String(tour.checkin||''))||!Number.isInteger(tour.nights)||tour.nights<1||tour.nights>60)return null;
 const meal=safeText(tour.meal&&tour.meal.raw,160),room=safeText(tour.room&&tour.room.raw,300),placement=safeText(tour.placement&&tour.placement.raw,160),operatorName=safeText(operator.canonical_name||operator.raw,180),party=tour.party||{};
 return{id:'cached:'+row.provider+':'+identity.offer_ref_digest,provider:row.provider,cachedListing:true,offerIdentityDigest:identity.offer_ref_digest,searchIdentityDigest:identity.search_ref_digest,selectionEnabled:false,quoteRequired:true,finalPriceReady:true,price,currency:'RUB',date:String(tour.checkin),nights:tour.nights,meal:{name:meal},roomType:room,placement,operator:{name:operatorName},adults:Number.isInteger(party.adults)?party.adults:undefined,childs:Number.isInteger(party.children)?party.children:undefined};
}
function parse(data){
 if(!data||data.source!=='anytour-db-first-results-v1'||data.scopeVersion!==1||!digest(data.scopeDigest)||data.selectionAuthority!==false||!Array.isArray(data.hotels)||data.hotels.length>MAX_HOTELS)return null;
 const hotels=[];let offerCount=0;
 for(const group of data.hotels){
  const own=positiveId(group&&group.anytourHotelId),hotel=group&&group.hotel;if(!own||!hotel||hotel.catalog!=='anytour'||positiveId(hotel.id)!==own||!Array.isArray(group.offers))return null;
  const offers=[];for(const stored of group.offers){if(++offerCount>MAX_OFFERS)return null;const tour=offerTour(stored),legacyHotelId=positiveId(stored&&stored.legacyHotelId);if(!tour||!legacyHotelId)return null;offers.push({tour,legacyHotelId});}
  hotels.push({anytourHotelId:own,hotel,offers});
 }
 return{hotels,offerCount};
}
function apply(owner,data){
 if(!owner||typeof owner.clearOffers!=='function'||typeof owner.upsertHotel!=='function'||typeof owner.upsertOffer!=='function'||typeof owner.refresh!=='function')return null;
 const snapshot=parse(data);if(!snapshot)return null;
 owner.clearOffers('local-db');
 for(const group of snapshot.hotels){owner.upsertHotel(group.hotel);for(const item of group.offers)owner.upsertOffer(group.anytourHotelId,item.tour,{source:'local-db',legacyHotelId:item.legacyHotelId});}
 owner.refresh();return snapshot;
}
function canonicalOwner(){const module=root.Search3CanonicalProfilesV1;return module&&typeof module.current==='function'?module.current():null;}
const api=Object.freeze({localCandidate,endpoint,offerTour,parse,apply,version:2});root.AnyTourLocalDbProviderV1=api;
const lifecycle=root.V2SearchLifecycle,target=endpoint();if(!lifecycle||!target||typeof root.fetch!=='function'||typeof root.addEventListener!=='function')return;
let active=null;
function current(run){return active===run&&lifecycle.generation===run.generation&&!lifecycle.dirty;}
function emit(status,detail){if(typeof root.CustomEvent==='function'&&typeof root.dispatchEvent==='function')root.dispatchEvent(new root.CustomEvent('v2:provider-status',{detail:Object.assign({provider:'local-db',status},detail||{})}));}
async function load(snapshot,generation){if(active&&active.controller)active.controller.abort();const run={generation,controller:new AbortController()};active=run;emit('loading',{generation});try{const response=await root.fetch(target.href,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3','Accept':'application/json'},body:JSON.stringify({params:snapshot}),signal:run.controller.signal});const payload=await response.json().catch(()=>null),data=payload&&payload.data;if(!response.ok||!payload||payload.ok!==true)throw new Error(payload&&payload.error||'local_db_unavailable');if(!current(run))return;const owner=canonicalOwner(),stored=apply(owner,data);if(!stored)throw new Error('local_db_invalid');emit('complete',{generation,hotels:Number(data.hotelCount||0),offers:Number(data.offerCount||0),storedOffers:Number(data.storedOfferCount||0),withheldOffers:Number(data.withheldOfferCount||0)});}catch(error){if(!current(run)||error&&error.name==='AbortError')return;const owner=canonicalOwner();if(owner){owner.clearOffers('local-db');owner.refresh();}emit('error',{generation,errorCode:String(error&&error.message||'local_db_unavailable').slice(0,80)});}}
root.addEventListener('v2:search-reset',event=>{if(active&&active.controller)active.controller.abort();active=null;const detail=event.detail||{},snapshot=lifecycle.snapshot;if(detail.dirty||!snapshot||!Number.isInteger(detail.generation)||detail.generation<1)return;load(snapshot,detail.generation);});
})(typeof window!=='undefined'?window:globalThis);
