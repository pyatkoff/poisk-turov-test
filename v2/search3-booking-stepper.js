(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function removeLegacy(r){if(!r)return;r.querySelectorAll('.checkout-journey,.checkout-facts-heading').forEach(n=>n.remove());}
function ensure(){const r=root();if(!r||r.hidden)return null;removeLegacy(r);let s=r.querySelector('.search3-booking-stepper');if(s)return s;s=document.createElement('nav');s.className='search3-booking-stepper';s.setAttribute('aria-label','Этапы оформления тура');s.innerHTML='<button type="button" class="search3-booking-step is-active" data-step="hotel" aria-current="step"><span>1</span><b>Отель</b></button><button type="button" class="search3-booking-step" data-step="room"><span>2</span><b>Номер и питание</b></button><button type="button" class="search3-booking-step" data-step="flight"><span>3</span><b>Рейсы</b></button><button type="button" class="search3-booking-step" data-step="tourists"><span>4</span><b>Туристы</b></button><button type="button" class="search3-booking-step" data-step="confirm"><span>5</span><b>Подтверждение</b></button>';const back=r.querySelector('.back-results');if(back&&back.nextSibling)r.insertBefore(s,back.nextSibling);else r.prepend(s);return s}
function set(step){const s=ensure();if(!s)return;const order=['hotel','room','flight','tourists','confirm'];const ix=Math.max(0,order.indexOf(step));s.querySelectorAll('.search3-booking-step').forEach((n,i)=>{n.classList.toggle('is-active',i===ix);n.classList.toggle('is-done',i<ix);if(i===ix)n.setAttribute('aria-current','step');else n.removeAttribute('aria-current')});}
function target(step){const r=root();if(!r)return null;if(step==='room')return r.querySelector('.facts,.hotel-desc')||r;if(step==='flight')return r.querySelector('.tour-flights')||r;if(step==='tourists')return r.querySelector('.search3-final-sections,.search3-lead-shell,.lead-form')||r;if(step==='confirm')return r.querySelector('.search3-lead-shell,.lead-form')||r;return r.querySelector('.selected-head,.selected-picture')||r}
window.addEventListener('v2:tour-selected',()=>set('hotel'));
window.addEventListener('v2:flight-selected',()=>set('flight'));
window.addEventListener('v2:booking-review',()=>set('tourists'));
window.addEventListener('v2:lead-started',()=>set('confirm'));
window.addEventListener('v2:lead-success',()=>set('confirm'));
window.addEventListener('v2:lead-error',()=>set('confirm'));
document.addEventListener('focusin',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .lead-form'))set('tourists')});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-booking-step');if(!b)return;const t=target(b.dataset.step);if(t){set(b.dataset.step);t.scrollIntoView({behavior:'smooth',block:'start'})}});
})();
