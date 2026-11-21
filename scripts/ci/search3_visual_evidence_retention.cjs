'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
// Keep every broad-matrix frame within the existing bounded artifact carrier.
// Re-encode only the PNG zlib stream; verify identical filtered pixel bytes and
// preserve every other chunk. Gzip ancillary JSON without changing its raw bytes;
// the main receipt and separately retained messenger bytes stay intact.
module.exports=async function retainLosslessPngEvidence(directory){
 const {inflateSync,deflate,gzipSync,gunzipSync,crc32,constants}=require('node:zlib'),{createHash}=require('node:crypto'),rows=[],jsonRows=[];
 const encode=require('node:util').promisify(deflate);
 const sha=value=>createHash('sha256').update(value).digest('hex'),signature=Buffer.from('89504e470d0a1a0a','hex');
 const visit=async folder=>{for(const entry of fs.readdirSync(folder,{withFileTypes:true})){
  const file=path.join(folder,entry.name),relative=path.relative(directory,file).split(path.sep).join('/');
  if(relative==='messenger-entry')continue;
  if(entry.isDirectory()){await visit(file);continue;}
  if(!entry.isFile())continue;
  if(file.endsWith('.json')&&relative!=='receipt.json'){
   const before=fs.readFileSync(file),candidate=gzipSync(before,{level:9}),compressed=candidate.length<before.length,retainedFile=compressed?file+'.gz':file;
   if(compressed){
    assert(!fs.existsSync(retainedFile),relative+' gzip path must not already exist');
    fs.writeFileSync(retainedFile,candidate);const persisted=fs.readFileSync(retainedFile);
    assert(persisted.equals(candidate),relative+' persisted gzip equals the candidate');
    assert(gunzipSync(persisted).equals(before),relative+' byte-identical receipt after gunzip');
    fs.unlinkSync(file);
   }
   const after=compressed?candidate:before;
   jsonRows.push({path:relative,retained_path:compressed?relative+'.gz':relative,encoding:compressed?'gzip':'identity',original_bytes:before.length,retained_bytes:after.length,original_sha256:sha(before),retained_sha256:sha(after)});
   continue;
  }
  if(!file.endsWith('.png'))continue;
  const before=fs.readFileSync(file),chunks=[],idat=[];assert(before.subarray(0,8).equals(signature),relative+' PNG signature');
  for(let offset=8;offset<before.length;){
   assert(offset+12<=before.length,relative+' complete PNG chunk');
   const length=before.readUInt32BE(offset),end=offset+12+length;assert(end<=before.length,relative+' bounded PNG chunk');
   const type=before.subarray(offset+4,offset+8),data=before.subarray(offset+8,end-4);
   assert.equal(crc32(before.subarray(offset+4,end-4)),before.readUInt32BE(end-4),relative+' original CRC');
   chunks.push({type:type.toString('ascii'),bytes:before.subarray(offset,end)});if(type.toString('ascii')==='IDAT')idat.push(data);offset=end;
  }
  assert(idat.length>0&&chunks[0].type==='IHDR'&&chunks.at(-1).type==='IEND',relative+' complete screenshot PNG');
  const filtered=inflateSync(Buffer.concat(idat));
  // Only this frame's two original strategies run at once. Settle both before
  // choosing bytes, reporting failure or touching the next evidence file.
  const encodings=await Promise.allSettled([{}, {strategy:constants.Z_FILTERED}]
   .map(options=>encode(filtered,{level:9,...options})));
  const failures=encodings.filter(result=>result.status==='rejected');
  if(failures.length)throw new AggregateError(failures.map(result=>result.reason),relative+' PNG compression failed');
  const compressed=encodings.map(result=>result.value).reduce((best,value)=>value.length<best.length?value:best);
  assert(inflateSync(compressed).equals(filtered),relative+' byte-identical filtered pixels');
  const replacement=Buffer.alloc(compressed.length+12);replacement.writeUInt32BE(compressed.length);replacement.write('IDAT',4,'ascii');compressed.copy(replacement,8);replacement.writeUInt32BE(crc32(replacement.subarray(4,-4)),replacement.length-4);
  let inserted=false;const retained=Buffer.concat([signature,...chunks.flatMap(chunk=>chunk.type==='IDAT'?(inserted?[]:(inserted=true,[replacement])):[chunk.bytes])]);
  const after=retained.length<before.length?retained:before;if(after!==before)fs.writeFileSync(file,after);
  rows.push({path:relative,original_bytes:before.length,retained_bytes:after.length,original_sha256:sha(before),retained_sha256:sha(after),filtered_pixel_sha256:sha(filtered)});
 }};
 await visit(directory);const originalBytes=rows.reduce((sum,row)=>sum+row.original_bytes,0),retainedBytes=rows.reduce((sum,row)=>sum+row.retained_bytes,0);
 fs.writeFileSync(path.join(directory,'png-retention.json.gz'),gzipSync(Buffer.from(JSON.stringify({encoding_only:true,pixel_streams_identical:true,dimensions_and_other_chunks_unchanged:true,messenger_artifact_unchanged:true,frames:rows.length,original_bytes:originalBytes,retained_bytes:retainedBytes,files:rows})+'\n'),{level:9}));
 const jsonOriginalBytes=jsonRows.reduce((sum,row)=>sum+row.original_bytes,0),jsonRetainedBytes=jsonRows.reduce((sum,row)=>sum+row.retained_bytes,0),jsonManifest=path.join(directory,'json-retention.json.gz');
 if(jsonRows.length||!fs.existsSync(jsonManifest))fs.writeFileSync(jsonManifest,gzipSync(Buffer.from(JSON.stringify({schema:1,uncompressed_bytes_identical:true,root_receipt_unchanged:true,messenger_artifact_unchanged:true,files:jsonRows})+'\n'),{level:9}));
 console.log('PASS lossless complete PNG retention',JSON.stringify({frames:rows.length,originalBytes,retainedBytes,savedBytes:originalBytes-retainedBytes}));
 console.log('PASS lossless ancillary JSON retention',JSON.stringify({files:jsonRows.length,originalBytes:jsonOriginalBytes,retainedBytes:jsonRetainedBytes,savedBytes:jsonOriginalBytes-jsonRetainedBytes}));
}
