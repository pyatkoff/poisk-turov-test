(function(){'use strict';
if(window.V2LeadUiRaceGuardV1)return;
function tourId(){const controller=window.V2TourController,tour=controller&&controller.currentTour;return String(tour&&tour.id||'');}
function eventTourId(event){return String(event&&event.detail&&event.detail.tourId||'');}
function isCurrent(event){const current=tourId(),incoming=eventTourId(event);return !!current&&!!incoming&&current===incoming;}
function protect(event){if(isCurrent(event))return true;if(event&&typeof event.stopImmediatePropagation==='function')event.stopImmediatePropagation();return false;}
window.addEventListener('v2:lead-error',protect);
window.addEventListener('v2:lead-success',protect);
window.V2LeadUiRaceGuardV1={tourId,eventTourId,isCurrent,protect,version:1};
})();
