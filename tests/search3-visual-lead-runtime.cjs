'use strict';
// Compare the real canonical lead implementation with its generated NEXT slice.
// All HTTP, forms and lifecycle events are intercepted; no supplier or lead I/O.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const {owners,target,generate,verify,project,parse,references}=require('../scripts/build/search3-js/lead-runtime.cjs');
const root=path.resolve(__dirname,'../v2'),read=f=>fs.readFileSync(path.join(root,f),'utf8');
const code=verify(root),php=read('visual-search/index.php'),html=read('visual-search/index.html');
const live=[...php.match(/\$scripts\s*=\s*\[([\s\S]*?)\];/)[1].matchAll(/'([^']+\.js)'/g)].map(m=>path.posix.normalize(path.posix.join('visual-search',m[1])));
assert(live.includes(target));owners.forEach(o=>assert(!live.includes(o.file),'legacy UI is not loaded by NEXT'));
assert.equal(live.filter(f=>f===target).length,1);
const allowed={V2TourController:['createLeadSession','createProviderLeadSession'],V2LeadFormGuard:['validatePhone']};
function property(n){return n.computed?n.property.type==='Literal'?n.property.value:null:n.property.name;}
function consumerAPIs(source){
 const found=[];
 function walk(n,parent,key){
  if(!n||typeof n!=='object')return;
  if(n.type==='MemberExpression'&&Object.hasOwn(allowed,property(n))){
   const owner=property(n);assert(parent?.type==='MemberExpression'&&key==='object','no dynamic/aliased legacy controller consumer');
   const member=property(parent);assert(allowed[owner].includes(member),'unsupported NEXT lead API: '+owner+'.'+member);found.push(owner+'.'+member);
  }
  for(const[k,v]of Object.entries(n)){if(Array.isArray(v))v.forEach(child=>walk(child,n,k));else if(v&&typeof v==='object')walk(v,n,k);}
 }walk(parse(source));return found;
}
const cold=[...html.matchAll(/data-(?:offer-list|hotel-details|flight-picker)-src="([^"?]+\.js)/g)].map(m=>path.posix.normalize(path.posix.join('visual-search',m[1])));
const calls=[];
for(const file of new Set([...live,...cold]))if(file!==target){
 const source=read(file);calls.push(...consumerAPIs(source));
 assert(!source.includes('.direct-tour')&&!source.includes('#selectedTour'),'native NEXT has no legacy selected-tour DOM consumer');
}
assert(!html.includes('id="selectedTour"')&&!html.includes('class="direct-tour"'));
assert.deepEqual([...new Set(calls)].sort(),['V2LeadFormGuard.validatePhone','V2TourController.createLeadSession','V2TourController.createProviderLeadSession']);
assert.throws(()=>consumerAPIs('window.V2TourController.selectTour(1)'),/unsupported/);
assert.throws(()=>consumerAPIs('const owner=window.V2TourController;'),/aliased/);
const top=new Set(['outside','hidden']);
assert.deepEqual([...references(parse('(function(hidden){return outside+hidden;})').body[0],top)],['outside']);
assert.deepEqual([...references(parse('(function(){outside();{let outside;outside();}return obj.hidden;})').body[0],top)],['outside']);
assert.throws(()=>project(read(owners[0].file).replace('if(!rt)return;','if(!rt)throw Error();'),owners[0]),/availability/);
assert.throws(()=>project(read(owners[0].file).replace('function createLeadSession(value){','function createLeadSession(value){eval("1");'),owners[0]),/Dynamic/);
// Canonical bodies, including payload/attribution/transport, remain source-owned.
assert.equal(generate(root),code);assert(Buffer.byteLength(code)<12000,'bounded dependency slice, not another full UI bundle');
const referenceFlag=process.argv.indexOf('--reference-root');
const referenceRoot=referenceFlag<0?root:path.resolve(process.argv[referenceFlag+1]);
const canonical=owners.map(o=>fs.readFileSync(path.join(referenceRoot,o.file),'utf8')).join('\n');
const enrich=fs.readFileSync(path.join(referenceRoot,'lead-search-context.js'),'utf8');
const plain=value=>JSON.parse(JSON.stringify(value,(key,v)=>v instanceof Error?{name:v.name,message:v.message}:v));
function setup(source,missingRuntime=false){
 const events=[],requests=[],listeners=[],pending=[];
 const form={dataset:{},values:{name:'Иван <&',phone:'+7 (999) 123-45-67',comment:'Семья & отдых',consent:'1'},button:{disabled:false,textContent:'Отправить'},message:{textContent:''},querySelector(s){return s==='button[type="submit"]'?this.button:s==='.lead-message'?this.message:null;}};
 const location={href:'https://example.invalid/_preview/search3-next-candidate/visual-search/?yclid=0123&utm_source=direct&utm_campaign=test',search:'?yclid=0123&utm_source=direct&utm_campaign=test'};
 const window={V2_CONFIG:{leadApi:'/preview-lead-disabled.php'},V2Runtime:missingRuntime?null:{state:{searchId:45},api:()=>{throw Error('supplier API forbidden');}},V2SearchLifecycle:{snapshot:{adults:2,childs:[0,17]}},location,
  fetch:(url,options)=>{requests.push({url,options:plain(options)});return new Promise((resolve,reject)=>pending.push({resolve,reject}));},
  addEventListener:(name,fn)=>listeners.push([name,fn]),dispatchEvent:e=>{events.push([e.type,plain(e.detail)]);for(const[name,fn]of listeners)if(name===e.type)fn(e);}};
 const document={cookie:'_ym_uid=987; broken=%E0%A4%A; eq=a=b',querySelector:s=>s==='#tourSearch input[name="sessid"]'?{value:'session-fixture'}:null,getElementById:()=>null,addEventListener:(name,fn)=>listeners.push([name,fn])};
 const sandbox={window,document,location,URL,URLSearchParams,structuredClone,Promise,Error,CustomEvent:class{constructor(type,init){this.type=type;this.detail=init.detail;}},FormData:class{constructor(f){this.values=f.values;}get(k){return this.values[k]??null;}}};
 vm.createContext(sandbox);vm.runInContext(enrich+'\n'+source,sandbox);
 assert.equal(requests.length,0,'bootstrap has no HTTP');
 return {window,sandbox,form,events,requests,listeners,pending,fd:()=>new sandbox.FormData(form)};
}
function value(flight=true){return{tour:{id:'tour-exact',hotel:{name:'Hotel <&',country:{name:'Турция'},region:{name:'Анталья'}},departure:{name:'Москва'},date:'2026-10-14',nights:7,adults:2,childs:2,meal:{russianName:'Всё включено'},roomType:'Standard <&',placement:'2+2',operator:{name:'Operator'},price:133500.5},searchId:45,search:{adults:2,childs:[0,17]},flight:flight?{forward:[{number:'XY 123',departure:{date:'2026-10-14',time:'10:30',port:{name:'Москва',id:'MOW'}},arrival:{date:'2026-10-14',time:'14:00',port:{name:'Анталья',id:'AYT'}},baggage:20,carryOn:'5 кг'}],backward:[],price:{value:133500.5},fuelCharge:{value:0}}:null};}
const successful={ok:true,writes:1,leadId:72};
function response(kind){return {ok:kind!=='http-error',json:()=>kind==='invalid-json'?Promise.reject(Error('JSON')):Promise.resolve(kind==='duplicate'?{ok:true,duplicate:true,leadId:72}:kind==='not-written'?{ok:true}:kind==='missing-provider-id'?{ok:true,writes:1}:successful)};}
async function observe(source,provider,kind,flight){
 const f=setup(source),v=value(flight),original=plain(v);let current=true,currentCalls=0;
 const session=provider==='tourvisor'?f.window.V2TourController.createLeadSession(v):f.window.V2TourController.createProviderLeadSession({provider,offerRef:'exact-offer',current(){currentCalls++;if(!current)throw Error('stale provider');},payload(fd){return{provider,providerOfferRef:'exact-offer',tourId:provider+':exact-offer',price:133500.5,flightFuel:null,childAges:[0,17],phone:fd.get('phone'),consent:fd.get('consent')==='1'};}});
 assert(Object.isFrozen(session));const payload=plain(session.payload(f.fd()));
 if(provider==='tourvisor'){v.tour.price=1;assert.deepEqual(plain(session.payload(f.fd())),payload,'captured selection cannot be mutated by caller');v.tour.price=original.tour.price;}
 const first=session.submit(f.form,{button:f.form.button}),second=session.submit(f.form,{button:f.form.button});
 assert.strictEqual(first,second,'overlapping submits retain one pending promise');assert.equal(f.requests.length,1);
 if(kind==='network')f.pending.shift().reject(Error('network'));else f.pending.shift().resolve(response(kind));
 const sent=await first;assert.equal(await second,sent);
 if(sent){assert.equal(await session.submit(f.form,{button:f.form.button}),true);assert.equal(f.requests.length,1,'sent receipt prevents another HTTP request');}
 else{const retry=session.submit(f.form,{button:f.form.button});assert.equal(f.requests.length,2);f.pending.shift().resolve(response('success'));await retry;}
 f.window.V2Runtime.state.searchId=46;current=false;
 assert.throws(()=>session.payload(f.fd()),/изменились|stale/);
 if(provider==='tourvisor')assert.throws(()=>session.submit(f.form,{}),/изменились/);
 assert.deepEqual(v,original,'caller-owned source objects retained');
 return plain({payload,requests:f.requests,events:f.events,sent,currentCalls,form:{dataset:f.form.dataset,button:f.form.button,message:f.form.message}});
}
(async()=>{
 let cases=0;
 for(const provider of ['tourvisor','anex','andromeda'])for(const kind of ['success','duplicate','http-error','network','invalid-json','not-written','missing-provider-id'])for(const flight of [false,true]){
  assert.deepEqual(await observe(code,provider,kind,flight),await observe(canonical,provider,kind,flight),provider+'/'+kind+'/'+flight);cases++;
 }
 const before=setup(canonical),after=setup(code);assert.equal(after.listeners.length,0,'NEXT slice installs no legacy UI listeners');assert(before.listeners.length>0);
 for(const raw of ['', '   ','123456789','1234567890','123456789012345','1234567890123456','+7 (999) 123-45-67','letters','0'.repeat(11),'１２３４５６７８９０']){
  function check(f){const writes=[],input={value:raw,setCustomValidity:v=>writes.push(v)};return [f.window.V2LeadFormGuard.validatePhone(input),writes];}
  assert.deepEqual(check(after),check(before));
 }
 assert.equal(after.window.V2LeadFormGuard.validatePhone(null),before.window.V2LeadFormGuard.validatePhone(null));
 for(const mutate of [v=>null,v=>({}),v=>({...v,searchId:0}),v=>({...v,searchId:1.5}),v=>({...v,search:null}),v=>({...v,tour:{...v.tour,cachedListing:true}}),v=>({...v,tour:{...v.tour,provider:'anex'}})]){
  const error=f=>{try{f.window.V2TourController.createLeadSession(mutate(value()));return null;}catch(e){return [e.name,e.message];}};
  assert.deepEqual(error(after),error(before));
 }
 assert.equal(setup(canonical,true).window.V2TourController,undefined);assert.equal(setup(code,true).window.V2TourController,undefined);
 console.log(`PASS NEXT lead dependency slice: ${cases} canonical transport/payload/event/session scenarios; phone/mutation/stale/consumer guards; legacy listeners ${before.listeners.length}->0; external supplier/lead HTTP 0`);
})().catch(error=>{console.error(error);process.exitCode=1;});
