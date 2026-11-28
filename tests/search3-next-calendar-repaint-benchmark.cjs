'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const http=require('node:http');
const path=require('node:path');
const {createHash}=require('node:crypto');
const {execFileSync}=require('node:child_process');
const {gzipSync}=require('node:zlib');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const {canonicalLogoFixture}=require('./search3-visual-live-fixture.cjs');

const root=path.resolve(__dirname,'..');
const baseline='7bcf3de058093331fb072f2cb819cb780fe8fa15';
const appSha='dcb19a092f463d387964ac3383753446effde8c8836e3f68a5d7362adabca1db';
const digest=bytes=>createHash('sha256').update(bytes).digest('hex');
const profiles=[{width:430,height:932,dpr:3,touch:true},{width:1280,height:720,dpr:1,touch:false}];
const contentTypes={
 '.css':'text/css; charset=utf-8','.js':'application/javascript; charset=utf-8','.json':'application/json; charset=utf-8',
 '.svg':'image/svg+xml','.ico':'image/x-icon','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg',
 '.webp':'image/webp','.woff2':'font/woff2','.woff':'font/woff'
};
const scenario=new URLSearchParams({scenario:'family',origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-07',
  minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'5',meals:'Всё включено',max:'240000',searched:'1'});

function replace(input,from,to){
 assert.equal(input.split(from).length,2,from);
 return input.replace(from,()=>to);
}
async function exactAssets(nextHome){
 const files=execFileSync('git',['diff','--name-only',baseline,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort();
 assert.deepEqual(files,['tests/search3-entry-owner-browser.cjs','tests/search3-next-calendar-repaint-benchmark.cjs']);
 const raw=fs.readFileSync(path.join(root,'v2','visual-search','app.js'),'utf8');
 assert.equal(digest(raw),'b6b4427335e57d148ad6ed13a82ae3a3692fc62923de0ed4a5601d4c09cbe310');
 const price=replace(raw,"   cells.forEach((b,i)=>{const price=prices[i];b.classList.toggle('is-cheap',price!==null&&price===min);b.querySelector('small').textContent=price===null?'—':shortAmount(price,true);b.setAttribute('aria-label',dateLong(b.dataset.date)+(price===null?', цена пока неизвестна':', от '+money(price)));});","    cells.forEach((button,index)=>{\n     const price=prices[index];\n     button.classList.toggle('is-cheap',price!==null&&price===min);\n     if(paintedCalendarPrices.get(button)===price)return;\n     button.querySelector('small').textContent=price===null?'—':shortAmount(price,true);\n     button.setAttribute('aria-label',dateLong(button.dataset.date)+(price===null?', цена пока неизвестна':', от '+money(price)));\n     paintedCalendarPrices.set(button,price);\n    });");
 const memo=replace(price,'function renderCalendarPrices(){','const paintedCalendarPrices=new WeakMap();\nfunction renderCalendarPrices(){');
 const changed=replace(memo," $$('[data-action=\"day-pick\"]').forEach(b=>{const d=b.dataset.date,active=d===dateDraft.from||d===dateDraft.to;b.classList.toggle('active',active);b.classList.toggle('in-range',d>dateDraft.from&&d<dateDraft.to);b.setAttribute('aria-pressed',active||d>dateDraft.from&&d<dateDraft.to)});"," $$('[data-action=\"day-pick\"]').forEach(button=>{\n  const day=button.dataset.date,active=day===dateDraft.from||day===dateDraft.to,inRange=day>dateDraft.from&&day<dateDraft.to;\n  button.classList.toggle('active',active);\n  button.classList.toggle('in-range',inRange);\n  const pressed=String(active||inRange);\n  if(button.getAttribute('aria-pressed')!==pressed)button.setAttribute('aria-pressed',pressed);\n });");
 const patched=await compile(changed,{privateFunctions:true});
 assert.equal(digest(patched),'7688fe16d239b5839f7718286f07320a999e1f52f72f636c2ae90f1e812d080d');
 const app=fs.readFileSync(path.join(nextHome.payload,'visual-search','app.js'),'utf8');
 assert.equal(digest(app),appSha);
 const sheet=fs.readFileSync(path.join(nextHome.payload,'visual-search','styles.css'));
 assert.equal(digest(sheet),'544533a067bb7f9d70f83c56aeb78ccdb8fb38522ce2668ed32b57f28c822e44');
 const base=nextHome.prefix+'/', home=nextHome.html.get(base), search=nextHome.html.get(base+'poisk-turov/');
 const url=base+'visual-search/styles.css?v='+digest(sheet).slice(0,12);
 const ref=base+'visual-search/app.js?v=', appUrl=ref+appSha.slice(0,12), newApp=ref+digest(patched).slice(0,12);
 return {sheet,url,variants:{
   before:{home,search,appUrl,code:app},
   after:{home:replace(home,appUrl,newApp),search:replace(search,appUrl,newApp),appUrl:newApp,code:patched}
 }};
}

function interceptCalendar(){
 let wrapped;
 Object.defineProperty(window,'AnyTourPrototypeData',{configurable:true,get:()=>wrapped,set(data){
  const descriptors=Object.getOwnPropertyDescriptors(data),regular=descriptors.calendarPrices.value;
  descriptors.calendarPrices={...descriptors.calendarPrices,value:async function(search,from,to,signal,filters,onUpdate){
   const snapshot=await Reflect.apply(regular,this,[search,from,to,signal,filters]);
   if(from==='2026-10-01'&&to==='2026-10-31'&&typeof onUpdate==='function'){
    const hotel=snapshot.hotels.find(h=>h.offers.length),seed=hotel?.offers.find(offer=>offer.day==='2026-10-01');
    if(seed&&!hotel.offers.some(offer=>offer.day==='2026-10-02')){
     const day='2026-10-02',ret=new Date(Date.parse(day)+seed.nights*86400000).toISOString().slice(0,10);
     hotel.offers.push({...seed,key:seed.key+':calendar-test-second-day',day,returnDay:ret});
    }
    window.search3CalendarRepaintProbe={snapshot,show:onUpdate};
    onUpdate(snapshot);
   }
   return snapshot;
  }};
  wrapped=Object.freeze(Object.create(Object.getPrototypeOf(data),descriptors));
 }});
}

async function capture(page) {
 return page.evaluate(async()=>{
  const {snapshot,show}=window.search3CalendarRepaintProbe;
  const modal=document.querySelector('#date-calendar');
  const state=()=>Array.from(modal.querySelectorAll('.month-day'),cell=>[cell.dataset.date,
    cell.querySelector('small').textContent,cell.getAttribute('aria-label'),
    cell.classList.contains('is-cheap'),cell.getAttribute('aria-pressed')]);
  const actual=new Map(state().map(row=>[row[0],row]));
  const dates=['2026-10-01','2026-10-02'];
  if(!dates.every(day=>actual.has(day)))throw Error('Missing both priced dates');
  const observer=new MutationObserver(()=>{});
  observer.observe(modal,{attributes:true,subtree:true,attributeFilter:['class','aria-label'],childList:true});
  function probe(value){
   return new Promise(resolve=>requestAnimationFrame(()=>{
    observer.takeRecords();
    const from=performance.now();
    show(value);
    const js=performance.now()-from;
    const records=observer.takeRecords();
    const writes={priceText:records.filter(x=>x.type==='childList').length,
      priceLabel:records.filter(x=>x.attributeName==='aria-label').length};
    requestAnimationFrame(()=>resolve({js,frame:performance.now()-from,writes}));
   }));
  }
  try {
   const tests=[];
   for(let i=0;i<9;i++)tests.push(await probe(snapshot));
   const variants=[];
   for(const day of dates){
    const next=structuredClone(snapshot);
    for(const hotel of next.hotels)for(const offer of hotel.offers)if(offer.day===day)offer.total=1000;
    await probe(next);
    const painted=state();
    const row=painted.find(value=>value[0]===day);
    if(!row||row[1]===actual.get(day)[1]||!row[3])throw Error('Changed minimum was not painted');
    variants.push(painted);
   }
   await probe(snapshot);
   return {initial:[...actual.values()],restored:state(),variants,tests:tests.slice(1)};
  } finally {observer.disconnect();}
 });
}

async function sample(browser,server,origin,paths,profile,mode){
 server.select(mode);
 const context=await browser.newContext({viewport:{width:profile.width,height:profile.height},deviceScaleFactor:profile.dpr,
   isMobile:profile.touch,hasTouch:profile.touch,reducedMotion:'reduce',serviceWorkers:'block',proxy:{server:origin,bypass:'127.0.0.1'}});
 try {
  const errors=[],external=[];
  const page=await context.newPage();
  page.on('pageerror',error=>errors.push(error.message));
  page.on('request',request=>{
   const requested=new URL(request.url());
   if(requested.origin!==origin)external.push(requested.pathname);
  });
  await page.addInitScript(interceptCalendar);
  const cdp=await context.newCDPSession(page);
  await cdp.send('Emulation.setCPUThrottlingRate',{rate:3});
  assert.equal((await page.goto(origin+paths.search+'?'+scenario,{waitUntil:'domcontentloaded',timeout:15000})).status(),200);
  await page.locator('#cards .hotel-card h3').first().waitFor({state:'visible',timeout:10000});
  const disclosure=page.locator('.result-calendar-disclosure');
  if(await disclosure.isVisible()&&await disclosure.getAttribute('aria-expanded')==='false')await disclosure.click();
  const open=page.locator('#price-calendar [data-action="calendar"]:visible').first();
  await open.click({timeout:8000});
  await page.waitForFunction(()=>window.search3CalendarRepaintProbe&&document.querySelector('.date-choice-tools')?.getAttribute('aria-busy')==='false',{},{timeout:10000});
  const data=await capture(page);
  assert.deepEqual(data.initial,data.restored);
  assert.deepEqual(errors,[]);
  assert.deepEqual(external,[]);
  return {initial:digest(JSON.stringify(data.initial)),variants:digest(JSON.stringify(data.variants)),cells:data.initial.length,tests:data.tests};
 } finally {await context.close();}
}

async function startServer(nextHome, fixture, paths) {
  let origin,mode='before';
  const cache = new Map();
  const rejected = [];
  function respond(request,response) {
    const url = new URL(request.url,origin);
    if (request.method!=='GET' || url.origin!==origin) throw Error('unexpected request '+request.method+' '+url.pathname);
    const variant=fixture.variants[mode], isSheet=url.pathname.endsWith('/visual-search/styles.css');
    const isApp=url.pathname.endsWith('/visual-search/app.js');
    const isDocument=url.pathname===paths.home||url.pathname===paths.search;
    const key=(isApp||isDocument?mode:'')+url.pathname;
    let item=cache.get(key);
    if(isSheet)assert.equal(url.pathname+url.search,fixture.url);
    if(isApp)assert.equal(url.pathname+url.search,variant.appUrl);
    if (!item) {
      let body, type;
      if (isDocument) {
        body=Buffer.from(url.pathname===paths.home?variant.home:variant.search);
        type = 'text/html; charset=utf-8';
      } else if (isApp) {
        body=Buffer.from(variant.code);
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
    origin, rejected, select(value){mode=value;},
    close:()=>new Promise(resolve=>{server.close(resolve);server.closeAllConnections();})
  };
}


function median(values){
 const list=values.slice().sort((a,b)=>a-b),m=Math.floor(list.length/2);
 return list.length%2?list[m]:(list[m-1]+list[m])/2;
}
module.exports=async function compareCalendarRepaint(browser,nextHome,output){
 const fixture=await exactAssets(nextHome),paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const server=await startServer(nextHome,fixture,paths),groups=[];
 try{
  for(const profile of profiles){
   const trials=6,pairs=[];
   for(let i=0;i<=trials;i++){
    const pair={};
    for(const mode of i%2?['after','before']:['before','after'])pair[mode]=await sample(browser,server,server.origin,paths,profile,mode);
    assert.equal(pair.before.initial,pair.after.initial);
    assert.equal(pair.before.variants,pair.after.variants);
    assert.deepEqual(server.rejected,[]);
    if(i)pairs.push(pair);
   }
   const summary=Object.fromEntries(['js','frame'].map(key=>{
    const per=mode=>pairs.map(row=>median(row[mode].tests.map(item=>item[key])));
    const before=per('before'),after=per('after');
    const deltas=after.map((v,i)=>v-before[i]);
    return [key,{before:median(before),after:median(after),paired:median(deltas),faster:deltas.filter(d=>d<0).length}];
   }));
   for(const pair of pairs){
    assert(pair.before.tests.every(item=>item.writes.priceText>0&&item.writes.priceLabel>0));
    assert(pair.after.tests.every(item=>item.writes.priceText===0&&item.writes.priceLabel===0));
   }
   groups.push({profile,trials,summary,pairs});
   console.log('SEARCH3_CALENDAR_REPAINT '+JSON.stringify({profile,summary}));
  }
  fs.writeFileSync(path.join(output,'canonical-next-calendar-repaint.json'),JSON.stringify({
    baseline,runnerCommit:process.env.SEARCH3_SOURCE_SHA,appCandidate:digest(fixture.variants.after.code),
    browser:browser.version(),cpuThrottle:3,groups
  },null,2)+'\n');
 }finally{await server.close();}
};
