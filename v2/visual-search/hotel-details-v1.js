'use strict';
(() => {
function create(context){
const {$,$$,data,hotels,modalType,plainHotelText,esc,hotelOffers,mealLabel,displayMealLabel=mealLabel,roomLabel=o=>o.room||'Номер уточняется',dateText,nightsText,flightLabel,guestsText,money,icon,offerCountText,cardPriceNote,observeHotelRoomChoices,syncHotelSectionNavigation,rememberUIRoute,ratingValue,ratingText,departureScopeText,durationText,countryNames={},hotelStarsHTML=h=>h.stars?`<span class="hotel-stars">${'★'.repeat(h.stars)}</span>`:''}=context;
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
function roomOfferChoiceHTML(o,minimum){
 const delta=o.total-minimum,deltaText=delta>0?'+'+money(delta):'Минимальная цена';
 return `<div class="room-offer-choice" data-offer-key="${esc(o.key)}"><div class="room-offer-conditions"><strong class="room-offer-meal">${esc(displayMealLabel(o))}</strong><dl class="room-choice-facts"><div><dt class="sr-only">Даты и отдых</dt><dd><time datetime="${esc(o.day)}">${dateText(o.day)}</time> → <time datetime="${esc(o.returnDay)}">${dateText(o.returnDay)}</time> · ${nightsText(o.nights)}</dd></div></dl><span class="room-offer-operator">${esc(o.operator)}${flightLabel(o)?' · '+flightLabel(o):''}</span></div><div class="hotel-room-price"><strong>${money(o.total)}</strong><span class="room-offer-delta" ${delta>0?`aria-label="На ${esc(money(delta))} больше минимальной цены ${esc(money(minimum))} в этом списке"`:''}>${deltaText}</span><button class="${delta>0?'secondary':'primary'}" data-action="offer" data-key="${esc(o.key)}" aria-label="Смотреть тур: ${esc(roomLabel(o))}, ${esc(displayMealLabel(o))}, ${dateText(o.day)}, ${esc(o.operator)}, ${money(o.total)}">Смотреть тур ${icon('arrow')}</button></div></div>`;
}
function renderHotelRooms(id,meal='',restoredRooms=null,initialOffers=null){
 const h=hotels.find(h=>h.id===id);if(!h||modalType!=='hotel-details')return;
 const allOffers=initialOffers||hotelOffers(h),meals=[...new Set(allOffers.map(o=>o.meal))];
 let mealControl=$('#hotel-room-meal');
 if(!mealControl&&meals.length>1&&allOffers.length>2){$('#hotel-room-count').insertAdjacentHTML('beforebegin',`<label class="hotel-room-meal-filter">Питание в туре<select id="hotel-room-meal" data-id="${h.id}"></select></label>`);mealControl=$('#hotel-room-meal');}
 if(mealControl){const values=['',...meals];if(meal&&!values.includes(meal))values.push(meal);if(JSON.stringify([...mealControl.options].map(o=>o.value))!==JSON.stringify(values))mealControl.innerHTML=values.map(value=>`<option value="${esc(value)}">${esc(value?displayMealLabel({meal:value}):'Любое питание')}</option>`).join('');}
 const offers=allOffers.filter(o=>!meal||o.meal===meal),rooms=[],byRoom=new Map();
 // Set merges signed zero; strict equality leaves the NaN group empty.
 offers.forEach(o=>{
  const room=o.room;let group=byRoom.get(room);
  if(!group){group={room:room===0?0:room,offers:[]};byRoom.set(room,group);rooms.push(group);}
  if(room===room)group.offers.push(o);
 });
 $('#hotel-room-count').textContent=`Номера: ${rooms.length} · Туры: ${offers.length}`;
 const roomCards=$('.hotel-room-cards');roomCards.classList.toggle('single-direct-offer',offers.length===1);
 const sorted=offers.slice().sort((a,b)=>a.total-b.total),minimum=sorted[0]?.total;
 roomCards.innerHTML=sorted.map(o=>`<article class="hotel-room-card" data-room="${esc(o.room)}" data-offer-key="${esc(o.key)}"><header class="room-choice-main"><h4>${esc(roomLabel(o))}</h4></header>${roomOfferChoiceHTML(o,minimum)}</article>`).join('')||'<p class="tour-missing">По текущим условиям предложений нет. Измените даты или фильтры поиска.</p>';
 const comparison=$('#hotel-offer-comparison');if(comparison)comparison.hidden=!sorted.some(o=>o.total>minimum);
 const control=$('#hotel-room-meal');if(control){control.value=meal;[...control.options].forEach(option=>option.defaultSelected=option.value===meal);}
 const price=$('#hotel-detail-min'),button=$('#hotel-detail-offers');
 if(price)price.textContent=offers.length?(offers.length>1?'от ':'')+money(sorted[0].total):'Нет предложений';
 const status=$('#hotel-detail-price-status');if(status)status.textContent=offers.length?cardPriceNote(sorted[0]):'';
 if(button){button.dataset.action=offers.length===1?'offer':'hotel-section';button.dataset.target='hotel-rooms-heading';if(offers.length===1)button.dataset.key=offers[0].key;else delete button.dataset.key;button.innerHTML=(offers.length===1?'Смотреть тур':'Выбрать тур')+' '+icon('arrow');button.disabled=!offers.length;}
 queueMicrotask(()=>{observeHotelRoomChoices();syncHotelSectionNavigation();});
 rememberUIRoute();
}
function openHotelDetails(id){
 const h=hotels.find(h=>h.id===id);if(!h)return;
 const offers=hotelOffers(h),description=plainHotelText(h.raw?.description||h.note||''),amenities=[...new Set((h.amenities||[]).map(a=>a.label).filter(Boolean))],meals=[...new Set(offers.map(o=>o.meal))];
 const facts=hotelDetailFacts(h)+hotelAmenitiesHTML(h);
 const photos=h.photos.length?`<div class="hotel-detail-hero-photo"><button class="hotel-detail-hero-image" data-action="hotel-gallery" data-id="${h.id}" data-value="0" aria-label="Открыть фотографии отеля ${esc(h.name)}"><img src="${esc(h.photos[0])}" alt="Фото отеля ${esc(h.name)}" loading="lazy"></button><button class="secondary hotel-all-photos" data-action="hotel-gallery" data-id="${h.id}" data-value="0">${icon('image')} Все фотографии · ${h.photos.length}</button></div>`:'<div class="hotel-detail-hero-photo hotel-detail-photo-missing"><p class="tour-missing">Фотографии отеля пока не предоставлены.</p></div>';
 const overview=`<section id="hotel-about-panel" class="hotel-about-panel" aria-labelledby="hotel-about-tab" hidden><h3 id="hotel-about-heading" class="hotel-section-anchor detail-section-title" tabindex="-1">Об отеле</h3>${amenities.length?`<ul class="hotel-key-facts">${amenities.slice(0,8).map(label=>`<li>${icon('check')}${esc(label)}</li>`).join('')}</ul>`:''}${hotelDescriptionHTML(description)||'<p class="tour-missing">Подробное описание пока не предоставлено.</p>'}${facts?`<section class="hotel-information-sections"><h3 class="detail-section-title">Услуги и инфраструктура</h3>${facts}</section>`:''}</section>`;
 $('#modal-body').innerHTML=`
 <div class="hotel-detail-title">${hotelStarsHTML(h)}<h2>${esc(h.name)}</h2><p class="hotel-detail-meta">${esc(h.resort)}${countryNames[h.country]?', '+esc(countryNames[h.country]):''}</p>${ratingValue(h)!==null?`<span class="detail-rating">${ratingText(h)} / 5</span>`:''}</div><section class="hotel-detail-hero">${photos}</section>
 <nav class="hotel-section-nav hotel-detail-tabs" data-hotel-id="${h.id}" aria-label="Разделы отеля" role="tablist"><button id="hotel-offers-tab" type="button" role="tab" aria-controls="hotel-offers-panel" aria-selected="true" aria-pressed="true" data-action="hotel-section" data-target="hotel-rooms-heading">Номера и туры</button><button id="hotel-about-tab" type="button" role="tab" aria-controls="hotel-about-panel" aria-selected="false" aria-pressed="false" data-action="hotel-section" data-target="hotel-about-heading">Об отеле</button></nav>
 <section id="hotel-offers-panel" class="hotel-room-section" aria-labelledby="hotel-offers-tab"><h3 id="hotel-rooms-heading" class="detail-section-title hotel-section-anchor" tabindex="-1">Выберите номер и питание</h3><p class="hotel-room-context">${departureScopeText()} · ${durationText()} · ${guestsText()}</p><p class="hotel-offer-price-note">Все цены за ${guestsText()}. ${esc(offers.length?cardPriceNote(offers[0]):'Условия предложения уточняются при выборе тура.')}</p><p id="hotel-offer-comparison" class="hotel-offer-comparison">«+» — разница с минимальной ценой в этом списке.</p>
 ${meals.length>1&&offers.length>2?`<label class="hotel-room-meal-filter">Питание в туре<select id="hotel-room-meal" data-id="${h.id}"><option value="">Любое питание</option>${meals.map(meal=>`<option value="${esc(meal)}">${esc(displayMealLabel({meal}))}</option>`).join('')}</select></label>`:''}
 <p id="hotel-room-count" class="hotel-room-count" role="status" aria-live="polite"></p><div class="hotel-room-cards"></div></section>${overview}`;
 $('#modal').classList.add('hotel-details-dialog','hotel-offers-active');
 $('#modal-footer').hidden=!offers.length;$('#modal-footer').innerHTML=offers.length?`<div class="footer-total hotel-detail-footer"><span>За ${guestsText()}</span><strong id="hotel-detail-min"></strong><small id="hotel-detail-price-status"></small></div><button id="hotel-detail-offers" class="primary" data-action="hotel-detail-offers" data-id="${id}"></button>`:'';
 renderHotelRooms(id,'',null,offers);
 return offers;
}
return {openHotelDetails,renderHotelRooms};
}
window.AnyTourHotelDetails=Object.freeze({create});
})();
