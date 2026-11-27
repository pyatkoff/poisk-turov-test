'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { gzipSync } = require('node:zlib');
const { canonicalLogoFixture } = require('./search3-visual-live-fixture.cjs');
const { compile } = require('../scripts/build/search3-js/visual-entry.cjs');

const root = path.resolve(__dirname, '..');
const digest = bytes => createHash('sha256').update(bytes).digest('hex');
const fullName = 'home-hero-v1.jpg';
const compactName = 'home-hero-v1-680.jpg';
const fixtureQuery = new URLSearchParams({
  scenario: 'family', origin: 'Москва', country: '4', from: '2026-10-01', to: '2026-10-07',
  minNights: '7', maxNights: '7', adults: '3', ages: '0,12', stars: '4|5',
  meals: 'Всё включено', max: '240000'
});
const network = { offline:false, latency:40, downloadThroughput:1250000, uploadThroughput:625000, connectionType:'ethernet' };
const profiles = [
  { name:'iphone', width:430, height:932, dpr:3, touch:true, hero:null },
  { name:'desktop', width:1280, height:720, dpr:1, touch:false, hero:compactName }
];
const contentTypes = {
  '.css':'text/css; charset=utf-8', '.js':'application/javascript; charset=utf-8',
  '.json':'application/json; charset=utf-8', '.svg':'image/svg+xml',
  '.ico':'image/x-icon', '.png':'image/png', '.jpg':'image/jpeg', '.jpeg':'image/jpeg',
  '.webp':'image/webp', '.woff2':'font/woff2', '.woff':'font/woff'
};

const instrumentApp=function(source){
 const one=(body,needle,replacement)=>{
  if(body.split(needle).length!==2)throw Error('Unpinned app phase '+needle);
  return body.replace(needle,()=>replacement);
 };
 source=one(source,"(() => {",`(() => {
const startupProfile=window.__search3ReceiverCPU={evalStart:performance.now(),calls:[]};
let startupDepth=0;
function startupMeasure(name,fn){
 return function(...args){
  const start=performance.now(),depth=startupDepth++;
  try{return Reflect.apply(fn,this,args);}
  finally{ startupDepth--;startupProfile.calls.push({name,depth,start,end:performance.now()});}
 };
}`);
 const marker="const initialUIRoute=history.state?.[uiHistoryKey];";
 source=one(source,marker,[
 "restoreURL","renderResults","renderCalendarStrip","refreshResultFilters","renderFilters","updateSearchUI","applyCatalog"
 ].map(name=>name+'=startupMeasure('+JSON.stringify(name)+','+name+');').join('\n')+'\n'+marker);
 source=one(source," prepare:prepareSearchRun,"," prepare:startupMeasure('prepareSearchRun',prepareSearchRun),");
 source=one(source,"hydrate();if(!window.AnyTourPublicBootstrapV1?.selected)restoreURL(true);renderResults({deferFilterPanel:!homeFormOnly&&innerWidth<=1100});bootRealData();",
 "startupMeasure('hydrate',hydrate)();if(!window.AnyTourPublicBootstrapV1?.selected)restoreURL(true);renderResults({deferFilterPanel:!homeFormOnly&&innerWidth<=1100});bootRealData();");
 const line="if(initialUIRoute&&!messengerReturn)addEventListener('pageshow',()=>restoreHistoryView(initialUIRoute),{once:true});";
 return one(source,line,line+'\nstartupProfile.evalEnd=performance.now();');
};
async function versions(nextHome) {
  const paths = execFileSync('git',['diff','--name-only','31aa05eef7d2d04dad0dcc9934a85b75dd884b1a','HEAD'],
    {cwd:root,encoding:'utf8'}).trim().split('\n').sort();
  assert.deepEqual(paths,['tests/search3-entry-owner-browser.cjs','tests/search3-next-receiver-cpu.cjs']);
  const homePath=nextHome.prefix+'/', resultPath=homePath+'poisk-turov/';
  const home=nextHome.html.get(homePath),search=nextHome.html.get(resultPath);
  assert(home.includes(' data-search3-handoff-prefetch="')&&!search.includes(' data-search3-handoff-prefetch="'));
  const name='visual-search/app.js';
  const source=execFileSync('git',['show','31aa05eef7d2d04dad0dcc9934a85b75dd884b1a:v2/'+name],{cwd:root,encoding:'utf8',maxBuffer:1024*1024});
  const before=fs.readFileSync(path.join(nextHome.payload,name));
  assert.equal(digest(before),'dcb19a092f463d387964ac3383753446effde8c8836e3f68a5d7362adabca1db');
  assert(Buffer.from(await compile(source,{privateFunctions:true})).equals(before));
  const after=Buffer.from(await compile(instrumentApp(source),{privateFunctions:true}));
  const ref=bytes=>homePath+name+'?v='+digest(bytes).slice(0,12);
  const change=html=>{
    assert.equal(html.split(ref(before)).length,2);
    return html.replace(ref(before),ref(after));
  };
  return {before:{home,search,bytes:before,url:ref(before)},
    after:{home:change(home),search:change(search),bytes:after,url:ref(after)}};
}

function installTimestamps(routes) {
  const isHome = location.pathname === routes.home;
  if (!isHome && location.pathname !== routes.search) return;
  const find = '#search-form .search-submit:not([disabled])';
  const clickKey = 'search3-5278-benchmark-click';
  const absoluteNow = () => performance.timeOrigin + performance.now();
  if (isHome) document.addEventListener('click', event => {
    if (event.isTrusted && event.target.closest?.(find)) sessionStorage.setItem(clickKey,String(absoluteNow()));
  }, { capture:true });
  let start, packet, inserted;
  if (!isHome) {
    let lifecycle;
    Object.defineProperty(window,'AnyTourPrototypeSearchLifecycleV1',{
      configurable:true,
      get:()=>lifecycle,
      set(value) {
        const getters=Object.getOwnPropertyDescriptors(value), create=getters.create.value;
        const wrapCreate=function(options) {
          const data=options.data, descriptors=Object.getOwnPropertyDescriptors(data);
          const search=descriptors.search.value;
          descriptors.search={...descriptors.search,value:function(...args) {
            start??=performance.now();
            const emit=args[1];
            args[1]=event=>{
              if(event?.type==='results'&&event.hotels?.length)packet??=performance.now();
              return emit(event);
            };
            return Reflect.apply(search,this,args);
          }};
          const observed=Object.freeze(Object.create(Object.getPrototypeOf(data),descriptors));
          return Reflect.apply(create,this,[{...options,data:observed}]);
        };
        lifecycle=Object.freeze(Object.create(Object.getPrototypeOf(value),{...getters,create:{...getters.create,value:wrapCreate}}));
      }
    });
  }
  const target = isHome ? find : '#cards .hotel-card h3';
  let pending = false;
  function readyNode() {
    const node = document.querySelector(target);
    if (!node || node.closest('[hidden],[inert]')) return null;
    if (isHome && node.closest('#search-form')?.getAttribute('aria-busy') === 'true') return null;
    if (!isHome && !node.closest('.hotel-card').querySelector('.starting-price strong')?.textContent) return null;
    return node;
  }
  const observer = new MutationObserver(check);
  function check() {
    if (pending || !readyNode()) return;
    if (!isHome) inserted??=performance.now();
    pending = true;
    requestAnimationFrame(() => requestAnimationFrame(() => {
      const node = readyNode();
      const box = node?.getBoundingClientRect();
      const hit = box && document.elementFromPoint(box.left+box.width/2,box.top+box.height/2);
      const visible = !!box && box.width>0 && box.height>0 && box.top>=0 &&
        box.bottom<=innerHeight+1 && !!hit && node.contains(hit);
      if (!visible) {
        pending = false;
        requestAnimationFrame(check);
        return;
      }
      const clicked = isHome ? null : Number(sessionStorage.getItem(clickKey));
      if (isHome || clicked>0) {
        const ready=performance.now();
        window.search3ReleasePairTiming = { ready, firstCard:clicked?absoluteNow()-clicked:null,
          first:!isHome?{clickRelative:clicked-performance.timeOrigin,start,packet,inserted,visible:ready}:null };

        observer.disconnect();
      }
    }));
  }
  observer.observe(document,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','aria-busy','hidden']});
}

async function sample(browser, server, origin, paths, profile, mode, trace) {
  server.select(mode);
  const context = await browser.newContext({
    viewport:{width:profile.width,height:profile.height},
    deviceScaleFactor:profile.dpr, isMobile:profile.touch, hasTouch:profile.touch,
    serviceWorkers:'block', reducedMotion:'reduce', proxy:{server:origin,bypass:'127.0.0.1'}
  });
  try {
    const blocked = [], errors = [], docs = [], photos = [];
    const page = await context.newPage();
    page.on('pageerror', error=>errors.push(error.message));
    page.on('request', request=>{
      const url = new URL(request.url());
      if (url.origin!==origin) blocked.push(url.pathname);
      if (request.isNavigationRequest() && request.frame()===page.mainFrame()) docs.push(url.pathname);
      if (url.pathname.startsWith(paths.home) && [fullName,compactName].includes(path.basename(url.pathname))) photos.push(path.basename(url.pathname));
    });
    const client = await context.newCDPSession(page);
    await client.send('Network.enable');
    await client.send('Network.emulateNetworkConditions',network);
    await page.addInitScript(installTimestamps,paths);
    const traceEvents=[];
    let traceState=null;
    if(trace){
      try{
        client.on('Tracing.dataCollected',payload=>{
          for(const event of payload.value){
            if(!/compile|evaluatescript|parse/i.test(event.name))continue;
            const meta=JSON.stringify(event.args??{});
            if(meta.includes('/visual-search/app.js'))traceEvents.push(event);
          }
        });
        await client.send('Tracing.start',{categories:'devtools.timeline,v8,disabled-by-default-devtools.timeline',transferMode:'ReportEvents'});
        traceState='started';
      }catch(error){traceState=error.message;}
    }
    assert.equal((await page.goto(origin+paths.home+'?'+fixtureQuery,{waitUntil:'commit',timeout:15000})).status(),200);
    await page.waitForFunction(()=>window.search3ReleasePairTiming?.ready>0,{},{timeout:12000});
    const ready = await page.evaluate(()=>({
      button:window.search3ReleasePairTiming.ready, dpr:devicePixelRatio, width:innerWidth, height:innerHeight,
      touch:navigator.maxTouchPoints>0, form:{
        meal:document.querySelector('#quick-meal')?.textContent?.trim(),
        budget:document.querySelector('#quick-budget')?.textContent?.trim()
      }
    }));
    assert.equal(ready.dpr,profile.dpr);
    assert.equal(ready.width,profile.width);
    assert.equal(ready.touch,profile.touch);
    assert(ready.form.meal&&ready.form.budget);
    const button = page.locator('#search-form .search-submit');
    await Promise.all([
      page.waitForURL(url=>url.pathname===paths.search,{timeout:12000}),
      profile.touch ? button.tap({timeout:8000}) : button.click({timeout:8000})
    ]);
    await page.waitForFunction(()=>window.search3ReleasePairTiming?.firstCard>0,{},{timeout:12000});
    const {firstCard,phase,transfer,encoding,cpu}=await page.evaluate(()=>{

      const timing=window.search3ReleasePairTiming, marks=timing.first;
      const navigation=performance.getEntriesByType('navigation')[0];
      const resources=performance.getEntriesByType('resource');
      const find=name=>resources.find(row=>new URL(row.name).pathname.endsWith('/visual-search/'+name));
      const app=find('app.js'),css=find('styles.css');
      const ordered=[marks.clickRelative,navigation.responseStart,app?.responseEnd,marks.start,marks.packet,marks.inserted,marks.visible];
      return {firstCard:timing.firstCard,
        phase:ordered.slice(1).map((end,i)=>end-ordered[i]),
        transfer:{app:app?.transferSize,css:css?.transferSize,all:resources.reduce((n,row)=>n+row.transferSize,0),
          searchOnly:resources.filter(row=>/\/(filter-panel-v1|flight-picker-v18|preview-lead)\.js\?/.test(row.name))
            .map(row=>[new URL(row.name).pathname.split('/').at(-1),row.transferSize])},
        encoding:[app?.deliveryType,css?.deliveryType],
        cpu:window.__search3ReceiverCPU?{
          bytesToEval:window.__search3ReceiverCPU.evalStart-app.responseEnd,
          evaluation:window.__search3ReceiverCPU.evalEnd-window.__search3ReceiverCPU.evalStart,
          evalToSearch:marks.start-window.__search3ReceiverCPU.evalEnd,
          calls:window.__search3ReceiverCPU.calls.filter(call=>call.end<=marks.start)
        }:null};
    });
    assert(phase.every(value=>Number.isFinite(value)&&value>=0),'Every phase must be present and ordered.');
    assert(Math.abs(phase.reduce((a,b)=>a+b,0)-firstCard)<1,'The phases cover the observed click to card interval.');
    assert(transfer.app===0&&transfer.css===0,'The shared versioned app and stylesheet must be reused from the cache.');
    const cards = await page.locator('#cards .hotel-card').evaluateAll(nodes=>nodes.map(node=>[
      node.id,node.querySelector('h3')?.textContent,node.querySelector('.starting-price strong')?.textContent
    ]));
    assert(cards.length&&cards[0][2],'A real priced fixture card is visible.');
    assert.deepEqual(docs,[paths.home,paths.search]);
    assert.deepEqual(photos,profile.hero?[profile.hero]:[]);
    assert.deepEqual(blocked,[]);
    assert.deepEqual(errors,[]);
    if(traceState==='started'){
      const completed=new Promise(resolve=>client.once('Tracing.tracingComplete',()=>resolve(true)));
      await client.send('Tracing.end');
      let timeout;
      traceState=await Promise.race([completed,new Promise(resolve=>{timeout=setTimeout(()=>resolve(false),5000);})])?'complete':'timeout';
      clearTimeout(timeout);
    }
    if(mode==='after')assert(cpu&&Number.isFinite(cpu.evaluation)&&cpu.calls.some(call=>call.name==='prepareSearchRun'));
    else assert.equal(cpu,null);
    return {button:ready.button,firstCard,phase,transfer,encoding,form:ready.form,cards,cpu,
      trace:trace?{state:traceState,events:traceEvents.map(event=>({
        name:event.name,ts:event.ts,duration:typeof event.dur==='number'?event.dur/1000:null,
        fields:Object.keys(event.args??{})
      })).sort((a,b)=>a.ts-b.ts)}:undefined};
  } finally {
    await context.close();
  }
}

async function startServer(nextHome, snapshots, paths) {
  let origin,mode='before';
  const cache = new Map();
  const rejected = [];
  function respond(request,response) {
    const url = new URL(request.url,origin);
    if (request.method!=='GET' || url.origin!==origin) throw Error('unexpected request '+request.method+' '+url.pathname);
    const variant=snapshots[mode],isApp=url.pathname.endsWith('/visual-search/app.js');
    const isDocument=url.pathname===paths.home||url.pathname===paths.search;
    const key=(isDocument||isApp?mode:'')+url.pathname;
    let item=cache.get(key);
    if(isApp)assert.equal(url.pathname+url.search,variant.url);
    if (!item) {
      let body, type;
      if (url.pathname===paths.home || url.pathname===paths.search) {
        body = Buffer.from(url.pathname===paths.home?variant.home:variant.search);
        type = 'text/html; charset=utf-8';
      } else if (isApp) {
        body=variant.bytes;
        type='application/javascript; charset=utf-8';
      } else if (url.pathname==='/images/logo.svg') {
        const logo = canonicalLogoFixture({url:()=>url.href,method:()=>request.method,resourceType:()=>'image'},origin);
        assert(logo);
        ({body,contentType:type}=logo);
      } else {
        const filename = url.pathname.startsWith(paths.home)
          ? path.resolve(nextHome.payload,url.pathname.slice(paths.home.length))
          : (/^\/favicon\.(svg|ico|png)$/.test(url.pathname) ? path.join(root,'v2',url.pathname.slice(1)) : '');
        type=contentTypes[path.extname(filename)];
        if (!type || !(filename.startsWith(nextHome.payload+path.sep)||filename.startsWith(path.join(root,'v2')+path.sep))
            || !fs.existsSync(filename) || !fs.statSync(filename).isFile()) throw Error('unapproved path '+url.pathname);
        body=fs.readFileSync(filename);
      }
      const compress=/^(text\/|application\/)/.test(type);
      item={body,type,gzip:compress?gzipSync(body):null};
      cache.set(key,item);
    }
    const accept=String(request.headers['accept-encoding']);
    const gzip=item.gzip&&/\bgzip\b/.test(accept);
    const body=gzip?item.gzip:item.body;
    const cacheControl=/\.(js|css)$/.test(url.pathname)&&/^[0-9a-f]{12,16}$/.test(url.searchParams.get('v')||'')
      ?'public, max-age=86400':'no-store';
    const headers={'Content-Type':item.type,'Content-Length':body.length,'Cache-Control':cacheControl};
    if(gzip)headers['Content-Encoding']='gzip';
    response.writeHead(200,headers);
    response.end(body);
  }
  const server = http.createServer((request,response)=>{
    try { respond(request,response); }
    catch (error) {
      rejected.push(error.message);
      response.writeHead(404,{'Content-Length':0});
      response.end();
    }
  });
  await new Promise((resolve,reject)=>{
    server.once('error',reject);
    server.listen(0,'127.0.0.1',resolve);
  });
  origin='http://127.0.0.1:'+server.address().port;
  return {
    origin, rejected, select:value=>{mode=value;},
    close:()=>new Promise(resolve=>{server.close(resolve);server.closeAllConnections();})
  };
}

function median(values) {
  const rows=[...values].sort((a,b)=>a-b);
  const middle=Math.floor(rows.length/2);
  return rows.length%2?rows[middle]:(rows[middle-1]+rows[middle])/2;
}

module.exports = async function measureReceiverCpu(browser,nextHome,output) {
  const snapshots=await versions(nextHome);
  const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const server=await startServer(nextHome,snapshots,paths);
  const groups=[];
  try {
    for (const profile of profiles) {
      const pairs=[];
      let trace=null;
      for (let i=0;i<10;i++) {
        const pair={};
        for (const mode of i%2?['after','before']:['before','after']) {
          pair[mode]=await sample(browser,server,server.origin,paths,profile,mode,i===0&&mode==='before');
          assert.deepEqual(server.rejected,[]);
        }
        assert.deepEqual(pair.before.cards,pair.after.cards);
        assert.deepEqual(pair.before.form,pair.after.form);
        if(i===0)trace=pair.before.trace;
        if(i>=2)pairs.push(pair);
      }
      const phases=Object.fromEntries(['bytesToEval','evaluation','evalToSearch'].map(name=>[
        name,median(pairs.map(p=>p.after.cpu[name]))
      ]));
      const scopes=['hydrate','restoreURL','renderResults','renderCalendarStrip','refreshResultFilters',
        'renderFilters','updateSearchUI','applyCatalog','prepareSearchRun'];
      const calls=Object.fromEntries(scopes.map(name=>{
        const timings=pairs.map(pair=>pair.after.cpu.calls.filter(call=>call.name===name));
        return [name,{
          total:median(timings.map(list=>list.reduce((sum,call)=>sum+call.end-call.start,0))),
          count:median(timings.map(list=>list.length))
        }];
      }));
      const before=pairs.map(p=>p.before.phase[2]),after=pairs.map(p=>p.after.phase[2]);
      const perturbation={before:median(before),after:median(after),pairedDelta:median(pairs.map(p=>p.after.phase[2]-p.before.phase[2]))};
      const result={profile,phases,calls,perturbation,trace};
      console.log('SEARCH3_RECEIVER_CPU '+JSON.stringify(result));
      groups.push({...result,pairs});
    }
    fs.writeFileSync(path.join(output,'canonical-next-receiver-cpu.json'),JSON.stringify({
      installedSource:'31aa05eef7d2d04dad0dcc9934a85b75dd884b1a',appSha: digest(snapshots.before.bytes),
      runnerCommit:process.env.SEARCH3_SOURCE_SHA,browserVersion:browser.version(),network,trials:8,
      scope:'Fresh Chromium contexts, ordinary cache between Home and search, measure before first data owner search. Nested named durations are inclusive.',
      groups
    },null,2)+'\n');
  } finally { await server.close(); }
};
