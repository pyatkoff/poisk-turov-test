(function(){'use strict';
var rail=document.querySelector('.results-filter-rail');if(!rail)return;
var source=[];var applying=false;var lastApplied=[];var rangeMax=0;var state={priceMax:0,seaMax:0,operator:'',charter:false};
function text(v){if(v==null)return'';if(typeof v==='string'||typeof v==='number')return String(v);if(typeof v==='object')return text(v.russianName||v.fullRussianName||v.name||v.title||v.value||'');return'';}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(ch){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch];});}
function price(h){var values=[];(Array.isArray(h&&h.tours)?h.tours:[]).forEach(function(t){var p=Number(t&&t.price||0);if(p>0)values.push(p);});var hp=Number(h&&h.price||0);if(hp>0)values.push(hp);return values.length?Math.min.apply(null,values):0;}
function allPrices(list){var values=[];(Array.isArray(list)?list:[]).forEach(function(h){var tours=Array.isArray(h&&h.tours)?h.tours:[];if(tours.length){tours.forEach(function(t){var p=Number(t&&t.price||0);if(p>0)values.push(p);});}else{var hp=Number(h&&h.price||0);if(hp>0)values.push(hp);}});return values;}
function operatorName(t){return text(t&&t.operator);}
function tourMatches(t){var p=Number(t&&t.price||0);if(state.priceMax&&p&&p>state.priceMax)return false;if(state.operator&&operatorName(t)!==state.operator)return false;if(state.charter&&!t.isCharter)return false;return true;}
function filteredHotel(h){var sea=Number(h&&h.seaDistance||0);if(state.seaMax&&(!sea||sea>state.seaMax))return null;var tours=Array.isArray(h&&h.tours)?h.tours:[];if(!tours.length){var hp=price(h);if(state.priceMax&&hp&&hp>state.priceMax)return null;if(state.operator||state.charter)return null;return h;}var kept=tours.filter(tourMatches);if(!kept.length)return null;var prices=kept.map(function(t){return Number(t&&t.price||0);}).filter(function(p){return p>0;});return Object.assign({},h,{tours:kept,price:prices.length?Math.min.apply(null,prices):h.price});}
function money(n){return new Intl.NumberFormat('ru-RU').format(Number(n||0));}
function word(n){var x=Math.abs(Number(n)||0)%100,y=x%10;if(x>10&&x<20)return'отелей';if(y===1)return'отель';if(y>=2&&y<=4)return'отеля';return'отелей';}
function operators(list){var set={};(Array.isArray(list)?list:[]).forEach(function(h){(Array.isArray(h&&h.tours)?h.tours:[]).forEach(function(t){var n=operatorName(t);if(n)set[n]=1;});});return Object.keys(set).sort(function(a,b){return a.localeCompare(b,'ru');});}
function sameRefs(a,b){if(!Array.isArray(a)||!Array.isArray(b)||a.length!==b.length)return false;for(var i=0;i<a.length;i+=1){if(a[i]!==b[i])return false;}return true;}
function activeCount(){var n=0;if(rangeMax&&state.priceMax&&state.priceMax<rangeMax)n+=1;if(state.seaMax)n+=1;if(state.operator)n+=1;if(state.charter)n+=1;return n;}
function announce(resultCount){var count=activeCount();rail.dataset.s3ActiveCount=String(count);window.dispatchEvent(new CustomEvent('search3:result-filters-changed',{detail:{activeCount:count,resultCount:Number(resultCount)||0}}));}
function renderRail(){
 var ops=operators(source),prices=allPrices(source),min=prices.length?Math.floor(Math.min.apply(null,prices)/5000)*5000:40000,max=prices.length?Math.ceil(Math.max.apply(null,prices)/5000)*5000:250000;if(max<=min)max=min+5000;rangeMax=max;if(!state.priceMax||state.priceMax>max)state.priceMax=max;
 rail.innerHTML='<div class="filter-rail-head"><div><div class="filter-rail-title">Фильтры</div></div><button type="button" class="filter-reset-link" data-s3-reset>Сбросить все</button></div>'+
 '<div class="search3-filter-scenarios"><button type="button" data-s3-sea="200">🏖 До моря 200 м</button><button type="button" data-s3-charter>✈️ Чартер</button></div>'+
 '<label class="filter-range"><span>Цена на тур</span><input type="range" data-s3-price min="'+min+'" max="'+max+'" step="5000" value="'+state.priceMax+'"><small data-s3-price-label>до '+money(state.priceMax)+' ₽</small></label>'+
 '<fieldset><legend>Расположение</legend><label class="filter-option"><input type="radio" name="s3-sea" value="0" '+(!state.seaMax?'checked':'')+'><span>Любое</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="200" '+(state.seaMax===200?'checked':'')+'><span>До 200 м</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="500" '+(state.seaMax===500?'checked':'')+'><span>До 500 м</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="1000" '+(state.seaMax===1000?'checked':'')+'><span>До 1 км</span></label></fieldset>'+
 '<fieldset><legend>Туроператор</legend><select class="search3-filter-select" data-s3-operator><option value="">Все туроператоры</option>'+ops.map(function(n){return'<option value="'+esc(n)+'" '+(state.operator===n?'selected':'')+'>'+esc(n)+'</option>';}).join('')+'</select></fieldset>'+
 '<fieldset><legend>Перелёт</legend><label class="filter-option"><input type="checkbox" data-s3-charter-check '+(state.charter?'checked':'')+'><span>Только чартерные туры</span></label></fieldset>'+
 '<div class="filter-rail-result"><span>Подходит</span><strong><b data-s3-count>'+source.length+'</b> <span data-s3-word>'+word(source.length)+'</span></strong></div>'+
 '<div class="search3-filter-mobile-footer"><button type="button" class="search3-filter-mobile-apply" data-s3-close-filters>Показать <span data-s3-mobile-result-count>'+source.length+' '+word(source.length)+'</span></button></div>';
 announce(source.length);
}
function updateCount(n){var c=rail.querySelector('[data-s3-count]'),w=rail.querySelector('[data-s3-word]'),m=rail.querySelector('[data-s3-mobile-result-count]');if(c)c.textContent=String(n);if(w)w.textContent=word(n);if(m)m.textContent=String(n)+' '+word(n);announce(n);}
function apply(){if(!source.length||!window.V2Results||typeof window.V2Results.render!=='function')return;var filtered=source.map(filteredHotel).filter(Boolean);lastApplied=filtered.slice();applying=true;try{window.V2Results.render(filtered,{keepResultsShell:true});}finally{applying=false;}updateCount(filtered.length);}
function reset(){state={priceMax:0,seaMax:0,operator:'',charter:false};renderRail();apply();}
rail.addEventListener('input',function(e){var t=e.target;if(t.matches('[data-s3-price]')){state.priceMax=Number(t.value||0);var out=rail.querySelector('[data-s3-price-label]');if(out)out.textContent='до '+money(state.priceMax)+' ₽';apply();}});
rail.addEventListener('change',function(e){var t=e.target;if(t.name==='s3-sea'){state.seaMax=Number(t.value||0);apply();}else if(t.matches('[data-s3-operator]')){state.operator=t.value||'';apply();}else if(t.matches('[data-s3-charter-check]')){state.charter=!!t.checked;apply();}});
rail.addEventListener('click',function(e){var resetBtn=e.target.closest('[data-s3-reset]');if(resetBtn){reset();return;}var sea=e.target.closest('[data-s3-sea]');if(sea){state.seaMax=Number(sea.dataset.s3Sea||0);renderRail();apply();return;}var charter=e.target.closest('[data-s3-charter]');if(charter){state.charter=!state.charter;renderRail();apply();}});
window.addEventListener('v2:results-rendered',function(e){if(applying)return;var items=e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[];if(lastApplied.length&&sameRefs(items,lastApplied)){updateCount(items.length);return;}source=items.slice();lastApplied=[];renderRail();updateCount(source.length);});
window.addEventListener('v2:search-reset',function(){source=[];lastApplied=[];rangeMax=0;state={priceMax:0,seaMax:0,operator:'',charter:false};renderRail();});
renderRail();
})();
