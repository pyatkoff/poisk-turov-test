(function(){'use strict';
function tourWord(n){var m=n%10,m100=n%100;return m===1&&m100!==11?'тур':m>=2&&m<=4&&(m100<12||m100>14)?'тура':'туров';}
function prepareTourRows(tours){
  if(!tours)return;
  var rows=tours.querySelectorAll('.tour-row');
  rows.forEach(function(row){
    var btn=row.querySelector('.direct-tour');
    if(btn)btn.textContent='Подробнее о туре';
  });
  var old=tours.querySelector('.search3-tour-list-head');if(old)old.remove();
  var head=document.createElement('div');head.className='search3-tour-list-head';
  head.innerHTML='<div><strong>Доступные туры</strong><span>Точные даты, питание, номер, оператор и актуальная цена</span></div><b>'+rows.length+' '+tourWord(rows.length)+'</b>';
  tours.insertBefore(head,tours.firstChild);
}
function enhanceCard(card){
  if(!card||card.dataset.search3Card==='1')return;
  card.dataset.search3Card='1';
  var tours=card.querySelector('.hotel-tours');
  var body=card.querySelector('.hotel-body');
  if(!tours||!body)return;
  tours.hidden=true;
  prepareTourRows(tours);
  var best=body.querySelector('.hotel-best-offer>small');if(best)best.textContent='Туры от';
  var hint=body.querySelector('.hotel-choice-hint');
  var countText=hint&&hint.textContent?hint.textContent.replace(/^Ещё\s+/,''):'';
  var action=document.createElement('div');action.className='search3-hotel-action';
  action.innerHTML='<div class="search3-hotel-action__copy"><strong>Выберите конкретный тур</strong><span>'+(countText?countText+' по датам, питанию и размещению':'с точными датами, номером, питанием и перелётом')+'</span></div><button type="button" class="search3-show-tours" aria-expanded="false">Показать туры</button>';
  body.appendChild(action);
}
function enhanceResults(){document.querySelectorAll('#results .hotel-card').forEach(enhanceCard);}
window.addEventListener('v2:results-rendered',function(){setTimeout(enhanceResults,0);});
document.addEventListener('click',function(e){
  var btn=e.target&&e.target.closest&&e.target.closest('.search3-show-tours');if(!btn)return;
  var card=btn.closest('.hotel-card'),tours=card&&card.querySelector('.hotel-tours');if(!tours)return;
  var open=btn.getAttribute('aria-expanded')==='true';
  btn.setAttribute('aria-expanded',open?'false':'true');
  btn.textContent=open?'Показать туры':'Скрыть туры';
  tours.hidden=open;
  if(!open){prepareTourRows(tours);tours.scrollIntoView({behavior:'smooth',block:'nearest'});}
},true);
document.addEventListener('click',function(e){
  var more=e.target&&e.target.closest&&e.target.closest('.tour-more-toggle');if(!more)return;
  var tours=more.closest('.hotel-tours');if(tours)setTimeout(function(){prepareTourRows(tours);},0);
},true);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',enhanceResults,{once:true});else enhanceResults();
})();
