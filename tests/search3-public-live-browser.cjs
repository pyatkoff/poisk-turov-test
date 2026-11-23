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
const homeStylePaths=new Set(['/design-system-v2.css','/home-v1.css','/site-footer-v1.css']);
const staticPaths=new Set([...initial,...lazy,...manifest.graph.styles.map(resourcePath),
 ...[...files.keys()].filter(file=>file.startsWith('/visual-search/assets/')),
 ...(manifest.shared_ui?.enabled?['/images/logo.svg',...homeStylePaths,'/home-hero-v1.jpg']:[])]);
if(manifest.shared_ui?.enabled)assert.equal(files.get('/images/logo.svg')?.sha256,
 '3c033c7697dbb8879f6a867f659a7964efc180518f92655391c289b74ce2e049','verified immutable canonical logo');
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
const failures=[],served=new Set(),homeRoutes=new Set(['/','/index.php']),publicRoutes=new Set([...homeRoutes,'/poisk-turov/','/poisk-turov/index.php']);
// Installed root ICO/PNG files are outside the v2 artifact. These two explicit
// offline responses use its inventoried 64px artwork, never an invented 404
// exemption or an expansion of the static/data allowlists.
const faviconSource='/visual-search/assets/favicon.png',faviconVersionSource='/favicon.svg';
assert(files.has(faviconSource)&&files.has(faviconVersionSource),'favicon fixture inputs are inventoried');
const faviconPNG=fs.readFileSync(path.join(site,faviconSource));
assert.equal(sha(faviconPNG),files.get(faviconSource).sha256);
assert(faviconPNG.subarray(0,8).equals(Buffer.from('89504e470d0a1a0a','hex')));
assert.equal(faviconPNG.readUInt32BE(16),64);assert.equal(faviconPNG.readUInt32BE(20),64);
const iconHeader=Buffer.alloc(22);iconHeader.writeUInt16LE(1,2);iconHeader.writeUInt16LE(1,4);
iconHeader[6]=64;iconHeader[7]=64;iconHeader.writeUInt16LE(1,10);iconHeader.writeUInt16LE(32,12);
iconHeader.writeUInt32LE(faviconPNG.length,14);iconHeader.writeUInt32LE(22,18);
const faviconResponses=new Map([
 ['/favicon.png',{contentType:'image/png',body:faviconPNG}],
 ['/favicon.ico',{contentType:'image/x-icon',body:Buffer.concat([iconHeader,faviconPNG])}]
]);
const faviconReceipt={scope:'offline GET-only root icon fixtures; installed root ICO/PNG bytes are not part of this artifact',
 manifestSHA256:sha(manifestBytes),source:{path:faviconSource,sha256:files.get(faviconSource).sha256},
 versionSource:{path:faviconVersionSource,sha256:files.get(faviconVersionSource).sha256},
 responses:[...faviconResponses].map(([file,response])=>({path:file,contentType:response.contentType,bytes:response.body.length,sha256:sha(response.body)})),requests:[]};
const saveFavicons=()=>fs.writeFileSync(path.join(output,'root-favicon-fixture.json'),JSON.stringify(faviconReceipt,null,2)+'\n');saveFavicons();
function faviconResponse(url,method){
 const response=faviconResponses.get(url.pathname);if(!response)return null;
 assert.equal(method,'GET','only GET may receive a root favicon fixture');
 assert(!url.hash&&!url.username&&!url.password,'plain root icon URL');
 assert(!url.search||[...url.searchParams.keys()].length===1&&url.searchParams.get('v')===files.get(faviconVersionSource).sha256.slice(0,16),'only the owner favicon version is admitted');
 faviconReceipt.requests.push({path:url.pathname,query:url.search});saveFavicons();return response;
}
function render(route,query=''){
 const php='$_SERVER["SCRIPT_NAME"]=$argv[2];$_SERVER["HTTP_HOST"]="anytoour.ru";$_SERVER["DOCUMENT_ROOT"]=$argv[3];$_SERVER["REQUEST_URI"]=$argv[2].($argv[4]!==""?"?".$argv[4]:"");parse_str($argv[4],$_GET);ob_start();register_shutdown_function(function(){echo json_encode(["status"=>http_response_code()?:200,"body"=>ob_get_clean()],JSON_THROW_ON_ERROR);});require $argv[1];';
 return JSON.parse(execFileSync('php',['-n','-d','allow_url_fopen=0','-d','allow_url_include=0','-d','display_errors=stderr',
  '-r',php,path.join(site,homeRoutes.has(route)?'index.php':'poisk-turov/index.php'),route,site,query],{encoding:'utf8',timeout:10000}));
}
const html=render('/poisk-turov/','profile=review&scenario=family');
assert.equal(html.status,200);assert(!html.body.includes('fixture-data.js'));
fs.writeFileSync(path.join(output,'entry-root.html'),html.body);
assert.equal(render('/_preview/search3-site-candidate/poisk-turov/').status,403,'LIVE cannot replace the isolated site review');
const homeHTML=render('/','profile=review&scenario=family&searched=1');
assert.equal(homeHTML.status,200);assert(!homeHTML.body.includes('fixture-data.js'));
assert(homeHTML.body.includes('id="search-form"'),'the actual main root alias renders the HOME form');
fs.writeFileSync(path.join(output,'entry-home.html'),homeHTML.body);
const server=http.createServer((req,res)=>{
 try{
  const url=new URL(req.url,'http://fixture');
  assert.equal(req.method,'GET','the local server accepts only static/entry GETs');
  if(publicRoutes.has(url.pathname)){
   const result=render(url.pathname,url.search.slice(1));assert.equal(result.status,200);
   res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'}).end(result.body);return;
  }
  const favicon=faviconResponse(url,req.method);
  if(favicon){res.writeHead(200,{'Content-Type':favicon.contentType}).end(favicon.body);return;}
  assert(staticPaths.has(url.pathname),'only exact graph/static manifest paths may reach the server: '+url.pathname);
  const bytes=fs.readFileSync(path.join(site,url.pathname)),metadata=files.get(url.pathname),extension=path.extname(url.pathname);
  assert.equal(sha(bytes),metadata.sha256,'served bytes changed: '+url.pathname);
  if(extension==='.js'||extension==='.css')assert.equal(url.searchParams.get('v'),metadata.sha256.slice(0,homeStylePaths.has(url.pathname)?16:12),'PHP binds served asset hash');
  const types={'.js':'application/javascript','.css':'text/css','.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'};
  assert(types[extension],'no executable dependency is served as a static asset');served.add(url.pathname);
  res.writeHead(200,{'Content-Type':types[extension]}).end(bytes);
 }catch(error){failures.push(error.message);res.writeHead(500).end('Offline LIVE fixture failure.');}
});

(async()=>{
 execFileSync(process.execPath,[path.join(__dirname,'search3-home-catalog-paths.cjs')],{stdio:'inherit'});
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
      recoveryActions:[...document.querySelectorAll('#messenger-entry #messenger-check:not([hidden]),#messenger-entry [data-action="modal-back"]')].map(box),
      status:document.querySelector('#messenger-status')?.textContent||null,amount:footer.querySelector('.footer-total>strong')?.textContent||null};});
    assert(state.pageWidth<=width+1,name+' fits the page');
    if(state.modalOpen){assert(state.modalContent<=state.modalWidth+1);assert(state.bodyContent<=state.bodyWidth+1);
     assert(state.body.bottom<=height+1,name+' body fits the viewport');
     if(state.footer.height>0)assert(state.footer.bottom<=height+1&&state.body.bottom<=state.footer.y+1,name+' footer does not cover the body');}
    if(name==='unknown'){
     assert.equal(state.recoveryActions.length,2,'unknown keeps status and return actions');
     const [check,back]=state.recoveryActions;
     assert(back.x-check.right>=12||back.y-check.bottom>=7,'status and return remain visibly separate, including when wrapped');
     for(const action of state.recoveryActions)assert(action.height>=44&&action.x>=state.body.x&&action.right<=state.body.right,'recovery action fits its own touch area');
    }
    const file=name+'-'+width+'.png';stages.push({name,file,...state});save();await page.screenshot({path:path.join(output,file)});
   };
   const compactForm=async(label,children)=>{
    await page.evaluate(()=>document.fonts.ready);
    const geometry=await page.evaluate(()=>{
     const rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
     const form=document.querySelector('#search-form'),guests=form.querySelector('[data-action="guests"]'),budget=form.querySelector('#quick-budget'),detail=form.querySelector('#guests-detail');
     const textFits=(el,parent)=>{const bounds=parent.getBoundingClientRect(),range=document.createRange();range.selectNodeContents(el);const rows=[...range.getClientRects()];return rows.length>0&&rows.every(r=>r.x>=bounds.x-1&&r.right<=bounds.right+1&&r.y>=bounds.y-1&&r.bottom<=bounds.bottom+1);};
     return{form:rect(form),primary:rect(form.querySelector('.search-primary')),guests:rect(guests),guestLabel:rect(guests.querySelector('.field-label')),guestValue:rect(guests.querySelector('.field-control')),
      budget:rect(budget),budgetLabel:rect(budget.querySelector('span:not(.chevron)')),budgetValue:rect(budget.querySelector('strong')),budgetArrow:rect(budget.querySelector('.chevron')),
      meal:rect(form.querySelector('#quick-meal')),submit:rect(form.querySelector('.search-submit')),budgetInPrimary:!!budget.closest('.search-primary'),
      agesVisible:detail.getClientRects().length>0,texts:[guests.querySelector('.field-label'),guests.querySelector('#guests-label'),...(!detail.hidden?[detail]:[])].map(el=>({id:el.id||'guests-heading',fits:textFits(el,guests)})),
      budgetTextFits:[...budget.querySelectorAll('span:not(.chevron),strong')].every(el=>textFits(el,budget)),pageWidth:document.documentElement.scrollWidth};
    });
    assert(geometry.pageWidth<=width+1,label+' form fits the viewport');
    assert.equal(geometry.agesVisible,children,label+' shows ages only when children are selected');
    assert(geometry.texts.every(row=>row.fits)&&geometry.budgetTextFits,label+' retains the complete party, ages and budget text');
    assert.equal(geometry.budgetInPrimary,false,'budget stays outside the primary trip controls');
    if(width===430){
     assert(geometry.guests.height>=48&&geometry.guests.height<=(children?68:56),label+' tourists remain a compact full-width action');
     assert(Math.abs(geometry.guests.x-geometry.primary.x)<=1&&Math.abs(geometry.guests.width-geometry.primary.width)<=1,'tourists retain a full-width touch area');
     assert(geometry.guestValue.x>=geometry.guestLabel.right-1,'tourist label and selected composition share a horizontal row');
     assert(geometry.budget.height>=44&&geometry.budget.height<=56,'default budget is a compact secondary action');
     assert(Math.abs(geometry.budgetLabel.y-geometry.budgetValue.y)<=1&&geometry.budgetValue.x>=geometry.budgetLabel.right-1&&geometry.budgetArrow.x>=geometry.budgetValue.right-1,'default budget label, value and arrow occupy one separate horizontal row');
     assert(geometry.budget.y>=geometry.meal.bottom-1&&geometry.submit.y>=geometry.budget.bottom-1,'secondary budget stays after meal and before Find');
    }
    for(const selector of ['#search-form [data-action="guests"]','#quick-budget']){
     const control=page.locator(selector);await control.scrollIntoViewIfNeeded();
     const target=await control.evaluate(el=>{const r=el.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return{width:r.width,height:r.height,reachable:hit===el||el.contains(hit)};});
     assert(target.width>=44&&target.height>=44&&target.reachable,label+' '+selector+' has an unobstructed touch target');
    }
    (receipt.formGeometry??=[]).push({label,...geometry});
   };
   const noPhone=async()=>assert.equal(await page.locator('#modal form,#modal [name="phone"],#modal input[type="tel"],#modal [type="submit"],#modal [data-action="messenger-phone"],#modal [data-messenger-phone-submit]').count(),0,'public contact has no phone fallback');
   const ready=()=>page.waitForFunction(()=>{const b=[...document.querySelectorAll('#messenger-entry [data-action="messenger-open"]')];return b.length===2&&b.every(el=>!el.disabled);});
   const cancelFlightDraft=async()=>{
    const choice=handoffs.filter(row=>row.action==='handoff_freeze').at(-1).body.choice_ref;
    // Back restores the previous enabled contact HTML before its new prepare
    // settles; wait for that exact freeze before accepting the restored state.
    const prepared=page.waitForResponse(response=>{
     const request=response.request();if(request.method()!=='POST'||new URL(request.url()).pathname!==intPath)return false;
     const body=JSON.parse(request.postData()||'{}');return body.action==='handoff_freeze'&&body.choice_ref===choice;
    });
    await page.locator('#modal-footer [data-action="modal-back"]').click();await prepared;await ready();
   };
   const total=async()=>Number((await page.locator('#modal-footer .footer-total>strong').textContent()).replace(/[^0-9,.-]/g,'').replace(',','.'));
   page.on('pageerror',error=>errors.push(error.message));
   page.on('request',request=>network.push({method:request.method(),path:new URL(request.url()).pathname}));
   try{
    await context.route('**/*',async route=>{
     const request=route.request(),url=new URL(request.url());
     try{
      assert.equal(url.origin,origin,'external supplier/bot/analytics requests are forbidden');
      const favicon=faviconResponse(url,request.method());if(favicon){await route.fulfill(favicon);return;}
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
    await page.goto(origin+'/poisk-turov/?'+new URLSearchParams({...trip,scenario:'family',yclid:'offline-public-live'}));
    await page.locator('.search-submit:not(:disabled)').waitFor();
    const config=await page.evaluate(()=>({profile:V2_CONFIG.search3PublicEntry,contact:V2_CONFIG.search3ContactMode,
     privateTV:V2_CONFIG.search3TVSessionEnabled,messenger:V2_CONFIG.search3MessengerEnabled,counter:V2_CONFIG.metrikaCounter,
     live:AnyTourPrototypeData.live,scenario:AnyTourPrototypeData.scenario,analytics:V2Analytics.version}));
    assert.deepEqual(config,{profile:'live',contact:'messenger-only',privateTV:true,messenger:true,counter:0,live:true,scenario:'live',analytics:4});
    assert.equal(await page.locator('.prototype-tools,.private-badge').count(),0);
    assert.equal(await page.locator('#guests-label').textContent(),'2 взрослых');
    assert.equal(await page.locator('#guests-detail').isVisible(),false);
    await compactForm('initial-default',false);
    const defaultURL=page.url(),defaultBudget=await page.locator('#budget-label').textContent();
    await page.locator('#quick-budget').click();await page.locator('#budget-max').fill('200000');
    await page.locator('#modal .modal-header [data-action="close-modal"]').click();
    await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
    assert.equal(page.url(),defaultURL,'direct budget Cancel preserves the initial URL');
    assert.equal(await page.locator('#budget-label').textContent(),defaultBudget,'direct budget Cancel preserves the initial limit');
    await page.locator('#search').scrollIntoViewIfNeeded();await capture('initial-default');
    // Confirm the family in the actual initial form, then reload before Find.
    // Every transport remains the explicitly fictional interceptor above.
    await page.locator('#search-form [data-action="guests"]').click();
    await page.locator('[data-action="adults-plus"]').click();
    await page.locator('[data-action="children-plus"]').click();await page.locator('[data-action="children-plus"]').click();
    assert(await page.locator('[data-action="apply-guests"]').isDisabled(),'both child ages are required before confirming the family');
    for(const [index,age] of [0,12].entries()){
     await page.locator('[data-action="child-age"][data-index="'+index+'"]').click();
     await page.locator('[data-action="age-pick"][data-value="'+age+'"]').click();await page.locator('[data-action="apply-age"]').click();
    }
    await page.locator('[data-action="apply-guests"]').click();
    await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
    await page.locator('#quick-stars [data-value="5"]').click();
    await page.locator('#quick-meal').click();await page.locator('[data-meal-choice][value="Всё включено"]').check();await page.locator('[data-action="apply-meals"]').click();
    await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
    const confirmedFamilyURL=page.url(),confirmedFamily=new URL(confirmedFamilyURL).searchParams;
    assert.equal(confirmedFamily.get('adults'),'3');assert.equal(confirmedFamily.get('ages'),'0,12');
    assert.equal(confirmedFamily.get('stars'),'5');assert.equal(confirmedFamily.get('meals'),'Всё включено');
    assert.equal(confirmedFamily.has('searched'),false);
    await page.locator('#search-form [data-action="guests"]').click();await page.locator('[data-action="adults-plus"]').click();await page.locator('#modal .modal-header [data-action="close-modal"]').click();
    await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
    assert.equal(page.url(),confirmedFamilyURL,'Cancel cannot save the unconfirmed fourth adult');
    await page.reload();await page.locator('.search-submit:not(:disabled)').waitFor();
    assert.equal(page.url(),confirmedFamilyURL,'the compiled LIVE page retains the exact family and sibling filters after reload');
    assert.match(await page.locator('#guests-label').textContent(),/3 взр.*2 реб/);
    assert.equal(await page.locator('#guests-detail').textContent(),'До 1 года и 12 лет');
    assert.equal(await page.locator('.hotel-card').count(),0,'restoring the initial form does not display a submitted search');
    assert.deepEqual(tv.calls,[],'initial family Apply, Cancel and reload never start private TV operations');
    assert.deepEqual(handoffs,[],'initial family persistence never prepares a messenger handoff');
    const initialOperations=transport.calls.filter(call=>/^(search(?:_.*)?|continue|tour|flights|quote(?:_.*)?|offer|lead(?:_.*)?)$/.test(call.action)||/api-anex-|api-andromeda-|quote|lead/.test(call.url));
    assert.deepEqual(initialOperations,[],'no supplier search, quote, offer or lead precedes the explicit Find action');
    receipt.initialParty={adults:3,childAges:[0,12],reloadPreserved:true,cancelPreserved:true,searchSubmits:0};
    await compactForm('initial-family-restored',true);
    await page.locator('#search').scrollIntoViewIfNeeded();await capture('initial-family-restored');
    await page.locator('.search-submit').click();await page.locator('.hotel-card').first().waitFor();
    await page.waitForFunction(()=>{const node=document.querySelector('#search-status');return node.hidden||node.classList.contains('search-status-compact');});
    assert.equal(new URL(page.url()).searchParams.get('yclid'),'offline-public-live');
    await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#all-offers-list').waitFor();
    await page.locator('#modal .modal-header [data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);
    await page.locator('.hotel-card h3 [data-action="hotel-details"]').first().click();await page.locator('#hotel-room-count').waitFor();
    const row=()=>page.locator('.room-offer-choice[data-offer-key="tourvisor%3Avisual-tv-101"]');
    await row().locator('[data-action="select-hotel-offer"]').click();await ready();await noPhone();
    assert.equal(handoffs.some(row=>row.action==='handoff_create'),false,'entering contact only prepares');
    const privateCount=action=>tv.calls.filter(row=>row.action===action).length;
    assert.equal(privateCount('tv_flights'),0,'contact starts before flight inventory is read');
    const quotesBeforeFlights=privateCount('tv_tour');
    await page.locator('[data-action="contact-flights"]').click();
    await page.locator('#tour-flight-options [name="flight-pair"][value="0"]').waitFor();
    assert.equal(privateCount('tv_flights'),1,'the first messenger-only contact opens its deferred flight picker');
    await capture('first-contact-flights');
    await page.locator('#tour-flight-options [name="flight-pair"][value="0"]').click();
    await cancelFlightDraft();await noPhone();
    assert.equal(await total(),120000,'Cancel leaves the original whole-party amount');
    assert.equal(frozen.observation.flights.state,'manager-deferred','a cancelled draft does not select a flight');
    assert.equal(privateCount('tv_tour'),quotesBeforeFlights,'reading flights does not requote the tour');
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
    assert.equal(await page.locator('#messenger-check').isVisible(),false,'ready contact keeps the status check hidden');
    await capture('contact');
    // Exercise the details -> contact -> flights -> Apply -> Back route. A
    // changed pair must replace this tour's earlier details snapshot and the
    // retained selection, while an unapplied draft remains cancellable.
    const tourReads=privateCount('tv_tour'),flightReads=privateCount('tv_flights');
    const acceptedStay=structuredClone(frozen.observation.stay),acceptedTrip=structuredClone(frozen.observation.trip);
    const resume=async()=>{
     await page.locator('#modal .modal-header [data-action="close-modal"]').click();
     await page.waitForFunction(()=>!document.querySelector('#modal').open);
     await page.locator('.current-card-selection [data-card-selection][data-key="tourvisor%3Avisual-tv-101"]').click();
     await page.locator('#detail-total').waitFor();
    };
    const choose=async id=>{
     await page.locator('[data-action="contact-flights"]').click();
     await page.locator('#tour-flight-options [name="flight-pair"][value="'+id+'"]').click();
    };
    await resume();assert.equal(await total(),133500.5);
    await page.locator('[data-action="confirm-tour"]').click();await ready();
    await choose('0');await cancelFlightDraft();
    assert.equal(await total(),133500.5,'Cancel keeps the previously applied pair price');
    assert.match(frozen.observation.flights.outward[0].label,/TEST201/,'Cancel keeps the previously applied flight');
    await choose('0');frozen=undefined;operationState='prepared';
    await page.locator('[data-action="apply-flight"]').click();await ready();await noPhone();
    assert.equal(await total(),120000);assert.equal(frozen.observation.price.amount,'120000');
    assert.deepEqual(frozen.observation.stay,acceptedStay,'changing flights retains room, meal and operator');
    assert.deepEqual(frozen.observation.trip,acceptedTrip,'changing flights retains dates and the complete family');
    assert.match(frozen.observation.flights.outward[0].label,/TEST101/);assert.match(frozen.observation.flights.inward[0].label,/TEST102/);
    await capture('applied-contact-flight');
    await page.locator('#modal-back').click();await page.locator('#detail-total').waitFor();
    assert.equal(await total(),120000,'Back to details retains the explicitly applied pair and whole-party price');
    assert.match(await page.locator('.flight-summary').textContent(),/TEST101/);
    assert.doesNotMatch(await page.locator('.flight-summary').textContent(),/TEST201/);
    await capture('contact-flight-back');
    await resume();assert.equal(await total(),120000,'Close and continue retains the same applied pair');
    assert.match(await page.locator('.flight-summary').textContent(),/TEST101/);
    await page.locator('#modal .modal-header [data-action="close-modal"]').click();
    await page.waitForFunction(()=>!document.querySelector('#modal').open);
    await page.locator('.hotel-card h3 [data-action="hotel-details"]').first().click();await row().waitFor({state:'visible'});
    await row().locator('[data-action="select-hotel-offer"]').click();await ready();await noPhone();
    assert.equal(await total(),120000,'the room entry also reuses the accepted selection');
    assert.equal(privateCount('tv_tour'),tourReads,'Apply, Back and resume never requote the selected tour');
    assert.equal(privateCount('tv_flights'),flightReads,'Apply, Back and resume reuse the loaded inventory');
    assert.equal(handoffs.some(row=>row.action==='handoff_create'),false,'flight changes alone never create a messenger transition');
    receipt.contactFlightReturn={firstDeferredOpen:true,cancelPreserved:true,backPreserved:true,resumePreserved:true,
     beforeAmount:'133500.5',appliedAmount:'120000',outward:'TEST101',inward:'TEST102',adults:3,childAges:[0,12],additionalQuotes:0,additionalFlightReads:0};
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
    receipt.status='passed';receipt.runtime=config;receipt.finalOperation=operation;receipt.wholePartyAmount='120000';
    receipt.privateTVActions=tv.calls.map(row=>row.action);receipt.analyticsEvents=events;receipt.loadedGraph=[...served].filter(file=>initial.includes(file)||lazy.includes(file));save();
   }catch(error){receipt.status='failed';receipt.error=error.message;receipt.errors=errors;receipt.forbidden=forbidden;save();await page.screenshot({path:path.join(output,'failure-'+width+'.png')});throw error;}
   finally{await context.close();}
  }
  await require('./search3-public-home-browser.cjs')({browser,origin,manifest,manifestSHA256:sha(manifestBytes),files,staticPaths,publicRoutes,output,failures,faviconResponse,
   launchHistoryBrowser:()=>chromium.launch({headless:true,timeout:20000,ignoreDefaultArgs:['--disable-back-forward-cache']})});
  console.log('PASS compiled public LIVE 430/1280: actual PHP, retained main owners, complete initial/lazy graph, first contact flight load and exact family Apply/Cancel/Back/resume, unknown status/return; no phone; fictional transports only; supplier/bot/lead HTTP0');
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
