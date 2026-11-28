'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const {printCSS}=require('../scripts/build/search3-js/compact-css.cjs');
const {createHash}=require('node:crypto');

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
 const marks={},clickKey='search3-next-photo-priority-click',clock=()=>performance.now();
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
 const attributes=await page.locator('#cards .hotel-card').first().evaluate(card=>({
  hero:card.querySelector('.hotel-image').getAttribute('fetchpriority'),loading:card.querySelector('.hotel-image').getAttribute('loading'),
  thumbs:[...card.querySelectorAll('.card-thumb img')].map(img=>img.getAttribute('fetchpriority'))
 }));
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
 return {home,result,cards,attributes};
}
async function sample(browser,server,paths,screen,net,mode,exercise=false){
 const origin=server.origin,context=await browser.newContext({
  viewport:{width:screen.width,height:screen.height},deviceScaleFactor:screen.dpr,hasTouch:screen.touch,isMobile:screen.touch,
  serviceWorkers:'block',reducedMotion:'reduce',proxy:{server:origin,bypass:'127.0.0.1'}
 });
 try{
  const page=await context.newPage(),unexpected=[],errors=[],tracked=new Map();
  page.on('request',r=>{const link=new URL(r.url());if(link.origin!==origin)unexpected.push(link.pathname);});
  page.on('pageerror',r=>errors.push(r.message));
  const client=await context.newCDPSession(page);
  client.on('Network.requestWillBeSent',event=>{
   const u=new URL(event.request.url),name=u.pathname.split('/').pop();
   if(u.origin===origin&&/^photo-[12]\.jpg$/.test(name))tracked.set(event.requestId,{name,priority:event.request.initialPriority,start:event.timestamp,updates:[]});
  });
  client.on('Network.resourceChangedPriority',event=>tracked.get(event.requestId)?.updates.push(event.newPriority));
  client.on('Network.loadingFinished',event=>{const item=tracked.get(event.requestId);if(item)item.finish=event.timestamp;});
  await client.send('Network.enable');
  await client.send('Network.emulateNetworkConditions',{offline:false,latency:net.rtt,
   downloadThroughput:net.mbps*125000,uploadThroughput:net.mbps*125000,connectionType:net.mbps===2?'cellular3g':'wifi'});
  await page.addInitScript(recordPage,paths);
  const measured=await flow(page,origin,paths,screen);
  const active=mode==='after'&&!screen.touch;
  assert.equal(measured.attributes.hero,active?'high':null);
  assert.equal(measured.attributes.loading,active?'eager':'lazy');
  assert.deepEqual(measured.attributes.thumbs,active?['low','low']:[null,null]);
  assert(measured.home.resources['app.js'].transferred>0);
  assert(measured.home.resources['styles.css'].transferred>0);
  if(active)await page.waitForFunction(()=>performance.getEntriesByType('resource').some(row=>new URL(row.name).pathname.endsWith('/photo-2.jpg')),{},{timeout:12000});
  const photoTrace=[...tracked.values()];
  const primary=photoTrace.filter(p=>p.name==='photo-1.jpg'&&p.finish>=p.start);
  assert.equal(primary.length,1);
  if(active){
   const thumbnails=photoTrace.filter(p=>p.name==='photo-2.jpg');
   assert.equal(thumbnails.length,1);
   assert(thumbnails[0].start>=primary[0].finish-0.001,'the secondary photo waits for the first response to complete');
  }
  if(exercise){
   const item=page.locator('#cards .hotel-card').first();
   const arrow=item.locator('.card-photo-arrow.next');
   await (screen.touch?arrow.tap():arrow.click());
   await page.waitForFunction(()=>document.querySelector('#cards .hotel-image').getAttribute('src')?.includes('photo-2.jpg'));
   const gallery=item.locator('.hotel-image-button');
   await (screen.touch?gallery.tap():gallery.click());
   await page.waitForFunction(()=>document.querySelector('#modal')?.open&&document.querySelector('#gallery-image')?.getAttribute('src')?.includes('photo-2.jpg'));
   await page.locator('#modal [data-action="close-modal"]').click();
   await page.waitForFunction(()=>!document.querySelector('#modal')?.open);
   const booking=item.locator('[data-card-entry]');
   await (screen.touch?booking.tap():booking.click());
   await page.locator('#modal[open] #modal-title').waitFor({state:'visible'});
   assert.equal((await page.locator('#modal-title').textContent()).trim(),'Ваш тур в деталях');
  }
  assert.deepEqual(unexpected,[]);assert.deepEqual(errors,[]);assert.deepEqual(server.rejected,[]);
  return {...measured,photoTrace};
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

module.exports=async function measurePhotoPriority(browser,nextHome,output){
 const root=path.resolve(__dirname,'..');
 const after=exactAssets(nextHome),paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const sha=value=>createHash('sha256').update(value).digest('hex');
 const raw=execFileSync('git',['show',source+':v2/visual-search/app.js'],{cwd:root,encoding:'utf8'});
 assert.equal(sha(raw),'92af33ac613b5591bd4f4a25d2861e3a0fd9d7d864a4d27d57b4a064932e1534');
 const prior=await compile(raw,{privateFunctions:true});
 assert.equal(sha(prior),pinned.appSha);
 const rawSheet=execFileSync('git',['show',source+':v2/visual-search/styles.css'],{cwd:root,encoding:'utf8'});
 const sheet=Buffer.from(printCSS(rawSheet));
 assert.equal(sha(sheet),pinned.sheetSha);
 const shaUrl=(url,from,to)=>url.replace(from.slice(0,12),to.slice(0,12));
 const appUrl=shaUrl(after.appUrl,after.appSha,pinned.appSha),url=shaUrl(after.url,after.sheetSha,pinned.sheetSha);
 const rewrite=html=>html.replace(after.appUrl,appUrl).replace(after.url,url);
 const before={...after,appSha:pinned.appSha,sheetSha:pinned.sheetSha,code:Buffer.from(prior),sheet,appUrl,url,
  home:rewrite(after.home),search:rewrite(after.search)};
 const servers={before:await startServer(nextHome,before,paths),after:await startServer(nextHome,after,paths)};
 const groups=[];
 try{
  for(const net of networks)for(const screen of screens){
   const pairs=[];
   for(let i=0;i<9;i++){
    const set={};
    const order=i%2?['after','before']:['before','after'];
    for(const mode of order)set[mode]=await sample(browser,servers[mode],paths,screen,net,mode,i===0&&net.mbps===2);
    assert.deepEqual(set.before.cards,set.after.cards);
    if(i>0)pairs.push({...set,order});
   }
   const measures=['button','homePaint','firstCard','photo','photoAfterCard'];
   const summary=Object.fromEntries(measures.map(key=>{
    const old=pairs.map(set=>asPhases(set.before)[key]),next=pairs.map(set=>asPhases(set.after)[key]);
    const delta=next.map((v,i)=>v-old[i]);
    return [key,{before:median(old),after:median(next),paired:median(delta),faster:delta.filter(v=>v<0).length,min:Math.min(...delta),max:Math.max(...delta)}];
   }));
   groups.push({screen,net,summary,pairs});
   console.log('SEARCH3_PHOTO_PRIORITY '+JSON.stringify({screen,net,summary}));
  }
  fs.writeFileSync(path.join(output,'canonical-next-photo-priority.json'),JSON.stringify({
    baseline:source,runnerCommit:process.env.SEARCH3_SOURCE_SHA,beforeApp:before.appSha,afterApp:after.appSha,
    css:after.sheetSha,browser:browser.version(),groups
  },null,2)+'\n');
 }finally{await Promise.all(Object.values(servers).map(server=>server.close()));}
};
