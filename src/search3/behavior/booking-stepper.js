
/* donor:search3-booking-stepper.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function nodeText(n){return String(n&&n.textContent||'').replace(/\s+/g,' ').trim()}
function removeLegacy(r){
  if(!r)return;
  r.querySelectorAll('.checkout-journey,.checkout-facts-heading,.selected-tour-progress').forEach(n=>n.remove());
  /* A pre-Search3 three-stage strip can be injected without a stable class by an
     older checkout layer. Remove only the smallest scoped legacy vocabulary and
     never touch the authoritative five-step Search3 stepper. */
  const labels=['Тур выбран','Выбор рейса','Заявка менеджеру'];
  const matches=[...r.querySelectorAll('div,nav,ol,ul')].filter(n=>{
    if(n.classList&&n.classList.contains('search3-booking-stepper'))return false;
    const s=nodeText(n);
    return s.length>0&&s.length<=80&&labels.every(label=>s.includes(label));
  });
  const outer=matches.find(n=>!matches.some(other=>other!==n&&other.contains(n)));
  if(outer)outer.remove();
}
function ensure(){const r=root();if(!r||r.hidden)return null;removeLegacy(r);let s=r.querySelector('.search3-booking-stepper');if(s)return s;s=document.createElement('nav');s.className='search3-booking-stepper';s.setAttribute('aria-label','Этапы оформления тура');s.innerHTML='<button type="button" class="search3-booking-step is-active" data-step="flight" aria-current="step"><span>1</span><b>Рейс</b></button><button type="button" class="search3-booking-step" data-step="review"><span>2</span><b>Итог тура</b></button><button type="button" class="search3-booking-step" data-step="lead"><span>3</span><b>Заявка</b></button>';const back=r.querySelector('.back-results');if(back&&back.nextSibling)r.insertBefore(s,back.nextSibling);else r.prepend(s);return s}
function set(step){const s=ensure();if(!s)return;const order=['flight','review','lead'];const ix=Math.max(0,order.indexOf(step));s.querySelectorAll('.search3-booking-step').forEach((n,i)=>{n.classList.toggle('is-active',i===ix);n.classList.toggle('is-done',i<ix);if(i===ix)n.setAttribute('aria-current','step');else n.removeAttribute('aria-current')});}
function target(step){const r=root();if(!r)return null;if(step==='review')return r.querySelector('.search3-booking-summary,.search3-lead-shell')||r;if(step==='lead')return r.querySelector('.lead-form')||r;return r.querySelector('.tour-flights')||r}
window.addEventListener('v2:tour-selected',()=>set('flight'));
window.addEventListener('v2:flight-selected',()=>set('flight'));
window.addEventListener('v2:booking-review',()=>set('review'));
window.addEventListener('search3:lead-entry',e=>set(e.detail&&e.detail.active===false?'review':'lead'));
window.addEventListener('v2:lead-started',()=>set('lead'));
window.addEventListener('v2:lead-success',()=>set('lead'));
window.addEventListener('v2:lead-error',()=>set('lead'));
document.addEventListener('focusin',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .lead-form'))set('lead')});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-booking-step');if(!b)return;const t=target(b.dataset.step);if(t){set(b.dataset.step);t.scrollIntoView({behavior:'smooth',block:'start'})}});
window.Search3BookingStepper={ensure,set,removeLegacy,version:5};
})();
