function fieldLabel(name,fallback){var el=form.elements[name];if(!el)return fallback||'Любое';var label='';if(el.tagName==='SELECT'){var opt=el.options&&el.selectedIndex>=0?el.options[el.selectedIndex]:null;label=opt?String(opt.textContent||'').trim():'';}else label=String(el.value||'').trim();return label||fallback||'Любое';}
function announce(resultCount){var count=activeCount();rail.dataset.s3ActiveCount=String(count);window.dispatchEvent(new CustomEvent('search3:result-filters-changed',{detail:{activeCount:count,resultCount:Number(resultCount)||0}}));}
function section(title,html,attrs){return'<section class="search3-filter-section"'+(attrs?' '+attrs:'')+'><h4>'+title+'</h4>'+html+'</section>';}
function editRow(label,value,panel){return'<button type="button" class="search3-filter-edit-row" '+(panel?'data-s3-panel="'+panel+'"':'data-s3-edit-search')+'><span>'+label+'</span><b>'+value+'</b><i aria-hidden="true">›</i></button>';}
function resolvePriceBounds(){var prices=allPrices(source),min=prices.length?Math.floor(Math.min.apply(null,prices)/5000)*5000:40000,max=prices.length?Math.ceil(Math.max.apply(null,prices)/5000)*5000:250000;if(max<=min)max=min+5000;rangeMin=min;rangeMax=max;return state.priceMax?Math.max(min,Math.min(state.priceMax,max)):max;}
function syncPriceRange(){var displayedMax=resolvePriceBounds(),input=rail.querySelector('[data-s3-price]'),out=rail.querySelector('[data-s3-price-label]');if(input){input.min=String(rangeMin);input.max=String(rangeMax);input.step='5000';input.value=String(displayedMax);}if(out)out.textContent='от '+money(rangeMin)+' ₽ — до '+money(displayedMax)+' ₽';}
function seaOption(value,label,checked){return'<label class="filter-option"><input type="radio" name="s3-sea" value="'+value+'" '+(checked?'checked':'')+'><span>'+label+'</span></label>';}
function renderRail(){
 var displayedMax=resolvePriceBounds(),min=rangeMin,max=rangeMax;
 var popular='<label class="filter-range" data-s3-price-field><span>Цена за тур</span><small data-s3-price-label>от '+money(rangeMin)+' ₽ — до '+money(displayedMax)+' ₽</small><input type="range" data-s3-price min="'+min+'" max="'+max+'" step="5000" value="'+displayedMax+'"></label>'+
  editRow('Категория отеля',fieldLabel('stars','Любая'),'stars')+editRow('Рейтинг отеля',fieldLabel('rating','Любой'),'rating');
 var hotel=editRow('Питание',fieldLabel('food','Любое'),'food')+editRow('Конкретный отель',fieldLabel('hotel','Любой'));
 var sea=seaOption(0,'Любое расстояние',!state.seaMax)+seaOption(200,'До 200 м',state.seaMax===200)+seaOption(500,'До 500 м',state.seaMax===500)+seaOption(1000,'До 1 км',state.seaMax===1000);
 var flight='<label class="filter-option" data-s3-charter-field><input type="checkbox" data-s3-charter-check '+(state.charter?'checked':'')+'><span>Только чартер</span></label>'+editRow('Прямой рейс',form.elements.onlyDirect&&form.elements.onlyDirect.checked?'Только прямой':'Любой','onlyDirect');
 rail.innerHTML='<div class="filter-rail-head"><div><div class="filter-rail-title">Фильтры</div><small>Дополнительные параметры</small></div><button type="button" class="filter-reset-link" data-s3-reset>Сбросить все</button></div>'+section('Популярные',popular)+section('Отель',hotel)+section('Расположение',sea,'data-s3-sea-section')+section('Перелёт',flight)+'<div class="filter-rail-result"><span>Подходит</span><strong><b data-s3-count>'+source.length+'</b> <span data-s3-word>'+word(source.length)+'</span></strong></div>';
 syncPriceAvailability();
 syncSeaAvailability();
 syncCharterAvailability();
 announce(source.length);
}
