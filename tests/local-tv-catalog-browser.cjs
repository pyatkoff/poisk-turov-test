'use strict';
// Actual LOCAL SQL/HTTP snapshots, existing adapter and existing NEXT renderers.
// All supplier transport is fictional; no deployed endpoint or paid search is used.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os'),http=require('node:http');
const {execFileSync}=require('node:child_process'),{JSDOM}=require('jsdom');
const {fixture,trip,profile,tour}=require('./search3-visual-live-fixture.cjs');
const repo=path.resolve(__dirname,'..'),sourceRoot=path.join(repo,'v2');
const exported=process.env.LOCAL_TV_TEST_EXPORT||path.join(fs.mkdtempSync(path.join(os.tmpdir(),'local-tv-browser-')),'http.json');
if(!fs.existsSync(exported))process.stdout.write(execFileSync('php',[path.join(__dirname,'local-tv-catalog-test.php')],{
 env:{...process.env,LOCAL_TV_TEST_EXPORT:exported},encoding:'utf8'}));
const original=JSON.parse(fs.readFileSync(exported,'utf8')),updated=JSON.parse(fs.readFileSync(exported+'.updated','utf8'));
assert.deepEqual(original.links,[{oldLocalId:501,tourvisorHotelId:101}]);
assert.equal(original.items[0].images.length,126);assert(updated.items[0].revision>original.items[0].revision);
const base='/_preview/search3-next-candidate/',reader=base+'data/local-tv-catalog-read-v1.php';
async function lookupBeforeOffersAcceptance(configScript){
 // Each case starts before project()/publish()/search(): only the existing bounded
 // autocomplete and ID-only content readers may provide this suggestion.
 // The query is the existing fictional legacy profile name; the genuine SQL/HTTP
 // export has a different display name. This is not evidence of a live alias.
 const query=profile.name,canonicalPath='/_preview/search3-local-candidate/data/hotel-details-read-v1.php';
 const receipts=[];
 for(const mode of ['ready','immediate','coalesced','restore','disabled','failure','missing','foreign-link','foreign-country','abort','stopped']){
  const dom=new JSDOM('<body></body>',{url:'https://anytoour.ru'+base+'visual-search/',runScripts:'outside-only'}),w=dom.window;
  Object.assign(w,{structuredClone,TextEncoder,AbortController,Response});
  let transport=async()=>{throw new Error('Unexpected transport before lookup setup');};
  w.fetch=(...args)=>transport(...args);
  Object.defineProperty(w.document,'currentScript',{configurable:true,value:configScript});
  w.eval(fs.readFileSync(path.join(sourceRoot,'prototype-search/config.js'),'utf8'));
  Object.defineProperty(w.document,'currentScript',{configurable:true,value:null});
  for(const file of ['runtime-v3.js','search3-canonical-profiles-v1.js','prototype-search/data.js'])w.eval(fs.readFileSync(path.join(sourceRoot,file),'utf8'));
  const data=w.AnyTourPrototypeData,calls=[],callbacks=[],events=[],request=new AbortController();let release,entered;
  const contentEntered=new Promise(resolve=>entered=resolve),contentGate=new Promise(resolve=>release=resolve);
  data.catalog.countries.push({id:'104',name:'Турция',tourvisorIds:['4']});
  if(mode==='disabled')w.V2_CONFIG.localTvCatalogEnabled=false;
  transport=async(url,options={})=>{
   const u=new URL(url,w.location.href);calls.push(u.pathname);
   assert(!options.method||options.method==='GET','suggestions use read-only GETs');
   if(u.pathname==='/data/hotel-search-v1.php'){
    assert.equal(u.searchParams.get('countryId'),'4','own country resolves through its accepted TV bridge');
    assert.equal(u.searchParams.get('limit'),'10','existing autocomplete bound is preserved');
    assert.equal(u.searchParams.get('q'),query);
    return new Response(JSON.stringify({ok:true,items:[{id:101,name:query,country:{id:mode==='foreign-country'?100:4}}]}));
   }
   if(u.pathname===canonicalPath){
    assert.equal(u.searchParams.get('catalog'),'anytour');
    if(u.searchParams.has('anytourHotelId')){
     assert.equal(u.searchParams.get('anytourHotelId'),'501');
     return new Response(JSON.stringify({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',item:structuredClone(profile)}));
    }
    assert.deepEqual(u.searchParams.getAll('legacyHotelIds[]'),['101']);
    return new Response(JSON.stringify({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:['101'],
     items:[structuredClone(profile)],links:[{legacyHotelId:'101',anytourHotelId:501}],missingLegacyIds:[]}));
   }
   if(u.pathname===reader){
    assert.deepEqual(u.searchParams.getAll('oldLocalHotelIds[]'),['501'],'new LOCAL content uses the canonical own ID, never a TV-shaped guess');
    assert.equal(options.cache,'no-store');entered();
    if(mode!=='immediate')await contentGate;
    let payload=structuredClone(original);
    if(mode==='missing')payload={...payload,items:[],links:[],missingIds:[501]};
    if(mode==='foreign-link')payload.links[0].oldLocalId=502;
    return new Response(JSON.stringify(mode==='failure'?{ok:false}:payload),{status:mode==='failure'?503:200});
   }
   throw new Error('Unexpected supplier/other request during lookup: '+url);
  };
  try{
   assert.equal(data.searchId,0);
   const priorRead=mode==='coalesced'?data.refreshHotelContent([501]):null;
   const pending=(mode==='restore'?data.restoreHotel(501,'104').then(row=>[row]):data.lookupHotels(query,'104',request.signal,rows=>{events.push('hydrated');callbacks.push(rows);})).then(rows=>{events.push('initial');return rows;});
   let readinessTimer;
   const rows=await Promise.race([pending,new Promise((_,reject)=>{readinessTimer=setTimeout(()=>reject(new Error('Canonical suggestion waited for the unreleased optional content reader')),1000);})]).finally(()=>clearTimeout(readinessTimer));
   if(mode==='foreign-country'){assert.equal(rows.length,0);assert.deepEqual(calls,['/data/hotel-search-v1.php']);}
   else{
    assert.equal(rows.length,1);const baseline=rows[0];assert.equal(baseline.id,501);assert.equal(baseline.country,'104');assert.deepEqual(Array.from(baseline.legacyIds),['101']);
    assert.equal(baseline.offers.length,0,'autocomplete never fabricates offers');
    assert.equal(baseline.name,profile.name);assert.equal(baseline.raw.localHotelId,undefined,'canonical row is available while optional hydration remains held');
    assert.equal(baseline.photos.length,profile.images.length);if(mode!=='immediate')assert.equal(callbacks.length,0);
    if(mode!=='disabled')await contentEntered;
    const readCompletion=!['disabled','immediate'].includes(mode)?data.refreshHotelContent([501]):null;
    if(mode==='abort')request.abort();else if(mode==='stopped')data.stop();
    release();if(readCompletion)await readCompletion;if(priorRead)await priorRead;for(let turn=0;turn<3;turn++)await new Promise(resolve=>setImmediate(resolve));
    if(['ready','immediate','coalesced'].includes(mode)){
     assert.equal(callbacks.length,1);assert.equal(callbacks[0].length,1);const shown=callbacks[0][0];
     assert.deepEqual(events,['initial','hydrated'],'optional completion cannot be overwritten by the original rows');
     if(mode==='coalesced')assert.equal(calls.filter(path=>path===reader).length,1,'lookup shares the existing same-ID content read');
     assert.equal(shown.id,baseline.id);assert.equal(shown.country,baseline.country);assert.deepEqual(Array.from(shown.legacyIds),Array.from(baseline.legacyIds));
     assert.equal(shown.raw.localHotelId,101);assert.equal(shown.name,original.items[0].name);assert.equal(shown.note,original.items[0].description);
     assert.equal(shown.photos.length,126);assert.equal(shown.resort,profile.region.name);assert.equal(shown.stars,profile.category);
     assert.notEqual(shown.name,query,'accepted query survives a different saved display name');
     assert.equal(baseline.name,profile.name,'progressive hydration does not mutate previously delivered rows');
     transport=async(url,options={})=>{
      const u=new URL(url,w.location.href);assert.equal(u.pathname,reader);assert.equal(options.cache,'no-store');
      return new Response(JSON.stringify(updated));
     };
     assert.equal(await data.refreshHotelContent([501]),true);assert.equal(data.hotelPresentation(shown.raw,{country:'104'}).note,updated.items[0].description);
    }else assert.equal(callbacks.length,0,'three-argument restore and disabled/missing/failed/foreign/aborted/stopped content do not invoke an absent/current callback');
    if(mode==='stopped'){
     const unhydrated=data.hotelPresentation({...structuredClone(profile),canonicalLegacyIds:['101']},{country:'104'});
     assert.equal(unhydrated.raw.localHotelId,undefined,'stopped-generation content cannot enter the suggestion cache');
    }
    const raw={...structuredClone(profile),anytourHotelId:501,canonicalLegacyIds:['101'],tours:[structuredClone(tour)]},rawBefore=JSON.stringify(raw);
    const offers=data.project([raw],{...trip,country:'104'})[0].offers;
    assert.equal(offers.length,1);assert.equal(offers[0].hotelId,501);assert.equal(offers[0].raw.id,tour.id);assert.equal(offers[0].total,tour.price);
    assert.equal(JSON.stringify(raw),rawBefore,'lookup hydration leaves exact offer input unchanged');
    if(mode==='disabled')assert(!calls.includes(reader),'disabled overlay does not read new LOCAL content');
   }
   assert.equal(data.searchId,0,'query never starts suppliers');
   receipts.push({mode,reads:calls.length,callbacks:callbacks.length,canonicalReadyBeforeOptionalContent:true,supplierHTTP:0});
  }finally{release();data.stop();dom.window.close();}
 }
 console.log('PASS LOCAL lookup before offers',JSON.stringify(receipts));
}
async function adapterAcceptance(){
 for(const pathname of ['/','/prototype-search/','/_preview/search3-site-candidate/visual-search/','/_preview/search3-local-candidate/visual-search/','/_preview/search3-next-candidate/visual-search/']){
  const configDom=new JSDOM('<body></body>',{url:'https://anytoour.ru'+pathname,runScripts:'outside-only'});
  try{configDom.window.eval(fs.readFileSync(path.join(sourceRoot,'prototype-search/config.js'),'utf8'));
   assert.equal(configDom.window.V2_CONFIG.localTvCatalogEnabled,false,'static/Site config stays off without the live server bootstrap');
   assert.equal(configDom.window.V2_CONFIG.andromedaApi,null);assert.equal(configDom.window.V2_CONFIG.andromedaQuoteApi,null);
  }finally{configDom.window.close();}
 }
 const dom=new JSDOM('<body></body>',{url:'https://anytoour.ru'+base+'visual-search/',runScripts:'outside-only'}),w=dom.window;
 Object.assign(w,{structuredClone,TextEncoder,AbortController,Response});
 w.fetch=async()=>{throw new Error('Unexpected supplier transport');};
 for(const [script,scenario] of [['/_preview/search3-next-candidate/visual-search/index.php','snapshot'],['/visual-search/index.php',''],['/_preview/search3-local-candidate/visual-search/index.php','']]){
  const html=execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]=$argv[2];$_GET["scenario"]=$argv[3];include $argv[1];',path.join(sourceRoot,'visual-search/index.php'),script,scenario],{encoding:'utf8'});
  assert(!html.includes('data-local-tv-catalog="enabled"'),'offline/forbidden PHP entry cannot enable LOCAL content');
 }
 const served=execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php";include $argv[1];',path.join(sourceRoot,'visual-search/index.php')],{encoding:'utf8'});
 const entry=new JSDOM(served),configScript=entry.window.document.querySelector('script[src*="prototype-search/config.js"]');assert.equal(configScript?.dataset.localTvCatalog,'enabled','actual live PHP entry opts in');
 Object.defineProperty(w.document,'currentScript',{configurable:true,value:configScript});
 w.eval(fs.readFileSync(path.join(sourceRoot,'prototype-search/config.js'),'utf8'));
 Object.defineProperty(w.document,'currentScript',{configurable:true,value:null});await lookupBeforeOffersAcceptance(configScript);entry.window.close();
 for(const file of ['runtime-v3.js','search3-canonical-profiles-v1.js','prototype-search/data.js'])w.eval(fs.readFileSync(path.join(sourceRoot,file),'utf8'));
 const data=w.AnyTourPrototypeData,raw={...structuredClone(profile),anytourHotelId:501,tours:[structuredClone(tour)]};
 const before=data.project([raw],trip)[0],rawBefore=JSON.stringify(raw),offerBefore=JSON.stringify(before.offers),calls=[];
 let reply=original,release;
 w.fetch=async(url,options)=>{const u=new URL(url,w.location.href);assert.equal(u.pathname,reader);assert.deepEqual(u.searchParams.getAll('oldLocalHotelIds[]'),['501']);assert.equal(options.cache,'no-store');calls.push(url);if(release)await release.promise;return new Response(JSON.stringify(reply),{status:200});};
 try{
  const rollout=w.V2_CONFIG.localTvCatalogEnabled;assert.equal(rollout,true,'actual NEXT config enables existing content reader');w.V2_CONFIG.localTvCatalogEnabled=false;
  assert.equal(await data.refreshHotelContent([501]),false);assert.equal(calls.length,0,'disabled rollout has no new reader calls');
  w.V2_CONFIG.localTvCatalogEnabled=rollout;
  let resolve;release={promise:new Promise(r=>resolve=r)};
  const first=data.refreshHotelContent([501]),second=data.refreshHotelContent([501]);assert.equal(calls.length,1,'concurrent same request is shared');resolve();await Promise.all([first,second]);release=null;
  const shown=data.project([raw],trip)[0];assert.equal(shown.id,501);assert.equal(shown.raw.localHotelId,101);assert.equal(shown.note,original.items[0].description);assert.equal(shown.photos.length,126);
  assert.equal(JSON.stringify(shown.offers),offerBefore,'all offer identity, fuel, price, flight and search values unchanged');assert.equal(JSON.stringify(raw),rawBefore,'input supplier/canonical objects unchanged');
  reply=updated;assert.equal(await data.refreshHotelContent([501]),true);assert.equal(data.project([raw],trip)[0].note,updated.items[0].description,'new saved revision visible without supplier search');
  reply=structuredClone(updated);reply.links[0].oldLocalId=502;assert.equal(await data.refreshHotelContent([501]),false,'foreign bridge rejected as a whole');
  reply=structuredClone(updated);reply.items[0].description='Same revision, conflicting content';assert.equal(await data.refreshHotelContent([501]),false,'conflicting revision rejected');
  reply=original;assert.equal(await data.refreshHotelContent([501]),false,'older revision cannot replace current content');
  reply=structuredClone(updated);reply.items[0].revision++;reply.items[0].description='';reply.items[0].manualFields=['description'];assert.equal(await data.refreshHotelContent([501]),true);assert.equal(data.project([raw],trip)[0].note,'','intentional manual empty value is authoritative');
  const emptyRevision=reply.items[0].revision;
  reply=structuredClone(updated);reply.items[0].revision=emptyRevision+1;reply.items[0].description='Late previous search';release={promise:new Promise(r=>resolve=r)};
  const stale=data.refreshHotelContent([501]);data.stop();resolve();assert.equal(await stale,false,'response from stopped search cannot update current cache');release=null;
  assert.equal(data.project([raw],trip)[0].note,'');assert.equal(JSON.stringify(data.project([raw],trip)[0].offers),offerBefore);
  console.log('PASS LOCAL adapter: exact old501→TV101 bridge,126photos, shared no-store read, revisions/manual clear/stale-generation guards, unchanged offers; supplier HTTP0');
 }finally{data.stop();dom.window.close();}
}
async function browserAcceptance(){
 const {chromium}=require('playwright'),assetRoot=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||sourceRoot);
 const evidence=path.resolve('visual-live-evidence/local-tv-catalog');fs.mkdirSync(evidence,{recursive:true});
 const server=http.createServer((req,res)=>{
  const u=new URL(req.url,'http://fixture');if(!u.pathname.startsWith(base)){res.writeHead(403).end();return;}
  const local=path.resolve(assetRoot,u.pathname.slice(base.length)||'index.php');if(!local.startsWith(assetRoot+'/')){res.writeHead(403).end();return;}
  const file=fs.existsSync(local)&&fs.statSync(local).isDirectory()?path.join(local,'index.php'):local;if(!fs.existsSync(file)){res.writeHead(404).end();return;}
  if(file.endsWith('.php')){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php";include $argv[1];',file]));return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':'application/octet-stream');
  const content=fs.readFileSync(file);res.end(content);
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port;
 let browser;const receipts=[];
 try{
  browser=await chromium.launch({headless:true});
  for(const width of [360,390,430,768,1280]){
   const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),transport=fixture(),errors=[],forbidden=[];
   transport.state.hotelCatalogue=[{id:profile.id,legacyId:101,name:profile.name,country:'4',countryName:profile.country.name,images:profile.images}];
   let current=original,contentReads=0,failed=false,gate=null,release;
   page.on('pageerror',e=>errors.push(e.message));
   await page.route('**/*',async route=>{
    const req=route.request(),u=new URL(req.url());
    if(u.pathname===reader){contentReads++;assert.deepEqual(u.searchParams.getAll('oldLocalHotelIds[]'),['501']);if(gate)await gate;
     await route.fulfill({status:failed?503:200,contentType:'application/json',headers:{'Cache-Control':'no-store'},body:JSON.stringify(failed?{ok:false}:current)});return;}
    if(req.resourceType()==='image'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect width="700" height="500" fill="#bacad5"/></svg>'});return;}
    if(u.origin===origin&&u.pathname.startsWith(base)&&!u.pathname.includes('/data/')){await route.continue();return;}
    try{const value=await transport.json(req.url(),{body:req.postData()});await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});}
    catch(e){forbidden.push(e.message);await route.abort();}
   });
   const close=async()=>{await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);};
   try{
    await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));await page.locator('.search-submit:not(:disabled)').waitFor();
    const starts=()=>transport.calls.filter(c=>c.action==='search_start').length;
    const originalDirection=await page.locator('#destination-detail').textContent();
    let progressiveState=null;
    const openLookup=async(holdContent=false)=>{
     if(holdContent)gate=new Promise(resolve=>release=resolve);
     await page.locator('[data-action="destination"]').first().click();await page.locator('#destination-query').fill(profile.name);
     if(holdContent){
      await page.waitForFunction(name=>document.querySelector('.destination-hotel[data-id="501"] strong')?.textContent===name,profile.name);
      assert.equal(await page.locator('#destination-query').getAttribute('aria-busy'),'false','canonical results are usable while optional content is held');
      assert.equal(starts(),0);await page.locator('.destination-hotel[data-id="501"]').click();
      if(width<=390)await page.locator('#destination-query').evaluate(el=>{el.focus({preventScroll:true});el.setSelectionRange(2,7);});
      else await page.locator('.destination-hotel[data-id="501"]').focus();
      const held=await page.evaluate(()=>{
       const body=document.querySelector('#modal-body'),field=document.querySelector('#destination-query');body.scrollTop=40;
       return {focus:document.activeElement.id,caret:[field.selectionStart,field.selectionEnd],scroll:body.scrollTop,selected:document.querySelector('.destination-hotel[data-id="501"]').getAttribute('aria-pressed')};
      });
      assert.equal(held.selected,'true');for(let i=0;i<50&&contentReads===0;i++)await new Promise(resolve=>setTimeout(resolve,20));assert(contentReads>0);
      release();gate=null;
      await page.waitForFunction(name=>document.querySelector('.destination-hotel[data-id="501"] strong')?.textContent===name,original.items[0].name);
      const hydrated=await page.evaluate(()=>{const field=document.querySelector('#destination-query');return {focus:document.activeElement.id,caret:[field.selectionStart,field.selectionEnd],scroll:document.querySelector('#modal-body').scrollTop,selected:document.querySelector('.destination-hotel[data-id="501"]').getAttribute('aria-pressed')};});
      assert.deepEqual(hydrated,held,'progressive saved content preserves current focus/caret/inner scroll and exact selection');
      progressiveState={canonicalReadyWhileHeld:true,selectedId:501,focus:held.focus,caret:held.caret,scroll:held.scroll,preserved:true};
     }
     await page.waitForFunction(name=>document.querySelector('.destination-hotel[data-id="501"] strong')?.textContent===name,original.items[0].name);
     const row=page.locator('.destination-hotel[data-id="501"]');
     const geography=row.locator('.destination-hotel-copy>small');
     assert((await geography.textContent()).includes(profile.region.name),'existing canonical geography remains visible');
     assert((await geography.textContent()).includes(profile.country.name));
     const stars=Number(profile.category),starOwner=row.locator('.destination-hotel-copy>.hotel-stars');
     assert.equal(await starOwner.textContent(),'★'.repeat(stars),'separate star owner preserves the exact canonical category');
     assert.equal(await starOwner.getAttribute('aria-label'),stars===1?'1 звезда':stars<5?stars+' звезды':stars+' звёзд','canonical category remains accessible');
     assert.equal(await row.locator('img').getAttribute('src'),original.items[0].primaryImage||original.items[0].images[0],'saved LOCAL thumbnail reaches the existing renderer');
     assert.equal(await page.locator('#destination-query').inputValue(),profile.name,'hydration preserves the accepted legacy query');
     assert.equal(starts(),0,'opening/typing/reading LOCAL content starts no tour search');
     assert(!transport.calls.some(c=>['search','quote_start','quote_calculate','quote','quote_select_flights'].includes(c.action)),'picker performs no supplier search/quote fixture action');
     return row;
    };
    let suggestion=await openLookup(true);
    const pickerGeometry=await page.locator('#modal-body').evaluate(el=>({modalOverflow:el.scrollWidth>el.clientWidth+1,documentOverflow:document.documentElement.scrollWidth>innerWidth+1}));
    assert.deepEqual(pickerGeometry,{modalOverflow:false,documentOverflow:false});
    await page.screenshot({path:path.join(evidence,'destination-before-offers-'+width+'.png')});
    assert.equal(await page.locator('.destination-hotel[data-id="501"]').getAttribute('aria-pressed'),'true');
    await close();assert.equal(await page.locator('#destination-detail').textContent(),originalDirection,'Cancel restores the original form selection');
    gate=new Promise(resolve=>release=resolve);const beforeCancelledLookup=contentReads;
    await page.locator('[data-action="destination"]').first().click();await page.locator('#destination-query').fill(profile.name);
    await page.waitForFunction(name=>document.querySelector('.destination-hotel[data-id="501"] strong')?.textContent===name,original.items[0].name);
    for(let i=0;i<50&&contentReads===beforeCancelledLookup;i++)await new Promise(resolve=>setTimeout(resolve,20));assert(contentReads>beforeCancelledLookup);
    await close();release();gate=null;await page.waitForTimeout(100);
    assert.equal(await page.locator('#modal').evaluate(el=>el.open),false,'late optional hydration cannot reopen the cancelled picker');
    assert.equal(await page.locator('#destination-detail').textContent(),originalDirection,'late optional hydration cannot apply a cancelled selection');
    suggestion=await openLookup();await suggestion.click();await page.locator('[data-action="apply-destination"]').click();
    await page.waitForFunction(()=>!document.querySelector('#modal').open);
    assert((await page.locator('#destination-detail').textContent()).includes(original.items[0].name),'Apply retains the exact hydrated hotel in the form');
    await page.locator('[data-action="destination"]').first().click();
    assert.equal(await page.locator('[data-action="destination-remove"][data-id="501"]').count(),1,'reopen keeps the exact own ID');
    await page.locator('#destination-query').fill('no-such-local-fixture');
    await page.waitForFunction(()=>document.querySelector('#destination-query')?.getAttribute('aria-busy')==='false');
    assert.equal(await page.locator('[data-action="destination-remove"][data-id="501"]').count(),1,'query change does not change selection');
    await page.locator('[data-action="clear-destination-query"]').first().click();assert.equal(await page.locator('#destination-query').inputValue(),'');
    await page.locator('[data-action="destination-remove"][data-id="501"]').click();await close();
    assert((await page.locator('#destination-detail').textContent()).includes(original.items[0].name),'Cancel restores applied selection after clear/removal');
    assert.equal(starts(),0);await page.locator('.search-submit').click();
    await page.waitForFunction(()=>document.querySelector('.hotel-card')?.textContent.includes('Synthetic LOCAL fixture 101'));
    await page.waitForFunction(()=>{const status=document.querySelector('#search-status');return document.querySelector('#search-form').hidden&&(status.hidden||!status.querySelector('[data-action="stop-search"]'));});
    const card=page.locator('.hotel-card').first(),price=await card.locator('.starting-price strong').textContent(),url=page.url();
    const searches=starts();
    const started=transport.calls.find(c=>c.action==='search_start');assert(started,'explicit submit remains the first tour search');
    assert.equal(started.query['hotelIds[]'],'101','submitted filter uses the verified legacy ID for the selected own501');
    assert.match(await card.locator('.photo-count').textContent(),/126/);
    await card.locator('[data-action="hotel-details"][data-target="hotel-about-heading"]').click();await page.locator('#hotel-room-count').waitFor();assert((await page.locator('#modal-body').textContent()).includes(original.items[0].description));
    assert((await page.locator('#modal-body').textContent()).includes('Два тестовых бассейна'));assert((await page.locator('#modal-body').textContent()).includes('Wi-Fi в общественных местах'));
    const choices=await page.locator('.room-offer-choice').evaluateAll(rows=>rows.map(el=>({key:el.dataset.offerKey,price:el.querySelector('.hotel-room-price strong')?.textContent})));assert(choices.length>0);
    await close();current=updated;await card.locator('[data-action="hotel-details"][data-target="hotel-about-heading"]').click();await page.locator('#hotel-room-count').waitFor();
    assert((await page.locator('#modal-body').textContent()).includes(updated.items[0].description));
    assert((await card.textContent()).includes(updated.items[0].name));assert.match(await card.locator('.photo-count').textContent(),/126/);
    assert.equal(await card.locator('.starting-price strong').textContent(),price);assert.deepEqual(await page.locator('.room-offer-choice').evaluateAll(rows=>rows.map(el=>({key:el.dataset.offerKey,price:el.querySelector('.hotel-room-price strong')?.textContent}))),choices);
    const geometry=await page.locator('#modal-body').evaluate(el=>({modalOverflow:el.scrollWidth>el.clientWidth+1,documentOverflow:document.documentElement.scrollWidth>innerWidth+1}));assert.deepEqual(geometry,{modalOverflow:false,documentOverflow:false});
    await page.screenshot({path:path.join(evidence,'content-'+width+'.png')});await close();failed=true;
    await card.locator('[data-action="hotel-details"][data-target="hotel-about-heading"]').click();await page.locator('#hotel-room-count').waitFor();assert((await page.locator('#modal-body').textContent()).includes(updated.items[0].description),'reader failure preserves latest saved content');await close();failed=false;
    gate=new Promise(resolve=>release=resolve);const before=contentReads;await card.locator('[data-action="hotel-details"][data-target="hotel-about-heading"]').click();await page.waitForFunction(()=>document.querySelector('#hotel-details-load')?.textContent.includes('Загружаем'));
    for(let i=0;i<50&&contentReads===before;i++)await new Promise(r=>setTimeout(r,20));assert(contentReads>before);await close();release();gate=null;
    await page.waitForTimeout(100);assert.equal(await page.locator('#modal').evaluate(el=>el.open),false,'late content cannot reopen a closed dialog');
    assert.equal(starts(),searches,'content reopening adds no tour search');assert.equal(page.url(),url);assert(!transport.calls.some(c=>/lead|payment/.test(c.url)));assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
    receipts.push({width,oldLocalId:501,localTvId:101,photos:126,contentReads,lookupBeforeOffers:true,progressiveState,queryPreserved:true,applyCancelExactId:true,pickerStarts:0,pickerGeometry,newRevisionVisible:true,offerChoicesUnchanged:true,geometry,supplierHTTP:0,realLeads:0,deployedNEXT:false});
   }finally{release?.();await context.close();}
  }
 }finally{await browser?.close();await new Promise(resolve=>server.close(resolve));}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify(receipts,null,2)+'\n');console.log('PASS LOCAL compiled NEXT browser',JSON.stringify(receipts));
}
(async()=>{await adapterAcceptance();if(process.argv.includes('--browser'))await browserAcceptance();})().catch(e=>{console.error(e);process.exitCode=1;});
