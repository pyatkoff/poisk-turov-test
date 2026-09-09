(function(){'use strict';
const results=document.getElementById('results'),actions=document.querySelector('#resultsTools .results-tools__actions');
if(!results||!actions)return;
let field=null,input=null,status=null;
function normalize(value){return String(value||'').replace(/\s+/g,' ').trim().toLocaleLowerCase('ru-RU');}
function cards(){return Array.from(results.querySelectorAll('.hotel-card'));}
function ensure(){
  if(field)return;
  field=document.createElement('label');
  field.className='search3-hotel-filter';
  field.hidden=true;
  field.innerHTML='<span>Отель среди загруженных</span><input type="search" autocomplete="off" placeholder="Название отеля" aria-describedby="search3HotelFilterStatus"><small id="search3HotelFilterStatus" aria-live="polite"></small>';
  actions.insertBefore(field,actions.firstChild);
  input=field.querySelector('input');
  status=field.querySelector('small');
  input.addEventListener('input',apply);
}
function apply(){
  ensure();
  const list=cards(),query=normalize(input.value);
  let shown=0;
  list.forEach(card=>{
    const title=card.querySelector('.hotel-title');
    const matches=!query||normalize(title&&title.textContent).includes(query);
    card.hidden=!matches;
    if(matches)shown++;
  });
  field.hidden=list.length<2;
  status.textContent=query?'Показано '+shown+' из '+list.length+' загруженных отелей':'';
}
function clear(){
  ensure();
  input.value='';
  cards().forEach(card=>{card.hidden=false;});
  status.textContent='';
  field.hidden=true;
}
ensure();
window.addEventListener('v2:results-rendered',apply);
window.addEventListener('v2:search-started',clear);
window.addEventListener('v2:search-reset',clear);
window.Search3LocalHotelFilter={apply,clear,version:1};
})();
