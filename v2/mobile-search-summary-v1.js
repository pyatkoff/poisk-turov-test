(function(){'use strict';
if(window.__V2MobileSearchSummaryLoading)return;
const mq=window.matchMedia?window.matchMedia('(max-width:700px)'):{matches:false};
const current=document.currentScript&&document.currentScript.src?document.currentScript.src:'';
function runtimeSrc(){return current?current.replace('mobile-search-summary-v1.js','mobile-search-summary-runtime-v1.js'):'/poisk-turov-test/v2/mobile-search-summary-runtime-v1.js';}
function loadDynamic(){if(!mq.matches||window.__V2MobileSearchSummaryLoaded||window.__V2MobileSearchSummaryLoading)return;window.__V2MobileSearchSummaryLoading=true;const s=document.createElement('script');s.src=runtimeSrc();s.async=false;s.onload=()=>{window.__V2MobileSearchSummaryLoading=false;window.__V2MobileSearchSummaryLoaded=true;};s.onerror=()=>{window.__V2MobileSearchSummaryLoading=false;};document.head.appendChild(s);}
if(mq.matches&&document.readyState==='loading'){document.write('<script src="'+runtimeSrc().replace(/"/g,'&quot;')+'"><\/script>');window.__V2MobileSearchSummaryLoaded=true;}
else loadDynamic();
if(mq&&typeof mq.addEventListener==='function')mq.addEventListener('change',loadDynamic);
})();