'use strict';
(() => {
const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
const paths = {
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
const rangeText = (a,b)=>a===b?dateText(a):`${dateText(a)} — ${dateText(b)}`;
const nightsText = n=>`${n} ${n%10===1&&n!==11?'ночь':n%10>=2&&n%10<=4&&(n<12||n>14)?'ночи':'ночей'}`;
const countryNames={};
const mealNames={};
const amenityNames=new Map();
const countriesTo={};
const operators=[];
const data=window.AnyTourPrototypeData;
const flightLabel=o=>o.flight==='regular'?'Регулярный':o.flight==='charter'?'Чартер':'Тип рейса уточняется';
const needsRefresh=o=>o.cached||o.provider!=='tourvisor'||o.raw?.selectionEnabled===false;
const offerActionLabel=o=>needsRefresh(o)?'Смотреть условия':'Выбрать тур';
const priceNote=o=>needsRefresh(o)?'Сохранённая цена · требует проверки':'Цена предложения · сборы уточняются';
const mealLabel=o=>o.meal||'Питание уточняется';
const photoUrl=(h,i=0)=>h.photos[i]||'./assets/photo-unavailable.svg';
const operatorAssets={'ANEX':'anex.svg','Coral Travel':'coral-travel.png','FUN&SUN':'fun-sun.svg','Библио-Глобус':'biblio-globus.svg'};
function operatorBadge(name){const file=operatorAssets[name],label='Туроператор: '+name;return `<button type="button" class="operator-logo ${file?'':'operator-text'}" data-action="operator-info" data-operator="${esc(name)}" aria-label="${esc(label)}" title="${esc(label)}">${file?`<img src="./assets/operators/${file}" alt="">`:esc(name)}<span class="operator-tooltip" aria-hidden="true">${esc(label)}</span></button>`;}
const arrivalCity=h=>data.text(h.raw?.arrival)||h.resort||countryNames[h.country];
function minimumOfferSummary(o){return `<div class="card-minimum-offer" data-minimum-key="${esc(o.key)}"><strong>${dateText(o.day)} → ${dateText(o.returnDay)} · ${nightsText(o.nights)}</strong><p class="minimum-stay"><span>${esc(mealLabel(o))}</span><span>${esc(o.room)}</span></p><div class="card-offer-flight"><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}</div></div>`;}
const startDay=addDays(iso(new Date()),1),endDay=addDays(startDay,180);
let hotels=[];
let resultCalendar={key:null,hotels:[],observations:[],phase:'idle',controller:null};

const defaultFilters=()=>({hotelId:0,q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false});
const getStored=(key,fallback)=>{try{return JSON.parse(localStorage.getItem(key))??fallback}catch{return fallback}};
const saveStored=(key,value)=>{try{localStorage.setItem(key,JSON.stringify(value))}catch{toast('Сохранение в браузере недоступно; выбор останется до перезагрузки.')}};
const clearStored=key=>{try{localStorage.removeItem(key)}catch{}};
const validIds=ids=>Array.isArray(ids)?[...new Set(ids.filter(id=>Number.isSafeInteger(id)&&id>0))].slice(0,20):[];
const state={search:{origin:'Москва',country:'',from:addDays(startDay,7),to:addDays(startDay,13),minNights:7,maxNights:7,adults:2,ages:[]},filters:defaultFilters(),selectedDate:null,sort:'recommended',openHotel:null,favorites:[],compare:[],onlyFavorites:false,hasSearched:false,photoIndexes:{}};
const modalHistory=[];let restoringModal=false;
let draftDestination=null,destinationChoice=null,draft=structuredClone(state.search),modalType='',guestDraft={},nightsDraft={},dateDraft={},calendarMonth=startDay.slice(0,7)+'-01',gallery={id:null,index:0},selectedOffer=null,searchTimer=null,searchStageTimer=null;
let searchResponse={key:'',phase:'complete',operators:[...operators],pending:false};
const searchKey=s=>JSON.stringify([s.origin,s.country,s.from,s.to,s.minNights,s.maxNights,s.adults,s.ages]);
const responseFor=s=>searchResponse.key===searchKey(s)?searchResponse:{phase:'complete',operators,pending:false};
const ratingValue=h=>h.rating;
const ratingText=h=>h.rating===null?'—':ratingValue(h).toLocaleString('ru-RU',{minimumFractionDigits:1,maximumFractionDigits:1});
const guestsText=(s=state.search)=>`${s.adults} взр.${s.ages.length?' + '+s.ages.length+' реб.':''}`;
const durationText=(s=state.search)=>s.minNights===s.maxNights?nightsText(s.minNights):`${s.minNights}–${s.maxNights} ночей`;
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
 state.filters.stars=(p.get('stars')||'').split('|').map(Number).filter(n=>[3,4,5].includes(n));
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
function updateURL(){const p=new URLSearchParams();for(const [k,v] of Object.entries(state.search))p.set(k,Array.isArray(v)?v.join(','):v);if(state.selectedDate)p.set('date',state.selectedDate);if(state.hasSearched)p.set('searched','1');if(state.onlyFavorites)p.set('favorites','1');const f=state.filters;for(const k of ['stars','meals','resorts','operators','flight','amenities'])if(f[k].length)p.set(k,f[k].join('|'));for(const k of ['beach','rating','family','spa'])if(f[k])p.set(k,'1');if(f.hotelId)p.set('hotel',f.hotelId);if(f.q)p.set('q',f.q);if(f.min)p.set('min',f.min);if(f.max!==null)p.set('max',f.max);if(state.sort!=='recommended')p.set('sort',state.sort);const query='?'+p.toString();if(location.search!==query||location.hash)history.replaceState(history.state,'',query);}
function renderSummary(){const s=state.search,f=state.filters;$('#applied-search').innerHTML=`<div class="applied-route"><span class="summary-icon">${icon('plane')}</span><div><strong>${esc(s.origin)}<span>→</span>${esc(destinationLabel(appliedDestination()))}</strong><p>${state.selectedDate?dateText(state.selectedDate):rangeText(s.from,s.to)} · ${durationText()} · ${guestsText()}</p></div></div><div class="applied-extras"><span>${f.stars.length?f.stars.join(' / ')+' ★':'Любая категория'}</span><span>${f.meals.length?f.meals.map(esc).join(', '):'Любое питание'}</span><span>${budgetText()}</span></div><button class="secondary" data-action="edit-search">${icon('sliders')} Изменить поиск</button>`;}
function collapseSearch(){renderSummary();$('#search-form').hidden=true;$('.intro').hidden=true;$('#applied-search').hidden=false;$('#search').classList.add('search-collapsed');}
function editSearch(){if(searchResponse.pending)stopSearch();if(innerWidth<=1100)closeFilters();$('#search-form').hidden=false;$('.intro').hidden=false;$('#applied-search').hidden=true;$('#search').classList.remove('search-collapsed');$('#search').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#origin').focus({preventScroll:true});}
const hotelPlaces=h=>[...new Set([h.subRegion,h.region,h.resort].map(value=>String(value||'').trim()).filter(Boolean))];
function hotelMatch(h,f=state.filters,s=state.search,onlyFavorites=state.onlyFavorites){
 const q=f.q.toLowerCase().trim(),places=[...new Set([h.subRegion,h.region,h.resort].map(value=>String(value||'').trim()).filter(Boolean))];
 return h.country===s.country&&(!f.hotelId||h.id===f.hotelId)
  &&(!q||h.name.toLowerCase().includes(q)||places.some(place=>place.toLowerCase().includes(q)))
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
 return (h.offers||[]).filter(o=>o.search.origin===s.origin&&o.search.country===s.country&&o.adults===s.adults&&JSON.stringify([...o.ages].sort())===JSON.stringify([...s.ages].sort())&&o.day>=from&&o.day<=to&&o.nights>=s.minNights&&o.nights<=s.maxNights&&(!f.meals.length||f.meals.includes(o.meal))&&o.total>=f.min&&(f.max===null||o.total<=f.max)&&(!f.operators.length||f.operators.includes(o.operator))&&(!f.flight.length||f.flight.includes(o.flight))).sort((a,b)=>a.total-b.total||a.day.localeCompare(b.day));
}
function results(){return hotels.map(h=>({hotel:h,offers:hotelOffers(h)})).filter(r=>r.offers.length).sort((a,b)=>state.sort==='price'?a.offers[0].total-b.offers[0].total:state.sort==='rating'?b.hotel.rating-a.hotel.rating:(b.hotel.rating+(b.hotel.beach!==null&&b.hotel.beach<=150?.2:0))-(a.hotel.rating+(a.hotel.beach!==null&&a.hotel.beach<=150?.2:0)));}
function minimumForDay(day,options={}){let min=Infinity;const source=options.calendarHotels||hotels;source.forEach(h=>{const offers=hotelOffers(h,{...options,day,ignoreDate:true,onlyFavorites:false});if(offers.length)min=Math.min(min,offers[0].total)});return Number.isFinite(min)?min:null;}
function calendarMinimum(day,options,observations=[]){const actual=minimumForDay(day,options),s=options.search||state.search,f=options.filters||state.filters,saved=data.observationScopeSupported(s,f)?observations.find(point=>point.date===day)?.price:null,values=[actual,saved].filter(value=>Number.isFinite(value)&&value>0);return values.length?Math.min(...values):null;}
function filterCount(){return Object.entries(state.filters).reduce((n,[k,v])=>n+(Array.isArray(v)?v.length:k==='max'?(v!==null?1:0):k==='min'?(v>0?1:0):v?1:0),0);}
function toast(msg){const t=$('#toast');t.textContent=msg;t.hidden=false;clearTimeout(toast.timer);toast.timer=setTimeout(()=>t.hidden=true,3600);}
const draftSelectedDate=()=>draft.from===state.search.from&&draft.to===state.search.to?state.selectedDate:null;
function updateSearchUI(){
 $('#origin').value=draft.origin;const place=currentDraftDestination();$('#destination-label').textContent=destinationLabel(place);$('#country').title=destinationLabel(place,true);$('#country').setAttribute('aria-label','Направление: '+destinationLabel(place,true));
 $('#dates-label').textContent=draftSelectedDate()?dateText(draftSelectedDate()):rangeText(draft.from,draft.to);$('#nights-label').textContent=durationText(draft);$('#guests-label').textContent=guestsText(draft);
 $$('#quick-stars button').forEach(b=>{const active=b.dataset.action==='any-stars'?!state.filters.stars.length:state.filters.stars.includes(+b.dataset.value);b.setAttribute('aria-pressed',active);b.classList.toggle('active',active)});
 const meals=state.filters.meals.join(' · ');$('#meal-label').textContent=state.filters.meals.length>1?state.filters.meals.length+' варианта':meals||'Любое';$('#quick-meal').setAttribute('aria-label','Питание: '+(meals||'любое'));$('#quick-meal').title=meals||'Любое питание';
 $('#budget-label').textContent=budgetText();
 $('.search-submit').disabled=!catalogReady||!!(place.hotelId&&!destinationHotel(place.hotelId)?.legacyIds.length);
}
const normalizeSearch=s=>String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/ё/g,'е').trim();
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
 cancelDestinationLookup();const q=$('#destination-query').value.trim(),country=destinationChoice.country;
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
async function loadResorts(country){
 if(!country||!countryNames[country])return;
 resortLoads.set(country,'loading');
 try{await data.regions(country);resortLoads.set(country,'complete');}
 catch{resortLoads.set(country,'error');}
 if(modalType==='destination'&&destinationChoice?.country===country)renderDestination();
}
function currentDraftDestination(){return draftDestination||{country:draft.country,resorts:draft.country===state.search.country?[...state.filters.resorts]:[],hotelId:draft.country===state.search.country?state.filters.hotelId:0};}
function destinationLabel(d,full=false){const h=destinationHotel(d.hotelId);if(h)return full?`${h.name}, ${h.resort}, ${countryNames[h.country]||''}`:h.name;if(d.hotelId)return hotelRestorePending?'Восстанавливаем отель…':'Выбранный отель недоступен';if(d.resorts.length)return full?`${countryNames[d.country]||''} · ${d.resorts.join(', ')}`:d.resorts.length===1?d.resorts[0]:`${countryNames[d.country]||''} · ${d.resorts.length} ${d.resorts.length<5?'курорта':'курортов'}`;return countryNames[d.country];}
function recentDestinations(){const v=getStored('anytour.real.destinations.v1',[]);return (Array.isArray(v)?v:[]).filter(d=>d&&countryNames[d.country]&&Array.isArray(d.resorts)&&d.resorts.every(r=>(data.catalog.regions[d.country]||[]).some(row=>row.name===r))&&(!d.hotelId||destinationHotel(d.hotelId)?.country===d.country)).slice(0,3);}
function rememberDestination(){const d=appliedDestination(),key=x=>JSON.stringify([x.country,[...x.resorts].sort(),x.hotelId]);saveStored('anytour.real.destinations.v1',[d,...recentDestinations().filter(x=>key(x)!==key(d))].slice(0,3));}
function openDestination(){cancelDestinationLookup();destinationChoice=structuredClone(currentDraftDestination());showModal('destination','Куда отправимся?','СТРАНА, КУРОРТ ИЛИ ОТЕЛЬ',`<div class="destination-search"><label class="sr-only" for="destination-query">Страна, курорт или отель</label>${icon('search')}<input class="input" type="search" id="destination-query" placeholder="Страна, курорт или отель" autocomplete="off" aria-controls="destination-results"></div><div id="destination-selection" class="destination-selection"></div><div id="destination-results" aria-live="polite"></div>`);$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<button class="primary picker-apply" data-action="apply-destination"></button>';renderDestination();loadResorts(destinationChoice.country);if(innerWidth>760)$('#destination-query').focus({preventScroll:true});}
function renderDestination(){
 const d=destinationChoice,q=normalizeSearch($('#destination-query').value),recent=recentDestinations();
 if(!countryNames[d.country]){
  $('#destination-selection').textContent=catalogError?'Направления пока недоступны':'Загружаем направления…';
  $('#destination-results').innerHTML=catalogError?`<div class="destination-empty" role="status"><p>${esc(catalogError)}</p><button class="secondary" data-action="retry-catalog">Повторить загрузку направлений</button></div>`:'<p role="status">Название можно ввести сейчас. Поиск начнётся после загрузки направлений.</p>';
  const apply=$('[data-action="apply-destination"]');apply.disabled=true;apply.textContent=catalogError?'Выбор пока недоступен':'Загружаем направления…';return;
 }
 $('#destination-selection').innerHTML=`<span>${esc(destinationLabel(d,true))}</span><button class="text-button" data-action="destination-all">Все курорты</button>`;
 const countryRows=Object.entries(countryNames).filter(([k,n])=>!q||normalizeSearch(n).includes(q));
 let html=!q&&recent.length?`<section class="destination-section"><h3>Недавние направления</h3><div class="destination-recents">${recent.map((x,i)=>`<button class="chip" data-action="destination-recent" data-value="${i}">${esc(destinationLabel(x))}</button>`).join('')}</div></section>`:'';
 if(countryRows.length)html+=`<section class="destination-section"><h3>${q?'Страны':'Направления'}</h3><div class="destination-countries">${countryRows.map(([k,n])=>`<button data-action="destination-country" data-value="${k}" aria-pressed="${d.country===k}">${esc(n)}${d.country===k?icon('check'):''}</button>`).join('')}</div></section>`;
 const resorts=Object.values(data.catalog.regions).flat().filter(r=>q?normalizeSearch(r.name+' '+countryNames[r.country]).includes(q):r.country===d.country);
 if(resorts.length)html+=`<section class="destination-section"><h3>${q?'Курорты':'Курорты · можно выбрать несколько'}</h3>${resorts.map(r=>{const selected=d.country===r.country&&d.resorts.includes(r.name);return `<button class="destination-row" data-action="destination-resort" data-country="${r.country}" data-value="${esc(r.name)}" aria-pressed="${selected}"><span class="choice-check">${selected?icon('check'):''}</span><span><strong>${esc(r.name)}</strong><small>${esc(countryNames[r.country]||'')}</small></span></button>`}).join('')}</section>`;
 if(!data.catalog.regions[d.country])html+=resortLoads.get(d.country)==='error'?'<p role="status">Не удалось загрузить курорты.</p><button class="secondary" data-action="retry-resorts">Повторить загрузку курортов</button>':'<p role="status">Загружаем курорты…</p>';
 const loaded=hotels.filter(h=>h.country===d.country&&(q?normalizeSearch([h.name,...hotelPlaces(h)].join(' ')).includes(q):!d.resorts.length||d.resorts.some(resort=>hotelPlaces(h).includes(resort))));
 const matches=q?[...new Map([...loaded,...destinationLookup.rows].map(h=>[h.id,h])).values()]:loaded;
 if(matches.length)html+=`<section class="destination-section"><h3>Отели · ${esc(countryNames[d.country]||'')}</h3>${matches.map(h=>`<button class="destination-row destination-hotel" data-action="destination-hotel" data-id="${h.id}" aria-pressed="${d.hotelId===h.id}"><img src="${esc(photoUrl(h))}" alt="" width="58" height="45"><span><strong>${esc(h.name)}</strong><small>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}${h.stars?' · '+h.stars+' ★':''}</small></span>${d.hotelId===h.id?icon('check'):''}</button>`).join('')}</section>`;
 if(q&&destinationLookup.status==='loading')html+='<p role="status">Ищем отели в каталоге…</p>';
 if(q&&destinationLookup.status==='error')html+='<div class="destination-empty" role="status"><h3>Не удалось загрузить отели</h3><p>Название сохранено. Попробуйте ещё раз.</p><button class="secondary" data-action="retry-destination">Повторить поиск отеля</button></div>';
 $('#destination-query').setAttribute('aria-busy',String(destinationLookup.status==='loading'));
 $('#destination-results').innerHTML=html||`<div class="destination-empty"><h3>${q.length===1?'Введите хотя бы 2 символа':'По названию ничего не нашли'}</h3><p>${q.length===1?'Продолжите название отеля.':'Проверьте написание или выберите другую страну.'}</p></div>`;
 const apply=$('[data-action="apply-destination"]');apply.disabled=!!q;apply.textContent=q?'Выберите отель или направление':'Выбрать '+destinationLabel(d);}
function updateNav(){
 const saved=readSelectedTour();$('#selected-tour-nav').innerHTML=icon('suitcase')+'<span class="nav-label">Мой тур</span>'+(saved?'<span class="saved-dot">1</span>':'');$('#selected-tour-nav').setAttribute('aria-label',saved?'Мой выбранный тур: '+(selectedTourHotel(saved)?.name||'Выбранный отель'):'Мой тур: пока не выбран');$$('.selected-tour-shortcut').forEach(b=>b.hidden=!saved);
 $('#favorites-nav').innerHTML=`${icon('heart')}<span class="nav-label">Избранное</span>${state.favorites.length?`<span class="saved-dot">${state.favorites.length}</span>`:''}`;$('#favorites-nav').setAttribute('aria-label',`Избранное: ${state.favorites.length}`);
 $('#compare-nav').innerHTML=`${icon('compare')}<span class="nav-label">Сравнение</span>${state.compare.length?`<span class="saved-dot">${state.compare.length}</span>`:''}`;$('#compare-nav').setAttribute('aria-label',`Сравнение: ${state.compare.length}`);
 const tray=$('#compare-tray');tray.hidden=!state.compare.length;tray.innerHTML=state.compare.length?`<div class="compare-tray-photos">${state.compare.map(id=>{const h=hotels.find(h=>h.id===id);return `<img src="${esc(photoUrl(h))}" alt="${esc(h.name)}">`}).join('')}</div><div class="compare-tray-copy"><strong>Сравнить отели</strong><span>Выбрано ${state.compare.length} из 3</span></div><button class="primary" data-action="compare">Сравнить ${icon('arrow')}</button><button class="icon-button" data-action="clear-compare" aria-label="Очистить сравнение">${icon('x')}</button>`:'';

}
let filterDraft=null,emptySuggestions=[],drawerSuggestions=[];
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
 if(model.onlyFavorites)add('favorites','Показать и несохранённые отели',m=>m.onlyFavorites=false);
 if(model.selectedDate)add('date',`Все даты: ${rangeText(state.search.from,state.search.to)}`,m=>m.selectedDate=null,'В пределах выбранного диапазона вылета');
 if(!candidates.length)add('reset','Сбросить все фильтры',m=>{m.filters=defaultFilters();m.onlyFavorites=false;m.selectedDate=null},'Город, страна, диапазон дат и туристы сохранятся');
 return candidates.slice(0,3);
}
function recoveryHTML(choices,source){return `<div class="recovery-options">${choices.map((c,i)=>`<button class="recovery-choice" data-action="recover-filters" data-source="${source}" data-recovery-key="${c.key}" data-value="${i}"><span><strong>${esc(c.title)}</strong><small>${esc(c.description)}</small></span><span class="recovery-count">${hotelCountText(c.count)} ${icon('arrow')}</span></button>`).join('')}</div>`;}
function emptyResultsHTML(){if(!state.hasSearched){const d=currentDraftDestination();if(d.hotelId&&!destinationHotel(d.hotelId)?.legacyIds.length)return `<div class="empty pristine-empty" role="status">${icon('info')}<h3>${hotelRestorePending?'Восстанавливаем выбранный отель…':'Не удалось восстановить отель'}</h3><p>Параметры поездки сохранены.${hotelRestorePending?'':' Повторите загрузку или выберите отель заново.'}</p>${hotelRestorePending?'':`<div class="empty-actions"><button class="secondary" data-action="retry-hotel-restore">Повторить загрузку отеля</button><button class="text-button" data-action="destination">Выбрать другой отель</button></div>`}</div>`;return `<div class="empty pristine-empty">${icon('search')}<h3>Начните с параметров поездки</h3><p>Выберите направление, даты и туристов, затем нажмите «Найти туры».</p></div>`;}emptySuggestions=recoverySuggestions(appliedFilterModel());return `<div class="empty recovery-empty">${icon('search')}<h3>Подходящих предложений пока нет</h3><p>Измените условия или повторите поиск.</p>${recoveryHTML(emptySuggestions,'results')}<div class="empty-actions"><button class="secondary" data-action="reset">Сбросить фильтры</button><button class="text-button" data-action="edit-search">Изменить поиск</button></div></div>`;}
function updateDrawerPreview(){
 if(!filterDraft)return;
 const count=countMatchingHotels(filterDraft),chips=filterChipData(filterDraft),pristine=!state.hasSearched&&!state.onlyFavorites;
 $('#apply-filters').textContent=pristine?'Сохранить условия':`Показать отели (${count})`;
 $('#filter-preview-count').textContent=pristine?'Условия для следующего поиска':count?`${hotelCountText(count)} по выбранным условиям`:'По выбранным условиям туров нет';
 $('#drawer-context').textContent=`${esc(countryNames[state.search.country]||'')} · ${rangeText(state.search.from,state.search.to)} · ${durationText()} · ${guestsText()}`;
 $('#drawer-selected').innerHTML=chips.map(c=>`<button class="active-filter" data-action="remove-draft-filter" data-key="${c.key}" data-value="${esc(c.value)}" aria-label="Убрать: ${esc(c.label)}">${esc(c.label)}${icon('x')}</button>`).join('');
 drawerSuggestions=count||pristine?[]:recoverySuggestions(filterDraft);
 $('#drawer-recovery').innerHTML=count||pristine?'':`<p>Можно изменить одно из условий:</p>${recoveryHTML(drawerSuggestions.slice(0,2),'drawer')}`;
 if(!pristine&&responseFor(state.search).phase!=='complete'){$('#filter-preview-count').textContent=count?`${hotelCountText(count)} среди полученных предложений`:'Подходящих предложений пока нет';drawerSuggestions=[];$('#drawer-recovery').innerHTML=count?'':'<p>Поиск не завершён. Условия можно сохранить и дождаться остальных предложений или повторить поиск.</p>';}
}
function filterEdited(rebuild=false){if(filterDraft){if(rebuild)renderFilters();else updateFacetCounts();updateDrawerPreview();return}if(rebuild)syncFilters();else{renderResults({keepFilters:true});updateSearchUI()}}
function checkRows(group,options){const model=editingFilterModel();return options.map(([val,label])=>{const count=countMatchingHotels({...model,filters:{...model.filters,[group]:[val]}});return `<label class="check-row"><input type="checkbox" data-filter="${group}" value="${esc(val)}" ${model.filters[group].includes(val)?'checked':''}><span>${esc(label)}</span><small aria-label="${hotelCountText(count)}">${count}</small></label>`}).join('');}
function amenityFilterGroups(hs,f){
 const facts=new Map();hs.forEach(h=>(h.amenities||[]).forEach(a=>{facts.set(a.key,a);amenityNames.set(a.key,a);}));
 for(const key of f.amenities||[])if(!facts.has(key)&&amenityNames.has(key))facts.set(key,amenityNames.get(key));
 const groups=new Map();for(const fact of facts.values()){if(!groups.has(fact.groupId))groups.set(fact.groupId,{name:fact.group,items:[]});groups.get(fact.groupId).items.push(fact);}
 return [...groups.values()].map(group=>`<div class="filter-group"><h4>${esc(group.name)}</h4>${group.items.map(a=>{const selected=(f.amenities||[]).includes(a.key),keys=[...new Set([...(f.amenities||[]),a.key])],count=countMatchingHotels({...editingFilterModel(),filters:{...f,amenities:keys}});return `<label class="check-row"><input type="checkbox" data-filter="amenities" value="${esc(a.key)}" ${selected?'checked':''}><span>${esc(a.label)}</span><small aria-label="${hotelCountText(count)}">${count}</small></label>`;}).join('')}</div>`).join('');
}
const plainHotelText=value=>data.text(value).replace(/<[^>]*>/g,' ').replace(/&nbsp;/gi,' ').replace(/\s+/g,' ').trim();
function hotelHighlights(h){
 const priority=[3,1,5,8,2],facts=[...(h.amenities||[])].sort((a,b)=>priority.indexOf(a.groupId)-priority.indexOf(b.groupId));
 if(facts.length)return [...new Set(facts.map(a=>a.label))].slice(0,4).join(' · ');
 const place=plainHotelText(h.raw?.place);return place.length>160?place.slice(0,157).replace(/\s+\S*$/,'')+'…':place;
}
function hotelDetailFacts(h){
 const info=h.raw?.hotelInformation||{},services=info.services||h.raw?.services||{},infrastructure=info.infrastructure||h.raw?.infrastructure||{};
 const sections=[['Расположение',h.raw?.place],['Пляж',infrastructure.beach],['Территория',infrastructure.territory],['Для детей',services.child],['Развлечения',services.animation],['Бесплатные услуги',services.free],['Платные услуги',services.servicesPay]];
 return sections.map(([label,value])=>{const text=plainHotelText(value);return text?`<h3 class="detail-section-title">${label}</h3><p class="hotel-detail-copy">${esc(text)}</p>`:'';}).join('');
}
function updateFacetCounts(){const model=editingFilterModel();$$('[data-filter],[data-filter-bool]').forEach(input=>{const key=input.dataset.filter||input.dataset.filterBool,value=key==='amenities'?[...new Set([...(model.filters.amenities||[]),input.value])]:input.dataset.filter?[input.value]:true,count=countMatchingHotels({...model,filters:{...model.filters,[key]:value}}),label=input.closest('.check-row')?.querySelector('small');if(label){label.textContent=count;label.setAttribute('aria-label',hotelCountText(count))}});}
function budgetScale(f){
 let high=Math.max(1000,f.min,f.max??0);
 for(const h of hotels)for(const o of h.offers||[])if(Number.isFinite(o.total)&&o.total>high)high=o.total;
 return Math.ceil(high/1000)*1000;
}
function syncBudgetControls(f){
 $('#min-price').value=f.min;$('#max-price').value=f.max??'';
 const range=$('#price-range'),high=budgetScale(f);range.max=high;range.value=f.max??high;
 range.setAttribute('aria-valuetext',budgetLabel(f));
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
 renderedFilterContext={filters,scope};
}
function renderFilters(){const model=editingFilterModel(),f=model.filters,hs=hotels.filter(h=>h.country===state.search.country),scale=budgetScale(f);paintFilters(`
 <div class="filter-group"><h4>Название отеля или курорт</h4><div class="filter-search"><input class="input" id="hotel-query" type="search" value="${esc(f.q)}" placeholder="Введите название" aria-label="Название отеля или курорт">${icon('search')}</div></div>
 <div class="filter-group"><h4>Бюджет на всех туристов</h4><div class="price-inputs"><label>От, ₽<input type="number" id="min-price" value="${f.min}" min="0" step="any"></label><label>До, ₽<input type="number" id="max-price" value="${f.max??''}" min="0" step="any" placeholder="Без ограничений"></label></div><input class="range" type="range" id="price-range" aria-label="Максимальная цена" aria-valuetext="${esc(budgetLabel(f))}" min="0" max="${scale}" step="1000" value="${f.max??scale}"></div>
 <div class="filter-group"><h4>Категория отеля</h4><div class="star-options">${[3,4,5].map(n=>`<button type="button" data-action="star" data-value="${n}" aria-pressed="${f.stars.includes(n)}" class="${f.stars.includes(n)?'active':''}">${n} <span>★</span></button>`).join('')}</div></div>
 ${Object.keys(mealNames).length?`<div class="filter-group"><h4>Питание</h4>${checkRows('meals',[...new Set([...Object.keys(mealNames),...f.meals])].map(m=>[m,m]))}</div>`:''}
 ${f.rating||hs.length&&hs.every(h=>h.rating!==null)?`<div class="filter-group"><h4>Оценка гостей</h4><label class="check-row"><input type="checkbox" data-filter-bool="rating" ${f.rating?'checked':''}><span>От 4,5 из 5</span><small>${countMatchingHotels({...model,filters:{...f,rating:true}})}</small></label></div>`:''}
 ${f.resorts.length||hs.some(h=>hotelPlaces(h).length)?`<div class="filter-group"><h4>Курорт</h4>${checkRows('resorts',[...new Set([...f.resorts,...hs.flatMap(h=>hotelPlaces(h))])].map(r=>[r,r]))}</div>`:''}
 ${f.operators.length||operators.length?`<div class="filter-group"><h4>Туроператор</h4>${checkRows('operators',[...new Set([...f.operators,...operators])].map(o=>[o,o]))}</div>`:''}
 ${amenityFilterGroups(hs,f)}
 <div class="filter-hint">${icon('info')}<span>${!state.hasSearched&&!state.onlyFavorites?'Условия применятся после нажатия «Найти туры». Доступные курорты и туроператоры появятся в выдаче.':'Фильтры применяются к найденным предложениям. Актуальная цена и сборы уточняются при выборе.'}</span></div>`,f);
 $('#beach-chip').hidden=true;$('#family-chip').hidden=true;$('#rating-chip').hidden=!hs.length||hs.some(h=>h.rating===null);
}
function loadResultCalendar(){
 if(!catalogReady||!state.hasSearched)return;
 const s=structuredClone(state.search),f=state.filters,filters={stars:[...f.stars],meals:[...f.meals],resorts:[...f.resorts],min:f.min,max:f.max};
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
 const source=resultCalendar.phase==='loading'?'Загружаем цены из базы…':resultCalendar.phase==='error'?'База цен временно недоступна · показаны найденные предложения':resultCalendar.phase==='partial'?'Часть базы цен временно недоступна · показаны доступные цены и найденные предложения':'Из базы и найденных предложений · цена требует проверки';
 $('#calendar-caption').textContent=`Цена от за ${guestsText()} · ${durationText()} · ${source} · прочерк — нет цены`;
 $('#price-strip').setAttribute('aria-busy',String(resultCalendar.phase==='loading'));
 $('#price-strip').innerHTML=days.map((day,i)=>`<button class="date-price ${prices[i]!==null&&prices[i]===min?'best':''} ${state.selectedDate===day?'selected':''}" data-action="select-date" data-date="${day}" aria-pressed="${state.selectedDate===day}" aria-label="Вылет ${dateLong(day)}${prices[i]!==null?', от '+money(prices[i]):', цена пока неизвестна'}"><span class="date">${dateText(day)}</span><strong>${prices[i]===null?'—':money(prices[i])}</strong><span class="calendar-bar" style="--bar-height:${prices[i]===null?5:12+Math.round((prices[i]-min)/Math.max(1,max-min)*22)}px"></span></button>`).join('');
 $('#clear-date').hidden=!state.selectedDate;
}
function renderActive(){const f=state.filters,chips=filterChipData();
 $('#active-filters').innerHTML=chips.map(c=>`<button class="active-filter" data-action="remove-filter" data-key="${c.key}" data-value="${esc(c.value)}" aria-label="Убрать: ${esc(c.label)}">${esc(c.label)}${icon('x')}</button>`).join('');
 for(const key of ['beach','rating','family']){$('#'+key+'-chip').classList.toggle('active',f[key]);$('#'+key+'-chip').setAttribute('aria-pressed',f[key])}
 const n=filterCount();$('#filter-count').textContent=n?`(${n})`:'';$('#mobile-count').textContent=n?`(${n})`:'';$('#drawer-filter-count').textContent=n?`(${n})`:'';
}
function offerHTML(h,o){return `<div class="offer" data-offer-key="${o.key}"><div><strong>${dateText(o.day)} → ${dateText(o.returnDay)}</strong><small>${nightsText(o.nights)} · ${guestsText(o)}</small><small>Город вылета: ${esc(o.origin)}</small></div><div><strong>${esc(mealLabel(o))}</strong><small>${esc(o.room)}</small></div><div><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}<small>${needsRefresh(o)?'Сохранённая цена · требует проверки':'Рейсы и багаж — при выборе'}</small></div><div class="offer-price"><strong>${money(o.total)}</strong><button class="primary" data-action="offer" data-key="${o.key}">${offerActionLabel(o)} ${icon('arrow')}</button></div></div>`;}
function cardHTML({hotel:h,offers}){const o=offers[0],opened=state.openHotel===h.id,photoIndex=state.photoIndexes[h.id]||0;return `<article class="hotel-card" id="hotel-${h.id}" data-hotel-id="${h.id}"><div class="hotel-main">
 <div class="hotel-photos ${h.photos.length?'':'photo-unavailable'}"><div class="hotel-image-wrap"><button class="hotel-image-button" data-action="gallery" data-id="${h.id}" aria-label="Открыть фотографии ${esc(h.name)}"><img class="hotel-image" src="${esc(photoUrl(h,photoIndex))}" alt="Фото отеля ${esc(h.name)}" loading="lazy" width="700" height="500"><span class="photo-count">${icon('image')} <span class="photo-index">${h.photos.length?photoIndex+1:0}</span> / ${h.photos.length}</span></button><button class="favorite-button ${state.favorites.includes(h.id)?'active':''}" data-action="favorite" data-id="${h.id}" aria-pressed="${state.favorites.includes(h.id)}" aria-label="${state.favorites.includes(h.id)?'Убрать из избранного':'В избранное'}: ${esc(h.name)}">${icon('heart')}</button><button class="compare-photo-button ${state.compare.includes(h.id)?'active':''}" data-action="toggle-compare" data-id="${h.id}" aria-pressed="${state.compare.includes(h.id)}" aria-label="${state.compare.includes(h.id)?'Убрать из сравнения':'Сравнить'}: ${esc(h.name)}" title="${state.compare.includes(h.id)?'В сравнении':'Сравнить отель'}">${icon(state.compare.includes(h.id)?'check':'compare')}</button><button class="card-photo-arrow prev" data-action="card-photo" data-id="${h.id}" data-dir="-1" aria-label="Предыдущее фото ${esc(h.name)}">${icon('back')}</button><button class="card-photo-arrow next" data-action="card-photo" data-id="${h.id}" data-dir="1" aria-label="Следующее фото ${esc(h.name)}">${icon('arrow')}</button></div><div class="card-thumbs">${h.photos.map((p,i)=>`<button class="card-thumb ${photoIndex===i?'active':''}" data-action="card-photo-index" data-id="${h.id}" data-value="${i}" aria-label="Показать фото ${i+1} отеля ${esc(h.name)}" aria-pressed="${photoIndex===i}"><img src="${esc(p)}" alt="" loading="lazy" width="150" height="100"></button>`).join('')}</div></div>
 <div class="hotel-info"><div class="hotel-info-top"><div><div class="hotel-stars" aria-label="${h.stars} звёзд">${'★'.repeat(h.stars)}</div><h3><button data-action="hotel-details" data-id="${h.id}">${esc(h.name)}${icon('arrow')}</button></h3><div class="hotel-location">${icon('pin')} ${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</div></div><div class="rating-block"><strong>${ratingText(h)}<small>/ 5</small></strong><span>${h.rating===null?'Нет оценки':ratingValue(h)>=4.5?'Отлично':'Оценка гостей'}</span><small>Оценка гостей</small></div></div><div class="hotel-facts"><span>${esc(hotelHighlights(h))}</span></div></div></div>
 ${minimumOfferSummary(o)}<div class="hotel-price"><div class="starting-price"><span>За ${guestsText(o)} · весь тур</span><strong><small>от </small>${money(o.total)}</strong></div><span class="fuel-note">${icon('info')} ${priceNote(o)}</span><button class="primary" data-action="all-offers" data-id="${h.id}">Выбрать тур ${icon('arrow')}</button><span class="hotel-offer-count">${offers.length} ${offers.length%10===1&&offers.length%100!==11?'вариант тура':offers.length%10>=2&&offers.length%10<=4&&(offers.length%100<12||offers.length%100>14)?'варианта тура':'вариантов тура'}</span></div><div class="hotel-more"><button class="text-button" data-action="toggle-offers" data-id="${h.id}" aria-expanded="${opened}" aria-controls="offers-${h.id}">${opened?'Скрыть варианты':offers.length===1?'Показать вариант тура':'Показать варианты туров'} <span class="rotate-arrow ${opened?'up':''}">⌄</span></button></div>
 <div class="offers" id="offers-${h.id}" ${opened?'':'hidden'}><div class="offers-header"><strong>Выберите подходящий вариант</strong><small>Цена предложения за ${guestsText()}</small></div>${offers.slice(0,3).map(o=>offerHTML(h,o)).join('')}${offers.length>3?`<button class="text-button" data-action="all-offers" data-id="${h.id}">Все ${offers.length} вариантов ${icon('arrow')}</button>`:''}</div></article>`;}
function openHotelDetails(id){const h=hotels.find(h=>h.id===id);if(!h)return;const offers=hotelOffers(h);showModal('hotel-details',h.name,`${esc(h.resort)} · ${h.stars?h.stars+' ★':'Категория не указана'}`,`<div class="hotel-detail-photos">${h.photos.slice(0,3).map((p,i)=>`<button data-action="hotel-gallery" data-id="${h.id}" data-value="${i}" aria-label="Открыть фото ${i+1}"><img src="${esc(p)}" alt="Фото отеля"></button>`).join('')}</div><div class="hotel-detail-heading"><h3>Об отеле</h3><span class="detail-rating">${ratingText(h)} / 5</span></div><p class="hotel-detail-copy">${esc(h.note.replace(/<[^>]*>/g,' '))||'Описание пока не заполнено.'}</p>${hotelDetailFacts(h)}<h3 class="detail-section-title">Номера в найденных турах</h3><div class="room-options">${[...new Set(offers.map(o=>o.room))].map(room=>`<div><strong>${esc(room)}</strong></div>`).join('')}</div>`,true);if(offers.length){$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total"><span>Туры за ${guestsText()}</span><strong>от ${money(offers[0].total)}</strong></div><button class="primary" data-action="all-offers" data-id="${id}">Выбрать тур ${icon('arrow')}</button>`;}}
function renderResults(options={}){
 if(searchResponse.key&&searchResponse.key!==searchKey(state.search)){clearSearchTimers();searchResponse={key:searchKey(state.search),phase:'complete',operators:[...operators],pending:false};}
 const items=results(),total=items.reduce((s,r)=>s+r.offers.length,0),pristine=!state.hasSearched&&!state.onlyFavorites;
 $('#results').classList.toggle('results-pristine',pristine);document.body.classList.toggle('results-pristine-active',pristine);
 $('#compact-route').textContent=`${esc(state.search.origin)} → ${destinationLabel(appliedDestination())}`;
 $('#compact-details').textContent=`${state.selectedDate?dateText(state.selectedDate):rangeText(state.search.from,state.search.to)} · ${durationText()} · ${guestsText()}`;
 $('#route-label').textContent=`${esc(state.search.origin)} → ${esc(countryNames[state.search.country]||'')} · ${rangeText(state.search.from,state.search.to)}`;
 $('#results-title').textContent=pristine?'Ваш следующий отдых':state.onlyFavorites?'Ваши избранные отели':`Отели и туры · ${countryNames[state.search.country]||'Выберите направление'}`;
 $('#results-summary').textContent=pristine?'Задайте направление, даты и состав туристов — предложения появятся после поиска.':`${items.length} отелей · ${total} вариантов тура · ${durationText()} · ${guestsText()}`;
 $('#cards').innerHTML=items.length?items.map(cardHTML).join(''):emptyResultsHTML();
 $('#apply-filters').textContent=pristine?'Сохранить условия':`Показать отели (${items.length})`;
 renderCalendarStrip();renderActive();updateNav();renderSummary();updateURL();if(!options.keepFilters)renderFilters();else updateFacetCounts();renderSearchStatus(items,total);if(filterDraft)updateDrawerPreview();
}
function syncFilters(){renderResults();updateSearchUI();}
// One browser-history entry belongs to an open dialog/drawer session. Nested
// Back consumes the in-memory step before reusing that entry; closing consumes
// it altogether. Forward/reload only reopen a passive view, never a request or
// a previously accepted price. Keep search parameters canonical on every pop.
const uiHistoryKey='anytour.real.ui.v1';
let uiHistoryOpen=false,uiHistoryClosing=false,restoringUIHistory=false,handlingUIBack=false,actionTrigger=null,pageReturn=null,pendingPageReturn=null;
function focusReference(element,root=document){
 if(!element||element===document.body||element===document.documentElement||!root.contains(element))return null;
 let selector=element.id?'#'+CSS.escape(element.id):'';
 if(!selector&&element.dataset?.action){selector='[data-action="'+CSS.escape(element.dataset.action)+'"]';for(const key of ['id','key','value'])if(element.dataset[key]!==undefined)selector+='[data-'+key+'="'+CSS.escape(element.dataset[key])+'"]';const card=element.closest('.hotel-card');if(card)selector='#'+CSS.escape(card.id)+' '+selector;}
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
 if(type==='success')return {type:'saved-tour'};
 if(type==='offer')return selectedOffer===savedSelection?{type:'saved-details'}:{type,key:selectedOffer?.key,flightChoiceId:selectedOffer?.flightChoiceId};
 if(type==='all-offers')return {type,id:offerView?.id,mode:offerView?.mode,day:offerView?.day,nights:offerView?.nights,flight:offerView?.flight,room:offerView?.room,meal:offerView?.meal,sort:offerView?.sort,pair:offerView?.pair,activeVariant:offerView?.activeVariant,differencesOnly:offerView?.differencesOnly};
 if(type==='hotel-details')return {type,id:hotels.find(h=>h.name===$('#modal-title').textContent)?.id};
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
 case 'saved-tour':openSelectedTour();break;
 case 'saved-details':{const o=readSelectedTour();if(o){selectedOffer=o;renderRealOffer();}else openSelectedTour();break;}
 case 'selected-tour':{const o=readSelectedTour();if(o){selectedOffer=o;openLeadPreview();}else openSelectedTour();break;}
 case 'favorites':openFavorites();break;
 case 'compare':openCompare();break;
 case 'hotel-details':if(!hotel)return false;openHotelDetails(hotel.id);break;
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
function showModal(type,title,kicker,body,wide=false){
 cancelVerification();const m=$('#modal');
 if(!m.open){modalHistory.length=0;enterUIHistory();}
 else if(!restoringModal&&modalType!==type){modalHistory.push({type:modalType,title:$('#modal-title').textContent,kicker:$('#modal-kicker').textContent,body:$('#modal-body').innerHTML,footer:$('#modal-footer').innerHTML,footerHidden:$('#modal-footer').hidden,className:m.className,scroll:$('#modal-body').scrollTop,gallery:{...gallery},offer:selectedOffer,focus:focusReference(actionTrigger||document.activeElement,m)});}
 modalType=type;$('#modal-back').hidden=!modalHistory.length;m.className=type==='gallery'?'gallery-dialog':wide?'wide-dialog':type==='dates'?'dates-dialog':'';$('#modal-title').textContent=title;$('#modal-kicker').textContent=kicker;$('#modal-body').innerHTML=body;$('#modal-footer').innerHTML='';$('#modal-footer').hidden=true;$('#modal-body').scrollTop=0;
 if(!m.open)m.showModal();document.body.style.overflow='hidden';m.scrollTop=0;$('#modal-body').scrollTop=0;hydrate();syncDestinationViewport();queueMicrotask(rememberUIRoute);
}
function closeModal({fromHistory=false}={}){
 selectionGeneration++;calendarRequest?.abort();calendarObserver?.disconnect();cancelDestinationLookup();
 const m=$('#modal');if(!m.open)return;
 leaveUIHistory(fromHistory);cancelVerification();modalType='';modalHistory.length=0;m.close();document.body.style.overflow=$('#filter-panel').classList.contains('open')?'hidden':'';restorePageReturn();
}
function modalBack(){
 const previous=modalHistory.pop();if(!previous)return;
 restoringModal=true;showModal(previous.type,previous.title,previous.kicker,previous.body,previous.className==='wide-dialog');restoringModal=false;
 $('#modal').className=previous.className;$('#modal-footer').innerHTML=previous.footer;$('#modal-footer').hidden=previous.footerHidden;gallery=previous.gallery;selectedOffer=previous.offer;$('#modal-back').hidden=!modalHistory.length;
 if(previous.type==='all-offers'&&offerView)renderOfferList();if(previous.type==='compare')renderCompare();if(previous.type==='favorites')renderFavorites();if(previous.type==='selected-tour')window.AnyTourPrototypeLead.bind(selectedOffer);
 restoreFocus(previous.focus,$('#modal-title'),$('#modal'));$('#modal-body').scrollTop=previous.scroll;rememberUIRoute();
}
$('#modal').addEventListener('cancel',e=>{e.preventDefault();closeModal();});
$('#modal').addEventListener('click',e=>{if(e.target===$('#modal')){const r=e.target.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closeModal()}});
let calendarHotels=[],calendarObservations=[],calendarRequest=null,calendarObserver=null,calendarMobile=null;
let dateContext=null,datePrices=new Map(),mealDraft=[],dateAnchor=null;
function budgetLabel(f){return f.max===null?(f.min?'От '+money(f.min):'Без ограничений'):f.min?`${money(f.min)} — ${money(f.max)}`:'До '+money(f.max);}
const budgetText=()=>budgetLabel(state.filters);
function createDateContext(source='form'){
 const s=source==='results'?state.search:draft,filters=structuredClone(state.filters);
 if(source==='form'&&draftDestination){filters.resorts=[...draftDestination.resorts];filters.hotelId=draftDestination.hotelId;filters.q='';}else if(s.country!==state.search.country){filters.resorts=[];filters.hotelId=0;filters.q='';}
 return {source,search:structuredClone(s),filters};
}
const dateContextLabel=s=>`${countryNames[s.country]||''} · из ${s.origin==='Москва'?'Москвы':s.origin==='Казань'?'Казани':'Санкт-Петербурга'} · ${guestsText(s)} · ${durationText(s)}`;
function openMeals(){
 mealDraft=[...state.filters.meals];showModal('meals','Какое питание включить?','ПИТАНИЕ',`<p class="modal-intro">Можно выбрать несколько вариантов.</p><div class="meal-options">${[['','Любое питание'],...[...new Set([...Object.keys(mealNames),...mealDraft])].map(m=>[m,m])].map(([v,label])=>`<label class="meal-option"><input type="checkbox" data-meal-choice value="${esc(v)}" ${v?mealDraft.includes(v)?'checked':'':!mealDraft.length?'checked':''}><span><strong>${esc(label)}</strong>${v==='Всё включено'?'<small>В том числе варианты «всё включено»</small>':v==='Полупансион'?'<small>Два приёма пищи в день</small>':''}</span></label>`).join('')}</div>`);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<button class="primary picker-apply" data-action="apply-meals">Применить</button>';
}
function openBudget(){showModal('budget','Бюджет на весь тур','НА ВСЕХ ТУРИСТОВ',`<p class="modal-intro">Бюджет на весь тур и всех туристов. Актуальность цены и обязательные сборы уточняются при выборе предложения.</p><div class="form-row"><label>От, ₽<input type="number" class="input" id="budget-min" min="0" step="any" value="${state.filters.min}"></label><label>До, ₽<input type="number" class="input" id="budget-max" min="0" step="any" value="${state.filters.max??''}" placeholder="Без ограничений"></label></div><div class="budget-presets">${[150000,200000,300000,null].map(n=>`<button class="chip" data-action="budget-preset" data-value="${n??''}">${n===null?'Без ограничений':'До '+money(n)}</button>`).join('')}</div><p class="error-text" id="budget-error" role="alert"></p>`);$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<button class="primary picker-apply" data-action="apply-budget">Применить бюджет</button>';}
function openDates(source='form'){
 dateContext=createDateContext(source);const s=dateContext.search,selectedDay=source==='results'?state.selectedDate:draftSelectedDate();
 dateDraft={from:selectedDay||s.from,to:selectedDay||s.to,phase:0,flex:0};
 dateAnchor=dateDraft.from;datePrices=new Map();calendarMonth=dateDraft.from.slice(0,7)+'-01';
 showModal('dates','Даты вылета','ЦЕНЫ ИЗ БАЗЫ И ВЫДАЧИ · ЗА ВСЕХ',`<p class="calendar-context">${esc(dateContextLabel(s))}</p><div class="calendar-legend"><span>Цена от, тыс. ₽</span><span><i class="legend-dot"></i>Минимум среди показанных цен</span></div><div id="date-calendar"></div><details class="manual-dates"><summary>Ввести даты вручную</summary><div class="form-row"><label>Вылет от<input class="input" id="date-from" type="date" min="${startDay}" max="${endDay}" value="${dateDraft.from}"></label><label>Вылет до<input class="input" id="date-to" type="date" min="${startDay}" max="${endDay}" value="${dateDraft.to}"></label></div></details>`);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="date-footer"><div class="flex-dates" aria-label="Гибкие даты">${[0,1,2,3].map(n=>`<button data-action="flex-date" data-value="${n}" aria-pressed="${!n}">${n?'±'+n+' '+(n===1?'день':'дня'):'Точно'}</button>`).join('')}</div><p id="date-selection-hint" aria-live="polite"></p><p class="error-text" id="date-error" role="alert"></p><button class="primary picker-apply" data-action="apply-dates"></button></div>`;
 renderDateCalendar();
 loadCalendarPrices();
 if(innerWidth<=760&&calendarMonth!==startDay.slice(0,7)+'-01')requestAnimationFrame(()=>document.querySelector(`[data-month="${calendarMonth}"]`)?.scrollIntoView({block:'start'}));
}
function calendarPrice(day){if(!datePrices.has(day))datePrices.set(day,calendarMinimum(day,{search:dateContext.search,filters:dateContext.filters,calendarHotels:[...hotels,...calendarHotels]},calendarObservations));return datePrices.get(day);}
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
function updateDateSelection(){
 $$('[data-action="day-pick"]').forEach(b=>{const d=b.dataset.date,active=d===dateDraft.from||d===dateDraft.to;b.classList.toggle('active',active);b.classList.toggle('in-range',d>dateDraft.from&&d<dateDraft.to);b.setAttribute('aria-pressed',active||d>dateDraft.from&&d<dateDraft.to)});
 $('#date-from').value=dateDraft.from;$('#date-to').value=dateDraft.to;
 $('[data-action="apply-dates"]').textContent='Выбрать '+rangeText(dateDraft.from,dateDraft.to);
 $('#date-selection-hint').textContent=dateDraft.phase?'Выберите конец диапазона или подтвердите один день':'Даты вылета · не больше 21 дня между датами';
 $$('[data-action="flex-date"]').forEach(b=>b.setAttribute('aria-pressed',+b.dataset.value===dateDraft.flex));$('#date-error').textContent='';
}
function openCalendar(){openDates('results');}
function openGuests(){guestDraft={adults:draft.adults,ages:[...draft.ages]};showModal('guests','Кто отправится в путешествие?','ТУРИСТЫ','');renderGuests();}
function renderGuests(){const g=guestDraft;$('#modal-body').innerHTML=`<div class="counter-row"><div><strong>Взрослые</strong><small>От 18 лет</small></div><div class="counter"><button data-action="adults-minus" aria-label="Убрать взрослого" ${g.adults<=1?'disabled':''}>−</button><output>${g.adults}</output><button data-action="adults-plus" aria-label="Добавить взрослого" ${g.adults>=6?'disabled':''}>+</button></div></div><div class="counter-row"><div><strong>Дети</strong><small>До 18 лет на дату возвращения</small></div><div class="counter"><button data-action="children-minus" aria-label="Убрать ребёнка" ${!g.ages.length?'disabled':''}>−</button><output>${g.ages.length}</output><button data-action="children-plus" aria-label="Добавить ребёнка" ${g.ages.length>=3?'disabled':''}>+</button></div></div>${g.ages.length?`<div class="ages">${g.ages.map((age,i)=>`<label>Ребёнок ${i+1}<select class="input" data-child-age="${i}" aria-label="Возраст ребёнка ${i+1}"><option value="" ${age===null?'selected':''}>Возраст</option>${Array.from({length:18},(_,n)=>`<option value="${n}" ${age===n?'selected':''}>${n===0?'До года':n+' лет'}</option>`).join('')}</select></label>`).join('')}</div>`:''}<p class="error-text" id="guest-error" role="alert"></p><p class="modal-intro" style="margin-top:18px">Цена учитывает всех туристов и возраст каждого ребёнка.</p><div class="modal-actions"><button class="primary" data-action="apply-guests">Готово</button></div>`;}
function openNights(){nightsDraft={min:draft.minNights,max:draft.maxNights,phase:0};showModal('nights','На сколько ночей?','ПРОДОЛЖИТЕЛЬНОСТЬ',`<p class="modal-intro">Нажмите одно число для точной длительности или два — для диапазона.</p><div class="night-grid" aria-label="Количество ночей">${Array.from({length:12},(_,i)=>`<button data-action="night-pick" data-value="${i+3}" aria-label="${nightsText(i+3)}">${i+3}</button>`).join('')}</div><div class="nights-options">${[7,10,14].map(n=>`<button data-action="night-preset" data-value="${n}">${nightsText(n)}</button>`).join('')}</div><p class="night-selection" aria-live="polite"></p>`);$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<button class="primary picker-apply" data-action="apply-nights"></button>';renderNightSelection();}
function renderNightSelection(){$$('.night-grid button').forEach(b=>{const n=+b.dataset.value,active=n===nightsDraft.min||n===nightsDraft.max;b.classList.toggle('active',active);b.classList.toggle('in-range',n>nightsDraft.min&&n<nightsDraft.max);b.setAttribute('aria-pressed',n>=nightsDraft.min&&n<=nightsDraft.max)});$$('.nights-options button').forEach(b=>b.setAttribute('aria-pressed',nightsDraft.min===+b.dataset.value&&nightsDraft.max===+b.dataset.value));const label=durationText({minNights:nightsDraft.min,maxNights:nightsDraft.max});$('.night-selection').textContent=nightsDraft.phase?'Выберите вторую границу или подтвердите '+label:'Выбрано: '+label;$('[data-action="apply-nights"]').textContent='Выбрать '+label;}
function openGallery(id,index=0){const h=hotels.find(x=>x.id===id);if(!h?.photos.length){toast('Фотографии этого отеля пока недоступны.');return;}showModal('gallery',h.name,'ФОТОГРАФИИ ОТЕЛЯ','');gallery={id,index};renderGallery();}
function renderGallery(){const h=hotels.find(x=>x.id===gallery.id);if(!h?.photos.length)return;$('#modal-body').innerHTML=`<div class="gallery-stage"><img id="gallery-image" src="${esc(photoUrl(h,gallery.index))}" alt="Фото ${gallery.index+1} из ${h.photos.length}"><button class="icon-button gallery-arrow prev" data-action="gallery-prev" aria-label="Предыдущее фото">${icon('back')}</button><button class="icon-button gallery-arrow next" data-action="gallery-next" aria-label="Следующее фото">${icon('arrow')}</button></div><div class="gallery-caption"><span>${esc(h.name)}</span><span>${gallery.index+1} / ${h.photos.length}</span></div><div class="gallery-thumbs">${h.photos.map((p,i)=>`<button data-action="gallery-index" data-value="${i}" class="${gallery.index===i?'active':''}" aria-label="Фото ${i+1}"><img src="${esc(p)}" alt=""></button>`).join('')}</div>`;}
let flightDraft=null,selectionGeneration=0;
const flightPairFor=o=>o?.variants?.[Number(o.flightChoiceId)]||null;
function offerFromKey(key){return hotels.flatMap(h=>h.offers||[]).find(o=>o.key===key)||null;}
function fuelText(o){const n=data.fuel(o.tour,flightPairFor(o));return n===null?'Сбор уточняется':n===0?'Без доплаты':money(n)+' · условия включения уточняются';}
function withFlightPair(o,id){const variant=o.variants?.[Number(id)],price=data.variantPrice(o.tour,variant);return {...o,flightChoiceId:String(id),total:price,pricePending:!price};}
function legHTML(segments,label){
 if(!segments?.length)return `<div class="flight-leg"><strong>${label}</strong><p class="tour-missing">Расписание пока не предоставлено.</p></div>`;
 return segments.map((f,i)=>{const d=f.departure||{},a=f.arrival||{},placeholder=/000$/.test(String(f.number||'').replace(/\s/g,''))&&d.time==='00:00'&&a.time==='00:00';const bag=f.baggage===null||f.baggage===undefined||f.baggage===''||placeholder&&Number(f.baggage)===0?'уточняется':Number(f.baggage)===0?'без багажа':f.baggage+' кг';return `<div class="flight-leg"><div class="flight-leg-label"><strong>${i?'Пересадка · ':''}${label}</strong><span>${esc(data.text(f.company))} ${esc(placeholder?'Рейс уточняется':f.number||'')}</span></div><div class="flight-timeline"><div><strong>${esc(placeholder?'—':d.time||'—')}</strong><span>${esc(data.text(d.port))}</span><small>${esc(d.date||'')}</small></div><div class="flight-duration"><i>${icon('plane')}</i><span>${esc(f.plane||'')}</span></div><div><strong>${esc(placeholder?'—':a.time||'—')}</strong><span>${esc(data.text(a.port))}</span><small>${esc(a.date||'')}</small></div></div><div class="flight-included"><span>${icon('suitcase')} Багаж: ${esc(bag)}</span><span>Ручная кладь: ${esc(f.carryOn||'уточняется')}</span></div></div>`;}).join('');
}
function flightSummaryHTML(o){const v=flightPairFor(o);return `<section class="tour-section flight-summary"><div class="tour-section-heading"><h3>${icon('plane')} Перелёт туда и обратно</h3></div>${o.flightsLoading?'<p role="status">Загружаем реальные рейсы…</p>':v?legHTML(v.forward,'Туда')+legHTML(v.backward,'Обратно'):`<p class="tour-missing">${esc(o.flightsError||'Варианты рейсов пока не предоставлены.')}</p>`}<div class="flight-summary-action"><span class="flight-demo-note">Время местное</span>${o.tour?`<button class="secondary" data-action="${o.variants?.length?'choose-flight':'retry-flights'}" ${o.flightsLoading?'disabled':''}>${o.variants?.length?'Выбрать рейсы':'Повторить загрузку'} ${icon('arrow')}</button>`:''}</div></section>`;}
async function openOffer(key,restored=null,chooseFlight=false){
 const initial=restored||offerFromKey(key);if(!initial)return;
 const run=++selectionGeneration;selectedOffer={...initial};renderRealOffer();
 if(needsRefresh(initial))return;
 selectedOffer.loading=true;renderRealOffer();
 try{const tour=await data.quote(initial);if(run!==selectionGeneration||!$('#modal').open||modalType!=='offer'||selectedOffer?.key!==key)return;selectedOffer={...initial,tour,total:data.amount(tour.price)||initial.total,room:data.text(tour.roomType)||initial.room,meal:data.meal(tour.meal)||initial.meal,loading:false,flightsLoading:true};renderRealOffer();await loadRealFlights(run);if(chooseFlight&&run===selectionGeneration&&selectedOffer?.variants?.length)openFlightPicker();}
 catch(error){if(run===selectionGeneration&&$('#modal').open&&modalType==='offer'&&selectedOffer?.key===key){selectedOffer={...initial,quoteError:error.message,loading:false};renderRealOffer();}}
}
async function loadRealFlights(run=selectionGeneration){
 const o=selectedOffer;if(!o?.tour)return;selectedOffer.flightsLoading=true;renderRealOffer();
 try{const variants=await data.flights(o.tour);if(run!==selectionGeneration||selectedOffer?.key!==o.key||!$('#modal').open||modalType!=='offer')return;const index=Math.max(0,variants.findIndex(v=>v.isDefault));selectedOffer={...selectedOffer,variants,flightsLoading:false,flightsError:''};if(variants.length)selectedOffer=withFlightPair(selectedOffer,String(index));renderRealOffer();}
 catch(error){if(run===selectionGeneration&&$('#modal').open&&modalType==='offer'&&selectedOffer?.key===o.key){selectedOffer={...selectedOffer,flightsLoading:false,flightsError:'Не удалось загрузить рейсы. Попробуйте ещё раз.'};renderRealOffer();}}
}
function renderRealOffer(){
 const o=selectedOffer,h=selectedTourHotel(o);if(!o||!h)return;
 const unavailable=needsRefresh(o);
 showModal('offer','Ваш тур в деталях',o.loading?'ПРОВЕРЯЕМ ПРЕДЛОЖЕНИЕ':'ПРОВЕРЬТЕ УСЛОВИЯ',`
 <div class="tour-hero"><img src="${esc(photoUrl(h))}" alt="Фото ${esc(h.name)}"><div><div class="hotel-stars">${'★'.repeat(h.stars)}</div><h3>${esc(h.name)}</h3><p>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</p></div>${operatorBadge(o.operator)}</div>
 ${unavailable?`<p class="saved-tour-notice">Сохранённое предложение доступно 24 часа с момента проверки. Повторный поиск использует тот же отель, дату, ${nightsText(o.nights)}, состав туристов, номер, питание, туроператора и тип перелёта. Другой тур не будет подставлен вместо выбранного.</p>`:''}
 ${o.quoteError?`<p class="error-text" role="alert">${esc(o.quoteError)}</p>`:''}
 ${o.pricePending?'<p class="error-text" role="status">Цена выбранного перелёта пока не подтверждена. Выберите другой вариант рейсов.</p>':''}
 <dl class="detail-grid tour-summary"><div><dt>Вылет — возвращение</dt><dd>${rangeText(o.day,o.returnDay)}</dd></div><div><dt>Продолжительность</dt><dd>${nightsText(o.nights)}</dd></div><div><dt>Туристы</dt><dd>${guestsText(o)}${o.ages.length?'<small>Возраст детей: '+o.ages.join(', ')+' лет</small>':''}</dd></div><div><dt>Питание</dt><dd>${esc(mealLabel(o))}</dd></div></dl>
 <div class="tour-layout"><div class="tour-main-details">${flightSummaryHTML(o)}<section class="tour-section"><h3>${icon('moon')} Проживание и питание</h3><dl class="stay-grid"><div><dt>Номер</dt><dd>${esc(o.room)}</dd></div><div><dt>Размещение</dt><dd>${esc(data.text(o.tour?.placement)||o.placement||'Уточняется')}</dd></div><div><dt>Питание</dt><dd>${esc(mealLabel(o))}</dd></div></dl></section><section class="tour-section"><h3>${icon('shield')} Условия перед выбором</h3><p class="tour-missing">Условия трансфера, страховки, отмены и изменения тура уточняются для конкретного предложения.</p></section></div>
 <aside class="tour-price-details" aria-label="Состав и стоимость тура"><div class="price-breakdown"><h3>Стоимость тура</h3><p class="price-party">За ${guestsText(o)} · ${nightsText(o.nights)}</p><div class="price-line"><span>Топливный сбор</span><strong>${fuelText(o)}</strong></div><div class="price-line total"><span>${o.flightChoiceId!==null?'С выбранным перелётом':'Цена предложения'}</span><strong id="detail-total">${o.pricePending?'Уточняется':money(o.total)}</strong></div><p class="price-assurance">${icon('info')} ${o.loading?'Проверяем актуальность…':unavailable?'Сохранённая цена требует проверки':'Условия цены и наличие подтверждаются перед оформлением'}</p></div></aside></div>`,true);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total"><span>За всех туристов</span><strong>${o.pricePending?'Цена уточняется':money(o.total)}</strong></div>${unavailable?`<button class="primary" data-action="refresh-hotel" data-id="${h.id}">Найти актуальные туры</button>`:o.quoteError?`<button class="primary" data-action="offer" data-key="${esc(o.key)}">Повторить проверку</button>`:`<button class="primary" data-action="confirm-tour" ${o.loading||o.flightsLoading||o.pricePending||!o.tour?'disabled':''}>Выбрать этот тур ${icon('arrow')}</button>`}`;
}
function openFlightPicker(){
 if(!selectedOffer?.variants?.length)return;flightDraft={base:selectedOffer,id:selectedOffer.flightChoiceId};const o=selectedOffer;
 showModal('flights','Выберите перелёт','ТУДА И ОБРАТНО',`<div class="flight-picker-context"><strong>${esc(hotels.find(h=>h.id===o.hotelId).name)}</strong><span>${rangeText(o.day,o.returnDay)} · ${nightsText(o.nights)} · ${guestsText(o)}</span></div><p class="flight-picker-note">Варианты от туроператора. Стоимость указана за весь тур и всех туристов. Время местное.</p><fieldset class="flight-options"><legend class="sr-only">Пары рейсов туда и обратно</legend>${o.variants.map((v,i)=>{const price=data.variantPrice(o.tour,v);return `<label class="flight-option"><div class="flight-option-heading"><input type="radio" name="flight-pair" value="${i}" ${String(i)===flightDraft.id?'checked':''}><span><strong>Вариант ${i+1}</strong><small>${v.isDefault?'Основной вариант':''}</small></span><span class="flight-pair-delta">${price?money(price):'Цена уточняется'}<small>за весь тур</small></span></div>${legHTML(v.forward,'Туда')+legHTML(v.backward,'Обратно')}<div class="flight-option-footer"><span>Топливный сбор: ${fuelText({...o,flightChoiceId:String(i)})}</span></div></label>`;}).join('')}</fieldset>`,true);
 $('#modal').classList.add('flight-picker-dialog');$('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<div class="flight-selection-total" aria-live="polite"><span>Весь тур за всех туристов</span><strong id="flight-total"></strong><small id="flight-price-change"></small></div><button class="secondary" data-action="modal-back">Отмена</button><button class="primary" data-action="apply-flight">Применить перелёт</button>';updateFlightPreview();
 const checked=$('.flight-options input:checked');if(checked){checked.focus({preventScroll:true});const panel=$('#modal-body'),row=checked.closest('.flight-option');panel.scrollTop=Math.max(0,panel.scrollTop+row.getBoundingClientRect().top-panel.getBoundingClientRect().top-12);}
}
function updateFlightPreview(){if(!flightDraft||modalType!=='flights')return;const o=withFlightPair(flightDraft.base,flightDraft.id);$('#flight-total').textContent=o.pricePending?'Цена уточняется':money(o.total);$('#flight-price-change').textContent=o.pricePending?'Цена этого перелёта пока не подтверждена. Выберите другой вариант.':'Топливный сбор: '+fuelText(o);$('[data-action="apply-flight"]').disabled=o.pricePending;}
function applyFlightPair(){if(!flightDraft)return;const applied=withFlightPair(flightDraft.base,flightDraft.id);if(applied.pricePending){updateFlightPreview();return;}flightDraft=null;modalBack();selectedOffer=applied;renderRealOffer();toast('Перелёт выбран');}
function savedFlightTextPlain(o){if(o?.savedFlightText)return String(o.savedFlightText);const v=flightPairFor(o);return v?[...(v.forward||[]),...(v.backward||[])].map(f=>[data.text(f.company),f.number,f.departure?.time].filter(Boolean).join(' ')).join(' · '):'Рейс пока не выбран';}
function savedFlightText(o){return esc(savedFlightTextPlain(o));}
function refreshHotel(id){
 const o=selectedOffer,h=selectedTourHotel(o);
 if(!o||o.hotelId!==id||!needsRefresh(o)||!h?.legacyIds.length){toast('Не удалось определить варианты отеля. Повторите общий поиск.');return;}
 const next={...structuredClone(o.search),from:o.day,to:o.day,minNights:o.nights,maxNights:o.nights,adults:o.adults,ages:[...o.ages]};
 const exactFilters=defaultFilters();exactFilters.hotelId=id;if(o.meal&&!/уточняется/i.test(o.meal))exactFilters.meals=[o.meal];if(o.operator&&!/уточняется/i.test(o.operator))exactFilters.operators=[o.operator];if(['regular','charter'].includes(o.flight))exactFilters.flight=[o.flight];
 try{data.params(next,h.legacyIds,exactFilters);}catch(error){toast(error.message);return;}
 closeModal();state.search=next;draft=structuredClone(next);draftDestination=null;state.filters=exactFilters;state.selectedDate=null;state.openHotel=id;
 updateSearchUI();updateURL();renderFilters();runSearch({hotelIds:h.legacyIds,exactRefresh:true,exactRefreshTarget:selectedTourOfferSnapshot(o)});
 $('#search-status').scrollIntoView({behavior:scrollBehavior(),block:'start'});
}
function openLeadPreview(){
 const o=selectedOffer,h=selectedTourHotel(o);if(!o?.tour||!h)return;if(o.pricePending){toast('Цена перелёта пока не подтверждена. Выберите другой вариант.');return;}storeSelectedTour(o,h);updateNav();
 showModal('selected-tour','Выбранный тур','ВАШ ВЫБОР',`<div class="verification-tour"><strong>${esc(h.name)}</strong><span>${dateText(o.day)} · ${nightsText(o.nights)} · ${guestsText(o)}</span><span>${esc(o.room)} · ${esc(mealLabel(o))}</span><span>${savedFlightText(o)}</span><strong>${o.pricePending?'Цена уточняется':money(o.total)}</strong></div>${window.AnyTourPrototypeLead.markup()}`);
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML='<button class="secondary" data-action="selected-tour-details">К туру</button>'+window.AnyTourPrototypeLead.action();
 window.AnyTourPrototypeLead.bind(o);
}

let offerView=null,comparisonQuotes=[];
const offerGroupKey=o=>encodeURIComponent(o.room+'|'+o.meal);
function openAllOffers(id,restored=null){
 const h=hotels.find(h=>h.id===id),all=hotelOffers(h);
 offerView={id,flight:'',room:'',meal:'',sort:'price',mode:'list',day:all[0]?.day||state.search.from,nights:all[0]?.nights||state.search.minNights,pair:[],activeVariant:null,differencesOnly:false,open:[],limits:{}};
 if(restored){for(const [name,values] of Object.entries({flight:['','regular','charter','unknown'],room:['',...all.map(o=>o.room)],meal:['',...Object.keys(mealNames)],sort:['price','date'],mode:['list','compare']}))if(values.includes(restored[name]))offerView[name]=restored[name];if(all.some(o=>o.day===restored.day))offerView.day=restored.day;if(all.some(o=>o.nights===restored.nights))offerView.nights=restored.nights;}
 if(restored){if(Array.isArray(restored.pair))offerView.pair=[...new Set(restored.pair.filter(v=>all.some(o=>o.variant===v)))].slice(0,2);if(all.some(o=>o.variant===restored.activeVariant))offerView.activeVariant=restored.activeVariant;offerView.differencesOnly=restored.differencesOnly===true;}
 showModal('all-offers',h.name,'ВЫБЕРИТЕ КОНКРЕТНЫЙ ТУР',`<div class="offer-list-context"><p>Вылет ${state.selectedDate?dateText(state.selectedDate):rangeText(state.search.from,state.search.to)} · ${durationText()} · ${guestsText()}</p><span>${icon('shield')} Цена за всех туристов · сборы уточняются при выборе</span></div><div class="offer-view-switch" role="group" aria-label="Как показать туры"><button data-action="offer-view" data-value="list" aria-pressed="true">Все туры</button><button data-action="offer-view" data-value="compare" aria-pressed="false">Сравнить на даты</button></div><div id="offer-comparison-dates" class="offer-comparison-dates" hidden></div><details class="offer-filter-disclosure" open><summary>Уточнить варианты <span id="offer-local-filter-count"></span></summary><div class="offer-controls"><label>Перелёт<select id="offer-flight"><option value="">Любой</option>${[...new Set(all.map(o=>o.flight))].map(f=>`<option value="${f}">${flightLabel({flight:f})}</option>`).join('')}</select></label><label>Номер<select id="offer-room"><option value="">Любой</option>${[...new Set(all.map(o=>o.room))].map(room=>`<option value="${esc(room)}">${esc(room)}</option>`).join('')}</select></label><label>Питание<select id="offer-meal"><option value="">Любое</option>${[...new Set(all.map(o=>o.meal))].map(m=>`<option value="${m}">${esc(mealNames[m]||m)}</option>`).join('')}</select></label></div></details><div class="offer-list-toolbar"><span id="offer-count" aria-live="polite"></span><label id="offer-sort-label"><span class="sr-only">Сортировать туры</span><select id="offer-sort"><option value="price">Сначала дешевле</option><option value="date">По дате вылета</option></select></label></div><div id="all-offers-list"></div>`,true);
 $('#modal').classList.add('offers-dialog');renderOfferList(true);
}
const comparisonVariantLabel=o=>o.room+' · '+o.operator;
function normalizeComparison(options){
 const ids=options.map(o=>o.variant),pair=(offerView.pair||[]).filter((v,i,a)=>ids.includes(v)&&a.indexOf(v)===i).slice(0,2);
 if(!pair.length&&ids.length){pair.push(ids[0]);if(ids.includes(offerView.activeVariant)&&offerView.activeVariant!==ids[0])pair.push(offerView.activeVariant);}
 if(pair.length<2&&ids.length>1)pair.push([...ids].reverse().find(v=>!pair.includes(v)));
 if(!ids.includes(offerView.activeVariant))offerView.activeVariant=pair[0]??null;
 if(innerWidth<=760&&pair.length&&!pair.includes(offerView.activeVariant))offerView.activeVariant=pair[0];
 offerView.pair=pair;
 return innerWidth<=760?pair.map(v=>options.find(o=>o.variant===v)):options;
}
function renderComparisonFooter(){const o=comparisonQuotes.find(o=>o.variant===offerView?.activeVariant),footer=$('#modal-footer');footer.hidden=!o;if(!o){footer.innerHTML='';return;}footer.innerHTML=`<div class="comparison-footer-summary" aria-live="polite"><div><span>За ${guestsText(o)}</span><strong>${money(o.total)}</strong></div><p>${esc(o.room)}<span>${esc(o.operator)}</span></p></div><button class="secondary" data-action="offer-flights" data-key="${esc(o.key)}">${icon(needsRefresh(o)?'info':'plane')} ${needsRefresh(o)?'Условия тура':'Рейсы и багаж'}</button><button class="primary" data-action="offer" data-key="${esc(o.key)}">${offerActionLabel(o)} ${icon('arrow')}</button>`;}
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
function renderTourComparison(all,filtered){
 const dates=[...new Set(all.map(o=>o.day))].sort();if(!dates.includes(offerView.day))offerView.day=dates[0]||state.search.from;
 const nights=[...new Set(all.filter(o=>o.day===offerView.day).map(o=>o.nights))].sort((a,b)=>a-b);if(!nights.includes(offerView.nights))offerView.nights=nights[0]||state.search.minNights;
 const options=filtered.filter(o=>o.day===offerView.day&&o.nights===offerView.nights).sort((a,b)=>a.total-b.total);comparisonQuotes=options;
 const visible=normalizeComparison(options),benchmark=visible.reduce((a,o)=>!a||o.total<a.total?o:a,null),mobile=innerWidth<=760;
 const fact=(key,label,value)=>{const different=new Set(visible.map(o=>o[key])).size>1;return offerView.differencesOnly&&visible.length>1&&!different?'':`<div class="${different?'fact-different':''}"><dt>${label}</dt><dd>${esc(value)}</dd></div>`;};
 $('#offer-comparison-dates').innerHTML=`<div class="comparison-date-fields"><label>Дата вылета<select id="compare-offer-day">${dates.map(day=>`<option value="${day}" ${day===offerView.day?'selected':''}>${dateText(day)}</option>`).join('')}</select></label><label>Ночей<select id="compare-offer-nights">${nights.map(n=>`<option value="${n}" ${n===offerView.nights?'selected':''}>${nightsText(n)}</option>`).join('')}</select></label></div><p>${esc(state.search.origin)} · ${guestsText()} · цена за весь тур</p>`;
 $('#offer-count').textContent=`${options.length} вариантов · ${dateText(offerView.day)} · ${nightsText(offerView.nights)}`;
 const controls=options.length>1?`<div class="comparison-pair-fields">${offerView.pair.map((v,i)=>`<label>${i?'Справа':'Слева'}<select id="comparison-pair-${i}">${options.map(o=>`<option value="${o.variant}" ${o.variant===v?'selected':''}>${esc(comparisonVariantLabel(o))}</option>`).join('')}</select></label>`).join('')}</div>`:'';
 $('#all-offers-list').innerHTML=options.length?`<div class="comparison-options-tools"><label class="comparison-differences-switch"><input type="checkbox" id="tour-differences-only" ${offerView.differencesOnly?'checked':''}> Только отличия</label></div>${controls}<div class="tour-comparison-grid" data-count="${options.length}" data-visible-count="${visible.length}">${options.map(o=>`<article class="tour-comparison-option ${o===benchmark?'best-price':''} ${offerView.activeVariant===o.variant?'focused-option':''} ${mobile&&!offerView.pair.includes(o.variant)?'comparison-hidden':''}" data-variant="${o.variant}" style="--comparison-order:${mobile?offerView.pair.indexOf(o.variant):options.indexOf(o)}"><label class="comparison-focus-choice"><input type="radio" name="comparison-focus" value="${o.variant}" ${offerView.activeVariant===o.variant?'checked':''}><span>Этот вариант</span></label><div class="comparison-option-top"><span>${o===benchmark?'Минимальная цена':'Вариант тура'}</span>${operatorBadge(o.operator)}</div><h3>${esc(o.room)}</h3><dl class="comparison-tour-facts">${fact('meal','Питание',o.meal)}${fact('operator','Туроператор',o.operator)}${fact('placement','Размещение',o.placement||'Уточняется')}${fact('flight','Тип перелёта',flightLabel(o))}<div><dt>Вылет — возвращение</dt><dd>${rangeText(o.day,o.returnDay)}</dd></div></dl><div class="comparison-option-price"><span>За всех туристов</span><strong>${money(o.total)}</strong><small>${priceNote(o)}</small></div><div class="comparison-option-actions"><button class="primary" data-action="offer" data-key="${esc(o.key)}">${offerActionLabel(o)} ${icon('arrow')}</button><button class="secondary" data-action="offer-flights" data-key="${esc(o.key)}">${icon(needsRefresh(o)?'info':'plane')} ${needsRefresh(o)?'Условия тура':'Рейсы и багаж'}</button></div></article>`).join('')}</div>`:'<div class="destination-empty"><h3>Нет вариантов на эти даты</h3><p>Измените дату или фильтры.</p></div>';renderComparisonFooter();
}
function renderOfferList(reset=false){
 const h=hotels.find(h=>h.id===offerView.id),all=hotelOffers(h),filtered=all.filter(o=>(!offerView.flight||o.flight===offerView.flight)&&(!offerView.room||o.room===offerView.room)&&(!offerView.meal||o.meal===offerView.meal));
 const sorted=[...filtered].sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total:a.total-b.total||a.day.localeCompare(b.day));
 const groups=[...new Set(sorted.map(offerGroupKey))].map(key=>({key,offers:sorted.filter(o=>offerGroupKey(o)===key)}));
 if(reset){offerView.open=groups.length?[groups[0].key]:[];offerView.limits={};}
 for(const name of ['flight','room','meal','sort'])$('#offer-'+name).value=offerView[name];
 const comparing=offerView.mode==='compare',wasComparing=$('#modal').classList.contains('tour-comparison-dialog');if(comparing!==wasComparing)$('.offer-filter-disclosure').open=!comparing;$('#modal').classList.toggle('tour-comparison-dialog',comparing);$('#offer-comparison-dates').hidden=!comparing;$('#offer-sort-label').hidden=comparing;$$('[data-action="offer-view"]').forEach(b=>b.setAttribute('aria-pressed',b.dataset.value===offerView.mode));
 const localCount=['flight','room','meal'].filter(k=>offerView[k]).length;$('#offer-local-filter-count').textContent=localCount?'('+localCount+')':'';$('.offer-list-context p').textContent=comparing?`${esc(state.search.origin)} → ${esc(h.resort)} · ${guestsText()}`:`Вылет ${state.selectedDate?dateText(state.selectedDate):rangeText(state.search.from,state.search.to)} · ${durationText()} · ${guestsText()}`;
 if(comparing){renderTourComparison(all,filtered);rememberUIRoute();return;}
 comparisonQuotes=[];$('#modal-footer').hidden=true;$('#modal-footer').innerHTML='';rememberUIRoute();
 $('#offer-count').textContent=`${filtered.length} вариантов · ${groups.length} ${groups.length===1?'тип размещения':'типа размещения'}`;
 $('#all-offers-list').innerHTML=groups.length?groups.map(({key,offers})=>{
  const first=offers[0],min=Math.min(...offers.map(o=>o.total)),open=offerView.open.includes(key),limit=offerView.limits[key]||4;
  const rows=offers.slice(0,limit).map(o=>{const reference=Math.min(...all.filter(x=>x.day===o.day&&x.nights===o.nights).map(x=>x.total)),delta=o.total-reference;return `<div class="offer grouped-offer" data-offer-key="${o.key}"><div class="offer-departure"><strong>${dateText(o.day)} → ${dateText(o.returnDay)}</strong><small>${nightsText(o.nights)} · ${guestsText(o)}</small><small>Город вылета: ${esc(o.origin)}</small></div><div class="offer-flight-details"><span class="flight-tag ${o.flight}">${flightLabel(o)}</span>${operatorBadge(o.operator)}<small>${needsRefresh(o)?'Сохранённая цена · требует проверки':'Рейсы и багаж — при выборе'}</small></div><div class="offer-price"><strong>${money(o.total)}</strong>${delta?`<small class="offer-delta">+${money(delta)} к минимуму на эти даты и ${nightsText(o.nights)}</small>`:'<small class="offer-lowest">Минимум на эти даты и ночи</small>'}<button class="primary" data-action="offer" data-key="${o.key}">${offerActionLabel(o)} ${icon('arrow')}</button><button class="text-button compare-tour-link" data-action="compare-tour" data-key="${o.key}">Сравнить на эти даты</button></div></div>`}).join('');
  return `<section class="offer-group"><button class="offer-group-heading" data-action="offer-group" data-value="${key}" aria-expanded="${open}" aria-controls="group-${key}"><span><strong>${esc(first.room)}</strong><small>Условия предложения · ${esc(mealLabel(first))}</small></span><span class="offer-group-min"><strong>от ${money(min)}</strong><small>${offers.length} вариантов <span class="rotate-arrow ${open?'up':''}">⌄</span></small></span></button><div id="group-${key}" class="offer-group-body" ${open?'':'hidden'}>${rows}${offers.length>limit?`<button class="text-button group-more" data-action="group-more" data-value="${key}">Ещё варианты (${offers.length-limit}) ${icon('arrow')}</button>`:''}</div></section>`;
 }).join(''):`<div class="destination-empty"><h3>Нет такого сочетания</h3><p>Измените номер, питание или перелёт. Даты и туристы сохранятся.</p><button class="secondary" data-action="reset-offer-filters">Сбросить фильтры туров</button></div>`;
}
let verifiedOffer=null;
function cancelVerification(){}
function confirmTour(){if(selectedOffer&&needsRefresh(selectedOffer)){refreshHotel(selectedOffer.hotelId);return;}openLeadPreview();}
const selectedTourKey='anytour.real.selected-tour.v1',selectedTourTTL=24*60*60*1000;
let savedSelection=null,savedSelectionHotel=null,savedSelectionObservedAt=0,removedSelectedTour=null,removedSelectedTourHotel=null,removedSelectedTourObservedAt=0,selectedTourExpiryTimer=null;
function selectedTourHotel(o=savedSelection){return hotels.find(h=>h.id===o?.hotelId)||(savedSelectionHotel?.id===o?.hotelId?savedSelectionHotel:null);}
function selectedTourOfferSnapshot(o){
 const search=o?.search||{},day=String(o?.day||''),nights=Number(o?.nights),adults=Number(o?.adults),hotelId=Number(o?.hotelId),total=Number(o?.total);
 if(!/^\d{4}-\d{2}-\d{2}$/.test(day)||!Number.isSafeInteger(hotelId)||hotelId<1||!Number.isInteger(nights)||nights<1||!Number.isInteger(adults)||adults<1||!Number.isFinite(total)||total<=0)return null;
 const ages=Array.isArray(o.ages)?o.ages.filter(age=>Number.isInteger(age)&&age>=0&&age<=17).slice(0,3):[];
 return {key:String(o.key||''),hotelId,day,nights,returnDay:addDays(day,nights),variant:Number.isInteger(o.variant)?o.variant:0,total,room:String(o.room||'Номер уточняется'),placement:String(o.placement||''),adults,ages,origin:String(o.origin||search.origin||''),meal:String(o.meal||'Питание уточняется'),operator:String(o.operator||'Туроператор уточняется'),flight:['regular','charter'].includes(o.flight)?o.flight:'unknown',cached:true,provider:String(o.provider||'local'),raw:{selectionEnabled:false},search:{origin:String(search.origin||o.origin||''),country:String(search.country||''),from:String(search.from||day),to:String(search.to||day),minNights:Number(search.minNights)||nights,maxNights:Number(search.maxNights)||nights,adults,ages:[...ages]},fuel:Number.isFinite(Number(o.fuel))&&Number(o.fuel)>=0?Number(o.fuel):null,flightChoiceId:null,tour:null,variants:[],savedFlightText:savedFlightTextPlain(o),loading:false,flightsLoading:false,pricePending:false};
}
function selectedTourHotelSnapshot(h){if(!h||!Number.isSafeInteger(Number(h.id)))return null;return {id:Number(h.id),name:String(h.name||'Выбранный отель'),resort:String(h.resort||''),country:String(h.country||''),stars:Number(h.stars)||0,rating:Number.isFinite(Number(h.rating))?Number(h.rating):null,photos:Array.isArray(h.photos)?h.photos.filter(x=>typeof x==='string').slice(0,8):[],legacyIds:Array.isArray(h.legacyIds)?h.legacyIds.filter(x=>Number.isSafeInteger(Number(x))&&Number(x)>0).map(Number):[],raw:{arrival:data.text(h.raw?.arrival)}};}
function selectedTourRecord(){const offer=selectedTourOfferSnapshot(savedSelection),hotel=selectedTourHotelSnapshot(selectedTourHotel());return offer&&hotel&&savedSelectionObservedAt?{version:1,observedAt:savedSelectionObservedAt,offer,hotel}:null;}
function scheduleSelectedTourExpiry(){clearTimeout(selectedTourExpiryTimer);if(!savedSelectionObservedAt)return;const delay=savedSelectionObservedAt+selectedTourTTL-Date.now();if(delay<=0){expireSelectedTour();return;}selectedTourExpiryTimer=setTimeout(()=>{expireSelectedTour();updateNav();},delay+25);}
function expireSelectedTour(){clearTimeout(selectedTourExpiryTimer);selectedTourExpiryTimer=null;savedSelection=null;savedSelectionHotel=null;savedSelectionObservedAt=0;clearStored(selectedTourKey);}
function persistSelectedTour(){const record=selectedTourRecord();if(record)saveStored(selectedTourKey,record);else clearStored(selectedTourKey);scheduleSelectedTourExpiry();}
function storeSelectedTour(o,h,observedAt=Date.now()){savedSelection=o;savedSelectionHotel=selectedTourHotelSnapshot(h);savedSelectionObservedAt=observedAt;persistSelectedTour();}
function restoreSelectedTour(){const record=getStored(selectedTourKey,null),now=Date.now(),observedAt=Number(record?.observedAt),offer=selectedTourOfferSnapshot(record?.offer),hotel=selectedTourHotelSnapshot(record?.hotel);if(record?.version!==1||!Number.isFinite(observedAt)||observedAt>now+60000||now-observedAt>=selectedTourTTL||!offer||!hotel||offer.hotelId!==hotel.id){expireSelectedTour();return;}savedSelection=offer;savedSelectionHotel=hotel;savedSelectionObservedAt=observedAt;scheduleSelectedTourExpiry();}
function demoteSavedTour(){if(!readSelectedTour())return;savedSelection=selectedTourOfferSnapshot(savedSelection);persistSelectedTour();}
function sameSelectedTourConditions(o,target){return !!o&&!!target&&o.hotelId===target.hotelId&&o.day===target.day&&o.nights===target.nights&&o.adults===target.adults&&JSON.stringify(o.ages||[])===JSON.stringify(target.ages||[])&&o.room===target.room&&o.meal===target.meal&&o.operator===target.operator&&o.flight===target.flight;}
function readSelectedTour(){if(savedSelectionObservedAt&&Date.now()-savedSelectionObservedAt>=selectedTourTTL)expireSelectedTour();return savedSelection;}
function openSelectedTour(){if(savedSelection){selectedOffer=savedSelection;renderRealOffer();return;}showModal('saved-tour','Мой тур','ВАШ ВЫБОР','<div class="saved-empty"><h3>Тур пока не выбран</h3><p>Откройте предложение и выберите перелёт.</p><button class="primary" data-action="close-modal">К отелям</button></div>');}
function restoreSelectedSearch(){closeModal();editSearch();}
function removeSelectedTour(){removedSelectedTour=savedSelection;removedSelectedTourHotel=savedSelectionHotel;removedSelectedTourObservedAt=savedSelectionObservedAt;expireSelectedTour();updateNav();openSelectedTour();}
function undoSelectedTour(){if(!removedSelectedTour||Date.now()-removedSelectedTourObservedAt>=selectedTourTTL)return; savedSelection=removedSelectedTour;savedSelectionHotel=removedSelectedTourHotel;savedSelectionObservedAt=removedSelectedTourObservedAt;removedSelectedTour=null;removedSelectedTourHotel=null;removedSelectedTourObservedAt=0;persistSelectedTour();updateNav();openSelectedTour();}
function completeTour(o){const h=selectedTourHotel(o);if(!h)return;storeSelectedTour(o,h);updateNav();openLeadPreview();}

let compareView={onlyDifferences:false,pair:[]};
function savedContext(){return `<div class="saved-context"><strong>${esc(state.search.origin)} → ${esc(countryNames[state.search.country]||'')}</strong><span>Вылет ${state.selectedDate?dateText(state.selectedDate):rangeText(state.search.from,state.search.to)} · ${durationText()} · ${guestsText()}</span><small>Цены за всех туристов${filterCount()||state.onlyFavorites?' с учётом текущих фильтров':''}. Актуальность и состав цены — в условиях предложения.</small></div>`;}
function savedAvailability(h){const offers=hotelOffers(h);return {hotel:h,offers,offer:offers[0],reason:h.country!==state.search.country?'Другое направление':'Нет туров по текущим фильтрам'};}
function savedRecovery(h){return `<button class="secondary saved-recovery" data-action="show-hotel" data-id="${h.id}">${h.country!==state.search.country?'Новый поиск':'Снять фильтры'}</button>`;}
function refreshSavedView(type,focusSelector){const scroll=$('#modal-body').scrollTop;if(type==='compare')renderCompare();else renderFavorites();$('#modal-body').scrollTop=scroll;if(focusSelector)($(focusSelector)||$('#modal-body button')||$('#modal-title')).focus({preventScroll:true});}
function toggleFavorite(id){const index=state.favorites.indexOf(id);if(index<0)state.favorites.push(id);else state.favorites.splice(index,1);saveStored('anytour.real.favorites.v1',state.favorites);updateNav();$$(`[data-action="favorite"][data-id="${id}"]`).forEach(b=>{b.classList.toggle('active',index<0);b.setAttribute('aria-pressed',index<0);const h=hotels.find(h=>h.id===id);b.setAttribute('aria-label',`${index<0?'Убрать из избранного':'В избранное'}: ${esc(h.name)}`)});if(state.onlyFavorites)renderResults({keepFilters:true});if(modalType==='favorites')refreshSavedView('favorites',`#modal-body [data-action="favorite"][data-id="${id}"]`);toast(index<0?'Отель добавлен в избранное':'Отель удалён из избранного');}
function toggleCompare(id){const index=state.compare.indexOf(id);if(index<0){if(state.compare.length>=3){const message='Можно сравнить до 3 отелей. Уберите один, чтобы добавить другой.';if(modalType==='favorites'&&$('#modal').open&&$('#shortlist-status'))$('#shortlist-status').textContent=message;else toast(message);return}state.compare.push(id)}else state.compare.splice(index,1);saveStored('anytour.real.compare.v1',state.compare);updateNav();$$(`[data-action="toggle-compare"][data-id="${id}"]`).forEach(b=>{b.classList.toggle('active',index<0);b.setAttribute('aria-pressed',index<0);b.innerHTML=`${icon(index<0?'check':'compare')}${index<0?'В сравнении':'Сравнить отель'}`});if(modalType==='compare'||modalType==='favorites')refreshSavedView(modalType,`#modal-body [data-action="toggle-compare"][data-id="${id}"]`);else toast(index<0?`В сравнении ${state.compare.length} из 3 отелей`:'Отель убран из сравнения');}
function openCompare(){showModal('compare','Сравнение отелей','ВАШ КОРОТКИЙ СПИСОК','',true);renderCompare();}
function renderCompare(){
 const hs=state.compare.map(id=>hotels.find(h=>h.id===id)).filter(Boolean);$('#modal').classList.add('comparison-dialog');$('#modal-footer').hidden=true;
 if(!hs.length){$('#modal-body').innerHTML='<div class="saved-empty"><h3>Какие отели сравним?</h3><p>Добавьте до трёх отелей из выдачи.</p></div>';return;}
 compareView.pair=compareView.pair.filter(id=>hs.some(h=>h.id===id));for(const h of hs)if(compareView.pair.length<2&&!compareView.pair.includes(h.id))compareView.pair.push(h.id);
 const entries=hs.map(savedAvailability),mobile=innerWidth<=760,visible=entries.filter(e=>!mobile||compareView.pair.includes(e.hotel.id));
 const hide=id=>mobile&&!compareView.pair.includes(id)?' mobile-hidden':'';
 const row=(key,label,get)=>{const diff=new Set(visible.map(get)).size>1;return compareView.onlyDifferences&&!diff?'':`<tr class="compare-fact ${diff?'is-different':''}"><th scope="row">${label}</th>${entries.map(e=>`<td class="${hide(e.hotel.id)}">${esc(get(e))}</td>`).join('')}</tr>`;};
 const controls=hs.length===3?`<div class="compare-pair-controls"><p>Выберите два отеля для сравнения</p>${['left','right'].map((side,i)=>`<label>${i?'Второй':'Первый'} отель<select id="compare-${side}">${hs.map(h=>`<option value="${h.id}" ${compareView.pair[i]===h.id?'selected':''}>${esc(h.name)}</option>`).join('')}</select></label>`).join('')}</div>`:'';
 $('#modal-body').innerHTML=`${savedContext()}<div class="compare-controls"><label class="differences-toggle"><input type="checkbox" id="compare-differences" ${compareView.onlyDifferences?'checked':''}> Только отличия</label></div>${controls}<p class="compare-price-basis">Минимумы среди найденных предложений. Точные даты и условия приведены для каждого отеля.</p><div class="comparison-scroll"><table class="comparison" data-count="${hs.length}"><thead><tr><th>Ваш выбор</th>${entries.map(({hotel:h,offer:o})=>`<th class="compare-hotel${hide(h.id)}"><div class="compare-hotel-card"><button class="compare-photo" data-action="gallery" data-id="${h.id}"><img src="${esc(photoUrl(h))}" alt="${esc(h.name)}"></button><button class="icon-button compare-remove" data-action="toggle-compare" data-id="${h.id}" aria-label="Убрать ${esc(h.name)}">${icon('x')}</button><span class="compare-stars">${h.stars?h.stars+' ★':'Категория не указана'}</span><button class="compare-name" data-action="hotel-details" data-id="${h.id}">${esc(h.name)}</button><div class="compare-price">${o?`<strong>от ${money(o.total)}</strong><span>${priceNote(o)}</span>`:'Нет туров по текущим условиям'}</div>${o?`<button class="primary" data-action="all-offers" data-id="${h.id}">Туры отеля</button>`:''}</div></th>`).join('')}</tr></thead><tbody>${row('resort','Курорт',e=>e.hotel.resort||'Не указан')}${row('rating','Рейтинг / 5',e=>ratingText(e.hotel))}${row('date','Вылет',e=>e.offer?dateText(e.offer.day):'—')}${row('nights','Ночей',e=>e.offer?.nights||'—')}${row('room','Номер',e=>e.offer?.room||'—')}${row('meal','Питание',e=>e.offer?.meal||'—')}${row('operator','Туроператор',e=>e.offer?.operator||'—')}</tbody></table></div>`;
}
function openFavorites(){showModal('favorites','Избранные отели','ВАШ КОРОТКИЙ СПИСОК','',true);renderFavorites();}
function renderFavorites(){
 const entries=state.favorites.map(id=>savedAvailability(hotels.find(h=>h.id===id)));
 $('#modal').classList.add('favorites-dialog');$('#modal-kicker').textContent=`ИЗБРАННОЕ · ${entries.length} ${entries.length===1?'ОТЕЛЬ':entries.length<5?'ОТЕЛЯ':'ОТЕЛЕЙ'}`;
 if(!entries.length){$('#modal-body').innerHTML=`<div class="saved-empty">${icon('heart')}<h3>Сохраните понравившиеся отели</h3><p>Нажмите на сердечко на фотографии. Здесь можно будет сравнить цены и выбрать тур.</p><button class="primary" data-action="close-modal">К отелям</button></div>`;$('#modal-footer').hidden=true;return;}
 $('#modal-body').innerHTML=`${savedContext()}<p class="saved-guidance">В сравнении ${state.compare.length} из 3. Список сохраняется в этом браузере.</p><div class="favorite-list">${entries.map(({hotel:h,offer:o,offers,reason})=>`<article class="favorite-item" data-saved-hotel="${h.id}"><button class="favorite-photo" data-action="gallery" data-id="${h.id}" aria-label="Фотографии ${esc(h.name)}"><img src="${esc(photoUrl(h))}" alt="Фото отеля"></button><div class="favorite-info"><span class="favorite-stars">${h.stars} ★ <span>${ratingText(h)} / 5</span></span><h3><button data-action="hotel-details" data-id="${h.id}">${esc(h.name)}</button></h3><p>${esc(h.resort)}, ${esc(countryNames[h.country]||'')}</p></div><button class="icon-button favorite-remove" data-action="favorite" data-id="${h.id}" aria-label="Удалить ${esc(h.name)} из избранного">${icon('x')}</button><div class="favorite-summary">${o?`<strong class="saved-price"><small>от </small>${money(o.total)}</strong><span>За ${guestsText()} · сборы уточняются</span><p>${dateText(o.day)} → ${dateText(o.returnDay)} · ${nightsText(o.nights)}</p><p>${esc(mealLabel(o))} · ${flightLabel(o)}</p>`:`<strong class="saved-unavailable">${reason}</strong><p>Отель остаётся в избранном.</p>`}</div><div class="favorite-actions"><button class="compare-btn ${state.compare.includes(h.id)?'active':''}" data-action="toggle-compare" data-id="${h.id}" aria-pressed="${state.compare.includes(h.id)}">${icon(state.compare.includes(h.id)?'check':'compare')}${state.compare.includes(h.id)?'В сравнении':'Сравнить отель'}</button>${o?`<button class="primary" data-action="all-offers" data-id="${h.id}">Туры отеля ${icon('arrow')}</button>`:savedRecovery(h)}</div></article>`).join('')}</div>${entries.some(e=>!e.offer)?'<p class="saved-recovery-hint">Новый поиск меняет направление и снимает фильтры. Даты и туристы сохраняются.</p>':''}`;
 $('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<p id="shortlist-status" class="error-text" role="status"></p><button class="secondary" data-action="only-favorites">Избранное в выдаче</button><button class="primary" data-action="compare" ${!state.compare.length?'disabled':''}>Сравнить ${state.compare.length||''} ${icon('arrow')}</button>`;
}
function openFilters(){if(innerWidth>1100){$('#results').classList.add('filters-requested');$('#filter-panel').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#hotel-query').focus({preventScroll:true});return}if(filterDraft)return;enterUIHistory();filterDraft=structuredClone(appliedFilterModel());renderFilters();updateDrawerPreview();$('#filter-panel').classList.add('open');$('#filter-panel').setAttribute('role','dialog');$('#filter-panel').setAttribute('aria-modal','true');$('#filter-backdrop').hidden=false;document.body.style.overflow='hidden';$('main>.search-section').inert=true;$('.header').inert=true;$('.results-heading').inert=true;$('.results-main').inert=true;$('.footer').inert=true;$('.mobile-bottom').inert=true;$('#compare-tray').inert=true;$('#compact-search').inert=true;$('#filter-panel').scrollTop=0;$('.mobile-close').focus({preventScroll:true});queueMicrotask(rememberUIRoute);}
function closeFilters({apply=false,fromHistory=false}={}){const wasOpen=$('#filter-panel').classList.contains('open');if(!wasOpen)return;leaveUIHistory(fromHistory);const next=apply?filterDraft:null;filterDraft=null;drawerSuggestions=[];$('#filter-panel').classList.remove('open');$('#filter-panel').removeAttribute('role');$('#filter-panel').removeAttribute('aria-modal');$('#filter-backdrop').hidden=true;document.body.style.overflow='';$$('[inert]').forEach(e=>e.inert=false);if(next){pageReturn=null;state.filters=next.filters;state.onlyFavorites=next.onlyFavorites;state.selectedDate=next.selectedDate;state.openHotel=null;syncFilters();const pristine=!state.hasSearched&&!state.onlyFavorites;$(pristine?'#search':'#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$(pristine?'.search-submit':'#results').focus({preventScroll:true})}else{renderFilters();restorePageReturn();}}
$('#filter-backdrop').addEventListener('click',closeFilters);
function selectDate(day,fromMonth=false){if(fromMonth&&(day<state.search.from||day>state.search.to)){state.search.from=day;state.search.to=day;draft.from=day;draft.to=day}state.selectedDate=day;state.openHotel=null;updateSearchUI();renderResults({keepFilters:true});updateURL();if(fromMonth)closeModal();}
function resetFilters(){state.filters=defaultFilters();state.onlyFavorites=false;state.selectedDate=null;syncFilters();updateURL();}
function clearSearchTimers(){data.stop();clearTimeout(searchTimer);clearTimeout(searchStageTimer);}
function stopSearch(){clearSearchTimers();searchResponse.pending=false;searchResponse.phase='cancelled';renderResults({keepFilters:true});}
function renderSearchStatus(items,total){
 const r=searchResponse,node=$('#search-status'),more=$('#search-more'),exactMatch=r.exactRefreshTarget&&items.some(item=>item.offers.some(o=>!needsRefresh(o)&&sameSelectedTourConditions(o,r.exactRefreshTarget))),noCurrent=r.phase==='complete'&&r.exactRefresh&&!exactMatch;
 const providerStates=Object.values(r.providers||{}),providerPending=providerStates.includes('loading'),providerError=providerStates.some(status=>status==='error'||status==='partial'),partialError=providerError||r.databaseError;
 more.hidden=!state.hasSearched||r.pending||!r.canContinue;
 more.innerHTML=more.hidden?'':`<div class="search-status-top"><div><h3>Продолжить подбор</h3><p>${r.resultLimitReached?'Получена большая выборка. Уточните условия, чтобы увидеть другие предложения.':'Запросите ещё варианты. Найденные туры и выбранные фильтры сохранятся.'}</p></div></div><div class="search-status-actions"><button class="primary" data-action="continue-search">${r.retryRead?'Проверить результат':'Продолжить поиск'}</button></div>`;
 node.hidden=!state.hasSearched||r.phase==='complete'&&!noCurrent&&!providerPending&&!partialError;
 if(node.hidden)return;
 const title=noCurrent?'Актуальные варианты пока не найдены':r.pending?(r.continued?'Продолжаем поиск':'Ищем предложения'):providerPending?'Дополняем найденные туры':partialError?'Получены не все предложения':r.phase==='cancelled'?'Поиск остановлен':'Поиск не завершён';
 const message=noCurrent?'Тот же тур — отель, дата, ночи, состав туристов, номер, питание, туроператор и тип перелёта — пока не найден. Другие предложения ниже показаны только как альтернативы и не заменяют сохранённый выбор.':r.pending?(r.message||'Предложения добавляются по мере получения.'):providerPending?'Часть туроператоров ещё отвечает. Уже найденные туры доступны ниже.':partialError?'Часть предложений сейчас недоступна. Уже найденные туры сохранены; можно выбрать их или повторить поиск.':r.message||'Поиск можно повторить с выбранными условиями.';
 node.innerHTML=`<div class="search-status-top"><div><h3>${r.pending||providerPending?'<span class="search-progress-spinner" aria-hidden="true"></span>':icon('info')}${title}</h3><p>${esc(message)}</p></div></div><div class="search-status-actions">${r.pending?'<button class="secondary" data-action="stop-search">Остановить поиск</button>':partialError||!r.canContinue?'<button class="primary" data-action="retry-search">Повторить поиск</button>':''}<button class="text-button" data-action="edit-search">Изменить условия</button></div>`;
 if(!items.length&&r.pending)$('#cards').innerHTML=Array.from({length:2},()=>'<div class="search-skeleton" aria-hidden="true"><div class="skeleton-photo"></div><div class="skeleton-lines"><i></i><i></i><i></i></div></div>').join('');
}

function prepareSearchRun(options={}){
 if(state.filters.hotelId&&!destinationHotel(state.filters.hotelId)?.legacyIds.length){state.hasSearched=false;editSearch();renderResults();updateSearchUI();return null;}
 resultCalendar.controller?.abort();resultCalendar={key:null,hotels:[],observations:[],phase:'idle',controller:null};
 selectionGeneration++;selectedOffer=null;demoteSavedTour();removedSelectedTour=null;removedSelectedTourHotel=null;removedSelectedTourObservedAt=0;window.AnyTourPrototypeLead.reset();updateNav();state.hasSearched=true;
 const key=searchKey(state.search);searchResponse={key,phase:'loading',operators:[],pending:true,exactRefresh:options.exactRefresh===true};collapseSearch();
 if(options.exactRefreshTarget)searchResponse.exactRefreshTarget=selectedTourOfferSnapshot(options.exactRefreshTarget);
 return {search:state.search,filters:structuredClone(state.filters),hotelIds:options.hotelIds||(state.filters.hotelId?destinationHotel(state.filters.hotelId)?.legacyIds||[]:[]),response:searchResponse};
}
function mergeSearchResults(event){
 const incoming=new Map(event.hotels.map(h=>[h.id,h]));
 hotels=hotels.filter(h=>state.favorites.includes(h.id)||state.compare.includes(h.id)).map(h=>({...h,offers:[]}));
 hotels=[...new Map([...hotels,...incoming.values()].map(h=>[h.id,h])).values()];
 operators.splice(0,operators.length,...new Set(hotels.flatMap(h=>h.offers.map(o=>o.operator))));
 hotels.forEach(h=>h.offers.forEach(o=>{mealNames[o.meal]=o.meal;}));
}
function commitSearchDraft(){
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
 prepare:prepareSearchRun,
 currentKey:()=>searchKey(state.search),
 onResults:mergeSearchResults,
 afterEvent:()=>{renderResults();updateSearchUI();if(modalType==='dates')refreshCalendarPrices();},
 afterStart:()=>renderResults(),
 afterFailure:()=>renderResults(),
 commit:commitSearchDraft,
 afterSubmit:()=>$('#search-status').scrollIntoView({behavior:scrollBehavior(),block:'start'})
});
function runSearch(options={}){return searchLifecycle.run(options);}
searchLifecycle.bind();
document.addEventListener('change',e=>{const t=e.target;
 if(t.name==='flight-pair'&&flightDraft){flightDraft.id=t.value;updateFlightPreview();}
 if(t.name==='comparison-focus'){selectComparisonVariant(+t.value);}
 if(t.id==='tour-differences-only'){offerView.differencesOnly=t.checked;refreshTourComparison(t.id);}
 if(t.id==='comparison-pair-0'||t.id==='comparison-pair-1'){const side=t.id==='comparison-pair-0'?0:1,other=1-side,old=offerView.pair[side],variant=+t.value;offerView.pair[side]=variant;if(offerView.pair[other]===variant)offerView.pair[other]=old;if(!offerView.pair.includes(offerView.activeVariant))offerView.activeVariant=variant;refreshTourComparison(t.id);}
 if(t.id==='origin'){draft.origin=t.value;loadCountries(t.value);}
 if(t.id==='compare-differences'){compareView.onlyDifferences=t.checked;refreshSavedView('compare','#compare-differences')}
 if(t.id==='compare-left'||t.id==='compare-right'){const side=t.id==='compare-left'?0:1,other=1-side,old=compareView.pair[side],id=+t.value;compareView.pair[side]=id;if(compareView.pair[other]===id)compareView.pair[other]=old;refreshSavedView('compare','#'+t.id)}
 if(['offer-flight','offer-room','offer-meal','offer-sort'].includes(t.id)){offerView[t.id.replace('offer-','')]=t.value;renderOfferList(true)}
 if(t.id==='compare-offer-day'||t.id==='compare-offer-nights'){const scroll=$('#modal-body').scrollTop;if(t.id==='compare-offer-day')offerView.day=t.value;else offerView.nights=+t.value;renderOfferList();$('#'+t.id)?.focus({preventScroll:true});$('#modal-body').scrollTop=scroll;}
 if(t.id==='sort'){state.sort=t.value;renderResults({keepFilters:true})}
 if(t.dataset.filter){const arr=editingFilterModel().filters[t.dataset.filter],idx=arr.indexOf(t.value);if(t.checked&&idx<0)arr.push(t.value);if(!t.checked&&idx>=0)arr.splice(idx,1);filterEdited()}
 if(t.dataset.filterBool){editingFilterModel().filters[t.dataset.filterBool]=t.checked;filterEdited()}
 if(['min-price','max-price'].includes(t.id)){
  const f=editingFilterModel().filters,isMax=t.id==='max-price',value=isMax&&t.value.trim()===''?null:Number(t.value);
  if(t.validity?.badInput||value!==null&&(!Number.isFinite(value)||value<0)){syncBudgetControls(f);toast('Введите неотрицательную сумму или оставьте верхнюю границу пустой.');return;}
  f[isMax?'max':'min']=value;if(f.max!==null&&f.min>f.max){if(isMax)f.min=f.max;else f.max=f.min;}
  syncBudgetControls(f);filterEdited();
 }
 if(t.dataset.childAge!==undefined)guestDraft.ages[+t.dataset.childAge]=t.value===''?null:+t.value;
 if(t.hasAttribute('data-meal-choice')){if(!t.value)mealDraft=[];else{mealDraft=t.checked?[...new Set([...mealDraft,t.value])]:mealDraft.filter(v=>v!==t.value)}$$('[data-meal-choice]').forEach(c=>c.checked=c.value?mealDraft.includes(c.value):!mealDraft.length)}
 if(t.id==='date-from'||t.id==='date-to'){dateDraft[t.id==='date-from'?'from':'to']=t.value;dateDraft.phase=0;dateDraft.flex=0;dateAnchor=dateDraft.from;if(dateDraft.from&&dateDraft.to)updateDateSelection();}
});
document.addEventListener('input',e=>{if(e.target.id==='destination-query')lookupDestination();if(e.target.id==='hotel-query'){editingFilterModel().filters.q=e.target.value;filterEdited()}if(e.target.id==='price-range'){const f=editingFilterModel().filters;f.max=Number(e.target.value)>=Number(e.target.max)?null:Number(e.target.value);if(f.max!==null&&f.min>f.max)f.min=f.max;$('#max-price').value=f.max??'';$('#min-price').value=f.min;e.target.setAttribute('aria-valuetext',budgetLabel(f));filterEdited()}});
document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(!b||b.disabled)return;actionTrigger=b;queueMicrotask(()=>{if(actionTrigger===b)actionTrigger=null;});const action=b.dataset.action,id=+b.dataset.id;
 switch(action){
 case 'choose-flight':openFlightPicker();break;case 'apply-flight':applyFlightPair();break;
 case 'selected-tour':openSelectedTour();break;
 case 'selected-tour-details':{const saved=readSelectedTour();if(saved){selectedOffer=saved;renderRealOffer();}break;}
 case 'selected-tour-alternatives':restoreSelectedSearch();break;
 case 'remove-selected-tour':removeSelectedTour();break;
 case 'undo-selected-tour':undoSelectedTour();break;
 case 'retry-search':if(!searchResponse.pending)runSearch({retain:true,exactRefresh:searchResponse.exactRefresh});break;
 case 'continue-search':if(!searchResponse.pending&&searchResponse.canContinue)data.continueSearch();break;
 case 'stop-search':stopSearch();break;
 case 'destination':openDestination();break;
 case 'retry-destination':lookupDestination();break;
 case 'destination-country':cancelDestinationLookup();destinationChoice={country:b.dataset.value,resorts:[],hotelId:0};$('#destination-query').value='';renderDestination();loadResorts(destinationChoice.country);break;
 case 'retry-resorts':loadResorts(destinationChoice.country);break;
 case 'destination-all':cancelDestinationLookup();destinationChoice.resorts=[];destinationChoice.hotelId=0;$('#destination-query').value='';renderDestination();break;
 case 'destination-resort':{cancelDestinationLookup();$('#destination-query').value='';const r=b.dataset.value,c=b.dataset.country;if(destinationChoice.country!==c)destinationChoice={country:c,resorts:[],hotelId:0};destinationChoice.hotelId=0;destinationChoice.resorts=destinationChoice.resorts.includes(r)?destinationChoice.resorts.filter(x=>x!==r):[...destinationChoice.resorts,r];renderDestination();break}
 case 'destination-hotel':{const h=destinationHotel(id);if(!h)return;cancelDestinationLookup();destinationChoice={country:h.country,resorts:[],hotelId:h.id};$('#destination-query').value='';$('#destination-query').blur();renderDestination();$('[data-action="apply-destination"]').focus({preventScroll:true});break}
 case 'destination-recent':cancelDestinationLookup();destinationChoice=structuredClone(recentDestinations()[+b.dataset.value]);$('#destination-query').value='';renderDestination();break;
 case 'apply-destination':draftDestination=structuredClone(destinationChoice);draft.country=destinationChoice.country;closeModal();updateSearchUI();if(!state.hasSearched)renderResults();break;
 case 'retry-hotel-restore':restoreURLHotel();break;
 case 'dates':openDates();break;case 'meals':openMeals();break;case 'budget':openBudget();break;
 case 'apply-meals':state.filters.meals=[...mealDraft];syncFilters();closeModal();break;
 case 'budget-preset':$('#budget-min').value=0;$('#budget-max').value=b.dataset.value;break;
 case 'apply-budget':{const low=$('#budget-min'),high=$('#budget-max'),min=Number(low.value),max=high.value.trim()===''?null:Number(high.value);if(low.validity.badInput||high.validity.badInput||!Number.isFinite(min)||min<0||max!==null&&(!Number.isFinite(max)||max<0||min>max)){$('#budget-error').textContent='Введите неотрицательные суммы; минимум не должен превышать максимум. Пустое поле «До» — без ограничений.';return}state.filters.min=min;state.filters.max=max;syncFilters();closeModal();break}
 case 'any-stars':state.filters.stars=[];syncFilters();break;case 'nights':openNights();break;case 'guests':openGuests();break;case 'calendar':openCalendar();break;
 case 'modal-back':modalBack();break;case 'close-modal':closeModal();break;case 'filters':openFilters();break;case 'close-filters':closeFilters();break;case 'apply-filters':closeFilters({apply:true});break;
 case 'top':case 'edit-search':editSearch();break;
 case 'hotel-details':openHotelDetails(id);break;
 case 'hotel-gallery':openGallery(id,+b.dataset.value);break;
 case 'clear-compare':state.compare=[];saveStored('anytour.real.compare.v1',[]);updateNav();renderResults({keepFilters:true});break;
 case 'card-photo-index':case 'card-photo':{const h=hotels.find(h=>h.id===id);if(!h?.photos.length)break;const idx=action==='card-photo-index'?+b.dataset.value:((state.photoIndexes[id]||0)+(+b.dataset.dir)+h.photos.length)%h.photos.length;state.photoIndexes[id]=idx;$('#hotel-'+id+' .hotel-image').src=photoUrl(h,idx);$('#hotel-'+id+' .photo-index').textContent=idx+1;$$('#hotel-'+id+' .card-thumb').forEach((el,i)=>{el.classList.toggle('active',i===idx);el.setAttribute('aria-pressed',i===idx)});break;}
 case 'reset':if(filterDraft&&b.closest('#filter-panel')){filterDraft={filters:defaultFilters(),onlyFavorites:false,selectedDate:null};filterEdited(true)}else resetFilters();break;
 case 'all-hotels':state.onlyFavorites=false;renderResults();break;
 case 'star':{const n=+b.dataset.value,a=(b.closest('#filter-panel')?editingFilterModel().filters:state.filters).stars,i=a.indexOf(n);if(i<0)a.push(n);else a.splice(i,1);filterEdited(true);if(filterDraft)$(`#filter-panel [data-action="star"][data-value="${n}"]`)?.focus({preventScroll:true});break}
 case 'preset':state.filters[b.dataset.preset]=!state.filters[b.dataset.preset];syncFilters();break;
 case 'remove-filter':{const model=appliedFilterModel();removeModelFilter(model,b.dataset.key,b.dataset.value);state.onlyFavorites=model.onlyFavorites;state.selectedDate=model.selectedDate;syncFilters();break}
 case 'remove-draft-filter':if(filterDraft){removeModelFilter(filterDraft,b.dataset.key,b.dataset.value);filterEdited(true);$('#filter-panel .mobile-close').focus({preventScroll:true})}break;
 case 'recover-filters':{const choice=(b.dataset.source==='drawer'?drawerSuggestions:emptySuggestions)[+b.dataset.value];if(!choice)break;if(b.dataset.source==='drawer'&&filterDraft){filterDraft=structuredClone(choice.model);filterEdited(true);$('#filter-panel .mobile-close').focus({preventScroll:true})}else{state.filters=structuredClone(choice.model.filters);state.onlyFavorites=choice.model.onlyFavorites;state.selectedDate=choice.model.selectedDate;state.openHotel=null;syncFilters();$('#results').scrollIntoView({behavior:scrollBehavior(),block:'start'});$('#results').focus({preventScroll:true})}break;}

 case 'clear-date':state.selectedDate=null;renderResults({keepFilters:true});updateSearchUI();break;
 case 'select-date':selectDate(b.dataset.date);break;
 case 'month-prev':case 'month-next':{const d=dateObj(calendarMonth);d.setUTCMonth(d.getUTCMonth()+(action==='month-next'?1:-1));calendarMonth=iso(d);renderDateCalendar();loadCalendarPrices();break}
 case 'day-pick':{const day=b.dataset.date;dateDraft.flex=0;if(dateDraft.phase===0){dateDraft.from=day;dateDraft.to=day;dateDraft.phase=1;dateAnchor=day}else{if(day<dateDraft.from){dateDraft.to=dateDraft.from;dateDraft.from=day}else dateDraft.to=day;dateDraft.phase=0}updateDateSelection();break}
 case 'flex-date':{const n=+b.dataset.value;dateDraft.flex=n;dateDraft.from=addDays(dateAnchor,-n)<startDay?startDay:addDays(dateAnchor,-n);dateDraft.to=addDays(dateAnchor,n)>endDay?endDay:addDays(dateAnchor,n);dateDraft.phase=0;updateDateSelection();break}
 case 'apply-dates':{const from=$('#date-from').value,to=$('#date-to').value;if(!from||!to||from<startDay||to>endDay||from>to||(dateObj(to)-dateObj(from))/86400000>21){$('#date-error').textContent='Выберите корректный диапазон не больше 21 дня между датами.';return}draft.from=from;draft.to=to;if(dateContext.source==='results'){state.search.from=from;state.search.to=to;state.selectedDate=from===to?from:null;state.openHotel=null;renderResults({keepFilters:true})}else if(state.selectedDate){state.selectedDate=null;renderResults({keepFilters:true})}closeModal();updateSearchUI();break}
 case 'adults-minus':guestDraft.adults=Math.max(1,guestDraft.adults-1);renderGuests();break;
 case 'adults-plus':guestDraft.adults=Math.min(6,guestDraft.adults+1);renderGuests();break;
 case 'children-minus':guestDraft.ages.pop();renderGuests();break;
 case 'children-plus':if(guestDraft.ages.length<3)guestDraft.ages.push(null);renderGuests();break;
 case 'apply-guests':if(guestDraft.ages.some(a=>a===null)){$('#guest-error').textContent='Укажите возраст каждого ребёнка.';return}draft.adults=guestDraft.adults;draft.ages=[...guestDraft.ages];closeModal();updateSearchUI();break;
 case 'night-pick':{const n=+b.dataset.value;if(nightsDraft.phase===0){nightsDraft.min=n;nightsDraft.max=n;nightsDraft.phase=1}else{nightsDraft.max=Math.max(nightsDraft.min,n);nightsDraft.min=Math.min(nightsDraft.min,n);nightsDraft.phase=0}renderNightSelection();break}
 case 'night-preset':nightsDraft={min:+b.dataset.value,max:+b.dataset.value,phase:0};renderNightSelection();break;
 case 'apply-nights':draft.minNights=nightsDraft.min;draft.maxNights=nightsDraft.max;closeModal();updateSearchUI();break;
 case 'toggle-offers':{state.openHotel=state.openHotel===id?null:id;const focusId=id;renderResults({keepFilters:true});const target=$(`[data-action="toggle-offers"][data-id="${focusId}"]`);target?.focus({preventScroll:true});if(state.openHotel)$('#offers-'+id).scrollIntoView({behavior:scrollBehavior(),block:'nearest'});break}
 case 'all-offers':openAllOffers(id);break;
 case 'offer-view':if(offerView&&['list','compare'].includes(b.dataset.value)){offerView.mode=b.dataset.value;renderOfferList();b.focus({preventScroll:true});}break;
 case 'compare-tour':{const o=offerFromKey(b.dataset.key);if(o&&offerView?.id===o.hotelId){offerView.mode='compare';offerView.day=o.day;offerView.nights=o.nights;offerView.activeVariant=o.variant;offerView.pair=[];renderOfferList();$('#modal-body').scrollTop=0;$('#compare-offer-day').focus({preventScroll:true});}break;}
 case 'offer-flights':openOffer(b.dataset.key,null,true);break;
 case 'offer-group':{const key=b.dataset.value;offerView.open=offerView.open.includes(key)?offerView.open.filter(x=>x!==key):[...offerView.open,key];renderOfferList();$(`[data-action="offer-group"][data-value="${key}"]`).focus({preventScroll:true});break}
 case 'group-more':offerView.limits[b.dataset.value]=(offerView.limits[b.dataset.value]||4)+8;renderOfferList();break;
 case 'reset-offer-filters':offerView.flight='';offerView.room='';offerView.meal='';renderOfferList(true);break;
 case 'more-offers':{const h=hotels.find(h=>h.id===id),offers=hotelOffers(h),off=+b.dataset.offset;$('#all-offers-list').insertAdjacentHTML('beforeend',offers.slice(off,off+30).map(o=>offerHTML(h,o)).join(''));if(off+30>=offers.length)b.remove();else b.dataset.offset=off+30;break}
 case 'offer':openOffer(b.dataset.key);break;case 'confirm-tour':confirmTour();break;case 'accept-price':if(verifiedOffer){const accepted=verifiedOffer;verifiedOffer=null;completeTour(accepted);}break;
 case 'gallery':openGallery(id,state.photoIndexes[id]||0);break;case 'gallery-next':case 'gallery-prev':{const count=hotels.find(h=>h.id===gallery.id).photos.length;gallery.index=(gallery.index+(action==='gallery-next'?1:-1)+count)%count;renderGallery();break}
 case 'gallery-index':gallery.index=+b.dataset.value;renderGallery();break;
 case 'favorite':toggleFavorite(id);break;case 'favorites':openFavorites();break;
 case 'toggle-compare':toggleCompare(id);break;case 'compare':openCompare();break;
 case 'only-favorites':state.onlyFavorites=true;closeModal();renderResults();$('#results').scrollIntoView({behavior:scrollBehavior()});break;
 case 'show-hotel':{const h=hotels.find(h=>h.id===id);state.search.country=h.country;draft=structuredClone(state.search);draftDestination=null;state.filters=defaultFilters();state.filters.hotelId=h.id;state.onlyFavorites=false;state.selectedDate=null;state.openHotel=id;closeModal();syncFilters();updateURL();$('#results').scrollIntoView({behavior:scrollBehavior()});break}
 case 'operator-info':toast('Туроператор: '+b.dataset.operator);break;
 case 'about':showModal('about','Версия для проверки','ANYTOUR SEARCH3','<p class="modal-intro">Исходный интерфейс прототипа подключается к реальным предложениям и базе отелей AnyTour. Цены в календаре справочные; актуальность проверяется при выборе. Заявки и оплата в этой версии отключены.</p>');break;
 }
});
matchMedia('(max-width:760px)').addEventListener('change',()=>refreshTourComparison());
let comparisonResizeTimer;addEventListener('resize',()=>{if(modalType==='compare'){clearTimeout(comparisonResizeTimer);comparisonResizeTimer=setTimeout(()=>{if(modalType==='compare')refreshSavedView('compare')},100)}});
document.addEventListener('keydown',e=>{
 if(modalType==='gallery'&&['ArrowLeft','ArrowRight'].includes(e.key)){e.preventDefault();gallery.index=(gallery.index+(e.key==='ArrowRight'?1:-1)+hotels.find(h=>h.id===gallery.id).photos.length)%hotels.find(h=>h.id===gallery.id).photos.length;renderGallery()}
 if($('#filter-panel').classList.contains('open')){if(e.key==='Escape'){e.preventDefault();closeFilters()}if(e.key==='Tab'){const els=$$('#filter-panel button:not([disabled]),#filter-panel input,#filter-panel select').filter(el=>el.getClientRects().length),first=els[0],last=els.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}}}
});
let touchStart=null;
$('#modal-body').addEventListener('touchstart',e=>{if(modalType==='gallery')touchStart=e.touches[0].clientX},{passive:true});
$('#modal-body').addEventListener('touchend',e=>{if(modalType==='gallery'&&touchStart!==null){const dx=e.changedTouches[0].clientX-touchStart;if(Math.abs(dx)>45){gallery.index=(gallery.index+(dx<0?1:-1)+hotels.find(h=>h.id===gallery.id).photos.length)%hotels.find(h=>h.id===gallery.id).photos.length;renderGallery()}touchStart=null}},{passive:true});
window.addEventListener('resize',()=>{if(innerWidth>1100&&$('#filter-panel').classList.contains('open'))closeFilters();if(modalType==='dates'&&calendarMobile!==(innerWidth<=760)){renderDateCalendar();loadCalendarPrices();}});

function refreshCalendarPrices(){
 datePrices.clear();
 $$('#date-calendar .calendar-month').forEach(month=>{
  const cells=[...month.querySelectorAll('.month-day:not([disabled])')],prices=cells.map(b=>calendarPrice(b.dataset.date)),min=Math.min(...prices.filter(p=>p!==null));
  cells.forEach((b,i)=>{const price=prices[i];b.classList.toggle('is-cheap',price!==null&&price===min);b.querySelector('small').textContent=price===null?'—':(price/1000).toLocaleString('ru-RU',{maximumFractionDigits:1});b.setAttribute('aria-label',dateLong(b.dataset.date)+(price===null?', цена пока неизвестна':', от '+money(price)));});
 });
}
function loadCalendarPrices(){
 calendarRequest?.abort();calendarObserver?.disconnect();calendarRequest=new AbortController();const controller=calendarRequest,ctx=dateContext,loads=new Map();
 calendarHotels=[];calendarObservations=[];const snapshots=new Map();refreshCalendarPrices();
 const legend=()=>{if(controller.signal.aborted||dateContext!==ctx||modalType!=='dates')return;const phases=[...loads.values()];$('.calendar-legend span').textContent=phases.includes('error')?'Не все цены загрузились. Даты можно выбрать без цены.':phases.includes('loading')?'Загружаем цены из базы…':'Цены из базы и текущей выдачи за всех, от · прочерк — нет цены';};
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
 data.catalog.meals.forEach(x=>{const label=data.meal(x);if(label)mealNames[label]=label;});
 draft.origin=c.origin;if(!countryNames[draft.country])draft.country=String(c.countries.find(x=>data.text(x)==='Турция')?.id||c.countries[0].id);
 draftDestination=null;updateSearchUI();
}
async function loadCountries(origin){catalogReady=false;$('.search-submit').disabled=true;try{const c=await data.countries(origin);if(!c)return;applyCatalog(c);await loadResorts(draft.country);catalogReady=true;updateSearchUI();}catch(error){toast(error.message);}}
async function restoreSavedHotels(){
 const favorites=validIds(getStored('anytour.real.favorites.v1',[])),compare=validIds(getStored('anytour.real.compare.v1',[])).slice(0,3),ids=[...new Set([...favorites,...compare])];if(!ids.length)return;const rows=await data.savedHotels(ids,state.search);if(state.hasSearched)return;hotels=rows;state.favorites=favorites.filter(id=>rows.some(h=>h.id===id));state.compare=compare.filter(id=>rows.some(h=>h.id===id));updateNav();
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
 try{applyCatalog(await data.init(new URLSearchParams(location.search).get('origin')||draft.origin));state.search=structuredClone(draft);restoreURL();await loadResorts(state.search.country);catalogReady=true;updateSearchUI();renderResults();if(modalType==='destination'){if(!countryNames[destinationChoice.country])destinationChoice=structuredClone(currentDraftDestination());lookupDestination();}if(modalType==='dates'){dateContext=createDateContext(dateContext?.source==='results'?'results':'form');$('.calendar-context').textContent=dateContextLabel(dateContext.search);loadCalendarPrices();}await restoreURLHotel();await restoreSavedHotels();}
 catch(error){catalogError=error.message;$('#cards').innerHTML=`<div class="empty"><h3>Не удалось загрузить направления</h3><p>${esc(error.message)}</p><button class="primary" data-action="retry-catalog">Повторить</button></div>`;if(modalType==='destination')renderDestination();}
}
document.addEventListener('click',event=>{const b=event.target.closest('[data-action]');if(!b||b.disabled)return;if(b.dataset.action==='refresh-hotel')refreshHotel(Number(b.dataset.id));if(b.dataset.action==='retry-flights')loadRealFlights();if(b.dataset.action==='retry-catalog')bootRealData();});
document.addEventListener('error',event=>{const img=event.target;if(img.tagName==='IMG'&&img.classList.contains('hotel-image')){img.closest('.hotel-photos')?.classList.add('photo-unavailable');img.removeAttribute('src');img.alt='Фото пока недоступно';}},true);

const initialUIRoute=history.state?.[uiHistoryKey];
restoreSelectedTour();hydrate();bootRealData();
if(initialUIRoute)addEventListener('pageshow',()=>restoreHistoryView(initialUIRoute),{once:true});
new IntersectionObserver(entries=>{$('#compact-search').hidden=entries[0].isIntersecting},{threshold:0}).observe($('#search'));
})();
