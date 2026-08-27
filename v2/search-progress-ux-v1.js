(function(){'use strict';
if(window.V2SearchProgressUXV1)return;
const status=document.getElementById('status'),results=document.getElementById('results');if(!status)return;
let renderedCount=0;
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function countRendered(){if(!results)return renderedCount;return results.querySelectorAll('.hotel-card').length||renderedCount;}
function render(progress,title,note,done){const p=Math.max(0,Math.min(100,Number(progress)||0));status.hidden=false;status.innerHTML='<div class="search-progress-ux'+(done?' search-progress-done':'')+'"><div class="search-progress-head"><strong>'+esc(title)+'</strong><span>'+(done?'Готово':p+'%')+'</span></div><div class="search-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'+p+'"><div class="search-progress-bar" style="width:'+p+'%"></div></div>'+(note?'<div class="search-progress-note">'+esc(note)+'</div>':'')+'</div>';}
function errorMessage(detail){const e=detail&&detail.error||{},phase=String(detail&&detail.phase||'');if(e&&e.code==='TIMEOUT')return'Tourvisor отвечает дольше обычного. Повторите поиск — выбранные параметры сохранятся.';if(phase==='start')return'Не удалось запустить поиск. Проверьте соединение и попробуйте ещё раз.';return'Не удалось обновить результаты. Попробуйте повторить поиск.';}
function renderError(detail){status.hidden=false;status.innerHTML='<div class="search-progress-error" role="alert"><div class="search-progress-error-copy"><strong>Не получилось завершить поиск</strong><span>'+esc(errorMessage(detail))+'</span></div><button type="button" class="search-progress-retry">Повторить поиск</button></div>';}
window.addEventListener('v2:results-rendered',e=>{const items=e.detail&&Array.isArray(e.detail.items)?e.detail.items:[];renderedCount=items.length;});
window.addEventListener('v2:search-started',()=>{renderedCount=0;render(6,'Ищем лучшие варианты','Первые предложения появятся прямо во время поиска.',false);});
window.addEventListener('v2:search-progress',e=>{const d=e.detail||{},p=Number(d.progress||0),n=countRendered(),note=n?'Уже можно смотреть '+n+' '+(n===1?'отель':'отелей')+' — поиск продолжается.':'Сравниваем предложения туроператоров.';render(p,'Ищем лучшие варианты',note,false);});
window.addEventListener('v2:search-complete',e=>{const items=e.detail&&Array.isArray(e.detail.items)?e.detail.items:[],n=items.length;renderedCount=n;render(100,'Поиск завершён',n?'Найдено отелей: '+n+'. Предложения актуальны на сейчас.':'По заданным условиям предложений нет.',true);});
window.addEventListener('v2:search-dirty',()=>{renderedCount=0;});
window.addEventListener('v2:search-error',e=>{const d=e.detail||{};if(d.phase==='validation')return;renderError(d);});
status.addEventListener('click',e=>{const btn=e.target&&e.target.closest('.search-progress-retry');if(!btn)return;const lifecycle=window.V2SearchLifecycle;if(!lifecycle||typeof lifecycle.submit!=='function')return;btn.disabled=true;lifecycle.submit();});
window.V2SearchProgressUXV1={render,renderError,version:1};
})();