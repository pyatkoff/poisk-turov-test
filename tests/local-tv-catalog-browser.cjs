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
async function adapterAcceptance(){
 const dom=new JSDOM('<body></body>',{url:'https://anytoour.ru'+base+'visual-search/',runScripts:'outside-only'}),w=dom.window;
 Object.assign(w,{structuredClone,TextEncoder,AbortController,Response});
 w.fetch=async()=>{throw new Error('Unexpected supplier transport');};
 for(const file of ['prototype-search/config.js','runtime-v3.js','search3-canonical-profiles-v1.js','prototype-search/data.js'])w.eval(fs.readFileSync(path.join(sourceRoot,file),'utf8'));
 const data=w.AnyTourPrototypeData,raw={...structuredClone(profile),anytourHotelId:501,tours:[structuredClone(tour)]};
 const before=data.project([raw],trip)[0],rawBefore=JSON.stringify(raw),offerBefore=JSON.stringify(before.offers),calls=[];
 let reply=original,release;
 w.fetch=async(url,options)=>{const u=new URL(url,w.location.href);assert.equal(u.pathname,reader);assert.deepEqual(u.searchParams.getAll('oldLocalHotelIds[]'),['501']);assert.equal(options.cache,'no-store');calls.push(url);if(release)await release.promise;return new Response(JSON.stringify(reply),{status:200});};
 try{
  assert.equal(await data.refreshHotelContent([501]),false);assert.equal(calls.length,0,'disabled rollout has no new reader calls');
  w.V2_CONFIG.localTvCatalogEnabled=true;
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
  const content=fs.readFileSync(file);res.end(file.endsWith('prototype-search/config.js')?content.toString()+'\nwindow.V2_CONFIG.localTvCatalogEnabled=true;':content);
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port;
 let browser;const receipts=[];
 try{
  browser=await chromium.launch({headless:true});
  for(const width of [360,390,430,768,1280]){
   const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),transport=fixture(),errors=[],forbidden=[];
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
    await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));await page.locator('.search-submit:not(:disabled)').waitFor();await page.locator('.search-submit').click();
    await page.waitForFunction(()=>document.querySelector('.hotel-card')?.textContent.includes('Synthetic LOCAL fixture 101'));
    const card=page.locator('.hotel-card').first(),price=await card.locator('.starting-price strong').textContent(),url=page.url();
    const starts=()=>transport.calls.filter(c=>c.action==='search_start').length,searches=starts();
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
    receipts.push({width,oldLocalId:501,localTvId:101,photos:126,contentReads,newRevisionVisible:true,offerChoicesUnchanged:true,geometry,supplierHTTP:0,realLeads:0,deployedNEXT:false});
   }finally{release?.();await context.close();}
  }
 }finally{await browser?.close();await new Promise(resolve=>server.close(resolve));}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify(receipts,null,2)+'\n');console.log('PASS LOCAL compiled NEXT browser',JSON.stringify(receipts));
}
(async()=>{await adapterAcceptance();if(process.argv.includes('--browser'))await browserAcceptance();})().catch(e=>{console.error(e);process.exitCode=1;});
