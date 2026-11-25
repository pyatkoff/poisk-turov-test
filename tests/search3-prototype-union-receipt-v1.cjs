'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'..','v2','prototype-search','search-lifecycle-v1.js'),'utf8');

function setup(){
 const form={hidden:false,addEventListener(){},requestSubmit(){}},events={addEventListener(){}};
 let callback=null,key='trip-1',response=null;
 const data={
  search(_search,receive){callback=receive;},
  resumeCached(_search,receive){callback=receive;}
 };
 const win={},sandbox={window:win,queueMicrotask,structuredClone,console};
 vm.createContext(sandbox);vm.runInContext(source,sandbox,{filename:'search-lifecycle-v1.js'});
 const lifecycle=win.AnyTourPrototypeSearchLifecycleV1.create({
  form,data,events,currentKey:()=>key,
  prepare(){response={key,pending:false,phase:'complete'};return {search:{},filters:{},hotelIds:[],response};}
 });
 return {lifecycle,get response(){return response;},emit:event=>callback(event),setKey:value=>key=value};
}
const valid=()=>({hotels:3,offers:5,hotelsByProvider:{tourvisor:2,anex:1},offersByProvider:{tourvisor:3,anex:2},providerSets:{tourvisor:2,anex:1}});

{
 const h=setup();assert.equal(h.lifecycle.run(),true);
 h.emit({type:'complete',canContinue:false,sources:{},union:valid()});
 assert.deepEqual(JSON.parse(JSON.stringify(h.response.union)),valid(),'completed first-union receipt survives lifecycle reduction');
}
{
 const h=setup();h.lifecycle.run();h.emit({type:'complete',sources:{},union:valid()});assert.ok(h.response.union);
 h.emit({type:'loading'});assert.equal(h.response.union,null,'new loading phase clears stale completed receipt');
}
for(const bad of [
 null,[],{...valid(),offers:-1},{...valid(),offers:6},{...valid(),hotelsByProvider:{tourvisor:4}},
 {...valid(),providerSets:{tourvisor:2}},{...valid(),offersByProvider:{tourvisor:3,anex:'2'}}
]){
 const h=setup();h.lifecycle.run();h.emit({type:'complete',sources:{},union:bad});
 assert.equal(h.response.union,null,'malformed union receipt is rejected');
}

{
 const dataSource=fs.readFileSync(path.join(__dirname,'..','v2','prototype-search','data.js'),'utf8');
 const first=dataSource.indexOf('  function offerTerms('),last=dataSource.indexOf('  function publish()',first);
 const helpers=['text','amount','date','plus'].map(name=>dataSource.split('\n').find(line=>line.startsWith('  const '+name+' =')));
 assert(first>=0&&last>first&&helpers.every(Boolean),'load the actual offer and union projection');
 const base={id:'valid',price:{value:'123400.50'},date:'10.12.2026',nights:'7',meal:'AI',roomType:'Standard',operator:'Fixture'};
 const sparse=Array(3),inherited=Object.create(Array.prototype);
 inherited[1]={...base,id:'held',provider:'ANDROMEDA'};
 Object.setPrototypeOf(sparse,inherited);
 const rows=[{id:1,tours:[base,{...base,id:'a',provider:'ANEX'},{...base,id:'bad-price',price:0}]},
  {id:2,tours:sparse},{id:3,tours:[{...base,date:'unknown'},{...base,nights:2.5}]},{id:4}];
 const expected={hotels:2,offers:3,hotelsByProvider:{anex:1,tourvisor:1,andromeda:1},
  offersByProvider:{tourvisor:1,anex:1,andromeda:1},providerSets:{'anex+tourvisor':1,andromeda:1}};
 let reads=0,displayPreparations=0;
 const ctx=vm.createContext({
  owner:{read(inventory){reads++;return inventory;}},raw:rows,context:{adults:2,ages:[0,8],origin:'Москва'},
  hotel(h){displayPreparations++;return {id:h.id};},mealIndexes(){displayPreparations++;return {};},
  mealPlan(){return null;},indexedMeal(){return '';},localStayName(){displayPreparations++;return '';},operator:value=>value,
  structuredClone(value){displayPreparations++;return structuredClone(value);}
 });
 vm.runInContext(helpers.join('\n')+'\n'+dataSource.slice(first,last),ctx);
 const plain=x=>JSON.parse(JSON.stringify(x));
 assert.deepEqual(plain(ctx.canonicalUnion()),expected);
 assert.equal(reads,1);
 assert.equal(displayPreparations,0,'receipt counts do not build hotel details, stay labels or search copies');
 assert.deepEqual(plain(ctx.project(rows,ctx.context).flatMap(h=>h.offers.map(o=>[o.key,o.total,o.returnDay]))),[
  ['tourvisor%3Avalid',123400.5,'2026-12-17'],['anex%3Aa',123400.5,'2026-12-17'],['andromeda%3Aheld',123400.5,'2026-12-17']
 ]);
 ctx.raw=[{id:5,tours:[{...base,id:'new',provider:'ANEX'}]}];
 assert.deepEqual(plain(ctx.canonicalUnion()),{hotels:1,offers:1,hotelsByProvider:{anex:1},offersByProvider:{anex:1},providerSets:{anex:1}});
 assert.equal(reads,2,'the next inventory is read when requested');
 for(const [invalid,error] of [[{date:'2026-13-10'},'RangeError'],[{id:'\ud800'},'URIError']]){
  ctx.raw=[{id:6,tours:[{...base,...invalid}]}];
  assert.throws(()=>ctx.canonicalUnion(),{name:error},'receipt retains the offer validation error');
  assert.throws(()=>ctx.project(ctx.raw,ctx.context),{name:error},'display retains the same offer validation error');
 }
 ctx.context=null;
 assert.deepEqual(plain(ctx.canonicalUnion()),{hotels:0,offers:0,hotelsByProvider:{},offersByProvider:{},providerSets:{}});
 assert.equal(reads,4,'absence of search context avoids reading canonical inventory');
}
console.log('SEARCH3_PROTOTYPE_UNION_RECEIPT_OK');
