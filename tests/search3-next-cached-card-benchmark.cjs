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
const commits = {
  before: 'eb6cd3afb123ae6112684f55101470454be07d65',
  after: '4fac391861004555f50d4d8c47272e38bc7d2dd2'
};
const hashes = {
  before: '1968129469a1f348df4a7246f4d45cf9d4404e8e30f27806fda95b69a80ed82e',
  after: '982bd6b34bef3ce9d8c85644d910e010ed492bb27aa01287a391ba1e59c2e193'
};
const fixtureQuery = new URLSearchParams({
  scenario: 'family', origin: 'Москва', country: '4', from: '2026-10-01', to: '2026-10-07',
  minNights: '7', maxNights: '7', adults: '3', ages: '0,12', stars: '4|5',
  meals: 'Всё включено', max: '240000'
});
const network = { offline:false, latency:40, downloadThroughput:1250000, uploadThroughput:625000, connectionType:'ethernet' };
const profiles = [
  { name:'iphone', width:430, height:932, dpr:3, touch:true, hero:null },
  { name:'desktop', width:1280, height:720, dpr:1, touch:false, hero:compactName },
  { name:'desktop-2x', width:1280, height:720, dpr:2, touch:false, hero:fullName }
];
const contentTypes = {
  '.css':'text/css; charset=utf-8', '.js':'application/javascript; charset=utf-8',
  '.json':'application/json; charset=utf-8', '.svg':'image/svg+xml',
  '.ico':'image/x-icon', '.png':'image/png', '.jpg':'image/jpeg', '.jpeg':'image/jpeg',
  '.webp':'image/webp', '.woff2':'font/woff2', '.woff':'font/woff'
};

async function versions(nextHome) {
  const tracked = execFileSync('git',['diff','--name-only',commits.after,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
  assert.deepEqual(tracked,[
    'tests/search3-entry-owner-browser.cjs','tests/search3-next-cached-card-benchmark.cjs','tests/search3-visual-next-home-timing.cjs',
    'v2/visual-search/entry-v1.php','v2/visual-search/migration-manifest.json','v2/visual-search/public-bootstrap-v1.js'
  ]);
  const commonFiles = {
    'visual-search/app.js':'dcb19a092f463d387964ac3383753446effde8c8836e3f68a5d7362adabca1db',
    'visual-search/styles.css':'544533a067bb7f9d70f83c56aeb78ccdb8fb38522ce2668ed32b57f28c822e44',
    [compactName]:'bd51899a8764c73d43668b39912b9318867e2cce9c3a0cced597d0ffab7fbb2c',
    [fullName]:hashes.after
  };
  for (const [name,hash] of Object.entries(commonFiles)) {
    assert.equal(digest(fs.readFileSync(path.join(nextHome.payload,name))),hash,name);
  }
  const home=nextHome.html.get(nextHome.prefix+'/'),search=nextHome.html.get(nextHome.prefix+'/poisk-turov/');
  const scripts=['filter-panel-v1.js','flight-picker-v18.js','preview-lead.js'];
  const assets=scripts.map(name=>{
    const matches=[...search.matchAll(/<script\b[^>]*\bsrc="([^"]+)"/g)].map(row=>row[1])
      .filter(url=>url.includes('/'+name+'?v='));
    assert.equal(matches.length,1,name);
    assert(!home.includes('src="'+matches[0]+'"'),name+' is executed only in search.');
    return matches[0];
  });
  const preload=' data-search3-handoff-prefetch="';
  const field=home.match(/ data-search3-handoff-prefetch="([^"]+)"/);
  assert(field&&home.split(preload).length===2&&!search.includes(preload));
  assert.deepEqual(field[1].split(' ').sort(),assets.slice().sort(),'Hint owners have the exact search script URLs.');
  const name='visual-search/public-bootstrap-v1.js';
  const source=execFileSync('git',['show',commits.after+':v2/'+name],{cwd:root,encoding:'utf8'});
  const beforeBytes=Buffer.from(await compile(source));
  assert.equal(digest(beforeBytes),'9772352181baa938da93d5462859bddba05dd08983a9a4a39f7e7222b02c478d','Exact prior compiled bootstrap');
  const afterBytes=fs.readFileSync(path.join(nextHome.payload,name));
  const afterURL=nextHome.prefix+'/'+name+'?v='+digest(afterBytes).slice(0,12);
  const beforeURL=nextHome.prefix+'/'+name+'?v='+digest(beforeBytes).slice(0,12);
  function previous(html) {
    assert.equal(html.split(afterURL).length,2);
    return html.replace(afterURL,beforeURL);
  }
  return {before:{home:previous(home).replace(field[0],''),search:previous(search),bytes:beforeBytes,url:beforeURL},
    after:{home,search,bytes:afterBytes,url:afterURL}};


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

async function sample(browser, server, origin, paths, profile, mode, dwell) {
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
    if (dwell) await new Promise(resolve=>setTimeout(resolve,dwell));
    const button = page.locator('#search-form .search-submit');
    await Promise.all([
      page.waitForURL(url=>url.pathname===paths.search,{timeout:12000}),
      profile.touch ? button.tap({timeout:8000}) : button.click({timeout:8000})
    ]);
    await page.waitForFunction(()=>window.search3ReleasePairTiming?.firstCard>0,{},{timeout:12000});
    const {firstCard,phase,transfer,encoding}=await page.evaluate(()=>{
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
        encoding:[app?.deliveryType,css?.deliveryType]};
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
    return {button:ready.button,firstCard,phase,transfer,encoding,form:ready.form,cards};
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
    const variant=snapshots[mode],isBootstrap=url.pathname.endsWith('/visual-search/public-bootstrap-v1.js');
    const isDocument=url.pathname===paths.home||url.pathname===paths.search;
    const key=(isDocument||isBootstrap?mode:'')+url.pathname;
    let item=cache.get(key);
    if(isBootstrap)assert.equal(url.pathname+url.search,variant.url);
    if (!item) {
      let body, type;
      if (url.pathname===paths.home || url.pathname===paths.search) {
        body = Buffer.from(url.pathname===paths.home?variant.home:variant.search);
        type = 'text/html; charset=utf-8';
      } else if (isBootstrap) {
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

module.exports = async function measureReleasePair(browser,nextHome,output) {
  const snapshots=await versions(nextHome);
  const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const server=await startServer(nextHome,snapshots,paths);
  const groups=[];
  try {
    for (const profile of profiles) for (const dwell of [0,200]) {
      const pairs=[];
      for (let index=0;index<12;index++) {
        const pair={};
        for (const mode of index%2?['after','before']:['before','after']) {
          pair[mode]=await sample(browser,server,server.origin,paths,profile,mode,dwell);
          assert.deepEqual(server.rejected,[]);
        }
        assert.deepEqual(pair.before.cards,pair.after.cards,'Same fixture tour order and prices.');
        assert.deepEqual(pair.before.form,pair.after.form,'Same meal and budget.');
        if (index>=2) pairs.push(pair);
      }
      const result={profile,dwell};
      for (const metric of ['button','firstCard']) {
        const delta=pairs.map(pair=>pair.after[metric]-pair.before[metric]);
        result[metric]={
          before:median(pairs.map(pair=>pair.before[metric])),
          after:median(pairs.map(pair=>pair.after[metric])),
          pairedDelta:median(delta), faster:delta.filter(value=>value<0).length,
          minDelta:Math.min(...delta), maxDelta:Math.max(...delta)
        };
      }
      const phases=['clickToResponse','responseToAppBytes','appBytesToSearch','searchToPacket','packetToCardDOM','cardDOMToVisible'];
      result.breakdown=Object.fromEntries(phases.map((name,index)=>[name,{
        before:median(pairs.map(pair=>pair.before.phase[index])),
        after:median(pairs.map(pair=>pair.after.phase[index]))
      }]));
      result.searchOnlyTransfers={
        before:median(pairs.map(pair=>pair.before.transfer.searchOnly.reduce((n,row)=>n+row[1],0))),
        after:median(pairs.map(pair=>pair.after.transfer.searchOnly.reduce((n,row)=>n+row[1],0)))
      };
      console.log('SEARCH3_CACHE_CARD_PROFILE '+JSON.stringify(result));
      groups.push({...result,pairs});
    }
    fs.writeFileSync(path.join(output,'canonical-next-cached-card-profile.json'),JSON.stringify({
      installedSource:commits.after, baseline:'versioned HTTP cache active', candidate:'compiled NEXT code prefetches search-only scripts after form readiness', runnerCommit:process.env.SEARCH3_SOURCE_SHA, browserVersion:browser.version(),
      network, trialsPerGroup:10, warmupPairs:2, groups
    },null,2)+'\n');
  } finally { await server.close(); }
};
