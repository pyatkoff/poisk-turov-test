(function(){'use strict';
function placeRegionInPrimary(){
  var form=document.getElementById('tourSearch');
  if(!form||!form.elements||!form.elements.region)return;
  var field=form.elements.region.closest&&form.elements.region.closest('.field');
  var main=form.querySelector('.search3-primary-grid');
  var dates=main&&main.querySelector('.search3-dates');
  if(!field||!main||!dates)return;
  field.classList.add('search3-region');
  var label=field.querySelector(':scope > span');if(label)label.textContent='Курорт / регион';
  if(field.parentNode!==main||field.nextElementSibling!==dates)main.insertBefore(field,dates);
}
function lock(){placeRegionInPrimary();setTimeout(placeRegionInPrimary,140);setTimeout(placeRegionInPrimary,500);}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',lock,{once:true});else lock();
window.addEventListener('resize',placeRegionInPrimary);
window.addEventListener('v2:search-reset',placeRegionInPrimary);
})();
