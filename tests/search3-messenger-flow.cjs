'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
// Reuse existing canonical UI receipt fixtures and the actual context owner.
// Register, but do not execute, the previously accepted 27 context cases.
let fixtures=fs.readFileSync(path.join(__dirname,'search3-messenger-context.cjs'),'utf8');
const runner="function test(name,fn){fn();count++;console.log('PASS '+name);}";
assert.ok(fixtures.includes(runner));fixtures=fixtures.replace(runner,'function test(){}');
fixtures=fixtures.slice(0,fixtures.lastIndexOf('\nconsole.log('));
const harness=vm.createContext({require,__dirname,structuredClone,console:{log(){throw Error('old baseline executed');}}});
vm.runInContext(fixtures+'\nglobalThis.fixtureOwner={applicationFixture,tourvisorFixture,api,existingCasesRun:count,setClock:value=>time=value,clock:()=>time};',harness);
assert.equal(harness.fixtureOwner.existingCasesRun,0);
const io=[];
const root={fetch(){io.push('fetch');throw Error('unexpected transport');},localStorage:new Proxy({},{get(){io.push('storage');throw Error('unexpected storage');}}),document:new Proxy({},{get(){io.push('DOM');throw Error('unexpected DOM');}})};
vm.runInNewContext(fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/messenger-flow-v1.js'),'utf8'),{window:root,URL});
const api=root.AnyTourSearch3MessengerFlowV1,plain=x=>JSON.parse(JSON.stringify(x));
const id='a'.repeat(32),csrf='b'.repeat(64),choice='anex_quote:'+'c'.repeat(64);
const validLinks={telegram:'https://t.me/Any_tour_bot?start=fixture-only',max:'https://max.ru/id9704048781_bot?start=fixture-only'};
const defer=()=>{let resolve,reject;const promise=new Promise((yes,no)=>{resolve=yes;reject=no;});return {promise,resolve,reject};};
function setup(route){
  const owner=harness.fixtureOwner;owner.setClock(1800000000000);
  const input=owner.applicationFixture(true,choice),selection=owner.api.createSession(()=>input,owner.clock),snapshot=selection.capture(),calls=[];
  const request=async(action,fields)=>{
    calls.push({action,fields:fields&&plain(fields)});
    if(route){const reply=route(action,fields);if(reply!==undefined)return reply;}
    if(action==='handoff_token')return {ok:true,csrf};
    if(action==='handoff_freeze')return {ok:true,data:{operation_id:id,state:'prepared',observation:plain(snapshot)}};
    return {ok:true,data:{operation_id:id,state:action==='handoff_revoke'?'revoked':'ready',links:validLinks}};
  };
  const flow=api.createSession({selection,request});return {input,selection,snapshot,calls,flow,owner};
}
const tests=[];const test=(name,fn)=>tests.push({name,fn});
test('Explicit prepare freezes exact selector; explicit create alone exposes platform links',async()=>{
  const x=setup();assert.equal(x.flow.view(x.snapshot).state,'idle');assert.equal(x.calls.length,0);
  const review=await x.flow.prepare(x.snapshot);assert.equal(review.state,'prepared');assert.equal(review.links,undefined);
  assert.equal(review.observation.price.amount,'231456');assert.deepEqual(plain(review.observation.trip.childAges),[0,0,8]);
  assert.deepEqual(x.calls.map(c=>c.action),['handoff_token','handoff_freeze']);
  assert.deepEqual(x.calls[1].fields,{generation:7,search_ref:'b'.repeat(32),offer_ref:'anex_online:'+'a'.repeat(64),local_hotel_id:3634,choice_ref:choice,csrf});
  const result=await x.flow.create(x.snapshot);assert.equal(result.state,'ready');assert.equal(x.flow.link(x.snapshot,'telegram'),validLinks.telegram);
  assert.deepEqual(x.calls[2],{action:'handoff_create',fields:{operation_id:id,csrf}});assert.equal(Object.isFrozen(result.links),true);
  assert.doesNotMatch(JSON.stringify(result),/anex_quote:|anex_online:|search_ref|choice_ref|csrf|SECRET_|operation_id/);
});
test('No token or create for copied, unsupported or unprepared selection',async()=>{
  const x=setup();assert.equal((await x.flow.prepare(plain(x.snapshot))).state,'stale');
  await x.flow.create(x.snapshot);assert.equal(x.calls.length,0);
  x.input.application={...x.input.application,priceKind:'estimate',finalPriceVerified:false,flights:[],choiceRef:undefined,expiresAt:undefined};
  const deferred=x.selection.capture();await x.flow.prepare(deferred);assert.equal(x.calls.length,0);
});
test('Selection changed during token prevents freeze',async()=>{
  const gate=defer(),x=setup(action=>action==='handoff_token'?gate.promise:undefined),task=x.flow.prepare(x.snapshot);
  await Promise.resolve();await Promise.resolve();x.input.revision++;gate.resolve({ok:true,csrf});
  assert.equal((await task).state,'stale');assert.deepEqual(x.calls.map(c=>c.action),['handoff_token']);
});
test('Late freeze retains cancellation identity while blocking changed selection',async()=>{
  const gate=defer(),x=setup(action=>action==='handoff_freeze'?gate.promise:undefined),task=x.flow.prepare(x.snapshot);
  while(!x.calls.some(c=>c.action==='handoff_freeze'))await Promise.resolve();
  x.input.revision++;gate.resolve({ok:true,data:{operation_id:id,state:'prepared',observation:plain(x.snapshot)}});
  const result=await task;assert.equal(result.state,'stale');assert.equal(result.canRevoke,true);await x.flow.create(x.snapshot);
  assert.equal(x.calls.some(c=>c.action==='handoff_create'),false);await x.flow.revoke(x.snapshot);assert.equal(x.calls.at(-1).action,'handoff_revoke');
});
test('Late successful create never exposes another selection links',async()=>{
  const gate=defer(),x=setup(action=>action==='handoff_create'?gate.promise:undefined);await x.flow.prepare(x.snapshot);
  const task=x.flow.create(x.snapshot);await Promise.resolve();x.input.revision++;gate.resolve({ok:true,data:{operation_id:id,state:'ready',links:validLinks}});
  const result=await task;assert.equal(result.state,'stale');assert.equal(result.links,undefined);assert.equal(x.flow.link(x.snapshot,'max'),null);
  await x.flow.status(x.snapshot);assert.equal(x.calls.at(-1).action,'handoff_status');assert.equal(x.flow.view(x.snapshot).links,undefined);
});
test('Concurrent prepare and double create coalesce into one invocation each',async()=>{
  const gate=defer(),x=setup(action=>action==='handoff_create'?gate.promise:undefined);
  await Promise.all([x.flow.prepare(x.snapshot),x.flow.prepare(x.snapshot)]);
  const first=x.flow.create(x.snapshot),second=x.flow.create(x.snapshot);gate.resolve({ok:true,data:{operation_id:id,state:'ready',links:validLinks}});await Promise.all([first,second]);
  assert.deepEqual(x.calls.map(c=>c.action),['handoff_token','handoff_freeze','handoff_create']);
  const check=defer(),unknown=setup(action=>action==='handoff_create'?Promise.reject(Error('timeout')):action==='handoff_status'?check.promise:undefined);
  await unknown.flow.prepare(unknown.snapshot);await unknown.flow.create(unknown.snapshot);
  const a=unknown.flow.create(unknown.snapshot),b=unknown.flow.create(unknown.snapshot);check.resolve({ok:true,data:{operation_id:id,state:'ready',links:validLinks}});await Promise.all([a,b]);
  assert.equal(unknown.calls.filter(c=>c.action==='handoff_status').length,1);
});
test('Lost create response permits only explicit status of the original operation',async()=>{
  const x=setup(action=>action==='handoff_create'?Promise.reject(Error('private transport diagnostic')):undefined);await x.flow.prepare(x.snapshot);
  const unknown=await x.flow.create(x.snapshot);assert.equal(unknown.state,'unknown');assert.equal(unknown.links,undefined);assert.equal(unknown.error,'handoff_unavailable');
  await x.flow.create(x.snapshot);assert.deepEqual(x.calls.map(c=>c.action),['handoff_token','handoff_freeze','handoff_create','handoff_status']);
  assert.equal(x.flow.link(x.snapshot,'max'),validLinks.max);assert.equal(x.calls[2].fields.operation_id,x.calls[3].fields.operation_id);
});
test('UNKNOWN missing/prepared status never remints or replays create',async()=>{
  for(const state of ['missing','prepared']){
    const x=setup(action=>action==='handoff_create'?Promise.reject(Error('timeout')):action==='handoff_status'?{ok:true,data:{operation_id:id,state}}:undefined);
    await x.flow.prepare(x.snapshot);await x.flow.create(x.snapshot);await x.flow.status(x.snapshot);await x.flow.create(x.snapshot);
    assert.equal(x.calls.filter(c=>c.action==='handoff_create').length,1);assert.equal(x.calls.filter(c=>c.action==='handoff_freeze').length,1);assert.equal(x.flow.link(x.snapshot,'telegram'),null);
    if(state==='prepared')assert.equal(x.flow.view(x.snapshot).state,'unknown');
  }
});
test('A recovered existing ready/UNKNOWN freeze is status-only',async()=>{
  for(const state of ['ready','unknown','revoke_unknown']){
    let observation;const x=setup(action=>action==='handoff_freeze'?{ok:true,data:{operation_id:id,state,observation}}:undefined);observation=plain(x.snapshot);
    await x.flow.prepare(x.snapshot);assert.equal(x.flow.view(x.snapshot).links,undefined);await x.flow.create(x.snapshot);
    assert.equal(x.calls.some(c=>c.action==='handoff_create'),false);
  }
});
test('Revocation hides links immediately, survives failure and cannot be reopened by ready',async()=>{
  const gate=defer(),x=setup(action=>action==='handoff_revoke'?gate.promise:undefined);await x.flow.prepare(x.snapshot);await x.flow.create(x.snapshot);
  const task=x.flow.revoke(x.snapshot);assert.equal(x.flow.link(x.snapshot,'telegram'),null);gate.reject(Error('timeout'));
  assert.equal((await task).state,'revoke_unknown');await x.flow.status(x.snapshot);assert.equal(x.flow.link(x.snapshot,'telegram'),null);
  await x.flow.create(x.snapshot);assert.equal(x.calls.filter(c=>c.action==='handoff_create').length,1);
});
test('Revocation queued behind create never exposes that late ready response',async()=>{
  const gate=defer(),x=setup(action=>action==='handoff_create'?gate.promise:undefined);await x.flow.prepare(x.snapshot);
  const create=x.flow.create(x.snapshot),revoke=x.flow.revoke(x.snapshot);gate.resolve({ok:true,data:{operation_id:id,state:'ready',links:validLinks}});
  assert.equal((await create).links,undefined);assert.equal((await revoke).state,'revoked');assert.equal(x.flow.link(x.snapshot,'telegram'),null);
  assert.deepEqual(x.calls.slice(-2).map(c=>c.action),['handoff_create','handoff_revoke']);
});
test('Foreign operation and malformed/foreign links cannot become ready',async()=>{
  const replies=[{operation_id:'d'.repeat(32),state:'ready',links:validLinks},
    ...['http://t.me/bot','https://t.me.evil.test/bot','https://u@t.me/bot','https://t.me/bot#x','https://t.me:444/bot','https://t.me/'].map(telegram=>({operation_id:id,state:'ready',links:{...validLinks,telegram}})),
    {operation_id:id,state:'ready',links:{telegram:validLinks.telegram}}];
  for(const data of replies){const x=setup(action=>action==='handoff_create'?{ok:true,data}:undefined);await x.flow.prepare(x.snapshot);assert.equal((await x.flow.create(x.snapshot)).state,'unknown');assert.equal(x.flow.link(x.snapshot,'telegram'),null);}
});
test('Malformed CSRF and freeze fail before CREATE and leak no diagnostics',async()=>{
  for(const bad of ['token','freeze']){
    const x=setup(action=>bad==='token'&&action==='handoff_token'?{ok:true,csrf:'bad'}:bad==='freeze'&&action==='handoff_freeze'?{ok:true,data:{operation_id:id,state:'prepared',observation:{}}}:undefined);
    const result=await x.flow.prepare(x.snapshot);assert.equal(result.state,'unavailable');assert.equal(result.error,'handoff_unavailable');await x.flow.create(x.snapshot);assert.equal(x.calls.some(c=>c.action==='handoff_create'),false);
  }
});
test('Actual click-time lookup rejects changed conditions and the exact expiry boundary',async()=>{
  for(const change of [x=>x.input.revision++,x=>x.owner.setClock(x.input.application.expiresAt*1000)]){
    const x=setup();await x.flow.prepare(x.snapshot);const old=await x.flow.create(x.snapshot);assert.ok(old.links);change(x);
    assert.equal(x.flow.link(x.snapshot,'telegram'),null);assert.equal(x.flow.link(x.snapshot,'other'),null);assert.equal(x.flow.view(x.snapshot).links,undefined);
  }
});
test('The same operation cannot be replayed through a fresh capture of identical terms',async()=>{
  const x=setup(action=>action==='handoff_create'?Promise.reject(Error('timeout')):undefined);await x.flow.prepare(x.snapshot);await x.flow.create(x.snapshot);
  const fresh=x.selection.capture();await x.flow.prepare(fresh);await x.flow.create(fresh);
  assert.equal(x.calls.filter(c=>c.action==='handoff_create').length,1);assert.equal(x.calls.at(-1).action,'handoff_status');
});
test('TV manager and exact-pair preparation retain the original server deadline through reopen and CREATE',async()=>{
 for(const manager of [true,false]){
  const owner=harness.fixtureOwner;owner.setClock(1800000000000);
  const input=owner.tourvisorFixture(manager),selection=owner.api.createSession(()=>input,owner.clock),snapshot=selection.capture(),calls=[];
  const observation={...plain(snapshot),price:{...plain(snapshot.price),expiresAt:owner.clock()/1000+30}};
  const flow=api.createSession({selection,clock:owner.clock,request:async(action,fields)=>{
   calls.push({action,fields:plain(fields||{})});
   if(action==='handoff_token')return {ok:true,csrf};
   if(action==='handoff_freeze')return {ok:true,data:{operation_id:id,state:'prepared',observation}};
   return {ok:true,data:{operation_id:id,state:'ready',links:validLinks}};
  }});
  assert.equal((await flow.prepare(snapshot)).state,'prepared');assert.deepEqual(calls[1].fields,{...plain(input.selector),csrf});
  assert.equal(calls.some(c=>c.action==='handoff_create'),false);assert.equal((await flow.create(snapshot)).state,'ready');
  owner.setClock(observation.price.expiresAt*1000);assert.equal(flow.view(snapshot).state,'expired');assert.equal(flow.link(snapshot,'telegram'),null);
  await flow.create(snapshot);assert.equal(calls.filter(c=>c.action==='handoff_create').length,1);
  const fresh=selection.capture();assert.equal((await flow.prepare(fresh)).state,'expired','reopen cannot renew the frozen deadline');
  await flow.create(fresh);assert.equal(calls.filter(c=>c.action==='handoff_create').length,1);assert.equal(flow.link(fresh,'max'),null);
 }
});
test('TV freeze without its authoritative original expiry cannot enable CREATE',async()=>{
 const owner=harness.fixtureOwner,input=owner.tourvisorFixture(true),selection=owner.api.createSession(()=>input,owner.clock),snapshot=selection.capture(),calls=[];
 const flow=api.createSession({selection,request:async(action)=>{calls.push(action);return action==='handoff_token'?{ok:true,csrf}:{ok:true,data:{operation_id:id,state:'prepared',observation:plain(snapshot)}};}});
 assert.equal((await flow.prepare(snapshot)).state,'unavailable');await flow.create(snapshot);assert.deepEqual(calls,['handoff_token','handoff_freeze']);
});
test('Preparation preserves bounded token and freeze failures without exposing private exception data',async()=>{
 for(const action of ['handoff_token','handoff_freeze']){
  const x=setup(current=>current===action?Promise.reject(Object.assign(Error('PRIVATE_EXCEPTION'),{handoffFailure:{
   action,stage:'http',code:'forbidden',httpStatus:403,csrf,operation_id:id,links:validLinks,message:'PRIVATE_BODY'
  }})):undefined);
  const result=await x.flow.prepare(x.snapshot);
  assert.equal(result.state,'unavailable');assert.equal(result.error,'handoff_unavailable');
  assert.deepEqual(plain(result.failure),{action,stage:'http',code:'forbidden',httpStatus:403});
  assert.equal(Object.isFrozen(result.failure),true);
  assert.doesNotMatch(JSON.stringify(result),/PRIVATE_|csrf|operation_id|fixture-only|links/);
  await x.flow.create(x.snapshot);assert.equal(x.calls.some(c=>c.action==='handoff_create'),false);
 }
});
test('Create and status failures keep distinct facts while UNKNOWN remains status-only',async()=>{
 let statuses=0;
 const x=setup(action=>action==='handoff_create'?Promise.reject(Object.assign(Error('PRIVATE_CREATE'),{handoffFailure:{action,stage:'http',code:'signer_unavailable',httpStatus:503}})):
  action==='handoff_status'&&statuses++===0?Promise.reject(Object.assign(Error('PRIVATE_STATUS'),{handoffFailure:{action,stage:'http',code:'handoff_missing',httpStatus:404}})):undefined);
 await x.flow.prepare(x.snapshot);
 const create=await x.flow.create(x.snapshot);assert.equal(create.state,'unknown');
 assert.deepEqual(plain(create.failure),{action:'handoff_create',stage:'http',code:'signer_unavailable',httpStatus:503});
 const status=await x.flow.create(x.snapshot);assert.equal(status.state,'unknown');
 assert.deepEqual(plain(status.failure),{action:'handoff_status',stage:'http',code:'handoff_missing',httpStatus:404});
 assert.deepEqual(x.calls.map(c=>c.action),['handoff_token','handoff_freeze','handoff_create','handoff_status']);
 const ready=await x.flow.status(x.snapshot);assert.equal(ready.state,'ready');assert.equal(ready.error,undefined);assert.equal(ready.failure,undefined);
 assert.equal(x.calls.filter(c=>c.action==='handoff_create').length,1);
});
test('Foreign action, unlisted stage/code and malformed status cannot enter the public failure view',async()=>{
 for(const httpStatus of [true,'403',99,600,NaN]){
  const x=setup(action=>action==='handoff_token'?Promise.reject(Object.assign(Error('PRIVATE'),{handoffFailure:{
   action,stage:'PRIVATE_STAGE',code:'PRIVATE_CODE',httpStatus,field:'PRIVATE_FIELD',owner:'PRIVATE_OWNER',raw:'PRIVATE_BODY'
  }})):undefined);
  const result=await x.flow.prepare(x.snapshot);
  assert.deepEqual(plain(result.failure),{action:'handoff_token',stage:'transport',code:'handoff_unavailable'});
  assert.doesNotMatch(JSON.stringify(result),/PRIVATE/);
 }
 const x=setup(action=>action==='handoff_token'?Promise.reject(Object.assign(Error('PRIVATE'),{handoffFailure:{
  action:'handoff_create',stage:'http',code:'forbidden',httpStatus:403
 }})):undefined);
 assert.deepEqual(plain((await x.flow.prepare(x.snapshot)).failure),{action:'handoff_token',stage:'transport',code:'handoff_unavailable'});
});
test('TV selection facts belong only to the conditional TV freeze boundary',async()=>{
 const fact={action:'tv_select',stage:'http',code:'retained_fact_missing',httpStatus:422};
 const owner=harness.fixtureOwner,input=owner.tourvisorFixture(true),selection=owner.api.createSession(()=>input,owner.clock),snapshot=selection.capture();
 const flow=api.createSession({selection,request:async action=>{
  if(action==='handoff_token')return {ok:true,csrf};
  throw Object.assign(Error('PRIVATE_TV'),{handoffFailure:fact});
 }});
 assert.deepEqual(plain((await flow.prepare(snapshot)).failure),fact);
 const anex=setup(action=>action==='handoff_freeze'?Promise.reject(Object.assign(Error('PRIVATE_TV'),{handoffFailure:fact})):undefined);
 assert.deepEqual(plain((await anex.flow.prepare(anex.snapshot)).failure),{action:'handoff_freeze',stage:'transport',code:'handoff_unavailable'});
});
test('Only a complete allowlisted retained-field and owner pair survives the failure boundary',async()=>{
 for(const [code,field,owner,allowed] of [
  ['retained_fact_missing','stay.room','INT',true],['retained_fact_missing','trip.stayEnd','SEARCH',true],
  ['retained_fact_missing','handoff.session','anex_session',true],['forbidden','stay.room','INT',false],
  ['retained_fact_missing','stay.room',undefined,false],['retained_fact_missing',undefined,'INT',false],
  ['retained_fact_missing','trip.checkIn','INT',false],['retained_fact_missing','trip.checkOut','INT',false],
  ['retained_fact_missing','PRIVATE_FIELD','INT',false],['retained_fact_missing','stay.room','PRIVATE_OWNER',false]
 ]){
  const x=setup(action=>action==='handoff_freeze'?Promise.reject(Object.assign(Error('PRIVATE'),{handoffFailure:{
   action,stage:'http',code,httpStatus:422,field,owner,operation_id:id,raw:'PRIVATE_BODY'
  }})):undefined);
  const expected={action:'handoff_freeze',stage:'http',code,httpStatus:422,...(allowed?{field,owner}:{})};
  const result=await x.flow.prepare(x.snapshot);assert.deepEqual(plain(result.failure),expected);
  assert.doesNotMatch(JSON.stringify(result),/PRIVATE_|operation_id|checkIn|checkOut/);
 }
});
test('Malformed replies identify the local response boundary rather than a backend failure',async()=>{
 for(const action of ['handoff_token','handoff_freeze','handoff_create','handoff_status']){
  const x=setup(current=>current===action?{ok:true,data:{}}:undefined);
  let result=await x.flow.prepare(x.snapshot);
  if(action==='handoff_create')result=await x.flow.create(x.snapshot);
  if(action==='handoff_status')result=await x.flow.status(x.snapshot);
  assert.deepEqual(plain(result.failure),{action,stage:'response',code:'handoff_unavailable'});
 }
});
test('Failed token/freeze preparation survives same-selector reopen without another request',async()=>{
 for(const failedAction of ['handoff_token','handoff_freeze']){
  const x=setup(action=>action===failedAction?Promise.reject(Object.assign(Error('PRIVATE'),{handoffFailure:{action,stage:'transport',code:'handoff_unknown'}})):undefined);
  const first=await x.flow.prepare(x.snapshot),calls=plain(x.calls);
  assert.equal(first.state,'unavailable');
  assert.deepEqual(plain((await x.flow.prepare(x.snapshot)).failure),plain(first.failure));
  const reopen=x.selection.capture();assert.equal(x.selection.isCurrent(reopen),true);
  assert.deepEqual(plain((await x.flow.prepare(reopen)).failure),plain(first.failure));
  await x.flow.create(reopen);await x.flow.status(reopen);
  assert.deepEqual(plain(x.calls),calls,'reopen cannot retry token/freeze or fabricate CREATE/status');
  x.input.revision++;x.input.offer.raw={...x.input.offer.raw,anexGeneration:8};x.input.application={...x.input.application,generation:8};
  const next=x.selection.capture();await x.flow.prepare(next);
  assert.equal(x.calls.filter(c=>c.action===failedAction).length,2,'a genuinely different current selector may prepare');
 }
});
test('Conditional TV-select failure remains unavailable across exact-selector recapture',async()=>{
 const owner=harness.fixtureOwner,input=owner.tourvisorFixture(true),selection=owner.api.createSession(()=>input,owner.clock),calls=[];
 const flow=api.createSession({selection,request:async action=>{
  calls.push(action);if(action==='handoff_token')return {ok:true,csrf};
  throw Object.assign(Error('PRIVATE'),{handoffFailure:{action:'tv_select',stage:'transport',code:'handoff_unknown'}});
 }});
 const first=selection.capture();assert.equal((await flow.prepare(first)).failure.action,'tv_select');
 const reopen=selection.capture();assert.equal((await flow.prepare(reopen)).failure.action,'tv_select');
 assert.deepEqual(calls,['handoff_token','handoff_freeze']);
});
test('Failed preparation identities retain the eight-operation bound without eviction or replay',async()=>{
 const x=setup(action=>action==='handoff_token'?Promise.reject(Error('PRIVATE')):undefined);
 for(let generation=7;generation<15;generation++){
  x.input.offer.raw={...x.input.offer.raw,anexGeneration:generation};x.input.application={...x.input.application,generation};
  await x.flow.prepare(x.selection.capture());
 }
 assert.equal(x.calls.length,8);
 x.input.offer.raw={...x.input.offer.raw,anexGeneration:15};x.input.application={...x.input.application,generation:15};
 const blocked=await x.flow.prepare(x.selection.capture());
 assert.deepEqual(plain(blocked.failure),{action:'handoff_freeze',stage:'request',code:'handoff_limit'});assert.equal(x.calls.length,8);
 x.input.offer.raw={...x.input.offer.raw,anexGeneration:7};x.input.application={...x.input.application,generation:7};
 assert.equal((await x.flow.prepare(x.selection.capture())).failure.action,'handoff_token');assert.equal(x.calls.length,8);
});
test('The failed-preparation bound does not block reopening an already-known successful operation',async()=>{
 const x=setup(action=>action==='handoff_freeze'?{ok:true,data:{
  operation_id:x.input.application.generation.toString(16).padStart(32,'0'),state:'prepared',observation:plain(x.selection.capture())
 }}:undefined);
 for(let generation=7;generation<15;generation++){
  x.input.offer.raw={...x.input.offer.raw,anexGeneration:generation};x.input.application={...x.input.application,generation};
  assert.equal((await x.flow.prepare(x.selection.capture())).state,'prepared');
 }
 x.input.offer.raw={...x.input.offer.raw,anexGeneration:7};x.input.application={...x.input.application,generation:7};
 assert.equal((await x.flow.prepare(x.selection.capture())).state,'prepared');
});
(async()=>{
  for(const {name,fn} of tests){await fn();console.log('PASS '+name);}
  assert.deepEqual(io,[]);assert.equal(harness.fixtureOwner.existingCasesRun,0);
  console.log(JSON.stringify({groups:tests.length,result:'PASS',oldContextCasesRun:0,supplierRequests:0,botMessages:0,storageWrites:0,runtimeConnected:false}));
})().catch(error=>{console.error(error);process.exitCode=1;});
