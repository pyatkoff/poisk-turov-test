'use strict';
(function(){
// Owns facet markup, section navigation and retained filter editors. Matching
// and draft application stay in the host; replaced inputs are read on demand.
function create({$,$$,esc,icon,state,data,editingFilterModel,currentFilterBudgetEdit,readBudgetFields,budgetLabel,normalizeSearch,hotelCountText,countMatchingHotels,countFacetOptions,amenityNames,searchKey,budgetScale,mealNames,ratingValue,hotelPlaces,operators,syncFilterResetState,showFilterBudgetValidity,getHotels,getFilterDraft,getViewportWidth}){
const expandedFacets=new Set(),facetQueries=new Map();
let facetQueryScope='';
const expandedFilterSections=new Set();
function syncFilterSections(){
 const f=editingFilterModel().filters;
 $$('#filters .filter-group').forEach((group,i)=>{
  if(group.querySelector('#hotel-query'))return;
  const heading=group.querySelector(':scope>h4');if(!heading)return;
  const key=heading.textContent;group.dataset.filterSection=key;
  let button=group.querySelector(':scope>.filter-section-toggle');
  if(!button){
   const body=document.createElement('div');body.className='filter-section-values';
   [...group.children].filter(child=>child!==heading).forEach(child=>body.append(child));
   button=document.createElement('button');button.type='button';button.className='filter-section-toggle';button.dataset.action='toggle-filter-section';
   button.innerHTML=`<span class="filter-section-title">${esc(key)}</span><span class="filter-section-value"></span><span class="filter-section-chevron" aria-hidden="true">⌄</span>`;
   group.append(button,body);
  }
  const body=group.querySelector(':scope>.filter-section-values');body.id='filter-values-'+i;button.setAttribute('aria-controls',body.id);
  const selected=[...group.querySelectorAll('.check-row input:checked')].map(input=>input.closest('label').querySelector('span').textContent);
  let summary=selected.slice(0,2).join(' · ')+(selected.length>2?` · ещё ${selected.length-2}`:''),active=selected.length>0;
  if(group.querySelector('#min-price')){summary=currentFilterBudgetEdit(f)&&!readBudgetFields($('#min-price'),$('#max-price')).valid?'Проверьте сумму':budgetLabel(f);active=f.min>0||f.max!==null;}
  else if(group.querySelector('.star-options')){summary=f.stars.map(n=>n+' ★').join(' · ')||'Любая';active=f.stars.length>0;}
  else if(!summary)summary={Питание:'Любое',Курорт:'Любой',Туроператор:'Любой','Оценка гостей':'Любая'}[key]||'Не выбрано';
  button.querySelector('.filter-section-value').textContent=summary;button.classList.toggle('has-selection',active);
  const open=expandedFilterSections.has(key);button.setAttribute('aria-expanded',String(open));group.classList.toggle('section-open',open);
 });
}
function setFilterSectionOpen(group,open){
 if(!group?.dataset.filterSection)return;
 const key=group.dataset.filterSection;if(open)expandedFilterSections.add(key);else expandedFilterSections.delete(key);
 group.classList.toggle('section-open',open);group.querySelector('.filter-section-toggle').setAttribute('aria-expanded',String(open));
}
function compareMealLabels(a,b){
 const order=['Всё включено','Ультра всё включено','Завтраки','Полупансион','Полный пансион','Без питания'],rank=label=>order.includes(label)?order.indexOf(label):100;
 return rank(a)-rank(b)||a.localeCompare(b,'ru');
}
function comparePopularFacetOptions(a,b){return b[2]-a[2]||a[1].localeCompare(b[1],'ru');}
function checkRows(group,options){const model=editingFilterModel(),counts=countFacetOptions(model,group,options.map(([value])=>value)),selected=model.filters[group]||[],ordered=options.map(([val,label],index)=>[val,label,counts.get(val),index]).sort((a,b)=>group==='meals'?compareMealLabels(a[1],b[1]):['resorts','operators'].includes(group)?comparePopularFacetOptions(a,b):Number(selected.includes(b[0]))-Number(selected.includes(a[0]))||Number(b[2]>0)-Number(a[2]>0)||a[3]-b[3]);if(options.length<=7)return fullCheckRows(group,ordered);const label={resorts:'Найти курорт',operators:'Найти туроператора',meals:'Найти питание'}[group]||'Найти вариант';return `<div class="facet-options" data-facet-options="${group}"><div class="facet-search"><label><span class="sr-only">${label} в списке фильтра</span><input type="search" data-facet-search="${group}" value="${esc(facetQueries.get(group)||'')}" placeholder="${label}" autocomplete="off"></label><button type="button" class="icon-button" data-action="clear-facet-query" aria-label="Очистить поиск в списке" hidden>${icon('x')}</button></div><div class="facet-picked" role="group" aria-label="Выбрано в этом разделе"></div><p class="facet-search-status" aria-live="polite" hidden></p>${fullCheckRows(group,ordered.slice(0,7))}<details class="facet-more" data-facet="${group}" ${expandedFacets.has(group)?'open':''}><summary></summary>${fullCheckRows(group,ordered.slice(7))}</details></div>`;}
function applyFacetSearch(host){
 const group=host.dataset.facetOptions,q=normalizeSearch(facetQueries.get(group)||''),rows=[...host.querySelectorAll('.check-row')].map(row=>({row,input:row.querySelector('input'),label:row.querySelector('span').textContent,count:Number(row.querySelector('small').textContent)})),more=host.querySelector('details'),focused=document.activeElement;
 rows.forEach(({row},i)=>{if(row.dataset.facetOrder===undefined)row.dataset.facetOrder=String(i)});
 rows.sort((a,b)=>group==='meals'?Number(a.row.dataset.facetOrder)-Number(b.row.dataset.facetOrder):['resorts','operators'].includes(group)?b.count-a.count||a.label.localeCompare(b.label,'ru'):Number(b.input.checked)-Number(a.input.checked)||Number(b.row.dataset.available==='true')-Number(a.row.dataset.available==='true')||Number(a.row.dataset.facetOrder)-Number(b.row.dataset.facetOrder));
 let found=0,availableCount=0;const primaryRows=[],moreRowsToPlace=[];
 for(const {row,input,label} of rows){const available=row.dataset.available==='true'||input.checked,matches=available&&(!q||normalizeSearch(label).includes(q));row.hidden=!matches;if(matches)found++;
  (available&&availableCount++<7?primaryRows:moreRowsToPlace).push(row);
 }
 // Reconcile the same row suffixes, preserving any unowned prefix nodes.
 const place=(parent,ordered,before=null)=>{for(let i=ordered.length-1;i>=0;i--){const row=ordered[i];if(row.parentNode!==parent||row.nextSibling!==before)parent.insertBefore(row,before);before=row;}};
 place(host,primaryRows,more);place(more,moreRowsToPlace);
 if(focused?.isConnected&&rows.some(({row})=>row.contains(focused))&&document.activeElement!==focused)focused.focus({preventScroll:true});
 const searchBox=host.querySelector('.facet-search');searchBox.hidden=availableCount<=7&&!q&&!searchBox.contains(focused);
 host.classList.toggle('facet-searching',!!q);
 const moreRows=rows.filter(({row})=>more.contains(row)),availableMore=moreRows.filter(({row,input})=>row.dataset.available==='true'||input.checked).length;
 more.querySelector('summary').textContent=`Показать ещё ${availableMore}`;more.open=!!q||expandedFacets.has(group);more.hidden=availableMore===0||!!q&&!moreRows.some(({row})=>!row.hidden);
 const status=host.querySelector('.facet-search-status');status.hidden=!q;status.textContent=found?`Найдено в списке: ${found}`:'Нет доступных совпадений. Попробуйте другое название.';
 const selected=editingFilterModel().filters[group]||[],picked=host.querySelector('.facet-picked');

 const labels=new Map(rows.map(({input,label})=>[input.value,label]));
 picked.hidden=!selected.length;
 picked.innerHTML=selected.length?`<span class="facet-picked-label">Выбрано: ${selected.length}</span><div class="facet-picked-items">${selected.map(value=>`<button type="button" class="facet-picked-item" data-action="remove-facet-choice" data-value="${esc(value)}" aria-label="Убрать из выбора: ${esc(labels.get(value)||value)}"><span>${esc(labels.get(value)||value)}</span>${icon('x')}</button>`).join('')}</div>`:'';
 host.querySelector('[data-action="clear-facet-query"]').hidden=!facetQueries.get(group);}
document.addEventListener('input',event=>{const group=event.target.dataset?.facetSearch;if(group){facetQueries.set(group,event.target.value);applyFacetSearch(event.target.closest('.facet-options'));settleFilterRoots($('#filters'));}});
document.addEventListener('toggle',event=>{const group=event.target.dataset?.facet;if(group&&!facetQueries.get(group)){if(event.target.open)expandedFacets.add(group);else expandedFacets.delete(group);}},true);
function filterCheckRowHTML(attributes,label,count,selected){const available=count>0||selected;return `<label class="check-row" data-available="${available}" ${available?'':'hidden'}><input type="checkbox" ${attributes} ${selected?'checked':''}><span>${esc(label)}</span><small aria-label="${hotelCountText(count)}">${count}</small></label>`;}
function fullCheckRows(group,options){const model=editingFilterModel();return options.map(([val,label,knownCount])=>filterCheckRowHTML(`data-filter="${group}" value="${esc(val)}"`,label,knownCount??countMatchingHotels({...model,filters:{...model.filters,[group]:[val]}}),model.filters[group].includes(val))).join('');}
function amenityFilterGroups(hs,f){
 const facts=new Map();hs.forEach(h=>(h.amenities||[]).forEach(a=>{if(a?.filterable===false)return;facts.set(a.key,a);amenityNames.set(a.key,a);}));
 for(const key of f.amenities||[])if(!facts.has(key)&&amenityNames.has(key))facts.set(key,amenityNames.get(key));
 const counts=countFacetOptions(editingFilterModel(),'amenities',[...facts.keys()]);
 const groups=new Map();for(const fact of facts.values()){if(!groups.has(fact.groupId))groups.set(fact.groupId,{name:fact.group,items:[]});groups.get(fact.groupId).items.push(fact);}
 return [...groups.values()].map(group=>`<div class="filter-group"><h4>${esc(group.name)}</h4>${group.items.map(a=>{const selected=(f.amenities||[]).includes(a.key);return filterCheckRowHTML(`data-filter="amenities" value="${esc(a.key)}"`,a.label,counts.get(a.key),selected);}).join('')}</div>`).join('');
}
function syncAvailableFilterGroups(){$$('#filters .filter-group').forEach(group=>{const rows=[...group.querySelectorAll('.check-row')];if(rows.length)group.hidden=rows.every(row=>row.dataset.available!=='true'&&!row.querySelector('input')?.checked);});syncFilterSections();}
function updateFacetCounts(ratingCount){const model=editingFilterModel(),inputs=$$('[data-filter],[data-filter-bool]'),counts=new Map(),grouped=new Map(),selectedInventory={count:null};
 for(const input of inputs){const group=input.dataset.filter;if(!group)continue;if(!grouped.has(group))grouped.set(group,[]);grouped.get(group).push(input.value);}
 const starOptions=filterStarOptions(model);if(starOptions.length)grouped.set('stars',starOptions);
 for(const [group,values] of grouped)counts.set(group,countFacetOptions(model,group,values,selectedInventory.count===null?selectedInventory:null));
 inputs.forEach(input=>{const key=input.dataset.filter||input.dataset.filterBool,value=key==='amenities'?[...new Set([...(model.filters.amenities||[]),input.value])]:input.dataset.filter?[input.value]:true,count=key==='rating'&&ratingCount!==undefined?ratingCount:counts.get(key)?.get(key==='stars'?Number(input.value):input.value)??countMatchingHotels({...model,filters:{...model.filters,[key]:value}}),row=input.closest('.check-row'),label=row?.querySelector('small'),available=count>0||input.checked;if(label){label.textContent=count;label.setAttribute('aria-label',hotelCountText(count))}if(row){row.dataset.available=String(available);if(!row.closest('.facet-options'))row.hidden=!available;}});$$('[data-facet-options]').forEach(applyFacetSearch);updateFilterStars(counts.get('stars'));syncAvailableFilterGroups();renderFilterNavigation();settleFilterRoots($('#filters'));return selectedInventory.count;}
let renderedFilterContext=null,filterEditorLease=null;
const filterEditorSelector='[data-facet-search],#hotel-query,#min-price,#max-price,#price-range';
$('#filters').addEventListener('focusin',event=>{filterEditorLease=event.target.matches?.(filterEditorSelector)?event.target:null;});
const filterRootBindings=new WeakMap();
function filterRootBinding(host){
 let binding=filterRootBindings.get(host);
 if(binding)return binding;
 binding={markup:new WeakMap(),dirty:new WeakSet(),structureDirty:false};
 const mark=records=>{for(const record of records){if(record.target===host){binding.structureDirty=true;continue;}let root=record.target.nodeType===1?record.target:record.target.parentElement;while(root&&root.parentNode!==host)root=root.parentElement;if(root&&root.parentNode===host)binding.dirty.add(root);}};
 binding.observer=new MutationObserver(mark);binding.mark=mark;binding.observer.observe(host,{subtree:true,childList:true,attributes:true,characterData:true});filterRootBindings.set(host,binding);return binding;
}
function filterRootMarkup(node){return node.nodeType===1?node.outerHTML:`${node.nodeType}:${node.nodeValue}`;}
function settleFilterRoots(host){const binding=filterRootBindings.get(host);if(binding){binding.observer.takeRecords();binding.structureDirty=false;}}
function reconcileFilterRoots(host,fragment,binding,preserved=null){
 const fresh=[...fragment.childNodes],current=[...host.childNodes],desired=[];
 for(let index=0;index<fresh.length;index++){
 const generated=fresh[index],existing=current[index],keep=preserved?.index===index?preserved.node:existing,markup=preserved?.index===index&&preserved.markup||filterRootMarkup(generated);
  const active=preserved?.index===index&&keep===preserved.node;
  const node=active&&preserved.transferred?keep:keep&&keep.parentNode===host&&(!binding.structureDirty||active)&&!binding.dirty.has(keep)&&(active||binding.markup.get(keep)===markup)?keep:generated.cloneNode(true);
  binding.markup.set(node,markup);binding.dirty.delete(node);desired.push(node);
 }
 let cursor=host.firstChild;
 for(const node of desired){
  if(node===preserved?.node&&node!==cursor&&node.parentNode===host)while(cursor&&cursor!==node){const next=cursor.nextSibling;cursor.remove();cursor=next;}
  if(node===cursor)cursor=cursor.nextSibling;else host.insertBefore(node,cursor);
 }
 while(cursor){const next=cursor.nextSibling;cursor.remove();cursor=next;}
 binding.structureDirty=false;return binding;
}
function paintFilters(markup,filters){
 const host=$('#filters'),focused=document.activeElement,focusedEditor=host.contains(focused)&&focused.matches?.(filterEditorSelector)?focused:null;
 const leased=!focusedEditor&&getFilterDraft()&&filterEditorLease?.isConnected&&host.contains(filterEditorLease)?filterEditorLease:null,active=focusedEditor||leased,scope=searchKey(state.search);
 const facet=active?.dataset.facetSearch;
 const group=active&&host.contains(active)&&(facet||['hotel-query','min-price','max-price','price-range'].includes(active.id))?active.closest('.filter-group'):null;
 const binding=filterRootBinding(host);binding.mark(binding.observer.takeRecords());
 const sameScope=renderedFilterContext?.scope===scope,sameModel=renderedFilterContext?.filters===filters;
 if(renderedFilterContext&&(!sameScope||!sameModel&&!getFilterDraft()))binding.structureDirty=true;
 const template=document.createElement('template');template.innerHTML=markup;
 let preserved=null;
 // A mobile draft wrapper may be refreshed while a progressive provider result
 // is folded into the same search. Once the drawer closes, object identity again
 // prevents a cancelled draft from leaking into the applied filter model.
 if(group&&sameScope&&(sameModel||getFilterDraft())){
  const replacement=template.content.querySelector(facet?`[data-facet-search="${facet}"]`:active.id==='hotel-query'?'#hotel-query':'#min-price')?.closest('.filter-group');
  if(replacement&&replacement.parentNode===template.content){
   const replacementEditor=replacement.querySelector(facet?`[data-facet-search="${facet}"]`:`#${active.id}`),expected=filterRootMarkup(replacement);
   if(replacementEditor){replacementEditor.replaceWith(active);preserved={node:replacement,index:[...template.content.childNodes].indexOf(replacement),markup:expected,transferred:true};}
  }
 }
 reconcileFilterRoots(host,template.content,binding,preserved);
 renderedFilterContext={filters,scope};$$('[data-facet-options]').forEach(applyFacetSearch);syncAvailableFilterGroups();
 if(preserved&&group&&(preserved.transferred||leased||!host.contains(active))){
  const restored=host.contains(active)?active:host.querySelector(facet?`[data-facet-search="${facet}"]`:`#${active.id}`);
  if(restored){restored.value=active.value;restored.focus({preventScroll:true});if(active.selectionStart!==null)try{restored.setSelectionRange(active.selectionStart,active.selectionEnd,active.selectionDirection)}catch{}}
 }
 // Dynamic facet/count presentation is owned by this painter, not an external disturbance.
 settleFilterRoots(host);
}
function renderFilterNavigation(){
 const select=$('#filter-section-jump');
 const headings=$$('#filters .filter-group:not([hidden])>h4');
 headings.forEach((heading,i)=>{heading.id='filter-heading-'+i;heading.tabIndex=-1;});
 select.innerHTML='<option value="">К разделу фильтров…</option><option value="drawer-context">Выбранные условия</option>'+headings.map(h=>`<option value="${h.id}">${esc(h.textContent)}</option>`).join('');
}
function jumpToFilterSection(id){
 const panel=$('#filter-panel');let target=document.getElementById(id);if(!target||!panel.contains(target))return;
 const group=target.closest('.filter-group');if(getViewportWidth()<=1100&&group?.dataset.filterSection){setFilterSectionOpen(group,true);target=group.querySelector('.filter-section-toggle');}
 const header=panel.querySelector('.filter-top');
 const top=panel.scrollTop+target.getBoundingClientRect().top-panel.getBoundingClientRect().top-header.getBoundingClientRect().height-12;
 panel.scrollTop=Math.max(0,top);if(!target.matches('button'))target.tabIndex=-1;target.focus({preventScroll:true});
 $('#filter-section-jump').value='';
}
function filterStarOptions(model){const f=model.filters,hs=getHotels().filter(h=>h.country===state.search.country);return [...new Set([...hs.map(h=>h.stars).filter(n=>Number.isInteger(n)&&n>=1&&n<=5),...f.stars])].sort((a,b)=>a-b);}
function filterStarButtons(model,counts=null){const f=model.filters,starOptions=counts?[...counts.keys()]:filterStarOptions(model);counts??=countFacetOptions(model,'stars',starOptions);return starOptions.map(n=>{const count=counts.get(n),selected=f.stars.includes(n);return count>0||selected?`<button type="button" data-action="star" data-value="${n}" aria-label="${n} ${n===1?'звезда':n<5?'звезды':'звёзд'} — ${hotelCountText(count)}" aria-pressed="${selected}" class="${selected?'active':''}"><span>${n} ★</span><small aria-hidden="true">${count}</small></button>`:''}).join('');}
function updateFilterStars(counts=null){const host=$('#filters .star-options');if(!host)return;const focused=document.activeElement,active=host.contains(focused)?focused.dataset.value:null,html=filterStarButtons(editingFilterModel(),counts);if(host.innerHTML!==html){host.innerHTML=html;if(active)host.querySelector(`[data-value="${active}"]`)?.focus({preventScroll:true});}host.closest('.filter-group').hidden=!html;}
function renderFilters(ratingCount){const queryScope=JSON.stringify([searchKey(state.search),data.scenario]);if(queryScope!==facetQueryScope){facetQueries.clear();facetQueryScope=queryScope;}const model=editingFilterModel(),f=model.filters,hs=getHotels().filter(h=>h.country===state.search.country),scale=budgetScale(f),budgetEdit=currentFilterBudgetEdit(f),starButtons=filterStarButtons(model);paintFilters(`
 <div class="filter-group"><h4>Название отеля или курорт</h4><div class="filter-search"><input class="input" id="hotel-query" type="search" value="${esc(f.q)}" placeholder="Название или несколько слов" aria-label="Название отеля или курорт">${icon('search')}<button type="button" class="icon-button clear-hotel-query" data-action="clear-hotel-query" aria-label="Очистить название отеля или курорт" ${f.q?'':'hidden'}>${icon('x')}</button></div></div>
 <div class="filter-group"><h4>Бюджет на всех туристов</h4><div class="price-inputs"><label>От, ₽<input type="text" inputmode="decimal" id="min-price" value="${esc(budgetEdit?.minText??f.min)}" aria-describedby="filter-budget-error"></label><label>До, ₽<input type="text" inputmode="decimal" id="max-price" value="${esc(budgetEdit?.maxText??f.max??'')}" placeholder="Без лимита" aria-describedby="filter-budget-error"></label></div><input class="range" type="range" id="price-range" aria-label="Максимальная цена" aria-valuetext="${esc(budgetLabel(f))}" min="${f.min}" max="${scale}" step="1000" value="${f.max??scale}"><p class="error-text filter-budget-error" id="filter-budget-error" role="alert" hidden></p></div>
 <div class="filter-group" ${starButtons?'':'hidden'}><h4>Категория отеля</h4><div class="star-options">${starButtons}</div></div>
 ${Object.keys(mealNames).length?`<div class="filter-group"><h4>Питание</h4>${checkRows('meals',[...new Set([...Object.keys(mealNames),...f.meals])].map(m=>[m,m]))}</div>`:''}
 ${f.rating||hs.some(h=>ratingValue(h)!==null)?(()=>{const count=ratingCount??countMatchingHotels({...model,filters:{...f,rating:true}});return `<div class="filter-group"><h4>Оценка гостей</h4>${filterCheckRowHTML('data-filter-bool="rating"','От 4,5 из 5',count,f.rating)}</div>`})():''}
 ${f.resorts.length||hs.some(h=>hotelPlaces(h).length)?`<div class="filter-group"><h4>Курорт</h4>${checkRows('resorts',[...new Set([...f.resorts,...hs.flatMap(h=>hotelPlaces(h))])].map(r=>[r,r]))}</div>`:''}
 ${f.operators.length||operators.length?`<div class="filter-group"><h4>Туроператор</h4>${checkRows('operators',[...new Set([...f.operators,...operators])].map(o=>[o,o]))}</div>`:''}
 ${amenityFilterGroups(hs,f)}
 <div class="filter-hint">${icon('info')}<span>${!state.hasSearched&&!state.onlyFavorites?'Условия применятся после нажатия «Найти туры». Доступные курорты и туроператоры появятся в выдаче.':'Фильтры применяются к найденным предложениям. Актуальная цена и сборы уточняются при выборе.'}</span></div>`,f);
 renderFilterNavigation();syncFilterResetState(model);showFilterBudgetValidity(readBudgetFields($('#min-price'),$('#max-price')));$('#beach-chip').hidden=true;$('#family-chip').hidden=true;settleFilterRoots($('#filters'));
}
return Object.freeze({facetQueries,expandedFilterSections,compareMealLabels,syncFilterSections,setFilterSectionOpen,applyFacetSearch,updateFacetCounts,settleFilterRoots,renderFilterNavigation,jumpToFilterSection,renderFilters});
}
window.AnyTourFilterPanelV1=Object.freeze({create});
})();
