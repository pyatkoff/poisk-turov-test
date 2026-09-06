

/* donor:search3-filter-rail-preview.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
var rail=document.querySelector('.results-filter-rail'),form=document.getElementById('tourSearch');if(!rail||!form)return;
var source=[],applying=false,lastApplied=[],rangeMin=0,rangeMax=0,state={priceMax:0,seaMax:0,charter:false};
var priceApplyRun=0,priceApplyQueued=0;
function money(n){return new Intl.NumberFormat('ru-RU').format(Number(n||0));}
function word(n){var x=Math.abs(Number(n)||0)%100,y=x%10;if(x>10&&x<20)return'отелей';if(y===1)return'отель';if(y>=2&&y<=4)return'отеля';return'отелей';}
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
function renderRail(announceSource){
 var prices=allPrices(source),min=prices.length?Math.floor(Math.min.apply(null,prices)/5000)*5000:40000,max=prices.length?Math.ceil(Math.max.apply(null,prices)/5000)*5000:250000;if(max<=min)max=min+5000;rangeMin=min;rangeMax=max;if(state.priceMax>max)state.priceMax=max;var displayedMax=state.priceMax||max;
 var popular='<label class="filter-range"><span>Цена за тур</span><small data-s3-price-label>от '+money(rangeMin)+' ₽ — до '+money(displayedMax)+' ₽</small><input type="range" data-ds2-price data-s3-price min="'+min+'" max="'+max+'" step="5000" value="'+displayedMax+'"></label>'+
  editRow('Категория отеля',fieldLabel('stars','Любая'),'stars')+editRow('Рейтинг отеля',fieldLabel('rating','Любой'),'rating');
 var hotel=editRow('Питание',fieldLabel('food','Любое'),'food')+editRow('Конкретный отель',fieldLabel('hotel','Любой'));
 var sea='<label class="filter-option"><input type="radio" name="s3-sea" value="0" '+(!state.seaMax?'checked':'')+'><span>Любое расстояние</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="200" '+(state.seaMax===200?'checked':'')+'><span>До 200 м</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="500" '+(state.seaMax===500?'checked':'')+'><span>До 500 м</span></label><label class="filter-option"><input type="radio" name="s3-sea" value="1000" '+(state.seaMax===1000?'checked':'')+'><span>До 1 км</span></label>';
 var flight='<label class="filter-option"><input type="checkbox" data-s3-charter-check '+(state.charter?'checked':'')+'><span>Только чартер</span></label>'+editRow('Прямой рейс',form.elements.onlyDirect&&form.elements.onlyDirect.checked?'Только прямой':'Любой','onlyDirect');
 rail.innerHTML='<div class="filter-rail-head"><div><div class="filter-rail-title">Фильтры</div><small>Дополнительные параметры</small></div><button type="button" class="filter-reset-link" data-s3-reset>Сбросить все</button></div>'+section('Популярные',popular)+section('Отель',hotel)+section('Расположение',sea)+section('Перелёт',flight)+'<div class="filter-rail-result"><span>Подходит</span><strong><b data-s3-count>'+source.length+'</b> <span data-s3-word>'+word(source.length)+'</span></strong></div>';
 if(announceSource!==false)announce(source.length);
}
function updateCount(n){var c=rail.querySelector('[data-s3-count]'),w=rail.querySelector('[data-s3-word]');if(c)c.textContent=String(n);if(w)w.textContent=word(n);announce(n);}
function apply(){if(!source.length||!window.V2Results||typeof window.V2Results.render!=='function')return;var filtered=source.map(filteredHotel).filter(Boolean);lastApplied=filtered.slice();applying=true;try{window.V2Results.render(filtered,{keepResultsShell:true});}finally{applying=false;}updateCount(filtered.length);}
function schedulePriceApply(){if(priceApplyQueued)return;var run=++priceApplyRun;priceApplyQueued=run;window.requestAnimationFrame(function(){if(priceApplyQueued!==run)return;priceApplyQueued=0;apply();});}
function cancelPriceApply(){priceApplyRun+=1;priceApplyQueued=0;}
function resetFormFilters(){['stars','rating','food','price_till','hotel'].forEach(function(name){var el=form.elements[name];if(!el)return;if(el.tagName==='SELECT')el.selectedIndex=0;else el.value='';});if(form.elements.onlyDirect)form.elements.onlyDirect.checked=false;}
function submitFormFilters(){if(typeof form.requestSubmit==='function')form.requestSubmit();else form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));}
function reset(){cancelPriceApply();state={priceMax:0,seaMax:0,charter:false};resetFormFilters();renderRail();apply();if(window.innerWidth>999)submitFormFilters();}
function editSearch(){form.classList.add('search3-mobile-advanced-open');var edit=document.getElementById('resultsSearchEdit');if(edit)edit.click();else form.scrollIntoView({behavior:'smooth',block:'start'});setTimeout(function(){var quality=form.querySelector('.search3-quality');if(quality)quality.scrollIntoView({behavior:'smooth',block:'nearest'});},220);}

rail.addEventListener('input',function(e){var t=e.target;if(t.matches('[data-s3-price]')){state.priceMax=Number(t.value||0);var out=rail.querySelector('[data-s3-price-label]');if(out)out.textContent='от '+money(rangeMin)+' ₽ — до '+money(state.priceMax)+' ₽';schedulePriceApply();}});
rail.addEventListener('change',function(e){var t=e.target;if(t.name==='s3-sea'){cancelPriceApply();state.seaMax=Number(t.value||0);apply();}else if(t.matches('[data-s3-charter-check]')){cancelPriceApply();state.charter=!!t.checked;apply();}});
rail.addEventListener('click',function(e){var panel=e.target.closest('[data-s3-panel]');if(panel){editSearch();return;}if(e.target.closest('[data-s3-reset]')){reset();return;}if(e.target.closest('[data-s3-edit-search]')){editSearch();return;}});
window.addEventListener('v2:results-rendered',function(e){if(applying)return;var items=e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[];if(lastApplied.length&&sameRefs(items,lastApplied))return;cancelPriceApply();source=items.slice();lastApplied=[];renderRail(false);if(source.length&&activeCount())apply();else announce(source.length);});
window.addEventListener('v2:search-reset',function(){cancelPriceApply();source=[];lastApplied=[];rangeMin=0;rangeMax=0;state={priceMax:0,seaMax:0,charter:false};renderRail();});
renderRail();
})();
