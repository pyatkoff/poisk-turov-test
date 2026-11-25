(function(root){'use strict';
// Canonical Search3 result state for the local preview. Supplier rows are only inputs.
const path='/_preview/search3-local-candidate/',endpoint=path+'data/hotel-details-read-v1.php';
let activeOwner=null;
function id(value){const s=String(value??'');return (typeof value==='string'||typeof value==='number')&&/^[1-9][0-9]*$/.test(s)&&Number.isSafeInteger(Number(s))?s:'';}
function legacyId(h){const key=id(h&&h.id),provider=String(h&&h.provider||'tourvisor').toLowerCase();if(!key||!['tourvisor','anex','andromeda'].includes(provider))return'';if(h.mappingStatus!==undefined&&h.mappingStatus!=='resolved')return'';return provider==='tourvisor'||h.mappingStatus==='resolved'?key:'';}
function offerIdentity(t){const provider=String(t&&t.provider||'').toLowerCase(),token=String(t&&(t.offerIdentityDigest||t.offerRef||t.id)||'');return provider&&token?provider+':'+token:'';}
// Display-only evidence; never replaces the canonical profile or offer identity.
function localContentRoute(){return String(root.location?.pathname||'').startsWith(path);}
function contentMeta(raw){
 const m=raw.localContentMeta;
 if(!m||m.source!=='anytour-local-tv-catalog-v1'||m.anytourHotelId!==Number(raw.id)||m.canonicalRevision!==raw.revision
  ||!Number.isSafeInteger(m.tourvisorHotelId)||m.tourvisorHotelId<1||!Number.isSafeInteger(m.revision)||m.revision<1
  ||!['pending','ready','retry'].includes(m.contentState)||typeof m.detailsAvailable!=='boolean'
  ||!Array.isArray(m.manualFields)||!m.manualFields.length||m.manualFields.length>3
  ||m.manualFields.some(field=>!['description','images','primaryImage'].includes(field)||!Object.hasOwn(raw,field)))return null;
 const absent=Array.isArray(m.sourceAbsent)&&m.sourceAbsent.length<=256&&m.sourceAbsent.every(field=>typeof field==='string'&&field.length<=192&&/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*$/.test(field))?Object.freeze([...m.sourceAbsent]):null;
 const fetched=m.contentState==='ready'&&typeof m.detailsFetchedAt==='string'&&/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(m.detailsFetchedAt)?m.detailsFetchedAt:null;
 return Object.freeze({source:m.source,anytourHotelId:m.anytourHotelId,tourvisorHotelId:m.tourvisorHotelId,canonicalRevision:m.canonicalRevision,
  revision:m.revision,manualFields:Object.freeze([...new Set(m.manualFields)].sort()),sourceAbsent:absent,contentState:m.contentState,
  detailsAvailable:m.contentState==='ready'&&m.detailsAvailable,detailsFetchedAt:fetched});
}
function withoutContentMeta(p){const {localContentMeta,...content}=p;return content;}
function manualFields(d){return new Set(localContentRoute()&&d?contentMeta(d)?.manualFields||[]:[]);}
function manualDetails(d){return manualFields(d).has('description')?{description:d.description}:null;}
function attribution(d,shownImages,plainText){
 const fields=manualFields(d),parts=[];
 if(fields.has('description')&&plainText(d.description))parts.push('Описание');
 const shown=shownImages.slice(0,5),manual=new Set();
 const add=url=>{if(typeof url==='string'&&/^https:\/\//i.test(url.trim()))manual.add(url.trim());};
 if(fields.has('images')&&Array.isArray(d.images))d.images.forEach(add);
 if(fields.has('primaryImage'))add(d.primaryImage);
 const count=shown.filter(url=>manual.has(url)).length;
 if(count)parts.push(count===shown.length?'фото':'часть фото');
 if(!parts.length)return '';
 const label=parts.join(' и ');
 // Fixed labels only; no user or catalogue text is interpolated into HTML.
 return '<p class="hotel-content-attribution"><small>'+label[0].toUpperCase()+label.slice(1)+': материалы AnyTour</small></p>';
}
function profile(raw){
 if(!raw||raw.catalog!=='anytour'||!id(raw.id)||!Number.isSafeInteger(raw.revision)||raw.revision<1||typeof raw.name!=='string'||!raw.name.trim())throw new Error('Invalid own profile');
 const info=raw.hotelInformation&&typeof raw.hotelInformation==='object'?raw.hotelInformation:{};
 const p=Object.assign({},raw,{infrastructure:info.infrastructure||[],services:info.services||[],meals:info.meals||[],roomTypes:info.roomTypes||null});
 if(localContentRoute()){const meta=contentMeta(raw);delete p.localContentMeta;if(meta)p.localContentMeta=meta;}
 return p;
}
function batch(payload,ids){
 if(!payload||payload.ok!==true||payload.source!=='anytour-canonical-catalog'||payload.catalog!=='anytour'||!Array.isArray(payload.requestedLegacyIds)||payload.requestedLegacyIds.map(id).join(',')!==ids.join(',')||!Array.isArray(payload.items)||!Array.isArray(payload.links)||!Array.isArray(payload.missingLegacyIds))throw new Error('Invalid catalogue response');
 const expected=new Set(ids),seen=new Set(),profiles=new Map(),links=new Map(),used=new Set();
 payload.items.forEach(raw=>{const p=profile(raw),key=id(p.id);if(profiles.has(key))throw new Error('Duplicate profile');profiles.set(key,p);});
 payload.links.forEach(link=>{const old=id(link&&link.legacyHotelId),own=id(link&&link.anytourHotelId);if(!expected.has(old)||seen.has(old)||!profiles.has(own))throw new Error('Invalid link');seen.add(old);used.add(own);links.set(old,own);});
 const missing=payload.missingLegacyIds.map(value=>{const key=id(value);if(!expected.has(key)||seen.has(key))throw new Error('Invalid missing ID');seen.add(key);return key;});
 if(seen.size!==expected.size||used.size!==profiles.size)throw new Error('Incomplete catalogue response');
 return{profiles,links,missing};
}
function create(refresh){
 const pathname=String(root.location&&root.location.pathname||'');
 const visualPath='/_preview/search3-next-candidate/visual-search/';
 const publicEntry=root.V2_CONFIG?.search3PublicEntry==='live'&&['/poisk-turov/','/poisk-turov/index.php'].includes(pathname);
 const homeEntry=root.AnyTourPublicConfigV1?.valid===true&&root.AnyTourPublicConfigV1.entry==='home'
  &&root.V2_CONFIG?.search3FormOnly===true&&root.V2_CONFIG?.search3PublicEntry==='live'&&['/','/index.php'].includes(pathname);
 if(!publicEntry&&!homeEntry&&pathname!==path.slice(0,-1)&&!pathname.startsWith(path)&&pathname!==visualPath.slice(0,-1)&&!pathname.startsWith(visualPath))return null;
 if(typeof refresh!=='function')throw new TypeError('Renderer callback required');
 let lifecycleGeneration=0;
 let epoch=0,raw=[],options={},links=new Map(),profiles=new Map(),anchors=new Map(),storedOffers=new Map(),legacyOffers=new Map(),legacyStates=new Map(),missing=new Set(),failed=new Set(),pending=new Set(),workers=new Set(),profileReads=new Map(),retryBatches=[],splitBudget=32;
 function reset(){epoch++;workers.forEach(task=>task.controller.abort());profileReads.forEach(read=>{if(!read.started)read.resolve(null);});workers=new Set();profileReads=new Map();pending=new Set();links=new Map();profiles=new Map();anchors=new Map();storedOffers=new Map();legacyOffers=new Map();legacyStates=new Map();missing=new Set();failed=new Set();retryBatches=[];splitBudget=32;raw=[];options={};}
 function checkedProfile(rawProfile){
  const p=profile(rawProfile),previous=profiles.get(id(p.id));
  if(previous&&p.revision===previous.revision){
   const local=localContentRoute();
   if(JSON.stringify(local?withoutContentMeta(p):p)!==JSON.stringify(local?withoutContentMeta(previous):previous))throw new Error('Conflicting profile revision');
   const before=previous.localContentMeta,after=p.localContentMeta;
   if(local&&before&&after&&before.tourvisorHotelId===after.tourvisorHotelId&&after.revision<before.revision)p.localContentMeta=before;
  }
  return previous&&p.revision<previous.revision?previous:p;
 }
 function putProfile(rawProfile){const p=checkedProfile(rawProfile);profiles.set(id(p.id),p);return p;}
 async function readProfile(anytourHotelId){
  const key=id(anytourHotelId);if(!key)throw new TypeError('Invalid own hotel ID');
  if(profileReads.has(key))return profileReads.get(key).promise;
  const generation=epoch,read={started:false};
  read.promise=new Promise((resolve,reject)=>{read.resolve=resolve;read.reject=reject;});
  profileReads.set(key,read);pump();
  try{return await read.promise;}finally{
   // Only pending work is shared; a later explicit read must check freshness.
   if(generation===epoch&&profileReads.get(key)===read)profileReads.delete(key);
  }
 }
 async function fetchProfile(key){
  const generation=epoch,controller=new AbortController(),task={controller};workers.add(task);
  const timer=setTimeout(()=>controller.abort(),15000);
  try{
   const fetcher=root.V2Runtime&&root.V2Runtime.fetch||root.fetch.bind(root);
   const response=await fetcher(endpoint+'?'+new URLSearchParams({catalog:'anytour',anytourHotelId:key}),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
   if(!response.ok)throw new Error('Catalogue HTTP '+response.status);
   const payload=await response.json();if(generation!==epoch)return null;
   if(controller.signal.aborted)throw new Error('Catalogue timeout');
   if(!payload||payload.ok!==true||payload.source!=='anytour-canonical-catalog'||payload.catalog!=='anytour'||id(payload.item&&payload.item.id)!==key)throw new Error('Invalid own profile response');
   return putProfile(payload.item);
  }finally{
   clearTimeout(timer);workers.delete(task);
   // Standalone/favourite reads share batch capacity; resume waiting results.
   if(generation===epoch)pump();
  }
 }
 function clearOffers(source){
  const key=String(source||'');if(!key)throw new TypeError('Offer source required');
  storedOffers.forEach((bucket,own)=>{bucket.forEach((record,offerKey)=>{if(record.source===key)bucket.delete(offerKey);});if(!bucket.size)storedOffers.delete(own);});
  legacyOffers.forEach((bucket,legacy)=>{bucket.forEach((record,offerKey)=>{if(record.source===key)bucket.delete(offerKey);});if(!bucket.size)legacyOffers.delete(legacy);});
  legacyStates.forEach((bucket,legacy)=>{bucket.delete(key);if(!bucket.size)legacyStates.delete(legacy);});
 }
 function upsertOffer(anytourHotelId,tour,meta){
  const own=id(anytourHotelId),legacy=id(meta&&meta.legacyHotelId),source=String(meta&&meta.source||''),identity=offerIdentity(tour);if(!own||!legacy||!source||!identity||!tour||typeof tour!=='object')throw new Error('Invalid canonical offer');
  if(!profiles.has(own))throw new Error('Offer profile missing');
  let bucket=storedOffers.get(own);if(!bucket){bucket=new Map();storedOffers.set(own,bucket);}bucket.set(identity,{tour,legacyHotelId:legacy,source});links.set(legacy,own);if(!anchors.has(own))anchors.set(own,legacy);return identity;
 }
 function upsertLegacyOffer(legacyHotelId,tour,meta){
  const legacy=id(legacyHotelId),source=String(meta&&meta.source||''),identity=offerIdentity(tour);if(!legacy||!source||!identity||!tour||typeof tour!=='object')throw new Error('Invalid legacy offer');
  let bucket=legacyOffers.get(legacy);if(!bucket){bucket=new Map();legacyOffers.set(legacy,bucket);}bucket.set(identity,{tour,legacyHotelId:legacy,source});return identity;
 }
 function setLegacyHotelState(legacyHotelId,state,meta){
  const legacy=id(legacyHotelId),source=String(meta&&meta.source||'');if(!legacy||!source||state!==null&&(!state||typeof state!=='object'||Array.isArray(state)))throw new Error('Invalid legacy hotel state');
  let bucket=legacyStates.get(legacy);if(state===null){if(bucket){bucket.delete(source);if(!bucket.size)legacyStates.delete(legacy);}return;}
  if(!bucket){bucket=new Map();legacyStates.set(legacy,bucket);}bucket.set(source,Object.assign({},state));
 }
 function ensureView(groups,own,anchor){const p=profiles.get(own);if(!p)return null;let view=groups.get(own);if(view)return view;const saved=anchors.get(own),stable=id(saved)?saved:id(anchor)?anchor:p.id;if(!anchors.has(own)&&id(stable))anchors.set(own,stable);view=Object.assign({},p,{id:anchors.get(own)||stable,anytourHotelId:p.id,canonicalLegacyIds:[],canonicalOfferLinks:[],tours:[],providers:[],picturelink:p.primaryImage||'',price:0});groups.set(own,view);return view;}
 function addTour(view,tour,legacy,seen){const key=offerIdentity(tour);if(key&&seen.has(key))return;if(key)seen.add(key);view.tours.push(tour);if(legacy&&!view.canonicalLegacyIds.includes(legacy))view.canonicalLegacyIds.push(legacy);if(!view.canonicalOfferLinks.some(link=>link.tour===tour&&String(link.legacyHotelId)===String(legacy)))view.canonicalOfferLinks.push({tour,legacyHotelId:legacy});const provider=String(tour&&tour.provider||'').toLowerCase();if(provider&&!view.providers.includes(provider))view.providers.push(provider);}
 function applyLegacyState(view,legacy){const bucket=legacyStates.get(legacy),state=bucket&&bucket.get('andromeda');if(state&&(!view.andromedaExpansion||view.andromedaExpansion.status!=='loading'))view.andromedaExpansion=Object.assign({},state);}
 function project(){
  const groups=new Map(),seenByOwn=new Map();
  const detail=root.V2SearchLifecycle&&root.V2SearchLifecycle.hotelDetail;
  if(detail&&detail.profileOnly){const view=ensureView(groups,id(detail.hotelId));return view?[view]:[];}
  // Exact current supplier rows must win the same provider+offer identity over
  // LOCAL cache. The cache is inventory/fallback, not a fresher selectable copy.
  legacyOffers.forEach((bucket,old)=>{const own=links.get(old),view=ensureView(groups,own,old);if(!view)return;let seen=seenByOwn.get(own);if(!seen){seen=new Set();seenByOwn.set(own,seen);}bucket.forEach(record=>addTour(view,record.tour,old,seen));applyLegacyState(view,old);});
  raw.forEach(h=>{
   const old=legacyId(h),own=links.get(old),view=ensureView(groups,own,h&&h.id),tours=Array.isArray(h&&h.tours)?h.tours:[];if(!view||!tours.length)return;
   let seen=seenByOwn.get(own);if(!seen){seen=new Set();seenByOwn.set(own,seen);}tours.forEach(t=>addTour(view,t,old,seen));
   view.providers=Array.from(new Set(view.providers.concat(Array.isArray(h.providers)?h.providers:[h.provider||'tourvisor']).map(v=>String(v||'').toLowerCase()).filter(Boolean)));
   if(h.andromedaExpansion&&(!view.andromedaExpansion||view.andromedaExpansion.status!=='loading'))view.andromedaExpansion=h.andromedaExpansion;
  });
  storedOffers.forEach((bucket,own)=>{const first=bucket.values().next().value,view=ensureView(groups,own,first&&first.legacyHotelId);if(!view)return;let seen=seenByOwn.get(own);if(!seen){seen=new Set();seenByOwn.set(own,seen);}bucket.forEach(record=>addTour(view,record.tour,record.legacyHotelId,seen));});
  return Array.from(groups.values()).filter(view=>view.tours.length).map(view=>{const prices=view.tours.map(t=>Number(t&&t.price)).filter(n=>Number.isFinite(n)&&n>0);view.price=prices.length?Math.min(...prices):0;return view;});
 }
 function wantedLegacyIds(){return Array.from(new Set(raw.map(legacyId).filter(Boolean).concat(Array.from(legacyOffers.keys()))));}
 function unresolved(key,wanted){return wanted.has(key)&&!links.has(key)&&!missing.has(key)&&!failed.has(key)&&!pending.has(key);}
 function nextBatch(){
  const wanted=new Set(wantedLegacyIds());
  while(retryBatches.length){
   const retry=retryBatches.shift().filter(key=>unresolved(key,wanted));
   if(retry.length)return retry;
  }
  return Array.from(wanted).filter(key=>unresolved(key,wanted)).slice(0,100);
 }
 function checkedBatch(payload,requested){
  let checked;
  try{checked=batch(payload,requested);}catch(error){
   const failure=new Error(error&&error.message||'Invalid canonical catalogue batch');
   failure.name='Search3CanonicalBatchValidationError';
   throw failure;
  }
  // State/revision conflicts stay fail-closed for the whole subset and require
  // an explicit retry; only malformed catalogue response rows are isolated.
  const prepared=Array.from(checked.profiles.values(),checkedProfile);
  return{checked,prepared};
 }
 function isolateInvalidBatch(requested){
  if(requested.length<2||splitBudget<2){requested.forEach(key=>failed.add(key));return;}
  const middle=Math.ceil(requested.length/2);
  splitBudget-=2;
  retryBatches.push(requested.slice(0,middle),requested.slice(middle));
 }
 function pump(){
  // nextBatch consumes split work; do not dequeue it until a worker is free.
  while(workers.size<2){
   const requested=nextBatch();if(!requested.length)break;
   const current=requested,generation=epoch,controller=new AbortController(),task={controller};workers.add(task);current.forEach(key=>pending.add(key));
   const timer=setTimeout(()=>controller.abort(),15000),query=new URLSearchParams({catalog:'anytour'});current.forEach(key=>query.append('legacyHotelIds[]',key));
   Promise.resolve().then(()=>{const fetcher=root.V2Runtime&&root.V2Runtime.fetch||root.fetch.bind(root);return fetcher(endpoint+'?'+query.toString(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});}).then(response=>{if(!response.ok)throw new Error('Catalogue HTTP '+response.status);return response.json();}).then(payload=>{
    if(generation!==epoch)return;if(controller.signal.aborted)throw new Error('Catalogue timeout');
    const validated=checkedBatch(payload,current),checked=validated.checked;
    validated.prepared.forEach(p=>profiles.set(id(p.id),p));checked.links.forEach((own,old)=>links.set(old,own));checked.missing.forEach(key=>missing.add(key));
   }).catch(error=>{
    if(generation!==epoch)return;
    if(error&&error.name==='Search3CanonicalBatchValidationError')isolateInvalidBatch(current);
    else current.forEach(key=>failed.add(key));
   }).finally(()=>{
    clearTimeout(timer);if(generation!==epoch)return;workers.delete(task);current.forEach(key=>pending.delete(key));
    try{refresh();}finally{if(generation===epoch)pump();}
   });
  }
  // Current result descriptions take the next free slot before waiting favourites.
  // Both consumers share the existing two-worker limit and in-flight ID registry.
  for(const [key,read] of profileReads){
   if(workers.size>=2)break;
   if(!read.started){read.started=true;fetchProfile(key).then(read.resolve,read.reject);}
  }
 }
 function status(results){
  const ids=wantedLegacyIds();if(!ids.length)return;
  const loading=ids.some(key=>!links.has(key)&&!missing.has(key)&&!failed.has(key)),error=ids.some(key=>failed.has(key)),excluded=raw.some(h=>!legacyId(h)||missing.has(legacyId(h)))||ids.some(key=>missing.has(key));
  if(!loading&&!error&&!excluded)return;
  const node=document.createElement('div');node.className='canonical-catalog-status search-progress-error';node.setAttribute('role',error?'alert':'status');
  const text=document.createElement('span');text.textContent=error?'Не удалось загрузить описания некоторых отелей.':loading?'Загружаем описания и фотографии отелей…':'Часть предложений пока недоступна.';node.appendChild(text);
  if(error){const button=document.createElement('button');button.type='button';button.className='secondary canonical-profile-retry';button.textContent='Повторить загрузку';button.addEventListener('click',()=>{failed.clear();retryBatches=[];splitBudget=32;refresh();});node.appendChild(button);}
  results.prepend(node);
 }
 function requestRefresh(patch){if(!patch||typeof patch!=='object'||Array.isArray(patch))return refresh();const previous=options;options=Object.assign({},options,patch);try{return refresh();}finally{options=previous;}}
 // Preparing a search already invalidates the previous generation. Its slower
 // started notification must not erase offers or profile work that arrived first.
 function resetForSearch(event){
  const generation=event&&event.detail&&event.detail.generation;
  if(!Number.isSafeInteger(generation)||generation<1){reset();return;}
  const current=root.V2SearchLifecycle&&root.V2SearchLifecycle.generation;
  if(generation<lifecycleGeneration||Number.isSafeInteger(current)&&generation<current)return;
  if(event.type==='v2:search-started'&&generation===lifecycleGeneration)return;
  lifecycleGeneration=generation;reset();
 }
 root.addEventListener('v2:search-started',resetForSearch);root.addEventListener('v2:search-reset',resetForSearch);
 const api={read(list,opts){raw=list.slice();options=Object.assign({},opts);pump();return project();},readProfile,source:()=>raw,options:()=>options,details:h=>profiles.get(id(h&&h.anytourHotelId))||null,status,reset,manualDetails,attribution,upsertHotel:putProfile,upsertOffer,upsertLegacyOffer,setLegacyHotelState,clearOffers,refresh:requestRefresh};activeOwner=api;return api;
}
root.Search3CanonicalProfilesV1=Object.freeze({create,current:()=>activeOwner});
})(window);
