// Cold download behavior at the real loader's DOM/history boundaries.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const app=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const start=app.indexOf('let offerListLoad=null,'),end=app.indexOf('function confirmTour(){',start);assert(start>=0&&end>start);
const openStart=app.indexOf('let offerView=null;'),openEnd=app.indexOf('const offerCountText=',openStart);assert(openStart>=0&&openEnd>openStart);
let source=app.slice(openStart,openEnd)+app.slice(start,end);const groupKeyOwner="const offerGroupKey=o=>encodeURIComponent(o.room+'|'+o.meal);";assert(source.includes(groupKeyOwner),'exact group-key owner');source=source.replace(groupKeyOwner,"const offerGroupKey=o=>(groupKeyCalls++,encodeURIComponent(o.room+'|'+o.meal));");
const context=source.match(/const api=owner.create\(\{([^}]+)\}\);/);assert(context,'real cold owner renderer context');
const dependencies=context[1].split(',').map(name=>name.trim());assert(dependencies.every(name=>/^[$A-Z_a-z][$\w]*$/.test(name)),'cold renderer context contains named live dependencies');
const flush=async()=>{for(let n=0;n<5;n++)await Promise.resolve();};
function fixture(rows=[{key:'one',day:'2026-10-10',flight:'regular',room:'SEA',meal:'BB',total:100000}]){
 const scripts=[],timers=new Map(),renders=[],list={innerHTML:'before'},modal={open:true,classList:{add:()=>{}}},filters={open:false},body={scrollTop:0};let nextTimer=0,remembers=0,hotelCalls=0;
 const ctx={window:{},Promise,Error,WeakMap,Number,Map,Object,groupKeyCalls:0,modalType:'all-offers',offerView:{id:1},hotels:[{id:1,name:'Hotel',offers:rows}],
  setTimeout:fn=>{const id=++nextTimer;timers.set(id,fn);return id;},clearTimeout:id=>timers.delete(id),
  document:{head:{dataset:{offerListSrc:'./offer-list-v1.js?v=exact'},append:script=>scripts.push(script)},createElement:()=>({removed:false,remove(){this.removed=true;}})},
  $:selector=>({'#all-offers-list':list,'#modal':modal,'.offer-filter-disclosure':filters,'#modal-body':body}[selector]),
  hotelOffers:()=>{hotelCalls++;return rows;},showModal:()=>{ctx.modalType='all-offers';modal.open=true;},boundedHistoryStrings:value=>Array.isArray(value)?value:[],offerMetaNote:()=>'',
  rememberUIRoute:()=>{remembers++;}
 };
 for(const name of dependencies)if(!(name in ctx))ctx[name]=()=>{};
 vm.createContext(ctx);vm.runInContext(source,ctx);vm.runInContext('offerView={id:1}',ctx);
 const owner={create:context=>({renderOfferList:(reset,precomputed)=>{const all=Array.isArray(precomputed)?precomputed:context.hotelOffers(context.hotels[0]);renders.push({view:context.offerView,reset,precomputed,all});list.innerHTML='rendered';}})};
 return {ctx,scripts,timers,renders,list,modal,filters,body,rows,owner,remembers:()=>remembers,hotelCalls:()=>hotelCalls};
}
(async()=>{
 const f=fixture();assert.equal(f.scripts.length,0,'bootstrap does not fetch the cold owner');
 f.ctx.renderOfferList(true);f.ctx.renderOfferList(false);assert.equal(f.scripts.length,1,'overlapping opens share one request');assert.match(f.list.innerHTML,/role="status"/);
 assert.equal(f.scripts[0].src,'./offer-list-v1.js?v=exact','use the entry supplied exact asset version');
 f.ctx.window.AnyTourOfferList=f.owner;f.scripts[0].onload();await flush();assert.equal(f.renders.length,1);assert.equal(f.renders[0].reset,false,'only the latest render request applies');assert.equal(vm.runInContext('offerListPage?.view===offerView',f.ctx),true,'latest full render retains its exact page API');assert(f.scripts[0].removed);assert.equal(f.timers.size,0);
 f.ctx.renderOfferList(true);assert.equal(f.renders.length,2,'warm rendering remains synchronous');assert.equal(f.scripts.length,1,'warm open has no additional download');
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
  const e=fixture();e.ctx.renderOfferList(true);if(failure==='network')e.scripts[0].onerror();else if(failure==='timeout')[...e.timers.values()][0]();else e.scripts[0].onload();await flush();
  assert.match(e.list.innerHTML,/role="alert"/);assert.match(e.list.innerHTML,/data-action="retry-offer-list"/);assert(e.scripts[0].removed);assert.equal(e.timers.size,0);
  e.ctx.renderOfferList(true);assert.equal(e.scripts.length,2,failure+' permits retry');e.ctx.window.AnyTourOfferList=e.owner;e.scripts[1].onload();await flush();assert.equal(e.renders.length,1);
 }
 const h=fixture();vm.runInContext('offerListRestores.set(offerView,{filtersOpen:true,scroll:713})',h.ctx);h.ctx.renderOfferList();h.ctx.window.AnyTourOfferList=h.owner;h.scripts[0].onload();await flush();assert.equal(h.filters.open,true);assert.equal(h.body.scrollTop,713);assert.equal(h.remembers(),1,'history restoration happens after the actual renderer');
 const c=fixture();c.ctx.renderOfferList();c.modal.open=false;const pending=c.list.innerHTML;c.scripts[0].onerror();await flush();assert.equal(c.list.innerHTML,pending,'late failure cannot edit a closed modal');
 assert(app.includes("case 'retry-offer-list':if(modalType==='all-offers')renderOfferList(true);"),'real event dispatcher exposes retry');
 console.log('PASS cold offer-list: zero bootstrap fetch; shared/warm request; warm history inventory 2→1; history group keys 10500→500; cold history freshness retained; newest view; close/replace cancellation; network/timeout/missing-owner retries; deferred history restore');
})().catch(error=>{console.error(error);process.exitCode=1;});

// Keep the same-owner inventory oracle on this existing lean/full CI entrypoint.
require('./search3-visual-offer-list-inventory.cjs');
