'use strict';
// Target visual-search, fictional HTTP only. Empty APD must not end flight reads.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM,VirtualConsole}=require('jsdom');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
const root=path.resolve(__dirname,'../v2'),source=n=>fs.readFileSync(path.join(root,n),'utf8');
const scripts=[...source('visual-search/index.php').match(/\$scripts = \[([\s\S]*?)\];/)[1].matchAll(/'([^']+\.js)'/g)].map(m=>path.posix.normalize('visual-search/'+m[1]));
// Transport fixtures install the presentation owner; cold loading has its own probe.
scripts.splice(scripts.indexOf('visual-search/app.js'),0,'visual-search/offer-list-v1.js','visual-search/hotel-details-v1.js');
async function expansionEntryScenario(count,late=false){
 const transport=fixture(),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
 const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
 const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
 Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
 w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
 w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
 w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
 let releaseExpansion;
 w.fetch=async(url,options={})=>{
  const value=await transport.json(url,options);
  if(value.data?.status==='expanded'){
   const tours=value.data.hotels[0].tours;
   if(count===0)tours.length=0;
   if(count===2)tours.push({...tours[0],room:'ANEX ALTERNATIVE',offer_ref:'anex_online:'+'2'.repeat(64)});
   if(late)await new Promise(resolve=>releaseExpansion=resolve);
  }
  return new Response(JSON.stringify(value),{status:200,headers:{'Content-Type':'application/json'}});
 };
 const settle=()=>new Promise(resolve=>setTimeout(resolve,150));
 const wait=async fn=>{for(let i=0;i<80;i++){if(fn())return;await new Promise(resolve=>setTimeout(resolve,50));}assert.fail('Expansion entry timeout: '+d.body.textContent.slice(-2000));};
 try{
  for(const file of scripts)w.eval(source(file));
  await wait(()=>!q('.search-submit').disabled);click('.search-submit');
  await wait(()=>q('#results-summary').textContent.includes('3 варианта')&&q('#search-status').hidden);
  click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="anex%3A"]');click('[data-action="refresh-hotel"]');
  if(late){
   await wait(()=>releaseExpansion);
   click('[data-action="close-modal"]');await settle();
   click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');
   const current=q('#modal-body').innerHTML;releaseExpansion();await settle();
   assert.equal(q('#modal-body').innerHTML,current,'late single-offer expansion cannot replace another selected tour');
   click('#modal-back');await settle();
   assert.match(q('#modal-body').textContent,/ANEX STANDARD/,'late expansion does not replace the original group inventory');
   assert.doesNotMatch(q('#modal-body').textContent,/ANEX CONCRETE/);
  }else if(count===0){
   await wait(()=>q('#modal-body .error-text')?.textContent.includes('не вернул конкретные'));
   assert.equal(q('#modal-title').textContent,'Ваш тур в деталях','empty supplier expansion keeps existing recovery');
   assert.equal(q('#all-offers-list'),null);assert(q('[data-action="refresh-hotel"]'));
   click('#modal-back');await settle();assert.match(q('#modal-body').textContent,/ANEX STANDARD/);
  }else if(count===2){
   await wait(()=>q('#all-offers-list')&&q('#modal-body').textContent.includes('ANEX ALTERNATIVE'));
   assert.equal(d.querySelectorAll('#all-offers-list [data-action="offer"][data-key^="anex%3A"]').length,2,'multiple exact offers retain explicit choice');
   assert.match(q('#modal-body').textContent,/ANEX CONCRETE/);
  }else{
   await wait(()=>q('#modal-body').textContent.includes('ANEX CONCRETE'));
   assert.equal(q('#all-offers-list'),null);assert(q('[data-action="refresh-hotel"]'));
   const calls=transport.calls.length;
   click('#modal-back');await settle();
   assert(q('#all-offers-list'),'Back skips the obsolete group detail and returns to the original passive list');
   assert.match(q('#modal-body').textContent,/ANEX CONCRETE/);
   click('[data-action="offer"][data-key^="anex%3A"]');await settle();
   click('[data-action="close-modal"]');await settle();w.history.forward();await settle();
   assert.equal(q('#modal').open,true);assert.equal(q('#all-offers-list'),null);
   assert.match(q('#modal-body').textContent,/ANEX CONCRETE/);
   assert.equal(transport.calls.length,calls,'Back, reopen and Forward never re-expand or verify the direct selection');
  }
  assert.equal(transport.calls.filter(c=>c.action==='expand').length,1);
  assert.equal(transport.calls.filter(c=>c.action==='offer').length,0,'expansion entry never verifies the exact offer implicitly');
  assert.equal(transport.calls.filter(c=>['quote_start','quote_calculate','additional_prices'].includes(c.action)).length,0);
  assert.deepEqual(errors,[]);
 }finally{releaseExpansion?.();await settle();dom.window.close();}
}
async function solePackagePriceScenario(mode,direct=false){
 const transport=fixture(),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
 transport.state.anexPackageChoiceCount=1;transport.state.anexCurrentAdditional=true;transport.state.anexQuoteFailure=mode==='failed';
 const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
 const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
 Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
 w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
 w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
 w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
 let releaseStart,releaseCalc;
 w.fetch=async(url,options={})=>{
  const value=await transport.json(url,options),action=options.body&&JSON.parse(options.body).action;
  if(action==='quote_start'&&mode==='late-start')await new Promise(resolve=>releaseStart=resolve);
  if(action==='quote_calculate')await new Promise(resolve=>releaseCalc=resolve);
  return new Response(JSON.stringify(value),{status:200,headers:{'Content-Type':'application/json'}});
 };
 const settle=()=>new Promise(resolve=>setTimeout(resolve,150));
 const wait=async fn=>{for(let i=0;i<80;i++){if(fn())return;await new Promise(resolve=>setTimeout(resolve,50));}assert.fail('Sole price timeout: '+d.body.textContent.slice(-2000));};
 const count=action=>transport.calls.filter(c=>c.action===action).length;
 const reopen=async()=>{click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="anex%3A"]');};
 try{
  for(const file of scripts)w.eval(source(file));
  await wait(()=>!q('.search-submit').disabled);click('.search-submit');
  await wait(()=>q('#results-summary').textContent.includes('3 варианта')&&q('#search-status').hidden);
  await reopen();click('[data-action="refresh-hotel"]');await wait(()=>q('#modal-body').textContent.includes('ANEX CONCRETE'));
  if(direct){click('[data-action="select-anex-tour"]');assert.equal(q('[data-action="anex-package-quote"]'),null,'primary path skips context-only screen');}else{click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="anex-package-quote"]'));click('[data-action="anex-package-quote"]');}
  if(mode==='late-start'){
   await wait(()=>releaseStart);click('[data-action="close-modal"]');await settle();await reopen();
   releaseStart();await wait(()=>q('[name="anex-package-choice"]'));
   assert.equal(count('quote_calculate'),0,'passive reopen cannot turn a late inventory response into a calculation');
   click('[data-action="anex-package-calculate"]');
  }
  await wait(()=>releaseCalc);
  assert.equal(count('quote_start'),1);assert.equal(count('quote_calculate'),1,'the explicit actualization continues the unique supplier pair once');
  assert.equal(transport.calls.find(c=>c.action==='quote_calculate').body.choice_ref,'anex_quote:'+'1'.repeat(64));
  assert.match(q('#anex-package-status').textContent,/Уточняем полную цену/);
  assert(q('[name="anex-package-choice"]').disabled);assert(q('[data-action="anex-package-calculate"]').disabled);
  assert.equal(q('[data-action="anex-application-preview"]'),null,'a pending price cannot enter application');
  click('[data-action="anex-package-calculate"]');assert.equal(count('quote_calculate'),1,'duplicate pending click is inert');
  if(mode==='late-calc'){
   click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');
   const body=q('#modal-body').innerHTML;releaseCalc();await settle();assert.equal(q('#modal-body').innerHTML,body,'late price cannot replace another selected tour');
   click('[data-action="close-modal"]');await settle();await reopen();
  }else{
   click('[data-action="close-modal"]');await settle();await reopen();
   assert(q('[data-action="anex-package-calculate"]').disabled,'pending reopen keeps the same reservation');
   click('[data-action="close-modal"]');await settle();w.history.forward();await settle();
   assert.equal(count('quote_calculate'),1,'Back/reopen/Forward never repeats calculation');
   releaseCalc();
  }
  if(mode==='failed'){
   await wait(()=>q('#anex-package-status')?.getAttribute('role')==='alert');
   assert.equal(q('[data-action="anex-application-preview"]'),null);assert.equal(q('[data-action="anex-package-calculate"]'),null);
   click('[data-action="close-modal"]');await settle();await reopen();assert.equal(count('quote_calculate'),1,'failure stays consumed on reopen');
  }else{
   await wait(()=>q('[data-action="anex-application-preview"]'));
   assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/135678,9/);
   const before=transport.calls.length;click('[data-action="anex-application-preview"]');assert(q('#prototype-lead-form'));
   assert.match(q('#modal-body').textContent,/TEST ANEX PACKAGE 1 OUT/);assert.equal(transport.calls.length,before);
   assert.equal(q('#modal-body [name="consent"]').checked,false);
  }
  assert.equal(count('quote_start'),1);assert.equal(count('quote_calculate'),1);assert.deepEqual(errors,[]);
 }finally{releaseStart?.();releaseCalc?.();await settle();dom.window.close();}
}
async function soleSamoPriceScenario(mode){
 const transport=fixture(),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
 transport.state.samoFlightChoice=true;transport.state.samoFailure=mode==='failed'?'supplier_auth':null;
 const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
 const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
 Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
 w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
 w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
 w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
 let releaseStart,releaseCalc;
 w.fetch=async(url,options={})=>{
  const value=await transport.json(url,options),action=options.body&&JSON.parse(options.body).action;
  if(String(url).includes('api-andromeda-quote')&&action==='quote'&&mode==='late-start')await new Promise(resolve=>releaseStart=resolve);
  if(action==='quote_select_flights')await new Promise(resolve=>releaseCalc=resolve);
  return new Response(JSON.stringify(value),{status:value.ok===false?502:200,headers:{'Content-Type':'application/json'}});
 };
 const settle=()=>new Promise(resolve=>setTimeout(resolve,150));
 const wait=async fn=>{for(let i=0;i<80;i++){if(fn())return;await new Promise(resolve=>setTimeout(resolve,50));}assert.fail('Sole SAMO price timeout: '+d.body.textContent.slice(-1500));};
 const count=action=>transport.calls.filter(c=>c.action===action&&c.url.includes('api-andromeda-quote')).length;
 const reopen=async()=>{click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="andromeda%3A"]');};
 try{
  for(const file of scripts)w.eval(source(file));
  await wait(()=>!q('.search-submit').disabled);click('.search-submit');await wait(()=>q('#results-summary').textContent.includes('3 варианта')&&q('#search-status').hidden);
  await reopen();click('[data-action="refresh-hotel"]');
  if(mode==='late-start'){
   await wait(()=>releaseStart);click('[data-action="close-modal"]');await settle();await reopen();releaseStart();
   await wait(()=>q('[data-action="apply-andromeda-flights"]'));
   assert.equal(count('quote_select_flights'),0,'passive reopen never calculates a newly received sole pair');
   click('[data-action="apply-andromeda-flights"]');
  }
  await wait(()=>releaseCalc);
  assert.equal(count('quote'),1);assert.equal(count('quote_select_flights'),1,'one explicit verification confirms the sole pair once');
  const request=transport.calls.find(c=>c.action==='quote_select_flights').body;
  assert.equal(request.flight_selection.outbound_ref,'flight_'+'1'.repeat(32));assert.equal(request.flight_selection.return_ref,'flight_'+'2'.repeat(32));
  assert(q('[data-action="apply-andromeda-flights"]').disabled);assert(q('[name="andromeda-outbound"]').disabled);
  assert.match(q('#andromeda-flight-price-status').textContent,/Уточняем полную цену/);assert.equal(q('#prototype-lead-form'),null);
  click('[data-action="apply-andromeda-flights"]');assert.equal(count('quote_select_flights'),1);
  if(mode==='late-calc'){
   click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');
   const body=q('#modal-body').innerHTML;releaseCalc();await settle();assert.equal(q('#modal-body').innerHTML,body,'late total cannot replace another selected tour');
   click('[data-action="close-modal"]');await settle();await reopen();
  }else releaseCalc();
  if(mode==='failed'){
   await wait(()=>q('#modal-body .error-text')?.textContent.includes('Подтверждение тура не получено'));
   assert.equal(q('[data-action="andromeda-application-preview"]'),null);assert.equal(q('[data-action="apply-andromeda-flights"]'),null);
   click('[data-action="close-modal"]');await settle();await reopen();await settle();assert.equal(count('quote_select_flights'),1,'failed sole continuation cannot replay');
  }else{
   await wait(()=>q('[data-action="andromeda-application-preview"]'));
   assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/125500/);assert(q('.chosen-stay'));
   assert.match(q('.quote-price-change').textContent.replace(/\s/g,''),/119000.*125500/,'listing versus exact verified total is visible');
   const before=transport.calls.length;click('[data-action="andromeda-application-preview"]');assert(q('#prototype-lead-form'));assert(q('.application-layout'));
   assert.match(q('.application-choice').textContent,/SAMO STANDARD/);assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/125500/);
   assert.equal(q('[name="consent"]').checked,false);click('#modal-back');await settle();assert.equal(transport.calls.length,before,'application Back retains the confirmed receipt');
  }
  assert.equal(count('quote'),1);assert.equal(count('quote_select_flights'),1);assert.deepEqual(errors,[]);
 }finally{releaseStart?.();releaseCalc?.();await settle();dom.window.close();}
}
(async()=>{
 for(const mode of ['verified','failed','late-start','late-calc'])await soleSamoPriceScenario(mode);
 console.log('VISUAL_SAMO_SOLE_PAIR_JOURNEY_OK verified/failure/late inventory/late total/duplicate/application Back; supplier HTTP 0');
 for(const mode of ['verified','failed','late-start','late-calc']){await solePackagePriceScenario(mode);await solePackagePriceScenario(mode,true);}
 console.log('VISUAL_ANEX_SOLE_PAIR_PRICE_OK verified/failed/late inventory/late price/history/duplicate; supplier calls 0');
 for(const count of [0,1,2])await expansionEntryScenario(count);
 await expansionEntryScenario(1,true);
 console.log('VISUAL_ANEX_EXPANSION_ENTRY_OK single/multiple/empty/history/late response; supplier calls 0');
 for(const zero of [false,true]){
  const transport=fixture({anexZeroSurcharge:zero}),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
  transport.state.anexCurrentAdditional=true;
  const weekEnd=new Date(Date.parse(trip.from+'T12:00:00Z')+6*86400000).toISOString().slice(0,10);
  const siblingDay=new Date(Date.parse(trip.from+'T12:00:00Z')+86400000).toISOString().slice(0,10);
  const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,to:weekEnd,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
  const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
  Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
  w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
  w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
  w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
  w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
  w.fetch=async(url,options={})=>{
   const value=await transport.json(url,options);
   if(value.data?.provider==='anex'&&value.data.hotels){
    for(const hotel of value.data.hotels)for(const tour of hotel.tours)tour.meal=zero?'RO':'AI';
    if(value.data.status==='expanded')value.data.hotels[0].tours.push({...value.data.hotels[0].tours[0],
     checkin:siblingDay,offer_ref:'anex_online:'+'3'.repeat(64),room:'ANEX OTHER DAY'});
   }
   return new Response(JSON.stringify(value),{status:200,headers:{'Content-Type':'application/json'}});
  };
  const wait=async(fn)=>{for(let i=0;i<80;i++){if(fn())return;await new Promise(r=>setTimeout(r,50));}assert.fail('Timeout: '+d.body.textContent.slice(-2000));};
  try{
   for(const file of scripts)w.eval(source(file));
   await wait(()=>!q('.search-submit').disabled);click('.search-submit');
   // Expansion preserves the original first search; finish its status/results
   // reads before asserting that later history navigation makes no requests.
   await wait(()=>q('#results-summary').textContent.includes('3 варианта')&&q('#search-status').hidden);
   click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="anex%3A"]');click('[data-action="refresh-hotel"]');
   await wait(()=>q('#modal-body').textContent.includes('ANEX CONCRETE'));
   assert.doesNotMatch(q('#modal-body').textContent,/ANEX OTHER DAY/,'retained window expansion preserves the selected day');
   if(zero)assert.match(q('#modal-body').textContent,/Без питания/,'native RO does not require a Tourvisor catalogue alias');
   assert.equal(transport.calls.filter(c=>c.action==='search').length,1);
   assert.equal(transport.calls.filter(c=>c.action==='expand').length,1);
   assert.equal(q('#modal-title').textContent,'Ваш тур в деталях','one concrete offer bypasses the list');assert.equal(q('#all-offers-list'),null);
   assert.equal(transport.calls.filter(c=>c.action==='offer').length,0,'direct selection spends no concrete verification request');
   click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="anex-application-preview"]'));
   assert.match(q('#modal-body').textContent,/не финально подтверждённая/);
   assert.equal(q('[data-action="anex-additional-prices"]'),null,'retained APD requires no repeated supplier request');
   click('[data-action="anex-application-preview"]');
   const form=q('#prototype-lead-form');assert(form);assert.match(q('#modal-footer').textContent,zero?/121\s*000/:/123\s*000/);
   assert.match(q('#modal-body').textContent,/ANEX CONCRETE/);assert.match(q('#modal-body').textContent,/Расчётная стоимость требует подтверждения оператором/);
   form.elements.phone.value='+79990000000';form.elements.consent.checked=true;
   form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
   assert.equal(form.dataset.checked,'1');assert.match(q('.lead-message').textContent,/Заявка не отправлена/);
   form.elements.phone.dispatchEvent(new w.Event('input',{bubbles:true}));q('#modal-body').scrollTop=73;
   const beforeForward=transport.calls.length;
   click('[data-action="close-modal"]');await new Promise(r=>setTimeout(r,150));w.history.forward();await new Promise(r=>setTimeout(r,150));
   assert.equal(q('#modal').open,true,'browser Forward restores the current ANEX application');
   assert.equal(q('#prototype-lead-form').elements.phone.value,'+79990000000');
   assert.equal(q('#prototype-lead-form').elements.consent.checked,false);assert.equal(q('#prototype-lead-form').dataset.checked,undefined);
   assert.equal(q('#modal-body').scrollTop,73);assert.match(q('#modal-footer').textContent,zero?/121\s*000/:/123\s*000/);
   assert.equal(transport.calls.length,beforeForward,'browser Forward never repeats current/APD/flights or submits a lead');
   const before=transport.calls.length;
   click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="anex%3A"]');click('[data-action="anex-application-preview"]');
   assert.equal(q('#prototype-lead-form').elements.consent.checked,false);assert.equal(transport.calls.length,before,'return uses the same retained exact offer');
   assert.equal(transport.calls.filter(c=>c.action==='additional_prices').length,0);assert.equal(transport.calls.filter(c=>c.action==='offer').length,1);
   await new Promise(r=>setTimeout(r,50));
   const historyKey='anytour.prototype.v18.ui.v1',route=w.history.state[historyKey];
   assert.deepEqual(Object.keys(route).sort(),['key','scroll','type'],'history does not persist price, supplier receipt, contacts or consent');
   click('[data-action="close-modal"]');await new Promise(r=>setTimeout(r,150));
   const restore=async value=>{w.history.replaceState({...w.history.state,[historyKey]:value},'',w.location.href);w.dispatchEvent(new w.PopStateEvent('popstate',{state:w.history.state}));await new Promise(r=>setTimeout(r,150));};
   await restore({...route,key:'tourvisor%3Avisual-tv-101'});assert(!q('#modal').open,'wrong-provider history cannot open an ANEX application');
   assert.equal(transport.calls.length,before,'invalid history never performs a provider request');
   click('#applied-search [data-action="edit-search"]');click('.search-submit');await wait(()=>q('[data-action="all-offers"]'));
   const quoteCalls=()=>transport.calls.filter(c=>['quote','quote_select_flights','offer','additional_prices','flights'].includes(c.action)).length,afterSearch=quoteCalls();
   await restore(route);assert(!q('#modal').open,'a new search invalidates the old application route');
   assert.equal(quoteCalls(),afterSearch,'stale history cannot re-quote the previous offer');
   assert.deepEqual(errors,[]);
  }finally{await new Promise(r=>setTimeout(r,50));dom.window.close();}
 }
 console.log('VISUAL_ANEX_RETAINED_ESTIMATE_APPLICATION_OK positive/zero/reopen; supplier calls 0');
 for(const failed of [false,true]){
  const transport=fixture({anexEmptyAdditional:true}),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
  const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
  const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
  Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
  w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
  w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
  w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
  w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
  let releaseFlights;
  w.fetch=async(url,options={})=>{
   const value=await transport.json(url,options);
   if(value.data?.status==='flights'){
    for(const route of value.data.flights.routes)route.date=route.date.replaceAll('-','');
    await new Promise(resolve=>releaseFlights=resolve);if(failed)return new Response(JSON.stringify({ok:false}),{status:502});
   }
   return new Response(JSON.stringify(value),{status:200,headers:{'Content-Type':'application/json'}});
  };
  const wait=async(fn)=>{for(let i=0;i<80;i++){if(fn())return;await new Promise(r=>setTimeout(r,50));}assert.fail('Timeout: '+d.body.textContent.slice(-2500));};
  try{
   for(const file of scripts)w.eval(source(file));
   await wait(()=>!q('.search-submit').disabled);click('.search-submit');
   await wait(()=>q('#results-summary').textContent.includes('3 варианта')&&q('#search-status').hidden);
   click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="anex%3A"]');click('[data-action="refresh-hotel"]');
   await wait(()=>q('#modal-body').textContent.includes('ANEX CONCRETE'));
   assert.equal(q('#all-offers-list'),null,'single concrete offer is already selected');
   click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="anex-additional-prices"]'));
   assert.match(q('#modal-body').textContent,/не пересчёт стоимости/);
   click('[data-action="anex-additional-prices"]');await wait(()=>q('#anex-additional-error').textContent.includes('не вернул'));
   assert(q('[data-action="anex-additional-prices"]').disabled);assert(!q('[data-action="anex-flights"]').disabled,'empty APD leaves independent continuation available');
   click('[data-action="anex-flights"]');await wait(()=>releaseFlights);
   click('[data-action="close-modal"]');click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="anex%3A"]');
   assert.match(q('#anex-flight-inventory').textContent,/Загружаем рейсы/);assert(!q('[data-action="anex-flights"]'),'pending read cannot repeat after reopen');
   releaseFlights();await wait(()=>q('#anex-flight-inventory').textContent.includes(failed?'Не удалось':'TEST ANEX 101'));
   if(!failed){const flightText=q('#anex-flight-inventory').textContent,expectedDate=new Date(trip.from+'T12:00:00Z').toLocaleDateString('ru-RU',{day:'numeric',month:'short',timeZone:'UTC'}).replace('.','');assert.match(flightText,new RegExp(expectedDate));assert.doesNotMatch(flightText,/\b\d{8}\b/,'compact supplier dates are never exposed raw');assert.match(flightText,/Расписание уточняется/);assert.match(flightText,/не выбранные рейсы/);}
   assert(!q('[data-action="anex-application-preview"]'),'unknown price cannot acquire priced application authority');
   const before=transport.calls.length;
   click('[data-action="close-modal"]');await new Promise(r=>setTimeout(r,150));w.history.forward();await new Promise(r=>setTimeout(r,150));
   assert(q('#modal').open,'browser Forward restores terminal APD and flight outcomes');
   assert(q('[data-action="anex-additional-prices"]').disabled);assert(!q('[data-action="anex-flights"]'));assert.equal(transport.calls.length,before);
   click('[data-action="close-modal"]');click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="anex%3A"]');
   assert(q('[data-action="anex-additional-prices"]').disabled);assert(!q('[data-action="anex-flights"]'));assert.equal(transport.calls.length,before);
   assert.equal(transport.calls.filter(c=>c.url.includes('anex')&&c.action==='flights').length,1);assert.equal(transport.calls.filter(c=>c.action==='additional_prices').length,1);
   assert.deepEqual(errors,[]);
  }finally{dom.window.close();}
 }
 console.log('VISUAL_ANEX_EMPTY_APD_FLIGHT_CONTINUATION_OK success/failure/pending/reopen; supplier calls 0');
 for(const failure of ['', 'supplier', 'different-pair']){
  const transport=fixture({anexEmptyAdditional:true}),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
  const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
  const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
  Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
  w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
  w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
  w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
  w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
  transport.state.anexQuoteFailure=failure==='supplier';
  let releaseQuote;
  w.fetch=async(url,options={})=>{const value=await transport.json(url,options);if(transport.calls.at(-1)?.action==='quote_calculate')await new Promise(resolve=>releaseQuote=resolve);if(failure==='different-pair'&&value.data?.status==='quote_verified')value.data.choice.choice_ref='anex_quote:'+'1'.repeat(64);return new Response(JSON.stringify(value),{status:200,headers:{'Content-Type':'application/json'}});};
  const wait=async(fn)=>{for(let i=0;i<80;i++){if(fn())return;await new Promise(r=>setTimeout(r,50));}assert.fail('Timeout quote: '+d.body.textContent.slice(-2000));};
  try{
   for(const file of scripts)w.eval(source(file));
   await wait(()=>!q('.search-submit').disabled);click('.search-submit');
   await wait(()=>q('#results-summary').textContent.includes('3 варианта')&&q('#search-status').hidden);
   click('[data-action="all-offers"][data-id="501"]');await wait(()=>q('#all-offers-list'));click('#modal-body [data-action="offer"][data-key^="anex%3A"]');click('[data-action="refresh-hotel"]');
   await wait(()=>q('#modal-body').textContent.includes('ANEX CONCRETE'));
   assert.equal(q('#all-offers-list'),null,'single concrete offer is already selected');
   click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="anex-package-quote"]'));
   click('[data-action="anex-package-quote"]');await wait(()=>q('[name="anex-package-choice"]'));
   const alternate=d.querySelectorAll('[name="anex-package-choice"]')[1];alternate.click();
   assert.match(q('#modal-body').textContent,/TEST ANEX PACKAGE 2 OUT/);
   click('[data-action="anex-package-calculate"]');await wait(()=>releaseQuote);
   assert.equal(q('[name="anex-package-choice"]:checked')?.value,alternate.value,'pending calculation must keep the requested second pair selected');
   assert([...d.querySelectorAll('[name="anex-package-choice"]')].every(el=>el.disabled),'pending pair is locked');
   assert(!q('[data-action="anex-application-preview"]'),'pending calculation grants no priced application');
   const pendingCalls=transport.calls.length;
   click('[data-action="close-modal"]');await new Promise(r=>setTimeout(r,150));w.history.forward();await new Promise(r=>setTimeout(r,150));
   assert.equal(q('[name="anex-package-choice"]:checked')?.value,alternate.value,'pending Forward keeps the actual requested pair');
   assert.equal(transport.calls.length,pendingCalls,'pending Forward is passive');
   releaseQuote();
   if(failure){
    await wait(()=>q('#anex-package-status').textContent.includes('не подтвердил'));
    assert(!q('[data-action="anex-application-preview"]'));assert(!q('[data-action="anex-package-calculate"]'));
    assert.equal(q('[name="anex-package-choice"]:checked')?.value,alternate.value,'failed calculation keeps the requested pair visible');
    assert([...d.querySelectorAll('[name="anex-package-choice"]')].every(el=>el.disabled),'consumed calculation cannot imply another selection is actionable');
   }else{
    await wait(()=>q('[data-action="anex-application-preview"]'));assert.match(q('#modal-body').textContent,/TEST ANEX PACKAGE 2 OUT/);
    assert.match(q('#modal-body').textContent.replace(/\s/g,''),/135678,9/);click('[data-action="anex-application-preview"]');
    const form=q('#prototype-lead-form');form.elements.phone.value='+79990000000';form.elements.phone.dispatchEvent(new w.Event('input',{bubbles:true}));form.elements.consent.checked=true;
    form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));assert.equal(form.dataset.checked,'1');
    assert.match(q('.lead-message').textContent,/Подтверждённая стоимость/);assert.match(q('.lead-message').textContent,/не отправлена/);
   }
   const count=transport.calls.length;click('[data-action="close-modal"]');await new Promise(r=>setTimeout(r,150));w.history.forward();await new Promise(r=>setTimeout(r,150));
   assert(q('#modal').open);assert.equal(transport.calls.length,count,'ANEX package/application Forward is supplier-free');
   if(failure)assert.equal(q('[name="anex-package-choice"]:checked')?.value,alternate.value,'failed Forward keeps the requested pair');
   if(!failure){assert(q('#prototype-lead-form'));assert.equal(q('#prototype-lead-form').elements.phone.value,'+79990000000');assert.equal(q('#prototype-lead-form').elements.consent.checked,false);}
   assert.equal(transport.calls.filter(c=>c.action==='quote_start').length,1);assert.equal(transport.calls.filter(c=>c.action==='quote_calculate').length,1);
   assert.equal(transport.calls.filter(c=>c.action==='additional_prices').length,0,'package quote independent of APD');assert.deepEqual(errors,[]);
  }finally{dom.window.close();}
 }
 console.log('VISUAL_ANEX_PACKAGE_QUOTE_OK exact pair/gross price/application/history/rejections; supplier calls 0');
})().catch(e=>{console.error(e);process.exitCode=1;});
