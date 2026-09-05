

/* donor:search3-filter-rail-preview.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
var rail=document.querySelector('.results-filter-rail'),form=document.getElementById('tourSearch');if(!rail||!form)return;
var source=[],applying=false,lastApplied=[],rangeMin=0,rangeMax=0,formDirty=false,drawerSnapshot=null,state={priceMax:0,seaMax:0,charter:false};
function money(n){return new Intl.NumberFormat('ru-RU').format(Number(n||0));}
function word(n){var x=Math.abs(Number(n)||0)%100,y=x%10;if(x>10&&x<20)return'отелей';if(y===1)return'отель';if(y>=2&&y<=4)return'отеля';return'отелей';}
function esc(v){return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');}
function textValue(v){return v&&typeof v==='object'?String(v.name||v.russianName||v.title||''):String(v||'');}
function price(h){var values=[];(Array.isArray(h&&h.tours)?h.tours:[]).forEach(function(t){var p=Number(t&&t.price||0);if(p>0)values.push(p);});var hp=Number(h&&h.price||0);if(hp>0)values.push(hp);return values.length?Math.min.apply(null,values):0;}
function allPrices(list){var values=[];(Array.isArray(list)?list:[]).forEach(function(h){var tours=Array.isArray(h&&h.tours)?h.tours:[];if(tours.length)tours.forEach(function(t){var p=Number(t&&t.price||0);if(p>0)values.push(p);});else{var hp=Number(h&&h.price||0);if(hp>0)values.push(hp);}});return values;}
function tourMatches(t){var p=Number(t&&t.price||0);if(state.priceMax&&p&&p>state.priceMax)return false;if(state.charter&&!t.isCharter)return false;return true;}
function filteredHotel(h){var sea=Number(h&&h.seaDistance||0);if(state.seaMax&&(!sea||sea>state.seaMax))return null;var tours=Array.isArray(h&&h.tours)?h.tours:[];if(!tours.length){var hp=price(h);if(state.priceMax&&hp&&hp>state.priceMax)return null;if(state.charter)return null;return h;}var kept=tours.filter(tourMatches);if(!kept.length)return null;var prices=kept.map(function(t){return Number(t&&t.price||0);}).filter(function(p){return p>0;});return Object.assign({},h,{tours:kept,price:prices.length?Math.min.apply(null,prices):h.price});}
function sameRefs(a,b){if(!Array.isArray(a)||!Array.isArray(b)||a.length!==b.length)return false;for(var i=0;i<a.length;i+=1)if(a[i]!==b[i])return false;return true;}
function activeCount(){var n=0;if(rangeMax&&state.priceMax&&state.priceMax<rangeMax)n++;if(state.seaMax)n++;if(state.charter)n++;return n;}
function fieldLabel(name,fallback){var el=form.elements[name];if(!el)return fallback||'Любое';var label='';if(el.tagName==='SELECT'){var opt=el.options&&el.selectedIndex>=0?el.options[el.selectedIndex]:null;label=opt?String(opt.textContent||'').trim():'';}else label=String(el.value||'').trim();return label||fallback||'Любое';}
function announce(resultCount){var count=activeCount();rail.dataset.s3ActiveCount=String(count);window.dispatchEvent(new CustomEvent('search3:result-filters-changed',{detail:{activeCount:count,resultCount:Number(resultCount)||0}}));}
function section(title,html){return'<section class="search3-filter-section"><h4>'+title+'</h4>'+html+'</section>';}
function editRow(label,value,panel){return'<button type="button" class="search3-filter-edit-row" '+(panel?'data-s3-panel="'+panel+'"':'data-s3-edit-search')+'><span>'+label+'</span><b>'+value+'</b><i aria-hidden="true">›</i></button>';}
function renderRail(){
 var prices=allPrices(source),min=prices.length?Math.floor(Math.min.apply(null,prices)/5000)*5000:40000,max=prices.length?Math.ceil(Math.max.apply(null,prices)/5000)*5000:250000;if(max<=min)max=min+5000;rangeMin=min;rangeMax=max;if(state.priceMax>max)state.priceMax=max;var displayedMax=state.priceMax||max;
 var popular='<label class="filter-range"><span>Цена за тур</span><small data-s3-price-label>от '+money(rangeMin)+' ₽ — до '+money(displayedMax)+' ₽</small><input type="range" data-ds2-price data-s3-price min="'+min+'" max="'+max+'" step="5000" value="'+displayedMax+'"></label>'+
  editRow('Категория отеля',fieldLabel('stars','Любая'),'stars')+editRow('Рейтинг отеля',fieldLabel('rating','Любой'),'rating');
 var hotel=editRow('Питание',fieldLabel('food','Любое'),'food')+editRow('Конкретный отель',fieldLabel('hotel','Любой'));
 var sea='<label class="filter-option"><input type="radio" name="s3-sea" value="0" '+(!state.seaMax?'checked':'')+'><span>Любое расстояние</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="200" '+(state.seaMax===200?'checked':'')+'><span>До 200 м</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="500" '+(state.seaMax===500?'checked':'')+'><span>До 500 м</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="1000" '+(state.seaMax===1000?'checked':'')+'><span>До 1 км</span></label>';
 var flight='<label class="filter-option"><input type="checkbox" data-s3-charter-check '+(state.charter?'checked':'')+'><span>Только чартер</span></label>'+editRow('Прямой рейс',form.elements.onlyDirect&&form.elements.onlyDirect.checked?'Только прямой':'Любой','onlyDirect');
 rail.innerHTML='<div class="filter-rail-head"><button type="button" class="filter-rail-back" data-s3-close-filters aria-label="Вернуться к результатам">←</button><div><div class="filter-rail-title">Фильтры</div><small>Дополнительные параметры</small></div><button type="button" class="filter-reset-link" data-s3-reset>Сбросить все</button></div>'+section('Популярные',popular)+section('Отель',hotel)+section('Расположение',sea)+section('Перелёт',flight)+'<div class="filter-rail-result"><span>Подходит</span><strong><b data-s3-count>'+source.length+'</b> <span data-s3-word>'+word(source.length)+'</span></strong></div><div class="search3-filter-mobile-footer"><button type="button" class="search3-filter-mobile-apply" data-s3-close-filters data-s3-commit-filters>Показать туры <span data-s3-mobile-result-count>('+source.length+')</span></button></div>';
 announce(source.length);
}
