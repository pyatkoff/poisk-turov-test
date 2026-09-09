(function(){'use strict';
if(window.V2SearchCompleteRecoveryV1)return;
const status=document.getElementById('status');if(!status)return;
let completedByStatus=false;
function reset(){completedByStatus=false;}
function isCompleteSignal(detail){const d=detail||{},s=d.status||{},name=String(s.status||'').toLowerCase();return Number(d.progress||0)>=100||name==='complete';}
function renderResultsRetry(){status.hidden=false;status.innerHTML='<div class="search-progress-error" role="alert"><div class="search-progress-error-copy"><strong>Поиск завершён — результаты не загрузились</strong><span>Поиск уже завершён, но итоговые результаты временно не загрузились. Повторно запускать поиск не нужно.</span></div><button type="button" class="search-progress-retry-results">Загрузить результаты ещё раз</button></div>';}
window.addEventListener('v2:search-reset',reset);
window.addEventListener('v2:search-started',reset);
window.addEventListener('v2:search-dirty',reset);
window.addEventListener('v2:search-progress',e=>{if(isCompleteSignal(e.detail))completedByStatus=true;});
window.addEventListener('v2:search-complete',()=>{completedByStatus=true;});
window.addEventListener('v2:search-error',e=>{const d=e.detail||{};if(String(d.phase||'')==='status'&&completedByStatus)renderResultsRetry();});
window.V2SearchCompleteRecoveryV1={isCompleteSignal,renderResultsRetry,get completedByStatus(){return completedByStatus;},version:1};
})();
