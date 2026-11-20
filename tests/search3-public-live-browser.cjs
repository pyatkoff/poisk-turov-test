'use strict';
// Actual public PHP entry plus the mixed-main compiled LIVE artifact. Every data,
// supplier and handoff request is fulfilled locally; no installed runtime is used.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {execFileSync}=require('node:child_process'),{createHash}=require('node:crypto'),{chromium}=require('playwright');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
const {privateTVFixture}=require('./search3-visual-messenger-browser.cjs');
assert(process.env.SEARCH3_PUBLIC_LIVE_ROOT,'an explicit compiled LIVE artifact is required');
const artifact=path.resolve(process.env.SEARCH3_PUBLIC_LIVE_ROOT),site=path.join(artifact,'site');
const manifestBytes=fs.readFileSync(path.join(artifact,'manifest.json')),manifest=JSON.parse(manifestBytes);
const sha=bytes=>createHash('sha256').update(bytes).digest('hex');
assert.equal(manifest.purpose,'local_public_live_install_candidate');
assert.equal(manifest.deployable,false);assert.equal(manifest.profile.profile,'live');
assert.equal(manifest.network_used,false);assert.equal(manifest.published,false);
assert.deepEqual(manifest.graph.initial,manifest.live_graph);
const files=new Map(Object.values(manifest.files).flat().map(row=>['/'+row.path,row.served]));
for(const [file,metadata] of files){
 const target=path.join(site,file),bytes=fs.readFileSync(target);
 assert.equal(sha(bytes),metadata.sha256,'artifact bytes changed: '+file);
 assert.equal(bytes.length,metadata.size);
 assert.equal((fs.statSync(target).mode&0o777).toString(8).padStart(4,'0'),metadata.mode);
}
const resourcePath=ref=>new URL(ref,'http://artifact.invalid/visual-search/index.html').pathname;
const initial=manifest.graph.initial.map(resourcePath),lazy=Object.values(manifest.graph.on_demand).map(resourcePath);
const staticPaths=new Set([...initial,...lazy,...manifest.graph.styles.map(resourcePath),
 ...[...files.keys()].filter(file=>file.startsWith('/visual-search/assets/'))]);
assert.equal(initial.length,22);assert.equal(lazy.length,3);
for(const file of staticPaths){
 const metadata=files.get(file);assert(metadata,'resource must be inventoried: '+file);
 const target=path.join(site,file),bytes=fs.readFileSync(target);
 assert.equal(sha(bytes),metadata.sha256,file+' uses final served bytes');
 assert.equal(bytes.length,metadata.size);
 assert.equal((fs.statSync(target).mode&0o777).toString(8).padStart(4,'0'),metadata.mode);
}
for(const name of ['runtime-v3.js','analytics-v4.js','lead-search-context.js']){
 assert(manifest.files.protected.some(row=>row.path===name),'main owns '+name);
 assert(!fs.existsSync(path.join(artifact,'overlay',name)),'overlay excludes '+name);
 assert.deepEqual(files.get('/'+name),manifest.server_dependencies.retained_main[name]);
}
// A future baseline containing executable local configuration/DB bootstrap needs
// a separately reviewed fixture, never silent execution by this browser harness.
for(const name of ['config.php','site_conf.php','data/hotel-popularity-v1.php']){
 assert(!fs.existsSync(path.join(site,name)),'unexpected PHP dependency in offline browser fixture: '+name);
}
assert(!fs.existsSync(path.join(artifact,'overlay/data')),'no PHP/DB expansion');
assert(!fs.existsSync(path.join(site,'visual-search/fixture-data.js')),'no family adapter in LIVE');
const output=path.resolve('visual-live-evidence/public-live');fs.mkdirSync(output,{recursive:true});
const failures=[],served=new Set(),publicRoutes=new Set(['/poisk-turov/','/poisk-turov/index.php']);
function render(route,query=''){
 const php='$_SERVER["SCRIPT_NAME"]=$argv[2];$_SERVER["HTTP_HOST"]="anytoour.ru";$_SERVER["DOCUMENT_ROOT"]=$argv[3];$_SERVER["REQUEST_URI"]=$argv[2].($argv[4]!==""?"?".$argv[4]:"");parse_str($argv[4],$_GET);ob_start();register_shutdown_function(function(){echo json_encode(["status"=>http_response_code()?:200,"body"=>ob_get_clean()],JSON_THROW_ON_ERROR);});require $argv[1];';
 return JSON.parse(execFileSync('php',['-n','-d','allow_url_fopen=0','-d','allow_url_include=0','-d','display_errors=stderr',
  '-r',php,path.join(site,'poisk-turov/index.php'),route,site,query],{encoding:'utf8',timeout:10000}));
}
const html=render('/poisk-turov/','profile=review&scenario=family');
assert.equal(html.status,200);assert(!html.body.includes('fixture-data.js'));
fs.writeFileSync(path.join(output,'entry-root.html'),html.body);
assert.equal(render('/_preview/search3-site-candidate/poisk-turov/').status,403,'LIVE cannot replace the isolated site review');
const server=http.createServer((req,res)=>{
 try{
  const url=new URL(req.url,'http://fixture');
  assert.equal(req.method,'GET','the local server accepts only static/entry GETs');
  if(publicRoutes.has(url.pathname)){
   const result=render(url.pathname,url.search.slice(1));assert.equal(result.status,200);
   res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'}).end(result.body);return;
  }
  assert(staticPaths.has(url.pathname),'only exact graph/static manifest paths may reach the server: '+url.pathname);
  const bytes=fs.readFileSync(path.join(site,url.pathname)),metadata=files.get(url.pathname),extension=path.extname(url.pathname);
  assert.equal(sha(bytes),metadata.sha256,'served bytes changed: '+url.pathname);
  if(extension==='.js'||extension==='.css')assert.equal(url.searchParams.get('v'),metadata.sha256.slice(0,12),'PHP binds served asset hash');
  const types={'.js':'application/javascript','.css':'text/css','.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'};
  assert(types[extension],'no executable dependency is served as a static asset');served.add(url.pathname);
  res.writeHead(200,{'Content-Type':types[extension]}).end(bytes);
 }catch(error){failures.push(error.message);res.writeHead(500).end('Offline LIVE fixture failure.');}
});

(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true});
  const origin='http://127.0.0.1:'+server.address().port;
  for(const width of [430,1280]){
   served.clear();
   const height=width===430?932:900,transport=fixture(),tv=privateTVFixture(transport,origin),handoffs=[],network=[],errors=[],forbidden=[],stages=[];
   // The family fixture withholds the mismatched ANEX row. Existing fictional
   // TV variants make the list entry available while retaining visual-tv-101.
   transport.state.wideFacets=true;
   const context=await browser.newContext({viewport:{width,height},serviceWorkers:'block'}),page=await context.newPage();page.setDefaultTimeout(10000);
   await context.addInitScript(()=>{window.dataLayer=[];});
   const csrf='a'.repeat(64),intPath='/_preview/search3-anex-candidate/api-anex-search3-preview.php';
   const dataPaths=new Set(['/api-v2.php','/data/departures-v1.php','/data/hotel-search-v1.php',
    '/_preview/search3-local-candidate/data/search3-destination-read-v1.php',
    '/_preview/search3-local-candidate/data/search3-local-results-read-v1.php',
    '/_preview/search3-local-candidate/data/hotel-details-read-v1.php',
    '/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php']);
   let frozen,operationState='prepared';
   const receipt={scope:'compiled mixed-main public LIVE; fictional intercepted data and handoff responses',
    source:manifest.candidate,main:manifest.main,manifestSHA256:sha(manifestBytes),width,height,network,handoffs,stages,
    supplierHTTP:0,botHTTP:0,realLeads:0,installed:false,physicalDevice:false};
   const save=()=>fs.writeFileSync(path.join(output,'receipt-'+width+'.json'),JSON.stringify(receipt,null,2)+'\n');
   const capture=async name=>{
    await page.evaluate(()=>document.fonts.ready);
    const state=await page.evaluate(()=>{const modal=document.querySelector('#modal'),body=modal.querySelector('#modal-body'),footer=modal.querySelector('#modal-footer');
     const box=el=>{const r=el.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,height:r.height};};
     return {pageWidth:document.documentElement.scrollWidth,width:innerWidth,modalOpen:modal.open,modalWidth:modal.clientWidth,modalContent:modal.scrollWidth,
      bodyWidth:body.clientWidth,bodyContent:body.scrollWidth,body:box(body),footer:box(footer),
      status:document.querySelector('#messenger-status')?.textContent||null,amount:footer.querySelector('.footer-total>strong')?.textContent||null};});
    assert(state.pageWidth<=width+1,name+' fits the page');
    if(state.modalOpen){assert(state.modalContent<=state.modalWidth+1);assert(state.bodyContent<=state.bodyWidth+1);
     assert(state.body.bottom<=height+1,name+' body fits the viewport');
     if(state.footer.height>0)assert(state.footer.bottom<=height+1&&state.body.bottom<=state.footer.y+1,name+' footer does not cover the body');}
    const file=name+'-'+width+'.png';stages.push({name,file,...state});save();await page.screenshot({path:path.join(output,file)});
   };
   const noPhone=async()=>assert.equal(await page.locator('#modal form,#modal [name="phone"],#modal input[type="tel"],#modal [type="submit"],#modal [data-action="messenger-phone"],#modal [data-messenger-phone-submit]').count(),0,'public contact has no phone fallback');
   const ready=()=>page.waitForFunction(()=>{const b=[...document.querySelectorAll('#messenger-entry [data-action="messenger-open"]')];return b.length===2&&b.every(el=>!el.disabled);});
   const total=async()=>Number((await page.locator('#modal-footer .footer-total>strong').textContent()).replace(/[^0-9,.-]/g,'').replace(',','.'));
   page.on('pageerror',error=>errors.push(error.message));
   page.on('request',request=>network.push({method:request.method(),path:new URL(request.url()).pathname}));
   try{
    await context.route('**/*',async route=>{
     const request=route.request(),url=new URL(request.url());
     try{
      assert.equal(url.origin,origin,'external supplier/bot/analytics requests are forbidden');
      if(request.method()==='GET'&&(publicRoutes.has(url.pathname)||staticPaths.has(url.pathname))){await route.continue();return;}
      if(url.pathname==='/test-photo.svg'&&request.method()==='GET'){
       await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;
      }
      const body=request.postData()?JSON.parse(request.postData()):{},action=url.searchParams.get('action')||body.action;
      let value;
      if(url.pathname===intPath){
       value=await tv.handle(action,body,request.method());
       if(value===undefined&&String(action).startsWith('handoff_')){
        handoffs.push({action,body});
        if(action==='handoff_token'){assert.equal(request.method(),'GET');value={ok:true,csrf};}
        else{
         assert.equal(request.method(),'POST');assert.equal(body.csrf,csrf);
         if(action==='handoff_freeze'){
          const {action:_,csrf:__,...pins}=body;const result=tv.freeze(pins);
          if(frozen)assert.equal(result.operation_id,frozen.operation_id,'return retains the same operation');
          frozen=result;value={ok:true,data:{...result,state:operationState}};
         }else{
          assert(frozen,'CREATE/status follows the retained selection');
          assert.deepEqual(Object.keys(body).sort(),['action','csrf','operation_id']);assert.equal(body.operation_id,frozen.operation_id);
          if(action==='handoff_create'){assert.equal(handoffs.filter(row=>row.action===action).length,1,'no replacement CREATE');operationState='unknown';}
          else{assert.equal(action,'handoff_status','no implicit revoke or other mutation');operationState='unavailable';}
          value={ok:true,data:{operation_id:frozen.operation_id,state:operationState}};
         }
        }
       }
       if(value===undefined)value=await transport.json(request.url(),{body:request.postData()});
      }else{
       assert(dataPaths.has(url.pathname),'unlisted data/lead route '+url.pathname);
       if(url.pathname==='/api-v2.php')assert.equal(action,'meals','no public root supplier search/quote fallback');
       value=await transport.json(request.url(),{body:request.postData()});
      }
      await route.fulfill({contentType:'application/json',body:JSON.stringify(value)});
     }catch(error){forbidden.push(error.message);await route.abort();}
    });
    await page.goto(origin+'/poisk-turov/?'+new URLSearchParams({...trip,adults:3,ages:'12,0',scenario:'family',yclid:'offline-public-live'}));
    await page.locator('.search-submit:not(:disabled)').waitFor();
    const config=await page.evaluate(()=>({profile:V2_CONFIG.search3PublicEntry,contact:V2_CONFIG.search3ContactMode,
     privateTV:V2_CONFIG.search3TVSessionEnabled,messenger:V2_CONFIG.search3MessengerEnabled,counter:V2_CONFIG.metrikaCounter,
     live:AnyTourPrototypeData.live,scenario:AnyTourPrototypeData.scenario,analytics:V2Analytics.version}));
    assert.deepEqual(config,{profile:'live',contact:'messenger-only',privateTV:true,messenger:true,counter:0,live:true,scenario:'live',analytics:4});
    assert.equal(await page.locator('.prototype-tools,.private-badge').count(),0);
    await page.locator('.search-submit').click();await page.locator('.hotel-card').first().waitFor();
    await page.waitForFunction(()=>{const node=document.querySelector('#search-status');return node.hidden||node.classList.contains('search-status-compact');});
    assert.equal(new URL(page.url()).searchParams.get('yclid'),'offline-public-live');
    await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#all-offers-list').waitFor();
    await page.locator('#modal .modal-header [data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);
    await page.locator('.hotel-card h3 [data-action="hotel-details"]').first().click();await page.locator('#hotel-room-count').waitFor();
    const row=()=>page.locator('.room-offer-choice[data-offer-key="tourvisor%3Avisual-tv-101"]');
    await row().locator('[data-action="select-hotel-offer"]').click();await ready();await noPhone();
    assert.equal(handoffs.some(row=>row.action==='handoff_create'),false,'entering contact only prepares');
    await page.locator('#modal-back').click();await row().waitFor({state:'visible'});
    await row().locator('[data-action="hotel-flights"]').click();await row().locator('[name="flight-pair"][value="1"]').waitFor();
    await row().locator('[name="flight-pair"][value="1"]').click();
    // A new exact flight pair legitimately has its own frozen operation. The
    // unknown CREATE exercise below then keeps that operation through return.
    frozen=undefined;operationState='prepared';
    await page.locator('#hotel-detail-offers').click();await ready();await noPhone();
    assert.equal(await total(),133500.5);assert.deepEqual(frozen.observation.trip.childAges,[0,12]);
    assert.equal(frozen.observation.trip.adults,3);assert.equal(frozen.observation.price.amount,'133500.5');
    assert.equal(frozen.observation.flights.state,'selected');assert.equal(frozen.observation.flights.outward.length,1);assert.equal(frozen.observation.flights.inward.length,1);
    await capture('contact');
    await page.locator('#messenger-entry [data-platform="telegram"]').click();await page.locator('#messenger-check').waitFor({state:'visible'});
    await noPhone();assert.match(await page.locator('#messenger-status').textContent(),/Не удалось подтвердить переход/);
    assert.doesNotMatch(await page.locator('#messenger-status').textContent(),/не отправля|не доставлен/i);
    await capture('unknown');const operation=frozen.operation_id;
    await page.locator('#messenger-check').click();await page.waitForFunction(()=>document.querySelector('#messenger-status')?.textContent.includes('сейчас недоступен'));
    await noPhone();await capture('unavailable');assert.equal(frozen.operation_id,operation);
    assert.equal(handoffs.filter(row=>row.action==='handoff_create').length,1);assert.equal(handoffs.filter(row=>row.action==='handoff_status').length,1);
    assert.equal(handoffs.find(row=>row.action==='handoff_status').body.operation_id,operation);
    const actionsBefore=tv.calls.length;await page.locator('#messenger-entry [data-action="modal-back"]').click();await row().waitFor({state:'visible'});
    assert.equal(await row().getAttribute('aria-current'),'true');assert.equal(tv.calls.length,actionsBefore,'return does not requote or change selection');
    await capture('retained-tour');
    for(const file of [...initial,...lazy])assert(served.has(file),'actual journey loaded '+file);
    const events=await page.evaluate(()=>Array.isArray(window.dataLayer)?window.dataLayer.map(row=>row.v2_event):[]);
    assert(events.includes('search_started'),'retained main analytics observes the public search');
    assert(!events.includes('lead_submitted'),'a handoff transition is never counted as delivery');
    assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);assert.deepEqual(failures,[]);
    receipt.status='passed';receipt.runtime=config;receipt.finalOperation=operation;receipt.wholePartyAmount='133500.5';
    receipt.privateTVActions=tv.calls.map(row=>row.action);receipt.analyticsEvents=events;receipt.loadedGraph=[...served].filter(file=>initial.includes(file)||lazy.includes(file));save();
   }catch(error){receipt.status='failed';receipt.error=error.message;receipt.errors=errors;receipt.forbidden=forbidden;save();await page.screenshot({path:path.join(output,'failure-'+width+'.png')});throw error;}
   finally{await context.close();}
  }
  console.log('PASS compiled public LIVE 430/1280: actual PHP, retained main owners, complete initial/lazy graph, exact family pair/contact/unknown status/return; no phone; fictional transports only; supplier/bot/lead HTTP0');
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
