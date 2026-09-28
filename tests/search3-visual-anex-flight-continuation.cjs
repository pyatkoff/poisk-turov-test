'use strict';
// Target visual-search, fictional HTTP only. Empty APD must not end flight reads.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM,VirtualConsole}=require('jsdom');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
const root=path.resolve(__dirname,'../v2'),source=n=>fs.readFileSync(path.join(root,n),'utf8');
const scripts=[...source('visual-search/index.php').match(/\$scripts = \[([\s\S]*?)\];/)[1].matchAll(/'([^']+\.js)'/g)].map(m=>path.posix.normalize('visual-search/'+m[1]));
(async()=>{
 for(const failed of [false,true]){
  const transport=fixture({anexEmptyAdditional:true}),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
  const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
  const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
  Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
  w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
  w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
  w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
  w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
  let releaseFlights;
  w.fetch=async(url,options={})=>{
   const value=await transport.json(url,options);
   if(value.data?.status==='flights'){await new Promise(resolve=>releaseFlights=resolve);if(failed)return new Response(JSON.stringify({ok:false}),{status:502});}
   return new Response(JSON.stringify(value),{status:200,headers:{'Content-Type':'application/json'}});
  };
  const wait=async(fn)=>{for(let i=0;i<80;i++){if(fn())return;await new Promise(r=>setTimeout(r,50));}assert.fail('Timeout: '+d.body.textContent.slice(-2500));};
  try{
   for(const file of scripts)w.eval(source(file));
   await wait(()=>!q('.search-submit').disabled);click('.search-submit');await wait(()=>q('[data-action="all-offers"]'));
   click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key^="anex%3A"]');click('[data-action="refresh-hotel"]');
   await wait(()=>q('#modal-body').textContent.includes('ANEX CONCRETE'));
   click('[data-action="offer"][data-key^="anex%3A"]');click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="anex-additional-prices"]'));
   assert.match(q('#modal-body').textContent,/не пересчёт стоимости/);
   click('[data-action="anex-additional-prices"]');await wait(()=>q('#anex-additional-error').textContent.includes('не вернул'));
   assert(q('[data-action="anex-additional-prices"]').disabled);assert(!q('[data-action="anex-flights"]').disabled,'empty APD leaves independent continuation available');
   click('[data-action="anex-flights"]');await wait(()=>releaseFlights);
   click('[data-action="close-modal"]');click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key^="anex%3A"]');
   assert.match(q('#anex-flight-inventory').textContent,/Загружаем рейсы/);assert(!q('[data-action="anex-flights"]'),'pending read cannot repeat after reopen');
   releaseFlights();await wait(()=>q('#anex-flight-inventory').textContent.includes(failed?'Не удалось':'TEST ANEX 101'));
   if(!failed){assert.match(q('#anex-flight-inventory').textContent,/Расписание уточняется/);assert.match(q('#anex-flight-inventory').textContent,/не выбранные рейсы/);}
   assert(!q('[data-action="anex-application-preview"]'),'unknown price cannot acquire priced application authority');
   const before=transport.calls.length;
   click('[data-action="close-modal"]');click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key^="anex%3A"]');
   assert(q('[data-action="anex-additional-prices"]').disabled);assert(!q('[data-action="anex-flights"]'));assert.equal(transport.calls.length,before);
   assert.equal(transport.calls.filter(c=>c.url.includes('anex')&&c.action==='flights').length,1);assert.equal(transport.calls.filter(c=>c.action==='additional_prices').length,1);
   assert.deepEqual(errors,[]);
  }finally{dom.window.close();}
 }
 console.log('VISUAL_ANEX_EMPTY_APD_FLIGHT_CONTINUATION_OK success/failure/pending/reopen; supplier calls 0');
})().catch(e=>{console.error(e);process.exitCode=1;});
