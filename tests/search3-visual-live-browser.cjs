'use strict';
// CI browser acceptance. Every data request is intercepted with fictional fixtures.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const {fixture,trip,hotelCatalogue,day,back}=require('./search3-visual-live-fixture.cjs');
const mobileCardPriceLayout=async(page,width,evidence)=>{
 if(width!==390){
  const boxes=await page.locator('.hotel-card').first().evaluate(el=>({photo:el.querySelector('.hotel-image-wrap').getBoundingClientRect().width,thumbs:[...el.querySelectorAll('.card-thumb')].map(t=>t.getBoundingClientRect().width)}));
  assert(boxes.thumbs.every(w=>w<=boxes.photo*.3),'a single desktop thumbnail remains a preview, not a second full-size photo at '+width);
  return;
 }
 const card=page.locator('.hotel-card').first(),amount=card.locator('.starting-price strong'),original=await amount.textContent(),wasWide=await card.locator('.hotel-price').evaluate(el=>el.classList.contains('price-wide'));
 try{
  await amount.evaluate(el=>{el.textContent='1\u00a0035\u00a0282 ₽';});
  await card.locator('.hotel-price').evaluate(el=>el.classList.add('price-wide'));
  for(const mobileWidth of [360,390,430]){
   await page.setViewportSize({width:mobileWidth,height:900});
   const boxes=await card.evaluate(el=>{
    const panel=el.querySelector('.hotel-price'),rect=node=>{const b=node.getBoundingClientRect();return{x:b.x,y:b.y,right:b.right,bottom:b.bottom,width:b.width,height:b.height};};
    return{card:rect(el),photo:rect(el.querySelector('.hotel-image-wrap')),identity:rect(el.querySelector('.hotel-info-top')),arrows:[...el.querySelectorAll('.card-photo-arrow')].map(rect),panel:rect(panel),price:rect(panel.querySelector('.starting-price strong')),total:rect(panel.querySelector('.starting-price')),status:rect(panel.querySelector('.fuel-note')),action:rect(panel.querySelector('.primary')),nowrap:getComputedStyle(panel.querySelector('.starting-price strong')).whiteSpace};
   });
   assert.equal(boxes.nowrap,'nowrap');
   assert(boxes.price.x>=boxes.panel.x&&boxes.price.right<=boxes.panel.right,'whole seven-digit price fits at '+mobileWidth);
   assert(boxes.status.y>=boxes.total.bottom-1&&boxes.action.y>=boxes.status.bottom-1,'approved wide-price status sits between the complete amount and full-row action at '+mobileWidth);
   assert(boxes.action.x>=boxes.total.right-1||boxes.action.y>=boxes.total.bottom-1,'price and action never overlap at '+mobileWidth);
   assert(boxes.action.x>=boxes.panel.x&&boxes.action.right<=boxes.panel.right&&boxes.action.height>=48,'price action stays inside the card with a 48px target at '+mobileWidth);
   assert(boxes.photo.width>boxes.card.width*.9&&boxes.photo.height>=150,'hotel photo spans the mobile card at '+mobileWidth);
   assert(boxes.photo.y>=boxes.identity.bottom-1,'hotel identity precedes its large photo at '+mobileWidth);
   assert(boxes.arrows.every(b=>b.width>=44&&b.height>=44),'gallery arrows remain touch targets at '+mobileWidth);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
   await card.screenshot({path:path.join(evidence,'card-price-'+mobileWidth+'.png')});
   fs.writeFileSync(path.join(evidence,'card-price-'+mobileWidth+'.json'),JSON.stringify(boxes,null,2));
  }
 }finally{await amount.evaluate((el,text)=>{el.textContent=text;},original);await card.locator('.hotel-price').evaluate((el,wide)=>el.classList.toggle('price-wide',wide),wasWide);await page.setViewportSize({width,height:900});}
 assert.equal(await amount.textContent(),original,'layout stress restores the fictional fixture price');
};
const openResultFilters=async(page,width)=>{
 if(width<=1100){await page.locator('[data-action="filters"]:visible').first().click();return;}
 const panel=page.locator('#filter-panel');
 assert(await panel.isVisible(),'desktop result filters are already visible in the sidebar');
 assert.equal(await page.locator('[data-action="filters"]:visible').count(),0,'desktop sidebar needs no duplicate drawer trigger');
 await panel.scrollIntoViewIfNeeded();
};
const editResultSearch=async(page,width)=>{
 // Closing a modal restores browser history and page scroll on later frames.
 // Use the persistent layout control after the passive return has finished.
 await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
 await page.locator(width<=760?'#compact-search .secondary[data-action="top"]':'#applied-search [data-action="edit-search"]').click();
 assert(await page.locator('#search-form').isVisible(),'the approved edit action opens the complete form at '+width);
};
const chosenDepartureContext=async(page,width,transport,evidence)=>{
 if(width!==390)return;
 // classList.remove during responsive price layout can retain a trailing space;
 // compare all card markup with only insignificant class whitespace normalized.
 const cards=()=>page.locator('#cards').evaluate(el=>{const copy=el.cloneNode(true);for(const node of copy.querySelectorAll('[class]'))node.setAttribute('class',node.getAttribute('class').trim().replace(/\s+/g,' '));return copy.innerHTML;});
 const starts=transport.calls.filter(c=>c.action==='search_start').length,originalURL=page.url(),originalCards=await cards();
 const day=trip.from,emptyDay=new Date(Date.parse(day+'T12:00:00Z')+86400000).toISOString().slice(0,10);
 const scope=async()=> (await page.locator('#compact-details').textContent()).split(' · ')[0];
 const allDates=await scope();assert.match(allDates,/ — /);
 for(const viewport of [360,390,430,768,1280]){
  await page.setViewportSize({width:viewport,height:900});
  await page.locator(`#price-strip [data-date="${day}"]`).click();
  assert.equal(await scope(),(await page.locator('#route-label').textContent()).split(' · ')[1]);
  assert.doesNotMatch(await scope(),/ — /);assert.equal(new URL(page.url()).searchParams.get('date'),day);
  assert.equal(await page.locator('.hotel-card').count(),1);
  if(viewport<=760){await page.locator('#results').scrollIntoViewIfNeeded();await page.waitForFunction(()=>document.body.classList.contains('mobile-results'));assert(await page.locator('#compact-search').isVisible());}
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:path.join(evidence,`chosen-date-${viewport}.png`)});
  await page.locator('#clear-date').click();assert.equal(await scope(),allDates);assert.equal(page.url(),originalURL);
  await page.locator(`#price-strip [data-date="${emptyDay}"]`).click();assert.equal(await page.locator('.hotel-card').count(),0);
  assert.equal(await scope(),(await page.locator('#route-label').textContent()).split(' · ')[1],'empty results still describe the applied exact day');
  const chip=page.locator('[data-action="remove-filter"][data-key="date"]'),chipVisible=await chip.isVisible();
  if(chipVisible)await chip.click();else await page.locator('#clear-date').click();
  assert.equal(await scope(),allDates);assert.equal(page.url(),originalURL);
  fs.writeFileSync(path.join(evidence,`chosen-date-${viewport}.json`),JSON.stringify({width:viewport,chosenDay:day,emptyDay,compact_matches_applied:true,clear_restores:true,empty_day_reset:chipVisible?'visible-chip':'calendar-clear',overflow:false,supplier_HTTP:0,physicalSafari:false},null,2));
 }
 await page.setViewportSize({width,height:900});
 await page.locator(`#price-strip [data-date="${day}"]`).click();const chosenURL=page.url(),chosenCards=await cards();
 await editResultSearch(page,width);await page.locator('#search-form [data-action="dates"]').click();
 await page.locator(`[data-action="day-pick"][data-date="${emptyDay}"]`).click();await page.locator('[data-action="apply-dates"]').click();
 assert.equal(await cards(),chosenCards,'unsubmitted date edit leaves applied results intact');
 await page.locator('#search-return').click();assert.equal(page.url(),chosenURL);assert.equal(await cards(),chosenCards);
 assert.equal(await scope(),(await page.locator('#route-label').textContent()).split(' · ')[1]);
 await page.locator(`#price-strip [data-date="${day}"]`).click();assert.equal(await scope(),allDates);assert.equal(page.url(),originalURL);assert.equal(await cards(),originalCards);
 assert.equal(transport.calls.filter(c=>c.action==='search_start').length,starts,'local dates and Cancel never start another supplier search');
};
const chosenDepartureJourney=async(browser,origin,base,evidence)=>{
 const width=390,transport=fixture({tvFuel:20686}),errors=[],forbidden=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();
 page.setDefaultTimeout(10000);page.on('pageerror',error=>errors.push(error.message));
 try{
  await page.route('**/*',async route=>{
   const request=route.request(),url=new URL(request.url());
   if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
   if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
   try{const value=await transport.json(request.url(),{body:request.postData()});await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});}catch(error){forbidden.push(error.message);await route.abort();}
  });
  const to=new Date(Date.parse(trip.from+'T12:00:00Z')+6*86400000).toISOString().slice(0,10);
  await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,to,ages:'',searched:'1'}));
  await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
  assert.equal(transport.calls.filter(call=>call.action==='search_start').length,0);
  await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
  await chosenDepartureContext(page,width,transport,evidence);
  assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
  console.log('PASS compiled chosen-departure journey: five widths, selected/empty/reset/Cancel, exact cards/URL and no extra fixture supplier starts');
 }finally{await context.close();}
};
const verifiedPairJourney=async(browser,origin,base,evidence)=>{
 for(const width of [360,390,430,768,1280]){
  const transport=fixture({tvFuel:20686}),errors=[],forbidden=[],receipts=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();
  page.setDefaultTimeout(10000);page.on('pageerror',error=>errors.push(error.message));
  try{
   await page.addInitScript(()=>{window.quoteFailures=[];window.addEventListener('anytour:quote-failure',e=>window.quoteFailures.push(e.detail));});
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
    if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
    try{const value=await transport.json(request.url(),{body:request.postData()});await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});}catch(error){forbidden.push(error.message);await route.abort();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
   assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0);
   const quoteCount=()=>transport.calls.filter(c=>c.url.endsWith('/api-andromeda-quote-preview.php')).length;
   for(const mode of ['exact','swapped','foreign','legacy']){
    Object.assign(transport.state,{samoFlightChoice:true,samoVerifiedPair:mode});
    if(mode!=='exact')await editResultSearch(page,width);
    await page.locator('.search-submit').click();await page.waitForFunction(()=>(document.querySelector('#search-status').hidden||!document.querySelector('[data-action="stop-search"]'))&&document.querySelector('#results-summary').textContent.includes('3 варианта'));
    const openList=async()=>{await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#all-offers-list').waitFor();};
    const choose=async()=>{const row=page.locator('#modal-body [data-action="offer"][data-key^="andromeda%3A"]').first();await row.waitFor({state:'visible'});await row.click();};
    // This fixture has one exact pair: the existing explicit refresh continues it automatically.
    // The original broad matrix separately selects both alternative directions.
    await openList();await choose();const before=quoteCount();await page.locator('[data-action="refresh-hotel"]').click();
    if(mode==='exact'||mode==='legacy'){
     await page.locator('[data-action="andromeda-application-preview"]').waitFor();assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/125500/);
    }else{
     await page.waitForFunction(()=>document.querySelector('#modal-body .error-text')?.textContent.includes('некорректное подтверждение'));
     assert.equal(await page.locator('[data-action="andromeda-application-preview"]').count(),0);
     assert.match(await page.locator('#modal-footer').textContent(),/Цена из выдачи · не подтверждена/);
     assert.equal((await page.evaluate(()=>window.quoteFailures)).at(-1).failureCategory,'invalid_response');
    }
    await page.screenshot({path:path.join(evidence,`samo-pair-${mode}-${width}.png`)});
    assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth),false);
    assert.equal(quoteCount(),before+2);
    await page.locator('[data-action="close-modal"]').click();await openList();await choose();
    if(mode==='exact'||mode==='legacy')await page.locator('[data-action="andromeda-application-preview"]').waitFor();
    else await page.waitForFunction(()=>document.querySelector('#modal-body .error-text')?.textContent.includes('некорректное подтверждение'));
    assert.equal(quoteCount(),before+2,'reopen retains confirmed or rejected pair without replay');
    await page.locator('[data-action="close-modal"]').click();await openList();
    await page.screenshot({path:path.join(evidence,`samo-pair-other-${mode}-${width}.png`)});
    await page.locator('#modal-body [data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]').waitFor({state:'visible'});
    await page.locator('[data-action="close-modal"]').click();receipts.push({mode,application_allowed:mode==='exact'||mode==='legacy',passive_replay_requests:0,overflow:false});
   }
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   fs.writeFileSync(path.join(evidence,`samo-pair-${width}.json`),JSON.stringify({width,receipts,supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));
  }finally{await context.close();}
 }
 console.log('PASS compiled verified-pair journey: five widths, exact/swapped/foreign/legacy, application gate and passive reopen');
};
const providerApplicationFlightSummary=async(page,width,names,evidence,label)=>{
 const disclosure=page.locator('.summary-flight-details'),summary=disclosure.locator(':scope>summary'),lines=summary.locator('.selected-flight-line');
 assert.equal(await summary.locator('.selected-flight-summary>strong').textContent(),'Выбранные рейсы');assert.equal(await lines.count(),2,'the application exposes only the two receipt flights');
 const wasOpen=await disclosure.evaluate(el=>el.open);assert.equal(wasOpen,width>760,'full receipt details retain native mobile/desktop disclosure state');
 await summary.scrollIntoViewIfNeeded();
 const boxes=await lines.evaluateAll(nodes=>nodes.map(el=>{const r=el.getBoundingClientRect(),style=getComputedStyle(el);return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height,overflow:style.overflow,textOverflow:style.textOverflow,whiteSpace:style.whiteSpace,text:el.textContent};}));
 for(const [index,name] of names.entries()){
  const line=lines.nth(index);assert.equal(await line.isVisible(),true,'receipt flight stays visible before mobile expansion');assert.match(await line.locator('strong').textContent(),new RegExp(index?'Обратно':'Туда'));assert((await line.textContent()).includes(name),'the visible summary retains the full canonical receipt flight name');assert.equal(boxes[index].textOverflow,'clip');assert.notEqual(boxes[index].whiteSpace,'nowrap');
 }
 const bounds=await summary.evaluate(el=>{const r=el.getBoundingClientRect();return{x:r.x,right:r.right};});assert(boxes.every(box=>box.x>=bounds.x-1&&box.right<=bounds.right+1),'long canonical receipt names wrap inside the summary at '+width);
 assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth+1),false);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await page.screenshot({path:path.join(evidence,`selected-flight-summary-${label}-${width}.png`)});
 if(!wasOpen){const detail=disclosure.locator(':scope>:not(summary)').first();assert.equal(await detail.isVisible(),false);await summary.click();assert.equal(await detail.isVisible(),true,'native disclosure still opens complete receipt details');await summary.click();assert.equal(await disclosure.evaluate(el=>el.open),false);}
 await page.locator('#prototype-lead-form').scrollIntoViewIfNeeded();assert.equal(await page.locator('[name="name"]').isEnabled(),true);assert.equal(await page.locator('[name="phone"]').isEnabled(),true);assert.equal(await page.locator('#modal-footer .primary').isVisible(),true);
 return{open:wasOpen,names,boxes,canonical_receipt_summary:true,native_details_toggle:true};
};
const boundedRepriceJourney=async(browser,origin,base,evidence)=>{
 for(const width of [360,390,430,768,1280])for(const provider of ['anex','andromeda']){
  const transport=fixture(),errors=[],forbidden=[],aborts=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();
  Object.assign(transport.state,{repricingEnabled:true,anexPackageChoiceCount:4,samoFlightChoice:true,...(provider==='andromeda'?{wideFacets:true,samoRoom:'STANDARD SEA VIEW'}:{})});
  page.setDefaultTimeout(10000);page.on('pageerror',error=>errors.push(error.message));
  page.on('requestfailed',request=>{if(request.url().includes('api-'+(provider==='anex'?'anex-search3':'andromeda-quote')+'-preview.php'))aborts.push(request.failure()?.errorText);});
  let releaseB,releaseUnknown,markB,markUnknown,retainedList,selectedOfferKey,unknownRecovery;
  const pendingB=new Promise(resolve=>markB=resolve),pendingUnknown=new Promise(resolve=>markUnknown=resolve);
  const action=provider==='anex'?'quote_calculate':'quote_select_flights',app=provider==='anex'?'anex-application-preview':'andromeda-application-preview',apply=provider==='anex'?'anex-package-calculate':'apply-andromeda-flights',edit=provider==='anex'?'edit-anex-flights':'edit-andromeda-flights';
  const count=()=>transport.calls.filter(call=>call.action===action).length;
  const ref=n=>provider==='anex'?'anex_quote:'+String(n).repeat(64):'flight_'+String(n*2-1).repeat(32);
  const radio=n=>'[name="'+(provider==='anex'?'anex-package-choice':'andromeda-outbound')+'"][value="'+ref(n)+'"]';
  const price=async n=>assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),new RegExp(String(100000+n*1000)));
  const pairClarity=async(n,verified,known=verified?[n]:[])=>{
   if(provider!=='anex')return null;
   const rows=page.locator('.anex-pair-option');assert.equal(await rows.count(),4,'all canonical ANEX pairs remain available');
   const selected=rows.filter({has:page.locator('input:checked')}),badge=selected.locator('.flight-option-selected');assert.equal(await selected.count(),1);assert.equal(await badge.isVisible(),true);assert.equal((await badge.textContent()).trim(),'Выбрано');
   const facts=await rows.evaluateAll(nodes=>nodes.map(row=>({choice:row.querySelector('input').value,checked:row.querySelector('input').checked,legs:[...row.querySelectorAll('.flight-option-leg')].map(leg=>({text:leg.textContent,font:parseFloat(getComputedStyle(leg).fontSize)})),price:row.querySelector('.flight-option-price').textContent,selectedBadgeVisible:row.querySelector('.flight-option-selected').getClientRects().length>0})));
   for(const [index,fact] of facts.entries()){
    assert.equal(fact.legs.length,2);assert(fact.legs[0].text.includes(`TEST ANEX PACKAGE ${index+1} OUT`));assert(fact.legs[1].text.includes(`TEST ANEX PACKAGE ${index+1} BACK`));if(width<=760)assert(fact.legs.every(leg=>leg.font>=14),'canonical leg facts are readable at '+width);
    assert.equal(fact.selectedBadgeVisible,fact.checked,'only the selected exact pair has a visible badge');
    if(known.includes(index+1)){assert.equal(fact.price.replace(/[^0-9]/g,''),String(100000+(index+1)*1000),'known exact pair retains only its own whole-party amount');assert.match(fact.price,/Весь тур за всех/);}
    else{assert.doesNotMatch(fact.price.replace(/\s/g,''),/101000|102000|103000/,'unknown or pending pair never inherits another amount');assert.match(fact.price,/цен[ау] тура/i);if(!fact.checked)assert.match(fact.price,/требует расчёта/);}
   }
   if(verified){assert.equal(await page.locator('#modal-title').textContent(),'Цена тура подтверждена');assert.match(await selected.locator('.flight-option-price').textContent(),/Весь тур за всех/);assert((await selected.locator('.flight-option-price').textContent()).replace(/\s/g,'').includes(String(100000+n*1000)));assert.match(await page.locator('.flight-summary').textContent(),/Цена выбранного варианта подтверждена\. Можно выбрать другие рейсы\./);}
   else assert.match(await selected.locator('.flight-option-price').textContent(),/Уточняем цену тура с этими рейсами/);
   return facts;
  };
  const geometry=async()=>{
   const boxes=await page.locator('#modal').evaluate(modal=>{
    const rect=el=>{const b=el.getBoundingClientRect();return{x:b.x,y:b.y,right:b.right,bottom:b.bottom,width:b.width,height:b.height};},footer=document.querySelector('#modal-footer'),total=footer.querySelector('.footer-total'),amount=total.querySelector('strong'),button=footer.querySelector('.primary')||footer.querySelector('.secondary'),body=document.querySelector('#modal-body');
     const radios=[...body.querySelectorAll('[name="anex-package-choice"],[name="andromeda-outbound"],[name="andromeda-return"]')],focused=radios.find(input=>input===document.activeElement),choice=input=>({name:input.name,value:input.value,checked:input.checked,disabled:input.disabled,input:rect(input),row:rect(input.closest('.flight-option'))});
    return{modalOverflow:modal.scrollWidth>modal.clientWidth+1,bodyOverflow:body.scrollWidth>body.clientWidth+1,documentOverflow:document.documentElement.scrollWidth>innerWidth,body:rect(body),footer:rect(footer),total:rect(total),amount:rect(amount),button:rect(button),choices:[...document.querySelectorAll('.flight-option')].map(rect),selectedChoices:radios.filter(input=>input.checked).map(choice),focusedChoice:focused?choice(focused):null};
   });
   assert.equal(boxes.modalOverflow,false);assert.equal(boxes.bodyOverflow,false);assert.equal(boxes.documentOverflow,false);
   assert(boxes.amount.x>=boxes.total.x-1&&boxes.amount.right<=boxes.total.right+1,'whole pending/verified total fits at '+width);
   assert(boxes.button.x>=boxes.footer.x-1&&boxes.button.right<=boxes.footer.right+1&&boxes.button.height>=44,'current action stays in footer with touch target at '+width);
   assert(boxes.button.x>=boxes.total.right-1||boxes.button.y>=boxes.total.bottom-1,'price and action never overlap at '+width);
   assert(boxes.choices.every(choice=>choice.height>=44),'flight choices keep touch targets at '+width);
   return boxes;
  };
  try{
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
    if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
    try{
     const body=JSON.parse(request.postData()||'{}'),own=body.action===action,n=own?provider==='anex'?Number(body.choice_ref?.slice('anex_quote:'.length,'anex_quote:'.length+1)):(Number(body.flight_selection?.outbound_ref?.slice('flight_'.length,'flight_'.length+1))+1)/2:0;
     if(own&&n===3)transport.state.repricingFailure='unknown';
     const value=await transport.json(request.url(),{body:request.postData()});
     if(own&&n===2){markB();await new Promise(resolve=>releaseB=resolve);}
     if(own&&n===3){markUnknown();await new Promise(resolve=>releaseUnknown=resolve);}
     await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});
    }catch(error){forbidden.push(error.message);await route.abort();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));
   await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);assert.equal(count(),0);
   await page.locator('.search-submit').click();await page.waitForFunction(expected=>document.querySelector('#results-summary').textContent.includes(expected)&&document.querySelector('#search-status').hidden,provider==='andromeda'?'11 вариантов':'3 варианта');
   await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#all-offers-list').waitFor();
   if(provider==='andromeda'){
    await page.locator('.offer-filter-disclosure>summary').waitFor();
    if(await page.locator('.offer-filter-disclosure:not([open])').count())await page.locator('.offer-filter-disclosure>summary').click();
    await page.locator('#offer-room').selectOption('STANDARD SEA VIEW');await page.locator('[data-action="group-more"]').click();
    await page.waitForFunction(()=>Object.values(history.state?.['anytour.prototype.v18.ui.v1']?.limits||{}).includes(12));
    const offer=page.locator('#modal-body [data-action="offer"][data-key^="andromeda%3A"]');selectedOfferKey=await offer.getAttribute('data-key');await offer.scrollIntoViewIfNeeded();
    retainedList=await page.locator('#modal-body').evaluate(body=>({route:{...history.state['anytour.prototype.v18.ui.v1'],scroll:body.scrollTop,filtersOpen:body.querySelector('.offer-filter-disclosure').open},values:Object.fromEntries(['departure','flight','room','meal','sort'].map(field=>[field,body.querySelector('#offer-'+field).value])),offerKeys:[...body.querySelectorAll('.grouped-offer')].map(row=>row.dataset.offerKey),title:document.querySelector('#modal-title').textContent}));
    assert.equal(retainedList.route.id,501);assert.equal(retainedList.route.room,'STANDARD SEA VIEW');assert.equal(retainedList.route.filtersOpen,true);assert(retainedList.route.open.length>0);assert(Object.values(retainedList.route.limits).includes(12));assert.equal(retainedList.offerKeys.length,10);
   }
   await page.locator('#modal-body [data-action="offer"][data-key^="'+provider+'%3A"]').click();await page.locator('[data-action="refresh-hotel"]').click();
   if(provider==='anex'){await page.waitForFunction(()=>document.querySelector('#modal-body').textContent.includes('ANEX CONCRETE'));await page.locator('[data-action="select-anex-tour"]').click();}
   await page.locator(radio(1)).waitFor();await page.locator('[data-action="'+apply+'"]').click();await page.locator('[data-action="'+app+'"]').waitFor();await price(1);
    await page.locator('[data-action="'+edit+'"]').click();
    await page.locator(radio(2)).scrollIntoViewIfNeeded();await page.locator(radio(2)).focus();
    const beforePendingBoxes=await geometry();
    const initialPairClarity=await pairClarity(1,true);
    await page.locator(radio(2)).check();await pendingB;
   assert.equal(await page.locator('[data-action="'+app+'"]').count(),0,'changed pending pair removes application immediately');assert.match(await page.locator('#modal-footer').textContent(),/Цена уточняется/);assert.equal(await page.locator(radio(2)).isDisabled(),false);
   const pendingBoxes=await geometry(),pendingPairClarity=await pairClarity(2,false);if(provider==='anex')assert.match(await page.locator('#anex-package-status').textContent(),/полную цену тура с выбранным перелётом/);await page.screenshot({path:path.join(evidence,provider+'-reprice-pending-'+width+'.png')});
   releaseB();await page.locator('[data-action="'+app+'"]').waitFor();await price(2);assert.equal(await page.locator(radio(2)).isChecked(),true);
   const verifiedBoxes=await geometry(),verifiedPairClarity=await pairClarity(2,true,[1,2]);await page.screenshot({path:path.join(evidence,provider+'-reprice-edit-verified-'+width+'.png')});
    {
    assert(pendingBoxes.focusedChoice&&verifiedBoxes.focusedChoice,'focused flight survives both receipts at '+width);
     assert.equal(pendingBoxes.focusedChoice.name,provider==='anex'?'anex-package-choice':'andromeda-outbound');assert.equal(pendingBoxes.focusedChoice.value,ref(2));
    assert.equal(verifiedBoxes.focusedChoice.name,pendingBoxes.focusedChoice.name);assert.equal(verifiedBoxes.focusedChoice.value,pendingBoxes.focusedChoice.value);assert.equal(verifiedBoxes.focusedChoice.checked,true);assert.equal(verifiedBoxes.focusedChoice.disabled,false);
     assert(Math.abs(pendingBoxes.focusedChoice.row.y-beforePendingBoxes.focusedChoice.row.y)<=1,'focused B row retains its visual anchor when price becomes pending at '+width+' '+JSON.stringify({beforePendingBoxes,pendingBoxes,verifiedBoxes}));
    assert(Math.abs(verifiedBoxes.focusedChoice.row.y-pendingBoxes.focusedChoice.row.y)<=1,'focused B row retains its visual anchor at '+width);
    const focus=verifiedBoxes.focusedChoice.input;assert(focus.x>=verifiedBoxes.body.x-1&&focus.right<=verifiedBoxes.body.right+1&&focus.y>=verifiedBoxes.body.y-1&&focus.bottom<=Math.min(verifiedBoxes.body.bottom,verifiedBoxes.footer.y)+1,'focused flight stays visible after receipt at '+width);
     if(provider==='andromeda'){
      const pendingReturn=pendingBoxes.selectedChoices.find(choice=>choice.name==='andromeda-return'),verifiedReturn=verifiedBoxes.selectedChoices.find(choice=>choice.name==='andromeda-return');
      assert(pendingReturn&&verifiedReturn);assert.equal(verifiedReturn.value,pendingReturn.value);assert(Math.abs(verifiedReturn.row.y-pendingReturn.row.y)<=1,'selected return row retains its visual anchor at '+width);
      if(pendingReturn.row.y>=pendingBoxes.body.y-1&&pendingReturn.row.bottom<=Math.min(pendingBoxes.body.bottom,pendingBoxes.footer.y)+1)assert(verifiedReturn.row.y>=verifiedBoxes.body.y-1&&verifiedReturn.row.bottom<=Math.min(verifiedBoxes.body.bottom,verifiedBoxes.footer.y)+1,'initially visible selected return stays fully visible at '+width);
     }
   }
    if(provider==='anex')await page.locator(radio(2)).press('ArrowUp');else await page.locator(radio(1)).check();
    await page.locator('[data-action="'+app+'"]').waitFor();await price(1);assert.equal(count(),2,'A/B/A uses two mutations');
    if(provider==='anex'){
     assert.equal(await page.locator(radio(1)).isChecked(),true,'keyboard return selects the exact cached A pair');
     assert.equal(await page.locator(radio(1)).evaluate(input=>document.activeElement===input),true,'keyboard return retains focus on the current pair');
    }
   const comparisonPairClarity=await pairClarity(1,true,[1,2]),comparisonBoxes=await geometry(),beforeComparison=transport.calls.length;
   if(provider==='anex'){
    await page.screenshot({path:path.join(evidence,'anex-reprice-comparison-'+width+'.png')});
    await page.locator(radio(1)).press('ArrowDown');await page.locator('[data-action="'+app+'"]').waitFor();await price(2);await pairClarity(2,true,[1,2]);assert.equal(count(),2,'cached B2→2');
    await page.locator(radio(2)).press('ArrowUp');await page.locator('[data-action="'+app+'"]').waitFor();await price(1);await pairClarity(1,true,[1,2]);assert.equal(transport.calls.length,beforeComparison,'passive comparisons and cached selection spend no request');
   }
   const comparisonRequests=transport.calls.length-beforeComparison;assert.equal(comparisonRequests,0);
   await page.locator('[data-action="'+app+'"]').click();await page.locator('#prototype-lead-form').waitFor();await price(1);assert.match(await page.locator('#modal-body').textContent(),new RegExp(provider==='anex'?'TEST ANEX PACKAGE 1 OUT':'TEST SAMO 1'));
   const applicationSummary=await providerApplicationFlightSummary(page,width,provider==='anex'?[`TEST ANEX PACKAGE 1 OUT · Москва SVO → Анталья AYT · ${day} 10:00`,`TEST ANEX PACKAGE 1 BACK · Анталья AYT → Москва SVO · ${back} 14:00`]:['TEST SAMO 1','TEST SAMO 2'],evidence,provider+'-reprice');
   const fields=await page.locator('#prototype-lead-form>.form-row').first().locator('label').evaluateAll(labels=>labels.map(el=>{const b=el.getBoundingClientRect();return{x:b.x,y:b.y,right:b.right,bottom:b.bottom};}));
   assert.equal(fields.length,2);if(width<=760)assert(fields[1].y>=fields[0].bottom-1,'contacts stack on mobile');else assert(fields[1].x>=fields[0].right-1,'contacts keep separate columns on desktop');
   await geometry();await page.screenshot({path:path.join(evidence,provider+'-reprice-application-'+width+'.png')});
   const beforeBack=transport.calls.length;await page.locator('#modal-back').click();await page.locator(radio(1)).waitFor();assert.equal(transport.calls.length,beforeBack,'application return is passive');
   await page.locator(radio(3)).check();await pendingUnknown;await page.locator(radio(1)).check();
   assert.equal(await page.locator('[data-action="'+app+'"]').count(),0,'cached A remains unavailable while C mutates');assert.match(await page.locator('#modal-footer').textContent(),/Цена уточняется/);assert.equal(count(),3);
   const cachedPendingBoxes=await geometry();await pairClarity(1,false,[]);await page.screenshot({path:path.join(evidence,provider+'-reprice-cached-pending-'+width+'.png')});
   releaseUnknown();await page.waitForFunction(({app,error})=>!document.querySelector('[data-action="'+app+'"]')&&document.querySelector(error)?.textContent.length>0,{app,error:provider==='anex'?'#anex-package-status':'#andromeda-quote-error'});
   assert.doesNotMatch((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/101000|103000/);
   if(provider==='anex'){assert.equal(await page.locator('#modal-footer .footer-total strong').textContent(),'Цена уточняется');assert.match(await page.locator('#modal-footer .footer-price-status').textContent(),/Цена требует подтверждения/);assert.doesNotMatch((await page.locator('.flight-summary').textContent()).replace(/\s/g,''),/101000|102000|103000/,'UNKNOWN cannot expose a previous pair total as current');for(const row of await page.locator('.flight-option-price').allTextContents())assert.doesNotMatch(row.replace(/\s/g,''),/101000|102000|103000/,'UNKNOWN also removes every retained comparison amount');}
   await geometry();await page.screenshot({path:path.join(evidence,provider+'-reprice-unknown-'+width+'.png')});
   if(provider==='andromeda'){
    const reason=await page.locator('#andromeda-quote-error').evaluate(error=>{const body=document.querySelector('#modal-body'),r=error.getBoundingClientRect(),b=body.getBoundingClientRect(),footer=document.querySelector('#modal-footer').getBoundingClientRect();return{text:error.textContent,first:body.firstElementChild===error,childElements:error.childElementCount,role:error.getAttribute('role'),visible:r.width>0&&r.height>0&&r.top>=b.top-1&&r.bottom<=Math.min(b.bottom,footer.top)+1,scroll:body.scrollTop};});
    assert.match(reason.text,/Цена и наличие пока неизвестны/);assert.equal(reason.first,true);assert.equal(reason.childElements,0,'human failure reason is rendered as escaped text');assert.equal(reason.role,'alert');assert.equal(reason.visible,true,'UNKNOWN human reason is immediately in the body viewport at '+width);assert.equal(reason.scroll,0);assert.equal(await page.locator('#modal-footer .footer-price-status').textContent(),reason.text);
    const options=page.locator('#modal-footer [data-action="all-offers"][data-id="501"]');assert.equal(await options.count(),1);assert.equal(await options.isEnabled(),true);assert.equal(await options.isVisible(),true);assert.equal(await page.locator('[data-action="'+apply+'"]').count(),0,'sealed picker has no apply action');
    assert.equal(await page.locator('[name="andromeda-outbound"]:not(:disabled),[name="andromeda-return"]:not(:disabled)').count(),0);
    const beforeOptions=transport.calls.length;await options.click();await page.locator('#all-offers-list').waitFor();
    const restoredList=await page.locator('#modal-body').evaluate(body=>({route:{...history.state['anytour.prototype.v18.ui.v1'],scroll:body.scrollTop,filtersOpen:body.querySelector('.offer-filter-disclosure').open},values:Object.fromEntries(['departure','flight','room','meal','sort'].map(field=>[field,body.querySelector('#offer-'+field).value])),offerKeys:[...body.querySelectorAll('.grouped-offer')].map(row=>row.dataset.offerKey),title:document.querySelector('#modal-title').textContent}));
    assert(Math.abs(restoredList.route.scroll-retainedList.route.scroll)<=1,'passive options return retains body scroll at '+width);assert.deepEqual({...restoredList,route:{...restoredList.route,scroll:retainedList.route.scroll}},retainedList,'passive options return retains same hotel, full filters, sort, open groups and limits at '+width);assert.equal(transport.calls.length,beforeOptions,'options return issues zero requests');
    await page.screenshot({path:path.join(evidence,'andromeda-reprice-unknown-options-'+width+'.png')});
    await page.goBack();await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');assert.equal(await page.locator('[name="andromeda-outbound"]').count(),0,'browser Back cannot immediately reopen the sealed picker');assert.equal(transport.calls.length,beforeOptions);
    await page.goForward();await page.locator('#all-offers-list').waitFor();assert.equal(transport.calls.length,beforeOptions,'options-list Forward is passive');
    const forwardList=await page.locator('#modal-body').evaluate(body=>({route:{...history.state['anytour.prototype.v18.ui.v1'],scroll:body.scrollTop,filtersOpen:body.querySelector('.offer-filter-disclosure').open},values:Object.fromEntries(['departure','flight','room','meal','sort'].map(field=>[field,body.querySelector('#offer-'+field).value])),offerKeys:[...body.querySelectorAll('.grouped-offer')].map(row=>row.dataset.offerKey),title:document.querySelector('#modal-title').textContent}));
    assert(Math.abs(forwardList.route.scroll-retainedList.route.scroll)<=1);assert.deepEqual({...forwardList,route:{...forwardList.route,scroll:retainedList.route.scroll}},retainedList,'Forward restores the retained options list');
    await page.locator('#all-offers-list [data-action="offer"][data-key="'+selectedOfferKey+'"]').click();await page.locator('#andromeda-quote-error').waitFor();assert.equal(await page.locator('#andromeda-quote-error').textContent(),reason.text);assert.equal(await page.locator('[data-action="'+app+'"]').count(),0);assert.equal(await page.locator('[data-action="'+apply+'"]').count(),0);assert.equal(transport.calls.length,beforeOptions,'reopening the sealed offer never requotes');assert.doesNotMatch((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/101000|103000/);
    unknownRecovery={reason_visible:reason.visible,reason_escaped:reason.childElements===0,options_requests:transport.calls.length-beforeOptions,back_skips_sealed_picker:true,retainedList,restoredList,forwardList};
   }else{
    if(await page.locator('#modal-back').isVisible()){await page.locator('#modal-back').click();assert.equal(await page.locator('[data-action="'+app+'"]').count(),0);assert.doesNotMatch((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/101000|103000/);}
    const beforeHistory=transport.calls.length;await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');await page.goForward();await page.waitForFunction(()=>document.querySelector('#modal').open);
    assert.equal(transport.calls.length,beforeHistory,'UNKNOWN Forward does not replay');assert.equal(await page.locator('[data-action="'+app+'"]').count(),0);assert.doesNotMatch((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/101000|103000/);
   }
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);assert.deepEqual(aborts,[]);
   fs.writeFileSync(path.join(evidence,provider+'-reprice-'+width+'.json'),JSON.stringify({width,provider,A_B_A_mutations:2,total_mutations:count(),cached_while_mutating_application:false,global_UNKNOWN_sealed:true,passive_history_requests:0,comparison_requests:comparisonRequests,beforePendingBoxes,pendingBoxes,verifiedBoxes,cachedPendingBoxes,comparisonBoxes,initialPairClarity,pendingPairClarity,verifiedPairClarity,comparisonPairClarity,applicationSummary,...(unknownRecovery?{unknownRecovery}:{}),supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));
  }finally{releaseB?.();releaseUnknown?.();await context.close();}
 }
 console.log('PASS compiled bounded flight repricing: ANEX/SAMO at five widths, cached return, pending and UNKNOWN application guards');
};
const applicationViewportLayout=async(page,width,transport,evidence,label,stress=true)=>{
 const form=page.locator('#prototype-lead-form'),amount=page.locator('#modal-footer .footer-total>strong'),phone=form.locator('[name="phone"]'),originalAmount=await amount.textContent(),originalPhone=await phone.inputValue(),beforeRequests=transport.calls.length,states=[];
 const fields=()=>form.evaluate(form=>[...form.querySelectorAll('input,textarea')].map(el=>({name:el.name,value:el.value,checked:el.checked}))),originalFields=await fields();
 const inspect=()=>page.locator('#modal').evaluate(modal=>{
  const rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};},textRects=el=>{const range=document.createRange();range.selectNodeContents(el);return[...range.getClientRects()].map(r=>({x:r.x,y:r.y,right:r.right,bottom:r.bottom}));},footer=modal.querySelector('#modal-footer'),price=footer.querySelector('.footer-total'),action=footer.querySelector('.primary'),body=modal.querySelector('#modal-body'),input=modal.querySelector('[name="phone"]');
  return{modal:rect(modal),footer:rect(footer),price:rect(price),amount:rect(price.querySelector('strong')),amountText:price.querySelector('strong').textContent,amountRects:textRects(price.querySelector('strong')),caption:rect(price.querySelector('small')),stayFacts:[...body.querySelectorAll('.chosen-stay dt,.chosen-stay dd')].map(el=>({box:rect(el),text:el.textContent,textRects:textRects(el)})),action:rect(action),actionRects:textRects(action),body:rect(body),phone:rect(input),phoneFocused:input===document.activeElement,caret:[input.selectionStart,input.selectionEnd],scroll:body.scrollTop,bodyTextSpill:(()=>{const walker=document.createTreeWalker(body,NodeFilter.SHOW_TEXT),spills=[];let node;while(node=walker.nextNode()){if(!node.textContent.trim())continue;const range=document.createRange();range.selectNodeContents(node);for(const box of range.getClientRects())if(box.right>body.getBoundingClientRect().right+1)spills.push({parent:node.parentElement.className,text:node.textContent.slice(0,70),right:box.right});}return spills;})(),bodySpill:[...body.querySelectorAll('*')].filter(el=>el.getBoundingClientRect().right>body.getBoundingClientRect().right+1).map(el=>({tag:el.tagName,class:el.className,text:el.textContent.slice(0,70),right:el.getBoundingClientRect().right})),bodyOverflow:body.scrollWidth>body.clientWidth+1,modalOverflow:modal.scrollWidth>modal.clientWidth+1,documentOverflow:document.documentElement.scrollWidth>innerWidth,keyboard:modal.classList.contains('application-keyboard'),viewport:{top:visualViewport.offsetTop,height:visualViewport.height}};
 });
 const contains=(parent,child)=>child.x>=parent.x-1&&child.right<=parent.right+1&&child.y>=parent.y-1&&child.bottom<=parent.bottom+1;
 const verify=async(state,keyboard=false)=>{
  const b=await inspect();states.push({state,...b});fs.writeFileSync(path.join(evidence,`application-${label}-${state}-${width}.json`),JSON.stringify(b,null,2));
  assert(b.amountRects.length&&b.amountRects.every(r=>contains(b.amount,r)&&contains(b.price,r)&&contains(b.footer,r)),label+' complete whole-party amount remains visible at '+width+' '+state+': '+JSON.stringify(b));
  assert(contains(b.footer,b.action)&&contains(b.footer,b.caption)&&b.actionRects.every(r=>contains(b.action,r)),label+' complete action and price status stay inside the footer at '+width+' '+state);assert(b.action.height>=48);
  for(const fact of b.stayFacts){assert(fact.textRects.every(r=>contains(fact.box,r)),label+' complete stay fact fits its own cell at '+width+' '+state+': '+JSON.stringify(fact));if(state==='closed'&&fact.text==='Размещение')assert.equal(fact.textRects.length,1,'ordinary placement label remains readable without splitting its last letter');}
  assert(b.phone.height>=48,label+' actual canonical phone control is a48px target');assert.equal(b.bodyOverflow,false,label+' body overflow at '+width+' '+state+': '+JSON.stringify(b));assert.equal(b.modalOverflow,false);assert.equal(b.documentOverflow,false);
  if(keyboard){assert.equal(b.keyboard,true,label+' uses the existing viewport owner');assert(b.modal.y>=b.viewport.top-1&&b.modal.bottom<=b.viewport.top+b.viewport.height+1,label+' confirmation stays above the modeled keyboard at '+width+': '+JSON.stringify(b));assert(contains(b.body,b.phone),label+' focused phone field is revealed in the scrollable body');assert.equal(b.phoneFocused,true);assert.deepEqual(b.caret,[3,8]);}
  await page.screenshot({path:path.join(evidence,`application-${label}-${state}-${width}.png`)});return b;
 };
 await page.evaluate(()=>{window.__applicationViewportAcceptance={descriptor:Object.getOwnPropertyDescriptor(window,'visualViewport'),native:window.visualViewport,fontSize:document.documentElement.style.fontSize,footerPadding:document.querySelector('#modal-footer').style.paddingBottom};});
 try{
  await verify('closed');
  if(width<=760){
   await phone.fill('+7 999 123-45-67');await phone.evaluate(el=>el.setSelectionRange(3,8));
   await page.evaluate(()=>{const state=window.__applicationViewportAcceptance,viewport=new EventTarget();viewport.height=420;viewport.offsetTop=25;Object.defineProperty(window,'visualViewport',{value:viewport,configurable:true});document.querySelector('#modal-footer').style.paddingBottom='34px';state.native.dispatchEvent(new Event('resize'));});
   const open=await verify('keyboard',true);
   await page.evaluate(()=>{visualViewport.offsetTop=35;window.__applicationViewportAcceptance.native.dispatchEvent(new Event('scroll'));});
   const moved=await inspect();assert.equal(moved.phoneFocused,true);assert.deepEqual(moved.caret,[3,8]);assert(Math.abs(moved.scroll-open.scroll)<=1,'viewport pan does not reset body scroll');assert.equal(await phone.inputValue(),'+7 999 123-45-67');
   if(label==='TV'&&width===390){
    const draft=await fields(),clearViewport=()=>page.locator('#modal').evaluate(modal=>({keyboard:modal.classList.contains('application-keyboard'),top:modal.style.getPropertyValue('--modal-viewport-top'),height:modal.style.getPropertyValue('--modal-viewport-height')}));
    await page.locator('#modal-back').click();await page.locator('.tour-main-details>.chosen-stay').waitFor();assert.equal(await amount.textContent(),originalAmount);assert.deepEqual(await clearViewport(),{keyboard:false,top:'',height:''},'Back clears application viewport state on the retained tour');assert.match(await page.locator('.flight-summary').textContent(),/TEST201/);
    await page.locator('[data-action="confirm-tour"]').click();await form.waitFor();assert.equal(await amount.textContent(),originalAmount);assert.deepEqual(await fields(),draft,'same-tour application Back/reopen keeps contact and consent draft');
    await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');await page.evaluate(()=>window.__applicationViewportAcceptance.native.dispatchEvent(new Event('scroll')));assert.deepEqual(await clearViewport(),{keyboard:false,top:'',height:''},'late viewport events do not restore a closed application');
    await page.goForward();await form.waitFor();assert.equal(await amount.textContent(),originalAmount);assert.deepEqual(await fields(),draft,'passive Forward restores the same exact application draft');
    await phone.evaluate(el=>{el.focus({preventScroll:true});el.setSelectionRange(3,8);});await page.evaluate(()=>window.__applicationViewportAcceptance.native.dispatchEvent(new Event('resize')));await verify('keyboard-history-return',true);
    // Forward intentionally restores one route, without the modal step stack.
    // Re-enter through the retained exact offer for the remaining Back checks.
    await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#modal-body [data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]').click();await page.locator('[data-action="confirm-tour"]').click();await form.waitFor();assert.deepEqual(await fields(),draft);assert.equal(await amount.textContent(),originalAmount);
    await phone.evaluate(el=>{el.focus({preventScroll:true});el.setSelectionRange(3,8);});await page.evaluate(()=>window.__applicationViewportAcceptance.native.dispatchEvent(new Event('resize')));await verify('keyboard-reopened',true);
   }
   await page.evaluate(()=>{const state=window.__applicationViewportAcceptance;Object.defineProperty(window,'visualViewport',state.descriptor);document.querySelector('#modal-footer').style.paddingBottom=state.footerPadding;state.native.dispatchEvent(new Event('resize'));});
   const closed=await verify('reclosed');assert.equal(closed.keyboard,false);assert.equal(closed.phoneFocused,true);assert.deepEqual(closed.caret,[3,8]);
  }
  if(stress){
   // DOM text/viewport stress is not physical iPhone text zoom or a price quote.
   await page.evaluate(()=>document.documentElement.style.fontSize='32px');
   await amount.evaluate(el=>el.textContent='1\u00a0035\u00a0282,99 ₽');await verify('text200-long-total');
  }
 }catch(error){
  fs.writeFileSync(path.join(evidence,`application-${label}-failure-${width}.json`),JSON.stringify({error:String(error),url:page.url(),modal:await page.locator('#modal').innerHTML()},null,2));await page.screenshot({path:path.join(evidence,`application-${label}-failure-${width}.png`)});throw error;
 }finally{
  if(await amount.count())await amount.evaluate((el,text)=>el.textContent=text,originalAmount);if(await phone.count())await phone.fill(originalPhone);
  await page.evaluate(()=>{const state=window.__applicationViewportAcceptance;Object.defineProperty(window,'visualViewport',state.descriptor);document.documentElement.style.fontSize=state.fontSize;document.querySelector('#modal-footer').style.paddingBottom=state.footerPadding;state.native.dispatchEvent(new Event('resize'));delete window.__applicationViewportAcceptance;});
 }
 assert.equal(await amount.textContent(),originalAmount);assert.deepEqual(await fields(),originalFields,'viewport testing preserves the complete contact and consent draft');assert.equal(transport.calls.length,beforeRequests,'viewport/text/focus changes never invoke quote, supplier or lead operations');
 fs.writeFileSync(path.join(evidence,`application-${label}-viewport-${width}.json`),JSON.stringify({width,states,restoredAmount:originalAmount,restoredPhone:originalPhone,viewportAndTextModel:true,safeAreaPaddingModel:width<=760,physicalSafari:false,supplier_HTTP:0,real_leads:0},null,2));
};
const quoteRetryAndRoomReturnJourney=async(browser,origin,base,evidence)=>{
 const widths=[360,390,430,768,1280];
 for(let offset=0;offset<widths.length;offset+=2){
  await Promise.all(widths.slice(offset,offset+2).map(async width=>{
  const transport=fixture({tvFuel:20686}),errors=[],forbidden=[],held=new Map(),releases=[],context=await browser.newContext({viewport:{width,height:650}}),page=await context.newPage();
  const longRoom='FICTIONAL STANDARD SEA VIEW WITH SEPARATE LIVING ROOM AND PRIVATE TERRACE FOR THE EXACT SELECTED TOUR',longOperator='Вымышленный оператор · длинное каноническое название для проверки условий тура';let longStay=false;
  page.setDefaultTimeout(10000);page.on('pageerror',error=>errors.push(error.message));
  const exact='[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]',requests=()=>transport.calls.filter(call=>call.url==='/api-v2.php'&&['tour','flights'].includes(call.action)).length;
  const hold=(action,fail=false)=>{let started,release;const pending=new Promise(resolve=>started=resolve),gate=new Promise(resolve=>release=resolve);releases.push(release);held.set(action,{started,gate,fail});return{pending,release};};
  const position=()=>page.locator('#modal-body').evaluate(body=>{
   const active=document.activeElement,rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
   return{scroll:body.scrollTop,max:body.scrollHeight-body.clientHeight,focus:active.id||active.dataset.action||active.tagName,focusTop:body.contains(active)?active.getBoundingClientRect().top-body.getBoundingClientRect().top:null,body:rect(body),footer:rect(document.querySelector('#modal-footer')),modalOverflow:document.querySelector('#modal').scrollWidth>document.querySelector('#modal').clientWidth+1,bodyOverflow:body.scrollWidth>body.clientWidth+1,documentOverflow:document.documentElement.scrollWidth>innerWidth};
  });
  const bodyAnchor=async()=>{await page.locator('[data-action="change-room"]').evaluate(button=>button.focus({preventScroll:true}));await page.locator('#modal-body').evaluate(body=>body.scrollTop=Math.min(183,body.scrollHeight-body.clientHeight));return position();};
  const retainedPosition=(before,after,label)=>{assert.equal(after.focus,'change-room',label+' keeps the actual body control focused at '+width);assert(Math.abs(after.scroll-before.scroll)<=1,label+' retains modal scroll at '+width);assert.equal(after.modalOverflow,false);assert.equal(after.bodyOverflow,false);assert.equal(after.documentOverflow,false);};
  const startSearch=async()=>{await page.locator('.search-submit:not(:disabled)').waitFor();await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelector('.hotel-card')&&document.querySelector('#search-status').hidden);};
  const openTv=async()=>{await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#modal-body '+exact).click();await page.locator('[data-action="start-lead"]').waitFor();};
  const wholePrice=async selector=>Number((await page.locator(selector).textContent()).replace(/[^\d,.-]/g,'').replace(',','.'));
  const stay=selector=>page.locator(selector).evaluate(section=>({trip:section.querySelector('.chosen-trip').textContent.replace(/\s+/g,' ').trim(),facts:Object.fromEntries([...section.querySelectorAll('.saved-stay-summary>div')].map(row=>[row.querySelector('dt').textContent.trim(),row.querySelector('dd').textContent.trim()]))}));
  const flightContext=async(expected,label)=>{
   const section=page.locator('.flight-picker-context .chosen-stay');assert.equal(await section.count(),1,label+' retains the canonical chosen room, meal and operator at '+width);assert.deepEqual(await stay('.flight-picker-context .chosen-stay'),expected,label+' preserves the exact stay and party');
   const boxes=await page.locator('.flight-picker-context').evaluate(context=>{const rect=el=>{const b=el.getBoundingClientRect();return{x:b.x,right:b.right,y:b.y,bottom:b.bottom,width:b.width,height:b.height};},textRects=el=>{const range=document.createRange();range.selectNodeContents(el);return[...range.getClientRects()].map(b=>({x:b.x,right:b.right,y:b.y,bottom:b.bottom,width:b.width,height:b.height}));},footer=document.querySelector('#modal-footer');return{context:rect(context),facts:[...context.querySelectorAll('dd')].map(rect),prices:[...document.querySelectorAll('.flight-option')].map(row=>({row:rect(row),price:rect(row.querySelector('.flight-price')),text:row.querySelector('.flight-price>strong').textContent,textRects:textRects(row.querySelector('.flight-price>strong'))})),footer:{box:rect(footer),total:rect(footer.querySelector('.flight-selection-total')),text:document.querySelector('#flight-total').textContent,textRects:textRects(document.querySelector('#flight-total')),action:rect(footer.querySelector('[data-action="apply-flight"]')),caption:rect(footer.querySelector('.flight-selection-caption'))},bodyOverflow:document.querySelector('#modal-body').scrollWidth>document.querySelector('#modal-body').clientWidth+1,modalOverflow:document.querySelector('#modal').scrollWidth>document.querySelector('#modal').clientWidth+1,documentOverflow:document.documentElement.scrollWidth>innerWidth};});
   assert(boxes.facts.every(box=>box.height>0&&box.x>=boxes.context.x-1&&box.right<=boxes.context.right+1),label+' wraps complete stay facts inside their context at '+width);assert.equal(boxes.bodyOverflow,false);assert.equal(boxes.modalOverflow,false);assert.equal(boxes.documentOverflow,false);
   const contains=(parent,child)=>child.x>=parent.x-1&&child.right<=parent.right+1&&child.y>=parent.y-1&&child.bottom<=parent.bottom+1,intersects=(a,b)=>a.x<b.right-1&&a.right>b.x+1&&a.y<b.bottom-1&&a.bottom>b.y+1;
   for(const price of boxes.prices)assert(price.textRects.length&&price.textRects.every(text=>contains(price.price,text)&&contains(price.row,text)),label+' shows the complete row amount or unknown status at '+width+': '+JSON.stringify(price));
   const footer=boxes.footer;assert(footer.textRects.length&&footer.textRects.every(text=>contains(footer.box,text)&&contains(footer.total,text)&&!intersects(text,footer.action)),label+' keeps the full footer amount/status beside its action at '+width+': '+JSON.stringify(footer));assert(contains(footer.box,footer.action)&&contains(footer.box,footer.caption),label+' keeps confirmation and selection caption inside the footer');assert(!intersects(footer.total,footer.action)&&!intersects(footer.caption,footer.total)&&!intersects(footer.caption,footer.action),label+' footer siblings never overlap at '+width+': '+JSON.stringify(footer));
   assert.match(await page.locator('.flight-picker-note').textContent(),/цена всего тура за всех туристов/);assert.deepEqual(await page.locator('.flight-price>small').allTextContents(),['весь тур за всех','весь тур за всех','весь тур за всех']);return boxes;
  };
  try{
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
    if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
    try{
     const action=url.pathname==='/api-v2.php'?url.searchParams.get('action'):null,control=held.get(action),value=await transport.json(request.url(),{body:request.postData()});
     // Keep fictional transport in the existing fixture boundary: missing pair
     // money is not zero or an inherited tour total, and long stay facts retain
     // the same exact tour ID in both its listing and accepted quote.
     if(action==='flights')value.push({...value[1],price:null,forward:value[1].forward.map(leg=>({...leg,number:'TEST UNPRICED 301'})),backward:value[1].backward.map(leg=>({...leg,number:'TEST UNPRICED 302'}))});
     if(longStay&&action==='search_results')for(const hotel of value)hotel.tours=hotel.tours.map(offer=>offer.id==='visual-tv-101'?{...offer,roomType:longRoom,operator:{...offer.operator,name:longOperator}}:offer);
     if(longStay&&action==='tour'){value.roomType=longRoom;value.operator={...value.operator,name:longOperator};}
     if(control){held.delete(action);control.started();await control.gate;}
     await route.fulfill({status:control?.fail||value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(control?.fail?{error:'Fictional temporary quote failure'}:value)});
    }catch(error){forbidden.push(error.message);await route.abort();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));await startSearch();await openTv();
   // A disabled primary action yields focus to the title, then only resumes
   // if the visitor has not moved to another control during the pending step.
   const firstFailure=hold('tour',true);await page.locator('[data-action="start-lead"]').click();await firstFailure.pending;
   await page.waitForFunction(()=>document.activeElement===document.querySelector('#modal-title'));
   const pendingFooter=await position();assert.equal(pendingFooter.focus,'modal-title');assert(await page.locator('#modal-footer .primary').isDisabled());
   await page.screenshot({path:path.join(evidence,`quote-pending-action-${width}.png`)});
   firstFailure.release();await page.locator('#modal-body .error-text').waitFor();await page.waitForFunction(()=>document.activeElement?.dataset.action==='start-lead');
   assert.match(await page.locator('#modal-body .error-text').textContent(),/Fictional temporary quote failure/);assert.equal(await page.locator('#prototype-lead-form').count(),0);
   const resumedFooter=await position();assert.equal(resumedFooter.focus,'start-lead');assert.equal(await page.locator('[data-action="start-lead"]').isDisabled(),false);
   const movedFailure=hold('tour',true);await page.locator('[data-action="start-lead"]').click();await movedFailure.pending;
   await page.waitForFunction(()=>document.activeElement===document.querySelector('#modal-title'));
   const movedBefore=await bodyAnchor();assert(movedBefore.scroll>0,'the body anchor exercises real scroll at '+width);movedFailure.release();await page.locator('#modal-body .error-text').waitFor();
   const movedAfter=await position();retainedPosition(movedBefore,movedAfter,'User-moved pending failure');
   await page.screenshot({path:path.join(evidence,`quote-retry-error-${width}.png`)});
   const successfulRetry=hold('tour'),beforeRetry=requests();await page.locator('[data-action="start-lead"]').click();await successfulRetry.pending;successfulRetry.release();
   await page.locator('#prototype-lead-form').waitFor();assert.equal(await page.locator('#modal-body .error-text').count(),0);assert.equal(await wholePrice('#modal-footer .footer-total strong'),120000);assert.equal(requests(),beforeRetry+1,'explicit retry quotes only once');
   await page.screenshot({path:path.join(evidence,`quote-retry-application-${width}.png`)});
   await page.locator('#modal-back').click();assert.equal(await page.locator('#modal-body .error-text').count(),0);assert.equal(await page.locator('[data-action="confirm-tour"]').isDisabled(),false);assert.equal(await page.locator('[data-action="start-lead"]').count(),0);
   assert.equal(await page.locator('#modal').getAttribute('data-offer-key'),'tourvisor%3Avisual-tv-101');
   await page.locator('[data-action="close-modal"]').click();await editResultSearch(page,width);await startSearch();await openTv();
   // Hold flights separately: these assertions observe the same offer redraw,
   // before its intentional transition to the actual flight picker.
   const chosenStay=await stay('.tour-main-details>.chosen-stay');assert.equal(chosenStay.facts['Номер'],'STANDARD SEA VIEW');assert.equal(chosenStay.facts['Питание'],'Всё включено');assert.equal(chosenStay.facts['Оператор'],'ANEX');assert.match(chosenStay.trip,/7 ночей/);assert.match(chosenStay.trip,/2 взр\./);
   const successfulQuote=hold('tour'),pendingFlights=hold('flights');await page.locator('[data-action="start-tour-flights"]').click();await successfulQuote.pending;
   const receiptBefore=await bodyAnchor();assert(receiptBefore.scroll>0);successfulQuote.release();await pendingFlights.pending;
   await page.waitForFunction(()=>document.querySelector('.flight-summary [role="status"]')?.textContent.includes('Загружаем варианты рейсов'));
   const receiptAfter=await position();retainedPosition(receiptBefore,receiptAfter,'Successful same-offer receipt');assert.equal(await page.locator('#modal').getAttribute('data-offer-key'),'tourvisor%3Avisual-tv-101');assert.equal(await page.locator('[name="flight-pair"]').count(),0);
   await page.screenshot({path:path.join(evidence,`quote-same-offer-position-${width}.png`)});
   pendingFlights.release();await page.locator('[name="flight-pair"][value="0"]').waitFor();assert.equal(await page.locator('#modal-body').evaluate(body=>body.scrollTop),0,'new flight step resets its own scroll');assert.equal(await page.locator('#modal-title').evaluate(title=>title===document.activeElement),true);
   const beforeLocalFlights=requests(),contextA=await flightContext(chosenStay,'TV pair A');assert.equal(await wholePrice('#flight-total'),120000);assert.equal(await page.locator('[name="flight-pair"][value="0"]').isChecked(),true);await page.screenshot({path:path.join(evidence,`tv-flight-context-A-${width}.png`)});
   await page.locator('[name="flight-pair"][value="1"]').check();const contextB=await flightContext(chosenStay,'TV draft pair B');assert.equal(await wholePrice('#flight-total'),133500.5);assert.equal(await page.locator('[name="flight-pair"][value="1"]').evaluate(input=>document.activeElement===input),true);assert.equal(requests(),beforeLocalFlights,'local TV pair selection never quotes or loads another flight batch');await page.screenshot({path:path.join(evidence,`tv-flight-context-B-${width}.png`)});
   await page.locator('#modal-back').click();assert.equal(await wholePrice('#detail-total'),120000,'Cancel keeps the applied A whole-tour total');assert.deepEqual(await stay('.tour-main-details>.chosen-stay'),chosenStay);
   await page.locator('[data-action="choose-flight"]').click();await page.locator('[name="flight-pair"][value="0"]').waitFor();assert.equal(await page.locator('[name="flight-pair"][value="0"]').isChecked(),true,'Cancel restores applied pair A');await flightContext(chosenStay,'TV cancelled draft');
   await page.locator('[name="flight-pair"][value="1"]').check();await page.locator('[data-action="apply-flight"]').click();await page.locator('#prototype-lead-form').waitFor();assert.equal(await wholePrice('#modal-footer .footer-total strong'),133500.5,'B authoritative total includes fuel without adding it again');assert.deepEqual(await stay('.application-choice>.chosen-stay'),chosenStay);assert.match(await page.locator('.summary-flight-details').textContent(),/TEST201/);assert.match(await page.locator('.summary-flight-details').textContent(),/TEST202/);
   await applicationViewportLayout(page,width,transport,evidence,'TV');
   await page.locator('#modal-back').click();assert.equal(await wholePrice('#detail-total'),133500.5);assert.deepEqual(await stay('.tour-main-details>.chosen-stay'),chosenStay);await page.locator('[data-action="choose-flight"]').click();await page.locator('[name="flight-pair"][value="1"]').waitFor();assert.equal(await page.locator('[name="flight-pair"][value="1"]').isChecked(),true);await flightContext(chosenStay,'TV application return');
   await page.locator('[name="flight-pair"][value="2"]').check();const unknownContext=await flightContext(chosenStay,'TV pair without money');assert.equal(await page.locator('#flight-total').textContent(),'Цена уточняется');assert.equal(await page.locator('.flight-option[data-flight-index="2"] .flight-price>strong').textContent(),'Цена уточняется');assert.equal(await page.locator('[data-action="apply-flight"]').isDisabled(),true);assert.equal(await page.locator('#flight-price-change').textContent(),'Цена всего тура с этими рейсами пока не подтверждена. Выберите другой вариант.');assert.doesNotMatch(await page.locator('#modal-footer').textContent(),/133\s*500|120\s*000/);assert.equal(await page.locator('[name="flight-pair"][value="2"]').evaluate(input=>document.activeElement===input),true);await page.screenshot({path:path.join(evidence,`tv-flight-context-unknown-${width}.png`)});
   await page.locator('#modal-back').click();assert.equal(await wholePrice('#detail-total'),133500.5,'unknown draft cannot replace applied B');await page.locator('[data-action="choose-flight"]').click();await page.locator('[name="flight-pair"][value="1"]').waitFor();assert.equal(await page.locator('[name="flight-pair"][value="1"]').isChecked(),true);await flightContext(chosenStay,'TV unknown Cancel');await page.locator('#modal-back').click();assert.equal(requests(),beforeLocalFlights,'TV Cancel, Apply, application Back and warm reopen remain local');
   await page.locator('[data-action="close-modal"]').click();await editResultSearch(page,width);transport.state.wideFacets=true;await startSearch();
   await page.locator('[data-action="hotel-details"][data-id="501"]').first().click();const room=page.locator('.room-overview[data-room="STANDARD SEA VIEW"]');await room.locator(':scope>summary').click();const roomOffer=room.locator(exact);await roomOffer.click();
   await page.locator('[data-action="start-tour-flights"]').click();await page.locator('[name="flight-pair"][value="1"]').check();assert.equal(await wholePrice('#flight-total'),133500.5);await page.locator('[data-action="apply-flight"]').click();await page.locator('#prototype-lead-form').waitFor();assert.equal(await wholePrice('#modal-footer .footer-total strong'),133500.5);
   await page.locator('#modal-back').click();assert.equal(await wholePrice('#detail-total'),133500.5);assert.match(await page.locator('.flight-summary').textContent(),/TEST201/);const beforeRoomReturn=requests();
   await page.locator('[data-action="change-room"]').click();await page.locator('#hotel-room-count').waitFor();assert(await room.evaluate(el=>el.open));assert.equal(requests(),beforeRoomReturn);
   assert.equal(await roomOffer.evaluate(button=>button===document.activeElement),true,'room return focuses the same exact tour');await roomOffer.click();assert.equal(await wholePrice('#detail-total'),133500.5);assert.match(await page.locator('.flight-summary').textContent(),/TEST201/);assert.equal(requests(),beforeRoomReturn,'room return and exact reopen never quote or load flights');
   await page.locator('[data-action="choose-flight"]').click();assert.equal(await page.locator('[name="flight-pair"][value="1"]').isChecked(),true);await page.locator('#modal-back').click();assert.equal(await wholePrice('#detail-total'),133500.5);
   await page.screenshot({path:path.join(evidence,`hotel-room-selected-return-${width}.png`)});
   await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();const selected=page.locator('.grouped-offer.is-selected[data-offer-key="tourvisor%3Avisual-tv-101"]');await selected.waitFor();assert.match(await selected.locator('.offer-selected').textContent(),/Выбран/);assert.equal(await wholePrice('.grouped-offer.is-selected .offer-price>strong'),133500.5);await selected.locator(exact).click();assert.equal(await wholePrice('#detail-total'),133500.5);assert.equal(requests(),beforeRoomReturn);
   await page.locator('[data-action="change-room"]').click();await page.locator('#modal-body [data-action="offer"][data-key="tourvisor%3Aoperator-tour-0"]').click();assert.equal(await page.locator('#modal').getAttribute('data-offer-key'),'tourvisor%3Aoperator-tour-0');assert.equal(await page.locator('#modal-body').evaluate(body=>body.scrollTop),0);assert.equal(await page.locator('#modal-title').evaluate(title=>title===document.activeElement),true);assert.equal(await wholePrice('#detail-total'),120000);assert.equal(requests(),beforeRoomReturn,'opening an independent offer is passive');
   await page.locator('[data-action="close-modal"]').click();await editResultSearch(page,width);transport.state.wideFacets=false;longStay=true;await startSearch();await openTv();const longChosenStay=await stay('.tour-main-details>.chosen-stay');assert.equal(longChosenStay.facts['Номер'],longRoom);assert.equal(longChosenStay.facts['Оператор'],longOperator);await page.locator('[data-action="start-tour-flights"]').click();await page.locator('[name="flight-pair"][value="0"]').waitFor();const longContext=await flightContext(longChosenStay,'TV long canonical stay');await page.screenshot({path:path.join(evidence,`tv-flight-context-long-${width}.png`)});
   const longBefore=requests();await page.locator('[name="flight-pair"][value="1"]').check();assert.equal(await page.locator('[name="flight-pair"][value="1"]').evaluate(input=>document.activeElement===input),true);assert.equal(await wholePrice('#flight-total'),133500.5);await flightContext(longChosenStay,'TV long stay selected B');const longPosition=await position();assert.equal(longPosition.modalOverflow,false);assert.equal(longPosition.bodyOverflow,false);assert.equal(longPosition.documentOverflow,false);assert(longPosition.footer.bottom<=650+1,'TV footer remains accessible while the long context scrolls');await page.screenshot({path:path.join(evidence,`tv-flight-context-long-selected-${width}.png`)});await page.locator('[data-action="apply-flight"]').click();await page.locator('#prototype-lead-form').waitFor();assert.deepEqual(await stay('.application-choice>.chosen-stay'),longChosenStay);assert.equal(await wholePrice('#modal-footer .footer-total strong'),133500.5);await page.locator('#modal-back').click();assert.deepEqual(await stay('.tour-main-details>.chosen-stay'),longChosenStay);assert.equal(await wholePrice('#detail-total'),133500.5);assert.equal(requests(),longBefore);
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   fs.writeFileSync(path.join(evidence,`quote-retry-room-return-${width}.json`),JSON.stringify({width,height:650,pendingFooter,resumedFooter,movedBefore,movedAfter,receiptBefore,receiptAfter,successful_retry_application:true,whole_tour_total:133500.5,selected_pair:'1',room_return_and_reopen_requests:0,selected_marker:true,new_offer_reset:true,supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));
   fs.writeFileSync(path.join(evidence,`tv-flight-context-${width}.json`),JSON.stringify({width,height:650,chosenStay,contextA,contextB,unknownContext,longChosenStay,longContext,longPosition,whole_tour_A:120000,whole_tour_B:133500.5,fuel_already_in_total:20686,local_pair_cancel_apply_return_requests:0,unknown_price:null,unknown_apply_disabled:true,unknown_cancel_retains_B:true,supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));
  }finally{for(const release of releases)release();await context.close();}
  }));
 }
 console.log('PASS compiled quote retry and room return: five widths, conditional focus/scroll retention, exact pair/whole total, passive reopen and independent offer');
};
const appliedSummaryControls=async(page,width,transport,evidence)=>{
 const summary=page.locator('#applied-search'),starts=transport.calls.filter(c=>c.action==='search_start').length;
 const originalCards=await page.locator('#cards').innerHTML(),originalURL=page.url();
 await editResultSearch(page,width);
 await page.locator('#quick-stars [data-value="5"]').click();
 assert.equal(await page.locator('#quick-stars [data-value="5"]').getAttribute('aria-pressed'),'true','approved form displays selected stars');
 await page.locator('#quick-meal').click();
 const choice=page.locator('[data-meal-choice]:not([value=""])').first(),mealValue=await choice.getAttribute('value');
 await choice.check();await page.locator('[data-action="apply-meals"]').click();
 assert((await page.locator('#meal-label').textContent()).includes(mealValue),'approved form displays selected meal');
 await page.locator('#quick-budget').click();await page.locator('#budget-min').fill('');await page.locator('#budget-max').fill('200000');await page.locator('[data-action="apply-budget"]').click();
 assert.match(await page.locator('#budget-label').textContent(),/200\s*000/,'approved form displays the total budget');
 if(width===390){
  await page.setViewportSize({width:360,height:900});
  const boxes=await page.locator('#search-form .quick-field').evaluateAll(buttons=>buttons.map(el=>{const b=el.getBoundingClientRect();return{x:b.x,y:b.y,right:b.right,bottom:b.bottom,width:b.width};}));
  assert.equal(boxes.length,2);assert(boxes.every(b=>b.x>=0&&b.right<=360),'meal and total budget fit360px');
  await page.screenshot({path:path.join(evidence,'approved-form-360.png')});await page.setViewportSize({width,height:900});
 }
 await page.locator('#quick-budget').click();await page.locator('#budget-max').fill('999999');await page.locator('[data-action="close-modal"]').click();
 assert.match(await page.locator('#budget-label').textContent(),/200\s*000/,'cancelled budget does not replace the applied value');
 await page.locator('#quick-budget').click();await page.locator('#budget-min').fill('');await page.locator('#budget-max').fill('');await page.locator('[data-action="apply-budget"]').click();
 await page.locator('#quick-meal').click();await page.locator('[data-meal-choice][value=""]').check();await page.locator('[data-action="apply-meals"]').click();
 await page.locator('#quick-stars [data-action="any-stars"]').click();
 assert.equal(await page.locator('#quick-stars [data-action="any-stars"]').getAttribute('aria-pressed'),'true');assert.match(await page.locator('#meal-label').textContent(),/Любое/);assert.match(await page.locator('#budget-label').textContent(),/Без ограничений/);
 await page.locator('#search-return').click();
 assert.equal(await page.locator('#cards').innerHTML(),originalCards,'cancelled form keeps the complete saved results');assert.equal(page.url(),originalURL,'cancelled form preserves URL');
 assert.equal(transport.calls.filter(c=>c.action==='search_start').length,starts,'form filter edits never repeat supplier search');
};
const contactLayout=async(page,width,provider)=>{
 const fields=await page.locator('#prototype-lead-form>.form-row').first().locator('label').evaluateAll(labels=>labels.map(label=>{const box=label.getBoundingClientRect();return{x:box.x,y:box.y,width:box.width,height:box.height};}));
 assert.equal(fields.length,2,provider+' keeps name and phone fields');
 if(width===390){assert(Math.abs(fields[0].x-fields[1].x)<1,provider+' fields align in one mobile column');assert(fields[1].y>=fields[0].y+fields[0].height,provider+' fields stack without overlap');assert(fields.every(field=>field.width>300),provider+' fields use the available mobile width');}
 else assert(fields[1].x>=fields[0].x+fields[0].width,provider+' keeps two contact columns on tablet/desktop');
};
const mobileFooterLayout=async(page,width)=>{
 if(width!==390)return;
 const verify=async()=>{
  const boxes=await page.locator('#modal-footer').evaluate(footer=>{
   const total=footer.querySelector('.footer-total'),price=total.querySelector('strong'),button=footer.querySelector('.primary'),rect=el=>{const b=el.getBoundingClientRect();return{x:b.x,y:b.y,width:b.width,height:b.height,bottom:b.bottom};};
   return{hidden:footer.hidden,display:getComputedStyle(footer).display,total:rect(total),price:rect(price),button:rect(button),nowrap:getComputedStyle(price).whiteSpace};
  });
  assert.equal(boxes.hidden,false,'visible tour footer remains visible');
  assert(boxes.button.y>=boxes.total.bottom,'mobile action is below price and status');
  assert(Math.abs(boxes.button.x-boxes.total.x)<1&&Math.abs(boxes.button.width-boxes.total.width)<1,'mobile action uses the full available row');
  assert.equal(boxes.nowrap,'nowrap','tour price does not break inside the amount');
  assert(boxes.price.x+boxes.price.width<=boxes.total.x+boxes.total.width+1,'whole price fits in the footer');
 };
 await verify();await page.setViewportSize({width:360,height:900});
 try{await verify();await page.screenshot({path:path.join(evidence,'tour-footer-360.png')});}
 finally{await page.setViewportSize({width,height:900});}
};
const mobileQuickFieldLayout=async(page,width)=>{
 if(width!==390)return;
 await page.setViewportSize({width:360,height:900});
 try{
  const boxes=await page.locator('#quick-budget').evaluate(field=>{
   const rect=el=>{const b=el.getBoundingClientRect();return{x:b.x,y:b.y,width:b.width,height:b.height,right:b.right,bottom:b.bottom};},value=field.querySelector('strong'),label=field.querySelector('span:not(.chevron)'),arrow=field.querySelector('.chevron');
   const range=document.createRange();range.selectNodeContents(value);const textBox=range.getBoundingClientRect();
   return{field:rect(field),value:rect(value),label:rect(label),arrow:rect(arrow),textRight:textBox.right,text:value.textContent};
  });
  assert(boxes.field.x>=0&&boxes.field.right<=360&&boxes.field.height>=48,'approved budget control fits the mobile viewport and retains its touch target');
  assert(boxes.value.y>=boxes.label.bottom-1,'budget value is below its label');
  assert(boxes.arrow.x>=boxes.textRight-1,'chevron stays beside the approved value text without overlap');
  assert(boxes.value.x>=boxes.field.x&&boxes.value.right<=boxes.field.right&&boxes.value.bottom<=boxes.field.bottom,'whole budget value stays inside its field');
 }finally{await page.setViewportSize({width,height:900});}
};
const hotelPickerBlock=async(page,width,transport,origin,base,evidence)=>{
 transport.state.hotelCatalogue=hotelCatalogue();
 await page.addInitScript(()=>{const viewport=new EventTarget();viewport.height=innerHeight;viewport.offsetTop=0;Object.defineProperty(window,'visualViewport',{value:viewport,configurable:true});window.__hotelViewport=viewport;});
 await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));
 await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
 const starts=()=>transport.calls.filter(c=>c.action==='search_start').length,beforeStarts=starts();
 const fields=()=>page.evaluate(()=>['#dates-label','#nights-label','#guests-label','#origin'].map(s=>{const el=document.querySelector(s);return el.value||el.textContent;}));
 const beforeTrip=await fields();
 await page.locator('#search-form [data-action="destination"]').click();await page.locator('#destination-query').fill('Rix');
 await page.waitForFunction(()=>document.querySelectorAll('.destination-hotel').length===8&&document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
 assert.equal(await page.locator('.destination-hotel img').count(),7);assert.match(await page.locator('#destination-scope').textContent(),/Турция/);
 await page.waitForFunction(()=>document.querySelector('.destination-hotel-image[src$="/test-missing-photo.svg"]')?.hidden===true);
 assert.equal(await page.locator('.destination-hotel[data-id="2003"] img').count(),0,'absent photo uses the placeholder');
 await page.screenshot({path:path.join(evidence,`hotel-picker-photos-${width}.png`)});
 const focusedHotel=page.locator('#destination-hotel-2001');
 await focusedHotel.focus();await focusedHotel.press('Space');
 await page.waitForFunction(()=>document.querySelector('#destination-hotel-2001')?.getAttribute('aria-pressed')==='true');
 await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
 assert.equal(await focusedHotel.evaluate(el=>document.activeElement===el),true,'keyboard selection and later region paint retain exact row focus');
 await page.screenshot({path:path.join(evidence,`hotel-picker-focus-${width}.png`)});
 await focusedHotel.press('Space');
 await page.waitForFunction(()=>document.querySelector('#destination-hotel-2001')?.getAttribute('aria-pressed')==='false');
 await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
 assert.equal(await focusedHotel.evaluate(el=>document.activeElement===el),true,'keyboard deselection retains its row');
 for(const id of [2001,2002,2003])await page.locator('#destination-hotel-'+id).press('Space');
 const countryDisclosure=page.locator('[data-action="destination-countries"]');
 await countryDisclosure.press('Space');
 assert.equal(await countryDisclosure.getAttribute('aria-expanded'),'true');assert.equal(await countryDisclosure.evaluate(el=>document.activeElement===el),true,'opening country disclosure retains keyboard focus');
 assert.equal(await page.locator('#destination-query').inputValue(),'Rix');assert.deepEqual(await page.locator('[data-action="destination-remove"]').evaluateAll(rows=>rows.map(row=>row.dataset.id)),['2001','2002','2003'],'country disclosure preserves exact draft IDs');
 await page.screenshot({path:path.join(evidence,`hotel-picker-country-disclosure-focus-${width}.png`)});
 await countryDisclosure.press('Space');
 assert.equal(await countryDisclosure.getAttribute('aria-expanded'),'false');assert.equal(await countryDisclosure.evaluate(el=>document.activeElement===el),true,'closing country disclosure retains keyboard focus');
 const chip=id=>page.locator('[data-action="destination-remove"][data-id="'+id+'"]');
 let releaseHotelLookup;transport.state.hotelLookupGates['4']=new Promise(resolve=>{releaseHotelLookup=resolve;});
 try{
  await page.locator('#destination-query').fill('Rixo');
  await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='true');
  await chip(2002).press('Space');
  assert.equal(await chip(2003).evaluate(el=>document.activeElement===el),true,'removing a middle chip retains focus on the next chip');
  releaseHotelLookup();
  await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
  assert.equal(await chip(2003).evaluate(el=>document.activeElement===el),true,'late catalogue completion retains the remaining exact chip focus');
 }finally{releaseHotelLookup();delete transport.state.hotelLookupGates['4'];}
 assert.deepEqual(await page.locator('[data-action="destination-remove"]').evaluateAll(rows=>rows.map(row=>row.dataset.id)),['2001','2003']);
 await chip(2003).press('Space');
 assert.equal(await chip(2001).evaluate(el=>document.activeElement===el),true,'removing the trailing chip retains focus on the preceding chip');
 await chip(2001).press('Space');
 assert.equal(await page.locator('[data-action="apply-destination"]').evaluate(el=>document.activeElement===el),true,'removing the final chip keeps confirmation focused');
 assert.equal(await page.locator('[data-action="destination-remove"]').count(),0);
 await page.locator('#destination-query').fill('Rix');await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
 if(width<=760){
  const viewport=async(height,offsetTop=0)=>page.evaluate(({height,offsetTop})=>{Object.assign(window.__hotelViewport,{height,offsetTop});window.__hotelViewport.dispatchEvent(new Event('resize'));},{height,offsetTop});
  const geometry=()=>page.locator('#modal').evaluate(modal=>{const rect=el=>{const b=el.getBoundingClientRect();return{top:b.top,bottom:b.bottom,height:b.height,right:b.right,left:b.left};};return{modal:rect(modal),body:rect(document.querySelector('#modal-body')),footer:rect(document.querySelector('#modal-footer')),action:rect(document.querySelector('[data-action="apply-destination"]')),input:rect(document.querySelector('#destination-query')),rows:[...document.querySelectorAll('.destination-hotel')].slice(0,3).map(rect),width:modal.clientWidth,scrollWidth:modal.scrollWidth};});
  await viewport(460);await page.waitForFunction(()=>document.querySelector('#modal').classList.contains('destination-keyboard'));
  const boxes=await geometry();assert(boxes.modal.top>=0&&boxes.modal.bottom<=460,'picker stays wholly above the simulated keyboard');assert(boxes.footer.bottom<=460&&boxes.action.height>=44,'confirmation remains visible with a touch target');assert(boxes.input.height>=44);assert(boxes.rows.every(row=>row.bottom<=boxes.body.bottom),'three exact hotel rows remain visible above the keyboard');assert.equal(boxes.scrollWidth<=boxes.width+1,true);
  await page.screenshot({path:path.join(evidence,`hotel-picker-keyboard-${width}.png`)});
  await viewport(460,24);const moved=await geometry();assert(moved.modal.top>=24&&moved.modal.bottom<=484,'panned visual viewport remains bounded');
  await viewport(900);assert.equal(await page.locator('#modal').evaluate(el=>el.classList.contains('destination-keyboard')),false);
  fs.writeFileSync(path.join(evidence,`hotel-picker-keyboard-${width}.json`),JSON.stringify({simulated:true,physicalSafari:false,boxes,panned:moved},null,2));
 }
 await page.locator('[data-action="destination-more-hotels"]').click();assert.equal(await page.locator('.destination-hotel').count(),10);
 transport.state.catalogueAliases={2004:['контрольный псевдоним'],2005:['контрольный псевдоним']};await page.locator('#destination-query').fill('контрольный псевдоним');await page.waitForFunction(()=>document.querySelectorAll('.destination-hotel').length===2&&document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
 assert.deepEqual(await page.locator('.destination-hotel').evaluateAll(rows=>rows.map(row=>row.dataset.id)),['2004','2005'],'server alias matches retain exact canonical identities');await page.locator('[data-action="destination-hotel"][data-id="2004"]').click();await page.locator('[data-action="destination-hotel"][data-id="2005"]').click();assert.match(await page.locator('[data-action="apply-destination"]').textContent(),/Выбрать отели \(2\)/);
 await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');assert.deepEqual(await fields(),beforeTrip);await page.goForward();await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelectorAll('.destination-hotel[aria-pressed="true"]').length===2&&document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
 await page.screenshot({path:path.join(evidence,`hotel-picker-alias-${width}.png`)});await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');await page.locator('#search-form [data-action="destination"]').click();await page.locator('#destination-query').fill('Rix');await page.waitForFunction(()=>document.querySelectorAll('.destination-hotel').length===8&&document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
 await page.locator('[data-action="destination-countries"]').click();assert.equal(await page.locator('[data-action="destination-country"]').count(),11,'country menu opens with the hotel query retained');
 await page.locator('[data-action="destination-country"][data-value="100"]').click();
 await page.waitForFunction(()=>document.querySelectorAll('.destination-hotel').length===3&&document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
 assert.equal(await page.locator('#destination-query').inputValue(),'Rix');assert((await page.locator('.destination-hotel strong').allTextContents()).every(name=>name.includes('Egypt')));assert.match(await page.locator('#destination-scope').textContent(),/Египет/);
 await page.screenshot({path:path.join(evidence,`hotel-picker-country-${width}.png`)});
 await page.locator('[data-action="destination-countries"]').click();await page.locator('[data-action="destination-country"][data-value="4"]').click();
 await page.waitForFunction(()=>document.querySelectorAll('.destination-hotel').length===8&&document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
 assert((await page.locator('.destination-hotel strong').allTextContents()).every(name=>name.includes('Belek')));
 await page.locator('[data-action="destination-hotel"][data-id="2002"]').click();assert.match(await page.locator('[data-action="apply-destination"]').textContent(),/Выбрать отель/);await page.locator('[data-action="apply-destination"]').click();
 await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
 assert.match(await page.locator('#country').textContent(),/Rixos Fictional Belek 02/);assert.deepEqual(await fields(),beforeTrip);assert.equal(starts(),beforeStarts,'all picker actions leave suppliers untouched');
 await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.length>0);for(let i=0;i<100&&starts()===beforeStarts;i++)await page.waitForTimeout(20);
 assert.equal(starts(),beforeStarts+1,'one explicit submit starts one search');const request=transport.calls.findLast(c=>c.action==='search_start');assert.equal(request.query['hotelIds[]'],'7002','supplier search uses the verified legacy ID, not the own catalogue ID');assert.equal(request.query.countryId,'4');
 fs.writeFileSync(path.join(evidence,`hotel-picker-block-${width}.json`),JSON.stringify({width,keyboard_selection_focus:true,keyboard_deselection_focus:true,keyboard_chip_removal_focus:true,available_photos:true,missing_and_failed_photo_fallback:true,typed_country_switch:true,cached_country_scope:true,own_hotel_id:2002,verified_legacy_id:7002,trip_preserved:true,explicit_searches:1,simulated_keyboard:width<=760,physicalSafari:false,supplier_HTTP:0,real_leads:0},null,2));
};
async function formPickerActionJourney(browser,origin,base,evidence){
 const receipts=[];
 for(const width of [360,390,430,768,1280]){
  const transport=fixture(),errors=[],forbidden=[],hotelReads=[],hotelReadWaiters=new Map(),releases=[],context=await browser.newContext({viewport:{width,height:650}}),page=await context.newPage();
  page.setDefaultTimeout(10000);page.on('pageerror',error=>errors.push(error.message));
  page.on('requestfailed',request=>{const read=hotelReads.find(entry=>entry.request===request);if(read){read.failure=request.failure()?.errorText||null;read.failedResolve(read.failure);}});
  try{
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/test-missing-photo.svg'){await route.fulfill({status:404,contentType:'text/plain',body:'Fictional photo unavailable'});return;}
    if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="64" height="52"><rect fill="#bacad5" width="64" height="52"/></svg>'});return;}
    if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
    let finished,failedResolve;const read=url.pathname==='/data/hotel-search-v1.php'?{request,query:url.searchParams.get('q'),country:url.searchParams.get('countryId'),held:!!transport.state.hotelLookupGates[url.searchParams.get('countryId')],done:new Promise(resolve=>finished=resolve),failed:new Promise(resolve=>failedResolve=resolve),failedResolve,expectAbort:false,cancelled:false}:null;
    if(read){hotelReads.push(read);hotelReadWaiters.get(request)?.(read);}
    try{const value=await transport.json(request.url(),{body:request.postData()});await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});}
    catch(error){
     // Only a deliberately held canonical read cancelled by these native actions
     // may finish without a response. Retain its explicit abort receipt; all
     // other route/fixture failures remain forbidden, including other aborts.
     let failure=request.failure()?.errorText;
     if(read?.held&&read.expectAbort&&!failure){
      // Cancellation and route completion are separate events too. Wait only for
      // this explicitly cancelled held read, with the unchanged 10s action cap.
      let timer;try{failure=await Promise.race([read.failed,new Promise(resolve=>timer=setTimeout(()=>resolve(null),10000))]);}finally{clearTimeout(timer);}
     }
     if(read?.held&&read.expectAbort&&failure==='net::ERR_ABORTED')read.cancelled=true;
     else{forbidden.push(error.message);await route.abort();}
    }finally{finished?.();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));await page.locator('.search-submit:not(:disabled)').waitFor();
   await page.locator('#quick-stars [data-action="star"][data-value="5"]').click();
   const initialURL=page.url(),fields=()=>page.locator('#search-form').evaluate(el=>[...el.querySelectorAll('input,select')].map(control=>[control.name||control.id,control.value]));
   const initialFields=await fields(),initialStars=await page.locator('#quick-stars').innerHTML();
   const target=async(locator,label)=>{
    await locator.scrollIntoViewIfNeeded();
    const box=await locator.evaluate(el=>{
     const r=el.getBoundingClientRect(),range=document.createRange();range.selectNodeContents(el);
     const hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2),text=[...range.getClientRects()].filter(b=>b.width&&b.height);
     return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height,hit:!!hit&&(hit===el||el.contains(hit)),textInside:text.every(b=>b.x>=r.x-1&&b.right<=r.right+1),viewportWidth:innerWidth,viewportHeight:innerHeight};
    });
    assert(box.width>=43.5&&box.height>=43.5,label+' has the existing44px target');assert(box.hit,label+' is reachable at its center');
    assert(box.x>=0&&box.right<=box.viewportWidth&&box.y>=0&&box.bottom<=box.viewportHeight,label+' fits the visible viewport');assert(box.textInside,label+' keeps its whole label');return box;
   };
   const focused=()=>page.evaluate(()=>document.activeElement.dataset.action||document.activeElement.id||document.activeElement.tagName);
   const close=async(action)=>{await target(page.locator('#modal [data-action="close-modal"]'),'picker close');await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');assert.equal(await focused(),action,'Escape returns focus to the exact picker trigger');assert.deepEqual(await fields(),initialFields,'Cancel preserves the complete form');assert.equal(await page.locator('#quick-stars').innerHTML(),initialStars,'Cancel preserves applied category');assert.equal(page.url(),initialURL,'Cancel preserves exact search URL');};
   for(const zoom of [100,200]){
    await page.evaluate(zoom=>document.documentElement.style.fontSize=zoom===200?'200%':'',zoom);
    await page.locator('#search-form [data-action="form-filters"]').click();assert.match(await page.locator('#form-filters-summary').textContent(),/1 группа/);
    const reset=await target(page.locator('[data-action="reset-form-filters"]'),'form filter reset');await page.locator('[data-action="reset-form-filters"]').press('Enter');assert.equal(await page.locator('#form-filters-summary').textContent(),'Без дополнительных условий');assert.equal(await focused(),'reset-form-filters','keyboard reset retains the same logical control');
    await page.keyboard.press('Tab');assert.equal(await focused(),'apply-form-filters','Tab after reset continues to Apply');await page.keyboard.press('Shift+Tab');assert.equal(await focused(),'reset-form-filters','reverse Tab returns to the reset control');await page.keyboard.press('Enter');assert.equal(await focused(),'reset-form-filters','repeated reset retains focus');
    await page.screenshot({path:path.join(evidence,`form-actions-filters-${width}-${zoom}.png`)});await close('form-filters');
    await page.locator('#search-form [data-action="dates"]').click();await page.locator('#date-calendar').waitFor();let next=null;
    if(await page.locator('#modal [data-action="month-next"]').isVisible()){
     const month=await page.locator('.calendar-month h3').first().textContent();next=await target(page.locator('#modal [data-action="month-next"]'),'calendar next month');await page.locator('#modal [data-action="month-next"]').press('Enter');const advanced=await page.locator('.calendar-month h3').first().textContent();assert.notEqual(advanced,month,'one Enter advances the displayed month');assert.equal(await focused(),'month-next','next month retains keyboard focus');
     await page.keyboard.press('Enter');assert.notEqual(await page.locator('.calendar-month h3').first().textContent(),advanced,'a second Enter advances the next month');assert.equal(await focused(),'month-next');
     await target(page.locator('#modal [data-action="month-prev"]'),'calendar previous month');await page.locator('#modal [data-action="month-prev"]').press('Enter');assert.equal(await page.locator('.calendar-month h3').first().textContent(),advanced);assert.equal(await focused(),'month-prev');await page.keyboard.press('Enter');assert.equal(await page.locator('.calendar-month h3').first().textContent(),month,'repeated previous Enter restores the same displayed month');
     for(let n=0;await page.locator('#modal [data-action="month-prev"]').isEnabled();n++){assert(n<2,'fixture trip starts within the first two available months');assert.equal(await focused(),'month-prev');await page.keyboard.press('Enter');}
     assert.equal(await focused(),'modal-title','the first-month boundary returns focus to the existing title');
    }
    await page.screenshot({path:path.join(evidence,`form-actions-dates-${width}-${zoom}.png`)});await close('dates');
    for(const type of ['nights','guests']){await page.locator('#search-form [data-action="'+type+'"]').click();await close(type);}
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'form actions do not widen the document');
    receipts.push({width,zoom,reset,next,keyboard_reset_focus:true,keyboard_month_focus:width>760,disabled_month_boundary_focus:width>760,Escape_returns_exact_trigger:true,cancel_preserves_fields_and_URL:true,physicalSafari:false});
   }
   // Continue the same isolated five-width form context. All catalogue rows,
   // aliases and profile links below are the existing explicitly fictional fixture.
   transport.state.hotelCatalogue=hotelCatalogue();
   transport.state.catalogueAliases={2004:['контрольный псевдоним'],2005:['контрольный псевдоним']};
   transport.state.catalogueCountries=[{id:100,kind:'country',parentId:null,name:'Египет',slug:'egypt',revision:1,tourvisorIds:['100']}];
   const historyKey='anytour.prototype.v18.ui.v1',alias='контрольный псевдоним',familyURL=origin+base+'visual-search/?'+new URLSearchParams({...trip,adults:3,ages:'0,12',hotels:'2001|2002'}),familyReceipts=[],destinationReceipts=[];
   const frames=()=>page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
   const closed=()=>page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
   const familyFields=()=>page.evaluate(()=>['#origin','#dates-label','#nights-label','#guests-label','#guests-detail','#destination-detail'].map(selector=>{const el=document.querySelector(selector);return el.value||el.textContent;}));
   const indexFocus=()=>page.evaluate(()=>({action:document.activeElement.dataset.action||document.activeElement.id,index:document.activeElement.dataset.index??null,disabled:document.activeElement.matches(':disabled')}));
   const selectedIDs=()=>page.locator('[data-action="destination-remove"][data-id]').evaluateAll(rows=>rows.map(row=>Number(row.dataset.id)));
   const hotelIDs=()=>page.locator('.destination-hotel').evaluateAll(rows=>rows.map(row=>Number(row.dataset.id)));
   const geometry=async label=>{
    const result=await page.locator('#modal').evaluate(modal=>{const rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};},body=document.querySelector('#modal-body'),footer=document.querySelector('#modal-footer'),active=document.activeElement;return{modal:rect(modal),body:rect(body),footer:rect(footer),scroll:body.scrollTop,focus:active.dataset.action||active.id,focusIndex:active.dataset.index??null,documentOverflow:document.documentElement.scrollWidth>innerWidth,modalOverflow:modal.scrollWidth>modal.clientWidth+1,bodyOverflow:body.scrollWidth>body.clientWidth+1,viewportHeight:innerHeight};});
    assert.equal(result.documentOverflow,false,label+' does not widen the document at '+width);assert.equal(result.modalOverflow,false);assert.equal(result.bodyOverflow,false);
    assert(result.footer.bottom<=result.viewportHeight+1,label+' retains its confirmation footer inside the viewport at '+width);return result;
   };
   const loadFamily=async()=>{
    await page.goto(familyURL);await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled&&document.querySelector('#destination-detail').textContent.includes('Fictional Belek 02'));
    return{url:page.url(),fields:await familyFields()};
   };
   const assertUnapplied=async baseline=>{assert.equal(page.url(),baseline.url,'picker edits never mutate the applied URL');assert.deepEqual(await familyFields(),baseline.fields,'Cancel preserves the entire family and exact-hotel form');};
   for(const zoom of [100,200]){
    const baseline=await loadFamily();await page.evaluate(zoom=>document.documentElement.style.fontSize=zoom===200?'200%':'',zoom);
    await page.locator('#search-form [data-action="guests"]').click();
    await page.locator('[data-action="child-age"][data-index="1"]').press('Enter');await page.locator('[data-action="age-pick"][data-value="13"]').press('Enter');await frames();
    const route=await page.evaluate(key=>history.state[key],historyKey);
    assert.deepEqual(route,{type:'child-age',guest:{adults:3,ages:[0,12]},choice:{index:1,value:13}},'capture the actual native second-child route, not a constructed replacement');
    await page.locator('[data-action="apply-age"]').press('Enter');assert.equal(await page.locator('#modal-title').textContent(),'Туристы');
    assert.deepEqual(await indexFocus(),{action:'child-age',index:'1',disabled:false},'ordinary second-child Apply returns to the exact indexed row');
    assert.match(await page.locator('[data-action="child-age"][data-index="0"]').textContent(),/До 1 года/);assert.match(await page.locator('[data-action="child-age"][data-index="1"]').textContent(),/13 лет/);await assertUnapplied(baseline);
    await page.locator('[data-action="child-age"][data-index="1"]').press('Enter');await page.locator('[data-action="age-pick"][data-value="12"]').press('Enter');await page.locator('[data-action="apply-age"]').press('Enter');
    await page.locator('[data-action="child-age"][data-index="1"]').press('Enter');await page.locator('[data-action="age-pick"][data-value="13"]').press('Enter');await frames();
    assert.deepEqual(await page.evaluate(key=>history.state[key],historyKey),route);
    await page.reload();await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelector('#modal-title').textContent==='Возраст ребёнка 2'&&!document.querySelector('.search-submit').disabled);await page.evaluate(zoom=>document.documentElement.style.fontSize=zoom===200?'200%':'',zoom);
    assert(await page.locator('#modal-back').isVisible(),'a full reload retains the parent guests step');assert.equal(await page.locator('[data-action="age-pick"][data-value="13"]').getAttribute('aria-pressed'),'true');
    const restored=await geometry('restored child-age');await target(page.locator('[data-action="apply-age"]'),'restored age Apply');await page.screenshot({path:path.join(evidence,'picker-family-reload-'+width+'-'+zoom+'.png')});
    await page.locator('[data-action="apply-age"]').press('Enter');assert.deepEqual(await indexFocus(),{action:'child-age',index:'1',disabled:false});assert.match(await page.locator('[data-action="child-age"][data-index="1"]').textContent(),/13 лет/);await assertUnapplied(baseline);await page.screenshot({path:path.join(evidence,'picker-family-return-'+width+'-'+zoom+'.png')});
    await page.keyboard.press('Escape');await closed();await assertUnapplied(baseline);
    // Restore a genuine child route again; Back must retain the original age.
    await page.locator('#search-form [data-action="guests"]').click();await page.locator('[data-action="child-age"][data-index="1"]').press('Enter');await page.locator('[data-action="age-pick"][data-value="13"]').press('Enter');await page.reload();
    await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelector('#modal-title').textContent==='Возраст ребёнка 2'&&!document.querySelector('.search-submit').disabled);await page.evaluate(zoom=>document.documentElement.style.fontSize=zoom===200?'200%':'',zoom);
    await page.locator('#modal-back').press('Enter');assert.deepEqual(await indexFocus(),{action:'child-age',index:'1',disabled:false});assert.match(await page.locator('[data-action="child-age"][data-index="1"]').textContent(),/12 лет/);
    for(let n=3;n<6;n++)await page.locator('[data-action="adults-plus"]').press('Enter');
    assert(await page.locator('[data-action="adults-plus"]').isDisabled());assert.deepEqual(await indexFocus(),{action:'adults-minus',index:null,disabled:false},'upper adult boundary retains the usable opposite counter');
    for(let n=6;n>1;n--)await page.locator('[data-action="adults-minus"]').press('Enter');
    assert(await page.locator('[data-action="adults-minus"]').isDisabled());assert.deepEqual(await indexFocus(),{action:'adults-plus',index:null,disabled:false},'lower adult boundary retains a usable counter');
    await page.locator('[data-action="children-plus"]').press('Enter');assert(await page.locator('[data-action="children-plus"]').isDisabled());assert.deepEqual(await indexFocus(),{action:'children-minus',index:null,disabled:false});assert(await page.locator('[data-action="apply-guests"]').isDisabled(),'missing third-child age still blocks Apply');
    const boundary=await geometry('family counter boundary');await target(page.locator('[data-action="children-minus"]'),'boundary child counter');await page.screenshot({path:path.join(evidence,'picker-family-boundary-'+width+'-'+zoom+'.png')});
    await page.locator('[data-action="child-age"][data-index="2"]').press('Enter');await page.locator('[data-action="age-pick"][data-value="17"]').press('Enter');await page.locator('[data-action="apply-age"]').press('Enter');assert.deepEqual(await indexFocus(),{action:'child-age',index:'2',disabled:false});
    await page.locator('[data-action="remove-child"][data-index="1"]').press('Enter');assert.deepEqual(await indexFocus(),{action:'child-age',index:'1',disabled:false});assert.match(await page.locator('[data-action="child-age"][data-index="1"]').textContent(),/17 лет/,'removing the middle child never aliases the surviving third child');
    await page.locator('[data-action="children-minus"]').press('Enter');await page.locator('[data-action="children-minus"]').press('Enter');assert(await page.locator('[data-action="children-minus"]').isDisabled());assert.deepEqual(await indexFocus(),{action:'children-plus',index:null,disabled:false});
    await page.keyboard.press('Escape');await closed();await assertUnapplied(baseline);
    // Only explicit whole-party Apply changes the form draft; no supplier starts.
    await page.locator('#search-form [data-action="guests"]').click();await page.locator('[data-action="child-age"][data-index="1"]').press('Enter');await page.locator('[data-action="age-pick"][data-value="13"]').press('Enter');await page.locator('[data-action="apply-age"]').press('Enter');await page.locator('[data-action="apply-guests"]').press('Enter');await closed();
    assert.match(await page.locator('#guests-label').textContent(),/3 взр.*2 реб/);assert.equal(await page.locator('#guests-detail').textContent(),'До 1 года и 13 лет');assert.equal(page.url(),baseline.url,'the current applied URL remains unchanged until search submit');
    await page.locator('#search-form [data-action="guests"]').click();assert.match(await page.locator('[data-action="child-age"][data-index="1"]').textContent(),/13 лет/);await page.keyboard.press('Escape');await closed();
    // Corrupted index is an explicitly labelled history fixture; it must recover
    // to the existing guests owner, not create a dead-end child dialog.
    await page.locator('#search-form [data-action="guests"]').click();await page.locator('[data-action="child-age"][data-index="1"]').press('Enter');await page.evaluate(({key})=>{const route=structuredClone(history.state[key]);route.choice.index=3;history.replaceState({...history.state,[key]:route},'',location.href);},{key:historyKey});await page.reload();
    await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelector('#modal-title').textContent==='Туристы'&&!document.querySelector('.search-submit').disabled);assert.equal(await page.locator('[data-action="apply-guests"]').count(),1);await page.keyboard.press('Escape');await closed();assert.equal(page.url(),baseline.url);
    familyReceipts.push({width,zoom,capturedNativeRoute:route,full_reload:true,indexed_return_focus:true,age0_and17_retained:true,all_four_counter_boundaries:true,missing_age_blocks_apply:true,explicit_party_apply:true,invalid_history_index_recovers:true,cancel_preserves_applied_URL:true,restored,boundary,physicalSafari:false});
   }
   const baseline=await loadFamily();await page.evaluate(()=>document.documentElement.style.fontSize='');
   const openDestination=async()=>{await page.locator('#search-form [data-action="destination"]').click();assert.deepEqual(await selectedIDs(),[2001,2002]);};
   const hideCountries=async()=>{if(await page.locator('[data-action="destination-countries"]').getAttribute('aria-expanded')==='true')await page.locator('[data-action="destination-countries"]').press('Enter');};
   const heldLookup=async(query=alias,country='4')=>{
    let release;const gate=new Promise(resolve=>release=resolve);transport.state.hotelLookupGates[country]=gate;const finish=()=>{if(transport.state.hotelLookupGates[country]===gate)delete transport.state.hotelLookupGates[country];release();};releases.push(finish);
    const started=page.waitForRequest(request=>{const url=new URL(request.url());return url.pathname==='/data/hotel-search-v1.php'&&url.searchParams.get('q')===query&&url.searchParams.get('countryId')===country;});
    await page.locator('#destination-query').fill(query);const request=await started;
    // Request and Route are distinct Playwright events: do not assume the route
    // callback has already registered the retained read when Request arrives.
    const read=hotelReads.find(entry=>entry.request===request)||await new Promise((resolve,reject)=>{
     const timer=setTimeout(()=>{hotelReadWaiters.delete(request);reject(new Error('Canonical read interception was not registered within the existing 10s action limit'));},10000);
     hotelReadWaiters.set(request,value=>{clearTimeout(timer);hotelReadWaiters.delete(request);resolve(value);});
    });assert(read,'actual intercepted canonical read is retained');
    return{finish,read};
   };
   const completeHeld=async(held,success=true)=>{
    const profile=success&&!held.read.request.failure()?page.waitForResponse(response=>{const url=new URL(response.url());return url.pathname.endsWith('/hotel-details-read-v1.php')&&url.searchParams.getAll('legacyHotelIds[]').join(',')==='7004,7005';}):null;
    held.finish();await held.read.done;if(profile)await(await profile).finished();await frames();
   };
   const closeDestination=async()=>{await page.locator('#modal [data-action="close-modal"]').click();await closed();await assertUnapplied(baseline);};
   for(const action of ['keep','back','cancel','confirm'])for(const failed of [false,true]){
    await openDestination();transport.state.hotelLookupError=failed;const held=await heldLookup();
    await page.locator('[data-action="destination-countries"]').press('Enter');await page.locator('[data-action="destination-country"][data-value="4"]').press('Enter');assert.equal(await page.locator('#modal-title').textContent(),'Изменить направление?');
    const before=await geometry('pending destination replacement');if(action==='keep'&&!failed)await page.screenshot({path:path.join(evidence,'picker-destination-pending-'+width+'.png')});await completeHeld(held,!failed);assert.equal(await page.locator('#modal-title').textContent(),'Изменить направление?','a late terminal read never replaces the active confirmation');
    const after=await geometry('completed read under replacement');assert.equal(after.scroll,before.scroll);assert.equal(after.focus,before.focus,'a hidden catalogue completion never steals confirmation focus');
    if(action==='keep')await page.locator('[data-action="keep-destination"]').press('Enter');
    else if(action==='back')await page.locator('#modal-back').press('Enter');
    else if(action==='cancel'){if(failed)await page.locator('#modal [data-action="close-modal"]').press('Enter');else await page.keyboard.press('Escape');}
    else await page.locator('[data-action="confirm-destination"]').press('Enter');
    await hideCountries();await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false');
    assert.equal(await page.locator('#destination-query').inputValue(),alias);assert.deepEqual(await selectedIDs(),action==='confirm'?[]:[2001,2002]);assert.equal(page.url(),baseline.url);
    if(failed){await page.locator('[data-action="retry-destination"]').waitFor();assert.match(await page.locator('#destination-results').textContent(),/Не удалось загрузить отели/);if(action==='cancel')await page.screenshot({path:path.join(evidence,'picker-destination-error-'+width+'-100.png')});transport.state.hotelLookupError=false;await page.locator('[data-action="retry-destination"]').press('Enter');await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false'&&document.querySelectorAll('.destination-hotel').length===2);}
    assert.deepEqual(await hotelIDs(),[2004,2005],'the retained canonical alias response is visible without name/ID guessing');
    if(action==='keep'&&failed===false||action==='cancel'&&failed===true)await page.screenshot({path:path.join(evidence,'picker-destination-'+action+'-'+(failed?'error-retry':'ready')+'-'+width+'.png')});
    destinationReceipts.push({width,action,failed,cancel_action:action==='cancel'?(failed?'header-close':'Escape'):null,terminal_during_confirmation:true,query_retained:true,exact_draft_IDs:action==='confirm'?[]:[2001,2002],alias_IDs:[2004,2005],focus_and_scroll_retained:true,geometry:after});await closeDestination();
   }
   // The response-after-return control uses the same request and preserves query
   // focus/selection/scroll while the existing parent picker settles normally.
   await openDestination();const control=await heldLookup();await page.locator('[data-action="destination-countries"]').press('Enter');await page.locator('[data-action="destination-country"][data-value="4"]').press('Enter');await page.locator('[data-action="keep-destination"]').press('Enter');await hideCountries();await page.locator('#destination-query').focus();await page.locator('#destination-query').evaluate(el=>el.setSelectionRange(2,7));const controlBefore=await geometry('response-after-return');
   await completeHeld(control);await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false');assert.deepEqual(await hotelIDs(),[2004,2005]);assert.equal(await focused(),'destination-query');assert.deepEqual(await page.locator('#destination-query').evaluate(el=>[el.selectionStart,el.selectionEnd]),[2,7]);const controlAfter=await geometry('response-after-return complete');assert.equal(controlAfter.scroll,controlBefore.scroll);await closeDestination();
   // Replace the country while an old request is retained. The new canonical
   // country settles first; the old read is explicitly released only afterwards.
   await openDestination();const foreign=await heldLookup('Rix');await page.locator('[data-action="destination-countries"]').press('Enter');await page.locator('[data-action="destination-country"][data-value="100"]').press('Enter');foreign.read.expectAbort=true;await page.locator('[data-action="confirm-destination"]').press('Enter');await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false'&&document.querySelectorAll('.destination-hotel').length===3);foreign.finish();await foreign.read.done;await frames();assert.deepEqual(await hotelIDs(),[3001,3002,3003]);assert.deepEqual(await selectedIDs(),[]);assert.match(await page.locator('#destination-scope').textContent(),/Египет/);await page.screenshot({path:path.join(evidence,'picker-destination-country-'+width+'.png')});await closeDestination();
   // Old A -> B -> new A exercises both query replacement and a duplicate
   // spelling without allowing the cancelled first response to become current.
   await openDestination();const old=await heldLookup();delete transport.state.hotelLookupGates['4'];old.read.expectAbort=true;await page.locator('#destination-query').fill('Rix');await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false'&&document.querySelectorAll('.destination-hotel').length===8);await page.locator('#destination-query').fill(alias);await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false'&&document.querySelectorAll('.destination-hotel').length===2);await page.locator('#destination-query').focus();old.finish();await old.read.done;await frames();assert.deepEqual(await hotelIDs(),[2004,2005]);assert.equal(await focused(),'destination-query');assert.deepEqual(await selectedIDs(),[2001,2002]);await closeDestination();
   for(const failed of [false,true]){
    await openDestination();transport.state.hotelLookupError=failed;const late=await heldLookup();late.read.expectAbort=true;await page.locator('#modal [data-action="close-modal"]').click();await closed();late.finish();await late.read.done;await frames();assert.equal(await page.locator('#modal').evaluate(el=>el.open),false);await assertUnapplied(baseline);transport.state.hotelLookupError=false;
   }
   // root200 retains readable recovery and keyboard focus without extending the
   // original viewport or inventing a physical keyboard/Safari claim.
   await page.evaluate(()=>document.documentElement.style.fontSize='200%');await openDestination();transport.state.hotelLookupError=true;const enlarged=await heldLookup();await page.locator('[data-action="destination-countries"]').press('Enter');await page.locator('[data-action="destination-country"][data-value="4"]').press('Enter');await completeHeld(enlarged,false);await page.locator('[data-action="keep-destination"]').press('Enter');await hideCountries();await page.locator('[data-action="retry-destination"]').waitFor();const enlargedGeometry=await geometry('root200 destination error');await target(page.locator('[data-action="retry-destination"]'),'root200 catalogue retry');await page.screenshot({path:path.join(evidence,'picker-destination-error-'+width+'-200.png')});transport.state.hotelLookupError=false;await page.locator('[data-action="retry-destination"]').press('Enter');await page.waitForFunction(()=>document.querySelector('#destination-query').getAttribute('aria-busy')==='false'&&document.querySelectorAll('.destination-hotel').length===2);
   // Explicit OR Apply commits only the two canonical own IDs to the form draft.
   for(const id of [2001,2002])await page.locator('[data-action="destination-remove"][data-id="'+id+'"]').press('Enter');
   for(const id of [2004,2005])await page.locator('[data-action="destination-hotel"][data-id="'+id+'"]').press('Enter');
   assert.deepEqual(await selectedIDs(),[2004,2005]);assert.match(await page.locator('[data-action="apply-destination"]').textContent(),/Выбрать отели \(2\)/);await page.locator('[data-action="apply-destination"]').press('Enter');await closed();assert.match(await page.locator('#destination-detail').textContent(),/Fictional Belek 04.*Fictional Belek 05/);assert.equal(page.url(),baseline.url);await page.locator('#search-form [data-action="destination"]').click();assert.deepEqual(await selectedIDs(),[2004,2005]);await page.keyboard.press('Escape');await closed();
   fs.writeFileSync(path.join(evidence,'picker-retained-'+width+'.json'),JSON.stringify({width,family:familyReceipts,destination:destinationReceipts,response_after_return:true,query_replacement_and_duplicate_guard:true,changed_country_late_guard:true,closed_success_and_error_guard:true,canonicalReadReceipts:hotelReads.map(read=>({query:read.query,country:read.country,held:read.held,expected_cancel:read.expectAbort,cancelled_route:read.cancelled,failure:read.failure||read.request.failure()?.errorText||null})),OR_own_IDs:[2004,2005],legacy_links:[7004,7005],Apply_commits_form_draft_only:true,root200_error:enlargedGeometry,fixture_only:true,supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));
   assert(!transport.calls.some(call=>call.action==='search_start'||/api-anex-|api-andromeda-|quote|lead/.test(call.url)),'form reset, calendar navigation and Cancel start no supplier/quote/lead operation');
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
  }finally{for(const release of releases)release();await context.close();}
 }
 fs.writeFileSync(path.join(evidence,'form-picker-actions.json'),JSON.stringify({receipts,supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));
 console.log('PASS compiled form picker actions: five widths/normal+root200,44px hitpoints, repeated keyboard reset/month focus, boundary title and Escape preserve exact form/URL/trigger; supplier HTTP0');
}
const root=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||path.join(__dirname,'../v2')),base='/_preview/search3-next-candidate/',evidence=path.resolve('visual-live-evidence');fs.mkdirSync(evidence,{recursive:true});
// The hotel footer is controlled by IntersectionObserver. Two animation frames
// can still capture its intermediate layout after Playwright scrolls a summary.
const settledHotelScroll=page=>page.locator('#modal-body').evaluate(async el=>{
 let previous='',stable=0;const samples=[];
 for(let frame=0;frame<120;frame++){
  await new Promise(resolve=>requestAnimationFrame(resolve));
  const sample=[el.scrollTop,el.clientHeight,el.scrollHeight,document.querySelector('#modal-footer').hidden];
  const key=JSON.stringify(sample);samples.push(sample);stable=key===previous?stable+1:0;previous=key;
  if(stable>=4)return {scroll:el.scrollTop,samples};
 }
 throw new Error('Hotel layout did not settle: '+JSON.stringify(samples.slice(-10)));
});
const forwardProviderApplication=async(page,transport,room,price)=>{
 const before=transport.calls.length;
 const scroll=await page.locator('#modal-body').evaluate(el=>{el.scrollTop=83;return el.scrollTop;});
 await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!history.state?.['anytour.prototype.v18.ui.v1']);
 await page.evaluate(()=>history.forward());await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelector('#prototype-lead-form'));
 assert((await page.locator('#modal-body').textContent()).includes(room));assert((await page.locator('#modal-footer').textContent()).replace(/\s/g,'').includes(price));
 assert.equal(await page.locator('[name="phone"]').inputValue(),'+7 999 123-45-67');assert.equal(await page.locator('[name="consent"]').isChecked(),false);
 assert.equal(await page.locator('#prototype-lead-form').getAttribute('data-checked'),null);
 const position=await page.locator('#modal-body').evaluate(el=>({top:el.scrollTop,max:el.scrollHeight-el.clientHeight}));
 assert.equal(position.top,Math.min(scroll,position.max));assert.equal(transport.calls.length,before,'provider application Forward is passive');
 assert.deepEqual(await page.evaluate(()=>Object.keys(history.state['anytour.prototype.v18.ui.v1']).sort()),['key','scroll','type']);
};
const continueToFlights=async page=>{
 if(await page.locator('[data-action="start-tour-flights"]').count()){
  await page.locator('[data-action="start-tour-flights"]').click();
  await page.locator('[data-action="apply-flight"]').click();
  await page.locator('#prototype-lead-form').waitFor();await page.locator('#modal-back').click();
 }else if(await page.locator('[data-action="retry-flights"]').count()){
  await page.locator('[data-action="retry-flights"]').click();
  await page.locator('[data-action="apply-flight"]').click();
  await page.locator('#prototype-lead-form').waitFor();await page.locator('#modal-back').click();
 }
};
const multiHotelReload=async(browser,origin,base,evidence)=>{
 for(const width of [360,390,430,768,1280]){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),transport=fixture(),errors=[],submitted=[],profiles=[];
  transport.state.hotelCatalogue=hotelCatalogue();page.on('pageerror',e=>errors.push(e.message));
  let releaseProfile,failProfile=false;const profileGate=new Promise(resolve=>releaseProfile=resolve);
  await page.route('**/*',async route=>{
   const req=route.request(),u=new URL(req.url());
   if(u.pathname==='/test-missing-photo.svg'){await route.fulfill({status:404,contentType:'text/plain',body:'Fictional photo unavailable'});return;}
   if(u.pathname.startsWith(base)&&!u.pathname.includes('/data/')){await route.continue();return;}
   if(u.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="64" height="52"><rect fill="#bacad5" width="64" height="52"/></svg>'});return;}
   const own=u.searchParams.get('anytourHotelId');if(own){profiles.push(own);if(own==='2002'){await profileGate;if(failProfile){await route.fulfill({status:502,contentType:'application/json',body:'{"ok":false}'});return;}}}
   if(u.searchParams.get('action')==='search_start')submitted.push(u.searchParams.getAll('hotelIds[]'));
   try{const value=await transport.json(req.url(),{body:req.postData()});await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});}catch(error){errors.push(error.message);await route.abort();}
  });
  const url=origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:'',hotels:'2001|2002'}),detail=page.locator('#destination-detail'),submit=page.locator('.search-submit');
  await page.goto(url);await page.locator('#catalog-error').getByText('Восстанавливаем выбранные отели…',{exact:true}).waitFor();
  assert(await submit.isDisabled(),'pending second identity blocks submit at '+width);assert.match(await page.locator('#catalog-error').textContent(),/Восстанавливаем выбранные отели/);
  assert.equal(submitted.length,0);releaseProfile();await page.waitForFunction(()=>document.querySelector('#destination-detail').textContent.includes('Fictional Belek 02'));
  assert(await submit.isEnabled());assert.equal(new URL(page.url()).searchParams.get('hotels'),'2001|2002');
  await page.locator('#search-form [data-action="destination"]').click();await page.locator('#destination-query').fill('Rix');await page.locator('.destination-hotel[data-id="2003"]').click();await page.locator('[data-action="close-modal"]').click();
  assert.match(await detail.textContent(),/Fictional Belek 01.*Fictional Belek 02/);assert.doesNotMatch(await detail.textContent(),/Fictional Belek 03/,'Cancel leaves both applied IDs intact');
  await page.reload();await page.waitForFunction(()=>document.querySelector('#destination-detail').textContent.includes('Fictional Belek 02'));assert(await submit.isEnabled());assert.equal(submitted.length,0,'passive reload never starts suppliers');
  await page.screenshot({path:path.join(evidence,`multi-hotel-reload-${width}.png`)});
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);assert.equal(overflow,false);
  failProfile=true;await page.goto(url+'&searched=1');await page.locator('#catalog-error [data-action="retry-hotel-restore"]').waitFor();
  assert(await submit.isDisabled());assert.match(await detail.textContent(),/Fictional Belek 01/);assert.equal(new URL(page.url()).searchParams.get('hotels'),'2001|2002');assert.equal(submitted.length,0,'searched URL with one unresolved own ID must not broaden to whole-country search');
  await page.screenshot({path:path.join(evidence,`multi-hotel-reload-error-${width}.png`)});
  await page.locator('#search-form [data-action="destination"]').click();assert.equal(await page.locator('[data-action="destination-remove"][data-id="2002"]').getAttribute('aria-label'),'Убрать выбранный отель','unresolved selection has a usable accessible name, not undefined');
  await page.locator('[data-action="destination-remove"][data-id="2002"]').click();await page.locator('[data-action="close-modal"]').click();assert.equal(new URL(page.url()).searchParams.get('hotels'),'2001|2002','Cancel preserves the unresolved ID too');
  const profileCount=profiles.length;failProfile=false;await page.locator('#catalog-error [data-action="retry-hotel-restore"]').click();await page.waitForFunction(()=>document.querySelector('.search-submit').disabled===false);
  assert.deepEqual(profiles.slice(profileCount),['2002'],'retry rereads only the unresolved own ID');assert.match(await detail.textContent(),/Fictional Belek 01.*Fictional Belek 02/);assert.equal(submitted.length,0,'retry restores the form without supplier replay');
  await page.screenshot({path:path.join(evidence,`multi-hotel-reload-retry-${width}.png`)});
  await page.goto(url);await page.waitForFunction(()=>document.querySelector('#destination-detail').textContent.includes('Fictional Belek 02'));
  await page.locator('#search-form [data-action="destination"]').click();failProfile=true;await page.reload();
  await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelectorAll('[data-action="destination-remove"]').length===2&&document.querySelector('#catalog-error [data-action="retry-hotel-restore"]'));
  assert.deepEqual(await page.locator('[data-action="destination-remove"]').evaluateAll(rows=>rows.map(row=>row.dataset.id)),['2001','2002'],'open-modal reload preserves the unresolved exact draft IDs');
  assert.match(await page.locator('[data-action="apply-destination"]').textContent(),/Выбрать отели \(2\)/);
  assert.equal(await page.locator('[data-action="destination-all"]').getAttribute('aria-pressed'),'false','missing metadata does not turn the draft into Whole country');
  await page.screenshot({path:path.join(evidence,`multi-hotel-history-${width}.png`)});
  await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');assert.equal(new URL(page.url()).searchParams.get('hotels'),'2001|2002');
  await page.goForward();await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelectorAll('[data-action="destination-remove"]').length===2);
  await page.locator('[data-action="apply-destination"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open&&history.scrollRestoration==='auto');
  assert.equal(new URL(page.url()).searchParams.get('hotels'),'2001|2002');assert.equal(submitted.length,0,'reload/Forward/Apply never starts a supplier search');
  assert(await submit.isDisabled(),'keeping unresolved draft IDs does not authorize a broad search');
  failProfile=false;await page.locator('#catalog-error [data-action="retry-hotel-restore"]').click();await page.waitForFunction(()=>document.querySelector('.search-submit').disabled===false);
  const started=page.waitForResponse(response=>new URL(response.url()).searchParams.get('action')==='search_start');await submit.click();await started;assert.deepEqual(submitted,[['7001','7002']],'explicit search submits both verified legacy links with OR identity');
  assert.deepEqual(errors,[]);assert(!transport.calls.some(c=>/lead|payment/.test(c.url)));
  fs.writeFileSync(path.join(evidence,`multi-hotel-reload-${width}.json`),JSON.stringify({width,full_reload:true,pending_blocked:true,partial_failure_blocked:true,retry_missing_only:true,cancel_preserved:true,open_modal_reload:true,unresolved_draft_retained:true,forward_and_apply:true,ownIds:[2001,2002],legacyIds:[7001,7002],overflow:false,supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));await context.close();
 }
};
const searchDeliveryJourney=async(browser,origin,base,evidence)=>{
 const transport=fixture(),errors=[],forbidden=[],anexCalls=[],context=await browser.newContext({viewport:{width:390,height:900}}),page=await context.newPage();
 let releaseAnex;const firstPageGate=new Promise(resolve=>releaseAnex=resolve);
 page.setDefaultTimeout(10000);page.on('pageerror',error=>errors.push(error.message));
 try{
  await page.route('**/*',async route=>{
   const request=route.request(),url=new URL(request.url()),body=JSON.parse(request.postData()||'{}');
   if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
   if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
   try{
    if(url.pathname.endsWith('/api-anex-search3-preview.php')){
     anexCalls.push(body.action);
     if(body.action==='continue'){await route.fulfill({status:502,contentType:'application/json',body:JSON.stringify({ok:false,error:'FIXTURE_DELIVERY_FAILED'})});return;}
    }
    const value=await transport.json(request.url(),{body:request.postData()});
    if(url.pathname.endsWith('/api-anex-search3-preview.php')&&body.action==='search'){
     value.data.continuation={state:'available',pages_read:1,next_page:2};await firstPageGate;
    }
    await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});
   }catch(error){forbidden.push(error.message);await route.abort();}
  });
  await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));await page.locator('.search-submit:not(:disabled)').waitFor();
  assert.equal(transport.calls.filter(call=>call.action==='search_start').length,0,'boot is passive');
  await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('[data-search-source="tourvisor"]')?.textContent.includes('Порция получена')&&document.querySelector('[data-search-source="anex"]')?.textContent.includes('Получаем предложения'));
  assert.doesNotMatch(await page.locator('[data-search-source="anex"]').textContent(),/Вариантов до фильтров/,'pending source count is unknown, not guessed zero');
  assert.doesNotMatch(await page.locator('#search-status').textContent(),/100%/,'TV progress cannot imply completion while another source is still pending');
  await page.screenshot({path:path.join(evidence,'search-delivery-pending-390.png')});
  releaseAnex();await page.waitForFunction(()=>document.querySelector('#search-status summary')?.textContent.includes('Получена часть предложений')&&!document.querySelector('#search-more').hidden);
  const status=page.locator('#search-status'),summary=status.locator('summary');
  await summary.click();assert.match(await status.textContent(),/Вариантов до фильтров: 1/);assert.doesNotMatch(await status.textContent(),/не загрузил|недоступн/,'normal bounded first page is not a failure');
  assert.equal(await status.locator('[data-search-source="andromeda"]').count(),0,'paused SAMO is not presented as an active source');
  await page.screenshot({path:path.join(evidence,'search-delivery-first-page-390.png')});
  assert.deepEqual(anexCalls,['search'],'no automatic page drain');
  await page.locator('#search-more [data-action="continue-search"]').click();
  await page.waitForFunction(()=>document.querySelector('#search-status summary')?.textContent.includes('ANEX')&&document.querySelector('[data-search-source="anex"]')?.textContent.includes('не загрузилась'));
  assert.deepEqual(anexCalls,['search','continue'],'one explicit continuation, no duplicate/retry');
  assert.match(await status.locator('[data-search-source="anex"]').textContent(),/Вариантов до фильтров: 1/,'failed continuation retains its received first-page count');
  assert.match(await page.locator('#results-summary').textContent(),/2 варианта/,'independent TV and retained ANEX offers survive the failure');
  if(!await status.locator('details').evaluate(el=>el.open))await summary.click();
  const ordinaryRender=async()=>page.locator('#mobile-sort').evaluate(el=>{el.value=el.value==='price'?'recommended':'price';el.dispatchEvent(new Event('change',{bubbles:true}));});
  await summary.focus();await ordinaryRender();assert.equal(await status.locator('details').evaluate(el=>el.open),true);assert.equal(await page.evaluate(()=>document.activeElement===document.querySelector('#search-status summary')),true,'passive result render preserves focused summary');
  await status.locator('[data-action="edit-search"]').focus();await ordinaryRender();assert.equal(await page.evaluate(()=>document.activeElement?.dataset.action),'edit-search','passive result render preserves focused current action');
  const samples=[];
  for(const width of [360,390,430,768,1280]){
   await page.setViewportSize({width,height:900});await status.scrollIntoViewIfNeeded();
   for(const largeText of [false,true]){
    await page.evaluate(large=>document.documentElement.style.fontSize=large?'32px':'',largeText);
    const geometry=await status.evaluate(el=>{
     const rect=node=>{const r=node.getBoundingClientRect();return{x:r.x,right:r.right,width:r.width,height:r.height};},bounds=rect(el);
     const controls=[...el.querySelectorAll('summary,button')].map(rect),rows=[...el.querySelectorAll('[data-search-source]')].map(row=>({key:row.dataset.searchSource,...rect(row)}));
     const texts=[...el.querySelectorAll('summary strong,summary>span,p,[data-search-source] strong,[data-search-source] small,button')].every(node=>{const range=document.createRange();range.selectNodeContents(node);return [...range.getClientRects()].every(r=>r.x>=bounds.x-1&&r.right<=bounds.right+1);});
     return{bounds,controls,rows,texts,overflow:document.documentElement.scrollWidth>innerWidth};
    });
    assert.equal(geometry.overflow,false,'status and whole page have no horizontal overflow at '+width+' text200='+largeText);assert(geometry.texts,'full source names/status/count/action text fits at '+width+' text200='+largeText);assert(geometry.controls.every(control=>control.height>=44),'status summary/actions retain44px targets at '+width);
    assert(geometry.rows.every(row=>row.x>=geometry.bounds.x&&row.right<=geometry.bounds.right+1),'source panels remain within their status owner');
    if(largeText&&width<=760)assert(geometry.rows[1].x===geometry.rows[0].x,'large text stacks source panels instead of breaking words in narrow columns');
    if(width<=760){
     const editWord=await page.locator('#compact-search .secondary[data-action="top"]').evaluate(el=>{
      const walker=document.createTreeWalker(el,NodeFilter.SHOW_TEXT);let node;while(node=walker.nextNode())if(node.data.includes('Изменить'))break;
      if(!node)return false;const start=node.data.indexOf('Изменить'),range=document.createRange();range.setStart(node,start);range.setEnd(node,start+'Изменить'.length);const rects=[...range.getClientRects()],bounds=el.getBoundingClientRect();
      return rects.length===1&&rects[0].x>=bounds.x&&rects[0].right<=bounds.right;
     });assert(editWord,'compact edit action keeps its ordinary word readable at '+width+' text200='+largeText);
     const routeWords=await page.locator('#compact-search .compact-route-block strong').evaluate(el=>{
      const node=el.firstChild,bounds=el.getBoundingClientRect();if(!node||node.nodeType!==Node.TEXT_NODE)return false;
      return [...node.data.matchAll(/[А-Яа-яЁё]+/g)].every(word=>{const range=document.createRange();range.setStart(node,word.index);range.setEnd(node,word.index+word[0].length);const rects=[...range.getClientRects()];return rects.length===1&&rects[0].x>=bounds.x-1&&rects[0].right<=bounds.right+1;});
     });assert(routeWords,'compact route preserves whole city/country words at '+width+' text200='+largeText);
    }
    await status.screenshot({path:path.join(evidence,`search-delivery-failed-${width}${largeText?'-text200':''}.png`)});samples.push({width,largeText,geometry});
    for(const action of ['retry-search','edit-search']){
     const control=status.locator(`[data-action="${action}"]`);await control.evaluate(el=>el.scrollIntoView({block:'center',behavior:'instant'}));
     const hit=await control.evaluate(el=>{const r=el.getBoundingClientRect(),node=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return{reachable:!!node&&(node===el||el.contains(node)),y:r.y,bottom:r.bottom,scrollY,hit:node?.className};});
     assert(hit.reachable,'status '+action+' remains reachable above fixed mobile controls at '+width+' text200='+largeText+': '+JSON.stringify(hit));
     if(width===390&&largeText&&action==='edit-search')await page.screenshot({path:path.join(evidence,'search-delivery-text200-controls-390.png')});
    }
   }
   await page.evaluate(()=>document.documentElement.style.fontSize='');
  }
  assert.equal(transport.calls.filter(call=>call.action==='search_start').length,1);assert.equal(transport.calls.filter(call=>call.action==='search_continue').length,1);assert.deepEqual(anexCalls,['search','continue']);
  assert(!transport.calls.some(call=>/andromeda|quote|lead/.test(call.url)),'delivery inspection/filter/resize do not issue SAMO, quote or lead operations');
  assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
  fs.writeFileSync(path.join(evidence,'search-delivery-journey.json'),JSON.stringify({phases:['pending-unknown-count','healthy-bounded-first-page','explicit-continuation-failure-retains-offers'],anexCalls,TVstarts:1,TVcontinuations:1,details_and_focus_preserved:true,samples,supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));
  console.log('PASS compiled search delivery: pending unknown count, healthy native first page, explicit failed continuation/retained offers, passive disclosure/focus and five widths/text200; supplier HTTP0');
 }finally{releaseAnex();await context.close();}
};
// Off for the real-default regression below. Retained SAMO integration journeys
// explicitly enable only this local fictional transport after that check passes.
let enableSamoFixture=false;
const server=http.createServer((req,res)=>{
 const u=new URL(req.url,'http://fixture');if(!u.pathname.startsWith(base)){res.writeHead(404).end();return;}
 const local=path.resolve(root,u.pathname.slice(base.length)||'index.php');if(!local.startsWith(root+'/')){res.writeHead(403).end();return;}
 const file=fs.existsSync(local)&&fs.statSync(local).isDirectory()?path.join(local,'index.php'):local;
 if(!fs.existsSync(file)){res.writeHead(404).end();return;}
 if(file.endsWith('.php')){
  let html=execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php"; include $argv[1];',file]).toString();
  if(u.searchParams.get('loadingMode')==='eager'){
   const cold=html.match(/data-flight-picker-src="([^"]+)"/);assert(cold,'explicit cold asset for controlled eager baseline');
   const initial=html;html=html.replace(/(<script src="\.\/app\.js[^"\n]*" defer><\/script>)/,`<script src="${cold[1]}" defer></script>\n$1`);assert.notEqual(html,initial,'controlled eager baseline inserts before the host');
  }
  res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);return;
 }
 let body=fs.readFileSync(file);
 if(enableSamoFixture&&file.endsWith('/prototype-search/config.js'))body=body.toString()+"\nwindow.V2_CONFIG.andromedaApi='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php';window.V2_CONFIG.andromedaQuoteApi='/_preview/search3-anex-candidate/api-andromeda-quote-preview.php';\n";
 res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':'application/octet-stream');res.end(body);
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port,browser=await chromium.launch();const receipts=[];
 try{
  for(const width of [390,1280]){
   const transport=fixture(),errors=[],forbidden=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));
   await page.route('**/*',async route=>{
    const req=route.request(),url=new URL(req.url());
    if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
    if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
    try{const value=await transport.json(req.url(),{body:req.postData()});await route.fulfill({contentType:'application/json',body:JSON.stringify(value)});}catch(error){forbidden.push(error.message);await route.abort();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));
   await page.locator('.search-submit:not(:disabled)').waitFor();
   assert.equal(transport.calls.filter(call=>call.action==='search_start').length,0,'default page does not auto-search');
   await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('2 варианта'));
   await page.locator('[data-action="all-offers"][data-id="501"]').click();
   await page.locator('#modal-body [data-action="offer"][data-key^="anex%3A"]').waitFor();
   assert.equal(await page.locator('#modal-body [data-action="offer"][data-key^="tourvisor%3A"]').count(),1);
   assert.equal(await page.locator('#modal-body [data-action="offer"][data-key^="andromeda%3A"]').count(),0);
   assert(!transport.calls.some(call=>call.url.includes('andromeda')),'unmodified compiled config sends no SAMO requests');
   assert(transport.calls.some(call=>call.action==='search_start'));assert(transport.calls.some(call=>call.url.endsWith('/api-anex-search3-preview.php')));
   await page.screenshot({path:path.join(evidence,'samo-paused-'+width+'.png')});
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   fs.writeFileSync(path.join(evidence,'samo-paused-'+width+'.json'),JSON.stringify({width,providers:['tourvisor','anex'],samo_requests:0,supplier_HTTP:0,real_leads:0,physicalSafari:false},null,2));await context.close();
  }
  console.log('PASS compiled default config at390/1280: TV + direct ANEX offers; SAMO requests0');
  await searchDeliveryJourney(browser,origin,base,evidence);
  enableSamoFixture=true;
  // Complete, independent matrices share only immutable compiled assets. The
  // first two use this browser; the large-flight owner retains its own browser,
  // server and evidence directory. Default/SAMO-mode transition tests above stay
  // ordered. Retain every rejection and settle all three before shared cleanup.
  const completed=await Promise.allSettled([
   formPickerActionJourney(browser,origin,base,evidence),
   (async()=>{
  for(const width of [360,390,430,768,1280]){
  const transport=fixture({tvFuel:20686}),errors=[],forbidden=[],flightDownloads=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));page.on('request',request=>{if(new URL(request.url()).pathname.endsWith('/flight-picker-ui-v1.js'))flightDownloads.push(request.url());});
  let releaseInitialCatalog;transport.state.countryGates['1']=new Promise(resolve=>releaseInitialCatalog=resolve);
  await page.addInitScript(()=>{window.quoteFailures=[];window.addEventListener('anytour:quote-failure',e=>window.quoteFailures.push(e.detail));});
  let releaseAnexQuote,markAnexQuotePending;let anexQuotePending=new Promise(resolve=>markAnexQuotePending=resolve);
  let releaseSamoPair,markSamoPairPending;let samoPairGate=new Promise(resolve=>releaseSamoPair=resolve);
  await page.route('**/*',async route=>{
   const req=route.request(),u=new URL(req.url());
   if(u.pathname==='/test-missing-photo.svg'){await route.fulfill({status:404,contentType:'text/plain',body:'Fictional photo unavailable'});return;}
   if(u.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
   if(u.pathname.startsWith(base)&&!u.pathname.includes('/data/')){await route.continue();return;}
   try{const value=await transport.json(req.url(),{body:req.postData()});if(value.kind==='country'&&value.items)value.items.push(...['Египет','ОАЭ','Таиланд','Вьетнам','Мальдивы','Шри-Ланка','Китай','Россия','Австрия','Саудовская Аравия'].map((name,i)=>({id:100+i,kind:'country',parentId:null,name,slug:'test-country-'+i,revision:1,tourvisorIds:[String(100+i)]})));if(value.data?.state==='flight_selection_required'&&!transport.state.samoSolePair)value.data.flights.push(...value.data.flights.map((f,i)=>({...f,name:'TEST SAMO ALTERNATIVE '+i,flight_ref:'flight_'+String(i+3).repeat(32)})));if(JSON.parse(req.postData()||'{}').action==='quote_select_flights'){markSamoPairPending?.();await samoPairGate;}if(u.pathname.includes('anex')&&value.data?.status==='quote_verified')await new Promise(resolve=>{releaseAnexQuote=resolve;markAnexQuotePending();});await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});}catch(e){forbidden.push(e.message);await route.abort();}
  });
  if(width===360||width===430){
   releaseInitialCatalog();delete transport.state.countryGates['1'];
   await hotelPickerBlock(page,width,transport,origin,base,evidence);
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   receipts.push({width,targeted_hotel_picker:true,supplier_requests:0,lead_requests:0});await context.close();continue;
  }
  await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:'',searched:'1',stars:'4'}));
  const hydrationControls=page.locator('.intro [data-action="filters"],#search-form [data-action="departure"],#search-form [data-action="destination"],#search-form [data-action="dates"],#search-form [data-action="nights"],#search-form [data-action="guests"],#quick-stars button,#quick-meal,#quick-budget');
  assert.equal(await page.locator('#search-form').getAttribute('aria-busy'),'true','initial form discloses catalog/URL hydration');
  assert.equal(await hydrationControls.evaluateAll(controls=>controls.every(control=>control.disabled)),true,'initial controls cannot accept input that URL hydration would overwrite');
  assert.equal(await page.locator('#destination-label').textContent(),'Загружаем направления…','initial destination never exposes a missing catalog value');
  assert.doesNotMatch(await page.locator('#country').getAttribute('aria-label'),/undefined|null/i,'initial destination accessible name stays truthful');
  assert.equal(await page.locator('#results').evaluate(node=>node.classList.contains('results-pristine')),true,'initial results shell is marked pristine before catalog hydration');
  assert.match(await page.locator('#cards').textContent(),/Готовим поиск/,'initial results shell truthfully describes hydration');
  assert.equal(await page.locator('#price-calendar').isVisible(),false,'initial results shell cannot flash the post-search calendar');
  assert.equal(await page.locator('.results-toolbar').isVisible(),false,'initial results shell cannot flash post-search controls');
  releaseInitialCatalog();delete transport.state.countryGates['1'];
  await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
  assert.equal(await page.locator('#search-form').getAttribute('aria-busy'),'false');
  await mobileQuickFieldLayout(page,width);
  assert.equal(await page.locator('#quick-stars [data-value="4"]').getAttribute('aria-pressed'),'true','URL stars restore after delayed catalog hydration');
  assert.equal(await page.locator('#quick-stars [data-value="5"]').getAttribute('aria-pressed'),'false','a locked pre-hydration control cannot replace URL state');
  await page.locator('#quick-stars [data-action="any-stars"]').click();
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0);
  await page.locator('#quick-budget').click();await page.locator('#budget-max').fill('abc');assert.equal(await page.locator('#budget-max').getAttribute('aria-invalid'),'true');
  assert(await page.locator('[data-action="apply-budget"]').isDisabled(),'invalid budget cannot be committed');
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0,'invalid budget starts no search');
  await page.locator('[data-action="close-modal"]').click();const initialInvalidBudgetBlocked=true;
  await page.locator('#search-form [data-action="destination"]').click();
  assert.equal(await page.locator('[data-action="destination-country"]:visible').count(),0,'approved direction begins with the current country and resorts');
  await page.screenshot({path:path.join(evidence,`destination-compact-${width}.png`)});
  await page.locator('[data-action="destination-countries"]').click();assert.equal(await page.locator('[data-action="destination-country"]:visible').count(),11,'all saved countries are reachable');
  await page.locator('[data-action="destination-country"][data-value="107"]').click();
  assert.match(await page.locator('.destination-current-country').textContent(),/Россия/,'country choice updates the draft context');
  await page.locator('#destination-query').fill('сауд');
  assert.equal(await page.locator('[data-action="destination-country"]:visible').count(),1,'unified search includes all saved countries');
  assert.match(await page.locator('[data-action="destination-country"]:visible').textContent(),/Саудовская Аравия/);
  await page.locator('[data-action="close-modal"]').click();
  assert.match(await page.locator('#country').textContent(),/Турция/,'cancel preserves the original destination');
  await page.locator('#quick-budget').click();
  await page.locator('#budget-min').fill('150000');await page.locator('#budget-max').fill('180000');
  await page.locator('[data-action="apply-budget"]').click();
  await page.locator('#search-form [data-action="dates"]').click();
  await page.waitForFunction(()=>document.querySelector('#date-calendar').textContent.includes('167,5'));
  assert.doesNotMatch(await page.locator('#date-calendar').textContent(),/97,5/,'cheaper out-of-budget observation cannot leak');
  const budgetRequest=transport.calls.filter(call=>call.action==='price_calendar').at(-1).body;
  assert.equal(budgetRequest.priceFrom,150000);assert.equal(budgetRequest.priceTo,180000);
  assert.equal(transport.calls.filter(call=>call.action==='search_start'||call.url.includes('/api-anex-')||call.url.includes('/api-andromeda-')).length,0,'budget calendar stays supplier-free');
  await page.screenshot({path:path.join(evidence,`calendar-budget-${width}.png`)});
  await page.reload();
  await page.waitForFunction(()=>document.querySelector('#modal')?.open&&document.querySelector('#date-calendar'));
  await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
  await page.waitForFunction(()=>document.querySelector('#date-calendar').textContent.includes('167,5')||document.querySelector('#date-calendar').textContent.includes('97,5'));
  const reloadedURL=new URL(page.url());
  assert.equal(reloadedURL.searchParams.get('min'),'150000','reload with the calendar open preserves the minimum budget');
  assert.equal(reloadedURL.searchParams.get('max'),'180000','reload with the calendar open preserves the maximum budget');
  const reloadedBudgetRequest=transport.calls.filter(call=>call.action==='price_calendar').at(-1).body;
  assert.equal(reloadedBudgetRequest.priceFrom,150000);assert.equal(reloadedBudgetRequest.priceTo,180000);
  assert.doesNotMatch(await page.locator('#date-calendar').textContent(),/97,5/,'reload cannot reopen an unbounded calendar');
  await page.locator('[data-action="close-modal"]').click();
  await page.locator('#quick-budget').click();
  await page.locator('#budget-min').fill('');await page.locator('#budget-max').fill('');
  await page.locator('[data-action="apply-budget"]').click();
  await page.locator('#search-form [data-action="dates"]').click();
  await page.waitForFunction(()=>document.querySelector('#date-calendar').textContent.includes('97,5'));
  if(width<=760){
   const heading=await page.evaluate(month=>{const tools=document.querySelector('.date-choice-tools').getBoundingClientRect(),h=document.querySelector(`[data-month="${month}"] h3`).getBoundingClientRect();return{top:h.top,toolsBottom:tools.bottom};},trip.from.slice(0,7)+'-01');
   assert(heading.top>=heading.toolsBottom,'selected month remains below the async calendar notice');
  }

  await page.locator('[data-action="apply-dates"]').click();
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0);
  let releaseSamoSearch;transport.state.samoSearchGate=new Promise(resolve=>releaseSamoSearch=resolve);
  await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('2 варианта'));
  await openResultFilters(page,width);
  const filterEditor=page.locator(width<=1100?'#max-price':'#hotel-query'),filterText=width<=1100?'180000':'Вымышленный',filterSuffix=width<=1100?'0':' отель',selectionEnd=width<=1100?5:8;
  if(width<=1100){assert.equal(await page.locator('#hotel-query').isVisible(),false,'approved mobile destination stays in the unified Куда picker');assert.match(await page.locator('.filter-destination-note').textContent(),/Курорты и отели/);}
  await filterEditor.fill(filterText);await filterEditor.evaluate((el,end)=>{el.setSelectionRange(3,end);window.activeFilterEditor=el;},selectionEnd);
  releaseSamoSearch();transport.state.samoSearchGate=null;
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
  assert.deepEqual(await filterEditor.evaluate(el=>({same:el===window.activeFilterEditor,focused:document.activeElement===el,start:el.selectionStart,end:el.selectionEnd})),{same:true,focused:true,start:3,end:selectionEnd});
  await filterEditor.press('End');await page.keyboard.type(filterSuffix);assert.equal(await filterEditor.inputValue(),filterText+filterSuffix);
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,1,'filter typing never repeats the initial search');
  assert.equal(await page.locator('[data-filter="amenities"][value^="local-service:"]').count(),0,'display-only LOCAL service facts never become search filters');
  await page.screenshot({path:path.join(evidence,`progressive-filter-${width}.png`)});
  if(width<=1100){await page.locator('.mobile-close[data-action="close-filters"]').click();assert.equal(await filterEditor.inputValue(),'','cancelled mobile budget draft does not leak into applied filters');}
  else await page.locator('[data-action="clear-hotel-query"]').click();
  assert.equal(await page.locator('.hotel-card').count(),1);
  assert.match(await page.locator('.hotel-card .hotel-facts').textContent(),/Wi-Fi из локального профиля/,'legacy canonical service fact is visible on the target hotel card');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:path.join(evidence,`results-${width}.png`)});
  await mobileCardPriceLayout(page,width,evidence);
  await appliedSummaryControls(page,width,transport,evidence);
  const cardsBeforeDeparture=await page.locator('#cards').innerHTML(),urlBeforeDeparture=page.url(),startsBeforeDeparture=transport.calls.filter(c=>c.action==='search_start').length;
  await editResultSearch(page,width);transport.state.countriesFailure='2';await page.locator('[data-action="departure"]').click();await page.locator('[data-action="choose-departure"][data-value="Казань"]').click();await page.locator('[data-action="apply-departure"]').click();
  await page.locator('#catalog-error [data-action="retry-countries"]').waitFor();assert(await page.locator('.search-submit').isDisabled());
  await page.screenshot({path:path.join(evidence,`departure-error-${width}.png`)});
  await page.locator('[data-action="destination"]').click();assert(await page.locator('[data-action="apply-destination"]').isDisabled());await page.locator('[data-action="close-modal"]').click();
  transport.state.countriesFailure='';await page.locator('#catalog-error [data-action="retry-countries"]').click();await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
  await page.locator('[data-action="departure"]').click();await page.locator('[data-action="choose-departure"][data-value="Екатеринбург"]').click();await page.locator('[data-action="apply-departure"]').click();await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
  await page.locator('#search-form [data-action="dates"]').click();assert.match(await page.locator('.calendar-context').textContent(),/Екатеринбург/);
  await page.waitForFunction(()=>document.querySelector('#date-calendar').textContent.includes('97,5'));
  await page.screenshot({path:path.join(evidence,`departure-calendar-${width}.png`)});
  await page.locator('[data-action="close-modal"]').click();await page.locator('#search-return').click();await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
  assert.equal(await page.locator('#origin').inputValue(),'Москва');assert.equal(await page.locator('#cards').innerHTML(),cardsBeforeDeparture);assert.equal(page.url(),urlBeforeDeparture);
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,startsBeforeDeparture,'departure recovery never starts a supplier search');

  const beforeGallery=transport.calls.length;
  await page.locator('.hotel-image-button[data-id="501"]').click();
  const contrast=await page.locator('.gallery-dialog .modal-header').evaluate(header=>{
   const rgb=value=>(value.match(/[\d.]+/g)||[]).slice(0,3).map(Number);
   const luminance=color=>rgb(color).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
   const background=luminance(getComputedStyle(header).backgroundColor);
   const ratio=el=>{const text=luminance(getComputedStyle(el).color);return(Math.max(text,background)+.05)/(Math.min(text,background)+.05);};
   return{title:ratio(header.querySelector('h2')),close:ratio(header.querySelector('[data-action="close-modal"]'))};
  });
  assert(contrast.title>=4.5&&contrast.close>=3,'gallery title and close contrast against the actual header');
  await page.screenshot({path:path.join(evidence,`gallery-header-${width}.png`)});
  await page.locator('[data-action="close-modal"]').click();
  assert.equal(transport.calls.length,beforeGallery,'gallery adds no provider request');
  await page.locator('[data-action="hotel-details"][data-id="501"]').first().click();
  await page.locator('#hotel-room-count').waitFor();
  assert((await page.locator('#modal-body').textContent()).includes('Тестовая улица'));
  assert.match(await page.locator('#modal-body').textContent(),/Wi-Fi из локального профиля/,'legacy canonical service fact remains visible in hotel details');
  assert(await page.evaluate(()=>!!(document.querySelector('#hotel-services-heading').compareDocumentPosition(document.querySelector('#hotel-rooms-heading')) & Node.DOCUMENT_POSITION_FOLLOWING)));
  await page.locator('[data-action="close-modal"]').click();
  await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  const tvOffer=page.locator('#modal-body [data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');
  await tvOffer.waitFor({state:'visible'});
  await tvOffer.click();await mobileFooterLayout(page,width);await page.locator('[data-action="start-lead"]').click();await page.locator('#prototype-lead-form').waitFor();
  assert.match(await page.locator('#modal-body').textContent(),/Рейс уточнит менеджер/);
  assert.equal((await page.locator('.selection-steps li').nth(1).textContent()).trim(),'2Перелёт позже');
  assert.equal(await page.locator('.selection-steps li').nth(1).evaluate(el=>el.classList.contains('previous')),false);
  assert.match(await page.locator('.tour-fuel-disclosure').textContent(),/включён в цену/);
  assert.match(await page.locator('#modal-footer').textContent(),/Цена предложения/);
  assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/120000/,'fuel is already part of the supplier total');
  await contactLayout(page,width,'Tourvisor');
  const quoteOnlyCalls=transport.calls.length;
  await page.screenshot({path:path.join(evidence,`application-without-flight-${width}.png`)});
  await page.locator('[data-action="close-modal"]').click();
  await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  await tvOffer.waitFor({state:'visible'});
  await tvOffer.click();await page.locator('[data-action="confirm-tour"]').click();await page.locator('#prototype-lead-form').waitFor();
  assert.match(await page.locator('#modal-body').textContent(),/Рейс уточнит менеджер/);
  assert.equal(transport.calls.length,quoteOnlyCalls,'return to the same actualized tour without flights adds no request');
  assert.equal(flightDownloads.length,0,'catalogue, results and quote without flights leave the picker cold');
  await page.locator('#modal-back').click();await continueToFlights(page);await page.waitForFunction(()=>document.querySelector('[data-action="confirm-tour"]')&&!document.querySelector('[data-action="confirm-tour"]').disabled);
  assert.equal(flightDownloads.length,1,'first actual picker open downloads one UI owner');assert.match(new URL(flightDownloads[0]).search,/^\?v=[0-9a-f]{12}$/,'PHP binds the exact compiled picker hash');
  await page.locator('[data-action="choose-flight"]').click();await page.locator('[name="flight-pair"][value="1"]').check();await page.locator('[data-action="apply-flight"]').click();await page.locator('#prototype-lead-form').waitFor();assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/133500/);await page.locator('#modal-back').click();assert.equal(flightDownloads.length,1,'warm picker has no second download');
  assert((await page.locator('#detail-total').textContent()).replace(/\s/g,'').includes('133500'));
  await page.locator('[data-action="confirm-tour"]').click();
  assert.equal((await page.locator('.selection-steps li').nth(1).textContent()).trim(),'2Перелёт');
  assert.equal(await page.locator('.selection-steps li').nth(1).evaluate(el=>el.classList.contains('previous')),true);
  assert.match((await page.locator('#modal-body .tour-fuel-disclosure').textContent()).replace(/\s/g,''),/20686₽/);
  assert.match(await page.locator('#modal-footer').textContent(),/Цена с выбранными рейсами · сбор включён/);
  await page.screenshot({path:path.join(evidence,`application-fuel-${width}.png`)});
  await page.locator('[name="name"]').fill('Тестовый турист');await page.locator('[name="phone"]').fill('+7 999 123-45-67');await page.locator('[name="comment"]').fill('Тестовый комментарий');await page.locator('[name="consent"]').check();await page.locator('[type="submit"][form="prototype-lead-form"]').click();
  await page.waitForFunction(()=>document.querySelector('#prototype-lead-form').dataset.checked==='1');assert((await page.locator('.lead-message').textContent()).includes('не отправлена'));
  await page.screenshot({path:path.join(evidence,`application-${width}.png`)});
  await page.locator('#modal-back').click();assert.match((await page.locator('#detail-total').textContent()).replace(/\s/g,''),/133500/);
  const tvRequests=()=>transport.calls.filter(c=>c.url==='/api-v2.php'&&['tour','flights'].includes(c.action)).length,beforeTvReturn=tvRequests();
  await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  await tvOffer.waitFor({state:'visible'});
  await tvOffer.click();await continueToFlights(page);await page.locator('[data-action="confirm-tour"]').waitFor();
  assert.match((await page.locator('#detail-total').textContent()).replace(/\s/g,''),/133500/);
  assert.match(await page.locator('.flight-summary').textContent(),/TEST201/);
  assert.equal(tvRequests(),beforeTvReturn,'TV close/reopen does not repeat quote/flights');
  await page.screenshot({path:path.join(evidence,`tourvisor-return-${width}.png`)});
  await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  transport.state.samoFlightChoice=true;
  const samoOffer=page.locator('#modal-body [data-action="offer"][data-key^="andromeda%3A"]').first();
  await samoOffer.waitFor({state:'visible'});
  await samoOffer.click();await page.locator('[data-action="refresh-hotel"]').click();
  await page.locator('[name="andromeda-outbound"]').first().waitFor();
  assert.match((await page.locator('[name="andromeda-outbound"]').first().locator('xpath=ancestor::label').textContent()).replace(/\s/g,''),/Доплатаоператора:2000₽/);
  assert.match(await page.locator('#andromeda-flight-price-status').textContent(),/пока не подтверждена/);
  assert.equal(await page.locator('[data-action="andromeda-application-preview"]').count(),0);
  await page.screenshot({path:path.join(evidence,`samo-flight-prices-${width}.png`)});
  const chosenOut='flight_'+'3'.repeat(32),chosenBack='flight_'+'4'.repeat(32);
  await page.locator('[name="andromeda-outbound"][value="'+chosenOut+'"]').check();
  await page.locator('[name="andromeda-return"][value="'+chosenBack+'"]').check();
  const beforeSamoDraftReturn=transport.calls.length;
  await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  await samoOffer.waitFor({state:'visible'});
  await samoOffer.click();
  assert.equal(await page.locator('[name="andromeda-outbound"]:checked').inputValue(),chosenOut);
  assert.equal(await page.locator('[name="andromeda-return"]:checked').inputValue(),chosenBack);
  assert.equal(transport.calls.length,beforeSamoDraftReturn,'ordinary reopen retains both directions without requoting');
  await page.locator('[data-action="apply-andromeda-flights"]').click();
  assert.equal(await page.locator('[name="andromeda-outbound"]:checked').isDisabled(),true);
  await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  await samoOffer.waitFor({state:'visible'});
  await samoOffer.click();
  assert.equal(await page.locator('[name="andromeda-outbound"]:checked').inputValue(),chosenOut);
  assert.equal(await page.locator('[name="andromeda-return"]:checked').inputValue(),chosenBack);
  assert.equal(await page.locator('[name="andromeda-return"]:checked').isDisabled(),true);
  assert.equal(await page.locator('[data-action="apply-andromeda-flights"]').isDisabled(),true);
  assert.match(await page.locator('#andromeda-flight-price-status').textContent(),/Уточняем полную цену/);
  await page.screenshot({path:path.join(evidence,`samo-flight-draft-return-${width}.png`)});
  const submittedPair=transport.calls.findLast(call=>call.action==='quote_select_flights').body.flight_selection;
  assert.equal(submittedPair.outbound_ref,chosenOut);assert.equal(submittedPair.return_ref,chosenBack);
  assert.equal(transport.calls.length,beforeSamoDraftReturn+1,'pending reopen never repeats continuation');releaseSamoPair();
  await page.locator('[data-action="andromeda-application-preview"]').waitFor();const beforeSamoReturn=transport.calls.length;
  await page.locator('#modal-back').click();await page.locator('#all-offers-list').waitFor();
  await page.screenshot({path:path.join(evidence,`samo-return-${width}.png`)});
  await samoOffer.waitFor({state:'visible'});
  await samoOffer.click();await page.locator('[data-action="andromeda-application-preview"]').click();
  assert.equal(transport.calls.length,beforeSamoReturn);transport.state.samoFlightChoice=false;
  assert((await page.locator('#modal-body').textContent()).includes('SAMO STANDARD'));
  assert.equal(await page.locator('[name="name"]').inputValue(),'Тестовый турист');
  assert.equal(await page.locator('[name="phone"]').inputValue(),'+7 999 123-45-67');
  assert.equal(await page.locator('[name="comment"]').inputValue(),'Тестовый комментарий');
  assert.equal(await page.locator('[name="consent"]').isChecked(),false);
  await page.locator('[name="phone"]').fill('+7 999 123-45-67');await page.locator('[name="consent"]').check();await page.locator('[type="submit"][form="prototype-lead-form"]').click();
  await page.waitForFunction(()=>document.querySelector('#prototype-lead-form').dataset.checked==='1');assert((await page.locator('.lead-message').textContent()).includes('не отправлена'));
  await contactLayout(page,width,'SAMO');if(width===390)await applicationViewportLayout(page,width,transport,evidence,'SAMO',false);
  await page.screenshot({path:path.join(evidence,`samo-application-${width}.png`)});
  await forwardProviderApplication(page,transport,'SAMO STANDARD','125500');
  await page.screenshot({path:path.join(evidence,`samo-application-forward-${width}.png`)});
  // Expiry while the form is open must be checked again at submit, not only on mount.
  const beforeSamoExpiry=transport.calls.length;
  await page.clock.setFixedTime(new Date(Date.now()+901000));
  await page.locator('[name="consent"]').check();await page.locator('[type="submit"][form="prototype-lead-form"]').click();
  await page.getByText('Срок подтверждения тура истёк. Выполните новый поиск.',{exact:true}).waitFor();
  assert.equal(await page.locator('#prototype-lead-form').getAttribute('data-checked'),null);
  assert.equal(await page.locator('[name="phone"]').inputValue(),'+7 999 123-45-67','expiry preserves the entered contact draft');
  await page.screenshot({path:path.join(evidence,`samo-expired-application-${width}.png`)});
  await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!history.state?.['anytour.prototype.v18.ui.v1']);
  await page.evaluate(()=>history.forward());
  await page.getByText('Срок подтверждения тура истёк. Выполните новый поиск.',{exact:true}).waitFor();
  assert.equal(await page.locator('[data-action="andromeda-application-preview"]').count(),0,'Forward cannot reopen an expired verified application');
  assert.equal(await page.locator('[data-action="refresh-hotel"]').count(),0,'expiry cannot replay the same supplier quote');
  assert.equal(transport.calls.length,beforeSamoExpiry,'expiry and history navigation are passive');
  await page.screenshot({path:path.join(evidence,`samo-expired-return-${width}.png`)});
  await page.clock.setFixedTime(new Date());

  await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  const anexOffer=page.locator('#modal-body [data-action="offer"][data-key^="anex%3A"]').first();await anexOffer.waitFor({state:'visible'});
  await anexOffer.click();await page.locator('[data-action="refresh-hotel"]').click();
  await page.waitForFunction(()=>document.querySelector('#modal-title').textContent==='Ваш тур в деталях'&&document.querySelector('#modal-body').textContent.includes('ANEX CONCRETE'));
  assert.equal(await page.locator('#all-offers-list').count(),0,'single expanded ANEX offer opens directly');
  assert.equal(transport.calls.filter(c=>c.action==='offer').length,0,'direct entry retains explicit concrete verification');
  transport.state.anexCurrentAdditional=true;
  await page.locator('[data-action="refresh-hotel"]').click();
  await page.waitForFunction(()=>document.querySelector('#modal-title').textContent==='Доплаты ANEX рассчитаны');assert((await page.locator('#modal-body').textContent()).replace(/\s/g,'').includes('123000'));
  assert.equal(transport.calls.filter(c=>c.action==='additional_prices').length,0,'ready current offer reuses retained APD');
  assert.equal(await page.locator('[data-action="anex-additional-prices"]').count(),0);
  await page.screenshot({path:path.join(evidence,`anex-retained-estimate-${width}.png`)});
  await page.locator('[data-action="anex-flights"]').click();await page.waitForFunction(()=>document.querySelector('#anex-flight-inventory').textContent.includes('TEST ANEX 101'));
  assert.match(await page.locator('#anex-flight-inventory').textContent(),/не выбранные рейсы/);
  await page.locator('#anex-flight-inventory').scrollIntoViewIfNeeded();
  assert.equal(await page.locator('#anex-flight-inventory').evaluate(el=>el.scrollWidth>el.clientWidth),false,'ANEX flight facts fit the target viewport');
  await page.screenshot({path:path.join(evidence,`anex-flights-${width}.png`)});
  const callsBeforeAnexApplication=transport.calls.length;await page.locator('[data-action="anex-application-preview"]').click();
  assert((await page.locator('#modal-footer').textContent()).includes('Расчётная стоимость'));assert((await page.locator('#modal-body').textContent()).includes('Расчётная стоимость требует подтверждения оператором'));
  await page.locator('[name="phone"]').fill('+7 999 123-45-67');await page.locator('[name="consent"]').check();await page.locator('[type="submit"][form="prototype-lead-form"]').click();
  await page.waitForFunction(()=>document.querySelector('#prototype-lead-form').dataset.checked==='1');assert((await page.locator('.lead-message').textContent()).includes('требует подтверждения'));
  assert.equal(transport.calls.length,callsBeforeAnexApplication,'ANEX application preview adds no provider request');
  await page.locator('.application-choice').scrollIntoViewIfNeeded();
  await contactLayout(page,width,'ANEX');if(width===390)await applicationViewportLayout(page,width,transport,evidence,'ANEX',false);
  await page.screenshot({path:path.join(evidence,`anex-retained-application-${width}.png`)});
  await forwardProviderApplication(page,transport,'ANEX CONCRETE','123000');
  await page.screenshot({path:path.join(evidence,`anex-application-forward-${width}.png`)});
  await page.locator('#modal-body [data-action="all-offers"]').click();
  const retainedAnex=page.locator('#modal-body [data-action="offer"][data-key^="anex%3A"]').first();
  await retainedAnex.waitFor({state:'visible'});
  await retainedAnex.click();assert.equal(await page.locator('#modal-title').textContent(),'Доплаты ANEX рассчитаны');
  assert.match((await page.locator('#modal-body').textContent()).replace(/\s/g,''),/123000/);
  await page.locator('[data-action="anex-application-preview"]').click();await page.locator('#modal-back').click();
  await page.locator('[data-action="anex-application-preview"]').click();assert.equal(transport.calls.length,callsBeforeAnexApplication);
  await page.screenshot({path:path.join(evidence,`anex-return-${width}.png`)});
  await page.locator('#modal-body [data-action="all-offers"]').click();
  await retainedAnex.waitFor({state:'visible'});
  await retainedAnex.click();await page.locator('[data-action="anex-package-quote"]').click();
  await page.locator('[name="anex-package-choice"]').nth(1).check();
  const legacyAnexFacts=await page.locator('[name="anex-package-choice"]').nth(1).evaluate(input=>[...input.closest('.flight-option').querySelectorAll('.flight-option-heading small')].slice(0,2).map(leg=>({text:leg.textContent,font:parseFloat(getComputedStyle(leg).fontSize)})));assert.equal(legacyAnexFacts.length,2);assert(legacyAnexFacts.every(leg=>leg.font>=14),'canonical ANEX leg names remain readable at '+width);assert(legacyAnexFacts[0].text.includes('TEST ANEX PACKAGE 2 OUT'));assert(legacyAnexFacts[1].text.includes('TEST ANEX PACKAGE 2 BACK'));
  assert.equal(await page.locator('.flight-option:has([name="anex-package-choice"]:checked) .flight-option-selected').isVisible(),true);assert.equal(await page.locator('.flight-option:has([name="anex-package-choice"]:not(:checked)) .flight-option-selected').isVisible(),false);
  await page.screenshot({path:path.join(evidence,`anex-package-choices-${width}.png`)});
  await page.locator('[data-action="anex-package-calculate"]').click();await anexQuotePending;
  assert.equal(await page.locator('[name="anex-package-choice"]').nth(1).isChecked(),true,'requested second pair stays selected while calculating');
  assert.equal(await page.locator('[name="anex-package-choice"]').nth(1).isDisabled(),true);
  assert.equal(await page.locator('[data-action="anex-application-preview"]').count(),0);
  await page.screenshot({path:path.join(evidence,`anex-package-pending-${width}.png`)});
  releaseAnexQuote();await page.locator('[data-action="anex-application-preview"]').waitFor();
  assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/135678,9/);
  assert.match(await page.locator('#modal-body').textContent(),/TEST ANEX PACKAGE 2 OUT/);
  await page.screenshot({path:path.join(evidence,`anex-package-verified-${width}.png`)});
  await page.locator('[data-action="anex-application-preview"]').click();await page.locator('[name="consent"]').check();
  await providerApplicationFlightSummary(page,width,[`TEST ANEX PACKAGE 2 OUT · Москва SVO → Анталья AYT · ${day} 10:00`,`TEST ANEX PACKAGE 2 BACK · Анталья AYT → Москва SVO · ${back} 14:00`],evidence,'anex-legacy');
  await page.locator('[type="submit"][form="prototype-lead-form"]').click();
  await page.waitForFunction(()=>document.querySelector('#prototype-lead-form').dataset.checked==='1');
  assert.match(await page.locator('.lead-message').textContent(),/Подтверждённая стоимость/);
  await forwardProviderApplication(page,transport,'ANEX CONCRETE','135678,9');
  await page.screenshot({path:path.join(evidence,`anex-package-application-${width}.png`)});
  assert.equal(transport.calls.filter(c=>c.action==='quote_start').length,1);assert.equal(transport.calls.filter(c=>c.action==='quote_calculate').length,1);
  await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  await tvOffer.waitFor({state:'visible'});
  await tvOffer.click();await continueToFlights(page);await page.locator('[data-action="confirm-tour"]').click();
  assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/133500/);
  assert.equal(tvRequests(),beforeTvReturn,'cross-provider return keeps the TV receipt and price');

  assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth),false);assert(!transport.calls.some(c=>/lead|payment/.test(c.url)));assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
  // Actual user order: expanding ANEX must leave another source's visible offer usable.
  await page.locator('[data-action="close-modal"]').click();
  await editResultSearch(page,width);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>(document.querySelector('#search-status').hidden||!document.querySelector('[data-action="stop-search"]'))&&document.querySelector('#results-summary').textContent.includes('3 варианта'));
  const beforeCrossSource=transport.calls.length;
  await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
  await anexOffer.waitFor({state:'visible'});
  await anexOffer.click();await page.locator('[data-action="refresh-hotel"]').click();
  await page.waitForFunction(()=>document.querySelector('#modal-body').textContent.includes('ANEX CONCRETE'));
  await page.locator('#modal-back').click();
  await samoOffer.waitFor({state:'visible'});
  await samoOffer.click();await page.locator('[data-action="refresh-hotel"]').click();
  await page.locator('[data-action="andromeda-application-preview"]').waitFor();
  assert.match((await page.locator('#modal-body').textContent()).replace(/\s/g,''),/125500/);
  const crossSourceCalls=transport.calls.slice(beforeCrossSource);
  assert.equal(crossSourceCalls.filter(c=>c.url.endsWith('/api-andromeda-quote-preview.php')).length,1);
  assert.equal(crossSourceCalls.filter(c=>c.action==='search_start').length,0,'ANEX expansion does not restart the mixed search');
  assert.equal(crossSourceCalls.filter(c=>c.action==='search').length,0,'opening a retained group never repeats direct initial search');
  assert.equal(crossSourceCalls.filter(c=>c.action==='expand').length,1,'one selected-group request uses the retained server context');
  await page.screenshot({path:path.join(evidence,`samo-after-anex-${width}.png`)});
  for(const flightChoice of [false,true]){
   await page.locator('[data-action="close-modal"]').click();
   transport.state.samoFailure='supplier_auth';transport.state.samoFlightChoice=flightChoice;
   transport.state.tvFlightFuel=flightChoice?{value:'0'}:width===390?' \t ':width===768?{value:null}:false;
   await editResultSearch(page,width);await page.locator('.search-submit').click();
   await page.waitForFunction(()=>(document.querySelector('#search-status').hidden||!document.querySelector('[data-action="stop-search"]'))&&document.querySelector('#results-summary').textContent.includes('3 варианта'));
   await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
   {
    await tvOffer.waitFor({state:'visible'});
    await tvOffer.click();await continueToFlights(page);await page.locator('[data-action="confirm-tour"]').waitFor();
    assert.equal(tvRequests(),beforeTvReturn+(flightChoice?4:2),'new search invalidates the previous TV selection');
    assert.match((await page.locator('#detail-total').textContent()).replace(/\s/g,''),/120000/);
    const fuelMessage=flightChoice?/Без доплаты по сбору/:/Сбор уточняется/;
    assert.match(await page.locator('.tour-fuel-disclosure').textContent(),fuelMessage,'pair fuel keeps explicit zero distinct from unknown');
    await page.locator('[data-action="confirm-tour"]').click();
    assert.match(await page.locator('.tour-fuel-disclosure').textContent(),fuelMessage,'application retains the fuel evidence state');
    assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/120000/,'fuel validation does not change the offered total');
    await page.screenshot({path:path.join(evidence,`fuel-${flightChoice?'zero':'unknown'}-${width}.png`)});
    await page.locator('[data-action="close-modal"]').click();await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
   }
   const chooseSamo=async()=>{const offer=page.locator('#modal-body [data-action="offer"][data-key^="andromeda%3A"]').first();await offer.waitFor({state:'visible'});await offer.click();};
   const quoteCount=()=>transport.calls.filter(c=>c.url.endsWith('/api-andromeda-quote-preview.php')).length;
   await chooseSamo();const before=quoteCount();await page.locator('[data-action="refresh-hotel"]').click();
   if(flightChoice){
    await page.locator('[data-action="apply-andromeda-flights"]').waitFor();
    assert.equal(await page.locator('[name="andromeda-outbound"]:checked').inputValue(),'flight_'+'1'.repeat(32),'a new search cannot inherit an old flight draft');
    assert.equal(await page.locator('[name="andromeda-return"]:checked').inputValue(),'flight_'+'2'.repeat(32));
    await page.locator('[data-action="apply-andromeda-flights"]').click();
   }
   await page.waitForFunction(()=>document.querySelector('#modal-body .error-text')?.textContent.includes('Подтверждение тура не получено'));
   assert.match(await page.locator('#modal-footer').textContent(),/Цена из выдачи · не подтверждена/);
   assert.equal(await page.locator('[data-action="refresh-hotel"]').count(),0);
   assert.equal(quoteCount(),before+(flightChoice?2:1));
   await page.locator('#modal-footer').scrollIntoViewIfNeeded();
   await page.screenshot({path:path.join(evidence,`samo-${flightChoice?'flight':'quote'}-recovery-${width}.png`)});
   assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth),false);
   await page.locator('#modal-footer [data-action="all-offers"]').click();await chooseSamo();
   await page.waitForFunction(()=>document.querySelector('#modal-body .error-text')?.textContent.includes('Подтверждение тура не получено'));
   assert.equal(quoteCount(),before+(flightChoice?2:1),'reopening does not call the supplier');
  }
  const failures=await page.evaluate(()=>window.quoteFailures);
  assert.equal(failures.length,2);assert(failures.every(f=>f.httpStatus===502&&f.failureCategory==='supplier_auth'));
  await page.locator('[data-action="close-modal"]').click();transport.state.wideFacets=true;
  let releaseFacetSource;transport.state.samoSearchGate=new Promise(resolve=>releaseFacetSource=resolve);
  await editResultSearch(page,width);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('10 вариантов'));
  await openResultFilters(page,width);
  if(width<=1100){await page.locator('.filter-operator-group>.filter-section-toggle').click();assert.equal(await page.locator('.filter-top h3').textContent(),'Туроператор');assert.equal(await page.locator('#filter-detail-back').evaluate(el=>document.activeElement===el),true);assert.equal(await page.locator('#apply-filters').isVisible(),false,'operator choices return to the complete filter draft before application');}
  const facetEditor=page.locator('[data-facet-search="operators"]'),facetStarts=transport.calls.filter(c=>c.action==='search_start').length;
  await facetEditor.fill('Тестовый');await facetEditor.evaluate(el=>{el.setSelectionRange(3,8);window.activeFacetEditor=el;});
  assert.equal(await page.locator('[data-filter="operators"][value="FUN&SUN"]').count(),0);
  releaseFacetSource();transport.state.samoSearchGate=null;
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('11 вариантов'));
  assert.deepEqual(await facetEditor.evaluate(el=>({same:el===window.activeFacetEditor,focused:document.activeElement===el,start:el.selectionStart,end:el.selectionEnd})),{same:true,focused:true,start:3,end:8});
  await facetEditor.press('End');await page.keyboard.type(' оператор');assert.equal(await facetEditor.inputValue(),'Тестовый оператор');
  assert.match(await page.locator('[data-facet-options="operators"] .facet-search-status').textContent(),/Найдено в списке: 8/);
  await page.screenshot({path:path.join(evidence,`progressive-facet-${width}.png`)});
  if(width<=1100){
   await page.locator('.mobile-close[data-action="close-filters"]').click();await openResultFilters(page,width);
   await page.locator('.filter-operator-group>.filter-section-toggle').click();
   assert.equal(await facetEditor.inputValue(),'','cancelling the drawer discards its transient list query');
   await page.locator('#filter-detail-back').click();assert.equal(await page.locator('.filter-top h3').textContent(),'Фильтры');assert.equal(await page.locator('.filter-operator-group>.filter-section-toggle').evaluate(el=>document.activeElement===el),true);
   const resizeStar=page.locator('#filters [data-action="star"][data-value="5"]'),resizeStarValue=await resizeStar.getAttribute('data-value');await resizeStar.click();
   await page.locator('#max-price').fill('abc');await page.setViewportSize({width:1280,height:900});
   await page.waitForFunction(value=>document.querySelector('#active-filters').textContent.includes(value+' ★'),resizeStarValue);
   assert.match(await page.locator('#active-filters').textContent(),new RegExp(resizeStarValue+' ★'),'widening promotes valid mobile draft choices');
   assert.equal(await page.locator('#filter-panel').evaluate(el=>el.classList.contains('open')),false);assert.equal(await page.locator('#max-price').inputValue(),'abc');assert.equal(await page.locator('#max-price').getAttribute('aria-invalid'),'true');
   assert.equal(await page.locator('[inert]').count(),0);assert.equal(await page.evaluate(()=>document.body.style.overflow),'');assert.equal(await page.locator('#max-price').evaluate(el=>document.activeElement===el),true);
   await page.locator('#max-price').fill('');await page.setViewportSize({width,height:900});await openResultFilters(page,width);
   await page.locator('.filter-operator-group>.filter-section-toggle').click();
  }
  await facetEditor.fill('FUN');await page.locator('[data-filter="operators"][value="FUN&SUN"]').check();
  if(width<=1100){await page.locator('#filter-detail-back').click();await page.locator('#apply-filters').click();}
  assert.match(await page.locator('#results-summary').textContent(),/1 вариант/);
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,facetStarts,'facet editing never starts another search');
  await editResultSearch(page,width);await page.locator('[data-action="departure"]').click();await page.locator('[data-action="choose-departure"][data-value="Казань"]').click();await page.locator('[data-action="apply-departure"]').click();await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>(document.querySelector('#search-status').hidden||!document.querySelector('[data-action="stop-search"]'))&&document.querySelector('#results-summary').textContent.includes('1 вариант'));
  assert.equal(await facetEditor.inputValue(),'');assert.equal(transport.calls.filter(c=>c.action==='search_start').length,facetStarts+1);
  await openResultFilters(page,width);
  const visibleReset=page.locator('#filter-panel [data-action="reset"]:visible');assert.equal(await visibleReset.count(),1,'one reset action is visible in the approved mobile/desktop filter layout');await visibleReset.click();
  if(width<=1100)await page.locator('#apply-filters').click();
  Object.assign(transport.state,{failAnex:false,samoFailure:null,samoFlightChoice:false,wideFacets:false});
  let releaseOfferSource;transport.state.samoSearchGate=new Promise(resolve=>releaseOfferSource=resolve);
  const beforeOfferSearch=transport.calls.filter(c=>c.action==='search_start').length;
  await editResultSearch(page,width);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('2 варианта'));
  await page.locator('[data-action="all-offers"][data-id="501"]').first().click();assert.equal(await page.locator('#offer-count').textContent(),'2 тура');
  if(await page.locator('.offer-filter-disclosure:not([open])').count())await page.locator('.offer-filter-disclosure>summary').click();
  const roomChoice=page.locator('#offer-room');await roomChoice.selectOption('STANDARD SEA VIEW');await roomChoice.focus();
  const offerScroll=await page.locator('#modal-body').evaluate(el=>el.scrollTop);
  releaseOfferSource();transport.state.samoSearchGate=null;
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
  assert.equal(await roomChoice.inputValue(),'STANDARD SEA VIEW');assert(await roomChoice.evaluate(el=>document.activeElement===el));
  assert.equal(await page.locator('#offer-room option[value="SAMO STANDARD"]').count(),1,'late source room becomes selectable without reopening');
  assert.equal(await page.locator('#offer-count').textContent(),'1 тур');assert.equal(await page.locator('#all-offers-list [data-action="offer"]').count(),1,'approved filtered exact row is visible');assert.equal(await page.locator('[data-action="offer-group"]').count(),0,'approved flat rows need no room disclosure');
  assert.equal(await page.locator('#modal-body').evaluate(el=>el.scrollTop),offerScroll);
  await page.screenshot({path:path.join(evidence,`progressive-offer-filter-${width}.png`)});
  await roomChoice.selectOption('');assert.equal(await page.locator('#offer-count').textContent(),'3 тура');
  await page.screenshot({path:path.join(evidence,`progressive-offers-${width}.png`)});
  const lateSamo=page.locator('#modal-body [data-action="offer"][data-key^="andromeda%3A"]');
  await lateSamo.click();
  assert.match(await page.locator('#modal-body').textContent(),/SAMO STANDARD/);
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,beforeOfferSearch+1);
  await page.locator('[data-action="close-modal"]').click();
  transport.state.samoMeal='BB';let releaseHotelSource;transport.state.samoSearchGate=new Promise(resolve=>releaseHotelSource=resolve);
  const beforeHotelSearch=transport.calls.filter(c=>c.action==='search_start').length;
  await editResultSearch(page,width);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('2 варианта'));
  await page.locator('[data-action="hotel-details"][data-id="501"]').first().click();
  assert.equal(await page.locator('#hotel-room-count').textContent(),'Номера: 2 · Туры: 2');
  const hotelRoomSummary=page.locator('.room-overview[data-room="STANDARD SEA VIEW"]>summary');await hotelRoomSummary.click();await hotelRoomSummary.focus();
  const hotelLayoutBefore=await settledHotelScroll(page),hotelScroll=hotelLayoutBefore.scroll;
  await page.evaluate(()=>{window.hotelOverview=document.querySelector('.hotel-detail-photos');});
  releaseHotelSource();transport.state.samoSearchGate=null;
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
  assert.equal(await page.locator('#hotel-room-count').textContent(),'Номера: 3 · Туры: 3');assert.match(await page.locator('#hotel-detail-min').textContent(),/119\s*000/);
  assert(await hotelRoomSummary.evaluate(el=>el.parentElement.open&&document.activeElement===el));
  assert(await page.locator('.hotel-detail-photos').evaluate(el=>el===window.hotelOverview));
  const hotelLayoutAfter=await settledHotelScroll(page);
  fs.writeFileSync(path.join(evidence,`hotel-scroll-layout-${width}.json`),JSON.stringify({before:hotelLayoutBefore,after:hotelLayoutAfter},null,2));
  assert.equal(hotelLayoutAfter.scroll,hotelScroll,'late source preserves settled hotel scroll at width '+width);
  await page.screenshot({path:path.join(evidence,`progressive-hotel-${width}.png`)});
  await page.locator('#hotel-room-meal').selectOption({label:'Завтраки'});
  assert.equal(await page.locator('#hotel-room-count').textContent(),'Номера: 1 · Туры: 1');
  assert.match(await page.locator('#hotel-detail-offers').getAttribute('data-key'),/^andromeda%3A/);
  await page.locator('#hotel-detail-offers').click();assert.match(await page.locator('#modal-body').textContent(),/SAMO STANDARD/);
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,beforeHotelSearch+1);
  await page.locator('[data-action="close-modal"]').click();
  transport.state.samoMeal='BB';let releaseHotelBackSource;transport.state.samoSearchGate=new Promise(resolve=>releaseHotelBackSource=resolve);
  const beforeHotelBackSearch=transport.calls.filter(c=>c.action==='search_start').length;
  await editResultSearch(page,width);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('2 варианта'));
  await page.locator('[data-action="hotel-details"][data-id="501"]').first().click();
  const backRoom=page.locator('.room-overview[data-room="STANDARD SEA VIEW"]');await backRoom.locator(':scope>summary').click();
  const backOffer=backRoom.locator('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');await backOffer.scrollIntoViewIfNeeded();await backOffer.focus();
  const hotelBackPosition=await backOffer.evaluate(el=>({scroll:el.closest('#modal-body').scrollTop,top:el.getBoundingClientRect().top-el.closest('#modal-body').getBoundingClientRect().top}));await backOffer.click();await continueToFlights(page);
  await page.waitForFunction(()=>document.querySelector('[data-action="confirm-tour"]')&&!document.querySelector('[data-action="confirm-tour"]').disabled);
  releaseHotelBackSource();transport.state.samoSearchGate=null;await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
  await page.locator('#modal-back').click();
  assert.equal(await page.locator('#hotel-room-count').textContent(),'Номера: 3 · Туры: 3','Back renders current progressive rooms, not the stored snapshot');
  assert.match(await page.locator('#hotel-detail-min').textContent(),/119\s*000/);assert.equal(await page.locator('#hotel-room-meal').count(),1);
  assert(await backRoom.evaluate(el=>el.open));assert(await backOffer.evaluate(el=>document.activeElement===el));
  const hotelBackReturned=await backOffer.evaluate(el=>({scroll:el.closest('#modal-body').scrollTop,top:el.getBoundingClientRect().top-el.closest('#modal-body').getBoundingClientRect().top}));
  assert(Math.abs(hotelBackReturned.top-hotelBackPosition.top)<2,'late rooms keep the returning offer at its viewport position');assert(hotelBackReturned.scroll>=hotelBackPosition.scroll);
  await page.screenshot({path:path.join(evidence,`progressive-hotel-back-${width}.png`)});
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,beforeHotelBackSearch+1);
  await page.locator('[data-action="close-modal"]').click();transport.state.wideFacets=true;
  const beforeMoreBackSearch=transport.calls.filter(c=>c.action==='search_start').length;
  await editResultSearch(page,width);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('11 вариантов'));
  await page.locator('[data-action="hotel-details"][data-id="501"]').first().click();
  const manyRoom=page.locator('.room-overview[data-room="STANDARD SEA VIEW"]');await manyRoom.locator(':scope>summary').click();
  const moreTours=manyRoom.locator('.hotel-room-more');await moreTours.locator(':scope>summary').click();
  const nestedOffer=moreTours.locator('[data-action="offer"][data-key="tourvisor%3Aoperator-tour-1"]');await nestedOffer.scrollIntoViewIfNeeded();await nestedOffer.focus();
  const moreBackScroll=await page.locator('#modal-body').evaluate(el=>el.scrollTop);await nestedOffer.click();await page.locator('[data-action="start-tour-flights"]').click();
  await page.waitForFunction(()=>document.querySelector('#modal-body').textContent.includes('Не удалось подтвердить цену'));await page.locator('#modal-back').click();
  assert(await manyRoom.evaluate(el=>el.open));assert(await moreTours.evaluate(el=>el.open),'Back retains the expanded nested tour list');
  assert(await nestedOffer.evaluate(el=>document.activeElement===el));assert.equal(await page.locator('#modal-body').evaluate(el=>el.scrollTop),moreBackScroll);
  await page.screenshot({path:path.join(evidence,`hotel-more-back-${width}.png`)});
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,beforeMoreBackSearch+1);
  await page.locator('[data-action="close-modal"]').click();
  await page.locator('[data-action="hotel-details"][data-id="501"]').first().click();
  const historyRoom=page.locator('.room-overview[data-room="STANDARD SEA VIEW"]');await historyRoom.locator(':scope>summary').click();
  const historyMore=historyRoom.locator('.hotel-room-more');await historyMore.locator(':scope>summary').click();
  await page.waitForFunction(()=>history.state?.['anytour.prototype.v18.ui.v1']?.more?.includes('STANDARD SEA VIEW'));
  const historyPosition=await historyMore.locator(':scope>summary').evaluate(el=>({scroll:el.closest('#modal-body').scrollTop,top:el.getBoundingClientRect().top-el.closest('#modal-body').getBoundingClientRect().top}));
  await page.evaluate(()=>history.back());await page.waitForFunction(()=>!document.querySelector('#modal').open);
  await page.evaluate(()=>history.forward());await page.waitForFunction(()=>document.querySelector('#modal').open);
  const restoredMore=page.locator('.room-overview[data-room="STANDARD SEA VIEW"] .hotel-room-more');assert(await restoredMore.evaluate(el=>el.open),'browser Forward restores the expanded nested tour list');
  const restoredPosition=await restoredMore.locator(':scope>summary').evaluate(el=>({scroll:el.closest('#modal-body').scrollTop,top:el.getBoundingClientRect().top-el.closest('#modal-body').getBoundingClientRect().top}));
  assert(Math.abs(restoredPosition.top-historyPosition.top)<2,'browser Forward restores the hotel disclosure viewport position');assert.equal(restoredPosition.scroll,historyPosition.scroll);
  await page.screenshot({path:path.join(evidence,`hotel-more-forward-${width}.png`)});
  await page.locator('[data-action="close-modal"]').click();transport.state.samoMeal='AI';transport.state.samoRoom='STANDARD SEA VIEW';
  let releaseMovedOfferSource;transport.state.samoSearchGate=new Promise(resolve=>releaseMovedOfferSource=resolve);
  const beforeMovedOfferSearch=transport.calls.filter(c=>c.action==='search_start').length;
  await editResultSearch(page,width);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('10 вариантов'));
  await page.locator('[data-action="hotel-details"][data-id="501"]').first().click();
  const movingRoom=page.locator('.room-overview[data-room="STANDARD SEA VIEW"]');await movingRoom.locator(':scope>summary').click();
  const movingOffer=movingRoom.locator('[data-action="offer"][data-key="tourvisor%3Aoperator-tour-0"]');
  assert.equal(await movingOffer.evaluate(el=>Boolean(el.closest('.hotel-room-more'))),false,'chosen offer starts in the visible first two');
  await movingOffer.scrollIntoViewIfNeeded();await movingOffer.focus();const movedOfferPosition=await movingOffer.evaluate(el=>({scroll:el.closest('#modal-body').scrollTop,top:el.getBoundingClientRect().top-el.closest('#modal-body').getBoundingClientRect().top}));await movingOffer.click();await page.locator('[data-action="start-tour-flights"]').click();
  await page.waitForFunction(()=>/Не удалось подтвердить цену|Условия поиска изменились/.test(document.querySelector('#modal-body').textContent));
  releaseMovedOfferSource();transport.state.samoSearchGate=null;await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('11 вариантов'));
  await page.locator('#modal-back').click();
  const movedOffer=page.locator('[data-action="offer"][data-key="tourvisor%3Aoperator-tour-0"]'),movedMore=movedOffer.locator('xpath=ancestor::details[contains(@class,"hotel-room-more")]');
  assert.equal(await movedMore.count(),1,'late cheaper offer moves the exact returning choice below the first two');
  assert(await movedMore.evaluate(el=>el.open),'Back reveals the exact returning offer after its position changes');
  assert(await movedOffer.evaluate(el=>document.activeElement===el));assert(await movedOffer.isVisible());
  const returnedPosition=await movedOffer.evaluate(el=>({scroll:el.closest('#modal-body').scrollTop,top:el.getBoundingClientRect().top-el.closest('#modal-body').getBoundingClientRect().top}));
  assert(Math.abs(returnedPosition.top-movedOfferPosition.top)<2,'Back preserves the chosen offer viewport position after reordering');assert(returnedPosition.scroll>=movedOfferPosition.scroll);
  await page.screenshot({path:path.join(evidence,`hotel-moved-offer-back-${width}.png`)});
  assert.equal(transport.calls.filter(c=>c.action==='search_start').length,beforeMovedOfferSearch+1);
  await page.locator('[data-action="close-modal"]').click();transport.state.samoRoom='SAMO STANDARD';
  // A fresh isolated fixture search exercises the actual compiled sole-pair path.
  transport.state.wideFacets=false;transport.state.anexCurrentAdditional=true;transport.state.anexPackageChoiceCount=1;
  anexQuotePending=new Promise(resolve=>markAnexQuotePending=resolve);
  const beforeSoleStart=transport.calls.filter(c=>c.action==='quote_start').length,beforeSoleCalc=transport.calls.filter(c=>c.action==='quote_calculate').length;
  await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));
  await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
  await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#modal-body [data-action="offer"][data-key^="anex%3A"]').click();
  await page.locator('[data-action="refresh-hotel"]').click();await page.waitForFunction(()=>document.querySelector('#modal-body').textContent.includes('ANEX CONCRETE'));
  await page.locator('[data-action="select-anex-tour"]').click();await anexQuotePending;assert.equal(await page.locator('[data-action="anex-package-quote"]').count(),0,'primary entry skips the context-only screen');
  assert.equal(transport.calls.filter(c=>c.action==='quote_calculate').length,beforeSoleCalc+1,'unique supplier pair calculates without another click');
  assert.equal(await page.locator('[name="anex-package-choice"]').count(),1);
  assert.equal(await page.locator('[data-action="anex-package-calculate"]').isDisabled(),true);
  assert.equal(await page.locator('[data-action="anex-application-preview"]').count(),0);
  assert.match(await page.locator('#anex-package-status').textContent(),/Уточняем полную цену/);
  assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth+1),false);
  await page.screenshot({path:path.join(evidence,`anex-sole-pair-pending-${width}.png`)});
  releaseAnexQuote();await page.locator('[data-action="anex-application-preview"]').waitFor();
  assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/135678,9/);
  assert.match(await page.locator('#modal-body').textContent(),/TEST ANEX PACKAGE 1 OUT/);
  assert.equal(transport.calls.filter(c=>c.action==='quote_start').length,beforeSoleStart+1);
  await page.screenshot({path:path.join(evidence,`anex-sole-pair-verified-${width}.png`)});
  await page.locator('[data-action="anex-application-preview"]').click();await contactLayout(page,width,'ANEX');
  assert.equal(await page.locator('[name="consent"]').isChecked(),false);assert.match(await page.locator('.application-choice').textContent(),/ANEX CONCRETE/);
  await page.screenshot({path:path.join(evidence,`anex-direct-application-${width}.png`)});
  await page.locator('#modal-back').click();assert.equal(transport.calls.filter(c=>c.action==='quote_calculate').length,beforeSoleCalc+1,'application Back never recalculates');
  await page.locator('[data-action="close-modal"]').click();
  // Exactly one SAMO option per direction continues only the explicit quote.
  transport.state.samoFailure=null;transport.state.samoFlightChoice=true;transport.state.samoSolePair=true;
  samoPairGate=new Promise(resolve=>releaseSamoPair=resolve);const samoPairPending=new Promise(resolve=>markSamoPairPending=resolve);
  const beforeSamoPair=transport.calls.filter(c=>c.action==='quote_select_flights').length;
  await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));
  await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);await page.locator('.search-submit').click();
  await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
  await page.locator('[data-action="all-offers"][data-id="501"]').first().click();await page.locator('#modal-body [data-action="offer"][data-key^="andromeda%3A"]').click();
  await page.locator('[data-action="refresh-hotel"]').click();await samoPairPending;
  assert.equal(transport.calls.filter(c=>c.action==='quote_select_flights').length,beforeSamoPair+1,'sole SAMO pair needs no extra confirmation click');
  assert.equal(await page.locator('[data-action="apply-andromeda-flights"]').isDisabled(),true);assert.equal(await page.locator('[data-action="andromeda-application-preview"]').count(),0);
  await page.screenshot({path:path.join(evidence,`samo-sole-pair-pending-${width}.png`)});
  releaseSamoPair();await page.locator('[data-action="andromeda-application-preview"]').waitFor();
  assert.match((await page.locator('#modal-footer').textContent()).replace(/\s/g,''),/125500/);assert.match(await page.locator('.quote-price-change').textContent(),/Цена изменилась/);
  await page.screenshot({path:path.join(evidence,`samo-sole-pair-verified-${width}.png`)});
  await page.locator('[data-action="andromeda-application-preview"]').click();await contactLayout(page,width,'SAMO');
  assert.match(await page.locator('.application-choice').textContent(),/SAMO STANDARD/);assert.equal(await page.locator('[name="consent"]').isChecked(),false);
  await page.screenshot({path:path.join(evidence,`samo-sole-pair-application-${width}.png`)});
  await page.locator('#modal-back').click();assert.equal(transport.calls.filter(c=>c.action==='quote_select_flights').length,beforeSamoPair+1);
  await hotelPickerBlock(page,width,transport,origin,base,evidence);
  assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
  receipts.push({width,three_sources_one_hotel:true,progressive_hotel_rooms:true,progressive_hotel_meal:true,progressive_hotel_back:true,hotel_more_back:true,hotel_more_forward:true,hotel_moved_offer_back:true,progressive_offer_list:true,progressive_offer_filter_preserved:true,calendar_database_observation:true,search_before_submit:0,total:133500.5,tv_fuel_disclosed:20686,tv_unknown_fuel_preserved:true,tv_explicit_zero_fuel_preserved:true,samo_total:125500,samo_terminal_recovery:true,samo_no_replay:true,departure_recovery_no_search:true,departure_calendar_context:true,provider_return_no_replay:true,tv_chosen_flight_retained:true,tv_reopen_no_replay:true,tv_new_search_invalidation:true,contact_draft_retained:true,anex_estimate_retained:true,local_application:true,progressive_facet_focus:true,late_facet_choice:true,mobile_facet_cancel_query_reset:width<=1100,mobile_filter_resize_state:width<=1100,initial_invalid_budget_blocked:initialInvalidBudgetBlocked,facet_query_scope_reset:true,supplier_requests:0,lead_requests:0});await context.close();
 }
 await chosenDepartureJourney(browser,origin,base,evidence);
 // Each journey owns its context, fictional transport and evidence names. Keep
 // both complete matrices; settle both before closing the shared browser.
 const isolated=await Promise.allSettled([
  quoteRetryAndRoomReturnJourney(browser,origin,base,evidence),
  verifiedPairJourney(browser,origin,base,evidence)
 ]),failures=isolated.filter(result=>result.status==='rejected');
 if(failures.length)throw new AggregateError(failures.map(result=>result.reason),'Independent compiled journeys failed');
 await boundedRepriceJourney(browser,origin,base,evidence);
 await require('./search3-visual-selected-session.cjs')({browser,origin,base,evidence});
 await require('./search3-visual-catalog-recovery.cjs')({browser,origin,base,evidence});
 await multiHotelReload(browser,origin,base,evidence);
 await require('./search3-visual-initial-loading.cjs')({browser,origin,base,evidence});
   })(),
   (async()=>{await require('./search3-visual-large-flight-choices.cjs')();})()
  ]),rejected=completed.filter(result=>result.status==='rejected');
  if(rejected.length)throw new AggregateError(rejected.map(result=>result.reason),'Complete independent compiled matrices failed');
 }finally{await browser.close();server.close();}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify({published:false,live_data:false,engine:'Chromium',physical_device:false,results:receipts},null,2));console.log('PASS visual live browser',JSON.stringify(receipts));
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
