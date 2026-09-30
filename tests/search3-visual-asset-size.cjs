'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto'),vm=require('node:vm');
const {compile,privateFunctionBindings}=require('../scripts/build/search3-js/visual-entry.cjs');
const {parsed}=require('../scripts/build/search3-js/compact.cjs');
const root=path.resolve(process.argv[2]||'v2'),manifest=JSON.parse(fs.readFileSync(path.join(root,'visual-search/asset-size.json')));
const hash=s=>crypto.createHash('sha256').update(s).digest('hex');
for(const file of manifest.files){
 const code=fs.readFileSync(path.join(root,file.target));assert.equal(hash(code),file.sha256,file.target+' exact served bytes');
 assert.equal(code.length,file.served);assert(file.served<=file.raw,'no raw growth');new vm.Script(code.toString(),{filename:file.target});
}
assert(manifest.totals.live.gzip_after<manifest.totals.live.gzip_before*0.90,'measurable initial transfer reduction');
assert.deepEqual(manifest.graphs.live,require('../scripts/build/search3-js/visual-entry.cjs').inventory(root).live,'source order/dependencies unchanged');
assert.deepEqual(manifest.graphs.ondemand,['./offer-list-v1.js','./hotel-details-v1.js']);
for(const src of manifest.graphs.ondemand)assert(!manifest.graphs.live.includes(src)&&!manifest.graphs.offline.includes(src),'cold owner is absent from both initial graphs');
for(const name of ['live','offline'])for(const key of ['raw','served','gzip_before','gzip_after'])assert.equal(manifest.totals['complete_'+name][key],manifest.totals[name][key]+manifest.totals.ondemand[key],'full-load bytes include the cold owner');
// Exercise syntax-sensitive cases independently from the application fixtures:
// global/API names, function.name/length, numeric and Unicode keys, __proto__,
// side-effect order, signed zero, direct eval, labels and tagged template raw text.
const probe=String.raw`/*! Copyright AnyTour */
var publicValue=7;var result;
(function(){
 let calls=[];function api(first,second){calls.push(first);return second;}
 const key='__proto__',short={ [key]:23 },plain={__proto__:null,'4':'numeric','Питание':'AI'};
 const tagged=(parts,...values)=>[parts.raw,parts,values];
 const raw=tagged\`escaped\\n\${publicValue}\`;
 let observed='';outer:for(let n=0;n<3;n++){if(n===1)continue outer;observed+=n;}
 function evalScope(){let named=41;return eval('named+1');}
 result={api:api(1,2),calls,name:api.name,arity:api.length,keys:Object.keys(plain),short:short[key],proto:Object.getPrototypeOf(plain)===null,negative:Object.is(-0,-0),raw,observed,eval:evalScope(),publicValue};
})();`;
(async()=>{
 // Keep the literal tagged template in this test source despite outer quoting.
 const original=probe.replaceAll('\\`','`').replaceAll('\\${','${');const output=await compile(original);
 function run(code){const context={};vm.runInNewContext(code,context);return JSON.parse(JSON.stringify({result:context.result,publicValue:context.publicValue}));}
 assert.deepEqual(run(output),run(original),'compiler preserves observable JS semantics');assert(output.includes('Copyright AnyTour'),'legal notice retained');
 assert.equal(await compile(original),output,'deterministic output');
 // Names observable through public values or reflection remain intact. Only
 // immediate-IIFE declarations referenced exclusively as callees are eligible.
 const privateProbe=`var result,publicApi;(function(){
 function privateOperation(a,b){return a+b;}
 function publicOperation(a){return privateOperation(a,3);}
 function observedOperation(a){return a;}
 const callback=observedOperation;
 result={value:publicOperation(4),callback:callback(5),name:callback.name,arity:callback.length};
 publicApi={publicOperation};})();`;
 const names=code=>privateFunctionBindings(parsed(code).tree);
 assert.deepEqual(names(privateProbe),['privateOperation'],'only unobserved, nonescaping callee is eligible');
 const compact=await compile(privateProbe,{privateFunctions:true});
 assert.deepEqual(run(compact),run(privateProbe),'private shortening preserves returned values and observed API names/arity');
 const apiContext={};vm.runInNewContext(compact,apiContext);assert.equal(apiContext.publicApi.publicOperation.name,'publicOperation');assert.equal(apiContext.publicApi.publicOperation.length,1);
 assert(!compact.includes('function privateOperation('),'private declaration shortened');
 assert(compact.includes('function publicOperation(')&&compact.includes('function observedOperation('),'escaping/observed names retained');
 assert.equal(await compile(privateProbe,{privateFunctions:true}),compact,'private output deterministic');
 assert((await compile(privateProbe)).includes('function privateOperation('),'default name contract unchanged');
 assert((await compile(privateProbe,{privateFunctions:true,preserveFunctions:['privateOperation']})).includes('function privateOperation('),'observed instrumentation name remains available');
 for(const observation of ['privateOperation.name','privateOperation.length','privateOperation.toString()','publicApi=privateOperation','[privateOperation]','{privateOperation}','privateOperation.bind(null)','Reflect.get(privateOperation,"name")']){
  assert.deepEqual(names(privateProbe.replace('result={value:',observation+';result={value:')),[],'observation/escape excludes private binding: '+observation);
 }
 for(const dynamic of ['eval("privateOperation.name")','window["eval"]("privateOperation.name")','Function("return 1")()','callback.caller','callback.callee','new Error().stack']){
  assert.deepEqual(names(privateProbe.replace('result={value:',dynamic+';result={value:')),[],'dynamic lookup/reflection bails out: '+dynamic);
 }
 assert.deepEqual(names(privateProbe.replace('return a+b;','function inner(privateOperation){return privateOperation(1);};return a+b;')),[],'shadow binding keeps names');
 const parameterProbe=privateProbe.replace('(function(){','(function(namespace){').replace('publicApi={publicOperation};})();','namespace.publicApi={publicOperation};})(globalThis);');
 assert.deepEqual(names(parameterProbe),['privateOperation'],'namespace parameter does not expose private callees');
 const parameterOutput=await compile(parameterProbe,{privateFunctions:true});
 assert.deepEqual(run(parameterOutput),run(parameterProbe),'namespace wrapper preserves observable values');
 const parameterContext={};vm.runInNewContext(parameterOutput,parameterContext);
 assert.equal(parameterContext.publicApi.publicOperation.name,'publicOperation');assert.equal(parameterContext.publicApi.publicOperation.length,1);
 assert(!parameterOutput.includes('function privateOperation('),'namespace-private declaration shortened');
 assert.equal(await compile(parameterProbe,{privateFunctions:true}),parameterOutput);
 const argumentProbe=`var result,publicApi,effects=[];(function(namespace){
 function privateOperation(value){effects.push('body');return value;}
 result={value:privateOperation(7),effects};namespace.publicApi={};})((effects.push('argument'),globalThis));`;
 assert.deepEqual(names(argumentProbe),['privateOperation']);
 assert.deepEqual(run(await compile(argumentProbe,{privateFunctions:true})),run(argumentProbe),'wrapper argument side effects retain exact execution order');
 for(const parameter of ['privateOperation','{argument}','argument=1'])assert.deepEqual(names(privateProbe.replace('(function(){','(function('+parameter+'){')),[],'shadow/default/destructuring wrapper keeps names');
 assert.deepEqual(names(privateProbe+'(function(){})();'),[],'multiple wrapper scopes are not mixed');
 const app=manifest.files.find(file=>file.target==='visual-search/app.js');
 assert(app.private_function_bindings.length>200,'actual eligible private app declarations recorded');
 const additional=['tour-controller-v4.js','prototype-search/data.js','search3-local-db-provider-v1.js','visual-search/local-db-parser.js','visual-search/flight-picker-v18.js'];
 for(const file of manifest.files)if(file!==app){
  if(additional.includes(file.target))assert(file.private_function_bindings.length>0,'bounded derived asset records eligible declarations');
  else assert.deepEqual(file.private_function_bindings,[],'assets outside the allowlist retain function names');
 }
 // Observe actual namespace APIs and request/payload/presentation behavior in
 // fresh VMs, without instrumenting or modifying any readable runtime source.
 const apiNames={'tour-controller-v4.js':'V2TourController','prototype-search/data.js':'AnyTourPrototypeData',
  'search3-local-db-provider-v1.js':'AnyTourLocalDbProviderV1','visual-search/local-db-parser.js':'AnyTourLocalDbProviderV1','visual-search/flight-picker-v18.js':'AnyTourFlightPickerV18'};
 function observeAsset(code,target){
  const events=[],http=[],location={protocol:'https:',hostname:'anytoour.ru',pathname:'/_preview/search3-next-candidate/visual-search/',origin:'https://anytoour.ru',href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/',search:''};
  const rejectHTTP=()=>{http.push('unexpected');throw Error('no HTTP permitted');};
  const document={cookie:'',addEventListener:type=>events.push('document:'+type),querySelector:()=>null};
  const window={location,V2_CONFIG:{},V2Runtime:{state:{searchId:17},api:rejectHTTP},fetch:rejectHTTP,
   Search3CanonicalProfilesV1:{create:()=>({})},V2LeadSearchContext:{enrichPayload:payload=>payload},addEventListener:type=>events.push('window:'+type)};
  vm.runInNewContext(code,{window,document,location,URL,URLSearchParams,AbortController,structuredClone,setTimeout,clearTimeout,fetch:rejectHTTP},{filename:target});
  const api=window[apiNames[target]];assert(api,target+' exposes the existing namespace');
  const descriptors=Reflect.ownKeys(api).map(key=>{const d=Object.getOwnPropertyDescriptor(api,key),v=api[key];return {key,enumerable:d.enumerable,configurable:d.configurable,writable:d.writable,
   get:d.get&&{name:d.get.name,arity:d.get.length},set:d.set&&{name:d.set.name,arity:d.set.length},
   type:typeof v,value:typeof v==='function'?{name:v.name,arity:v.length}:v};});
  let behavior;
  if(target==='prototype-search/data.js'){
   api.catalog.departures=[{id:1,name:'Москва'}];api.catalog.countries=[{id:4,name:'Турция',tourvisorIds:['4']}];
   const request=api.params({origin:'Москва',country:'4',from:'2026-10-13',to:'2026-10-19',minNights:7,maxNights:7,adults:2,ages:[12,4]},['101'],{stars:[5],min:50000,max:180000});
   behavior={request,sameScope:api.sameScope(request,{...request,scopeVersion:1}),
    amounts:[null,'',0,-1,133500.5,'133500.5',{value:'7.5'},Infinity].map(value=>api.amount(value)),
    dates:['13.10.2026','2026-10-13','invalid'].map(value=>api.date(value)),meals:['AI','UAI','BB','RO','Код от поставщика'].map(value=>api.meal(value)),
    fuel:[api.fuel({fuelCharge:0}),api.fuel({fuelCharge:20},{fuelCharge:0}),api.fuel({}),api.fuel({fuelCharge:{value:'20.5'}})]};
  }else if(target==='tour-controller-v4.js'){
   const session=api.createLeadSession({tour:{id:'fixture',price:120000,adults:2,nights:7,date:'2026-10-13',hotel:{name:'Вымышленный отель'}},searchId:17,search:{},flight:null});
   const values=new Map([['name','Тест'],['phone','+7 000 000-00-00'],['comment','Проверка'],['consent','1']]);
   behavior={payload:session.payload({get:key=>values.get(key)}),frozen:Object.isFrozen(session)};
  }else if(target.includes('local-db'))behavior={outsideLocal:api.localCandidate(),endpoint:api.endpoint(),invalid:[api.parse(null),api.parse({}),api.offerTour({},'1')]};
  else behavior={summary:api.selectionSummary({variants:[]},null),allowances:[0,20,null].map(baggage=>api.allowanceValue({baggage},'baggage'))};
  assert.equal(http.length,0,'no supplier/lead HTTP while inspecting public APIs');
  return JSON.parse(JSON.stringify({descriptors,frozen:Object.isFrozen(api),events,behavior}));
 }
 for(const target of additional){
  const readable=fs.readFileSync(path.resolve(__dirname,'../v2',target),'utf8'),served=fs.readFileSync(path.join(root,target),'utf8');
  assert.deepEqual(observeAsset(served,target),observeAsset(readable,target),target+' retains public names/arity/descriptors and behavior');
 }
 const dataCode=fs.readFileSync(path.join(root,'prototype-search/data.js'),'utf8');
 for(const name of ['andromedaPoint','normalizeAndromedaQuote'])assert(dataCode.includes('function '+name+'('),'canonical parser oracle retains observed '+name);

 console.log('PASS compiled visual assets: '+manifest.files.length+' exact files; public API/template/eval/arithmetic probes; '+JSON.stringify(manifest.totals));
})().catch(e=>{console.error(e);process.exitCode=1});
