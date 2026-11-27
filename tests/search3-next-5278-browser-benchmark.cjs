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

function versions(nextHome) {
  const changes = execFileSync('git',['diff','--name-only',commits.before,commits.after],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
  assert.deepEqual(changes,['tests/search3-entry-owner-browser.cjs','v2/' + fullName]);
  const harnessChanges = execFileSync('git',['diff','--name-only',commits.after,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
  assert.deepEqual(harnessChanges,['tests/search3-entry-owner-browser.cjs','tests/search3-next-5278-browser-benchmark.cjs']);
  const commonFiles = {
    'visual-search/app.js':'dcb19a092f463d387964ac3383753446effde8c8836e3f68a5d7362adabca1db',
    'visual-search/styles.css':'544533a067bb7f9d70f83c56aeb78ccdb8fb38522ce2668ed32b57f28c822e44',
    [compactName]:'bd51899a8764c73d43668b39912b9318867e2cce9c3a0cced597d0ffab7fbb2c'
  };
  for (const [name,hash] of Object.entries(commonFiles)) {
    assert.equal(digest(fs.readFileSync(path.join(nextHome.payload,name))),hash,name);
  }
  const before = execFileSync('git',['show',commits.before+':v2/'+fullName],{cwd:root,maxBuffer:512*1024});
  const after = fs.readFileSync(path.join(nextHome.payload,fullName));
  assert.deepEqual([before.length,after.length],[109085,90086]);
  assert.deepEqual([digest(before),digest(after)],[hashes.before,hashes.after]);
  const homePath = nextHome.prefix+'/';
  const home = nextHome.html.get(homePath);
  const originalURL = homePath+fullName+'?v='+hashes.before.slice(0,16);
  const newURL = homePath+fullName+'?v='+hashes.after.slice(0,16);
  assert.equal(home.split(newURL).length,2,'One content-versioned desktop JPEG link.');
  return {
    before:{home:home.replace(newURL,originalURL),image:before,url:originalURL},
    after:{home,image:after,url:newURL}
  };
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
        window.search3ReleasePairTiming = { ready:performance.now(), firstCard:clicked?absoluteNow()-clicked:null };
        observer.disconnect();
      }
    }));
  }
  observer.observe(document,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','aria-busy','hidden']});
}

async function sample(browser, server, origin, paths, profile, mode) {
  server.select(mode);
  const context = await browser.newContext({
    viewport:{width:profile.width,height:profile.height},
    deviceScaleFactor:profile.dpr, isMobile:profile.touch, hasTouch:profile.touch,
    serviceWorkers:'block', reducedMotion:'reduce'
  });
  try {
    const blocked = [], errors = [], docs = [], photos = [];
    await context.route(url=>url.origin!==origin, route=>{
      blocked.push(new URL(route.request().url()).pathname);
      return route.abort();
    });
    const page = await context.newPage();
    page.on('pageerror', error=>errors.push(error.message));
    page.on('request', request=>{
      const url = new URL(request.url());
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
    const button = page.locator('#search-form .search-submit');
    await Promise.all([
      page.waitForURL(url=>url.pathname===paths.search,{timeout:12000}),
      profile.touch ? button.tap({timeout:8000}) : button.click({timeout:8000})
    ]);
    await page.waitForFunction(()=>window.search3ReleasePairTiming?.firstCard>0,{},{timeout:12000});
    const firstCard = await page.evaluate(()=>window.search3ReleasePairTiming.firstCard);
    const cards = await page.locator('#cards .hotel-card').evaluateAll(nodes=>nodes.map(node=>[
      node.id,node.querySelector('h3')?.textContent,node.querySelector('.starting-price strong')?.textContent
    ]));
    assert(cards.length&&cards[0][2],'A real priced fixture card is visible.');
    assert.deepEqual(docs,[paths.home,paths.search]);
    assert.deepEqual(photos,profile.hero?[profile.hero]:[]);
    assert.deepEqual(blocked,[]);
    assert.deepEqual(errors,[]);
    return {button:ready.button,firstCard,form:ready.form,cards};
  } finally {
    await context.close();
  }
}

async function startServer(nextHome, snapshots, paths) {
  let mode = 'before', origin;
  const cache = new Map();
  const rejected = [];
  function respond(request,response) {
    const url = new URL(request.url,origin);
    if (request.method!=='GET' || url.origin!==origin) throw Error('unexpected request '+request.method+' '+url.pathname);
    const variant = snapshots[mode];
    const key = url.pathname===paths.home || url.pathname===paths.home+fullName ? mode+url.pathname : url.pathname;
    let item = cache.get(key);
    if (url.pathname===paths.home+fullName) assert.equal(url.pathname+url.search,variant.url);
    if (!item) {
      let body, type;
      if (url.pathname===paths.home || url.pathname===paths.search) {
        body = Buffer.from(url.pathname===paths.home?variant.home:nextHome.html.get(paths.search));
        type = 'text/html; charset=utf-8';
      } else if (url.pathname===paths.home+fullName) {
        body = variant.image;
        type = 'image/jpeg';
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
      const compress = /^(text\/|application\/)/.test(type);
      item = { body, type, gzip:compress?gzipSync(body):null };
      cache.set(key,item);
    }
    const gzip = item.gzip && /\bgzip\b/.test(String(request.headers['accept-encoding']));
    const body = gzip?item.gzip:item.body;
    const headers = {'Content-Type':item.type,'Content-Length':body.length,'Cache-Control':'no-store'};
    if (gzip) headers['Content-Encoding']='gzip';
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
  const snapshots=versions(nextHome);
  const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const server=await startServer(nextHome,snapshots,paths);
  const groups=[];
  try {
    for (const profile of profiles) {
      const pairs=[];
      for (let index=0;index<12;index++) {
        const pair={};
        for (const mode of index%2?['after','before']:['before','after']) {
          pair[mode]=await sample(browser,server,server.origin,paths,profile,mode);
          assert.deepEqual(server.rejected,[]);
        }
        assert.deepEqual(pair.before.cards,pair.after.cards,'Same fixture tour order and prices.');
        assert.deepEqual(pair.before.form,pair.after.form,'Same meal and budget.');
        if (index>=2) pairs.push(pair);
      }
      const result={profile};
      for (const metric of ['button','firstCard']) {
        const delta=pairs.map(pair=>pair.after[metric]-pair.before[metric]);
        result[metric]={
          before:median(pairs.map(pair=>pair.before[metric])),
          after:median(pairs.map(pair=>pair.after[metric])),
          pairedDelta:median(delta), faster:delta.filter(value=>value<0).length,
          minDelta:Math.min(...delta), maxDelta:Math.max(...delta)
        };
      }
      console.log('SEARCH3_RELEASE_5278_PAIR '+JSON.stringify(result));
      groups.push({...result,pairs});
    }
    fs.writeFileSync(path.join(output,'canonical-next-home-5278-timing.json'),JSON.stringify({
      commits, hashes, runnerCommit:process.env.SEARCH3_SOURCE_SHA, browserVersion:browser.version(),
      network, trialsPerGroup:10, warmupPairs:2, groups
    },null,2)+'\n');
  } finally { await server.close(); }
};
