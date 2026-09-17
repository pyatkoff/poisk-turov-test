(function(root){'use strict';
// Canonical Search3 result state for the local preview. Supplier rows are only inputs.
const path='/_preview/search3-local-candidate/',endpoint=path+'data/hotel-details-read-v1.php';
let activeOwner=null;
function id(value){const s=String(value??'');return (typeof value==='string'||typeof value==='number')&&/^[1-9][0-9]*$/.test(s)&&Number.isSafeInteger(Number(s))?s:'';}
function legacyId(h){const key=id(h&&h.id),provider=String(h&&h.provider||'tourvisor').toLowerCase();if(!key||!['tourvisor','anex','andromeda'].includes(provider))return'';if(h.mappingStatus!==undefined&&h.mappingStatus!=='resolved')return'';return provider==='tourvisor'||h.mappingStatus==='resolved'?key:'';}
function offerIdentity(t){const provider=String(t&&t.provider||'').toLowerCase(),token=String(t&&(t.offerIdentityDigest||t.offerRef||t.id)||'');return provider&&token?provider+':'+token:'';}
function profile(raw){
 if(!raw||raw.catalog!=='anytour'||!id(raw.id)||!Number.isSafeInteger(raw.revision)||raw.revision<1||typeof raw.name!=='string'||!raw.name.trim())throw new Error('Invalid own profile');
 const info=raw.hotelInformation&&typeof raw.hotelInformation==='object'?raw.hotelInformation:{};
 return Object.assign({},raw,{infrastructure:info.infrastructure||[],services:info.services||[],meals:info.meals||[],roomTypes:info.roomTypes||null});
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
 if(pathname!==path.slice(0,-1)&&!pathname.startsWith(path))return null;
 if(typeof refresh!=='function')throw new TypeError('Renderer callback required');
 let epoch=0,raw=[],options={},links=new Map(),profiles=new Map(),anchors=new Map(),storedOffers=new Map(),legacyOffers=new Map(),legacyStates=new Map(),missing=new Set(),failed=new Set(),pending=new Set(),workers=new Set();
 function reset(){epoch++;workers.forEach(task=>task.controller.abort());workers=new Set();pending=new Set();links=new Map();profiles=new Map();anchors=new Map();storedOffers=new Map();legacyOffers=new Map();legacyStates=new Map();missing=new Set();failed=new Set();raw=[];options={};}
 function putProfile(rawProfile){const p=profile(rawProfile),key=id(p.id),previous=profiles.get(key);if(previous&&p.revision===previous.revision&&JSON.stringify(p)!==JSON.stringify(previous))throw new Error('Conflicting profile revision');if(!previous||p.revision>=previous.revision)profiles.set(key,p);return profiles.get(key);}
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
  storedOffers.forEach((bucket,own)=>{const first=bucket.values().next().value,view=ensureView(groups,own,first&&first.legacyHotelId);if(!view)return;let seen=seenByOwn.get(own);if(!seen){seen=new Set();seenByOwn.set(own,seen);}bucket.forEach(record=>addTour(view,record.tour,record.legacyHotelId,seen));});
  legacyOffers.forEach((bucket,old)=>{const own=links.get(old),view=ensureView(groups,own,old);if(!view)return;let seen=seenByOwn.get(own);if(!seen){seen=new Set();seenByOwn.set(own,seen);}bucket.forEach(record=>addTour(view,record.tour,old,seen));applyLegacyState(view,old);});
  raw.forEach(h=>{
   const old=legacyId(h),own=links.get(old),view=ensureView(groups,own,h&&h.id),tours=Array.isArray(h&&h.tours)?h.tours:[];if(!view||!tours.length)return;
   let seen=seenByOwn.get(own);if(!seen){seen=new Set();seenByOwn.set(own,seen);}tours.forEach(t=>addTour(view,t,old,seen));
   view.providers=Array.from(new Set(view.providers.concat(Array.isArray(h.providers)?h.providers:[h.provider||'tourvisor']).map(v=>String(v||'').toLowerCase()).filter(Boolean)));
   if(h.andromedaExpansion&&(!view.andromedaExpansion||view.andromedaExpansion.status!=='loading'))view.andromedaExpansion=h.andromedaExpansion;
  });
  return Array.from(groups.values()).filter(view=>view.tours.length).map(view=>{const prices=view.tours.map(t=>Number(t&&t.price)).filter(n=>Number.isFinite(n)&&n>0);view.price=prices.length?Math.min(...prices):0;return view;});
 }
 function wantedLegacyIds(){return Array.from(new Set(raw.map(legacyId).filter(Boolean).concat(Array.from(legacyOffers.keys()))));}
 function pump(){
  const ids=wantedLegacyIds().filter(key=>!links.has(key)&&!missing.has(key)&&!failed.has(key)&&!pending.has(key));
  while(ids.length&&workers.size<2){
   const requested=ids.splice(0,100),generation=epoch,controller=new AbortController(),task={controller};workers.add(task);requested.forEach(key=>pending.add(key));
   const timer=setTimeout(()=>controller.abort(),15000),query=new URLSearchParams({catalog:'anytour'});requested.forEach(key=>query.append('legacyHotelIds[]',key));
   Promise.resolve().then(()=>{const fetcher=root.V2Runtime&&root.V2Runtime.fetch||root.fetch.bind(root);return fetcher(endpoint+'?'+query.toString(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});}).then(response=>{if(!response.ok)throw new Error('Catalogue HTTP '+response.status);return response.json();}).then(payload=>{
    if(generation!==epoch)return;if(controller.signal.aborted)throw new Error('Catalogue timeout');
    const checked=batch(payload,requested);checked.profiles.forEach(p=>putProfile(p));checked.links.forEach((own,old)=>links.set(old,own));checked.missing.forEach(key=>missing.add(key));
   }).catch(()=>{if(generation===epoch)requested.forEach(key=>failed.add(key));}).finally(()=>{
    clearTimeout(timer);if(generation!==epoch)return;workers.delete(task);requested.forEach(key=>pending.delete(key));refresh();
   });
  }
 }
 function status(results){
  const ids=wantedLegacyIds();if(!ids.length)return;
  const loading=ids.some(key=>!links.has(key)&&!missing.has(key)&&!failed.has(key)),error=ids.some(key=>failed.has(key)),excluded=raw.some(h=>!legacyId(h)||missing.has(legacyId(h)))||ids.some(key=>missing.has(key));
  if(!loading&&!error&&!excluded)return;
  const node=document.createElement('div');node.className='canonical-catalog-status search-progress-error';node.setAttribute('role',error?'alert':'status');
  const text=document.createElement('span');text.textContent=error?'Не удалось загрузить описания некоторых отелей.':loading?'Загружаем описания и фотографии отелей…':'Часть предложений пока недоступна: карточки отелей ещё не подготовлены.';node.appendChild(text);
  if(error){const button=document.createElement('button');button.type='button';button.className='secondary canonical-profile-retry';button.textContent='Повторить загрузку';button.addEventListener('click',()=>{failed.clear();refresh();});node.appendChild(button);}
  results.prepend(node);
 }
 root.addEventListener('v2:search-started',reset);root.addEventListener('v2:search-reset',reset);
 const api={read(list,opts){raw=list.slice();options=Object.assign({},opts);pump();return project();},source:()=>raw,options:()=>options,details:h=>profiles.get(id(h&&h.anytourHotelId))||null,status,reset,upsertHotel:putProfile,upsertOffer,upsertLegacyOffer,setLegacyHotelState,clearOffers,refresh:()=>refresh()};activeOwner=api;return api;
}
root.Search3CanonicalProfilesV1=Object.freeze({create,current:()=>activeOwner});
})(window);
