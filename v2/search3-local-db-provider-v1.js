(function(root){'use strict';
if(root.AnyTourLocalDbProviderV1)return;
const PROVIDERS=new Set(['tourvisor','anex','andromeda']),MAX_HOTELS=5000,MAX_OFFERS=20000;
function localCandidate(){const l=root&&root.location;return !!(l&&l.protocol==='https:'&&l.hostname==='anytoour.ru'&&/^\/_preview\/search3-local-candidate(?:\/|$)/.test(String(l.pathname||'')));}
function endpoint(){return localCandidate()?new URL('/_preview/search3-local-candidate/data/search3-local-results-read-v1.php',root.location.href):null;}
function positiveId(value){const s=String(value??'');return /^(?:[1-9][0-9]*)$/.test(s)&&Number.isSafeInteger(Number(s))?s:'';}
function digest(value){return typeof value==='string'&&/^[a-f0-9]{64}$/.test(value)?value:'';}
function amount(value){const raw=value&&typeof value==='object'?value.amount:value,n=Number(raw);return Number.isFinite(n)&&n>0&&n<=1e12?n:0;}
function safeText(value,limit){return typeof value==='string'&&value.length<=limit&&!/[\u0000-\u001f\u007f]/.test(value)?value.trim():'';}
// Mirror the LOCAL listing contract. Even a previously verified quote is display-only here.
function priceState(listing){
 const state=listing.listingPriceState;
 if(state==null)return listing.listingPriceReady===true?'final_ready_estimate':'';
 const confirmation=state==='search_price_confirmation_required',verified=state==='final_verified';
 if(!confirmation&&!verified&&state!=='final_ready_estimate')return'';
 if(listing.listingPriceReady!==!confirmation||listing.priceConfirmationRequired!==confirmation||listing.quoteState!==(verified?'verified':'unknown')||listing.finalPriceVerified!==verified)return'';
 if(verified?!digest(listing.quoteEvidenceDigest):listing.quoteEvidenceDigest!=null)return'';
 return state;
}
function listingOf(row){
 if(!row||!PROVIDERS.has(row.provider)||!positiveId(row.legacyHotelId)||row.currency!=='RUB'||!amount(row.price))return null;
 const listing=row.listing;if(!listing||listing.schema_version!==1||listing.provider!==row.provider||listing.currency!=='RUB'||listing.selection_state!=='refresh_required'||listing.booking_enabled!==false)return null;
 return priceState(listing)&&amount(listing.listingPrice)===amount(row.price)?listing:null;
}
// Only an accepted exact LOCAL mapping gives a canonical stay identity. Names
// and numeric coincidences never promote an unmapped supplier fact.
function canonicalStay(row,hotelId,kind){
 const match=row.stayMatch,part=match&&match[kind],c=part&&part.canonical;
 if(!match||match.source!=='anytour-hotel-stay-v2'||match.exactScope!==true||!part||part.status!=='accepted'||!c||c.kind!==kind||positiveId(c.hotelId)!==hotelId||!positiveId(c.id)||!positiveId(c.revision))return null;
 const nameRu=safeText(c.nameRu,255),localKey=safeText(c.localKey,128);
 if(!nameRu||!localKey)return null;
 return{kind,id:Number(c.id),hotelId:Number(hotelId),nameRu,localKey,revision:Number(c.revision)};
}
function searchMealPlan(row,hotelId,concept){
 const m=row.searchMeal,p=m&&m.plan;
 if(!concept||!m||m.source!=='anytour-search-meal-v1'||positiveId(m.hotelId)!==hotelId||m.conceptId!==concept.id||m.conceptRevision!==concept.revision||!p||!positiveId(p.id)||!safeText(p.nameRu,255)||!safeText(p.code,64))return null;
 return{id:Number(p.id),code:p.code,nameRu:p.nameRu};
}
function offerTour(row,hotelId){
 const listing=listingOf(row);if(!listing)return null;
 const listingPriceState=priceState(listing),identity=listing.identity,tour=listing.tour,operator=listing.operator;if(!identity||!digest(identity.offer_ref_digest)||!digest(identity.search_ref_digest)||!digest(identity.provider_hotel_ref_digest)||!tour||!operator)return null;
 const price=amount(listing.listingPrice);if(!/^\d{4}-\d{2}-\d{2}$/.test(String(tour.checkin||''))||!Number.isInteger(tour.nights)||tour.nights<1||tour.nights>60)return null;
 const localMeal=canonicalStay(row,hotelId,'meal'),localRoom=canonicalStay(row,hotelId,'room');
 const meal=safeText(tour.meal&&tour.meal.raw,160),room=safeText(tour.room&&tour.room.raw,300),placement=safeText(tour.placement&&tour.placement.raw,160),operatorName=safeText(operator.canonical_name||operator.raw,180),party=tour.party||{};
 return{...(searchMealPlan(row,hotelId,localMeal)?{searchMealPlan:searchMealPlan(row,hotelId,localMeal)}:{}),...(localMeal||localRoom?{localStay:{source:'anytour-hotel-stay-v2',meal:localMeal,room:localRoom}}:{}),id:'cached:'+row.provider+':'+identity.offer_ref_digest,provider:row.provider,cachedListing:true,offerIdentityDigest:identity.offer_ref_digest,searchIdentityDigest:identity.search_ref_digest,selectionEnabled:false,quoteRequired:true,listingPriceState,finalPriceReady:listing.listingPriceReady,priceNeedsConfirmation:listingPriceState==='search_price_confirmation_required',price,currency:'RUB',date:String(tour.checkin),nights:tour.nights,meal:{name:meal},roomType:room,placement,operator:{name:operatorName},adults:Number.isInteger(party.adults)?party.adults:undefined,childs:Number.isInteger(party.children)?party.children:undefined};
}
function parse(data){
 if(!data||data.source!=='anytour-db-first-results-v1'||data.scopeVersion!==1||!digest(data.scopeDigest)||data.selectionAuthority!==false||!Array.isArray(data.hotels)||data.hotels.length>MAX_HOTELS)return null;
 const hotels=[];let scanned=0,offerCount=0,withheldOfferCount=0;
 for(const group of data.hotels){
  const own=positiveId(group&&group.anytourHotelId),hotel=group&&group.hotel;if(!own||!hotel||hotel.catalog!=='anytour'||positiveId(hotel.id)!==own||!Array.isArray(group.offers))return null;
  const offers=[];for(const stored of group.offers){
   if(++scanned>MAX_OFFERS)return null;
   const listing=listingOf(stored);
   // The DB reader can retain legacy display rows without identity. Withhold that
   // row, never invent an ID or discard independently identified sibling offers.
   if(listing&&!Object.prototype.hasOwnProperty.call(listing,'identity')){withheldOfferCount++;continue;}
   // Listing safety and exact identity remain snapshot authority. Only after both
   // are valid may a display-only projection failure be isolated to this row.
   if(!listing)return null;
   const identity=listing.identity;
   if(!identity||!digest(identity.offer_ref_digest)||!digest(identity.search_ref_digest)||!digest(identity.provider_hotel_ref_digest))return null;
   const legacyHotelId=positiveId(stored&&stored.legacyHotelId);if(!legacyHotelId)return null;
   const tour=offerTour(stored,own);
   if(!tour){withheldOfferCount++;continue;}
   offers.push({tour,legacyHotelId});offerCount++;
  }
  if(offers.length)hotels.push({anytourHotelId:own,hotel,offers});
 }
 return{hotels,offerCount,withheldOfferCount};
}
function apply(owner,data){
 if(!owner||typeof owner.clearOffers!=='function'||typeof owner.upsertHotel!=='function'||typeof owner.upsertOffer!=='function'||typeof owner.refresh!=='function')return null;
 const snapshot=parse(data);if(!snapshot)return null;
 owner.clearOffers('local-db');
 for(const group of snapshot.hotels){owner.upsertHotel(group.hotel);for(const item of group.offers)owner.upsertOffer(group.anytourHotelId,item.tour,{source:'local-db',legacyHotelId:item.legacyHotelId});}
 owner.refresh();return snapshot;
}
// Compare the complete HTTP intent using AnyTourSearchScopeV1 semantics. This
// never rewrites the request or equates a cached source cohort with current intent.
const SCOPE_KEYS=['departureId','countryId','dateFrom','dateTo','nightsFrom','nightsTo','adults','childs','meal','hotelCategory','hotelRating','hotelTypes','hotelIds','hotelServices','arrivalId','regionIds','subregionIds','operatorIds','priceFrom','priceTo','currency','onlyCharter','onlyDirect'];
function scopeTrim(value){return value.replace(/^[ \t\n\r\0\v]+|[ \t\n\r\0\v]+$/g,'');}
function scopeToken(value,limit){
 if(value===null)return'';
 if(typeof value!=='string'&&!Number.isSafeInteger(value))throw new Error('scope_token');
 const text=scopeTrim(String(value));
 if(/[\u0000-\u001f\u007f]/.test(text)||encodeURIComponent(text).replace(/%[0-9A-F]{2}/g,'x').length>limit)throw new Error('scope_token');
 return text;
}
function scopeInteger(value,min,max){
 if((typeof value!=='string'&&!Number.isSafeInteger(value))||!/^(?:0|[1-9][0-9]*)$/.test(String(value)))throw new Error('scope_integer');
 const n=Number(value);if(!Number.isSafeInteger(n)||n<min||n>max)throw new Error('scope_integer');return n;
}
function scopeDate(value){
 if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(value)||new Date(value+'T00:00:00Z').toISOString().slice(0,10)!==value)throw new Error('scope_date');
 return value;
}
function scopeMoney(value){
 if(value===null||value==='')return'';
 if(typeof value!=='string'&&(typeof value!=='number'||!Number.isFinite(value)))throw new Error('scope_money');
 const raw=scopeTrim(String(value));if(!/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/.test(raw))throw new Error('scope_money');
 const [whole,decimal='']=raw.split('.'),fraction=decimal.replace(/0+$/,'');return whole+(fraction?'.'+fraction:'');
}
function scopeSignature(params,response=false){
 try{
  if(!params||typeof params!=='object'||Array.isArray(params)||Object.keys(params).length!==SCOPE_KEYS.length+(response?1:0)||SCOPE_KEYS.some(key=>!Object.prototype.hasOwnProperty.call(params,key))||(response&&params.scopeVersion!==1))return null;
  const out={};
  for(const key of SCOPE_KEYS){
   const value=params[key];
   if(key==='departureId'||key==='countryId'){
    if((typeof value!=='string'&&!Number.isSafeInteger(value))||!/^[1-9][0-9]*$/.test(String(value))||String(value).length>19||(String(value).length===19&&String(value)>'9223372036854775807'))return null;
    out[key]=String(value);
   }else if(key==='dateFrom'||key==='dateTo')out[key]=scopeDate(value);
   else if(key==='nightsFrom'||key==='nightsTo'||key==='adults')out[key]=scopeInteger(value,1,key==='adults'?6:28);
   else if(key==='childs'){
    if(!Array.isArray(value)||value.length>3)return null;
    out[key]=value.map(age=>scopeInteger(age,0,17)).sort((a,b)=>a-b);
   }else if(['hotelTypes','hotelIds','hotelServices','regionIds','subregionIds','operatorIds'].includes(key)){
    if(!Array.isArray(value)||value.length>(key==='hotelTypes'?30:100))return null;
    out[key]=Array.from(new Set(value.map(item=>scopeToken(item,128)).filter(Boolean))).sort();
   }else if(key==='priceFrom'||key==='priceTo')out[key]=scopeMoney(value);
   else if(key==='currency'){if(value!=='RUB')return null;out[key]=value;}
   else if(key==='onlyCharter'||key==='onlyDirect'){
    if(value===true||value==='true'||value==='1'||value===1)out[key]=true;
    else if(value===false||value==='false'||value==='0'||value===0)out[key]=false;else return null;
   }else out[key]=scopeToken(value,key==='meal'?128:key==='arrivalId'?64:32);
  }
  const days=(Date.parse(out.dateTo+'T00:00:00Z')-Date.parse(out.dateFrom+'T00:00:00Z'))/86400000;
  if(days<0||days>21||out.nightsTo<out.nightsFrom||out.nightsTo-out.nightsFrom>10)return null;
  // String decimal order only for request filters; offer-price arithmetic is untouched.
  const moneyOrder=value=>{const [whole,fraction='']=value.split('.');return whole.padStart(12,'0')+fraction.padEnd(2,'0');};
  if(out.priceFrom!==''&&out.priceTo!==''&&moneyOrder(out.priceTo)<moneyOrder(out.priceFrom))return null;
  return JSON.stringify(out);
 }catch(_){return null;}
}
function canonicalOwner(){const module=root.Search3CanonicalProfilesV1;return module&&typeof module.current==='function'?module.current():null;}
const api=Object.freeze({localCandidate,endpoint,offerTour,parse,apply,version:4});root.AnyTourLocalDbProviderV1=api;
const lifecycle=root.V2SearchLifecycle,target=endpoint();if(!lifecycle||!target||typeof root.fetch!=='function'||typeof root.addEventListener!=='function')return;
let active=null;
function current(run){return active===run&&lifecycle.generation===run.generation&&!lifecycle.dirty;}
function emit(status,detail){if(typeof root.CustomEvent==='function'&&typeof root.dispatchEvent==='function')root.dispatchEvent(new root.CustomEvent('v2:provider-status',{detail:Object.assign({provider:'local-db',status},detail||{})}));}
async function load(snapshot,generation){if(active&&active.controller)active.controller.abort();const run={generation,controller:new AbortController()};active=run;emit('loading',{generation});try{const body=JSON.stringify({params:snapshot}),requestScope=scopeSignature(JSON.parse(body).params);if(requestScope===null)throw new Error('local_db_scope_mismatch');const response=await root.fetch(target.href,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3','Accept':'application/json'},body,signal:run.controller.signal});const payload=await response.json().catch(()=>null),data=payload&&payload.data;if(!response.ok||!payload||payload.ok!==true)throw new Error(payload&&payload.error||'local_db_unavailable');if(!current(run))return;if(scopeSignature(data&&data.scope,true)!==requestScope)throw new Error('local_db_scope_mismatch');const owner=canonicalOwner(),stored=apply(owner,data);if(!stored)throw new Error('local_db_invalid');emit('complete',{generation,hotels:stored.hotels.length,offers:stored.offerCount,storedOffers:Number(data.storedOfferCount||0),withheldOffers:Number(data.withheldOfferCount||0)+stored.withheldOfferCount});}catch(error){if(!current(run)||error&&error.name==='AbortError')return;const owner=canonicalOwner();if(owner){owner.clearOffers('local-db');owner.refresh();}emit('error',{generation,errorCode:String(error&&error.message||'local_db_unavailable').slice(0,80)});}}
root.addEventListener('v2:search-reset',event=>{if(active&&active.controller)active.controller.abort();active=null;const detail=event.detail||{},snapshot=lifecycle.snapshot;if(detail.dirty||!snapshot||!Number.isInteger(detail.generation)||detail.generation<1)return;load(snapshot,detail.generation);});
})(typeof window!=='undefined'?window:globalThis);