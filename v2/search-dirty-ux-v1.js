(function(){'use strict';
if(window.V2SearchDirtyUXV1)return;
const form=document.getElementById('tourSearch'),results=document.getElementById('results'),tools=document.getElementById('resultsTools');if(!form||!results)return;
let stale=false,banner=null;
function hasResults(){return !!results.querySelector('.hotel-card');}
function ensureBanner(){if(banner&&banner.isConnected)return banner;banner=document.createElement('div');banner.className='search-stale-banner';banner.hidden=true;banner.innerHTML='<div><strong>Условия поиска изменены</strong><span>Предложения ниже найдены по предыдущим параметрам.</span></div><button type="button" class="search-stale-update">Обновить результаты</button>';results.parentNode.insertBefore(banner,results);banner.querySelector('.search-stale-update').addEventListener('click',()=>{if(window.V2SearchLifecycle&&typeof window.V2SearchLifecycle.submit==='function')window.V2SearchLifecycle.submit();});return banner;}
function mark(){if(!hasResults())return;stale=true;ensureBanner().hidden=false;results.classList.add('search-results-stale');if(tools)tools.hidden=true;}
function clear(){stale=false;if(banner)banner.hidden=true;results.classList.remove('search-results-stale');}
window.addEventListener('v2:search-dirty',mark);
window.addEventListener('v2:search-started',clear);
window.addEventListener('v2:search-reset',e=>{if(!(e.detail&&e.detail.dirty))clear();});
window.addEventListener('v2:results-rendered',()=>{if(stale)mark();});
ensureBanner();
window.V2SearchDirtyUXV1={mark,clear,get stale(){return stale;},version:1};
})();