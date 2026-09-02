(function(){'use strict';
let lastTour=null,lastFlight=null;
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function number(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=number(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function text(v){if(v==null)return'';if(typeof v==='string'||typeof v==='number')return String(v);if(Array.isArray(v))return v.map(text).filter(Boolean).join(', ');for(const k of ['russianName','fullRussianName','name','title','value','text']){const s=text(v[k]);if(s)return s;}return'';}
function meal(t){return text(t&&t.meal)||'—';}
function operator(t){return text(t&&t.operator)||'—';}
function people(t){const p=[];if(t&&t.adults)p.push(t.adults+' взр.');if(t&&t.childs)p.push(t.childs+' дет.');return p.join(' + ')||'—';}
function place(t){const h=t&&t.hotel||{};return [text(h.country),text(h.region),text(h.subRegion)].filter(Boolean).join(', ')||'—';}
function flightLabel(v){if(!v)return'Выберите рейс';const fw=Array.isArray(v.forward)?v.forward:[],bw=Array.isArray(v.backward)?v.backward:[];const names=[];fw.concat(bw).forEach(f=>{const n=[text(f&&f.company),text(f&&f.number)].filter(Boolean).join(' ');if(n&&!names.includes(n))names.push(n);});return names.join(' · ')||'Рейс выбран';}
function flightMoneyHtml(v){if(!v)return'';const price=number(v.price),fuel=number(v.fuelCharge),rows=[];if(price>0)rows.push('<div><span>Цена варианта рейса</span><b>'+money(price)+'</b></div>');if(fuel>0)rows.push('<div><span>Топливный сбор рейса</span><b>'+money(fuel)+'</b></div>');return rows.length?'<div class="search3-booking-summary__flight-costs">'+rows.join('')+'</div>':'';}
function summaryHtml(t){const h=t&&t.hotel||{};const pic=t&&t.picture||h.picturelink||'';return '<aside class="search3-booking-summary" aria-label="Ваш тур">'+
'<div class="search3-booking-summary__title">Ваш тур</div>'+
(pic?'<img class="search3-booking-summary__image" src="'+esc(pic)+'" alt="">':'')+
'<strong class="search3-booking-summary__hotel">'+esc(text(h.name)||text(t&&t.name)||'Выбранный тур')+'</strong>'+
'<div class="search3-booking-summary__place">'+esc(place(t))+'</div>'+
'<dl><div><dt>Дата</dt><dd>'+esc(text(t&&t.date)||'—')+'</dd></div><div><dt>Ночей</dt><dd>'+esc(text(t&&t.nights)||'—')+'</dd></div><div><dt>Туристы</dt><dd>'+esc(people(t))+'</dd></div><div><dt>Номер</dt><dd>'+esc(text(t&&t.roomType)||'—')+'</dd></div><div><dt>Питание</dt><dd>'+esc(meal(t))+'</dd></div><div><dt>Оператор</dt><dd>'+esc(operator(t))+'</dd></div><div><dt>Перелёт</dt><dd class="search3-booking-summary__flight">'+esc(flightLabel(lastFlight))+'</dd></div></dl>'+
flightMoneyHtml(lastFlight)+'<div class="search3-booking-summary__total"><span>Стоимость тура</span><strong>'+money(t&&t.price)+'</strong></div><p class="search3-booking-summary__price-note">Стоимость тура показана отдельно от параметров выбранного варианта рейса.</p></aside>';}
function syncLayout(){
  const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form'),shell=form&&form.closest('.search3-lead-shell'),summary=shell&&shell.querySelector('.search3-booking-summary');
  if(!root||!form||!shell||!summary)return;
  const finalDesktop=root.classList.contains('search3-final-review')&&window.matchMedia('(min-width:1000px)').matches;
  if(finalDesktop){
    /* The final board is a two-column grid owned by #selectedTour. display:contents
       lets the real lead form and real booking summary participate in that grid
       without duplicating or fabricating any booking data. */
    shell.style.setProperty('display','contents','important');
    form.style.setProperty('grid-column','1','important');
    summary.style.setProperty('display','block','important');
    summary.style.setProperty('grid-column','2','important');
    summary.style.setProperty('grid-row','4 / 12','important');
  }else{
    shell.style.removeProperty('display');
    form.style.removeProperty('grid-column');
    summary.style.removeProperty('display');
    summary.style.removeProperty('grid-column');
    summary.style.removeProperty('grid-row');
  }
}
function render(){const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form');if(!form||!lastTour)return;let shell=form.closest('.search3-lead-shell');if(!shell){shell=document.createElement('div');shell.className='search3-lead-shell';form.parentNode.insertBefore(shell,form);shell.appendChild(form);}const old=shell.querySelector('.search3-booking-summary');if(old)old.remove();shell.insertAdjacentHTML('beforeend',summaryHtml(lastTour));syncLayout();}
function renderSoon(){setTimeout(render,0)}
function layoutSoon(){setTimeout(syncLayout,0)}
window.addEventListener('v2:tour-selected',e=>{lastTour=e.detail&&e.detail.tour||null;lastFlight=null;renderSoon();});
window.addEventListener('v2:flight-selected',e=>{lastFlight=e.detail&&e.detail.flight||null;renderSoon();});
window.addEventListener('v2:booking-review',layoutSoon);
window.addEventListener('v2:lead-success',renderSoon);
window.addEventListener('resize',layoutSoon);
document.addEventListener('click',e=>{if(e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button'))layoutSoon();});
window.Search3BookingSummary={render,syncLayout,version:2};
})();