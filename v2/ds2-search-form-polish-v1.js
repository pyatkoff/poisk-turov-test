(function(){'use strict';
var form=document.getElementById('tourSearch');
if(!form)return;
var tools=document.getElementById('resultsTools'),selected=document.getElementById('selectedTour');
function syncInitialState(){
  var initial=!(tools&&!tools.hidden)&&!(selected&&!selected.hidden);
  form.classList.toggle('ds2-search-initial',initial);
  form.dataset.ds2InitialState=initial?'1':'0';
}
function closeOtherPickers(current){
  form.querySelectorAll('.dates-picker,.guests-picker,.ds2-nights-picker').forEach(function(other){if(other!==current&&other.open)other.open=false;});
}
function ruWord(n,one,few,many){n=Math.abs(Number(n)||0)%100;var last=n%10;if(n>10&&n<20)return many;if(last>1&&last<5)return few;if(last===1)return one;return many;}
function nightsText(from,to){var a=String(from&&from.value||''),b=String(to&&to.value||'');function one(v){var n=Number(v||0);return n?n+' '+ruWord(n,'ночь','ночи','ночей'):'';}return a&&b?(a===b?one(a):a+'–'+b+' ночей'):a?one(a):'Выберите ночи';}
function enhanceNights(main){
  var field=main.querySelector('.nights-ux');
  if(!field)return false;
  var from=form.elements.daysFrom,to=form.elements.daysTill;
  if(!from||!to)return false;
  var title=field.querySelector(':scope > span');if(title)title.textContent='Ночи';
  if(field.dataset.ds2CompactNights==='1'){
    var existing=field.querySelector('.ds2-nights-picker>summary'),existingOut=field.querySelector('.ds2-nights-summary');
    var existingLabel=nightsText(from,to);if(existingOut)existingOut.textContent=existingLabel;if(existing)existing.setAttribute('aria-label','Ночи: '+existingLabel+'. Изменить');
    return !!existing;
  }
  var quick=field.querySelector('.nights-quick'),custom=field.querySelector('.nights-custom');
  if(!quick||!custom)return false;
  field.dataset.ds2CompactNights='1';
  var picker=document.createElement('details');picker.className='ds2-nights-picker';
  var summary=document.createElement('summary');summary.setAttribute('aria-label','Выбрать количество ночей');summary.innerHTML='<strong class="ds2-nights-summary"></strong><span aria-hidden="true">⌄</span>';
  var panel=document.createElement('div');panel.className='ds2-nights-panel';panel.id='ds2-nights-panel';summary.setAttribute('aria-controls',panel.id);summary.setAttribute('aria-haspopup','dialog');
  var caption=document.createElement('small');caption.className='ds2-nights-caption';caption.textContent='Выберите диапазон ночей';
  panel.appendChild(caption);panel.appendChild(quick);panel.appendChild(custom);picker.appendChild(summary);picker.appendChild(panel);field.appendChild(picker);
  function sync(){var label=nightsText(from,to),out=summary.querySelector('.ds2-nights-summary');if(out)out.textContent=label;summary.setAttribute('aria-label','Ночи: '+label+'. Изменить');}
  picker.addEventListener('toggle',function(){summary.setAttribute('aria-expanded',picker.open?'true':'false');if(picker.open)closeOtherPickers(picker);});
  from.addEventListener('input',sync);to.addEventListener('input',sync);from.addEventListener('change',sync);to.addEventListener('change',sync);
  quick.addEventListener('click',function(e){var btn=e.target&&e.target.closest&&e.target.closest('.nights-choice');if(!btn)return;setTimeout(function(){sync();if(!btn.dataset.custom)picker.open=false;},0);});
  document.addEventListener('click',function(e){if(picker.open&&!picker.contains(e.target))picker.open=false;});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&picker.open){picker.open=false;summary.focus();}});
  summary.setAttribute('aria-expanded','false');sync();return true;
}
function coordinateExistingPickers(){form.querySelectorAll('.dates-picker,.guests-picker').forEach(function(picker,index){if(picker.dataset.ds2Coordinated==='1')return;picker.dataset.ds2Coordinated='1';var summary=picker.querySelector(':scope > summary'),panel=picker.querySelector(':scope > .dates-panel,:scope > .guests-panel');if(summary){summary.setAttribute('aria-expanded',picker.open?'true':'false');summary.setAttribute('aria-haspopup','dialog');if(panel){if(!panel.id)panel.id='ds2-picker-panel-'+index;summary.setAttribute('aria-controls',panel.id);}}picker.addEventListener('toggle',function(){if(summary)summary.setAttribute('aria-expanded',picker.open?'true':'false');if(picker.open)closeOtherPickers(picker);});});}
function compactDate(v){if(!v)return'';var p=String(v).split('-').map(Number);if(p.length!==3||!p[0]||!p[1]||!p[2])return String(v);try{return new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short'}).format(new Date(p[0],p[1]-1,p[2])).replace('.','');}catch(e){return String(v);}}
function tunePrimarySummaries(main){
  var dateFrom=form.elements.dateFrom,dateTo=form.elements.dateTo,adults=form.elements.count_people,children=form.elements.child_count;
  var dateOut=main.querySelector('.dates-summary'),guestOut=main.querySelector('.guests-summary');
  if(!dateFrom||!dateTo||!adults||!children||!dateOut||!guestOut)return false;
  var dateSummary=main.querySelector('.dates-picker>summary'),guestSummary=main.querySelector('.guests-picker>summary');
  function syncDates(){var a=compactDate(dateFrom.value),b=compactDate(dateTo.value),label=a&&b?a+' – '+b:a?('с '+a):b?('до '+b):'Выберите даты';dateOut.textContent=label;if(dateSummary)dateSummary.setAttribute('aria-label','Когда: '+label+'. Изменить');}
  function syncGuests(){var a=Math.max(1,Number(adults.value||1)),c=Math.max(0,Number(children.value||0));var adultWord=ruWord(a,'взрослый','взрослых','взрослых');var childWord=ruWord(c,'ребёнок','ребёнка','детей');var label=a+' '+adultWord+(c?' · '+c+' '+childWord:'');guestOut.textContent=label;if(guestSummary)guestSummary.setAttribute('aria-label','Туристы: '+label+'. Изменить');}
  if(main.dataset.ds2CompactSummaries!=='1'){
    main.dataset.ds2CompactSummaries='1';
    function after(fn){return function(){setTimeout(fn,0);};}
    dateFrom.addEventListener('input',after(syncDates));dateTo.addEventListener('input',after(syncDates));dateFrom.addEventListener('change',after(syncDates));dateTo.addEventListener('change',after(syncDates));adults.addEventListener('change',after(syncGuests));children.addEventListener('change',after(syncGuests));
  }
  syncDates();syncGuests();return true;
}
function tuneQuickStars(stars){
  if(!stars)return false;
  stars.classList.add('main-stars');
  var select=form.elements.stars,labels={'':'Все варианты','2':'2★ и выше','3':'3★ и выше','4':'4★ и выше','5':'5★ отели'};
  var wrap=stars.querySelector('.stars-quick');
  if(!wrap&&select){wrap=document.createElement('div');wrap.className='stars-quick';wrap.setAttribute('role','group');wrap.setAttribute('aria-label','Категория отеля');stars.appendChild(wrap);Object.keys(labels).forEach(function(value){var btn=document.createElement('button');btn.type='button';btn.className='stars-choice';btn.dataset.value=value;btn.addEventListener('click',function(){if(String(select.value||'')===value)return;select.value=value;select.dispatchEvent(new Event('change',{bubbles:true}));sync();});wrap.appendChild(btn);});}
  function sync(){if(!wrap)return;wrap.querySelectorAll('.stars-choice').forEach(function(btn){var value=String(btn.dataset.value||'');if(Object.prototype.hasOwnProperty.call(labels,value))btn.textContent=labels[value];var active=String(select&&select.value||'')===value;btn.classList.toggle('is-active',active);btn.setAttribute('aria-pressed',active?'true':'false');btn.dataset.ds2QuickRole=value===''?'all':'stars';});}
  var title=stars.querySelector(':scope > span');if(title){title.textContent='Категория отеля';title.classList.add('v2-visually-hidden');}
  stars.dataset.ds2QuickLabels='1';stars.classList.add('ds2-search-quick-stars');if(select&&!select.dataset.ds2QuickSync){select.dataset.ds2QuickSync='1';select.addEventListener('change',sync);}sync();return !!wrap;
}
function tuneAdvancedFilters(){var details=form.querySelector('details.extras,details.extras-secondary');if(!details)return false;details.dataset.ds2CollapsedInitial='1';var summary=details.querySelector(':scope > summary');if(summary){var strong=summary.querySelector('strong');if(strong)strong.textContent='Все фильтры';else summary.insertAdjacentHTML('afterbegin','<strong>Все фильтры</strong>');}if(form.classList.contains('ds2-search-initial'))details.open=false;return true;}
function arrange(){
  syncInitialState();
  var main=form.querySelector('.main-fields');if(!main)return false;
  var starsSelect=form.elements.stars,stars=starsSelect&&starsSelect.closest('.field'),nights=main.querySelector('.nights-ux'),dates=main.querySelector('.dates-ux'),guests=main.querySelector('.guests-ux'),details=form.querySelector('details.extras,details.extras-secondary');
  if(!stars||!nights||!dates||!guests)return false;
  if(!tuneQuickStars(stars))return false;
  if(stars.parentElement!==form||stars.nextElementSibling!==details){if(details)form.insertBefore(stars,details);else main.insertAdjacentElement('afterend',stars);}
  if(!enhanceNights(main))return false;
  coordinateExistingPickers();tunePrimarySummaries(main);tuneAdvancedFilters();
  form.dataset.ds2InitialPolish='1';return true;
}
syncInitialState();
if(tools)new MutationObserver(function(){syncInitialState();tuneAdvancedFilters();}).observe(tools,{attributes:true,attributeFilter:['hidden','class']});
if(selected)new MutationObserver(function(){syncInitialState();tuneAdvancedFilters();}).observe(selected,{attributes:true,attributeFilter:['hidden','class']});
var attempts=0,timer=setInterval(function(){attempts++;if(arrange()||attempts>=40)clearInterval(timer);},100);
arrange();
})();