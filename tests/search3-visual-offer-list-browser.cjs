// Exercise the actual compiled lazy owner through the isolated offline PHP entry.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const root=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||path.join(__dirname,'../v2'));
const base='/_preview/search3-next-candidate/',evidence=path.resolve('visual-live-evidence/offer-list-lazy');fs.mkdirSync(evidence,{recursive:true});
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://fixture');if(!url.pathname.startsWith(base)){res.writeHead(403).end();return;}
 const local=path.resolve(root,url.pathname.slice(base.length)||'index.php');if(!local.startsWith(root+'/')){res.writeHead(403).end();return;}
 const file=fs.existsSync(local)&&fs.statSync(local).isDirectory()?path.join(local,'index.php'):local;
 if(!fs.existsSync(file)){res.writeHead(404).end();return;}
 if(file.endsWith('.php')){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php"; $_GET["scenario"]="mixed"; include $argv[1];',file]));return;}
 res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':'application/octet-stream');res.end(fs.readFileSync(file));
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;const receipts=[];
 try{browser=await chromium.launch({headless:true});for(const width of [360,390,430,768,1280]){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[],forbidden=[],geometry=[];let requests=0,release,pending;
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{
   const url=new URL(route.request().url());if(url.hostname!=='127.0.0.1'){if(route.request().resourceType()!=='image')forbidden.push(url.pathname);await route.abort();return;}
   if(url.pathname.endsWith('/offer-list-v1.js')){requests++;assert.match(url.search,/^\?v=[0-9a-f]{12}$/,'PHP binds the actual cold asset hash');if(requests===1){await route.abort();return;}if(requests===2){pending=true;await new Promise(resolve=>{release=resolve;});}}
   await route.continue();
  });
  const shot=async name=>{
   await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(80);
   const record=await page.evaluate(()=>({viewport:innerWidth,pageWidth:document.documentElement.scrollWidth,modalOpen:document.querySelector('#modal').open,modalWidth:document.querySelector('#modal').clientWidth,modalScrollWidth:document.querySelector('#modal').scrollWidth,steps:[...document.querySelectorAll('#modal .selection-steps li')].map(step=>{const style=getComputedStyle(step),number=step.querySelector('span'),box=number.getBoundingClientRect();return {display:style.display,gap:parseFloat(style.gap),numberWidth:box.width,numberHeight:box.height};})}));
   assert(record.pageWidth<=width+1,name+' fits the viewport');if(record.modalOpen)assert(record.modalScrollWidth<=record.modalWidth+1,name+' has no horizontal dialog overflow');
   for(const step of record.steps)assert(step.display==='flex'&&step.gap>=5&&step.numberWidth>=20&&Math.abs(step.numberWidth-step.numberHeight)<1,name+' retains separated, numbered selection steps');
   geometry.push({name,...record});await page.screenshot({path:path.join(evidence,`${name}-${width}.png`)});
  };
  try{
   await page.goto(`http://127.0.0.1:${server.address().port}${base}visual-search/?scenario=mixed`);
   const trigger=page.locator('.hotel-card [data-action="all-offers"]').first();await trigger.waitFor();assert.equal(requests,0,'form/results leave the offer-list renderer cold');
   assert(await page.locator('#calendar-preview').isVisible(),'price calendar is open');assert.equal(await page.locator('#price-strip .date-price').count(),7);await shot('results');
   const photo=await page.locator('.hotel-card').first().evaluate(card=>{const image=card.querySelector('.hotel-image'),box=image.getBoundingClientRect();return {card:card.clientWidth,width:box.width,height:box.height,natural:image.naturalWidth};});
   if(width<=760){assert(photo.width>=photo.card-4,'approved mobile photo spans the card');assert(Math.abs(photo.width/photo.height-1.8)<0.03,'approved mobile photo ratio 1.8');}assert(photo.natural>0,'saved demo photo is available');
   await page.locator('.hotel-card').first().scrollIntoViewIfNeeded();await shot('card');
   await page.locator('#applied-search [data-action="edit-search"]:visible,#compact-search .secondary[data-action="top"]:visible').first().click();await page.locator('#search-form').waitFor({state:'visible'});await page.locator('#search-form').scrollIntoViewIfNeeded();await shot('form');
   for(const name of ['departure','destination','dates','nights','guests','meals','budget','form-filters']){
    await page.locator(`#search-form [data-action="${name}"]`).click();await page.locator('#modal').waitFor({state:'visible'});await shot(name);
    if(name==='guests'){
     await page.locator('[data-action="children-plus"]').click();await page.locator('[data-action="child-age"]').first().click();await shot('child-age');await page.locator('#modal-back').click();
    }
    if(name==='form-filters'){await page.locator('#modal [data-action="stars"]').click();await shot('stars');await page.locator('#modal-back').click();}
    await page.locator('#modal .modal-header [data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);
   }
   await page.locator('[data-action="cancel-search-edit"]').click();assert.equal(requests,0,'all form states leave the exact-list owner cold');
   if(width<=1100){await page.locator('.results-toolbar [data-action="filters"]').click();await shot('filters');await page.locator('.filter-operator-group>.filter-section-toggle').click();assert.equal(await page.locator('.filter-top h3').textContent(),'Туроператор');assert(await page.locator('#filter-detail-back').evaluate(el=>document.activeElement===el));assert.equal(await page.locator('#apply-filters').isVisible(),false);await shot('operators');await page.locator('#filter-detail-back').click();assert.equal(await page.locator('.filter-top h3').textContent(),'Фильтры');assert(await page.locator('.filter-operator-group>.filter-section-toggle').evaluate(el=>document.activeElement===el));await page.locator('#filter-panel [data-action="close-filters"]').click();}
   else{await page.locator('#filters').scrollIntoViewIfNeeded();await shot('filters');}
   await trigger.click();await page.locator('[data-action="retry-offer-list"]').waitFor();assert.equal(requests,1);
   await page.locator('[data-action="retry-offer-list"]').click();while(!pending)await page.waitForTimeout(20);assert.equal(requests,2);
   await page.locator('#modal .modal-header [data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);release();await page.waitForFunction(()=>!!window.AnyTourOfferList?.create);
   assert.equal(await page.locator('#modal').evaluate(el=>el.open),false,'late load cannot reopen a closed modal');
   await page.evaluate(()=>{const owner=window.AnyTourOfferList,create=owner.create;window.__ownerInventories=0;owner.create=context=>create({...context,hotelOffers:hotel=>{window.__ownerInventories++;const rows=context.hotelOffers(hotel);if(!rows.length)return rows;const seed=rows[0],placeholder={...seed,key:seed.key+'-request-placeholder',room:' on request ',meal:'On Request'},extra=Array.from({length:11},(_,i)=>({...seed,key:seed.key+'-pagination-demo-'+i,total:seed.total+i+1}));return [...rows,placeholder,...extra];}});});
   await trigger.click();await page.locator('.grouped-offer').first().waitFor();assert.equal(requests,2,'warm open reuses the cold owner');
   assert.equal(await page.locator('[data-action="offer-group"]').count(),0,'approved exact offers appear without room disclosures');await shot('offers');
   const placeholder=page.locator('[data-offer-key$="-request-placeholder"]');await placeholder.waitFor();const placeholderText=await placeholder.textContent();
   assert.match(placeholderText,/Номер уточняется/);assert.match(placeholderText,/Питание уточняется/);assert.doesNotMatch(placeholderText,/on request/i,'supplier placeholder is not exposed as user-facing copy');
   assert.match(await page.locator('#offer-room option[value=" on request "]').textContent(),/^Номер уточняется · 1 тур$/);
   assert.match(await page.locator('#offer-meal option[value="On Request"]').textContent(),/^Питание уточняется · 1 тур$/);
   await page.locator('#offer-room').selectOption(' on request ');assert.equal(await page.locator('#all-offers-list .grouped-offer').count(),1,'raw room identity still drives exact filtering');assert.equal(await placeholder.count(),1);await page.locator('#offer-room').selectOption('');
   const list=page.locator('#all-offers-list'),more=page.locator('[data-action="group-more"]').first();assert(await more.count(),'controlled pagination demo exposes a bounded page');
   const before=await list.locator('.grouped-offer').count();
   await list.evaluate(body=>{const rows=body.querySelectorAll('.grouped-offer');window.__shownKeys=[...rows].map(row=>row.dataset.offerKey);window.__cleanRow=rows[0];window.__dirtyRow=rows[1];rows[1].setAttribute('data-external-dirty','1');rows[1].querySelector('strong').textContent='DIRTY';});
   await more.click();await page.waitForFunction(before=>document.querySelectorAll('#all-offers-list .grouped-offer').length>before,before);
   const pagination=await list.evaluate((body,before)=>{const rows=body.querySelectorAll('.grouped-offer'),active=document.activeElement;return {before,after:rows.length,cleanRetained:rows[0]===window.__cleanRow,dirtyReplaced:rows[1]!==window.__dirtyRow,dirtyRestored:!rows[1].hasAttribute('data-external-dirty')&&!rows[1].textContent.includes('DIRTY'),focusedNew:active?.dataset.action==='offer'&&!window.__shownKeys.includes(active.closest('.grouped-offer')?.dataset.offerKey)};},before);
   assert(pagination.after>before&&pagination.after<=before+8);assert.equal(pagination.cleanRetained,true);assert.equal(pagination.dirtyReplaced,true);assert.equal(pagination.dirtyRestored,true);assert.equal(pagination.focusedNew,true,'show-more focuses a newly revealed exact offer');
   if(!await page.locator('.offer-filter-disclosure').evaluate(el=>el.open))await page.locator('.offer-filter-disclosure>summary').click();
   const room=page.locator('#offer-room'),choices=await room.locator('option').count();assert(choices>=3);const selected=await room.locator('option').nth(1).getAttribute('value');await room.selectOption(selected);assert(await list.locator('.grouped-offer').count()>0);
   await page.evaluate(()=>window.__ownerInventories=0);await page.locator('#modal .modal-header [data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);await page.waitForTimeout(150);await page.goForward();await room.waitFor();assert.equal(await room.inputValue(),selected);assert.equal(await page.evaluate(()=>window.__ownerInventories),0,'warm Forward uses the validated history inventory');assert.equal(requests,2);
   await list.locator('[data-action="offer"]').first().click();const tourPrice=page.locator(width<=760?'#modal-footer .footer-total>strong':'#detail-total');await tourPrice.waitFor();assert.match(await tourPrice.textContent(),/\d.*₽/,'the selected offer has a visible total');await shot('tour');
   await page.locator('[data-action="start-tour-flights"]').click();await page.locator('[data-action="apply-flight"]').waitFor();await shot('flights');
   await page.locator('[data-action="apply-flight"]').click();await page.locator('#prototype-lead-form').waitFor();await shot('application');
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);receipts.push({width,initial_downloads:0,failed_downloads:1,retry_downloads:1,warm_downloads:0,late_closed_modal_render:false,flat_exact_offers:true,pagination,history_room_restored:true,history_owner_inventory_calls:0,saved_demo_photo:photo,geometry,supplier_requests:0,lead_requests:0,physicalSafari:false});
  }finally{release?.();await context.close();}
 }}finally{await browser?.close();await new Promise(resolve=>server.close(resolve));}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify(receipts,null,2)+'\n');console.log('PASS compiled approved interface and cold offer-list browser',JSON.stringify(receipts));
})().catch(error=>{console.error(error);process.exitCode=1;});
