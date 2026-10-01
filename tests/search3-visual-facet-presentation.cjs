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
function facetRefresh(source,legacy=false){
 let reads=0,scalarCalls=0;const inputs=[],batchCalls=[],tails=[];
 const fake=(dataset,value,index)=>{const tracked=new Proxy(dataset,{get(target,key){if(key==='filter')reads++;return target[key];}}),label={textContent:'',ariaLabel:'',setAttribute(name,value){if(name==='aria-label')this.ariaLabel=value;}},row={dataset:{},hidden:false,querySelector:()=>label,closest:()=>null};return {dataset:tracked,value,checked:index%17===0,closest:()=>row,row,label};};
 let index=0;for(const group of ['meals','operators','flight','resorts','stars'])for(let i=0;i<(group==='stars'?5:20);i++)inputs.push(fake({filter:group},group==='stars'?i+1:group+i,index++));
 for(let i=0;i<5;i++)inputs.push(fake({filter:'amenities'},'amenity'+i,index++));
 for(let i=0;i<4;i++)inputs.push(fake({filterBool:'boolean'+i},'',index++));
 const model={filters:{amenities:[],stars:[]}},c={hotels:[1,2,3,4,5].map(stars=>({country:'4',stars})),state:{search:{country:'4'}},editingFilterModel:()=>model,$:()=>null,$$:selector=>selector==='[data-filter],[data-filter-bool]'?inputs:[],countFacetOptions:(current,group,values)=>{batchCalls.push([group,[...values]]);return new Map(values.map((value,i)=>[value,i%4]));},countMatchingHotels:()=>{scalarCalls++;return 2;},hotelCountText:n=>n+' отелей',applyFacetSearch:()=>tails.push('facet'),updateFilterStars:()=>tails.push('stars'),syncAvailableFilterGroups:()=>tails.push('groups'),renderFilterNavigation:()=>tails.push('nav'),settleFilterRoots:()=>{}};
 const previous=`function updateFacetCounts(){const model=editingFilterModel(),inputs=\$\$('[data-filter],[data-filter-bool]'),counts=new Map();
 for(const input of inputs){const group=input.dataset.filter;if(group&&group!=='amenities'&&!counts.has(group))counts.set(group,countFacetOptions(model,group,inputs.filter(row=>row.dataset.filter===group).map(row=>row.value)));}
 inputs.forEach(input=>{const key=input.dataset.filter||input.dataset.filterBool,value=key==='amenities'?[...new Set([...(model.filters.amenities||[]),input.value])]:input.dataset.filter?[input.value]:true,count=counts.get(key)?.get(input.value)??countMatchingHotels({...model,filters:{...model.filters,[key]:value}}),row=input.closest('.check-row'),label=row?.querySelector('small'),available=count>0||input.checked;if(label){label.textContent=count;label.setAttribute('aria-label',hotelCountText(count))}if(row){row.dataset.available=String(available);if(!row.closest('.facet-options'))row.hidden=!available;}});\$\$('[data-facet-options]').forEach(applyFacetSearch);updateFilterStars();syncAvailableFilterGroups();renderFilterNavigation();}`;
 vm.createContext(c);vm.runInContext(legacy?previous:functions(source,['filterStarOptions','updateFacetCounts']),c);c.updateFacetCounts();
 return {reads,scalarCalls,batchCalls,rows:inputs.map(input=>[input.label.textContent,input.label.ariaLabel,input.row.dataset.available,input.row.hidden]),tails};
}
function observations(source){const records=[];for(const group of ['resorts','operators','meals'])for(const n of [0,1,7,8,20])for(const query of ['', 'Вариант 1','елка','несуществующий'])for(const selected of [false,true])for(const expanded of [false,true])for(const focus of ['first','last','search'])records.push(facet(source,{group,n,query,selected,expanded,focus}));for(const selected of [false,true])records.push(amenities(source,selected));for(const query of ['', 'h','hotel','hot el','Ёлка'])for(const status of ['idle','loading','error','complete'])for(const ready of [false,true])for(const hotel of [false,true])for(const resorts of [false,true])for(const resolved of [false,true])records.push(destination(source,{query,status,ready,hotel,resorts,resolved,error:status==='error'}).result);return records;}
const actual=observations(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex');
if(!process.argv.includes('--capture'))assert.equal(digest,'7a16a4b0b373fe30821b8a2ba4299344b84dfab1df6b59388079b6535656dc9f','original filter/destination HTML, availability, ordering, selection and focus');
const i=process.argv.indexOf('--compare'),rootBaselineIndex=process.argv.indexOf('--root-baseline'),rootBaseline=rootBaselineIndex>=0?fs.readFileSync(process.argv[rootBaselineIndex+1],'utf8'):null;if(i>=0){const baseline=fs.readFileSync(process.argv[i+1],'utf8');assert.deepEqual(actual,observations(baseline),'before/after presentation');for(const query of ['hotel 1','hotel 22','hotel 0','???'])assert.deepEqual(destination(source,{query,status:'complete',ready:true}).result,destination(baseline,{query,status:'complete',ready:true}).result,'mixed name preference and empty-word query');}
if(source.includes('function filterCheckRowHTML(')){
 const scenario={group:'operators',n:20,query:'Вариант 1',selected:true,expanded:false,focus:'first'};
 assert.notDeepEqual(facet(source.replace('count>0||selected','count>0&&selected'),scenario),facet(source,scenario),'availability mutation detected');
 assert.notDeepEqual(facet(source.replace('row.hidden=!matches','row.hidden=false'),scenario),facet(source,scenario),'facet query mutation detected');
 const current=facet(source,{...scenario,work:true});assert.equal(current.reads,120,'three row DOM reads per item per refresh');
 if(i>=0){const baseline=fs.readFileSync(process.argv[i+1],'utf8');const old=facet(baseline,{...scenario,work:true});assert.equal(current.reads,old.reads,'dynamic row reads remain fresh');const oldDestination=destination(baseline,{query:'hotel',status:'complete',ready:true});assert.equal(oldDestination.calls,25);console.log(`WORK facet row reads ${old.reads}→${current.reads}; destination normalization unchanged25`);}
 const preference={query:'hotel 1',status:'complete',ready:true};assert.notDeepEqual(destination(source.replace('Number(nameMatches(b))-Number(nameMatches(a))','Number(nameMatches(a))-Number(nameMatches(b))'),preference).result,destination(source,preference).result,'name ranking mutation detected');
 const work=destination(source,{query:'hotel',status:'complete',ready:true});assert.equal(work.calls,25,'one query normalization plus one per distinct hotel');
}
console.log(`PASS filter/destination presentation: ${actual.length} original DOM/focus observations; digest ${digest}; supplier/lead HTTP 0`);

// Actual DOM callers retain zero counts, checked availability and the any-meal row.
{
 const c=environment('<div id="host">'+['zero','yes','checked'].map(value=>'<label class="check-row"><input data-filter="operators" value="'+value+'" '+(value==='checked'?'checked':'')+'><small>99</small></label>').join('')+'<label class="check-row"><input data-filter="amenities" value="pool"><small>99</small></label><label class="check-row"><input data-filter-bool="rating"><small>99</small></label></div>');
 let scalarCalls=0,batchCalls=0;const filters={operators:[],amenities:[],stars:[],rating:false};
 Object.assign(c,{hotels:[],state:{search:{country:'4'}},editingFilterModel:()=>({filters}),countFacetOptions:(model,group,values)=>{batchCalls++;return new Map(values.map(value=>[value,value==='yes'?5:0]));},countMatchingHotels:model=>{scalarCalls++;return model.filters.rating?2:model.filters.amenities.includes('pool')?3:99;},applyFacetSearch:()=>{},updateFilterStars:()=>{},syncAvailableFilterGroups:()=>{},renderFilterNavigation:()=>{},settleFilterRoots:()=>{}});
 vm.createContext(c);vm.runInContext(functions(source,['filterStarOptions','updateFacetCounts']),c);c.updateFacetCounts();
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
 vm.createContext(c);vm.runInContext(functions(source,['filterStarOptions','filterStarButtons']),c);c.$('#host').innerHTML=c.filterStarButtons(model);
 assert.deepEqual(c.$$('button').map(button=>[button.dataset.value,button.getAttribute('aria-pressed'),button.querySelector('small').textContent]),[['2','false','5'],['4','true','0']],'selected unavailable star stays visible');
 c.dom.window.close();
}
{
 const c=environment('<div id="filters"><div class="filter-group"><div class="star-options"></div></div></div>'),model={filters:{stars:[5],amenities:[]}};let inventories=0,countryReads=0,starReads=0;
 const hotels=Array.from({length:100},(_,i)=>({get country(){countryReads++;return '4'},get stars(){starReads++;return i%2?3:2}}));
 Object.assign(c,{hotels,state:{search:{country:'4'}},editingFilterModel:()=>model,countFacetOptions:(current,group,values)=>{inventories++;assert.equal(group,'stars');return new Map(values.map(value=>[value,value===2?3:0]));},countMatchingHotels:()=>{throw Error('star scalar fallback')},applyFacetSearch:()=>{},syncAvailableFilterGroups:()=>{},renderFilterNavigation:()=>{},settleFilterRoots:()=>{}});
 vm.createContext(c);vm.runInContext(functions(source,['filterStarOptions','filterStarButtons','updateFilterStars','updateFacetCounts']),c);c.updateFacetCounts();
 assert.equal(inventories,1,'one complete star inventory per filter refresh');
 assert.equal(countryReads,100,'one canonical star option country pass per filter refresh');
 assert.equal(starReads,100,'one canonical star option value pass per filter refresh');
 assert.deepEqual(c.$$('.star-options button').map(button=>[button.dataset.value,button.getAttribute('aria-pressed'),button.querySelector('small').textContent]),[['2','false','3'],['5','true','0']],'batched star inventory retains available and selected-unavailable buttons');
 c.dom.window.close();
}
{
 const current=facetRefresh(source),previous=facetRefresh(source,true);
 assert.deepEqual({...current,reads:0},{...previous,reads:0},'one-pass grouping preserves batch inputs, scalar fallbacks, counts, availability and final refresh calls');
 assert.equal(previous.reads,747,'previous grouping rescans the whole input list for each facet section');
 assert.equal(current.reads,277,'current grouping reads each filter identity once before rendering counts');
 console.log(`WORK facet identity reads ${previous.reads}→${current.reads}; five batch sections and observable rows preserved`);
}
console.log('PASS facet DOM callers: batch zero/checked availability, scalar amenities/rating, any-meal/null-draft and selected-star counts');

function facetOrderEnvironment(code,group,n){
 const c=environment('<div id="host"></div>'),filters={resorts:[],operators:[],meals:[],amenities:[]};
 Object.assign(c,{editingFilterModel:()=>({filters}),countMatchingHotels:model=>Number(model.filters[group][0]?.slice(1))%4||0,facetQueries:new Map(),expandedFacets:new Set(),amenityNames:new Map()});
 c.countFacetOptions=(model,key,values)=>new Map(values.map(value=>[value,c.countMatchingHotels({...model,filters:{...filters,[key]:[value]}})]));
 vm.createContext(c);vm.runInContext(functions(code,['compareMealLabels','comparePopularFacetOptions','filterCheckRowHTML','checkRows','fullCheckRows','applyFacetSearch']),c);
 c.$('#host').innerHTML=c.checkRows(group,Array.from({length:n},(_,j)=>['v'+j,'Вариант '+j]));
 const host=c.$('.facet-options'),more=host.querySelector('details');c.applyFacetSearch(host);
 const work={reads:0,moves:0};
 const query=c.dom.window.Element.prototype.querySelector;c.dom.window.Element.prototype.querySelector=function(selector){if(this.matches('.check-row'))work.reads++;return query.call(this,selector);};
 for(const node of [host,more]){const insert=node.insertBefore;node.insertBefore=function(row,before){if(row.matches?.('.check-row'))work.moves++;return insert.call(this,row,before);};}
 const append=more.append;more.append=function(...nodes){work.moves+=nodes.filter(row=>row.matches?.('.check-row')).length;return append.apply(this,nodes);};
 const snapshot=()=>({html:c.$('#host').innerHTML,focus:c.document.activeElement===c.document.body?'BODY':c.document.activeElement.outerHTML,checked:[...host.querySelectorAll('.check-row input')].map(input=>[input.value,input.checked])});
 return {c,filters,host,more,work,snapshot};
}
function facetOrderRecords(code){
 const out=[];
 for(const group of ['meals','resorts','operators'])for(const n of [8,20])for(const expanded of [false,true]){
  const {c,filters,host,more,snapshot}=facetOrderEnvironment(code,group,n),refresh=()=>{c.applyFacetSearch(host);out.push(snapshot());},rows=[...host.querySelectorAll('.check-row')];
  c.expandedFacets[expanded?'add':'delete'](group);rows[0].querySelector('input').focus();refresh();refresh();
  c.facetQueries.set(group,'вариант 1');refresh();
  rows.forEach((row,j)=>{row.querySelector('small').textContent=String((n-j)%5);row.dataset.available=String(j%3!==0);});refresh();
  rows.forEach((row,j)=>row.querySelector('input').checked=j%4===0);filters[group]=rows.filter(row=>row.querySelector('input').checked).map(row=>row.querySelector('input').value);refresh();
  rows.forEach((row,j)=>row.querySelector('span').textContent='Ёлка <& '+j);c.facetQueries.set(group,'елка');refresh();
  host.append(rows.at(-1));more.insertBefore(rows[0],more.firstChild);refresh();
  const added=rows[1].cloneNode(true);added.querySelector('input').value='added';added.querySelector('input').checked=true;added.dataset.available='false';more.append(added);filters[group].push('added');refresh();
  more.append(c.document.createTextNode('unowned tail'));host.insertBefore(c.document.createElement('hr'),more);refresh();
  rows[2].innerHTML='<input type="checkbox" value="replaced" checked><span>Новый &lt;&amp; ярлык</span><small>0</small>';rows[2].dataset.available='false';filters[group].push('replaced');c.facetQueries.set(group,'новый');refresh();
  rows[3].remove();c.facetQueries.delete(group);host.querySelector('[data-facet-search]').focus();refresh();
  c.expandedFacets[expanded?'delete':'add'](group);refresh();c.dom.window.close();
 }
 return out;
}
const orderRecords=facetOrderRecords(source),orderDigest=crypto.createHash('sha256').update(JSON.stringify(orderRecords)).digest('hex');
if(process.argv.includes('--capture'))console.log('CAPTURE facet order digest',orderDigest);
else assert.equal(orderDigest,'2c3369db65ee06032e5721f4e70b2c27d39b19982eca5278697b8457bb00db0f','original dynamic rows, child-node repair, labels, availability, order and focus');
if(i>=0)assert.deepEqual(orderRecords,facetOrderRecords(fs.readFileSync(process.argv[i+1],'utf8')),'original/candidate live row reconciliation');
for(const [original,replacement]of [['row.nextSibling!==before','false'],['row.parentNode!==parent||','']]){
 let changed;try{changed=JSON.stringify(facetOrderRecords(source.replace(original,replacement)))!==JSON.stringify(orderRecords);}catch(error){assert.equal(error.name,'NotFoundError');changed=true;}
 assert(changed,'row suffix/parent mutation detected');
}
for(const n of [20,1000]){
 const current=facetOrderEnvironment(source,'operators',n);current.work.reads=current.work.moves=0;current.c.applyFacetSearch(current.host);
 assert.equal(current.work.reads,n*3,'dynamic row descriptors reread on every refresh');assert.equal(current.work.moves,0,'settled order needs no physical row move');
 if(i>=0){const old=facetOrderEnvironment(fs.readFileSync(process.argv[i+1],'utf8'),'operators',n);old.work.reads=old.work.moves=0;old.c.applyFacetSearch(old.host);assert.equal(old.work.moves,n);assert.deepEqual(current.snapshot(),old.snapshot());console.log('WORK settled facet rows',JSON.stringify({n,before:old.work,after:current.work}));old.c.dom.window.close();}
 current.c.dom.window.close();
}
console.log(`PASS facet row reconciliation: ${orderRecords.length} original dynamic DOM/focus states (${orderDigest}); live counts/labels/checked reads retained; settled moves20/1000→0; supplier/lead HTTP0`);

function filterRootMarkup(groups=[334,333,333],changed=-1){return '\n '+groups.map((count,group)=>`<div class="filter-group"><h4>Группа ${group}</h4><div class="facet-options" data-facet-options="g${group}"><div class="facet-search"><input type="search" data-facet-search="g${group}" value=""></div>${Array.from({length:count},(_,row)=>`<label class="check-row" data-available="${row%3!==0}"><input data-filter="g${group}" value="v${row}" ${row===0?'checked':''}><span>Вариант ${row}</span><small>${row===0&&group===changed?9:row%5}</small></label>`).join('')}</div></div>`).join('\n ')+'\n';}
function filterEditorMarkup(){return `\n <div class="filter-group"><h4>Название</h4><input id="hotel-query" type="search" value=""></div>\n <div class="filter-group"><h4>Операторы</h4><div class="facet-options" data-facet-options="operators"><div class="facet-search"><input type="search" data-facet-search="operators" value=""></div><label class="check-row" data-available="true"><input data-filter="operators" value="one"><span>Один</span><small>1</small></label></div></div>\n <div class="filter-group"><h4>Бюджет</h4><input id="min-price" value="0"><input id="max-price" value=""><input id="price-range" type="range" min="0" max="10" value="10"></div>\n`;}
function filterPainter(code){
 const c=environment('<div id="filters"></div>'),filters={};
 Object.assign(c,{MutationObserver:c.dom.window.MutationObserver,filterRootBindings:new WeakMap(),renderedFilterContext:null,filterDraft:null,filterEditorLease:null,filterEditorSelector:'[data-facet-search],#hotel-query,#min-price,#max-price,#price-range',state:{search:{country:'4'}},searchKey:()=> 'scope',editingFilterModel:()=>({filters}),applyFacetSearch:()=>{},syncAvailableFilterGroups:()=>{}});
 vm.createContext(c);vm.runInContext(functions(code,['filterRootBinding','filterRootMarkup','settleFilterRoots','reconcileFilterRoots','paintFilters']),c);
 return {c,filters,host:c.$('#filters'),paint:(markup,next=filters)=>c.paintFilters(markup,next)};
}
function paintIdentity(code,markup){
 const e=filterPainter(code);e.paint(markup);const roots=[...e.host.children],descendants=[...e.host.querySelectorAll('*')];e.paint(markup);
 const result={html:e.host.innerHTML,rootCount:roots.filter(node=>e.host.contains(node)).length,descendantCount:descendants.filter(node=>e.host.contains(node)).length,total:descendants.length};e.c.dom.window.close();return result;
}
function editorPaintRecord(code,selector){
 const e=filterPainter(code),markup=filterEditorMarkup();e.paint(markup);const active=e.host.querySelector(selector);active.focus();active.value='draft value';if(active.setSelectionRange)try{active.setSelectionRange(2,5)}catch{}e.paint(markup);const restored=e.host.querySelector(selector),result={html:e.host.innerHTML,value:restored.value,focus:e.c.document.activeElement===restored,selection:restored.selectionStart===null?null:[restored.selectionStart,restored.selectionEnd]};e.c.dom.window.close();return result;
}
{
 const markup=filterRootMarkup(),current=paintIdentity(source,markup),baseline=rootBaseline?paintIdentity(rootBaseline,markup):null;
 assert(current.total>4000,'representative filter tree has more than 4,000 descendants');assert.equal(current.rootCount,3,'unchanged generated filter roots retain identity');assert.equal(current.descendantCount,current.total,'unchanged filter descendants retain identity');
 if(baseline){assert.equal(baseline.rootCount,0,'baseline replaces every generated root');assert.equal(baseline.descendantCount,0,'baseline replaces every descendant');assert.equal(current.html,baseline.html,'unchanged second paint remains byte-identical');console.log(`WORK settled filter roots ${JSON.stringify({descendants:current.total,retainedBefore:baseline.descendantCount,retainedAfter:current.descendantCount,removedInsertedBefore:[baseline.total,baseline.total],removedInsertedAfter:[0,0]})}`);}
}
{
 const e=filterPainter(source),markup=filterRootMarkup([8,8,8]);e.paint(markup);const roots=[...e.host.children];roots[1].querySelector('h4').textContent='Нарушено';e.paint(markup);assert.equal(e.host.querySelectorAll('h4')[1].textContent,'Группа 1','disturbed root is repaired');assert.equal(e.host.children[0],roots[0],'clean preceding root retained');assert.notEqual(e.host.children[1],roots[1],'disturbed root replaced');assert.equal(e.host.children[2],roots[2],'clean following root retained');
 const settled=[...e.host.children];e.paint(filterRootMarkup([8,8,8],1));assert.equal(e.host.children[0],settled[0]);assert.notEqual(e.host.children[1],settled[1],'changed generated group replaced');assert.equal(e.host.children[2],settled[2]);
 e.host.append(e.c.document.createElement('aside'));const before=[...e.host.children];e.paint(filterRootMarkup([8,8,8],1));assert.equal(e.host.children.length,3,'extra root removed');assert(before.slice(0,3).every((node,index)=>e.host.children[index]!==node),'top-level structural disturbance invalidates the root set');e.c.dom.window.close();
}
for(const selector of ['#hotel-query','[data-facet-search="operators"]','#min-price']){
 if(rootBaseline)assert.deepEqual(editorPaintRecord(source,selector),editorPaintRecord(rootBaseline,selector),selector+' ordinary active-editor reference parity');
 const e=filterPainter(source),markup=filterEditorMarkup();e.paint(markup);const active=e.host.querySelector(selector),group=active.closest('.filter-group');active.focus();active.value='draft value';if(active.setSelectionRange)try{active.setSelectionRange(2,5)}catch{}group.querySelector('h4').textContent='Нарушено';e.paint(markup);
 const restored=e.host.querySelector(selector);assert.equal(e.c.document.activeElement,restored,selector+' focus restored after repair');assert.equal(restored.value,'draft value',selector+' unfinished value restored');assert.equal(restored.closest('.filter-group').querySelector('h4').textContent,selector==='[data-facet-search="operators"]'?'Операторы':selector==='#hotel-query'?'Название':'Бюджет',selector+' disturbed group repaired');if(restored.selectionStart!==null)assert.deepEqual([restored.selectionStart,restored.selectionEnd],[2,5],selector+' caret restored');e.c.dom.window.close();
}
{
 const e=filterPainter(source),markup=filterEditorMarkup(),firstModel={},nextModel={};e.paint(markup,firstModel);const active=e.host.querySelector('[data-facet-search="operators"]'),group=active.closest('.filter-group'),shell=e.c.document.createElement('div'),other=e.c.document.createElement('button');shell.className='filter-section-values';group.replaceWith(shell);shell.append(group);e.c.filterRootBindings.get(e.host).observer.takeRecords();e.c.document.body.append(other);active.focus();active.value='draft value';active.setSelectionRange(2,5);e.c.filterEditorLease=active;other.focus();e.c.filterDraft={filters:nextModel};e.paint(markup,nextModel);
 assert.equal(e.host.querySelector('[data-facet-search="operators"]'),active,'progressive mobile draft wrapper replacement retains the editor');assert.equal(e.c.document.activeElement,active,'progressive mobile draft wrapper replacement retains focus');assert.deepEqual([active.selectionStart,active.selectionEnd],[2,5],'progressive mobile draft wrapper replacement retains the caret');
 e.c.filterDraft=null;e.paint(markup,{});assert.notEqual(e.host.querySelector('[data-facet-search="operators"]'),active,'closing the drawer discards the previous draft editor');assert.equal(e.host.querySelector('[data-facet-search="operators"]').value,'','closing the drawer restores applied filter markup');e.c.dom.window.close();
}
console.log('PASS filter root reconciliation: unchanged roots/descendants retained; changed, disturbed and extra roots repaired; active query/facet/budget focus, value and caret restored; supplier/lead HTTP0');
