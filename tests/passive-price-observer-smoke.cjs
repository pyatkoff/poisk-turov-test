'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(__dirname+'/../v2/passive-price-observer-v1.js','utf8');
const ok=()=>({ok:true,json:async()=>({ok:true,persisted:true})});
const settle=async()=>{for(let i=0;i<10;i++)await Promise.resolve();};
function fixture(responses=[]){
  const listeners={},calls=[],timers=[];
  const lifecycle={searchId:101,snapshot:{departureId:1,countryId:4,adults:2,childs:[7,3]}};
  const form={from:'1',country:'4',count_people:'2','child_age[]':['7','3']};
  const window={V2SearchLifecycle:lifecycle,addEventListener:(name,fn)=>{listeners[name]=fn;}};
  const box={window,document:{getElementById:()=>form},FormData:class{get(k){return form[k];}getAll(k){return form[k]||[];}},console:{warn(){}},setTimeout:fn=>{timers.push(fn);return timers.length;},fetch:async(url,options)=>{
    calls.push({url,payload:JSON.parse(options.body),options});
    const response=responses.length?responses.shift():ok();
    if(response instanceof Error)throw response;
    return typeof response==='function'?response():response;
  }};
  vm.runInNewContext(source,box);
  return {window,lifecycle,form,calls,timers,emit:(name,id=101)=>listeners['v2:search-'+name]({detail:{searchId:id}})};
}
(async()=>{
  const f=fixture();f.emit('started');
  f.lifecycle.snapshot.childs[0]=15;f.lifecycle.snapshot.countryId=1;f.form.country='1';
  f.emit('complete');await settle();
  assert.deepEqual(f.calls[0].payload,{searchId:101,departureId:1,countryId:4,adults:2,childs:[7,3]});
  assert.equal(f.calls[0].options.keepalive,true);
  f.emit('complete');await settle();assert.equal(f.calls.length,1,'duplicate completion is idempotent');
  f.emit('continued');await settle();assert.equal(f.calls.length,2,'continuation saves new offers even under same searchId');
  assert.deepEqual(f.calls[1].payload,f.calls[0].payload);

  const retry=fixture([new Error('network'),ok()]);retry.emit('started');retry.emit('complete');await settle();
  assert.equal(retry.timers.length,1);retry.lifecycle.snapshot.countryId=6;retry.timers.shift()();await settle();
  assert.equal(retry.calls.length,2);assert.equal(retry.calls[1].payload.countryId,4,'retry keeps immutable identity');
  const legacy=fixture([{ok:true,json:async()=>({ok:true,accepted:true})},ok()]);
  legacy.emit('started');legacy.emit('complete');await settle();assert.equal(legacy.timers.length,1,'202 accepted is not persisted');legacy.timers.shift()();await settle();assert.equal(legacy.calls.length,2);
  const failing=fixture(Array.from({length:5},()=>({ok:false,json:async()=>({ok:false})})));
  failing.emit('started');failing.emit('complete');await settle();
  while(failing.timers.length){failing.timers.shift()();await settle();}
  assert.equal(failing.calls.length,3,'bounded retry budget');

  let finish;const racing=fixture([()=>new Promise(resolve=>{finish=resolve;})]);
  racing.emit('started');racing.emit('complete');await settle();racing.emit('continued');
  assert.equal(racing.calls.length,1,'coalesce in-flight');finish(ok());await settle();
  assert.equal(racing.calls.length,2,'continuation is not lost during acknowledgement');
  const stale=fixture();stale.lifecycle.searchId=999;stale.emit('complete');await settle();
  assert.equal(stale.calls.length,0,'unknown stale search never uses current form context');
  const late=fixture();late.emit('complete');await settle();assert.equal(late.calls.length,1,'late observer can recover matching lifecycle snapshot');
  const formOnly=fixture();delete formOnly.window.V2SearchLifecycle;formOnly.emit('started');formOnly.form.country='6';formOnly.emit('complete');await settle();
  assert.equal(formOnly.calls[0].payload.countryId,4,'fallback captured at start, not completion');
  console.log('PASSIVE_PRICE_OBSERVER_OK immutable_context=1 acknowledged=1 retries=3 continuation_race=1 stale_guard=1');
})().catch(e=>{console.error(e);process.exitCode=1;});
