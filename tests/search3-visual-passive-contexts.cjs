// Actual passive route restoration and calendar presentation, with only their
// DOM/render/loading boundaries intercepted. No supplier or lead transport runs.
// Pinned baseline: app blob6a27aa34, before context extraction.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const copy=x=>JSON.parse(JSON.stringify(x));
const esc=x=>String(x).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function section(source,first,last){const a=source.indexOf(first),b=source.indexOf(last,a);assert(a>=0&&b>a);return source.slice(a,b);}
function owner(source,kind){
 if(kind==='capture')return section(source,source.includes('function providerHistoryRoute(')?'function providerHistoryRoute(':'function uiRoute(){','function rememberUIRoute(){');
 if(kind==='restore')return section(source,'function retainedProviderView(o){','function rememberProviderView(')+section(source,source.includes('function restoreProviderHistoryRoute(')?'function restoreProviderHistoryRoute(':'function reopenUIRoute(route){','function restoreHistoryView(route){');
 if(kind==='scope')return section(source,source.includes('function calendarScopeHTML(')?'function calendarScopeHTML(':'function renderCalendarScope(){','function mealPreviewModel(){');
 if(kind==='dates')return section(source,"function createDateContext(source='form'){",'const dateContextLabel=')+section(source,source.includes('function prepareDatePicker(')?'function prepareDatePicker(':"function openDates(source='form',restore=null){",'function refreshCalendarPriceCache(){');
 return section(source,'function calendarSelectionPhase(){','function openCalendar(){');
}
function observe(source,s){
 const trace=[],nodes=new Map();let ctx;
 const normalize=x=>x?.nodeKey||x;
 const record=(name,...args)=>trace.push([name,...args.map(normalize),{modal:ctx?.modalType,selected:ctx?.selectedOffer?.key,phase:ctx?.dateDraft?.phase,month:ctx?.calendarMonth}]);
 const call=(name,fn)=>(...args)=>{record(name,...args);return fn?.(...args);};
 function node(key){
  if(s.noNode&&(key==='#date-selection-price'||key==='.calendar-context'))return null;
  if(nodes.has(key))return nodes.get(key);
  const n=new Proxy({nodeKey:key,value:key.includes(':checked')?'choice':'old',checked:false,scrollTop:37,innerHTML:'<old>',hidden:true,offsetHeight:80,dataset:{room:'r1'},style:{setProperty:call('style:'+key)},
   classList:{contains:()=>false},querySelector:()=>null,closest:()=>null,
   scrollIntoView:call('scrollIntoView:'+key),setAttribute:call('attribute:'+key)},
   {set:(t,k,v)=>{record('write:'+key,k,v);t[k]=v;return true;}});
  nodes.set(key,n);return n;
 }
 const offer={key:'o1',provider:s.provider||(s.type?.startsWith('anex-')?'anex':'andromeda'),raw:{identity:'current'}};
 const view={type:s.viewType||s.type,offer,pending:!!s.pending,error:s.error?'error':'',result:{state:s.verified?'quote_verified':'estimate'},choice:'old'};
 const oldPrices=new Map([['old',5]]),oldLoads=new Map([['old','complete']]);
 const search={origin:'Москва<&',country:'4',from:'2026-10-14',to:'2026-10-16',adults:2,ages:s.noAges?[]:[0,17],minNights:7,maxNights:10};
 ctx={Math,Number,String,Array,Set,Map,JSON,Date,structuredClone,esc,$:node,$$:selector=>{const n=node(selector);n.value='choice';return [n];},
  modalType:s.type||'offer',selectedOffer:s.noSelection?null:{key:'previous',raw:{}},savedSelection:null,
  hotels:[{id:7,photos:['p1']}],providerViews:new Map(s.noView?[]:[['o1',{...view,offer:s.rawMismatch?{...offer,raw:{identity:'old'}}:offer}]]),
  offerFromKey:call('offerFromKey',()=>s.noOffer?null:s.currentMismatch?{...offer,raw:{identity:'other'}}:offer),
  innerWidth:s.mobile?390:1280,state:{search,selectedDate:s.selected?'2026-10-15':null,filters:{hotelId:7,resorts:[9],q:'old',meals:['AI'],stars:[5],min:50000,max:200000}},
  draft:{...search,country:s.changedCountry?'5':'4'},draftDestination:s.destination?{resorts:[12],hotelId:88}:null,
  dateContext:{source:s.fromResults?'results':'form',search},dateDraft:s.range||{from:'2026-10-14',to:'2026-10-16',phase:0},
  datePrices:oldPrices,calendarLoads:oldLoads,calendarMonth:'2026-09-01',startDay:'2026-10-01',endDay:'2026-12-31',catalogReady:!s.catalogLoading,catalogError:s.catalogError?'error':'',
  document:{querySelector:selector=>{record('documentQuery',selector);return node(selector);}},
  draftSelectedDate:call('draftSelectedDate',()=>s.selected?'2026-10-16':null),
  dateRangeError:call('dateRangeError',range=>!range.from||!range.to||range.from>range.to||range.from<'2026-10-01'||range.to>'2026-12-31'?'invalid':''),
  dateObj:call('dateObj',day=>new Date(day+'T00:00:00Z')),addDays:call('addDays',day=>new Date(Date.parse(day)+86400000).toISOString().slice(0,10)),
  calendarPrice:call('calendarPrice',day=>s.prices?.[Number(day.slice(-2))-14]??null),
  calendarScope:call('calendarScope',()=>({destination:'Кемер<&',fullDestination:'Турция<& · Кемер',filters:s.noFilters?[]:[{label:'5 звёзд<&'},{label:'Бюджет<&'}]})),
  dateContextLabel:call('dateContextLabel',()=> 'Москва · 2+2'),childAgesLabel:call('childAgesLabel',()=> 'Дети 0 и 17 лет'),calendarSourceLabel:call('calendarSourceLabel',()=> 'Ранее найденная цена<&'),guestsText:call('guestsText',()=> '2 взрослых · дети 0/17'),money:call('money',n=>n+' ₽')
 };
 ctx.selectedOffer=s.kind==='capture'&&!s.noSelection?offer:ctx.selectedOffer;
 for(const name of ['openFilters','openDeparture','openDestination','openGuests','openNights','openDates','openMeals','openBudget','openLeadPreview','openFavorites','openCompare','openHotelDetails','renderHotelRooms','openAllOffers','openGallery','renderRealOffer','rememberAndromedaFlightChoice','openAndromedaApplicationPreview','openAnexApplicationPreview','showModal','renderCalendarScope','refreshCalendarPriceCache','renderDateCalendar','loadCalendarPrices'])ctx[name]=call(name);
 ctx.restoreProviderView=call('restoreProviderView',()=>{ctx.modalType=s.restoredType||view.type;return !s.restoreFailed;});
 ctx.needsRefresh=call('needsRefresh',()=>true);ctx.hotelOffers=call('hotelOffers',()=>[]);
 for(const month of ['2026-10-01','2026-11-01','2026-12-01'])ctx.calendarLoads.set(month,s.phase||'complete');
 vm.createContext(ctx);vm.runInContext(owner(source,s.kind),ctx);let result;
 if(s.kind==='capture')result=ctx.uiRoute();
 else if(s.kind==='restore')result=ctx.reopenUIRoute({type:s.type,key:'o1',id:7,outbound:'choice',inbound:'choice',choice:'choice',scroll:s.invalidScroll?-1:83});
 else if(s.kind==='scope')ctx.renderCalendarScope();
 else if(s.kind==='dates')ctx.openDates(s.fromResults?'results':'form',s.restore||null);
 else ctx.renderDateSelectionPrice();
 return copy({result,trace,dom:[...nodes].map(([key,n])=>[key,{value:n.value,checked:n.checked,scrollTop:n.scrollTop,innerHTML:n.innerHTML,hidden:n.hidden}]),selected:ctx.selectedOffer,modal:ctx.modalType,view:[...ctx.providerViews.values()],dateContext:ctx.dateContext,dateDraft:ctx.dateDraft,month:ctx.calendarMonth,prices:[...ctx.datePrices],loads:[...ctx.calendarLoads],newPrices:ctx.datePrices!==oldPrices,newLoads:ctx.calendarLoads!==oldLoads});
}
const scenarios=[];const add=(name,s)=>scenarios.push({name,...s});
const types=['andromeda-flights','andromeda-verified','provider-application','anex-current','anex-additional','anex-quote','anex-application'];
for(const type of types){
 for(const noSelection of [false,true])add('capture:'+type+':'+noSelection,{kind:'capture',type,noSelection});
 for(const viewType of ['andromeda-flights','andromeda-verified','anex-current','anex-additional','anex-quote'])for(const pending of [false,true])for(const verified of [false,true])add(`restore:${type}:${viewType}:${pending}:${verified}`,{kind:'restore',type,viewType,pending,verified});
 for(const flag of ['noOffer','noView','rawMismatch','currentMismatch','restoreFailed','error','invalidScroll'])add(type+':'+flag,{kind:'restore',type,[flag]:true,verified:true});
}
for(const fromResults of [false,true])for(const selected of [false,true])for(const restore of [null,{draft:{from:'2026-11-02',to:'2026-11-04',phase:1},month:'2026-11-01'},{draft:{from:'bad',to:'',phase:3},month:'bad'},{draft:{from:'2026-11-02',to:'2026-11-02',phase:0},month:'2030-01-01'}])for(const mobile of [false,true])add('dates:'+JSON.stringify([fromResults,selected,restore,mobile]),{kind:'dates',fromResults,selected,restore,mobile,destination:!fromResults});
add('new country context',{kind:'dates',changedCountry:true});
for(const noNode of [false,true])for(const noAges of [false,true])for(const noFilters of [false,true])add(`scope:${noNode}:${noAges}:${noFilters}`,{kind:'scope',noNode,noAges,noFilters});
for(const phase of ['loading','error','idle','complete'])for(const prices of [[null,null,null],[110,90,null],[0,-1,NaN]])for(const range of [{from:'2026-10-14',to:'2026-10-16'},{from:'2026-10-15',to:'2026-10-15'},{from:'',to:''},{from:'2026-10-14',to:'2026-11-10'}])add('price:'+JSON.stringify([phase,prices,range]),{kind:'price',phase,prices,range});
add('catalog loading',{kind:'price',catalogLoading:true,prices:[120]});add('catalog error',{kind:'price',catalogLoading:true,catalogError:true});add('absent price node',{kind:'price',noNode:true});
function records(source){return scenarios.map(s=>({name:s.name,result:observe(source,s)}));}
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex'),i=process.argv.indexOf('--compare');
if(i>=0)assert.deepEqual(actual,records(fs.readFileSync(process.argv[i+1],'utf8')),'before/after passive route/calendar observations');
if(!process.argv.includes('--capture'))assert.equal(digest,'e4136b234fce6937d3cc6c341e5c4dab9d9832118619e9e6c4684dfa8de07648','original passive context observations');
const changed=mutated=>JSON.stringify(records(mutated))!==JSON.stringify(actual);
assert(changed(source.replace('view.offer.raw===o.raw','true')),'retained raw identity mutation detected');
assert(changed(source.replace("modalType==='andromeda-flights'&&!view.pending","modalType==='andromeda-flights'")),'pending history choice mutation detected');
assert(changed(source.replaceAll("source==='results'?state.selectedDate:draftSelectedDate()","draftSelectedDate()")),'source date selection mutation detected');
console.log(`PASS passive contexts: ${actual.length} route/DOM/callback/state observations; digest ${digest}; identity/pending/date-source mutations detected; supplier/lead HTTP 0`);
