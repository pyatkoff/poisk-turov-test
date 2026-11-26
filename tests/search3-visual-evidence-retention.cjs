'use strict';
// Exercise the real archive owner with valid/corrupt PNGs and delayed encoders.
// No browser, supplier, runtime or lead request is made by this regression.
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),vm=require('node:vm');
const zlib=require('node:zlib'),{createHash}=require('node:crypto');
const {spawnSync}=require('node:child_process');
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
function verifyBoundedPackaging(){
 const workflow=fs.readFileSync(path.join(__dirname,'../.github/workflows/search3-visual-migration.yml'),'utf8');
 const start=workflow.indexOf('      - name: Package complete visual acceptance in bounded parts\n');
 const uploads=workflow.indexOf('      - name: Retain visual bridge acceptance part 01\n',start);
 assert(start>=0&&uploads>start,'the existing packaging owner precedes its uploads');
 const block=workflow.slice(start,uploads),python=block.match(/          python3 - <<'PY'\n([\s\S]*?)          PY\n/);
 assert(python,'exercise the actual workflow packer');
 const code=python[1].replace(/^          /gm,'');
 assert(code.includes('limit = 24 * 1024 * 1024'),'each evidence part retains its 24 MiB cap');
 assert(code.includes('parts = [[] for _ in range(5)]'),'only five bounded parts are available');
 assert(code.includes('sizes[index] + overhead >= 31 * 1024 * 1024'),'transfer headroom remains enforced');
 const uploadBlock=workflow.slice(uploads,workflow.indexOf('  visual-migration:\n',uploads));
 const carriers=[...uploadBlock.matchAll(/      - name: Retain visual bridge acceptance part (\d{2})\n([\s\S]*?)(?=      - name:|$)/g)];
 assert.deepEqual(carriers.map(row=>row[1]),['01','02','03','04','05']);
 for(const [,id,body]of carriers)for(const required of [
  `steps.acceptance-parts.outputs.part${id} == 'true'`,
  'uses: actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02',
  `name: search3-visual-live-acceptance-\${{ github.event.pull_request.head.sha }}-part-${id}`,
  `path: \${{ runner.temp }}/visual-acceptance-parts/part-${id}/`,
  'compression-level: 0','include-hidden-files: true','if-no-files-found: error'
 ])assert(body.includes(required),'complete bounded upload '+id+': '+required);
 const directory=folder('bounded-parts'),source=path.join(directory,'visual-live-evidence'),names=[];
 fs.mkdirSync(path.join(source,'public-review'),{recursive:true});
 const write=(name,bytes)=>{fs.writeFileSync(path.join(source,name),bytes);names.push(name);};
 // Opaque files supply >96 MiB; valid PNG and JSON fixtures must also survive.
 for(let i=0;i<5;i++)write('payload-'+i+'.bin',Buffer.alloc(20*1024*1024,i+1));
 write('public-review/frame.png',screenshot().bytes);write('public-review/receipt.json',Buffer.from('{"review":true}\n'));write('receipt.json',Buffer.from('{"complete":true}\n'));
 const expected=new Map(names.map(name=>{const bytes=fs.readFileSync(path.join(source,name));return [name,{bytes:bytes.length,sha256:sha(bytes)}];}));
 function run(name){
  const runner=path.join(directory,name);fs.mkdirSync(runner);
  const result=spawnSync('python3',['-c',code],{cwd:directory,encoding:'utf8',timeout:30000,maxBuffer:1024*1024,
   env:{...process.env,RUNNER_TEMP:runner,GITHUB_OUTPUT:path.join(runner,'outputs'),ACCEPTANCE_SOURCE_SHA:'a'.repeat(40),GITHUB_RUN_ID:'123',GITHUB_RUN_ATTEMPT:'1'}});
  return {runner,result,target:path.join(runner,'visual-acceptance-parts')};
 }
 const accepted=run('fits-five');assert.equal(accepted.result.status,0,accepted.result.stderr);
 const indexBytes=fs.readFileSync(path.join(accepted.target,'part-01/acceptance-index.json')),index=JSON.parse(indexBytes);
 assert.equal(index.file_count,expected.size);assert(index.total_bytes>4*24*1024*1024);assert.equal(index.parts.length,5);
 assert.equal(index.source_sha,'a'.repeat(40));assert.equal(index.run_id,'123');assert.equal(index.run_attempt,'1');
 assert.deepEqual(index.files.map(row=>row.path).sort(),[...expected.keys()].sort());
 assert.equal(new Set(index.files.map(row=>row.path)).size,expected.size);
 for(const part of index.parts){
  assert(part.bytes<=24*1024*1024);assert(fs.readFileSync(path.join(accepted.target,'part-'+part.id+'/acceptance-index.json')).equals(indexBytes));
  assert.equal(part.file_count,index.files.filter(row=>row.part===part.id).length);
 }
 for(const row of index.files){
  assert.deepEqual({bytes:row.bytes,sha256:row.sha256},expected.get(row.path));
  const original=fs.readFileSync(path.join(source,row.path)),retained=fs.readFileSync(path.join(accepted.target,'part-'+row.part+'/evidence',row.path));
  assert.equal(sha(original),expected.get(row.path).sha256);assert(retained.equals(original),'exact evidence bytes: '+row.path);
 }
 assert.deepEqual(fs.readFileSync(path.join(accepted.runner,'outputs'),'utf8').trim().split('\n'),['01','02','03','04','05'].map(id=>'part'+id+'=true'));
 write('payload-5.bin',Buffer.alloc(20*1024*1024,6));
 const overflow=run('six-parts');assert.notEqual(overflow.result.status,0);assert.match(overflow.result.stderr,/Complete acceptance evidence exceeds five bounded parts/);assert(!fs.existsSync(overflow.target));assert(!fs.existsSync(path.join(overflow.runner,'outputs')));
 for(let i=0;i<6;i++)fs.unlinkSync(path.join(source,'payload-'+i+'.bin'));
 const oversized=path.join(source,'oversized.bin'),fd=fs.openSync(oversized,'w');fs.ftruncateSync(fd,24*1024*1024+1);fs.closeSync(fd);
 const overLimit=run('oversized-file');assert.notEqual(overLimit.result.status,0);assert.match(overLimit.result.stderr,/Complete acceptance evidence exceeds five bounded parts/);assert(!fs.existsSync(overLimit.target));assert(!fs.existsSync(path.join(overLimit.runner,'outputs')));
 console.log('PASS five-part evidence carrier: >96 MiB, all paths/digests/bytes and indexes retained; 24 MiB cap and overflow refusal; upload 01–05');
}
(async()=>{
 try{
  const directory=folder('valid'),image=screenshot(),json=Buffer.from(JSON.stringify({observations:Array(200).fill('exact retained facts')})+'\n'),receipt=Buffer.from('{"root":"exact bytes"}\n'),small=Buffer.from('0'),messenger=Buffer.from('separately retained bytes');
  fs.mkdirSync(path.join(directory,'nested'));fs.mkdirSync(path.join(directory,'messenger-entry'));
  fs.writeFileSync(path.join(directory,'frame.png'),image.bytes);fs.writeFileSync(path.join(directory,'nested','frame.png'),image.bytes);
  fs.writeFileSync(path.join(directory,'facts.json'),json);fs.writeFileSync(path.join(directory,'tiny.json'),small);fs.writeFileSync(path.join(directory,'receipt.json'),receipt);
  fs.writeFileSync(path.join(directory,'messenger-entry','frame.png'),messenger);fs.writeFileSync(path.join(directory,'messenger-entry','facts.json'),messenger);fs.writeFileSync(path.join(directory,'unchanged.txt'),messenger);
  await injectedEncoder(()=>{throw Error('Evidence that fits must keep original PNG bytes');})(directory);
  const png=readJson(path.join(directory,'png-retention.json.gz')),facts=readJson(path.join(directory,'json-retention.json.gz'));
  assert.equal(png.frames,2);assert.equal(png.pixel_streams_identical,true);assert.equal(png.dimensions_and_other_chunks_unchanged,true);assert.equal(png.messenger_artifact_unchanged,true);
  for(const row of png.files){
   const bytes=fs.readFileSync(path.join(directory,row.path)),decoded=unpack(bytes);
   assert(decoded.pixels.equals(image.pixels),'all filtered RGBA bytes survive');assert.deepEqual(decoded.other,unpack(image.bytes).other,'dimensions and ancillary chunks survive byte for byte');
   assert(bytes.equals(image.bytes),'PNG bytes are unchanged when the raw evidence fits');assert.equal(row.original_sha256,sha(image.bytes));assert.equal(row.retained_sha256,sha(bytes));assert.equal(row.filtered_pixel_sha256,sha(image.pixels));assert.equal(row.original_bytes,image.bytes.length);assert.equal(row.retained_bytes,bytes.length);
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
  const payload=fs.openSync(path.join(failure,'payload.bin'),'w');
  fs.ftruncateSync(payload,24*1024*1024);
  fs.closeSync(payload);
  const owner=injectedEncoder((input,options,callback)=>{encoders.push({input,options,callback});if(encoders.length===2)entered.resolve();});
  let outcome='pending';const completion=owner(failure).then(()=>{outcome='resolved';},error=>{outcome='rejected';return error;});
  await entered.promise;assert.equal(encoders.length,2,'only the two strategies of one image are submitted');encoders[0].callback(Error('encoder failed'));
  await tick();assert.equal(outcome,'pending');assert.equal(encoders.length,2);assert(fs.readFileSync(path.join(failure,'a.png')).equals(image.bytes));
  encoders[1].callback(null,zlib.deflateSync(encoders[1].input,encoders[1].options));const error=await completion;
  assert.equal(outcome,'rejected');assert.equal(error.name,'AggregateError');assert.equal(error.errors[0].message,'encoder failed');assert.equal(encoders.length,2);assert.equal(fs.existsSync(path.join(failure,'png-retention.json.gz')),false);
  await retain(failure);
  const recompressed=readJson(path.join(failure,'png-retention.json.gz'));
  assert.equal(recompressed.frames,2);
  for(const row of recompressed.files){
   const bytes=fs.readFileSync(path.join(failure,row.path)),decoded=unpack(bytes);
   assert(decoded.pixels.equals(image.pixels),'recompression preserves the original pixel stream');
   assert.deepEqual(decoded.other,unpack(image.bytes).other,'recompression preserves all other chunks');
   assert.equal(row.retained_sha256,sha(bytes));
   assert(bytes.length<=image.bytes.length);
  }
  // The real browser owner must await the moved asynchronous archive before
  // returning success to the existing artifact-packaging step.
  const {parse}=require('../scripts/build/search3-js/node_modules/acorn'),ast=parse(fs.readFileSync(path.join(__dirname,'search3-visual-live-browser.cjs'),'utf8'),{ecmaVersion:'latest'}),calls=[];
  function walk(node,parent){if(!node||typeof node!=='object')return;if(node.type==='CallExpression'&&node.callee.type==='Identifier'&&node.callee.name==='retainLosslessPngEvidence')calls.push({node,parent});for(const value of Object.values(node))if(Array.isArray(value)){for(const child of value)walk(child,node);}else if(value&&typeof value==='object')walk(value,node);}
  walk(ast,null);assert.equal(calls.length,1);assert.equal(calls[0].parent.type,'AwaitExpression','asynchronous archive completion is part of the browser gate');
  verifyBoundedPackaging();
  console.log('PASS lossless evidence retention: filtered pixels/chunks/CRC/digests/JSON; root+messenger bytes; corruption and bounded asynchronous failure propagation');
 }finally{fs.rmSync(temporary,{recursive:true,force:true});}
})().catch(error=>{console.error(error);process.exitCode=1;});
