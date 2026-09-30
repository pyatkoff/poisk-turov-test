// Characterize actual presentation owners against the pre-O11 baseline.
// No bootstrap, supplier request, quote or lead submission is executed.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),crypto=require('node:crypto');
const {JSDOM}=require('jsdom');
const acorn=require('../scripts/build/search3-js/node_modules/acorn');
const source=fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8');
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function functions(source,names){const nodes=acorn.parse(source,{ecmaVersion:'latest'}).body[1].expression.callee.body.body;return nodes.filter(n=>n.type==='FunctionDeclaration'&&names.includes(n.id.name)).map(n=>source.slice(n.start,n.end)).join('\n');}
function environment(html){const dom=new JSDOM(html);const document=dom.window.document;return {dom,document,$:s=>document.querySelector(s),$$:s=>[...document.querySelectorAll(s)],esc,icon:n=>`<i>${n}</i>`,hotelCountText:n=>`${n} отелей`,normalizeSearch:s=>String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/ё/g,'е').trim()};}
function facet(source,s){
 const c=environment('<div id="host"></div>'),selected=s.selected?[0,8,18].filter(v=>v<s.n).map(v=>'v'+v):[],filters={resorts:[],operators:[],meals:[],amenities:[],[s.group]:selected};
 Object.assign(c,{editingFilterModel:()=>({filters}),countMatchingHotels:m=>{const value=m.filters[s.group]?.[0];return value?Number(value.slice(1))%4:0;},facetQueries:new Map([[s.group,s.query]]),expandedFacets:new Set(s.expanded?[s.group]:[]),amenityNames:new Map()});
 c.countFacetOptions=(model,group,values)=>new Map(values.map(value=>[value,c.countMatchingHotels({...model,filters:{...model.filters,[group]:[value]}})]));
 vm.createContext(c);vm.runInContext(functions(source,['compareMealLabels','comparePopularFacetOptions','filterCheckRowHTML','checkRows','fullCheckRows','applyFacetSearch','amenityFilterGroups']),c);
 const options=Array.from({length:s.n},(_,i)=>['v'+i,'Вариант '+i+(i===8?' Ёлка <&':'')]);
 c.$('#host').innerHTML=c.checkRows(s.group,options);
 let reads=0;const originalQuery=c.dom.window.Element.prototype.querySelector;c.dom.window.Element.prototype.querySelector=function(selector){if(this.matches('.check-row'))reads++;return originalQuery.call(this,selector);};
 const host=c.$('.facet-options');if(host){
  const input=host.querySelector(s.focus==='search'?'[data-facet-search]':`.check-row:nth-of-type(${s.focus==='last'?7:1}) input`);input?.focus();
  c.applyFacetSearch(host);
  // A second pass covers updates, moved nodes and checked-but-unavailable rows.
  const first=host.querySelector('.check-row input');if(first)first.checked=!first.checked;
  c.applyFacetSearch(host);
 }
 const result={html:c.$('#host').innerHTML,focus:c.document.activeElement.outerHTML};c.dom.window.close();return s.work?{result,reads}:result;
}
function amenities(source,selected){
 const c=environment(''),filters={amenities:selected?['pool','retained']:[]};
 Object.assign(c,{editingFilterModel:()=>({filters}),countMatchingHotels:m=>m.filters.amenities.includes('pool')?3:0,amenityNames:new Map([['retained',{key:'retained',label:'Сохранённое <&',groupId:2,group:'Другие'}]])});
 vm.createContext(c);vm.runInContext(functions(source,['filterCheckRowHTML','amenityFilterGroups']),c);
 const html=c.amenityFilterGroups([{amenities:[{key:'pool',label:'Бассейн <&',groupId:1,group:'Удобства'},{key:'hidden',label:'Нет данных',filterable:false}]}],filters);c.dom.window.close();return html;
}
function destination(source,s){
 const c=environment('<input id="destination-query"><button data-action="clear-destination-query"></button><div id="destination-selection"></div><div id="destination-results"></div><button data-action="apply-destination"></button><div class="destination-apply-context"></div>');
 const rows=Array.from({length:24},(_,i)=>({id:i+1,country:'4',name:i%3?'Hotel '+i:'Resort '+i+' hotel',resort:'Курорт <&',stars:i%6,photos:['x<&']}));
 let calls=0;
 Object.assign(c,{destinationChoice:{country:'4',resorts:s.resorts?['Регион <&']:[],hotelId:s.hotel?2:0},countryNames:{'4':'Турция','5':'Египет'},catalogReady:s.ready,catalogError:s.error?'Ошибка <&':'',catalogDeparture:'Москва',destinationHotel:id=>rows.find(h=>h.id===id),recentDestinations:()=>[{country:'4',resorts:[],hotelId:0}],destinationLabel:()=> 'Турция <&',destinationOrder:(a,b)=>a.localeCompare(b,'ru'),resortGroups:()=>[{parent:{country:'4',name:'Регион <&'},children:[]}],resortChoiceHTML:r=>'<button>'+esc(r.name)+'</button>',destinationResortsExpanded:false,destinationResortPreviewLimit:6,resortLoads:new Map(),data:{catalog:{regions:{'4':[]}}},hotels:rows.slice(0,12),matchesHotelQuery:()=>true,normalizeHotelQuery:value=>{calls++;return c.normalizeSearch(value).replace(/[^\p{L}\p{N}]+/gu,' ').trim();},destinationLookup:{status:s.status,rows:rows.slice(8)},destinationHotelLimit:8,destinationHotelPageSize:8,destinationResolvedQuery:s.resolved?s.query:'',photoUrl:h=>h.photos[0],rememberUIRoute:()=>{}});
 c.$('#destination-query').value=s.query;
 vm.createContext(c);vm.runInContext(functions(source,['destinationNameMatches','renderDestination']),c);c.renderDestination();
 const result={html:c.document.body.innerHTML,disabled:c.$('[data-action="apply-destination"]').disabled};c.dom.window.close();return {result,calls};
}
function observations(source){const records=[];for(const group of ['resorts','operators','meals'])for(const n of [0,1,7,8,20])for(const query of ['', 'Вариант 1','елка','несуществующий'])for(const selected of [false,true])for(const expanded of [false,true])for(const focus of ['first','last','search'])records.push(facet(source,{group,n,query,selected,expanded,focus}));for(const selected of [false,true])records.push(amenities(source,selected));for(const query of ['', 'h','hotel','hot el','Ёлка'])for(const status of ['idle','loading','error','complete'])for(const ready of [false,true])for(const hotel of [false,true])for(const resorts of [false,true])for(const resolved of [false,true])records.push(destination(source,{query,status,ready,hotel,resorts,resolved,error:status==='error'}).result);return records;}
const actual=observations(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex');
if(!process.argv.includes('--capture'))assert.equal(digest,'7a16a4b0b373fe30821b8a2ba4299344b84dfab1df6b59388079b6535656dc9f','original filter/destination HTML, availability, ordering, selection and focus');
const i=process.argv.indexOf('--compare');if(i>=0){const baseline=fs.readFileSync(process.argv[i+1],'utf8');assert.deepEqual(actual,observations(baseline),'before/after presentation');for(const query of ['hotel 1','hotel 22','hotel 0','???'])assert.deepEqual(destination(source,{query,status:'complete',ready:true}).result,destination(baseline,{query,status:'complete',ready:true}).result,'mixed name preference and empty-word query');}
if(source.includes('function filterCheckRowHTML(')){
 const scenario={group:'operators',n:20,query:'Вариант 1',selected:true,expanded:false,focus:'first'};
 assert.notDeepEqual(facet(source.replace('count>0||selected','count>0&&selected'),scenario),facet(source,scenario),'availability mutation detected');
 assert.notDeepEqual(facet(source.replace('row.hidden=!matches','row.hidden=false'),scenario),facet(source,scenario),'facet query mutation detected');
 const current=facet(source,{...scenario,work:true});assert.equal(current.reads,120,'three row DOM reads per item per refresh');
 if(i>=0){const baseline=fs.readFileSync(process.argv[i+1],'utf8');const old=facet(baseline,{...scenario,work:true});assert(current.reads<old.reads);const oldDestination=destination(baseline,{query:'hotel',status:'complete',ready:true});assert(oldDestination.calls>25);console.log(`WORK facet row reads ${old.reads}→${current.reads}; destination normalization ${oldDestination.calls}→25`);}
 const preference={query:'hotel 1',status:'complete',ready:true};assert.notDeepEqual(destination(source.replace('Number(nameMatches(b))-Number(nameMatches(a))','Number(nameMatches(a))-Number(nameMatches(b))'),preference).result,destination(source,preference).result,'name ranking mutation detected');
 const work=destination(source,{query:'hotel',status:'complete',ready:true});assert.equal(work.calls,25,'one query normalization plus one per distinct hotel');
}
console.log(`PASS filter/destination presentation: ${actual.length} original DOM/focus observations; digest ${digest}; supplier/lead HTTP 0`);

// Actual DOM callers retain zero counts, checked availability and the any-meal row.
{
 const c=environment('<div id="host">'+['zero','yes','checked'].map(value=>'<label class="check-row"><input data-filter="operators" value="'+value+'" '+(value==='checked'?'checked':'')+'><small>99</small></label>').join('')+'<label class="check-row"><input data-filter="amenities" value="pool"><small>99</small></label><label class="check-row"><input data-filter-bool="rating"><small>99</small></label></div>');
 let scalarCalls=0,batchCalls=0;const filters={operators:[],amenities:[],rating:false};
 Object.assign(c,{editingFilterModel:()=>({filters}),countFacetOptions:(model,group,values)=>{batchCalls++;return new Map(values.map(value=>[value,value==='yes'?5:0]));},countMatchingHotels:model=>{scalarCalls++;return model.filters.rating?2:model.filters.amenities.includes('pool')?3:99;},applyFacetSearch:()=>{},updateFilterStars:()=>{},syncAvailableFilterGroups:()=>{},renderFilterNavigation:()=>{}});
 vm.createContext(c);vm.runInContext(functions(source,['updateFacetCounts']),c);c.updateFacetCounts();
 assert.deepEqual(c.$$('.check-row').map(row=>[row.querySelector('small').textContent,row.dataset.available,row.hidden]),[['0','false',true],['5','true',false],['0','true',false],['3','true',false],['2','true',false]]);
 assert.equal(batchCalls,1,'one batch per section');assert.equal(scalarCalls,2,'zero batched counts do not fall back');
 c.dom.window.close();
}
{
 const c=environment('<div id="host">'+['','AI','BB'].map(value=>'<label class="meal-option"><input value="'+value+'"><span class="meal-hotel-count">99</span></label>').join('')+'</div>');let current={filters:{meals:['AI']}};
 Object.assign(c,{mealPreviewModel:()=>current,countFacetOptions:(model,group,values)=>new Map(values.map(value=>[value,value==='AI'?3:0])),countMatchingHotels:model=>model.filters.meals.length?99:9});
 vm.createContext(c);vm.runInContext(functions(source,['updateMealCounts']),c);c.updateMealCounts();
 assert.deepEqual(c.$$('.meal-hotel-count').map(span=>span.textContent),['9','3','0'],'any/known/unavailable meal counts');
 current=null;c.updateMealCounts();assert.equal(c.$$('.meal-hotel-count').length,0,'new draft has no result counts');
 c.dom.window.close();
}
{
 const c=environment('<div id="host"></div>'),model={filters:{stars:[4]}};
 Object.assign(c,{hotels:[{country:'4',stars:2},{country:'4',stars:3}],state:{search:{country:'4'}},countFacetOptions:(model,group,values)=>new Map(values.map(value=>[value,value===2?5:0]))});
 vm.createContext(c);vm.runInContext(functions(source,['filterStarButtons']),c);c.$('#host').innerHTML=c.filterStarButtons(model);
 assert.deepEqual(c.$$('button').map(button=>[button.dataset.value,button.getAttribute('aria-pressed'),button.querySelector('small').textContent]),[['2','false','5'],['4','true','0']],'selected unavailable star stays visible');
 c.dom.window.close();
}
console.log('PASS facet DOM callers: batch zero/checked availability, scalar amenities/rating, any-meal/null-draft and selected-star counts');
