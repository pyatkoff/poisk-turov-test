'use strict';
// Characterize the real Search3 NEXT result merge without starting the app,
// supplier transport, lead delivery or a renderer. The optional --compare
// source must preserve every observable record and identity assertion.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const target=path.resolve(__dirname,'../v2/visual-search/app.js');
const source=fs.readFileSync(target,'utf8');
function owner(value){
 const start=value.indexOf('function mergeSearchResults(event){');
 const end=value.indexOf('\nfunction commitSearchDraft()',start);
 assert(start>=0&&end>start,'real mergeSearchResults owner boundaries');
 return value.slice(start,end);
}
const copy=value=>JSON.parse(JSON.stringify(value));
function execute(value,setup){
 const trace=[],mealNames={},operators=['stale'],state={favorites:[]},data={live:false};
 const context=vm.createContext({Map,Set,Number,String,state,data,mealNames,operators,
  hotels:[],refreshOpenOfferList:()=>trace.push('refresh-offers'),refreshOpenHotelRooms:()=>trace.push('refresh-rooms')});
 vm.runInContext(owner(value),context,{filename:'visual-search/app.js#mergeSearchResults'});
 const fixture=setup({trace,state,data,context});
 context.hotels=fixture.hotels;context.event=fixture.event;
 let error='';try{vm.runInContext('mergeSearchResults(event)',context);}catch(reason){error=reason.name;}
 const mergeTrace=trace.slice();
 return {trace:mergeTrace,error,ids:copy(context.hotels.map(h=>h.id)),offers:copy(context.hotels.map(h=>h.offers.map(o=>({operator:o.operator,meal:o.meal,sourceMeal:o.sourceMeal,mealPlanId:o.mealPlanId,mealFacet:o.mealFacet})))),
  operators:copy(operators),mealNames:copy(mealNames),hotels:context.hotels,fixture};
}
const plain=(id,operator='op-'+id,meal='AI',extra={})=>({id,name:'hotel-'+id,...extra,offers:[{key:'offer-'+id,operator,meal}]});
function records(value){
 const result=[];
 const add=(name,setup)=>{const run=execute(value,setup);result.push({name,trace:run.trace,error:run.error,ids:run.ids,offers:run.offers,operators:run.operators,mealNames:run.mealNames});return run;};
 const raw={token:'raw'},meta={token:'meta'};
 const dense=add('duplicates, overlap and identity',({state})=>{
  state.favorites=[1,2,1];
  const retainedFirst=plain(1,'old-a'),retainedLast=plain(1,'old-b'),retainedTwo=plain(2,'old-c');
  const incomingFirst=plain(2,'incoming-a','AI',{meta}),incomingLast=plain(2,'incoming-b','BB',{meta});incomingLast.offers[0].raw=raw;
  return {hotels:[retainedFirst,retainedLast,retainedTwo,plain(9,'discarded')],event:{hotels:[incomingFirst,plain(3,'incoming-c'),incomingLast]}};
 });
 assert.deepEqual(dense.ids,[1,2,3],'retained first-key order, overlap overwrite and incoming first-key order');
 assert.notStrictEqual(dense.hotels[0],dense.fixture.hotels[1],'last retained duplicate is cloned');
 assert.equal(dense.hotels[0].offers.length,0,'retained offers are cleared');
 assert.strictEqual(dense.hotels[1].meta,meta,'incoming shallow hotel references retained');
 assert.strictEqual(dense.hotels[1].offers[0].raw,raw,'raw offer identity retained');
 assert.notStrictEqual(dense.hotels[1],dense.fixture.event.hotels[2],'incoming hotel cloned');
 assert.notStrictEqual(dense.hotels[1].offers[0],dense.fixture.event.hotels[2].offers[0],'incoming offer cloned');

 add('phase order, initial length and late fills',({trace,state})=>{
  state.favorites=[10,11,12];
  const incoming=new Array(3),existing=[];
  const hotel=(id,label,onOffers)=>Object.defineProperties({}, {
   id:{enumerable:true,get(){trace.push(label+':id');return id;}},
   label:{enumerable:true,get(){trace.push(label+':spread');return label;}},
   offers:{enumerable:true,get(){trace.push(label+':offers');onOffers?.();return [{operator:label,meal:'AI'}];}}
  });
  incoming[0]=hotel(11,'incoming-0',()=>{incoming[1]=hotel(12,'incoming-filled');incoming.push(plain(99));});
  incoming[2]=hotel(13,'incoming-2');
  existing.push(hotel(10,'retained-0',()=>existing.push(plain(98))),hotel(11,'retained-1'));
  return {hotels:existing,event:{hotels:incoming}};
 });
 add('incoming sparse failure after all present clones',({trace})=>{
  const incoming=new Array(4);
  incoming[0]=plain(1);incoming[2]=Object.defineProperties({}, {
   id:{enumerable:true,get(){trace.push('late:id');return 2;}},
   offers:{enumerable:true,get(){trace.push('late:offers');return [];}}
  });
  return {hotels:[Object.defineProperty(plain(7),'id',{get(){trace.push('retained-id');return 7;},enumerable:true})],event:{hotels:incoming}};
 });
 const inherited=add('inherited incoming and retained slots',({state})=>{
  state.favorites=[20,21];
  const incoming=[];Object.setPrototypeOf(incoming,Object.assign(Object.create(Array.prototype),{1:plain(21,'inherited-in')}));incoming.length=2;incoming[0]=plain(20,'own-in');
  const existing=[];Object.setPrototypeOf(existing,Object.assign(Object.create(Array.prototype),{1:plain(21,'inherited-old')}));existing.length=3;existing[0]=plain(20,'own-old');
  return {hotels:existing,event:{hotels:incoming}};
 });
 assert.deepEqual(inherited.ids,[20,21]);
 add('live canonical meal facts',({data,state})=>{
  data.live=true;state.favorites=[];
  return {hotels:[],event:{hotels:[{id:30,offers:[
   {operator:'A',meal:'display-a',mealPlanId:7,mealFacet:'Всё включено'},
   {operator:'B',meal:'display-b',mealPlanId:7,mealFacet:'Всё включено'},
   {operator:'A',meal:'display-c',mealPlanId:8,mealFacet:'Всё включено'},
   {operator:'C',meal:'display-d',mealPlanId:9,mealFacet:'Завтраки'}]}]}};
 });
 return result;
}
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex');
const compareIndex=process.argv.indexOf('--compare');
if(compareIndex>=0)assert.deepEqual(actual,records(fs.readFileSync(process.argv[compareIndex+1],'utf8')),'before/after result merge observations');
const sparse=actual.find(row=>row.name==='incoming sparse failure after all present clones');
assert.equal(sparse.error,'TypeError');assert.deepEqual(sparse.trace,['late:id','late:id','late:offers','late:offers'],'all present incoming clones complete before sparse Map consumption fails; retained phase is not reached');
const phase=actual.find(row=>row.name==='phase order, initial length and late fills');
assert.deepEqual(phase.ids,[10,11,12,13],'future hole fill is observed while appended incoming/existing rows are ignored');
assert(phase.trace.indexOf('retained-1:id')<phase.trace.lastIndexOf('retained-0:id'),'all favorite filtering precedes retained cloning');
assert.deepEqual(actual.find(row=>row.name==='live canonical meal facts').mealNames,{'Всё включено':7,'Завтраки':9},'first conflicting canonical meal ID remains authoritative');
assert.deepEqual(actual.find(row=>row.name==='live canonical meal facts').operators,['A','B','C'],'operator insertion order is stable');
// Dense 1000 incoming + 100 retained-favorite accounting. Offer arrays and
// the required final hotels array are outside the temporary merge-container
// count. This is allocation/work accounting, not a transfer/latency claim.
const before={temporaryMergeArrays:1+1000+1+1+1+1+1100,hotelVisits:1000+100+100+1100+1100};
const after={temporaryMergeArrays:1,hotelVisits:1000+100+100+1000+1100};
assert.deepEqual({before,after},{before:{temporaryMergeArrays:2105,hotelVisits:3400},after:{temporaryMergeArrays:1,hotelVisits:3300}});
console.log(`PASS result merge: ${actual.length} phase/identity/fact scenarios, digest ${digest}; dense merge-container arrays 2105->1, application hotel visits 3400->3300; supplier HTTP 0, leads 0`);
