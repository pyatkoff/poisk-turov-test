(function(){'use strict';
var form=document.getElementById('tourSearch');if(!form)return;
function ensure(){
  if(document.querySelector('.search3-mobile-search-entry'))return;
  var box=document.createElement('section');
  box.className='search3-mobile-search-entry';
  box.setAttribute('aria-label','Дополнительные возможности поиска');
  box.innerHTML='<div class="search3-mobile-search-trust"><span><b>✓</b>Актуальные цены</span><span><b>✈</b>Перелёт и багаж</span><span><b>?</b>Помощь менеджера</span></div><button type="button" class="search3-mobile-search-filter-button" aria-expanded="false"><span>☷</span> Фильтры</button>';
  form.insertAdjacentElement('afterend',box);
  var btn=box.querySelector('.search3-mobile-search-filter-button');
  btn.addEventListener('click',function(){
    var open=!form.classList.contains('search3-mobile-advanced-open');
    form.classList.toggle('search3-mobile-advanced-open',open);
    btn.setAttribute('aria-expanded',open?'true':'false');
    btn.lastChild.nodeValue=open?' Скрыть фильтры':' Фильтры';
    if(open){var quality=form.querySelector('.search3-quality');if(quality)quality.scrollIntoView({behavior:'smooth',block:'nearest'});}
  });
}
function sync(){
  ensure();
  var hasResults=document.body.classList.contains('search3-has-results');
  var box=document.querySelector('.search3-mobile-search-entry');
  if(box)box.hidden=hasResults;
  if(hasResults)form.classList.remove('search3-mobile-advanced-open');
}
window.addEventListener('v2:results-rendered',function(){setTimeout(sync,0);});
window.addEventListener('v2:search-reset',function(){setTimeout(sync,0);});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',sync,{once:true});else sync();
})();
