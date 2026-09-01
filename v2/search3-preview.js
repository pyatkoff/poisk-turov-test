(function(){'use strict';
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function init(){
  var form=document.getElementById('tourSearch');if(!form||form.dataset.search3Ready==='1')return;
  form.dataset.search3Ready='1';document.body.classList.add('search3-preview');
  var hero=document.querySelector('.v2-product-hero');if(hero)hero.hidden=true;
  var title=form.querySelector('.search-section-title');if(title)title.hidden=true;
  var main=form.querySelector('.main-fields'),extras=form.querySelector(':scope > details.extras');if(!main||!extras)return;
  [['region','search3-resort'],['stars','search3-stars'],['rating','search3-rating'],['food','search3-meal'],['price_till','search3-budget'],['hotel','search3-hotel']].forEach(function(x){var f=field(form,x[0]);if(!f)return;f.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7','result-filter-priority','result-filter-stars','result-filter-meal');f.classList.add(x[1]);var select=f.querySelector('select');if(select){select.classList.remove('ux-native-hidden','meal-native-select');select.removeAttribute('aria-hidden');select.tabIndex=0;}var quick=f.querySelector('.stars-quick,.meal-quick');if(quick)quick.hidden=true;});
  var price=field(form,'price_till');if(price){var label=price.querySelector(':scope > span');if(label)label.textContent='Цена до';}
  var rating=field(form,'rating');if(rating){var rlabel=rating.querySelector(':scope > span');if(rlabel)rlabel.textContent='Рейтинг от';}
  var stars=field(form,'stars');if(stars){var slabel=stars.querySelector(':scope > span');if(slabel)slabel.textContent='Звёздность';}
  var region=field(form,'region');if(region){var reglabel=region.querySelector(':scope > span');if(reglabel)reglabel.textContent='Курорт / район';}
  var filters=document.createElement('div');filters.className='search3-mockup-filters';
  [price,rating,stars,field(form,'food'),region,field(form,'hotel')].forEach(function(f){if(f)filters.appendChild(f);});
  var direct=form.elements.onlyDirect;if(direct){var directLabel=direct.closest('label');if(directLabel){directLabel.classList.add('search3-filter-chip');filters.appendChild(directLabel);}}
  var instant=document.createElement('label');instant.className='search3-filter-chip search3-filter-chip--instant';instant.innerHTML='<input type="checkbox" data-search3-instant><span>⚡ Моментальное подтверждение</span>';filters.appendChild(instant);
  var more=document.createElement('button');more.type='button';more.className='search3-more-filters';more.textContent='☷ Ещё фильтры';filters.appendChild(more);
  main.parentNode.insertBefore(filters,extras);
  var summary=extras.querySelector(':scope > summary');if(summary)summary.innerHTML='<strong>Ещё фильтры</strong>';
  more.addEventListener('click',function(){extras.open=!extras.open;if(extras.open)extras.scrollIntoView({behavior:'smooth',block:'nearest'});});
  extras.classList.remove('result-filter-rail');
  setTimeout(function(){['stars','food'].forEach(function(name){var f=field(form,name);if(f&&f.parentNode!==filters){var s=f.querySelector('select');if(s){s.classList.remove('ux-native-hidden','meal-native-select');s.removeAttribute('aria-hidden');s.tabIndex=0;}var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;filters.insertBefore(f,more);}});},80);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(init,20);},{once:true});else setTimeout(init,20);
})();
