(function(){'use strict';
if(window.V2CompareRefreshGuardV1)return;
function enforce(){const confidence=window.V2ConversionConfidenceV1,overlay=document.getElementById('v2CompareOverlay');if(!confidence||!confidence.compareState||!overlay||overlay.hidden)return false;if(confidence.compareState.selected.size>=2)return false;if(typeof confidence.closeCompare!=='function')return false;confidence.closeCompare();return true;}
window.addEventListener('v2:results-rendered',enforce);
window.V2CompareRefreshGuardV1={enforce,version:1};
})();