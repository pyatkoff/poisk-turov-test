

/* donor:search3-flight-continue.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function syncHeading(r){if(!r)return;const strong=r.querySelector('.tour-flights .section-heading strong'),hint=r.querySelector('.tour-flights .section-heading span'),review=r.classList.contains('search3-final-review');if(strong)strong.textContent=review?'Рейсы':'Выберите рейс';if(hint)hint.textContent=review?'Выбранный перелёт':'Сравните время, багаж и доплату — цена тура обновится автоматически';}
function markDirections(r){if(!r)return;r.querySelectorAll('.tour-flights .flight-variant').forEach(variant=>{let outbound=0,backward=0;variant.querySelectorAll('.flight-segment').forEach(segment=>{const strong=segment.querySelector('.flight-segment-title strong'),title=String(strong&&strong.textContent||'').trim();segment.classList.remove('is-outbound','is-return');if(/^(Туда|Пересадка туда)$/.test(title)){segment.classList.add('is-outbound');outbound+=1}else if(/^(Обратно|Пересадка обратно)$/.test(title)){segment.classList.add('is-return');backward+=1}});variant.classList.toggle('has-roundtrip-pair',outbound>0&&backward>0);variant.classList.toggle('has-simple-roundtrip',outbound===1&&backward===1);});}
function ensure(){const r=root(),box=r&&r.querySelector('.tour-flights');if(!box)return;let action=box.querySelector('.search3-flight-continue');if(!action){action=document.createElement('div');action.className='search3-flight-continue';action.innerHTML='<button type="button" class="primary">Далее: итог тура</button>';box.appendChild(action)}action.hidden=false;action.querySelector('button').textContent=r.classList.contains('search3-final-review')?'Изменить рейс':'Далее: итог тура';syncHeading(r);markDirections(r);}
function enterReview(){const r=root();if(!r)return;r.classList.add('search3-final-review');ensure();window.dispatchEvent(new CustomEvent('v2:booking-review',{detail:{}}));const target=r.querySelector('.search3-final-sections,.search3-lead-shell,.lead-form');if(target)target.scrollIntoView({behavior:'smooth',block:'start'});}
function exitReview(){const r=root();if(!r)return;r.classList.remove('search3-final-review');ensure();const flights=r.querySelector('.tour-flights');if(flights)flights.scrollIntoView({behavior:'smooth',block:'start'});}
window.addEventListener('v2:tour-selected',()=>{const r=root();if(r){r.classList.remove('search3-final-review');setTimeout(()=>{syncHeading(r);markDirections(r)},0)}});
window.addEventListener('v2:flight-selected',()=>setTimeout(ensure,0));
window.addEventListener('v2:booking-review',()=>setTimeout(()=>{syncHeading(root());markDirections(root())},0));
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button');if(!b)return;e.preventDefault();const r=root();if(r&&r.classList.contains('search3-final-review'))exitReview();else enterReview();});
})();
