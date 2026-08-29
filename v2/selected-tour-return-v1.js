(function(){'use strict';
if(window.V2SelectedTourReturnV1)return;
let sourceButton=null;
function isUsable(node){return !!(node&&document.contains(node)&&!node.disabled);}
function focusAndReveal(node){if(!node)return;try{node.focus({preventScroll:true});}catch(e){try{node.focus();}catch(_){}}
try{node.scrollIntoView({behavior:'smooth',block:'center',inline:'nearest'});}catch(e){node.scrollIntoView();}}
function fallbackResults(){const results=document.getElementById('results');if(!results)return;const hadTabindex=results.hasAttribute('tabindex');if(!hadTabindex)results.setAttribute('tabindex','-1');focusAndReveal(results);if(!hadTabindex)results.addEventListener('blur',()=>results.removeAttribute('tabindex'),{once:true});}
function returnToResults(root){if(!root)return;root.hidden=true;root.setAttribute('aria-hidden','true');const target=isUsable(sourceButton)?sourceButton:null;requestAnimationFrame(()=>{if(target)focusAndReveal(target);else fallbackResults();});window.dispatchEvent(new CustomEvent('v2:tour-returned',{detail:{source:target||null}}));}
document.addEventListener('click',function(e){const target=e.target&&e.target.closest?e.target.closest('.direct-tour,.back-results'):null;if(!target)return;if(target.classList.contains('direct-tour')){sourceButton=target;return;}const root=document.getElementById('selectedTour');if(!root||!root.contains(target))return;e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();returnToResults(root);},true);
window.addEventListener('v2:tour-selected',()=>{const root=document.getElementById('selectedTour');if(root){root.hidden=false;root.removeAttribute('aria-hidden');}});
window.V2SelectedTourReturnV1={returnToResults,get sourceButton(){return sourceButton;},version:1};
})();
