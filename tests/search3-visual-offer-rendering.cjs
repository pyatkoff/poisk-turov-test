// Actual offer rendering and action policy, with surrounding formatting/data
// collaborators intercepted. No quote, supplier transport or lead submission.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
function owner(source){const start=source.indexOf('function offerSelectionHint('),end=source.indexOf('function openFlightPicker(){',start);assert(start>=0&&end>start);return source.slice(start,end);}
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function observe(source,s){
 const calls=[],dom=new Map();const o={key:'offer<&"',provider:s.provider,hotelId:7,total:133500.5,operator:'Operator<&',room:'Room<&',ages:[0,17],day:'2026-10-14',returnDay:'2026-10-21',nights:7,
  loading:!!(s.flags&1),flightsLoading:!!(s.flags&2),quoteError:s.flags&4?'Ошибка<&':'',pricePending:!!(s.flags&8),
  tour:s.flags&16?{price:133500.5}:null,variants:s.flags&32?[{id:'pair'}]:[],flightChoiceId:s.flags&32?'0':'',
  quoteErrorTerminal:!!(s.flags&64),quoteErrorCode:s.flags&128?'offer_expired':'',flightsError:s.flags&256?'flight error':'',raw:{anexKind:s.group?'group_minimum':'concrete'}};
 const hotel={id:7,name:'Hotel<&"',resort:'Resort<&',country:'4',photos:s.noPhoto?[]:['photo<&']};
 const call=(name,fn)=>(...args)=>{calls.push([name,...args.map(x=>x===o?'OFFER':x===hotel?'HOTEL':x)]);return fn?fn(...args):'['+name+']';};
 const node=key=>{if(!dom.has(key))dom.set(key,{innerHTML:'initial',hidden:true,classList:{add:name=>calls.push(['classAdd',key,name])}});return dom.get(key);};
 const ctx={Math,Number,String,Array,JSON,selectedOffer:s.noOffer?null:o,countryNames:{'4':'Турция<&'},$:node,esc,
  data:{live:s.live,amount:call('amount',v=>Number(v)||null)},
  selectedTourHotel:call('selectedTourHotel',()=>s.noHotel?null:hotel),needsRefresh:call('needsRefresh',()=>s.unavailable),
  rememberProviderView:call('rememberProviderView',()=>undefined),
  showModal:call('showModal',()=>undefined),flightPairFor:call('flightPairFor',x=>x.flightChoiceId?{id:'pair'}:null),
  money:call('money',v=>Number(v).toLocaleString('ru-RU',{minimumFractionDigits:1})+' ₽'),
  guestsText:call('guestsText',()=> '2 взрослых · 2 ребёнка'),nightsText:call('nightsText',n=>n+' ночей'),
  photoUrl:call('photoUrl',h=>h.photos[0]),icon:call('icon',n=>'<i>'+n+'</i>'),
  window:{AnyTourPrototypeLead:{unavailableMarkup:call('unavailableMarkup',()=>'<offline-unavailable>')}}
 };
 for(const name of ['selectionStepsHTML','quotePriceChangeHTML','hotelStarsHTML','refreshOfferNotice','priceNote','chosenStayHTML','flightSummaryHTML','fuelDisclosureHTML','selectedPriceStatus','refreshOfferActionLabel'])ctx[name]=call(name);
 vm.createContext(ctx);vm.runInContext(owner(source),ctx);ctx.renderRealOffer();
 return JSON.parse(JSON.stringify({calls,dom:[...dom].map(([key,n])=>[key,{html:n.innerHTML,hidden:n.hidden}]),selectedOffer:ctx.selectedOffer}));
}
const scenarios=[];
for(const provider of ['tourvisor','andromeda','anex','fixture'])for(const live of [false,true])for(const unavailable of [false,true])for(let flags=0;flags<512;flags++)scenarios.push({provider,live,unavailable,flags,noPhoto:!!(flags&16),group:!!(flags&32)});
scenarios.push({provider:'tourvisor',live:true,flags:0,noOffer:true},{provider:'tourvisor',live:true,flags:0,noHotel:true});
function records(source){return scenarios.map(s=>observe(source,s));}
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex'),i=process.argv.indexOf('--compare');
const flightGuard="${unavailable||terminalQuoteError?'':flightSummaryHTML(o)}";
assert(source.includes(flightGuard),'terminal flight presentation guard');
const baseline=records(source.replace(flightGuard,'${flightSummaryHTML(o)}'));
assert.equal(crypto.createHash('sha256').update(JSON.stringify(baseline)).digest('hex'),'1fe709d5b40c2fc691860711c02e7cc5f44afd24b470b96f35b32c383489fadc','original oracle unchanged outside the intentional flight-summary delta');
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
console.log(`PASS offer rendering: ${actual.length} states, original oracle retained with exact terminal-flight delta; actual digest ${digest}; supplier/lead HTTP 0`);
