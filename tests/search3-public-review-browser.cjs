'use strict';
// The actual PHP public entry and compiled mixed-main artifact, with all transport
// restricted to local static files. This is review evidence, not supplier/device QA.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {execFileSync}=require('node:child_process'),{createHash}=require('node:crypto'),{chromium}=require('playwright');
const artifact=path.resolve(process.env.SEARCH3_PUBLIC_REVIEW_ROOT||''),site=path.join(artifact,'site');
assert(process.env.SEARCH3_PUBLIC_REVIEW_ROOT,'explicit review artifact is required');
const manifest=JSON.parse(fs.readFileSync(path.join(artifact,'manifest.json')));
assert.equal(manifest.deployable,false);assert.equal(manifest.profile.profile,'review');assert.equal(manifest.profile.scenario,'family');
const evidence=path.resolve('visual-live-evidence/public-review');fs.mkdirSync(evidence,{recursive:true});
const prefixes=['','/_preview/search3-site-candidate'],serverFailures=[];
const sha=bytes=>createHash('sha256').update(bytes).digest('hex');
const sharedHeader=manifest.shared_ui?.enabled===true;
if(sharedHeader)assert.equal(sha(fs.readFileSync(path.join(site,'images/logo.svg'))),
 '3c033c7697dbb8879f6a867f659a7964efc180518f92655391c289b74ce2e049','verified immutable canonical logo');
let rootEntrySaved=false;
const server=http.createServer((req,res)=>{
 try{
  const url=new URL(req.url,'http://review'),prefix=prefixes.find(value=>
   ['/poisk-turov/','/visual-search/','/prototype-search/'].some(route=>url.pathname.startsWith(value+route))||
   sharedHeader&&(url.pathname===value+'/site-header-v2.css'||value===''&&url.pathname==='/images/logo.svg'));
  if(prefix===undefined||req.method!=='GET'){serverFailures.push(req.method+' '+url.pathname);res.writeHead(403).end();return;}
  const localPath=url.pathname.slice(prefix.length),file=path.resolve(site,'.'+localPath+(localPath.endsWith('/')?'index.php':''));
  if(!file.startsWith(site+'/')||!fs.existsSync(file)||!fs.statSync(file).isFile()){serverFailures.push('missing '+localPath);res.writeHead(404).end();return;}
  if(localPath==='/poisk-turov/'||localPath==='/poisk-turov/index.php'){
   const php='$_SERVER["SCRIPT_NAME"]=$argv[2];$_SERVER["DOCUMENT_ROOT"]=$argv[3];$_SERVER["REQUEST_URI"]=$argv[4];parse_str($argv[5],$_GET);ob_start();include $argv[1];$body=ob_get_clean();echo json_encode(["status"=>http_response_code()?:200,"body"=>$body],JSON_THROW_ON_ERROR);';
   const result=JSON.parse(execFileSync('php',['-r',php,file,url.pathname,site,req.url,url.search.slice(1)],{encoding:'utf8',timeout:10000}));
   if(prefix===''&&localPath==='/poisk-turov/'&&!rootEntrySaved){
    assert.equal(result.status,200,'captured public root HTML must be a successful PHP response');
    const config=result.body.match(/data-search3-public-config="([^"]+)"/);assert(config,'captured PHP HTML contains its public build profile');
    assert.equal(JSON.parse(config[1].replace(/&quot;/g,'"').replace(/&amp;/g,'&')).profile,'review','captured PHP HTML must remain review-only');
    fs.writeFileSync(path.join(evidence,'entry-root.html'),result.body);rootEntrySaved=true;
   }
   res.writeHead(result.status,{'Content-Type':'text/html; charset=utf-8'}).end(result.body);return;
  }
  const extension=path.extname(file),types={'.js':'application/javascript','.css':'text/css','.json':'application/json','.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.woff2':'font/woff2'};
  if(!types[extension]){serverFailures.push('non-static '+localPath);res.writeHead(403).end();return;}
  const bytes=fs.readFileSync(file);
  if(['.js','.css'].includes(extension))assert.equal(url.searchParams.get('v'),sha(bytes).slice(0,12),'public PHP binds exact served asset '+localPath);
  res.writeHead(200,{'Content-Type':types[extension]}).end(bytes);
 }catch(error){serverFailures.push(error.message);res.writeHead(500).end('Review fixture failure.');}
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true});
  for(const width of [430,1280]){
   const context=await browser.newContext({viewport:{width,height:width===430?932:900}}),page=await context.newPage(),errors=[],forbidden=[],network=[],stages=[];
   page.on('pageerror',error=>errors.push(error.message));
   page.on('request',request=>network.push({method:request.method(),path:new URL(request.url()).pathname}));
   await page.route('**/*',async route=>{const request=route.request(),url=new URL(request.url());if(url.origin!==`http://127.0.0.1:${server.address().port}`||request.method()!=='GET'||/\.php$/.test(url.pathname)&&!url.pathname.endsWith('/poisk-turov/index.php')){forbidden.push(request.method()+' '+url.origin+url.pathname);await route.abort();return;}await route.continue();});
   const receipt={scope:'compiled public review; fictional family prices and simulated messenger unavailability; no real handoff or submitted contact form',width,height:width===430?932:900,
    source:manifest.candidate,main:manifest.main,manifestSHA256:sha(fs.readFileSync(path.join(artifact,'manifest.json'))),
    stages,network,supplierRequests:0,realLeads:0,physicalIPhone:false};
   const save=()=>fs.writeFileSync(path.join(evidence,'receipt-'+width+'.json'),JSON.stringify(receipt,null,2)+'\n');
   const capture=async name=>{
    await page.evaluate(()=>document.fonts.ready);
    const state=await page.evaluate(()=>({path:location.pathname,query:location.search,pageWidth:document.documentElement.scrollWidth,width:innerWidth,height:innerHeight,
     modalOpen:document.querySelector('#modal').open,modalWidth:document.querySelector('#modal').clientWidth,modalScrollWidth:document.querySelector('#modal').scrollWidth,
     footer:document.querySelector('#modal-footer').textContent,application:document.querySelector('.application-summary')?.textContent||null,
     selection:document.querySelector('.current-card-selection')?.textContent||null,
     messenger:document.querySelector('#messenger-entry')?{demo:document.querySelector('#messenger-entry').dataset.search3ReviewMessenger,
      status:document.querySelector('#messenger-status')?.textContent,buttons:[...document.querySelectorAll('#messenger-entry [data-action="messenger-open"]')].map(button=>({platform:button.dataset.platform,disabled:button.disabled})),
      phoneForms:document.querySelectorAll('#modal form,#modal [name="phone"]').length}:null}));
    assert(state.pageWidth<=width+1,name+' fits the viewport');
    if(state.modalOpen)assert(state.modalScrollWidth<=state.modalWidth+1,name+' dialog has no horizontal overflow');
    stages.push({name,...state});save();await page.screenshot({path:path.join(evidence,name+'-'+width+'.png')});
   };
   const total=async amount=>assert.equal(Number((await page.locator('#modal-footer .footer-total>strong').textContent()).replace(/\D/g,'')),amount,'retained whole-party fixture total');
   const party=async()=>{const text=await page.locator('.application-review-card').textContent();assert.match(text,/3 взр\./);assert.match(text,/2 реб\./);assert.match(text,/до (?:1 )?года/i);assert.match(text,/12 лет/);assert.match(text,/Всё включено/);assert.match(text,/Standard/);};
   const messenger=async unavailable=>{
    const entry=page.locator('#messenger-entry[data-search3-review-messenger="true"]');await entry.waitFor();
    assert.equal(await page.evaluate(()=>window.AnyTourPrototypeData?.live===true),false,'review fixture never becomes live');
    assert.equal(await page.evaluate(()=>V2_CONFIG.search3MessengerEnabled),false,'review never enables the real messenger transport');
    assert.equal(await page.locator('#modal form,#modal [name="phone"],#modal input[type="tel"],#modal [type="submit"],#modal [data-action="messenger-phone"],#modal [data-messenger-phone-submit]').count(),0,'public messenger contact has no telephone form or submit fallback');
    assert.deepEqual(await entry.locator('[data-action="messenger-open"]').evaluateAll(buttons=>buttons.map(button=>button.dataset.platform).sort()),['max','telegram']);
    for(const [platform,label] of [['telegram','Telegram'],['max','MAX']]){
     const button=entry.locator('[data-action="messenger-open"][data-platform="'+platform+'"]');assert(await button.isVisible());assert.equal((await button.textContent()).trim(),label);assert.equal(await button.isDisabled(),unavailable);
    }
    const status=entry.locator('#messenger-status'),text=(await status.textContent()).trim();assert.match(text,/^Демонстрация:/);
    assert.match(text,unavailable?/переход в Telegram и MAX сейчас недоступен/:/готов к показу перехода/);
    assert.match(text,unavailable?/Заявка не отправлялась/:/Заявки не отправляются/);
    assert.equal(((await entry.innerText()).match(/заявк(?:а|и) не отправля(?:лась|ются)/gi)||[]).length,1,'the visible contact notice appears once');
    assert.equal(await status.getAttribute('role'),'status');assert.equal(await status.getAttribute('aria-live'),'polite');
    assert.doesNotMatch(await entry.innerHTML(),/(?:csrf|operation_id|search_ref|offer_ref|choice_ref|record_id|anex_quote:|anex_online:|tourvisor_pair:|https?:\/\/|href\s*=)/i,'demo has no real selectors, operation, token or bot link');
    const geometry=await entry.evaluate(el=>{
     const box=node=>{const r=node.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,height:r.height};},body=el.closest('#modal-body'),footer=document.querySelector('#modal-footer');
     const summary=el.closest('.application-layout'),recap=box(summary.querySelector('.application-recap')),more=box(summary.querySelector('.application-more'));
     return {viewportHeight:innerHeight,body:box(body),footer:box(footer),status:box(el.querySelector('#messenger-status')),buttons:[...el.querySelectorAll('[data-action="messenger-open"]')].map(box),leftCardGap:more.y-recap.bottom};
    });
    assert(geometry.footer.bottom<=geometry.viewportHeight+1&&geometry.body.bottom<=geometry.footer.y+1,'messenger footer does not cover the contact content');
    for(const button of geometry.buttons){assert(button.height>=44,'each messenger has a 44px target');assert(button.x>=geometry.body.x-1&&button.right<=geometry.body.right+1);assert(button.y>=geometry.body.y-1&&button.bottom<=geometry.body.bottom+1,'messenger choices are visible without scrolling');}
    assert(geometry.status.y>=geometry.body.y-1&&geometry.status.bottom<=geometry.body.bottom+1,'the complete demo notice is visible');
    if(width===1280)assert(Math.abs(geometry.leftCardGap)<=1,'the left summary and details share one edge. Gap: '+geometry.leftCardGap+'px');
    if(unavailable)assert(await entry.locator('[data-action="modal-back"]').isVisible(),'failed demo has a return to the same tour');
    await total(186400);await party();assert.match(await page.locator('#modal-footer').textContent(),/Демонстрационная цена/);
   };
   try{
    const initial=new URLSearchParams({scenario:'live',origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-07',minNights:'7',maxNights:'7',adults:'2',ages:'0,8',searched:'1'});
    await page.goto(`http://127.0.0.1:${server.address().port}/poisk-turov/?${initial}`);
    await page.locator('#hotel-900001').waitFor();
    assert.equal(await page.evaluate(()=>V2_CONFIG.search3PublicEntry),'review','query cannot activate live');
    assert.equal(await page.evaluate(()=>V2_CONFIG.search3ReviewScenario),'family');
    assert.equal(await page.evaluate(()=>V2_CONFIG.api),null);assert.equal(await page.evaluate(()=>V2_CONFIG.anexApi),null);
    assert.equal(await page.evaluate(()=>V2_CONFIG.search3TVSessionEnabled),false);assert.equal(await page.evaluate(()=>V2_CONFIG.search3MessengerEnabled),false);
    assert.equal(await page.evaluate(()=>typeof V2Analytics),'undefined','review does not load analytics owner');
    assert.equal(await page.locator('.prototype-tools').count(),0,'public surface has no laboratory scenario picker');
    assert.match(await page.locator('body').textContent(),/Проверочная версия: сохранённые\/тестовые данные, заявки не отправляются/);
    assert.equal(new URL(page.url()).searchParams.get('scenario'),'family');
    await page.locator('#applied-search [data-action="edit-search"]:visible,#compact-search .secondary[data-action="top"]:visible').first().click();
    assert(await page.locator('#quick-stars').isVisible(),'hotel category remains visible in the form');
    assert.match(await page.locator('.quick-category legend').textContent(),/Категория отеля/);
    for(const stars of [3,4,5])assert(await page.locator('#quick-stars [data-value="'+stars+'"]').isVisible());
    if(width===430){
     const budget=page.locator('#quick-budget');assert(await budget.isVisible(),'budget is directly available in the secondary row');
     assert.equal(await page.locator('.search-primary #quick-budget').count(),0,'budget stays outside primary mobile parameters');
     const budgetTraffic=()=>network.filter(row=>row.method!=='GET'||/api|\/data\//.test(row.path)).length;
     const beforeBudget=page.url(),value=await page.locator('#budget-label').textContent(),networkBefore=budgetTraffic();
     await budget.click();await page.locator('#budget-min').waitFor();await page.locator('#budget-max').fill('200000');
     await page.locator('#modal .modal-header [data-action="close-modal"]').click();
     await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
     assert.equal(await page.locator('#budget-label').textContent(),value,'direct budget Cancel retains the applied value');
     assert.equal(page.url(),beforeBudget);assert.equal(budgetTraffic(),networkBefore,'direct budget editing starts no data or mutation request');
    }
    await page.locator('#quick-stars [data-value="5"]').click();
    await page.locator('#search-form [data-action="guests"]').click();await page.locator('[data-action="adults-plus"]').click();
    await page.locator('[data-action="child-age"][data-index="1"]').click();await page.locator('[data-action="age-pick"][data-value="12"]').click();await page.locator('[data-action="apply-age"]').click();await page.locator('[data-action="apply-guests"]').click();
    assert.equal(await page.locator('#guests-label').textContent(),'3 взр. · 2 реб.');assert.equal(await page.locator('#guests-detail').textContent(),'До 1 года и 12 лет');
    await page.locator('#search-form').scrollIntoViewIfNeeded();await capture('form');await page.locator('#search-form .search-submit').click();
    await page.waitForFunction(()=>document.querySelector('#hotel-900001 .starting-price')?.textContent.includes('3 взр.'));
    const query=new URL(page.url()).searchParams;assert.equal(query.get('adults'),'3');assert.equal(query.get('ages'),'0,12');assert.equal(query.get('stars'),'5');
    assert.match(await page.locator('#hotel-900001 .hotel-stars').textContent(),/5|★★★★★/);assert.match(await page.locator('#hotel-900001 .starting-price').textContent(),/186[\s\u00a0\u202f]*400/);
    await page.waitForFunction(()=>document.querySelector('#hotel-900001 .hotel-image')?.naturalWidth>0);
    await page.locator('#hotel-900001').scrollIntoViewIfNeeded();await capture('card');
    const firstPhotoStart=performance.now();
    await page.locator('#hotel-900001 h3 [data-action="hotel-details"]').click();
    const choose=page.locator('[data-action="select-hotel-offer"][data-key="demo-flight-24"]');
    const profilePhotos=page.locator('.hotel-profile-photo');
    const leadPhoto=profilePhotos.first().locator('img');
    assert.equal(await leadPhoto.getAttribute('loading'),'eager');
    await page.waitForFunction(()=>document.querySelector('.hotel-profile-photo')?.dataset.photoState==='ready');
    receipt.firstProfilePhotoMs=Math.round(performance.now()-firstPhotoStart);
    assert(await leadPhoto.evaluate(image=>image.naturalWidth>0));
    await choose.waitFor();
    await capture('room');

    // Use the same fixture with an isolated failing image URL to exercise native error events.
    const unavailablePhoto=/\/visual-search\/assets\/photo-[12]\.jpg\?gallery-error=1$/;
    await page.route(unavailablePhoto,route=>route.abort());
    const photoSources=await profilePhotos.locator('img').evaluateAll(images=>images.map(image=>image.src));
    await profilePhotos.locator('img').evaluateAll(images=>images.forEach(image=>{image.src+='?gallery-error=1';}));
    await page.waitForFunction(()=>document.querySelectorAll('.hotel-profile-photo[data-photo-state="failed"]').length===2);
    assert.deepEqual(await profilePhotos.locator('.hotel-profile-photo-status span').allTextContents(),['Фото не загрузилось','Фото не загрузилось']);
    const failedGalleryHeight=await page.locator('.hotel-detail-photos').evaluate(gallery=>gallery.getBoundingClientRect().height);
    assert(failedGalleryHeight<=96,'Unreasonably tall failed gallery: '+failedGalleryHeight);
    await capture('room-photos-unavailable');
    await page.unroute(unavailablePhoto);
    await profilePhotos.locator('img').evaluateAll((images,sources)=>images.forEach((image,i)=>{image.src=sources[i];}),photoSources);
    await page.waitForFunction(()=>document.querySelector('.hotel-profile-photo')?.dataset.photoState==='ready');
    await choose.click();
    await messenger(false);await capture('contact');
    const beforeTelegram=network.length;await page.locator('#messenger-entry [data-platform="telegram"]').click();
    await messenger(true);await capture('messenger-error');assert.equal(network.length,beforeTelegram,'Telegram demo failure performs no request');
    await page.locator('#messenger-entry [data-action="modal-back"]').click();await page.locator('#hotel-room-count').waitFor();
    assert.equal(await page.locator('[data-action="select-hotel-offer"][data-key="demo-flight-24"]').getAttribute('aria-pressed'),'true');
    await page.locator('#modal .modal-header [data-action="close-modal"]').click();
    const selected=page.locator('#hotel-900001 .current-card-selection');await selected.waitFor();assert.equal(await selected.getAttribute('data-selected-key'),'demo-flight-24');
    assert.match(await selected.textContent(),/186[\s\u00a0\u202f]*400/);await selected.locator('[data-action="offer"]').click();
    await page.locator('[data-action="confirm-tour"]').waitFor();await total(186400);await page.locator('[data-action="confirm-tour"]').click();
    await messenger(false);await capture('return-contact');
    const beforeMax=network.length;await page.locator('#messenger-entry [data-platform="max"]').click();
    await messenger(true);await capture('messenger-error-max');assert.equal(network.length,beforeMax,'MAX demo failure performs no request');
    await page.locator('#modal-back').click();await page.locator('[data-action="confirm-tour"]').waitFor();await total(186400);
    await page.locator('[data-action="confirm-tour"]').click();await messenger(false);await capture('return-messenger-ready');
    if(width===1280){
     await page.setViewportSize({width,height:720});await messenger(false);await capture('contact-short');
     const beforeShort=network.length;await page.locator('#messenger-entry [data-platform="telegram"]').click();
     await messenger(true);await capture('error-short');assert.equal(network.length,beforeShort,'short desktop demo failure performs no request');
     await page.setViewportSize({width,height:900});
    }
    const retainedURL=page.url();await page.reload();await page.locator('#hotel-900001').waitFor();
    assert.equal(new URL(page.url()).searchParams.get('ages'),'0,12');assert.equal(new URL(page.url()).searchParams.get('stars'),'5');
    receipt.retainedURL=retainedURL;receipt.familyTotal=186400;receipt.meal='Всё включено';receipt.offerKey='demo-flight-24';
    receipt.messengerDemo={simulatedUnavailable:['telegram','max'],readyAfterReturn:true,phoneForms:0,handoffCreates:0,botRequests:0};
    // Unknown old parameters remain explicit. No partial restore/autosearch is acceptable.
    const unsupported='from=1&country=4&unknown_filter=retain-me';await page.goto(`http://127.0.0.1:${server.address().port}/poisk-turov/?${unsupported}`);
    const fallback=page.locator('#catalog-error a');await fallback.waitFor();assert.equal(await fallback.getAttribute('href'),'/poisk-turov-old/?'+unsupported);
    assert.equal(new URL(page.url()).search,'?'+unsupported);assert.equal(await page.locator('.hotel-card').count(),0);await capture('unsupported-link');
    // The existing whole-site review prefix serves the same bounded profile and assets.
    await page.goto(`http://127.0.0.1:${server.address().port}/_preview/search3-site-candidate/poisk-turov/?${initial}`);
    await page.locator('#hotel-900001').waitFor();assert.equal(await page.evaluate(()=>V2_CONFIG.search3PublicResourceBase),'/_preview/search3-site-candidate/visual-search/');
    await page.waitForFunction(()=>document.querySelector('#hotel-900001 .hotel-image')?.naturalWidth>0);await capture('whole-site-preview');
    assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);assert.deepEqual(serverFailures,[]);
    assert.deepEqual(network.filter(row=>row.method!=='GET'||/\.php$/.test(row.path)&&!row.path.endsWith('/poisk-turov/index.php')),[],'review has no API, analytics or mutating requests');
    receipt.status='passed';save();
   }catch(error){receipt.status='failed';receipt.error=error.message;save();await page.screenshot({path:path.join(evidence,'failure-'+width+'.png')});throw error;}
   finally{await context.close();}
  }
  assert.equal(rootEntrySaved,true,'native PHP public root HTML evidence was captured');
  console.log('PASS public review compiled PHP journey: 430x932 and 1280x900, with 1280x720 contact/error; category/budget/family total/room return/legacy fallback/root and bounded preview; demo Telegram/MAX unavailable and retained tour return; no phone form; zero supplier/handoff/bot/lead calls; no physical-device claim');
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
