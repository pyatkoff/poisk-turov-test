(function(){'use strict';
function positive(value){const n=Number(value);return Number.isFinite(n)&&n>0?n:0;}
function offerReady(tour){
  if(!tour||tour.finalPriceReady!==true||String(tour.currency||'RUB').toUpperCase()!=='RUB')return false;
  const display=positive(tour.price),finalPrice=positive(tour.finalPrice);
  return !!display&&!!finalPrice&&display===finalPrice;
}
function filterHotels(items){
  return (Array.isArray(items)?items:[]).map(hotel=>{
    if(!hotel||!Array.isArray(hotel.tours))return null;
    const tours=hotel.tours.filter(offerReady);
    if(!tours.length)return null;
    const price=Math.min(...tours.map(tour=>positive(tour.price)).filter(Boolean));
    return price?Object.assign({},hotel,{tours,price}):null;
  }).filter(Boolean);
}
window.Search3PriceReadiness={offerReady,filterHotels,version:1};
})();
