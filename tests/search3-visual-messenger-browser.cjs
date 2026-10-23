'use strict';
// The existing live-browser server supplies the actual compiled visual graph.
// Every API and external navigation below is intercepted with fictional data.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {fixture,trip,profile,day,back}=require('./search3-visual-live-fixture.cjs');
const plain=value=>JSON.parse(JSON.stringify(value));
const hash=file=>crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const waitFor=(promise,label)=>new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(label+' did not start')),10000);promise.then(value=>{clearTimeout(timer);resolve(value);},error=>{clearTimeout(timer);reject(error);});});

module.exports=async function messengerJourney({browser,origin,base,evidence}){
 const output=path.join(evidence,'messenger-entry');fs.mkdirSync(output,{recursive:true});
 const assetRoot=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||path.join(__dirname,'../v2'));
 const files=['visual-search/app.js','visual-search/styles.css','visual-search/index.php','prototype-search/data.js','prototype-search/messenger-context-v1.js','prototype-search/messenger-flow-v1.js'];
 const hashes=Object.fromEntries(files.map(file=>[file,hash(path.join(assetRoot,file))]));
 const records=[];
 for(const width of [430,1280]){
  const height=width===1280?720:900,transport=fixture(),calls=[],errors=[],forbidden=[],navigations=[],shots=[];
  transport.state.anexPackageChoiceCount=1;
  const context=await browser.newContext({viewport:{width,height}}),page=await context.newPage();page.setDefaultTimeout(10000);
  page.on('pageerror',error=>errors.push(error.message));
  const csrf='a'.repeat(64),operation='f'.repeat(32),links={telegram:'https://t.me/Any_tour_bot?start=search3_fixture_only',max:'https://max.ru/id9704048781_bot?start=search3_fixture_only'};
  let selected,selector,releaseCreate,createStarted;
  const createGate=new Promise(resolve=>releaseCreate=resolve),creating=new Promise(resolve=>createStarted=resolve);
  const capture=async(stage,{methods=false}={})=>{
   const geometry=await page.locator('#modal').evaluate(modal=>{
    const rect=element=>{const r=element.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
    const body=modal.querySelector('#modal-body'),footer=modal.querySelector('#modal-footer');
    return {dialog:rect(modal),body:rect(body),footer:rect(footer),dialogOverflow:modal.scrollWidth>modal.clientWidth+1,bodyOverflow:body.scrollWidth>body.clientWidth+1,pageOverflow:document.documentElement.scrollWidth>innerWidth,buttons:[...modal.querySelectorAll('#messenger-methods .messenger-methods button')].map(rect),bodyScroll:body.scrollTop,viewport:innerHeight};
   });
   const filename=`${stage}-${width}x${height}.png`;await page.screenshot({path:path.join(output,filename)});shots.push({stage,file:filename,geometry});
   assert.equal(geometry.dialogOverflow,false,stage+' dialog overflow');assert.equal(geometry.bodyOverflow,false,stage+' body overflow');assert.equal(geometry.pageOverflow,false,stage+' page overflow');
   assert(geometry.footer.bottom<=height+1&&geometry.body.bottom<=geometry.footer.y+1,stage+' footer does not cover the body');
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
      if(url.pathname.endsWith('/prototype-search/config.js')){
       const response=await route.fetch();await route.fulfill({response,body:(await response.text())+'\nwindow.V2_CONFIG.search3MessengerEnabled=true;\n'});return;
      }
      await route.continue();return;
     }
     const body=request.postData()?JSON.parse(request.postData()):{},action=url.searchParams.get('action')||body.action;
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
       // Independently projected fictional server facts, never a DOM snapshot.
       const observation={schema:1,kind:'search3_browser_observation',capturedAt:Date.now(),source:'anex',hotel:{uiId:profile.id,namespace:'search3-ui',name:profile.name,resort:'Белек'},
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
   await page.locator('.search-submit:not(:disabled)').waitFor();await page.locator('.search-submit').click();
   await page.waitForFunction(()=>document.querySelector('.hotel-card')&&document.querySelector('#search-status').hidden);
   await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
   await page.locator('#modal-body [data-action="offer"][data-key^="anex%3A"]').click();await page.locator('[data-action="refresh-hotel"]').click();
   await page.waitForFunction(()=>document.querySelector('#modal-body').textContent.includes('ANEX CONCRETE'));
   await page.locator('[data-action="select-anex-tour"]').click();await page.locator('[data-action="anex-application-preview"]').click();
   await page.waitForFunction(()=>{const buttons=[...document.querySelectorAll('[data-action="messenger-open"]')];return buttons.length===2&&buttons.every(button=>!button.disabled);});
   assert.equal(await page.locator('#modal-title').textContent(),'Продолжить');
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
   await Promise.all([page.waitForURL(links.max),page.locator('[data-action="messenger-open"][data-platform="max"]').click()]);
   assert.equal(await page.locator('h1').textContent(),'Fictional navigation intercepted');assert.deepEqual(navigations,[links.max]);
   assert.deepEqual(calls.map(call=>call.action),['handoff_token','handoff_freeze','handoff_create'],'explicit MAX reuses the ready operation without a second CREATE');
   assert.equal(transport.calls.filter(call=>call.action==='quote_calculate').length,1);assert(!transport.calls.some(call=>/lead|booking|payment/.test(call.url)));
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   records.push({width,height,selector,amount:selected.price.amount,phoneChoiceOnly:true,sameFormNode:true,draftRetained:true,cancelledPendingNavigation:true,createCalls:1,externalNavigationIntercepted:navigations.length,shots});
  }catch(error){
   await page.screenshot({path:path.join(output,`failure-${width}x${height}.png`)}).catch(()=>{});
   fs.writeFileSync(path.join(output,`failure-${width}.json`),JSON.stringify({message:error.message,errors,forbidden,calls,shots},null,2));throw error;
  }finally{releaseCreate();await context.close();}
 }
 fs.writeFileSync(path.join(output,'receipt.json'),JSON.stringify({schema:1,compiled:!!process.env.SEARCH3_VISUAL_ASSET_ROOT,engine:'Chromium',source_sha:process.env.SEARCH3_SOURCE_SHA||null,asset_sha256:hashes,test_sha256:hash(__filename),records,transport:'fictional-intercepted-HTTP',supplier_HTTP:0,bot_HTTP:0,real_leads:0,installed:false,physical_device:false},null,2)+'\n');
 console.log('PASS compiled messenger430/1280-short: exact tour/explicit phone/same draft/pending intent/one create/intercepted navigation; supplier/bot/lead HTTP0');
};
