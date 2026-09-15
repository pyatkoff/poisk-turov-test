'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const code=fs.readFileSync(require('path').join(__dirname,'../v2/anex-provider-v1.js'),'utf8');
const sandbox={URL,console,location:new URL('https://anytoour.ru/_preview/search3-site-candidate/poisk-turov/')};
sandbox.globalThis=sandbox;vm.createContext(sandbox);vm.runInContext(code,sandbox);
const api=sandbox.AnyTourAnexProvider;assert(api&&api.version===1);
const fallback=api.endpoint('');assert(fallback&&fallback.href==='https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php');
assert.strictEqual(api.endpoint('https://evil.invalid/api-anex-search3-preview.php'),null);
assert.strictEqual(api.endpoint('/_preview/search3-anex-candidate/not-anex.php'),null);
const hotel={local_id:245,name:'Fixture Hotel',category:5,rating:4.7,country:'Египет',region:'Шарм-эль-Шейх',catalog:{image_url:'https://img.example.com/hotel.jpg',description:'Описание',address:'Адрес',subregion:'Hadaba'},tours:[{price:{amount:'100123.45',currency:'RUB'},checkin:'2026-09-20',nights:7,meal:'AI',room:'Standard',kind:'group_minimum',final_price_verified:false},{price:{amount:'110000',currency:'RUB'},checkin:'2026-09-21',nights:8,meal:'UAI',room:'Sea View',kind:'concrete',final_price_verified:false}]};
const normalized=api.normalizeHotel(hotel);assert(normalized);assert.strictEqual(normalized.id,'245');assert.strictEqual(normalized.provider,'anex');assert.strictEqual(normalized.tours.length,2);assert.strictEqual(normalized.tours[0].selectionEnabled,false);assert.strictEqual(normalized.tours[0].quoteRequired,true);assert.strictEqual(normalized.tours[0].groupMinimum,true);assert.strictEqual(normalized.tours[0].operator.name,'ANEX');assert.strictEqual(normalized.picturelink,'https://img.example.com/hotel.jpg');
const base=[{id:'245',name:'Fixture Hotel',provider:'tourvisor',price:120000,picturelink:'https://tv.example.com/a.jpg',tours:[{id:'tv1',provider:'tourvisor',price:120000,date:'20.09.2026',nights:7,meal:{name:'AI'},roomType:'Standard'}]}];
const merged=api.merge(base,[hotel]);assert.strictEqual(merged.length,1);assert.deepStrictEqual(Array.from(merged[0].providers),['tourvisor','anex']);assert.strictEqual(merged[0].tours.length,3);assert.strictEqual(merged[0].price,100123.45);assert.strictEqual(merged[0].picturelink,'https://tv.example.com/a.jpg');
const bad=JSON.parse(JSON.stringify(hotel));bad.tours[0].price.currency='EUR';assert(api.normalizeHotel(bad));bad.tours=[bad.tours[0]];assert.strictEqual(api.normalizeHotel(bad),null);
const unmapped=Object.assign({},hotel,{local_id:null});assert.strictEqual(api.normalizeHotel(unmapped),null);

// Same-price/same-room concrete offers are separate supplier identities, not duplicates.
const searchRef='a'.repeat(32),refA='anex_online:'+'b'.repeat(64),refB='anex_online:'+'c'.repeat(64);
const tourA=Object.assign({},hotel.tours[0],{kind:'concrete',search_ref:searchRef,offer_ref:refA,supplier_offer_id:'DO_NOT_COPY'});
const tourB=Object.assign({},tourA,{offer_ref:refB});
const samePrice=Object.assign({},hotel,{tours:[tourA,tourB,tourA]});
const exact=api.normalizeHotel(samePrice);
assert.strictEqual(exact.tours.length,2);
assert.strictEqual(exact.tours[0].searchRef,searchRef);
assert.strictEqual(exact.tours[0].offerRef,refA);
assert.notStrictEqual(exact.tours[0].id,exact.tours[1].id);
assert.strictEqual(exact.tours[0].id,api.normalizeTour(tourA,245,99).id,'reordering must not change retained offer identity');
assert.strictEqual(exact.tours[0].selectionEnabled,false);
assert.strictEqual(exact.tours[0].quoteRequired,true);
assert(!JSON.stringify(exact).includes('DO_NOT_COPY'));
const first=api.merge(base,[Object.assign({},hotel,{tours:[tourA]})]);
const both=api.merge(first,[samePrice]);
assert.strictEqual(both[0].tours.length,3,'second same-price offer must not be collapsed');
assert.strictEqual(api.merge(both,[samePrice])[0].tours.length,3,'same retained offers must not multiply');
const otherSearch=Object.assign({},tourA,{search_ref:'d'.repeat(32)});
assert.notStrictEqual(api.normalizeTour(otherSearch,245,0).id,exact.tours[0].id,'search references cannot mix');
const otherProvider={id:'245',tours:[{id:refA,offerRef:refA,provider:'andromeda',price:90000}]};
assert.strictEqual(api.merge([otherProvider],[samePrice])[0].tours.length,3,'source namespaces must remain independent');
for(const invalid of [{search_ref:undefined},{offer_ref:undefined},{search_ref:searchRef+'\n'},
    {offer_ref:'andromeda:'+'b'.repeat(64)},{offer_ref:refA+'\n'},{search_ref:{toString:()=>searchRef}},{offer_ref:'private-concrete-a'}]){
    const result=api.normalizeTour(Object.assign({},tourA,invalid),245,0);
    assert(result);assert.strictEqual(result.searchRef,'');assert.strictEqual(result.offerRef,'');
    assert.strictEqual(result.selectionEnabled,false,'unverified references must never activate selection');
}
assert.strictEqual(normalized.tours[0].searchRef,'');
assert.strictEqual(normalized.tours[0].offerRef,'','legacy display-only data must not invent a reference');
for(const href of ['https://anytoour.ru/poisk-turov/','https://anytoour.ru/_preview/other/poisk-turov/',
    'http://anytoour.ru/_preview/search3-site-candidate/poisk-turov/','http://127.0.0.1:8098/_preview/search3-site-candidate/poisk-turov/']){
    sandbox.location=new URL(href);assert.strictEqual(api.endpoint(''),null,'fallback must stay isolated: '+href);
}
sandbox.location=new URL('https://anytoour.ru/_preview/search3-site-candidate/poisk-turov/');
assert(api.endpoint(''));
console.log('ANEX provider common-results boundary: PASS; retained_refs=1 distinct_same_price=1 dedupe=1 isolation=1 selection=disabled network=0');
