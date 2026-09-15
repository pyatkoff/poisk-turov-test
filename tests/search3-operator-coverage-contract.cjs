'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');

const renderer=fs.readFileSync(path.join(__dirname,'../v2/results-renderer-v5.js'),'utf8');
const logosDir=path.join(__dirname,'../v2/assets/operator-logos');
const assets=new Set(fs.readdirSync(logosDir));

// Customer-facing operator identity must be independent from provider/source.
assert.match(renderer,/provider\/source never supplies operator identity/);

// Current release must not be described as having logo coverage that it does not ship.
const required=[
  ['ANEX','anex.svg'],
  ['FUN&SUN','funsun.svg'],
  ['Интурист','intourist.png'],
  ['Библио-Глобус','biblio-globus.svg']
];
for(const [name,file] of required)assert.ok(assets.has(file),`${name} logo asset is present`);

// Owner acceptance gaps: these major operators are currently text fallbacks.
for(const file of ['coral.svg','sunmar.svg'])assert.equal(assets.has(file),false,`${file} is an explicit unresolved coverage gap`);
assert.doesNotMatch(renderer,/key:'coral'/,'Coral is not yet mapped as a canonical brand');
assert.doesNotMatch(renderer,/key:'sunmar'/,'Sunmar is not yet mapped as a canonical brand');

console.log('search3 operator coverage contract: known assets verified; Coral/Sunmar gaps explicit');
