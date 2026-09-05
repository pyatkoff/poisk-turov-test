/* donor:search3-mobile-search-entry.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
var form=document.getElementById('tourSearch');if(!form)return;
function ensureStyles(){if(document.getElementById('search3-mobile-search-entry-style'))return;var s=document.createElement('style');s.id='search3-mobile-search-entry-style';s.textContent='.search3-mobile-search-entry{display:none!important}@media(max-width:760px){body.search3-candidate #tourSearch .search3-mobile-search-entry{display:grid!important;gap:10px!important;margin:11px 0 0!important;padding:11px 0 0!important;border-top:1px solid #e6ebf2!important}.search3-mobile-search-trust{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:6px!important}.search3-mobile-search-trust span{display:flex!important;align-items:center!important;justify-content:center!important;gap:4px!important;min-width:0!important;padding:7px 4px!important;border-radius:7px!important;background:#f6f8fb!important;color:#475467!important;font-size:7px!important;font-weight:800!important;text-align:center!important}.search3-mobile-search-trust b{color:#1463ff!important;font-size:9px!important}.search3-mobile-search-filter-button{width:100%!important;min-height:42px!important;border:1px solid #cfd9e8!important;border-radius:8px!important;background:#fff!important;color:#155eef!important;font:850 10px/1 var(--at-font)!important}.search3-mobile-search-filter-button span{margin-right:5px!important}.search3-mobile-advanced-open .search3-quality{display:block!important;margin-top:10px!important}.search3-mobile-advanced-open .search3-quality__grid{display:grid!important;grid-template-columns:1fr!important;gap:9px!important}.search3-mobile-advanced-open .search3-quick{display:grid!important;grid-template-columns:1fr 1fr!important;gap:10px 8px!important;margin-top:10px!important}.search3-mobile-advanced-open .search3-quick__label{display:inline-flex!important;width:100%!important}}';document.head.appendChild(s);}
function ensure(){
  ensureStyles();
  if(form.querySelector('.search3-mobile-search-entry'))return;
  var box=document.createElement('section');
  box.className='search3-mobile-search-entry';
  box.setAttribute('aria-label','Дополнительные возможности поиска');
  box.innerHTML='<div class="search3-mobile-search-trust"><span><b>✓</b>Актуальные цены</span><span><b>✈</b>Детали перелёта</span><span><b>?</b>Помощь менеджера</span></div><button type="button" class="search3-mobile-search-filter-button" aria-expanded="false"><span>☷</span><b>Фильтры</b></button>';
  var extras=form.querySelector(':scope > details.extras');if(extras)form.insertBefore(box,extras);else form.appendChild(box);
  var btn=box.querySelector('.search3-mobile-search-filter-button'),label=btn.querySelector('b');
  btn.addEventListener('click',function(){
    var open=!form.classList.contains('search3-mobile-advanced-open');
    form.classList.toggle('search3-mobile-advanced-open',open);
    btn.setAttribute('aria-expanded',open?'true':'false');
    if(label)label.textContent=open?'Скрыть фильтры':'Фильтры';
    if(open){var quality=form.querySelector('.search3-quality');if(quality)setTimeout(function(){quality.scrollIntoView({behavior:'smooth',block:'nearest'});},0);}
  });
}
function sync(){
  ensure();
  var hasResults=document.body.classList.contains('search3-has-results');
  var box=form.querySelector('.search3-mobile-search-entry');
  if(box)box.hidden=hasResults;
  if(hasResults)form.classList.remove('search3-mobile-advanced-open');
}
window.addEventListener('v2:results-rendered',function(){setTimeout(sync,0);});
window.addEventListener('v2:search-reset',function(){setTimeout(sync,0);});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',sync,{once:true});else sync();
})();


