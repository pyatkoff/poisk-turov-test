'use strict';
// Compiled UI, retained fictional catalogues only. This accepts visible recovery
// before any search; it does not measure live availability or a physical device.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
module.exports=async function({browser,origin,base,evidence}){
 const records=[],started=Date.now();
 const scenario=async(width,stress=false)=>{
  const transport=fixture(),context=await browser.newContext({viewport:{width,height:stress?650:900}}),page=await context.newPage(),errors=[],forbidden=[];
  transport.state.countriesFailure='1';page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));
  const label=width+(stress?'-root200-reduced':''),actions=['departure','destination','dates','nights','guests'];
  const requested={...trip,to:new Date(Date.parse(trip.from+'T12:00:00Z')+3*86400000).toISOString().slice(0,10),minNights:6,maxNights:9,adults:3,ages:'0,12'};
  const countryReads=()=>transport.calls.filter(c=>c.url.endsWith('/search3-destination-read-v1.php')&&c.action==='countries').length;
  const assertPassive=()=>{
   assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0,'catalogue recovery never starts Tourvisor search');
   assert.equal(transport.calls.filter(c=>/api-(?:anex|andromeda)/.test(c.url)).length,0,'catalogue recovery never reaches direct suppliers');
   assert.equal(transport.calls.filter(c=>/lead|booking/.test(c.url)).length,0,'catalogue recovery never sends a lead');
   assert(transport.calls.every(c=>!['tour','flights','expand','offer','quote','quote_start','quote_calculate','quote_select_flights','additional_prices','search_continue'].includes(c.action)),'catalogue recovery never checks a tour or price');
  };
  const assertURL=async()=>{
   const url=new URL(page.url());
   for(const [key,value] of Object.entries(requested))assert.equal(url.searchParams.get(key),String(value),'exact requested '+key+' survives catalogue recovery');
   assert.equal(url.searchParams.has('searched'),false,'recovery never authorizes a search through URL');
  };
  const assertControls=async(disabled)=>{
   for(const action of actions)assert.equal(await page.locator('#search-form [data-action="'+action+'"]').isDisabled(),disabled,'catalogue gate on '+action);
   assert.equal(await page.locator('#search-form .search-submit').isDisabled(),disabled,'catalogue gate on search submit');
  };
  const failed=async(index)=>{
   const error=page.locator('#search-form #catalog-error'),retry=error.locator('[data-action="retry-catalog"]');
   await error.waitFor({state:'visible'});await retry.waitFor({state:'visible'});
   assert.equal(await error.getAttribute('role'),'alert','initial failure is an accessible form alert');
   assert.match(await error.textContent(),/Не удалось загрузить направления/);
   assert.equal(await page.locator('#search-form').getAttribute('aria-busy'),'false','failed catalogue is settled, not a perpetual busy state');
   await assertControls(true);assert.equal(await retry.isEnabled(),true,'visible recovery remains usable after failure');
   assert.equal(await error.locator('[data-action="retry-catalog"]').count(),1,'one recovery action belongs to the existing error owner');
   const geometry=await retry.evaluate(el=>{const r=el.getBoundingClientRect(),form=el.closest('#search-form').getBoundingClientRect();return{x:r.x,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height,formLeft:form.x,formRight:form.right,viewportWidth:innerWidth};});
   assert(geometry.width>=44&&geometry.height>=44,'catalogue retry has a44px hit target at '+label);
   assert(geometry.x>=geometry.formLeft-1&&geometry.right<=geometry.formRight+1&&geometry.right<=geometry.viewportWidth+1,'catalogue retry stays inside the form at '+label);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false,'failed form has no horizontal overflow at '+label);
   assert.equal(await page.locator('.hotel-card').count(),0,'catalogue error cannot masquerade as offers');
   assertPassive();await assertURL();
   await retry.scrollIntoViewIfNeeded();await page.screenshot({path:path.join(evidence,`catalog-recovery-${label}-failed${index}.png`)});
   return geometry;
  };
  let releaseRetry,markRetry;const retryReached=new Promise(resolve=>markRetry=resolve);
  try{
   if(stress)await page.addInitScript(()=>document.addEventListener('DOMContentLoaded',()=>document.documentElement.style.fontSize='200%'));
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
    if(request.resourceType()==='image'){await route.abort();return;}
    try{
     const action=url.searchParams.get('action')||(request.postData()?JSON.parse(request.postData()).action:null);
     if(url.pathname.endsWith('/search3-destination-read-v1.php')&&action==='countries'&&countryReads()===1)markRetry();
     const value=await transport.json(request.url(),{body:request.postData()});
     await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});
    }catch(error){forbidden.push(error.message);await route.abort();}
   });
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams(requested));
   const first=await failed(1);assert.equal(countryReads(),1,'one initial catalogue read');
   transport.state.countryGates['1']=new Promise(resolve=>releaseRetry=resolve);
   const retry=page.locator('#catalog-error [data-action="retry-catalog"]');
   await retry.click();await retryReached;
   assert.equal(await page.locator('#search-form').getAttribute('aria-busy'),'true','explicit retry exposes pending catalogue state');
   await assertControls(true);
   const retainedRetry=page.locator('#cards [data-action="retry-catalog"]');
   assert.equal(await retainedRetry.isDisabled(),true,'retained desktop recovery is disabled while the canonical read is pending');
   await retainedRetry.evaluate(el=>{if(!el.isConnected)throw Error('Retry fixture must target the still-attached delegated action');el.click();el.click();el.dispatchEvent(new MouseEvent('click',{bubbles:true}));});
   assert.equal(countryReads(),2,'duplicate clicks during one pending retry cannot replay the catalogue read');
   assertPassive();await assertURL();
   releaseRetry();releaseRetry=null;transport.state.countryGates['1']=null;
   const second=await failed(2);assert.equal(countryReads(),2,'the failed retry terminates without an automatic loop');
   transport.state.countriesFailure='';
   await page.locator('#catalog-error [data-action="retry-catalog"]').click();
   await page.waitForFunction(()=>document.querySelector('#search-form').getAttribute('aria-busy')==='false'&&!document.querySelector('#search-form .search-submit').disabled);
   await assertControls(false);await assertURL();assertPassive();
   assert.equal(countryReads(),3,'success belongs to the next explicit retry');
   assert.equal(await page.locator('#catalog-error').isVisible(),false,'successful canonical catalogue clears the existing error');
   assert.equal(await page.locator('#search-form [data-action="retry-catalog"]:visible').count(),0,'settled success removes recovery action');
   assert.equal(await page.locator('.hotel-card').count(),0,'recovered catalogue does not invent search results');
   assert.equal(await page.locator('#search-form [data-action="destination"]').getAttribute('aria-label'),'Направление: Турция','success uses the canonical destination label');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
   await page.locator('#search-form .search-submit').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(evidence,`catalog-recovery-${label}-ready.png`)});
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   records.push({width,height:stress?650:900,root200:stress,compiled:true,fictional_catalogue:true,initial_failure:true,explicit_retry_failure:true,duplicate_pending_reads:0,explicit_success:true,country_reads:countryReads(),requested,geometry:{first,second},supplier_HTTP:0,real_leads:0,physical_device:false});
  }finally{releaseRetry?.();await context.close();}
 };
 const jobs=[...[360,390,430,768,1280].map(width=>[width,false]),[390,true]];
 for(let i=0;i<jobs.length;i+=3){const outcomes=await Promise.allSettled(jobs.slice(i,i+3).map(args=>scenario(...args))),failures=outcomes.filter(r=>r.status==='rejected');if(failures.length)throw new AggregateError(failures.map(r=>r.reason),'Compiled catalogue recovery failed');}
 const receipt={published:false,compiled:true,live_data:false,engine:'Chromium',physical_device:false,duration_ms:Date.now()-started,records};
 fs.writeFileSync(path.join(evidence,'catalog-recovery.json'),JSON.stringify(receipt,null,2)+'\n');
 console.log('PASS compiled catalogue recovery',JSON.stringify({cases:records.length,duration_ms:receipt.duration_ms,five_widths:true,root200_reduced_height:true,retries_explicit:true,exact_trip_retained:true,supplier_HTTP:0,real_leads:0}));return receipt;
};
