(function(){'use strict';
function selectedRoot(){return document.getElementById('selectedTour');}
function trigger(detail){const tour=detail&&detail.tour||{},tid=String(tour.id||'');if(!tid)return false;const root=selectedRoot();if(!root)return false;const btn=root.querySelector('.load-flights');if(!btn||btn.disabled||String(btn.dataset.tid||'')!==tid)return false;setTimeout(()=>{if(!document.contains(btn)||btn.disabled)return;btn.click();},0);return true;}
window.addEventListener('v2:tour-selected',e=>trigger(e.detail||{}));
window.V2FlightAutoload={trigger,version:1};
})();