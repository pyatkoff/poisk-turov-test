'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

const source=fs.readFileSync(process.argv[2]||'v2/prototype-search/search-lifecycle-v1.js','utf8');
const flush=async()=>{for(let i=0;i<6;i++)await Promise.resolve();};

function harness({hidden=true,covered=false}={}){
  const submitListeners=[],eventListeners={click:[],change:[]},searchCalls=[],scopeCalls=[];
  let requests=0,currentKey='trip-a';
  const form={
    hidden,
    addEventListener(type,listener){if(type==='submit')submitListeners.push(listener);},
    requestSubmit(){requests++;for(const listener of submitListeners)listener({preventDefault(){}});}
  };
  const events={addEventListener(type,listener){if(eventListeners[type])eventListeners[type].push(listener);}};
  const data={
    currentSupplierScope:{kind:'previous'},
    search(search,callback,hotelIds,filters){searchCalls.push({search,callback,hotelIds,filters});return Promise.resolve();},
    supplierScope(filters){scopeCalls.push(filters);return {kind:'next'};},
    supplierScopeCovered(){return covered;}
  };
  const context={window:{},Object,Promise,console,queueMicrotask};
  vm.createContext(context);vm.runInContext(source,context,{filename:'search-lifecycle-v1.js'});
  const lifecycle=context.window.AnyTourPrototypeSearchLifecycleV1.create({
    form,data,events,
    supplierFilters:()=>({stars:[5]}),
    prepare:()=>({search:{origin:'Москва',country:'4'},filters:{stars:[5]},hotelIds:[101],response:{key:currentKey,pending:true}}),
    currentKey:()=>currentKey,
    commit:()=>({})
  });
  lifecycle.bind();
  return {lifecycle,form,data,submitListeners,eventListeners,searchCalls,scopeCalls,
    get requests(){return requests;},set currentKey(value){currentKey=value;}};
}

const action=name=>({target:{closest(){return {dataset:{action:name}};}}});

test('public invalidation cancels a queued canonical auto-submit before supplier search',async()=>{
  const h=harness();
  h.lifecycle.requestSubmit();
  h.lifecycle.invalidate();
  await flush();
  assert.equal(h.requests,0);
  assert.equal(h.searchCalls.length,0);
});

test('Stop cancels scope inspection queued earlier in the same turn',async()=>{
  const h=harness();
  h.eventListeners.change[0]({});
  h.eventListeners.click[0](action('stop-search'));
  await flush();
  assert.equal(h.scopeCalls.length,0,'stale scope must not even be recomputed after Stop');
  assert.equal(h.requests,0);
  assert.equal(h.searchCalls.length,0);
});

test('Edit cancels an already queued canonical auto-submit',async()=>{
  const h=harness();
  h.lifecycle.requestSubmit();
  h.eventListeners.click[0](action('edit-search'));
  await flush();
  assert.equal(h.requests,0);
  assert.equal(h.searchCalls.length,0);
});

test('a fresh generation can schedule immediately while stale queued work drains',async()=>{
  const h=harness();
  h.lifecycle.requestSubmit();
  h.lifecycle.invalidate();
  h.lifecycle.requestSubmit();
  await flush();
  assert.equal(h.requests,1,'stale queued work must not clear the fresh generation token');
  assert.equal(h.searchCalls.length,1);
});

test('manual submit makes an older queued auto-submit stale instead of launching twice',async()=>{
  const h=harness();
  h.lifecycle.requestSubmit();
  h.submitListeners[0]({preventDefault(){}});
  assert.equal(h.searchCalls.length,1,'manual submit starts the canonical search immediately');
  await flush();
  assert.equal(h.requests,0,'older queued auto-submit is discarded after generation advances');
  assert.equal(h.searchCalls.length,1,'supplier search is not duplicated');
});
