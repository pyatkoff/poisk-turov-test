'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const http=require('node:http');
const path=require('node:path');
const {createHash}=require('node:crypto');
const {execFileSync}=require('node:child_process');
const {gzipSync}=require('node:zlib');
const {canonicalLogoFixture}=require('./search3-visual-live-fixture.cjs');

const root=path.resolve(__dirname,'..');
const baseline='7bcf3de058093331fb072f2cb819cb780fe8fa15';
const digest=bytes=>createHash('sha256').update(bytes).digest('hex');
const start='2026-12-09';
const cases=[
 {name:'single-normal',to:start,partial:false},
 {name:'single-anex-partial',to:start,partial:true},
 {name:'weekly-normal',to:'2026-12-15',partial:false}
];
const profiles=[{width:430,height:932,dpr:3,touch:true},{width:1280,height:720,dpr:1,touch:false}];
const contentTypes={
 '.css':'text/css; charset=utf-8','.js':'application/javascript; charset=utf-8','.json':'application/json; charset=utf-8',
 '.svg':'image/svg+xml','.ico':'image/x-icon','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg',
 '.webp':'image/webp','.woff2':'font/woff2','.woff':'font/woff'
};
function exactAssets(nextHome){
 const changed=execFileSync('git',['diff','--name-only',baseline,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
 assert.deepEqual(changed,["tests/search3-entry-owner-browser.cjs","tests/search3-next-compact-calendar.cjs","tests/search3-visual-results-rendering.cjs","v2/visual-search/app.js","v2/visual-search/migration-manifest.json","v2/visual-search/styles.css"]);
 const code=fs.readFileSync(path.join(nextHome.payload,'visual-search','app.js'));
 const sheet=fs.readFileSync(path.join(nextHome.payload,'visual-search','styles.css'));
 const boot=fs.readFileSync(path.join(nextHome.payload,'visual-search','public-bootstrap-v1.js'));
 const appSha=digest(code),sheetSha=digest(sheet);
 assert.equal(digest(boot),'64d0b7dd0225d16ed626b1a1b692e942dcb36c98ed866d30a8beec70aa4b4ae9');
 const base=nextHome.prefix+'/',home=nextHome.html.get(base),search=nextHome.html.get(base+'poisk-turov/');
 const appUrl=base+'visual-search/app.js?v='+appSha.slice(0,12),url=base+'visual-search/styles.css?v='+sheetSha.slice(0,12);
 for(const html of [home,search])for(const ref of [appUrl,url])assert.equal(html.split(ref).length,2);
 return {sheet,url,code,appUrl,home,search,appSha,sheetSha};
}
function addPartialStatus(searchPath){
 if(location.pathname!==searchPath)return;
 let wrapped;
 Object.defineProperty(window,'AnyTourPrototypeData',{configurable:true,get:()=>wrapped,set(data){
  const descriptors=Object.getOwnPropertyDescriptors(data),original=descriptors.search.value;
  descriptors.search={...descriptors.search,value:function(search,emit,...rest){
   return Reflect.apply(original,this,[search,event=>{
    if(event?.type==='results'&&event.hotels?.length) {
     const hotels=structuredClone(event.hotels);
     const first=hotels[0],seed=first.offers[0];
     if(seed)first.offers=Array.from({length:9},(_,i)=>({...seed,key:seed.key+':single-day-check-'+i,
       variant:i,room:i===0?seed.room:seed.room+' '+(i+1),total:seed.total+i*1500}));
     return emit({...event,hotels});
    }
    if(event?.type==='complete'){
     emit({type:'provider',provider:'anex',status:'error'});
     return emit({...event,sources:{anex:{status:'error'}}});
    }
    return emit(event);
   },...rest]);
  }};
  wrapped=Object.freeze(Object.create(Object.getPrototypeOf(data),descriptors));
 }});
}
function geometry(){
 const find=selector=>document.querySelector(selector);
 const box=el=>{
  if(!el||el.closest('[hidden],[inert]'))return null;
  const r=el.getBoundingClientRect();
  if(!r.width||!r.height)return null;
  return {x:r.x,y:r.y,width:r.width,height:r.height,bottom:r.bottom};
 };
 const viewport={width:innerWidth,height:innerHeight,scrollY,devicePixelRatio,touch:navigator.maxTouchPoints>0};
 const card=find('#cards .hotel-card'),action=card?.querySelector('[data-card-entry][data-action="offer"]');
 if(!action)throw Error('Missing the first tour action');
 const btn=box(action),visiblePixels=btn?Math.max(0,Math.min(innerHeight,btn.bottom)-Math.max(0,btn.y)):0;
 const hits=[];
 if(btn&&visiblePixels){
  for(const offset of [1,Math.min(btn.height/2,visiblePixels/2),Math.max(1,visiblePixels-1)]){
   const target=document.elementFromPoint(btn.x+btn.width/2,Math.max(0,btn.y)+offset);
   hits.push(!!target&&action.contains(target));
  }
 }
 const warning=find('#search-status');
 return {viewport,card:box(card),button:btn,label:action.textContent.trim(),visiblePixels,hitSample:hits,
  calendar:box(find('#price-calendar')),visibleDates:[...document.querySelectorAll('#price-strip .date-price')].filter(el=>box(el)).length,
  singleDay:find('#price-calendar')?.classList.contains('calendar-single-day'),
  disclosure:box(find('.result-calendar-disclosure')), expanded:find('.result-calendar-disclosure')?.getAttribute('aria-expanded'),
  displayedPrice:find('#result-calendar-compact-price')?.textContent.trim()||'',
  status:box(warning),statusText:box(warning)?warning.innerText.trim():'',
  notice:find('.form-price-note')?.innerText.trim()??'',href:location.pathname+location.search};
}
async function sample(browser,server,paths,profile,entry,output){
 const origin=server.origin,context=await browser.newContext({
  viewport:{width:profile.width,height:profile.height},deviceScaleFactor:profile.dpr,hasTouch:profile.touch,isMobile:profile.touch,
  reducedMotion:'reduce',serviceWorkers:'block',proxy:{server:origin,bypass:'127.0.0.1'}
 });
 try{
  const page=await context.newPage(),external=[],errors=[];
  page.on('request',request=>{const u=new URL(request.url());if(u.origin!==origin)external.push(u.pathname);});
  page.on('pageerror',error=>errors.push(error.message));
  if(entry.partial)await page.addInitScript(addPartialStatus,paths.search);
  const query=new URLSearchParams({scenario:'family',origin:'Москва',country:'4',from:start,to:entry.to,
    minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
  assert.equal((await page.goto(origin+paths.home+'?'+query,{waitUntil:'domcontentloaded'})).status(),200);
  const find=page.locator('#search-form .search-submit');
  await page.waitForFunction(()=>{const t=document.querySelector('#search-form .search-submit');return !!t&&!t.disabled;},{},{timeout:10000});
  await Promise.all([page.waitForURL(url=>url.pathname===paths.search),profile.touch?find.tap():find.click()]);
  const action=page.locator('#cards .hotel-card [data-card-entry][data-action="offer"]').first();
  await action.waitFor({state:'attached',timeout:10000});
  if(entry.partial)await page.waitForFunction(()=>document.querySelector('#search-status')?.innerText.includes('Получены не все предложения'));
  else await page.waitForFunction(()=>document.querySelector('#search-status')?.hidden===true,{},{timeout:10000});
  await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>resolve()))));
  const view=await page.evaluate(geometry);
  assert.equal(view.viewport.width,profile.width);
  assert.equal(view.viewport.height,profile.height);
  assert.equal(view.viewport.touch,profile.touch);
  const url=new URL(view.href,origin);
  assert.equal(url.searchParams.get('from'),start);
  assert.equal(url.searchParams.get('to'),entry.to);
  assert.equal(url.searchParams.get('max'),'240000');
  assert.equal(view.label,'Смотреть тур');
  assert.equal(view.viewport.scrollY,0,'the first card must fit without scrolling');
  assert.equal(view.visiblePixels,view.button.height,'the full booking action must be in the initial viewport');
  assert.deepEqual(view.hitSample,[true,true,true],'the displayed action must accept direct clicks');
  const compact=!profile.touch&&entry.to===start;
  assert.equal(view.singleDay,compact);
  assert.equal(view.expanded,compact||profile.touch?'false':'true');
  assert.equal(!!view.disclosure,compact||profile.touch);
  assert.equal(view.visibleDates,compact||profile.touch?0:7);
  if(compact)assert.match(view.displayedPrice,/^от 186\s400 ₽$/);

  const screenshot='canonical-next-compact-calendar-'+profile.width+'-'+entry.name+'.png';
  const shot=await page.screenshot({path:path.join(output,screenshot),animations:'disabled'});
  if(compact||profile.touch){
   const disclosure=page.locator('.result-calendar-disclosure');
   const original=page.url();
   await (profile.touch?disclosure.tap():disclosure.click());
   assert.equal(await disclosure.getAttribute('aria-expanded'),'true');
   await page.locator('#price-strip [data-date="'+start+'"]').waitFor({state:'visible'});
   assert.equal(await page.locator('#price-strip .date-price').count(),entry.to===start?1:7);
   assert.equal(page.url(),original,'expanding the calendar keeps the selected trip');
   await (profile.touch?disclosure.tap():disclosure.click());
   assert.equal(await disclosure.getAttribute('aria-expanded'),'false');
   const collapsed=await page.evaluate(geometry);
   assert.equal(collapsed.viewport.scrollY,0);
   assert.equal(collapsed.visiblePixels,collapsed.button.height);
   assert.deepEqual(collapsed.hitSample,[true,true,true]);
  }
  await (profile.touch?action.tap():action.click());
  await page.locator('#modal[open] #modal-title').waitFor({state:'visible',timeout:10000});
  const opened=(await page.locator('#modal-title').textContent()).trim();
  assert(opened);
  assert.deepEqual(external,[]);
  assert.deepEqual(errors,[]);
  return {profile,case:entry.name,...view,screenshot,sha256:digest(shot),opened};
 }finally{await context.close();}
}

async function startServer(nextHome, fixture, paths) {
  let origin;
  const cache = new Map();
  const rejected = [];
  function respond(request,response) {
    const url = new URL(request.url,origin);
    if (request.method!=='GET' || url.origin!==origin) throw Error('unexpected request '+request.method+' '+url.pathname);
    const isSheet=url.pathname.endsWith('/visual-search/styles.css');
    const isApp=url.pathname.endsWith('/visual-search/app.js');
    const isDocument=url.pathname===paths.home||url.pathname===paths.search;
    const key=url.pathname;
    let item=cache.get(key);
    if(isSheet)assert.equal(url.pathname+url.search,fixture.url);
    if(isApp)assert.equal(url.pathname+url.search,fixture.appUrl);
    if (!item) {
      let body, type;
      if (isDocument) {
        body=Buffer.from(url.pathname===paths.home?fixture.home:fixture.search);
        type = 'text/html; charset=utf-8';
      } else if (isApp) {
        body=Buffer.from(fixture.code);
        type='application/javascript; charset=utf-8';
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
    origin, rejected,
    close:()=>new Promise(resolve=>{server.close(resolve);server.closeAllConnections();})
  };
}



module.exports=async function verifySingleDayGeometry(browser,nextHome,output){
 const fixture=exactAssets(nextHome),paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const server=await startServer(nextHome,fixture,paths),samples=[];
 try{
  for(const profile of profiles)for(const entry of cases){
   samples.push(await sample(browser,server,paths,profile,entry,output));
   assert.deepEqual(server.rejected,[]);
  }
  fs.writeFileSync(path.join(output,'canonical-next-compact-calendar.json'),JSON.stringify({
   baseline,appSha:fixture.appSha,sheetSha:fixture.sheetSha,runnerCommit:process.env.SEARCH3_SOURCE_SHA,browser:browser.version(),samples
  },null,2)+'\n');
 }finally{await server.close();}
};
