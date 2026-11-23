'use strict';
// Exercise real config/profile/data owners with fixed catalogue responses only.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'../v2'),baseline=process.argv.includes('--baseline');
const implementation=baseline?path.resolve(root,'../../source-base/v2'):root;
const profile={catalog:'anytour',id:501,revision:1,name:'Проверочный отель',category:5};
const settings={schemaVersion:1,profile:'live',scenario:'family',entry:'home',publicBase:'/poisk-turov/',resourceBase:'/visual-search/',
 api:'/api-v2.php',leadApi:'/_preview/search3-next-candidate/preview-lead-disabled.php',
 anexApi:'/_preview/search3-anex-candidate/api-anex-search3-preview.php',metrikaCounter:0};
function harness(pathname='/',overrides={}){
 const requests=[],unexpected=[],value={...settings,...overrides};
 const context={URL,URLSearchParams,AbortController,DOMException,TextEncoder,structuredClone,setTimeout,clearTimeout,addEventListener(){},
  location:new URL('https://home-catalog.invalid'+pathname),document:{currentScript:{dataset:{search3PublicConfig:JSON.stringify(value)}}},
  V2Runtime:{api(){throw Error('Supplier API must not run during home catalogue restoration');}},
  fetch:async input=>{
   const url=new URL(input,'https://home-catalog.invalid');requests.push(url.pathname+url.search);let payload;
   if(url.pathname==='/_preview/search3-local-candidate/data/hotel-details-read-v1.php'&&url.searchParams.get('anytourHotelId')==='501')
    payload={ok:true,source:'anytour-canonical-catalog',catalog:'anytour',item:profile};
   else if(url.pathname==='/data/hotel-search-v1.php'&&url.searchParams.get('countryId')==='4')
    payload={ok:true,items:[{id:101,country:{id:4}}]};
   else if(url.pathname==='/_preview/search3-local-candidate/data/hotel-details-read-v1.php'&&url.searchParams.get('legacyHotelIds[]')==='101')
    payload={ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:['101'],items:[profile],links:[{legacyHotelId:101,anytourHotelId:501}],missingLegacyIds:[]};
   else if(url.pathname==='/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php'&&url.searchParams.get('oldLocalHotelIds[]')==='501')
    payload={ok:true,source:'anytour-local-tv-catalog-v1',catalog:'local-tv',requestedIds:[501],items:[],links:[],missingIds:[501]};
   else{unexpected.push(url.href);throw Error('Unexpected catalogue request: '+url.href);}
   return {ok:true,json:async()=>payload};
  }};
 context.window=context;vm.createContext(context);
 vm.runInContext(fs.readFileSync(path.join(root,'visual-search/public-config-v1.js'),'utf8'),context);
 for(const file of ['search3-canonical-profiles-v1.js','prototype-search/data.js'])vm.runInContext(fs.readFileSync(path.join(implementation,file),'utf8'),context,{filename:file});
 context.AnyTourPrototypeData.catalog.countries.push({id:'4',name:'Турция',tourvisorIds:['4']});
 return {context,requests,unexpected};
}
(async()=>{
 if(baseline){
  const h=harness();assert.equal(h.context.AnyTourPublicConfigV1.valid,true);
  assert.equal(h.context.Search3CanonicalProfilesV1.current(),null);
  await assert.rejects(h.context.AnyTourPrototypeData.restoreHotel(501,'4'),/Hotel catalogue unavailable/);
  assert.equal(await h.context.AnyTourPrototypeData.refreshHotelContent([501]),false);assert.deepEqual(h.requests,[]);
  console.log('REPRODUCED: valid home entry cannot restore canonical hotel 501 or read its accepted LOCAL content');return;
 }
 for(const pathname of ['/','/index.php']){
  const h=harness(pathname),hotel=await h.context.AnyTourPrototypeData.restoreHotel(501,'4');
  assert.equal(hotel.id,501);assert.deepEqual(Array.from(hotel.legacyIds),['101']);assert.equal(hotel.country,'4');
  await h.context.AnyTourPrototypeData.refreshHotelContent([501]);
  assert(h.requests.some(url=>url.includes('anytourHotelId=501')));
  assert(h.requests.some(url=>url.startsWith('/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php?')));
  assert.deepEqual(h.unexpected,[]);
 }
 for(const [pathname,override] of [['/other/',{}],['/poisk-turov/',{}],['/',{entry:'search'}],['/',{entry:'other'}],['/',{profile:'review',anexApi:null}]]){
  const h=harness(pathname,override);assert.equal(h.context.Search3CanonicalProfilesV1.current(),null);
  await assert.rejects(h.context.AnyTourPrototypeData.restoreHotel(501,'4'),/Hotel catalogue unavailable/);
  assert.equal(await h.context.AnyTourPrototypeData.refreshHotelContent([501]),false);assert.deepEqual(h.requests,[]);
 }
 const search=harness('/poisk-turov/',{entry:'search'}),hotel=await search.context.AnyTourPrototypeData.restoreHotel(501,'4');
 assert.equal(hotel.id,501);assert.deepEqual(Array.from(hotel.legacyIds),['101']);assert.deepEqual(search.unexpected,[]);
 console.log('PASS home canonical hotel/LOCAL paths: exact live home, restored own/native IDs, public search retained; other paths/profiles fail closed; supplier starts=0');
})().catch(error=>{console.error(error);process.exitCode=1;});
