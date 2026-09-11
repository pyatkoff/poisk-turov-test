(function(){'use strict';
const form=document.getElementById('tourSearch');if(!form)return;
const details=form.querySelector('details.extras');
const preferences=form.querySelector('.search-preferences');
if(!details||!preferences)return;

function fieldFor(name){const control=form.elements[name];return control&&control.closest('.field');}
function restorePrimary(name,beforeName){
  const field=fieldFor(name),before=fieldFor(beforeName);if(!field)return;
  field.classList.remove('result-filter-stars','result-filter-meal','result-filter-priority');
  if(before&&before.parentElement===preferences)preferences.insertBefore(field,before);else preferences.appendChild(field);
}
function keepSecondarySearchSemantics(){
  restorePrimary('stars','food');
  restorePrimary('food','price_from');
  details.classList.remove('result-filter-rail');
  details.classList.add('search-secondary-params');
  const summary=details.querySelector(':scope > summary');
  if(summary&&summary.firstChild&&summary.firstChild.nodeType===Node.TEXT_NODE)summary.firstChild.nodeValue='Ещё фильтры ';
  form.classList.add('search-params-filter-split');
}

// Category and meal are primary OTA search parameters. The separate canonical
// `.results-filter-rail` owns local result facets after results are loaded.
// Keep this compatibility asset as an in-place semantic guard; do not create a
// second result-filter owner or auto-open supplier-search parameters on results.
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',keepSecondarySearchSemantics,{once:true});else keepSecondarySearchSemantics();
})();
