// Site100 presentation composition replaces the earlier tour layout. Selected-offer objects and policy collaborators are unchanged; only inert unavailable markup is computed before frame assembly. The 8194 action-policy, terminal/error/stale recovery and mutation cases remain.
// Actual offer rendering and action policy, with surrounding formatting/data
// collaborators intercepted. No quote, supplier transport or lead submission.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
function owner(source){const start=source.indexOf('function offerSelectionHint('),end=source.indexOf('function openFlightPicker(',start);assert(start>=0&&end>start);return source.slice(start,end);}
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const moneyFormat=new Intl.NumberFormat('ru-RU',{minimumFractionDigits:1});
function observe(source,s){
 const calls=[],dom=new Map();const o={key:'offer<&"',provider:s.provider,hotelId:7,total:133500.5,operator:'Operator<&',room:'Room<&',ages:[0,17],day:'2026-10-14',returnDay:'2026-10-21',nights:7,
  loading:!!(s.flags&1),flightsLoading:!!(s.flags&2),quoteError:s.flags&4?'Ошибка<&':'',pricePending:!!(s.flags&8),
  tour:s.flags&16?{price:133500.5}:null,variants:s.flags&32?[{id:'pair'}]:[],flightChoiceId:s.flags&32?'0':'',
  quoteErrorTerminal:!!(s.flags&64),quoteErrorCode:s.flags&128?'offer_expired':'',flightsError:s.flags&256?'flight error':'',raw:{anexKind:s.group?'group_minimum':'concrete',...(s.session?{anexSessionCurrent:true}:{})}};
 const hotel={id:7,name:'Hotel<&"',resort:'Resort<&',country:'4',photos:s.noPhoto?[]:['photo<&']};
 const call=(name,fn)=>(...args)=>{calls.push([name,...args.map(x=>x===o?'OFFER':x===hotel?'HOTEL':x)]);return fn?fn(...args):'['+name+']';};
 const node=key=>{if(!dom.has(key))dom.set(key,{innerHTML:'initial',hidden:true,dataset:{},classList:{add:name=>calls.push(['classAdd',key,name])}});return dom.get(key);};
 const ctx={Math,Number,String,Array,JSON,selectedOffer:s.noOffer?null:o,countryNames:{'4':'Турция<&'},$:node,esc,
  data:{live:s.live,amount:call('amount',v=>Number(v)||null)},
  selectedTourHotel:call('selectedTourHotel',()=>s.noHotel?null:hotel),needsRefresh:call('needsRefresh',()=>s.unavailable),
  rememberProviderView:call('rememberProviderView',()=>undefined),
  showModal:call('showModal',()=>undefined),flightPairFor:call('flightPairFor',x=>x.flightChoiceId?{id:'pair'}:null),
  money:call('money',v=>moneyFormat.format(Number(v))+' ₽'),
  guestsText:call('guestsText',()=> '2 взрослых · 2 ребёнка'),nightsText:call('nightsText',n=>n+' ночей'),
  photoUrl:call('photoUrl',h=>h.photos[0]),icon:call('icon',n=>'<i>'+n+'</i>'),
  window:{AnyTourPrototypeLead:{unavailableMarkup:call('unavailableMarkup',()=>'<offline-unavailable>')}}
 };
 for(const name of ['selectionStepsHTML','quotePriceChangeHTML','hotelStarsHTML','refreshOfferNotice','priceNote','chosenStayHTML','flightSummaryHTML','fuelDisclosureHTML','selectedPriceStatus','refreshOfferActionLabel'])ctx[name]=call(name);
 vm.createContext(ctx);(typeof source==='string'?new vm.Script(owner(source)):source).runInContext(ctx);ctx.renderRealOffer();
 return JSON.parse(JSON.stringify({calls,dom:[...dom].map(([key,n])=>[key,{html:n.innerHTML,hidden:n.hidden}]),selectedOffer:ctx.selectedOffer}));
}
const scenarios=[];
for(const provider of ['tourvisor','andromeda','anex','fixture'])for(const live of [false,true])for(const unavailable of [false,true])for(let flags=0;flags<512;flags++)scenarios.push({provider,live,unavailable,flags,noPhoto:!!(flags&16),group:!!(flags&32)});
scenarios.push({provider:'tourvisor',live:true,flags:0,noOffer:true},{provider:'tourvisor',live:true,flags:0,noHotel:true});
// Compile each source/mutation once; every scenario still receives a fresh VM
// context and executes the complete owner with the same observed collaborators.
function records(source){const script=new vm.Script(owner(source));return scenarios.map(s=>observe(script,s));}
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex'),i=process.argv.indexOf('--compare');
const flightGuard="${unavailable||terminalQuoteError?'':flightSummaryHTML(o)}";
assert(source.includes(flightGuard),'terminal flight presentation guard');
const baseline=records(source.replace(flightGuard,'${flightSummaryHTML(o)}'));
assert.equal(crypto.createHash('sha256').update(JSON.stringify(baseline)).digest('hex'),'b622b57c5d36aa7ea230bb5828db319698b24f151fac260e318a19ee8b0b26ad','approved journey frame and terminal flight-summary policy');
function expectedDelta(before){return before.map((record,index)=>{
 const s=scenarios[index];if(!s.unavailable&&!(s.flags&64)&&!(s.flags&128))return record;
 const expected=JSON.parse(JSON.stringify(record));expected.calls=expected.calls.filter(c=>c[0]!=='flightSummaryHTML');
 for(const c of expected.calls)if(c[0]==='showModal')c[4]=c[4].replace('[flightSummaryHTML]','');
 return expected;
});}
assert.deepEqual(actual,expectedDelta(baseline),'only terminal/unavailable flight block and its renderer invocation may disappear');
if(i>=0)assert.deepEqual(actual,expectedDelta(records(fs.readFileSync(process.argv[i+1],'utf8'))),'original/candidate exact intentional presentation delta');
const verified=observe(source,{provider:'tourvisor',live:true,unavailable:false,flags:16});
assert(verified.calls.some(c=>c[0]==='rememberProviderView'));
assert(verified.calls.find(c=>c[0]==='showModal')[4].includes('tour-layout'));
for(const flags of [0,1]){
 const direct=observe(source,{provider:'anex',live:true,unavailable:true,flags,session:true});
 const footer=direct.dom.find(([key])=>key==='#modal-footer')[1].html;
 assert.match(footer,/data-action="select-anex-tour"/,'current concrete ANEX has one primary flight-price path');
 assert.equal(footer.includes('disabled'),!!flags,'pending context blocks duplicate primary clicks');
 assert.match(direct.calls.find(c=>c[0]==='showModal')[4],/Уточнить цену без выбора рейсов/,'optional no-flight route remains separate');
}
const loading=observe(source,{provider:'tourvisor',live:true,unavailable:false,flags:17});
assert(!loading.calls.some(c=>c[0]==='rememberProviderView'));
const terminal=observe(source,{provider:'anex',live:true,unavailable:true,flags:64});
assert(terminal.dom.find(([key])=>key==='#modal-footer')[1].html.includes('data-action="all-offers"'));
assert(!terminal.calls.some(c=>c[0]==='flightSummaryHTML'));
const flightError=observe(source,{provider:'tourvisor',live:true,unavailable:false,flags:16|256});
assert(flightError.calls.some(c=>c[0]==='flightSummaryHTML'),'available flight-error retains the retry block');
assert(flightError.dom.find(([key])=>key==='#modal-footer')[1].html.includes('data-action="confirm-tour"'),'available flight-error retains application');
assert.notDeepEqual(records(source.replace(flightGuard,'${flightSummaryHTML(o)}')),actual,'unavailable flight actions mutation detected');
assert.notDeepEqual(records(source.replace(flightGuard,"${unavailable?'':flightSummaryHTML(o)}")),actual,'terminal-only flight actions mutation detected');
assert.notDeepEqual(records(source.replace('o.quoteErrorTerminal===true||','false||')),actual,'terminal action mutation detected');
assert.notDeepEqual(records(source.replace('&&!o.loading&&!o.quoteError&&!o.flightsLoading','&&!o.quoteError&&!o.flightsLoading')),actual,'retained selection guard mutation detected');
assert.notDeepEqual(records(source.replace("${o.pricePending?'Цена уточняется':money(o.total)}","${money(o.total)}")),actual,'pending price disclosure mutation detected');
console.log(`PASS offer rendering: ${actual.length} states, approved presentation and exact terminal-flight policy; actual digest ${digest}; supplier/lead HTTP 0`);

// Execute the actual asynchronous loader against controlled supplier responses.
// The real transport is never called; stale replies must not replace a new tour.
async function flightRecovery(){
 const checkSource=process.env.SEARCH3_FLIGHT_RECOVERY_SOURCE?fs.readFileSync(process.env.SEARCH3_FLIGHT_RECOVERY_SOURCE,'utf8'):source;
 const summary=checkSource.slice(checkSource.indexOf('function flightSummaryHTML('),checkSource.indexOf('async function quoteSelectedOffer('));
 const loading=checkSource.slice(checkSource.indexOf('async function openOffer('),checkSource.indexOf('function leadReadyOffer('));
 function setup(){
  let resolve,reject;const response=new Promise((a,b)=>{resolve=a;reject=b;}),events=[],modal={open:true};
  const ctx={selectedOffer:{key:'exact-tour',provider:'tourvisor',tour:{price:115764},total:115764,room:'standard room without balcony',variants:[]},selectionGeneration:7,modalType:'offer',andromedaApplicationDraft:null,
   data:{flights:()=>{events.push('request');return response;},text:String},$:()=>modal,Math,String,esc,
   icon:()=>'',flightPairFor:o=>o.variants?.[Number(o.flightChoiceId)]||null,
   window:{AnyTourFlightPickerV18:{pairSummary:()=>'<pair>'}},legHTML:()=>'',savedFlightSummaryHTML:()=>'',
   withFlightPair:(o,id)=>({...o,flightChoiceId:id}),renderRealOffer:()=>events.push('render'),openFlightPicker:()=>events.push('picker'),
   restoreProviderView:()=>true,offerFromKey:()=>ctx.selectedOffer,needsRefresh:()=>false,quoteSelectedOffer:()=>{throw Error('cached quote must not be repeated');}};
  vm.createContext(ctx);vm.runInContext(summary+loading,ctx);return {ctx,resolve,reject,events,modal};
 }
 const success=setup(),pending=success.ctx.loadRealFlights(7,{chooseFlight:true});
 assert.match(success.ctx.flightSummaryHTML(success.ctx.selectedOffer),/role="status">Загружаем/);
 assert(!success.ctx.flightSummaryHTML(success.ctx.selectedOffer).includes('data-action="retry-flights"'),'pending request hides retry');
 success.resolve([{isDefault:true}]);await pending;
 assert.deepEqual(success.events,['render','request','render','picker'],'explicit retry opens loaded picker once');
 assert.equal(success.ctx.selectedOffer.total,115764);assert.equal(success.ctx.selectedOffer.room,'standard room without balcony');
 const passive=setup(),passivePending=passive.ctx.loadRealFlights();passive.resolve([{}]);await passivePending;
 assert(!passive.events.includes('picker'),'existing passive caller retains its navigation policy');
 const empty=setup(),emptyPending=empty.ctx.loadRealFlights(7,{chooseFlight:true});empty.resolve([]);await emptyPending;
 assert(!empty.events.includes('picker'));assert.match(empty.ctx.flightSummaryHTML(empty.ctx.selectedOffer),/role="status">Поставщик не передал варианты рейсов/);
 assert.match(empty.ctx.flightSummaryHTML(empty.ctx.selectedOffer),/оставить заявку/);assert.match(empty.ctx.flightSummaryHTML(empty.ctx.selectedOffer),/data-action="retry-flights"/);
 const failure=setup(),failedPending=failure.ctx.loadRealFlights(7,{chooseFlight:true});failure.reject(Error('fixture failure'));await failedPending;
 assert(!failure.events.includes('picker'));assert.match(failure.ctx.flightSummaryHTML(failure.ctx.selectedOffer),/Не удалось загрузить рейсы/);
 for(const change of ['generation','closed','application','new-tour']){
  const stale=setup(),stalePending=stale.ctx.loadRealFlights(7,{chooseFlight:true});
  if(change==='generation')stale.ctx.selectionGeneration++;if(change==='closed')stale.modal.open=false;
  if(change==='application')stale.ctx.modalType='lead';if(change==='new-tour')stale.ctx.selectedOffer={key:'another-tour',total:999};
  const before=JSON.stringify(stale.ctx.selectedOffer);stale.resolve([{}]);await stalePending;
  assert.equal(JSON.stringify(stale.ctx.selectedOffer),before,change+' ignores late result');assert(!stale.events.includes('picker'));
 }
 const cached=setup(),cachedPending=cached.ctx.openOffer('exact-tour',null,true);cached.resolve([{}]);await cachedPending;
 assert.equal(cached.events.filter(e=>e==='request').length,1);assert.equal(cached.events.filter(e=>e==='picker').length,1,'cached tour with no variants loads and opens picker');
 console.log('PASS flight recovery: explicit success/empty/error, passive navigation, four stale replies, cached no-variant tour; supplier/lead HTTP 0');
}
flightRecovery().catch(error=>{console.error(error);process.exitCode=1;});
