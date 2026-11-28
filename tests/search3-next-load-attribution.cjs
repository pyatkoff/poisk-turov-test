'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');

const source='007bf45644e827c400b91719778b8ab5defe8e62';
const pinned={
 appSha:'d207bc7bbd865d1a1b5d2f61004ce3f5a0bd00804795dc11cd7d3e9151b26231',
 sheetSha:'cccc1c6a2c01d765c9c524b301d70df3d95205343cf0754dd0ebee4e9f09feee'
};
const screens=[{width:430,height:932,dpr:3,touch:true},{width:1280,height:720,dpr:1,touch:false}];
const networks=[{mbps:2,rtt:80},{mbps:10,rtt:40}];
const resourceNames=['app.js','styles.css','mobile-controls-v1.css','public-config-v1.js','public-bootstrap-v1.js',
 'fixture-data.js','search-lifecycle-v1.js','filter-panel-v1.js','photo-1.jpg','photo-2.jpg','home-hero-v1-680.jpg'];
const searchQuery=new URLSearchParams({scenario:'family',origin:'Москва',country:'4',from:'2026-12-09',to:'2026-12-15',
 minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'}).toString();

function recordPage(paths){
 const isHome=location.pathname===paths.home;
 if(!isHome&&location.pathname!==paths.search)return;
 const marks={},clickKey='search3-next-attribution-click',clock=()=>performance.now();
 window.nextLoadAttribution=marks;
 const active='#search-form .search-submit:not([disabled])';
 const mark=(key)=>{marks[key]??=clock();};
 document.addEventListener('load',event=>{
  const el=event.target;
  if(el?.tagName==='SCRIPT'&&el.src&&new URL(el.src).pathname.endsWith('/visual-search/app.js'))mark('appLoad');
 },true);
 if(isHome)document.addEventListener('click',event=>{
  if(event.isTrusted&&event.target.closest?.(active))sessionStorage.setItem(clickKey,String(performance.timeOrigin+clock()));
 },true);
 else marks.clickRelative=Number(sessionStorage.getItem(clickKey))-performance.timeOrigin;

 let adapter;
 Object.defineProperty(window,'AnyTourPrototypeData',{
  configurable:true,get:()=>adapter,set(data){
   const descriptors=Object.getOwnPropertyDescriptors(data);
   const method=isHome?'init':'search',descriptor=descriptors[method];
   if(typeof descriptor?.value!=='function'){adapter=data;return;}
   const invoke=descriptor.value;
   descriptors[method]={...descriptor,value:isHome?function(...args){
    mark('catalogStart');
    return Promise.resolve(Reflect.apply(invoke,this,args)).then(value=>{mark('catalogReady');return value;});
   }:function(query,emit,...args){
    mark('searchStart');
    return Reflect.apply(invoke,this,[query,event=>{
     if(event?.type==='results'&&event.hotels?.length)mark('firstPacket');
     return emit(event);
    },...args]);
   }};
   adapter=Object.freeze(Object.create(Object.getPrototypeOf(data),descriptors));
  }
 });
 const target=isHome?active:'#cards .hotel-card h3';
 let pending=false,photoStarted=false;
 function readyTarget(){
  const element=document.querySelector(target);
  if(!element||element.closest('[hidden],[inert]'))return null;
  if(isHome&&element.closest('#search-form')?.getAttribute('aria-busy')==='true')return null;
  if(!isHome&&!element.closest('.hotel-card').querySelector('.starting-price strong')?.textContent)return null;
  return element;
 }
 function photo(){
  if(photoStarted)return;
  const image=document.querySelector('#cards .hotel-card .hotel-image');
  if(!image||!image.complete||!image.naturalWidth)return;
  photoStarted=true;
  mark('photoLoaded');
  image.decode().then(()=>requestAnimationFrame(()=>requestAnimationFrame(()=>{
   const rect=image.getBoundingClientRect();
   if(rect.width>0&&rect.height>0&&rect.bottom>0&&rect.top<innerHeight)mark('photoFrame');
  })),()=>{marks.photoError=true;});
 }
 if(!isHome)document.addEventListener('load',event=>{
  if(event.target?.matches?.('#cards .hotel-card .hotel-image'))photo();
 },true);
 function check(){
  if(!isHome)photo();
  if(pending||!readyTarget())return;
  mark('domReady');pending=true;
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
   const el=readyTarget(),box=el?.getBoundingClientRect();
   const hit=box&&document.elementFromPoint(box.left+box.width/2,box.top+box.height/2);
   if(!box||box.width<=0||box.height<=0||box.top<0||box.bottom>innerHeight+1||!el.contains(hit)){
    pending=false;requestAnimationFrame(check);return;
   }
   mark('visible');observer.disconnect();
  }));
 }
 const observer=new MutationObserver(check);
 observer.observe(document,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','aria-busy','hidden']});
}

function readPage(tracked){
 const marks=window.nextLoadAttribution;
 const nav=performance.getEntriesByType('navigation')[0];
 const resources=Object.fromEntries(performance.getEntriesByType('resource').flatMap(row=>{
  const name=new URL(row.name).pathname.split('/').pop();
  if(!tracked.includes(name))return [];
  return [[name,{start:row.startTime,responseStart:row.responseStart,end:row.responseEnd,encoded:row.encodedBodySize,transferred:row.transferSize}]];
 }));
 return {marks,resources,navigation:{start:nav.startTime,responseStart:nav.responseStart,end:nav.responseEnd,
   domInteractive:nav.domInteractive,domContentLoaded:nav.domContentLoadedEventEnd},
  paint:performance.getEntriesByName('first-contentful-paint','paint')[0]?.startTime??null,
  touch:navigator.maxTouchPoints>0,width:innerWidth,height:innerHeight,dpr:devicePixelRatio,scroll:scrollY};
}
async function flow(page,origin,paths,screen){
 const request=await page.goto(origin+paths.home+'?'+searchQuery,{waitUntil:'commit'});
 assert.equal(request.status(),200);
 await page.waitForFunction(()=>window.nextLoadAttribution?.visible>0&&window.nextLoadAttribution?.appLoad>0&&
  performance.getEntriesByName('first-contentful-paint','paint').length>0,{},{timeout:12000});
 const home=await page.evaluate(readPage,resourceNames);
 assert.equal(home.width,screen.width);assert.equal(home.height,screen.height);assert.equal(home.touch,screen.touch);
 assert.equal(home.dpr,screen.dpr);
 assert(home.marks.catalogReady>=home.marks.catalogStart);
 for(const name of ['app.js','styles.css'])assert(home.resources[name].encoded>0,name);
 assert.equal(await page.locator('#quick-meal + #quick-budget').count(),1);
 const button=page.locator('#search-form .search-submit');
 await Promise.all([page.waitForURL(url=>url.pathname===paths.search,{timeout:10000}),screen.touch?button.tap():button.click()]);
 await page.waitForFunction(()=>window.nextLoadAttribution?.visible>0&&window.nextLoadAttribution?.photoFrame>0&&window.nextLoadAttribution?.appLoad>0,{},{timeout:16000});
 const result=await page.evaluate(readPage,resourceNames);
 const cards=await page.locator('#cards .hotel-card').evaluateAll(nodes=>nodes.map(node=>[
  node.id,node.querySelector('h3')?.textContent,node.querySelector('.starting-price strong')?.textContent
 ]));
 const url=new URL(page.url());
 assert.equal(url.searchParams.get('max'),'240000');
 assert(cards[0]?.[2]);assert.equal(result.scroll,0);
 assert(result.marks.firstPacket>=result.marks.searchStart);
 assert(result.marks.photoFrame>=result.marks.photoLoaded);
 assert(result.marks.clickRelative<0);
 assert.equal(result.resources['app.js'].transferred,0);
 assert.equal(result.resources['styles.css'].transferred,0);
 return {home,result,cards};
}
async function pair(browser,server,paths,screen,net){
 const origin=server.origin,context=await browser.newContext({
  viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,hasTouch:screen.touch,isMobile:screen.touch,
  serviceWorkers:'block',reducedMotion:'reduce',proxy:{server:origin,bypass:'127.0.0.1'}
 });
 try{
  const page=await context.newPage(),unexpected=[],errors=[];
  page.on('request',r=>{const url=new URL(r.url());if(url.origin!==origin)unexpected.push(url.pathname);});
  page.on('pageerror',r=>errors.push(r.message));
  const client=await context.newCDPSession(page);
  await client.send('Network.enable');
  await client.send('Network.emulateNetworkConditions',{offline:false,latency:net.rtt,
   downloadThroughput:net.mbps*125000,uploadThroughput:net.mbps*125000,connectionType:net.mbps===2?'cellular3g':'wifi'});
  await page.addInitScript(recordPage,paths);
  const cold=await flow(page,origin,paths,screen),warm=await flow(page,origin,paths,screen);
  assert.deepEqual(warm.cards,cold.cards);assert.deepEqual(unexpected,[]);assert.deepEqual(errors,[]);
  assert.deepEqual(server.rejected,[]);
  for(const name of ['app.js','styles.css']){
   assert(cold.home.resources[name].transferred>0);
   assert.equal(warm.home.resources[name].transferred,0);
  }
  return {cold,warm};
 }finally{await context.close();}
}
const median=values=>{const sorted=values.slice().sort((a,b)=>a-b),i=Math.floor(sorted.length/2);return sorted.length%2?sorted[i]:(sorted[i-1]+sorted[i])/2;};
function asPhases(row){
 const h=row.home,r=row.result;
 return {
  button:h.marks.visible,homePaint:h.paint,documentResponse:h.navigation.responseStart,
  cssEnd:h.resources['styles.css'].end,appEnd:h.resources['app.js'].end,appLoad:h.marks.appLoad,
  catalogStart:h.marks.catalogStart,catalogReady:h.marks.catalogReady,
  catalogWait:h.marks.catalogReady-h.marks.catalogStart,buttonAfterCatalog:h.marks.visible-h.marks.catalogReady,
  appLoadAfterBytes:h.marks.appLoad-h.resources['app.js'].end,
  firstCard:r.marks.visible-r.marks.clickRelative,photo:r.marks.photoFrame-r.marks.clickRelative,
  firstPacket:r.marks.firstPacket-r.marks.searchStart,
  photoAfterCard:r.marks.photoFrame-r.marks.visible
 };
}

module.exports=async function measureNextLoad(browser,nextHome,output){
 const changed=execFileSync('git',['diff','--name-only',source,'HEAD'],{cwd:path.resolve(__dirname,'..'),encoding:'utf8'}).trim().split('\n').sort();
 assert.deepEqual(changed,['tests/search3-entry-owner-browser.cjs','tests/search3-next-compact-calendar.cjs','tests/search3-next-load-attribution.cjs']);
 const fixture=exactAssets(nextHome);
 for(const [key,sha] of Object.entries(pinned))assert.equal(fixture[key],sha);
 const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const server=await startServer(nextHome,fixture,paths),groups=[];
 try{
  for(const net of networks)for(const screen of screens){
   const pairs=[];
   for(let i=0;i<6;i++){
    const results=await pair(browser,server,paths,screen,net);
    if(i>0)pairs.push(results);
   }
   const summary=Object.fromEntries(['cold','warm'].map(cache=>{
    const observations=pairs.map(probe=>asPhases(probe[cache]));
    return [cache,Object.fromEntries(Object.keys(observations[0]).map(key=>[key,median(observations.map(x=>x[key]))]))];
   }));
   groups.push({screen,net,summary,pairs});
   console.log('SEARCH3_LOAD_ATTRIBUTION '+JSON.stringify({screen,net,summary}));
  }
  fs.writeFileSync(path.join(output,'canonical-next-load-attribution.json'),JSON.stringify({
   source,runnerCommit:process.env.SEARCH3_SOURCE_SHA,...pinned,browser:browser.version(),pairsPerGroup:5,groups
  },null,2)+'\n');
 }finally{await server.close();}
};
