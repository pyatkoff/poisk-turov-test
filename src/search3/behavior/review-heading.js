
/* donor:search3-review-heading.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function ensure(){const r=root();if(!r)return null;let h=r.querySelector('.search3-review-heading');if(h)return h;h=document.createElement('header');h.className='search3-review-heading';h.hidden=true;h.innerHTML='<div><span>ФИНАЛЬНАЯ ПРОВЕРКА</span><h2>Ваш тур</h2><p>Проверьте детали поездки и отправьте заявку.</p></div><b aria-hidden="true">✓</b>';const step=r.querySelector('.search3-booking-stepper');if(step&&step.nextSibling)r.insertBefore(h,step.nextSibling);else r.prepend(h);return h}
function sync(){const r=root(),h=ensure();if(!r||!h)return;h.hidden=!r.classList.contains('search3-final-review')}
window.addEventListener('v2:tour-selected',()=>setTimeout(sync,0));
window.addEventListener('v2:booking-review',()=>setTimeout(sync,0));
document.addEventListener('click',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button'))setTimeout(sync,0)});
})();
