'use strict';
// The actual host loader with UI/history boundaries observed, no supplier I/O.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const {JSDOM,VirtualConsole}=require('jsdom');
const app=fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8');
const start=app.indexOf('function openFlightPicker('),end=app.indexOf('function updateFlightPreview(){',start);assert(start>=0&&end>start);
const stayStart=app.indexOf('function chosenStayHTML('),stayEnd=app.indexOf('\nfunction ',stayStart);assert(stayStart>=0&&stayEnd>stayStart,'actual chosen-stay owner extraction');
assert(app.slice(stayEnd+1).startsWith('function offerSelectionHint('),'chosen-stay extraction ends at the next actual owner');
const staySource=app.slice(stayStart,stayEnd);
const source=app.slice(start,end),flush=async()=>{for(let i=0;i<6;i++)await Promise.resolve();};
async function actualSession(){
 const root=__dirname+'/../v2/visual-search',errors=[],reads=[],console=new VirtualConsole();console.on('jsdomError',e=>errors.push(e.message));
 const dom=new JSDOM(fs.readFileSync(root+'/index.html','utf8'),{url:'https://fixture.test/?scenario=flights&searched=1',runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:console}),w=dom.window,d=w.document;
 const pause=()=>new Promise(resolve=>setTimeout(resolve,30)),click=s=>{const n=d.querySelector(s);assert(n,s);n.click();},change=(s,v)=>{const n=d.querySelector(s);assert(n,s);if(n.type==='checkbox')n.checked=v;else n.value=v;n.dispatchEvent(new w.Event(n.type==='search'?'input':'change',{bubbles:true}));};
 try{
  w.CSS={escape:v=>String(v).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};w.structuredClone=structuredClone;w.innerWidth=390;w.scrollTo=()=>{};w.HTMLElement.prototype.scrollIntoView=function(){};
  w.matchMedia=q=>({matches:q.includes('max-width'),addEventListener(){},removeEventListener(){}});w.IntersectionObserver=class{observe(){}disconnect(){}unobserve(){}};
  w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};w.HTMLDialogElement.prototype.close=function(){this.open=false;};
  w.fetch=async input=>{const u=new URL(input,w.location.href);assert.equal(u.origin,'https://fixture.test');assert.equal(u.pathname,'/fixtures/live-search-2026-09-23.json');reads.push(u.pathname);return{ok:true,json:async()=>JSON.parse(fs.readFileSync(root+u.pathname,'utf8'))};};
  for(const name of ['fixture-data.js','local-db-parser.js','recorded-data.js','search-lifecycle-v1.js','flight-picker-v18.js','preview-lead.js','filter-panel-v1.js','offer-list-v1.js','hotel-details-v1.js','flight-picker-ui-v1.js','app.js'])w.eval(fs.readFileSync(root+'/'+name,'utf8'));
  await pause();await pause();click('.hotel-card [data-action="offer"]');click('[data-action="start-tour-flights"]');await pause();
  const pair=()=>d.querySelector('[name="flight-pair"]:checked').value,route=()=>w.history.state['anytour.prototype.v18.ui.v1'];assert.equal(pair(),'0');
  change('[data-flight-query]','no-such-flight');assert.equal(pair(),'0','query never changes draft');const reset=d.querySelector('.flight-filter-state button');reset.focus();reset.click();assert.strictEqual(d.activeElement,d.querySelector('.flight-filter-panel>summary'));
  change('[data-flight-sort]','original');click('[data-flight-load-more]');click('[name="flight-pair"][value="3"]');change('[data-flight-filter="direct"]',true);
  d.querySelector('.flight-filter-panel').open=true;d.querySelector('.flight-time-filters').open=true;d.querySelector('[data-flight-index="3"] details').open=true;d.querySelector('#modal-body').scrollTop=123;await pause();
  click('#modal-back');click('[data-action="choose-flight"]');await pause();
  assert.equal(pair(),'0','Cancel leaves the applied pair unchanged');assert.equal(route().type,'flights');assert.equal(route().view.direct,true);assert.equal(route().view.sort,'original');assert.equal(route().view.limit,4);assert.equal(route().view.panel,true);assert.equal(route().view.timePanel,true);assert.deepEqual([...route().view.expanded],[3]);assert.equal(d.querySelector('#modal-body').scrollTop,123);
  click('[name="flight-pair"][value="3"]');await pause();const total=d.querySelector('#flight-total').textContent;assert.notEqual(total,'Цена уточняется');click('[data-action="apply-flight"]');assert(d.querySelector('#prototype-lead-form'),'Apply opens the existing lead preview');assert(d.querySelector('#modal-footer').textContent.includes(total));
  click('#modal-back');click('[data-action="choose-flight"]');await pause();assert.equal(pair(),'3','Apply retains the exact pair');assert.equal(route().view.direct,true);assert.equal(route().view.panel,true);assert.equal(d.querySelector('#modal-body').scrollTop,123);
  change('[data-flight-filter="direct"]',false);while(!d.querySelector('[data-flight-load-more]').hidden)click('[data-flight-load-more]');click('[name="flight-pair"][value="23"]');await pause();assert.equal(d.querySelector('#flight-total').textContent,'Цена уточняется');assert.equal(d.querySelector('[data-action="apply-flight"]').disabled,true,'missing total cannot enter application');
  click('#modal-back');click('[data-action="choose-flight"]');await pause();assert.equal(pair(),'3','unknown draft never replaces applied pair');click('[name="flight-pair"][value="23"]');await pause();
  const saved=JSON.parse(JSON.stringify(route()));click('[data-action="close-modal"]');await pause();assert.equal(d.querySelector('#modal').open,false);w.history.forward();await pause();await pause();assert.equal(d.querySelector('#modal').open,true);assert.equal(pair(),'23','Forward restores draft only');assert.equal(d.querySelector('[data-action="apply-flight"]').disabled,true);assert.deepEqual(JSON.parse(JSON.stringify(route().view)),saved.view);
  assert.deepEqual(errors,[]);assert.deepEqual(reads,['/fixtures/live-search-2026-09-23.json']);
 }finally{dom.window.close();}
}
function fixture(){
 const dom=new JSDOM('<head data-flight-picker-src="./flight-picker-ui-v1.js?v=exact"></head><dialog id="modal" open><h2 id="modal-title"></h2><div id="modal-body"></div><footer id="modal-footer"></footer></dialog>'),document=dom.window.document;
 const scripts=[],timers=new Map(),renders=[],binds=[];let nextTimer=0,history=0,previews=0;
 const ctx={window:{},Promise,Error,document,$:s=>document.querySelector(s),modalType:'offer',flightDraft:null,selectedOffer:{key:'fixture-exact-tv-501',hotelId:501,flightChoiceId:'0',variants:[{}],provider:'fixture',room:'Fictional STANDARD SEA VIEW',meal:'AI · fictional meal',operator:'Fictional TV operator',tour:{id:'fixture-exact-tv-501',placement:'DBL exact quote fixture'}},hotels:[{id:501,name:'Вымышленный отель'}],esc:String,money:String,data:{text:String,variantPrice:()=>{throw Error('loader cannot calculate a price')}},legHTML:()=>'',fuelText:()=>{throw Error('loader cannot read fuel')},mealLabel:o=>o.meal,displayMealLabel:o=>o.meal,roomLabel:o=>o.room,selectionStepsHTML:()=>'<steps>',rangeText:()=> 'Даты',nightsText:()=> '7 ночей',guestsText:()=> '2 взрослых',
  setTimeout:fn=>{const id=++nextTimer;timers.set(id,fn);return id;},clearTimeout:id=>timers.delete(id),
  showModal:(type,title,kicker,body)=>{ctx.modalType=type;ctx.$('#modal').open=true;ctx.$('#modal-body').innerHTML=body;ctx.$('#modal-footer').hidden=true;},
  updateFlightPreview:()=>previews++,rememberUIRoute:()=>history++};
 document.head.append=script=>scripts.push(script);
 vm.createContext(ctx);vm.runInContext(fs.readFileSync(__dirname+'/../v2/visual-search/flight-picker-v18.js','utf8'),ctx);vm.runInContext(staySource,ctx);vm.runInContext(source,ctx);
 const owner={render:(offer,id)=>{renders.push({offer,id});return '<div class="flight-options"></div>';},bind:container=>binds.push(container)};
 return {ctx,dom,scripts,timers,renders,binds,owner,history:()=>history,previews:()=>previews,close:()=>dom.window.close()};
}
(async()=>{
 const f=fixture();assert.equal(f.scripts.length,0,'bootstrap does not fetch the picker UI');f.ctx.openFlightPicker();f.ctx.openFlightPicker();assert.equal(f.scripts.length,1,'overlapping opens share the cold request');assert.equal(f.scripts[0].src,'./flight-picker-ui-v1.js?v=exact');assert.match(f.ctx.$('#modal-body').innerHTML,/role="status"/);assert(f.ctx.$('#modal-footer').hidden,'apply action is absent during loading');
 const latest=f.ctx.flightDraft;f.ctx.window.AnyTourFlightPickerUIV1=f.owner;f.scripts[0].onload();await flush();assert.equal(f.renders.length,1,'only the latest open paints');assert.strictEqual(f.renders[0].offer,latest.base);assert.equal(f.binds.length,1);assert.equal(f.history(),1);assert.equal(f.previews(),1);assert.equal(f.timers.size,0);assert(!f.ctx.$('#modal-footer').hidden);
 const stay=f.ctx.$('.flight-picker-context .chosen-stay');assert(stay,'actual chosen-stay helper supplies the picker context');
 assert.equal(stay.querySelector('.chosen-trip strong').textContent,'Даты · 7 ночей');assert.equal(stay.querySelector('.chosen-trip span').textContent,'2 взрослых');
 assert.deepEqual([...stay.querySelectorAll('dl>div')].map(row=>[row.querySelector('dt').textContent,row.querySelector('dd').textContent]),[['Номер',latest.base.room],['Питание',latest.base.meal],['Размещение',latest.base.tour.placement],['Оператор',latest.base.operator]],'exact fixture stay facts survive the actual helper');
 assert(!stay.querySelector('[data-action="change-room"]'),'picker context does not introduce a second editable room path');
 f.ctx.openFlightPicker();assert.equal(f.renders.length,2,'warm open renders synchronously');assert.equal(f.scripts.length,1,'warm open downloads nothing');f.close();
 for(const cancel of ['close','modal','draft','selection']){
  const g=fixture();g.ctx.openFlightPicker();if(cancel==='close')g.ctx.$('#modal').open=false;else if(cancel==='modal')g.ctx.modalType='gallery';else if(cancel==='draft')g.ctx.flightDraft={base:g.ctx.selectedOffer,id:'1'};else g.ctx.selectedOffer={...g.ctx.selectedOffer};
  const before=g.ctx.$('#modal-body').innerHTML;g.ctx.window.AnyTourFlightPickerUIV1=g.owner;g.scripts[0].onload();await flush();assert.equal(g.renders.length,0,cancel+' rejects stale completion');assert.equal(g.ctx.$('#modal-body').innerHTML,before);g.close();
 }
 for(const failure of ['network','timeout','missing-owner']){
  const g=fixture();g.ctx.openFlightPicker();if(failure==='network')g.scripts[0].onerror();else if(failure==='timeout')[...g.timers.values()][0]();else g.scripts[0].onload();await flush();assert.match(g.ctx.$('#modal-body').innerHTML,/role="alert"/);assert.match(g.ctx.$('#modal-body').innerHTML,/retry-flight-picker/);assert.equal(g.timers.size,0);assert(g.ctx.$('#modal-footer').hidden);
  g.ctx.renderFlightPicker();assert.equal(g.scripts.length,2,failure+' permits another request');g.ctx.window.AnyTourFlightPickerUIV1=g.owner;g.scripts[1].onload();await flush();assert.equal(g.renders.length,1);g.close();
 }
 const g=fixture();g.ctx.openFlightPicker();g.ctx.$('#modal').open=false;const before=g.ctx.$('#modal-body').innerHTML;g.scripts[0].onerror();await flush();assert.equal(g.ctx.$('#modal-body').innerHTML,before,'late failure cannot overwrite a closed picker');g.close();
 assert(app.includes("case 'retry-flight-picker':if(modalType==='flights')renderFlightPicker();"),'real event dispatcher provides retry');
 await actualSession();
 console.log('PASS cold flight picker: bootstrap0/cold1/warm0; shared newest open; closed/replaced draft/selection cancellation; network/timeout/missing-owner retry; price/fuel/supplier/lead0');
 console.log('PASS actual flight session DOM: Reset focus; Cancel/Apply exact pair; retained refinements/details/page/scroll; passive Forward unknown draft stays gated; committed snapshot read1/external HTTP0');
})().catch(error=>{console.error(error);process.exitCode=1;});

