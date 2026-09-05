

/* donor:search3-maket7-lock.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function ensureSelectedConfidenceGridLock(){
  if(document.getElementById('search3-selected-confidence-grid-lock'))return;
  var style=document.createElement('style');
  style.id='search3-selected-confidence-grid-lock';
  style.textContent='@media(min-width:1000px){html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-confidence{grid-column:1/3!important;width:100%!important;max-width:none!important;min-width:0!important;box-sizing:border-box!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-confidence>div:first-child{min-width:180px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-confidence-steps{min-width:0!important}}';
  document.head.appendChild(style);
}
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function placeRegionInPrimary(form,main){
  var f=field(form,'region'),dates=main&&main.querySelector('.search3-dates');
  if(!f||!main||!dates)return;
  f.classList.add('search3-region');
  var label=f.querySelector(':scope > span');if(label)label.textContent='Курорт / регион';
  if(f.parentNode!==main||f.nextElementSibling!==dates)main.insertBefore(f,dates);
}
function placeAdvanced(form,grid,name,labelText,cls){
  var f=field(form,name);if(!f||!grid)return;
  f.classList.add(cls);
  var label=f.querySelector(':scope > span');if(label)label.textContent=labelText;
  grid.appendChild(f);
}
function syncLabels(form){var dates=form.querySelector('.search3-dates>:scope > span,.search3-dates>span');if(dates)dates.textContent='Дата вылета';}
function desktopLock(){
  ensureSelectedConfidenceGridLock();
  var form=document.getElementById('tourSearch');if(!form)return;
  var main=form.querySelector('.search3-primary-grid');
  var quality=document.getElementById('search3AdvancedSearch')||form.querySelector('.search3-quality');
  var grid=quality&&quality.querySelector('.search3-quality__grid');
  var quick=form.querySelector('.search3-quick');
  var operatorField=field(form,'operator');
  placeRegionInPrimary(form,main);syncLabels(form);
  if(operatorField)operatorField.style.setProperty('display','none','important');
  if(window.innerWidth>760){
    if(quality){quality.hidden=false;quality.style.setProperty('display','block','important');}
    if(grid){
      grid.style.setProperty('display','grid','important');
      placeAdvanced(form,grid,'stars','Категория отеля','search3-stars');
      placeAdvanced(form,grid,'rating','Оценка отеля','search3-rating');
      placeAdvanced(form,grid,'food','Питание','search3-meal');
      placeAdvanced(form,grid,'price_till','Бюджет на тур','search3-budget');
      placeAdvanced(form,grid,'hotel','Конкретный отель','search3-hotel');
    }
    if(quick&&quick.children.length){quick.hidden=false;quick.style.setProperty('display','flex','important');}
    form.classList.add('search3-desktop-two-row');
  }else{
    if(quality)quality.style.removeProperty('display');
    if(grid)grid.style.removeProperty('display');
    if(quick)quick.style.removeProperty('display');
    form.classList.remove('search3-desktop-two-row');
  }
}
function lock(){ensureSelectedConfidenceGridLock();desktopLock();setTimeout(desktopLock,80);setTimeout(desktopLock,180);setTimeout(desktopLock,550);}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',lock,{once:true});else lock();
window.addEventListener('resize',desktopLock);
window.addEventListener('v2:search-reset',lock);
window.addEventListener('v2:results-rendered',desktopLock);
})();
