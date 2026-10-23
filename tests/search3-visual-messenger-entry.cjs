'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const app=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const start=app.indexOf('let messengerOwners=null,messengerUi=null;'),end=app.indexOf('function openAnexApplicationPreview(){',start);assert.ok(start>=0&&end>start);
let fixtures=fs.readFileSync(path.join(__dirname,'search3-messenger-context.cjs'),'utf8');const runner="function test(name,fn){fn();count++;console.log('PASS '+name);}";assert.ok(fixtures.includes(runner));
fixtures=fixtures.replace(runner,'function test(){}');fixtures=fixtures.slice(0,fixtures.lastIndexOf('\nconsole.log('));
const old=vm.createContext({require,__dirname,structuredClone,console:{log(){throw Error('old baseline ran');}}});vm.runInContext(fixtures+'\nglobalThis.owner={applicationFixture,api,existingCasesRun:count};',old);assert.equal(old.owner.existingCasesRun,0);
const flowRoot={};vm.runInNewContext(fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/messenger-flow-v1.js'),'utf8'),{window:flowRoot,URL});
const plain=x=>JSON.parse(JSON.stringify(x)),id='f'.repeat(32),csrf='a'.repeat(64),links={telegram:'https://t.me/Any_tour_bot?start=fixture-only',max:'https://max.ru/id9704048781_bot?start=fixture-only'};
const defer=()=>{let resolve,reject;const promise=new Promise((yes,no)=>{resolve=yes;reject=no;});return {promise,resolve,reject};};
async function spin(check){for(let i=0;i<150;i++){if(check())return;await Promise.resolve();}throw Error('fixture did not settle');}
function setup(route){
  const input=old.owner.applicationFixture(true,'anex_quote:'+'c'.repeat(64)),calls=[],navigation=[],buttons=[{disabled:true},{disabled:true}],submit={hidden:true},phoneInput={value:'',focuses:0,focus(){this.focuses++;}},nodes={
    '#messenger-methods':{hidden:false},'#messenger-phone':{hidden:true},'#messenger-status':{textContent:''},'#messenger-check':{hidden:true,disabled:false},'[name="phone"]':phoneInput};
  const scope={isConnected:true,querySelector:key=>nodes[key],querySelectorAll:()=>buttons},modal={open:true},body={scrollTop:0};
  const retained={type:'anex-quote',choice:input.application.choiceRef,pending:false,error:'',sealed:false,result:{state:'quote_verified',finalPrice:{amount:input.application.price}}};
  let ctx,observation;
  const window={V2_CONFIG:{search3MessengerEnabled:true},AnyTourSearch3MessengerContextV1:old.owner.api,AnyTourSearch3MessengerFlowV1:flowRoot.AnyTourSearch3MessengerFlowV1,
    AnyTourPrototypeLead:{markup:()=>'<form id="prototype-lead-form"><input name="phone" required></form>',action:()=>'<button type="submit" form="prototype-lead-form">Проверить заявку</button>'},location:{assign:url=>navigation.push(url)}};
  const data={live:true,messengerHandoff:async(action,fields)=>{
    calls.push({action,fields:plain(fields||{})});if(route){const reply=route(action,fields);if(reply!==undefined)return reply;}
    if(action==='handoff_token')return {ok:true,csrf};
    if(action==='handoff_freeze'){observation=observation||plain(ctx.test.snapshot());return {ok:true,data:{operation_id:id,state:'prepared',observation}};}
    return {ok:true,data:{operation_id:id,state:'ready',links}};
  }};
  ctx=vm.createContext({window,data,selectedOffer:input.offer,anexApplicationDraft:input.application,selectionGeneration:input.revision,modalType:'anex-application',
    selectedTourHotel:()=>input.hotel,retainedProviderView:()=>retained,$:key=>key==='#modal'?modal:key==='#modal-body'?body:key==='#messenger-entry'?scope:key==='[data-messenger-phone-submit]'?submit:null});
  vm.runInContext(app.slice(start,end)+'\nglobalThis.test={available:messengerAvailable,markup:messengerContactHTML,bind:bindMessenger,open:messengerOpen,phone:messengerPhone,check:messengerCheck,snapshot:()=>messengerUi?.snapshot};',ctx);
  return {ctx,window,data,input,calls,navigation,buttons,submit,phoneInput,nodes,scope,modal,body,retained,test:ctx.test,settled:()=>spin(()=>buttons.every(b=>!b.disabled))};
}
const tests=[];const test=(name,fn)=>tests.push({name,fn});
test('Activation defaults off and unsupported receipts retain the existing phone owner',async()=>{
  const x=setup();delete x.window.V2_CONFIG.search3MessengerEnabled;assert.equal(x.test.available(x.input.application),false);assert.equal(x.test.markup(x.input.application),x.window.AnyTourPrototypeLead.markup());
  x.window.V2_CONFIG.search3MessengerEnabled=true;assert.equal(x.test.available({...x.input.application,finalPriceVerified:false}),false);assert.equal(x.calls.length,0);
});
test('Contact entry only freezes; three method buttons precede the hidden phone form',async()=>{
  const x=setup(),html=x.test.markup(x.input.application);assert.match(html,/Telegram/);assert.match(html,/>MAX</);assert.match(html,/По телефону/);
  assert.ok(html.indexOf('data-action="messenger-phone"')<html.indexOf('<form'));assert.match(html,/<div id="messenger-phone" hidden>/);
  x.test.bind();await x.settled();assert.deepEqual(x.calls.map(c=>c.action),['handoff_token','handoff_freeze']);assert.equal(x.navigation.length,0);assert.equal(x.submit.hidden,true);assert.equal(x.nodes['#messenger-phone'].hidden,true);assert.equal(x.phoneInput.focuses,0);
});
test('An explicit bot choice creates once and navigates with the current platform link without phone',async()=>{
  const x=setup();x.test.bind();await x.settled();await x.test.open('max');assert.deepEqual(x.navigation,[links.max]);assert.equal(x.calls.filter(c=>c.action==='handoff_create').length,1);assert.equal(x.phoneInput.value,'');assert.equal(x.phoneInput.focuses,0);
});
test('Explicit phone choice retains the same draft nodes and only then exposes submit',async()=>{
  const x=setup();x.test.bind();await x.settled();x.test.phone(true);assert.equal(x.nodes['#messenger-methods'].hidden,true);assert.equal(x.nodes['#messenger-phone'].hidden,false);assert.equal(x.submit.hidden,false);
  x.phoneInput.value='fixture draft';const node=x.phoneInput;x.body.scrollTop=127;x.test.phone(false);assert.equal(x.body.scrollTop,0,'return to methods shows the full choice heading');x.test.phone(true);assert.equal(x.phoneInput,node);assert.equal(x.phoneInput.value,'fixture draft');assert.equal(x.calls.some(c=>c.action==='handoff_create'),false);
});
test('Switching to phone and back during a pending create cancels its navigation intent',async()=>{
  const gate=defer(),x=setup(action=>action==='handoff_create'?gate.promise:undefined);x.test.bind();await x.settled();const task=x.test.open('telegram');await spin(()=>x.calls.some(c=>c.action==='handoff_create'));
  x.test.phone(true);x.test.phone(false);gate.resolve({ok:true,data:{operation_id:id,state:'ready',links}});await task;assert.equal(x.navigation.length,0);
});
test('A delayed result cannot navigate after conditions change or the modal closes',async()=>{
  for(const invalidate of [x=>x.ctx.selectionGeneration++,x=>x.modal.open=false]){
    const gate=defer(),x=setup(action=>action==='handoff_create'?gate.promise:undefined);x.test.bind();await x.settled();const task=x.test.open('telegram');await spin(()=>x.calls.some(c=>c.action==='handoff_create'));invalidate(x);
    gate.resolve({ok:true,data:{operation_id:id,state:'ready',links}});await task;assert.equal(x.navigation.length,0);
  }
});
test('A lost create response offers status and never repeats create',async()=>{
  const x=setup(action=>action==='handoff_create'?Promise.reject(Error('timeout')):undefined);x.test.bind();await x.settled();await x.test.open('telegram');assert.equal(x.navigation.length,0);assert.equal(x.nodes['#messenger-check'].hidden,false);assert.ok(x.buttons.every(b=>b.disabled));
  await x.test.check();assert.ok(x.buttons.every(b=>!b.disabled));await x.test.open('telegram');assert.deepEqual(x.navigation,[links.telegram]);assert.deepEqual(x.calls.map(c=>c.action),['handoff_token','handoff_freeze','handoff_create','handoff_status']);
});
test('Mismatched canonical review cannot enable a bot or create a capability',async()=>{
  let x; x=setup(action=>action==='handoff_freeze'?{ok:true,data:{operation_id:id,state:'prepared',observation:{...plain(x.ctx.test.snapshot()),hotel:{uiId:3634,name:'Other hotel'}}}}:undefined);
  x.test.bind();await spin(()=>x.nodes['#messenger-status'].textContent.includes('Условия тура изменились'));await x.test.open('telegram');assert.ok(x.buttons.every(b=>b.disabled));assert.equal(x.navigation.length,0);assert.equal(x.calls.some(c=>c.action==='handoff_create'),false);
});
test('Pending, sealed and foreign retained views do not make a dispatcher request',async()=>{
  for(const change of [x=>x.retained.pending=true,x=>x.retained.sealed=true,x=>x.retained.choice='foreign']){
    const x=setup();change(x);x.test.bind();await Promise.resolve();assert.equal(x.calls.length,0);assert.equal(x.navigation.length,0);assert.equal(x.submit.hidden,true);
  }
});
test('The real graph and existing action dispatcher connect the reviewed owners without changing lead code',async()=>{
  const index=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/index.php'),'utf8');for(const name of ['messenger-context-v1.js','messenger-flow-v1.js'])assert.ok(index.includes(name));
  for(const action of ['messenger-open','messenger-phone','messenger-other','messenger-check'])assert.ok(app.includes("case '"+action+"':"));
  const footer=app.slice(app.indexOf('function providerApplicationFooterHTML('),app.indexOf('\nfunction renderRealOffer'));
  assert.match(footer,/data-messenger-phone-submit hidden/);assert.match(footer,/window\.AnyTourPrototypeLead\.action\(\)/);
});
(async()=>{for(const {name,fn} of tests){await fn();console.log('PASS '+name);}assert.equal(old.owner.existingCasesRun,0);console.log(JSON.stringify({groups:tests.length,result:'PASS',contextBaselineRun:0,supplierRequests:0,botMessages:0,nativeNavigation:'intercepted-function-fixture',physicalAcceptance:false}));})().catch(e=>{console.error(e);process.exitCode=1;});
