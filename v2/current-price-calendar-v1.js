(function(){'use strict';
if(window.V2CurrentPriceCalendar)return;
const money=new Intl.NumberFormat('ru-RU'),dayFormatter=new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short',weekday:'short',timeZone:'UTC'});
let terminal=false,filteredItems=null,disclosureOpen=null;
function dateValue(raw){
const s=String(raw||'').trim(),iso=s.match(/^(\d{4})-(\d{2})-(\d{2})$/),local=iso?null:s.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
if(!iso&&!local)return'';
const parts=iso?iso.slice(1):[local[3],local[2],local[1]],year=Number(parts[0]),month=Number(parts[1]),day=Number(parts[2]),leap=year%4===0&&(year%100!==0||year%400===0),days=[31,leap?29:28,31,30,31,30,31,31,30,31,30,31];
return year>0&&month>=1&&month<=12&&day>=1&&day<=days[month-1]?parts.join('-'):'';
}
function dateLabel(iso){const d=new Date(iso+'T12:00:00Z');if(Number.isNaN(d.getTime()))return iso;return dayFormatter.format(d).replace(/\.$/,'');}
function collect(items){const byDate=new Map();(Array.isArray(items)?items:[]).forEach(h=>{(Array.isArray(h&&h.tours)?h.tours:[]).forEach(t=>{const date=dateValue(t&&t.date),price=Number(t&&t.price||0);if(!date||!Number.isFinite(price)||price<=0)return;const previous=byDate.get(date);if(previous===undefined||price<previous)byDate.set(date,price);});});return Array.from(byDate.entries()).map(([date,price])=>({date,price})).sort((a,b)=>a.date.localeCompare(b.date));}
function ensure(){let box=document.getElementById('currentPriceCalendar');if(box)return box;const tools=document.getElementById('resultsTools'),results=document.getElementById('results');if(!tools&&!results)return null;box=document.createElement('section');box.id='currentPriceCalendar';box.className='current-price-calendar';box.hidden=true;box.setAttribute('aria-labelledby','currentPriceCalendarTitle');(tools||results).insertAdjacentElement('beforebegin',box);return box;}
function render(items){const box=ensure();if(!box)return[];const previous=box.querySelector('details');if(previous)disclosureOpen=previous.open;const days=collect(items);if(days.length<2){box.hidden=true;box.innerHTML='';return days;}const best=Math.min.apply(null,days.map(x=>x.price));
const compact=document.body.classList.contains('search3-candidate'),expanded=disclosureOpen===null?(compact||window.matchMedia('(min-width:701px)').matches):disclosureOpen,head=compact?'summary':'div';
box.innerHTML=(compact?'<details'+(expanded?' open':'')+'>':'')+'<'+head+' class="current-price-calendar__head"><span class="current-price-calendar__heading"><span>Цены по датам</span><strong id="currentPriceCalendarTitle">'+(compact?'Календарь цен':'Когда дешевле вылететь')+'</strong></span><small>Минимум среди найденных сейчас туров</small></'+head+'><div class="current-price-calendar__days">'+days.map(x=>{const label=dateLabel(x.date),price=money.format(x.price),fullDate=x.date.split('-').reverse().join('.');return '<button type="button" class="current-price-calendar__day'+(x.price===best?' is-best':'')+'" data-calendar-date="'+x.date+'" aria-label="'+label+' ('+fullDate+'), '+price+' ₽, '+(x.price===best?'самая низкая среди найденных туров':'проверить дату')+'"><span>'+label+'</span><strong>'+price+' ₽</strong>'+(x.price===best?'<small>самая низкая</small>':'<small>проверить дату</small>')+'</button>';}).join('')+'</div><p class="current-price-calendar__note">Это текущие цены из уже выполненного поиска, а не история. Нажмите дату, чтобы перепроверить предложения именно на неё.</p>'+(compact?'</details>':'');box.hidden=false;return days;}
function clear(){disclosureOpen=null;const box=document.getElementById('currentPriceCalendar');if(box){box.hidden=true;box.innerHTML='';}}
function complete(event){terminal=true;render(filteredItems||event&&event.detail&&event.detail.items);}
function reset(event){if(!(event&&event.detail&&event.detail.dirty)){terminal=false;filteredItems=null;}clear();}
window.addEventListener('v2:search-complete',complete);
window.addEventListener('v2:search-continued',complete);
window.addEventListener('search3:local-results-filtered',e=>{filteredItems=e&&e.detail&&e.detail.items;if(terminal)render(filteredItems);});
window.addEventListener('v2:search-started',reset);
window.addEventListener('v2:search-reset',reset);
document.addEventListener('click',e=>{const btn=e.target&&e.target.closest&&e.target.closest('[data-calendar-date]');if(!btn)return;const form=document.getElementById('tourSearch'),date=dateValue(btn.dataset.calendarDate);if(!form||!date)return;const from=form.elements.dateFrom,to=form.elements.dateTo;if(!from||!to)return;e.preventDefault();from.value=date;to.value=date;from.dispatchEvent(new Event('input',{bubbles:true}));to.dispatchEvent(new Event('input',{bubbles:true}));if(window.V2SearchLifecycle&&typeof window.V2SearchLifecycle.submit==='function')window.V2SearchLifecycle.submit();});
window.V2CurrentPriceCalendar={collect,render,clear,dateValue,version:3};
})();