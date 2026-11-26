'use strict';
(() => {
const roomLayouts=new Map();
const bedroomForms={one:'спальня',few:'спальни',many:'спален',other:'спальни'};
const bedroomCountRules=new Intl.PluralRules('ru');
function create(context){
const {$,$$,data,hotels,modalType,plainHotelText,esc,hotelOffers,mealLabel,displayMealLabel=mealLabel,roomLabel=o=>o.room||'Номер уточняется',dateText,nightsText,flightLabel,guestsText,money,icon,offerCountText,cardPriceNote,sameSelectedTourConditions,selectedPriceStatus,needsRefresh,operatorBadge,syncHotelOfferSelection,observeHotelRoomChoices,syncHotelSectionNavigation,rememberUIRoute,ratingValue,ratingText,departureScopeText,durationText}=context;
function hotelTextExcerpt(text,limit){
 if(text.length<=limit)return {start:text,rest:''};
 const space=text.lastIndexOf(' ',limit),cut=space>limit/2?space:limit;
 return {start:text.slice(0,cut),rest:text.slice(cut)};
}
function hotelDescriptionHTML(text){
 if(!text)return '';
 if(text.length<=360)return `<p class="hotel-detail-copy hotel-description-copy">${esc(text)}</p>`;
 const {start,rest}=hotelTextExcerpt(text,280);
 return `<div class="hotel-description-preview"><p class="hotel-detail-copy">${esc(start)}<span class="description-ellipsis" aria-hidden="true">…</span></p><details class="hotel-description"><summary><span class="description-closed">Читать полностью</span><span class="description-open">Свернуть описание</span></summary><p class="hotel-detail-copy">${esc(rest)}</p></details></div>`;
}
function hotelFactHTML(label,text,body){
 if(!text)return '';
 if(text.length<=140&&!body)return `<div class="hotel-fact-section hotel-fact-short"><h4>${esc(label)}</h4><p class="hotel-detail-copy">${esc(text)}</p></div>`;
 const {start,rest}=hotelTextExcerpt(text,110);
 return `<details class="hotel-fact-section"><summary><span class="hotel-fact-label">${esc(label)}</span><span class="hotel-fact-preview">${esc(start)}${rest?'…':''}</span></summary>${body||`<p class="hotel-detail-copy">${esc(text)}</p>`}</details>`;
}
function hotelSectionText(value){
 if(Array.isArray(value))return [...new Set(value.map(hotelSectionText).filter(Boolean))].join(' · ');
 if(value&&typeof value==='object'){
  const parts=['description','name','text','list','value']
   .filter(key=>Object.prototype.hasOwnProperty.call(value,key))
   .map(key=>hotelSectionText(value[key])).filter(Boolean);
  return [...new Set(parts)].join(' · ');
 }
 return plainHotelText(value);
}
function hotelDetailFacts(h){
 const info=h.raw?.hotelInformation||{},services=info.services||h.raw?.services||{},infrastructure=info.infrastructure||h.raw?.infrastructure||{};
 const sections=[
  ['Адрес',h.raw?.address],['Расположение',h.raw?.place],
  ['Пляж',infrastructure.beach],['Территория',infrastructure.territory],
  ['Услуги в отеле',services.available],['В номере',services.inRoom],
  ['Для детей',services.child],['Развлечения',services.animation],
  ['Бесплатные услуги',services.free],['Платные услуги',services.servicesPay],
  ['Питание в отеле',info.meals],['Номера отеля',info.roomTypes],
  ['Год постройки',h.raw?.build],['Ремонт',h.raw?.repair],['Площадь территории',h.raw?.square]
 ];
 const rendered=sections.map(([label,value])=>[label,hotelSectionText(value)]),seen=new Set(rendered.map(([,value])=>value).filter(Boolean));
 const description=plainHotelText(h.raw?.description);if(description)seen.add(description);
 for(const section of h.raw?.descriptionSections||[]){const text=plainHotelText(section?.text);if(text&&!seen.has(text)){rendered.push([plainHotelText(section.label)||'Сведения об отеле',text]);seen.add(text);}}
 return rendered.map(([label,text])=>hotelFactHTML(label,text)).join('');
}
function hotelAmenitiesHTML(h){
 const groups=new Map();
 for(const amenity of h.amenities||[]){if(!amenity.label)continue;const group=amenity.group||'Удобства';if(!groups.has(group))groups.set(group,new Set());groups.get(group).add(amenity.label);}
 return [...groups].map(([group,labels])=>hotelFactHTML(group,[...labels].join(' · '),labels.size>2?`<ul class="hotel-service-list">${[...labels].map(label=>`<li>${esc(label)}</li>`).join('')}</ul>`:null)).join('');
}
let mountedProfile='';
function hotelProfileHTML(h){
 const description=plainHotelText(h.raw?.description||h.note||''),amenities=[...new Set((h.amenities||[]).map(a=>a.label).filter(Boolean))];
 const facts=hotelDetailFacts(h)+hotelAmenitiesHTML(h);
 const visiblePhotos=h.photos.slice(0,3);
 const photos=h.photos.length?`<section class="hotel-photo-section" aria-label="Фотографии"><h3 id="hotel-photos-heading" class="sr-only hotel-section-anchor" tabindex="-1">Фотографии отеля</h3><div class="hotel-detail-photos photo-count-${visiblePhotos.length}">${visiblePhotos.map((p,i)=>`<button class="hotel-profile-photo" data-photo-state="loading" data-action="hotel-gallery" data-id="${h.id}" data-value="${i}" aria-label="Открыть фото ${i+1} отеля ${esc(h.name)}"><img src="${esc(p)}" alt="" loading="${i===0?'eager':'lazy'}"><span class="hotel-profile-photo-status" aria-hidden="true">${icon('image')} <span>Загружаем фото</span></span></button>`).join('')}</div><button class="secondary hotel-all-photos" data-action="hotel-gallery" data-id="${h.id}" data-value="0">${icon('image')} Все фотографии · ${h.photos.length}</button></section>`:'<p class="tour-missing">Фотографии отеля пока не предоставлены.</p>';
 const rating=ratingValue(h),hasOverview=Boolean(h.photos.length||description||amenities.length||facts);
 const overview=hasOverview?`${photos}<div class="hotel-detail-heading"><h3 id="hotel-about-heading" class="hotel-section-anchor" tabindex="-1">Об отеле</h3>${ratingValue(h)!==null?`<span class="detail-rating">${ratingText(h)} / 5</span>`:''}</div>
 ${amenities.length?`<ul class="hotel-key-facts">${amenities.slice(0,8).map(label=>`<li>${icon('check')}${esc(label)}</li>`).join('')}</ul>`:''}
 ${hotelDescriptionHTML(description)||(!amenities.length&&!facts?'<p class="tour-missing">Подробное описание пока не предоставлено.</p>':'')}`:`<div class="hotel-data-note"><span>Фото и описание отеля пока не предоставлены. Ниже — доступные туры с исходными условиями.</span>${rating!==null?`<strong>Оценка гостей ${ratingText(h)} / 5</strong>`:''}</div>`;
 const sections=[...(h.photos.length?[['hotel-photos-heading','Фото']]:[]),...(hasOverview?[['hotel-about-heading','Об отеле']]:[]),...(facts?[['hotel-services-heading','Услуги']]:[]),['hotel-rooms-heading','Номера и цены']];
 return `
 <nav class="hotel-section-nav" data-hotel-id="${h.id}" aria-label="Разделы отеля">${sections.map(([target,label])=>`<button type="button" data-action="hotel-section" data-target="${target}">${label}</button>`).join('')}</nav>
 ${overview}
 ${facts?`<section class="hotel-information-sections" aria-labelledby="hotel-services-heading"><h3 id="hotel-services-heading" class="detail-section-title hotel-section-anchor" tabindex="-1">Услуги и инфраструктура</h3>${facts}</section>`:''}`;
}
function refreshHotelProfile(h,entryScroll){
 const markup=hotelProfileHTML(h);if(markup===mountedProfile)return;
 const body=$('#modal-body'),rooms=body.querySelector('.hotel-room-section'),nav=body.querySelector('.hotel-section-nav');
 if(!rooms||Number(nav?.dataset.hotelId)!==h.id)return;
 const doc=body.ownerDocument,focused=doc.activeElement,inProfile=focused!==body&&body.contains(focused)&&!rooms.contains(focused);
 const section=nav.querySelector('[aria-current="location"]')?.dataset.target,scroll=body.scrollTop,navScroll=nav.scrollLeft;
 const roomRect=rooms.getBoundingClientRect(),viewport=body.getBoundingClientRect(),roomVisible=roomRect.top<viewport.bottom&&roomRect.bottom>viewport.top+nav.offsetHeight;
 const keepRoom=roomVisible&&(rooms.contains(focused)||!inProfile&&(entryScroll===scroll||scroll>0&&section==='hotel-rooms-heading'));
 const anchor=keepRoom?rooms:scroll>0&&section?doc.getElementById(section):null,top=anchor?.getBoundingClientRect().top;
 const detailKey=d=>d.classList.contains('hotel-description')?'description':'fact:'+d.querySelector('.hotel-fact-label')?.textContent;
 const controlKey=el=>el.id||el.dataset.target||el.querySelector('img')?.getAttribute('src')||el.dataset.action||(el.closest('details')?detailKey(el.closest('details')):'');
 const focusedKey=inProfile?controlKey(focused):'';
 const template=doc.createElement('template');template.innerHTML=markup;
 const open=new Set([...body.querySelectorAll('.hotel-description[open],.hotel-fact-section[open]')].map(detailKey));
 for(const details of template.content.querySelectorAll('details'))details.open=open.has(detailKey(details));
 // Change only the profile above the rooms. A selected tariff, its controls and the footer stay mounted.
 const range=doc.createRange();range.setStart(body,0);range.setEndBefore(rooms);range.deleteContents();range.insertNode(template.content);
 mountedProfile=markup;
 body.querySelector('.hotel-section-nav').scrollLeft=navScroll;
 if(inProfile){
  const target=[...body.querySelectorAll('button,summary,[tabindex]')].find(el=>!rooms.contains(el)&&controlKey(el)===focusedKey)||body.querySelector('.hotel-section-nav button');
  target?.focus({preventScroll:true});
 }
 const nextAnchor=anchor===rooms?rooms:anchor?.id?doc.getElementById(anchor.id):null;
 if(nextAnchor)body.scrollTop+=nextAnchor.getBoundingClientRect().top-top;
 else if(body.scrollTop!==scroll)body.scrollTop=scroll;
 syncHotelSectionNavigation();
}
function roomLayoutId(offer){
 if(typeof data?.readRoomLayouts!=='function'||offer?.provider!=='tourvisor')return null;
 const rawId=offer.raw?.roomId;
 const id=typeof rawId==='string'&&/^[1-9]\d*$/.test(rawId)?Number(rawId):rawId;
 return Number.isSafeInteger(id)&&id>0?id:null;
}
function roomLayoutNote(id){
 return id?'<span class="room-layout-note" data-room-layout-id="'+id+'" tabindex="-1" hidden></span>':'';
}
function roomLayoutTargets(section){
 return [...section.querySelectorAll('[data-room-layout-id]')];
}
function paintRoomLayouts(section){
 const control=section?.querySelector('.room-layout-controls');
 if(!control)return;
 const button=control.querySelector('button'),status=control.querySelector('[role="status"]');
 const targets=roomLayoutTargets(section);
 let hasFact=false,hasUnloaded=false;
 for(const target of targets){
  const id=Number(target.dataset.roomLayoutId),layout=roomLayouts.get(id)||'';
  if(target.textContent!==layout)target.textContent=layout;
  target.hidden=!layout;
  hasFact||=Boolean(layout);
  hasUnloaded||=!roomLayouts.has(id);
 }
 if(!button.disabled){
  button.hidden=!hasUnloaded;
  if(!hasUnloaded||!status.dataset.failed){
   delete status.dataset.failed;
   status.textContent=targets.length&&!hasUnloaded&&!hasFact?'Планировка этих номеров не указана.':'';
  }
 }
 if(!targets.length)status.textContent='';
 status.hidden=!status.textContent;
 control.hidden=button.hidden&&status.hidden;
}
async function loadRoomLayouts(section,button){
 if(button.disabled)return;
 const ids=[...new Set(roomLayoutTargets(section).map(node=>Number(node.dataset.roomLayoutId)))].filter(id=>!roomLayouts.has(id)).slice(0,30);
 if(!ids.length)return;
 const status=button.parentElement.querySelector('[role="status"]');
 delete status.dataset.failed;
 button.disabled=true;
 status.textContent='Загружаем планировки…';
 status.hidden=false;
 try{
  const layouts=await data.readRoomLayouts(ids);
  const labels=new Map(layouts.map(({id,bedroomCount,sleepingPlaces})=>{
   const bedrooms=bedroomCount?bedroomCount+' '+bedroomForms[bedroomCountRules.select(bedroomCount)]:'';
   return [id,[bedrooms,plainHotelText(sleepingPlaces)].filter(Boolean).join(' · ')];
  }));
  ids.forEach(id=>roomLayouts.set(id,labels.get(id)||''));
 }catch{
  status.dataset.failed='true';
  status.textContent='Планировки временно недоступны. Попробуйте ещё раз.';
 }finally{
  button.disabled=false;
  if(section.isConnected){
   const restoreFocus=button===section.ownerDocument.activeElement;
   paintRoomLayouts(section);
   if(restoreFocus&&button.hidden){
    const shown=roomLayoutTargets(section).find(node=>!node.hidden&&node.getClientRects().length);
    (shown||status).focus({preventScroll:true});
   }
  }
 }
}
function roomOfferChoiceHTML(o,{common={},sharedDemoNote='',minimum=null,priceInfo=roomOfferPrice(o),layoutId=null}={}){
 const selected=context.hotelOfferDraft?.hotelId===o.hotelId&&context.hotelOfferDraft?.key===o.key;
 const meal=esc(displayMealLabel(o)),key=esc(o.key),price=priceInfo.pending?'Цена уточняется':money(priceInfo.total);
 const description=esc(roomOfferDescription(o,price));
 const note=priceInfo.note;
 const facts=[
  !common.dates&&`<div><dt>Даты</dt><dd>${roomOfferDates(o)}</dd></div>`,
  !common.flight&&`<div><dt>Перелёт</dt><dd>${esc(flightLabel(o)||'Уточняется при выборе')}</dd></div>`,
  !common.operator&&`<div class="room-offer-operator"><dt>Туроператор</dt><dd>${operatorBadge(o.operator)}</dd></div>`
 ].filter(Boolean).join('');
 return `<div class="room-offer-choice${selected?' selected':''}" data-offer-key="${key}" ${selected?'aria-current="true"':''}>
  <div class="room-offer-conditions">
   <button type="button" class="room-tariff-details text-button" data-action="offer" data-key="${key}" aria-label="Подробности тура: ${description}"><strong class="room-offer-meal">${meal}</strong><span aria-hidden="true">›</span></button>
   ${layoutId?roomLayoutNote(layoutId):''}<span class="room-choice-selected" ${selected?'':'hidden'}></span>${facts?`<dl class="room-choice-facts">${facts}</dl>`:''}
  </div>
  <div class="hotel-room-price" ${selected?'hidden':''}>
   <strong>${price}</strong>${common.party?'':`<small class="room-offer-party">${esc(guestsText(o))}</small>`}
   <button type="button" class="${selected?'secondary':'primary'}" data-action="select-hotel-offer" data-key="${key}" aria-pressed="${selected}" aria-label="Проверить цену: ${description}">${selected?'✓ Ваш выбранный тур':'Проверить цену'}</button>
   ${roomPriceDifferenceHTML(priceInfo.total,minimum)}
   ${note&&note!==sharedDemoNote?`<small class="room-offer-price-status">${esc(note)}</small>`:''}
  </div></div>`;
}
function roomOfferDescription(o,price){
 const duration=`${dateText(o.day)} → ${dateText(o.returnDay)} · ${nightsText(o.nights)}`;
 return [o.room,displayMealLabel(o),duration,flightLabel(o)||'Перелёт уточняется',o.operator,price].join(', ');
}
function roomOfferPrice(listing){
 const current=context.selectedOffer,listingNote=cardPriceNote(listing);
 const price={total:listing.total,note:listingNote,basis:listing.pricePending?null:listingNote,pending:!!listing.pricePending};
 if(!current||current.key!==listing.key||current.raw!==listing.raw||!sameSelectedTourConditions(listing,current)||
  (current.quoteListingTotal||current.total)!==listing.total)return price;
 if(current.pricePending)return {...price,pending:true,basis:null};
 if(current.loading||current.flightsLoading||current.quoteError)return {...price,basis:null};
 if(['anex','andromeda'].includes(current.provider))return {...price,basis:null};
 const note=selectedPriceStatus(current);
 // A recorded or demonstration amount stays on the same basis after choosing its flight.
 const offline=current.provider==='recorded'||current.provider==='fixture'&&!current.raw?.prototypeConditions;
 const basis=offline||!current.tour?price.basis:note;
 return {total:current.total,note,basis:current.priceAcceptancePending?null:basis};
}
function roomPriceDifference(total,minimum){
 return minimum!=null&&total>minimum?`На ${money(total-minimum)} выше минимума`:'';
}
function roomPriceDifferenceHTML(total,minimum){
 const text=roomPriceDifference(total,minimum);
 return text?`<small class="room-price-difference">${text}</small>`:'';
}
function roomPriceComparison(offers){
 const prices=new Map(offers.map(o=>[o.key,{...roomOfferPrice(o),offer:o}]));
 const first=offers[0],partyAges=JSON.stringify(first?.ages),basis=prices.get(first?.key)?.basis;
 const comparable=offers.length>1&&basis!=null&&offers.every(o=>{
  const price=prices.get(o.key);
  return Number.isFinite(price.total)&&price.total>0&&price.basis===basis&&
   o.adults===first.adults&&JSON.stringify(o.ages)===partyAges;
 });
 let lowest=null;
 for(const price of prices.values())if(!price.pending&&Number.isFinite(price.total)&&price.total>0&&(!lowest||price.total<lowest.total))lowest=price;
 const minimum=comparable?lowest.total:null;
 const sharedDemoNote=offers.length>1&&offers.every(o=>o.provider==='fixture')?cardPriceNote(first):'';
 return {prices,minimum,sharedDemoNote,lowest};
}
function updatePriceNote(parent,className,text,before=null){
 const old=parent.querySelector('.'+className);
 if(!text){old?.remove();return;}
 if(old){
  if(old.textContent!==text)old.textContent=text;
 }else{
  const note=parent.ownerDocument.createElement('small');
  note.className=className;note.textContent=text;parent.insertBefore(note,before);
 }
}
function syncRoomPriceComparison(cards,offers,comparison=roomPriceComparison(offers)){
 const {prices,minimum,sharedDemoNote}=comparison;
 for(const card of cards.querySelectorAll('.hotel-room-card')){
  let groupMinimum=Infinity;
  for(const row of card.querySelectorAll('.room-offer-choice')){
   const price=prices.get(row.dataset.offerKey);if(!price)continue;
   groupMinimum=Math.min(groupMinimum,price.pending?NaN:price.total);
   const panel=row.querySelector('.hotel-room-price'),amount=panel.querySelector('strong');
   const copy=price.pending?'Цена уточняется':money(price.total);
   if(amount.textContent!==copy)amount.textContent=copy;
   const description=roomOfferDescription(price.offer,copy),details=row.querySelector('[data-action="offer"]'),choose=row.querySelector('[data-action="select-hotel-offer"]');
   if(details.getAttribute('aria-label')!=='Подробности тура: '+description)details.setAttribute('aria-label','Подробности тура: '+description);
   if(choose.getAttribute('aria-label')!=='Проверить цену: '+description)choose.setAttribute('aria-label','Проверить цену: '+description);
   updatePriceNote(panel,'room-offer-price-status',price.note===sharedDemoNote?'':price.note);
   updatePriceNote(panel,'room-price-difference',roomPriceDifference(price.total,minimum),panel.querySelector('.room-offer-price-status'));
  }
  const group=card.querySelector('.room-overview-bottom');
  if(group){
   const label=group.querySelector('.room-overview-price'),copy=Number.isFinite(groupMinimum)?'от '+money(groupMinimum):'Цена уточняется';
   if(label.textContent!==copy)label.textContent=copy;
   updatePriceNote(group,'room-price-difference',roomPriceDifference(groupMinimum,minimum),group.querySelector('.room-overview-action'));
  }
 }
 return comparison;
}
function roomOfferDates(o){
 return `<time datetime="${esc(o.day)}">${dateText(o.day)}</time> → <time datetime="${esc(o.returnDay)}">${dateText(o.returnDay)}</time> · ${nightsText(o.nights)}`;
}
function commonRoomTerms(rows){
 if(rows.length<2)return {};
 const first=rows[0];
 return {
  dates:rows.every(o=>o.day===first.day&&o.returnDay===first.returnDay&&o.nights===first.nights),
  flight:rows.every(o=>o.flight===first.flight),
  operator:rows.every(o=>o.operator===first.operator)
 };
}
function roomSharedFacts(first,terms,inventoryTerms=null,demoNote=''){
 if(!first)return '';
 const facts=[
  terms.dates&&!inventoryTerms?.dates&&roomOfferDates(first),
  terms.flight&&!inventoryTerms?.flight&&esc(flightLabel(first)||'Перелёт уточняется'),
  terms.operator&&!inventoryTerms?.operator&&operatorBadge(first.operator),
  demoNote&&esc(demoNote)
 ].filter(Boolean);
 return facts.length?`<div class="room-shared-facts${inventoryTerms?'':' room-inventory-facts'}" aria-label="Общие условия вариантов">${facts.map(fact=>`<span>${fact}</span>`).join('')}</div>`:'';
}
function renderHotelRooms(id,meal='',restoredRooms=null,initialOffers=null){
 const h=hotels.find(h=>h.id===id);if(!h||modalType!=='hotel-details')return;
 const allOffers=initialOffers||hotelOffers(h),meals=[...new Set(allOffers.map(o=>o.meal))];
 let mealControl=$('#hotel-room-meal');
 if(!mealControl&&meals.length>1&&allOffers.length>2){$('#hotel-room-count').insertAdjacentHTML('beforebegin',`<label class="hotel-room-meal-filter">Питание в туре<select id="hotel-room-meal" data-id="${h.id}"></select></label>`);mealControl=$('#hotel-room-meal');}
 if(mealControl){const values=['',...meals];if(meal&&!values.includes(meal))values.push(meal);if(JSON.stringify([...mealControl.options].map(o=>o.value))!==JSON.stringify(values))mealControl.innerHTML=values.map(value=>`<option value="${esc(value)}">${esc(value?displayMealLabel({meal:value}):'Любое питание')}</option>`).join('');}
 const offers=allOffers.filter(o=>!meal||o.meal===meal),rooms=[],byRoom=new Map();
 if(context.hotelOfferDraft?.hotelId===id&&!offers.some(o=>o.key===context.hotelOfferDraft.key))context.hotelOfferDraft=null;
 if(offers.length===1&&!context.hotelOfferDraft)context.hotelOfferDraft={hotelId:id,key:offers[0].key};
 // Set merges signed zero; strict equality leaves the NaN group empty.
 offers.forEach(o=>{
  const room=o.room;let group=byRoom.get(room);
  if(!group){group={room:room===0?0:room,offers:[]};byRoom.set(room,group);rooms.push(group);}
  if(room===room)group.offers.push(o);
 });
 $('#hotel-room-count').textContent=`Номера: ${rooms.length} · Туры: ${offers.length}`;
 const roomCards=$('.hotel-room-cards');roomCards.classList.toggle('single-direct-offer',offers.length===1);
 const inventoryTerms=commonRoomTerms(offers),searchParty=guestsText();
 const comparison=roomPriceComparison(offers),{prices,minimum,sharedDemoNote}=comparison;
 const inventoryFacts=roomSharedFacts(offers[0],inventoryTerms,null,sharedDemoNote);
 roomCards.innerHTML=inventoryFacts+rooms.map(({room,offers:rows})=>{
  const roomTerms=commonRoomTerms(rows);
  const common={dates:inventoryTerms.dates||roomTerms.dates,flight:inventoryTerms.flight||roomTerms.flight,operator:inventoryTerms.operator||roomTerms.operator};
  const firstLayoutId=roomLayoutId(rows[0]);
  const sharedLayoutId=firstLayoutId&&rows.every(o=>roomLayoutId(o)===firstLayoutId)?firstLayoutId:null;
  const choice=o=>roomOfferChoiceHTML(o,{common:{...common,party:guestsText(o)===searchParty},sharedDemoNote,minimum,priceInfo:prices.get(o.key),layoutId:sharedLayoutId?null:roomLayoutId(o)});
  const choices=roomSharedFacts(rows[0],roomTerms,inventoryTerms)+`<div class="room-offer-list">${rows.slice(0,2).map(choice).join('')}${rows.length>2?`<details class="hotel-room-more"><summary><span class="room-more-closed">Ещё ${offerCountText(rows.length-2)}</span><span class="room-more-open">Скрыть остальные туры</span></summary>${rows.slice(2).map(choice).join('')}</details>`:''}</div>`;
  const title=`<h4>${esc(roomLabel({room}))}</h4>`;
  const layoutNote=roomLayoutNote(sharedLayoutId),header=layoutNote?'<div>'+title+layoutNote+'</div>':title;
  if(rooms.length===1||rows.length===1)return `<article class="hotel-room-card" data-room="${esc(room)}"><header class="room-choice-main">${header}<span class="room-choice-label">${offerCountText(rows.length)}</span></header>${choices}</article>`;
  const meals=[...new Set(rows.map(displayMealLabel))].map(esc).join(' · '),min=Math.min(...rows.map(o=>{const p=prices.get(o.key);return p.pending?NaN:p.total;}));
  return `<details class="hotel-room-card room-overview" data-room="${esc(room)}" ${restoredRooms?.includes(room)?'open':''}><summary class="room-overview-toggle"><span class="room-overview-name">${title}${layoutNote}<span class="room-overview-meals">${meals}</span></span><span class="room-overview-bottom"><strong class="room-overview-price">${Number.isFinite(min)?'от '+money(min):'Цена уточняется'}</strong>${roomPriceDifferenceHTML(min,minimum)}<span class="room-overview-action"><span class="room-overview-closed">${offerCountText(rows.length)}</span><span class="room-overview-open">Свернуть</span><span class="rotate-arrow" aria-hidden="true">⌄</span></span></span></summary>${choices}</details>`;
 }).join('')||(meal&&allOffers.length?'<p class="tour-missing">С выбранным питанием сейчас нет подходящих вариантов в этом отеле.</p>':'<p class="tour-missing">По текущим условиям предложений нет. Измените даты или фильтры поиска.</p>');
 const control=$('#hotel-room-meal');if(control){control.value=meal;[...control.options].forEach(option=>option.defaultSelected=option.value===meal);}
 if(typeof data?.readRoomLayouts==='function')paintRoomLayouts(roomCards.closest('.hotel-room-section'));
 syncHotelOfferSelection(id,allOffers,{meal,comparison});
 queueMicrotask(()=>{observeHotelRoomChoices();syncHotelSectionNavigation();});
 rememberUIRoute();
 return {hotel:h,offers:allOffers};
}
function openHotelDetails(id){
 const h=hotels.find(h=>h.id===id);if(!h)return;
 const offers=hotelOffers(h),meals=[...new Set(offers.map(o=>o.meal))];
 mountedProfile=hotelProfileHTML(h);
 $('#modal-body').innerHTML=`${mountedProfile}
 <section class="hotel-room-section" aria-labelledby="hotel-rooms-heading"><h3 id="hotel-rooms-heading" class="detail-section-title hotel-section-anchor" tabindex="-1">Номера и питание</h3><p class="hotel-room-context">${departureScopeText()} · ${durationText()} · ${guestsText()}</p>
 ${meals.length>1&&offers.length>2?`<label class="hotel-room-meal-filter">Питание в туре<select id="hotel-room-meal" data-id="${h.id}"><option value="">Любое питание</option>${meals.map(meal=>`<option value="${esc(meal)}">${esc(displayMealLabel({meal}))}</option>`).join('')}</select></label>`:''}
 <p id="hotel-room-count" class="hotel-room-count" role="status" aria-live="polite"></p>${typeof data?.readRoomLayouts==='function'?'<div class="room-layout-controls" hidden><button class="secondary" type="button">Показать планировки</button><span role="status" tabindex="-1" hidden></span></div>':''}<div class="hotel-room-cards"></div></section>`;
 const layoutControl=$('.room-layout-controls button');
 if(layoutControl)layoutControl.addEventListener('click',()=>loadRoomLayouts(layoutControl.closest('.hotel-room-section'),layoutControl));
 $('#modal').classList.add('hotel-details-dialog');
 if(offers.length){$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total"><span>Весь тур · ${guestsText()}</span><strong id="hotel-detail-min"></strong><small id="hotel-detail-price-status"></small></div><button id="hotel-detail-offers" class="primary" data-action="hotel-detail-offers" data-id="${id}"></button>`;}
 renderHotelRooms(id,'',null,offers);
 return offers;
}
return {openHotelDetails,refreshHotelProfile,renderHotelRooms,syncRoomPriceComparison};
}
window.AnyTourHotelDetails=Object.freeze({create});
})();
