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
