'use strict';
// Invoked by the existing native public LIVE owner after its Search3 corpus.
// The server, PHP root alias, manifest and compiled bytes belong to that owner.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
const {privateTVFixture}=require('./search3-visual-messenger-browser.cjs');
const catalogueTiming=require('./search3-public-home-catalog-timing.cjs');
const intPath='/_preview/search3-anex-candidate/api-anex-search3-preview.php';
const dataPaths=new Set(['/api-v2.php','/data/departures-v1.php','/data/hotel-search-v1.php',
 '/_preview/search3-local-candidate/data/search3-destination-read-v1.php',
 '/_preview/search3-local-candidate/data/search3-local-results-read-v1.php',
 '/_preview/search3-local-candidate/data/hotel-details-read-v1.php',
 '/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php']);
const viewports=[{width:390,height:844},{width:430,height:932},{width:1280,height:720},{width:390,height:740}];
const counts=(transport,tv)=>({tv:tv.calls.filter(call=>call.action==='tv_search').length,
 anex:transport.calls.filter(call=>call.url===intPath&&call.action==='search').length});
const idleModal=page=>page.waitForFunction(()=>!document.querySelector('#modal').open);
const doubleSubmit=page=>page.evaluate(()=>{const form=document.querySelector('#search-form');form.requestSubmit();form.requestSubmit();});
async function readyHome(page){
 await page.locator('.home-hero #search-form .search-submit:not(:disabled)').waitFor();
 const config=await page.evaluate(()=>({entry:AnyTourPublicConfigV1.entry,valid:AnyTourPublicConfigV1.valid,
  profile:V2_CONFIG.search3PublicEntry,formOnly:V2_CONFIG.search3FormOnly,counter:V2_CONFIG.metrikaCounter}));
 assert.deepEqual(config,{entry:'home',valid:true,profile:'live',formOnly:true,counter:0});
}
async function chooseFamily(page){
 await page.locator('#search-form [data-action="guests"]').click();
 await page.locator('[data-action="adults-plus"]').click();
 await page.locator('[data-action="children-plus"]').click();await page.locator('[data-action="children-plus"]').click();
 assert.equal(await page.locator('[data-action="apply-guests"]').isDisabled(),true,'missing child ages cannot be confirmed');
 for(const [index,age]of [0,12].entries()){
  await page.locator('[data-action="child-age"][data-index="'+index+'"]').click();
  await page.locator('[data-action="age-pick"][data-value="'+age+'"]').click();
  await page.locator('[data-action="apply-age"]').click();
 }
 await page.locator('[data-action="apply-guests"]').click();await idleModal(page);
 await page.locator('#quick-stars [data-value="4"]').click();await page.locator('#quick-stars [data-value="5"]').click();
 await page.locator('#quick-meal').click();await page.locator('[data-meal-choice][value="Всё включено"]').check();
 await page.locator('[data-action="apply-meals"]').click();await idleModal(page);
 await page.locator('#quick-budget').click();
 await page.locator('#budget-max').fill('240000');await page.locator('[data-action="apply-budget"]').click();await idleModal(page);
 assert.match(await page.locator('#budget-label').textContent(),/240\s*000/,'the chosen whole-tour budget stays visible');
}
async function geometry(page,viewport,home){
 await page.evaluate(()=>document.fonts.ready);
 if(home)await page.evaluate(()=>window.scrollTo(0,0));
 return page.evaluate(({viewport,home})=>{
  const box=el=>{const r=el.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
  // Rects can exist below closed details in Chromium. Native visibility follows
  // its user-agent shadow tree and content-visibility, as Playwright does.
  const visible=el=>!!el&&el.checkVisibility()&&el.getClientRects().length>0&&getComputedStyle(el).visibility==='visible';
  const result={viewport,pageWidth:document.documentElement.scrollWidth};
  const modal=document.querySelector('#modal');
  if(modal?.open){
   const body=modal.querySelector('#modal-body'),footer=modal.querySelector('#modal-footer');
   result.modal={width:modal.clientWidth,content:modal.scrollWidth,bodyWidth:body.clientWidth,bodyContent:body.scrollWidth,body:box(body),footer:box(footer)};
  }
  if(home){
   result.formsInHero=document.querySelectorAll('.home-hero form').length;
   const selectors=['#search-form [data-action="departure"]','#search-form [data-action="destination"]',
    '#search-form [data-action="dates"]','#search-form [data-action="nights"]','#search-form [data-action="guests"]',
    '#quick-stars','#quick-meal','#quick-budget','#search-form .search-submit'];
   result.controls=Object.fromEntries(selectors.map(selector=>{
    const el=document.querySelector(selector),rect=el?box(el):null,hit=rect?document.elementFromPoint(rect.x+rect.width/2,rect.y+rect.height/2):null;
    return [selector,{count:document.querySelectorAll(selector).length,visible:visible(el),rect,hit:!!el&&(hit===el||el.contains(hit))}];
   }));
  }
  return result;
 },{viewport,home});
}
function checkGeometry(value,label,home){
 const {width,height}=value.viewport;
 assert(value.pageWidth<=width+1,label+' has no page overflow');
 if(value.modal){const m=value.modal;
  assert(m.content<=m.width+1&&m.bodyContent<=m.bodyWidth+1,label+' modal has no horizontal overflow');
  assert(m.body.bottom<=height+1,label+' modal body fits the viewport');
  if(m.footer.height>0)assert(m.footer.bottom<=height+1&&m.body.bottom<=m.footer.y+1,label+' footer does not cover the body');
 }
 if(!home)return;
 assert.equal(value.formsInHero,1,label+' has one actual form in the hero');
 for(const [selector,control]of Object.entries(value.controls)){
  assert.equal(control.count,1,label+' has one '+selector);assert(control.visible,label+' shows '+selector);
  assert(control.rect.width>0&&control.rect.height>0&&control.rect.x>=-1&&control.rect.right<=width+1,label+' fits '+selector);
 }
 const meal=value.controls['#quick-meal'].rect,budget=value.controls['#quick-budget'].rect;
 assert(Math.abs(meal.y-budget.y)<=1&&budget.x>=meal.right-1,label+' keeps the visible budget beside meals');
 assert(meal.height>=44&&budget.height>=44,label+' keeps both controls tappable');
 const submit=value.controls['#search-form .search-submit'];
 assert(submit.rect.height>=44&&submit.rect.y>=0&&submit.rect.bottom<=height+1&&submit.hit,label+' keeps an unobstructed Find action in the first viewport');
}
function checkTrip(url,nights,searched){
 const params=new URL(url).searchParams;
 for(const [key,value]of Object.entries({adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000',
  minNights:String(nights),maxNights:String(nights),country:'4',origin:'Москва',from:trip.from,to:trip.to,
  yclid:'offline-public-home',utm_source:'native-live-fixture',utm_medium:'fixture'}))assert.equal(params.get(key),value,'handoff retains '+key);
 assert.equal(params.get('searched'),searched?'1':null,'only the receiver owns searched=1');
}
async function backState(page){
 await page.waitForFunction(()=>window.__homePageShows?.length>0);
 return page.evaluate(()=>{
  const navigation=performance.getEntriesByType('navigation')[0];
  return {documentId:window.__homeDocumentId,pageshow:window.__homePageShows,
   navigationType:navigation?.type||null,reasonAPIAvailable:!!navigation&&'notRestoredReasons'in navigation,
   notRestoredReasons:navigation?.notRestoredReasons?JSON.parse(JSON.stringify(navigation.notRestoredReasons)):null};
 });
}

module.exports=async function publicHomeJourney({browser,origin,manifest,manifestSHA256,files,staticPaths,publicRoutes,output,failures,faviconResponse,launchHistoryBrowser}){
 assert.equal(manifest.shared_ui?.enabled,true,'HOME uses the admitted shared entry graph');
 assert.equal(Object.values(manifest.files).flat().find(row=>row.path==='index.php')?.source_path,'v2/home-entry-v1.php','serve the actual main root alias');
 const directory=path.join(output,'home');fs.mkdirSync(directory,{recursive:true});
 const assetNames=['/index.php','/home-v1.php','/home-v1.css','/visual-search/entry-v1.php','/visual-search/app.js',
  '/visual-search/public-config-v1.js','/visual-search/public-bootstrap-v1.js','/search3-canonical-profiles-v1.js','/prototype-search/data.js'];
 const receipt={scope:'native compiled public LIVE HOME; actual PHP root alias; fictional intercepted transports',
  source:manifest.candidate,main:manifest.main,manifestSHA256,browser:browser.version(),
  servedHashes:Object.fromEntries(assetNames.map(name=>{assert(files.has(name),name+' is inventoried');return [name,files.get(name).sha256];})),
  supplierHTTP:0,botHTTP:0,realLeads:0,installed:false,physicalDevice:false,cases:[],status:'running'};
 const save=()=>fs.writeFileSync(path.join(directory,'receipt-home.json'),JSON.stringify(receipt,null,2)+'\n');save();
 async function runCase(owner,{viewport,platform=null,historyCache=false,selectedPair=false,retained='fresh',benchmark=null}){
  const variant=historyCache?'-warm':selectedPair?'-pair':retained==='fresh'?'':'-'+retained;
  const contextName=benchmark?benchmark.name:platform?platform+variant:historyCache?'history-cache':'form';
  const id=contextName+'-'+viewport.width+'x'+viewport.height;
  const transport=fixture(),tv=privateTVFixture(transport,origin,{minimum:selectedPair}),errors=[],consoleErrors=[],forbidden=[],network=[],navigations=[],documentCommits=[],handoffs=[],externalNavigations=[];
  const catalogueCalls=[];
  const checkApplication=Boolean(platform)||!historyCache&&[430,1280].includes(viewport.width);
  transport.state.wideFacets=true;
  const csrf='a'.repeat(64),links={telegram:'https://t.me/Any_tour_bot?start=home_fixture_only',max:'https://max.ru/id9704048781_bot?start=home_fixture_only'};
  let frozen,returning=false;
  const context=await owner.newContext({viewport,serviceWorkers:'block'}),page=await context.newPage();page.setDefaultTimeout(15000);
  if(benchmark)await context.addInitScript(catalogueTiming.observeReadyHome);
  await context.addInitScript(({retained})=>{
   window.dataLayer=[];window.__homeDocumentId=Date.now()+'-'+Math.random();window.__homePageShows=[];
   addEventListener('pageshow',event=>window.__homePageShows.push({persisted:event.persisted}));
   if(retained==='fresh')return;
   const key='anytour.prototype.v18.ui.v1',replaceState=history.replaceState;
   history.replaceState=function(state,...args){
    const entry=state?.[key];
    if(entry?.messengerExit===true&&entry.messengerReturn){
     if(retained==='missing')delete entry.messengerReturn;
     if(retained==='expired')entry.messengerReturn.at=0;
     if(retained==='mismatch')entry.messengerReturn.scope+='different-conditions';
    }
    return replaceState.call(this,state,...args);
   };
  },{retained});
  page.on('pageerror',error=>errors.push(error.message));
  page.on('console',message=>{if(message.type()==='error')consoleErrors.push(message.text());});
  page.on('framenavigated',frame=>{if(frame===page.mainFrame())navigations.push(frame.url());});
  // History.replaceState emits framenavigated too. Count actual document loads
  // separately while retaining every URL rewrite in the diagnostic trace.
  page.on('domcontentloaded',()=>documentCommits.push(page.url()));
  page.on('request',request=>network.push({method:request.method(),path:new URL(request.url()).pathname,
   document:request.isNavigationRequest()&&request.frame()===page.mainFrame()}));
  const row={id,viewport,platform,historyCache,errors,consoleErrors,forbidden,network,navigations,documentCommits,handoffs,externalNavigations,stages:[],status:'running'};
  if(!benchmark){receipt.cases.push(row);save();}
  const privateRequests=()=>network.filter(request=>request.path===intPath).length;
  const capture=async(name,home=false)=>{
   const value=await geometry(page,viewport,home),file=id+'-'+name+'.png';
   row.stages.push({name,file,...value});save();
   await page.screenshot({path:path.join(directory,file)});checkGeometry(value,id+' '+name,home);
  };
  async function submit(nights,expected){
   const beforeNavigation=documentCommits.filter(url=>new URL(url).pathname==='/poisk-turov/').length;
   const beforeDocumentId=await page.evaluate(()=>window.__homeDocumentId);
   const beforeDocuments=network.filter(request=>request.document&&request.path==='/poisk-turov/').length;
   const response=action=>page.waitForResponse(result=>{
    const request=result.request();if(new URL(request.url()).pathname!==intPath||request.method()!=='POST')return false;
    return JSON.parse(request.postData()||'{}').action===action;
   });
   await Promise.all([page.waitForURL(url=>url.pathname==='/poisk-turov/'),response('tv_search'),response('search'),doubleSubmit(page)]);
   await page.waitForFunction(()=>Number(window.AnyTourPrototypeData?.searchId)>0&&window.dataLayer?.some(row=>row.v2_event==='search_complete'));
   checkTrip(page.url(),nights,true);
   assert.equal(documentCommits.filter(url=>new URL(url).pathname==='/poisk-turov/').length-beforeNavigation,1,'double submit commits one receiver document');
   assert.notEqual(await page.evaluate(()=>window.__homeDocumentId),beforeDocumentId,'the receiver has a new document identity');
   assert.equal(network.filter(request=>request.document&&request.path==='/poisk-turov/').length-beforeDocuments,1,'double submit requests one receiver document');
   assert.deepEqual(counts(transport,tv),{tv:expected,anex:expected},'receiver starts each supplier once');
  }
  try{
   await context.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    try{
     if(platform&&url.href===links[platform]&&request.isNavigationRequest()){
      assert.equal(request.method(),'GET');externalNavigations.push(url.href);returning=true;
      await route.fulfill({contentType:'text/html',body:'<!doctype html><title>Fictional HOME handoff</title><h1>Navigation intercepted; no message was sent.</h1>'});return;
     }
     assert.equal(url.origin,origin,'external supplier, bot and analytics traffic is forbidden');
     const favicon=faviconResponse(url,request.method());if(favicon){await route.fulfill(favicon);return;}
     if(benchmark&&request.method()==='GET'&&url.pathname===catalogueTiming.dataURL){
      await catalogueTiming.servePinnedAsset(route,benchmark.variant,receipt.servedHashes[catalogueTiming.dataURL]);return;
     }
     if(request.method()==='GET'&&(publicRoutes.has(url.pathname)||staticPaths.has(url.pathname))){await route.continue();return;}
     if(url.pathname==='/test-photo.svg'&&request.method()==='GET'){
      await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;
     }
     const body=request.postData()?JSON.parse(request.postData()):{},action=url.searchParams.get('action')||body.action;let value;
     if(url.pathname===intPath){
      value=await tv.handle(action,body,request.method());
      if(value===undefined&&String(action).startsWith('handoff_')){
       assert(checkApplication,'contact preparation is allowed only in the expected walkthrough');handoffs.push({action,body});
       if(action==='handoff_token'){assert.equal(request.method(),'GET');value={ok:true,csrf};}
       else{
        assert.equal(request.method(),'POST');assert.equal(body.csrf,csrf);
        if(action==='handoff_freeze'){
         const {action:_,csrf:__,...pins}=body;const result=tv.freeze(pins);
         if(frozen)assert.equal(result.operation_id,frozen.operation_id,'one retained selection');frozen=result;value={ok:true,data:result};
        }else{
         assert(platform,'the contact-only walkthrough never sends the application');
         assert.equal(action,'handoff_create');assert(frozen);assert.equal(body.operation_id,frozen.operation_id);
         assert.deepEqual(Object.keys(body).sort(),['action','csrf','operation_id']);
         assert.equal(handoffs.filter(call=>call.action==='handoff_create').length,1,'only one mock CREATE');
         value={ok:true,data:{operation_id:body.operation_id,state:'ready',links}};
        }
       }
      }
      if(value===undefined)value=await transport.json(request.url(),{body:request.postData()});
     }else{
      assert(dataPaths.has(url.pathname),'unlisted data/lead route '+url.pathname);
      const catalogue=catalogueTiming.catalogueRequest(url.pathname,action,body.provider);
      if(catalogue){
       catalogueCalls.push(catalogue);
       if(benchmark?.controlled)await new Promise(resolve=>setTimeout(resolve,catalogueTiming.delays[catalogue]));
       if(benchmark?.unavailable&&catalogue==='tourvisor'){
        await route.fulfill({json:{ok:false,error:'Fictional canonical meal catalogue unavailable'}});return;
       }
      }
      if(url.pathname==='/api-v2.php'){
        assert.equal(action,'meals','no root supplier fallback');
        // A fresh document can see pageshow while the catalog is still loading.
        if(returning)await new Promise(resolve=>setTimeout(resolve,300));
       }
      value=await transport.json(request.url(),{body:request.postData()});
     }
     await route.fulfill({contentType:'application/json',body:JSON.stringify(value)});
    }catch(error){forbidden.push(error.message);await route.abort().catch(()=>{});}
   });
   const initial=new URLSearchParams({...trip,scenario:'live',searched:'1',yclid:'offline-public-home',utm_source:'native-live-fixture',utm_medium:'fixture'});
   if(benchmark){
    initial.set('meals','AI|Всё включено');initial.set('max','240000');
    await page.goto(origin+'/?'+initial);await readyHome(page);
    await page.waitForFunction(()=>window.__homeCatalogueReady);
    const timing=await page.evaluate(()=>window.__homeCatalogueReady);
    assert(timing.ready>=timing.navigationResponse);
    assert.deepEqual(catalogueCalls.slice().sort(),benchmark.variant.name==='before'?catalogueTiming.catalogueNames:['countries','departures','tourvisor']);
    assert.deepEqual(counts(transport,tv),{tv:0,anex:0});assert.equal(privateRequests(),0);
    assert.equal(await page.locator('#meal-label').textContent(),'Всё включено');
    assert.match(await page.locator('#budget-label').textContent(),/240\s*000/);
    checkGeometry(await geometry(page,viewport,true),id,true);
    await page.locator('#quick-meal').click();
    const options=await page.locator('[data-meal-choice]').evaluateAll(nodes=>nodes.map(node=>[node.value,node.checked]));
    const expected=benchmark.unavailable?[['',false],['Всё включено',true]]:[['',false],['Всё включено',true],['Завтраки',false]];
    assert.deepEqual(options,expected,id+' preserves the saved choice and the available canonical options');
    assert.deepEqual(errors,[]);assert.deepEqual(consoleErrors,[]);assert.deepEqual(forbidden,[]);assert.deepEqual(failures,[]);
    return {...timing,requests:catalogueCalls,options};
   }
   await page.goto(origin+'/?'+initial);await readyHome(page);
   assert.deepEqual(counts(transport,tv),{tv:0,anex:0});assert.equal(privateRequests(),0,'HOME searched=1 starts no private supplier preparation');
   await capture('initial',true);await chooseFamily(page);
   assert.match(await page.locator('#guests-label').textContent(),/3 взр.*2 реб/);
   assert.equal(await page.locator('#guests-detail').textContent(),'До 1 года и 12 лет');
   assert.equal(privateRequests(),0,'editing HOME controls starts no private supplier preparation');await capture('family',true);
   const initialDocument=(await backState(page)).documentId;
   const homeCatalogues=catalogueCalls.length;
   await submit(7,1);await page.locator('.hotel-card').first().waitFor();
   const receiverCatalogues=catalogueCalls.slice(homeCatalogues).sort();
   assert.deepEqual(receiverCatalogues,catalogueTiming.catalogueNames,'only HOME can omit the offer mappings');
   const params=tv.calls.find(call=>call.action==='tv_search').body.params;
   assert.equal(params.adults,3);assert.deepEqual(params.childs,[0,12]);assert.equal(String(params.priceTo),'240000');assert.equal(String(params.countryId),'4');
   row.firstSearch={url:page.url(),counts:counts(transport,tv),params,catalogues:receiverCatalogues};await capture('receiver');
   let observed=null,searchDocument='';
   if(checkApplication){
    if(selectedPair)await page.locator('.hotel-card .hotel-price [data-key="tourvisor%3Avisual-tv-101"]').click();
    else await page.locator('.hotel-card h3 [data-action="hotel-details"]').first().click();
    const offer=page.locator('.room-offer-choice[data-offer-key="tourvisor%3Avisual-tv-101"]');await offer.waitFor();
    await capture('hotel-offer');
    if(selectedPair){
     await offer.locator('[data-action="hotel-flights"]').click();
     await offer.locator('[name="flight-pair"][value="1"]').click();
     await page.locator('#hotel-detail-offers').click();
    }else await offer.locator('[data-action="select-hotel-offer"]').click();
    await page.waitForFunction(()=>{const buttons=[...document.querySelectorAll('#messenger-entry [data-action="messenger-open"]')];return buttons.length===2&&buttons.every(button=>!button.disabled);});
    assert(frozen);assert.equal(frozen.observation.trip.adults,3);assert.deepEqual(frozen.observation.trip.childAges,[0,12]);
    assert.equal(frozen.observation.trip.nights,7);assert.equal(frozen.observation.stay.room,'STANDARD SEA VIEW');
    assert.equal(handoffs.filter(call=>call.action==='handoff_create').length,0,'opening contacts never creates a request');
    assert.equal(await page.locator('#modal form,#modal [name="phone"],#modal input[type="tel"],#modal [data-action="messenger-phone"]').count(),0);
    observed=frozen.observation;
    assert.equal(observed.flights.state,selectedPair?'selected':'manager-deferred');
    const shownTotal=await page.locator('#modal-footer .footer-total>strong').innerText();
    assert.equal(Number(shownTotal.replace(/[^\d,]/g,'').replace(',','.')),Number(observed.price.amount),'the exact whole-family total is checked before navigation');
    searchDocument=(await backState(page)).documentId;
    await capture('contact');
    if(!platform){
     await page.locator('#modal .modal-header [data-action="close-modal"]').click();
     await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
     assert.equal(handoffs.filter(call=>call.action==='handoff_create').length,0,'the walkthrough stops before sending');
    }
   }
   if(platform){
    await Promise.all([page.waitForURL(links[platform]),page.locator('[data-action="messenger-open"][data-platform="'+platform+'"]').click()]);
    assert.deepEqual(externalNavigations,[links[platform]]);assert.equal(handoffs.filter(call=>call.action==='handoff_create').length,1);
    assert.deepEqual(counts(transport,tv),{tv:1,anex:1});row.frozenObservation=observed;await capture('intercepted-navigation');
    const before=privateRequests(),contactCalls=handoffs.length;
    await page.goBack();await page.waitForURL(url=>url.pathname==='/poisk-turov/');
    row.messengerBack=await backState(page);
    const warm=row.messengerBack.pageshow.at(-1).persisted;
    if(warm){
     assert(historyCache,'the default test browser must exercise cold Back');
     assert.equal(row.messengerBack.documentId,searchDocument);
     await page.locator('#messenger-entry [data-action="messenger-open"]').first().waitFor();
     assert.deepEqual(counts(transport,tv),{tv:1,anex:1});
    }else{
     assert.equal(row.messengerBack.navigationType,'back_forward');
     assert.notEqual(row.messengerBack.documentId,searchDocument);
     const restored=page.locator('#messenger-return');
     const find=restored.locator('button[form="search-form"]:not(:disabled)');
     await find.waitFor();
     checkTrip(page.url(),7,false);
     const display=await restored.innerText();
     if(retained==='fresh'){
      for(const label of [observed.hotel.name,observed.stay.room,'Всё включено','3 взр.','до года','12 лет'])assert(display.includes(label),'keeps '+label);
      if(selectedPair)for(const label of [...observed.flights.outward,...observed.flights.inward])assert(display.includes(label.label),'keeps exact saved flight '+label.label);
      else assert.match(display,/Перелёт оставлен на потом/);
     }else{
      assert.match(display,/Условия поиска сохранены/);
      assert(!display.includes(observed.hotel.name),'stale or missing selection is not displayed');
     }
     assert(!/[₽€$]|\bRUB\b/.test(display),'the former price is not an active quote');
     assert.equal(await page.locator('#modal[open],#messenger-entry').count(),0);
     const retainedState=await page.evaluate(()=>history.state?.['anytour.prototype.v18.ui.v1']?.messengerReturn??null);
     if(retainedState?.tour){
      assert.deepEqual(Object.keys(retainedState.tour).sort(),['hotel','origin','day','nights','adults','ages','room','meal','placement','flight','outward','inward'].sort());
      assert(!/utm_|yclid|https?:|home_fixture_only|operation_id|search_ref|csrf/.test(JSON.stringify(retainedState)));
     }
     await restored.scrollIntoViewIfNeeded();await capture('messenger-back');
     assert.equal(await page.evaluate(()=>dataLayer.some(event=>event.v2_event==='search_complete')),false,'Back sends no search completion');
     assert.deepEqual(counts(transport,tv),{tv:1,anex:1});
    }
    assert.equal(privateRequests(),before,'Back does not call supplier, quote or handoff endpoints');
    assert.equal(handoffs.length,contactCalls);
    row.returnCounts={...counts(transport,tv),create:handoffs.filter(call=>call.action==='handoff_create').length};
    if(!warm&&retained==='fresh'){
     await page.locator('#messenger-return button[form="search-form"]').click();
     await page.waitForFunction(()=>window.dataLayer?.some(event=>event.v2_event==='search_complete'));
     checkTrip(page.url(),7,true);assert.deepEqual(counts(transport,tv),{tv:2,anex:2},'a deliberate Find starts one new generation');
     assert.equal(handoffs.length,contactCalls,'a new Find does not create another handoff');
     assert.equal(await page.locator('#messenger-return').count(),0);
    }
   }else{
    const beforeBack=privateRequests();await page.goBack();await readyHome(page);checkTrip(page.url(),7,false);
    row.back=await backState(page);row.actualBFCacheRestored=row.back.pageshow.some(event=>event.persisted===true);
    row.back.reasonStatus=row.actualBFCacheRestored?'restored':row.back.notRestoredReasons?'reported-by-browser':'not-exposed-by-browser';
    if(row.actualBFCacheRestored)assert.equal(row.back.documentId,initialDocument,'BFCache retains the actual document');
    else assert.notEqual(row.back.documentId,initialDocument,'cold Back reconstructs the document');
    assert.equal(privateRequests(),beforeBack,'Back to HOME does not prepare suppliers');
    assert.equal(await page.locator('#guests-detail').textContent(),'До 1 года и 12 лет');await capture('back',true);
    await page.locator('#search-form [data-action="nights"]').click();await page.locator('[data-action="night-pick"][data-value="8"]').click();
    await page.locator('[data-action="apply-nights"]').click();await idleModal(page);
    await page.locator('#search-form [data-action="guests"]').click();await page.locator('[data-action="apply-guests"]').click();await idleModal(page);
    await submit(8,2);
    const beforeCold=privateRequests();await page.goBack();await readyHome(page);
    const beforeReload=(await backState(page)).documentId;await page.reload();await readyHome(page);checkTrip(page.url(),8,false);
    row.coldReload=await backState(page);assert.notEqual(row.coldReload.documentId,beforeReload,'reload executes a fresh HOME document');
    assert.equal(privateRequests(),beforeCold,'cold HOME restores conditions without preparing suppliers');
    assert.equal(await page.locator('#guests-detail').textContent(),'До 1 года и 12 лет');await capture('cold-reload',true);
    const incomplete=new URLSearchParams({...trip,ages:'0,',searched:'1'});await page.goto(origin+'/?'+incomplete);
    await readyHome(page);await doubleSubmit(page);
    await page.locator('#modal[open] [data-action="apply-guests"]').waitFor();
    assert.equal(await page.locator('[data-action="apply-guests"]').isDisabled(),true,'an incomplete age requires correction');
    assert.equal(new URL(page.url()).pathname,'/','an incomplete child age blocks navigation');
    assert.equal(privateRequests(),beforeCold,'an incomplete age starts no supplier preparation');assert.deepEqual(counts(transport,tv),{tv:2,anex:2});
    row.incompleteAge={url:page.url(),applyDisabled:true};await capture('incomplete-age');
   }
   assert.deepEqual(errors,[]);assert.deepEqual(consoleErrors,[]);assert.deepEqual(forbidden,[]);assert.deepEqual(failures,[]);
   row.status='passed';save();
  }catch(error){
   row.status='failed';row.error=error.stack||error.message;
   if(benchmark){row.catalogueCalls=catalogueCalls;receipt.failedCatalogueSample=row;}
   row.generationCounts=counts(transport,tv);
   row.supplierActions={tv:tv.calls.map(call=>call.action),anex:transport.calls.filter(call=>call.url===intPath).map(call=>call.action)};save();
   await page.screenshot({path:path.join(directory,id+'-failure.png')}).catch(()=>{});
   fs.writeFileSync(path.join(directory,id+'-failure.html'),await page.content().catch(()=>''));throw error;
  }finally{await context.close();}
 }
 let historyBrowser;
 try{
  const variants=await catalogueTiming.prepareCataloguePair(receipt.servedHashes[catalogueTiming.dataURL]);
  if(variants){
   const report={baseline:catalogueTiming.baseline,variants:variants.map(({code,...pin})=>pin),delaysMs:catalogueTiming.delays,
    boundary:'navigation start to the visible and enabled Find button at its first animation frame',warmups:1,pairsPerScreen:7,measurements:[],unavailable:[]};
   receipt.catalogueTiming=report;save();
   for(const viewport of viewports.filter(viewport=>[430,1280].includes(viewport.width))){
    for(const controlled of [false,true]){
     for(let pair=0;pair<=report.pairsPerScreen;pair++){
      const order=pair%2?variants.slice().reverse():variants;
      const sample={viewport,controlled,pair,warmup:pair===0,order:order.map(variant=>variant.name)};
      for(const variant of order){
       const name='catalogue-'+(controlled?'controlled':'normal')+'-'+pair+'-'+variant.name;
       sample[variant.name]=await runCase(browser,{viewport,benchmark:{name,variant,controlled}});
      }
      report.measurements.push(sample);save();
     }
    }
    for(const variant of variants){
     const result=await runCase(browser,{viewport,benchmark:{name:'catalogue-unavailable-'+variant.name,variant,unavailable:true}});
     report.unavailable.push({viewport,variant:variant.name,...result});save();
    }
   }
   console.log('PASS paired HOME catalogue timing: exact compiled assets; saved meals, options and catalogue failure');
  }
  for(const viewport of viewports)await runCase(browser,{viewport});
  for(const platform of ['telegram','max'])await runCase(browser,{viewport:{width:430,height:932},platform});
  await runCase(browser,{viewport:{width:1280,height:720},platform:'max',selectedPair:true});
  for(const retained of ['expired','missing','mismatch'])await runCase(browser,{viewport:{width:390,height:740},platform:'telegram',retained});
  // Default Playwright disables BFCache. Only this bounded extra launch omits
  // that flag; actual pageshow and Navigation Timing explain the observed Back.
  // No synthetic pageshow, response-header changes, or CDP cache intervention.
  historyBrowser=await launchHistoryBrowser();assert.equal(historyBrowser.version(),browser.version());
  await runCase(historyBrowser,{viewport:{width:430,height:932},historyCache:true});
  await runCase(historyBrowser,{viewport:{width:430,height:932},platform:'telegram',historyCache:true});
  assert.deepEqual(failures,[]);receipt.status='passed';save();
  console.log('PASS native compiled HOME 390/430/1280 + 390x740: actual root form, first-viewport Find, family0/12, one handoff/search, Back/cold reload, Telegram/MAX mocked end-to-end; return retains selection without another search; BFCache observation retained');
 }catch(error){receipt.status='failed';receipt.error=error.message;save();throw error;}
 finally{if(historyBrowser)await historyBrowser.close();}
};
