(function(){'use strict';
if(window.V2Accessibility)return;
const form=document.getElementById('tourSearch'),status=document.getElementById('status'),results=document.getElementById('results'),selected=document.getElementById('selectedTour'),tools=document.getElementById('resultsTools');
function closeSearchPanels(){if(!form)return;const extras=form.querySelector('details.extras');if(extras)extras.open=false;form.querySelectorAll('details.dates-picker,details.guests-picker,details.service-picker').forEach(d=>{d.open=false;});}
closeSearchPanels();
if(status){status.hidden=true;status.setAttribute('role','status');status.setAttribute('aria-live','polite');status.setAttribute('aria-atomic','true');}
if(tools)tools.hidden=true;
if(selected)selected.hidden=true;
if(results)results.setAttribute('aria-busy','false');
function markBusy(value){if(results)results.setAttribute('aria-busy',value?'true':'false');}
function safeFocus(el){if(!el||typeof el.focus!=='function')return;try{el.focus({preventScroll:true});}catch(e){el.focus();}}
function decorate(root){const scope=root||document;scope.querySelectorAll('.hotel-info-toggle').forEach((b,i)=>{const card=b.closest('.hotel-card'),detail=card&&card.querySelector('.hotel-inline-detail');if(!detail)return;if(!detail.id)detail.id='hotel-detail-'+String(card&&card.dataset.hotelId||i)+'-'+i;b.setAttribute('aria-controls',detail.id);b.setAttribute('aria-expanded',detail.hidden?'false':'true');});scope.querySelectorAll('.room-detail-toggle').forEach((b,i)=>{const host=b.closest('.room-details-host'),detail=host&&host.querySelector('.room-detail-content');if(!detail)return;if(!detail.id)detail.id='room-detail-'+i;b.setAttribute('aria-controls',detail.id);b.setAttribute('aria-expanded',detail.hidden?'false':'true');});scope.querySelectorAll('.hotel-gallery-thumb').forEach((b,i)=>b.setAttribute('aria-label','Фото отеля '+(i+1)));scope.querySelectorAll('.room-gallery-thumb').forEach((b,i)=>b.setAttribute('aria-label','Фото номера '+(i+1)));scope.querySelectorAll('.lead-message').forEach(x=>{x.setAttribute('role','status');x.setAttribute('aria-live','polite');});}
window.addEventListener('v2:search-reset',()=>markBusy(true));
window.addEventListener('v2:search-started',()=>{closeSearchPanels();markBusy(true);});
window.addEventListener('v2:search-complete',()=>{markBusy(false);decorate(document);});
window.addEventListener('v2:search-error',()=>markBusy(false));
window.addEventListener('v2:results-rendered',e=>decorate(e.detail&&e.detail.results||document));
window.addEventListener('v2:tour-selected',()=>{if(selected){selected.setAttribute('tabindex','-1');selected.setAttribute('aria-label','Выбранный тур');decorate(selected);requestAnimationFrame(()=>safeFocus(selected));}});
window.addEventListener('v2:tour-selection-reset',()=>{if(results){results.setAttribute('tabindex','-1');requestAnimationFrame(()=>safeFocus(results));}});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('.hotel-info-toggle,.room-detail-toggle');if(!b)return;setTimeout(()=>{const detail=b.classList.contains('room-detail-toggle')?b.closest('.room-details-host')?.querySelector('.room-detail-content'):b.closest('.hotel-card')?.querySelector('.hotel-inline-detail');if(detail)b.setAttribute('aria-expanded',detail.hidden?'false':'true');},0);},false);
window.V2Accessibility={decorate,closeSearchPanels,safeFocus,version:3};
})();