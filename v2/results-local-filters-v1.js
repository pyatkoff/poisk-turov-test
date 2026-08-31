(function(){'use strict';
if(window.V2ResultsLocalFiltersV1)return;
const form=document.getElementById('tourSearch'),results=document.getElementById('results'),summary=document.getElementById('resultSummary');
if(!form||!results)return;
const localNames=new Set(['stars','rating','price_from','price_till']);
let sourceItems=[],applying=false,lastRenderedCount=0;
function renderer(){return window.V2Results||null;}
function hasSource(){return sourceItems.length>0||!!results.querySelector('.hotel-card');}
function isLocalTarget(target){return !!(target&&target.name&&localNames.has(String(target.name)));}
function ratingThreshold(value){const map={2:3,3:3.5,4:4,5:4.5};return map[Number(value)]||0;}
function current(){const f=new FormData(form);return{stars:Number(f.get('stars')||0),rating:ratingThreshold(f.get('rating')),priceFrom:Number(f.get('price_from')||0),priceTo:Number(f.get('price_till')||0)};}
function matches(h,filters){const stars=Number(h&&h.category||0),rating=Number(h&&h.rating||0),price=Number(h&&h.price||0);if(filters.stars&&stars<filters.stars)return false;if(filters.rating&&rating<filters.rating)return false;if(filters.priceFrom&&(!price||price<filters.priceFrom))return false;if(filters.priceTo&&(!price||price>filters.priceTo))return false;return true;}
function apply(){const r=renderer();if(!r||typeof r.render!=='function'||!sourceItems.length)return[];const filtered=sourceItems.filter(h=>matches(h,current()));applying=true;try{r.render(filtered);}finally{applying=false;}lastRenderedCount=filtered.length;if(summary)summary.textContent=filtered.length===sourceItems.length?'Показано отелей: '+filtered.length:'Подходит '+filtered.length+' из '+sourceItems.length+' отелей';window.dispatchEvent(new CustomEvent('v2:results-local-filtered',{detail:{shown:filtered.length,total:sourceItems.length,filters:current()}}));return filtered;}
function captureRendered(event){if(applying)return;const items=event&&event.detail&&Array.isArray(event.detail.items)?event.detail.items:[];sourceItems=items.slice();lastRenderedCount=items.length;if(!items.length)return;const f=current();if(f.stars||f.rating||f.priceFrom||f.priceTo)apply();}
function handleLocal(event){const target=event.target;if(!isLocalTarget(target)||!hasSource())return;event.stopImmediatePropagation();if(target.removeAttribute)target.removeAttribute('aria-invalid');apply();}
form.addEventListener('input',handleLocal,true);
form.addEventListener('change',handleLocal,true);
window.addEventListener('v2:results-rendered',captureRendered);
window.addEventListener('v2:search-reset',event=>{const detail=event&&event.detail||{};if(!detail.dirty){sourceItems=[];lastRenderedCount=0;}});
window.V2ResultsLocalFiltersV1={apply,current,matches,isLocalTarget,get sourceItems(){return sourceItems.slice();},get shown(){return lastRenderedCount;},names:Array.from(localNames),version:1};
})();
