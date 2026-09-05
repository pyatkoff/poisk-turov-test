

/* donor:search3-booking-summary.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
let lastTour=null,lastFlight=null,selectedTotal=0;
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function number(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=number(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function text(v){if(v==null)return'';if(typeof v==='string'||typeof v==='number')return String(v);if(Array.isArray(v))return v.map(text).filter(Boolean).join(', ');for(const k of ['russianName','fullRussianName','name','title','value','text']){const s=text(v[k]);if(s)return s;}return'';}
function meal(t){return text(t&&t.meal)||'—';}
function operator(t){return text(t&&t.operator)||'—';}
function people(t){const p=[];if(t&&t.adults)p.push(t.adults+' взр.');if(t&&t.childs)p.push(t.childs+' дет.');return p.join(' + ')||'—';}
function place(t){const h=t&&t.hotel||{};return [text(h.country),text(h.region),text(h.subRegion)].filter(Boolean).join(', ')||'—';}
function flightLabel(v){return window.Search3FlightPresentation.flightLabel(v,'Выберите рейс');}
function normalizedTotal(detail){const d=detail||{},tour=d.tour||lastTour||{};if(d.pricePending)return number(d.basePrice)||number(tour.price);return number(d.price)||number(d.basePrice)||number(tour.price);}
function summaryHtml(t){const h=t&&t.hotel||{};const pic=t&&t.picture||h.picturelink||'';return '<aside class="search3-booking-summary" aria-label="Ваш тур">'+
'<div class="search3-booking-summary__title">Ваш тур</div>'+
(pic?'<img class="search3-booking-summary__image" src="'+esc(pic)+'" alt="">':'')+
'<strong class="search3-booking-summary__hotel">'+esc(text(h.name)||text(t&&t.name)||'Выбранный тур')+'</strong>'+
'<div class="search3-booking-summary__place">'+esc(place(t))+'</div>'+
'<dl><div><dt>Дата</dt><dd>'+esc(text(t&&t.date)||'—')+'</dd></div><div><dt>Ночей</dt><dd>'+esc(text(t&&t.nights)||'—')+'</dd></div><div><dt>Туристы</dt><dd>'+esc(people(t))+'</dd></div><div><dt>Номер</dt><dd>'+esc(text(t&&t.roomType)||'—')+'</dd></div><div><dt>Питание</dt><dd>'+esc(meal(t))+'</dd></div><div><dt>Оператор</dt><dd>'+esc(operator(t))+'</dd></div><div><dt>Перелёт</dt><dd class="search3-booking-summary__flight">'+esc(flightLabel(lastFlight))+'</dd></div></dl>'+
'<div class="search3-booking-summary__total"><span>Стоимость тура</span><strong>'+money(selectedTotal||t&&t.price)+'</strong></div><p class="search3-booking-summary__price-note">Перед оплатой менеджер подтвердит итоговую стоимость и детали перелёта.</p></aside>';}
function clearLayout(shell,form,summary){['display','grid-column','grid-template-columns','gap','align-items'].forEach(p=>shell.style.removeProperty(p));['grid-column','grid-row'].forEach(p=>form.style.removeProperty(p));['display','grid-column','grid-row'].forEach(p=>summary.style.removeProperty(p));}
function syncLayout(){
  const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form'),shell=form&&form.closest('.search3-lead-shell'),summary=shell&&shell.querySelector('.search3-booking-summary');
  if(!root||!form||!shell||!summary)return;
  const desktop=window.matchMedia('(min-width:1000px)').matches,finalReview=root.classList.contains('search3-final-review'),leadEntry=root.classList.contains('search3-lead-entry');
  if(finalReview&&!leadEntry)root.dataset.search3FinalLayout='maket7';else delete root.dataset.search3FinalLayout;
  const title=summary.querySelector('.search3-booking-summary__title');if(title)title.textContent=finalReview&&!leadEntry?'Итоговая стоимость':'Ваш тур';
  clearLayout(shell,form,summary);
  if(desktop&&finalReview&&leadEntry){
    shell.style.setProperty('display','grid','important');
    shell.style.setProperty('grid-column','1 / -1','important');
    shell.style.setProperty('grid-template-columns','minmax(0,1fr) 320px','important');
    shell.style.setProperty('gap','18px','important');
    shell.style.setProperty('align-items','start','important');
    form.style.setProperty('grid-column','1','important');
    form.style.setProperty('grid-row','1','important');
    summary.style.setProperty('display','block','important');
    summary.style.setProperty('grid-column','2','important');
    summary.style.setProperty('grid-row','1','important');
  }else if(desktop&&finalReview){
    /* Maket7 final review uses a compact hotel card on the left and cost-only rail on the right. */
    shell.style.setProperty('display','contents','important');
    form.style.setProperty('grid-column','1 / 3','important');
    summary.style.setProperty('display','block','important');
    summary.style.setProperty('grid-column','3','important');
    summary.style.setProperty('grid-row','4 / 12','important');
  }
}
function render(){const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form');if(!form||!lastTour)return;let shell=form.closest('.search3-lead-shell');if(!shell){shell=document.createElement('div');shell.className='search3-lead-shell';form.parentNode.insertBefore(shell,form);shell.appendChild(form);}const old=shell.querySelector('.search3-booking-summary');if(old)old.remove();shell.insertAdjacentHTML('beforeend',summaryHtml(lastTour));syncLayout();}
function renderSoon(){setTimeout(render,0)}
function layoutSoon(){setTimeout(syncLayout,0)}
window.addEventListener('v2:tour-selected',e=>{lastTour=e.detail&&e.detail.tour||null;lastFlight=null;selectedTotal=number(lastTour&&lastTour.price);renderSoon();});
window.addEventListener('v2:flight-selected',e=>{lastFlight=e.detail&&e.detail.flight||null;renderSoon();});
window.addEventListener('v2:tour-price-updated',e=>{selectedTotal=normalizedTotal(e.detail);renderSoon();});
window.addEventListener('v2:booking-review',layoutSoon);
window.addEventListener('search3:lead-entry',layoutSoon);
window.addEventListener('v2:lead-started',layoutSoon);
window.addEventListener('v2:lead-error',layoutSoon);
window.addEventListener('v2:lead-success',renderSoon);
window.addEventListener('resize',layoutSoon);
document.addEventListener('click',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button'))layoutSoon();});
window.Search3BookingSummary={render,syncLayout,normalizedTotal,version:5};
})();
