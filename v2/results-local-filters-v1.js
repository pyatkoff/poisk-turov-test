(function(){'use strict';
if(window.V2ResultsLocalFiltersV1)return;
const form=document.getElementById('tourSearch'),results=document.getElementById('results'),summary=document.getElementById('resultSummary');
if(!form||!results)return;
const localNames=new Set(['stars','rating','price_from','price_till','region','subregion','food','operator']);
let sourceItems=[],applying=false,lastRenderedCount=0;
function renderer(){return window.V2Results||null;}
function lifecycle(){return window.V2SearchLifecycle||null;}
function catalogs(){return window.V2Catalogs||null;}
function hasSource(){return sourceItems.length>0||!!results.querySelector('.hotel-card');}
function isLocalTarget(target){return !!(target&&target.name&&localNames.has(String(target.name)));}
function ratingThreshold(value){const map={2:3,3:3.5,4:4,5:4.5};return map[Number(value)]||0;}
function first(value){return Array.isArray(value)?String(value[0]||''):String(value||'');}
function entityId(value){if(value===null||value===undefined)return'';if(typeof value==='string'||typeof value==='number')return String(value);if(typeof value==='object'){for(const key of ['id','value','code'])if(value[key]!==undefined&&value[key]!==null&&String(value[key])!=='')return String(value[key]);}return'';}
function current(){const f=new FormData(form);return{stars:Number(f.get('stars')||0),rating:ratingThreshold(f.get('rating')),priceFrom:Number(f.get('price_from')||0),priceTo:Number(f.get('price_till')||0),region:String(f.get('region')||''),subregion:String(f.get('subregion')||''),meal:String(f.get('food')||''),operator:String(f.get('operator')||'')};}
function sourceBounds(){const lc=lifecycle(),s=lc&&lc.snapshot||{};return{stars:Number(s.hotelCategory||0),rating:ratingThreshold(s.hotelRating),priceFrom:Number(s.priceFrom||0),priceTo:Number(s.priceTo||0),region:first(s.regionIds),subregion:first(s.subregionIds),meal:String(s.meal||''),operator:first(s.operatorIds)};}
function sameOrNarrower(base,currentValue){return !base||String(base)===String(currentValue||'');}
function canUseLocal(filters){const base=sourceBounds();if(base.stars&&(!filters.stars||filters.stars<base.stars))return false;if(base.rating&&(!filters.rating||filters.rating<base.rating))return false;if(base.priceFrom&&(!filters.priceFrom||filters.priceFrom<base.priceFrom))return false;if(base.priceTo&&(!filters.priceTo||filters.priceTo>base.priceTo))return false;if(!sameOrNarrower(base.region,filters.region)||!sameOrNarrower(base.subregion,filters.subregion)||!sameOrNarrower(base.meal,filters.meal)||!sameOrNarrower(base.operator,filters.operator))return false;return true;}
function hotelMatches(h,filters){const stars=Number(h&&h.category||0),rating=Number(h&&h.rating||0);if(filters.stars&&stars<filters.stars)return false;if(filters.rating&&rating<filters.rating)return false;if(filters.region&&entityId(h&&h.region)!==filters.region)return false;if(filters.subregion&&entityId(h&&h.subRegion)!==filters.subregion)return false;return true;}
function tourMatches(t,filters){const price=Number(t&&t.price||0);if(filters.priceFrom&&(!price||price<filters.priceFrom))return false;if(filters.priceTo&&(!price||price>filters.priceTo))return false;if(filters.meal&&entityId(t&&t.meal)!==filters.meal)return false;if(filters.operator&&entityId(t&&t.operator)!==filters.operator)return false;return true;}
function filteredHotel(h,filters){if(!hotelMatches(h,filters))return null;const tours=Array.isArray(h&&h.tours)?h.tours:[],needsTourFilter=!!(filters.priceFrom||filters.priceTo||filters.meal||filters.operator);if(!needsTourFilter){const price=Number(h&&h.price||0);if(filters.priceFrom&&(!price||price<filters.priceFrom))return null;if(filters.priceTo&&(!price||price>filters.priceTo))return null;return h;}if(!tours.length)return null;const kept=tours.filter(t=>tourMatches(t,filters));if(!kept.length)return null;const prices=kept.map(t=>Number(t&&t.price||0)).filter(v=>Number.isFinite(v)&&v>0);return Object.assign({},h,{tours:kept,price:prices.length?Math.min(...prices):h.price});}
function matches(h,filters){return !!filteredHotel(h,filters);}
function apply(){const r=renderer(),filters=current();if(!r||typeof r.render!=='function'||!sourceItems.length||!canUseLocal(filters))return[];const filtered=sourceItems.map(h=>filteredHotel(h,filters)).filter(Boolean);applying=true;try{r.render(filtered);}finally{applying=false;}lastRenderedCount=filtered.length;if(summary)summary.textContent=filtered.length===sourceItems.length?'Показано отелей: '+filtered.length:'Подходит '+filtered.length+' из '+sourceItems.length+' отелей';window.dispatchEvent(new CustomEvent('v2:results-local-filtered',{detail:{shown:filtered.length,total:sourceItems.length,filters}}));return filtered;}
function captureRendered(event){if(applying)return;const items=event&&event.detail&&Array.isArray(event.detail.items)?event.detail.items:[];sourceItems=items.slice();lastRenderedCount=items.length;if(!items.length)return;const filters=current();if(Object.values(filters).some(Boolean)&&canUseLocal(filters))apply();}
function refreshDependentCatalogs(event){const c=catalogs();if(!c||typeof c.handleChange!=='function')return;if(event.target&&event.target.name==='region'){const sub=form.elements.subregion;if(sub)sub.value='';}if(event.target&&['region','subregion'].includes(event.target.name))Promise.resolve(c.handleChange(event)).catch(err=>console.warn('local filter catalog refresh',err));}
function handleLocal(event){const target=event.target;if(!isLocalTarget(target)||!hasSource())return;const filters=current();if(!canUseLocal(filters))return;event.stopImmediatePropagation();if(target.removeAttribute)target.removeAttribute('aria-invalid');refreshDependentCatalogs(event);apply();}
form.addEventListener('input',handleLocal,true);
form.addEventListener('change',handleLocal,true);
window.addEventListener('v2:results-rendered',captureRendered);
window.addEventListener('v2:search-reset',event=>{const detail=event&&event.detail||{};if(!detail.dirty){sourceItems=[];lastRenderedCount=0;}});
window.V2ResultsLocalFiltersV1={apply,current,sourceBounds,canUseLocal,matches,filteredHotel,isLocalTarget,get sourceItems(){return sourceItems.slice();},get shown(){return lastRenderedCount;},names:Array.from(localNames),version:1};
})();
