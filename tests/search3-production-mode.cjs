'use strict';
// Execute the actual config and data owner with a fictional LOCAL-only response.
// No browser, supplier, bot, CRM or persistent storage is used.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'..');
const configSource=fs.readFileSync(path.join(root,'v2/prototype-search/config.js'),'utf8');
const dataSource=fs.readFileSync(path.join(root,'v2/prototype-search/data.js'),'utf8');
const canonicalSource=fs.readFileSync(path.join(root,'v2/search3-canonical-profiles-v1.js'),'utf8');
const endpoint='/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php';
const canonicalEndpoint='/_preview/search3-local-candidate/data/hotel-details-read-v1.php';
const marked={search3TvSession:'enabled',search3Messenger:'enabled',localTvCatalog:'enabled'};
const production={...marked,search3Production:'enabled'};
const plain=value=>JSON.parse(JSON.stringify(value)),tests=[];
const test=(name,run)=>tests.push({name,run});
function configure(pathname,dataset,search=''){
 const window={location:{pathname,search,origin:'https://anytoour.ru',href:'https://anytoour.ru'+pathname+search},
  document:dataset===null?{currentScript:null}:{currentScript:{dataset}}};
 vm.runInNewContext(configSource,{window,URLSearchParams},{filename:'prototype-search/config.js'});
 return window;
}
function noProduction(config){
 assert.equal(Object.hasOwn(config,'search3ProductionEnabled'),false);
 assert.equal(Object.hasOwn(config,'search3PhoneLeadEnabled'),false);
}
function contentOwner(window){
 const calls=[],timers=new Set();let timer=0;
 window.Search3CanonicalProfilesV1={create:()=>({})};
 const context=vm.createContext({window,URL,URLSearchParams,AbortController,
  setTimeout(){const id=++timer;timers.add(id);return id;},clearTimeout:id=>timers.delete(id),
  fetch:async(url,options)=>{
   calls.push({url,options});
   return {ok:true,json:async()=>({ok:true,source:'anytour-local-tv-catalog-v1',catalog:'local-tv',
    requestedIds:[101],items:[{catalog:'local-tv',id:901,revision:1,name:'Сохранённый отель'}],
    links:[{oldLocalId:101,tourvisorHotelId:901}],missingIds:[]})};
  }});
 vm.runInContext(dataSource,context,{filename:'prototype-search/data.js'});
 assert.equal(calls.length,0,'loading a config/data owner never starts a request');
 return {api:window.AnyTourPrototypeData,calls,timers};
}
function canonicalDataOwner(window){
 const calls=[],timers=new Set();let timer=0;
 const profile={catalog:'anytour',id:901,revision:1,name:'Канонический отель',category:4,
  country:{id:78,name:'Египет'},region:{name:'Хургада'},hotelInformation:{}};
 window.addEventListener=()=>{};
 window.fetch=async(url,options)=>{
  calls.push({url,options});const target=new URL(url,window.location.href);
  assert.equal(target.pathname,canonicalEndpoint,'only the existing canonical reader may be used');
  assert.equal(target.searchParams.get('catalog'),'anytour');
  if(target.searchParams.has('anytourHotelId')){
   assert.equal(target.searchParams.get('anytourHotelId'),'901');
   return {ok:true,json:async()=>({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',item:profile})};
  }
  assert.deepEqual(target.searchParams.getAll('legacyHotelIds[]'),['101']);
  return {ok:true,json:async()=>({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',
   requestedLegacyIds:['101'],items:[profile],links:[{legacyHotelId:101,anytourHotelId:901}],missingLegacyIds:[]})};
 };
 const context=vm.createContext({window,URL,URLSearchParams,AbortController,structuredClone,
  setTimeout(){const id=++timer;timers.add(id);return id;},clearTimeout:id=>timers.delete(id),fetch:window.fetch});
 vm.runInContext(canonicalSource,context,{filename:'search3-canonical-profiles-v1.js'});
 vm.runInContext(dataSource,context,{filename:'prototype-search/data.js'});
 assert.equal(calls.length,0,'loading real canonical/data modules starts no request');
 return {api:window.AnyTourPrototypeData,owner:window.Search3CanonicalProfilesV1.current(),calls,timers};
}

test('Default config retains its exact existing shape and disabled SAMO/phone transport',()=>{
 const window={};vm.runInNewContext(configSource,{window,URLSearchParams});
 assert.deepEqual(plain(window.V2_CONFIG),{api:'/api-v2.php',leadApi:'../preview-lead-disabled.php',
  andromedaApi:null,andromedaQuoteApi:null,anexApi:'/_preview/search3-anex-candidate/api-anex-search3-preview.php',
  search3TVSessionEnabled:false,search3MessengerEnabled:false,localTvCatalogEnabled:false});
 noProduction(window.V2_CONFIG);
});

test('Both exact production entries require the literal script marker and retain existing endpoint contracts',()=>{
 for(const pathname of ['/poisk-turov/','/poisk-turov/index.php']){
  for(const search of ['','?scenario=live','?country=78','?%73cenario=live']){
   const c=configure(pathname,production,search).V2_CONFIG;
   assert.equal(c.search3ProductionEnabled,true);assert.equal(c.search3PhoneLeadEnabled,false);
   assert.equal(c.search3TVSessionEnabled,true);assert.equal(c.search3MessengerEnabled,true);assert.equal(c.localTvCatalogEnabled,true);
   assert.equal(c.api,'/api-v2.php');assert.equal(c.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
   assert.equal(c.leadApi,'../preview-lead-disabled.php');assert.equal(c.andromedaApi,null);assert.equal(c.andromedaQuoteApi,null);
  }
  for(const dataset of [{},marked,{...marked,search3Production:'true'},{...marked,search3Production:'disabled'},null]){
   const c=configure(pathname,dataset,'?search3Production=enabled&search3MessengerEnabled=true').V2_CONFIG;
   noProduction(c);assert.equal(c.search3TVSessionEnabled,false);assert.equal(c.search3MessengerEnabled,false);assert.equal(c.localTvCatalogEnabled,false);
  }
 }
});

test('Production opt-in does not independently grant the TV, messenger or content capability',()=>{
 const names={search3TvSession:'search3TVSessionEnabled',search3Messenger:'search3MessengerEnabled',localTvCatalog:'localTvCatalogEnabled'};
 for(const selected of [null,...Object.keys(names)]){
  const dataset={search3Production:'enabled',...(selected?{[selected]:'enabled'}:{})};
  const c=configure('/poisk-turov/',dataset).V2_CONFIG;assert.equal(c.search3ProductionEnabled,true);
  for(const [attribute,flag] of Object.entries(names))assert.equal(c[flag],attribute===selected,flag);
 }
});

test('Subpaths, traversal spellings, alternate routes and unknown paths cannot activate production',()=>{
 for(const pathname of ['/poisk-turov','/poisk-turov//','/poisk-turov/index.php/','/poisk-turov/child/',
  '/poisk-turov/../poisk-turov/','/poisk-turov/%2e%2e/poisk-turov/','/POISK-TUROV/','//poisk-turov/',
  '/poisk-turov-old/','/prototype-search/','/visual-search/','/','https://evil.test/poisk-turov/']){
  const c=configure(pathname,production).V2_CONFIG;noProduction(c);
  assert.equal(c.search3TVSessionEnabled,false,pathname);assert.equal(c.search3MessengerEnabled,false,pathname);assert.equal(c.localTvCatalogEnabled,false,pathname);
 }
});

test('Offline, unknown and ambiguous duplicate scenarios cannot activate production',()=>{
 for(const scenario of ['snapshot','flights','mixed','family','incomplete','price-change','unavailable','expired','flight-error','empty','recorded','demo','unknown','LIVE']){
  const c=configure('/poisk-turov/',production,'?scenario='+encodeURIComponent(scenario)).V2_CONFIG;
  noProduction(c);assert.equal(c.search3TVSessionEnabled,false,scenario);assert.equal(c.search3MessengerEnabled,false,scenario);
 }
 for(const search of ['?scenario','?scenario=&country=78','?scenario=live&scenario=family',
  '?scenario=family&scenario=live','?scenario=live&scenario=live','?scenario[]=live',
  '?scenario%5B0%5D=demo&scenario=live','?scenario=live&scenario%5B%5D=live']){
  const c=configure('/poisk-turov/index.php',production,search).V2_CONFIG;noProduction(c);
  assert.equal(c.search3TVSessionEnabled,false,search);assert.equal(c.search3MessengerEnabled,false,search);
  assert.equal(c.localTvCatalogEnabled,false,search);
 }
});

test('NEXT and LOCAL opt-ins preserve their existing behavior without production-only flags',()=>{
 for(const [pathname,tv,messenger,content] of [
  ['/_preview/search3-next-candidate/visual-search/',true,true,true],
  ['/_preview/search3-next-candidate/prototype-search/',false,false,true],
  ['/_preview/search3-local-candidate/prototype-search/',false,false,true],
  ['/_preview/search3-local-candidate/visual-search/',false,false,true],
  ['/_preview/search3-design-v2-candidate/visual-search/',false,false,false]]){
  for(const dataset of [marked,production]){
   const c=configure(pathname,dataset).V2_CONFIG;noProduction(c);
   assert.equal(c.search3TVSessionEnabled,tv,pathname);assert.equal(c.search3MessengerEnabled,messenger,pathname);assert.equal(c.localTvCatalogEnabled,content,pathname);
  }
 }
 const c=configure('/_preview/search3-next-candidate/visual-search/',marked,'?scenario=family').V2_CONFIG;
 assert.equal(c.search3TVSessionEnabled,true,'the existing preview gate is unchanged');noProduction(c);
});

test('Production content reads use the fixed existing same-origin reader with its unchanged credentials and shape',async()=>{
 for(const pathname of ['/poisk-turov/','/poisk-turov/index.php']){
  const window=configure(pathname,production);window.V2_CONFIG.localTvCatalogApi='https://evil.test/reader';
  const x=contentOwner(window);assert.equal(await x.api.refreshHotelContent([101,101,-1,'101']),true);
  assert.equal(x.calls.length,1);const {url,options}=x.calls[0],target=new URL(url,window.location.href);
  assert.equal(target.origin,window.location.origin);assert.equal(target.pathname,endpoint);
  assert.deepEqual(target.searchParams.getAll('oldLocalHotelIds[]'),['101']);
  assert.equal(options.credentials,'same-origin');assert.equal(options.cache,'no-store');assert.equal(options.headers.Accept,'application/json');
  assert.equal(options.method,undefined);assert.equal(options.body,undefined);assert.equal(x.timers.size,0);
 }
});

test('Actual canonical and data owners restore profiles and project supplier rows on both marked production entries',async()=>{
 const trip={origin:'Москва',country:'78',from:'2027-03-29',to:'2027-03-29',minNights:7,maxNights:7,adults:2,ages:[]};
 for(const pathname of ['/poisk-turov/','/poisk-turov/index.php']){
  const window=configure(pathname,production),x=canonicalDataOwner(window);
  assert.ok(x.owner,'production must instantiate the real canonical owner');
  const saved=await x.api.savedHotels([901],trip);
  assert.equal(saved.length,1);assert.equal(saved[0].id,901);assert.equal(saved[0].name,'Канонический отель');
  assert.equal(saved[0].country,'78');assert.equal(saved[0].offers.length,0);
  const supplierRows=[{id:101,provider:'tourvisor',name:'Supplier display name is not authoritative',tours:[{
   id:'tv-fixture-1',provider:'tourvisor',price:{value:119887,currency:'RUB'},date:trip.from,nights:7,
   roomType:'PREMIUM GARDEN VIEW',meal:{name:'AI'},operator:{name:'Интурист'}}]}];
  assert.equal(x.owner.read(supplierRows,{}).length,0,'unlinked supplier rows cannot appear as own catalogue profiles');
  for(let turn=0;turn<20&&x.timers.size;turn++)await Promise.resolve();
  const hotels=x.api.project(x.owner.read(supplierRows,{}),trip);
  assert.equal(hotels.length,1);assert.equal(hotels[0].id,901);assert.equal(hotels[0].name,'Канонический отель');
  assert.deepEqual(plain(hotels[0].legacyIds),['101']);assert.equal(hotels[0].offers.length,1);
  const offer=hotels[0].offers[0];
  assert.equal(offer.hotelId,901);assert.equal(offer.total,119887);assert.equal(offer.day,trip.from);
  assert.equal(offer.nights,7);assert.equal(offer.adults,2);assert.equal(offer.room,'PREMIUM GARDEN VIEW');
  assert.equal(offer.meal,'Всё включено');assert.equal(offer.mealRaw,'AI');assert.equal(offer.raw.meal.name,'AI');
  assert.equal(offer.operator,'Интурист');assert.equal(offer.provider,'tourvisor');
  assert.equal(x.calls.length,2);assert.equal(x.timers.size,0);
  for(const {url,options} of x.calls){
   const target=new URL(url,window.location.href);assert.equal(target.origin,window.location.origin);
   assert.equal(target.pathname,canonicalEndpoint);assert.equal(options.credentials,'same-origin');
   assert.equal(options.cache,'no-store');assert.equal(options.headers.Accept,'application/json');
   assert.equal(options.method,undefined);assert.equal(options.body,undefined);
  }
 }
});

test('Actual canonical owner rejects unmarked, offline and forged non-exact production paths and preserves preview routes',()=>{
 for(const [pathname,dataset,search,patch] of [
  ['/poisk-turov/',marked,'',{}],['/poisk-turov/index.php',production,'?scenario=family',{}],
  ['/poisk-turov/child/',production,'',{search3ProductionEnabled:true}],
  ['/poisk-turov/../poisk-turov/',production,'',{search3ProductionEnabled:true}],
  ['/elsewhere/',production,'',{search3ProductionEnabled:true}],
  ['/poisk-turov/',production,'',{search3ProductionEnabled:'true'}]]){
  const window=configure(pathname,dataset,search);Object.assign(window.V2_CONFIG,patch);
  const x=canonicalDataOwner(window);assert.equal(x.owner,null,pathname);assert.equal(x.calls.length,0);
 }
 for(const pathname of ['/_preview/search3-local-candidate/','/_preview/search3-local-candidate/prototype-search/',
  '/_preview/search3-next-candidate/visual-search/']){
  const x=canonicalDataOwner(configure(pathname,marked));assert.ok(x.owner,pathname);assert.equal(x.calls.length,0);
 }
});

test('Disabled or unsupported production paths make no content request even with forged additional config',async()=>{
 for(const [pathname,dataset,patch] of [
  ['/poisk-turov/',marked,{}],['/poisk-turov/',{search3Production:'enabled'},{}],
  ['/poisk-turov/',production,{localTvCatalogEnabled:false}],
  ['/poisk-turov/child/',production,{search3ProductionEnabled:true,localTvCatalogEnabled:true}],
  ['/poisk-turov/../poisk-turov/',production,{search3ProductionEnabled:true,localTvCatalogEnabled:true}],
  ['/elsewhere/',production,{search3ProductionEnabled:true,localTvCatalogEnabled:true}]]){
  const window=configure(pathname,dataset);Object.assign(window.V2_CONFIG,patch);
  const x=contentOwner(window);assert.equal(await x.api.refreshHotelContent([101]),false);assert.equal(x.calls.length,0,pathname);assert.equal(x.timers.size,0);
 }
});

test('Existing NEXT and LOCAL content paths keep their original readers',async()=>{
 for(const prefix of ['/_preview/search3-next-candidate/','/_preview/search3-local-candidate/']){
  const window=configure(prefix+'prototype-search/',marked),x=contentOwner(window);
  assert.equal(await x.api.refreshHotelContent([101]),true);assert.equal(x.calls.length,1);
  assert.equal(new URL(x.calls[0].url,window.location.href).pathname,prefix+'data/local-tv-catalog-read-v1.php');
 }
});

(async()=>{for(const {name,run} of tests){await run();console.log('PASS '+name);}console.log('search3 production mode: '+tests.length+' groups PASS; fixed existing transports, explicit route opt-in and no supplier/lead IO');})()
 .catch(error=>{console.error(error);process.exitCode=1;});
