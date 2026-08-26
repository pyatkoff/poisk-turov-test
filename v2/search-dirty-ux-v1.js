(function(){'use strict';
if(window.V2SearchDirtyUXV1)return;
const form=document.getElementById('tourSearch'),results=document.getElementById('results'),tools=document.getElementById('resultsTools');if(!form||!results)return;
let stale=false,banner=null;
function ensureStyle(){if(document.getElementById('v2-search-dirty-ux-style'))return;const s=document.createElement('style');s.id='v2-search-dirty-ux-style';s.textContent='.search-stale-banner{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:10px 0 12px;padding:12px 14px;border:1px solid #f0d7a5;border-radius:14px;background:#fffaf0;color:#55421e}.search-stale-banner strong{display:block;font-size:14px}.search-stale-banner span{display:block;margin-top:2px;font-size:12px;color:#7c6840}.search-stale-update{flex:0 0 auto;min-height:40px;border:0;border-radius:10px;padding:0 14px;background:#3154cf;color:#fff;font-weight:900;cursor:pointer}.results.search-results-stale{opacity:.45;filter:saturate(.55);pointer-events:none;user-select:none}.results.search-results-stale:before{content:"Результаты относятся к предыдущим условиям";display:block;margin:0 0 10px;padding:8px 10px;border-radius:10px;background:#f4f6fa;color:#667085;font-size:12px;font-weight:800}@media(max-width:560px){.search-stale-banner{position:sticky;top:0;z-index:15;display:grid;grid-template-columns:minmax(0,1fr);padding:11px 12px}.search-stale-update{width:100%;min-height:44px}}';document.head.appendChild(s);}
function hasResults(){return !!results.querySelector('.hotel-card');}
function ensureBanner(){if(banner&&banner.isConnected)return banner;banner=document.createElement('div');banner.className='search-stale-banner';banner.hidden=true;banner.innerHTML='<div><strong>Условия поиска изменены</strong><span>Предложения ниже найдены по предыдущим параметрам.</span></div><button type="button" class="search-stale-update">Обновить результаты</button>';results.parentNode.insertBefore(banner,results);banner.querySelector('.search-stale-update').addEventListener('click',()=>{if(window.V2SearchLifecycle&&typeof window.V2SearchLifecycle.submit==='function')window.V2SearchLifecycle.submit();});return banner;}
function mark(){if(!hasResults())return;stale=true;ensureBanner().hidden=false;results.classList.add('search-results-stale');if(tools)tools.hidden=true;}
function clear(){stale=false;if(banner)banner.hidden=true;results.classList.remove('search-results-stale');}
window.addEventListener('v2:search-dirty',mark);
window.addEventListener('v2:search-started',clear);
window.addEventListener('v2:search-reset',e=>{if(!(e.detail&&e.detail.dirty))clear();});
window.addEventListener('v2:results-rendered',()=>{if(stale)mark();});
ensureStyle();ensureBanner();
window.V2SearchDirtyUXV1={mark,clear,get stale(){return stale;},version:1};
})();