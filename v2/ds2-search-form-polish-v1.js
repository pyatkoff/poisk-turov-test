(function(){'use strict';
var form=document.getElementById('tourSearch');
if(!form||form.dataset.ds2InitialPolish==='1')return;
form.dataset.ds2InitialPolish='1';
function enhanceNights(main){
  var field=main.querySelector('.nights-ux');
  if(!field||field.dataset.ds2CompactNights==='1')return !!field;
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
function arrange(){
  var main=form.querySelector('.main-fields');
  if(!main)return false;
  var stars=main.querySelector('.main-stars');
  var nights=main.querySelector('.nights-ux');
  if(!stars||!nights)return false;
  stars.classList.add('ds2-search-quick-stars');
  var title=stars.querySelector(':scope > span');
  if(title)title.textContent='Быстрый выбор';
  main.insertAdjacentElement('afterend',stars);
  enhanceNights(main);
  return true;
}
if(!arrange()){
  var observer=new MutationObserver(function(){if(arrange())observer.disconnect();});
  observer.observe(form,{childList:true,subtree:true});
  setTimeout(function(){observer.disconnect();},4000);
}
})();