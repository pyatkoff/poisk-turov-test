(function(){'use strict';
var selected=document.getElementById('selectedTour');if(!selected)return;
var bar=document.createElement('div');bar.className='search3-selected-mobile-bar';bar.hidden=true;bar.innerHTML='<div class="search3-selected-mobile-bar__price"><small>Точная стоимость тура</small><strong data-s3-selected-price>—</strong></div><button type="button" data-s3-selected-lead>Оставить заявку</button>';
document.body.appendChild(bar);
function sync(){var visible=!selected.hidden&&getComputedStyle(selected).display!=='none'&&selected.children.length>0;bar.hidden=!visible;if(!visible)return;var source=selected.querySelector('.selected-price');var target=bar.querySelector('[data-s3-selected-price]');if(source&&target){var text=String(source.textContent||'').replace(/^Стоимость\s*/i,'').trim();target.textContent=text||'—';}}
document.addEventListener('click',function(e){var btn=e.target&&e.target.closest&&e.target.closest('[data-s3-selected-lead]');if(!btn)return;var form=selected.querySelector('.lead-form');if(form)form.scrollIntoView({behavior:'smooth',block:'start'});});
window.addEventListener('v2:tour-selected',sync);window.addEventListener('v2:selected-tour-opened',sync);window.addEventListener('v2:selected-tour-closed',sync);window.addEventListener('v2:results-rendered',sync);
new MutationObserver(function(){sync();}).observe(selected,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden','class','style']});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',sync,{once:true});else sync();
})();
