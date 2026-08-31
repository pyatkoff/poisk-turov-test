(function(){'use strict';
const form=document.getElementById('tourSearch');if(!form)return;
const details=form.querySelector('details.extras');if(!details)return;
const main=form.querySelector('.main-fields');
const extraGrid=details.querySelector(':scope > .extra-grid');
const summary=details.querySelector(':scope > summary');
const servicePicker=details.querySelector('.service-picker');

function normalizePrimaryLayout(){
  if(!main||!extraGrid)return;
  const stars=form.elements.stars;
  const starsField=stars&&stars.closest('.field');
  if(starsField){
    starsField.classList.remove('field-wide','main-stars','primary-step','primary-step-6');
    starsField.classList.add('result-filter-stars');
    const quick=starsField.querySelector('.stars-quick');
    if(quick)quick.hidden=true;
    if(stars){stars.classList.remove('ux-native-hidden');stars.removeAttribute('aria-hidden');stars.tabIndex=0;}
    const rating=form.elements.rating;
    const ratingField=rating&&rating.closest('.field');
    if(ratingField&&ratingField.parentElement===extraGrid)extraGrid.insertBefore(starsField,ratingField);else extraGrid.appendChild(starsField);
  }
  [...main.querySelectorAll('.primary-step')].forEach((field,i)=>{
    field.classList.remove('primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6');
    field.classList.add('primary-step-'+(i+1));
  });
  const title=form.querySelector('.search-section-title');
  if(title)title.innerHTML='<span>Параметры поездки</span><small>Маршрут, даты, длительность и туристы</small>';
  form.classList.add('search-params-filter-split');
  details.classList.add('result-filter-rail');
}

function keepFilterLabel(){
  if(!summary)return;
  const strong=summary.querySelector('strong');
  if(strong&&strong.textContent!=='Фильтры результатов')strong.textContent='Фильтры результатов';
}

normalizePrimaryLayout();
keepFilterLabel();
if(summary&&typeof MutationObserver!=='undefined')new MutationObserver(keepFilterLabel).observe(summary,{childList:true,subtree:true,characterData:true});

let revealed=false;
window.addEventListener('v2:results-rendered',()=>{
  if(revealed||window.innerWidth<821)return;
  revealed=true;
  details.open=true;
  if(servicePicker)servicePicker.open=true;
  keepFilterLabel();
});
})();
