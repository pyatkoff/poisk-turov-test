// Observe the actual result, calendar and offer-list owners at DOM/data boundaries.
// Baseline is app blob 33bee027, before the combined structural extraction.
// No application bootstrap, supplier transport, quote or lead submission executes.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const copy=x=>JSON.parse(JSON.stringify(x));
const esc=x=>String(x).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function section(source,first,last){const a=source.indexOf(first),b=source.indexOf(last,a);assert(a>=0&&b>a,'actual owner boundaries');return source.slice(a,b);}
function owner(source,kind){
 if(kind==='results')return section(source,"let renderedCardLimit=24,renderedCardScope='';",'function syncFilters(){');
 if(kind==='calendar')return section(source,source.includes('function resultCalendarModel(){')?'function resultCalendarModel(){':'function renderCalendarStrip(){','function renderActive(){');
 return section(source,source.includes('function offerListInventory(){')?'function offerListInventory(){':'function renderOfferList(reset=false){','let verifiedOffer=null;');
}
function observe(source,s){
 const trace=[],nodes=new Map();let ctx;
 const record=(name,...args)=>trace.push([name,...args]);
 const call=(name,fn)=>(...args)=>{record(name,...args);return fn?.(...args);};
 function node(key){
  if(nodes.has(key))return nodes.get(key);
  const classes=new Set(s.comparing?['tour-comparison-dialog']:[]);
  const target={id:key==='#anchor'?'hotel-1':key,value:'old',textContent:'old',innerHTML:'<old>',hidden:false,title:'old',open:false,options:s.optionsCurrent?[{value:''},{value:'2026-10-14'},{value:'2026-10-15'}]:[],dataset:{action:s.noAction?undefined:'offer'},
   contains:x=>!!s.focus&&x===node('#active'),
   closest:()=>s.noAnchor?null:node('#anchor'),
   getBoundingClientRect:()=>({top:key==='#next-anchor'?s.nextTop??130:100}),
   querySelector:selector=>{record('query',key,selector);return s.noFallback?null:node(key+':'+selector);},
   setAttribute:(k,v)=>record('attribute',key,k,v),
   classList:{toggle:(k,v)=>{record('classToggle',key,k,v);v?classes.add(k):classes.delete(k);},contains:k=>classes.has(k)}
  };
  const n=new Proxy(target,{set:(t,k,v)=>{record('write',key,k,v);t[k]=v;return true;}});nodes.set(key,n);return n;
 }
 const search={origin:'Москва<&',country:'4',from:'2026-10-14',to:s.singleDay?'2026-10-14':'2026-10-16',minNights:7,maxNights:10,adults:2,ages:[0,17]};
 const items=Array.from({length:s.count??2},(_,i)=>({hotel:{id:i+1},offers:Array.from({length:i===0?s.total??2:1},(_,j)=>({key:`${i}:${j}`}))}));
 ctx={Math,Number,String,Array,Set,JSON,esc,$:node,$$:selector=>[node(selector+'0'),node(selector+'1')],
  state:{search,filters:{meal:['AI']},selectedDate:s.selected?'2026-10-15':'',sort:s.sort||'price',hasSearched:!s.pristine,onlyFavorites:!!s.favorites},
  searchEditSession:!!s.draft,filterDraft:!!s.filterDraft,modalType:s.modal||'',data:{scenario:'live'},operators:['tourvisor','anex'],searchResponse:{key:s.stale?'old':'current',phase:s.phase||'complete',pending:true},
  document:{activeElement:node('#active'),body:node('body'),getElementById:id=>{record('getElementById',id);return s.anchorMissing?null:node('#next-anchor');}},
  CSS:{escape:x=>'escaped-'+x},scrollY:400,window:{scrollTo:call('scrollTo')},
  searchKey:call('searchKey',()=> 'current'),clearSearchTimers:call('clearSearchTimers'),results:call('results',()=>items),
  appliedDestination:call('appliedDestination',()=>({kind:'resort',id:9})),destinationLabel:call('destinationLabel',()=> 'Кемер'),countryNames:{'4':'Турция'},
  dateText:call('dateText',d=>'date:'+d),dateLong:call('dateLong',d=>'long:'+d),rangeText:call('rangeText',(a,b)=>a+' — '+b),durationText:call('durationText',()=> '7–10 ночей'),guestsText:call('guestsText',()=> '2 взрослых · дети 0/17'),departureScopeText:call('departureScopeText',()=> 'даты поиска'),
  responseFor:call('responseFor',()=>({phase:s.phase||'complete'})),hotelCountText:call('hotelCountText',n=>n+' отелей'),
  cardHTML:call('cardHTML',r=>`<card id="${r.hotel.id}">${r.offers.length}</card>`),emptyResultsHTML:call('emptyResultsHTML',()=>'<empty>'),
  focusReference:call('focusReference',()=>({selector:'#old',top:17})),restoreFocus:call('restoreFocus'),
  resultCalendar:{hotels:[{id:9}],observations:[{day:'2026-10-14',price:12}],phase:s.phase||'complete'},hotels:[{id:1,name:'Hotel<&',resort:'Кемер'}],
  addDays:call('addDays',d=>new Date(Date.parse(d)+86400000).toISOString().slice(0,10)),
  calendarMinimum:call('calendarMinimum',day=>s.prices?.[Number(day.slice(-2))-14]??null),
  calendarSourceLabel:call('calendarSourceLabel',()=> 'Найденные цены'),calendarScope:call('calendarScope',()=>({destination:'Кемер',filters:s.noFilters?[]:['meal']})),money:call('money',n=>n+' ₽'),
  optionalShortlistEnabled:!!s.shortlist,innerWidth:s.mobile?390:1280,
  offerView:{id:1,mode:s.comparing?'compare':'list',departure:s.departure||'',flight:s.flight||'',room:s.room||'',meal:s.meal||'',sort:s.sort||'price',open:['r1-AI'],limits:{'r1-AI':1}},
  offerRefinementFields:['departure','flight','room','meal'],mealNames:{AI:'Всё включено'},comparisonQuotes:[{key:'old'}]
 };
 for(const name of ['renderFilters','updateFacetCounts','syncFilterResetState','updateDrawerPreview','updateBudgetPreview','updateMealCounts','updateMealPicker','renderCalendarStrip','renderActive','updateNav','renderSummary','updateURL','renderSearchStatus','loadResultCalendar','refreshEmptyCalendarContext','renderOfferRefinements','renderTourComparison','rememberUIRoute'])ctx[name]=call(name);
 const offers=s.empty?[]:Array.from({length:8},(_,i)=>({key:'o'+i,day:i%2?'2026-10-15':'2026-10-14',returnDay:i%2?'2026-10-22':'2026-10-21',nights:i%2?7:8,total:i%3?100000+i:100000,room:i%2?'r2':'r1',meal:i%3?'AI':'BB',flight:i%2?'regular':'charter',operator:'Operator<&'}));
 ctx.hotelOffers=call('hotelOffers',()=>offers);
 ctx.offerGroupKey=call('offerGroupKey',o=>o.room+'-'+o.meal);
 ctx.offerRefinementLabel=call('offerRefinementLabel',(field,value)=>field+':'+value);
 for(const name of ['flightLabel','offerSearchContext','offerCountText','nightsText','operatorBadge','offerMetaNote','offerActionLabel','icon','mealLabel','offerRefinementRecovery'])ctx[name]=call(name,v=>name+':'+(v?.key??v?.operator??v?.meal??v??''));
 ctx.sharedOfferNote=call('sharedOfferNote',()=>s.shared?'Общее примечание':'');
 // DOM identities are represented as stable names in collaborator traces.
 for(const name of ['focusReference','restoreFocus'])ctx[name]=(...args)=>{record(name,...args.map(x=>x?.id||x));return name==='focusReference'?{selector:'#old',top:17}:undefined;};
 // The optimized bulk port supplies the same price sequence to this DOM owner.
 // The real bulk algorithm is characterized in calendar-inventory.cjs.
 ctx.calendarMinimums=(days,options,observations)=>days.map(day=>ctx.calendarMinimum(day,options,observations));
 vm.createContext(ctx);vm.runInContext(owner(source,s.kind),ctx);
 if(s.kind==='results'){
  if(s.sameScope)vm.runInContext("renderedCardScope=JSON.stringify([state.search,state.filters,state.selectedDate,state.sort,state.onlyFavorites,data.scenario]);renderedCardLimit=48;",ctx);
  ctx.renderResults({keepFilters:!!s.keepFilters});
 }else if(s.kind==='calendar')ctx.renderCalendarStrip();else ctx.renderOfferList(!!s.reset);
 const dom=[...nodes].map(([key,n])=>[key,{value:n.value,textContent:n.textContent,innerHTML:n.innerHTML,hidden:n.hidden,title:n.title,open:n.open}]);
 return copy({trace,dom,state:ctx.state,response:ctx.searchResponse,view:ctx.offerView,comparisonQuotes:ctx.comparisonQuotes,
  cards:s.kind==='results'?vm.runInContext('({limit:renderedCardLimit,scope:renderedCardScope})',ctx):null});
}
const scenarios=[];const add=(name,s)=>scenarios.push({name,...s});
for(const draft of [false,true])for(const keepFilters of [false,true])for(const modal of ['', 'budget','meals'])for(const filterDraft of [false,true])add(`filter:${draft}:${keepFilters}:${modal}:${filterDraft}`,{kind:'results',draft,keepFilters,modal,filterDraft});
for(const count of [0,1,2,25,49])for(const pristine of [false,true])for(const phase of ['error','complete'])add(`results:${count}:${pristine}:${phase}`,{kind:'results',count,pristine,phase,selected:true,stale:true});
for(const sameScope of [false,true])for(const focus of [false,true])for(const variant of ['normal','noAnchor','noAction','anchorMissing','noFallback'])add(`focus:${sameScope}:${focus}:${variant}`,{kind:'results',count:49,sameScope,focus,[variant]:true});
for(const total of [1,2,4,5,11,12,14,21,22,24,25])add('plural:'+total,{kind:'results',count:1,total});
for(const phase of ['loading','error','partial','complete'])for(const prices of [[null,null,null],[100,100,100],[100,null,250]])for(const selected of [false,true])add(`calendar:${phase}:${prices}:${selected}`,{kind:'calendar',phase,prices,selected});
add('one date no filters',{kind:'calendar',singleDay:true,noFilters:true,prices:[120]});
for(const comparing of [false,true])for(const shortlist of [false,true])for(const sort of ['date','price'])for(const reset of [false,true])for(const filters of [{},{departure:'2026-10-14'},{flight:'regular',room:'r2',meal:'AI'},{departure:'missing',flight:'charter',room:'missing',meal:'BB'}])add(`offers:${comparing}:${shortlist}:${sort}:${reset}:${JSON.stringify(filters)}`,{kind:'offers',comparing,shortlist,sort,reset,...filters});
add('empty offers',{kind:'offers',empty:true,reset:true});add('shared note mobile',{kind:'offers',shared:true,mobile:true});
function records(source){return scenarios.map(s=>({name:s.name,result:observe(source,s)}));}
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex'),i=process.argv.indexOf('--compare');
if(i>=0)assert.deepEqual(actual,records(fs.readFileSync(process.argv[i+1],'utf8')),'before/after result/calendar/offer-list observations');
if(!process.argv.includes('--capture'))assert.equal(digest,'21632cf0ab0e31a6537ff66c09acc57bdcc2c14fc15f86dfcc96f770df25479d','pinned original observations');
const original=actual.find(r=>r.name==='filter:true:false::false').result;
assert(!original.trace.some(x=>['results','updateURL','cardHTML'].includes(x[0])),'editing form preserves cards and URL');
const changed=mutated=>JSON.stringify(records(mutated))!==JSON.stringify(actual);
assert(changed(source.replaceAll('if(searchEditSession){','if(false&&searchEditSession){')),'draft guard mutation detected');
assert(changed(source.replaceAll('renderedCardLimit=24;','renderedCardLimit=25;')),'card reset mutation detected');
assert(changed(source.replace("offerView.mode==='compare'||!offerView.departure", "false||!offerView.departure")),'comparison date scope mutation detected');
console.log(`PASS result/calendar/offer-list: ${actual.length} DOM/collaborator/state observations; digest ${digest}; draft/focus/date mutations detected; supplier and lead HTTP 0`);
