'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');

const source=fs.readFileSync(process.argv[2]||'v2/prototype-search/search-lifecycle-v1.js','utf8');
const app=fs.readFileSync(process.argv[3]||'v2/prototype-search/app.js','utf8');
const entry=fs.readFileSync(process.argv[4]||'v2/prototype-search/index.html','utf8');

assert.match(entry,/search-lifecycle-v1\.js/,'prototype entrypoint must load the canonical lifecycle owner');
assert.ok(entry.indexOf('search-lifecycle-v1.js')<entry.indexOf('./app.js'),'lifecycle owner must load before app.js');
assert.match(app,/AnyTourPrototypeSearchLifecycleV1\.create/,'app must delegate to canonical lifecycle owner');
assert.doesNotMatch(app,/\bdata\.search\s*\(/,'app.js must not own direct async data.search orchestration');
assert.doesNotMatch(app,/\$\('#search-form'\)\.addEventListener\('submit'/,'app.js must not own the search-form submit listener');

const flush=async()=>{for(let i=0;i<4;i++)await Promise.resolve();};

function harness({reject=false}={}){
 const submitListeners=[],calls=[],results=[],events=[],failures=[],starts=[],submits=[];
 let currentKey='trip-a',prepareCount=0,commitCount=0;
 const form={addEventListener(type,listener){if(type==='submit')submitListeners.push(listener);}};
 const data={search(search,callback,hotelIds,filters){
   calls.push({search:structuredClone(search),callback,hotelIds:structuredClone(hotelIds),filters:structuredClone(filters)});
   return reject?Promise.reject(new Error('synthetic search failure')):Promise.resolve();
 }};
 const context={window:{},Object,Promise,console};
 vm.createContext(context);vm.runInContext(source,context,{filename:'search-lifecycle-v1.js'});
 const api=context.window.AnyTourPrototypeSearchLifecycleV1;
 assert.ok(Object.isFrozen(api));
 const lifecycle=api.create({
   form,data,
   prepare(options){prepareCount++;return {search:{origin:'Москва',country:'4'},filters:{stars:[5]},hotelIds:[101,102],response:{key:currentKey,phase:'loading',operators:[],pending:true,exactRefresh:options.exactRefresh===true}};},
   currentKey:()=>currentKey,
   onResults(event){results.push(structuredClone(event.hotels));},
   afterEvent(event,response){events.push({type:event.type,response:structuredClone(response)});},
   afterStart(response){starts.push(structuredClone(response));},
   afterFailure(error,response){failures.push({message:error.message,response:structuredClone(response)});},
   commit(){commitCount++;return {exactRefresh:false};},
   afterSubmit(started){submits.push(started);}
 });
 return {
   lifecycle,form,data,calls,results,events,failures,starts,submits,submitListeners,
   get prepareCount(){return prepareCount;},get commitCount(){return commitCount;},
   set currentKey(value){currentKey=value;}
 };
}

(async()=>{
 {
  const h=harness();
  assert.equal(h.lifecycle.bind(),true);assert.equal(h.lifecycle.bind(),false);assert.equal(h.submitListeners.length,1);
  let prevented=0;h.submitListeners[0]({preventDefault(){prevented++;}});
  assert.equal(prevented,1);assert.equal(h.commitCount,1);assert.equal(h.prepareCount,1);assert.equal(h.calls.length,1);assert.equal(h.submits[0],true);
  assert.deepEqual(h.calls[0].search,{origin:'Москва',country:'4'});
  assert.deepEqual(h.calls[0].hotelIds,[101,102]);assert.deepEqual(h.calls[0].filters,{stars:[5]});
  assert.equal(h.starts.length,1,'initial render hook must run after data.search starts');
  const send=h.calls[0].callback;
  send({type:'loading',continued:true,retryRead:false});
  assert.equal(h.events.at(-1).response.pending,true);assert.equal(h.events.at(-1).response.continued,true);
  assert.equal(h.events.at(-1).response.message,'Запрашиваем дополнительные варианты. Найденные предложения сохраняются.');
  send({type:'provider',provider:'andromeda',status:'partial'});
  assert.equal(h.events.at(-1).response.providers.andromeda,'partial');
  send({type:'database-error'});assert.equal(h.events.at(-1).response.databaseError,true);
  send({type:'database'});assert.equal(h.events.at(-1).response.databaseError,false);
  send({type:'progress',progress:42});assert.equal(h.events.at(-1).response.message,'Получаем предложения · 42%');
  send({type:'results',hotels:[{id:1}]});assert.deepEqual(h.results,[[{id:1}]]);
  send({type:'complete',canContinue:true,retryRead:true,resultLimitReached:false,sources:{tourvisor:{status:'complete'}}});
  const complete=h.events.at(-1).response;assert.equal(complete.pending,false);assert.equal(complete.phase,'complete');assert.equal(complete.canContinue,true);assert.equal(complete.retryRead,true);assert.deepEqual(complete.sources,{tourvisor:{status:'complete'}});
  send({type:'error',message:'synthetic provider error',canContinue:false,retryRead:false});
  const failed=h.events.at(-1).response;assert.equal(failed.phase,'error');assert.equal(failed.message,'synthetic provider error');assert.equal(failed.canContinue,false);
 }
 {
  const h=harness();h.lifecycle.run({});const stale=h.calls[0].callback;
  h.currentKey='trip-b';h.lifecycle.run({exactRefresh:true});const fresh=h.calls[1].callback;
  const before=h.events.length;stale({type:'progress',progress:10});assert.equal(h.events.length,before,'old callback must be ignored after key/generation changes');
  fresh({type:'loading',retryRead:true});assert.equal(h.events.at(-1).response.message,'Проверяем результат предыдущего запроса без повторного запуска.');
  assert.equal(h.events.at(-1).response.exactRefresh,true);
 }
 {
  const h=harness({reject:true});h.lifecycle.run({});await flush();
  assert.equal(h.failures.length,1);assert.equal(h.failures[0].message,'synthetic search failure');
  assert.equal(h.failures[0].response.pending,false);assert.equal(h.failures[0].response.phase,'error');assert.equal(h.failures[0].response.message,'synthetic search failure');
 }
 console.log('search3 prototype search lifecycle v1: PASS');
})().catch(error=>{console.error(error);process.exitCode=1;});
