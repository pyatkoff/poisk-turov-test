/* Search3 safe candidate: approved donor composition. Source e5baf32f455cdb0aa1a704964f28e5efbebf57ff. */
/* donor:search3-candidate.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function cleanField(f){if(!f)return;f.classList.remove('field-wide','main-stars','main-meal','primary-step','primary-step-1','primary-step-2','primary-step-3','primary-step-4','primary-step-5','primary-step-6','primary-step-7','result-filter-priority','result-filter-stars','result-filter-meal');var select=f.querySelector('select');if(select){select.classList.remove('ux-native-hidden','meal-native-select');select.removeAttribute('aria-hidden');select.tabIndex=0;}var q=f.querySelector('.stars-quick,.meal-quick');if(q)q.hidden=true;}
function makeComposite(label,cls){var box=document.createElement('label');box.className='field search3-composite '+cls;box.innerHTML='<span>'+label+'</span><div class="search3-composite__control"></div>';return box;}
function formatDate(v){var p=String(v||'').split('-');return p.length===3?p[2]+'.'+p[1]+'.'+p[0]:String(v||'');}
function clampNight(v){var n=parseInt(v,10);if(!Number.isFinite(n))n=7;return Math.max(1,Math.min(28,n));}
function init(){
  var form=document.getElementById('tourSearch');if(!form||form.dataset.search3Ready==='1')return;
  form.dataset.search3Ready='1';document.body.classList.add('search3-candidate');
  var hero=document.querySelector('.v2-product-hero');if(hero){var legacyH1=hero.querySelector('h1');if(legacyH1)legacyH1.remove();hero.hidden=true;}
  var shell=form.parentNode;if(shell&&!document.querySelector('.search3-page-intro')){var intro=document.createElement('div');intro.className='search3-page-intro';intro.innerHTML='<div class="search3-breadcrumb">Главная <span>›</span> Поиск туров</div><h1>Поиск туров</h1><p>Найдите туры по лучшим ценам от надежных туроператоров</p>';shell.insertBefore(intro,form);}
  var title=form.querySelector('.search-section-title');if(title)title.hidden=true;
  var main=form.querySelector('.main-fields'),extras=form.querySelector(':scope > details.extras');if(!main||!extras)return;
  var refs={};['from','country','dateFrom','dateTo','daysFrom','daysTill','count_people','child_count'].forEach(function(name){refs[name]=form.elements[name]||null;});
  var childAges=document.getElementById('childAges');
  var originalFields={};Object.keys(refs).forEach(function(name){var el=refs[name];originalFields[name]=el&&el.closest?el.closest('.field'):null;});
  var submit=form.querySelector(':scope > .search-submit');if(childAges)childAges.remove();main.innerHTML='';main.className='main-fields search3-primary-grid';
  function appendOriginal(name,label,cls){var f=originalFields[name];if(!f)return null;cleanField(f);var s=f.querySelector(':scope > span');if(s)s.textContent=label;if(cls)f.classList.add(cls);main.appendChild(f);return f;}
  appendOriginal('from','Откуда','search3-from');appendOriginal('country','Куда','search3-country');
  var region=field(form,'region');if(region){cleanField(region);var rs=region.querySelector(':scope > span');if(rs)rs.textContent='Курорт / регион';region.classList.add('search3-region');main.appendChild(region);}
  var d1=refs.dateFrom,d2=refs.dateTo;if(d1&&d2){var dateBox=makeComposite('Дата вылета','search3-dates'),dateCtl=dateBox.querySelector('.search3-composite__control');d1.classList.add('search3-direct-control');d2.classList.add('search3-direct-control');d1.setAttribute('aria-label','Вылет не раньше');d2.setAttribute('aria-label','Вылет не позже');dateCtl.appendChild(d1);var dash=document.createElement('span');dash.className='search3-composite__dash';dash.textContent='—';dateCtl.appendChild(dash);dateCtl.appendChild(d2);main.appendChild(dateBox);}
  var n1=refs.daysFrom,n2=refs.daysTill;if(n1&&n2){
    var nightBox=makeComposite('Ночей','search3-nights'),nightCtl=nightBox.querySelector('.search3-composite__control');
    function nightSelect(input,label){
      var select=document.createElement('select');select.className='search3-direct-control';select.setAttribute('aria-label',label);select.dataset.search3Night=input.name;
      for(var i=1;i<=28;i++){var option=document.createElement('option');option.value=String(i);option.textContent=String(i);select.appendChild(option);}
      function sync(){select.value=String(clampNight(input.value));}
      input.hidden=true;input.tabIndex=-1;input.setAttribute('aria-hidden','true');
      input.addEventListener('input',sync);input.addEventListener('change',sync);select.addEventListener('focus',sync);
      select.addEventListener('change',function(){input.value=select.value;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));});
      form.addEventListener('reset',function(){setTimeout(sync,0);});sync();nightCtl.appendChild(input);nightCtl.appendChild(select);
    }
    nightSelect(n1,'Минимум ночей');var nd=document.createElement('span');nd.className='search3-composite__dash';nd.textContent='—';nightCtl.appendChild(nd);nightSelect(n2,'Максимум ночей');main.appendChild(nightBox);
  }
  var adults=refs.count_people,children=refs.child_count,touristBox=null;if(adults&&children){touristBox=makeComposite('Туристы','search3-tourists');var touristCtl=touristBox.querySelector('.search3-composite__control');var summary=document.createElement('button');summary.type='button';summary.className='search3-tourists__summary';var pop=document.createElement('div');pop.className='search3-tourists__pop';pop.hidden=true;pop.innerHTML='<span>Взрослых</span>';pop.appendChild(adults);var ch=document.createElement('span');ch.textContent='Детей';pop.appendChild(ch);pop.appendChild(children);if(childAges){childAges.classList.add('search3-tourists__ages');pop.appendChild(childAges);}function syncGuests(){var a=Number(adults.value||2),c=Number(children.value||0);summary.textContent=a+' '+(a===1?'взрослый':'взрослых')+(c?' · '+c+' '+(c===1?'ребёнок':'детей'):'');}summary.addEventListener('click',function(e){e.preventDefault();pop.hidden=!pop.hidden;});adults.addEventListener('change',syncGuests);children.addEventListener('change',syncGuests);syncGuests();touristCtl.appendChild(summary);touristCtl.appendChild(pop);main.appendChild(touristBox);}
  if(submit){submit.innerHTML='<span>Найти туры</span><b aria-hidden="true">→</b>';main.appendChild(submit);}
  var quality=document.createElement('section');quality.className='search3-quality';quality.innerHTML='<div class="search3-quality__grid"></div>';main.parentNode.insertBefore(quality,main.nextSibling);var grid=quality.querySelector('.search3-quality__grid');
  [['stars','Категория отеля','search3-stars'],['rating','Оценка отеля','search3-rating'],['food','Питание','search3-meal'],['price_till','Бюджет на тур','search3-budget'],['hotel','Конкретный отель','search3-hotel']].forEach(function(x){var f=field(form,x[0]);if(!f)return;cleanField(f);var s=f.querySelector(':scope > span');if(s)s.textContent=x[1];f.classList.add(x[2]);grid.appendChild(f);});
  var budget=form.elements.price_till;if(budget)budget.placeholder='до 250 000 ₽';var hotel=form.elements.hotel;if(hotel)hotel.setAttribute('aria-label','Конкретный отель');
  var quick=document.createElement('div');quick.className='search3-quick';
  var flight=document.createElement('label');flight.className='search3-quick__label search3-quick__label--static';flight.innerHTML='<input type="checkbox" checked disabled><span>Только с перелётом</span>';quick.appendChild(flight);
  var direct=form.elements.onlyDirect;if(direct){var directLabel=document.createElement('label');directLabel.className='search3-quick__label search3-quick__label--direct';directLabel.appendChild(direct);var directText=document.createElement('span');directText.textContent='Прямой рейс';directLabel.appendChild(directText);quick.appendChild(directLabel);}
  quality.parentNode.insertBefore(quick,extras);extras.classList.remove('result-filter-rail');extras.hidden=true;
  if(touristBox)document.addEventListener('click',function(e){if(!touristBox.contains(e.target)){var p=touristBox.querySelector('.search3-tourists__pop');if(p)p.hidden=true;}},true);
  setTimeout(function(){['region','stars','rating','food'].forEach(function(name){var f=field(form,name);if(!f)return;cleanField(f);if(name==='region'){var s=f.querySelector(':scope > span');if(s)s.textContent='Курорт / регион';f.classList.add('search3-region');if(f.parentNode!==main){var dateBoxNow=main.querySelector('.search3-dates');main.insertBefore(f,dateBoxNow||main.children[2]||null);}}else{if(name==='stars'){var st=f.querySelector(':scope > span');if(st)st.textContent='Категория отеля';}if(name==='rating'){var rt=f.querySelector(':scope > span');if(rt)rt.textContent='Оценка отеля';}if(name==='food'){var ft=f.querySelector(':scope > span');if(ft)ft.textContent='Питание';}if(f.parentNode!==grid)grid.appendChild(f);}});},100);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(init,20);},{once:true});else setTimeout(init,20);
})();


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
function updateCount(n){var c=rail.querySelector('[data-s3-count]'),w=rail.querySelector('[data-s3-word]'),m=rail.querySelector('[data-s3-mobile-result-count]');if(c)c.textContent=String(n);if(w)w.textContent=word(n);if(m)m.textContent='('+String(n)+')';announce(n);}
function apply(){if(!source.length||!window.V2Results||typeof window.V2Results.render!=='function')return;var filtered=source.map(filteredHotel).filter(Boolean);lastApplied=filtered.slice();applying=true;try{window.V2Results.render(filtered,{keepResultsShell:true});}finally{applying=false;}updateCount(filtered.length);}
function filterNames(){return['stars','rating','food','price_till','hotel','onlyDirect'];}
function captureFormFilters(){var out={};filterNames().forEach(function(name){var el=form.elements[name];if(!el)return;out[name]=el.type==='checkbox'?!!el.checked:String(el.value||'');});return out;}
function restoreFormFilters(values){if(!values)return;filterNames().forEach(function(name){var el=form.elements[name];if(!el||!Object.prototype.hasOwnProperty.call(values,name))return;if(el.type==='checkbox')el.checked=!!values[name];else el.value=String(values[name]||'');});formDirty=false;}
function resetFormFilters(){['stars','rating','food','price_till','hotel'].forEach(function(name){var el=form.elements[name];if(!el)return;if(el.tagName==='SELECT')el.selectedIndex=0;else el.value='';});if(form.elements.onlyDirect)form.elements.onlyDirect.checked=false;formDirty=true;}
function submitFormFilters(){if(!formDirty)return;formDirty=false;if(typeof form.requestSubmit==='function')form.requestSubmit();else form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));}
function reset(){state={priceMax:0,seaMax:0,charter:false};resetFormFilters();renderRail();apply();if(window.innerWidth>999)submitFormFilters();}
function editSearch(){document.body.classList.remove('search3-filter-open');var overlay=document.querySelector('.search3-filter-overlay');if(overlay)overlay.hidden=true;form.classList.add('search3-mobile-advanced-open');var edit=document.getElementById('resultsSearchEdit');if(edit)edit.click();else form.scrollIntoView({behavior:'smooth',block:'start'});setTimeout(function(){var quality=form.querySelector('.search3-quality');if(quality)quality.scrollIntoView({behavior:'smooth',block:'nearest'});},220);}

function panelTitle(name){return{stars:'Категория отеля',rating:'Рейтинг отеля',food:'Питание',onlyDirect:'Прямой рейс'}[name]||'Фильтр';}
function panelOptions(name){var el=form.elements[name];if(name==='onlyDirect')return[{value:'0',label:'Любой рейс'},{value:'1',label:'Только прямой рейс'}];if(!el||el.tagName!=='SELECT')return[];return Array.from(el.options).map(function(o){return{value:String(o.value||''),label:String(o.textContent||'').trim()};}).filter(function(o){return o.label;});}
function panelValue(name){var el=form.elements[name];if(!el)return'';if(name==='onlyDirect')return el.checked?'1':'0';return String(el.value||'');}
function removeSubpanel(){var old=rail.querySelector('.search3-filter-subpanel');if(old)old.remove();rail.classList.remove('search3-filter-subpanel-open');}
function renderSubpanel(name){if(window.innerWidth>999){editSearch();return;}removeSubpanel();var options=panelOptions(name),current=panelValue(name);if(!options.length){editSearch();return;}var panel=document.createElement('section');panel.className='search3-filter-subpanel';panel.dataset.s3Subpanel=name;panel.setAttribute('role','dialog');panel.setAttribute('aria-modal','true');panel.setAttribute('aria-label',panelTitle(name));panel.innerHTML='<header><button type="button" data-s3-subpanel-back aria-label="Назад">←</button><h3>'+esc(panelTitle(name))+'</h3><button type="button" data-s3-subpanel-reset>Сбросить</button></header><div class="search3-filter-subpanel-options">'+options.map(function(o){var selected=o.value===current;return'<button type="button" class="search3-filter-subpanel-option'+(selected?' is-selected':'')+'" data-s3-subpanel-value="'+esc(o.value)+'" aria-pressed="'+(selected?'true':'false')+'"><span>'+esc(o.label)+'</span><i aria-hidden="true"></i></button>';}).join('')+'</div><footer><button type="button" data-s3-subpanel-apply>Показать туры <span>('+(rail.querySelector('[data-s3-count]')?rail.querySelector('[data-s3-count]').textContent:source.length)+')</span></button></footer>';rail.appendChild(panel);rail.classList.add('search3-filter-subpanel-open');rail.scrollTop=0;var first=panel.querySelector('[data-s3-subpanel-value]');if(first)first.focus({preventScroll:true});}
function setPanelValue(name,value){var el=form.elements[name];if(!el)return;if(name==='onlyDirect')el.checked=value==='1';else el.value=value;formDirty=true;renderSubpanel(name);}
function applyAndClose(){removeSubpanel();renderRail();var button=rail.querySelector('[data-s3-close-filters]');if(button)button.click();else submitFormFilters();}

rail.addEventListener('input',function(e){var t=e.target;if(t.matches('[data-s3-price]')){state.priceMax=Number(t.value||0);var out=rail.querySelector('[data-s3-price-label]');if(out)out.textContent='от '+money(rangeMin)+' ₽ — до '+money(state.priceMax)+' ₽';apply();}});
rail.addEventListener('change',function(e){var t=e.target;if(t.name==='s3-sea'){state.seaMax=Number(t.value||0);apply();}else if(t.matches('[data-s3-charter-check]')){state.charter=!!t.checked;apply();}});
rail.addEventListener('click',function(e){var panel=e.target.closest('[data-s3-panel]');if(panel){renderSubpanel(panel.dataset.s3Panel);return;}var option=e.target.closest('[data-s3-subpanel-value]');if(option){setPanelValue((rail.querySelector('.search3-filter-subpanel')||{}).dataset.s3Subpanel||'',String(option.dataset.s3SubpanelValue||''));return;}if(e.target.closest('[data-s3-subpanel-back]')){removeSubpanel();renderRail();return;}if(e.target.closest('[data-s3-subpanel-reset]')){var current=(rail.querySelector('.search3-filter-subpanel')||{}).dataset.s3Subpanel||'';setPanelValue(current,current==='onlyDirect'?'0':'');return;}if(e.target.closest('[data-s3-subpanel-apply]')){applyAndClose();return;}if(e.target.closest('[data-s3-reset]')){reset();return;}if(e.target.closest('[data-s3-commit-filters]')){return;}if(e.target.closest('[data-s3-edit-search]')){editSearch();return;}});
window.addEventListener('search3:filters-opened',function(){drawerSnapshot=captureFormFilters();});
window.addEventListener('search3:filters-cancelled',function(){restoreFormFilters(drawerSnapshot);drawerSnapshot=null;removeSubpanel();renderRail();});
window.addEventListener('search3:filters-committed',function(){drawerSnapshot=null;submitFormFilters();});
window.addEventListener('v2:results-rendered',function(e){if(applying)return;var items=e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[];if(lastApplied.length&&sameRefs(items,lastApplied)){updateCount(items.length);return;}source=items.slice();lastApplied=[];renderRail();updateCount(source.length);});
window.addEventListener('v2:search-reset',function(){source=[];lastApplied=[];rangeMin=0;rangeMax=0;formDirty=false;drawerSnapshot=null;state={priceMax:0,seaMax:0,charter:false};removeSubpanel();renderRail();});
renderRail();
})();


/* donor:search3-mobile-search-entry.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
var form=document.getElementById('tourSearch');if(!form)return;
function ensureStyles(){if(document.getElementById('search3-mobile-search-entry-style'))return;var s=document.createElement('style');s.id='search3-mobile-search-entry-style';s.textContent='.search3-mobile-search-entry{display:none!important}@media(max-width:760px){body.search3-candidate #tourSearch .search3-mobile-search-entry{display:grid!important;gap:10px!important;margin:11px 0 0!important;padding:11px 0 0!important;border-top:1px solid #e6ebf2!important}.search3-mobile-search-trust{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:6px!important}.search3-mobile-search-trust span{display:flex!important;align-items:center!important;justify-content:center!important;gap:4px!important;min-width:0!important;padding:7px 4px!important;border-radius:7px!important;background:#f6f8fb!important;color:#475467!important;font-size:7px!important;font-weight:800!important;text-align:center!important}.search3-mobile-search-trust b{color:#1463ff!important;font-size:9px!important}.search3-mobile-search-filter-button{width:100%!important;min-height:42px!important;border:1px solid #cfd9e8!important;border-radius:8px!important;background:#fff!important;color:#155eef!important;font:850 10px/1 var(--at-font)!important}.search3-mobile-search-filter-button span{margin-right:5px!important}.search3-mobile-advanced-open .search3-quality{display:block!important;margin-top:10px!important}.search3-mobile-advanced-open .search3-quality__grid{display:grid!important;grid-template-columns:1fr!important;gap:9px!important}.search3-mobile-advanced-open .search3-quick{display:grid!important;grid-template-columns:1fr 1fr!important;gap:10px 8px!important;margin-top:10px!important}.search3-mobile-advanced-open .search3-quick__label{display:inline-flex!important;width:100%!important}}';document.head.appendChild(s);}
function ensure(){
  ensureStyles();
  if(form.querySelector('.search3-mobile-search-entry'))return;
  var box=document.createElement('section');
  box.className='search3-mobile-search-entry';
  box.setAttribute('aria-label','Дополнительные возможности поиска');
  box.innerHTML='<div class="search3-mobile-search-trust"><span><b>✓</b>Актуальные цены</span><span><b>✈</b>Детали перелёта</span><span><b>?</b>Помощь менеджера</span></div><button type="button" class="search3-mobile-search-filter-button" aria-expanded="false"><span>☷</span><b>Фильтры</b></button>';
  var extras=form.querySelector(':scope > details.extras');if(extras)form.insertBefore(box,extras);else form.appendChild(box);
  var btn=box.querySelector('.search3-mobile-search-filter-button'),label=btn.querySelector('b');
  btn.addEventListener('click',function(){
    var open=!form.classList.contains('search3-mobile-advanced-open');
    form.classList.toggle('search3-mobile-advanced-open',open);
    btn.setAttribute('aria-expanded',open?'true':'false');
    if(label)label.textContent=open?'Скрыть фильтры':'Фильтры';
    if(open){var quality=form.querySelector('.search3-quality');if(quality)setTimeout(function(){quality.scrollIntoView({behavior:'smooth',block:'nearest'});},0);}
  });
}
function sync(){
  ensure();
  var hasResults=document.body.classList.contains('search3-has-results');
  var box=form.querySelector('.search3-mobile-search-entry');
  if(box)box.hidden=hasResults;
  if(hasResults)form.classList.remove('search3-mobile-advanced-open');
}
window.addEventListener('v2:results-rendered',function(){setTimeout(sync,0);});
window.addEventListener('v2:search-reset',function(){setTimeout(sync,0);});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',sync,{once:true});else sync();
})();


/* donor:search3-results-top.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
/* Donor CSS is bundled into the isolated candidate asset. */
var form=document.getElementById('tourSearch'),tools=document.getElementById('resultsTools'),heading=tools&&tools.querySelector('strong'),summary=document.getElementById('resultSummary'),searchSummary=document.getElementById('resultsSearchSummary'),edit=document.getElementById('resultsSearchEdit'),results=document.getElementById('results'),intro=document.querySelector('.search3-page-intro'),introTitle=intro&&intro.querySelector('h1'),introText=intro&&intro.querySelector('p'),breadcrumb=intro&&intro.querySelector('.search3-breadcrumb');
if(!form||!tools||!heading||!summary)return;
var mapButton=tools.querySelector('.results-map-button');if(mapButton){mapButton.textContent='';mapButton.setAttribute('aria-label','Карта');}
function word(n,one,few,many){var x=Math.abs(Number(n)||0)%100,y=x%10;if(x>10&&x<20)return many;if(y===1)return one;if(y>=2&&y<=4)return few;return many;}
function toursCount(items){return (Array.isArray(items)?items:[]).reduce(function(sum,h){return sum+(Array.isArray(h&&h.tours)?h.tours.length:0);},0);}
function selectedText(name){var el=form.elements[name];if(!el)return'';if(el.tagName==='SELECT'){var o=el.options&&el.selectedIndex>=0?el.options[el.selectedIndex]:null;return o?String(o.textContent||'').trim():'';}return String(el.value||'').trim();}
function compactRoute(){var from=selectedText('from'),country=selectedText('country'),region=selectedText('region');var dest=[country,region].filter(Boolean).join(', ');return [from,dest].filter(Boolean).join(' → ');}
function resetIntro(){if(introTitle)introTitle.textContent='Поиск туров';if(introText)introText.textContent='Найдите туры по лучшим ценам от надежных туроператоров';if(breadcrumb)breadcrumb.innerHTML='Главная <span>›</span> Поиск туров';}
function syncRoute(){if(searchSummary){var route=searchSummary.querySelector('#resultsSearchRoute');if(route){var text=compactRoute();if(text)route.textContent=text;}}resetIntro();}
function ensureMeta(){var existing=tools.querySelector('.search3-results-meta');if(existing)return existing;var meta=document.createElement('div');meta.className='search3-results-meta';meta.innerHTML='<span data-s3-hotels>0 отелей</span><span aria-hidden="true">·</span><span data-s3-tours>0 туров</span>';var first=tools.firstElementChild;if(first)first.appendChild(meta);return meta;}
function syncToolsFlow(){tools.style.setProperty('position','static','important');tools.style.setProperty('top','auto','important');tools.style.setProperty('z-index','auto','important');tools.style.setProperty('transform','none','important');tools.style.setProperty('-webkit-backdrop-filter','none','important');tools.style.setProperty('backdrop-filter','none','important');}
function syncDesktopGeometry(has){syncToolsFlow();if(window.innerWidth<1000){tools.style.removeProperty('width');tools.style.removeProperty('margin-left');tools.style.removeProperty('margin-right');tools.style.removeProperty('padding-left');tools.style.removeProperty('padding-right');return;}var grid=form.querySelector('.search3-primary-grid');if(grid){grid.style.setProperty('display','grid','important');grid.style.setProperty('width','100%','important');grid.style.setProperty('max-width','none','important');grid.style.setProperty('grid-template-columns','minmax(0,1.08fr) minmax(0,1.08fr) minmax(0,1fr) minmax(0,1.28fr) minmax(0,.68fr) minmax(0,.88fr) 150px','important');grid.style.setProperty('gap','12px','important');Array.prototype.forEach.call(grid.children,function(child){child.style.setProperty('grid-column','auto','important');child.style.setProperty('min-width','0','important');child.style.setProperty('max-width','none','important');});}if(has&&results){var shell=tools.parentElement,rr=results.getBoundingClientRect(),sr=shell&&shell.getBoundingClientRect();if(sr&&rr.width>0){tools.style.setProperty('box-sizing','border-box','important');tools.style.setProperty('width',rr.width+'px','important');tools.style.setProperty('margin-left',Math.max(0,rr.left-sr.left)+'px','important');tools.style.setProperty('margin-right','0','important');tools.style.setProperty('padding-left','9px','important');tools.style.setProperty('padding-right','9px','important');}}else{tools.style.removeProperty('width');tools.style.removeProperty('margin-left');tools.style.removeProperty('margin-right');tools.style.removeProperty('padding-left');tools.style.removeProperty('padding-right');}}
function syncResultsState(){var has=!!(results&&results.querySelector('.hotel-card'));document.body.classList.toggle('search3-has-results',has);if(has){document.body.classList.remove('search3-editing-search');syncRoute();}else resetIntro();syncDesktopGeometry(has);}
function update(items){items=Array.isArray(items)?items:[];var hotels=items.length,tours=toursCount(items);heading.textContent='Найдено '+tours+' '+word(tours,'тур','тура','туров');summary.textContent=hotels?hotels+' '+word(hotels,'отель','отеля','отелей')+' · актуальные варианты':'Актуальные варианты';var meta=ensureMeta(),h=meta.querySelector('[data-s3-hotels]'),t=meta.querySelector('[data-s3-tours]');if(h)h.textContent=hotels+' '+word(hotels,'отель','отеля','отелей');if(t)t.textContent=tours+' '+word(tours,'тур','тура','туров');document.body.classList.toggle('search3-has-results',hotels>0);document.body.classList.remove('search3-editing-search');if(hotels>0)syncRoute();else resetIntro();requestAnimationFrame(function(){syncDesktopGeometry(hotels>0);});}
window.addEventListener('v2:results-rendered',function(e){update(e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[]);});
window.addEventListener('v2:search-reset',function(){document.body.classList.remove('search3-has-results','search3-editing-search');heading.textContent='Предложения';summary.textContent='Актуальные варианты';var meta=tools.querySelector('.search3-results-meta');if(meta)meta.remove();resetIntro();syncDesktopGeometry(false);});
if(results){new MutationObserver(function(){requestAnimationFrame(syncResultsState);}).observe(results,{childList:true});syncResultsState();}
window.addEventListener('resize',function(){requestAnimationFrame(syncResultsState);});
if(edit)edit.addEventListener('click',function(){document.body.classList.add('search3-editing-search');form.scrollIntoView({behavior:'smooth',block:'start'});var focusTarget=form.querySelector('select,input:not([type="hidden"]),button');if(focusTarget)setTimeout(function(){try{focusTarget.focus({preventScroll:true});}catch(_e){focusTarget.focus();}},250);});
form.addEventListener('change',syncRoute);syncRoute();
})();


/* donor:search3-selected-tour-mobile.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
var selected=document.getElementById('selectedTour');if(!selected)return;
function injectMobileConvergence(){if(document.getElementById('search3-mobile-convergence-style'))return;var s=document.createElement('style');s.id='search3-mobile-convergence-style';s.textContent='@media(max-width:640px){'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-lead-cta,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-lead-cta-note,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-choice-summary,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .facts-secondary-toggle,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-confidence,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .search3-lead-shell,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review)>.lead-form{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review){display:flex!important;flex-direction:column!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .back-results{order:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .search3-booking-stepper{display:grid!important;order:4!important;width:100%!important;margin:0 0 8px!important;padding:7px 6px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-picture{order:1!important;height:148px!important;min-height:148px!important;border-radius:12px 12px 0 0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-head{order:2!important;min-height:0!important;padding:10px 12px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .facts{order:3!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .hotel-desc{order:8!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .hotel-desc-toggle{order:9!important;align-self:flex-start!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .room-details-host{order:6!important;width:100%!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .search3-final-sections{order:7!important;width:100%!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .tour-flights{order:5!important;width:100%!important;margin-top:10px!important;padding:12px!important;border-radius:12px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry){display:flex!important;flex-direction:column!important;max-width:none!important;margin:10px var(--at-page-edge) 36px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .back-results{order:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-stepper{order:1!important;width:100%!important;margin:0 0 10px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-review-heading{display:block!important;order:2!important;width:100%!important;margin:0 0 10px!important;padding:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-review-heading h2{font-size:22px!important;line-height:1.1!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-picture,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-head,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .facts,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .facts-secondary-toggle,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-choice-summary,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .hotel-desc,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .hotel-desc-toggle,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .room-details-host,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .tour-flights,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-final-sections,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-confidence,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-tour-detail-rail{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-lead-shell{display:block!important;order:3!important;width:100%!important;margin:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-lead-shell>.lead-form{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary{display:block!important;width:100%!important;max-width:none!important;position:static!important;grid-column:auto!important;grid-row:auto!important;margin:0!important;padding:12px!important;box-sizing:border-box!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__image{height:150px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-summary-actions{display:block!important;margin-top:12px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-summary-submit{width:100%!important;min-height:48px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry{display:flex!important;flex-direction:column!important;width:auto!important;max-width:none!important;margin:10px var(--at-page-edge) 36px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .search3-lead-back:not([hidden]){display:inline-flex!important;order:0!important;align-self:flex-start!important;margin:0 0 8px!important;padding:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .search3-lead-shell{display:flex!important;flex-direction:column!important;order:1!important;width:100%!important;max-width:none!important;gap:10px!important;margin:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .search3-lead-shell>.lead-form{display:block!important;order:1!important;width:100%!important;max-width:none!important;grid-column:auto!important;grid-row:auto!important;margin:0!important;padding:16px 14px!important;box-sizing:border-box!important;border-radius:12px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .search3-lead-shell>.search3-booking-summary{display:block!important;order:2!important;width:100%!important;max-width:none!important;position:static!important;grid-column:auto!important;grid-row:auto!important;margin:0!important;padding:12px!important;box-sizing:border-box!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-fields{display:grid!important;grid-template-columns:1fr!important;gap:10px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-fields>label{display:grid!important;gap:6px!important;width:100%!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form .section-heading strong{font-size:22px!important;line-height:1.1!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state]{display:block!important;min-height:0!important;align-items:stretch!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state] .section-heading,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state] .lead-fields,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state] .lead-consent,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state] .search3-lead-protection,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state] .lead-message,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state] .search3-lead-comment,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state]>button[type=submit]{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry .lead-form[data-search3-lead-state] .search3-lead-status{display:grid!important;width:100%!important;grid-template-columns:1fr!important;gap:12px!important;padding:8px 2px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-head h2{font-size:18px!important;line-height:1.08!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-head p{margin-top:3px!important;font-size:8px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .tour-flights .section-heading{display:flex!important;flex-direction:row!important;align-items:baseline!important;gap:8px!important;margin-bottom:9px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .tour-flights .section-heading strong{font-size:15px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .tour-flights .section-heading span{font-size:8px!important;text-align:right!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-variants{gap:8px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-choice{grid-template-columns:17px minmax(0,1fr)!important;padding:8px 9px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-choice b{grid-column:2!important;justify-self:start!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-segment{padding:8px 9px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-segment-title{display:flex!important;flex-direction:row!important;align-items:baseline!important;gap:8px!important;margin-bottom:6px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-segment-title span{margin-left:auto!important;text-align:right!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-baggage{margin-top:6px!important}'+
'.search3-selected-mobile-bar:not([hidden]){gap:12px!important}.search3-selected-mobile-bar__price{display:grid!important;grid-template-columns:minmax(0,1fr)!important;align-items:baseline!important;max-width:60%!important}.search3-selected-mobile-bar__price small{white-space:normal!important;line-height:1.2!important}.search3-selected-mobile-bar__price strong{display:block!important;margin-top:2px!important;line-height:1.2!important;white-space:normal!important}'+
'}'+
'@media(min-width:641px) and (max-width:999px){'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review){display:grid!important;grid-template-columns:230px minmax(0,1fr)!important;column-gap:0!important;align-items:stretch!important;margin:14px var(--at-page-edge) 44px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-lead-cta,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-lead-cta-note,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-choice-summary,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .facts-secondary-toggle,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-confidence,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .search3-lead-shell,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review)>.lead-form{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .back-results{grid-column:1/-1!important;grid-row:1!important;justify-self:start!important;margin:0 0 10px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .search3-booking-stepper{display:grid!important;grid-column:1/-1!important;grid-row:4!important;margin:0 0 10px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-picture{grid-column:1!important;grid-row:2!important;width:100%!important;height:178px!important;min-height:178px!important;margin:0!important;border:1px solid var(--at-line)!important;border-right:0!important;border-radius:12px 0 0 12px!important;overflow:hidden!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-picture img{width:100%!important;height:100%!important;object-fit:cover!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-head{grid-column:2!important;grid-row:2!important;align-content:center!important;min-height:178px!important;height:178px!important;margin:0!important;padding:16px 18px!important;border:1px solid var(--at-line)!important;border-left:0!important;border-radius:0 12px 12px 0!important;box-sizing:border-box!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-head h2{font-size:22px!important;line-height:1.08!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-price{font-size:22px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .facts{grid-column:1/-1!important;grid-row:3!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;margin-top:10px!important;border-top:1px solid var(--at-line)!important;border-radius:12px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .facts>div,html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .facts>div:nth-child(n){min-height:0!important;padding:10px 11px!important;border-top:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .hotel-desc{grid-column:1/-1!important;grid-row:8!important;margin-top:10px!important;padding:14px 16px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .room-details-host{grid-column:1/-1!important;grid-row:6!important;width:100%!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .search3-final-sections{grid-column:1/-1!important;grid-row:7!important;width:100%!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .tour-flights{grid-column:1/-1!important;grid-row:5!important;width:100%!important;margin-top:12px!important;padding:14px 16px!important;border-radius:12px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-variants{gap:9px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-choice{padding:9px 11px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .flight-segment{padding:9px 11px!important}'+
'}'+
'@media(min-width:1000px){html body.search3-candidate.search3-has-results .results-tools--ds2{position:relative!important;top:auto!important;z-index:1!important;box-sizing:border-box!important;width:calc(100% - var(--at-page-gutter) - 210px)!important;margin-left:calc(var(--at-page-edge) + 210px)!important;margin-right:var(--at-page-edge)!important;margin-bottom:10px!important}html body.search3-candidate.search3-has-results .results-layout{clear:both!important;position:relative!important;z-index:0!important}}';document.head.appendChild(s)}
injectMobileConvergence();
var bar=document.createElement('div');bar.className='search3-selected-mobile-bar';bar.hidden=true;bar.innerHTML='<div class="search3-selected-mobile-bar__price"><small>Стоимость тура</small><strong data-s3-selected-price>—</strong></div><button type="button" data-s3-selected-lead>Далее: итог тура</button>';
document.body.appendChild(bar);
var syncQueued=false,selectedTotal=0;
function actionButton(){return bar.querySelector('[data-s3-selected-lead]')}
function normalizedTotal(detail){var d=detail||{},tour=d.tour||{};if(d.pricePending)return Number(d.basePrice||tour.price||0);var value=Number(d.price||0);return value>0?value:Number(d.basePrice||tour.price||0);}
function money(value){var amount=Number(value||0);return amount>0?new Intl.NumberFormat('ru-RU').format(amount)+' ₽':'';}
function selectedAmount(source){if(selectedTotal>0)return money(selectedTotal);if(!source)return'';return Array.from(source.childNodes||[]).filter(function(node){return node.nodeType===Node.TEXT_NODE;}).map(function(node){return String(node.textContent||'').trim();}).filter(Boolean).join(' ').replace(/\s+/g,' ').trim();}
function normalizeLeadFields(){if(!window.matchMedia||!window.matchMedia('(max-width:640px)').matches||!selected.classList.contains('search3-lead-entry'))return;var form=selected.querySelector('.lead-form'),fields=form&&form.querySelector('.lead-fields'),name=form&&form.querySelector('input[name="name"]'),phone=form&&form.querySelector('input[name="phone"]');if(!form||!fields||!name||!phone||form.dataset.search3MobileLeadNormalized==='1')return;form.dataset.search3MobileLeadNormalized='1';var nameLabel=name.closest('label'),phoneLabel=phone.closest('label');if(nameLabel){nameLabel.hidden=false;nameLabel.removeAttribute('hidden');nameLabel.style.setProperty('display','grid','important');name.hidden=false;name.removeAttribute('hidden');if(phoneLabel&&nameLabel.nextElementSibling!==phoneLabel)fields.insertBefore(nameLabel,phoneLabel);else if(!phoneLabel&&fields.firstElementChild!==nameLabel)fields.prepend(nameLabel)}if(phoneLabel){phoneLabel.hidden=false;phoneLabel.removeAttribute('hidden');phoneLabel.style.setProperty('display','grid','important')}Array.from(form.querySelectorAll('button,summary')).forEach(function(node){var text=String(node.textContent||'').replace(/\s+/g,' ').trim();if(/^Дополнить заявку/i.test(text)){node.hidden=true;if(node.style.display!=='none')node.style.setProperty('display','none','important')}})}
function sync(){var visible=!selected.hidden&&getComputedStyle(selected).display!=='none'&&selected.children.length>0;document.body.classList.toggle('search3-selected-open',visible);var leadEntry=selected.classList.contains('search3-lead-entry'),finalReview=selected.classList.contains('search3-final-review');bar.hidden=!visible||leadEntry||finalReview;if(!visible)return;if(leadEntry)normalizeLeadFields();var source=selected.querySelector('.selected-price'),label=bar.querySelector('.search3-selected-mobile-bar__price small'),sourceLabel=source&&source.querySelector(':scope > small'),scope=String(sourceLabel&&sourceLabel.textContent||'Стоимость тура').replace(/\s+/g,' ').trim();if(label)label.textContent=scope||'Стоимость тура';var target=bar.querySelector('[data-s3-selected-price]'),amount=selectedAmount(source);if(target)target.textContent=amount||'—';var btn=actionButton();if(!btn)return;btn.textContent='Далее: итог тура';}
function scheduleSync(){if(syncQueued)return;syncQueued=true;setTimeout(function(){syncQueued=false;sync()},0)}
function continueFlow(){
  if(selected.classList.contains('search3-lead-entry'))return;
  if(selected.classList.contains('search3-final-review')){
    if(window.Search3SummaryCta&&typeof window.Search3SummaryCta.enterLead==='function'){window.Search3SummaryCta.enterLead('mobile-bar');return;}
    var summary=selected.querySelector('.search3-summary-submit');if(summary){summary.click();return;}
  }
  var next=selected.querySelector('.search3-flight-continue button');
  if(next){next.click();return;}
  var flights=selected.querySelector('.tour-flights');if(flights)flights.scrollIntoView({behavior:'smooth',block:'start'});
}
document.addEventListener('click',function(e){var btn=e.target&&e.target.closest&&e.target.closest('[data-s3-selected-lead]');if(!btn)return;e.preventDefault();continueFlow();});
window.addEventListener('v2:tour-selected',function(event){selectedTotal=normalizedTotal({tour:event&&event.detail&&event.detail.tour});scheduleSync();});
window.addEventListener('v2:tour-price-updated',function(event){selectedTotal=normalizedTotal(event&&event.detail);scheduleSync();});
['v2:selected-tour-opened','v2:selected-tour-closed','v2:results-rendered','v2:booking-review','search3:lead-entry','v2:lead-started','v2:lead-success','v2:lead-error'].forEach(function(name){window.addEventListener(name,scheduleSync);});
new MutationObserver(scheduleSync).observe(selected,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden','class','style']});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scheduleSync,{once:true});else scheduleSync();
window.Search3SelectedTourMobile={sync,scheduleSync,continueFlow,normalizeLeadFields,normalizedTotal,selectedAmount,version:14};
})();


/* donor:search3-maket7-lock.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function ensureSelectedConfidenceGridLock(){
  if(document.getElementById('search3-selected-confidence-grid-lock'))return;
  var style=document.createElement('style');
  style.id='search3-selected-confidence-grid-lock';
  style.textContent='@media(min-width:1000px){html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-confidence{grid-column:1/3!important;width:100%!important;max-width:none!important;min-width:0!important;box-sizing:border-box!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-confidence>div:first-child{min-width:180px!important}html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review) .selected-confidence-steps{min-width:0!important}}';
  document.head.appendChild(style);
}
function field(form,name){var el=form&&form.elements&&form.elements[name];return el&&el.closest?el.closest('.field'):null;}
function placeRegionInPrimary(form,main){
  var f=field(form,'region'),dates=main&&main.querySelector('.search3-dates');
  if(!f||!main||!dates)return;
  f.classList.add('search3-region');
  var label=f.querySelector(':scope > span');if(label)label.textContent='Курорт / регион';
  if(f.parentNode!==main||f.nextElementSibling!==dates)main.insertBefore(f,dates);
}
function placeAdvanced(form,grid,name,labelText,cls){
  var f=field(form,name);if(!f||!grid)return;
  f.classList.add(cls);
  var label=f.querySelector(':scope > span');if(label)label.textContent=labelText;
  grid.appendChild(f);
}
function syncLabels(form){var dates=form.querySelector('.search3-dates>:scope > span,.search3-dates>span');if(dates)dates.textContent='Дата вылета';}
function desktopLock(){
  ensureSelectedConfidenceGridLock();
  var form=document.getElementById('tourSearch');if(!form)return;
  var main=form.querySelector('.search3-primary-grid');
  var quality=document.getElementById('search3AdvancedSearch')||form.querySelector('.search3-quality');
  var grid=quality&&quality.querySelector('.search3-quality__grid');
  var quick=form.querySelector('.search3-quick');
  var operatorField=field(form,'operator');
  placeRegionInPrimary(form,main);syncLabels(form);
  if(operatorField)operatorField.style.setProperty('display','none','important');
  if(window.innerWidth>760){
    if(quality){quality.hidden=false;quality.style.setProperty('display','block','important');}
    if(grid){
      grid.style.setProperty('display','grid','important');
      placeAdvanced(form,grid,'stars','Категория отеля','search3-stars');
      placeAdvanced(form,grid,'rating','Оценка отеля','search3-rating');
      placeAdvanced(form,grid,'food','Питание','search3-meal');
      placeAdvanced(form,grid,'price_till','Бюджет на тур','search3-budget');
      placeAdvanced(form,grid,'hotel','Конкретный отель','search3-hotel');
    }
    if(quick&&quick.children.length){quick.hidden=false;quick.style.setProperty('display','flex','important');}
    form.classList.add('search3-desktop-two-row');
  }else{
    if(quality)quality.style.removeProperty('display');
    if(grid)grid.style.removeProperty('display');
    if(quick)quick.style.removeProperty('display');
    form.classList.remove('search3-desktop-two-row');
  }
}
function lock(){ensureSelectedConfidenceGridLock();desktopLock();setTimeout(desktopLock,80);setTimeout(desktopLock,180);setTimeout(desktopLock,550);}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',lock,{once:true});else lock();
window.addEventListener('resize',desktopLock);
window.addEventListener('v2:search-reset',lock);
window.addEventListener('v2:results-rendered',desktopLock);
})();


/* donor:search3-lead-flow.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
const cfg=window.V2_CONFIG||{};
function qs(sel,root){return (root||document).querySelector(sel)}
function safeUrl(v){const s=String(v||'').trim();return /^https:\/\//i.test(s)?s:''}
function footerMessenger(rx){const links=Array.from(document.querySelectorAll('.search3-footer-socials a,.ds2-site-footer__socials a'));const hit=links.find(a=>rx.test(String(a.href||'')));return hit?safeUrl(hit.href):''}
function messengerLinks(){const configuredMax=safeUrl(cfg.maxUrl||cfg.maxBotUrl||cfg.maxLink),configuredTelegram=safeUrl(cfg.telegramUrl||cfg.telegramBotUrl||cfg.telegramLink);return{max:{url:configuredMax||footerMessenger(/https:\/\/max\.ru\//i),direct:!!configuredMax},telegram:{url:configuredTelegram||footerMessenger(/https:\/\/(?:t\.me|telegram\.me)\//i),direct:!!configuredTelegram}}}
function ensureStatus(form){let box=form.querySelector('.search3-lead-status');if(!box){box=document.createElement('div');box.className='search3-lead-status';box.hidden=true;form.prepend(box)}return box}
function enterLead(){const root=qs('#selectedTour');if(!root)return;root.classList.add('search3-lead-entry');window.dispatchEvent(new CustomEvent('search3:lead-entry',{detail:{active:true,source:'lifecycle'}}));}
function leaveLead(){const root=qs('#selectedTour');if(!root)return;root.classList.remove('search3-lead-entry');window.dispatchEvent(new CustomEvent('search3:lead-entry',{detail:{active:false,source:'lifecycle'}}));}
function setState(state,detail){const form=qs('#selectedTour .lead-form');if(!form)return false;enterLead();form.dataset.search3LeadState=state;const box=ensureStatus(form);box.hidden=false;const links=messengerLinks();if(state==='sending'){
box.innerHTML='<div class="search3-lead-status__icon search3-lead-status__icon--sending">✈</div><div><h3>Отправляем заявку…</h3><p>Пожалуйста, подождите. Это займёт несколько секунд.</p><ol><li>Сохраняем ваши данные</li><li>Отправляем заявку менеджеру</li><li>Подтверждаем получение</li></ol></div>';
}else if(state==='success'){
const leadId=detail&&detail.leadId?'<p class="search3-lead-id">Заявка № '+String(detail.leadId)+'</p>':'';
const max=links.max.url?'<a class="search3-msg-btn search3-msg-btn--max" href="'+links.max.url+'" target="_blank" rel="noopener noreferrer" aria-label="'+(links.max.direct?'Продолжить общение в MAX':'Открыть AnyTour в MAX')+'">'+(links.max.direct?'Продолжить в MAX':'Открыть MAX')+'</a>':'<span class="search3-msg-btn search3-msg-btn--disabled" aria-disabled="true">MAX недоступен</span>';
const tg=links.telegram.url?'<a class="search3-msg-btn search3-msg-btn--tg" href="'+links.telegram.url+'" target="_blank" rel="noopener noreferrer" aria-label="'+(links.telegram.direct?'Продолжить общение в Telegram':'Открыть AnyTour в Telegram')+'">'+(links.telegram.direct?'Продолжить в Telegram':'Открыть Telegram')+'</a>':'<span class="search3-msg-btn search3-msg-btn--disabled" aria-disabled="true">Telegram недоступен</span>';
box.innerHTML='<div class="search3-lead-status__icon search3-lead-status__icon--success">✓</div><div><h3>Заявка отправлена!</h3><p>Менеджер уже получил информацию о поездке и свяжется с вами в ближайшее время.</p>'+leadId+'<strong>Хотите открыть AnyTour в мессенджере?</strong><div class="search3-messenger-actions">'+max+tg+'<button type="button" class="search3-stay-site">Остаться на сайте</button></div></div>';
}else if(state==='error'){
box.innerHTML='<div class="search3-lead-status__icon search3-lead-status__icon--error">!</div><div><h3>Не удалось отправить заявку</h3><p>Проверьте данные и попробуйте ещё раз.</p><div class="search3-error-actions"><button type="button" class="search3-retry-lead">Повторить отправку</button><button type="button" class="search3-edit-lead">Изменить данные</button></div><p class="search3-error-preserve-note">Ваши данные сохранены. Можно изменить их и отправить заявку снова.</p></div>';
}else{return false}
return true;
}
function clearState(){const form=qs('#selectedTour .lead-form');if(!form)return;delete form.dataset.search3LeadState;const box=form.querySelector('.search3-lead-status');if(box)box.hidden=true}
window.addEventListener('v2:lead-started',e=>setState('sending',e.detail||{}));
window.addEventListener('v2:lead-success',e=>setState('success',e.detail||{}));
window.addEventListener('v2:lead-error',e=>setState('error',e.detail||{}));
/* Preview-only state injection for visual QA. It deliberately does not reuse
   the real lead lifecycle events, so legacy handlers cannot create backend-like
   side effects or mutate the form before the Search3 state is asserted. */
document.addEventListener('click',e=>{const form=qs('#selectedTour .lead-form');if(!form)return;if(e.target.closest('.search3-stay-site')){clearState();leaveLead();const summary=qs('#selectedTour .search3-booking-summary');if(summary)summary.scrollIntoView({behavior:'smooth',block:'start'})}if(e.target.closest('.search3-edit-lead')){clearState();enterLead();const first=form.querySelector('input,textarea,select');if(first)first.focus()}if(e.target.closest('.search3-retry-lead')){clearState();enterLead();const btn=form.querySelector('button[type="submit"]');if(btn)btn.click()}});
window.Search3LeadFlow={setState,clearState,enterLead,leaveLead,version:3};
})();


/* donor:search3-booking-summary.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
let lastTour=null,lastFlight=null,selectedTotal=0;
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function number(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=number(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function text(v){if(v==null)return'';if(typeof v==='string'||typeof v==='number')return String(v);if(Array.isArray(v))return v.map(text).filter(Boolean).join(', ');for(const k of ['russianName','fullRussianName','name','title','value','text']){const s=text(v[k]);if(s)return s;}return'';}
function meal(t){return text(t&&t.meal)||'—';}
function operator(t){return text(t&&t.operator)||'—';}
function people(t){const p=[];if(t&&t.adults)p.push(t.adults+' взр.');if(t&&t.childs)p.push(t.childs+' дет.');return p.join(' + ')||'—';}
function place(t){const h=t&&t.hotel||{};return [text(h.country),text(h.region),text(h.subRegion)].filter(Boolean).join(', ')||'—';}
function flightLabel(v){if(!v)return'Выберите рейс';const fw=Array.isArray(v.forward)?v.forward:[],bw=Array.isArray(v.backward)?v.backward:[];const names=[];fw.concat(bw).forEach(f=>{const n=[text(f&&f.company),text(f&&f.number)].filter(Boolean).join(' ');if(n&&!names.includes(n))names.push(n);});return names.join(' · ')||'Рейс выбран';}
function flightMoneyHtml(v){if(!v)return'';const price=number(v.price),fuel=number(v.fuelCharge),rows=[];if(price>0)rows.push('<div><span>Цена варианта рейса</span><b>'+money(price)+'</b></div>');if(fuel>0)rows.push('<div><span>Топливный сбор рейса</span><b>'+money(fuel)+'</b></div>');return rows.length?'<div class="search3-booking-summary__flight-costs">'+rows.join('')+'</div>':'';}
function normalizedTotal(detail){const d=detail||{},tour=d.tour||lastTour||{};if(d.pricePending)return number(d.basePrice)||number(tour.price);return number(d.price)||number(d.basePrice)||number(tour.price);}
function summaryHtml(t){const h=t&&t.hotel||{};const pic=t&&t.picture||h.picturelink||'';return '<aside class="search3-booking-summary" aria-label="Ваш тур">'+
'<div class="search3-booking-summary__title">Ваш тур</div>'+
(pic?'<img class="search3-booking-summary__image" src="'+esc(pic)+'" alt="">':'')+
'<strong class="search3-booking-summary__hotel">'+esc(text(h.name)||text(t&&t.name)||'Выбранный тур')+'</strong>'+
'<div class="search3-booking-summary__place">'+esc(place(t))+'</div>'+
'<dl><div><dt>Дата</dt><dd>'+esc(text(t&&t.date)||'—')+'</dd></div><div><dt>Ночей</dt><dd>'+esc(text(t&&t.nights)||'—')+'</dd></div><div><dt>Туристы</dt><dd>'+esc(people(t))+'</dd></div><div><dt>Номер</dt><dd>'+esc(text(t&&t.roomType)||'—')+'</dd></div><div><dt>Питание</dt><dd>'+esc(meal(t))+'</dd></div><div><dt>Оператор</dt><dd>'+esc(operator(t))+'</dd></div><div><dt>Перелёт</dt><dd class="search3-booking-summary__flight">'+esc(flightLabel(lastFlight))+'</dd></div></dl>'+
flightMoneyHtml(lastFlight)+'<div class="search3-booking-summary__total"><span>Стоимость тура</span><strong>'+money(selectedTotal||t&&t.price)+'</strong></div><p class="search3-booking-summary__price-note">Стоимость тура показана отдельно от параметров выбранного варианта рейса.</p></aside>';}
function clearLayout(shell,form,summary){['display','grid-column','grid-template-columns','gap','align-items'].forEach(p=>shell.style.removeProperty(p));['grid-column','grid-row'].forEach(p=>form.style.removeProperty(p));['display','grid-column','grid-row'].forEach(p=>summary.style.removeProperty(p));}
function syncLayout(){
  const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form'),shell=form&&form.closest('.search3-lead-shell'),summary=shell&&shell.querySelector('.search3-booking-summary');
  if(!root||!form||!shell||!summary)return;
  const desktop=window.matchMedia('(min-width:1000px)').matches,finalReview=root.classList.contains('search3-final-review'),leadEntry=root.classList.contains('search3-lead-entry');
  if(finalReview&&!leadEntry)root.dataset.search3FinalLayout='maket7';else delete root.dataset.search3FinalLayout;
  const title=summary.querySelector('.search3-booking-summary__title');if(title)title.textContent=finalReview&&!leadEntry?'Итоговая стоимость':'Ваш тур';
  clearLayout(shell,form,summary);
  if(desktop&&finalReview&&leadEntry){
    shell.style.setProperty('display','grid','important');
    shell.style.setProperty('grid-column','1 / -1','important');
    shell.style.setProperty('grid-template-columns','minmax(0,1fr) 320px','important');
    shell.style.setProperty('gap','18px','important');
    shell.style.setProperty('align-items','start','important');
    form.style.setProperty('grid-column','1','important');
    form.style.setProperty('grid-row','1','important');
    summary.style.setProperty('display','block','important');
    summary.style.setProperty('grid-column','2','important');
    summary.style.setProperty('grid-row','1','important');
  }else if(desktop&&finalReview){
    /* Maket7 final review uses a compact hotel card on the left and cost-only rail on the right. */
    shell.style.setProperty('display','contents','important');
    form.style.setProperty('grid-column','1 / 3','important');
    summary.style.setProperty('display','block','important');
    summary.style.setProperty('grid-column','3','important');
    summary.style.setProperty('grid-row','4 / 12','important');
  }
}
function render(){const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form');if(!form||!lastTour)return;let shell=form.closest('.search3-lead-shell');if(!shell){shell=document.createElement('div');shell.className='search3-lead-shell';form.parentNode.insertBefore(shell,form);shell.appendChild(form);}const old=shell.querySelector('.search3-booking-summary');if(old)old.remove();shell.insertAdjacentHTML('beforeend',summaryHtml(lastTour));syncLayout();}
function renderSoon(){setTimeout(render,0)}
function layoutSoon(){setTimeout(syncLayout,0)}
window.addEventListener('v2:tour-selected',e=>{lastTour=e.detail&&e.detail.tour||null;lastFlight=null;selectedTotal=number(lastTour&&lastTour.price);renderSoon();});
window.addEventListener('v2:flight-selected',e=>{lastFlight=e.detail&&e.detail.flight||null;renderSoon();});
window.addEventListener('v2:tour-price-updated',e=>{selectedTotal=normalizedTotal(e.detail);renderSoon();});
window.addEventListener('v2:booking-review',layoutSoon);
window.addEventListener('search3:lead-entry',layoutSoon);
window.addEventListener('v2:lead-started',layoutSoon);
window.addEventListener('v2:lead-error',layoutSoon);
window.addEventListener('v2:lead-success',renderSoon);
window.addEventListener('resize',layoutSoon);
document.addEventListener('click',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button'))layoutSoon();});
window.Search3BookingSummary={render,syncLayout,normalizedTotal,version:5};
})();

/* donor:search3-booking-stepper.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function nodeText(n){return String(n&&n.textContent||'').replace(/\s+/g,' ').trim()}
function removeLegacy(r){
  if(!r)return;
  r.querySelectorAll('.checkout-journey,.checkout-facts-heading,.selected-tour-progress').forEach(n=>n.remove());
  /* A pre-Search3 three-stage strip can be injected without a stable class by an
     older checkout layer. Remove only the smallest scoped legacy vocabulary and
     never touch the authoritative five-step Search3 stepper. */
  const labels=['Тур выбран','Выбор рейса','Заявка менеджеру'];
  const matches=[...r.querySelectorAll('div,nav,ol,ul')].filter(n=>{
    if(n.classList&&n.classList.contains('search3-booking-stepper'))return false;
    const s=nodeText(n);
    return s.length>0&&s.length<=80&&labels.every(label=>s.includes(label));
  });
  const outer=matches.find(n=>!matches.some(other=>other!==n&&other.contains(n)));
  if(outer)outer.remove();
}
function ensure(){const r=root();if(!r||r.hidden)return null;removeLegacy(r);let s=r.querySelector('.search3-booking-stepper');if(s)return s;s=document.createElement('nav');s.className='search3-booking-stepper';s.setAttribute('aria-label','Этапы оформления тура');s.innerHTML='<button type="button" class="search3-booking-step is-active" data-step="flight" aria-current="step"><span>1</span><b>Рейс</b></button><button type="button" class="search3-booking-step" data-step="review"><span>2</span><b>Итог тура</b></button><button type="button" class="search3-booking-step" data-step="lead"><span>3</span><b>Заявка</b></button>';const back=r.querySelector('.back-results');if(back&&back.nextSibling)r.insertBefore(s,back.nextSibling);else r.prepend(s);return s}
function set(step){const s=ensure();if(!s)return;const order=['flight','review','lead'];const ix=Math.max(0,order.indexOf(step));s.querySelectorAll('.search3-booking-step').forEach((n,i)=>{n.classList.toggle('is-active',i===ix);n.classList.toggle('is-done',i<ix);if(i===ix)n.setAttribute('aria-current','step');else n.removeAttribute('aria-current')});}
function target(step){const r=root();if(!r)return null;if(step==='review')return r.querySelector('.search3-booking-summary,.search3-lead-shell')||r;if(step==='lead')return r.querySelector('.lead-form')||r;return r.querySelector('.tour-flights')||r}
window.addEventListener('v2:tour-selected',()=>set('flight'));
window.addEventListener('v2:flight-selected',()=>set('flight'));
window.addEventListener('v2:booking-review',()=>set('review'));
window.addEventListener('search3:lead-entry',e=>set(e.detail&&e.detail.active===false?'review':'lead'));
window.addEventListener('v2:lead-started',()=>set('lead'));
window.addEventListener('v2:lead-success',()=>set('lead'));
window.addEventListener('v2:lead-error',()=>set('lead'));
document.addEventListener('focusin',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .lead-form'))set('lead')});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-booking-step');if(!b)return;const t=target(b.dataset.step);if(t){set(b.dataset.step);t.scrollIntoView({behavior:'smooth',block:'start'})}});
window.Search3BookingStepper={ensure,set,removeLegacy,version:5};
})();


/* donor:search3-final-sections.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
let tour=null,flight=null;
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function text(v){if(v==null)return'';if(typeof v==='string'||typeof v==='number')return String(v);if(Array.isArray(v))return v.map(text).filter(Boolean).join(', ');for(const k of ['russianName','fullRussianName','name','title','value','text']){const s=text(v[k]);if(s)return s;}return'';}
function number(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=number(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function baggage(v){if(!v)return'';const legs=[].concat(Array.isArray(v.forward)?v.forward:[],Array.isArray(v.backward)?v.backward:[]);const vals=[];legs.forEach(f=>{if(!f)return;const b=f.baggage, c=f.carryOn;const s=[];if(b!==null&&b!==undefined&&b!=='')s.push('багаж '+b+' кг');if(c!==null&&c!==undefined&&c!=='')s.push('ручная кладь '+c);const line=s.join(' · ');if(line&&!vals.includes(line))vals.push(line)});return vals.join('; ');}
function people(t){const a=Number(t&&t.adults||0),c=Number(t&&t.childs||0),format=window.Search3CandidateResultsV1;if(format&&typeof format.partyLabel==='function')return format.partyLabel(a,c);const p=[];if(a)p.push(a+' взрослых');if(c)p.push(c+' '+(c===1?'ребёнок':c>=2&&c<=4?'ребёнка':'детей'));return p.join(' · ')||'—';}
function render(){const root=document.getElementById('selectedTour');if(!root||!tour)return;let box=root.querySelector('.search3-final-sections');if(box)box.remove();box=document.createElement('div');box.className='search3-final-sections';const meal=text(tour.meal)||'—',room=text(tour.roomType)||'—',placement=text(tour.placement)||'—',operator=text(tour.operator)||'—',fuel=number(tour.fuelCharge),flightFuel=number(flight&&flight.fuelCharge),bag=baggage(flight);const service=[];service.push('<article><span>Питание</span><strong>'+esc(meal)+'</strong></article>');service.push('<article><span>Номер</span><strong>'+esc(room)+'</strong><small>'+esc(placement)+'</small></article>');if(fuel>0)service.push('<article><span>Топливный сбор тура</span><strong>'+money(fuel)+'</strong></article>');if(flightFuel>0)service.push('<article><span>Топливный сбор рейса</span><strong>'+money(flightFuel)+'</strong></article>');if(bag)service.push('<article><span>Багаж</span><strong>'+esc(bag)+'</strong></article>');service.push('<article><span>Туроператор</span><strong>'+esc(operator)+'</strong></article>');box.innerHTML='<section class="search3-final-section"><div class="search3-final-section__heading"><strong>Услуги и условия</strong><span>Только данные выбранного тура</span></div><div class="search3-final-services">'+service.join('')+'</div></section><section class="search3-final-section"><div class="search3-final-section__heading"><strong>Туристы</strong><span>Состав размещения у туроператора</span></div><div class="search3-final-tourists"><span>Для выбранного варианта</span><strong>'+esc(people(tour))+'</strong></div></section>';const lead=root.querySelector('.search3-lead-shell,.lead-form');if(lead&&lead.parentNode)lead.parentNode.insertBefore(box,lead);else root.appendChild(box);}
window.addEventListener('v2:tour-selected',e=>{tour=e.detail&&e.detail.tour||null;flight=null;setTimeout(render,0)});
window.addEventListener('v2:flight-selected',e=>{flight=e.detail&&e.detail.flight||null;setTimeout(render,0)});
})();


/* donor:search3-flight-continue.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function syncHeading(r){if(!r)return;const strong=r.querySelector('.tour-flights .section-heading strong'),hint=r.querySelector('.tour-flights .section-heading span'),review=r.classList.contains('search3-final-review');if(strong)strong.textContent=review?'Рейсы':'Выберите рейс';if(hint)hint.textContent=review?'Выбранный перелёт':'Сравните время, багаж и доплату — цена тура обновится автоматически';}
function markDirections(r){if(!r)return;r.querySelectorAll('.tour-flights .flight-variant').forEach(variant=>{let outbound=0,backward=0;variant.querySelectorAll('.flight-segment').forEach(segment=>{const strong=segment.querySelector('.flight-segment-title strong'),title=String(strong&&strong.textContent||'').trim();segment.classList.remove('is-outbound','is-return');if(/^(Туда|Пересадка туда)$/.test(title)){segment.classList.add('is-outbound');outbound+=1}else if(/^(Обратно|Пересадка обратно)$/.test(title)){segment.classList.add('is-return');backward+=1}});variant.classList.toggle('has-roundtrip-pair',outbound>0&&backward>0);variant.classList.toggle('has-simple-roundtrip',outbound===1&&backward===1);});}
function ensure(){const r=root(),box=r&&r.querySelector('.tour-flights');if(!box)return;let action=box.querySelector('.search3-flight-continue');if(!action){action=document.createElement('div');action.className='search3-flight-continue';action.innerHTML='<button type="button" class="primary">Далее: итог тура</button>';box.appendChild(action)}action.hidden=false;action.querySelector('button').textContent=r.classList.contains('search3-final-review')?'Изменить рейс':'Далее: итог тура';syncHeading(r);markDirections(r);}
function enterReview(){const r=root();if(!r)return;r.classList.add('search3-final-review');ensure();window.dispatchEvent(new CustomEvent('v2:booking-review',{detail:{}}));const target=r.querySelector('.search3-final-sections,.search3-lead-shell,.lead-form');if(target)target.scrollIntoView({behavior:'smooth',block:'start'});}
function exitReview(){const r=root();if(!r)return;r.classList.remove('search3-final-review');ensure();const flights=r.querySelector('.tour-flights');if(flights)flights.scrollIntoView({behavior:'smooth',block:'start'});}
window.addEventListener('v2:tour-selected',()=>{const r=root();if(r){r.classList.remove('search3-final-review');setTimeout(()=>{syncHeading(r);markDirections(r)},0)}});
window.addEventListener('v2:flight-selected',()=>setTimeout(ensure,0));
window.addEventListener('v2:booking-review',()=>setTimeout(()=>{syncHeading(root());markDirections(root())},0));
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button');if(!b)return;e.preventDefault();const r=root();if(r&&r.classList.contains('search3-final-review'))exitReview();else enterReview();});
})();

/* donor:search3-review-heading.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function ensure(){const r=root();if(!r)return null;let h=r.querySelector('.search3-review-heading');if(h)return h;h=document.createElement('header');h.className='search3-review-heading';h.hidden=true;h.innerHTML='<div><span>ФИНАЛЬНАЯ ПРОВЕРКА</span><h2>Ваш тур</h2><p>Проверьте детали поездки и отправьте заявку.</p></div><b aria-hidden="true">✓</b>';const step=r.querySelector('.search3-booking-stepper');if(step&&step.nextSibling)r.insertBefore(h,step.nextSibling);else r.prepend(h);return h}
function sync(){const r=root(),h=ensure();if(!r||!h)return;h.hidden=!r.classList.contains('search3-final-review')}
window.addEventListener('v2:tour-selected',()=>setTimeout(sync,0));
window.addEventListener('v2:booking-review',()=>setTimeout(sync,0));
document.addEventListener('click',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button'))setTimeout(sync,0)});
})();


/* donor:search3-summary-cta.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
let relayoutTimer=0;
function root(){return document.getElementById('selectedTour')}
function relayout(){if(window.Search3BookingSummary&&typeof window.Search3BookingSummary.syncLayout==='function')window.Search3BookingSummary.syncLayout()}
function relayoutSoon(){if(relayoutTimer)clearTimeout(relayoutTimer);relayoutTimer=setTimeout(function(){relayoutTimer=0;relayout()},0)}
function afterPaint(fn){if(typeof requestAnimationFrame==='function')requestAnimationFrame(function(){setTimeout(fn,0)});else setTimeout(fn,0)}
function emitLeadEntry(active,source){setTimeout(function(){window.dispatchEvent(new CustomEvent('search3:lead-entry',{detail:{active:!!active,source:source||'summary'}}))},0)}
function injectMobileFinalReviewStyle(){if(document.getElementById('search3-mobile-final-review-v2'))return;const s=document.createElement('style');s.id='search3-mobile-final-review-v2';s.textContent='@media(max-width:640px){'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry){display:grid!important;grid-template-columns:108px minmax(0,1fr)!important;column-gap:10px!important;row-gap:10px!important;align-items:stretch!important;max-width:none!important;margin:10px var(--at-page-edge) 36px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .back-results{display:inline-flex!important;grid-column:1/-1!important;grid-row:1!important;margin:0 0 2px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-stepper{display:flex!important;grid-column:1/-1!important;grid-row:2!important;width:100%!important;margin:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-review-heading{display:block!important;grid-column:1/-1!important;grid-row:3!important;width:100%!important;margin:0!important;padding:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-review-heading h2{font-size:22px!important;line-height:1.1!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-picture{display:block!important;grid-column:1!important;grid-row:4!important;width:108px!important;height:104px!important;min-height:104px!important;margin:0!important;border-radius:12px!important;overflow:hidden!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-picture img{width:100%!important;height:100%!important;object-fit:cover!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-head{display:block!important;grid-column:2!important;grid-row:4!important;min-width:0!important;min-height:104px!important;margin:0!important;padding:11px!important;border:1px solid var(--at-line)!important;border-radius:12px!important;background:#fff!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-head h2{margin:0!important;font-size:15px!important;line-height:1.12!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-head .selected-price,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-head .selected-lead-cta,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-head .selected-lead-cta-note{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .facts,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .facts-secondary-toggle,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-choice-summary,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .hotel-desc,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .hotel-desc-toggle,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .room-details-host,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .selected-confidence,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-tour-detail-rail{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .tour-flights{display:block!important;grid-column:1/-1!important;grid-row:5!important;width:100%!important;margin:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-final-sections{display:grid!important;grid-column:1/-1!important;grid-row:6!important;width:100%!important;margin:0!important;gap:10px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-lead-shell{display:block!important;grid-column:1/-1!important;grid-row:7!important;width:100%!important;margin:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-lead-shell>.lead-form{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary{display:block!important;width:100%!important;max-width:none!important;position:static!important;margin:0!important;padding:14px!important;box-sizing:border-box!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__image,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__hotel,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__place,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary dl,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__flight-costs,html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__price-note{display:none!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__title{margin:0 0 8px!important;font-size:14px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__total{margin:0!important;padding:0 0 10px!important;border:0!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-booking-summary__total strong{font-size:24px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-summary-actions{display:block!important;margin-top:8px!important}'+
'html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry) .search3-summary-submit{width:100%!important;min-height:48px!important}'+
'}';document.head.appendChild(s)}
injectMobileFinalReviewStyle();
function ensureBack(r,entry){if(!r)return null;let back=r.querySelector('.search3-lead-back');if(!back){back=document.createElement('button');back.type='button';back.className='search3-lead-back';back.textContent='← Вернуться к туру';const shell=r.querySelector('.search3-lead-shell');if(shell)r.insertBefore(back,shell);else r.appendChild(back)}back.hidden=!entry;return back}
function isolateRootChildren(r,entry){if(!r)return;const keep=new Set(['search3-lead-back','search3-lead-shell']);Array.from(r.children).forEach(node=>{const allowed=Array.from(keep).some(cls=>node.classList&&node.classList.contains(cls));if(entry&&!allowed){if(node.dataset.search3LeadIsolated!=='1'){node.dataset.search3LeadIsolated='1';node.style.setProperty('display','none','important')}}else if(!entry&&node.dataset.search3LeadIsolated==='1'){delete node.dataset.search3LeadIsolated;node.style.removeProperty('display')}})}
function syncLeadVisibility(r,form){const review=r&&r.classList.contains('search3-final-review'),entry=r&&r.classList.contains('search3-lead-entry');ensureBack(r,entry);isolateRootChildren(r,entry);if(review&&!entry)form.style.setProperty('display','none','important');else form.style.removeProperty('display');relayoutSoon()}
function ensure(){const r=root(),summary=r&&r.querySelector('.search3-booking-summary'),form=r&&r.querySelector('.lead-form');if(!summary||!form)return;let actions=summary.querySelector('.search3-summary-actions');if(!actions){actions=document.createElement('div');actions.className='search3-summary-actions';actions.innerHTML='<button type="button" class="search3-summary-submit">Перейти к заявке</button><p>Перед отправкой проверьте выбранный тур и рейс.</p>';summary.appendChild(actions)}const active=r.classList.contains('search3-final-review'),entry=r.classList.contains('search3-lead-entry');form.classList.toggle('search3-has-summary-submit',active&&!entry);syncLeadVisibility(r,form);const sent=form.dataset.sent==='1';const sending=form.dataset.search3LeadState==='sending';const button=actions.querySelector('.search3-summary-submit');if(button){button.disabled=sent||sending;button.textContent=sent?'Заявка отправлена':sending?'Отправляем…':'Перейти к заявке'}actions.hidden=!active||entry;}
function enterLead(source){const r=root(),form=r&&r.querySelector('.lead-form');if(!r||!form)return false;r.classList.add('search3-lead-entry');ensure();emitLeadEntry(true,source||'summary');afterPaint(function(){relayoutSoon();try{form.scrollIntoView({behavior:'smooth',block:'start'})}catch(e){try{form.scrollIntoView()}catch(_){}}const phone=form.querySelector('input[name="phone"]');if(phone){try{phone.focus({preventScroll:true})}catch(e){try{phone.focus()}catch(_){}}}});return true}
function leaveLead(){const r=root();if(!r)return;r.classList.remove('search3-lead-entry');isolateRootChildren(r,false);ensure();emitLeadEntry(false,'back');afterPaint(function(){relayoutSoon();const summary=r.querySelector('.search3-booking-summary');if(summary){try{summary.scrollIntoView({behavior:'smooth',block:'start'})}catch(e){try{summary.scrollIntoView()}catch(_){}}}const button=summary&&summary.querySelector('.search3-summary-submit');if(button){try{button.focus({preventScroll:true})}catch(e){try{button.focus()}catch(_){}}}})}
window.addEventListener('v2:booking-review',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-started',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-success',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-error',()=>setTimeout(ensure,0));
window.addEventListener('search3:lead-entry',()=>setTimeout(ensure,0));
window.addEventListener('v2:tour-selected',()=>{const r=root();if(r){r.classList.remove('search3-lead-entry');isolateRootChildren(r,false)}setTimeout(ensure,0)});
document.addEventListener('click',e=>{const summaryButton=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-summary-submit');if(summaryButton){e.preventDefault();enterLead('summary');return}const back=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-lead-back');if(back){e.preventDefault();leaveLead();}});
window.Search3SummaryCta={ensure,enterLead,leaveLead,isolateRootChildren,version:8};
})();


/* donor:search3-lead-note.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function ensure(){const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form');if(!form)return;const heading=form.querySelector('.section-heading');if(heading){const title=heading.querySelector('strong'),sub=heading.querySelector('span');if(title)title.textContent='Оставьте заявку';if(sub)sub.textContent='Мы отправим выбранный тур менеджеру. Он свяжется с вами в ближайшее время.';}let note=form.querySelector('.search3-lead-protection');if(!note){note=document.createElement('p');note.className='search3-lead-protection';note.innerHTML='<span aria-hidden="true">♢</span> Проверьте контактные данные перед отправкой заявки.';form.appendChild(note);}const comment=form.querySelector('textarea[name="comment"]');if(comment&&comment.closest('label'))comment.closest('label').classList.add('search3-lead-comment');}
window.addEventListener('v2:tour-selected',()=>setTimeout(ensure,0));window.addEventListener('v2:booking-review',()=>setTimeout(ensure,0));})();


/* donor:search3-tour-detail-rail.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
let tour=null,flight=null,selectedTotal=0;
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function txt(v){if(v==null)return'';if(typeof v==='string'||typeof v==='number')return String(v);if(Array.isArray(v))return v.map(txt).filter(Boolean).join(', ');for(const k of ['russianName','fullRussianName','name','title','value','text']){const s=txt(v[k]);if(s)return s;}return'';}
function num(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=num(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function people(t){const a=[];if(t&&t.adults)a.push(t.adults+' взр.');if(t&&t.childs)a.push(t.childs+' дет.');return a.join(' + ')||'—';}
function hotel(t){return t&&t.hotel||{};}
function place(t){const h=hotel(t);return [txt(h.country),txt(h.region),txt(h.subRegion)].filter(Boolean).join(', ')||'—';}
function meal(t){return txt(t&&t.meal)||'—';}
function flightName(v){if(!v)return'Выбирается';const rows=[].concat(Array.isArray(v.forward)?v.forward:[],Array.isArray(v.backward)?v.backward:[]),names=[];rows.forEach(x=>{const s=[txt(x&&x.company),txt(x&&x.number)].filter(Boolean).join(' ');if(s&&!names.includes(s))names.push(s)});return names.join(' · ')||'Выбран';}
function normalizedTotal(detail){const value=detail||{},source=value.tour||tour||{};if(value.pricePending)return num(value.basePrice)||num(source.price);return num(value.price)||num(value.basePrice)||num(source.price);}
function html(t){const h=hotel(t),fuel=num(t&&t.fuelCharge);return '<aside class="search3-tour-detail-rail" aria-label="Состав тура"><h3>Состав тура</h3><dl><div><dt>Отель</dt><dd>'+esc(txt(h.name)||txt(t&&t.name)||'—')+'</dd></div><div><dt>Направление</dt><dd>'+esc(place(t))+'</dd></div><div><dt>Номер</dt><dd>'+esc(txt(t&&t.roomType)||'—')+'</dd></div><div><dt>Питание</dt><dd>'+esc(meal(t))+'</dd></div><div><dt>Туристы</dt><dd>'+esc(people(t))+'</dd></div><div><dt>Дата</dt><dd>'+esc(txt(t&&t.date)||'—')+(t&&t.nights?' · '+esc(t.nights)+' ноч.':'')+'</dd></div><div><dt>Перелёт</dt><dd>'+esc(flightName(flight))+'</dd></div></dl><div class="search3-tour-detail-rail__price"><span>Стоимость тура</span><strong>'+money(selectedTotal||t&&t.price)+'</strong>'+(fuel?'<small>Топливный сбор: '+money(fuel)+'</small>':'')+'</div><button type="button" class="search3-tour-detail-rail__continue">Далее: итог тура</button></aside>';}
function render(){const root=document.getElementById('selectedTour');if(!root||root.hidden||!tour)return;const old=root.querySelector('.search3-tour-detail-rail');if(old)old.remove();root.insertAdjacentHTML('beforeend',html(tour));}
window.addEventListener('v2:tour-selected',e=>{tour=e.detail&&e.detail.tour||null;flight=null;selectedTotal=num(tour&&tour.price);setTimeout(render,0)});
window.addEventListener('v2:flight-selected',e=>{flight=e.detail&&e.detail.flight||null;setTimeout(render,0)});
window.addEventListener('v2:tour-price-updated',e=>{selectedTotal=normalizedTotal(e.detail);setTimeout(render,0)});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-tour-detail-rail__continue');if(!b)return;const root=document.getElementById('selectedTour'),target=root&&root.querySelector('.search3-flight-continue button');if(target)target.click();});
})();


/* donor:search3-footer-preview.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
/* Donor CSS is bundled into the isolated candidate asset. */
var footer=document.querySelector('.ds2-site-footer');if(!footer||footer.dataset.search3Footer==='1')return;footer.dataset.search3Footer='1';
footer.style.setProperty('background','#0b1324','important');footer.style.setProperty('background-color','#0b1324','important');footer.style.setProperty('color','#fff','important');
function esc(v){return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function href(selector){var a=document.querySelector(selector);return a&&a.href?a.href:'';}
function headerLink(pattern){var list=Array.from(document.querySelectorAll('.at-global-header__nav a'));var a=list.find(function(x){return pattern.test(String(x.textContent||''));});return a&&a.href?a.href:'';}
var logo=footer.querySelector('.ds2-site-footer__logo'),logoImage=logo&&logo.querySelector('img'),logoHref=logo&&logo.href?logo.href:'/',logoSrc=logoImage&&logoImage.getAttribute('src')?logoImage.getAttribute('src'):'/images/logo.svg',logoAlt=logoImage&&logoImage.alt?logoImage.alt:'AnyTour';
var phone=document.querySelector('.at-global-header__phone'),phoneText=phone?String(phone.textContent||'').trim():'',phoneHref=phone&&phone.href?phone.href:'';
var socials={max:href('.ds2-site-footer__socials a:nth-child(1)'),tg:href('.ds2-site-footer__socials a:nth-child(2)'),vk:href('.ds2-site-footer__socials a:nth-child(3)')};
var apps={ios:href('.ds2-site-footer__apps>a:nth-of-type(1)'),android:href('.ds2-site-footer__apps>a:nth-of-type(2)')};
var legal=Array.from(document.querySelectorAll('.ds2-site-footer__legal a')).map(function(a){return{href:a.href,text:String(a.textContent||'').trim()};});
function legalHref(rx){var x=legal.find(function(a){return rx.test(a.text);});return x?x.href:'';}
function link(hrefValue,label){return hrefValue?'<a href="'+esc(hrefValue)+'">'+esc(label)+'</a>':'';}
function externalLink(hrefValue,label,icon){return hrefValue?'<a href="'+esc(hrefValue)+'" target="_blank" rel="noopener noreferrer"><b>'+esc(icon)+'</b><span>'+esc(label)+'</span></a>':'';}
function appLink(hrefValue,label,icon){return hrefValue?'<a href="'+esc(hrefValue)+'" target="_blank" rel="noopener noreferrer">'+esc(icon)+' <b>'+esc(label)+'</b></a>':'';}
function group(title,links,extraClass){var items=links.filter(Boolean);return items.length?'<details class="search3-footer-group'+(extraClass?' '+extraClass:'')+'" open><summary>'+esc(title)+'</summary><div>'+items.join('')+'</div></details>':'';}
var tours=[link(headerLink(/^Поиск туров/i),'Поиск туров'),link(headerLink(/Горящие туры/i),'Горящие туры'),link(headerLink(/^Страны/i),'Страны')];
var useful=[link(headerLink(/Как купить/i),'Как это работает'),link(legalHref(/Политика конфиденциальности/i),'Политика конфиденциальности')];
var company=[link(headerLink(/Контакты/i),'Контакты')];
var phoneLink=link(phoneHref,phoneText),mobileSupport=[phoneLink,''];
var socialLinks=[externalLink(socials.max,'MAX','◎'),externalLink(socials.tg,'Telegram','➤'),externalLink(socials.vk,'VK','VK')].filter(Boolean).join('');
var appLinks=[appLink(apps.ios,'App Store',''),appLink(apps.android,'Google Play','▶')].filter(Boolean).join('');
footer.innerHTML='<div class="search3-footer-main"><div class="search3-footer-brand"><a class="search3-footer-logo" href="'+esc(logoHref)+'"><img src="'+esc(logoSrc)+'" alt="'+esc(logoAlt)+'"></a><strong>AnyTour всегда рядом</strong><p>Каналы AnyTour — для идей и выгодных предложений. Приложение — чтобы искать туры с телефона.</p><div class="search3-footer-socials">'+socialLinks+'</div></div><div class="search3-footer-nav">'+group('Туры',tours)+group('Полезная информация',useful)+group('О компании',company)+group('Поддержка',mobileSupport,'search3-footer-support-mobile')+'</div><div class="search3-footer-support search3-footer-support-desktop"><strong>Поддержка</strong><span>Свяжитесь с нами</span>'+phoneLink+'</div></div><div class="search3-footer-benefits"><div class="search3-footer-apps"><strong>Мобильные приложения</strong><span>Установите и ищите туры ещё удобнее</span><div>'+appLinks+'</div></div><div class="search3-footer-benefit"><div><strong>Актуальные предложения</strong><span>Сравнивайте доступные варианты и условия тура.</span></div></div><div class="search3-footer-benefit"><div><strong>Проверка конкретного тура</strong><span>Проверьте детали рейса, багажа и размещения.</span></div></div><div class="search3-footer-benefit"><div><strong>Цена до заявки</strong><span>Стоимость выбранного варианта видна до передачи контактов.</span></div></div><div class="search3-footer-benefit"><div><strong>Менеджер рядом</strong><span>Помощь с учётом параметров поиска и выбранного предложения.</span></div></div></div><div class="search3-footer-bottom"><span>© 2026 AnyTour — Все права защищены</span></div>';
function syncGroups(){var mobile=matchMedia('(max-width:640px)').matches;footer.querySelectorAll('.search3-footer-group').forEach(function(d){d.open=!mobile;});var mobileSupportEl=footer.querySelector('.search3-footer-support-mobile');var desktopSupportEl=footer.querySelector('.search3-footer-support-desktop');if(mobileSupportEl)mobileSupportEl.style.setProperty('display',mobile?'block':'none','important');if(desktopSupportEl)desktopSupportEl.style.setProperty('display',mobile?'none':'grid','important');}
syncGroups();window.addEventListener('resize',syncGroups);
})();


/* Candidate-owned result and responsive safety layer. */
(function () {
  'use strict';

  if (window.Search3CandidateResultsV1) return;

  var body = document.body;
  var results = document.getElementById('results');
  var tools = document.getElementById('resultsTools');
  var sort = document.getElementById('sortResults');
  if (!body || !body.classList.contains('search3-candidate') || !results || !tools) return;

  var hotelsById = new Map();

  function safe(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function textValue(value) {
    if (value == null) return '';
    if (typeof value === 'object') {
      return textValue(value.russianName || value.fullRussianName || value.name || value.title || '');
    }
    return String(value).trim();
  }

  function hotelId(hotel) {
    return String(hotel && hotel.id != null ? hotel.id : '');
  }

  function representativeTour(hotel) {
    var tours = hotel && Array.isArray(hotel.tours) ? hotel.tours : [];
    if (!tours.length) return null;
    return tours.slice().sort(function (a, b) {
      var left = Number(a && a.price || 0) || Number.MAX_SAFE_INTEGER;
      var right = Number(b && b.price || 0) || Number.MAX_SAFE_INTEGER;
      return left - right;
    })[0] || null;
  }

  function tourWord(count) {
    var n = Math.abs(Number(count) || 0);
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return 'тур';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'тура';
    return 'туров';
  }

  function plural(count, one, few, many) {
    var n = Math.abs(Number(count) || 0);
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  function formatTourDate(value) {
    var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim());
    if (!match) return String(value || '').trim();
    var months = ['янв.', 'февр.', 'марта', 'апр.', 'мая', 'июня', 'июля', 'авг.', 'сент.', 'окт.', 'нояб.', 'дек.'];
    return String(Number(match[3])) + ' ' + months[Number(match[2]) - 1] + ' ' + match[1];
  }

  function mealLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var key = raw.toUpperCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
    var labels = {
      'RO': 'Без питания',
      'ROOM ONLY': 'Без питания',
      'BB': 'Завтраки',
      'BREAKFAST': 'Завтраки',
      'HB': 'Завтрак и ужин',
      'HALF BOARD': 'Завтрак и ужин',
      'FB': 'Трёхразовое питание',
      'FULL BOARD': 'Трёхразовое питание',
      'AI': 'Всё включено',
      'ALL INCLUSIVE': 'Всё включено',
      'UAI': 'Ультра всё включено',
      'ULTRA ALL INCLUSIVE': 'Ультра всё включено'
    };
    return labels[key] || raw;
  }

  function guestCountLabel(adults, children) {
    adults = Math.max(1, Number(adults) || 2);
    children = Math.max(0, Number(children) || 0);
    var label = adults + ' ' + plural(adults, 'взрослый', 'взрослых', 'взрослых');
    if (children > 0) label += ' и ' + children + ' ' + plural(children, 'ребёнок', 'ребёнка', 'детей');
    return label;
  }

  function roomLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var key = raw.toLowerCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
    var labels = {
      'standard': 'Стандартный номер',
      'standard room': 'Стандартный номер',
      'std': 'Стандартный номер',
      'std room': 'Стандартный номер',
      'std room without air conditioner': 'Стандартный номер без кондиционера'
    };
    return labels[key] || raw;
  }

  function placementLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var labels = {
      'SGL': 'Одноместное',
      'DBL': 'Двухместное',
      'TRPL': 'Трёхместное',
      'QUAD': 'Четырёхместное'
    };
    return labels[raw.toUpperCase()] || raw;
  }

  function guestLabel() {
    var form = document.getElementById('tourSearch');
    var adults = Number(form && form.elements && form.elements.count_people && form.elements.count_people.value || 2) || 2;
    var children = Number(form && form.elements && form.elements.child_count && form.elements.child_count.value || 0) || 0;
    return guestCountLabel(adults, children);
  }

  function cardFacts(hotel) {
    var tour = representativeTour(hotel);
    if (!tour) return [];
    var facts = [];
    if (tour.date) facts.push(['Вылет', formatTourDate(tour.date)]);
    if (tour.nights) facts.push(['Ночей', String(tour.nights)]);
    var meal = mealLabel(tour.meal);
    if (meal) facts.push(['Питание', meal]);
    if (tour.isCharter === true) facts.push(['Рейс', 'Чартер']);
    return facts.slice(0, 4);
  }

  function decorateDecisionCopy(card, hotel) {
    var rating = Number(hotel && hotel.rating || 0);
    var ratingNode = card.querySelector('.hotel-decision-rating');
    if (ratingNode && rating > 0) {
      ratingNode.textContent = '★ ' + rating.toLocaleString('ru-RU', { maximumFractionDigits: 1 }) + '/5';
      ratingNode.setAttribute('aria-label', 'Оценка отеля ' + rating.toLocaleString('ru-RU', { maximumFractionDigits: 1 }) + ' из 5');
    }
    var sea = Number(hotel && hotel.seaDistance || 0);
    var seaNode = card.querySelector('.hotel-decision-sea');
    if (seaNode && sea > 0) seaNode.textContent = 'До моря ' + new Intl.NumberFormat('ru-RU').format(sea) + ' м';
  }

  function decorateHeading(bodyNode, hotel) {
    var title = bodyNode.querySelector('.hotel-title');
    if (!title || title.parentElement.classList.contains('search3-hotel-heading')) return;
    var heading = document.createElement('div');
    heading.className = 'search3-hotel-heading';
    title.parentNode.insertBefore(heading, title);
    heading.appendChild(title);
    var category = Number(hotel && hotel.category || 0);
    if (category > 0) {
      var stars = document.createElement('span');
      stars.className = 'search3-hotel-category';
      stars.textContent = category + '★';
      stars.setAttribute('aria-label', 'Категория отеля ' + category + ' звёзд');
      heading.appendChild(stars);
    }
  }

  function decoratePriceContext(bodyNode, hotel) {
    var tour = representativeTour(hotel);
    var bestOffer = bodyNode.querySelector('.hotel-best-offer');
    var label = bestOffer && bestOffer.querySelector(':scope > small:not(.hotel-price-context)');
    var price = bestOffer && bestOffer.querySelector('.hotel-price');
    var context = bestOffer && bestOffer.querySelector('.hotel-price-context');
    if (label) label.textContent = 'За весь тур';
    if (price) price.setAttribute('aria-label', (price.textContent || '').replace(/\s+/g, ' ').trim() + ', за тур на ' + guestLabel());
    if (!tour || !context) return;
    context.innerHTML = '<span>' + safe(guestLabel()) + '</span>';
  }

  function ensureTourListHead(toursNode, hotel) {
    if (!toursNode || toursNode.querySelector('.search3-tour-list-head')) return;
    var count = Array.isArray(hotel && hotel.tours) ? hotel.tours.length : toursNode.querySelectorAll('.tour-row').length;
    var head = document.createElement('div');
    head.className = 'search3-tour-list-head';
    head.innerHTML = '<div><strong>Лучшее предложение</strong><span>Сравните дату, номер, питание и цену</span></div>'
      + '<b>' + count + ' ' + tourWord(count) + '</b>';
    toursNode.insertBefore(head, toursNode.firstChild);
  }

  function decorateTourRows(toursNode, hotel) {
    if (!toursNode) return;
    ensureTourListHead(toursNode, hotel);
    toursNode.querySelectorAll('.tour-row').forEach(function (row) {
      if (row.dataset.search3OfferV2 === '1') return;
      row.dataset.search3OfferV2 = '1';

      var date = row.querySelector('.tour-meta > strong');
      if (date) date.textContent = formatTourDate(date.textContent);

      row.querySelectorAll('.tour-fact').forEach(function (fact) {
        var label = fact.querySelector('small');
        var value = fact.querySelector('b');
        if (!label || !value) return;
        var name = textValue(label.textContent).toLowerCase();
        if (name === 'питание') value.textContent = mealLabel(value.textContent);
        if (name === 'номер') value.textContent = roomLabel(value.textContent);
        if (name === 'размещение') value.textContent = placementLabel(value.textContent);
      });

      var action = row.querySelector('.tour-action');
      var price = action && action.querySelector(':scope > b');
      var productionChoice = action && action.querySelector('button[data-tid]');
      if (productionChoice && !productionChoice.dataset.search3ProductionLabel) {
        productionChoice.dataset.search3ProductionLabel = (productionChoice.textContent || '').replace(/\s+/g, ' ').trim();
      }
      if (action && price) {
        var scope = document.createElement('small');
        scope.className = 'search3-tour-price-scope';
        scope.textContent = 'За весь тур';
        action.insertBefore(scope, price);
        price.setAttribute('aria-label', (price.textContent || '').replace(/\s+/g, ' ').trim() + ', за весь тур');
      }
    });
  }

  function collapseCard(card) {
    var tours = card.querySelector('.hotel-tours');
    var button = card.querySelector('.search3-show-tours');
    card.classList.remove('search3-tours-open');
    if (tours) tours.hidden = true;
    if (button) {
      button.setAttribute('aria-expanded', 'false');
      button.textContent = 'Показать туры';
    }
  }

  function collapseAll(except) {
    results.querySelectorAll('.hotel-card.search3-tours-open').forEach(function (card) {
      if (card !== except) collapseCard(card);
    });
  }

  function decorateCard(card) {
    if (!card || card.dataset.search3ResultsV1 === '1') return;
    var hotel = hotelsById.get(String(card.dataset.hotelId || ''));
    var bodyNode = card.querySelector('.hotel-body');
    var tours = card.querySelector('.hotel-tours');
    if (!hotel || !bodyNode || !tours) return;

    card.dataset.search3ResultsV1 = '1';
    decorateHeading(bodyNode, hotel);
    decorateDecisionCopy(card, hotel);
    decoratePriceContext(bodyNode, hotel);
    decorateTourRows(tours, hotel);
    var facts = cardFacts(hotel);
    if (facts.length) {
      var factsNode = document.createElement('div');
      factsNode.className = 'search3-hotel-facts';
      factsNode.innerHTML = facts.map(function (fact) {
        return '<span><small>' + safe(fact[0]) + '</small><b>' + safe(fact[1]) + '</b></span>';
      }).join('');
      bodyNode.appendChild(factsNode);
    }

    var count = Array.isArray(hotel.tours) ? hotel.tours.length : tours.querySelectorAll('.tour-row').length;
    var action = document.createElement('div');
    action.className = 'search3-hotel-action';
    action.innerHTML = '<div class="search3-hotel-action__copy"><strong>' + count + ' ' + tourWord(count)
      + '</strong><span>доступно по выбранным датам</span></div>'
      + '<button type="button" class="search3-show-tours" aria-expanded="false">Показать туры</button>';
    bodyNode.appendChild(action);

    if (!tours.id) tours.id = 'search3-hotel-tours-' + safe(String(card.dataset.hotelId || 'result'));
    action.querySelector('.search3-show-tours').setAttribute('aria-controls', tours.id);
    tours.hidden = true;
  }

  function decorate(items) {
    hotelsById = new Map((Array.isArray(items) ? items : []).map(function (hotel) {
      return [hotelId(hotel), hotel];
    }));
    body.classList.toggle('search3-results-active', hotelsById.size > 0);
    if (!hotelsById.size) return;
    results.querySelectorAll('.hotel-card').forEach(decorateCard);
    window.setTimeout(mountMobileToolbar, 0);
  }

  function mountMobileToolbar() {
    if (!body.classList.contains('search3-results-active')) return;
    var filterBar = document.querySelector('.mrf-bar');
    if (!filterBar || !sort) return;
    var toolbar = document.querySelector('.search3-mobile-toolbar');
    if (!toolbar) {
      toolbar = document.createElement('div');
      toolbar.className = 'search3-mobile-toolbar';
      toolbar.innerHTML = '<div class="search3-mobile-filter-slot"></div>'
        + '<label class="search3-mobile-sort"><span>Сортировка</span><select aria-label="Сортировка результатов"></select></label>';
      tools.insertAdjacentElement('afterend', toolbar);
      var proxy = toolbar.querySelector('select');
      proxy.innerHTML = sort.innerHTML;
      proxy.value = sort.value;
      proxy.addEventListener('change', function () {
        sort.value = proxy.value;
        sort.dispatchEvent(new Event('change', { bubbles: true }));
      });
      sort.addEventListener('change', function () { proxy.value = sort.value; });
    }
    var slot = toolbar.querySelector('.search3-mobile-filter-slot');
    if (slot && filterBar.parentElement !== slot) slot.appendChild(filterBar);
  }

  window.addEventListener('v2:results-rendered', function (event) {
    collapseAll();
    decorate(event && event.detail && Array.isArray(event.detail.items) ? event.detail.items : []);
  });

  window.addEventListener('v2:search-reset', function () {
    hotelsById.clear();
    collapseAll();
    body.classList.remove('search3-results-active');
  });

  window.addEventListener('v2:tour-selected', function () { collapseAll(); });

  document.addEventListener('click', function (event) {
    var more = event.target && event.target.closest && event.target.closest('.tour-more-toggle');
    if (more && results.contains(more)) {
      window.setTimeout(function () {
        var card = more.closest('.hotel-card');
        var hotel = card && hotelsById.get(String(card.dataset.hotelId || ''));
        decorateTourRows(card && card.querySelector('.hotel-tours'), hotel);
      }, 0);
      return;
    }
    var button = event.target && event.target.closest && event.target.closest('.search3-show-tours');
    if (!button || !results.contains(button)) return;
    var card = button.closest('.hotel-card');
    var tours = card && card.querySelector('.hotel-tours');
    if (!card || !tours) return;
    var open = button.getAttribute('aria-expanded') !== 'true';
    collapseAll(open ? card : null);
    card.classList.toggle('search3-tours-open', open);
    tours.hidden = !open;
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.textContent = open ? 'Скрыть туры' : 'Показать туры';
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Tab') return;
    var sheet = document.querySelector('.mrf-sheet.is-open');
    var panel = sheet && sheet.querySelector('.mrf-panel[role="dialog"]');
    if (!panel) return;
    var focusable = Array.prototype.filter.call(
      panel.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'),
      function (node) {
        var box = node.getBoundingClientRect();
        var style = window.getComputedStyle(node);
        return box.width > 0 && box.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
      }
    );
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
      event.preventDefault();
      first.focus();
    }
  });

  if (window.matchMedia) {
    var compactResults = window.matchMedia('(max-width:999px)');
    if (compactResults.addEventListener) {
      compactResults.addEventListener('change', function (event) {
        if (event.matches) window.setTimeout(mountMobileToolbar, 0);
      });
    }
  }

  window.Search3CandidateResultsV1 = Object.freeze({
    version: 3,
    status: 'REFERENCE_IMPLEMENTATION_IN_PROGRESS',
    approvedPixelsCompared: false,
    partyLabel: guestCountLabel,
    formatDate: formatTourDate,
    mealLabel: mealLabel,
    roomLabel: roomLabel,
    placementLabel: placementLabel,
    decorate: decorate,
    collapseAll: collapseAll
  });
})();


/* Candidate-only human-readable selected-tour presentation. */
(function () {
  'use strict';

  if (window.Search3CandidateSelectedPresentationV1) return;
  var selected = document.getElementById('selectedTour');
  var format = window.Search3CandidateResultsV1;
  if (!selected || !format || !document.body.classList.contains('search3-candidate')) return;
  var tour = null;
  var selectedTotal = 0;
  var queued = false;

  function text(value) {
    if (value == null) return '';
    if (typeof value === 'object') return text(value.russianName || value.fullRussianName || value.name || value.title || '');
    return String(value).trim();
  }

  function plural(count, one, few, many) {
    var n = Math.abs(Number(count) || 0);
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  function setText(node, value) {
    value = String(value || '').trim();
    if (node && value && String(node.textContent || '').trim() !== value) node.textContent = value;
  }

  function labelValueRows(scope, rowSelector, labelSelector, valueSelector, values) {
    if (!scope) return;
    scope.querySelectorAll(rowSelector).forEach(function (row) {
      var label = row.querySelector(labelSelector);
      var value = row.querySelector(valueSelector);
      var key = String(label && label.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (value && values[key]) setText(value, values[key]);
    });
  }

  function partyLabel(value) {
    return format.partyLabel(Number(value && value.adults || 2), Number(value && value.childs || 0));
  }

  function normalizedTotal(detail) {
    var value = detail || {};
    var sourceTour = value.tour || tour || {};
    if (value.pricePending) return Number(value.basePrice || sourceTour.price || 0);
    return Number(value.price || 0) || Number(value.basePrice || sourceTour.price || 0);
  }

  function dateWithNights(value) {
    var date = format.formatDate(value && value.date);
    var nights = Number(value && value.nights || 0);
    var stay = nights ? nights + ' ' + plural(nights, 'ночь', 'ночи', 'ночей') : '';
    return [date, stay].filter(Boolean).join(' · ');
  }

  function displayValues(value) {
    return {
      'дата': format.formatDate(value && value.date),
      'питание': format.mealLabel(value && value.meal),
      'номер': format.roomLabel(value && value.roomType),
      'размещение': format.placementLabel(value && value.placement)
    };
  }

  function decoratePriceContext(value) {
    var party = partyLabel(value);
    var scope = 'За весь тур · ' + party;
    var amount = selectedTotal || Number(value && value.price || 0);
    var money = amount > 0 ? new Intl.NumberFormat('ru-RU').format(amount) + ' ₽' : '';
    var selectedPrice = selected.querySelector('.selected-price');
    var selectedPriceLabel = selectedPrice && selectedPrice.querySelector('small');
    setText(selectedPriceLabel, scope);
    if (selectedPrice && money) selectedPrice.setAttribute('aria-label', money + ', ' + scope.toLowerCase());

    selected.querySelectorAll('.search3-booking-summary__total,.search3-tour-detail-rail__price').forEach(function (price) {
      setText(price.querySelector(':scope > span'), scope);
      setText(price.querySelector(':scope > strong'), money);
      if (money) price.setAttribute('aria-label', money + ', ' + scope.toLowerCase());
    });

    var mobileBar = document.querySelector('.search3-selected-mobile-bar');
    if (mobileBar) {
      setText(mobileBar.querySelector('.search3-selected-mobile-bar__price small'), scope);
      var mobilePrice = mobileBar.querySelector('[data-s3-selected-price]');
      setText(mobilePrice, money);
      if (mobilePrice && money) mobilePrice.setAttribute('aria-label', money + ', ' + scope.toLowerCase());
      setText(mobileBar.querySelector('[data-s3-selected-lead]'), 'Далее: итог тура');
    }
  }

  function decorate() {
    if (!tour || selected.hidden) return;
    var values = displayValues(tour);
    labelValueRows(selected, '.facts > div', 'span', 'b', values);
    labelValueRows(selected, '.search3-booking-summary dl > div', 'dt', 'dd', values);
    labelValueRows(selected, '.search3-tour-detail-rail dl > div', 'dt', 'dd', values);
    labelValueRows(selected, '.search3-final-services > article', 'span', 'strong', values);

    selected.querySelectorAll('.search3-final-services > article').forEach(function (article) {
      var label = article.querySelector('span');
      if (String(label && label.textContent || '').trim().toLowerCase() === 'номер') {
        setText(article.querySelector('small'), values['размещение']);
      }
    });

    selected.querySelectorAll('.search3-tour-detail-rail dl > div').forEach(function (row) {
      if (String(row.querySelector('dt') && row.querySelector('dt').textContent || '').trim().toLowerCase() === 'дата') {
        setText(row.querySelector('dd'), dateWithNights(tour));
      }
    });

    decoratePriceContext(tour);
    var detailContinue = selected.querySelector('.search3-tour-detail-rail__continue');
    setText(detailContinue, 'Далее: итог тура');
    var flightContinue = selected.querySelector('.search3-flight-continue button');
    if (flightContinue && !selected.classList.contains('search3-final-review')) setText(flightContinue, 'Далее: итог тура');
    selected.dataset.search3SelectedPresentation = '1';
  }

  function schedule() {
    if (queued) return;
    queued = true;
    window.setTimeout(function () {
      queued = false;
      decorate();
    }, 0);
  }

  new MutationObserver(schedule).observe(selected, { childList: true, subtree: true });
  window.addEventListener('v2:tour-selected', function (event) {
    tour = event && event.detail && event.detail.tour || null;
    selectedTotal = Number(tour && tour.price || 0);
    schedule();
  });
  window.addEventListener('v2:flight-selected', schedule);
  window.addEventListener('v2:tour-price-updated', function (event) {
    selectedTotal = normalizedTotal(event && event.detail);
    schedule();
  });
  window.addEventListener('v2:booking-review', schedule);
  window.addEventListener('search3:lead-entry', schedule);

  window.Search3CandidateSelectedPresentationV1 = Object.freeze({
    version: 1,
    decorate: decorate,
    displayValues: displayValues,
    dateWithNights: dateWithNights,
    normalizedTotal: normalizedTotal
  });
})();


/* Candidate-only selected-tour handoff. Production search, tour and lead contracts stay authoritative. */
(function () {
  'use strict';

  if (window.Search3CandidateSelectedHandoffV1) return;
  var selected = document.getElementById('selectedTour');
  var results = document.getElementById('results');
  if (!selected || !results || !document.body.classList.contains('search3-candidate')) return;
  var selectedFocusRun = 0;
  var returnFocusRun = 0;

  function restoreProductionLabels() {
    results.querySelectorAll('button[data-search3-production-label]').forEach(function (button) {
      var original = String(button.dataset.search3ProductionLabel || '').trim();
      var current = String(button.textContent || '').replace(/\s+/g, ' ').trim();
      if (!button.disabled && original && current !== original) button.textContent = original;
    });
  }

  function isTourLoading() {
    if (selected.hidden || selected.children.length !== 1) return false;
    var onlyChild = selected.firstElementChild;
    return !!(onlyChild && onlyChild.classList.contains('selected-loading') && !onlyChild.querySelector('button,a,input,select,textarea'));
  }

  function syncBusy() {
    selected.setAttribute('aria-busy', isTourLoading() ? 'true' : 'false');
  }

  function prepareSelectedContext() {
    var heading = selected.querySelector('.selected-head h2') || selected.querySelector('.search3-review-heading h2');
    if (!heading) return null;
    if (!heading.id) heading.id = 'search3-selected-tour-heading';
    heading.setAttribute('tabindex', '-1');
    selected.setAttribute('tabindex', '-1');
    selected.setAttribute('aria-labelledby', heading.id);
    return heading;
  }

  function focusSelectedHeading() {
    if (selected.hidden) return;
    var heading = prepareSelectedContext();
    if (!heading) return;
    try { heading.focus({ preventScroll: true }); } catch (_error) { heading.focus(); }
  }

  function focusSelectedContext() {
    if (selected.hidden || !prepareSelectedContext()) return;
    try { selected.focus({ preventScroll: true }); } catch (_error) { selected.focus(); }
  }

  function scheduleSelectedContextFocus() {
    var run = ++selectedFocusRun;
    var attempts = 0;
    function settle() {
      if (run !== selectedFocusRun || selected.hidden) return;
      var heading = prepareSelectedContext();
      attempts += 1;
      if (!heading || !heading.isConnected) {
        if (attempts < 6) window.requestAnimationFrame(settle);
        return;
      }
      if (document.activeElement !== heading && document.activeElement !== selected) focusSelectedContext();
      if (attempts < 6) window.requestAnimationFrame(settle);
    }
    window.setTimeout(function () { window.requestAnimationFrame(settle); }, 0);
  }

  function isVisibleFocusTarget(target) {
    return !!(target && target.isConnected && !target.disabled && target.getClientRects().length > 0);
  }

  function focusReturnedContext(sourceHint) {
    var run = ++returnFocusRun;
    var attempts = 0;
    var source = sourceHint || (window.V2SelectedTourReturnV1 && window.V2SelectedTourReturnV1.sourceButton);
    var card = source && source.closest && source.closest('.hotel-card');

    function settle() {
      if (run !== returnFocusRun || !selected.hidden) return;
      var target = card && card.querySelector('.search3-show-tours');
      if (!isVisibleFocusTarget(target)) {
        target = results;
        if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
      }
      results.dataset.search3ReturnFocus = target === results ? 'results' : 'resume-tours';
      if (document.activeElement !== target) {
        try { target.focus({ preventScroll: true }); } catch (_error) { target.focus(); }
      }
      attempts += 1;
      if (attempts < 8) window.requestAnimationFrame(settle);
    }

    window.setTimeout(function () { window.requestAnimationFrame(settle); }, 0);
  }

  new MutationObserver(function () {
    syncBusy();
    restoreProductionLabels();
  }).observe(selected, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });

  new MutationObserver(restoreProductionLabels).observe(results, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['disabled']
  });

  function cancelPendingFocus(event) {
    if (event.target && selected.contains(event.target)) selectedFocusRun += 1;
  }
  function cancelReturnFocus() {
    if (selected.hidden) returnFocusRun += 1;
  }
  selected.addEventListener('pointerdown', cancelPendingFocus, true);
  selected.addEventListener('keydown', cancelPendingFocus, true);
  document.addEventListener('pointerdown', cancelReturnFocus, true);
  document.addEventListener('keydown', cancelReturnFocus, true);

  window.addEventListener('v2:tour-selected', function () {
    selected.setAttribute('aria-busy', 'false');
    restoreProductionLabels();
    scheduleSelectedContextFocus();
  });
  window.addEventListener('v2:tour-returned', function (event) {
    selectedFocusRun += 1;
    selected.setAttribute('aria-busy', 'false');
    restoreProductionLabels();
    focusReturnedContext(event && event.detail && event.detail.source);
  });
  window.addEventListener('v2:search-reset', function () {
    selectedFocusRun += 1;
    returnFocusRun += 1;
    selected.setAttribute('aria-busy', 'false');
  });

  syncBusy();
  restoreProductionLabels();
  window.Search3CandidateSelectedHandoffV1 = Object.freeze({
    version: 1,
    restoreProductionLabels: restoreProductionLabels,
    syncBusy: syncBusy,
    focusSelectedHeading: focusSelectedHeading,
    focusSelectedContext: focusSelectedContext,
    scheduleSelectedContextFocus: scheduleSelectedContextFocus,
    focusReturnedContext: focusReturnedContext
  });
})();
