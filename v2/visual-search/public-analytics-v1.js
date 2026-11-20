'use strict';
// Preserve the existing analytics owner and goal names without dispatching
// legacy search DOM events (the canonical catalogue also subscribes to those).
(function(root){
 const cfg=root.V2_CONFIG||{},enabled=cfg.search3PublicEntry==='live'
  &&['/poisk-turov/','/poisk-turov/index.php'].includes(String(root.location?.pathname||''));
 const runs=new WeakMap(),tours=new Set(),flights=new Set();
 const count=value=>Number.isSafeInteger(Number(value))&&Number(value)>=0?Number(value):0;
 const money=value=>Number.isFinite(Number(value))&&Number(value)>0?Number(value):null;
 const searchIdentity=meta=>count(meta.searchId)>0?{searchId:count(meta.searchId)}:{};
 const nativeHotel=value=>/^[1-9]\d*$/.test(String(value||''))?{hotelId:String(value)}:{};
 const now=()=>root.performance?.now?.()??Date.now();
 const track=(name,fields)=>{try{if(enabled&&typeof root.V2Analytics?.track==='function')root.V2Analytics.track(name,fields);}catch{}};
 function start(response,meta={},loading=false){
  if(!enabled||!response||typeof response!=='object')return;
  const previous=runs.get(response);if(previous&&(!loading||previous.pending))return;
  const run={pending:true,silent:response.cachedResume===true,continued:response.continued===true,
   start:now(),before:count(meta.resultsCount)};
  runs.set(response,run);
  if(run.silent||run.continued)return;
  tours.clear();flights.clear();
  track('search_started',searchIdentity(meta));
 }
 function event(event,response,meta={}){
  if(!enabled||!event||!response)return;
  if(event.type==='loading'){start(response,meta,true);return;}
  if(!['complete','error'].includes(event.type))return;
  const run=runs.get(response);if(!run?.pending)return;run.pending=false;
  if(run.silent)return;
  if(event.type==='error'){track('search_error',{...searchIdentity(meta),phase:run.continued?'continue':'search',errorCode:'SEARCH_ERROR'});return;}
  const results=count(meta.resultsCount);
  if(run.continued)track('search_continued',{...searchIdentity(meta),previousResultsCount:run.before,addedResultsCount:Math.max(0,results-run.before)});
  else track('search_complete',{...searchIdentity(meta),durationMs:Math.max(0,Math.round(now()-run.start)),resultsCount:results});
 }
 function failure(error,response,meta={}){
  if(!enabled||!response)return;
  const run=runs.get(response);if(run&&!run.pending||run?.silent)return;
  if(run)run.pending=false;else runs.set(response,{pending:false});
  track('search_error',{...searchIdentity(meta),phase:'search',errorCode:String(error?.code||error?.name||'ERROR')});
 }
 function tour(offer,hotel,country){
  if(!enabled||!offer?.tour||offer.cached||offer.loading||offer.quoteError||offer.pricePending)return;
  const price=money(offer.total),id=String(offer.tour.id||'');if(!id||price===null)return;
  const key=JSON.stringify([offer.provider,id,price]);if(tours.has(key))return;tours.add(key);
  track('tour_selected',{tourId:id,...nativeHotel(offer.tour.hotel?.id),hotel:String(hotel?.name||''),
   region:String(hotel?.resort||''),country:String(country||''),price,currency:'RUB'});
 }
 function flight(offer,pair,index){
  if(!enabled||!offer?.tour||!pair||offer.loading||offer.quoteError||offer.pricePending)return;
  const price=money(offer.total),id=String(offer.tour.id||'');if(!id||price===null||!Number.isSafeInteger(index)||index<0)return;
  const key=JSON.stringify([id,index,price]);if(flights.has(key))return;flights.add(key);
  track('flight_selected',{tourId:id,flightIndex:index,flightNumber:String(pair.forward?.[0]?.number||''),
   isDefault:pair.isDefault===true,flightPrice:price});
 }
 function provider(receipt){
  if(!enabled||receipt?.provider!=='anex'||receipt.finalPriceVerified!==true||receipt.priceKind!=='verified'
   ||!Number.isSafeInteger(receipt.expiresAt)||receipt.expiresAt*1000<=Date.now())return;
  const price=money(receipt.price),id=String(receipt.offerRef||'');if(!/^anex_online:[a-f0-9]{64}$/.test(id)||price===null)return;
  const key=JSON.stringify(['anex',id,receipt.choiceRef,price]);
  if(!tours.has(key)){tours.add(key);track('tour_selected',{tourId:id,hotel:String(receipt.hotel||''),
   region:String(receipt.resort||''),country:String(receipt.country||''),price,currency:'RUB'});}
  if(Array.isArray(receipt.flights)&&receipt.flights.length===2&&!flights.has(key)){
   flights.add(key);track('flight_selected',{tourId:id,flightNumber:String(receipt.flights[0].name||''),flightPrice:price});
  }
 }
 root.AnyTourPublicAnalyticsV1=Object.freeze({start,event,failure,tour,flight,provider,
  hotel:hotel=>track('hotel_open',Array.isArray(hotel?.legacyIds)&&hotel.legacyIds.length===1?nativeHotel(hotel.legacyIds[0]):{}),
  sort:value=>{if(['recommended','price','rating'].includes(value))track('sort_changed',{sort:value});}});
})(window);
