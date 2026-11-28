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
const slow={...network,latency:80,downloadThroughput:250000,uploadThroughput:125000};
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


function exactFixture(nextHome) {
  const revision='30fb66d69f09754fbc70cec0df3da56d4262722a';
  const diff=execFileSync('git',['diff','--name-only',revision,'HEAD'],
    {cwd:root,encoding:'utf8'}).trim().split('\n').sort();
  assert.deepEqual(diff,['tests/search3-entry-css-link-browser.cjs','tests/search3-next-css-link-benchmark.cjs']);
  const base=nextHome.prefix+'/', home=nextHome.html.get(base), search=nextHome.html.get(base+'poisk-turov/');
  const sheet=fs.readFileSync(path.join(nextHome.payload,'visual-search','styles.css'));
  assert.equal(digest(sheet),'544533a067bb7f9d70f83c56aeb78ccdb8fb38522ce2668ed32b57f28c822e44');
  const url=base+'visual-search/styles.css?v='+digest(sheet).slice(0,12);
  for (const html of [home,search]) {
    assert.equal(html.split('<link rel="stylesheet" href="'+url+'">').length,2);
  }
  return {home,search,sheet,url};
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

async function sample(browser, server, origin, paths, profile, mode, at) {
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
    await client.send('Network.emulateNetworkConditions',at==='slow'?slow:network);
    await page.addInitScript(installTimestamps,paths);
    assert.equal((await page.goto(origin+paths.home+'?'+fixtureQuery,{waitUntil:'commit',timeout:15000})).status(),200);
    try {
      await page.waitForFunction(()=>window.search3ReleasePairTiming?.ready>0,{},{timeout:8000});
    } catch (error) {
      throw new Error('Ready '+profile.name+'/'+mode+': '+JSON.stringify({errors,rejected:server.rejected,blocked}),{cause:error});
    }
    await page.waitForFunction(()=>performance.getEntriesByName('first-contentful-paint','paint').length>0);
    const ready=await page.evaluate(()=>{
      const nav=performance.getEntriesByType('navigation')[0], listed=performance.getEntriesByType('resource')
        .filter(row=>new URL(row.name).pathname.endsWith('/visual-search/styles.css'));
      if(listed.length!==1)throw Error('Expected a single CSS request, got '+listed.length);
      const css=listed[0];
      return {
        button:window.search3ReleasePairTiming.ready,dpr:devicePixelRatio,width:innerWidth,
        firstPaint:performance.getEntriesByName('first-contentful-paint','paint')[0].startTime,
        cssBytes:css.encodedBodySize,cssStarts:css.startTime-nav.responseStart,cssEnds:css.responseEnd-nav.responseStart,
        cssTransferred:css.transferSize,touch:navigator.maxTouchPoints>0,form:{
          meal:document.querySelector('#quick-meal')?.textContent?.trim(),
          budget:document.querySelector('#quick-budget')?.textContent?.trim()
        }
      };
    });
    assert.equal(ready.dpr,profile.dpr);
    assert.equal(ready.width,profile.width);
    assert.equal(ready.touch,profile.touch);
    assert(ready.form.meal&&ready.form.budget);
    const button=page.locator('#search-form .search-submit');
    await Promise.all([
      page.waitForURL(url=>url.pathname===paths.search,{timeout:12000}),
      profile.touch?button.tap({timeout:8000}):button.click({timeout:8000})
    ]);
    try {
      await page.waitForFunction(()=>window.search3ReleasePairTiming?.firstCard>0,{},{timeout:8000});
    } catch(error) {
      throw new Error('First card '+profile.name+'/'+mode+': '+JSON.stringify({errors,rejected:server.rejected,blocked}),{cause:error});
    }
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
         cardDOM:marks.inserted,navResponse:nav.responseStart};
    });
    assert.equal(timing.app.length,1,'Exactly one versioned app resource');
    assert.equal(timing.css.length,1,'Exactly one visual stylesheet resource');
    assert(timing.app[0].encoded>0&&timing.css[0].encoded>0);
    assert(timing.firstCard>0 && Number.isFinite(timing.afterApp));
    assert(timing.form.meal&&timing.form.budget&&timing.form.max==='240000');
    assert.equal(timing.app[0].bytes,0,'The app must still be warm.');
    assert.equal(timing.css[0].bytes,0,'The CSS hint must reuse the exact common URL and cache.');
    assert(ready.cssTransferred>ready.cssBytes&&ready.cssBytes>0);
    const cards = await page.locator('#cards .hotel-card').evaluateAll(nodes=>nodes.map(node=>[
      node.id,node.querySelector('h3')?.textContent,node.querySelector('.starting-price strong')?.textContent
    ]));
    assert(cards.length&&cards[0][2],'A real priced fixture card is visible.');
    assert.deepEqual(docs,[paths.home,paths.search]);
    assert.deepEqual(photos,profile.hero?[profile.hero]:[]);
    assert.deepEqual(blocked,[]);
    assert.deepEqual(errors,[]);
    return {button:ready.button,homePaint:ready.firstPaint,cssBytes:ready.cssBytes,cssStarts:ready.cssStarts,cssEnds:ready.cssEnds,...timing,cards};
  } finally {
    await context.close();
  }
}

async function startServer(nextHome, fixture, paths) {
  let origin,mode='before';
  const cache = new Map();
  const rejected = [];
  function respond(request,response) {
    const url = new URL(request.url,origin);
    if (request.method!=='GET' || url.origin!==origin) throw Error('unexpected request '+request.method+' '+url.pathname);
    const isSheet=url.pathname.endsWith('/visual-search/styles.css');
    const key=url.pathname;
    let item=cache.get(key);
    if(isSheet)assert.equal(url.pathname+url.search,fixture.url);
    if (!item) {
      let body, type;
      if (url.pathname===paths.home || url.pathname===paths.search) {
        body = Buffer.from(url.pathname===paths.home?fixture.home:fixture.search);
        type = 'text/html; charset=utf-8';
      } else if (isSheet) {
        body=fixture.sheet;
        type='text/css; charset=utf-8';
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
    if (mode==='after'&&url.pathname===paths.home) headers.Link='<'+fixture.url+'>; rel=preload; as=style';
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
module.exports=async function compareCssLinkHeader(browser,nextHome,output) {
  const fixture=exactFixture(nextHome),paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const server=await startServer(nextHome,fixture,paths),groups=[];
  try {
    for(const profile of profiles)for(const at of ['slow','normal']){
      const pairs=[],trials=6;
      for(let trial=0;trial<trials+1;trial++){
        const pair={};
        for(const mode of trial%2?['after','before']:['before','after']){
          pair[mode]=await sample(browser,server,server.origin,paths,profile,mode,at);
          assert.deepEqual(server.rejected,[]);
        }
        assert.deepEqual(pair.before.cards,pair.after.cards);
        assert.deepEqual(pair.before.form,pair.after.form);
        assert.equal(pair.before.cssBytes,pair.after.cssBytes);
        if(trial>0)pairs.push(pair);
      }
      const summary=Object.fromEntries(['button','homePaint','firstCard','cssStarts','cssEnds'].map(key=>[key,summarize(pairs,row=>row[key])]));
      const group={profile,at,trials,summary};
      console.log('SEARCH3_CSS_LINK_HEADER '+JSON.stringify(group));
      groups.push({...group,pairs});
    }
    fs.writeFileSync(path.join(output,'canonical-next-css-link.json'),JSON.stringify({
       baseline:'30fb66d69f09754fbc70cec0df3da56d4262722a',runnerCommit:process.env.SEARCH3_SOURCE_SHA,
       browser:browser.version(),groups
    },null,2)+'\n');
  } finally {await server.close();}
};
