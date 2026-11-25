'use strict';
const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
const app=fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8'),start=app.indexOf('// The hotel shell enters history immediately;'),end=app.indexOf("let renderedCardLimit=24",start);assert(start>=0&&end>start);
const loaderStart=app.indexOf('function createPresentationLoader(');assert(loaderStart>=0&&loaderStart<start,'actual shared presentation loader');
const source=app.slice(loaderStart,start)+app.slice(start,end),names=source.slice(source.indexOf('.create({')+9,source.indexOf('});}',source.indexOf('.create({'))).replace(/get hotelOfferDraft\(\)\{[^}]*\},set hotelOfferDraft\(value\)\{[^}]*\},get selectedOffer\(\)\{[^}]*\},/,'').split(','),flush=async()=>{for(let n=0;n<5;n++)await Promise.resolve();};
function fixture(){
 const scripts=[],timers=new Map(),renders=[],roomInputs=[],load={innerHTML:'old'},body={scrollTop:0},modal={open:true,classList:{add(){}}},nav={dataset:{hotelId:'1'}},rooms=[{dataset:{room:'Standard'},querySelector:()=>more}],more={open:false},offerRows=[{meal:'BB'}];let timer=0,ready=false,remembers=0,offerInventories=0;
 const ctx={window:{},Promise,Error,Number,Array,modalType:'hotel-details',state:{search:{country:'4'},filters:{meals:[]},selectedDate:null,onlyFavorites:false},hotelOfferDraft:null,selectedOffer:null,hotels:[{id:1,name:'One',resort:'Place',stars:5},{id:2,name:'Two',resort:'Place',stars:4}],
  document:{head:{dataset:{hotelDetailsSrc:'./hotel-details-v1.js?v=exact'},append:s=>scripts.push(s)},createElement:()=>({remove(){this.removed=true;}})},
  setTimeout:(fn,delay)=>{assert.equal(delay,15000,'hotel module keeps its bounded load timeout');timers.set(++timer,fn);return timer;},clearTimeout:id=>timers.delete(id),
  $:s=>({'#hotel-details-load':load,'#modal':modal,'#modal-body':body,'.hotel-section-nav':nav,'#hotel-room-count':ready?{}:null}[s]),$$:()=>rooms,
  showModal:(type,title,kicker,html)=>{ctx.modalType=type;nav.dataset.hotelId=String(ctx.hotels.find(h=>h.name===title).id);modal.open=true;ready=false;},
  hotelOffers:()=>{offerInventories++;return offerRows;},rememberUIRoute:()=>remembers++
 };for(const name of names)if(!(name in ctx))ctx[name]=()=>{};
 vm.createContext(ctx);vm.runInContext(source,ctx);
 const owner={create:()=>({openHotelDetails:id=>{const offers=ctx.hotelOffers();renders.push(['mount',id]);ready=true;return offers;},renderHotelRooms:(id,meal,restoredRooms,initialOffers)=>{const offers=initialOffers??ctx.hotelOffers();roomInputs.push(initialOffers);renders.push(['rooms',id,meal,restoredRooms]);return {hotel:ctx.hotels.find(h=>h.id===id),offers};}})};
 return {ctx,scripts,timers,renders,roomInputs,load,modal,body,more,offerRows,owner,remembers:()=>remembers,inventories:()=>offerInventories};
}
function profileFixture(warm=true){
 const f=fixture(),pending=[],profiles=[],renders=[],groups=[],create=f.owner.create;
 f.ctx.window.V2_CONFIG={localTvCatalogEnabled:true};f.ctx.state={search:{country:'4'}};
 f.ctx.hotels.forEach(h=>{h.raw={id:h.id};h.offers=f.offerRows;});
 f.ctx.data={refreshHotelContent:ids=>new Promise((resolve,reject)=>pending.push({ids,resolve,reject})),
  hotelPresentation:raw=>({id:raw.id,name:'Updated '+raw.id,offers:[]})};
 f.ctx.renderResults=()=>renders.push('cards');f.ctx.refreshLiveHotel=id=>groups.push(id);
 f.owner.create=()=>({...create(),refreshHotelProfile:(h,scroll)=>profiles.push([h.id,h.name,scroll])});
 if(warm)f.ctx.window.AnyTourHotelDetails=f.owner;
 return {...f,pending,profiles,cards:renders,groups};
}
(async()=>{
 const f=fixture();assert.equal(f.scripts.length,0);f.ctx.openHotelDetails(1);f.ctx.openHotelDetails(2);assert.equal(f.scripts.length,1);assert.match(f.load.innerHTML,/role="status"/);assert.equal(f.scripts[0].src,'./hotel-details-v1.js?v=exact');
 f.ctx.window.AnyTourHotelDetails=f.owner;f.scripts[0].onload();await flush();assert.deepEqual(f.renders,[['mount',2]],'newest hotel only');assert(f.scripts[0].removed);assert.equal(f.timers.size,0);
 f.ctx.openHotelDetails(1);assert.deepEqual(f.renders.at(-1),['mount',1]);assert.equal(f.scripts.length,1,'warm synchronous mount without new download');
 for(const cancel of ['close','replace','missing-hotel']){const g=fixture();g.ctx.openHotelDetails(1);if(cancel==='close')g.modal.open=false;else if(cancel==='replace')g.ctx.modalType='gallery';else g.ctx.hotels=[];g.ctx.window.AnyTourHotelDetails=g.owner;g.scripts[0].onload();await flush();assert.equal(g.renders.length,0,cancel+' prevents stale rendering');}
 for(const failure of ['network','timeout','missing-owner']){const g=fixture();if(failure==='missing-owner')g.ctx.window.AnyTourHotelDetails={};g.ctx.openHotelDetails(1);const outcome=vm.runInContext('loadHotelDetails()',g.ctx).catch(error=>error.message);if(failure==='network')g.scripts[0].onerror();else if(failure==='timeout')[...g.timers.values()][0]();else g.scripts[0].onload();await flush();assert.equal(await outcome,{network:'Hotel details load failed',timeout:'Hotel details load timed out','missing-owner':'Hotel details owner missing'}[failure]);assert.match(g.load.innerHTML,/role="alert"/);assert.match(g.load.innerHTML,/retry-hotel-details/);assert.equal(g.timers.size,0);assert.equal(g.scripts[0].onload,null);assert.equal(g.scripts[0].onerror,null);g.ctx.document.head.dataset.hotelDetailsSrc='./hotel-details-v1.js?v=later';g.ctx.renderHotelDetails();assert.equal(g.scripts.length,2);assert.equal(g.scripts[1].src,'./hotel-details-v1.js?v=exact','retry retains the captured hotel asset');g.ctx.window.AnyTourHotelDetails=g.owner;g.scripts[1].onload();await flush();assert.deepEqual(g.renders,[['mount',1]]);}
 {
  const g=fixture();g.ctx.openHotelDetails(1);const expired=g.scripts[0],lateLoad=expired.onload,lateError=expired.onerror;
  [...g.timers.values()][0]();await flush();g.ctx.renderHotelDetails();const retry=vm.runInContext('loadHotelDetails()',g.ctx);let settled=false;retry.then(()=>settled=true);
  lateLoad();lateError();await flush();assert.strictEqual(vm.runInContext('loadHotelDetails()',g.ctx),retry,'expired callbacks cannot clear the shared retry');assert.equal(g.scripts.length,2);assert.equal(g.timers.size,1);
  g.ctx.window.AnyTourHotelDetails=g.owner;lateLoad();await flush();assert.equal(settled,false,'expired load cannot resolve a newer request');assert.deepEqual(g.renders,[]);
  g.scripts[1].onload();assert.strictEqual(await retry,g.owner);await flush();assert.deepEqual(g.renders,[['mount',1]]);assert.equal(g.timers.size,0);assert.equal(g.scripts[1].onload,null);assert.equal(g.scripts[1].onerror,null);
  delete g.ctx.window.AnyTourHotelDetails;assert.strictEqual(vm.runInContext('loadHotelDetails()',g.ctx),retry,'successful load remains cached');assert.equal(g.scripts.length,2);
 }
 const meals=fixture();meals.ctx.window.AnyTourHotelDetails=meals.owner;meals.ctx.openHotelDetails(1);
 assert.equal(meals.inventories(),1);meals.ctx.refineHotelRoomMeal(1,'BB');
 assert.equal(meals.inventories(),1,'changing only meals reuses the current mounted room offers');
 assert.strictEqual(meals.roomInputs.at(-1),meals.offerRows);
 meals.ctx.state.filters.meals=['BB'];meals.ctx.refineHotelRoomMeal(1);
 assert.equal(meals.inventories(),2,'different applied search filters invalidate the mounted inventory');
 meals.ctx.hotels[0].offers=[{meal:'BB',total:80000}];meals.ctx.refineHotelRoomMeal(1,'BB');
 assert.equal(meals.inventories(),3,'new raw inventory is recalculated');
 meals.ctx.hotels=[...meals.ctx.hotels];meals.ctx.refineHotelRoomMeal(1,'BB');
 assert.equal(meals.inventories(),4,'new supplier generation is recalculated');
 const h=fixture();h.ctx.openHotelDetails(1,{meal:'BB',rooms:['Standard'],more:['Standard'],scroll:713});h.ctx.renderHotelRooms(1,'BB');assert.equal(h.renders.length,0,'room refresh waits for mounted DOM');h.ctx.window.AnyTourHotelDetails=h.owner;h.scripts[0].onload();await flush();assert.deepEqual(h.renders,[['mount',1],['rooms',1,'BB',['Standard']]]);assert.equal(h.inventories(),1,'history restore hands one exact owner inventory to validation and room rendering');assert.strictEqual(h.roomInputs[0],h.offerRows,'history room restoration receives the exact mounted offer array');assert.equal(h.more.open,true);assert.equal(h.body.scrollTop,713);assert.equal(h.remembers(),1);
 const c=fixture();c.ctx.openHotelDetails(1);c.modal.open=false;const pending=c.load.innerHTML;c.scripts[0].onerror();await flush();assert.equal(c.load.innerHTML,pending);
 for(const warm of [false,true]){
  const f=profileFixture(warm);f.offerRows[0]={key:'selected-group',meal:'BB',provider:'anex',raw:{anexKind:'group_minimum'}};
  f.ctx.openHotelDetails(1,{entryOfferKey:'selected-group',meal:'BB',scroll:123});
  if(!warm){f.ctx.window.AnyTourHotelDetails=f.owner;f.scripts[0].onload();await flush();}
  assert.deepEqual(f.renders,[['mount',1],['rooms',1,'BB',null]],'exact rooms are available with the profile held');assert.deepEqual(f.groups,[1]);
  const selection=f.ctx.selectedOffer,draft=f.ctx.hotelOfferDraft;f.pending[0].resolve(true);await flush();
  assert.deepEqual(f.profiles,[[1,'Updated 1',123]]);assert.deepEqual(f.cards,['cards']);assert.strictEqual(f.ctx.hotels[0].offers,f.offerRows);
  assert.strictEqual(f.ctx.selectedOffer,selection);assert.strictEqual(f.ctx.hotelOfferDraft,draft);assert.deepEqual(f.groups,[1],'late profile must never re-enter group expansion');
  assert.equal(f.renders.length,2,'late presentation does not remount the hotel or rooms');
  const inventoryCount=f.inventories();f.ctx.refineHotelRoomMeal(1,'BB');assert.equal(f.inventories(),inventoryCount+1,'a replaced profile invalidates eligibility even with an unchanged offers array');
 }
 const early=profileFixture(false);early.ctx.openHotelDetails(1);early.pending[0].resolve(true);await flush();assert.equal(early.ctx.hotels[0].name,'Updated 1');assert.deepEqual(early.profiles,[]);
 early.ctx.window.AnyTourHotelDetails=early.owner;early.scripts[0].onload();await flush();assert.deepEqual(early.renders,[['mount',1]],'content already available is used on the first mount');
 for(const end of ['reject','missing','close','replace']){
  const f=profileFixture();f.ctx.openHotelDetails(1);
  if(end==='close')f.modal.open=false;
  if(end==='replace')f.ctx.openHotelDetails(2);
  if(end==='reject')f.pending[0].reject(Error('LOCAL unavailable'));else f.pending[0].resolve(end!=='missing');
  await flush();assert.deepEqual(f.profiles,[]);assert.deepEqual(f.cards,[]);assert.strictEqual(f.ctx.hotels[0].offers,f.offerRows);assert.equal(f.load.innerHTML,'old','optional profile does not replace the room module with its error');
  if(end==='replace'){f.pending[1].resolve(true);await flush();assert.deepEqual(f.profiles,[[2,'Updated 2',null]]);}
 }
 assert(app.includes("case 'retry-hotel-details':if(modalType==='hotel-details')renderHotelDetails();"));
 console.log('PASS cold hotel-details: initial0/shared/warm/newest/close/replace/failure/retry; history inventories 3→1; room refresh guards');
})().catch(e=>{console.error(e);process.exitCode=1;});
