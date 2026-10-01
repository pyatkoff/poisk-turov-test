// Observe the actual result, calendar and offer-list owners at DOM/data boundaries.
// Baseline is app blob 33bee027, before the combined structural extraction.
// No application bootstrap, supplier transport, quote or lead submission executes.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const cold=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/offer-list-v1.js'),'utf8');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8')+'\n'+section(cold,'function offerListInventory(','function mountOfferList(){');
const copy=x=>JSON.parse(JSON.stringify(x));
const esc=x=>String(x).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function section(source,first,last){const a=source.indexOf(first),b=source.indexOf(last,a);assert(a>=0&&b>a,'actual owner boundaries');return source.slice(a,b);}
function generatedRootOwner(source){return source.includes('const generatedRootBindings=')?section(source,'const generatedRootBindings=','function renderSummary(){'):'';}
function owner(source,kind){
 if(kind==='results'){const first=source.includes("let renderedCardLimit=24,renderedCardScope='',resultCardObserver=null;")?"let renderedCardLimit=24,renderedCardScope='',resultCardObserver=null;":"let renderedCardLimit=24,renderedCardScope='';";return generatedRootOwner(source)+section(source,first,'function syncFilters(){');}
 if(kind==='calendar')return generatedRootOwner(source)+section(source,source.includes('function resultCalendarModel(){')?'function resultCalendarModel(){':'function renderCalendarStrip(){','function renderActive(');
 const first=source.includes('function offerListInventory(')?'function offerListInventory(':'function renderOfferList(reset=false){';
 const start=source.indexOf(first),end=source.indexOf('let verifiedOffer=null;',start);return end<0?source.slice(start):source.slice(start,end);
}
function observe(source,s){
 const trace=[],nodes=new Map();let ctx;
 const record=(name,...args)=>trace.push([name,...args]);
 const call=(name,fn)=>(...args)=>{record(name,...args);return fn?.(...args);};
 function node(key){
  if(nodes.has(key))return nodes.get(key);
  const classes=new Set(s.comparing?['tour-comparison-dialog']:[]);
  const target={id:key==='#anchor'?'hotel-1':key,value:'old',textContent:'old',innerHTML:'<old>',hidden:false,title:'old',open:false,children:[],firstChild:null,childElementCount:0,options:s.optionsCurrent?[{value:''},{value:'2026-10-14'},{value:'2026-10-15'}]:[],dataset:{action:s.noAction?undefined:'offer'},
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
 ctx={Math,Number,String,Array,Map,Set,WeakMap,WeakSet,JSON,esc,$:node,$$:selector=>[node(selector+'0'),node(selector+'1')],MutationObserver:class{observe(){}takeRecords(){return[];}},
  state:{search,filters:{meal:['AI']},selectedDate:s.selected?'2026-10-15':'',sort:s.sort||'price',hasSearched:!s.pristine,onlyFavorites:!!s.favorites},
  searchEditSession:!!s.draft,filterDraft:!!s.filterDraft,modalType:s.modal||'',data:{scenario:'live'},operators:['tourvisor','anex'],searchResponse:{key:s.stale?'old':'current',phase:s.phase||'complete',pending:true},
  document:{activeElement:node('#active'),body:node('body'),getElementById:id=>{record('getElementById',id);return s.anchorMissing?null:node('#next-anchor');}},
  CSS:{escape:x=>'escaped-'+x},scrollY:400,window:{scrollTo:call('scrollTo')},
  searchKey:call('searchKey',()=> 'current'),clearSearchTimers:call('clearSearchTimers'),resultInventory:call('results',()=>({items,ratingCounts:undefined})),
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
 ctx.renderActive=()=>record('renderActive');
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
 ctx.setComparisonQuotes=value=>{ctx.comparisonQuotes=value;};
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
// O29 changes only two pure-work traces. Keep the original oracle and compare
// every DOM/state write and every other collaborator, rather than re-pin a digest.
const groupStart=cold.indexOf(' const groups=[],byKey=new Map();'),groupEnd=cold.indexOf('\n return {h,all,filtered,groups};',groupStart);
assert(groupStart>=0&&groupEnd>groupStart,'current grouping boundary');
const oldGrouping=' const groups=[...new Set(sorted.map(offerGroupKey))].map(key=>({key,offers:sorted.filter(o=>offerGroupKey(o)===key)}));';
let oldCold=(cold.slice(0,groupStart)+oldGrouping+cold.slice(groupEnd))
 .replace(" const commonNote=groups.length?sharedOfferNote(all):'';\n",'')
 .replace('  const rows=offers.slice','  const commonNote=sharedOfferNote(all);\n  const rows=offers.slice')
 .replace("offerListInventory(offerView.mode!=='compare'||reset)",'offerListInventory()');
const scopeStart=oldCold.indexOf('function offerGroupScope('),scopeEnd=oldCold.indexOf('function renderOfferList(',scopeStart);
if(scopeStart>=0){
 assert(scopeEnd>scopeStart,'current heading scope boundary');
 oldCold=(oldCold.slice(0,scopeStart)+oldCold.slice(scopeEnd))
  .replace('${offerGroupScope(offers)}</small>','${[...new Set(offers.map(o=>nightsText(o.nights)))].join(\' / \')} · ${[...new Set(offers.map(o=>o.day))].length===1?dateText(first.day):\'Вылеты \'+rangeText([...offers].sort((a,b)=>a.day.localeCompare(b.day))[0].day,[...offers].sort((a,b)=>a.day.localeCompare(b.day)).at(-1).day)}</small>');
}
const baseline=records(source.replace(section(cold,'function offerListInventory(','function mountOfferList(){'),()=>section(oldCold,'function offerListInventory(','function mountOfferList(){')));
const baselineDigest=crypto.createHash('sha256').update(JSON.stringify(baseline)).digest('hex');
if(!process.argv.includes('--capture'))assert.equal(baselineDigest,'e76ff50796b381c820621e698dcf69152c710c1486516a02e2b12886d5d4e744','pinned original observations');
const visibleRecords=rows=>rows.map((row,index)=>scenarios[index].kind==='offers'?{...row,result:{...row.result,trace:row.result.trace.filter(call=>call[0]!=='offerGroupKey'&&call[0]!=='sharedOfferNote')}}:row);
assert.deepEqual(visibleRecords(actual),visibleRecords(baseline),'only bounded pure grouping/note work may change');
if(i>=0)assert.deepEqual(visibleRecords(actual),visibleRecords(records(fs.readFileSync(process.argv[i+1],'utf8'))),'before/after result/calendar/offer-list observations');
const original=actual.find(r=>r.name==='filter:true:false::false').result;
assert(!original.trace.some(x=>['results','updateURL','cardHTML'].includes(x[0])),'editing form preserves cards and URL');
const changed=mutated=>JSON.stringify(records(mutated))!==JSON.stringify(actual);
assert(changed(source.replaceAll('if(searchEditSession){','if(false&&searchEditSession){')),'draft guard mutation detected');
assert(changed(source.replaceAll('renderedCardLimit=24;','renderedCardLimit=25;')),'card reset mutation detected');
assert(changed(source.replace("offerView.mode==='compare'||!offerView.departure", "false||!offerView.departure")),'comparison date scope mutation detected');
console.log(`PASS result/calendar/offer-list: ${actual.length} DOM/collaborator/state observations; digest ${digest}; draft/focus/date mutations detected; supplier and lead HTTP 0`);
const {JSDOM}=require('jsdom');

// Compare the actual summary owner with uncached full replacement. The pinned
// markup digest comes from the pre-O34 owner; DOM identity/work is checked apart
// from the generated strings, including edits made outside the application.
function summaryDOM({full=false,code=source}={}){
 const dom=new JSDOM('<section id="search"><form id="search-form"></form><div class="intro"></div><div id="applied-search"></div></section>'),document=dom.window.document,summary=document.querySelector('#applied-search');let parsed=0;
 const createElement=document.createElement.bind(document);document.createElement=tag=>{if(String(tag).toLowerCase()==='template')parsed++;return createElement(tag);};
 const ctx={document,Map,WeakMap,WeakSet,Array,JSON,Number,MutationObserver:dom.window.MutationObserver,esc,
  $:selector=>document.querySelector(selector),icon:name=>`<i data-icon="${name}"></i>`,
  state:{search:{origin:'Москва',country:'4',from:'2026-10-12',to:'2026-10-18',minNights:7,maxNights:7,adults:2,ages:[]},filters:{stars:[],meals:[],min:0,max:null}},
  appliedDestination:()=>ctx.state.search.country,destinationLabel:country=>({'4':'Турция','1':'Египет','9':'Направление <&"'}[country]||country),
  departureScopeLabel:()=> 'Вылет',departureScopeValue:s=>s.from+' — '+s.to,
  durationText:()=>ctx.state.search.minNights+'–'+ctx.state.search.maxNights+' ночей',guestsText:()=>ctx.state.search.adults+' взрослых · '+ctx.state.search.ages.join('/'),
  budgetLabel:f=>f.min+'–'+(f.max??'любой'),filterCount:()=>ctx.state.filters.stars.length+ctx.state.filters.meals.length+(ctx.state.filters.min?1:0)+(ctx.state.filters.max===null?0:1)};
 if(full)ctx.paintGeneratedRoots=(container,entries)=>{container.innerHTML=entries.map(entry=>entry.markup).join('');};
 vm.createContext(ctx);vm.runInContext((full?'':generatedRootOwner(code))+section(code,'function renderSummary(){','function editSearch(){'),ctx);
 const observer=new dom.window.MutationObserver(()=>{});observer.observe(summary,{childList:true});
 return {dom,ctx,summary,render:()=>{ctx.renderSummary();return observer.takeRecords();},parsed:()=>parsed,
  close:()=>{observer.disconnect();dom.window.close();}};
}
const subtreeElements=(records,key)=>records.reduce((sum,record)=>sum+[...record[key]].reduce((n,node)=>n+(node.nodeType===1?1+node.querySelectorAll('*').length:0),0),0);
const summaryBaseline=i>=0?fs.readFileSync(process.argv[i+1],'utf8'):source;
{
 const current=summaryDOM(),legacy=summaryDOM({full:true,code:summaryBaseline});
 try{
  current.render();legacy.render();assert.equal(current.summary.innerHTML,legacy.summary.innerHTML,'original summary markup');
  const roots=[...current.summary.children],focused=current.summary.querySelector('[data-action="edit-search"]');focused.focus();
  const elements=current.summary.querySelectorAll('*').length;let removed=0,inserted=0,oldRemoved=0,oldInserted=0;
  for(let round=0;round<10;round++){
   const records=current.render(),oldRecords=legacy.render();removed+=subtreeElements(records,'removedNodes');inserted+=subtreeElements(records,'addedNodes');oldRemoved+=subtreeElements(oldRecords,'removedNodes');oldInserted+=subtreeElements(oldRecords,'addedNodes');
  }
  assert.equal(oldRemoved,elements*10);assert.equal(oldInserted,elements*10);assert.equal(removed,0);assert.equal(inserted,0);assert.equal(current.parsed(),0);
  roots.forEach((node,index)=>assert.strictEqual(current.summary.children[index],node));
  assert.strictEqual(current.ctx.document.activeElement,focused,'unchanged summary action focus survives');
  current.ctx.state.filters.max=150000;legacy.ctx.state.filters.max=150000;const changed=current.render();legacy.render();
  assert.equal(changed.flatMap(record=>[...record.removedNodes]).length,1,'budget change replaces only extras');assert.equal(current.parsed(),1);assert.strictEqual(current.summary.firstElementChild,roots[0]);assert.strictEqual(current.ctx.document.activeElement,focused);
  assert.equal(current.summary.innerHTML,legacy.summary.innerHTML,'changed budget equals original full render');
  current.summary.querySelector('[data-action="meals"] strong').firstChild.data='stale';current.render();assert.equal(current.summary.innerHTML,legacy.summary.innerHTML,'character-data edit is repaired');
  current.summary.querySelector('[data-action="edit-search"]').setAttribute('aria-expanded','true');current.render();assert.equal(current.summary.innerHTML,legacy.summary.innerHTML,'live attribute edit is repaired');
  current.summary.appendChild(current.ctx.document.createElement('aside'));current.render();assert.equal(current.summary.innerHTML,legacy.summary.innerHTML,'extra root is removed');
  current.summary.querySelector('.applied-route').remove();current.render();assert.equal(current.summary.innerHTML,legacy.summary.innerHTML,'missing subtree is repaired');
  current.ctx.collapseSearch();assert.equal(current.ctx.document.querySelector('#search-form').hidden,true);assert.equal(current.ctx.document.querySelector('.intro').hidden,true);assert.equal(current.summary.hidden,false);assert(current.ctx.document.querySelector('#search').classList.contains('search-collapsed'));
  console.log(`PASS applied summary: ten unchanged refreshes remove/insert ${oldRemoved}→0/${oldInserted}→0 elements; budget change replaces one root; focus, collapse and live-DOM repair retained`);
 }finally{current.close();legacy.close();}
}
{
 const current=summaryDOM(),legacy=summaryDOM({full:true,code:summaryBaseline}),markup=[];let seed=34567;
 const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
 try{
  for(let round=0;round<100;round++){
   const state={search:{origin:['Москва','Гранд <&"','A\nB','Αθήνα'][round%4],country:['4','1','9'][round%3],from:'2026-10-'+String(1+round%27).padStart(2,'0'),to:'2026-11-'+String(1+round%27).padStart(2,'0'),minNights:1+Math.floor(random()*14),maxNights:15+Math.floor(random()*7),adults:1+Math.floor(random()*5),ages:[[],[0],[2,17],[0,8,17]][round%4]},filters:{stars:[[],[5],[5,3],[4,5]][round%4].slice(),meals:[[],['AI'],['BB','UAI'],['Питание <&"']][round%4].slice(),min:round%2?50000+round:0,max:round%3?150000+round:null}};
   current.ctx.state=state;legacy.ctx.state=structuredClone(state);const before=JSON.stringify(state);current.render();legacy.render();
   assert.equal(current.summary.innerHTML,legacy.summary.innerHTML,'changed search/filter summary equals uncached rendering');assert.equal(JSON.stringify(state),before,'summary preserves raw search/filter values and order');
   markup.push(legacy.summary.innerHTML);
  }
  const digest=crypto.createHash('sha256').update(JSON.stringify(markup)).digest('hex');
  assert.equal(digest,'10983625dddcf3d14ce59ed7cadb185f3039f97e119fe585827bbf621a8b2731','pre-O34 summary markup for 100 search/filter states');
  console.log('PASS summary parity: 100 original search/filter/escaping/party states; digest '+digest+'; raw input order preserved');
 }finally{current.close();legacy.close();}
}
// Identical keys in independent containers must never share a dirty/markup
// binding. Also prove that invalidation is needed, rather than testing a cache.
{
 const dom=new JSDOM('<div id="a"></div><div id="b"></div>'),document=dom.window.document,ctx={document,Map,WeakMap,WeakSet,MutationObserver:dom.window.MutationObserver};
 vm.createContext(ctx);vm.runInContext(generatedRootOwner(source),ctx);
 const a=document.querySelector('#a'),b=document.querySelector('#b'),entries=[{id:'same',markup:'<button id="same">Current</button>'}];
 try{
  ctx.paintGeneratedRoots(a,entries);ctx.paintGeneratedRoots(b,entries);const other=b.firstChild;a.firstChild.textContent='stale';ctx.paintGeneratedRoots(a,entries);ctx.paintGeneratedRoots(b,entries);
  assert.equal(a.textContent,'Current');assert.strictEqual(b.firstChild,other,'container bindings are independent');
 }finally{dom.window.close();}
 const mutated=summaryDOM({code:source.replace('previous&&!dirty.has(previous)&&known.get(previous)===markup?previous:parseGeneratedRoot(markup)','previous&&known.get(previous)===markup?previous:parseGeneratedRoot(markup)')});
 try{mutated.render();mutated.summary.querySelector('[data-action="budget"] strong').textContent='stale';mutated.render();assert.equal(mutated.summary.querySelector('[data-action="budget"] strong').textContent,'stale','missing mutation invalidation is detected');}finally{mutated.close();}
}

// Progressive calendar updates keep exact full-render markup, but unchanged
// date roots must survive provider/status refreshes. Live-only edits still
// invalidate the generated-markup binding and are repaired on the next pass.
function calendarDOM(code=source){
 const dom=new JSDOM('<p id="calendar-caption"></p><div id="price-strip"></div><button id="clear-date"></button>'),document=dom.window.document,strip=document.querySelector('#price-strip');
 let prices=Array.from({length:21},(_,i)=>100000+i*1000),parsed=0;
 const createElement=document.createElement.bind(document);document.createElement=tag=>{if(String(tag).toLowerCase()==='template')parsed++;return createElement(tag);};
 const addDays=(day,n)=>{const date=new Date(day+'T00:00:00Z');date.setUTCDate(date.getUTCDate()+n);return date.toISOString().slice(0,10);};
 const ctx={document,Map,WeakMap,WeakSet,Array,JSON,Number,Math,MutationObserver:dom.window.MutationObserver,
  state:{search:{from:'2026-10-01',to:'2026-10-21'},filters:{meals:['AI']},selectedDate:null},hotels:[],resultCalendar:{hotels:[],observations:[],phase:'complete'},
  $:selector=>document.querySelector(selector),addDays,calendarMinimums:days=>days.map((_,i)=>prices[i]??null),calendarSourceLabel:()=> 'Найденные цены',
  calendarScope:()=>({destination:'Турция <&',filters:['meal']}),guestsText:()=> '2 взрослых',durationText:()=> '7–10 ночей',dateLong:day=>'long:'+day,dateText:day=>'date:'+day,money:value=>value+' ₽',
  loadResultCalendar:()=>{},refreshEmptyCalendarContext:()=>{}};
 vm.createContext(ctx);vm.runInContext(owner(code,'calendar'),ctx);
 const observer=new dom.window.MutationObserver(()=>{});observer.observe(strip,{childList:true});
 return {dom,ctx,strip,render:()=>{ctx.renderCalendarStrip();return observer.takeRecords();},parsed:()=>parsed,
  setPrice:(index,value)=>{prices[index]=value;},setPrices:value=>{prices=value.slice();},markup:()=>vm.runInContext('calendarStripHTML(resultCalendarModel())',ctx),
  close:()=>{observer.disconnect();dom.window.close();}};
}
const calendarRoots=(records,key)=>records.reduce((sum,record)=>sum+[...record[key]].filter(node=>node.matches?.('.date-price')).length,0);
{
 const current=calendarDOM(),legacy=calendarDOM(source.replace('paintCalendarStrip(strip,calendarStripEntries(model));','strip.innerHTML=calendarStripHTML(model);'));
 try{
  current.render();legacy.render();let removed=0,inserted=0,legacyRemoved=0,legacyInserted=0;
  const original=[...current.strip.children],focused=original[5];focused.focus();
  for(let i=0;i<10;i++){
   const records=current.render(),legacyRecords=legacy.render();
   removed+=calendarRoots(records,'removedNodes');inserted+=calendarRoots(records,'addedNodes');legacyRemoved+=calendarRoots(legacyRecords,'removedNodes');legacyInserted+=calendarRoots(legacyRecords,'addedNodes');
  }
  assert.equal(legacyRemoved,210,'legacy ten-refresh baseline removes every date root');assert.equal(legacyInserted,210,'legacy ten-refresh baseline inserts every date root');
  assert.equal(removed,0,'ten unchanged refreshes remove no date roots');assert.equal(inserted,0,'ten unchanged refreshes insert no date roots');assert.equal(current.parsed(),0,'unchanged refreshes parse no date roots');
  original.forEach((node,index)=>assert.strictEqual(current.strip.children[index],node));assert.strictEqual(current.ctx.document.activeElement,focused,'unchanged focused date survives');
  current.setPrice(10,110500);legacy.setPrice(10,110500);const changed=current.render();legacy.render();
  assert.equal(calendarRoots(changed,'removedNodes'),1,'one non-extreme price change removes one date');assert.equal(calendarRoots(changed,'addedNodes'),1,'one non-extreme price change inserts one date');assert.equal(current.parsed(),1,'one non-extreme price change parses one date');
  assert.equal(current.strip.innerHTML,legacy.strip.innerHTML,'changed-price markup matches a full render');
  current.ctx.state.selectedDate='2026-10-06';legacy.ctx.state.selectedDate='2026-10-06';current.render();legacy.render();
  assert.equal(current.strip.innerHTML,legacy.strip.innerHTML,'selected date markup/aria matches a full render');assert.equal(current.strip.querySelector('[data-date="2026-10-06"]').getAttribute('aria-pressed'),'true');
  current.strip.querySelector('[data-date="2026-10-12"] strong').textContent='stale';current.render();
  assert.notEqual(current.strip.querySelector('[data-date="2026-10-12"] strong').textContent,'stale','live-only date mutation is repaired');
  console.log('PASS progressive calendar: 10 unchanged 21-day updates remove/insert 210→0 roots; one non-extreme price change parses/replaces 1; exact markup, focus, selection and stale-DOM repair retained');
 }finally{current.close();legacy.close();}
}
{
 const current=calendarDOM(),reference=new JSDOM('<div id="price-strip"></div>'),expected=reference.window.document.querySelector('#price-strip');let seed=97531;
 const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
 try{
  for(let round=0;round<90;round++){
   const length=1+Math.floor(random()*31),prices=Array.from({length},()=>random()<.2?null:Math.floor(random()*20)*1000+.5);
   current.ctx.state.search.to=new Date(Date.parse('2026-10-01T00:00:00Z')+(length-1)*86400000).toISOString().slice(0,10);current.ctx.state.selectedDate=random()<.5?null:'2026-10-'+String(1+Math.floor(random()*length)).padStart(2,'0');current.ctx.resultCalendar.phase=['loading','error','partial','complete'][round%4];current.setPrices(prices);
   const before=JSON.stringify(prices);current.render();expected.innerHTML=current.markup();assert.equal(current.strip.innerHTML,expected.innerHTML,'progressive calendar DOM equals uncached full markup');assert.equal(JSON.stringify(prices),before,'calendar rendering preserves price inputs');
  }
 }finally{current.close();reference.window.close();}
 console.log('PASS calendar DOM parity: 90 ranges/price-null/minimum/selection/phase sequences; raw price order unchanged; supplier/lead HTTP 0');
}

// Progressive updates must keep current markup while retaining unchanged live
// articles and photos. Compare against the old full-innerHTML DOM independently.
function cardDOM(code=source){
 const dom=new JSDOM('<main id="results"><div id="cards"></div></main>'),document=dom.window.document,cards=document.querySelector('#cards');let parsed=0;
 const createElement=document.createElement.bind(document);document.createElement=tag=>{if(String(tag).toLowerCase()==='template')parsed++;return createElement(tag);};
 const ctx={document,Map,WeakMap,WeakSet,Array,JSON,Number,Math,CSS:{escape:s=>String(s)},MutationObserver:dom.window.MutationObserver,scrollY:400,
  window:{scrollTo:()=>{}},$:s=>document.querySelector(s),data:{scenario:'live'},
  state:{search:{country:'4'},filters:{meals:[]},selectedDate:null,sort:'price',onlyFavorites:false},
  cardHTML:({hotel:h,offers})=>`<article class="hotel-card" id="hotel-${h.id}" data-hotel-id="${h.id}"><img src="/photo-${h.photo||0}.jpg" alt="${esc(h.name)}"><h3>${esc(h.name)}</h3><strong>${offers[0].total}</strong><button data-action="offer" data-key="${offers[0].key}">Тур</button></article>`,
  emptyResultsHTML:()=>'<p class="empty">Нет подходящих туров</p>'};
 dom.window.HTMLElement.prototype.getClientRects=function(){return [{}];};
 dom.window.HTMLElement.prototype.getBoundingClientRect=function(){return {top:100};};
 vm.createContext(ctx);vm.runInContext(owner(code,'results')+'\n'+section(code,'function focusReference(','function capturePageReturn('),ctx);
 const observer=new dom.window.MutationObserver(()=>{});observer.observe(cards,{childList:true});
 return {dom,ctx,cards,render:items=>{ctx.renderResultCards(items);return observer.takeRecords();},parsed:()=>parsed,
  more:()=>vm.runInContext('renderedCardLimit+=24;',ctx),close:()=>{observer.disconnect();dom.window.close();}};
}
const entry=(id,total=100000+id)=>({hotel:{id,name:'Hotel <& '+id,photo:id%3},offers:[{key:'tour-'+id,total}]});
const removedArticles=records=>records.reduce((sum,r)=>sum+[...r.removedNodes].filter(n=>n.matches?.('.hotel-card')).length,0);
{
 const h=cardDOM(),items=Array.from({length:55},(_,i)=>entry(i+1)),before=JSON.stringify(items);
 try{
  h.render(items);const original=[...h.cards.querySelectorAll('.hotel-card')],photo=original[3].querySelector('img'),focus=original[3].querySelector('button');focus.focus();
  assert.equal(original.length,24);assert.equal(h.cards.querySelector('[data-action="more-cards"] span').textContent,'Показано 24 из 55');
  for(let i=0;i<10;i++)assert.equal(removedArticles(h.render(items)),0,'unchanged provider/status refresh removes no articles');
  assert.equal(h.parsed(),0,'ten unchanged 24-card refreshes parse zero card/load-more roots');
  original.forEach(n=>assert.strictEqual(h.cards.querySelector('#'+n.id),n));assert.strictEqual(original[3].querySelector('img'),photo);assert.strictEqual(h.ctx.document.activeElement,focus);
  const changed=items.map((e,i)=>i===3?{...e,offers:[{...e.offers[0],total:133500.5}]}:e);
  assert.equal(removedArticles(h.render(changed)),1,'one changed current price replaces only its article');
  assert.equal(h.parsed(),1,'one price change parses exactly its changed article');
  assert.equal(h.cards.querySelector('#hotel-4 strong').textContent,'133500.5');assert.notStrictEqual(h.cards.querySelector('#hotel-4'),original[3]);assert.equal(h.ctx.document.activeElement.dataset.key,'tour-4','focused action restored on replaced article');
  original.filter((_,i)=>i!==3).forEach(n=>assert.strictEqual(h.cards.querySelector('#'+n.id),n));
  const current=[...h.cards.querySelectorAll('.hotel-card')];h.more();assert.equal(removedArticles(h.render(changed)),0,'load more retains the first 24 articles');assert.equal(h.cards.querySelectorAll('.hotel-card').length,48);current.forEach(n=>assert.strictEqual(h.cards.querySelector('#'+n.id),n));
  const reordered=[...changed].reverse();h.render(reordered);assert.deepEqual([...h.cards.querySelectorAll('.hotel-card')].map(n=>n.dataset.hotelId),reordered.slice(0,48).map(e=>String(e.hotel.id)),'sort follows current result order');
  h.ctx.state.filters.meals=['AI'];const reset=h.render(reordered);assert.equal(h.cards.querySelectorAll('.hotel-card').length,24,'changed filter scope resets the existing limit');assert.equal(removedArticles(reset),48,'changed search/filter scope retains the original full replacement path');
  h.render([]);assert.equal(h.cards.innerHTML,h.ctx.emptyResultsHTML());h.render(items.slice(0,1));assert.equal(h.cards.querySelectorAll('.hotel-card').length,1);assert.equal(h.cards.querySelector('[data-action="more-cards"]'),null);
  assert.equal(JSON.stringify(items),before,'render preserves raw hotel/offer inputs');
  console.log('PASS progressive cards: 10 unchanged 24-card updates parse 250→0 roots and remove 240→0 articles; one price update parses/replaces 1; photo/focus/order/load-more/filter reset retained; no wall-clock or whole-page timing claim');
 }finally{h.close();}
}
{
 const h=cardDOM(),reference=new JSDOM('<div id="cards"></div>'),expected=reference.window.document.querySelector('#cards');let seed=12345;
 const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
 try{
  for(let i=0;i<90;i++){
   const items=Array.from({length:Math.floor(random()*35)},()=>{const id=Math.floor(random()*18)+1,e=entry(id,Math.floor(random()*10)*100+.5);e.hotel.name=['Hotel <&','Гранд "Отель"','A\nB'][i%3]+id;e.hotel.photo=Math.floor(random()*4);return e;});
   const html=items.length?items.slice(0,24).map(h.ctx.cardHTML).join('')+(items.length>24?`<button type="button" class="secondary load-more-cards" data-action="more-cards">Показать ещё ${Math.min(24,items.length-24)} отеля <span>Показано 24 из ${items.length}</span></button>`:''):h.ctx.emptyResultsHTML();
   expected.innerHTML=html;h.render(items);assert.equal(h.cards.innerHTML,expected.innerHTML,'actual DOM equals uncached full rendering, including duplicate IDs/Unicode/escaping/empty states');
  }
  // Live-only DOM edits are repaired by exact equality, never trusted as a cache.
  const items=[entry(1),entry(2)];h.render(items);h.cards.querySelector('#hotel-1 strong').textContent='stale price';h.cards.querySelector('#hotel-2 img').src='/stale.jpg';h.render(items);
  assert.equal(h.cards.querySelector('#hotel-1 strong').textContent,'100001');assert.equal(h.cards.querySelector('#hotel-2 img').getAttribute('src'),'/photo-2.jpg');
 }finally{h.close();reference.window.close();}
 const mutated=cardDOM(source.replace('previous&&!dirty.has(previous)&&known.get(previous)===markup?previous:parseGeneratedRoot(markup)','previous||parseGeneratedRoot(markup)'));
 try{mutated.render([entry(1,100)]);mutated.render([entry(1,200)]);assert.notEqual(mutated.cards.querySelector('strong').textContent,'200','stale-node-reuse mutation is detected');}finally{mutated.close();}
 console.log('PASS actual DOM parity: 90 progressive sequences; duplicate IDs, escaping, empty/repopulation, stale DOM edits and equality mutation covered; supplier/lead HTTP 0');
}

// Run the actual ranking owner against the previous comparator independently.
// Sort keys may be reused within one call, never across changing result data.
const popularityCode=fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/hotel-popularity-v1.js'),'utf8');
function rankingOwner(code=source){
 const work={score:0,rank:0,offers:0};
 const ctx={window:{AnyTourTopHotelLegacyIds:[5,2,9,5,0,'bad',7]},hotels:[],state:{sort:'recommended'},work,
  hotelOffers:h=>{work.offers++;return h.offers||[];}};
 vm.createContext(ctx);vm.runInContext(popularityCode,ctx);ctx.popularity=ctx.window.AnyTourHotelPopularityV1;
 vm.runInContext(code.match(/^const ratingValue=[^\n]+/m)[0]+'\n'+section(code,'function recommendedHotelScore(','function calendarMinimums('),ctx);
 vm.runInContext('const scoreOwner=recommendedHotelScore,rankOwner=recommendedHotelRank;recommendedHotelScore=h=>{work.score++;return scoreOwner(h);};recommendedHotelRank=h=>{work.rank++;return rankOwner(h);};',ctx);
 return {ctx,work,run:()=>{work.score=work.rank=work.offers=0;return ctx.results();}};
}
function previousRanking(hotels,sort,popularity){
 const rating=h=>Number.isFinite(h.rating)&&h.rating>0&&h.rating<=5?h.rating:null;
 const score=h=>(rating(h)??0)+(h.beach!==null&&h.beach<=150?.2:0)+(popularity?.boost(h)||0);
 const rank=h=>{const value=popularity?.rank(h);return Number.isInteger(value)?value:Number.MAX_SAFE_INTEGER;};
 return hotels.map(h=>({hotel:h,offers:h.offers||[]})).filter(r=>r.offers.length).sort((a,b)=>sort==='price'?a.offers[0].total-b.offers[0].total:sort==='rating'?(rating(b.hotel)??0)-(rating(a.hotel)??0):score(b.hotel)-score(a.hotel)||rank(a.hotel)-rank(b.hotel)||a.offers[0].total-b.offers[0].total);
}
const ranking=rankingOwner(),rankingRecords=[];
function checkRanking(label,rows,sort){
 const before=structuredClone(rows);ranking.ctx.hotels=rows;ranking.ctx.state.sort=sort;
 const actual=ranking.run(),expected=previousRanking(rows,sort,ranking.ctx.popularity);
 assert.equal(actual.length,expected.length,label+' membership');
 actual.forEach((row,i)=>{assert.strictEqual(row.hotel,expected[i].hotel,label+' raw hotel/order');assert.strictEqual(row.offers,expected[i].offers,label+' raw offer array');});
 assert.deepEqual(rows,before,label+' never sorts or decorates source objects');
 assert.equal(ranking.work.offers,rows.length,label+' one current inventory read per hotel');
 if(!process.argv.includes('--capture')){
  const ranked=sort!=='price'&&sort!=='rating'&&actual.length>1;
  assert.equal(ranking.work.score,ranked?actual.length:0,label+' bounded score work');
  assert.equal(ranking.work.rank,ranked?actual.length:0,label+' bounded rank work');
 }
 rankingRecords.push([label,actual.map(r=>[r.hotel.id,r.offers[0].key])]);return actual;
}
const sortModes=['recommended','price','rating','unknown',''];
let rankingSeed=24681357;
const rankingRandom=()=>((rankingSeed=(rankingSeed*1664525+1013904223)>>>0)/4294967296);
function rankingRows(n){
 const rows=Array.from({length:n},(_,i)=>({id:i%11, rating:[null,NaN,-1,0,4.5,5,6,Infinity][i%8],beach:[null,0,150,151,undefined][i%5],
  legacyIds:[[],[2,5,'9',0,-1],['bad'],[7],null,[99],[9,9]][i%7],
  offers:i%9===0?[]:[{key:'tour-'+i,total:[100,.5,100,200,NaN,Infinity][i%6]}]}));
 for(let i=rows.length-1;i>0;i--){const j=Math.floor(rankingRandom()*(i+1));[rows[i],rows[j]]=[rows[j],rows[i]];}return rows;
}
for(let round=0;round<12;round++)for(const n of [0,1,2,17,64])for(const sort of sortModes)checkRanking(round+':'+n+':'+sort,rankingRows(n),sort);
const progressive=rankingRows(35);progressive.push(progressive[3]);
for(let i=0;i<20;i++){
 const row=progressive[i%progressive.length];row.rating=i%2?5:1;row.legacyIds=[i%2?5:2];row.offers=[{key:'current-'+i,total:i*100+.5}];
 if(i%4===0)progressive.reverse();for(const sort of sortModes)checkRanking('progressive:'+i+':'+sort,progressive,sort);
}
const tied=Array.from({length:8},(_,i)=>({id:i,rating:4,beach:null,legacyIds:[],offers:[{key:'tie-'+i,total:100}]}));
assert.deepEqual(Array.from(checkRanking('stable ties',tied,'recommended'),r=>r.hotel.id),tied.map(h=>h.id));
const workRows=rankingRows(500).map((h,i)=>({...h,offers:[{key:'work-'+i,total:i%5*100+100}]}));
const oldRanking=rankingOwner(source.replace(section(source,'function results(){','function calendarMinimums('),'function results(){return hotels.map(h=>({hotel:h,offers:hotelOffers(h)})).filter(r=>r.offers.length).sort((a,b)=>state.sort===\'price\'?a.offers[0].total-b.offers[0].total:state.sort===\'rating\'?(ratingValue(b.hotel)??0)-(ratingValue(a.hotel)??0):recommendedHotelScore(b.hotel)-recommendedHotelScore(a.hotel)||recommendedHotelRank(a.hotel)-recommendedHotelRank(b.hotel)||a.offers[0].total-b.offers[0].total);}\n'));
oldRanking.ctx.hotels=workRows;oldRanking.run();checkRanking('500-hotel work',workRows,'recommended');
const rankingDigest=crypto.createHash('sha256').update(JSON.stringify(rankingRecords)).digest('hex');
assert.equal(rankingDigest,'0caebd4a34f08845d2ac70a75d763440b218c0e74c18f7cca295fbf33193fd92','pre-optimization ranking digest');
assert(oldRanking.work.score>ranking.work.score*10&&oldRanking.work.rank>ranking.work.rank*8,'bounded recommended-ranking work');
const rankMutation=rankingOwner(source.replace('a.rank-b.rank','b.rank-a.rank'));
rankMutation.ctx.hotels=[2,5].map(id=>({id,rating:4,beach:null,legacyIds:[id],offers:[{key:'rank-'+id,total:100}]}));
assert.notDeepEqual(Array.from(rankMutation.run(),r=>r.hotel.id),previousRanking(rankMutation.ctx.hotels,'recommended',rankMutation.ctx.popularity).map(r=>r.hotel.id),'popularity tie-break mutation detected');
console.log('PASS actual result ranking: '+rankingRecords.length+' independent order/reference/progressive observations; digest '+rankingDigest+'; score work '+oldRanking.work.score+' → '+ranking.work.score+', rank work '+oldRanking.work.rank+' → '+ranking.work.rank+'; supplier/lead HTTP 0');
require('./search3-visual-rating-render-inventory.cjs');
