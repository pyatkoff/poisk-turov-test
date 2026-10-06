'use strict';
// Readable owners remain in v2. Only the isolated artifact/browser fixture gets
// these derived assets; no compression of statements, expressions or arithmetic.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),crypto=require('node:crypto'),zlib=require('node:zlib');
const terser=require('terser');
const {parsed}=require('./compact.cjs');
const {printCSS}=require('./compact-css.cjs');
const {verify:verifyLeadRuntime}=require('./lead-runtime.cjs');
const cssTree=require('css-tree');
const hash=s=>crypto.createHash('sha256').update(s).digest('hex');
function syntax(node,rename=false,context=''){
 if(Array.isArray(node))return node.filter(n=>n?.type!=='EmptyStatement').map(n=>syntax(n,rename,context));
 if(!node||typeof node!=='object')return node;
 const result={};
 for(const[k,v]of Object.entries(node)){
  // Property spelling/shorthand is presentation; retain the exact public key.
  if(node.type==='Property'&&!node.computed&&k==='key'){
   result[k]={type:'Literal',value:String(v.type==='Identifier'?v.name:v.value)};continue;
  }
  if(node.type==='Property'&&k==='shorthand'&&node.key?.name!=='__proto__'){result[k]=false;continue;}
  if(rename&&node.type==='Identifier'&&k==='name'&&context!=='member-key')continue;
  // Untagged templates observe cooked text. Tagged templates also observe raw.
  if(node.type==='TemplateElement'&&k==='value'&&context!=='tagged'){result[k]={cooked:v.cooked};continue;}
  const next=node.type==='TemplateLiteral'?context:
   node.type==='TaggedTemplateExpression'&&k==='quasi'?'tagged':
   (node.type==='MemberExpression'&&!node.computed&&k==='property')?'member-key':
   /^(?:LabeledStatement|BreakStatement|ContinueStatement)$/.test(node.type)&&k==='label'?'label':node.type;
  result[k]=syntax(v,rename,next);
 }return result;
}
// Only direct-call-only declarations inside one private, anonymous IIFE
// may lose their diagnostic name. Any escape/inspection/shadow or dynamic
// lookup keeps the name; the default compiler still retains every name.
function privateFunctionBindings(tree,{preserveFunctions=[]}={}){
 const wrappers=tree.body.filter(n=>n.type==='ExpressionStatement'&&n.expression.type==='CallExpression'&&['FunctionExpression','ArrowFunctionExpression'].includes(n.expression.callee.type));
 if(wrappers.length!==1)return [];
 const call=wrappers[0].expression;
 if(call.callee.params.some(param=>param.type!=='Identifier')||call.callee.id||call.callee.body.type!=='BlockStatement')return [];
 const declarations=new Map(call.callee.body.body.filter(n=>n.type==='FunctionDeclaration').map(n=>[n.id.name,n]));
 const candidates=new Set(declarations.keys()),called=new Set();let dynamic=false;
 function visit(node,parent,key){
  if(!node||typeof node!=='object')return;
  if(node.type==='WithStatement'||node.type==='Identifier'&&['eval','Function'].includes(node.name))dynamic=true;
  if(node.type==='MemberExpression'){
   const property=node.computed?node.property.value:node.property.name;
   if(['eval','Function','caller','callee','stack','prepareStackTrace','constructor'].includes(property))dynamic=true;
  }
  if(node.type==='Identifier'&&candidates.has(node.name)&&node!==declarations.get(node.name).id){
   if(parent?.type==='CallExpression'&&key==='callee')called.add(node.name);
   else candidates.delete(node.name);
  }
  for(const[k,v]of Object.entries(node)){
   if(Array.isArray(v))v.forEach(child=>visit(child,node,k));
   else if(v&&typeof v==='object')visit(v,node,k);
  }
 }
 visit(tree);return dynamic?[]:[...candidates].filter(name=>called.has(name)&&!preserveFunctions.includes(name)).sort();
}
async function compile(code,{privateFunctions=false,preserveFunctions=[]}={}){
 const source=parsed(code),privateNames=privateFunctions?privateFunctionBindings(source.tree,{preserveFunctions}):[];
 const keepNames=privateNames.length?new RegExp('^(?!(?:'+privateNames.map(name=>name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')).join('|')+')$)'):true;
 const exact=await terser.minify(code,{compress:false,mangle:false,keep_fnames:true,keep_classnames:true,
  format:{comments:'all',quote_style:3,keep_quoted_props:true,keep_numbers:true}});
 assert.deepEqual(syntax(parsed(exact.code).tree),syntax(source.tree),'Visual asset printing changed executable syntax');
 const result=await terser.minify(code,{compress:false,mangle:{toplevel:false,eval:false,properties:false},keep_fnames:keepNames,keep_classnames:true,
  format:{comments:/^!|@(?:license|preserve|cc_on)|copyright|source(?:mapping)?url/i,quote_style:3,keep_quoted_props:true,keep_numbers:true}});
 assert.deepEqual(syntax(parsed(result.code).tree,true),syntax(source.tree,true),'Visual asset renaming changed statement/literal/arithmetic/public key structure');
 const output=result.code+'\n';return Buffer.byteLength(output)<Buffer.byteLength(code)?output:code;
}
function inventory(root){
 const php=fs.readFileSync(path.join(root,'visual-search/index.php'),'utf8');
 const html=fs.readFileSync(path.join(root,'visual-search/index.html'),'utf8');
 const array=php.match(/\$scripts\s*=\s*\[([\s\S]*?)\];/);assert(array,'explicit LIVE script graph');
 const live=[...array[1].matchAll(/'([^']+\.js)'/g)].map(m=>m[1]);
 const offline=[...html.matchAll(/<script src="([^"?]+\.js)"/g)].map(m=>m[1]);
 const ondemand=[...html.matchAll(/data-(?:offer-list|hotel-details|flight-picker)-src="([^"?]+\.js)"/g)].map(m=>m[1]);assert.equal(ondemand.length,3,'three explicit cold presentation owners');
 const graphs={live,offline,ondemand};
 for(const src of [...live,...offline,...ondemand])assert(/^\.\.?\/[\w./-]+\.js$/.test(src)&&!src.includes('/../'),'bounded visual script path');
 return graphs;
}
// Keep visual CSS separate from the existing JS graph/size contract. Only these
// two entry stylesheets receive syntax-preserving printing in the artifact.
const visualStyleOwners=['./styles.css','./mobile-controls-v1.css'];
function stylesheetInventory(root){
 const html=fs.readFileSync(path.join(root,'visual-search/index.html'),'utf8');
 const initial=[...html.matchAll(/<link\b[^>]*>/g)]
  .filter(match=>/\brel="stylesheet"/.test(match[0]))
  .map(match=>match[0].match(/\bhref="([^"?]+\.css)"/)?.[1]);
 assert.deepEqual(initial,visualStyleOwners,'explicit two-owner visual stylesheet graph');
 return {initial};
}
// A bounded artifact allowlist, never a source/API edit. Other assets keep names.
const privateBindingAssets=new Set(['visual-search/app.js','tour-controller-v4.js','prototype-search/data.js',
 'search3-local-db-provider-v1.js','visual-search/local-db-parser.js','visual-search/flight-picker-v18.js']);
// The full 132/1000-flight oracle extracts the real normalizer from served code.
// Its entry/boundary declarations are observed instrumentation, not private names.
const observedBindings={'prototype-search/data.js':['andromedaPoint','normalizeAndromedaQuote']};
async function build(root){
 root=path.resolve(root);verifyLeadRuntime(root);const graphs=inventory(root),files=[];
 for(const src of new Set([...graphs.live,...graphs.offline,...graphs.ondemand])){
  const relative=path.posix.normalize(path.posix.join('visual-search',src));
  const input=path.resolve(root,relative);assert(input.startsWith(root+path.sep),'source stays inside payload');
  const source=fs.readFileSync(input,'utf8'),privateFunctions=privateBindingAssets.has(relative),preserveFunctions=observedBindings[relative]||[],code=await compile(source,{privateFunctions,preserveFunctions}),target=relative;
  const output=path.join(root,target);fs.mkdirSync(path.dirname(output),{recursive:true});fs.writeFileSync(output,code);
  files.push({src,source:relative,target,private_function_bindings:privateFunctions?privateFunctionBindings(parsed(source).tree,{preserveFunctions}):[],source_sha256:hash(source),sha256:hash(code),raw:Buffer.byteLength(source),served:Buffer.byteLength(code),gzip_before:zlib.gzipSync(source,{level:9}).length,gzip_after:zlib.gzipSync(code,{level:9}).length});
 }
 const completeGraphs={...graphs,complete_live:[...graphs.live,...graphs.ondemand],complete_offline:[...graphs.offline,...graphs.ondemand]};
 const totals={};for(const[name,graph]of Object.entries(completeGraphs))totals[name]=files.filter(f=>graph.includes(f.src)).reduce((s,f)=>Object.fromEntries(Object.keys(s).map(k=>[k,s[k]+f[k]])),{raw:0,served:0,gzip_before:0,gzip_after:0});
 const cssGraphs=stylesheetInventory(root),cssFiles=[];
 for(const src of cssGraphs.initial){
  const relative=path.posix.join('visual-search',src),input=path.resolve(root,relative);
  assert(input.startsWith(root+path.sep),'stylesheet source stays inside payload');
  const source=fs.readFileSync(input,'utf8'),code=printCSS(source);
  fs.writeFileSync(input,code);
  cssFiles.push({src,source:relative,target:relative,source_sha256:hash(source),sha256:hash(code),raw:Buffer.byteLength(source),served:Buffer.byteLength(code),gzip_before:zlib.gzipSync(source,{level:9}).length,gzip_after:zlib.gzipSync(code,{level:9}).length});
 }
 const cssTotals={initial:cssFiles.reduce((s,f)=>Object.fromEntries(Object.keys(s).map(k=>[k,s[k]+f[k]])),{raw:0,served:0,gzip_before:0,gzip_after:0})};
 const css={schema:1,tool:'css-tree@'+cssTree.version,policy:'exact-AST printing only; selector, condition and declaration value slices and order retained; protected notes retained',graphs:cssGraphs,totals:cssTotals,files:cssFiles};
 const manifest={schema:1,tool:'terser@5.51.2',policy:'binding-renaming-only; no expression compression; globals/properties/classes/arity retained; names retained except proven direct-call-only private declarations in the bounded visual asset allowlist',graphs,totals,files,css};
 fs.writeFileSync(path.join(root,'visual-search/asset-size.json'),JSON.stringify(manifest,null,2)+'\n');return manifest;
}
module.exports={compile,syntax,inventory,stylesheetInventory,build,privateFunctionBindings};
if(require.main===module)build(process.argv[2]||'v2').then(m=>console.log('VISUAL_COMPILED_ASSETS '+JSON.stringify(m.totals))).catch(e=>{console.error(e);process.exitCode=1});
