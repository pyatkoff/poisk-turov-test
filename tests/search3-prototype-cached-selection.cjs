'use strict';
// Fictional HTTP fixtures only. A saved offer must not become a broad search or a quote.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../v2'),base='/_preview/search3-local-candidate/';
const evidence=process.env.SEARCH3_PROTOTYPE_EVIDENCE||'/tmp/search3-prototype-cached-selection';fs.mkdirSync(evidence,{recursive:true});
const day=n=>new Date(Date.now()+n*86400000).toISOString().slice(0,10);
const profile={id:1,catalog:'anytour',revision:1,name:'Отель для проверки «Море»',category:5,rating:4.7,region:{name:'Анталья'},country:{name:'Турция'},description:'Описание из собственной базы.',images:[],hotelInformation:{}};
const exactDay=day(10),nights=9;
function stored(provider){return {provider,legacyHotelId:101,price:110000,currency:'RUB',listing:{schema_version:1,provider,operator:{raw:'ANEX'},identity:{search_ref_digest:'a'.repeat(64),offer_ref_digest:'b'.repeat(64),provider_hotel_ref_digest:'c'.repeat(64)},tour:{checkin:exactDay,nights,party:{adults:2,children:2,child_ages:[0,17]},meal:{raw:'AI'},room:{raw:'STANDARD'},placement:{raw:'DBL+2CH'}},listingPriceReady:true,listingPrice:{amount:'110000',currency:'RUB'},currency:'RUB',selection_state:'refresh_required',booking_enabled:false}};}
const currentTour={id:'fresh-tour',price:125000,date:exactDay,nights,adults:2,childs:2,meal:{name:'AI'},roomType:'SUPERIOR',placement:'DBL+2CH',operator:{name:'Coral Travel'}};
const server=http.createServer((req,res)=>{const pathname=new URL(req.url,'http://localhost').pathname;let file=path.join(root,pathname.slice(base.length));if(pathname.endsWith('/'))file=path.join(file,'index.html');if(!pathname.startsWith(base)||!file.startsWith(root)||!fs.existsSync(file)){res.writeHead(404);return res.end();}res.setHeader('Content-Type',({'.js':'text/javascript','.css':'text/css','.html':'text/html','.svg':'image/svg+xml','.woff2':'font/woff2','.png':'image/png'})[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port,browser=await chromium.launch({headless:true});
 try{for(const [width,provider] of [[390,'anex'],[1440,'andromeda']]){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),calls=[],starts=[],dbCalls=[],errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await context.route('**/*',async route=>{
   const url=new URL(route.request().url()),json=data=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(data)});
   if(url.pathname==='/data/departures-v1.php')return json({ok:true,items:[{id:1,name:'Москва'},{id:2,name:'Казань'}]});
   if(url.pathname.endsWith('/hotel-details-read-v1.php'))return json({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:['101'],missingLegacyIds:[],items:[profile],links:[{legacyHotelId:101,anytourHotelId:1}]});
   if(url.pathname.endsWith('/search3-local-results-read-v1.php')){const p=route.request().postDataJSON().params;dbCalls.push(p);return json({ok:true,data:{source:'anytour-db-first-results-v1',scopeVersion:1,scope:{scopeVersion:1,...p},scopeDigest:'d'.repeat(64),selectionAuthority:false,hotels:[{anytourHotelId:1,hotel:profile,offers:[stored(provider)]}]}});}
   if(url.pathname==='/api-v2.php'){
    const action=url.searchParams.get('action');calls.push(action);
    if(action==='meals')return json([{id:7,name:'AI'}]);
    if(action==='countries')return json([{id:4,name:'Турция'}]);
    if(action==='search_start'){starts.push(url.searchParams);return json({searchId:122+starts.length});}
    if(action==='search_status')return json({progress:100,status:'complete'});
    if(action==='search_results')return json(starts.length===1||provider==='andromeda'?[]:[{id:101,provider:'tourvisor',tours:[currentTour,{...currentTour,id:'wrong-date',date:day(11)},{...currentTour,id:'wrong-nights',nights:7}]}]);
    if(action==='tour'){assert.equal(url.searchParams.get('tourId'),'fresh-tour');return json(currentTour);}
    if(action==='flights')return json([{isDefault:true,price:{value:125000},fuelCharge:0,forward:[],backward:[]}]);
    throw new Error('Unexpected API action '+action);
   }
   if(url.origin!==origin)throw new Error('Unexpected external URL '+url.href);
   return route.continue();
  });
  const query=new URLSearchParams({origin:'Казань',country:'4',from:day(8),to:day(14),minNights:'7',maxNights:'10',adults:'2',ages:'0,17',stars:'5',min:'80000',max:'180000'});
  await page.goto(origin+base+'prototype-search/?'+query);await page.locator('.search-submit:not([disabled])').waitFor();
  await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelector('.hotel-card')&&document.querySelector('#search-status').hidden);
  await page.locator('.hotel-price [data-action="all-offers"]').click();
  const saved=page.locator('#modal [data-action="offer"]').first();
  assert.match(await saved.textContent(),/Смотреть условия/);
  await page.locator('[data-action="offer-view"][data-value="compare"]').click();
  assert.match(await page.locator('#modal-footer [data-action="offer"]').textContent(),/Смотреть условия/);
  assert.match(await page.locator('#modal-footer [data-action="offer-flights"]').textContent(),/Условия тура/);
  await page.locator('[data-action="offer-view"][data-value="list"]').click();
  const before=calls.length;await saved.click();
  await page.locator('[data-action="refresh-hotel"]').waitFor();
  assert.equal(calls.length,before,'Inspecting a saved direct-provider row uses no supplier action');
  assert.equal(await page.locator('[data-action="confirm-tour"]').count(),0,'Saved source has no confirm or lead action');
  await page.locator('#modal-back').click();assert.equal(calls.length,before,'Cancel is supplier-free');
  await saved.click();await page.locator('[data-action="refresh-hotel"]').click();
  await page.waitForFunction(()=>document.querySelector('#modal').open===false&&(document.querySelector('#search-status').hidden||!document.querySelector('#search-status .search-progress-spinner')));
  assert.equal(starts.length,2,'One intentional action starts exactly one fresh search');
  const p=starts[1];
  assert.equal(p.get('dateFrom'),exactDay,'Refresh keeps the clicked departure date');
  assert.equal(p.get('dateTo'),exactDay,'Refresh does not widen the date window');
  assert.deepEqual([p.get('nightsFrom'),p.get('nightsTo')],['9','9'],'Refresh keeps the concrete duration');
  assert.deepEqual([p.get('departureId'),p.get('countryId'),p.get('adults')],['2','4','2']);
  assert.deepEqual(p.getAll('childs[]'),['0','17'],'Child ages survive');
  assert.deepEqual(p.getAll('hotelIds[]'),['101'],'Use canonical accepted legacy IDs, never cached/provider offer IDs');
  assert.deepEqual([p.get('hotelCategory'),p.get('priceFrom'),p.get('priceTo')],['5','80000','180000'],'Retain existing filters');
  assert.equal(calls.includes('tour')||calls.includes('flights'),false,'Fresh results are not automatically selected or quoted');
  const href=new URL(page.url());assert.equal(href.searchParams.get('from'),exactDay);assert.equal(href.searchParams.get('maxNights'),'9');
  assert.match(await page.locator('#applied-search').textContent(),/9 ночей/);
  assert.match(await page.locator('#destination-label').textContent(),/Море/);
  await page.locator('.hotel-price [data-action="all-offers"]').click();
  assert.equal(await page.locator('[data-key="tourvisor%3Awrong-date"]').count(),0);
  assert.equal(await page.locator('[data-key="tourvisor%3Awrong-nights"]').count(),0);
  const savedRows=page.locator(`#modal [data-action="offer"][data-key^="${provider}%3A"]`);
  assert.equal(await savedRows.count(),1,'The saved row remains explicitly distinct after refresh');
  {
   assert.match(await savedRows.first().textContent(),/Смотреть условия/);
   await savedRows.first().click();
   assert.match(await page.locator('.saved-tour-notice').textContent(),/на эту дату и 9 ночей/);
   assert.match(await page.locator('[data-action="refresh-hotel"]').textContent(),/Найти актуальные туры/);
   await page.screenshot({path:path.join(evidence,`cached-detail-${width}.png`),fullPage:false});
   await page.locator('#modal-back').click();
  }
  if(provider==='andromeda'){
   await page.locator('[data-action="close-modal"]').click();
   assert.match(await page.locator('#search-status').textContent(),/Актуальные варианты пока не найдены/);
   assert.match(await page.locator('#search-status').textContent(),/Сохранённые цены ниже ещё требуют проверки/);
   await page.screenshot({path:path.join(evidence,`exact-refresh-empty-${width}.png`),fullPage:false});
   assert.equal(starts.length,2,'No automatic retry when current offers are absent');
   assert.equal(calls.includes('tour')||calls.includes('flights'),false);
   await page.locator('[data-action="retry-search"]').click();
   await page.waitForFunction(()=>!document.querySelector('#search-status .search-progress-spinner'));
   assert.equal(starts.length,3,'Only the explicit retry adds another search');
   assert.equal(starts[2].get('dateFrom'),exactDay);assert.equal(starts[2].get('nightsTo'),'9');
   assert.equal(await page.locator('#search-status').isVisible(),true,'Retry retains the no-current-offers state');
   assert.match(await page.locator('#search-status').textContent(),/Актуальные варианты пока не найдены/);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
   assert.deepEqual(errors,[]);await context.close();console.log(JSON.stringify({width,provider,starts:starts.length,status:'empty-current-passed'}));continue;
  }
  const current=page.locator('#modal [data-action="offer"][data-key="tourvisor%3Afresh-tour"]');
  await current.locator('xpath=ancestor::section[contains(@class,"offer-group")]').locator('.offer-group-heading').click();
  await page.screenshot({path:path.join(evidence,`exact-refresh-${width}.png`),fullPage:false});
  await current.click();
  await page.locator('[data-action="confirm-tour"]:not([disabled])').waitFor();
  assert.match(await page.locator('#detail-total').textContent(),/125\s?000/,'Fresh selection uses its own price, not the saved110000');
  assert.match(await page.locator('#modal-body').textContent(),/SUPERIOR/,'Different returned conditions remain explicit');
  assert.equal(calls.filter(x=>x==='tour').length,1);assert.equal(calls.filter(x=>x==='flights').length,1);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);assert.deepEqual(errors,[]);
  console.log(JSON.stringify({width,provider,starts:starts.length,dbReads:dbCalls.length,status:'passed'}));await context.close();
 }}finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
