(function(){'use strict';
if(window.V2UnpricedFlightPriceResetV1)return;
function value(v){if(v&&typeof v==='object'&&v.value!==undefined)return Number(v.value||0);return Number(v||0);}
function selectedFuelValue(tour,flight){return flight&&Object.prototype.hasOwnProperty.call(flight,'fuelCharge')?value(flight.fuelCharge):value(tour&&tour.fuelCharge);}
function apply(event){const detail=event&&event.detail||{},tour=detail.tour||null,flight=detail.flight||null;if(!tour||!flight||value(flight.price)>0)return false;const sync=window.V2FlightPriceSync;if(!sync||typeof sync.reset!=='function')return false;sync.reset(tour);if(typeof sync.renderFuel==='function'&&Object.prototype.hasOwnProperty.call(flight,'fuelCharge'))sync.renderFuel(flight);const note=document.querySelector('#selectedTour .lead-form .section-heading span');if(note)note.textContent='Менеджер получит выбранный тур и выбранный рейс; стоимость рейса уточнит менеджер';const basePrice=Number(tour.price||0),fuel=selectedFuelValue(tour,flight);window.dispatchEvent(new CustomEvent('v2:tour-price-updated',{detail:{tour,flight,index:Number(detail.index||0),basePrice,price:0,delta:0,fuelCharge:fuel,pricePending:true}}));return true;}
window.addEventListener('v2:flight-selected',apply);
window.V2UnpricedFlightPriceResetV1={apply,value,selectedFuelValue,version:1};
})();
