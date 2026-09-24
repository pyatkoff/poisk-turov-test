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
console.log('SEARCH3_PROTOTYPE_UNION_RECEIPT_OK');
