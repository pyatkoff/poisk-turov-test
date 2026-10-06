'use strict';
// Controlled eager/deferred comparison of the same compiled UI and fictional
// catalogue. Timings are observations, never a speed threshold or supplier test.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
module.exports=async function({browser,origin,base,evidence}){
 const records=[];
 for(const width of [390,1280])for(let trial=0;trial<5;trial++)for(const mode of trial%2?['deferred','eager']:['eager','deferred']){
  const transport=fixture(),context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[],forbidden=[],scripts=[];page.setDefaultTimeout(10000);
  page.on('pageerror',e=>errors.push(e.message));page.on('request',request=>{if(request.resourceType()==='script')scripts.push(new URL(request.url()).pathname);});
  await page.addInitScript(()=>{
   const observer=new MutationObserver(()=>{
    const form=document.querySelector('#search-form'),button=document.querySelector('.search-submit');
    if(window.AnyTourPrototypeData&&form?.getAttribute('aria-busy')==='false'&&button&&!button.disabled){window.__initialReadyMs=performance.now();observer.disconnect();}
   });observer.observe(document,{subtree:true,childList:true,attributes:true,attributeFilter:['aria-busy','disabled']});
  });
  await page.route('**/*',async route=>{
   const request=route.request(),url=new URL(request.url());
   if(url.origin===origin&&url.pathname.startsWith(base)&&!url.pathname.includes('/data/')){await route.continue();return;}
   if(request.resourceType()==='image'){await route.abort();return;}
   try{await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(await transport.json(request.url(),{body:request.postData()}))});}
   catch(error){forbidden.push(error.message);await route.abort();}
  });
  try{
   await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:'',loadingMode:mode}));
   await page.waitForFunction(()=>Number.isFinite(window.__initialReadyMs));
   assert.equal(scripts.filter(src=>src.endsWith('/flight-picker-ui-v1.js')).length,mode==='eager'?1:0,'controlled initial picker request count');
   assert.equal(transport.calls.filter(call=>call.action==='search_start'||call.url.includes('/api-anex-')||call.url.includes('/api-andromeda-')).length,0,'catalogue bootstrap starts no supplier search');
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
   const observation=await page.evaluate(()=>{
    const navigation=performance.getEntriesByType('navigation')[0],resources=performance.getEntriesByType('resource').filter(r=>new URL(r.name).pathname.endsWith('.js'));
    return {ready_ms:window.__initialReadyMs,dom_content_loaded_ms:navigation.domContentLoadedEventEnd,scripts:resources.map(r=>({path:new URL(r.name).pathname,bytes:r.decodedBodySize,duration_ms:r.duration})),pristine:document.querySelector('#results').classList.contains('results-pristine'),overflow:document.documentElement.scrollWidth>innerWidth};
   });assert(observation.pristine);assert(!observation.overflow);
   records.push({width,trial,mode,...observation,supplier_requests:0,real_leads:0});
  }finally{await context.close();}
 }
 const median=values=>[...values].sort((a,b)=>a-b)[Math.floor(values.length/2)],summary=[];
 for(const width of [390,1280]){
  const eager=records.filter(r=>r.width===width&&r.mode==='eager'),deferred=records.filter(r=>r.width===width&&r.mode==='deferred');
  const bytes=rows=>rows.map(row=>row.scripts.reduce((sum,r)=>sum+r.bytes,0));
  assert(bytes(eager).every(n=>n===bytes(eager)[0])&&bytes(deferred).every(n=>n===bytes(deferred)[0]),'same compiled bytes across trials');
  const cold=eager[0].scripts.find(r=>r.path.endsWith('/flight-picker-ui-v1.js'));assert(cold.bytes>0);assert.equal(bytes(eager)[0]-bytes(deferred)[0],cold.bytes,'exact initial bytes removed equal the cold owner');
  summary.push({width,trials:5,eager_initial_js:bytes(eager)[0],deferred_initial_js:bytes(deferred)[0],removed_initial_js:cold.bytes,eager_ready_median_ms:median(eager.map(r=>r.ready_ms)),deferred_ready_median_ms:median(deferred.map(r=>r.ready_ms))});
 }
 const receipt={comparison:'same compiled candidate, eager versus deferred flight UI; alternating order, fresh contexts, identical fictional catalogue',engine:'Chromium',physical_device:false,latency_claim:false,summary,records};
 fs.writeFileSync(path.join(evidence,'initial-loading.json'),JSON.stringify(receipt,null,2)+'\n');console.log('PASS compiled initial loading',JSON.stringify(summary));return receipt;
};
