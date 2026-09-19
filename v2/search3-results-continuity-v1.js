(function(){'use strict';
if(window.Search3ResultsContinuityV1)return;
const openDetails=new Set();
let focusState=null,anchorState=null,anchorFrame=0;
function resultsNode(){return document.getElementById('results');}
function cardOf(node){return node&&typeof node.closest==='function'?node.closest('.hotel-card'):null;}
function hotelId(card){return String(card&&card.dataset&&card.dataset.hotelId||'');}
function cardById(results,id){if(!results||!id)return null;return Array.from(results.querySelectorAll('.hotel-card')).find(card=>hotelId(card)===String(id))||null;}
function focusIdentity(node){
 const card=cardOf(node),hotel=hotelId(card);if(!card||!hotel||!node||typeof node.closest!=='function')return null;
 let target=node.closest('.direct-tour');if(target)return{hotel,kind:'tour',value:String(target.dataset.tid||'')};
 target=node.closest('.provider-detail-toggle');if(target)return{hotel,kind:'provider',value:String(target.dataset.andromedaDetail||'')};
 target=node.closest('[data-detail-retry]');if(target)return{hotel,kind:'provider-retry',value:String(target.dataset.andromedaDetail||'')};
 target=node.closest('.tour-more-toggle');if(target)return{hotel,kind:'offers',value:''};
 target=node.closest('.tour-list-more');if(target)return{hotel,kind:'offers-more',value:''};
 target=node.closest('.hotel-gallery-thumb');if(target)return{hotel,kind:'gallery',value:String(target.dataset.galleryIndex||'')};
 target=node.closest('summary');if(target&&target.parentElement&&target.parentElement.classList.contains('hotel-details'))return{hotel,kind:'details',value:''};
 return null;
}
function focusTarget(results,state){
 const card=state&&cardById(results,state.hotel);if(!card)return null;
 let nodes=[];
 if(state.kind==='tour')nodes=card.querySelectorAll('.direct-tour');
 else if(state.kind==='provider')nodes=card.querySelectorAll('.provider-detail-toggle');
 else if(state.kind==='provider-retry')nodes=card.querySelectorAll('[data-detail-retry]');
 else if(state.kind==='offers')return card.querySelector('.tour-more-toggle');
 else if(state.kind==='offers-more')return card.querySelector('.tour-list-more');
 else if(state.kind==='gallery')nodes=card.querySelectorAll('.hotel-gallery-thumb');
 else if(state.kind==='details')return card.querySelector('.hotel-details > summary');
 return Array.from(nodes).find(node=>state.kind==='tour'?String(node.dataset.tid||'')===state.value:state.kind==='gallery'?String(node.dataset.galleryIndex||'')===state.value:String(node.dataset.andromedaDetail||'')===state.value)||null;
}
function sampleAnchor(results){
 results=results||resultsNode();if(!results)return;
 const cards=Array.from(results.querySelectorAll('.hotel-card'));if(!cards.length){anchorState=null;return;}
 const height=Math.max(1,Number(window.innerHeight)||1),visible=cards.find(card=>{const rect=card.getBoundingClientRect();return rect.bottom>0&&rect.top<height;}),card=visible||cards.find(item=>item.getBoundingClientRect().bottom>0);
 if(!card)return;const id=hotelId(card);if(!id)return;anchorState={hotel:id,top:card.getBoundingClientRect().top};
}
function scheduleAnchor(){if(anchorFrame)return;const run=()=>{anchorFrame=0;sampleAnchor();};anchorFrame=typeof window.requestAnimationFrame==='function'?window.requestAnimationFrame(run):(setTimeout(run,16),-1);}
function rememberDetails(details){const card=cardOf(details),id=hotelId(card);if(!id)return;if(details.open)openDetails.add(id);else openDetails.delete(id);sampleAnchor();}
function restoreDetails(results){openDetails.forEach(id=>{const card=cardById(results,id),details=card&&card.querySelector('.hotel-details');if(details)details.open=true;});}
function restoreFocus(results){if(!focusState)return;const target=focusTarget(results,focusState);if(!target){focusState=null;return;}try{target.focus({preventScroll:true});}catch(e){target.focus();}}
function restoreAnchor(results){if(!anchorState)return;const card=cardById(results,anchorState.hotel);if(!card){anchorState=null;return;}const delta=card.getBoundingClientRect().top-Number(anchorState.top||0);if(Math.abs(delta)>=1&&typeof window.scrollBy==='function')window.scrollBy(0,delta);anchorState={hotel:anchorState.hotel,top:card.getBoundingClientRect().top};}
function purge(results){const ids=new Set(Array.from(results.querySelectorAll('.hotel-card')).map(hotelId).filter(Boolean));Array.from(openDetails).forEach(id=>{if(!ids.has(id))openDetails.delete(id);});if(focusState&&!ids.has(focusState.hotel))focusState=null;if(anchorState&&!ids.has(anchorState.hotel))anchorState=null;}
function restore(results){if(!results)return;restoreDetails(results);restoreAnchor(results);restoreFocus(results);purge(results);scheduleAnchor();}
function reset(){openDetails.clear();focusState=null;anchorState=null;if(anchorFrame&&typeof window.cancelAnimationFrame==='function'&&anchorFrame!==-1)window.cancelAnimationFrame(anchorFrame);anchorFrame=0;}
document.addEventListener('focusin',event=>{const results=resultsNode();if(!results||!results.contains(event.target)){focusState=null;return;}focusState=focusIdentity(event.target);sampleAnchor(results);},true);
document.addEventListener('toggle',event=>{const details=event.target;if(details&&details.classList&&details.classList.contains('hotel-details'))rememberDetails(details);},true);
document.addEventListener('pointerdown',event=>{const results=resultsNode();if(results&&results.contains(event.target))sampleAnchor(results);},true);
window.addEventListener('scroll',scheduleAnchor,{passive:true});
window.addEventListener('resize',scheduleAnchor,{passive:true});
window.addEventListener('v2:search-progress',()=>sampleAnchor());
window.addEventListener('v2:search-continue-started',()=>sampleAnchor());
window.addEventListener('v2:search-continue-progress',()=>sampleAnchor());
window.addEventListener('v2:results-rendered',event=>restore(event.detail&&event.detail.results||resultsNode()));
window.addEventListener('v2:search-started',reset);
window.addEventListener('v2:search-reset',reset);

// Same-tab, display-only snapshots. Never serialize DOM, supplier context or a quote.
const snapshotKey='anytour:search3:completed:v1',snapshotTtl=30*60*1000,snapshotMax=2000000;
const filterSelectors=['.search3-hotel-filter input','.search3-category-filter select','.search3-region-filter select','.search3-meal-filter select','.search3-operator-filter select','.search3-nights-filter select','.search3-flight-filter select','.search3-rating-filter select','.search3-sea-filter select','.search3-budget-min','.search3-budget-max'];
let completedGeneration=0,completedAt=0,snapshotTimer=0;
function snapshotRoute(){return window.location.pathname==='/_preview/search3-local-candidate/poisk-turov/'&&document.body.classList.contains('search3-candidate');}
function positive(value){const s=String(value??'');return /^[1-9][0-9]*$/.test(s)&&Number.isSafeInteger(Number(s))?s:'';}
function bounded(value,max){return typeof value==='string'&&value.length<=max&&!/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/.test(value)?value:'';}
function numeric(value,min,max){return typeof value==='number'&&Number.isFinite(value)&&value>=min&&value<=max?value:null;}
function imageUrl(value){try{const u=new URL(value);return /^https?:$/.test(u.protocol)&&!u.username&&!u.password&&!u.search&&!u.hash&&u.href.length<=2048?u.href:'';}catch(e){return'';}}
function savedProfile(raw){
 if(!raw||raw.catalog!=='anytour'||!positive(raw.id)||!Number.isSafeInteger(raw.revision)||raw.revision<1||!bounded(raw.name,300).trim())return null;
 const p={id:Number(raw.id),catalog:'anytour',revision:raw.revision,name:raw.name,description:bounded(raw.description,12000),detailsAvailable:raw.detailsAvailable===true};
 for(const key of ['category','rating','seaDistance']){const v=numeric(raw[key],0,key==='seaDistance'?100000:10);if(v!==null)p[key]=v;}
 for(const key of ['country','region','city','subRegion']){const name=bounded(raw[key]&&raw[key].name,200);if(name)p[key]={name};}
 p.primaryImage=imageUrl(raw.primaryImage);p.images=(Array.isArray(raw.images)?raw.images:[]).slice(0,12).map(imageUrl).filter(Boolean);
 const info=raw.hotelInformation||{};p.hotelInformation={};
 for(const key of ['infrastructure','services','meals'])p.hotelInformation[key]=(Array.isArray(info[key])?info[key]:[]).slice(0,80).map(v=>bounded(v,300)).filter(Boolean);
 if(typeof info.roomTypes==='string')p.hotelInformation.roomTypes=bounded(info.roomTypes,1000);
 return p;
}
function displayTour(raw){
 if(!raw||!['tourvisor','anex','andromeda'].includes(raw.provider)||!positive(raw.legacyHotelId)||!numeric(raw.price,0.01,1e12)||!Number.isInteger(raw.nights)||raw.nights<1||raw.nights>60||!/^\d{4}-\d{2}-\d{2}$/.test(raw.date||''))return null;
 const parsed=new Date(raw.date+'T12:00:00Z');if(Number.isNaN(parsed.getTime())||parsed.toISOString().slice(0,10)!==raw.date)return null;
 const t={provider:raw.provider,legacyHotelId:raw.legacyHotelId,price:raw.price,date:raw.date,nights:raw.nights,meal:bounded(raw.meal,160),room:bounded(raw.room,300),placement:bounded(raw.placement,160),operator:bounded(raw.operator,180)};
 if(raw.provider==='tourvisor'&&positive(raw.tourId))t.tourId=positive(raw.tourId);
 for(const key of ['adults','childs'])if(Number.isInteger(raw[key])&&raw[key]>=0&&raw[key]<=9)t[key]=raw[key];
 if(typeof raw.isCharter==='boolean')t.isCharter=raw.isCharter;
 return t;
}
function readSnapshot(){
 if(!snapshotRoute())return null;
 try{
  const raw=window.sessionStorage.getItem(snapshotKey);if(!raw||raw.length>snapshotMax)return null;
  const data=JSON.parse(raw),life=window.V2SearchLifecycle,now=Date.now();
  if(!data||data.version!==1||data.route!==window.location.pathname||!positive(data.searchId)||!Number.isSafeInteger(data.createdAt)||now<data.createdAt||now-data.createdAt>=snapshotTtl||!life||typeof data.query!=='string'||!data.query||life.normalizeRestoreQuery(data.query)!==data.query||!Array.isArray(data.hotels)||!data.hotels.length||data.hotels.length>500)return null;
  let count=0;const own=new Set(),hotels=[];
  for(const h of data.hotels){const hotel=savedProfile(h&&h.hotel);if(!hotel||own.has(hotel.id)||!Array.isArray(h.tours)||!h.tours.length)return null;own.add(hotel.id);const tours=[];for(const rawTour of h.tours){if(++count>6000)return null;const tour=displayTour(rawTour);if(!tour)return null;tours.push(tour);}hotels.push({hotel,tours});}
  const view=data.view&&typeof data.view==='object'?data.view:{},filters={};
  filterSelectors.forEach(selector=>{const value=bounded(view.filters&&view.filters[selector],200);if(value)filters[selector]=value;});
  return{version:1,route:data.route,createdAt:data.createdAt,query:data.query,searchId:String(data.searchId),hotels,view:{renderer:view.renderer,details:(Array.isArray(view.details)?view.details:[]).map(positive).filter(Boolean).slice(0,500),scrollY:numeric(view.scrollY,0,10000000)||0,anchor:view.anchor&&positive(view.anchor.hotel)&&numeric(view.anchor.top,-100000,100000)!==null?{hotel:positive(view.anchor.hotel),top:view.anchor.top}:null,filters}};
 }catch(error){return null;}
}
function writeSnapshot(data){try{const encoded=JSON.stringify(data);if(encoded.length>snapshotMax)return false;window.sessionStorage.setItem(snapshotKey,encoded);return true;}catch(error){return false;}}
function captureView(old){
 const r=window.V2Results,node=resultsNode();if(!node||node.hidden)return old&&old.view||null;
 const filters={};filterSelectors.forEach(selector=>{const field=document.querySelector(selector);if(!field)return;let value=bounded(String(field.value||''),200);if(selector==='.search3-budget-max'&&Number(value)>=Number(field.max))value='';if(value)filters[selector]=value;});
 return{renderer:r.captureView(),details:Array.from(openDetails),anchor:anchorState&&Object.assign({},anchorState),scrollY:Math.max(0,Number(window.scrollY)||0),filters};
}
function captureSnapshot(){
 const life=window.V2SearchLifecycle,r=window.V2Results,owner=window.Search3CanonicalProfilesV1&&window.Search3CanonicalProfilesV1.current();
 if(!snapshotRoute()||!life||!r||!owner||life.hotelDetail||life.dirty||life.pending||!life.restoreQuery||!positive(life.searchId))return false;
 const old=readSnapshot();
 if(life.restoredAt){
  // Update only the view of the original display snapshot. Re-entry is neither
  // a fresh price observation nor permission to extend its lifetime.
  if(!old||old.createdAt!==life.restoredAt||old.query!==life.restoreQuery||old.searchId!==String(life.searchId))return false;
  const view=captureView(old);return !!view&&writeSnapshot(Object.assign({},old,{view}));
 }
 if(completedGeneration!==life.generation)return false;
 const items=r.state.items;if(!items.length||items.length>500)return false;let total=0;const hotels=[];
 for(const h of items){
  const hotel=savedProfile(owner.details(h));if(!hotel)return false;const tours=[];
  for(const t of h.tours||[]){
   if(++total>6000)return false;const link=(h.canonicalOfferLinks||[]).find(item=>item.tour===t),legacyHotelId=positive(link&&link.legacyHotelId);if(!legacyHotelId)return false;
   const provider=String(t.provider||'tourvisor').toLowerCase(),raw={provider,legacyHotelId,price:Number(t.price),date:r.formatTourDate(t.date)?(window.V2CurrentPriceCalendar?window.V2CurrentPriceCalendar.dateValue(t.date):String(t.date)):String(t.date),nights:Number(t.nights),meal:r.rawMealLabel(t),room:r.rawRoomLabel(t),placement:r.placementLabel(t.placement),operator:r.textValue(t.operator),adults:t.adults,childs:t.childs,isCharter:t.isCharter};
   // A numeric Tourvisor ID is only a locator for the existing fresh tour API.
   // Other providers keep display-only rows: no offerRef or context is retained.
   if(provider==='tourvisor'&&!t.cachedListing&&t.selectionEnabled!==false&&t.selection_enabled!==false)raw.tourId=positive(t.id);
   const tour=displayTour(raw);if(!tour)return false;tours.push(tour);
  }
  if(!tours.length)return false;hotels.push({hotel,tours});
 }
 const view=captureView(old);if(!view)return false;
 return writeSnapshot({version:1,route:window.location.pathname,createdAt:completedAt,query:life.restoreQuery,searchId:String(life.searchId),hotels,view});
}
function scheduleSnapshot(){if(snapshotTimer)return;snapshotTimer=setTimeout(()=>{snapshotTimer=0;captureSnapshot();},80);}
function applySnapshot(data){
 const owner=window.Search3CanonicalProfilesV1&&window.Search3CanonicalProfilesV1.current(),r=window.V2Results;if(!owner||!r)return false;
 // data has passed readSnapshot. Reconstruct display fields, never stored authority.
 owner.reset();let count=0;
 data.hotels.forEach(group=>{owner.upsertHotel(group.hotel);group.tours.forEach(saved=>{
  const selectable=saved.provider==='tourvisor'&&!!saved.tourId;
  const tour={id:selectable?saved.tourId:'saved:'+saved.provider+':'+group.hotel.id+':'+(++count),provider:saved.provider,price:saved.price,currency:'RUB',date:saved.date,nights:saved.nights,meal:{name:saved.meal},roomType:saved.room,placement:saved.placement,operator:{name:saved.operator},restoredListing:true,cachedListing:!selectable,selectionEnabled:selectable,quoteRequired:true,finalPriceReady:false,priceNeedsConfirmation:true};
  for(const key of ['adults','childs','isCharter'])if(saved[key]!==undefined)tour[key]=saved[key];
  owner.upsertOffer(group.hotel.id,tour,{source:'session-resume',legacyHotelId:saved.legacyHotelId});
 });});
 reset();data.view.details.forEach(key=>openDetails.add(key));r.restoreView(data.view.renderer);r.render([],{empty:false});
 for(const selector of filterSelectors){const value=data.view.filters[selector],node=document.querySelector(selector);if(!value||!node||node.tagName==='SELECT'&&!Array.from(node.options).some(option=>option.value===value))continue;node.value=value;node.dispatchEvent(new Event(node.type==='search'?'input':'change',{bubbles:true}));}
 restoreDetails(resultsNode());
 requestAnimationFrame(()=>{window.scrollTo({top:data.view.scrollY,left:0,behavior:'instant'});if(data.view.anchor){anchorState=data.view.anchor;restoreAnchor(resultsNode());}sampleAnchor();});
 return true;
}
window.addEventListener('v2:search-complete',event=>{const life=window.V2SearchLifecycle;if(!life||life.hotelDetail||life.pending||life.dirty||life.restoredAt||Number(event.detail&&event.detail.searchId)!==life.searchId)return;if(completedGeneration!==life.generation){completedGeneration=life.generation;completedAt=Date.now();}scheduleSnapshot();});
window.addEventListener('v2:search-reset',()=>{completedGeneration=0;completedAt=0;if(snapshotTimer)clearTimeout(snapshotTimer);snapshotTimer=0;});
window.addEventListener('v2:results-rendered',scheduleSnapshot);
window.addEventListener('scroll',scheduleSnapshot,{passive:true});
window.addEventListener('pagehide',captureSnapshot);
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')captureSnapshot();});
document.addEventListener('toggle',scheduleSnapshot,true);
window.addEventListener('v2:hotel-offers-toggle',scheduleSnapshot);
window.addEventListener('v2:hotel-offers-more',scheduleSnapshot);

window.Search3ResultsContinuityV1={sampleAnchor,restore,reset,readSnapshot,captureSnapshot,applySnapshot,version:2};
})();
