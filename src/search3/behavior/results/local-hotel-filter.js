(function(){'use strict';
const results=document.getElementById('results'),actions=document.querySelector('#resultsTools .results-tools__actions'),rail=document.querySelector('.results-filter-rail');
if(!results||!actions||!rail)return;
const desktop=window.matchMedia('(min-width:1025px)');
let field=null,input=null,status=null,categoryField=null,categorySelect=null,mealField=null,mealSelect=null,budgetField=null,budgetInput=null,budgetLabel=null,ratingField=null,ratingSelect=null,seaField=null,seaSelect=null,resetButton=null,count=null;
let sourceItems=[],projectedItems=[],unmatched=new Set(),budgetActive=false;
function normalize(value){return String(value||'').replace(/\s+/g,' ').trim().toLocaleLowerCase('ru-RU');}
function id(value){return String(value&&value.id!==undefined&&value.id!==null?value.id:'');}
function cards(){return Array.from(results.querySelectorAll('.hotel-card'));}
function cardValues(key){const byId=new Map(sourceItems.map(item=>[id(item),Number(item&&item[key]||0)]));return cards().map(card=>byId.get(String(card.dataset.hotelId||''))||0);}
function money(value){return new Intl.NumberFormat('ru-RU',{maximumFractionDigits:0}).format(Number(value||0));}
function fields(){return[field,budgetField,mealField,categoryField,ratingField,seaField];}
function active(){return!!(normalize(input.value)||Number(categorySelect.value)||mealSelect.value||budgetActive||Number(ratingSelect.value)||Number(seaSelect.value));}
function mount(){
  if(!field)return;
  if(desktop.matches){fields().forEach(node=>rail.appendChild(node));rail.append(resetButton);}
  else{const anchor=actions.querySelector('#sortResults')?.closest('label')||actions.firstChild;fields().forEach(node=>actions.insertBefore(node,anchor));actions.insertBefore(resetButton,anchor);}
}
function option(value,label){const node=document.createElement('option');node.value=String(value);node.textContent=label;return node;}
function ensure(){
  if(field)return;
  rail.hidden=true;
  rail.innerHTML='<div class="search3-filter-rail__head"><strong>Фильтры</strong><span>Подходит: <b data-search3-filter-count>0</b></span></div>';
  count=rail.querySelector('[data-search3-filter-count]');
  field=document.createElement('label');field.className='search3-hotel-filter';field.hidden=true;
  field.innerHTML='<span>Название отеля</span><input type="search" autocomplete="off" placeholder="Введите название" aria-describedby="search3HotelFilterStatus"><small id="search3HotelFilterStatus" aria-live="polite"></small>';
  budgetField=document.createElement('label');budgetField.className='search3-budget-filter';budgetField.hidden=true;
  budgetField.innerHTML='<span>Бюджет за весь тур</span><input type="range" min="0" max="0" step="5000" value="0" aria-describedby="search3BudgetLabel"><small id="search3BudgetLabel"></small>';
  mealField=document.createElement('label');mealField.className='search3-meal-filter';mealField.hidden=true;mealField.innerHTML='<span>Питание</span><select aria-describedby="search3HotelFilterStatus"><option value="">Любое питание</option></select>';
  categoryField=document.createElement('label');categoryField.className='search3-category-filter';categoryField.hidden=true;categoryField.innerHTML='<span>Категория отеля</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любая категория</option></select>';
  ratingField=document.createElement('label');ratingField.className='search3-rating-filter';ratingField.hidden=true;ratingField.innerHTML='<span>Рейтинг гостей</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любой рейтинг</option><option value="4">4,0 и выше</option><option value="4.5">4,5 и выше</option></select>';
  seaField=document.createElement('label');seaField.className='search3-sea-filter';seaField.hidden=true;seaField.innerHTML='<span>До моря</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любое расстояние</option><option value="200">До 200 м</option><option value="500">До 500 м</option><option value="1000">До 1 км</option></select>';
  resetButton=document.createElement('button');resetButton.type='button';resetButton.className='search3-filter-reset';resetButton.textContent='Сбросить фильтры';resetButton.hidden=true;
  input=field.querySelector('input');status=field.querySelector('small');budgetInput=budgetField.querySelector('input');budgetLabel=budgetField.querySelector('small');mealSelect=mealField.querySelector('select');categorySelect=categoryField.querySelector('select');ratingSelect=ratingField.querySelector('select');seaSelect=seaField.querySelector('select');
  input.addEventListener('input',apply);categorySelect.addEventListener('change',apply);ratingSelect.addEventListener('change',apply);seaSelect.addEventListener('change',apply);
  mealSelect.addEventListener('change',()=>window.V2Results.rerender());
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
  const complete=items.length>1&&items.every(h=>{const hotelId=id(h);if(!hotelId||ids.has(hotelId)||!Array.isArray(h.tours)||!h.tours.length)return false;ids.add(hotelId);return h.tours.every(t=>{const label=api.mealLabel(t).replace(/\s+/g,' ').trim(),key=normalize(label);if(!key)return false;labels.set(key,label);return true;});});
  const previous=mealSelect.value,available=complete&&labels.size>1;mealSelect.replaceChildren(option('','Любое питание'));
  if(available)Array.from(labels).sort((a,b)=>a[1].localeCompare(b[1],'ru')).forEach(([value,label])=>mealSelect.appendChild(option(value,label)));
  mealSelect.value=available&&labels.has(previous)?previous:'';mealField.hidden=!available;return mealSelect.value;
}
function project(items){
  ensure();sourceItems=items.slice();unmatched=new Set();const api=window.V2Results,meal=syncMeal(items),budget=syncBudget(items);
  if(!meal&&!budget){projectedItems=items;mount();return projectedItems;}
  projectedItems=items.map(h=>{const tours=(Array.isArray(h.tours)?h.tours:[]).filter(t=>(!meal||normalize(api.mealLabel(t))===meal)&&(!budget||Number(t&&t.price||0)<=budget));if(!tours.length){unmatched.add(id(h));return Object.assign({},h,{tours:[]});}return Object.assign({},h,{tours,price:api.representativeTour({tours}).price});});
  mount();return projectedItems;
}
function apply(){
  ensure();const list=cards(),query=normalize(input.value),facets=syncHotelFacets(),visibleIds=new Set();let shown=0;
  list.forEach((card,index)=>{const title=card.querySelector('.hotel-title'),matchesName=!query||normalize(title&&title.textContent).includes(query),matchesCategory=!facets.category||facets.categories[index]===facets.category,matchesRating=!facets.rating||facets.ratings[index]>=facets.rating,matchesSea=!facets.sea||facets.seas[index]<=facets.sea;card.hidden=!(matchesName&&matchesCategory&&matchesRating&&matchesSea&&!unmatched.has(String(card.dataset.hotelId)));if(!card.hidden){shown++;visibleIds.add(String(card.dataset.hotelId||''));}});
  field.hidden=list.length<2;count.textContent=String(shown);status.textContent=active()?'Показано '+shown+' из '+list.length+' загруженных отелей':'';resetButton.hidden=!active();rail.hidden=fields().every(node=>node.hidden);
  const items=projectedItems.filter(item=>visibleIds.has(id(item)));window.dispatchEvent(new CustomEvent('search3:local-results-filtered',{detail:{items,shown,total:list.length,active:active()}}));
}
function reset(){ensure();input.value='';categorySelect.value='0';mealSelect.value='';ratingSelect.value='0';seaSelect.value='0';budgetActive=false;window.V2Results.rerender();}
function clear(event){
  ensure();if(event&&event.detail&&event.detail.dirty){fields().forEach(node=>{node.hidden=true;});resetButton.hidden=true;rail.hidden=true;return;}
  sourceItems=[];projectedItems=[];unmatched=new Set();input.value='';categorySelect.value='0';mealSelect.value='';ratingSelect.value='0';seaSelect.value='0';budgetActive=false;budgetInput.value='0';cards().forEach(card=>{card.hidden=false;});status.textContent='';count.textContent='0';fields().forEach(node=>{node.hidden=true;});resetButton.hidden=true;rail.hidden=true;
}
function rendered(event){sourceItems=event&&event.detail&&Array.isArray(event.detail.items)?event.detail.items.slice():[];apply();}
ensure();window.addEventListener('v2:results-rendered',rendered);window.addEventListener('v2:search-started',clear);window.addEventListener('v2:search-reset',clear);
window.Search3LocalHotelFilter={apply,clear,project,reset,version:5};
})();
