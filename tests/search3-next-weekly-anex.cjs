'use strict';

const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {createHash}=require('node:crypto'),{execFileSync}=require('node:child_process');
const {exactAssets,startServer}=require('./search3-next-compact-calendar.cjs');
const baseline='8310e93438491fe9569e3bd6ef4873342ed1563f';
const app='3578923bdb58e3f3e4201c3ea4ca7b1d9ff3df27db7e62ca53329d9d3fc3a16b';
const css='0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const start='2026-12-09',end='2026-12-15';
const digest=bytes=>createHash('sha256').update(bytes).digest('hex');
const profiles=[{width:430,height:932,dpr:3,touch:true},{width:1280,height:720,dpr:1,touch:false}];
function addAnexNotice(searchPath){
 if(location.pathname!==searchPath)return;
 let adapter;
 Object.defineProperty(window,'AnyTourPrototypeData',{configurable:true,get:()=>adapter,set(value){
  const fields=Object.getOwnPropertyDescriptors(value),original=fields.search?.value;
  if(typeof original!=='function'){adapter=value;return;}
  fields.search={...fields.search,value:function(query,emit,...rest){
   return Reflect.apply(original,this,[query,event=>{
    if(event?.type!=='complete')return emit(event);
    emit({type:'provider',provider:'anex',status:'error'});
    return emit({...event,sources:{...event.sources,anex:{...event.sources?.anex,status:'error'}}});
   },...rest]);
  }};
  adapter=Object.freeze(Object.create(Object.getPrototypeOf(value),fields));
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

const mockStyles='@media (min-width:1101px) {'
 +'#price-calendar[data-weekly-mock]{padding:8px 12px;}'
 +'#price-calendar[data-weekly-mock] .result-date-heading{display:flex;align-items:center;gap:12px;min-height:0;margin:0 0 3px;padding:0;}'
 +'#price-calendar[data-weekly-mock] .result-date-heading strong{font-size:15px;margin:0;white-space:nowrap;}'
 +'#price-calendar[data-weekly-mock] .calendar-units{margin:0;line-height:1.25;}'
 +'#price-calendar[data-weekly-mock] .result-date-heading>.text-button{margin-left:auto;min-height:28px;height:28px;padding:2px 8px;flex:none;}'
 +'#price-calendar[data-weekly-mock] .price-strip{margin:0;gap:6px;}'
 +'#price-calendar[data-weekly-mock] .price-strip .date-price{display:flex;flex-direction:row;align-items:center;justify-content:space-between;gap:5px;height:36px;min-height:36px;padding:0 8px;}'
 +'#price-calendar[data-weekly-mock] .calendar-result-notes{margin:4px 0 0;padding:0;min-height:0;line-height:1.1;}'
 +'#price-calendar[data-weekly-mock] .calendar-result-notes p{margin:0;line-height:1.2;}'
 +'}';

const waitFrame=page=>page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
async function capture(page,output,name){
 const png=await page.screenshot({path:path.join(output,name),animations:'disabled',scale:'css'});
 return {file:name,sha256:digest(png)};
}
function fullyVisible(view){
 return !!view.button&&Math.abs(view.visiblePixels-view.button.height)<0.01&&view.hitSample.every(Boolean);
}
async function sample(browser,server,paths,profile,output){
 const origin=server.origin,context=await browser.newContext({
  viewport:{width:profile.width,height:profile.height},deviceScaleFactor:profile.dpr,hasTouch:profile.touch,isMobile:profile.touch,
  reducedMotion:'reduce',serviceWorkers:'block',proxy:{server:origin,bypass:'127.0.0.1'}
 });
 try{
  const page=await context.newPage(),unexpected=[],errors=[];
  page.on('request',request=>{const url=new URL(request.url());if(url.origin!==origin)unexpected.push(url.pathname);});
  page.on('pageerror',error=>errors.push(error.message));
  await page.addInitScript(addAnexNotice,paths.search);
  const params=new URLSearchParams({scenario:'family',origin:'Москва',country:'4',from:start,to:end,
   minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});
  assert.equal((await page.goto(origin+paths.home+'?'+params,{waitUntil:'domcontentloaded'})).status(),200);
  const form=page.locator('#search-form .search-submit');
  await page.waitForFunction(()=>{const button=document.querySelector('#search-form .search-submit');return !!button&&!button.disabled;});
  await Promise.all([page.waitForURL(url=>url.pathname===paths.search),profile.touch?form.tap():form.click()]);
  const action=page.locator('#cards .hotel-card [data-card-entry][data-action="offer"]').first();
  await action.waitFor({state:'attached',timeout:12000});
  await page.waitForFunction(()=>document.querySelector('#search-status')?.innerText.includes('Получены не все предложения'));
  await page.waitForFunction(()=>{const img=document.querySelector('#cards .hotel-image');return img?.complete&&img.naturalWidth>0;});
  await page.evaluate(()=>document.fonts.ready);
  await waitFrame(page);
  const original=await page.evaluate(geometry);
  assert(Object.values(original).length>0);
  assert.deepEqual([original.viewport.width,original.viewport.height,original.viewport.touch,original.viewport.devicePixelRatio,original.viewport.scrollY],
   [profile.width,profile.height,profile.touch,profile.dpr,0]);
  const url=new URL(original.href,origin);
  assert.deepEqual(['from','to','max'].map(name=>url.searchParams.get(name)),[start,end,'240000']);
  assert.equal(original.label,'Смотреть тур');
  assert(original.button?.height>0);
  assert(original.status?.height>0);
  assert.match(original.statusText,/Получены не все предложения/);
  assert.equal(original.visibleDates,profile.touch?0:7);
  assert.equal(original.singleDay,false);
  const direct=fullyVisible(original),viewport=await capture(page,output,'weekly-anex-'+profile.width+'x'+profile.height+'.png');

  // An unshipped layout sketch. Only the screenshot context receives these
  // styles. The browser then restores the exact app before testing the route.
  let mock=null;
  if(!profile.touch&&!direct){
   const tag=await page.addStyleTag({content:mockStyles});
   await page.locator('#price-calendar').evaluate(el=>el.setAttribute('data-weekly-mock',''));
   await waitFrame(page);
   const preview=await page.evaluate(geometry);
   assert.equal(preview.visibleDates,7);
   assert.equal(preview.statusText,original.statusText);
   assert.equal(preview.viewport.scrollY,0);
   mock={geometry:preview,direct:fullyVisible(preview),screenshot:await capture(page,output,'weekly-anex-layout-sketch-1280x720.png')};
   await page.locator('#price-calendar').evaluate(el=>el.removeAttribute('data-weekly-mock'));
   await tag.evaluate(node=>node.remove());
   await waitFrame(page);
   const restored=await page.evaluate(geometry);
   assert.equal(restored.button.bottom,original.button.bottom);
   assert.equal(restored.statusText,original.statusText);
  }
  if(profile.touch){
   const disclosure=page.locator('.result-calendar-disclosure');
   await disclosure.tap();
   assert.equal(await disclosure.getAttribute('aria-expanded'),'true');
   assert.equal(await page.locator('#price-strip .date-price').count(),7);
   await disclosure.tap();
   assert.equal(await disclosure.getAttribute('aria-expanded'),'false');
   await page.evaluate(()=>window.scrollTo(0,0));
   await waitFrame(page);
  }
  await action.scrollIntoViewIfNeeded();
  const scrolling=await page.evaluate(()=>scrollY);
  await (profile.touch?action.tap():action.click());
  await page.locator('#modal[open] #modal-title').waitFor({state:'visible'});
  const opened=(await page.locator('#modal-title').textContent()).trim();
  assert.equal(opened,'Ваш тур в деталях');
  assert.deepEqual(unexpected,[]);
  assert.deepEqual(errors,[]);
  assert.deepEqual(server.rejected,[]);
  return {profile,original,direct,viewport,scrolling,opened,mock};
 }finally{await context.close();}
}
module.exports=async function verifyWeeklyWarning(browser,nextHome,output){
 const assets=exactAssets(nextHome);
 assert.equal(assets.appSha,app);
 assert.equal(assets.sheetSha,css);
 const root=path.resolve(__dirname,'..');
 assert.deepEqual(execFileSync('git',['diff','--name-only','0612e2a49bc4f8b4b3de0cad1ca1a5d5eceb92cf','HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort(),
  ['tests/search3-entry-owner-browser.cjs','tests/search3-next-weekly-anex.cjs']);
 const paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
 const server=await startServer(nextHome,assets,paths),samples=[];
 try{
  for(const profile of profiles)samples.push(await sample(browser,server,paths,profile,output));
  console.log('SEARCH3_WEEKLY_ANEX '+JSON.stringify(samples.map(s=>[s.profile.width,s.original.button,s.original.visiblePixels,s.direct,s.mock?.geometry.button])));
  fs.writeFileSync(path.join(output,'canonical-next-weekly-anex.json'),JSON.stringify({
   source:process.env.SEARCH3_SOURCE_SHA,baseline,app,css,browser:browser.version(),from:start,to:end,samples
  },null,2)+'\n');
 }finally{await server.close();}
};
module.exports.addAnexNotice=addAnexNotice;
