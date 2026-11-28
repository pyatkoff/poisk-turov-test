'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { gzipSync } = require('node:zlib');
const { canonicalLogoFixture } = require('./search3-visual-live-fixture.cjs');

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


async function versions(nextHome) {
  const paths=execFileSync('git',['diff','--name-only','31aa05eef7d2d04dad0dcc9934a85b75dd884b1a','HEAD'],
    {cwd:root,encoding:'utf8'}).trim().split('\n').sort();
  assert.deepEqual(paths,['tests/search3-entry-owner-browser.cjs','tests/search3-next-app-priority-benchmark.cjs']);
  const homePath=nextHome.prefix+'/', resultsPath=homePath+'poisk-turov/';
  const home=nextHome.html.get(homePath),search=nextHome.html.get(resultsPath);
  assert(home.includes(' data-search3-handoff-prefetch="')&&!search.includes(' data-search3-handoff-prefetch="'));
  const name='visual-search/app.js';
  const bytes=fs.readFileSync(path.join(nextHome.payload,name));
  assert.equal(digest(bytes),'dcb19a092f463d387964ac3383753446effde8c8836e3f68a5d7362adabca1db');
  const url=homePath+name+'?v='+digest(bytes).slice(0,12);
  assert.equal(search.split(url).length,2);
  const lastCriticalStyle=/<link\b[^>]*href="[^"]*\/site-header-v2\.css(?:\?[^"]+)?"[^>]*>/g;
  const candidates=[...search.matchAll(lastCriticalStyle)];
  assert.equal(candidates.length,1,'Insert after the canonical eager styles');
  const anchor=candidates[0][0];
  assert(search.indexOf(anchor)<search.indexOf(url));
  const stylesheet=fs.readFileSync(path.join(nextHome.payload,'visual-search/styles.css'));
  assert.equal(digest(stylesheet),'544533a067bb7f9d70f83c56aeb78ccdb8fb38522ce2668ed32b57f28c822e44');
  const hint='<link rel="preload" as="script" href="'+url+'" fetchpriority="low">';
  const low=search.replace(anchor,()=>anchor+'\n'+hint);
  assert.equal(low.replace('\n'+hint,''),search);
  return {before:{home,search,bytes,url},low:{home,search:low,bytes,url},
    afterStyles:{home,search,bytes,url}};
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
      {
        const ready=performance.now();
        window.search3ReleasePairTiming = { ready, firstCard:!isHome?(clicked>0?absoluteNow()-clicked:ready):null,
          first:!isHome?{clickRelative:clicked>0?clicked-performance.timeOrigin:0,start,packet,inserted,visible:ready}:null };

        observer.disconnect();
      }
    }));
  }
  observer.observe(document,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','aria-busy','hidden']});
}

async function sample(browser, server, origin, paths, profile, mode, cache, pinned) {
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
    const starts=new Map();
    client.on('Network.requestWillBeSent',event=>{
      const url=new URL(event.request.url);
      if(!/\/visual-search\/(app\.js|styles\.css)$/.test(url.pathname))return;
      starts.set(event.requestId,{file:path.basename(url.pathname),document:new URL(event.documentURL).pathname,
        initial:event.request.initialPriority,changes:[]});
    });
    client.on('Network.resourceChangedPriority',event=>starts.get(event.requestId)?.changes.push(event.newPriority));
    await client.send('Network.enable');
    const testNetwork=cache==='cold-slow'?{...network,downloadThroughput:250000,uploadThroughput:125000,latency:80}:network;
    await client.send('Network.emulateNetworkConditions',testNetwork);
    await page.addInitScript(installTimestamps,paths);
    if(mode==='afterStyles') await page.addInitScript(({search,pinned})=>{
      if(location.pathname!==search)return;
      document.addEventListener('load',function afterStyles(event) {
        const target=event.target;
        if(!target.matches?.('link[rel="stylesheet"][href*="/visual-search/styles.css?"]'))return;
        document.removeEventListener('load',afterStyles,true);
        const hint=document.createElement('link');
        hint.rel='preload';hint.as='script';hint.fetchPriority='high';hint.href=pinned;
        window.search3PrioritizedAfterStyle=performance.now();
        document.head.append(hint);
      },true);
    },{search:paths.search,pinned});
    let ready=null;
    if(cache==='warm'){
      assert.equal((await page.goto(origin+paths.home+'?'+fixtureQuery,{waitUntil:'commit',timeout:15000})).status(),200);
      try{
        await page.waitForFunction(()=>window.search3ReleasePairTiming?.ready>0,{},{timeout:8000});
      }catch(error){
        throw new Error('Ready '+profile.name+'/'+mode+': '+JSON.stringify({errors,rejected:server.rejected,blocked}),{cause:error});
      }
      ready=await page.evaluate(()=>({
        button:window.search3ReleasePairTiming.ready,dpr:devicePixelRatio,width:innerWidth,height:innerHeight,
        touch:navigator.maxTouchPoints>0,form:{
          meal:document.querySelector('#quick-meal')?.textContent?.trim(),
          budget:document.querySelector('#quick-budget')?.textContent?.trim()
        }
      }));
      assert.equal(ready.dpr,profile.dpr);
      assert.equal(ready.width,profile.width);
      assert.equal(ready.touch,profile.touch);
      assert(ready.form.meal&&ready.form.budget);
      const button=page.locator('#search-form .search-submit');
      await Promise.all([
        page.waitForURL(url=>url.pathname===paths.search,{timeout:12000}),
        profile.touch?button.tap({timeout:8000}):button.click({timeout:8000})
      ]);
    }else{
      const initial=new URLSearchParams(fixtureQuery);
      initial.set('searched','1');
      assert.equal((await page.goto(origin+paths.search+'?'+initial,{waitUntil:'commit',timeout:15000})).status(),200);
    }
    try {
      await page.waitForFunction(()=>window.search3ReleasePairTiming?.firstCard>0,{},{timeout:8000});
    } catch(error) {
      throw new Error('First card '+profile.name+'/'+mode+': '+JSON.stringify({errors,rejected:server.rejected,blocked}),{cause:error});
    }
    await page.waitForFunction(()=>performance.getEntriesByName('first-contentful-paint','paint').length>0);
    const timing=await page.evaluate(()=>{
      const t=window.search3ReleasePairTiming,marks=t.first;
      const nav=performance.getEntriesByType('navigation')[0],resources=performance.getEntriesByType('resource');
      const find=name=>resources.filter(row=>new URL(row.name).pathname.endsWith('/visual-search/'+name));
      const assets=(items)=>items.map(r=>({
        initiator:r.initiatorType, discovery:r.startTime-nav.responseStart,
        waiting:r.requestStart>0?r.requestStart-r.fetchStart:null,
        response:r.requestStart>0?r.responseStart-r.requestStart:null,
        body:r.responseStart>0?r.responseEnd-r.responseStart:null,
        untilBytes:r.responseEnd-nav.responseStart, bytes:r.transferSize,encoded:r.encodedBodySize
      }));
      const url=new URL(location.href);
      return {firstCard:t.firstCard,form:{
        meal:document.querySelector('#meal-label')?.textContent?.trim(),
        budget:document.querySelector('#budget-label')?.textContent?.trim(),
        max:url.searchParams.get('max'), guests:document.querySelector('#guests-label')?.textContent?.trim()
      },app:assets(find('app.js')),css:assets(find('styles.css')),afterApp:marks.start-find('app.js')[0]?.responseEnd,
         cardDOM:marks.inserted,navResponse:nav.responseStart,
         firstPaint:performance.getEntriesByName('first-contentful-paint','paint')[0]?.startTime,
         promotion:window.search3PrioritizedAfterStyle??null};
    });
    assert(timing.app.length>=1,'The app resource must complete');
    assert.equal(timing.css.length,1,'Exactly one visual stylesheet resource');
    assert(timing.app[0].encoded>0&&timing.css[0].encoded>0);
    assert(timing.firstCard>0 && Number.isFinite(timing.afterApp));
    assert(timing.form.meal&&timing.form.budget&&timing.form.max==='240000');
    assert(timing.firstPaint>0&&timing.firstPaint<=timing.firstCard+40);
    if(cache!=='warm') assert(timing.app[0].bytes>0&&timing.css[0].bytes>0,'Cold navigation transfers both resources');
    else assert(timing.app[0].bytes===0&&timing.css[0].bytes===0,'The shared URL still hits the warm cache.');
    const cards = await page.locator('#cards .hotel-card').evaluateAll(nodes=>nodes.map(node=>[
      node.id,node.querySelector('h3')?.textContent,node.querySelector('.starting-price strong')?.textContent
    ]));
    assert(cards.length&&cards[0][2],'A real priced fixture card is visible.');
    assert.deepEqual(docs,cache==='warm'?[paths.home,paths.search]:[paths.search]);
    assert.deepEqual(photos,cache==='warm'&&profile.hero?[profile.hero]:[]);
    assert.deepEqual(blocked,[]);
    assert.deepEqual(errors,[]);
    return {button:ready?.button??null,...timing,cards,extraAppTransfers:timing.app.slice(1).filter(row=>row.bytes>0).length,priorities:[...starts.values()].filter(row=>row.document===paths.search)};
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

function summarize(pairs,measurement){
  const before=pairs.map(sample=>measurement(sample.before));
  const after=pairs.map(sample=>measurement(sample.after));
  const deltas=after.map((value,index)=>value-before[index]);
  return {before:median(before),after:median(after),pairedDelta:median(deltas),faster:deltas.filter(value=>value<0).length,
    minDelta:Math.min(...deltas),maxDelta:Math.max(...deltas)};
}
module.exports = async function compareAppPreload(browser,nextHome,output) {
  const snapshots=await versions(nextHome);
  const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const server=await startServer(nextHome,snapshots,paths);
  const groups=[];
  try {
    for(const profile of profiles)for(const cache of ['cold','cold-slow','warm']){
      const rows=[],trials=cache==='warm'?4:6;
      const variants=['before','low','afterStyles'];
      for(let i=0;i<trials+2;i++){
        const pair={};
        const order=variants.slice(i%3).concat(variants.slice(0,i%3));
        if(i%2===1)order.reverse();
        for(const mode of order){
          pair[mode]=await sample(browser,server,server.origin,paths,profile,mode,cache,snapshots[mode].url);
          assert.deepEqual(server.rejected,[]);
        }
        for(const mode of variants.slice(1)){
          assert.deepEqual(pair.before.cards,pair[mode].cards);
          assert.deepEqual(pair.before.form,pair[mode].form);
          assert.equal(pair.before.app[0].encoded,pair[mode].app[0].encoded);
        }
        if(i>=2)rows.push(pair);
      }
      const timings={card:row=>row.firstCard,firstPaint:row=>row.firstPaint,cssEnd:row=>row.css[0].untilBytes,
        appEnd:row=>row.app[0].untilBytes,discovery:row=>row.app[0].discovery,afterApp:row=>row.afterApp};
      if(cache==='warm')timings.button=row=>row.button;
      else for(const name of ['waiting','response','body'])timings[name]=row=>row.app[0][name];
      const options=Object.fromEntries(variants.slice(1).map(mode=>{
        const pairs=rows.map(row=>({before:row.before,after:row[mode]}));
        const stats=Object.fromEntries(Object.entries(timings).map(([name,read])=>[name,summarize(pairs,read)]));
        return [mode,{stats,extraAppTransfers:pairs.reduce((n,row)=>n+row.after.extraAppTransfers,0)}];
      }));
      const group={profile,cache,trials,options};
      console.log('SEARCH3_APP_PRIORITY '+JSON.stringify(group));
      groups.push({...group,pairs:rows});
    }
    fs.writeFileSync(path.join(output,'canonical-next-app-priority.json'),JSON.stringify({
      installedSource:'31aa05eef7d2d04dad0dcc9934a85b75dd884b1a',runnerCommit:process.env.SEARCH3_SOURCE_SHA,
      sameApp:digest(snapshots.before.bytes),browserVersion:browser.version(),network,
      slow:{downloadThroughput:250000,latency:80},groups
    },null,2)+'\n');
  } finally {await server.close();}
};
