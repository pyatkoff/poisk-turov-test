'use strict';
// Full LIVE data owner, deterministic intercepted transports and timers only.
// The probe exposes state to this test; it is never added to the runtime file.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const target=path.resolve(__dirname,'../v2/prototype-search/data.js');
const trip=()=>({origin:'Москва',country:'4',from:'2026-10-13',to:'2026-10-19',minNights:7,maxNights:7,adults:2,ages:[12,4]});
const copy=value=>JSON.parse(JSON.stringify(value));
const gate=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
const flush=async()=>{for(let i=0;i<16;i++)await Promise.resolve();};
const rows=(n=1)=>Array.from({length:n},(_,i)=>({id:101+i,name:'Fictional '+i,tours:[{id:'tv-'+i,provider:'tourvisor',price:120000+i,date:'2026-10-13',nights:7,meal:'AI',roomType:'TEST'}]}));
const eventSnapshot=event=>{
 const value=copy(event);
 if(Array.isArray(value.hotels))value.hotels=value.hotels.map(h=>({id:h.id,offers:h.offers.map(o=>({key:o.key,total:o.total,provider:o.provider,day:o.day,nights:o.nights,ages:o.ages}))}));
 return value;
};
// Original algorithms are test-only oracles; they call the real unchanged
// validators/constructors, so request IDs, raw identity and variant are observed.
const previousDestination=`function(s,filters){
 const regionIds=[],subregionIds=[];
 for(const name of filters.resorts||[]){
  const found=(catalog.regions[String(s.country)]||[]).filter(row=>row.name===name);
  if(found.length!==1)throw new Error('Выберите курорт из канонического справочника.');
  const row=found[0],target=row.kind==='region'?regionIds:row.kind==='subregion'?subregionIds:null;
  if(!target)throw new Error('Некорректный тип направления.');
  target.push(...tourvisorIds(row));
 }
 const unique=ids=>[...new Set(ids)].sort((a,b)=>Number(a)-Number(b));
 return {regionIds:unique(regionIds),subregionIds:unique(subregionIds)};
}`;
const previousProject=`function(list,s){return list.map(rawHotel=>{
 const h=hotel(rawHotel,s);
 h.offers=(rawHotel.tours||[]).map((t,i)=>offer(t,h,s,i)).filter(Boolean);
 return h;
}).filter(h=>h.offers.length);}`;
const previousMeal=`function(value){
 const label=text(value).trim(),record=catalog.meals.find(x=>value?.id&&String(x.id)===String(value.id)
  ||text(x).trim().toLocaleLowerCase('ru-RU')===label.toLocaleLowerCase('ru-RU'));
 const candidates=[label,text(value?.fullName),text(value?.russianName),text(record?.fullName),text(record?.russianName),text(record)]
  .map(value=>value.trim()).filter(Boolean);
 for(const candidate of candidates){
  const normalized=candidate.toUpperCase().replace(/\\s+/g,' ');
  if(mealAliases[normalized])return mealAliases[normalized];
  const coded=normalized.match(/^(RO|BB|HB|FB|AI|UAI|ALL)\\s*(?:[-—:]\\s*|\\s+).+$/);
  if(coded&&mealAliases[coded[1]])return mealAliases[coded[1]];
 }
 return candidates.find(candidate=>!(/^[A-Z]{1,7}\\+?$/.test(candidate)))||candidates[0]||'';
}`;
const previousSupplierMealNativeId=`function(value){
 const direct=value&&typeof value==='object'?String(value.id??''):'';
 if(/^[1-9][0-9]*$/.test(direct))return direct;
 const candidates=[text(value),text(value?.fullName),text(value?.russianName)].map(v=>v.trim()).filter(Boolean);
 if(!candidates.length)return '';
 const matches=catalog.meals.filter(row=>{
  const labels=[text(row),text(row?.fullName),text(row?.russianName)].map(v=>v.trim()).filter(Boolean);
  return candidates.some(candidate=>labels.includes(candidate));
 }).map(row=>String(row?.id??'')).filter(id=>/^[1-9][0-9]*$/.test(id));
 return [...new Set(matches)].length===1?matches[0]:'';
}`;
const previousMealPlan=`function(t,provider=String(t?.provider||'tourvisor').toLowerCase()){
 const explicit=t&&t.searchMealPlan;
 if(explicit&&Number.isSafeInteger(explicit.id)&&explicit.id>0&&typeof explicit.code==='string'&&/^[a-z0-9][a-z0-9-]{0,63}$/.test(explicit.code)
  &&typeof explicit.nameRu==='string'&&explicit.nameRu.trim()&&explicit.nameRu.length<=255){
  const known=catalog.mealPlans.find(plan=>plan.id===explicit.id);
  if(!known||known.code===explicit.code&&known.nameRu===explicit.nameRu.trim())return Object.freeze({id:explicit.id,code:explicit.code,nameRu:explicit.nameRu.trim()});
  return null;
 }
 if(provider!=='tourvisor'||catalog.mealPlanAvailable!==true)return null;
 const native=(${previousSupplierMealNativeId})(t?.meal);if(!native)return null;
 const matches=catalog.mealPlans.filter(plan=>plan.nativeIds.includes(native));
 return matches.length===1?Object.freeze({id:matches[0].id,code:matches[0].code,nameRu:matches[0].nameRu}):null;
}`;
function harness(source,{providers=false}={}){
 const log=[],events=[],calls=[],timers=new Map(),http=[];let id=0,clock=1791878400000,status={progress:100},inventory=rows(),onEvent=()=>{},respond=null,fetchGate=null;
 const window={location:{href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/',origin:'https://anytoour.ru'},
  V2_CONFIG:providers?{andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'}:{},
  V2Runtime:{setSearchId(value){log.push(['searchId',value]);},async api(action,body){calls.push({action,body:copy(body)});log.push(['api',action,copy(body)]);if(respond)return respond(action,body);if(action==='search_start')return{searchId:17};if(action==='search_status')return status;if(action==='search_results')return inventory;if(action==='search_continue')return{};throw Error('Unexpected API '+action);}},
  Search3CanonicalProfilesV1:{create(publish){return {reset(){log.push(['reset']);},read(value){log.push(['read',value.length]);return value;},clearOffers(value){log.push(['clearOffers',value]);},refresh(){log.push(['refresh']);publish();}}; }},
  dispatchEvent(event){log.push(['dispatch',event.type,copy(event.detail)]);}};
 const context=vm.createContext({window,URL,URLSearchParams,AbortController,Map,Set,WeakMap,Promise,structuredClone,
  Date:class extends Date{static now(){return clock;}},
  CustomEvent:class{constructor(type,{detail}){this.type=type;this.detail=detail;}},
  fetch:async(url,options)=>{
   assert(providers&&String(url).includes('api-andromeda-search3-preview.php'),'no unexpected network path');
   const body=JSON.parse(options.body);http.push(copy(body));log.push(['http',copy(body)]);
   if(fetchGate)await fetchGate;
   return {ok:true,status:200,json:async()=>({ok:true,data:{provider:'andromeda',generation:body.generation,hotels:[],
    date_range:{from:body.params.dateFrom,to:body.params.dateTo},search_ref:'a'.repeat(64),page:body.page,pages_count:2,
    status:'complete',selection_enabled:false,first_page_only:false,received_offers:0,mapped_offers:0}})};
  },
  setTimeout(fn,delay){const key=++id;timers.set(key,fn);log.push(['timer',key,delay]);return key;},
  clearTimeout(key){log.push(['clearTimer',key]);timers.delete(key);}});
 const marker='  root.AnyTourPrototypeData=Object.freeze(';
 assert.equal(source.split(marker).length,2,'one actual data-owner export');
 const probe=`  root.__test={run:()=>activeSearch,poll:run=>pollSearch(run),state:()=>({generation,searchId,context,searchParams,currentSupplierScope}),destinationScope,previousDestination:${previousDestination},previousProject:${previousProject},previousMeal:${previousMeal},previousMealPlan:${previousMealPlan}};\n`;
 vm.runInContext(source.replace(marker,probe+marker),context,{filename:'prototype-search/data.js'});
 const api=window.AnyTourPrototypeData;
 api.catalog.departures=[{id:1,name:'Москва'}];api.catalog.countries=[{id:4,name:'Турция',tourvisorIds:['4']}];
 const callback=event=>{events.push(eventSnapshot(event));log.push(['event',eventSnapshot(event)]);onEvent(event);};
 return {api,log,events,calls,http,timers,probe:window.__test,callback,
  setEvent:fn=>onEvent=fn,setAPI:fn=>respond=fn,setStatus:value=>status=value,setRows:value=>inventory=value,setFetchGate:value=>fetchGate=value,
  tick:async()=>{const entry=timers.entries().next().value;assert(entry,'a timer is scheduled');timers.delete(entry[0]);await entry[1]();},
  advance:ms=>clock+=ms,
  capture(){const run=window.__test.run();return {log,events,calls,http,state:copy(window.__test.state()),run:run?copy({generation:run.generation,search:run.search,hotelIds:run.hotelIds,filters:run.filters,pending:run.pending,searchId:run.searchId,resumeOnly:run.resumeOnly,continued:run.continued,expired:run.expired,canContinue:run.canContinue,tvCanContinue:run.tvCanContinue,andromedaCanContinue:run.andromedaCanContinue,continueBaseline:run.continueBaseline,continueBaselineTourvisor:run.continueBaselineTourvisor,lastProgress:run.lastProgress,lastRead:run.lastRead,deadline:run.deadline,sourceCounts:run.sourceCounts,aborted:run.controller.signal.aborted}):null,timers:[...timers.keys()]};}};
}
function dataWorkOracles(source){
 const h=harness(source),s=trip();let nameReads=0;
 const regions=Array.from({length:80},(_,i)=>({get name(){nameReads++;return 'Resort '+i;},kind:i%2?'subregion':'region',tourvisorIds:[String(i+1)]}));
 h.api.catalog.regions['4']=regions;
 const observed=fn=>{try{return {value:copy(fn())};}catch(error){return {error:error.message};}};
 const scope=selected=>{
  const filters={resorts:selected};nameReads=0;
  const before=observed(()=>h.probe.previousDestination(s,filters)),previousReads=nameReads;nameReads=0;
  const after=observed(()=>h.probe.destinationScope(s,filters)),currentReads=nameReads;
  assert.deepEqual(after,before,'destination scope/ambiguity/errors retain original semantics');
  return {previousReads,currentReads};
 };
 const selected=Array.from({length:20},(_,i)=>'Resort '+i);
 assert.deepEqual(scope(selected),{previousReads:1600,currentReads:80});
 assert.deepEqual(scope([]),{previousReads:0,currentReads:0});
 scope(['Resort 1','Resort 0','Resort 1']);scope(['missing']);
 for(const items of [
  [{name:'same',kind:'region',tourvisorIds:['1']},{name:'same',kind:'subregion',tourvisorIds:['2']}],
  [{name:'same',kind:'country',tourvisorIds:['1']}],
  [{name:'same',kind:'region',tourvisorIds:[]}],
  [{name:'same',kind:'region',tourvisorIds:['01']}],
  [{name:'same',kind:'region',tourvisorIds:['10','2','2']}],
  Object.assign(new Array(3),{1:{name:'same',kind:'region',tourvisorIds:['1']}})
 ]){h.api.catalog.regions['4']=items;scope(['same']);}
 const mutable={name:'before',kind:'region',tourvisorIds:['1']};
 h.api.catalog.regions['4']=[mutable];scope(['before']);mutable.name='after';mutable.tourvisorIds=['3'];scope(['after']);scope(['before']);
 h.api.catalog.regions['4'].push({...mutable});scope(['after']);
 h.api.catalog.regions['4']=[{name:NaN,kind:'region',tourvisorIds:['1']}];scope([NaN]);
 h.api.catalog.regions['4']=[{name:'same',kind:'subregion',tourvisorIds:['4']}];scope(['same']);
 h.api.catalog.regions={};scope(['same']);scope([]);

 const stayTour={id:'cached:andromeda:fixture',provider:'andromeda',price:150000,date:'2026-10-13',nights:7,
  meal:{name:'Ultra All Inclusive'},roomType:'DELUXE SEA VIEW',placement:'2 ADL',
  searchMealPlan:{id:7,code:'all-inclusive',nameRu:'Всё включено'},
  localStay:{source:'anytour-hotel-stay-v2',
   meal:{kind:'meal',id:901,hotelId:401,nameRu:'Премиальное питание',localKey:'meal-901',revision:3},
   room:{kind:'room',id:701,hotelId:401,nameRu:'Делюкс с видом на море',localKey:'room-701',revision:4}}};
 const stayOffer=h.api.project([{id:401,name:'Stay labels',tours:[stayTour]}],s)[0].offers[0];
 assert.equal(stayOffer.meal,'Премиальное питание');assert.equal(stayOffer.room,'Делюкс с видом на море');
 assert.deepEqual({id:stayOffer.mealPlanId,code:stayOffer.mealPlanCode,facet:stayOffer.mealFacet,raw:stayOffer.mealRaw},
  {id:7,code:'all-inclusive',facet:'Всё включено',raw:'Ultra All Inclusive'},'local label cannot change the global meal facet');
 assert.strictEqual(stayOffer.raw,stayTour);assert.equal(stayTour.meal.name,'Ultra All Inclusive');assert.equal(stayTour.roomType,'DELUXE SEA VIEW');
 const supplierTour=structuredClone(stayTour);delete supplierTour.localStay;
 const supplierOffer=h.api.project([{id:401,name:'Stay labels',tours:[supplierTour]}],s)[0].offers[0];
 const nonPresentation=offer=>Object.fromEntries(Object.entries(copy(offer)).filter(([key])=>!['meal','room','raw'].includes(key)));
 assert.deepEqual(nonPresentation(stayOffer),nonPresentation(supplierOffer),'local labels cannot change offer identity, price, scope or payload fields');
 const invalidStayCases=[
  stay=>{stay.source='foreign';},stay=>{stay.meal.kind='room';},stay=>{stay.meal.hotelId=402;},stay=>{stay.meal.id=0;},
  stay=>{stay.meal.revision='3';},stay=>{stay.meal.nameRu='';},stay=>{stay.meal.nameRu='x'.repeat(256);},
  stay=>{stay.meal.nameRu='bad\nname';},stay=>{stay.meal.localKey='';},stay=>{stay.meal.localKey='x'.repeat(129);},
  stay=>{stay.meal.localKey='bad\u0000key';}
 ];
 for(const mutate of invalidStayCases){
  const tour=structuredClone(stayTour);mutate(tour.localStay);
  const projected=h.api.project([{id:401,tours:[tour]}],s)[0].offers[0];
  assert.equal(projected.meal,'Всё включено','invalid local meal stays on the existing global presentation fallback');
  assert.equal(projected.mealFacet,'Всё включено');assert.equal(projected.mealRaw,'Ultra All Inclusive');assert.strictEqual(projected.raw,tour);
 }
 const invalidRoomCases=[
  stay=>{stay.room.kind='meal';},stay=>{stay.room.hotelId=402;},stay=>{stay.room.id=0;},stay=>{stay.room.revision=0;},
  stay=>{stay.room.nameRu='bad\u007fname';},stay=>{stay.room.localKey='';}
 ];
 for(const mutate of invalidRoomCases){
  const tour=structuredClone(stayTour);mutate(tour.localStay);
  const projected=h.api.project([{id:401,tours:[tour]}],s)[0].offers[0];
  assert.equal(projected.room,'DELUXE SEA VIEW');assert.equal(projected.meal,'Премиальное питание');assert.strictEqual(projected.raw,tour);
 }
 const rawOnly=structuredClone(stayTour);delete rawOnly.searchMealPlan;delete rawOnly.localStay;
 const rawOffer=h.api.project([{id:401,tours:[rawOnly]}],s)[0].offers[0];
 assert.equal(rawOffer.meal,'Ультра всё включено');assert.equal(rawOffer.room,'DELUXE SEA VIEW');assert.strictEqual(rawOffer.raw,rawOnly);

 const sameLookup=(actual,expected,message)=>actual.error||expected.error?assert.equal(Boolean(actual.error),Boolean(expected.error),message):assert.deepEqual(actual,expected,message);
 const compareMeal=value=>sameLookup(observed(()=>h.api.meal(value)),observed(()=>h.probe.previousMeal(value)),'indexed meal keeps first ID/label resolution');
 const comparePlan=(tour,provider)=>sameLookup(observed(()=>h.api.mealPlan(tour,provider)),observed(()=>h.probe.previousMealPlan(tour,provider)),'indexed plan keeps explicit/native ambiguity');
 const catalogMeals=[
  {id:1,name:'BB',fullName:'Breakfast'},
  {id:2,name:'Target',fullName:'AI'},
  {id:3,name:'same',russianName:'Первый'},
  {id:4,name:'same',russianName:'Второй'},
  {id:4,name:'duplicate id',fullName:'same-id'}
 ];
 const catalogPlans=[
  {id:7,code:'breakfast',nameRu:'Завтраки',nativeIds:['1']},
  {id:8,code:'all-inclusive',nameRu:'Всё включено',nativeIds:['2']},
  {id:9,code:'duplicate-a',nameRu:'Дубликат',nativeIds:['3']},
  {id:10,code:'duplicate-b',nameRu:'Дубликат',nativeIds:['3']}
 ];
 h.api.catalog.meals=catalogMeals;h.api.catalog.mealPlans=catalogPlans;h.api.catalog.mealPlanAvailable=true;
 for(const value of [{id:2,name:'BB'},{id:2,name:'missing'},{name:'same'},{name:'same-id'},'AI','unknown',null])compareMeal(value);
 for(const tour of [
  {provider:'tourvisor',meal:{id:1,name:'ignored'}},
  {provider:'tourvisor',meal:{name:'Target'}},
  {provider:'tourvisor',meal:{name:'same'}},
  {provider:'andromeda',meal:{id:1}},
  {searchMealPlan:{id:8,code:'all-inclusive',nameRu:'Всё включено'}},
  {searchMealPlan:{id:8,code:'wrong',nameRu:'Всё включено'}},
  {searchMealPlan:{id:777,code:'future',nameRu:'Будущее'}}
 ])comparePlan(tour);
 assert.throws(()=>h.api.supplierScope({meals:['Дубликат']}),/канонического справочника/);
 assert.equal(h.api.supplierScope({meals:['Завтраки']}).meal,'1');
 assert.equal(h.api.observationScopeSupported(s,{meals:['Завтраки']}),true);
 assert.equal(h.api.observationScopeSupported(s,{meals:['Дубликат']}),false);

 // Directly exposed catalog arrays remain live between owner calls: same-array
 // edits, pushes, sparse rows and inherited slots rebuild the operation index.
 catalogMeals[0].name='RO';catalogMeals[0].fullName='Room Only';compareMeal({id:1,name:'missing'});
 catalogPlans[0].nameRu='Без питания';catalogPlans[0].code='room-only';comparePlan({searchMealPlan:{id:7,code:'room-only',nameRu:'Без питания'}});
 catalogPlans.push({id:11,code:'second-room-only',nameRu:'Без питания',nativeIds:['1']});
 assert.throws(()=>h.api.supplierScope({meals:['Без питания']}),/канонического справочника/);
 assert.equal(h.api.observationScopeSupported(s,{meals:['Без питания']}),false);
 const inheritedMeals=[];Object.setPrototypeOf(inheritedMeals,Object.assign(Object.create(Array.prototype),{1:{id:21,name:'Inherited AI'}}));inheritedMeals.length=3;
 h.api.catalog.meals=inheritedMeals;compareMeal({id:21,name:'missing'});comparePlan({provider:'tourvisor',meal:{id:21}});
 const sparsePlans=new Array(4);sparsePlans[3]={id:22,code:'sparse',nameRu:'Sparse',nativeIds:['21']};
 h.api.catalog.mealPlans=sparsePlans;comparePlan({provider:'tourvisor',meal:{id:21}});

 const mealReads={ids:0,labels:0,planIds:0},perf=harness(source),perfMeals=[],perfPlans=[];
 for(let i=1;i<=100;i++){
  const id=i;perfMeals.push({get id(){mealReads.ids++;return id;},get name(){mealReads.labels++;return id===100?'Tail meal':'Meal '+id;}});
  perfPlans.push({get id(){mealReads.planIds++;return id;},code:'plan-'+id,nameRu:'Plan '+id,nativeIds:[String(id)]});
 }
 perf.api.catalog.meals=perfMeals;perf.api.catalog.mealPlans=perfPlans;perf.api.catalog.mealPlanAvailable=true;
 const perfTour={id:'tail',provider:'tourvisor',price:120000,date:'2026-10-13',nights:7,meal:{id:100,name:'Tail meal'},searchMealPlan:{id:100,code:'plan-100',nameRu:'Plan 100'}};
 for(let i=0;i<1000;i++){perf.probe.previousMeal(perfTour.meal);perf.probe.previousMealPlan(perfTour);}
 const previousMealReads={...mealReads};Object.keys(mealReads).forEach(key=>mealReads[key]=0);
 const perfTours=Array.from({length:1000},(_,i)=>({...perfTour,id:'tail-'+i})),projected=perf.api.project([{id:501,name:'Indexed',tours:perfTours}],s);
 assert.equal(projected[0].offers.length,1000);assert.equal(projected[0].offers[999].meal,'Plan 100');
 assert.deepEqual(previousMealReads,{ids:100000,labels:100000,planIds:100000});
 assert.deepEqual(mealReads,{ids:100,labels:100,planIds:100});
 const currentMealReads={...mealReads};

 const work={map:0,filter:0,forEach:0,callbacks:0};
 function counted(array){
  Object.defineProperty(array,'map',{value(fn){work.map++;const result=Array.prototype.map.call(this,(...args)=>{work.callbacks++;return fn(...args);});
   Object.defineProperty(result,'filter',{value(fn){work.filter++;return Array.prototype.filter.call(this,(...args)=>{work.callbacks++;return fn(...args);});}});return result;}});
  Object.defineProperty(array,'forEach',{value(fn){work.forEach++;return Array.prototype.forEach.call(this,(...args)=>{work.callbacks++;return fn(...args);});}});
  return array;
 }
 const list=counted(Array.from({length:100},(_,i)=>({id:101+i,name:'Projection '+i,tours:counted(Array.from({length:10},(_,j)=>({id:i+'-'+j,price:i<90?120000:0,date:'2026-10-13',nights:7,meal:'AI'})))})));
 const before=h.probe.previousProject(list,s),previousWork={...work};
 Object.keys(work).forEach(key=>work[key]=0);
 const after=h.api.project(list,s);assert.deepEqual(copy(after),copy(before));
 assert.deepEqual(previousWork,{map:101,filter:101,forEach:0,callbacks:2200});
 assert.deepEqual(work,{map:0,filter:0,forEach:101,callbacks:1100});
 const currentWork={...work};
 function identities(input){
  const expected=h.probe.previousProject(input,s),actual=h.api.project(input,s);
  assert.deepEqual(copy(actual),copy(expected),'projection order, variant and observable fields match');
  actual.forEach((hotel,i)=>{assert.strictEqual(hotel.raw,expected[i].raw);hotel.offers.forEach((offer,j)=>assert.strictEqual(offer.raw,expected[i].offers[j].raw));});
 }
 identities(list);identities([]);identities([{id:1,name:'No tours'},{id:2,tours:[]}]);
 for(const malformed of [{0:list[0],length:1},'not an array',null]){
  assert.throws(()=>h.probe.previousProject(malformed,s),{name:'TypeError'});
  assert.throws(()=>h.api.project(malformed,s),{name:'TypeError'});
 }
 for(const tours of ['invalid',{0:list[0].tours[0],length:1},true]){
  assert.throws(()=>h.probe.previousProject([{id:1,tours}],s),{name:'TypeError'});
  assert.throws(()=>h.api.project([{id:1,tours}],s),{name:'TypeError'});
 }
 const sparse=new Array(5),tours=new Array(6);
 tours[1]={id:'kept',price:100000,date:'2026-10-13',nights:7};tours[3]={id:'rejected',price:0,date:'2026-10-13',nights:7};tours[5]={...tours[1],id:'last'};
 sparse[2]={id:1,tours};sparse[4]={id:2,tours:[]};identities(sparse);
 assert.deepEqual(Array.from(h.api.project(sparse,s)[0].offers,o=>o.variant),[1,5]);
 // map captures the length once and includes inherited occupied slots.
 const inherited=[];Object.setPrototypeOf(inherited,Object.assign(Object.create(Array.prototype),{1:tours[1]}));inherited.length=3;
 identities([{id:3,tours:inherited}]);
 const changing=[{id:4,tours:[tours[1]]}];
 Object.defineProperty(changing[0],'name',{get(){changing.push({id:5,tours:[tours[1]]});return 'append';}});
 assert.equal(h.api.project(changing,s).length,1,'new outer slots past the initial length are not visited');
 return {destinationNameReads:[1600,80],emptySelectionReads:[0,0],mealIndexReads:{previous:previousMealReads,current:currentMealReads,offers:1000},projection:{previous:previousWork,current:currentWork,temporaryArraysRemoved:101},stayPresentation:{accepted:2,invalidRejected:invalidStayCases.length+invalidRoomCases.length,rawIdentity:'identical',mealFacet:'unchanged'},rawIdentity:'identical'};
}
async function characterize(source){
 const records=[];
 async function check(name,body,options){const h=harness(source,options);await body(h);await flush();records.push({name,...h.capture()});}
 await check('fresh search setup, independent snapshots, public descriptors',async h=>{
  const s=trip(),ids=['101'],filters={stars:[5],min:50000,max:180000};await h.api.search(s,h.callback,ids,filters);
  const r=h.probe.run();assert.equal(r.pending,true);assert.equal(r.canContinue,true);assert.equal(r.searchId,17);
  assert.equal(h.calls[0].action,'search_start');assert.deepEqual(h.calls[0].body.childs,[4,12]);
  s.ages[0]=16;ids.push('102');filters.stars[0]=3;
  assert.deepEqual(copy(r.search.ages),[12,4]);assert.deepEqual(copy(r.hotelIds),['101']);assert.deepEqual(copy(r.filters.stars),[5]);
  assert.equal(Object.isFrozen(h.api),true);assert.equal(h.api.search.length,2);assert.equal(h.api.resumeCached.length,2);
  assert.equal(typeof Object.getOwnPropertyDescriptor(h.api,'searchId').get,'function');assert.equal(Object.getOwnPropertyDescriptor(h.api,'search').writable,false);
  assert.deepEqual(h.events.map(e=>e.type),['loading','provider']);assert.deepEqual(h.log.slice(0,4).map(x=>x[0]),['clearTimer','searchId','reset','event']);
 });
 await check('cached resume performs no live start',async h=>{
  assert.equal(await h.api.resumeCached(trip(),h.callback,['101'],{stars:[5]}),true);
  assert.equal(h.calls.length,0);assert.equal(h.timers.size,0);assert.equal(h.probe.run().pending,false);
  assert.equal(h.probe.run().canContinue,false);assert.deepEqual(h.events.map(e=>e.type),['loading','complete']);
  assert.deepEqual(Object.keys(h.events[1].sources),['tourvisor','anex','andromeda','database']);
 });
 await check('invalid parameters do not stop an active search',async h=>{
  await h.api.search(trip(),h.callback);const run=h.probe.run(),before=h.log.length;
  await assert.rejects(h.api.search({...trip(),origin:'missing'},h.callback));
  assert.equal(h.probe.run(),run);assert.equal(h.log.length,before);
 });
 for(const method of ['search','resumeCached'])await check(method+' loading cancellation is passive',async h=>{
  h.setEvent(e=>{if(e.type==='loading')h.api.stop();});const result=await h.api[method](trip(),h.callback);
  assert.equal(result,method==='resumeCached'?false:undefined);assert.equal(h.calls.length,0);assert.equal(h.timers.size,0);
 });
 await check('provider callback cancellation prevents TV start',async h=>{
  h.setEvent(e=>{if(e.type==='provider')h.api.stop();});await h.api.search(trip(),h.callback);assert.equal(h.calls.length,0);
 });
 await check('throwing loading callback retains rejection semantics',async h=>{
  h.setEvent(()=>{throw Error('callback failure');});await assert.rejects(h.api.search(trip(),h.callback),/callback failure/);assert.equal(h.calls.length,0);
 });
 await check('late started response after stop is ignored',async h=>{
  const g=gate();h.setAPI(()=>g.promise);const search=h.api.search(trip(),h.callback);await flush();h.api.stop();g.resolve({searchId:91});await search;
  assert.equal(h.timers.size,0);assert.equal(h.api.searchId,0);
 });
 await check('invalid search id completes partially',async h=>{
  h.setAPI(()=>({searchId:'invalid'}));await h.api.search(trip(),h.callback);
  assert.equal(h.events.at(-1).type,'complete');assert.equal(h.events.at(-1).partial,true);assert.equal(h.probe.run().pending,false);
 });
 await check('progress first portion keeps polling cadence',async h=>{
  h.setStatus({progress:25});await h.api.search(trip(),h.callback);await h.tick();
  assert.equal(h.calls.at(-1).body.limit,25);assert.equal(h.log.at(-1)[2],2500);assert(!h.events.some(e=>e.type==='complete'));
 });
 await check('complete waits for both initial sources',async h=>{
  await h.api.search(trip(),h.callback);const a=gate(),b=gate(),run=h.probe.run();run.anex=a.promise;run.andromeda=b.promise;
  const polling=h.tick();await flush();assert(!h.events.some(e=>e.type==='complete'));a.resolve();await flush();assert(!h.events.some(e=>e.type==='complete'));
  b.resolve();await polling;assert.equal(h.events.at(-1).type,'complete');assert.equal(h.events.at(-1).union.offers,1);
  assert.equal(h.events.at(-1).canContinue,true);assert.equal(run.pending,false);
 });
 await check('stop during initial settlement cannot publish complete',async h=>{
  await h.api.search(trip(),h.callback);const g=gate();h.probe.run().anex=g.promise;const polling=h.tick();await flush();h.api.stop();g.resolve();await polling;
  assert(!h.events.some(e=>e.type==='complete'));
 });
 for(const type of ['progress','results'])await check(type+' callback cancellation prevents downstream work',async h=>{
  await h.api.search(trip(),h.callback);h.setEvent(e=>{if(e.type===type)h.api.stop();});await h.tick();assert(!h.events.some(e=>e.type==='complete'));
 });
 await check('TV result cap disables further TV continuation',async h=>{
  h.setRows(rows(5000));await h.api.search(trip(),h.callback);await h.tick();const last=h.events.at(-1);
  assert.equal(last.resultLimitReached,true);assert.equal(last.canContinue,false);assert.equal(last.union.hotels,5000);
  // Keep the differential record compact while retaining all other events/state.
  for(const e of h.events)if(Array.isArray(e.hotels))e.hotels={count:e.hotels.length,first:e.hotels[0],last:e.hotels.at(-1)};
  for(const item of h.log)if(item[0]==='event'&&Array.isArray(item[1].hotels))item[1].hotels={count:item[1].hotels.length,first:item[1].hotels[0],last:item[1].hotels.at(-1)};
 });
 await check('TV continuation with growth keeps exact TV receipt',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();h.setRows(rows(2));assert.equal(await h.api.continueSearch(),true);
  const last=h.events.at(-1);assert.deepEqual(last.continuationGrowth,{before:{hotels:1,offers:1},after:{hotels:2,offers:2},grew:true});
  assert.equal(last.canContinue,true);assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);
 });
 await check('TV continuation without growth becomes exhausted',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();await h.api.continueSearch();
  assert.equal(h.events.at(-1).continuationGrowth.grew,false);assert.equal(h.events.at(-1).canContinue,false);assert.equal(await h.api.continueSearch(),false);
 });
 await check('double Continue preserves single in-flight transport',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();const g=gate();h.setAPI((action)=>action==='search_continue'?g.promise:action==='search_status'?{progress:100}:rows(2));
  const first=h.api.continueSearch();assert.equal(await h.api.continueSearch(),false);g.resolve({});await first;
  assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);
 });
 await check('lost Continue response recovers by reads, not repeat start',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();let fail=true;
  h.setAPI(action=>{if(action==='search_continue'&&fail){fail=false;throw Error('lost response');}if(action==='search_status')return{progress:100};return rows(2);});
  assert.equal(await h.api.continueSearch(),false);assert.equal(h.events.at(-1).retryRead,true);
  assert.equal(await h.api.continueSearch(),true);assert.equal(h.calls.filter(c=>c.action==='search_continue').length,1);
 });
 await check('expired Continue cannot restart',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();h.setAPI(()=>{throw Object.assign(Error('expired'),{status:410});});
  assert.equal(await h.api.continueSearch(),false);assert.equal(h.probe.run().expired,true);assert.equal(await h.api.continueSearch(),false);
 });
 await check('initial provider page and explicit provider-only continuation',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();assert.deepEqual(h.http.map(c=>c.page),[1]);
  assert.equal(h.http[0].params.dateTo,'2026-10-19');await h.api.continueSearch();assert.equal(h.probe.run().tvCanContinue,false);
  assert.deepEqual(h.http.map(c=>c.page),[1,2]);
 },{providers:true});
 await check('provider-only settlement publishes union growth',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();h.probe.run().tvCanContinue=false;
  assert.equal(await h.api.continueSearch(),true);assert.deepEqual(h.http.map(c=>c.page),[1,2]);
  assert.equal(h.calls.filter(c=>c.action==='search_continue').length,0);assert.equal(h.events.at(-1).continued,true);
  assert.equal(h.events.at(-1).canContinue,false);assert.equal(h.events.at(-1).continuationGrowth.grew,false);
 },{providers:true});
 await check('provider-only pending stop cannot finalize stale generation',async h=>{
  await h.api.search(trip(),h.callback);await h.tick();h.probe.run().tvCanContinue=false;const g=gate();h.setFetchGate(g.promise);
  const n=h.events.filter(e=>e.type==='complete').length,pending=h.api.continueSearch();await flush();h.api.stop();g.resolve();assert.equal(await pending,false);
  assert.equal(h.events.filter(e=>e.type==='complete').length,n);
 },{providers:true});
 await check('new search invalidates older pending start and callback',async h=>{
  const g=gate();let starts=0;h.setAPI(()=>++starts===1?g.promise:{searchId:33});
  const old=h.api.search(trip(),h.callback);await flush();await h.api.search({...trip(),adults:3},h.callback);g.resolve({searchId:17});await old;
  assert.equal(h.api.searchId,33);assert.equal(h.probe.run().search.adults,3);assert.equal(h.timers.size,1);
 });
 return records;
}
async function selectedHotelRestoration(){
 const app=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
 const start=app.indexOf('async function restoreURLHotel(){'),end=app.indexOf('\nasync function bootRealData()',start);
 assert(start>=0&&end>start,'existing URL hotel restoration owner');
 const calls=[],cache=new Map(),selected={country:'4',hotelId:0,hotelIds:[2001,2002]},context={
  state:{filters:selected,search:{country:'4'}},draft:{origin:'Москва'},catalogLoadGeneration:0,
  currentDraftDestination:()=>selected,destinationIds:d=>d.hotelIds?.length?d.hotelIds:d.hotelId?[d.hotelId]:[],
  destinationHotel:id=>cache.get(id),destinationHotels:cache,hotelRestorePending:false,modalType:'',
  updateSearchUI(){},renderResults(){},renderDestination(){},
  data:{restoreHotel:async(id,country)=>{calls.push([id,country]);return {id,country,legacyIds:[String(id+5000)]};}}
 };
 vm.createContext(context);vm.runInContext(app.slice(start,end),context);
 await context.restoreURLHotel();
 assert.deepEqual(calls,[[2001,'4'],[2002,'4']],'multi-own-ID reload restores every exact canonical link');
 assert.deepEqual([...cache.keys()],[2001,2002]);assert.equal(context.hotelRestorePending,false);
 calls.length=0;cache.delete(2002);context.data.restoreHotel=async(id,country)=>{calls.push([id,country]);throw Error('fixture partial failure');};
 await context.restoreURLHotel();assert.deepEqual(calls,[[2002,'4']],'retry does not reread verified selections');
 assert.deepEqual(selected.hotelIds,[2001,2002],'partial failure never drops an exact selected ID');
 const rhs=app.match(/\$\('\.search-submit'\)\.disabled=([^;]+);\n renderCatalogError/)[1];
 const disabled=()=>vm.runInNewContext(rhs,{...context,place:selected,catalogReady:true,data:{preview:false}});
 assert.equal(disabled(),true,'one unresolved selected hotel blocks broad or partial supplier search');
 context.data.restoreHotel=async(id,country)=>{calls.push([id,country]);return {id,country,legacyIds:[String(id+5000)]};};
 await context.restoreURLHotel();assert.equal(disabled(),false,'successful explicit retry restores submit');
 cache.clear();calls.length=0;let pending=gate();context.data.restoreHotel=async(id,country)=>{calls.push([id,country]);await pending.promise;return {id,country,legacyIds:[String(id+5000)]};};
 const restoring=context.restoreURLHotel();await flush();const duplicated=context.restoreURLHotel();await duplicated;
 assert.equal(calls.length,2,'duplicate restoration shares the bounded in-progress work');
 selected.country='100';selected.hotelIds=[3001];pending.resolve();await restoring;
 assert.equal(cache.size,0,'late former-country profiles never enter the current selection cache');
 selected.country='4';selected.hotelIds=[2001];selected.hotelId=2001;pending=gate();
 const changedSelection=context.restoreURLHotel();await flush();selected.hotelIds=[2002];selected.hotelId=2002;pending.resolve();await changedSelection;
 assert.equal(cache.size,0,'late former-selection profiles never enter the current selection cache');
 context.data.restoreHotel=async()=>({id:9999,country:'4',legacyIds:['8999']});await context.restoreURLHotel();
 assert.equal(cache.size,0,'a different own ID cannot substitute the requested selection');
 context.data.restoreHotel=async()=>({id:2002,country:'100',legacyIds:['7002']});await context.restoreURLHotel();
 assert.equal(cache.size,0,'a different country cannot substitute the requested selection');
 const choiceStart=app.indexOf('function restoredDestinationChoice(value){'),choiceEnd=app.indexOf('\nfunction openDeparture(',choiceStart);
 Object.assign(context,{structuredClone,countryNames:{'4':'Турция'},catalogReady:true});vm.runInContext(app.slice(choiceStart,choiceEnd),context);
 cache.clear();selected.country='4';selected.hotelIds=[2001,2002];selected.hotelId=0;
 const restored=value=>JSON.parse(JSON.stringify(context.restoredDestinationChoice(value)));
 assert.deepEqual(restored({...selected,resorts:[]}).hotelIds,[2001,2002],'unresolved draft IDs survive passive history recovery');
 context.countryNames={};context.catalogReady=false;
 assert.deepEqual(restored({...selected,resorts:[]}).hotelIds,[2001,2002],'initial catalogue pending does not discard the saved draft');
 context.countryNames={'4':'Турция'};context.catalogReady=true;cache.set(2002,{id:2002,country:'100',legacyIds:['7002']});
 assert.deepEqual(restored({...selected,resorts:[]}).hotelIds,[2001],'known foreign-country metadata never joins the restored draft');
 assert.equal(restored({country:'javascript:4',hotelIds:[2001],resorts:[]}).country,'4');
 console.log('PASS selected hotel URL restoration: multi-ID, partial failure/retry, duplicate and stale context, exact ID/country; supplierHTTP0');
}
function verifiedFlightPairBinding(source){
 const start=source.indexOf('  function andromedaPoint('),end=source.indexOf('  function hasAndromedaQuoteAttempt(',start);
 assert(start>=0&&end>start,'the connected canonical quote projector is exercised');
 const context=vm.createContext({Date,Object,Set,Number,String});vm.runInContext(source.slice(start,end),context);
 const ref=n=>'flight_'+String(n).repeat(32),selection={provider:'andromeda',outbound_ref:ref(3),return_ref:ref(4)};
 const verified=()=>({schema_version:1,provider:'andromeda',local_id:101,selection_enabled:true,booking_enabled:false,
  expires_at:Math.floor(Date.now()/1000)+900,state:'quote_verified',quote_state:'verified',final_price_verified:true,
  flight_selection_required:false,final_price:{amount:'125500',currency:'RUB'},
  flights:[{direction:'0',flight_ref:ref(3)},{direction:'1',flight_ref:ref(4)}]});
 const read=value=>context.normalizeAndromedaQuote(value,101,selection);
 const exact=read(verified());assert(exact);assert.equal(exact.finalPrice.amount,'125500');
 assert.deepEqual(Array.from(exact.flights,f=>f.flightRef),[ref(3),ref(4)],'the final total retains the exact requested pair');
 const reversed=verified();reversed.flights.reverse();assert(read(reversed),'directions, not response array order, bind the pair');
 const invalid=[
  value=>{value.flights[0].flight_ref=ref(4);value.flights[1].flight_ref=ref(3);},
  value=>{value.flights[0].flight_ref=ref(9);},value=>{value.flights[1].flight_ref=ref(8);},
  value=>{value.flights[1].flight_ref=ref(3);},value=>{value.flights[1].direction='0';},
  value=>{delete value.flights[1].flight_ref;},value=>{value.flights[0].flight_ref=null;},
  value=>{value.flights[0].flight_ref='not-an-opaque-ref';},
  value=>{value.flights.push({direction:'0',flight_ref:ref(7)});},value=>{value.flights.pop();},
  value=>{value.local_id=102;},value=>{value.expires_at=Math.floor(Date.now()/1000)-1;},
  value=>{value.final_price_verified=false;},value=>{value.final_price=null;}
 ];
 for(const change of invalid){const value=verified();change(value);assert.equal(read(value),null,'foreign/malformed/expired or unverified totals never replace the chosen pair');}
 const legacy=verified();for(const f of legacy.flights)delete f.flight_ref;
 const old=read(legacy);assert(old);assert(old.flights.every(f=>!Object.hasOwn(f,'flightRef')),'legacy completed cache has no invented refs');
 const pending=verified();Object.assign(pending,{state:'flight_selection_required',quote_state:'unverified',final_price_verified:false,flight_selection_required:true,final_price:null});
 assert.equal(read(pending).finalPrice,null,'pending choices remain unpriced');delete pending.flights[0].flight_ref;assert.equal(read(pending),null);
 const native=verified();native.flights[0].transport_markup_reported={amount:'18.25',currency:'USD',source:'andromeda_transport_detail',aggregation:'unknown'};
 const reported=read(native);assert.equal(reported.finalPrice.amount,'125500');assert.equal(reported.flights[0].transportMarkupReported.currency,'USD','native markup never enters whole-tour arithmetic');
 console.log(JSON.stringify({verifiedPairCases:20,supplierHTTP:0,legacyCompatible:true,priceArithmeticChanged:false}));
 const fresh=()=>({...verified(),verified_at:Math.floor(Date.now()/1000),repricing:{enabled:true,max_pairs:3,used_pairs:1,remaining_pairs:2}});
 const freshQuote=fresh();assert.equal(read(freshQuote).verifiedAt,freshQuote.verified_at,'fresh verification time is retained');
 for(const metadata of [null,{},[],{enabled:true,max_pairs:3,used_pairs:1},{enabled:true,max_pairs:'3',used_pairs:1,remaining_pairs:2},
  {enabled:true,max_pairs:3,used_pairs:-1,remaining_pairs:4},{enabled:true,max_pairs:3,used_pairs:1,remaining_pairs:1},
  {enabled:false,max_pairs:3,used_pairs:1,remaining_pairs:2}]){
  const value=fresh();value.repricing=metadata;assert.equal(read(value),null,'partial or malformed capability never promotes a receipt');
 }
 for(const timestamp of [undefined,0,-1,String(Math.floor(Date.now()/1000)),Math.floor(Date.now()/1000)+10]){
  const value=fresh();value.verified_at=timestamp;assert.equal(read(value),null,'fresh verification timestamp must be current and typed');
 }
 const initial=fresh();assert.equal(context.normalizeAndromedaQuote(initial,101),null,'a fresh capability is issued by pending inventory, never an invented immediate verified pair');
 const missing=fresh();for(const f of missing.flights)delete f.flight_ref;assert.equal(read(missing),null,'fresh verified pair requires both refs');
 console.log(JSON.stringify({freshCapabilityParserCases:16,supplierHTTP:0,legacyCompatible:true}));
}
function quoteHarness(source,provider,{enabled=true}={}){
 const transport=require('./search3-visual-live-fixture.cjs').fixture(),calls=[],warnings=[],events=[],timers=new Map();
 Object.assign(transport.state,{repricingEnabled:enabled,anexPackageChoiceCount:enabled?4:2,samoFlightChoice:true});
 let clock=Date.now(),onReply=null,hold=null,active=0,maxActive=0,timerId=0,quoteDeadline=null,initialPublic=null;
 const window={location:{href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/',origin:'https://anytoour.ru'},
  V2_CONFIG:{anexApi:'/_preview/search3-anex-candidate/api-anex-search3-preview.php',andromedaQuoteApi:'/_preview/search3-anex-candidate/api-andromeda-quote-preview.php'},
  V2Runtime:{},console:{warn(value){warnings.push(String(value));}},Search3CanonicalProfilesV1:{create(){return{};}},
  CustomEvent:class{constructor(type,{detail}){this.type=type;this.detail=detail;}},
  dispatchEvent(event){events.push({type:event.type,detail:copy(event.detail)});}};
 const context=vm.createContext({window,URL,URLSearchParams,AbortController,Map,Set,WeakMap,Promise,structuredClone,
  Date:class extends Date{static now(){return clock;}},setTimeout(fn){const id=++timerId;timers.set(id,fn);return id;},clearTimeout(id){timers.delete(id);},
  fetch:async(url,options)=>{
   const body=JSON.parse(options.body),call={url:String(url),body,signal:options.signal};calls.push(call);active++;maxActive=Math.max(maxActive,active);
   try{
    let value=await transport.json(url,options);
    if(['quote_start','quote'].includes(body.action))initialPublic=copy(value.data);
    if(enabled&&value.data?.repricing&&['quote_start','quote'].includes(body.action))quoteDeadline=value.data.expires_at||Math.floor(clock/1000)+600;
    if(enabled&&(value.data?.state==='quote_verified'||value.data?.status==='quote_verified')){
     value.data.verified_at=Math.floor(clock/1000);value.data.expires_at=quoteDeadline;
    }
    if(onReply)value=await onReply(value,body);
    if(hold&&hold.when(body))await hold.gate.promise;
    return {ok:value.httpStatus?value.httpStatus<400:value.ok!==false,status:value.httpStatus||200,json:async()=>value};
   }finally{active--;}
  }});
 const marker='  root.AnyTourPrototypeData=Object.freeze(';
 vm.runInContext(source.replace(marker,'  generation=1;\n'+marker),context);
 const offer=provider==='anex'?{provider,cached:false,raw:{selectionEnabled:false,anexKind:'concrete',anexSessionCurrent:true,
  anexLocalHotelId:101,anexGeneration:1,offerRef:'anex_online:'+'1'.repeat(64),searchRef:'b'.repeat(32)}}
  :{provider,cached:false,raw:{selectionEnabled:false,quoteRequired:true,andromedaLocalHotelId:101,offerRef:'offer_'+'d'.repeat(64),
   offer_context:{provider:'andromeda',generation:1,page:1,offer_ref:'offer_'+'d'.repeat(64),search_ref:'c'.repeat(64)},
   andromedaSearchParams:{dateFrom:'2026-10-13',dateTo:'2026-10-19',nightsFrom:7,nightsTo:7,adults:2,childs:[],countryId:'4'}}};
 const api=window.AnyTourPrototypeData,pair=n=>provider==='anex'?'anex_quote:'+String(n).repeat(64)
  :{provider:'andromeda',outbound_ref:'flight_'+String(n*2-1).repeat(32),return_ref:'flight_'+'2'.repeat(32)};
 const calculate=n=>provider==='anex'?api.verifyAnexPackage(offer,pair(n)):api.verifyAndromeda(offer,pair(n));
 const latest=()=>provider==='anex'?api.verifyAnexPackage(offer):api.verifyAndromeda(offer);
 const isCalc=body=>['quote_calculate','quote_select_flights'].includes(body.action);
 const pairNumber=body=>body.choice_ref?Number(body.choice_ref.slice(-1)):(Number(body.flight_selection?.outbound_ref.slice(-1))+1)/2;
 return {api,offer,calls,warnings,events,transport,context,pair,calculate,latest,pairNumber,isCalc,
  async start(){if(provider==='anex'){await api.verifyAnexConcrete(offer);return api.verifyAnexPackage(offer);}return api.verifyAndromeda(offer);},
  setReply:fn=>onReply=fn,advance:ms=>clock+=ms,
  hold(n){const pending=gate();hold={when:body=>isCalc(body)&&pairNumber(body)===n,gate:pending};return pending;},
  timeout(){const fn=[...timers.values()].at(-1);assert(fn);fn();},
  get initialPublic(){return copy(initialPublic);},get maxActive(){return maxActive;},get calcCalls(){return calls.filter(call=>isCalc(call.body));},
  amount:quote=>quote.finalPrice.amount,
  tuple:quote=>copy({price:quote.finalPrice,choice:quote.choice,flights:quote.flights,verifiedAt:quote.verifiedAt,expiresAt:quote.expiresAt})};
}
async function safeAndromedaFailureDiagnostics(source){
 const allowed=['ANDROMEDA_INVALID_RESPONSE','ANDROMEDA_INVALID_PACKAGE_RESPONSE','ANDROMEDA_INVALID_CLAIM_RESPONSE',
  'ANDROMEDA_RESPONSE_TOO_LARGE','ANDROMEDA_SECRET_ECHO'];
 const phases=['request','database','catalog','criteria','quote_resolve','quote_reserve','quote_bootstrap','flight_state',
  'quote_validate','quote_checkpoint','flight_continuation'];
 const guards=['ANDROMEDA_SELECTION_CONTEXT_MISMATCH','ANDROMEDA_SELECTION_MAPPING_UNAVAILABLE','ANDROMEDA_QUOTE_ATTEMPT_INVALID',
  'ANDROMEDA_QUOTE_RESULT_INVALID','ANDROMEDA_QUOTE_MONEY_INVALID','ANDROMEDA_QUOTE_PRIVATE_STATE','ANDROMEDA_QUOTE_PROVENANCE_INVALID'];
 const raw='RAW_SUPPLIER_TEXT_SENTINEL: login=fictional-secret';let cases=0;
 const check=async(reason,category='supplier_response',expectedReason=null,extra={},expectedPhase=null)=>{
  const h=quoteHarness(source,'andromeda');
  h.setReply(()=>({ok:false,httpStatus:502,failure_category:category,failure_reason:reason,message:raw,raw_response:raw,...extra}));
  const failure=await h.start().catch(error=>error);
  assert.equal(failure.code,'quote_unconfirmed');assert.equal(failure.retryable,false);
  assert.equal(failure.httpStatus,502);assert.equal(failure.failureCategory,category);
  assert.equal(failure.message,'Подтверждение тура не получено. Цена и наличие пока неизвестны.','diagnostic facts do not change public copy');
  assert.equal(failure.failureReason,expectedReason||undefined);
  assert.equal(failure.failurePhase,expectedPhase||undefined);
  assert.equal(failure.responseFailure,undefined,'outer HTTP502 keeps its existing diagnostic fields');
  assert.equal(h.warnings.length,1);assert(h.warnings[0].startsWith('[AnyTour quote] '));
  const detail=JSON.parse(h.warnings[0].slice('[AnyTour quote] '.length));
  const existingDetail={provider:'andromeda',action:'quote',code:'quote_unconfirmed',httpStatus:502,failureCategory:category,
   ...(expectedReason?{failureReason:expectedReason}:{}),
   ...(category==='supplier_rejected'?{failureStage:'broninit',supplierCode:'FIXED_17'}:{})};
  assert.deepEqual(detail,{...existingDetail,...(expectedPhase?{failurePhase:expectedPhase}:{})});
  assert.deepEqual(h.events,[{type:'anytour:quote-failure',detail:existingDetail}],'phase stays console-only; public event facts are unchanged');
  assert(!h.warnings[0].includes(raw));assert(!JSON.stringify(failure).includes(raw));
  assert.equal(h.calls.length,1,'one initial intercepted quote request');
  await assert.rejects(h.start(),error=>error===failure,'reopening retains the same terminal failure');
  await assert.rejects(h.latest(),error=>error===failure,'passive replay retains the same terminal failure');
  assert.equal(h.calls.length,1,'the diagnostic never reopens supplier transport');cases++;
 };
 for(const reason of allowed)await check(reason,'supplier_response',reason);
 for(const reason of [undefined,null,0,{},[allowed[0]],raw,'INVALID_RESPONSE',allowed[0]+' '+raw,
  ' '+allowed[0],allowed[0].toLowerCase(),'ANDROMEDA_QUOTE_CONTEXT_MISMATCH'])await check(reason);
 for(const category of ['quote_state','supplier_http','supplier_transport','supplier_auth','internal'])await check(allowed[0],category);
 await check('ANDROMEDA_QUOTE_CONTEXT_MISMATCH','quote_state','ANDROMEDA_QUOTE_CONTEXT_MISMATCH');
 await check(undefined,'supplier_rejected',null,{failure_stage:'broninit',supplier_code:'FIXED_17'});
 for(const phase of phases)await check(undefined,'internal',null,{failure_phase:phase},phase);
 await check(guards[0],'quote_state',guards[0],{failure_phase:'quote_resolve'},'quote_resolve');
 for(const phase of [null,0,{},['quote_resolve'],raw,'quote_resolve_suffix','prefix_quote_resolve','quote_resolve\n'+raw,' quote_resolve','QUOTE_RESOLVE'])
  await check(undefined,'internal',null,{failure_phase:phase});
 for(const guard of guards){await check(guard,'quote_state',guard);await check(guard,'supplier_response');}
 for(const guard of [null,{},[guards[0]],'SELECTION_CONTEXT_MISMATCH','PREFIX_'+guards[0],guards[0]+' '+raw,guards[0]+'\n'+raw])
  await check(guard,'quote_state');
 {
  const h=quoteHarness(source,'andromeda'),pending=gate();
  h.setReply(async()=>{await pending.promise;return {ok:false,httpStatus:502,failure_category:'internal',failure_phase:'quote_bootstrap'};});
  const old=h.start().catch(error=>error);await flush();h.api.stop();pending.resolve();
  const stale=await old;assert.equal(stale.failureCategory,'stale');assert.equal(stale.failurePhase,undefined);
  assert.equal(h.warnings.length,0);assert.equal(h.events.length,0,'late old-generation phase is not published');
  await assert.rejects(h.start());assert.equal(h.calls.length,1,'stale failure cannot reopen the quote');cases++;
 }
 {
  const h=quoteHarness(source,'andromeda');await h.start();await h.calculate(1);
  h.setReply((value,body)=>h.isCalc(body)?{ok:true,data:{...h.initialPublic,state:'quote_unknown',selection_enabled:false,
   failure_category:'internal',failure_phase:'flight_continuation',failure_reason:raw}}:value);
  const pending=h.hold(2),b=h.calculate(2).catch(error=>error);await flush();
  const queued=h.calculate(3).catch(error=>error),cached=h.calculate(1).catch(error=>error);pending.resolve();
  const failure=await b;assert.equal(failure.failureCategory,'invalid_response');assert.equal(failure.httpStatus,200);
  assert.equal(failure.message,'Andromeda вернул некорректное подтверждение. Цена и наличие пока неизвестны.');
  assert.equal(failure.failurePhase,'flight_continuation');assert.equal(failure.failureReason,undefined);
  assert.equal(failure.retryable,false);assert.equal(await queued,failure);assert.equal(await cached,failure);
  assert.equal(h.calcCalls.length,2,'queued C never reaches transport after sealed HTTP200 UNKNOWN');
  const before=h.calls.length;await assert.rejects(h.calculate(1),error=>error===failure);await assert.rejects(h.latest(),error=>error===failure);
  assert.equal(h.calls.length,before,'neither cached quote nor application authority can be restored by diagnostics');
  assert(!JSON.stringify(failure).includes(raw));assert(!h.warnings.at(-1).includes(raw));
  assert.equal(JSON.parse(h.warnings.at(-1).slice('[AnyTour quote] '.length)).failurePhase,'flight_continuation');
  assert.equal(h.events.at(-1).detail.failurePhase,undefined);cases++;
 }
 let embeddedCases=0;
 const embedded=async(fields,expected)=>{
  const h=quoteHarness(source,'andromeda');await h.start();const a=await h.calculate(1),tuple=h.tuple(a);
  h.setReply((value,body)=>h.isCalc(body)?{ok:true,data:{...h.initialPublic,state:'quote_unknown',selection_enabled:false,
   repricing:{enabled:false,max_pairs:3,used_pairs:2,remaining_pairs:0},failure_phase:'flight_continuation',
   message:raw,raw_response:raw,...fields}}:value);
  const pending=h.hold(2),b=h.calculate(2).catch(error=>error);await flush();
  const queued=h.calculate(3).catch(error=>error),cached=h.calculate(1).catch(error=>error);pending.resolve();
  const failure=await b;
  assert.equal(failure.failureCategory,'invalid_response');assert.equal(failure.code,'quote_unconfirmed');
  assert.equal(failure.message,'Andromeda вернул некорректное подтверждение. Цена и наличие пока неизвестны.');
  assert.equal(failure.retryable,false);assert.equal(failure.httpStatus,200);assert.equal(failure.failurePhase,'flight_continuation');
  assert.equal(failure.failureReason,undefined);assert.equal(failure.failureStage,undefined);assert.equal(failure.supplierCode,undefined);
  assert.deepEqual(failure.responseFailure&&copy(failure.responseFailure),expected);
  if(expected)assert(Object.isFrozen(failure.responseFailure));
  const existingDetail={provider:'andromeda',action:'quote_select_flights',code:'quote_unconfirmed',httpStatus:200,failureCategory:'invalid_response'};
  assert.deepEqual(JSON.parse(h.warnings.at(-1).slice('[AnyTour quote] '.length)),{...existingDetail,failurePhase:'flight_continuation',
   ...(expected?{responseFailure:expected}:{})});
  assert.deepEqual(h.events.at(-1),{type:'anytour:quote-failure',detail:existingDetail},'embedded diagnostics never change the public event');
  assert(!JSON.stringify(failure).includes(raw));assert(!h.warnings.at(-1).includes(raw));
  assert.equal(await queued,failure);assert.equal(await cached,failure,'old cached A has no authority after UNKNOWN');
  assert.deepEqual(h.tuple(a),tuple,'historical A money and exact tuple stay immutable');
  assert.deepEqual(h.calcCalls.map(call=>h.pairNumber(call.body)),[1,2],'queued C never reaches transport');
  const before=h.calls.length;
  await assert.rejects(h.calculate(1),error=>error===failure);await assert.rejects(h.latest(),error=>error===failure);
  await assert.rejects(h.start(),error=>error===failure);assert.equal(h.calls.length,before,'diagnostics cannot restore a quote/application or replay transport');
  embeddedCases++;
 };
 for(const category of ['supplier_transport','supplier_http','supplier_rejected','supplier_response','supplier_auth','quote_state','internal'])
  await embedded({failure_category:category},{failureCategory:category});
 for(const reason of [...guards,'ANDROMEDA_FLIGHT_REFS_INVALID','ANDROMEDA_SELECTED_FLIGHTS_INVALID'])
  await embedded({failure_category:'quote_state',failure_reason:reason},{failureCategory:'quote_state',failureReason:reason});
 for(const reason of allowed)
  await embedded({failure_category:'supplier_response',failure_reason:reason},{failureCategory:'supplier_response',failureReason:reason});
 for(const stage of ['broninit','get_flights','changeservice','calc'])
  await embedded({failure_category:'supplier_rejected',failure_stage:stage,supplier_code:'FIXED_17'},
   {failureCategory:'supplier_rejected',failureStage:stage,supplierCode:'FIXED_17'});
 for(const category of [undefined,null,0,{},['quote_state'],'quote_state_suffix',raw])
  await embedded({failure_category:category,failure_reason:guards[0],failure_stage:'calc',supplier_code:'FIXED_17'},undefined);
 for(const reason of [null,0,{},[guards[0]],raw,guards[0]+' '+raw,'PREFIX_'+guards[0],allowed[0]])
  await embedded({failure_category:'quote_state',failure_reason:reason},{failureCategory:'quote_state'});
 for(const reason of [null,{},raw,allowed[0]+'\n'+raw,guards[0]])
  await embedded({failure_category:'supplier_response',failure_reason:reason},{failureCategory:'supplier_response'});
 for(const stage of [null,0,{},['calc'],'calc_suffix',raw])
  await embedded({failure_category:'supplier_rejected',failure_stage:stage,supplier_code:'FIXED_17'},{failureCategory:'supplier_rejected'});
 for(const code of [null,17,{},['FIXED_17'],'',raw,'x'.repeat(65),'../private/file'])
  await embedded({failure_category:'supplier_rejected',failure_stage:'calc',supplier_code:code},
   {failureCategory:'supplier_rejected',failureStage:'calc'});
 await embedded({failure_category:'supplier_rejected',failure_stage:'calc',supplier_code:'CODE_.:-123'},
  {failureCategory:'supplier_rejected',failureStage:'calc',supplierCode:'CODE_.:-123'});
 await embedded({failure_category:'internal',failure_reason:guards[0],failure_stage:'calc',supplier_code:'FIXED_17'},{failureCategory:'internal'});
 console.log(JSON.stringify({safeSupplierResponseDiagnosticCases:cases,embeddedResponseFailureCases:embeddedCases,
  actualOwner:true,supplierHTTP:0,terminalReplayHTTP:0,publicCopyChanged:false}));
}
async function boundedFlightRepricing(source){
 let cases=0;
 for(const provider of ['anex','andromeda']){
  const check=async(name,run)=>{await run();cases++;console.log('PASS '+provider+' bounded quote: '+name);};
  await check('A/B/A immutable exact tuple, current counts, cap and expiry',async()=>{
   const h=quoteHarness(source,provider),inventory=await h.start();
   assert.equal(inventory.repricing.remaining_pairs,3);
   const a=await h.calculate(1),tuple=h.tuple(a);assert.equal(h.amount(a),'101000');
   h.advance(12000);const b=await h.calculate(2);assert.equal(h.amount(b),'102000');
   assert.notEqual(a.verifiedAt,b.verifiedAt,'different source tuples detect verification timestamp replacement');assert.equal(a.expiresAt,b.expiresAt,'one context deadline never renews');
   const cached=await h.calculate(1);assert.deepEqual(h.tuple(cached),tuple);assert.equal(cached.repricing.used_pairs,2);assert.equal(h.calcCalls.length,2);
   assert(Object.isFrozen(cached));assert(Object.isFrozen(cached.finalPrice));
   if(provider==='andromeda'){assert.equal(cached.flightChoices.length,5);assert.equal(cached.flights.length,2);assert.equal(cached.flights[0].flightRef,h.pair(1).outbound_ref);}
   else assert.equal(cached.choices.length,4);
   await h.calculate(3);const before=h.calls.length;
   await assert.rejects(h.calculate(4),error=>error.code==='quote_pair_budget_exhausted'&&error.retryable===true);
   assert.equal(h.calls.length,before,'fourth pair consumes no transport');
   assert.equal((await h.calculate(1)).repricing.used_pairs,3);
   assert.equal((await h.latest()).repricing.used_pairs,3,'passive latest reports current healthy counts');
   h.advance((a.expiresAt*1000-Date.now())+1000);
   await assert.rejects(h.calculate(1));assert.equal(h.calls.length,before,'expired cached pair cannot reprice');
  });
  await check('cached A supersedes unsent C, waits active B and retains A timestamps',async()=>{
   const h=quoteHarness(source,provider);await h.start();const a=await h.calculate(1),tuple=h.tuple(a);h.advance(12000);
   const pending=h.hold(2),b=h.calculate(2);await flush();let finished=false;
   const c=h.calculate(3).then(()=>assert.fail('superseded C cannot dispatch'),error=>error);
   const back=h.calculate(1).then(value=>{finished=true;return value;});await flush();
   assert.equal(finished,false);assert.equal(h.calcCalls.length,2);assert.equal(h.calcCalls[1].signal.aborted,false);
   pending.resolve();await b;const cached=await back;assert.equal((await c).code,'quote_superseded');
   assert.deepEqual(h.tuple(cached),tuple);assert.equal(cached.repricing.used_pairs,2);assert.equal(h.maxActive,1);assert.equal(h.calcCalls.length,2);
  });
  await check('A/B/C latest unsent choice only; duplicate queued choice joins',async()=>{
   const h=quoteHarness(source,provider);await h.start();const pending=h.hold(1),a=h.calculate(1);await flush();
   const b=h.calculate(2).then(()=>assert.fail('superseded B cannot verify'),error=>error);
   const duplicateB=h.calculate(2).then(()=>assert.fail('superseded duplicate B cannot verify'),error=>error);
   const c=h.calculate(3);await flush();assert.equal(h.calcCalls.length,1);assert.equal(h.calcCalls[0].signal.aborted,false);
   pending.resolve();await a;assert.equal((await b).code,'quote_superseded');assert.equal((await duplicateB).code,'quote_superseded');
   assert.equal(h.amount(await c),'103000');assert.deepEqual(h.calcCalls.map(call=>h.pairNumber(call.body)),[1,3]);assert.equal(h.maxActive,1);
  });
  await check('A/B/A cancels unsent B; duplicate active and queued promises join',async()=>{
   const h=quoteHarness(source,provider);await h.start();const pending=h.hold(1),a=h.calculate(1);await flush();
   const b=h.calculate(2).then(()=>assert.fail('B cannot dispatch'),error=>error),same=h.calculate(1),sameAgain=h.calculate(1);
   pending.resolve();const results=await Promise.all([a,same,sameAgain]);assert.equal((await b).code,'quote_superseded');
   assert(results.every(value=>h.amount(value)==='101000'));assert.equal(h.calcCalls.length,1);
   const pendingC=h.hold(3),c=h.calculate(3);await flush();const firstB=h.calculate(2),secondB=h.calculate(2);pendingC.resolve();await c;
   assert.equal(h.amount(await firstB),'102000');assert.equal(h.amount(await secondB),'102000');
   assert.deepEqual(h.calcCalls.map(call=>h.pairNumber(call.body)),[1,3,2]);assert.equal(h.maxActive,1);
  });
  await check('UNKNOWN seals active, queued and cached authority without replay',async()=>{
   const h=quoteHarness(source,provider);await h.start();await h.calculate(1);h.transport.state.repricingFailure='unknown';
   const pending=h.hold(2),b=h.calculate(2).catch(error=>error);await flush();const back=h.calculate(1).catch(error=>error);
   pending.resolve();assert.notEqual((await b).retryable,true);assert.notEqual((await back).retryable,true);
   const before=h.calls.length;await assert.rejects(h.calculate(1));await assert.rejects(h.latest());assert.equal(h.calls.length,before);
  });
  await check('new generation rejects late A and never dispatches queued B',async()=>{
   const h=quoteHarness(source,provider);await h.start();const pending=h.hold(1),a=h.calculate(1).catch(error=>error);await flush();
   const b=h.calculate(2).catch(error=>error);h.api.stop();pending.resolve();await a;await b;
   assert.equal(h.calcCalls.length,1);await assert.rejects(h.calculate(1));assert.equal(h.calcCalls.length,1);
  });
  await check('healthy server budget refusal drains cached A without replay',async()=>{
   const h=quoteHarness(source,provider);await h.start();const a=await h.calculate(1),tuple=h.tuple(a);
   h.setReply((value,body)=>{
    if(!h.isCalc(body))return value;
    value.data=h.initialPublic;value.data.repricing={enabled:true,max_pairs:3,used_pairs:3,remaining_pairs:0};
    if(provider==='anex')Object.assign(value.data,{status:'quote_selection_locked',final_price_verified:false,selection_state:'disabled',price:null});
    else value.data.failure_reason='ANDROMEDA_FLIGHT_REPRICE_BUDGET';
    return value;
   });
   const pending=h.hold(2),b=h.calculate(2).catch(error=>error);await flush();const back=h.calculate(1);pending.resolve();
   assert.equal((await b).code,'quote_pair_budget_exhausted');const cached=await back;assert.deepEqual(h.tuple(cached),tuple);
   assert.equal(cached.repricing.used_pairs,3);assert.equal((await h.latest()).repricing.used_pairs,3);
   const before=h.calls.length;await assert.rejects(h.calculate(2),error=>error.code==='quote_pair_budget_exhausted');assert.equal(h.calls.length,before);
  });
  await check('another ANEX verification/expansion cannot abort active quote',async()=>{
   const h=quoteHarness(source,provider);await h.start();const pending=h.hold(1),a=h.calculate(1);await flush();
   const other={provider:'anex',cached:false,raw:{selectionEnabled:false,anexKind:'concrete',anexSessionCurrent:true,
    anexLocalHotelId:101,anexGeneration:1,offerRef:'anex_online:'+'9'.repeat(64),searchRef:'b'.repeat(32)}};
   const before=h.calls.length;
   await assert.rejects(h.api.verifyAnexConcrete(other),error=>error.code==='quote_operation_pending'&&error.retryable===true);
   await assert.rejects(h.api.expandAnexGroup({...other,raw:{...other.raw,anexKind:'group_minimum'}}),error=>error.code==='quote_operation_pending');
   assert.equal(h.calls.length,before);assert.equal(h.calcCalls[0].signal.aborted,false);pending.resolve();await a;
  });
  await check('legacy one-pair and sealed cache retain authority',async()=>{
   const h=quoteHarness(source,provider,{enabled:false}),inventory=await h.start();assert.equal(inventory.repricing,undefined);
   const a=await h.calculate(1),before=h.calls.length;assert.equal((await h.calculate(1)).finalPrice.amount,a.finalPrice.amount);
   await assert.rejects(h.calculate(2));assert.equal(h.calls.length,before);
  });
  for(const malformed of ['missing-cap','disabled-cap','bad-count','stale-coherent-count','invalid-time','invalid-deadline','unsafe-expiry','http-budget','foreign-budget',...(provider==='andromeda'?['missing-refs']:[])]){
   await check(malformed+' response cannot keep cached authority',async()=>{
    const h=quoteHarness(source,provider);await h.start();await h.calculate(1);
    h.setReply((value,body)=>{
     if(!h.isCalc(body))return value;
     const v=value.data;
     if(malformed==='missing-cap')delete v.repricing;
     if(malformed==='disabled-cap')v.repricing={enabled:false,max_pairs:3,used_pairs:2,remaining_pairs:0};
     if(malformed==='bad-count')v.repricing={enabled:true,max_pairs:3,used_pairs:2,remaining_pairs:2};
     if(malformed==='stale-coherent-count')v.repricing={enabled:true,max_pairs:3,used_pairs:1,remaining_pairs:2};
     if(malformed==='invalid-time')v.verified_at=v.expires_at;
     if(malformed==='invalid-deadline')v.expires_at++;
     if(malformed==='unsafe-expiry')v.expires_at=1e20;
     if(malformed==='missing-refs')for(const flight of v.flights)delete flight.flight_ref;
     if(malformed==='http-budget'||malformed==='foreign-budget'){
      const initial=h.transport.state.repricingEnabled;assert(initial);
      if(provider==='anex')Object.assign(v,{status:'quote_selection_locked',final_price_verified:false,selection_state:'disabled',price:null});
      else Object.assign(v,{state:'flight_selection_required',quote_state:'unverified',final_price:null,final_price_verified:false,
       flight_selection_required:true,failure_reason:'ANDROMEDA_FLIGHT_REPRICE_BUDGET'});
      v.repricing={enabled:true,max_pairs:3,used_pairs:3,remaining_pairs:0};
      if(malformed==='http-budget')value.httpStatus=503;
      else if(provider==='anex')v.offer_ref='anex_online:'+'9'.repeat(64);else v.local_id=102;
     }
     return value;
    });
    await assert.rejects(h.calculate(2));const before=h.calls.length;await assert.rejects(h.calculate(1));
    if(malformed==='stale-coherent-count'){await assert.rejects(h.calculate(3));await assert.rejects(h.calculate(4));assert.equal(h.calcCalls.length,2,'contradictory coherent count seals before C/D transport');}
    assert.equal(h.calls.length,before);
   });
  }
 }
 const h=quoteHarness(source,'andromeda',{enabled:false});h.transport.state.samoFlightChoice=false;
 h.setReply(value=>{if(value.data?.state==='quote_verified')value.data.flights=[];return value;});
 const direct=await h.start();assert.equal(direct.finalPrice.amount,'125500');assert.deepEqual(copy(direct.flights),[]);assert.equal(direct.repricing,undefined);
 const before=h.calls.length;await h.latest();assert.equal(h.calls.length,before);cases++;
 console.log(JSON.stringify({boundedQuoteCases:cases,actualOwner:true,supplierHTTP:0,realLeads:0}));
}
(async()=>{
 await selectedHotelRestoration();
 const options=process.argv.slice(2),sourcePath=options[0]&&options[0]!=='--compare'?path.resolve(options.shift()):target;
 const source=fs.readFileSync(sourcePath,'utf8'),work=dataWorkOracles(source),records=await characterize(source);
 verifiedFlightPairBinding(source);
 await safeAndromedaFailureDiagnostics(source);
 await boundedFlightRepricing(source);
 const digest=crypto.createHash('sha256').update(JSON.stringify(records)).digest('hex');
 // Pinned before the refactor on full data owner blob 931fb024951b6d69ce84e635e61ddd8112501623.
 assert.equal(digest,'f2a89681027398304bf0f9c4c55434e8447c01b591f0f1b07130eac0174f461a','observable orchestration trace changed from the characterized baseline');
 if(options[0]==='--compare'){
  assert(options[1],'--compare requires an original source file');const before=await characterize(fs.readFileSync(path.resolve(options[1]),'utf8'));
  assert.deepEqual(records,before,'original and refactored full data owner observable traces must match');
 }
 console.log(JSON.stringify({cases:records.length,digest,comparison:options[0]==='--compare'?'identical':'characterized',work,supplierHTTP:0,realLeads:0}));
})().catch(error=>{console.error(error);process.exitCode=1;});
