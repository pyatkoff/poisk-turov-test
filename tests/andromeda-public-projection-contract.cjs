'use strict';
// One boundary regression: actual PHP response -> actual shared JS consumer.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const {execFileSync}=require('node:child_process');
const [server,providerFile,expected='accepted']=process.argv.slice(2);
const data=JSON.parse(execFileSync('php',[__dirname+'/andromeda-public-projection-fixture.php',server],{encoding:'utf8'}));
const window={location:{href:'https://anytoour.ru/_preview/search3-site-candidate/poisk-turov/',origin:'https://anytoour.ru'}};
vm.runInNewContext(fs.readFileSync(providerFile,'utf8'),{window,URL,decodeURIComponent});
const api=window.AnyTourAndromedaProvider;
const tv={id:447,name:'Local hotel',price:120000,tours:[{id:'tv_fixture',price:120000}]};
const merged=api.merge([tv],data.hotels);
if(expected==='rejected') {
    assert.equal(data.hotels[0].mapping_status,undefined);
    assert.equal(merged[0].tours.length,1,'baseline reproduces dropped accepted Andromeda offer');
} else {
    assert.equal(data.hotels[0].mapping_status,'resolved');
    assert.equal(merged[0].tours.length,2,'real server projection reaches the shared card');
    assert.equal(merged[0].tours[1].offerContext.offer_ref,data.hotels[0].tours[0].offer_ref);
    assert.equal(merged[0].tours[1].selectionEnabled,false,'display is not verified quote/selection authority');
    const unresolved={...data.hotels[0],mapping_status:'unresolved'};
    assert.equal(api.merge([tv],[unresolved])[0].tours.length,1,'consumer acceptance guard stays strict');
}
console.log('ANDROMEDA_PROJECTION_CONTRACT '+expected+' supplier_calls=0 persistent_db_writes=0');
