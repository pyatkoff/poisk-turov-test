(function(){'use strict';
var rail=document.querySelector('.results-filter-rail');if(!rail)return;
var source=[];var applying=false;var state={priceMax:0,seaMax:0,operator:'',charter:false};
function text(v){if(v==null)return'';if(typeof v==='string'||typeof v==='number')return String(v);if(typeof v==='object')return text(v.russianName||v.fullRussianName||v.name||v.title||v.value||'');return'';}
function price(h){var values=[];(Array.isArray(h&&h.tours)?h.tours:[]).forEach(function(t){var p=Number(t&&t.price||0);if(p>0)values.push(p);});var hp=Number(h&&h.price||0);if(hp>0)values.push(hp);return values.length?Math.min.apply(null,values):0;}
function operatorName(t){return text(t&&t.operator);}
function tourMatches(t){var p=Number(t&&t.price||0);if(state.priceMax&&p&&p>state.priceMax)return false;if(state.operator&&operatorName(t)!==state.operator)return false;if(state.charter&&!t.isCharter)return false;return true;}
function filteredHotel(h){var sea=Number(h&&h.seaDistance||0);if(state.seaMax&&(!sea||sea>state.seaMax))return null;var tours=Array.isArray(h&&h.tours)?h.tours:[];if(!tours.length){var hp=price(h);if(state.priceMax&&hp&&hp>state.priceMax)return null;if(state.operator||state.charter)return null;return h;}var kept=tours.filter(tourMatches);if(!kept.length)return null;var prices=kept.map(function(t){return Number(t&&t.price||0);}).filter(function(p){return p>0;});return Object.assign({},h,{tours:kept,price:prices.length?Math.min.apply(null,prices):h.price});}
function money(n){return new Intl.NumberFormat('ru-RU').format(Number(n||0));}
function word(n){var x=Math.abs(Number(n)||0)%100,y=x%10;if(x>10&&x<20)return'отелей';if(y===1)return'отель';if(y>=2&&y<=4)return'отеля';return'отелей';}
function operators(list){var set={};(Array.isArray(list)?list:[]).forEach(function(h){(Array.isArray(h&&h.tours)?h.tours:[]).forEach(function(t){var n=operatorName(t);if(n)set[n]=1;});});return Object.keys(set).sort(function(a,b){return a.localeCompare(b,'ru');});}
function renderRail(){
 var ops=operators(source),prices=source.map(price).filter(function(p){return p>0;}),min=prices.length?Math.floor(Math.min.apply(null,prices)/5000)*5000:40000,max=prices.length?Math.ceil(Math.max.apply(null,prices)/5000)*5000:250000;if(max<=min)max=min+5000;if(!state.priceMax||state.priceMax>max)state.priceMax=max;
 rail.innerHTML='<div class="filter-rail-head"><div><div class="filter-rail-title">Уточнить отдых</div><small class="search3-filter-note">без повторения параметров поиска</small></div><button type="button" class="filter-reset-link" data-s3-reset>Сбросить</button></div>'+
 '<div class="search3-filter-scenarios"><button type="button" data-s3-sea="200">🏖 До моря 200 м</button><button type="button" data-s3-charter>✈️ Чартер</button></div>'+
 '<label class="filter-range"><span>Цена найденного тура</span><input type="range" data-s3-price min="'+min+'" max="'+max+'" step="5000" value="'+state.priceMax+'"><small data-s3-price-label>до '+money(state.priceMax)+' ₽</small></label>'+
 '<fieldset><legend>Расположение у моря</legend><label class="filter-option"><input type="radio" name="s3-sea" value="0" '+(!state.seaMax?'checked':'')+'><span>Любое</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="200" '+(state.seaMax===200?'checked':'')+'><span>До 200 м</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="500" '+(state.seaMax===500?'checked':'')+'><span>До 500 м</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="1000" '+(state.seaMax===1000?'checked':'')+'><span>До 1 км</span></label></fieldset>'+
 '<fieldset><legend>Туроператор</legend><select class="search3-filter-select" data-s3-operator><option value="">Все туроператоры</option>'+ops.map(function(n){return'<option value="'+n.replace(/&/g,'&amp;').replace(/"/g,'&quot;')+'" '+(state.operator===n?'selected':'')+'>'+n.replace(/&/g,'&amp;').replace(/</g,'&lt;')+'</option>';}).join('')+'</select></fieldset>'+
 '<fieldset><legend>Тип перелёта</legend><label class="filter-option"><input type="checkbox" data-s3-charter-check '+(state.charter?'checked':'')+'><span>Только чартерные туры</span></label></fieldset>'+
 '<div class="filter-rail-result"><span>Подходит</span><strong><b data-s3-count>'+source.length+'</b> <span data-s3-word>'+word(source.length)+'</span></strong></div>';
}
function updateCount(n){var c=rail.querySelector('[data-s3-count]'),w=rail.querySelector('[data-s3-word]');if(c)c.textContent=String(n);if(w)w.textContent=word(n);}
function apply(){if(!source.length||!window.V2Results||typeof window.V2Results.render!=='function')return;var filtered=source.map(filteredHotel).filter(Boolean);applying=true;try{window.V2Results.render(filtered,{keepResultsShell:true});}finally{applying=false;}updateCount(filtered.length);}
function reset(){state={priceMax:0,seaMax:0,operator:'',charter:false};renderRail();apply();}
rail.addEventListener('input',function(e){var t=e.target;if(t.matches('[data-s3-price]')){state.priceMax=Number(t.value||0);var out=rail.querySelector('[data-s3-price-label]');if(out)out.textContent='до '+money(state.priceMax)+' ₽';apply();}});
rail.addEventListener('change',function(e){var t=e.target;if(t.name==='s3-sea'){state.seaMax=Number(t.value||0);apply();}else if(t.matches('[data-s3-operator]')){state.operator=t.value||'';apply();}else if(t.matches('[data-s3-charter-check]')){state.charter=!!t.checked;apply();}});
rail.addEventListener('click',function(e){var resetBtn=e.target.closest('[data-s3-reset]');if(resetBtn){reset();return;}var sea=e.target.closest('[data-s3-sea]');if(sea){state.seaMax=Number(sea.dataset.s3Sea||0);renderRail();apply();return;}var charter=e.target.closest('[data-s3-charter]');if(charter){state.charter=!state.charter;renderRail();apply();}});
window.addEventListener('v2:results-rendered',function(e){if(applying)return;var items=e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[];source=items.slice();renderRail();updateCount(source.length);});
window.addEventListener('v2:search-reset',function(){source=[];state={priceMax:0,seaMax:0,operator:'',charter:false};renderRail();});
renderRail();
})();
