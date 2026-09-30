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
 for(const observation of ['privateOperation.name','privateOperation.length','privateOperation.toString()','publicApi=privateOperation','[privateOperation]','{privateOperation}','privateOperation.bind(null)','Reflect.get(privateOperation,"name")']){
  assert.deepEqual(names(privateProbe.replace('result={value:',observation+';result={value:')),[],'observation/escape excludes private binding: '+observation);
 }
 for(const dynamic of ['eval("privateOperation.name")','window["eval"]("privateOperation.name")','Function("return 1")()','callback.caller','callback.callee','new Error().stack']){
  assert.deepEqual(names(privateProbe.replace('result={value:',dynamic+';result={value:')),[],'dynamic lookup/reflection bails out: '+dynamic);
 }
 assert.deepEqual(names(privateProbe.replace('return a+b;','function inner(privateOperation){return privateOperation(1);};return a+b;')),[],'shadow binding keeps names');
 assert.deepEqual(names(privateProbe.replace('(function(){','(function(argument){')),[],'non-private argument wrapper rejected');
 const app=manifest.files.find(file=>file.target==='visual-search/app.js');
 assert(app.private_function_bindings.length>200,'actual eligible private app declarations recorded');
 for(const file of manifest.files)if(file!==app)assert.deepEqual(file.private_function_bindings,[],'all other assets retain function names');

 console.log('PASS compiled visual assets: '+manifest.files.length+' exact files; public API/template/eval/arithmetic probes; '+JSON.stringify(manifest.totals));
})().catch(e=>{console.error(e);process.exitCode=1});
