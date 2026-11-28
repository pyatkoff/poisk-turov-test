'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {createHash}=require('node:crypto');
const {gzipSync}=require('node:zlib');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const {execFileSync}=require('node:child_process');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');

const release='9ab37eac3d5ecb663cc93fee065642c98cc91012',source='3c8da1b8fdf5cfa0f95e207ad9ac006fe384a2cd';
const appSha='0866042091c62d0a5f7e09254f9e8d29bdd12882a6a60a39c192a4483ac6780c';
const cssSha='0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const adapterSha='b1150386a2a6e71f4f2a71441ab6c2c84bcf4810bd6c778f2268a6abda280c3a';
const screens=[{width:430,height:932,dpr:3,touch:true},{width:1280,height:720,dpr:1,touch:false}];
const profiles=[{mbps:2,rtt:80,cpu:1},{mbps:10,rtt:40,cpu:1},{mbps:2,rtt:80,cpu:4}];
const future=days=>new Date(Date.now()+days*86400000).toISOString().slice(0,10);
const query=new URLSearchParams({scenario:'family',searched:'1',origin:'Москва',country:'4',from:future(14),to:future(20),minNights:'7',maxNights:'7',
  adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
const sha=bytes=>createHash('sha256').update(bytes).digest('hex');
const amount=async locator=>Number((await locator.textContent()).replace(/\D/g,''));
const settle=page=>page.evaluate(()=>new Promise(done=>requestAnimationFrame(()=>requestAnimationFrame(done))));

function markFixtureFlights(){
  const records=window.flightOpeningDataCalls=[];
  let adapter;
  Object.defineProperty(window,'AnyTourPrototypeData',{configurable:true,get:()=>adapter,set(data){
    const descriptors=Object.getOwnPropertyDescriptors(data), original=descriptors.flights?.value;
    if(typeof original!=='function'){adapter=data;return;}
    descriptors.flights={...descriptors.flights,value:function(...args){
      const call={start:performance.now()};
      records.push(call);
      return Promise.resolve(Reflect.apply(original,this,args)).then(result=>{
        call.end=performance.now();
        call.count=result?.length??null;
        return result;
      });
    }};
    adapter=Object.freeze(Object.create(Object.getPrototypeOf(data),descriptors));
  }});
}

function observeOpen(modulePath){
  const body=document.querySelector('#modal-body'),footer=document.querySelector('#modal-footer');
  const result=window.flightOpeningProbe={shellMounts:0};
  const observer=new MutationObserver(records=>{
    for(const record of records)for(const node of record.addedNodes){
      if(record.target===body&&node.matches?.('[role="status"]')&&node.textContent.includes('Загружаем варианты перелёта'))result.shellMounts++;
    }
    if(!Number.isFinite(result.shell)&&document.querySelector('#modal-title')?.textContent==='Выберите перелёт')result.shell=performance.now()-result.start;
    const items=body.querySelectorAll('#tour-flight-options [name="flight-pair"]');
    const choose=footer.querySelector('[data-action="apply-flight"]');
    if(items.length!==3||[...items].some(option=>option.disabled)||!choose||footer.hidden)return;
    result.applyEnabled=choose.disabled===false;
    result.dom=performance.now()-result.start;
    observer.disconnect();
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
      result.frame=performance.now()-result.start;
      result.focus=document.activeElement?.id||document.activeElement?.dataset.action||null;
      result.focusInside=document.querySelector('#modal').contains(document.activeElement);
      const first=items[0].closest('label')||items[0];
      const rect=first.getBoundingClientRect();
      result.firstVisible=rect.bottom>0&&rect.top<innerHeight&&rect.width>0&&rect.height>0;
      const entry=performance.getEntriesByType('resource').find(x=>new URL(x.name).pathname===modulePath);
      if(entry)result.asset={
        start:entry.startTime-result.start,requestStart:entry.requestStart-result.start,
        responseStart:entry.responseStart-result.start,end:entry.responseEnd-result.start,
        duration:entry.duration,encoded:entry.encodedBodySize,decoded:entry.decodedBodySize
      };
      result.flights=window.flightOpeningDataCalls.filter(x=>x.start>=result.start).map(x=>({
        start:x.start-result.start,end:x.end-result.start,count:x.count
      }));
    }));
  });
  function begin(event){
    if(!event.isTrusted||!event.target.closest('#modal [data-action="contact-flights"]'))return;
    result.start=performance.now();
    observer.observe(body,{childList:true,subtree:true,characterData:true});
    observer.observe(footer,{childList:true,attributes:true,subtree:true});
    document.removeEventListener('click',begin,true);
  }
  document.addEventListener('click',begin,true);
}

async function sample(browser,server,paths,screen,profile,moduleSha,warmup){
  const context=await browser.newContext({viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,hasTouch:screen.touch,isMobile:screen.touch,
    reducedMotion:'reduce',serviceWorkers:'block',proxy:{server:server.origin,bypass:'127.0.0.1'}});
  const page=await context.newPage(),wrong=[],errors=[],requests=[];
  const modulePath=paths.home+'visual-search/flight-picker-ui-v1.js';
  page.on('request',request=>{
    const url=new URL(request.url());
    if(url.origin!==server.origin||request.method()!=='GET')wrong.push([request.method(),url.pathname]);
    if(url.pathname===modulePath)requests.push(url.searchParams.get('v'));
  });
  page.on('pageerror',error=>errors.push(error.message));
  const action=locator=>screen.touch?locator.tap():locator.click();
  try{
    await page.addInitScript(markFixtureFlights);
    const client=await context.newCDPSession(page);
    await client.send('Emulation.setCPUThrottlingRate',{rate:profile.cpu});
    assert.equal((await page.goto(server.origin+paths.search+'?'+query,{waitUntil:'domcontentloaded'})).status(),200);
    const entry=page.locator('#hotel-900001 h3 [data-action="hotel-details"]');
    await entry.waitFor();
    assert.deepEqual(await page.evaluate(()=>[innerWidth,innerHeight,devicePixelRatio,navigator.maxTouchPoints>0,window.V2_CONFIG?.api,window.V2_CONFIG?.anexApi]),
      [screen.width,screen.height,screen.dpr,screen.touch,null,null]);
    await action(entry);
    await action(page.locator('#modal[open] [data-action="select-hotel-offer"][data-key="demo-flight-24"]'));
    const footer=page.locator('#modal-footer .footer-total>strong'),choice=page.locator('#modal[open] [data-action="contact-flights"]');
    await choice.waitFor();
    assert.equal(await amount(footer),186400);
    await page.evaluate(()=>document.fonts.ready);
    if(warmup)await page.waitForLoadState('networkidle');
    const noPickerAtApplication=await page.evaluate(()=>!window.AnyTourFlightPickerUIV1);
    assert(noPickerAtApplication&&requests.length===0);
    const speed=profile.mbps*1000000/8;
    await client.send('Network.enable');
    await client.send('Network.emulateNetworkConditions',{offline:false,latency:profile.rtt,downloadThroughput:speed,uploadThroughput:speed});
    async function open(cold){
      await choice.scrollIntoViewIfNeeded();
      await settle(page);
      await page.evaluate(observeOpen,modulePath);
      await action(choice);
      try{await page.waitForFunction(()=>Number.isFinite(window.flightOpeningProbe?.frame),null,{timeout:12000});}
      catch(error){
        const state=await page.evaluate(()=>({
          stage:window.flightOpeningProbe,hasModule:!!window.AnyTourFlightPickerUIV1,
          options:document.querySelectorAll('#tour-flight-options [name="flight-pair"]').length,
          applyDisabled:document.querySelector('#modal [data-action="apply-flight"]')?.disabled,
        }));
        throw new Error(JSON.stringify({screen,profile,cold,requests,errors,state}),{cause:error});
      }
      const data=await page.evaluate(()=>window.flightOpeningProbe);
      assert(data.dom>=data.shell&&data.frame>=data.dom);
      assert.equal(await page.locator('#tour-flight-options [name="flight-pair"][value="2"]').isEnabled(),true);
      assert.equal(await page.locator('#modal[open] [data-action="apply-flight"]').isVisible(),true);
      assert.equal(data.focusInside,true);
      if(cold)assert.equal(data.focus,'modal-title');
      assert.deepEqual(requests,[moduleSha.slice(0,12)]);
      if(cold)assert(data.asset.start>=0&&data.asset.end>=data.asset.start&&data.asset.encoded>0&&data.asset.encoded<data.asset.decoded);
      else assert(data.asset.end<0);
      const cancel=page.locator('#modal-footer [data-action="modal-back"]');
      await action(cancel);
      await choice.waitFor();
      assert.equal(await amount(footer),186400);
      return data;
    }
    const cold=await open(true),repeat=await open(false);
    assert.deepEqual(wrong,[]);
    assert.deepEqual(errors,[]);
    assert.deepEqual(server.rejected,[]);
    return {cold,repeat,noPickerAtApplication,moduleRequests:requests.length};
  }finally{await context.close();}
}

const candidateSha='c3659db6681c6aca34534df186bc9683f6bcbffe80a90dbb0842139814a94e06';
const rawSha='c7cfa9e8eef7c25075e0113ee3bc7f3bfb49328d691e2348de060e2319287b1b';
const changes=[
  [
    "  window.AnyTourPublicAnalyticsV1?.flight(selectedOffer,flightPairFor(selectedOffer),Number(selectedOffer.flightChoiceId));\n  renderOfferProgress();\n  if(chooseFlight&&variants.length)openFlightPicker();",
    "  const picker=variants.length&&selectedOffer.contactFlightEditing&&!window.AnyTourFlightPickerUIV1?loadFlightPicker():null;\n  picker?.catch(()=>{});\n  window.AnyTourPublicAnalyticsV1?.flight(selectedOffer,flightPairFor(selectedOffer),Number(selectedOffer.flightChoiceId));\n  renderOfferProgress();\n  if(chooseFlight&&variants.length)openFlightPicker();\n  if(picker)return {picker};"
  ],
  [
    " const run=++selectionGeneration;selectedOffer={...o,contactFlightEditing:true};\n await loadRealFlights(run,{preserveChoice:true});",
    " const run=++selectionGeneration;selectedOffer={...o,contactFlightEditing:true};\n const prepared=await loadRealFlights(run,{preserveChoice:true});"
  ],
  [
    " if(selectedOffer.variants?.length)openFlightPicker();else renderContactFlightProgress();",
    " if(selectedOffer.variants?.length)openFlightPicker(null,prepared?.picker);else renderContactFlightProgress();"
  ],
  [
    "function openFlightPicker(restore=null){",
    "function openFlightPicker(restore=null,pendingPicker=null){"
  ],
  [
    " $('#modal-title').tabIndex=-1;$('#modal-title').focus({preventScroll:true});$('#modal-body').scrollTop=0;\n renderFlightPicker();",
    " $('#modal-title').tabIndex=-1;$('#modal-title').focus({preventScroll:true});$('#modal-body').scrollTop=0;\n renderFlightPicker(pendingPicker);"
  ],
  [
    "function renderFlightPicker(){",
    "function renderFlightPicker(pendingPicker=null){"
  ],
  [
    " loadFlightPicker().then(paint,()=>{if(current())$('#modal-body').innerHTML=",
    " (pendingPicker||loadFlightPicker()).then(paint,()=>{if(current())$('#modal-body').innerHTML="
  ]
];

const median=values=>{const a=[...values].sort((x,y)=>x-y);return (a[a.length/2-1]+a[a.length/2])/2;};
const metrics={assetStart:r=>r.asset.start,assetEnd:r=>r.asset.end,dom:r=>r.dom,frame:r=>r.frame};
function summarize(samples){
 return Object.fromEntries(['cold','repeat'].map(stage=>[stage,Object.fromEntries(
  Object.entries(metrics).filter(([key])=>stage==='cold'||!key.startsWith('asset')).map(([key,metric])=>{
   const a=samples.map(s=>metric(s.before[stage])),b=samples.map(s=>metric(s.after[stage]));
   const delta=a.map((x,i)=>b[i]-x);
   return [key,{before:median(a),after:median(b),paired:median(delta),faster:delta.filter(x=>x<0).length,min:Math.min(...delta),max:Math.max(...delta)}];
  })
 )]));
}

module.exports=async function compareFlightPickerAfterData(browser,nextHome,output){
 const exact=exactAssets(nextHome),root=path.resolve(__dirname,'..');
 assert.equal(exact.appSha,appSha);assert.equal(exact.sheetSha,cssSha);
 assert.deepEqual(execFileSync('git',['diff','--name-only',release,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort(),
  ['tests/search3-entry-owner-browser.cjs','tests/search3-next-flight-picker-after-data-ab.cjs']);
 const module=fs.readFileSync(path.join(nextHome.payload,'visual-search/flight-picker-ui-v1.js'));
 const adapter=fs.readFileSync(path.join(nextHome.payload,'prototype-search/data.js'));
 assert.equal(sha(adapter),adapterSha);assert.equal(module.length,12347);assert.equal(gzipSync(module).length,4518);
 const raw=execFileSync('git',['show',source+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
 assert.equal(sha(raw),rawSha);
 assert.equal(sha(await compile(raw,{privateFunctions:true})),appSha);
 let patched=raw;
 for(const [before,after] of changes){assert.equal(patched.split(before).length,2,before);patched=patched.replace(before,after);}
 const code=await compile(patched,{privateFunctions:true});
 assert.equal(sha(code),candidateSha);
 const appUrl=exact.appUrl.replace(appSha.slice(0,12),candidateSha.slice(0,12));
 assert.notEqual(appUrl,exact.appUrl);
 const HTML=s=>s.replace(exact.appUrl,appUrl);
 const candidate={...exact,code,appSha:candidateSha,appUrl,home:HTML(exact.home),search:HTML(exact.search)};
 const moduleSha=sha(module),paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const before=await startServer(nextHome,exact,paths),groups=[];
 try{
  const after=await startServer(nextHome,candidate,paths);
  try{
   for(const screen of screens)for(const profile of profiles){
    const A=warmup=>sample(browser,before,paths,screen,profile,moduleSha,warmup);
    const B=warmup=>sample(browser,after,paths,screen,profile,moduleSha,warmup);
    const warmup={before:await A(true),after:await B(true)},samples=[];
    for(let i=0;i<6;i++){
     if(i%2===0)samples.push({first:'before',before:await A(false),after:await B(false)});
     else {const b=await B(false);samples.push({first:'after',before:await A(false),after:b});}
    }
    const summary=summarize(samples);
    console.log('SEARCH3_FLIGHT_PICKER_AFTER_DATA_AB '+JSON.stringify({screen,profile,summary}));
    groups.push({screen,profile,warmup,samples,summary});
   }
   fs.writeFileSync(path.join(output,'canonical-next-flight-picker-after-data-ab.json'),JSON.stringify({
    runner:process.env.SEARCH3_SOURCE_SHA,release,source,appSha,candidateSha,cssSha,adapterSha,moduleSha,moduleGzip:gzipSync(module).length,
    gzip:[gzipSync(exact.code).length,gzipSync(code).length],browser:browser.version(),groups
   },null,2)+'\n');
  }finally{await after.close();}
 }finally{await before.close();}
};
