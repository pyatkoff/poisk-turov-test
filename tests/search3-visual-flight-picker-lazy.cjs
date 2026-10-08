'use strict';
// The actual host loader with UI/history boundaries observed, no supplier I/O.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const {JSDOM}=require('jsdom');
const app=fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8');
const start=app.indexOf('function openFlightPicker(){'),end=app.indexOf('function updateFlightPreview(){',start);assert(start>=0&&end>start);
const stayStart=app.indexOf('function chosenStayHTML('),stayEnd=app.indexOf('\nfunction ',stayStart);assert(stayStart>=0&&stayEnd>stayStart,'actual chosen-stay owner extraction');
assert(app.slice(stayEnd+1).startsWith('function offerSelectionHint('),'chosen-stay extraction ends at the next actual owner');
const staySource=app.slice(stayStart,stayEnd);
const source=app.slice(start,end),flush=async()=>{for(let i=0;i<6;i++)await Promise.resolve();};
function fixture(){
 const dom=new JSDOM('<head data-flight-picker-src="./flight-picker-ui-v1.js?v=exact"></head><dialog id="modal" open><h2 id="modal-title"></h2><div id="modal-body"></div><footer id="modal-footer"></footer></dialog>'),document=dom.window.document;
 const scripts=[],timers=new Map(),renders=[],binds=[];let nextTimer=0,history=0,previews=0;
 const ctx={window:{},Promise,Error,document,$:s=>document.querySelector(s),modalType:'offer',flightDraft:null,selectedOffer:{key:'fixture-exact-tv-501',hotelId:501,flightChoiceId:'0',variants:[{}],provider:'fixture',room:'Fictional STANDARD SEA VIEW',meal:'AI · fictional meal',operator:'Fictional TV operator',tour:{id:'fixture-exact-tv-501',placement:'DBL exact quote fixture'}},hotels:[{id:501,name:'Вымышленный отель'}],esc:String,money:String,data:{text:String,variantPrice:()=>{throw Error('loader cannot calculate a price')}},legHTML:()=>'',fuelText:()=>{throw Error('loader cannot read fuel')},mealLabel:o=>o.meal,selectionStepsHTML:()=>'<steps>',rangeText:()=> 'Даты',nightsText:()=> '7 ночей',guestsText:()=> '2 взрослых',
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
 console.log('PASS cold flight picker: bootstrap0/cold1/warm0; shared newest open; closed/replaced draft/selection cancellation; network/timeout/missing-owner retry; price/fuel/supplier/lead0');
})().catch(error=>{console.error(error);process.exitCode=1;});
