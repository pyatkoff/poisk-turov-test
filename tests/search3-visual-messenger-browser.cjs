'use strict';
// The existing live-browser server supplies the actual compiled visual graph.
// Every API and external navigation below is intercepted with fictional data.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {fixture,trip,profile,day,back}=require('./search3-visual-live-fixture.cjs');
const plain=value=>JSON.parse(JSON.stringify(value));
const hash=file=>crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const waitFor=(promise,label)=>new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(label+' did not start')),10000);promise.then(value=>{clearTimeout(timer);resolve(value);},error=>{clearTimeout(timer);reject(error);});});
const digest=value=>crypto.createHash('sha256').update(value).digest('hex');
// Accepted HTTP interface only. The native fictional fixture supplies DTOs;
// these retained records never come from a DOM or a submitted browser price.
function privateTVFixture(transport,origin,{minimum=false}={}){
 const calls=[],members=new Map(),checked=new Map(),pairs=new Map(),observations=new Map(),generation=37,search_ref='d'.repeat(32),csrf='a'.repeat(64);let params;
 const pin=(ref,choice_ref)=>({provider:'tourvisor',generation,search_ref,offer_ref:ref,local_hotel_id:members.get(ref).hotelId,choice_ref});
 const native=async(action,fields={})=>{
  const query=new URLSearchParams({action});
  for(const [name,value] of Object.entries(fields)){
   if(Array.isArray(value))for(const item of value)query.append(name+'[]',String(item));
   else query.set(name,String(value));
  }
  return transport.json(origin+'/api-v2.php?'+query);
 };
 const text=v=>String(v&&typeof v==='object'?v.russianName||v.name||v.fullName||'':v??'').trim();
 const legs=values=>values.map(v=>({label:text(v.number)+' · '+text(v.departure.port.shortName??v.departure.port.id)+' → '+text(v.arrival.port.shortName??v.arrival.port.id)+' · '+text(v.departure.date)}));
 async function handle(action,body,method){
  if(!String(action).startsWith('tv_'))return undefined;
  calls.push({action,method,body:plain(body)});
  if(action==='tv_token'){assert.equal(method,'GET');return {ok:true,csrf,provider:'tourvisor'};}
  assert.equal(method,'POST');assert.equal(body.provider,'tourvisor');assert.equal(body.csrf,csrf);assert.match(body.request_id,/^[a-f0-9]{32}$/);
  const shape=['action','provider','request_id','csrf',...(action==='tv_search'?['params']:['generation','search_ref']),...(['tv_tour','tv_flights','tv_select'].includes(action)?['offer_ref']:[]),...(action==='tv_select'?['choice_ref']:[])];
  assert.deepEqual(Object.keys(body).sort(),shape.sort(),'private TV exact interface');
  if(action!=='tv_search'){assert.equal(body.generation,generation);assert.equal(body.search_ref,search_ref);}
  let data=null,extra={};
  if(action==='tv_search'){params=plain(body.params);data=await native('search_start',params);}
  else if(action==='tv_status')data=await native('search_status');
  else if(action==='tv_continue')data=await native('search_continue');
  else if(action==='tv_results'){
   data=await native('search_results',{limit:1000});
   if(minimum)data=data.map(h=>({...h,tours:h.tours.map(t=>({...t,price:90000,operator:{name:'FUN&SUN'}}))}));
   extra.offers=data.flatMap(h=>h.tours.map(t=>{const ref='tourvisor:'+digest('tourvisor:tour:'+t.id);members.set(ref,{tourId:t.id,hotelId:Number(h.id)});return {offer_ref:ref,local_hotel_id:Number(h.id)};}));
  }else{
   const member=members.get(body.offer_ref);assert(member,'only a returned offer is retained');
   if(action==='tv_tour'){
    data={...await native('tour',{tourId:member.tourId}),departure:'Москва'};
    if(minimum)data={...data,price:90000,operator:{name:'FUN&SUN'}};
    checked.set(body.offer_ref,{tour:plain(data),expiresAt:Math.floor(Date.now()/1000)+600});extra={selector:pin(body.offer_ref,'manager-deferred'),record_sha256:'a'.repeat(64)};
   }else if(action==='tv_flights'){
    data=await native('flights',{tourId:member.tourId});extra.choices=data.map((value,index)=>({index,choice_ref:'tourvisor_pair:'+digest(JSON.stringify(value))}));
    pairs.set(body.offer_ref,new Map(extra.choices.map(p=>[p.choice_ref,plain(data[p.index])])));
   }else if(action==='tv_select'){
    assert(checked.has(body.offer_ref));assert(body.choice_ref==='manager-deferred'||pairs.get(body.offer_ref)?.has(body.choice_ref));extra={selector:pin(body.offer_ref,body.choice_ref),record_sha256:'b'.repeat(64)};
   }else throw Error('Unexpected TV action '+action);
  }
  return {ok:true,data:{provider:'tourvisor',generation,search_ref,data,...extra}};
 }
 function freeze(fields){
  const {offer_ref,choice_ref}=fields,member=members.get(offer_ref),fact=checked.get(offer_ref);assert(member&&fact);
  assert.deepEqual(fields,pin(offer_ref,choice_ref));assert(calls.some(c=>c.action==='tv_select'&&c.body.offer_ref===offer_ref&&c.body.choice_ref===choice_ref));
  const key=offer_ref+'|'+choice_ref;if(observations.has(key))return observations.get(key);
  const tour=fact.tour,pair=choice_ref==='manager-deferred'?null:pairs.get(offer_ref)?.get(choice_ref);if(choice_ref!=='manager-deferred')assert(pair);
  const price=pair?.price??tour.price,amount=String(price&&typeof price==='object'?price.value:price);
  const observation={schema:1,kind:'search3_browser_observation',capturedAt:Date.now(),source:'tourvisor',hotel:{uiId:member.hotelId,namespace:'search3-ui',name:profile.name,resort:'Белек'},
   trip:{origin:text(tour.departure),countryUiId:String(params.countryId),departure:tour.date,nights:tour.nights,stayEnd:null,stayEndBasis:'unknown',adults:params.adults,childAges:plain(params.childs)},
   stay:{room:text(tour.roomType),meal:text(tour.meal),placement:text(tour.placement),operator:text(tour.operator)},
   flights:{state:pair?'selected':'manager-deferred',outward:pair?legs(pair.forward):[],inward:pair?legs(pair.backward):[]},
   price:{amount,currency:'RUB',state:'checked',basis:pair?'selected-pair':'shown-offer',description:'Цена Tourvisor за всю группу; окончательный расчёт не подтверждён.',expiresAt:fact.expiresAt,finalPriceVerified:false,authority:'browser-observation-only'}};
  const result={operation_id:digest(key).slice(0,32),state:'prepared',observation};observations.set(key,result);return result;
 }
 return {calls,handle,freeze};
}

async function tourvisorJourney({browser,origin,base,output}){
 const records=[],key='tourvisor%3Avisual-tv-101';
 for(const width of [430,1280]){
  const height=width===1280?720:900,transport=fixture(),tv=privateTVFixture(transport,origin,{minimum:true}),calls=[],errors=[],forbidden=[],navigations=[],shots=[];
  const context=await browser.newContext({viewport:{width,height}}),page=await context.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));
  const csrf='a'.repeat(64),links={telegram:'https://t.me/Any_tour_bot?start=tv_fixture_only',max:'https://max.ru/id9704048781_bot?start=tv_fixture_only'};
  let releaseCreate,startedCreate;const createGate=new Promise(resolve=>releaseCreate=resolve),creating=new Promise(resolve=>startedCreate=resolve);
  const ready=()=>page.waitForFunction(()=>{const buttons=[...document.querySelectorAll('[data-action="messenger-open"]')];return buttons.length===2&&buttons.every(b=>!b.disabled);});
  const amount=()=>page.locator('#modal-footer .footer-total>strong').textContent(),row=()=>page.locator('.room-offer-choice[data-offer-key="'+key+'"]');
  const capture=async(stage,methods=false)=>{
   const geometry=await page.locator('#modal').evaluate(el=>{
    const box=n=>{const r=n.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,height:r.height};},body=el.querySelector('#modal-body'),footer=el.querySelector('#modal-footer');
    return {overflow:el.scrollWidth>el.clientWidth+1||body.scrollWidth>body.clientWidth+1||document.documentElement.scrollWidth>innerWidth,body:box(body),footer:box(footer),buttons:[...el.querySelectorAll('#messenger-methods button:not([hidden])')].map(box)};
   });
   assert.equal(geometry.overflow,false,stage+' fits the viewport');assert(geometry.footer.bottom<=height+1&&geometry.body.bottom<=geometry.footer.y+1,stage+' footer leaves the content available');
   if(methods)for(const b of geometry.buttons){assert(b.height>=44);assert(b.x>=geometry.body.x-1&&b.right<=geometry.body.right+1);assert(b.y>=geometry.body.y-1&&b.bottom<=geometry.body.bottom+1,stage+' methods are visible');}
   const file=`tv-${stage}-${width}x${height}.png`;await page.screenshot({path:path.join(output,file)});shots.push({stage,file,geometry});
  };
  try{
   await page.route('**/*',async route=>{
    const req=route.request(),url=new URL(req.url()),body=req.postData()?JSON.parse(req.postData()):{},action=url.searchParams.get('action')||body.action;
    try{
     if(Object.values(links).includes(url.href)&&req.isNavigationRequest()){navigations.push(url.href);await route.fulfill({contentType:'text/html',body:'<!doctype html><h1>Fictional TV navigation intercepted</h1>'});return;}
     assert.equal(url.origin,origin,'external HTTP is forbidden');
     if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
     if(url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
     assert(!(url.pathname==='/api-v2.php'&&/^search_|^(?:tour|flights)$/.test(action)),'actual TV default cannot use root search/quote/flights');
     const tvReply=await tv.handle(action,body,req.method());if(tvReply!==undefined){await route.fulfill({contentType:'application/json',body:JSON.stringify(tvReply)});return;}
     let value;
     if(String(action).startsWith('handoff_')){
      calls.push({action,body:plain(body)});
      if(action==='handoff_token'){assert.equal(req.method(),'GET');value={ok:true,csrf};}
      else{
       assert.equal(req.method(),'POST');assert.equal(body.csrf,csrf);
       if(action==='handoff_freeze'){const {action:_,csrf:__,...pins}=body;value={ok:true,data:tv.freeze(pins)};}
       else{assert.equal(action,'handoff_create');assert.deepEqual(Object.keys(body).sort(),['action','csrf','operation_id']);startedCreate();await createGate;value={ok:true,data:{operation_id:body.operation_id,state:'ready',links}};}
      }
     }else value=await transport.json(req.url(),{body:req.postData()});
     await route.fulfill({contentType:'application/json',body:JSON.stringify(value)});
    }catch(error){forbidden.push(error.message);await route.abort();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,adults:3,ages:'12,0'}));
   // This family fixture keeps the independent ANEX source partial. Its honest
   // compact completion notice is visible; it is not a still-running TV search.
   await page.locator('.search-submit:not(:disabled)').click();await page.waitForFunction(()=>document.querySelector('.hotel-card')&&(document.querySelector('#search-status').hidden||document.querySelector('#search-status').classList.contains('search-status-compact')));
   assert.equal(await page.evaluate(()=>window.V2_CONFIG.search3TVSessionEnabled),true,'unmodified real default');
   const card=page.locator('.hotel-card .hotel-price [data-key="'+key+'"]');await card.click();await row().waitFor({state:'visible'});await capture('rooms');
   assert.equal(tv.calls.some(c=>c.action==='tv_tour'||c.action==='tv_flights'),false,'room entry spends no quote or flights');
   await page.locator('#hotel-detail-offers').click();await ready();await capture('manager-methods',true);
   assert.equal(await page.locator('#modal-title').textContent(),'Продолжить');assert.match((await amount()).replace(/\s/g,''),/90000/);
   assert.match((await page.locator('.application-review-heading small').textContent()).replace(/\s+/g,' '),/12 лет, до года/,'the entered child order stays visible while its canonical request order is compared');
   assert.equal(await page.locator('[name="phone"]').isVisible(),false);assert.equal(await page.locator('[type="submit"][form="prototype-lead-form"]').isVisible(),false);
   // Retained ordinary INT screenshot: full Russian labels must not paint over
   // operator/placement values. Use the same fictional contact journey, no new calls.
   const details=page.locator('.application-review-card>details.application-review');
   await details.locator(':scope > summary').click();
   const conditionRows=await details.locator('.saved-stay-summary>div').evaluateAll(rows=>rows.map(row=>{
    const dt=row.querySelector('dt'),dd=row.querySelector('dd'),range=document.createRange();range.selectNodeContents(dt);
    const text=range.getBoundingClientRect(),label=dt.getBoundingClientRect(),value=dd.getBoundingClientRect();
    return {label:dt.textContent,textRight:text.right,labelRight:label.right,valueX:value.x,valueY:value.y,labelBottom:label.bottom};
   }));
   assert(conditionRows.some(row=>row.label==='Туроператор'),'expanded exact operator fact is covered');
   for(const row of conditionRows){
    assert(row.textRight<=row.labelRight+1,'full condition label wraps inside its own column: '+row.label);
    assert(row.valueY>=row.labelBottom-1||row.textRight+6<=row.valueX,'condition label/value remain separated: '+row.label);
   }
   await capture('manager-condition-details',true);
   await details.locator(':scope > summary').click();
   assert.equal(tv.calls.filter(c=>c.action==='tv_tour').length,1);assert.equal(tv.calls.some(c=>c.action==='tv_flights'),false);
   const first=calls.find(c=>c.action==='handoff_freeze').body;assert.equal(first.choice_ref,'manager-deferred');assert.equal(first.local_hotel_id,101);
   assert.equal(calls.some(c=>c.action==='handoff_create'),false,'contact entry never CREATEs');
   await page.locator('[data-action="messenger-phone"]').click();await page.locator('[name="phone"]').fill('70000001234');await page.locator('[name="comment"]').fill('Fictional TV family draft');await page.locator('[name="consent"]').click();
   const phone=await page.locator('[name="phone"]').elementHandle();await capture('manager-phone');await page.locator('[data-action="messenger-other"]').click();await capture('methods-return',true);
   await page.locator('[data-action="messenger-phone"]').click();assert.equal(await page.evaluate(el=>el===document.querySelector('[name="phone"]'),phone),true);assert.equal(await page.locator('[name="consent"]').isChecked(),true);
   await page.locator('#modal-back').click();await row().waitFor({state:'visible'});assert.equal(await row().getAttribute('aria-current'),'true');
   await row().locator('[data-action="hotel-flights"]').click();await row().locator('[name="flight-pair"][value="1"]').waitFor();
   assert.equal(await row().locator('[name="flight-pair"]:checked').count(),0,'optional flights never choose pair zero implicitly');
   await row().locator('[name="flight-pair"][value="1"]').click();await capture('pair-rooms');await page.locator('#hotel-detail-offers').click();await ready();
   await capture('pair-methods',true);assert.match((await amount()).replace(/\s/g,''),/133500/);
   const pairFreeze=calls.filter(c=>c.action==='handoff_freeze').at(-1).body;assert.match(pairFreeze.choice_ref,/^tourvisor_pair:[a-f0-9]{64}$/);assert.equal(pairFreeze.offer_ref,first.offer_ref);assert.equal(pairFreeze.generation,first.generation);
   await page.locator('[data-action="messenger-phone"]').click();assert.equal(await page.locator('[name="phone"]').inputValue(),'70000001234');assert.equal(await page.locator('[name="comment"]').inputValue(),'Fictional TV family draft');assert.equal(await page.locator('[name="consent"]').isChecked(),false,'changed exact pair resets consent');
   await page.locator('[data-action="messenger-other"]').click();await page.locator('[data-action="messenger-open"][data-platform="telegram"]').click();await waitFor(creating,'TV explicit CREATE');
   await page.locator('[data-action="messenger-phone"]').click();await page.locator('[data-action="messenger-other"]').click();releaseCreate();await ready();
   assert.deepEqual(navigations,[],'late CREATE cannot navigate after a changed method intent');await capture('pending-return',true);
   await page.locator('#modal-back').click();await row().waitFor({state:'visible'});await page.locator('#hotel-detail-offers').click();await ready();
   await page.locator('[data-action="messenger-phone"]').click();assert.equal(await page.locator('[name="phone"]').inputValue(),'70000001234');await page.locator('[data-action="messenger-other"]').click();await capture('pair-reopen',true);
   assert.equal(tv.calls.filter(c=>c.action==='tv_token').length,1);assert.equal(tv.calls.filter(c=>c.action==='tv_tour').length,1);assert.equal(tv.calls.filter(c=>c.action==='tv_flights').length,1);assert.equal(tv.calls.filter(c=>c.action==='tv_select').length,2,'only manager and the exact chosen pair');
   assert.equal(calls.filter(c=>c.action==='handoff_create').length,1);assert.equal(tv.calls[0].action,'tv_token');assert.deepEqual(tv.calls.find(c=>c.action==='tv_search').body.params.childs,[0,12]);
   await Promise.all([page.waitForURL(links.max),page.locator('[data-action="messenger-open"][data-platform="max"]').click()]);assert.deepEqual(navigations,[links.max]);
   assert.equal(calls.filter(c=>c.action==='handoff_create').length,1);assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   records.push({provider:'tourvisor',width,height,party:{adults:3,enteredChildAges:[12,0],requestChildAges:[0,12]},managerAmount:'90000',pairAmount:'133500.5',managerSelector:first,pairSelector:pairFreeze,actions:tv.calls.map(c=>c.action),rootTVSearchQuoteHTTP:0,quoteCalls:1,flightCalls:1,selectCalls:2,createCalls:1,draftRetained:true,consentResetOnPair:true,lateNavigationCancelled:true,externalNavigationIntercepted:1,shots});
  }catch(error){await page.screenshot({path:path.join(output,`tv-failure-${width}.png`)}).catch(()=>{});fs.writeFileSync(path.join(output,`tv-failure-${width}.json`),JSON.stringify({message:error.message,errors,forbidden,calls,tv:tv.calls,shots},null,2));throw error;}
  finally{releaseCreate();await context.close();}
 }
 return records;
}

// The same actual owner, one delayed fictional freeze. Fresh contact bindings
// share its pending preparation and retain the eventual terminal failure.
async function tourvisorFailedPreparationJourney({browser,origin,base,output}){
 const records=[],key='tourvisor%3Avisual-tv-101';
 for(const width of [430,1280]){
  const height=width===1280?720:900,transport=fixture(),tv=privateTVFixture(transport,origin,{minimum:true}),calls=[],errors=[],forbidden=[],shots=[];
  const context=await browser.newContext({viewport:{width,height}}),page=await context.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));
  const csrf='a'.repeat(64),row=()=>page.locator('.room-offer-choice[data-offer-key="'+key+'"]');
  let releaseFreeze,markFreeze;
  const freezePending=new Promise(resolve=>{releaseFreeze=resolve;}),freezeArrived=new Promise(resolve=>{markFreeze=resolve;});
  const failed=()=>page.waitForFunction(()=>document.querySelector('#messenger-status')?.textContent.includes('Не удалось подготовить выбранный тур для передачи.'));
  const capture=async(stage,methods=false)=>{
   const geometry=await page.locator('#modal').evaluate(el=>{
    const box=n=>{const r=n.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,height:r.height};},body=el.querySelector('#modal-body'),footer=el.querySelector('#modal-footer');
    return {overflow:el.scrollWidth>el.clientWidth+1||body.scrollWidth>body.clientWidth+1||document.documentElement.scrollWidth>innerWidth,body:box(body),footer:box(footer),status:box(el.querySelector('#messenger-status')),buttons:[...el.querySelectorAll('#messenger-methods .messenger-methods button')].map(box)};
   });
   assert.equal(geometry.overflow,false,stage+' fits the viewport');assert(geometry.footer.bottom<=height+1&&geometry.body.bottom<=geometry.footer.y+1,stage+' footer leaves content available');
   if(methods){
    for(const b of geometry.buttons){assert(b.height>=44);assert(b.x>=geometry.body.x-1&&b.right<=geometry.body.right+1);assert(b.y>=geometry.body.y-1&&b.bottom<=geometry.body.bottom+1,stage+' methods remain visible');}
    assert(geometry.status.y>=geometry.body.y-1&&geometry.status.bottom<=geometry.body.bottom+1,stage+' complete failure guidance remains visible');
   }
   const file=`tv-${stage}-${width}x${height}.png`;await page.screenshot({path:path.join(output,file)});shots.push({stage,file,geometry});
  };
  try{
   await page.route('**/*',async route=>{
    const req=route.request(),url=new URL(req.url()),body=req.postData()?JSON.parse(req.postData()):{},action=url.searchParams.get('action')||body.action;
    try{
     assert.equal(url.origin,origin,'no external navigation or HTTP in failed preparation');
     if(url.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
     if(url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
     assert(!(url.pathname==='/api-v2.php'&&/^search_|^(?:tour|flights)$/.test(action)),'actual TV entry cannot use root search/quote/flights');
     const tvReply=await tv.handle(action,body,req.method());if(tvReply!==undefined){await route.fulfill({contentType:'application/json',body:JSON.stringify(tvReply)});return;}
     if(String(action).startsWith('handoff_')){
      calls.push({action,method:req.method()});
      if(action==='handoff_token'){assert.equal(req.method(),'GET');await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,csrf})});return;}
      assert.equal(action,'handoff_freeze','failed preparation never CREATEs, checks or revokes');assert.equal(req.method(),'POST');assert.equal(body.csrf,csrf);
      const {action:_,csrf:__,...pins}=body;tv.freeze(pins); // Validate the same retained selector without sending it anywhere.
      markFreeze();await freezePending;
      await route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({ok:false,error:'retained_fact_missing',field:'stay.room',owner:'INT',private_detail:'PRIVATE_NOT_FOR_UI'})});return;
     }
     const value=await transport.json(req.url(),{body:req.postData()});await route.fulfill({contentType:'application/json',body:JSON.stringify(value)});
    }catch(error){forbidden.push(error.message);await route.abort();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,adults:3,ages:'12,0'}));
   await page.locator('.search-submit:not(:disabled)').click();await page.waitForFunction(()=>document.querySelector('.hotel-card')&&(document.querySelector('#search-status').hidden||document.querySelector('#search-status').classList.contains('search-status-compact')));
   await page.locator('.hotel-card .hotel-price [data-key="'+key+'"]').click();await row().waitFor({state:'visible'});await page.locator('#hotel-detail-offers').click();
   let freezeDeadline;
   try{await Promise.race([freezeArrived,new Promise((resolve,reject)=>{freezeDeadline=setTimeout(()=>reject(Error('preparation request did not arrive')),10000);})]);}finally{clearTimeout(freezeDeadline);}
   assert.match(await page.locator('#messenger-status').textContent(),/Подготавливаем переход/);await capture('pending-methods',true);
   await page.locator('[data-action="messenger-phone"]').click();await page.locator('[name="phone"]').fill('70000001234');await page.locator('[name="comment"]').fill('Fictional failed-preparation draft');await page.locator('[data-action="messenger-other"]').click();
   await page.locator('#modal-back').click();await row().waitFor({state:'visible'});assert.equal(await row().getAttribute('aria-current'),'true');await page.locator('#hotel-detail-offers').click();
   assert.match(await page.locator('#messenger-status').textContent(),/Подготавливаем переход/);await capture('pending-return',true);
   assert.equal(await page.locator('[data-action="messenger-open"]:disabled').count(),2);assert.equal(await page.locator('#messenger-check').isVisible(),false);
   assert.deepEqual(calls.map(c=>c.action),['handoff_token','handoff_freeze'],'Back/reopen shares the pending preparation');
   releaseFreeze();await failed();
   assert.equal(await page.evaluate(()=>window.V2_CONFIG.search3TVSessionEnabled),true,'unmodified real default');
   assert.match((await page.locator('#modal-footer .footer-total>strong').textContent()).replace(/\s/g,''),/90000/);
   const status=await page.locator('#messenger-status').textContent();assert.match(status,/телефону|выбору тура/);assert.doesNotMatch(status,/422|stay\.room|INT|PRIVATE|retained_fact_missing/);
   assert.equal(await page.locator('[data-action="messenger-open"]:disabled').count(),2);assert.equal(await page.locator('#messenger-check').isVisible(),false);
   assert.equal(await page.locator('[name="phone"]').isVisible(),false);await capture('failed-methods',true);
   await page.locator('[data-action="messenger-phone"]').click();assert.equal(await page.locator('[name="phone"]').inputValue(),'70000001234');assert.equal(await page.locator('[name="comment"]').inputValue(),'Fictional failed-preparation draft');await capture('failed-phone');
   await page.locator('[data-action="messenger-other"]').click();
   await page.locator('#modal-back').click();await row().waitFor({state:'visible'});assert.equal(await row().getAttribute('aria-current'),'true');await page.locator('#hotel-detail-offers').click();await failed();
   assert.equal(await page.locator('[data-action="messenger-open"]:disabled').count(),2);assert.equal(await page.locator('#messenger-check').isVisible(),false);assert.match((await page.locator('#modal-footer .footer-total>strong').textContent()).replace(/\s/g,''),/90000/);await capture('failed-return',true);
   await page.locator('[data-action="messenger-phone"]').click();assert.equal(await page.locator('[name="phone"]').inputValue(),'70000001234');assert.equal(await page.locator('[name="comment"]').inputValue(),'Fictional failed-preparation draft');assert.equal(await page.locator('[name="consent"]').isChecked(),false);
   assert.deepEqual(calls.map(c=>c.action),['handoff_token','handoff_freeze'],'ordinary Back/reopen never repeats failed preparation');
   assert.equal(tv.calls.filter(c=>c.action==='tv_tour').length,1);assert.equal(tv.calls.filter(c=>c.action==='tv_flights').length,0);assert.equal(tv.calls.filter(c=>c.action==='tv_select').length,1);assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   records.push({provider:'tourvisor',scenario:'failed-preparation',width,height,party:{adults:3,enteredChildAges:[12,0],requestChildAges:[0,12]},amount:'90000',failure:{action:'handoff_freeze',stage:'http',code:'retained_fact_missing',httpStatus:422,field:'stay.room',owner:'INT'},message:status,botsDisabled:true,statusCheckHidden:true,pendingPreparationRetained:true,failedPreparationRetained:true,draftRetained:true,quoteCalls:1,flightCalls:0,selectCalls:1,createCalls:0,externalNavigationIntercepted:0,shots});
  }catch(error){await page.screenshot({path:path.join(output,`tv-failed-preparation-error-${width}.png`)}).catch(()=>{});fs.writeFileSync(path.join(output,`tv-failed-preparation-error-${width}.json`),JSON.stringify({message:error.message,errors,forbidden,calls,tv:tv.calls,shots},null,2));throw error;}
  finally{releaseFreeze();await context.close();}
 }
 return records;
}

module.exports=async function messengerJourney({browser,origin,base,evidence}){
 const output=path.join(evidence,'messenger-entry');fs.mkdirSync(output,{recursive:true});
 const assetRoot=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||path.join(__dirname,'../v2'));
 const files=['visual-search/app.js','visual-search/styles.css','visual-search/index.php','prototype-search/config.js','prototype-search/data.js','prototype-search/messenger-context-v1.js','prototype-search/messenger-flow-v1.js'];
 const hashes=Object.fromEntries(files.map(file=>[file,hash(path.join(assetRoot,file))]));
 const records=[];
 for(const width of [430,1280]){
  const height=width===1280?720:900,transport=fixture(),tv=privateTVFixture(transport,origin),calls=[],errors=[],forbidden=[],navigations=[],shots=[];
  transport.state.anexPackageChoiceCount=1;
  const context=await browser.newContext({viewport:{width,height}}),page=await context.newPage();page.setDefaultTimeout(10000);
  page.on('pageerror',error=>errors.push(error.message));
  const csrf='a'.repeat(64),operation='f'.repeat(32),links={telegram:'https://t.me/Any_tour_bot?start=search3_fixture_only',max:'https://max.ru/id9704048781_bot?start=search3_fixture_only'};
  let selected,selector,releaseCreate,createStarted,observation;
  const createGate=new Promise(resolve=>releaseCreate=resolve),creating=new Promise(resolve=>createStarted=resolve);
  const capture=async(stage,{methods=false}={})=>{
   const geometry=await page.locator('#modal').evaluate(modal=>{
    const rect=element=>{const r=element.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
    const body=modal.querySelector('#modal-body'),footer=modal.querySelector('#modal-footer');
    return {dialog:rect(modal),body:rect(body),footer:rect(footer),heading:modal.querySelector('#messenger-methods>h3')?rect(modal.querySelector('#messenger-methods>h3')):null,dialogOverflow:modal.scrollWidth>modal.clientWidth+1,bodyOverflow:body.scrollWidth>body.clientWidth+1,pageOverflow:document.documentElement.scrollWidth>innerWidth,buttons:[...modal.querySelectorAll('#messenger-methods .messenger-methods button')].map(rect),bodyScroll:body.scrollTop,viewport:innerHeight};
   });
   const filename=`${stage}-${width}x${height}.png`;await page.screenshot({path:path.join(output,filename)});shots.push({stage,file:filename,geometry});
   assert.equal(geometry.dialogOverflow,false,stage+' dialog overflow');assert.equal(geometry.bodyOverflow,false,stage+' body overflow');assert.equal(geometry.pageOverflow,false,stage+' page overflow');
   assert(geometry.footer.bottom<=height+1&&geometry.body.bottom<=geometry.footer.y+1,stage+' footer does not cover the body');
   if(methods){assert(geometry.heading.y>=geometry.body.y-1&&geometry.heading.bottom<=geometry.body.bottom+1,stage+' shows the complete choice heading');assert.equal(geometry.bodyScroll,0,stage+' starts at the top of the choice');}
   if(methods)for(const button of geometry.buttons){
    assert(button.height>=44,stage+' each method has a44px target');
    assert(button.x>=geometry.body.x-1&&button.right<=geometry.body.right+1,stage+' method fits horizontally');
    assert(button.y>=geometry.body.y-1&&button.bottom<=Math.min(geometry.body.bottom,geometry.footer.y)+1,stage+' methods are visible without searching through the review');
   }
   return geometry;
  };
  try{
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    try{
     // Never continue a request to a real bot, supplier or other external host.
     if(Object.values(links).includes(url.href)&&request.isNavigationRequest()){
      navigations.push(url.href);await route.fulfill({contentType:'text/html',body:'<!doctype html><title>Fictional bot destination</title><h1>Fictional navigation intercepted</h1>'});return;
     }
     assert.equal(url.origin,origin,'external HTTP is forbidden: '+url.origin);
     if(url.pathname==='/test-photo.svg'){
      await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;
     }
     if(url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){
      await route.continue();return;
     }
     const body=request.postData()?JSON.parse(request.postData()):{},action=url.searchParams.get('action')||body.action;
     const tvReply=await tv.handle(action,body,request.method());
     if(tvReply!==undefined){await route.fulfill({contentType:'application/json',body:JSON.stringify(tvReply)});return;}
     assert(!(url.pathname==='/api-v2.php'&&/^search_|^(?:tour|flights)$/.test(action)),'actual new entry never sends root TV search/quote HTTP');
     if(String(action).startsWith('handoff_')){
      assert(url.pathname.endsWith('/api-anex-search3-preview.php'));calls.push({action,method:request.method(),fields:plain(body)});
      if(action==='handoff_token'){
       assert.equal(request.method(),'GET');await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,csrf})});return;
      }
      assert.equal(request.method(),'POST');assert.equal(body.csrf,csrf);
      if(action==='handoff_freeze'){
       assert(selected,'a real fictional pair quote precedes contact preparation');
       const quoted=transport.calls.find(call=>call.action==='quote_calculate').body;
       selector={generation:quoted.generation,search_ref:quoted.search_ref,offer_ref:quoted.offer_ref,local_hotel_id:quoted.local_hotel_id,choice_ref:quoted.choice_ref};
       assert.deepEqual(body,{action,...selector,csrf},'only exact retained identity pins enter the dispatcher');
       // One retained fictional server observation per operation, never a DOM snapshot.
       observation=observation||{schema:1,kind:'search3_browser_observation',capturedAt:Date.now(),source:'anex',hotel:{uiId:profile.id,namespace:'search3-ui',name:profile.name,resort:'Белек'},
        trip:{origin:'Москва',departure:day,nights:7,stayEnd:back,stayEndBasis:'shown-stay-end',adults:2,childAges:[]},stay:{room:'ANEX CONCRETE',meal:'Всё включено',placement:'',operator:''},
        flights:{state:'selected',outward:[selected.choice.legs[0]],inward:[selected.choice.legs[1]]},
        price:{amount:selected.price.amount,currency:'RUB',state:'checked',basis:'selected-pair',description:'Цена с выбранными рейсами',expiresAt:selected.expires_at,finalPriceVerified:false,authority:'browser-observation-only'}};
       await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{operation_id:operation,state:'prepared',observation}})});return;
      }
      assert.equal(action,'handoff_create','this successful journey has no automatic status/revoke/retry');
      assert.deepEqual(body,{action,operation_id:operation,csrf});createStarted();await createGate;
      await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{operation_id:operation,state:'ready',links}})});return;
     }
     const result=await transport.json(request.url(),{body:request.postData()});
     if(action==='quote_calculate')selected=plain(result.data);
     await route.fulfill({status:result.ok===false?502:200,contentType:'application/json',body:JSON.stringify(result)});
    }catch(error){forbidden.push(error.message);await route.abort();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));
   await page.waitForFunction(()=>!!window.V2_CONFIG);assert.equal(await page.evaluate(()=>window.V2_CONFIG.search3MessengerEnabled),true,'actual NEXT entry enables the flag without a test override');
   assert.equal(await page.evaluate(()=>window.V2_CONFIG.search3TVSessionEnabled),true,'actual NEXT entry enables the private TV session');
   await page.locator('.search-submit:not(:disabled)').waitFor();await page.locator('.search-submit').click();
   await page.waitForFunction(()=>document.querySelector('.hotel-card')&&document.querySelector('#search-status').hidden);
   await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
   await page.locator('#modal-body [data-action="offer"][data-key^="anex%3A"]').click();await page.locator('[data-action="refresh-hotel"]').click();
   await page.waitForFunction(()=>document.querySelector('#modal-body').textContent.includes('ANEX CONCRETE'));
   await page.locator('[data-action="select-anex-tour"]').click();
   assert.equal(await page.locator('[data-action="anex-application-preview"]').textContent(),'Продолжить');
   assert.match(await page.locator('.selection-steps li').last().textContent(),/Связь/);await capture('verified');
   await page.locator('[data-action="anex-application-preview"]').click();
   await page.waitForFunction(()=>{const buttons=[...document.querySelectorAll('[data-action="messenger-open"]')];return buttons.length===2&&buttons.every(button=>!button.disabled);});
   assert.equal(await page.locator('#modal-title').textContent(),'Продолжить');
   assert.match(await page.locator('.selection-steps li').last().textContent(),/Связь/);
   assert.equal(await page.locator('[name="phone"]').isVisible(),false);assert.equal(await page.locator('[type="submit"][form="prototype-lead-form"]').isVisible(),false);
   assert.deepEqual(calls.map(call=>call.action),['handoff_token','handoff_freeze']);assert.deepEqual(navigations,[]);
   await capture('choices',{methods:true});
   await page.locator('[data-action="messenger-phone"]').click();await page.locator('[name="phone"]').fill('+7 000 000-00-00');
   assert.equal(await page.locator('[type="submit"][form="prototype-lead-form"]').isVisible(),true);assert.equal(await page.locator('[name="consent"]').isChecked(),false);
   const phone=await page.locator('[name="phone"]').elementHandle();await capture('phone');
   await page.locator('[data-action="messenger-other"]').click();
   assert.equal(await page.locator('[name="phone"]').isVisible(),false);await capture('methods-return',{methods:true});
   await page.locator('[data-action="messenger-open"][data-platform="telegram"]').click();await waitFor(creating,'explicit CREATE');
   await page.locator('[data-action="messenger-phone"]').click();await page.locator('[data-action="messenger-other"]').click();
   await capture('pending-return',{methods:true});releaseCreate();
   await page.waitForFunction(()=>{const buttons=[...document.querySelectorAll('[data-action="messenger-open"]')];return buttons.length===2&&buttons.every(button=>!button.disabled);});
   assert.equal(page.url().startsWith(origin+base),true,'the delayed reply cannot navigate after phone/back');assert.deepEqual(navigations,[]);
   await page.locator('[data-action="messenger-phone"]').click();
   assert.equal(await page.evaluate(element=>element===document.querySelector('[name="phone"]'),phone),true,'method changes retain the same actual form node');
   assert.equal(await page.locator('[name="phone"]').inputValue(),'+7 000 000-00-00');await page.locator('[data-action="messenger-other"]').click();
   await capture('ready-return',{methods:true});
   await page.locator('[data-action="messenger-phone"]').click();
   const priorPhoneScroll=await page.locator('#modal-body').evaluate(el=>{el.scrollTop=83;return el.scrollTop;});if(width===1280)assert(priorPhoneScroll>0,'desktop regression starts in a scrolled telephone view');
   await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!history.state?.['anytour.prototype.v18.ui.v1']);
   await page.goForward();await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelector('#messenger-methods')&&!document.querySelector('#messenger-methods').hidden);
   await capture('history-forward',{methods:true});
   assert.equal(await page.locator('[name="phone"]').isVisible(),false,'Forward returns to the optional method choice');
   await page.locator('[data-action="messenger-phone"]').click();assert.equal(await page.locator('[name="phone"]').inputValue(),'+7 000 000-00-00');assert.equal(await page.locator('[name="consent"]').isChecked(),false);
   await page.locator('[data-action="messenger-other"]').click();
   assert.equal(transport.calls.filter(call=>call.action==='quote_calculate').length,1,'Forward never requotes the selected tour');
   await Promise.all([page.waitForURL(links.max),page.locator('[data-action="messenger-open"][data-platform="max"]').click()]);
   assert.equal(await page.locator('h1').textContent(),'Fictional navigation intercepted');assert.deepEqual(navigations,[links.max]);
   assert.deepEqual(calls.map(call=>call.action),['handoff_token','handoff_freeze','handoff_create','handoff_freeze'],'Forward refreezes the same retained observation; explicit MAX never repeats CREATE');
   assert.equal(transport.calls.filter(call=>call.action==='quote_calculate').length,1);assert(!transport.calls.some(call=>/lead|booking|payment/.test(call.url)));
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   assert.equal(tv.calls[0].action,'tv_token');assert.equal(tv.calls.filter(c=>c.action==='tv_token').length,1);
   records.push({provider:'anex',width,height,selector,amount:selected.price.amount,TVSessionInitialized:true,rootTVSearchQuoteHTTP:0,phoneChoiceOnly:true,sameFormNode:true,draftRetained:true,cancelledPendingNavigation:true,historyForwardMethods:true,priorPhoneScroll,createCalls:1,externalNavigationIntercepted:navigations.length,shots});
  }catch(error){
   await page.screenshot({path:path.join(output,`failure-${width}x${height}.png`)}).catch(()=>{});
   fs.writeFileSync(path.join(output,`failure-${width}.json`),JSON.stringify({message:error.message,errors,forbidden,calls,shots},null,2));throw error;
  }finally{releaseCreate();await context.close();}
 }
 records.push(...await tourvisorJourney({browser,origin,base,output}));
 records.push(...await tourvisorFailedPreparationJourney({browser,origin,base,output}));
 fs.writeFileSync(path.join(output,'receipt.json'),JSON.stringify({schema:1,compiled:!!process.env.SEARCH3_VISUAL_ASSET_ROOT,engine:'Chromium',source_sha:process.env.SEARCH3_SOURCE_SHA||null,asset_sha256:hashes,test_sha256:hash(__filename),records,transport:'fictional-intercepted-HTTP',supplier_HTTP:0,bot_HTTP:0,real_leads:0,installed:false,physical_device:false},null,2)+'\n');
 console.log('PASS compiled messenger430/1280-short: exact tour/explicit phone/same draft/pending intent/one create/intercepted navigation; supplier/bot/lead HTTP0');
};
// One fictional private HTTP interface for adjacent real-entry browser consumers.
module.exports.privateTVFixture=privateTVFixture;
