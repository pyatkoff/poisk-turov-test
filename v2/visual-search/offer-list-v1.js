// Cold presentation owner. The application supplies its current view and helpers.
(function(){
'use strict';
function create(context){
 const {$,$$,appendGeneratedRoots,byId,cardPriceNote,dateText,durationText,esc,flightLabel,guestsText,hotelOffers,hotels,icon,innerWidth,mealLabel,mealNames,money,needsRefresh,nightsText,offerActionLabel,offerCountText,offerGroupKey,offerMetaNote,offerRefinementFields,offerSearchContext,offerView,operatorBadge,paintGeneratedRoots,rangeText,rememberUIRoute,sharedOfferNote,selectionStepsHTML}=context;
const offerRefinementLabels={departure:'Дата',flight:'Перелёт',room:'Номер',meal:'Питание'};
const offerRefinementAny={departure:'Любая дата',flight:'Любой перелёт',room:'Любой номер',meal:'Любое питание'};
let currentRefinementInventory;
let currentInventory,currentCommonNote='';
function matchesOfferRefinements(o,view=offerView){return offerRefinementFields.every(field=>!view[field]||(field==='departure'?o.day:o[field])===view[field]);}
function offerRefinementLabel(field,value){return field==='departure'?dateText(value):field==='flight'?flightLabel({flight:value}):field==='meal'?value:value;}
function offerRefinementCounts(all,field,options){
 const counts=new Map([...options].map(option=>[option.value,0])),length=all.length;
 for(let i=0;i<length;i++){
  if(!(i in all))continue;
  const offer=all[i];let matches=true;
  for(const current of offerRefinementFields){
   if(current===field)continue;
   const selected=offerView[current];if(selected&&(current==='departure'?offer.day:offer[current])!==selected){matches=false;break;}
  }
  if(!matches)continue;
  counts.set('',counts.get('')+1);
  const value=field==='departure'?offer.day:offer[field];if(value!==''&&counts.has(value))counts.set(value,counts.get(value)+1);
 }
 return counts;
}
function renderOfferRefinements(all){
 const host=$('#offer-local-selected');
 host.hidden=!offerRefinementFields.some(field=>offerView[field]);
 host.innerHTML=offerRefinementFields.filter(field=>offerView[field]).map(field=>`<button type="button" class="active-filter" data-action="remove-offer-filter" data-field="${field}" aria-label="Убрать условие: ${offerRefinementLabels[field]} — ${esc(offerRefinementLabel(field,offerView[field]))}"><span>${offerRefinementLabels[field]}: ${esc(offerRefinementLabel(field,offerView[field]))}</span>${icon('x')}</button>`).join('');
 const visibleFields=[];
 for(const field of offerRefinementFields){
  const select=$('#offer-'+field);
  const hasChoice=currentRefinementInventory.get(field).hasChoice;
  const visible=hasChoice||!!offerView[field];
  select.closest('label').hidden=!visible;if(visible)visibleFields.push(field);
  const counts=offerRefinementCounts(all,field,select.options);
  for(const option of select.options){
   if(!option.dataset.baseLabel)option.dataset.baseLabel=option.textContent;
   option.textContent=`${option.dataset.baseLabel} · ${offerCountText(counts.get(option.value))}`;
  }
 }
 $('.offer-filter-disclosure').hidden=!visibleFields.length;
 $('.offer-controls').style.setProperty('--offer-filter-columns',Math.min(2,visibleFields.length)||1);
 if(!offerRefinementFields.some(field=>offerView[field]))$('#offer-filter-summary').textContent=visibleFields.map(field=>offerRefinementLabels[field]).join(' · ');
}
function offerRefinementRecovery(all){
 const choices=offerRefinementFields.filter(field=>offerView[field]).map(field=>({field,count:all.filter(o=>matchesOfferRefinements(o,{...offerView,[field]:''})).length})).filter(choice=>choice.count);
 return choices.length?`<p>Можно убрать одно условие, сохранив остальные:</p><div class="offer-recovery-actions">${choices.map(({field,count})=>`<button type="button" class="secondary" data-action="remove-offer-filter" data-field="${field}">${offerRefinementAny[field]} · ${offerCountText(count)}</button>`).join('')}</div>`:'<p>Измените условия выбора тура. Даты поездки и туристы в основном поиске сохранятся.</p>';
}
function offerListInventory(){
 const h=hotels.find(h=>h.id===offerView.id),all=hotelOffers(h),filtered=all.filter(o=>(!offerView.departure||o.day===offerView.departure)&&(!offerView.flight||o.flight===offerView.flight)&&(!offerView.room||o.room===offerView.room)&&(!offerView.meal||o.meal===offerView.meal));
 const keyed=filtered.length<2?filtered.map(offer=>({offer})):filtered.map((offer,index)=>({offer,index,total:offer.total,day:offer.day}));
 keyed.sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total||a.index-b.index:a.total-b.total||a.day.localeCompare(b.day)||a.index-b.index);
 const sorted=keyed.map(item=>item.offer);
 const groups=[],byKey=new Map();
 for(const offer of sorted){
  const key=offerGroupKey(offer);let group=byKey.get(key);
  if(!group){group={key,offers:[]};byKey.set(key,group);groups.push(group);}
  group.offers.push(offer);
 }
 return {h,all,filtered,groups};
}
function offerRefinementInventory(all){
 const inventory=new Map();
 for(const field of offerRefinementFields){
  const values=[...new Set(all.map(offer=>field==='departure'?offer.day:offer[field]))];
  inventory.set(field,{values,hasChoice:values.length>1});
 }
 return inventory;
}
function offerGroupScope(offers){
 const nights=[],seenNights=new Set(),firstDay=offers[0].day;let earliest=firstDay,latest=firstDay,sameDay=true;
 for(const offer of offers){
  const night=nightsText(offer.nights);if(!seenNights.has(night)){seenNights.add(night);nights.push(night);}
  const day=offer.day;if(day===firstDay){if(sameDay)continue;}else sameDay=false;
  const earliestOrder=day.localeCompare(earliest);
  if(earliestOrder<0)earliest=day;else if(day.localeCompare(latest)>=0)latest=day;
 }
 return `${nights.join(' / ')} · ${sameDay?dateText(firstDay):'Вылеты '+rangeText(earliest,latest)}`;
}
function offerRowEntry(o,commonNote){return {offerKey:o.key,markup:`<div class="offer grouped-offer" data-offer-key="${o.key}"><div class="offer-departure"><strong>${dateText(o.day)} → ${dateText(o.returnDay)}</strong><small>${nightsText(o.nights)}</small></div><div class="offer-flight-details"><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}${commonNote?'':`<small>${esc(offerMetaNote(o))}</small>`}</div><div class="offer-price"><strong aria-label="${esc(money(o.total))} за всех туристов">${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${o.key}">${offerActionLabel(o)} ${icon('arrow')}</button></div></div>`};}
function offerMoreEntry(key,total,limit){return total>limit?{offerKey:'',markup:`<button class="text-button group-more" data-action="group-more" data-value="${key}">Ещё варианты (${total-limit}) ${icon('arrow')}</button>`}:null;}
function renderOfferList(reset=false,inventory=offerListInventory()){
 const {h,all,filtered,groups}=inventory;
 currentInventory=inventory;
 currentRefinementInventory=offerRefinementInventory(all);
 for(const field of offerRefinementFields){
  const select=$('#offer-'+field),values=[...currentRefinementInventory.get(field).values];
  if(offerView[field]&&!values.includes(offerView[field]))values.push(offerView[field]);
  if(field==='departure')values.sort();
  if(JSON.stringify([...select.options].map(o=>o.value))!==JSON.stringify(['',...values]))select.innerHTML=`<option value="">${field==='departure'?'Все даты':field==='meal'?'Любое':'Любой'}</option>`+values.map(value=>`<option value="${esc(value)}">${esc(offerRefinementLabel(field,value))}</option>`).join('');
 }
 if(reset){offerView.open=groups.length===1?[groups[0].key]:[];offerView.limits={};}
 for(const name of ['departure','flight','room','meal','sort'])$('#offer-'+name).value=offerView[name];
 $('#offer-sort-label').hidden=new Set(filtered.map(o=>o.day)).size<2;
 $('.offer-departure-filter').hidden=false;const localCount=['departure','flight','room','meal'].filter(k=>offerView[k]).length;$('.offer-reset').hidden=localCount<2||!filtered.length;$('#offer-local-filter-count').textContent=localCount?'('+localCount+')':'';$('#offer-filter-summary').textContent=[offerView.departure?dateText(offerView.departure):'',offerView.flight?flightLabel({flight:offerView.flight}):'',offerView.room,offerView.meal?mealNames[offerView.meal]||offerView.meal:''].filter(Boolean).join(' · ')||'Дата, перелёт, номер и питание';$('.offer-list-context p').textContent=`${offerSearchContext()} · ${durationText()} · ${guestsText()}`;
 renderOfferRefinements(all);
 $('#modal-footer').hidden=true;$('#modal-footer').innerHTML='';rememberUIRoute();
 $('#offer-count').textContent=offerCountText(filtered.length);
 const commonNote=groups.length?sharedOfferNote(all):'';
 currentCommonNote=commonNote;
 const groupViews=groups.map(({key,offers})=>{
  const first=offers[0],min=Math.min(...offers.map(o=>o.total)),open=offerView.open.includes(key),limit=offerView.limits[key]||4;
  const entries=offers.slice(0,limit).map(o=>offerRowEntry(o,commonNote));
  const markup=`<section class="offer-group"><button class="offer-group-heading" data-action="offer-group" data-value="${key}" aria-expanded="${open}" aria-controls="group-${key}"><span><strong>${esc(first.room)}</strong><small>${esc(mealLabel(first))}</small><small class="offer-group-scope" ${open?'hidden':''}>${offerGroupScope(offers)}</small></span><span class="offer-group-min"><strong ${open?'hidden':''}>от ${money(min)}</strong><small>${offerCountText(offers.length)} <span class="rotate-arrow ${open?'up':''}">⌄</span></small></span></button><div id="group-${key}" class="offer-group-body" ${open?'':'hidden'}></div></section>`,more=offerMoreEntry(key,offers.length,limit);
  return {key,markup,entries:[...entries,...(more?[more]:[])]};
 });
 $('#all-offers-list').innerHTML=groupViews.length?groupViews.map(group=>group.markup).join(''):`<div class="destination-empty offer-recovery-empty"><h3>Нет такого сочетания</h3>${offerRefinementRecovery(all)}<button class="text-button" data-action="reset-offer-filters">Сбросить все условия выбора тура</button></div>`;
 for(const group of groupViews)paintGeneratedRoots(byId('group-'+group.key),group.entries,true,'offerKey');
}
function renderMoreGroup(key,shown,remove){
 const group=currentInventory?.groups.find(group=>group.key===key),body=byId('group-'+key);if(!group||!body||shown<0||shown>group.offers.length)return false;
 const limit=Math.min(group.offers.length,offerView.limits[key]||4),more=offerMoreEntry(key,group.offers.length,limit);
 appendGeneratedRoots(body,[...group.offers.slice(shown,limit).map(o=>offerRowEntry(o,currentCommonNote)),...(more?[more]:[])],remove);rememberUIRoute();return true;
}
function mountOfferList({h,all}){
 $('#modal-body').innerHTML=`${all.some(o=>!needsRefresh(o))?selectionStepsHTML(0):''}<div class="offer-list-context"><p>${offerSearchContext()} · ${durationText()} · ${guestsText()}</p><span>${icon('info')} Все цены за всех туристов. ${esc(sharedOfferNote(all)||(all.every(needsRefresh)?'Цены и наличие требуют проверки':'Сборы уточняются при выборе'))}</span></div><details class="offer-filter-disclosure" ${innerWidth>760?'open':''}><summary><span>Уточнить варианты <span id="offer-local-filter-count"></span></span><small id="offer-filter-summary"></small></summary><div class="offer-controls"><label class="offer-departure-filter">Дата вылета<select id="offer-departure"><option value="">Все даты</option></select></label><label>Перелёт<select id="offer-flight"><option value="">Любой</option></select></label><label>Номер<select id="offer-room"><option value="">Любой</option></select></label><label>Питание<select id="offer-meal"><option value="">Любое</option></select></label></div></details><div id="offer-local-selected" class="offer-local-selected" aria-label="Условия выбора тура" hidden></div><div class="offer-list-toolbar"><span id="offer-count" aria-live="polite" tabindex="-1"></span><label id="offer-sort-label"><span class="sr-only">Сортировать туры</span><select id="offer-sort"><option value="price">Сначала дешевле</option><option value="date">По дате вылета</option></select></label></div><button class="text-button offer-reset" data-action="reset-offer-filters" hidden>Сбросить фильтры туров</button><div id="all-offers-list"></div>`;
}
 return {renderOfferList:reset=>{const inventory=offerListInventory();if(!$('#offer-count'))mountOfferList(inventory);renderOfferList(reset,inventory);},renderMoreGroup};
}
window.AnyTourOfferList={create};
})();
