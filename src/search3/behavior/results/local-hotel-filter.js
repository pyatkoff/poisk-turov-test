(function(){'use strict';
const results=document.getElementById('results'),actions=document.querySelector('#resultsTools .results-tools__actions');
if(!results||!actions)return;
let field=null,input=null,status=null,categoryField=null,categorySelect=null,sourceItems=[];
function normalize(value){return String(value||'').replace(/\s+/g,' ').trim().toLocaleLowerCase('ru-RU');}
function cards(){return Array.from(results.querySelectorAll('.hotel-card'));}
function itemCategories(list){
  const byId=new Map(sourceItems.map(item=>[String(item&&item.id!==undefined&&item.id!==null?item.id:''),Number(item&&item.category||0)]));
  return list.map(card=>byId.get(String(card.dataset.hotelId||''))||0);
}
function ensure(){
  if(field)return;
  field=document.createElement('label');
  field.className='search3-hotel-filter';
  field.hidden=true;
  field.innerHTML='<span>Отель среди загруженных</span><input type="search" autocomplete="off" placeholder="Название отеля" aria-describedby="search3HotelFilterStatus"><small id="search3HotelFilterStatus" aria-live="polite"></small>';
  categoryField=document.createElement('label');
  categoryField.className='search3-category-filter';
  categoryField.hidden=true;
  categoryField.innerHTML='<span>Категория среди загруженных</span><select aria-describedby="search3HotelFilterStatus"><option value="0">Любая категория</option></select>';
  actions.insertBefore(categoryField,actions.firstChild);
  actions.insertBefore(field,categoryField);
  input=field.querySelector('input');
  status=field.querySelector('small');
  categorySelect=categoryField.querySelector('select');
  input.addEventListener('input',apply);
  categorySelect.addEventListener('change',apply);
}
function syncCategory(list){
  const values=itemCategories(list),complete=list.length>1&&values.length===sourceItems.length&&values.every(value=>value>0);
  const options=complete?Array.from(new Set(values)).sort((a,b)=>b-a):[];
  const previous=Number(categorySelect.value||0),available=options.length>1;
  categorySelect.innerHTML='<option value="0">Любая категория</option>'+options.map(value=>'<option value="'+value+'">'+value+'★</option>').join('');
  categorySelect.value=available&&options.includes(previous)?String(previous):'0';
  categoryField.hidden=!available;
  return Number(categorySelect.value||0);
}
function apply(){
  ensure();
  const list=cards(),query=normalize(input.value),category=syncCategory(list),categories=itemCategories(list);
  let shown=0;
  list.forEach((card,index)=>{
    const title=card.querySelector('.hotel-title');
    const matchesName=!query||normalize(title&&title.textContent).includes(query);
    const matchesCategory=!category||categories[index]===category;
    card.hidden=!(matchesName&&matchesCategory);
    if(!card.hidden)shown++;
  });
  field.hidden=list.length<2;
  status.textContent=query||category?'Показано '+shown+' из '+list.length+' загруженных отелей':'';
}
function clear(){
  ensure();
  sourceItems=[];
  input.value='';
  categorySelect.value='0';
  cards().forEach(card=>{card.hidden=false;});
  status.textContent='';
  field.hidden=true;
  categoryField.hidden=true;
}
function rendered(event){sourceItems=event&&event.detail&&Array.isArray(event.detail.items)?event.detail.items.slice():[];apply();}
ensure();
window.addEventListener('v2:results-rendered',rendered);
window.addEventListener('v2:search-started',clear);
window.addEventListener('v2:search-reset',clear);
window.Search3LocalHotelFilter={apply,clear,version:2};
})();
