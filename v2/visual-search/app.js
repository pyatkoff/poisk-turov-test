'use strict';
(() => {
const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
const prototypeVersion=147;
// Optional shortlisting is paused by the owner. Keep stored choices intact, but
// keep every visible route focused on search → hotel → exact tour.
const optionalShortlistEnabled=false;
$('.prototype-lab strong').textContent='Прототип v'+prototypeVersion;
 $('#prototype-version').textContent='Прототип v'+prototypeVersion;
$('.footer-inner>span').textContent='Search3 · v'+prototypeVersion+' · 25.09.2026';
const paths = {
 sort:'<path d="M8 4v16m-4-4 4 4 4-4M14 5h6M14 10h4M14 15h2"/>',
 info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7v1"/>',
 suitcase:'<rect x="4" y="6" width="16" height="15" rx="3"/><path d="M9 6V3h6v3M8 10v7M16 10v7"/>',
 search:'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>',
 plane:'<path d="m22 2-7 20-4-9-9-4L22 2Z M11 13l6-6"/>',
 pin:'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
 calendar:'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4M17 3v4M3 11h18M8 15h2M14 15h2"/>',
 moon:'<path d="M20 14a9 9 0 0 1-10-10A9 9 0 1 0 20 14Z"/>',
 users:'<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-3-5"/>',
 heart:'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
 compare:'<path d="M5 20V10M12 20V4M19 20v-7M3 20h18"/>',
 sliders:'<path d="M4 6h6m4 0h6M4 12h11m4 0h1M4 18h1m4 0h11"/><circle cx="12" cy="6" r="2"/><circle cx="17" cy="12" r="2"/><circle cx="7" cy="18" r="2"/>',
 check:'<path d="m5 12 4 4L19 6"/>',
 shield:'<path d="m12 3 8 3v6c0 6-8 10-8 10S4 18 4 12V6l8-3Z M8 12l3 3 5-6"/>',
 waves:'<path d="M2 7q2-3 5 0t5 0 5 0 5 0M2 13q2-3 5 0t5 0 5 0 5 0M2 19q2-3 5 0t5 0 5 0 5 0"/>',
 x:'<path d="m6 6 12 12M6 18 18 6"/>',
 image:'<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m21 15-5-5L5 21"/>',
 arrow:'<path d="M4 12h16m-6-6 6 6-6 6"/>',
 back:'<path d="M20 12H4m6-6-6 6 6 6"/>',
 food:'<path d="M5 3v7m3-7v7M3 3v7q2.5 4 5 0M5.5 13v9M17 22V3q-6 6 0 10"/>'
};
const scrollBehavior=()=>matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth';
const icon = n => `<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">${paths[n]||paths.check}</svg>`;
const hydrate = () => $$('[data-icon]').forEach(el=>{el.outerHTML=icon(el.dataset.icon)});
const esc = s => String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
// Reuse presentation formatters across cards and calendar cells.
const amountFormatter=new Intl.NumberFormat('ru-RU',{maximumFractionDigits:2});
const shortAmountFormatter=new Intl.NumberFormat('ru-RU',{maximumFractionDigits:1});
const ratingFormatter=new Intl.NumberFormat('ru-RU',{minimumFractionDigits:1,maximumFractionDigits:1});
const dayFormatter=new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short',timeZone:'UTC'});
const fullDayFormatter=new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'});
const monthFormatter=new Intl.DateTimeFormat('ru-RU',{month:'long',year:'numeric',timeZone:'UTC'});
const money = n => amountFormatter.format(Number(n))+' ₽';
const shortAmount = n => shortAmountFormatter.format(n/1000);
const shortMoney = n => shortAmount(n)+' тыс.';
const dateObj = s=>new Date(s+'T12:00:00Z');
const iso = d=>d.toISOString().slice(0,10);
const addDays = (s,n)=>iso(new Date(dateObj(s).getTime()+n*86400000));
const formatDate=(formatter,date)=>Number.isNaN(date.getTime())?'Invalid Date':formatter.format(date);
const dateText = s=>formatDate(dayFormatter,dateObj(s)).replace('.','');
const dateLong = s=>formatDate(fullDayFormatter,dateObj(s));
const flightDateText = s=>{const raw=String(s||'');const iso=/^\d{8}$/.test(raw)?`${raw.slice(0,4)}-${raw.slice(4,6)}-${raw.slice(6,8)}`:raw;return /^\d{4}-\d{2}-\d{2}$/.test(iso)&&!Number.isNaN(dateObj(iso).getTime())?dateText(iso):raw||'Дата уточняется';};
const rangeText = (a,b)=>a===b?dateText(a):`${dateText(a)} — ${dateText(b)}`;
const nightsText = n=>`${n} ${n%10===1&&n!==11?'ночь':n%10>=2&&n%10<=4&&(n<12||n>14)?'ночи':'ночей'}`;
const childAgeText = n=>n===0?'до года':`${n} ${n%10===1&&n!==11?'год':n%10>=2&&n%10<=4&&(n<12||n>14)?'года':'лет'}`;
const childAgesText = ages=>ages.map(childAgeText).join(', ');
const childAgesLabel = (ages,lowercase=false)=>`${lowercase?'возраст':'Возраст'} ${ages.length===1?'ребёнка':'детей'}: ${childAgesText(ages)}`;
const countryNames={};
const mealNames={};
const amenityNames=new Map();
const countriesTo={};
const operators=[];
const data=window.AnyTourPrototypeData;
const popularity=window.AnyTourHotelPopularityV1;
const flightLabel=o=>o.flight==='regular'?'Регулярный':o.flight==='charter'?'Чартер':'Тип рейса уточняется';
const needsRefresh=o=>o.cached||(!data.preview&&o.provider!=='tourvisor')||o.raw?.selectionEnabled===false;
const offerActionLabel=o=>needsRefresh(o)?'Смотреть условия':'Выбрать тур';
const refreshOfferActionLabel=o=>o.cached?'Найти актуальные туры':'Проверить этот тур';
const refreshOfferNotice=o=>data.live?'Предложение из текущего поиска. Проверьте цену, наличие и рейсы для этих условий.':'Снимок от 23.09.2026. Цена и наличие не обновляются.';
const aboutDisclosureHTML=()=>data.live
 ? `<p class="modal-intro">Версия v${prototypeVersion} получает актуальные предложения только из живого поиска ТВ, SAMO/Andromeda и ANEX. База не подменяет живую выдачу: ранее найденные цены используются только в календаре.</p><p class="modal-intro">Для конкретного предложения выполняется доступная поставщику актуализация цены, наличия и рейсов. Неподтверждённая или расчётная сумма показывается с явным статусом и не выдаётся за финально подтверждённую.</p><p class="modal-intro">Формы заявки доступны только для проверки сценария: данные не отправляются туроператору, бронирование и оплата отключены.</p>`
 : `<p class="modal-intro">Демонстрационный сценарий v${prototypeVersion} работает на сохранённых и учебных данных. Цены и наличие не обновляются, сценарии рейсов могут быть вымышленными.</p><p class="modal-intro">Формы заявки доступны только для проверки интерфейса: данные не отправляются, бронирование и оплата отключены.</p>`;
const cachedPriceNote=o=>data.scenario==='live'?'Цена из базы AnyTour · наличие требует проверки':'Цена из снимка 23.09.2026 · наличие не проверяется';
const priceNote=o=>o.provider==='recorded'?(o.recordingKind==='demo'?'Демонстрационная запись · не для бронирования':'Загруженная запись · не для бронирования'):o.provider==='fixture'?'Демонстрационная цена · не для бронирования':o.cached?cachedPriceNote(o):needsRefresh(o)?'Цена из текущего поиска · требует подтверждения':'Цена предложения · сборы уточняются';
const cardPriceNote=o=>o.provider==='recorded'?(o.recordingKind==='demo'?'Демо · не для бронирования':'Запись · не для бронирования'):o.provider==='fixture'?'Демо · не для бронирования':o.cached?(data.scenario==='live'?'Цена из базы · требует проверки':'Снимок · цена требует проверки'):priceNote(o);
const offerMetaNote=o=>needsRefresh(o)?priceNote(o):'Рейсы и багаж — при выборе';
// Meal identities and names are provided by the data adapter; UI does not map supplier aliases.
const mealLabel=o=>o.meal||'Питание уточняется';
const matchesMeal=(o,labels)=>!labels.length||(data.live?labels.some(label=>Number.isSafeInteger(mealNames[label])&&mealNames[label]===o.mealPlanId):labels.includes(o.meal));
const mealHelp={'Без питания':'Питание не включено в стоимость','Завтраки':'В стоимость включён завтрак','Полупансион':'Два приёма пищи в день','Полный пансион':'Три приёма пищи в день','Всё включено':'Состав питания и напитков зависит от отеля','Ультра всё включено':'Расширенная программа; точный состав зависит от отеля','Питание уточняется':'Состав питания не указан в предложении'};
const photoUrl=(h,i=0)=>h.photos[i]||'./assets/photo-unavailable.svg';
const operatorAssets={'ANEX':'anex.svg','Coral Travel':'coral-travel.png','FUN&SUN':'fun-sun.svg','Библио-Глобус':'biblio-globus.svg'};
function operatorBadge(name){const file=operatorAssets[name],label='Туроператор: '+name;return `<button type="button" class="operator-logo ${file?'':'operator-text'}" data-action="operator-info" data-operator="${esc(name)}" aria-label="${esc(label)}" title="${esc(label)}">${file?`<img src="./assets/operators/${file}" alt="">`:esc(name)}<span class="operator-tooltip" aria-hidden="true">${esc(label)}</span></button>`;}
const arrivalCity=h=>data.text(h.raw?.arrival)||h.resort||countryNames[h.country];
function minimumOfferSummary(o){return `<div class="card-minimum-offer" data-minimum-key="${esc(o.key)}"><span class="minimum-label">Тур по указанной цене</span><strong>${dateText(o.day)} → ${dateText(o.returnDay)} · ${nightsText(o.nights)}</strong><p class="card-meal-line">${icon('food')}<span>${esc(mealLabel(o))}</span></p><details class="card-room-disclosure"><summary><span><span class="card-room-label">Номер</span> ${esc(o.room)}</span><span class="card-room-expand" aria-hidden="true">⌄</span></summary><dl class="minimum-stay"><div><dt>Номер</dt><dd>${esc(o.room)}</dd></div></dl><div class="card-offer-flight"><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}</div></details></div>`;}
function selectionStepsHTML(step,{flightDeferred=false}={}){return `<ol class="selection-steps" aria-label="Этапы выбора тура">${['Номер и питание',flightDeferred?'Перелёт позже':'Перелёт','Заявка'].map((label,i)=>{const deferred=flightDeferred&&i===1;return `<li ${i===step?'aria-current="step"':''} class="${i===step?'current':i<step&&!deferred?'previous':''}"><span aria-hidden="true">${i+1}</span>${label}</li>`;}).join('')}</ol>`;}
const startDay=data.clockStart||addDays(iso(new Date()),1),endDay=addDays(startDay,180);
let hotels=[];
let resultCalendar={key:null,hotels:[],observations:[],phase:'idle',controller:null};

const defaultFilters=()=>({hotelId:0,q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false});
const getStored=(key,fallback)=>{try{return JSON.parse(localStorage.getItem(key))??fallback}catch{return fallback}};
const saveStored=(key,value)=>{try{localStorage.setItem(key,JSON.stringify(value))}catch{toast('Сохранение в браузере недоступно; выбор останется до перезагрузки.')}};
const clearStored=key=>{try{localStorage.removeItem(key)}catch{}};
const validIds=ids=>Array.isArray(ids)?[...new Set(ids.filter(id=>Number.isSafeInteger(id)&&id>0))].slice(0,20):[];
const state={search:structuredClone(data.initialSearch),filters:defaultFilters(),selectedDate:null,sort:'recommended',openHotel:null,favorites:[],compare:[],onlyFavorites:false,hasSearched:false,photoIndexes:{}};
const modalHistory=[];let restoringModal=false;
let draftDestination=null,destinationChoice=null,draft=structuredClone(state.search),modalType='',guestDraft={},nightsDraft={},dateDraft={},calendarMonth=startDay.slice(0,7)+'-01',gallery={id:null,index:0},selectedOffer=null,searchTimer=null,searchStageTimer=null,hotelRoomObserver=null,urlStateHydrated=false;
let searchResponse={key:'',phase:'complete',operators:[...operators],pending:false};
const searchKey=s=>JSON.stringify([s.origin,s.country,s.from,s.to,s.minNights,s.maxNights,s.adults,s.ages]);
const responseFor=s=>searchResponse.key===searchKey(s)?searchResponse:{phase:'complete',operators,pending:false};
const ratingValue=h=>Number.isFinite(h.rating)&&h.rating>0&&h.rating<=5?h.rating:null;
const ratingText=h=>ratingValue(h)===null?'—':ratingFormatter.format(ratingValue(h));
function hotelStarsHTML(h){const stars=Number(h?.stars);if(!Number.isInteger(stars)||stars<1||stars>5)return'';const label=stars===1?'1 звезда':stars<5?stars+' звезды':stars+' звёзд';return `<div class="hotel-stars" aria-label="${label}">${'★'.repeat(stars)}</div>`;}
const guestsText=(s=state.search)=>`${s.adults} взр.${s.ages.length?' + '+s.ages.length+' реб.':''}`;
const durationText=(s=state.search)=>s.minNights===s.maxNights?nightsText(s.minNights):`${s.minNights}–${s.maxNights} ночей`;
const departureScopeLabel=(s=state.search,selected=state.selectedDate)=>selected||s.from===s.to?'Вылет':'Даты вылета';
const departureScopeValue=(s=state.search,selected=state.selectedDate)=>selected?dateText(selected):rangeText(s.from,s.to);
const departureScopeText=(s=state.search,selected=state.selectedDate)=>`${departureScopeLabel(s,selected)} ${departureScopeValue(s,selected)}`;
function restoreURL(){
 const p=new URLSearchParams(location.search),s=state.search;
 if(countryNames[p.get('country')])s.country=p.get('country');
 if(data.catalog.departures.some(x=>data.text(x)===p.get('origin')))s.origin=p.get('origin');
 const valid=v=>/^\d{4}-\d{2}-\d{2}$/.test(v||'')&&v>=startDay&&v<=endDay&&data.date(v)===v;
 if(valid(p.get('from'))&&valid(p.get('to'))&&p.get('from')<=p.get('to')&&(dateObj(p.get('to'))-dateObj(p.get('from')))/86400000<=21){s.from=p.get('from');s.to=p.get('to');}
 const selectedDay=p.get('date');state.selectedDate=valid(selectedDay)&&selectedDay>=s.from&&selectedDay<=s.to?selectedDay:null;
 for(const k of ['minNights','maxNights','adults']){const n=Number(p.get(k));if(Number.isInteger(n)&&n>=1&&n<=(k==='adults'?6:28))s[k]=n;}
 if(s.maxNights<s.minNights||s.maxNights-s.minNights>10)s.maxNights=s.minNights;
 if(p.has('ages'))s.ages=p.get('ages').split(',').filter(x=>/^\d+$/.test(x)&&Number(x)<=17).slice(0,3).map(Number);
 const hotelId=p.get('hotel');if(/^[1-9]\d*$/.test(hotelId||'')&&Number.isSafeInteger(Number(hotelId)))state.filters.hotelId=Number(hotelId);
 state.filters.stars=(p.get('stars')||'').split('|').map(Number).filter(n=>Number.isInteger(n)&&n>=1&&n<=5);
 for(const k of ['min','max'])if(p.has(k)&&p.get(k).trim()!==''&&Number.isFinite(Number(p.get(k))))state.filters[k]=Math.max(0,Number(p.get(k)));
 if(state.filters.max!==null&&state.filters.min>state.filters.max)state.filters.min=state.filters.max;
 state.filters.meals=[...new Set((p.get('meals')||'').split('|').filter(Boolean).map(data.meal).filter(Boolean))];
 for(const k of ['resorts','operators'])state.filters[k]=[...new Set((p.get(k)||'').split('|').filter(Boolean))];
 state.filters.amenities=[...new Set((p.get('amenities')||'').split('|').filter(x=>/^(1|2|3|5|8):[1-9]\d*$/.test(x)))];
 state.filters.flight=[...new Set((p.get('flight')||'').split('|').filter(value=>['charter','regular'].includes(value)))];
 state.filters.q=p.get('q')||'';
 for(const key of ['rating','beach','family','spa'])state.filters[key]=p.get(key)==='1';
 if(['recommended','price','rating'].includes(p.get('sort')))state.sort=p.get('sort');
 $('#sort').value=state.sort;
 state.hasSearched=false;draft=structuredClone(s);
}
let searchEditSession=null;
function updateURL(){if(searchEditSession||!urlStateHydrated)return;const p=new URLSearchParams();p.set('scenario',data.scenario);for(const [k,v] of Object.entries(state.search))p.set(k,Array.isArray(v)?v.join(','):v);if(state.selectedDate)p.set('date',state.selectedDate);if(state.hasSearched)p.set('searched','1');if(state.onlyFavorites)p.set('favorites','1');const f=state.filters;for(const k of ['stars','meals','resorts','operators','flight','amenities'])if(f[k].length)p.set(k,f[k].join('|'));for(const k of ['beach','rating','family','spa'])if(f[k])p.set(k,'1');if(f.hotelId)p.set('hotel',f.hotelId);if(f.q)p.set('q',f.q);if(f.min)p.set('min',f.min);if(f.max!==null)p.set('max',f.max);if(state.sort!=='recommended')p.set('sort',state.sort);const query='?'+p.toString();if(location.search!==query||location.hash)history.replaceState(history.state,'',query);}
const generatedRootBindings=new WeakMap();
// Bind exact, freshly generated markup to each live root. One observer per
// container invalidates only roots edited outside this painter.
function parseGeneratedRoot(markup){const template=document.createElement('template');template.innerHTML=markup;return template.content.firstElementChild;}
function paintGeneratedRoots(container,entries,sameScope=true,field='id'){
 let binding=generatedRootBindings.get(container);
 if(!binding){
  const markup=new WeakMap(),dirty=new WeakSet();
  const mark=records=>{for(const record of records){let root=record.target.nodeType===1?record.target:record.target.parentElement;while(root&&root.parentNode!==container)root=root.parentElement;if(root&&root.parentNode===container)dirty.add(root);}};
  const observer=new MutationObserver(mark);observer.observe(container,{subtree:true,childList:true,attributes:true,characterData:true});
  binding={markup,dirty,mark,observer};generatedRootBindings.set(container,binding);
 }else binding.mark(binding.observer.takeRecords());
 const {markup:known,dirty,observer}=binding;
 if(!sameScope||!container.childElementCount){container.innerHTML=entries.map(entry=>entry.markup).join('');[...container.children].forEach((node,index)=>known.set(node,entries[index]?.markup));observer.takeRecords();return;}
 const keyed=new Map();for(const node of container.children){const key=field==='day'?node.dataset.date:node.id;if(key&&!keyed.has(key))keyed.set(key,node);}
 let cursor=container.firstChild;
 for(const entry of entries){
  const key=entry[field],markup=entry.markup,previous=field==='day'||key?keyed.get(key):cursor;keyed.delete(key);
  const node=previous&&!dirty.has(previous)&&known.get(previous)===markup?previous:parseGeneratedRoot(markup);
  known.set(node,markup);dirty.delete(node);
  if(node===cursor)cursor=cursor.nextSibling;
  else if(cursor&&previous===cursor){container.replaceChild(node,cursor);cursor=node.nextSibling;}
  else container.insertBefore(node,cursor);
 }
 while(cursor){const next=cursor.nextSibling;cursor.remove();cursor=next;}
 observer.takeRecords();
}
function renderSummary(){const s=state.search,f=state.filters;paintGeneratedRoots($('#applied-search'),[{id:'',markup:`<div class="applied-main"><div class="applied-route"><span class="summary-icon">${icon('plane')}</span><div><small>Маршрут</small><strong>${esc(s.origin)}<span>→</span>${esc(destinationLabel(appliedDestination()))}</strong></div></div><dl class="applied-trip"><div><dt>${departureScopeLabel(s)}</dt><dd>${departureScopeValue(s)}</dd></div><div><dt>Отдых</dt><dd>${durationText()}</dd></div><div><dt>Туристы</dt><dd>${guestsText()}</dd></div></dl><button class="secondary" data-action="edit-search" aria-controls="search-form" aria-expanded="false">${icon('sliders')} Изменить</button></div>`},{id:'',markup:`<div class="applied-extras"><button type="button" data-action="filters" aria-label="Звёзды: ${esc(f.stars.length?f.stars.join(', '):'любая категория')}. Открыть фильтры"><span class="applied-extra-label">Звёзды</span><strong>${esc(f.stars.length?[...f.stars].sort((a,b)=>a-b).map(n=>n+' ★').join(', '):'Любая')}</strong></button><button type="button" data-action="meals" aria-haspopup="dialog" title="${esc(f.meals.join(' · ')||'Любое питание')}" aria-label="Питание: ${esc(f.meals.join(', ')||'любое')}"><span class="applied-extra-label">Питание</span><strong>${esc(f.meals.length>1?f.meals.length+' варианта':f.meals[0]||'Любое')}</strong></button><button type="button" data-action="budget" aria-haspopup="dialog"><span class="applied-extra-label">Бюджет за всех</span><strong>${esc(budgetLabel(f))}</strong></button><button type="button" class="applied-all-filters" data-action="filters">${icon('sliders')} Все фильтры${filterCount()?' · '+filterCount():''}</button></div>`}]);}
function collapseSearch(){renderSummary();$('#search-form').hidden=true;$('.intro').hidden=true;$('#applied-search').hidden=false;$('#search').classList.add('search-collapsed');document.body.classList.remove('search-editing');}
function editSearch(){
 if(searchResponse.pending||Object.values(searchResponse.providers||{}).includes('loading'))stopSearch();if(innerWidth<=1100)closeFilters();
 $('#results').classList.remove('filters-requested');
 if(state.hasSearched&&!searchEditSession){
  searchEditSession={filters:structuredClone(state.filters),selectedDate:state.selectedDate,onlyFavorites:state.onlyFavorites,openHotel:state.openHotel,y:scrollY,focus:focusReference(actionTrigger||document.activeElement)};
  draft=structuredClone(state.search);draftDestination=null;
 }
 $('#search-return').hidden=!state.hasSearched;$('#search-edit-note').hidden=!state.hasSearched;
 $('#page-title').textContent=state.hasSearched?'Изменить поиск':'Куда отправимся?';
 $('#search-form').hidden=false;$('.intro').hidden=false;$('#applied-search').hidden=true;$('#search').classList.remove('search-collapsed');document.body.classList.add('search-editing');
 updateSearchUI();$('#search').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#departure-button').focus({preventScroll:true});
}
function cancelSearchEdit(){
 const saved=searchEditSession,originChanged=draft.origin!==state.search.origin;searchEditSession=null;
 if(saved){state.filters=saved.filters;state.selectedDate=saved.selectedDate;state.onlyFavorites=saved.onlyFavorites;state.openHotel=saved.openHotel;}
 draft=structuredClone(state.search);draftDestination=null;filterBudgetEdit=null;
 updateSearchUI();collapseSearch();renderFilters();updateURL();
 if(originChanged)loadCountries(draft.origin);
 restoreFocus(saved?.focus,$('#applied-search [data-action="edit-search"]'));
 if(saved)requestAnimationFrame(()=>window.scrollTo({top:saved.y,behavior:'instant'}));
}
const hotelPlaces=h=>[...new Set([h.subRegion,h.region,h.resort].map(value=>String(value||'').trim()).filter(Boolean))];
function hotelMatch(h,f=state.filters,s=state.search,onlyFavorites=state.onlyFavorites){
 const places=f.resorts.length?hotelPlaces(h):null;
 return h.country===s.country&&(!f.hotelId||h.id===f.hotelId)
  &&matchesHotelQuery(h,f.q)
  &&(!f.stars.length||f.stars.includes(h.stars))
  &&(!f.resorts.length||f.resorts.some(resort=>places.includes(resort)))
  &&(f.amenities||[]).every(key=>(h.amenities||[]).some(a=>a.key===key))
  &&(!f.rating||ratingValue(h)>=4.5)&&(!f.beach||h.beach!==null&&h.beach<=150)&&(!f.family||h.family)&&(!f.spa||h.spa)
  &&(!onlyFavorites||s.country!==state.search.country||state.favorites.includes(h.id));
}
function hotelOfferPredicate(s,f,from,to){
 const ages=JSON.stringify([...s.ages].sort());
 return o=>o.search.origin===s.origin&&o.search.country===s.country&&o.adults===s.adults&&JSON.stringify([...o.ages].sort())===ages&&o.day>=from&&o.day<=to&&o.nights>=s.minNights&&o.nights<=s.maxNights&&matchesMeal(o,f.meals)&&o.total>=f.min&&(f.max===null||o.total<=f.max)&&(!f.operators.length||f.operators.includes(o.operator))&&(!f.flight.length||f.flight.includes(o.flight));
}
function hotelOffers(h,options={}){
 if(!h)return[];
 const s=options.search||state.search,f=options.filters||state.filters,selected=Object.hasOwn(options,'selectedDate')?options.selectedDate:state.selectedDate;
 const from=options.day||(selected&&!options.ignoreDate?selected:s.from),to=options.day||(selected&&!options.ignoreDate?selected:s.to);
 if(!hotelMatch(h,f,s,options.onlyFavorites??state.onlyFavorites))return[];
 const matches=hotelOfferPredicate(s,f,from,to),compare=(a,b)=>a.total-b.total||a.day.localeCompare(b.day);
 if(options.minimumOnly){let offer=null;const rows=h.offers||[];for(let index=0,length=rows.length;index<length;index++){if(!(index in rows))continue;const row=rows[index];if(matches(row)&&(!offer||compare(row,offer)<0))offer=row;}return offer?[offer]:[];}
 if(options.firstOnly){const offer=(h.offers||[]).find(matches);return offer?[offer]:[];}
 const offers=(h.offers||[]).filter(matches);
 return options.sort===false?offers:offers.sort(compare);
}
function recommendedHotelScore(h){return (ratingValue(h)??0)+(h.beach!==null&&h.beach<=150?.2:0)+(popularity?.boost(h)||0);}
function recommendedHotelRank(h){const rank=popularity?.rank(h);return Number.isInteger(rank)?rank:Number.MAX_SAFE_INTEGER;}
function sortResultItems(items){
 if(items.length<2)return items;
 if(state.sort==='price')return items.sort((a,b)=>a.offers[0].total-b.offers[0].total);
 if(state.sort==='rating')return items.sort((a,b)=>(ratingValue(b.hotel)??0)-(ratingValue(a.hotel)??0));
 // Rank only this current inventory, so progressive prices/profiles stay fresh.
 const ranked=items.map(row=>({row,score:recommendedHotelScore(row.hotel),rank:recommendedHotelRank(row.hotel)}));
 ranked.sort((a,b)=>b.score-a.score||a.rank-b.rank||a.row.offers[0].total-b.row.offers[0].total);
 return ranked.map(item=>item.row);
}
function resultInventory(){
 const rating=!!state.filters?.rating,options=rating?{filters:{...state.filters,rating:false}}:null;
 const inventory=hotels.reduce((value,h)=>{
  const rated=ratingValue(h)>=4.5,offers=rating?hotelOffers(h,rated?options:{...options,firstOnly:true}):hotelOffers(h);
  if(!offers.length)return value;
  value.ratingCounts.total++;if(rated)value.ratingCounts.rating++;
  if(!rating||rated)value.items.push({hotel:h,offers});
  return value;
 },{items:[],ratingCounts:{total:0,rating:0}});
 inventory.items=sortResultItems(inventory.items);return inventory;
}
function results(){return resultInventory().items;}
function calendarMinimums(days,options={},observations=[]){
 if(!days.length)return[];
 const s=options.search||state.search,f=options.filters||state.filters,span=[...days].sort(),requested=new Set(days),minimums=new Map(),saved=new Map();
 const search={...s,from:span[0],to:span.at(-1)};
 for(const h of options.calendarHotels||hotels)for(const o of hotelOffers(h,{...options,search,day:'',selectedDate:null,ignoreDate:true,onlyFavorites:false,sort:false})){
  if(requested.has(o.day))minimums.set(o.day,Math.min(minimums.get(o.day)??Infinity,o.total));
 }
 // Keep the first saved observation for a date, just as the old find() did.
 if(data.observationScopeSupported(s,f))for(const point of observations)if(!saved.has(point.date))saved.set(point.date,point.price);
 return days.map(day=>{const values=[minimums.get(day),saved.get(day)].filter(value=>Number.isFinite(value)&&value>0);return values.length?Math.min(...values):null;});
}
function filterCount(){return Object.entries(state.filters).reduce((n,[k,v])=>n+(Array.isArray(v)?v.length:k==='max'?(v!==null?1:0):k==='min'?(v>0?1:0):v?1:0),0);}
function toast(msg){const t=$('#toast');t.textContent=msg;t.hidden=false;clearTimeout(toast.timer);toast.timer=setTimeout(()=>t.hidden=true,3600);}
const draftSelectedDate=()=>draft.from===state.search.from&&draft.to===state.search.to?state.selectedDate:null;
function updateSearchUI(){
 const hydrationPending=!urlStateHydrated;
 $('#search-form').setAttribute('aria-busy',String(hydrationPending));
 $$('.intro [data-action="filters"],#search-form [data-action="departure"],#search-form [data-action="destination"],#search-form [data-action="dates"],#search-form [data-action="nights"],#search-form [data-action="guests"],#quick-stars button,#quick-meal,#quick-budget').forEach(control=>control.disabled=hydrationPending);
 const count=filterCount();$('#filter-count').textContent=count?`(${count})`:'';
 $('#origin').value=draft.origin;$('#origin-label').textContent=draft.origin;$('#departure-button').setAttribute('aria-label','Город вылета: '+draft.origin);const place=currentDraftDestination();$('#destination-label').textContent=destinationLabel(place);$('#country').title=destinationLabel(place,true);$('#country').setAttribute('aria-label','Направление: '+destinationLabel(place,true));
 $('#dates-label').textContent=draftSelectedDate()?dateText(draftSelectedDate()):rangeText(draft.from,draft.to);$('#nights-label').textContent=durationText(draft);$('#guests-label').textContent=guestsText(draft);
 $$('#quick-stars button').forEach(b=>{const active=b.dataset.action==='any-stars'?!state.filters.stars.length:state.filters.stars.includes(+b.dataset.value);b.setAttribute('aria-pressed',active);b.classList.toggle('active',active)});
 const meals=state.filters.meals.join(' · ');$('#meal-label').textContent=state.filters.meals.length>1?state.filters.meals.length+' варианта':meals||'Любое';$('#quick-meal').setAttribute('aria-label','Питание: '+(meals||'любое'));$('#quick-meal').title=meals||'Любое питание';
 $('#budget-label').textContent=budgetText();
 $('.search-submit').disabled=!catalogReady||!!(place.hotelId&&!data.preview&&!destinationHotel(place.hotelId)?.legacyIds.length);
 renderCatalogError();
}
function renderCatalogError(){
 let node=$('#catalog-error');
 if(!node){node=document.createElement('p');node.id='catalog-error';node.className='error-text';node.setAttribute('role','alert');$('#search-form .search-actions').before(node);}
 node.hidden=!catalogError||!catalogDeparture;
 node.innerHTML=node.hidden?'':`${esc(catalogError)} <button type="button" class="text-button" data-action="retry-countries">Повторить загрузку направлений</button>`;
 $('#country').setAttribute('aria-busy',String(!catalogReady&&!catalogError));
}
const normalizeSearch=s=>String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/ё/g,'е').trim();
const normalizeHotelQuery=value=>normalizeSearch(value).replace(/[^\p{L}\p{N}]+/gu,' ').trim();
const hotelQueryCache=new WeakMap();let lastHotelQuery=null,lastHotelWords=[];
function matchesHotelQuery(h,query){
 if(query!==lastHotelQuery){lastHotelQuery=query;lastHotelWords=normalizeHotelQuery(query).split(' ').filter(Boolean);}
 if(!lastHotelWords.length)return true;
 // Hotel identity is stable for this loaded object; a new response supplies new objects.
 if(!hotelQueryCache.has(h))hotelQueryCache.set(h,normalizeHotelQuery([h.name,...hotelPlaces(h)].join(' ')));
 const text=hotelQueryCache.get(h);return lastHotelWords.every(word=>text.includes(word));
}

const destinationHotels=new Map();
const destinationHotel=id=>hotels.find(h=>h.id===id&&h.legacyIds.length)||destinationHotels.get(id)||hotels.find(h=>h.id===id);
let destinationLookup={status:'idle',rows:[]},destinationRequest=null,destinationTimer=null,catalogError='',catalogReady=false,hotelRestorePending=false,catalogLoadGeneration=0,catalogDeparture='';
function cancelDestinationLookup(){clearTimeout(destinationTimer);destinationRequest?.abort();destinationRequest=null;destinationLookup={status:'idle',rows:[]};}
function syncDestinationViewport(){
 const m=$('#modal'),v=window.visualViewport,keyboard=modalType==='destination'&&m.open&&innerWidth<=760&&v&&v.height<innerHeight-120;
 m.classList.toggle('destination-keyboard',!!keyboard);
 if(keyboard){m.style.setProperty('--destination-top',(v.offsetTop+8)+'px');m.style.setProperty('--destination-height',Math.max(180,v.height-16)+'px');}
 else{m.style.removeProperty('--destination-top');m.style.removeProperty('--destination-height');}
}
window.visualViewport?.addEventListener('resize',syncDestinationViewport);
window.visualViewport?.addEventListener('scroll',syncDestinationViewport);
function lookupDestination(){
 cancelDestinationLookup();destinationResolvedQuery='';destinationHotelLimit=destinationHotelPageSize;$('#modal-body').scrollTop=0;const q=$('#destination-query').value.trim(),country=destinationChoice.country;
 if(!countryNames[country]||q.length<2){renderDestination();return;}
 const request=new AbortController();destinationRequest=request;destinationLookup={status:'loading',rows:[]};renderDestination();
 destinationTimer=setTimeout(async()=>{const timeout=setTimeout(()=>request.abort(),15000);try{
  const rows=await data.lookupHotels(q,country,request.signal);if(destinationRequest!==request||modalType!=='destination')return;
  rows.forEach(h=>destinationHotels.set(h.id,h));destinationLookup={status:'complete',rows};
 }catch(error){if(destinationRequest!==request||modalType!=='destination')return;destinationLookup={status:'error',rows:[]};}
 finally{clearTimeout(timeout);if(destinationRequest===request&&modalType==='destination'){destinationRequest=null;renderDestination();}}
 },180);
}
function appliedDestination(){return {country:state.search.country,resorts:[...state.filters.resorts],hotelId:state.filters.hotelId};}
const resortLoads=new Map();
const destinationResortPreviewLimit=6;
const destinationHotelPageSize=8;
let destinationResortsExpanded=false,destinationHotelLimit=destinationHotelPageSize,destinationResolvedQuery='';
async function loadResorts(country){
 if(!country||!countryNames[country])return;
 resortLoads.set(country,'loading');
 try{await data.regions(country);resortLoads.set(country,'complete');}
 catch{resortLoads.set(country,'error');}
 if(modalType==='destination'&&destinationChoice?.country===country)renderDestination();
}
function currentDraftDestination(){return draftDestination||{country:draft.country,resorts:draft.country===state.search.country?[...state.filters.resorts]:[],hotelId:draft.country===state.search.country?state.filters.hotelId:0};}
function destinationLabel(d,full=false){
 const h=destinationHotel(d.hotelId),country=countryNames[d.country]||'';
 if(h)return full?`${h.name}, ${h.resort}, ${countryNames[h.country]||''}`:h.name;
 if(d.hotelId)return hotelRestorePending?'Восстанавливаем отель…':'Выбранный отель недоступен';
 if(d.resorts.length)return full&&country?`${country} · ${d.resorts.join(', ')}`:d.resorts.length===1?d.resorts[0]:`${country?country+' · ':''}${d.resorts.length} ${d.resorts.length<5?'курорта':'курортов'}`;
 return country||(!catalogReady&&!catalogError?'Загружаем направления…':'Выберите направление');
}
function recentDestinations(){const v=getStored('anytour.prototype.v18.destinations.v1',[]);return (Array.isArray(v)?v:[]).filter(d=>d&&countryNames[d.country]&&Array.isArray(d.resorts)&&d.resorts.every(r=>(data.catalog.regions[d.country]||[]).some(row=>row.name===r))&&(!d.hotelId||destinationHotel(d.hotelId)?.country===d.country)).slice(0,3);}
function rememberDestination(){const d=appliedDestination(),key=x=>JSON.stringify([x.country,[...x.resorts].sort(),x.hotelId]);saveStored('anytour.prototype.v18.destinations.v1',[d,...recentDestinations().filter(x=>key(x)!==key(d))].slice(0,3));}
function restoredDestinationChoice(value){
 const fallback=structuredClone(currentDraftDestination());if(!value||typeof value!=='object')return fallback;
 const country=String(value.country||'');if(!countryNames[country])return fallback;
 const resorts=Array.isArray(value.resorts)?[...new Set(value.resorts.filter(v=>typeof v==='string'&&v.length<=120))].slice(0,20):[];
 const hotelId=Number(value.hotelId),hotel=Number.isInteger(hotelId)&&hotelId>0?destinationHotel(hotelId):null;
 return {country,resorts:hotel?[]:resorts,hotelId:hotel&&String(hotel.country)===country?hotel.id:0};
}
function openDeparture(restore=null){
 showModal('departure','Откуда вылетаем?','ГОРОД ВЫЛЕТА',`<div class="destination-search"><label class="sr-only" for="departure-query">Найти город вылета</label>${icon('search')}<input class="input" id="departure-query" type="search" placeholder="Найти город" autocomplete="off"></div><div id="departure-results"></div>`);
 $('#modal').classList.add('departure-dialog');$('#departure-query').value=boundedHistoryText(restore?.query,'');renderDepartures();
 if(innerWidth>760)$('#departure-query').focus({preventScroll:true});
}
function renderDepartures(){
 const q=normalizeSearch($('#departure-query').value),cities=[...new Set(data.catalog.departures.map(data.text))],saved=getStored('anytour.departures.v1',[]);
 const recent=Array.isArray(saved)?saved.filter(city=>cities.includes(city)).slice(0,3):[];
 const first=[...new Set([draft.origin,...recent])].filter(city=>cities.includes(city)),rest=cities.filter(city=>!first.includes(city)).sort((a,b)=>a.localeCompare(b,'ru'));
 const rows=(names)=>names.filter(city=>!q||normalizeSearch(city).includes(q)).map(city=>`<button type="button" class="destination-row" data-action="choose-departure" data-value="${esc(city)}" aria-pressed="${city===draft.origin}"><span>${esc(city)}</span>${city===draft.origin?icon('check'):''}</button>`).join('');
 const selected=rows(first),others=rows(rest);
 $('#departure-results').innerHTML=(selected?`<section class="destination-section"><h3>Текущий и недавние</h3>${selected}</section>`:'')+(others?`<section class="destination-section"><h3>Города вылета</h3>${others}</section>`:'')||'<p class="destination-empty" role="status">Такого города в списке вылетов нет.</p>';
 rememberUIRoute();
}
document.addEventListener('input',event=>{if(event.target.id==='departure-query')renderDepartures();});
const primaryDestinations=['Турция','Египет','ОАЭ','Таиланд','Вьетнам','Мальдивы','Шри-Ланка','Китай','Абхазия','Россия'];
const destinationOrder=(a,b)=>{const rank=name=>{const i=primaryDestinations.indexOf(name);return i<0?100:i;};return rank(a)-rank(b)||a.localeCompare(b,'ru');};
function resortGroups(query,country){
 const parents=[],childrenByCountry=new Map();
 for(const row of Object.values(data.catalog.regions).flat()){
  if(row.kind!=='subregion'){parents.push(row);continue;}
  let childrenByParent=childrenByCountry.get(row.country);
  if(!childrenByParent){childrenByParent=new Map();childrenByCountry.set(row.country,childrenByParent);}
  const parentId=String(row.parentId),children=childrenByParent.get(parentId);
  if(children)children.push(row);else childrenByParent.set(parentId,[row]);
 }
 return parents.map(parent=>({parent,children:childrenByCountry.get(parent.country)?.get(String(parent.id))||[]}))
 .filter(group=>query?[group.parent,...group.children].some(r=>normalizeSearch(r.name+' '+countryNames[r.country]).includes(query)):group.parent.country===country)
 .sort((a,b)=>a.parent.name.localeCompare(b.parent.name,'ru'));
}
function resortChoiceHTML(r,selectedCountry,selectedResorts,context=''){
 const selected=selectedCountry===r.country&&selectedResorts.includes(r.name);
 return `<button type="button" class="destination-row" data-action="destination-resort" data-country="${r.country}" data-value="${esc(r.name)}" aria-pressed="${selected}"><span class="choice-check">${selected?icon('check'):''}</span><span><strong>${esc(r.name)}</strong>${context?`<small>${esc(context)}</small>`:''}</span></button>`;
}
function openDestination(restore=null){
 cancelDestinationLookup();destinationResortsExpanded=restore?.expanded===true;destinationHotelLimit=Number.isInteger(restore?.limit)?Math.min(80,Math.max(destinationHotelPageSize,restore.limit)):destinationHotelPageSize;
 const query=typeof restore?.query==='string'?restore.query.slice(0,120):'',resolved=typeof restore?.resolvedQuery==='string'?restore.resolvedQuery.slice(0,120):'';
 destinationResolvedQuery=resolved;destinationChoice=restoredDestinationChoice(restore?.choice);
 showModal('destination','Куда отправимся?','СТРАНА, КУРОРТ ИЛИ ОТЕЛЬ',`<div class="destination-search-sticky"><div class="destination-search"><label class="sr-only" for="destination-query">Страна, курорт или отель</label>${icon('search')}<input class="input" type="search" id="destination-query" placeholder="Страна, курорт или отель" autocomplete="off" aria-controls="destination-results"><button class="icon-button destination-clear" data-action="clear-destination-query" aria-label="Очистить поиск направления" hidden>${icon('x')}</button></div></div><div id="destination-selection" class="destination-selection"></div><div id="destination-results" aria-live="polite"></div>`);$('#modal').classList.add('destination-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="destination-apply-context" role="status"></div><button class="primary picker-apply" data-action="apply-destination"></button>';
 $('#destination-query').value=query;renderDestination();loadResorts(destinationChoice.country);if(Number.isFinite(restore?.scroll)&&restore.scroll>=0)$('#modal-body').scrollTop=restore.scroll;if(innerWidth>760)$('#destination-query').focus({preventScroll:true});
}
function renderDestination(){
 const d=destinationChoice,q=normalizeSearch($('#destination-query').value),recent=recentDestinations(),focused=document.activeElement?.closest('#destination-results button, #destination-selection button'),focus=focused?{...focused.dataset}:null;
 $('[data-action="clear-destination-query"]').hidden=!$('#destination-query').value;
 $('#destination-selection').hidden=!!countryNames[d.country]&&!d.resorts.length&&!d.hotelId;
 if(!catalogReady||!countryNames[d.country]){
  $('#destination-selection').textContent=catalogError?'Направления пока недоступны':'Загружаем направления…';
  $('#destination-results').innerHTML=catalogError?`<div class="destination-empty" role="status"><p>${esc(catalogError)}</p><button class="secondary" data-action="${catalogDeparture?'retry-countries':'retry-catalog'}">Повторить загрузку направлений</button></div>`:'<p role="status">Название можно ввести сейчас. Поиск начнётся после загрузки направлений.</p>';
  const apply=$('[data-action="apply-destination"]');apply.disabled=true;apply.textContent=catalogError?'Выбор пока недоступен':'Загружаем направления…';rememberUIRoute();return;
 }
 const selectedHotel=destinationHotel(d.hotelId);
 $('#destination-selection').innerHTML=d.hotelId?`<div class="destination-selection-heading"><span>Выбранный отель</span></div><div class="destination-chosen-hotel"><div><strong>${esc(selectedHotel?.name||'Выбранный отель недоступен')}</strong><small>${esc(selectedHotel?[selectedHotel.resort,countryNames[d.country]].filter(Boolean).join(', '):countryNames[d.country])}</small></div><button class="icon-button" data-action="destination-remove" data-id="${d.hotelId}" aria-label="Убрать выбранный отель">${icon('x')}</button></div><button class="text-button" data-action="destination-all">Искать по всей стране</button>`:d.resorts.length?`<div class="destination-selection-heading"><span>${esc(countryNames[d.country])} · выбранные курорты</span><button class="text-button" data-action="destination-all">Сбросить</button></div><div class="destination-selected-resorts">${d.resorts.map(r=>`<button type="button" data-action="destination-remove" data-value="${esc(r)}" aria-label="Убрать курорт: ${esc(r)}"><span>${esc(r)}</span>${icon('x')}</button>`).join('')}</div>`:'';
 const countryRows=Object.entries(countryNames).filter(([k,n])=>!q||normalizeSearch(n).includes(q)).sort((a,b)=>destinationOrder(a[1],b[1]));
 let html=!q&&recent.length?`<section class="destination-section"><h3>Недавние направления</h3><div class="destination-recents">${recent.map((x,i)=>`<button class="chip" data-action="destination-recent" data-value="${i}">${esc(destinationLabel(x))}</button>`).join('')}</div></section>`:'';
 if(countryRows.length){
  const shown=q?countryRows:countryRows.filter(([k],index)=>index<6||k===d.country),remaining=countryRows.filter(row=>!shown.includes(row));
  const countries=rows=>`<div class="destination-countries">${rows.map(([k,n])=>`<button data-action="destination-country" data-value="${k}" aria-pressed="${d.country===k}">${esc(n)}${d.country===k?icon('check'):''}</button>`).join('')}</div>`;
  html+=`<section class="destination-section"><h3>${q?'Страны':'Направления'}</h3>${countries(shown)}${remaining.length?`<details class="destination-more-countries"><summary>Все страны (${countryRows.length})</summary>${countries(remaining)}</details>`:''}</section>`;
 }
 const groups=resortGroups(q,d.country),visible=q||destinationResortsExpanded?groups:groups.filter((g,i)=>i<destinationResortPreviewLimit||[g.parent,...g.children].some(r=>d.resorts.includes(r.name)));
 const resortToggle=!q&&groups.length>destinationResortPreviewLimit?`<button class="secondary destination-list-toggle" data-action="toggle-destination-resorts" aria-expanded="${destinationResortsExpanded}">${destinationResortsExpanded?'Свернуть список':`Все регионы (${groups.length})`}</button>`:'';
 if(groups.length&&(!d.hotelId||q))html+=`<section class="destination-section"><h3>Регионы и курорты</h3>${visible.map(({parent,children})=>`<div class="destination-region">${resortChoiceHTML(parent,d.country,d.resorts,q?countryNames[parent.country]:'')}${children.length?`<details ${q||children.some(r=>d.resorts.includes(r.name))?'open':''}><summary>Курорты региона · ${children.length}</summary><div class="destination-subregions">${children.filter(r=>!q||normalizeSearch(parent.name+' '+r.name+' '+countryNames[r.country]).includes(q)).sort((a,b)=>a.name.localeCompare(b.name,'ru')).map(r=>resortChoiceHTML(r,d.country,d.resorts)).join('')}</div></details>`:''}</div>`).join('')}${resortToggle}</section>`;
 if(!data.catalog.regions[d.country])html+=resortLoads.get(d.country)==='error'?'<p role="status">Не удалось загрузить курорты.</p><button class="secondary" data-action="retry-resorts">Повторить загрузку курортов</button>':'<p role="status">Загружаем курорты…</p>';
 const loaded=q.length>=2?hotels.filter(h=>h.country===d.country&&matchesHotelQuery(h,q)):[];
 const words=normalizeHotelQuery(q).split(' ').filter(Boolean),names=new Map(),nameMatches=h=>{if(!names.has(h))names.set(h,normalizeHotelQuery(h.name));return words.every(word=>names.get(h).includes(word));};
 const matches=q.length>=2?[...new Map([...loaded,...destinationLookup.rows].map(h=>[h.id,h])).values()].sort((a,b)=>Number(nameMatches(b))-Number(nameMatches(a))):[];
 if(matches.length)html+=`<section class="destination-section"><h3>Отели · ${esc(countryNames[d.country]||'')} <span class="destination-match-count">${matches.length} найдено</span></h3>${matches.slice(0,destinationHotelLimit).map(h=>`<button class="destination-row destination-hotel" data-action="destination-hotel" data-id="${h.id}" aria-pressed="${d.hotelId===h.id}"><img src="${esc(photoUrl(h))}" alt="" width="58" height="45"><span><strong>${esc(h.name)}</strong><small>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}${h.stars?' · '+h.stars+' ★':''}</small></span>${d.hotelId===h.id?icon('check'):''}</button>`).join('')}${matches.length>destinationHotelLimit?`<button class="secondary destination-list-toggle" data-action="destination-more-hotels">Показать ещё ${Math.min(destinationHotelPageSize,matches.length-destinationHotelLimit)}<small>Показано ${destinationHotelLimit} из ${matches.length} найденных отелей</small></button>`:''}</section>`;
 if(q&&destinationLookup.status==='loading')html+='<p role="status">Ищем отели в каталоге…</p>';
 if(q&&destinationLookup.status==='error')html+='<div class="destination-empty" role="status"><h3>Не удалось загрузить отели</h3><p>Название сохранено. Попробуйте ещё раз.</p><button class="secondary" data-action="retry-destination">Повторить поиск отеля</button></div>';
 $('#destination-query').setAttribute('aria-busy',String(destinationLookup.status==='loading'));
 $('#destination-results').innerHTML=html||`<div class="destination-empty"><h3>${q.length===1?'Введите хотя бы 2 символа':'По названию ничего не нашли'}</h3><p>${q.length===1?'Продолжите название отеля.':'Проверьте написание или выберите другую страну.'}</p></div>`;
 const unresolved=!!q&&destinationResolvedQuery!==q,apply=$('[data-action="apply-destination"]');apply.disabled=unresolved;apply.textContent=d.hotelId?'Выбрать отель':'Выбрать направление';
 $('.destination-apply-context').textContent=unresolved?'Выберите подсказку или очистите поиск':d.hotelId?'Поиск по выбранному отелю':d.resorts.length?destinationLabel(d):countryNames[d.country]+' · все курорты';
 if(focus){const target=$$('#destination-results button, #destination-selection button').find(b=>['action','value','id','country'].every(key=>b.dataset[key]===focus[key]));(target||$('#destination-selection button')||$('#destination-query')).focus({preventScroll:true});}
 rememberUIRoute();
}
function updateNav(){
 $('#favorites-results').hidden=!optionalShortlistEnabled||!state.favorites.length;$('#favorites-results-count').textContent=state.favorites.length;
 $('#selected-tour-nav').hidden=true;$$('.selected-tour-shortcut').forEach(b=>b.hidden=true);$('.mobile-bottom>[data-action="top"]').hidden=false;
 $('#favorites-nav').hidden=!optionalShortlistEnabled;$('#favorites-nav').innerHTML=`${icon('heart')}<span class="nav-label">Избранное</span>${state.favorites.length?`<span class="saved-dot">${state.favorites.length}</span>`:''}`;$('#favorites-nav').setAttribute('aria-label',`Избранное: ${state.favorites.length}`);
 $('#compare-nav').hidden=!optionalShortlistEnabled;$('#compare-nav').innerHTML=`${icon('compare')}<span class="nav-label">Сравнение</span>${state.compare.length?`<span class="saved-dot">${state.compare.length}</span>`:''}`;$('#compare-nav').setAttribute('aria-label',`Сравнение: ${state.compare.length}`);
 const tray=$('#compare-tray');tray.hidden=!optionalShortlistEnabled||!state.compare.length;tray.innerHTML=optionalShortlistEnabled&&state.compare.length?`<div class="compare-tray-photos">${state.compare.map(id=>{const h=hotels.find(h=>h.id===id);return `<img src="${esc(photoUrl(h))}" alt="${esc(h.name)}">`}).join('')}</div><div class="compare-tray-copy"><strong>Сравнить отели</strong><span>Выбрано ${state.compare.length} из 3</span></div><button class="primary" data-action="compare">Сравнить ${icon('arrow')}</button><button class="icon-button" data-action="clear-compare" aria-label="Очистить сравнение">${icon('x')}</button>`:'';

}
let filterDraft=null,emptySuggestions=[],drawerSuggestions=[],filterBudgetEdit=null;
const appliedFilterModel=()=>({filters:state.filters,onlyFavorites:state.onlyFavorites,selectedDate:state.selectedDate});
const editingFilterModel=()=>filterDraft||appliedFilterModel();
const countMatchingHotels=model=>{const s=model.search||state.search,f=model.filters||state.filters,selected=Object.hasOwn(model,'selectedDate')?model.selectedDate:state.selectedDate,from=model.day||(selected&&!model.ignoreDate?selected:s.from),to=model.day||(selected&&!model.ignoreDate?selected:s.to),matches=hotelOfferPredicate(s,f,from,to),onlyFavorites=model.onlyFavorites??state.onlyFavorites;return hotels.reduce((count,h)=>count+Number(hotelMatch(h,f,s,onlyFavorites)&&(h.offers||[]).find(matches)!==undefined),0);};
// Facet counts are per hotel, so stop after each requested identity is found.
// Keep the inventory local to this pass: later responses and edits recalculate it.
function countFacetOptions(model,group,values,selectedInventory=null){
 const counts=new Map(values.map(value=>[value,0]));if(!counts.size)return counts;
 if(!['meals','operators','flight','resorts','stars','amenities'].includes(group))return new Map(values.map(value=>[value,countMatchingHotels({...model,filters:{...model.filters,[group]:[value]}})]));
 const selectedValues=model.filters[group]||[],filters={...model.filters,[group]:[]},s=model.search||state.search,hotelFacet=['resorts','stars','amenities'].includes(group),wanted=new Map(),selectedKeys=new Set();
 if(selectedInventory)selectedInventory.count=0;
 const selectedDate=Object.hasOwn(model,'selectedDate')?model.selectedDate:state.selectedDate;
 const from=model.day||(selectedDate&&!model.ignoreDate?selectedDate:s.from),to=model.day||(selectedDate&&!model.ignoreDate?selectedDate:s.to);
 if(!hotelFacet){
  for(const value of counts.keys()){
   const key=group==='meals'&&data.live?mealNames[value]:value;if(group==='meals'&&data.live&&!Number.isSafeInteger(key))continue;
   if(!wanted.has(key))wanted.set(key,[]);wanted.get(key).push(value);
  }
  for(const value of selectedValues){const key=group==='meals'&&data.live?mealNames[value]:value;if(group!=='meals'||!data.live||Number.isSafeInteger(key))selectedKeys.add(key);}
  if(!wanted.size)return counts;
 }
 const matches=!hotelFacet||group==='amenities'?hotelOfferPredicate(s,filters,from,to):null;
 for(const h of hotels){
  if(!h)continue;
  if(hotelFacet){
   if(group==='amenities'){
    if(!hotelMatch(h,filters,s,model.onlyFavorites??state.onlyFavorites)||(h.offers||[]).find(matches)===undefined)continue;
   }else if(!hotelOffers(h,{...model,filters,firstOnly:true}).length)continue;
   let facts;if(group==='resorts')facts=hotelPlaces(h);else if(group==='stars')facts=[h.stars];else{facts=new Set();(h.amenities||[]).forEach(value=>facts.add(value.key));}
   const selectedMatch=group==='amenities'?selectedValues.every(value=>facts.has(value)):!selectedValues.length||selectedValues.some(value=>facts.includes(value));
   if(selectedInventory&&selectedMatch)selectedInventory.count++;
   if(group==='amenities'&&!selectedMatch)continue;
   for(const value of new Set(facts))if(counts.has(value))counts.set(value,counts.get(value)+1);
   continue;
  }
  if(!hotelMatch(h,filters,s,model.onlyFavorites??state.onlyFavorites))continue;
  const remaining=new Set(wanted.keys());let selectedMatch=false;
  for(const o of h.offers||[]){
   const key=group==='meals'?(data.live?o.mealPlanId:o.meal):group==='operators'?o.operator:o.flight;
   const pendingCount=remaining.has(key),pendingSelection=selectedInventory&&!selectedMatch&&(!selectedValues.length||selectedKeys.has(key));
   if(!pendingCount&&!pendingSelection)continue;
   if(!matches(o))continue;
   if(pendingSelection){selectedMatch=true;selectedInventory.count++;}
   if(!pendingCount)continue;
   for(const value of wanted.get(key))counts.set(value,counts.get(value)+1);
   remaining.delete(key);if(!remaining.size&&(!selectedInventory||selectedMatch))break;
  }
 }
 return counts;
}
const hotelCountText=n=>`${n} ${n%10===1&&n%100!==11?'отель':n%10>=2&&n%10<=4&&(n%100<12||n%100>14)?'отеля':'отелей'}`;
function filterChipData(model=appliedFilterModel()){
 const f=model.filters,chips=[];
 for(const key of f.amenities||[])chips.push({key:'amenities',value:key,label:amenityNames.get(key)?.label||'Удобство отеля'});
 if(model.selectedDate)chips.push({key:'date',value:'',label:`Вылет ${dateText(model.selectedDate)}`});
 if(model.onlyFavorites)chips.push({key:'favorites',value:'',label:'Только избранное'});
 if(f.hotelId)chips.push({key:'hotelId',value:'',label:destinationHotel(f.hotelId)?.name||'Выбранный отель'});
 if(f.q)chips.push({key:'q',value:'',label:`Отель: ${f.q}`});
 for(const key of ['stars','meals','resorts','operators','flight'])f[key].forEach(value=>chips.push({key,value,label:key==='stars'?value+' ★':key==='flight'?(value==='charter'?'Чартер':'Регулярный рейс'):value}));
 for(const [key,label] of [['beach','Первая линия'],['rating','Рейтинг 4,5+'],['family','Детский клуб'],['spa','Спа-центр']])if(f[key])chips.push({key,value:'',label});
 if(f.min>0||f.max!==null)chips.push({key:'price',value:'',label:budgetLabel(f)});
 return chips;
}
function removeModelFilter(model,key,value){const f=model.filters;if(key==='date')model.selectedDate=null;else if(key==='favorites')model.onlyFavorites=false;else if(key==='price'){f.min=0;f.max=null}else if(Array.isArray(f[key]))f[key]=f[key].filter(x=>String(x)!==String(value));else f[key]=key==='q'?'':key==='hotelId'?0:false;}
function recoverySuggestions(model){
 const f=model.filters,candidates=[];
 const add=(key,title,change,description='Остальные условия сохранятся')=>{const next=structuredClone(model);change(next);const count=countMatchingHotels(next);return !!count&&candidates.push({key,title,description,count,model:next})===3;};
 if(f.max!==null){const wider={...model,filters:{...f,max:null},minimumOnly:true},offers=hotels.map(h=>hotelOffers(h,wider)[0]).filter(Boolean),minimum=offers.length?Math.min(...offers.map(o=>o.total)):null;if(minimum!==null&&minimum>f.max){const ceiling=Math.ceil(minimum/1000)*1000;if(add('budget',`Бюджет до ${money(ceiling)}`,m=>m.filters.max=ceiling))return candidates;}}
 if(f.min>0&&add('minimum',`Убрать бюджет «от ${money(f.min)}»`,m=>m.filters.min=0))return candidates;
 if(f.q&&add('q','Убрать поиск по названию',m=>m.filters.q=''))return candidates;
 for(const [key,title] of [['meals','Любое питание'],['stars','Любая категория отеля'],['flight','Любой тип перелёта'],['operators','Любой туроператор'],['beach','Без условия «Первая линия»'],['rating','Без ограничения по рейтингу'],['family','Без условия «Детский клуб»'],['spa','Без условия «Спа-центр»'],['resorts','Все курорты направления'],['hotelId','Другие отели в направлении']])if((Array.isArray(f[key])?f[key].length:f[key])&&add(key,title,m=>m.filters[key]=Array.isArray(f[key])?[]:key==='hotelId'?0:false))return candidates;
 for(const key of f.amenities||[])if(add('amenity:'+key,`Без условия «${amenityNames.get(key)?.label||'Удобство отеля'}»`,m=>m.filters.amenities=m.filters.amenities.filter(x=>x!==key)))return candidates;
 if(model.onlyFavorites&&add('favorites','Показать и несохранённые отели',m=>m.onlyFavorites=false))return candidates;
 if(model.selectedDate&&add('date',`Все даты: ${rangeText(state.search.from,state.search.to)}`,m=>m.selectedDate=null,'В пределах выбранного диапазона вылета'))return candidates;
 if(!candidates.length)add('reset','Сбросить все фильтры',m=>{m.filters=defaultFilters();m.onlyFavorites=false;m.selectedDate=null},'Город, страна, диапазон дат и туристы сохранятся');
 return candidates;
}
function recoveryHTML(choices,source){return `<div class="recovery-options">${choices.map((c,i)=>`<button class="recovery-choice" data-action="recover-filters" data-source="${source}" data-recovery-key="${c.key}" data-value="${i}"><span><strong>${esc(c.title)}</strong><small>${esc(c.description)}</small></span><span class="recovery-count">${hotelCountText(c.count)} ${icon('arrow')}</span></button>`).join('')}</div>`;}
function syncFilterResetState(model=editingFilterModel()){
 const button=$('#filter-panel [data-action="reset"]');if(!button)return;
 const active=filterChipData(model).length>0||!!currentFilterBudgetEdit(model.filters)&&!readBudgetFields($('#min-price'),$('#max-price')).valid;button.disabled=!active;
 button.setAttribute('aria-label',active?'Сбросить выбранные фильтры':'Сбросить — фильтры не выбраны');
}
function emptyCalendarContext(){
 if(data.scenario!=='live'||!data.observationScopeSupported(state.search,state.filters))return '';
 const from=state.selectedDate||state.search.from,to=state.selectedDate||state.search.to;
 const point=resultCalendar.observations.filter(p=>p.date>=from&&p.date<=to&&Number.isFinite(p.price)&&p.price>0).reduce((minimum,p)=>!minimum||p.price<minimum.price?p:minimum,null);
 if(!point)return '';
 const observed=/^\d{4}-\d{2}-\d{2}/.test(point.observedAt||'')?' · найдена '+dateText(point.observedAt.slice(0,10)):'';
 return `<strong>В календаре — от ${money(point.price)}${esc(observed)}</strong><p>Это ранее сохранённая цена. Предложений по ней в текущем поиске нет.</p>`;
}
function refreshEmptyCalendarContext(){const node=$('[data-empty-calendar]');if(!node)return;const html=emptyCalendarContext();node.hidden=!html;if(node.innerHTML!==html)node.innerHTML=html;}
function emptyResultsHTML(){if(!state.hasSearched){if(!urlStateHydrated&&!catalogError)return `<div class="empty pristine-empty" role="status">${icon('search')}<h3>Готовим поиск…</h3><p>Загружаем направления и сохранённые параметры поездки.</p></div>`;const d=currentDraftDestination();if(d.hotelId&&!data.preview&&!destinationHotel(d.hotelId)?.legacyIds.length)return `<div class="empty pristine-empty" role="status">${icon('info')}<h3>${hotelRestorePending?'Восстанавливаем выбранный отель…':'Не удалось восстановить отель'}</h3><p>Параметры поездки сохранены.${hotelRestorePending?'':' Повторите загрузку или выберите отель заново.'}</p>${hotelRestorePending?'':`<div class="empty-actions"><button class="secondary" data-action="retry-hotel-restore">Повторить загрузку отеля</button><button class="text-button" data-action="destination">Выбрать другой отель</button></div>`}</div>`;return `<div class="empty pristine-empty">${icon('search')}<h3>Начните с параметров поездки</h3><p>Выберите направление, даты и туристов, затем нажмите «Найти туры».</p></div>`;}const model=appliedFilterModel(),chips=filterChipData(model),response=responseFor(state.search);if(response.phase==='error')return '';
 if(response.pending||response.phase!=='complete'){emptySuggestions=[];return `<div class="empty recovery-empty" role="status">${icon('search')}<h3>${response.pending?'Подбираем туры по вашим условиям':'Поиск пока не завершён'}</h3><p>${response.pending?'Предложения появятся здесь по мере загрузки.':'Отсутствие результатов пока не означает, что подходящих туров нет.'}</p><p class="empty-preserved">Даты, туристы и фильтры сохранены.</p>${response.pending?'':'<button class="secondary" data-action="retry-search">Повторить поиск</button>'}</div>`;}
 emptySuggestions=recoverySuggestions(model);const calendarContext=emptyCalendarContext();return `<div class="empty recovery-empty">${icon('search')}<h3>Подходящих предложений пока нет</h3><p>${emptySuggestions.length?'Вот какие изменения вернут отели в выдачу:':'Попробуйте другие даты или направление.'}</p><div class="empty-calendar-context" data-empty-calendar ${calendarContext?'':'hidden'}>${calendarContext}</div>${recoveryHTML(emptySuggestions,'results')}<p class="empty-preserved">${emptySuggestions.length?'Количество рассчитано по уже загруженным предложениям. При изменении фильтров даты поездки и туристы сохранятся.':'По текущему запросу нет доступных предложений среди загруженных данных.'}</p><div class="empty-actions"><button class="primary" data-action="calendar">Выбрать другие даты</button>${chips.length?'<button class="secondary" data-action="reset">Сбросить фильтры</button>':''}<button class="text-button" data-action="edit-search">Изменить поиск</button></div></div>`;}
function updateDrawerPreview(knownCount){
 if(!filterDraft)return;
 const count=knownCount??countMatchingHotels(filterDraft),chips=filterChipData(filterDraft),pristine=!state.hasSearched&&!state.onlyFavorites;
 syncFilterResetState(filterDraft);
 $('#filter-preview-count').textContent=pristine?'Условия для следующего поиска':count?`${hotelCountText(count)} по выбранным условиям`:'По выбранным условиям туров нет';
 $('#drawer-context').textContent=`${esc(countryNames[state.search.country]||'')} · ${departureScopeText()} · ${durationText()} · ${guestsText()}`;
 $('#drawer-selected').innerHTML=chips.map(c=>`<button class="active-filter" data-action="remove-draft-filter" data-key="${c.key}" data-value="${esc(c.value)}" aria-label="Убрать: ${esc(c.label)}">${esc(c.label)}${icon('x')}</button>`).join('');
 drawerSuggestions=count||pristine?[]:recoverySuggestions(filterDraft);
 $('#drawer-recovery').innerHTML=count||pristine?'':`<p>Можно изменить одно из условий:</p>${recoveryHTML(drawerSuggestions.slice(0,2),'drawer')}`;
 if(!pristine&&responseFor(state.search).phase!=='complete'){$('#filter-preview-count').textContent=count?`${hotelCountText(count)} среди полученных предложений`:'Подходящих предложений пока нет';drawerSuggestions=[];$('#drawer-recovery').innerHTML=count?'':'<p>Поиск не завершён. Условия можно сохранить и дождаться остальных предложений или повторить поиск.</p>';}
 const canRecover=!count&&drawerSuggestions.length>0,button=$('#apply-filters');button.dataset.action=canRecover?'review-filter-recovery':'apply-filters';button.textContent=canRecover?'Как вернуть отели':pristine||!count?'Сохранить условия':`Показать отели (${count})`;button.setAttribute('aria-describedby','filter-preview-count');
 showFilterBudgetValidity(readBudgetFields($('#min-price'),$('#max-price')));
 if(searchEditSession){
  $('#filter-preview-count').textContent='Условия для нового поиска';
  $('#drawer-context').textContent=`${destinationLabel(currentDraftDestination())} · ${rangeText(draft.from,draft.to)} · ${durationText(draft)} · ${guestsText(draft)}`;
  drawerSuggestions=[];$('#drawer-recovery').innerHTML='';button.dataset.action='apply-filters';
  if(!button.disabled)button.textContent='Сохранить условия';
 }
}
function filterEdited(rebuild=false){if(filterDraft){if(rebuild){renderFilters();updateDrawerPreview()}else updateDrawerPreview(updateFacetCounts());rememberUIRoute();settleFilterRoots($('#filters'));return}if(rebuild)syncFilters();else{renderResults({keepFilters:true});updateSearchUI()}settleFilterRoots($('#filters'));}
const expandedFacets=new Set(),facetQueries=new Map();let facetQueryScope='';
const expandedFilterSections=new Set();
function syncFilterSections(){
 const f=editingFilterModel().filters;
 $$('#filters .filter-group').forEach((group,i)=>{
  if(group.querySelector('#hotel-query'))return;
  const heading=group.querySelector(':scope>h4');if(!heading)return;
  const key=heading.textContent;group.dataset.filterSection=key;
  let button=group.querySelector(':scope>.filter-section-toggle');
  if(!button){
   const body=document.createElement('div');body.className='filter-section-values';
   [...group.children].filter(child=>child!==heading).forEach(child=>body.append(child));
   button=document.createElement('button');button.type='button';button.className='filter-section-toggle';button.dataset.action='toggle-filter-section';
   button.innerHTML=`<span class="filter-section-title">${esc(key)}</span><span class="filter-section-value"></span><span class="filter-section-chevron" aria-hidden="true">⌄</span>`;
   group.append(button,body);
  }
  const body=group.querySelector(':scope>.filter-section-values');body.id='filter-values-'+i;button.setAttribute('aria-controls',body.id);
  const selected=[...group.querySelectorAll('.check-row input:checked')].map(input=>input.closest('label').querySelector('span').textContent);
  let summary=selected.slice(0,2).join(' · ')+(selected.length>2?` · ещё ${selected.length-2}`:''),active=selected.length>0;
  if(group.querySelector('#min-price')){summary=currentFilterBudgetEdit(f)&&!readBudgetFields($('#min-price'),$('#max-price')).valid?'Проверьте сумму':budgetLabel(f);active=f.min>0||f.max!==null;}
  else if(group.querySelector('.star-options')){summary=f.stars.map(n=>n+' ★').join(' · ')||'Любая';active=f.stars.length>0;}
  else if(!summary)summary={Питание:'Любое',Курорт:'Любой',Туроператор:'Любой','Оценка гостей':'Любая'}[key]||'Не выбрано';
  button.querySelector('.filter-section-value').textContent=summary;button.classList.toggle('has-selection',active);
  const open=expandedFilterSections.has(key);button.setAttribute('aria-expanded',String(open));group.classList.toggle('section-open',open);
 });
}
function setFilterSectionOpen(group,open){
 if(!group?.dataset.filterSection)return;
 const key=group.dataset.filterSection;if(open)expandedFilterSections.add(key);else expandedFilterSections.delete(key);
 group.classList.toggle('section-open',open);group.querySelector('.filter-section-toggle').setAttribute('aria-expanded',String(open));
}
function compareMealLabels(a,b){
 const order=['Всё включено','Ультра всё включено','Завтраки','Полупансион','Полный пансион','Без питания'],rank=label=>order.includes(label)?order.indexOf(label):100;
 return rank(a)-rank(b)||a.localeCompare(b,'ru');
}
function comparePopularFacetOptions(a,b){return b[2]-a[2]||a[1].localeCompare(b[1],'ru');}
function checkRows(group,options){const model=editingFilterModel(),counts=countFacetOptions(model,group,options.map(([value])=>value)),selected=model.filters[group]||[],ordered=options.map(([val,label],index)=>[val,label,counts.get(val),index]).sort((a,b)=>group==='meals'?compareMealLabels(a[1],b[1]):['resorts','operators'].includes(group)?comparePopularFacetOptions(a,b):Number(selected.includes(b[0]))-Number(selected.includes(a[0]))||Number(b[2]>0)-Number(a[2]>0)||a[3]-b[3]);if(options.length<=7)return fullCheckRows(group,ordered);const label={resorts:'Найти курорт',operators:'Найти туроператора',meals:'Найти питание'}[group]||'Найти вариант';return `<div class="facet-options" data-facet-options="${group}"><div class="facet-search"><label><span class="sr-only">${label} в списке фильтра</span><input type="search" data-facet-search="${group}" value="${esc(facetQueries.get(group)||'')}" placeholder="${label}" autocomplete="off"></label><button type="button" class="icon-button" data-action="clear-facet-query" aria-label="Очистить поиск в списке" hidden>${icon('x')}</button></div><div class="facet-picked" role="group" aria-label="Выбрано в этом разделе"></div><p class="facet-search-status" aria-live="polite" hidden></p>${fullCheckRows(group,ordered.slice(0,7))}<details class="facet-more" data-facet="${group}" ${expandedFacets.has(group)?'open':''}><summary></summary>${fullCheckRows(group,ordered.slice(7))}</details></div>`;}
function applyFacetSearch(host){
 const group=host.dataset.facetOptions,q=normalizeSearch(facetQueries.get(group)||''),rows=[...host.querySelectorAll('.check-row')].map(row=>({row,input:row.querySelector('input'),label:row.querySelector('span').textContent,count:Number(row.querySelector('small').textContent)})),more=host.querySelector('details'),focused=document.activeElement;
 rows.forEach(({row},i)=>{if(row.dataset.facetOrder===undefined)row.dataset.facetOrder=String(i)});
 rows.sort((a,b)=>group==='meals'?Number(a.row.dataset.facetOrder)-Number(b.row.dataset.facetOrder):['resorts','operators'].includes(group)?b.count-a.count||a.label.localeCompare(b.label,'ru'):Number(b.input.checked)-Number(a.input.checked)||Number(b.row.dataset.available==='true')-Number(a.row.dataset.available==='true')||Number(a.row.dataset.facetOrder)-Number(b.row.dataset.facetOrder));
 let found=0,availableCount=0;const primaryRows=[],moreRowsToPlace=[];
 for(const {row,input,label} of rows){const available=row.dataset.available==='true'||input.checked,matches=available&&(!q||normalizeSearch(label).includes(q));row.hidden=!matches;if(matches)found++;
  (available&&availableCount++<7?primaryRows:moreRowsToPlace).push(row);
 }
 // Reconcile the same row suffixes, preserving any unowned prefix nodes.
 const place=(parent,ordered,before=null)=>{for(let i=ordered.length-1;i>=0;i--){const row=ordered[i];if(row.parentNode!==parent||row.nextSibling!==before)parent.insertBefore(row,before);before=row;}};
 place(host,primaryRows,more);place(more,moreRowsToPlace);
 if(focused?.isConnected&&rows.some(({row})=>row.contains(focused))&&document.activeElement!==focused)focused.focus({preventScroll:true});
 const searchBox=host.querySelector('.facet-search');searchBox.hidden=availableCount<=7&&!q&&!searchBox.contains(focused);
 host.classList.toggle('facet-searching',!!q);
 const moreRows=rows.filter(({row})=>more.contains(row)),availableMore=moreRows.filter(({row,input})=>row.dataset.available==='true'||input.checked).length;
 more.querySelector('summary').textContent=`Показать ещё ${availableMore}`;more.open=!!q||expandedFacets.has(group);more.hidden=availableMore===0||!!q&&!moreRows.some(({row})=>!row.hidden);
 const status=host.querySelector('.facet-search-status');status.hidden=!q;status.textContent=found?`Найдено в списке: ${found}`:'Нет доступных совпадений. Попробуйте другое название.';
 const selected=editingFilterModel().filters[group]||[],picked=host.querySelector('.facet-picked');

 const labels=new Map(rows.map(({input,label})=>[input.value,label]));
 picked.hidden=!selected.length;
 picked.innerHTML=selected.length?`<span class="facet-picked-label">Выбрано: ${selected.length}</span><div class="facet-picked-items">${selected.map(value=>`<button type="button" class="facet-picked-item" data-action="remove-facet-choice" data-value="${esc(value)}" aria-label="Убрать из выбора: ${esc(labels.get(value)||value)}"><span>${esc(labels.get(value)||value)}</span>${icon('x')}</button>`).join('')}</div>`:'';
 host.querySelector('[data-action="clear-facet-query"]').hidden=!facetQueries.get(group);}
document.addEventListener('input',event=>{const group=event.target.dataset?.facetSearch;if(group){facetQueries.set(group,event.target.value);applyFacetSearch(event.target.closest('.facet-options'));settleFilterRoots($('#filters'));}});
document.addEventListener('toggle',event=>{const group=event.target.dataset?.facet;if(group&&!facetQueries.get(group)){if(event.target.open)expandedFacets.add(group);else expandedFacets.delete(group);}},true);
function filterCheckRowHTML(attributes,label,count,selected){const available=count>0||selected;return `<label class="check-row" data-available="${available}" ${available?'':'hidden'}><input type="checkbox" ${attributes} ${selected?'checked':''}><span>${esc(label)}</span><small aria-label="${hotelCountText(count)}">${count}</small></label>`;}
function fullCheckRows(group,options){const model=editingFilterModel();return options.map(([val,label,knownCount])=>filterCheckRowHTML(`data-filter="${group}" value="${esc(val)}"`,label,knownCount??countMatchingHotels({...model,filters:{...model.filters,[group]:[val]}}),model.filters[group].includes(val))).join('');}
function amenityFilterGroups(hs,f){
 const facts=new Map();hs.forEach(h=>(h.amenities||[]).forEach(a=>{if(a?.filterable===false)return;facts.set(a.key,a);amenityNames.set(a.key,a);}));
 for(const key of f.amenities||[])if(!facts.has(key)&&amenityNames.has(key))facts.set(key,amenityNames.get(key));
 const counts=countFacetOptions(editingFilterModel(),'amenities',[...facts.keys()]);
 const groups=new Map();for(const fact of facts.values()){if(!groups.has(fact.groupId))groups.set(fact.groupId,{name:fact.group,items:[]});groups.get(fact.groupId).items.push(fact);}
 return [...groups.values()].map(group=>`<div class="filter-group"><h4>${esc(group.name)}</h4>${group.items.map(a=>{const selected=(f.amenities||[]).includes(a.key);return filterCheckRowHTML(`data-filter="amenities" value="${esc(a.key)}"`,a.label,counts.get(a.key),selected);}).join('')}</div>`).join('');
}
// Source HTML stays inert. Parsing through a detached template decodes named,
// decimal and hexadecimal entities without executing encoded supplier markup.
function hotelContentText(value){
 if(typeof value!=='string'&&typeof value!=='number')return '';
 const template=document.createElement('template');template.innerHTML=String(value);
 template.content.querySelectorAll('script,style,iframe,object,embed,svg,math,template').forEach(node=>node.remove());
 template.content.querySelectorAll('br').forEach(node=>node.replaceWith('\n'));
 template.content.querySelectorAll('p,div,li,ul,ol,tr,h1,h2,h3,h4,section').forEach(node=>node.append('\n'));
 return (template.content.textContent||'').replace(/[\u200B\uFEFF]/g,'').split('\n').map(line=>line.replace(/\s+/g,' ').trim()).filter(Boolean).join('\n');
}
const plainHotelText=value=>hotelContentText(value).replace(/\s+/g,' ').trim();


function hotelHighlights(h){
 const priority=[3,1,5,8,2],facts=[...(h.amenities||[])].sort((a,b)=>priority.indexOf(a.groupId)-priority.indexOf(b.groupId));
 if(facts.length)return [...new Set(facts.map(a=>a.label))].slice(0,4).join(' · ');
 const place=plainHotelText(h.raw?.description||h.raw?.place);return place.length>160?place.slice(0,157).replace(/\s+\S*$/,'')+'…':place;
}


function syncAvailableFilterGroups(){$$('#filters .filter-group').forEach(group=>{const rows=[...group.querySelectorAll('.check-row')];if(rows.length)group.hidden=rows.every(row=>row.dataset.available!=='true'&&!row.querySelector('input')?.checked);});syncFilterSections();}
function updateFacetCounts(ratingCount){const model=editingFilterModel(),inputs=$$('[data-filter],[data-filter-bool]'),counts=new Map(),grouped=new Map(),selectedInventory={count:null};
 for(const input of inputs){const group=input.dataset.filter;if(!group)continue;if(!grouped.has(group))grouped.set(group,[]);grouped.get(group).push(input.value);}
 const starOptions=filterStarOptions(model);if(starOptions.length)grouped.set('stars',starOptions);
 for(const [group,values] of grouped)counts.set(group,countFacetOptions(model,group,values,selectedInventory.count===null?selectedInventory:null));
 inputs.forEach(input=>{const key=input.dataset.filter||input.dataset.filterBool,value=key==='amenities'?[...new Set([...(model.filters.amenities||[]),input.value])]:input.dataset.filter?[input.value]:true,count=key==='rating'&&ratingCount!==undefined?ratingCount:counts.get(key)?.get(key==='stars'?Number(input.value):input.value)??countMatchingHotels({...model,filters:{...model.filters,[key]:value}}),row=input.closest('.check-row'),label=row?.querySelector('small'),available=count>0||input.checked;if(label){label.textContent=count;label.setAttribute('aria-label',hotelCountText(count))}if(row){row.dataset.available=String(available);if(!row.closest('.facet-options'))row.hidden=!available;}});$$('[data-facet-options]').forEach(applyFacetSearch);updateFilterStars(counts.get('stars'));syncAvailableFilterGroups();renderFilterNavigation();settleFilterRoots($('#filters'));return selectedInventory.count;}
function budgetScale(f){
 let high=Math.max(1000,f.min,f.max??0);
 for(const h of hotels)for(const o of h.offers||[])if(Number.isFinite(o.total)&&o.total>high)high=o.total;
 return Math.ceil(high/1000)*1000;
}
function syncBudgetControls(f){
 filterBudgetEdit=null;
 $('#min-price').value=f.min;$('#max-price').value=f.max??'';
 const range=$('#price-range'),high=budgetScale(f);range.min=f.min;range.max=high;range.value=f.max??high;
 range.setAttribute('aria-valuetext',budgetLabel(f));
 showFilterBudgetValidity(readBudgetFields($('#min-price'),$('#max-price')));
}
function currentFilterBudgetEdit(f){return filterBudgetEdit?.filters===f&&filterBudgetEdit.scope===searchKey(state.search)&&filterBudgetEdit.appliedMin===f.min&&filterBudgetEdit.appliedMax===f.max?filterBudgetEdit:null;}
function showFilterBudgetValidity(budget){
 const error=$('#filter-budget-error');if(!error)return;
 $('#min-price').setAttribute('aria-invalid',String(budget.invalidMin));$('#max-price').setAttribute('aria-invalid',String(budget.invalidMax));
 error.hidden=budget.valid;error.textContent=budgetErrorText(budget);$('#price-range').disabled=!budget.valid;
 if(!budget.valid)setFilterSectionOpen(error.closest('.filter-group'),true);
 if(filterDraft){const button=$('#apply-filters');button.disabled=!budget.valid;if(!budget.valid){button.textContent='Исправьте бюджет';button.setAttribute('aria-describedby','filter-budget-error');$('#filter-preview-count').textContent='Бюджет пока не применён';$('#drawer-recovery').innerHTML='';drawerSuggestions=[];}}
}
function editFilterBudget(){
 const low=$('#min-price'),high=$('#max-price'),f=editingFilterModel().filters,budget=readBudgetFields(low,high);
 const changed=budget.valid&&(f.min!==budget.min||f.max!==budget.max);
 if(budget.valid){f.min=budget.min;f.max=budget.max;const range=$('#price-range');range.min=f.min;range.max=budgetScale(f);range.value=f.max??range.max;range.setAttribute('aria-valuetext',budgetLabel(f));}
 filterBudgetEdit={filters:f,scope:searchKey(state.search),appliedMin:f.min,appliedMax:f.max,minText:low.value,maxText:high.value};
 if(changed)filterEdited();else if(filterDraft)updateDrawerPreview();
 showFilterBudgetValidity(budget);syncFilterSections();syncFilterResetState();if(filterDraft)rememberUIRoute();return budget;
}
let renderedFilterContext=null,filterEditorLease=null;
const filterEditorSelector='[data-facet-search],#hotel-query,#min-price,#max-price,#price-range';
$('#filters').addEventListener('focusin',event=>{filterEditorLease=event.target.matches?.(filterEditorSelector)?event.target:null;});
const filterRootBindings=new WeakMap();
function filterRootBinding(host){
 let binding=filterRootBindings.get(host);
 if(binding)return binding;
 binding={markup:new WeakMap(),dirty:new WeakSet(),structureDirty:false};
 const mark=records=>{for(const record of records){if(record.target===host){binding.structureDirty=true;continue;}let root=record.target.nodeType===1?record.target:record.target.parentElement;while(root&&root.parentNode!==host)root=root.parentElement;if(root&&root.parentNode===host)binding.dirty.add(root);}};
 binding.observer=new MutationObserver(mark);binding.mark=mark;binding.observer.observe(host,{subtree:true,childList:true,attributes:true,characterData:true});filterRootBindings.set(host,binding);return binding;
}
function filterRootMarkup(node){return node.nodeType===1?node.outerHTML:`${node.nodeType}:${node.nodeValue}`;}
function settleFilterRoots(host){const binding=filterRootBindings.get(host);if(binding){binding.observer.takeRecords();binding.structureDirty=false;}}
function reconcileFilterRoots(host,fragment,binding,preserved=null){
 const fresh=[...fragment.childNodes],current=[...host.childNodes],desired=[];
 for(let index=0;index<fresh.length;index++){
 const generated=fresh[index],existing=current[index],keep=preserved?.index===index?preserved.node:existing,markup=preserved?.index===index&&preserved.markup||filterRootMarkup(generated);
  const active=preserved?.index===index&&keep===preserved.node;
  const node=active&&preserved.transferred?keep:keep&&keep.parentNode===host&&(!binding.structureDirty||active)&&!binding.dirty.has(keep)&&(active||binding.markup.get(keep)===markup)?keep:generated.cloneNode(true);
  binding.markup.set(node,markup);binding.dirty.delete(node);desired.push(node);
 }
 let cursor=host.firstChild;
 for(const node of desired){
  if(node===preserved?.node&&node!==cursor&&node.parentNode===host)while(cursor&&cursor!==node){const next=cursor.nextSibling;cursor.remove();cursor=next;}
  if(node===cursor)cursor=cursor.nextSibling;else host.insertBefore(node,cursor);
 }
 while(cursor){const next=cursor.nextSibling;cursor.remove();cursor=next;}
 binding.structureDirty=false;return binding;
}
function paintFilters(markup,filters){
 const host=$('#filters'),focused=document.activeElement,focusedEditor=host.contains(focused)&&focused.matches?.(filterEditorSelector)?focused:null;
 const leased=!focusedEditor&&filterDraft&&filterEditorLease?.isConnected&&host.contains(filterEditorLease)?filterEditorLease:null,active=focusedEditor||leased,scope=searchKey(state.search);
 const facet=active?.dataset.facetSearch;
 const group=active&&host.contains(active)&&(facet||['hotel-query','min-price','max-price','price-range'].includes(active.id))?active.closest('.filter-group'):null;
 const binding=filterRootBinding(host);binding.mark(binding.observer.takeRecords());
 const sameScope=renderedFilterContext?.scope===scope,sameModel=renderedFilterContext?.filters===filters;
 if(renderedFilterContext&&(!sameScope||!sameModel&&!filterDraft))binding.structureDirty=true;
 const template=document.createElement('template');template.innerHTML=markup;
 let preserved=null;
 // A mobile draft wrapper may be refreshed while a progressive provider result
 // is folded into the same search. Once the drawer closes, object identity again
 // prevents a cancelled draft from leaking into the applied filter model.
 if(group&&sameScope&&(sameModel||filterDraft)){
  const replacement=template.content.querySelector(facet?`[data-facet-search="${facet}"]`:active.id==='hotel-query'?'#hotel-query':'#min-price')?.closest('.filter-group');
  if(replacement&&replacement.parentNode===template.content){
   const replacementEditor=replacement.querySelector(facet?`[data-facet-search="${facet}"]`:`#${active.id}`),expected=filterRootMarkup(replacement);
   if(replacementEditor){replacementEditor.replaceWith(active);preserved={node:replacement,index:[...template.content.childNodes].indexOf(replacement),markup:expected,transferred:true};}
  }
 }
 reconcileFilterRoots(host,template.content,binding,preserved);
 renderedFilterContext={filters,scope};$$('[data-facet-options]').forEach(applyFacetSearch);syncAvailableFilterGroups();
 if(preserved&&group&&(preserved.transferred||leased||!host.contains(active))){
  const restored=host.contains(active)?active:host.querySelector(facet?`[data-facet-search="${facet}"]`:`#${active.id}`);
  if(restored){restored.value=active.value;restored.focus({preventScroll:true});if(active.selectionStart!==null)try{restored.setSelectionRange(active.selectionStart,active.selectionEnd,active.selectionDirection)}catch{}}
 }
 // Dynamic facet/count presentation is owned by this painter, not an external disturbance.
 settleFilterRoots(host);
}
function renderFilterNavigation(){
 const select=$('#filter-section-jump');
 const headings=$$('#filters .filter-group:not([hidden])>h4');
 headings.forEach((heading,i)=>{heading.id='filter-heading-'+i;heading.tabIndex=-1;});
 select.innerHTML='<option value="">К разделу фильтров…</option><option value="drawer-context">Выбранные условия</option>'+headings.map(h=>`<option value="${h.id}">${esc(h.textContent)}</option>`).join('');
}
function jumpToFilterSection(id){
 const panel=$('#filter-panel');let target=document.getElementById(id);if(!target||!panel.contains(target))return;
 const group=target.closest('.filter-group');if(innerWidth<=1100&&group?.dataset.filterSection){setFilterSectionOpen(group,true);target=group.querySelector('.filter-section-toggle');}
 const header=panel.querySelector('.filter-top');
 const top=panel.scrollTop+target.getBoundingClientRect().top-panel.getBoundingClientRect().top-header.getBoundingClientRect().height-12;
 panel.scrollTop=Math.max(0,top);if(!target.matches('button'))target.tabIndex=-1;target.focus({preventScroll:true});
 $('#filter-section-jump').value='';
}
function filterStarOptions(model){const f=model.filters,hs=hotels.filter(h=>h.country===state.search.country);return [...new Set([...hs.map(h=>h.stars).filter(n=>Number.isInteger(n)&&n>=1&&n<=5),...f.stars])].sort((a,b)=>a-b);}
function filterStarButtons(model,counts=null){const f=model.filters,starOptions=counts?[...counts.keys()]:filterStarOptions(model);counts??=countFacetOptions(model,'stars',starOptions);return starOptions.map(n=>{const count=counts.get(n),selected=f.stars.includes(n);return count>0||selected?`<button type="button" data-action="star" data-value="${n}" aria-label="${n} ${n===1?'звезда':n<5?'звезды':'звёзд'} — ${hotelCountText(count)}" aria-pressed="${selected}" class="${selected?'active':''}"><span>${n} ★</span><small aria-hidden="true">${count}</small></button>`:''}).join('');}
function updateFilterStars(counts=null){const host=$('#filters .star-options');if(!host)return;const focused=document.activeElement,active=host.contains(focused)?focused.dataset.value:null,html=filterStarButtons(editingFilterModel(),counts);if(host.innerHTML!==html){host.innerHTML=html;if(active)host.querySelector(`[data-value="${active}"]`)?.focus({preventScroll:true});}host.closest('.filter-group').hidden=!html;}
function renderFilters(ratingCount){const queryScope=JSON.stringify([searchKey(state.search),data.scenario]);if(queryScope!==facetQueryScope){facetQueries.clear();facetQueryScope=queryScope;}const model=editingFilterModel(),f=model.filters,hs=hotels.filter(h=>h.country===state.search.country),scale=budgetScale(f),budgetEdit=currentFilterBudgetEdit(f),starButtons=filterStarButtons(model);paintFilters(`
 <div class="filter-group"><h4>Название отеля или курорт</h4><div class="filter-search"><input class="input" id="hotel-query" type="search" value="${esc(f.q)}" placeholder="Название или несколько слов" aria-label="Название отеля или курорт">${icon('search')}<button type="button" class="icon-button clear-hotel-query" data-action="clear-hotel-query" aria-label="Очистить название отеля или курорт" ${f.q?'':'hidden'}>${icon('x')}</button></div></div>
 <div class="filter-group"><h4>Бюджет на всех туристов</h4><div class="price-inputs"><label>От, ₽<input type="text" inputmode="decimal" id="min-price" value="${esc(budgetEdit?.minText??f.min)}" aria-describedby="filter-budget-error"></label><label>До, ₽<input type="text" inputmode="decimal" id="max-price" value="${esc(budgetEdit?.maxText??f.max??'')}" placeholder="Без лимита" aria-describedby="filter-budget-error"></label></div><input class="range" type="range" id="price-range" aria-label="Максимальная цена" aria-valuetext="${esc(budgetLabel(f))}" min="${f.min}" max="${scale}" step="1000" value="${f.max??scale}"><p class="error-text filter-budget-error" id="filter-budget-error" role="alert" hidden></p></div>
 <div class="filter-group" ${starButtons?'':'hidden'}><h4>Категория отеля</h4><div class="star-options">${starButtons}</div></div>
 ${Object.keys(mealNames).length?`<div class="filter-group"><h4>Питание</h4>${checkRows('meals',[...new Set([...Object.keys(mealNames),...f.meals])].map(m=>[m,m]))}</div>`:''}
 ${f.rating||hs.some(h=>ratingValue(h)!==null)?(()=>{const count=ratingCount??countMatchingHotels({...model,filters:{...f,rating:true}});return `<div class="filter-group"><h4>Оценка гостей</h4>${filterCheckRowHTML('data-filter-bool="rating"','От 4,5 из 5',count,f.rating)}</div>`})():''}
 ${f.resorts.length||hs.some(h=>hotelPlaces(h).length)?`<div class="filter-group"><h4>Курорт</h4>${checkRows('resorts',[...new Set([...f.resorts,...hs.flatMap(h=>hotelPlaces(h))])].map(r=>[r,r]))}</div>`:''}
 ${f.operators.length||operators.length?`<div class="filter-group"><h4>Туроператор</h4>${checkRows('operators',[...new Set([...f.operators,...operators])].map(o=>[o,o]))}</div>`:''}
 ${amenityFilterGroups(hs,f)}
 <div class="filter-hint">${icon('info')}<span>${!state.hasSearched&&!state.onlyFavorites?'Условия применятся после нажатия «Найти туры». Доступные курорты и туроператоры появятся в выдаче.':'Фильтры применяются к найденным предложениям. Актуальная цена и сборы уточняются при выборе.'}</span></div>`,f);
 renderFilterNavigation();syncFilterResetState(model);showFilterBudgetValidity(readBudgetFields($('#min-price'),$('#max-price')));$('#beach-chip').hidden=true;$('#family-chip').hidden=true;settleFilterRoots($('#filters'));
}
function loadResultCalendar(){
 if(!catalogReady||!state.hasSearched)return;
 const s=structuredClone(state.search),f=state.filters,filters=structuredClone(f);
 const key=JSON.stringify([s,filters]);if(resultCalendar.key===key)return;
 resultCalendar.controller?.abort();const request={key,hotels:[],observations:[],phase:'loading',controller:new AbortController()};resultCalendar=request;
 const show=snapshot=>{if(resultCalendar!==request)return;request.hotels=snapshot.hotels;request.observations=snapshot.observations;renderCalendarStrip();};
 data.calendarPrices(s,s.from,s.to,request.controller.signal,filters,show).then(snapshot=>{
  if(resultCalendar!==request)return;request.hotels=snapshot.hotels;request.observations=snapshot.observations;request.phase=snapshot.partial?'partial':'complete';renderCalendarStrip();
 }).catch(error=>{if(resultCalendar!==request||error.name==='AbortError')return;request.phase='error';renderCalendarStrip();});
}
function resultCalendarModel(){
 const s=state.search,days=[];for(let day=s.from;day<=s.to;day=addDays(day,1))days.push(day);
 const calendarRows=[...hotels,...resultCalendar.hotels],prices=calendarMinimums(days,{calendarHotels:calendarRows},resultCalendar.observations),known=prices.filter(p=>p!==null),min=Math.min(...known),max=Math.max(...known);
 const source=resultCalendar.phase==='loading'?'Открываем сохранённые цены…':resultCalendar.phase==='error'?'База цен временно недоступна · показаны найденные предложения':resultCalendar.phase==='partial'?'Часть базы цен временно недоступна · показаны доступные цены и найденные предложения':calendarSourceLabel();
 const scope=calendarScope({search:s,filters:state.filters});
 return {s,days,prices,min,max,source,scope};
}
function calendarStripEntries({days,prices,min,max}){return days.map((day,i)=>{const price=prices[i];return {day,markup:`<button class="date-price ${price!==null&&price===min?'best':''} ${state.selectedDate===day?'selected':''}" data-action="select-date" data-date="${day}" aria-pressed="${state.selectedDate===day}" aria-label="Вылет ${dateLong(day)}${price!==null?', от '+money(price):', цена пока неизвестна'}${state.selectedDate===day?', выбрано; нажмите ещё раз, чтобы вернуть все даты':''}"><span class="date">${dateText(day)}</span><strong>${price===null?'—':money(price)}</strong><span class="calendar-bar" style="--bar-height:${price===null?5:12+Math.round((price-min)/Math.max(1,max-min)*22)}px"></span></button>`};});}
function calendarStripHTML(model){return calendarStripEntries(model).map(entry=>entry.markup).join('');}
function paintCalendarStrip(strip,entries){paintGeneratedRoots(strip,entries,true,'day');}
function renderCalendarStrip(){
 loadResultCalendar();
 const model=resultCalendarModel(),{s,source,scope}=model;
 $('#calendar-caption').textContent=source;$('#calendar-caption').title=`${scope.destination} · ${guestsText(s)} · ${durationText(s)}${scope.filters.length?' · с выбранными фильтрами':''}`;
 const strip=$('#price-strip');strip.setAttribute('aria-busy',String(resultCalendar.phase==='loading'));
 paintCalendarStrip(strip,calendarStripEntries(model));
 $('#clear-date').hidden=!state.selectedDate;
 refreshEmptyCalendarContext();
}
function renderActive(ratingCounts){const f=state.filters,chips=filterChipData();
 $('#sort').value=state.sort;$('#mobile-sort').value=state.sort;
 const sortLabel=state.sort==='price'?'Дешевле':state.sort==='rating'?'Рейтинг':'Сортировка';
 $('#mobile-sort-label').textContent=sortLabel;$('.mobile-sort').classList.toggle('active',state.sort!=='recommended');
 $('#active-filters').innerHTML=chips.map(c=>`<button class="active-filter" data-action="remove-filter" data-key="${c.key}" data-value="${esc(c.value)}" aria-label="Убрать: ${esc(c.label)}">${esc(c.label)}${icon('x')}</button>`).join('');
 for(const key of ['beach','rating','family']){$('#'+key+'-chip').classList.toggle('active',f[key]);$('#'+key+'-chip').setAttribute('aria-pressed',f[key])}
 const ratingTotal=ratingCounts.total,ratingCount=ratingCounts.rating,ratingChip=$('#rating-chip');
 ratingChip.hidden=!f.rating&&(ratingCount===0||ratingCount===ratingTotal);
 $('#rating-chip-count').textContent=ratingChip.hidden?'':`· ${ratingCount}`;
 ratingChip.setAttribute('aria-label',`${f.rating?'Убрать фильтр':'Показать'}: рейтинг от 4,5 — ${hotelCountText(ratingCount)}`);
 const n=filterCount();$('#filter-count').textContent=n?`(${n})`:'';$('#mobile-count').textContent=n?`(${n})`:'';$('#drawer-filter-count').textContent=n?`(${n})`:'';return ratingCounts;
}
function offerHTML(h,o){return `<div class="offer" data-offer-key="${o.key}"><div><strong>${dateText(o.day)} → ${dateText(o.returnDay)}</strong><small>${nightsText(o.nights)} · ${guestsText(o)}</small><small>Город вылета: ${esc(o.origin)}</small></div><div><strong>${esc(mealLabel(o))}</strong><small>${esc(o.room)}</small></div><div><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}<small>${offerMetaNote(o)}</small></div><div class="offer-price"><strong>${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${o.key}">${offerActionLabel(o)} ${icon('arrow')}</button></div></div>`;}
function cardHTML({hotel:h,offers}){const o=offers[0],opened=state.openHotel===h.id,photoIndex=state.photoIndexes[h.id]||0,popularityBadge=popularity?.badge(h)||'',shortlistActions=optionalShortlistEnabled?`<button class="favorite-button ${state.favorites.includes(h.id)?'active':''}" data-action="favorite" data-id="${h.id}" aria-pressed="${state.favorites.includes(h.id)}" aria-label="${state.favorites.includes(h.id)?'Убрать из избранного':'В избранное'}: ${esc(h.name)}">${icon('heart')}</button><button class="compare-photo-button ${state.compare.includes(h.id)?'active':''}" data-action="toggle-compare" data-id="${h.id}" aria-pressed="${state.compare.includes(h.id)}" aria-label="${state.compare.includes(h.id)?'Убрать из сравнения':'Сравнить'}: ${esc(h.name)}" title="${state.compare.includes(h.id)?'В сравнении':'Сравнить отель'}">${icon(state.compare.includes(h.id)?'check':'compare')}</button>`:'';return `<article class="hotel-card" id="hotel-${h.id}" data-hotel-id="${h.id}"><div class="hotel-main">
 <div class="hotel-photos ${h.photos.length?'':'photo-unavailable'}"><div class="hotel-image-wrap">${h.photos.length?'':`<span class="photo-missing-label">${icon('image')}Нет фотографий</span>`}<button class="hotel-image-button" data-action="gallery" data-id="${h.id}" ${h.photos.length?'':'disabled'} aria-label="${h.photos.length?'Открыть фотографии':'Фото пока недоступны:'} ${esc(h.name)}"><img class="hotel-image" src="${esc(photoUrl(h,photoIndex))}" alt="Фото отеля ${esc(h.name)}" loading="lazy" width="700" height="500"><span class="photo-count">${icon('image')} <span class="photo-index">${h.photos.length?photoIndex+1:0}</span> / ${h.photos.length}</span></button>${shortlistActions}<button class="card-photo-arrow prev" data-action="card-photo" data-id="${h.id}" data-dir="-1" aria-label="Предыдущее фото ${esc(h.name)}">${icon('back')}</button><button class="card-photo-arrow next" data-action="card-photo" data-id="${h.id}" data-dir="1" aria-label="Следующее фото ${esc(h.name)}">${icon('arrow')}</button></div><div class="card-thumbs">${h.photos.slice(0,h.photos.length>4?3:4).map((p,i)=>`<button class="card-thumb ${photoIndex===i?'active':''}" data-action="card-photo-index" data-id="${h.id}" data-value="${i}" aria-label="Показать фото ${i+1} отеля ${esc(h.name)}" aria-pressed="${photoIndex===i}"><img src="${esc(p)}" alt="" loading="lazy" width="150" height="100"></button>`).join('')}${h.photos.length>4?`<button class="card-more-photos" data-action="gallery" data-id="${h.id}" aria-label="Все ${h.photos.length} фотографий отеля ${esc(h.name)}">${icon('image')}<span>Все ${h.photos.length}</span></button>`:''}</div></div>
 <div class="hotel-info"><div class="hotel-info-top"><div>${popularityBadge?`<span class="hotel-popularity-badge" title="Входит в TOP500 продаваемых отелей">${esc(popularityBadge)}</span>`:'' }${hotelStarsHTML(h)}<h3><button data-action="hotel-details" data-id="${h.id}" title="${esc(h.name)}"><span class="hotel-card-name">${esc(h.name)}</span>${icon('arrow')}</button></h3><div class="hotel-location">${icon('pin')} ${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</div></div>${ratingValue(h)!==null?`<div class="rating-block" aria-label="Оценка гостей ${ratingText(h)} из 5"><strong>${ratingText(h)}<small>/ 5</small></strong><span>${ratingValue(h)>=4.5?'Отлично':'Оценка гостей'}</span></div>`:''}</div><div class="hotel-facts"><span>${esc(hotelHighlights(h))}</span></div></div></div>
 ${minimumOfferSummary(o)}<div class="hotel-price"><div class="starting-price"><span>За ${guestsText(o)} · весь тур</span><strong>${money(o.total)}</strong></div><span class="fuel-note">${icon('info')} ${cardPriceNote(o)}</span><button class="primary" data-action="offer" data-card-entry="true" data-key="${esc(o.key)}" aria-label="Смотреть тур: ${esc(h.name)}"><span>Смотреть тур</span> ${icon('arrow')}</button></div><div class="hotel-more"><button class="text-button" data-action="hotel-details" data-id="${h.id}">Об отеле</button>${offers.length>1?`<button class="text-button" data-action="all-offers" data-id="${h.id}">Все туры (${offers.length}) ${icon('arrow')}</button>`:''}</div></article>`;}


function refreshOpenHotelRooms(){
 if(modalType!=='hotel-details')return;
 const body=$('#modal-body'),id=Number($('.hotel-section-nav')?.dataset.hotelId);if(!id)return;
 const focused=document.activeElement,focus=focusReference(focused,body),scroll=body.scrollTop;
 const openRooms=[...body.querySelectorAll('.room-overview[open],article.hotel-room-card')].map(room=>room.dataset.room);
 const expandedMore=[...body.querySelectorAll('.hotel-room-more[open]')].map(more=>more.closest('[data-room]').dataset.room);
 const summaryRoom=focused?.matches('summary')?focused.closest('[data-room]')?.dataset.room:null;
 const summaryMore=focused?.parentElement?.classList.contains('hotel-room-more');
 renderHotelRooms(id,$('#hotel-room-meal')?.value||'',openRooms);
 for(const room of body.querySelectorAll('.hotel-room-card[data-room]'))if(expandedMore.includes(room.dataset.room)){const more=room.querySelector('.hotel-room-more');if(more)more.open=true;}
 if(summaryRoom!==null&&summaryRoom!==undefined){const room=[...body.querySelectorAll('.hotel-room-card[data-room]')].find(room=>room.dataset.room===summaryRoom);room?.querySelector(summaryMore?'.hotel-room-more>summary':':scope>summary')?.focus({preventScroll:true});}
 else if(focus&&document.activeElement!==focus.element)restoreFocus(focus,$('#hotel-room-count'),body);
 body.scrollTop=scroll;
}
function observeHotelRoomChoices(){
 hotelRoomObserver?.disconnect();hotelRoomObserver=null;
 const footer=$('#modal-footer'),rooms=$('.hotel-room-cards');
 if(modalType!=='hotel-details'||!footer||!rooms||rooms.classList.contains('single-direct-offer')){if(footer)footer.hidden=false;return;}
 const body=$('#modal-body'),visibleChoices=new Set();
 hotelRoomObserver=new IntersectionObserver(entries=>{if(modalType!=='hotel-details'||$('#modal-footer')!==footer)return;for(const entry of entries){if(entry.isIntersecting)visibleChoices.add(entry.target);else visibleChoices.delete(entry.target);}footer.hidden=visibleChoices.size>0;},{root:body,threshold:.7});
 rooms.querySelectorAll('.room-overview-toggle,[data-action="offer"]').forEach(action=>hotelRoomObserver.observe(action));
}
$('#modal-body').addEventListener('toggle',event=>{if(modalType==='hotel-details'&&event.target.matches('.room-overview,.hotel-room-more'))rememberUIRoute();},true);
let hotelNavigationFrame=0;
function syncHotelSectionNavigation(){
 if(modalType!=='hotel-details')return;
 const body=$('#modal-body'),nav=$('.hotel-section-nav');if(!nav)return;
 const links=[...nav.querySelectorAll('[data-target]')],threshold=body.getBoundingClientRect().top+nav.offsetHeight+28;
 let current=links[0];
 for(const link of links){const target=document.getElementById(link.dataset.target);if(target&&target.getBoundingClientRect().top<=threshold)current=link;}
 if(body.scrollHeight>body.clientHeight&&body.scrollTop+body.clientHeight>=body.scrollHeight-3)current=links.at(-1);
 for(const link of links){if(link===current)link.setAttribute('aria-current','location');else link.removeAttribute('aria-current');}
}
function scheduleHotelNavigation(){
 if(modalType!=='hotel-details'||hotelNavigationFrame)return;
 hotelNavigationFrame=requestAnimationFrame(()=>{hotelNavigationFrame=0;syncHotelSectionNavigation();});
}
$('#modal-body').addEventListener('scroll',scheduleHotelNavigation,{passive:true});
$('#modal-body').addEventListener('toggle',scheduleHotelNavigation,true);
addEventListener('resize',scheduleHotelNavigation);

// The hotel shell enters history immediately; its presentation loads on demand.
let hotelDetailsLoad=null,hotelDetailsRequest=0,hotelDetailsView=null;
const hotelDetailsAsset=document.head.dataset.hotelDetailsSrc;
function loadHotelDetails(){
 if(window.AnyTourHotelDetails?.create)return Promise.resolve(window.AnyTourHotelDetails);
 if(hotelDetailsLoad)return hotelDetailsLoad;
 hotelDetailsLoad=new Promise((resolve,reject)=>{
  const script=document.createElement('script');let finished=false;
  const finish=error=>{if(finished)return;finished=true;clearTimeout(timer);script.onload=script.onerror=null;script.remove();if(error){hotelDetailsLoad=null;reject(error);}else resolve(window.AnyTourHotelDetails);};
  const timer=setTimeout(()=>finish(new Error('Hotel details load timed out')),15000);
  script.onload=()=>finish(window.AnyTourHotelDetails?.create?null:new Error('Hotel details owner missing'));
  script.onerror=()=>finish(new Error('Hotel details load failed'));
  script.src=hotelDetailsAsset;document.head.append(script);
 });
 return hotelDetailsLoad;
}
function hotelDetailsOwner(){return window.AnyTourHotelDetails.create({$,$$,data,hotels,modalType,plainHotelText,esc,hotelOffers,mealLabel,dateText,nightsText,flightLabel,guestsText,money,icon,offerCountText,cardPriceNote,observeHotelRoomChoices,syncHotelSectionNavigation,rememberUIRoute,ratingValue,ratingText,departureScopeText,durationText});}
function renderHotelRooms(id,meal='',restoredRooms=null){
 if(!window.AnyTourHotelDetails?.create||modalType!=='hotel-details'||!$('#hotel-room-count')||Number($('.hotel-section-nav')?.dataset.hotelId)!==id)return;
 hotelDetailsOwner().renderHotelRooms(id,meal,restoredRooms);
}
function openHotelDetails(id,restored=null){
 const h=hotels.find(h=>h.id===id);if(!h)return;
 hotelDetailsView={id,restored};
 showModal('hotel-details',h.name,`${h.resort} · ${h.stars?h.stars+' ★':'Категория не указана'}`,`<nav class="hotel-section-nav" data-hotel-id="${h.id}" aria-label="Разделы отеля"></nav><div id="hotel-details-load" aria-live="polite"></div>`,true);
 $('#modal').classList.add('hotel-details-dialog');renderHotelDetails();
}
function renderHotelDetails(){
 const view=hotelDetailsView,request=++hotelDetailsRequest;
 const current=()=>hotelDetailsView===view&&request===hotelDetailsRequest&&modalType==='hotel-details'&&$('#modal').open;
 const render=()=>{
  if(!current()||!hotels.some(h=>h.id===view.id))return;
  hotelDetailsOwner().openHotelDetails(view.id);
  const restored=view.restored;view.restored=null;
  if(restored){const h=hotels.find(h=>h.id===view.id);renderHotelRooms(view.id,hotelOffers(h).some(o=>o.meal===restored.meal)?restored.meal:'',Array.isArray(restored.rooms)?restored.rooms:null);
   for(const room of $$('.hotel-room-card[data-room]'))if(Array.isArray(restored.more)&&restored.more.includes(room.dataset.room)){const more=room.querySelector('.hotel-room-more');if(more)more.open=true;}
   if(Number.isFinite(restored.scroll)&&restored.scroll>=0)$('#modal-body').scrollTop=restored.scroll;
  }
  rememberUIRoute();
 };
 if(window.AnyTourHotelDetails?.create){render();return;}
 $('#hotel-details-load').innerHTML='<p role="status">Загружаем подробности отеля…</p>';
 loadHotelDetails().then(render).catch(()=>{if(current())$('#hotel-details-load').innerHTML='<div role="alert"><p>Не удалось загрузить подробности отеля.</p><button class="secondary" data-action="retry-hotel-details">Попробовать ещё раз</button></div>';});
}

let renderedCardLimit=24,renderedCardScope='';
function refreshResultFilters(options,ratingCounts){
 const ratingCount=filterDraft?undefined:ratingCounts?.rating;
 if(!options.keepFilters){if(ratingCount===undefined)renderFilters();else renderFilters(ratingCount);}else{if(ratingCount===undefined)updateFacetCounts();else updateFacetCounts(ratingCount);syncFilterResetState();}
}
function refreshResultPickerPreviews(){
 if(filterDraft)updateDrawerPreview();if(modalType==='budget')updateBudgetPreview();if(modalType==='meals'){updateMealCounts();updateMealPicker();}
}
function renderResultHeadings(items,total,pristine){
 $('#results').classList.toggle('results-pristine',pristine);document.body.classList.toggle('results-pristine-active',pristine);
 $('#compact-route').textContent=`${esc(state.search.origin)} → ${destinationLabel(appliedDestination())}`;
 $('#compact-details').textContent=`${state.selectedDate?dateText(state.selectedDate):rangeText(state.search.from,state.search.to)} · ${durationText()} · ${guestsText()}`;

 $('#route-label').textContent=`${esc(state.search.origin)} → ${esc(countryNames[state.search.country]||'')} · ${departureScopeText()}`;
 $('#results-title').textContent=pristine?'Ваш следующий отдых':state.onlyFavorites?'Ваши избранные отели':`Отели и туры · ${countryNames[state.search.country]||'Выберите направление'}`;
 const failed=!pristine&&!items.length&&responseFor(state.search).phase==='error';
 $('#results-summary').textContent=pristine?'Задайте направление, даты и состав туристов — предложения появятся после поиска.':failed?`Результаты не получены · ${durationText()} · ${guestsText()}`:`${hotelCountText(items.length)} · ${total} ${total%10===1&&total%100!==11?'вариант':total%10>=2&&total%10<=4&&(total%100<12||total%100>14)?'варианта':'вариантов'} тура`;
 const canSort=items.length>1;$('.sort-label').hidden=!canSort;$('.mobile-sort').hidden=!canSort;
}
function paintResultCards(cards,entries,sameScope){paintGeneratedRoots(cards,entries,sameScope);}
function renderResultCards(items){
 const cards=$('#cards'),cardScope=JSON.stringify([state.search,state.filters,state.selectedDate,state.sort,state.onlyFavorites,data.scenario]),sameCardScope=cardScope===renderedCardScope;
 const active=document.activeElement,activeInCards=sameCardScope&&cards.contains(active),focus=activeInCards?focusReference(active,cards):null,focusAction=activeInCards?active.dataset.action:null;
 const anchor=activeInCards?active.closest('.hotel-card'):null,anchorId=anchor?.id||'',anchorTop=anchor?.getBoundingClientRect().top,anchorScroll=scrollY;
 if(!sameCardScope){renderedCardScope=cardScope;renderedCardLimit=24;}
 const entries=items.length?items.slice(0,renderedCardLimit).map(item=>({id:`hotel-${item.hotel.id}`,markup:cardHTML(item)})): [{id:'',markup:emptyResultsHTML()}];
 if(items.length>renderedCardLimit)entries.push({id:'',markup:`<button type="button" class="secondary load-more-cards" data-action="more-cards">Показать ещё ${Math.min(24,items.length-renderedCardLimit)} отеля <span>Показано ${Math.min(renderedCardLimit,items.length)} из ${items.length}</span></button>`});
 paintResultCards(cards,entries,sameCardScope);
 if(focus){
  const nextAnchor=anchorId?document.getElementById(anchorId):null,fallback=nextAnchor?.querySelector(focusAction?`[data-action="${CSS.escape(focusAction)}"]`:'button')||nextAnchor?.querySelector('button')||cards.querySelector('[data-action="more-cards"]')||$('#results');
  restoreFocus(focus,fallback,cards);
  if(nextAnchor&&Number.isFinite(anchorTop))window.scrollTo({top:anchorScroll+nextAnchor.getBoundingClientRect().top-anchorTop,behavior:'instant'});
 }
}
function renderResults(options={}){
 // A changed form is a draft, not a new result set. Keep cards, pagination and
 // the shareable URL intact until Search; child pickers may still preview counts.
 if(searchEditSession){
  refreshResultFilters(options);refreshResultPickerPreviews();
  return;
 }
 if(searchResponse.key&&searchResponse.key!==searchKey(state.search)){clearSearchTimers();searchResponse={key:searchKey(state.search),phase:'complete',operators:[...operators],pending:false};}
 const inventory=resultInventory(),items=inventory.items,total=items.reduce((s,r)=>s+r.offers.length,0),pristine=!state.hasSearched&&!state.onlyFavorites;
 renderResultHeadings(items,total,pristine);
 renderResultCards(items);
 $('#apply-filters').textContent=pristine?'Сохранить условия':`Показать отели (${items.length})`;
 renderCalendarStrip();renderActive(inventory.ratingCounts);updateNav();renderSummary();updateURL();refreshResultFilters(options,inventory.ratingCounts);renderSearchStatus(items,total);refreshResultPickerPreviews();
}
function syncFilters(){renderResults();updateSearchUI();}
function applyQuickFilters(){
 const showResults=state.hasSearched&&$('#search-form').hidden;
 if(showResults)pageReturn=null;
 syncFilters();closeModal();
 if(showResults)requestAnimationFrame(()=>{$('#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#results').focus({preventScroll:true});});
}

// One browser-history entry belongs to an open dialog/drawer session. Nested
// Back consumes the in-memory step before reusing that entry; closing consumes
// it altogether. Forward only reuses current-search outcomes held in memory;
// reload/history never authorizes a request or price. Keep search parameters canonical.
const uiHistoryKey='anytour.prototype.v18.ui.v1';
let uiHistoryOpen=false,uiHistoryClosing=false,restoringUIHistory=false,handlingUIBack=false,actionTrigger=null,pageReturn=null,pendingPageReturn=null;
function focusReference(element,root=document){
 if(!element||element===document.body||element===document.documentElement||!root.contains(element))return null;
 let selector=element.id?'#'+CSS.escape(element.id):'';
 if(!selector&&element.dataset?.action){selector='[data-action="'+CSS.escape(element.dataset.action)+'"]';for(const key of ['id','key','value','room'])if(element.dataset[key]!==undefined)selector+='[data-'+key+'="'+CSS.escape(element.dataset[key])+'"]';const card=element.closest('.hotel-card');if(card)selector='#'+CSS.escape(card.id)+' '+selector;}
 const scroller=root.querySelector?.('#modal-body'),top=scroller?.contains(element)?element.getBoundingClientRect().top-scroller.getBoundingClientRect().top:null;
 return {element,selector,top};
}
function restoreFocus(reference,fallback,root=document){
 const element=reference?.element?.isConnected&&root.contains(reference.element)?reference.element:reference?.selector?root.querySelector(reference.selector):null;
 const target=element&&element.getClientRects().length&&!element.matches(':disabled')?element:fallback;
 if(target){if(!target.matches('button,input,select,a,[tabindex]'))target.tabIndex=-1;target.focus({preventScroll:true});}
}
function capturePageReturn(){
 const trigger=actionTrigger||document.activeElement,card=trigger?.closest('.hotel-card');
 pageReturn={focus:focusReference(trigger),y:scrollY,card:card?.id,top:card?.getBoundingClientRect().top,url:location.search};
}
function boundedHistoryStrings(value,{max=20,pattern=null}={}){
 if(!Array.isArray(value)||value.length>max)return null;
 const strings=[...new Set(value)];
 return strings.every(item=>typeof item==='string'&&item.length>0&&item.length<=120&&(!pattern||pattern.test(item)))?strings:null;
}
function filterHistorySnapshot(model){
 if(!model?.filters)return null;const f=model.filters;
 return {filters:{hotelId:f.hotelId,q:f.q,stars:[...f.stars],meals:[...f.meals],resorts:[...f.resorts],operators:[...f.operators],flight:[...f.flight],amenities:[...f.amenities],min:f.min,max:f.max,rating:f.rating,beach:f.beach,family:f.family,spa:f.spa},onlyFavorites:model.onlyFavorites===true,selectedDate:model.selectedDate||null};
}
function restoreFilterHistoryModel(value){
 if(!value||typeof value!=='object'||!value.filters||typeof value.filters!=='object'||typeof value.onlyFavorites!=='boolean')return null;
 const f=value.filters,stars=Array.isArray(f.stars)&&f.stars.length<=5&&f.stars.every(n=>Number.isInteger(n)&&n>=1&&n<=5)?[...new Set(f.stars)]:null;
 const meals=boundedHistoryStrings(f.meals),resorts=boundedHistoryStrings(f.resorts),operators=boundedHistoryStrings(f.operators),flight=boundedHistoryStrings(f.flight,{pattern:/^(charter|regular)$/}),amenities=boundedHistoryStrings(f.amenities,{pattern:/^(1|2|3|5|8):[1-9]\d*$/});
 const hotelId=Number.isSafeInteger(f.hotelId)&&f.hotelId>=0?f.hotelId:null,q=typeof f.q==='string'&&f.q.length<=200?f.q:null,min=Number.isFinite(f.min)&&f.min>=0&&f.min<=1e12?f.min:null,max=f.max===null?null:Number.isFinite(f.max)&&f.max>=0&&f.max<=1e12?f.max:NaN;
 const flags=['rating','beach','family','spa'];
 if(hotelId===null||q===null||stars===null||meals===null||resorts===null||operators===null||flight===null||amenities===null||min===null||Number.isNaN(max)||max!==null&&min>max||flags.some(key=>typeof f[key]!=='boolean'))return null;
 const selected=value.selectedDate===null?null:typeof value.selectedDate==='string'&&/^\d{4}-\d{2}-\d{2}$/.test(value.selectedDate)&&value.selectedDate>=state.search.from&&value.selectedDate<=state.search.to?value.selectedDate:null;
 if(value.selectedDate!==null&&selected===null)return null;
 return {filters:{hotelId,q,stars,meals,resorts,operators,flight,amenities,min,max,...Object.fromEntries(flags.map(key=>[key,f[key]]))},onlyFavorites:value.onlyFavorites,selectedDate:selected};
}
const boundedHistoryText=(value,fallback,max=120)=>typeof value==='string'&&value.length<=max?value:fallback;
function finishPageReturn(saved){
 requestAnimationFrame(()=>{
  if(uiHistoryOpen||$('#modal').open||filterDraft)return;
  if(saved&&saved.url===location.search){const card=saved.card?document.getElementById(saved.card):null;const y=card?scrollY+card.getBoundingClientRect().top-saved.top:saved.y;window.scrollTo({top:y,behavior:'instant'});}
  requestAnimationFrame(()=>{if(!uiHistoryOpen&&!uiHistoryClosing)history.scrollRestoration='auto';});
 });
}
function restorePageReturn(){
 const saved=pageReturn;pageReturn=null;if(saved)restoreFocus(saved.focus,$('#results'));
 if(uiHistoryClosing)pendingPageReturn=saved;else finishPageReturn(saved);
}
function providerHistoryRoute(type){return {type,key:selectedOffer?.key,scroll:$('#modal-body').scrollTop,...(type==='andromeda-flights'?{outbound:$('[name="andromeda-outbound"]:checked')?.value,inbound:$('[name="andromeda-return"]:checked')?.value}:{}),...(type==='anex-quote'?{choice:$('[name="anex-package-choice"]:checked')?.value}:{})};}
function uiRoute(){
 if($('#filter-panel').classList.contains('open')){const budget=currentFilterBudgetEdit(filterDraft?.filters);return {type:'filters',draft:filterHistorySnapshot(filterDraft),budget:budget?{minText:budget.minText,maxText:budget.maxText}:null,sections:[...expandedFilterSections],scroll:$('#filter-panel').scrollTop};}
 const type=modalType;
 if(['andromeda-flights','andromeda-verified','provider-application','anex-current','anex-additional','anex-quote','anex-application'].includes(type))return providerHistoryRoute(type);
 if(type==='departure')return {type,query:$('#departure-query')?.value||''};
 if(type==='destination')return {type,choice:{country:destinationChoice?.country,resorts:[...(destinationChoice?.resorts||[])],hotelId:destinationChoice?.hotelId||0},query:$('#destination-query')?.value||'',resolvedQuery:destinationResolvedQuery,expanded:destinationResortsExpanded,limit:destinationHotelLimit,scroll:$('#modal-body').scrollTop};
 if(type==='guests')return {type,draft:{adults:guestDraft.adults,ages:[...(guestDraft.ages||[])]}};
 if(type==='nights')return {type,draft:{min:nightsDraft.min,max:nightsDraft.max,phase:nightsDraft.phase===1?1:0}};
 if(type==='verification')return selectedOffer===savedSelection?{type:'saved-tour'}:{type:'offer',key:selectedOffer?.key,flightChoiceId:selectedOffer?.flightChoiceId};
 if(type==='flights'){const o=flightDraft?.base;return o?.isSavedSelection?{type:'saved-details'}:{type:'offer',key:o?.key,flightChoiceId:o?.flightChoiceId};}
 if(type==='selected-tour')return {type,key:selectedOffer?.key,scroll:$('#modal-body').scrollTop};
 if(type==='offer')return selectedOffer===savedSelection?{type:'saved-details'}:{type,key:selectedOffer?.key,flightChoiceId:selectedOffer?.flightChoiceId};
 if(type==='all-offers')return {type,id:offerView?.id,mode:offerView?.mode,departure:offerView?.departure,day:offerView?.day,nights:offerView?.nights,flight:offerView?.flight,room:offerView?.room,meal:offerView?.meal,sort:offerView?.sort,pair:offerView?.pair,activeVariant:offerView?.activeVariant,differencesOnly:offerView?.differencesOnly,open:[...(offerView?.open||[])],limits:{...(offerView?.limits||{})},filtersOpen:$('.offer-filter-disclosure')?.open===true,scroll:$('#modal-body').scrollTop};
 if(type==='hotel-details'&&hotelDetailsView?.restored&&!$('#hotel-room-count'))return {...hotelDetailsView.restored,type,id:hotelDetailsView.id};
 if(type==='hotel-details')return {type,id:Number($('.hotel-section-nav')?.dataset.hotelId)||null,meal:$('#hotel-room-meal')?.value||'',rooms:$$('.room-overview[open]').map(el=>el.dataset.room),more:$$('#modal-body .hotel-room-more[open]').map(el=>el.closest('[data-room]').dataset.room),scroll:$('#modal-body').scrollTop};
 if(type==='gallery')return {type,id:gallery.id,index:gallery.index};
 if(type==='dates')return {type,source:dateContext?.source,draft:{from:dateDraft.from,to:dateDraft.to,phase:dateDraft.phase===1?1:0},month:calendarMonth,scroll:$('#modal-body').scrollTop};
 if(type==='meals')return {type,meals:[...mealDraft],query:$('#meal-query')?.value||'',scroll:$('#modal-body').scrollTop};
 if(type==='budget')return {type,minText:$('#budget-min')?.value||'',maxText:$('#budget-max')?.value||'',scroll:$('#modal-body').scrollTop};
 return {type};
}
function rememberUIRoute(){
 if(!uiHistoryOpen||uiHistoryClosing||handlingUIBack)return;
 const route=uiRoute();if(JSON.stringify(history.state?.[uiHistoryKey])!==JSON.stringify(route))history.replaceState({...history.state,[uiHistoryKey]:route},'',location.href);
}
function enterUIHistory(){
 if(uiHistoryOpen)return;
 uiHistoryOpen=true;capturePageReturn();history.scrollRestoration='manual';
 if(!restoringUIHistory&&!uiHistoryClosing)history.pushState({...history.state,[uiHistoryKey]:{type:'pending'}},'',location.href);
}
function leaveUIHistory(fromHistory=false){
 if(!uiHistoryOpen)return;
 if(!fromHistory)rememberUIRoute();uiHistoryOpen=false;
 if(!fromHistory&&history.state?.[uiHistoryKey]){uiHistoryClosing=true;history.back();}
}
function restoreProviderHistoryRoute(route){
  const o=offerFromKey(route.key),view=retainedProviderView(o),provider=route.type.startsWith('anex-')?'anex':'andromeda';
  // History is a passive locator, never authority for a price or supplier request.
  if(o?.provider!==provider||!view||!view.type.startsWith(provider+'-'))return false;
  if(route.type==='provider-application'&&view.type!=='andromeda-verified'||route.type==='anex-application'&&view.type!=='anex-additional'&&!(view.type==='anex-quote'&&view.result?.state==='quote_verified'))return false;
  if(view.type==='andromeda-flights'){selectedOffer=view.offer;renderRealOffer();}
  if(!restoreProviderView(o))return false;
  if(modalType==='andromeda-flights'&&!view.pending)for(const [name,value] of [['andromeda-outbound',route.outbound],['andromeda-return',route.inbound]]){const input=$$('[name="'+name+'"]').find(el=>el.value===value);if(input){input.checked=true;rememberAndromedaFlightChoice(input);}}
  if(modalType==='anex-quote'&&!view.pending&&!view.error){const input=$$('[name="anex-package-choice"]').find(el=>el.value===route.choice);if(input){input.checked=true;retainedProviderView(o).choice=input.value;}}
  if(route.type==='provider-application')openAndromedaApplicationPreview();
  if(route.type==='anex-application')openAnexApplicationPreview();
  if(Number.isFinite(route.scroll)&&route.scroll>=0)$('#modal-body').scrollTop=route.scroll;
  return true;
}
function reopenUIRoute(route){
 if(!route||typeof route!=='object')return false;
 const hotel=hotels.find(h=>h.id===route.id);
 switch(route.type){
 case 'filters':if(innerWidth>1100)return false;openFilters(route);break;
 case 'departure':openDeparture(route);break;
 case 'destination':openDestination(route);break;
 case 'guests':openGuests(route);break;
 case 'nights':openNights(route);break;
 case 'dates':openDates(route.source==='results'?'results':'form',route);if(Number.isFinite(route.scroll)&&route.scroll>=0)$('#modal-body').scrollTop=route.scroll;break;
 case 'meals':openMeals(route);break;
 case 'budget':openBudget(route);break;
 case 'saved-tour':case 'saved-details':return false;
 case 'andromeda-flights':case 'andromeda-verified':case 'provider-application':case 'anex-current':case 'anex-additional':case 'anex-quote':case 'anex-application':{
  if(!restoreProviderHistoryRoute(route))return false;
  break;
 }
 case 'selected-tour':{
  if(!route.key||selectedOffer?.key!==route.key)return false;
  openLeadPreview();if(Number.isFinite(route.scroll)&&route.scroll>=0)$('#modal-body').scrollTop=route.scroll;break;
 }
 case 'favorites':openFavorites();break;
 case 'compare':openCompare();break;
 case 'hotel-details':{if(!hotel)return false;openHotelDetails(hotel.id,route);break;}
 case 'all-offers':{
  if(!hotel)return false;openAllOffers(hotel.id,route);
  const filters=$('.offer-filter-disclosure');if(filters)filters.open=route.filtersOpen===true;
  if(Number.isFinite(route.scroll)&&route.scroll>=0)$('#modal-body').scrollTop=route.scroll;break;
 }
 case 'gallery':if(!hotel)return false;openGallery(hotel.id,Number.isInteger(route.index)&&route.index>=0&&route.index<hotel.photos.length?route.index:0);break;
 case 'offer':{
  const o=offerFromKey(route.key);if(!o)return false;
  const retained=selectedOffer?.key===route.key?selectedOffer:null;
  selectedOffer=retained&&!retained.loading&&!retained.flightsLoading?retained:{...o,quoteError:needsRefresh(o)?'':'Проверьте актуальность тура, чтобы продолжить выбор.'};
  renderRealOffer();break;
 }
 case 'about':$('[data-action="about"]').click();break;
 default:return false;
 }
 return true;
}
function restoreHistoryView(route){
 // Let the browser finish its history/document transition before changing the
 // top layer or focus. This also captures the restored page position correctly.
 requestAnimationFrame(()=>{
  if(uiHistoryOpen||JSON.stringify(history.state?.[uiHistoryKey])!==JSON.stringify(route))return;
  restoringUIHistory=true;const restored=reopenUIRoute(route);restoringUIHistory=false;
  if(!restored){const next={...history.state};delete next[uiHistoryKey];history.replaceState(next,'',location.href);}
  updateURL();
 });
}
addEventListener('popstate',e=>{
 if(uiHistoryClosing){uiHistoryClosing=false;const saved=pendingPageReturn;pendingPageReturn=null;updateURL();if(uiHistoryOpen){history.pushState({...history.state,[uiHistoryKey]:uiRoute()},'',location.href);}else finishPageReturn(saved);return;}
 if(uiHistoryOpen){
  if($('#modal').open&&modalHistory.length){handlingUIBack=true;modalBack();handlingUIBack=false;history.pushState({...history.state,[uiHistoryKey]:uiRoute()},'',location.href);}
  else if($('#modal').open)closeModal({fromHistory:true});
  else closeFilters({fromHistory:true});
  updateURL();return;
 }
 if(e.state?.[uiHistoryKey]){restoreHistoryView(e.state[uiHistoryKey]);return;}
 updateURL();
});
function updateModalBack(){
 const previous=modalHistory.at(-1),button=$('#modal-back');button.hidden=!previous;
 const labels={'hotel-details':'К отелю','all-offers':'К вариантам тура',offer:'К деталям тура',flights:'К перелётам',gallery:'К фотографиям',favorites:'В избранное',compare:'К сравнению','selected-tour':'К выбранному туру','saved-tour':'К выбранному туру'};
 const label=labels[previous?.type]||'Назад';
 button.setAttribute('aria-label',label);button.title=previous?label+': '+previous.title:label;
 $('#modal-back-label').textContent=label;
}
function captureModalStep(m){
 return {type:modalType,title:$('#modal-title').textContent,kicker:$('#modal-kicker').textContent,body:$('#modal-body').innerHTML,footer:$('#modal-footer').innerHTML,footerHidden:$('#modal-footer').hidden,className:m.className,scroll:$('#modal-body').scrollTop,gallery:{...gallery},offer:selectedOffer,focus:focusReference(actionTrigger||document.activeElement,m)};
}
function restoreModalStepSnapshot(previous){
 $('#modal').className=previous.className;$('#modal-footer').innerHTML=previous.footer;$('#modal-footer').hidden=previous.footerHidden;gallery=previous.gallery;selectedOffer=previous.offer;updateModalBack();
}
function restoreHotelDetailStep(previous){
  const id=Number($('.hotel-section-nav')?.dataset.hotelId),h=hotels.find(h=>h.id===id);
  const meal=$('#hotel-room-meal')?.value||'',meals=new Set(hotelOffers(h).map(o=>o.meal));
  const openRooms=$$('.room-overview[open],article.hotel-room-card').map(room=>room.dataset.room);
  const expandedMore=$$('.hotel-room-more[open]').map(more=>more.closest('[data-room]').dataset.room);
  renderHotelRooms(id,!meal||meals.has(meal)?meal:'',openRooms);
  for(const room of $$('.hotel-room-card[data-room]'))if(expandedMore.includes(room.dataset.room)){const more=room.querySelector('.hotel-room-more');if(more)more.open=true;}
  const returningOffer=previous.focus?.selector?$('#modal-body').querySelector(previous.focus.selector):null;
  if(returningOffer?.matches('[data-action="offer"]'))returningOffer.closest('.hotel-room-more')?.setAttribute('open','');
}
function restoreModalStepFocus(previous){
 const modalBody=$('#modal-body');restoreFocus(previous.focus,$('#modal-title'),$('#modal'));modalBody.scrollTop=previous.scroll;
 if(Number.isFinite(previous.focus?.top)&&modalBody.contains(document.activeElement)){const top=document.activeElement.getBoundingClientRect().top-modalBody.getBoundingClientRect().top;modalBody.scrollTop+=top-previous.focus.top;}
}
function showModal(type,title,kicker,body,wide=false){
 cancelVerification();hotelRoomObserver?.disconnect();hotelRoomObserver=null;const m=$('#modal'),newStep=!m.open||modalType!==type;
 if(!m.open){modalHistory.length=0;enterUIHistory();}
 else if(!restoringModal&&modalType!==type){modalHistory.push(captureModalStep(m));}
 modalType=type;updateModalBack();m.className=type==='gallery'?'gallery-dialog':wide?'wide-dialog':type==='dates'?'dates-dialog':'';$('#modal-title').textContent=title;$('#modal-kicker').textContent=kicker;$('#modal-body').innerHTML=body;$('#modal-footer').innerHTML='';$('#modal-footer').hidden=true;$('#modal-body').scrollTop=0;
 if(!m.open)m.showModal();document.body.style.overflow='hidden';m.scrollTop=0;$('#modal-body').scrollTop=0;hydrate();syncDestinationViewport();if(newStep&&!restoringModal)$('#modal-title').focus({preventScroll:true});queueMicrotask(rememberUIRoute);
}
function closeModal({fromHistory=false}={}){
 selectionGeneration++;calendarRequest?.abort();calendarObserver?.disconnect();hotelRoomObserver?.disconnect();hotelRoomObserver=null;cancelDestinationLookup();
 const m=$('#modal');if(!m.open)return;
 leaveUIHistory(fromHistory);cancelVerification();modalType='';modalHistory.length=0;m.close();document.body.style.overflow=$('#filter-panel').classList.contains('open')?'hidden':'';restorePageReturn();
}
function modalBack(){
 let previous=modalHistory.pop();
 while(previous&&['andromeda-flights','anex-current'].includes(previous.type)&&retainedProviderView(previous.offer)?.type!==previous.type)previous=modalHistory.pop();
 if(!previous)return;
 restoringModal=true;showModal(previous.type,previous.title,previous.kicker,previous.body,previous.className==='wide-dialog');
 restoreModalStepSnapshot(previous);
 if(previous.type==='offer')renderRealOffer();
 if(['andromeda-flights','andromeda-verified','anex-current','anex-additional','anex-quote','provider-application','anex-application'].includes(previous.type)){
  restoreProviderView(selectedOffer);
  if(previous.type==='provider-application')openAndromedaApplicationPreview();
  if(previous.type==='anex-application')openAnexApplicationPreview();
 }
 if(previous.type==='hotel-details')restoreHotelDetailStep(previous);
 restoringModal=false;if(previous.type==='all-offers'&&offerView)renderOfferList();if(previous.type==='compare')renderCompare();if(previous.type==='favorites')renderFavorites();if(previous.type==='selected-tour')window.AnyTourPrototypeLead.bind(selectedOffer);refreshSavedTourControls();
 restoreModalStepFocus(previous);
 syncHotelSectionNavigation();rememberUIRoute();
}
$('#modal').addEventListener('cancel',e=>{e.preventDefault();closeModal();});
$('#modal').addEventListener('click',e=>{if(e.target===$('#modal')){const r=e.target.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closeModal()}});
let calendarHotels=[],calendarObservations=[],calendarRequest=null,calendarObserver=null,calendarMobile=null,calendarLoads=new Map();
let dateContext=null,datePrices=new Map(),mealDraft=[];
function budgetLabel(f){return f.max===null?(f.min?'От '+money(f.min):'Без ограничений'):f.min?`${money(f.min)} — ${money(f.max)}`:'До '+money(f.max);}
const budgetText=()=>budgetLabel(state.filters);
function createDateContext(source='form'){
 const s=source==='results'?state.search:draft,filters=structuredClone(state.filters);
 if(source==='form'&&draftDestination){filters.resorts=[...draftDestination.resorts];filters.hotelId=draftDestination.hotelId;filters.q='';}else if(s.country!==state.search.country){filters.resorts=[];filters.hotelId=0;filters.q='';}
 return {source,search:structuredClone(s),filters};
}
const dateContextLabel=s=>`Вылет: ${s.origin} · ${guestsText(s)} · ${durationText(s)}`;
function calendarScope(ctx){
 const place={country:ctx.search.country,hotelId:ctx.filters.hotelId,resorts:ctx.filters.resorts};
 return {destination:destinationLabel(place),fullDestination:destinationLabel(place,true),filters:filterChipData({filters:ctx.filters}).filter(chip=>!['hotelId','resorts'].includes(chip.key))};
}
const calendarSourceLabel=()=>data.scenario==='live'?'Ранее найденная цена':data.scenario==='snapshot'?'Снимок 23.09 · без обновления':data.scenario==='recorded'?(hotels.some(h=>h.offers?.some(o=>o.recordingKind==='demo'))?'Демонстрационная запись':'Цена из загруженной записи'):'Демонстрационная цена';
function calendarScopeHTML(ctx,scope){return `<details class="calendar-scope"><summary><span class="calendar-scope-label"><strong>${esc(scope.destination)}</strong><small>${esc(dateContextLabel(ctx.search))}</small></span><span class="calendar-scope-toggle">${scope.filters.length?'Фильтры: '+scope.filters.length:'Условия'}</span></summary><div class="calendar-scope-content"><p>${esc(scope.fullDestination)}</p>${ctx.search.ages.length?`<p>${esc(childAgesLabel(ctx.search.ages))}</p>`:''}${scope.filters.length?`<p>Цены с учётом выбранных условий:</p><ul>${scope.filters.map(chip=>`<li>${esc(chip.label)}</li>`).join('')}</ul>`:'<p>Без дополнительных фильтров</p>'}<p>${esc(calendarSourceLabel())}. Цена указана за весь тур и всех туристов; актуальность и наличие требуют проверки.</p><p>Прочерк означает, что подсказки цены нет. Он не означает отсутствие туров.</p></div></details>`;}
function renderCalendarScope(){
 const ctx=dateContext,scope=calendarScope(ctx),node=$('.calendar-context');if(!node)return;
 node.innerHTML=calendarScopeHTML(ctx,scope);
}
function mealPreviewModel(){
 const place=currentDraftDestination(),changed=searchKey(draft)!==searchKey(state.search)||place.hotelId!==state.filters.hotelId||JSON.stringify(place.resorts)!==JSON.stringify(state.filters.resorts);
 if(!state.hasSearched||changed||responseFor(state.search).phase==='error')return null;
 return {...appliedFilterModel(),filters:{...state.filters,meals:[...mealDraft]}};
}
function updateMealCounts(){
 const model=mealPreviewModel(),rows=$$('.meal-option'),counts=model?countFacetOptions(model,'meals',rows.map(row=>row.querySelector('input').value).filter(Boolean)):null;
 rows.forEach(row=>{row.querySelector('.meal-hotel-count')?.remove();if(!model)return;const value=row.querySelector('input').value,count=value?counts.get(value):countMatchingHotels({...model,filters:{...model.filters,meals:[]}});row.insertAdjacentHTML('beforeend',`<span class="meal-hotel-count" aria-label="${hotelCountText(count)}">${count}</span>`);});
}
function updateMealPicker(){
 const query=($('#meal-query')?.value||'').trim().toLocaleLowerCase('ru-RU');let visible=0;
 $$('.meal-option').forEach(row=>{const value=row.querySelector('input').value;row.hidden=!!value&&!value.toLocaleLowerCase('ru-RU').includes(query);if(value&&!row.hidden)visible++;});
 $('#meal-no-match').hidden=visible>0;
 $('#meal-selection-status').textContent=mealDraft.length?'Выбрано: '+mealDraft.length:'Любое питание';
 if($('#meal-clear-query'))$('#meal-clear-query').hidden=!query;
 const model=mealPreviewModel(),preview=$('#meal-result-preview');
 preview.classList.remove('meal-preview-empty');
 if(!model){preview.textContent='Количество отелей появится после поиска с новыми условиями.';return;}
 const count=countMatchingHotels(model),complete=responseFor(state.search).phase==='complete';
 preview.textContent=count?`${hotelCountText(count)} · по загруженной выдаче`:complete?'Нет отелей с таким питанием и остальными условиями.':'В загруженной части выдачи пока нет подходящих отелей.';
 preview.classList.toggle('meal-preview-empty',count===0);
}
function openMeals(restore=null){
 const restoredMeals=boundedHistoryStrings(restore?.meals);
 mealDraft=restoredMeals??[...state.filters.meals];
 const choices=[...new Set([...Object.keys(mealNames),...mealDraft])].sort(compareMealLabels);
 showModal('meals','Питание','УСЛОВИЯ ТУРА',`<p class="modal-intro">Можно выбрать несколько вариантов.</p>${choices.length>7?'<div class="meal-search"><label><span class="sr-only">Найти тип питания</span><input id="meal-query" type="search" placeholder="Найти тип питания" autocomplete="off"></label><button type="button" id="meal-clear-query" class="text-button" data-action="clear-meal-query" hidden>Сбросить поиск</button></div>':''}<div class="meal-options">${[['','Любое питание'],...choices.map(m=>[m,m])].map(([v,label])=>`<label class="meal-option"><input type="checkbox" data-meal-choice value="${esc(v)}" ${v?mealDraft.includes(v)?'checked':'':!mealDraft.length?'checked':''}><span><strong>${esc(label)}</strong>${mealHelp[v]?`<small>${esc(mealHelp[v])}</small>`:''}</span></label>`).join('')}</div><p id="meal-no-match" class="meal-no-match" hidden>Такого названия нет. Попробуйте другое или сбросьте поиск.</p>`);
 $('#modal').classList.add('meals-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="meal-result-summary" role="status" aria-live="polite"><strong id="meal-result-preview"></strong><span id="meal-selection-status"></span></div><button class="primary picker-apply" data-action="apply-meals">Применить</button>';
 if($('#meal-query'))$('#meal-query').value=boundedHistoryText(restore?.query,'');
 updateMealCounts();
 updateMealPicker();
 if(Number.isFinite(restore?.scroll)&&restore.scroll>=0)$('#modal-body').scrollTop=restore.scroll;
}
function parseBudgetAmount(value,empty){
 const text=String(value).trim();if(!text)return empty;
 if(!/^(?:\d+|\d{1,3}(?:[ \u00a0\u202f]\d{3})+)(?:[.,]\d{1,2})?$/.test(text))return NaN;
 return Number(text.replace(/[ \u00a0\u202f]/g,'').replace(',','.'));
}
function readBudgetFields(low,high){
 const min=parseBudgetAmount(low.value,0),max=parseBudgetAmount(high.value,null);
 const invalidMin=!Number.isFinite(min)||min<0,invalidMax=max!==null&&(!Number.isFinite(max)||max<0||!invalidMin&&min>max);
 return {min,max,invalidMin,invalidMax,valid:!invalidMin&&!invalidMax};
}
function readBudget(){return readBudgetFields($('#budget-min'),$('#budget-max'));}
function budgetErrorText(budget){return budget.valid?'':budget.invalidMin?'Укажите сумму «От» цифрами, например 100 000.':!Number.isFinite(budget.max)||budget.max<0?'Укажите сумму «До» цифрами или оставьте поле пустым.':'Сумма «До» должна быть не меньше суммы «От».';}
function updateBudgetPreview(){
 if(modalType!=='budget')return;
 const budget=readBudget(),{min,max,valid}=budget,preview=$('#budget-preview'),recovery=$('#budget-recovery'),apply=$('[data-action="apply-budget"]');
 $('#budget-min').setAttribute('aria-invalid',String(budget.invalidMin));$('#budget-max').setAttribute('aria-invalid',String(budget.invalidMax));
 $('#budget-error').textContent=budgetErrorText(budget);
 apply.disabled=!valid;recovery.hidden=true;apply.textContent='Применить бюджет';
 $$('[data-action="budget-preset"]').forEach(button=>{const selected=valid&&min===0&&(button.dataset.value===''?max===null:max===Number(button.dataset.value));button.classList.toggle('active',selected);button.setAttribute('aria-pressed',String(selected));});
 if(!valid){preview.textContent='Исправьте сумму, чтобы увидеть варианты.';return;}
 const place=currentDraftDestination(),changed=searchKey(draft)!==searchKey(state.search)||place.hotelId!==state.filters.hotelId||JSON.stringify(place.resorts)!==JSON.stringify(state.filters.resorts);
 if(!state.hasSearched||changed){preview.textContent='Количество отелей появится после поиска с новыми условиями поездки.';return;}
 const model={...appliedFilterModel(),filters:{...state.filters,min,max}},count=countMatchingHotels(model),complete=responseFor(state.search).phase==='complete';
 preview.textContent=count?`${hotelCountText(count)} в этом бюджете · по загруженной выдаче`:complete?'В этом бюджете нет отелей с выбранными условиями.':'В загруженной части выдачи пока нет отелей в этом бюджете.';
 if(count)apply.textContent=`Применить · ${hotelCountText(count)}`;
 if(!count&&complete&&max!==null){const offers=hotels.map(h=>hotelOffers(h,{...model,filters:{...model.filters,max:null},minimumOnly:true})[0]).filter(Boolean),minimum=offers.length?Math.min(...offers.map(o=>o.total)):null;
  if(minimum!==null&&minimum>max){const ceiling=Math.ceil(minimum/1000)*1000;recovery.dataset.value=ceiling;recovery.textContent=`Увеличить бюджет до ${money(ceiling)}`;recovery.hidden=false;}
 }
}
function openBudget(restore=null){
 const minText=boundedHistoryText(restore?.minText,String(state.filters.min),32),maxText=boundedHistoryText(restore?.maxText,String(state.filters.max??''),32);
 showModal('budget','Бюджет на весь тур','НА ВСЕХ ТУРИСТОВ',`<p class="modal-intro">За ${guestsText(draft)} · весь тур, а не за ночь.</p><div class="form-row"><label>От, ₽<input type="text" inputmode="decimal" class="input" id="budget-min" value="${esc(minText)}" aria-describedby="budget-error"></label><label>До, ₽<input type="text" inputmode="decimal" class="input" id="budget-max" value="${esc(maxText)}" placeholder="Без лимита" aria-describedby="budget-error"></label></div><div class="budget-presets">${[150000,200000,300000,null].map(n=>`<button class="chip" data-action="budget-preset" data-value="${n??''}" aria-pressed="false">${n===null?'Без ограничений':'До '+money(n)}</button>`).join('')}</div><p class="error-text" id="budget-error" role="alert"></p><div class="budget-feedback"><p id="budget-preview" role="status" aria-live="polite"></p><button class="text-button" id="budget-recovery" data-action="budget-adjust-max" hidden></button></div><p class="budget-price-note">Цены и наличие уточняются при выборе тура.</p>`);
 $('#modal').classList.add('budget-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<button class="primary picker-apply" data-action="apply-budget">Применить бюджет</button>';updateBudgetPreview();
 if(Number.isFinite(restore?.scroll)&&restore.scroll>=0)$('#modal-body').scrollTop=restore.scroll;
}

function prepareDatePicker(source,restore){
 dateContext=createDateContext(source);const s=dateContext.search,selectedDay=source==='results'?state.selectedDate:draftSelectedDate();
 const restoredDraft=restore?.draft&&typeof restore.draft==='object'?{from:String(restore.draft.from||''),to:String(restore.draft.to||''),phase:restore.draft.phase===1?1:0}:null;
 dateDraft=restoredDraft&&!dateRangeError(restoredDraft)?restoredDraft:{from:selectedDay||s.from,to:selectedDay||s.to,phase:0};
 const firstMonth=startDay.slice(0,7)+'-01',lastMonth=endDay.slice(0,7)+'-01',restoredMonth=String(restore?.month||'');
 datePrices=new Map();calendarLoads=new Map();calendarMonth=/^\d{4}-\d{2}-01$/.test(restoredMonth)&&restoredMonth>=firstMonth&&restoredMonth<=lastMonth?restoredMonth:dateDraft.from.slice(0,7)+'-01';
}
function openDates(source='form',restore=null){
 prepareDatePicker(source,restore);
 showModal('dates','Даты вылета','КАЛЕНДАРЬ ЦЕН · ЗА ВСЕХ ТУРИСТОВ',`<div class="date-choice-tools"><div class="calendar-context"></div><div class="calendar-price-key"><span>Весь тур · тыс. ₽</span><span><i class="legend-dot"></i>Минимум в месяце</span></div><div class="calendar-legend" role="status" hidden><span></span></div></div><div id="date-calendar"></div>`);
 renderCalendarScope();
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="date-footer"><div id="date-selection-price" class="date-selection-price" aria-live="polite"></div><p id="date-selection-hint" aria-live="polite"></p><p class="error-text" id="date-error" role="alert"></p><button class="primary picker-apply" data-action="apply-dates"></button></div>`;
 refreshCalendarPriceCache();renderDateCalendar();
 loadCalendarPrices();
 if(innerWidth<=760&&calendarMonth!==startDay.slice(0,7)+'-01'){const tools=$('.date-choice-tools');$('#modal').style.setProperty('--calendar-context-height',tools.offsetHeight+12+'px');document.querySelector(`[data-month="${calendarMonth}"]`)?.scrollIntoView({block:'start',behavior:'instant'});}
}
function refreshCalendarPriceCache(){
 datePrices.clear();const s={...dateContext.search,from:startDay,to:endDay},f=dateContext.filters;
 const add=(day,price)=>{if(day>=startDay&&day<=endDay&&Number.isFinite(price)&&price>0)datePrices.set(day,Math.min(datePrices.get(day)??Infinity,price));};
 for(const h of [...hotels,...calendarHotels])for(const o of hotelOffers(h,{search:s,filters:f,selectedDate:null,onlyFavorites:false,sort:false}))add(o.day,o.total);
 if(data.observationScopeSupported(s,f))for(const point of calendarObservations)add(point.date,point.price);
}
function calendarPrice(day){return datePrices.get(day)??null;}
function monthFrame(month){
 const date=dateObj(month),first=(date.getUTCDay()+6)%7,count=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth()+1,0)).getUTCDate(),heading=formatDate(monthFormatter,date);
 const prices=Array.from({length:count},(_,i)=>{const d=month.slice(0,8)+String(i+1).padStart(2,'0');return d>=startDay&&d<=endDay?calendarPrice(d):null}),cheapest=Math.min(...prices.filter(p=>p!==null));
 let days='<span></span>'.repeat(first);
 for(let n=1;n<=count;n++){const day=month.slice(0,8)+String(n).padStart(2,'0'),valid=day>=startDay&&day<=endDay,price=prices[n-1];days+=`<button class="month-day ${price!==null&&price===cheapest?'is-cheap':''}" data-action="day-pick" data-date="${day}" ${!valid?'disabled':''} aria-label="${dateLong(day)}${price!==null?', от '+money(price):valid?', цена пока неизвестна':''}"><span>${n}</span><small>${price!==null?shortAmount(price):valid?'—':''}</small></button>`;}
 return `<section class="calendar-month" data-month="${month}"><h3>${heading.charAt(0).toUpperCase()+heading.slice(1)}</h3><div class="month-grid">${['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].map(d=>`<span class="weekday">${d}</span>`).join('')}${days}</div></section>`;
}
function renderDateCalendar(){
 calendarMobile=innerWidth<=760;
 const months=[],first=startDay.slice(0,7)+'-01',last=endDay.slice(0,7)+'-01',next=dateObj(calendarMonth);next.setUTCMonth(next.getUTCMonth()+1);
 if(innerWidth<=760){for(let m=first;m<=last;){months.push(m);const d=dateObj(m);d.setUTCMonth(d.getUTCMonth()+1);m=iso(d);}}else{months.push(calendarMonth);if(iso(next)<=last)months.push(iso(next));}
 $('#date-calendar').innerHTML=`${innerWidth<=760?'':`<div class="calendar-navigation"><button class="icon-button" data-action="month-prev" aria-label="Предыдущий месяц" ${calendarMonth<=first?'disabled':''}>${icon('back')}</button><span>Выберите даты вылета</span><button class="icon-button" data-action="month-next" aria-label="Следующий месяц" ${calendarMonth>=last?'disabled':''}>${icon('arrow')}</button></div>`}<div class="calendar-months">${months.map(monthFrame).join('')}</div>`;updateDateSelection();
}
function dateRangeError(range){if(!range?.from||!range?.to)return 'Укажите обе даты вылета.';if(range.from<startDay||range.to>endDay)return 'Выберите даты в доступном периоде календаря.';if(range.from>range.to)return 'Конец диапазона должен быть не раньше начала.';if((dateObj(range.to)-dateObj(range.from))/86400000>21)return 'Выберите диапазон не больше 21 дня между датами.';return '';}
function updateDateSelection(){
 $$('[data-action="day-pick"]').forEach(b=>{const d=b.dataset.date,active=d===dateDraft.from||d===dateDraft.to;b.classList.toggle('active',active);b.classList.toggle('in-range',d>dateDraft.from&&d<dateDraft.to);b.setAttribute('aria-pressed',active||d>dateDraft.from&&d<dateDraft.to)});
 $('[data-action="apply-dates"]').textContent=dateDraft.from&&dateDraft.to?(data.live&&dateContext?.source==='results'?'Найти туры: ':'Выбрать ')+rangeText(dateDraft.from,dateDraft.to):'Выберите обе даты';
 $('#date-selection-hint').textContent=dateDraft.phase?'Для диапазона нажмите вторую дату.':dateDraft.from===dateDraft.to?'Один день — одно нажатие. Диапазон — два.':'Чтобы изменить даты, начните новый выбор.';
 renderDateSelectionPrice();
 const error=dateRangeError(dateDraft);
 $('[data-action="apply-dates"]').disabled=!!error;$('#date-error').textContent=error;
}
function calendarSelectionPhase(){
 if(!catalogReady)return catalogError?'error':'loading';
 if(dateRangeError(dateDraft))return 'invalid';
 const phases=new Set();for(let day=dateDraft.from;day<=dateDraft.to;day=addDays(day,1))phases.add(calendarLoads.get(day.slice(0,7)+'-01')||'idle');
 return phases.has('loading')?'loading':phases.has('error')?'error':phases.has('idle')?'idle':'complete';
}
function dateSelectionPriceModel(){
 const from=dateDraft.from,to=dateDraft.to,values=[];
 if(from&&to&&from<=to&&(dateObj(to)-dateObj(from))/86400000<=21){for(let day=from;day<=to;day=addDays(day,1)){const p=calendarPrice(day);if(Number.isFinite(p)&&p>0)values.push(p);}}
 const phase=calendarSelectionPhase();
 const note=phase==='loading'?'Загружаем подсказки цен…':phase==='error'?'Не все подсказки цен загрузились.':phase==='idle'?'Подсказка цены ещё не загружена.':phase==='invalid'?'Выберите корректные даты вылета.':'На выбранные даты нет подсказки цены.';
 return {values,phase,note};
}
function dateSelectionPriceHTML({values,phase,note}){return values.length?`<span>За весь тур · ${esc(guestsText(dateContext.search))}<small>${esc(calendarSourceLabel())}${phase==='loading'?' · загрузка продолжается':phase==='error'?' · часть цен недоступна':''}</small></span><strong>от ${money(Math.min(...values))}</strong>`:`<span>${note}<small>${phase==='complete'?'Даты можно выбрать: это не означает, что туров нет.':phase==='invalid'?'':'Даты можно выбрать, не дожидаясь цены.'}</small></span>`;}
function renderDateSelectionPrice(){
 const node=$('#date-selection-price');if(!node)return;const model=dateSelectionPriceModel();
 node.setAttribute('aria-busy',String(model.phase==='loading'));
 node.innerHTML=dateSelectionPriceHTML(model);
}
function openCalendar(){openDates('results');}
function restoredGuestDraft(value){
 const fallback={adults:draft.adults,ages:[...draft.ages]};if(!value||typeof value!=='object')return fallback;
 const adults=Number(value.adults),ages=Array.isArray(value.ages)?value.ages:[];
 if(!Number.isInteger(adults)||adults<1||adults>6||ages.length>3||ages.some(age=>age!==null&&(!Number.isInteger(age)||age<0||age>17)))return fallback;
 return {adults,ages:[...ages]};
}
function openGuests(restore=null){
 guestDraft=restoredGuestDraft(restore?.draft);showModal('guests','Кто отправится?','ТУРИСТЫ','');$('#modal').classList.add('guests-dialog');
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="guest-footer"><div class="guest-selection" role="status" aria-live="polite"><strong id="guest-count-summary"></strong><span id="guest-selection-hint"></span></div><button class="primary picker-apply" data-action="apply-guests">Применить</button></div>';renderGuests();
}
function updateGuestSelection(){
 const g=guestDraft,missing=g.ages.map((age,i)=>age===null?i+1:null).filter(Boolean);
 $('#guest-count-summary').textContent=guestsText(g);
 $('#guest-selection-hint').textContent=missing.length?'Укажите возраст '+(missing.length===1?'ребёнка '+missing[0]:'детей '+missing.join(', ')):g.ages.length?childAgesLabel(g.ages):'Цена тура рассчитывается за всех туристов';
 $('[data-action="apply-guests"]').disabled=!!missing.length;
}
function renderGuests(){
 const focused=document.activeElement?.closest('#modal-body [data-action]')?.dataset.action,g=guestDraft;
 $('#modal-body').innerHTML=`<div class="counter-row"><div><strong>Взрослые</strong><small>От 18 лет</small></div><div class="counter"><button data-action="adults-minus" aria-label="Убрать взрослого" ${g.adults<=1?'disabled':''}>−</button><output aria-label="Количество взрослых">${g.adults}</output><button data-action="adults-plus" aria-label="Добавить взрослого" ${g.adults>=6?'disabled':''}>+</button></div></div><div class="counter-row"><div><strong>Дети</strong><small>До 18 лет на дату возвращения</small></div><div class="counter"><button data-action="children-minus" aria-label="Убрать ребёнка" ${!g.ages.length?'disabled':''}>−</button><output aria-label="Количество детей">${g.ages.length}</output><button data-action="children-plus" aria-label="Добавить ребёнка" ${g.ages.length>=3?'disabled':''}>+</button></div></div>${g.ages.length?`<div class="ages">${g.ages.map((age,i)=>`<div class="guest-age-row"><label for="child-age-${i}">Ребёнок ${i+1}</label><select class="input" id="child-age-${i}" data-child-age="${i}" aria-label="Возраст ребёнка ${i+1}" aria-describedby="guest-age-help" required><option value="" ${age===null?'selected':''}>Возраст</option>${Array.from({length:18},(_,n)=>`<option value="${n}" ${age===n?'selected':''}>${n===0?'До года':childAgeText(n)}</option>`).join('')}</select><button class="icon-button" type="button" data-action="remove-child" data-index="${i}" aria-label="Убрать ребёнка ${i+1}">${icon('x')}</button></div>`).join('')}</div><p class="guest-age-help" id="guest-age-help">Укажите возраст на дату возвращения.</p>`:''}<p class="error-text" id="guest-error" role="alert"></p>`;
 updateGuestSelection();
 if(focused){let target=$(`#modal-body [data-action="${focused}"]`);if(target?.disabled){const opposite=focused.endsWith('plus')?focused.replace('plus','minus'):focused.replace('minus','plus');target=$(`#modal-body [data-action="${opposite}"]`);}target?.focus({preventScroll:true});}
 rememberUIRoute();
}
function restoredNightsDraft(value){
 const fallback={min:draft.minNights,max:draft.maxNights,phase:0};if(!value||typeof value!=='object')return fallback;
 const min=Number(value.min),max=Number(value.max);if(!Number.isInteger(min)||!Number.isInteger(max)||min<1||max>28||min>max||max-min>10)return fallback;
 return {min,max,phase:value.phase===1?1:0};
}
function openNights(restore=null){nightsDraft=restoredNightsDraft(restore?.draft);showModal('nights','На сколько ночей?','ПРОДОЛЖИТЕЛЬНОСТЬ',`<p class="modal-intro">Нажмите одно число для точной длительности или два — для диапазона.</p><div class="night-grid" aria-label="Количество ночей">${Array.from({length:28},(_,i)=>`<button data-action="night-pick" data-value="${i+1}" aria-label="${nightsText(i+1)}">${i+1}</button>`).join('')}</div><div class="nights-options">${[7,10,14,21].map(n=>`<button data-action="night-preset" data-value="${n}">${nightsText(n)}</button>`).join('')}</div>`);$('#modal').classList.add('nights-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="nights-footer"><p class="night-selection" aria-live="polite"></p><p class="error-text" id="night-error" role="alert" hidden></p><button class="primary picker-apply" data-action="apply-nights"></button></div>';renderNightSelection();}
function renderNightSelection(){$('#night-error').hidden=!nightsDraft.error;$('#night-error').textContent=nightsDraft.error||'';$$('.night-grid button').forEach(b=>{const n=+b.dataset.value,active=n===nightsDraft.min||n===nightsDraft.max;b.classList.toggle('active',active);b.classList.toggle('in-range',n>nightsDraft.min&&n<nightsDraft.max);b.setAttribute('aria-pressed',n>=nightsDraft.min&&n<=nightsDraft.max)});$$('.nights-options button').forEach(b=>b.setAttribute('aria-pressed',nightsDraft.min===+b.dataset.value&&nightsDraft.max===+b.dataset.value));const label=durationText({minNights:nightsDraft.min,maxNights:nightsDraft.max});$('.night-selection').textContent=nightsDraft.phase?'Выберите вторую границу или подтвердите '+label:'Выбрано: '+label;$('[data-action="apply-nights"]').textContent='Выбрать '+label;rememberUIRoute();}
function openGallery(id,index=0){const h=hotels.find(x=>x.id===id);if(!h?.photos.length){toast('Фотографии этого отеля пока недоступны.');return;}showModal('gallery',h.name,'ФОТОГРАФИИ ОТЕЛЯ','');gallery={id,index};renderGallery();}
function renderGallery(){const focused=document.activeElement?.closest('#modal-body button'),focusAction=focused?.dataset.action,focusValue=focused?.dataset.value;const h=hotels.find(x=>x.id===gallery.id);if(!h?.photos.length)return;$('#modal-body').innerHTML=`<div class="gallery-stage"><img id="gallery-image" src="${esc(photoUrl(h,gallery.index))}" alt="Фото ${gallery.index+1} из ${h.photos.length}" draggable="false"><button class="icon-button gallery-arrow prev" data-action="gallery-prev" aria-label="Предыдущее фото">${icon('back')}</button><button class="icon-button gallery-arrow next" data-action="gallery-next" aria-label="Следующее фото">${icon('arrow')}</button></div><div class="gallery-caption"><span>${esc(h.name)}</span><span>${gallery.index+1} / ${h.photos.length}</span></div><div class="gallery-thumbs">${h.photos.map((p,i)=>`<button data-action="gallery-index" data-value="${i}" class="${gallery.index===i?'active':''}" aria-pressed="${gallery.index===i}" aria-label="Фото ${i+1}"><img src="${esc(p)}" alt="" loading="lazy"></button>`).join('')}</div>`;if(focusAction){const target=$$('#modal-body button').find(b=>b.dataset.action===focusAction&&(focusValue===undefined||b.dataset.value===focusValue));target?.focus({preventScroll:true});}}
let flightDraft=null,andromedaQuoteDraft=null,andromedaApplicationDraft=null,anexCurrentDraft=null,anexApplicationDraft=null,selectionGeneration=0;
// Retain only this search's direct-provider outcomes for navigation; never persist them.
const providerViews=new Map();
function retainedProviderView(o){const view=providerViews.get(o?.key);return view&&view.offer.raw===o.raw&&offerFromKey(o.key)?.raw===o.raw?view:null;}
function rememberProviderView(o,type,result,error='',pending=false){
 const previous=retainedProviderView(o);
 if(o&&o.raw&&offerFromKey(o.key)?.raw===o.raw)providerViews.set(o.key,{offer:{...o,loading:false},type,result,error,pending,...(type==='anex-quote'?{choice:previous?.choice}: {}),...(type==='andromeda-flights'&&previous?.type===type&&previous.result===result?{outbound:previous.outbound,inbound:previous.inbound}:{})});
}
function rememberAndromedaFlightChoice(input){
 const view=retainedProviderView(selectedOffer),direction=input.name==='andromeda-outbound'?'0':'1';
 if(view?.type!=='andromeda-flights'||view.pending||!input.checked||!view.result.flights.some(f=>f.direction===direction&&f.flightRef===input.value))return;
 view[direction==='0'?'outbound':'inbound']=input.value;
}
function restoreProviderView(o){
 const view=retainedProviderView(o);if(!view)return false;
 selectedOffer=view.offer;
 if(view.type==='tourvisor-selection')renderRealOffer();
 if(view.type==='andromeda-flights')openAndromedaFlightChoice(view.offer,view.result);
 if(view.type==='andromeda-verified')openAndromedaVerified(view.offer,view.result);
 if(view.type==='anex-current'){
  openAnexConcreteCurrent(view.offer,view.result);
  if(view.error||view.pending){$('#anex-additional-error').textContent=view.error||'Уточняем обязательные доплаты…';const button=$('[data-action="anex-additional-prices"]');if(button)button.disabled=true;}
 }
 if(view.type==='anex-additional')openAnexAdditionalEstimate(view.offer,view.result);
 if(view.type==='anex-quote')openAnexPackageQuote(view.offer,view.result,view.error,view.pending);
 return true;
}
const flightPairFor=o=>o?.flightChoiceId==null||o.flightChoiceId===''?null:o.variants?.[Number(o.flightChoiceId)]||null;
function offerFromKey(key){
 for(let i=0,length=hotels.length;i<length;i++){
  if(!(i in hotels))continue;
  const offers=hotels[i].offers||[];
  for(let j=0,count=offers.length;j<count;j++)if(j in offers&&offers[j].key===key)return offers[j];
 }
 return null;
}
function cardEntryOfferKey(button){
 const fallback=button.dataset.key;if(button.dataset.cardEntry!=='true')return fallback;
 const id=Number(button.closest('.hotel-card')?.dataset.hotelId),hotel=hotels.find(h=>h.id===id);
 if(!hotel||selectedOffer?.hotelId!==id)return fallback;
 return hotelOffers(hotel).some(o=>o.key===selectedOffer.key)?selectedOffer.key:fallback;
}
function flightAllowanceText(o,field){
 const v=flightPairFor(o);if(!v)return typeof o.savedFlightAllowance?.[field]==='string'?o.savedFlightAllowance[field]:'Уточняется после выбора и проверки рейсов';
 return [['forward','Туда'],['backward','Обратно']].map(([key,label])=>label+': '+window.AnyTourFlightPickerV18.directionAllowance(v[key],field)).join(' · ');
}
function fuelAmount(o){const pair=flightPairFor(o),raw=pair||o.savedFuelAmount===undefined?data.fuel(o.tour,pair):o.savedFuelAmount;if(!['number','string'].includes(typeof raw)||String(raw).trim()==='')return null;const n=Number(raw);return Number.isFinite(n)&&n>=0?n:null;}
function fuelIncludedInPrice(o){
 if(o.provider!=='tourvisor'||o.cached||o.quoteError||o.pricePending)return false;
 // Tourvisor supplies the total. Apply its contract only to fuel reported by
 // the same priced tour/flight pair, never to an inherited fee or another API.
 const priced=flightPairFor(o)||o.tour,price=data.amount(priced?.price),fuel=data.fuel(priced);
 const currency=priced?.price?.currency||priced?.currency||'RUB',fuelCurrency=priced?.fuelCharge?.currency||currency;
 return currency==='RUB'&&fuelCurrency===currency&&price>0&&price===data.amount(o.total)&&fuel!==null&&fuel<=price;
}
function fuelText(o){const n=fuelAmount(o);return n===null?'Сбор уточняется':n===0?'Без доплаты по сбору':money(n)+(fuelIncludedInPrice(o)?' · включён в цену':' · включение в цену уточняется');}
function selectedPriceStatus(o){
 if(o.provider==='fixture')return 'Демонстрационная цена';
 if(o.provider==='recorded')return 'Цена из записи';
 if(o.quoteError)return 'Цена из выдачи · не подтверждена';
 if(!o.tour)return 'Цена из выдачи · требует проверки';
 if(!flightPairFor(o))return 'Цена предложения · перелёт уточняется';
 const fuel=fuelAmount(o);
 return fuel===0?'Цена с выбранными рейсами · без топливной доплаты':fuel===null?'Цена с выбранными рейсами · сбор уточняется':fuelIncludedInPrice(o)?'Цена с выбранными рейсами · сбор включён':'Цена с выбранными рейсами · включение сбора уточняется';
}
function fuelDisclosureHTML(o){
 const n=fuelAmount(o);
 return `<div class="tour-fuel-disclosure"><span>Топливный сбор</span><strong>${esc(fuelText(o))}</strong><p>${n===null?'Размер сбора и его включение в цену нужно проверить.':n===0?'По данным предложения. Другие возможные доплаты требуют проверки.':fuelIncludedInPrice(o)?'Уже учтён в показанной стоимости тура. Дополнительно прибавлять его не нужно.':'Не указано, входит ли сбор в цену предложения или оплачивается дополнительно.'}</p></div>`;
}
function withFlightPair(o,id){const variant=o.variants?.[Number(id)],price=data.variantPrice(o.tour,variant);return {...o,flightChoiceId:String(id),total:price,pricePending:!price};}
function legHTML(segments,label){
 if(!segments?.length)return `<div class="flight-leg"><strong>${label}</strong><p class="tour-missing">Расписание пока не предоставлено.</p></div>`;
 return segments.map((f,i)=>{const d=f.departure||{},a=f.arrival||{},placeholder=/000$/.test(String(f.number||'').replace(/\s/g,''))&&d.time==='00:00'&&a.time==='00:00';const bag=window.AnyTourFlightPickerV18.allowanceValue(f,'baggage'),carryOn=window.AnyTourFlightPickerV18.allowanceValue(f,'carryOn');return `<div class="flight-leg"><div class="flight-leg-label"><strong>${i?'Пересадка · ':''}${label}</strong><span>${esc(data.text(f.company))} ${esc(placeholder?'Рейс уточняется':f.number||'')}</span></div><div class="flight-timeline"><div><strong>${esc(placeholder?'—':d.time||'—')}</strong><span>${esc(data.text(d.port))}</span><small>${esc(flightDateText(d.date))}</small></div><div class="flight-duration"><i>${icon('plane')}</i><span>${esc(f.plane||'')}</span></div><div><strong>${esc(placeholder?'—':a.time||'—')}</strong><span>${esc(data.text(a.port))}</span><small>${esc(flightDateText(a.date))}</small></div></div><div class="flight-included"><span>${icon('suitcase')} Багаж: ${esc(bag)}</span><span>Ручная кладь: ${esc(carryOn)}</span></div></div>`;}).join('');
}
function flightSummaryHTML(o){
 const v=flightPairFor(o),canChoose=o.variants?.length;
 const action=canChoose?`<button class="secondary" data-action="choose-flight">${v?'Изменить рейсы':'Выбрать рейсы'} ${icon('arrow')}</button>`:o.flightsLoading||o.loading?'':o.tour?'<button class="secondary" data-action="retry-flights">Уточнить рейсы</button>':`<button class="secondary" data-action="start-tour-flights" data-key="${esc(o.key)}">Уточнить рейсы ${icon('arrow')}</button>`;
 const content=o.flightsLoading?'<p role="status">Загружаем варианты рейсов…</p>':v?`${window.AnyTourFlightPickerV18.pairSummary(v,{esc,text:data.text})}<details class="tour-flight-details"><summary>Детали рейсов</summary><div>${legHTML(v.forward,'Туда')+legHTML(v.backward,'Обратно')}</div></details>`:o.flightsLoaded&&!o.variants?.length?`<p class="tour-missing" role="status">${esc(o.flightsError||'Поставщик не передал варианты рейсов. Можно повторить проверку или оставить заявку — рейс уточнит менеджер.')}</p>`:o.savedFlightText&&o.savedFlightText!=='Рейс пока не выбран'?'<p class="saved-flight-notice">Сохранённый перелёт · расписание требует проверки</p>'+savedFlightSummaryHTML(o):`<p class="tour-missing">${esc(o.flightsError||'Рейс можно уточнить сейчас или оставить менеджеру.')}</p>`;
 return `<section class="tour-section flight-summary"><div class="tour-section-heading"><h3>${icon('plane')} Перелёт</h3>${action}</div>${content}</section>`;
}
// Only the current, still-open selection may consume an asynchronous response.
function isCurrentOfferRequest(run,key){
 return run===selectionGeneration&&$('#modal').open&&modalType==='offer'&&selectedOffer?.key===key;
}
async function quoteSelectedOffer(initial,run,{chooseFlight=false}={}){
 try{
  const tour=await data.quote(initial);
  if(!isCurrentOfferRequest(run,initial.key))return;
  const flightState=chooseFlight?{flightsLoading:true}:{flightsLoading:false,flightsError:'',variants:[],flightChoiceId:null};
  selectedOffer={...initial,tour,quoteListingTotal:initial.total,
   total:data.amount(tour.price)||initial.total,room:data.text(tour.roomType)||initial.room,
   meal:data.meal(tour.meal)||initial.meal,loading:false,...flightState};
  renderRealOffer();
  if(chooseFlight){
   await loadRealFlights(run);
   if(run===selectionGeneration&&selectedOffer?.variants?.length)openFlightPicker();
  }else openLeadPreview(selectedOffer);
 }catch(error){
  if(isCurrentOfferRequest(run,initial.key)){
   selectedOffer={...initial,quoteError:error.message,quoteErrorCode:String(error?.code||''),loading:false};
   renderRealOffer();
  }
 }
}
async function openOffer(key,restored=null,chooseFlight=false){
 const initial=restored||offerFromKey(key);if(!initial)return;
 andromedaApplicationDraft=null;const run=++selectionGeneration;selectedOffer={...initial};if(restoreProviderView(initial)){if(chooseFlight){if(selectedOffer?.variants?.length)openFlightPicker();else if(modalType==='offer'&&selectedOffer?.tour)await loadRealFlights(run,{chooseFlight:true});}return;}renderRealOffer();
 if(needsRefresh(initial)){
  if(initial.provider==='andromeda'&&data.hasAndromedaQuoteAttempt?.(initial))await refreshHotel(initial.hotelId);
  return;
 }
 if(!chooseFlight)return;
 selectedOffer.loading=true;renderRealOffer();
 await quoteSelectedOffer(initial,run,{chooseFlight:true});
}
async function loadRealFlights(run=selectionGeneration,{chooseFlight=false}={}){
 const o=selectedOffer;if(!o?.tour)return;
 selectedOffer.flightsLoading=true;renderRealOffer();
 try{
  const variants=await data.flights(o.tour);
  if(!isCurrentOfferRequest(run,o.key))return;
  const index=Math.max(0,variants.findIndex(v=>v.isDefault));
  selectedOffer={...selectedOffer,variants,flightsLoaded:true,flightsLoading:false,flightsError:''};
  if(variants.length)selectedOffer=withFlightPair(selectedOffer,String(index));
  renderRealOffer();
  if(chooseFlight&&variants.length)openFlightPicker();
 }catch(error){
  if(isCurrentOfferRequest(run,o.key)){
   selectedOffer={...selectedOffer,flightsLoading:false,flightsError:'Не удалось загрузить рейсы. Попробуйте ещё раз.'};
   renderRealOffer();
  }
 }
}
function leadReadyOffer(o){
 const quoted=data.amount(o?.tour?.price);if(!o?.tour||!quoted)return null;
 const pair=flightPairFor(o);
 if(pair&&!o.pricePending&&!o.flightsLoading&&!o.flightsError)return o;
 return {...o,total:quoted,pricePending:false,flightChoiceId:null,variants:[],flightsLoading:false,flightsError:'',savedFlightText:'Рейс уточнит менеджер',savedFlightLegs:[]};
}
async function openLeadWithQuote(key){
 const initial=selectedOffer?.key===key?selectedOffer:offerFromKey(key);if(!initial||needsRefresh(initial))return;
 if(leadReadyOffer(initial)){openLeadPreview(initial);return;}
 const run=++selectionGeneration;selectedOffer={...initial,loading:true,quoteError:''};renderRealOffer();
 await quoteSelectedOffer(initial,run);
}
function quotePriceChangeHTML(o){
 const before=Number(o?.quoteListingTotal),after=o?.pricePending?null:data.amount(o?.total);
 if(o?.loading||o?.quoteError||!Number.isFinite(before)||before<=0||!after||before===after)return '';
 return `<p class="quote-price-change" role="status"><strong>Цена изменилась после проверки</strong><span>Было ${money(before)} → стало ${money(after)} за всех туристов.</span></p>`;
}
function chosenStayHTML(o,editable=false){
 const placement=data.text(o.tour?.placement)||o.placement;
 const alternatives=editable&&hotelOffers(selectedTourHotel(o)).length>1;
 return `<section class="chosen-stay" aria-label="Выбранные условия тура"><p class="chosen-trip"><strong>${rangeText(o.day,o.returnDay)} · ${nightsText(o.nights)}</strong><span>${guestsText(o)}${o.ages?.length?' · '+esc(childAgesLabel(o.ages,true)):''}</span></p><dl class="saved-stay-summary"><div><dt>Номер</dt><dd>${esc(o.room)}</dd></div><div><dt>Питание</dt><dd>${esc(mealLabel(o))}</dd></div>${placement?`<div><dt>Размещение</dt><dd>${esc(placement)}</dd></div>`:''}<div><dt>Оператор</dt><dd>${esc(o.operator)}</dd></div></dl>${alternatives?'<button class="text-button change-room" data-action="change-room">Другие номера и питание '+icon('arrow')+'</button>':''}</section>`;
}
function offerSelectionHint(o,terminalQuoteError){
 if(o.loading)return 'Получаем цену и условия тура…';
 if(o.flightsLoading)return 'Загружаем варианты перелёта…';
 if(terminalQuoteError)return 'Выберите другой тур в результатах.';
 if(o.quoteError)return 'Повторите проверку предложения, чтобы продолжить.';
 if(o.pricePending)return 'Цена этого рейса не подтверждена. Можно выбрать другой или оставить рейс менеджеру.';
 if(o.flightsError)return 'Рейсы не загрузились — заявку можно оставить без них.';
 if(!o.tour)return 'Перед заявкой получим условия предложения. Рейс можно оставить менеджеру.';
 if(!o.variants?.length)return 'Рейсы не указаны — их уточнит менеджер.';
 return '';
}
function offerPrimaryActionHTML(o,h,unavailable,terminalQuoteError){
 if(terminalQuoteError)return o.quoteErrorTerminal
  ?`<button class="primary" data-action="all-offers" data-id="${h.id}">Выбрать другой тур</button>`
  :'<button class="primary" data-action="close-modal">К результатам</button>';
 if(unavailable){
  if(!data.live)return '<button class="primary" data-action="close-modal">К результатам</button>';
  const label=o.loading?'Проверяем предложение…':o.quoteError?'Повторить проверку':o.raw?.anexKind==='group_minimum'?'Показать конкретные туры':refreshOfferActionLabel(o);
  return `<button class="primary" data-action="refresh-hotel" data-id="${h.id}" ${o.loading?'disabled':''}>${label}</button>`;
 }
 if(o.quoteError)return `<button class="primary" data-action="start-lead" data-key="${esc(o.key)}">Повторить проверку</button>`;
 if(o.loading)return '<button class="primary" disabled>Проверяем предложение…</button>';
 if(o.flightsLoading)return '<button class="primary" disabled>Загружаем рейсы…</button>';
 if(o.tour)return `<button class="primary" data-action="confirm-tour">${flightPairFor(o)&&!o.pricePending?'К заявке':'Оставить заявку · рейс уточнит менеджер'} ${icon('arrow')}</button>`;
 return `<button class="primary" data-action="start-lead" data-key="${esc(o.key)}">К заявке ${icon('arrow')}</button>`;
}
function offerDetailBodyHTML(o,h,unavailable,terminalQuoteError,selectionHint){
 return `
 ${unavailable?'':selectionStepsHTML(!o.tour||o.loading||o.quoteError?0:1)}${quotePriceChangeHTML(o)}<div class="tour-hero">${h.photos?.length?`<img src="${esc(photoUrl(h))}" alt="Фото ${esc(h.name)}">`:`<div class="tour-photo-missing">${icon('image')}<span>Нет фото</span></div>`}<div>${hotelStarsHTML(h)}<h3>${esc(h.name)}</h3><p>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</p></div></div>

 ${unavailable&&!terminalQuoteError?(data.live?`<p class="saved-tour-notice">${esc(refreshOfferNotice(o))}</p>`:window.AnyTourPrototypeLead.unavailableMarkup(o)):''}
 ${o.quoteError?`<p class="error-text" role="alert">${esc(o.quoteError)}</p>`:''}
 ${o.pricePending?'<p class="error-text" role="status">Цена выбранного перелёта пока не подтверждена. Можно выбрать другой вариант или оставить рейс менеджеру.</p>':''}

 <div class="tour-layout"><div class="tour-main-details">${chosenStayHTML(o,true)}${unavailable||terminalQuoteError?'':flightSummaryHTML(o)}</div>
 <aside class="tour-price-details" aria-label="Состав и стоимость тура"><div class="price-breakdown"><h3>Цена и условия</h3><p class="price-party">За ${guestsText(o)} · ${nightsText(o.nights)}</p><div class="price-line total"><span>${o.quoteError?'Цена из выдачи':flightPairFor(o)?'С выбранным перелётом':'Цена предложения'}</span><strong id="detail-total">${o.pricePending?'Уточняется':money(o.total)}</strong></div>${!unavailable?`<p class="price-assurance">${icon('info')} ${o.quoteError?'Эта сумма не подтверждена после проверки.':o.provider==='fixture'?priceNote(o):o.loading?'Получаем цену предложения…':unavailable?priceNote(o):'Условия цены и наличие подтверждаются перед оформлением'}</p>`:''}${!unavailable&&selectionHint?`<p class="tour-selection-hint" role="status">${selectionHint}</p>`:''}${fuelDisclosureHTML(o)}</div></aside></div>`;
}
function offerDetailFooterHTML(o,footerAction,footerStatus){
 return `<div class="footer-total"><span>${o.quoteError?'Цена из выдачи за всех':'За всех туристов'}</span><strong>${o.pricePending?'Цена уточняется':money(o.total)}</strong><small class="footer-price-status">${footerStatus}</small></div>${footerAction}`;
}
function renderRealOffer(){
 const o=selectedOffer,h=selectedTourHotel(o);if(!o||!h)return;
 if(o.provider==='tourvisor'&&data.amount(o.tour?.price)>0&&!needsRefresh(o)&&!o.loading&&!o.quoteError&&!o.flightsLoading)rememberProviderView(o,'tourvisor-selection');
 const unavailable=needsRefresh(o);
 const terminalQuoteError=o.quoteErrorTerminal===true||['offer_unavailable','offer_expired'].includes(o.quoteErrorCode);
 const selectionHint=offerSelectionHint(o,terminalQuoteError);
 showModal('offer','Ваш тур в деталях',o.loading?'ПРОВЕРЯЕМ ПРЕДЛОЖЕНИЕ':'ПРОВЕРЬТЕ УСЛОВИЯ',offerDetailBodyHTML(o,h,unavailable,terminalQuoteError,selectionHint),true);
 $('#modal').classList.add('tour-dialog');
 const footerAction=offerPrimaryActionHTML(o,h,unavailable,terminalQuoteError);
 const footerStatus=selectedPriceStatus(o);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=offerDetailFooterHTML(o,footerAction,footerStatus);
}
function openFlightPicker(){
 if(!selectedOffer?.variants?.length)return;flightDraft={base:selectedOffer,id:selectedOffer.flightChoiceId};const o=selectedOffer;
 showModal('flights','Выберите перелёт','ТУДА И ОБРАТНО',`${selectionStepsHTML(1)}<div class="flight-picker-context"><strong>${esc(hotels.find(h=>h.id===o.hotelId).name)}</strong><span>${rangeText(o.day,o.returnDay)} · ${nightsText(o.nights)} · ${guestsText(o)}</span></div><p class="flight-picker-note">${o.provider==='fixture'?'Демо · ':''}Время местное · цены за весь тур</p>${window.AnyTourFlightPickerV18.render(o,flightDraft.id,{esc,money,text:data.text,price:data.variantPrice,legHTML,fuelText})}`,true);
 window.AnyTourFlightPickerV18.bind($('#modal-body'),money);
 $('#modal').classList.add('flight-picker-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="flight-selection-total" aria-live="polite"><span>Весь тур за всех</span><strong id="flight-total"></strong></div><button class="primary" data-action="apply-flight">Выбрать рейсы</button><div class="flight-selection-caption" aria-live="polite"><span id="flight-selected-summary"></span><span id="flight-price-change"></span></div>';updateFlightPreview();
 $('#modal-title').tabIndex=-1;$('#modal-title').focus({preventScroll:true});$('#modal-body').scrollTop=0;
}
function updateFlightPreview(){if(!flightDraft||modalType!=='flights')return;const o=withFlightPair(flightDraft.base,flightDraft.id),before=flightDraft.base.quoteListingTotal||flightDraft.base.total,delta=o.total-before;$('#flight-price-change').hidden=!o.pricePending&&delta===0;$('#flight-selected-summary').textContent=window.AnyTourFlightPickerV18.selectionSummary(flightDraft.base,flightDraft.id);$('#flight-total').textContent=o.pricePending?'Цена уточняется':money(o.total);$('#flight-price-change').textContent=o.pricePending?'Цена этого перелёта пока не подтверждена. Выберите другой вариант.':(delta===0?'Цена тура не изменится':`${delta>0?'Дороже':'Дешевле'} на ${money(Math.abs(delta))} · было ${money(before)}`);$('[data-action="apply-flight"]').disabled=o.pricePending;}

function applyFlightPair(){if(!flightDraft)return;const applied=withFlightPair(flightDraft.base,flightDraft.id);if(applied.pricePending){updateFlightPreview();return;}flightDraft=null;modalBack();selectedOffer=applied;renderRealOffer();toast('Перелёт выбран');}
function flightConnectionText(segments){return (segments||[]).slice(0,-1).map((segment,i)=>[...new Set([data.text(segment.arrival?.port),data.text(segments[i+1]?.departure?.port)].filter(Boolean))].join(' / ')||'Аэропорт уточняется').join('; ');}
function savedFlightTextPlain(o){
 if(o?.savedFlightText)return String(o.savedFlightText);const v=flightPairFor(o);if(!v)return 'Рейс пока не выбран';
 return [['forward','Туда'],['backward','Обратно']].map(([key,label])=>{
  const segments=v[key]||[];if(!segments.length)return label+': расписание уточняется';
  const placeholder=segments.some(f=>/000$/.test(String(f.number||'').replace(/\s/g,''))&&f.departure?.time==='00:00'&&f.arrival?.time==='00:00');
  const point=p=>[p?.date,placeholder?'время уточняется':p?.time,data.text(p?.port)].filter(Boolean).join(' ');
  const route=point(segments[0].departure)+' → '+point(segments.at(-1).arrival);
  const carriers=[...new Set(segments.map(f=>data.text(f.company)).filter(Boolean))].join(' / ');
  const numbers=placeholder?'номер рейса уточняется':segments.map(f=>f.number).filter(Boolean).join(' / ');
  return label+': '+[route,carriers,numbers,segments.length>1?'Пересадка: '+flightConnectionText(segments):'без пересадок'].filter(Boolean).join(' · ');
 }).join('\n');
}
function savedFlightLegs(o){
 const pair=flightPairFor(o);
 const legs=pair?['forward','backward'].map(key=>{
  const segments=pair[key]||[],first=segments[0]?.departure,last=segments.at(-1)?.arrival;
  const placeholder=segments.some(f=>/000$/.test(String(f.number||'').replace(/\s/g,''))&&f.departure?.time==='00:00'&&f.arrival?.time==='00:00');
  return {departureDate:first?.date||'',arrivalDate:last?.date||'',times:!segments.length?'Расписание уточняется':placeholder?'Время уточняется':(first?.time||'—')+' → '+(last?.time||'—'),route:segments.length?(data.text(first?.port)||'Аэропорт уточняется')+' → '+(data.text(last?.port)||'Аэропорт уточняется'):'',carrier:[...new Set(segments.map(f=>data.text(f.company)).filter(Boolean))].join(' / '),numbers:placeholder?'Номер рейса уточняется':segments.map(f=>f.number).filter(Boolean).join(' / '),connections:flightConnectionText(segments),baggage:window.AnyTourFlightPickerV18.directionAllowance(segments,'baggage'),carryOn:window.AnyTourFlightPickerV18.directionAllowance(segments,'carryOn'),stops:!segments.length?'':segments.length===1?'Без пересадок':segments.length===2?'1 пересадка':(segments.length-1)+' пересадки'};
 }):o?.savedFlightLegs;
 if(!Array.isArray(legs))return [];
 return legs.slice(0,2).filter(leg=>leg&&typeof leg==='object'&&typeof leg.times==='string').map(leg=>Object.fromEntries(['departureDate','arrivalDate','times','route','carrier','numbers','stops','connections','baggage','carryOn'].map(key=>[key,String(leg[key]||'').slice(0,500)])));
}
function savedFlightSummaryHTML(o){
 const legs=savedFlightLegs(o);if(!legs.length)return `<p class="saved-flight-fallback">${esc(savedFlightTextPlain(o))}</p>`;
 const date=value=>/^\d{4}-\d{2}-\d{2}$/.test(value)?dateText(value):value;
 return `<div class="saved-flight-summary" aria-label="Выбранный перелёт">${legs.map((leg,i)=>`<section class="saved-flight-leg"><header><strong>${i?'Обратно':'Туда'}</strong><span>${esc(date(leg.departureDate))}${leg.arrivalDate&&leg.arrivalDate!==leg.departureDate?' → '+esc(date(leg.arrivalDate)):''}</span></header><strong class="saved-flight-times">${esc(leg.times)}</strong><p>${esc(leg.route)}</p><small>${esc([leg.carrier,leg.numbers,leg.stops].filter(Boolean).join(' · '))}</small>${leg.connections?`<p class="saved-flight-connection">Пересадка: ${esc(leg.connections)}</p>`:''}<dl class="saved-flight-allowances"><div><dt>Багаж</dt><dd>${esc(leg.baggage||'уточняется')}</dd></div><div><dt>Ручная кладь</dt><dd>${esc(leg.carryOn||'уточняется')}</dd></div></dl></section>`).join('')}</div>`;
}
function finalFlightDetailsHTML(o){
 const legs=savedFlightLegs(o);if(!legs.length)return savedFlightSummaryHTML(o);
 const summary=legs.map((leg,i)=>`${i?'Обратно':'Туда'} ${leg.times}`).join(' · ');
 return `<details class="summary-flight-details" ${innerWidth>760?'open':''}><summary><span>Рейсы и багаж</span><small>${esc(summary)}</small></summary>${savedFlightSummaryHTML(o)}</details>`;
}
function andromedaFlightRoute(f){
 const point=p=>[data.text(p?.town),data.text(p?.port)].filter(Boolean).join(' · ')||'Аэропорт уточняется';
 const day=data.date(f?.datebeg);return `<div class="flight-leg"><div class="flight-leg-label"><strong>${f.direction==='0'?'Туда':'Обратно'}</strong><span>${esc(f.name||'Рейс уточняется')}</span></div><div class="flight-timeline"><div><strong>${day?dateText(day):'—'}</strong><span>${esc(point(f.departure))}</span></div><div class="flight-duration"><i>${icon('plane')}</i><span>${esc(f.class||'')}</span></div><div><strong>${day?dateText(day):'—'}</strong><span>${esc(point(f.arrival))}</span></div></div></div>`;
}
function andromedaQuoteCurrent(quote){return Number.isSafeInteger(quote?.expiresAt)&&quote.expiresAt*1000>Date.now();}
function showAndromedaExpired(o){
 providerViews.delete(o?.key);andromedaQuoteDraft=null;andromedaApplicationDraft=null;
 selectedOffer={...o,loading:false,quoteError:'Срок подтверждения тура истёк. Выполните новый поиск.',quoteErrorCode:'offer_expired',quoteErrorTerminal:true};
 renderRealOffer();
}
function openAndromedaFlightChoice(o,quote){
 if(!andromedaQuoteCurrent(quote)){showAndromedaExpired(o);return;}
 const h=selectedTourHotel(o),outbound=quote.flights.filter(f=>f.direction==='0'),inbound=quote.flights.filter(f=>f.direction==='1');
 if(!h||!outbound.length||!inbound.length){selectedOffer={...o,loading:false,quoteError:'Andromeda не вернул полный выбор перелёта.'};renderRealOffer();return;}
 rememberProviderView(o,'andromeda-flights',quote,'',retainedProviderView(o)?.pending);andromedaQuoteDraft={offer:o,quote};
 showModal('andromeda-flights','Выберите перелёт','ANDROMEDA · ПРОВЕРКА ТУРА',`<div class="flight-picker-context"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span></div><p class="flight-picker-note">Выберите один рейс туда и один обратно. Цена будет подтверждена Andromeda после выбора.</p><fieldset class="flight-options"><legend>Туда</legend>${outbound.map((f,i)=>`<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="andromeda-outbound" value="${esc(f.flightRef)}" ${i===0?'checked':''}><span><strong>${esc(f.name||'Рейс '+(i+1))}</strong><small>${esc([data.text(f.departure?.port),data.text(f.arrival?.port)].filter(Boolean).join(' → '))}</small></span></div></label>`).join('')}</fieldset><fieldset class="flight-options"><legend>Обратно</legend>${inbound.map((f,i)=>`<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="andromeda-return" value="${esc(f.flightRef)}" ${i===0?'checked':''}><span><strong>${esc(f.name||'Рейс '+(i+1))}</strong><small>${esc([data.text(f.departure?.port),data.text(f.arrival?.port)].filter(Boolean).join(' → '))}</small></span></div></label>`).join('')}</fieldset><p class="error-text" id="andromeda-quote-error" role="alert"></p>`,true);
 $('#modal').classList.add('flight-picker-dialog');$('#modal-footer').hidden=false;
 $('#modal-footer').innerHTML='<button class="secondary" data-action="modal-back">Отмена</button><button class="primary" data-action="apply-andromeda-flights">Проверить выбранные рейсы</button>';
 const view=retainedProviderView(o);
 for(const [name,value] of [['andromeda-outbound',view.outbound],['andromeda-return',view.inbound]]){
  const inputs=$$('[name="'+name+'"]'),chosen=inputs.find(input=>input.value===value);
  if(chosen)chosen.checked=true;
  inputs.forEach(input=>input.disabled=!!view.pending);
 }
 if(view.pending)$('[data-action="apply-andromeda-flights"]').disabled=true;
}
function andromedaApplicationReceipt(o,quote,h){
 const price=Number(quote?.finalPrice?.amount),offerRef=String(o?.raw?.offerRef||o?.raw?.offer_context?.offer_ref||'');
 if(!o||!h||!andromedaQuoteCurrent(quote)||quote?.state!=='quote_verified'||quote?.finalPriceVerified!==true||quote?.flightSelectionRequired!==false
   ||quote?.finalPrice?.currency!=='RUB'||!Number.isFinite(price)||price<=0||!(/^offer_[a-f0-9]{64}$/).test(offerRef)
   ||!Array.isArray(quote.flights))return null;
 return Object.freeze({provider:'andromeda',offerRef,priceKind:'verified',finalPriceVerified:true,expiresAt:quote.expiresAt,departure:String(o.origin||state.search.origin||''),hotel:String(h.name||''),country:String(countryNames[h.country]||''),
  resort:String(h.resort||''),day:o.day,nights:o.nights,adults:o.adults,ages:Object.freeze([...(o.ages||[])]),
  room:String(o.room||''),meal:String(mealLabel(o)||''),operator:String(o.operator||''),price,currency:'RUB',
  flights:Object.freeze(quote.flights.map(f=>Object.freeze({direction:String(f.direction||''),name:String(f.name||''),
    datebeg:String(f.datebeg||''),dateend:String(f.dateend||''),class:String(f.class||''),
    departure:f.departure?structuredClone(f.departure):null,arrival:f.arrival?structuredClone(f.arrival):null})))});
}
function openAndromedaVerified(o,quote){
 if(!andromedaQuoteCurrent(quote)){showAndromedaExpired(o);return;}
 const h=selectedTourHotel(o),receipt=andromedaApplicationReceipt(o,quote,h),price=Number(quote.finalPrice?.amount);
 if(!receipt){selectedOffer={...o,loading:false,quoteError:'Andromeda не подтвердил итоговую цену.'};renderRealOffer();return;}
 rememberProviderView(o,'andromeda-verified',quote);andromedaQuoteDraft=null;andromedaApplicationDraft=receipt;selectedOffer={...o,loading:false};
 showModal('andromeda-verified','Тур подтверждён','ANDROMEDA · АКТУАЛЬНЫЕ УСЛОВИЯ',`<div class="verification-tour"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span><span>${esc(o.room)} · ${esc(mealLabel(o))}</span><strong>${money(price)}</strong></div>${quote.flights.length?`<section class="tour-section"><h3>${icon('plane')} Подтверждённые рейсы</h3>${quote.flights.map(andromedaFlightRoute).join('')}</section>`:''}<p class="modal-intro">Цена подтверждена поставщиком для выбранного предложения. В preview можно пройти до проверки заявки; реальная отправка здесь отключена.</p>`,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button><button class="primary" data-action="andromeda-application-preview">К заявке ${icon('arrow')}</button>`;
}
function openAndromedaApplicationPreview(){
 const receipt=andromedaApplicationDraft;if(!receipt||modalType!=='andromeda-verified')return;
 if(!andromedaQuoteCurrent(receipt)){showAndromedaExpired(selectedOffer);return;}
 const flights=receipt.flights.length?receipt.flights.map(f=>andromedaFlightRoute(f)).join(''):'<p class="tour-missing">Рейсы не указаны поставщиком в подтверждённом ответе.</p>';
 showModal('provider-application','Заявка на тур','ANDROMEDA · ПРОВЕРКА ЗАЯВКИ',`
  <div class="verification-tour"><strong>${esc(receipt.hotel)}</strong><span>${dateText(receipt.day)} · ${nightsText(receipt.nights)} · ${receipt.adults} взр.${receipt.ages.length?' · дети '+receipt.ages.join(', '):''}</span><span>${esc(receipt.room)} · ${esc(receipt.meal)}</span><strong>${money(receipt.price)}</strong></div>
  <section class="tour-section"><h3>${icon('plane')} Перелёт из подтверждённого ответа</h3>${flights}</section>
  ${window.AnyTourPrototypeLead.markup()}`,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="modal-back">К туру</button>${window.AnyTourPrototypeLead.action()}`;
 window.AnyTourPrototypeLead.bindProviderApplication(receipt);
}
async function applyAndromedaFlightChoice(){
 const draft=andromedaQuoteDraft;if(!draft||modalType!=='andromeda-flights')return;
 const outbound=$('input[name="andromeda-outbound"]:checked')?.value,back=$('input[name="andromeda-return"]:checked')?.value;
 if(!outbound||!back)return;
 selectionGeneration++;const button=$('[data-action="apply-andromeda-flights"]');button.disabled=true;
 $('#andromeda-quote-error').textContent='';
 rememberProviderView(draft.offer,'andromeda-flights',draft.quote,'',true);
 $$('[name="andromeda-outbound"],[name="andromeda-return"]').forEach(input=>input.disabled=true);
 try{
  const quote=await data.verifyAndromeda(draft.offer,{provider:'andromeda',outbound_ref:outbound,return_ref:back});
  if(quote.state!=='quote_verified')throw new Error('Andromeda не подтвердил выбранный перелёт.');
  rememberProviderView(draft.offer,'andromeda-verified',quote);
  if(!$('#modal').open||modalType!=='andromeda-flights'||selectedOffer?.raw!==draft.offer.raw)return;
  openAndromedaVerified(draft.offer,quote);
 }catch(error){
  if(retainedProviderView(draft.offer)){if(error.retryable===false)providerViews.delete(draft.offer.key);else rememberProviderView(draft.offer,'andromeda-flights',draft.quote);}
  if($('#modal').open&&modalType==='andromeda-flights'&&selectedOffer?.raw===draft.offer.raw){
   if(error.retryable===false){
    andromedaQuoteDraft=null;selectedOffer={...draft.offer,loading:false,quoteError:error.message,quoteErrorCode:error.code,quoteErrorTerminal:true};renderRealOffer();
   }else{$('#andromeda-quote-error').textContent=error.message;button.disabled=false;$$('[name="andromeda-outbound"],[name="andromeda-return"]').forEach(input=>input.disabled=false);}
  }
 }
}
function anexApplicationReceipt(o,result,h){
 const raw=o?.raw,price=Number(result?.calculatedTotal?.amount);
 if(!o||!h||o.provider!=='anex'||raw?.anexKind!=='concrete'||raw?.anexSessionCurrent!==true
   ||!(/^anex_online:[a-f0-9]{64}$/).test(String(raw.offerRef||''))||!(/^[a-f0-9]{32}$/).test(String(raw.searchRef||''))
   ||!Number.isInteger(raw.anexGeneration)||raw.anexGeneration<1||!Number.isSafeInteger(Number(raw.anexLocalHotelId))
   ||Number(raw.anexLocalHotelId)<1||result?.state!=='additional_prices'||result?.finalPriceVerified!==false
   ||result?.arithmeticApplied!==true||result?.calculatedTotal?.currency!=='RUB'||!Number.isFinite(price)||price<=0)return null;
 return Object.freeze({provider:'anex',offerRef:String(raw.offerRef),searchRef:String(raw.searchRef),generation:raw.anexGeneration,
  localHotelId:Number(raw.anexLocalHotelId),priceKind:'estimate',finalPriceVerified:false,hotel:String(h.name||''),
  country:String(countryNames[h.country]||''),resort:String(h.resort||''),day:o.day,nights:o.nights,adults:o.adults,
  ages:Object.freeze([...(o.ages||[])]),room:String(o.room||''),meal:String(mealLabel(o)||''),operator:String(o.operator||'ANEX'),
  price,currency:'RUB',flights:Object.freeze([])});
}
function openAnexApplicationPreview(){
 const receipt=anexApplicationDraft;if(!receipt)return;
 if(receipt.finalPriceVerified===true){
  if(receipt.expiresAt*1000<=Date.now()){openAnexPackageQuote(selectedOffer,null,'Срок подтверждённой цены истёк.');return;}
  showModal('anex-application','Заявка на тур','ANEX · ПОДТВЕРЖДЁННАЯ СТОИМОСТЬ',`<div class="verification-tour"><strong>${esc(receipt.hotel)}</strong><span>${dateText(receipt.day)} · ${nightsText(receipt.nights)} · ${receipt.adults+(receipt.ages?.length||0)} туриста</span><span>${esc(receipt.room)} · ${esc(receipt.meal)}</span><strong>${money(receipt.price)}</strong></div>${receipt.flights.map(f=>`<p><strong>${f.direction==='0'?'Туда':'Обратно'}</strong><br>${esc(f.name)}</p>`).join('')}${window.AnyTourPrototypeLead.markup()}`,true);
  $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="modal-back">К туру</button>${window.AnyTourPrototypeLead.action()}`;
  window.AnyTourPrototypeLead.bindProviderApplication(receipt);return;
 }
 showModal('anex-application','Заявка на тур','ANEX · РАСЧЁТНАЯ СТОИМОСТЬ',window.AnyTourPrototypeLead.markup(),true);
 $('#modal-body').insertAdjacentHTML('afterbegin',`<div class="verification-tour"><strong>${esc(receipt.hotel)}</strong><span>${dateText(receipt.day)} · ${nightsText(receipt.nights)} · ${receipt.adults+(receipt.ages?.length||0)} туриста</span><span>${esc(receipt.room)} · ${esc(receipt.meal)}</span><strong>${money(receipt.price)}</strong></div><p class="modal-intro">Расчётная сумма включает обязательную доплату ANEX для выбранного состава туристов. Итоговая стоимость требует подтверждения.</p>`);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="all-offers" data-id="${selectedOffer.hotelId}">К вариантам</button>${window.AnyTourPrototypeLead.action()}`;
 window.AnyTourPrototypeLead.bindProviderApplication(receipt);
}
const anexFlightViews=new WeakMap();
function openAnexPackageQuote(o,result,error='',pending=false){
 const h=selectedTourHotel(o);if(!h)return;
 if(result?.state==='quote_verified'&&result.expiresAt*1000<=Date.now()){result=null;error='Срок подтверждённой цены истёк.';}
 rememberProviderView(o,'anex-quote',result,error,pending);selectedOffer={...o,loading:false};anexApplicationDraft=null;
 const verified=result?.state==='quote_verified'&&result.finalPriceVerified===true;
 if(verified){
  const raw=o.raw;anexApplicationDraft=Object.freeze({provider:'anex',offerRef:raw.offerRef,searchRef:raw.searchRef,generation:raw.anexGeneration,
   departure:String(o.origin||state.search.origin||''),localHotelId:Number(raw.anexLocalHotelId),priceKind:'verified',finalPriceVerified:true,choiceRef:result.choice.choiceRef,expiresAt:result.expiresAt,
   hotel:String(h.name||''),country:String(countryNames[h.country]||''),resort:String(h.resort||''),day:o.day,nights:o.nights,adults:o.adults,
   ages:Object.freeze([...(o.ages||[])]),room:String(o.room||''),meal:String(mealLabel(o)||''),operator:'ANEX',
   price:Number(result.finalPrice.amount),currency:'RUB',flights:Object.freeze(result.choice.legs.map((leg,i)=>Object.freeze({direction:String(i),name:leg.label})))});
 }
 const choices=result?.state==='quote_choices'?result.choices:[];
 const view=retainedProviderView(o),choice=choices.find(c=>c.choiceRef===view?.choice)?.choiceRef||choices[0]?.choiceRef;
 if(view)view.choice=choice;
 const content=`<div class="verification-tour"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span><span>${esc(o.room)} · ${esc(mealLabel(o))}</span>${verified?`<strong>${money(Number(result.finalPrice.amount))}</strong>`:''}</div>`
  +(verified?`<p>ANEX пересчитал полную стоимость тура с выбранным перелётом.</p>${result.choice.legs.map((leg,i)=>`<p><strong>${i?'Обратно':'Туда'}</strong><br>${esc(leg.label)}</p>`).join('')}`
   :choices.length?`<p>Выберите перелёт. Полную цену подтвердит ANEX после выбора.</p><fieldset class="flight-options"><legend>Перелёт туда и обратно</legend>${choices.map((c,i)=>`<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="anex-package-choice" value="${esc(c.choiceRef)}" ${c.choiceRef===choice?'checked':''} ${pending||error?'disabled':''}><span>${c.legs.map((leg,n)=>`<strong>${n?'Обратно':'Туда'}</strong><small>${esc(leg.label)}</small>`).join('')}</span></div></label>`).join('')}</fieldset>`:'')
  +`<p id="anex-package-status" class="${error?'error-text':''}" role="${error?'alert':'status'}">${esc(error||(pending?'ANEX проверяет выбранный тур…':''))}</p>`;
 showModal('anex-quote',verified?'Тур подтверждён':'Перелёт и цена тура','ANEX · АКТУАЛИЗАЦИЯ',content,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button>`+(verified?'<button class="primary" data-action="anex-application-preview">К заявке</button>':choices.length&&!error?`<button class="primary" data-action="anex-package-calculate" ${pending?'disabled':''}>Подтвердить перелёт и цену</button>`:'');
}
async function loadAnexPackageQuote(calculate=false){
 const o=selectedOffer,view=retainedProviderView(o);if(!o||o.provider!=='anex'||view?.pending)return;
 if(calculate&&modalType!=='anex-quote'||!calculate&&!['anex-current','anex-additional'].includes(modalType))return;
 const choice=calculate?$('[name="anex-package-choice"]:checked')?.value:null;if(calculate&&!choice)return;
 const old=calculate?view?.result:null;openAnexPackageQuote(o,old,'',true);
 try{
  const result=await data.verifyAnexPackage(o,choice);
  rememberProviderView(o,'anex-quote',result);
  if($('#modal').open&&selectedOffer?.raw===o.raw&&modalType==='anex-quote')openAnexPackageQuote(o,result);
 }catch(error){
  rememberProviderView(o,'anex-quote',old,error.message);
  if($('#modal').open&&selectedOffer?.raw===o.raw&&modalType==='anex-quote')openAnexPackageQuote(o,old,error.message);
 }
}
function anexFlightInventoryHTML(o){
 const view=anexFlightViews.get(o.raw);
 if(!view)return `<h3>Перелёт ANEX</h3><p>Рейсы можно запросить независимо от расчёта доплат.</p><button class="secondary" data-action="anex-flights" ${retainedProviderView(o)?.pending?'disabled':''}>Показать рейсы ANEX</button>`;
 if(view.pending)return '<h3>Перелёт ANEX</h3><p role="status">Загружаем рейсы…</p>';
 if(view.error)return `<h3>Перелёт ANEX</h3><p class="error-text" role="alert">${esc(view.error)} Повторный запрос автоматически не выполняется.</p>`;
 const result=view.result,point=p=>[p.airportCode||p.airport,p.time].filter(Boolean).join(' · ')||'Расписание уточняется';
 const availability={Y:'Есть места',N:'Нет мест',R:'Под запрос'};
 const routes=result.routes.map(route=>`<details open><summary>${esc([flightDateText(route.date),route.from,route.to].filter(Boolean).join(' · ')||'Направление уточняется')}</summary>${route.options.length?route.options.map(option=>`<div class="verification-tour"><strong>${esc([option.name,option.carrier].filter(Boolean).join(' · ')||'Рейс уточняется')}</strong><span>${esc(point(option.departure))} → ${esc(point(option.arrival))}</span>${option.classes.map(item=>`<span>${esc(item.name||'Класс уточняется')} · ${availability[item.availability]||'Места уточняются'} · Багаж: ${esc(item.baggage??'не указан')} · Ручная кладь: ${esc(item.handBaggage??'не указана')}</span>`).join('')}</div>`).join(''):'<p>Варианты рейсов не получены.</p>'}</details>`).join('');
 return `<h3>Перелёт ANEX</h3>${routes||'<p>ANEX не вернул варианты рейсов для этого предложения.</p>'}${result.truncated?'<p>Показана часть вариантов поставщика.</p>':''}<p class="modal-intro">Это варианты перелёта, не выбранные рейсы. Их включение в цену и итоговая стоимость тура пока не подтверждены.</p>`;
}
function renderAnexFlightInventory(){
 if(!selectedOffer||!['anex-current','anex-additional'].includes(modalType))return;
 let section=$('#anex-flight-inventory');
 if(!section){$('#modal-body').insertAdjacentHTML('beforeend','<section id="anex-flight-inventory" aria-label="Перелёт ANEX"></section>');section=$('#anex-flight-inventory');}
 section.innerHTML=`<p><button class="primary" data-action="anex-package-quote" ${retainedProviderView(selectedOffer)?.pending?'disabled':''}>Актуализировать тур и выбрать перелёт</button></p>`+anexFlightInventoryHTML(selectedOffer);
 const apd=$('[data-action="anex-additional-prices"]'),view=retainedProviderView(selectedOffer);
 if(apd)apd.disabled=!!(view?.pending||view?.error||anexFlightViews.get(selectedOffer.raw)?.pending);
}
async function loadAnexFlightInventory(){
 const o=selectedOffer;
 if(!o||!['anex-current','anex-additional'].includes(modalType)||retainedProviderView(o)?.pending||anexFlightViews.has(o.raw))return;
 anexFlightViews.set(o.raw,{pending:true});renderAnexFlightInventory();
 try{const result=await data.verifyAnexFlights(o);anexFlightViews.set(o.raw,{result});}
 catch(error){anexFlightViews.set(o.raw,{error:error.message});}
 if($('#modal').open&&selectedOffer?.raw===o.raw)renderAnexFlightInventory();
}
function openAnexConcreteCurrent(o,current){
 if(current?.finalPriceReady===true&&current.additionalPrices){selectedOffer={...o,loading:false};openAnexAdditionalEstimate(o,current.additionalPrices);return;}
 const h=selectedTourHotel(o),price=Number(o.total);
 if(!h||!Number.isFinite(price)||price<=0){selectedOffer={...o,loading:false,quoteError:'ANEX не вернул корректную цену предложения.'};renderRealOffer();return;}
 rememberProviderView(o,'anex-current',current,retainedProviderView(o)?.error,retainedProviderView(o)?.pending);selectedOffer={...o,loading:false};anexApplicationDraft=null;anexCurrentDraft={offer:o,current};
 showModal('anex-current','Предложение ANEX','ANEX · ТУР ИЗ ТЕКУЩЕГО ПОИСКА',`<div class="verification-tour"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span><span>${esc(o.room)} · ${esc(mealLabel(o))}</span><strong>${money(price)}</strong></div><p class="modal-intro">Предложение относится к текущему поиску. Это проверка контекста, а не пересчёт стоимости у ANEX. Показана цена из поиска; обязательные доплаты и итоговая цена ещё требуют подтверждения.</p><p class="error-text" id="anex-additional-error" role="alert"></p>`,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button><button class="primary" data-action="anex-additional-prices">Уточнить обязательные доплаты</button>`;
 renderAnexFlightInventory();
}
function openAnexAdditionalEstimate(o,result){
 const h=selectedTourHotel(o),search=Number(result?.searchPrice?.amount),surcharge=Number(result?.partySurcharge?.amount),total=Number(result?.calculatedTotal?.amount);
 if(!h||![search,total].every(value=>Number.isFinite(value)&&value>0)||!Number.isFinite(surcharge)||surcharge<0){throw new Error('ANEX вернул некорректный расчёт доплат.');}
 rememberProviderView(o,'anex-additional',result);anexCurrentDraft=null;anexApplicationDraft=anexApplicationReceipt(o,result,h);
 showModal('anex-additional','Доплаты ANEX рассчитаны','ANEX · ОБЯЗАТЕЛЬНЫЕ ДОПЛАТЫ',`<div class="verification-tour"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span><span>${esc(o.room)} · ${esc(mealLabel(o))}</span></div><div class="price-breakdown"><div class="price-line"><span>Цена из поиска ANEX</span><strong>${money(search)}</strong></div><div class="price-line"><span>Обязательная доплата за состав туристов</span><strong>${money(surcharge)}</strong></div><div class="price-line total"><span>Расчётная сумма с доплатой</span><strong>${money(total)}</strong></div></div><p class="modal-intro">Доплата и сумма рассчитаны сервером по данным ANEX для этого конкретного предложения. Это расчёт обязательных доплат, а не финально подтверждённая цена бронирования.</p><p class="modal-intro">Можно проверить контактные данные для заявки. Реальная отправка в этой preview-версии отключена.</p>`,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button>${anexApplicationDraft?'<button class="primary" data-action="anex-application-preview">К заявке</button>':'<button class="primary" data-action="close-modal">Готово</button>'}`;
 renderAnexFlightInventory();
}
async function applyAnexAdditionalPrices(){
 const draft=anexCurrentDraft;if(!draft||modalType!=='anex-current'||anexFlightViews.get(draft.offer.raw)?.pending)return;
 selectionGeneration++;const button=$('[data-action="anex-additional-prices"]');if(button)button.disabled=true;
 const error=$('#anex-additional-error');if(error)error.textContent='';
 rememberProviderView(draft.offer,'anex-current',draft.current,'',true);
 renderAnexFlightInventory();
 try{
  const result=await data.verifyAnexAdditional(draft.offer);
  rememberProviderView(draft.offer,'anex-additional',result);
  if(!$('#modal').open||modalType!=='anex-current'||selectedOffer?.raw!==draft.offer.raw)return;
  openAnexAdditionalEstimate(draft.offer,result);
 }catch(e){
  rememberProviderView(draft.offer,'anex-current',draft.current,e.message+' Для новой попытки повторите поиск.');
  if($('#modal').open&&modalType==='anex-current'&&selectedOffer?.raw===draft.offer.raw){
   const node=$('#anex-additional-error');if(node)node.textContent=e.message+' Для новой попытки повторите поиск.';
  }
 }finally{if(selectedOffer?.raw===draft.offer.raw)renderAnexFlightInventory();}
}
async function refreshLiveHotel(id){
 const o=selectedOffer,h=selectedTourHotel(o);
 if(!o||o.hotelId!==id||!needsRefresh(o)||!h){toast('Не удалось определить варианты отеля. Повторите общий поиск.');return;}
 if(restoreProviderView(o))return;
 if(o.provider==='anex'&&o.raw?.anexKind==='concrete'&&o.raw?.anexSessionCurrent===true){
  const run=++selectionGeneration;selectedOffer={...o,loading:true,quoteError:''};renderRealOffer();
  try{
   const current=await data.verifyAnexConcrete(o);
   rememberProviderView(o,'anex-current',current);
   if(run!==selectionGeneration||!$('#modal').open||modalType!=='offer'||selectedOffer?.key!==o.key)return;
   openAnexConcreteCurrent(o,current);
  }catch(error){
   if(run===selectionGeneration&&$('#modal').open&&modalType==='offer'&&selectedOffer?.key===o.key){
    selectedOffer={...o,loading:false,quoteError:error.message};renderRealOffer();
   }
  }
  return;
 }
 if(o.provider==='andromeda'&&o.raw?.quoteRequired===true){
  const run=++selectionGeneration;selectedOffer={...o,loading:true,quoteError:''};renderRealOffer();
  try{
   const quote=await data.verifyAndromeda(o);
   if(run!==selectionGeneration||!$('#modal').open||modalType!=='offer'||selectedOffer?.key!==o.key)return;
   selectedOffer={...o,loading:false};
   if(quote.state==='flight_selection_required')openAndromedaFlightChoice(o,quote);else openAndromedaVerified(o,quote);
  }catch(error){
   if(run===selectionGeneration&&$('#modal').open&&modalType==='offer'&&selectedOffer?.key===o.key){
    selectedOffer={...o,loading:false,quoteError:error.message,quoteErrorCode:error.code,quoteErrorTerminal:error.retryable===false};renderRealOffer();
   }
  }
  return;
 }
 if(o.provider==='anex'&&o.raw?.anexKind==='group_minimum'){
  const run=++selectionGeneration;terminalizeSearchForVerification();renderResults({keepFilters:true});
  selectedOffer={...o,loading:true,quoteError:''};renderRealOffer();
  try{
   const expanded=await data.expandAnexGroup(o);
   if(run!==selectionGeneration||!$('#modal').open||modalType!=='offer'||selectedOffer?.key!==o.key)return;
   const keys=new Set(expanded.offers.map(item=>item.key));
   h.offers=[...h.offers.filter(item=>item.key!==o.key&&!keys.has(item.key)),...expanded.offers];
   selectedOffer={...o,loading:false};renderResults({keepFilters:true});
   openAllOffers(id,{day:o.day,nights:o.nights,flight:'',room:'',meal:'',sort:'price',mode:'list',pair:[],activeVariant:null,differencesOnly:false});
   toast('ANEX вернул конкретные варианты тура');
  }catch(error){
   if(run===selectionGeneration&&$('#modal').open&&modalType==='offer'&&selectedOffer?.key===o.key){
    selectedOffer={...o,loading:false,quoteError:error.message};renderRealOffer();
   }
  }
  return;
 }
 refreshHotel(id,true);
}
function refreshHotel(id,freshSearch=false){
 if(data.live&&!freshSearch)return refreshLiveHotel(id);
 const o=selectedOffer,h=selectedTourHotel(o);
 if(!o||o.hotelId!==id||!needsRefresh(o)||!data.preview&&!h?.legacyIds.length){toast('Не удалось определить варианты отеля. Повторите общий поиск.');return;}
 const next={...structuredClone(o.search),from:o.day,to:o.day,minNights:o.nights,maxNights:o.nights,adults:o.adults,ages:[...o.ages]};
 const exactFilters=defaultFilters();exactFilters.hotelId=id;if(o.meal&&!/уточняется/i.test(o.meal))exactFilters.meals=[o.meal];if(o.operator&&!/уточняется/i.test(o.operator))exactFilters.operators=[o.operator];if(['regular','charter'].includes(o.flight))exactFilters.flight=[o.flight];
 try{data.params(next,h.legacyIds,exactFilters);}catch(error){toast(error.message);return;}
 closeModal();state.search=next;draft=structuredClone(next);draftDestination=null;state.filters=exactFilters;state.selectedDate=null;state.openHotel=id;
 updateSearchUI();updateURL();renderFilters();runSearch({hotelIds:h.legacyIds,exactRefresh:true,exactRefreshTarget:selectedTourOfferSnapshot(o)});
 $('#search-status').scrollIntoView({behavior:scrollBehavior(),block:'start'});
}
function openLeadPreview(source=selectedOffer){
 const current=source,h=selectedTourHotel(current),o=leadReadyOffer(current);if(!current||!h||!o)return;if(needsRefresh(current)||!window.AnyTourPrototypeLead.canApply(o)){renderRealOffer();return;}
 showModal('selected-tour','Заявка на тур',data.live?'ПРОВЕРКА БЕЗ ОТПРАВКИ':'УЧЕБНЫЙ СЦЕНАРИЙ',`${selectionStepsHTML(2,{flightDeferred:!flightPairFor(o)})}${quotePriceChangeHTML(o)}<section class="application-choice" aria-label="Выбранный тур"><h3>${esc(h.name)}</h3>${chosenStayHTML(o)}</section>${data.live?fuelDisclosureHTML(o):''}${window.AnyTourPrototypeLead.markup(o)}${finalFlightDetailsHTML(o)}`);
 $('#modal').classList.add('application-dialog');
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total summary-price"><span>За всех туристов</span><strong>${money(o.total)}</strong><small>${selectedPriceStatus(o)}</small></div>${window.AnyTourPrototypeLead.action(o)}`;
 window.AnyTourPrototypeLead.bind(o);
}

let offerView=null,comparisonQuotes=[];
const offerGroupKey=o=>encodeURIComponent(o.room+'|'+o.meal);
function restoredOfferLimits(value,offers){
 const limits={};if(!value||typeof value!=='object'||Array.isArray(value))return limits;
 const sizes=new Map();for(const offer of offers){const key=offerGroupKey(offer);sizes.set(key,(sizes.get(key)||0)+1);}
 for(const [key,limit] of Object.entries(value).slice(0,100)){
  const size=sizes.get(key),max=Math.min(500,(size||0)+7);
  if(size>4&&Number.isInteger(limit)&&limit>=12&&(limit-4)%8===0&&limit<=max)limits[key]=limit;
 }
 return limits;
}
const sharedOfferNote=offers=>{const notes=[...new Set(offers.map(offerMetaNote))];return notes.length===1?notes[0]:'';};
function openAllOffers(id,restored=null){
 const h=hotels.find(h=>h.id===id),all=hotelOffers(h);
 offerView={id,departure:'',flight:'',room:'',meal:'',sort:'price',mode:'list',day:all[0]?.day||state.search.from,nights:all[0]?.nights||state.search.minNights,pair:[],activeVariant:null,differencesOnly:false,open:[],limits:{}};
 if(restored)offerListRestores.set(offerView,restored);
 if(restored){for(const [name,values] of Object.entries({departure:['',...all.map(o=>o.day)],flight:['','regular','charter','unknown'],room:['',...all.map(o=>o.room)],meal:['',...all.map(o=>o.meal)],sort:['price','date'],mode:optionalShortlistEnabled?['list','compare']:['list']}))if(values.includes(restored[name]))offerView[name]=restored[name];if(all.some(o=>o.day===restored.day))offerView.day=restored.day;if(all.some(o=>o.nights===restored.nights))offerView.nights=restored.nights;}
 const restoredOpen=restored?boundedHistoryStrings(restored.open):null;
 if(restored){if(Array.isArray(restored.pair))offerView.pair=[...new Set(restored.pair.filter(v=>all.some(o=>o.variant===v)))].slice(0,2);if(all.some(o=>o.variant===restored.activeVariant))offerView.activeVariant=restored.activeVariant;offerView.differencesOnly=restored.differencesOnly===true;if(restoredOpen)offerView.open=restoredOpen.filter(key=>all.some(o=>offerGroupKey(o)===key));offerView.limits=restoredOfferLimits(restored.limits,all);}
 showModal('all-offers',h.name,'ВЫБЕРИТЕ КОНКРЕТНЫЙ ТУР','<div id="all-offers-list" aria-live="polite"></div>',true);
 $('#modal').classList.add('offers-dialog');renderOfferList(restoredOpen===null);
}
const offerCountText=n=>`${n} ${n%10===1&&n%100!==11?'тур':n%10>=2&&n%10<=4&&(n%100<12||n%100>14)?'тура':'туров'}`;
const offerSearchContext=()=>state.selectedDate?`Вылет ${dateText(state.selectedDate)}`:state.search.from===state.search.to?`Вылет ${dateText(state.search.from)}`:`Период вылета: ${dateText(state.search.from)} — ${dateText(state.search.to)}`;


function renderComparisonFooter(){const o=comparisonQuotes.find(o=>o.variant===offerView?.activeVariant),footer=$('#modal-footer');footer.hidden=!o;if(!o){footer.innerHTML='';return;}footer.innerHTML=`<div class="comparison-footer-summary" aria-live="polite"><div><span>За ${guestsText(o)}</span><strong>${money(o.total)}</strong><small class="comparison-price-status">${esc(cardPriceNote(o))}</small></div><p>${esc(o.room)}<span>${esc(mealLabel(o))} · ${esc(o.operator)}</span></p></div>${needsRefresh(o)?'':`<button class="secondary" data-action="offer-flights" data-key="${esc(o.key)}">${icon('plane')} Рейсы и багаж</button>`}<button class="primary" data-action="offer" data-key="${esc(o.key)}">${offerActionLabel(o)} ${icon('arrow')}</button>`;}

function selectComparisonVariant(variant){
 if(modalType!=='all-offers'||offerView?.mode!=='compare'||!comparisonQuotes.some(o=>o.variant===variant))return;
 offerView.activeVariant=variant;
 if(!offerView.pair.includes(variant))offerView.pair[offerView.pair.length>1?1:0]=variant;
 $$('.tour-comparison-option').forEach(el=>el.classList.toggle('focused-option',+el.dataset.variant===variant));
 $$('input[name="comparison-focus"]').forEach(el=>el.checked=+el.value===variant);
 renderComparisonFooter();rememberUIRoute();
}
function refreshTourComparison(focusId){
 if(modalType!=='all-offers'||offerView?.mode!=='compare')return;
 const body=$('#modal-body'),scroll=body.scrollTop;renderOfferList();if(focusId)$('#'+focusId)?.focus({preventScroll:true});body.scrollTop=scroll;
}


const offerRefinementFields=['departure','flight','room','meal'];


function refreshOpenOfferList(){
 if(modalType!=='all-offers'||!offerView)return;
 const body=$('#modal-body'),focus=focusReference(document.activeElement,body),scroll=body.scrollTop;
 renderOfferList();
 if(focus&&document.activeElement!==focus.element)restoreFocus(focus,$('#offer-count'),body);
 body.scrollTop=scroll;
}


// Keep the modal shell/history synchronous; fetch only its cold list renderer.
let offerListLoad=null,offerListRequest=0;
const offerListRestores=new WeakMap();
const offerListAsset=document.head.dataset.offerListSrc;
function loadOfferList(){
 if(window.AnyTourOfferList?.create)return Promise.resolve(window.AnyTourOfferList);
 if(offerListLoad)return offerListLoad;
 offerListLoad=new Promise((resolve,reject)=>{
  const script=document.createElement('script');let finished=false;
  const finish=error=>{if(finished)return;finished=true;clearTimeout(timer);script.onload=script.onerror=null;script.remove();if(error){offerListLoad=null;reject(error);}else resolve(window.AnyTourOfferList);};
  const timer=setTimeout(()=>finish(new Error('Offer list load timed out')),15000);
  script.onload=()=>finish(window.AnyTourOfferList?.create?null:new Error('Offer list owner missing'));
  script.onerror=()=>finish(new Error('Offer list load failed'));
  script.src=offerListAsset;document.head.append(script);
 });
 return offerListLoad;
}
function renderOfferList(reset=false){
 const view=offerView,request=++offerListRequest;
 const current=()=>offerView===view&&request===offerListRequest&&modalType==='all-offers'&&$('#modal').open;
 const render=owner=>{
  if(!current())return;
  owner.create({$,$$,cardPriceNote,dateText,durationText,esc,flightLabel,guestsText,hotelOffers,hotels,icon,innerWidth,mealLabel,mealNames,money,needsRefresh,nightsText,offerActionLabel,offerCountText,offerGroupKey,offerMetaNote,offerRefinementFields,offerSearchContext,offerView,operatorBadge,optionalShortlistEnabled,rangeText,rememberUIRoute,renderComparisonFooter,sharedOfferNote,state,selectionStepsHTML,setComparisonQuotes:value=>{comparisonQuotes=value;}}).renderOfferList(reset);
  const restored=offerListRestores.get(view);if(restored){offerListRestores.delete(view);const filters=$('.offer-filter-disclosure');if(filters)filters.open=restored.filtersOpen===true;if(Number.isFinite(restored.scroll)&&restored.scroll>=0)$('#modal-body').scrollTop=restored.scroll;rememberUIRoute();}
 };
 if(window.AnyTourOfferList?.create){render(window.AnyTourOfferList);return;}
 $('#all-offers-list').innerHTML='<p role="status">Загружаем варианты тура…</p>';
 loadOfferList().then(render).catch(()=>{if(current())$('#all-offers-list').innerHTML='<div role="alert"><p>Не удалось загрузить варианты тура.</p><button class="secondary" data-action="retry-offer-list">Попробовать ещё раз</button></div>';});
}
let verifiedOffer=null;
function cancelVerification(){}
function confirmTour(){openLeadPreview();}
const selectedTourKey='anytour.prototype.v18.selected-tour.v1',selectedTourTTL=24*60*60*1000;
let savedSelection=null,savedSelectionHotel=null,savedSelectionObservedAt=0,removedSelectedTour=null,removedSelectedTourHotel=null,removedSelectedTourObservedAt=0,selectedTourExpiryTimer=null,savedSelectionPersistent=false;
function selectedTourHotel(o=savedSelection){return hotels.find(h=>h.id===o?.hotelId)||(savedSelectionHotel?.id===o?.hotelId?savedSelectionHotel:null);}
function selectedTourOfferSnapshot(o){
 const search=o?.search||{},day=String(o?.day||''),nights=Number(o?.nights),adults=Number(o?.adults),hotelId=Number(o?.hotelId),total=Number(o?.total);
 if(!/^\d{4}-\d{2}-\d{2}$/.test(day)||!Number.isSafeInteger(hotelId)||hotelId<1||!Number.isInteger(nights)||nights<1||!Number.isInteger(adults)||adults<1||!Number.isFinite(total)||total<=0)return null;
 const ages=Array.isArray(o.ages)?o.ages.filter(age=>Number.isInteger(age)&&age>=0&&age<=17).slice(0,3):[];
 return {key:String(o.key||''),hotelId,day,nights,returnDay:addDays(day,nights),variant:Number.isInteger(o.variant)?o.variant:0,total,room:String(o.room||'Номер уточняется'),placement:String(o.placement||''),adults,ages,origin:String(o.origin||search.origin||''),meal:String(o.meal||'Питание уточняется'),operator:String(o.operator||'Туроператор уточняется'),flight:['regular','charter'].includes(o.flight)?o.flight:'unknown',cached:true,provider:String(o.provider||'local'),raw:{selectionEnabled:false},search:{origin:String(search.origin||o.origin||''),country:String(search.country||''),from:String(search.from||day),to:String(search.to||day),minNights:Number(search.minNights)||nights,maxNights:Number(search.maxNights)||nights,adults,ages:[...ages]},savedFuelAmount:fuelAmount(o),savedFlightAllowance:{baggage:flightAllowanceText(o,'baggage'),carryOn:flightAllowanceText(o,'carryOn')},flightChoiceId:null,tour:null,variants:[],savedFlightText:savedFlightTextPlain(o),savedFlightLegs:savedFlightLegs(o),loading:false,flightsLoading:false,pricePending:false};
}
function selectedTourHotelSnapshot(h){if(!h||!Number.isSafeInteger(Number(h.id)))return null;return {id:Number(h.id),name:String(h.name||'Выбранный отель'),resort:String(h.resort||''),country:String(h.country||''),stars:Number(h.stars)||0,rating:Number.isFinite(Number(h.rating))?Number(h.rating):null,photos:Array.isArray(h.photos)?h.photos.filter(x=>typeof x==='string').slice(0,8):[],legacyIds:Array.isArray(h.legacyIds)?h.legacyIds.filter(x=>Number.isSafeInteger(Number(x))&&Number(x)>0).map(Number):[],raw:{arrival:data.text(h.raw?.arrival)}};}
function selectedTourRecord(){const offer=selectedTourOfferSnapshot(savedSelection),hotel=selectedTourHotelSnapshot(selectedTourHotel());return offer&&hotel&&savedSelectionObservedAt?{version:1,observedAt:savedSelectionObservedAt,offer,hotel}:null;}
function scheduleSelectedTourExpiry(){clearTimeout(selectedTourExpiryTimer);if(!savedSelectionObservedAt)return;const delay=savedSelectionObservedAt+selectedTourTTL-Date.now();if(delay<=0){expireSelectedTour();return;}selectedTourExpiryTimer=setTimeout(()=>{expireSelectedTour();updateNav();refreshSavedTourControls();},delay+25);}
function expireSelectedTour(){clearTimeout(selectedTourExpiryTimer);selectedTourExpiryTimer=null;savedSelection=null;savedSelectionHotel=null;savedSelectionObservedAt=0;clearStored(selectedTourKey);}
function persistSelectedTour(){const record=selectedTourRecord();try{if(record)localStorage.setItem(selectedTourKey,JSON.stringify(record));else localStorage.removeItem(selectedTourKey);savedSelectionPersistent=!!record;}catch{savedSelectionPersistent=false;}scheduleSelectedTourExpiry();}
function demoteSavedTour(){if(!readSelectedTour())return;savedSelection=selectedTourOfferSnapshot(savedSelection);persistSelectedTour();}
function sameSelectedTourConditions(o,target){return !!o&&!!target&&o.hotelId===target.hotelId&&o.day===target.day&&o.nights===target.nights&&o.adults===target.adults&&JSON.stringify(o.ages||[])===JSON.stringify(target.ages||[])&&o.room===target.room&&o.meal===target.meal&&o.operator===target.operator&&o.flight===target.flight;}
function readSelectedTour(){if(savedSelectionObservedAt&&Date.now()-savedSelectionObservedAt>=selectedTourTTL)expireSelectedTour();return savedSelection;}
function savedTourControlsHTML(o,{showResults=true}={}){
 if(o?.provider==='recorded')return '<p class="saved-flight-notice">Загруженный тур доступен только в этой вкладке. Для повторной проверки после перезагрузки загрузите файл снова.</p>';
 const saved=readSelectedTour(),same=!!saved&&sameSelectedTourConditions(o,saved)&&o.key===saved.key&&o.total===saved.total&&savedFlightTextPlain(o)===savedFlightTextPlain(saved);
 const canSave=!o.loading&&!o.flightsLoading&&!o.pricePending&&Number.isFinite(o.total)&&o.total>0;
 const until=savedSelectionObservedAt?new Date(savedSelectionObservedAt+selectedTourTTL).toLocaleString('ru-RU',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}):'';
 return `<section id="saved-tour-controls" class="tour-save-choice" aria-label="Сохранить выбранный тур">${same?`<strong>${icon('check')} В «Мой тур»</strong><p id="saved-tour-storage-status" role="status" tabindex="-1">${savedSelectionPersistent?'Сохранён в этом браузере до '+esc(until)+'.':'Браузер не разрешил сохранение. Тур доступен только до перезагрузки страницы.'} Цена и наличие требуют проверки.</p><div class="tour-save-actions">${showResults?'<button class="secondary" data-action="close-modal">К результатам</button>':''}<button class="text-button" data-action="remove-selected-tour">Удалить из «Мой тур»</button></div>`:`<button class="secondary" data-action="save-tour-for-later" ${canSave?'':'disabled'}>${icon('suitcase')} ${saved?'Заменить тур в «Мой тур»':'Сохранить в «Мой тур»'}</button><p id="saved-tour-storage-status" role="status" tabindex="-1">${saved?'Сейчас сохранён '+esc(selectedTourHotel(saved)?.name||'другой тур')+'. Новый выбор заменит его.':'Номер, питание, даты и цена сохранятся в этом браузере на 24 часа. Это не бронирование.'}</p>`}</section>`;
}
function refreshSavedTourControls(focus=false){const section=$('#saved-tour-controls');if(!section||!selectedOffer)return;section.outerHTML=savedTourControlsHTML(selectedOffer,{showResults:modalType!=='selected-tour'});if(focus)$('#saved-tour-storage-status').focus({preventScroll:true});}
function openSelectedTour(){
 const saved=readSelectedTour();if(saved){selectedOffer=saved;renderRealOffer();return;}
 const canUndo=removedSelectedTour&&Date.now()-removedSelectedTourObservedAt<selectedTourTTL;
 showModal('saved-tour','Мой тур','ВАШ ВЫБОР',`<div class="saved-empty"><h3>${canUndo?'Тур удалён из «Мой тур»':'Тур пока не сохранён'}</h3><p>${canUndo?'Можно восстановить последний выбор.':'Откройте конкретное предложение и нажмите «Сохранить в “Мой тур”».'}</p><div class="saved-empty-actions">${canUndo?'<button class="primary" data-action="undo-selected-tour">Восстановить тур</button>':''}<button class="secondary" data-action="close-modal">К результатам</button></div></div>`);
}
function restoreSelectedSearch(){closeModal();editSearch();}
function replaceSelectedTourView(){const previous=restoringModal;restoringModal=true;openSelectedTour();restoringModal=previous;}
function removeSelectedTour(){if(!readSelectedTour())return;try{localStorage.removeItem(selectedTourKey);}catch{const status=$('#saved-tour-storage-status');if(status){status.textContent='Не удалось удалить тур из браузера. Он пока остаётся в «Мой тур»; попробуйте ещё раз.';status.focus({preventScroll:true});}return;}removedSelectedTour=savedSelection;removedSelectedTourHotel=savedSelectionHotel;removedSelectedTourObservedAt=savedSelectionObservedAt;expireSelectedTour();updateNav();replaceSelectedTourView();}
function undoSelectedTour(){if(!removedSelectedTour||Date.now()-removedSelectedTourObservedAt>=selectedTourTTL)return; savedSelection=removedSelectedTour;savedSelectionHotel=removedSelectedTourHotel;savedSelectionObservedAt=removedSelectedTourObservedAt;removedSelectedTour=null;removedSelectedTourHotel=null;removedSelectedTourObservedAt=0;persistSelectedTour();updateNav();replaceSelectedTourView();}
function completeTour(o){selectedOffer=o;openLeadPreview();}

let compareView={onlyDifferences:false,pair:[]};
function savedHotelPhoto(h,className){
 if(!h.photos?.length)return `<div class="${className} saved-photo-missing">${icon('image')}<span>Нет фотографий</span></div>`;
 return `<button class="${className}" data-action="gallery" data-id="${h.id}" aria-label="Фотографии ${esc(h.name)}"><img src="${esc(photoUrl(h))}" alt="${esc(h.name)}"></button>`;
}
function savedContext(compact=false,showPriceHelp=true){return `<div class="saved-context"><strong>${esc(state.search.origin)} → ${esc(countryNames[state.search.country]||'')}</strong><span>${departureScopeText()} · ${durationText()} · ${guestsText()}</span>${!showPriceHelp?'':compact?`<details class="saved-explanation"><summary>О ценах и сохранении</summary><p>Цены за всех туристов${filterCount()||state.onlyFavorites?' с учётом текущих фильтров':''}. Актуальность и состав цены — в условиях предложения. Список сохраняется в этом браузере.</p></details>`:`<small>Цены за всех туристов${filterCount()||state.onlyFavorites?' с учётом текущих фильтров':''}. Актуальность и состав цены — в условиях предложения.</small>`}</div>`;}
function savedAvailability(h){const offers=hotelOffers(h);return {hotel:h,offers,offer:offers[0],reason:h.country!==state.search.country?'Другое направление':'Нет туров по текущим фильтрам'};}
function savedRecovery(h){return `<button class="secondary saved-recovery" data-action="show-hotel" data-id="${h.id}">${h.country!==state.search.country?'Новый поиск':'Снять фильтры'}</button>`;}
function refreshSavedView(type,focusSelector){const scroll=$('#modal-body').scrollTop;if(type==='compare')renderCompare();else renderFavorites();$('#modal-body').scrollTop=scroll;if(focusSelector)($(focusSelector)||$('#modal-body button')||$('#modal-title')).focus({preventScroll:true});}
function toggleFavorite(id){const index=state.favorites.indexOf(id);if(index<0)state.favorites.push(id);else state.favorites.splice(index,1);saveStored('anytour.prototype.v18.favorites.v1',state.favorites);updateNav();$$(`[data-action="favorite"][data-id="${id}"]`).forEach(b=>{b.classList.toggle('active',index<0);b.setAttribute('aria-pressed',index<0);const h=hotels.find(h=>h.id===id);b.setAttribute('aria-label',`${index<0?'Убрать из избранного':'В избранное'}: ${esc(h.name)}`)});if(state.onlyFavorites)renderResults({keepFilters:true});if(modalType==='favorites')refreshSavedView('favorites',`#modal-body [data-action="favorite"][data-id="${id}"]`);toast(index<0?'Отель добавлен в избранное':'Отель удалён из избранного');}
function toggleCompare(id){const index=state.compare.indexOf(id);if(index<0){if(state.compare.length>=3){const message='Можно сравнить до 3 отелей. Уберите один, чтобы добавить другой.';if(modalType==='favorites'&&$('#modal').open&&$('#shortlist-status'))$('#shortlist-status').textContent=message;else toast(message);return}state.compare.push(id)}else state.compare.splice(index,1);saveStored('anytour.prototype.v18.compare.v1',state.compare);updateNav();$$(`[data-action="toggle-compare"][data-id="${id}"]`).forEach(b=>{b.classList.toggle('active',index<0);b.setAttribute('aria-pressed',index<0);b.innerHTML=icon(index<0?'check':'compare')+(b.classList.contains('compare-photo-button')?'':index<0?'В сравнении':'Сравнить отель');const label=index<0?'Убрать из сравнения':'Сравнить отель';b.setAttribute('aria-label',label);b.title=label});if(modalType==='compare'||modalType==='favorites')refreshSavedView(modalType,`#modal-body [data-action="toggle-compare"][data-id="${id}"]`);else toast(index<0?`В сравнении ${state.compare.length} из 3 отелей`:'Отель убран из сравнения');}
function openCompare(){showModal('compare','Сравнение отелей','ВАШ КОРОТКИЙ СПИСОК','',true);renderCompare();}
function renderCompare(){
 const hs=state.compare.map(id=>hotels.find(h=>h.id===id)).filter(Boolean);$('#modal').classList.add('comparison-dialog');$('#modal-footer').hidden=true;
 if(!hs.length){$('#modal-body').innerHTML='<div class="saved-empty"><h3>Какие отели сравним?</h3><p>Добавьте до трёх отелей из выдачи.</p></div>';return;}
 compareView.pair=compareView.pair.filter(id=>hs.some(h=>h.id===id));for(const h of hs)if(compareView.pair.length<2&&!compareView.pair.includes(h.id))compareView.pair.push(h.id);
 const mobile=innerWidth<=760,ordered=mobile?[...compareView.pair,...hs.map(h=>h.id).filter(id=>!compareView.pair.includes(id))].map(id=>hs.find(h=>h.id===id)):hs;
 const entries=ordered.map(savedAvailability),visible=entries.filter(e=>!mobile||compareView.pair.includes(e.hotel.id));
 const priced=visible.filter(e=>e.offer),comparable=priced.length===visible.length&&priced.length>1&&new Set(priced.map(({offer:o})=>JSON.stringify([o.day,o.returnDay,o.nights,o.adults,o.ages,mealLabel(o)]))).size===1,minPrice=priced.length?Math.min(...priced.map(e=>e.offer.total)):null;
 const hide=id=>mobile&&!compareView.pair.includes(id)?' mobile-hidden':'';
 const row=(key,label,get)=>{const diff=new Set(visible.map(get)).size>1;return compareView.onlyDifferences&&visible.length>1&&!diff?'':`<tr class="compare-fact ${diff?'is-different':''}"><th class="compare-key" scope="row">${label}</th>${entries.map(e=>`<td class="${hide(e.hotel.id)}">${esc(get(e))}</td>`).join('')}</tr>`;};
 const controls=hs.length===3?`<div class="compare-pair-controls"><p>Выберите два отеля для сравнения</p>${['left','right'].map((side,i)=>`<label>${i?'Второй':'Первый'} отель<select id="compare-${side}">${hs.map(h=>`<option value="${h.id}" ${compareView.pair[i]===h.id?'selected':''}>${esc(h.name)}</option>`).join('')}</select></label>`).join('')}</div>`:'';
 $('#modal-body').innerHTML=`${savedContext(false,false)}<div class="compare-controls"><label class="differences-toggle"><input type="checkbox" id="compare-differences" ${compareView.onlyDifferences&&visible.length>1?'checked':''} ${visible.length<2?'disabled':''}> Только отличия</label></div>${controls}<p class="compare-price-basis ${comparable?'':'compare-scope-note'}">Цены за всех${filterCount()?' по текущим фильтрам':''}. ${visible.length<2?'Добавьте ещё отель для сравнения.':comparable?'Даты, ночи и питание совпадают.':'Даты, номера и питание могут отличаться.'}</p><div class="comparison-scroll"><table class="comparison" data-count="${hs.length}"><thead><tr><th class="compare-key" scope="col">Ваш выбор</th>${entries.map(({hotel:h,offer:o,offers})=>`<th class="compare-hotel${hide(h.id)}"><div class="compare-hotel-card">${savedHotelPhoto(h,'compare-photo')}<button class="icon-button compare-remove" data-action="toggle-compare" data-id="${h.id}" aria-label="Убрать ${esc(h.name)}">${icon('x')}</button><span class="compare-stars">${h.stars?h.stars+' ★':'Категория не указана'}</span><button class="compare-name" data-action="hotel-details" data-id="${h.id}">${esc(h.name)}</button><div class="compare-price">${o?`<strong>${money(o.total)}</strong>${comparable?`<small class="compare-price-gap">${o.total===minPrice?'Минимальная цена в сравнении':'+'+money(o.total-minPrice)+' к минимуму'}</small>`:''}<span>${cardPriceNote(o)}</span>`:'Нет туров по текущим условиям'}</div>${o?`<button class="primary" data-action="offer" data-key="${esc(o.key)}">Этот тур</button>${offers.length>1?`<button class="text-button compare-all-tours" data-action="all-offers" data-id="${h.id}">Все туры отеля</button>`:''}`:''}</div></th>`).join('')}</tr></thead><tbody>${row('price','Цена за всех',e=>e.offer?money(e.offer.total):'Нет предложения')}${row('resort','Курорт',e=>e.hotel.resort||'Не указан')}${row('rating','Рейтинг / 5',e=>ratingText(e.hotel))}${row('date','Вылет',e=>e.offer?dateText(e.offer.day):'—')}${row('return','Возвращение',e=>e.offer?dateText(e.offer.returnDay):'—')}${row('nights','Ночей',e=>e.offer?.nights||'—')}${row('room','Номер',e=>e.offer?.room||'—')}${row('meal','Питание',e=>e.offer?mealLabel(e.offer):'—')}${row('flight','Перелёт',e=>e.offer?flightLabel(e.offer):'—')}${row('operator','Туроператор',e=>e.offer?.operator||'—')}</tbody></table></div>`;
 if(!$('.comparison tbody').children.length)$('#modal-body').insertAdjacentHTML('beforeend','<p class="compare-diff-empty" role="status">В доступных данных отличий нет. Отключите «Только отличия», чтобы увидеть все условия.</p>');
}
function openFavorites(){showModal('favorites','Избранные отели','ВАШ КОРОТКИЙ СПИСОК','',true);renderFavorites();}
function renderFavorites(){
 const entries=state.favorites.map(id=>savedAvailability(hotels.find(h=>h.id===id)));
 $('#modal').classList.add('favorites-dialog');$('#modal-kicker').textContent=`ИЗБРАННОЕ · ${entries.length} ${entries.length===1?'ОТЕЛЬ':entries.length<5?'ОТЕЛЯ':'ОТЕЛЕЙ'}`;
 if(!entries.length){$('#modal-body').innerHTML=`<div class="saved-empty">${icon('heart')}<h3>Сохраните понравившиеся отели</h3><p>Нажмите на сердечко на фотографии. Здесь можно будет сравнить цены и выбрать тур.</p><button class="primary" data-action="close-modal">К отелям</button></div>`;$('#modal-footer').hidden=true;return;}
 $('#modal-body').innerHTML=`${savedContext(true)}<p class="saved-guidance">Для сравнения выбрано: ${state.compare.length} из 3</p><div class="favorite-list">${entries.map(({hotel:h,offer:o,offers,reason})=>`<article class="favorite-item" data-saved-hotel="${h.id}">${savedHotelPhoto(h,'favorite-photo')}<div class="favorite-info"><span class="favorite-stars">${h.stars?h.stars+' ★':'Категория не указана'}${ratingValue(h)!==null?`<span>${ratingText(h)} / 5</span>`:''}</span><h3><button data-action="hotel-details" data-id="${h.id}">${esc(h.name)}</button></h3><p>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</p></div><button class="icon-button favorite-remove" data-action="favorite" data-id="${h.id}" aria-label="Удалить ${esc(h.name)} из избранного">${icon('x')}</button><div class="favorite-summary">${o?`<div class="favorite-price-line"><strong class="saved-price">${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${esc(o.key)}">Этот тур ${icon('arrow')}</button></div><span class="favorite-party">За ${guestsText(o)}</span><span>${esc(cardPriceNote(o))}</span><p>${dateText(o.day)} → ${dateText(o.returnDay)} · ${nightsText(o.nights)}</p><p>${esc(mealLabel(o))} · ${flightLabel(o)}</p><p class="favorite-room"><span>Номер</span> ${esc(o.room)}</p>`:`<strong class="saved-unavailable">${reason}</strong><p>Отель остаётся в избранном.</p>`}</div><div class="favorite-actions"><button class="compare-btn ${state.compare.includes(h.id)?'active':''}" data-action="toggle-compare" data-id="${h.id}" aria-pressed="${state.compare.includes(h.id)}">${icon(state.compare.includes(h.id)?'check':'compare')}${state.compare.includes(h.id)?'В сравнении':'Сравнить отель'}</button>${o?`${offers.length>1?`<button class="text-button favorite-all-offers" data-action="all-offers" data-id="${h.id}">Все туры отеля (${offers.length})</button>`:''}`:savedRecovery(h)}</div></article>`).join('')}</div>${entries.some(e=>!e.offer)?'<p class="saved-recovery-hint">Новый поиск меняет направление и снимает фильтры. Даты и туристы сохраняются.</p>':''}`;
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<p id="shortlist-status" class="error-text" role="status"></p><button class="secondary" data-action="only-favorites">Избранное в выдаче</button><button class="primary" data-action="compare" ${!state.compare.length?'disabled':''}>Сравнить ${state.compare.length||''} ${icon('arrow')}</button>`;
}
function openFilters(restore=null){if(innerWidth>1100){$('#results').classList.add('filters-requested');$('#filter-panel').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#hotel-query').focus({preventScroll:true});return}if(filterDraft)return;enterUIHistory();const restored=restoreFilterHistoryModel(restore?.draft);expandedFilterSections.clear();const sections=boundedHistoryStrings(restore?.sections,{max:30});if(restored&&sections)sections.forEach(key=>expandedFilterSections.add(key));filterDraft=restored||structuredClone(appliedFilterModel());const budget=restored&&restore?.budget&&typeof restore.budget==='object'?restore.budget:null;if(budget){filterBudgetEdit={filters:filterDraft.filters,scope:searchKey(state.search),appliedMin:filterDraft.filters.min,appliedMax:filterDraft.filters.max,minText:boundedHistoryText(budget.minText,String(filterDraft.filters.min),32),maxText:boundedHistoryText(budget.maxText,String(filterDraft.filters.max??''),32)}}renderFilters();updateDrawerPreview();$('#filter-panel').classList.add('open');$('#filter-panel').setAttribute('role','dialog');$('#filter-panel').setAttribute('aria-modal','true');$('#filter-backdrop').hidden=false;document.body.style.overflow='hidden';$('main>.search-section').inert=true;$('.header').inert=true;$('.results-heading').inert=true;$('.results-main').inert=true;$('.footer').inert=true;$('.mobile-bottom').inert=true;$('#compare-tray').inert=true;$('#compact-search').inert=true;$('#filter-panel').scrollTop=restored&&Number.isFinite(restore?.scroll)&&restore.scroll>=0?restore.scroll:0;$('.mobile-close').focus({preventScroll:true});queueMicrotask(rememberUIRoute);}
function closeFilters({apply=false,fromHistory=false,adapt=false}={}){const wasOpen=$('#filter-panel').classList.contains('open');if(!wasOpen)return;let budget=null;if(apply){budget=editFilterBudget();if(!budget.valid&&!adapt){$(budget.invalidMin?'#min-price':'#max-price').focus();return;}}leaveUIHistory(fromHistory);const next=apply?filterDraft:null;filterDraft=null;drawerSuggestions=[];facetQueries.clear();$('#filter-panel').classList.remove('open');$('#filter-panel').removeAttribute('role');$('#filter-panel').removeAttribute('aria-modal');$('#filter-backdrop').hidden=true;document.body.style.overflow='';$$('[inert]').forEach(e=>e.inert=false);if(next){pageReturn=null;state.filters=next.filters;state.onlyFavorites=next.onlyFavorites;state.selectedDate=next.selectedDate;state.openHotel=null;syncFilters();if(budget&&!budget.valid){$(budget.invalidMin?'#min-price':'#max-price').focus({preventScroll:true})}else{const pristine=!!searchEditSession||!state.hasSearched&&!state.onlyFavorites;$(pristine?'#search':'#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$(pristine?'.search-submit':'#results').focus({preventScroll:true})}}else{renderFilters();restorePageReturn();}}
$('#filter-backdrop').addEventListener('click',closeFilters);
function selectDate(day,fromMonth=false){if(fromMonth&&(day<state.search.from||day>state.search.to)){state.search.from=day;state.search.to=day;draft.from=day;draft.to=day}state.selectedDate=!fromMonth&&state.selectedDate===day?null:day;state.openHotel=null;updateSearchUI();renderResults({keepFilters:true});updateURL();if(fromMonth)closeModal();else $(`#price-strip [data-date="${day}"]`)?.focus({preventScroll:true});}
function resetFilters(){state.filters=defaultFilters();state.onlyFavorites=false;state.selectedDate=null;syncFilters();updateURL();}
function clearSearchTimers(){data.stop();clearTimeout(searchTimer);clearTimeout(searchStageTimer);}
function terminalizeSearchForVerification(){
 if(searchResponse.providers)for(const key of Object.keys(searchResponse.providers))if(searchResponse.providers[key]==='loading')searchResponse.providers[key]='cancelled';
 searchResponse.pending=false;searchResponse.canContinue=false;searchResponse.retryRead=false;searchResponse.phase='cancelled';
}
function stopSearch(){clearSearchTimers();searchLifecycle.invalidate?.();terminalizeSearchForVerification();renderResults({keepFilters:true});}
function renderSearchStatus(items,total){
 const r=searchResponse,node=$('#search-status'),more=$('#search-more'),exactMatch=r.exactRefreshTarget&&items.some(item=>item.offers.some(o=>!needsRefresh(o)&&sameSelectedTourConditions(o,r.exactRefreshTarget))),noCurrent=r.phase==='complete'&&r.exactRefresh&&!exactMatch;
 const providerStates=Object.values(r.providers||{}),providerPending=providerStates.includes('loading'),providerError=providerStates.some(status=>status==='error'||status==='partial'),partialError=providerError||r.databaseError;
 more.hidden=!state.hasSearched||r.pending||!r.canContinue;
 more.innerHTML=more.hidden?'':`<div class="search-status-top"><div><h3>Продолжить подбор</h3><p>${r.resultLimitReached?'Получена большая выборка. Уточните условия, чтобы увидеть другие предложения.':'Запросите ещё варианты. Найденные туры и выбранные фильтры сохранятся.'}</p></div></div><div class="search-status-actions"><button class="primary" data-action="continue-search">${r.retryRead?'Проверить результат':'Продолжить поиск'}</button></div>`;
 node.hidden=!state.hasSearched||r.phase==='complete'&&!noCurrent&&!providerPending&&!partialError;
 node.classList.toggle('search-status-compact',!r.pending&&!providerPending&&items.length>0&&!noCurrent);
 if(node.hidden)return;
 const failed=r.phase==='error'&&!items.length,fallback=r.databaseError&&items.length>0;
 const title=r.coverageGap?'Условия стали шире поиска':noCurrent?'Актуальные варианты пока не найдены':r.pending?(r.continued?'Продолжаем поиск':'Ищем предложения'):providerPending?'Дополняем найденные туры':fallback?'Показаны сохранённые туры':partialError?'Получены не все предложения':r.phase==='cancelled'?'Поиск остановлен':failed?'Туры не загрузились':'Поиск не завершён';
 const message=r.coverageGap?r.message:noCurrent?'Тот же тур — отель, дата, ночи, состав туристов, номер, питание, туроператор и тип перелёта — пока не найден. Другие предложения ниже показаны только как альтернативы и не заменяют сохранённый выбор.':r.pending?(r.message||'Предложения добавляются по мере получения.'):providerPending?'Часть туроператоров ещё отвечает. Уже найденные туры доступны ниже.':fallback?(r.message||'База сейчас не отвечает. Ниже показаны сохранённые предложения; цену и наличие нужно проверить.'):partialError?'Часть предложений сейчас недоступна. Уже найденные туры доступны для выбора; можно повторить поиск.':r.message||'Поиск можно повторить с выбранными условиями.';
 if(node.classList.contains('search-status-compact')){
  node.innerHTML=`<details><summary>${icon('info')} ${esc(title)}<span>Подробнее</span></summary><p>${esc(message)}</p><div class="search-status-actions"><button class="text-button" data-action="retry-search">Повторить поиск</button><button class="text-button" data-action="edit-search">Изменить поиск</button></div></details>`;
  return;
 }
 node.innerHTML=`<div class="search-status-top"><div><h3>${r.pending||providerPending?'<span class="search-progress-spinner" aria-hidden="true"></span>':icon('info')}${title}</h3><p>${esc(message)}</p></div></div><div class="search-status-actions">${r.pending||providerPending?'<button class="secondary" data-action="stop-search">Остановить поиск</button>':partialError||!r.canContinue?'<button class="primary" data-action="retry-search">Повторить поиск</button>':''}<button class="text-button" data-action="edit-search">Изменить условия</button></div>`;
 if(!items.length&&r.pending)$('#cards').innerHTML=Array.from({length:2},()=>'<div class="search-skeleton" aria-hidden="true"><div class="skeleton-photo"></div><div class="skeleton-lines"><i></i><i></i><i></i></div></div>').join('');
}

function prepareSearchRun(options={}){
 if(state.filters.hotelId&&!data.preview&&!destinationHotel(state.filters.hotelId)?.legacyIds.length){state.hasSearched=false;editSearch();renderResults();updateSearchUI();return null;}
 resultCalendar.controller?.abort();resultCalendar={key:null,hotels:[],observations:[],phase:'idle',controller:null};
 selectionGeneration++;providerViews.clear();selectedOffer=null;andromedaQuoteDraft=null;andromedaApplicationDraft=null;anexCurrentDraft=null;demoteSavedTour();removedSelectedTour=null;removedSelectedTourHotel=null;removedSelectedTourObservedAt=0;window.AnyTourPrototypeLead.reset();updateNav();state.hasSearched=true;
 const key=searchKey(state.search);searchResponse={key,phase:'loading',operators:[],pending:true,exactRefresh:options.exactRefresh===true};collapseSearch();
 if(options.exactRefreshTarget)searchResponse.exactRefreshTarget=selectedTourOfferSnapshot(options.exactRefreshTarget);
 return {search:state.search,filters:structuredClone(state.filters),hotelIds:options.hotelIds||(state.filters.hotelId?destinationHotel(state.filters.hotelId)?.legacyIds||[]:[]),response:searchResponse};
}
function mergeSearchResults(event){
 const incoming=new Map(event.hotels.map(h=>[h.id,{...h,offers:h.offers.map(o=>({...o,sourceMeal:o.sourceMeal??o.meal,meal:String(o.meal||'Питание уточняется')}))}]));
 hotels=hotels.filter(h=>state.favorites.includes(h.id)||state.compare.includes(h.id)).map(h=>({...h,offers:[]}));
 hotels=[...new Map([...hotels,...incoming.values()].map(h=>[h.id,h])).values()];
 operators.splice(0,operators.length,...new Set(hotels.flatMap(h=>h.offers.map(o=>o.operator))));
 hotels.forEach(h=>h.offers.forEach(o=>{if(!data.live)mealNames[o.meal]=o.meal;else if(Number.isSafeInteger(o.mealPlanId)&&o.mealPlanId>0&&o.mealFacet){const previous=mealNames[o.mealFacet];if(previous===undefined||previous===o.mealPlanId)mealNames[o.mealFacet]=o.mealPlanId;}}));
 refreshOpenOfferList();
 refreshOpenHotelRooms();
}
function commitSearchDraft(){
 if(currentFilterBudgetEdit(state.filters)){
  const budget=readBudgetFields($('#min-price'),$('#max-price'));
  if(!budget.valid){showFilterBudgetValidity(budget);$(budget.invalidMin?'#min-price':'#max-price').focus();return false;}
 }
 searchEditSession=null;
 const selectedDay=draftSelectedDate();
 draft.origin=$('#origin').value;const countryChanged=draft.country!==state.search.country;state.search=structuredClone(draft);state.hasSearched=true;
 if(countryChanged){state.filters.resorts=[];state.filters.hotelId=0;state.filters.q='';state.onlyFavorites=false;}
 if(draftDestination){state.filters.resorts=[...draftDestination.resorts];state.filters.hotelId=draftDestination.hotelId;state.filters.q='';draftDestination=null;}
 rememberDestination();state.selectedDate=selectedDay;state.openHotel=null;updateURL();renderFilters();
 return {};
}
const searchLifecycle=window.AnyTourPrototypeSearchLifecycleV1.create({
 form:$('#search-form'),
 data,
 events:document,
 canSubmit:()=>!$('.search-submit').disabled,
 supplierFilters:()=>structuredClone(state.filters),
 prepare:prepareSearchRun,
 currentKey:()=>searchKey(state.search),
 onResults:mergeSearchResults,
 afterEvent:()=>{renderResults();updateSearchUI();if(modalType==='dates')refreshCalendarPrices();},
 afterStart:()=>renderResults(),
 afterFailure:()=>renderResults(),
 commit:commitSearchDraft,
 afterSubmit:started=>{if(started)requestAnimationFrame(()=>{$('#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#results').focus({preventScroll:true});});}
});
function runSearch(options={}){return searchLifecycle.run(options);}
searchLifecycle.bind();
// Delegated events retain one registration and the original callback order.
function handleOfferChoiceChange(t){
 if(t.name==='andromeda-outbound'||t.name==='andromeda-return'){rememberAndromedaFlightChoice(t);rememberUIRoute();}
 if(t.id==='hotel-room-meal')renderHotelRooms(+t.dataset.id,t.value);
 if(t.name==='flight-pair'&&flightDraft){flightDraft.id=t.value;updateFlightPreview();}
 if(t.name==='comparison-focus'){selectComparisonVariant(+t.value);}
 if(t.id==='tour-differences-only'){offerView.differencesOnly=t.checked;refreshTourComparison(t.id);}
 if(t.id==='comparison-pair-0'||t.id==='comparison-pair-1'){const side=t.id==='comparison-pair-0'?0:1,other=1-side,old=offerView.pair[side],variant=+t.value;offerView.pair[side]=variant;if(offerView.pair[other]===variant)offerView.pair[other]=old;if(!offerView.pair.includes(offerView.activeVariant))offerView.activeVariant=variant;refreshTourComparison(t.id);}
}
function handleOfferRefinementChange(t){
 if(t.name==='anex-package-choice'){const view=retainedProviderView(selectedOffer);if(view?.type==='anex-quote'&&!view.pending&&!view.error&&view.result?.choices?.some(c=>c.choiceRef===t.value))view.choice=t.value;rememberUIRoute();}
 if(t.id==='compare-differences'){compareView.onlyDifferences=t.checked;refreshSavedView('compare','#compare-differences')}
 if(t.id==='compare-left'||t.id==='compare-right'){const side=t.id==='compare-left'?0:1,other=1-side,old=compareView.pair[side],id=+t.value;compareView.pair[side]=id;if(compareView.pair[other]===id)compareView.pair[other]=old;refreshSavedView('compare','#'+t.id)}
 if(['offer-departure','offer-flight','offer-room','offer-meal','offer-sort'].includes(t.id)){offerView[t.id.replace('offer-','')]=t.value;renderOfferList(true)}
 if(t.id==='compare-offer-day'||t.id==='compare-offer-nights'){const scroll=$('#modal-body').scrollTop;if(t.id==='compare-offer-day')offerView.day=t.value;else offerView.nights=+t.value;renderOfferList();$('#'+t.id)?.focus({preventScroll:true});$('#modal-body').scrollTop=scroll;}
}
function handleResultFilterChange(t){
 if(t.id==='sort'||t.id==='mobile-sort'){if(!['recommended','price','rating'].includes(t.value))return true;state.sort=t.value;renderResults({keepFilters:true});if(t.id==='mobile-sort'){$('#cards').scrollIntoView({behavior:scrollBehavior(),block:'start'});toast(t.options[t.selectedIndex].textContent)}}
 if(t.id==='filter-section-jump'){jumpToFilterSection(t.value);return true;}
 if(t.dataset.filter){const arr=editingFilterModel().filters[t.dataset.filter],idx=arr.indexOf(t.value);if(t.checked&&idx<0)arr.push(t.value);if(!t.checked&&idx>=0)arr.splice(idx,1);filterEdited()}
 if(t.dataset.filter){const host=t.closest('.facet-options');if(host)applyFacetSearch(host);}
 if(t.dataset.filterBool){editingFilterModel().filters[t.dataset.filterBool]=t.checked;filterEdited()}
 if(['min-price','max-price'].includes(t.id))editFilterBudget();
}
function handlePartyMealChange(t){
 if(t.dataset.childAge!==undefined){guestDraft.ages[+t.dataset.childAge]=t.value===''?null:+t.value;if(t.value!==''){t.removeAttribute('aria-invalid');t.removeAttribute('aria-describedby');}if(guestDraft.ages.every(a=>a!==null))$('#guest-error').textContent='';updateGuestSelection();rememberUIRoute();}
 if(t.hasAttribute('data-meal-choice')){if(!t.value)mealDraft=[];else{mealDraft=t.checked?[...new Set([...mealDraft,t.value])]:mealDraft.filter(v=>v!==t.value)}$$('[data-meal-choice]').forEach(c=>c.checked=c.value?mealDraft.includes(c.value):!mealDraft.length);updateMealPicker();rememberUIRoute()}
}
function handleBudgetMealDestinationInput(e){if(['min-price','max-price'].includes(e.target.id))editFilterBudget();if(e.target.id==='budget-min'||e.target.id==='budget-max'){updateBudgetPreview();rememberUIRoute()}if(e.target.id==='meal-query'){updateMealPicker();rememberUIRoute()}if(e.target.id==='destination-query')lookupDestination();}
function handleResultFilterInput(e){if(e.target.id==='hotel-query'){editingFilterModel().filters.q=e.target.value;$('.clear-hotel-query').hidden=!e.target.value;filterEdited()}if(e.target.id==='price-range'){const f=editingFilterModel().filters;f.max=Number(e.target.value)>=Number(e.target.max)?null:Number(e.target.value);if(f.max!==null)f.max=Math.max(f.min,f.max);syncBudgetControls(f);filterEdited()}}
function handleSearchParameterAction(action,b,id){
 switch(action){
 case 'departure':openDeparture();break;
 case 'choose-departure':{const city=b.dataset.value;if(!data.catalog.departures.some(x=>data.text(x)===city))break;const saved=getStored('anytour.departures.v1',[]);saveStored('anytour.departures.v1',[city,...(Array.isArray(saved)?saved:[]).filter(x=>x!==city)].slice(0,3));closeModal();if(city!==draft.origin){$('#origin').value=city;$('#origin').dispatchEvent(new Event('change',{bubbles:true}));}break;}
 case 'destination':openDestination();break;
 case 'retry-destination':lookupDestination();break;
 case 'destination-country':cancelDestinationLookup();destinationResortsExpanded=false;destinationChoice={country:b.dataset.value,resorts:[],hotelId:0};$('#destination-query').value='';renderDestination();loadResorts(destinationChoice.country);break;
 case 'retry-resorts':loadResorts(destinationChoice.country);break;
 case 'destination-all':cancelDestinationLookup();destinationChoice.resorts=[];destinationChoice.hotelId=0;destinationResolvedQuery='';$('#destination-query').value='';renderDestination();break;
 case 'clear-destination-query':cancelDestinationLookup();destinationResolvedQuery='';destinationHotelLimit=destinationHotelPageSize;$('#destination-query').value='';renderDestination();$('#modal-body').scrollTop=0;$('#destination-query').focus({preventScroll:true});break;
 case 'destination-remove':{if(b.dataset.id)destinationChoice.hotelId=0;else destinationChoice.resorts=destinationChoice.resorts.filter(r=>r!==b.dataset.value);renderDestination();break}
 case 'destination-more-hotels':{const next=destinationHotelLimit;destinationHotelLimit+=destinationHotelPageSize;renderDestination();const firstNew=$$('.destination-hotel')[next];firstNew?.focus({preventScroll:true});firstNew?.scrollIntoView({block:'nearest',behavior:'instant'});break}
 case 'toggle-destination-resorts':destinationResortsExpanded=!destinationResortsExpanded;renderDestination();$('[data-action="toggle-destination-resorts"]')?.focus({preventScroll:true});break;
 case 'destination-resort':{const r=b.dataset.value,c=b.dataset.country;if(destinationChoice.country!==c){cancelDestinationLookup();destinationChoice={country:c,resorts:[],hotelId:0};}destinationResolvedQuery=normalizeSearch($('#destination-query').value);destinationChoice.hotelId=0;destinationChoice.resorts=destinationChoice.resorts.includes(r)?destinationChoice.resorts.filter(x=>x!==r):[...destinationChoice.resorts,r];renderDestination();break}
 case 'destination-hotel':{const h=destinationHotel(id);if(!h)return true;cancelDestinationLookup();destinationChoice={country:h.country,resorts:[],hotelId:h.id};$('#destination-query').value='';$('#destination-query').blur();renderDestination();$('#modal-body').scrollTop=0;$('[data-action="apply-destination"]').focus({preventScroll:true});break}
 case 'destination-recent':cancelDestinationLookup();destinationResortsExpanded=false;destinationChoice=structuredClone(recentDestinations()[+b.dataset.value]);$('#destination-query').value='';renderDestination();break;
 case 'apply-destination':draftDestination=structuredClone(destinationChoice);draft.country=destinationChoice.country;closeModal();updateSearchUI();if(!state.hasSearched)renderResults();break;
 case 'retry-hotel-restore':restoreURLHotel();break;
 case 'retry-countries':loadCountries(draft.origin);break;
 case 'dates':openDates();break;case 'meals':openMeals();break;case 'budget':openBudget();break;
 case 'clear-meal-query':$('#meal-query').value='';updateMealPicker();$('#meal-query').focus();break;
 case 'budget-preset':$('#budget-min').value=0;$('#budget-max').value=b.dataset.value;updateBudgetPreview();rememberUIRoute();break;
 case 'budget-adjust-max':$('#budget-max').value=b.dataset.value;updateBudgetPreview();rememberUIRoute();$('[data-action="apply-budget"]').focus({preventScroll:true});break;
 case 'apply-budget':{const budget=readBudget();if(!budget.valid){updateBudgetPreview();$(budget.invalidMin?'#budget-min':'#budget-max').focus();return true}filterBudgetEdit=null;state.filters.min=budget.min;state.filters.max=budget.max;applyQuickFilters();break}
 case 'nights':openNights();break;case 'guests':openGuests();break;case 'calendar':openCalendar();break;
 case 'top':case 'edit-search':editSearch();break;
 case 'cancel-search-edit':cancelSearchEdit();break;
 case 'month-prev':case 'month-next':{const d=dateObj(calendarMonth);d.setUTCMonth(d.getUTCMonth()+(action==='month-next'?1:-1));calendarMonth=iso(d);renderDateCalendar();loadCalendarPrices();rememberUIRoute();break}
 case 'day-pick':{const day=b.dataset.date;if(dateDraft.phase===0){dateDraft.from=day;dateDraft.to=day;dateDraft.phase=1}else{const range={from:day<dateDraft.from?day:dateDraft.from,to:day<dateDraft.from?dateDraft.from:day};if(dateRangeError(range)){$('#date-error').textContent='Между датами — не больше 21 дня. Выберите вторую дату ближе к первой.';break;}dateDraft.from=range.from;dateDraft.to=range.to;dateDraft.phase=0}updateDateSelection();rememberUIRoute();break}
 case 'apply-dates':{const {from,to}=dateDraft;if(!from||!to||from<startDay||to>endDay||from>to||(dateObj(to)-dateObj(from))/86400000>21){$('#date-error').textContent='Выберите корректный диапазон не больше 21 дня между датами.';return true}const refreshResults=dateContext.source==='results'&&(from!==dateContext.search.from||to!==dateContext.search.to);draft.from=from;draft.to=to;if(dateContext.source==='results'){state.search.from=from;state.search.to=to;state.selectedDate=from===to?from:null;state.openHotel=null;renderResults({keepFilters:true})}else if(state.selectedDate){state.selectedDate=null;renderResults({keepFilters:true})}closeModal();updateSearchUI();if(refreshResults)searchLifecycle.requestSubmit();break}
 case 'adults-minus':guestDraft.adults=Math.max(1,guestDraft.adults-1);renderGuests();break;
 case 'adults-plus':guestDraft.adults=Math.min(6,guestDraft.adults+1);renderGuests();break;
 case 'children-minus':guestDraft.ages.pop();renderGuests();break;
 case 'children-plus':if(guestDraft.ages.length<3)guestDraft.ages.push(null);renderGuests();break;
 case 'remove-child':{const index=Number(b.dataset.index);if(!Number.isInteger(index)||index<0||index>=guestDraft.ages.length)break;guestDraft.ages.splice(index,1);renderGuests();const target=$('[data-child-age="'+Math.min(index,guestDraft.ages.length-1)+'"]')||$('[data-action="children-plus"]');target.focus();break;}
 case 'apply-guests':if(guestDraft.ages.some(a=>a===null)){$('#guest-error').textContent='Укажите возраст каждого ребёнка.';$$('[data-child-age]').forEach(el=>{if(el.value===''){el.setAttribute('aria-invalid','true');el.setAttribute('aria-describedby','guest-error');}});$('[data-child-age][aria-invalid="true"]').focus();return true}draft.adults=guestDraft.adults;draft.ages=[...guestDraft.ages];closeModal();updateSearchUI();break;
 case 'night-pick':{const n=+b.dataset.value;if(nightsDraft.phase===1&&Math.abs(n-nightsDraft.min)>10){nightsDraft.error=`Слишком широкий диапазон. Выберите вторую границу от ${Math.max(1,nightsDraft.min-10)} до ${Math.min(28,nightsDraft.min+10)} ночей.`;renderNightSelection();break;}nightsDraft.error='';if(nightsDraft.phase===0){nightsDraft.min=n;nightsDraft.max=n;nightsDraft.phase=1}else{nightsDraft.max=Math.max(nightsDraft.min,n);nightsDraft.min=Math.min(nightsDraft.min,n);nightsDraft.phase=0}renderNightSelection();break}
 case 'night-preset':nightsDraft={min:+b.dataset.value,max:+b.dataset.value,phase:0};renderNightSelection();break;
 case 'apply-nights':draft.minNights=nightsDraft.min;draft.maxNights=nightsDraft.max;closeModal();updateSearchUI();break;
 default:return false;
 }
 return true;
}
function handleResultFilterAction(action,b,id){
 switch(action){
 case 'category-filters':{openFilters();const heading=$('#filters .star-options')?.closest('.filter-group')?.querySelector('h4');if(heading)jumpToFilterSection(heading.id);break;}
 case 'toggle-filter-section':{const group=b.closest('.filter-group');setFilterSectionOpen(group,b.getAttribute('aria-expanded')!=='true');rememberUIRoute();break;}
 case 'clear-facet-query':{const host=b.closest('.facet-options'),input=host.querySelector('[data-facet-search]');facetQueries.delete(host.dataset.facetOptions);input.value='';applyFacetSearch(host);input.focus({preventScroll:true});break;}
 case 'remove-facet-choice':{const host=b.closest('.facet-options'),group=host.dataset.facetOptions,f=editingFilterModel().filters;f[group]=f[group].filter(value=>value!==b.dataset.value);host.querySelectorAll('[data-filter]').forEach(input=>input.checked=f[group].includes(input.value));filterEdited();applyFacetSearch(host);host.querySelector('[data-facet-search]').focus({preventScroll:true});break;}
 case 'clear-hotel-query':editingFilterModel().filters.q='';$('#hotel-query').value='';$('.clear-hotel-query').hidden=true;filterEdited();$('#hotel-query').focus({preventScroll:true});break;
 case 'apply-meals':state.filters.meals=[...mealDraft];applyQuickFilters();break;
 case 'any-stars':state.filters.stars=[];syncFilters();break;
 case 'filters':openFilters();break;case 'close-filters':closeFilters();break;case 'apply-filters':closeFilters({apply:true});break;
 case 'review-filter-recovery':if(filterDraft)jumpToFilterSection('drawer-recovery');break;
 case 'reset':if(filterDraft&&b.closest('#filter-panel')){filterDraft={filters:defaultFilters(),onlyFavorites:false,selectedDate:null};filterEdited(true)}else resetFilters();break;
 case 'all-hotels':state.onlyFavorites=false;renderResults();break;
 case 'star':{const n=+b.dataset.value,a=(b.closest('#filter-panel')?editingFilterModel().filters:state.filters).stars,i=a.indexOf(n);if(i<0)a.push(n);else a.splice(i,1);filterEdited(true);if(filterDraft)$(`#filter-panel [data-action="star"][data-value="${n}"]`)?.focus({preventScroll:true});break}
 case 'preset':state.filters[b.dataset.preset]=!state.filters[b.dataset.preset];syncFilters();break;
 case 'remove-filter':{const model=appliedFilterModel();removeModelFilter(model,b.dataset.key,b.dataset.value);state.onlyFavorites=model.onlyFavorites;state.selectedDate=model.selectedDate;syncFilters();break}
 case 'remove-draft-filter':if(filterDraft){removeModelFilter(filterDraft,b.dataset.key,b.dataset.value);filterEdited(true);$('#filter-panel .mobile-close').focus({preventScroll:true})}break;
 case 'recover-filters':{const choice=(b.dataset.source==='drawer'?drawerSuggestions:emptySuggestions)[+b.dataset.value];if(!choice)break;if(b.dataset.source==='drawer'&&filterDraft){filterDraft=structuredClone(choice.model);filterEdited(true);$('#apply-filters').focus({preventScroll:true})}else{state.filters=structuredClone(choice.model.filters);state.onlyFavorites=choice.model.onlyFavorites;state.selectedDate=choice.model.selectedDate;state.openHotel=null;syncFilters();$('#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#results').focus({preventScroll:true})}break;}
 case 'clear-date':state.selectedDate=null;renderResults({keepFilters:true});updateSearchUI();break;
 case 'select-date':selectDate(b.dataset.date);break;
 default:return false;
 }
 return true;
}
document.addEventListener('change',e=>{const t=e.target;
 handleOfferChoiceChange(t);
 if(t.id==='origin'){draft.origin=t.value;loadCountries(t.value);}
 handleOfferRefinementChange(t);
 if(handleResultFilterChange(t))return;
 handlePartyMealChange(t);
});
document.addEventListener('input',e=>{handleBudgetMealDestinationInput(e);handleResultFilterInput(e);});
document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(!b||b.disabled)return;actionTrigger=b;queueMicrotask(()=>{if(actionTrigger===b)actionTrigger=null;});const action=b.dataset.action,id=+b.dataset.id;
 if(handleSearchParameterAction(action,b,id))return;
 if(handleResultFilterAction(action,b,id))return;
 switch(action){
 case 'choose-flight':openFlightPicker();break;case 'apply-flight':applyFlightPair();break;
 case 'selected-tour':break;
 case 'save-tour-for-later':break;
 case 'selected-tour-details':{const saved=readSelectedTour();if(saved){selectedOffer=saved;renderRealOffer();}break;}
 case 'selected-tour-alternatives':restoreSelectedSearch();break;
 case 'remove-selected-tour':removeSelectedTour();break;
 case 'undo-selected-tour':undoSelectedTour();break;
 case 'retry-search':if(!searchResponse.pending)runSearch({retain:true,exactRefresh:searchResponse.exactRefresh});break;
 case 'continue-search':if(!searchResponse.pending&&searchResponse.canContinue)data.continueSearch();break;
 case 'stop-search':stopSearch();break;
 case 'modal-back':modalBack();break;case 'close-modal':closeModal();break;
 case 'hotel-details':openHotelDetails(id);break;
 case 'change-room':{const previous=modalHistory.at(-1);if(['hotel-details','all-offers'].includes(previous?.type))modalBack();else if(selectedOffer)openAllOffers(selectedOffer.hotelId);break;}
 case 'hotel-gallery':openGallery(id,+b.dataset.value);break;
 case 'hotel-room-offers':openAllOffers(id);offerView.room=b.dataset.room;offerView.meal=b.dataset.meal||'';renderOfferList(true);break;
 case 'hotel-detail-offers':openAllOffers(id);offerView.meal=b.dataset.meal||'';renderOfferList(true);break;
 case 'hotel-section':{const body=$('#modal-body'),target=body.querySelector('#'+CSS.escape(b.dataset.target));if(target){target.focus({preventScroll:true});const top=target.getBoundingClientRect().top-body.getBoundingClientRect().top+body.scrollTop-($('.hotel-section-nav')?.offsetHeight||0)-16;body.scrollTo({top:Math.max(0,top),behavior:scrollBehavior()});}break;}
 case 'clear-compare':state.compare=[];saveStored('anytour.prototype.v18.compare.v1',[]);updateNav();renderResults({keepFilters:true});break;
 case 'card-photo-index':case 'card-photo':{const h=hotels.find(h=>h.id===id);if(!h?.photos.length)break;const idx=action==='card-photo-index'?+b.dataset.value:((state.photoIndexes[id]||0)+(+b.dataset.dir)+h.photos.length)%h.photos.length;state.photoIndexes[id]=idx;$('#hotel-'+id+' .hotel-image').src=photoUrl(h,idx);$('#hotel-'+id+' .photo-index').textContent=idx+1;$$('#hotel-'+id+' .card-thumb').forEach((el,i)=>{el.classList.toggle('active',i===idx);el.setAttribute('aria-pressed',i===idx)});break;}

 case 'toggle-offers':{state.openHotel=state.openHotel===id?null:id;const focusId=id;renderResults({keepFilters:true});const target=$(`[data-action="toggle-offers"][data-id="${focusId}"]`);target?.focus({preventScroll:true});if(state.openHotel)$('#offers-'+id).scrollIntoView({behavior:scrollBehavior(),block:'nearest'});break}
 case 'all-offers':openAllOffers(id);break;
 case 'retry-hotel-details':if(modalType==='hotel-details')renderHotelDetails();break;
 case 'retry-offer-list':if(modalType==='all-offers')renderOfferList(true);break;
 case 'offer-view':if(optionalShortlistEnabled&&offerView&&['list','compare'].includes(b.dataset.value)){offerView.mode=b.dataset.value;renderOfferList();b.focus({preventScroll:true});}break;
 case 'compare-tour':{if(!optionalShortlistEnabled)break;const o=offerFromKey(b.dataset.key);if(o&&offerView?.id===o.hotelId){offerView.mode='compare';offerView.day=o.day;offerView.nights=o.nights;offerView.activeVariant=o.variant;offerView.pair=[];renderOfferList();$('#modal-body').scrollTop=0;($('.comparison-date-disclosure>summary')||$('.comparison-fixed-dates'))?.focus({preventScroll:true});}break;}
 case 'offer-flights':case 'start-tour-flights':openOffer(b.dataset.key,null,true);break;
 case 'offer-group':{const key=b.dataset.value;offerView.open=offerView.open.includes(key)?offerView.open.filter(x=>x!==key):[...offerView.open,key];renderOfferList();$(`[data-action="offer-group"][data-value="${key}"]`).focus({preventScroll:true});break}
 case 'group-more':{const key=b.dataset.value,body=$('#modal-body'),scroll=body.scrollTop,shown=b.parentElement.querySelectorAll('.grouped-offer').length;offerView.limits[key]=(offerView.limits[key]||4)+8;renderOfferList();body.scrollTop=scroll;const firstNew=document.getElementById('group-'+key)?.querySelectorAll('.grouped-offer')[shown];firstNew?.querySelector('[data-action="offer"]')?.focus({preventScroll:true});firstNew?.scrollIntoView({behavior:scrollBehavior(),block:'nearest'});break;}
 case 'remove-offer-filter':if(offerRefinementFields.includes(b.dataset.field)&&offerView){offerView[b.dataset.field]='';renderOfferList(true);$('#offer-count').focus();}break;
 case 'reset-offer-filters':offerView.departure='';offerView.flight='';offerView.room='';offerView.meal='';renderOfferList(true);break;
 case 'more-offers':{const h=hotels.find(h=>h.id===id),offers=hotelOffers(h),off=+b.dataset.offset;$('#all-offers-list').insertAdjacentHTML('beforeend',offers.slice(off,off+30).map(o=>offerHTML(h,o)).join(''));if(off+30>=offers.length)b.remove();else b.dataset.offset=off+30;break}
 case 'offer':openOffer(cardEntryOfferKey(b));break;case 'start-lead':openLeadWithQuote(b.dataset.key);break;case 'confirm-tour':confirmTour();break;case 'andromeda-application-preview':openAndromedaApplicationPreview();break;case 'anex-application-preview':openAnexApplicationPreview();break;case 'apply-andromeda-flights':applyAndromedaFlightChoice();break;case 'anex-additional-prices':applyAnexAdditionalPrices();break;case 'anex-flights':loadAnexFlightInventory();break;case 'anex-package-quote':loadAnexPackageQuote();break;case 'anex-package-calculate':loadAnexPackageQuote(true);break;case 'accept-price':if(verifiedOffer){const accepted=verifiedOffer;verifiedOffer=null;completeTour(accepted);}break;
 case 'gallery':openGallery(id,state.photoIndexes[id]||0);break;case 'gallery-next':case 'gallery-prev':{const count=hotels.find(h=>h.id===gallery.id).photos.length;gallery.index=(gallery.index+(action==='gallery-next'?1:-1)+count)%count;renderGallery();break}
 case 'gallery-index':gallery.index=+b.dataset.value;renderGallery();break;
 case 'favorite':toggleFavorite(id);break;case 'favorites':openFavorites();break;
 case 'toggle-compare':toggleCompare(id);break;case 'compare':openCompare();break;
 case 'only-favorites':state.onlyFavorites=true;closeModal();renderResults();$('#results').scrollIntoView({behavior:scrollBehavior()});break;
 case 'show-hotel':{const h=hotels.find(h=>h.id===id);state.search.country=h.country;draft=structuredClone(state.search);draftDestination=null;state.filters=defaultFilters();state.filters.hotelId=h.id;state.onlyFavorites=false;state.selectedDate=null;state.openHotel=id;closeModal();syncFilters();updateURL();$('#results').scrollIntoView({behavior:scrollBehavior()});break}
 case 'operator-info':toast('Туроператор: '+b.dataset.operator);break;
 case 'about':showModal('about','Версия для проверки','ANYTOUR SEARCH3',aboutDisclosureHTML());break;
 }
});
matchMedia('(max-width:760px)').addEventListener('change',()=>refreshTourComparison());
let comparisonResizeTimer;addEventListener('resize',()=>{if(modalType==='compare'){clearTimeout(comparisonResizeTimer);comparisonResizeTimer=setTimeout(()=>{if(modalType==='compare')refreshSavedView('compare')},100)}});
document.addEventListener('keydown',e=>{
 if(modalType==='gallery'&&['ArrowLeft','ArrowRight'].includes(e.key)){e.preventDefault();gallery.index=(gallery.index+(e.key==='ArrowRight'?1:-1)+hotels.find(h=>h.id===gallery.id).photos.length)%hotels.find(h=>h.id===gallery.id).photos.length;renderGallery()}
 if($('#filter-panel').classList.contains('open')){if(e.key==='Escape'){e.preventDefault();closeFilters()}if(e.key==='Tab'){const els=$$('#filter-panel button:not([disabled]),#filter-panel input,#filter-panel select').filter(el=>el.getClientRects().length),first=els[0],last=els.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}}}
});
let touchStart=null;
$('#modal-body').addEventListener('touchstart',e=>{touchStart=null;if(modalType==='gallery'&&e.touches.length===1&&e.target.closest('.gallery-stage')&&!e.target.closest('button'))touchStart={x:e.touches[0].clientX,y:e.touches[0].clientY,id:gallery.id};},{passive:true});
$('#modal-body').addEventListener('touchcancel',()=>{touchStart=null;},{passive:true});
$('#modal-body').addEventListener('touchend',e=>{const start=touchStart;touchStart=null;if(modalType!=='gallery'||!start||start.id!==gallery.id||!e.changedTouches.length)return;const dx=e.changedTouches[0].clientX-start.x,dy=e.changedTouches[0].clientY-start.y;if(Math.abs(dx)>45&&Math.abs(dx)>Math.abs(dy)*1.5){const count=hotels.find(h=>h.id===gallery.id).photos.length;gallery.index=(gallery.index+(dx<0?1:-1)+count)%count;renderGallery();}},{passive:true});
window.addEventListener('resize',()=>{if(innerWidth>1100&&$('#filter-panel').classList.contains('open'))closeFilters({apply:true,adapt:true});if(modalType==='dates'&&calendarMobile!==(innerWidth<=760)){renderDateCalendar();loadCalendarPrices();}});

function refreshCalendarPrices(){
 refreshCalendarPriceCache();
 $$('#date-calendar .calendar-month').forEach(month=>{
  const cells=[...month.querySelectorAll('.month-day:not([disabled])')],prices=cells.map(b=>calendarPrice(b.dataset.date)),min=Math.min(...prices.filter(p=>p!==null));
  cells.forEach((b,i)=>{const price=prices[i];b.classList.toggle('is-cheap',price!==null&&price===min);b.querySelector('small').textContent=price===null?'—':shortAmount(price);b.setAttribute('aria-label',dateLong(b.dataset.date)+(price===null?', цена пока неизвестна':', от '+money(price)));});
 });
 renderDateSelectionPrice();
}
function loadCalendarPrices(){
 calendarRequest?.abort();calendarObserver?.disconnect();calendarRequest=new AbortController();const controller=calendarRequest,ctx=dateContext,loads=new Map();calendarLoads=loads;
 calendarHotels=[];calendarObservations=[];const snapshots=new Map();refreshCalendarPrices();
 if(!catalogReady){$('.date-choice-tools').setAttribute('aria-busy',String(!catalogError));$('.calendar-price-key>span').textContent=catalogError?'Даты можно выбрать без цены':'Загружаем направления…';return;}
 const legend=()=>{if(controller.signal.aborted||dateContext!==ctx||modalType!=='dates')return;const phases=[...loads.values()],node=$('.calendar-legend'),loading=phases.includes('loading');$('.date-choice-tools').setAttribute('aria-busy',String(loading));$('.calendar-price-key>span').textContent=loading?'Открываем цены…':'Весь тур · тыс. ₽';node.hidden=!phases.includes('error');node.querySelector('span').textContent=phases.includes('error')?'Не все цены загрузились. Даты можно выбрать без цены.':'';renderDateSelectionPrice();};
 const read=async month=>{
  if(loads.has(month)||controller.signal.aborted)return;loads.set(month,'loading');legend();
  const from=month<startDay?startDay:month,next=dateObj(month);next.setUTCMonth(next.getUTCMonth()+1);next.setUTCDate(0);const to=iso(next)>endDay?endDay:iso(next);
  const show=snapshot=>{if(controller.signal.aborted||dateContext!==ctx||modalType!=='dates')return;snapshots.set(month,snapshot);calendarHotels=[...snapshots.values()].flatMap(row=>row.hotels);calendarObservations=[...snapshots.values()].flatMap(row=>row.observations);refreshCalendarPrices();};
  try{const snapshot=await data.calendarPrices(ctx.search,from,to,controller.signal,ctx.filters,show);if(controller.signal.aborted||dateContext!==ctx||modalType!=='dates')return;show(snapshot);loads.set(month,snapshot.partial?'error':'complete');legend();}
  catch(error){if(error.name!=='AbortError'){loads.set(month,'error');legend();}}
 };
 // Desktop displays two months; mobile reads each month as it comes into view.
 if(innerWidth>760)$$('#date-calendar .calendar-month').forEach(m=>read(m.dataset.month));
 else{calendarObserver=new IntersectionObserver(entries=>entries.filter(e=>e.isIntersecting).forEach(e=>read(e.target.dataset.month)),{root:$('#modal-body'),rootMargin:'120px 0px'});$$('#date-calendar .calendar-month').forEach(m=>calendarObserver.observe(m));}
}

function applyCatalog(c){
 if(!c)return;Object.keys(countryNames).forEach(k=>delete countryNames[k]);c.countries.forEach(x=>countryNames[String(x.id)]=data.text(x));
 $('#origin').innerHTML=c.departures.map(x=>`<option value="${esc(data.text(x))}">${esc(data.text(x))}</option>`).join('');
 if(data.live){Object.keys(mealNames).forEach(key=>delete mealNames[key]);data.catalog.mealPlans.filter(plan=>plan.nativeIds?.length).forEach(plan=>{mealNames[plan.nameRu]=plan.id;});}
 else data.catalog.meals.forEach(x=>{const label=data.meal(x);if(label)mealNames[label]=label;});
 draft.origin=c.origin;if(!countryNames[draft.country])draft.country=String(c.countries.find(x=>data.text(x)==='Турция')?.id||c.countries[0].id);
 draftDestination=null;updateSearchUI();
}
async function loadCountries(origin){
 const run=++catalogLoadGeneration;catalogReady=false;catalogError='';catalogDeparture=origin;updateSearchUI();
 if(modalType==='destination')renderDestination();
 try{
  const c=await data.countries(origin);if(!c||run!==catalogLoadGeneration||draft.origin!==origin)return;
  applyCatalog(c);await loadResorts(draft.country);
  if(run!==catalogLoadGeneration||draft.origin!==origin)return;
  catalogReady=true;catalogDeparture='';updateSearchUI();
  if(modalType==='destination'){destinationChoice=structuredClone(currentDraftDestination());lookupDestination();}
  if(modalType==='dates'){dateContext=createDateContext(dateContext.source);renderCalendarScope();loadCalendarPrices();}
 }catch(error){
  if(run!==catalogLoadGeneration||draft.origin!==origin)return;
  catalogError='Не удалось загрузить направления для города «'+origin+'». Выберите другой город или повторите загрузку.';updateSearchUI();
  if(modalType==='destination')renderDestination();
  if(modalType==='dates')loadCalendarPrices();
 }
}
async function restoreSavedHotels(){
 const favorites=validIds(getStored('anytour.prototype.v18.favorites.v1',[])),compare=validIds(getStored('anytour.prototype.v18.compare.v1',[])).slice(0,3),ids=[...new Set([...favorites,...compare])];if(!ids.length)return;const rows=await data.savedHotels(ids,state.search);if(state.hasSearched)return;hotels=rows;state.favorites=favorites.filter(id=>rows.some(h=>h.id===id));state.compare=compare.filter(id=>rows.some(h=>h.id===id));updateNav();
}
async function restoreURLHotel(){
 const id=state.filters.hotelId;if(!id||hotelRestorePending)return;
 hotelRestorePending=true;updateSearchUI();renderResults();
 try{const h=await data.restoreHotel(id,state.search.country);destinationHotels.set(h.id,h);}
 catch{/* Keep the selected ID and offer an explicit retry or reselection. */}
 finally{hotelRestorePending=false;updateSearchUI();renderResults();if(modalType==='destination')renderDestination();}
}
async function bootRealData(){
 catalogReady=false;$('.search-submit').disabled=true;catalogError='';updateSearchUI();if(modalType==='destination')renderDestination();
 try{applyCatalog(await data.init(new URLSearchParams(location.search).get('origin')||draft.origin));state.search=structuredClone(draft);restoreURL();urlStateHydrated=true;await loadResorts(state.search.country);catalogReady=true;updateSearchUI();renderResults();if(modalType==='destination'){if(!countryNames[destinationChoice.country])destinationChoice=structuredClone(currentDraftDestination());lookupDestination();}if(modalType==='dates'){dateContext=createDateContext(dateContext?.source==='results'?'results':'form');renderCalendarScope();loadCalendarPrices();}await restoreURLHotel();if(!data.live)await restoreSavedHotels();$('#fixture-description').textContent=data.describe();if(data.live){const requestedSearch=new URLSearchParams(location.search).get('searched')==='1';state.hasSearched=requestedSearch;renderFilters();renderResults();updateSearchUI();if(requestedSearch)runSearch();}else{state.hasSearched=true;runSearch();}}
 catch(error){catalogError=error.message;$('#cards').innerHTML=`<div class="empty"><h3>Не удалось загрузить направления</h3><p>${esc(error.message)}</p><button class="primary" data-action="retry-catalog">Повторить</button></div>`;if(modalType==='destination')renderDestination();}
}
document.addEventListener('click',event=>{const b=event.target.closest('[data-action]');if(!b||b.disabled)return;if(b.dataset.action==='refresh-hotel')refreshHotel(Number(b.dataset.id));if(b.dataset.action==='retry-flights')loadRealFlights(selectionGeneration,{chooseFlight:true});if(b.dataset.action==='retry-catalog')bootRealData();});
document.addEventListener('error',event=>{const img=event.target;if(img.tagName==='IMG'&&img.classList.contains('hotel-image')){img.closest('.hotel-photos')?.classList.add('photo-unavailable');img.removeAttribute('src');img.alt='Фото пока недоступно';}},true);

async function switchFixture(value){
 if(data.live||value==='live'){clearSearchTimers();location.assign(location.pathname.replace(/index\.html$/,'')+(value==='live'?'':'?scenario='+encodeURIComponent(value)));return;}
 searchEditSession=null;
 clearSearchTimers();closeModal();window.AnyTourPrototypeLead.reset();await data.setScenario(value);hotels=[];destinationHotels.clear();state.search=structuredClone(data.initialSearch);draft=structuredClone(state.search);state.filters=defaultFilters();state.selectedDate=null;state.favorites=[];state.compare=[];state.onlyFavorites=false;state.openHotel=null;state.hasSearched=false;draftDestination=null;
 for(const key of Object.keys(mealNames))delete mealNames[key];operators.splice(0);applyCatalog(await data.init());catalogReady=true;$('#fixture-description').textContent=data.describe();updateSearchUI();state.hasSearched=true;runSearch();
}
window.addEventListener('anytour:data-status',()=>{$('#fixture-description').textContent=data.describe();});
$('#fixture-scenario').value=data.scenario;
$('#fixture-scenario').addEventListener('change',event=>switchFixture(event.target.value).catch(error=>{catalogReady=false;toast(error.message);$('#fixture-description').textContent=error.message;}));
document.addEventListener('click',event=>{if(event.target.closest('[data-action="more-cards"]')){const next=renderedCardLimit;renderedCardLimit+=24;renderResults({keepFilters:true});$('#cards').children[next]?.querySelector('button')?.focus({preventScroll:true});}});

const initialUIRoute=history.state?.[uiHistoryKey];
hydrate();renderResults();bootRealData();
if(initialUIRoute)addEventListener('pageshow',()=>restoreHistoryView(initialUIRoute),{once:true});
// The trip bar follows this document's viewport, including in the responsive preview.
let compactSearchFrame=0;
function updateCompactSearch(){compactSearchFrame=0;$('#compact-search').hidden=$('#search').getBoundingClientRect().bottom>88;}
function scheduleCompactSearch(){if(!compactSearchFrame)compactSearchFrame=requestAnimationFrame(updateCompactSearch);}
addEventListener('scroll',scheduleCompactSearch,{passive:true});
addEventListener('resize',scheduleCompactSearch);
new IntersectionObserver(scheduleCompactSearch,{threshold:0}).observe($('#search'));
updateCompactSearch();
})();
