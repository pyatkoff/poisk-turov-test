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
if(i>=0)assert.deepEqual(actual,records(fs.readFileSync(process.argv[i+1],'utf8')),'offer HTML and collaborator-order equivalence');
if(!process.argv.includes('--capture'))assert.equal(digest,'1fe709d5b40c2fc691860711c02e7cc5f44afd24b470b96f35b32c383489fadc','pinned original offer rendering observations');
const verified=observe(source,{provider:'tourvisor',live:true,unavailable:false,flags:16});
assert(verified.calls.some(c=>c[0]==='rememberProviderView'));
assert(verified.calls.find(c=>c[0]==='showModal')[4].includes('tour-layout'));
const loading=observe(source,{provider:'tourvisor',live:true,unavailable:false,flags:17});
assert(!loading.calls.some(c=>c[0]==='rememberProviderView'));
const terminal=observe(source,{provider:'anex',live:true,unavailable:true,flags:64});
assert(terminal.dom.find(([key])=>key==='#modal-footer')[1].html.includes('data-action="all-offers"'));
assert.notDeepEqual(records(source.replace('o.quoteErrorTerminal===true||','false||')),actual,'terminal action mutation detected');
assert.notDeepEqual(records(source.replace('&&!o.loading&&!o.quoteError&&!o.flightsLoading','&&!o.quoteError&&!o.flightsLoading')),actual,'retained selection guard mutation detected');
assert.notDeepEqual(records(source.replace("${o.pricePending?'Цена уточняется':money(o.total)}","${money(o.total)}")),actual,'pending price disclosure mutation detected');
console.log(`PASS offer rendering: ${actual.length} states, byte-identical HTML and price/fuel/callback-order digest ${digest}; supplier/lead HTTP 0`);
