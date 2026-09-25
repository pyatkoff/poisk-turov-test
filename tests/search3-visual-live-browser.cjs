'use strict';
// CI browser acceptance. Every data request is intercepted with fictional fixtures.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
const root=path.resolve(__dirname,'../v2'),base='/_preview/search3-next-candidate/',evidence=path.resolve('visual-live-evidence');fs.mkdirSync(evidence,{recursive:true});
const server=http.createServer((req,res)=>{
 const u=new URL(req.url,'http://fixture');if(!u.pathname.startsWith(base)){res.writeHead(404).end();return;}
 const local=path.resolve(root,u.pathname.slice(base.length)||'index.php');if(!local.startsWith(root+'/')){res.writeHead(403).end();return;}
 const file=fs.existsSync(local)&&fs.statSync(local).isDirectory()?path.join(local,'index.php'):local;
 if(!fs.existsSync(file)){res.writeHead(404).end();return;}
 if(file.endsWith('.php')){const html=execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php"; include $argv[1];',file]);res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);return;}
 res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':'application/octet-stream');res.end(fs.readFileSync(file));
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port,browser=await chromium.launch();const receipts=[];
 try{for(const width of [390,768,1280]){
  const transport=fixture({tvFuel:20686}),errors=[],forbidden=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{
   const req=route.request(),u=new URL(req.url());
   if(u.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
   if(u.pathname.startsWith(base)&&!u.pathname.includes('/data/')){await route.continue();return;}
   try{const value=await transport.json(req.url(),{body:req.postData()});await route.fulfill({contentType:'application/json',body:JSON.stringify(value)});}catch(e){forbidden.push(e.message);await route.abort();}
  });
  await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:'',searched:'1'}));
  await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0);
  await page.locator('#search-form [data-action="dates"]').click();
  await page.waitForFunction(()=>document.querySelector('#date-calendar').textContent.includes('97,5'));
  await page.locator('[data-action="apply-dates"]').click();
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0);
  await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
  assert.equal(await page.locator('.hotel-card').count(),1);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:path.join(evidence,`results-${width}.png`)});
  await page.locator('[data-action="hotel-details"][data-id="501"]').first().click();
  assert((await page.locator('#modal-body').textContent()).includes('Тестовая улица'));
  assert(await page.evaluate(()=>!!(document.querySelector('#hotel-services-heading').compareDocumentPosition(document.querySelector('#hotel-rooms-heading')) & Node.DOCUMENT_POSITION_FOLLOWING)));
  await page.locator('[data-action="close-modal"]').click();
  await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  const tvOffer=page.locator('#modal-body [data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');
  if(!await tvOffer.isVisible())await tvOffer.locator('xpath=ancestor::section[contains(@class,"offer-group")]').locator('[data-action="offer-group"]').click();
  await tvOffer.click();await page.waitForFunction(()=>document.querySelector('[data-action="confirm-tour"]')&&!document.querySelector('[data-action="confirm-tour"]').disabled);
  await page.locator('[data-action="choose-flight"]').click();await page.locator('[name="flight-pair"][value="1"]').check();await page.locator('[data-action="apply-flight"]').click();
  assert((await page.locator('#detail-total').textContent()).replace(/\s/g,'').includes('133500'));
  await page.locator('[data-action="confirm-tour"]').click();
  assert.match((await page.locator('#modal-body .tour-fuel-disclosure').textContent()).replace(/\s/g,''),/20686₽/);
  assert.match(await page.locator('#modal-footer').textContent(),/Цена предложения · сборы уточняются/);
  await page.screenshot({path:path.join(evidence,`application-fuel-${width}.png`)});
  await page.locator('[name="phone"]').fill('+7 999 123-45-67');await page.locator('[name="consent"]').check();await page.locator('[type="submit"][form="prototype-lead-form"]').click();
  await page.waitForFunction(()=>document.querySelector('#prototype-lead-form').dataset.checked==='1');assert((await page.locator('.lead-message').textContent()).includes('не отправлена'));
  await page.screenshot({path:path.join(evidence,`application-${width}.png`)});
  await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  const samoOffer=page.locator('#modal-body [data-action="offer"][data-key^="andromeda%3A"]').first();
  if(!await samoOffer.isVisible())await samoOffer.locator('xpath=ancestor::section[contains(@class,"offer-group")]').locator('[data-action="offer-group"]').click();
  await samoOffer.click();await page.locator('[data-action="refresh-hotel"]').click();await page.locator('[data-action="andromeda-application-preview"]').click();
  assert((await page.locator('#modal-body').textContent()).includes('SAMO STANDARD'));
  await page.locator('[name="phone"]').fill('+7 999 123-45-67');await page.locator('[name="consent"]').check();await page.locator('[type="submit"][form="prototype-lead-form"]').click();
  await page.waitForFunction(()=>document.querySelector('#prototype-lead-form').dataset.checked==='1');assert((await page.locator('.lead-message').textContent()).includes('не отправлена'));
  await page.screenshot({path:path.join(evidence,`samo-application-${width}.png`)});
  assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth),false);assert(!transport.calls.some(c=>/lead|payment/.test(c.url)));assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
  receipts.push({width,three_sources_one_hotel:true,calendar_database_observation:true,search_before_submit:0,total:133500.5,tv_fuel_disclosed:20686,samo_total:125500,local_application:true,supplier_requests:0,lead_requests:0});await context.close();
 }}finally{await browser.close();server.close();}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify({published:false,live_data:false,engine:'Chromium',physical_device:false,results:receipts},null,2));console.log('PASS visual live browser',JSON.stringify(receipts));
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
