'use strict';
(() => {
function create(context){
const {$,$$,data,hotels,modalType,plainHotelText,esc,hotelOffers,mealLabel,dateText,nightsText,flightLabel,guestsText,money,icon,offerCountText,cardPriceNote,observeHotelRoomChoices,syncHotelSectionNavigation,rememberUIRoute,ratingValue,ratingText,departureScopeText,durationText}=context;
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
 return sections.map(([label,value])=>hotelFactHTML(label,hotelSectionText(value))).join('');
}
function hotelAmenitiesHTML(h){
 const groups=new Map();
 for(const amenity of h.amenities||[]){if(!amenity.label)continue;const group=amenity.group||'Удобства';if(!groups.has(group))groups.set(group,new Set());groups.get(group).add(amenity.label);}
 return [...groups].map(([group,labels])=>hotelFactHTML(group,[...labels].join(' · '),labels.size>2?`<ul class="hotel-service-list">${[...labels].map(label=>`<li>${esc(label)}</li>`).join('')}</ul>`:null)).join('');
}
function roomOfferChoiceHTML(o){
 return `<div class="room-offer-choice" data-offer-key="${esc(o.key)}"><div class="room-offer-conditions"><strong class="room-offer-meal">${esc(mealLabel(o))}</strong><dl class="room-choice-facts"><div><dt class="sr-only">Даты и отдых</dt><dd><time datetime="${esc(o.day)}">${dateText(o.day)}</time> → <time datetime="${esc(o.returnDay)}">${dateText(o.returnDay)}</time> · ${nightsText(o.nights)}</dd></div></dl><span class="room-offer-operator">${esc(o.operator)}${flightLabel(o)?' · '+flightLabel(o):''}</span></div><div class="hotel-room-price"><small>Весь тур · ${guestsText(o)}</small><strong>${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${esc(o.key)}" aria-label="Смотреть тур: ${esc(mealLabel(o))}, ${dateText(o.day)}, ${esc(o.operator)}, ${money(o.total)}">Смотреть тур ${icon('arrow')}</button></div></div>`;
}
function renderHotelRooms(id,meal='',restoredRooms=null,initialOffers=null){
 const h=hotels.find(h=>h.id===id);if(!h||modalType!=='hotel-details')return;
 const allOffers=initialOffers||hotelOffers(h),meals=[...new Set(allOffers.map(o=>o.meal))];
 let mealControl=$('#hotel-room-meal');
 if(!mealControl&&meals.length>1&&allOffers.length>2){$('#hotel-room-count').insertAdjacentHTML('beforebegin',`<label class="hotel-room-meal-filter">Питание в туре<select id="hotel-room-meal" data-id="${h.id}"></select></label>`);mealControl=$('#hotel-room-meal');}
 if(mealControl){const values=['',...meals];if(meal&&!values.includes(meal))values.push(meal);if(JSON.stringify([...mealControl.options].map(o=>o.value))!==JSON.stringify(values))mealControl.innerHTML=values.map(value=>`<option value="${esc(value)}">${esc(value||'Любое питание')}</option>`).join('');}
 const offers=allOffers.filter(o=>!meal||o.meal===meal),rooms=[],byRoom=new Map();
 // Set merges signed zero; strict equality leaves the NaN group empty.
 offers.forEach(o=>{
  const room=o.room;let group=byRoom.get(room);
  if(!group){group={room:room===0?0:room,offers:[]};byRoom.set(room,group);rooms.push(group);}
  if(room===room)group.offers.push(o);
 });
 $('#hotel-room-count').textContent=`Номера: ${rooms.length} · Туры: ${offers.length}`;
 const roomCards=$('.hotel-room-cards');roomCards.classList.toggle('single-direct-offer',offers.length===1);
 roomCards.innerHTML=rooms.map(({room,offers:rows})=>{
  const choices=`<div class="room-offer-list">${rows.slice(0,2).map(roomOfferChoiceHTML).join('')}${rows.length>2?`<details class="hotel-room-more"><summary><span class="room-more-closed">Ещё ${offerCountText(rows.length-2)}</span><span class="room-more-open">Скрыть остальные туры</span></summary>${rows.slice(2).map(roomOfferChoiceHTML).join('')}</details>`:''}</div>`;
  const title=`<h4>${esc(room||'Тип номера уточняется')}</h4>`;
  if(rooms.length===1)return `<article class="hotel-room-card" data-room="${esc(room)}"><header class="room-choice-main">${title}<span class="room-choice-label">${offerCountText(rows.length)}</span></header>${choices}</article>`;
  const meals=[...new Set(rows.map(mealLabel))].map(esc).join(' · '),min=Math.min(...rows.map(o=>o.total));
  return `<details class="hotel-room-card room-overview" data-room="${esc(room)}" ${restoredRooms?.includes(room)?'open':''}><summary class="room-overview-toggle"><span class="room-overview-name">${title}<span class="room-overview-meals">${meals}</span></span><span class="room-overview-bottom"><strong class="room-overview-price">от ${money(min)}</strong><span class="room-overview-action"><span class="room-overview-closed">${offerCountText(rows.length)}</span><span class="room-overview-open">Свернуть</span><span class="rotate-arrow" aria-hidden="true">⌄</span></span></span></summary>${choices}</details>`;
 }).join('')||'<p class="tour-missing">По текущим условиям предложений нет. Измените даты или фильтры поиска.</p>';
 const control=$('#hotel-room-meal');if(control){control.value=meal;[...control.options].forEach(option=>option.defaultSelected=option.value===meal);}
 const price=$('#hotel-detail-min'),button=$('#hotel-detail-offers');
 if(price)price.textContent=offers.length?(offers.length>1?'от ':'')+money(offers[0].total):'Нет предложений';
 const status=$('#hotel-detail-price-status');if(status)status.textContent=offers.length?cardPriceNote(offers[0]):'';
 if(button){button.dataset.action=offers.length===1?'offer':'hotel-section';button.dataset.target='hotel-rooms-heading';if(offers.length===1)button.dataset.key=offers[0].key;else delete button.dataset.key;button.innerHTML=(offers.length===1?'Смотреть тур':'Выбрать тур')+' '+icon('arrow');button.disabled=!offers.length;}
 queueMicrotask(()=>{observeHotelRoomChoices();syncHotelSectionNavigation();});
 rememberUIRoute();
}
function openHotelDetails(id){
 const h=hotels.find(h=>h.id===id);if(!h)return;
 const offers=hotelOffers(h),description=plainHotelText(h.raw?.description||h.note||''),amenities=[...new Set((h.amenities||[]).map(a=>a.label).filter(Boolean))],meals=[...new Set(offers.map(o=>o.meal))];
 const facts=hotelDetailFacts(h)+hotelAmenitiesHTML(h);
 const visiblePhotos=h.photos.slice(0,3);
 const photos=h.photos.length?`<section class="hotel-photo-section" aria-label="Фотографии"><h3 id="hotel-photos-heading" class="sr-only hotel-section-anchor" tabindex="-1">Фотографии отеля</h3><div class="hotel-detail-photos photo-count-${visiblePhotos.length}">${visiblePhotos.map((p,i)=>`<button data-action="hotel-gallery" data-id="${h.id}" data-value="${i}" aria-label="Открыть фото ${i+1} отеля ${esc(h.name)}"><img src="${esc(p)}" alt="Фото отеля" loading="lazy"></button>`).join('')}</div><button class="secondary hotel-all-photos" data-action="hotel-gallery" data-id="${h.id}" data-value="0">${icon('image')} Все фотографии · ${h.photos.length}</button></section>`:'<p class="tour-missing">Фотографии отеля пока не предоставлены.</p>';
 const rating=ratingValue(h),hasOverview=Boolean(h.photos.length||description||amenities.length||facts);
 const overview=hasOverview?`${photos}<div class="hotel-detail-heading"><h3 id="hotel-about-heading" class="hotel-section-anchor" tabindex="-1">Об отеле</h3>${ratingValue(h)!==null?`<span class="detail-rating">${ratingText(h)} / 5</span>`:''}</div>
 ${amenities.length?`<ul class="hotel-key-facts">${amenities.slice(0,8).map(label=>`<li>${icon('check')}${esc(label)}</li>`).join('')}</ul>`:''}
 ${hotelDescriptionHTML(description)||(!amenities.length&&!facts?'<p class="tour-missing">Подробное описание пока не предоставлено.</p>':'')}`:`<div class="hotel-data-note"><span>Фото и описание отеля пока не предоставлены. Ниже — доступные туры с исходными условиями.</span>${rating!==null?`<strong>Оценка гостей ${ratingText(h)} / 5</strong>`:''}</div>`;
 const sections=[...(h.photos.length?[['hotel-photos-heading','Фото']]:[]),...(hasOverview?[['hotel-about-heading','Об отеле']]:[]),...(facts?[['hotel-services-heading','Услуги']]:[]),['hotel-rooms-heading','Номера и цены']];
 $('#modal-body').innerHTML=`
 <nav class="hotel-section-nav" data-hotel-id="${h.id}" aria-label="Разделы отеля">${sections.map(([target,label])=>`<button type="button" data-action="hotel-section" data-target="${target}">${label}</button>`).join('')}</nav>
 ${overview}
 ${facts?`<section class="hotel-information-sections" aria-labelledby="hotel-services-heading"><h3 id="hotel-services-heading" class="detail-section-title hotel-section-anchor" tabindex="-1">Услуги и инфраструктура</h3>${facts}</section>`:''}
 <section class="hotel-room-section" aria-labelledby="hotel-rooms-heading"><h3 id="hotel-rooms-heading" class="detail-section-title hotel-section-anchor" tabindex="-1">Номера и питание</h3><p class="hotel-room-context">${departureScopeText()} · ${durationText()} · ${guestsText()}</p>
 ${meals.length>1&&offers.length>2?`<label class="hotel-room-meal-filter">Питание в туре<select id="hotel-room-meal" data-id="${h.id}"><option value="">Любое питание</option>${meals.map(meal=>`<option value="${esc(meal)}">${esc(meal)}</option>`).join('')}</select></label>`:''}
 <p id="hotel-room-count" class="hotel-room-count" role="status" aria-live="polite"></p><div class="hotel-room-cards"></div></section>`;
 $('#modal').classList.add('hotel-details-dialog');
 if(offers.length){$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total"><span>За ${guestsText()}</span><strong id="hotel-detail-min"></strong><small id="hotel-detail-price-status"></small></div><button id="hotel-detail-offers" class="primary" data-action="hotel-detail-offers" data-id="${id}"></button>`;}
 renderHotelRooms(id,'',null,offers);
 return offers;
}
return {openHotelDetails,renderHotelRooms};
}
window.AnyTourHotelDetails=Object.freeze({create});
})();
