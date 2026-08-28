(function(){'use strict';
let searchId=0,operation=0;
const rt=window.V2Runtime;if(!rt){console.warn('V2 search continue: runtime unavailable');return;}
function moreNode(){return document.getElementById('v2SearchMore');}
function removeMore(){const n=moreNode();if(n)n.remove();}
function render(list){const r=window.V2Results;if(!r||typeof r.render!=='function')throw new Error('Модуль результатов не загружен');r.render(Array.isArray(list)?list:[],{empty:false});}
function currentCount(){const r=window.V2Results&&window.V2Results.state;return r&&Array.isArray(r.items)?r.items.length:0;}
function emit(name,detail){window.dispatchEvent(new CustomEvent(name,{detail:detail||{}}));}
function ensureMore(){const results=document.getElementById('results');if(!results||!searchId||moreNode())return;const wrap=document.createElement('div');wrap.id='v2SearchMore';wrap.className='results-more';wrap.innerHTML='<button type="button" class="secondary more-search">Показать ещё варианты</button><small>Tourvisor выполнит дополнительный запрос к туроператорам</small>';results.insertAdjacentElement('afterend',wrap);wrap.querySelector('button').addEventListener('click',continueSearch);}
function isCurrent(id,op){return Number(id||0)===Number(searchId||0)&&op===operation;}
async function waitComplete(id,op){const deadline=Date.now()+75000;while(Date.now()<deadline){await new Promise(r=>setTimeout(r,2000));if(!isCurrent(id,op))return false;const s=await rt.api('search_status',{searchId:id});if(!isCurrent(id,op))return false;const p=Math.max(0,Math.min(100,Number(s.progress||0)));emit('v2:search-continue-progress',{searchId:id,progress:p});if(p>=100||String(s.status||'').toLowerCase()==='complete')return true;}throw new Error('Tourvisor не завершил продолжение поиска за 75 секунд');}
async function continueSearch(){const btn=document.querySelector('#v2SearchMore button'),id=Number(searchId||0);if(!id||!btn)return;const op=++operation,before=currentCount();btn.disabled=true;btn.textContent='Ищем ещё…';emit('v2:search-continue-started',{searchId:id,previousResultsCount:before});try{const d=await rt.api('search_continue',{searchId:id});if(!isCurrent(id,op))return;emit('v2:search-continue-requested',{searchId:id,requestCount:Number(d&&d.requestCount||0)});const done=await waitComplete(id,op);if(!done||!isCurrent(id,op))return;const all=await rt.api('search_results',{searchId:id,limit:100});if(!isCurrent(id,op))return;const items=Array.isArray(all)?all:[];render(items);removeMore();const added=Math.max(0,items.length-before);if(items.length>before)ensureMore();emit('v2:search-continued',{searchId:id,items,previousResultsCount:before,addedResultsCount:added});}catch(e){if(!isCurrent(id,op))return;btn.disabled=false;btn.textContent='Попробовать ещё раз';emit('v2:search-continue-error',{searchId:id,error:e,previousResultsCount:before});}}
window.addEventListener('v2:search-reset',()=>{operation++;searchId=0;removeMore();});
window.addEventListener('v2:search-started',e=>{operation++;searchId=Number(e.detail&&e.detail.searchId||0);removeMore();});
window.addEventListener('v2:search-complete',e=>{const id=Number(e.detail&&e.detail.searchId||0);if(id)searchId=id;ensureMore();});
window.V2SearchContinue={ensureMore,currentCount,version:7};
})();
