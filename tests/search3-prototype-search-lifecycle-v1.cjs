'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');

const source=fs.readFileSync(process.argv[2]||'v2/prototype-search/search-lifecycle-v1.js','utf8');
const app=fs.readFileSync(process.argv[3]||'v2/prototype-search/app.js','utf8');
const entry=fs.readFileSync(process.argv[4]||'v2/prototype-search/index.html','utf8');
const php=fs.readFileSync(process.argv[5]||'v2/prototype-search/index.php','utf8');
const dataSource=fs.readFileSync('v2/prototype-search/data.js','utf8');
const leadSource=fs.readFileSync('v2/prototype-search/lead.js','utf8');

assert.match(entry,/search-lifecycle-v1\.js/,'prototype entrypoint must load the canonical lifecycle owner');
assert.ok(entry.indexOf('search-lifecycle-v1.js')<entry.indexOf('./app.js'),'lifecycle owner must load before app.js');
assert.match(app,/AnyTourPrototypeSearchLifecycleV1\.create/,'app must delegate to canonical lifecycle owner');
assert.doesNotMatch(app,/\bdata\.search\s*\(/,'app.js must not own direct async data.search orchestration');
assert.doesNotMatch(app,/\$\('#search-form'\)\.addEventListener\('submit'/,'app.js must not own the search-form submit listener');
assert.match(app,/supplierFilters:\(\)=>structuredClone\(state\.filters\)/,'inventory scope must come from canonical applied app state');
assert.match(app,/refreshResults=dateContext\.source==='results'.*searchLifecycle\.requestSubmit\(\)/s,'results-side date apply must ask the lifecycle owner for a real search');
assert.doesNotMatch(php,/results-date-refresh-v1\.js|inventory-scope-refresh-v1\.js/,'PHP runtime must not inject post-app lifecycle patches');
assert.equal(fs.existsSync('v2/prototype-search/results-date-refresh-v1.js'),false,'retired results-date patch must stay deleted');
assert.equal(fs.existsSync('v2/prototype-search/inventory-scope-refresh-v1.js'),false,'retired inventory-scope patch must stay deleted');
assert.equal(fs.existsSync('tests/search3-prototype-results-date-refresh-v1.cjs'),false,'retired date-patch regression must stay deleted');
assert.equal(fs.existsSync('tests/search3-prototype-inventory-scope-refresh-v1.cjs'),false,'retired scope-patch regression must stay deleted');
assert.match(entry,/\.\.\/tour-controller-v4\.js/,'prototype must retain the compatibility lead-session dependency');
assert.ok(entry.indexOf('../tour-controller-v4.js')<entry.indexOf('./data.js'),'tour controller must load before data.js consumes its lead-session API');
assert.match(dataSource,/V2TourController\.createLeadSession\(/,'data leadSession must explicitly delegate to the protected controller contract');
assert.match(leadSource,/AnyTourPrototypeData\.leadSession\(offer\)/,'lead UI must consume that protected data lead-session boundary');

const flush=async()=>{for(let i=0;i<6;i++)await Promise.resolve();};

function harness({reject=false,hidden=false,covered=true,previous={kind:'previous'},submitEnabled=true}={}){
 const submitListeners=[],eventListeners={click:[],change:[],input:[]},calls=[],results=[],events=[],failures=[],starts=[],submits=[],scopeCalls=[];
 let currentKey='trip-a',prepareCount=0,commitCount=0,requests=0,filters={stars:[5],max:600000},enabled=submitEnabled;
 const form={
   hidden,
   addEventListener(type,listener){if(type==='submit')submitListeners.push(listener);},
   requestSubmit(){requests++;for(const listener of submitListeners)listener({preventDefault(){}});}
 };
 const eventTarget={addEventListener(type,listener){if(eventListeners[type])eventListeners[type].push(listener);}};
 const data={
   currentSupplierScope:previous,
   search(search,callback,hotelIds,appliedFilters){
     calls.push({search:structuredClone(search),callback,hotelIds:structuredClone(hotelIds),filters:structuredClone(appliedFilters)});
     return reject?Promise.reject(new Error('synthetic search failure')):Promise.resolve();
   },
   supplierScope(nextFilters){scopeCalls.push(structuredClone(nextFilters));return {kind:'next',filters:structuredClone(nextFilters)};},
   supplierScopeCovered(before,next){return typeof covered==='function'?covered(before,next):covered;}
 };
 const context={window:{},Object,Promise,console,queueMicrotask};
 vm.createContext(context);vm.runInContext(source,context,{filename:'search-lifecycle-v1.js'});
 const api=context.window.AnyTourPrototypeSearchLifecycleV1;
 assert.ok(Object.isFrozen(api));
 const lifecycle=api.create({
   form,data,events:eventTarget,canSubmit:()=>enabled,supplierFilters:()=>structuredClone(filters),
   prepare(options){prepareCount++;return {search:{origin:'Москва',country:'4'},filters:structuredClone(filters),hotelIds:[101,102],response:{key:currentKey,phase:'loading',operators:[],pending:true,exactRefresh:options.exactRefresh===true}};},
   currentKey:()=>currentKey,
   onResults(event){results.push(structuredClone(event.hotels));},
   afterEvent(event,response){events.push({type:event.type,response:structuredClone(response)});},
   afterStart(response){starts.push(structuredClone(response));},
   afterFailure(error,response){failures.push({message:error.message,response:structuredClone(response)});},
   commit(){commitCount++;return {exactRefresh:false};},
   afterSubmit(started){submits.push(started);}
 });
 const fire=async (type,event={})=>{for(const listener of eventListeners[type]||[])listener(event);await flush();};
 return {
   lifecycle,form,data,calls,results,events,failures,starts,submits,submitListeners,eventListeners,scopeCalls,fire,
   get prepareCount(){return prepareCount;},get commitCount(){return commitCount;},get requests(){return requests;},
   set currentKey(value){currentKey=value;},set filters(value){filters=value;},set enabled(value){enabled=value;}
 };
}

(async()=>{
 {
  const h=harness();
  assert.equal(h.lifecycle.bind(),true);assert.equal(h.lifecycle.bind(),false);assert.equal(h.submitListeners.length,1);
  assert.equal(h.eventListeners.click.length,1);assert.equal(h.eventListeners.change.length,1);assert.equal(h.eventListeners.input.length,0);
  let prevented=0;h.submitListeners[0]({preventDefault(){prevented++;}});
  assert.equal(prevented,1);assert.equal(h.commitCount,1);assert.equal(h.prepareCount,1);assert.equal(h.calls.length,1);assert.equal(h.submits[0],true);
  assert.deepEqual(h.calls[0].search,{origin:'Москва',country:'4'});
  assert.deepEqual(h.calls[0].hotelIds,[101,102]);assert.deepEqual(h.calls[0].filters,{stars:[5],max:600000});
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
  const h=harness({hidden:true,covered:true});h.lifecycle.bind();h.lifecycle.run({});const late=h.calls[0].callback;
  await h.fire('click',{target:{closest(){return {dataset:{action:'stop-search'}};}}});
  const beforeEvents=h.events.length,beforeResults=h.results.length;
  late({type:'progress',progress:77});late({type:'results',hotels:[{id:77}]});
  assert.equal(h.events.length,beforeEvents,'stop-search invalidates late lifecycle events');
  assert.equal(h.results.length,beforeResults,'stop-search invalidates late result projection');
  assert.equal(h.requests,0,'stop-search never schedules a supplier scope refresh');
 }
 {
  const h=harness({hidden:true,covered:true});h.lifecycle.bind();h.lifecycle.run({});const late=h.calls[0].callback;
  await h.fire('click',{target:{closest(){return {dataset:{action:'edit-search'}};}}});
  const before=h.events.length;late({type:'progress',progress:55});
  assert.equal(h.events.length,before,'editing a pending search invalidates late callbacks');
  assert.equal(h.requests,0,'edit-search does not schedule an automatic supplier refresh');
 }
 {
  const h=harness({reject:true});h.lifecycle.run({});await flush();
  assert.equal(h.failures.length,1);assert.equal(h.failures[0].message,'synthetic search failure');
  assert.equal(h.failures[0].response.pending,false);assert.equal(h.failures[0].response.phase,'error');assert.equal(h.failures[0].response.message,'synthetic search failure');
 }
 {
  const h=harness({hidden:true,covered:false});h.lifecycle.bind();
  h.eventListeners.click[0]({});
  h.filters={stars:[4],max:900000};
  await flush();
  assert.equal(h.requests,1,'widened result scope must use canonical requestSubmit path');
  assert.deepEqual(h.scopeCalls[0],{stars:[4],max:900000},'scope inspection must read applied state after the click handler finishes');
  assert.equal(h.calls.length,1,'requestSubmit must re-enter the same lifecycle search path');
 }
 {
  const h=harness({hidden:true,covered:true});h.lifecycle.bind();await h.fire('change');
  assert.equal(h.requests,0,'covered narrowing stays local');
  assert.equal(h.scopeCalls.length,1);
 }
 {
  const h=harness({hidden:false,covered:false});h.lifecycle.bind();await h.fire('click');
  assert.equal(h.requests,0,'visible main form edits never auto-submit');
  assert.equal(h.scopeCalls.length,0);
 }
 {
  const h=harness({hidden:true,covered:false,previous:null});h.lifecycle.bind();await h.fire('click');
  assert.equal(h.requests,0,'without an actual prior supplier scope lifecycle does not invent one');
 }
 {
  const h=harness({hidden:true,covered:false,submitEnabled:false});h.lifecycle.bind();await h.fire('change');
  assert.equal(h.requests,0,'disabled canonical submit fails closed');
  h.enabled=true;await h.fire('change');assert.equal(h.requests,1,'same widening refreshes once submit becomes available');
 }
 {
  const h=harness({hidden:true,covered:false});h.lifecycle.bind();await h.fire('input');
  assert.equal(h.requests,0,'input keystrokes and range drags do not trigger supplier searches before native change');
 }
 {
  const h=harness({submitEnabled:true});h.lifecycle.bind();h.lifecycle.requestSubmit();h.lifecycle.requestSubmit();await flush();
  assert.equal(h.requests,1,'results-date refresh requests coalesce into one canonical submit');
 }
 {
  const h=harness({submitEnabled:false});h.lifecycle.bind();h.lifecycle.requestSubmit();await flush();
  assert.equal(h.requests,0,'results-date refresh respects disabled canonical submit');
 }
 {
  const h=harness({submitEnabled:false});h.lifecycle.bind();let prevented=0;
  const started=h.submitListeners[0]({preventDefault(){prevented++;}});
  assert.equal(started,false,'disabled direct submit must fail closed');
  assert.equal(prevented,1,'disabled direct submit must still suppress native navigation');
  assert.equal(h.commitCount,0,'disabled direct submit must not commit form state');
  assert.equal(h.prepareCount,0,'disabled direct submit must not prepare a search');
  assert.equal(h.calls.length,0,'disabled direct submit must not call the data search owner');
  assert.equal(h.submits.length,0,'disabled direct submit must not run afterSubmit side effects');
  h.enabled=true;h.submitListeners[0]({preventDefault(){}});
  assert.equal(h.commitCount,1,'re-enabled direct submit resumes canonical commit');
  assert.equal(h.prepareCount,1,'re-enabled direct submit prepares exactly once');
  assert.equal(h.calls.length,1,'re-enabled direct submit starts exactly one search');
  assert.deepEqual(h.submits,[true]);
 }
 console.log('search3 prototype search lifecycle v1: PASS');
})().catch(error=>{console.error(error);process.exitCode=1;});
