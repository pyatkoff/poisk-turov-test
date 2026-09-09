(function(){'use strict';
if(window.V2FlightEmptyRecoveryV1)return;
const EMPTY_TEXT='Для тура варианты рейсов не найдены.';
function currentTourId(){const c=window.V2TourController,t=c&&c.currentTour;return String(t&&t.id||'');}
function decorate(scope){scope=scope||document.getElementById('selectedTour');if(!scope)return false;const box=scope.querySelector('.tour-flights');if(!box||box.querySelector('.flight-variant')||box.querySelector('.flight-error'))return false;const empty=[...box.querySelectorAll('.selected-loading')].find(node=>String(node.textContent||'').trim()===EMPTY_TEXT);if(!empty)return false;const tid=currentTourId();empty.textContent='Данные по рейсам пока не получены. Можно проверить ещё раз; если данные не появятся, менеджер уточнит перелёт по заявке.';if(tid&&!box.querySelector('.load-flights')){const btn=document.createElement('button');btn.type='button';btn.className='load-flights secondary';btn.dataset.tid=tid;btn.textContent='Проверить рейсы ещё раз';empty.insertAdjacentElement('afterend',btn);}return true;}
let queued=false;function queue(){if(queued)return;queued=true;requestAnimationFrame(()=>{queued=false;decorate();});}
function observe(){const root=document.getElementById('selectedTour');if(!root||root.dataset.flightEmptyRecoveryObserved==='1')return;root.dataset.flightEmptyRecoveryObserved='1';new MutationObserver(queue).observe(root,{childList:true,subtree:true});}
window.addEventListener('v2:tour-selected',()=>{observe();queue();});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>{observe();queue();},{once:true});else{observe();queue();}
window.V2FlightEmptyRecoveryV1={decorate,currentTourId,version:1};
})();
