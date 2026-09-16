(function(root){'use strict';
// Read-only profile hydration for the existing renderer, never a supplier matcher.
const path='/_preview/search3-local-candidate/',endpoint=path+'data/hotel-details-read-v1.php';
function id(value){const s=String(value??'');return (typeof value==='string'||typeof value==='number')&&/^[1-9][0-9]*$/.test(s)&&Number.isSafeInteger(Number(s))?s:'';}
function legacyId(h){const key=id(h&&h.id),provider=String(h&&h.provider||'tourvisor').toLowerCase();if(!key||!['tourvisor','anex','andromeda'].includes(provider))return'';if(h.mappingStatus!==undefined&&h.mappingStatus!=='resolved')return'';return provider==='tourvisor'||h.mappingStatus==='resolved'?key:'';}
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
 let epoch=0,raw=[],options={},links=new Map(),profiles=new Map(),anchors=new Map(),missing=new Set(),failed=new Set(),pending=new Set(),workers=new Set();
 function reset(){epoch++;workers.forEach(task=>task.controller.abort());workers=new Set();pending=new Set();links=new Map();profiles=new Map();anchors=new Map();missing=new Set();failed=new Set();raw=[];options={};}
 function project(){
  const groups=new Map();
  raw.forEach(h=>{
   const old=legacyId(h),own=links.get(old),p=profiles.get(own),tours=Array.isArray(h&&h.tours)?h.tours:[];if(!p||!tours.length)return;
   let view=groups.get(own);
   if(!view){if(!anchors.has(own))anchors.set(own,h.id);view=Object.assign({},p,{id:anchors.get(own),anytourHotelId:p.id,canonicalLegacyIds:[],canonicalOfferLinks:[],tours:[],providers:[],picturelink:p.primaryImage||'',price:0});groups.set(own,view);}
   if(!view.canonicalLegacyIds.includes(old))view.canonicalLegacyIds.push(old);
   // Preserve the source-admitted objects. No quote, fuel, room or meal inference.
   tours.forEach(t=>{if(!view.tours.includes(t))view.tours.push(t);if(!view.canonicalOfferLinks.some(link=>link.tour===t&&link.legacyHotelId===h.id))view.canonicalOfferLinks.push({tour:t,legacyHotelId:h.id});});
   view.providers=Array.from(new Set(view.providers.concat(Array.isArray(h.providers)?h.providers:[h.provider||'tourvisor'])));
   if(h.andromedaExpansion&&(!view.andromedaExpansion||view.andromedaExpansion.status!=='loading'))view.andromedaExpansion=h.andromedaExpansion;
  });
  return Array.from(groups.values()).map(view=>{const prices=view.tours.map(t=>Number(t&&t.price)).filter(n=>Number.isFinite(n)&&n>0);view.price=prices.length?Math.min(...prices):0;return view;});
 }
 function pump(){
  const ids=Array.from(new Set(raw.map(legacyId).filter(Boolean))).filter(key=>!links.has(key)&&!missing.has(key)&&!failed.has(key)&&!pending.has(key));
  while(ids.length&&workers.size<2){
   const requested=ids.splice(0,100),generation=epoch,controller=new AbortController(),task={controller};workers.add(task);requested.forEach(key=>pending.add(key));
   const timer=setTimeout(()=>controller.abort(),15000),query=new URLSearchParams({catalog:'anytour'});requested.forEach(key=>query.append('legacyHotelIds[]',key));
   Promise.resolve().then(()=>{const fetcher=root.V2Runtime&&root.V2Runtime.fetch||root.fetch.bind(root);return fetcher(endpoint+'?'+query.toString(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});}).then(response=>{if(!response.ok)throw new Error('Catalogue HTTP '+response.status);return response.json();}).then(payload=>{
    if(generation!==epoch)return;if(controller.signal.aborted)throw new Error('Catalogue timeout');
    const checked=batch(payload,requested);
    checked.profiles.forEach((p,key)=>{const previous=profiles.get(key);if(previous&&p.revision===previous.revision&&JSON.stringify(p)!==JSON.stringify(previous))throw new Error('Conflicting profile revision');});
    checked.profiles.forEach((p,key)=>{const previous=profiles.get(key);if(!previous||p.revision>=previous.revision)profiles.set(key,p);});
    checked.links.forEach((own,old)=>links.set(old,own));checked.missing.forEach(key=>missing.add(key));
   }).catch(()=>{if(generation===epoch)requested.forEach(key=>failed.add(key));}).finally(()=>{
    clearTimeout(timer);if(generation!==epoch)return;workers.delete(task);requested.forEach(key=>pending.delete(key));refresh();
   });
  }
 }
 function status(results){
  if(!raw.length)return;
  const ids=Array.from(new Set(raw.map(legacyId).filter(Boolean))),loading=ids.some(key=>!links.has(key)&&!missing.has(key)&&!failed.has(key)),error=ids.some(key=>failed.has(key)),excluded=raw.some(h=>!legacyId(h)||missing.has(legacyId(h)));
  if(!loading&&!error&&!excluded)return;
  const node=document.createElement('div');node.className='canonical-catalog-status search-progress-error';node.setAttribute('role',error?'alert':'status');
  const text=document.createElement('span');text.textContent=error?'Не удалось загрузить описания некоторых отелей.':loading?'Загружаем описания и фотографии отелей…':'Часть предложений пока недоступна: карточки отелей ещё не подготовлены.';node.appendChild(text);
  if(error){const button=document.createElement('button');button.type='button';button.className='secondary canonical-profile-retry';button.textContent='Повторить загрузку';button.addEventListener('click',()=>{failed.clear();refresh();});node.appendChild(button);}
  results.prepend(node);
 }
 root.addEventListener('v2:search-started',reset);root.addEventListener('v2:search-reset',reset);
 return{read(list,opts){raw=list.slice();options=Object.assign({},opts);pump();return project();},source:()=>raw,options:()=>options,details:h=>profiles.get(id(h&&h.anytourHotelId))||null,status,reset};
}
root.Search3CanonicalProfilesV1=Object.freeze({create});
})(window);
