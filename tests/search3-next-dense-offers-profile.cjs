'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { exactAssets, startServer } = require('./search3-next-compact-calendar.cjs');
const { compile } = require('../scripts/build/search3-js/visual-entry.cjs');
const { parse } = require('../scripts/build/search3-js/node_modules/acorn');

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
const appPhaseNames = new Set(['openAllOffers','renderOfferList','showModal','paintGeneratedRoots','rememberUIRoute','uiRoute','captureOfferInventory','hotelOffers']);
const offerPhaseNames = new Set(['offerListInventory','offerRefinementInventory','renderOfferRefinements','offerRowEntry','renderOfferList','mountOfferList']);
const offerSha='0af11129916d635f2f40f502ebedc5422c2b32cac545a7e89614ebc3dcdb9bb2';

function instrument(source, phaseNames) {
  const seen = new Set();
  const insertions = [];
  function visit(node) {
    if (!node || typeof node !== 'object') return;
    if (Array.isArray(node)) {
      for (const item of node) visit(item);
      return;
    }
    if (node.type === 'FunctionDeclaration' && phaseNames.has(node.id?.name)) {
      const name = node.id.name;
      assert(!seen.has(name), name);
      seen.add(name);
      insertions.push([node.body.start + 1, 'const probe=window.denseOffersProbe;const time=probe?.enter(' + JSON.stringify(name) + ');try{']);
      insertions.push([node.body.end - 1, '}finally{if(time)probe.exit(time);}']);
    }
    for (const value of Object.values(node)) visit(value);
  }
  visit(parse(source, { ecmaVersion: 'latest' }));
  assert.deepEqual(seen, phaseNames);
  for (const [at, code] of insertions.sort((a, b) => b[0] - a[0])) source = source.slice(0, at) + code + source.slice(at);
  return source;
}
function instrumentApp(source) {
  const owner = ',icon,innerWidth,mealNames,';
  assert.equal(source.split(owner).length, 2);
  return instrument(source, appPhaseNames).replace(owner,
    ',icon,innerWidth:window.denseOffersProbe?window.denseOffersProbe.width():innerWidth,mealNames,');
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
  const stack=[];
  const run=window.denseOffersProbe={
    entries:[],
    enter(name) {
      if(this.start===undefined||this.finished!==undefined)return null;
      const entry={name,start:performance.now(),children:0,path:stack.length?stack.at(-1).path+'/'+name:name};
      stack.push(entry);return entry;
    },
    exit(entry) {
      const total=performance.now()-entry.start;
      if(stack.pop()!==entry)throw Error('Unbalanced offer timing');
      if(stack.length)stack.at(-1).children+=total;
      this.entries.push({path:entry.path,total,self:total-entry.children});
    },
    width(){
      const token=this.enter('innerWidth');
      try{return innerWidth;}finally{if(token)this.exit(token);}
    },
  };
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

async function runPair(browser,server,searchPath,moduleCode,screen,cpu,exercise){
  const context=await browser.newContext({viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,hasTouch:screen.touch,isMobile:screen.touch,
    reducedMotion:'reduce',serviceWorkers:'block',proxy:{server:server.origin,bypass:'127.0.0.1'}});
  const page=await context.newPage(),wrong=[],errors=[];
  let moduleLoads=0;
  page.on('request',request=>{const url=new URL(request.url());if(url.origin!==server.origin)wrong.push(url.pathname);});
  page.on('pageerror',error=>errors.push(error.message));
  const tap=loc=>screen.touch?loc.tap():loc.click();
  try{
    const file=new URL(searchPath.replace(/poisk-turov\/$/,'visual-search/offer-list-v1.js'),server.origin);
    await page.route(url=>url.origin===file.origin&&url.pathname===file.pathname,async interception=>{
      assert.equal(new URL(interception.request().url()).searchParams.get('v'),offerSha.slice(0,12));
      moduleLoads++;
      await interception.fulfill({contentType:'application/javascript; charset=utf-8',body:moduleCode});
    });
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
        const {dispatch,shell,ready,frame,entries}=window.denseOffersProbe;
        return {dispatch,shell,ready,frame,entries};
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
      await tap(more.first());
      assert.equal(await items.count(),106);
      assert.equal(await more.count(),24);
      assert.match(await page.locator('#offer-count').textContent(),/^250 /);
    }
    assert.deepEqual(await page.evaluate(()=>[window.denseOfferFixture.searches,window.denseOfferFixture.seeded]),[1,1]);
    assert.deepEqual(wrong,[]);
    assert.deepEqual(errors,[]);
    assert.deepEqual(server.rejected,[]);
    return {cold,warm};
  }finally{await context.close();}
}

function summarize(samples) {
  return Object.fromEntries(['cold','warm'].map(kind=>{
    const group=samples.map(pair=>pair[kind]);
    const paths=new Set(group.flatMap(row=>row.entries.map(item=>item.path)));
    const phases=Object.fromEntries([...paths].sort().map(path=>{
      const records=group.map(sample=>sample.entries.filter(row=>row.path===path));
      return [path,{calls:records.map(rows=>rows.length),
        total:median(records.map(rows=>rows.reduce((sum,row)=>sum+row.total,0))),
        self:median(records.map(rows=>rows.reduce((sum,row)=>sum+row.self,0)))}];
    }));
    return [kind,{...Object.fromEntries(['dispatch','shell','ready','frame','style','layout'].map(metric=>[metric,median(group.map(row=>row[metric]))])),phases}];
  }));
}

module.exports=async function profileDenseOffers(browser,nextHome,output){
  const exact=exactAssets(nextHome),root=path.resolve(__dirname,'..');
  assert.equal(exact.appSha,compiledApp);
  assert.equal(exact.sheetSha,styles);
  assert.deepEqual(execFileSync('git',['diff','--name-only',releaseCommit,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort(),
    ['tests/search3-entry-owner-browser.cjs','tests/search3-next-dense-offers-profile.cjs']);
  const raw=execFileSync('git',['show',sourceCommit+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
  assert.equal(sha256(raw),rawApp);
  assert.equal(sha256(await compile(raw,{privateFunctions:true})),compiledApp);
  const code=await compile(instrumentApp(raw),{privateFunctions:true}),instrumentedApp=sha256(code);
  const module=fs.readFileSync(path.join(nextHome.payload,'visual-search/offer-list-v1.js'),'utf8');
  assert.equal(sha256(module),offerSha);
  const instrumentedModule=instrument(module,offerPhaseNames);
  const appUrl=exact.appUrl.replace(compiledApp.slice(0,12),instrumentedApp.slice(0,12));
  assert.notEqual(appUrl,exact.appUrl);
  const update=html=>html.replace(exact.appUrl,appUrl);
  const changed={...exact,code:Buffer.from(code),appSha:instrumentedApp,appUrl,home:update(exact.home),search:update(exact.search)};
  const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const server=await startServer(nextHome,changed,paths),groups=[];
  try{
    for(const screen of screens)for(const cpu of [1,4]){
      const warmup=await runPair(browser,server,paths.search,instrumentedModule,screen,cpu,cpu===1);
      const samples=[];
      for(let i=0;i<3;i++)samples.push(await runPair(browser,server,paths.search,instrumentedModule,screen,cpu,false));
      const summary=summarize(samples);
      console.log('SEARCH3_DENSE_OFFER_PROFILE '+JSON.stringify({screen,cpu,summary}));
      groups.push({screen,cpu,warmup,samples,summary});
    }
    fs.writeFileSync(path.join(output,'canonical-next-dense-offers-profile.json'),JSON.stringify({
      diagnosticSource:process.env.SEARCH3_SOURCE_SHA,sourceCommit,releaseCommit,compiledApp,instrumentedApp,
      offerSha,instrumentedModuleSha:sha256(instrumentedModule),styles,browser:browser.version(),offerCount,groupCount,groups,
    },null,2)+'\n');
  }finally{await server.close();}
};
