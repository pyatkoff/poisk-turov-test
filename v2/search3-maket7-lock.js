(function(){'use strict';
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
  if(f.parentNode!==grid)grid.appendChild(f);
}
function desktopLock(){
  var form=document.getElementById('tourSearch');if(!form)return;
  var main=form.querySelector('.search3-primary-grid');
  var quality=document.getElementById('search3AdvancedSearch')||form.querySelector('.search3-quality');
  var grid=quality&&quality.querySelector('.search3-quality__grid');
  var quick=form.querySelector('.search3-quick');
  placeRegionInPrimary(form,main);
  if(window.innerWidth>760){
    if(quality){quality.hidden=false;quality.style.setProperty('display','block','important');}
    if(grid){
      grid.style.setProperty('display','grid','important');
      placeAdvanced(form,grid,'stars','Категория отеля','search3-stars');
      placeAdvanced(form,grid,'food','Питание','search3-meal');
      placeAdvanced(form,grid,'price_till','Бюджет на тур','search3-budget');
      placeAdvanced(form,grid,'hotel','Конкретный отель','search3-hotel');
      placeAdvanced(form,grid,'operator','Туроператор','search3-operator');
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
function lock(){desktopLock();setTimeout(desktopLock,80);setTimeout(desktopLock,180);setTimeout(desktopLock,550);}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',lock,{once:true});else lock();
window.addEventListener('resize',desktopLock);
window.addEventListener('v2:search-reset',lock);
window.addEventListener('v2:results-rendered',desktopLock);
})();
