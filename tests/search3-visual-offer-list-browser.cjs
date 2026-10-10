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
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[],forbidden=[],geometry=[],density=[],calendarContext=[];let requests=0,release,pending;
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
  const densityFrame=async(state,zoom)=>{
   const record=await page.evaluate(({state,zoom})=>{
    const rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};},fits=(el,box=el)=>{const bounds=box.getBoundingClientRect(),range=document.createRange();range.selectNodeContents(el);return [...range.getClientRects()].every(r=>r.x>=bounds.x-1&&r.right<=bounds.right+1&&r.y>=bounds.y-1&&r.bottom<=bounds.bottom+1);};
    const form=document.querySelector('#search-form'),first=document.querySelector('.hotel-card'),price=first?.querySelector('.starting-price strong'),actions=[...form.querySelectorAll('.search-actions button')].filter(el=>el.getClientRects().length),values=[...form.querySelectorAll('#origin-label,#destination-label,#destination-detail,#dates-label,#nights-label,#guests-label,#guests-detail,#meal-label,#budget-label')].filter(el=>el.getClientRects().length);
    return{state,zoom,scrollY,form:rect(form),actions:actions.map(el=>({action:el.dataset.action||'submit',text:el.textContent,...rect(el),fullText:fits(el)})),values:values.map(el=>({id:el.id,text:el.textContent,fullText:fits(el,el.closest('.field-control')||el)})),firstCard:first?rect(first):null,firstPrice:price?rect(price):null,calendar:rect(document.querySelector('#price-calendar')),datePrices:[...document.querySelectorAll('#price-strip .date-price')].map(el=>({text:el.querySelector('strong').textContent,...rect(el),fullText:fits(el.querySelector('strong'),el)})),overflow:document.documentElement.scrollWidth>innerWidth+1};
   },{state,zoom});
   assert.equal(record.overflow,false,state+' text'+zoom+' fits the document');
   if(state==='results'&&zoom===100&&width<=760){
    const decision=await page.locator('.hotel-card').first().evaluate(card=>{const price=card.querySelector('.starting-price strong').getBoundingClientRect(),action=card.querySelector('.hotel-price .primary').getBoundingClientRect(),nav=document.querySelector('.mobile-bottom').getBoundingClientRect();return {priceBottom:price.bottom,actionBottom:action.bottom,visibleBottom:nav.top};});
    assert(decision.priceBottom<=decision.visibleBottom&&decision.actionBottom<=decision.visibleBottom,'first mobile tour price and action are visible above fixed navigation without scrolling');
    record.firstDecision=decision;
   }
   if(state==='results'){assert.equal(record.scrollY,0,'first-card/price measurements share the top-of-page frame');assert(record.firstCard&&record.firstPrice);assert(record.datePrices.every(price=>price.width>=44&&price.height>=44&&price.fullText),'complete saved date prices stay inside44px date targets at '+width+' text'+zoom);}
   else{assert(record.values.every(value=>value.fullText),'complete form values stay readable at '+width+' text'+zoom);assert(record.actions.every(action=>action.height>=44&&action.fullText),'form actions keep44px targets and complete labels');}
   density.push(record);return record;
  };
  const reachable=async(control,label)=>{
   await control.scrollIntoViewIfNeeded();const box=await control.evaluate(el=>{const r=el.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2),range=document.createRange();range.selectNodeContents(el);return{width:r.width,height:r.height,hit:hit===el||el.contains(hit),fullText:[...range.getClientRects()].every(t=>t.x>=r.x-1&&t.right<=r.right+1&&t.y>=r.y-1&&t.bottom<=r.bottom+1)};});
   assert(box.width>=44&&box.height>=44&&box.hit&&box.fullText,label+' keeps a reachable44px action and its complete label');return box;
  };
  try{
   await page.goto(`http://127.0.0.1:${server.address().port}${base}visual-search/?scenario=mixed`);
   const trigger=page.locator('.hotel-card [data-action="all-offers"]').first();await trigger.waitFor();assert.equal(requests,0,'form/results leave the offer-list renderer cold');
   const cardOrder=await page.locator('.hotel-card').first().evaluate(card=>({blocks:[...card.children].map(el=>el.classList[0]),priceKey:card.querySelector('.hotel-price .primary').dataset.key,conditionsKey:card.querySelector('.card-minimum-offer').dataset.minimumKey}));
   assert.deepEqual(cardOrder.blocks,['hotel-info-top','hotel-photos','hotel-price','hotel-facts','card-minimum-offer','hotel-more'],'card DOM follows the mobile reading and keyboard order');
   assert.equal(cardOrder.priceKey,cardOrder.conditionsKey,'the earlier price action keeps the exact offer conditions');
   assert(await page.locator('#calendar-preview').isVisible(),'price calendar is open');assert.equal(await page.locator('#price-strip .date-price').count(),7);await page.evaluate(()=>scrollTo(0,0));await shot('results');await densityFrame('results',100);
   await page.evaluate(()=>document.documentElement.style.fontSize='200%');await page.evaluate(()=>scrollTo(0,0));await densityFrame('results',200);await shot('results-text200');
   const dateTargets=page.locator('#price-strip .date-price');assert.equal(await dateTargets.count(),7,'large text retains all seven dates');
   const passiveDateURL=page.url();await dateTargets.last().focus();const lastDate=await reachable(dateTargets.last(),'last saved date text200');
   assert(await dateTargets.last().evaluate(el=>document.activeElement===el),'the last date remains a keyboard target');assert.equal(page.url(),passiveDateURL,'keyboard rail navigation is passive');
   if(width<=760){const focus=await dateTargets.last().evaluate(el=>{const style=getComputedStyle(el);return {visible:el.matches(':focus-visible'),style:style.outlineStyle,offset:parseFloat(style.outlineOffset)};});assert(focus.visible&&focus.style!=='none'&&focus.offset<=0,'keyboard date focus remains visible inside the scrollable rail');}
   const rail=await page.locator('#price-strip').evaluate(el=>({width:el.clientWidth,contentWidth:el.scrollWidth,scrollLeft:el.scrollLeft,overflowX:getComputedStyle(el).overflowX}));
   if(width===360){assert(rail.contentWidth>rail.width&&rail.scrollLeft>0,'large saved prices use the internal rail and keyboard focus reveals the last date');assert.equal(rail.overflowX,'auto');}
   density.push({state:'date-rail-keyboard',zoom:200,lastDate,...rail});
   await page.evaluate(()=>{document.documentElement.style.fontSize='';document.querySelector('#price-strip').scrollLeft=0;scrollTo(0,0);});
   const photo=await page.locator('.hotel-card').first().evaluate(card=>{const image=card.querySelector('.hotel-image'),box=image.getBoundingClientRect();return {card:card.clientWidth,width:box.width,height:box.height,natural:image.naturalWidth};});
   if(width<=760){assert(photo.width>=photo.card-4,'approved mobile photo spans the card');assert(Math.abs(photo.width/photo.height-1.8)<0.03,'approved mobile photo ratio 1.8');}assert(photo.natural>0,'saved demo photo is available');
   await page.locator('.hotel-card').first().scrollIntoViewIfNeeded();await shot('card');
   const appliedURL=page.url(),appliedKeys=await page.locator('.hotel-card').evaluateAll(cards=>cards.map(card=>card.id));
   await page.locator('#applied-search [data-action="edit-search"]:visible,#compact-search .secondary[data-action="top"]:visible').first().click();await page.locator('#search-form').waitFor({state:'visible'});await page.locator('#search-form').scrollIntoViewIfNeeded();await shot('form');
   for(const zoom of [100,200]){
    await page.evaluate(zoom=>document.documentElement.style.fontSize=zoom===200?'200%':'',zoom);await densityFrame('form',zoom);
    for(const control of ['.search-submit','[data-action="cancel-search-edit"]','[data-action="form-filters"]'])await reachable(page.locator('#search-form '+control),'form '+control+' text'+zoom);
    if(zoom===200)await shot('form-text200');
   }
   await page.evaluate(()=>document.documentElement.style.fontSize='');await page.locator('#search-form').scrollIntoViewIfNeeded();
   const editSearchNotice=await page.locator('#search-edit-note').textContent();
   assert(await page.locator('#search-edit-note').isVisible());assert.doesNotMatch(editSearchNotice,/Условия изменены/,'opening the unchanged applied search does not assert a change');assert.match(editSearchNotice,/Найти туры/,'the explicit search action is explained');
   assert.equal(page.url(),appliedURL);assert.deepEqual(await page.locator('.hotel-card').evaluateAll(cards=>cards.map(card=>card.id)),appliedKeys,'opening preserves the result inventory');
   await page.locator('#search-edit-note').scrollIntoViewIfNeeded();await shot('edit-notice');
   for(const name of ['departure','destination','dates','nights','guests','meals','budget','form-filters']){
    await page.locator(`#search-form [data-action="${name}"]`).click();await page.locator('#modal').waitFor({state:'visible'});await shot(name);
    if(name==='dates'){
     const body=page.locator('#modal-body'),initialScroll=await body.evaluate(el=>el.scrollTop);await page.locator('#modal').evaluate(modal=>{window.__compactCalendarFocus=document.activeElement;});
     for(const zoom of [100,200]){
      await page.evaluate(zoom=>document.documentElement.style.fontSize=zoom===200?'200%':'',zoom);
      if(width<=760)await page.locator('#date-calendar .calendar-month').nth(2).evaluate(el=>el.scrollIntoView({block:'start',behavior:'instant'}));
      const scope=await page.locator('#modal').evaluate(modal=>{const units=[...modal.querySelectorAll('.calendar-units')],body=modal.querySelector('#modal-body').getBoundingClientRect(),footer=modal.querySelector('#modal-footer').getBoundingClientRect(),boxes=units.map(el=>{const r=el.getBoundingClientRect();return{text:el.textContent,top:r.top,bottom:r.bottom,width:r.width,height:r.height};});return{count:units.length,boxes,visible:boxes.every(r=>r.top>=body.top-1&&r.bottom<=Math.min(body.bottom,footer.top)+1),scroll:modal.querySelector('#modal-body').scrollTop,overflow:modal.scrollWidth>modal.clientWidth+1||document.documentElement.scrollWidth>innerWidth+1};});
      assert.equal(scope.count,1,'calendar explains price units once');assert.match(scope.boxes[0].text,/тыс\. ₽.*весь тур/);assert.equal(scope.visible,true,'price units stay visible with later mobile months at '+width+' text'+zoom);assert.equal(scope.overflow,false);
      const apply=await reachable(page.locator('#modal [data-action="apply-dates"]'),'calendar Apply text'+zoom);calendarContext.push({zoom,...scope,apply});await shot('dates-context-text'+zoom);
     }
     await page.evaluate(()=>{document.documentElement.style.fontSize='';window.__compactCalendarFocus?.focus({preventScroll:true});delete window.__compactCalendarFocus;});await body.evaluate((el,scroll)=>el.scrollTop=scroll,initialScroll);
    }
    if(name==='guests'){
     await page.locator('[data-action="children-plus"]').click();await page.locator('[data-action="child-age"]').first().click();await shot('child-age');await page.locator('#modal-back').click();
    }
    if(name==='form-filters'){await page.locator('#modal [data-action="stars"]').click();await shot('stars');await page.locator('#modal-back').click();}
    await page.locator('#modal .modal-header [data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);
   }
   await page.locator('[data-action="cancel-search-edit"]').click();assert.equal(requests,0,'all form states leave the exact-list owner cold');
   assert.equal(page.url(),appliedURL);assert.deepEqual(await page.locator('.hotel-card').evaluateAll(cards=>cards.map(card=>card.id)),appliedKeys,'Cancel preserves the applied results');
   if(width<=1100){await page.locator('.results-toolbar [data-action="filters"]').click();await shot('filters');await page.locator('.filter-operator-group>.filter-section-toggle').click();assert.equal(await page.locator('.filter-top h3').textContent(),'Туроператор');assert(await page.locator('#filter-detail-back').evaluate(el=>document.activeElement===el));assert.equal(await page.locator('#apply-filters').isVisible(),false);await shot('operators');await page.locator('#filter-detail-back').click();assert.equal(await page.locator('.filter-top h3').textContent(),'Фильтры');assert(await page.locator('.filter-operator-group>.filter-section-toggle').evaluate(el=>document.activeElement===el));await page.locator('#filter-panel [data-action="close-filters"]').click();}
   else{await page.locator('#filters').scrollIntoViewIfNeeded();await shot('filters');}
   await trigger.click();await page.locator('[data-action="retry-offer-list"]').waitFor();assert.equal(requests,1);
   await page.locator('[data-action="retry-offer-list"]').click();while(!pending)await page.waitForTimeout(20);assert.equal(requests,2);
   await page.locator('#modal .modal-header [data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);release();await page.waitForFunction(()=>!!window.AnyTourOfferList?.create);
   assert.equal(await page.locator('#modal').evaluate(el=>el.open),false,'late load cannot reopen a closed modal');
   await page.evaluate(()=>{const owner=window.AnyTourOfferList,create=owner.create;window.__ownerInventories=0;owner.create=context=>{window.__exactConditionMatch=context.sameSelectedTourConditions;return create({...context,hotelOffers:hotel=>{window.__ownerInventories++;const rows=context.hotelOffers(hotel);if(!rows.length)return rows;const seed=rows[0],placeholder={...seed,key:seed.key+'-request-placeholder',room:' on request ',meal:'On Request'},extra=Array.from({length:11},(_,i)=>({...seed,key:seed.key+'-pagination-demo-'+i,total:seed.total+i+1}));return [...rows,placeholder,...extra];}});};});
   await trigger.click();await page.locator('.grouped-offer').first().waitFor();assert.equal(requests,2,'warm open reuses the cold owner');
   const exactConditionGuard=await page.evaluate(()=>{
    const match=window.__exactConditionMatch,original=Object.freeze({hotelId:7,day:'2026-10-05',nights:7,adults:2,ages:Object.freeze([0,17]),room:'Standard',placement:'DBL + 2 CHD',origin:'Москва',meal:'BB',operator:'ANEX',flight:'charter'}),before=JSON.stringify(original);
    const alternatives=[{...original,placement:'TWIN + 2 CHD'},{...original,placement:''},{...original,origin:'Калининград'},{...original,origin:'',search:{origin:'Калининград'}}];
    return {from_connected_owner:typeof match==='function',alternatives:alternatives.map(o=>match(o,original)),same:match({...original,total:999999,key:'fresh-offer'},original),searchFallback:match({...original,origin:'',search:{origin:'Москва'}},original),unchanged:JSON.stringify(original)===before};
   });
   assert.equal(exactConditionGuard.from_connected_owner,true);assert.deepEqual(exactConditionGuard.alternatives,[false,false,false,false],'actual compiled owner rejects different or unknown placement/departure');assert.equal(exactConditionGuard.same,true);assert.equal(exactConditionGuard.searchFallback,true);assert.equal(exactConditionGuard.unchanged,true);
   assert.equal(await page.locator('[data-action="offer-group"]').count(),0,'approved exact offers appear without room disclosures');await shot('offers');
   const placeholder=page.locator('[data-offer-key$="-request-placeholder"]');await placeholder.waitFor();const placeholderText=await placeholder.textContent();
   assert.match(placeholderText,/Номер уточняется/);assert.match(placeholderText,/Питание уточняется/);assert.doesNotMatch(placeholderText,/on request/i,'supplier placeholder is not exposed as user-facing copy');
   assert.doesNotMatch(placeholderText,/Номер\s+Номер уточняется/,'room label is not duplicated around the readable placeholder');
   if(!await page.locator('.offer-filter-disclosure').evaluate(el=>el.open))await page.locator('.offer-filter-disclosure>summary').click();
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
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);receipts.push({width,initial_downloads:0,failed_downloads:1,retry_downloads:1,warm_downloads:0,late_closed_modal_render:false,flat_exact_offers:true,editSearchNotice,exactConditionGuard,pagination,history_room_restored:true,history_owner_inventory_calls:0,saved_demo_photo:photo,geometry,density,calendarContext,supplier_requests:0,lead_requests:0,physicalSafari:false});
  }finally{release?.();await context.close();}
 }}finally{await browser?.close();await new Promise(resolve=>server.close(resolve));}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify(receipts,null,2)+'\n');console.log('PASS compiled approved interface and cold offer-list browser',JSON.stringify(receipts));
})().catch(error=>{console.error(error);process.exitCode=1;});
