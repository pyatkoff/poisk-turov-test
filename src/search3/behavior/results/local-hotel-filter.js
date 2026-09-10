(function(){'use strict';
const results=document.getElementById('results'),actions=document.querySelector('#resultsTools .results-tools__actions'),rail=document.querySelector('.results-filter-rail');
if(!results||!actions||!rail)return;
const desktop=window.matchMedia('(min-width:1025px)');
let field=null,input=null,status=null,categoryField=null,categorySelect=null,categoryPresets=null,mealField=null,mealSelect=null,mealPresets=null,operatorField=null,operatorSelect=null,budgetField=null,budgetInput=null,budgetLabel=null,ratingField=null,ratingSelect=null,seaField=null,seaSelect=null,resetButton=null,count=null,mobilePanel=null,mobileBody=null,mobileSummary=null;
let sourceItems=[],projectedItems=[],unmatched=new Set(),budgetActive=false;
function normalize(value){return String(value||'').replace(/\s+/g,' ').trim().toLocaleLowerCase('ru-RU');}
function mealKey(value){
  const label=normalize(value),code=(label.match(/^(uai|ai|bb|hb|fb|ro|sc)(?=$|[+\s-])/)||[])[1]||'';
  if(code==='ai'||code==='uai'||/(?:ultra\s+)?all[ -]?inclusive|вс[её] включено/.test(label))return'meal:all-inclusive';
  if(code==='bb'||/bed\s*(?:&|and)\s*breakfast|breakfast|(?:только )?завтрак/.test(label))return'meal:breakfast';
  if(code==='hb'||/half[ -]?board|полупансион/.test(label))return'meal:half-board';
  if(code==='fb'||/full[ -]?board|полный пансион/.test(label))return'meal:full-board';
  if(code==='ro'||/room[ -]?only|no[ -]?meal|без питания/.test(label))return'meal:room-only';
  if(code==='sc'||/self[ -]?catering|самообслуживан/.test(label))return'meal:self-catering';
  if(/on[ -]?request|по запросу/.test(label))return'meal:on-request';
  return label;
}
function mealOptionLabel(key,label){return({
  'meal:all-inclusive':'Всё включено','meal:breakfast':'Завтрак','meal:half-board':'Полупансион',
  'meal:full-board':'Полный пансион','meal:room-only':'Без питания','meal:self-catering':'Самообслуживание',
  'meal:on-request':'По запросу'
})[key]||label;}
function id(value){return String(value&&value.id!==undefined&&value.id!==null?value.id:'');}
function cards(){return Array.from(results.querySelectorAll('.hotel-card'));}
function cardValues(key){const byId=new Map(sourceItems.map(item=>[id(item),Number(item&&item[key]||0)]));return cards().map(card=>byId.get(String(card.dataset.hotelId||''))||0);}
function money(value){return new Intl.NumberFormat('ru-RU',{maximumFractionDigits:0}).format(Number(value||0));}
function fields(){return[field,budgetField,mealField,operatorField,categoryField,ratingField,seaField];}
function active(){return!!(normalize(input.value)||Number(categorySelect.value)||mealSelect.value||operatorSelect.value||budgetActive||Number(ratingSelect.value)||Number(seaSelect.value));}
function selectedLabel(select){const selected=select.selectedOptions&&select.selectedOptions[0];return selected?selected.textContent.trim():'';}
function activeLabels(){
  const labels=[];
  if(budgetActive)labels.push('до '+money(budgetInput.value)+' ₽');
  if(mealSelect.value)labels.push(selectedLabel(mealSelect));
  if(operatorSelect.value)labels.push(selectedLabel(operatorSelect));
  if(Number(categorySelect.value))labels.push(selectedLabel(categorySelect));
  if(Number(ratingSelect.value))labels.push('Рейтинг '+selectedLabel(ratingSelect));
  if(Number(seaSelect.value))labels.push(selectedLabel(seaSelect));
  if(normalize(input.value))labels.push('Отель: '+input.value.trim());
  return labels;
}
function syncContainers(shown){
  const available=fields().some(node=>!node.hidden),labels=activeLabels(),selected=labels.length,visible=labels.slice(0,2);
  count.textContent=String(shown);mobileSummary.textContent='Подходит: '+shown+(selected?' · '+visible.join(' · ')+(selected>visible.length?' · ещё '+(selected-visible.length):''):'');
  mobileSummary.setAttribute('aria-label','Подходит: '+shown+'; '+(selected?'активные фильтры: '+labels.join('; '):'активных фильтров нет'));
  resetButton.hidden=!selected;rail.hidden=!desktop.matches||!available;mobilePanel.hidden=desktop.matches||!available;
}
function syncEmptyState(list,shown){
  let empty=results.querySelector('.search3-local-empty');
  if(!list.length||shown||!active()){if(empty)empty.remove();return;}
  if(empty)return;
  empty=document.createElement('div');empty.className='empty empty-actionable search3-local-empty';empty.setAttribute('role','status');
  empty.innerHTML='<strong>По выбранным фильтрам ничего не подошло</strong><span>Сбросьте фильтры, чтобы снова показать загруженные варианты.</span><div class="empty-actions"><button type="button" class="secondary search3-local-empty-reset">Сбросить фильтры</button></div>';
  empty.querySelector('.search3-local-empty-reset').addEventListener('click',reset);results.prepend(empty);
}
function mount(){
  if(!field)return;
  if(desktop.matches){mobilePanel.open=false;fields().forEach(node=>rail.appendChild(node));rail.append(resetButton);}
  else{const anchor=actions.querySelector('#sortResults')?.closest('label')||actions.firstChild;actions.insertBefore(mobilePanel,anchor);fields().forEach(node=>mobileBody.appendChild(node));mobileBody.append(resetButton);}
  syncContainers(Number(count.textContent||0));
}
function option(value,label){const node=document.createElement('option');node.value=String(value);node.textContent=label;return node;}
function syncPresets(group,select,choices,empty){
  const signature=choices.map(item=>item.value+'\n'+item.label).join('\n');
  if(group.dataset.options!==signature){group.dataset.options=signature;group.replaceChildren(...choices.map(item=>{const button=document.createElement('button');button.type='button';button.dataset.value=item.value;button.textContent=item.label;return button;}));}
  group.hidden=!choices.length;Array.from(group.children).forEach(button=>button.setAttribute('aria-pressed',String(select.value===button.dataset.value)));group.dataset.empty=empty;
}
function bindPresets(group,select){
  group.addEventListener('click',event=>{const button=event.target.closest('button[data-value]');if(!button||!group.contains(button))return;const value=button.dataset.value;select.value=select.value===value?group.dataset.empty:value;select.dispatchEvent(new Event('change',{bubbles:true}));const current=Array.from(group.children).find(item=>item.dataset.value===value);if(current&&!current.hidden)current.focus();});
}
function ensure(){
  if(field)return;
  rail.hidden=true;
  rail.innerHTML='<div class="search3-filter-rail__head"><strong>Фильтры</strong><span>Подходит: <b data-search3-filter-count>0</b></span></div>';
  count=rail.querySelector('[data-search3-filter-count]');
  mobilePanel=document.createElement('details');mobilePanel.className='search3-mobile-filter-panel';mobilePanel.hidden=true;
  mobilePanel.innerHTML='<summary><strong>Фильтры</strong><span data-search3-mobile-filter-summary>Подходит: 0</span></summary><div class="search3-mobile-filter-panel__body"></div>';
  mobileSummary=mobilePanel.querySelector('[data-search3-mobile-filter-summary]');mobileBody=mobilePanel.querySelector('.search3-mobile-filter-panel__body');
  field=document.createElement('label');field.className='search3-hotel-filter';field.hidden=true;
  field.innerHTML='<span>Название отеля</span><input type="search" autocomplete="off" placeholder="Введите название" aria-describedby="search3HotelFilterStatus"><small id="search3HotelFilterStatus" aria-live="polite"></small>';
  budgetField=document.createElement('label');budgetField.className='search3-budget-filter';budgetField.hidden=true;
  budgetField.innerHTML='<span>Бюджет за весь тур</span><input type="range" min="0" max="0" step="5000" value="0" aria-describedby="search3BudgetLabel"><small id="search3BudgetLabel"></small>';
  mealField=document.createElement('div');mealField.className='search3-meal-filter';mealField.hidden=true;mealField.innerHTML='<label><span>Питание</span><select aria-describedby="search3HotelFilterStatus"><option value="">Любое питание</option></select></label><div class="search3-filter-presets" role="group" aria-label="Быстрый выбор питания" hidden></div>';
  operatorField=document.createElement('label');operatorField.className='search3-operator-filter';operatorField.hidden=true;operatorField.innerHTML='<span>Туроператор</span><select aria-describedby="search3HotelFilterStatus"><option value="">Все туроператоры</option></select>';
  categoryField=document.createElement('div');categoryField.className='search3-category-filter';categoryField.hidden=true;categoryField.innerHTML='<label><span>Категория отеля</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любая категория</option></select></label><div class="search3-filter-presets" role="group" aria-label="Быстрый выбор категории" hidden></div>';
  ratingField=document.createElement('label');ratingField.className='search3-rating-filter';ratingField.hidden=true;ratingField.innerHTML='<span>Рейтинг гостей</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любой рейтинг</option><option value="4">4,0 и выше</option><option value="4.5">4,5 и выше</option></select>';
  seaField=document.createElement('label');seaField.className='search3-sea-filter';seaField.hidden=true;seaField.innerHTML='<span>До моря</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любое расстояние</option><option value="200">До 200 м</option><option value="500">До 500 м</option><option value="1000">До 1 км</option></select>';
  resetButton=document.createElement('button');resetButton.type='button';resetButton.className='search3-filter-reset';resetButton.textContent='Сбросить фильтры';resetButton.hidden=true;
  input=field.querySelector('input');status=field.querySelector('small');budgetInput=budgetField.querySelector('input');budgetLabel=budgetField.querySelector('small');mealSelect=mealField.querySelector('select');mealPresets=mealField.querySelector('.search3-filter-presets');operatorSelect=operatorField.querySelector('select');categorySelect=categoryField.querySelector('select');categoryPresets=categoryField.querySelector('.search3-filter-presets');ratingSelect=ratingField.querySelector('select');seaSelect=seaField.querySelector('select');
  input.addEventListener('input',apply);categorySelect.addEventListener('change',apply);ratingSelect.addEventListener('change',apply);seaSelect.addEventListener('change',apply);
  mealSelect.addEventListener('change',()=>window.V2Results.rerender());
  operatorSelect.addEventListener('change',()=>window.V2Results.rerender());
  bindPresets(mealPresets,mealSelect);bindPresets(categoryPresets,categorySelect);
  budgetInput.addEventListener('input',()=>{budgetActive=Number(budgetInput.value)<Number(budgetInput.max);syncBudgetLabel();window.V2Results.rerender();});
  resetButton.addEventListener('click',reset);
  if(desktop.addEventListener)desktop.addEventListener('change',mount);else desktop.addListener(mount);
  mount();
}
function syncSelect(fieldNode,select,list,baseLabel,format){
  const previous=select.value,options=list.length>1?Array.from(new Set(list)).sort((a,b)=>b-a):[];
  select.replaceChildren(option(0,baseLabel));options.forEach(value=>select.appendChild(option(value,format(value))));
  select.value=options.includes(Number(previous))?previous:'0';fieldNode.hidden=options.length<2;return Number(select.value||0);
}
function syncHotelFacets(){
  const categories=cardValues('category'),ratings=cardValues('rating'),seas=cardValues('seaDistance'),complete=list=>sourceItems.length>1&&list.length===sourceItems.length&&list.every(value=>value>0);
  const category=syncSelect(categoryField,categorySelect,complete(categories)?categories:[],'Любая категория',value=>value+'★');
  syncPresets(categoryPresets,categorySelect,Array.from(categorySelect.options).slice(1).map(item=>({value:item.value,label:item.textContent})),'0');
  const ratingComplete=complete(ratings);ratingField.hidden=!ratingComplete;if(!ratingComplete)ratingSelect.value='0';
  const seaComplete=complete(seas);seaField.hidden=!seaComplete;if(!seaComplete)seaSelect.value='0';
  return{categories,ratings,seas,category,rating:Number(ratingSelect.value||0),sea:Number(seaSelect.value||0)};
}
function prices(items){const result=[];items.forEach(h=>(Array.isArray(h&&h.tours)?h.tours:[]).forEach(t=>{const value=Number(t&&t.price||0);if(value>0)result.push(value);}));return result;}
function syncBudget(items){
  const list=prices(items),available=items.length>1&&items.every(h=>Array.isArray(h&&h.tours)&&h.tours.length&&h.tours.every(t=>Number(t&&t.price||0)>0))&&new Set(list).size>1;
  if(!available){budgetActive=false;budgetInput.min='0';budgetInput.max='0';budgetInput.value='0';budgetField.hidden=true;syncBudgetLabel();return 0;}
  const minimum=Math.floor(Math.min(...list)/5000)*5000,maximum=Math.ceil(Math.max(...list)/5000)*5000,previous=Number(budgetInput.value||0);
  budgetInput.min=String(minimum);budgetInput.max=String(Math.max(minimum+5000,maximum));budgetInput.value=String(budgetActive?Math.min(Math.max(previous,minimum),Number(budgetInput.max)):budgetInput.max);
  budgetActive=Number(budgetInput.value)<Number(budgetInput.max);budgetField.hidden=false;syncBudgetLabel();return budgetActive?Number(budgetInput.value):0;
}
function syncBudgetLabel(){budgetLabel.textContent=budgetField.hidden?'':'до '+money(budgetInput.value)+' ₽';}
function syncMeal(items){
  const labels=new Map(),ids=new Set(),api=window.V2Results;
  const complete=items.length>1&&items.every(h=>{const hotelId=id(h);if(!hotelId||ids.has(hotelId)||!Array.isArray(h.tours)||!h.tours.length)return false;ids.add(hotelId);return h.tours.every(t=>{const label=api.mealLabel(t).replace(/\s+/g,' ').trim(),key=mealKey(label);if(!key)return false;if(!labels.has(key))labels.set(key,mealOptionLabel(key,label));return true;});});
  const previous=mealSelect.value,available=complete&&labels.size>1;mealSelect.replaceChildren(option('','Любое питание'));
  if(available)Array.from(labels).sort((a,b)=>a[1].localeCompare(b[1],'ru')).forEach(([value,label])=>mealSelect.appendChild(option(value,label)));
  mealSelect.value=available&&labels.has(previous)?previous:'';mealField.hidden=!available;
  const options=Array.from(mealSelect.options).slice(1),pick=value=>options.find(item=>item.value===value),quick=[];
  const breakfast=pick('meal:breakfast'),inclusive=pick('meal:all-inclusive');
  [breakfast,inclusive].forEach(item=>{if(item&&!quick.some(choice=>choice.value===item.value))quick.push({value:item.value,label:item===breakfast?'Завтрак':'Всё включено'});});
  syncPresets(mealPresets,mealSelect,quick,'');return mealSelect.value;
}
function syncOperator(items){
  const labels=new Map(),api=window.V2Results;
  const complete=items.length>1&&items.every(h=>Array.isArray(h&&h.tours)&&h.tours.length&&h.tours.every(t=>{const label=api.textValue(t&&t.operator).replace(/\s+/g,' ').trim(),key=normalize(label);if(!key)return false;if(!labels.has(key))labels.set(key,label);return true;}));
  const previous=operatorSelect.value,available=complete&&labels.size>1;operatorSelect.replaceChildren(option('','Все туроператоры'));
  if(available)Array.from(labels).sort((a,b)=>a[1].localeCompare(b[1],'ru')).forEach(([value,label])=>operatorSelect.appendChild(option(value,label)));
  operatorSelect.value=available&&labels.has(previous)?previous:'';operatorField.hidden=!available;return operatorSelect.value;
}
function project(items){
  ensure();sourceItems=items.slice();unmatched=new Set();const api=window.V2Results,meal=syncMeal(items),operator=syncOperator(items),budget=syncBudget(items);
  if(!meal&&!operator&&!budget){projectedItems=items;mount();return projectedItems;}
  projectedItems=items.map(h=>{const tours=(Array.isArray(h.tours)?h.tours:[]).filter(t=>(!meal||mealKey(api.mealLabel(t))===meal)&&(!operator||normalize(api.textValue(t&&t.operator))===operator)&&(!budget||Number(t&&t.price||0)<=budget));if(!tours.length){unmatched.add(id(h));return Object.assign({},h,{tours:[]});}return Object.assign({},h,{tours,price:api.representativeTour({tours}).price});});
  mount();return projectedItems;
}
function apply(){
  ensure();const list=cards(),query=normalize(input.value),facets=syncHotelFacets(),visibleIds=new Set();let shown=0;
  list.forEach((card,index)=>{const title=card.querySelector('.hotel-title'),matchesName=!query||normalize(title&&title.textContent).includes(query),matchesCategory=!facets.category||facets.categories[index]===facets.category,matchesRating=!facets.rating||facets.ratings[index]>=facets.rating,matchesSea=!facets.sea||facets.seas[index]<=facets.sea;card.hidden=!(matchesName&&matchesCategory&&matchesRating&&matchesSea&&!unmatched.has(String(card.dataset.hotelId)));if(!card.hidden){shown++;visibleIds.add(String(card.dataset.hotelId||''));}});
  field.hidden=list.length<2;status.textContent=active()?'Показано '+shown+' из '+list.length+' загруженных отелей':'';syncContainers(shown);syncEmptyState(list,shown);
  const items=projectedItems.filter(item=>visibleIds.has(id(item)));window.dispatchEvent(new CustomEvent('search3:local-results-filtered',{detail:{items,shown,total:list.length,active:active()}}));
}
function reset(){ensure();input.value='';categorySelect.value='0';mealSelect.value='';operatorSelect.value='';ratingSelect.value='0';seaSelect.value='0';budgetActive=false;window.V2Results.rerender();}
function clear(event){
  ensure();const empty=results.querySelector('.search3-local-empty');if(empty)empty.remove();if(event&&event.detail&&event.detail.dirty){fields().forEach(node=>{node.hidden=true;});mobilePanel.open=false;syncContainers(0);return;}
  sourceItems=[];projectedItems=[];unmatched=new Set();input.value='';categorySelect.value='0';mealSelect.value='';operatorSelect.value='';ratingSelect.value='0';seaSelect.value='0';budgetActive=false;budgetInput.value='0';cards().forEach(card=>{card.hidden=false;});status.textContent='';fields().forEach(node=>{node.hidden=true;});mobilePanel.open=false;syncContainers(0);
}
function rendered(event){sourceItems=event&&event.detail&&Array.isArray(event.detail.items)?event.detail.items.slice():[];apply();}
ensure();window.addEventListener('v2:results-rendered',rendered);window.addEventListener('v2:search-started',clear);window.addEventListener('v2:search-reset',clear);
window.Search3LocalHotelFilter={apply,clear,project,reset,version:8};
})();
