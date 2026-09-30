'use strict';
// Readable owners remain in v2. Only the isolated artifact/browser fixture gets
// these derived assets; no compression of statements, expressions or arithmetic.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),crypto=require('node:crypto'),zlib=require('node:zlib');
const terser=require('terser');
const {parsed}=require('./compact.cjs');
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
async function compile(code){
 const source=parsed(code);
 const exact=await terser.minify(code,{compress:false,mangle:false,keep_fnames:true,keep_classnames:true,
  format:{comments:'all',quote_style:3,keep_quoted_props:true,keep_numbers:true}});
 assert.deepEqual(syntax(parsed(exact.code).tree),syntax(source.tree),'Visual asset printing changed executable syntax');
 const result=await terser.minify(code,{compress:false,mangle:{toplevel:false,eval:false,properties:false},keep_fnames:true,keep_classnames:true,
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
 const ondemand=[...html.matchAll(/data-(?:offer-list|hotel-details)-src="([^"?]+\.js)"/g)].map(m=>m[1]);assert.equal(ondemand.length,2,'two explicit cold presentation owners');
 const graphs={live,offline,ondemand};
 for(const src of [...live,...offline,...ondemand])assert(/^\.\.?\/[\w./-]+\.js$/.test(src)&&!src.includes('/../'),'bounded visual script path');
 return graphs;
}
async function build(root){
 root=path.resolve(root);const graphs=inventory(root),files=[];
 for(const src of new Set([...graphs.live,...graphs.offline,...graphs.ondemand])){
  const relative=path.posix.normalize(path.posix.join('visual-search',src));
  const input=path.resolve(root,relative);assert(input.startsWith(root+path.sep),'source stays inside payload');
  const source=fs.readFileSync(input,'utf8'),code=await compile(source),target=relative;
  const output=path.join(root,target);fs.mkdirSync(path.dirname(output),{recursive:true});fs.writeFileSync(output,code);
  files.push({src,source:relative,target,source_sha256:hash(source),sha256:hash(code),raw:Buffer.byteLength(source),served:Buffer.byteLength(code),gzip_before:zlib.gzipSync(source,{level:9}).length,gzip_after:zlib.gzipSync(code,{level:9}).length});
 }
 const completeGraphs={...graphs,complete_live:[...graphs.live,...graphs.ondemand],complete_offline:[...graphs.offline,...graphs.ondemand]};
 const totals={};for(const[name,graph]of Object.entries(completeGraphs))totals[name]=files.filter(f=>graph.includes(f.src)).reduce((s,f)=>Object.fromEntries(Object.keys(s).map(k=>[k,s[k]+f[k]])),{raw:0,served:0,gzip_before:0,gzip_after:0});
 const manifest={schema:1,tool:'terser@5.51.2',policy:'binding-renaming-only; no expression compression; globals/properties/function names/arity retained',graphs,totals,files};
 fs.writeFileSync(path.join(root,'visual-search/asset-size.json'),JSON.stringify(manifest,null,2)+'\n');return manifest;
}
module.exports={compile,syntax,inventory,build};
if(require.main===module)build(process.argv[2]||'v2').then(m=>console.log('VISUAL_COMPILED_ASSETS '+JSON.stringify(m.totals))).catch(e=>{console.error(e);process.exitCode=1});
