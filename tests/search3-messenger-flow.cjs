'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
// Reuse existing canonical UI receipt fixtures and the actual context owner.
// Register, but do not execute, the previously accepted 27 context cases.
let fixtures=fs.readFileSync(path.join(__dirname,'search3-messenger-context.cjs'),'utf8');
const runner="function test(name,fn){fn();count++;console.log('PASS '+name);}";
assert.ok(fixtures.includes(runner));fixtures=fixtures.replace(runner,'function test(){}');
fixtures=fixtures.slice(0,fixtures.lastIndexOf('\nconsole.log('));
const harness=vm.createContext({require,__dirname,structuredClone,console:{log(){throw Error('old baseline executed');}}});
vm.runInContext(fixtures+'\nglobalThis.fixtureOwner={applicationFixture,api,existingCasesRun:count,setClock:value=>time=value,clock:()=>time};',harness);
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
(async()=>{
  for(const {name,fn} of tests){await fn();console.log('PASS '+name);}
  assert.deepEqual(io,[]);assert.equal(harness.fixtureOwner.existingCasesRun,0);
  console.log(JSON.stringify({groups:tests.length,result:'PASS',oldContextCasesRun:0,supplierRequests:0,botMessages:0,storageWrites:0,runtimeConnected:false}));
})().catch(error=>{console.error(error);process.exitCode=1;});
