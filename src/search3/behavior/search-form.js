/* Search3 safe candidate: approved donor composition. Source e5baf32f455cdb0aa1a704964f28e5efbebf57ff. */
/* donor:search3-candidate.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function cleanField(f){if(!f)return;f.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7','result-filter-priority','result-filter-stars','result-filter-meal');var select=f.querySelector('select');if(select){select.classList.remove('ux-native-hidden','meal-native-select');select.removeAttribute('aria-hidden');select.tabIndex=0;}var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;}
function makeComposite(label,cls){var box=document.createElement('label');box.className='field search3-composite '+cls;box.innerHTML='<span>'+label+'</span><div class="search3-composite__control"></div>';return box;}
function clampNight(v){var n=parseInt(v,10);if(!Number.isFinite(n))n=7;return Math.max(1,Math.min(28,n));}
/* @include behavior/search-form/entry-presentation.js */
function init(){
  var form=document.getElementById('tourSearch');if(!form||form.dataset.search3Ready==='1')return;
  form.dataset.search3Ready='1';document.body.classList.add('search3-candidate');
  var hero=document.querySelector('.v2-product-hero');if(hero){var legacyH1=hero.querySelector('h1');if(legacyH1)legacyH1.remove();hero.hidden=true;}
  var shell=form.parentNode;if(shell&&!document.querySelector('.search3-page-intro')){var intro=document.createElement('div');intro.className='search3-page-intro';intro.innerHTML='<div class="search3-breadcrumb">Главная <span>›</span> Поиск туров</div><h1>Поиск туров</h1><p>Найдите туры по лучшим ценам от надежных туроператоров</p>';shell.insertBefore(intro,form);}
  var title=form.querySelector('.search-section-title');if(title)title.hidden=true;
  var main=form.querySelector('.main-fields'),extras=form.querySelector(':scope > details.extras');if(!main||!extras)return;
  var refs={};['from','country','dateFrom','dateTo','daysFrom','daysTill','count_people','child_count'].forEach(function(name){refs[name]=form.elements[name]||null;});
  var childAges=document.getElementById('childAges');
  var originalFields={};Object.keys(refs).forEach(function(name){var el=refs[name];originalFields[name]=el&&el.closest?el.closest('.field'):null;});
  var submit=form.querySelector(':scope > .search-submit'),starsField=field(form,'stars'),legacyGrid=extras.querySelector('.extra-grid'),ratingField=field(form,'rating');if(starsField&&main.contains(starsField)&&legacyGrid)legacyGrid.insertBefore(starsField,ratingField&&ratingField.parentNode===legacyGrid?ratingField:null);if(childAges)childAges.remove();main.innerHTML='';main.className='main-fields search3-primary-grid';
  function appendOriginal(name,label,cls){var f=originalFields[name];if(!f)return null;cleanField(f);var s=f.querySelector(':scope > span');if(s)s.textContent=label;if(cls)f.classList.add(cls);main.appendChild(f);return f;}
  appendOriginal('from','Откуда','search3-from');appendOriginal('country','Куда','search3-country');
  var region=field(form,'region');if(region){cleanField(region);var rs=region.querySelector(':scope > span');if(rs)rs.textContent='Курорт / регион';region.classList.add('search3-region');main.appendChild(region);}
/* @include behavior/search-form/primary-controls.js */
  if(submit){submit.innerHTML='<span>Найти туры</span><b aria-hidden="true">→</b>';main.appendChild(submit);}
/* @include behavior/search-form/secondary-controls.js */
  installEntryPresentation(form,main,grid,region);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(init,20);},{once:true});else setTimeout(init,20);
})();
