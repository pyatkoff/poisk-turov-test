(function(){'use strict';
if(window.V2LeadUiRaceGuardV1)return;
function tourId(){const controller=window.V2TourController,tour=controller&&controller.currentTour;return String(tour&&tour.id||'');}
function searchId(){const runtime=window.V2Runtime,state=runtime&&runtime.state;return Number(state&&state.searchId||0);}
function eventTourId(event){return String(event&&event.detail&&event.detail.tourId||'');}
function eventSearchId(event){return Number(event&&event.detail&&event.detail.searchId||0);}
function isCurrent(event){const currentTour=tourId(),incomingTour=eventTourId(event),currentSearch=searchId(),incomingSearch=eventSearchId(event);return !!currentTour&&!!incomingTour&&currentTour===incomingTour&&currentSearch>0&&incomingSearch>0&&currentSearch===incomingSearch;}
function protect(event){if(isCurrent(event))return true;if(event&&typeof event.stopImmediatePropagation==='function')event.stopImmediatePropagation();return false;}
function flightChoices(){const root=document.getElementById('selectedTour');return root&&root.querySelector('.flight-variants')||null;}
function setFlightLocked(locked){const box=flightChoices();if(!box)return false;box.inert=!!locked;if(locked){box.setAttribute('aria-busy','true');box.dataset.leadSubmitLocked='1';}else{box.removeAttribute('aria-busy');delete box.dataset.leadSubmitLocked;}return true;}
function handleError(event){if(protect(event))setFlightLocked(false);}
function handleSuccess(event){protect(event);}
window.addEventListener('v2:lead-started',()=>setFlightLocked(true));
window.addEventListener('v2:lead-error',handleError);
window.addEventListener('v2:lead-success',handleSuccess);
window.addEventListener('v2:tour-selected',()=>setFlightLocked(false));
window.addEventListener('v2:search-reset',()=>setFlightLocked(false));
window.V2LeadUiRaceGuardV1={tourId,searchId,eventTourId,eventSearchId,isCurrent,protect,flightChoices,setFlightLocked,handleError,handleSuccess,version:3};
})();
