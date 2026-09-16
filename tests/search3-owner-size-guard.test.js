'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');

const limits={
  'v2/results-renderer-v5.js':45000,
  'v2/search3-room-normalizer-v1.js':10000,
};
for(const [file,maxBytes] of Object.entries(limits)){
  const bytes=fs.statSync(file).size;
  assert.ok(bytes<=maxBytes,`${file} is ${bytes} bytes; owner limit is ${maxBytes}. Extract a bounded owner instead of growing the shared file.`);
}
const renderer=fs.readFileSync('v2/results-renderer-v5.js','utf8');
assert.doesNotMatch(renderer,/const\s+roomAliases\s*=/,'room aliases must stay outside the renderer');
assert.match(renderer,/Search3RoomNormalizerV1/,'renderer delegates room presentation to its bounded owner');
console.log('SEARCH3_OWNER_SIZE_GUARD_OK renderer_limit=45000 room_owner_limit=10000');
