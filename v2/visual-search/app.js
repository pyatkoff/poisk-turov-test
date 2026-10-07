'use strict';
(() => {
const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
const prototypeVersion=154;
const designRevision=34;
// Optional shortlisting is paused by the owner. Keep stored choices intact, but
// keep every visible route focused on search → hotel → exact tour.
const optionalShortlistEnabled=false;
$('.prototype-lab strong').textContent='Дизайн · пакет '+designRevision;
 $('#prototype-version').textContent='Дизайн · пакет '+designRevision;
$('.footer-inner>span').textContent='Дизайн · пакет '+designRevision+' · 06.10.2026 · исходник v'+prototypeVersion;
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
const calendarAmountFormatter=new Intl.NumberFormat('ru-RU',{useGrouping:false,minimumFractionDigits:1,maximumFractionDigits:1});
const shortAmountFormatter=new Intl.NumberFormat('ru-RU',{maximumFractionDigits:1});
const ratingFormatter=new Intl.NumberFormat('ru-RU',{minimumFractionDigits:1,maximumFractionDigits:1});
const dayFormatter=new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short',timeZone:'UTC'});
const fullDayFormatter=new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'});
const monthFormatter=new Intl.DateTimeFormat('ru-RU',{month:'long',year:'numeric',timeZone:'UTC'});
const money = n => amountFormatter.format(Number(n))+' ₽';
const shortAmount = (n,calendar=false) => calendar?(n>=1000000?String(Math.round(n/1000)):calendarAmountFormatter.format(n/1000)):shortAmountFormatter.format(n/1000);
const shortMoney = n => shortAmount(n)+' тыс.';
const dateObj = s=>new Date(s+'T12:00:00Z');
const iso = d=>d.toISOString().slice(0,10);
const addDays = (s,n)=>iso(new Date(dateObj(s).getTime()+n*86400000));
const formatDate=(formatter,date)=>Number.isNaN(date.getTime())?'Invalid Date':formatter.format(date);
const dateText = s=>formatDate(dayFormatter,dateObj(s)).replace('.','');
const dateLong = s=>formatDate(fullDayFormatter,dateObj(s));
const flightDateText = s=>{const raw=String(s||'');const iso=/^\d{8}$/.test(raw)?`${raw.slice(0,4)}-${raw.slice(4,6)}-${raw.slice(6,8)}`:raw;return /^\d{4}-\d{2}-\d{2}$/.test(iso)&&!Number.isNaN(dateObj(iso).getTime())?dateText(iso):raw||'Дата уточняется';};
const rangeText = (a,b)=>a===b?dateText(a):a.slice(0,4)!==b.slice(0,4)?`${dateText(a)} ${a.slice(0,4)} — ${dateText(b)} ${b.slice(0,4)}`:`${dateText(a)} — ${dateText(b)}`;
const departureRangeText=(a,b)=>a.slice(0,7)===b.slice(0,7)?(a===b?'':Number(a.slice(8))+'–')+dateObj(b).toLocaleDateString('ru-RU',{day:'numeric',month:'long',timeZone:'UTC'}):rangeText(a,b);
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
const flightLabel=o=>o?.flight==='regular'?'Регулярный':o?.flight==='charter'?'Чартер':'';
const needsRefresh=o=>o.cached||(!data.preview&&o.provider!=='tourvisor')||o.raw?.selectionEnabled===false;
const offerActionLabel=o=>data.preview||!needsRefresh(o)?'Выбрать тур':'Смотреть условия';
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
const operatorAssets={'ANEX':'anex.svg','Coral Travel':'coral-travel.png','FUN&SUN':'fun-sun.svg','Библио-Глобус':'biblio-globus.svg','LOTI':'loti.png'};
function operatorBadge(name){const file=operatorAssets[name],label='Туроператор: '+name;return `<button type="button" class="operator-logo ${file?'':'operator-text'}" data-action="operator-info" data-operator="${esc(name)}" aria-label="${esc(label)}" title="${esc(label)}">${file?`<img src="./assets/operators/${file}" alt="">`:esc(name)}<span class="operator-tooltip" aria-hidden="true">${esc(label)}</span></button>`;}
const arrivalCity=h=>data.text(h.raw?.arrival)||h.resort||countryNames[h.country];
function minimumOfferSummary(o){return `<div class="card-minimum-offer${flightLabel(o)?'':' room-operator-inline'}" data-minimum-key="${esc(o.key)}" aria-label="Условия тура по указанной цене"><strong><time datetime="${esc(o.day)}">${dateText(o.day)}</time> → <time datetime="${esc(o.returnDay)}">${dateText(o.returnDay)}</time> · ${nightsText(o.nights)}</strong><p class="card-meal-line">${icon('food')}<span>${esc(mealLabel(o))}</span></p><p class="card-room"><span class="card-room-label">Номер</span> ${esc(o.room)}</p><div class="card-offer-flight">${flightLabel(o)?`<span class="flight-tag ${o.flight}">${flightLabel(o)}</span>`:''}${operatorBadge(o.operator)}</div></div>`;}
function selectionStepsHTML(step,{flightDeferred=false}={}){return `<ol class="selection-steps" aria-label="Этапы выбора тура">${['Номер и питание',flightDeferred?'Перелёт позже':'Перелёт','Заявка'].map((label,i)=>{const deferred=flightDeferred&&i===1;return `<li ${i===step?'aria-current="step"':''} class="${i===step?'current':i<step&&!deferred?'previous':''}"><span aria-hidden="true">${i+1}</span>${label}</li>`;}).join('')}</ol>`;}
const startDay=data.clockStart||addDays(iso(new Date()),1),endDay=addDays(startDay,180);
let hotels=[];
let resultCalendar={key:null,hotels:[],observations:[],basePrices:[],remotePrices:[],observationPrices:[],phase:'idle',controller:null};

const defaultFilters=()=>({hotelId:0,hotelIds:[],q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false});
const getStored=(key,fallback)=>{try{return JSON.parse(localStorage.getItem(key))??fallback}catch{return fallback}};
const saveStored=(key,value)=>{try{localStorage.setItem(key,JSON.stringify(value))}catch{toast('Сохранение в браузере недоступно; выбор останется до перезагрузки.')}};
const validIds=ids=>Array.isArray(ids)?[...new Set(ids.filter(id=>Number.isSafeInteger(id)&&id>0))].slice(0,20):[];
const state={search:structuredClone(data.initialSearch),filters:defaultFilters(),selectedDate:null,sort:'recommended',favorites:[],onlyFavorites:false,hasSearched:false,photoIndexes:{}};
const modalHistory=[];let restoringModal=false;
let draftDestination=null,destinationChoice=null,draft=structuredClone(state.search),modalType='',guestDraft={},nightsDraft={},dateDraft={},calendarMonth=startDay.slice(0,7)+'-01',gallery={id:null,index:0},selectedOffer=null,searchTimer=null,searchStageTimer=null,hotelRoomObserver=null,urlStateHydrated=false;
let searchResponse={key:'',phase:'complete',operators:[...operators],pending:false};
const searchKey=s=>JSON.stringify([s.origin,s.country,s.from,s.to,s.minNights,s.maxNights,s.adults,s.ages]);
const responseFor=s=>searchResponse.key===searchKey(s)?searchResponse:{phase:'complete',operators,pending:false};
const ratingValue=h=>{const rating=h.rating;return Number.isFinite(rating)&&rating>0&&rating<=5?rating:null;};
const ratingText=h=>{const rating=ratingValue(h);return rating===null?'—':ratingFormatter.format(rating);};
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
 state.filters.hotelIds=validIds((p.get('hotels')||'').split('|').map(Number));if(state.filters.hotelIds.length){setDestinationIds(state.filters,state.filters.hotelIds);state.filters.resorts=[];}
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
 if(destinationIds(state.filters).length){setDestinationIds(state.filters,destinationIds(state.filters));state.filters.resorts=[];}
 $('#sort').value=state.sort;
 state.hasSearched=false;draft=structuredClone(s);
}
let compactSearchFrame=0;
let searchEditSession=null,agesNeedReview=false,departureChoice='',destinationPending=null,destinationCountryList=false,ageChoice=null,starsDraft=[],formFiltersDraft=null;
const destinationIds=d=>validIds(d?.hotelIds?.length?d.hotelIds:d?.hotelId?[d.hotelId]:[]);
function setDestinationIds(d,ids){d.hotelIds=validIds(ids);d.hotelId=d.hotelIds.length===1?d.hotelIds[0]:0;}
const pickerFilters=()=>formFiltersDraft||state.filters;
const partyLabel=s=>s.ages.length?`${s.adults} взр. · ${s.ages.length} реб.`:`${s.adults} ${s.adults===1?'взрослый':'взрослых'}`;
function finishFilterPicker(){if(formFiltersDraft){modalBack();renderFormFilters();}else applyQuickFilters();}
function cancelPicker(){if(['child-age','destination-replace'].includes(modalType)||formFiltersDraft&&modalType!=='form-filters')modalBack();else closeModal();}
function updateURL(){if(searchEditSession||!urlStateHydrated)return;const p=new URLSearchParams();p.set('scenario',data.scenario);for(const [k,v] of Object.entries(state.search))p.set(k,Array.isArray(v)?v.join(','):v);if(state.selectedDate)p.set('date',state.selectedDate);if(state.hasSearched)p.set('searched','1');if(state.onlyFavorites)p.set('favorites','1');const f=state.filters;for(const k of ['stars','meals','resorts','operators','flight','amenities'])if(f[k].length)p.set(k,f[k].join('|'));for(const k of ['beach','rating','family','spa'])if(f[k])p.set(k,'1');if(f.hotelId)p.set('hotel',f.hotelId);if(destinationIds(f).length>1)p.set('hotels',destinationIds(f).join('|'));if(f.q)p.set('q',f.q);if(f.min)p.set('min',f.min);if(f.max!==null)p.set('max',f.max);if(state.sort!=='recommended')p.set('sort',state.sort);const query='?'+p.toString();if(location.search!==query||location.hash)history.replaceState(history.state,'',query);}
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
 const keyed=new Map();for(const node of container.children){const key=field==='id'?node.id:field==='day'?node.dataset.date:node.dataset[field];if(key&&!keyed.has(key))keyed.set(key,node);}
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
function appendGeneratedRoots(container,entries,remove){
 const binding=generatedRootBindings.get(container);let settled=!!binding;if(binding)binding.mark(binding.observer.takeRecords());
 if(binding)for(const node of [...container.children])if(node!==remove){const markup=binding.markup.get(node);if(!markup){settled=false;continue}if(binding.dirty.has(node)){const fresh=parseGeneratedRoot(markup);binding.markup.set(fresh,markup);binding.dirty.delete(node);container.replaceChild(fresh,node);}}
 if(remove){binding?.dirty.delete(remove);remove.remove();}
 for(const entry of entries){const node=parseGeneratedRoot(entry.markup);binding?.markup.set(node,entry.markup);container.append(node);}
 binding?.observer.takeRecords();
 return settled;
}
function renderSummary(){const s=state.search;paintGeneratedRoots($('#applied-search'),[{id:'',markup:`<div class="applied-main"><div class="applied-route"><span class="summary-icon">${icon('plane')}</span><div><small>Маршрут</small><strong>${esc(s.origin)}<span>→</span>${esc(destinationLabel(appliedDestination()))}</strong></div></div><dl class="applied-trip"><div><dt>${departureScopeLabel(s)}</dt><dd>${departureScopeValue(s)}</dd></div><div><dt>Отдых</dt><dd>${durationText()}</dd></div><div><dt>Туристы</dt><dd>${guestsText()}</dd></div></dl><button class="secondary" data-action="edit-search" aria-controls="search-form" aria-expanded="false">${icon('sliders')} Изменить поиск</button></div>`}]);}
function collapseSearch(){renderSummary();$('#search-form').hidden=true;$('.intro').hidden=true;$('#applied-search').hidden=false;$('#search').classList.add('search-collapsed');document.body.classList.remove('search-editing','form-visible');updateCompactSearch();}
function editSearch(){
 if(searchResponse.pending||Object.values(searchResponse.providers||{}).includes('loading'))stopSearch();if(innerWidth<=1100)closeFilters();
 $('#results').classList.remove('filters-requested');
 if(state.hasSearched&&!searchEditSession){
  searchEditSession={filters:structuredClone(state.filters),selectedDate:state.selectedDate,onlyFavorites:state.onlyFavorites,y:scrollY,focus:focusReference(actionTrigger||document.activeElement)};
  draft=structuredClone(state.search);draftDestination=null;
 }
 $('#search-return').hidden=!state.hasSearched;$('#search-edit-note').hidden=!state.hasSearched;
 $('#page-title').textContent=state.hasSearched?'Изменить поиск':'Подберём ваш отдых';
 $('#search-form').hidden=false;$('.intro').hidden=false;$('#applied-search').hidden=true;$('#search').classList.remove('search-collapsed');document.body.classList.add('search-editing');
 updateSearchUI();$('#search').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#departure-button').focus({preventScroll:true});
}
function cancelSearchEdit(){
 agesNeedReview=false;
 const saved=searchEditSession,originChanged=draft.origin!==state.search.origin;searchEditSession=null;
 if(saved){state.filters=saved.filters;state.selectedDate=saved.selectedDate;state.onlyFavorites=saved.onlyFavorites;}
 draft=structuredClone(state.search);draftDestination=null;filterBudgetEdit=null;
 updateSearchUI();collapseSearch();renderFilters();updateURL();
 if(originChanged)loadCountries(draft.origin);
 restoreFocus(saved?.focus,innerWidth<=760?$('#compact-search [data-action="top"]:last-child'):$('#applied-search [data-action="edit-search"]'));
 if(saved)requestAnimationFrame(()=>window.scrollTo({top:saved.y,behavior:'instant'}));
}
const hotelPlaces=h=>[...new Set([h.subRegion,h.region,h.resort].map(value=>String(value||'').trim()).filter(Boolean))];
function hotelMatch(h,f=state.filters,s=state.search,onlyFavorites=state.onlyFavorites){
 const places=f.resorts.length?hotelPlaces(h):null;
 return h.country===s.country&&(!destinationIds(f).length||destinationIds(f).includes(h.id))
  &&matchesHotelQuery(h,f.q)
  &&(!f.stars.length||f.stars.includes(h.stars))
  &&(!f.resorts.length||f.resorts.some(resort=>places.includes(resort)))
  &&(f.amenities||[]).every(key=>(h.amenities||[]).some(a=>a.key===key))
  &&(!f.rating||ratingValue(h)>=4.5)&&(!f.beach||h.beach!==null&&h.beach<=150)&&(!f.family||h.family)&&(!f.spa||h.spa)
  &&(!onlyFavorites||s.country!==state.search.country||state.favorites.includes(h.id));
}
function touristAgesKey(ages){
 if(!Array.isArray(ages)||ages.length>3)return null;
 let key=0;for(const age of ages){if(!Number.isInteger(age)||age<0||age>17)return null;key+=4**age;}return key;
}
const touristAgesFallback=ages=>JSON.stringify([...ages].sort());
function hotelOfferPredicate(s,f,from,to){
 const ages=touristAgesKey(s.ages);let fallbackAges=ages===null?touristAgesFallback(s.ages):null;
 const sameAges=o=>{const key=touristAgesKey(o.ages);if(ages!==null&&key!==null)return key===ages;fallbackAges??=touristAgesFallback(s.ages);return touristAgesFallback(o.ages)===fallbackAges;};
 return o=>o.search.origin===s.origin&&o.search.country===s.country&&o.adults===s.adults&&sameAges(o)&&o.day>=from&&o.day<=to&&o.nights>=s.minNights&&o.nights<=s.maxNights&&matchesMeal(o,f.meals)&&o.total>=f.min&&(f.max===null||o.total<=f.max)&&(!f.operators.length||f.operators.includes(o.operator))&&(!f.flight.length||f.flight.includes(o.flight));
}
function hotelOffers(h,options={}){
 const rawMinimum=options.minimumOnly==='raw';
 if(!h)return rawMinimum?null:[];
 const s=options.search||state.search,f=options.filters||state.filters,selected=Object.hasOwn(options,'selectedDate')?options.selectedDate:state.selectedDate;
 const from=options.day||(selected&&!options.ignoreDate?selected:s.from),to=options.day||(selected&&!options.ignoreDate?selected:s.to);
 if(!hotelMatch(h,f,s,options.onlyFavorites??state.onlyFavorites))return rawMinimum?null:[];
 const matches=hotelOfferPredicate(s,f,from,to),compare=(a,b)=>a.total-b.total||a.day.localeCompare(b.day);
 if(options.minimumOnly){let offer=null;const rows=h.offers||[];for(let index=0,length=rows.length;index<length;index++){if(!(index in rows))continue;const row=rows[index];if(matches(row)&&(!offer||compare(row,offer)<0))offer=row;}return rawMinimum?offer:offer?[offer]:[];}
 if(options.firstOnly){const offer=(h.offers||[]).find(matches);return offer?[offer]:[];}
 const offers=(h.offers||[]).filter(matches);
 return options.sort===false?offers:offers.sort(compare);
}
function recommendedHotelScore(h,rating=ratingValue(h)){return (rating??0)+(h.beach!==null&&h.beach<=150?.2:0)+(popularity?.boost(h)||0);}
function recommendedHotelRank(h){const rank=popularity?.rank(h);return Number.isInteger(rank)?rank:Number.MAX_SAFE_INTEGER;}
function sortResultItems(items){
 if(items.length<2)return items;
 if(state.sort==='price'||state.sort==='rating'){
  const rating=state.sort==='rating',direction=rating?-1:1;
  const ranked=items.map((row,index)=>({row,index,key:rating?(row.rating??0):row.offers[0].total}));
  ranked.sort((a,b)=>direction*(a.key-b.key)||a.index-b.index);
  for(let index=0;index<items.length;index++)items[index]=ranked[index].row;
  return items;
 }
 // Rank only this current inventory, so progressive prices/profiles stay fresh.
 const ranked=items.map(row=>({row,score:recommendedHotelScore(row.hotel,row.rating),rank:recommendedHotelRank(row.hotel)}));
 ranked.sort((a,b)=>b.score-a.score||a.rank-b.rank||a.row.offers[0].total-b.row.offers[0].total);
 return ranked.map(item=>item.row);
}
function resultInventory(){
 const rating=!!state.filters?.rating,options=rating?{filters:{...state.filters,rating:false}}:null;
 const inventory=hotels.reduce((value,h)=>{
  const hotelRating=ratingValue(h),rated=hotelRating>=4.5,offers=rating?hotelOffers(h,rated?options:{...options,firstOnly:true}):hotelOffers(h);
  const offerCount=offers.length;if(!offerCount)return value;
  value.ratingCounts.total++;if(rated)value.ratingCounts.rating++;
  if(!rating||rated){value.items.push({hotel:h,offers,rating:hotelRating});value.total+=offerCount;}
  return value;
 },{items:[],total:0,ratingCounts:{total:0,rating:0}});
 inventory.items=sortResultItems(inventory.items);return inventory;
}
function results(){return resultInventory().items;}
function positivePriceMinimum2(first,second){
 let minimum=null;
 if(Number.isFinite(first)&&first>0)minimum=first;
 if(Number.isFinite(second)&&second>0&&(minimum===null||second<minimum))minimum=second;
 return minimum;
}
function positivePriceMinimum3(first,second,third){
 let minimum=positivePriceMinimum2(first,second);
 if(Number.isFinite(third)&&third>0&&(minimum===null||third<minimum))minimum=third;
 return minimum;
}
function minimumKnownPrice(prices){return prices.reduce((minimum,price)=>price!==null&&price<minimum?price:minimum,Infinity);}
function calendarMinimums(days,options={},observations=[]){
 if(!days.length)return[];
 const s=options.search||state.search,f=options.filters||state.filters,span=[...days].sort(),requested=new Set(days),minimums=new Map(),saved=new Map();
 const search={...s,from:span[0],to:span.at(-1)};
 for(const h of options.calendarHotels||hotels)for(const o of hotelOffers(h,{...options,search,day:'',selectedDate:null,ignoreDate:true,onlyFavorites:false,sort:false})){
  if(requested.has(o.day))minimums.set(o.day,Math.min(minimums.get(o.day)??Infinity,o.total));
 }
 // Keep the first saved observation for a date, just as the old find() did.
 if(destinationIds(f).length<2&&data.observationScopeSupported(s,f))for(const point of observations)if(!saved.has(point.date))saved.set(point.date,point.price);
 return days.map(day=>positivePriceMinimum2(minimums.get(day),saved.get(day)));
}
function filterCount(){return destinationIds(state.filters).length+Object.entries(state.filters).filter(([k])=>!['hotelId','hotelIds'].includes(k)).reduce((n,[k,v])=>n+(Array.isArray(v)?v.length:k==='max'?(v!==null?1:0):k==='min'?(v>0?1:0):v?1:0),0);}
function toast(msg){const t=$('#toast');t.textContent=msg;t.hidden=false;clearTimeout(toast.timer);toast.timer=setTimeout(()=>t.hidden=true,3600);}
const draftSelectedDate=()=>draft.from===state.search.from&&draft.to===state.search.to?state.selectedDate:null;
function updateSearchUI(){
 document.body.classList.toggle('form-visible',!$('#search-form').hidden);updateCompactSearch();const hydrationPending=!urlStateHydrated;
 $('#search-form').setAttribute('aria-busy',String(hydrationPending));
 $$('.intro [data-action="filters"],#search-form [data-action="departure"],#search-form [data-action="destination"],#search-form [data-action="dates"],#search-form [data-action="nights"],#search-form [data-action="guests"],#quick-stars button,#quick-meal,#quick-budget').forEach(control=>control.disabled=hydrationPending);
 const count=filterCount();$('#filter-count').textContent=count?`(${count})`:'';
 $('#origin').value=draft.origin;$('#origin-label').textContent=draft.origin;$('#departure-button').setAttribute('aria-label','Город вылета: '+draft.origin);const place=currentDraftDestination();$('#destination-label').textContent=countryNames[place.country]||destinationLabel(place);$('#destination-detail').textContent=destinationIds(place).length?destinationIds(place).map(id=>destinationHotel(id)?.name||'Выбранный отель').join(' · '):place.resorts.join(', ')||'Вся страна';$('#country').title=destinationLabel(place,true);$('#country').setAttribute('aria-label','Направление: '+destinationLabel(place,true));
 $('#dates-label').textContent=draftSelectedDate()?departureRangeText(draftSelectedDate(),draftSelectedDate()):departureRangeText(draft.from,draft.to);$('#nights-label').textContent=durationText(draft);$('#guests-label').textContent=partyLabel(draft);$('#guests-detail').hidden=!draft.ages.length;$('#guests-detail').textContent=draft.ages.map(age=>age===0?'До 1 года':childAgeText(age)).join(' и ');$('#form-ages-recheck').hidden=!agesNeedReview||!draft.ages.length;$('#form-ages-recheck').textContent='Даты или ночи изменены. Проверьте возраст детей на возвращение.';const conflict=destinationIds(place).map(destinationHotel).filter(h=>h&&state.filters.stars.length&&!state.filters.stars.includes(h.stars));$('#form-conflict').hidden=!conflict.length;$('#form-conflict').textContent=conflict.length?`${conflict[0].stars}★ у ${conflict[0].name} · выбраны ${state.filters.stars.map(n=>n+'★').join(' и ')}. Измените категории или выберите другой отель.`:'';
 $$('#quick-stars button').forEach(b=>{const active=b.dataset.action==='any-stars'?!state.filters.stars.length:state.filters.stars.includes(+b.dataset.value);b.setAttribute('aria-pressed',active);b.classList.toggle('active',active)});
 const meals=state.filters.meals.join(' · ');$('#meal-label').textContent=meals||'Любое';$('#quick-meal').setAttribute('aria-label','Питание: '+(meals||'любое'));$('#quick-meal').title=meals||'Любое питание';
 $('#budget-label').textContent=budgetText();
 $('.search-submit').disabled=!catalogReady||!data.preview&&destinationIds(place).some(id=>!destinationHotel(id)?.legacyIds.length);
 renderCatalogError();
}
function renderCatalogError(){
 let node=$('#catalog-error');
 if(!node){node=document.createElement('p');node.id='catalog-error';node.className='error-text';node.setAttribute('role','alert');$('#search-form .search-actions').before(node);}
 const countryFailed=!!catalogError&&!!catalogDeparture,hotelMissing=urlStateHydrated&&!data.preview&&destinationIds(currentDraftDestination()).some(id=>!destinationHotel(id)?.legacyIds.length);
 const restoring=hotelMissing&&hotelRestorePending&&!countryFailed;
 node.hidden=!countryFailed&&!hotelMissing;node.setAttribute('role',restoring?'status':'alert');node.classList.toggle('error-text',!restoring);node.classList.toggle('picker-caption',restoring);
 node.innerHTML=node.hidden?'':countryFailed?`${esc(catalogError)} <button type="button" class="text-button" data-action="retry-countries">Повторить загрузку направлений</button>`:restoring?'Восстанавливаем выбранные отели…':'Не удалось загрузить все выбранные отели. Выбор сохранён. <button type="button" class="text-button" data-action="retry-hotel-restore">Повторить загрузку отелей</button>';
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
 destinationCountryList=false;cancelDestinationLookup();destinationResolvedQuery='';destinationHotelLimit=destinationHotelPageSize;$('#modal-body').scrollTop=0;const q=$('#destination-query').value.trim(),country=destinationChoice.country;
 if(!countryNames[country]||q.length<2){renderDestination();return;}
 const request=new AbortController();destinationRequest=request;destinationLookup={status:'loading',rows:[]};renderDestination();
 const current=()=>destinationRequest===request&&modalType==='destination'&&destinationChoice?.country===country&&$('#destination-query').value.trim()===q;
 destinationTimer=setTimeout(async()=>{const timeout=setTimeout(()=>request.abort(),15000);try{
  const rows=await data.lookupHotels(q,country,request.signal);if(!current())return;
  rows.forEach(h=>destinationHotels.set(h.id,h));destinationLookup={status:'complete',rows,query:normalizeSearch(q),country};
 }catch(error){if(!current())return;destinationLookup={status:'error',rows:[]};}
 finally{clearTimeout(timeout);if(current()){destinationRequest=null;renderDestination();}}
 },180);
}
function appliedDestination(){return {country:state.search.country,resorts:[...state.filters.resorts],hotelId:state.filters.hotelId,hotelIds:destinationIds(state.filters)};}
const resortLoads=new Map();
const destinationResortPreviewLimit=6;
const destinationHotelPageSize=8;
let destinationResortsExpanded=false,destinationHotelLimit=destinationHotelPageSize,destinationResolvedQuery='',destinationMatchItems=[];
async function loadResorts(country){
 if(!country||!countryNames[country])return;
 resortLoads.set(country,'loading');
 try{await data.regions(country);resortLoads.set(country,'complete');}
 catch{resortLoads.set(country,'error');}
 if(modalType==='destination'&&destinationChoice?.country===country)renderDestination();
}
function currentDraftDestination(){return draftDestination||{country:draft.country,resorts:draft.country===state.search.country?[...state.filters.resorts]:[],hotelId:draft.country===state.search.country?state.filters.hotelId:0,hotelIds:draft.country===state.search.country?destinationIds(state.filters):[]};}
function destinationLabel(d,full=false){
 const ids=destinationIds(d),h=destinationHotel(ids[0]),country=countryNames[d.country]||'';
 if(ids.length>1)return full?`${country} · ${ids.map(id=>destinationHotel(id)?.name||'Выбранный отель').join(', ')}`:`${country} · ${ids.length} отеля`;
 if(h)return full?`${h.name}, ${h.resort}, ${countryNames[h.country]||''}`:h.name;
 if(d.hotelId)return hotelRestorePending?'Восстанавливаем отель…':'Выбранный отель недоступен';
 if(d.resorts.length)return full&&country?`${country} · ${d.resorts.join(', ')}`:d.resorts.length===1?d.resorts[0]:`${country?country+' · ':''}${d.resorts.length} ${d.resorts.length<5?'курорта':'курортов'}`;
 return country||(!catalogReady&&!catalogError?'Загружаем направления…':'Выберите направление');
}
function recentDestinations(){const v=getStored('anytour.prototype.v18.destinations.v1',[]);return (Array.isArray(v)?v:[]).filter(d=>d&&countryNames[d.country]&&Array.isArray(d.resorts)&&d.resorts.every(r=>(data.catalog.regions[d.country]||[]).some(row=>row.name===r))&&destinationIds(d).every(id=>destinationHotel(id)?.country===d.country)).slice(0,3);}
function rememberDestination(){const d=appliedDestination(),key=x=>JSON.stringify([x.country,[...x.resorts].sort(),destinationIds(x)]);saveStored('anytour.prototype.v18.destinations.v1',[d,...recentDestinations().filter(x=>key(x)!==key(d))].slice(0,3));}
function restoredDestinationChoice(value){
 const fallback=structuredClone(currentDraftDestination());if(!value||typeof value!=='object')return fallback;
 const country=String(value.country||'');if(!/^[1-9][0-9]*$/.test(country)||catalogReady&&!countryNames[country])return fallback;
 const ids=destinationIds(value).filter(id=>!destinationHotel(id)||destinationHotel(id).country===country),resorts=Array.isArray(value.resorts)?[...new Set(value.resorts.filter(v=>typeof v==='string'&&v.length<=120))].slice(0,20):[];
 return {country,resorts:ids.length?[]:resorts,hotelId:ids.length===1?ids[0]:0,hotelIds:ids};
}
function openDeparture(restore=null){
 departureChoice=data.catalog.departures.some(x=>data.text(x)===restore?.choice)?restore.choice:draft.origin;
 showModal('departure','Город вылета','',`<div class="destination-search"><label class="sr-only" for="departure-query">Город вылета</label>${icon('search')}<input class="input" id="departure-query" type="search" placeholder="Город вылета" autocomplete="off"><button class="icon-button destination-clear" data-action="clear-departure-query" aria-label="Очистить запрос города" hidden>${icon('x')}</button></div><p class="picker-caption">Поиск по городам вылета</p><div id="departure-results"></div>`);
 $('#modal').classList.add('departure-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="picker-summary"><strong id="departure-summary"></strong><small>Остальные параметры сохранятся</small></div><button class="primary picker-apply" data-action="apply-departure"></button>';
 $('#departure-query').value=boundedHistoryText(restore?.query,'');renderDepartures();if(innerWidth>760)$('#departure-query').focus({preventScroll:true});
}
function renderDepartures(){
 const q=normalizeSearch($('#departure-query').value),cities=[...new Set(data.catalog.departures.map(data.text))],rows=names=>names.filter(city=>!q||normalizeSearch(city).includes(q)).map(city=>`<button type="button" class="destination-row" data-action="choose-departure" data-value="${esc(city)}" aria-pressed="${city===departureChoice}"><span>${esc(city)}</span><span class="choice-check ${city===departureChoice?'active':''}">${city===departureChoice?icon('check'):''}</span></button>`).join('');
 const first=rows([draft.origin]),rest=rows(cities.filter(city=>city!==draft.origin));
 $('#departure-results').innerHTML=(first?`<section class="destination-section"><h3>Текущий город</h3>${first}</section>`:'')+(rest?`<section class="destination-section"><h3>Другие города</h3>${rest}</section>`:'')||'<div class="destination-empty" role="status"><h3>Нет совпадений</h3><p>Проверьте название или сократите запрос. Выбранный город сохранён.</p><button class="text-button" data-action="clear-departure-query">Очистить запрос</button></div>';
 $('#departure-summary').textContent=departureChoice;$('[data-action="apply-departure"]').textContent='Выбрать '+departureChoice;$('[data-action="apply-departure"]').disabled=!cities.includes(departureChoice);$('[data-action="clear-departure-query"]').hidden=!q;rememberUIRoute();
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
 cancelDestinationLookup();destinationPending=null;destinationCountryList=restore?.countries===true;destinationResortsExpanded=restore?.expanded===true;destinationHotelLimit=Number.isInteger(restore?.limit)?Math.min(80,Math.max(destinationHotelPageSize,restore.limit)):destinationHotelPageSize;
 destinationResolvedQuery='';destinationChoice=restoredDestinationChoice(restore?.choice);
 showModal('destination','Куда поедем?','',`<div class="destination-search-sticky"><div class="destination-search"><label class="sr-only" for="destination-query">Страна, курорт или отель</label>${icon('search')}<input class="input" type="search" id="destination-query" placeholder="Страна, курорт или отель" autocomplete="off" aria-controls="destination-results"><button class="icon-button destination-clear" data-action="clear-destination-query" aria-label="Очистить поиск направления" hidden>${icon('x')}</button></div><p id="destination-scope" class="picker-caption">Отели — в выбранной стране</p></div><div id="destination-country-context"></div><div id="destination-selection" class="destination-selection"></div><div id="destination-results" aria-live="polite"></div>`);
 $('#modal').classList.add('destination-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="picker-summary"><strong id="destination-summary"></strong><small class="destination-apply-context"></small></div><button class="primary picker-apply" data-action="apply-destination">Выбрать направление</button>';
 $('#destination-query').value=boundedHistoryText(restore?.query,'');
 if(restore&&$('#destination-query').value.trim().length>=2&&!destinationCountryList){const limit=destinationHotelLimit;lookupDestination();destinationHotelLimit=limit;}else renderDestination();
 loadResorts(destinationChoice.country);if(Number.isFinite(restore?.scroll)&&restore.scroll>=0)$('#modal-body').scrollTop=restore.scroll;if(innerWidth>760)$('#destination-query').focus({preventScroll:true});
}
function requestDestination(next,whole=false){
 const old=destinationChoice,changed=old.country!==next.country||old.resorts.length&&destinationIds(next).length||destinationIds(old).length&&next.resorts.length,qualifiers=old.resorts.length||destinationIds(old).length;
 if(qualifiers&&(changed||whole)){
  destinationPending=next;showModal('destination-replace','Изменить направление?','',`<p class="modal-intro">Прежний выбор будет снят:</p><p class="replacement-value">${esc(destinationLabel(old,true))}</p><p class="modal-intro">Новый выбор:</p><p class="replacement-value">${esc(destinationLabel(next,true))}</p><p class="picker-caption">Город, даты, ночи и туристы сохранятся.</p><button class="secondary" data-action="keep-destination">Оставить прежний выбор</button>`);$('#modal').classList.add('destination-replace-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<button class="primary picker-apply" data-action="confirm-destination">Заменить направление</button>';return;
 }
 settleDestinationChoice(next);
}
function settleDestinationChoice(next){
 const changed=destinationChoice.country!==next.country;
 destinationChoice=next;destinationResolvedQuery=normalizeSearch($('#destination-query').value);destinationCountryList=false;
 renderDestination();loadResorts(next.country);if(changed)lookupDestination();
}
function destinationNameMatches(hotel,words){const name=normalizeHotelQuery(hotel.name);return words.every(word=>name.includes(word));}
function rankDestinationHotels(hotels,words){
 const ranked=hotels.map((hotel,index)=>({hotel,index,preferred:destinationNameMatches(hotel,words)}));
 ranked.sort((a,b)=>Number(b.preferred)-Number(a.preferred)||a.index-b.index);
 return ranked.map(item=>item.hotel);
}
function destinationHotelEntries(matches,start=0,limit=destinationHotelLimit){
 const ids=destinationIds(destinationChoice),entries=matches.slice(start,limit).map(h=>({id:'destination-hotel-'+h.id,markup:`<button id="destination-hotel-${h.id}" class="destination-row destination-hotel" data-action="destination-hotel" data-id="${h.id}" aria-pressed="${ids.includes(h.id)}"><span class="destination-hotel-thumb" aria-hidden="true">${icon('image')}${h.photos?.length?`<img class="destination-hotel-image" src="${esc(photoUrl(h))}" alt="" loading="lazy" decoding="async" width="64" height="52">`:''}</span><span class="destination-hotel-label"><strong>${esc(h.name)}</strong><small>Отель · ${esc(h.resort)}, ${esc(countryNames[h.country]||'')}${h.stars?' · '+h.stars+'★':''}</small></span><span class="choice-check">${ids.includes(h.id)?icon('check'):''}</span></button>`}));
 if(matches.length>limit)entries.push({id:'destination-more-hotels',markup:'<button id="destination-more-hotels" class="secondary destination-list-toggle" data-action="destination-more-hotels">Показать ещё отели</button>'});return entries;
}
function renderMoreDestinationHotels(){
 const next=destinationHotelLimit,section=$('#destination-results .destination-section:last-of-type');destinationHotelLimit+=destinationHotelPageSize;
 if(!section||!destinationMatchItems.length){renderDestination();return next;}
 const last=section.lastElementChild,more=last?.matches('[data-action="destination-more-hotels"]')?last:null;
 appendGeneratedRoots(section,destinationHotelEntries(destinationMatchItems,next,destinationHotelLimit),more);rememberUIRoute();return next;
}
function renderDestination(){
 const focus=focusReference(document.activeElement,$('#destination-results'));
 destinationMatchItems=[];
 const d=destinationChoice,q=normalizeSearch($('#destination-query').value),ids=destinationIds(d);
 $('#destination-query').setAttribute('aria-busy',String(destinationLookup.status==='loading'));
 $('[data-action="clear-destination-query"]').hidden=!q;
 $('#destination-scope').textContent='Отели — '+(countryNames[d.country]||'в выбранной стране')+' · страны и курорты можно выбрать по названию';
 $('#destination-country-context').innerHTML=`<div class="destination-current-country"><strong>${q.length>=2?'Отели: ':''}${esc(countryNames[d.country]||'Направление')}</strong><button class="text-button" data-action="destination-countries" aria-expanded="${destinationCountryList}">Изменить</button></div>`;
 $('#destination-selection').hidden=!ids.length&&!d.resorts.length;
 $('#destination-selection').innerHTML=`<div class="destination-selection-heading">ВЫБРАНО</div><div class="destination-selected-resorts">${ids.length?ids.map(id=>`<button data-action="destination-remove" data-id="${id}" aria-label="${destinationHotel(id)?.name?'Убрать отель '+esc(destinationHotel(id).name):'Убрать выбранный отель'}"><span>${esc(destinationHotel(id)?.name||'Выбранный отель')}</span>${icon('x')}</button>`).join(''):d.resorts.map(r=>`<button data-action="destination-remove" data-value="${esc(r)}" aria-label="Убрать курорт ${esc(r)}"><span>${esc(r)}</span>${icon('x')}</button>`).join('')}</div><p class="picker-caption">${ids.length?'Только выбранные отели':'Любой из выбранных курортов'}</p>`;
 let html='';const countryRows=Object.entries(countryNames).filter(([k,n])=>destinationCountryList||q&&normalizeSearch(n).includes(q)).sort((a,b)=>destinationOrder(a[1],b[1]));
 if(countryRows.length)html+=`<section class="destination-section"><h3>Страны</h3>${countryRows.map(([k,n])=>`<button class="destination-row" data-action="destination-country" data-value="${k}"><span><strong>${esc(n)}</strong><small>Страна${k===d.country?' · текущий выбор':''}</small></span><span class="chevron">›</span></button>`).join('')}</section>`;
 if(!q&&!destinationCountryList)html+=`<button class="destination-row" data-action="destination-all" aria-pressed="${!ids.length&&!d.resorts.length}"><span><strong>Вся страна</strong><small>Все курорты и отели ${esc(countryNames[d.country])}</small></span><span class="choice-check">${!ids.length&&!d.resorts.length?icon('check'):''}</span></button>`;
 const groups=resortGroups(q,d.country),visible=q||destinationResortsExpanded?groups:groups.filter((g,i)=>i<destinationResortPreviewLimit||[g.parent,...g.children].some(r=>d.resorts.includes(r.name)));
 if(visible.length&&!destinationCountryList)html+=`<section class="destination-section"><h3>Курорты${q?'':' · '+esc(countryNames[d.country])}</h3>${visible.map(({parent,children})=>`${resortChoiceHTML(parent,d.country,d.resorts,q?'Курорт · '+countryNames[parent.country]:'')}${children.filter(r=>q||d.resorts.includes(r.name)).map(r=>resortChoiceHTML(r,d.country,d.resorts,'Курорт · '+countryNames[r.country])).join('')}`).join('')}${!q&&groups.length>destinationResortPreviewLimit?`<button class="secondary destination-list-toggle" data-action="toggle-destination-resorts" aria-expanded="${destinationResortsExpanded}">${destinationResortsExpanded?'Свернуть список':'Все курорты ('+groups.length+')'}</button>`:''}</section>`;
 const currentRows=destinationLookup.status==='complete'&&destinationLookup.query===q&&destinationLookup.country===d.country?destinationLookup.rows.filter(h=>String(h.country)===String(d.country)):[];
 const words=normalizeHotelQuery(q).split(' ').filter(Boolean),matches=destinationMatchItems=q.length>=2&&!destinationCountryList?rankDestinationHotels([...new Map([...hotels,...destinationHotels.values(),...destinationLookup.rows].filter(h=>String(h.country)===String(d.country)&&matchesHotelQuery(h,q)).concat(currentRows).map(h=>[h.id,h])).values()],words):[];
 if(matches.length)html+='<section id="destination-hotel-results" class="destination-section"></section>';
 if(!q&&!destinationCountryList)html+='<p class="picker-caption">Для отеля введите название выше</p>';
 if(destinationLookup.status==='loading'&&!destinationCountryList)html+=`<p role="status">Ищем отели · ${esc(countryNames[d.country]||'выбранная страна')}…</p>`;
 if(destinationLookup.status==='error')html+='<div class="destination-empty" role="status"><p>Не удалось загрузить отели. Запрос и выбранное направление сохранены.</p><button class="secondary" data-action="retry-destination">Повторить</button></div>';
 $('#destination-results').innerHTML=html||'<div class="destination-empty" role="status"><h3>Совпадений нет</h3><p>Проверьте название или сократите запрос. Выбранное направление сохранено.</p><button class="text-button" data-action="clear-destination-query">Очистить запрос</button></div>';
 if(matches.length)paintGeneratedRoots($('#destination-hotel-results'),[{id:'destination-hotels-heading',markup:`<h3 id="destination-hotels-heading">Отели · ${matches.length} в списке</h3>`},...destinationHotelEntries(matches)],false);
 restoreFocus(focus,null,$('#destination-results'));
 $('#destination-summary').textContent=destinationLabel(d,true);$('.destination-apply-context').textContent=ids.length?'Только выбранные отели':d.resorts.length?'Любой из выбранных курортов':'Все курорты и отели страны';const apply=$('[data-action="apply-destination"]');apply.textContent=ids.length===1?'Выбрать отель':ids.length?'Выбрать отели ('+ids.length+')':d.resorts.length?'Выбрать курорты':'Выбрать страну';apply.disabled=!catalogReady||!countryNames[d.country];rememberUIRoute();
}
function updateNav(){
 $('#favorites-results').hidden=!optionalShortlistEnabled||!state.favorites.length;$('#favorites-results-count').textContent=state.favorites.length;
 $('#selected-tour-nav').hidden=true;$$('.selected-tour-shortcut').forEach(b=>b.hidden=true);$('.mobile-bottom>[data-action="top"]').hidden=false;
 $('#favorites-nav').hidden=!optionalShortlistEnabled;$('#favorites-nav').innerHTML=`${icon('heart')}<span class="nav-label">Избранное</span>${state.favorites.length?`<span class="saved-dot">${state.favorites.length}</span>`:''}`;$('#favorites-nav').setAttribute('aria-label',`Избранное: ${state.favorites.length}`);

}
let filterDraft=null,emptySuggestions=[],drawerSuggestions=[],filterBudgetEdit=null;
const appliedFilterModel=()=>({filters:state.filters,onlyFavorites:state.onlyFavorites,selectedDate:state.selectedDate});
const editingFilterModel=()=>filterDraft||appliedFilterModel();
function matchingHotelPlan(model){const s=model.search||state.search,f=model.filters||state.filters,selected=Object.hasOwn(model,'selectedDate')?model.selectedDate:state.selectedDate,from=model.day||(selected&&!model.ignoreDate?selected:s.from),to=model.day||(selected&&!model.ignoreDate?selected:s.to),matches=hotelOfferPredicate(s,f,from,to),onlyFavorites=model.onlyFavorites??state.onlyFavorites;return h=>hotelMatch(h,f,s,onlyFavorites)&&(h.offers||[]).find(matches)!==undefined;}
const countMatchingHotels=model=>{const matches=matchingHotelPlan(model);return hotels.reduce((count,h)=>count+Number(matches(h)),0);};
function countMatchingHotelGroups(models){const plans=models.map(matchingHotelPlan);return hotels.reduce((counts,h)=>{for(let i=0;i<plans.length;i++)counts[i]+=Number(plans[i](h));return counts;},models.map(()=>0));}
// Facet counts are per hotel, so stop after each requested identity is found.
// Keep the inventory local to this pass: later responses and edits recalculate it.
function facetCountPlan(model,group,values,selectedInventory=null,dynamic=false){
 const supported=['meals','operators','flight','resorts','stars','amenities'].includes(group),hotelFacet=['resorts','stars','amenities'].includes(group),counts=new Map(values.map(value=>[value,0]));
 if(!counts.size&&!(dynamic&&hotelFacet))return counts;
 if(!supported)return new Map(values.map(value=>[value,countMatchingHotels({...model,filters:{...model.filters,[group]:[value]}})]));
 const selectedValues=model.filters[group]||[],filters={...model.filters,[group]:[]},s=model.search||state.search,wanted=new Map(),selectedKeys=new Set();
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
 const visit=h=>{
  if(!h)return;
  if(hotelFacet){
   if(group==='amenities'){
    if(!hotelMatch(h,filters,s,model.onlyFavorites??state.onlyFavorites)||(h.offers||[]).find(matches)===undefined)return;
   }else if(!hotelOffers(h,{...model,filters,firstOnly:true}).length)return;
   let facts;if(group==='resorts')facts=hotelPlaces(h);else if(group==='stars')facts=[h.stars];else{facts=new Set();(h.amenities||[]).forEach(value=>facts.add(value.key));}
   const selectedMatch=group==='amenities'?selectedValues.every(value=>facts.has(value)):!selectedValues.length||selectedValues.some(value=>facts.includes(value));
   if(selectedInventory&&selectedMatch)selectedInventory.count++;
   if(group==='amenities'&&!selectedMatch)return;
   for(const value of new Set(facts))if(counts.has(value))counts.set(value,counts.get(value)+1);
   return;
  }
  if(!hotelMatch(h,filters,s,model.onlyFavorites??state.onlyFavorites))return;
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
 };
 const add=value=>{if(dynamic&&hotelFacet&&!counts.has(value))counts.set(value,0);};
 return {counts,visit,add};
}
function countFacetOptions(model,group,values,selectedInventory=null){
 const plan=facetCountPlan(model,group,values,selectedInventory);if(plan instanceof Map)return plan;
 for(const h of hotels)plan.visit(h);
 return plan.counts;
}
function countFacetOptionGroups(model,groups,selectedInventory=null,discover=null){
 const counts=new Map(),plans=[],plansByGroup=new Map();let selected=false;
 for(const [group,values] of groups){
  const selection=selectedInventory&&!selected?selectedInventory:null,plan=facetCountPlan(model,group,values,selection,!!discover);
  if(plan instanceof Map){counts.set(group,plan);continue;}
  if(selection)selected=true;counts.set(group,plan.counts);plans.push(plan);plansByGroup.set(group,plan);
 }
 for(const h of hotels){
  discover?.(h,(group,value)=>plansByGroup.get(group)?.add(value));
  for(const plan of plans)plan.visit(h);
 }
 return counts;
}
const hotelCountText=n=>`${n} ${n%10===1&&n%100!==11?'отель':n%10>=2&&n%10<=4&&(n%100<12||n%100>14)?'отеля':'отелей'}`;
function filterChipData(model=appliedFilterModel()){
 const f=model.filters,chips=[];
 for(const key of f.amenities||[])chips.push({key:'amenities',value:key,label:amenityNames.get(key)?.label||'Удобство отеля'});
 if(model.selectedDate)chips.push({key:'date',value:'',label:`Вылет ${dateText(model.selectedDate)}`});
 if(model.onlyFavorites)chips.push({key:'favorites',value:'',label:'Только избранное'});
 for(const id of destinationIds(f))chips.push({key:'hotelIds',value:id,label:destinationHotel(id)?.name||'Выбранный отель'});
 if(f.q)chips.push({key:'q',value:'',label:`Отель: ${f.q}`});
 for(const key of ['stars','meals','resorts','operators','flight'])f[key].forEach(value=>chips.push({key,value,label:key==='stars'?value+' ★':key==='flight'?(value==='charter'?'Чартер':'Регулярный рейс'):value}));
 for(const [key,label] of [['beach','Первая линия'],['rating','Рейтинг 4,5+'],['family','Детский клуб'],['spa','Спа-центр']])if(f[key])chips.push({key,value:'',label});
 if(f.min>0||f.max!==null)chips.push({key:'price',value:'',label:budgetLabel(f)});
 return chips;
}
function removeModelFilter(model,key,value){const f=model.filters;if(key==='hotelId'||key==='hotelIds')setDestinationIds(f,destinationIds(f).filter(id=>String(id)!==String(value)));else if(key==='date')model.selectedDate=null;else if(key==='favorites')model.onlyFavorites=false;else if(key==='price'){f.min=0;f.max=null}else if(Array.isArray(f[key]))f[key]=f[key].filter(x=>String(x)!==String(value));else f[key]=key==='q'?'':key==='hotelId'?0:false;}
function minimumHotelOfferTotal(model){
 const options={...model,filters:{...model.filters,max:null},minimumOnly:'raw'},offers=[];
 for(let index=0,length=hotels.length;index<length;index++){if(!(index in hotels))continue;const offer=hotelOffers(hotels[index],options);if(offer)offers.push(offer);}
 if(!offers.length)return null;
 let minimum=Infinity;for(const offer of offers)minimum=Math.min(minimum,offer.total);return minimum;
}
function recoverySuggestions(model){
 const f=model.filters,candidates=[];
 const count=(specs)=>{if(!specs.length)return false;const models=specs.map(spec=>{const next=structuredClone(model);spec.change(next);return next;}),counts=countMatchingHotelGroups(models);for(let i=0;i<specs.length&&candidates.length<3;i++)if(counts[i]){const {key,title,description='Остальные условия сохранятся'}=specs[i];candidates.push({key,title,description,count:counts[i],model:models[i]});}return candidates.length===3;};
 const first=[];
 if(f.max!==null){const minimum=minimumHotelOfferTotal(model);if(minimum!==null&&minimum>f.max){const ceiling=Math.ceil(minimum/1000)*1000;first.push({key:'budget',title:`Бюджет до ${money(ceiling)}`,change:m=>m.filters.max=ceiling});}}
 if(f.min>0)first.push({key:'minimum',title:`Убрать бюджет «от ${money(f.min)}»`,change:m=>m.filters.min=0});
 if(f.q)first.push({key:'q',title:'Убрать поиск по названию',change:m=>m.filters.q=''});
 if(count(first))return candidates;
 const batch=[];
 for(const [key,title] of [['meals','Любое питание'],['stars','Любая категория отеля'],['flight','Любой тип перелёта'],['operators','Любой туроператор'],['beach','Без условия «Первая линия»'],['rating','Без ограничения по рейтингу'],['family','Без условия «Детский клуб»'],['spa','Без условия «Спа-центр»'],['resorts','Все курорты направления'],['hotelIds','Другие отели в направлении']])if(key==='hotelIds'?destinationIds(f).length:Array.isArray(f[key])?f[key].length:f[key]){batch.push({key,title,change:m=>key==='hotelIds'?setDestinationIds(m.filters,[]):m.filters[key]=Array.isArray(f[key])?[]:false});if(batch.length===3){if(count(batch.splice(0)))return candidates;}}
 if(count(batch.splice(0)))return candidates;
 for(const key of f.amenities||[]){batch.push({key:'amenity:'+key,title:`Без условия «${amenityNames.get(key)?.label||'Удобство отеля'}»`,change:m=>m.filters.amenities=m.filters.amenities.filter(x=>x!==key)});if(batch.length===3){if(count(batch.splice(0)))return candidates;}}
 if(count(batch.splice(0)))return candidates;
 if(model.onlyFavorites)batch.push({key:'favorites',title:'Показать и несохранённые отели',change:m=>m.onlyFavorites=false});
 if(model.selectedDate)batch.push({key:'date',title:`Все даты: ${rangeText(state.search.from,state.search.to)}`,description:'В пределах выбранного диапазона вылета',change:m=>m.selectedDate=null});
 if(count(batch))return candidates;
 if(!candidates.length)count([{key:'reset',title:'Сбросить все фильтры',description:'Город, страна, диапазон дат и туристы сохранятся',change:m=>{m.filters=defaultFilters();m.onlyFavorites=false;m.selectedDate=null}}]);
 return candidates;
}
function recoveryHTML(choices,source){return `<div class="recovery-options">${choices.map((c,i)=>`<button class="recovery-choice" data-action="recover-filters" data-source="${source}" data-recovery-key="${c.key}" data-value="${i}"><span><strong>${esc(c.title)}</strong><small>${esc(c.description)}</small></span><span class="recovery-count">${hotelCountText(c.count)} ${icon('arrow')}</span></button>`).join('')}</div>`;}
function syncFilterResetState(model=editingFilterModel()){
 const active=filterChipData(model).length>0||!!currentFilterBudgetEdit(model.filters)&&!readBudgetFields($('#min-price'),$('#max-price')).valid;
 $$('#filter-panel [data-action="reset"]').forEach(button=>{button.disabled=!active;button.setAttribute('aria-label',active?'Сбросить выбранные фильтры':'Сбросить — фильтры не выбраны');});
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
function emptyResultsHTML(){if(!state.hasSearched){if(!urlStateHydrated&&!catalogError)return `<div class="empty pristine-empty" role="status">${icon('search')}<h3>Готовим поиск…</h3><p>Загружаем направления и сохранённые параметры поездки.</p></div>`;const d=currentDraftDestination();if(!data.preview&&destinationIds(d).some(id=>!destinationHotel(id)?.legacyIds.length))return `<div class="empty pristine-empty" role="status">${icon('info')}<h3>${hotelRestorePending?'Восстанавливаем выбранные отели…':'Не удалось восстановить выбранные отели'}</h3><p>Параметры поездки сохранены.${hotelRestorePending?'':' Повторите загрузку или выберите отель заново.'}</p>${hotelRestorePending?'':`<div class="empty-actions"><button class="secondary" data-action="retry-hotel-restore">Повторить загрузку отелей</button><button class="text-button" data-action="destination">Выбрать другой отель</button></div>`}</div>`;return `<div class="empty pristine-empty">${icon('search')}<h3>Начните с параметров поездки</h3><p>Выберите направление, даты и туристов, затем нажмите «Найти туры».</p></div>`;}const model=appliedFilterModel(),chips=filterChipData(model),response=responseFor(state.search);if(response.phase==='error')return '';
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
const {facetQueries,expandedFilterSections,compareMealLabels,syncFilterSections,setFilterSectionOpen,applyFacetSearch,updateFacetCounts,settleFilterRoots,renderFilterNavigation,jumpToFilterSection,renderFilters}=window.AnyTourFilterPanelV1.create({
 $,$$,esc,icon,state,data,editingFilterModel,currentFilterBudgetEdit,readBudgetFields,budgetLabel,normalizeSearch,hotelCountText,countMatchingHotels,countFacetOptions,countFacetOptionGroups,amenityNames,searchKey,budgetScale,mealNames,ratingValue,hotelPlaces,operators,syncFilterResetState,showFilterBudgetValidity,
 getHotels:()=>hotels,getFilterDraft:()=>filterDraft,getViewportWidth:()=>innerWidth
});


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


function budgetScale(f){
 let high=Math.max(1000,f.min,f.max??0);
 for(const h of hotels)for(const o of h.offers||[])if(Number.isFinite(o.total)&&o.total>high)high=o.total;
 return Math.ceil(high/1000)*1000;
}
function syncBudgetControls(f){
 filterBudgetEdit=null;
 $('#min-price').value=f.min||'';$('#max-price').value=f.max??'';
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


function resultCalendarDays(s){const days=[];for(let day=s.from;day<=s.to;day=addDays(day,1))days.push(day);return days;}
function resultCalendarSnapshot(request,snapshot){
 request.hotels=snapshot.hotels;request.observations=snapshot.observations;
 const days=resultCalendarDays(request.search),options={search:request.search,filters:request.filters};
 request.remotePrices=calendarMinimums(days,{...options,calendarHotels:snapshot.hotels},[]);
 request.observationPrices=calendarMinimums(days,{...options,calendarHotels:[]},snapshot.observations);
}
function loadResultCalendar(){
 if(!catalogReady||!state.hasSearched)return;
 const s=structuredClone(state.search),f=state.filters,filters=structuredClone(f);
 const key=JSON.stringify([s,filters]);if(resultCalendar.key===key)return;
 resultCalendar.controller?.abort();const request={key,search:s,filters,hotels:[],observations:[],basePrices:[],remotePrices:[],observationPrices:[],phase:'loading',controller:new AbortController()};resultCalendar=request;
 const show=snapshot=>{if(resultCalendar!==request)return;resultCalendarSnapshot(request,snapshot);renderCalendarStrip();};
 data.calendarPrices(s,s.from,s.to,request.controller.signal,filters,show).then(snapshot=>{
  if(resultCalendar!==request)return;resultCalendarSnapshot(request,snapshot);request.phase=snapshot.partial?'partial':'complete';renderCalendarStrip();
 }).catch(error=>{if(resultCalendar!==request||error.name==='AbortError')return;request.phase='error';renderCalendarStrip();});
}
function resultCalendarModel(){
 const s=state.search,days=[];for(let day=s.from;day<=s.to;day=addDays(day,1))days.push(day);
 const prices=Array.isArray(resultCalendar.basePrices)?resultCalendarPrices(days):calendarMinimums(days,{calendarHotels:[...hotels,...resultCalendar.hotels]},resultCalendar.observations),known=prices.filter(p=>p!==null),min=Math.min(...known),max=Math.max(...known);
 const source=resultCalendar.phase==='loading'?'Открываем сохранённые цены…':resultCalendar.phase==='error'?'База цен временно недоступна · показаны найденные предложения':resultCalendar.phase==='partial'?'Часть базы цен временно недоступна · показаны доступные цены и найденные предложения':calendarSourceLabel();
 const scope=calendarScope({search:s,filters:state.filters});
 return {s,days,prices,min,max,source,scope};
}
function prepareResultCalendarBase(){resultCalendar.basePrices=calendarMinimums(resultCalendarDays(state.search),{calendarHotels:hotels},[]);}
function resultCalendarPrices(days){
 return days.map((_,index)=>positivePriceMinimum3(resultCalendar.basePrices[index],resultCalendar.remotePrices[index],resultCalendar.observationPrices[index]));
}
function calendarStripEntries({days,prices,min,max}){return days.map((day,i)=>{const price=prices[i];return {day,markup:`<button class="date-price ${price!==null&&price===min?'best':''} ${state.selectedDate===day?'selected':''}" data-action="select-date" data-date="${day}" aria-pressed="${state.selectedDate===day}" aria-label="Вылет ${dateLong(day)}${price!==null?', от '+money(price):', цена не сохранена; дату можно выбрать'}${state.selectedDate===day?', выбрано; нажмите ещё раз, чтобы вернуть все даты':''}"><span class="date">${Number(day.slice(-2))}</span><strong>${price===null?'—':shortAmount(price,true)}</strong></button>`};});}
function calendarStripHTML(model){return calendarStripEntries(model).map(entry=>entry.markup).join('');}
function paintCalendarStrip(strip,entries){paintGeneratedRoots(strip,entries,true,'day');}
function renderCalendarStrip(){
 const refreshBase=resultCalendar.refreshBase===true;resultCalendar.refreshBase=false;
 loadResultCalendar();
 if(refreshBase)prepareResultCalendarBase();
 const model=resultCalendarModel(),{s,source,scope}=model;
 $('#calendar-units').textContent=`От, тыс. ₽ · весь тур за ${partyLabel(s)}`;
 const monthName=day=>dateObj(day).toLocaleDateString('ru-RU',{month:'long',timeZone:'UTC'}),fromYear=s.from.slice(0,4),toYear=s.to.slice(0,4);
 const monthRange=s.from.slice(0,7)===s.to.slice(0,7)?`${monthName(s.from)} ${fromYear}`:fromYear===toYear?`${monthName(s.from)} — ${monthName(s.to)} ${toYear}`:`${monthName(s.from)} ${fromYear} — ${monthName(s.to)} ${toYear}`;
 $('#calendar-month-label').textContent=monthRange.charAt(0).toLocaleUpperCase('ru-RU')+monthRange.slice(1);
 $('#calendar-caption').textContent=source;$('#calendar-caption').title=`${scope.destination} · ${guestsText(s)} · ${durationText(s)}${scope.filters.length?' · с выбранными фильтрами':''}`;
 const strip=$('#price-strip');strip.setAttribute('aria-busy',String(resultCalendar.phase==='loading'));
 paintCalendarStrip(strip,calendarStripEntries(model));
 const minimumDay=model.days[model.prices.indexOf(model.min)];
 $('#calendar-minimum-legend').textContent=Number.isFinite(model.min)?`Минимум: ${dateText(minimumDay)} · ${money(model.min)}`:'';
 $('#calendar-minimum-legend').hidden=!Number.isFinite(model.min);
 $('#calendar-selected-date').textContent=state.selectedDate?`Вылет ${dateLong(state.selectedDate)}`:'';
 $('#calendar-selected-date').parentElement.hidden=!state.selectedDate;
 $('#clear-date').hidden=!state.selectedDate;
 refreshEmptyCalendarContext();
}
const mobileDetailFilterKeys=new Set(['amenities','favorites','hotelId','hotelIds','q','resorts','operators','flight','spa']);
function renderActive(ratingCounts){const f=state.filters,chips=filterChipData(),activeFilters=$('#active-filters');
 $('#sort').value=state.sort;$('#mobile-sort').value=state.sort;
 const sortLabel=state.sort==='price'?'Дешевле':state.sort==='rating'?'Рейтинг':'Сортировка';
 $('#mobile-sort-label').textContent=sortLabel;$('.mobile-sort').classList.toggle('active',state.sort!=='recommended');
 activeFilters.innerHTML=chips.map(c=>`<button class="active-filter${mobileDetailFilterKeys.has(c.key)?' mobile-filter-detail':''}" data-action="remove-filter" data-key="${c.key}" data-value="${esc(c.value)}" aria-label="Убрать: ${esc(c.label)}">${esc(c.label)}${icon('x')}</button>`).join('');
 activeFilters.classList.toggle('mobile-filter-details',chips.some(c=>mobileDetailFilterKeys.has(c.key)));
 for(const key of ['beach','rating','family']){$('#'+key+'-chip').classList.toggle('active',f[key]);$('#'+key+'-chip').setAttribute('aria-pressed',f[key])}
 const ratingTotal=ratingCounts.total,ratingCount=ratingCounts.rating,ratingChip=$('#rating-chip');
 ratingChip.hidden=!f.rating&&(ratingCount===0||ratingCount===ratingTotal);
 $('#rating-chip-count').textContent=ratingChip.hidden?'':`· ${ratingCount}`;
 ratingChip.setAttribute('aria-label',`${f.rating?'Убрать фильтр':'Показать'}: рейтинг от 4,5 — ${hotelCountText(ratingCount)}`);
 const n=filterCount();$('#filter-count').textContent=n?`(${n})`:'';$('#mobile-count').textContent=n?`(${n})`:'';$('#drawer-filter-count').textContent=n?`(${n})`:'';return ratingCounts;
}
function hotelHighlightsHTML(h){
 const priority=[3,1,5,8,2],rank=group=>{const index=priority.indexOf(group);return index<0?priority.length:index;};
 const facts=[...(h.amenities||[])].filter(a=>a.label).sort((a,b)=>rank(a.groupId)-rank(b.groupId));
 if(facts.length)return `<ul class="hotel-highlight-list" aria-label="Удобства отеля">${[...new Set(facts.map(a=>a.label))].slice(0,3).map(label=>`<li>${esc(label)}</li>`).join('')}</ul>`;
 const place=plainHotelText(h.raw?.description||h.raw?.place),text=place.length>160?place.slice(0,157).replace(/\s+\S*$/,'')+'…':place;
 const capturedFacts=place.split(' · ').map(label=>label.trim()).filter(Boolean);
 if(capturedFacts.length>1&&capturedFacts.every(label=>label.length<=56))return `<ul class="hotel-highlight-list" aria-label="Особенности отеля">${[...new Set(capturedFacts)].slice(0,3).map(label=>`<li>${esc(label)}</li>`).join('')}</ul>`;
 return text?`<span>${esc(text)}</span>`:'';
}
function cardHTML({hotel:h,offers,rating=ratingValue(h)}){const o=offers[0],ratingLabel=rating===null?'—':ratingFormatter.format(rating),photoIndex=state.photoIndexes[h.id]||0,popularityBadge=popularity?.badge(h)||'',shortlistActions=optionalShortlistEnabled?`<button class="favorite-button ${state.favorites.includes(h.id)?'active':''}" data-action="favorite" data-id="${h.id}" aria-pressed="${state.favorites.includes(h.id)}" aria-label="${state.favorites.includes(h.id)?'Убрать из избранного':'В избранное'}: ${esc(h.name)}">${icon('heart')}</button>`:'';return `<article class="hotel-card" id="hotel-${h.id}" data-hotel-id="${h.id}"><div class="hotel-main">
 <div class="hotel-photos ${h.photos.length?'':'photo-unavailable'}"><div class="hotel-image-wrap">${h.photos.length?'':`<span class="photo-missing-label">${icon('image')}Нет фотографий</span>`}<button class="hotel-image-button" data-action="gallery" data-id="${h.id}" ${h.photos.length?'':'disabled'} aria-label="${h.photos.length?'Открыть фотографии':'Фото пока недоступны:'} ${esc(h.name)}"><img class="hotel-image" src="${esc(photoUrl(h,photoIndex))}" alt="Фото отеля ${esc(h.name)}" loading="lazy" width="700" height="500"><span class="photo-count">${icon('image')} <span class="photo-index">${h.photos.length?photoIndex+1:0}</span> / ${h.photos.length}</span></button>${shortlistActions}<button class="card-photo-arrow prev" data-action="card-photo" data-id="${h.id}" data-dir="-1" aria-label="Предыдущее фото ${esc(h.name)}">${icon('back')}</button><button class="card-photo-arrow next" data-action="card-photo" data-id="${h.id}" data-dir="1" aria-label="Следующее фото ${esc(h.name)}">${icon('arrow')}</button></div><div class="card-thumbs">${h.photos.slice(0,h.photos.length>4?3:4).map((p,i)=>`<button class="card-thumb ${photoIndex===i?'active':''}" data-action="card-photo-index" data-id="${h.id}" data-value="${i}" aria-label="Показать фото ${i+1} отеля ${esc(h.name)}" aria-pressed="${photoIndex===i}"><img src="${esc(p)}" alt="" loading="lazy" width="150" height="100"></button>`).join('')}${h.photos.length>4?`<button class="card-more-photos" data-action="gallery" data-id="${h.id}" aria-label="Все ${h.photos.length} фотографий отеля ${esc(h.name)}">${icon('image')}<span>Все ${h.photos.length}</span></button>`:''}</div></div>
 <div class="hotel-info"><div class="hotel-info-top"><div>${popularityBadge?`<span class="hotel-popularity-badge" title="Входит в TOP500 продаваемых отелей">${esc(popularityBadge)}</span>`:'' }${hotelStarsHTML(h)}<h3><button data-action="hotel-details" data-id="${h.id}" title="${esc(h.name)}"><span class="hotel-card-name">${esc(h.name)}</span>${icon('arrow')}</button></h3><div class="hotel-location">${icon('pin')} ${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</div></div>${rating!==null?`<div class="rating-block" aria-label="Оценка гостей ${ratingLabel} из 5"><strong>${ratingLabel}<small>/ 5</small></strong><span>${rating>=4.5?'Отлично':'Оценка гостей'}</span></div>`:''}</div><div class="hotel-facts">${hotelHighlightsHTML(h)}</div></div></div>
 ${minimumOfferSummary(o)}<div class="hotel-price ${o.total>=1000000?'price-wide':''}"><div class="starting-price"><span>За ${guestsText(o)} · весь тур</span><strong>${money(o.total)}</strong></div><span class="fuel-note">${icon('info')} ${cardPriceNote(o)}</span><button class="primary" data-action="offer" data-card-entry="true" data-key="${esc(o.key)}" aria-label="Смотреть тур: ${esc(h.name)}"><span>Смотреть тур</span></button></div><div class="hotel-more"><button class="text-button" data-action="hotel-details" data-id="${h.id}" data-target="hotel-about-heading">Об отеле</button>${offers.length>1?`<button class="secondary card-all-offers" data-action="all-offers" data-id="${h.id}">Все туры (${offers.length}) ${icon('arrow')}</button>`:''}</div></article>`;}


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
function renderHotelRooms(id,meal='',restoredRooms=null,initialOffers=null){
 if(!window.AnyTourHotelDetails?.create||modalType!=='hotel-details'||!$('#hotel-room-count')||Number($('.hotel-section-nav')?.dataset.hotelId)!==id)return;
 hotelDetailsOwner().renderHotelRooms(id,meal,restoredRooms,initialOffers);
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
  const initialOffers=hotelDetailsOwner().openHotelDetails(view.id);
  const restored=view.restored;view.restored=null;
  if(restored){renderHotelRooms(view.id,initialOffers.some(o=>o.meal===restored.meal)?restored.meal:'',Array.isArray(restored.rooms)?restored.rooms:null,initialOffers);
   for(const room of $$('.hotel-room-card[data-room]'))if(Array.isArray(restored.more)&&restored.more.includes(room.dataset.room)){const more=room.querySelector('.hotel-room-more');if(more)more.open=true;}
   if(Number.isFinite(restored.scroll)&&restored.scroll>=0)$('#modal-body').scrollTop=restored.scroll;
  }
  rememberUIRoute();
 };
 if(window.AnyTourHotelDetails?.create){render();return;}
 $('#hotel-details-load').innerHTML='<p role="status">Загружаем подробности отеля…</p>';
 loadHotelDetails().then(render).catch(()=>{if(current())$('#hotel-details-load').innerHTML='<div role="alert"><p>Не удалось загрузить подробности отеля.</p><button class="secondary" data-action="retry-hotel-details">Попробовать ещё раз</button></div>';});
}

let renderedCardLimit=24,renderedCardScope='',renderedResultItems=[];
function refreshResultFilters(options,ratingCounts){
 const ratingCount=filterDraft?undefined:ratingCounts?.rating;
 if(!options.keepFilters){if(ratingCount===undefined)renderFilters();else renderFilters(ratingCount);}else{if(ratingCount===undefined)updateFacetCounts();else updateFacetCounts(ratingCount);syncFilterResetState();}
}
function refreshResultPickerPreviews(){
 if(filterDraft)updateDrawerPreview();if(modalType==='budget')updateBudgetPreview();if(modalType==='meals')updateMealPicker(updateMealCounts());
}
function renderResultHeadings(items,total,pristine){
 $('#results').classList.toggle('results-pristine',pristine);document.body.classList.toggle('results-pristine-active',pristine);
 $('#compact-route').textContent=`${state.search.origin} → ${destinationLabel(appliedDestination())}`;
 $('#compact-details').textContent=`Вылет ${departureScopeValue()} · ${durationText()} · ${partyLabel(state.search)}`;

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
function renderMoreResultCards(){
 const next=renderedCardLimit;renderedCardLimit+=24;
 const entries=renderedResultItems.slice(next,renderedCardLimit).map(item=>({id:`hotel-${item.hotel.id}`,markup:cardHTML(item)}));
 if(renderedResultItems.length>renderedCardLimit)entries.push({id:'',markup:`<button type="button" class="secondary load-more-cards" data-action="more-cards">Показать ещё ${Math.min(24,renderedResultItems.length-renderedCardLimit)} отеля <span>Показано ${Math.min(renderedCardLimit,renderedResultItems.length)} из ${renderedResultItems.length}</span></button>`});
 const cards=$('#cards'),last=cards.lastElementChild,more=last?.matches('[data-action="more-cards"]')?last:null;appendGeneratedRoots(cards,entries,more);return next;
}
function renderResults(options={}){
 // A changed form is a draft, not a new result set. Keep cards, pagination and
 // the shareable URL intact until Search; child pickers may still preview counts.
 if(searchEditSession){
  refreshResultFilters(options);refreshResultPickerPreviews();
  return;
 }
 if(searchResponse.key&&searchResponse.key!==searchKey(state.search)){clearSearchTimers();searchResponse={key:searchKey(state.search),phase:'complete',operators:[...operators],pending:false};}
 const inventory=resultInventory(),items=inventory.items,total=inventory.total,pristine=!state.hasSearched&&!state.onlyFavorites;renderedResultItems=items;
 renderResultHeadings(items,total,pristine);
 renderResultCards(items);
 $('#apply-filters').textContent=pristine?'Сохранить условия':`Показать отели (${items.length})`;
 resultCalendar.refreshBase=true;renderCalendarStrip();renderActive(inventory.ratingCounts);updateNav();renderSummary();updateURL();refreshResultFilters(options,inventory.ratingCounts);renderSearchStatus(items,total);refreshResultPickerPreviews();
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
 return {filters:{hotelId:f.hotelId,hotelIds:destinationIds(f),q:f.q,stars:[...f.stars],meals:[...f.meals],resorts:[...f.resorts],operators:[...f.operators],flight:[...f.flight],amenities:[...f.amenities],min:f.min,max:f.max,rating:f.rating,beach:f.beach,family:f.family,spa:f.spa},onlyFavorites:model.onlyFavorites===true,selectedDate:model.selectedDate||null};
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
 return {filters:{hotelId,hotelIds:validIds(f.hotelIds||[]),q,stars,meals,resorts,operators,flight,amenities,min,max,...Object.fromEntries(flags.map(key=>[key,f[key]]))},onlyFavorites:value.onlyFavorites,selectedDate:selected};
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
 if($('#filter-panel').classList.contains('open')){const budget=currentFilterBudgetEdit(filterDraft?.filters);return {type:'filters',draft:filterHistorySnapshot(filterDraft),budget:budget?{minText:budget.minText,maxText:budget.maxText}:null,sections:[...expandedFilterSections],scroll:$('#filter-panel').scrollTop,overviewScroll:Number($('#filter-panel').dataset.overviewScroll)||0};}
 const type=modalType;
 if(['andromeda-flights','andromeda-verified','provider-application','anex-current','anex-additional','anex-quote','anex-application'].includes(type))return providerHistoryRoute(type);
 if(type==='departure')return {type,choice:departureChoice,query:$('#departure-query')?.value||''};
 if(type==='destination')return {type,choice:{country:destinationChoice?.country,resorts:[...(destinationChoice?.resorts||[])],hotelId:destinationChoice?.hotelId||0,hotelIds:destinationIds(destinationChoice)},countries:destinationCountryList,query:$('#destination-query')?.value||'',resolvedQuery:destinationResolvedQuery,expanded:destinationResortsExpanded,limit:destinationHotelLimit,scroll:$('#modal-body').scrollTop};
 if(type==='child-age')return {type,guest:structuredClone(guestDraft),choice:{...ageChoice}};
 if(type==='form-filters')return {type,filters:structuredClone(formFiltersDraft),scroll:$('#modal-body').scrollTop};
 if(type==='stars')return {type,stars:[...starsDraft]};
 if(type==='guests')return {type,draft:{adults:guestDraft.adults,ages:[...(guestDraft.ages||[])]}};
 if(type==='nights')return {type,draft:{min:nightsDraft.min,max:nightsDraft.max,phase:nightsDraft.phase===1?1:0}};
 if(type==='flights'){const o=flightDraft?.base;return {type:'offer',key:o?.key,flightChoiceId:o?.flightChoiceId};}
 if(type==='selected-tour')return {type,key:selectedOffer?.key,scroll:$('#modal-body').scrollTop};
 if(type==='offer')return {type,key:selectedOffer?.key,flightChoiceId:selectedOffer?.flightChoiceId};
 if(type==='all-offers')return {type,id:offerView?.id,departure:offerView?.departure,flight:offerView?.flight,room:offerView?.room,meal:offerView?.meal,sort:offerView?.sort,open:[...(offerView?.open||[])],limits:{...(offerView?.limits||{})},filtersOpen:$('.offer-filter-disclosure')?.open===true,scroll:$('#modal-body').scrollTop};
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
  if(route.type==='provider-application'&&view.type!=='andromeda-verified'&&!(view.type==='andromeda-flights'&&view.result?.repricing?.enabled===true&&view.result?.state==='quote_verified'&&!view.pending)||route.type==='anex-application'&&view.type!=='anex-additional'&&!(view.type==='anex-quote'&&view.result?.state==='quote_verified'))return false;
  if(view.type==='andromeda-flights'){selectedOffer=view.offer;renderRealOffer();}
  if(!restoreProviderView(o))return false;
  if(modalType==='andromeda-flights'&&!view.pending&&view.result?.repricing?.enabled!==true)for(const [name,value] of [['andromeda-outbound',route.outbound],['andromeda-return',route.inbound]]){const input=$$('[name="'+name+'"]').find(el=>el.value===value);if(input){input.checked=true;rememberAndromedaFlightChoice(input);}}
  if(modalType==='anex-quote'&&!view.pending&&!view.error&&view.result?.repricing?.enabled!==true){const input=$$('[name="anex-package-choice"]').find(el=>el.value===route.choice);if(input){input.checked=true;retainedProviderView(o).choice=input.value;}}
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
 case 'child-age':guestDraft=restoredGuestDraft(route.guest);openChildAge(route.choice?.index,route.choice?.value);break;
 case 'form-filters':openFormFilters(route);break;
 case 'stars':openStars(route);break;
 case 'guests':openGuests(route);break;
 case 'nights':openNights(route);break;
 case 'dates':openDates(route.source==='results'?'results':'form',route);if(Number.isFinite(route.scroll)&&route.scroll>=0)$('#modal-body').scrollTop=route.scroll;break;
 case 'meals':openMeals(route);break;
 case 'budget':openBudget(route);break;
 case 'andromeda-flights':case 'andromeda-verified':case 'provider-application':case 'anex-current':case 'anex-additional':case 'anex-quote':case 'anex-application':{
  if(!restoreProviderHistoryRoute(route))return false;
  break;
 }
 case 'selected-tour':{
  if(!route.key||selectedOffer?.key!==route.key)return false;
  openLeadPreview();if(Number.isFinite(route.scroll)&&route.scroll>=0)$('#modal-body').scrollTop=route.scroll;break;
 }
 case 'favorites':openFavorites();break;
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
  else if($('#filter-panel').classList.contains('filter-detail-open')){handlingUIBack=true;setFilterSectionOpen($('.filter-operator-group'),false);handlingUIBack=false;history.pushState({...history.state,[uiHistoryKey]:uiRoute()},'',location.href);}
  else if($('#modal').open)closeModal({fromHistory:true});
  else closeFilters({fromHistory:true});
  updateURL();return;
 }
 if(e.state?.[uiHistoryKey]){restoreHistoryView(e.state[uiHistoryKey]);return;}
 updateURL();
});
function updateModalBack(){
 const previous=modalHistory.at(-1),button=$('#modal-back');button.hidden=!previous;
 const labels={'hotel-details':'К отелю','all-offers':'К вариантам тура',offer:'К деталям тура',flights:'К перелётам',gallery:'К фотографиям',favorites:'В избранное','selected-tour':'К выбранному туру'};
 const label=labels[previous?.type]||'Назад';
 button.setAttribute('aria-label',label);button.title=previous?label+': '+previous.title:label;
 $('#modal-back-label').textContent=label;
}
function captureModalStep(m){
 return {type:modalType,title:$('#modal-title').textContent,kicker:$('#modal-kicker').textContent,body:$('#modal-body').innerHTML,footer:$('#modal-footer').innerHTML,footerHidden:$('#modal-footer').hidden,className:m.className,scroll:$('#modal-body').scrollTop,gallery:{...gallery},offer:selectedOffer,route:uiRoute(),focus:focusReference(actionTrigger||document.activeElement,m)};
}
function restoreModalStepSnapshot(previous){
 $('#modal').className=previous.className;$('#modal-footer').innerHTML=previous.footer;$('#modal-footer').hidden=previous.footerHidden;gallery=previous.gallery;
 // The offer list is a passive view: returning to it must not undo chosen flights.
 if(previous.type!=='all-offers')selectedOffer=previous.offer;
 updateModalBack();
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
 hotelRoomObserver?.disconnect();hotelRoomObserver=null;const m=$('#modal'),newStep=!m.open||modalType!==type;
 if(!m.open){modalHistory.length=0;enterUIHistory();}
 else if(!restoringModal&&modalType!==type){modalHistory.push(captureModalStep(m));}
 modalType=type;updateModalBack();m.className=type==='gallery'?'gallery-dialog':wide?'wide-dialog':type==='dates'?'dates-dialog':'';$('#modal-title').textContent=title;$('#modal-kicker').textContent=kicker;m.classList.toggle('form-picker', ['departure','destination','destination-replace','dates','nights','guests','child-age','meals','budget','stars','form-filters'].includes(type));$('#modal-body').innerHTML=body;$('#modal-footer').innerHTML='';$('#modal-footer').hidden=true;$('#modal-body').scrollTop=0;
 if(!m.open)m.showModal();document.body.style.overflow='hidden';m.scrollTop=0;$('#modal-body').scrollTop=0;hydrate();syncDestinationViewport();if(newStep&&!restoringModal)$('#modal-title').focus({preventScroll:true});queueMicrotask(rememberUIRoute);
}
function closeModal({fromHistory=false}={}){
 selectionGeneration++;calendarRequest?.abort();calendarObserver?.disconnect();hotelRoomObserver?.disconnect();hotelRoomObserver=null;cancelDestinationLookup();
 const m=$('#modal');if(!m.open)return;
 leaveUIHistory(fromHistory);modalType='';formFiltersDraft=null;destinationPending=null;modalHistory.length=0;m.close();document.body.style.overflow=$('#filter-panel').classList.contains('open')?'hidden':'';restorePageReturn();
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
 if(previous.type==='guests')renderGuests();if(previous.type==='destination'){$('#destination-query').value=previous.route?.query||'';renderDestination();}if(previous.type==='form-filters')renderFormFilters();
 if(previous.type==='hotel-details')restoreHotelDetailStep(previous);
 restoringModal=false;if(previous.type==='all-offers'&&offerView)renderOfferList();if(previous.type==='favorites')renderFavorites();if(previous.type==='selected-tour')window.AnyTourPrototypeLead.bind(selectedOffer);
 restoreModalStepFocus(previous);
 syncHotelSectionNavigation();rememberUIRoute();
}
$('#modal').addEventListener('cancel',e=>{e.preventDefault();cancelPicker();});
$('#modal').addEventListener('click',e=>{if(e.target===$('#modal')){const r=e.target.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closeModal()}});
let calendarHotels=[],calendarObservations=[],calendarRequest=null,calendarObserver=null,calendarMobile=null,calendarLoads=new Map(),calendarBasePrices=new Map(),calendarMonthPrices=new Map(),calendarPriceContext=null,calendarScopeTogglePending=false;
let dateContext=null,datePrices=new Map(),mealDraft=[];
function budgetLabel(f){return f.max===null?(f.min?'От '+money(f.min):'Без ограничений'):f.min?`${money(f.min)} — ${money(f.max)}`:'До '+money(f.max);}
const budgetText=()=>budgetLabel(state.filters);
function createDateContext(source='form'){
 const s=source==='results'?state.search:draft,filters=structuredClone(state.filters);
 if(source==='form'&&draftDestination){filters.resorts=[...draftDestination.resorts];filters.hotelId=draftDestination.hotelId;filters.hotelIds=destinationIds(draftDestination);filters.q='';}else if(s.country!==state.search.country){filters.resorts=[];filters.hotelId=0;filters.hotelIds=[];filters.q='';}
 return {source,search:structuredClone(s),filters};
}
const dateContextLabel=s=>`Вылет: ${s.origin} · ${guestsText(s)} · ${durationText(s)}`;
function calendarScope(ctx){
 const place={country:ctx.search.country,hotelId:ctx.filters.hotelId,hotelIds:destinationIds(ctx.filters),resorts:ctx.filters.resorts};
 return {destination:destinationLabel(place),fullDestination:destinationLabel(place,true),filters:filterChipData({filters:ctx.filters}).filter(chip=>!['hotelId','hotelIds','resorts'].includes(chip.key))};
}
const calendarSourceLabel=()=>data.scenario==='live'?'Ранее найденная цена':data.scenario==='snapshot'?'Снимок 23.09 · без обновления':data.scenario==='recorded'?(hotels.some(h=>h.offers?.some(o=>o.recordingKind==='demo'))?'Демонстрационная запись':'Цена из загруженной записи'):'Демонстрационная цена';
function calendarScopeHTML(ctx,scope){return `<details class="calendar-scope"><summary><span class="calendar-scope-label"><strong>${esc(scope.destination)}</strong><small>${esc(dateContextLabel(ctx.search))}</small></span><span class="calendar-scope-toggle">${scope.filters.length?'Фильтры: '+scope.filters.length:'Условия'}</span></summary><div class="calendar-scope-content"><p>${esc(scope.fullDestination)}</p>${ctx.search.ages.length?`<p>${esc(childAgesLabel(ctx.search.ages))}</p>`:''}${scope.filters.length?`<p>Цены с учётом выбранных условий:</p><ul>${scope.filters.map(chip=>`<li>${esc(chip.label)}</li>`).join('')}</ul>`:'<p>Без дополнительных фильтров</p>'}<p>${esc(calendarSourceLabel())}. Цена указана за весь тур и всех туристов; актуальность и наличие требуют проверки.</p><p>Прочерк означает, что подсказки цены нет. Он не означает отсутствие туров.</p></div></details>`;}
function renderCalendarScope(){
 const ctx=dateContext,scope=calendarScope(ctx),node=$('.calendar-context');if(!node)return;
 node.innerHTML=`<p class="picker-caption">${esc(dateContextLabel(ctx.search))}</p>`+((destinationIds(ctx.filters).length||ctx.filters.resorts.length||scope.filters.length)?`<p class="picker-caption">Цены: ${esc(scope.destination)}${scope.filters.length?' · '+scope.filters.map(chip=>esc(chip.label)).join(' · '):''}</p>`:'');
}
function mealPreviewModel(){
 const place=currentDraftDestination(),changed=searchKey(draft)!==searchKey(state.search)||destinationIds(place).join('|')!==destinationIds(state.filters).join('|')||JSON.stringify(place.resorts)!==JSON.stringify(state.filters.resorts);
 if(!state.hasSearched||changed||responseFor(state.search).phase==='error')return null;
 return {...appliedFilterModel(),filters:{...pickerFilters(),meals:[...mealDraft]}};
}
function updateMealCounts(){
 const model=mealPreviewModel(),rows=$$('.meal-option'),selectedInventory=model?{count:null}:null,counts=model?countFacetOptions(model,'meals',rows.map(row=>row.querySelector('input').value).filter(Boolean),selectedInventory):null;
 rows.forEach(row=>{row.querySelector('.meal-hotel-count')?.remove();if(!model)return;const value=row.querySelector('input').value,count=value?counts.get(value):countMatchingHotels({...model,filters:{...model.filters,meals:[]}});row.insertAdjacentHTML('beforeend',`<span class="meal-hotel-count" aria-label="${hotelCountText(count)}">${count}</span>`);});
 return selectedInventory?.count;
}
function updateMealPicker(selectedCount=null){
 const query=($('#meal-query')?.value||'').trim().toLocaleLowerCase('ru-RU');let visible=0;
 $$('.meal-option').forEach(row=>{const value=row.querySelector('input').value;row.hidden=!!value&&!value.toLocaleLowerCase('ru-RU').includes(query);if(value&&!row.hidden)visible++;});
 $('#meal-no-match').hidden=visible>0;
 $('#meal-selection-status').textContent=mealDraft.join(' · ')||'Любое питание';
 if($('#meal-clear-query'))$('#meal-clear-query').hidden=!query;
 const model=mealPreviewModel(),preview=$('#meal-result-preview');
 preview.classList.remove('meal-preview-empty');
 if(!model){preview.textContent='Любой выбранный вариант';return;}
 const count=selectedCount??countMatchingHotels(model),complete=responseFor(state.search).phase==='complete';
 preview.textContent=count?`${hotelCountText(count)} · по загруженной выдаче`:complete?'Нет отелей с таким питанием и остальными условиями.':'В загруженной части выдачи пока нет подходящих отелей.';
 preview.classList.toggle('meal-preview-empty',count===0);
}
function openMeals(restore=null){
 const restoredMeals=boundedHistoryStrings(restore?.meals);
 mealDraft=restoredMeals??[...pickerFilters().meals];
 const choices=[...new Set([...Object.keys(mealNames),...mealDraft])].sort(compareMealLabels);
 showModal('meals','Питание','УСЛОВИЯ ТУРА',`<p class="modal-intro">Можно выбрать несколько вариантов.</p>${true?'<div class="meal-search">'+icon('search')+'<label><span class="sr-only">Найти тип питания</span><input id="meal-query" type="search" placeholder="Найти тип питания" autocomplete="off"></label><button type="button" id="meal-clear-query" class="text-button" data-action="clear-meal-query" hidden>Сбросить поиск</button></div>':''}<div class="meal-options">${[['','Любое питание'],...choices.map(m=>[m,m])].map(([v,label])=>`<label class="meal-option"><input type="checkbox" data-meal-choice value="${esc(v)}" ${v?mealDraft.includes(v)?'checked':'':!mealDraft.length?'checked':''}><span><strong>${esc(label)}</strong>${v?`<small>${esc({'Всё включено':'AI','Ультра всё включено':'UAI','Завтраки':'BB','Полупансион':'HB','Полный пансион':'FB','Без питания':'RO'}[v]||'')}</small>`:''}</span></label>`).join('')}</div><p id="meal-no-match" class="meal-no-match" hidden>Такого названия нет. Попробуйте другое или сбросьте поиск.</p>`);
 $('#modal').classList.add('meals-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="meal-result-summary" role="status" aria-live="polite"><strong id="meal-result-preview"></strong><span id="meal-selection-status"></span></div><button class="primary picker-apply" data-action="apply-meals">Применить</button>';
 if($('#meal-query'))$('#meal-query').value=boundedHistoryText(restore?.query,'');
 updateMealPicker(updateMealCounts());
 if(Number.isFinite(restore?.scroll)&&restore.scroll>=0)$('#modal-body').scrollTop=restore.scroll;
}
function parseBudgetAmount(value,empty){
 const text=String(value).trim();if(!text)return empty;
 if(!/^(?:\d+|\d{1,3}(?:[ \u00a0\u202f]\d{3})+)$/.test(text))return NaN;
 const n=Number(text.replace(/[ \u00a0\u202f]/g,''));return Number.isSafeInteger(n)?n:NaN;
}
function readBudgetFields(low,high){
 const min=parseBudgetAmount(low.value,0),max=parseBudgetAmount(high.value,null);
 const invalidMin=!Number.isFinite(min)||min<0,invalidMax=max!==null&&(!Number.isFinite(max)||max<0||!invalidMin&&min>max);
 return {min,max,invalidMin,invalidMax,valid:!invalidMin&&!invalidMax};
}
function readBudget(){return readBudgetFields($('#budget-min'),$('#budget-max'));}
function budgetErrorText(budget){return budget.valid?'':budget.invalidMin?'Введите целую сумму в рублях без точки и запятой.':!Number.isFinite(budget.max)||budget.max<0?'Введите целую сумму в рублях без точки и запятой.':'Сумма «До» должна быть не меньше суммы «От».';}
function updateBudgetPreview(){
 if(modalType!=='budget')return;
 const budget=readBudget(),{min,max,valid}=budget,preview=$('#budget-preview'),recovery=$('#budget-recovery'),apply=$('[data-action="apply-budget"]');
 $('#budget-min').setAttribute('aria-invalid',String(budget.invalidMin));$('#budget-max').setAttribute('aria-invalid',String(budget.invalidMax));
 $('#budget-error').textContent=budgetErrorText(budget);$('#budget-summary').textContent=budget.valid?budgetLabel(budget):'Проверьте бюджет';
 apply.disabled=!valid;recovery.hidden=true;apply.textContent='Применить бюджет';
 $$('[data-action="budget-preset"]').forEach(button=>{const selected=valid&&min===0&&(button.dataset.value===''?max===null:max===Number(button.dataset.value));button.classList.toggle('active',selected);button.setAttribute('aria-pressed',String(selected));});
 if(!valid){preview.textContent='Исправьте сумму, чтобы увидеть варианты.';return;}
 const place=currentDraftDestination(),changed=searchKey(draft)!==searchKey(state.search)||destinationIds(place).join('|')!==destinationIds(state.filters).join('|')||JSON.stringify(place.resorts)!==JSON.stringify(state.filters.resorts);
 if(!state.hasSearched||changed){preview.textContent='Количество отелей появится после поиска с новыми условиями поездки.';return;}
 const model={...appliedFilterModel(),filters:{...pickerFilters(),min,max}},count=countMatchingHotels(model),complete=responseFor(state.search).phase==='complete';
 preview.textContent=count?`${hotelCountText(count)} в этом бюджете · по загруженной выдаче`:complete?'В этом бюджете нет отелей с выбранными условиями.':'В загруженной части выдачи пока нет отелей в этом бюджете.';
 if(count)apply.textContent=`Применить · ${hotelCountText(count)}`;
 if(!count&&complete&&max!==null){const minimum=minimumHotelOfferTotal(model);
  if(minimum!==null&&minimum>max){const ceiling=Math.ceil(minimum/1000)*1000;recovery.dataset.value=ceiling;recovery.textContent=`Увеличить бюджет до ${money(ceiling)}`;recovery.hidden=false;}
 }
}
function openBudget(restore=null){
 const minText=boundedHistoryText(restore?.minText,pickerFilters().min?String(pickerFilters().min):'',32),maxText=boundedHistoryText(restore?.maxText,String(pickerFilters().max??''),32);
 showModal('budget','Бюджет','',`<p class="modal-intro">За весь тур для всех туристов</p><p class="picker-caption">Сумма в рублях · ₽</p><div class="form-row"><label>От, ₽<input type="text" inputmode="numeric" class="input" id="budget-min" placeholder="Без минимума" value="${esc(minText)}" aria-describedby="budget-error"></label><label>До, ₽<input type="text" inputmode="numeric" class="input" id="budget-max" value="${esc(maxText)}" placeholder="Без максимума" aria-describedby="budget-error"></label></div><div class="budget-presets">${[150000,200000,null].map(n=>`<button class="chip" data-action="budget-preset" data-value="${n??''}" aria-pressed="false">${n===null?'Без ограничений':'До '+money(n)}</button>`).join('')}</div><p class="error-text" id="budget-error" role="alert"></p><div class="budget-feedback"><p id="budget-preview" role="status" aria-live="polite"></p><button class="text-button" id="budget-recovery" data-action="budget-adjust-max" hidden></button></div><p class="budget-price-note">Цены и наличие уточняются при выборе тура.</p>`);
 $('#modal').classList.add('budget-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="picker-summary"><strong id="budget-summary"></strong><small>Цена за весь тур и всех туристов</small></div><button class="primary picker-apply" data-action="apply-budget">Применить бюджет</button>';updateBudgetPreview();
 if(Number.isFinite(restore?.scroll)&&restore.scroll>=0)$('#modal-body').scrollTop=restore.scroll;
}

function prepareDatePicker(source,restore){
 calendarScopeTogglePending=false;
 dateContext=createDateContext(source);const s=dateContext.search,selectedDay=source==='results'?state.selectedDate:draftSelectedDate();
 const restoredDraft=restore?.draft&&typeof restore.draft==='object'?{from:String(restore.draft.from||''),to:String(restore.draft.to||''),phase:restore.draft.phase===1?1:0}:null;
 dateDraft=restoredDraft&&!dateRangeError(restoredDraft)?restoredDraft:{from:selectedDay||s.from,to:selectedDay||s.to,phase:0};
 const firstMonth=startDay.slice(0,7)+'-01',lastMonth=endDay.slice(0,7)+'-01',restoredMonth=String(restore?.month||'');
 datePrices=new Map();calendarLoads=new Map();calendarHotels=[];calendarObservations=[];calendarBasePrices=new Map();calendarMonthPrices=new Map();calendarPriceContext=null;calendarMonth=/^\d{4}-\d{2}-01$/.test(restoredMonth)&&restoredMonth>=firstMonth&&restoredMonth<=lastMonth?restoredMonth:dateDraft.from.slice(0,7)+'-01';
}
function openDates(source='form',restore=null){
 prepareDatePicker(source,restore);
 showModal('dates','Даты вылета','',`<div class="date-choice-tools"><strong id="date-selection-label"></strong><p id="date-selection-hint" aria-live="polite"></p><div class="calendar-context"></div><span data-calendar-load-status hidden></span><div class="calendar-legend" role="status" hidden><span></span></div></div><div id="date-calendar"></div>`);
 renderCalendarScope();
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="date-footer"><p class="picker-caption date-missing-price">— цена не сохранена; дату можно выбрать.</p><div id="date-selection-price" class="date-selection-price" aria-live="polite"></div><p class="picker-caption date-source">${esc(calendarSourceLabel())}</p><p class="error-text" id="date-error" role="alert"></p><button class="primary picker-apply" data-action="apply-dates"></button></div>`;
 refreshCalendarPriceCache();renderDateCalendar();
 syncCalendarViewport();revealCalendarMonth();loadCalendarPrices();
}
function syncCalendarViewport(){
 const modal=$('#modal'),tools=$('.date-choice-tools'),expanded=innerWidth<=760&&!!$('.calendar-scope')?.open;
 tools.classList.toggle('scope-expanded',expanded);
 if(innerWidth<=760&&!expanded)modal.style.setProperty('--calendar-context-height',tools.offsetHeight+12+'px');
 else modal.style.removeProperty('--calendar-context-height');
}
function revealCalendarMonth(){
 const body=$('#modal-body'),month=$(`#date-calendar [data-month="${calendarMonth}"]`);
 if(innerWidth>760){body.scrollTop=0;return;}
 if($('.calendar-scope')?.open)return;
 if(month)body.scrollTop=Math.max(0,body.scrollTop+month.getBoundingClientRect().top-body.getBoundingClientRect().top-$('.date-choice-tools').offsetHeight-12);
}
function syncCalendarScopeDisclosure(){
 if(modalType!=='dates')return;
 const scope=this;
 requestAnimationFrame(()=>{
  if(modalType!=='dates'||scope!==$('.calendar-scope'))return;
  const expanded=scope.open&&innerWidth<=760;
  syncCalendarViewport();
  if(innerWidth>760)return;
  if(expanded)$('#modal-body').scrollTop=0;
  else{const restoreMonth=scope.dataset.restoreMonth;if(restoreMonth)calendarMonth=restoreMonth;revealCalendarMonth();}
  calendarScopeTogglePending=false;
 });
}
function rememberVisibleCalendarMonth(){
 // Ignore the old DOM while a breakpoint change is being laid out.
 if(modalType!=='dates'||!calendarMobile||innerWidth>760||calendarScopeTogglePending||$('.calendar-scope')?.open)return;
 const month=visibleCalendarMonth();
 if(month){calendarMonth=month.dataset.month;if($('.calendar-scope'))$('.calendar-scope').dataset.restoreMonth=calendarMonth;}
}
function visibleCalendarMonth(){
 const boundary=$('.date-choice-tools').getBoundingClientRect().bottom+12;
 return $$('#date-calendar .calendar-month').find(node=>node.getBoundingClientRect().bottom>boundary+24);
}
function calendarPriceInventory(hotelRows,observationRows,ctx=dateContext){
 const prices=new Map(),s={...ctx.search,from:startDay,to:endDay},f=ctx.filters;
 const add=(day,price)=>{if(day>=startDay&&day<=endDay&&Number.isFinite(price)&&price>0)prices.set(day,Math.min(prices.get(day)??Infinity,price));};
 // Snapshot the source membership before offer access: preserve the previous
 // spread semantics for sparse/inherited arrays and mid-scan source growth.
 for(const h of [...hotelRows])for(const o of hotelOffers(h,{search:s,filters:f,selectedDate:null,onlyFavorites:false,sort:false}))add(o.day,o.total);
 if(data.observationScopeSupported(s,f))for(const point of observationRows)add(point.date,point.price);
 return prices;
}
function applyCalendarPriceInventories(){
 datePrices.clear();
 for(const prices of [calendarBasePrices,...calendarMonthPrices.values()])for(const [day,price]of prices)datePrices.set(day,Math.min(datePrices.get(day)??Infinity,price));
}
function prepareCalendarBasePrices(ctx){
 calendarPriceContext=ctx;calendarBasePrices=calendarPriceInventory(hotels,[],ctx);calendarMonthPrices=new Map();applyCalendarPriceInventories();
}
function refreshCalendarPriceCache(){
 const prices=calendarPriceInventory([...hotels,...calendarHotels],calendarObservations,dateContext);datePrices.clear();for(const [day,price]of prices)datePrices.set(day,price);
 if(!calendarHotels.length&&!calendarObservations.length){calendarPriceContext=dateContext;calendarBasePrices=new Map(prices);calendarMonthPrices=new Map();}
}
function calendarPrice(day){return datePrices.get(day)??null;}
function monthFrame(month){
 const date=dateObj(month),first=(date.getUTCDay()+6)%7,count=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth()+1,0)).getUTCDate(),heading=formatDate(monthFormatter,date);
 const prices=Array.from({length:count},(_,i)=>{const d=month.slice(0,8)+String(i+1).padStart(2,'0');return d>=startDay&&d<=endDay?calendarPrice(d):null}),cheapest=minimumKnownPrice(prices);
 let days='<span></span>'.repeat(first);
 for(let n=1;n<=count;n++){const day=month.slice(0,8)+String(n).padStart(2,'0'),valid=day>=startDay&&day<=endDay,price=prices[n-1];days+=`<button class="month-day ${price!==null&&price===cheapest?'is-cheap':''}" data-action="day-pick" data-date="${day}" ${!valid?'disabled':''} aria-label="${dateLong(day)}${price!==null?', от '+money(price):valid?', цена пока неизвестна':''}"><span>${n}</span><small>${price!==null?shortAmount(price,true):valid?'—':''}</small></button>`;}
 return `<section class="calendar-month" data-month="${month}"><h3>${heading.charAt(0).toUpperCase()+heading.slice(1)}</h3><p class="picker-caption calendar-units">От, тыс. ₽ · весь тур за ${esc(partyLabel(dateContext.search))}</p><div class="month-grid">${['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].map(d=>`<span class="weekday">${d}</span>`).join('')}${days}</div></section>`;
}
function renderDateCalendar(){
 calendarMobile=innerWidth<=760;
 const months=[],first=startDay.slice(0,7)+'-01',last=endDay.slice(0,7)+'-01',next=dateObj(calendarMonth);next.setUTCMonth(next.getUTCMonth()+1);
 if(innerWidth<=760){for(let m=first;m<=last;){months.push(m);const d=dateObj(m);d.setUTCMonth(d.getUTCMonth()+1);m=iso(d);}}else{months.push(calendarMonth);if(iso(next)<=last)months.push(iso(next));}
 $('#date-calendar').innerHTML=`${innerWidth<=760?'':`<div class="calendar-navigation"><button class="icon-button" data-action="month-prev" aria-label="Предыдущий месяц" ${calendarMonth<=first?'disabled':''}>${icon('back')}</button><span>Выберите даты вылета</span><button class="icon-button" data-action="month-next" aria-label="Следующий месяц" ${calendarMonth>=last?'disabled':''}>${icon('arrow')}</button></div>`}<div class="calendar-months">${months.map(monthFrame).join('')}</div>`;
 updateDateSelection();
}
function dateRangeError(range){if(!range?.from||!range?.to)return 'Укажите обе даты вылета.';if(range.from<startDay||range.to>endDay)return 'Выберите даты в доступном периоде календаря.';if(range.from>range.to)return 'Конец диапазона должен быть не раньше начала.';if((dateObj(range.to)-dateObj(range.from))/86400000>21)return 'Период может включать не больше 22 дат. Выберите конец ближе к началу.';return '';}
function updateDateSelection(){
 $('#date-selection-label').textContent=(dateDraft.from===dateDraft.to?'Вылет ':'Диапазон ')+departureRangeText(dateDraft.from,dateDraft.to)+' · '+durationText(dateContext.search);
 $$('[data-action="day-pick"]').forEach(b=>{const d=b.dataset.date,active=d===dateDraft.from||d===dateDraft.to;b.classList.toggle('active',active);b.classList.toggle('in-range',d>dateDraft.from&&d<dateDraft.to);b.setAttribute('aria-pressed',active||d>dateDraft.from&&d<dateDraft.to)});
 $('[data-action="apply-dates"]').textContent=dateDraft.from&&dateDraft.to?(data.live&&dateContext?.source==='results'?'Найти туры: ':'Применить ')+departureRangeText(dateDraft.from,dateDraft.to):'Выберите обе даты';
 const hint=$('#date-selection-hint');hint.hidden=false;hint.textContent=dateDraft.phase?'Для диапазона нажмите вторую дату.':'Выберите день или диапазон вылета.';
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
 if(from&&to&&from<=to&&(dateObj(to)-dateObj(from))/86400000<=21){for(let day=from;day<=to;day=addDays(day,1)){const p=calendarPrice(day);if(Number.isFinite(p)&&p>0)values.push({day,price:p});}}
 const phase=calendarSelectionPhase();
 const note=phase==='loading'?'Загружаем подсказки цен…':phase==='error'?'Не все подсказки цен загрузились.':phase==='idle'?'Подсказка цены ещё не загружена.':phase==='invalid'?'Выберите корректные даты вылета.':'— для этих условий цены не сохранены';
 return {values,phase,note};
}
function dateSelectionPriceHTML({values,phase,note}){
 const minimum=values.reduce((best,value)=>!best||value.price<best.price?value:best,null);
 return minimum?`<span class="calendar-minimum">Минимум: ${dateText(minimum.day)} · <strong>${money(minimum.price)}</strong>${phase==='loading'?'<small>Загрузка продолжается.</small>':phase==='error'?'<small>Часть цен недоступна.</small>':''}</span>`:`<span>${note}<small>${phase==='complete'?'Даты можно выбрать: это не означает, что туров нет.':phase==='invalid'?'':'Даты можно выбрать, не дожидаясь цены.'}</small></span>`;
}
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
 guestDraft=restoredGuestDraft(restore?.draft);showModal('guests','Туристы','','');$('#modal').classList.add('guests-dialog');
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="guest-footer"><div class="guest-selection" role="status" aria-live="polite"><strong id="guest-count-summary"></strong><span id="guest-selection-hint"></span></div><button class="primary picker-apply" data-action="apply-guests">Выбрать туристов</button></div>';renderGuests();
}
function updateGuestSelection(){
 const g=guestDraft,missing=g.ages.map((age,i)=>age===null?i+1:null).filter(Boolean);
 $('#guest-count-summary').textContent=partyLabel(g);
 $('#guest-selection-hint').textContent=missing.length?'Укажите возраст '+(missing.length===1?'ребёнка '+missing[0]:'детей '+missing.join(', ')):g.ages.length?childAgesLabel(g.ages):'Цена тура рассчитывается за всех туристов';
 $('[data-action="apply-guests"]').disabled=!!missing.length;
}
function renderGuests(){
 const focused=document.activeElement?.closest('#modal-body [data-action]')?.dataset.action,g=guestDraft;
 $('#modal-body').innerHTML=`<div class="counter-row"><div><strong>Взрослые</strong><small>От 18 лет</small></div><div class="counter"><button data-action="adults-minus" aria-label="Убрать взрослого" ${g.adults<=1?'disabled':''}>−</button><output aria-label="Количество взрослых">${g.adults}</output><button data-action="adults-plus" aria-label="Добавить взрослого" ${g.adults>=6?'disabled':''}>+</button></div></div><div class="counter-row"><div><strong>Дети</strong><small>До 18 лет</small></div><div class="counter"><button data-action="children-minus" aria-label="Убрать ребёнка" ${!g.ages.length?'disabled':''}>−</button><output aria-label="Количество детей">${g.ages.length}</output><button data-action="children-plus" aria-label="Добавить ребёнка" ${g.ages.length>=3?'disabled':''}>+</button></div></div><div class="ages">${g.ages.map((age,i)=>`<div class="guest-age-row"><button class="guest-age-choice" data-action="child-age" data-index="${i}" aria-label="Возраст ребёнка ${i+1}"><small>Ребёнок ${i+1}</small><span>${age===null?'Укажите возраст':age===0?'До 1 года':childAgeText(age)}</span><span class="chevron">›</span></button><button class="icon-button" type="button" data-action="remove-child" data-index="${i}" aria-label="Убрать ребёнка ${i+1}">${icon('x')}</button></div>`).join('')}</div><p class="guest-age-help" id="guest-age-help">${agesNeedReview?'Даты или ночи изменены. Проверьте возраст детей на возвращение.':g.ages.length?'Укажите возраст на дату возвращения. Она зависит от выбранного тура.':'Можно добавить до 3 детей. Возраст нужен для каждого ребёнка.'}</p><p class="error-text" id="guest-error" role="alert"></p>`;
 updateGuestSelection();if(focused)$(`#modal-body [data-action="${focused}"]:not(:disabled)`)?.focus({preventScroll:true});rememberUIRoute();
}
function openChildAge(index,value=undefined){
 if(!Number.isInteger(index)||index<0||index>=guestDraft.ages.length)return;ageChoice={index,value:value===undefined?guestDraft.ages[index]:Number.isInteger(value)&&value>=0&&value<=17?value:null};
 showModal('child-age','Возраст ребёнка '+(index+1),'',`<p class="modal-intro">Возраст на дату возвращения</p><p class="picker-caption">Дата возвращения зависит от выбранного тура.</p><div class="age-grid">${Array.from({length:18},(_,n)=>`<button data-action="age-pick" data-value="${n}" aria-pressed="${ageChoice.value===n}" aria-label="${n===0?'До 1 года':childAgeText(n)}">${n===0?'<span>До 1</span><small>года</small>':n}</button>`).join('')}</div><p class="picker-caption">«Выбрать возраст» вернёт к туристам. «Назад» сохранит прежний возраст.</p>`);$('#modal').classList.add('child-age-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="picker-summary"><strong id="age-summary"></strong><small>Для ребёнка '+(index+1)+'</small></div><button class="primary picker-apply" data-action="apply-age">Выбрать возраст</button>';updateAgeChoice();
}
function updateAgeChoice(){const n=ageChoice.value;$$('[data-action="age-pick"]').forEach(b=>b.setAttribute('aria-pressed',String(+b.dataset.value===n)));$('#age-summary').textContent=n===null?'Укажите возраст':n===0?'До 1 года':childAgeText(n);$('[data-action="apply-age"]').disabled=n===null;rememberUIRoute();}
function openStars(restore=null){starsDraft=Array.isArray(restore?.stars)?restore.stars.filter(n=>[3,4,5].includes(n)):[...pickerFilters().stars];showModal('stars','Категория отеля','',`<p class="modal-intro">Можно выбрать несколько категорий</p><div class="category-options">${[0,3,4,5].map(n=>`<button class="destination-row" data-action="picker-star" data-value="${n}"><span><strong>${n?'★'.repeat(n):'Любая категория'}</strong>${n?`<small>${n} ${n===5?'звёзд':'звезды'}</small>`:''}</span><span class="choice-check"></span></button>`).join('')}</div>`);$('#modal').classList.add('stars-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="picker-summary"><strong id="stars-summary"></strong><small>Точная категория · любой выбранный вариант</small></div><button class="primary picker-apply" data-action="apply-stars">Применить</button>';updateStarsPicker();}
function updateStarsPicker(){$$('[data-action="picker-star"]').forEach(b=>{const active=+b.dataset.value?starsDraft.includes(+b.dataset.value):!starsDraft.length;b.setAttribute('aria-pressed',String(active));b.querySelector('.choice-check').innerHTML=active?icon('check'):'';});$('#stars-summary').textContent=starsDraft.map(n=>n+'★').join(' и ')||'Любая категория';rememberUIRoute();}
function openFormFilters(restore=null){formFiltersDraft=restoreFilterHistoryModel({filters:restore?.filters,onlyFavorites:false,selectedDate:null})?.filters||structuredClone(state.filters);showModal('form-filters','Все фильтры','','');$('#modal').classList.add('form-filters-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="picker-summary"><strong id="form-filters-summary"></strong><small>Основная форма и фильтры используют один выбор</small></div><button class="primary picker-apply" data-action="apply-form-filters">Применить фильтры</button>';renderFormFilters();if(Number.isFinite(restore?.scroll)&&restore.scroll>=0)$('#modal-body').scrollTop=restore.scroll;}
function renderFormFilters(){const f=formFiltersDraft;if(!f)return;$('#modal-body').innerHTML=`<p class="picker-caption">${esc(draft.origin)} → ${esc(destinationLabel(currentDraftDestination()))} · ${esc(durationText(draft))} · ${esc(partyLabel(draft))}</p>${[['stars','Категория отеля',f.stars.map(n=>n+'★').join(' и ')||'Любая'],['meals','Питание',f.meals.join(' · ')||'Любое'],['budget','Бюджет за всех',budgetLabel(f)]].map(([action,label,value])=>`<button class="destination-row" data-action="${action}"><span><strong>${label}</strong><small>${esc(value)}</small></span><span class="chevron">›</span></button>`).join('')}<button class="text-button" data-action="reset-form-filters">Сбросить фильтры</button><p class="picker-caption">Сброс сохранит город, направление, даты, ночи и туристов.</p>`;const count=[f.stars.length,f.meals.length,f.min>0||f.max!==null].filter(Boolean).length;$('#form-filters-summary').textContent=count?count+(count===1?' группа условий':' группы условий'):'Без дополнительных условий';rememberUIRoute();}

function restoredNightsDraft(value){
 const fallback={min:draft.minNights,max:draft.maxNights,phase:0};if(!value||typeof value!=='object')return fallback;
 const min=Number(value.min),max=Number(value.max);if(!Number.isInteger(min)||!Number.isInteger(max)||min<1||max>28||min>max||max-min>10)return fallback;
 return {min,max,phase:value.phase===1?1:0};
}
function openNights(restore=null){nightsDraft=restoredNightsDraft(restore?.draft);showModal('nights','Количество ночей','',`<p class="modal-intro">Нажмите одно число для точной длительности или два — для диапазона.</p><div class="night-grid" aria-label="Количество ночей">${Array.from({length:28},(_,i)=>`<button data-action="night-pick" data-value="${i+1}" aria-label="${nightsText(i+1)}">${i+1}</button>`).join('')}</div><p class="picker-caption">Даты вылета не изменятся</p><h3 class="picker-subtitle">Быстрый выбор</h3><div class="nights-options">${[7,10,14,21].map(n=>`<button data-action="night-preset" data-value="${n}">${nightsText(n)}</button>`).join('')}</div>`);$('#modal').classList.add('nights-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="nights-footer"><p class="night-selection" aria-live="polite"></p><p class="error-text" id="night-error" role="alert" hidden></p><button class="primary picker-apply" data-action="apply-nights"></button></div>';renderNightSelection();}
function renderNightSelection(){$('#night-error').hidden=!nightsDraft.error;$('#night-error').textContent=nightsDraft.error||'';$$('.night-grid button').forEach(b=>{const n=+b.dataset.value,active=n===nightsDraft.min||n===nightsDraft.max;b.classList.toggle('active',active);b.classList.toggle('in-range',n>nightsDraft.min&&n<nightsDraft.max);b.setAttribute('aria-pressed',n>=nightsDraft.min&&n<=nightsDraft.max)});$$('.nights-options button').forEach(b=>b.setAttribute('aria-pressed',nightsDraft.min===+b.dataset.value&&nightsDraft.max===+b.dataset.value));const label=durationText({minNights:nightsDraft.min,maxNights:nightsDraft.max});$('.night-selection').textContent=nightsDraft.phase?'Выберите вторую границу или подтвердите '+label:'Выбрано: '+label;$('[data-action="apply-nights"]').textContent='Выбрать '+label;rememberUIRoute();}
function openGallery(id,index=0){const h=hotels.find(x=>x.id===id);if(!h?.photos.length){toast('Фотографии этого отеля пока недоступны.');return;}showModal('gallery',h.name,'ФОТОГРАФИИ ОТЕЛЯ','');gallery={id,index};renderGallery();}
function renderGallery(){const focused=document.activeElement?.closest('#modal-body button'),focusAction=focused?.dataset.action,focusValue=focused?.dataset.value;const h=hotels.find(x=>x.id===gallery.id);if(!h?.photos.length)return;$('#modal-body').innerHTML=`<div class="gallery-stage"><img id="gallery-image" src="${esc(photoUrl(h,gallery.index))}" alt="Фото ${gallery.index+1} из ${h.photos.length}" draggable="false"><button class="icon-button gallery-arrow prev" data-action="gallery-prev" aria-label="Предыдущее фото">${icon('back')}</button><button class="icon-button gallery-arrow next" data-action="gallery-next" aria-label="Следующее фото">${icon('arrow')}</button></div><div class="gallery-caption"><span>${esc(h.name)}</span><span>${gallery.index+1} / ${h.photos.length}</span></div><div class="gallery-thumbs">${h.photos.map((p,i)=>`<button data-action="gallery-index" data-value="${i}" class="${gallery.index===i?'active':''}" aria-pressed="${gallery.index===i}" aria-label="Фото ${i+1}"><img src="${esc(p)}" alt="" loading="lazy"></button>`).join('')}</div>`;if(focusAction){const target=$$('#modal-body button').find(b=>b.dataset.action===focusAction&&(focusValue===undefined||b.dataset.value===focusValue));target?.focus({preventScroll:true});}}
let flightDraft=null,andromedaQuoteDraft=null,andromedaApplicationDraft=null,anexCurrentDraft=null,anexApplicationDraft=null,selectionGeneration=0;
// Retain only this search's direct-provider outcomes for navigation; never persist them.
const providerViews=new Map();
function retainedProviderView(o){const view=providerViews.get(o?.key);return view&&view.offer.raw===o.raw&&offerFromKey(o.key)?.raw===o.raw?view:null;}
function rememberProviderView(o,type,result,error='',pending=false){
 const previous=retainedProviderView(o),repricing=result?.repricing?.enabled===true;
 if(o&&o.raw&&offerFromKey(o.key)?.raw===o.raw)providerViews.set(o.key,{offer:{...o,loading:false},type,result,error,pending,
  ...(type==='anex-quote'?{choice:previous?.choice||result?.choice?.choiceRef}:{}),
  ...(repricing?{editing:previous?.editing===true,sealed:previous?.sealed===true}:{}),
  ...(type.startsWith('andromeda-')&&repricing?{outbound:previous?.outbound||result.flights?.find(f=>f.direction==='0')?.flightRef,inbound:previous?.inbound||result.flights?.find(f=>f.direction==='1')?.flightRef}:
   type==='andromeda-flights'&&previous?.type===type&&previous.result===result?{outbound:previous.outbound,inbound:previous.inbound}:{})});
}
function rememberAndromedaFlightChoice(input){
 const view=retainedProviderView(selectedOffer),direction=input.name==='andromeda-outbound'?'0':'1',repricing=view?.result?.repricing?.enabled===true;
 const flights=repricing?view.result.flightChoices||view.result.flights:view?.result?.flights,field=direction==='0'?'outbound':'inbound';
 if(view?.type!=='andromeda-flights'||view.sealed||view.pending&&!repricing||!input.checked||!flights.some(f=>f.direction===direction&&f.flightRef===input.value)||view[field]===input.value)return false;
 view[field]=input.value;if(repricing){view.editing=true;andromedaApplicationDraft=null;}return true;
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
  if(o.provider==='anex'&&o.raw?.anexKind==='concrete'&&o.raw?.anexSessionCurrent===true&&!o.quoteError)return `<button class="primary" data-action="select-anex-tour" ${o.loading?'disabled':''}>${o.loading?'Уточняем цену тура…':'Выбрать перелёт и уточнить цену'}</button>`;
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
 const withoutFlights=unavailable&&!terminalQuoteError&&o.provider==='anex'&&o.raw?.anexKind==='concrete'&&o.raw?.anexSessionCurrent===true?`<button class="text-button" data-action="refresh-hotel" data-id="${h.id}" ${o.loading?'disabled':''}>Уточнить цену без выбора рейсов</button>`:'';
 const feedback=`${unavailable&&!terminalQuoteError?(data.live?`<p class="saved-tour-notice">${esc(refreshOfferNotice(o))}</p>`:window.AnyTourPrototypeLead.unavailableMarkup(o)):''}${withoutFlights}${o.quoteError?`<p class="error-text" role="alert">${esc(o.quoteError)}</p>`:''}${o.pricePending?'<p class="error-text" role="status">Цена выбранного перелёта пока не подтверждена. Можно выбрать другой вариант или оставить рейс менеджеру.</p>':''}`;
 return `
 ${selectionStepsHTML(!o.tour||o.loading||o.quoteError?0:1)}${quotePriceChangeHTML(o)}${tourHeroHTML(h)}


 <div class="tour-layout"><div class="tour-main-details">${chosenStayHTML(o,true)}${feedback}${unavailable||terminalQuoteError?'':flightSummaryHTML(o)}</div>
 <aside class="tour-price-details" aria-label="Состав и стоимость тура"><div class="price-breakdown"><h3>Цена и условия</h3><p class="price-party">За ${guestsText(o)} · ${nightsText(o.nights)}</p><div class="price-line total"><span>${o.quoteError?'Цена из выдачи':flightPairFor(o)?'С выбранным перелётом':'Цена предложения'}</span><strong id="detail-total">${o.pricePending?'Уточняется':money(o.total)}</strong></div>${!unavailable?`<p class="price-assurance">${icon('info')} ${o.quoteError?'Эта сумма не подтверждена после проверки.':o.provider==='fixture'?priceNote(o):o.loading?'Получаем цену предложения…':unavailable?priceNote(o):'Условия цены и наличие подтверждаются перед оформлением'}</p>`:''}${!unavailable&&selectionHint?`<p class="tour-selection-hint" role="status">${selectionHint}</p>`:''}${fuelDisclosureHTML(o)}</div></aside></div>`;
}
function offerDetailFooterHTML(o,footerAction,footerStatus){
 return `<div class="footer-total"><span>${o.quoteError?'Цена из выдачи за всех':'За всех туристов'}</span><strong>${o.pricePending?'Цена уточняется':money(o.total)}</strong><small class="footer-price-status">${footerStatus}</small></div>${footerAction}`;
}
function tourHeroHTML(h){return `<div class="tour-hero">${h.photos?.length?`<img src="${esc(photoUrl(h))}" alt="Фото ${esc(h.name)}">`:`<div class="tour-photo-missing">${icon('image')}<span>Нет фото</span></div>`}<div>${hotelStarsHTML(h)}<h3>${esc(h.name)}</h3><p>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</p></div></div>`;}
function providerTourBodyHTML(o,h,flights,price,status){
 const changed=price===null?'':quotePriceChangeHTML({...o,quoteListingTotal:o.total,total:price,loading:false,quoteError:'',pricePending:false});
 return `${selectionStepsHTML(1)}${tourHeroHTML(h)}${changed}<div class="tour-layout"><div class="tour-main-details">${chosenStayHTML(o)}<section class="tour-section flight-summary"><h3>${icon('plane')} Перелёт</h3>${flights}</section></div><aside class="tour-price-details"><div class="price-breakdown"><h3>Цена тура</h3><p class="price-party">За ${guestsText(o)} · ${nightsText(o.nights)}</p><div class="price-line total"><span>${esc(status)}</span><strong>${price===null?'Цена уточняется':money(price)}</strong></div><p class="price-assurance">${price===null?'Полную стоимость получим для выбранного перелёта.':'Подтверждённая стоимость этого тура.'}</p></div></aside></div>`;
}
function providerApplicationBodyHTML(receipt,flights,hotelId){
 return `${selectionStepsHTML(2,{flightDeferred:!receipt.flights.length})}<div class="application-layout"><div class="application-review"><section class="application-choice" aria-label="Выбранный тур"><h3>${esc(receipt.hotel)}</h3><section class="chosen-stay"><p class="chosen-trip"><strong>${rangeText(receipt.day,addDays(receipt.day,receipt.nights))} · ${nightsText(receipt.nights)}</strong><span>${guestsText(receipt)}${receipt.ages.length?' · '+esc(childAgesLabel(receipt.ages,true)):''}</span></p><dl class="saved-stay-summary"><div><dt>Номер</dt><dd>${esc(receipt.room)}</dd></div><div><dt>Питание</dt><dd>${esc(receipt.meal)}</dd></div><div><dt>Оператор</dt><dd>${esc(receipt.operator)}</dd></div></dl></section>${Number.isSafeInteger(hotelId)&&hotelId>0?`<button class="secondary" data-action="all-offers" data-id="${hotelId}">Другие туры этого отеля</button>`:''}</section>${flights?`<details class="summary-flight-details" ${innerWidth>760?'open':''}><summary>Рейсы и багаж</summary>${flights}</details>`:'<p class="saved-flight-fallback">Рейс уточнит менеджер.</p>'}${receipt.finalPriceVerified?'':'<p class="tour-selection-hint">Расчётная стоимость требует подтверждения оператором.</p>'}</div><div class="application-contact">${window.AnyTourPrototypeLead.markup()}</div></div>`;
}
function providerApplicationFooterHTML(receipt){return offerDetailFooterHTML({total:receipt.price},window.AnyTourPrototypeLead.action(),receipt.finalPriceVerified?'Подтверждённая цена выбранного тура':'Расчётная стоимость · требует подтверждения');}
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
 if(!selectedOffer?.variants?.length)return;flightDraft={base:selectedOffer,id:selectedOffer.flightChoiceId};
 showModal('flights','Выберите перелёт','ТУДА И ОБРАТНО','<div class="empty" role="status">Загружаем варианты перелёта…</div>',true);
 $('#modal').classList.add('flight-picker-dialog');renderFlightPicker();
 $('#modal-title').tabIndex=-1;$('#modal-title').focus({preventScroll:true});$('#modal-body').scrollTop=0;
}
let flightPickerLoad=null,flightPickerRequest=0;
function loadFlightPicker(){
 if(window.AnyTourFlightPickerUIV1)return Promise.resolve(window.AnyTourFlightPickerUIV1);
 if(flightPickerLoad)return flightPickerLoad;
 const script=document.createElement('script');script.src=document.head.dataset.flightPickerSrc||'./flight-picker-ui-v1.js';script.async=true;
 flightPickerLoad=new Promise((resolve,reject)=>{
  let done=false;const finish=error=>{if(done)return;done=true;clearTimeout(timer);script.remove();if(error){flightPickerLoad=null;reject(error);}else resolve(window.AnyTourFlightPickerUIV1);};
  const timer=setTimeout(()=>finish(new Error('Не удалось загрузить варианты перелёта.')),15000);
  script.onload=()=>finish(window.AnyTourFlightPickerUIV1?null:new Error('Варианты перелёта недоступны.'));
  script.onerror=()=>finish(new Error('Не удалось загрузить варианты перелёта.'));document.head.append(script);
 });return flightPickerLoad;
}
function renderFlightPicker(){
 const view=flightDraft,request=++flightPickerRequest;if(!view)return;
 const current=()=>request===flightPickerRequest&&flightDraft===view&&selectedOffer===view.base&&modalType==='flights'&&$('#modal').open;
 const paint=()=>{if(!current())return;const o=view.base;
  $('#modal-body').innerHTML=`${selectionStepsHTML(1)}<div class="flight-picker-context"><strong>${esc(hotels.find(h=>h.id===o.hotelId).name)}</strong><span>${rangeText(o.day,o.returnDay)} · ${nightsText(o.nights)} · ${guestsText(o)}</span></div><p class="flight-picker-note">${o.provider==='fixture'?'Демо · ':''}Время местное · цены за весь тур</p>${window.AnyTourFlightPickerV18.render(o,flightDraft.id,{esc,money,text:data.text,price:data.variantPrice,legHTML,fuelText})}`;
 window.AnyTourFlightPickerV18.bind($('#modal-body'),money);
 $('#modal').classList.add('flight-picker-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="flight-selection-total" aria-live="polite"><span>Весь тур за всех</span><strong id="flight-total"></strong></div><button class="primary" data-action="apply-flight">К заявке с этими рейсами</button><div class="flight-selection-caption" aria-live="polite"><span id="flight-selected-summary"></span><span id="flight-price-change"></span></div>';updateFlightPreview();

  rememberUIRoute();
 };
 if(window.AnyTourFlightPickerUIV1){paint();return;}
 $('#modal-body').innerHTML='<div class="empty" role="status">Загружаем варианты перелёта…</div>';$('#modal-footer').hidden=true;
 loadFlightPicker().then(paint,()=>{if(current())$('#modal-body').innerHTML='<div class="empty" role="alert"><p>Не удалось загрузить варианты перелёта.</p><button class="secondary" data-action="retry-flight-picker">Повторить загрузку</button></div>';});
}
function updateFlightPreview(){if(!flightDraft||modalType!=='flights')return;const o=withFlightPair(flightDraft.base,flightDraft.id),before=flightDraft.base.quoteListingTotal||flightDraft.base.total,delta=o.total-before;$('#flight-price-change').hidden=!o.pricePending&&delta===0;$('#flight-selected-summary').textContent=window.AnyTourFlightPickerV18.selectionSummary(flightDraft.base,flightDraft.id);$('#flight-total').textContent=o.pricePending?'Цена уточняется':money(o.total);$('#flight-price-change').textContent=o.pricePending?'Цена этого перелёта пока не подтверждена. Выберите другой вариант.':(delta===0?'Цена тура не изменится':`${delta>0?'Дороже':'Дешевле'} на ${money(Math.abs(delta))} · было ${money(before)}`);$('[data-action="apply-flight"]').disabled=o.pricePending;}

function applyFlightPair(){if(!flightDraft)return;const applied=withFlightPair(flightDraft.base,flightDraft.id);if(applied.pricePending){updateFlightPreview();return;}flightDraft=null;modalBack();selectedOffer=applied;renderRealOffer();openLeadPreview(applied);}
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
function andromedaFlightPriceHTML(f){
 const fact=f.transportMarkupReported;
 if(!fact)return '<small>Доплата не указана</small>';
 const value=Number(fact.amount).toLocaleString('ru-RU',{maximumFractionDigits:2}),currency={RUB:'₽',USD:'$',EUR:'€'}[fact.currency]||fact.currency;
 return `<small>Доплата оператора: ${esc(value)} ${esc(currency)}</small>`;
}
function openAndromedaFlightChoice(o,quote){
 if(!andromedaQuoteCurrent(quote)){showAndromedaExpired(o);return;}
 const repricing=quote.repricing?.enabled===true,flights=repricing?quote.flightChoices||quote.flights:quote.flights;
 const h=selectedTourHotel(o),outbound=flights.filter(f=>f.direction==='0'),inbound=flights.filter(f=>f.direction==='1');
 if(!h||!outbound.length||!inbound.length){selectedOffer={...o,loading:false,quoteError:'Andromeda не вернул полный выбор перелёта.'};renderRealOffer();return;}
 const previous=retainedProviderView(o),body=$('#modal-body'),samePicker=repricing&&modalType==='andromeda-flights'&&selectedOffer?.raw===o.raw;
 const focused=samePicker?document.activeElement:null,focusName=focused?.name,focusValue=focused?.value,scroll=body.scrollTop;let anchor=null;
 if(samePicker&&!previous?.sealed){
  const bounds=body.getBoundingClientRect(),bottom=Math.min(bounds.bottom,$('#modal-footer').getBoundingClientRect().top);
  for(const input of [focused,...$$('[name="andromeda-outbound"]:checked,[name="andromeda-return"]:checked')]){
   if(!input?.matches('[name="andromeda-outbound"],[name="andromeda-return"]'))continue;
   const row=input.closest('.flight-option'),rect=row?.getBoundingClientRect();
   if(rect?.height>0&&rect.top>=bounds.top&&rect.bottom<=bottom){anchor={name:input.name,value:input.value,top:rect.top};break;}
  }
 }
 rememberProviderView(o,'andromeda-flights',quote,previous?.error||'',previous?.pending);andromedaQuoteDraft={offer:o,quote};
 const view=retainedProviderView(o),receipt=repricing&&!view.pending&&!view.error&&!view.sealed?andromedaApplicationReceipt(o,quote,h):null;
 if(repricing)andromedaApplicationDraft=receipt;
 const sealed=repricing&&view.sealed,price=receipt?.price??null,status=sealed?view.error||'Цена и наличие тура пока неизвестны.':receipt?'Подтверждённая цена':view.pending?'Уточняем полную цену тура…':'Цена требует подтверждения';
 const options=`<p class="flight-picker-note">Выберите один рейс туда и один обратно. Доплаты показаны в валюте оператора. Одна общая доплата может повторяться у обоих рейсов — они не складываются.</p><fieldset class="flight-options"><legend>Туда</legend>${outbound.map((f,i)=>`<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="andromeda-outbound" value="${esc(f.flightRef)}" ${i===0?'checked':''}><span><strong>${esc(f.name||'Рейс '+(i+1))}</strong><small>${esc([data.text(f.departure?.port),data.text(f.arrival?.port)].filter(Boolean).join(' → '))}</small>${andromedaFlightPriceHTML(f)}</span></div></label>`).join('')}</fieldset><fieldset class="flight-options"><legend>Обратно</legend>${inbound.map((f,i)=>`<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="andromeda-return" value="${esc(f.flightRef)}" ${i===0?'checked':''}><span><strong>${esc(f.name||'Рейс '+(i+1))}</strong><small>${esc([data.text(f.departure?.port),data.text(f.arrival?.port)].filter(Boolean).join(' → '))}</small>${andromedaFlightPriceHTML(f)}</span></div></label>`).join('')}</fieldset><p class="flight-picker-note" id="andromeda-flight-price-status" role="status">${receipt?'Подтверждённая цена выбранного тура':sealed?esc(status):view.pending?'Уточняем полную цену тура с выбранными рейсами…':'Итоговая цена тура пока не подтверждена. Проверьте выбранные рейсы.'}</p>${sealed?'':`<p class="error-text" id="andromeda-quote-error" role="alert">${esc(view.error||'')}</p>`}`;
 showModal('andromeda-flights',sealed?'Цена тура не подтверждена':receipt?'Тур подтверждён':'Выберите перелёт','ANDROMEDA · ПРОВЕРКА ТУРА',repricing?(sealed?`<p class="error-text" id="andromeda-quote-error" role="alert">${esc(status)}</p>`:'')+providerTourBodyHTML(o,h,options,price,status):`<div class="flight-picker-context"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span></div>${options}`,true);
 $('#modal').classList.add(repricing?'tour-dialog':'flight-picker-dialog');$('#modal-footer').hidden=false;
 $('#modal-footer').innerHTML=repricing?offerDetailFooterHTML({total:price,pricePending:price===null},sealed?`<button class="primary" data-action="all-offers" data-id="${h.id}">К вариантам тура</button>`:receipt?'<button class="primary" data-action="andromeda-application-preview">К заявке</button>':'<button class="primary" data-action="apply-andromeda-flights">Проверить выбранные рейсы</button>',esc(status)):'<button class="secondary" data-action="modal-back">Отмена</button><button class="primary" data-action="apply-andromeda-flights">Проверить выбранные рейсы</button>';
 for(const [name,value] of [['andromeda-outbound',view.outbound],['andromeda-return',view.inbound]]){
  const inputs=$$('[name="'+name+'"]'),chosen=inputs.find(input=>input.value===value);
  if(chosen)chosen.checked=true;
  inputs.forEach(input=>input.disabled=!!view.sealed||!!view.pending&&!repricing);
 }
 const apply=$('[data-action="apply-andromeda-flights"]');if(apply)apply.disabled=!!view.pending||!!view.sealed;
 if(sealed)body.scrollTop=0;
 else if(samePicker){
  if(focusName)$$('[name="'+focusName+'"]')?.find(input=>input.value===focusValue)?.focus({preventScroll:true});
  body.scrollTop=scroll;
  const input=anchor&&$$('[name="'+anchor.name+'"]')?.find(input=>input.value===anchor.value);
  if(input)body.scrollTop+=input.closest('.flight-option').getBoundingClientRect().top-anchor.top;
 }
}
function andromedaApplicationReceipt(o,quote,h){
 const price=Number(quote?.finalPrice?.amount),offerRef=String(o?.raw?.offerRef||o?.raw?.offer_context?.offer_ref||'');
 if(!o||!h||!andromedaQuoteCurrent(quote)||quote?.state!=='quote_verified'||quote?.finalPriceVerified!==true||quote?.flightSelectionRequired!==false
   ||quote?.finalPrice?.currency!=='RUB'||!Number.isFinite(price)||price<=0||!(/^offer_[a-f0-9]{64}$/).test(offerRef)
   ||!Array.isArray(quote.flights))return null;
 const view=retainedProviderView(o);
 if(quote.repricing?.enabled===true&&view&&(view.sealed||quote.flights.length!==2||quote.flights.find(f=>f.direction==='0')?.flightRef!==view.outbound||quote.flights.find(f=>f.direction==='1')?.flightRef!==view.inbound))return null;
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
 showModal('andromeda-verified','Тур подтверждён','ПЕРЕЛЁТ И ПОЛНАЯ ЦЕНА',providerTourBodyHTML(o,h,quote.flights.map(andromedaFlightRoute).join(''),price,'Подтверждённая цена'),true);
 $('#modal').classList.add('tour-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=offerDetailFooterHTML({total:price},(quote.repricing?.enabled===true&&quote.flightChoices?.length>2?'<button class="secondary" data-action="edit-andromeda-flights">Изменить рейсы</button>':'')+'<button class="primary" data-action="andromeda-application-preview">К заявке '+icon('arrow')+'</button>','Подтверждённая цена выбранного тура');
}
function openAndromedaApplicationPreview(){
 const receipt=andromedaApplicationDraft,view=retainedProviderView(selectedOffer);if(!receipt||!view||!['andromeda-verified','andromeda-flights'].includes(modalType)||view.pending||view.error||view.sealed||view.result?.state!=='quote_verified'||modalType==='andromeda-flights'&&view.result?.repricing?.enabled!==true)return;
 const current=andromedaApplicationReceipt(selectedOffer,view.result,selectedTourHotel(selectedOffer));if(!current||current.offerRef!==receipt.offerRef||current.price!==receipt.price)return;
 if(!andromedaQuoteCurrent(receipt)){showAndromedaExpired(selectedOffer);return;}
 const flights=receipt.flights.length?receipt.flights.map(f=>andromedaFlightRoute(f)).join(''):'<p class="tour-missing">Рейсы не указаны поставщиком в подтверждённом ответе.</p>';
 showModal('provider-application','Заявка на тур','ПРОВЕРКА БЕЗ ОТПРАВКИ',providerApplicationBodyHTML(receipt,flights,selectedTourHotel(selectedOffer)?.id),true);
 $('#modal').classList.add('application-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=providerApplicationFooterHTML(receipt);
 window.AnyTourPrototypeLead.bindProviderApplication(receipt);
}
async function applyAndromedaFlightChoice(){
 const draft=andromedaQuoteDraft,repricing=draft?.quote?.repricing?.enabled===true,view=retainedProviderView(draft?.offer);if(!draft||modalType!=='andromeda-flights'||view?.sealed||view?.pending&&!repricing)return;
 const outbound=$('input[name="andromeda-outbound"]:checked')?.value,back=$('input[name="andromeda-return"]:checked')?.value;
 if(!outbound||!back)return;
 selectionGeneration++;const button=$('[data-action="apply-andromeda-flights"]');if(button)button.disabled=true;andromedaApplicationDraft=null;
 $('#andromeda-quote-error').textContent='';
 rememberProviderView(draft.offer,'andromeda-flights',draft.quote,'',true);
 if(repricing)openAndromedaFlightChoice(draft.offer,draft.quote);
 else{$('#andromeda-flight-price-status').textContent='Уточняем полную цену тура с выбранными рейсами…';$$('[name="andromeda-outbound"],[name="andromeda-return"]').forEach(input=>input.disabled=true);}
 try{
  const quote=await data.verifyAndromeda(draft.offer,{provider:'andromeda',outbound_ref:outbound,return_ref:back});
  if(repricing){const current=retainedProviderView(draft.offer);if(!current||current.outbound!==outbound||current.inbound!==back)return;}
  if(quote.state!=='quote_verified')throw new Error('Andromeda не подтвердил выбранный перелёт.');
  rememberProviderView(draft.offer,'andromeda-verified',quote);
  if(!$('#modal').open||modalType!=='andromeda-flights'||selectedOffer?.raw!==draft.offer.raw)return;
  if(repricing&&retainedProviderView(draft.offer)?.editing)openAndromedaFlightChoice(draft.offer,quote);else openAndromedaVerified(draft.offer,quote);
 }catch(error){
  if(repricing){
   const current=retainedProviderView(draft.offer);if(error.code==='quote_superseded'||!current)return;
   if(error.code!=='quote_pair_budget_exhausted'&&!(error.code==='quote_operation_pending'&&error.retryable===true)){
    current.sealed=true;rememberProviderView(draft.offer,'andromeda-flights',current.result,error.message);andromedaApplicationDraft=null;
    if($('#modal').open&&['andromeda-flights','andromeda-verified'].includes(modalType)&&selectedOffer?.raw===draft.offer.raw)openAndromedaFlightChoice(draft.offer,current.result);
    return;
   }
   if(current.outbound!==outbound||current.inbound!==back)return;
   rememberProviderView(draft.offer,'andromeda-flights',draft.quote,error.message);
   if($('#modal').open&&modalType==='andromeda-flights'&&selectedOffer?.raw===draft.offer.raw)openAndromedaFlightChoice(draft.offer,draft.quote);
   return;
  }
  if(retainedProviderView(draft.offer)){if(error.retryable===false)providerViews.delete(draft.offer.key);else rememberProviderView(draft.offer,'andromeda-flights',draft.quote);}
  if($('#modal').open&&modalType==='andromeda-flights'&&selectedOffer?.raw===draft.offer.raw){
   if(error.retryable===false){
    andromedaQuoteDraft=null;selectedOffer={...draft.offer,loading:false,quoteError:error.message,quoteErrorCode:error.code,quoteErrorTerminal:true};renderRealOffer();
   }else{$('#andromeda-quote-error').textContent=error.message;$('#andromeda-flight-price-status').textContent='Полная цена тура пока не подтверждена.';button.disabled=false;$$('[name="andromeda-outbound"],[name="andromeda-return"]').forEach(input=>input.disabled=false);}
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
 const view=retainedProviderView(selectedOffer);if(receipt.finalPriceVerified===true&&(modalType!=='anex-quote'||view?.type!=='anex-quote'||view.pending||view.error||view.sealed||view.result?.state!=='quote_verified'||view.choice!==receipt.choiceRef||Number(view.result.finalPrice?.amount)!==receipt.price))return;
 if(receipt.finalPriceVerified===true){
  if(receipt.expiresAt*1000<=Date.now()){openAnexPackageQuote(selectedOffer,null,'Срок подтверждённой цены истёк.');return;}
  showModal('anex-application','Заявка на тур','ПРОВЕРКА БЕЗ ОТПРАВКИ',providerApplicationBodyHTML(receipt,receipt.flights.map(f=>`<p><strong>${f.direction==='0'?'Туда':'Обратно'}</strong><br>${esc(f.name)}</p>`).join(''),selectedTourHotel(selectedOffer)?.id),true);
  $('#modal').classList.add('application-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=providerApplicationFooterHTML(receipt);
  window.AnyTourPrototypeLead.bindProviderApplication(receipt);return;
 }
 showModal('anex-application','Заявка на тур','ANEX · РАСЧЁТНАЯ СТОИМОСТЬ',providerApplicationBodyHTML(receipt,'',selectedTourHotel(selectedOffer)?.id),true);
 $('#modal').classList.add('application-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=providerApplicationFooterHTML(receipt);
 window.AnyTourPrototypeLead.bindProviderApplication(receipt);
}
const anexFlightViews=new WeakMap();
function openAnexPackageQuote(o,result,error='',pending=false){
 const h=selectedTourHotel(o);if(!h)return;
 const focused=typeof modalType!=='undefined'&&modalType==='anex-quote'?document.activeElement:null,focusName=focused?.name,focusValue=focused?.value,scroll=$('#modal-body').scrollTop;
 if(result?.state==='quote_verified'&&result.expiresAt*1000<=Date.now()){result=null;error='Срок подтверждённой цены истёк.';}
 rememberProviderView(o,'anex-quote',result,error,pending);selectedOffer={...o,loading:false};anexApplicationDraft=null;
 const view=retainedProviderView(o),repricing=result?.repricing?.enabled===true&&!view?.sealed;
 const verified=result?.state==='quote_verified'&&result.finalPriceVerified===true&&!pending&&!error&&!view?.sealed&&(!repricing||view&&(!view.choice||view.choice===result.choice.choiceRef));
 if(verified){
  const raw=o.raw;anexApplicationDraft=Object.freeze({provider:'anex',offerRef:raw.offerRef,searchRef:raw.searchRef,generation:raw.anexGeneration,
   departure:String(o.origin||state.search.origin||''),localHotelId:Number(raw.anexLocalHotelId),priceKind:'verified',finalPriceVerified:true,choiceRef:result.choice.choiceRef,expiresAt:result.expiresAt,
   hotel:String(h.name||''),country:String(countryNames[h.country]||''),resort:String(h.resort||''),day:o.day,nights:o.nights,adults:o.adults,
   ages:Object.freeze([...(o.ages||[])]),room:String(o.room||''),meal:String(mealLabel(o)||''),operator:'ANEX',
   price:Number(result.finalPrice.amount),currency:'RUB',flights:Object.freeze(result.choice.legs.map((leg,i)=>Object.freeze({direction:String(i),name:leg.label})))});
 }
 const choices=result?.state==='quote_choices'||repricing&&view?.editing?result?.choices||[]:[];
 const choice=choices.find(c=>c.choiceRef===view?.choice)?.choiceRef||result?.choice?.choiceRef||choices[0]?.choiceRef;
 if(view)view.choice=choice;
 const price=verified?Number(result.finalPrice.amount):null,status=verified?'Подтверждённая цена':pending?'Уточняем полную цену тура…':'Цена требует подтверждения';
 const flights=(verified&&!view?.editing?result.choice.legs.map((leg,i)=>`<p><strong>${i?'Обратно':'Туда'}</strong><br>${esc(leg.label)}</p>`).join('')
   :choices.length?`${pending?'':'<p>Выберите перелёт для расчёта полной цены тура.</p>'}<fieldset class="flight-options"><legend>Перелёт туда и обратно</legend>${choices.map(c=>`<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="anex-package-choice" value="${esc(c.choiceRef)}" ${c.choiceRef===choice?'checked':''} ${view?.sealed||!repricing&&(pending||error)?'disabled':''}><span>${c.legs.map((leg,n)=>`<strong>${n?'Обратно':'Туда'}</strong><small>${esc(leg.label)}</small>`).join('')}<small>${verified&&c.choiceRef===choice?money(price)+' за всех туристов':'Цена тура с этим перелётом требует расчёта'}</small></span></div></label>`).join('')}</fieldset>`:'')
  +`<p id="anex-package-status" class="${error?'error-text':''}" role="${error?'alert':'status'}">${esc(error||(pending?choices.length?'Уточняем полную цену тура с выбранным перелётом…':'ANEX проверяет выбранный тур…':''))}</p>`;
 const content=providerTourBodyHTML(o,h,flights,price,status);
 showModal('anex-quote',verified?'Тур подтверждён':'Перелёт и цена тура','ANEX · АКТУАЛИЗАЦИЯ',content,true);
 $('#modal').classList.add('tour-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=offerDetailFooterHTML({total:price,pricePending:price===null},verified?(repricing&&!view?.editing&&result.choices?.length>1?'<button class="secondary" data-action="edit-anex-flights">Изменить рейсы</button>':'')+'<button class="primary" data-action="anex-application-preview">К заявке</button>':choices.length&&(repricing||!error)&&!view?.sealed?`<button class="primary" data-action="anex-package-calculate" ${pending?'disabled':''}>Уточнить цену с этими рейсами</button>`:`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button>`,status);
 if(repricing&&view?.editing&&focusName){$$('[name="'+focusName+'"]')?.find(input=>input.value===focusValue)?.focus({preventScroll:true});$('#modal-body').scrollTop=scroll;}
}
async function loadAnexPackageQuote(calculate=false,{fromOffer=false}={}){
 const o=selectedOffer,view=retainedProviderView(o),repricing=view?.result?.repricing?.enabled===true;if(!o||o.provider!=='anex'||view?.sealed||view?.pending&&!repricing)return;
 if(calculate&&modalType!=='anex-quote'||!calculate&&!['anex-current','anex-additional'].includes(modalType)&&!(fromOffer&&modalType==='offer'))return;
 const choice=calculate?$('[name="anex-package-choice"]:checked')?.value:null;if(calculate&&!choice)return;
 let old=calculate?view?.result:null;openAnexPackageQuote(o,old,'',true);
 const requestedView=retainedProviderView(o),run=selectionGeneration;
 try{
  let result=await data.verifyAnexPackage(o,choice);
  if(!calculate&&result.state==='quote_choices'&&result.choices.length===1&&run===selectionGeneration
    &&retainedProviderView(o)===requestedView&&$('#modal').open&&modalType==='anex-quote'&&selectedOffer?.raw===o.raw){
   old=result;openAnexPackageQuote(o,result,'',true);
   result=await data.verifyAnexPackage(o,result.choices[0].choiceRef);
  }
  if(calculate&&repricing&&retainedProviderView(o)?.choice!==choice)return;
  rememberProviderView(o,'anex-quote',result);
  if($('#modal').open&&selectedOffer?.raw===o.raw&&modalType==='anex-quote')openAnexPackageQuote(o,result);
 }catch(error){
  if(!calculate&&!old&&error.code==='quote_operation_pending'&&error.retryable===true&&['anex-current','anex-additional'].includes(view?.type)){
   if(retainedProviderView(o)!==requestedView)return;
   providerViews.set(o.key,view);
   if($('#modal').open&&selectedOffer?.raw===o.raw&&modalType==='anex-quote'){restoreProviderView(o);toast(error.message);}
   return;
  }
  if(calculate&&repricing){
   const current=retainedProviderView(o);if(error.code==='quote_superseded'||!current)return;
   if(error.code!=='quote_pair_budget_exhausted'&&!(error.code==='quote_operation_pending'&&error.retryable===true)){
    current.sealed=true;rememberProviderView(o,'anex-quote',current.result,error.message);anexApplicationDraft=null;
    if($('#modal').open&&selectedOffer?.raw===o.raw&&modalType==='anex-quote')openAnexPackageQuote(o,current.result,error.message);
    return;
   }
   if(current.choice!==choice)return;
  }
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
async function refreshLiveHotel(id,{selectFlights=false,continueSoleFlight=false}={}){
 const o=selectedOffer,h=selectedTourHotel(o);
 if(!o||o.hotelId!==id||!needsRefresh(o)||!h){toast('Не удалось определить варианты отеля. Повторите общий поиск.');return;}
 if(restoreProviderView(o)){if(selectFlights&&['anex-current','anex-additional'].includes(modalType))await loadAnexPackageQuote();return;}
 if(o.provider==='anex'&&o.raw?.anexKind==='concrete'&&o.raw?.anexSessionCurrent===true){
  const run=++selectionGeneration;selectedOffer={...o,loading:true,quoteError:''};renderRealOffer();
  try{
   const current=await data.verifyAnexConcrete(o);
   rememberProviderView(o,'anex-current',current);
   if(run!==selectionGeneration||!$('#modal').open||modalType!=='offer'||selectedOffer?.key!==o.key)return;
   if(selectFlights){selectedOffer={...o,loading:false};await loadAnexPackageQuote(false,{fromOffer:true});}
   else openAnexConcreteCurrent(o,current);
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
   if(quote.state==='flight_selection_required'){
    openAndromedaFlightChoice(o,quote);
    if(continueSoleFlight&&quote.flights.length===2&&quote.flights.filter(f=>f.direction==='0').length===1&&quote.flights.filter(f=>f.direction==='1').length===1)await applyAndromedaFlightChoice();
   }else openAndromedaVerified(o,quote);
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
   if(expanded.offers.length===1&&expanded.offers[0].raw?.anexKind==='concrete')openOffer(expanded.offers[0].key);
   else openAllOffers(id,{flight:'',room:'',meal:'',sort:'price'});
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
function refreshHotel(id,freshSearch=false,options={}){
 if(data.live&&!freshSearch)return refreshLiveHotel(id,options);
 const o=selectedOffer,h=selectedTourHotel(o);
 if(!o||o.hotelId!==id||!needsRefresh(o)||!data.preview&&!h?.legacyIds.length){toast('Не удалось определить варианты отеля. Повторите общий поиск.');return;}
 const next={...structuredClone(o.search),from:o.day,to:o.day,minNights:o.nights,maxNights:o.nights,adults:o.adults,ages:[...o.ages]};
 const exactFilters=defaultFilters();exactFilters.hotelId=id;if(o.meal&&!/уточняется/i.test(o.meal))exactFilters.meals=[o.meal];if(o.operator&&!/уточняется/i.test(o.operator))exactFilters.operators=[o.operator];if(['regular','charter'].includes(o.flight))exactFilters.flight=[o.flight];
 try{data.params(next,h.legacyIds,exactFilters);}catch(error){toast(error.message);return;}
 closeModal();state.search=next;draft=structuredClone(next);draftDestination=null;state.filters=exactFilters;state.selectedDate=null;
 updateSearchUI();updateURL();renderFilters();runSearch({hotelIds:h.legacyIds,exactRefresh:true,exactRefreshTarget:selectedTourOfferSnapshot(o)});
 $('#search-status').scrollIntoView({behavior:scrollBehavior(),block:'start'});
}
function openLeadPreview(source=selectedOffer){
 const current=source,h=selectedTourHotel(current),o=leadReadyOffer(current);if(!current||!h||!o)return;if(needsRefresh(current)||!window.AnyTourPrototypeLead.canApply(o)){renderRealOffer();return;}
 showModal('selected-tour','Заявка на тур',data.live?'ПРОВЕРКА БЕЗ ОТПРАВКИ':'УЧЕБНЫЙ СЦЕНАРИЙ',`${selectionStepsHTML(2,{flightDeferred:!flightPairFor(o)})}${quotePriceChangeHTML(o)}<div class="application-layout"><div class="application-review"><section class="application-choice" aria-label="Выбранный тур"><h3>${esc(h.name)}</h3>${chosenStayHTML(o)}</section>${finalFlightDetailsHTML(o)}${data.live?fuelDisclosureHTML(o):''}</div><div class="application-contact">${window.AnyTourPrototypeLead.markup(o)}</div></div>`);
 $('#modal').classList.add('application-dialog');
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total summary-price"><span>За всех туристов</span><strong>${money(o.total)}</strong><small>${selectedPriceStatus(o)}</small></div>${window.AnyTourPrototypeLead.action(o)}`;
 window.AnyTourPrototypeLead.bind(o);
}

let offerView=null;
const offerGroupKey=o=>encodeURIComponent(o.room+'|'+o.meal);
function restoredOfferGroups(open,value,offers){
 const limits={},entries=value&&typeof value==='object'&&!Array.isArray(value)?Object.entries(value).slice(0,100):[];
 if((!open||!open.length)&&!entries.length)return {open:[],limits};
 const sizes=new Map();for(const offer of offers){const key=offerGroupKey(offer);sizes.set(key,(sizes.get(key)||0)+1);}
 for(const [key,limit] of entries){
  const size=sizes.get(key),max=Math.min(500,(size||0)+7);
  if(size>4&&Number.isInteger(limit)&&limit>=12&&(limit-4)%8===0&&limit<=max)limits[key]=limit;
 }
 return {open:open?open.filter(key=>sizes.has(key)):[],limits};
}
const sharedOfferNote=offers=>{const notes=[...new Set(offers.map(offerMetaNote))];return notes.length===1?notes[0]:'';};
function openAllOffers(id,restored=null){
 const h=hotels.find(h=>h.id===id),all=restored?hotelOffers(h):null;
 offerView={id,departure:'',flight:'',room:'',meal:'',sort:'price',open:[],limits:{}};
 if(restored)offerListRestores.set(offerView,restored);
 if(restored){for(const [name,values] of Object.entries({departure:['',...all.map(o=>o.day)],flight:['','regular','charter','unknown'],room:['',...all.map(o=>o.room)],meal:['',...all.map(o=>o.meal)],sort:['price','date']}))if(values.includes(restored[name]))offerView[name]=restored[name];}
 const restoredOpen=restored?boundedHistoryStrings(restored.open):null;
 if(restored){const groups=restoredOfferGroups(restoredOpen,restored.limits,all);offerView.open=groups.open;offerView.limits=groups.limits;}
 showModal('all-offers',h.name,'ВЫБЕРИТЕ КОНКРЕТНЫЙ ТУР','<div id="all-offers-list" aria-live="polite"></div>',true);
 $('#modal').classList.add('offers-dialog');renderOfferList(restoredOpen===null,all);
}
const offerCountText=n=>`${n} ${n%10===1&&n%100!==11?'тур':n%10>=2&&n%10<=4&&(n%100<12||n%100>14)?'тура':'туров'}`;
const offerSearchContext=()=>state.selectedDate?`Вылет ${dateText(state.selectedDate)}`:state.search.from===state.search.to?`Вылет ${dateText(state.search.from)}`:`Период вылета: ${dateText(state.search.from)} — ${dateText(state.search.to)}`;





const offerRefinementFields=['departure','flight','room','meal'];


function refreshOpenOfferList(){
 if(modalType!=='all-offers'||!offerView)return;
 const body=$('#modal-body'),focus=focusReference(document.activeElement,body),scroll=body.scrollTop;
 renderOfferList();
 if(focus&&document.activeElement!==focus.element)restoreFocus(focus,$('#offer-count'),body);
 body.scrollTop=scroll;
}


// Keep the modal shell/history synchronous; fetch only its cold list renderer.
let offerListLoad=null,offerListRequest=0,offerListPage=null;
const offerListRestores=new WeakMap();
const offerListAsset=document.head.dataset.offerListSrc;
const byId=id=>document.getElementById(id);
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
function renderOfferList(reset=false,initialAll=null){
 offerListPage=null;
 const view=offerView,request=++offerListRequest;
 const current=()=>offerView===view&&request===offerListRequest&&modalType==='all-offers'&&$('#modal').open;
 const render=(owner,all=null)=>{
  if(!current())return;
  const api=owner.create({$,$$,selectedOffer,sameSelectedTourConditions,data,appendGeneratedRoots,byId,cardPriceNote,dateText,durationText,esc,flightLabel,guestsText,hotelOffers,hotels,icon,innerWidth,mealLabel,mealNames,money,needsRefresh,nightsText,offerActionLabel,offerCountText,offerGroupKey,offerMetaNote,offerRefinementFields,offerSearchContext,offerView,operatorBadge,paintGeneratedRoots,rangeText,rememberUIRoute,sharedOfferNote,selectionStepsHTML});
  api.renderOfferList(reset,all);offerListPage={view,api};
  const restored=offerListRestores.get(view);if(restored){offerListRestores.delete(view);const filters=$('.offer-filter-disclosure');if(filters)filters.open=restored.filtersOpen===true;if(Number.isFinite(restored.scroll)&&restored.scroll>=0)$('#modal-body').scrollTop=restored.scroll;rememberUIRoute();}
 };
 if(window.AnyTourOfferList?.create){render(window.AnyTourOfferList,initialAll);return;}
 $('#all-offers-list').innerHTML='<p role="status">Загружаем варианты тура…</p>';
 loadOfferList().then(render).catch(()=>{if(current())$('#all-offers-list').innerHTML='<div role="alert"><p>Не удалось загрузить варианты тура.</p><button class="secondary" data-action="retry-offer-list">Попробовать ещё раз</button></div>';});
}
function renderMoreOfferGroup(key,shown,remove){return offerListPage?.view===offerView&&offerListPage.api.renderMoreGroup?.(key,shown,remove)===true;}
function renderOfferGroup(key){return offerListPage?.view===offerView&&offerListPage.api.renderOfferGroup?.(key)===true;}
function confirmTour(){openLeadPreview();}
function selectedTourHotel(o){return hotels.find(h=>h.id===o?.hotelId)||null;}
function selectedTourOfferSnapshot(o){
 const search=o?.search||{},day=String(o?.day||''),nights=Number(o?.nights),adults=Number(o?.adults),hotelId=Number(o?.hotelId),total=Number(o?.total);
 if(!/^\d{4}-\d{2}-\d{2}$/.test(day)||!Number.isSafeInteger(hotelId)||hotelId<1||!Number.isInteger(nights)||nights<1||!Number.isInteger(adults)||adults<1||!Number.isFinite(total)||total<=0)return null;
 const ages=Array.isArray(o.ages)?o.ages.filter(age=>Number.isInteger(age)&&age>=0&&age<=17).slice(0,3):[];
 return {key:String(o.key||''),hotelId,day,nights,returnDay:addDays(day,nights),variant:Number.isInteger(o.variant)?o.variant:0,total,room:String(o.room||'Номер уточняется'),placement:String(o.placement||''),adults,ages,origin:String(o.origin||search.origin||''),meal:String(o.meal||'Питание уточняется'),operator:String(o.operator||'Туроператор уточняется'),flight:['regular','charter'].includes(o.flight)?o.flight:'unknown',cached:true,provider:String(o.provider||'local'),raw:{selectionEnabled:false},search:{origin:String(search.origin||o.origin||''),country:String(search.country||''),from:String(search.from||day),to:String(search.to||day),minNights:Number(search.minNights)||nights,maxNights:Number(search.maxNights)||nights,adults,ages:[...ages]},savedFuelAmount:fuelAmount(o),savedFlightAllowance:{baggage:flightAllowanceText(o,'baggage'),carryOn:flightAllowanceText(o,'carryOn')},flightChoiceId:null,tour:null,variants:[],savedFlightText:savedFlightTextPlain(o),savedFlightLegs:savedFlightLegs(o),loading:false,flightsLoading:false,pricePending:false};
}
function sameSelectedTourConditions(o,target){return !!o&&!!target&&o.hotelId===target.hotelId&&o.day===target.day&&o.nights===target.nights&&o.adults===target.adults&&JSON.stringify(o.ages||[])===JSON.stringify(target.ages||[])&&o.room===target.room&&o.meal===target.meal&&o.operator===target.operator&&o.flight===target.flight;}

function savedHotelPhoto(h,className){
 if(!h.photos?.length)return `<div class="${className} saved-photo-missing">${icon('image')}<span>Нет фотографий</span></div>`;
 return `<button class="${className}" data-action="gallery" data-id="${h.id}" aria-label="Фотографии ${esc(h.name)}"><img src="${esc(photoUrl(h))}" alt="${esc(h.name)}"></button>`;
}
function savedContext(compact=false,showPriceHelp=true){return `<div class="saved-context"><strong>${esc(state.search.origin)} → ${esc(countryNames[state.search.country]||'')}</strong><span>${departureScopeText()} · ${durationText()} · ${guestsText()}</span>${!showPriceHelp?'':compact?`<details class="saved-explanation"><summary>О ценах и сохранении</summary><p>Цены за всех туристов${filterCount()||state.onlyFavorites?' с учётом текущих фильтров':''}. Актуальность и состав цены — в условиях предложения. Список сохраняется в этом браузере.</p></details>`:`<small>Цены за всех туристов${filterCount()||state.onlyFavorites?' с учётом текущих фильтров':''}. Актуальность и состав цены — в условиях предложения.</small>`}</div>`;}
function savedAvailability(h){const offers=hotelOffers(h);return {hotel:h,offers,offer:offers[0],reason:h.country!==state.search.country?'Другое направление':'Нет туров по текущим фильтрам'};}
function savedRecovery(h){return `<button class="secondary saved-recovery" data-action="show-hotel" data-id="${h.id}">${h.country!==state.search.country?'Новый поиск':'Снять фильтры'}</button>`;}
function refreshSavedView(type,focusSelector){const scroll=$('#modal-body').scrollTop;renderFavorites();$('#modal-body').scrollTop=scroll;if(focusSelector)($(focusSelector)||$('#modal-body button')||$('#modal-title')).focus({preventScroll:true});}
function toggleFavorite(id){const index=state.favorites.indexOf(id);if(index<0)state.favorites.push(id);else state.favorites.splice(index,1);saveStored('anytour.prototype.v18.favorites.v1',state.favorites);updateNav();$$(`[data-action="favorite"][data-id="${id}"]`).forEach(b=>{b.classList.toggle('active',index<0);b.setAttribute('aria-pressed',index<0);const h=hotels.find(h=>h.id===id);b.setAttribute('aria-label',`${index<0?'Убрать из избранного':'В избранное'}: ${esc(h.name)}`)});if(state.onlyFavorites)renderResults({keepFilters:true});if(modalType==='favorites')refreshSavedView('favorites',`#modal-body [data-action="favorite"][data-id="${id}"]`);toast(index<0?'Отель добавлен в избранное':'Отель удалён из избранного');}
function openFavorites(){showModal('favorites','Избранные отели','ВАШ КОРОТКИЙ СПИСОК','',true);renderFavorites();}
function renderFavorites(){
 const entries=state.favorites.map(id=>savedAvailability(hotels.find(h=>h.id===id)));
 $('#modal').classList.add('favorites-dialog');$('#modal-kicker').textContent=`ИЗБРАННОЕ · ${entries.length} ${entries.length===1?'ОТЕЛЬ':entries.length<5?'ОТЕЛЯ':'ОТЕЛЕЙ'}`;
 if(!entries.length){$('#modal-body').innerHTML=`<div class="saved-empty">${icon('heart')}<h3>Сохраните понравившиеся отели</h3><p>Нажмите на сердечко на фотографии. Здесь можно будет сравнить цены и выбрать тур.</p><button class="primary" data-action="close-modal">К отелям</button></div>`;$('#modal-footer').hidden=true;return;}
 $('#modal-body').innerHTML=`${savedContext(true)}<div class="favorite-list">${entries.map(({hotel:h,offer:o,offers,reason})=>`<article class="favorite-item" data-saved-hotel="${h.id}">${savedHotelPhoto(h,'favorite-photo')}<div class="favorite-info"><span class="favorite-stars">${h.stars?h.stars+' ★':'Категория не указана'}${ratingValue(h)!==null?`<span>${ratingText(h)} / 5</span>`:''}</span><h3><button data-action="hotel-details" data-id="${h.id}">${esc(h.name)}</button></h3><p>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</p></div><button class="icon-button favorite-remove" data-action="favorite" data-id="${h.id}" aria-label="Удалить ${esc(h.name)} из избранного">${icon('x')}</button><div class="favorite-summary">${o?`<div class="favorite-price-line"><strong class="saved-price">${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${esc(o.key)}">Этот тур ${icon('arrow')}</button></div><span class="favorite-party">За ${guestsText(o)}</span><span>${esc(cardPriceNote(o))}</span><p>${dateText(o.day)} → ${dateText(o.returnDay)} · ${nightsText(o.nights)}</p><p>${esc(mealLabel(o))} · ${flightLabel(o)}</p><p class="favorite-room"><span>Номер</span> ${esc(o.room)}</p>`:`<strong class="saved-unavailable">${reason}</strong><p>Отель остаётся в избранном.</p>`}</div><div class="favorite-actions">${o?`${offers.length>1?`<button class="text-button favorite-all-offers" data-action="all-offers" data-id="${h.id}">Все туры отеля (${offers.length})</button>`:''}`:savedRecovery(h)}</div></article>`).join('')}</div>${entries.some(e=>!e.offer)?'<p class="saved-recovery-hint">Новый поиск меняет направление и снимает фильтры. Даты и туристы сохраняются.</p>':''}`;
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="only-favorites">Избранное в выдаче</button>`;
}
function openFilters(restore=null){if(innerWidth>1100){$('#results').classList.add('filters-requested');$('#filter-panel').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#hotel-query').focus({preventScroll:true});return}if(filterDraft)return;enterUIHistory();const restored=restoreFilterHistoryModel(restore?.draft);expandedFilterSections.clear();const sections=boundedHistoryStrings(restore?.sections,{max:30});if(restored&&sections)sections.forEach(key=>expandedFilterSections.add(key));filterDraft=restored||structuredClone(appliedFilterModel());const budget=restored&&restore?.budget&&typeof restore.budget==='object'?restore.budget:null;if(budget){filterBudgetEdit={filters:filterDraft.filters,scope:searchKey(state.search),appliedMin:filterDraft.filters.min,appliedMax:filterDraft.filters.max,minText:boundedHistoryText(budget.minText,String(filterDraft.filters.min),32),maxText:boundedHistoryText(budget.maxText,String(filterDraft.filters.max??''),32)}}renderFilters();updateDrawerPreview();$('#filter-panel').classList.add('open');$('#filter-panel').setAttribute('role','dialog');$('#filter-panel').setAttribute('aria-modal','true');$('#filter-backdrop').hidden=false;document.body.style.overflow='hidden';$('main>.search-section').inert=true;$('.header').inert=true;$('.results-heading').inert=true;$('.results-main').inert=true;$('.footer').inert=true;$('.mobile-bottom').inert=true;$('#compact-search').inert=true;$('#filter-panel').scrollTop=restored&&Number.isFinite(restore?.scroll)&&restore.scroll>=0?restore.scroll:0;$('.mobile-close').focus({preventScroll:true});queueMicrotask(rememberUIRoute);}
function closeFilters({apply=false,fromHistory=false,adapt=false}={}){const wasOpen=$('#filter-panel').classList.contains('open');if(!wasOpen)return;let budget=null;if(apply){budget=editFilterBudget();if(!budget.valid&&!adapt){$(budget.invalidMin?'#min-price':'#max-price').focus();return;}}leaveUIHistory(fromHistory);const next=apply?filterDraft:null;filterDraft=null;drawerSuggestions=[];facetQueries.clear();$('#filter-panel').classList.remove('open');$('#filter-panel').removeAttribute('role');$('#filter-panel').removeAttribute('aria-modal');$('#filter-backdrop').hidden=true;document.body.style.overflow='';$$('[inert]').forEach(e=>e.inert=false);if(next){pageReturn=null;state.filters=next.filters;state.onlyFavorites=next.onlyFavorites;state.selectedDate=next.selectedDate;syncFilters();if(budget&&!budget.valid){$(budget.invalidMin?'#min-price':'#max-price').focus({preventScroll:true})}else{const pristine=!!searchEditSession||!state.hasSearched&&!state.onlyFavorites;$(pristine?'#search':'#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$(pristine?'.search-submit':'#results').focus({preventScroll:true})}}else{renderFilters();restorePageReturn();}}
$('#filter-backdrop').addEventListener('click',closeFilters);
function selectDate(day,fromMonth=false){if(fromMonth&&(day<state.search.from||day>state.search.to)){state.search.from=day;state.search.to=day;draft.from=day;draft.to=day}state.selectedDate=!fromMonth&&state.selectedDate===day?null:day;updateSearchUI();renderResults({keepFilters:true});updateURL();if(fromMonth)closeModal();else $(`#price-strip [data-date="${day}"]`)?.focus({preventScroll:true});}
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
 if(!data.preview&&destinationIds(state.filters).some(id=>!destinationHotel(id)?.legacyIds.length)){state.hasSearched=false;editSearch();renderResults();updateSearchUI();return null;}
 resultCalendar.controller?.abort();resultCalendar={key:null,hotels:[],observations:[],basePrices:[],remotePrices:[],observationPrices:[],phase:'idle',controller:null};
 selectionGeneration++;providerViews.clear();selectedOffer=null;andromedaQuoteDraft=null;andromedaApplicationDraft=null;anexCurrentDraft=null;window.AnyTourPrototypeLead.reset();updateNav();state.hasSearched=true;
 const key=searchKey(state.search);searchResponse={key,phase:'loading',operators:[],pending:true,exactRefresh:options.exactRefresh===true};collapseSearch();
 if(options.exactRefreshTarget)searchResponse.exactRefreshTarget=selectedTourOfferSnapshot(options.exactRefreshTarget);
 return {search:state.search,filters:structuredClone(state.filters),hotelIds:options.hotelIds||destinationIds(state.filters).flatMap(id=>destinationHotel(id)?.legacyIds||[]),response:searchResponse};
}
function mergeSearchResults(event){
 const incoming=new Map(),received=event.hotels;let sparse=false;
 for(let index=0,length=received.length;index<length;index++){
  if(!(index in received)){sparse=true;continue}
  const h=received[index];incoming.set(h.id,{...h,offers:h.offers.map(o=>({...o,sourceMeal:o.sourceMeal??o.meal,meal:String(o.meal||'Питание уточняется')}))});
 }
 if(sparse)throw new TypeError('Iterator value undefined is not an entry object');
 const retained=hotels.filter(h=>state.favorites.includes(h.id)),merged=new Map();
 retained.forEach(h=>{const copy={...h,offers:[]};merged.set(copy.id,copy)});
 incoming.forEach(h=>merged.set(h.id,h));
 hotels=[...merged.values()];
 const resultOperators=new Set();
 hotels.forEach(h=>h.offers.forEach(o=>{resultOperators.add(o.operator);if(!data.live)mealNames[o.meal]=o.meal;else if(Number.isSafeInteger(o.mealPlanId)&&o.mealPlanId>0&&o.mealFacet){const previous=mealNames[o.mealFacet];if(previous===undefined||previous===o.mealPlanId)mealNames[o.mealFacet]=o.mealPlanId;}}));
 operators.splice(0,operators.length,...resultOperators);
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
 if(countryChanged){state.filters.resorts=[];state.filters.hotelId=0;state.filters.hotelIds=[];state.filters.q='';state.onlyFavorites=false;}
 if(draftDestination){state.filters.resorts=[...draftDestination.resorts];state.filters.hotelId=draftDestination.hotelId;state.filters.hotelIds=destinationIds(draftDestination);state.filters.q='';draftDestination=null;}
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
 if(t.name==='andromeda-outbound'||t.name==='andromeda-return'){const changed=rememberAndromedaFlightChoice(t);rememberUIRoute();if(changed&&retainedProviderView(selectedOffer)?.result?.repricing?.enabled===true)applyAndromedaFlightChoice();}
 if(t.id==='hotel-room-meal')renderHotelRooms(+t.dataset.id,t.value);
 if(t.name==='flight-pair'&&flightDraft){flightDraft.id=t.value;updateFlightPreview();}
}
function handleOfferRefinementChange(t){
 if(t.name==='anex-package-choice'){const view=retainedProviderView(selectedOffer),repricing=view?.result?.repricing?.enabled===true;if(view?.type==='anex-quote'&&!view.sealed&&(!view.pending&&!view.error||repricing)&&view.result?.choices?.some(c=>c.choiceRef===t.value)&&view.choice!==t.value){view.choice=t.value;if(repricing){view.editing=true;anexApplicationDraft=null;loadAnexPackageQuote(true);}}rememberUIRoute();}
 if(['offer-departure','offer-flight','offer-room','offer-meal','offer-sort'].includes(t.id)){offerView[t.id.replace('offer-','')]=t.value;renderOfferList(true)}
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
 case 'choose-departure':departureChoice=b.dataset.value;renderDepartures();break;
 case 'clear-departure-query':$('#departure-query').value='';renderDepartures();break;
 case 'apply-departure':{const city=departureChoice;if(!data.catalog.departures.some(x=>data.text(x)===city))break;const saved=getStored('anytour.departures.v1',[]);saveStored('anytour.departures.v1',[city,...(Array.isArray(saved)?saved:[]).filter(x=>x!==city)].slice(0,3));closeModal();if(city!==draft.origin){$('#origin').value=city;$('#origin').dispatchEvent(new Event('change',{bubbles:true}));}break;}

 case 'destination':openDestination();break;
 case 'retry-destination':lookupDestination();break;
 case 'destination-countries':destinationCountryList=!destinationCountryList;renderDestination();break;
 case 'destination-country':requestDestination({country:b.dataset.value,resorts:[],hotelId:0,hotelIds:[]},true);break;

 case 'retry-resorts':loadResorts(destinationChoice.country);break;
 case 'destination-all':requestDestination({country:destinationChoice.country,resorts:[],hotelId:0,hotelIds:[]},true);break;

 case 'clear-destination-query':cancelDestinationLookup();destinationResolvedQuery='';destinationHotelLimit=destinationHotelPageSize;$('#destination-query').value='';renderDestination();$('#modal-body').scrollTop=0;$('#destination-query').focus({preventScroll:true});break;
 case 'destination-remove':{if(b.dataset.id)setDestinationIds(destinationChoice,destinationIds(destinationChoice).filter(x=>x!==id));else destinationChoice.resorts=destinationChoice.resorts.filter(r=>r!==b.dataset.value);renderDestination();break}

 case 'destination-more-hotels':{const next=renderMoreDestinationHotels();const firstNew=$$('.destination-hotel')[next];firstNew?.focus({preventScroll:true});firstNew?.scrollIntoView({block:'nearest',behavior:'instant'});break}
 case 'toggle-destination-resorts':destinationResortsExpanded=!destinationResortsExpanded;renderDestination();$('[data-action="toggle-destination-resorts"]')?.focus({preventScroll:true});break;
 case 'destination-resort':{const r=b.dataset.value,c=b.dataset.country,old=destinationChoice;requestDestination({country:c,resorts:old.country===c&&!destinationIds(old).length?(old.resorts.includes(r)?old.resorts.filter(x=>x!==r):[...old.resorts,r]):[r],hotelId:0,hotelIds:[]});break}
 case 'destination-hotel':{const h=destinationHotel(id);if(!h)break;const ids=destinationChoice.country===h.country?destinationIds(destinationChoice):[],next={country:h.country,resorts:[],hotelId:0,hotelIds:[]};setDestinationIds(next,ids.includes(id)?ids.filter(x=>x!==id):[...ids,id]);requestDestination(next);break}
 case 'confirm-destination':if(destinationPending){const next=destinationPending;destinationPending=null;modalBack();settleDestinationChoice(next);}break;
 case 'keep-destination':destinationPending=null;modalBack();break;

 case 'destination-recent':cancelDestinationLookup();destinationResortsExpanded=false;destinationChoice=structuredClone(recentDestinations()[+b.dataset.value]);$('#destination-query').value='';renderDestination();break;
 case 'apply-destination':draftDestination=structuredClone(destinationChoice);draft.country=destinationChoice.country;closeModal();updateSearchUI();if(!state.hasSearched)renderResults();break;
 case 'retry-hotel-restore':restoreURLHotel();break;
 case 'retry-countries':loadCountries(draft.origin);break;
 case 'form-filters':openFormFilters();break;
 case 'stars':openStars();break;
 case 'picker-star':{const n=+b.dataset.value;if(!n)starsDraft=[];else starsDraft=starsDraft.includes(n)?starsDraft.filter(x=>x!==n):[...starsDraft,n];updateStarsPicker();break;}
 case 'apply-stars':pickerFilters().stars=[...starsDraft];finishFilterPicker();break;
 case 'reset-form-filters':{const keep={hotelId:formFiltersDraft.hotelId,hotelIds:destinationIds(formFiltersDraft),resorts:[...formFiltersDraft.resorts]};formFiltersDraft={...defaultFilters(),...keep};renderFormFilters();break;}
 case 'apply-form-filters':state.filters=structuredClone(formFiltersDraft);applyQuickFilters();break;
 case 'child-age':openChildAge(Number(b.dataset.index));break;
 case 'age-pick':ageChoice.value=+b.dataset.value;updateAgeChoice();break;
 case 'apply-age':if(ageChoice.value!==null){guestDraft.ages[ageChoice.index]=ageChoice.value;modalBack();}break;
 case 'dates':openDates();break;case 'meals':openMeals();break;case 'budget':openBudget();break;
 case 'clear-meal-query':$('#meal-query').value='';updateMealPicker();$('#meal-query').focus();break;
 case 'budget-preset':$('#budget-min').value=0;$('#budget-max').value=b.dataset.value;updateBudgetPreview();rememberUIRoute();break;
 case 'budget-adjust-max':$('#budget-max').value=b.dataset.value;updateBudgetPreview();rememberUIRoute();$('[data-action="apply-budget"]').focus({preventScroll:true});break;
 case 'apply-budget':{const budget=readBudget();if(!budget.valid){updateBudgetPreview();$(budget.invalidMin?'#budget-min':'#budget-max').focus();return true}filterBudgetEdit=null;pickerFilters().min=budget.min;pickerFilters().max=budget.max;finishFilterPicker();break}
 case 'nights':openNights();break;case 'guests':openGuests();break;case 'calendar':openCalendar();break;
 case 'top':case 'edit-search':editSearch();break;
 case 'cancel-search-edit':cancelSearchEdit();break;
 case 'month-prev':case 'month-next':{const d=dateObj(calendarMonth);d.setUTCMonth(d.getUTCMonth()+(action==='month-next'?1:-1));calendarMonth=iso(d);renderDateCalendar();loadCalendarPrices();rememberUIRoute();break}
 case 'day-pick':{const day=b.dataset.date;if(dateDraft.phase===0){dateDraft.from=day;dateDraft.to=day;dateDraft.phase=1}else{const range={from:day<dateDraft.from?day:dateDraft.from,to:day<dateDraft.from?dateDraft.from:day};if(dateRangeError(range)){$('#date-error').textContent='Период может включать не больше 22 дат. Осталась выбрана первая дата.';break;}dateDraft.from=range.from;dateDraft.to=range.to;dateDraft.phase=0}updateDateSelection();rememberUIRoute();break}
 case 'apply-dates':{const {from,to}=dateDraft;if(!from||!to||from<startDay||to>endDay||from>to||(dateObj(to)-dateObj(from))/86400000>21){$('#date-error').textContent='Выберите корректный диапазон не больше 21 дня между датами.';return true}const refreshResults=dateContext.source==='results'&&(from!==dateContext.search.from||to!==dateContext.search.to);agesNeedReview=agesNeedReview||draft.ages.length>0&&(draft.from!==from||draft.to!==to);draft.from=from;draft.to=to;if(dateContext.source==='results'){state.search.from=from;state.search.to=to;state.selectedDate=from===to?from:null;state.openHotel=null;renderResults({keepFilters:true})}else if(state.selectedDate){state.selectedDate=null;renderResults({keepFilters:true})}closeModal();updateSearchUI();if(refreshResults)searchLifecycle.requestSubmit();break}
 case 'adults-minus':guestDraft.adults=Math.max(1,guestDraft.adults-1);renderGuests();break;
 case 'adults-plus':guestDraft.adults=Math.min(6,guestDraft.adults+1);renderGuests();break;
 case 'children-minus':guestDraft.ages.pop();renderGuests();break;
 case 'children-plus':if(guestDraft.ages.length<3)guestDraft.ages.push(null);renderGuests();break;
 case 'remove-child':{const index=Number(b.dataset.index);if(!Number.isInteger(index)||index<0||index>=guestDraft.ages.length)break;guestDraft.ages.splice(index,1);renderGuests();const target=$('[data-action="child-age"][data-index="'+Math.min(index,guestDraft.ages.length-1)+'"]')||$('[data-action="children-plus"]');target.focus();break;}
 case 'apply-guests':if(guestDraft.ages.some(a=>a===null)){$('#guest-error').textContent='Укажите возраст каждого ребёнка.';return true}draft.adults=guestDraft.adults;draft.ages=[...guestDraft.ages];agesNeedReview=false;closeModal();updateSearchUI();break;

 case 'night-pick':{const n=+b.dataset.value;if(nightsDraft.phase===1&&Math.abs(n-nightsDraft.min)>10){nightsDraft.error=`Слишком широкий диапазон. Выберите вторую границу от ${Math.max(1,nightsDraft.min-10)} до ${Math.min(28,nightsDraft.min+10)} ночей.`;renderNightSelection();break;}nightsDraft.error='';if(nightsDraft.phase===0){nightsDraft.min=n;nightsDraft.max=n;nightsDraft.phase=1}else{nightsDraft.max=Math.max(nightsDraft.min,n);nightsDraft.min=Math.min(nightsDraft.min,n);nightsDraft.phase=0}renderNightSelection();break}
 case 'night-preset':nightsDraft={min:+b.dataset.value,max:+b.dataset.value,phase:0};renderNightSelection();break;
 case 'apply-nights':agesNeedReview=agesNeedReview||draft.ages.length>0&&(draft.minNights!==nightsDraft.min||draft.maxNights!==nightsDraft.max);draft.minNights=nightsDraft.min;draft.maxNights=nightsDraft.max;closeModal();updateSearchUI();break;
 default:return false;
 }
 return true;
}
function handleResultFilterAction(action,b,id){
 switch(action){
 case 'category-filters':{openFilters();const heading=$('#filters .star-options')?.closest('.filter-group')?.querySelector('h4');if(heading)jumpToFilterSection(heading.id);break;}
 case 'toggle-filter-section':{const group=b.closest('.filter-group');setFilterSectionOpen(group,b.getAttribute('aria-expanded')!=='true');rememberUIRoute();break;}
 case 'back-filter-overview':setFilterSectionOpen($('.filter-operator-group'),false);rememberUIRoute();break;
 case 'clear-facet-query':{const host=b.closest('.facet-options'),input=host.querySelector('[data-facet-search]');facetQueries.delete(host.dataset.facetOptions);input.value='';applyFacetSearch(host);input.focus({preventScroll:true});break;}
 case 'remove-facet-choice':{const host=b.closest('.facet-options'),group=host.dataset.facetOptions,f=editingFilterModel().filters;f[group]=f[group].filter(value=>value!==b.dataset.value);host.querySelectorAll('[data-filter]').forEach(input=>input.checked=f[group].includes(input.value));filterEdited();applyFacetSearch(host);host.querySelector('[data-facet-search]').focus({preventScroll:true});break;}
 case 'clear-hotel-query':editingFilterModel().filters.q='';$('#hotel-query').value='';$('.clear-hotel-query').hidden=true;filterEdited();$('#hotel-query').focus({preventScroll:true});break;
 case 'apply-meals':pickerFilters().meals=[...mealDraft];finishFilterPicker();break;
 case 'any-stars':state.filters.stars=[];syncFilters();break;
 case 'filters':if(!$('#search-form').hidden)openFormFilters();else openFilters();break;case 'close-filters':closeFilters();break;case 'apply-filters':closeFilters({apply:true});break;
 case 'review-filter-recovery':if(filterDraft)jumpToFilterSection('drawer-recovery');break;
 case 'reset':if(filterDraft&&b.closest('#filter-panel')){filterDraft={filters:defaultFilters(),onlyFavorites:false,selectedDate:null};filterEdited(true)}else resetFilters();break;
 case 'all-hotels':state.onlyFavorites=false;renderResults();break;
 case 'star':{const n=+b.dataset.value,a=(b.closest('#filter-panel')?editingFilterModel().filters:state.filters).stars,i=a.indexOf(n);if(n===0)a.length=0;else if(i<0)a.push(n);else a.splice(i,1);filterEdited(true);if(filterDraft)$(`#filter-panel [data-action="star"][data-value="${n}"]`)?.focus({preventScroll:true});break}
 case 'preset':state.filters[b.dataset.preset]=!state.filters[b.dataset.preset];syncFilters();break;
 case 'remove-filter':{const model=appliedFilterModel();removeModelFilter(model,b.dataset.key,b.dataset.value);state.onlyFavorites=model.onlyFavorites;state.selectedDate=model.selectedDate;syncFilters();break}
 case 'remove-draft-filter':if(filterDraft){removeModelFilter(filterDraft,b.dataset.key,b.dataset.value);filterEdited(true);$('#filter-panel .mobile-close').focus({preventScroll:true})}break;
 case 'recover-filters':{const choice=(b.dataset.source==='drawer'?drawerSuggestions:emptySuggestions)[+b.dataset.value];if(!choice)break;if(b.dataset.source==='drawer'&&filterDraft){filterDraft=structuredClone(choice.model);filterEdited(true);$('#apply-filters').focus({preventScroll:true})}else{state.filters=structuredClone(choice.model.filters);state.onlyFavorites=choice.model.onlyFavorites;state.selectedDate=choice.model.selectedDate;syncFilters();$('#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#results').focus({preventScroll:true})}break;}
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
 case 'select-anex-tour':if(selectedOffer)refreshLiveHotel(selectedOffer.hotelId,{selectFlights:true});break;
 case 'retry-flight-picker':if(modalType==='flights')renderFlightPicker();break;
 case 'retry-search':if(!searchResponse.pending)runSearch({retain:true,exactRefresh:searchResponse.exactRefresh});break;
 case 'continue-search':if(!searchResponse.pending&&searchResponse.canContinue)data.continueSearch();break;
 case 'stop-search':stopSearch();break;
 case 'modal-back':modalBack();break;case 'close-modal':cancelPicker();break;
 case 'hotel-details':openHotelDetails(id,b.dataset.target||'');break;
 case 'change-room':{const previous=modalHistory.at(-1);if(['hotel-details','all-offers'].includes(previous?.type))modalBack();else if(selectedOffer)openAllOffers(selectedOffer.hotelId);break;}
 case 'hotel-gallery':openGallery(id,+b.dataset.value);break;
 case 'hotel-room-offers':openAllOffers(id);offerView.room=b.dataset.room;offerView.meal=b.dataset.meal||'';renderOfferList(true);break;
 case 'hotel-detail-offers':openAllOffers(id);offerView.meal=b.dataset.meal||'';renderOfferList(true);break;
 case 'hotel-section':{const body=$('#modal-body'),target=body.querySelector('#'+CSS.escape(b.dataset.target));if(target){target.focus({preventScroll:true});const top=target.getBoundingClientRect().top-body.getBoundingClientRect().top+body.scrollTop-($('.hotel-section-nav')?.offsetHeight||0)-16;body.scrollTo({top:Math.max(0,top),behavior:scrollBehavior()});}break;}
 case 'card-photo-index':case 'card-photo':{const h=hotels.find(h=>h.id===id);if(!h?.photos.length)break;const idx=action==='card-photo-index'?+b.dataset.value:((state.photoIndexes[id]||0)+(+b.dataset.dir)+h.photos.length)%h.photos.length;state.photoIndexes[id]=idx;$('#hotel-'+id+' .hotel-image').src=photoUrl(h,idx);$('#hotel-'+id+' .photo-index').textContent=idx+1;$$('#hotel-'+id+' .card-thumb').forEach((el,i)=>{el.classList.toggle('active',i===idx);el.setAttribute('aria-pressed',i===idx)});break;}

 case 'all-offers':{
  const view=retainedProviderView(selectedOffer);
  if(modalType==='andromeda-flights'&&view?.sealed&&view.result?.repricing?.enabled===true&&selectedOffer?.hotelId===id){
   let index=-1;for(let i=modalHistory.length-1;i>=0;i--)if(modalHistory[i].type==='all-offers'&&modalHistory[i].route?.id===id){index=i;break;}
   const previous=index>=0?modalHistory[index]:null;
   if(previous)modalHistory.length=index;
   else for(let i=modalHistory.length-1;i>=0;i--)if(['andromeda-flights','andromeda-verified','provider-application'].includes(modalHistory[i].type)&&modalHistory[i].offer?.raw===selectedOffer.raw)modalHistory.splice(i,1);
   restoringModal=true;
   try{if(previous)reopenUIRoute(previous.route);else openAllOffers(id,offerView?.id===id?{...offerView}:null);}finally{restoringModal=false;}
   if(previous)restoreModalStepFocus(previous);rememberUIRoute();
  }else openAllOffers(id);
  break;
 }
 case 'retry-hotel-details':if(modalType==='hotel-details')renderHotelDetails();break;
 case 'retry-offer-list':if(modalType==='all-offers')renderOfferList(true);break;
 case 'offer-flights':case 'start-tour-flights':openOffer(b.dataset.key,null,true);break;
 case 'offer-group':{const key=b.dataset.value;offerView.open=offerView.open.includes(key)?offerView.open.filter(x=>x!==key):[...offerView.open,key];if(!renderOfferGroup(key))renderOfferList();byId('group-'+key)?.previousElementSibling?.focus({preventScroll:true});break}
 case 'group-more':{const key=b.dataset.value,body=$('#modal-body'),scroll=body.scrollTop,shown=offerView.limits[key]||4,previous=new Set($$('#all-offers-list .grouped-offer').map(row=>row.dataset.offerKey));offerView.limits[key]=shown+8;if(!renderMoreOfferGroup(key,shown,b))renderOfferList();body.scrollTop=scroll;const firstNew=$$('#all-offers-list .grouped-offer').find(row=>!previous.has(row.dataset.offerKey));firstNew?.querySelector('[data-action="offer"]')?.focus({preventScroll:true});firstNew?.scrollIntoView({behavior:scrollBehavior(),block:'nearest'});break;}
 case 'remove-offer-filter':if(offerRefinementFields.includes(b.dataset.field)&&offerView){offerView[b.dataset.field]='';renderOfferList(true);$('#offer-count').focus();}break;
 case 'reset-offer-filters':offerView.departure='';offerView.flight='';offerView.room='';offerView.meal='';renderOfferList(true);break;
 case 'offer':openOffer(cardEntryOfferKey(b));break;case 'start-lead':openLeadWithQuote(b.dataset.key);break;case 'confirm-tour':confirmTour();break;case 'andromeda-application-preview':openAndromedaApplicationPreview();break;case 'anex-application-preview':openAnexApplicationPreview();break;case 'apply-andromeda-flights':applyAndromedaFlightChoice();break;case 'anex-additional-prices':applyAnexAdditionalPrices();break;case 'anex-flights':loadAnexFlightInventory();break;case 'anex-package-quote':loadAnexPackageQuote();break;case 'anex-package-calculate':loadAnexPackageQuote(true);break;
 case 'edit-andromeda-flights':{const view=retainedProviderView(selectedOffer);if(view?.result?.repricing?.enabled===true&&!view.pending&&!view.sealed){view.editing=true;openAndromedaFlightChoice(selectedOffer,view.result);}break;}
 case 'edit-anex-flights':{const view=retainedProviderView(selectedOffer);if(view?.result?.repricing?.enabled===true&&!view.pending&&!view.sealed){view.editing=true;openAnexPackageQuote(selectedOffer,view.result);}break;}
 case 'gallery':openGallery(id,state.photoIndexes[id]||0);break;case 'gallery-next':case 'gallery-prev':{const count=hotels.find(h=>h.id===gallery.id).photos.length;gallery.index=(gallery.index+(action==='gallery-next'?1:-1)+count)%count;renderGallery();break}
 case 'gallery-index':gallery.index=+b.dataset.value;renderGallery();break;
 case 'favorite':toggleFavorite(id);break;case 'favorites':openFavorites();break;
 case 'only-favorites':state.onlyFavorites=true;closeModal();renderResults();$('#results').scrollIntoView({behavior:scrollBehavior()});break;
 case 'show-hotel':{const h=hotels.find(h=>h.id===id);state.search.country=h.country;draft=structuredClone(state.search);draftDestination=null;state.filters=defaultFilters();state.filters.hotelId=h.id;state.onlyFavorites=false;state.selectedDate=null;closeModal();syncFilters();updateURL();$('#results').scrollIntoView({behavior:scrollBehavior()});break}
 case 'operator-info':toast('Туроператор: '+b.dataset.operator);break;
 case 'about':showModal('about','Версия для проверки','ANYTOUR SEARCH3',aboutDisclosureHTML());break;
 }
});
document.addEventListener('keydown',e=>{
 if(modalType==='gallery'&&['ArrowLeft','ArrowRight'].includes(e.key)){e.preventDefault();gallery.index=(gallery.index+(e.key==='ArrowRight'?1:-1)+hotels.find(h=>h.id===gallery.id).photos.length)%hotels.find(h=>h.id===gallery.id).photos.length;renderGallery()}
 if($('#filter-panel').classList.contains('open')){if(e.key==='Escape'){e.preventDefault();if($('#filter-panel').classList.contains('filter-detail-open')){setFilterSectionOpen($('.filter-operator-group'),false);rememberUIRoute();}else closeFilters()}if(e.key==='Tab'){const els=$$('#filter-panel button:not([disabled]),#filter-panel input,#filter-panel select').filter(el=>el.getClientRects().length),first=els[0],last=els.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}}}
});
let touchStart=null;
$('#modal-body').addEventListener('touchstart',e=>{touchStart=null;if(modalType==='gallery'&&e.touches.length===1&&e.target.closest('.gallery-stage')&&!e.target.closest('button'))touchStart={x:e.touches[0].clientX,y:e.touches[0].clientY,id:gallery.id};},{passive:true});
$('#modal-body').addEventListener('touchcancel',()=>{touchStart=null;},{passive:true});
$('#modal-body').addEventListener('touchend',e=>{const start=touchStart;touchStart=null;if(modalType!=='gallery'||!start||start.id!==gallery.id||!e.changedTouches.length)return;const dx=e.changedTouches[0].clientX-start.x,dy=e.changedTouches[0].clientY-start.y;if(Math.abs(dx)>45&&Math.abs(dx)>Math.abs(dy)*1.5){const count=hotels.find(h=>h.id===gallery.id).photos.length;gallery.index=(gallery.index+(dx<0?1:-1)+count)%count;renderGallery();}},{passive:true});
window.addEventListener('resize',()=>{
 if(innerWidth>1100&&$('#filter-panel').classList.contains('open'))closeFilters({apply:true,adapt:true});
 if(modalType!=='dates')return;
 if(calendarMobile!==(innerWidth<=760)){renderDateCalendar();syncCalendarViewport();revealCalendarMonth();loadCalendarPrices();}
 else syncCalendarViewport();
});

function refreshCalendarPrices(){
 if(typeof calendarPriceContext==='undefined'||calendarPriceContext!==dateContext)refreshCalendarPriceCache();
 else{calendarBasePrices=calendarPriceInventory(hotels,[],dateContext);applyCalendarPriceInventories();}
 renderCalendarPrices();
}
function renderCalendarPrices(){
 $$('#date-calendar .calendar-month').forEach(month=>{
  const cells=[...month.querySelectorAll('.month-day:not([disabled])')],prices=cells.map(b=>calendarPrice(b.dataset.date)),min=minimumKnownPrice(prices);
  cells.forEach((b,i)=>{const price=prices[i];b.classList.toggle('is-cheap',price!==null&&price===min);b.querySelector('small').textContent=price===null?'—':shortAmount(price,true);b.setAttribute('aria-label',dateLong(b.dataset.date)+(price===null?', цена пока неизвестна':', от '+money(price)));});
 });
 renderDateSelectionPrice();
}
function loadCalendarPrices(){
 calendarRequest?.abort();calendarObserver?.disconnect();calendarRequest=new AbortController();const controller=calendarRequest,ctx=dateContext,loads=new Map();calendarLoads=loads;
 calendarHotels=[];calendarObservations=[];if(calendarPriceContext!==ctx)prepareCalendarBasePrices(ctx);else{calendarMonthPrices=new Map();applyCalendarPriceInventories();}renderCalendarPrices();
 if(!catalogReady){$('.date-choice-tools').setAttribute('aria-busy',String(!catalogError));$('[data-calendar-load-status]').hidden=false;$('[data-calendar-load-status]').textContent=catalogError?'Даты можно выбрать без цены':'Загружаем направления…';return;}
 const legend=()=>{if(controller.signal.aborted||dateContext!==ctx||modalType!=='dates')return;const phases=[...loads.values()],node=$('.calendar-legend'),loading=phases.includes('loading');$('.date-choice-tools').setAttribute('aria-busy',String(loading));$('[data-calendar-load-status]').textContent=loading?'Открываем цены…':'Весь тур · тыс. ₽';node.hidden=!phases.includes('error');node.querySelector('span').textContent=phases.includes('error')?'Не все цены загрузились. Даты можно выбрать без цены.':'';renderDateSelectionPrice();};
 const read=async month=>{
  if(loads.has(month)||controller.signal.aborted)return;loads.set(month,'loading');legend();
  const from=month<startDay?startDay:month,next=dateObj(month);next.setUTCMonth(next.getUTCMonth()+1);next.setUTCDate(0);const to=iso(next)>endDay?endDay:iso(next);
  const show=snapshot=>{if(controller.signal.aborted||dateContext!==ctx||modalType!=='dates')return;calendarMonthPrices.set(month,calendarPriceInventory(snapshot.hotels,snapshot.observations,ctx));applyCalendarPriceInventories();renderCalendarPrices();};
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
 const favorites=validIds(getStored('anytour.prototype.v18.favorites.v1',[])),ids=favorites;if(!ids.length)return;const rows=await data.savedHotels(ids,state.search);if(state.hasSearched)return;hotels=rows;state.favorites=favorites.filter(id=>rows.some(h=>h.id===id));updateNav();
}
async function restoreURLHotel(){
 const place=currentDraftDestination(),ids=destinationIds(place),country=place.country;
 if(!ids.length||hotelRestorePending)return;
 const origin=draft.origin,generation=catalogLoadGeneration,key=JSON.stringify(ids);
 const current=()=>generation===catalogLoadGeneration&&draft.origin===origin&&currentDraftDestination().country===country&&JSON.stringify(destinationIds(currentDraftDestination()))===key;
 hotelRestorePending=true;updateSearchUI();renderResults();
 try{await Promise.all(ids.filter(id=>!destinationHotel(id)?.legacyIds.length).map(async id=>{
  try{const h=await data.restoreHotel(id,country);if(current()&&h.id===id&&String(h.country)===String(country)&&h.legacyIds?.length)destinationHotels.set(id,h);}
  catch{/* Keep every unresolved selected ID for explicit retry or reselection. */}
 }));}
 finally{hotelRestorePending=false;updateSearchUI();renderResults();if(modalType==='destination')renderDestination();}
}
async function bootRealData(){
 catalogReady=false;$('.search-submit').disabled=true;catalogError='';updateSearchUI();if(modalType==='destination')renderDestination();
 try{applyCatalog(await data.init(new URLSearchParams(location.search).get('origin')||draft.origin));state.search=structuredClone(draft);restoreURL();urlStateHydrated=true;await loadResorts(state.search.country);catalogReady=true;updateSearchUI();renderResults();if(modalType==='destination'){if(!countryNames[destinationChoice.country])destinationChoice=structuredClone(currentDraftDestination());lookupDestination();}if(modalType==='dates'){dateContext=createDateContext(dateContext?.source==='results'?'results':'form');renderCalendarScope();loadCalendarPrices();}await restoreURLHotel();if(!data.live)await restoreSavedHotels();$('#fixture-description').textContent=data.describe();if(data.live){const requestedSearch=new URLSearchParams(location.search).get('searched')==='1';state.hasSearched=requestedSearch;renderFilters();renderResults();updateSearchUI();if(requestedSearch)runSearch();}else{state.hasSearched=true;runSearch();}}
 catch(error){catalogError=error.message;$('#cards').innerHTML=`<div class="empty"><h3>Не удалось загрузить направления</h3><p>${esc(error.message)}</p><button class="primary" data-action="retry-catalog">Повторить</button></div>`;if(modalType==='destination')renderDestination();}
}
document.addEventListener('click',event=>{const b=event.target.closest('[data-action]');if(!b||b.disabled)return;if(b.dataset.action==='refresh-hotel')refreshHotel(Number(b.dataset.id),false,{continueSoleFlight:true});if(b.dataset.action==='retry-flights')loadRealFlights(selectionGeneration,{chooseFlight:true});if(b.dataset.action==='retry-catalog')bootRealData();});
document.addEventListener('error',event=>{const img=event.target;if(img.tagName==='IMG'&&img.classList.contains('destination-hotel-image')){img.hidden=true;return;}if(img.tagName==='IMG'&&img.classList.contains('hotel-image')){img.closest('.hotel-photos')?.classList.add('photo-unavailable');img.removeAttribute('src');img.alt='Фото пока недоступно';}},true);

async function switchFixture(value){
 if(data.live||value==='live'){clearSearchTimers();location.assign(location.pathname.replace(/index\.html$/,'')+(value==='live'?'':'?scenario='+encodeURIComponent(value)));return;}
 searchEditSession=null;
 clearSearchTimers();closeModal();window.AnyTourPrototypeLead.reset();await data.setScenario(value);hotels=[];destinationHotels.clear();state.search=structuredClone(data.initialSearch);draft=structuredClone(state.search);state.filters=defaultFilters();state.selectedDate=null;state.favorites=[];state.onlyFavorites=false;state.hasSearched=false;draftDestination=null;
 for(const key of Object.keys(mealNames))delete mealNames[key];operators.splice(0);applyCatalog(await data.init());catalogReady=true;$('#fixture-description').textContent=data.describe();updateSearchUI();state.hasSearched=true;runSearch();
}
window.addEventListener('anytour:data-status',()=>{$('#fixture-description').textContent=data.describe();});
$('#fixture-scenario').value=data.scenario;
$('#fixture-scenario').addEventListener('change',event=>switchFixture(event.target.value).catch(error=>{catalogReady=false;toast(error.message);$('#fixture-description').textContent=error.message;}));
document.addEventListener('click',event=>{if(event.target.closest('[data-action="more-cards"]')){const next=renderMoreResultCards();$('#cards').children[next]?.querySelector('button')?.focus({preventScroll:true});}});

const initialUIRoute=history.state?.[uiHistoryKey];
hydrate();renderResults();bootRealData();
if(initialUIRoute)addEventListener('pageshow',()=>restoreHistoryView(initialUIRoute),{once:true});
// The trip bar follows this document's viewport, including in the responsive preview.
function updateCompactSearch(){compactSearchFrame=0;const mobileResults=innerWidth<=760&&state.hasSearched&&$('#search-form').hidden&&!searchEditSession;document.body.classList.toggle('mobile-results',mobileResults);$('#compact-search').hidden=!mobileResults&&$('#search').getBoundingClientRect().bottom>88;}
function scheduleCompactSearch(){if(!compactSearchFrame)compactSearchFrame=requestAnimationFrame(updateCompactSearch);}
addEventListener('scroll',scheduleCompactSearch,{passive:true});
addEventListener('resize',scheduleCompactSearch);
new IntersectionObserver(scheduleCompactSearch,{threshold:0}).observe($('#search'));
updateCompactSearch();
})();
