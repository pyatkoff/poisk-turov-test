// Cold download behavior at the real loader's DOM/history boundaries.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const app=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const start=app.indexOf('let offerListLoad=null,'),end=app.indexOf('let verifiedOffer=null;',start);assert(start>=0&&end>start);
const source=app.slice(start,end),dependencies=source.match(/owner.create\(\{([^}]+),setComparisonQuotes:/)[1].split(',');
const flush=async()=>{for(let n=0;n<5;n++)await Promise.resolve();};
function fixture(){
 const scripts=[],timers=new Map(),renders=[],list={innerHTML:'before'},modal={open:true},filters={open:false},body={scrollTop:0};let nextTimer=0,remembers=0;
 const ctx={window:{},Promise,Error,WeakMap,Number,modalType:'all-offers',offerView:{id:1},comparisonQuotes:[],
  setTimeout:fn=>{const id=++nextTimer;timers.set(id,fn);return id;},clearTimeout:id=>timers.delete(id),
  document:{head:{dataset:{offerListSrc:'./offer-list-v1.js?v=exact'},append:script=>scripts.push(script)},createElement:()=>({removed:false,remove(){this.removed=true;}})},
  $:selector=>({'#all-offers-list':list,'#modal':modal,'.offer-filter-disclosure':filters,'#modal-body':body}[selector]),
  rememberUIRoute:()=>{remembers++;}
 };
 for(const name of dependencies)if(!(name in ctx))ctx[name]=()=>{};
 vm.createContext(ctx);vm.runInContext(source,ctx);
 const owner={create:context=>({renderOfferList:reset=>{renders.push({view:context.offerView,reset});list.innerHTML='rendered';}})};
 return {ctx,scripts,timers,renders,list,modal,filters,body,owner,remembers:()=>remembers};
}
(async()=>{
 const f=fixture();assert.equal(f.scripts.length,0,'bootstrap does not fetch the cold owner');
 f.ctx.renderOfferList(true);f.ctx.renderOfferList(false);assert.equal(f.scripts.length,1,'overlapping opens share one request');assert.match(f.list.innerHTML,/role="status"/);
 assert.equal(f.scripts[0].src,'./offer-list-v1.js?v=exact','use the entry supplied exact asset version');
 f.ctx.window.AnyTourOfferList=f.owner;f.scripts[0].onload();await flush();assert.equal(f.renders.length,1);assert.equal(f.renders[0].reset,false,'only the latest render request applies');assert(f.scripts[0].removed);assert.equal(f.timers.size,0);
 f.ctx.renderOfferList(true);assert.equal(f.renders.length,2,'warm rendering remains synchronous');assert.equal(f.scripts.length,1,'warm open has no additional download');
 for(const cancel of ['close','replace','view']){
  const g=fixture();g.ctx.renderOfferList();if(cancel==='close')g.modal.open=false;else if(cancel==='replace')g.ctx.modalType='gallery';else g.ctx.offerView={id:2};
  const pending=g.list.innerHTML;g.ctx.window.AnyTourOfferList=g.owner;g.scripts[0].onload();await flush();assert.equal(g.renders.length,0,cancel+' rejects late render');assert.equal(g.list.innerHTML,pending);
 }
 const g=fixture();g.ctx.renderOfferList();g.ctx.offerView={id:2};g.ctx.renderOfferList(true);g.ctx.window.AnyTourOfferList=g.owner;g.scripts[0].onload();await flush();assert.equal(g.renders.length,1);assert.equal(g.renders[0].view.id,2,'new view replaces queued old hotel');
 for(const failure of ['network','timeout','missing-owner']){
  const e=fixture();e.ctx.renderOfferList(true);if(failure==='network')e.scripts[0].onerror();else if(failure==='timeout')[...e.timers.values()][0]();else e.scripts[0].onload();await flush();
  assert.match(e.list.innerHTML,/role="alert"/);assert.match(e.list.innerHTML,/data-action="retry-offer-list"/);assert(e.scripts[0].removed);assert.equal(e.timers.size,0);
  e.ctx.renderOfferList(true);assert.equal(e.scripts.length,2,failure+' permits retry');e.ctx.window.AnyTourOfferList=e.owner;e.scripts[1].onload();await flush();assert.equal(e.renders.length,1);
 }
 const h=fixture();vm.runInContext('offerListRestores.set(offerView,{filtersOpen:true,scroll:713})',h.ctx);h.ctx.renderOfferList();h.ctx.window.AnyTourOfferList=h.owner;h.scripts[0].onload();await flush();assert.equal(h.filters.open,true);assert.equal(h.body.scrollTop,713);assert.equal(h.remembers(),1,'history restoration happens after the actual renderer');
 const c=fixture();c.ctx.renderOfferList();c.modal.open=false;const pending=c.list.innerHTML;c.scripts[0].onerror();await flush();assert.equal(c.list.innerHTML,pending,'late failure cannot edit a closed modal');
 assert(app.includes("case 'retry-offer-list':if(modalType==='all-offers')renderOfferList(true);"),'real event dispatcher exposes retry');
 console.log('PASS cold offer-list: zero bootstrap fetch; shared/warm request; newest view; close/replace cancellation; network/timeout/missing-owner retries; deferred history restore');
})().catch(error=>{console.error(error);process.exitCode=1;});

// Keep the same-owner inventory oracle on this existing lean/full CI entrypoint.
require('./search3-visual-offer-list-inventory.cjs');
