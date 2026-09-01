(function(){'use strict';
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function relabel(form,name,label){var f=field(form,name),s=f&&f.querySelector(':scope > span');if(s)s.textContent=label;}
function cleanField(f){if(!f)return;f.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7','result-filter-priority','result-filter-stars','result-filter-meal');var select=f.querySelector('select');if(select){select.classList.remove('ux-native-hidden','meal-native-select');select.removeAttribute('aria-hidden');select.tabIndex=0;}var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;}
function init(){
  var form=document.getElementById('tourSearch');if(!form||form.dataset.search3Ready==='1')return;
  form.dataset.search3Ready='1';document.body.classList.add('search3-preview');
  var hero=document.querySelector('.v2-product-hero');if(hero)hero.hidden=true;

  var shell=form.parentNode;
  if(shell&&!document.querySelector('.search3-page-intro')){
    var intro=document.createElement('div');intro.className='search3-page-intro';intro.innerHTML='<div class="search3-breadcrumb">Главная <span>›</span> Поиск туров</div><h1>Поиск туров</h1><p>Найдите туры по лучшим ценам от надежных туроператоров</p>';
    shell.insertBefore(intro,form);
  }

  var title=form.querySelector('.search-section-title');if(title)title.hidden=true;
  var main=form.querySelector('.main-fields'),extras=form.querySelector(':scope > details.extras');if(!main||!extras)return;
  main.classList.add('search3-primary-grid');

  var primary=['from','country','region','dateFrom','daysFrom','count_people'];
  primary.forEach(function(name){var f=field(form,name);if(f){cleanField(f);main.appendChild(f);}});
  relabel(form,'from','Откуда');relabel(form,'country','Куда');relabel(form,'region','Курорт / регион');relabel(form,'dateFrom','Дата вылета');relabel(form,'daysFrom','Ночей');relabel(form,'count_people','Туристы');

  var submit=form.querySelector(':scope > .search-submit');if(submit){submit.textContent='Найти туры →';main.appendChild(submit);}

  var quality=document.createElement('section');quality.className='search3-quality';quality.innerHTML='<div class="search3-quality__grid"></div>';
  main.parentNode.insertBefore(quality,main.nextSibling);
  var grid=quality.querySelector('.search3-quality__grid');
  [['stars','search3-stars'],['rating','search3-rating'],['food','search3-meal'],['price_till','search3-budget'],['hotel','search3-hotel']].forEach(function(x){var f=field(form,x[0]);if(!f)return;cleanField(f);f.classList.add(x[1]);grid.appendChild(f);});
  relabel(form,'stars','Категория отеля');relabel(form,'rating','Оценка отеля');relabel(form,'food','Питание');relabel(form,'price_till','Бюджет на тур');relabel(form,'hotel','Конкретный отель');

  var quick=document.createElement('div');quick.className='search3-quick';
  var flight=document.createElement('label');flight.className='search3-quick__label search3-quick__label--static';flight.innerHTML='<input type="checkbox" checked><span>Только с перелётом</span>';quick.appendChild(flight);
  var instant=document.createElement('label');instant.className='search3-quick__label search3-quick__label--instant';instant.innerHTML='<input type="checkbox" data-search3-instant><span>⚡ Моментальное подтверждение</span>';quick.appendChild(instant);
  var direct=form.elements.onlyDirect;if(direct){var dl=direct.closest('label');if(dl){dl.classList.add('search3-quick__label');quick.appendChild(dl);}}
  var seats=document.createElement('label');seats.className='search3-quick__label search3-quick__label--optional';seats.innerHTML='<input type="checkbox" data-search3-seats><span>Есть места</span>';quick.appendChild(seats);
  var hot=document.createElement('label');hot.className='search3-quick__label search3-quick__label--optional';hot.innerHTML='<input type="checkbox" data-search3-hot><span>Горящие туры</span>';quick.appendChild(hot);
  quality.parentNode.insertBefore(quick,extras);

  extras.classList.remove('result-filter-rail');extras.hidden=true;

  setTimeout(function(){
    ['region','stars','food'].forEach(function(name){var f=field(form,name);if(!f)return;cleanField(f);if(name==='region'&&f.parentNode!==main)main.insertBefore(f,field(form,'dateFrom'));if(name!=='region'&&f.parentNode!==grid)grid.appendChild(f);});
  },80);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(init,20);},{once:true});else setTimeout(init,20);
})();
