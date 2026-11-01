'use strict';
// Real UI/lifecycle owners with deferred fictional responses; no browser or HTTP.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const app=fs.readFileSync(path.join(__dirname,'../v2/visual-search/app.js'),'utf8');
const slice=(start,end)=>{
 const from=app.indexOf(start),to=app.indexOf(end,from);
 assert(from>=0&&to>from,start);return app.slice(from,to);
};
const deferred=()=>{let resolve;const promise=new Promise(done=>resolve=done);return {promise,resolve};};

async function run(){
 const search={origin:'Москва',country:'100',from:'2026-12-20',to:'2026-12-26',minNights:7,maxNights:7,adults:2,ages:[1]};
 const group={key:'anex-group',hotelId:1,provider:'anex',flight:'charter',room:'Standard Room',meal:'AI',day:'2026-12-24',returnDay:'2026-12-31',nights:7,adults:2,ages:[1],total:107581,search,
  raw:{anexKind:'group_minimum',anexSessionCurrent:true,infantCharge:{status:'unknown',amount:null,includedInSearchPrice:null}}};
 const concrete={...group,key:'anex-concrete',raw:{...group.raw,anexKind:'concrete'}};
 const alternative={...concrete,key:'anex-alternative',raw:{...concrete.raw},total:110000};
 const hotel={id:1,offers:[group]},current=deferred(),views=new Map(),calls={expand:0,current:0,quote:0,results:0};
 const nodes={'#modal':{open:true},'.hotel-section-nav':{dataset:{hotelId:'1'}}};
 let receive;
 const c={console,Number,Set,Map,Promise,window:{},queueMicrotask,state:{search,favorites:[]},hotels:[hotel],operators:[],mealNames:{},
  searchResponse:{key:JSON.stringify(search),phase:'loading',pending:true,providers:{anex:'complete',tourvisor:'loading'}},
  selectedOffer:{...group},hotelOfferDraft:{hotelId:1,key:group.key},hotelDetailsView:{id:1,checkKey:group.key},modalType:'hotel-details',selectionGeneration:0,
  $:selector=>nodes[selector],searchKey:JSON.stringify,selectedTourHotel:o=>c.hotels.find(h=>h.id===o.hotelId),
  sameSelectedTourConditions:(a,b)=>['room','meal','day','returnDay','nights','adults'].every(key=>a[key]===b[key])&&JSON.stringify(a.ages)===JSON.stringify(b.ages),
  needsRefresh:()=>true,terminalOfferQuote:()=>false,retainedProviderView:o=>views.get(o.key),restoreProviderView:()=>false,
  rememberProviderView:(o,type,result)=>views.set(o.key,{offer:o,type,result}),cancelHotelOfferCheck(){},syncHotelOfferSelection(){},rememberUIRoute(){},
  renderResults(){},renderOfferProgress(){},refreshOpenHotelRooms(){},refreshOpenOfferList(){},positionHotelRooms(){},toast(){},
  offerQuoteFailure:(o,error)=>({...o,loading:false,quoteError:error.message}),
  loadAnexPackageQuote:async()=>{calls.quote++;assert.equal(c.selectedOffer.key,concrete.key);assert.deepEqual(c.selectedOffer.search,search);},
  data:{live:true,search:(_search,callback)=>{receive=callback;return Promise.resolve();},
   expandAnexGroup:async()=>{calls.expand++;return {offers:[concrete,alternative]};},
   verifyAnexConcrete:()=>{calls.current++;return current.promise;}},
 };
 vm.createContext(c);
 vm.runInContext(fs.readFileSync(path.join(__dirname,'../v2/prototype-search/search-lifecycle-v1.js'),'utf8'),c);
 const owners=[slice('function offerFromKey(','function flightAllowanceText('),
  slice('function hotelOfferCheckActive(','function contactFlightActive('),slice('function isCurrentOfferRequest(','function terminalOfferQuote('),
  slice('function continueHotelOffer(','function acceptSelectedOfferPrice('),slice('async function refreshLiveHotel(','function refreshHotel('),
  slice('function terminalizeSearchForVerification(','function stopSearch('),slice('function mergeSearchResults(','function commitSearchDraft('),
  app.split('\n').find(line=>line.startsWith('const unknownInfantCharge='))].join('\n');
 vm.runInContext(owners,c);
 c.searchLifecycle=c.window.AnyTourPrototypeSearchLifecycleV1.create({form:{addEventListener(){}},data:c.data,
  prepare:()=>({search,response:c.searchResponse}),currentKey:()=>JSON.stringify(search),onResults:event=>{calls.results++;c.mergeSearchResults(event);}});
 assert.equal(c.searchLifecycle.run(),true);
 await c.refreshLiveHotel(1);
 assert.equal(calls.expand,1);
 assert.equal(c.searchResponse.phase,'cancelled');
 c.b={dataset:{key:concrete.key}};
 vm.runInContext("function select(){switch('select-hotel-offer'){"+slice("case 'select-hotel-offer':", "case 'continue-hotel-offer':")+"}};select();",c);
 assert.equal(calls.current,1,'ordinary concrete selection starts one fictional current check');
 receive({type:'results',hotels:[{id:1,offers:[group]}]});
 assert.equal(c.offerFromKey(concrete.key)?.key,concrete.key,'late original inventory cannot remove the selected expanded tour');
 assert.equal(c.hotelOfferDraft.key,concrete.key);
 for(const key of ['room','meal','day','returnDay','nights','adults','total'])assert.equal(c.selectedOffer[key],concrete[key],key);
 assert.deepEqual(c.selectedOffer.ages,[1]);assert.deepEqual(c.selectedOffer.search,search);
 assert.strictEqual(c.selectedOffer.raw,concrete.raw,'exact current-session provider identity stays intact');
 assert.equal(calls.results,0,'verification freezes only the obsolete UI subscription');
 current.resolve({state:'current'});
 await new Promise(resolve=>setImmediate(resolve));
 assert.deepEqual(calls,{expand:1,current:1,quote:1,results:0},'late inventory cannot suppress or duplicate the fictional infant fullquote continuation');
 console.log('PASS ANEX expanded selection survives late results while current is pending; exact tour/2A+[1]/identity retained; fictional quote once; supplier HTTP 0');
}
module.exports=run;
if(require.main===module)run().catch(error=>{console.error(error);process.exitCode=1;});
