(function(){'use strict';
function money(v){const n=Number(v||0);return n?new Intl.NumberFormat('ru-RU').format(n):'';}
function valueOfPrice(v){if(v&&typeof v==='object'&&v.value!==undefined)return Number(v.value||0);return Number(v||0);}
function box(){return document.querySelector('#selectedTour .selected-price');}
function render(price,label){const el=box();if(!el||!Number(price))return;el.innerHTML='<small>'+label+'</small>'+money(price)+' ₽';}
function reset(tour){const basePrice=Number(tour&&tour.price||0);if(basePrice)render(basePrice,'Стоимость');}
function sync(tour,flight,index){const basePrice=Number(tour&&tour.price||0),flightPrice=valueOfPrice(flight&&flight.price),fuel=valueOfPrice(flight&&flight.fuelCharge);if(!flightPrice)return;const delta=flightPrice - basePrice;let label='Стоимость с выбранным рейсом';if(basePrice&&delta>0)label+=' · +'+money(delta)+' ₽';else if(basePrice&&delta<0)label+=' · '+money(delta)+' ₽';render(flightPrice,label);window.dispatchEvent(new CustomEvent('v2:tour-price-updated',{detail:{tour:tour||null,flight:flight||null,index:Number(index||0),basePrice,price:flightPrice,delta,fuelCharge:fuel}}));}
window.addEventListener('v2:tour-selected',e=>reset(e.detail&&e.detail.tour));
window.addEventListener('v2:flight-selected',e=>sync(e.detail&&e.detail.tour,e.detail&&e.detail.flight,e.detail&&e.detail.index));
window.V2FlightPriceSync={sync,reset,version:1};
})();
