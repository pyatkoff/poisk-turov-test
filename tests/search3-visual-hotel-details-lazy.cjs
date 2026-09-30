'use strict';
const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm');
const app=fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8'),start=app.indexOf('// The hotel shell enters history immediately;'),end=app.indexOf("let renderedCardLimit=24",start);assert(start>=0&&end>start);
const source=app.slice(start,end),names=source.match(/\.create\(\{([^}]+)\}\)/)[1].split(','),flush=async()=>{for(let n=0;n<5;n++)await Promise.resolve();};
function fixture(){
 const scripts=[],timers=new Map(),renders=[],load={innerHTML:'old'},body={scrollTop:0},modal={open:true,classList:{add(){}}},nav={dataset:{hotelId:'1'}},rooms=[{dataset:{room:'Standard'},querySelector:()=>more}],more={open:false};let timer=0,ready=false,remembers=0;
 const ctx={window:{},Promise,Error,Number,Array,modalType:'hotel-details',hotels:[{id:1,name:'One',resort:'Place',stars:5},{id:2,name:'Two',resort:'Place',stars:4}],
  document:{head:{dataset:{hotelDetailsSrc:'./hotel-details-v1.js?v=exact'},append:s=>scripts.push(s)},createElement:()=>({remove(){this.removed=true;}})},
  setTimeout:fn=>{timers.set(++timer,fn);return timer;},clearTimeout:id=>timers.delete(id),
  $:s=>({'#hotel-details-load':load,'#modal':modal,'#modal-body':body,'.hotel-section-nav':nav,'#hotel-room-count':ready?{}:null}[s]),$$:()=>rooms,
  showModal:(type,title,kicker,html)=>{ctx.modalType=type;nav.dataset.hotelId=String(ctx.hotels.find(h=>h.name===title).id);modal.open=true;ready=false;},
  hotelOffers:()=>[{meal:'BB'}],rememberUIRoute:()=>remembers++
 };for(const name of names)if(!(name in ctx))ctx[name]=()=>{};
 vm.createContext(ctx);vm.runInContext(source,ctx);
 const owner={create:()=>({openHotelDetails:id=>{renders.push(['mount',id]);ready=true;},renderHotelRooms:(...args)=>renders.push(['rooms',...args])})};
 return {ctx,scripts,timers,renders,load,modal,body,more,owner,remembers:()=>remembers};
}
(async()=>{
 const f=fixture();assert.equal(f.scripts.length,0);f.ctx.openHotelDetails(1);f.ctx.openHotelDetails(2);assert.equal(f.scripts.length,1);assert.match(f.load.innerHTML,/role="status"/);assert.equal(f.scripts[0].src,'./hotel-details-v1.js?v=exact');
 f.ctx.window.AnyTourHotelDetails=f.owner;f.scripts[0].onload();await flush();assert.deepEqual(f.renders,[['mount',2]],'newest hotel only');assert(f.scripts[0].removed);assert.equal(f.timers.size,0);
 f.ctx.openHotelDetails(1);assert.deepEqual(f.renders.at(-1),['mount',1]);assert.equal(f.scripts.length,1,'warm synchronous mount without new download');
 for(const cancel of ['close','replace','missing-hotel']){const g=fixture();g.ctx.openHotelDetails(1);if(cancel==='close')g.modal.open=false;else if(cancel==='replace')g.ctx.modalType='gallery';else g.ctx.hotels=[];g.ctx.window.AnyTourHotelDetails=g.owner;g.scripts[0].onload();await flush();assert.equal(g.renders.length,0,cancel+' prevents stale rendering');}
 for(const failure of ['network','timeout','missing-owner']){const g=fixture();g.ctx.openHotelDetails(1);if(failure==='network')g.scripts[0].onerror();else if(failure==='timeout')[...g.timers.values()][0]();else g.scripts[0].onload();await flush();assert.match(g.load.innerHTML,/role="alert"/);assert.match(g.load.innerHTML,/retry-hotel-details/);assert.equal(g.timers.size,0);g.ctx.renderHotelDetails();assert.equal(g.scripts.length,2);g.ctx.window.AnyTourHotelDetails=g.owner;g.scripts[1].onload();await flush();assert.deepEqual(g.renders,[['mount',1]]);}
 const h=fixture();h.ctx.openHotelDetails(1,{meal:'BB',rooms:['Standard'],more:['Standard'],scroll:713});h.ctx.renderHotelRooms(1,'BB');assert.equal(h.renders.length,0,'room refresh waits for mounted DOM');h.ctx.window.AnyTourHotelDetails=h.owner;h.scripts[0].onload();await flush();assert.deepEqual(h.renders,[['mount',1],['rooms',1,'BB',['Standard']]]);assert.equal(h.more.open,true);assert.equal(h.body.scrollTop,713);assert.equal(h.remembers(),1);
 const c=fixture();c.ctx.openHotelDetails(1);c.modal.open=false;const pending=c.load.innerHTML;c.scripts[0].onerror();await flush();assert.equal(c.load.innerHTML,pending);
 assert(app.includes("case 'retry-hotel-details':if(modalType==='hotel-details')renderHotelDetails();"));
 console.log('PASS cold hotel-details: initial0/shared/warm/newest/close/replace/failure/retry/history/room refresh guards');
})().catch(e=>{console.error(e);process.exitCode=1;});
