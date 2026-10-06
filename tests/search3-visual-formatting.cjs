// Display parity and a deterministic construction budget; no supplier/lead calls.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
function owner(source){
 const first=source.indexOf(source.includes('const amountFormatter=')?'const amountFormatter=':'const money =');
 const last=source.indexOf('const flightDateText',first);
 assert(first>=0&&last>first,'actual presentation helpers');
 const rating=source.match(/^const rating(?:Value|Text)=[^\n]+/gm);
 assert.equal(rating.length,2);
 return source.slice(first,last)+rating.join('\n')+'\nglobalThis.api={money,shortMoney,dateText,dateLong,ratingText};';
}
const numbers=[0,-0,1,-1,999.994,999.995,12345.67,133500.5,125500.555,Number.MAX_SAFE_INTEGER,NaN,Infinity,-Infinity,'187700.50','',null,undefined];
const dates=[];
for(let i=0;i<180;i++)dates.push(new Date(Date.UTC(2026,8,30+i)).toISOString().slice(0,10));
dates.push('2024-02-29','2026-02-29','2026-12-31','2027-01-01','','invalid',null,undefined,'9999-12-31');
const ratings=[null,undefined,NaN,Infinity,-1,0,0.01,1,4.45,4.5,4.95,5,5.01,'4.5'];
const native={
 money:n=>Number(n).toLocaleString('ru-RU',{maximumFractionDigits:2})+' ₽',
 shortMoney:n=>(n/1000).toLocaleString('ru-RU',{maximumFractionDigits:1})+' тыс.',
 dateText:s=>new Date(s+'T12:00:00Z').toLocaleDateString('ru-RU',{day:'numeric',month:'short',timeZone:'UTC'}).replace('.',''),
 dateLong:s=>new Date(s+'T12:00:00Z').toLocaleDateString('ru-RU',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'}),
 ratingText:h=>Number.isFinite(h.rating)&&h.rating>0&&h.rating<=5?h.rating.toLocaleString('ru-RU',{minimumFractionDigits:1,maximumFractionDigits:1}):'—'
};
let constructions=0;
const context={Intl:{NumberFormat:class extends Intl.NumberFormat{constructor(...args){super(...args);constructions++;}},DateTimeFormat:class extends Intl.DateTimeFormat{constructor(...args){super(...args);constructions++;}}}};
vm.createContext(context);vm.runInContext(owner(source),context);const api=context.api;
function observations(api){return [...numbers.flatMap(n=>[api.money(n),api.shortMoney(n)]),...dates.flatMap(s=>[api.dateText(s),api.dateLong(s)]),...ratings.map(rating=>api.ratingText({rating}))];}
const expected=observations(native),actual=observations(api);assert.deepEqual(actual,expected,'same date/amount/rating text, including invalid and non-finite inputs');
const before=constructions;
for(let i=0;i<20;i++)assert.deepEqual(observations(api),expected);
assert.equal(constructions,before,'no per-cell/per-card formatter construction');
assert.equal(before,7,'bounded formatter instances per app');
assert.equal(api.money(133500.5),'133 500,5 ₽');assert.equal(api.dateText('2027-01-01'),'1 янв');assert.equal(api.dateLong('invalid'),'Invalid Date');
assert(source.includes('heading=formatDate(monthFormatter,date)'), 'month heading uses the retained formatter');
assert.equal((source.match(/shortAmount\(price,true\)/g)||[]).length,3,'tape plus both initial and updated calendar cells reuse the short amount formatter');
const compare=process.argv.indexOf('--compare');
function calendarObservations(source){
 const cells=['2026-10-01','2026-10-02','2026-10-03'].map(date=>({dataset:{date},classList:{toggle:(...args)=>trace.push(['class',date,...args])},querySelector:()=>({set textContent(value){trace.push(['text',date,value]);}}),setAttribute:(...args)=>trace.push(['attribute',date,...args])}));
 const trace=[],context={esc:String,dateContext:{search:{adults:2,ages:[]}},partyLabel:()=> '2 взрослых',startDay:'2024-01-01',endDay:'2028-12-31',calendarPrice:day=>day.endsWith('02')?null:133500.5,refreshCalendarPriceCache:()=>trace.push(['refresh']),renderDateSelectionPrice:()=>trace.push(['selection']),$$:()=>[{querySelectorAll:()=>cells}]};
 vm.createContext(context);vm.runInContext(owner(source),context);
 const minimum=source.match(/^function minimumKnownPrice\([^\n]+/m);if(minimum)vm.runInContext(minimum[0],context);
 for(const [first,last] of [['function monthFrame(', 'function renderDateCalendar('],['function refreshCalendarPrices(', 'function loadCalendarPrices(']]){
  const a=source.indexOf(first),b=source.indexOf(last,a);assert(a>=0&&b>a);vm.runInContext(source.slice(a,b),context);
 }
 const frames=['2024-02-01','2026-02-01','2026-10-01','2026-12-01','2027-01-01'].map(context.monthFrame);
 assert(frames[0].includes('data-date="2024-02-29"'),'leap day retained');assert(!frames[1].includes('data-date="2026-02-29"'),'ordinary February');
 assert(frames[2].includes('aria-label="1 октября 2026 г., от 133 500,5 ₽"'),'calendar accessible label');
 assert(frames[2].includes('<small>133,5</small>'),'compact calendar price');
 context.refreshCalendarPrices();assert(trace.some(row=>row[0]==='text'&&row[1]==='2026-10-02'&&row[2]==='—'),'missing-price update');
 return {frames,trace};
}
const calendars=calendarObservations(source);
if(compare>=0){const original=fs.readFileSync(process.argv[compare+1],'utf8'),previous={};vm.createContext(previous);vm.runInContext(owner(original),previous);assert.deepEqual(actual,observations(previous.api),'original/candidate helpers');assert.deepEqual(calendars,calendarObservations(original),'original/candidate month HTML and asynchronous cell updates');}
if(process.argv.includes('--benchmark')){
 const perf=require('node:perf_hooks').performance;
 const workload=target=>{let bytes=0;for(let i=0;i<1047;i++){bytes+=target.money(100000.5+i).length+target.dateText(dates[i%180]).length+target.dateLong(dates[(i+7)%180]).length+target.shortMoney(100000.5+i).length;}return bytes;};
 const measure=target=>{workload(target);const times=[];for(let i=0;i<7;i++){const start=perf.now();workload(target);times.push(perf.now()-start);}return times.sort((a,b)=>a-b)[3];};
 assert.equal(workload(api),workload(native));
 const originalMs=measure(native),candidateMs=measure(api);
 console.log(JSON.stringify({workload:'1047 display rows × four formatters, seven warm runs, median',originalMs,candidateMs,speedup:originalMs/candidateMs,wholePageTiming:false}));
}
console.log(`PASS formatting: ${actual.length} outputs × 21 passes; seven formatter instances; no per-output construction; supplier/lead HTTP 0`);
