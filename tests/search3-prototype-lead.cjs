'use strict';
// Existing lead owner + new presentation; all contacts/HTTP responses are fixtures.
const assert=require('node:assert/strict'),path=require('node:path'),http=require('node:http');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../v2');
const server=http.createServer((req,res)=>{res.setHeader('Content-Type','text/html');res.end('<!doctype html><html lang="ru"><body><div id="form"></div></body></html>');});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({headless:true});
 try{
  for(const preview of [true,false]){
   const page=await browser.newPage();let calls=0,reply='failure',received=[];
   await page.route('**/lead-fixture',async route=>{calls++;received.push(route.request().postDataJSON());await new Promise(r=>setTimeout(r,60));return route.fulfill({status:reply==='failure'?503:200,contentType:'application/json',body:JSON.stringify(reply==='failure'?{ok:false,error:'fixture_unavailable'}:{ok:true,duplicate:true,leadId:4242})});});
   await page.goto('http://127.0.0.1:'+server.address().port);
   await page.evaluate(preview=>{window.V2_CONFIG={leadApi:preview?'/preview-lead-disabled.php':'/lead-fixture'};},preview);
   for(const file of ['runtime-v3.js','lead-search-context.js','tour-controller-v4.js','lead-form-guard-v1.js'])await page.addScriptTag({path:path.join(root,file)});
   const contract=await page.evaluate(()=>{
    V2Runtime.setSearchId(123);
    const selection={tour:{id:'exact-1',price:120000,adults:2,childs:2,date:'28.09.2026',nights:7,meal:{name:'AI'},roomType:'STANDARD',placement:'2AD+2CH',operator:{name:'ANEX'},departure:{name:'Москва'},hotel:{name:'Тестовый отель',country:{name:'Турция'},region:{name:'Анталья'}}},flight:{price:{value:133500.5},fuelCharge:{value:0},forward:[{number:'TT 211',departure:{time:'14:00'},arrival:{time:'18:00'}}],backward:[{number:'TT 212',departure:{time:'16:00'}}]},searchId:123,search:{adults:2,childs:[0,17]}};
    window.fixtureSession=V2TourController.createLeadSession(selection);
    window.AnyTourPrototypeData={leadSession:()=>window.fixtureSession};
    selection.tour.id='mutated';selection.flight.price.value=999;selection.search.childs[0]=12;
    const fd=new FormData();fd.set('phone','+7 999 000-00-00');fd.set('consent','1');
    const p=fixtureSession.payload(fd);
    let rejectsProvider=false;try{V2TourController.createLeadSession({...selection,tour:{id:'offer_x',provider:'andromeda'}});}catch{rejectsProvider=true;}
    return {payload:p,rejectsProvider,currentTour:V2TourController.currentTour};
   });
   assert.equal(contract.payload.tourId,'exact-1');assert.equal(contract.payload.price,120000);assert.equal(contract.payload.flightPrice,133500.5);assert.equal(contract.payload.flightFuel,0);
   assert.deepEqual(contract.payload.childAges,[0,17]);assert.equal(contract.payload.childs,2);assert.equal(contract.payload.searchId,123);assert.match(contract.payload.flight,/TT 211/);
   assert.equal(contract.currentTour,null,'Independent presentation does not replace legacy selected state');assert.equal(contract.rejectsProvider,true);
   await page.addScriptTag({path:path.join(root,'prototype-search/lead.js')});
   await page.evaluate(()=>{document.getElementById('form').innerHTML=AnyTourPrototypeLead.markup()+AnyTourPrototypeLead.action();AnyTourPrototypeLead.bind({});});
   const phone=page.locator('[name="phone"]'),submit=page.locator('[type="submit"]');
   await phone.fill('123');await page.locator('[name="consent"]').check();await submit.click();
   assert.equal(await phone.evaluate(el=>el.validity.valid),false);assert.equal(calls,0);
   await phone.fill('+7 999 000-00-00');await page.locator('[name="name"]').fill('Тестовый контакт');await page.locator('[name="comment"]').fill('Позвонить после 18:00');
   await page.evaluate(()=>document.querySelector('form').requestSubmit());
   if(preview){
    assert.equal(await page.locator('form').getAttribute('data-checked'),'1');assert.match(await page.locator('.lead-message').textContent(),/не отправлена/);assert.equal(calls,0);
   }else{
    await page.evaluate(()=>document.querySelector('form').requestSubmit());
    await page.waitForFunction(()=>document.querySelector('.lead-message').textContent.includes('Не удалось'));
    assert.equal(calls,1,'Pending duplicate is not sent');assert.equal(await submit.isEnabled(),true);assert.equal(await phone.inputValue(),'+7 999 000-00-00');
    reply='duplicate';await submit.click();await page.waitForFunction(()=>document.querySelector('form').dataset.sent==='1');assert.equal(calls,2);assert.match(await page.locator('.lead-message').textContent(),/4242/);
    await page.evaluate(()=>fixtureSession.submit(document.querySelector('form'),{button:document.querySelector('[type=submit]')}));assert.equal(calls,2,'Successful session is not sent twice');
    assert.equal(received[1].tourId,'exact-1');assert.equal(received[1].flightPrice,133500.5);assert.deepEqual(received[1].childAges,[0,17]);
   }
   const stale=await page.evaluate(()=>{V2Runtime.setSearchId(456);try{fixtureSession.payload(new FormData());return false;}catch{return true;}});assert.equal(stale,true);
   if(preview){
    await page.evaluate(()=>{document.getElementById('form').innerHTML=AnyTourPrototypeLead.markup()+AnyTourPrototypeLead.action();AnyTourPrototypeLead.bind({});});
    assert.equal(await page.locator('[name="phone"]').inputValue(),'+7 999 000-00-00');assert.equal(await page.locator('[name="consent"]').isChecked(),false,'Consent is not silently reused on a new render');
    await page.locator('[name="consent"]').check();await page.locator('[type="submit"]').click();assert.match(await page.locator('.lead-message').textContent(),/поиска изменились/);assert.equal(calls,0);
   }
   console.log(JSON.stringify({preview,fixtureLeadCalls:calls,status:'passed'}));await page.close();
  }
 }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
