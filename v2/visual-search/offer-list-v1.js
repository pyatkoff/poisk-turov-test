// Cold presentation owner. The application supplies its current view and helpers.
(function(){
'use strict';
function create(context){
 const {$,$$,cardPriceNote,dateText,durationText,esc,flightLabel,guestsText,hotelOffers,hotels,icon,innerWidth,mealLabel,mealNames,money,needsRefresh,nightsText,offerActionLabel,offerCountText,offerGroupKey,offerMetaNote,offerRefinementFields,offerSearchContext,offerView,operatorBadge,optionalShortlistEnabled,rangeText,rememberUIRoute,renderComparisonFooter,sharedOfferNote,state,selectionStepsHTML,setComparisonQuotes}=context;
const comparisonVariantLabel=o=>money(o.total)+' · '+o.room+' · '+mealLabel(o)+' · '+o.operator+' · '+flightLabel(o);
function normalizeComparison(options){
 const ids=options.map(o=>o.variant),pair=(offerView.pair||[]).filter((v,i,a)=>ids.includes(v)&&a.indexOf(v)===i).slice(0,2);
 if(!pair.length&&ids.length){pair.push(ids[0]);if(ids.includes(offerView.activeVariant)&&offerView.activeVariant!==ids[0])pair.push(offerView.activeVariant);}
 if(pair.length<2&&ids.length>1)pair.push(ids.find(v=>!pair.includes(v)));
 if(!ids.includes(offerView.activeVariant))offerView.activeVariant=pair[0]??null;
 if(pair.length&&!pair.includes(offerView.activeVariant))offerView.activeVariant=pair[0];
 offerView.pair=pair;
 return pair.map(v=>options.find(o=>o.variant===v));
}
const comparisonFields=[['room','номер',o=>o.room||'Уточняется'],['meal','питание',mealLabel],['operator','туроператор',o=>o.operator||'Уточняется'],['placement','размещение',o=>o.placement||'Уточняется'],['flight','тип перелёта',flightLabel],['returnDay','дата возвращения',o=>o.returnDay||'Уточняется']];
function comparisonDecisionHTML(visible){
 if(visible.length<2)return '<div class="comparison-decision" role="status"><strong>На эти даты и условия один вариант</strong><p>Можно изменить дату или фильтры, чтобы найти другие предложения.</p></div>';
 const [first,second]=visible,delta=second.total-first.total,differences=comparisonFields.filter(([, ,value])=>value(first)!==value(second)).map(([,label])=>label);
 return `<div class="comparison-decision" role="status"><strong>${delta===0?'Цена двух вариантов одинакова':`Второй вариант ${delta>0?'дороже':'дешевле'} на ${money(Math.abs(delta))}`}</strong><p>${differences.length?'Различаются: '+differences.map(esc).join(', ')+'.':'Показанные условия совпадают. Конкретные рейсы и полные условия уточняются для каждого тура.'}</p></div>`;
}
function renderTourComparison(all,filtered){
 const dates=[...new Set(all.map(o=>o.day))].sort();if(!dates.includes(offerView.day))offerView.day=dates[0]||state.search.from;
 const nights=[...new Set(all.filter(o=>o.day===offerView.day).map(o=>o.nights))].sort((a,b)=>a-b);if(!nights.includes(offerView.nights))offerView.nights=nights[0]||state.search.minNights;
 const options=filtered.filter(o=>o.day===offerView.day&&o.nights===offerView.nights).sort((a,b)=>a.total-b.total);setComparisonQuotes(options);
 const visible=normalizeComparison(options),minPrice=visible.length?Math.min(...visible.map(o=>o.total)):null;
 const fact=(key,label,value)=>{const get=comparisonFields.find(([field])=>field===key)?.[2]||((o)=>o[key]);const different=new Set(visible.map(get)).size>1;return offerView.differencesOnly&&visible.length>1&&!different?'':`<div class="${different?'fact-different':''}"><dt>${label}</dt><dd>${esc(value)}</dd></div>`;};
 const datesExpanded=$('.comparison-date-disclosure')?.open===true;
 const dateFields=`<div class="comparison-date-fields"><label>Дата вылета<select id="compare-offer-day" ${dates.length<2?'disabled':''}>${dates.map(day=>`<option value="${day}" ${day===offerView.day?'selected':''}>${dateText(day)}</option>`).join('')}</select></label><label>Ночей<select id="compare-offer-nights" ${nights.length<2?'disabled':''}>${nights.map(n=>`<option value="${n}" ${n===offerView.nights?'selected':''}>${nightsText(n)}</option>`).join('')}</select></label></div>`;
 $('#offer-comparison-dates').innerHTML=dates.length>1||nights.length>1?`<details class="comparison-date-disclosure" ${datesExpanded?'open':''}><summary>Вылет ${dateText(offerView.day)} · ${nightsText(offerView.nights)} <span>Изменить</span></summary>${dateFields}</details>`:`<p class="comparison-fixed-dates" tabindex="-1">Вылет ${dateText(offerView.day)} · ${nightsText(offerView.nights)}</p>`;
 $('#offer-count').textContent=offerCountText(options.length);
 const controls=options.length>2?`<div class="comparison-pair-fields">${offerView.pair.map((v,i)=>`<label>${i?'Второй вариант':'Первый вариант'}<select id="comparison-pair-${i}">${options.map(o=>`<option value="${o.variant}" ${o.variant===v?'selected':''}>${esc(comparisonVariantLabel(o))}</option>`).join('')}</select></label>`).join('')}</div>`:'';
 $('#all-offers-list').innerHTML=options.length?`<div class="comparison-options-tools"><label class="comparison-differences-switch"><input type="checkbox" id="tour-differences-only" ${offerView.differencesOnly&&options.length>1?'checked':''} ${options.length<2?'disabled':''}> Только отличия</label><span class="comparison-visible-count">${offerCountText(options.length)}</span></div>${controls}${comparisonDecisionHTML(visible)}<div class="tour-comparison-grid" data-count="${visible.length}" data-total-count="${options.length}" data-visible-count="${visible.length}">${visible.map(o=>`<article class="tour-comparison-option ${o.total===minPrice?'best-price':''} ${offerView.activeVariant===o.variant?'focused-option':''}" data-variant="${o.variant}" style="--comparison-order:${offerView.pair.indexOf(o.variant)}"><label class="comparison-focus-choice"><input type="radio" name="comparison-focus" value="${o.variant}" ${offerView.activeVariant===o.variant?'checked':''}><span>${offerView.pair.indexOf(o.variant)===0?'Первый вариант':'Второй вариант'}</span></label><div class="comparison-option-top"><span>${visible.length===1?'Вариант тура':o.total===minPrice?'Минимум в сравнении':'Вариант тура'}</span>${operatorBadge(o.operator)}</div><h3>${esc(o.room)}</h3><dl class="comparison-tour-facts">${fact('meal','Питание',mealLabel(o))}${fact('operator','Туроператор',o.operator)}${fact('placement','Размещение',o.placement||'Уточняется')}${fact('flight','Тип перелёта',flightLabel(o))}<div><dt>Вылет — возвращение</dt><dd>${rangeText(o.day,o.returnDay)}</dd></div></dl><div class="comparison-option-price"><span>За всех туристов</span><strong>${money(o.total)}</strong><small>${cardPriceNote(o)}</small></div><div class="comparison-option-actions"><button class="primary" data-action="offer" data-key="${esc(o.key)}">${offerActionLabel(o)} ${icon('arrow')}</button>${needsRefresh(o)?'':`<button class="secondary" data-action="offer-flights" data-key="${esc(o.key)}">${icon('plane')} Рейсы и багаж</button>`}</div></article>`).join('')}</div>`:'<div class="destination-empty"><h3>Нет вариантов на эти даты</h3><p>Измените дату или фильтры.</p></div>';renderComparisonFooter();
}
const offerRefinementLabels={departure:'Дата',flight:'Перелёт',room:'Номер',meal:'Питание'};
const offerRefinementAny={departure:'Любая дата',flight:'Любой перелёт',room:'Любой номер',meal:'Любое питание'};
function matchesOfferRefinements(o,view=offerView){return offerRefinementFields.every(field=>!view[field]||(field==='departure'?o.day:o[field])===view[field]);}
function offerRefinementLabel(field,value){return field==='departure'?dateText(value):field==='flight'?flightLabel({flight:value}):field==='meal'?value:value;}
function renderOfferRefinements(all,comparing){
 const host=$('#offer-local-selected');
 host.hidden=comparing||!offerRefinementFields.some(field=>offerView[field]);
 host.innerHTML=comparing?'':offerRefinementFields.filter(field=>offerView[field]).map(field=>`<button type="button" class="active-filter" data-action="remove-offer-filter" data-field="${field}" aria-label="Убрать условие: ${offerRefinementLabels[field]} — ${esc(offerRefinementLabel(field,offerView[field]))}"><span>${offerRefinementLabels[field]}: ${esc(offerRefinementLabel(field,offerView[field]))}</span>${icon('x')}</button>`).join('');
 const visibleFields=[];
 for(const field of offerRefinementFields){
  const select=$('#offer-'+field);
  const hasChoice=new Set(all.map(o=>field==='departure'?o.day:o[field])).size>1;
  const visible=comparing?field!=='departure':hasChoice||!!offerView[field];
  select.closest('label').hidden=!visible;if(visible)visibleFields.push(field);
  for(const option of select.options){
   if(!option.dataset.baseLabel)option.dataset.baseLabel=option.textContent;
   const count=all.filter(o=>matchesOfferRefinements(o,{...offerView,[field]:option.value})).length;
   option.textContent=comparing?option.dataset.baseLabel:`${option.dataset.baseLabel} · ${offerCountText(count)}`;
  }
 }
 $('.offer-filter-disclosure').hidden=!comparing&&!visibleFields.length;
 $('.offer-controls').style.setProperty('--offer-filter-columns',Math.min(2,visibleFields.length)||1);
 if(!comparing&&!offerRefinementFields.some(field=>offerView[field]))$('#offer-filter-summary').textContent=visibleFields.map(field=>offerRefinementLabels[field]).join(' · ');
}
function offerRefinementRecovery(all){
 const choices=offerRefinementFields.filter(field=>offerView[field]).map(field=>({field,count:all.filter(o=>matchesOfferRefinements(o,{...offerView,[field]:''})).length})).filter(choice=>choice.count);
 return choices.length?`<p>Можно убрать одно условие, сохранив остальные:</p><div class="offer-recovery-actions">${choices.map(({field,count})=>`<button type="button" class="secondary" data-action="remove-offer-filter" data-field="${field}">${offerRefinementAny[field]} · ${offerCountText(count)}</button>`).join('')}</div>`:'<p>Измените условия выбора тура. Даты поездки и туристы в основном поиске сохранятся.</p>';
}
function offerListInventory(){
 const h=hotels.find(h=>h.id===offerView.id),all=hotelOffers(h),filtered=all.filter(o=>(offerView.mode==='compare'||!offerView.departure||o.day===offerView.departure)&&(!offerView.flight||o.flight===offerView.flight)&&(!offerView.room||o.room===offerView.room)&&(!offerView.meal||o.meal===offerView.meal));
 const sorted=[...filtered].sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total:a.total-b.total||a.day.localeCompare(b.day));
 const groups=[...new Set(sorted.map(offerGroupKey))].map(key=>({key,offers:sorted.filter(o=>offerGroupKey(o)===key)}));
 return {h,all,filtered,groups};
}
function renderOfferList(reset=false){
 if(!optionalShortlistEnabled)offerView.mode='list';
 const {h,all,filtered,groups}=offerListInventory();
 for(const field of offerRefinementFields){
  const select=$('#offer-'+field),values=[...new Set(all.map(o=>field==='departure'?o.day:o[field]))];
  if(offerView[field]&&!values.includes(offerView[field]))values.push(offerView[field]);
  if(field==='departure')values.sort();
  if(JSON.stringify([...select.options].map(o=>o.value))!==JSON.stringify(['',...values]))select.innerHTML=`<option value="">${field==='departure'?'Все даты':field==='meal'?'Любое':'Любой'}</option>`+values.map(value=>`<option value="${esc(value)}">${esc(offerRefinementLabel(field,value))}</option>`).join('');
 }
 if(reset){offerView.open=groups.length===1?[groups[0].key]:[];offerView.limits={};}
 for(const name of ['departure','flight','room','meal','sort'])$('#offer-'+name).value=offerView[name];
 const comparing=offerView.mode==='compare',wasComparing=$('#modal').classList.contains('tour-comparison-dialog');if(comparing!==wasComparing)$('.offer-filter-disclosure').open=!comparing&&innerWidth>760;$('#modal').classList.toggle('tour-comparison-dialog',comparing);$('#offer-comparison-dates').hidden=!comparing;$('#offer-sort-label').hidden=comparing||new Set(filtered.map(o=>o.day)).size<2;$$('[data-action="offer-view"]').forEach(b=>b.setAttribute('aria-pressed',b.dataset.value===offerView.mode));
 $('.offer-departure-filter').hidden=comparing;const localCount=[...(comparing?[]:['departure']),'flight','room','meal'].filter(k=>offerView[k]).length;$('.offer-reset').hidden=localCount<(comparing?1:2)||(!comparing&&!filtered.length);$('#offer-local-filter-count').textContent=localCount?'('+localCount+')':'';$('#offer-filter-summary').textContent=[!comparing&&offerView.departure?dateText(offerView.departure):'',offerView.flight?flightLabel({flight:offerView.flight}):'',offerView.room,offerView.meal?mealNames[offerView.meal]||offerView.meal:''].filter(Boolean).join(' · ')||(comparing?'Перелёт, номер и питание':'Дата, перелёт, номер и питание');$('.offer-list-context p').textContent=comparing?`${esc(state.search.origin)} → ${esc(h.resort)} · ${guestsText()}`:`${offerSearchContext()} · ${durationText()} · ${guestsText()}`;
 renderOfferRefinements(all,comparing);
 if(comparing){renderTourComparison(all,filtered);rememberUIRoute();return;}
 setComparisonQuotes([]);$('#modal-footer').hidden=true;$('#modal-footer').innerHTML='';rememberUIRoute();
 $('#offer-count').textContent=offerCountText(filtered.length);
 $('#all-offers-list').innerHTML=groups.length?groups.map(({key,offers})=>{
  const first=offers[0],min=Math.min(...offers.map(o=>o.total)),open=offerView.open.includes(key),limit=offerView.limits[key]||4;
  const commonNote=sharedOfferNote(all);
  const rows=offers.slice(0,limit).map(o=>`<div class="offer grouped-offer" data-offer-key="${o.key}"><div class="offer-departure"><strong>${dateText(o.day)} → ${dateText(o.returnDay)}</strong><small>${nightsText(o.nights)}</small></div><div class="offer-flight-details"><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}${commonNote?'':`<small>${esc(offerMetaNote(o))}</small>`}</div><div class="offer-price"><strong aria-label="${esc(money(o.total))} за всех туристов">${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${o.key}">${offerActionLabel(o)} ${icon('arrow')}</button>${optionalShortlistEnabled?`<button class="text-button compare-tour-link" data-action="compare-tour" data-key="${o.key}">Сравнить на эти даты</button>`:''}</div></div>`).join('');
  return `<section class="offer-group"><button class="offer-group-heading" data-action="offer-group" data-value="${key}" aria-expanded="${open}" aria-controls="group-${key}"><span><strong>${esc(first.room)}</strong><small>${esc(mealLabel(first))}</small><small class="offer-group-scope" ${open?'hidden':''}>${[...new Set(offers.map(o=>nightsText(o.nights)))].join(' / ')} · ${[...new Set(offers.map(o=>o.day))].length===1?dateText(first.day):'Вылеты '+rangeText([...offers].sort((a,b)=>a.day.localeCompare(b.day))[0].day,[...offers].sort((a,b)=>a.day.localeCompare(b.day)).at(-1).day)}</small></span><span class="offer-group-min"><strong ${open?'hidden':''}>от ${money(min)}</strong><small>${offerCountText(offers.length)} <span class="rotate-arrow ${open?'up':''}">⌄</span></small></span></button><div id="group-${key}" class="offer-group-body" ${open?'':'hidden'}>${rows}${offers.length>limit?`<button class="text-button group-more" data-action="group-more" data-value="${key}">Ещё варианты (${offers.length-limit}) ${icon('arrow')}</button>`:''}</div></section>`;
 }).join(''):`<div class="destination-empty offer-recovery-empty"><h3>Нет такого сочетания</h3>${offerRefinementRecovery(all)}<button class="text-button" data-action="reset-offer-filters">Сбросить все условия выбора тура</button></div>`;
}
function mountOfferList(){
 const h=hotels.find(h=>h.id===offerView.id),all=hotelOffers(h);
 $('#modal-body').innerHTML=`${all.some(o=>!needsRefresh(o))?selectionStepsHTML(0):''}<div class="offer-list-context"><p>${offerSearchContext()} · ${durationText()} · ${guestsText()}</p><span>${icon('info')} Все цены за всех туристов. ${esc(sharedOfferNote(all)||(all.every(needsRefresh)?'Цены и наличие требуют проверки':'Сборы уточняются при выборе'))}</span></div>${optionalShortlistEnabled?'<div class="offer-view-switch" role="group" aria-label="Как показать туры"><button data-action="offer-view" data-value="list" aria-pressed="true">Все туры</button><button data-action="offer-view" data-value="compare" aria-pressed="false">Сравнить на даты</button></div>':''}<div id="offer-comparison-dates" class="offer-comparison-dates" hidden></div><details class="offer-filter-disclosure" ${innerWidth>760?'open':''}><summary><span>Уточнить варианты <span id="offer-local-filter-count"></span></span><small id="offer-filter-summary"></small></summary><div class="offer-controls"><label class="offer-departure-filter">Дата вылета<select id="offer-departure"><option value="">Все даты</option>${[...new Set(all.map(o=>o.day))].sort().map(day=>`<option value="${day}">${dateText(day)}</option>`).join('')}</select></label><label>Перелёт<select id="offer-flight"><option value="">Любой</option>${[...new Set(all.map(o=>o.flight))].map(f=>`<option value="${f}">${flightLabel({flight:f})}</option>`).join('')}</select></label><label>Номер<select id="offer-room"><option value="">Любой</option>${[...new Set(all.map(o=>o.room))].map(room=>`<option value="${esc(room)}">${esc(room)}</option>`).join('')}</select></label><label>Питание<select id="offer-meal"><option value="">Любое</option>${[...new Set(all.map(o=>o.meal))].map(m=>`<option value="${esc(m)}">${esc(m)}</option>`).join('')}</select></label></div></details><div id="offer-local-selected" class="offer-local-selected" aria-label="Условия выбора тура" hidden></div><div class="offer-list-toolbar"><span id="offer-count" aria-live="polite" tabindex="-1"></span><label id="offer-sort-label"><span class="sr-only">Сортировать туры</span><select id="offer-sort"><option value="price">Сначала дешевле</option><option value="date">По дате вылета</option></select></label></div><button class="text-button offer-reset" data-action="reset-offer-filters" hidden>Сбросить фильтры туров</button><div id="all-offers-list"></div>`;
}
 return {renderOfferList:reset=>{if(!$('#offer-count'))mountOfferList();renderOfferList(reset);}};
}
window.AnyTourOfferList={create};
})();
