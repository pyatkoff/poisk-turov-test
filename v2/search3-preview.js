(function(){'use strict';
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function after(node,ref){if(!node||!ref||!ref.parentNode)return;ref.parentNode.insertBefore(node,ref.nextSibling);}
function init(){
  var form=document.getElementById('tourSearch');if(!form||form.dataset.search3Ready==='1')return;
  form.dataset.search3Ready='1';document.body.classList.add('search3-preview');
  var hero=document.querySelector('.v2-product-hero__content');if(hero){var h=hero.querySelector('h1'),p=hero.querySelector('p'),e=hero.querySelector('.v2-product-hero__eyebrow');if(e)e.textContent='Расширенный подбор тура';if(h)h.textContent='Найдите тур под ваш сценарий отдыха';if(p)p.textContent='Сначала задайте поездку и качество отеля, затем уточняйте детали уже по найденным предложениям.';}
  var title=form.querySelector('.search-section-title');if(title)title.innerHTML='<span>Поездка</span><small>Маршрут, даты, длительность и туристы</small>';
  var main=form.querySelector('.main-fields'),extras=form.querySelector(':scope > details.extras');if(!main||!extras)return;
  var quality=document.createElement('section');quality.className='search3-quality';quality.innerHTML='<div class="search3-quality__title"><strong>Отель и условия отдыха</strong><span>Главные критерии до запуска поиска</span></div><div class="search3-quality__grid"></div>';
  main.parentNode.insertBefore(quality,main.nextSibling);
  var grid=quality.querySelector('.search3-quality__grid');
  [['region','search3-resort'],['stars','search3-stars'],['rating','search3-rating'],['food','search3-meal'],['price_till','search3-budget'],['hotel','search3-hotel']].forEach(function(x){var f=field(form,x[0]);if(!f)return;f.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7','result-filter-priority','result-filter-stars','result-filter-meal');f.classList.add(x[1]);var select=f.querySelector('select');if(select){select.classList.remove('ux-native-hidden','meal-native-select');select.removeAttribute('aria-hidden');select.tabIndex=0;}var quick=f.querySelector('.stars-quick,.meal-quick');if(quick)quick.hidden=true;grid.appendChild(f);});
  var price=field(form,'price_till');if(price){var label=price.querySelector(':scope > span');if(label)label.textContent='Бюджет до';}
  var rating=field(form,'rating');if(rating){var rlabel=rating.querySelector(':scope > span');if(rlabel)rlabel.textContent='Оценка отеля';}
  var stars=field(form,'stars');if(stars){var slabel=stars.querySelector(':scope > span');if(slabel)slabel.textContent='Звёздность';}
  var region=field(form,'region');if(region){var reglabel=region.querySelector(':scope > span');if(reglabel)reglabel.textContent='Курорт / регион';}
  var quick=document.createElement('div');quick.className='search3-quick';quick.innerHTML='<label class="search3-quick__label search3-quick__label--instant"><input type="checkbox" disabled><span>⚡ Моментальное подтверждение</span><small>подключим только после проверки поля Tourvisor</small></label><span class="search3-preview-note">В preview не имитируем неподтверждённые данные</span>';
  after(quick,quality);
  var direct=form.elements.onlyDirect;if(direct){var directLabel=direct.closest('label');if(directLabel){directLabel.classList.add('search3-quick__label');quick.insertBefore(directLabel,quick.querySelector('.search3-preview-note'));}}
  var operator=field(form,'operator');if(operator)operator.classList.add('search3-deep-filter');
  var summary=extras.querySelector(':scope > summary');if(summary)summary.innerHTML='<strong>Дополнительные параметры</strong><span>аэропорт, район, тип отеля, туроператор и услуги</span>';
  extras.classList.remove('result-filter-rail');
  setTimeout(function(){
    [['stars','search3-stars'],['food','search3-meal']].forEach(function(x){var f=field(form,x[0]);if(f&&f.parentNode!==grid){f.classList.remove('result-filter-priority','result-filter-stars','result-filter-meal','main-stars','main-meal','primary-step','primary-step-6','primary-step-7');f.classList.add(x[1]);var s=f.querySelector('select');if(s){s.classList.remove('ux-native-hidden','meal-native-select');s.removeAttribute('aria-hidden');s.tabIndex=0;}var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;grid.appendChild(f);}});
  },80);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(init,20);},{once:true});else setTimeout(init,20);
})();
