'use strict';
// Compiled presentation acceptance: fictional transport only, no paid supplier
// or lead request. Expiry changes the presentation; canonical locks stay owned
// by the existing adapter. The browser clock models time, not a physical device.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
const historyKey='anytour.prototype.v18.ui.v1';
module.exports=async function({browser,origin,base,evidence}){
 const records=[],started=Date.now();
 const scenario=async(provider,width,mode)=>{
  const transport=fixture(),errors=[],forbidden=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();
  transport.state.anexPackageChoiceCount=mode==='editing'?2:1;transport.state.repricingEnabled=mode==='editing';page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));
  const application=provider==='anex'?'anex-application-preview':'andromeda-application-preview';
  const oldPrice=mode==='editing'?'101000':provider==='anex'?'135678,9':'125500',contacts=mode!=='verified'&&mode!=='editing';
  const calls=()=>transport.calls.length;
  try{
   await page.clock.install({time:new Date()});
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
    if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){
     if(url.pathname.endsWith('/prototype-search/config.js')){
      const response=await route.fetch();await route.fulfill({response,body:(await response.text())+"\nwindow.V2_CONFIG.andromedaApi='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php';window.V2_CONFIG.andromedaQuoteApi='/_preview/search3-anex-candidate/api-andromeda-quote-preview.php';\n"});return;
     }
     await route.continue();return;
    }
    try{
     const value=await transport.json(request.url(),{body:request.postData()});
     if(mode==='return'&&url.pathname==='/api-v2.php'&&url.searchParams.get('action')==='search_results'){
      const first=value[0].tours[0],tomorrow=new Date(Date.parse(trip.from+'T12:00:00Z')+86400000).toISOString().slice(0,10);
      // A real selectable refinement needs multiple retained meal values; the
      // existing owner deliberately hides a one-value field. Native BB is an
      // already-mapped canonical fixture meal, not a fabricated display alias.
      value[0].tours.push({...first,id:'visual-tv-next-day',date:tomorrow,price:120001,meal:{id:3,name:'BB'}});
     }
     if(mode==='return'&&value.data?.status==='expanded'){
      const first=value.data.hotels[0].tours[0];
      value.data.hotels[0].tours=Array.from({length:14},(_,i)=>({...first,offer_ref:'anex_online:'+(i+1).toString(16).repeat(64),price:{amount:String(121000+i),currency:'RUB'}}));
     }
     await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});
    }catch(error){forbidden.push(error.message);await route.abort();}
   });
   const to=mode==='return'?new Date(Date.parse(trip.from+'T12:00:00Z')+86400000).toISOString().slice(0,10):trip.to;
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,to,ages:''}));
   await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
   assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0,'bootstrap does not search');
   await page.locator('.search-submit').click();
   await page.waitForFunction(()=>document.querySelector('#search-status').hidden&&document.querySelector('[data-action="all-offers"][data-id="501"]'));
   const list=async()=>{await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#all-offers-list').waitFor();};
   const choose=async(p=provider)=>{await page.locator('#modal-body [data-action="offer"][data-key^="'+p+'%3A"]').first().click();};
   await list();await choose();await page.locator('[data-action="refresh-hotel"]').click();
   let returnState;
   if(provider==='anex'){
    if(mode==='return'){
     await page.locator('.offer-filter-disclosure>summary').click();
     for(const name of ['room','meal','departure','flight'])assert.equal(await page.locator('#offer-'+name).isVisible(),true,'dense fixture exposes a genuine '+name+' choice');
     await page.locator('#offer-sort').selectOption('date');
     for(const [name,value] of Object.entries({room:'ANEX CONCRETE',meal:'Всё включено',departure:trip.from,flight:'charter'}))await page.locator('#offer-'+name).selectOption(value);
     await page.locator('[data-action="group-more"]').click();
     // Capture the exact position after the real offer action is focused, so
     // browser scrolling for a click is included in the expected return state.
     const offer=page.locator('#modal-body [data-action="offer"][data-key^="anex%3A"]').first();await offer.focus();
     returnState=await page.evaluate(key=>({route:structuredClone(history.state[key]),focus:document.activeElement.dataset.key,scroll:document.querySelector('#modal-body').scrollTop}),historyKey);
     await offer.click();
    }else await page.locator('[data-action="select-anex-tour"]').waitFor();
    await page.locator('[data-action="select-anex-tour"]').click();
    if(mode==='editing')await page.locator('[data-action="anex-package-calculate"]:enabled').click();
   }
   if(provider==='andromeda'&&mode==='editing')await page.locator('[data-action="apply-andromeda-flights"]:enabled').click();
   await page.locator('[data-action="'+application+'"]').waitFor();
   assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),new RegExp(oldPrice));
   if(mode==='other'){
    await page.locator('[data-action="close-modal"]').click();
    await page.waitForFunction(()=>!document.querySelector('#modal').open&&!history.state?.['anytour.prototype.v18.ui.v1']);
    await list();await choose('tourvisor');const before=calls();
    await page.clock.fastForward(901000);await page.evaluate(()=>{dispatchEvent(new Event('focus'));document.dispatchEvent(new Event('visibilitychange'));});
    assert.equal(await page.locator('#provider-price-expiry').count(),0,'stale provider timer cannot mark another offer expired');
    assert.doesNotMatch(await page.locator('#modal-footer').textContent(),/Срок подтверждения цены истёк/);
    assert.equal(calls(),before,'stale timer never quotes another offer');
   }else{
    let reportedMarkup;
    if(mode==='editing'){
     await page.locator('[data-action="edit-'+(provider==='anex'?'anex':'andromeda')+'-flights"]').click();
     reportedMarkup=(await page.locator('.flight-option small').allTextContents()).filter(text=>text.includes('Доплата оператора'));
     if(provider==='anex')assert.match((await page.locator('.flight-option-price').allTextContents()).join('').replace(/\s/g,''),new RegExp(oldPrice),'selected bounded pair visibly carries its verified whole-tour price before expiry');
     else assert.match(await page.locator('#andromeda-flight-price-status').textContent(),/Подтверждённая/);
    }
    if(contacts){
     await page.locator('[data-action="'+application+'"]').click();
     for(const [name,value] of Object.entries({name:'Тестовый турист',phone:'+7 999 123-45-67',comment:'Тестовый комментарий'}))await page.locator('[name="'+name+'"]').fill(value);
     await page.locator('[name="consent"]').check();
     if(mode==='return'){
      const before=calls();await page.locator('#modal-body [data-action="all-offers"]').click();await page.locator('#offer-room').waitFor();
      const restored=await page.evaluate(key=>({route:structuredClone(history.state[key]),focus:document.activeElement.dataset.key,scroll:document.querySelector('#modal-body').scrollTop}),historyKey);
      for(const field of ['id','departure','flight','room','meal','sort','open','limits','filtersOpen'])assert.deepEqual(restored.route[field],returnState.route[field],'same-hotel application return preserves '+field);
      assert.equal(restored.scroll,returnState.scroll,'same-hotel return preserves scroll');assert.equal(restored.focus,returnState.focus,'same-hotel return focuses the exact offer');
      assert.equal(calls(),before,'application return uses retained offers');
      await page.screenshot({path:path.join(evidence,'selected-session-return-'+width+'.png')});
      await page.locator('#modal-back').click();await page.locator('#prototype-lead-form').waitFor();
      assert.equal(await page.locator('[name="phone"]').inputValue(),'+7 999 123-45-67');
     }
     if(width===390&&mode==='application'){
      await page.setViewportSize({width,height:650});
      await page.evaluate(()=>document.documentElement.style.fontSize='200%');
     }
     await page.locator('[name="phone"]').focus();
     await page.evaluate(()=>{window.__selectedSessionForm=document.querySelector('#prototype-lead-form');window.__selectedSessionPhone=document.activeElement;});
    }
    const before=calls(),scroll=await page.locator('#modal-body').evaluate(el=>el.scrollTop);
    await page.clock.fastForward(901000);
    await page.waitForFunction(()=>document.querySelector('#modal').dataset.quoteExpired==='1');
    await page.evaluate(()=>{dispatchEvent(new Event('focus'));document.dispatchEvent(new Event('visibilitychange'));});
    assert.equal(await page.locator('#provider-price-expiry').count(),1,'expiry is reflected once after deadline and resume');
    assert.match(await page.locator('#provider-price-expiry').textContent(),/Срок подтверждения цены истёк/);
    assert.doesNotMatch((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),new RegExp(oldPrice),'expired footer removes current final-price claim');
    assert.doesNotMatch((await page.locator('#modal-body .price-line.total').allTextContents()).join('').replace(/\s/g,''),new RegExp(oldPrice),'expired verified body removes the old final total');
    assert.equal(await page.locator('[data-action="'+application+'"]:enabled').count(),0,'expired receipt cannot enter application');
    assert.equal(await page.locator('[type="submit"][form="prototype-lead-form"]:enabled').count(),0,'expired application cannot submit');
    if(mode==='editing'){
     assert.doesNotMatch((await page.locator('.flight-option-price').allTextContents()).join('').replace(/\s/g,''),new RegExp(oldPrice),'expired selected pair removes its old verified whole-tour price');
     assert.doesNotMatch((await page.locator('#anex-flight-price-status,#andromeda-flight-price-status').allTextContents()).join(''),/подтверждена|Подтверждённая/,'expired flight screen revokes verified status');
     assert.equal(await page.locator('[name="anex-package-choice"]:enabled,[name="andromeda-outbound"]:enabled,[name="andromeda-return"]:enabled').count(),0,'expired flight inventory cannot select another pair');
     assert.deepEqual((await page.locator('.flight-option small').allTextContents()).filter(text=>text.includes('Доплата оператора')),reportedMarkup,'reported supplier markup remains informational, not silently removed or summed');
    }
    if(contacts){
     assert.equal(await page.evaluate(()=>document.querySelector('#prototype-lead-form')===window.__selectedSessionForm),true,'expiry keeps the same contact form');
     assert.equal(await page.evaluate(()=>document.activeElement===window.__selectedSessionPhone),true,'expiry preserves keyboard focus');
     assert.equal(await page.locator('#modal-body').evaluate(el=>el.scrollTop),scroll,'expiry preserves internal scroll');
     for(const [name,value] of Object.entries({name:'Тестовый турист',phone:'+7 999 123-45-67',comment:'Тестовый комментарий'}))assert.equal(await page.locator('[name="'+name+'"]').inputValue(),value);
     assert.equal(await page.locator('[name="consent"]').isChecked(),false,'expired price clears consent');
     assert.equal(await page.locator('#prototype-lead-form').getAttribute('data-checked'),null,'expired receipt clears successful rehearsal status');
    }
    assert.equal(calls(),before,'idle expiry and resume send no request');
    assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth+1),false);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
    const recovery=await page.locator('#modal-footer [data-action="all-offers"]').evaluate(el=>{const r=el.getBoundingClientRect();return{x:r.x,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height,viewportWidth:innerWidth,viewportHeight:innerHeight};});
    assert(recovery.width>=44&&recovery.height>=44&&recovery.x>=-1&&recovery.right<=recovery.viewportWidth+1&&recovery.top>=0&&recovery.bottom<=recovery.viewportHeight+1,'expired recovery stays inside the viewport with a44px target');
    await page.screenshot({path:path.join(evidence,`selected-session-${provider}-${mode}-${width}.png`)});
    if(contacts){
     await page.locator('#modal-back').click();
     assert.equal(await page.locator('[data-action="'+application+'"]:enabled').count(),0,'Back cannot restore an expired application action');
    }
    await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open&&!history.state?.['anytour.prototype.v18.ui.v1']);
    await page.evaluate(()=>history.forward());
    await page.waitForTimeout(50);
    assert.equal(await page.locator('[data-action="'+application+'"]:enabled').count(),0,'Forward does not restore expired price authority');
    assert.equal(calls(),before,'Back and Forward do not recalculate');
   }
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   records.push({provider,width,mode,compiled:true,expiry_presentation:mode!=='other',same_hotel_return:mode==='return',independent_offer:mode==='other',root_200_reduced_height:width===390&&mode==='application',supplier_HTTP:0,real_leads:0,physical_device:false});
  }finally{await context.close();}
 };
 // Keep the existing full CI budget: at most four independent fixture contexts
 // share the compiled browser, with no overlapping source or transport state.
 const jobs=[...['anex','andromeda'].flatMap(provider=>[360,390,430,768,1280].map(width=>[provider,width,'application'])),...['anex','andromeda'].flatMap(provider=>[[provider,390,'verified'],[provider,390,'other'],[provider,390,'editing']]),['anex',390,'return']];
 for(let i=0;i<jobs.length;i+=4){const results=await Promise.allSettled(jobs.slice(i,i+4).map(args=>scenario(...args))),failed=results.filter(r=>r.status==='rejected');if(failed.length)throw new AggregateError(failed.map(r=>r.reason),'Selected-session acceptance failed');}
 const flightSession=async width=>{
  const errors=[],forbidden=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));
  try{
   await page.route('**/*',async route=>{const u=new URL(route.request().url());if(u.origin===origin&&u.pathname.startsWith(base)&&!u.pathname.includes('/data/'))await route.continue();else{forbidden.push(u.pathname);await route.abort();}});
   await page.goto(origin+base+'visual-search/?scenario=flights&searched=1');await page.locator('.hotel-card [data-action="offer"]').first().click();await page.locator('[data-action="start-tour-flights"]').click();await page.locator('#flight-total').waitFor();
   const pair=()=>page.locator('[name="flight-pair"]:checked').inputValue(),read=()=>page.evaluate(key=>structuredClone(history.state[key]),historyKey);
   assert.equal(await pair(),'0');await page.locator('.flight-filter-panel>summary').click();await page.locator('[data-flight-query]').fill('no-such-flight');assert.equal(await pair(),'0');await page.locator('.flight-filter-state button').click();assert.equal(await page.locator('.flight-filter-panel>summary').evaluate(el=>el===document.activeElement),true,'Reset focuses a remaining visible target');
   await page.locator('[data-flight-sort]').selectOption('original');await page.locator('[data-flight-load-more]').click();await page.locator('[name="flight-pair"][value="3"]').check();await page.locator('[data-flight-filter="direct"]').check();await page.locator('.flight-time-filters>summary').click();
   await page.locator('[data-flight-index="3"] details>summary').click();await page.locator('[data-flight-query]').focus();await page.locator('#modal-body').evaluate(el=>el.scrollTop=123);await page.waitForTimeout(30);
   // Native browser Back consumes the nested step. The latest scroll/focus
   // must be captured even while history writing is deliberately suppressed.
   await page.evaluate(()=>history.back());await page.locator('[data-action="choose-flight"]').waitFor();await page.locator('[data-action="choose-flight"]').click();await page.locator('#flight-total').waitFor();
   let retained=await read();assert.equal(await pair(),'0','Cancel does not apply draft3');for(const [key,value]of Object.entries({direct:true,sort:'original',panel:true,timePanel:true,limit:4}))assert.equal(retained.view[key],value);assert.deepEqual(retained.view.expanded,[3]);assert.equal(await page.locator('#modal-body').evaluate(el=>el.scrollTop),123,'native Back retains internal scroll');assert.equal(await page.locator('[data-flight-query]').evaluate(el=>el===document.activeElement),true,'native Back retains input focus');
   await page.screenshot({path:path.join(evidence,'flight-session-retained-'+width+'.png')});
   await page.locator('[name="flight-pair"][value="3"]').check();const total=await page.locator('#flight-total').textContent();await page.locator('[data-action="apply-flight"]').click();await page.locator('#prototype-lead-form').waitFor();assert((await page.locator('#modal-footer').textContent()).includes(total),'application uses the exact pair whole-tour amount');await page.locator('#modal-back').click();await page.locator('[data-action="choose-flight"]').click();assert.equal(await pair(),'3','Apply preserves exact pair3');retained=await read();assert.equal(retained.view.direct,true);assert.deepEqual(retained.view.expanded,[3]);
   await page.locator('[data-flight-filter="direct"]').uncheck();while(await page.locator('[data-flight-load-more]').isVisible())await page.locator('[data-flight-load-more]').click();await page.locator('[name="flight-pair"][value="23"]').check();assert.equal(await page.locator('#flight-total').textContent(),'Цена уточняется');assert.equal(await page.locator('[data-action="apply-flight"]').isDisabled(),true);
   await page.locator('#modal-back').click();await page.locator('[data-action="choose-flight"]').click();assert.equal(await pair(),'3','unknown draft cannot replace applied pair');await page.locator('[name="flight-pair"][value="23"]').check();
   const saved=await read();await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(key=>!document.querySelector('#modal').open&&!history.state?.[key],historyKey);await page.evaluate(()=>history.forward());await page.locator('#flight-total').waitFor();assert.equal(await pair(),'23');assert.equal(await page.locator('[data-action="apply-flight"]').isDisabled(),true);assert.deepEqual((await read()).view,saved.view,'Forward restores presentation/draft, not price authority');
   assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth+1),false);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
   const bounds=await page.locator('#modal-footer [data-action="apply-flight"]').evaluate(el=>{const r=el.getBoundingClientRect();return{left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height,vw:innerWidth,vh:innerHeight};});assert(bounds.width>=44&&bounds.height>=44&&bounds.left>=-1&&bounds.right<=bounds.vw+1&&bounds.top>=0&&bounds.bottom<=bounds.vh+1,'confirmation target remains inside viewport');
   await page.screenshot({path:path.join(evidence,'flight-session-unknown-'+width+'.png')});assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   records.push({provider:'fixture',width,mode:'flight-session',compiled:true,native_history:true,reset_focus:true,exact_draft_apply_cancel:true,unknown_gated:true,retained_refinements:true,supplier_HTTP:0,real_leads:0,physical_device:false});
  }finally{await context.close();}
 };
 for(let i=0;i<5;i+=4){const results=await Promise.allSettled([360,390,430,768,1280].slice(i,i+4).map(flightSession)),failed=results.filter(r=>r.status==='rejected');if(failed.length)throw new AggregateError(failed.map(r=>r.reason),'Compiled flight session acceptance failed');}
 const receipt={published:false,live_data:false,engine:'Chromium',physical_device:false,duration_ms:Date.now()-started,records};
 fs.writeFileSync(path.join(evidence,'selected-session.json'),JSON.stringify(receipt,null,2)+'\n');
 console.log('PASS compiled selected session',JSON.stringify({cases:records.length,duration_ms:receipt.duration_ms,expiry_at_five_widths:true,retained_contact_focus:true,same_hotel_scope_return:true,supplier_HTTP:0,real_leads:0}));return receipt;
};
