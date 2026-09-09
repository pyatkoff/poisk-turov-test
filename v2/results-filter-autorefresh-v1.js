(function(){'use strict';
if(window.V2ResultsFilterAutorefreshV1)return;
const form=document.getElementById('tourSearch'),status=document.getElementById('status'),results=document.getElementById('results');
if(!form||!results)return;
const desktop=window.matchMedia?window.matchMedia('(min-width:761px)'):{matches:true};
const filterNames=new Set(['arrival','region','subregion','hotel','operator','hotel_type','stars','rating','food','price_from','price_till','onlyDirect','onlyCharter','hotel_service[]']);
let timer=0,lastTargetName='';
function lifecycle(){return window.V2SearchLifecycle||null;}
function isResultFilter(target){return !!(desktop.matches&&target&&target.name&&filterNames.has(String(target.name)));}
function hasActiveSearch(lc){return !!(lc&&(lc.pending||Number(lc.searchId||0)>0||results.querySelector('.hotel-card')));}
function showRefreshing(){if(!status)return;status.hidden=false;status.textContent='Фильтры изменены · обновляем предложения по всем доступным турам…';}
function schedule(target){const lc=lifecycle();if(!lc||!hasActiveSearch(lc))return;lastTargetName=String(target&&target.name||'');if(timer)clearTimeout(timer);showRefreshing();timer=setTimeout(()=>{timer=0;const current=lifecycle();if(!current||typeof current.submit!=='function')return;if(!current.dirty&&!results.querySelector('.hotel-card'))return;current.submit();window.dispatchEvent(new CustomEvent('v2:results-filter-autorefresh',{detail:{name:lastTargetName}}));},650);}
function onPotentialFilterChange(event){const target=event.target;if(!isResultFilter(target))return;const type=String(target.type||'').toLowerCase();if(event.type==='change'&&(type==='number'||type==='text'||type==='search'||type==='range'))return;schedule(target);}
function onFilterReset(event){const target=event.target&&event.target.closest?event.target.closest('.search-filters-reset'):null;if(!target)return;queueMicrotask(()=>{const lc=lifecycle();if(!lc||!hasActiveSearch(lc)||typeof lc.submit!=='function')return;if(timer){clearTimeout(timer);timer=0;}showRefreshing();lc.submit();window.dispatchEvent(new CustomEvent('v2:results-filter-autorefresh',{detail:{name:'filters_reset'}}));});}
form.addEventListener('input',onPotentialFilterChange,true);
form.addEventListener('change',onPotentialFilterChange,true);
form.addEventListener('click',onFilterReset);
window.addEventListener('v2:search-started',()=>{if(timer){clearTimeout(timer);timer=0;}});
if(desktop.addEventListener)desktop.addEventListener('change',e=>{if(!e.matches&&timer){clearTimeout(timer);timer=0;}});
window.V2ResultsFilterAutorefreshV1={schedule,filterNames:Array.from(filterNames),version:3};
})();
