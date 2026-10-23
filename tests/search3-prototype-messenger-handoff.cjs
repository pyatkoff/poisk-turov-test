'use strict';
// Actual canonical dispatcher functions, synthetic fetch only; no supplier/bot IO.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/data.js'),'utf8');
const nativeStart=source.indexOf('  function nativeEndpoint('),nativeEnd=source.indexOf('\n  const anexSearchFailureCodes',nativeStart);
const start=source.indexOf('  async function messengerHandoff('),end=source.indexOf('  async function verifyAnexFollowUp(',start);
assert.ok(nativeStart>=0&&nativeEnd>nativeStart&&start>=0&&end>start);
assert.match(source,/root\.AnyTourPrototypeData=Object\.freeze\(\{[^\n]*\bmessengerHandoff\b/);
const endpoint='https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php',csrf='a'.repeat(64),operation_id='b'.repeat(32);
const selector={generation:7,search_ref:'c'.repeat(32),offer_ref:'anex_online:'+'d'.repeat(64),local_hotel_id:3634,choice_ref:'anex_quote:'+'e'.repeat(64),csrf};
function setup(handler){
  const calls=[],timers=new Map(),cleared=[],io=[];let timerId=0;
  const root={location:{href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/',origin:'https://anytoour.ru'},V2_CONFIG:{anexApi:endpoint}};
  const ctx=vm.createContext({root,URL,URLSearchParams,AbortController,TextEncoder,
    setTimeout(fn,ms){const id=++timerId;timers.set(id,{fn,ms});return id;},clearTimeout(id){cleared.push(id);timers.delete(id);},
    fetch:async(url,options)=>{calls.push({url,options});if(handler)return handler(url,options);return {ok:true,text:async()=>JSON.stringify(options.method==='GET'?{ok:true,csrf}:{ok:true,data:{operation_id,state:'prepared'}})};},
    document:new Proxy({},{get(){io.push('DOM');throw Error('unexpected DOM');}}),localStorage:new Proxy({},{get(){io.push('storage');throw Error('unexpected storage');}})});
  vm.runInContext(source.slice(nativeStart,nativeEnd)+source.slice(start,end)+'\nglobalThis.dispatch=messengerHandoff;',ctx);
  return {root,dispatch:ctx.dispatch,calls,timers,cleared,io};
}
const plain=x=>JSON.parse(JSON.stringify(x)),tests=[];const test=(name,fn)=>tests.push({name,fn});
test('Token uses the exact same-origin GET with private credential/cache/referrer policy',async()=>{
  const x=setup(),result=await x.dispatch('handoff_token');assert.deepEqual(plain(result),{ok:true,csrf});assert.equal(Object.isFrozen(result),true);
  assert.equal(x.calls.length,1);const {url,options}=x.calls[0];assert.equal(url,endpoint+'?action=handoff_token');assert.equal(options.method,'GET');assert.equal(options.body,undefined);
  for(const [key,value] of Object.entries({credentials:'same-origin',mode:'same-origin',cache:'no-store',redirect:'error',referrerPolicy:'no-referrer'}))assert.equal(options[key],value);
  assert.equal(x.cleared.length,1);assert.equal(x.timers.size,0);assert.deepEqual(x.io,[]);
});
test('Freeze sends only the accepted exact selector and token, with no browser observation or price',async()=>{
  const x=setup();await x.dispatch('handoff_freeze',selector);const {url,options}=x.calls[0];assert.equal(url,endpoint);assert.equal(options.method,'POST');
  assert.deepEqual(JSON.parse(options.body),{action:'handoff_freeze',...selector});assert.equal(options.headers['Content-Type'],'application/json');assert.equal(options.headers['X-Requested-With'],'AnyTourSearch3');
  assert.doesNotMatch(options.body,/"(?:amount|price|observation|phone|party|signature|key_id|url)"/);
});
test('Create/status/revoke retain the exact original operation body',async()=>{
  for(const action of ['handoff_create','handoff_status','handoff_revoke']){
    const x=setup();await x.dispatch(action,{operation_id,csrf});assert.equal(x.calls.length,1);assert.deepEqual(JSON.parse(x.calls[0].options.body),{action,operation_id,csrf});
  }
});
test('Unknown actions, extra fields, malformed selector/token and arbitrary destinations never fetch',async()=>{
  const variants=[['create',{}],['toString',{}],['__proto__',{}],['handoff_token',{url:endpoint}],['handoff_create',{operation_id,csrf,observation:{}}],['handoff_freeze',{...selector,choice_ref:'0'}],['handoff_freeze',{...selector,generation:'7'}],['handoff_freeze',{...selector,local_hotel_id:0}],['handoff_create',{operation_id,csrf:'bad'}],['handoff_status',[]]];
  for(const [action,fields] of variants){const x=setup();await assert.rejects(x.dispatch(action,fields),e=>e.code==='invalid_request');assert.equal(x.calls.length,0);assert.equal(x.timers.size,0);}
});
test('Existing native endpoint owner blocks foreign origin/path/query/hash and URL credentials',async()=>{
  for(const url of [endpoint.replace('anytoour.ru','evil.test'),endpoint.replace('api-anex-search3-preview.php','other.php'),endpoint+'?x=1',endpoint+'#x',endpoint.replace('https://','https://user:pass@')]){
    const x=setup();x.root.V2_CONFIG.anexApi=url;await assert.rejects(x.dispatch('handoff_token'),e=>e.code==='handoff_unavailable');assert.equal(x.calls.length,0);
  }
});
test('Allowed server failures retain only a fixed code, without private diagnostics',async()=>{
  const x=setup(()=>({ok:false,text:async()=>JSON.stringify({ok:false,error:'handoff_stale',detail:'PRIVATE_RECEIPT_REF'})}));
  await assert.rejects(x.dispatch('handoff_create',{operation_id,csrf}),e=>e.code==='handoff_stale'&&!e.message.includes('PRIVATE'));assert.equal(x.calls.length,1);assert.equal(x.timers.size,0);
  const y=setup(()=>({ok:false,text:async()=>JSON.stringify({ok:false,error:'PRIVATE_RECEIPT_REF'})}));await assert.rejects(y.dispatch('handoff_status',{operation_id,csrf}),e=>e.code==='handoff_unavailable'&&!e.message.includes('PRIVATE'));
});
test('Lost create response has one attempt and no automatic retry or new freeze',async()=>{
  const x=setup(()=>{throw Error('PRIVATE_TRANSPORT_DIAGNOSTIC');});await assert.rejects(x.dispatch('handoff_create',{operation_id,csrf}),e=>e.code==='handoff_unknown'&&!e.message.includes('PRIVATE'));
  assert.equal(x.calls.length,1);assert.equal(x.timers.size,0);assert.deepEqual(x.io,[]);
});
test('Malformed, oversized UTF-8 and incomplete successful responses are unavailable',async()=>{
  for(const raw of ['not-json',JSON.stringify({ok:true,data:{text:'я'.repeat(18000)}}),JSON.stringify({ok:true,data:[]}),JSON.stringify({ok:true})]){
    const x=setup(()=>({ok:true,text:async()=>raw}));await assert.rejects(x.dispatch('handoff_status',{operation_id,csrf}),e=>e.code==='handoff_unavailable');assert.equal(x.calls.length,1);assert.equal(x.timers.size,0);
  }
  const x=setup(()=>({ok:true,text:async()=>JSON.stringify({ok:true,csrf:'bad'})}));await assert.rejects(x.dispatch('handoff_token'),e=>e.code==='handoff_unavailable');
});
test('The 15s request deadline aborts its own request and clears the timer without replay',async()=>{
  const x=setup((url,options)=>new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(Error('aborted'))))),pending=x.dispatch('handoff_create',{operation_id,csrf});
  assert.equal(x.timers.size,1);const timer=[...x.timers.values()][0];assert.equal(timer.ms,15000);timer.fn();
  await assert.rejects(pending,e=>e.code==='handoff_unknown');assert.equal(x.calls[0].options.signal.aborted,true);assert.equal(x.calls.length,1);assert.equal(x.timers.size,0);
});
(async()=>{for(const {name,fn} of tests){await fn();console.log('PASS '+name);}console.log(JSON.stringify({groups:tests.length,result:'PASS',supplierRequests:0,botMessages:0,storageWrites:0,runtimeConnected:false}));})().catch(e=>{console.error(e);process.exitCode=1;});
