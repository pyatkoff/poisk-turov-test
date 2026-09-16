'use strict';
const fs=require('node:fs');
const vm=require('node:vm');

function run(path){vm.runInThisContext(fs.readFileSync(path,'utf8'),{filename:path});}

function loadSearch3Renderer(){
  run('v2/search3-room-normalizer-v1.js');
  run('v2/results-renderer-v5.js');
  return window.V2Results;
}

module.exports={loadSearch3Renderer};
