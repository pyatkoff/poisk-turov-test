

/* donor:search3-tour-detail-rail.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
const railClass='search3-tour-detail-rail';
let tour=null,flight=null,selectedTotal=0;
const {esc,text:txt,people,place}=window.Search3PresentationText;
function num(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=num(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function hotel(t){return t&&t.hotel||{};}
function meal(t){return txt(t&&t.meal)||'—';}
function flightName(v){return window.Search3FlightPresentation.flightLabel(v,'Выбирается');}
function normalizedTotal(detail){const value=detail||{},source=value.tour||tour||{};if(value.pricePending)return num(value.basePrice)||num(source.price);return num(value.price)||num(value.basePrice)||num(source.price);}
function detailRow(label,value){return '<div><dt>'+label+'</dt><dd>'+value+'</dd></div>';}
function html(t){const h=hotel(t),fuel=num(t&&t.fuelCharge);return ('<aside class="'+railClass+'" aria-label="Состав тура"><h3>Состав тура</h3><dl>')+
detailRow('Отель',esc(txt(h.name)||txt(t&&t.name)||'—'))+
detailRow('Направление',esc(place(t)))+
detailRow('Номер',esc(txt(t&&t.roomType)||'—'))+
detailRow('Питание',esc(meal(t)))+
detailRow('Туристы',esc(people(t)))+
detailRow('Дата',esc(txt(t&&t.date)||'—')+(t&&t.nights?' · '+esc(t.nights)+' ноч.':''))+
detailRow('Перелёт',esc(flightName(flight)))+
('</dl><div class="'+railClass+'__price"><span>Стоимость тура</span><strong>')+money(selectedTotal||t&&t.price)+'</strong>'+(fuel?'<small>Топливный сбор: '+money(fuel)+'</small>':'')+('</div><button type="button" class="'+railClass+'__continue">Далее: итог тура</button></aside>');}
function render(){const root=document.getElementById('selectedTour');if(!root||root.hidden||!tour)return;const old=root.querySelector('.'+railClass);if(old)old.remove();root.insertAdjacentHTML('beforeend',html(tour));}
window.addEventListener('v2:tour-selected',e=>{tour=e.detail&&e.detail.tour||null;flight=null;selectedTotal=num(tour&&tour.price);setTimeout(render,0)});
window.addEventListener('v2:flight-selected',e=>{flight=e.detail&&e.detail.flight||null;setTimeout(render,0)});
window.addEventListener('v2:tour-price-updated',e=>{selectedTotal=normalizedTotal(e.detail);setTimeout(render,0)});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest(('#selectedTour .'+railClass+'__continue'));if(!b)return;const root=document.getElementById('selectedTour'),target=root&&root.querySelector('.search3-flight-continue button');if(target)target.click();});
})();
