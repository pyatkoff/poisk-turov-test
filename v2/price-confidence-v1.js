(function(){'use strict';
if(window.V2PriceConfidenceV1)return;
function priceBox(){return document.querySelector('#selectedTour .selected-price');}
function ensureNote(){const price=priceBox();if(!price)return null;let note=price.querySelector('.selected-price-confidence');if(note)return note;note=document.createElement('p');note.className='selected-price-confidence hotel-choice-hint';note.setAttribute('role','note');price.appendChild(note);return note;}
function baseCopy(){const note=ensureNote();if(!note)return;note.innerHTML='<strong>Цена из поиска.</strong> После выбора рейса стоимость пересчитается; перед оплатой менеджер подтвердит итог.';}
function flightCopy(event){const note=ensureNote();if(!note)return;const detail=event&&event.detail||{},flight=detail.flight,price=Number(detail.price||0);if(detail.pricePending&&flight){note.innerHTML='<strong>Рейс выбран, цена уточняется.</strong> Пока показываем цену из поиска; менеджер подтвердит стоимость выбранного рейса перед оплатой.';return;}if(!flight||!price){baseCopy();return;}note.innerHTML='<strong>Цена с выбранным рейсом.</strong> Перед оплатой менеджер ещё раз подтвердит итоговую стоимость и детали перелёта.';}
window.addEventListener('v2:tour-selected',baseCopy);
window.addEventListener('v2:tour-price-updated',flightCopy);
window.V2PriceConfidenceV1={ensureNote,baseCopy,flightCopy,version:5};
})();