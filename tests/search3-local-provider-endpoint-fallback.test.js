'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../v2/andromeda-local-endpoint-v1.js'),'utf8');
function run(pathname,initial){const root={location:{protocol:'https:',hostname:'anytoour.ru',pathname},V2_CONFIG:{andromedaApi:initial||''}};root.window=root;root.globalThis=root;vm.runInNewContext(source,root);return root.V2_CONFIG.andromedaApi;}
assert.equal(run('/_preview/search3-local-candidate/poisk-turov/',''),'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php');
assert.equal(run('/_preview/search3-local-candidate/poisk-turov/','/configured.php'),'/configured.php');
assert.equal(run('/_preview/search3-site-candidate/poisk-turov/',''),'');
assert.equal(run('/poisk-turov/',''),'');
console.log('SEARCH3_LOCAL_PROVIDER_ENDPOINT_OK local_fallback=1 configured_preserved=1 other_routes=2');
