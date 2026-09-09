(function(){'use strict';
if(window.V2PriceConfidenceV1)return;
function ensureStyle(){if(document.getElementById('v2PriceConfidenceStyle'))return;const style=document.createElement('style');style.id='v2PriceConfidenceStyle';style.textContent='.selected-price-confidence{max-width:280px;margin:7px 0 0 auto;color:#66738b;font-size:11px;font-weight:650;line-height:1.4;text-align:right}.selected-price-confidence strong{color:#34425c;font-weight:800}@media(max-width:640px){.selected-price-confidence{max-width:none;margin:7px 0 0;text-align:left;font-size:10.5px}}';document.head.appendChild(style);}
function priceBox(){return document.querySelector('#selectedTour .selected-price');}
function ensureNote(){const price=priceBox();if(!price)return null;const head=price.closest('.selected-head');if(!head)return null;let note=head.querySelector('.selected-price-confidence');if(note)return note;ensureStyle();note=document.createElement('p');note.className='selected-price-confidence';note.setAttribute('role','note');price.insertAdjacentElement('afterend',note);return note;}
function baseCopy(){const note=ensureNote();if(!note)return;note.innerHTML='<strong>Цена из поиска.</strong> После выбора рейса стоимость пересчитается; перед оплатой менеджер подтвердит итог.';}
function pendingCopy(){const note=ensureNote();if(!note)return;note.innerHTML='<strong>Цена выбранного рейса уточняется.</strong> Пока показана цена тура из поиска; перед оплатой менеджер подтвердит итоговую стоимость и детали перелёта.';}
function flightCopy(event){const note=ensureNote();if(!note)return;const detail=event&&event.detail||{},flight=detail.flight,price=Number(detail.price||0);if(flight&&(detail.pricePending||!price)){pendingCopy();return;}if(!flight){baseCopy();return;}note.innerHTML='<strong>Цена с выбранным рейсом.</strong> Перед оплатой менеджер ещё раз подтвердит итоговую стоимость и детали перелёта.';}
window.addEventListener('v2:tour-selected',baseCopy);
window.addEventListener('v2:tour-price-updated',flightCopy);
window.V2PriceConfidenceV1={ensureNote,baseCopy,pendingCopy,flightCopy,version:1};
})();
