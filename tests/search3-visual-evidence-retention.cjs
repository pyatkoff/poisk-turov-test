'use strict';
// Exercise the real archive owner with valid/corrupt PNGs and delayed encoders.
// No browser, supplier, runtime or lead request is made by this regression.
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),vm=require('node:vm');
const zlib=require('node:zlib'),{createHash}=require('node:crypto');
const retain=require('../scripts/ci/search3_visual_evidence_retention.cjs');
const temporary=fs.mkdtempSync(path.join(os.tmpdir(),'search3-evidence-'));
const sha=value=>createHash('sha256').update(value).digest('hex');
const signature=Buffer.from('89504e470d0a1a0a','hex');
function chunk(type,data){
 const bytes=Buffer.alloc(data.length+12);bytes.writeUInt32BE(data.length);bytes.write(type,4,'ascii');data.copy(bytes,8);bytes.writeUInt32BE(zlib.crc32(bytes.subarray(4,-4)),bytes.length-4);return bytes;
}
function screenshot(){
 const header=Buffer.alloc(13);header.writeUInt32BE(32);header.writeUInt32BE(32,4);header[8]=8;header[9]=6;
 const pixels=Buffer.alloc(32*(1+32*4));
 for(let y=0;y<32;y++)for(let x=0;x<32;x++){const offset=y*129+1+x*4;pixels.set([x*7,y*7,(x+y)*3,255],offset);}
 const packed=zlib.deflateSync(pixels,{level:1}),split=Math.floor(packed.length/2);
 return {pixels,bytes:Buffer.concat([signature,chunk('IHDR',header),chunk('tEXt',Buffer.from('Source\0fictional RGBA screenshot')),chunk('IDAT',packed.subarray(0,split)),chunk('IDAT',packed.subarray(split)),chunk('IEND',Buffer.alloc(0))])};
}
function unpack(bytes){
 assert(bytes.subarray(0,8).equals(signature));const parts=[],other=[];
 for(let offset=8;offset<bytes.length;){const length=bytes.readUInt32BE(offset),end=offset+length+12,type=bytes.toString('ascii',offset+4,offset+8);assert.equal(zlib.crc32(bytes.subarray(offset+4,end-4)),bytes.readUInt32BE(end-4));if(type==='IDAT')parts.push(bytes.subarray(offset+8,end-4));else other.push(bytes.subarray(offset,end));offset=end;}
 return {pixels:zlib.inflateSync(Buffer.concat(parts)),other};
}
function folder(name){const directory=path.join(temporary,name);fs.mkdirSync(directory);return directory;}
const readJson=file=>JSON.parse(zlib.gunzipSync(fs.readFileSync(file)));
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function deferred(){let resolve;const promise=new Promise(r=>resolve=r);return {promise,resolve};}
function injectedEncoder(encode){
 const file=path.join(__dirname,'../scripts/ci/search3_visual_evidence_retention.cjs'),module={exports:{}};
 vm.runInNewContext(fs.readFileSync(file,'utf8'),{module,Buffer,console,require:name=>name==='node:zlib'?{...zlib,deflate:encode,deflateSync:()=>{throw Error('Synchronous encoding bypassed the bounded worker pair');}}:require(name)},{filename:file});
 return module.exports;
}
(async()=>{
 try{
  const directory=folder('valid'),image=screenshot(),json=Buffer.from(JSON.stringify({observations:Array(200).fill('exact retained facts')})+'\n'),receipt=Buffer.from('{"root":"exact bytes"}\n'),small=Buffer.from('0'),messenger=Buffer.from('separately retained bytes');
  fs.mkdirSync(path.join(directory,'nested'));fs.mkdirSync(path.join(directory,'messenger-entry'));
  fs.writeFileSync(path.join(directory,'frame.png'),image.bytes);fs.writeFileSync(path.join(directory,'nested','frame.png'),image.bytes);
  fs.writeFileSync(path.join(directory,'facts.json'),json);fs.writeFileSync(path.join(directory,'tiny.json'),small);fs.writeFileSync(path.join(directory,'receipt.json'),receipt);
  fs.writeFileSync(path.join(directory,'messenger-entry','frame.png'),messenger);fs.writeFileSync(path.join(directory,'messenger-entry','facts.json'),messenger);fs.writeFileSync(path.join(directory,'unchanged.txt'),messenger);
  await retain(directory);
  const png=readJson(path.join(directory,'png-retention.json.gz')),facts=readJson(path.join(directory,'json-retention.json.gz'));
  assert.equal(png.frames,2);assert.equal(png.pixel_streams_identical,true);assert.equal(png.dimensions_and_other_chunks_unchanged,true);assert.equal(png.messenger_artifact_unchanged,true);
  for(const row of png.files){
   const bytes=fs.readFileSync(path.join(directory,row.path)),decoded=unpack(bytes);
   assert(decoded.pixels.equals(image.pixels),'all filtered RGBA bytes survive');assert.deepEqual(decoded.other,unpack(image.bytes).other,'dimensions and ancillary chunks survive byte for byte');
   assert(bytes.length<=image.bytes.length);assert.equal(row.original_sha256,sha(image.bytes));assert.equal(row.retained_sha256,sha(bytes));assert.equal(row.filtered_pixel_sha256,sha(image.pixels));assert.equal(row.original_bytes,image.bytes.length);assert.equal(row.retained_bytes,bytes.length);
  }
  assert.equal(facts.files.length,2);for(const row of facts.files){const bytes=fs.readFileSync(path.join(directory,row.retained_path)),original=row.path==='facts.json'?json:small;assert.equal(row.original_sha256,sha(original));assert.equal(row.retained_sha256,sha(bytes));assert((row.encoding==='gzip'?zlib.gunzipSync(bytes):bytes).equals(original));}
  assert.equal(fs.existsSync(path.join(directory,'facts.json')),false);assert(fs.readFileSync(path.join(directory,'tiny.json')).equals(small));
  assert(fs.readFileSync(path.join(directory,'receipt.json')).equals(receipt));for(const file of ['messenger-entry/frame.png','messenger-entry/facts.json','unchanged.txt'])assert(fs.readFileSync(path.join(directory,file)).equals(messenger));
  for(const [name,mutate,reason] of [
   ['crc',bytes=>{bytes[bytes.length-1]^=1;return bytes;},/original CRC/],
   ['truncated',bytes=>bytes.subarray(0,bytes.length-2),/complete PNG chunk/],
   ['oversized-chunk',bytes=>{bytes.writeUInt32BE(bytes.length,8);return bytes;},/bounded PNG chunk/],
   ['missing-idat',()=>Buffer.concat([signature,chunk('IHDR',Buffer.alloc(13)),chunk('IEND',Buffer.alloc(0))]),/complete screenshot PNG/]
  ]){
   const invalid=folder(name);fs.writeFileSync(path.join(invalid,'bad.png'),mutate(Buffer.from(image.bytes)));await assert.rejects(retain(invalid),reason);assert.equal(fs.existsSync(path.join(invalid,'png-retention.json.gz')),false,'corruption cannot produce a successful evidence manifest');
  }
  // One failure must not resolve/reject the archive while its other worker is
  // still running; no next file or output is touched before both settle.
  const failure=folder('encoder-failure'),entered=deferred(),encoders=[];fs.writeFileSync(path.join(failure,'a.png'),image.bytes);fs.writeFileSync(path.join(failure,'b.png'),image.bytes);
  const owner=injectedEncoder((input,options,callback)=>{encoders.push({input,options,callback});if(encoders.length===2)entered.resolve();});
  let outcome='pending';const completion=owner(failure).then(()=>{outcome='resolved';},error=>{outcome='rejected';return error;});
  await entered.promise;assert.equal(encoders.length,2,'only the two strategies of one image are submitted');encoders[0].callback(Error('encoder failed'));
  await tick();assert.equal(outcome,'pending');assert.equal(encoders.length,2);assert(fs.readFileSync(path.join(failure,'a.png')).equals(image.bytes));
  encoders[1].callback(null,zlib.deflateSync(encoders[1].input,encoders[1].options));const error=await completion;
  assert.equal(outcome,'rejected');assert.equal(error.name,'AggregateError');assert.equal(error.errors[0].message,'encoder failed');assert.equal(encoders.length,2);assert.equal(fs.existsSync(path.join(failure,'png-retention.json.gz')),false);
  // The real browser owner must await the moved asynchronous archive before
  // returning success to the existing artifact-packaging step.
  const {parse}=require('../scripts/build/search3-js/node_modules/acorn'),ast=parse(fs.readFileSync(path.join(__dirname,'search3-visual-live-browser.cjs'),'utf8'),{ecmaVersion:'latest'}),calls=[];
  function walk(node,parent){if(!node||typeof node!=='object')return;if(node.type==='CallExpression'&&node.callee.type==='Identifier'&&node.callee.name==='retainLosslessPngEvidence')calls.push({node,parent});for(const value of Object.values(node))if(Array.isArray(value)){for(const child of value)walk(child,node);}else if(value&&typeof value==='object')walk(value,node);}
  walk(ast,null);assert.equal(calls.length,1);assert.equal(calls[0].parent.type,'AwaitExpression','asynchronous archive completion is part of the browser gate');
  console.log('PASS lossless evidence retention: filtered pixels/chunks/CRC/digests/JSON; root+messenger bytes; corruption and bounded asynchronous failure propagation');
 }finally{fs.rmSync(temporary,{recursive:true,force:true});}
})().catch(error=>{console.error(error);process.exitCode=1;});
