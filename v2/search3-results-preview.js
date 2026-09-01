(function(){'use strict';
function enhanceCard(card){
  if(!card||card.dataset.search3Card==='1')return;
  card.dataset.search3Card='1';
  var tours=card.querySelector('.hotel-tours');
  var body=card.querySelector('.hotel-body');
  if(!tours||!body)return;
  tours.hidden=true;
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
  if(!open)tours.scrollIntoView({behavior:'smooth',block:'nearest'});
},true);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',enhanceResults,{once:true});else enhanceResults();
})();
