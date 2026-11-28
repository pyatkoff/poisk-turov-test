'use strict';
// Resolves the public URL before the app applies any part of its conditions.
(function(root){
 const cfg=root.V2_CONFIG||{},selected=!!root.AnyTourPublicConfigV1;
 const script=root.document?.currentScript,handoffAssets=script?.dataset?.search3HandoffPrefetch;
 if(handoffAssets&&root.AnyTourPublicConfigV1?.valid===true&&cfg.search3FormOnly===true
     &&root.navigator?.connection?.saveData!==true){
  const form=root.document.getElementById('search-form'),button=form?.querySelector('.search-submit');
  if(button){
   const pending=new Set(handoffAssets.split(' '));
   const prefetch=href=>{
    if(!pending.delete(href))return;
    const hint=root.document.createElement('link');
    hint.rel='prefetch';hint.as='script';hint.fetchPriority='low';hint.href=href;
    root.document.head.append(hint);
   };
   const filter=[...pending].find(href=>/\/filter-panel-v1\.js\?v=[0-9a-f]{12}$/.test(href));
   const app=script.nextElementSibling;
   // The filter panel blocks results. Start its hint once app.js has loaded.
   if(filter&&app?.tagName==='SCRIPT'&&/\/app\.js\?v=[0-9a-f]{12}$/.test(app.getAttribute('src')||'')){
    app.addEventListener('load',()=>{if(form.isConnected)prefetch(filter);},{once:true});
   }
   let scheduled=false;
   const ready=()=>!button.disabled&&form.getAttribute('aria-busy')!=='true';
   const observer=new root.MutationObserver(()=>{
    if(scheduled||!ready())return;
    scheduled=true;
    root.requestAnimationFrame(()=>root.requestAnimationFrame(()=>{
     scheduled=false;
     if(!ready()||!form.isConnected)return;
     observer.disconnect();
     for(const href of pending)prefetch(href);
    }));
   });
   observer.observe(form,{subtree:true,attributes:true,attributeFilter:['disabled','aria-busy']});
  }
 }
 function reject(reason,query){
  const error=new Error('Не удалось перенести все условия этой ссылки. Исходная ссылка сохранена.');
  error.publicSearchURL=true;error.reason=String(reason||'unavailable');
  const base=typeof cfg.search3PublicBase==='string'?cfg.search3PublicBase:'/poisk-turov/';
  error.fallback=base.replace(/poisk-turov\/$/,'poisk-turov-old/')+(query?'?'+new URLSearchParams(query).toString():'')+String(root.location?.hash||'');
  return error;
 }
 async function prepare(data,{query='',origin='Москва',startDay,endDay}={}){
  if(!selected||root.AnyTourPublicConfigV1.valid!==true)throw reject('public_config',query);
  const mapper=root.AnyTourPublicSearchURLV1;
  if(!mapper||typeof mapper.inspect!=='function')throw reject('public_url_owner',query);
  const input=new URLSearchParams(query),inspection=mapper.inspect(input);
  if(!inspection.ok)throw reject(inspection.code,query);
  const legacy=inspection.namespace==='legacy',requestedOrigin=legacy?origin:input.get('origin')||origin;
  if(legacy){const preflight=mapper.fromLegacy(input,null);if(!preflight.ok&&preflight.code!=='catalog_required')throw reject(preflight.code,query);}
  const departureId=legacy&&input.has('from')?input.get('from'):null;
  if(departureId!==null&&(!/^[1-9]\d*$/.test(departureId)||!Number.isSafeInteger(Number(departureId))))throw reject('departure_unmapped',query);
  // The data owner resolves a native departure before it loads that departure's countries.
  let catalog;
  try{catalog=await data.init(requestedOrigin,departureId===null?{}:{departureId});}
  catch(error){if(error?.code==='PUBLIC_DEPARTURE_UNMAPPED')throw reject('departure_unmapped',query);throw error;}
  if(!catalog)return null;
  if(!Array.isArray(catalog.departures)||!Array.isArray(catalog.countries))throw reject('catalog_required',query);
  if(!legacy&&input.has('origin')&&catalog.origin!==input.get('origin'))throw reject('departure_unmapped',query);
  let converted=null;
  if(legacy){
   converted=mapper.fromLegacy(input,{...catalog,text:data.text,startDay,endDay});
   if(!converted.ok)throw reject(converted.code,query);
  }else if(input.has('country')&&!catalog.countries.some(row=>String(row.id)===input.get('country'))){
   throw reject('country_unmapped',query);
  }
  const output=new URLSearchParams(converted?converted.query:input);
  // A converted country-only link must not be read as a native country again on reload.
  if(converted){output.set('origin',catalog.origin);output.set('scenario',cfg.search3PublicEntry==='live'?'live':cfg.search3ReviewScenario);}
  if(cfg.search3PublicEntry==='review')output.set('scenario',cfg.search3ReviewScenario);
  if(converted?.autoSearch)output.set('searched','1');
  if(cfg.search3FormOnly===true)output.delete('searched');
  return Object.freeze({catalog,query:output.toString(),rewrite:legacy||cfg.search3PublicEntry==='review'||cfg.search3FormOnly===true&&input.has('searched'),
   autoSearch:cfg.search3FormOnly!==true&&(converted?converted.autoSearch:output.get('searched')==='1'),namespace:inspection.namespace});
 }
 root.AnyTourPublicBootstrapV1=Object.freeze({selected,prepare});
})(window);
