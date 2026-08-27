(function(){'use strict';
if(window.V2MobileResultsFiltersV1||window.__V2MobileResultsFiltersLoading)return;
const mq=window.matchMedia?window.matchMedia('(max-width:760px)'):{matches:true};
const current=document.currentScript&&document.currentScript.src?document.currentScript.src:'';
function load(){if(!mq.matches||window.V2MobileResultsFiltersV1||window.__V2MobileResultsFiltersLoading)return;window.__V2MobileResultsFiltersLoading=true;const s=document.createElement('script');s.src=current?current.replace('mobile-results-filters-v1.js','mobile-results-filters-runtime-v1.js'):'/poisk-turov-test/v2/mobile-results-filters-runtime-v1.js';s.async=false;s.onload=()=>{window.__V2MobileResultsFiltersLoading=false;};s.onerror=()=>{window.__V2MobileResultsFiltersLoading=false;};document.head.appendChild(s);}
load();
if(mq&&typeof mq.addEventListener==='function')mq.addEventListener('change',load);
})();