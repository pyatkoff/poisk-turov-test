'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

const root=path.resolve(__dirname,'..');
const source=fs.readFileSync(path.join(root,'v2/prototype-search/config.js'),'utf8');
const sandbox={window:{}};
vm.runInNewContext(source,sandbox,{filename:'v2/prototype-search/config.js'});

const config=JSON.parse(JSON.stringify(sandbox.window.V2_CONFIG));
assert.deepEqual(config,{
  api:'/api-v2.php',
  leadApi:'../preview-lead-disabled.php',
  andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php',
  andromedaQuoteApi:'/_preview/search3-anex-candidate/api-andromeda-quote-preview.php',
  anexApi:'/_preview/search3-anex-candidate/api-anex-search3-preview.php'
});
assert.match(config.andromedaQuoteApi,/^\/_preview\/search3-anex-candidate\/api-andromeda-quote-preview\.php$/);
assert.doesNotMatch(source,/https?:\/\//i);
assert.ok(config.leadApi.endsWith('/preview-lead-disabled.php'),'prototype lead transport must stay disabled');

console.log('search3 prototype provider config contract: PASS');
