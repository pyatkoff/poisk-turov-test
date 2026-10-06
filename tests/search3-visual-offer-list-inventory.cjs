// Ordered cold-list inventory and same-render work, without supplier/lead/DOM I/O.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'../v2/visual-search');
const source=fs.readFileSync(path.join(root,'offer-list-v1.js'),'utf8');
const app=fs.readFileSync(path.join(root,'app.js'),'utf8');
const keySource=app.match(/const offerGroupKey=[^;]+;/)?.[0];
const noteSource=app.match(/const sharedOfferNote=[\s\S]*?};/)?.[0];
assert(keySource&&noteSource,'use the actual unchanged application collaborators');
const helpers=vm.runInNewContext(keySource+noteSource+'({offerGroupKey,sharedOfferNote})',{offerMetaNote:o=>o.note});
const key=helpers.offerGroupKey;
function referenceInventory(hotels,hotelOffers,offerView,offerGroupKey){
 const h=hotels.find(h=>h.id===offerView.id),all=hotelOffers(h);
 const filtered=all.filter(o=>(!offerView.departure||o.day===offerView.departure)&&(!offerView.flight||o.flight===offerView.flight)&&(!offerView.room||o.room===offerView.room)&&(!offerView.meal||o.meal===offerView.meal));
 const sorted=[...filtered].sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total:a.total-b.total||a.day.localeCompare(b.day));
 const groups=[...new Set(sorted.map(offerGroupKey))].map(key=>({key,offers:sorted.filter(o=>offerGroupKey(o)===key)}));
 return {h,all,filtered,groups};
}
function referenceScope(offers){
 let comparisons=0;
 const nights=[...new Set(offers.map(o=>String(o.nights)))],days=[...new Set(offers.map(o=>o.day))];
 const compare=(a,b)=>{comparisons++;return a.day.localeCompare(b.day);};
 const value=nights.join(' / ')+' · '+(days.length===1?offers[0].day:'Вылеты '+[...offers].sort(compare)[0].day+'/'+[...offers].sort(compare).at(-1).day);
 return {value,comparisons};
}
function groupScope(code,offers){
 const start=code.indexOf('function offerGroupScope('),end=code.indexOf('function renderOfferList(',start);
 assert(start>=0&&end>start,'group scope owner boundary');
 const work={comparisons:0},ctx={Set,String,work,nightsText:String,dateText:String,rangeText:(a,b)=>a+'/'+b,compare:(a,b)=>{work.comparisons++;return String(a).localeCompare(String(b));}};
 const measuredOwner=code.slice(start,end).replaceAll('day.localeCompare(','compare(day,');
 vm.createContext(ctx);vm.runInContext(measuredOwner+'globalThis.scopeOwner=offerGroupScope;',ctx);
 return {value:ctx.scopeOwner(offers),comparisons:work.comparisons};
}
const refinementFields=['departure','flight','room','meal'];
const refinementValue=(offer,field)=>field==='departure'?offer.day:offer[field];
function referenceRefinementCounts(all,view,options){
 const work={visits:0,predicates:0},counts={};
 for(const field of refinementFields){
  counts[field]=new Map(options[field].map(value=>[value,all.filter(offer=>{work.visits++;return refinementFields.every(current=>{work.predicates++;const selected=current===field?value:view[current];return !selected||refinementValue(offer,current)===selected;});}).length]));
 }
 return {counts,work};
}
function refinementInventory(code,all,view={departure:'',flight:'',room:'',meal:''},measure=false){
 const start=code.indexOf('function offerRefinementInventory('),end=code.indexOf('function offerGroupScope(',start);
 assert(start>=0&&end>start,'refinement value inventory owner boundary');
 const work={visits:0,predicates:0},ctx={Map,Set,offerRefinementFields:refinementFields,offerView:view,work};
 const owner=measure?code.slice(start,end)
  .replace('  const offer=all[i],values=','  work.visits++;const offer=all[i],values=')
  .replace('   const selected=offerView[offerRefinementFields[index]];','   work.predicates++;const selected=offerView[offerRefinementFields[index]];'):code.slice(start,end);
 vm.createContext(ctx);vm.runInContext(owner+'globalThis.inventoryOwner=offerRefinementInventory;',ctx);
 return {inventory:ctx.inventoryOwner(all),work};
}
function referenceRefinementValues(all,repeats=1){
 let inventory;
 for(let pass=0;pass<repeats;pass++)inventory=new Map(refinementFields.map(field=>{const values=[...new Set(all.map(offer=>refinementValue(offer,field)))];return [field,{values,hasChoice:values.length>1}];}));
 return inventory;
}
function referenceRefinementInventory(all,view){
 const inventory=referenceRefinementValues(all),options=Object.fromEntries(refinementFields.map(field=>{const values=['',...inventory.get(field).values];if(view[field]&&!values.includes(view[field]))values.push(view[field]);return [field,values];}));
 const counts=referenceRefinementCounts(all,view,options).counts;
 for(const field of refinementFields)inventory.get(field).counts=counts[field];
 return inventory;
}
function sameRefinementInventory(actual,expected){for(const field of refinementFields){assert.deepEqual([...actual.get(field).values],[...expected.get(field).values],field+' ordered values');assert.equal(actual.get(field).hasChoice,expected.get(field).hasChoice,field+' visibility');}}
function sameCounts(actual,expected){for(const field of refinementFields)assert.deepEqual([...actual.get(field).counts],[...expected[field]],field+' option counts');}
function inventory(code,rows,view){
 const start=code.indexOf('function offerListInventory('),end=code.indexOf('function renderOfferList(',start);
 assert(start>=0&&end>start);
 let keyCalls=0;const h={id:1,rows},ctx={hotels:[h],hotelOffers:hotel=>hotel?.rows||[],offerView:view,offerGroupKey:o=>{keyCalls++;return key(o);}};
 vm.createContext(ctx);vm.runInContext(code.slice(start,end),ctx);
 return {value:ctx.offerListInventory(),keyCalls,h};
}
function sameReferences(actual,expected){
 assert.strictEqual(actual.h,expected.h);assert.strictEqual(actual.all,expected.all);
 assert.equal(actual.filtered.length,expected.filtered.length);
 actual.filtered.forEach((o,i)=>assert.strictEqual(o,expected.filtered[i]));
 assert.equal(actual.groups.length,expected.groups.length);
 actual.groups.forEach((g,i)=>{assert.equal(g.key,expected.groups[i].key);assert.equal(g.offers.length,expected.groups[i].offers.length);g.offers.forEach((o,j)=>assert.strictEqual(o,expected.groups[i].offers[j]));});
}
const baseView=()=>({id:1,sort:'price',departure:'',flight:'',room:'',meal:'',open:[],limits:{}});
const offer=(i,group=i%50)=>Object.freeze({key:'offer-'+i,variant:i,room:group===0?'__proto__':group===1?'Номер <&"':'room-'+group,meal:group%2?'AI':'BB',day:'2026-10-'+(10+i%3),returnDay:'2026-10-'+(17+i%3),total:100000+i%11,nights:7,flight:i%2?'charter':'regular',operator:'fixture',placement:'2',note:'same',raw:Object.freeze({id:i})});
const rows=Object.freeze(Array.from({length:1000},(_,i)=>offer(i)));
let cases=0;
for(const sort of ['date','price'])for(const departure of ['','2026-10-11','missing'])for(const flight of ['','charter'])for(const room of ['','room-3'])for(const meal of ['','AI']){
 const view={...baseView(),sort,departure,flight,room,meal};
 const actual=inventory(source,rows,view),expected=referenceInventory([actual.h],h=>h.rows,view,key);
 sameReferences(actual.value,expected);assert.equal(actual.keyCalls,expected.filtered.length,'exactly one key per retained offer');cases++;
}
const sparse=[];sparse.length=9;sparse[2]=offer(2);sparse[6]=sparse[2];
const proto=Object.create(Array.prototype);proto[4]=offer(4);Object.setPrototypeOf(sparse,proto);Object.freeze(sparse);
for(const special of [Object.freeze([]),Object.freeze([offer(0)]),sparse,Object.freeze([offer(1),offer(0),offer(1)]),Object.freeze([{...offer(0),room:'a|b',meal:'c'},{...offer(1),room:'a',meal:'b|c'}])]){
 for(const sort of ['price','date']){
  const view={...baseView(),sort},actual=inventory(source,special,view);
  sameReferences(actual.value,referenceInventory([actual.h],h=>h.rows,view,key));cases++;
 }
}
const measured=inventory(source,rows,baseView());let beforeCalls=0;
referenceInventory([measured.h],h=>h.rows,baseView(),o=>{beforeCalls++;return key(o);});
assert.equal(beforeCalls,51000);assert.equal(measured.keyCalls,1000);
// Native sparse/inherited/initial-length filtering remains a list contract.
// The comparison-only ungrouped path and its discarded-work oracle are retired.
let sparseCases=0,seed=78231;
const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
for(let round=0;round<500;round++){
 const all=[];all.length=round%121;
 for(let i=0;i<all.length;i++)if(random()>.21)all[i]=offer(round*127+i,(round+i)%50);
 if(round%7===0&&all.length>3){const proto=Object.create(Array.prototype);proto[1]=offer(round*127+999,49);Object.setPrototypeOf(all,proto);}
 const view={...baseView(),departure:round%3?'missing':'',flight:round%4?'':'charter',room:round%5?'':'room-3',meal:round%6?'':'AI'};
 const actual=inventory(source,all,view);sameReferences(actual.value,referenceInventory([actual.h],h=>h.rows,view,key));assert.equal(actual.keyCalls,actual.value.filtered.length);sparseCases++;
}
{
 const first=offer(10001),appended=offer(10002),all=[];Object.defineProperty(all,0,{configurable:true,get(){all.push(appended);return first;}});all.length=1;
 const view=baseView(),actual=inventory(source,all,view);
 assert.equal(all.length,2,'fixture appends during native filter');assert.equal(actual.value.filtered.length,1,'native initial length is retained');assert.strictEqual(actual.value.filtered[0],first);assert.strictEqual(actual.value.groups[0].offers[0],first);assert.equal(actual.keyCalls,1);sparseCases++;
}
const keyedSort=" const keyed=filtered.length<2?filtered.map(offer=>({offer})):filtered.map((offer,index)=>({offer,index,total:offer.total,day:offer.day}));\n keyed.sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total||a.index-b.index:a.total-b.total||a.day.localeCompare(b.day)||a.index-b.index);\n const sorted=keyed.map(item=>item.offer);";
const legacySort=source.replace(keyedSort," const sorted=[...filtered].sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total:a.total-b.total||a.day.localeCompare(b.day));");assert.notEqual(legacySort,source,'cached sort-key owner boundary');
function measuredSort(code,sort){
 let seed=78231,totalReads=0,dayReads=0;const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
 const all=Array.from({length:1000},(_,id)=>{const total=Math.floor(random()*100000),day='2026-10-'+String(1+id%20).padStart(2,'0');return {id,key:'sort-'+id,variant:id,room:'same',meal:'AI',flight:'regular',get total(){totalReads++;return total},get day(){dayReads++;return day}};});
 const value=inventory(code,all,{...baseView(),sort}).value;return {totalReads,dayReads,order:Array.from(value.groups[0].offers,offer=>offer.id)};
}
const sortReads={};
for(const sort of ['price','date']){
 const before=measuredSort(legacySort,sort),after=measuredSort(source,sort);assert.deepEqual(after.order,before.order,sort+' cached keys retain exact stable raw order');assert.equal(after.totalReads,1000);assert.equal(after.dayReads,1000);assert(before.totalReads+before.dayReads>(after.totalReads+after.dayReads)*4);sortReads[sort]={before:[before.totalReads,before.dayReads],after:[after.totalReads,after.dayReads]};
}
{
 let reads=0;const single={id:1,key:'single',variant:1,room:'same',meal:'AI',flight:'regular',get total(){reads++;return 1},get day(){reads++;return '2026-10-01'}};inventory(source,[single],baseView());assert.equal(reads,0,'zero/one-row native sort still reads no sort keys');
}
const dayCases=[
 ['2026-10-12'],
 ['2026-10-12','2026-10-11'],
 ['2026-10-12','2026-10-11','2026-10-12'],
 ['2026-10-10','2026-10-12','2026-10-11'],
 ['é','e\u0301','é'],
 ['e\u0301','é','e\u0301'],
 ['Я','я','Я'],
 ['2026-10-12','2026-10-10','2026-10-12','2026-10-11'],
 ['b','a','c','a','b']
],nightCases=[[7],[7,8],[8,7,8],[7,10,14],[7,7,8,10,8]];
let scopeCases=0;
for(const days of dayCases)for(const nights of nightCases)for(let rotation=0;rotation<3;rotation++){
 const length=Math.max(days.length,nights.length),offers=Array.from({length},(_,i)=>({day:days[(i+rotation)%days.length],nights:nights[(i+rotation)%nights.length]}));
 assert.deepEqual(groupScope(source,offers).value,referenceScope(offers).value,'scope keeps native stable date range and nights order');scopeCases++;
}
const measuredScope=Array.from({length:1000},(_,i)=>({day:'2026-'+String((i*37)%997).padStart(3,'0'),nights:7+i%4}));
const previousScope=referenceScope(measuredScope),currentScope=groupScope(source,measuredScope);
assert.equal(currentScope.value,previousScope.value);assert(previousScope.comparisons>currentScope.comparisons*4,'single pass removes full date sorts');
assert.equal(groupScope(source,Array.from({length:1000},()=>({day:'same',nights:7}))).comparisons,0,'all-one-day scope performs no locale comparisons');
assert.throws(()=>{const mutated=source.replace('else if(day.localeCompare(latest)>=0)','else if(day.localeCompare(latest)>0)');for(const days of [['é','e\u0301'],['e\u0301','é']]){const offers=days.map((day,i)=>({day,nights:7+i}));assert.equal(groupScope(mutated,offers).value,referenceScope(offers).value);}},'stable equivalent last-date mutation detected');
const refinementRows=Array.from({length:1000},(_,i)=>({day:'2026-10-'+String(1+i%20).padStart(2,'0'),flight:i%2?'charter':'regular',room:'room-'+i%25,meal:['RO','BB','HB','FB','AI'][i%5]}));
const refinementOptions=Object.fromEntries(refinementFields.map(field=>[field,['',...new Set(refinementRows.map(offer=>refinementValue(offer,field)))]]));
let refinementCases=0;
for(let i=0;i<500;i++){
 const all=refinementRows.slice(0,1+i*37%120),view={...baseView(),departure:i%3?refinementRows[i%all.length].day:'',flight:i%4?refinementRows[i*3%all.length].flight:'',room:i%5?refinementRows[i*7%all.length].room:'',meal:i%6?refinementRows[i*11%all.length].meal:''};
 if(i%17===0)view.room='absent';
 const options=Object.fromEntries(refinementFields.map(field=>{const values=['',...new Set(all.map(offer=>refinementValue(offer,field)))];if(view[field]&&!values.includes(view[field]))values.push(view[field]);return [field,values];}));
 sameCounts(refinementInventory(source,all,view).inventory,referenceRefinementCounts(all,view,options).counts);refinementCases++;
}
const refinementSparse=[];refinementSparse.length=9;refinementSparse[2]=refinementRows[2];refinementSparse[6]=refinementRows[6];
const refinementProto=Object.create(Array.prototype);refinementProto[4]=refinementRows[4];Object.setPrototypeOf(refinementSparse,refinementProto);
const sparseRefinements=refinementInventory(source,refinementSparse,baseView()).inventory,sparseRefinementOptions=Object.fromEntries(refinementFields.map(field=>[field,['',...sparseRefinements.get(field).values]]));
const sparseReferenceCounts=referenceRefinementCounts(refinementSparse,baseView(),sparseRefinementOptions).counts;for(const field of refinementFields)sparseReferenceCounts[field].set(undefined,0);
sameCounts(sparseRefinements,sparseReferenceCounts);refinementCases++;
const previousRefinements=referenceRefinementCounts(refinementRows,{...baseView(),departure:refinementRows[2].day,flight:'regular',room:'room-7',meal:'AI'},refinementOptions);
const refinementView={...baseView(),departure:refinementRows[2].day,flight:'regular',room:'room-7',meal:'AI'},currentRefinements=refinementInventory(source,refinementRows,refinementView,true);
sameCounts(currentRefinements.inventory,previousRefinements.counts);assert.equal(previousRefinements.work.visits,56000);assert.equal(currentRefinements.work.visits,1000);assert.equal(previousRefinements.work.predicates,62670);assert.equal(currentRefinements.work.predicates,4000);
for(const values of [refinementRows.slice(0,120),refinementSparse,[]])sameRefinementInventory(refinementInventory(source,values,baseView()).inventory,referenceRefinementInventory(values,baseView()));
let refinementValueReads=0;
const trackedRefinementRows=Array.from({length:1000},(_,i)=>{const day='2026-10-'+String(1+i%20).padStart(2,'0'),flight=i%2?'charter':'regular',room='room-'+i%25,meal=['RO','BB','HB','FB','AI'][i%5];return {get day(){refinementValueReads++;return day},get flight(){refinementValueReads++;return flight},get room(){refinementValueReads++;return room},get meal(){refinementValueReads++;return meal}};});
referenceRefinementValues(trackedRefinementRows,3);const firstRenderValueReads=refinementValueReads;refinementValueReads=0;
referenceRefinementValues(trackedRefinementRows,2);const repeatedRenderValueReads=refinementValueReads;refinementValueReads=0;
const trackedCurrent=refinementInventory(source,trackedRefinementRows,refinementView);sameRefinementInventory(trackedCurrent.inventory,referenceRefinementInventory(refinementRows,refinementView));const currentValueReads=refinementValueReads;
assert.equal(firstRenderValueReads,12000);assert.equal(repeatedRenderValueReads,8000);assert.equal(currentValueReads,4000);
assert(source.includes('<select id="offer-departure"><option value="">Все даты</option></select>'),'mount defers refinement enumeration to the render owner');
// Render both actual owner variants against the same deterministic DOM boundary.
// The reference restores the old repeated note, heading and refinement inventory work.
const hoisted=" const commonNote=reuseRows?currentCommonNote:groups.length?sharedOfferNote(all):'';\n";
assert(source.includes(hoisted));
const legacyNotes=source.replace(hoisted," const commonNote=reuseRows?currentCommonNote:'';\n")
 .replace('entry=offerRowEntry(o,commonNote);','entry=offerRowEntry(o,sharedOfferNote(all));');
const legacyCounts=legacyNotes
 .replace('  const counts=currentRefinementInventory.get(field).counts;\n','')
 .replace('   option.textContent=`${option.dataset.baseLabel} · ${offerCountText(counts.get(option.value)||0)}`;','   const count=all.filter(o=>matchesOfferRefinements(o,{...offerView,[field]:option.value})).length;\n   option.textContent=`${option.dataset.baseLabel} · ${offerCountText(count)}`;')
 .replace('   option.textContent=comparing?option.dataset.baseLabel:`${option.dataset.baseLabel} · ${offerCountText(counts.get(option.value))}`;','   const count=all.filter(o=>matchesOfferRefinements(o,{...offerView,[field]:option.value})).length;\n   option.textContent=comparing?option.dataset.baseLabel:`${option.dataset.baseLabel} · ${offerCountText(count)}`;');
const legacyRenderer=legacyCounts.replace('${offerGroupScope(offers)}</small>','${[...new Set(offers.map(o=>nightsText(o.nights)))].join(\' / \')} · ${[...new Set(offers.map(o=>o.day))].length===1?dateText(first.day):\'Вылеты \'+rangeText([...offers].sort((a,b)=>a.day.localeCompare(b.day))[0].day,[...offers].sort((a,b)=>a.day.localeCompare(b.day)).at(-1).day)}</small>');
const legacyValueRenderer=source
 .replace('  const hasChoice=currentRefinementInventory.get(field).hasChoice;','  const hasChoice=new Set(all.map(o=>field===\'departure\'?o.day:o[field])).size>1;')
 .replace("  const select=$('#offer-'+field),values=[...currentRefinementInventory.get(field).values];","  const select=$('#offer-'+field),values=[...new Set(all.map(o=>field==='departure'?o.day:o[field]))];");
assert.notEqual(legacyValueRenderer,source,'refinement value inventory legacy boundary');
const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const unesc=v=>v.replace(/&quot;|&#39;|&lt;|&gt;|&amp;/g,c=>({'&amp;':'&','&lt;':'<','&gt;':'>','&quot;':'"','&#39;':"'"}[c]));
function render(code,all,view,shortlist=false,reset=false,mount=false,precomputed){
 const dom=new Map(),events=[];let noteCalls=0,noteVisits=0,keyCalls=0,hotelCalls=0,rowMarkupCalls=0,appendCalls=0,currentRows=all,mounted=!mount;
 const node=name=>{if(name==='#offer-count'&&!mounted)return null;if(!dom.has(name)){
  let html='',heading=null;const classes=new Set(),style={},label={hidden:false},n={hidden:false,open:false,textContent:'',value:'',options:[{value:'',textContent:'Any',dataset:{}}],classList:{contains:c=>classes.has(c),toggle:(c,on)=>on?classes.add(c):classes.delete(c)},style:{setProperty:(k,v)=>{style[k]=v;}},closest:()=>label};
  if(name.startsWith('#group-')){const scope={hidden:false},minimum={hidden:false},arrowClasses=new Set(),arrow={classList:{toggle:(c,on)=>on?arrowClasses.add(c):arrowClasses.delete(c)}};heading={dataset:{value:name.slice(7)},matches:s=>s==='[data-action="offer-group"]',querySelector:s=>s==='.offer-group-scope'?scope:s==='.offer-group-min>strong'?minimum:s==='.rotate-arrow'?arrow:null,setAttribute:(key,value)=>{if(key==='aria-expanded')heading.expanded=value},focus:()=>{heading.focused=true},scope,minimum,arrowClasses,expanded:'false',focused:false};n.previousElementSibling=heading;}
  Object.defineProperty(n,'innerHTML',{get:()=>html,set:v=>{html=v;if(name==='#modal-body')mounted=true;if(name.startsWith('#offer-'))n.options=[...v.matchAll(/<option value="([^"]*)"[^>]*>(.*?)<\/option>/g)].map(m=>({value:unesc(m[1]),textContent:unesc(m[2]),dataset:{}}));}});
  n.snapshot=()=>({html,hidden:n.hidden,open:n.open,text:n.textContent,value:n.value,options:n.options,classes:[...classes],style,label,heading:heading&&{expanded:heading.expanded,focused:heading.focused,scopeHidden:heading.scope.hidden,minimumHidden:heading.minimum.hidden,arrowClasses:[...heading.arrowClasses]}});dom.set(name,n);
 }return dom.get(name);};
 const context={$:node,$$:()=>[],byId:id=>node('#'+id),hotels:[{id:1,resort:'Resort'}],hotelOffers:()=>{hotelCalls++;return currentRows;},offerGroupKey:o=>{keyCalls++;return key(o);},offerView:structuredClone(view),offerRefinementFields:['departure','flight','room','meal'],innerWidth:1440,optionalShortlistEnabled:shortlist,state:{search:{origin:'Москва',from:'2026-10-10',minNights:7}},mealNames:{},esc,
  paintGeneratedRoots:(container,entries)=>{container.innerHTML=entries.map(entry=>entry.markup).join('');},appendGeneratedRoots:(container,entries)=>{appendCalls++;container.innerHTML=container.innerHTML.replace(/<button class="text-button group-more"[\s\S]*?<\/button>$/,'')+entries.map(entry=>entry.markup).join('');return true;},
  sharedOfferNote:input=>{assert.strictEqual(input,currentRows,'shared note uses current all, not filtered/group rows');noteCalls++;noteVisits+=input.length;return helpers.sharedOfferNote(input);},offerMetaNote:o=>o.note,
  mealLabel:o=>o.meal,flightLabel:o=>o.flight,needsRefresh:()=>false,cardPriceNote:()=>'',dateText:String,nightsText:String,offerCountText:String,money:String,rangeText:(a,b)=>a+'/'+b,durationText:()=>'',guestsText:()=>'',icon:()=>'',offerActionLabel:()=>{rowMarkupCalls++;return '';},offerSearchContext:()=>'',operatorBadge:String,selectionStepsHTML:()=>'',rememberUIRoute:()=>events.push('route'),renderComparisonFooter:()=>events.push('comparison'),setComparisonQuotes:values=>events.push(['quotes',values.map(o=>o.key)])};
 const sandbox={window:{}};vm.createContext(sandbox);vm.runInContext(code,sandbox);
 const api=sandbox.window.AnyTourOfferList.create(context);api.renderOfferList(reset,precomputed);
 const snapshot=()=>JSON.parse(JSON.stringify({dom:[...dom].map(([k,n])=>[k,n.snapshot()]),events,view:context.offerView}));
 return {snapshot:snapshot(),noteCalls,noteVisits,keyCalls,hotelCalls,rowMarkupCalls,currentSnapshot:snapshot,
  paginate:(groupKey,count,incremental=true)=>{const before={hotelCalls,rowMarkupCalls},renderPage=shown=>incremental&&api.renderMoreGroup?api.renderMoreGroup(groupKey,shown,{}):api.renderOfferList(false);let shown=4;for(let limit=12;limit<count;limit+=8){context.offerView.limits[groupKey]=Math.min(count,limit);renderPage(shown);shown=limit;}if(count>4){context.offerView.limits[groupKey]=count;renderPage(shown);}return {hotelCalls:hotelCalls-before.hotelCalls,rowMarkupCalls:rowMarkupCalls-before.rowMarkupCalls};},
  toggle:(groupKey,incremental=true)=>{const before={hotelCalls,rowMarkupCalls,appendCalls};context.offerView.open=context.offerView.open.includes(groupKey)?context.offerView.open.filter(key=>key!==groupKey):[...context.offerView.open,groupKey];if(!incremental||api.renderOfferGroup?.(groupKey)!==true)api.renderOfferList(false);return {hotelCalls:hotelCalls-before.hotelCalls,rowMarkupCalls:rowMarkupCalls-before.rowMarkupCalls,appendCalls:appendCalls-before.appendCalls};},
  rerender:(nextRows,changes={})=>{currentRows=nextRows;Object.assign(context.offerView,changes);const previousCalls=hotelCalls;api.renderOfferList(false);return {snapshot:snapshot(),hotelCalls:hotelCalls-previousCalls};}};
}
// Optional before/after proof uses the old owner only when explicitly supplied.
// CI remains self-contained; its independent reference algorithms stay above.
const compareIndex=process.argv.indexOf('--compare'),priorSource=compareIndex>=0?fs.readFileSync(process.argv[compareIndex+1],'utf8'):null;
function retainedListSnapshot(snapshot){
 const {mode,...view}=snapshot.view;
 return {...snapshot,view,dom:snapshot.dom.filter(([key])=>key!=='#modal'&&key!=='#offer-comparison-dates').map(([key,node])=>[key,{...node,html:node.html.replace(/<button class="text-button compare-tour-link" data-action="compare-tour" data-key="[^"]*">Сравнить на эти даты<\/button>/g,'')}]),events:snapshot.events.filter(event=>!Array.isArray(event)||event[0]!=='quotes')};
}
let renders=0;
for(const all of [rows.slice(0,100),rows.slice(0,1),[],rows.slice(0,25).map((o,i)=>({...o,note:i%2?'different':'same'}))])for(const sort of ['price','date'])for(const departure of ['','missing'])for(const reset of [false,true]){
 const view={...baseView(),sort,departure},before=render(legacyRenderer,all,view,true,reset),after=render(source,all,view,true,reset);
 assert.deepEqual(after.snapshot,before.snapshot,'render output and view state unchanged');
 assert.deepEqual(after.snapshot,render(legacyValueRenderer,all,view,true,reset).snapshot,'single refinement inventory preserves full render output and view state');
 assert.equal(after.noteCalls,before.noteCalls?1:0,'one same-render note, zero for empty list');renders++;
 if(priorSource){
  const previous=render(priorSource,all,{...view,mode:'list'},true,reset);
  assert.deepEqual(retainedListSnapshot(after.snapshot),retainedListSnapshot(previous.snapshot),'before/after list markup, refinements, state and route output');
  assert.deepEqual([after.noteCalls,after.noteVisits,after.keyCalls],[previous.noteCalls,previous.noteVisits,previous.keyCalls],'retained same-render list work');
 }
}
const before=render(legacyRenderer,rows,baseView()),after=render(source,rows,baseView());
assert.equal(before.noteCalls,200);assert.equal(after.noteCalls,1);assert.equal(before.noteVisits,200000);assert.equal(after.noteVisits,1000);
assert.deepEqual(after.snapshot,before.snapshot);
// Mount the actual public cold owner. A fresh render owns one inventory; later
// prices and refinement changes must recalculate it instead of reusing old rows.
let invocationCases=0;
for(const all of [[],rows.slice(0,1),rows.slice(0,2),rows]){
 const current=render(source,all,baseView(),false,false,true);
 assert.equal(current.hotelCalls,1,'mount and list share this invocation inventory');
 const restored=render(source,all,baseView(),false,false,true,all);assert.equal(restored.hotelCalls,0,'warm restored rows skip the duplicate owner inventory');assert.deepEqual(restored.snapshot,current.snapshot,'warm restored inventory preserves exact public DOM/view/route output');
 if(priorSource){const previous=render(priorSource,all,baseView(),false,false,true);assert.equal(previous.hotelCalls,2,'measured prior mount plus render');assert.deepEqual(current.snapshot,previous.snapshot,'public mount output and view unchanged');}
 const updated=all.map(o=>({...o,total:o.total+765432}));
 const next=current.rerender(updated,{flight:'regular'}),fresh=render(source,updated,{...baseView(),flight:'regular'},false,false,true);
 assert.equal(next.hotelCalls,1,'refinement reads fresh normalized rows once');
 for(const selector of ['#all-offers-list','#offer-count','#offer-flight'])assert.deepEqual(next.snapshot.dom.find(([key])=>key===selector),fresh.snapshot.dom.find(([key])=>key===selector),'later result/refinement matches fresh mount: '+selector);
 invocationCases+=2;
}
console.log(`PASS cold list invocation: ${invocationCases} actual mount/refinement/fresh-result states; mount hotelOffers calls 2→1; raw current rows and updated prices retained`);
const paginationRows=Array.from({length:500},(_,i)=>({...offer(20000+i),room:'one-room',meal:'AI'}));
const paginationView=baseView(),paginationKey=key(paginationRows[0]),fullPagination=render(source,paginationRows,paginationView,false,false,true),incrementalPagination=render(source,paginationRows,paginationView,false,false,true);
const fullPaginationWork=fullPagination.paginate(paginationKey,500,false),incrementalPaginationWork=incrementalPagination.paginate(paginationKey,500,true);
assert.equal(fullPagination.rowMarkupCalls+fullPaginationWork.rowMarkupCalls,15876,'500-row full-render reference rebuilds every visible row on each page');
assert.equal(fullPagination.hotelCalls+fullPaginationWork.hotelCalls,63,'500-row full-render reference rebuilds the full inventory for all 63 renders');
assert.equal(incrementalPagination.rowMarkupCalls+incrementalPaginationWork.rowMarkupCalls,500,'incremental owner builds each visible row once');
assert.equal(incrementalPagination.hotelCalls+incrementalPaginationWork.hotelCalls,1,'incremental owner retains only the immediately preceding full inventory');
assert.deepEqual(incrementalPagination.currentSnapshot(),fullPagination.currentSnapshot(),'incremental and repeated full-render pagination finish with exact DOM/view/route output');
console.log(`PASS cold group pagination: 500 rows, inventory visits 31500→500, row markups 15876→500; exact final public-owner DOM/view/route output`);
// Approved Site100 exposes exact offers immediately. Room/meal groups and their
// lazy pagination remain covered separately by hotel-details-rendering/browser.
assert.equal(fullPagination.currentSnapshot().dom.find(([selector])=>selector==='#all-offers-list')[1].html.includes('offer-group-heading'),false,'flat list does not hide exact offers behind a group disclosure');
assert.equal(incrementalPagination.currentSnapshot().dom.some(([selector])=>selector.startsWith('#group-')),false,'flat offer list has no obsolete group bodies');
assert.equal(incrementalPagination.currentSnapshot().dom.find(([selector])=>selector==='#all-offers-list')[1].html.match(/data-offer-key=/g).length,500,'every paginated exact offer remains visible');
console.log('PASS approved flat offer list: exact offers shown without room-group disclosures; independent hotel room groups retained');
const forbiddenRefinementInventory=source.replace('function offerRefinementInventory(all){','function offerRefinementInventory(all){throw new Error("required list refinement inventory");');
assert.throws(()=>render(forbiddenRefinementInventory,rows,baseView(),true),'list rendering still needs refinement inventory');
const reversed=source.replace('groups.push(group)','groups.unshift(group)');assert.notEqual(reversed,source);
const reversedResult=inventory(reversed,rows,baseView());
assert.throws(()=>sameReferences(reversedResult.value,referenceInventory([reversedResult.h],h=>h.rows,baseView(),key)),'reversed group order mutation detected');
const copied=source.replace('group.offers.push(offer)','group.offers.push({...offer})');assert.notEqual(copied,source);
const copiedResult=inventory(copied,rows,baseView());
assert.throws(()=>sameReferences(copiedResult.value,referenceInventory([copiedResult.h],h=>h.rows,baseView(),key)),'raw identity mutation detected');
console.log(`PASS cold offer-list inventory: ${cases} grouping, ${scopeCases} heading, ${refinementCases} refinement, ${sparseCases} sparse/inherited/initial-length list reference cases; ${renders} render states; key calls ${beforeCalls}→${measured.keyCalls}; list sort total/day reads price ${sortReads.price.before.join('/')}→${sortReads.price.after.join('/')}, date ${sortReads.date.before.join('/')}→${sortReads.date.after.join('/')}; heading localeCompare ${previousScope.comparisons}→${currentScope.comparisons}; refinement visits ${previousRefinements.work.visits}→${currentRefinements.work.visits}, predicates ${previousRefinements.work.predicates}→${currentRefinements.work.predicates}; refinement value reads first ${firstRenderValueReads}→${currentValueReads}, repeated ${repeatedRenderValueReads}→${currentValueReads}; shared note calls ${before.noteCalls}→${after.noteCalls}, visits ${before.noteVisits}→${after.noteVisits}; supplier/lead HTTP 0`);
