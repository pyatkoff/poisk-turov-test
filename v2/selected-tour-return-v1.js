(function(){'use strict';
if(window.V2SelectedTourReturnV1)return;
let sourceButton=null,sourceTourId='';
function isUsable(node){if(!(node&&document.contains(node)&&!node.disabled))return false;if(node.hidden||node.getAttribute('aria-hidden')==='true')return false;return typeof node.getClientRects!=='function'||node.getClientRects().length>0;}
function findCurrentSource(){if(isUsable(sourceButton))return sourceButton;if(!sourceTourId)return null;const buttons=document.querySelectorAll('.direct-tour');for(const button of buttons){if(String(button.dataset&&button.dataset.tid||'')===sourceTourId&&isUsable(button)){sourceButton=button;return button;}}return null;}
function focusAndReveal(node){if(!node)return;try{node.focus({preventScroll:true});}catch(e){try{node.focus();}catch(_){}}
try{node.scrollIntoView({behavior:'smooth',block:'center',inline:'nearest'});}catch(e){node.scrollIntoView();}}
function fallbackResults(){const results=document.getElementById('results');if(!results)return;const hadTabindex=results.hasAttribute('tabindex');if(!hadTabindex)results.setAttribute('tabindex','-1');focusAndReveal(results);if(!hadTabindex)results.addEventListener('blur',()=>results.removeAttribute('tabindex'),{once:true});}
function returnToResults(root){if(!root)return;root.hidden=true;root.setAttribute('aria-hidden','true');const target=findCurrentSource();requestAnimationFrame(()=>{if(target)focusAndReveal(target);else fallbackResults();});window.dispatchEvent(new CustomEvent('v2:tour-returned',{detail:{source:target||null}}));}
document.addEventListener('click',function(e){const target=e.target&&e.target.closest?e.target.closest('.direct-tour,.back-results,.lead-success-back'):null;if(!target)return;if(target.classList.contains('direct-tour')){sourceButton=target;sourceTourId=String(target.dataset&&target.dataset.tid||'');return;}const root=document.getElementById('selectedTour');if(!root||!root.contains(target))return;e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();returnToResults(root);},true);
window.addEventListener('v2:tour-selected',e=>{const tour=e&&e.detail&&e.detail.tour;if(tour&&tour.id!==undefined&&tour.id!==null)sourceTourId=String(tour.id);const root=document.getElementById('selectedTour');if(root){root.hidden=false;root.removeAttribute('aria-hidden');}});
window.V2SelectedTourReturnV1={returnToResults,isUsable,findCurrentSource,get sourceButton(){return sourceButton;},get sourceTourId(){return sourceTourId;},version:2};
})();
