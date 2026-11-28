'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { exactAssets, startServer } = require('./search3-next-compact-calendar.cjs');
const { compile } = require('../scripts/build/search3-js/visual-entry.cjs');

const sourceCommit = 'deb670a48b8ff3aa0b86b366124a10124a918f32';
const releaseCommit = '46760aed5c3e0cd3573e85f350c0ca969395304d';
const rawApp = '76008646a83926fff1a6737e366e974101bd618ada8c4565affc4634545e29bc';
const compiledApp = 'a2f5b906a953364e735988eb055eb5b370cca5f1c675913ae0d1a2e0b663c633';
const styles = '0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const screens = [{width:430,height:932,dpr:3,touch:true},{width:1280,height:720,dpr:1,touch:false}];
const offerCount = 250, groupCount = 25;
const today = new Date().toISOString().slice(0, 10);
const future = days => new Date(Date.parse(today + 'T12:00Z') + days * 86400000).toISOString().slice(0, 10);
const query = new URLSearchParams({
  scenario: 'family', searched: '1', origin: 'Москва', country: '4',
  from: future(14), to: future(20), minNights: '7', maxNights: '7',
  adults: '3', ages: '0,12', stars: '4|5', meals: 'Всё включено', max: '240000',
});
const sha256 = value => createHash('sha256').update(value).digest('hex');
const offerSha = '0af11129916d635f2f40f502ebedc5422c2b32cac545a7e89614ebc3dcdb9bb2';
const routeKey = 'anytour.prototype.v18.ui.v1';

function changeWarmOpen(source) {
  const changes = [
    [
      "function showModal(type,title,kicker,body,wide=false){",
      "function showModal(type,title,kicker,body,wide=false,{rememberOnOpen=true}={}){"
    ],
    [
      "queueMicrotask(rememberUIRoute);queueMicrotask(refreshProviderQuoteExpiry);",
      "if(rememberOnOpen)queueMicrotask(rememberUIRoute);queueMicrotask(refreshProviderQuoteExpiry);"
    ],
    [
      " showModal('all-offers',h.name,'ВЫБЕРИТЕ КОНКРЕТНЫЙ ТУР','<div id=\"all-offers-list\" aria-live=\"polite\"></div>',true);",
      " showModal('all-offers',h.name,'ВЫБЕРИТЕ КОНКРЕТНЫЙ ТУР','<div id=\"all-offers-list\" aria-live=\"polite\"></div>',true,{rememberOnOpen:!window.AnyTourOfferList?.create});"
    ]
  ];
  for (const [before, after] of changes) {
    assert.equal(source.split(before).length, 2, before);
    source = source.replace(before, after);
  }
  return source;
}

function fixture({path,variantCount,rooms}) {
  if (location.pathname !== path) return;
  const proof=window.denseOfferFixture={searches:0,seeded:0};
  let adapter;
  Object.defineProperty(window,'AnyTourPrototypeData',{configurable:true,get:()=>adapter,set(data){
    const descriptors=Object.getOwnPropertyDescriptors(data),original=descriptors.search?.value;
    if(typeof original!=='function'){adapter=data;return;}
    descriptors.search={...descriptors.search,value:function(query,emit,...args){
      proof.searches++;
      return Reflect.apply(original,this,[query,event=>{
        if(event?.type!=='results')return emit(event);
        const hotel=event.hotels.find(h=>h.id===900001);
        const seed=hotel?.offers?.[0];
        if(!seed)throw Error('Dense offer fixture is missing the family seed');
        proof.seeded++;
        const variants=Array.from({length:variantCount},(_,i)=>({
          ...seed,key:seed.key+':dense:'+i,variant:i,total:seed.total+i*100,
          room:'Учебный номер '+String(Math.floor(i/(variantCount/rooms))+1).padStart(2,'0'),
        }));
        return emit({...event,hotels:event.hotels.map(row=>row===hotel?{...hotel,offers:variants}:row)});
      },...args]);
    }};
    adapter=Object.freeze(Object.create(Object.getPrototypeOf(data),descriptors));
  }});
}

function observe() {
  const modal=document.querySelector('#modal'),selector='#hotel-900001 [data-action="all-offers"]';
  const run=window.denseOffersProbe={};
  function start(event){
    if(!event.isTrusted||!event.target.closest(selector))return;
    run.event=event;run.start=performance.now();
    observer.observe(modal,{attributes:true,childList:true,subtree:true,characterData:true});
    document.removeEventListener('click',start,true);
    queueMicrotask(check);
  }
  function tail(event){
    if(event!==run.event)return;
    run.dispatch=performance.now()-run.start;
    delete run.event;
    document.removeEventListener('click',tail);
  }
  function check(){
    if(run.start===undefined||run.ready!==undefined)return;
    if(modal.open&&!Number.isFinite(run.shell))run.shell=performance.now()-run.start;
    const count=document.querySelector('#offer-count')?.textContent??'';
    if(!modal.open||!/^250 /.test(count)||!modal.querySelector('#all-offers-list .grouped-offer [data-action="offer"]'))return;
    run.ready=performance.now()-run.start;
    observer.disconnect();
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
      run.frame=performance.now()-run.start;run.finished=true;
    }));
  }
  const observer=new MutationObserver(check);
  document.addEventListener('click',start,true);
  document.addEventListener('click',tail);
}

const median=values=>{const ordered=[...values].sort((a,b)=>a-b),mid=Math.floor(ordered.length/2);return ordered.length%2?ordered[mid]:(ordered[mid-1]+ordered[mid])/2;};
const waitFrame=page=>page.evaluate(()=>new Promise(done=>requestAnimationFrame(()=>requestAnimationFrame(done))));

async function runPair(browser,server,searchPath,screen,cpu,exercise){
  const context=await browser.newContext({viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,hasTouch:screen.touch,isMobile:screen.touch,
    reducedMotion:'reduce',serviceWorkers:'block',proxy:{server:server.origin,bypass:'127.0.0.1'}});
  const page=await context.newPage(),wrong=[],errors=[];
  let moduleLoads=0;
  const file=new URL(searchPath.replace(/poisk-turov\/$/,'visual-search/offer-list-v1.js'),server.origin);
  page.on('request',request=>{
    const url=new URL(request.url());
    if(url.origin!==server.origin)wrong.push(url.pathname);
    else if(url.pathname===file.pathname){
      assert.equal(url.searchParams.get('v'),offerSha.slice(0,12));
      moduleLoads++;
    }
  });
  page.on('pageerror',error=>errors.push(error.message));
  const tap=loc=>screen.touch?loc.tap():loc.click();
  try{
    const client=await context.newCDPSession(page);
    await client.send('Emulation.setCPUThrottlingRate',{rate:cpu});
    await client.send('Performance.enable');
    const counters=async()=>Object.fromEntries((await client.send('Performance.getMetrics')).metrics.map(v=>[v.name,v.value]));
    await page.addInitScript(fixture,{path:searchPath,variantCount:offerCount,rooms:groupCount});
    assert.equal((await page.goto(server.origin+searchPath+'?'+query,{waitUntil:'domcontentloaded'})).status(),200);
    const entry=page.locator('#hotel-900001 [data-action="all-offers"]');
    await entry.waitFor({state:'visible',timeout:9000});
    assert.match(await entry.textContent(),/\(250\)/);
    await page.evaluate(()=>document.fonts.ready);
    await waitFrame(page);
    assert.deepEqual(await page.evaluate(()=>[innerWidth,innerHeight,devicePixelRatio,navigator.maxTouchPoints>0]),
      [screen.width,screen.height,screen.dpr,screen.touch]);
    assert.equal(await page.evaluate(()=>Boolean(window.AnyTourOfferList)),false);
    const items=page.locator('#all-offers-list article.grouped-offer'),more=page.locator('#all-offers-list [data-action="group-more"]');
    async function measure(expectLoads){
      await entry.scrollIntoViewIfNeeded();
      await waitFrame(page);
      await page.evaluate(observe);
      const before=await counters();
      await tap(entry);
      await page.waitForFunction(()=>window.denseOffersProbe?.finished===true,null,{timeout:12000});
      const after=await counters();
      const performance=await page.evaluate(()=>{
        const {dispatch,shell,ready,frame}=window.denseOffersProbe;
        return {dispatch,shell,ready,frame};
      });
      assert(performance.shell>=0&&performance.ready>=performance.shell&&performance.frame>=performance.ready);
      assert.equal(await items.count(),100);
      assert.equal(await more.count(),25);
      assert.match(await items.first().locator('.offer-price strong').textContent(),/186\s400/);
      assert.equal(moduleLoads,expectLoads);
      return {...performance,style:(after.RecalcStyleDuration-before.RecalcStyleDuration)*1000,layout:(after.LayoutDuration-before.LayoutDuration)*1000};
    }
    const cold=await measure(1);
    await tap(page.locator('#modal [data-action="close-modal"]'));
    await page.waitForFunction(()=>document.querySelector('#modal').open===false);
    await waitFrame(page);
    assert.equal(await page.evaluate(()=>Boolean(window.AnyTourOfferList)),true);
    const warm=await measure(1);
    if(exercise){
      const route=()=>page.evaluate(key=>history.state?.[key],routeKey);
      const current=await route();
      assert.equal(current.type,'all-offers');
      assert.equal(current.id,900001);
      assert.equal(current.scroll,0);
      assert.equal(await page.evaluate(()=>document.activeElement.id),'modal-title');
      await tap(more.first());
      assert.equal(await items.count(),106);
      assert.equal(await more.count(),24);
      assert.match(await page.locator('#offer-count').textContent(),/^250 /);
      const expanded=await route();
      assert.deepEqual(Object.values(expanded.limits),[12]);
      const displayedPrice=await items.first().locator('.offer-price strong').textContent();
      await page.evaluate(()=>history.back());
      await page.waitForFunction(()=>!document.querySelector('#modal').open);
      await page.evaluate(()=>history.forward());
      await page.waitForFunction(()=>document.querySelector('#modal').open&&document.querySelectorAll('#all-offers-list .grouped-offer').length===106);
      assert.equal(await more.count(),24);
      assert.equal(await items.first().locator('.offer-price strong').textContent(),displayedPrice);
      assert.equal((await route()).type,'all-offers');
      assert.deepEqual((await route()).limits,expanded.limits);
      assert.equal((await route()).scroll,expanded.scroll);
    }
    assert.deepEqual(await page.evaluate(()=>[window.denseOfferFixture.searches,window.denseOfferFixture.seeded]),[1,1]);
    assert.deepEqual(wrong,[]);
    assert.deepEqual(errors,[]);
    assert.deepEqual(server.rejected,[]);
    return {cold,warm};
  }finally{await context.close();}
}

function summarize(samples) {
  return Object.fromEntries(['cold','warm'].map(kind=>[
    kind,Object.fromEntries(['dispatch','shell','ready','frame','style','layout'].map(metric=>{
      const before=samples.map(ab=>ab.before[kind][metric]);
      const after=samples.map(ab=>ab.after[kind][metric]);
      const delta=before.map((value,i)=>after[i]-value);
      return [metric,{before:median(before),after:median(after),medianPairedDelta:median(delta),faster:delta.filter(value=>value<0).length}];
    })),
  ]));
}

module.exports=async function compareWarmOfferHistory(browser,nextHome,output){
  const exact=exactAssets(nextHome),root=path.resolve(__dirname,'..');
  assert.equal(exact.appSha,compiledApp);
  assert.equal(exact.sheetSha,styles);
  assert.deepEqual(execFileSync('git',['diff','--name-only',releaseCommit,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort(),
    ['tests/search3-entry-owner-browser.cjs','tests/search3-next-warm-offers-history-ab.cjs']);
  const raw=execFileSync('git',['show',sourceCommit+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
  assert.equal(sha256(raw),rawApp);
  assert.equal(sha256(await compile(raw,{privateFunctions:true})),compiledApp);
  const code=await compile(changeWarmOpen(raw),{privateFunctions:true}),candidateApp=sha256(code);
  const module=fs.readFileSync(path.join(nextHome.payload,'visual-search/offer-list-v1.js'));
  assert.equal(sha256(module),offerSha);
  const appUrl=exact.appUrl.replace(compiledApp.slice(0,12),candidateApp.slice(0,12));
  assert.notEqual(appUrl,exact.appUrl);
  const update=html=>html.replace(exact.appUrl,appUrl);
  const changed={...exact,code:Buffer.from(code),appSha:candidateApp,appUrl,home:update(exact.home),search:update(exact.search)};
  const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const base=await startServer(nextHome,exact,paths),groups=[];
  try{
    const candidate=await startServer(nextHome,changed,paths);
    try{
      for(const screen of screens)for(const cpu of [1,4]){
        const before=()=>runPair(browser,base,paths.search,screen,cpu,false);
        const after=()=>runPair(browser,candidate,paths.search,screen,cpu,false);
        const warmup={
          before:await runPair(browser,base,paths.search,screen,cpu,cpu===1),
          after:await runPair(browser,candidate,paths.search,screen,cpu,cpu===1),
        };
        const samples=[];
        for(let i=0;i<6;i++){
          if(i%2===0)samples.push({first:'before',before:await before(),after:await after()});
          else {const changed=await after();samples.push({first:'after',before:await before(),after:changed});}
        }
        const summary=summarize(samples);
        console.log('SEARCH3_WARM_OFFER_HISTORY_AB '+JSON.stringify({screen,cpu,summary}));
        groups.push({screen,cpu,warmup,samples,summary});
      }
      const gzip=require('node:zlib').gzipSync;
      fs.writeFileSync(path.join(output,'canonical-next-warm-offers-history-ab.json'),JSON.stringify({
        diagnosticSource:process.env.SEARCH3_SOURCE_SHA,sourceCommit,releaseCommit,compiledApp,candidateApp,
        baselineBytes:exact.code.length,candidateBytes:Buffer.byteLength(code),
        baselineGzip:gzip(exact.code).length,candidateGzip:gzip(code).length,
        offerSha,styles,browser:browser.version(),offerCount,groupCount,groups,
      },null,2)+'\n');
    }finally{await candidate.close();}
  }finally{await base.close();}
};
