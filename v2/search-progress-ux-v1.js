(function(){'use strict';
if(window.V2SearchProgressUXV1)return;
const status=document.getElementById('status'),results=document.getElementById('results');if(!status)return;
let renderedCount=0;
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function ensureStyle(){if(document.getElementById('v2-search-progress-ux-style'))return;const style=document.createElement('style');style.id='v2-search-progress-ux-style';style.textContent='.search-progress-ux{display:grid;gap:8px}.search-progress-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.search-progress-head strong{font-size:14px}.search-progress-head span{font-size:12px;color:#667085}.search-progress-track{height:7px;border-radius:999px;background:#edf0f5;overflow:hidden}.search-progress-bar{height:100%;width:0;border-radius:inherit;background:currentColor;transition:width .3s ease}.search-progress-note{font-size:12px;color:#667085}.search-progress-done .search-progress-track{display:none}@media(max-width:560px){.status:has(.search-progress-ux){position:sticky;top:0;z-index:14}.search-progress-head strong{font-size:13px}.search-progress-note{font-size:11px}}';document.head.appendChild(style);}
function countRendered(){if(!results)return renderedCount;return results.querySelectorAll('.hotel-card').length||renderedCount;}
function render(progress,title,note,done){const p=Math.max(0,Math.min(100,Number(progress)||0));status.hidden=false;status.innerHTML='<div class="search-progress-ux'+(done?' search-progress-done':'')+'"><div class="search-progress-head"><strong>'+esc(title)+'</strong><span>'+(done?'Готово':p+'%')+'</span></div><div class="search-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'+p+'"><div class="search-progress-bar" style="width:'+p+'%"></div></div>'+(note?'<div class="search-progress-note">'+esc(note)+'</div>':'')+'</div>';}
window.addEventListener('v2:results-rendered',e=>{const items=e.detail&&Array.isArray(e.detail.items)?e.detail.items:[];renderedCount=items.length;});
window.addEventListener('v2:search-started',()=>{renderedCount=0;render(6,'Ищем лучшие варианты','Первые предложения появятся прямо во время поиска.',false);});
window.addEventListener('v2:search-progress',e=>{const d=e.detail||{},p=Number(d.progress||0),n=countRendered(),note=n?'Уже можно смотреть '+n+' '+(n===1?'отель':'отелей')+' — поиск продолжается.':'Сравниваем предложения туроператоров.';render(p,'Ищем лучшие варианты',note,false);});
window.addEventListener('v2:search-complete',e=>{const items=e.detail&&Array.isArray(e.detail.items)?e.detail.items:[],n=items.length;renderedCount=n;render(100,n?'Поиск завершён':'Поиск завершён',n?'Найдено отелей: '+n+'. Предложения актуальны на сейчас.':'По заданным условиям предложений нет.',true);});
window.addEventListener('v2:search-dirty',()=>{renderedCount=0;});
window.addEventListener('v2:search-error',e=>{const d=e.detail||{};if(d.phase==='validation')return;});
ensureStyle();
window.V2SearchProgressUXV1={render,version:1};
})();