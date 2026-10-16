'use strict';
// New frontend on the original final Sites v2 markup. The donor simulator and
// Search3 NEXT presentation are not dependencies of this owner.
(function (window) {
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const money = value => new Intl.NumberFormat('ru-RU').format(value).replace(/[\u00a0\u202f]/g,' ') + ' ₽';
  const dateText = value => new Date(value + 'T12:00:00Z').toLocaleDateString('ru-RU', {day:'numeric',month:'short',timeZone:'UTC'}).replace('.', '');
  const dateRange = (from,to) => from === to ? dateText(from) : from.slice(0,7) === to.slice(0,7) ? Number(from.slice(8)) + '–' + dateText(to) : dateText(from) + '–' + dateText(to);
  const addDays = (value, days) => new Date(Date.parse(value + 'T12:00:00Z') + days * 86400000).toISOString().slice(0,10);
  const nights = n => n + ' ' + (n % 10 === 1 && n % 100 !== 11 ? 'ночь' : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14) ? 'ночи' : 'ночей');
  const icon = name => '<i data-lucide="' + name + '" aria-hidden="true"></i>';
  const party = (search, afterFor = false) => {
    const children = search.ages.length;
    const adultWord = search.adults === 1 ? afterFor ? 'взрослого' : 'взрослый' : 'взрослых';
    const childWord = afterFor ? children === 1 ? 'ребёнка' : 'детей' : children === 1 ? 'ребёнок' : children <= 4 ? 'ребёнка' : 'детей';
    return search.adults + ' ' + adultWord + (children ? ' + ' + children + ' ' + childWord : '');
  };
  const mealText = offer => /^(?:on request|питание уточняется)$/i.test(offer.meal || '') ? 'Питание уточняется' : offer.meal || 'Питание уточняется';
  const sourceNames = Object.freeze({tourvisor:'Tourvisor',anex:'ANEX',andromeda:'Andromeda'});
  const sourceList = names => names.length < 2 ? names[0] || '' : names.slice(0,-1).join(', ') + ' и ' + names[names.length-1];
  function partialSourceMessage(state) {
    if (state.status !== 'partial' || !state.hotels.length || !state.sources || typeof state.sources !== 'object') return '';
    const entries = Object.keys(sourceNames).map(key => [key,state.sources[key]]).filter(([,value]) => value && typeof value === 'object');
    const received = entries.filter(([,value]) => Number(value.offers) > 0).map(([key]) => sourceNames[key]);
    const incomplete = entries.filter(([,value]) => value.status === 'error' || value.status === 'partial').map(([key]) => sourceNames[key]);
    if (!received.length) return '';
    return 'Показаны предложения: ' + sourceList(received) + '. ' + (incomplete.length ? 'Не все источники завершили поиск: ' + sourceList(incomplete) + '.' : 'Поиск завершён не полностью.');
  }
  function resultStatusMessage(state) {
    if (state.status === 'loading') return {
      title:'Идёт поиск',
      detail:'Найденные туры уже доступны для просмотра.'
    };
    if (state.status === 'error') return {
      title:'Не удалось завершить поиск',
      detail:'Полученные предложения сохранены. Измените условия или повторите поиск.',
      action:'Изменить и повторить'
    };
    if (state.status === 'partial') return {
      title:'Поиск завершён не полностью',
      detail:partialSourceMessage(state) || 'Полученные предложения сохранены.'
    };
    return null;
  }
  const favoriteKey = 'anytour.prototype.v18.favorites.v1';
  function mount({root, bridge, initialState, onReady}) {
    const $ = selector => root.querySelector(selector), $$ = selector => [...root.querySelectorAll(selector)];
    const listeners = new AbortController(); let alive = true, editor = null, editorSnapshot = null, searchEpoch = 0, originEpoch = 0;
    const editorFields = {
      dates:{panel:'[data-search-calendar]',opener:'[data-search-date-toggle]',focus:'[data-calendar-boundary="from"]'},
      nights:{panel:'[data-search-nights-editor]',opener:'[data-search-nights-toggle]',focus:'[data-native-night][aria-pressed="true"]'},
      travelers:{panel:'[data-search-travelers-editor]',opener:'[data-search-travelers-toggle]',focus:'[data-search-adult="1"]'},
      destination:{panel:'[data-destination-editor]',opener:'[data-search-destination-toggle]',focus:'[data-destination-query]'},
      food:{panel:'[data-search-food-editor]',opener:'[data-conditions-open="food"]',focus:'[data-native-form-meal]'},
      budget:{panel:'[data-search-budget-editor]',opener:'[data-conditions-open="budget"]',focus:'[data-search-budget="min"]'}
    };
    // Reuse the canonical editors and the one Find button inside a native modal.
    // They remain in the same form: no duplicate fields, drafts or submit owner.
    const popup = window.document.createElement('dialog');
    popup.dataset.nativeFormPopup='';
    const popupSubmit = window.document.createElement('div');popupSubmit.className='st-popup-submit';
    const submitWrap=$('[data-search-form] .st-search-submit-wrap'),submitButton=submitWrap.querySelector('[type="submit"]');
    const editorHomes=Object.values(editorFields).map(field=>{
      const panel=$(field.panel),marker=window.document.createComment('canonical field editor');
      panel.before(marker);panel.dataset.nativePopupPanel='';popup.append(panel);return{panel,marker};
    });
    popup.append(popupSubmit);$('[data-search-form]').append(popup);
    function syncPopup(){
      if(!editor){
        if(popup.open){if(typeof popup.close==='function')popup.close();else popup.removeAttribute('open');}
        if(submitButton.parentElement!==submitWrap)submitWrap.append(submitButton);
        submitWrap.style.removeProperty('min-height');return;
      }
      popup.dataset.editor=editor;
      popup.setAttribute('aria-label',$(editorFields[editor].panel).querySelector('h2').textContent);
      if(root.hidden)return;
      if(!popup.open){
        submitWrap.style.minHeight=submitWrap.getBoundingClientRect().height+'px';popupSubmit.append(submitButton);
        if(typeof popup.showModal==='function')popup.showModal();else popup.setAttribute('open','');
        $(editorFields[editor].focus)?.focus({preventScroll:true});
      }
    }
    const state = initialState || {screen:'search',search:null,filters:null,hotels:[],status:'idle',message:'',sources:null,sort:'recommended',favorites:[],onlyFavorites:false,hotelId:null,selected:null,selectedDate:null,calendarOpen:false,calendarPage:0,calendarRange:null,hasSearched:false};
    let dictionaries = state.dictionaries || null, draft = null, draftFilters = null, filterDraft = null, filterBudgetValues = null, photoIndex = 0, tourOwner = null;
    let destinationRows = [], destinationController = null, destinationEpoch = 0, destinationPending = null, destinationLoading = false, destinationError = '';
    let nightAnchor = null, nightError = '', calendarMonth = null, calendarBoundary = 'from', calendarError = '', agesReviewNeeded = false, editorOpener = null, originPending = false;
    const destinationHotels = new Map(), regionCache = new Map();
    // History carries only a small route token. Canonical offers and receipt
    // objects remain the same in-memory references rather than cloned state.
    const historyRoutes = state.historyRoutes || new Map();
    let historySequence = state.historySequence || 0;
    const text = (selector, value) => $$(selector).forEach(node => node.textContent = value);
    const redrawIcons = () => window.lucide?.createIcons({attrs:{width:18,height:18}});
    const defaultFilters = () => ({hotelId:0,hotelIds:[],q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false});
    const cloneFilters = f => ({...f,stars:[...f.stars],meals:[...f.meals],resorts:[...f.resorts],hotelIds:[...(f.hotelIds || [])]});
    const countryName = id => dictionaries?.countries.find(row => String(row.id) === String(id))?.name || '';
    const currentHotel = () => state.hotels.find(hotel => hotel.id === state.hotelId);
    const findOffer = key => state.hotels.flatMap(hotel => hotel.offers || []).find(offer => offer.key === key);
    const countText = n => n + ' ' + (n % 10 === 1 && n % 100 !== 11 ? 'отель' : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14) ? 'отеля' : 'отелей');
    const resultBatch = 24;
    if (!Number.isInteger(state.resultLimit) || state.resultLimit < resultBatch) state.resultLimit = resultBatch;
    const resetResultLimit = () => { state.resultLimit = resultBatch; };
    // Result meal groups use exact loaded canonical label or exact displayed
    // supplier fact. They never create supplier IDs or modify form plan mapping.
    const mealKey = offer => offer.mealFacet || mealText(offer);
    function facetCoverage() {
      return {stars:state.hotels.every(hotel=>Number.isInteger(hotel.stars)&&hotel.stars>=1&&hotel.stars<=5),
        meals:state.hotels.every(hotel=>(hotel.offers||[]).every(offer=>mealKey(offer)!=='Питание уточняется'))};
    }
    function eligible(hotel, filters = state.filters, selectedDate = state.selectedDate, onlyFavorites = state.onlyFavorites) {
      if (!hotel || !state.search || String(hotel.country) !== String(state.search.country)) return [];
      if (filters.hotelIds?.length && !filters.hotelIds.includes(hotel.id)) return [];
      if (!filters.hotelIds?.length && filters.hotelId && hotel.id !== filters.hotelId) return [];
      if (onlyFavorites && !state.favorites.includes(hotel.id)) return [];
      if (filters.stars.length && !filters.stars.includes(hotel.stars)) return [];
      if (filters.resorts.length && !filters.resorts.some(r => [hotel.subRegion,hotel.region,hotel.resort].includes(r))) return [];
      return (hotel.offers || []).filter(offer => Number.isFinite(offer.total) && offer.total > 0
        && (!selectedDate || offer.day === selectedDate)
        && (!filters.meals.length || filters.meals.includes(mealKey(offer)))
        && offer.total >= filters.min && (filters.max === null || offer.total <= filters.max))
        .slice().sort((a,b) => a.total - b.total || a.key.localeCompare(b.key));
    }
    function rows(filters = state.filters, selectedDate = state.selectedDate, onlyFavorites = state.onlyFavorites) {
      const result = state.hotels.filter(hotel => eligible(hotel,filters,selectedDate,onlyFavorites).length);
      if (state.sort === 'price') result.sort((a,b) => eligible(a,filters,selectedDate,onlyFavorites)[0].total - eligible(b,filters,selectedDate,onlyFavorites)[0].total);
      if (state.sort === 'name') result.sort((a,b) => a.name.localeCompare(b.name,'ru'));
      return result;
    }
    function resultConstraints() {
      const f=state.filters,items=[];
      if(f.min||f.max!==null)items.push({group:'budget',value:'',label:[f.min?'от '+money(f.min):'',f.max!==null?'до '+money(f.max):''].filter(Boolean).join(' · ')});
      for(const group of ['stars','meals','resorts'])for(const value of f[group])items.push({group,value:String(value),label:group==='stars'?value+' звёзд':String(value)});
      for(const id of f.hotelIds?.length?f.hotelIds:f.hotelId?[f.hotelId]:[])items.push({group:'hotel',value:String(id),label:state.hotels.find(h=>h.id===id)?.name||'Выбранный отель'});
      if(state.selectedDate)items.push({group:'date',value:state.selectedDate,label:'Вылет '+dateText(state.selectedDate)});
      if(state.onlyFavorites)items.push({group:'favorites',value:'',label:'Только избранное'});
      return items;
    }
    function withoutConstraint(item) {
      const filters=cloneFilters(state.filters);let selectedDate=state.selectedDate,onlyFavorites=state.onlyFavorites;
      if(item.group==='budget'){filters.min=0;filters.max=null;}
      else if(item.group==='date')selectedDate=null;
      else if(item.group==='favorites')onlyFavorites=false;
      else if(item.group==='hotel'){filters.hotelIds=(filters.hotelIds?.length?filters.hotelIds:filters.hotelId?[filters.hotelId]:[]).filter(id=>String(id)!==item.value);filters.hotelId=filters.hotelIds[0]||0;}
      else filters[item.group]=filters[item.group].filter(value=>String(value)!==item.value);
      return{filters,selectedDate,onlyFavorites};
    }
    function renderResultConstraints(found,loadingEmpty,errorEmpty,partialEmpty) {
      const items=resultConstraints(),chips=$('[data-active-chips]');chips.hidden=!items.length;
      chips.setAttribute('aria-label','Выбранные условия');
      chips.innerHTML=items.map(item=>`<button type="button" class="st-chip cursor-interaction" data-native-remove-constraint="${item.group}" data-native-constraint-value="${esc(item.value)}" aria-label="Снять условие: ${esc(item.label)}"><span>${esc(item.label)}</span>${icon('x')}</button>`).join('');
      const action=$('[data-empty-action]'),description=$('[data-empty-text]');
      action.dataset.action='search';delete action.dataset.nativeRemoveConstraint;delete action.dataset.nativeConstraintValue;
      action.hidden=loadingEmpty;
      action.textContent=!state.hasSearched?'К параметрам поиска':errorEmpty||partialEmpty?'Вернуться к поиску':'Изменить поиск';description.textContent=!state.hasSearched?'Выберите условия поездки и нажмите «Найти туры».':loadingEmpty?'Первые предложения появятся здесь.':partialEmpty?'Предложения не получены. Вернитесь к сохранённым условиям и попробуйте повторить поиск.':errorEmpty?'Сервис поиска временно недоступен. Попробуйте повторить поиск позже.':state.status==='loading'?'Предложения ещё загружаются. Полученные туры появятся здесь.':'Попробуйте изменить даты или снять часть фильтров.';
      const recovery=!loadingEmpty&&!found.length?items.map(item=>{const next=withoutConstraint(item);return{...item,count:rows(next.filters,next.selectedDate,next.onlyFavorites).length};}).find(item=>item.count>0):null;
      if(recovery){
        delete action.dataset.action;action.dataset.nativeRemoveConstraint=recovery.group;action.dataset.nativeConstraintValue=recovery.value;
        action.textContent=recovery.group==='budget'?'Убрать бюджет':'Снять «'+recovery.label+'»';
        description.textContent='Без этого условия: '+countText(recovery.count)+' из загруженных. Остальные условия сохранятся.';
      }
      $$('[data-empty-edit]').forEach(node=>node.hidden=!recovery);
    }
    function syncFavoriteControls() {
      $$('[data-action="favorites"]').forEach(node=>node.setAttribute('aria-pressed',String(state.onlyFavorites)));
      text('[data-fav-count]',state.favorites.length);$('[data-fav-count]').hidden=!state.favorites.length;
      const selected=state.hotelId!==null&&state.hotelId!==undefined&&state.favorites.includes(Number(state.hotelId));
      $$('[data-favorite-current]').forEach(node=>{node.setAttribute('aria-pressed',String(selected));node.setAttribute('aria-label',selected?'Удалить из избранного':'Сохранить отель');});
    }
    function photo(node, hotel, index = 0) {
      if (!node) return;
      const source = hotel?.photos?.[index];
      node.style.backgroundImage = source && /^(https?:\/\/|\/)/.test(source) ? 'url(' + JSON.stringify(source) + ')' : 'none';
      node.style.backgroundPosition = 'center'; node.style.backgroundSize = 'cover';
      node.classList.toggle('st-no-photo', !source); node.setAttribute('aria-label', source ? 'Фото отеля ' + hotel.name : 'Фото отеля пока нет');
      if(node.hasAttribute('data-hotel-hero')){
        node.classList.toggle('q8c5',!source);let empty=node.querySelector('.st-photo-empty-message');
        if(!source&&!empty){empty=window.document.createElement('div');empty.className='st-photo-empty-message';empty.innerHTML=icon('image')+'<span>Фото пока нет</span>';node.prepend(empty);}
        if(empty)empty.hidden=!!source;
      }
    }
    function routeURL(screen = state.screen) {
      const p = new URLSearchParams(window.location.search), s = state.search;
      if (!s) return window.location.href;
      for (const key of ['origin','country','from','to','minNights','maxNights','adults','ages']) p.set(key,Array.isArray(s[key]) ? s[key].join(',') : String(s[key]));
      if (state.hasSearched) p.set('searched','1'); else p.delete('searched');
      for (const key of ['stars','meals','resorts']) { const values = state.filters[key]; if(values.length)p.set(key,values.join('|'));else p.delete(key); }
      const ids = state.filters.hotelIds?.length ? state.filters.hotelIds : state.filters.hotelId ? [state.filters.hotelId] : [];
      p.delete('hotelId');
      if(ids.length)p.set('hotelIds',ids.join('|'));else p.delete('hotelIds');
      if(state.filters.min)p.set('min',String(state.filters.min));else p.delete('min');
      if(state.filters.max!==null)p.set('max',String(state.filters.max));else p.delete('max');
      if(state.selectedDate)p.set('selectedDate',state.selectedDate);else p.delete('selectedDate');
      if(state.sort!=='recommended')p.set('sort',state.sort);else p.delete('sort');
      if(state.onlyFavorites)p.set('favoritesOnly','1');else p.delete('favoritesOnly');
      return '?' + p.toString() + '#' + screen;
    }
    function rememberRoute(push) {
      const previous = window.history.state?.anytourNative?.id;
      const id = push || !historyRoutes.has(previous) ? ++historySequence : previous;
      historyRoutes.set(id,{screen:state.screen,search:{...state.search,ages:[...state.search.ages]},filters:cloneFilters(state.filters),hotels:state.hotels,status:state.status,message:state.message,sources:state.sources?Object.fromEntries(Object.entries(state.sources).map(([key,value])=>[key,value&&typeof value==='object'?{...value}:value])):null,hasSearched:state.hasSearched,hotelId:state.hotelId,selected:state.selected,selectedDate:state.selectedDate,sort:state.sort,onlyFavorites:state.onlyFavorites,calendarOpen:state.calendarOpen,calendarPage:state.calendarPage,calendarRange:state.calendarRange,resultLimit:state.resultLimit,searchGeneration:state.searchGeneration});
      state.historyRoutes=historyRoutes;state.historySequence=historySequence;
      window.history[push?'pushState':'replaceState']({anytourNative:{id,screen:state.screen}},'',routeURL());
    }
    function updateURL() {
      if(state.search)rememberRoute(false);
    }
    function show(screen, push = true) {
      if (!alive) return;
      if(screen!=='filters'){filterDraft=null;filterBudgetValues=null;state.nativeFilters=null;}
      state.screen = screen;
      $$('[data-screen]').forEach(node => node.hidden = node.dataset.screen !== screen);
      $('.st-footer [data-action="search"]').hidden = screen === 'search';
      if (screen === 'search') openSearch();
      if (screen === 'results') renderResults();
      if (screen === 'filters') renderFilterEditor();
      if (screen === 'hotel') renderHotel();
      if (screen === 'tour' || screen === 'lead') renderTour();
      if (screen === 'gallery') renderGallery();
      if (state.search) rememberRoute(push);
      redrawIcons();
    }
    function hotelNameHTML(name, withArrow = false) {
      // Only a complete trailing EX. alias gets a quieter second line. Every
      // literal character remains in the heading, including its whitespace.
      const former = name.match(/^([\s\S]*?\S)(\s*\(\s*EX\.[^()]*\))(\s*)$/i);
      const tail = former ? former[2] + former[3] : name;
      const nameEnd = withArrow && tail.match(/^([\s\S]*?)(\S+)(\s*)$/);
      const end = nameEnd
        ? esc(nameEnd[1]) + '<span class="st-hotel-title-end"><span>' + esc(nameEnd[2]) + '</span>' + icon('arrow-up-right') + '</span>' + esc(nameEnd[3])
        : esc(tail) + (withArrow ? icon('arrow-up-right') : '');
      return former ? '<span class="st-hotel-title-main">' + esc(former[1]) + '</span><span class="st-hotel-title-former">' + end + '</span>' : end;
    }
    function conditionRows(hotel) {
      const offer = eligible(hotel)[0]; if (!offer) return '';
      const hasPhoto = !!hotel.photos?.length, selected = state.favorites.includes(hotel.id);
      const title = hotelNameHTML(hotel.name, true);
      return `<article class="st-hotel-card ${hotel.name.length > 28 ? 'st-hotel-card--long-name' : ''} ${offer.total >= 1000000 ? 'st-hotel-card--wide-price' : ''}" data-hotel-card="${hotel.id}"><div class="st-card-content"><div class="q8cb ${hasPhoto ? '' : 'q8c5'}" data-card-photo="${hotel.id}" aria-label="${hasPhoto ? 'Фото отеля ' + esc(hotel.name) : 'Фото отеля пока нет'}">${hasPhoto ? `<button class="st-photo-more cursor-interaction" data-gallery-hotel="${hotel.id}" aria-label="Открыть фотографию отеля">${icon('expand')}<span class="st-photo-label">Фото</span></button>` : `<div class="st-photo-empty-message">${icon('image')}<span>Фото пока нет</span></div>`}<button class="st-favorite cursor-interaction" data-favorite="${hotel.id}" aria-pressed="${selected}" aria-label="${selected ? 'Удалить из избранного' : 'Добавить в избранное'}">${icon('heart')}</button></div><div class="st-card-info"><div class="st-card-name"><button class="st-name-button cursor-interaction" data-hotel-open="${hotel.id}">${title}</button><span class="st-stars" aria-label="${hotel.stars} звёзд">${'★'.repeat(Math.min(5,Math.max(0,hotel.stars || 0)))}</span></div><div class="st-location">${esc(hotel.location || [countryName(hotel.country),hotel.subRegion || hotel.region].filter(Boolean).join(' · '))}</div><div class="st-card-conditions"><span class="st-conditions-date">${icon('calendar-days')}${dateText(offer.day)} · ${nights(offer.nights)}</span><span class="st-conditions-room">${icon('bed-double')}${esc(offer.room || 'Номер уточняется')}</span><span class="st-conditions-meal">${icon('utensils')}${esc(mealText(offer))}</span><span class="st-conditions-operator">${esc(offer.operator)}</span></div><div class="st-price-area"><div><div class="st-price"><small>от</small>${money(offer.total)}</div><div class="st-price-caption">за ${party(offer,true)}</div></div><button class="q8c2 cursor-interaction" data-offer="${esc(offer.key)}">Выбрать тур${icon('arrow-up-right')}</button></div><div class="st-card-actions"><button class="st-card-about cursor-interaction" data-hotel-about="${hotel.id}">Об отеле</button><button class="st-card-all cursor-interaction" data-hotel-open="${hotel.id}">Все варианты (${eligible(hotel).length})${icon('chevron-right')}</button></div></div></div></article>`;
    }
    function budgetMaximum(filters){return Math.max(1000,filters.min,filters.max || 0,...state.hotels.flatMap(h=>(h.offers||[]).map(o=>o.total).filter(Number.isFinite)));}
    function facetHTML(filters) {
      const coverage=facetCoverage();
      const options = [...new Set(state.hotels.flatMap(h => (h.offers || []).map(mealKey)))].filter(label => label !== 'Питание уточняется');
      const count = (group,value) => rows({...filters,[group]:[value]}).length;
      const checkbox = (group,value,label) => `<label class="st-check"><input type="checkbox" data-native-filter="${group}" value="${esc(value)}" ${filters[group].includes(value) ? 'checked' : ''}><span>${esc(label)}</span><span class="st-filter-count" aria-label="${countText(count(group,value))}">${count(group,value)}</span></label>`;
      return `${coverage.stars?`<div class="st-group" data-native-facet-group="stars"><strong class="st-group-title">Категория отеля</strong>${[5,4,3].map(n=>checkbox('stars',n,n+' звёзд')).join('')}</div>`:''}${coverage.meals?`<div class="st-group" data-native-facet-group="meals"><strong class="st-group-title">Питание</strong>${options.map(label=>checkbox('meals',label,label)).join('')}</div>`:''}`;
    }
    function filterHTML(filters) {
      const max=budgetMaximum(filters);
      return `<div class="st-group"><strong class="st-group-title">Бюджет за весь тур, ₽</strong><div class="st-budget"><label>От<input type="number" inputmode="numeric" min="0" step="1" data-native-budget="min" aria-label="Бюджет от" value="${filters.min || ''}" placeholder="0"></label><label>До<input type="number" inputmode="numeric" min="0" step="1" data-native-budget="max" aria-label="Бюджет до" value="${filters.max ?? ''}" placeholder="Без лимита"></label></div><input type="range" class="st-budget-range" data-native-budget="max" min="0" max="${max}" value="${filters.max ?? max}" aria-label="Максимальная цена тура"><p class="st-budget-caption">За всех туристов</p><p class="st-budget-error" data-native-budget-error role="status" hidden></p></div>${facetHTML(filters)}`;
    }
    function filterSnapshot(){return filterDraft?{filters:cloneFilters(filterDraft),budget:{...filterBudgetValues}}:null;}
    function renderFilterEditor(){
      if(!filterDraft){filterDraft=cloneFilters(state.nativeFilters?.filters||state.filters);filterBudgetValues=state.nativeFilters?.budget||{min:String(filterDraft.min||''),max:String(filterDraft.max??'')};}
      const panel=$('[data-filter-form]');panel.innerHTML=filterHTML(filterDraft);
      for(const node of panel.querySelectorAll('input[type="number"][data-native-budget]'))node.value=filterBudgetValues[node.dataset.nativeBudget];
      validateResultBudget(panel);updateFilterDraft();
    }
    function updateFilterDraft(){
      if(!filterDraft)return;
      const panel=$('[data-filter-form]'),invalid=!!panel.querySelector('input[aria-invalid="true"]');
      const apply=$('[data-filter-apply]');apply.disabled=invalid;apply.textContent=invalid?'Проверьте бюджет':'Показать '+countText(rows(filterDraft).length);
      for(const node of panel.querySelectorAll('[data-native-filter]')){const group=node.dataset.nativeFilter,value=group==='stars'?Number(node.value):node.value,count=rows({...filterDraft,[group]:[value]}).length;const label=node.parentElement.querySelector('.st-filter-count');label.textContent=count;label.setAttribute('aria-label',countText(count));}
      state.nativeFilters=filterSnapshot();
    }
    function refreshFilterEditor(){
      if(!filterDraft)return;
      const panel=$('[data-filter-form]'),coverage=facetCoverage(),active=window.document.activeElement;
      const focusedFacet=active?.dataset.nativeFilter?{group:active.dataset.nativeFilter,value:active.value}:null;
      for(const key of ['stars','meals'])if(!coverage[key])filterDraft[key]=[];
      panel.querySelectorAll('[data-native-facet-group]').forEach(node=>node.remove());panel.insertAdjacentHTML('beforeend',facetHTML(filterDraft));
      const range=panel.querySelector('input[type="range"]');range.max=budgetMaximum(filterDraft);range.value=filterDraft.max??range.max;
      updateFilterDraft();
      if(focusedFacet)[...panel.querySelectorAll('[data-native-filter]')].find(node=>node.dataset.nativeFilter===focusedFacet.group&&node.value===focusedFacet.value)?.focus({preventScroll:true});
      redrawIcons();
    }
    function validateResultBudget(panel){
      const fields=[...panel.querySelectorAll('input[type="number"][data-native-budget]')],values={};let error='';
      for(const node of fields){const raw=node.value.trim(),valid=!node.validity.badInput&&(raw===''||/^\d+$/.test(raw)&&Number.isSafeInteger(Number(raw)));node.setAttribute('aria-invalid',String(!valid));if(!valid)error='Укажите целую сумму в рублях.';else values[node.dataset.nativeBudget]=raw===''?(node.dataset.nativeBudget==='max'?null:0):Number(raw);}
      if(!error&&values.max!==null&&values.min>values.max){error='Бюджет «от» должен быть не больше бюджета «до».';fields.forEach(node=>node.setAttribute('aria-invalid','true'));}
      const hint=panel.querySelector('[data-native-budget-error]');hint.textContent=error;hint.hidden=!error;
      return error?null:values;
    }
    function changeResultBudget(node,commit){
      const panel=node.closest('[data-filter-form],[data-filter-sidebar]');if(!panel)return;
      if(node.type==='range')panel.querySelector('input[type="number"][data-native-budget="max"]').value=node.value;
      const values=validateResultBudget(panel);
      if(filterDraft){filterBudgetValues=Object.fromEntries([...panel.querySelectorAll('input[type="number"][data-native-budget]')].map(field=>[field.dataset.nativeBudget,field.value]));}
      if(values){
        const range=panel.querySelector('input[type="range"]');range.max=Math.max(Number(range.max),values.min,values.max||0);range.value=values.max??range.max;
        if(filterDraft)Object.assign(filterDraft,values);else if(commit){Object.assign(state.filters,values);resetResultLimit();renderResults();updateURL();}
      }
      updateFilterDraft();
    }
    function renderCalendar() {
      const days = [], s = state.search, range=s.from+'|'+s.to;
      for(let day=s.from;day<=s.to;day=addDays(day,1))days.push(day);
      const selectedIndex=state.selectedDate?days.indexOf(state.selectedDate):-1,maxPage=Math.max(0,Math.ceil(days.length/7)-1);
      if(state.calendarRange!==range){state.calendarRange=range;state.calendarPage=selectedIndex>=0?Math.floor(selectedIndex/7):0;}
      if(!Number.isInteger(state.calendarPage))state.calendarPage=selectedIndex>=0?Math.floor(selectedIndex/7):0;
      state.calendarPage=Math.max(0,Math.min(maxPage,state.calendarPage));
      const pageDays=days.slice(state.calendarPage*7,state.calendarPage*7+7);
      const unfiltered=defaultFilters();
      const cells = pageDays.map(day=>{const prices=state.hotels.flatMap(h=>eligible(h,state.filters,day).map(o=>o.total));return {day,price:prices.length?Math.min(...prices):null,loaded:state.hotels.some(h=>eligible(h,unfiltered,day,false).length)};});
      const minimum=cells.filter(c=>c.price!==null).sort((a,b)=>a.price-b.price)[0], opened=!root.classList.contains('st-mobile')||state.calendarOpen;
      $('.q8c9').innerHTML=`<div class="st-pc-head"><span>Цены по датам вылета</span><span>За всех туристов</span></div>${root.classList.contains('st-mobile')?`<button class="st-pc-toggle" data-native-calendar aria-expanded="${opened}"><span class="st-pc-label">${icon('calendar-days')}Цены по датам</span><span class="st-pc-price">${icon(opened?'chevron-up':'chevron-down')}</span></button>`:''}<div class="st-pc-panel ${opened?'is-open':''}" ${opened?'':'hidden'}><div class="st-pc-grid" style="--date-count:${Math.max(1,cells.length)}" role="group" aria-label="Дата вылета и цена за всех туристов">${cells.map(c=>`<button type="button" class="st-pc-date ${minimum&&c.price===minimum.price?'st-pc-date--lowest':''} cursor-interaction" data-native-day="${c.day}" aria-pressed="${state.selectedDate===c.day}" aria-label="Вылет ${dateText(c.day)}${c.price===null?(c.loaded?', нет предложений по выбранным условиям':', цена пока не получена'):', от '+money(c.price)+' за всех туристов'}"><span class="st-pc-day">${dateText(c.day)}</span>${c.price===null?'<span class="st-pc-none">—</span>':`<span class="st-pc-full">${money(c.price)}</span><span class="st-pc-short"><strong>${(c.price/1000).toLocaleString('ru-RU',{maximumFractionDigits:1})}</strong><span>тыс. ₽</span></span>`}</button>`).join('')}</div><div class="st-pc-foot"><span>${minimum?'Минимум на этих датах: '+dateText(minimum.day)+' · '+money(minimum.price):cells.some(c=>c.loaded)?'По выбранным условиям на этих датах предложений нет':'Цены по этим датам пока не получены'}</span>${days.length>7?`<nav class="st-pc-pager" aria-label="Другие даты вылета"><button type="button" class="st-pc-page cursor-interaction" data-native-calendar-page="prev" aria-label="Предыдущие даты" ${state.calendarPage===0?'disabled':''}>${icon('chevron-left')}</button><button type="button" class="st-pc-page cursor-interaction" data-native-calendar-page="next" aria-label="Следующие даты" ${state.calendarPage===maxPage?'disabled':''}>${icon('chevron-right')}</button></nav>`:''}</div></div>`;
    }
    function renderResults() {
      if (!state.search) return;
      const coverage=facetCoverage();let reset=false;
      for(const key of ['stars','meals'])if(!coverage[key]&&state.filters[key].length){state.filters[key]=[];reset=true;}
      if(reset)updateURL();
      const found=rows(), visible=found.slice(0,state.resultLimit), s=state.search,loadingEmpty=state.status==='loading'&&!state.hotels.length,errorEmpty=state.status==='error'&&!state.hotels.length,partialEmpty=state.status==='partial'&&!state.hotels.length;
      root.classList.toggle('st-result-unavailable',!state.hasSearched||errorEmpty||partialEmpty);
      const country=countryName(s.country),title={'Турция':'Туры в Турцию','Египет':'Туры в Египет','ОАЭ':'Туры в ОАЭ'}[country]||'Туры: '+country;
      text('[data-country-title]',title);text('[data-origin]',s.origin);text('[data-country]',country);
      text('[data-departure-range]',dateRange(s.from,s.to));text('[data-nights]',s.minNights===s.maxNights?s.minNights:s.minNights+'–'+s.maxNights);
      text('[data-party]',party(s));text('[data-route]',s.origin+' → '+countryName(s.country));text('[data-mobile-dates]','Вылет '+dateRange(s.from,s.to));
      text('[data-nights-copy]',s.minNights===s.maxNights?nights(s.minNights):s.minNights+'–'+s.maxNights+' ночей');text('[data-party-copy]',party(s));
      text('[data-result-count]',!state.hasSearched?'Поиск ещё не запускали':loadingEmpty?'Ищем…':partialEmpty?'Неполные данные':errorEmpty?'Поиск прерван':countText(found.length));
      $('.st-sort').value=state.sort;
      $('.st-search-done').hidden=state.status!=='complete';
      const sourceAlert=$('[data-source-alert]'),statusMessage=resultStatusMessage(state);
      sourceAlert.hidden=!statusMessage||!state.hotels.length;
      sourceAlert.innerHTML=sourceAlert.hidden?'':`<strong>${esc(statusMessage.title)}</strong><span>${esc(statusMessage.detail)}</span>${statusMessage.action?`<div class="st-alert-actions"><button type="button" class="st-link cursor-interaction" data-action="search">${esc(statusMessage.action)}</button></div>`:''}`;
      $('[data-filter-sidebar]').innerHTML=filterHTML(state.filters);
      const list=$('[data-card-list]');list.innerHTML=visible.map(conditionRows).join('');
      let more=$('[data-result-more]');
      if(!more){more=window.document.createElement('div');more.className='st-result-more';more.dataset.resultMore='';list.after(more);}
      const remaining=Math.max(0,found.length-visible.length),next=Math.min(resultBatch,remaining);
      more.hidden=!remaining;
      more.innerHTML=remaining?`<span class="st-result-more-count" aria-live="polite">Показано ${visible.length} из ${found.length}</span><button type="button" class="st-secondary cursor-interaction" data-native-result-more>Показать ещё ${next}</button>`:'';
      const notice=$('.st-results-note');
      if(notice){
        notice.innerHTML=icon('info')+'<span>Цены из поиска — за весь тур и всех туристов. Перед заявкой проверим цену, наличие и состав пакета, включая обязательные доплаты.</span>';
        notice.hidden=!found.length;
        more.after(notice);
      }
      $$('[data-card-photo]').forEach(node=>photo(node,state.hotels.find(h=>h.id===Number(node.dataset.cardPhoto))));
      $('[data-empty]').hidden=found.length>0;text('[data-empty-title]',!state.hasSearched?'Начните с поиска':loadingEmpty?'Ищем туры…':partialEmpty?'Поиск завершён не полностью':errorEmpty?'Не удалось загрузить туры':state.status==='partial'?'Нет подходящих туров среди полученных':'Нет подходящих туров');
      $('[data-empty]').setAttribute('aria-busy',String(loadingEmpty));
      renderResultConstraints(found,loadingEmpty,errorEmpty,partialEmpty);
      syncFavoriteControls();
      renderCalendar();redrawIcons();
    }
    function renderHotel() {
      const h=currentHotel(), list=h?eligible(h):[];if(!h||!list.length){show('results',false);return;}
      const min=list[0];$('[data-hotel-name]').innerHTML=hotelNameHTML(h.name);text('[data-hotel-stars]','★'.repeat(Math.min(5,h.stars||0)));text('[data-hotel-location]',h.location);
      text('[data-hotel-min-price]',money(min.total));text('[data-hotel-price-caption]','За '+party(min,true)+' · '+nights(min.nights));
      text('[data-offer-context]','Все цены за '+party(min,true));text('[data-offer-comparison]','«+» — разница с минимальной ценой в этом списке.');
      $('[data-offer-comparison]').hidden=!list.some(o=>o.total>min.total);photo($('[data-hotel-hero]'),h);
      $('[data-hotel-offers]').innerHTML=list.map(o=>`<div class="st-offer-choice"><div class="st-offer ${state.selected===o?'st-offer--selected':''}"><div><div class="st-offer-name">${esc(o.room)}</div><div class="st-offer-meal">${esc(mealText(o))}</div><div class="st-offer-meta"><span>${dateText(o.day)} · ${nights(o.nights)} · ${esc(o.operator)}</span></div></div><div><div class="st-offer-price">${money(o.total)}</div><div class="st-offer-delta">${o.total>min.total?'+'+money(o.total-min.total):'Минимальная цена'}</div></div><button class="${o===min?'q8c2':'st-secondary'} st-offer-select" data-offer="${esc(o.key)}" aria-pressed="${state.selected===o}">${state.selected===o?'Выбран тур':'Выбрать тур'}</button></div></div>`).join('');
      const description=document.createElement('div');description.innerHTML=h.raw?.description||h.note||'';description.querySelectorAll('script,style,iframe,svg,object').forEach(n=>n.remove());
      text('.st-hotel-description',description.textContent||'Описание отеля пока не получено.');
      const about=$('[data-about-hotel]');about.querySelector('h2').textContent='Об отеле';about.querySelector('.st-amenities').innerHTML=(h.amenities||[]).map(a=>`<span class="st-amenity">${esc(a.label)}</span>`).join('');
      $('.st-aside-summary .st-included-list').innerHTML='<span>Состав пакета и обязательные доплаты требуют подтверждения.</span>';
      const heroText=$('.st-hero-controls span');if(heroText)heroText.textContent=h.photos?.length?'Фотографии отеля':'Фото пока нет';syncFavoriteControls();redrawIcons();
    }
    function renderTour() {
      const o=state.selected,h=currentHotel();if(!o||!h){show('results',false);return;}
      if(!tourOwner)tourOwner=window.AnyTourDesignV2Tour.mount({root,bridge,initialState:state.tourState,onChanged(change){
        if(!alive)return;
        state.tourSelection=change.selection;
        if(change.navigate==='hotel'){show('hotel');return;}
        redrawIcons();
      }});
      tourOwner.render({offer:o,hotel:h,screen:state.screen});
      redrawIcons();
    }
    function renderGallery() {const h=currentHotel();if(!h)return;text('[data-gallery-title]',h.name);photo($('[data-gallery-photo]'),h,photoIndex);text('[data-gallery-counter]',h.photos?.length?(photoIndex+1)+' / '+h.photos.length:'Нет фото');$('.st-gallery-thumbs').innerHTML='';$$('[data-direction]').forEach(n=>n.hidden=(h.photos?.length||0)<2);}
    function openSearch() {
      ++originEpoch;originPending=false;draft={...state.search,ages:[...state.search.ages]};draftFilters=cloneFilters(state.filters);editor=null;agesReviewNeeded=false;renderSearch();
    }
    function renderSearch() {
      if(!draft||!dictionaries)return;
      $$('[data-screen="search"] [data-action="results"]').forEach(node=>node.hidden=!state.hasSearched);
      const form=$('[data-search-form]');
      form.querySelector('[type="submit"]').disabled=originPending;
      form.elements.origin.setAttribute('aria-busy',String(originPending));
      form.elements.origin.innerHTML=dictionaries.origins.map(name=>`<option ${name===draft.origin?'selected':''}>${esc(name)}</option>`).join('');
      form.elements.country.innerHTML=dictionaries.countries.map(c=>`<option value="${esc(c.id)}" ${String(c.id)===String(draft.country)?'selected':''}>${esc(c.name)}</option>`).join('');
      text('[data-destination-country]',countryName(draft.country));text('[data-destination-summary]',destinationSummary());
      text('[data-search-date-value]',dateRange(draft.from,draft.to));text('[data-search-night-value]',draft.minNights===draft.maxNights?nights(draft.minNights):draft.minNights+'–'+draft.maxNights+' ночей');
      text('[data-search-travelers-value]',party(draft));text('[data-search-adult-value]',draft.adults);
      const children=$('[data-search-children]');
      if(children.querySelectorAll('[data-native-age]').length!==draft.ages.length)children.innerHTML=draft.ages.map((age,i)=>`<div class="st-search-child"><label class="q8c4"><span>Ребёнок ${i+1}</span><select data-native-age="${i}" aria-label="Возраст ребёнка ${i+1} на дату возвращения" required><option value="" ${age===null?'selected':''}>Укажите возраст</option>${Array.from({length:18},(_,n)=>`<option value="${n}" ${age===n?'selected':''}>${n}${n===0?' · до 1 года':''}</option>`).join('')}</select></label><button class="st-search-remove-child" type="button" data-native-remove-child="${i}" aria-label="Убрать ребёнка ${i+1}">${icon('x')}</button></div>`).join('');
      else children.querySelectorAll('[data-native-age]').forEach((node,i)=>{node.value=draft.ages[i]??'';});
      $$('[data-search-adult]').forEach(n=>n.disabled=Number(n.dataset.searchAdult)<0?draft.adults<=1:draft.adults>=6);
      $('[data-search-add-child]').disabled=draft.ages.length>=3;
      text('[data-search-child-hint]',agesReviewNeeded?'Даты или длительность изменились. Проверьте возраста детей на дату возвращения и нажмите «Выбрать туристов». Сохранённые значения оставлены.':'Укажите возраст каждого ребёнка на дату возвращения.');
      $('[data-search-child-hint]').hidden=!draft.ages.length;
      const incomplete=draft.ages.findIndex(age=>!Number.isInteger(age)||age<0||age>17);
      text('[data-search-family-status]',incomplete>=0?'Укажите возраст ребёнка '+(incomplete+1)+' на дату возвращения.':agesReviewNeeded?'Подтвердите возраста детей на дату возвращения.':party(draft));
      $('[data-search-family-status]').classList.toggle('st-family-incomplete',incomplete>=0);
      $('[data-search-editor-done="travelers"]').disabled=incomplete>=0;
      const meals=dictionaries.meals||[];
      $('.st-condition-food-list').innerHTML=meals.map(m=>`<label class="q8c6"><input type="checkbox" data-native-form-meal="${esc(m.nameRu)}" ${draftFilters.meals.includes(m.nameRu)?'checked':''}><span><span class="st-condition-food-name">${esc(m.nameRu)}</span></span></label>`).join('');
      text('[data-search-food-value]',draftFilters.meals.length?draftFilters.meals.join(' · '):'Любое');
      text('[data-search-budget-value]',draftFilters.min||draftFilters.max!==null?[draftFilters.min?'от '+money(draftFilters.min):'',draftFilters.max!==null?'до '+money(draftFilters.max):''].filter(Boolean).join(' · '):'Без лимита');
      $$('[data-search-budget]').forEach(n=>n.value=draftFilters[n.dataset.searchBudget]??'');
      validateBudget();
      const nums={three:3,four:4,five:5};$$('[data-search-category]').forEach(n=>n.setAttribute('aria-pressed',String(n.dataset.searchCategory==='any'?!draftFilters.stars.length:draftFilters.stars.includes(nums[n.dataset.searchCategory]))));
      const panels={dates:'[data-search-calendar]',nights:'[data-search-nights-editor]',travelers:'[data-search-travelers-editor]',destination:'[data-destination-editor]',food:'[data-search-food-editor]',budget:'[data-search-budget-editor]'};
      for(const [name,sel]of Object.entries(panels))$(sel).hidden=editor!==name;
      for(const [name,sel]of Object.entries({dates:'[data-search-date-toggle]',nights:'[data-search-nights-toggle]',travelers:'[data-search-travelers-toggle]',destination:'[data-search-destination-toggle]',food:'[data-conditions-open="food"]',budget:'[data-conditions-open="budget"]'}))$$(sel).forEach(n=>n.setAttribute('aria-expanded',String(editor===name)));
      renderNights();
      if(editor==='nights')renderNights();if(editor==='dates')renderDates();if(editor==='destination')renderDestination();
      redrawIcons();
      syncPopup();
    }
    function renderNights(){
      const grid=$('.st-night-grid');if(!grid||!draft)return;
      if(grid.querySelectorAll('[data-native-night]').length!==28)grid.innerHTML=Array.from({length:28},(_,i)=>`<button type="button" data-native-night="${i+1}" aria-label="${nights(i+1)}">${i+1}</button>`).join('');
      grid.querySelectorAll('[data-native-night]').forEach(node=>{const n=Number(node.dataset.nativeNight),selected=n>=draft.minNights&&n<=draft.maxNights;node.className='q8c3'+(selected&&(n===draft.minNights||n===draft.maxNights)?' st-night-endpoint':'')+(selected&&n>draft.minNights&&n<draft.maxNights?' st-night-in-range':'');node.setAttribute('aria-pressed',String(selected));});
      const value=draft.minNights===draft.maxNights?nights(draft.minNights):draft.minNights+'–'+draft.maxNights+' ночей';
      const form=$('[data-search-form]');form.elements.minNights.value=draft.minNights;form.elements.maxNights.value=draft.maxNights;
      text('[data-search-night-selection]',value);text('[data-search-night-instruction]',nightAnchor!==null?'Выберите вторую границу или оставьте ровно '+nights(nightAnchor)+'.':'Первый тап — точная длительность, второй — диапазон. Между границами — до 10 ночей.');
      text('[data-search-night-error]',nightError);$('[data-search-night-error]').hidden=!nightError;
      text('[data-search-editor-done="nights"]','Выбрать '+value);
      $$('[data-search-night]').forEach(n=>n.setAttribute('aria-pressed',String(draft.minNights===draft.maxNights&&Number(n.dataset.searchNight)===draft.minNights)));
    }
    function chooseNight(n,preset=false){
      if(!Number.isInteger(n)||n<1||n>28)return;
      if(preset){draft.minNights=draft.maxNights=n;nightAnchor=null;nightError='';}
      else if(nightAnchor!==null){if(Math.abs(n-nightAnchor)>10)nightError='Между границами должно быть не больше 10 ночей. Выбранные значения сохранены.';else{draft.minNights=Math.min(n,nightAnchor);draft.maxNights=Math.max(n,nightAnchor);nightAnchor=null;nightError='';}}
      else{draft.minNights=draft.maxNights=n;nightAnchor=n;nightError='';}
      renderNights();text('[data-search-night-value]',draft.minNights===draft.maxNights?nights(draft.minNights):draft.minNights+'–'+draft.maxNights+' ночей');
    }
    const firstMonth=value=>value.slice(0,7)+'-01';
    const shiftMonth=(value,delta)=>{const d=new Date(value+'T12:00:00Z');return new Date(Date.UTC(d.getUTCFullYear(),d.getUTCMonth()+delta,1,12)).toISOString().slice(0,10);};
    const today=()=>{const value=new Date(),pad=part=>String(part).padStart(2,'0');return value.getFullYear()+'-'+pad(value.getMonth()+1)+'-'+pad(value.getDate());};
    function dateError(from,to){return !from||!to?'Выберите начало и конец периода вылета.':from<today()?'Сохранённый период истёк. Выберите даты заново.':to<from?'Конец периода не может быть раньше начала. Начало сохранено.':Date.parse(to)-Date.parse(from)>21*86400000?'Период может включать не больше 22 дат. Начало сохранено.':'';}
    function renderDates(){
      calendarMonth=calendarMonth||firstMonth(draft.from);const now=today();
      $('[data-calendar-months]').innerHTML=[0,1].map(offset=>{
        const start=shiftMonth(calendarMonth,offset),month=new Date(start+'T12:00:00Z'),blank=(month.getUTCDay()+6)%7,days=[];
        for(let d=start;firstMonth(d)===start;d=addDays(d,1))days.push(d);
        return `<div class="st-calendar-month"><div class="st-calendar-month-head">${offset?'':`<button class="st-calendar-nav" type="button" data-calendar-month="-1" aria-label="Предыдущий месяц" ${calendarMonth<=firstMonth(now)?'disabled':''}>${icon('chevron-left')}</button>`}<h3>${month.toLocaleDateString('ru-RU',{month:'long',year:'numeric',timeZone:'UTC'})}</h3><button class="st-calendar-nav ${offset?'':'st-calendar-mobile-next'}" type="button" data-calendar-month="1" aria-label="Следующий месяц">${icon('chevron-right')}</button></div><div class="st-calendar-weekdays" aria-hidden="true">${['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].map(d=>'<span>'+d+'</span>').join('')}</div><div class="st-calendar-days">${'<span aria-hidden="true"></span>'.repeat(blank)}${days.map(day=>`<button type="button" class="st-calendar-day${day===draft.from||day===draft.to?' st-selected':''}${day>draft.from&&day<draft.to?' st-in-range':''}${day===now?' st-today':''}" data-native-date="${day}" aria-label="${new Date(day+'T12:00:00Z').toLocaleDateString('ru-RU',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'})}, цена неизвестна; дату можно выбрать" aria-pressed="${day===draft.from||day===draft.to}" ${day<now?'disabled':''}><span>${Number(day.slice(8))}</span><span class="st-calendar-day-price">—</span></button>`).join('')}</div></div>`;
      }).join('');
      if(root.classList.contains('st-mobile'))$('[data-calendar-months]').before($('[data-calendar-done]').parentElement);
      text('[data-calendar-price-context]',draft.origin+' → '+countryName(draft.country)+' · '+(draft.minNights===draft.maxNights?nights(draft.minNights):draft.minNights+'–'+draft.maxNights+' ночей')+' · '+party(draft));
      text('[data-calendar-price-unit]','Даты вылета · цены за весь тур');text('[data-calendar-price-source]','Для этих условий цены ещё не запрошены. «—» не мешает выбрать дату.');
      text('[data-calendar-from-value]',dateText(draft.from));text('[data-calendar-to-value]',dateText(draft.to));
      $$('[data-calendar-boundary]').forEach(n=>n.setAttribute('aria-pressed',String(n.dataset.calendarBoundary===calendarBoundary)));
      text('[data-calendar-instruction]',calendarBoundary==='from'?'Выберите первую удобную дату вылета.':'Выберите последнюю удобную дату вылета.');
      text('[data-calendar-selection]',dateText(draft.from)+'–'+dateText(draft.to));
      const invalid=dateError(draft.from,draft.to);text('[data-calendar-error]',calendarError||invalid);$('[data-calendar-error]').hidden=!(calendarError||invalid);
      $('[data-calendar-done]').disabled=!!invalid;$('[data-calendar-done]').innerHTML='Выбрать '+dateText(draft.from)+'–'+dateText(draft.to)+icon('check');redrawIcons();
    }
    function destinationSummary(){const ids=draftFilters.hotelIds?.length?draftFilters.hotelIds:draftFilters.hotelId?[draftFilters.hotelId]:[];return ids.length?ids.map(id=>destinationHotels.get(id)?.name||state.hotels.find(h=>h.id===id)?.name||'Выбранный отель').join(', '):draftFilters.resorts.length?draftFilters.resorts.join(', '):'Вся страна';}
    const normalizeQuery=value=>String(value||'').toLocaleLowerCase('ru').replace(/ё/g,'е').trim();
    function focusDestinationChoice(choice){
      ($$('[data-native-destination]').find(node=>node.dataset.nativeDestination===choice.kind&&node.dataset.nativeDestinationId===String(choice.id))||$('[data-destination-query]')).focus({preventScroll:true});
    }
    function renderDestination(){
      if(!draft)return;const q=normalizeQuery($('[data-destination-query]').value),matches=value=>!q||normalizeQuery(value).includes(q);
      const active=window.document.activeElement;
      const focusedChoice=active?.dataset.nativeDestination?{kind:active.dataset.nativeDestination,id:active.dataset.nativeDestinationId}:null;
      const focusedChip=active?.dataset.nativeDestinationRemove?{kind:active.dataset.nativeDestinationRemove,value:active.dataset.nativeDestinationValue,index:$$('[data-native-destination-remove]').indexOf(active)}:null;
      const selectedIds=draftFilters.hotelIds?.length?draftFilters.hotelIds:draftFilters.hotelId?[draftFilters.hotelId]:[];
      const regionRows=regionCache.get(String(draft.country))||[];
      const groups=[{label:'Страны',rows:dictionaries.countries.filter(c=>matches(c.name)).map(c=>({kind:'country',id:String(c.id),name:c.name,detail:'Страна',selected:String(c.id)===String(draft.country)&&!draftFilters.resorts.length&&!selectedIds.length}))},{label:'Курорты',rows:regionRows.filter(r=>matches(r.name+' '+countryName(draft.country))).map(r=>({kind:'resort',id:r.id,name:r.name,detail:'Курорт · '+countryName(draft.country),selected:draftFilters.resorts.includes(r.name)}))},{label:'Отели',rows:destinationRows.filter(h=>matches(h.name)).map(h=>({kind:'hotel',id:h.id,name:h.name,detail:['Отель',h.subRegion||h.region||h.resort,countryName(draft.country)].filter(Boolean).join(' · '),selected:selectedIds.includes(h.id)}))}];
      $('[data-destination-results]').innerHTML=groups.filter(g=>g.rows.length).map(g=>`<div class="st-destination-group"><h3>${g.label}</h3>${g.rows.map(r=>`<button type="button" class="st-destination-choice cursor-interaction" data-native-destination="${r.kind}" data-native-destination-id="${esc(r.id)}" aria-pressed="${r.selected}"><span><strong>${esc(r.name)}</strong><small>${esc(r.detail)}</small></span>${icon(r.selected?'check':'plus')}</button>`).join('')}</div>`).join('')+(destinationLoading?'<p class="st-destination-empty" role="status">Ищем в справочнике…</p>':destinationError?`<p class="st-destination-empty" role="status">${esc(destinationError)}</p>`:groups.every(g=>!g.rows.length)?'<p class="st-destination-empty">В справочнике ничего не найдено. Попробуйте другое название.</p>':'');
      text('[data-destination-current-country]',countryName(draft.country));text('[data-destination-footer-summary]',countryName(draft.country)+' · '+destinationSummary());
      $('[data-destination-all]').setAttribute('aria-pressed',String(!selectedIds.length&&!draftFilters.resorts.length));
      const chips=selectedIds.length?selectedIds.map(id=>({kind:'hotel',value:id,label:destinationHotels.get(id)?.name||'Выбранный отель'})):draftFilters.resorts.map(name=>({kind:'resort',value:name,label:name}));
      $('[data-destination-chips]').innerHTML=chips.map(c=>`<button type="button" class="st-chip cursor-interaction" data-native-destination-remove="${c.kind}" data-native-destination-value="${esc(c.value)}" aria-label="Убрать ${esc(c.label)}">${esc(c.label)}${icon('x')}</button>`).join('');$('[data-destination-chips]').hidden=!chips.length;
      $('[data-destination-confirm]').hidden=!destinationPending;$('[data-destination-apply]').disabled=!!destinationPending;
      if(destinationPending){text('[data-destination-confirm-title]',destinationPending.kind==='country'?'Заменить направление?':'Заменить уточнения?');text('[data-destination-confirm-copy]','Сейчас: '+countryName(draft.country)+' · '+destinationSummary()+'. Новый выбор: '+destinationPending.name+'. Прежние уточнения будут заменены.');}
      redrawIcons();
      if(focusedChoice){if(destinationPending)$('[data-destination-keep]').focus();else focusDestinationChoice(focusedChoice);}
      if(focusedChip){const chips=$$('[data-native-destination-remove]');(chips.find(node=>node.dataset.nativeDestinationRemove===focusedChip.kind&&node.dataset.nativeDestinationValue===focusedChip.value)||chips[Math.min(focusedChip.index,chips.length-1)]||$('[data-destination-all]')).focus();}
    }
    async function loadDestination(){
      const epoch=++destinationEpoch,country=String(draft.country),query=$('[data-destination-query]').value.trim();destinationController?.abort();destinationController=new AbortController();const signal=destinationController.signal;
      destinationLoading=true;destinationError='';destinationRows=[];renderDestination();
      try{
        if(!regionCache.has(country)&&typeof bridge.regions==='function'){const rows=await bridge.regions(country);if(!alive||epoch!==destinationEpoch||editor!=='destination')return;const names=new Map();for(const row of rows||[])names.set(row.name,(names.get(row.name)||0)+1);regionCache.set(country,(rows||[]).filter(row=>row.country===country&&names.get(row.name)===1));}
        if(query.length>=2&&typeof bridge.lookupHotels==='function'){const rows=await bridge.lookupHotels(query,country,signal);if(!alive||epoch!==destinationEpoch||editor!=='destination')return;destinationRows=(rows||[]).filter(h=>Number.isSafeInteger(h.id)&&String(h.country)===country);for(const hotel of destinationRows)destinationHotels.set(hotel.id,hotel);}
      }catch(error){if(!alive||epoch!==destinationEpoch||signal.aborted)return;destinationError='Справочник временно недоступен. Выбор страны сохранён.';}
      finally{if(alive&&epoch===destinationEpoch&&editor==='destination'){destinationLoading=false;renderDestination();}}
    }
    function selectDestination(choice,confirmed=false){
      const ids=draftFilters.hotelIds?.length?draftFilters.hotelIds:draftFilters.hotelId?[draftFilters.hotelId]:[];
      const conflicts=choice.kind==='country'?(String(choice.id)!==String(draft.country)||draftFilters.resorts.length||ids.length):choice.kind==='resort'?ids.length: draftFilters.resorts.length||ids.some(id=>id!==choice.id);
      if(conflicts&&!confirmed){destinationPending=choice;renderDestination();return;}
      destinationPending=null;
      if(choice.kind==='country'){draft.country=choice.id;draftFilters.resorts=[];draftFilters.hotelIds=[];draftFilters.hotelId=0;loadDestination();}
      if(choice.kind==='resort'){draftFilters.hotelIds=[];draftFilters.hotelId=0;draftFilters.resorts=draftFilters.resorts.includes(choice.name)?draftFilters.resorts.filter(x=>x!==choice.name):[...draftFilters.resorts,choice.name];}
      if(choice.kind==='hotel'){draftFilters.resorts=[];draftFilters.hotelId=choice.id;draftFilters.hotelIds=[choice.id];}
      renderSearch();
      if(confirmed)focusDestinationChoice(choice);
    }
    function openEditor(name,opener){if(editor)closeEditor(true);editorSnapshot={draft:{...draft,ages:[...draft.ages]},filters:cloneFilters(draftFilters),agesReviewNeeded};editor=name;editorOpener=opener||$(editorFields[name].opener);nightAnchor=null;nightError='';calendarMonth=firstMonth(draft.from);calendarBoundary='from';calendarError='';destinationPending=null;$('[data-search-error]').hidden=true;renderSearch();if(name==='destination'){loadDestination();$('[data-destination-query]').focus();}}
    function validateBudget(){
      let error='';const values={min:0,max:null};
      for(const node of $$('[data-search-budget]')){const raw=node.value.trim().replace(/[ \u00a0\u202f]/g,'');const valid=raw===''||/^\d+$/.test(raw)&&Number.isSafeInteger(Number(raw));node.setAttribute('aria-invalid',String(!valid));if(!valid)error='Укажите целую сумму в рублях.';else values[node.dataset.searchBudget]=raw===''?(node.dataset.searchBudget==='max'?null:0):Number(raw);}
      if(!error&&values.max!==null&&values.min>values.max)error='Бюджет «от» должен быть не больше бюджета «до».';
      text('[data-search-budget-error]',error);$('[data-search-budget-error]').hidden=!error;$('[data-conditions-apply="budget"]').disabled=!!error;
      if(!error){draftFilters.min=values.min;draftFilters.max=values.max;}
      return !error;
    }
    function closeEditor(cancel){
      if(!cancel&&editor==='budget'&&!validateBudget()){($('[data-search-budget][aria-invalid="true"]')||$('[data-search-budget="max"]')).focus();return false;}
      if(!cancel&&editor==='dates'&&dateError(draft.from,draft.to)){$('[data-calendar-boundary="from"]').focus();return false;}
      if(!cancel&&editor==='travelers'&&draft.ages.some(age=>!Number.isInteger(age)||age<0||age>17)){$('[data-native-age="'+draft.ages.findIndex(age=>!Number.isInteger(age)||age<0||age>17)+'"]').focus();return false;}
      if(!cancel&&editor==='destination'&&destinationPending){$('[data-destination-keep]').focus();return false;}
      if(cancel&&editorSnapshot){draft=editorSnapshot.draft;draftFilters=editorSnapshot.filters;agesReviewNeeded=editorSnapshot.agesReviewNeeded;}
      else if(editorSnapshot&&draft.ages.length&&(editor==='dates'||editor==='nights')&&(draft.from!==editorSnapshot.draft.from||draft.to!==editorSnapshot.draft.to||draft.minNights!==editorSnapshot.draft.minNights||draft.maxNights!==editorSnapshot.draft.maxNights))agesReviewNeeded=true;
      if(!cancel&&editor==='travelers')agesReviewNeeded=false;
      ++destinationEpoch;destinationController?.abort();editor=null;editorSnapshot=null;destinationPending=null;renderSearch();editorOpener?.focus();editorOpener=null;return true;
    }
    function applyDictionarySnapshot(event){
      if(!alive||!event||event.generation<(state.dictionaryGeneration||0))return;
      state.dictionaryGeneration=event.generation;originPending=event.type==='pending';state.dictionaryPending=originPending;
      if(event.type==='complete'&&event.dictionaries){
        dictionaries=event.dictionaries;state.dictionaries=dictionaries;regionCache.clear();
        if(draft){draft.origin=event.origin;if(!dictionaries.countries.some(c=>String(c.id)===String(draft.country))){draft.country=dictionaries.countries[0]?.id;draftFilters.resorts=[];draftFilters.hotelIds=[];draftFilters.hotelId=0;}}
      }
      if(event.type==='error'){if(draft)draft.origin=event.origin;text('[data-search-error]',event.message||'Не удалось загрузить страны. Предыдущий город сохранён.');$('[data-search-error]').hidden=false;}
      if(draft){renderSearch();if(editor==='destination'&&!originPending)loadDestination();}
    }
    async function runSearch(preserveDate=false){
      if(editor&&!originPending&&!closeEditor(false))return;
      const invalid=originPending?'Подождите загрузки стран для выбранного города.':dateError(draft.from,draft.to)||(!Number.isInteger(draft.minNights)||!Number.isInteger(draft.maxNights)||draft.minNights<1||draft.maxNights>28||draft.minNights>draft.maxNights||draft.maxNights-draft.minNights>10?'Выберите от 1 до 28 ночей, с диапазоном до 10 ночей.':'')||(draftFilters.max!==null&&draftFilters.min>draftFilters.max?'Бюджет «от» должен быть не больше бюджета «до».':'')||(draft.ages.length>3||draft.ages.some(age=>!Number.isInteger(age)||age<0||age>17)?'Укажите возраст каждого ребёнка.':'')||(agesReviewNeeded&&draft.ages.length?'После изменения дат или ночей подтвердите возраста детей кнопкой «Выбрать туристов».':'');
      if(invalid){text('[data-search-error]',invalid);$('[data-search-error]').hidden=false;if(!originPending){if(dateError(draft.from,draft.to))openEditor('dates');else if(agesReviewNeeded||draft.ages.some(age=>!Number.isInteger(age)||age<0||age>17))openEditor('travelers');}return;}
      rememberRoute(false);
      state.search={...draft,ages:[...draft.ages]};state.filters=cloneFilters(draftFilters);state.hasSearched=true;state.restoredHistory=false;state.selected=null;resetResultLimit();if(!preserveDate)state.selectedDate=null;state.calendarPage=0;state.calendarRange=null;state.hotels=[];state.status='loading';state.message='';state.sources=null;
      $('[data-search-error]').hidden=true;show('results');const epoch=++searchEpoch;
      try{if(state.filters.resorts.length&&typeof bridge.regions==='function')await bridge.regions(state.search.country);if(!alive||epoch!==searchEpoch)return;const snapshot=await bridge.search(state.search,state.filters,event=>{if(!alive||epoch!==searchEpoch)return;state.hotels=event.hotels;state.status=event.status;state.message=event.message||'';state.sources=event.sources||null;state.searchGeneration=event.searchGeneration;if(state.screen==='results')renderResults();else if(state.screen==='filters')refreshFilterEditor();updateURL();});if(alive&&epoch===searchEpoch){state.hotels=snapshot.hotels;state.status=snapshot.status;state.message=snapshot.message||'';state.sources=snapshot.sources||null;if(state.screen==='results')renderResults();else if(state.screen==='filters')refreshFilterEditor();updateURL();}}
      catch(error){if(alive&&epoch===searchEpoch){state.status='error';state.message=error.message;state.sources=null;renderResults();}}
    }
    function handleClick(event){
      const button=event.target.closest('button');if(!button||!root.contains(button))return;
      const d=button.dataset;
      if(button.hasAttribute('data-native-remove-constraint')){
        const items=resultConstraints(),index=items.findIndex(item=>item.group===d.nativeRemoveConstraint&&item.value===d.nativeConstraintValue);
        if(index<0)return;
        const next=withoutConstraint(items[index]);Object.assign(state,next);resetResultLimit();renderResults();updateURL();
        const chips=$$('[data-native-remove-constraint].st-chip');
        (chips[Math.min(index,chips.length-1)]||$('[data-card-list] .st-name-button')||$('[data-empty-action]'))?.focus({preventScroll:true});return;
      }
      if(d.favorite!==undefined||button.hasAttribute('data-favorite-current')){
        const fromCard=d.favorite!==undefined,id=Number(fromCard?d.favorite:state.hotelId),cardIndex=fromCard?$$('[data-favorite]').indexOf(button):-1;
        state.favorites=state.favorites.includes(id)?state.favorites.filter(x=>x!==id):[...state.favorites,id];try{window.localStorage.setItem(favoriteKey,JSON.stringify(state.favorites));}catch{}
        if(state.screen==='results'){
          renderResults();const same=$(`[data-favorite="${id}"]`),remaining=$$('[data-favorite]');
          (same||remaining[Math.min(cardIndex,remaining.length-1)]||$('[data-empty-action]')||$('[data-action="favorites"]'))?.focus({preventScroll:true});
        }else{syncFavoriteControls();button.focus({preventScroll:true});}
        return;
      }
      if(d.hotelOpen||d.hotelAbout){state.hotelId=Number(d.hotelOpen||d.hotelAbout);show('hotel');$('[data-about-hotel]').hidden=!d.hotelAbout;$('[data-hotel-offers-panel]').hidden=!!d.hotelAbout;return;}
      if(d.offer){state.selected=findOffer(d.offer);if(state.selected){state.hotelId=state.selected.hotelId;rememberRoute(false);show('tour');}return;}
      if(d.hotelSection){$('[data-about-hotel]').hidden=d.hotelSection!=='about';$('[data-hotel-offers-panel]').hidden=d.hotelSection==='about';$$('[data-hotel-section]').forEach(n=>n.setAttribute('aria-pressed',String(n===button)));return;}
      if(d.galleryHotel){state.hotelId=Number(d.galleryHotel);photoIndex=0;show('gallery');return;}
      if(d.direction){const count=currentHotel()?.photos?.length||0;if(count){photoIndex=(photoIndex+Number(d.direction)+count)%count;renderGallery();}return;}
      if(button.hasAttribute('data-native-calendar')){state.calendarOpen=!state.calendarOpen;renderCalendar();redrawIcons();$('[data-native-calendar]')?.focus({preventScroll:true});return;}
      if(d.nativeCalendarPage){state.calendarPage+=d.nativeCalendarPage==='prev'?-1:1;renderCalendar();redrawIcons();const same=$(`[data-native-calendar-page="${d.nativeCalendarPage}"]:not(:disabled)`),fallback=$(`[data-native-calendar-page="${d.nativeCalendarPage==='prev'?'next':'prev'}"]:not(:disabled)`);(same||fallback)?.focus({preventScroll:true});updateURL();return;}
      if(button.hasAttribute('data-native-result-more')){const firstNew=rows()[state.resultLimit];state.resultLimit+=resultBatch;renderResults();const next=$('[data-native-result-more]'),first=firstNew?$(`[data-hotel-card="${firstNew.id}"] [data-hotel-open]`):null;(next||first)?.focus({preventScroll:true});rememberRoute(false);return;}
      if(d.nativeDay){const day=d.nativeDay;state.selectedDate=state.selectedDate===day?null:day;resetResultLimit();renderResults();updateURL();$(`[data-native-day="${day}"]`)?.focus({preventScroll:true});return;}
      const toggles={'searchDestinationToggle':'destination','searchDateToggle':'dates','searchNightsToggle':'nights','searchTravelersToggle':'travelers'};
      for(const [key,name]of Object.entries(toggles))if(button.hasAttribute('data-'+key.replace(/[A-Z]/g,c=>'-'+c.toLowerCase()))){openEditor(name,button);return;}
      if(d.conditionsOpen){openEditor(d.conditionsOpen,button);return;}
      if(d.searchEditorCancel||d.conditionsCancel||button.hasAttribute('data-destination-cancel')||button.hasAttribute('data-search-calendar-close')){closeEditor(true);return;}
      if(d.searchEditorDone||d.conditionsApply||button.hasAttribute('data-calendar-done')||button.hasAttribute('data-destination-apply')){closeEditor(false);return;}
      if(d.searchAdult){draft.adults=Math.max(1,Math.min(6,draft.adults+Number(d.searchAdult)));renderSearch();return;}
      if(button.hasAttribute('data-search-add-child')){if(draft.ages.length<3){draft.ages.push(null);renderSearch();$('[data-native-age="'+(draft.ages.length-1)+'"]').focus({preventScroll:true});}return;}
      if(d.nativeRemoveChild!==undefined){const index=Number(d.nativeRemoveChild);draft.ages.splice(index,1);renderSearch();($('[data-native-age="'+Math.min(index,draft.ages.length-1)+'"]')||$('[data-search-add-child]')).focus({preventScroll:true});return;}
      if(d.nativeNight){chooseNight(Number(d.nativeNight));return;}
      if(d.searchNight){chooseNight(Number(d.searchNight),true);return;}
      if(d.calendarMonth){const shifted=shiftMonth(calendarMonth,Number(d.calendarMonth));if(shifted>=firstMonth(today()))calendarMonth=shifted;renderDates();const controls=$$('[data-calendar-month]:not(.st-calendar-mobile-next)').filter(node=>!node.disabled);(controls.find(node=>node.dataset.calendarMonth===d.calendarMonth)||controls[0])?.focus({preventScroll:true});return;}
      if(d.calendarBoundary){calendarBoundary=d.calendarBoundary;calendarError='';renderDates();return;}
      if(d.nativeDate){if(calendarBoundary==='from'){draft.from=draft.to=d.nativeDate;calendarBoundary='to';calendarError='';}else{const invalid=dateError(draft.from,d.nativeDate);if(invalid)calendarError=invalid;else{draft.to=d.nativeDate;calendarError='';}}renderDates();$('[data-native-date="'+d.nativeDate+'"]').focus();return;}
      if(d.nativeDestination){const kind=d.nativeDestination,id=d.nativeDestinationId;const row=kind==='country'?dictionaries.countries.find(c=>String(c.id)===id):kind==='resort'?(regionCache.get(String(draft.country))||[]).find(r=>String(r.id)===id):destinationRows.find(h=>String(h.id)===id);if(row)selectDestination({kind,id:kind==='hotel'?Number(id):id,name:row.name});return;}
      if(button.hasAttribute('data-destination-replace')){if(destinationPending)selectDestination(destinationPending,true);return;}
      if(button.hasAttribute('data-destination-keep')){const choice=destinationPending;destinationPending=null;renderDestination();if(choice)focusDestinationChoice(choice);return;}
      if(button.hasAttribute('data-destination-clear')){$('[data-destination-query]').value='';loadDestination();$('[data-destination-query]').focus();return;}
      if(button.hasAttribute('data-destination-all')){draftFilters.resorts=[];draftFilters.hotelIds=[];draftFilters.hotelId=0;destinationPending=null;renderSearch();return;}
      if(d.nativeDestinationRemove){if(d.nativeDestinationRemove==='hotel'){draftFilters.hotelIds=draftFilters.hotelIds.filter(id=>id!==Number(d.nativeDestinationValue));draftFilters.hotelId=draftFilters.hotelIds[0]||0;}else draftFilters.resorts=draftFilters.resorts.filter(name=>name!==d.nativeDestinationValue);renderSearch();return;}
      if(d.searchCategory){const n={three:3,four:4,five:5}[d.searchCategory];draftFilters.stars=n?(draftFilters.stars.includes(n)?draftFilters.stars.filter(x=>x!==n):[...draftFilters.stars,n]):[];renderSearch();return;}
      if(button.hasAttribute('data-search-food-any')){draftFilters.meals=[];renderSearch();return;}
      const action=d.action;
      if(action==='search'){show('search');return;}if(action==='results'){closeEditor(true);show(state.hasSearched?'results':'search');return;}
      if(action==='hotel'){show('hotel');return;}if(action==='tour'){show('tour');return;}if(action==='lead'){show('lead');return;}
      if(action==='best-tour'){state.selected=eligible(currentHotel())[0];rememberRoute(false);show('tour');return;}
      if(action==='gallery'){photoIndex=0;show('gallery');return;}
      if(action==='favorites'){state.onlyFavorites=!state.onlyFavorites;resetResultLimit();show('results');return;}
      if(action==='reset-draft'){if(filterDraft){filterDraft=defaultFilters();filterBudgetValues={min:'',max:''};renderFilterEditor();}return;}
      if(action==='reset-filters'){state.filters=defaultFilters();state.selectedDate=null;resetResultLimit();renderResults();updateURL();return;}
      if(action==='filters'){filterDraft=null;filterBudgetValues=null;state.nativeFilters=null;show('filters');return;}
      if(action==='close-filters'){filterDraft=null;show('results');return;}
      if(action==='apply-filters'){if(!filterDraft||!validateResultBudget($('[data-filter-form]')))return;state.filters=filterDraft;resetResultLimit();show('results');updateURL();return;}
    }
    root.addEventListener('click',handleClick,{signal:listeners.signal});
    root.addEventListener('change',async event=>{const node=event.target,d=node.dataset;
      if(d.nativeFilter){const f=filterDraft||state.filters,key=d.nativeFilter,value=key==='stars'?Number(node.value):node.value;f[key]=node.checked?[...f[key],value]:f[key].filter(x=>x!==value);if(filterDraft)updateFilterDraft();else{resetResultLimit();renderResults();updateURL();[...$('[data-filter-sidebar]').querySelectorAll('[data-native-filter]')].find(control=>control.dataset.nativeFilter===key&&control.value===node.value)?.focus({preventScroll:true});}}
      if(d.nativeBudget){const inline=!filterDraft,kind=d.nativeBudget,type=node.type;changeResultBudget(node,true);if(inline)[...$('[data-filter-sidebar]').querySelectorAll('[data-native-budget]')].find(control=>control.dataset.nativeBudget===kind&&control.type===type)?.focus({preventScroll:true});}
      if(d.nativeFormMeal){draftFilters.meals=node.checked?[...draftFilters.meals,d.nativeFormMeal]:draftFilters.meals.filter(x=>x!==d.nativeFormMeal);}
      if(d.nativeAge!==undefined){draft.ages[Number(d.nativeAge)]=node.value===''?null:Number(node.value);renderSearch();}
      if(node.classList.contains('st-sort')){state.sort=node.value;resetResultLimit();renderResults();updateURL();}
      if(node.name==='origin'){
        const epoch=++originEpoch,requested=node.value;originPending=true;
        try{const next=await bridge.changeOrigin(requested);if(!alive||epoch!==originEpoch||state.screen!=='search')return;dictionaries=next;state.dictionaries=next;draft.origin=requested;regionCache.clear();if(!dictionaries.countries.some(c=>String(c.id)===String(draft.country))){draft.country=dictionaries.countries[0]?.id;draftFilters.resorts=[];draftFilters.hotelIds=[];draftFilters.hotelId=0;}renderSearch();}
        catch(error){if(alive&&epoch===originEpoch){text('[data-search-error]',error.message);$('[data-search-error]').hidden=false;node.value=draft.origin;}}
        finally{if(alive&&epoch===originEpoch)originPending=false;}
      }
    },{signal:listeners.signal});
    root.addEventListener('input',event=>{const d=event.target.dataset;if(event.target.hasAttribute('data-destination-query'))loadDestination();if(d.searchBudget)validateBudget();if(d.nativeBudget)changeResultBudget(event.target,false);}, {signal:listeners.signal});
    root.addEventListener('keydown',event=>{if(event.key==='Escape'&&editor){event.preventDefault();closeEditor(true);}}, {signal:listeners.signal});
    popup.addEventListener('cancel',event=>{event.preventDefault();closeEditor(true);},{signal:listeners.signal});
    popup.addEventListener('click',event=>{if(event.target!==popup)return;const box=popup.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)closeEditor(true);},{signal:listeners.signal});
    $('[data-search-form]').addEventListener('submit',event=>{event.preventDefault();runSearch();},{signal:listeners.signal});
    $('[data-lead-form]').addEventListener('submit',event=>event.preventDefault(),{signal:listeners.signal});
    window.addEventListener('popstate',event=>{const saved=historyRoutes.get(event.state?.anytourNative?.id);if(saved){++searchEpoch;closeEditor(true);Object.assign(state,saved,{search:{...saved.search,ages:[...saved.search.ages]},filters:cloneFilters(saved.filters),restoredHistory:true});if(state.status==='loading'){state.status='partial';state.message='Сохранённые предложения. Для нового поиска измените условия и нажмите «Найти туры».';}show(saved.screen,false);}else show(state.hasSearched?'results':'search',false);},{signal:listeners.signal});
    const ready=(async()=>{
      const initial=await bridge.init();if(!alive)return;
      dictionaries=state.dictionaries||initial.dictionaries;state.dictionaries=dictionaries;if(!state.search){state.search=initial.search;state.filters={...defaultFilters(),...initial.filters};const p=new URLSearchParams(window.location.search);state.sort=['price','name'].includes(p.get('sort'))?p.get('sort'):'recommended';state.onlyFavorites=p.get('favoritesOnly')==='1';const chosen=p.get('selectedDate');state.selectedDate=chosen&&/^\d{4}-\d{2}-\d{2}$/.test(chosen)&&chosen>=state.search.from&&chosen<=state.search.to?chosen:null;try{const stored=JSON.parse(window.localStorage.getItem(favoriteKey)||'[]');state.favorites=Array.isArray(stored)?stored.filter(Number.isSafeInteger):[];}catch{}}
      root.style.setProperty('--photo','none');root.style.setProperty('--marina-photo','none');
      $$('[data-screen="states"],[data-screen="success"]').forEach(node=>node.remove());
      const input=state.nativeInput,s=state.screen;show(s,false);if(input&&s==='search'){draft={...input.draft,ages:[...input.draft.ages]};draftFilters=cloneFilters(input.filters);editor=input.editor;editorSnapshot=input.editorSnapshot;editorOpener=editor?$(editorFields[editor].opener):null;agesReviewNeeded=input.agesReviewNeeded;nightAnchor=input.nightAnchor;nightError=input.nightError;calendarMonth=input.calendarMonth;calendarBoundary=input.calendarBoundary;calendarError=input.calendarError;for(const [id,hotel]of input.destinationHotels||[])destinationHotels.set(id,hotel);for(const [id,rows]of input.regionCache||[])regionCache.set(id,rows);renderSearch();if(editor==='destination')loadDestination();}applyDictionarySnapshot(bridge.getDictionarySnapshot?.());onReady?.();root.hidden=false;syncPopup();
      // This intent belongs to the page lifetime, not a historical screen.
      // Route snapshots omit it so Back cannot authorize another supplier start.
      if(initial.requestedSearch&&!state.urlSearchConsumed&&!state.hasSearched){state.urlSearchConsumed=true;openSearch();await runSearch(true);}
    })();
    return {ready,applyDictionarySnapshot,getState(){if(tourOwner)state.tourState=tourOwner.getState();state.nativeInput=draft?{draft:{...draft,ages:[...draft.ages]},filters:cloneFilters(draftFilters),editor,editorSnapshot,agesReviewNeeded,nightAnchor,nightError,calendarMonth,calendarBoundary,calendarError,destinationHotels:[...destinationHotels],regionCache:[...regionCache]}:null;return state;},render(){if(state.screen==='filters')refreshFilterEditor();else renderResults();if(state.hasSearched)updateURL();},destroy(){alive=false;++searchEpoch;++originEpoch;++destinationEpoch;tourOwner?.destroy();listeners.abort();destinationController?.abort();if(popup.open&&typeof popup.close==='function')popup.close();submitWrap.append(submitButton);submitWrap.style.removeProperty('min-height');for(const {panel,marker}of editorHomes){marker.replaceWith(panel);delete panel.dataset.nativePopupPanel;}popup.remove();}};
  }
  window.AnyTourDesignV2Native=Object.freeze({mount});
})(window);
