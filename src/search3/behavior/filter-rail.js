

/* donor:search3-filter-rail-preview.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
var rail=document.querySelector('.results-filter-rail'),form=document.getElementById('tourSearch');if(!rail||!form)return;
var source=[],applying=false,lastApplied=null,rangeMin=0,rangeMax=0,state={priceMax:0,seaMax:0,charter:false};
var priceApplyRun=0,priceApplyQueued=0;
function money(n){return new Intl.NumberFormat('ru-RU').format(Number(n||0));}
function word(n){var x=Math.abs(Number(n)||0)%100,y=x%10;if(x>10&&x<20)return'отелей';if(y===1)return'отель';if(y>=2&&y<=4)return'отеля';return'отелей';}
function allPrices(list){var values=[];(Array.isArray(list)?list:[]).forEach(function(h){var tours=Array.isArray(h&&h.tours)?h.tours:[];if(tours.length)tours.forEach(function(t){var p=Number(t&&t.price||0);if(p>0)values.push(p);});else{var hp=Number(h&&h.price||0);if(hp>0)values.push(hp);}});return values;}
function tourMatches(t){var p=Number(t&&t.price||0);if(state.priceMax&&p&&p>state.priceMax)return false;if(state.charter&&!t.isCharter)return false;return true;}
function filteredHotel(h){var sea=Number(h&&h.seaDistance||0);if(state.seaMax&&(!sea||sea>state.seaMax))return null;var tours=Array.isArray(h&&h.tours)?h.tours:[];if(!tours.length){var hp=Number(h&&h.price||0);if(state.priceMax&&hp&&hp>state.priceMax)return null;if(state.charter)return null;return h;}var kept=tours.filter(tourMatches);if(!kept.length)return null;var prices=kept.map(function(t){return Number(t&&t.price||0);}).filter(function(p){return p>0;});return Object.assign({},h,{tours:kept,price:prices.length?Math.min.apply(null,prices):h.price});}
function sameRefs(a,b){if(!Array.isArray(a)||!Array.isArray(b)||a.length!==b.length)return false;for(var i=0;i<a.length;i+=1)if(a[i]!==b[i])return false;return true;}
function activeCount(){var n=0;if(rangeMax&&state.priceMax&&state.priceMax<rangeMax)n++;if(state.seaMax)n++;if(state.charter)n++;return n;}
/* @include behavior/filter-rail/availability.js */
/* @include behavior/filter-rail/render.js */
function updateCount(n){var c=rail.querySelector('[data-s3-count]'),w=rail.querySelector('[data-s3-word]');if(c)c.textContent=String(n);if(w)w.textContent=word(n);announce(n);}
function renderItems(items){lastApplied=items.slice();applying=true;try{window.V2Results.render(items,{keepResultsShell:true});}finally{applying=false;}}
function apply(){if(!source.length||!window.V2Results||typeof window.V2Results.render!=='function')return;var filtered=source.map(filteredHotel).filter(Boolean);renderItems(filtered);updateCount(filtered.length);}
function applyOrUpdateEmpty(){if(source.length)apply();else updateCount(0);}
function schedulePriceApply(){if(priceApplyQueued)return;var run=++priceApplyRun;priceApplyQueued=run;window.requestAnimationFrame(function(){if(priceApplyQueued!==run)return;priceApplyQueued=0;applyOrUpdateEmpty();});}
function cancelPriceApply(){priceApplyRun+=1;priceApplyQueued=0;}
function resetFormFilters(){['stars','rating','food','price_from','price_till','hotel'].forEach(function(name){var el=form.elements[name];if(!el)return;if(el.tagName==='SELECT')el.selectedIndex=0;else el.value='';});['onlyDirect','onlyCharter'].forEach(function(name){var el=form.elements[name];if(el)el.checked=false;});}
function submitFormFilters(){if(typeof form.requestSubmit==='function')form.requestSubmit();else form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));}
function reset(){cancelPriceApply();state={priceMax:0,seaMax:0,charter:false};resetFormFilters();renderRail();if(source.length&&window.V2Results&&typeof window.V2Results.render==='function')renderItems(source);if(window.innerWidth>999)submitFormFilters();}
function editSearch(){form.classList.add('search3-mobile-advanced-open');var edit=document.getElementById('resultsSearchEdit');if(edit)edit.click();else form.scrollIntoView({behavior:'smooth',block:'start'});setTimeout(function(){var quality=form.querySelector('.search3-quality');if(quality)quality.scrollIntoView({behavior:'smooth',block:'nearest'});},220);}

rail.addEventListener('input',function(e){var t=e.target;if(t.matches('[data-s3-price]')){state.priceMax=Number(t.value||0);var out=rail.querySelector('[data-s3-price-label]');if(out)out.textContent='от '+money(rangeMin)+' ₽ — до '+money(state.priceMax)+' ₽';schedulePriceApply();}});
rail.addEventListener('change',function(e){var t=e.target;if(t.name==='s3-sea'){cancelPriceApply();state.seaMax=Number(t.value||0);applyOrUpdateEmpty();}else if(t.matches('[data-s3-charter-check]')){cancelPriceApply();state.charter=!!t.checked;if(form.elements.onlyCharter)form.elements.onlyCharter.checked=state.charter;applyOrUpdateEmpty();}});
rail.addEventListener('click',function(e){var panel=e.target.closest('[data-s3-panel]');if(panel){editSearch();return;}if(e.target.closest('[data-s3-reset]')){reset();return;}if(e.target.closest('[data-s3-edit-search]')){editSearch();return;}});
window.addEventListener('v2:results-rendered',function(e){if(applying)return;var items=e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[];if(lastApplied&&sameRefs(items,lastApplied))return;cancelPriceApply();source=items.slice();lastApplied=null;syncPriceRange();syncPriceAvailability();syncSeaAvailability();syncCharterAvailability();if(source.length&&activeCount())apply();else updateCount(source.length);});
window.addEventListener('v2:search-reset',function(){cancelPriceApply();source=[];lastApplied=null;rangeMin=0;rangeMax=0;state={priceMax:0,seaMax:0,charter:!!(form.elements.onlyCharter&&form.elements.onlyCharter.checked)};renderRail();});
renderRail();
})();
