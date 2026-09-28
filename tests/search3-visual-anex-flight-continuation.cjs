'use strict';
// Target visual-search, fictional HTTP only. Empty APD must not end flight reads.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM,VirtualConsole}=require('jsdom');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
const root=path.resolve(__dirname,'../v2'),source=n=>fs.readFileSync(path.join(root,n),'utf8');
const scripts=[...source('visual-search/index.php').match(/\$scripts = \[([\s\S]*?)\];/)[1].matchAll(/'([^']+\.js)'/g)].map(m=>path.posix.normalize('visual-search/'+m[1]));
(async()=>{
 for(const zero of [false,true]){
  const transport=fixture({anexZeroSurcharge:zero}),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
  transport.state.anexCurrentAdditional=true;
  const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
  const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
  Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
  w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
  w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
  w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
  w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
  w.fetch=async(url,options={})=>new Response(JSON.stringify(await transport.json(url,options)),{status:200,headers:{'Content-Type':'application/json'}});
  const wait=async(fn)=>{for(let i=0;i<80;i++){if(fn())return;await new Promise(r=>setTimeout(r,50));}assert.fail('Timeout: '+d.body.textContent.slice(-2000));};
  try{
   for(const file of scripts)w.eval(source(file));
   await wait(()=>!q('.search-submit').disabled);click('.search-submit');await wait(()=>q('[data-action="all-offers"]'));
   click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key^="anex%3A"]');click('[data-action="refresh-hotel"]');
   await wait(()=>q('#modal-body').textContent.includes('ANEX CONCRETE'));
   click('[data-action="offer"][data-key^="anex%3A"]');click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="anex-application-preview"]'));
   assert.match(q('#modal-body').textContent,/не финально подтверждённая/);
   assert.equal(q('[data-action="anex-additional-prices"]'),null,'retained APD requires no repeated supplier request');
   click('[data-action="anex-application-preview"]');
   const form=q('#prototype-lead-form');assert(form);assert.match(q('#modal-body').textContent,zero?/121\s*000/:/123\s*000/);
   assert.match(q('#modal-body').textContent,/ANEX CONCRETE/);assert.match(q('#modal-body').textContent,/Итоговая стоимость требует подтверждения/);
   form.elements.phone.value='+79990000000';form.elements.consent.checked=true;
   form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
   assert.equal(form.dataset.checked,'1');assert.match(q('.lead-message').textContent,/Заявка не отправлена/);
   form.elements.phone.dispatchEvent(new w.Event('input',{bubbles:true}));q('#modal-body').scrollTop=73;
   const beforeForward=transport.calls.length;
   click('[data-action="close-modal"]');await new Promise(r=>setTimeout(r,150));w.history.forward();await new Promise(r=>setTimeout(r,150));
   assert.equal(q('#modal').open,true,'browser Forward restores the current ANEX application');
   assert.equal(q('#prototype-lead-form').elements.phone.value,'+79990000000');
   assert.equal(q('#prototype-lead-form').elements.consent.checked,false);assert.equal(q('#prototype-lead-form').dataset.checked,undefined);
   assert.equal(q('#modal-body').scrollTop,73);assert.match(q('#modal-body').textContent,zero?/121\s*000/:/123\s*000/);
   assert.equal(transport.calls.length,beforeForward,'browser Forward never repeats current/APD/flights or submits a lead');
   const before=transport.calls.length;
   click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key^="anex%3A"]');click('[data-action="anex-application-preview"]');
   assert.equal(q('#prototype-lead-form').elements.consent.checked,false);assert.equal(transport.calls.length,before,'return uses the same retained exact offer');
   assert.equal(transport.calls.filter(c=>c.action==='additional_prices').length,0);assert.equal(transport.calls.filter(c=>c.action==='offer').length,1);
   await new Promise(r=>setTimeout(r,50));
   const historyKey='anytour.prototype.v18.ui.v1',route=w.history.state[historyKey];
   assert.deepEqual(Object.keys(route).sort(),['key','scroll','type'],'history does not persist price, supplier receipt, contacts or consent');
   click('[data-action="close-modal"]');await new Promise(r=>setTimeout(r,150));
   const restore=async value=>{w.history.replaceState({...w.history.state,[historyKey]:value},'',w.location.href);w.dispatchEvent(new w.PopStateEvent('popstate',{state:w.history.state}));await new Promise(r=>setTimeout(r,150));};
   await restore({...route,key:'tourvisor%3Avisual-tv-101'});assert(!q('#modal').open,'wrong-provider history cannot open an ANEX application');
   assert.equal(transport.calls.length,before,'invalid history never performs a provider request');
   click('#applied-search [data-action="edit-search"]');click('.search-submit');await wait(()=>q('[data-action="all-offers"]'));
   const quoteCalls=()=>transport.calls.filter(c=>['quote','quote_select_flights','offer','additional_prices','flights'].includes(c.action)).length,afterSearch=quoteCalls();
   await restore(route);assert(!q('#modal').open,'a new search invalidates the old application route');
   assert.equal(quoteCalls(),afterSearch,'stale history cannot re-quote the previous offer');
   assert.deepEqual(errors,[]);
  }finally{await new Promise(r=>setTimeout(r,50));dom.window.close();}
 }
 console.log('VISUAL_ANEX_RETAINED_ESTIMATE_APPLICATION_OK positive/zero/reopen; supplier calls 0');
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
   click('[data-action="close-modal"]');await new Promise(r=>setTimeout(r,150));w.history.forward();await new Promise(r=>setTimeout(r,150));
   assert(q('#modal').open,'browser Forward restores terminal APD and flight outcomes');
   assert(q('[data-action="anex-additional-prices"]').disabled);assert(!q('[data-action="anex-flights"]'));assert.equal(transport.calls.length,before);
   click('[data-action="close-modal"]');click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key^="anex%3A"]');
   assert(q('[data-action="anex-additional-prices"]').disabled);assert(!q('[data-action="anex-flights"]'));assert.equal(transport.calls.length,before);
   assert.equal(transport.calls.filter(c=>c.url.includes('anex')&&c.action==='flights').length,1);assert.equal(transport.calls.filter(c=>c.action==='additional_prices').length,1);
   assert.deepEqual(errors,[]);
  }finally{dom.window.close();}
 }
 console.log('VISUAL_ANEX_EMPTY_APD_FLIGHT_CONTINUATION_OK success/failure/pending/reopen; supplier calls 0');
})().catch(e=>{console.error(e);process.exitCode=1;});
