'use strict';
// A NEXT-only dependency projection. Canonical controllers stay unchanged for
// legacy routes; retained initializers/functions are copied from their AST ranges.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const acorn=require('acorn');
const target='visual-search/lead-runtime-v1.js';
const owners=[
 {file:'tour-controller-v4.js',global:'V2TourController',exports:['createLeadSession','createProviderLeadSession','version'],guard:true},
 {file:'lead-form-guard-v1.js',global:'V2LeadFormGuard',exports:['digits','validatePhone','version']}
];
const parse=code=>acorn.parse(code,{ecmaVersion:2022,sourceType:'script'});
function names(pattern,out){
 if(!pattern)return out;
 if(pattern.type==='Identifier')out.add(pattern.name);
 else if(pattern.type==='RestElement')names(pattern.argument,out);
 else if(pattern.type==='AssignmentPattern')names(pattern.left,out);
 else if(pattern.type==='ArrayPattern')pattern.elements.forEach(p=>names(p,out));
 else if(pattern.type==='ObjectPattern')pattern.properties.forEach(p=>names(p.type==='RestElement'?p.argument:p.value,out));
 return out;
}
function children(node,fn){for(const [key,value]of Object.entries(node)){if(key==='start'||key==='end')continue;if(Array.isArray(value))value.forEach(n=>n&&typeof n.type==='string'&&fn(n,key));else if(value&&typeof value.type==='string')fn(value,key);}}
function hoisted(node,out){
 children(node,child=>{
  if(/^(?:FunctionDeclaration|FunctionExpression|ArrowFunctionExpression|ClassDeclaration|ClassExpression)$/.test(child.type))return;
  if(child.type==='VariableDeclaration'&&child.kind==='var')child.declarations.forEach(d=>names(d.id,out));
  hoisted(child,out);
 });
}
// Only references resolving to the canonical IIFE scope count as dependencies.
// Do not confuse object keys, labels or shadowed callback parameters with owners.
function references(root,top){
 const refs=new Set();
 function visit(node,scopes=[]){
  if(!node)return;
  if(node.type==='WithStatement'||node.type==='Identifier'&&['eval','Function'].includes(node.name))throw Error('Dynamic scope is not projectable');
  if(node.type==='Identifier'){if(top.has(node.name)&&!scopes.some(s=>s.has(node.name)))refs.add(node.name);return;}
  if(/^(?:FunctionDeclaration|FunctionExpression|ArrowFunctionExpression)$/.test(node.type)){
   const local=new Set(node.id?[node.id.name]:[]);node.params.forEach(p=>names(p,local));hoisted(node.body,local);
   const next=[...scopes,local];node.params.forEach(p=>defaults(p,next));visit(node.body,next);return;
  }
  if(node.type==='BlockStatement'){
   const local=new Set();for(const child of node.body){if(child.type==='VariableDeclaration'&&child.kind!=='var')child.declarations.forEach(d=>names(d.id,local));else if(['FunctionDeclaration','ClassDeclaration'].includes(child.type))local.add(child.id.name);}
   node.body.forEach(n=>visit(n,[...scopes,local]));return;
  }
  if(node.type==='VariableDeclaration'){node.declarations.forEach(d=>{defaults(d.id,scopes);visit(d.init,scopes);});return;}
  if(['ForStatement','ForInStatement','ForOfStatement'].includes(node.type)){
   const local=new Set(),decl=node.init||node.left;if(decl?.type==='VariableDeclaration'&&decl.kind!=='var')decl.declarations.forEach(d=>names(d.id,local));children(node,n=>visit(n,[...scopes,local]));return;
  }
  if(node.type==='CatchClause'){const local=names(node.param,new Set());visit(node.body,[...scopes,local]);return;}
  if(node.type==='MemberExpression'){visit(node.object,scopes);if(node.computed)visit(node.property,scopes);return;}
  if(['Property','MethodDefinition'].includes(node.type)){if(node.computed)visit(node.key,scopes);visit(node.value,scopes);return;}
  if(node.type==='LabeledStatement'){visit(node.body,scopes);return;}
  if(['BreakStatement','ContinueStatement','MetaProperty'].includes(node.type))return;
  children(node,n=>visit(n,scopes));
 }
 function defaults(pattern,scopes){
  if(!pattern)return;
  if(pattern.type==='AssignmentPattern'){visit(pattern.right,scopes);defaults(pattern.left,scopes);}
  else if(pattern.type==='ObjectPattern')pattern.properties.forEach(p=>{if(p.computed)visit(p.key,scopes);defaults(p.value||p.argument,scopes);});
  else if(pattern.type==='ArrayPattern')pattern.elements.forEach(p=>defaults(p,scopes));
  else if(pattern.type==='RestElement')defaults(pattern.argument,scopes);
 }
 visit(root);return refs;
}
const property=n=>n.computed?n.property.type==='Literal'?n.property.value:null:n.property.name;
function project(code,owner){
 const tree=parse(code);assert.equal(tree.body.length,1,'single canonical IIFE');
 const call=tree.body[0].expression;assert.equal(call.type,'CallExpression');assert.equal(call.arguments.length,0);
 const wrapper=call.callee;assert.equal(wrapper.type,'FunctionExpression');assert.equal(wrapper.params.length,0);assert(!wrapper.id&&!wrapper.async&&!wrapper.generator);
 const body=wrapper.body.body;assert.equal(body[0].directive,'use strict');
 const bindings=new Map();
 for(const stmt of body){
  if(stmt.type==='FunctionDeclaration'){assert(!bindings.has(stmt.id.name));bindings.set(stmt.id.name,{node:stmt,code:code.slice(stmt.start,stmt.end),order:stmt.start});}
  else if(stmt.type==='VariableDeclaration')for(const d of stmt.declarations){assert.equal(d.id.type,'Identifier');assert(!bindings.has(d.id.name));bindings.set(d.id.name,{node:d.init,code:stmt.kind+' '+code.slice(d.start,d.end)+';',order:d.start});}
 }
 const exports=body.filter(s=>s.type==='ExpressionStatement'&&s.expression.type==='AssignmentExpression'&&s.expression.operator==='='&&s.expression.left.type==='MemberExpression'&&s.expression.left.object.name==='window'&&property(s.expression.left)===owner.global);
 assert.equal(exports.length,1,'one canonical public export');const value=exports[0].expression.right;assert.equal(value.type,'ObjectExpression');
 const kept=value.properties.filter(p=>!p.computed&&owner.exports.includes(p.key.name??p.key.value));
 assert.deepEqual(kept.map(p=>p.key.name??p.key.value).sort(),[...owner.exports].sort(),'exact retained API');
 const top=new Set(bindings.keys()),needed=new Set();
 function include(node){for(const name of references(node,top))if(!needed.has(name)){needed.add(name);include(bindings.get(name).node);}}
 kept.forEach(p=>include(p.value));let guard=null;
 if(owner.guard){guard=body.find(s=>s.type==='IfStatement');assert(guard&&code.slice(guard.start,guard.end)==='if(!rt)return;','retain original runtime availability guard');include(guard);}
 const declarations=[...needed].map(name=>bindings.get(name));if(guard)declarations.push({code:code.slice(guard.start,guard.end),order:guard.start});
 const rendered=declarations.sort((a,b)=>a.order-b.order).map(d=>d.code).join('\n');
 return `(function(){'use strict';\n${rendered}\nwindow.${owner.global}={${kept.map(p=>code.slice(p.start,p.end)).join(',')}};\n})();\n`;
}
function generate(root){return '/*! GENERATED by scripts/build/search3-js/lead-runtime.cjs; canonical lead functions are not edited here. */\n'+owners.map(o=>project(fs.readFileSync(path.join(root,o.file),'utf8'),o)).join('');}
function verify(root){const expected=generate(root);assert.equal(fs.readFileSync(path.join(root,target),'utf8'),expected,'NEXT lead projection drift: regenerate from canonical owners; never hand-edit payload/transport');return expected;}
module.exports={owners,target,project,generate,verify,references,parse};
if(require.main===module){const root=path.resolve(process.argv[2]||'v2');if(process.argv.includes('--write'))fs.writeFileSync(path.join(root,target),generate(root));else verify(root);}
