// Cold presentation owner. The application supplies its current view and helpers.
(function(){
'use strict';
function create(context){
 const {$,$$,selectedOffer,sameSelectedTourConditions,data,appendGeneratedRoots,byId,cardPriceNote,dateText,durationText,mealLabel,displayMealLabel=mealLabel,esc,flightLabel,guestsText,hotelOffers,hotels,icon,innerWidth,mealNames,money,needsRefresh,nightsText,offerActionLabel,offerCountText,offerGroupKey,offerMetaNote,offerRefinementFields,offerSearchContext,offerView,operatorBadge,paintGeneratedRoots,rangeText,rememberUIRoute,roomLabel=o=>o.room||'Номер уточняется',sharedOfferNote,selectionStepsHTML}=context;
const offerRefinementLabels={departure:'Дата',flight:'Перелёт',room:'Номер',meal:'Питание'};
const offerRefinementAny={departure:'Любая дата',flight:'Любой перелёт',room:'Любой номер',meal:'Любое питание'};
let currentRefinementInventory;
let currentInventory,currentCommonNote='',currentRowEntries=new Map();
function matchesOfferRefinements(o,view=offerView){return offerRefinementFields.every(field=>!view[field]||(field==='departure'?o.day:o[field])===view[field]);}
function offerRefinementLabel(field,value){return field==='departure'?dateText(value):field==='flight'?flightLabel({flight:value}):field==='meal'?displayMealLabel({meal:value}):field==='room'?roomLabel({room:value}):value;}
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
  const counts=currentRefinementInventory.get(field).counts;
  for(const option of select.options){
   if(!option.dataset.baseLabel)option.dataset.baseLabel=option.textContent;
   option.textContent=`${option.dataset.baseLabel} · ${offerCountText(counts.get(option.value)||0)}`;
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
function offerListInventory(precomputed){
 const h=hotels.find(h=>h.id===offerView.id),all=Array.isArray(precomputed)?precomputed:hotelOffers(h),filtered=all.filter(o=>(!offerView.departure||o.day===offerView.departure)&&(!offerView.flight||o.flight===offerView.flight)&&(!offerView.room||o.room===offerView.room)&&(!offerView.meal||o.meal===offerView.meal));
 const keyed=filtered.length<2?filtered.map(offer=>({offer})):filtered.map((offer,index)=>({offer,index,total:offer.total,day:offer.day}));
 keyed.sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total||a.index-b.index:a.total-b.total||a.day.localeCompare(b.day)||a.index-b.index);
 const sorted=keyed.map(item=>item.offer);
 const groups=[],byKey=new Map();
 for(const offer of sorted){
  const key=offerGroupKey(offer);let group=byKey.get(key);
  if(!group){group={key,offers:[]};byKey.set(key,group);groups.push(group);}
  group.offers.push(offer);
 }
 return {h,all,filtered,groups,sorted};
}
function offerRefinementInventory(all){
 const inventory=new Map(offerRefinementFields.map(field=>[field,{values:[],seen:new Set(),counts:new Map([['',0]])}])),length=all.length;
 for(let i=0;i<length;i++){
  if(!(i in all)){for(const entry of inventory.values())if(!entry.seen.has(undefined)){entry.seen.add(undefined);entry.values.push(undefined);entry.counts.set(undefined,0);}continue;}
  const offer=all[i],values=offerRefinementFields.map(field=>field==='departure'?offer.day:offer[field]);let mismatches=0,mismatch=-1;
  for(let index=0;index<offerRefinementFields.length;index++){
   const selected=offerView[offerRefinementFields[index]];if(selected&&values[index]!==selected){mismatches++;mismatch=index;}
  }
  for(let index=0;index<offerRefinementFields.length;index++){
   const entry=inventory.get(offerRefinementFields[index]),value=values[index];
   if(!entry.seen.has(value)){entry.seen.add(value);entry.values.push(value);if(value!==''&&!entry.counts.has(value))entry.counts.set(value,0);}
   if(mismatches===0||(mismatches===1&&mismatch===index)){
    entry.counts.set('',entry.counts.get('')+1);
    if(value!=='')entry.counts.set(value,(entry.counts.get(value)||0)+1);
   }
  }
 }
 for(const field of offerRefinementFields){const entry=inventory.get(field),selected=offerView[field];if(selected&&!entry.counts.has(selected))entry.counts.set(selected,0);entry.hasChoice=entry.values.length>1;delete entry.seen;}
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
function offerRowEntry(listing,commonNote){
 const chosen=selectedOffer?.key===listing.key&&sameSelectedTourConditions(listing,selectedOffer)&&(selectedOffer.quoteListingTotal||selectedOffer.total)===listing.total&&!selectedOffer.loading&&!selectedOffer.flightsLoading&&!selectedOffer.pricePending,o=chosen?selectedOffer:listing,actionLabel=chosen?'Смотреть тур':offerActionLabel(o);
 return {offerKey:o.key,markup:`<article class="offer grouped-offer${chosen?' is-selected':''}" data-offer-key="${esc(o.key)}" aria-label="${esc(o.operator)}, ${esc(roomLabel(o))}, ${dateText(o.day)}${chosen?', выбранный тур':''}"><div class="offer-departure"><strong>${dateText(o.day)} → ${dateText(o.returnDay)} · ${nightsText(o.nights)}</strong>${chosen?'<span class="offer-selected" role="status">✓ Выбран</span>':''}</div><div class="offer-conditions"><p>${icon('food')}${esc(displayMealLabel(o))}</p><p><span class="card-room-label">Номер</span> ${esc(roomLabel(o))}</p></div><div class="offer-flight-details">${flightLabel(o)?`<span class="flight-tag ${o.flight}">${flightLabel(o)}</span>`:''}${operatorBadge(o.operator)}${commonNote?'':`<small>${esc(offerMetaNote(o))}</small>`}</div><div class="offer-price"><strong aria-label="${esc(money(o.total))} за всех туристов">${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${esc(o.key)}" aria-label="${actionLabel}: ${esc(roomLabel(o))}, ${esc(displayMealLabel(o))}, ${dateText(o.day)}, ${esc(o.operator)}, ${money(o.total)}">${actionLabel} ${icon('arrow')}</button></div></article>`};
}
function offerMoreEntry(key,total,limit){return total>limit?{offerKey:'',markup:`<button class="text-button group-more" data-action="group-more" data-value="${key}">Ещё варианты (${total-limit}) ${icon('arrow')}</button>`}:null;}
function renderOfferList(reset=false,inventory=offerListInventory(),reuseRows=false){
 const {h,all,filtered,groups,sorted}=inventory;
 currentInventory=inventory;
 if(!reuseRows){currentRowEntries=new Map();
 currentRefinementInventory=offerRefinementInventory(all);
 for(const field of offerRefinementFields){
  const select=$('#offer-'+field),values=[...currentRefinementInventory.get(field).values].filter(value=>field!=='flight'||flightLabel({flight:value}));
  if(offerView[field]&&!values.includes(offerView[field]))values.push(offerView[field]);
  if(field==='departure')values.sort();
  if(JSON.stringify([...select.options].map(o=>o.value))!==JSON.stringify(['',...values]))select.innerHTML=`<option value="">${field==='departure'?'Все даты':field==='meal'?'Любое':'Любой'}</option>`+values.map(value=>`<option value="${esc(value)}">${esc(offerRefinementLabel(field,value))}</option>`).join('');
 }
 if(reset){offerView.open=groups.length===1?[groups[0].key]:[];offerView.limits={};}
 for(const name of ['departure','flight','room','meal','sort'])$('#offer-'+name).value=offerView[name];
 $('#offer-sort-label').hidden=new Set(filtered.map(o=>o.day)).size<2;
 $('.offer-departure-filter').hidden=false;const localCount=['departure','flight','room','meal'].filter(k=>offerView[k]).length;$('.offer-reset').hidden=localCount<2||!filtered.length;$('#offer-local-filter-count').textContent=localCount?'('+localCount+')':'';$('#offer-filter-summary').textContent=[offerView.departure?dateText(offerView.departure):'',offerView.flight?flightLabel({flight:offerView.flight}):'',offerView.room?roomLabel({room:offerView.room}):'',offerView.meal?mealNames[offerView.meal]||displayMealLabel({meal:offerView.meal}):''].filter(Boolean).join(' · ')||'Дата, перелёт, номер и питание';$('.offer-list-context p').textContent=`${h.name} · ${offerSearchContext()} · ${durationText()} · ${guestsText()}`;
 renderOfferRefinements(all);
 $('#modal-footer').hidden=true;$('#modal-footer').innerHTML='';rememberUIRoute();
 $('#offer-count').textContent=offerCountText(filtered.length);
 }
 const commonNote=reuseRows?currentCommonNote:groups.length?sharedOfferNote(all):'';
 currentCommonNote=commonNote;
 const visibleKeys=new Set(groups.flatMap(({key,offers})=>offers.slice(0,offerView.limits[key]||4).map(o=>o.key)));
 const entries=sorted.filter(o=>visibleKeys.has(o.key)).map(o=>{let entry=currentRowEntries.get(o.key);if(!entry){entry=offerRowEntry(o,commonNote);currentRowEntries.set(o.key,entry);}return entry;});
 for(const group of groups){const limit=offerView.limits[group.key]||4;if(group.offers.length>limit)entries.push({offerKey:'more-'+group.key,markup:`<button class="text-button group-more" data-action="group-more" data-value="${esc(group.key)}">Ещё варианты: ${esc(roomLabel(group.offers[0]))}, ${esc(displayMealLabel(group.offers[0]))} (${group.offers.length-limit}) ${icon('arrow')}</button>`});}
 const host=$('#all-offers-list');if(entries.length)paintGeneratedRoots(host,entries,true,'offerKey');else host.innerHTML=`<div class="destination-empty offer-recovery-empty"><h3>Нет такого сочетания</h3>${offerRefinementRecovery(all)}<button class="text-button" data-action="reset-offer-filters">Сбросить все условия выбора тура</button></div>`;
}
function renderMoreGroup(key,shown,remove){const group=currentInventory?.groups.find(group=>group.key===key);if(!group||shown<0||shown>group.offers.length)return false;renderOfferList(false,currentInventory,true);rememberUIRoute();return true;}
function renderOfferGroup(){return false;}
function mountOfferList({h,all}){
 $('#modal-body').innerHTML=`${all.some(o=>!needsRefresh(o))?selectionStepsHTML(0):''}<div class="offer-list-context"><p>${esc(h.name)} · ${offerSearchContext()} · ${durationText()} · ${guestsText()}</p><span>${icon('info')} Все цены за всех туристов. ${esc(sharedOfferNote(all)||(all.every(needsRefresh)?'Цены и наличие требуют проверки':'Сборы уточняются при выборе'))}</span></div><details class="offer-filter-disclosure" ${innerWidth>760?'open':''}><summary><span>Уточнить варианты <span id="offer-local-filter-count"></span></span><small id="offer-filter-summary"></small></summary><div class="offer-controls"><label class="offer-departure-filter">Дата вылета<select id="offer-departure"><option value="">Все даты</option></select></label><label>Перелёт<select id="offer-flight"><option value="">Любой</option></select></label><label>Номер<select id="offer-room"><option value="">Любой</option></select></label><label>Питание<select id="offer-meal"><option value="">Любое</option></select></label></div></details><div id="offer-local-selected" class="offer-local-selected" aria-label="Условия выбора тура" hidden></div><div class="offer-list-toolbar"><span id="offer-count" aria-live="polite" tabindex="-1"></span><label id="offer-sort-label"><span class="sr-only">Сортировать туры</span><select id="offer-sort"><option value="price">Сначала дешевле</option><option value="date">По дате вылета</option></select></label></div><button class="text-button offer-reset" data-action="reset-offer-filters" hidden>Сбросить фильтры туров</button><div id="all-offers-list"></div>`;
}
 return {renderOfferList:(reset,precomputed)=>{const inventory=offerListInventory(precomputed);if(!$('#offer-count'))mountOfferList(inventory);renderOfferList(reset,inventory);},renderMoreGroup,renderOfferGroup};
}
window.AnyTourOfferList={create};
})();
