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
const money = n => Number(n).toLocaleString('ru-RU',{maximumFractionDigits:2})+' ₽';
const shortMoney = n => (n/1000).toLocaleString('ru-RU',{maximumFractionDigits:1})+' тыс.';
const dateObj = s=>new Date(s+'T12:00:00Z');
const iso = d=>d.toISOString().slice(0,10);
const addDays = (s,n)=>iso(new Date(dateObj(s).getTime()+n*86400000));
const dateText = s=>dateObj(s).toLocaleDateString('ru-RU',{day:'numeric',month:'short',timeZone:'UTC'}).replace('.','');
const dateLong = s=>dateObj(s).toLocaleDateString('ru-RU',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'});
const flightDateText = s=>/^\d{4}-\d{2}-\d{2}$/.test(String(s||''))?dateText(s):String(s||'Дата уточняется');
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
function selectionStepsHTML(step){return `<ol class="selection-steps" aria-label="Этапы выбора тура">${['Номер и питание','Перелёт','Заявка'].map((label,i)=>`<li ${i===step?'aria-current="step"':''} class="${i===step?'current':i<step?'previous':''}"><span aria-hidden="true">${i+1}</span>${label}</li>`).join('')}</ol>`;}
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
let draftDestination=null,destinationChoice=null,draft=structuredClone(state.search),modalType='',guestDraft={},nightsDraft={},dateDraft={},calendarMonth=startDay.slice(0,7)+'-01',gallery={id:null,index:0},selectedOffer=null,searchTimer=null,searchStageTimer=null,hotelRoomObserver=null;
let searchResponse={key:'',phase:'complete',operators:[...operators],pending:false};
const searchKey=s=>JSON.stringify([s.origin,s.country,s.from,s.to,s.minNights,s.maxNights,s.adults,s.ages]);
const responseFor=s=>searchResponse.key===searchKey(s)?searchResponse:{phase:'complete',operators,pending:false};
const ratingValue=h=>Number.isFinite(h.rating)&&h.rating>0&&h.rating<=5?h.rating:null;
const ratingText=h=>ratingValue(h)===null?'—':ratingValue(h).toLocaleString('ru-RU',{minimumFractionDigits:1,maximumFractionDigits:1});
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
 state.filters.q=p.get('q')||'';state.filters.rating=p.get('rating')==='1';
 if(['recommended','price','rating'].includes(p.get('sort')))state.sort=p.get('sort');
 $('#sort').value=state.sort;
 state.hasSearched=false;draft=structuredClone(s);
}
let searchEditSession=null;
function updateURL(){if(searchEditSession)return;const p=new URLSearchParams();p.set('scenario',data.scenario);for(const [k,v] of Object.entries(state.search))p.set(k,Array.isArray(v)?v.join(','):v);if(state.selectedDate)p.set('date',state.selectedDate);if(state.hasSearched)p.set('searched','1');if(state.onlyFavorites)p.set('favorites','1');const f=state.filters;for(const k of ['stars','meals','resorts','operators','flight','amenities'])if(f[k].length)p.set(k,f[k].join('|'));for(const k of ['beach','rating','family','spa'])if(f[k])p.set(k,'1');if(f.hotelId)p.set('hotel',f.hotelId);if(f.q)p.set('q',f.q);if(f.min)p.set('min',f.min);if(f.max!==null)p.set('max',f.max);if(state.sort!=='recommended')p.set('sort',state.sort);const query='?'+p.toString();if(location.search!==query||location.hash)history.replaceState(history.state,'',query);}
function renderSummary(){const s=state.search,f=state.filters;$('#applied-search').innerHTML=`<div class="applied-main"><div class="applied-route"><span class="summary-icon">${icon('plane')}</span><div><small>Маршрут</small><strong>${esc(s.origin)}<span>→</span>${esc(destinationLabel(appliedDestination()))}</strong></div></div><dl class="applied-trip"><div><dt>${departureScopeLabel(s)}</dt><dd>${departureScopeValue(s)}</dd></div><div><dt>Отдых</dt><dd>${durationText()}</dd></div><div><dt>Туристы</dt><dd>${guestsText()}</dd></div></dl><button class="secondary" data-action="edit-search" aria-controls="search-form" aria-expanded="false">${icon('sliders')} Изменить</button></div><div class="applied-extras"><button type="button" data-action="category-filters" aria-label="Категория отеля">${f.stars.length?f.stars.join(' / ')+' ★':'Любая категория'}</button><button type="button" data-action="meals">${f.meals.length?f.meals.map(esc).join(', '):'Любое питание'}</button><button type="button" data-action="budget" aria-label="Бюджет за всех: ${budgetText()}">${f.min||f.max!==null?budgetText():'Без лимита'}</button><button type="button" data-action="filters">Все фильтры${filterCount()?' · '+filterCount():''}</button></div>`;}
function collapseSearch(){renderSummary();$('#search-form').hidden=true;$('.intro').hidden=true;$('#applied-search').hidden=false;$('#search').classList.add('search-collapsed');document.body.classList.remove('search-editing');renderCompactRefinements();}
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
 updateSearchUI();renderCompactRefinements();$('#search').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#origin').focus({preventScroll:true});
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
 const places=hotelPlaces(h);
 return h.country===s.country&&(!f.hotelId||h.id===f.hotelId)
  &&matchesHotelQuery(h,f.q)
  &&(!f.stars.length||f.stars.includes(h.stars))
  &&(!f.resorts.length||f.resorts.some(resort=>places.includes(resort)))
  &&(f.amenities||[]).every(key=>(h.amenities||[]).some(a=>a.key===key))
  &&(!f.rating||ratingValue(h)>=4.5)&&(!f.beach||h.beach!==null&&h.beach<=150)&&(!f.family||h.family)&&(!f.spa||h.spa)
  &&(!onlyFavorites||s.country!==state.search.country||state.favorites.includes(h.id));
}
function hotelOffers(h,options={}){
 if(!h)return[];
 const s=options.search||state.search,f=options.filters||state.filters,selected=Object.hasOwn(options,'selectedDate')?options.selectedDate:state.selectedDate;
 const from=options.day||(selected&&!options.ignoreDate?selected:s.from),to=options.day||(selected&&!options.ignoreDate?selected:s.to);
 if(!hotelMatch(h,f,s,options.onlyFavorites??state.onlyFavorites))return[];
 return (h.offers||[]).filter(o=>o.search.origin===s.origin&&o.search.country===s.country&&o.adults===s.adults&&JSON.stringify([...o.ages].sort())===JSON.stringify([...s.ages].sort())&&o.day>=from&&o.day<=to&&o.nights>=s.minNights&&o.nights<=s.maxNights&&matchesMeal(o,f.meals)&&o.total>=f.min&&(f.max===null||o.total<=f.max)&&(!f.operators.length||f.operators.includes(o.operator))&&(!f.flight.length||f.flight.includes(o.flight))).sort((a,b)=>a.total-b.total||a.day.localeCompare(b.day));
}
function recommendedHotelScore(h){return (ratingValue(h)??0)+(h.beach!==null&&h.beach<=150?.2:0)+(popularity?.boost(h)||0);}
function recommendedHotelRank(h){const rank=popularity?.rank(h);return Number.isInteger(rank)?rank:Number.MAX_SAFE_INTEGER;}
function results(){return hotels.map(h=>({hotel:h,offers:hotelOffers(h)})).filter(r=>r.offers.length).sort((a,b)=>state.sort==='price'?a.offers[0].total-b.offers[0].total:state.sort==='rating'?(ratingValue(b.hotel)??0)-(ratingValue(a.hotel)??0):recommendedHotelScore(b.hotel)-recommendedHotelScore(a.hotel)||recommendedHotelRank(a.hotel)-recommendedHotelRank(b.hotel)||a.offers[0].total-b.offers[0].total);}
function minimumForDay(day,options={}){let min=Infinity;const source=options.calendarHotels||hotels;source.forEach(h=>{const offers=hotelOffers(h,{...options,day,ignoreDate:true,onlyFavorites:false});if(offers.length)min=Math.min(min,offers[0].total)});return Number.isFinite(min)?min:null;}
function calendarMinimum(day,options,observations=[]){const actual=minimumForDay(day,options),s=options.search||state.search,f=options.filters||state.filters,saved=data.observationScopeSupported(s,f)?observations.find(point=>point.date===day)?.price:null,values=[actual,saved].filter(value=>Number.isFinite(value)&&value>0);return values.length?Math.min(...values):null;}
function filterCount(){return Object.entries(state.filters).reduce((n,[k,v])=>n+(Array.isArray(v)?v.length:k==='max'?(v!==null?1:0):k==='min'?(v>0?1:0):v?1:0),0);}
function toast(msg){const t=$('#toast');t.textContent=msg;t.hidden=false;clearTimeout(toast.timer);toast.timer=setTimeout(()=>t.hidden=true,3600);}
const draftSelectedDate=()=>draft.from===state.search.from&&draft.to===state.search.to?state.selectedDate:null;
function updateSearchUI(){
 const count=filterCount();$('#filter-count').textContent=count?`(${count})`:'';
 $('#origin').value=draft.origin;const place=currentDraftDestination();$('#destination-label').textContent=destinationLabel(place);$('#country').title=destinationLabel(place,true);$('#country').setAttribute('aria-label','Направление: '+destinationLabel(place,true));
 $('#dates-label').textContent=draftSelectedDate()?dateText(draftSelectedDate()):rangeText(draft.from,draft.to);$('#nights-label').textContent=durationText(draft);$('#guests-label').textContent=guestsText(draft);
 $$('#quick-stars button').forEach(b=>{const active=b.dataset.action==='any-stars'?!state.filters.stars.length:state.filters.stars.includes(+b.dataset.value);b.setAttribute('aria-pressed',active);b.classList.toggle('active',active)});
 const meals=state.filters.meals.join(' · ');$('#meal-label').textContent=state.filters.meals.length>1?state.filters.meals.length+' варианта':meals||'Любое';$('#quick-meal').setAttribute('aria-label','Питание: '+(meals||'любое'));$('#quick-meal').title=meals||'Любое питание';
 $('#budget-label').textContent=budgetText();
 $('.search-submit').disabled=!catalogReady||!!(place.hotelId&&!data.preview&&!destinationHotel(place.hotelId)?.legacyIds.length);
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
let destinationLookup={status:'idle',rows:[]},destinationRequest=null,destinationTimer=null,catalogError='',catalogReady=false,hotelRestorePending=false;
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
function destinationLabel(d,full=false){const h=destinationHotel(d.hotelId);if(h)return full?`${h.name}, ${h.resort}, ${countryNames[h.country]||''}`:h.name;if(d.hotelId)return hotelRestorePending?'Восстанавливаем отель…':'Выбранный отель недоступен';if(d.resorts.length)return full?`${countryNames[d.country]||''} · ${d.resorts.join(', ')}`:d.resorts.length===1?d.resorts[0]:`${countryNames[d.country]||''} · ${d.resorts.length} ${d.resorts.length<5?'курорта':'курортов'}`;return countryNames[d.country];}
function recentDestinations(){const v=getStored('anytour.prototype.v18.destinations.v1',[]);return (Array.isArray(v)?v:[]).filter(d=>d&&countryNames[d.country]&&Array.isArray(d.resorts)&&d.resorts.every(r=>(data.catalog.regions[d.country]||[]).some(row=>row.name===r))&&(!d.hotelId||destinationHotel(d.hotelId)?.country===d.country)).slice(0,3);}
function rememberDestination(){const d=appliedDestination(),key=x=>JSON.stringify([x.country,[...x.resorts].sort(),x.hotelId]);saveStored('anytour.prototype.v18.destinations.v1',[d,...recentDestinations().filter(x=>key(x)!==key(d))].slice(0,3));}
function openDestination(){cancelDestinationLookup();destinationResortsExpanded=false;destinationHotelLimit=destinationHotelPageSize;destinationResolvedQuery='';destinationChoice=structuredClone(currentDraftDestination());showModal('destination','Куда отправимся?','СТРАНА, КУРОРТ ИЛИ ОТЕЛЬ',`<div class="destination-search-sticky"><div class="destination-search"><label class="sr-only" for="destination-query">Страна, курорт или отель</label>${icon('search')}<input class="input" type="search" id="destination-query" placeholder="Страна, курорт или отель" autocomplete="off" aria-controls="destination-results"><button class="icon-button destination-clear" data-action="clear-destination-query" aria-label="Очистить поиск направления" hidden>${icon('x')}</button></div></div><div id="destination-selection" class="destination-selection"></div><div id="destination-results" aria-live="polite"></div>`);$('#modal').classList.add('destination-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="destination-apply-context" role="status"></div><button class="primary picker-apply" data-action="apply-destination"></button>';renderDestination();loadResorts(destinationChoice.country);if(innerWidth>760)$('#destination-query').focus({preventScroll:true});}
function renderDestination(){
 const d=destinationChoice,q=normalizeSearch($('#destination-query').value),recent=recentDestinations(),focused=document.activeElement?.closest('#destination-results button, #destination-selection button'),focus=focused?{...focused.dataset}:null;
 $('[data-action="clear-destination-query"]').hidden=!$('#destination-query').value;
 $('#destination-selection').hidden=!!countryNames[d.country]&&!d.resorts.length&&!d.hotelId;
 if(!countryNames[d.country]){
  $('#destination-selection').textContent=catalogError?'Направления пока недоступны':'Загружаем направления…';
  $('#destination-results').innerHTML=catalogError?`<div class="destination-empty" role="status"><p>${esc(catalogError)}</p><button class="secondary" data-action="retry-catalog">Повторить загрузку направлений</button></div>`:'<p role="status">Название можно ввести сейчас. Поиск начнётся после загрузки направлений.</p>';
  const apply=$('[data-action="apply-destination"]');apply.disabled=true;apply.textContent=catalogError?'Выбор пока недоступен':'Загружаем направления…';return;
 }
 const selectedHotel=destinationHotel(d.hotelId);
 $('#destination-selection').innerHTML=d.hotelId?`<div class="destination-selection-heading"><span>Выбранный отель</span></div><div class="destination-chosen-hotel"><div><strong>${esc(selectedHotel?.name||'Выбранный отель недоступен')}</strong><small>${esc(selectedHotel?[selectedHotel.resort,countryNames[d.country]].filter(Boolean).join(', '):countryNames[d.country])}</small></div><button class="icon-button" data-action="destination-remove" data-id="${d.hotelId}" aria-label="Убрать выбранный отель">${icon('x')}</button></div><button class="text-button" data-action="destination-all">Искать по всей стране</button>`:d.resorts.length?`<div class="destination-selection-heading"><span>${esc(countryNames[d.country])} · выбранные курорты</span><button class="text-button" data-action="destination-all">Сбросить</button></div><div class="destination-selected-resorts">${d.resorts.map(r=>`<button type="button" data-action="destination-remove" data-value="${esc(r)}" aria-label="Убрать курорт: ${esc(r)}"><span>${esc(r)}</span>${icon('x')}</button>`).join('')}</div>`:'';
 const countryRows=Object.entries(countryNames).filter(([k,n])=>!q||normalizeSearch(n).includes(q));
 let html=!q&&recent.length?`<section class="destination-section"><h3>Недавние направления</h3><div class="destination-recents">${recent.map((x,i)=>`<button class="chip" data-action="destination-recent" data-value="${i}">${esc(destinationLabel(x))}</button>`).join('')}</div></section>`:'';
 if(countryRows.length)html+=`<section class="destination-section"><h3>${q?'Страны':'Направления'}</h3><div class="destination-countries">${countryRows.map(([k,n])=>`<button data-action="destination-country" data-value="${k}" aria-pressed="${d.country===k}">${esc(n)}${d.country===k?icon('check'):''}</button>`).join('')}</div></section>`;
 const resorts=Object.values(data.catalog.regions).flat().filter(r=>q?normalizeSearch(r.name+' '+countryNames[r.country]).includes(q):r.country===d.country).sort((a,b)=>a.name.localeCompare(b.name,'ru'));
 const visibleResorts=q||destinationResortsExpanded?resorts:resorts.filter((r,i)=>i<destinationResortPreviewLimit||(d.country===r.country&&d.resorts.includes(r.name)));
 const resortToggle=!q&&resorts.length>destinationResortPreviewLimit?`<button class="secondary destination-list-toggle" data-action="toggle-destination-resorts" aria-expanded="${destinationResortsExpanded}">${destinationResortsExpanded?'Свернуть список':`Показать все курорты (${resorts.length})`}</button>`:'';
 if(resorts.length&&(!d.hotelId||q))html+=`<section class="destination-section"><h3>Курорты · можно выбрать несколько</h3>${visibleResorts.map(r=>{const selected=d.country===r.country&&d.resorts.includes(r.name);return `<button class="destination-row" data-action="destination-resort" data-country="${r.country}" data-value="${esc(r.name)}" aria-pressed="${selected}"><span class="choice-check">${selected?icon('check'):''}</span><span><strong>${esc(r.name)}</strong>${q?`<small>${esc(countryNames[r.country]||'')}</small>`:''}</span></button>`}).join('')}${resortToggle}</section>`;
 if(!data.catalog.regions[d.country])html+=resortLoads.get(d.country)==='error'?'<p role="status">Не удалось загрузить курорты.</p><button class="secondary" data-action="retry-resorts">Повторить загрузку курортов</button>':'<p role="status">Загружаем курорты…</p>';
 const loaded=q.length>=2?hotels.filter(h=>h.country===d.country&&matchesHotelQuery(h,q)):[];
 const words=normalizeHotelQuery(q).split(' ').filter(Boolean),nameMatches=h=>words.every(word=>normalizeHotelQuery(h.name).includes(word));
 const matches=q.length>=2?[...new Map([...loaded,...destinationLookup.rows].map(h=>[h.id,h])).values()].sort((a,b)=>Number(nameMatches(b))-Number(nameMatches(a))):[];
 if(matches.length)html+=`<section class="destination-section"><h3>Отели · ${esc(countryNames[d.country]||'')} <span class="destination-match-count">${matches.length} найдено</span></h3>${matches.slice(0,destinationHotelLimit).map(h=>`<button class="destination-row destination-hotel" data-action="destination-hotel" data-id="${h.id}" aria-pressed="${d.hotelId===h.id}"><img src="${esc(photoUrl(h))}" alt="" width="58" height="45"><span><strong>${esc(h.name)}</strong><small>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}${h.stars?' · '+h.stars+' ★':''}</small></span>${d.hotelId===h.id?icon('check'):''}</button>`).join('')}${matches.length>destinationHotelLimit?`<button class="secondary destination-list-toggle" data-action="destination-more-hotels">Показать ещё ${Math.min(destinationHotelPageSize,matches.length-destinationHotelLimit)}<small>Показано ${destinationHotelLimit} из ${matches.length} найденных отелей</small></button>`:''}</section>`;
 if(q&&destinationLookup.status==='loading')html+='<p role="status">Ищем отели в каталоге…</p>';
 if(q&&destinationLookup.status==='error')html+='<div class="destination-empty" role="status"><h3>Не удалось загрузить отели</h3><p>Название сохранено. Попробуйте ещё раз.</p><button class="secondary" data-action="retry-destination">Повторить поиск отеля</button></div>';
 $('#destination-query').setAttribute('aria-busy',String(destinationLookup.status==='loading'));
 $('#destination-results').innerHTML=html||`<div class="destination-empty"><h3>${q.length===1?'Введите хотя бы 2 символа':'По названию ничего не нашли'}</h3><p>${q.length===1?'Продолжите название отеля.':'Проверьте написание или выберите другую страну.'}</p></div>`;
 const unresolved=!!q&&destinationResolvedQuery!==q,apply=$('[data-action="apply-destination"]');apply.disabled=unresolved;apply.textContent=d.hotelId?'Выбрать отель':'Выбрать направление';
 $('.destination-apply-context').textContent=unresolved?'Выберите подсказку или очистите поиск':d.hotelId?'Поиск по выбранному отелю':d.resorts.length?destinationLabel(d):countryNames[d.country]+' · все курорты';
 if(focus){const target=$$('#destination-results button, #destination-selection button').find(b=>['action','value','id','country'].every(key=>b.dataset[key]===focus[key]));(target||$('#destination-selection button')||$('#destination-query')).focus({preventScroll:true});}
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
const countMatchingHotels=model=>hotels.filter(h=>hotelOffers(h,{...model,firstOnly:true}).length).length;
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
 const add=(key,title,change,description='Остальные условия сохранятся')=>{const next=structuredClone(model);change(next);const count=countMatchingHotels(next);if(count)candidates.push({key,title,description,count,model:next});};
 if(f.max!==null){const wider={...model,filters:{...f,max:null}},offers=hotels.map(h=>hotelOffers(h,wider)[0]).filter(Boolean),minimum=offers.length?Math.min(...offers.map(o=>o.total)):null;if(minimum!==null&&minimum>f.max){const ceiling=Math.ceil(minimum/1000)*1000;add('budget',`Бюджет до ${money(ceiling)}`,m=>m.filters.max=ceiling);}}
 if(f.min>0)add('minimum',`Убрать бюджет «от ${money(f.min)}»`,m=>m.filters.min=0);
 if(f.q)add('q','Убрать поиск по названию',m=>m.filters.q='');
 for(const [key,title] of [['meals','Любое питание'],['stars','Любая категория отеля'],['flight','Любой тип перелёта'],['operators','Любой туроператор'],['beach','Без условия «Первая линия»'],['rating','Без ограничения по рейтингу'],['family','Без условия «Детский клуб»'],['spa','Без условия «Спа-центр»'],['resorts','Все курорты направления'],['hotelId','Другие отели в направлении']])if(Array.isArray(f[key])?f[key].length:f[key])add(key,title,m=>m.filters[key]=Array.isArray(f[key])?[]:key==='hotelId'?0:false);
 for(const key of f.amenities||[])add('amenity:'+key,`Без условия «${amenityNames.get(key)?.label||'Удобство отеля'}»`,m=>m.filters.amenities=m.filters.amenities.filter(x=>x!==key));
 if(model.onlyFavorites)add('favorites','Показать и несохранённые отели',m=>m.onlyFavorites=false);
 if(model.selectedDate)add('date',`Все даты: ${rangeText(state.search.from,state.search.to)}`,m=>m.selectedDate=null,'В пределах выбранного диапазона вылета');
 if(!candidates.length)add('reset','Сбросить все фильтры',m=>{m.filters=defaultFilters();m.onlyFavorites=false;m.selectedDate=null},'Город, страна, диапазон дат и туристы сохранятся');
 return candidates.slice(0,3);
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
 const point=resultCalendar.observations.filter(p=>p.date>=from&&p.date<=to&&Number.isFinite(p.price)&&p.price>0).sort((a,b)=>a.price-b.price)[0];
 if(!point)return '';
 const observed=/^\d{4}-\d{2}-\d{2}/.test(point.observedAt||'')?' · найдена '+dateText(point.observedAt.slice(0,10)):'';
 return `<strong>В календаре — от ${money(point.price)}${esc(observed)}</strong><p>Это ранее сохранённая цена. Предложений по ней в текущем поиске нет.</p>`;
}
function refreshEmptyCalendarContext(){const node=$('[data-empty-calendar]');if(!node)return;const html=emptyCalendarContext();node.hidden=!html;if(node.innerHTML!==html)node.innerHTML=html;}
function emptyResultsHTML(){if(!state.hasSearched){const d=currentDraftDestination();if(d.hotelId&&!data.preview&&!destinationHotel(d.hotelId)?.legacyIds.length)return `<div class="empty pristine-empty" role="status">${icon('info')}<h3>${hotelRestorePending?'Восстанавливаем выбранный отель…':'Не удалось восстановить отель'}</h3><p>Параметры поездки сохранены.${hotelRestorePending?'':' Повторите загрузку или выберите отель заново.'}</p>${hotelRestorePending?'':`<div class="empty-actions"><button class="secondary" data-action="retry-hotel-restore">Повторить загрузку отеля</button><button class="text-button" data-action="destination">Выбрать другой отель</button></div>`}</div>`;return `<div class="empty pristine-empty">${icon('search')}<h3>Начните с параметров поездки</h3><p>Выберите направление, даты и туристов, затем нажмите «Найти туры».</p></div>`;}const model=appliedFilterModel(),chips=filterChipData(model),response=responseFor(state.search);if(response.phase==='error')return '';
 if(response.pending||response.phase!=='complete'){emptySuggestions=[];return `<div class="empty recovery-empty" role="status">${icon('search')}<h3>${response.pending?'Подбираем туры по вашим условиям':'Поиск пока не завершён'}</h3><p>${response.pending?'Предложения появятся здесь по мере загрузки.':'Отсутствие результатов пока не означает, что подходящих туров нет.'}</p><p class="empty-preserved">Даты, туристы и фильтры сохранены.</p>${response.pending?'':'<button class="secondary" data-action="retry-search">Повторить поиск</button>'}</div>`;}
 emptySuggestions=recoverySuggestions(model);const calendarContext=emptyCalendarContext();return `<div class="empty recovery-empty">${icon('search')}<h3>Подходящих предложений пока нет</h3><p>${emptySuggestions.length?'Вот какие изменения вернут отели в выдачу:':'Попробуйте другие даты или направление.'}</p><div class="empty-calendar-context" data-empty-calendar ${calendarContext?'':'hidden'}>${calendarContext}</div>${recoveryHTML(emptySuggestions,'results')}<p class="empty-preserved">${emptySuggestions.length?'Количество рассчитано по уже загруженным предложениям. При изменении фильтров даты поездки и туристы сохранятся.':'По текущему запросу нет доступных предложений среди загруженных данных.'}</p><div class="empty-actions"><button class="primary" data-action="calendar">Выбрать другие даты</button>${chips.length?'<button class="secondary" data-action="reset">Сбросить фильтры</button>':''}<button class="text-button" data-action="edit-search">Изменить поиск</button></div></div>`;}
function updateDrawerPreview(){
 if(!filterDraft)return;
 const count=countMatchingHotels(filterDraft),chips=filterChipData(filterDraft),pristine=!state.hasSearched&&!state.onlyFavorites;
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
function filterEdited(rebuild=false){if(filterDraft){if(rebuild)renderFilters();else updateFacetCounts();updateDrawerPreview();return}if(rebuild)syncFilters();else{renderResults({keepFilters:true});updateSearchUI()}}
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
   const body=document.createElement('div');body.className='filter-section-values';body.id='filter-values-'+i;
   [...group.children].filter(child=>child!==heading).forEach(child=>body.append(child));
   button=document.createElement('button');button.type='button';button.className='filter-section-toggle';button.dataset.action='toggle-filter-section';button.setAttribute('aria-controls',body.id);
   button.innerHTML=`<span class="filter-section-title">${esc(key)}</span><span class="filter-section-value"></span><span class="filter-section-chevron" aria-hidden="true">⌄</span>`;
   group.append(button,body);
  }
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
function checkRows(group,options){const model=editingFilterModel(),selected=model.filters[group]||[],ordered=options.map(([val,label],index)=>[val,label,countMatchingHotels({...model,filters:{...model.filters,[group]:[val]}}),index]).sort((a,b)=>Number(selected.includes(b[0]))-Number(selected.includes(a[0]))||Number(b[2]>0)-Number(a[2]>0)||a[3]-b[3]);if(options.length<=7)return fullCheckRows(group,ordered);const label={resorts:'Найти курорт',operators:'Найти туроператора',meals:'Найти питание'}[group]||'Найти вариант';return `<div class="facet-options" data-facet-options="${group}"><div class="facet-search"><label><span class="sr-only">${label} в списке фильтра</span><input type="search" data-facet-search="${group}" value="${esc(facetQueries.get(group)||'')}" placeholder="${label}" autocomplete="off"></label><button type="button" class="icon-button" data-action="clear-facet-query" aria-label="Очистить поиск в списке" hidden>${icon('x')}</button></div><div class="facet-picked" role="group" aria-label="Выбрано в этом разделе"></div><p class="facet-search-status" aria-live="polite" hidden></p>${fullCheckRows(group,ordered.slice(0,7))}<details class="facet-more" data-facet="${group}" ${expandedFacets.has(group)?'open':''}><summary></summary>${fullCheckRows(group,ordered.slice(7))}</details></div>`;}
function applyFacetSearch(host){
 const group=host.dataset.facetOptions,q=normalizeSearch(facetQueries.get(group)||''),rows=[...host.querySelectorAll('.check-row')],more=host.querySelector('details'),focused=document.activeElement;
 rows.forEach((row,i)=>{if(row.dataset.facetOrder===undefined)row.dataset.facetOrder=String(i)});
 rows.sort((a,b)=>Number(b.querySelector('input').checked)-Number(a.querySelector('input').checked)||Number(b.dataset.available==='true')-Number(a.dataset.available==='true')||Number(a.dataset.facetOrder)-Number(b.dataset.facetOrder));
 let found=0,availableCount=0;
 for(const row of rows){const input=row.querySelector('input'),available=row.dataset.available==='true'||input.checked,matches=available&&(!q||normalizeSearch(row.querySelector('span').textContent).includes(q));row.hidden=!matches;if(matches)found++;
  if(available&&availableCount++<7)host.insertBefore(row,more);else more.append(row);
 }
 if(focused?.isConnected&&rows.some(row=>row.contains(focused))&&document.activeElement!==focused)focused.focus({preventScroll:true});
 const searchBox=host.querySelector('.facet-search');searchBox.hidden=availableCount<=7&&!q&&!searchBox.contains(focused);
 host.classList.toggle('facet-searching',!!q);
 const moreRows=[...more.querySelectorAll('.check-row')],availableMore=moreRows.filter(row=>row.dataset.available==='true'||row.querySelector('input').checked).length;
 more.querySelector('summary').textContent=`Показать ещё ${availableMore}`;more.open=!!q||expandedFacets.has(group);more.hidden=availableMore===0||!!q&&!moreRows.some(row=>!row.hidden);
 const status=host.querySelector('.facet-search-status');status.hidden=!q;status.textContent=found?`Найдено в списке: ${found}`:'Нет доступных совпадений. Попробуйте другое название.';
 const selected=editingFilterModel().filters[group]||[],picked=host.querySelector('.facet-picked');

 const labels=new Map(rows.map(row=>[row.querySelector('input').value,row.querySelector('span').textContent]));
 picked.hidden=!selected.length;
 picked.innerHTML=selected.length?`<span class="facet-picked-label">Выбрано: ${selected.length}</span><div class="facet-picked-items">${selected.map(value=>`<button type="button" class="facet-picked-item" data-action="remove-facet-choice" data-value="${esc(value)}" aria-label="Убрать из выбора: ${esc(labels.get(value)||value)}"><span>${esc(labels.get(value)||value)}</span>${icon('x')}</button>`).join('')}</div>`:'';
 host.querySelector('[data-action="clear-facet-query"]').hidden=!facetQueries.get(group);}
document.addEventListener('input',event=>{const group=event.target.dataset?.facetSearch;if(group){facetQueries.set(group,event.target.value);applyFacetSearch(event.target.closest('.facet-options'));}});
document.addEventListener('toggle',event=>{const group=event.target.dataset?.facet;if(group&&!facetQueries.get(group)){if(event.target.open)expandedFacets.add(group);else expandedFacets.delete(group);}},true);
function fullCheckRows(group,options){const model=editingFilterModel();return options.map(([val,label,knownCount])=>{const count=knownCount??countMatchingHotels({...model,filters:{...model.filters,[group]:[val]}}),selected=model.filters[group].includes(val),available=count>0||selected;return `<label class="check-row" data-available="${available}" ${available?'':'hidden'}><input type="checkbox" data-filter="${group}" value="${esc(val)}" ${selected?'checked':''}><span>${esc(label)}</span><small aria-label="${hotelCountText(count)}">${count}</small></label>`}).join('');}
function amenityFilterGroups(hs,f){
 const facts=new Map();hs.forEach(h=>(h.amenities||[]).forEach(a=>{facts.set(a.key,a);amenityNames.set(a.key,a);}));
 for(const key of f.amenities||[])if(!facts.has(key)&&amenityNames.has(key))facts.set(key,amenityNames.get(key));
 const groups=new Map();for(const fact of facts.values()){if(!groups.has(fact.groupId))groups.set(fact.groupId,{name:fact.group,items:[]});groups.get(fact.groupId).items.push(fact);}
 return [...groups.values()].map(group=>`<div class="filter-group"><h4>${esc(group.name)}</h4>${group.items.map(a=>{const selected=(f.amenities||[]).includes(a.key),keys=[...new Set([...(f.amenities||[]),a.key])],count=countMatchingHotels({...editingFilterModel(),filters:{...f,amenities:keys}}),available=count>0||selected;return `<label class="check-row" data-available="${available}" ${available?'':'hidden'}><input type="checkbox" data-filter="amenities" value="${esc(a.key)}" ${selected?'checked':''}><span>${esc(a.label)}</span><small aria-label="${hotelCountText(count)}">${count}</small></label>`;}).join('')}</div>`).join('');
}
const plainHotelText=value=>data.text(value).replace(/<[^>]*>/g,' ').replace(/&nbsp;/gi,' ').replace(/\s+/g,' ').trim();
function hotelTextExcerpt(text,limit){
 if(text.length<=limit)return {start:text,rest:''};
 const space=text.lastIndexOf(' ',limit),cut=space>limit/2?space:limit;
 return {start:text.slice(0,cut),rest:text.slice(cut)};
}
function hotelDescriptionHTML(text){
 if(!text)return '';
 if(text.length<=360)return `<p class="hotel-detail-copy hotel-description-copy">${esc(text)}</p>`;
 const {start,rest}=hotelTextExcerpt(text,280);
 return `<div class="hotel-description-preview"><p class="hotel-detail-copy">${esc(start)}<span class="description-ellipsis" aria-hidden="true">…</span></p><details class="hotel-description"><summary><span class="description-closed">Читать полностью</span><span class="description-open">Свернуть описание</span></summary><p class="hotel-detail-copy">${esc(rest)}</p></details></div>`;
}
function hotelFactHTML(label,text,body){
 if(!text)return '';
 if(text.length<=140&&!body)return `<div class="hotel-fact-section hotel-fact-short"><h4>${esc(label)}</h4><p class="hotel-detail-copy">${esc(text)}</p></div>`;
 const {start,rest}=hotelTextExcerpt(text,110);
 return `<details class="hotel-fact-section"><summary><span class="hotel-fact-label">${esc(label)}</span><span class="hotel-fact-preview">${esc(start)}${rest?'…':''}</span></summary>${body||`<p class="hotel-detail-copy">${esc(text)}</p>`}</details>`;
}
function hotelHighlights(h){
 const priority=[3,1,5,8,2],facts=[...(h.amenities||[])].sort((a,b)=>priority.indexOf(a.groupId)-priority.indexOf(b.groupId));
 if(facts.length)return [...new Set(facts.map(a=>a.label))].slice(0,4).join(' · ');
 const place=plainHotelText(h.raw?.description||h.raw?.place);return place.length>160?place.slice(0,157).replace(/\s+\S*$/,'')+'…':place;
}
function hotelSectionText(value){
 if(Array.isArray(value))return [...new Set(value.map(hotelSectionText).filter(Boolean))].join(' · ');
 if(value&&typeof value==='object'){
  const parts=['description','name','text','list','value']
   .filter(key=>Object.prototype.hasOwnProperty.call(value,key))
   .map(key=>hotelSectionText(value[key])).filter(Boolean);
  return [...new Set(parts)].join(' · ');
 }
 return plainHotelText(value);
}
function hotelDetailFacts(h){
 const info=h.raw?.hotelInformation||{},services=info.services||h.raw?.services||{},infrastructure=info.infrastructure||h.raw?.infrastructure||{};
 const sections=[
  ['Адрес',h.raw?.address],['Расположение',h.raw?.place],
  ['Пляж',infrastructure.beach],['Территория',infrastructure.territory],
  ['Услуги в отеле',services.available],['В номере',services.inRoom],
  ['Для детей',services.child],['Развлечения',services.animation],
  ['Бесплатные услуги',services.free],['Платные услуги',services.servicesPay],
  ['Питание в отеле',info.meals],['Номера отеля',info.roomTypes],
  ['Год постройки',h.raw?.build],['Ремонт',h.raw?.repair],['Площадь территории',h.raw?.square]
 ];
 return sections.map(([label,value])=>hotelFactHTML(label,hotelSectionText(value))).join('');
}
function syncAvailableFilterGroups(){$$('#filters .filter-group').forEach(group=>{const rows=[...group.querySelectorAll('.check-row')];if(rows.length)group.hidden=rows.every(row=>row.dataset.available!=='true'&&!row.querySelector('input')?.checked);});syncFilterSections();}
function updateFacetCounts(){const model=editingFilterModel();$$('[data-filter],[data-filter-bool]').forEach(input=>{const key=input.dataset.filter||input.dataset.filterBool,value=key==='amenities'?[...new Set([...(model.filters.amenities||[]),input.value])]:input.dataset.filter?[input.value]:true,count=countMatchingHotels({...model,filters:{...model.filters,[key]:value}}),row=input.closest('.check-row'),label=row?.querySelector('small'),available=count>0||input.checked;if(label){label.textContent=count;label.setAttribute('aria-label',hotelCountText(count))}if(row){row.dataset.available=String(available);if(!row.closest('.facet-options'))row.hidden=!available;}});$$('[data-facet-options]').forEach(applyFacetSearch);updateFilterStars();syncAvailableFilterGroups();renderFilterNavigation();}
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
 showFilterBudgetValidity(budget);syncFilterSections();syncFilterResetState();return budget;
}
let renderedFilterContext=null;
function paintFilters(markup,filters){
 const host=$('#filters'),active=document.activeElement,scope=searchKey(state.search);
 const group=active&&host.contains(active)&&['min-price','max-price','price-range'].includes(active.id)?active.closest('.filter-group'):null;
 if(group&&renderedFilterContext?.filters===filters&&renderedFilterContext.scope===scope){
  const template=document.createElement('template');template.innerHTML=markup;
  const replacement=template.content.querySelector('#min-price')?.closest('.filter-group');
  if(replacement&&replacement.parentNode===template.content){
   // Preserve the attached editor, native focus and an unfinished number.
   const nodes=[...template.content.childNodes],index=nodes.indexOf(replacement);
   [...host.childNodes].forEach(node=>{if(node!==group)node.remove();});
   group.before(...nodes.slice(0,index));group.after(...nodes.slice(index+1));
  }else host.innerHTML=markup;
 }else host.innerHTML=markup;
 renderedFilterContext={filters,scope};$$('[data-facet-options]').forEach(applyFacetSearch);syncAvailableFilterGroups();
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
function filterStarButtons(model){const f=model.filters,hs=hotels.filter(h=>h.country===state.search.country),starOptions=[...new Set([...hs.map(h=>h.stars).filter(n=>Number.isInteger(n)&&n>=1&&n<=5),...f.stars])].sort((a,b)=>a-b),starButtons=starOptions.map(n=>{const count=countMatchingHotels({...model,filters:{...f,stars:[n]}}),selected=f.stars.includes(n);return count>0||selected?`<button type="button" data-action="star" data-value="${n}" aria-label="${n} ${n===1?'звезда':n<5?'звезды':'звёзд'} — ${hotelCountText(count)}" aria-pressed="${selected}" class="${selected?'active':''}"><span>${n} ★</span><small aria-hidden="true">${count}</small></button>`:''}).join('');return starButtons;}
function updateFilterStars(){const host=$('#filters .star-options');if(!host)return;const focused=document.activeElement,active=host.contains(focused)?focused.dataset.value:null,html=filterStarButtons(editingFilterModel());if(host.innerHTML!==html){host.innerHTML=html;if(active)host.querySelector(`[data-value="${active}"]`)?.focus({preventScroll:true});}host.closest('.filter-group').hidden=!html;}
function renderFilters(){const queryScope=JSON.stringify([searchKey(state.search),data.scenario]);if(queryScope!==facetQueryScope){facetQueries.clear();facetQueryScope=queryScope;}const model=editingFilterModel(),f=model.filters,hs=hotels.filter(h=>h.country===state.search.country),scale=budgetScale(f),budgetEdit=currentFilterBudgetEdit(f),starButtons=filterStarButtons(model);paintFilters(`
 <div class="filter-group"><h4>Название отеля или курорт</h4><div class="filter-search"><input class="input" id="hotel-query" type="search" value="${esc(f.q)}" placeholder="Название или несколько слов" aria-label="Название отеля или курорт">${icon('search')}<button type="button" class="icon-button clear-hotel-query" data-action="clear-hotel-query" aria-label="Очистить название отеля или курорт" ${f.q?'':'hidden'}>${icon('x')}</button></div></div>
 <div class="filter-group"><h4>Бюджет на всех туристов</h4><div class="price-inputs"><label>От, ₽<input type="text" inputmode="decimal" id="min-price" value="${esc(budgetEdit?.minText??f.min)}" aria-describedby="filter-budget-error"></label><label>До, ₽<input type="text" inputmode="decimal" id="max-price" value="${esc(budgetEdit?.maxText??f.max??'')}" placeholder="Без лимита" aria-describedby="filter-budget-error"></label></div><input class="range" type="range" id="price-range" aria-label="Максимальная цена" aria-valuetext="${esc(budgetLabel(f))}" min="${f.min}" max="${scale}" step="1000" value="${f.max??scale}"><p class="error-text filter-budget-error" id="filter-budget-error" role="alert" hidden></p></div>
 <div class="filter-group" ${starButtons?'':'hidden'}><h4>Категория отеля</h4><div class="star-options">${starButtons}</div></div>
 ${Object.keys(mealNames).length?`<div class="filter-group"><h4>Питание</h4>${checkRows('meals',[...new Set([...Object.keys(mealNames),...f.meals])].map(m=>[m,m]))}</div>`:''}
 ${f.rating||hs.some(h=>ratingValue(h)!==null)?(()=>{const count=countMatchingHotels({...model,filters:{...f,rating:true}}),available=count>0||f.rating;return `<div class="filter-group"><h4>Оценка гостей</h4><label class="check-row" data-available="${available}" ${available?'':'hidden'}><input type="checkbox" data-filter-bool="rating" ${f.rating?'checked':''}><span>От 4,5 из 5</span><small aria-label="${hotelCountText(count)}">${count}</small></label></div>`})():''}
 ${f.resorts.length||hs.some(h=>hotelPlaces(h).length)?`<div class="filter-group"><h4>Курорт</h4>${checkRows('resorts',[...new Set([...f.resorts,...hs.flatMap(h=>hotelPlaces(h))])].map(r=>[r,r]))}</div>`:''}
 ${f.operators.length||operators.length?`<div class="filter-group"><h4>Туроператор</h4>${checkRows('operators',[...new Set([...f.operators,...operators])].map(o=>[o,o]))}</div>`:''}
 ${amenityFilterGroups(hs,f)}
 <div class="filter-hint">${icon('info')}<span>${!state.hasSearched&&!state.onlyFavorites?'Условия применятся после нажатия «Найти туры». Доступные курорты и туроператоры появятся в выдаче.':'Фильтры применяются к найденным предложениям. Актуальная цена и сборы уточняются при выборе.'}</span></div>`,f);
 renderFilterNavigation();syncFilterResetState(model);showFilterBudgetValidity(readBudgetFields($('#min-price'),$('#max-price')));$('#beach-chip').hidden=true;$('#family-chip').hidden=true;
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
function renderCalendarStrip(){
 loadResultCalendar();
 const s=state.search,days=[];for(let day=s.from;day<=s.to;day=addDays(day,1))days.push(day);
 const calendarRows=[...hotels,...resultCalendar.hotels],prices=days.map(day=>calendarMinimum(day,{calendarHotels:calendarRows},resultCalendar.observations)),known=prices.filter(p=>p!==null),min=Math.min(...known),max=Math.max(...known);
 const source=resultCalendar.phase==='loading'?'Открываем сохранённые цены…':resultCalendar.phase==='error'?'База цен временно недоступна · показаны найденные предложения':resultCalendar.phase==='partial'?'Часть базы цен временно недоступна · показаны доступные цены и найденные предложения':calendarSourceLabel();
 const scope=calendarScope({search:s,filters:state.filters});
 $('#calendar-caption').textContent=`${scope.destination} · ${guestsText(s)} · ${durationText(s)}${scope.filters.length?' · с выбранными фильтрами':''} · ${source}`;
 $('#price-strip').setAttribute('aria-busy',String(resultCalendar.phase==='loading'));
 $('#price-strip').innerHTML=days.map((day,i)=>`<button class="date-price ${prices[i]!==null&&prices[i]===min?'best':''} ${state.selectedDate===day?'selected':''}" data-action="select-date" data-date="${day}" aria-pressed="${state.selectedDate===day}" aria-label="Вылет ${dateLong(day)}${prices[i]!==null?', от '+money(prices[i]):', цена пока неизвестна'}${state.selectedDate===day?', выбрано; нажмите ещё раз, чтобы вернуть все даты':''}"><span class="date">${dateText(day)}</span><strong>${prices[i]===null?'—':money(prices[i])}</strong><span class="calendar-bar" style="--bar-height:${prices[i]===null?5:12+Math.round((prices[i]-min)/Math.max(1,max-min)*22)}px"></span></button>`).join('');
 $('#clear-date').hidden=!state.selectedDate;
 refreshEmptyCalendarContext();
}
function renderActive(){const f=state.filters,chips=filterChipData();
 $('#sort').value=state.sort;$('#mobile-sort').value=state.sort;
 const sortLabel=state.sort==='price'?'Дешевле':state.sort==='rating'?'Рейтинг':'Сортировка';
 $('#mobile-sort-label').textContent=sortLabel;$('.mobile-sort').classList.toggle('active',state.sort!=='recommended');
 $('#active-filters').innerHTML=chips.map(c=>`<button class="active-filter" data-action="remove-filter" data-key="${c.key}" data-value="${esc(c.value)}" aria-label="Убрать: ${esc(c.label)}">${esc(c.label)}${icon('x')}</button>`).join('');
 for(const key of ['beach','rating','family']){$('#'+key+'-chip').classList.toggle('active',f[key]);$('#'+key+'-chip').setAttribute('aria-pressed',f[key])}
 const ratingBase=structuredClone(appliedFilterModel());ratingBase.filters.rating=false;
 const ratingTotal=countMatchingHotels(ratingBase),ratingModel=structuredClone(ratingBase);ratingModel.filters.rating=true;
 const ratingCount=countMatchingHotels(ratingModel),ratingChip=$('#rating-chip');
 ratingChip.hidden=!f.rating&&(ratingCount===0||ratingCount===ratingTotal);
 $('#rating-chip-count').textContent=ratingChip.hidden?'':`· ${ratingCount}`;
 ratingChip.setAttribute('aria-label',`${f.rating?'Убрать фильтр':'Показать'}: рейтинг от 4,5 — ${hotelCountText(ratingCount)}`);
 const n=filterCount();$('#filter-count').textContent=n?`(${n})`:'';$('#mobile-count').textContent=n?`(${n})`:'';$('#drawer-filter-count').textContent=n?`(${n})`:'';
}
function offerHTML(h,o){return `<div class="offer" data-offer-key="${o.key}"><div><strong>${dateText(o.day)} → ${dateText(o.returnDay)}</strong><small>${nightsText(o.nights)} · ${guestsText(o)}</small><small>Город вылета: ${esc(o.origin)}</small></div><div><strong>${esc(mealLabel(o))}</strong><small>${esc(o.room)}</small></div><div><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}<small>${offerMetaNote(o)}</small></div><div class="offer-price"><strong>${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${o.key}">${offerActionLabel(o)} ${icon('arrow')}</button></div></div>`;}
function cardHTML({hotel:h,offers}){const o=offers[0],opened=state.openHotel===h.id,photoIndex=state.photoIndexes[h.id]||0,popularityBadge=popularity?.badge(h)||'',shortlistActions=optionalShortlistEnabled?`<button class="favorite-button ${state.favorites.includes(h.id)?'active':''}" data-action="favorite" data-id="${h.id}" aria-pressed="${state.favorites.includes(h.id)}" aria-label="${state.favorites.includes(h.id)?'Убрать из избранного':'В избранное'}: ${esc(h.name)}">${icon('heart')}</button><button class="compare-photo-button ${state.compare.includes(h.id)?'active':''}" data-action="toggle-compare" data-id="${h.id}" aria-pressed="${state.compare.includes(h.id)}" aria-label="${state.compare.includes(h.id)?'Убрать из сравнения':'Сравнить'}: ${esc(h.name)}" title="${state.compare.includes(h.id)?'В сравнении':'Сравнить отель'}">${icon(state.compare.includes(h.id)?'check':'compare')}</button>`:'';return `<article class="hotel-card" id="hotel-${h.id}" data-hotel-id="${h.id}"><div class="hotel-main">
 <div class="hotel-photos ${h.photos.length?'':'photo-unavailable'}"><div class="hotel-image-wrap">${h.photos.length?'':`<span class="photo-missing-label">${icon('image')}Нет фотографий</span>`}<button class="hotel-image-button" data-action="gallery" data-id="${h.id}" ${h.photos.length?'':'disabled'} aria-label="${h.photos.length?'Открыть фотографии':'Фото пока недоступны:'} ${esc(h.name)}"><img class="hotel-image" src="${esc(photoUrl(h,photoIndex))}" alt="Фото отеля ${esc(h.name)}" loading="lazy" width="700" height="500"><span class="photo-count">${icon('image')} <span class="photo-index">${h.photos.length?photoIndex+1:0}</span> / ${h.photos.length}</span></button>${shortlistActions}<button class="card-photo-arrow prev" data-action="card-photo" data-id="${h.id}" data-dir="-1" aria-label="Предыдущее фото ${esc(h.name)}">${icon('back')}</button><button class="card-photo-arrow next" data-action="card-photo" data-id="${h.id}" data-dir="1" aria-label="Следующее фото ${esc(h.name)}">${icon('arrow')}</button></div><div class="card-thumbs">${h.photos.slice(0,h.photos.length>4?3:4).map((p,i)=>`<button class="card-thumb ${photoIndex===i?'active':''}" data-action="card-photo-index" data-id="${h.id}" data-value="${i}" aria-label="Показать фото ${i+1} отеля ${esc(h.name)}" aria-pressed="${photoIndex===i}"><img src="${esc(p)}" alt="" loading="lazy" width="150" height="100"></button>`).join('')}${h.photos.length>4?`<button class="card-more-photos" data-action="gallery" data-id="${h.id}" aria-label="Все ${h.photos.length} фотографий отеля ${esc(h.name)}">${icon('image')}<span>Все ${h.photos.length}</span></button>`:''}</div></div>
 <div class="hotel-info"><div class="hotel-info-top"><div>${popularityBadge?`<span class="hotel-popularity-badge" title="Входит в TOP500 продаваемых отелей">${esc(popularityBadge)}</span>`:'' }${hotelStarsHTML(h)}<h3><button data-action="hotel-details" data-id="${h.id}" title="${esc(h.name)}"><span class="hotel-card-name">${esc(h.name)}</span>${icon('arrow')}</button></h3><div class="hotel-location">${icon('pin')} ${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</div></div>${ratingValue(h)!==null?`<div class="rating-block" aria-label="Оценка гостей ${ratingText(h)} из 5"><strong>${ratingText(h)}<small>/ 5</small></strong><span>${ratingValue(h)>=4.5?'Отлично':'Оценка гостей'}</span></div>`:''}</div><div class="hotel-facts"><span>${esc(hotelHighlights(h))}</span></div></div></div>
 ${minimumOfferSummary(o)}<div class="hotel-price"><div class="starting-price"><span>За ${guestsText(o)} · весь тур</span><strong>${money(o.total)}</strong></div><span class="fuel-note">${icon('info')} ${cardPriceNote(o)}</span><button class="primary" data-action="offer" data-key="${esc(o.key)}" aria-label="Смотреть тур: ${esc(h.name)}"><span>Смотреть тур</span> ${icon('arrow')}</button></div><div class="hotel-more"><button class="text-button" data-action="hotel-details" data-id="${h.id}">Об отеле</button>${offers.length>1?`<button class="text-button" data-action="all-offers" data-id="${h.id}">Все туры (${offers.length}) ${icon('arrow')}</button>`:''}</div></article>`;}
function hotelAmenitiesHTML(h){
 const groups=new Map();
 for(const amenity of h.amenities||[]){if(!amenity.label)continue;const group=amenity.group||'Удобства';if(!groups.has(group))groups.set(group,new Set());groups.get(group).add(amenity.label);}
 return [...groups].map(([group,labels])=>hotelFactHTML(group,[...labels].join(' · '),labels.size>2?`<ul class="hotel-service-list">${[...labels].map(label=>`<li>${esc(label)}</li>`).join('')}</ul>`:null)).join('');
}
function roomOfferChoiceHTML(o){
 return `<div class="room-offer-choice" data-offer-key="${esc(o.key)}"><div class="room-offer-conditions"><strong class="room-offer-meal">${esc(mealLabel(o))}</strong><dl class="room-choice-facts"><div><dt class="sr-only">Даты и отдых</dt><dd><time datetime="${esc(o.day)}">${dateText(o.day)}</time> → <time datetime="${esc(o.returnDay)}">${dateText(o.returnDay)}</time> · ${nightsText(o.nights)}</dd></div></dl><span class="room-offer-operator">${esc(o.operator)} · ${flightLabel(o)}</span></div><div class="hotel-room-price"><small>Весь тур · ${guestsText(o)}</small><strong>${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${esc(o.key)}" aria-label="Смотреть тур: ${esc(mealLabel(o))}, ${dateText(o.day)}, ${esc(o.operator)}, ${money(o.total)}">Смотреть тур ${icon('arrow')}</button></div></div>`;
}
function renderHotelRooms(id,meal='',restoredRooms=null){
 const h=hotels.find(h=>h.id===id);if(!h||modalType!=='hotel-details')return;
 const offers=hotelOffers(h).filter(o=>!meal||o.meal===meal),rooms=[...new Set(offers.map(o=>o.room))].map(room=>({room,offers:offers.filter(o=>o.room===room)}));
 $('#hotel-room-count').textContent=`Номера: ${rooms.length} · Туры: ${offers.length}`;
 const roomCards=$('.hotel-room-cards');roomCards.classList.toggle('single-direct-offer',offers.length===1);
 roomCards.innerHTML=rooms.map(({room,offers:rows})=>{
  const choices=`<div class="room-offer-list">${rows.slice(0,2).map(roomOfferChoiceHTML).join('')}${rows.length>2?`<details class="hotel-room-more"><summary><span class="room-more-closed">Ещё ${offerCountText(rows.length-2)}</span><span class="room-more-open">Скрыть остальные туры</span></summary>${rows.slice(2).map(roomOfferChoiceHTML).join('')}</details>`:''}</div>`;
  const title=`<h4>${esc(room||'Тип номера уточняется')}</h4>`;
  if(rooms.length===1)return `<article class="hotel-room-card"><header class="room-choice-main">${title}<span class="room-choice-label">${offerCountText(rows.length)}</span></header>${choices}</article>`;
  const meals=[...new Set(rows.map(mealLabel))].map(esc).join(' · '),min=Math.min(...rows.map(o=>o.total));
  return `<details class="hotel-room-card room-overview" data-room="${esc(room)}" ${restoredRooms?.includes(room)?'open':''}><summary class="room-overview-toggle"><span class="room-overview-name">${title}<span class="room-overview-meals">${meals}</span></span><span class="room-overview-bottom"><strong class="room-overview-price">от ${money(min)}</strong><span class="room-overview-action"><span class="room-overview-closed">${offerCountText(rows.length)}</span><span class="room-overview-open">Свернуть</span><span class="rotate-arrow" aria-hidden="true">⌄</span></span></span></summary>${choices}</details>`;
 }).join('')||'<p class="tour-missing">По текущим условиям предложений нет. Измените даты или фильтры поиска.</p>';
 const control=$('#hotel-room-meal');if(control){control.value=meal;[...control.options].forEach(option=>option.defaultSelected=option.value===meal);}
 const price=$('#hotel-detail-min'),button=$('#hotel-detail-offers');
 if(price)price.textContent=offers.length?(offers.length>1?'от ':'')+money(offers[0].total):'Нет предложений';
 const status=$('#hotel-detail-price-status');if(status)status.textContent=offers.length?cardPriceNote(offers[0]):'';
 if(button){button.dataset.action=offers.length===1?'offer':'hotel-section';button.dataset.target='hotel-rooms-heading';if(offers.length===1)button.dataset.key=offers[0].key;else delete button.dataset.key;button.innerHTML=(offers.length===1?'Смотреть тур':'Выбрать тур')+' '+icon('arrow');button.disabled=!offers.length;}
 queueMicrotask(()=>{observeHotelRoomChoices();syncHotelSectionNavigation();});
 rememberUIRoute();
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
function openHotelDetails(id){
 const h=hotels.find(h=>h.id===id);if(!h)return;
 const offers=hotelOffers(h),description=plainHotelText(h.raw?.description||h.note||''),amenities=[...new Set((h.amenities||[]).map(a=>a.label).filter(Boolean))],meals=[...new Set(offers.map(o=>o.meal))];
 const facts=hotelDetailFacts(h)+hotelAmenitiesHTML(h);
 const visiblePhotos=h.photos.slice(0,3);
 const photos=h.photos.length?`<section class="hotel-photo-section" aria-label="Фотографии"><h3 id="hotel-photos-heading" class="sr-only hotel-section-anchor" tabindex="-1">Фотографии отеля</h3><div class="hotel-detail-photos photo-count-${visiblePhotos.length}">${visiblePhotos.map((p,i)=>`<button data-action="hotel-gallery" data-id="${h.id}" data-value="${i}" aria-label="Открыть фото ${i+1} отеля ${esc(h.name)}"><img src="${esc(p)}" alt="Фото отеля" loading="lazy"></button>`).join('')}</div><button class="secondary hotel-all-photos" data-action="hotel-gallery" data-id="${h.id}" data-value="0">${icon('image')} Все фотографии · ${h.photos.length}</button></section>`:'<p class="tour-missing">Фотографии отеля пока не предоставлены.</p>';
 const rating=ratingValue(h),hasOverview=Boolean(h.photos.length||description||amenities.length||facts);
 const overview=hasOverview?`${photos}<div class="hotel-detail-heading"><h3 id="hotel-about-heading" class="hotel-section-anchor" tabindex="-1">Об отеле</h3>${ratingValue(h)!==null?`<span class="detail-rating">${ratingText(h)} / 5</span>`:''}</div>
 ${amenities.length?`<ul class="hotel-key-facts">${amenities.slice(0,8).map(label=>`<li>${icon('check')}${esc(label)}</li>`).join('')}</ul>`:''}
 ${hotelDescriptionHTML(description)||(!amenities.length&&!facts?'<p class="tour-missing">Подробное описание пока не предоставлено.</p>':'')}`:`<div class="hotel-data-note"><span>Фото и описание отеля пока не предоставлены. Ниже — доступные туры с исходными условиями.</span>${rating!==null?`<strong>Оценка гостей ${ratingText(h)} / 5</strong>`:''}</div>`;
 const sections=[...(h.photos.length?[['hotel-photos-heading','Фото']]:[]),...(hasOverview?[['hotel-about-heading','Об отеле']]:[]),...(facts?[['hotel-services-heading','Услуги']]:[]),['hotel-rooms-heading','Номера и цены']];
 showModal('hotel-details',h.name,`${h.resort} · ${h.stars?h.stars+' ★':'Категория не указана'}`,`
 <nav class="hotel-section-nav" data-hotel-id="${h.id}" aria-label="Разделы отеля">${sections.map(([target,label])=>`<button type="button" data-action="hotel-section" data-target="${target}">${label}</button>`).join('')}</nav>
 ${overview}
 ${facts?`<section class="hotel-information-sections" aria-labelledby="hotel-services-heading"><h3 id="hotel-services-heading" class="detail-section-title hotel-section-anchor" tabindex="-1">Услуги и инфраструктура</h3>${facts}</section>`:''}
 <section class="hotel-room-section" aria-labelledby="hotel-rooms-heading"><h3 id="hotel-rooms-heading" class="detail-section-title hotel-section-anchor" tabindex="-1">Номера и питание</h3><p class="hotel-room-context">${departureScopeText()} · ${durationText()} · ${guestsText()}</p>
 ${meals.length>1&&offers.length>2?`<label class="hotel-room-meal-filter">Питание в туре<select id="hotel-room-meal" data-id="${h.id}"><option value="">Любое питание</option>${meals.map(meal=>`<option value="${esc(meal)}">${esc(meal)}</option>`).join('')}</select></label>`:''}
 <p id="hotel-room-count" class="hotel-room-count" role="status" aria-live="polite"></p><div class="hotel-room-cards"></div></section>`,true);
 $('#modal').classList.add('hotel-details-dialog');
 if(offers.length){$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total"><span>За ${guestsText()}</span><strong id="hotel-detail-min"></strong><small id="hotel-detail-price-status"></small></div><button id="hotel-detail-offers" class="primary" data-action="hotel-detail-offers" data-id="${id}"></button>`;}
 renderHotelRooms(id);
}
let renderedCardLimit=24,renderedCardScope='';
function renderResults(options={}){
 // A changed form is a draft, not a new result set. Keep cards, pagination and
 // the shareable URL intact until Search; child pickers may still preview counts.
 if(searchEditSession){
  if(!options.keepFilters)renderFilters();else{updateFacetCounts();syncFilterResetState();}
  if(filterDraft)updateDrawerPreview();if(modalType==='budget')updateBudgetPreview();if(modalType==='meals'){updateMealCounts();updateMealPicker();}
  return;
 }
 if(searchResponse.key&&searchResponse.key!==searchKey(state.search)){clearSearchTimers();searchResponse={key:searchKey(state.search),phase:'complete',operators:[...operators],pending:false};}
 const items=results(),total=items.reduce((s,r)=>s+r.offers.length,0),pristine=!state.hasSearched&&!state.onlyFavorites;
 $('#results').classList.toggle('results-pristine',pristine);document.body.classList.toggle('results-pristine-active',pristine);
 $('#compact-route').textContent=`${esc(state.search.origin)} → ${destinationLabel(appliedDestination())}`;
 $('#compact-details').textContent=`${state.selectedDate?dateText(state.selectedDate):rangeText(state.search.from,state.search.to)} · ${durationText()} · ${guestsText()}`;
 renderCompactRefinements();
 $('#route-label').textContent=`${esc(state.search.origin)} → ${esc(countryNames[state.search.country]||'')} · ${departureScopeText()}`;
 $('#results-title').textContent=pristine?'Ваш следующий отдых':state.onlyFavorites?'Ваши избранные отели':`Отели и туры · ${countryNames[state.search.country]||'Выберите направление'}`;
 const failed=!pristine&&!items.length&&responseFor(state.search).phase==='error';
 $('#results-summary').textContent=pristine?'Задайте направление, даты и состав туристов — предложения появятся после поиска.':failed?`Результаты не получены · ${durationText()} · ${guestsText()}`:`${hotelCountText(items.length)} · ${total} ${total%10===1&&total%100!==11?'вариант':total%10>=2&&total%10<=4&&(total%100<12||total%100>14)?'варианта':'вариантов'} тура`;
 const canSort=items.length>1;$('.sort-label').hidden=!canSort;$('.mobile-sort').hidden=!canSort;
 const cardScope=JSON.stringify([state.search,state.filters,state.selectedDate,state.sort,state.onlyFavorites,data.scenario]);if(cardScope!==renderedCardScope){renderedCardScope=cardScope;renderedCardLimit=24;}
 $('#cards').innerHTML=items.length?items.slice(0,renderedCardLimit).map(cardHTML).join('')+(items.length>renderedCardLimit?`<button type="button" class="secondary load-more-cards" data-action="more-cards">Показать ещё ${Math.min(24,items.length-renderedCardLimit)} отеля <span>Показано ${Math.min(renderedCardLimit,items.length)} из ${items.length}</span></button>`:''):emptyResultsHTML();
 $('#apply-filters').textContent=pristine?'Сохранить условия':`Показать отели (${items.length})`;
 renderCalendarStrip();renderActive();updateNav();renderSummary();updateURL();if(!options.keepFilters)renderFilters();else{updateFacetCounts();syncFilterResetState();}renderSearchStatus(items,total);if(filterDraft)updateDrawerPreview();if(modalType==='budget')updateBudgetPreview();if(modalType==='meals'){updateMealCounts();updateMealPicker();}
}
function renderCompactRefinements(){
 $('.compact-refinements').hidden=!state.hasSearched||!$('#search-form').hidden;
 const f=state.filters,meal=f.meals.join(', '),category=f.stars.join(' / '),budget=f.min>0||f.max!==null;
 const choices=[['category',category?category+' ★':'Звёзды','Категория отеля: '+(category?category+' звёзд':'любая'),f.stars.length>0],['meal',f.meals.length>1?'Питание · '+f.meals.length:meal||'Питание','Питание: '+(meal||'любое'),f.meals.length>0],['budget',budget?budgetText():'Бюджет','Бюджет на всех туристов: '+budgetText(),budget]];
 for(const [id,label,full,active] of choices){const button=$('#compact-'+id);button.firstElementChild.textContent=label;button.setAttribute('aria-label',full);button.title=full;button.classList.toggle('active',active);}
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
// it altogether. Forward/reload only reopen a passive view, never a request or
// a previously accepted price. Keep search parameters canonical on every pop.
const uiHistoryKey='anytour.prototype.v18.ui.v1';
let uiHistoryOpen=false,uiHistoryClosing=false,restoringUIHistory=false,handlingUIBack=false,actionTrigger=null,pageReturn=null,pendingPageReturn=null;
function focusReference(element,root=document){
 if(!element||element===document.body||element===document.documentElement||!root.contains(element))return null;
 let selector=element.id?'#'+CSS.escape(element.id):'';
 if(!selector&&element.dataset?.action){selector='[data-action="'+CSS.escape(element.dataset.action)+'"]';for(const key of ['id','key','value','room'])if(element.dataset[key]!==undefined)selector+='[data-'+key+'="'+CSS.escape(element.dataset[key])+'"]';const card=element.closest('.hotel-card');if(card)selector='#'+CSS.escape(card.id)+' '+selector;}
 return {element,selector};
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
function uiRoute(){
 if($('#filter-panel').classList.contains('open'))return {type:'filters'};
 const type=modalType;
 if(type==='verification')return selectedOffer===savedSelection?{type:'saved-tour'}:{type:'offer',key:selectedOffer?.key,flightChoiceId:selectedOffer?.flightChoiceId};
 if(type==='flights'){const o=flightDraft?.base;return o?.isSavedSelection?{type:'saved-details'}:{type:'offer',key:o?.key,flightChoiceId:o?.flightChoiceId};}
 if(type==='selected-tour')return {type,key:selectedOffer?.key};
 if(type==='offer')return selectedOffer===savedSelection?{type:'saved-details'}:{type,key:selectedOffer?.key,flightChoiceId:selectedOffer?.flightChoiceId};
 if(type==='all-offers')return {type,id:offerView?.id,mode:offerView?.mode,departure:offerView?.departure,day:offerView?.day,nights:offerView?.nights,flight:offerView?.flight,room:offerView?.room,meal:offerView?.meal,sort:offerView?.sort,pair:offerView?.pair,activeVariant:offerView?.activeVariant,differencesOnly:offerView?.differencesOnly};
 if(type==='hotel-details')return {type,id:Number($('.hotel-section-nav')?.dataset.hotelId)||null,meal:$('#hotel-room-meal')?.value||'',rooms:$$('.room-overview[open]').map(el=>el.dataset.room)};
 if(type==='gallery')return {type,id:gallery.id,index:gallery.index};
 if(type==='dates')return {type,source:dateContext?.source};
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
function reopenUIRoute(route){
 if(!route||typeof route!=='object')return false;
 const hotel=hotels.find(h=>h.id===route.id);
 switch(route.type){
 case 'filters':if(innerWidth>1100)return false;openFilters();break;
 case 'destination':openDestination();break;
 case 'guests':openGuests();break;
 case 'nights':openNights();break;
 case 'dates':openDates(route.source==='results'?'results':'form');break;
 case 'meals':openMeals();break;
 case 'budget':openBudget();break;
 case 'saved-tour':case 'saved-details':return false;
 case 'selected-tour':{if(!route.key||selectedOffer?.key!==route.key)return false;openLeadPreview();break;}
 case 'favorites':openFavorites();break;
 case 'compare':openCompare();break;
 case 'hotel-details':if(!hotel)return false;openHotelDetails(hotel.id);renderHotelRooms(hotel.id,hotelOffers(hotel).some(o=>o.meal===route.meal)?route.meal:'',Array.isArray(route.rooms)?route.rooms:null);break;
 case 'all-offers':if(!hotel)return false;openAllOffers(hotel.id,route);break;
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
function showModal(type,title,kicker,body,wide=false){
 cancelVerification();hotelRoomObserver?.disconnect();hotelRoomObserver=null;const m=$('#modal'),newStep=!m.open||modalType!==type;
 if(!m.open){modalHistory.length=0;enterUIHistory();}
 else if(!restoringModal&&modalType!==type){modalHistory.push({type:modalType,title:$('#modal-title').textContent,kicker:$('#modal-kicker').textContent,body:$('#modal-body').innerHTML,footer:$('#modal-footer').innerHTML,footerHidden:$('#modal-footer').hidden,className:m.className,scroll:$('#modal-body').scrollTop,gallery:{...gallery},offer:selectedOffer,focus:focusReference(actionTrigger||document.activeElement,m)});}
 modalType=type;updateModalBack();m.className=type==='gallery'?'gallery-dialog':wide?'wide-dialog':type==='dates'?'dates-dialog':'';$('#modal-title').textContent=title;$('#modal-kicker').textContent=kicker;$('#modal-body').innerHTML=body;$('#modal-footer').innerHTML='';$('#modal-footer').hidden=true;$('#modal-body').scrollTop=0;
 if(!m.open)m.showModal();document.body.style.overflow='hidden';m.scrollTop=0;$('#modal-body').scrollTop=0;hydrate();syncDestinationViewport();if(newStep&&!restoringModal)$('#modal-title').focus({preventScroll:true});queueMicrotask(rememberUIRoute);
}
function closeModal({fromHistory=false}={}){
 selectionGeneration++;calendarRequest?.abort();calendarObserver?.disconnect();hotelRoomObserver?.disconnect();hotelRoomObserver=null;cancelDestinationLookup();
 const m=$('#modal');if(!m.open)return;window.AnyTourPrototypeLead.reset();
 leaveUIHistory(fromHistory);cancelVerification();modalType='';modalHistory.length=0;m.close();document.body.style.overflow=$('#filter-panel').classList.contains('open')?'hidden':'';restorePageReturn();
}
function modalBack(){
 const previous=modalHistory.pop();if(!previous)return;
 restoringModal=true;showModal(previous.type,previous.title,previous.kicker,previous.body,previous.className==='wide-dialog');restoringModal=false;
 $('#modal').className=previous.className;$('#modal-footer').innerHTML=previous.footer;$('#modal-footer').hidden=previous.footerHidden;gallery=previous.gallery;selectedOffer=previous.offer;updateModalBack();
 if(previous.type==='all-offers'&&offerView)renderOfferList();if(previous.type==='compare')renderCompare();if(previous.type==='favorites')renderFavorites();if(previous.type==='selected-tour')window.AnyTourPrototypeLead.bind(selectedOffer);if(previous.type==='hotel-details')queueMicrotask(observeHotelRoomChoices);refreshSavedTourControls();
 restoreFocus(previous.focus,$('#modal-title'),$('#modal'));$('#modal-body').scrollTop=previous.scroll;syncHotelSectionNavigation();rememberUIRoute();
}
$('#modal').addEventListener('cancel',e=>{e.preventDefault();closeModal();});
$('#modal').addEventListener('click',e=>{if(e.target===$('#modal')){const r=e.target.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closeModal()}});
let calendarHotels=[],calendarObservations=[],calendarRequest=null,calendarObserver=null,calendarMobile=null;
let dateContext=null,datePrices=new Map(),mealDraft=[];
function budgetLabel(f){return f.max===null?(f.min?'От '+money(f.min):'Без ограничений'):f.min?`${money(f.min)} — ${money(f.max)}`:'До '+money(f.max);}
const budgetText=()=>budgetLabel(state.filters);
function createDateContext(source='form'){
 const s=source==='results'?state.search:draft,filters=structuredClone(state.filters);
 if(source==='form'&&draftDestination){filters.resorts=[...draftDestination.resorts];filters.hotelId=draftDestination.hotelId;filters.q='';}else if(s.country!==state.search.country){filters.resorts=[];filters.hotelId=0;filters.q='';}
 return {source,search:structuredClone(s),filters};
}
const dateContextLabel=s=>`из ${s.origin==='Москва'?'Москвы':s.origin==='Казань'?'Казани':'Санкт-Петербурга'} · ${guestsText(s)} · ${durationText(s)}`;
function calendarScope(ctx){
 const place={country:ctx.search.country,hotelId:ctx.filters.hotelId,resorts:ctx.filters.resorts};
 return {destination:destinationLabel(place),fullDestination:destinationLabel(place,true),filters:filterChipData({filters:ctx.filters}).filter(chip=>!['hotelId','resorts'].includes(chip.key))};
}
const calendarSourceLabel=()=>data.scenario==='live'?'Ранее найденная цена':data.scenario==='snapshot'?'Снимок 23.09 · без обновления':data.scenario==='recorded'?(hotels.some(h=>h.offers?.some(o=>o.recordingKind==='demo'))?'Демонстрационная запись':'Цена из загруженной записи'):'Демонстрационная цена';
function renderCalendarScope(){
 const ctx=dateContext,scope=calendarScope(ctx),node=$('.calendar-context');if(!node)return;
 node.innerHTML=`<details class="calendar-scope"><summary><span class="calendar-scope-label"><strong>${esc(scope.destination)}</strong><small>${esc(dateContextLabel(ctx.search))}</small></span><span class="calendar-scope-toggle">${scope.filters.length?'Фильтры: '+scope.filters.length:'Условия'}</span></summary><div class="calendar-scope-content"><p>${esc(scope.fullDestination)}</p>${ctx.search.ages.length?`<p>${esc(childAgesLabel(ctx.search.ages))}</p>`:''}${scope.filters.length?`<p>Цены с учётом выбранных условий:</p><ul>${scope.filters.map(chip=>`<li>${esc(chip.label)}</li>`).join('')}</ul>`:'<p>Без дополнительных фильтров</p>'}<p>${esc(calendarSourceLabel())}. Цена указана за весь тур и всех туристов; актуальность и наличие требуют проверки.</p><p>Прочерк означает, что подсказки цены нет. Он не означает отсутствие туров.</p></div></details>`;
}
function mealPreviewModel(){
 const place=currentDraftDestination(),changed=searchKey(draft)!==searchKey(state.search)||place.hotelId!==state.filters.hotelId||JSON.stringify(place.resorts)!==JSON.stringify(state.filters.resorts);
 if(!state.hasSearched||changed||responseFor(state.search).phase==='error')return null;
 return {...appliedFilterModel(),filters:{...state.filters,meals:[...mealDraft]}};
}
function updateMealCounts(){
 const model=mealPreviewModel();
 $$('.meal-option').forEach(row=>{row.querySelector('.meal-hotel-count')?.remove();if(!model)return;const value=row.querySelector('input').value,count=countMatchingHotels({...model,filters:{...model.filters,meals:value?[value]:[]}});row.insertAdjacentHTML('beforeend',`<span class="meal-hotel-count" aria-label="${hotelCountText(count)}">${count}</span>`);});
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
function openMeals(){
 mealDraft=[...state.filters.meals];
 const choices=[...new Set([...Object.keys(mealNames),...mealDraft])].sort((a,b)=>Number(mealDraft.includes(b))-Number(mealDraft.includes(a))||a.localeCompare(b,'ru'));
 showModal('meals','Питание','УСЛОВИЯ ТУРА',`<p class="modal-intro">Можно выбрать несколько вариантов.</p>${choices.length>7?'<div class="meal-search"><label><span class="sr-only">Найти тип питания</span><input id="meal-query" type="search" placeholder="Найти тип питания" autocomplete="off"></label><button type="button" id="meal-clear-query" class="text-button" data-action="clear-meal-query" hidden>Сбросить поиск</button></div>':''}<div class="meal-options">${[['','Любое питание'],...choices.map(m=>[m,m])].map(([v,label])=>`<label class="meal-option"><input type="checkbox" data-meal-choice value="${esc(v)}" ${v?mealDraft.includes(v)?'checked':'':!mealDraft.length?'checked':''}><span><strong>${esc(label)}</strong>${mealHelp[v]?`<small>${esc(mealHelp[v])}</small>`:''}</span></label>`).join('')}</div><p id="meal-no-match" class="meal-no-match" hidden>Такого названия нет. Попробуйте другое или сбросьте поиск.</p>`);
 $('#modal').classList.add('meals-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="meal-result-summary" role="status" aria-live="polite"><strong id="meal-result-preview"></strong><span id="meal-selection-status"></span></div><button class="primary picker-apply" data-action="apply-meals">Применить</button>';
 updateMealCounts();
 updateMealPicker();
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
 if(!count&&complete&&max!==null){const offers=hotels.map(h=>hotelOffers(h,{...model,filters:{...model.filters,max:null}})[0]).filter(Boolean),minimum=offers.length?Math.min(...offers.map(o=>o.total)):null;
  if(minimum!==null&&minimum>max){const ceiling=Math.ceil(minimum/1000)*1000;recovery.dataset.value=ceiling;recovery.textContent=`Увеличить бюджет до ${money(ceiling)}`;recovery.hidden=false;}
 }
}
function openBudget(){
 showModal('budget','Бюджет на весь тур','НА ВСЕХ ТУРИСТОВ',`<p class="modal-intro">За ${guestsText(draft)} · весь тур, а не за ночь.</p><div class="form-row"><label>От, ₽<input type="text" inputmode="decimal" class="input" id="budget-min" value="${state.filters.min}" aria-describedby="budget-error"></label><label>До, ₽<input type="text" inputmode="decimal" class="input" id="budget-max" value="${state.filters.max??''}" placeholder="Без лимита" aria-describedby="budget-error"></label></div><div class="budget-presets">${[150000,200000,300000,null].map(n=>`<button class="chip" data-action="budget-preset" data-value="${n??''}" aria-pressed="false">${n===null?'Без ограничений':'До '+money(n)}</button>`).join('')}</div><p class="error-text" id="budget-error" role="alert"></p><div class="budget-feedback"><p id="budget-preview" role="status" aria-live="polite"></p><button class="text-button" id="budget-recovery" data-action="budget-adjust-max" hidden></button></div><p class="budget-price-note">Цены и наличие уточняются при выборе тура.</p>`);
 $('#modal').classList.add('budget-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<button class="primary picker-apply" data-action="apply-budget">Применить бюджет</button>';updateBudgetPreview();
}

function openDates(source='form'){
 dateContext=createDateContext(source);const s=dateContext.search,selectedDay=source==='results'?state.selectedDate:draftSelectedDate();
 dateDraft={from:selectedDay||s.from,to:selectedDay||s.to,phase:0};
 datePrices=new Map();calendarMonth=dateDraft.from.slice(0,7)+'-01';
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
 for(const h of [...hotels,...calendarHotels])for(const o of hotelOffers(h,{search:s,filters:f,selectedDate:null,onlyFavorites:false}))add(o.day,o.total);
 if(data.observationScopeSupported(s,f))for(const point of calendarObservations)add(point.date,point.price);
}
function calendarPrice(day){return datePrices.get(day)??null;}
function monthFrame(month){
 const date=dateObj(month),first=(date.getUTCDay()+6)%7,count=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth()+1,0)).getUTCDate(),heading=date.toLocaleDateString('ru-RU',{month:'long',year:'numeric',timeZone:'UTC'});
 const prices=Array.from({length:count},(_,i)=>{const d=month.slice(0,8)+String(i+1).padStart(2,'0');return d>=startDay&&d<=endDay?calendarPrice(d):null}),cheapest=Math.min(...prices.filter(p=>p!==null));
 let days='<span></span>'.repeat(first);
 for(let n=1;n<=count;n++){const day=month.slice(0,8)+String(n).padStart(2,'0'),valid=day>=startDay&&day<=endDay,price=prices[n-1];days+=`<button class="month-day ${price!==null&&price===cheapest?'is-cheap':''}" data-action="day-pick" data-date="${day}" ${!valid?'disabled':''} aria-label="${dateLong(day)}${price!==null?', от '+money(price):valid?', цена пока неизвестна':''}"><span>${n}</span><small>${price!==null?(price/1000).toLocaleString('ru-RU',{maximumFractionDigits:1}):valid?'—':''}</small></button>`;}
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
function renderDateSelectionPrice(){const node=$('#date-selection-price');if(!node)return;const from=dateDraft.from,to=dateDraft.to,values=[];if(from&&to&&from<=to&&(dateObj(to)-dateObj(from))/86400000<=21){for(let day=from;day<=to;day=addDays(day,1)){const p=calendarPrice(day);if(Number.isFinite(p)&&p>0)values.push(p);}}node.innerHTML=values.length?`<span>За весь тур · ${esc(guestsText(dateContext.search))}<small>${esc(calendarSourceLabel())}</small></span><strong>от ${money(Math.min(...values))}</strong>`:'<span>На выбранные даты нет подсказки цены.<small>Даты можно выбрать: это не означает, что туров нет.</small></span>';}
function openCalendar(){openDates('results');}
function openGuests(){
 guestDraft={adults:draft.adults,ages:[...draft.ages]};showModal('guests','Кто отправится?','ТУРИСТЫ','');$('#modal').classList.add('guests-dialog');
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
}
function openNights(){nightsDraft={min:draft.minNights,max:draft.maxNights,phase:0};showModal('nights','На сколько ночей?','ПРОДОЛЖИТЕЛЬНОСТЬ',`<p class="modal-intro">Нажмите одно число для точной длительности или два — для диапазона.</p><div class="night-grid" aria-label="Количество ночей">${Array.from({length:28},(_,i)=>`<button data-action="night-pick" data-value="${i+1}" aria-label="${nightsText(i+1)}">${i+1}</button>`).join('')}</div><div class="nights-options">${[7,10,14,21].map(n=>`<button data-action="night-preset" data-value="${n}">${nightsText(n)}</button>`).join('')}</div>`);$('#modal').classList.add('nights-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="nights-footer"><p class="night-selection" aria-live="polite"></p><p class="error-text" id="night-error" role="alert" hidden></p><button class="primary picker-apply" data-action="apply-nights"></button></div>';renderNightSelection();}
function renderNightSelection(){$('#night-error').hidden=!nightsDraft.error;$('#night-error').textContent=nightsDraft.error||'';$$('.night-grid button').forEach(b=>{const n=+b.dataset.value,active=n===nightsDraft.min||n===nightsDraft.max;b.classList.toggle('active',active);b.classList.toggle('in-range',n>nightsDraft.min&&n<nightsDraft.max);b.setAttribute('aria-pressed',n>=nightsDraft.min&&n<=nightsDraft.max)});$$('.nights-options button').forEach(b=>b.setAttribute('aria-pressed',nightsDraft.min===+b.dataset.value&&nightsDraft.max===+b.dataset.value));const label=durationText({minNights:nightsDraft.min,maxNights:nightsDraft.max});$('.night-selection').textContent=nightsDraft.phase?'Выберите вторую границу или подтвердите '+label:'Выбрано: '+label;$('[data-action="apply-nights"]').textContent='Выбрать '+label;}
function openGallery(id,index=0){const h=hotels.find(x=>x.id===id);if(!h?.photos.length){toast('Фотографии этого отеля пока недоступны.');return;}showModal('gallery',h.name,'ФОТОГРАФИИ ОТЕЛЯ','');gallery={id,index};renderGallery();}
function renderGallery(){const focused=document.activeElement?.closest('#modal-body button'),focusAction=focused?.dataset.action,focusValue=focused?.dataset.value;const h=hotels.find(x=>x.id===gallery.id);if(!h?.photos.length)return;$('#modal-body').innerHTML=`<div class="gallery-stage"><img id="gallery-image" src="${esc(photoUrl(h,gallery.index))}" alt="Фото ${gallery.index+1} из ${h.photos.length}" draggable="false"><button class="icon-button gallery-arrow prev" data-action="gallery-prev" aria-label="Предыдущее фото">${icon('back')}</button><button class="icon-button gallery-arrow next" data-action="gallery-next" aria-label="Следующее фото">${icon('arrow')}</button></div><div class="gallery-caption"><span>${esc(h.name)}</span><span>${gallery.index+1} / ${h.photos.length}</span></div><div class="gallery-thumbs">${h.photos.map((p,i)=>`<button data-action="gallery-index" data-value="${i}" class="${gallery.index===i?'active':''}" aria-pressed="${gallery.index===i}" aria-label="Фото ${i+1}"><img src="${esc(p)}" alt="" loading="lazy"></button>`).join('')}</div>`;if(focusAction){const target=$$('#modal-body button').find(b=>b.dataset.action===focusAction&&(focusValue===undefined||b.dataset.value===focusValue));target?.focus({preventScroll:true});}}
let flightDraft=null,andromedaQuoteDraft=null,anexCurrentDraft=null,selectionGeneration=0;
const flightPairFor=o=>o?.flightChoiceId==null||o.flightChoiceId===''?null:o.variants?.[Number(o.flightChoiceId)]||null;
function offerFromKey(key){return hotels.flatMap(h=>h.offers||[]).find(o=>o.key===key)||null;}
function tourHandoffSummary(o){
 const h=selectedTourHotel(o);
 return [o.provider==='fixture'?'ДЕМОНСТРАЦИОННЫЙ ТУР — не для бронирования':'Параметры тура — цена и наличие требуют подтверждения',h?.name||'Отель уточняется','Маршрут: '+(o.origin||o.search?.origin||'Город вылета уточняется')+' → '+[countryNames[h?.country]||countryNames[o.search?.country],h?.resort].filter(Boolean).join(', '),'Даты: '+dateLong(o.day)+' — '+dateLong(o.returnDay)+' · '+nightsText(o.nights),guestsText(o)+(o.ages?.length?' · '+childAgesLabel(o.ages,true):''),'Туроператор: '+o.operator,'Номер: '+o.room,'Питание: '+mealLabel(o),'Перелёт: '+savedFlightTextPlain(o),'Багаж: '+flightAllowanceText(o,'baggage'),'Ручная кладь: '+flightAllowanceText(o,'carryOn'),'Цена предложения за всех: '+(o.pricePending?'уточняется':money(o.total)),'Топливный сбор: '+fuelText(o),'Трансфер и страховка: состав и включение в цену не указаны.','Отмена и изменения: условия не указаны.','Окончательная сумма требует подтверждения. Заявка не отправлена.'].join('\n');
}
function flightAllowanceText(o,field){
 const v=flightPairFor(o);if(!v)return typeof o.savedFlightAllowance?.[field]==='string'?o.savedFlightAllowance[field]:'Уточняется после выбора и проверки рейсов';
 return [['forward','Туда'],['backward','Обратно']].map(([key,label])=>label+': '+window.AnyTourFlightPickerV18.directionAllowance(v[key],field)).join(' · ');
}
function priceExplanationHTML(o){
 const demo=o.provider==='fixture';
 return `<details class="tour-price-explanation"><summary>О цене и багаже</summary><p>${demo?'Это вымышленное предложение для проверки интерфейса.':o.provider==='recorded'?'Цена и рейсы из загруженной записи. Наличие и подлинность источника не проверяются; заявка только учебная.':o.cached?'Сохранённая цена из предложения. Перед оформлением нужно проверить её актуальность и наличие тура.':'Цена предложения требует подтверждения перед оформлением.'}</p><dl><div><dt>Перелёт</dt><dd>${flightPairFor(o)?'Выбранная пара рейсов туда и обратно':'Конкретные рейсы пока не подтверждены'}</dd></div><div><dt>Багаж</dt><dd>${esc(flightAllowanceText(o,'baggage'))}</dd></div><div><dt>Ручная кладь</dt><dd>${esc(flightAllowanceText(o,'carryOn'))}</dd></div></dl></details>`;
}
function fuelAmount(o){const pair=flightPairFor(o),raw=pair||o.savedFuelAmount===undefined?data.fuel(o.tour,pair):o.savedFuelAmount;if(!['number','string'].includes(typeof raw)||String(raw).trim()==='')return null;const n=Number(raw);return Number.isFinite(n)&&n>=0?n:null;}
function fuelText(o){const n=fuelAmount(o);return n===null?'Сбор уточняется':n===0?'Без доплаты по сбору':money(n)+' · включение в цену уточняется';}
function fuelDisclosureHTML(o){
 const n=fuelAmount(o);
 return `<div class="tour-fuel-disclosure"><span>Топливный сбор</span><strong>${esc(fuelText(o))}</strong><p>${n===null?'Размер сбора и его включение в цену нужно проверить.':n===0?'По данным предложения. Другие возможные доплаты требуют проверки.':'Не указано, входит ли сбор в цену предложения или оплачивается дополнительно.'}</p></div>`;
}
function tourConditionsHTML(){return `<details class="tour-section tour-conditions-review" ${innerWidth>760?'open':''}><summary><span>${icon('shield')} Дополнительные условия</span><small>Трансфер, страховка и отмена · нужно уточнить</small></summary><dl>${[['Трансфер','Не указаны маршрут, тип и включение в цену.'],['Страховка','Не указаны покрытие и включение в цену.'],['Отмена и изменения','Сроки, штрафы и возможность возврата не указаны.']].map(([label,note])=>`<div><dt>${label}</dt><dd>${note}</dd></div>`).join('')}</dl><p>Эти условия и окончательную сумму нужно подтвердить перед оформлением.</p></details>`;}
function withFlightPair(o,id){const variant=o.variants?.[Number(id)],price=data.variantPrice(o.tour,variant);return {...o,flightChoiceId:String(id),total:price,pricePending:!price};}
function legHTML(segments,label){
 if(!segments?.length)return `<div class="flight-leg"><strong>${label}</strong><p class="tour-missing">Расписание пока не предоставлено.</p></div>`;
 return segments.map((f,i)=>{const d=f.departure||{},a=f.arrival||{},placeholder=/000$/.test(String(f.number||'').replace(/\s/g,''))&&d.time==='00:00'&&a.time==='00:00';const bag=window.AnyTourFlightPickerV18.allowanceValue(f,'baggage'),carryOn=window.AnyTourFlightPickerV18.allowanceValue(f,'carryOn');return `<div class="flight-leg"><div class="flight-leg-label"><strong>${i?'Пересадка · ':''}${label}</strong><span>${esc(data.text(f.company))} ${esc(placeholder?'Рейс уточняется':f.number||'')}</span></div><div class="flight-timeline"><div><strong>${esc(placeholder?'—':d.time||'—')}</strong><span>${esc(data.text(d.port))}</span><small>${esc(flightDateText(d.date))}</small></div><div class="flight-duration"><i>${icon('plane')}</i><span>${esc(f.plane||'')}</span></div><div><strong>${esc(placeholder?'—':a.time||'—')}</strong><span>${esc(data.text(a.port))}</span><small>${esc(flightDateText(a.date))}</small></div></div><div class="flight-included"><span>${icon('suitcase')} Багаж: ${esc(bag)}</span><span>Ручная кладь: ${esc(carryOn)}</span></div></div>`;}).join('');
}
function flightSummaryHTML(o){
 const v=flightPairFor(o),canChoose=o.variants?.length;
 const action=canChoose?`<button class="secondary" data-action="choose-flight">${v?'Изменить рейсы':'Выбрать рейсы'} ${icon('arrow')}</button>`:'';
 const content=o.flightsLoading?'<p role="status">Загружаем варианты рейсов…</p>':v?`${window.AnyTourFlightPickerV18.pairSummary(v,{esc,text:data.text})}<details class="tour-flight-details"><summary>Детали рейсов</summary><div>${legHTML(v.forward,'Туда')+legHTML(v.backward,'Обратно')}</div></details>`:o.savedFlightText&&o.savedFlightText!=='Рейс пока не выбран'?'<p class="saved-flight-notice">Сохранённый перелёт · расписание требует проверки</p>'+savedFlightSummaryHTML(o):`<p class="tour-missing">${esc(o.flightsError||'Варианты рейсов пока не предоставлены.')}</p>`;
 return `<section class="tour-section flight-summary"><div class="tour-section-heading"><h3>${icon('plane')} Перелёт</h3>${action}</div>${content}</section>`;
}
async function openOffer(key,restored=null,chooseFlight=false){
 const initial=restored||offerFromKey(key);if(!initial)return;if(selectedOffer?.key!==key)window.AnyTourPrototypeLead.reset();
 const run=++selectionGeneration;selectedOffer={...initial};renderRealOffer();
 if(needsRefresh(initial))return;
 selectedOffer.loading=true;renderRealOffer();
 try{const tour=await data.quote(initial);if(run!==selectionGeneration||!$('#modal').open||modalType!=='offer'||selectedOffer?.key!==key)return;selectedOffer={...initial,tour,quoteListingTotal:initial.total,total:data.amount(tour.price)||initial.total,room:data.text(tour.roomType)||initial.room,meal:data.meal(tour.meal)||initial.meal,loading:false,flightsLoading:true};renderRealOffer();await loadRealFlights(run);if(chooseFlight&&run===selectionGeneration&&selectedOffer?.variants?.length)openFlightPicker();}
 catch(error){if(run===selectionGeneration&&$('#modal').open&&modalType==='offer'&&selectedOffer?.key===key){selectedOffer={...initial,quoteError:error.message,quoteErrorCode:String(error?.code||''),loading:false};renderRealOffer();}}
}
async function loadRealFlights(run=selectionGeneration){
 const o=selectedOffer;if(!o?.tour)return;selectedOffer.flightsLoading=true;renderRealOffer();
 try{const variants=await data.flights(o.tour);if(run!==selectionGeneration||selectedOffer?.key!==o.key||!$('#modal').open||modalType!=='offer')return;const index=Math.max(0,variants.findIndex(v=>v.isDefault));selectedOffer={...selectedOffer,variants,flightsLoading:false,flightsError:''};if(variants.length)selectedOffer=withFlightPair(selectedOffer,String(index));renderRealOffer();}
 catch(error){if(run===selectionGeneration&&$('#modal').open&&modalType==='offer'&&selectedOffer?.key===o.key){selectedOffer={...selectedOffer,flightsLoading:false,flightsError:'Не удалось загрузить рейсы. Попробуйте ещё раз.'};renderRealOffer();}}
}
function quotePriceChangeHTML(o){
 const before=Number(o?.quoteListingTotal),after=data.amount(o?.tour?.price);
 if(o?.loading||o?.quoteError||!Number.isFinite(before)||before<=0||!after||before===after)return '';
 return `<p class="quote-price-change" role="status"><strong>Цена изменилась после проверки</strong><span>Было ${money(before)} → стало ${money(after)} за всех туристов.</span></p>`;
}
function chosenStayHTML(o,editable=false){
 const placement=data.text(o.tour?.placement)||o.placement;
 const alternatives=editable&&hotelOffers(selectedTourHotel(o)).length>1;
 return `<section class="chosen-stay" aria-label="Выбранные условия тура"><p class="chosen-trip"><strong>${rangeText(o.day,o.returnDay)} · ${nightsText(o.nights)}</strong><span>${guestsText(o)}${o.ages?.length?' · '+esc(childAgesLabel(o.ages,true)):''}</span></p><dl class="saved-stay-summary"><div><dt>Номер</dt><dd>${esc(o.room)}</dd></div><div><dt>Питание</dt><dd>${esc(mealLabel(o))}</dd></div>${placement?`<div><dt>Размещение</dt><dd>${esc(placement)}</dd></div>`:''}<div><dt>Оператор</dt><dd>${esc(o.operator)}</dd></div></dl>${alternatives?'<button class="text-button change-room" data-action="change-room">Другие номера и питание '+icon('arrow')+'</button>':''}</section>`;
}
function renderRealOffer(){
 const o=selectedOffer,h=selectedTourHotel(o);if(!o||!h)return;
 const unavailable=needsRefresh(o);
 const terminalQuoteError=['offer_unavailable','offer_expired'].includes(o.quoteErrorCode);
 const selectionHint=o.loading?'Проверяем цену и условия тура…':o.flightsLoading?'Загружаем варианты перелёта…':terminalQuoteError?'Выберите другой тур в результатах.':o.quoteError?'Повторите проверку предложения, чтобы продолжить.':o.pricePending?'Выберите перелёт с подтверждённой ценой.':o.flightsError?'Повторите загрузку рейсов в разделе перелёта.':!o.variants?.length||!o.tour?'Для выбора тура нужны актуальная цена и доступные рейсы.':'';
 showModal('offer','Ваш тур в деталях',o.loading?'ПРОВЕРЯЕМ ПРЕДЛОЖЕНИЕ':'ПРОВЕРЬТЕ УСЛОВИЯ',`
 ${unavailable?'':selectionStepsHTML(o.loading||o.quoteError?0:1)}${quotePriceChangeHTML(o)}<div class="tour-hero">${h.photos?.length?`<img src="${esc(photoUrl(h))}" alt="Фото ${esc(h.name)}">`:`<div class="tour-photo-missing">${icon('image')}<span>Нет фото</span></div>`}<div>${hotelStarsHTML(h)}<h3>${esc(h.name)}</h3><p>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</p></div></div>

 ${unavailable?(data.live?`<p class="saved-tour-notice">${esc(refreshOfferNotice(o))}</p>`:window.AnyTourPrototypeLead.unavailableMarkup(o)):''}
 ${o.quoteError?`<p class="error-text" role="alert">${esc(o.quoteError)}</p>`:''}
 ${o.pricePending?'<p class="error-text" role="status">Цена выбранного перелёта пока не подтверждена. Выберите другой вариант рейсов.</p>':''}

 <div class="tour-layout"><div class="tour-main-details">${chosenStayHTML(o,true)}${flightSummaryHTML(o)}${tourConditionsHTML()}</div>
 <aside class="tour-price-details" aria-label="Состав и стоимость тура"><div class="price-breakdown"><h3>Цена и условия</h3><p class="price-party">За ${guestsText(o)} · ${nightsText(o.nights)}</p><div class="price-line total"><span>${o.quoteError?'Цена из выдачи':flightPairFor(o)?'С выбранным перелётом':'Цена предложения'}</span><strong id="detail-total">${o.pricePending?'Уточняется':money(o.total)}</strong></div>${!unavailable?`<p class="price-assurance">${icon('info')} ${o.quoteError?'Эта сумма не подтверждена после проверки.':o.provider==='fixture'?priceNote(o):o.loading?'Проверяем актуальность…':unavailable?priceNote(o):'Условия цены и наличие подтверждаются перед оформлением'}</p>`:''}${!unavailable&&selectionHint?`<p class="tour-selection-hint" role="status">${selectionHint}</p>`:''}${fuelDisclosureHTML(o)}${priceExplanationHTML(o)}</div></aside></div>`,true);
 $('#modal').classList.add('tour-dialog');
 const footerAction=unavailable?(data.live?`<button class="primary" data-action="refresh-hotel" data-id="${h.id}" ${o.loading?'disabled':''}>${o.loading?'Проверяем предложение…':o.quoteError?'Повторить проверку':o.raw?.anexKind==='group_minimum'?'Показать конкретные туры':refreshOfferActionLabel(o)}</button>`:'<button class="primary" data-action="close-modal">К результатам</button>'):terminalQuoteError?'<button class="primary" data-action="close-modal">К результатам</button>':o.quoteError?`<button class="primary" data-action="offer" data-key="${esc(o.key)}">Повторить проверку</button>`:o.loading?'<button class="primary" disabled>Проверяем предложение…</button>':o.flightsLoading?'<button class="primary" disabled>Загружаем рейсы…</button>':o.flightsError?'<button class="primary" data-action="retry-flights">Повторить загрузку рейсов</button>':o.pricePending&&o.variants?.length?'<button class="primary" data-action="choose-flight">Выбрать другой рейс</button>':!o.variants?.length&&o.tour?'<button class="primary" data-action="retry-flights">Загрузить рейсы снова</button>':`<button class="primary" data-action="confirm-tour" ${!o.tour?'disabled':''}>К заявке ${icon('arrow')}</button>`;
 const footerStatus=o.quoteError?'Цена из выдачи · не подтверждена':o.provider==='fixture'?'Демонстрационная цена':'Цена требует подтверждения';
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total"><span>${o.quoteError?'Цена из выдачи за всех':'За всех туристов'}</span><strong>${o.pricePending?'Цена уточняется':money(o.total)}</strong><small class="footer-price-status">${footerStatus}</small></div>${footerAction}`;
}
function openFlightPicker(){
 if(!selectedOffer?.variants?.length)return;flightDraft={base:selectedOffer,id:selectedOffer.flightChoiceId};const o=selectedOffer;
 showModal('flights','Выберите перелёт','ТУДА И ОБРАТНО',`${selectionStepsHTML(1)}<div class="flight-picker-context"><strong>${esc(hotels.find(h=>h.id===o.hotelId).name)}</strong><span>${rangeText(o.day,o.returnDay)} · ${nightsText(o.nights)} · ${guestsText(o)}</span></div><p class="flight-picker-note">${o.provider==='fixture'?'Демо · ':''}Время местное · цены за весь тур</p>${window.AnyTourFlightPickerV18.render(o,flightDraft.id,{esc,money,text:data.text,price:data.variantPrice,legHTML,fuelText})}`,true);
 window.AnyTourFlightPickerV18.bind($('#modal-body'),money);
 $('#modal').classList.add('flight-picker-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="flight-selection-total" aria-live="polite"><span>Весь тур за всех</span><strong id="flight-total"></strong></div><button class="primary" data-action="apply-flight">Выбрать рейсы</button><div class="flight-selection-caption" aria-live="polite"><span id="flight-selected-summary"></span><span id="flight-price-change"></span></div>';updateFlightPreview();
 $('#modal-title').tabIndex=-1;$('#modal-title').focus({preventScroll:true});$('#modal-body').scrollTop=0;
}
function updateFlightPreview(){if(!flightDraft||modalType!=='flights')return;const o=withFlightPair(flightDraft.base,flightDraft.id),delta=o.total-flightDraft.base.total;$('#flight-price-change').hidden=!o.pricePending&&delta===0;$('#flight-selected-summary').textContent=window.AnyTourFlightPickerV18.selectionSummary(flightDraft.base,flightDraft.id);$('#flight-total').textContent=o.pricePending?'Цена уточняется':money(o.total);$('#flight-price-change').textContent=o.pricePending?'Цена этого перелёта пока не подтверждена. Выберите другой вариант.':(delta===0?'Цена тура не изменится':`${delta>0?'Дороже':'Дешевле'} на ${money(Math.abs(delta))} · было ${money(flightDraft.base.total)}`);$('[data-action="apply-flight"]').disabled=o.pricePending;}

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
function openAndromedaFlightChoice(o,quote){
 const h=selectedTourHotel(o),outbound=quote.flights.filter(f=>f.direction==='0'),inbound=quote.flights.filter(f=>f.direction==='1');
 if(!h||!outbound.length||!inbound.length){selectedOffer={...o,loading:false,quoteError:'Andromeda не вернул полный выбор перелёта.'};renderRealOffer();return;}
 andromedaQuoteDraft={offer:o,quote};
 showModal('andromeda-flights','Выберите перелёт','ANDROMEDA · ПРОВЕРКА ТУРА',`<div class="flight-picker-context"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span></div><p class="flight-picker-note">Выберите один рейс туда и один обратно. Цена будет подтверждена Andromeda после выбора.</p><fieldset class="flight-options"><legend>Туда</legend>${outbound.map((f,i)=>`<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="andromeda-outbound" value="${esc(f.flightRef)}" ${i===0?'checked':''}><span><strong>${esc(f.name||'Рейс '+(i+1))}</strong><small>${esc([data.text(f.departure?.port),data.text(f.arrival?.port)].filter(Boolean).join(' → '))}</small></span></div></label>`).join('')}</fieldset><fieldset class="flight-options"><legend>Обратно</legend>${inbound.map((f,i)=>`<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="andromeda-return" value="${esc(f.flightRef)}" ${i===0?'checked':''}><span><strong>${esc(f.name||'Рейс '+(i+1))}</strong><small>${esc([data.text(f.departure?.port),data.text(f.arrival?.port)].filter(Boolean).join(' → '))}</small></span></div></label>`).join('')}</fieldset><p class="error-text" id="andromeda-quote-error" role="alert"></p>`,true);
 $('#modal').classList.add('flight-picker-dialog');$('#modal-footer').hidden=false;
 $('#modal-footer').innerHTML='<button class="secondary" data-action="modal-back">Отмена</button><button class="primary" data-action="apply-andromeda-flights">Проверить выбранные рейсы</button>';
}
function openAndromedaVerified(o,quote){
 const h=selectedTourHotel(o),price=Number(quote.finalPrice?.amount);
 if(!h||!Number.isFinite(price)||price<=0){selectedOffer={...o,loading:false,quoteError:'Andromeda не подтвердил итоговую цену.'};renderRealOffer();return;}
 andromedaQuoteDraft=null;selectedOffer={...o,loading:false};
 showModal('andromeda-verified','Тур подтверждён','ANDROMEDA · АКТУАЛЬНЫЕ УСЛОВИЯ',`<div class="verification-tour"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span><span>${esc(o.room)} · ${esc(mealLabel(o))}</span><strong>${money(price)}</strong></div>${quote.flights.length?`<section class="tour-section"><h3>${icon('plane')} Подтверждённые рейсы</h3>${quote.flights.map(andromedaFlightRoute).join('')}</section>`:''}<p class="modal-intro">Цена подтверждена поставщиком для выбранного предложения. Оформление заявки из Andromeda в этой preview-версии пока не подключено.</p>`,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button><button class="primary" data-action="close-modal">Готово</button>`;
}
async function applyAndromedaFlightChoice(){
 const draft=andromedaQuoteDraft;if(!draft||modalType!=='andromeda-flights')return;
 const outbound=$('input[name="andromeda-outbound"]:checked')?.value,back=$('input[name="andromeda-return"]:checked')?.value;
 if(!outbound||!back)return;
 const run=++selectionGeneration,button=$('[data-action="apply-andromeda-flights"]');button.disabled=true;
 $('#andromeda-quote-error').textContent='';
 try{
  const quote=await data.verifyAndromeda(draft.offer,{provider:'andromeda',outbound_ref:outbound,return_ref:back});
  if(run!==selectionGeneration||modalType!=='andromeda-flights')return;
  if(quote.state!=='quote_verified')throw new Error('Andromeda не подтвердил выбранный перелёт.');
  openAndromedaVerified(draft.offer,quote);
 }catch(error){
  if(run===selectionGeneration&&modalType==='andromeda-flights'){
   $('#andromeda-quote-error').textContent=error.message;button.disabled=false;
  }
 }
}
function openAnexConcreteCurrent(o,current){
 const h=selectedTourHotel(o),ready=current?.finalPriceReady===true,price=ready?Number(current.finalPrice?.amount):Number(o.total);
 if(!h||!Number.isFinite(price)||price<=0){selectedOffer={...o,loading:false,quoteError:'ANEX не вернул корректную цену предложения.'};renderRealOffer();return;}
 selectedOffer={...o,loading:false};anexCurrentDraft={offer:o,current};
 showModal('anex-current','Тур ANEX проверен','ANEX · АКТУАЛЬНОЕ ПРЕДЛОЖЕНИЕ',`<div class="verification-tour"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span><span>${esc(o.room)} · ${esc(mealLabel(o))}</span><strong>${money(price)}</strong></div><p class="modal-intro">${ready?'ANEX подтвердил текущее предложение. Показанная сумма включает сохранённую расчётную обязательную доплату, но не помечается как финально подтверждённая цена.':'ANEX подтвердил, что конкретное предложение всё ещё актуально. Показана цена из текущего поиска; обязательные доплаты и итоговая цена ещё требуют подтверждения.'}</p><p class="modal-intro">Оформление заявки из ANEX в этой preview-версии пока не подключено.</p><p class="error-text" id="anex-additional-error" role="alert"></p>`,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=ready
  ?`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button><button class="primary" data-action="close-modal">Готово</button>`
  :`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button><button class="primary" data-action="anex-additional-prices">Уточнить обязательные доплаты</button>`;
}
function openAnexAdditionalEstimate(o,result){
 const h=selectedTourHotel(o),search=Number(result?.searchPrice?.amount),surcharge=Number(result?.partySurcharge?.amount),total=Number(result?.calculatedTotal?.amount);
 if(!h||![search,surcharge,total].every(value=>Number.isFinite(value)&&value>0)){throw new Error('ANEX вернул некорректный расчёт доплат.');}
 anexCurrentDraft=null;
 showModal('anex-additional','Доплаты ANEX рассчитаны','ANEX · ОБЯЗАТЕЛЬНЫЕ ДОПЛАТЫ',`<div class="verification-tour"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span><span>${esc(o.room)} · ${esc(mealLabel(o))}</span></div><div class="price-breakdown"><div class="price-line"><span>Цена из поиска ANEX</span><strong>${money(search)}</strong></div><div class="price-line"><span>Обязательная доплата за состав туристов</span><strong>${money(surcharge)}</strong></div><div class="price-line total"><span>Расчётная сумма с доплатой</span><strong>${money(total)}</strong></div></div><p class="modal-intro">Доплата и сумма рассчитаны сервером по данным ANEX для этого конкретного предложения. Это расчёт обязательных доплат, а не финально подтверждённая цена бронирования.</p><p class="modal-intro">Оформление заявки из ANEX в этой preview-версии пока не подключено.</p>`,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<button class="secondary" data-action="all-offers" data-id="${h.id}">К вариантам</button><button class="primary" data-action="close-modal">Готово</button>`;
}
async function applyAnexAdditionalPrices(){
 const draft=anexCurrentDraft;if(!draft||modalType!=='anex-current')return;
 const run=++selectionGeneration,button=$('[data-action="anex-additional-prices"]');if(button)button.disabled=true;
 const error=$('#anex-additional-error');if(error)error.textContent='';
 try{
  const result=await data.verifyAnexAdditional(draft.offer);
  if(run!==selectionGeneration||modalType!=='anex-current')return;
  openAnexAdditionalEstimate(draft.offer,result);
 }catch(e){
  if(run===selectionGeneration&&modalType==='anex-current'){
   const node=$('#anex-additional-error');if(node)node.textContent=e.message+' Для новой попытки повторите поиск.';
  }
 }
}
async function refreshLiveHotel(id){
 const o=selectedOffer,h=selectedTourHotel(o);
 if(!o||o.hotelId!==id||!needsRefresh(o)||!h){toast('Не удалось определить варианты отеля. Повторите общий поиск.');return;}
 if(o.provider==='anex'&&o.raw?.anexKind==='concrete'&&o.raw?.anexSessionCurrent===true){
  const run=++selectionGeneration;selectedOffer={...o,loading:true,quoteError:''};renderRealOffer();
  try{
   const current=await data.verifyAnexConcrete(o);
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
    selectedOffer={...o,loading:false,quoteError:error.message};renderRealOffer();
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
function openLeadPreview(){
 const o=selectedOffer,h=selectedTourHotel(o);if(!o||!h)return;if(needsRefresh(o)||!window.AnyTourPrototypeLead.canApply(o)){renderRealOffer();return;}
 showModal('selected-tour','Заявка на тур',data.live?'ПРОВЕРКА БЕЗ ОТПРАВКИ':'УЧЕБНЫЙ СЦЕНАРИЙ',`${selectionStepsHTML(2)}${quotePriceChangeHTML(o)}<section class="application-choice" aria-label="Выбранный тур"><h3>${esc(h.name)}</h3>${chosenStayHTML(o)}</section>${window.AnyTourPrototypeLead.markup(o)}${finalFlightDetailsHTML(o)}`);
 $('#modal').classList.add('application-dialog');
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total summary-price"><span>За всех туристов</span><strong>${money(o.total)}</strong><small>${data.live?'С выбранным перелётом':o.provider==='recorded'?'Цена из записи':'Демонстрационная цена'}</small></div>${window.AnyTourPrototypeLead.action(o)}`;
 window.AnyTourPrototypeLead.bind(o);
}

let offerView=null,comparisonQuotes=[];
const offerGroupKey=o=>encodeURIComponent(o.room+'|'+o.meal);
const sharedOfferNote=offers=>{const notes=[...new Set(offers.map(offerMetaNote))];return notes.length===1?notes[0]:'';};
function openAllOffers(id,restored=null){
 const h=hotels.find(h=>h.id===id),all=hotelOffers(h);
 offerView={id,departure:'',flight:'',room:'',meal:'',sort:'price',mode:'list',day:all[0]?.day||state.search.from,nights:all[0]?.nights||state.search.minNights,pair:[],activeVariant:null,differencesOnly:false,open:[],limits:{}};
 if(restored){for(const [name,values] of Object.entries({departure:['',...all.map(o=>o.day)],flight:['','regular','charter','unknown'],room:['',...all.map(o=>o.room)],meal:['',...all.map(o=>o.meal)],sort:['price','date'],mode:optionalShortlistEnabled?['list','compare']:['list']}))if(values.includes(restored[name]))offerView[name]=restored[name];if(all.some(o=>o.day===restored.day))offerView.day=restored.day;if(all.some(o=>o.nights===restored.nights))offerView.nights=restored.nights;}
 if(restored){if(Array.isArray(restored.pair))offerView.pair=[...new Set(restored.pair.filter(v=>all.some(o=>o.variant===v)))].slice(0,2);if(all.some(o=>o.variant===restored.activeVariant))offerView.activeVariant=restored.activeVariant;offerView.differencesOnly=restored.differencesOnly===true;}
 showModal('all-offers',h.name,'ВЫБЕРИТЕ КОНКРЕТНЫЙ ТУР',`${all.some(o=>!needsRefresh(o))?selectionStepsHTML(0):''}<div class="offer-list-context"><p>${offerSearchContext()} · ${durationText()} · ${guestsText()}</p><span>${icon('info')} Все цены за всех туристов. ${esc(sharedOfferNote(all)||(all.every(needsRefresh)?'Цены и наличие требуют проверки':'Сборы уточняются при выборе'))}</span></div>${optionalShortlistEnabled?'<div class="offer-view-switch" role="group" aria-label="Как показать туры"><button data-action="offer-view" data-value="list" aria-pressed="true">Все туры</button><button data-action="offer-view" data-value="compare" aria-pressed="false">Сравнить на даты</button></div>':''}<div id="offer-comparison-dates" class="offer-comparison-dates" hidden></div><details class="offer-filter-disclosure" ${innerWidth>760?'open':''}><summary><span>Уточнить варианты <span id="offer-local-filter-count"></span></span><small id="offer-filter-summary"></small></summary><div class="offer-controls"><label class="offer-departure-filter">Дата вылета<select id="offer-departure"><option value="">Все даты</option>${[...new Set(all.map(o=>o.day))].sort().map(day=>`<option value="${day}">${dateText(day)}</option>`).join('')}</select></label><label>Перелёт<select id="offer-flight"><option value="">Любой</option>${[...new Set(all.map(o=>o.flight))].map(f=>`<option value="${f}">${flightLabel({flight:f})}</option>`).join('')}</select></label><label>Номер<select id="offer-room"><option value="">Любой</option>${[...new Set(all.map(o=>o.room))].map(room=>`<option value="${esc(room)}">${esc(room)}</option>`).join('')}</select></label><label>Питание<select id="offer-meal"><option value="">Любое</option>${[...new Set(all.map(o=>o.meal))].map(m=>`<option value="${esc(m)}">${esc(m)}</option>`).join('')}</select></label></div></details><div id="offer-local-selected" class="offer-local-selected" aria-label="Условия выбора тура" hidden></div><div class="offer-list-toolbar"><span id="offer-count" aria-live="polite" tabindex="-1"></span><label id="offer-sort-label"><span class="sr-only">Сортировать туры</span><select id="offer-sort"><option value="price">Сначала дешевле</option><option value="date">По дате вылета</option></select></label></div><button class="text-button offer-reset" data-action="reset-offer-filters" hidden>Сбросить фильтры туров</button><div id="all-offers-list"></div>`,true);
 $('#modal').classList.add('offers-dialog');renderOfferList(true);
}
const offerCountText=n=>`${n} ${n%10===1&&n%100!==11?'тур':n%10>=2&&n%10<=4&&(n%100<12||n%100>14)?'тура':'туров'}`;
const offerSearchContext=()=>state.selectedDate?`Вылет ${dateText(state.selectedDate)}`:state.search.from===state.search.to?`Вылет ${dateText(state.search.from)}`:`Период вылета: ${dateText(state.search.from)} — ${dateText(state.search.to)}`;
const comparisonVariantLabel=o=>money(o.total)+' · '+o.room+' · '+mealLabel(o)+' · '+o.operator+' · '+flightLabel(o);
function normalizeComparison(options){
 const ids=options.map(o=>o.variant),pair=(offerView.pair||[]).filter((v,i,a)=>ids.includes(v)&&a.indexOf(v)===i).slice(0,2);
 if(!pair.length&&ids.length){pair.push(ids[0]);if(ids.includes(offerView.activeVariant)&&offerView.activeVariant!==ids[0])pair.push(offerView.activeVariant);}
 if(pair.length<2&&ids.length>1)pair.push(ids.find(v=>!pair.includes(v)));
 if(!ids.includes(offerView.activeVariant))offerView.activeVariant=pair[0]??null;
 if(pair.length&&!pair.includes(offerView.activeVariant))offerView.activeVariant=pair[0];
 offerView.pair=pair;
 return pair.map(v=>options.find(o=>o.variant===v));
}
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
const comparisonFields=[['room','номер',o=>o.room||'Уточняется'],['meal','питание',mealLabel],['operator','туроператор',o=>o.operator||'Уточняется'],['placement','размещение',o=>o.placement||'Уточняется'],['flight','тип перелёта',flightLabel],['returnDay','дата возвращения',o=>o.returnDay||'Уточняется']];
function comparisonDecisionHTML(visible){
 if(visible.length<2)return '<div class="comparison-decision" role="status"><strong>На эти даты и условия один вариант</strong><p>Можно изменить дату или фильтры, чтобы найти другие предложения.</p></div>';
 const [first,second]=visible,delta=second.total-first.total,differences=comparisonFields.filter(([, ,value])=>value(first)!==value(second)).map(([,label])=>label);
 return `<div class="comparison-decision" role="status"><strong>${delta===0?'Цена двух вариантов одинакова':`Второй вариант ${delta>0?'дороже':'дешевле'} на ${money(Math.abs(delta))}`}</strong><p>${differences.length?'Различаются: '+differences.map(esc).join(', ')+'.':'Показанные условия совпадают. Конкретные рейсы и полные условия уточняются для каждого тура.'}</p></div>`;
}
function renderTourComparison(all,filtered){
 const dates=[...new Set(all.map(o=>o.day))].sort();if(!dates.includes(offerView.day))offerView.day=dates[0]||state.search.from;
 const nights=[...new Set(all.filter(o=>o.day===offerView.day).map(o=>o.nights))].sort((a,b)=>a-b);if(!nights.includes(offerView.nights))offerView.nights=nights[0]||state.search.minNights;
 const options=filtered.filter(o=>o.day===offerView.day&&o.nights===offerView.nights).sort((a,b)=>a.total-b.total);comparisonQuotes=options;
 const visible=normalizeComparison(options),minPrice=visible.length?Math.min(...visible.map(o=>o.total)):null;
 const fact=(key,label,value)=>{const get=comparisonFields.find(([field])=>field===key)?.[2]||((o)=>o[key]);const different=new Set(visible.map(get)).size>1;return offerView.differencesOnly&&visible.length>1&&!different?'':`<div class="${different?'fact-different':''}"><dt>${label}</dt><dd>${esc(value)}</dd></div>`;};
 const datesExpanded=$('.comparison-date-disclosure')?.open===true;
 const dateFields=`<div class="comparison-date-fields"><label>Дата вылета<select id="compare-offer-day" ${dates.length<2?'disabled':''}>${dates.map(day=>`<option value="${day}" ${day===offerView.day?'selected':''}>${dateText(day)}</option>`).join('')}</select></label><label>Ночей<select id="compare-offer-nights" ${nights.length<2?'disabled':''}>${nights.map(n=>`<option value="${n}" ${n===offerView.nights?'selected':''}>${nightsText(n)}</option>`).join('')}</select></label></div>`;
 $('#offer-comparison-dates').innerHTML=dates.length>1||nights.length>1?`<details class="comparison-date-disclosure" ${datesExpanded?'open':''}><summary>Вылет ${dateText(offerView.day)} · ${nightsText(offerView.nights)} <span>Изменить</span></summary>${dateFields}</details>`:`<p class="comparison-fixed-dates" tabindex="-1">Вылет ${dateText(offerView.day)} · ${nightsText(offerView.nights)}</p>`;
 $('#offer-count').textContent=offerCountText(options.length);
 const controls=options.length>2?`<div class="comparison-pair-fields">${offerView.pair.map((v,i)=>`<label>${i?'Второй вариант':'Первый вариант'}<select id="comparison-pair-${i}">${options.map(o=>`<option value="${o.variant}" ${o.variant===v?'selected':''}>${esc(comparisonVariantLabel(o))}</option>`).join('')}</select></label>`).join('')}</div>`:'';
 $('#all-offers-list').innerHTML=options.length?`<div class="comparison-options-tools"><label class="comparison-differences-switch"><input type="checkbox" id="tour-differences-only" ${offerView.differencesOnly&&options.length>1?'checked':''} ${options.length<2?'disabled':''}> Только отличия</label><span class="comparison-visible-count">${offerCountText(options.length)}</span></div>${controls}${comparisonDecisionHTML(visible)}<div class="tour-comparison-grid" data-count="${visible.length}" data-total-count="${options.length}" data-visible-count="${visible.length}">${visible.map(o=>`<article class="tour-comparison-option ${o.total===minPrice?'best-price':''} ${offerView.activeVariant===o.variant?'focused-option':''}" data-variant="${o.variant}" style="--comparison-order:${offerView.pair.indexOf(o.variant)}"><label class="comparison-focus-choice"><input type="radio" name="comparison-focus" value="${o.variant}" ${offerView.activeVariant===o.variant?'checked':''}><span>${offerView.pair.indexOf(o.variant)===0?'Первый вариант':'Второй вариант'}</span></label><div class="comparison-option-top"><span>${visible.length===1?'Вариант тура':o.total===minPrice?'Минимум в сравнении':'Вариант тура'}</span>${operatorBadge(o.operator)}</div><h3>${esc(o.room)}</h3><dl class="comparison-tour-facts">${fact('meal','Питание',mealLabel(o))}${fact('operator','Туроператор',o.operator)}${fact('placement','Размещение',o.placement||'Уточняется')}${fact('flight','Тип перелёта',flightLabel(o))}<div><dt>Вылет — возвращение</dt><dd>${rangeText(o.day,o.returnDay)}</dd></div></dl><div class="comparison-option-price"><span>За всех туристов</span><strong>${money(o.total)}</strong><small>${cardPriceNote(o)}</small></div><div class="comparison-option-actions"><button class="primary" data-action="offer" data-key="${esc(o.key)}">${offerActionLabel(o)} ${icon('arrow')}</button>${needsRefresh(o)?'':`<button class="secondary" data-action="offer-flights" data-key="${esc(o.key)}">${icon('plane')} Рейсы и багаж</button>`}</div></article>`).join('')}</div>`:'<div class="destination-empty"><h3>Нет вариантов на эти даты</h3><p>Измените дату или фильтры.</p></div>';renderComparisonFooter();
}
const offerRefinementFields=['departure','flight','room','meal'];
const offerRefinementLabels={departure:'Дата',flight:'Перелёт',room:'Номер',meal:'Питание'};
const offerRefinementAny={departure:'Любая дата',flight:'Любой перелёт',room:'Любой номер',meal:'Любое питание'};
function matchesOfferRefinements(o,view=offerView){return offerRefinementFields.every(field=>!view[field]||(field==='departure'?o.day:o[field])===view[field]);}
function offerRefinementLabel(field,value){return field==='departure'?dateText(value):field==='flight'?flightLabel({flight:value}):field==='meal'?value:value;}
function renderOfferRefinements(all,comparing){
 const host=$('#offer-local-selected');
 host.hidden=comparing||!offerRefinementFields.some(field=>offerView[field]);
 host.innerHTML=comparing?'':offerRefinementFields.filter(field=>offerView[field]).map(field=>`<button type="button" class="active-filter" data-action="remove-offer-filter" data-field="${field}" aria-label="Убрать условие: ${offerRefinementLabels[field]} — ${esc(offerRefinementLabel(field,offerView[field]))}"><span>${offerRefinementLabels[field]}: ${esc(offerRefinementLabel(field,offerView[field]))}</span>${icon('x')}</button>`).join('');
 const visibleFields=[];
 for(const field of offerRefinementFields){
  const select=$('#offer-'+field);
  const hasChoice=new Set(all.map(o=>field==='departure'?o.day:o[field])).size>1;
  const visible=comparing?field!=='departure':hasChoice||!!offerView[field];
  select.closest('label').hidden=!visible;if(visible)visibleFields.push(field);
  for(const option of select.options){
   if(!option.dataset.baseLabel)option.dataset.baseLabel=option.textContent;
   const count=all.filter(o=>matchesOfferRefinements(o,{...offerView,[field]:option.value})).length;
   option.textContent=comparing?option.dataset.baseLabel:`${option.dataset.baseLabel} · ${offerCountText(count)}`;
  }
 }
 $('.offer-filter-disclosure').hidden=!comparing&&!visibleFields.length;
 $('.offer-controls').style.setProperty('--offer-filter-columns',Math.min(2,visibleFields.length)||1);
 if(!comparing&&!offerRefinementFields.some(field=>offerView[field]))$('#offer-filter-summary').textContent=visibleFields.map(field=>offerRefinementLabels[field]).join(' · ');
}
function offerRefinementRecovery(all){
 const choices=offerRefinementFields.filter(field=>offerView[field]).map(field=>({field,count:all.filter(o=>matchesOfferRefinements(o,{...offerView,[field]:''})).length})).filter(choice=>choice.count);
 return choices.length?`<p>Можно убрать одно условие, сохранив остальные:</p><div class="offer-recovery-actions">${choices.map(({field,count})=>`<button type="button" class="secondary" data-action="remove-offer-filter" data-field="${field}">${offerRefinementAny[field]} · ${offerCountText(count)}</button>`).join('')}</div>`:'<p>Измените условия выбора тура. Даты поездки и туристы в основном поиске сохранятся.</p>';
}
function renderOfferList(reset=false){
 if(!optionalShortlistEnabled)offerView.mode='list';
 const h=hotels.find(h=>h.id===offerView.id),all=hotelOffers(h),filtered=all.filter(o=>(offerView.mode==='compare'||!offerView.departure||o.day===offerView.departure)&&(!offerView.flight||o.flight===offerView.flight)&&(!offerView.room||o.room===offerView.room)&&(!offerView.meal||o.meal===offerView.meal));
 const sorted=[...filtered].sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total:a.total-b.total||a.day.localeCompare(b.day));
 const groups=[...new Set(sorted.map(offerGroupKey))].map(key=>({key,offers:sorted.filter(o=>offerGroupKey(o)===key)}));
 if(reset){offerView.open=groups.length===1?[groups[0].key]:[];offerView.limits={};}
 for(const name of ['departure','flight','room','meal','sort'])$('#offer-'+name).value=offerView[name];
 const comparing=offerView.mode==='compare',wasComparing=$('#modal').classList.contains('tour-comparison-dialog');if(comparing!==wasComparing)$('.offer-filter-disclosure').open=!comparing&&innerWidth>760;$('#modal').classList.toggle('tour-comparison-dialog',comparing);$('#offer-comparison-dates').hidden=!comparing;$('#offer-sort-label').hidden=comparing||new Set(filtered.map(o=>o.day)).size<2;$$('[data-action="offer-view"]').forEach(b=>b.setAttribute('aria-pressed',b.dataset.value===offerView.mode));
 $('.offer-departure-filter').hidden=comparing;const localCount=[...(comparing?[]:['departure']),'flight','room','meal'].filter(k=>offerView[k]).length;$('.offer-reset').hidden=localCount<(comparing?1:2)||(!comparing&&!filtered.length);$('#offer-local-filter-count').textContent=localCount?'('+localCount+')':'';$('#offer-filter-summary').textContent=[!comparing&&offerView.departure?dateText(offerView.departure):'',offerView.flight?flightLabel({flight:offerView.flight}):'',offerView.room,offerView.meal?mealNames[offerView.meal]||offerView.meal:''].filter(Boolean).join(' · ')||(comparing?'Перелёт, номер и питание':'Дата, перелёт, номер и питание');$('.offer-list-context p').textContent=comparing?`${esc(state.search.origin)} → ${esc(h.resort)} · ${guestsText()}`:`${offerSearchContext()} · ${durationText()} · ${guestsText()}`;
 renderOfferRefinements(all,comparing);
 if(comparing){renderTourComparison(all,filtered);rememberUIRoute();return;}
 comparisonQuotes=[];$('#modal-footer').hidden=true;$('#modal-footer').innerHTML='';rememberUIRoute();
 $('#offer-count').textContent=offerCountText(filtered.length);
 $('#all-offers-list').innerHTML=groups.length?groups.map(({key,offers})=>{
  const first=offers[0],min=Math.min(...offers.map(o=>o.total)),open=offerView.open.includes(key),limit=offerView.limits[key]||4;
  const commonNote=sharedOfferNote(all);
  const rows=offers.slice(0,limit).map(o=>`<div class="offer grouped-offer" data-offer-key="${o.key}"><div class="offer-departure"><strong>${dateText(o.day)} → ${dateText(o.returnDay)}</strong><small>${nightsText(o.nights)}</small></div><div class="offer-flight-details"><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}${commonNote?'':`<small>${esc(offerMetaNote(o))}</small>`}</div><div class="offer-price"><strong aria-label="${esc(money(o.total))} за всех туристов">${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${o.key}">${offerActionLabel(o)} ${icon('arrow')}</button>${optionalShortlistEnabled?`<button class="text-button compare-tour-link" data-action="compare-tour" data-key="${o.key}">Сравнить на эти даты</button>`:''}</div></div>`).join('');
  return `<section class="offer-group"><button class="offer-group-heading" data-action="offer-group" data-value="${key}" aria-expanded="${open}" aria-controls="group-${key}"><span><strong>${esc(first.room)}</strong><small>${esc(mealLabel(first))}</small><small class="offer-group-scope" ${open?'hidden':''}>${[...new Set(offers.map(o=>nightsText(o.nights)))].join(' / ')} · ${[...new Set(offers.map(o=>o.day))].length===1?dateText(first.day):'Вылеты '+rangeText([...offers].sort((a,b)=>a.day.localeCompare(b.day))[0].day,[...offers].sort((a,b)=>a.day.localeCompare(b.day)).at(-1).day)}</small></span><span class="offer-group-min"><strong ${open?'hidden':''}>от ${money(min)}</strong><small>${offerCountText(offers.length)} <span class="rotate-arrow ${open?'up':''}">⌄</span></small></span></button><div id="group-${key}" class="offer-group-body" ${open?'':'hidden'}>${rows}${offers.length>limit?`<button class="text-button group-more" data-action="group-more" data-value="${key}">Ещё варианты (${offers.length-limit}) ${icon('arrow')}</button>`:''}</div></section>`;
 }).join(''):`<div class="destination-empty offer-recovery-empty"><h3>Нет такого сочетания</h3>${offerRefinementRecovery(all)}<button class="text-button" data-action="reset-offer-filters">Сбросить все условия выбора тура</button></div>`;
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
function storeSelectedTour(o,h,observedAt=Date.now()){if(o.provider==='recorded')return;savedSelection=o;savedSelectionHotel=selectedTourHotelSnapshot(h);savedSelectionObservedAt=observedAt;persistSelectedTour();}
function restoreSelectedTour(){const record=getStored(selectedTourKey,null),now=Date.now(),observedAt=Number(record?.observedAt),offer=selectedTourOfferSnapshot(record?.offer),hotel=selectedTourHotelSnapshot(record?.hotel);if(record?.version!==1||!Number.isFinite(observedAt)||observedAt>now+60000||now-observedAt>=selectedTourTTL||!offer||!hotel||offer.hotelId!==hotel.id){expireSelectedTour();return;}savedSelection=offer;savedSelectionHotel=hotel;savedSelectionObservedAt=observedAt;savedSelectionPersistent=true;scheduleSelectedTourExpiry();}
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
function saveTourForLater(){const o=selectedOffer,h=selectedTourHotel(o);if(!o||!h||o.loading||o.flightsLoading||o.pricePending||!selectedTourOfferSnapshot(o))return;storeSelectedTour(o,h);updateNav();refreshSavedTourControls(true);rememberUIRoute();}
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
 $('#modal-body').innerHTML=`${savedContext(false,false)}<div class="compare-controls"><label class="differences-toggle"><input type="checkbox" id="compare-differences" ${compareView.onlyDifferences&&visible.length>1?'checked':''} ${visible.length<2?'disabled':''}> Только отличия</label></div>${controls}<p class="compare-price-basis ${comparable?'':'compare-scope-note'}">Цены за всех${filterCount()?' по текущим фильтрам':''}. ${visible.length<2?'Добавьте ещё отель для сравнения.':comparable?'Даты, ночи и питание совпадают.':'Даты, номера и питание могут отличаться.'}</p><div class="comparison-scroll"><table class="comparison" data-count="${hs.length}"><thead><tr><th class="compare-key" scope="col">Ваш выбор</th>${entries.map(({hotel:h,offer:o})=>`<th class="compare-hotel${hide(h.id)}"><div class="compare-hotel-card">${savedHotelPhoto(h,'compare-photo')}<button class="icon-button compare-remove" data-action="toggle-compare" data-id="${h.id}" aria-label="Убрать ${esc(h.name)}">${icon('x')}</button><span class="compare-stars">${h.stars?h.stars+' ★':'Категория не указана'}</span><button class="compare-name" data-action="hotel-details" data-id="${h.id}">${esc(h.name)}</button><div class="compare-price">${o?`<strong>${money(o.total)}</strong>${comparable?`<small class="compare-price-gap">${o.total===minPrice?'Минимальная цена в сравнении':'+'+money(o.total-minPrice)+' к минимуму'}</small>`:''}<span>${cardPriceNote(o)}</span>`:'Нет туров по текущим условиям'}</div>${o?`<button class="primary" data-action="offer" data-key="${esc(o.key)}">Этот тур</button>${hotelOffers(h).length>1?`<button class="text-button compare-all-tours" data-action="all-offers" data-id="${h.id}">Все туры отеля</button>`:''}`:''}</div></th>`).join('')}</tr></thead><tbody>${row('price','Цена за всех',e=>e.offer?money(e.offer.total):'Нет предложения')}${row('resort','Курорт',e=>e.hotel.resort||'Не указан')}${row('rating','Рейтинг / 5',e=>ratingText(e.hotel))}${row('date','Вылет',e=>e.offer?dateText(e.offer.day):'—')}${row('return','Возвращение',e=>e.offer?dateText(e.offer.returnDay):'—')}${row('nights','Ночей',e=>e.offer?.nights||'—')}${row('room','Номер',e=>e.offer?.room||'—')}${row('meal','Питание',e=>e.offer?mealLabel(e.offer):'—')}${row('flight','Перелёт',e=>e.offer?flightLabel(e.offer):'—')}${row('operator','Туроператор',e=>e.offer?.operator||'—')}</tbody></table></div>`;
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
function openFilters(){if(innerWidth>1100){$('#results').classList.add('filters-requested');$('#filter-panel').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#hotel-query').focus({preventScroll:true});return}if(filterDraft)return;enterUIHistory();expandedFilterSections.clear();filterDraft=structuredClone(appliedFilterModel());renderFilters();updateDrawerPreview();$('#filter-panel').classList.add('open');$('#filter-panel').setAttribute('role','dialog');$('#filter-panel').setAttribute('aria-modal','true');$('#filter-backdrop').hidden=false;document.body.style.overflow='hidden';$('main>.search-section').inert=true;$('.header').inert=true;$('.results-heading').inert=true;$('.results-main').inert=true;$('.footer').inert=true;$('.mobile-bottom').inert=true;$('#compare-tray').inert=true;$('#compact-search').inert=true;$('#filter-panel').scrollTop=0;$('.mobile-close').focus({preventScroll:true});queueMicrotask(rememberUIRoute);}
function closeFilters({apply=false,fromHistory=false}={}){const wasOpen=$('#filter-panel').classList.contains('open');if(!wasOpen)return;if(apply){const budget=editFilterBudget();if(!budget.valid){$(budget.invalidMin?'#min-price':'#max-price').focus();return;}}leaveUIHistory(fromHistory);const next=apply?filterDraft:null;filterDraft=null;drawerSuggestions=[];$('#filter-panel').classList.remove('open');$('#filter-panel').removeAttribute('role');$('#filter-panel').removeAttribute('aria-modal');$('#filter-backdrop').hidden=true;document.body.style.overflow='';$$('[inert]').forEach(e=>e.inert=false);if(next){pageReturn=null;state.filters=next.filters;state.onlyFavorites=next.onlyFavorites;state.selectedDate=next.selectedDate;state.openHotel=null;syncFilters();const pristine=!!searchEditSession||!state.hasSearched&&!state.onlyFavorites;$(pristine?'#search':'#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$(pristine?'.search-submit':'#results').focus({preventScroll:true})}else{renderFilters();restorePageReturn();}}
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
 if(node.hidden)return;
 const failed=r.phase==='error'&&!items.length,fallback=r.databaseError&&items.length>0;
 const title=r.coverageGap?'Условия стали шире поиска':noCurrent?'Актуальные варианты пока не найдены':r.pending?(r.continued?'Продолжаем поиск':'Ищем предложения'):providerPending?'Дополняем найденные туры':fallback?'Показаны сохранённые туры':partialError?'Получены не все предложения':r.phase==='cancelled'?'Поиск остановлен':failed?'Туры не загрузились':'Поиск не завершён';
 const message=r.coverageGap?r.message:noCurrent?'Тот же тур — отель, дата, ночи, состав туристов, номер, питание, туроператор и тип перелёта — пока не найден. Другие предложения ниже показаны только как альтернативы и не заменяют сохранённый выбор.':r.pending?(r.message||'Предложения добавляются по мере получения.'):providerPending?'Часть туроператоров ещё отвечает. Уже найденные туры доступны ниже.':fallback?(r.message||'База сейчас не отвечает. Ниже показаны сохранённые предложения; цену и наличие нужно проверить.'):partialError?(r.message||'Часть предложений сейчас недоступна. Уже найденные туры сохранены; можно выбрать их или повторить поиск.'):r.message||'Поиск можно повторить с выбранными условиями.';
 node.innerHTML=`<div class="search-status-top"><div><h3>${r.pending||providerPending?'<span class="search-progress-spinner" aria-hidden="true"></span>':icon('info')}${title}</h3><p>${esc(message)}</p></div></div><div class="search-status-actions">${r.pending||providerPending?'<button class="secondary" data-action="stop-search">Остановить поиск</button>':partialError||!r.canContinue?'<button class="primary" data-action="retry-search">Повторить поиск</button>':''}<button class="text-button" data-action="edit-search">Изменить условия</button></div>`;
 if(!items.length&&r.pending)$('#cards').innerHTML=Array.from({length:2},()=>'<div class="search-skeleton" aria-hidden="true"><div class="skeleton-photo"></div><div class="skeleton-lines"><i></i><i></i><i></i></div></div>').join('');
}

function prepareSearchRun(options={}){
 if(state.filters.hotelId&&!data.preview&&!destinationHotel(state.filters.hotelId)?.legacyIds.length){state.hasSearched=false;editSearch();renderResults();updateSearchUI();return null;}
 resultCalendar.controller?.abort();resultCalendar={key:null,hotels:[],observations:[],phase:'idle',controller:null};
 selectionGeneration++;selectedOffer=null;andromedaQuoteDraft=null;anexCurrentDraft=null;demoteSavedTour();removedSelectedTour=null;removedSelectedTourHotel=null;removedSelectedTourObservedAt=0;window.AnyTourPrototypeLead.reset();updateNav();state.hasSearched=true;
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
}
function commitSearchDraft(){
 if(searchEditSession&&currentFilterBudgetEdit(state.filters)){
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
document.addEventListener('change',e=>{const t=e.target;
 if(t.id==='hotel-room-meal')renderHotelRooms(+t.dataset.id,t.value);
 if(t.name==='flight-pair'&&flightDraft){flightDraft.id=t.value;updateFlightPreview();}
 if(t.name==='comparison-focus'){selectComparisonVariant(+t.value);}
 if(t.id==='tour-differences-only'){offerView.differencesOnly=t.checked;refreshTourComparison(t.id);}
 if(t.id==='comparison-pair-0'||t.id==='comparison-pair-1'){const side=t.id==='comparison-pair-0'?0:1,other=1-side,old=offerView.pair[side],variant=+t.value;offerView.pair[side]=variant;if(offerView.pair[other]===variant)offerView.pair[other]=old;if(!offerView.pair.includes(offerView.activeVariant))offerView.activeVariant=variant;refreshTourComparison(t.id);}
 if(t.id==='origin'){draft.origin=t.value;loadCountries(t.value);}
 if(t.id==='compare-differences'){compareView.onlyDifferences=t.checked;refreshSavedView('compare','#compare-differences')}
 if(t.id==='compare-left'||t.id==='compare-right'){const side=t.id==='compare-left'?0:1,other=1-side,old=compareView.pair[side],id=+t.value;compareView.pair[side]=id;if(compareView.pair[other]===id)compareView.pair[other]=old;refreshSavedView('compare','#'+t.id)}
 if(['offer-departure','offer-flight','offer-room','offer-meal','offer-sort'].includes(t.id)){offerView[t.id.replace('offer-','')]=t.value;renderOfferList(true)}
 if(t.id==='compare-offer-day'||t.id==='compare-offer-nights'){const scroll=$('#modal-body').scrollTop;if(t.id==='compare-offer-day')offerView.day=t.value;else offerView.nights=+t.value;renderOfferList();$('#'+t.id)?.focus({preventScroll:true});$('#modal-body').scrollTop=scroll;}
 if(t.id==='sort'||t.id==='mobile-sort'){if(!['recommended','price','rating'].includes(t.value))return;state.sort=t.value;renderResults({keepFilters:true});if(t.id==='mobile-sort'){$('#cards').scrollIntoView({behavior:scrollBehavior(),block:'start'});toast(t.options[t.selectedIndex].textContent)}}
 if(t.id==='filter-section-jump'){jumpToFilterSection(t.value);return;}
 if(t.dataset.filter){const arr=editingFilterModel().filters[t.dataset.filter],idx=arr.indexOf(t.value);if(t.checked&&idx<0)arr.push(t.value);if(!t.checked&&idx>=0)arr.splice(idx,1);filterEdited()}
 if(t.id==='filter-section-jump'){jumpToFilterSection(t.value);return;}
 if(t.dataset.filter){const host=t.closest('.facet-options');if(host)applyFacetSearch(host);}
 if(t.dataset.filterBool){editingFilterModel().filters[t.dataset.filterBool]=t.checked;filterEdited()}
 if(['min-price','max-price'].includes(t.id))editFilterBudget();
 if(t.dataset.childAge!==undefined){guestDraft.ages[+t.dataset.childAge]=t.value===''?null:+t.value;if(t.value!==''){t.removeAttribute('aria-invalid');t.removeAttribute('aria-describedby');}if(guestDraft.ages.every(a=>a!==null))$('#guest-error').textContent='';updateGuestSelection();}
 if(t.hasAttribute('data-meal-choice')){if(!t.value)mealDraft=[];else{mealDraft=t.checked?[...new Set([...mealDraft,t.value])]:mealDraft.filter(v=>v!==t.value)}$$('[data-meal-choice]').forEach(c=>c.checked=c.value?mealDraft.includes(c.value):!mealDraft.length);updateMealPicker()}
});
document.addEventListener('input',e=>{if(['min-price','max-price'].includes(e.target.id))editFilterBudget();if(e.target.id==='budget-min'||e.target.id==='budget-max')updateBudgetPreview();if(e.target.id==='meal-query')updateMealPicker();if(e.target.id==='destination-query')lookupDestination();if(e.target.id==='hotel-query'){editingFilterModel().filters.q=e.target.value;$('.clear-hotel-query').hidden=!e.target.value;filterEdited()}if(e.target.id==='price-range'){const f=editingFilterModel().filters;f.max=Number(e.target.value)>=Number(e.target.max)?null:Number(e.target.value);if(f.max!==null)f.max=Math.max(f.min,f.max);syncBudgetControls(f);filterEdited()}});
document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(!b||b.disabled)return;actionTrigger=b;queueMicrotask(()=>{if(actionTrigger===b)actionTrigger=null;});const action=b.dataset.action,id=+b.dataset.id;
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
 case 'destination-hotel':{const h=destinationHotel(id);if(!h)return;cancelDestinationLookup();destinationChoice={country:h.country,resorts:[],hotelId:h.id};$('#destination-query').value='';$('#destination-query').blur();renderDestination();$('#modal-body').scrollTop=0;$('[data-action="apply-destination"]').focus({preventScroll:true});break}
 case 'destination-recent':cancelDestinationLookup();destinationResortsExpanded=false;destinationChoice=structuredClone(recentDestinations()[+b.dataset.value]);$('#destination-query').value='';renderDestination();break;
 case 'apply-destination':draftDestination=structuredClone(destinationChoice);draft.country=destinationChoice.country;closeModal();updateSearchUI();if(!state.hasSearched)renderResults();break;
 case 'retry-hotel-restore':restoreURLHotel();break;
 case 'dates':openDates();break;case 'meals':openMeals();break;case 'budget':openBudget();break;
 case 'category-filters':{openFilters();const heading=$('#filters .star-options')?.closest('.filter-group')?.querySelector('h4');if(heading)jumpToFilterSection(heading.id);break;}
 case 'toggle-filter-section':{const group=b.closest('.filter-group');setFilterSectionOpen(group,b.getAttribute('aria-expanded')!=='true');break;}
 case 'clear-facet-query':{const host=b.closest('.facet-options'),input=host.querySelector('[data-facet-search]');facetQueries.delete(host.dataset.facetOptions);input.value='';applyFacetSearch(host);input.focus({preventScroll:true});break;}
 case 'remove-facet-choice':{const host=b.closest('.facet-options'),group=host.dataset.facetOptions,f=editingFilterModel().filters;f[group]=f[group].filter(value=>value!==b.dataset.value);host.querySelectorAll('[data-filter]').forEach(input=>input.checked=f[group].includes(input.value));filterEdited();applyFacetSearch(host);host.querySelector('[data-facet-search]').focus({preventScroll:true});break;}
 case 'clear-hotel-query':editingFilterModel().filters.q='';$('#hotel-query').value='';$('.clear-hotel-query').hidden=true;filterEdited();$('#hotel-query').focus({preventScroll:true});break;
 case 'clear-meal-query':$('#meal-query').value='';updateMealPicker();$('#meal-query').focus();break;
 case 'apply-meals':state.filters.meals=[...mealDraft];applyQuickFilters();break;
 case 'budget-preset':$('#budget-min').value=0;$('#budget-max').value=b.dataset.value;updateBudgetPreview();break;
 case 'budget-adjust-max':$('#budget-max').value=b.dataset.value;updateBudgetPreview();$('[data-action="apply-budget"]').focus({preventScroll:true});break;
 case 'apply-budget':{const budget=readBudget();if(!budget.valid){updateBudgetPreview();$(budget.invalidMin?'#budget-min':'#budget-max').focus();return}filterBudgetEdit=null;state.filters.min=budget.min;state.filters.max=budget.max;applyQuickFilters();break}
 case 'any-stars':state.filters.stars=[];syncFilters();break;case 'nights':openNights();break;case 'guests':openGuests();break;case 'toggle-price-preview':{const expanded=b.getAttribute('aria-expanded')!=='true';b.setAttribute('aria-expanded',String(expanded));$('#price-calendar').classList.toggle('preview-expanded',expanded);$('.calendar-preview-label').textContent=expanded?'Скрыть':'Показать';break;}case 'calendar':openCalendar();break;
 case 'modal-back':modalBack();break;case 'close-modal':closeModal();break;case 'filters':openFilters();break;case 'close-filters':closeFilters();break;case 'apply-filters':closeFilters({apply:true});break;case 'review-filter-recovery':if(filterDraft)jumpToFilterSection('drawer-recovery');break;
 case 'top':case 'edit-search':editSearch();break;
 case 'cancel-search-edit':cancelSearchEdit();break;
 case 'hotel-details':openHotelDetails(id);break;
 case 'change-room':{const previous=modalHistory.at(-1);if(['hotel-details','all-offers'].includes(previous?.type))modalBack();else if(selectedOffer)openAllOffers(selectedOffer.hotelId);break;}
 case 'hotel-gallery':openGallery(id,+b.dataset.value);break;
 case 'hotel-room-offers':openAllOffers(id);offerView.room=b.dataset.room;offerView.meal=b.dataset.meal||'';renderOfferList(true);break;
 case 'hotel-detail-offers':openAllOffers(id);offerView.meal=b.dataset.meal||'';renderOfferList(true);break;
 case 'hotel-section':{const body=$('#modal-body'),target=body.querySelector('#'+CSS.escape(b.dataset.target));if(target){target.focus({preventScroll:true});const top=target.getBoundingClientRect().top-body.getBoundingClientRect().top+body.scrollTop-($('.hotel-section-nav')?.offsetHeight||0)-16;body.scrollTo({top:Math.max(0,top),behavior:scrollBehavior()});}break;}
 case 'clear-compare':state.compare=[];saveStored('anytour.prototype.v18.compare.v1',[]);updateNav();renderResults({keepFilters:true});break;
 case 'card-photo-index':case 'card-photo':{const h=hotels.find(h=>h.id===id);if(!h?.photos.length)break;const idx=action==='card-photo-index'?+b.dataset.value:((state.photoIndexes[id]||0)+(+b.dataset.dir)+h.photos.length)%h.photos.length;state.photoIndexes[id]=idx;$('#hotel-'+id+' .hotel-image').src=photoUrl(h,idx);$('#hotel-'+id+' .photo-index').textContent=idx+1;$$('#hotel-'+id+' .card-thumb').forEach((el,i)=>{el.classList.toggle('active',i===idx);el.setAttribute('aria-pressed',i===idx)});break;}
 case 'reset':if(filterDraft&&b.closest('#filter-panel')){filterDraft={filters:defaultFilters(),onlyFavorites:false,selectedDate:null};filterEdited(true)}else resetFilters();break;
 case 'all-hotels':state.onlyFavorites=false;renderResults();break;
 case 'star':{const n=+b.dataset.value,a=(b.closest('#filter-panel')?editingFilterModel().filters:state.filters).stars,i=a.indexOf(n);if(i<0)a.push(n);else a.splice(i,1);filterEdited(true);if(filterDraft)$(`#filter-panel [data-action="star"][data-value="${n}"]`)?.focus({preventScroll:true});break}
 case 'preset':state.filters[b.dataset.preset]=!state.filters[b.dataset.preset];syncFilters();break;
 case 'remove-filter':{const model=appliedFilterModel();removeModelFilter(model,b.dataset.key,b.dataset.value);state.onlyFavorites=model.onlyFavorites;state.selectedDate=model.selectedDate;syncFilters();break}
 case 'remove-draft-filter':if(filterDraft){removeModelFilter(filterDraft,b.dataset.key,b.dataset.value);filterEdited(true);$('#filter-panel .mobile-close').focus({preventScroll:true})}break;
 case 'recover-filters':{const choice=(b.dataset.source==='drawer'?drawerSuggestions:emptySuggestions)[+b.dataset.value];if(!choice)break;if(b.dataset.source==='drawer'&&filterDraft){filterDraft=structuredClone(choice.model);filterEdited(true);$('#apply-filters').focus({preventScroll:true})}else{state.filters=structuredClone(choice.model.filters);state.onlyFavorites=choice.model.onlyFavorites;state.selectedDate=choice.model.selectedDate;state.openHotel=null;syncFilters();$('#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#results').focus({preventScroll:true})}break;}

 case 'clear-date':state.selectedDate=null;renderResults({keepFilters:true});updateSearchUI();break;
 case 'select-date':selectDate(b.dataset.date);break;
 case 'month-prev':case 'month-next':{const d=dateObj(calendarMonth);d.setUTCMonth(d.getUTCMonth()+(action==='month-next'?1:-1));calendarMonth=iso(d);renderDateCalendar();loadCalendarPrices();break}
 case 'day-pick':{const day=b.dataset.date;if(dateDraft.phase===0){dateDraft.from=day;dateDraft.to=day;dateDraft.phase=1}else{const range={from:day<dateDraft.from?day:dateDraft.from,to:day<dateDraft.from?dateDraft.from:day};if(dateRangeError(range)){$('#date-error').textContent='Между датами — не больше 21 дня. Выберите вторую дату ближе к первой.';break;}dateDraft.from=range.from;dateDraft.to=range.to;dateDraft.phase=0}updateDateSelection();break}
 case 'apply-dates':{const {from,to}=dateDraft;if(!from||!to||from<startDay||to>endDay||from>to||(dateObj(to)-dateObj(from))/86400000>21){$('#date-error').textContent='Выберите корректный диапазон не больше 21 дня между датами.';return}const refreshResults=dateContext.source==='results'&&(from!==dateContext.search.from||to!==dateContext.search.to);draft.from=from;draft.to=to;if(dateContext.source==='results'){state.search.from=from;state.search.to=to;state.selectedDate=from===to?from:null;state.openHotel=null;renderResults({keepFilters:true})}else if(state.selectedDate){state.selectedDate=null;renderResults({keepFilters:true})}closeModal();updateSearchUI();if(refreshResults)searchLifecycle.requestSubmit();break}
 case 'adults-minus':guestDraft.adults=Math.max(1,guestDraft.adults-1);renderGuests();break;
 case 'adults-plus':guestDraft.adults=Math.min(6,guestDraft.adults+1);renderGuests();break;
 case 'children-minus':guestDraft.ages.pop();renderGuests();break;
 case 'children-plus':if(guestDraft.ages.length<3)guestDraft.ages.push(null);renderGuests();break;
 case 'remove-child':{const index=Number(b.dataset.index);if(!Number.isInteger(index)||index<0||index>=guestDraft.ages.length)break;guestDraft.ages.splice(index,1);renderGuests();const target=$('[data-child-age="'+Math.min(index,guestDraft.ages.length-1)+'"]')||$('[data-action="children-plus"]');target.focus();break;}
 case 'apply-guests':if(guestDraft.ages.some(a=>a===null)){$('#guest-error').textContent='Укажите возраст каждого ребёнка.';$$('[data-child-age]').forEach(el=>{if(el.value===''){el.setAttribute('aria-invalid','true');el.setAttribute('aria-describedby','guest-error');}});$('[data-child-age][aria-invalid="true"]').focus();return}draft.adults=guestDraft.adults;draft.ages=[...guestDraft.ages];closeModal();updateSearchUI();break;
 case 'night-pick':{const n=+b.dataset.value;if(nightsDraft.phase===1&&Math.abs(n-nightsDraft.min)>10){nightsDraft.error=`Слишком широкий диапазон. Выберите вторую границу от ${Math.max(1,nightsDraft.min-10)} до ${Math.min(28,nightsDraft.min+10)} ночей.`;renderNightSelection();break;}nightsDraft.error='';if(nightsDraft.phase===0){nightsDraft.min=n;nightsDraft.max=n;nightsDraft.phase=1}else{nightsDraft.max=Math.max(nightsDraft.min,n);nightsDraft.min=Math.min(nightsDraft.min,n);nightsDraft.phase=0}renderNightSelection();break}
 case 'night-preset':nightsDraft={min:+b.dataset.value,max:+b.dataset.value,phase:0};renderNightSelection();break;
 case 'apply-nights':draft.minNights=nightsDraft.min;draft.maxNights=nightsDraft.max;closeModal();updateSearchUI();break;
 case 'toggle-offers':{state.openHotel=state.openHotel===id?null:id;const focusId=id;renderResults({keepFilters:true});const target=$(`[data-action="toggle-offers"][data-id="${focusId}"]`);target?.focus({preventScroll:true});if(state.openHotel)$('#offers-'+id).scrollIntoView({behavior:scrollBehavior(),block:'nearest'});break}
 case 'all-offers':openAllOffers(id);break;
 case 'offer-view':if(optionalShortlistEnabled&&offerView&&['list','compare'].includes(b.dataset.value)){offerView.mode=b.dataset.value;renderOfferList();b.focus({preventScroll:true});}break;
 case 'compare-tour':{if(!optionalShortlistEnabled)break;const o=offerFromKey(b.dataset.key);if(o&&offerView?.id===o.hotelId){offerView.mode='compare';offerView.day=o.day;offerView.nights=o.nights;offerView.activeVariant=o.variant;offerView.pair=[];renderOfferList();$('#modal-body').scrollTop=0;($('.comparison-date-disclosure>summary')||$('.comparison-fixed-dates'))?.focus({preventScroll:true});}break;}
 case 'offer-flights':openOffer(b.dataset.key,null,true);break;
 case 'offer-group':{const key=b.dataset.value;offerView.open=offerView.open.includes(key)?offerView.open.filter(x=>x!==key):[...offerView.open,key];renderOfferList();$(`[data-action="offer-group"][data-value="${key}"]`).focus({preventScroll:true});break}
 case 'group-more':{const key=b.dataset.value,body=$('#modal-body'),scroll=body.scrollTop,shown=b.parentElement.querySelectorAll('.grouped-offer').length;offerView.limits[key]=(offerView.limits[key]||4)+8;renderOfferList();body.scrollTop=scroll;const firstNew=document.getElementById('group-'+key)?.querySelectorAll('.grouped-offer')[shown];firstNew?.querySelector('[data-action="offer"]')?.focus({preventScroll:true});firstNew?.scrollIntoView({behavior:scrollBehavior(),block:'nearest'});break;}
 case 'remove-offer-filter':if(offerRefinementFields.includes(b.dataset.field)&&offerView){offerView[b.dataset.field]='';renderOfferList(true);$('#offer-count').focus();}break;
 case 'reset-offer-filters':offerView.departure='';offerView.flight='';offerView.room='';offerView.meal='';renderOfferList(true);break;
 case 'more-offers':{const h=hotels.find(h=>h.id===id),offers=hotelOffers(h),off=+b.dataset.offset;$('#all-offers-list').insertAdjacentHTML('beforeend',offers.slice(off,off+30).map(o=>offerHTML(h,o)).join(''));if(off+30>=offers.length)b.remove();else b.dataset.offset=off+30;break}
 case 'offer':openOffer(b.dataset.key);break;case 'confirm-tour':confirmTour();break;case 'apply-andromeda-flights':applyAndromedaFlightChoice();break;case 'anex-additional-prices':applyAnexAdditionalPrices();break;case 'accept-price':if(verifiedOffer){const accepted=verifiedOffer;verifiedOffer=null;completeTour(accepted);}break;
 case 'gallery':openGallery(id,state.photoIndexes[id]||0);break;case 'gallery-next':case 'gallery-prev':{const count=hotels.find(h=>h.id===gallery.id).photos.length;gallery.index=(gallery.index+(action==='gallery-next'?1:-1)+count)%count;renderGallery();break}
 case 'gallery-index':gallery.index=+b.dataset.value;renderGallery();break;
 case 'favorite':toggleFavorite(id);break;case 'favorites':openFavorites();break;
 case 'toggle-compare':toggleCompare(id);break;case 'compare':openCompare();break;
 case 'only-favorites':state.onlyFavorites=true;closeModal();renderResults();$('#results').scrollIntoView({behavior:scrollBehavior()});break;
 case 'show-hotel':{const h=hotels.find(h=>h.id===id);state.search.country=h.country;draft=structuredClone(state.search);draftDestination=null;state.filters=defaultFilters();state.filters.hotelId=h.id;state.onlyFavorites=false;state.selectedDate=null;state.openHotel=id;closeModal();syncFilters();updateURL();$('#results').scrollIntoView({behavior:scrollBehavior()});break}
 case 'operator-info':toast('Туроператор: '+b.dataset.operator);break;
 case 'about':showModal('about','Версия для проверки','ANYTOUR SEARCH3',`<p class="modal-intro">Прототип v${prototypeVersion} подключён к базе туров и календарю AnyTour через доступ только для чтения. Если база временно недоступна, совместимый поиск может показать сохранённые реальные предложения от 23 сентября с явным предупреждением. Проверка цены и наличия выбранного предложения в этом прототипе пока не подключена. Демонстрационные сценарии сохранены. Большая выдача — сохранённый пример: 1047 отелей и 1453 видимых предложения из 1718 найденных. Сценарии рейсов — вымышленные. Цены не обновляются; заявки и оплата отключены.</p>`);break;
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
window.addEventListener('resize',()=>{if(innerWidth>1100&&$('#filter-panel').classList.contains('open'))closeFilters();if(modalType==='dates'&&calendarMobile!==(innerWidth<=760)){renderDateCalendar();loadCalendarPrices();}});

function refreshCalendarPrices(){
 refreshCalendarPriceCache();
 $$('#date-calendar .calendar-month').forEach(month=>{
  const cells=[...month.querySelectorAll('.month-day:not([disabled])')],prices=cells.map(b=>calendarPrice(b.dataset.date)),min=Math.min(...prices.filter(p=>p!==null));
  cells.forEach((b,i)=>{const price=prices[i];b.classList.toggle('is-cheap',price!==null&&price===min);b.querySelector('small').textContent=price===null?'—':(price/1000).toLocaleString('ru-RU',{maximumFractionDigits:1});b.setAttribute('aria-label',dateLong(b.dataset.date)+(price===null?', цена пока неизвестна':', от '+money(price)));});
 });
 renderDateSelectionPrice();
}
function loadCalendarPrices(){
 calendarRequest?.abort();calendarObserver?.disconnect();calendarRequest=new AbortController();const controller=calendarRequest,ctx=dateContext,loads=new Map();
 calendarHotels=[];calendarObservations=[];const snapshots=new Map();refreshCalendarPrices();
 const legend=()=>{if(controller.signal.aborted||dateContext!==ctx||modalType!=='dates')return;const phases=[...loads.values()],node=$('.calendar-legend'),loading=phases.includes('loading');$('.date-choice-tools').setAttribute('aria-busy',String(loading));$('.calendar-price-key>span').textContent=loading?'Открываем цены…':'Весь тур · тыс. ₽';node.hidden=!phases.includes('error');node.querySelector('span').textContent=phases.includes('error')?'Не все цены загрузились. Даты можно выбрать без цены.':'';};
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
async function loadCountries(origin){catalogReady=false;$('.search-submit').disabled=true;try{const c=await data.countries(origin);if(!c)return;applyCatalog(c);await loadResorts(draft.country);catalogReady=true;updateSearchUI();}catch(error){toast(error.message);}}
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
 catalogReady=false;$('.search-submit').disabled=true;catalogError='';if(modalType==='destination')renderDestination();
 try{applyCatalog(await data.init(new URLSearchParams(location.search).get('origin')||draft.origin));state.search=structuredClone(draft);restoreURL();await loadResorts(state.search.country);catalogReady=true;updateSearchUI();renderResults();if(modalType==='destination'){if(!countryNames[destinationChoice.country])destinationChoice=structuredClone(currentDraftDestination());lookupDestination();}if(modalType==='dates'){dateContext=createDateContext(dateContext?.source==='results'?'results':'form');renderCalendarScope();loadCalendarPrices();}await restoreURLHotel();if(!data.live)await restoreSavedHotels();$('#fixture-description').textContent=data.describe();if(data.live){state.hasSearched=false;renderFilters();renderResults();updateSearchUI();}else{state.hasSearched=true;runSearch();}}
 catch(error){catalogError=error.message;$('#cards').innerHTML=`<div class="empty"><h3>Не удалось загрузить направления</h3><p>${esc(error.message)}</p><button class="primary" data-action="retry-catalog">Повторить</button></div>`;if(modalType==='destination')renderDestination();}
}
document.addEventListener('click',event=>{const b=event.target.closest('[data-action]');if(!b||b.disabled)return;if(b.dataset.action==='refresh-hotel')refreshHotel(Number(b.dataset.id));if(b.dataset.action==='retry-flights')loadRealFlights();if(b.dataset.action==='retry-catalog')bootRealData();});
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
hydrate();bootRealData();
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
