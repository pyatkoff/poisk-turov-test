(function(){'use strict';
function numberValue(v){const n=Number(v||0);return Number.isFinite(n)&&n>0?Math.round(n):0;}
function flightPrice(flight){if(!flight)return 0;const p=flight.price;return numberValue(p&&typeof p==='object'&&p.value!==undefined?p.value:p);}
function summary(basePrice,flight){const base=numberValue(basePrice),fp=flightPrice(flight),selected=fp||base;return{basePrice:base,selectedPrice:selected,delta:base&&selected?selected-base:0,hasFlightPrice:fp>0};}
function money(v){return new Intl.NumberFormat('ru-RU').format(numberValue(v))+' ₽';}
function priceRoot(){const root=document.getElementById('selectedTour');return root&&root.querySelector('.selected-price');}
function render(s){const el=priceRoot();if(!el)return;let label='Стоимость тура',note='';if(s.hasFlightPrice){label='Стоимость с выбранным рейсом';if(s.delta>0)note='+'+money(s.delta)+' к базовой цене';else if(s.delta<0)note='−'+money(Math.abs(s.delta))+' от базовой цены';else note='Без доплаты за выбранный рейс';}el.innerHTML='<small>'+label+'</small>'+money(s.selectedPrice)+(note?'<small class="selected-price-delta">'+note+'</small>':'');el.dataset.basePrice=String(s.basePrice||0);el.dataset.selectedPrice=String(s.selectedPrice||0);el.dataset.flightDelta=String(s.delta||0);}
let basePrice=0,currentTour=null,currentFlight=null;
function publish(){const s=summary(basePrice,currentFlight);render(s);window.dispatchEvent(new CustomEvent('v2:tour-price-updated',{detail:Object.assign({tour:currentTour,flight:currentFlight},s)}));return s;}
window.addEventListener('v2:tour-selected',function(e){currentTour=e&&e.detail&&e.detail.tour||null;basePrice=numberValue(currentTour&&currentTour.price);currentFlight=null;publish();});
window.addEventListener('v2:flight-selected',function(e){currentFlight=e&&e.detail&&e.detail.flight||null;if(!basePrice){const t=e&&e.detail&&e.detail.tour||currentTour;basePrice=numberValue(t&&t.price);}publish();});
window.V2TourPriceSync={summary:summary,flightPrice:flightPrice,publish:publish,version:2};
})();
