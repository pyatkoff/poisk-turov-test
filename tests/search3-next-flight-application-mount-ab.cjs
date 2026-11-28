'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {createHash}=require('node:crypto');
const {gzipSync}=require('node:zlib');
const {execFileSync}=require('node:child_process');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');

const release='46760aed5c3e0cd3573e85f350c0ca969395304d';
const source='deb670a48b8ff3aa0b86b366124a10124a918f32';
const rawHash='76008646a83926fff1a6737e366e974101bd618ada8c4565affc4634545e29bc';
const appHash='a2f5b906a953364e735988eb055eb5b370cca5f1c675913ae0d1a2e0b663c633';
const candidateHash='0866042091c62d0a5f7e09254f9e8d29bdd12882a6a60a39c192a4483ac6780c';
const cssHash='0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const changes=[
  [
    "function modalBack(){",
    "function modalBack(renderSelectedTour=null){"
  ],
  [
    " restoringModal=true;showModal(previous.type,previous.title,previous.kicker,previous.body,previous.className==='wide-dialog');",
    " restoringModal=true;\n if(previous.type==='selected-tour'&&renderSelectedTour){\n  gallery=previous.gallery;\n  try{renderSelectedTour();}finally{restoringModal=false;}\n  $('#modal-title').focus({preventScroll:true});rememberUIRoute();return true;\n }\n showModal(previous.type,previous.title,previous.kicker,previous.body,previous.className==='wide-dialog');"
  ],
  [
    " flightDraft=null;modalBack();selectedOffer=next;\n if(next.provider==='tourvisor'&&!next.loading&&!next.flightsLoading&&data.amount(next.tour?.price)>0&&!needsRefresh(next)&&!next.quoteError)rememberProviderView(next,'tourvisor-selection');\n if(contactReturn)openLeadPreview(next);else renderOfferProgress();",
    " flightDraft=null;\n const renderAppliedSelection=()=>{\n  selectedOffer=next;\n  if(next.provider==='tourvisor'&&!next.loading&&!next.flightsLoading&&data.amount(next.tour?.price)>0&&!needsRefresh(next)&&!next.quoteError)rememberProviderView(next,'tourvisor-selection');\n  if(contactReturn)openLeadPreview(next);else renderOfferProgress();\n };\n if(!modalBack(contactReturn?renderAppliedSelection:null))renderAppliedSelection();"
  ]
];
const sha=value=>createHash('sha256').update(value).digest('hex');
const screens=[{width:430,height:932,dpr:3,touch:true},{width:1280,height:720,dpr:1,touch:false}];
const future=days=>new Date(Date.now()+days*86400000).toISOString().slice(0,10);
const query=new URLSearchParams({scenario:'family',searched:'1',origin:'Москва',country:'4',from:future(14),to:future(20),minNights:'7',maxNights:'7',
  adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
const historyKey='anytour.prototype.v18.ui.v1';
const amount=async locator=>Number((await locator.textContent()).replace(/\D/g,''));
const settle=page=>page.evaluate(()=>new Promise(done=>requestAnimationFrame(()=>requestAnimationFrame(done))));

function observeApply(){
  const body=document.querySelector('#modal-body');
  const result=window.flightApplicationTiming={mounts:0};
  const observer=new MutationObserver(records=>{
    for(const record of records)for(const node of record.addedNodes){
      if(record.target===body&&node.matches?.('.application-review-card'))result.mounts++;
    }
    const total=body.querySelector('.application-price-breakdown [data-price-part="total"] dd');
    if(!total||Number(total.textContent.replace(/\D/g,''))!==189000)return;
    result.dom=performance.now()-result.start;
    observer.disconnect();
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
      result.frame=performance.now()-result.start;
      result.active=document.activeElement?.id||document.activeElement?.dataset.action||document.activeElement?.tagName||null;
      result.scroll=body.scrollTop;
    }));
  });
  function begin(event){
    if(!event.isTrusted||!event.target.closest('[data-action="apply-flight"]'))return;
    result.start=performance.now();
    observer.observe(body,{childList:true,subtree:true,characterData:true});
    document.removeEventListener('click',begin,true);
    queueMicrotask(()=>{result.handler=performance.now()-result.start;});
  }
  document.addEventListener('click',begin,true);
}

async function browserSample(browser,server,searchPath,screen,cpu,variant,exercise){
  const context=await browser.newContext({viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,hasTouch:screen.touch,isMobile:screen.touch,
    reducedMotion:'reduce',serviceWorkers:'block',proxy:{server:server.origin,bypass:'127.0.0.1'}});
  const page=await context.newPage(),wrong=[],errors=[];
  page.on('request',request=>{const url=new URL(request.url());if(url.origin!==server.origin||request.method()!=='GET')wrong.push([request.method(),url.pathname]);});
  page.on('pageerror',error=>errors.push(error.message));
  const action=locator=>screen.touch?locator.tap():locator.click();
  try{
    const client=await context.newCDPSession(page);
    await client.send('Emulation.setCPUThrottlingRate',{rate:cpu});
    assert.equal((await page.goto(server.origin+searchPath+'?'+query,{waitUntil:'domcontentloaded'})).status(),200);
    const entry=page.locator('#hotel-900001 h3 [data-action="hotel-details"]');
    await entry.waitFor({state:'visible'});
    assert.deepEqual(await page.evaluate(()=>[innerWidth,innerHeight,devicePixelRatio,navigator.maxTouchPoints>0,window.V2_CONFIG?.api,window.V2_CONFIG?.anexApi]),
      [screen.width,screen.height,screen.dpr,screen.touch,null,null]);
    await page.evaluate(()=>document.fonts.ready);
    await action(entry);
    await action(page.locator('#modal[open] [data-action="select-hotel-offer"][data-key="demo-flight-24"]'));
    const application=page.locator('#modal[open] .application-review-card'),footer=page.locator('#modal-footer .footer-total>strong');
    await application.waitFor();
    assert.equal(await amount(footer),186400);
    await action(page.locator('#modal[open] [data-action="contact-flights"]'));
    const flight=page.locator('#tour-flight-options [name="flight-pair"][value="2"]');
    await action(flight);
    assert.equal(await flight.isChecked(),true);
    const apply=page.locator('#modal[open] [data-action="apply-flight"]');
    await apply.scrollIntoViewIfNeeded();
    await settle(page);
    await page.evaluate(observeApply);
    await action(apply);
    await page.waitForFunction(()=>Number.isFinite(window.flightApplicationTiming?.frame),null,{timeout:12000});
    const performance=await page.evaluate(()=>window.flightApplicationTiming);
    assert(performance.dom>=0&&performance.frame>=performance.dom);
    assert.equal(performance.mounts,variant==='before'?2:1);
    assert.equal(performance.scroll,0);
    if(variant==='after')assert.equal(performance.active,'modal-title');
    const breakdown=page.locator('#modal .application-price-breakdown');
    const parts=await Promise.all(['tour','flight','total'].map(key=>amount(breakdown.locator('[data-price-part="'+key+'"] dd'))));
    assert.deepEqual(parts,[186400,2600,189000]);
    assert.equal(await amount(footer),189000);
    assert.equal(await page.evaluate(key=>history.state?.[key]?.type,historyKey),'selected-tour');
    if(exercise){
      const messenger=page.locator('#messenger-entry button[data-platform]').first();
      await messenger.waitFor();
      assert.match(await page.locator('#messenger-status').textContent(),/Заявки не отправляются/);
      const output=exercise;
      await page.screenshot({path:path.join(output,'canonical-next-flight-mount-'+variant+'-'+screen.width+'.png'),animations:'disabled'});
      await action(page.locator('#messenger-entry [data-action="modal-back"]'));
      const retained=page.locator('#modal[open] [data-action="select-hotel-offer"][data-key="demo-flight-24"][aria-pressed="true"]');
      const offerTotal=page.locator('#modal[open] #detail-total');
      await retained.or(offerTotal).first().waitFor();
      if(await offerTotal.isVisible())assert.equal(await amount(offerTotal),189000);
    }
    assert.deepEqual(wrong,[]);
    assert.deepEqual(errors,[]);
    assert.deepEqual(server.rejected,[]);
    return performance;
  }finally{await context.close();}
}

const median=values=>{const s=[...values].sort((a,b)=>a-b),mid=s.length/2;return (s[mid-1]+s[mid])/2;};
function summarize(samples){
  return Object.fromEntries(['handler','dom','frame'].map(metric=>{
    const before=samples.map(s=>s.before[metric]),after=samples.map(s=>s.after[metric]);
    const delta=before.map((value,i)=>after[i]-value);
    return [metric,{before:median(before),after:median(after),paired:median(delta),faster:delta.filter(value=>value<0).length,min:Math.min(...delta),max:Math.max(...delta)}];
  }));
}

module.exports=async function compareFlightApplicationMount(browser,nextHome,output){
  const exact=exactAssets(nextHome),root=path.resolve(__dirname,'..');
  assert.equal(exact.appSha,appHash);assert.equal(exact.sheetSha,cssHash);
  assert.deepEqual(execFileSync('git',['diff','--name-only',release,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort(),
    ['tests/search3-entry-owner-browser.cjs','tests/search3-next-flight-application-mount-ab.cjs']);
  const raw=execFileSync('git',['show',source+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
  assert.equal(sha(raw),rawHash);
  assert.equal(sha(await compile(raw,{privateFunctions:true})),appHash);
  let patched=raw;
  for(const [before,after] of changes){
    assert.equal(patched.split(before).length,2,before);
    patched=patched.replace(before,after);
  }
  const code=await compile(patched,{privateFunctions:true});
  assert.equal(sha(code),candidateHash);
  const appUrl=exact.appUrl.replace(appHash.slice(0,12),candidateHash.slice(0,12));
  assert.notEqual(appUrl,exact.appUrl);
  const update=html=>html.replace(exact.appUrl,appUrl);
  const candidateAsset={...exact,code,appSha:candidateHash,appUrl,home:update(exact.home),search:update(exact.search)};
  const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const baseline=await startServer(nextHome,exact,paths),groups=[];
  try{
    const candidate=await startServer(nextHome,candidateAsset,paths);
    try{
      for(const screen of screens)for(const cpu of [1,4]){
        const before=exercise=>browserSample(browser,baseline,paths.search,screen,cpu,'before',exercise);
        const after=exercise=>browserSample(browser,candidate,paths.search,screen,cpu,'after',exercise);
        const warmup={before:await before(cpu===1?output:null),after:await after(cpu===1?output:null)};
        const samples=[];
        for(let i=0;i<6;i++){
          if(i%2===0)samples.push({first:'before',before:await before(),after:await after()});
          else {const sample=await after();samples.push({first:'after',before:await before(),after:sample});}
        }
        const summary=summarize(samples);
        console.log('SEARCH3_FLIGHT_MOUNT_AB '+JSON.stringify({screen,cpu,summary}));
        groups.push({screen,cpu,warmup,samples,summary});
      }
      fs.writeFileSync(path.join(output,'canonical-next-flight-application-mount-ab.json'),JSON.stringify({
        runner:process.env.SEARCH3_SOURCE_SHA,release,source,appHash,candidateHash,cssHash,bytes:[Buffer.byteLength(exact.code),Buffer.byteLength(code)],
        gzip:[gzipSync(exact.code).length,gzipSync(code).length],browser:browser.version(),groups
      },null,2)+'\n');
    }finally{await candidate.close();}
  }finally{await baseline.close();}
};
