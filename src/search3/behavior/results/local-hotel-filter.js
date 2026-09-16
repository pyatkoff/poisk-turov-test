(function(){'use strict';
const results=document.getElementById('results'),actions=document.querySelector('#resultsTools .results-tools__actions'),rail=document.querySelector('.results-filter-rail'),summary=document.getElementById('resultSummary');
if(!results||!actions||!rail)return;
const desktop=window.matchMedia('(min-width:1025px)');
let field=null,input=null,status=null,categoryField=null,categorySelect=null,categoryPresets=null,mealField=null,mealSelect=null,mealPresets=null,operatorField=null,operatorSelect=null,regionField=null,regionSelect=null,budgetField=null,budgetInput=null,ratingField=null,ratingSelect=null,ratingCoverage=null,seaField=null,seaSelect=null,resetButton=null,count=null,mobilePanel=null,mobileBody=null,mobileSummary=null,activeList=null;
let sourceItems=[],projectedItems=[],unmatched=new Set(),budgetActive=false,nightsField=null,nightsSelect=null,flightField=null,flightSelect=null,budgetMinInput=null,budgetHint=null,mobileResultsButton=null;
function normalize(value){return String(value||'').replace(/\s+/g,' ').trim().toLocaleLowerCase('ru-RU');}
function id(value){return String(value&&value.id!==undefined&&value.id!==null?value.id:'');}
function cards(){return Array.from(results.querySelectorAll('.hotel-card'));}
function cardValues(key){const byId=new Map(sourceItems.map(item=>[id(item),Number(window.V2Results.hotelFacts(item)[key]||0)]));return cards().map(card=>byId.get(String(card.dataset.hotelId||''))||0);}
function cardTextValues(key){
  const api=window.V2Results,byId=new Map(sourceItems.map(item=>{const label=api.textValue(api.hotelFacts(item)[key]).replace(/\s+/g,' ').trim();return[id(item),{key:normalize(label),label}];}));
  return cards().map(card=>byId.get(String(card.dataset.hotelId||''))||{key:'',label:''});
}
function money(value){return new Intl.NumberFormat('ru-RU',{maximumFractionDigits:0}).format(Number(value||0));}
function fields(){return[field,regionField,categoryField,mealField,nightsField,flightField,budgetField,operatorField,ratingField,seaField];}
function active(){return!!(normalize(input.value)||Number(categorySelect.value)||mealSelect.value||operatorSelect.value||regionSelect.value||Number(nightsSelect.value)||flightSelect.value||budgetActive||Number(budgetMinInput.value)||Number(ratingSelect.value)||Number(seaSelect.value));}
function selectedLabel(select){const selected=select.selectedOptions&&select.selectedOptions[0];return selected?selected.textContent.trim():'';}
function activeEntries(){
  const entries=[];
  const lower=Number(budgetMinInput.value||0);
  if(lower||budgetActive)entries.push({key:'budget',label:(lower?'от '+money(lower):'')+(lower&&budgetActive?' ':'')+(budgetActive?'до '+money(budgetInput.value):'')+' ₽'});
  if(mealSelect.value)entries.push({key:'meal',label:selectedLabel(mealSelect)});
  if(Number(nightsSelect.value))entries.push({key:'nights',label:'Ночей: '+nightsSelect.value});
  if(flightSelect.value)entries.push({key:'flight',label:selectedLabel(flightSelect)});
  if(operatorSelect.value)entries.push({key:'operator',label:selectedLabel(operatorSelect)});
  if(regionSelect.value)entries.push({key:'region',label:selectedLabel(regionSelect)});
  if(Number(categorySelect.value))entries.push({key:'category',label:selectedLabel(categorySelect)});
  if(Number(ratingSelect.value))entries.push({key:'rating',label:'Рейтинг '+selectedLabel(ratingSelect)});
  if(Number(seaSelect.value))entries.push({key:'sea',label:selectedLabel(seaSelect)});
  if(normalize(input.value))entries.push({key:'hotel',label:'Отель: '+input.value.trim()});
  return entries;
}
function syncActiveList(entries){
  activeList.replaceChildren(...entries.map(entry=>{const button=document.createElement('button');button.type='button';button.dataset.filterKey=entry.key;button.textContent=entry.label+' ×';button.setAttribute('aria-label','Убрать фильтр: '+entry.label);return button;}));
  activeList.hidden=!entries.length;
}
function focusFilter(key){return({budget:budgetInput,meal:mealSelect,nights:nightsSelect,flight:flightSelect,operator:operatorSelect,region:regionSelect,category:categorySelect,rating:ratingSelect,sea:seaSelect,hotel:input})[key]||input;}
function clearActive(key){
  const target=focusFilter(key),projected=['budget','meal','operator','nights','flight'].includes(key);
  if(key==='budget'){budgetActive=false;budgetInput.value=budgetInput.max;budgetMinInput.value='';}
  else if(key==='meal')mealSelect.value='';
  else if(key==='nights')nightsSelect.value='0';
  else if(key==='flight')flightSelect.value='';
  else if(key==='operator')operatorSelect.value='';
  else if(key==='region')regionSelect.value='';
  else if(key==='category')categorySelect.value='0';
  else if(key==='rating')ratingSelect.value='0';
  else if(key==='sea')seaSelect.value='0';
  else if(key==='hotel')input.value='';
  if(projected)window.V2Results.rerender();else apply();
  requestAnimationFrame(()=>{if(!target||target.hidden)return;try{target.focus({preventScroll:true});}catch(error){target.focus();}});
}
function syncContainers(shown){
  const available=fields().some(node=>!node.hidden),entries=activeEntries(),labels=entries.map(entry=>entry.label),selected=labels.length,visible=labels.slice(0,2);
  count.textContent=String(shown);mobileSummary.textContent='Подходит: '+shown+(selected?' · '+visible.join(' · ')+(selected>visible.length?' · ещё '+(selected-visible.length):''):'');
  mobileResultsButton.textContent=shown?'Показать отели · '+shown:'Вернуться к результатам';
  mobileSummary.setAttribute('aria-label','Подходит: '+shown+'; '+(selected?'активные фильтры: '+labels.join('; '):'активных фильтров нет'));
  syncActiveList(entries);resetButton.hidden=!selected;rail.hidden=!desktop.matches||!available;mobilePanel.hidden=desktop.matches||!available;
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
  if(desktop.matches){mobilePanel.open=false;rail.append(activeList);fields().forEach(node=>rail.appendChild(node));rail.append(resetButton);}
  else{actions.appendChild(mobilePanel);mobileBody.append(activeList);fields().forEach(node=>mobileBody.appendChild(node));mobileBody.append(resetButton);}
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
function showResults(){
  mobilePanel.open=false;
  requestAnimationFrame(()=>{const card=cards().find(node=>!node.hidden),target=results.querySelector('.search3-local-empty')||(card&&card.querySelector('.hotel-title'))||results,temporary=!target.hasAttribute('tabindex');if(temporary)target.setAttribute('tabindex','-1');try{target.focus({preventScroll:true});}catch(error){target.focus();}(card||target).scrollIntoView({block:'start',behavior:'instant'});if(temporary)target.addEventListener('blur',()=>target.removeAttribute('tabindex'),{once:true});});
}
function ensure(){
  if(field)return;
  rail.hidden=true;
  rail.innerHTML='<div class="search3-filter-rail__head"><strong>Фильтры</strong><span>Подходит: <b data-search3-filter-count>0</b></span></div>';
  count=rail.querySelector('[data-search3-filter-count]');
  mobilePanel=document.createElement('details');mobilePanel.className='search3-mobile-filter-panel';mobilePanel.hidden=true;
  mobilePanel.innerHTML='<summary><strong>Фильтры</strong><span data-search3-mobile-filter-summary>Подходит: 0</span></summary><div class="search3-mobile-filter-panel__body"></div><div class="search3-mobile-filter-panel__footer"><button type="button" class="primary search3-filter-results">Показать отели</button></div>';
  mobileSummary=mobilePanel.querySelector('[data-search3-mobile-filter-summary]');mobileBody=mobilePanel.querySelector('.search3-mobile-filter-panel__body');
  mobileResultsButton=mobilePanel.querySelector('.search3-filter-results');mobileResultsButton.addEventListener('click',showResults);
  activeList=document.createElement('div');activeList.className='search3-filter-presets search3-active-filters';activeList.hidden=true;activeList.style.gridColumn='1 / -1';activeList.setAttribute('role','group');activeList.setAttribute('aria-label','Активные фильтры');
  activeList.addEventListener('click',event=>{const button=event.target.closest('button[data-filter-key]');if(button&&activeList.contains(button))clearActive(button.dataset.filterKey);});
  field=document.createElement('label');field.className='search3-hotel-filter';field.hidden=true;
  field.innerHTML='<span>Название отеля</span><input type="search" autocomplete="off" placeholder="Введите название" aria-describedby="search3HotelFilterStatus"><small id="search3HotelFilterStatus" aria-live="polite"></small>';
  budgetField=document.createElement('div');budgetField.className='search3-budget-filter';budgetField.hidden=true;
  budgetField.innerHTML='<span>Бюджет на тур</span><div class="search3-budget-range"><label><span>Цена от, ₽</span><input class="search3-budget-min" type="number" min="0" step="1" inputmode="numeric" placeholder="Любая" aria-describedby="search3BudgetHint"></label><label><span>Цена до, ₽</span><input class="search3-budget-max" type="number" min="0" max="0" step="1" inputmode="numeric" value="0" aria-describedby="search3BudgetHint"></label></div><small id="search3BudgetHint" aria-live="polite"></small>';
  mealField=document.createElement('div');mealField.className='search3-meal-filter';mealField.hidden=true;mealField.innerHTML='<label><span>Питание</span><select aria-describedby="search3HotelFilterStatus"><option value="">Любое питание</option></select></label><div class="search3-filter-presets" role="group" aria-label="Быстрый выбор питания" hidden></div>';
  nightsField=document.createElement('label');nightsField.className='search3-offer-filter search3-nights-filter';nightsField.hidden=true;nightsField.innerHTML='<span>Ночей</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любое количество</option></select>';
  flightField=document.createElement('label');flightField.className='search3-offer-filter search3-flight-filter';flightField.hidden=true;flightField.innerHTML='<span>Тип перелёта</span><select aria-describedby="search3HotelFilterStatus"><option value="">Любой перелёт</option></select>';
  nightsSelect=nightsField.querySelector('select');flightSelect=flightField.querySelector('select');
  nightsSelect.addEventListener('change',()=>window.V2Results.rerender());flightSelect.addEventListener('change',()=>window.V2Results.rerender());
  operatorField=document.createElement('label');operatorField.className='search3-operator-filter';operatorField.hidden=true;operatorField.innerHTML='<span>Туроператор</span><select aria-describedby="search3HotelFilterStatus"><option value="">Все туроператоры</option></select>';
  regionField=document.createElement('label');regionField.className='search3-region-filter';regionField.hidden=true;regionField.innerHTML='<span>Курорт / регион</span><select aria-describedby="search3HotelFilterStatus"><option value="">Все курорты</option></select>';
  categoryField=document.createElement('div');categoryField.className='search3-category-filter';categoryField.hidden=true;categoryField.innerHTML='<label><span>Категория отеля</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любая категория</option></select></label><div class="search3-filter-presets" role="group" aria-label="Быстрый выбор категории" hidden></div>';
  ratingField=document.createElement('label');ratingField.className='search3-rating-filter';ratingField.hidden=true;ratingField.innerHTML='<span>Рейтинг гостей</span><select aria-describedby="search3HotelFilterStatus search3RatingCoverage"><option value="0">Любой рейтинг</option><option value="4">4,0 и выше</option><option value="4.5">4,5 и выше</option></select><small id="search3RatingCoverage" data-search3-rating-coverage></small>';
  seaField=document.createElement('label');seaField.className='search3-sea-filter';seaField.hidden=true;seaField.innerHTML='<span>До моря</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любое расстояние</option><option value="200">До 200 м</option><option value="500">До 500 м</option><option value="1000">До 1 км</option></select>';
  resetButton=document.createElement('button');resetButton.type='button';resetButton.className='search3-filter-reset';resetButton.textContent='Сбросить фильтры';resetButton.hidden=true;
  input=field.querySelector('input');status=field.querySelector('small');budgetInput=budgetField.querySelector('input');mealSelect=mealField.querySelector('select');mealPresets=mealField.querySelector('.search3-filter-presets');operatorSelect=operatorField.querySelector('select');regionSelect=regionField.querySelector('select');categorySelect=categoryField.querySelector('select');categoryPresets=categoryField.querySelector('.search3-filter-presets');ratingSelect=ratingField.querySelector('select');ratingCoverage=ratingField.querySelector('[data-search3-rating-coverage]');seaSelect=seaField.querySelector('select');
  input.addEventListener('input',apply);regionSelect.addEventListener('change',apply);categorySelect.addEventListener('change',apply);ratingSelect.addEventListener('change',apply);seaSelect.addEventListener('change',apply);
  mealSelect.addEventListener('change',()=>window.V2Results.rerender());
  operatorSelect.addEventListener('change',()=>window.V2Results.rerender());
  bindPresets(mealPresets,mealSelect);bindPresets(categoryPresets,categorySelect);
  budgetInput=budgetField.querySelector('.search3-budget-max');budgetMinInput=budgetField.querySelector('.search3-budget-min');budgetHint=budgetField.querySelector('small');
  budgetInput.addEventListener('change',()=>setBudget(budgetInput.value));
  budgetMinInput.addEventListener('change',()=>{const value=Number(budgetMinInput.value);budgetMinInput.value=Number.isFinite(value)&&value>0?String(Math.round(value)):'';window.V2Results.rerender();});
  resetButton.addEventListener('click',reset);
  if(desktop.addEventListener)desktop.addEventListener('change',mount);else desktop.addListener(mount);
  mount();
}
function syncSelect(fieldNode,select,list,baseLabel,format){
  const previous=select.value,options=list.length>1?Array.from(new Set(list)).sort((a,b)=>b-a):[];
  select.replaceChildren(option(0,baseLabel));options.forEach(value=>select.appendChild(option(value,format(value))));
  select.value=options.includes(Number(previous))?previous:'0';fieldNode.hidden=options.length<2;return Number(select.value||0);
}
function syncTextSelect(fieldNode,select,list,baseLabel){
  const previous=select.value,labels=new Map();list.forEach(item=>{if(item.key&&!labels.has(item.key))labels.set(item.key,item.label);});
  const available=list.length>1&&list.every(item=>item.key)&&labels.size>1;select.replaceChildren(option('',baseLabel));
  if(available)Array.from(labels).sort((a,b)=>a[1].localeCompare(b[1],'ru')).forEach(([value,label])=>select.appendChild(option(value,label)));
  select.value=available&&labels.has(previous)?previous:'';fieldNode.hidden=!available;return select.value;
}
function numericCoverage(list,minimum){
  const total=sourceItems.length,known=list.filter(value=>value>0).length;
  return{t:total,k:known,a:total>1&&known>1&&known/total>=minimum};
}
function syncHotelFacets(){
  const regions=cardTextValues('region'),categories=cardValues('category'),ratings=cardValues('rating'),seas=cardValues('seaDistance');
  const region=syncTextSelect(regionField,regionSelect,regions,'Все курорты');
  const c=numericCoverage(categories,.95),category=syncSelect(categoryField,categorySelect,c.a?categories.filter(value=>value>0):[],'Любая категория'+(c.a?' · '+c.k+'/'+c.t:''),value=>value+'★');
  syncPresets(categoryPresets,categorySelect,Array.from(categorySelect.options).slice(1).map(item=>({value:item.value,label:item.textContent})),'0');
  const r=numericCoverage(ratings,.95);ratingField.hidden=!r.a;ratingCoverage.textContent=r.a?'Рейтинг указан у '+r.k+' из '+r.t+' отелей':'';if(!r.a)ratingSelect.value='0';
  const s=numericCoverage(seas,.8);seaField.hidden=!s.a;if(s.a)seaSelect.options[0].textContent='Любое расстояние · '+s.k+'/'+s.t;else seaSelect.value='0';
  return{regions,categories,ratings,seas,region,category,rating:Number(ratingSelect.value||0),sea:Number(seaSelect.value||0)};
}
function prices(items){const result=[];items.forEach(h=>(Array.isArray(h&&h.tours)?h.tours:[]).forEach(t=>{const value=Number(t&&t.price||0);if(value>0)result.push(value);}));return result;}
function setBudget(value){
  const min=Number(budgetInput.min||0),max=Number(budgetInput.max||0),number=Number(value),next=value===''||!Number.isFinite(number)?max:Math.min(max,Math.max(min,Math.round(number)));
  budgetInput.value=String(next);budgetActive=next<max;window.V2Results.rerender();
}
function syncBudget(items){
  const list=prices(items),available=items.length>1&&items.every(h=>Array.isArray(h&&h.tours)&&h.tours.length&&h.tours.every(t=>Number(t&&t.price||0)>0))&&new Set(list).size>1;
  if(!available){budgetActive=false;budgetMinInput.value='';budgetInput.min='0';budgetInput.max='0';budgetInput.value='0';budgetHint.textContent='';budgetField.hidden=true;return 0;}
  const maximum=Math.max(5000,Math.ceil(Math.max(...list)/5000)*5000),previous=Number(budgetInput.value||0),next=budgetActive?Math.max(previous,0):maximum;
  budgetInput.min='0';budgetInput.max=String(maximum);budgetInput.value=String(next);
  const reversed=Number(budgetMinInput.value||0)>next;budgetHint.textContent=reversed?'Цена «от» больше цены «до». Измените границы бюджета.':'';budgetMinInput.setAttribute('aria-invalid',String(reversed));budgetInput.setAttribute('aria-invalid',String(reversed));
  budgetActive=next<maximum;budgetField.hidden=false;return budgetActive?next:0;
}
function syncMeal(items){
  const labels=new Map(),ids=new Set(),api=window.V2Results;
  const complete=items.length>1&&items.every(h=>{const hotelId=id(h);if(!hotelId||ids.has(hotelId)||!Array.isArray(h.tours)||!h.tours.length)return false;ids.add(hotelId);return h.tours.every(t=>{const identity=api.mealIdentity(t);if(!identity)return false;if(!labels.has(identity.key))labels.set(identity.key,identity.label);return true;});});
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
  const complete=items.length>0&&items.every(h=>Array.isArray(h&&h.tours)&&h.tours.length&&h.tours.every(t=>{const identity=api.operatorIdentity(t);if(!identity)return false;if(!labels.has(identity.key))labels.set(identity.key,identity.label);return true;}));
  const previous=operatorSelect.value,available=complete&&labels.size>1;operatorSelect.replaceChildren(option('','Все туроператоры'));
  if(available)Array.from(labels).sort((a,b)=>a[1].localeCompare(b[1],'ru')).forEach(([value,label])=>operatorSelect.appendChild(option(value,label)));
  operatorSelect.value=available&&labels.has(previous)?previous:'';operatorField.hidden=!available;return operatorSelect.value;
}
function tourNights(t){const value=Number(t&&t.nights);return Number.isInteger(value)&&value>0?value:0;}
function tourFlight(t){return t&&t.isCharter===true?'charter':t&&t.isCharter===false?'regular':'';}
function syncOfferFacets(items){
  const complete=items.length>0&&items.every(h=>Array.isArray(h&&h.tours)&&h.tours.length),tours=complete?items.flatMap(h=>h.tours):[],nights=tours.map(tourNights),flights=tours.map(t=>{const key=tourFlight(t);return{key,label:key==='charter'?'Чартер':'Регулярный рейс'};});
  return{nights:syncSelect(nightsField,nightsSelect,nights.every(Boolean)?nights:[],'Любое количество',value=>value+' ноч.'),flight:syncTextSelect(flightField,flightSelect,flights,'Любой перелёт')};
}
function project(items){
  ensure();sourceItems=items.slice();unmatched=new Set();const api=window.V2Results,meal=syncMeal(items),operator=syncOperator(items),budget=syncBudget(items);
  const budgetMin=Number(budgetMinInput.value||0),facets=syncOfferFacets(items);
  if(!facets.nights&&!facets.flight&&!meal&&!operator&&!budgetActive&&!budgetMin){projectedItems=items;mount();return projectedItems;}
  projectedItems=items.map(h=>{const tours=(Array.isArray(h.tours)?h.tours:[]).filter(t=>(!facets.nights||tourNights(t)===facets.nights)&&(!facets.flight||tourFlight(t)===facets.flight)&&(!meal||api.mealIdentity(t)?.key===meal)&&(!operator||api.operatorIdentity(t)?.key===operator)&&(!budgetActive||Number(t&&t.price||0)<=budget)&&(!budgetMin||Number(t&&t.price||0)>=budgetMin));if(!tours.length){unmatched.add(id(h));return Object.assign({},h,{tours:[]});}return Object.assign({},h,{tours,price:api.representativeTour({tours}).price});});
  mount();return projectedItems;
}
function apply(){
  ensure();const list=cards(),query=normalize(input.value),facets=syncHotelFacets(),visibleIds=new Set();let shown=0;
  list.forEach((card,index)=>{const title=card.querySelector('.hotel-title'),matchesName=!query||normalize(title&&title.textContent).includes(query),matchesRegion=!facets.region||facets.regions[index].key===facets.region,matchesCategory=!facets.category||facets.categories[index]===facets.category,matchesRating=!facets.rating||facets.ratings[index]>=facets.rating,matchesSea=!facets.sea||facets.seas[index]>0&&facets.seas[index]<=facets.sea;card.hidden=!(matchesName&&matchesRegion&&matchesCategory&&matchesRating&&matchesSea&&!unmatched.has(String(card.dataset.hotelId)));if(!card.hidden){shown++;visibleIds.add(String(card.dataset.hotelId||''));}});
  field.hidden=list.length<2;status.textContent=active()?'Показано '+shown+' из '+list.length+' загруженных отелей':'';syncContainers(shown);syncEmptyState(list,shown);
  if(summary&&list.length)summary.textContent=(active()?'Показано отелей: '+shown+' из '+list.length:'Найдено отелей: '+list.length)+' · цены из текущего поиска';
  const items=projectedItems.filter(item=>visibleIds.has(id(item)));window.dispatchEvent(new CustomEvent('search3:local-results-filtered',{detail:{items,shown,total:list.length,active:active()}}));
}
function focusAfterReset(trigger){
  requestAnimationFrame(()=>{const restored=cards().find(card=>!card.hidden),target=trigger.classList.contains('search3-local-empty-reset')&&(restored&&restored.querySelector('.hotel-title')||results)||input;if(!target)return;const temporary=target!==input&&!target.hasAttribute('tabindex');if(temporary)target.setAttribute('tabindex','-1');try{target.focus({preventScroll:true});}catch(error){target.focus();}if(temporary)target.addEventListener('blur',()=>target.removeAttribute('tabindex'),{once:true});});
}
function reset(event){ensure();const trigger=event&&event.currentTarget;input.value='';categorySelect.value='0';mealSelect.value='';nightsSelect.value='0';flightSelect.value='';operatorSelect.value='';regionSelect.value='';ratingSelect.value='0';seaSelect.value='0';budgetActive=false;budgetMinInput.value='';window.V2Results.rerender();if(trigger)focusAfterReset(trigger);}
function clear(event){
  ensure();const empty=results.querySelector('.search3-local-empty');if(empty)empty.remove();if(event&&event.detail&&event.detail.dirty){fields().forEach(node=>{node.hidden=true;});mobilePanel.open=false;syncContainers(0);return;}
  sourceItems=[];projectedItems=[];unmatched=new Set();input.value='';categorySelect.value='0';mealSelect.value='';nightsSelect.value='0';flightSelect.value='';operatorSelect.value='';regionSelect.value='';ratingSelect.value='0';seaSelect.value='0';budgetActive=false;budgetMinInput.value='';budgetInput.value='0';cards().forEach(card=>{card.hidden=false;});status.textContent='';fields().forEach(node=>{node.hidden=true;});mobilePanel.open=false;syncContainers(0);
}
function rendered(event){sourceItems=event&&event.detail&&Array.isArray(event.detail.items)?event.detail.items.slice():[];apply();}
ensure();window.addEventListener('v2:results-rendered',rendered);window.addEventListener('v2:hotel-details-rendered',apply);window.addEventListener('v2:search-started',clear);window.addEventListener('v2:search-reset',clear);
window.Search3LocalHotelFilter={apply,clear,project,reset,version:12};
})();
