(function(root){'use strict';
if(root.AnyTourAndromedaProvider)return;
const MAX_PAGES=1000,MAX_OFFERS_PER_HOTEL=5000;
function safeUrl(value){if(typeof value!=='string'||!value||value.length>2048||/[\u0000-\u0020\u007f]/.test(value))return'';try{const url=new URL(value,root.location&&root.location.href||'https://anytoour.ru/');if(url.protocol!=='https:'||url.username||url.password||url.port||!/^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}$/i.test(url.hostname)||/\.(?:local|localhost|internal|lan)$/i.test(url.hostname)||/(?:^|&)(?:sid|token|password|auth|apikey|secret|session)=/i.test(url.search.slice(1)))return'';return url.href;}catch(_){return'';}}
function operatorHotelCodeFromImage(value){const clean=safeUrl(value);if(!clean)return null;const url=new URL(clean);if(url.hostname.toLowerCase()==='files.anextour.ru'){const code=url.searchParams.get('hotelCode');if(code&&/^[1-9][0-9]{0,11}$/.test(code))return{operator:'anex',code,evidence:'hotel_image_query'};}const file=decodeURIComponent(url.pathname.split('/').pop()||'');const match=file.match(/^5\.([1-9][0-9]{0,11})\.[1-9][0-9]{0,11}\.(?:jpe?g|webp)$/i);return match?{operator:'anex',code:match[1],evidence:'hotel_image_path'}:null;}
function context(value){if(!value||value.provider!=='andromeda'||!/^offer_[a-f0-9]{64}$/.test(value.offer_ref||'')||!/^[a-f0-9]{64}$/.test(value.search_ref||'')||!Number.isInteger(value.generation)||value.generation<1||!Number.isInteger(value.page)||value.page<1||value.page>MAX_PAGES)return null;const result={provider:'andromeda',search_ref:value.search_ref,generation:value.generation,page:value.page,offer_ref:value.offer_ref};if(value.hotel_scope){const scope=value.hotel_scope;if(!Number.isSafeInteger(scope.local_id)||scope.local_id<1||!scope.seed||scope.seed.hotel_scope)return null;const seed=context(scope.seed);if(!seed)return null;result.hotel_scope={local_id:scope.local_id,seed};}return result;}
function amount(value){const raw=value&&typeof value==='object'?value.amount:value,number=Number(raw);return Number.isFinite(number)&&number>0&&number<=1e12?number:0;}
function listingReference(value){return typeof value==='string'&&/^listing_[a-f0-9]{64}$/.test(value)?value:null;}
function category(value){const number=Number(value);return Number.isInteger(number)&&number>=1&&number<=5?number:0;}
function hotelKey(hotel){if(Number.isSafeInteger(hotel&&hotel.local_id)&&hotel.local_id>0)return String(hotel.local_id);const key=String(hotel&&hotel.card_key||'');return /^andromeda:[A-Za-z0-9_-]{1,128}:[A-Za-z0-9_-]{1,128}$/.test(key)?key:'';}
function normalizeTour(tour,hotel){const offerContext=context(tour&&tour.offer_context);if(!tour||tour.provider!=='andromeda'||!offerContext||offerContext.offer_ref!==tour.offer_ref)return null;const price=amount(tour.price),currency=String(tour.price&&tour.price.currency||'');if(!price||currency!=='RUB'||!/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(tour.checkin||'')||!Number.isInteger(tour.nights)||tour.nights<1||tour.nights>60)return null;const content=hotel&&hotel.andromeda_content||{},image=safeUrl(content.image_url),providerCode=operatorHotelCodeFromImage(image);return{id:'andromeda:'+tour.offer_ref,provider:'andromeda',offerRef:tour.offer_ref,offerContext,listingPriceRef:listingReference(tour.listing_price_ref),selectionEnabled:false,quoteRequired:true,price,currency:'RUB',date:tour.checkin.split('-').reverse().join('.'),nights:tour.nights,meal:{name:String(tour.meal||'').slice(0,160)},roomType:String(tour.room||'').slice(0,300),placement:String(tour.placement||'').slice(0,160),operator:{name:String(tour.operator||'').slice(0,180)},providerHotelUrl:safeUrl(content.hotel_url),providerImageUrl:image,providerHotelCode:providerCode};}
function localCatalog(hotel){const data=hotel&&hotel.catalog;if(!data||data.source!=='tourvisor'||!Number.isSafeInteger(data.hotel_id)||data.hotel_id<1||data.hotel_id!==hotel.local_id)return null;const text=(value,limit)=>typeof value==='string'?value.trim().slice(0,limit):'',sea=Number(data.sea_distance);return{hotel_id:data.hotel_id,source:data.source,image_url:safeUrl(data.image_url),description:text(data.description,2000),address:text(data.address,1000),subregion:text(data.subregion,180),sea_distance:Number.isFinite(sea)&&sea>0&&sea<=100000?sea:0};}
function normalizeHotel(hotel){const key=hotelKey(hotel),name=hotel&&typeof hotel.name==='string'?hotel.name.trim():'';if(!key||!name||name.length>4096||!Array.isArray(hotel.tours)||hotel.tours.length>MAX_OFFERS_PER_HOTEL)return null;const tours=hotel.tours.map(tour=>normalizeTour(tour,hotel)).filter(Boolean);if(!tours.length)return null;tours.sort((a,b)=>a.price-b.price);const content=hotel.andromeda_content||{},image=safeUrl(content.image_url),catalog=localCatalog(hotel);return{id:key,name,provider:'andromeda',mappingStatus:hotel.mapping_status==='resolved'?'resolved':'unresolved',category:category(hotel.category),rating:Number(hotel.rating)||0,picturelink:catalog?catalog.image_url:'',catalog,country:{name:typeof hotel.country==='string'?hotel.country:String(hotel.country&&hotel.country.name||'')},region:{name:typeof hotel.region==='string'?hotel.region.slice(0,300):''},subRegion:{name:catalog?catalog.subregion:''},seaDistance:catalog?catalog.sea_distance:0,price:tours[0].price,tours,andromedaContent:{imageUrl:image,hotelUrl:safeUrl(content.hotel_url),providerHotelCode:operatorHotelCodeFromImage(image)}};}
function tourIdentity(tour){return String(tour&&tour.provider||'tourvisor')+':'+String(tour&&tour.offerRef||tour&&tour.id||'');}
function merge(base,provider){const rows=new Map();(Array.isArray(base)?base:[]).forEach(hotel=>{if(!hotel||hotel.id===undefined||hotel.id===null)return;const key=String(hotel.id);rows.set(key,hotel);});(Array.isArray(provider)?provider:[]).forEach(input=>{const hotel=normalizeHotel(input);if(!hotel||hotel.mappingStatus!=='resolved'||!/^[1-9][0-9]*$/.test(String(hotel.id)))return;const key=String(hotel.id),existing=rows.get(key);if(!existing){if(hotel.catalog&&hotel.picturelink)rows.set(key,hotel);return;}const seen=new Set((existing.tours||[]).map(tourIdentity)),added=hotel.tours.filter(t=>!seen.has(tourIdentity(t)));const combined=Object.assign({},existing,{tours:(existing.tours||[]).concat(added),providers:Array.from(new Set([].concat(existing.providers||['tourvisor'],'andromeda'))),andromedaContent:hotel.andromedaContent});if(!combined.picturelink&&hotel.picturelink)combined.picturelink=hotel.picturelink;if(!Number(combined.category)&&hotel.category)combined.category=hotel.category;if(!Number(combined.rating)&&hotel.rating)combined.rating=hotel.rating;if(!(combined.region&&combined.region.name)&&hotel.region.name)combined.region=hotel.region;if(!(combined.subRegion&&combined.subRegion.name)&&hotel.subRegion.name)combined.subRegion=hotel.subRegion;if(!Number(combined.seaDistance)&&hotel.seaDistance)combined.seaDistance=hotel.seaDistance;combined.price=combined.tours.reduce((best,tour)=>{const value=amount(tour.price);return value&&value<best?value:best;},Number.POSITIVE_INFINITY);if(!Number.isFinite(combined.price))combined.price=0;rows.set(key,combined);});return Array.from(rows.values());}
function endpoint(value){if(!value||!root.location)return null;try{const url=new URL(value,root.location.href);return url.origin===root.location.origin&&/^\/(?:_preview\/[A-Za-z0-9._-]+\/)?api-andromeda-search3-preview\.php$/.test(url.pathname)&&!url.search&&!url.hash?url:null;}catch(_){return null;}}
function quoteEndpoint(value){const url=endpoint(value);if(!url||url.username||url.password)return null;url.pathname=url.pathname.replace(/api-andromeda-search3-preview\.php$/,'api-andromeda-quote-preview.php');return url;}
function offerRequest(selection,action){
 const tour=selection&&selection.tour,c=context(tour&&tour.offerContext);
 if(!c||tour.provider!=='andromeda'||tour.offerRef!==c.offer_ref||selection.generation!==c.generation||!Number.isSafeInteger(selection.localId)||selection.localId<1||!selection.params||typeof selection.params!=='object'||Array.isArray(selection.params))return null;
 const {hotel_scope,...identity}=c,body={action,generation:selection.generation,page:c.page,params:JSON.parse(JSON.stringify(selection.params)),offer_context:identity};
 // Browser hotel_scope is a seed envelope; SelectedOffer's optional scope is private HOTELS.
 // The existing HTTP contract consumes the public envelope only at the top level.
 if(hotel_scope){if(hotel_scope.local_id!==selection.localId)return null;body.hotel_scope=hotel_scope;}
 return body;
}
function quoteRequest(selection){const body=offerRequest(selection,'quote');if(!body)return null;const reference=listingReference(selection.tour.listingPriceRef);if(reference)body.listing_price_ref=reference;return body;}
function normalizeQuote(data,localId){
 if(!data||data.schema_version!==1||data.provider!=='andromeda'||data.local_id!==localId||data.selection_enabled!==true||data.booking_enabled!==false||!Array.isArray(data.flights)||data.flights.length>100)return null;
 const verified=data.state==='quote_verified'&&data.quote_state==='verified'&&data.final_price_verified===true&&data.flight_selection_required===false;
 const pending=data.state==='flight_selection_required'&&data.quote_state==='unverified'&&data.final_price_verified===false&&data.flight_selection_required===true&&data.final_price===null;
 const price=data.final_price;
 if(!verified&&!pending||verified&&(!price||price.currency!=='RUB'||typeof price.amount!=='string'||!/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/.test(price.amount)||!amount(price)))return null;
 const text=(value,limit)=>typeof value==='string'?value.slice(0,limit):null;
 const flights=[];for(const f of data.flights){if(!f||!['0','1'].includes(f.direction))return null;const flight={direction:f.direction,name:text(f.name,160),datebeg:text(f.datebeg,40),dateend:text(f.dateend,40),class:text(f.class,80)};for(const side of ['departure','arrival']){const point=f[side];flight[side]=point&&typeof point==='object'?{town:text(point.town,100),port:text(point.port,100)}:null;}flights.push(flight);}
 return{state:data.state,finalPrice:verified?{amount:price.amount,currency:price.currency}:null,flights,finalPriceVerified:verified,flightSelectionRequired:pending};
}
const api={safeUrl,operatorHotelCodeFromImage,context,listingReference,normalizeTour,normalizeHotel,merge,endpoint,quoteEndpoint,quoteRequest,normalizeQuote,version:1};root.AnyTourAndromedaProvider=api;
const document=root.document,renderer=root.V2Results,lifecycle=root.V2SearchLifecycle,target=endpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaApi);if(!document||!renderer||!lifecycle||!target||typeof root.fetch!=='function')return;
const originalRender=renderer.render.bind(renderer);let rendering=false,baseItems=[],providerPages=new Map(),lastOptions={},active=null,expansions=new Map(),details=new Map(),detailRequest=null,quotes=new Map();
function providerItems(){const all=[];Array.from(providerPages.keys()).sort((a,b)=>a-b).forEach(page=>all.push(...providerPages.get(page).map(h=>{const expansion=expansions.get(String(h.local_id));if(!expansion||!expansion.tours.length)return h;const extra=expansion.tours,refs=new Set(extra.map(t=>t.offer_ref));return Object.assign({},h,{tours:(expansion.status==='complete'?[]:h.tours.filter(t=>!refs.has(t.offer_ref))).concat(extra)});})));return all;}
function contextKey(value){const normalized=context(value);return normalized?JSON.stringify(normalized):'';}
function detailTours(){const entries=new Map();providerItems().map(normalizeHotel).filter(h=>h&&h.mappingStatus==='resolved'&&/^[1-9][0-9]*$/.test(h.id)).forEach(h=>(h.tours||[]).forEach(t=>{const ref=String(t.offerRef||''),key=contextKey(t.offerContext),localId=Number(h.id);if(!ref||!key)return;if(entries.has(ref)){const current=entries.get(ref);if(!current||current.key!==key||current.localId!==localId)entries.set(ref,null);}else entries.set(ref,{key,tour:t,localId});}));return entries;}
function current(run){return active===run&&lifecycle.generation===run.generation&&!lifecycle.dirty;}
function render(options){const focused=document.activeElement,toggleKey=focused&&focused.matches&&focused.matches('.tour-more-toggle')?focused.dataset.hotelId:'';rendering=true;try{const provider=providerItems(),accepted=detailTours(),items=merge(baseItems,provider).map(h=>{const eligible=h&&((h.provider==='andromeda')||(Array.isArray(h.providers)&&h.providers.includes('andromeda')));if(!eligible)return h;const key=String(h.id),expansion=expansions.get(key),tours=(h.tours||[]).map(t=>{const entry=accepted.get(t.offerRef);if(t.provider!=='andromeda'||!entry||entry.key!==contextKey(t.offerContext))return t;const state=details.get(t.offerRef);return Object.assign({},t,{providerDetail:Object.assign({eligible:true,open:false,status:'idle'},state||{})});});return Object.assign({},h,{tours,andromedaExpansion:expansion?{status:expansion.status,count:expansion.tours.length}: {status:'idle',count:0}});});const result=originalRender(items,options||lastOptions);if(toggleKey&&typeof document.querySelectorAll==='function'){const toggle=Array.from(document.querySelectorAll('.tour-more-toggle')).find(node=>node.dataset.hotelId===toggleKey);if(toggle)toggle.focus({preventScroll:true});}return result;}finally{rendering=false;}}
renderer.render=function(list,options){if(rendering)return originalRender(list,options);baseItems=Array.isArray(list)?list.filter(hotel=>hotel&&hotel.provider!=='andromeda'):[];lastOptions=Object.assign({},options||{});return render(active&&!active.done?Object.assign({},lastOptions,{empty:false}):lastOptions);};
function emit(status,detail){root.dispatchEvent(new CustomEvent('v2:provider-status',{detail:Object.assign({provider:'andromeda',status},detail||{})}));}
async function request(run,page){const response=await root.fetch(target.href,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify({generation:run.generation,page,params:run.params}),signal:run.controller.signal});const payload=await response.json().catch(()=>null),data=payload&&payload.data;if(!response.ok||!payload||payload.ok!==true||!data||data.provider!=='andromeda'||data.generation!==run.generation||data.page!==page||!Array.isArray(data.hotels))throw new Error(payload&&payload.error||'andromeda_unavailable');return data;}
async function start(params,generation){if(active&&active.controller)active.controller.abort();providerPages=new Map();const run={generation,params,controller:new AbortController(),done:false};active=run;emit('loading',{generation});try{let page=1,total=1;do{const data=await request(run,page);if(!current(run))return;providerPages.set(page,data.hotels);total=Math.max(page,Math.min(MAX_PAGES,Number(data.pages_count)||page));render(Object.assign({},lastOptions,{empty:false}));emit('progress',{generation,page,pagesCount:total,hotels:providerItems().length});page++;}while(page<=total&&current(run));if(!current(run))return;run.done=true;render(lastOptions);emit('complete',{generation,pagesCount:providerPages.size,hotels:providerItems().length});}catch(error){if(!current(run)||error&&error.name==='AbortError')return;run.done=true;render(lastOptions);emit('error',{generation,errorCode:String(error&&error.message||'andromeda_unavailable').slice(0,80),retainedHotels:providerItems().length});}}
async function expandHotel(key){
 const run=active;key=String(key);if(!run||!current(run)||expansions.has(key))return;
 const hotel=providerItems().find(h=>String(h.local_id)===key&&h.mapping_status==='resolved');
 const seed=hotel&&(hotel.tours||[]).map(t=>context(t.offer_context)).find(c=>c&&!c.hotel_scope);if(!seed||!/^[1-9][0-9]*$/.test(key))return;
 const queued=Array.from(expansions.values()).some(s=>s.status==='loading'),state={status:queued?'queued':'loading',tours:[],seed,controller:new AbortController()};expansions.set(key,state);render(lastOptions);if(!queued)await loadHotel(run,key,state);
}
async function loadHotel(run,key,state){
 const seed=state.seed;
 try{let page=1,total=1;do{
  const timer=root.setTimeout(()=>state.controller.abort(),30000);let response,payload;
  try{response=await root.fetch(target.href,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify({generation:run.generation,params:run.params,action:'hotel_offers',page,hotel_scope:{local_id:Number(key),seed}}),signal:state.controller.signal});payload=await response.json();}finally{root.clearTimeout(timer);}
  if(!current(run))return;const data=payload&&payload.data;
  if(!response.ok||!payload.ok||!data||data.provider!=='andromeda'||data.generation!==run.generation||data.page!==page||data.grouped!==false||!Array.isArray(data.hotels)||data.hotels.some(h=>String(h.local_id)!==key||!normalizeHotel(h)))throw new Error('invalid_hotel_page');
  const refs=new Set(state.tours.map(t=>t.offer_ref));data.hotels.forEach(h=>h.tours.forEach(t=>{if(!refs.has(t.offer_ref)){state.tours.push(t);refs.add(t.offer_ref);}}));
  total=data.pages_count;if(!Number.isInteger(total)||total<1||total>MAX_PAGES)throw new Error('invalid_page_count');render(lastOptions);page++;
 }while(page<=total&&current(run));state.status='complete';
 }catch(error){if(!current(run))return;state.status='unavailable';}
 if(current(run)){render(lastOptions);const next=Array.from(expansions.entries()).find(([,entry])=>entry.status==='queued');if(next){next[1].status='loading';render(lastOptions);await loadHotel(run,next[0],next[1]);}}
}
async function openDetail(ref){
 const run=active;ref=String(ref||'');if(!run||!current(run)||!/^offer_[a-f0-9]{64}$/.test(ref))return;
 const accepted=detailTours().get(ref),tour=accepted&&accepted.tour;if(!tour)return;
 const body=offerRequest({tour,localId:accepted.localId,generation:run.generation,params:run.params},'offer_detail');if(!body)return;
 const renderDetail=()=>{render(lastOptions);if(typeof document.querySelectorAll!=='function')return;const button=Array.from(document.querySelectorAll('[data-andromeda-detail]')).find(node=>node.dataset&&node.dataset.andromedaDetail===ref);if(button&&typeof button.focus==='function')button.focus({preventScroll:true});};
 const previous=details.get(ref);if(previous&&previous.open){previous.open=false;if(detailRequest&&detailRequest.ref===ref)detailRequest.controller.abort();renderDetail();return;}
 if(previous&&previous.status==='complete'){previous.open=true;renderDetail();return;}
 if(previous&&previous.status==='error'&&previous.retry===false){previous.open=true;renderDetail();return;}
 if(detailRequest){detailRequest.controller.abort();const old=details.get(detailRequest.ref);if(old)old.open=false;}
 const state={open:true,status:'loading'},request={ref,controller:new AbortController()};details.set(ref,state);detailRequest=request;renderDetail();
 let timedOut=false;const timer=root.setTimeout(()=>{timedOut=true;request.controller.abort();},15000);
 try{
  const response=await root.fetch(target.href,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(body),signal:request.controller.signal});
  const payload=await response.json().catch(()=>null),data=payload&&payload.data;if(!current(run)||detailRequest!==request)return;
  const responseContext=data&&context(data.offer_context),latest=detailTours().get(ref),sameContext=responseContext&&contextKey(responseContext)===contextKey(body.offer_context)&&latest&&latest.key===accepted.key&&latest.localId===accepted.localId;
  if(!response.ok||!payload||payload.ok!==true){state.retry=!!response&&response.status>=500;throw new Error('request_failed');}
  if(!data||data.provider!=='andromeda'||data.local_id!==accepted.localId||!sameContext||!amount(data.price)||String(data.price.currency||'')!=='RUB'){state.retry=false;throw new Error('context_failed');}
  const text=(value,max)=>String(value==null?'':value).slice(0,max),integer=value=>Number.isSafeInteger(Number(value))&&Number(value)>=0?Number(value):0;
  state.status='complete';state.data={hotel:text(data.hotel,4096),operator:text(data.operator,180),room:text(data.room,300),placement:text(data.placement,160),checkin:text(data.checkin,10),nights:integer(data.nights),adults:integer(data.adults),children:integer(data.children),meal:text(data.meal,160),price:amount(data.price)};
 }catch(error){if(!current(run)||detailRequest!==request||!state.open)return;state.status='error';state.retry=state.retry!==false;state.message=timedOut?'Подробности не успели загрузиться.':state.retry?'Не удалось загрузить подробности. Список предложений сохранён.':'Не удалось подтвердить предложение. Повторите поиск.';}
 finally{root.clearTimeout(timer);if(detailRequest===request)detailRequest=null;}
 if(current(run))renderDetail();
}
function freeze(value){if(value&&typeof value==='object'){Object.values(value).forEach(freeze);Object.freeze(value);}return value;}
function prepareQuote(ref){
 const run=active,accepted=detailTours().get(ref);if(!run||!current(run)||!accepted)return null;
 const matches=[];for(const hotel of renderer.state&&renderer.state.items||[])for(const tour of hotel.tours||[])if(tour.provider==='andromeda'&&tour.offerRef===ref)matches.push({hotel,tour});
 if(matches.length!==1)return null;const {hotel,tour}=matches[0],localId=Number(hotel.id);
 if(contextKey(tour.offerContext)!==accepted.key||!Number.isSafeInteger(localId)||localId<1||localId!==accepted.localId)return null;
 const selection=JSON.parse(JSON.stringify({localId,generation:run.generation,params:run.params,tour,hotel:{id:hotel.id,name:hotel.name,country:hotel.country,region:hotel.region,subRegion:hotel.subRegion,picturelink:hotel.picturelink}}));
 return quoteRequest(selection)?freeze(selection):null;
}
async function verifyQuote(ref){
 const run=active,selection=prepareQuote(ref),url=quoteEndpoint(target.href);if(!selection||!url)throw new Error('andromeda_quote_stale');
 const key=contextKey(selection.tour.offerContext),previous=quotes.get(key);if(previous)return previous.promise;
 const controller=new AbortController(),state={controller,selection,status:'loading',promise:null};quotes.set(key,state);
 state.promise=(async()=>{
  const timer=root.setTimeout(()=>controller.abort(),45000);
  try{
   const response=await root.fetch(url.href,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(quoteRequest(selection)),signal:controller.signal});
   const payload=await response.json().catch(()=>null),currentSelection=prepareQuote(ref);
   if(controller.signal.aborted||!current(run)||!currentSelection||currentSelection.localId!==selection.localId||contextKey(currentSelection.tour.offerContext)!==key)throw new Error('andromeda_quote_stale');
   if(!response.ok||!payload||payload.ok!==true)throw new Error('andromeda_quote_unavailable');
   const quote=normalizeQuote(payload.data,selection.localId);if(!quote)throw new Error('andromeda_quote_invalid');
   state.status='complete';return freeze({selection,quote});
  }catch(error){state.status='unknown';throw error;}
  finally{root.clearTimeout(timer);}
 })();
 return state.promise;
}
api.prepareQuote=prepareQuote;
api.verifyQuote=verifyQuote;
api.openDetail=openDetail;
api.expandHotel=expandHotel;
root.addEventListener('v2:hotel-offers-toggle',event=>{const detail=event.detail||{},key=String(detail.hotelId||'');if(detail.expanded===true){expandHotel(key);return;}const pending=expansions.get(key);if(pending&&pending.status==='queued'){expansions.delete(key);render(lastOptions);}});
if(typeof document.addEventListener==='function')document.addEventListener('click',event=>{const detail=event.target&&event.target.closest&&event.target.closest('[data-andromeda-detail]');if(!detail)return;event.preventDefault();const ref=detail.dataset.andromedaDetail;if(detail.dataset.detailRetry==='1'){const state=details.get(ref);if(state)state.open=false;}openDetail(ref);});
root.addEventListener('v2:search-reset',event=>{quotes.forEach(s=>s.controller.abort());quotes=new Map();if(detailRequest)detailRequest.controller.abort();detailRequest=null;details=new Map();expansions.forEach(s=>s.controller.abort());expansions=new Map();if(active&&active.controller)active.controller.abort();active=null;providerPages=new Map();baseItems=[];lastOptions={};const detail=event.detail||{},snapshot=lifecycle.snapshot;if(detail.dirty||!snapshot||!Number.isInteger(detail.generation)||detail.generation<1)return;start(snapshot,detail.generation);});
})(typeof window!=='undefined'?window:globalThis);
