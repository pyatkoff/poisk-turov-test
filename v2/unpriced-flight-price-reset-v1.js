(function(){'use strict';
if(window.V2UnpricedFlightPriceResetV1)return;
function value(v){if(v&&typeof v==='object'&&v.value!==undefined)return Number(v.value||0);return Number(v||0);}
function clarifyPendingLabels(){document.querySelectorAll('#selectedTour .flight-choice>b').forEach(label=>{const text=String(label.textContent||'').trim();if(!/[0-9]/.test(text))label.textContent='Цена уточняется';});}
function apply(event){clarifyPendingLabels();const detail=event&&event.detail||{},tour=detail.tour||null,flight=detail.flight||null;if(!tour||!flight||value(flight.price)>0)return false;const sync=window.V2FlightPriceSync;if(!sync||typeof sync.reset!=='function')return false;sync.reset(tour);if(typeof sync.renderFuel==='function')sync.renderFuel(flight);const note=document.querySelector('#selectedTour .lead-form .section-heading span');if(note)note.textContent='Менеджер получит выбранный тур и выбранный рейс; стоимость рейса уточнит менеджер';const basePrice=Number(tour.price||0),fuel=value(flight.fuelCharge);window.dispatchEvent(new CustomEvent('v2:tour-price-updated',{detail:{tour,flight,index:Number(detail.index||0),basePrice,price:0,delta:0,fuelCharge:fuel,pricePending:true}}));return true;}
window.addEventListener('v2:flight-selected',apply);
window.V2UnpricedFlightPriceResetV1={apply,value,clarifyPendingLabels,version:2};
})();
