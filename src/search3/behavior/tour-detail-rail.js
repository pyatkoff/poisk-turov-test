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
function flightName(v){return window.Search3FlightPresentation.flightLabel(v,'Выбирается');}
function normalizedTotal(detail){const value=detail||{},source=value.tour||tour||{};if(value.pricePending)return num(value.basePrice)||num(source.price);return num(value.price)||num(value.basePrice)||num(source.price);}
function html(t){const h=hotel(t),fuel=num(t&&t.fuelCharge);return '<aside class="search3-tour-detail-rail" aria-label="Состав тура"><h3>Состав тура</h3><dl><div><dt>Отель</dt><dd>'+esc(txt(h.name)||txt(t&&t.name)||'—')+'</dd></div><div><dt>Направление</dt><dd>'+esc(place(t))+'</dd></div><div><dt>Номер</dt><dd>'+esc(txt(t&&t.roomType)||'—')+'</dd></div><div><dt>Питание</dt><dd>'+esc(meal(t))+'</dd></div><div><dt>Туристы</dt><dd>'+esc(people(t))+'</dd></div><div><dt>Дата</dt><dd>'+esc(txt(t&&t.date)||'—')+(t&&t.nights?' · '+esc(t.nights)+' ноч.':'')+'</dd></div><div><dt>Перелёт</dt><dd>'+esc(flightName(flight))+'</dd></div></dl><div class="search3-tour-detail-rail__price"><span>Стоимость тура</span><strong>'+money(selectedTotal||t&&t.price)+'</strong>'+(fuel?'<small>Топливный сбор: '+money(fuel)+'</small>':'')+'</div><button type="button" class="search3-tour-detail-rail__continue">Далее: итог тура</button></aside>';}
function render(){const root=document.getElementById('selectedTour');if(!root||root.hidden||!tour)return;const old=root.querySelector('.search3-tour-detail-rail');if(old)old.remove();root.insertAdjacentHTML('beforeend',html(tour));}
window.addEventListener('v2:tour-selected',e=>{tour=e.detail&&e.detail.tour||null;flight=null;selectedTotal=num(tour&&tour.price);setTimeout(render,0)});
window.addEventListener('v2:flight-selected',e=>{flight=e.detail&&e.detail.flight||null;setTimeout(render,0)});
window.addEventListener('v2:tour-price-updated',e=>{selectedTotal=normalizedTotal(e.detail);setTimeout(render,0)});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-tour-detail-rail__continue');if(!b)return;const root=document.getElementById('selectedTour'),target=root&&root.querySelector('.search3-flight-continue button');if(target)target.click();});
})();


