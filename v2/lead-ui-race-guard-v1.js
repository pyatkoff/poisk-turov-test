(function(){'use strict';
if(window.V2LeadUiRaceGuardV1)return;
let leadPending=false;
function tourId(){const controller=window.V2TourController,tour=controller&&controller.currentTour;return String(tour&&tour.id||'');}
function searchId(){const runtime=window.V2Runtime,state=runtime&&runtime.state;return Number(state&&state.searchId||0);}
function eventTourId(event){return String(event&&event.detail&&event.detail.tourId||'');}
function eventSearchId(event){return Number(event&&event.detail&&event.detail.searchId||0);}
function isCurrent(event){const currentTour=tourId(),incomingTour=eventTourId(event),currentSearch=searchId(),incomingSearch=eventSearchId(event);return !!currentTour&&!!incomingTour&&currentTour===incomingTour&&currentSearch>0&&incomingSearch>0&&currentSearch===incomingSearch;}
function protect(event){if(isCurrent(event))return true;if(event&&typeof event.stopImmediatePropagation==='function')event.stopImmediatePropagation();return false;}
function selectedRoot(){return document.getElementById('selectedTour');}
function searchForm(){return document.getElementById('tourSearch');}
function flightChoices(){const root=selectedRoot();return root&&root.querySelector('.flight-variants')||null;}
function returnAction(){const root=selectedRoot();return root&&root.querySelector('.back-results')||null;}
function tourActions(){return Array.from(document.querySelectorAll('.direct-tour'));}
function stickySearchActions(){return Array.from(document.querySelectorAll('.mobile-search-sticky-submit'));}
function setFlightLocked(locked){const box=flightChoices();if(!box)return false;box.inert=!!locked;if(locked){box.setAttribute('aria-busy','true');box.dataset.leadSubmitLocked='1';}else{box.removeAttribute('aria-busy');delete box.dataset.leadSubmitLocked;}return true;}
function setReturnLocked(locked){const action=returnAction();if(!action)return false;action.disabled=!!locked;if(locked){action.setAttribute('aria-disabled','true');action.dataset.leadSubmitLocked='1';}else{action.removeAttribute('aria-disabled');delete action.dataset.leadSubmitLocked;}return true;}
function setTourActionsLocked(locked){tourActions().forEach(action=>{if(locked){if(action.disabled)return;action.disabled=true;action.setAttribute('aria-disabled','true');action.dataset.leadSubmitLocked='1';return;}if(action.dataset.leadSubmitLocked!=='1')return;action.disabled=false;action.removeAttribute('aria-disabled');delete action.dataset.leadSubmitLocked;});}
function setSearchLocked(locked){const form=searchForm();if(form){if(locked){if(!form.inert){form.inert=true;form.setAttribute('aria-busy','true');form.dataset.leadSubmitLocked='1';}}else if(form.dataset.leadSubmitLocked==='1'){form.inert=false;form.removeAttribute('aria-busy');delete form.dataset.leadSubmitLocked;}}stickySearchActions().forEach(action=>{if(locked){if(action.disabled)return;action.disabled=true;action.setAttribute('aria-disabled','true');action.dataset.leadSubmitLocked='1';return;}if(action.dataset.leadSubmitLocked!=='1')return;action.disabled=false;action.removeAttribute('aria-disabled');delete action.dataset.leadSubmitLocked;});}
function setPendingLocked(locked){setFlightLocked(locked);setReturnLocked(locked);setTourActionsLocked(locked);setSearchLocked(locked);}
function startPending(){leadPending=true;setPendingLocked(true);}
function clearPending(){leadPending=false;setPendingLocked(false);}
function handleError(event){if(protect(event))clearPending();}
function handleSuccess(event){protect(event);}
function handleSearchSubmit(event){const form=event&&event.target;if(!leadPending||!form||form.id!=='tourSearch')return true;if(typeof event.preventDefault==='function')event.preventDefault();if(typeof event.stopImmediatePropagation==='function')event.stopImmediatePropagation();return false;}
window.addEventListener('v2:lead-started',startPending);
window.addEventListener('v2:lead-error',handleError);
window.addEventListener('v2:lead-success',handleSuccess);
window.addEventListener('v2:tour-selected',()=>{if(!leadPending)setPendingLocked(false);});
window.addEventListener('v2:search-reset',clearPending);
window.addEventListener('submit',handleSearchSubmit,true);
window.V2LeadUiRaceGuardV1={tourId,searchId,eventTourId,eventSearchId,isCurrent,protect,selectedRoot,searchForm,flightChoices,returnAction,tourActions,stickySearchActions,setFlightLocked,setReturnLocked,setTourActionsLocked,setSearchLocked,setPendingLocked,startPending,clearPending,handleError,handleSuccess,handleSearchSubmit,get leadPending(){return leadPending;},version:6};
})();
