'use strict';
// CI browser acceptance. Every data request is intercepted with fictional fixtures.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const {fixture,trip,hotelCatalogue}=require('./search3-visual-live-fixture.cjs');
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
 fs.writeFileSync(path.join(evidence,`hotel-picker-block-${width}.json`),JSON.stringify({width,available_photos:true,missing_and_failed_photo_fallback:true,typed_country_switch:true,cached_country_scope:true,own_hotel_id:2002,verified_legacy_id:7002,trip_preserved:true,explicit_searches:1,simulated_keyboard:width<=760,physicalSafari:false,supplier_HTTP:0,real_leads:0},null,2));
};
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
 res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':'application/octet-stream');res.end(fs.readFileSync(file));
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port,browser=await chromium.launch();const receipts=[];
 try{for(const width of [390,768,1280]){
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
  const resultRangeTo=new Date(Date.parse(trip.from+'T12:00:00Z')+6*86400000).toISOString().slice(0,10);
  await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,to:resultRangeTo,ages:'',searched:'1',stars:'4'}));
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
  await chosenDepartureContext(page,width,transport,evidence);
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
  await contactLayout(page,width,'SAMO');
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
  await contactLayout(page,width,'ANEX');
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
 await multiHotelReload(browser,origin,base,evidence);
 await require('./search3-visual-initial-loading.cjs')({browser,origin,base,evidence});
 }finally{await browser.close();server.close();}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify({published:false,live_data:false,engine:'Chromium',physical_device:false,results:receipts},null,2));console.log('PASS visual live browser',JSON.stringify(receipts));
 await require('./search3-visual-large-flight-choices.cjs')();
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
