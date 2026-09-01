(function(){'use strict';
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function cleanField(f){if(!f)return;f.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7','result-filter-priority','result-filter-stars','result-filter-meal');var select=f.querySelector('select');if(select){select.classList.remove('ux-native-hidden','meal-native-select');select.removeAttribute('aria-hidden');select.tabIndex=0;}var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;}
function makeComposite(label,cls){var box=document.createElement('label');box.className='field search3-composite '+cls;box.innerHTML='<span>'+label+'</span><div class="search3-composite__control"></div>';return box;}
function formatDate(v){var p=String(v||'').split('-');return p.length===3?p[2]+'.'+p[1]+'.'+p[0]:String(v||'');}
function clampNight(v){var n=parseInt(v,10);if(!Number.isFinite(n))n=7;return Math.max(1,Math.min(28,n));}
function init(){
  var form=document.getElementById('tourSearch');if(!form||form.dataset.search3Ready==='1')return;
  form.dataset.search3Ready='1';document.body.classList.add('search3-preview');
  var hero=document.querySelector('.v2-product-hero');if(hero)hero.hidden=true;
  var shell=form.parentNode;if(shell&&!document.querySelector('.search3-page-intro')){var intro=document.createElement('div');intro.className='search3-page-intro';intro.innerHTML='<div class="search3-breadcrumb">Главная <span>›</span> Поиск туров</div><h1>Поиск туров</h1><p>Найдите туры по лучшим ценам от надежных туроператоров</p>';shell.insertBefore(intro,form);}
  var title=form.querySelector('.search-section-title');if(title)title.hidden=true;
  var main=form.querySelector('.main-fields'),extras=form.querySelector(':scope > details.extras');if(!main||!extras)return;

  var refs={};['from','country','dateFrom','dateTo','daysFrom','daysTill','count_people','child_count'].forEach(function(name){refs[name]=form.elements[name]||null;});
  var originalFields={};Object.keys(refs).forEach(function(name){var el=refs[name];originalFields[name]=el&&el.closest?el.closest('.field'):null;});
  var submit=form.querySelector(':scope > .search-submit');
  main.innerHTML='';main.className='main-fields search3-primary-grid';

  function appendOriginal(name,label,cls){var f=originalFields[name];if(!f)return null;cleanField(f);var s=f.querySelector(':scope > span');if(s)s.textContent=label;if(cls)f.classList.add(cls);main.appendChild(f);return f;}
  appendOriginal('from','Откуда','search3-from');
  appendOriginal('country','Куда','search3-country');
  var region=field(form,'region');if(region){cleanField(region);var rs=region.querySelector(':scope > span');if(rs)rs.textContent='Курорт / регион';region.classList.add('search3-region');main.appendChild(region);}

  var d1=refs.dateFrom,d2=refs.dateTo;
  if(d1&&d2){
    var dateBox=makeComposite('Дата вылета','search3-dates'),dateCtl=dateBox.querySelector('.search3-composite__control');
    d1.classList.add('search3-native-control');d2.classList.add('search3-native-control');
    function dateButton(input){var b=document.createElement('button');b.type='button';b.className='search3-date-button';function sync(){b.textContent=formatDate(input.value)||'—';}b.addEventListener('click',function(){try{if(input.showPicker)input.showPicker();else input.focus();}catch(e){input.focus();}});input.addEventListener('change',sync);sync();return b;}
    dateCtl.appendChild(dateButton(d1));var dash=document.createElement('span');dash.className='search3-composite__dash';dash.textContent='—';dateCtl.appendChild(dash);dateCtl.appendChild(dateButton(d2));dateCtl.appendChild(d1);dateCtl.appendChild(d2);main.appendChild(dateBox);
  }

  var n1=refs.daysFrom,n2=refs.daysTill;
  if(n1&&n2){
    var nightBox=makeComposite('Ночей','search3-nights'),nightCtl=nightBox.querySelector('.search3-composite__control');n1.classList.add('search3-native-control');n2.classList.add('search3-native-control');
    function nightProxy(input){var x=document.createElement('input');x.type='text';x.inputMode='numeric';x.className='search3-night-proxy';x.value=String(clampNight(input.value));function commit(){var v=clampNight(x.value);x.value=String(v);input.value=String(v);input.dispatchEvent(new Event('change',{bubbles:true}));}x.addEventListener('blur',commit);x.addEventListener('change',commit);input.addEventListener('change',function(){x.value=String(clampNight(input.value));});return x;}
    nightCtl.appendChild(nightProxy(n1));var nd=document.createElement('span');nd.className='search3-composite__dash';nd.textContent='—';nightCtl.appendChild(nd);nightCtl.appendChild(nightProxy(n2));nightCtl.appendChild(n1);nightCtl.appendChild(n2);main.appendChild(nightBox);
  }

  var adults=refs.count_people,children=refs.child_count,touristBox=null;
  if(adults&&children){touristBox=makeComposite('Туристы','search3-tourists');var touristCtl=touristBox.querySelector('.search3-composite__control');var summary=document.createElement('button');summary.type='button';summary.className='search3-tourists__summary';var pop=document.createElement('div');pop.className='search3-tourists__pop';pop.hidden=true;pop.innerHTML='<span>Взрослых</span>';pop.appendChild(adults);var ch=document.createElement('span');ch.textContent='Детей';pop.appendChild(ch);pop.appendChild(children);function syncGuests(){var a=Number(adults.value||2),c=Number(children.value||0);summary.textContent=a+' '+(a===1?'взрослый':'взрослых')+(c?' · '+c+' '+(c===1?'ребёнок':'детей'):'');}summary.addEventListener('click',function(e){e.preventDefault();pop.hidden=!pop.hidden;});adults.addEventListener('change',syncGuests);children.addEventListener('change',syncGuests);syncGuests();touristCtl.appendChild(summary);touristCtl.appendChild(pop);main.appendChild(touristBox);}

  if(submit){submit.innerHTML='<span>Найти туры</span><b aria-hidden="true">→</b>';main.appendChild(submit);}
  var quality=document.createElement('section');quality.className='search3-quality';quality.innerHTML='<div class="search3-quality__grid"></div>';main.parentNode.insertBefore(quality,main.nextSibling);var grid=quality.querySelector('.search3-quality__grid');
  [['stars','Категория отеля','search3-stars'],['rating','Оценка отеля','search3-rating'],['food','Питание','search3-meal'],['price_till','Бюджет на тур','search3-budget'],['hotel','Конкретный отель','search3-hotel']].forEach(function(x){var f=field(form,x[0]);if(!f)return;cleanField(f);var s=f.querySelector(':scope > span');if(s)s.textContent=x[1];f.classList.add(x[2]);grid.appendChild(f);});
  var quick=document.createElement('div');quick.className='search3-quick';
  var flight=document.createElement('label');flight.className='search3-quick__label search3-quick__label--static';flight.innerHTML='<input type="checkbox" checked><span>Только с перелётом</span>';quick.appendChild(flight);
  var instant=document.createElement('label');instant.className='search3-quick__label search3-quick__label--instant';instant.innerHTML='<input type="checkbox" data-search3-instant><span>⚡ Моментальное подтверждение</span>';quick.appendChild(instant);
  var direct=form.elements.onlyDirect;if(direct){var dl=direct.closest('label');if(dl){dl.classList.add('search3-quick__label');quick.appendChild(dl);}}
  var seats=document.createElement('label');seats.className='search3-quick__label';seats.innerHTML='<input type="checkbox" data-search3-seats><span>Есть места</span>';quick.appendChild(seats);
  var hot=document.createElement('label');hot.className='search3-quick__label';hot.innerHTML='<input type="checkbox" data-search3-hot><span>Горящие туры</span>';quick.appendChild(hot);
  quality.parentNode.insertBefore(quick,extras);extras.classList.remove('result-filter-rail');extras.hidden=true;
  if(touristBox)document.addEventListener('click',function(e){if(!touristBox.contains(e.target)){var p=touristBox.querySelector('.search3-tourists__pop');if(p)p.hidden=true;}},true);
  setTimeout(function(){['region','stars','food'].forEach(function(name){var f=field(form,name);if(!f)return;cleanField(f);if(name==='region'){var s=f.querySelector(':scope > span');if(s)s.textContent='Курорт / регион';f.classList.add('search3-region');if(f.parentNode!==main){var dateBoxNow=main.querySelector('.search3-dates');main.insertBefore(f,dateBoxNow||main.children[2]||null);}}else{if(name==='stars'){var st=f.querySelector(':scope > span');if(st)st.textContent='Категория отеля';}if(name==='food'){var ft=f.querySelector(':scope > span');if(ft)ft.textContent='Питание';}if(f.parentNode!==grid)grid.appendChild(f);}});},100);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(init,20);},{once:true});else setTimeout(init,20);
})();
