(function(){'use strict';
if(window.V2Accessibility)return;
const status=document.getElementById('status'),results=document.getElementById('results'),selected=document.getElementById('selectedTour');
if(status){status.setAttribute('role','status');status.setAttribute('aria-live','polite');status.setAttribute('aria-atomic','true');}
if(results)results.setAttribute('aria-busy','false');
function markBusy(value){if(results)results.setAttribute('aria-busy',value?'true':'false');}
function decorate(root){const scope=root||document;scope.querySelectorAll('.hotel-info-toggle').forEach((b,i)=>{const card=b.closest('.hotel-card'),detail=card&&card.querySelector('.hotel-inline-detail');if(!detail)return;if(!detail.id)detail.id='hotel-detail-'+String(card&&card.dataset.hotelId||i)+'-'+i;b.setAttribute('aria-controls',detail.id);b.setAttribute('aria-expanded',detail.hidden?'false':'true');});scope.querySelectorAll('.hotel-gallery-thumb').forEach((b,i)=>b.setAttribute('aria-label','Фото отеля '+(i+1)));scope.querySelectorAll('.room-gallery-thumb').forEach((b,i)=>b.setAttribute('aria-label','Фото номера '+(i+1)));scope.querySelectorAll('.lead-message').forEach(x=>{x.setAttribute('role','status');x.setAttribute('aria-live','polite');});}
window.addEventListener('v2:search-reset',()=>markBusy(true));
window.addEventListener('v2:search-started',()=>markBusy(true));
window.addEventListener('v2:search-complete',()=>{markBusy(false);decorate(document);});
window.addEventListener('v2:search-error',()=>markBusy(false));
window.addEventListener('v2:results-rendered',e=>decorate(e.detail&&e.detail.results||document));
window.addEventListener('v2:tour-selected',()=>{if(selected)selected.setAttribute('tabindex','-1');decorate(selected||document);});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('.hotel-info-toggle');if(!b)return;setTimeout(()=>{const card=b.closest('.hotel-card'),detail=card&&card.querySelector('.hotel-inline-detail');if(detail)b.setAttribute('aria-expanded',detail.hidden?'false':'true');},0);},false);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>decorate(document),{once:true});else decorate(document);
function loadUXScript(src,flag,ready){if(window[ready]||document.querySelector('script['+flag+']'))return;const script=document.createElement('script');script.setAttribute(flag,'1');script.src=src;script.defer=true;document.head.appendChild(script);}
loadUXScript('/poisk-turov-test/v2/mobile-results-filters-v1.js','data-v2-mobile-results-filters','V2MobileResultsFiltersV1');
loadUXScript('/poisk-turov-test/v2/primary-meal-ux-v1.js','data-v2-primary-meal-ux','V2PrimaryMealUXV1');
window.V2Accessibility={decorate,version:1};
})();