/* Compact booking total owner. Full tour facts stay in the selected-tour source DOM. */
(function(){'use strict';
/* @include behavior/booking/format.js */
const summaryClass='search3-booking-summary';
let lastTour=null,lastFlight=null,selectedTotal=0,pending=false;
function number(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=number(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function flightLabel(v){const root=document.getElementById('selectedTour'),lead=root&&root.classList.contains('search3-lead-entry');return supplierFlightLabel(v,lead?'Рейс уточнит менеджер':'Выберите рейс');}
function normalizedTotal(detail){const d=detail||{},tour=d.tour||lastTour||{};if(d.pricePending)return number(d.basePrice)||number(tour.price);return number(d.price)||number(d.basePrice)||number(tour.price);}
function summaryHtml(){return '<aside class="'+summaryClass+'" aria-label="Ваш тур"><div class="'+summaryClass+'__title">Ваш тур</div><div class="'+summaryClass+'__flight">'+esc(flightLabel(lastFlight))+'</div><div class="'+summaryClass+'__total"><span>Стоимость тура</span><strong>'+money(selectedTotal||lastTour&&lastTour.price)+'</strong></div><p class="'+summaryClass+'__price-note">Перед оплатой менеджер подтвердит итоговую стоимость и детали перелёта.</p></aside>';}
function render(){const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form');if(!form||!lastTour)return;let shell=form.closest('.search3-lead-shell');if(!shell){shell=document.createElement('div');shell.className='search3-lead-shell';form.parentNode.insertBefore(shell,form);shell.appendChild(form)}const old=shell.querySelector('.'+summaryClass);if(old)old.remove();shell.insertAdjacentHTML('beforeend',summaryHtml())}
function renderSoon(){if(pending)return;pending=true;setTimeout(()=>{pending=false;render()},0)}
window.addEventListener('v2:tour-selected',event=>{lastTour=event.detail&&event.detail.tour||null;lastFlight=null;selectedTotal=number(lastTour&&lastTour.price);renderSoon()});
window.addEventListener('v2:flight-selected',event=>{lastFlight=event.detail&&event.detail.flight||null;renderSoon()});
window.addEventListener('v2:tour-price-updated',event=>{selectedTotal=normalizedTotal(event.detail);renderSoon()});
['v2:booking-review','search3:lead-entry','v2:lead-started','v2:lead-success','v2:lead-error'].forEach(name=>window.addEventListener(name,renderSoon));
window.Search3BookingSummary={render,syncLayout:render,normalizedTotal,version:6};
})();
