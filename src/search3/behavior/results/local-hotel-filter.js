(function(){'use strict';
const results=document.getElementById('results'),actions=document.querySelector('#resultsTools .results-tools__actions');
if(!results||!actions)return;
let field=null,input=null,status=null,categoryField=null,categorySelect=null,mealField=null,mealSelect=null,sourceItems=[],unmatched=new Set();
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
  mealField=document.createElement('label');
  mealField.className='search3-meal-filter';
  mealField.hidden=true;
  mealField.innerHTML='<span>Питание среди загруженных</span><select aria-describedby="search3HotelFilterStatus"><option value="">Любое питание</option></select>';
  actions.insertBefore(mealField,actions.firstChild);
  actions.insertBefore(categoryField,actions.firstChild);
  actions.insertBefore(field,categoryField);
  input=field.querySelector('input');
  status=field.querySelector('small');
  categorySelect=categoryField.querySelector('select');
  mealSelect=mealField.querySelector('select');
  input.addEventListener('input',apply);
  categorySelect.addEventListener('change',apply);
  mealSelect.addEventListener('change',()=>window.V2Results.rerender());
}
function project(items){
  ensure();
  sourceItems=items.slice();
  unmatched=new Set();
  const labels=new Map(),ids=new Set(),api=window.V2Results;
  const complete=items.length>1&&items.every(h=>{
    const id=h&&h.id;
    if(id===undefined||id===null||String(id)===''||ids.has(String(id))||!Array.isArray(h.tours)||!h.tours.length)return false;
    ids.add(String(id));
    return h.tours.every(t=>{const label=api.mealLabel(t).replace(/\s+/g,' ').trim(),key=normalize(label);if(!key)return false;labels.set(key,label);return true;});
  });
  const previous=mealSelect.value,available=complete&&labels.size>1;
  mealSelect.innerHTML='<option value="">Любое питание</option>';
  if(available)Array.from(labels).sort((a,b)=>a[1].localeCompare(b[1],'ru')).forEach(([value,label])=>{const option=document.createElement('option');option.value=value;option.textContent=label;mealSelect.appendChild(option);});
  mealSelect.value=available&&labels.has(previous)?previous:'';
  mealField.hidden=!available;
  const selected=mealSelect.value;
  if(!selected)return items;
  return items.map(h=>{
    const tours=h.tours.filter(t=>normalize(api.mealLabel(t))===selected);
    if(!tours.length)unmatched.add(String(h.id));
    return Object.assign({},h,{tours,price:api.representativeTour({tours}).price});
  });
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
    card.hidden=!(matchesName&&matchesCategory&&!unmatched.has(String(card.dataset.hotelId)));
    if(!card.hidden)shown++;
  });
  field.hidden=list.length<2;
  status.textContent=query||category||mealSelect.value?'Показано '+shown+' из '+list.length+' загруженных отелей':'';
}
function clear(event){
  ensure();
  // Editing keeps the prior result projection intact until a real new search.
  if(event&&event.detail&&event.detail.dirty){field.hidden=true;categoryField.hidden=true;mealField.hidden=true;return;}
  sourceItems=[];
  unmatched=new Set();
  input.value='';
  categorySelect.value='0';
  mealSelect.value='';
  cards().forEach(card=>{card.hidden=false;});
  status.textContent='';
  field.hidden=true;
  categoryField.hidden=true;
  mealField.hidden=true;
}
function rendered(event){sourceItems=event&&event.detail&&Array.isArray(event.detail.items)?event.detail.items.slice():[];apply();}
ensure();
window.addEventListener('v2:results-rendered',rendered);
window.addEventListener('v2:search-started',clear);
window.addEventListener('v2:search-reset',clear);
window.Search3LocalHotelFilter={apply,clear,project,version:3};
})();
