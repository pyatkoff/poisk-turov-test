// Final reference tap selection replaces only night draft/actions;162 cases retain
// disabled/state-order/ownership mutations and add named endpoint/invalid-input checks.
// Characterize the real delegated event owner without starting the application,
// supplier transport or lead delivery. Pinned observations come from app blob
// db413a559e1789057e82293d4cb4c1a7f7d89c8e before structural extraction.
// The retained pin was recomputed from release 7a4e93b before removal, excluding
// only eight comparison IDs and the comparison-focus change event.
// O51 keeps those cases and projects only the discarded inline expansion field,
// with direct before/after parity against the fresh 6f6d4b9585 source.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');
const appPath = path.resolve(__dirname, '../v2/visual-search/app.js');
const source = fs.readFileSync(appPath, 'utf8');
function eventOwner(source) {
  const start = source.indexOf("searchLifecycle.bind();") + "searchLifecycle.bind();".length;
  // The original dispatcher ended before comparison media listeners; after
  // their retirement the next live owner is keyboard handling. Keep the old
  // delimiter for --compare so both versions execute the same three listeners.
  const legacyEnd = source.indexOf("matchMedia('(max-width:760px)')", start);
  const keyboardEnd = source.indexOf("document.addEventListener('keydown'", start);
  const end = legacyEnd >= start && legacyEnd < keyboardEnd ? legacyEnd : keyboardEnd;
  assert(start > 0 && end > start, 'real event owner boundaries');
  return source.slice(start, end);
}
function characterize(source, scenario) {
  const code = eventOwner(source), trace = [], checkpoints = [], listeners = {}, nodes = new Map(), microtasks = [];
  const copy = value => JSON.parse(JSON.stringify(value));
  const filters = () => ({stars:[3], meals:['AI'], q:'old', min:100, max:1000, resorts:['old']});
  function node(selector) {
    if (nodes.has(selector)) return nodes.get(selector);
    const n = {id:selector, value:'query', hidden:false, checked:false, textContent:'', scrollTop:37,
      dataset:{facetOptions:'resorts'}, options:[{textContent:'Цена'}], selectedIndex:0,
      focus:options=>trace.push(['focus',selector,options||null]),
      blur:()=>trace.push(['blur',selector]),
      scrollIntoView:options=>trace.push(['scrollIntoView',selector,options]),
      removeAttribute:key=>trace.push(['removeAttribute',selector,key]),
      setAttribute:(key,value)=>trace.push(['setAttribute',selector,key,value]),
      getAttribute:()=> 'false',
      hasAttribute:()=>false,
      closest:s=>s==='#filter-panel'?(scenario.panel?node('#filter-panel'):null):node(s),
      querySelector:s=>node(s), querySelectorAll:s=>[node(s+'[0]'),node(s+'[1]')],
      getBoundingClientRect:()=>({top:100}), offsetHeight:10,
      scrollTo:options=>trace.push(['scrollTo',selector,options]),
      dispatchEvent:e=>{trace.push(['dispatch',selector,e.type]);n.id='origin';listeners[e.type]( {target:n} );}
    };
    nodes.set(selector,n);return n;
  }
  const state = {filters:filters(),sort:'recommended',onlyFavorites:false,selectedDate:'2026-10-15',openHotel:7,hasSearched:false,search:{country:'Turkey',from:'2026-10-14',to:'2026-10-20'}};
  const model = {filters:filters(),onlyFavorites:false,selectedDate:null};
  const choice = {model:{filters:filters(),onlyFavorites:true,selectedDate:'2026-10-18'}};
  const ctx = {console, Math, Number, String, Array, Set, Map, JSON, Date, structuredClone,
    Event:class Event {constructor(type,options){this.type=type;this.options=options;}},
    $:node, $$:s=>[node(s+'[0]'),node(s+'[1]')],
    document:{addEventListener:(type,fn)=>{assert(!listeners[type],'one registration per type');listeners[type]=fn;}},
    queueMicrotask:fn=>{trace.push(['queueMicrotask']);microtasks.push(fn);},
    state,draft:{origin:'Moscow',country:'Turkey',from:'2026-10-14',to:'2026-10-20',adults:2,ages:[]},
    draftDestination:null,destinationChoice:{country:'Turkey',resorts:['old'],hotelId:7},
    destinationResortsExpanded:false,destinationResolvedQuery:'old',destinationHotelLimit:4,destinationHotelPageSize:4,
    flightDraft:{id:'old'},offerView:{pair:[1,2],activeVariant:1,mode:'list',id:7,open:[],limits:{}},
    selectedOffer:{hotelId:7},compareView:{pair:[7,8]},filterDraft:scenario.filterDraft?model:null,
    guestDraft:{adults:2,ages:[5,null]},mealDraft:['AI'],facetQueries:new Map([['resorts','query']]),
    drawerSuggestions:[choice],emptySuggestions:[choice],filterBudgetEdit:{},actionTrigger:null,
    searchResponse:{pending:!!scenario.pending,canContinue:true,exactRefresh:true},
    dateDraft:{from:'2026-10-14',to:'2026-10-20',phase:0},
    dateContext:{source:'results',search:{from:'2026-10-14',to:'2026-10-20'}},
    calendarMonth:'2026-10-01',startDay:'2026-10-01',endDay:'2026-12-31',
    nightsDraft:{min:7,max:10,mode:'range',bound:'min'},modalHistory:[],modalType:'',optionalShortlistEnabled:false,
    editingFilterModel:()=>model,defaultFilters:filters,
    appliedFilterModel:()=>({filters:state.filters,onlyFavorites:state.onlyFavorites,selectedDate:state.selectedDate}),
    data:{catalog:{departures:[{name:'Moscow'},{name:'Kazan'}]},text:x=>x.name,continueSearch:()=>trace.push(['continueSearch'])},
    searchLifecycle:{requestSubmit:()=>trace.push(['requestSubmit'])},
    getStored:()=>['Moscow','Kazan'],recentDestinations:()=>[{country:'Egypt',resorts:[],hotelId:0}],
    destinationHotel:()=>({id:7,country:'Turkey'}),normalizeSearch:s=>s.trim().toLowerCase(),
    renderMoreDestinationHotels:()=>{trace.push(['renderMoreDestinationHotels']);return 4;},
    renderOfferGroup:key=>{trace.push(['renderOfferGroup',key]);return scenario.groupHandled===true;},byId:id=>node('#'+id),
    dateObj:s=>new Date(s+'T00:00:00Z'),iso:d=>d.toISOString().slice(0,10),dateRangeError:()=>false,
    readBudget:()=>({valid:!scenario.invalidBudget,min:200,max:2000,invalidMin:true}),scrollBehavior:()=> 'instant'
  };
  // External collaborators are observable boundaries; the actual handlers and
  // extracted helpers execute unchanged. No collaborator issues an HTTP request.
  const keywords = new Set(['if','switch','for','while','catch','function','Number','String','Math','Date','Set','Map','Event','structuredClone']);
  for (const [,name] of code.matchAll(/(?<![.\w])([A-Za-z_]\w*)\s*\(/g)) {
    if (!keywords.has(name) && !(name in ctx)) ctx[name]=(...args)=>{trace.push([name,...args.map(x=>x&&typeof x==='object'?(x.focus?'DOM:'+x.id:copy(x)):x)]);checkpoints.push({name,state:copy(state),model:copy(model),draft:copy(ctx.draft),dateDraft:copy(ctx.dateDraft),guestDraft:copy(ctx.guestDraft)});};
  }
  ctx.destinationIds=f=>f?.hotelIds?.length?f.hotelIds:f?.hotelId?[f.hotelId]:[];
 ctx.agesNeedReview=false;ctx.formFiltersDraft=null;ctx.starsDraft=[];ctx.ageChoice=null;ctx.departureChoice='Москва';ctx.destinationCountryList=false;ctx.destinationPending=null;ctx.destinationMatchItems=[];ctx.setDestinationIds=(f,ids)=>{f.hotelIds=ids;f.hotelId=ids.length===1?ids[0]:0;};
 ctx.pickerFilters=()=>ctx.state.filters;ctx.finishFilterPicker=ctx.applyQuickFilters;
 vm.createContext(ctx);vm.runInContext(code,ctx);
  const t=node('target');Object.assign(t,{id:'',name:'',value:'new',max:'2000',checked:true,dataset:{}},scenario.target||{});
  if(scenario.type==='click') {
    t.dataset={action:scenario.action,id:'7',value:'Kazan',date:'2026-10-18',key:'key',source:'drawer',index:'0',...scenario.dataset};
    t.disabled=!!scenario.disabled;
    t.closest=s=>s==='[data-action]'?(scenario.noAction?null:t):s==='#filter-panel'?(scenario.panel?node('#filter-panel'):null):node(s);
  }
  if(scenario.meal)t.hasAttribute=k=>k==='data-meal-choice';
  if(scenario.setup)scenario.setup(ctx,t,node);
  listeners[scenario.type]({target:t});
  const triggerBeforeDrain=ctx.actionTrigger===t;
  if(scenario.replaceTrigger)ctx.actionTrigger=node('new-trigger');
  microtasks.forEach(fn=>fn());
  const dom=[...nodes].map(([selector,n])=>[selector,{value:n.value,checked:n.checked,hidden:n.hidden,textContent:n.textContent,scrollTop:n.scrollTop}]);
  return copy({trace,checkpoints,state,model,draft:ctx.draft,draftDestination:ctx.draftDestination,destinationChoice:ctx.destinationChoice,
    destinationResortsExpanded:ctx.destinationResortsExpanded,destinationResolvedQuery:ctx.destinationResolvedQuery,
    flightDraft:ctx.flightDraft,offerView:ctx.offerView,compareView:ctx.compareView,filterDraft:ctx.filterDraft,
    retainedView:ctx.retainedView,guestDraft:ctx.guestDraft,mealDraft:ctx.mealDraft,dateDraft:ctx.dateDraft,nightsDraft:ctx.nightsDraft,
    filterBudgetEdit:ctx.filterBudgetEdit,calendarMonth:ctx.calendarMonth,facetQueries:[...ctx.facetQueries],
    triggerBeforeDrain,triggerAfterDrain:ctx.actionTrigger===null?'null':ctx.actionTrigger===t?'target':'other',
    registrations:Object.keys(listeners),dom});
}
const scenarios=[];
const add=(name,s)=>scenarios.push({name,...s});
for(const id of ['origin','sort','mobile-sort','filter-section-jump','min-price','max-price','hotel-room-meal','offer-departure','offer-flight','offer-room','offer-meal','offer-sort'])add('change:'+id,{type:'change',target:{id,value:['sort','mobile-sort'].includes(id)?'price':'2',dataset:{id:'7'}}});
for(const name of ['andromeda-outbound','andromeda-return','flight-pair','anex-package-choice'])add('change:'+name,{type:'change',target:{name,value:'2'}});
for(const checked of [true,false])for(const value of ['old','new'])add('facet:'+checked+':'+value,{type:'change',target:{checked,value,dataset:{filter:'resorts'}}});
add('boolean filter',{type:'change',target:{dataset:{filterBool:'family'},checked:false}});
add('invalid sort stops later attributes',{type:'change',target:{id:'sort',value:'bad',dataset:{filterBool:'family',childAge:'1'}},meal:true});
add('jump stops later attributes',{type:'change',target:{id:'filter-section-jump',dataset:{filterBool:'family',childAge:'1'}},meal:true});
add('multi-purpose origin facet',{type:'change',target:{id:'origin',value:'new',dataset:{filter:'resorts',filterBool:'family',childAge:'1'}},meal:true});
for(const value of ['', '0','17'])add('child:'+value,{type:'change',target:{value,dataset:{childAge:'1'}}});
for(const value of ['', 'AI','BB'])for(const checked of [true,false])add('meal:'+value+':'+checked,{type:'change',meal:true,target:{value,checked}});
for(const id of ['min-price','max-price','budget-min','budget-max','meal-query','destination-query','hotel-query','price-range','unknown'])add('input:'+id,{type:'input',target:{id,value:id==='price-range'?'500':'text'}});
add('empty hotel query',{type:'input',target:{id:'hotel-query',value:''}});
for(const value of ['50','2000'])add('price-range:'+value,{type:'input',target:{id:'price-range',value}});
const actions=['departure','choose-departure','destination','retry-destination','destination-country','retry-resorts','destination-all','clear-destination-query','destination-remove','destination-more-hotels','toggle-destination-resorts','destination-resort','destination-hotel','destination-recent','apply-destination','retry-hotel-restore','retry-countries','dates','meals','budget','category-filters','toggle-filter-section','clear-facet-query','remove-facet-choice','clear-hotel-query','clear-meal-query','apply-meals','budget-preset','budget-adjust-max','apply-budget','any-stars','nights','guests','calendar','filters','close-filters','apply-filters','review-filter-recovery','top','edit-search','cancel-search-edit','reset','all-hotels','star','preset','remove-filter','remove-draft-filter','recover-filters','clear-date','select-date','month-prev','month-next','day-pick','apply-dates','adults-minus','adults-plus','children-minus','children-plus','remove-child','apply-guests','night-pick','night-preset','apply-nights','retry-search','continue-search','stop-search','choose-flight','apply-flight','selected-tour','save-tour-for-later','unknown'];
for(const action of actions)add('click:'+action,{type:'click',action,filterDraft:true,dataset:{value:['recover-filters','destination-recent'].includes(action)?'0':action==='star'?'4':action==='night-pick'||action==='night-preset'?'9':'Kazan'}});
add('unmatched click',{type:'click',action:'dates',noAction:true});
add('disabled click',{type:'click',action:'dates',disabled:true});
add('trigger replaced before microtask',{type:'click',action:'dates',replaceTrigger:true});
add('invalid budget return',{type:'click',action:'apply-budget',invalidBudget:true});
add('departure rejected',{type:'click',action:'choose-departure',dataset:{value:'Unknown'}});
add('pending search',{type:'click',action:'retry-search',pending:true});
add('pending continue',{type:'click',action:'continue-search',pending:true});
add('valid guest apply',{type:'click',action:'apply-guests',setup:c=>c.guestDraft.ages=[0,17]});
add('invalid date return',{type:'click',action:'apply-dates',setup:c=>c.dateDraft.from=''});
add('changed results date submits',{type:'click',action:'apply-dates',setup:c=>c.dateDraft.to='2026-10-21'});
add('date second boundary',{type:'click',action:'day-pick',dataset:{date:'2026-10-12'},setup:c=>c.dateDraft.phase=1});
add('night second boundary',{type:'click',action:'night-pick',dataset:{value:'10'},setup:c=>c.nightsDraft.bound='max'});
add('night too wide',{type:'click',action:'night-pick',dataset:{value:'28'},setup:c=>c.nightsDraft.bound='max'});
add('night exact mode',{type:'click',action:'night-mode',dataset:{value:'exact'}});
add('night range mode',{type:'click',action:'night-mode',dataset:{value:'range'},setup:c=>c.nightsDraft={min:7,max:7,mode:'exact',bound:'min'}});
add('night lower boundary',{type:'click',action:'night-bound',dataset:{value:'min'},setup:c=>c.nightsDraft.bound='max'});
add('night upper boundary',{type:'click',action:'night-bound',dataset:{value:'max'}});
add('night exact pick',{type:'click',action:'night-pick',dataset:{value:'21'},setup:c=>c.nightsDraft={min:7,max:7,mode:'exact',bound:'min'}});
add('night valid preset',{type:'click',action:'night-preset',dataset:{value:'14'}});
add('night invalid number',{type:'click',action:'night-pick',dataset:{value:'29'}});
add('night invalid mode',{type:'click',action:'night-mode',dataset:{value:'unknown'}});
add('night invalid boundary',{type:'click',action:'night-bound',dataset:{value:'unknown'}});
add('filter panel reset',{type:'click',action:'reset',panel:true,filterDraft:true});
add('recover applied filters',{type:'click',action:'recover-filters',dataset:{source:'results',value:'0'}});
add('overlapping flight origin change',{type:'change',target:{name:'andromeda-outbound',id:'origin',value:'Kazan'}});
add('no flight draft',{type:'change',target:{name:'flight-pair'},setup:c=>c.flightDraft=null});
for(const pending of [false,true])add('retained ANEX choice:'+pending,{type:'change',target:{name:'anex-package-choice',value:'choice'},setup:c=>{c.retainedView={type:'anex-quote',pending,error:null,result:{choices:[{choiceRef:'choice'}]}};c.retainedProviderView=()=>c.retainedView;}});
add('destination resort toggles off',{type:'click',action:'destination-resort',dataset:{country:'Turkey',value:'old'}});
add('destination resort toggles on',{type:'click',action:'destination-resort',dataset:{country:'Turkey',value:'new'}});
add('destination resort removed',{type:'click',action:'destination-remove',dataset:{id:'',value:'old'}});
add('missing destination hotel',{type:'click',action:'destination-hotel',setup:c=>c.destinationHotel=()=>null});
add('destination already searched',{type:'click',action:'apply-destination',setup:c=>c.state.hasSearched=true});
add('departure unchanged',{type:'click',action:'choose-departure',dataset:{value:'Moscow'}});
add('first new hotel focuses',{type:'click',action:'destination-more-hotels',setup:(c,t,n)=>c.$$=s=>Array.from({length:5},(_,i)=>n(s+'['+i+']'))});
add('second date rejected',{type:'click',action:'day-pick',setup:c=>{c.dateDraft.phase=1;c.dateRangeError=()=>true;}});
add('draft date applies without submit',{type:'click',action:'apply-dates',setup:c=>{c.dateContext.source='draft';c.dateDraft.to='2026-10-21';}});
add('guest marks missing ages',{type:'click',action:'apply-guests',setup:(c,t,n)=>{n('[data-child-age][0]').value='';n('[data-child-age][1]').value='';}});
add('children maximum',{type:'click',action:'children-plus',setup:c=>c.guestDraft.ages=[0,5,17]});
add('adult minimum',{type:'click',action:'adults-minus',setup:c=>c.guestDraft.adults=1});
add('adult maximum',{type:'click',action:'adults-plus',setup:c=>c.guestDraft.adults=6});
add('invalid child index',{type:'click',action:'remove-child',dataset:{index:'bad'}});
add('star removed in drawer',{type:'click',action:'star',panel:true,filterDraft:true,dataset:{value:'3'}});
add('recovery missing choice',{type:'click',action:'recover-filters',dataset:{value:'99'}});
add('no draft filter',{type:'click',action:'remove-draft-filter'});
add('facet without host',{type:'change',target:{dataset:{filter:'resorts'}},setup:(c,t)=>t.closest=()=>null});
// openHotel belonged to the retired inline result expansion. It never changed
// connected markup. Project only that discarded field from each state snapshot;
// all 153 retained actions plus9 explicit-night cases, collaborator calls, DOM and ordering still compare.
function records(source){return scenarios.map(s=>{const result=characterize(source,s);delete result.state.openHotel;for(const checkpoint of result.checkpoints)delete checkpoint.state.openHotel;return {name:s.name,result};});}
const historicalNightActions=" case 'night-mode':{const mode=b.dataset.value;if(!['exact','range'].includes(mode))break;nightsDraft.mode=mode;nightsDraft.bound='min';nightsDraft.error='';if(mode==='exact')nightsDraft.max=nightsDraft.min;renderNightSelection();break;}\n case 'night-bound':if(nightsDraft.mode==='range'&&['min','max'].includes(b.dataset.value)){nightsDraft.bound=b.dataset.value;nightsDraft.error='';renderNightSelection();}break;\n case 'night-pick':{const n=+b.dataset.value;if(!Number.isInteger(n)||n<1||n>28)break;\n  if(nightsDraft.mode!=='range'){nightsDraft.min=n;nightsDraft.max=n;}\n  else{const min=nightsDraft.bound==='min'?n:Math.min(nightsDraft.min,n),max=nightsDraft.bound==='max'?n:Math.max(nightsDraft.max,n);if(max-min>10){nightsDraft.error='Между «От» и «До» может быть не больше 10 ночей. Измените другую границу или выберите ближе.';renderNightSelection();break;}nightsDraft.min=min;nightsDraft.max=max;if(nightsDraft.bound==='min')nightsDraft.bound='max';}\n  nightsDraft.error='';renderNightSelection();break;\n }\n case 'night-preset':{const n=+b.dataset.value;if(![7,10,14,21].includes(n))break;nightsDraft={min:n,max:n,mode:'exact',bound:'min'};renderNightSelection();break;}\n";
const nightStart=source.indexOf(" case 'night-pick':"),nightEnd=source.indexOf(" case 'apply-nights':",nightStart);assert(nightStart>=0&&nightEnd>nightStart);
const clearMealHistory="case 'clear-meal-query':$('#meal-query').value='';updateMealPicker();rememberUIRoute();$('#meal-query').focus();break;";
assert.equal(source.split(clearMealHistory).length,2,'one existing clear action records its cleared query');
const historicalSource=(source.slice(0,nightStart)+historicalNightActions+source.slice(nightEnd)).replace(clearMealHistory,clearMealHistory.replace('rememberUIRoute();',''));
const historical=records(historicalSource);
const actual=records(source);
const digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex');
const compareIndex=process.argv.indexOf('--compare');
if(compareIndex>=0)assert.deepEqual(actual,records(fs.readFileSync(process.argv[compareIndex+1],'utf8')),'before/after observable dispatch');
const BASELINE='b6492fbda92404504ac6a78cac7f34acad5fe2d600be3bfa2830292d6236c772';
assert.equal(actual.length,162,'153 retained dispatch cases plus9 explicit-night cases');
assert.equal(crypto.createHash('sha256').update(JSON.stringify(historical)).digest('hex'),BASELINE,'unchanged historical162-case event oracle, exact prepacket night handlers');
const revisedNightActions=new Set(['night-mode','night-bound','night-pick','night-preset']);
for(let i=0;i<scenarios.length;i++)if(!revisedNightActions.has(scenarios[i].action)){
 if(scenarios[i].action==='clear-meal-query'){
  const expected=structuredClone(historical[i]),trace=expected.result.trace,checkpoints=expected.result.checkpoints,paint=trace.findIndex(call=>call[0]==='updateMealPicker'),snapshot=checkpoints.findIndex(step=>step.name==='updateMealPicker');assert(paint>=0&&snapshot>=0);
  trace.splice(paint+1,0,['rememberUIRoute']);checkpoints.splice(snapshot+1,0,{...structuredClone(checkpoints[snapshot]),name:'rememberUIRoute'});
  assert.deepEqual(actual[i],expected,'clear changes only the exact history callback after paint/before focus; DOM/selection/order stay pinned');
 }else assert.deepEqual(actual[i],historical[i],'unchanged protected non-night event '+scenarios[i].name);
}
const result=name=>actual.find(r=>r.name===name).result;
assert.deepEqual(result('disabled click').trace,[]);
assert.deepEqual(result('unmatched click').trace,[]);
assert.equal(result('trigger replaced before microtask').triggerAfterDrain,'other');
assert.deepEqual(result('jump stops later attributes').trace,[['jumpToFilterSection','new']]);
assert.deepEqual(result('invalid sort stops later attributes').trace,[]);
assert.equal(result('changed results date submits').trace.at(-1)[0],'requestSubmit');
assert(!result('pending continue').trace.some(x=>x[0]==='continueSearch'));
const unknownNight=(action,value)=>characterize(source,{type:'click',action,dataset:{value},setup:c=>c.nightsDraft={min:7,max:10,phase:0,anchor:null}});
for(const [action,value] of [['night-mode','exact'],['night-mode','range'],['night-mode','unknown'],['night-bound','min'],['night-bound','max'],['night-bound','unknown']])assert.deepEqual(unknownNight(action,value),unknownNight('unknown',value),'removed mode/bound controls are inert: '+action+'/'+value);
const pickNight=(draft,value,action='night-pick')=>characterize(source,{type:'click',action,dataset:{value:String(value)},setup:c=>c.nightsDraft={...draft}});
const firstNight=pickNight({min:7,max:10,phase:0,anchor:null},7);assert.deepEqual(firstNight.nightsDraft,{min:7,max:7,phase:1,anchor:7});
const secondNight=pickNight(firstNight.nightsDraft,10);assert.deepEqual(secondNight.nightsDraft,{min:7,max:10,phase:0,anchor:null});
const thirdNight=pickNight(secondNight.nightsDraft,14);assert.deepEqual(thirdNight.nightsDraft,{min:14,max:14,phase:1,anchor:14});
const reverseFirst=pickNight({min:7,max:7,phase:0,anchor:null},10),reverseSecond=pickNight(reverseFirst.nightsDraft,7);assert.deepEqual(reverseSecond.nightsDraft,{min:7,max:10,phase:0,anchor:null});
const wideNight=pickNight(firstNight.nightsDraft,28);assert.equal(wideNight.nightsDraft.min,7);assert.equal(wideNight.nightsDraft.max,7);assert.equal(wideNight.nightsDraft.phase,1);assert.equal(wideNight.nightsDraft.anchor,7);assert.match(wideNight.nightsDraft.error,/10 ночей/);assert.deepEqual(pickNight(wideNight.nightsDraft,17).nightsDraft,{min:7,max:17,phase:0,anchor:null});
for(const n of [7,10,14,21])assert.deepEqual(pickNight(firstNight.nightsDraft,n,'night-preset').nightsDraft,{min:n,max:n,phase:0,anchor:null});
for(const n of [0,29,1.5,NaN]){const invalid=pickNight(firstNight.nightsDraft,n);assert.deepEqual(invalid.nightsDraft,firstNight.nightsDraft);assert(!invalid.trace.some(x=>x[0]==='renderNightSelection'));}
const restoreStart=source.indexOf('function restoredNightsDraft('),restoreEnd=source.indexOf('function openNights(',restoreStart);assert(restoreStart>=0&&restoreEnd>restoreStart);
const restoreCtx={Number,draft:{minNights:7,maxNights:10}};vm.createContext(restoreCtx);vm.runInContext(source.slice(restoreStart,restoreEnd),restoreCtx);
assert.deepEqual(JSON.parse(JSON.stringify(restoreCtx.restoredNightsDraft(firstNight.nightsDraft))),firstNight.nightsDraft,'Back/Forward restores exact first tap and anchor');
assert.deepEqual(JSON.parse(JSON.stringify(restoreCtx.restoredNightsDraft(secondNight.nightsDraft))),secondNight.nightsDraft,'Back/Forward restores completed range');
assert.deepEqual(JSON.parse(JSON.stringify(restoreCtx.restoredNightsDraft({min:7,max:10,phase:1,anchor:7}))),{min:7,max:10,phase:0,anchor:null},'forged active range anchor cannot grant another endpoint phase');
assert.deepEqual(result('first new hotel focuses').trace.slice(-3),[['renderMoreDestinationHotels'],['focus','.destination-hotel[4]',{preventScroll:true}],['scrollIntoView','.destination-hotel[4]',{block:'nearest',behavior:'instant'}]],'destination page focuses and reveals the first appended hotel');
const groupSetup=(c,t,node)=>{node('#group-room|AI').previousElementSibling=node('offer-group-heading');};
const groupHandled=characterize(source,{type:'click',action:'offer-group',groupHandled:true,dataset:{value:'room|AI'},setup:groupSetup});
assert.deepEqual(groupHandled.offerView.open,['room|AI']);assert.deepEqual(groupHandled.trace,[['queueMicrotask'],['renderOfferGroup','room|AI'],['focus','offer-group-heading',{preventScroll:true}]],'handled disclosure updates view and restores heading focus without a full render');
const groupFallback=characterize(source,{type:'click',action:'offer-group',dataset:{value:'room|AI'},setup:groupSetup});
assert.deepEqual(groupFallback.trace,[['queueMicrotask'],['renderOfferGroup','room|AI'],['renderOfferList'],['focus','offer-group-heading',{preventScroll:true}]],'stale disclosure falls back to the full renderer and restores focus');
// Stale comparison controls are unknown change events; they cannot mutate the
// retained favorites, selected tour, provider, filter or flight state.
const unknownChange=characterize(source,{type:'change',target:{id:'unknown',value:'2',dataset:{id:'7'}}});
for(const id of ['tour-differences-only','comparison-pair-0','comparison-pair-1','compare-differences','compare-left','compare-right','compare-offer-day','compare-offer-nights'])assert.deepEqual(characterize(source,{type:'change',target:{id,value:'2',dataset:{id:'7'}}}),unknownChange,'retired comparison ID is inert: '+id);
assert.deepEqual(characterize(source,{type:'change',target:{name:'comparison-focus',value:'2'}}),characterize(source,{type:'change',target:{name:'unknown',value:'2'}}),'retired comparison-focus is inert');
const unknownClick=characterize(source,{type:'click',action:'unknown'});
for(const action of ['toggle-offers','more-offers','accept-price'])assert.deepEqual(characterize(source,{type:'click',action}),unknownClick,'retired inline-offer/verification action is inert: '+action);
// Prove these observations reject two plausible extraction regressions.
assert.notDeepEqual(records(source.replace("queueMicrotask(()=>{if(actionTrigger===b)actionTrigger=null;});","queueMicrotask(()=>{actionTrigger=null;});")),actual,'trigger ownership mutation detected');
assert.notDeepEqual(records(source.replace("if(!b||b.disabled)return;","if(!b)return;")),actual,'disabled-control mutation detected');
assert.notDeepEqual(records(source.replace('state.sort=t.value;renderResults({keepFilters:true})','renderResults({keepFilters:true});state.sort=t.value')),actual,'state-before-render mutation detected');
console.log(`PASS event dispatch: ${actual.length} retained scenarios + 9 retired inert changes + 3 retired inert clicks, approved presentation digest ${digest}, dispatch/state/DOM/focus/microtasks; transport HTTP 0`);
