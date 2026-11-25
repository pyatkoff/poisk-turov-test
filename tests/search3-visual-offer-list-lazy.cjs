// Cold download behavior at the real loader's DOM/history boundaries.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const app=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const start=app.indexOf('let offerListRequest=0,'),end=app.indexOf('function confirmTour(){',start);assert(start>=0&&end>start);
const loaderStart=app.indexOf('function createPresentationLoader('),loaderEnd=app.indexOf('// The hotel shell enters history immediately;',loaderStart);assert(loaderStart>=0&&loaderEnd>loaderStart,'actual shared presentation loader');
const openStart=app.indexOf('let offerView=null;'),openEnd=app.indexOf('const offerCountText=',openStart);assert(openStart>=0&&openEnd>openStart);
const scopeStart=app.indexOf('function offerInventoryScope('),scopeEnd=app.indexOf('function hotelDetailsOwner(',scopeStart);assert(scopeStart>=0&&scopeEnd>scopeStart);
let source=app.slice(loaderStart,loaderEnd)+app.slice(scopeStart,scopeEnd)+app.slice(openStart,openEnd)+app.slice(start,end);const groupKeyOwner="const offerGroupKey=o=>encodeURIComponent(o.room+'|'+o.meal);";assert(source.includes(groupKeyOwner),'exact group-key owner');source=source.replace(groupKeyOwner,"const offerGroupKey=o=>(groupKeyCalls++,encodeURIComponent(o.room+'|'+o.meal));");
const context=source.match(/const api=owner.create\(\{([^}]+)\}\);/);assert(context,'real cold owner renderer context');
const dependencies=context[1].split(',').map(name=>name.trim());assert(dependencies.every(name=>/^[$A-Z_a-z][$\w]*$/.test(name)),'cold renderer context contains named live dependencies');
const flush=async()=>{for(let n=0;n<5;n++)await Promise.resolve();};
function fixture(rows=[{key:'one',day:'2026-10-10',flight:'regular',room:'SEA',meal:'BB',total:100000}]){
 const scripts=[],timers=new Map(),renders=[],list={innerHTML:'before'},modal={open:true,classList:{add:()=>{}}},filters={open:false},body={scrollTop:0};let nextTimer=0,remembers=0,hotelCalls=0;
 const ctx={window:{},Promise,Error,WeakMap,Number,Map,Object,groupKeyCalls:0,modalType:'all-offers',offerView:{id:1},state:{search:{country:'4'},filters:{meals:[]},selectedDate:null,onlyFavorites:false},data:{scenario:'fixture'},hotels:[{id:1,name:'Hotel',offers:rows}],
  setTimeout:(fn,delay)=>{assert.equal(delay,15000,'offer module keeps its bounded load timeout');const id=++nextTimer;timers.set(id,fn);return id;},clearTimeout:id=>timers.delete(id),
  document:{head:{dataset:{offerListSrc:'./offer-list-v1.js?v=exact'},append:script=>scripts.push(script)},createElement:()=>({removed:false,remove(){this.removed=true;}})},
  $:selector=>({'#all-offers-list':list,'#modal':modal,'.offer-filter-disclosure':filters,'#modal-body':body}[selector]),
  hotelOffers:hotel=>{hotelCalls++;return hotel.offers;},showModal:()=>{ctx.modalType='all-offers';modal.open=true;},boundedHistoryStrings:value=>Array.isArray(value)?value:[],offerMetaNote:()=>'',
  rememberUIRoute:()=>{remembers++;}
 };
 for(const name of dependencies)if(!(name in ctx))ctx[name]=()=>{};
 vm.createContext(ctx);vm.runInContext(source,ctx);vm.runInContext('offerView={id:1}',ctx);
 const owner={create:context=>({renderOfferList:(reset,precomputed)=>{const hotel=context.hotels.find(h=>h.id===context.offerView.id)||context.hotels[0],all=Array.isArray(precomputed)?precomputed:context.hotelOffers(hotel);renders.push({view:context.offerView,reset,precomputed,all});list.innerHTML='rendered';return {hotel,offers:all};}})};
 return {ctx,scripts,timers,renders,list,modal,filters,body,rows,owner,remembers:()=>remembers,hotelCalls:()=>hotelCalls};
}
(async()=>{
 const f=fixture();assert.equal(f.scripts.length,0,'bootstrap does not fetch the cold owner');
 f.ctx.renderOfferList(true);f.ctx.renderOfferList(false);assert.equal(f.scripts.length,1,'overlapping opens share one request');assert.match(f.list.innerHTML,/role="status"/);
 assert.equal(f.scripts[0].src,'./offer-list-v1.js?v=exact','use the entry supplied exact asset version');
 f.ctx.window.AnyTourOfferList=f.owner;f.scripts[0].onload();await flush();assert.equal(f.renders.length,1);assert.equal(f.renders[0].reset,false,'only the latest render request applies');assert.equal(vm.runInContext('offerListPage?.view===offerView',f.ctx),true,'latest full render retains its exact page API');assert(f.scripts[0].removed);assert.equal(f.timers.size,0);
 f.ctx.renderOfferList(true);assert.equal(f.renders.length,2,'warm rendering remains synchronous');assert.equal(f.scripts.length,1,'warm open has no additional download');
 const refinements=fixture();refinements.ctx.window.AnyTourOfferList=refinements.owner;
 refinements.ctx.openAllOffers(1);assert.equal(refinements.hotelCalls(),1);
 refinements.ctx.renderRefinedOfferList();assert.equal(refinements.hotelCalls(),1,'a local refinement reuses the warm source');
 assert.strictEqual(refinements.renders.at(-1).precomputed,refinements.rows);
 refinements.ctx.state.filters.meals=['BB'];refinements.ctx.renderRefinedOfferList();
 assert.equal(refinements.hotelCalls(),2,'changed applied search scope recalculates on the next refinement');
 const newOffer={...refinements.rows[0],total:85000};
 refinements.ctx.hotels[0].offers=[newOffer];refinements.ctx.renderRefinedOfferList();
 assert.equal(refinements.hotelCalls(),3,'replaced raw offers cannot reuse the older inventory');
 assert.equal(refinements.renders.at(-1).all[0].total,85000);
 refinements.ctx.hotels=[{...refinements.ctx.hotels[0]}];refinements.ctx.renderRefinedOfferList();
 assert.equal(refinements.hotelCalls(),4,'new supplier generation recalculates before rendering');
 refinements.ctx.openAllOffers(1);assert.equal(refinements.hotelCalls(),5,'a new list view starts from current data');
 const warmHistory=fixture();warmHistory.ctx.window.AnyTourOfferList=warmHistory.owner;warmHistory.ctx.openAllOffers(1,{departure:'2026-10-10',flight:'regular',room:'SEA',meal:'BB',sort:'price',open:[],limits:{}});assert.equal(warmHistory.renders.length,1);assert.strictEqual(warmHistory.renders[0].precomputed,warmHistory.rows,'warm history hands the exact validated rows to its one render');assert.equal(warmHistory.hotelCalls(),1,'warm history builds one complete inventory');
 const boundedRows=Array.from({length:500},(_,i)=>({key:'bounded-'+i,day:'2026-10-10',flight:'regular',room:'ROOM',meal:'MEAL',total:100000+i})),bounded=fixture(boundedRows),knownKey=encodeURIComponent('ROOM|MEAL'),forgedOpen=Array.from({length:20},(_,i)=>'forged-'+i);
 bounded.ctx.window.AnyTourOfferList=bounded.owner;bounded.ctx.openAllOffers(1,{departure:'2026-10-10',flight:'regular',room:'',meal:'',sort:'price',open:forgedOpen,limits:{[knownKey]:500}});assert.equal(bounded.ctx.groupKeyCalls,500,'20 restored group probes and limits share one bounded group-key inventory');assert.equal(vm.runInContext('offerView.open.length',bounded.ctx),0,'unknown restored groups remain rejected');assert.equal(vm.runInContext(`offerView.limits[${JSON.stringify(knownKey)}]`,bounded.ctx),500,'shared inventory retains exact valid restored depth');
 const coldHistory=fixture();coldHistory.ctx.openAllOffers(1,{departure:'2026-10-10',flight:'regular',room:'SEA',meal:'BB',sort:'price',open:[],limits:{}});assert.equal(coldHistory.hotelCalls(),1);coldHistory.ctx.window.AnyTourOfferList=coldHistory.owner;coldHistory.scripts[0].onload();await flush();assert.equal(coldHistory.renders.length,1);assert.equal(coldHistory.renders[0].precomputed,null,'cold history does not retain rows across the asynchronous owner load');assert.equal(coldHistory.hotelCalls(),2,'cold history deliberately reads fresh rows after loading');
 for(const cancel of ['close','replace','view']){
  const g=fixture();g.ctx.renderOfferList();if(cancel==='close')g.modal.open=false;else if(cancel==='replace')g.ctx.modalType='gallery';else vm.runInContext('offerView={id:2}',g.ctx);
  const pending=g.list.innerHTML;g.ctx.window.AnyTourOfferList=g.owner;g.scripts[0].onload();await flush();assert.equal(g.renders.length,0,cancel+' rejects late render');assert.equal(g.list.innerHTML,pending);
 }
 const g=fixture();g.ctx.renderOfferList();vm.runInContext('offerView={id:2}',g.ctx);g.ctx.renderOfferList(true);g.ctx.window.AnyTourOfferList=g.owner;g.scripts[0].onload();await flush();assert.equal(g.renders.length,1);assert.equal(g.renders[0].view.id,2,'new view replaces queued old hotel');
 for(const failure of ['network','timeout','missing-owner']){
  const e=fixture();if(failure==='missing-owner')e.ctx.window.AnyTourOfferList={};e.ctx.renderOfferList(true);const outcome=vm.runInContext('loadOfferList()',e.ctx).catch(error=>error.message);if(failure==='network')e.scripts[0].onerror();else if(failure==='timeout')[...e.timers.values()][0]();else e.scripts[0].onload();await flush();
  assert.equal(await outcome,{network:'Offer list load failed',timeout:'Offer list load timed out','missing-owner':'Offer list owner missing'}[failure]);assert.equal(e.scripts[0].onload,null);assert.equal(e.scripts[0].onerror,null);
  assert.match(e.list.innerHTML,/role="alert"/);assert.match(e.list.innerHTML,/data-action="retry-offer-list"/);assert(e.scripts[0].removed);assert.equal(e.timers.size,0);
  e.ctx.document.head.dataset.offerListSrc='./offer-list-v1.js?v=later';e.ctx.renderOfferList(true);assert.equal(e.scripts.length,2,failure+' permits retry');assert.equal(e.scripts[1].src,'./offer-list-v1.js?v=exact','retry retains the captured offer-list asset');e.ctx.window.AnyTourOfferList=e.owner;e.scripts[1].onload();await flush();assert.equal(e.renders.length,1);
 }
 {
  const e=fixture();e.ctx.renderOfferList(true);const expired=e.scripts[0],lateLoad=expired.onload,lateError=expired.onerror;
  [...e.timers.values()][0]();await flush();e.ctx.renderOfferList(true);const retry=vm.runInContext('loadOfferList()',e.ctx);let settled=false;retry.then(()=>settled=true);
  lateLoad();lateError();await flush();assert.strictEqual(vm.runInContext('loadOfferList()',e.ctx),retry,'expired callbacks cannot clear the shared retry');assert.equal(e.scripts.length,2);assert.equal(e.timers.size,1);
  e.ctx.window.AnyTourOfferList=e.owner;lateLoad();await flush();assert.equal(settled,false,'expired load cannot resolve a newer request');assert.equal(e.renders.length,0);
  e.scripts[1].onload();assert.strictEqual(await retry,e.owner);await flush();assert.equal(e.renders.length,1);assert.equal(e.timers.size,0);assert.equal(e.scripts[1].onload,null);assert.equal(e.scripts[1].onerror,null);
  delete e.ctx.window.AnyTourOfferList;assert.strictEqual(vm.runInContext('loadOfferList()',e.ctx),retry,'successful load remains cached');assert.equal(e.scripts.length,2);
 }
 const h=fixture();vm.runInContext('offerListRestores.set(offerView,{filtersOpen:true,scroll:713})',h.ctx);h.ctx.renderOfferList();h.ctx.window.AnyTourOfferList=h.owner;h.scripts[0].onload();await flush();assert.equal(h.filters.open,true);assert.equal(h.body.scrollTop,713);assert.equal(h.remembers(),1,'history restoration happens after the actual renderer');
 const c=fixture();c.ctx.renderOfferList();c.modal.open=false;const pending=c.list.innerHTML;c.scripts[0].onerror();await flush();assert.equal(c.list.innerHTML,pending,'late failure cannot edit a closed modal');
 assert(app.includes("case 'retry-offer-list':if(modalType==='all-offers')renderOfferList(true);"),'real event dispatcher exposes retry');
 console.log('PASS cold offer-list: zero bootstrap fetch; shared/warm request; warm history inventory 2→1; history group keys 10500→500; cold history freshness retained; newest view; close/replace cancellation; network/timeout/missing-owner retries; deferred history restore');
})().catch(error=>{console.error(error);process.exitCode=1;});

// Keep the same-owner inventory oracle on this existing lean/full CI entrypoint.
require('./search3-visual-offer-list-inventory.cjs');
