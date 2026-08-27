(function(){'use strict';
if(window.V2MobileResultsFiltersV1||window.__V2MobileResultsFiltersLoading)return;
const mq=window.matchMedia?window.matchMedia('(max-width:760px)'):{matches:true};
const current=document.currentScript&&document.currentScript.src?document.currentScript.src:'';
const runtime=current?current.replace('mobile-results-filters-v1.js','mobile-results-filters-runtime-v1.js'):'/poisk-turov-test/v2/mobile-results-filters-runtime-v1.js';
function dynamicLoad(){if(!mq.matches||window.V2MobileResultsFiltersV1||window.__V2MobileResultsFiltersLoading)return;window.__V2MobileResultsFiltersLoading=true;const s=document.createElement('script');s.src=runtime;s.onload=()=>{window.__V2MobileResultsFiltersLoading=false;};s.onerror=()=>{window.__V2MobileResultsFiltersLoading=false;};document.head.appendChild(s);}
if(mq.matches&&document.readyState==='loading'&&document.currentScript){window.__V2MobileResultsFiltersLoading=true;document.write('<script src="'+runtime.replace(/&/g,'&amp;').replace(/"/g,'&quot;')+'"><\/script>');window.__V2MobileResultsFiltersLoading=false;}else dynamicLoad();
if(mq&&typeof mq.addEventListener==='function')mq.addEventListener('change',dynamicLoad);
})();