// Actual calendar/matching owners: price parity, input identity and scan budget.
// No bootstrap, provider transport or lead code executes.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const defaultFilters=()=>({hotelId:0,q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false});
const day=i=>new Date(Date.UTC(2026,9,28+i)).toISOString().slice(0,10);
function fixture(){return Array.from({length:12},(_,i)=>({id:i+1,country:i===11?'9':'4',name:'Hotel '+i,subRegion:i%2?'Кемер':'Анталья',region:'Анталья',resort:'Кемер',stars:i%4+2,rating:i%3?4.8:3.2,beach:i%2?100:null,family:!!(i%2),spa:!!(i%3),amenities:i%2?[{key:'pool'}]:[],offers:Array.from({length:18},(_,j)=>({key:i+':'+j,search:{origin:j===17?'Казань':'Москва',country:j===16?'9':'4'},adults:j===15?3:2,ages:j===14?[0]:j===13?['17',0]:j===12?[17,0]:[0,17],day:day(j%7),nights:j===11?10:7,meal:j%2?'AI':'BB',mealPlanId:j%2?7:3,total:j===0?0:j===1?Infinity:100000+i*100+j*10,operator:j%2?'A':'B',flight:j%2?'charter':'regular'}))}));}
function section(source,first,last){const a=source.indexOf(first),b=source.indexOf(last,a);assert(a>=0&&b>a,first);return source.slice(a,b);}
function make(source,hotels){
 const state={search:{origin:'Москва',country:'4',from:day(0),to:day(6),minNights:7,maxNights:7,adults:2,ages:[0,17]},filters:defaultFilters(),selectedDate:day(2),onlyFavorites:true,favorites:[1]};
 const context={hotels,state,data:{live:false,observationScopeSupported:()=>context.supported},supported:true,mealNames:{AI:7,BB:3},matchesHotelQuery:(h,q)=>!q||h.name.includes(q),ratingValue:h=>h.rating};
 vm.createContext(context);
 vm.runInContext(source.match(/^const matchesMeal=[^\n]+/m)[0]+'\n'+section(source,'const hotelPlaces=','function recommendedHotelScore('),context);
 if(source.includes('function calendarMinimums('))vm.runInContext(section(source,'function calendarMinimums(','function filterCount('),context);
 else vm.runInContext(section(source,'function minimumForDay(','function filterCount('),context);
 vm.runInContext('const originalHotelOffers=hotelOffers;hotelOffers=function(...args){globalThis.scans++;return originalHotelOffers(...args);};',context);
 context.scans=0;
 context.run=(days,options,observations)=>{context.scans=0;return Array.from(context.calendarMinimums?context.calendarMinimums(days,options,observations):days.map(d=>context.calendarMinimum(d,options,observations)));};
 return context;
}
// Original per-day algorithm remains the independent reference for focused CI.
function reference(ctx,days,options,observations){
 const search=options.search||ctx.state.search,filters=options.filters||ctx.state.filters;
 return days.map(date=>{let min=Infinity;for(const h of options.calendarHotels||ctx.hotels){const offers=ctx.hotelOffers(h,{...options,day:date,ignoreDate:true,onlyFavorites:false});if(offers.length)min=Math.min(min,offers[0].total);}const actual=Number.isFinite(min)?min:null,saved=ctx.supported?observations.find(p=>p.date===date)?.price:null,values=[actual,saved].filter(x=>Number.isFinite(x)&&x>0);return values.length?Math.min(...values):null;});
}
const variants=[{},...Object.entries({hotelId:2,q:'Hotel 1',stars:[3],meals:['AI'],resorts:['Кемер'],operators:['A'],flight:['charter'],amenities:['pool'],min:100050,max:100900,rating:true,beach:true,family:true,spa:true}).map(([k,v])=>({[k]:v}))];
function records(source,checkReference=true){
 const hotels=fixture(),before=JSON.stringify(hotels),ctx=make(source,hotels),out=[];
 for(const filters of variants)for(const live of [false,true])for(const supported of [false,true])for(const width of [1,3,7]){
  ctx.state.filters={...defaultFilters(),...filters};ctx.data.live=live;ctx.supported=supported;
  const days=Array.from({length:width},(_,i)=>day(i));
  const observations=[{date:day(0),price:0},{date:day(0),price:1},{date:day(1),price:90000.5},{date:day(2),price:NaN},{date:day(2),price:1},{date:day(3),price:Infinity}];
  const options=out.length%2?{calendarHotels:[...hotels,hotels[0]],onlyFavorites:true,selectedDate:day(5),day:day(5)}:{};
  const actual=ctx.run(days,options,observations),scans=ctx.scans;
  if(checkReference)assert.deepEqual(actual,reference(ctx,days,options,observations),'per-day price contract');
  if(source.includes('function calendarMinimums('))assert.equal(scans,(options.calendarHotels||hotels).length,'one scan per hotel regardless of day count');
  out.push(actual);
 }
 // Empty/unsorted/duplicated dates and explicit alternative search/filter scope.
 for(const days of [[],[day(6),day(0),day(6)]]){
  const options={search:{...ctx.state.search,from:day(2),to:day(3),ages:[17,0],adults:3},filters:defaultFilters(),calendarHotels:hotels.slice(0,4)};
  const actual=ctx.run(days,options,[]);if(checkReference)assert.deepEqual(actual,reference(ctx,days,options,[]));out.push(actual);
 }
 assert.equal(JSON.stringify(hotels),before,'loaded objects and offer order unchanged');
 assert.strictEqual(ctx.hotels[0],hotels[0]);assert.strictEqual(ctx.hotels[0].offers[0],hotels[0].offers[0]);
 return out;
}
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex'),compare=process.argv.indexOf('--compare');
if(compare>=0)assert.deepEqual(actual,records(fs.readFileSync(process.argv[compare+1],'utf8')),'actual original/candidate algorithms');
assert.equal(digest,'53766c9a1cd0fd38f3ee6f9a203889169462400fb9738c3f2ee697e3b2a6fad1','pinned original price sequences');
const changed=mutated=>JSON.stringify(records(mutated,false))!==JSON.stringify(actual);
assert(changed(source.replace('if(!saved.has(point.date))','if(true)')),'first duplicate saved observation matters');
assert(changed(source.replace('ignoreDate:true,onlyFavorites:false','ignoreDate:true,onlyFavorites:true')),'calendar ignores shortlist');
assert(changed(source.replace('if(data.observationScopeSupported(s,f))','if(true)')),'unsupported saved scope remains excluded');
if(process.argv.includes('--benchmark')){
 assert(compare>=0,'benchmark needs actual original');const perf=require('node:perf_hooks').performance;
 const hotels=Array.from({length:1047},(_,i)=>{const h=fixture()[1];h.id=i+1;h.offers=h.offers.filter(o=>Number.isFinite(o.total)&&o.total>0);return h;});
 const days=Array.from({length:7},(_,i)=>day(i)),original=make(fs.readFileSync(process.argv[compare+1],'utf8'),hotels),candidate=make(source,hotels);
 const measure=ctx=>{ctx.run(days,{},[]);const times=[];let scans;for(let i=0;i<9;i++){const start=perf.now();ctx.run(days,{},[]);times.push(perf.now()-start);scans=ctx.scans;}return {medianMs:times.sort((a,b)=>a-b)[4],scans};};
 assert.deepEqual(candidate.run(days,{},[]),original.run(days,{},[]));
 const before=measure(original),after=measure(candidate);console.log(JSON.stringify({workload:'1047 synthetic hotels, seven dates, nine warm runs',before,after,speedup:before.medianMs/after.medianMs,wholePageTiming:false}));
}
console.log(`PASS calendar inventory: ${actual.length} original price sequences; digest ${digest}; raw inputs retained, one hotel scan, duplicate/favorite/saved-scope mutations detected; supplier/lead HTTP 0`);

// Filter counts need existence only; normal consumers retain every sorted offer.
{
 const hs=fixture(),ctx=make(source,hs);
 for(const filters of variants)for(const selectedDate of [null,day(2)])for(const onlyFavorites of [false,true]){
  ctx.state.filters={...defaultFilters(),...filters};
  for(const h of hs){const options={selectedDate,onlyFavorites},all=ctx.hotelOffers(h,options),first=ctx.hotelOffers(h,{...options,firstOnly:true});assert.equal(first.length,all.length?1:0);if(first.length)assert(all.includes(first[0]),'raw identity retained');}
 }
 const h=fixture()[1];h.offers=Array.from({length:1000},(_,i)=>({...h.offers[2],key:String(i),total:100000+i}));
 const budget=make(source,[h]);
 vm.runInContext('globalThis.ageCalls=0;const stringify=JSON.stringify;JSON.stringify=(...args)=>{ageCalls++;return stringify(...args)};',budget);
 const options={selectedDate:day(2),onlyFavorites:false};
 assert.equal(budget.hotelOffers(h,{...options,firstOnly:true}).length,1);
 assert.equal(budget.ageCalls,2,'existence stops on first match');
 budget.ageCalls=0;const full=budget.hotelOffers(h,options);
 assert.equal(full.length,1000);assert.equal(budget.ageCalls,1001,'search ages serialized once per call');
 assert.strictEqual(full[0],h.offers[0]);assert.strictEqual(full[999],h.offers[999]);
 console.log('PASS filter existence: 720 hotel/filter/date/shortlist cases; first-only age calls 2000->2, full 2000->1001; raw references and sorted output retained');
}

// Budget recovery needs the sorted comparator minimum, without materializing or
// sorting every matching offer. Keep native filter length/hole/inheritance facts.
{
 assert.equal((source.match(/minimumOnly:true/g)||[]).length,2,'both budget-recovery consumers request a comparator minimum');
 let seed=7,reads=0;const random=()=>((seed=seed*48271%2147483647)/2147483647),hs=[];
 for(let hotel=0;hotel<10;hotel++){
  const h=fixture()[1],base=h.offers[2];h.id=hotel+1;h.offers=Array.from({length:100},(_,index)=>{const row={...base,key:hotel+':'+index},total=Math.floor(random()*100000)+index,date=day(index%7);Object.defineProperty(row,'total',{enumerable:true,get(){reads++;return total}});Object.defineProperty(row,'day',{enumerable:true,get(){reads++;return date}});return row;});hs.push(h);
 }
 const ctx=make(source,hs),options={selectedDate:null,onlyFavorites:false},order=hs.map(h=>h.offers.map(o=>o.key).join(','));
 reads=0;const sorted=hs.map(h=>ctx.hotelOffers(h,options)[0]),sortedReads=reads;reads=0;const minimum=hs.map(h=>ctx.hotelOffers(h,{...options,minimumOnly:true})[0]),minimumReads=reads;
 const predicateReads=10*100*3,sortedKeyReads=sortedReads-predicateReads,minimumKeyReads=minimumReads-predicateReads;assert.deepEqual(minimum.map(o=>o.key),sorted.map(o=>o.key),'minimum-only keeps total/day comparator results');assert(minimum.every((o,index)=>hs[index].offers.includes(o)),'minimum-only returns raw offer references');assert.deepEqual(hs.map(h=>h.offers.map(o=>o.key).join(',')),order,'minimum-only preserves raw offer order');assert(minimumKeyReads<sortedKeyReads/3,'minimum scan removes repeated sort-key reads');
 const base=fixture()[1].offers[2],tieFirst={...base,key:'tie-first',total:90000,day:day(2)},tieSecond={...base,key:'tie-second',total:90000,day:day(2)},earlier={...base,key:'earlier',total:90000,day:day(1)},high={...base,key:'high',total:120000,day:day(0)},h=fixture()[1];
 h.offers=[high,tieFirst,tieSecond];const ties=make(source,[h]);assert.strictEqual(ties.hotelOffers(h,{...options,minimumOnly:true})[0],tieFirst,'exact ties keep first raw object');h.offers.push(earlier);assert.strictEqual(ties.hotelOffers(h,{...options,minimumOnly:true})[0],earlier,'equal price keeps earlier day');earlier.total=130000;assert.strictEqual(ties.hotelOffers(h,{...options,minimumOnly:true})[0],tieFirst,'same-array edits are recalculated');
 const inherited={...base,key:'inherited',total:80000,day:day(2)},sparse=new Array(4),prototype=Object.create(Array.prototype);prototype[2]=inherited;sparse[0]=high;Object.setPrototypeOf(sparse,prototype);h.offers=sparse;assert.strictEqual(ties.hotelOffers(h,{...options,minimumOnly:true})[0],inherited,'sparse/inherited rows retain native filter membership');
 const pushed={...base,key:'pushed',total:1,day:day(2)},initialA={...base,key:'initial-a',day:day(2)},initialB={...base,key:'initial-b',total:100000,day:day(2)},growing=[initialA,initialB];let appended=false;Object.defineProperty(initialA,'total',{enumerable:true,get(){if(!appended){appended=true;growing.push(pushed)}return 110000}});h.offers=growing;assert.strictEqual(ties.hotelOffers(h,{...options,minimumOnly:true})[0],initialB,'scan captures initial array length');assert.strictEqual(ties.hotelOffers(h,{...options,minimumOnly:true})[0],pushed,'next call observes appended row');
 console.log(`PASS offer minimum inventory: 10x100 predicate reads ${predicateReads}->${predicateReads}; comparator keys ${sortedKeyReads}->${minimumKeyReads}; zero match arrays; tie/sparse/inherited/initial-length/edit guards; raw identity/order retained`);
}

// Exercise hotel membership with the actual query normalizer, not only prices.
function membership(source){
 const hs=fixture(),ctx=make(source,hs),before=JSON.stringify(hs),rows=[];
 vm.runInContext(section(source,'const normalizeSearch=','const destinationHotels=')+'\n'+source.match(/^const countMatchingHotels=[^\n]+/m)[0]+'\nglobalThis.matchingCount=countMatchingHotels;',ctx);
 const choices=[...variants,{q:'  HOTEL--1 '},{q:'неизвестный'},{resorts:['неизвестный','Кемер']},{resorts:[' Анталья ']},{resorts:['Анталья','Анталья']}];
 for(const a of choices)for(const b of choices)for(const selectedDate of [null,day(2)])for(const onlyFavorites of [false,true]){
  const filters={...defaultFilters(),...a,...b},model={filters,selectedDate,onlyFavorites};
  const ids=hs.filter(h=>ctx.hotelOffers(h,{...model,firstOnly:true}).length).map(h=>h.id);
  assert.equal(ctx.matchingCount(model),ids.length,'scalar count matches membership');rows.push(ids);
 }
 assert.equal(JSON.stringify(hs),before,'membership preserves raw inputs');return rows;
}
{
 const rows=membership(source),hash=crypto.createHash('sha256').update(JSON.stringify(rows)).digest('hex');
 assert.equal(hash,'bea2e770dcfadef635f51c7f05e9359a5edc74e42f964e008c3596e8d2f10e11','pinned original hotel membership');
 if(compare>=0)assert.deepEqual(rows,membership(fs.readFileSync(process.argv[compare+1],'utf8')),'original/candidate hotel membership');
 assert.notDeepEqual(rows,membership(source.replace('!f.resorts.length||f.resorts.some','true||f.resorts.some')),'resort predicate mutation detected');
 assert(source.includes("(h.offers||[]).find(matches)!==undefined"),'scalar counts use native existence membership');
 assert.throws(()=>membership(source.replace("(h.offers||[]).find(matches)!==undefined",'false')),/scalar count matches membership/,'offer-existence mutation detected');
 const hs=Array.from({length:1047},(_,i)=>({...fixture()[1],id:i+1})),ctx=make(source,hs);
 vm.runInContext('globalThis.placeCalls=0;const RealSet=Set;globalThis.Set=class extends RealSet{constructor(...args){super(...args);placeCalls++;}};',ctx);
 for(let i=0;i<30;i++)for(const h of hs)ctx.hotelOffers(h,{onlyFavorites:false,firstOnly:true});
 assert.equal(ctx.placeCalls,0,'empty resort filter allocates no place inventories');
 ctx.hotelOffers(hs[0],{onlyFavorites:false,filters:{...defaultFilters(),resorts:['missing','Кемер']},firstOnly:true});
 assert.equal(ctx.placeCalls,1,'multi-resort predicate computes places once per hotel');

 // Scalar counts need only membership. Compile the invariant predicate once
 // and do not allocate a singleton offer array for every matching hotel.
 const base=fixture()[1].offers[2],perfHotels=Array.from({length:100},(_,i)=>({...fixture()[1],id:i+1,offers:Array.from({length:100},(_,j)=>({...base,key:i+':'+j}))})),perf=make(source,perfHotels),model={filters:defaultFilters(),selectedDate:null,onlyFavorites:false};
 vm.runInContext(source.match(/^const countMatchingHotels=[^\n]+/m)[0]+'\nglobalThis.matchingCount=countMatchingHotels;globalThis.referenceCount=model=>hotels.reduce((count,h)=>count+Number(hotelOffers(h,{...model,firstOnly:true}).length>0),0);globalThis.offerCalls=0;const baseOffers=hotelOffers;hotelOffers=(...args)=>{offerCalls++;return baseOffers(...args)};globalThis.ageCalls=0;const stringify=JSON.stringify;JSON.stringify=(...args)=>{ageCalls++;return stringify(...args)};',perf);
 let beforeCount=0;for(let i=0;i<12;i++)beforeCount+=perf.referenceCount(model);const beforeAges=perf.ageCalls,beforeCalls=perf.offerCalls;
 perf.ageCalls=0;perf.offerCalls=0;let afterCount=0;for(let i=0;i<12;i++)afterCount+=perf.matchingCount(model);const afterAges=perf.ageCalls,afterCalls=perf.offerCalls;
 assert.equal(afterCount,beforeCount);assert.deepEqual({beforeAges,afterAges,beforeCalls,afterCalls},{beforeAges:2400,afterAges:1212,beforeCalls:1200,afterCalls:0});
 const rawOrder=perfHotels.map(h=>h.offers),rawOffers=perfHotels.map(h=>h.offers[0]);perf.matchingCount(model);perfHotels.forEach((h,i)=>{assert.strictEqual(h.offers,rawOrder[i]);assert.strictEqual(h.offers[0],rawOffers[i]);});

 // Recalculate every invocation and retain native find semantics for unusual
 // arrays instead of adding a persistent or normalized cache.
 const live=make(source,[{...fixture()[1],offers:[base]}]);vm.runInContext(source.match(/^const countMatchingHotels=[^\n]+/m)[0]+'\nglobalThis.matchingCount=countMatchingHotels;globalThis.referenceCount=model=>hotels.reduce((count,h)=>count+Number(hotelOffers(h,{...model,firstOnly:true}).length>0),0);',live);
 const compareCount=()=>assert.equal(live.matchingCount(model),live.referenceCount(model));compareCount();live.hotels[0].offers[0].search.origin='Казань';compareCount();live.hotels[0].offers[0].search.origin='Москва';live.state.search.origin='Казань';compareCount();live.state.search.origin='Москва';
 const inherited=[];Object.setPrototypeOf(inherited,Object.assign(Object.create(Array.prototype),{0:base}));inherited.length=1;live.hotels[0].offers=inherited;compareCount();
 const sparse=new Array(2);live.hotels[0].offers=sparse;const observe=fn=>{try{return {value:fn()}}catch(error){return {name:error.name,message:error.message}}};assert.deepEqual(observe(()=>live.matchingCount(model)),observe(()=>live.referenceCount(model)),'sparse-array error semantics');
 const makeGrowing=()=>{const appended={...base,key:'appended',search:{...base.search}},growing=[{...base,key:'first',search:{...base.search}}];let pushed=false;Object.defineProperty(growing[0].search,'origin',{get(){if(!pushed){pushed=true;growing.push(appended)}return 'Казань'}});return growing;};
 const actualGrowing=makeGrowing();live.hotels[0].offers=actualGrowing;const actualFirst=live.matchingCount(model);assert.equal(actualGrowing.length,2);const referenceGrowing=makeGrowing();live.hotels[0].offers=referenceGrowing;const referenceFirst=live.referenceCount(model);assert.equal(referenceGrowing.length,2);assert.equal(actualFirst,referenceFirst,'find captures initial array length');live.hotels[0].offers=actualGrowing;compareCount();
 console.log(`PASS hotel membership: ${rows.length} model cases; digest ${hash}; 31410 unused resort inventories -> 0; scalar ages ${beforeAges}->${afterAges}, hotelOffers/singleton arrays ${beforeCalls}->${afterCalls}; live/sparse/inherited/initial-length and raw identity/order retained`);
}

function expandedCalendar(source){
 const hs=fixture(),ctx=make(source,hs),rows=[];
 ctx.calendarHotels=[hs[1],hs[0]];ctx.startDay=day(0);ctx.endDay=day(6);ctx.datePrices=new Map();
 ctx.calendarObservations=[{date:day(0),price:0},{date:day(1),price:90000},{date:day(1),price:89000},{date:day(2),price:NaN},{date:day(7),price:1}];
 vm.runInContext(section(source,'function refreshCalendarPriceCache(){','function calendarPrice('),ctx);
 for(const filters of variants)for(const supported of [false,true]){
  ctx.dateContext={search:ctx.state.search,filters:{...defaultFilters(),...filters}};ctx.supported=supported;ctx.refreshCalendarPriceCache();
  rows.push(Array.from({length:7},(_,i)=>ctx.datePrices.get(day(i))??null));
 }
 return rows;
}
{
 const rows=expandedCalendar(source),hash=crypto.createHash('sha256').update(JSON.stringify(rows)).digest('hex');if(compare>=0)assert.deepEqual(rows,expandedCalendar(fs.readFileSync(process.argv[compare+1],'utf8')),'expanded calendar price parity');
 assert.equal(hash,'b3d315ee46ea6ba1e7eeb5c657afffa370f5be2042a74a85b07debd532521423','pinned original expanded calendar prices');
 const hs=fixture(),ctx=make(source,hs),raw=hs[1].offers;
 const options={selectedDate:null,onlyFavorites:false},all=ctx.hotelOffers(hs[1],options),unordered=ctx.hotelOffers(hs[1],{...options,sort:false});
 assert.deepEqual(Array.from(unordered).sort((a,b)=>a.total-b.total||a.day.localeCompare(b.day)),Array.from(all),'same raw offers after ordering');
 assert(unordered.every(o=>raw.includes(o)),'unordered offers retain references');
 let sorts=0;for(const h of hs)h.offers.filter=function(...args){const rows=Array.prototype.filter.apply(this,args);rows.sort=function(...args){sorts++;return Array.prototype.sort.apply(this,args)};return rows;};
 ctx.run([day(0),day(1),day(2)],{},[]);assert.equal(sorts,0,'calendar does not sort offer arrays');
 Object.assign(ctx,{calendarHotels:[hs[1]],startDay:day(0),endDay:day(6),datePrices:new Map(),calendarObservations:[],dateContext:{search:ctx.state.search,filters:defaultFilters()}});
 vm.runInContext(section(source,'function refreshCalendarPriceCache(){','function calendarPrice('),ctx);ctx.refreshCalendarPriceCache();assert.equal(sorts,0,'expanded calendar does not sort offer arrays');
 ctx.hotelOffers(hs[1],options);assert.equal(sorts,1,'normal offer consumers remain sorted');
 console.log(`PASS calendar ordering: ${rows.length} expanded price sequences; digest ${hash}; raw membership retained; per-hotel calendar sorts removed, normal sorting retained`);
}

// Batch facet counts are compared with independent scalar existence counts.
function facetCountRecords(candidate,checkReference=true){
 const hs=fixture(),ctx=make(candidate,hs),before=JSON.stringify(hs),rows=[];
 ctx.mealNames.alias=7;ctx.mealNames.invalid='7';
 vm.runInContext(candidate.match(/^const countMatchingHotels=[^\n]+/m)[0]+'\n'+section(candidate,'function countFacetOptions(','const hotelCountText=')+'\nglobalThis.facetCounts=countFacetOptions;',ctx);
 const values={operators:['A','B','missing'],meals:['AI','BB','alias','missing'],flight:['regular','charter','unknown'],resorts:['Кемер','Анталья','missing'],stars:[2,3,4,5,9]};
 for(const filters of variants)for(const live of [false,true])for(const selectedDate of [null,day(2)])for(const onlyFavorites of [false,true]){
  ctx.data.live=live;const model={filters:{...defaultFilters(),...filters},selectedDate,onlyFavorites};
  for(const [group,options]of Object.entries(values)){
   const actual=ctx.facetCounts(model,group,options);
   const expected=options.map(value=>hs.filter(h=>ctx.hotelOffers(h,{...model,filters:{...model.filters,[group]:[value]},firstOnly:true}).length).length);
   const counts=options.map(value=>actual.get(value));if(checkReference)assert.deepEqual(counts,expected,'scalar facet counts: '+group);rows.push(counts);
  }
 }
 assert.equal(JSON.stringify(hs),before,'facet counting preserves raw objects and offer order');
 const model={filters:defaultFilters(),selectedDate:null,onlyFavorites:false};
 assert.equal(ctx.facetCounts(model,'operators',[]).size,0,'empty options');
 const nil=make(candidate,[null,undefined]);vm.runInContext(section(candidate,'function countFacetOptions(','const hotelCountText=')+'\nglobalThis.facetCounts=countFacetOptions;',nil);
 assert.equal(nil.facetCounts(model,'operators',['A']).get('A'),0,'nullable entries retain hotelOffers semantics');
 assert.equal(ctx.facetCounts(model,'operators',['A','A']).size,1,'duplicate options do not multiply counts');
 assert.equal(ctx.facetCounts(model,'amenities',['pool']).get('pool'),hs.filter(h=>ctx.hotelOffers(h,{...model,filters:{...model.filters,amenities:['pool']},firstOnly:true}).length).length,'fallback keeps original scalar semantics');
 ctx.data.live=true;assert.equal(ctx.facetCounts(model,'meals',['invalid']).get('invalid'),0,'numeric string is not a canonical meal identity');
 return rows;
}
{
 const rows=facetCountRecords(source),hash=crypto.createHash('sha256').update(JSON.stringify(rows)).digest('hex');
 assert.equal(hash,'2029aef32f0a35ffeac388a0db3df79d1e1ae2fb31a01cf436c1b80c91de2b52','original scalar facet-count sequences');
 for(const [from,to]of [['remaining.delete(key);',''],['!remaining.has(key)||!matches(o)','!remaining.has(key)'],['if(!hotelMatch(h,filters,s,model.onlyFavorites??state.onlyFavorites))continue;','']]){
  assert(source.includes(from),'actual facet mutation boundary');
  assert.notDeepEqual(facetCountRecords(source.replace(from,to),false),rows,'facet predicate/deduplication mutation detected');
 }
 // Reuse the same hotel and model after updates: this pass has no persistent cache.
 const h=fixture()[1],ctx=make(source,[h]),model={filters:defaultFilters(),selectedDate:null,onlyFavorites:false};
 vm.runInContext(section(source,'function countFacetOptions(','const hotelCountText=')+'\nglobalThis.facetCounts=countFacetOptions;',ctx);
 const options=['new','A','B'],read=()=>options.map(value=>ctx.facetCounts(model,'operators',options).get(value));
 assert.deepEqual(read(),[0,1,1]);h.offers.push({...h.offers[2],operator:'new'});
 assert.deepEqual(read(),[1,1,1],'new response contributions are counted');
 model.filters.min=Infinity;assert.deepEqual(read(),[0,1,0],'filter edits are recalculated');
 model.filters.min=0;ctx.state.search.origin='другое';assert.deepEqual(read(),[0,0,0],'new search scope is recalculated');
 // Deterministic work budget; this is not whole-page or production timing.
 const hs=Array.from({length:100},(_,i)=>{const h=fixture()[1];return {...h,id:i+1,offers:Array.from({length:1000},(_,j)=>({...h.offers[2],key:i+':'+j,operator:'OP'+(j%40),total:100000+j}))};});
 const work=make(source,hs),values=Array.from({length:40},(_,i)=>'OP'+i),scope={filters:defaultFilters(),selectedDate:null,onlyFavorites:false};
 vm.runInContext(section(source,'function countFacetOptions(','const hotelCountText=')+'\nglobalThis.facetCounts=countFacetOptions;globalThis.ageCalls=0;const stringify=JSON.stringify;JSON.stringify=(...args)=>{ageCalls++;return stringify(...args)};',work);
 const original=values.map(value=>hs.filter(h=>work.hotelOffers(h,{...scope,filters:{...scope.filters,operators:[value]},firstOnly:true}).length).length),before=work.ageCalls;
 work.ageCalls=0;const batched=work.facetCounts(scope,'operators',values),after=work.ageCalls;
 assert.deepEqual(values.map(value=>batched.get(value)),original);assert.equal(before,86000);assert.equal(after,4001);
 work.ageCalls=0;const missing=work.facetCounts(scope,'operators',[...values,'missing']);assert.equal(missing.get('missing'),0);assert.equal(work.ageCalls,4001,'already-counted identities do not repeat predicate work');
 vm.runInContext('const originalFacetPredicate=hotelOfferPredicate;hotelOfferPredicate=function(...args){globalThis.facetPredicateCalls++;return originalFacetPredicate(...args)};',work);work.facetPredicateCalls=0;
 for(const [group,options]of Object.entries({meals:['AI','BB'],operators:['A','B'],flight:['regular','charter']}))work.facetCounts(scope,group,options);
 assert.equal(work.facetPredicateCalls,3,'one invariant offer predicate per non-hotel facet pass');
 console.log(`PASS facet count inventory: 2160 scalar comparisons; digest ${hash}; incremental/filter/scope/alias/deduplication guards; age serializations ${before}->${after}; predicate constructions 300->${work.facetPredicateCalls}; supplier/lead HTTP 0`);
}
