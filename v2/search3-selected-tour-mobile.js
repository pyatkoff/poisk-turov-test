(function(){'use strict';
var selected=document.getElementById('selectedTour');if(!selected)return;
var bar=document.createElement('div');bar.className='search3-selected-mobile-bar';bar.hidden=true;bar.innerHTML='<div class="search3-selected-mobile-bar__price"><small>Точная стоимость тура</small><strong data-s3-selected-price>—</strong></div><button type="button" data-s3-selected-lead>Продолжить</button>';
document.body.appendChild(bar);
function actionButton(){return bar.querySelector('[data-s3-selected-lead]')}
function sync(){var visible=!selected.hidden&&getComputedStyle(selected).display!=='none'&&selected.children.length>0;document.body.classList.toggle('search3-selected-open',visible);var leadEntry=selected.classList.contains('search3-lead-entry');bar.hidden=!visible||leadEntry;if(!visible)return;var source=selected.querySelector('.selected-price');var target=bar.querySelector('[data-s3-selected-price]');if(source&&target){var text=String(source.textContent||'').replace(/^Стоимость\s*/i,'').trim();target.textContent=text||'—';}var btn=actionButton();if(!btn)return;btn.textContent=selected.classList.contains('search3-final-review')?'Перейти к заявке':'Продолжить';}
function continueFlow(){
  if(selected.classList.contains('search3-lead-entry'))return;
  if(selected.classList.contains('search3-final-review')){
    if(window.Search3SummaryCta&&typeof window.Search3SummaryCta.enterLead==='function'){window.Search3SummaryCta.enterLead('mobile-bar');return;}
    var summary=selected.querySelector('.search3-summary-submit');if(summary){summary.click();return;}
  }
  var next=selected.querySelector('.search3-flight-continue button');
  if(next){next.scrollIntoView({behavior:'smooth',block:'center'});try{next.focus({preventScroll:true});}catch(_e){}return;}
  var flights=selected.querySelector('.tour-flights');if(flights)flights.scrollIntoView({behavior:'smooth',block:'start'});
}
document.addEventListener('click',function(e){var btn=e.target&&e.target.closest&&e.target.closest('[data-s3-selected-lead]');if(!btn)return;e.preventDefault();continueFlow();});
['v2:tour-selected','v2:selected-tour-opened','v2:selected-tour-closed','v2:results-rendered','v2:booking-review','search3:lead-entry','v2:lead-started','v2:lead-success','v2:lead-error'].forEach(function(name){window.addEventListener(name,function(){setTimeout(sync,0)});});
new MutationObserver(sync).observe(selected,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden','class','style']});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',sync,{once:true});else sync();
window.Search3SelectedTourMobile={sync,continueFlow,version:4};
})();