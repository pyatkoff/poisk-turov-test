(function(){'use strict';
const form=document.getElementById('tourSearch');if(!form)return;
const details=form.querySelector('details.extras');if(!details)return;
const main=form.querySelector('.main-fields');
const extraGrid=details.querySelector(':scope > .extra-grid');
const summary=details.querySelector(':scope > summary');
const servicePicker=details.querySelector('.service-picker');
let normalized=false;

function restoreSelect(select,classes){
  if(!select)return;
  classes.forEach(name=>select.classList.remove(name));
  select.removeAttribute('aria-hidden');
  select.tabIndex=0;
}
function moveFilter(name,beforeName,fieldClass,quickSelector,selectClasses){
  const select=form.elements[name],field=select&&select.closest('.field');if(!select||!field||!extraGrid)return;
  field.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-6','primary-step-7');
  field.classList.add(fieldClass);
  const quick=field.querySelector(quickSelector);if(quick)quick.hidden=true;
  restoreSelect(select,selectClasses);
  const before=form.elements[beforeName],beforeField=before&&before.closest('.field');
  if(beforeField&&beforeField.parentElement===extraGrid)extraGrid.insertBefore(field,beforeField);else extraGrid.appendChild(field);
}
function markPriorityFilter(name){const control=form.elements[name],field=control&&control.closest('.field');if(field)field.classList.add('result-filter-priority');}
function prioritizeResultFilters(){
  ['stars','rating','food','price_from','price_till'].forEach(markPriorityFilter);
  const flight=form.elements.onlyDirect,flightField=flight&&flight.closest('.field');if(flightField)flightField.classList.add('result-filter-priority');
  if(servicePicker&&extraGrid&&servicePicker.parentElement===extraGrid.parentElement){
    servicePicker.classList.add('result-filter-services-first');
    extraGrid.parentElement.insertBefore(servicePicker,extraGrid);
  }
}
function normalizePrimaryLayout(){
  if(!main||!extraGrid)return;
  moveFilter('stars','rating','result-filter-stars','.stars-quick',['ux-native-hidden']);
  moveFilter('food','price_from','result-filter-meal','.meal-quick',['meal-native-select','ux-native-hidden']);
  prioritizeResultFilters();
  [...main.querySelectorAll('.primary-step')].forEach((field,i)=>{
    field.classList.remove('primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7');
    field.classList.add('primary-step-'+(i+1));
  });
  const title=form.querySelector('.search-section-title');
  if(title)title.innerHTML='<span>Параметры поездки</span><small>Маршрут, даты, длительность и туристы</small>';
  form.classList.add('search-params-filter-split');
  details.classList.add('result-filter-rail');
  normalized=true;
}
function keepFilterLabel(){
  if(!summary)return;
  const strong=summary.querySelector('strong');
  if(strong&&strong.textContent!=='Фильтры результатов')strong.textContent='Фильтры результатов';
}
// Star/meal enhancers also initialize on DOMContentLoaded; defer one task so this layer is the final owner of search-vs-filter placement.
function scheduleNormalize(){setTimeout(()=>{normalizePrimaryLayout();keepFilterLabel();},0);}

if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scheduleNormalize,{once:true});else scheduleNormalize();
keepFilterLabel();
if(summary&&typeof MutationObserver!=='undefined')new MutationObserver(()=>{keepFilterLabel();if(normalized){const stars=form.elements.stars,food=form.elements.food;if(stars&&main.contains(stars.closest('.field')))scheduleNormalize();if(food&&main.contains(food.closest('.field')))scheduleNormalize();}}).observe(summary,{childList:true,subtree:true,characterData:true});

let revealed=false;
window.addEventListener('v2:results-rendered',()=>{
  if(!normalized)scheduleNormalize();
  if(revealed||window.innerWidth<821)return;
  revealed=true;
  details.open=true;
  if(servicePicker)servicePicker.open=true;
  keepFilterLabel();
});
})();
