'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

const source=fs.readFileSync('v2/prototype-search/hotel-popularity-v1.js','utf8');
const ids=Array.from({length:300},(_,i)=>1001+i);
const context={window:{AnyTourTopHotelLegacyIds:ids},Number,Object,Array,Map,String};
vm.createContext(context);
vm.runInContext(source,context);
const popularity=context.window.AnyTourHotelPopularityV1;

assert.equal(popularity.source,'owner-top500');
assert.equal(popularity.size,300);
assert.equal(popularity.rank({id:1001,legacyIds:['1001']}),1,'verified canonical legacy ID gets ordered rank');
assert.equal(popularity.rank({id:1001,legacyIds:[]}),null,'AnyTour local ID collision is never popularity evidence');
assert.equal(popularity.rank({id:77,legacyIds:['1100','1005']}),5,'best verified legacy rank wins after canonical merge');
assert.equal(popularity.badge({legacyIds:['1001']}),'Популярный отель');
assert.equal(popularity.badge({legacyIds:['1101']}),'Популярный отель');
assert.equal(popularity.badge({legacyIds:['999999']}),'');
assert.equal(popularity.boost({legacyIds:['1001']}),.5);
assert.equal(popularity.boost({legacyIds:['1101']}),.5);
assert.equal(popularity.boost({legacyIds:['1299']}),.5);
assert.equal(popularity.boost({legacyIds:['999999']}),0);

const sameCanonicalHotel={id:42,legacyIds:['1001'],offers:[
  {provider:'tourvisor'},{provider:'anex'},{provider:'andromeda'},{provider:'local'}
]};
assert.equal(popularity.rank(sameCanonicalHotel),1,'one hotel-level rank applies regardless of offer providers');

const indexPhp=fs.readFileSync('v2/prototype-search/index.php','utf8');
const indexHtml=fs.readFileSync('v2/prototype-search/index.html','utf8');
const app=fs.readFileSync('v2/prototype-search/app.js','utf8');
const css=fs.readFileSync('v2/prototype-search/styles.css','utf8');
assert.match(indexPhp,/data\/hotel-popularity-v1\.php/,'prototype bootstraps the canonical cohort helper');
assert.match(indexPhp,/v2_hotel_popularity_legacy_ids/,'prototype does not copy the TOP500 list');
assert.match(indexPhp,/data-popular-hotel-legacy-ids/,'prototype exposes cohort as inert HTML data, not executable inline code');
assert.ok(indexHtml.indexOf('./hotel-popularity-v1.js')<indexHtml.indexOf('./app.js'),'popularity module loads before app');
assert.match(app,/const popularity=window\.AnyTourHotelPopularityV1/,'app consumes canonical hotel popularity');
assert.match(app,/popularity\?\.badge\(h\)/,'card badge is driven only by canonical popularity');
assert.match(css,/\.hotel-popularity-badge\{/,'card badge has a dedicated responsive style');

const sortStart=app.indexOf('function recommendedHotelScore(');
const sortEnd=app.indexOf('\nfunction minimumForDay',sortStart);
assert.ok(sortStart>=0&&sortEnd>sortStart,'recommended ranking block is present');
const sortContext={
  Number,
  hotels:[
    {id:1,rating:4.4,beach:null,legacyIds:['1001'],offers:[{total:160000}]},
    {id:2,rating:4.6,beach:null,legacyIds:[],offers:[{total:140000}]},
    {id:3,rating:5.0,beach:null,legacyIds:[],offers:[{total:180000}]}
  ],
  hotelOffers:h=>h.offers,
  state:{sort:'recommended'},
  popularity
};
vm.createContext(sortContext);
vm.runInContext(app.slice(sortStart,sortEnd)+'\nthis.popularitySortTest={results,recommendedHotelScore,recommendedHotelRank};',sortContext);
assert.deepEqual(Array.from(sortContext.popularitySortTest.results(),r=>r.hotel.id),[3,1,2],'recommended boost is bounded: popular 4.4 beats 4.6 but not a 5.0 hotel');
sortContext.state.sort='price';
assert.deepEqual(Array.from(sortContext.popularitySortTest.results(),r=>r.hotel.id),[2,1,3],'explicit price sort ignores popularity');
sortContext.state.sort='rating';
assert.deepEqual(Array.from(sortContext.popularitySortTest.results(),r=>r.hotel.id),[3,2,1],'explicit rating sort ignores popularity');


console.log('search3 prototype hotel popularity: ok');
