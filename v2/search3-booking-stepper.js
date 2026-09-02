(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function ensure(){const r=root();if(!r||r.hidden)return null;let s=r.querySelector('.search3-booking-stepper');if(s)return s;s=document.createElement('nav');s.className='search3-booking-stepper';s.setAttribute('aria-label','Этапы оформления тура');s.innerHTML='<button type="button" class="search3-booking-step is-active" data-step="tour" aria-current="step"><span>1</span><b>Проверьте тур</b></button><button type="button" class="search3-booking-step" data-step="flight"><span>2</span><b>Выберите перелёт</b></button><button type="button" class="search3-booking-step" data-step="lead"><span>3</span><b>Оставьте контакты</b></button>';const back=r.querySelector('.back-results');if(back&&back.nextSibling)r.insertBefore(s,back.nextSibling);else r.prepend(s);return s}
function set(step){const s=ensure();if(!s)return;const order=['tour','flight','lead'];const ix=Math.max(0,order.indexOf(step));s.querySelectorAll('.search3-booking-step').forEach((n,i)=>{n.classList.toggle('is-active',i===ix);n.classList.toggle('is-done',i<ix);if(i===ix)n.setAttribute('aria-current','step');else n.removeAttribute('aria-current')});}
function target(step){const r=root();if(!r)return null;if(step==='flight')return r.querySelector('.tour-flights');if(step==='lead')return r.querySelector('.search3-lead-shell,.lead-form');return r.querySelector('.selected-head,.selected-picture')||r}
window.addEventListener('v2:tour-selected',()=>set('tour'));
window.addEventListener('v2:flight-selected',()=>set('flight'));
window.addEventListener('v2:lead-started',()=>set('lead'));
window.addEventListener('v2:lead-success',()=>set('lead'));
window.addEventListener('v2:lead-error',()=>set('lead'));
document.addEventListener('focusin',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .lead-form'))set('lead')});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-booking-step');if(!b)return;const t=target(b.dataset.step);if(t){set(b.dataset.step);t.scrollIntoView({behavior:'smooth',block:'start'})}});
})();
