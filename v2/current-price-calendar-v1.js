(function(){'use strict';
if(window.V2CurrentPriceCalendar)return;
const money=new Intl.NumberFormat('ru-RU'),dayFormatter=new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short',weekday:'short',timeZone:'UTC'});
let terminal=false,filteredItems=null,disclosureOpen=null,pendingFocus=false,selectedDate='',availableDays=[];
function dateValue(raw){
const s=String(raw||'').trim(),iso=s.match(/^(\d{4})-(\d{2})-(\d{2})$/),local=iso?null:s.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
if(!iso&&!local)return'';
const parts=iso?iso.slice(1):[local[3],local[2],local[1]],year=Number(parts[0]),month=Number(parts[1]),day=Number(parts[2]),leap=year%4===0&&(year%100!==0||year%400===0),days=[31,leap?29:28,31,30,31,30,31,31,30,31,30,31];
return year>0&&month>=1&&month<=12&&day>=1&&day<=days[month-1]?parts.join('-'):'';
}
function dateLabel(iso){const d=new Date(iso+'T12:00:00Z');if(Number.isNaN(d.getTime()))return iso;return dayFormatter.format(d).replace(/\.$/,'');}
function collect(items){const byDate=new Map();(Array.isArray(items)?items:[]).forEach(h=>{(Array.isArray(h&&h.tours)?h.tours:[]).forEach(t=>{const date=dateValue(t&&t.date),price=Number(t&&t.price||0);if(!date||!Number.isFinite(price)||price<=0)return;const previous=byDate.get(date);if(previous===undefined||price<previous)byDate.set(date,price);});});return Array.from(byDate.entries()).map(([date,price])=>({date,price})).sort((a,b)=>a.date.localeCompare(b.date));}
function ensure(){let box=document.getElementById('currentPriceCalendar');if(box)return box;const tools=document.getElementById('resultsTools'),results=document.getElementById('results');if(!tools&&!results)return null;box=document.createElement('section');box.id='currentPriceCalendar';box.className='current-price-calendar';box.hidden=true;box.setAttribute('aria-labelledby','currentPriceCalendarTitle');(tools||results).insertAdjacentElement('beforebegin',box);return box;}
function focusFallback(){
const form=document.getElementById('tourSearch');
const target=[document.getElementById('resultsSearchEdit'),form&&form.elements.dateFrom].find(node=>node&&node.getClientRects().length&&getComputedStyle(node).visibility!=='hidden');
if(target)target.focus({preventScroll:true});
}
function updateNavigation(box){
const strip=box&&box.querySelector('.current-price-calendar__days'),nav=box&&box.querySelector('.current-price-calendar__navigation');
if(!strip||!nav)return;
const end=strip.scrollWidth-strip.clientWidth;
nav.hidden=strip.clientWidth===0||end<=1;
nav.querySelector('[data-calendar-move="previous"]').setAttribute('aria-disabled',String(strip.scrollLeft<=1));
nav.querySelector('[data-calendar-move="next"]').setAttribute('aria-disabled',String(strip.scrollLeft>=end-1));
if(nav.hidden&&nav.contains(document.activeElement))box.querySelector('summary').focus({preventScroll:true});
}
function navigate(box,button){
const strip=box.querySelector('.current-price-calendar__days');if(!strip||button.getAttribute('aria-disabled')==='true')return;
if(button.hasAttribute('data-calendar-best')){
const best=availableDays.reduce((lowest,day)=>!lowest||day.price<lowest.price?day:lowest,null);
const target=best&&Array.from(strip.children).find(node=>node.dataset.calendarDate===best.date);
if(target){target.focus({preventScroll:true});strip.scrollLeft+=target.getBoundingClientRect().left-strip.getBoundingClientRect().left-5;}
}else{
const first=strip.firstElementChild;if(!first)return;
const tile=first.getBoundingClientRect().width+(parseFloat(getComputedStyle(strip).columnGap)||0);
const step=tile*Math.max(1,Math.floor((strip.clientWidth-10)/tile));
strip.scrollLeft+=(button.dataset.calendarMove==='previous'?-1:1)*step;
}
updateNavigation(box);
}
function updateSelection(box){
const selected=availableDays.find(day=>day.date===selectedDate),actions=box.querySelector('.current-price-calendar__actions');
if(!actions)return;
box.querySelectorAll('[data-calendar-date]').forEach(button=>button.setAttribute('aria-pressed',String(!!selected&&button.dataset.calendarDate===selected.date)));
actions.hidden=!selected;
if(selected){
actions.querySelector('[role="status"]').textContent='Вылет '+dateLabel(selected.date)+' · от '+money.format(selected.price)+' ₽ за весь тур';
const apply=actions.querySelector('[data-calendar-apply]');
apply.textContent='Найти туры на '+dateLabel(selected.date).replace(/^.*?,\s*/,'');
apply.disabled=false;
}
}
function submitDate(date){
const form=document.getElementById('tourSearch'),lifecycle=window.V2SearchLifecycle;
if(!form||!date||!lifecycle||typeof lifecycle.submit!=='function')return;
const from=form.elements.dateFrom,to=form.elements.dateTo;if(!from||!to)return;
from.value=date;to.value=date;
from.dispatchEvent(new Event('input',{bubbles:true}));to.dispatchEvent(new Event('input',{bubbles:true}));
lifecycle.submit();
}
function render(items){
const box=ensure();if(!box)return[];
const compact=document.body.classList.contains('search3-candidate'),active=document.activeElement;
const focused=compact&&box.contains(active)?active:null;
const focusedDate=focused?dateValue(focused.getAttribute('data-calendar-date')):'';
const focusedApply=focused&&focused.hasAttribute('data-calendar-apply');
const focusedMove=focused&&focused.getAttribute('data-calendar-move');
const focusedBest=focused&&focused.hasAttribute('data-calendar-best');
const previousDays=box.querySelector('.current-price-calendar__days'),scrollLeft=previousDays?previousDays.scrollLeft:0;
const previous=box.querySelector('details');if(previous)disclosureOpen=previous.open;
const days=collect(items);
availableDays=days;if(!days.some(day=>day.date===selectedDate))selectedDate='';
if(days.length<2){
selectedDate='';
box.hidden=true;box.innerHTML='';
if(focused)focusFallback();
return days;
}
const best=Math.min.apply(null,days.map(x=>x.price));
const bestDay=days.find(day=>day.price===best),bestLabel=dateLabel(bestDay.date);
const expanded=disclosureOpen===null?(compact||window.matchMedia('(min-width:701px)').matches):disclosureOpen,head=compact?'summary':'div';
box.innerHTML=(compact?'<details'+(expanded?' open':'')+'>':'')+'<'+head+' class="current-price-calendar__head"><span class="current-price-calendar__heading"><span>Цены по датам</span><strong id="currentPriceCalendarTitle">'+(compact?'Календарь цен':'Когда дешевле вылететь')+'</strong></span><small>Минимум среди найденных туров</small></'+head+'>'+(compact?'<div class="current-price-calendar__navigation" role="group" aria-label="Просмотр дат" hidden><button type="button" data-calendar-best aria-label="Показать минимальную найденную цену: '+money.format(best)+' ₽, '+bestLabel+'">К минимуму · '+bestLabel.replace(/^.*?,\s*/,'')+'</button><button type="button" data-calendar-move="previous" aria-label="Предыдущие даты"><span aria-hidden="true">‹</span></button><button type="button" data-calendar-move="next" aria-label="Следующие даты"><span aria-hidden="true">›</span></button></div>':'')+'<div class="current-price-calendar__days">'+days.map(x=>{const label=dateLabel(x.date),price=money.format(x.price),fullDate=x.date.split('-').reverse().join('.');return '<button type="button" class="current-price-calendar__day'+(x.price===best?' is-best':'')+'" data-calendar-date="'+x.date+'"'+(compact?' aria-pressed="false"':'')+' aria-label="'+label+' ('+fullDate+'), от '+price+' ₽ за весь тур, '+(x.price===best?'самая низкая среди найденных туров':compact?'выбрать дату':'проверить дату')+'"><span>'+label+'</span><strong>'+price+' ₽</strong>'+(x.price===best?'<small>самая низкая</small>':'<small>'+(compact?'за весь тур':'проверить дату')+'</small>')+'</button>';}).join('')+'</div>'+(compact?'<div class="current-price-calendar__actions" hidden><p role="status" aria-live="polite" aria-atomic="true"></p><button type="button" data-calendar-apply></button></div>':'')+'<p class="current-price-calendar__note">'+(compact?'Цены «от» по найденным турам. Выберите дату и подтвердите новый поиск — остальные условия поездки сохранятся. Цена может измениться.':'Это текущие цены из уже выполненного поиска, а не история. Нажмите дату, чтобы перепроверить предложения именно на неё.')+'</p>'+(compact?'</details>':'');box.hidden=false;
if(compact){
updateSelection(box);
const strip=box.querySelector('.current-price-calendar__days');strip.scrollLeft=scrollLeft;
strip.addEventListener('scroll',()=>updateNavigation(box),{passive:true});
box.querySelector('details').addEventListener('toggle',()=>updateNavigation(box));
updateNavigation(box);
if(focused){
const day=expanded&&focusedDate?Array.from(strip.children).find(node=>node.dataset.calendarDate===focusedDate):null;
const nav=box.querySelector('.current-price-calendar__navigation');
const control=expanded&&!nav.hidden?(focusedBest?nav.querySelector('[data-calendar-best]'):Array.from(nav.querySelectorAll('[data-calendar-move]')).find(node=>node.dataset.calendarMove===focusedMove)):null;
const target=day||control||(expanded&&focusedApply&&selectedDate?box.querySelector('[data-calendar-apply]'):null)||box.querySelector('summary');
if(target)target.focus({preventScroll:true});
if(day){
const rect=day.getBoundingClientRect(),viewport=strip.getBoundingClientRect();
if(rect.left-5<viewport.left)strip.scrollLeft-=viewport.left-rect.left+5;
else if(rect.right+5>viewport.right)strip.scrollLeft+=rect.right+5-viewport.right;
}
updateNavigation(box);
}
}
return days;}
function clear(){disclosureOpen=null;selectedDate='';availableDays=[];const box=document.getElementById('currentPriceCalendar'),restoreFocus=box&&box.contains(document.activeElement);if(box){box.hidden=true;box.innerHTML='';}if(restoreFocus){pendingFocus=true;focusFallback();}}
function complete(event){terminal=true;render(filteredItems||event&&event.detail&&event.detail.items);if(pendingFocus){pendingFocus=false;focusFallback();}}
function reset(event){if(!(event&&event.detail&&event.detail.dirty)){terminal=false;filteredItems=null;}clear();}
window.addEventListener('v2:search-complete',complete);
window.addEventListener('v2:search-continued',complete);
window.addEventListener('search3:local-results-filtered',e=>{filteredItems=e&&e.detail&&e.detail.items;if(terminal)render(filteredItems);});
window.addEventListener('v2:search-started',reset);
window.addEventListener('v2:search-reset',reset);
window.addEventListener('resize',()=>updateNavigation(document.getElementById('currentPriceCalendar')),{passive:true});
document.addEventListener('click',e=>{
const btn=e.target&&e.target.closest&&e.target.closest('[data-calendar-date],[data-calendar-apply],[data-calendar-move],[data-calendar-best]');
if(!btn)return;const box=btn.closest('#currentPriceCalendar');if(!box||box.hidden)return;
if(btn.hasAttribute('data-calendar-move')||btn.hasAttribute('data-calendar-best')){e.preventDefault();navigate(box,btn);return;}
if(btn.hasAttribute('data-calendar-apply')){
if(btn.disabled||!availableDays.some(day=>day.date===selectedDate))return;
// Disabling a focused button moves focus to body before clear() can capture it.
// Retain the existing immediate/terminal recovery across this explicit submit.
if(box.contains(document.activeElement)){pendingFocus=true;focusFallback();}
e.preventDefault();btn.disabled=true;submitDate(selectedDate);return;
}
const date=dateValue(btn.dataset.calendarDate);if(!date||!availableDays.some(day=>day.date===date))return;
e.preventDefault();
if(document.body.classList.contains('search3-candidate')){selectedDate=date;updateSelection(box);}else submitDate(date);
});
window.V2CurrentPriceCalendar={collect,render,clear,dateValue,version:4};
})();
