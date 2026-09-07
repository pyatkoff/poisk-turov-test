

/* donor:search3-booking-summary.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
const summaryClass='search3-booking-summary';
let lastTour=null,lastFlight=null,selectedTotal=0;
const {esc,text,people,place}=window.Search3PresentationText;
function number(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=number(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function meal(t){return text(t&&t.meal)||'—';}
function operator(t){return text(t&&t.operator)||'—';}
function flightLabel(v){const root=document.getElementById('selectedTour'),pending=root&&root.classList.contains('search3-lead-entry')&&!v;return window.Search3FlightPresentation.flightLabel(v,pending?'Рейс уточнит менеджер':'Выберите рейс');}
function normalizedTotal(detail){const d=detail||{},tour=d.tour||lastTour||{};if(d.pricePending)return number(d.basePrice)||number(tour.price);return number(d.price)||number(d.basePrice)||number(tour.price);}
function detailRow(label,value){return '<div><dt>'+label+'</dt><dd>'+value+'</dd></div>';}
function summaryHtml(t){const h=t&&t.hotel||{};const pic=t&&t.picture||h.picturelink||'';return ('<aside class="'+summaryClass+'" aria-label="Ваш тур">')+
('<div class="'+summaryClass+'__title">Ваш тур</div>')+
(pic?('<img class="'+summaryClass+'__image" src="')+esc(pic)+'" alt="">':'')+
('<strong class="'+summaryClass+'__hotel">')+esc(text(h.name)||text(t&&t.name)||'Выбранный тур')+'</strong>'+
('<div class="'+summaryClass+'__place">')+esc(place(t))+'</div>'+
'<dl>'+detailRow('Дата',esc(text(t&&t.date)||'—'))+
detailRow('Ночей',esc(text(t&&t.nights)||'—'))+
detailRow('Туристы',esc(people(t)))+
detailRow('Номер',esc(text(t&&t.roomType)||'—'))+
detailRow('Питание',esc(meal(t)))+
detailRow('Оператор',esc(operator(t)))+
('<div><dt>Перелёт</dt><dd class="'+summaryClass+'__flight">')+esc(flightLabel(lastFlight))+'</dd></div></dl>'+
('<div class="'+summaryClass+'__total"><span>Стоимость тура</span><strong>')+money(selectedTotal||t&&t.price)+('</strong></div><p class="'+summaryClass+'__price-note">Перед оплатой менеджер подтвердит итоговую стоимость и детали перелёта.</p></aside>');}
/* @include behavior/booking/layout.js */
/* @include behavior/booking/services.js */
function render(){const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form');if(!form||!lastTour)return;let shell=form.closest('.search3-lead-shell');if(!shell){shell=document.createElement('div');shell.className='search3-lead-shell';form.parentNode.insertBefore(shell,form);shell.appendChild(form);}const old=shell.querySelector('.'+summaryClass);if(old)old.remove();shell.insertAdjacentHTML('beforeend',summaryHtml(lastTour));syncLayout();}
// Tour, flight and price events can arrive in the same turn. Render the latest
// state once; a full render already includes layout synchronization.
// Pending bits: 1 = price summary, 2 = services. Price-only events keep services.
let updatePending=false,renderPending=0;
function scheduleUpdate(parts){
  renderPending|=parts;
  if(updatePending)return;
  updatePending=true;
  setTimeout(()=>{
    const shouldRender=renderPending;
    updatePending=false;renderPending=0;
    if(shouldRender&1)render();else syncLayout();
    if(shouldRender&2)renderServices();
  },0);
}
function renderSoon(){scheduleUpdate(1)}
function layoutSoon(){scheduleUpdate(0)}
window.addEventListener('v2:tour-selected',e=>{lastTour=e.detail&&e.detail.tour||null;lastFlight=null;selectedTotal=number(lastTour&&lastTour.price);scheduleUpdate(3);});
window.addEventListener('v2:flight-selected',e=>{lastFlight=e.detail&&e.detail.flight||null;scheduleUpdate(3);});
window.addEventListener('v2:tour-price-updated',e=>{selectedTotal=normalizedTotal(e.detail);renderSoon();});
['v2:booking-review','search3:lead-entry','v2:lead-started','v2:lead-error'].forEach(name=>window.addEventListener(name,layoutSoon));
window.addEventListener('v2:lead-success',renderSoon);
document.addEventListener('click',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button'))layoutSoon();});
window.Search3BookingSummary={render,syncLayout,normalizedTotal,version:5};
})();
