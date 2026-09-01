(function(){'use strict';
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
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

  var quality=document.createElement('section');quality.className='search3-quality';quality.innerHTML='<div class="search3-quality__grid"></div>';
  main.parentNode.insertBefore(quality,main.nextSibling);
  var grid=quality.querySelector('.search3-quality__grid');

  [['region','search3-resort'],['stars','search3-stars'],['rating','search3-rating'],['food','search3-meal'],['price_till','search3-budget'],['hotel','search3-hotel']].forEach(function(x){
    var f=field(form,x[0]);if(!f)return;
    f.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7','result-filter-priority','result-filter-stars','result-filter-meal');
    f.classList.add(x[1]);
    var select=f.querySelector('select');if(select){select.classList.remove('ux-native-hidden','meal-native-select');select.removeAttribute('aria-hidden');select.tabIndex=0;}
    var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;
    grid.appendChild(f);
  });

  var labels={region:'Курорт / регион',stars:'Категория отеля',rating:'Оценка отеля',food:'Питание',price_till:'Бюджет на тур',hotel:'Конкретный отель'};
  Object.keys(labels).forEach(function(name){var f=field(form,name),label=f&&f.querySelector(':scope > span');if(label)label.textContent=labels[name];});

  var quick=document.createElement('div');quick.className='search3-quick';
  var flight=document.createElement('label');flight.className='search3-quick__label search3-quick__label--static';flight.innerHTML='<input type="checkbox" checked disabled><span>Только с перелётом</span>';quick.appendChild(flight);
  var instant=document.createElement('label');instant.className='search3-quick__label search3-quick__label--instant';instant.innerHTML='<input type="checkbox" data-search3-instant><span>⚡ Моментальное подтверждение</span>';quick.appendChild(instant);
  var direct=form.elements.onlyDirect;if(direct){var dl=direct.closest('label');if(dl){dl.classList.add('search3-quick__label');quick.appendChild(dl);}}
  var seats=document.createElement('label');seats.className='search3-quick__label search3-quick__label--optional';seats.innerHTML='<input type="checkbox" data-search3-seats disabled><span>Есть места</span>';quick.appendChild(seats);
  var hot=document.createElement('label');hot.className='search3-quick__label search3-quick__label--optional';hot.innerHTML='<input type="checkbox" data-search3-hot disabled><span>Горящие туры</span>';quick.appendChild(hot);
  quality.parentNode.insertBefore(quick,extras);

  var summary=extras.querySelector(':scope > summary');if(summary)summary.innerHTML='<strong>Дополнительные параметры</strong><span>аэропорт, район, тип отеля, туроператор и услуги</span>';
  extras.classList.remove('result-filter-rail');

  setTimeout(function(){
    ['stars','food'].forEach(function(name){var f=field(form,name);if(f&&f.parentNode!==grid){var s=f.querySelector('select');if(s){s.classList.remove('ux-native-hidden','meal-native-select');s.removeAttribute('aria-hidden');s.tabIndex=0;}var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;grid.appendChild(f);}});
  },80);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(init,20);},{once:true});else setTimeout(init,20);
})();
