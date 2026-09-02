(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function ensure(){const r=root();if(!r||r.hidden)return null;let s=r.querySelector('.search3-booking-stepper');if(s)return s;s=document.createElement('div');s.className='search3-booking-stepper';s.setAttribute('aria-label','Этапы оформления тура');s.innerHTML='<div class="search3-booking-step is-active" data-step="tour"><span>1</span><b>Проверьте тур</b></div><div class="search3-booking-step" data-step="flight"><span>2</span><b>Выберите перелёт</b></div><div class="search3-booking-step" data-step="lead"><span>3</span><b>Оставьте контакты</b></div>';const back=r.querySelector('.back-results');if(back&&back.nextSibling)r.insertBefore(s,back.nextSibling);else r.prepend(s);return s}
function set(step){const s=ensure();if(!s)return;const order=['tour','flight','lead'];const ix=Math.max(0,order.indexOf(step));s.querySelectorAll('.search3-booking-step').forEach((n,i)=>{n.classList.toggle('is-active',i===ix);n.classList.toggle('is-done',i<ix)});}
window.addEventListener('v2:tour-selected',()=>set('tour'));
window.addEventListener('v2:flight-selected',()=>set('flight'));
window.addEventListener('v2:lead-started',()=>set('lead'));
window.addEventListener('v2:lead-success',()=>set('lead'));
window.addEventListener('v2:lead-error',()=>set('lead'));
document.addEventListener('focusin',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .lead-form'))set('lead')});
})();
