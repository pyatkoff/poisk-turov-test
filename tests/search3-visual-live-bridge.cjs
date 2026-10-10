'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM,VirtualConsole}=require('jsdom');
const {fixture,trip,day,hotelCatalogue}=require('./search3-visual-live-fixture.cjs');
const resultRangeTo=new Date(Date.parse(day+'T12:00:00Z')+6*86400000).toISOString().slice(0,10);
const root=path.resolve(__dirname,'../v2'),source=n=>fs.readFileSync(path.join(root,n),'utf8');
function searchDeliveryPresentationRegressions(){
 const app=source('visual-search/app.js'),start=app.indexOf('function renderSearchStatus(items,total){'),end=app.indexOf('\nfunction prepareSearchRun(',start);
 assert(start>0&&end>start,'one current search status owner');
 const local=new JSDOM('<div id="search-status"></div><div id="search-more"></div><div id="cards"></div><button id="outside">Outside</button>',{runScripts:'outside-only'}),win=local.window,doc=win.document,get=s=>doc.querySelector(s);
 win.eval(`const $=s=>document.querySelector(s),state={hasSearched:true};let searchResponse={};const icon=()=>'<svg aria-hidden="true"></svg>',needsRefresh=()=>false;${app.split('\n').find(line=>line.startsWith('function sameSelectedTourConditions('))}${app.split('\n').find(line=>line.startsWith('const esc = '))}\n${app.slice(start,end)}\nwindow.__searchStatus=(response,items)=>{searchResponse=response;renderSearchStatus(items,0);};`);
 const render=(patch={},visible=true)=>win.__searchStatus({phase:'complete',key:'current',pending:false,canContinue:false,providers:{tourvisor:'complete',anex:'error'},sources:{tourvisor:{status:'complete',offers:40},anex:{status:'error',offers:0},andromeda:{status:'skipped',offers:0}},...patch},visible?[{offers:[]}]:[]),row=key=>get(`[data-search-source="${key}"]`);
 render();assert.match(get('#search-status summary').textContent,/ANEX/,'partial delivery identifies the failed source in its visible summary');assert.match(row('tourvisor').textContent,/40/);assert.match(row('anex').textContent,/не загрузились/);assert.equal(row('andromeda'),null,'paused/unrequested SAMO is not an active source');
 render({providers:{tourvisor:'complete',anex:'partial'},sources:{tourvisor:{status:'complete',offers:40},anex:{status:'partial',offers:10}},canContinue:true});
 assert.match(get('#search-status').textContent,/Получена часть предложений/,'bounded healthy first page is not a supplier failure');assert.doesNotMatch(get('#search-status').textContent,/не загрузил|недоступн/);assert.match(row('anex').textContent,/10/);assert(get('#search-more [data-action="continue-search"]'),'stock explicit continuation remains available');
 get('#search-status details').open=true;get('#search-status [data-action="edit-search"]').focus();render({providers:{tourvisor:'complete',anex:'partial'},sources:{anex:{status:'partial',offers:10}},canContinue:true});assert.equal(get('#search-status details').open,true,'ordinary result/filter render keeps opened source details');assert.equal(doc.activeElement.dataset.action,'edit-search','same available action retains focus');
 get('#search-more [data-action="continue-search"]').focus();render({providers:{tourvisor:'complete',anex:'partial'},sources:{anex:{status:'partial',offers:10}},canContinue:true});assert.equal(doc.activeElement.dataset.action,'continue-search','same explicit continuation retains focus');
 for(const flag of ['continuationFailed','destinationBranchFailed']){render({providers:{anex:'partial'},sources:{anex:{status:'partial',offers:10,[flag]:true}},canContinue:true});assert.match(get('#search-status summary').textContent,/ANEX/);assert.match(row('anex').textContent,/не загрузилась/,'only a canonical failure flag turns partial delivery into failure');}
 for(const offers of [undefined,null,-1,NaN,Infinity,'40']){render({providers:{anex:'error'},sources:{anex:{status:'error',offers,failureCode:'PRIVATE_INTERNAL_ERROR',message:'private-message',url:'https://private.example'}}});assert.doesNotMatch(row('anex').textContent,/Вариантов до фильтров|PRIVATE|private/,'absent or invalid count/reason remains undisclosed');}
 render({providers:{tourvisor:'complete',anex:'error'},sources:{},union:{offersByProvider:{tourvisor:7,anex:0}}});assert.match(row('tourvisor').textContent,/7/,'existing sanitized union is available independently of source counts');assert.match(row('anex').textContent,/0/,'explicit received zero remains distinct from missing');
 render({pending:true,phase:'loading',providers:{tourvisor:'loading',anex:'loading'}});assert.match(get('#search-status').textContent,/Ищем предложения/);assert.match(row('anex').textContent,/Получаем предложения/);assert(get('[data-action="stop-search"]'));assert.equal(get('#search-more').hidden,true);
 render({providers:{tourvisor:'complete',anex:'loading'}});assert.match(get('#search-status').textContent,/Дополняем найденные туры/);assert.match(row('anex').textContent,/Получаем предложения/);
 render({pending:true,phase:'loading',message:'Получаем предложения · 100%',providers:{tourvisor:'complete',anex:'loading'}});assert.doesNotMatch(get('#search-status').textContent,/100%/,'one source progress is not whole-search completion');
 for(const message of ['Восстанавливаем сохранённые предложения без нового запроса к туроператорам.','Проверяем результат предыдущего запроса без повторного запуска.']){render({pending:true,phase:'loading',message,providers:{tourvisor:'loading'}});assert.match(get('#search-status').textContent,new RegExp(message),'existing retained/read-only operation disclosure is preserved');}
 render({providers:{anex:'unknown'},sources:{anex:{status:'unknown'}}});assert.equal(get('#search-status').hidden,false);assert.match(row('anex').textContent,/не подтверждён/);assert.doesNotMatch(row('anex').textContent,/Вариантов до фильтров/);
 render({providers:{tourvisor:'complete',anex:'complete'}});assert.equal(get('#search-status').hidden,true,'completed received batch does not imply whole-market claims');
 render({phase:'error',providers:{tourvisor:'error',anex:'error'},sources:{}} ,false);assert.match(get('#search-status').textContent,/Туры не загрузились/);assert.doesNotMatch(get('#search-status').textContent,/найденные туры доступны/);
 render({databaseError:true,message:'База сейчас не отвечает.'});assert.match(get('#search-status').textContent,/Показаны сохранённые туры/,'DB fallback stays separate from live source completion');
 render({coverageGap:true,message:'Разрешённое покрытие изменилось.'});assert.match(get('#search-status').textContent,/Условия стали шире поиска/);assert.match(get('#search-status').textContent,/Разрешённое покрытие изменилось/);
 render({exactRefresh:true,exactRefreshTarget:{}});assert.match(get('#search-status').textContent,/Актуальные варианты пока не найдены/);assert.match(get('#search-status').textContent,/не заменяют сохранённый выбор/);
 const exact=Object.freeze({hotelId:7,day:'2026-10-05',nights:7,adults:2,ages:Object.freeze([0,17]),room:'Standard',placement:'DBL + 2 CHD',origin:'Москва',meal:'BB',operator:'ANEX',flight:'charter'}),original=JSON.stringify(exact),finished={phase:'complete',pending:false,canContinue:false,providers:{tourvisor:'complete'},exactRefresh:true,exactRefreshTarget:exact};
 get('#outside').focus();
 for(const alternative of [{...exact,placement:'TWIN + 2 CHD'},{...exact,placement:''},{...exact,origin:'Калининград'},{...exact,origin:'',search:{origin:'Калининград'}}]){
  win.__searchStatus(finished,[{offers:[alternative]}]);assert.equal(get('#search-status').hidden,false,'another departure or placement retains the exact-refresh warning');assert.match(get('#search-status').textContent,/не заменяют сохранённый выбор/);assert.match(get('#search-status').textContent,/город вылета.*размещение/);assert.equal(doc.activeElement,get('#outside'),'passive alternative warning keeps outside focus');assert.equal(JSON.stringify(exact),original,'comparing an alternative cannot change the selected conditions');
 }
 win.__searchStatus(finished,[{offers:[{...exact,total:999999,key:'fresh-offer'}]}]);assert.equal(get('#search-status').hidden,true,'same conditions with a new price or exact offer ID are still found; comparison grants no price/selection authority');
 win.__searchStatus(finished,[{offers:[{...exact,origin:'',search:{origin:'Москва'}}]}]);assert.equal(get('#search-status').hidden,true,'existing accepted search-origin fallback is retained');
 render({phase:'cancelled',providers:{tourvisor:'cancelled',anex:'cancelled'},sources:{}});assert.match(get('#search-status').textContent,/Поиск остановлен/);assert.match(row('anex').textContent,/Запрос остановлен/);
 get('#outside').focus();render({providers:{anex:'error',privateProvider:'error'},sources:{anex:{status:'error',offers:3}}});assert.strictEqual(doc.activeElement,get('#outside'),'passive status render cannot steal outside focus');assert.doesNotMatch(get('#search-status').textContent,/privateProvider/);
 local.window.close();console.log('PASS search delivery presentation: qualified errors, healthy bounded page, existing counts, missing/zero, pending/unknown/cancelled, DB and exact-offer guards, opened details/action focus; supplier HTTP0');
}
searchDeliveryPresentationRegressions();
const scripts=[...source('visual-search/index.php').match(/\$scripts = \[([\s\S]*?)\];/)[1].matchAll(/'([^']+\.js)'/g)].map(m=>path.posix.normalize('visual-search/'+m[1]));
// Transport fixtures install the presentation owner; cold loading has its own probe.
scripts.splice(scripts.indexOf('visual-search/app.js'),0,'visual-search/offer-list-v1.js','visual-search/hotel-details-v1.js');
const transport=fixture({tvFuel:20686}),errors=[],vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
transport.state.catalogueCountries=[{id:100,kind:'country',parentId:null,name:'Египет',slug:'egypt',revision:1,tourvisorIds:['100']}];
const dom=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,to:resultRangeTo,ages:'',searched:'1'}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
const w=dom.window,d=w.document,q=s=>d.querySelector(s),click=s=>{assert(q(s),s);q(s).click();};
// Outside-only JSDOM does not fetch script elements. Service the real third
// cold loader with a bounded local file, keeping transport fixture calls apart.
const coldScripts=[],append=d.head.append.bind(d.head);
d.head.append=(...nodes)=>{
 append(...nodes);
 for(const node of nodes)if(node.tagName==='SCRIPT'){
  assert.equal(new URL(node.src).pathname,'/_preview/search3-next-candidate/visual-search/flight-picker-ui-v1.js','only the declared cold flight owner is executable');coldScripts.push(node.src);
  queueMicrotask(()=>{w.eval(source('visual-search/flight-picker-ui-v1.js'));node.onload();});
 }
};
Object.defineProperty(w,'crypto',{value:require('node:crypto').webcrypto});
w.innerHeight=900;const departureViewport=new w.EventTarget();departureViewport.height=900;departureViewport.offsetTop=0;w.visualViewport=departureViewport;
w.innerWidth=390;w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.CSS={escape:s=>String(s).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
w.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
w.HTMLElement.prototype.scrollIntoView=function(){};w.scrollTo=()=>{};
w.HTMLDialogElement.prototype.showModal=function(){this.open=true};w.HTMLDialogElement.prototype.close=function(){this.open=false};
let additionalGate=null;
w.fetch=async(url,options={})=>{const value=await transport.json(url,options);if(value.data?.state==='flight_selection_required')value.data.flights.push(...value.data.flights.map((f,i)=>({...f,name:'TEST SAMO ALTERNATIVE '+i,flight_ref:'flight_'+String(i+3).repeat(32),transport_markup_reported:i?{amount:'0',currency:'RUB',source:'andromeda_transport_detail',aggregation:'unknown',uid:'private-not-public'}:{amount:'18.25',currency:'USD',source:'andromeda_transport_detail',aggregation:'unknown'}})),{...value.data.flights[0],name:'TEST SAMO UNPRICED',flight_ref:'flight_'+'5'.repeat(32),transport_markup_reported:{amount:'-100',currency:'RUB',source:'andromeda_transport_detail',aggregation:'unknown'}},{...value.data.flights[1],name:'TEST SAMO NO MARKUP',flight_ref:'flight_'+'6'.repeat(32),transport_markup_reported:null});if(value.data?.status==='additional_prices'&&additionalGate)await additionalGate;return new Response(JSON.stringify(value),{status:value.ok===false?502:200,headers:{'Content-Type':'application/json'}});};
const quoteFailures=[];w.addEventListener('anytour:quote-failure',e=>quoteFailures.push(e.detail));
let lastSamoOffer,quoteControl=null;
for(const file of scripts){
 if(file==='visual-search/app.js'){
  const canonical=w.AnyTourPrototypeData;
  w.AnyTourPrototypeData=Object.freeze(Object.create(canonical,{quote:{value:async(...args)=>{const control=quoteControl,tour=await canonical.quote(...args);if(control){control.started=true;await control.pending;if(control.error)throw control.error;}return tour;}},verifyAndromeda:{value:(...args)=>{lastSamoOffer=args[0];return canonical.verifyAndromeda(...args);}}}));
 }
 let code=source(file);
 if(file==='visual-search/app.js'){
  const marker=`function offerFromKey(key){\n for(let i=0,length=hotels.length;i<length;i++){\n  if(!(i in hotels))continue;\n  const offers=hotels[i].offers||[];\n  for(let j=0,count=offers.length;j<count;j++)if(j in offers&&offers[j].key===key)return offers[j];\n }\n return null;\n}`;
  assert.equal(code.split(marker).length,2,'one actual offer-key lookup owner');
  code=code.replace(marker,marker+`\nwindow.__offerLookupProbe={find:offerFromKey,swap(value){const previous=hotels;hotels=value;return previous;}};`);
 }
 w.eval(code);
 if(file==='prototype-search/config.js'){
  // Retained integration regressions opt in only to this fictional transport.
  w.V2_CONFIG.andromedaApi='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php';
  w.V2_CONFIG.andromedaQuoteApi='/_preview/search3-anex-candidate/api-andromeda-quote-preview.php';
 }
}
function offerLookupWork(){
 const probe=w.__offerLookupProbe;assert(probe);delete w.__offerLookupProbe;
 const make=()=>{
  const work={hotelOfferReads:0,keyReads:0},offers=[];
  const rows=Array.from({length:500},(_,i)=>{
   const hotelOffers=Array.from({length:10},(_,j)=>{const offer={id:i+'-'+j};Object.defineProperty(offer,'key',{configurable:true,get(){work.keyReads++;return i+'-'+j;}});offers.push(offer);return offer;});
   return Object.defineProperty({id:i},'offers',{get(){work.hotelOfferReads++;return hotelOffers;}});
  });
  return {work,offers,rows};
 };
 const previous=(rows,key)=>{const flat=rows.flatMap(h=>h.offers||[]);return {value:flat.find(o=>o.key===key)||null,flattened:flat.length};};
 const cases=[['0-0',{hotelOfferReads:1,keyReads:1}],['250-0',{hotelOfferReads:251,keyReads:2501}],['missing',{hotelOfferReads:500,keyReads:5000}]];
 const results=[];
 for(const [key,expected] of cases){
  const before=make(),old=previous(before.rows,key),after=make(),restore=probe.swap(after.rows),value=probe.find(key);probe.swap(restore);
  assert.strictEqual(value,key==='missing'?null:after.offers.find(o=>o.id===key));assert.deepEqual(after.work,expected);
  assert.equal(old.flattened,5000);assert.deepEqual(before.work,{hotelOfferReads:500,keyReads:expected.keyReads});
  results.push({key,previous:{...before.work,flattened:old.flattened},current:{...after.work,flattened:0}});
 }
 const duplicate=make(),first=duplicate.rows[0].offers[0],second=duplicate.rows[1].offers[0];Object.defineProperty(first,'key',{value:'duplicate'});Object.defineProperty(second,'key',{value:'duplicate'});
 const restore=probe.swap(duplicate.rows);assert.strictEqual(probe.find('duplicate'),first,'first duplicate-key offer identity is retained');probe.swap(restore);
 return results;
}
const offerLookupEvidence=offerLookupWork();
const settle=async()=>{await new Promise(resolve=>setTimeout(resolve,120));};
// Positive readiness polls share the same 4.8s bound, with finer observation.
// Keep settle() unchanged for negative/passive-operation observation windows.
const wait=async(fn)=>{for(let i=0;i<160;i++){if(fn())return;await new Promise(resolve=>setTimeout(resolve,30));}throw Error('Timed out: '+q('#cards').textContent+' / '+q('#modal-body').textContent);};
const continueToFlights=async()=>{
 const start=q('[data-action="start-tour-flights"]'),retry=q('[data-action="retry-flights"]');if(!start&&!retry)return;
 const before=transport.calls.filter(c=>['tour','flights'].includes(c.action)).length;
 if(start){assert(q('.chosen-stay'),'exact room/meal visible before any quote');click('[data-action="start-tour-flights"]');await wait(()=>q('[data-action="apply-flight"]'));assert.equal(transport.calls.filter(c=>['tour','flights'].includes(c.action)).length,before+2,'one quote and one flight request after explicit action');click('[data-action="apply-flight"]');await settle();assert(q('#prototype-lead-form'),'one flight confirmation opens application directly');click('#modal-back');await settle();}
 else{click('[data-action="retry-flights"]');await wait(()=>q('[data-action="apply-flight"]'));assert.equal(transport.calls.filter(c=>['tour','flights'].includes(c.action)).length,before+1,'an already actualized tour needs only one explicit flight request');click('[data-action="apply-flight"]');await settle();assert(q('#prototype-lead-form'),'one flight confirmation opens application directly');click('#modal-back');await settle();}
};
const starts=()=>transport.calls.filter(c=>c.action==='search_start').length;
// Fresh receiving contexts keep retry/position regressions independent of the
// retained successful TV selection in the cumulative journey below.
async function quoteReturnRegressions(){
 for(const width of [390,1280]){
  const t=fixture(),localErrors=[],console=new VirtualConsole();console.on('jsdomError',e=>localErrors.push(e.message));
  const local=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({...trip,ages:''}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:console});
  const win=local.window,doc=win.document,get=s=>doc.querySelector(s),tap=s=>{assert(get(s),s);get(s).click();};
  let quote=null,flights=null;
  Object.assign(win,{innerWidth:width,structuredClone,TextEncoder,CSS:w.CSS});Object.defineProperty(win,'crypto',{value:require('node:crypto').webcrypto});
  win.matchMedia=()=>({matches:width<768,addEventListener(){},removeEventListener(){}});win.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
  win.HTMLElement.prototype.scrollIntoView=function(){};win.scrollTo=()=>{};
  // JSDOM visibility only: real focus/scroll geometry is tested in compiled Chromium.
  win.HTMLElement.prototype.getClientRects=function(){return [{}];};
  win.HTMLDialogElement.prototype.showModal=function(){this.open=true};win.HTMLDialogElement.prototype.close=function(){this.open=false};
  const append=doc.head.append.bind(doc.head);doc.head.append=(...nodes)=>{append(...nodes);for(const node of nodes)if(node.tagName==='SCRIPT'){assert(new URL(node.src).pathname.endsWith('/flight-picker-ui-v1.js'));queueMicrotask(()=>{win.eval(source('visual-search/flight-picker-ui-v1.js'));node.onload();});}};
  win.fetch=async(url,options={})=>{assert(!String(url).includes('lead-bridge'),'no real lead transport');const body=options.body?JSON.parse(options.body):{},action=body.action||new URL(url,win.location.href).searchParams.get('action');if(action==='flights'&&flights){flights.started=true;await flights.pending;}const value=await t.json(url,options);return new Response(JSON.stringify(value),{status:value.ok===false?502:200,headers:{'Content-Type':'application/json'}});};
  for(const file of scripts){
   if(file==='visual-search/app.js'){const canonical=win.AnyTourPrototypeData;win.AnyTourPrototypeData=Object.freeze(Object.create(canonical,{quote:{value:async(...args)=>{const control=quote,tour=await canonical.quote(...args);if(control){control.started=true;await control.pending;if(control.error)throw control.error;}return tour;}}}));}
   let code=source(file);if(file==='visual-search/app.js'){const marker='async function restoreURLHotel(){';assert.equal(code.split(marker).length,2);code=code.replace(marker,'window.__retention=()=>({offer:selectedOffer,type:modalType});\n'+marker);}win.eval(code);
  }
  const until=async fn=>{for(let i=0;i<100;i++){if(fn())return;await new Promise(resolve=>setTimeout(resolve,30));}throw Error('Receiving retention timeout at '+width);};
  const exact='[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]',open=()=>{tap('[data-action="all-offers"][data-id="501"]');tap(exact);};
  try{
   await until(()=>!get('.search-submit').disabled);tap('.search-submit');await until(()=>get('[data-action="all-offers"][data-id="501"]'));open();
   quote={error:Object.assign(new Error('Fictional temporary retry failure'),{code:'temporary_fixture'})};tap('[data-action="start-lead"]');await until(()=>get('#modal-body .error-text'));
   assert.equal(win.__retention().offer.quoteErrorCode,'temporary_fixture');quote=null;tap('[data-action="start-lead"]');await until(()=>win.__retention().offer.tour&&!win.__retention().offer.loading);
   const retried=win.__retention().offer;assert.equal(retried.quoteError,'');assert.equal(retried.quoteErrorCode,'');assert.equal(retried.quoteErrorTerminal,false);assert.equal(retried.tour.price,120000);
   await until(()=>get('#prototype-lead-form'));
   tap('#modal-back');tap('[data-action="close-modal"]');await until(()=>!get('#modal').open);open();
   // The retained quote starts only the explicitly requested flight inventory.
   let releaseFlights;flights={pending:new Promise(resolve=>releaseFlights=resolve)};get('#modal-body').scrollTop=183;get('#modal-body [data-action="change-room"]').focus();tap('[data-action="retry-flights"]');await until(()=>flights.started);
   assert.equal(win.__retention().type,'offer');assert.equal(get('#modal-body').scrollTop,183);assert.equal(doc.activeElement.dataset.action,'change-room');releaseFlights();await until(()=>get('[data-action="apply-flight"]'));flights=null;
   assert.equal(win.__retention().type,'flights','intentional next step is distinct from same-offer updates');assert.equal(get('#modal-body').scrollTop,0);tap('[data-action="close-modal"]');await until(()=>!get('#modal').open);
   // Fresh hotel-rooms history captures the earlier listing. It is a passive view.
   tap('[data-action="hotel-details"][data-id="501"]');await until(()=>get('#hotel-room-count'));tap('#modal-body '+exact);tap('[data-action="choose-flight"]');await until(()=>get('[data-action="apply-flight"]'));tap('[name="flight-pair"][value="1"]');tap('[data-action="apply-flight"]');await until(()=>get('#prototype-lead-form'));tap('#modal-back');
   const chosen=win.__retention().offer,before=t.calls.length;assert.equal(chosen.total,133500.5);assert.equal(String(chosen.flightChoiceId),'1');tap('[data-action="change-room"]');await until(()=>win.__retention().type==='hotel-details');
   assert.strictEqual(win.__retention().offer,chosen,'room return retains the applied exact selection');assert.equal(win.__retention().offer.key,'tourvisor%3Avisual-tv-101');assert.equal(t.calls.length,before,'passive room return spends no operation');
   tap('#modal-body '+exact);assert.equal(win.__retention().offer.total,133500.5);assert.equal(String(win.__retention().offer.flightChoiceId),'1');assert.equal(t.calls.length,before,'passive exact reopen does not recalculate');
  }finally{win.AnyTourPrototypeData.stop();await Promise.resolve();local.window.close();assert.deepEqual(localErrors,[]);}
 }
}
// The actual entry graph and canonical catalogue adapter own these passive
// regressions. JSDOM permits focus identity, not rendered/physical geometry.
async function pickerRetainedDraftRegressions(){
 const historyKey='anytour.prototype.v18.ui.v1',alias='контрольный псевдоним',pause=()=>new Promise(resolve=>setTimeout(resolve,30));
 const records={family:0,destination:0};
 async function create(width,{route=null,hotel=false}={}){
  const t=fixture(),errors=[],console=new VirtualConsole(),completed=[],started=[];
  console.on('jsdomError',error=>errors.push(error.message));
  t.state.hotelCatalogue=hotelCatalogue();t.state.catalogueAliases={2004:[alias],2005:[alias]};
  t.state.catalogueCountries=[{id:100,kind:'country',parentId:null,name:'Египет',slug:'egypt',revision:1,tourvisorIds:['100']}];
  const local=new JSDOM(source('visual-search/index.html'),{url:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/?'+new URLSearchParams({scenario:'live',...trip,adults:3,ages:'0,12',...(hotel?{hotel:'2001'}:{})}),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:console});
  const win=local.window,doc=win.document,get=s=>doc.querySelector(s),originalURL=win.location.href;
  Object.assign(win,{innerWidth:width,structuredClone,TextEncoder,CSS:w.CSS});Object.defineProperty(win,'crypto',{value:require('node:crypto').webcrypto});
  win.matchMedia=()=>({matches:width<768,addEventListener(){},removeEventListener(){}});win.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};
  win.HTMLElement.prototype.scrollIntoView=function(){};win.HTMLElement.prototype.getClientRects=function(){return [{}];};win.scrollTo=()=>{};
  win.HTMLDialogElement.prototype.showModal=function(){this.open=true};win.HTMLDialogElement.prototype.close=function(){this.open=false};
  win.fetch=async(url,options={})=>{assert(!/lead|booking/.test(String(url)),'passive picker has no lead transport');const value=await t.json(url,options);return new Response(JSON.stringify(value),{status:value.ok===false?502:200,headers:{'Content-Type':'application/json'}});};
  // Actual modal entry retains the unchanged search underneath. Replay that
  // existing history stack on reload so its native closing Back has a target.
  if(route)win.history.pushState({[historyKey]:structuredClone(route)},'',originalURL);
  for(const file of scripts){
   if(file==='visual-search/app.js'){
    const canonical=win.AnyTourPrototypeData;
    win.AnyTourPrototypeData=Object.freeze(Object.create(canonical,{lookupHotels:{value:async(...args)=>{
     const call={query:args[0],country:args[1],signal:args[2]};started.push(call);
     try{const rows=await canonical.lookupHotels(...args);completed.push({call,ids:rows.map(h=>h.id)});return rows;}
     catch(error){completed.push({call,error:error.message});throw error;}
    }}}));
   }
   win.eval(source(file));
  }
  const until=async predicate=>{for(let i=0;i<100;i++){if(predicate())return;await pause();}throw Error('Passive picker timeout '+width+' / '+get('#modal-title').textContent+' / '+get('#modal-body').textContent);};
  const tap=selector=>{const node=get(selector);assert(node,selector);assert(!node.disabled,'enabled '+selector);node.focus();node.click();};
  const input=value=>{const node=get('#destination-query');node.focus();node.value=value;node.dispatchEvent(new win.Event('input',{bubbles:true}));};
  const ui=()=>JSON.parse(JSON.stringify(win.history.state?.[historyKey]||null)),ids=()=>[...doc.querySelectorAll('.destination-hotel')].map(node=>Number(node.dataset.id));
  const selected=()=>[...doc.querySelectorAll('#destination-selection [data-action="destination-remove"]')].map(node=>Number(node.dataset.id));
  const reads=()=>t.calls.filter(call=>call.url.endsWith('/data/hotel-search-v1.php')).length;
  const passive=()=>{assert.equal(win.location.href,originalURL,'draft/Back/reload must not mutate the applied search URL');assert.equal(t.calls.filter(call=>['search_start','search_continue','quote','quote_start','quote_calculate','quote_select_flights','search','tour','flights','expand','offer','additional_prices'].includes(call.action)||/api-(?:anex|andromeda)|lead|booking/.test(call.url)).length,0,'passive picker performs no supplier, price or lead operation');assert.deepEqual(errors,[],'actual app has no JSDOM errors');};
  await until(()=>!get('.search-submit').disabled);
  if(hotel)await until(()=>get('#destination-detail').textContent===t.state.hotelCatalogue.find(h=>h.id===2001).name);
  if(route){win.dispatchEvent(new win.Event('pageshow'));await until(()=>get('#modal').open);}
  const dispose=async()=>{await pause();passive();local.window.close();};
  return{t,win,doc,get,tap,input,ui,ids,selected,reads,started,completed,until,passive,dispose};
 }
 const actionFocus=(c,action,index=null)=>{assert.equal(c.doc.activeElement.dataset.action,action,'retained usable action focus');if(index!==null)assert.equal(c.doc.activeElement.dataset.index,String(index),'focus retains the exact child index');assert(c.doc.activeElement.isConnected);assert(!c.doc.activeElement.matches(':disabled'));};
 const returnNested=(c,action)=>{if(action==='cancel')c.get('#modal').dispatchEvent(new c.win.Event('cancel',{cancelable:true}));else c.tap(action==='back'?'#modal-back':'[data-action="'+action+'"]');};
 for(const width of [390,1280]){
  const family=await create(width);let captured;
  try{
   family.tap('#search-form [data-action="guests"]');family.tap('[data-action="child-age"][data-index="1"]');family.tap('[data-action="age-pick"][data-value="13"]');await pause();
   captured=family.ui();assert.deepEqual(captured,{type:'child-age',guest:{adults:3,ages:[0,12]},choice:{index:1,value:13}},'retain a route captured from actual second-child UI');
   family.tap('[data-action="apply-age"]');assert.equal(family.get('#modal-title').textContent,'Туристы');actionFocus(family,'child-age',1);assert.match(family.get('[data-action="child-age"][data-index="1"]').textContent,/13 лет/);
   family.tap('[data-action="children-plus"]');actionFocus(family,'children-minus');assert(family.get('[data-action="children-plus"]').disabled);assert(family.get('[data-action="apply-guests"]').disabled,'missing third age still blocks Apply');
   family.tap('[data-action="child-age"][data-index="2"]');family.tap('[data-action="age-pick"][data-value="17"]');family.tap('[data-action="apply-age"]');actionFocus(family,'child-age',2);assert(!family.get('[data-action="apply-guests"]').disabled);
   for(const action of ['back','cancel']){family.tap('[data-action="child-age"][data-index="2"]');family.tap('[data-action="age-pick"][data-value="16"]');returnNested(family,action);actionFocus(family,'child-age',2);assert.match(family.get('[data-action="child-age"][data-index="2"]').textContent,/17 лет/,'nested cancel does not commit changed age');}
   family.tap('[data-action="adults-minus"]');family.tap('[data-action="adults-minus"]');assert(family.get('[data-action="adults-minus"]').disabled);actionFocus(family,'adults-plus');
   for(let i=0;i<5;i++)family.tap('[data-action="adults-plus"]');assert(family.get('[data-action="adults-plus"]').disabled);actionFocus(family,'adults-minus');
   for(let i=0;i<3;i++)family.tap('[data-action="children-minus"]');assert(family.get('[data-action="children-minus"]').disabled);actionFocus(family,'children-plus');
   family.tap('[data-action="close-modal"]');await family.until(()=>!family.get('#modal').open&&!family.ui());
   assert.equal(family.get('#guests-label').textContent,'3 взр. · 2 реб.');assert.equal(family.get('#guests-detail').textContent,'До 1 года и 12 лет');family.passive();records.family++;
  }finally{await family.dispose();}
  for(const action of ['back','cancel','apply-age']){
   const restored=await create(width,{route:captured});
   try{
    await restored.until(()=>restored.get('#modal-title').textContent==='Возраст ребёнка 2');assert.equal(restored.get('#modal-back').hidden,false,'passive reload reconstructs the existing guests parent');assert.match(restored.get('#age-summary').textContent,/13 лет/);
    returnNested(restored,action);assert.equal(restored.get('#modal-title').textContent,'Туристы');actionFocus(restored,'child-age',1);
    assert.match(restored.get('[data-action="child-age"][data-index="0"]').textContent,/До 1 года/);assert.match(restored.get('[data-action="child-age"][data-index="1"]').textContent,action==='apply-age'?/13 лет/:/12 лет/);
    assert.equal(restored.get('#guests-detail').textContent,'До 1 года и 12 лет','nested age confirmation only changes the parent draft');
    restored.tap(action==='apply-age'?'[data-action="apply-guests"]':'[data-action="close-modal"]');await restored.until(()=>!restored.get('#modal').open&&!restored.ui());
    assert.equal(restored.get('#guests-detail').textContent,action==='apply-age'?'До 1 года и 13 лет':'До 1 года и 12 лет');restored.passive();records.family++;
   }finally{await restored.dispose();}
  }
  for(const index of [-1,99,'1']){
   const invalid=await create(width,{route:{...captured,choice:{...captured.choice,index}}});
   try{await invalid.until(()=>invalid.get('#modal-title').textContent==='Туристы');assert.equal(invalid.get('#modal-back').hidden,true);assert(invalid.get('[data-action="apply-guests"]'));assert.equal(invalid.get('[data-action="apply-age"]'),null,'invalid index is not a stranded child route');assert.match(invalid.get('[data-action="child-age"][data-index="1"]').textContent,/12 лет/);invalid.passive();records.family++;}finally{await invalid.dispose();}
  }
  // A canonical request may finish in the nested confirmation. It must update
  // retained lookup state without painting or stealing the confirmation focus.
  for(const beforeReturn of [true,false])for(const fail of [false,true])for(const action of beforeReturn?['keep-destination','back','cancel','confirm-destination']:['keep-destination','confirm-destination']){
   const c=await create(width,{hotel:true});let release;
   try{
    c.tap('#search-form [data-action="destination"]');const baseline=c.reads();c.t.state.hotelLookupGates['4']=new Promise(resolve=>release=resolve);c.t.state.hotelLookupError=fail;
    c.input(alias);await c.until(()=>c.reads()===baseline+1);assert.equal(c.get('#destination-query').getAttribute('aria-busy'),'true');
    c.tap('[data-action="destination-countries"]');c.tap('[data-action="destination-country"][data-value="4"]');await pause();assert.equal(c.ui().type,'destination-replace');
    const confirmation=c.get('#modal-body').innerHTML,footer=c.get('#modal-footer').innerHTML;const control=c.get('[data-action="keep-destination"]');control.focus();
    if(beforeReturn){release();await c.until(()=>c.completed.length===1);await pause();assert.equal(c.get('#modal-body').innerHTML,confirmation,'terminal lookup does not repaint nested confirmation');assert.equal(c.get('#modal-footer').innerHTML,footer);assert.strictEqual(c.doc.activeElement,control,'terminal lookup cannot steal nested confirmation focus');}
    returnNested(c,action);assert.equal(c.get('#destination-query').value,alias);if(c.get('[data-action="destination-countries"]').getAttribute('aria-expanded')==='true')c.tap('[data-action="destination-countries"]');
    if(!beforeReturn)release();await c.until(()=>c.get('#destination-query').getAttribute('aria-busy')==='false');
    assert.equal(c.started.length,1);assert.equal(c.started[0].query,alias);assert.equal(c.started[0].country,'4');assert.equal(c.reads(),baseline+1,'return/repaint does not automatically replay catalogue lookup');assert.deepEqual(c.selected(),action==='confirm-destination'?[]:[2001],'only explicit confirm clears the exact parent selection');
    if(fail){assert(c.get('[data-action="retry-destination"]'));assert.deepEqual(c.ids(),[]);assert(c.completed[0].error);c.t.state.hotelLookupError=false;delete c.t.state.hotelLookupGates['4'];c.tap('[data-action="retry-destination"]');await c.until(()=>c.get('#destination-query').getAttribute('aria-busy')==='false'&&c.ids().length===2);assert.equal(c.reads(),baseline+2,'one existing explicit Retry performs one catalogue read');}
    assert.deepEqual(c.ids(),[2004,2005],'actual canonical alias rows retain exact IDs independent of names');assert.equal(c.get('[data-action="retry-destination"]'),null);
    if(beforeReturn&&!fail&&action==='keep-destination'){
     c.tap('[data-action="destination-remove"][data-id="2001"]');c.tap('[data-action="destination-hotel"][data-id="2004"]');c.tap('[data-action="destination-hotel"][data-id="2005"]');assert.deepEqual(c.selected(),[2004,2005]);assert.match(c.get('.destination-apply-context').textContent,/Только выбранные отели/);
     c.input('Rix');await c.until(()=>c.get('#destination-query').getAttribute('aria-busy')==='false');assert.deepEqual(c.selected(),[2004,2005],'typing never mutates OR selection');c.tap('[data-action="apply-destination"]');await c.until(()=>!c.get('#modal').open&&!c.ui());assert.equal(c.get('#destination-detail').textContent,c.t.state.hotelCatalogue.filter(h=>[2004,2005].includes(h.id)).map(h=>h.name).join(' · '));
     c.tap('#search-form [data-action="destination"]');assert.deepEqual(c.selected(),[2004,2005],'Apply retains exact IDs in the form draft');c.tap('[data-action="destination-remove"][data-id="2004"]');c.input(alias);await c.until(()=>c.get('#destination-query').getAttribute('aria-busy')==='false');c.tap('[data-action="close-modal"]');await c.until(()=>!c.get('#modal').open&&!c.ui());
     c.tap('#search-form [data-action="destination"]');assert.deepEqual(c.selected(),[2004,2005],'Cancel restores applied exact IDs');assert.equal(c.get('#destination-query').value,'','Cancel drops only the abandoned query');
    }
    c.passive();records.destination++;
   }finally{release?.();await c.dispose();}
  }
  for(const scenario of ['changed-country','changed-query','same-query-new-request','closed']){
   const c=await create(width,{hotel:true});let release;
   try{
    c.tap('#search-form [data-action="destination"]');const baseline=c.reads();c.t.state.hotelLookupGates['4']=new Promise(resolve=>release=resolve);c.input(scenario==='changed-country'?'Rix':alias);await c.until(()=>c.reads()===baseline+1);
    c.tap('[data-action="destination-countries"]');c.tap('[data-action="destination-country"][data-value="'+(scenario==='changed-country'?'100':'4')+'"]');await pause();assert.equal(c.ui().type,'destination-replace');
    if(scenario==='closed'){
     // The existing dialog backdrop handler closes the entire owner; rectangles
     // are not asserted in JSDOM, only actual lifecycle and aborted identity.
     c.get('#modal').dispatchEvent(new c.win.MouseEvent('click',{bubbles:true,clientX:1,clientY:1}));await c.until(()=>!c.get('#modal').open&&!c.ui());assert(c.started[0].signal.aborted);release();await c.until(()=>c.completed.length===1);c.tap('#search-form [data-action="destination"]');assert.equal(c.get('#destination-query').value,'');assert.equal(c.get('#destination-query').getAttribute('aria-busy'),'false');assert.deepEqual(c.ids(),[]);assert.deepEqual(c.selected(),[2001]);assert.equal(c.reads(),baseline+1,'late closed request does not re-open or auto-read');
    }else{
     returnNested(c,scenario==='changed-country'?'confirm-destination':'keep-destination');delete c.t.state.hotelLookupGates['4'];
     if(scenario!=='changed-country'){if(c.get('[data-action="destination-countries"]').getAttribute('aria-expanded')==='true')c.tap('[data-action="destination-countries"]');c.input(scenario==='changed-query'?'Rix':alias);}
     await c.until(()=>c.reads()===baseline+2&&c.get('#destination-query').getAttribute('aria-busy')==='false');
     const expected=scenario==='changed-country'?[3001,3002,3003]:scenario==='changed-query'?[2001,2002,2003,2004,2005,2006,2007,2008]:[2004,2005];assert.deepEqual(c.ids(),expected);assert(c.started[0].signal.aborted,'old request identity is cancelled');assert.equal(c.started[1].country,scenario==='changed-country'?'100':'4');
     c.get('#destination-query').focus();const focused=c.doc.activeElement,rendered=c.get('#destination-results').innerHTML;
     if(scenario==='same-query-new-request')c.t.state.hotelLookupError=true; // distinct late terminal outcome for the identical country/query tuple
     release();await c.until(()=>c.completed.length===2);await pause();if(scenario==='same-query-new-request')assert(c.completed.at(-1).error,'the older identical tuple actually completed with an error');assert.deepEqual(c.ids(),expected,'late old response cannot replace current canonical rows');assert.equal(c.get('#destination-results').innerHTML,rendered);assert.strictEqual(c.doc.activeElement,focused,'late response cannot move current input focus');assert.equal(c.reads(),baseline+2);assert.equal(c.get('[data-action="retry-destination"]'),null,'stale same-tuple error cannot replace the current success');
    }
    c.passive();records.destination++;
   }finally{release?.();await c.dispose();}
  }
 }
 console.log('PASS retained picker lifecycle: '+records.family+' family/history/index/boundary cases, '+records.destination+' nested canonical completion/error/retry/late cases at390/1280; supplier/lead0; geometry/physical not modelled');
}
(async()=>{
 // Each complete matrix owns its windows, fixtures and errors. Keep the
 // retained corpus ordered and settle both before closing their shared CSS host.
 const completed=await Promise.allSettled([
  pickerRetainedDraftRegressions(),
  (async()=>{
 await quoteReturnRegressions();
 await wait(()=>!q('.search-submit').disabled);
 assert.equal(starts(),0,'opening shared/search URL never spends a supplier search');assert.equal(d.querySelectorAll('.hotel-card').length,0);
 assert.equal(coldScripts.length,0,'catalogue bootstrap leaves the flight UI cold');
 // Form pickers preserve canonical values and spend no supplier searches.
 click('[data-action="departure"]');
 assert.deepEqual([...d.querySelectorAll('[data-action="choose-departure"]')].map(x=>x.dataset.value),['Москва','Казань','Екатеринбург']);
 q('#departure-query').focus();departureViewport.height=420;departureViewport.offsetTop=64;departureViewport.dispatchEvent(new w.Event('resize'));
 assert.equal(q('#modal').classList.contains('viewport-keyboard'),true,'departure search uses the visible mobile viewport');
 assert.equal(q('#modal').style.getPropertyValue('--modal-viewport-top'),'72px');assert.equal(q('#modal').style.getPropertyValue('--modal-viewport-height'),'404px');
 assert.equal(d.activeElement,q('#departure-query'));q('#modal-body').scrollTop=73;
 departureViewport.offsetTop=24;departureViewport.dispatchEvent(new w.Event('scroll'));assert.equal(q('#modal-body').scrollTop,73);assert.equal(d.activeElement,q('#departure-query'));assert.equal(q('#departure-summary').textContent,'Москва');assert.equal(starts(),0);
 departureViewport.height=900;departureViewport.offsetTop=0;departureViewport.dispatchEvent(new w.Event('resize'));assert.equal(q('#modal').classList.contains('viewport-keyboard'),false);assert.equal(q('#modal').style.getPropertyValue('--modal-viewport-height'),'');
 w.innerWidth=768;departureViewport.height=420;departureViewport.dispatchEvent(new w.Event('resize'));assert.equal(q('#modal').classList.contains('viewport-keyboard'),false,'tablet does not inherit the mobile keyboard layout');w.innerWidth=390;departureViewport.height=900;departureViewport.dispatchEvent(new w.Event('resize'));


 q('#departure-query').value='кат';q('#departure-query').dispatchEvent(new w.Event('input',{bubbles:true}));
 assert.equal(d.querySelectorAll('[data-action="choose-departure"]').length,1);assert.equal(q('[data-action="choose-departure"]').dataset.value,'Екатеринбург');
 click('[data-action="close-modal"]');await settle();assert.equal(q('#origin').value,'Москва');
 click('#search-form [data-action="meals"]');click('[data-meal-choice][value="Завтраки"]');click('[data-action="apply-meals"]');await settle();
 click('#search-form [data-action="meals"]');assert.deepEqual([...d.querySelectorAll('[data-meal-choice]')].map(x=>x.value),['','Всё включено','Завтраки']);
 click('[data-meal-choice][value=""]');click('[data-action="apply-meals"]');await settle();
 const regions=w.AnyTourPrototypeData.catalog.regions['4'];
 w.AnyTourPrototypeData.catalog.regions['4']=[...regions,{id:'121',kind:'subregion',parentId:'21',country:'4',name:'Кадрие',tourvisorIds:['121']}];
 click('#search-form [data-action="destination"]');
 q('#destination-query').value='Кадрие';q('#destination-query').dispatchEvent(new w.Event('input',{bubbles:true}));await wait(()=>q('[data-action="destination-resort"][data-value="Кадрие"]'));
 assert(q('[data-action="destination-resort"][data-value="Кадрие"]'),'saved LOCAL subresort is reachable through the unified direction search');click('[data-action="clear-destination-query"]');
 const hierarchyRows=[];
 for(let i=0;i<40;i++){
  const suffix=String(i).padStart(2,'0');
  hierarchyRows.push({id:'perf-'+i,kind:'region',country:'4',name:'Группа '+suffix,tourvisorIds:[]});
  hierarchyRows.push({id:'perf-child-'+i,kind:'subregion',parentId:'perf-'+i,country:'4',name:'Курорт '+suffix,tourvisorIds:[]});
 }
 const previousHierarchy=hierarchyRows.filter(r=>r.kind!=='subregion').map(parent=>({
  parent,
  children:hierarchyRows.filter(r=>r.kind==='subregion'&&r.country===parent.country&&String(r.parentId)===String(parent.id))
 })).filter(group=>group.parent.country==='4').sort((a,b)=>a.parent.name.localeCompare(b.parent.name,'ru'));
 let hierarchyKindReads=0;
 const observedHierarchy=hierarchyRows.map(row=>new Proxy(row,{get(target,key,receiver){if(key==='kind')hierarchyKindReads++;return Reflect.get(target,key,receiver);}}));
 w.AnyTourPrototypeData.catalog.regions['4']=observedHierarchy;
 hierarchyKindReads=0;click('[data-action="clear-destination-query"]');const firstHierarchyReads=hierarchyKindReads;click('[data-action="toggle-destination-resorts"]');
 const renderedParents=[...d.querySelectorAll('[data-action="destination-resort"] strong')].map(el=>el.textContent);
 assert.deepEqual(renderedParents,previousHierarchy.map(group=>group.parent.name),'approved compact direction list keeps exact saved region order');assert.equal(firstHierarchyReads,hierarchyRows.length,'one destination render classifies each region once');
 q('#destination-query').value='Группа';q('#destination-query').dispatchEvent(new w.Event('input',{bubbles:true}));
 const renderedHierarchy=[...d.querySelectorAll('[data-action="destination-resort"] strong')].map(el=>el.textContent);
 assert.deepEqual(renderedHierarchy,previousHierarchy.flatMap(group=>[group.parent.name,...group.children.map(row=>row.name).sort((a,b)=>a.localeCompare(b,'ru'))]),'saved children follow their parent in matching unified direction results');
 const previousKindReads=hierarchyRows.length+previousHierarchy.length*hierarchyRows.length;
 console.log('PASS destination hierarchy: '+hierarchyRows.length+' rows, kind reads '+previousKindReads+' → '+firstHierarchyReads+', saved parent/child order preserved');
 click('[data-action="close-modal"]');await settle();w.AnyTourPrototypeData.catalog.regions['4']=regions;
 assert.equal(starts(),0,'city/meal/destination pickers never start suppliers');
 // Real catalogue adapter: photos and exact IDs; country changes retain query
 // and reject a response from a request whose AbortSignal was ignored.
 transport.state.hotelCatalogue=require('./search3-visual-live-fixture.cjs').hotelCatalogue();
 const beforePicker=starts(),tripFields=['#dates-label','#nights-label','#guests-label','#origin'].map(s=>q(s).value||q(s).textContent);
 const typeHotel=value=>{q('#destination-query').value=value;q('#destination-query').dispatchEvent(new w.Event('input',{bubbles:true}));};
 click('#search-form [data-action="destination"]');typeHotel('Rix');await wait(()=>d.querySelectorAll('.destination-hotel').length===8&&q('#destination-query').getAttribute('aria-busy')==='false');
 assert.match(q('#destination-scope').textContent,/Турция/);assert.equal(d.querySelectorAll('.destination-hotel img').length,7,'available photos are shown, missing photo uses its own placeholder');
 const missing=q('.destination-hotel-image[src$="/test-missing-photo.svg"]');missing.dispatchEvent(new w.Event('error'));assert.equal(missing.hidden,true,'broken photo cannot cover the fallback');
 click('[data-action="destination-more-hotels"]');assert.equal(d.querySelectorAll('.destination-hotel').length,10);
 assert.equal(d.querySelector('[data-action="destination-more-hotels"]'),null);assert.match(q('#destination-hotels-heading').textContent,/10 в списке/);
 click('[data-action="destination-hotel"][data-id="2002"]');assert.equal(q('[data-action="destination-hotel"][data-id="2002"]').getAttribute('aria-pressed'),'true');assert.match(q('[data-action="apply-destination"]').textContent,/Выбрать отель/);
 assert.match(q('#destination-summary').textContent,/Rixos Fictional Belek 02/);click('[data-action="close-modal"]');await settle();
 assert.deepEqual(['#dates-label','#nights-label','#guests-label','#origin'].map(s=>q(s).value||q(s).textContent),tripFields,'hotel draft cancel preserves trip');assert.match(q('#country').textContent,/Турция/);
 w.history.forward();await wait(()=>q('#modal').open&&q('[data-action="destination-hotel"][data-id="2002"]'));assert.equal(q('[data-action="destination-hotel"][data-id="2002"]').getAttribute('aria-pressed'),'true','Forward retains the exact un-applied hotel');click('[data-action="close-modal"]');await settle();
 let releaseHotel;transport.state.hotelLookupGates['4']=new Promise(resolve=>releaseHotel=resolve);
 const lookupCount=()=>transport.calls.filter(c=>c.url==='/data/hotel-search-v1.php').length,beforeLookup=lookupCount();
 click('#search-form [data-action="destination"]');typeHotel('Rix');await wait(()=>lookupCount()>beforeLookup);
 click('[data-action="destination-countries"]');assert.equal(d.querySelectorAll('[data-action="destination-country"]').length,2,'typed hotel query does not hide country switching');
 click('[data-action="destination-country"][data-value="100"]');await wait(()=>d.querySelectorAll('.destination-hotel').length===3&&q('#destination-query').getAttribute('aria-busy')==='false');
 assert.equal(q('#destination-query').value,'Rix');assert([...d.querySelectorAll('.destination-hotel strong')].every(el=>el.textContent.includes('Egypt')));assert.match(q('#destination-scope').textContent,/Египет/);
 const egypt=q('#destination-results').innerHTML;releaseHotel();delete transport.state.hotelLookupGates['4'];await settle();assert.equal(q('#destination-results').innerHTML,egypt,'late other-country response cannot replace or populate this list');
 click('[data-action="destination-countries"]');click('[data-action="destination-country"][data-value="4"]');await wait(()=>q('#destination-query').getAttribute('aria-busy')==='false');
 assert.equal(d.querySelectorAll('.destination-hotel').length,8);assert([...d.querySelectorAll('.destination-hotel strong')].every(el=>el.textContent.includes('Belek')),'other-country cached hotels never enter the current country');
 transport.state.hotelLookupError=true;typeHotel('Rixos');await wait(()=>q('[data-action="retry-destination"]'));assert.equal(q('#destination-query').value,'Rixos');assert.match(q('.destination-current-country').textContent,/Турция/);
 transport.state.hotelLookupError=false;click('[data-action="retry-destination"]');await wait(()=>q('#destination-query').getAttribute('aria-busy')==='false'&&!q('[data-action="retry-destination"]'));
 assert.equal(d.querySelectorAll('.destination-hotel').length,8);
 transport.state.catalogueAliases={2004:['контрольный псевдоним'],2005:['контрольный псевдоним']};typeHotel('контрольный псевдоним');await wait(()=>d.querySelectorAll('.destination-hotel').length===2&&q('#destination-query').getAttribute('aria-busy')==='false');
 assert.deepEqual([...d.querySelectorAll('.destination-hotel')].map(el=>el.dataset.id),['2004','2005'],'current server-accepted alternate label retains both exact canonical IDs');
 click('[data-action="destination-hotel"][data-id="2004"]');click('[data-action="destination-hotel"][data-id="2005"]');assert.match(q('[data-action="apply-destination"]').textContent,/Выбрать отели \(2\)/);click('[data-action="close-modal"]');await settle();assert.match(q('#country').textContent,/Турция/);
 w.history.forward();await wait(()=>q('#modal').open&&d.querySelectorAll('.destination-hotel').length===2&&q('#destination-query').getAttribute('aria-busy')==='false');assert.equal(d.querySelectorAll('.destination-hotel[aria-pressed="true"]').length,2,'Forward reloads the exact alias query and retains the two-ID draft');click('[data-action="close-modal"]');await settle();
 let releaseAlias;transport.state.hotelLookupGates['4']=new Promise(resolve=>releaseAlias=resolve);const beforeAlias=lookupCount();click('#search-form [data-action="destination"]');typeHotel('контрольный псевдоним');await wait(()=>lookupCount()>beforeAlias);typeHotel('неизвестное контрольное название');await wait(()=>lookupCount()>beforeAlias+1);releaseAlias();delete transport.state.hotelLookupGates['4'];await wait(()=>q('#destination-query').getAttribute('aria-busy')==='false');assert.equal(d.querySelectorAll('.destination-hotel').length,0,'old alias response and cached display names are not current matches');click('[data-action="close-modal"]');await settle();transport.state.catalogueAliases={};transport.state.hotelCatalogue=[];
 assert.equal(starts(),beforePicker,'hotel catalogue typing, country switch, retry, cancel and Forward never start suppliers');
 console.log('PASS hotel picker block: catalogue photos/fallback, 8+2 rows, exact draft IDs, country-query retention, stale country/query responses, accepted alias/Forward and retry; supplier HTTP0');
 w.innerWidth=1280;w.dispatchEvent(new w.Event('resize'));
 q('#max-price').value='abc';q('#max-price').dispatchEvent(new w.Event('input',{bubbles:true}));
 assert.equal(q('#max-price').getAttribute('aria-invalid'),'true');click('.search-submit');await settle();
 assert.equal(starts(),0,'invalid budget blocks the first desktop search');assert.equal(d.activeElement,q('#max-price'));
 q('#max-price').value='';q('#max-price').dispatchEvent(new w.Event('input',{bubbles:true}));w.innerWidth=390;w.dispatchEvent(new w.Event('resize'));
 click('[data-action="about"]');
 assert.match(q('#modal-body').textContent,/живого поиска Tourvisor и прямого ANEX/);
 assert.match(q('#modal-body').textContent,/База не подменяет живую выдачу/);
 assert.match(q('#modal-body').textContent,/Неподтверждённая или расчётная сумма/);
 assert.match(q('#modal-body').textContent,/данные не отправляются туроператору/);
 assert.doesNotMatch(q('#modal-body').textContent,/сохранённые реальные предложения|проверка цены и наличия.+не подключена/i);
 assert.equal(starts(),0,'opening live disclosure never starts a supplier search');click('[data-action="close-modal"]');await wait(()=>!q('#modal').open&&!w.history.state?.['anytour.prototype.v18.ui.v1']);
 click('#search-form [data-action="dates"]');await settle();assert.equal(starts(),0);
 const calendarDraftDay=new Date(Date.parse(day+'T12:00:00Z')+86400000).toISOString().slice(0,10);
 click(`[data-action="day-pick"][data-date="${calendarDraftDay}"]`);
 w.history.back();await wait(()=>!q('#modal').open);assert(!q('#modal').open,'browser Back closes the calendar without applying its draft');
 w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert(q(`[data-action="day-pick"][data-date="${calendarDraftDay}"]`).classList.contains('active'),'browser Forward restores the un-applied calendar date');
 assert.match(q('[data-action="apply-dates"]').textContent,new RegExp(calendarDraftDay.slice(-2).replace(/^0/,'')),'restored calendar action describes the same draft');
 assert.equal(starts(),0,'calendar Back/Forward never starts a supplier search');
 click('[data-action="close-modal"]');await wait(()=>!q('#modal').open&&!w.history.state?.['anytour.prototype.v18.ui.v1']);click('#search-form [data-action="dates"]');await settle();click('[data-action="apply-dates"]');await wait(()=>!q('#modal').open&&!w.history.state?.['anytour.prototype.v18.ui.v1']);assert.equal(starts(),0);
 click('#search-form [data-action="destination"]');await wait(()=>q('[data-action="destination-resort"][data-value="Белек"]'));
 click('[data-action="destination-resort"][data-value="Белек"]');
 w.history.back();await wait(()=>!q('#modal').open);assert(!q('#modal').open,'browser Back closes the destination picker without applying its draft');
 w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert.equal(q('[data-action="destination-resort"][data-value="Белек"]').getAttribute('aria-pressed'),'true','browser Forward restores the un-applied resort choice');
 assert.match(q('.destination-apply-context').textContent,/Любой из выбранных курортов/,'restored destination action describes the same resort draft');
 // Closing the previous picker consumes an asynchronous history entry. Wait
 // for that transition, rather than racing it against the next picker under
 // the parallel source matrix. Keep the existing bounded wait and assertions.
 click('[data-action="close-modal"]');await wait(()=>!q('#modal').open&&!w.history.state?.['anytour.prototype.v18.ui.v1']);
 click('#search-form [data-action="nights"]');click('[data-action="night-pick"][data-value="10"]');click('[data-action="night-pick"][data-value="12"]');
 w.history.back();await wait(()=>!q('#modal').open);assert(!q('#modal').open,'browser Back closes the nights picker without applying its draft');
 w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert(q('[data-action="night-pick"][data-value="10"]').classList.contains('active'),'browser Forward restores the first night boundary');
 assert(q('[data-action="night-pick"][data-value="11"]').classList.contains('in-range'),'browser Forward restores the nights range');
 assert(q('[data-action="night-pick"][data-value="12"]').classList.contains('active'),'browser Forward restores the second night boundary');
 click('[data-action="close-modal"]');await wait(()=>!q('#modal').open&&!w.history.state?.['anytour.prototype.v18.ui.v1']);
 click('#search-form [data-action="guests"]');click('[data-action="adults-plus"]');click('[data-action="children-plus"]');
 click('[data-action="child-age"][data-index="0"]');click('[data-action="age-pick"][data-value="8"]');click('[data-action="apply-age"]');
 w.history.back();await wait(()=>!q('#modal').open);assert(!q('#modal').open,'browser Back closes the guest picker without applying its draft');
 w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert.equal(q('[aria-label="Количество взрослых"]').textContent,'3','browser Forward restores the un-applied adult count');
 assert.match(q('[data-action="child-age"][data-index="0"]').textContent,/8 лет/,'browser Forward restores the un-applied child age');
 assert.equal(starts(),0,'primary picker Back/Forward never starts a supplier search');
 click('[data-action="close-modal"]');await wait(()=>!q('#modal').open&&!w.history.state?.['anytour.prototype.v18.ui.v1']);
 click('#search-form [data-action="meals"]');
 const mealDraftChoice=q('[data-meal-choice][value="Всё включено"]');assert(mealDraftChoice,'meal picker exposes the canonical live meal choice');
 q('#modal-body').scrollTop=73;mealDraftChoice.click();
 w.history.back();await wait(()=>!q('#modal').open);assert(!q('#modal').open,'browser Back closes the meal picker without applying its draft');
 w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert(q('[data-meal-choice][value="Всё включено"]').checked,'browser Forward restores the un-applied meal choice');
 assert.equal(q('#modal-body').scrollTop,73,'browser Forward restores the meal picker position');
 click('[data-action="close-modal"]');await settle();await wait(()=>!q('#modal').open&&!w.history.state?.['anytour.prototype.v18.ui.v1']);
 click('#search-form [data-action="budget"]');q('#budget-min').value='111 000';q('#budget-min').dispatchEvent(new w.Event('input',{bubbles:true}));q('#budget-max').value='222 000';q('#budget-max').dispatchEvent(new w.Event('input',{bubbles:true}));
 w.history.back();await settle();await wait(()=>!q('#modal').open);assert(!q('#modal').open,'browser Back closes the budget picker without applying its draft');
 w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert.equal(q('#budget-min').value,'111 000','browser Forward restores the un-applied minimum budget text');
 assert.equal(q('#budget-max').value,'222 000','browser Forward restores the un-applied maximum budget text');
 click('[data-action="close-modal"]');await settle();await wait(()=>!q('#modal').open&&!w.history.state?.['anytour.prototype.v18.ui.v1']);
 click('#search-form [data-action="form-filters"]');click('#modal [data-action="stars"]');click('[data-action="picker-star"][data-value="5"]');click('[data-action="apply-stars"]');q('#modal-body').scrollTop=131;
 w.history.back();await settle();await wait(()=>!q('#modal').open);assert(!q('#modal').open,'browser Back closes form filters without applying their draft');
 w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert.match(q('#modal-body [data-action="stars"]').textContent,/5★/,'browser Forward restores the un-applied category draft');
 assert.equal(q('#modal-body').scrollTop,131,'browser Forward restores the form filter position');
 assert.equal(starts(),0,'filter picker Back/Forward never starts a supplier search');
 click('[data-action="close-modal"]');await settle();
 let releaseSamoSearch;transport.state.samoSearchGate=new Promise(resolve=>releaseSamoSearch=resolve);
 click('.search-submit');await wait(()=>q('#results-summary').textContent.includes('2 варианта'));await settle();
 click('.results-toolbar [data-action="filters"]');
 const hotelEditor=q('#hotel-query');hotelEditor.focus();hotelEditor.value='Вымышленный';hotelEditor.dispatchEvent(new w.Event('input',{bubbles:true}));hotelEditor.setSelectionRange(3,8);
 releaseSamoSearch();transport.state.samoSearchGate=null;await wait(()=>q('#results-summary').textContent.includes('3 варианта'));
 assert.equal(q('#hotel-query'),hotelEditor,'late initial-source results preserve the attached filter editor');
 assert.equal(d.activeElement,hotelEditor,'progressive results do not interrupt typing');
 assert.deepEqual([hotelEditor.selectionStart,hotelEditor.selectionEnd],[3,8]);
 assert.equal(hotelEditor.value,'Вымышленный');
 const filterKeyboardStarts=starts(),filterKeyboardURL=w.location.href,filterKeyboardCards=[...d.querySelectorAll('.hotel-card')].map(el=>el.id),filterPanel=q('#filter-panel'),filterBudget=q('#min-price'),filterBudgetBefore=filterBudget.value;
 filterBudget.focus();filterBudget.value='12345';filterBudget.dispatchEvent(new w.Event('input',{bubbles:true}));filterBudget.setSelectionRange(1,3);filterPanel.scrollTop=73;
 const filterBudgetRect=filterBudget.getBoundingClientRect,filterPanelRect=filterPanel.getBoundingClientRect,filterHeader=q('.filter-top'),filterFooter=q('.mobile-filter-footer'),filterHeaderRect=filterHeader.getBoundingClientRect,filterFooterRect=filterFooter.getBoundingClientRect;
 filterBudget.getBoundingClientRect=()=>({top:130,bottom:160});filterPanel.getBoundingClientRect=()=>({top:33,bottom:437});filterHeader.getBoundingClientRect=()=>({bottom:100});filterFooter.getBoundingClientRect=()=>({top:330});
 for(const width of [360,390,430,768,1280]){
  w.innerWidth=width;departureViewport.height=420;departureViewport.offsetTop=25;departureViewport.dispatchEvent(new w.Event('resize'));
  assert.equal(filterPanel.classList.contains('viewport-keyboard'),width<=1100,'open result drawer binds only its responsive layout');
  if(width<=1100){assert.equal(filterPanel.style.getPropertyValue('--filter-viewport-top'),'33px');assert.equal(filterPanel.style.getPropertyValue('--filter-viewport-bottom'),'463px');assert.equal(filterPanel.style.getPropertyValue('--filter-viewport-height'),'404px');}else assert.equal(filterPanel.style.getPropertyValue('--filter-viewport-height'),'');
  assert.equal(d.activeElement,filterBudget);assert.deepEqual([filterBudget.selectionStart,filterBudget.selectionEnd],[1,3]);assert.equal(filterBudget.value,'12345');assert.equal(filterPanel.scrollTop,73,'visible budget input does not cause scroll jumps');assert.equal(starts(),filterKeyboardStarts);assert.equal(w.location.href,filterKeyboardURL);
 }
 w.innerWidth=390;departureViewport.height=420;departureViewport.offsetTop=35;departureViewport.dispatchEvent(new w.Event('scroll'));assert.equal(filterPanel.style.getPropertyValue('--filter-viewport-top'),'43px');assert.equal(filterBudget.value,'12345');
 filterBudget.getBoundingClientRect=()=>({top:350,bottom:380});departureViewport.dispatchEvent(new w.Event('resize'));assert.equal(filterPanel.scrollTop,123,'budget below the fixed footer is revealed within the same drawer');assert.equal(d.activeElement,filterBudget);assert.deepEqual([filterBudget.selectionStart,filterBudget.selectionEnd],[1,3]);
 filterBudget.getBoundingClientRect=filterBudgetRect;filterPanel.getBoundingClientRect=filterPanelRect;filterHeader.getBoundingClientRect=filterHeaderRect;filterFooter.getBoundingClientRect=filterFooterRect;
 departureViewport.height=900;departureViewport.offsetTop=0;departureViewport.dispatchEvent(new w.Event('resize'));assert.equal(filterPanel.classList.contains('viewport-keyboard'),false);assert.equal(filterPanel.style.getPropertyValue('--filter-viewport-bottom'),'');assert.equal(filterBudget.value,'12345');
 click('[data-action="close-filters"]');await settle();
 assert.equal(q('#min-price').value,filterBudgetBefore,'Cancel restores applied budget');assert.deepEqual([...d.querySelectorAll('.hotel-card')].map(el=>el.id),filterKeyboardCards);assert.equal(w.location.href,filterKeyboardURL);assert.equal(starts(),filterKeyboardStarts);
 departureViewport.height=420;departureViewport.offsetTop=25;departureViewport.dispatchEvent(new w.Event('scroll'));assert.equal(filterPanel.classList.contains('viewport-keyboard'),false,'late viewport event never reopens the closed drawer');departureViewport.height=900;departureViewport.offsetTop=0;departureViewport.dispatchEvent(new w.Event('resize'));
 assert.equal(q('#hotel-query').value,'','discarding the mobile filter draft restores applied conditions');
 let releaseFocusedSource;transport.state.samoSearchGate=new Promise(resolve=>releaseFocusedSource=resolve);
 click('#applied-search [data-action="edit-search"]');click('.search-submit');await wait(()=>q('#results-summary').textContent.includes('2 варианта'));
 const focusedHotelAction=q('[data-action="hotel-details"][data-id="501"]'),focusedCard=focusedHotelAction.closest('.hotel-card');focusedHotelAction.focus();
 const originalRect=w.HTMLElement.prototype.getBoundingClientRect,originalScrollTo=w.scrollTo,restoredScroll=[];
 w.HTMLElement.prototype.getBoundingClientRect=function(){if(this===focusedCard)return {top:90};if(this.id==='hotel-501')return {top:210};return originalRect.call(this);};
 w.scrollTo=options=>{restoredScroll.push(options.top);};
 releaseFocusedSource();transport.state.samoSearchGate=null;await wait(()=>q('#results-summary').textContent.includes('3 варианта'));
 assert.equal(d.activeElement,q('[data-action="hotel-details"][data-id="501"]'),'a late source preserves the focused result action while refreshing the card');
 assert(restoredScroll.includes(120),'a late source keeps the focused hotel at the same viewport position when its rank moves');
 w.HTMLElement.prototype.getBoundingClientRect=originalRect;w.scrollTo=originalScrollTo;
 assert.equal(starts(),2);assert.equal(d.querySelectorAll('.hotel-card').length,1,'three sources use one canonical hotel');
 assert.equal(w.AnyTourPrototypeData.searchId,123,'live bridge retains dynamic searchId getter');
 assert(w.AnyTourPrototypeData.currentSupplierScope,'live bridge retains current supplier coverage');
 // Applied date context follows the real local offer filter, including empty days.
 const dateStarts=starts(),dateCards=q('#cards').innerHTML,dateURL=w.location.search;
 const dateScope=()=>q('#compact-details').textContent.split(' · ')[0];
 const allDates=dateScope();assert.match(allDates,/ — /);
 click(`#price-strip [data-date="${day}"]`);
 assert.equal(dateScope(),q('#route-label').textContent.split(' · ')[1],'compact context matches the actual chosen departure');
 assert.doesNotMatch(dateScope(),/ — /);assert.equal(new URL(w.location.href).searchParams.get('date'),day);
 assert.equal(d.querySelectorAll('.hotel-card').length,1);
 const chosenCards=q('#cards').innerHTML,chosenURL=w.location.search;
 click('#applied-search [data-action="edit-search"]');click('#search-form [data-action="dates"]');
 click(`[data-action="day-pick"][data-date="${calendarDraftDay}"]`);click('[data-action="apply-dates"]');await settle();
 assert.equal(q('#cards').innerHTML,chosenCards,'date draft leaves applied results intact');
 click('#search-return');await settle();assert.equal(w.location.search,chosenURL);assert.equal(q('#cards').innerHTML,chosenCards);
 assert.equal(dateScope(),q('#route-label').textContent.split(' · ')[1],'Cancel restores the chosen date context');
 click('#clear-date');assert.equal(dateScope(),allDates);assert.equal(w.location.search,dateURL);
 click(`#price-strip [data-date="${calendarDraftDay}"]`);assert.equal(d.querySelectorAll('.hotel-card').length,0);
 assert.equal(dateScope(),q('#route-label').textContent.split(' · ')[1],'empty results retain the exact chosen day');
 click('[data-action="remove-filter"][data-key="date"]');assert.equal(dateScope(),allDates);assert.equal(q('#cards').innerHTML,dateCards);
 click(`#price-strip [data-date="${day}"]`);click(`#price-strip [data-date="${day}"]`);
 assert.equal(dateScope(),allDates);assert.equal(w.location.search,dateURL);assert.equal(starts(),dateStarts,'local date selection/clear/Cancel never starts suppliers');
 console.log('PASS applied departure context: exact-day/empty-day narrowing, clear/chip/toggle, date edit Cancel and URL/card restoration; supplier HTTP0');
 const changeOrigin=value=>{q('#origin').value=value;q('#origin').dispatchEvent(new w.Event('change',{bubbles:true}));};
 const frozenCards=q('#cards').innerHTML,frozenURL=w.location.search,searchesBeforeRecovery=starts();
 click('#applied-search [data-action="edit-search"]');transport.state.countriesFailure='2';let releaseFailedCountries;transport.state.countryGates['2']=new Promise(resolve=>releaseFailedCountries=resolve);changeOrigin('Казань');
 click('#search-form [data-action="dates"]');releaseFailedCountries();await settle();delete transport.state.countryGates['2'];
 assert.equal(q('.date-choice-tools').getAttribute('aria-busy'),'false','active catalogue failure stops the calendar loading state');
 click('[data-action="close-modal"]');await settle();
 assert(q('[data-action="retry-countries"]'),'failed departure catalogue exposes an explicit retry in the search form');
 assert(q('.search-submit').disabled);assert.equal(q('#cards').innerHTML,frozenCards);assert.equal(w.location.search,frozenURL);
 click('[data-action="destination"]');assert(q('[data-action="apply-destination"]').disabled,'old departure countries cannot be applied after failure');click('[data-action="close-modal"]');await settle();
 transport.state.countriesFailure='';click('[data-action="retry-countries"]');await wait(()=>!q('.search-submit').disabled);assert.equal(q('#origin').value,'Казань');assert(q('#catalog-error').hidden);
 click('#search-return');await wait(()=>!q('.search-submit').disabled);assert.equal(q('#origin').value,'Москва');assert.equal(q('#cards').innerHTML,frozenCards);assert.equal(w.location.search,frozenURL);
 // A completed older regions load must not unlock a newer pending departure.
 click('#applied-search [data-action="edit-search"]');let releaseRegions,releaseCountries;
 transport.state.regionsGate=new Promise(resolve=>releaseRegions=resolve);const regionReads=()=>transport.calls.filter(c=>c.action==='regions').length,beforeRegions=regionReads();
 changeOrigin('Казань');await wait(()=>regionReads()>beforeRegions);
 transport.state.regionsGate=null;transport.state.countryGates['3']=new Promise(resolve=>releaseCountries=resolve);changeOrigin('Екатеринбург');releaseRegions();await settle();
 assert(q('.search-submit').disabled,'late regions response cannot unlock a different pending departure');
 const beforeCalendar=transport.calls.filter(c=>c.action==='price_calendar').length;
 click('#search-form [data-action="dates"]');await settle();assert.match(q('.calendar-context').textContent,/Екатеринбург/,'calendar identifies the actual departure city');
 assert.equal(transport.calls.filter(c=>c.action==='price_calendar').length,beforeCalendar,'pending country catalogue does not start a calendar read');
 releaseCountries();await wait(()=>!q('.search-submit').disabled);assert.match(q('.calendar-context').textContent,/Екатеринбург/);click('[data-action="close-modal"]');await settle();delete transport.state.countryGates['3'];
 click('#search-return');await wait(()=>!q('.search-submit').disabled);assert.equal(q('#origin').value,'Москва');assert.equal(q('#cards').innerHTML,frozenCards);assert.equal(w.location.search,frozenURL);
 // A failed older country request must not replace a successful later selection.
 click('#applied-search [data-action="edit-search"]');let releaseOldCountries;transport.state.countriesFailure='2';transport.state.countryGates['2']=new Promise(resolve=>releaseOldCountries=resolve);
 changeOrigin('Казань');await settle();transport.state.countriesFailure='';changeOrigin('Екатеринбург');await wait(()=>!q('.search-submit').disabled);
 releaseOldCountries();await settle();delete transport.state.countryGates['2'];assert(!q('.search-submit').disabled);assert(q('#catalog-error').hidden);assert.equal(q('#origin').value,'Екатеринбург');
 click('#search-return');await wait(()=>!q('.search-submit').disabled);assert.equal(q('#cards').innerHTML,frozenCards);assert.equal(w.location.search,frozenURL);
 assert.equal(starts(),searchesBeforeRecovery,'catalogue recovery and cancellation never start a supplier search');

 click('[data-action="hotel-details"][data-id="501"]');assert.match(q('#modal-body').textContent,/Тестовая улица/);assert.match(q('#modal-body').textContent,/Мини-клуб/);click('[data-action="close-modal"]');await settle();
 // Both quote entry points must ignore a response after closing/reopening the same offer.
 const tvActions=()=>transport.calls.filter(c=>c.url==='/api-v2.php'&&['tour','flights'].includes(c.action));
 const openTv=()=>{click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');};
 for(const action of ['start-lead','start-tour-flights']){
  for(const failure of [false,true]){
   let release;quoteControl={pending:new Promise(resolve=>release=resolve),error:failure?new Error('Отложенный тестовый отказ'):null};
   openTv();const before=tvActions().length;click(`[data-action="${action}"]`);await wait(()=>quoteControl.started);
   assert(q('#modal-footer .primary').disabled,'a pending quote disables continuation');
   assert.match(q('.tour-selection-hint').textContent,/Получаем цену/);
   click('[data-action="close-modal"]');await settle();openTv();const unchanged=q('#modal-body').innerHTML;
   release();await settle();quoteControl=null;
   assert.equal(q('#modal-body').innerHTML,unchanged,'late '+action+' response cannot replace a reopened offer');
   assert(q('[data-action="start-lead"]'),'reopened offer still needs its own quote');
   assert(!q('#prototype-lead-form')&&!q('[data-action="choose-flight"]'));
   assert.deepEqual(tvActions().slice(before).map(c=>c.action),['tour'],'late quote never starts flights');
   click('[data-action="close-modal"]');await settle();
  }
  for(const code of ['', 'offer_expired']){
   quoteControl={error:Object.assign(new Error('Тестовый отказ актуализации'),{code})};
   openTv();click(`[data-action="${action}"]`);await wait(()=>q('#modal-body .error-text')?.textContent==='Тестовый отказ актуализации');
   assert.match(q('.footer-price-status').textContent,/не подтверждена/);
   assert.equal(q('#modal-footer .primary').dataset.action,code?'close-modal':'start-lead','terminal errors exit; temporary errors allow explicit retry');
   assert(!q('#prototype-lead-form'));quoteControl=null;
   click('[data-action="close-modal"]');await settle();
  }
 }
 const tvCallsBeforeSelection=tvActions().length;
 click('[data-action="all-offers"][data-id="501"]');await settle();
 const callsBeforeOfferListForward=transport.calls.length;q('.offer-filter-disclosure').open=true;q('#modal-body').scrollTop=142;
 click('[data-action="close-modal"]');await settle();w.history.forward();await settle();await wait(()=>q('#all-offers-list'));
 assert(q('.offer-filter-disclosure').open,'browser Forward restores the expanded concrete-tour filters');
 assert.equal(q('#modal-body').scrollTop,142,'browser Forward restores the concrete-tour list position');
 assert.equal(transport.calls.length,callsBeforeOfferListForward,'browser Forward neither restarts search nor checks an offer');
 click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');assert.equal(tvActions().length,tvCallsBeforeSelection,'opening exact tour is supplier-free');
 assert(q('[data-action="start-lead"]')&&q('[data-action="start-tour-flights"]'),'application and optional flight check are separate actions');
 click('[data-action="start-lead"]');await wait(()=>q('#prototype-lead-form'));
 assert.deepEqual(tvActions().slice(tvCallsBeforeSelection).map(c=>c.action),['tour'],'application retrieves tour details once without requesting flights');
 assert.match(q('#modal-body').textContent,/Рейс уточнит менеджер/);assert.match(q('#modal-footer').textContent,/перелёт уточняется/);
 assert.equal(q('.selection-steps li:nth-child(2)').textContent.trim(),'2Перелёт позже','skipped optional flight is explicit in the application steps');
 assert(!q('.selection-steps li:nth-child(2)').classList.contains('previous'),'a skipped flight is not presented as completed');
 assert.match(q('.tour-fuel-disclosure').textContent,/включён в цену/,'known Tourvisor fuel belongs to the unchanged supplier total');
 assert.match(q('#modal-footer').textContent,/Цена предложения/,'tour details do not claim cart actualization');
 assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/120000/,'tour fuel is not added a second time');
 q('[name="phone"]').value='+7 999 123-45-67';q('[name="consent"]').checked=true;q('#prototype-lead-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();
 assert.match(q('.lead-message').textContent,/не отправлена/);click('#modal-back');await settle();
 assert(q('[data-action="retry-flights"]')&&q('[data-action="confirm-tour"]'),'flight retry stays optional after returning from the application');
 const beforeQuoteOnlyReturn=transport.calls.length;
 click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');
 click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');await settle();
 assert(q('[data-action="confirm-tour"]'),'reopening an actualized tour without flights retains application readiness');
 click('[data-action="confirm-tour"]');await wait(()=>q('#prototype-lead-form'));
 assert.match(q('#modal-body').textContent,/Рейс уточнит менеджер/);
 assert.equal(transport.calls.length,beforeQuoteOnlyReturn,'quote-only return never repeats actualization or loads flights');
 click('#modal-back');await settle();
 await continueToFlights();await wait(()=>q('[data-action="confirm-tour"]')&&!q('[data-action="confirm-tour"]').disabled);
 assert.match(q('#modal-body').textContent,/STANDARD SEA VIEW/);
 click('[data-action="choose-flight"]');click('[name="flight-pair"][value="1"]');click('[data-action="apply-flight"]');await settle();
 assert(q('#prototype-lead-form'),'chosen flight proceeds directly to application');assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/133500/);click('#modal-back');await settle();
 assert.match(q('#detail-total').textContent.replace(/\s/g,''),/133500/);click('[data-action="confirm-tour"]');await settle();
 assert(q('#prototype-lead-form'),'canonical TV lead form connected');
 assert.equal(q('.selection-steps li:nth-child(2)').textContent.trim(),'2Перелёт','a selected flight keeps the normal step label');
 assert(q('.selection-steps li:nth-child(2)').classList.contains('previous'),'a selected flight remains a completed step');
 assert.match(q('#modal-body .tour-fuel-disclosure').textContent.replace(/\s/g,''),/20686₽/,'application retains the selected-flight fuel amount');
 assert.match(q('#modal-body .tour-fuel-disclosure').textContent,/включён в цену/);
 assert.match(q('#modal-footer').textContent,/Цена с выбранными рейсами · сбор включён/);
 assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/133500/,'disclosure does not add an unverified surcharge');
 q('[name="name"]').value='Тестовый турист';q('[name="phone"]').value='+7 999 123-45-67';q('[name="comment"]').value='Тестовый комментарий';q('[name="comment"]').dispatchEvent(new w.Event('input',{bubbles:true}));q('[name="consent"]').checked=true;
 q('#prototype-lead-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();
 assert.match(q('.lead-message').textContent,/не отправлена/);assert.equal(q('#prototype-lead-form').dataset.checked,'1');
 click('#modal-back');await settle();assert.match(q('#detail-total').textContent.replace(/\s/g,''),/133500/,'TV application Back keeps chosen flight price');click('[data-action="confirm-tour"]');await settle();
 const callsBeforeApplicationForward=transport.calls.length;q('#modal-body').scrollTop=174;
 click('[data-action="close-modal"]');await settle();w.history.forward();await settle();await wait(()=>q('#prototype-lead-form'));
 assert.equal(q('[name="name"]').value,'Тестовый турист','browser Forward keeps the application name draft');
 assert.equal(q('[name="phone"]').value,'+7 999 123-45-67','browser Forward keeps the application phone draft');
 assert.equal(q('[name="comment"]').value,'Тестовый комментарий','browser Forward keeps the application comment draft');
 assert.equal(q('[name="consent"]').checked,false,'browser Forward never restores consent');
 assert.equal(q('#prototype-lead-form').dataset.checked,undefined,'browser Forward reopens a passive unchecked application');
 assert.equal(q('#modal-body').scrollTop,174,'browser Forward restores the application form position');
 assert.equal(transport.calls.length,callsBeforeApplicationForward,'browser Forward neither repeats the quote nor sends a lead');
 const tvRequests=()=>transport.calls.filter(c=>c.url==='/api-v2.php'&&['tour','flights'].includes(c.action)).length,beforeTvReturn=tvRequests();
 click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');
 click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');await continueToFlights();await wait(()=>q('[data-action="confirm-tour"]')&&!q('[data-action="confirm-tour"]').disabled);
 assert.match(q('#detail-total').textContent.replace(/\s/g,''),/133500/,'closing and reopening TV keeps the chosen flight price');
 assert.match(q('.flight-summary').textContent,/TEST201/,'closing and reopening TV keeps the chosen outbound flight');
 assert.equal(tvRequests(),beforeTvReturn,'returning to TV never repeats the completed quote and flights');
 click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');
 transport.state.samoFlightChoice=true;
 const samo=[...q('#modal-body').querySelectorAll('[data-action="offer"]')].find(b=>b.dataset.key.startsWith('andromeda%3A'));assert(samo);samo.click();await settle();click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="apply-andromeda-flights"]'));
 const markupQuote=await w.AnyTourPrototypeData.verifyAndromeda(lastSamoOffer);
 assert.equal(markupQuote.finalPrice,null);assert.equal(markupQuote.finalPriceVerified,false,'reported flight markup never verifies a tour total');
 const markupRows=markupQuote.flights;
 assert.equal(markupRows.find(f=>f.flightRef==='flight_'+'4'.repeat(32)).transportMarkupReported.amount,'0','explicit zero is preserved');
 assert.deepEqual(Object.keys(markupRows[0].transportMarkupReported).sort(),['aggregation','amount','currency','source']);
 assert.equal(markupRows.find(f=>f.flightRef==='flight_'+'5'.repeat(32)).transportMarkupReported,null,'malformed money is unpriced');
 assert.equal(markupRows.find(f=>f.flightRef==='flight_'+'6'.repeat(32)).transportMarkupReported,null,'missing money is unpriced');
 assert.doesNotMatch(JSON.stringify(markupQuote),/private-not-public/,'only whitelisted public facts survive');
 assert.match(q('[name="andromeda-outbound"]').closest('label').textContent.replace(/\s/g,''),/Доплатаоператора:2000₽/);
 assert.match(q('[name="andromeda-outbound"][value="flight_'+'3'.repeat(32)+'"]').closest('label').textContent,/18,25\s*\$/,'native USD is displayed without RUB conversion');
 assert.match(q('[name="andromeda-return"][value="flight_'+'4'.repeat(32)+'"]').closest('label').textContent,/Доплата оператора: 0/);
 assert.match(q('[name="andromeda-outbound"][value="flight_'+'5'.repeat(32)+'"]').closest('label').textContent,/Доплата не указана/);
 assert.match(q('#andromeda-flight-price-status').textContent,/пока не подтверждена/);
 const alternative=q('[name="andromeda-outbound"][value="flight_'+'3'.repeat(32)+'"]');assert(alternative);alternative.click();
 const alternativeReturn=q('[name="andromeda-return"][value="flight_'+'4'.repeat(32)+'"]');assert(alternativeReturn);alternativeReturn.click();
 const beforeSamoForward=transport.calls.length,flightRef=alternative.value;
 click('[data-action="close-modal"]');await settle();w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert.equal(q('[name="andromeda-outbound"]:checked').value,flightRef);assert.equal(transport.calls.length,beforeSamoForward);
 assert(!q('#modal-back').hidden,'restored flight choice retains an actionable passive Back');
 click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');
 [...q('#modal-body').querySelectorAll('[data-action="offer"]')].find(b=>b.dataset.key===samo.dataset.key).click();await settle();
 assert.equal(q('[name="andromeda-outbound"]:checked').value,flightRef,'reopening the same tour retains the chosen outbound flight');
 assert.equal(q('[name="andromeda-return"]:checked').value,alternativeReturn.value,'reopening retains the chosen inbound flight');
 assert.equal(transport.calls.length,beforeSamoForward,'reopening the flight draft never repeats quote or search');
 const realNow=w.Date.now;w.Date.now=()=>realNow()+901000;
 await assert.rejects(w.AnyTourPrototypeData.verifyAndromeda(lastSamoOffer,{provider:'andromeda',outbound_ref:flightRef,return_ref:'flight_'+'2'.repeat(32)}),e=>e.code==='offer_expired');
 assert.equal(transport.calls.length,beforeSamoForward,'expired flight choice cannot spend a continuation');w.Date.now=realNow;

 click('[data-action="apply-andromeda-flights"]');assert.match(q('#andromeda-flight-price-status').textContent,/Уточняем полную цену/);await wait(()=>q('#modal-title').textContent==='Тур подтверждён');assert.match(q('#modal-body').textContent.replace(/\s/g,''),/125500/);
 const submittedPair=transport.calls.findLast(call=>call.action==='quote_select_flights').body.flight_selection;
 assert.equal(submittedPair.outbound_ref,flightRef);assert.equal(submittedPair.return_ref,alternativeReturn.value);
 const pairCalls=transport.calls.length,acceptedPair=await w.AnyTourPrototypeData.verifyAndromeda(lastSamoOffer,submittedPair);
 assert.deepEqual(Array.from(acceptedPair.flights,f=>f.flightRef),[submittedPair.outbound_ref,submittedPair.return_ref]);
 assert.equal(acceptedPair.finalPrice.amount,'125500');assert.equal(transport.calls.length,pairCalls,'verified exact-pair cache does not spend another request');
 const beforeSamoReturn=transport.calls.length;click('#modal-back');await settle();
 assert(q('#all-offers-list'),'Back from a reopened SAMO quote returns to its offer list');
 [...q('#modal-body').querySelectorAll('[data-action="offer"]')].find(b=>b.dataset.key===samo.dataset.key).click();await wait(()=>q('#modal-title').textContent==='Тур подтверждён');
 assert.equal(transport.calls.length,beforeSamoReturn,'return to retained SAMO verification adds no request');
 assert(!q('[data-action="apply-andromeda-flights"]'),'completed flight choice is never restored');transport.state.samoFlightChoice=false;
 click('[data-action="andromeda-application-preview"]');await settle();assert(q('#prototype-lead-form'));assert.match(q('#modal-body').textContent,/SAMO STANDARD/);
 assert.equal(q('[name="name"]').value,'Тестовый турист','current-search offer change keeps the name draft');
 assert.equal(q('[name="phone"]').value,'+7 999 123-45-67','current-search offer change keeps the phone draft');
 assert.equal(q('[name="comment"]').value,'Тестовый комментарий','current-search offer change keeps the comment draft');
 assert.equal(q('[name="consent"]').checked,false,'consent never carries to another offer');
 q('[name="phone"]').value='+7 999 123-45-67';q('[name="phone"]').dispatchEvent(new w.Event('input',{bubbles:true}));q('[name="consent"]').checked=true;
 q('#prototype-lead-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();assert.equal(q('#prototype-lead-form').dataset.checked,'1');assert.match(q('.lead-message').textContent,/не отправлена/);
 const beforeSamoApplicationForward=transport.calls.length;q('#modal-body').scrollTop=87;
 click('[data-action="close-modal"]');await settle();w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert(q('#prototype-lead-form'));assert.match(q('#modal-body').textContent,/SAMO STANDARD/);assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/125500/);
 assert.equal(q('[name="phone"]').value,'+7 999 123-45-67');assert.equal(q('[name="consent"]').checked,false);assert.equal(q('#prototype-lead-form').dataset.checked,undefined);
 assert.equal(q('#modal-body').scrollTop,87);assert.equal(transport.calls.length,beforeSamoApplicationForward,'SAMO Forward never requotes or submits');
 w.Date.now=()=>realNow()+901000;
 await assert.rejects(w.AnyTourPrototypeData.verifyAndromeda(lastSamoOffer),e=>e.code==='offer_expired');
 q('[name="consent"]').checked=true;q('#prototype-lead-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();
 assert.match(q('.lead-message').textContent,/Срок подтверждения тура истёк/);assert.equal(q('#prototype-lead-form').dataset.checked,undefined);
 assert.equal(q('[name="phone"]').value,'+7 999 123-45-67');
 click('[data-action="close-modal"]');await settle();w.history.forward();await settle();
 assert.match(q('#modal-body').textContent,/Срок подтверждения тура истёк/);
 assert(!q('#prototype-lead-form')&&!q('[data-action="andromeda-application-preview"]')&&!q('[data-action="refresh-hotel"]'));
 assert.equal(transport.calls.length,beforeSamoApplicationForward,'expired reuse, submit and Forward spend zero requests');w.Date.now=realNow;

 click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');
 const anex=[...q('#modal-body').querySelectorAll('[data-action="offer"]')].find(b=>b.dataset.key.startsWith('anex%3A'));assert(anex);anex.click();await settle();click('[data-action="refresh-hotel"]');await wait(()=>q('#modal-body').textContent.includes('ANEX CONCRETE'));
 assert.equal(q('#modal-title').textContent,'Ваш тур в деталях','one concrete ANEX offer opens directly without another offer list');
 assert.equal(q('#all-offers-list'),null);assert(q('[data-action="refresh-hotel"]'),'exact offer still requires explicit verification');
 assert.equal(transport.calls.filter(c=>c.action==='expand').length,1);
 assert.equal(transport.calls.filter(c=>c.action==='offer').length,0,'direct entry does not verify the concrete offer automatically');
 click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="anex-additional-prices"]'));
 let releaseAdditional;additionalGate=new Promise(resolve=>releaseAdditional=resolve);const callsBeforeAdditional=transport.calls.length;
 click('[data-action="anex-additional-prices"]');await wait(()=>transport.calls.length>callsBeforeAdditional);
 click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key^="anex%3A"]');await settle();
 assert(q('[data-action="anex-additional-prices"]').disabled,'pending APD cannot be resubmitted after reopening');
 assert.equal(transport.calls.length,callsBeforeAdditional+1,'reopen neither rechecks ANEX current nor resubmits APD');
 click('[data-action="close-modal"]');await settle();w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert(q('[data-action="anex-additional-prices"]').disabled,'browser Forward keeps the pending reservation');
 assert.equal(transport.calls.length,callsBeforeAdditional+1,'pending browser Forward does not replay APD');
 releaseAdditional();additionalGate=null;await wait(()=>q('#modal-title').textContent==='Доплаты ANEX рассчитаны');
 click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key^="anex%3A"]');
 await wait(()=>q('#modal-title').textContent==='Доплаты ANEX рассчитаны');assert.match(q('#modal-body').textContent,/не финально подтверждённая цена/);assert.match(q('#modal-body').textContent.replace(/\s/g,''),/123000/);
 assert(q('[data-action="anex-application-preview"]'));click('[data-action="anex-flights"]');await wait(()=>q('#anex-flight-inventory').textContent.includes('TEST ANEX 101'));
 assert.match(q('#anex-flight-inventory').textContent,/не выбранные рейсы/);assert.match(q('#anex-flight-inventory').textContent,/Расписание уточняется/);
 assert.equal(transport.calls.filter(c=>c.action==='flights'&&c.url.includes('anex')).length,1);
 const anexCallsBefore=transport.calls.length;click('[data-action="anex-application-preview"]');await settle();
 assert(q('#prototype-lead-form'));assert.match(q('#modal-footer').textContent,/Расчётная стоимость/);assert.match(q('#modal-body').textContent,/Расчётная стоимость требует подтверждения оператором/);
 q('[name="phone"]').value='+7 999 123-45-67';q('[name="phone"]').dispatchEvent(new w.Event('input',{bubbles:true}));q('[name="consent"]').checked=true;
 q('#prototype-lead-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();assert.equal(q('#prototype-lead-form').dataset.checked,'1');
 assert.match(q('.lead-message').textContent,/Расчётная сумма/);assert.match(q('.lead-message').textContent,/требует подтверждения/);assert.equal(transport.calls.length,anexCallsBefore,'ANEX application preview adds no provider request');
 click(q('#prototype-lead-form')?'#modal-body [data-action="all-offers"]':'#modal-footer [data-action="all-offers"]');await settle();
 assert(q('[data-action="offer"][data-key^="anex%3A"]'),'ANEX application returns to this hotel’s offer list');
 const callsBeforeAnexReturn=transport.calls.length;click('[data-action="offer"][data-key^="anex%3A"]');await settle();
 assert.equal(q('#modal-title').textContent,'Доплаты ANEX рассчитаны');assert.match(q('#modal-body').textContent.replace(/\s/g,''),/123000/);
 click('[data-action="anex-application-preview"]');await settle();assert(q('#prototype-lead-form'));
 click('#modal-back');await settle();assert.equal(q('#modal-title').textContent,'Доплаты ANEX рассчитаны');
 click('[data-action="anex-application-preview"]');await settle();
 assert.equal(transport.calls.length,callsBeforeAnexReturn,'ANEX quote and APD never replay on list/reopen/application/Back');
 click(q('#prototype-lead-form')?'#modal-body [data-action="all-offers"]':'#modal-footer [data-action="all-offers"]');click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');await continueToFlights();await wait(()=>q('[data-action="confirm-tour"]')&&!q('[data-action="confirm-tour"]').disabled);
 assert.equal(tvRequests(),beforeTvReturn,'cross-provider return reuses the same completed TV receipt');
 assert.match(q('#detail-total').textContent.replace(/\s/g,''),/133500/,'cross-provider return keeps the chosen TV price');
 const callsBeforeCrossReturn=transport.calls.length;click('#modal-back');click('#modal-back');await settle();
 assert(q('#prototype-lead-form'));assert.match(q('#modal-body').textContent,/ANEX CONCRETE/);assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/123000/);
 q('[name="phone"]').value='+7 999 123-45-67';q('[name="phone"]').dispatchEvent(new w.Event('input',{bubbles:true}));q('[name="consent"]').checked=true;
 q('#prototype-lead-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();assert.equal(q('#prototype-lead-form').dataset.checked,'1','restored provider application is bound to its own receipt');
 assert.equal(transport.calls.length,callsBeforeCrossReturn);

 click('[data-action="close-modal"]');await settle();
 const previousCards=q('#cards').innerHTML,previousURL=w.location.search;
 click('#applied-search [data-action="edit-search"]');click('#quick-stars [data-value="4"]');click('#search-return');await settle();
 assert.equal(q('#cards').innerHTML,previousCards);assert.equal(w.location.search,previousURL);assert.equal(starts(),2,'Cancel never starts another provider search');
 transport.state.tvFlightFuel=' \t ';transport.state.failAnex=true;click('#applied-search [data-action="edit-search"]');click('.search-submit');await wait(()=>q('#results-summary').textContent.includes('2 варианта'));assert.match(q('#search-status').textContent,/Получены не все предложения/);
 assert.doesNotMatch(q('#search-status').textContent,/Получаем предложения|100%/,'terminal partial search must not reuse a loading message');
 assert.match(q('#search-status').textContent,/найденные туры доступны для выбора/);
 click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');await continueToFlights();await wait(()=>q('[data-action="confirm-tour"]')&&!q('[data-action="confirm-tour"]').disabled);
 assert.equal(tvRequests(),beforeTvReturn+2,'an explicit new search invalidates the old TV selection');
 assert.match(q('#detail-total').textContent.replace(/\s/g,''),/120000/);
 assert.match(q('.tour-fuel-disclosure').textContent,/Сбор уточняется/,'blank pair fuel never becomes zero or inherits the tour amount');
 click('[data-action="confirm-tour"]');await settle();assert.match(q('.tour-fuel-disclosure').textContent,/Сбор уточняется/,'application retains unknown fuel');
 assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/120000/,'unknown fuel never changes the offered total');
 click('[data-action="close-modal"]');await settle();
 // A flight price cannot inherit the tour's fuel-inclusion assurance when its own fee is absent.
 transport.state.tvFlightFuel=undefined;
 click('#applied-search [data-action="edit-search"]');click('.search-submit');await wait(()=>!q('[data-action="stop-search"]')&&q('#results-summary').textContent.includes('2 варианта'));
 click('[data-action="all-offers"][data-id="501"]');click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');await continueToFlights();
 assert.match(q('.tour-fuel-disclosure').textContent,/включение в цену уточняется/);
 assert.doesNotMatch(q('.tour-fuel-disclosure').textContent,/включён в цену/);
 assert.match(q('#detail-total').textContent.replace(/\s/g,''),/120000/,'an inherited fuel amount never changes the selected flight price');
 click('[data-action="close-modal"]');await settle();
 // A failed current SAMO quote has one safe exit and cannot be repeated by reopening.
 const quoteCount=()=>transport.calls.filter(c=>c.url.endsWith('/api-andromeda-quote-preview.php')).length;
 for(const flightChoice of [false,true]){
  transport.state.samoFailure='supplier_auth';transport.state.samoFlightChoice=flightChoice;
  transport.state.tvFlightFuel={value:'0'};
  click('#applied-search [data-action="edit-search"]');click('.search-submit');await wait(()=>!q('[data-action="stop-search"]')&&q('#results-summary').textContent.includes('2 варианта'));
  click('[data-action="all-offers"][data-id="501"]');
  if(!flightChoice){
   click('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');await continueToFlights();await wait(()=>q('[data-action="confirm-tour"]')&&!q('[data-action="confirm-tour"]').disabled);
   assert.match(q('.tour-fuel-disclosure').textContent,/Без доплаты по сбору/,'explicit supplier zero is preserved');
   click('[data-action="confirm-tour"]');await settle();assert.match(q('.tour-fuel-disclosure').textContent,/Без доплаты по сбору/);
   click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');
  }
  const chooseSamo=()=>[...q('#modal-body').querySelectorAll('[data-action="offer"]')].find(b=>b.dataset.key.startsWith('andromeda%3A')).click();
  chooseSamo();await settle();const before=quoteCount();click('[data-action="refresh-hotel"]');
  if(flightChoice){await wait(()=>q('[data-action="apply-andromeda-flights"]'));click('[data-action="apply-andromeda-flights"]');}
  await wait(()=>q('#modal-body .error-text')?.textContent.includes('Подтверждение тура не получено'));
  assert.match(q('#modal-footer').textContent,/Цена из выдачи · не подтверждена/);
  assert.match(q('#modal-footer').textContent,/Выбрать другой тур/);assert(!q('[data-action="refresh-hotel"]'));
  assert.equal(quoteCount(),before+(flightChoice?2:1));
  assert.equal(quoteFailures.at(-1).failureCategory,'supplier_auth');assert.equal(quoteFailures.at(-1).httpStatus,502);
  click(q('#prototype-lead-form')?'#modal-body [data-action="all-offers"]':'#modal-footer [data-action="all-offers"]');chooseSamo();await settle();
  assert.match(q('#modal-body .error-text').textContent,/Подтверждение тура не получено/);
  assert.equal(quoteCount(),before+(flightChoice?2:1),'reopening must never resubmit a sealed attempt');
  click('[data-action="close-modal"]');await settle();
 }
 assert.equal(quoteFailures.length,2,'only the first failure emits a local diagnostic');
 // Explicitly foreign pairs cannot unlock an application; old completed no-ref receipts remain sealed.
 for(const mode of ['swapped','foreign','legacy']){
  Object.assign(transport.state,{samoFailure:null,samoFlightChoice:true,samoVerifiedPair:mode});
  click('#applied-search [data-action="edit-search"]');click('.search-submit');await wait(()=>!q('[data-action="stop-search"]')&&q('#results-summary').textContent.includes('2 варианта'));
  click('[data-action="all-offers"][data-id="501"]');
  const choose=()=>[...q('#modal-body').querySelectorAll('[data-action="offer"]')].find(b=>b.dataset.key.startsWith('andromeda%3A')).click();
  choose();await settle();const before=quoteCount();click('[data-action="refresh-hotel"]');await wait(()=>q('[data-action="apply-andromeda-flights"]'));click('[data-action="apply-andromeda-flights"]');
  if(mode==='legacy'){
   await wait(()=>q('[data-action="andromeda-application-preview"]'));assert.match(q('#modal-footer').textContent.replace(/\s/g,''),/125500/);
   const prior=transport.calls.length,legacy=await w.AnyTourPrototypeData.verifyAndromeda(lastSamoOffer);
   assert(legacy.flights.every(f=>!Object.hasOwn(f,'flightRef')));assert.equal(transport.calls.length,prior);
   click('[data-action="close-modal"]');await settle();click('[data-action="all-offers"][data-id="501"]');choose();await wait(()=>q('[data-action="andromeda-application-preview"]'));
  }else{
   await wait(()=>q('#modal-body .error-text')?.textContent.includes('некорректное подтверждение'));
   assert(!q('[data-action="andromeda-application-preview"]'));assert.match(q('#modal-footer').textContent,/Цена из выдачи · не подтверждена/);
   assert.equal(quoteFailures.at(-1).failureCategory,'invalid_response');
   click('#modal-footer [data-action="all-offers"]');choose();await settle();assert.match(q('#modal-body .error-text').textContent,/некорректное подтверждение/);
  }
  assert.equal(quoteCount(),before+2,'passive return retains exact/legacy or failed pair outcome without retry');
  click('[data-action="close-modal"]');await settle();
 }
 Object.assign(transport.state,{samoVerifiedPair:undefined,samoFlightChoice:false});
 // Long facet lists must refresh their choices without interrupting typing.
 transport.state.failAnex=false;transport.state.wideFacets=true;
 let releaseFacetSource;transport.state.samoSearchGate=new Promise(resolve=>releaseFacetSource=resolve);
 click('#applied-search [data-action="edit-search"]');click('.search-submit');await wait(()=>q('#results-summary').textContent.includes('10 вариантов'));
 click('.results-toolbar [data-action="filters"]');
 const facetEditor=q('[data-facet-search="operators"]'),facetStarts=starts();
 facetEditor.closest('.filter-group').querySelector('.filter-section-toggle').click();
 facetEditor.focus();facetEditor.value='Тестовый';facetEditor.dispatchEvent(new w.Event('input',{bubbles:true}));facetEditor.setSelectionRange(3,8);
 assert(!q('[data-filter="operators"][value="FUN&SUN"]'));
 releaseFacetSource();transport.state.samoSearchGate=null;await wait(()=>q('#results-summary').textContent.includes('11 вариантов'));
 assert.equal(q('[data-facet-search="operators"]'),facetEditor);assert.equal(d.activeElement,facetEditor);
 assert.deepEqual([facetEditor.selectionStart,facetEditor.selectionEnd],[3,8]);
 assert.match(q('[data-facet-options="operators"] .facet-search-status').textContent,/Найдено в списке: 8/);
 assert(q('[data-filter="operators"][value="FUN&SUN"]'),'late-source operator joins the current facet choices');
 click('.mobile-close[data-action="close-filters"]');await settle();
 click('.results-toolbar [data-action="filters"]');
 const reopenedFacetEditor=q('[data-facet-search="operators"]');
 reopenedFacetEditor.closest('.filter-group').querySelector('.filter-section-toggle').click();
 assert.equal(reopenedFacetEditor.value,'','cancelling the drawer discards its transient list query');
 const resizeStar=q('#filters [data-action="star"][data-value="5"]'),resizeStarValue=resizeStar.dataset.value;resizeStar.click();
 q('#max-price').value='abc';q('#max-price').dispatchEvent(new w.Event('input',{bubbles:true}));w.innerWidth=1280;w.dispatchEvent(new w.Event('resize'));await settle();
 assert.match(q('#active-filters').textContent,new RegExp(resizeStarValue+' ★'),'widening promotes valid mobile draft choices');
 assert(!q('#filter-panel').classList.contains('open'));assert.equal(q('#max-price').value,'abc');assert.equal(q('#max-price').getAttribute('aria-invalid'),'true');assert.equal(d.activeElement,q('#max-price'));
 assert.equal(d.body.style.overflow,'');assert.equal(d.querySelectorAll('[inert]').length,0,'desktop remains interactive after widening with invalid budget text');
 q('#max-price').value='';q('#max-price').dispatchEvent(new w.Event('input',{bubbles:true}));assert.equal(q('#max-price').getAttribute('aria-invalid'),'false');w.innerWidth=390;w.dispatchEvent(new w.Event('resize'));
 click('.results-toolbar [data-action="filters"]');const resizedFacetEditor=q('[data-facet-search="operators"]');resizedFacetEditor.closest('.filter-group').querySelector('.filter-section-toggle').click();
 resizedFacetEditor.value='FUN';resizedFacetEditor.dispatchEvent(new w.Event('input',{bubbles:true}));
 assert(!q('[data-filter="operators"][value="FUN&SUN"]').closest('label').hidden);
 click('[data-filter="operators"][value="FUN&SUN"]');click('#apply-filters');await settle();
 assert.match(q('#results-summary').textContent,/1 вариант/);assert.equal(starts(),facetStarts,'facet query and selection never start another search');
 click('#applied-search [data-action="edit-search"]');changeOrigin('Казань');await wait(()=>!q('.search-submit').disabled);click('.search-submit');
 await wait(()=>starts()===facetStarts+1&&(q('#search-status').hidden||!q('[data-action="stop-search"]'))&&q('#results-summary').textContent.includes('1 вариант'));
 assert.equal(q('[data-facet-search="operators"]').value,'','a new departure clears the previous facet-list query');
 assert.notEqual(q('[data-facet-search="operators"]'),resizedFacetEditor,'the previous draft editor does not cross search contexts');
 // A late source updates an already-open concrete-offer list without discarding its choices.
 while(q('#active-filters [data-action="remove-filter"]'))click('#active-filters [data-action="remove-filter"]');
 Object.assign(transport.state,{failAnex:false,samoFailure:null,samoFlightChoice:false,wideFacets:false});
 let releaseOfferSource;transport.state.samoSearchGate=new Promise(resolve=>releaseOfferSource=resolve);
 const beforeOfferSearch=starts();click('#applied-search [data-action="edit-search"]');click('.search-submit');
 await wait(()=>q('#results-summary').textContent.includes('2 варианта'));
 click('[data-action="all-offers"][data-id="501"]');assert.equal(q('#offer-count').textContent,'2 тура');
 const roomChoice=q('#offer-room');roomChoice.value='STANDARD SEA VIEW';roomChoice.dispatchEvent(new w.Event('change',{bubbles:true}));roomChoice.focus();
 releaseOfferSource();transport.state.samoSearchGate=null;await wait(()=>q('#results-summary').textContent.includes('3 варианта'));
 assert.equal(q('#offer-room'),roomChoice);assert.equal(d.activeElement,roomChoice);assert.equal(roomChoice.value,'STANDARD SEA VIEW');
 assert([...roomChoice.options].some(o=>o.value==='SAMO STANDARD'),'late source adds its new room option to the open list');
 assert.equal(q('#offer-count').textContent,'1 тур','the selected room filter remains applied');assert.equal(d.querySelectorAll('.grouped-offer').length,1,'filtered exact offer is visible immediately');
 roomChoice.value='';roomChoice.dispatchEvent(new w.Event('change',{bubbles:true}));assert.equal(q('#offer-count').textContent,'3 тура');
 const callsBeforeOfferGroupForward=transport.calls.length,visibleKeys=()=>[...d.querySelectorAll('.grouped-offer')].map(row=>row.dataset.offerKey),keysBeforeForward=visibleKeys();
 assert.equal(d.querySelectorAll('[data-action="offer-group"]').length,0,'approved exact list does not collapse room groups');q('#modal-body').scrollTop=157;
 click('[data-action="close-modal"]');await settle();w.history.forward();await settle();await wait(()=>q('#all-offers-list'));
 assert.deepEqual(visibleKeys(),keysBeforeForward,'Forward restores the same visible exact offers');assert.equal(q('#modal-body').scrollTop,157);
 assert.equal(transport.calls.length,callsBeforeOfferGroupForward,'offer-list history never starts a supplier or offer request');
 const uiHistoryKey='anytour.prototype.v18.ui.v1',forgedRoute={...w.history.state[uiHistoryKey],open:Array.from({length:20},(_,i)=>'forged|group|'+i)};
 w.history.replaceState({...w.history.state,[uiHistoryKey]:forgedRoute},'',w.location.href);w.history.back();await settle();w.history.forward();await settle();await wait(()=>q('#all-offers-list'));
 assert.deepEqual(visibleKeys(),keysBeforeForward,'unknown group history leaves exact offers unchanged');assert.equal(transport.calls.length,callsBeforeOfferGroupForward);
 click('#modal-body [data-action="offer"][data-key^="andromeda%3A"]');assert.match(q('#modal-body').textContent,/SAMO STANDARD/);
 assert.equal(starts(),beforeOfferSearch+1,'late list updates and choosing its offer do not start another search');
 click('[data-action="close-modal"]');await settle();
 // Hotel room choices and minimum must follow late initial results too.
 transport.state.samoMeal='BB';
 let releaseHotelSource;transport.state.samoSearchGate=new Promise(resolve=>releaseHotelSource=resolve);
 const beforeHotelSearch=starts();click('#applied-search [data-action="edit-search"]');click('.search-submit');
 await wait(()=>q('#results-summary').textContent.includes('2 варианта'));
 click('[data-action="hotel-details"][data-id="501"]');
 assert.equal(q('#hotel-room-count').textContent,'Номера: 2 · Туры: 2');
 const overview=q('.hotel-detail-photos'),room=q('.room-overview[data-room="STANDARD SEA VIEW"]');room.open=true;
 room.querySelector('summary').focus();
 releaseHotelSource();transport.state.samoSearchGate=null;await wait(()=>q('#results-summary').textContent.includes('3 варианта'));
 assert.equal(q('#hotel-room-count').textContent,'Номера: 3 · Туры: 3');
 assert.match(q('#hotel-detail-min').textContent,/119\s*000/);
 assert.equal(q('.hotel-detail-photos'),overview,'overview is not recreated');
 assert(q('.room-overview[data-room="STANDARD SEA VIEW"]').open);
 assert.equal(d.activeElement,q('.room-overview[data-room="STANDARD SEA VIEW"]>summary'));
 const mealChoice=q('#hotel-room-meal');assert(mealChoice,'late different meal creates the existing meal filter');
 mealChoice.value='Завтраки';mealChoice.dispatchEvent(new w.Event('change',{bubbles:true}));assert.equal(q('#hotel-room-count').textContent,'Номера: 1 · Туры: 1');

 assert.match(q('#hotel-detail-offers').dataset.key,/^andromeda%3A/);click('#hotel-detail-offers');assert.match(q('#modal-body').textContent,/SAMO STANDARD/);
 assert.equal(starts(),beforeHotelSearch+1);click('[data-action="close-modal"]');await settle();
 // Back from a chosen offer must reconcile a stored hotel snapshot with late initial results.
 transport.state.samoMeal='BB';
 let releaseHotelBackSource;transport.state.samoSearchGate=new Promise(resolve=>releaseHotelBackSource=resolve);
 const beforeHotelBackSearch=starts();click('#applied-search [data-action="edit-search"]');click('.search-submit');
 await wait(()=>q('#results-summary').textContent.includes('2 варианта'));
 click('[data-action="hotel-details"][data-id="501"]');
 const priorRoom=q('.room-overview[data-room="STANDARD SEA VIEW"]');priorRoom.open=true;
 const priorOffer=priorRoom.querySelector('[data-action="offer"][data-key="tourvisor%3Avisual-tv-101"]');priorOffer.focus();q('#modal-body').scrollTop=64;priorOffer.click();await continueToFlights();
 await wait(()=>q('[data-action="confirm-tour"]')&&!q('[data-action="confirm-tour"]').disabled);
 releaseHotelBackSource();transport.state.samoSearchGate=null;await wait(()=>q('#results-summary').textContent.includes('3 варианта'));
 click('#modal-back');
 assert.equal(q('#hotel-room-count').textContent,'Номера: 3 · Туры: 3','Back renders current progressive rooms, not the stored snapshot');
 assert.match(q('#hotel-detail-min').textContent,/119\s*000/);assert(q('#hotel-room-meal'));
 assert(q('.room-overview[data-room="STANDARD SEA VIEW"]').open);
 assert.equal(q('#modal-body').scrollTop,64);assert.equal(starts(),beforeHotelBackSearch+1);
 click('[data-action="close-modal"]');await settle();
 // Back must also retain an expanded nested list when a room has more than two tours.
 transport.state.wideFacets=true;const beforeMoreBackSearch=starts();click('#applied-search [data-action="edit-search"]');click('.search-submit');
 await wait(()=>q('#results-summary').textContent.includes('11 вариантов'));
 click('[data-action="hotel-details"][data-id="501"]');
 const manyRoom=q('.room-overview[data-room="STANDARD SEA VIEW"]');manyRoom.open=true;
 const moreTours=manyRoom.querySelector('.hotel-room-more');assert(moreTours);moreTours.open=true;
 const nestedOffer=moreTours.querySelector('[data-action="offer"][data-key="tourvisor%3Aoperator-tour-1"]');nestedOffer.focus();q('#modal-body').scrollTop=96;nestedOffer.click();click('[data-action="start-tour-flights"]');
 await wait(()=>q('#modal-body').textContent.includes('Не удалось подтвердить цену'));click('#modal-back');
 assert(q('.room-overview[data-room="STANDARD SEA VIEW"]').open);assert(q('.room-overview[data-room="STANDARD SEA VIEW"] .hotel-room-more').open,'Back retains the expanded nested tour list');
 assert.equal(q('#modal-body').scrollTop,96);assert.equal(starts(),beforeMoreBackSearch+1);
 click('[data-action="close-modal"]');await settle();
 // Browser Back/Forward must reopen the same passive hotel disclosure state.
 click('[data-action="hotel-details"][data-id="501"]');
 const historyRoom=q('.room-overview[data-room="STANDARD SEA VIEW"]');historyRoom.open=true;historyRoom.dispatchEvent(new w.Event('toggle'));
 const historyMore=historyRoom.querySelector('.hotel-room-more');historyMore.open=true;q('#modal-body').scrollTop=128;historyMore.dispatchEvent(new w.Event('toggle'));
 w.history.back();await settle();assert(!q('#modal').open,'browser Back closes hotel details');
 w.history.forward();await settle();await wait(()=>q('#modal').open);
 assert(q('.room-overview[data-room="STANDARD SEA VIEW"] .hotel-room-more').open,'browser Forward restores the expanded nested tour list');
 assert.equal(q('#modal-body').scrollTop,128,'browser Forward restores the hotel disclosure position');
 click('[data-action="close-modal"]');await settle();
 // Browser Back/Forward must retain how many concrete offers the user revealed.
 click('[data-action="all-offers"][data-id="501"]');
 const deepGroupMore=q('[data-action="group-more"]');assert(deepGroupMore,'a nine-offer group exposes the bounded show-more action');
 const deepGroupKey=deepGroupMore.dataset.value,[deepRoomValue,deepMealValue]=decodeURIComponent(deepGroupKey).split('|');const deepRoom=q('#offer-room');deepRoom.value=deepRoomValue;deepRoom.dispatchEvent(new w.Event('change',{bubbles:true}));const deepMeal=q('#offer-meal');deepMeal.value=deepMealValue;deepMeal.dispatchEvent(new w.Event('change',{bubbles:true}));const deepGroupBody=()=>q('#all-offers-list');const deepRows=()=>[...deepGroupBody().querySelectorAll('.grouped-offer')];
 assert.equal(deepRows().length,4);
 deepGroupBody().querySelector('[data-action="group-more"]').click();assert.equal(deepRows().length,9);
 assert(!deepGroupBody().querySelector('[data-action="group-more"]'));
 const callsBeforeOfferDepthForward=transport.calls.length;q('#modal-body').scrollTop=143;
 click('[data-action="close-modal"]');await settle();w.history.forward();await settle();await wait(()=>q('#all-offers-list'));
 assert.equal(d.querySelectorAll('[data-action="offer-group"]').length,0);
 assert.equal(deepRows().length,9,'browser Forward restores all concrete offers already revealed by the user');
 assert(!deepGroupBody().querySelector('[data-action="group-more"]'),'restored offer depth does not bring the consumed show-more action back');
 assert.equal(q('#modal-body').scrollTop,143);assert.equal(transport.calls.length,callsBeforeOfferDepthForward);
 const depthRouteKey='anytour.prototype.v18.ui.v1',depthRoute=w.history.state[depthRouteKey];
 w.history.replaceState({...w.history.state,[depthRouteKey]:{...depthRoute,limits:{[deepGroupKey]:9999,'forged-group':12}}},'',w.location.href);
 w.history.back();await settle();w.history.forward();await settle();await wait(()=>q('#all-offers-list'));
 assert.equal(deepRows().length,4,'oversized and unknown history limits fall back to the initial bounded offer depth');
 assert.equal(transport.calls.length,callsBeforeOfferDepthForward,'restoring concrete-offer depth never starts a supplier or offer request');
 click('[data-action="close-modal"]');await settle();
 // A late cheaper offer can move the exact returning choice into the nested list.
 transport.state.samoMeal='AI';transport.state.samoRoom='STANDARD SEA VIEW';
 let releaseMovedOfferSource;transport.state.samoSearchGate=new Promise(resolve=>releaseMovedOfferSource=resolve);
 const beforeMovedOfferSearch=starts();click('#applied-search [data-action="edit-search"]');click('.search-submit');
 await wait(()=>q('#results-summary').textContent.includes('10 вариантов'));
 click('[data-action="hotel-details"][data-id="501"]');
 const movingRoom=q('.room-overview[data-room="STANDARD SEA VIEW"]');movingRoom.open=true;
 const movingOffer=movingRoom.querySelector('[data-action="offer"][data-key="tourvisor%3Aoperator-tour-0"]');
 assert(movingOffer);assert(!movingOffer.closest('.hotel-room-more'),'chosen offer starts in the visible first two');movingOffer.focus();movingOffer.click();click('[data-action="start-tour-flights"]');
 await wait(()=>/Не удалось подтвердить цену|Условия поиска изменились/.test(q('#modal-body').textContent));
 releaseMovedOfferSource();transport.state.samoSearchGate=null;await wait(()=>q('#results-summary').textContent.includes('11 вариантов'));
 click('#modal-back');
 const movedOffer=q('[data-action="offer"][data-key="tourvisor%3Aoperator-tour-0"]'),movedMore=movedOffer.closest('.hotel-room-more');
 assert(movedMore,'late cheaper offer moves the exact returning choice below the first two');
 assert(movedMore.open,'Back reveals the exact returning offer after its position changes');
 assert.equal(starts(),beforeMovedOfferSearch+1);
 click('[data-action="close-modal"]');await settle();transport.state.samoRoom='SAMO STANDARD';
 const url=w.location.href;w.history.replaceState(null,'','/poisk-turov/');assert.equal(w.Search3CanonicalProfilesV1.create(()=>{}),null,'production consumer stays denied');w.history.replaceState(null,'',url);
 assert(!transport.calls.some(c=>/lead|payment/.test(c.url)));assert.deepEqual(errors,[]);
 console.log('PASS offer-key lookup work '+JSON.stringify(offerLookupEvidence));
 console.log('PASS live bridge: truthful live/DB/application disclosure; explicit search only; departure error/retry/cancel/late-response recovery; three canonical sources → one hotel; current TV quote/flights/exact-price application dry-run; contact draft survives offer change while consent resets; SAMO verified receipt; ANEX concrete + non-final surcharge; actionable Back, retained receipts and late APD, cross-provider return without replay; no live HTTP');
  })()
 ]),rejected=completed.filter(result=>result.status==='rejected');
 if(rejected.length)throw new AggregateError(rejected.map(result=>result.reason),'Complete independent source matrices failed');
 dom.window.close();
})().catch(e=>{console.error(e);console.error(transport.calls.slice(-8));dom.window.close();process.exitCode=1;});
