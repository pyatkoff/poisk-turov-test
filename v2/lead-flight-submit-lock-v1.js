(function(){'use strict';
if(window.V2LeadFlightSubmitLockV1)return;
function flightChoices(root){const scope=root||document.getElementById('selectedTour');return scope&&scope.querySelector('.flight-variants')||null;}
function setLocked(locked,root){const box=flightChoices(root);if(!box)return false;box.inert=!!locked;if(locked){box.setAttribute('aria-busy','true');box.dataset.leadSubmitLocked='1';}else{box.removeAttribute('aria-busy');delete box.dataset.leadSubmitLocked;}return true;}
function currentRoot(){return document.getElementById('selectedTour');}
window.addEventListener('v2:lead-started',()=>setLocked(true,currentRoot()));
window.addEventListener('v2:lead-error',()=>setLocked(false,currentRoot()));
window.addEventListener('v2:lead-success',()=>setLocked(true,currentRoot()));
window.addEventListener('v2:tour-selected',()=>setLocked(false,currentRoot()));
window.addEventListener('v2:search-reset',()=>setLocked(false,currentRoot()));
window.V2LeadFlightSubmitLockV1={flightChoices,setLocked,version:1};
})();
