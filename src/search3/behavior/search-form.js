/* Search3 safe candidate: approved donor composition. Source e5baf32f455cdb0aa1a704964f28e5efbebf57ff. */
/* donor:search3-candidate.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function cleanField(f){if(!f)return;f.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7','result-filter-priority','result-filter-stars','result-filter-meal');var select=f.querySelector('select');if(select){select.classList.remove('ux-native-hidden','meal-native-select');select.removeAttribute('aria-hidden');select.tabIndex=0;}var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;}
function makeComposite(label,cls){var box=document.createElement('label');box.className='field search3-composite '+cls;box.innerHTML='<span>'+label+'</span><div class="search3-composite__control"></div>';return box;}
function formatDate(v){var p=String(v||'').split('-');return p.length===3?p[2]+'.'+p[1]+'.'+p[0]:String(v||'');}
function clampNight(v){var n=parseInt(v,10);if(!Number.isFinite(n))n=7;return Math.max(1,Math.min(28,n));}
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
  var submit=form.querySelector(':scope > .search-submit');if(childAges)childAges.remove();main.innerHTML='';main.className='main-fields search3-primary-grid';
  function appendOriginal(name,label,cls){var f=originalFields[name];if(!f)return null;cleanField(f);var s=f.querySelector(':scope > span');if(s)s.textContent=label;if(cls)f.classList.add(cls);main.appendChild(f);return f;}
  appendOriginal('from','Откуда','search3-from');appendOriginal('country','Куда','search3-country');
  var region=field(form,'region');if(region){cleanField(region);var rs=region.querySelector(':scope > span');if(rs)rs.textContent='Курорт / регион';region.classList.add('search3-region');main.appendChild(region);}
  var d1=refs.dateFrom,d2=refs.dateTo;if(d1&&d2){var dateBox=makeComposite('Дата вылета','search3-dates'),dateCtl=dateBox.querySelector('.search3-composite__control');d1.classList.add('search3-direct-control');d2.classList.add('search3-direct-control');d1.setAttribute('aria-label','Вылет не раньше');d2.setAttribute('aria-label','Вылет не позже');dateCtl.appendChild(d1);var dash=document.createElement('span');dash.className='search3-composite__dash';dash.textContent='—';dateCtl.appendChild(dash);dateCtl.appendChild(d2);main.appendChild(dateBox);}
  var n1=refs.daysFrom,n2=refs.daysTill;if(n1&&n2){
    var nightBox=makeComposite('Ночей','search3-nights'),nightCtl=nightBox.querySelector('.search3-composite__control');
    function nightSelect(input,label){
      var select=document.createElement('select');select.className='search3-direct-control';select.setAttribute('aria-label',label);select.dataset.search3Night=input.name;
      for(var i=1;i<=28;i++){var option=document.createElement('option');option.value=String(i);option.textContent=String(i);select.appendChild(option);}
      function sync(){select.value=String(clampNight(input.value));}
      input.hidden=true;input.tabIndex=-1;input.setAttribute('aria-hidden','true');
      input.addEventListener('input',sync);input.addEventListener('change',sync);select.addEventListener('focus',sync);
      select.addEventListener('change',function(){input.value=select.value;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));});
      form.addEventListener('reset',function(){setTimeout(sync,0);});sync();nightCtl.appendChild(input);nightCtl.appendChild(select);
    }
    nightSelect(n1,'Минимум ночей');var nd=document.createElement('span');nd.className='search3-composite__dash';nd.textContent='—';nightCtl.appendChild(nd);nightSelect(n2,'Максимум ночей');main.appendChild(nightBox);
  }
  var adults=refs.count_people,children=refs.child_count,touristBox=null;if(adults&&children){touristBox=makeComposite('Туристы','search3-tourists');var touristCtl=touristBox.querySelector('.search3-composite__control');var summary=document.createElement('button');summary.type='button';summary.className='search3-tourists__summary';var pop=document.createElement('div');pop.className='search3-tourists__pop';pop.hidden=true;pop.innerHTML='<span>Взрослых</span>';pop.appendChild(adults);var ch=document.createElement('span');ch.textContent='Детей';pop.appendChild(ch);pop.appendChild(children);if(childAges){childAges.classList.add('search3-tourists__ages');pop.appendChild(childAges);}function syncGuests(){var a=Number(adults.value||2),c=Number(children.value||0);summary.textContent=a+' '+(a===1?'взрослый':'взрослых')+(c?' · '+c+' '+(c===1?'ребёнок':'детей'):'');}summary.addEventListener('click',function(e){e.preventDefault();pop.hidden=!pop.hidden;});adults.addEventListener('change',syncGuests);children.addEventListener('change',syncGuests);syncGuests();touristCtl.appendChild(summary);touristCtl.appendChild(pop);main.appendChild(touristBox);}
  if(submit){submit.innerHTML='<span>Найти туры</span><b aria-hidden="true">→</b>';main.appendChild(submit);}
  var quality=document.createElement('section');quality.className='search3-quality';quality.innerHTML='<div class="search3-quality__grid"></div>';main.parentNode.insertBefore(quality,main.nextSibling);var grid=quality.querySelector('.search3-quality__grid');
  [['stars','Категория отеля','search3-stars'],['rating','Оценка отеля','search3-rating'],['food','Питание','search3-meal'],['price_till','Бюджет на тур','search3-budget'],['hotel','Конкретный отель','search3-hotel']].forEach(function(x){var f=field(form,x[0]);if(!f)return;cleanField(f);var s=f.querySelector(':scope > span');if(s)s.textContent=x[1];f.classList.add(x[2]);grid.appendChild(f);});
  var budget=form.elements.price_till;if(budget)budget.placeholder='до 250 000 ₽';var hotel=form.elements.hotel;if(hotel)hotel.setAttribute('aria-label','Конкретный отель');
  var quick=document.createElement('div');quick.className='search3-quick';
  var flight=document.createElement('label');flight.className='search3-quick__label search3-quick__label--static';flight.innerHTML='<input type="checkbox" checked disabled><span>Только с перелётом</span>';quick.appendChild(flight);
  var direct=form.elements.onlyDirect;if(direct){var directLabel=document.createElement('label');directLabel.className='search3-quick__label search3-quick__label--direct';directLabel.appendChild(direct);var directText=document.createElement('span');directText.textContent='Прямой рейс';directLabel.appendChild(directText);quick.appendChild(directLabel);}
  quality.parentNode.insertBefore(quick,extras);extras.classList.remove('result-filter-rail');extras.hidden=true;
  if(touristBox)document.addEventListener('click',function(e){if(!touristBox.contains(e.target)){var p=touristBox.querySelector('.search3-tourists__pop');if(p)p.hidden=true;}},true);
  setTimeout(function(){['region','stars','rating','food'].forEach(function(name){var f=field(form,name);if(!f)return;cleanField(f);if(name==='region'){var s=f.querySelector(':scope > span');if(s)s.textContent='Курорт / регион';f.classList.add('search3-region');if(f.parentNode!==main){var dateBoxNow=main.querySelector('.search3-dates');main.insertBefore(f,dateBoxNow||main.children[2]||null);}}else{if(name==='stars'){var st=f.querySelector(':scope > span');if(st)st.textContent='Категория отеля';}if(name==='rating'){var rt=f.querySelector(':scope > span');if(rt)rt.textContent='Оценка отеля';}if(name==='food'){var ft=f.querySelector(':scope > span');if(ft)ft.textContent='Питание';}if(f.parentNode!==grid)grid.appendChild(f);}});},100);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(init,20);},{once:true});else setTimeout(init,20);
})();
