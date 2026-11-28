'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { gzipSync } = require('node:zlib');
const { canonicalLogoFixture } = require('./search3-visual-live-fixture.cjs');

const root=path.resolve(__dirname,'..');
const digest=bytes=>createHash('sha256').update(bytes).digest('hex');
const compactName='home-hero-v1-680.jpg';
const fullName='home-hero-v1.jpg';
const baseCommit='369d6fbd610644f1a1b999683fa8ded7dbddd8e4';
const previous='31aa05eef7d2d04dad0dcc9934a85b75dd884b1a';
const network={offline:false,latency:40,downloadThroughput:1250000,uploadThroughput:625000,connectionType:'ethernet'};
const slow={...network,latency:80,downloadThroughput:250000,uploadThroughput:125000};
const profiles=[
 {name:'desktop-slow',width:1280,height:720,dpr:1,network:slow,hero:compactName,trials:8},
 {name:'desktop',width:1280,height:720,dpr:1,network,hero:compactName,trials:8},
 {name:'iphone',width:430,height:932,dpr:3,network,hero:null,trials:4},
 {name:'desktop-2x',width:1280,height:720,dpr:2,network,hero:fullName,trials:4}
];
const query=new URLSearchParams({
 scenario:'family',origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-07',
 minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'
});
const contentTypes={
 '.css':'text/css; charset=utf-8','.js':'application/javascript; charset=utf-8',
 '.json':'application/json; charset=utf-8','.svg':'image/svg+xml','.ico':'image/x-icon',
 '.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.webp':'image/webp',
 '.woff2':'font/woff2','.woff':'font/woff'
};

function versions(nextHome){
 const diff=execFileSync('git',['diff','--name-only',previous,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
 assert.deepEqual(diff,[
  'tests/capture-canonical-next-journey.cjs','tests/search3-entry-owner-browser.cjs',
  'tests/search3-visual-next-hero-transfer.cjs','v2/home-hero-v1-680.jpg'
 ]);
 const home=nextHome.prefix+'/',search=home+'poisk-turov/';
 const original=nextHome.html.get(home),receiver=nextHome.html.get(search);
 const before=execFileSync('git',['show',previous+':v2/'+compactName],{cwd:root});
 const after=fs.readFileSync(path.join(nextHome.payload,compactName));
 assert.equal(before.length,73342);
 assert.equal(after.length,63442);
 assert.equal(digest(before),'bd51899a8764c73d43668b39912b9318867e2cce9c3a0cced597d0ffab7fbb2c');
 assert.equal(digest(after),'842743a8423a6ccba74913f658b56491629454221f946b65da50657cf254e7e3');
 assert.equal(digest(fs.readFileSync(path.join(nextHome.payload,fullName))),'982bd6b34bef3ce9d8c85644d910e010ed492bb27aa01287a391ba1e59c2e193');
 assert.equal(digest(fs.readFileSync(path.join(nextHome.payload,'visual-search','app.js'))),'dcb19a092f463d387964ac3383753446effde8c8836e3f68a5d7362adabca1db');
 const href=b=>home+compactName+'?v='+digest(b).slice(0,16);
 const heroImg=/<img\b[^>]*src="[^"]*\/home-hero-v1\.jpg\?v=[a-f0-9]{16}"[^>]*>/g;
 const matches=[...original.matchAll(heroImg)];
 assert.equal(matches.length,1);
 assert.equal(original.split(href(after)).length,2);
 const instrumented=original.replace(matches[0][0],()=>matches[0][0].replace('<img ','<img elementtiming="next-home-hero" '));
 return {before:{home:instrumented.replace(href(after),href(before)),search:receiver,hero:before,heroURL:href(before)},
  after:{home:instrumented,search:receiver,hero:after,heroURL:href(after)}};
}

function installHomeMeasures(route){
 if(location.pathname!==route)return;
 window.search3HeroBenchmark={hero:null,button:null};
 const measurements=window.search3HeroBenchmark;
 const observer=new PerformanceObserver(list=>{
  for(const item of list.getEntries()){
   if(item.identifier==='next-home-hero'&&item.renderTime>0)measurements.hero??=item.renderTime;
  }
 });
 observer.observe({type:'element',buffered:true});
 let pending=false;
 const observe=new MutationObserver(check);
 const find=()=>document.querySelector('#search-form:not([aria-busy="true"]) .search-submit:not([disabled])');
 function check(){
  if(pending||!find())return;
  pending=true;
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
   const button=find(),rect=button?.getBoundingClientRect();
   if(!rect||rect.bottom>innerHeight+1||rect.height===0){
    pending=false;
    requestAnimationFrame(check);
    return;
   }
   measurements.button=performance.now();
   observe.disconnect();
  }));
 }
 observe.observe(document,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','aria-busy','hidden']});
}

async function sample(browser,server,paths,profile,mode,output,save){
 server.select(mode);
 const context=await browser.newContext({
  viewport:{width:profile.width,height:profile.height},deviceScaleFactor:profile.dpr,
  hasTouch:profile.name==='iphone',isMobile:profile.name==='iphone',serviceWorkers:'block',
  reducedMotion:'reduce',proxy:{server:server.origin,bypass:'127.0.0.1'}
 });
 try {
  const unexpected=[],errors=[];
  const page=await context.newPage();
  page.on('pageerror',e=>errors.push(e.message));
  page.on('request',request=>{if(new URL(request.url()).origin!==server.origin)unexpected.push(new URL(request.url()).pathname);});
  const client=await context.newCDPSession(page);
  await client.send('Network.enable');
  await client.send('Network.emulateNetworkConditions',profile.network);
  await page.addInitScript(installHomeMeasures,paths.home);
  assert.equal((await page.goto(server.origin+paths.home+'?'+query,{waitUntil:'commit',timeout:15000})).status(),200);
  await page.waitForFunction(()=>window.search3HeroBenchmark?.button>0);
  const element=page.locator('.home-hero-image img');
  const hero=await element.evaluate(async img=>{
   await img.decode();
   const a=img.getBoundingClientRect();
   return {src:img.currentSrc,width:img.naturalWidth,height:img.naturalHeight,box:[a.x,a.y,a.width,a.height]};
  });
  if(profile.hero)await page.waitForFunction(()=>window.search3HeroBenchmark?.hero>0);
  else assert.match(hero.src,/^data:image\/gif;/);
  await page.waitForFunction(()=>performance.getEntriesByName('first-contentful-paint','paint').length>0);
  const timing=await page.evaluate(()=>{
   const m=window.search3HeroBenchmark;
   const resources=performance.getEntriesByType('resource').filter(r=>/\/home-hero-v1(?:-680)?\.jpg\?/.test(r.name));
   return {button:m.button,hero:m.hero,firstPaint:performance.getEntriesByName('first-contentful-paint','paint')[0].startTime,
    choice:resources.map(r=>({file:new URL(r.name).pathname.split('/').at(-1),body:r.encodedBodySize,end:r.responseEnd,transfer:r.transferSize})),
    form:{meal:document.querySelector('#quick-meal')?.textContent?.trim(),budget:document.querySelector('#quick-meal + #quick-budget')?.textContent?.trim(),
      guests:document.querySelector('#guests-label')?.textContent?.trim()}};
  });
  assert(timing.form.meal&&timing.form.budget&&timing.form.guests);
  assert.deepEqual(timing.choice.map(r=>r.file),profile.hero?[profile.hero]:[]);
  if(profile.hero===compactName)assert.equal(timing.choice[0].body,mode==='before'?73342:63442);
  if(profile.hero===fullName)assert.equal(timing.choice[0].body,90086);
  if(save){
   await page.evaluate(()=>document.fonts.ready);
   await page.screenshot({path:path.join(output,'canonical-next-hero-'+profile.name+'-'+mode+'.png'),animations:'disabled'});
  }
  const button=page.locator('#search-form .search-submit');
  await Promise.all([page.waitForURL(url=>url.pathname===paths.search),
    profile.name==='iphone'?button.tap():button.click()]);
  const first=page.locator('#hotel-900001 .starting-price strong');
  await first.waitFor();
  assert.equal(Number((await first.textContent()).replace(/\D/g,'')),186400);
  assert.equal(new URL(page.url()).searchParams.get('max'),'240000');
  assert.deepEqual(errors,[]);assert.deepEqual(unexpected,[]);assert.deepEqual(server.rejected,[]);
  return {...timing,geometry:{box:hero.box,width:hero.width,height:hero.height}};
 }finally{await context.close();}
}

async function startServer(nextHome, snapshots, paths) {
  let origin,mode='before';
  const cache = new Map();
  const rejected = [];
  function respond(request,response) {
    const url = new URL(request.url,origin);
    if (request.method!=='GET' || url.origin!==origin) throw Error('unexpected request '+request.method+' '+url.pathname);
    const variant=snapshots[mode],isHero=url.pathname.endsWith('/'+compactName);
    const isDocument=url.pathname===paths.home||url.pathname===paths.search;
    const key=(isDocument||isHero?mode:'')+url.pathname;
    let item=cache.get(key);
    if(isHero)assert.equal(url.pathname+url.search,variant.heroURL);
    if (!item) {
      let body, type;
      if (url.pathname===paths.home || url.pathname===paths.search) {
        body = Buffer.from(url.pathname===paths.home?variant.home:variant.search);
        type = 'text/html; charset=utf-8';
      } else if (isHero) {
        body=variant.hero;
        type='image/jpeg';
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

module.exports=async function compareNextHomeHero(browser,nextHome,output){
 // Only the candidate built directly upon the current photo needs this paired qualification.
 if(execFileSync('git',['rev-parse','HEAD^'],{cwd:root,encoding:'utf8'}).trim()!==baseCommit)return;
 const snapshots=versions(nextHome),paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const server=await startServer(nextHome,snapshots,paths);
 const groups=[];
 try {
  for(const profile of profiles){
   const pairs=[];
   for(let i=0;i<profile.trials+2;i++){
    const pair={};
    for(const mode of i%2?['after','before']:['before','after']){
     pair[mode]=await sample(browser,server,paths,profile,mode,output,i===profile.trials+1);
    }
    assert.deepEqual(pair.before.geometry,pair.after.geometry);
    assert.deepEqual(pair.before.form,pair.after.form);
    if(i>=2)pairs.push(pair);
   }
   const m={firstPaint:row=>row.firstPaint,button:row=>row.button};
   if(profile.hero){m.hero=row=>row.hero;m.imageEnd=row=>row.choice[0].end;}
   const stats=Object.fromEntries(Object.entries(m).map(([k,v])=>[k,summarize(pairs,v)]));
   const group={profile,stats,expectedCompaction:profile.hero===compactName?9900:0};
   console.log('SEARCH3_HOME_HERO_TRANSFER '+JSON.stringify(group));
   groups.push({...group,pairs});
  }
  fs.writeFileSync(path.join(output,'canonical-next-hero-transfer.json'),JSON.stringify({
   installedSource:previous,runnerCommit:process.env.SEARCH3_SOURCE_SHA,browserVersion:browser.version(),
   before:digest(snapshots.before.hero),after:digest(snapshots.after.hero),groups
  },null,2)+'\n');
 }finally{await server.close();}
};
