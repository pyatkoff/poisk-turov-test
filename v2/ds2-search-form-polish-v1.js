(function(){'use strict';
var form=document.getElementById('tourSearch');
if(!form||form.dataset.ds2InitialPolish==='1')return;
form.dataset.ds2InitialPolish='1';
function closeOtherPickers(current){
  form.querySelectorAll('.dates-picker,.guests-picker,.ds2-nights-picker').forEach(function(other){
    if(other!==current&&other.open)other.open=false;
  });
}
function enhanceNights(main){
  var field=main.querySelector('.nights-ux');
  if(!field||field.dataset.ds2CompactNights==='1')return !!field;
  var title=field.querySelector(':scope > span');
  if(title)title.textContent='Ночи';
  var quick=field.querySelector('.nights-quick');
  var custom=field.querySelector('.nights-custom');
  var from=form.elements.daysFrom,to=form.elements.daysTill;
  if(!quick||!custom||!from||!to)return false;
  field.dataset.ds2CompactNights='1';
  var picker=document.createElement('details');
  picker.className='ds2-nights-picker';
  var summary=document.createElement('summary');
  summary.innerHTML='<strong class="ds2-nights-summary"></strong><span aria-hidden="true">⌄</span>';
  var panel=document.createElement('div');
  panel.className='ds2-nights-panel';
  var caption=document.createElement('small');
  caption.className='ds2-nights-caption';
  caption.textContent='Выберите диапазон ночей';
  panel.appendChild(caption);
  panel.appendChild(quick);
  panel.appendChild(custom);
  picker.appendChild(summary);
  picker.appendChild(panel);
  field.appendChild(picker);
  function sync(){
    var a=String(from.value||''),b=String(to.value||'');
    var label=a&&b?(a===b?a+' ночей':a+'–'+b+' ночей'):a?a+' ночей':'Выберите ночи';
    var out=summary.querySelector('.ds2-nights-summary');
    if(out)out.textContent=label;
  }
  picker.addEventListener('toggle',function(){if(picker.open)closeOtherPickers(picker);});
  from.addEventListener('input',sync);to.addEventListener('input',sync);
  from.addEventListener('change',sync);to.addEventListener('change',sync);
  quick.addEventListener('click',function(e){
    var btn=e.target&&e.target.closest&&e.target.closest('.nights-choice');
    if(!btn)return;
    setTimeout(function(){sync();if(!btn.dataset.custom)picker.open=false;},0);
  });
  document.addEventListener('click',function(e){if(picker.open&&!picker.contains(e.target))picker.open=false;});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&picker.open){picker.open=false;summary.focus();}});
  sync();
  return true;
}
function coordinateExistingPickers(){
  form.querySelectorAll('.dates-picker,.guests-picker').forEach(function(picker){
    if(picker.dataset.ds2Coordinated==='1')return;
    picker.dataset.ds2Coordinated='1';
    picker.addEventListener('toggle',function(){if(picker.open)closeOtherPickers(picker);});
  });
}
function compactDate(v){
  if(!v)return'';
  var p=String(v).split('-').map(Number);
  if(p.length!==3||!p[0]||!p[1]||!p[2])return String(v);
  try{return new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short'}).format(new Date(p[0],p[1]-1,p[2])).replace('.','');}
  catch(e){return String(v);}
}
function tunePrimarySummaries(main){
  if(!main||main.dataset.ds2CompactSummaries==='1')return;
  var dateFrom=form.elements.dateFrom,dateTo=form.elements.dateTo;
  var adults=form.elements.count_people,children=form.elements.child_count;
  var dateOut=main.querySelector('.dates-summary');
  var guestOut=main.querySelector('.guests-summary');
  if(!dateFrom||!dateTo||!adults||!children||!dateOut||!guestOut)return;
  main.dataset.ds2CompactSummaries='1';
  function syncDates(){
    var a=compactDate(dateFrom.value),b=compactDate(dateTo.value);
    dateOut.textContent=a&&b?a+' – '+b:a?('с '+a):b?('до '+b):'Выберите даты';
  }
  function syncGuests(){
    var a=Math.max(1,Number(adults.value||1)),c=Math.max(0,Number(children.value||0));
    var adultWord=a===1?'взрослый':'взрослых';
    var childWord=c===1?'ребёнок':c>=2&&c<=4?'ребёнка':'детей';
    guestOut.textContent=a+' '+adultWord+(c?' · '+c+' '+childWord:'');
  }
  function after(fn){return function(){setTimeout(fn,0);};}
  dateFrom.addEventListener('input',after(syncDates));dateTo.addEventListener('input',after(syncDates));
  dateFrom.addEventListener('change',after(syncDates));dateTo.addEventListener('change',after(syncDates));
  adults.addEventListener('change',after(syncGuests));children.addEventListener('change',after(syncGuests));
  syncDates();syncGuests();
}
function tuneQuickStars(stars){
  if(!stars||stars.dataset.ds2QuickLabels==='1')return;
  stars.dataset.ds2QuickLabels='1';
  var title=stars.querySelector(':scope > span');
  if(title){title.textContent='Быстрый выбор';title.classList.add('v2-visually-hidden');}
  var labels={'':'Все варианты','2':'2★+','3':'3★+','4':'4★+','5':'5★ отели'};
  stars.querySelectorAll('.stars-choice').forEach(function(btn){
    var value=String(btn.dataset.value||'');
    if(Object.prototype.hasOwnProperty.call(labels,value))btn.textContent=labels[value];
  });
}
function tuneAdvancedFilters(){
  var details=form.querySelector('details.extras,details.extras-secondary');
  if(!details||details.dataset.ds2CollapsedInitial==='1')return;
  details.dataset.ds2CollapsedInitial='1';
  if(!document.body.classList.contains('at-search2-results')&&!document.body.classList.contains('at-search2-selected'))details.open=false;
  var summary=details.querySelector(':scope > summary');
  if(summary){
    var strong=summary.querySelector('strong');
    if(strong)strong.textContent='Все фильтры';
    else summary.insertAdjacentHTML('afterbegin','<strong>Все фильтры</strong>');
  }
}
function arrange(){
  var main=form.querySelector('.main-fields');
  if(!main)return false;
  var stars=main.querySelector('.main-stars');
  var nights=main.querySelector('.nights-ux');
  if(!stars||!nights)return false;
  stars.classList.add('ds2-search-quick-stars');
  tuneQuickStars(stars);
  main.insertAdjacentElement('afterend',stars);
  enhanceNights(main);
  coordinateExistingPickers();
  tunePrimarySummaries(main);
  tuneAdvancedFilters();
  return true;
}
if(!arrange()){
  var observer=new MutationObserver(function(){if(arrange())observer.disconnect();});
  observer.observe(form,{childList:true,subtree:true});
  setTimeout(function(){observer.disconnect();},4000);
}
})();