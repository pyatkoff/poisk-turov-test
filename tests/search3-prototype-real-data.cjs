'use strict';
// Isolated presentation acceptance. All HTTP fixtures below are fictional; no supplier requests.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../v2'),base='/_preview/search3-local-candidate/';
const evidence=process.env.SEARCH3_PROTOTYPE_EVIDENCE||'/tmp/search3-prototype-evidence';fs.mkdirSync(evidence,{recursive:true});
const day=n=>new Date(Date.now()+n*86400000).toISOString().slice(0,10);
const photo='data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>');
const profiles=[1,2].map(id=>({id,catalog:'anytour',revision:1,name:id===1?'Отель для проверки «Море»':'Отель для проверки «Сад»',category:id===1?5:4,rating:id===1?4.7:4.2,region:{name:'Анталья'},country:{name:'Турция'},description:'Описание отеля из собственной базы.',images:['http://127.0.0.1/test-photo.svg'],hotelInformation:{}}));
const tours=[{id:'exact-1',price:120000,date:day(8),nights:7,adults:2,childs:0,meal:{name:'AI'},roomType:'STANDARD',placement:'DBL',operator:{name:'ANEX'},fuelCharge:null},{id:'exact-2',price:133000,date:day(8),nights:7,adults:2,childs:0,meal:{name:'HB'},roomType:'SUPERIOR',placement:'DBL',operator:{name:'Coral Travel'},fuelCharge:0}];
const segment=(number,time)=>({company:{name:'Тестовая авиакомпания'},number,departure:{date:day(8),time,port:{name:'Москва',id:'SVO'}},arrival:{date:day(8),time:'14:00',port:{name:'Анталья',id:'AYT'}},baggage:20,carryOn:'5 кг'});
const variants=[{isDefault:true,price:{value:120000},fuelCharge:null,forward:[segment('TT 111','10:00')],backward:[segment('TT 112','12:00')]},{price:{value:133500.5},fuelCharge:0,forward:[segment('TT 211','14:00')],backward:[segment('TT 212','16:00')]}];
function stored(p){return {provider:'tourvisor',legacyHotelId:101,price:110000,currency:'RUB',listing:{schema_version:1,provider:'tourvisor',operator:{raw:'ANEX'},identity:{search_ref_digest:'a'.repeat(64),offer_ref_digest:'b'.repeat(64),provider_hotel_ref_digest:'c'.repeat(64)},tour:{checkin:p.dateFrom,nights:p.nightsFrom,party:{adults:p.adults,children:p.childs.length,child_ages:p.childs},meal:{raw:'AI'},room:{raw:'STANDARD'},placement:{raw:'DBL'}},listingPriceReady:true,listingPrice:{amount:'110000',currency:'RUB'},currency:'RUB',selection_state:'refresh_required',booking_enabled:false}};}
const server=http.createServer((req,res)=>{const pathname=new URL(req.url,'http://localhost').pathname;let file=path.join(root,pathname.slice(base.length));if(pathname.endsWith('/'))file=path.join(file,'index.html');if(!pathname.startsWith(base)||!file.startsWith(root)||!fs.existsSync(file)){res.writeHead(404);return res.end();}const ext=path.extname(file);res.setHeader('Content-Type',({'.js':'text/javascript','.css':'text/css','.html':'text/html','.svg':'image/svg+xml','.woff2':'font/woff2','.png':'image/png'})[ext]||'application/octet-stream');res.end(fs.readFileSync(file));});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port;
 const browser=await chromium.launch({headless:true});
 try{for(const width of [390,1440]){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[],calls=[],dbCalls=[];
  let releaseCountries;const countriesReady=new Promise(resolve=>{releaseCountries=resolve});let countriesBlocked=true;
  page.on('pageerror',error=>{errors.push(error.message);console.error('browser:',error.message);});
  await context.route('**/*',async route=>{
   const url=new URL(route.request().url());
   const json=data=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(data)});
   if(url.pathname==='/test-photo.svg')return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});
   if(url.pathname==='/data/departures-v1.php')return json({ok:true,items:[{id:1,name:'Москва'},{id:2,name:'Казань'}]});
   if(url.pathname.endsWith('/hotel-details-read-v1.php')){const ids=url.searchParams.getAll('legacyHotelIds[]');return json({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:ids,missingLegacyIds:[],items:profiles.filter(p=>ids.includes(String(100+p.id))),links:ids.map(id=>({legacyHotelId:Number(id),anytourHotelId:Number(id)-100}))});}
   if(url.pathname.endsWith('/search3-local-results-read-v1.php')){const p=route.request().postDataJSON().params;dbCalls.push(p);return json({ok:true,data:{source:'anytour-db-first-results-v1',scopeVersion:1,scope:{scopeVersion:1,...p},scopeDigest:'c'.repeat(64),selectionAuthority:false,hotels:[{anytourHotelId:1,hotel:profiles[0],offers:[stored(p)]}]}});}
   if(url.pathname==='/api-v2.php'){
    const action=url.searchParams.get('action');calls.push(action);
    if(action==='meals')return json([{id:7,name:'AI'},{id:5,name:'HB'}]);
    if(action==='countries'){if(countriesBlocked)await countriesReady;return json([{id:4,name:'Турция'},{id:5,name:'Египет'}]);}
    if(action==='search_start')return json({searchId:123});
    if(action==='search_status')return json({progress:100,status:'complete'});
    if(action==='search_results')return json([{id:101,provider:'tourvisor',tours}, {id:102,provider:'tourvisor',tours:[{...tours[0],id:'exact-3',price:99000}]}]);
    if(action==='tour'){const t=tours.find(t=>t.id===url.searchParams.get('tourId'));return json({...t,hotel:{name:profiles[0].name}});}
    if(action==='flights')return json(variants);
    throw new Error('Unexpected API action '+action);
   }
   if(url.origin!==origin)throw new Error('Unexpected external URL '+url.href);
   return route.continue();
  });
  await page.goto(origin+base+'prototype-search/');
  await page.locator('[data-action="dates"]').click();
  await page.getByText('Цены пока недоступны. Даты можно выбрать без цены.').waitFor();
  const earlyDate=day(10);await page.locator(`[data-action="day-pick"][data-date="${earlyDate}"]`).click();
  countriesBlocked=false;releaseCountries();
  await page.locator('.search-submit:not([disabled])').waitFor({timeout:10000}).catch(async error=>{console.error(await page.locator('#cards').textContent());throw error;});
  await page.waitForFunction(()=>document.querySelectorAll('.month-day.is-cheap').length>0);
  assert.equal(await page.locator(`[data-action="day-pick"][data-date="${earlyDate}"]`).getAttribute('aria-pressed'),'true','Catalog retry preserves the early date draft');
  assert.equal(await page.locator('.calendar-legend span').first().textContent(),'Цены из базы за всех, от · пробелы означают отсутствие сохранённой цены','Open calendar retries after catalogs load');
  await page.screenshot({path:path.join(evidence,`calendar-retry-${width}.png`),fullPage:true});
  await page.locator('[data-action="close-modal"]').click();await page.waitForTimeout(100);
  assert.equal(await page.locator('#destination-label').textContent(),'Турция');
  await page.screenshot({path:path.join(evidence,`form-${width}.png`),fullPage:true});
  await page.locator('[data-action="dates"]').click();await page.waitForFunction(()=>document.querySelectorAll('.month-day.is-cheap').length>0);
  assert.equal(await page.locator('.month-day:not(.is-cheap):not([disabled])').first().isEnabled(),true,'A day without a saved price remains selectable');
  assert.equal(calls.filter(x=>x==='search_start').length,0,'Calendar never starts a supplier search');
  assert.ok(dbCalls.every(p=>p.adults===2&&p.childs.length===0&&p.nightsFrom===7),'Calendar keeps party and duration');
  await page.locator('[data-action="close-modal"]').click();await page.waitForTimeout(100);
  await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelectorAll('.hotel-card').length===2);
  await page.waitForTimeout(100);assert.equal(await page.locator('.hotel-card').count(),2);
  await page.screenshot({path:path.join(evidence,`results-${width}.png`),fullPage:true});
  const callsBefore=calls.length;
  if(width<1100)await page.locator('.drawer-trigger').click();
  await page.locator('#filter-panel [data-action="star"][data-value="5"]').click();
  if(width<1100)await page.locator('[data-action="apply-filters"]').click();
  assert.equal(await page.locator('.hotel-card').count(),1,'Exact category chip filters cards');assert.equal(calls.length,callsBefore,'Local filter does not repeat supplier search');
  await page.locator('.hotel-price [data-action="all-offers"]').first().click();
  const real=page.locator('#modal [data-action="offer"][data-key="tourvisor%3Aexact-1"]').first();await real.click();
  await page.waitForFunction(()=>document.querySelector('[data-action="choose-flight"]'));
  assert.match(await page.locator('#modal-body').textContent(),/Сбор уточняется/,'Missing fuel is not announced as included');
  await page.locator('[data-action="choose-flight"]').click();await page.locator('input[name="flight-pair"][value="1"]').check();
  assert.match(await page.locator('#flight-total').textContent(),/133\s?500,5/,'Variant price is full tour price with original precision');
  await page.locator('[data-action="apply-flight"]').click();assert.match(await page.locator('#detail-total').textContent(),/133\s?500,5/);
  assert.match(await page.locator('#modal-body').textContent(),/TT 211/,'Chosen real flight retained');
  await page.screenshot({path:path.join(evidence,`tour-${width}.png`),fullPage:true});
  await page.locator('[data-action="confirm-tour"]').click();
  const phone=page.locator('#prototype-lead-form [name="phone"]');
  await phone.fill('+7 999 000-00-00');await page.locator('#prototype-lead-form [name="name"]').fill('Тестовый контакт');
  await page.locator('#prototype-lead-form [name="consent"]').check();await page.locator('#modal-footer [type="submit"]').click();
  assert.equal(await page.locator('#prototype-lead-form').getAttribute('data-checked'),'1','Preview constructs the canonical request locally');
  assert.match(await page.locator('.lead-message').textContent(),/не отправлена/,'Validation never claims delivery');
  await page.screenshot({path:path.join(evidence,`lead-${width}.png`),fullPage:true});
  const flightCalls=calls.filter(x=>x==='flights').length;
  await page.locator('[data-action="selected-tour-details"]').click();
  assert.match(await page.locator('#detail-total').textContent(),/133\s?500,5/,'Returning to details keeps the chosen full price');
  assert.match(await page.locator('#modal-body').textContent(),/TT 211/,'Returning to details keeps the chosen flight');
  assert.equal(calls.filter(x=>x==='flights').length,flightCalls,'Back navigation does not reset the choice with another request');
  await page.locator('#modal-back').click();
  assert.equal(await phone.inputValue(),'+7 999 000-00-00','Contacts survive modal history restoration');
  assert.equal(await page.locator('#prototype-lead-form [name="consent"]').isChecked(),false);
  await page.locator('#prototype-lead-form [name="consent"]').check();await page.locator('#modal-footer [type="submit"]').click();
  assert.equal(await page.locator('#prototype-lead-form').getAttribute('data-checked'),'1','Restored form remains interactive');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'No document overflow');
  const scopeCheck=await page.evaluate(async()=>{const d=window.AnyTourPrototypeData,p=d.params({origin:'Москва',country:'4',from:new Date(Date.now()+86400000).toISOString().slice(0,10),to:new Date(Date.now()+2*86400000).toISOString().slice(0,10),minNights:7,maxNights:7,adults:2,ages:[0,17]}),r={scopeVersion:1,...p};return {same:d.sameScope(p,r),party:d.sameScope(p,{...r,childs:[7,10]}),departure:d.sameScope(p,{...r,departureId:'2'}),cached:await d.quote({cached:true}).then(()=>false,()=>true)};});
  assert.deepEqual(scopeCheck,{same:true,party:false,departure:false,cached:true});
  const primary=await page.evaluate(()=>window.AnyTourPrototypeData.params({origin:'Москва',country:'4',from:new Date(Date.now()+86400000).toISOString().slice(0,10),to:new Date(Date.now()+86400000).toISOString().slice(0,10),minNights:7,maxNights:7,adults:2,ages:[]},['101'],{stars:[4,5],meals:['AI'],min:80000,max:200000}));
  assert.deepEqual([primary.hotelCategory,primary.meal,primary.priceFrom,primary.priceTo,primary.hotelIds],['4','7','80000','200000',['101']],'Primary constraints use existing API fields; exact star set remains local');
  assert.deepEqual(errors,[],'No browser errors');
  console.log(JSON.stringify({width,calls,dbReads:dbCalls.length,status:'passed'}));await context.close();
 }}finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
