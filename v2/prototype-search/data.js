(function (root) {
  'use strict';
  // Presentation adapter only. Existing API actions, source DTOs and DB guards stay authoritative.
  const rt = root.V2Runtime;
  const local = '/_preview/search3-local-candidate/';
  const catalog = { departures: [], countries: [], meals: [], regions: {} };
  const regionRequests=new Map();
  const quoteReceipts = new WeakMap();
  const calendarWindows=new Map(),CALENDAR_REUSE_MS=30000,CALENDAR_CACHE_BYTES=4*1024*1024;
  let calendarWindowBytes=0,calendarVersion=0;
  let generation = 0, searchId = 0, timer = null, notify = () => {}, raw = [], context = null, searchParams = null, activeSearch = null;
  const owner = root.Search3CanonicalProfilesV1.create(() => publish());
  function nativeEndpoint(value,expectedPath){
    if(typeof value!=='string'||typeof expectedPath!=='string'||!root.location)return null;
    try{const url=new URL(value,root.location.href);return url.origin===root.location.origin&&url.pathname===expectedPath&&!url.search&&!url.hash?url:null;}catch{return null;}
  }
  const text = value => typeof value === 'object' && value ? String(value.russianName || value.name || '') : String(value ?? '');
  const amount = value => { const n = Number(value && typeof value === 'object' ? value.value : value); return Number.isFinite(n) && n > 0 ? n : null; };
  const date = value => { const s = String(value || '').slice(0, 10), p = s.match(/^(\d{2})\.(\d{2})\.(\d{4})$/); return p ? `${p[3]}-${p[2]}-${p[1]}` : /^\d{4}-\d{2}-\d{2}$/.test(s) ? s : ''; };
  const plus = (d, n) => new Date(new Date(d + 'T12:00:00Z').getTime() + n * 86400000).toISOString().slice(0, 10);
  const mealAliases=Object.freeze({
    RO:'Без питания','NO MEAL':'Без питания','БЕЗ ПИТАНИЯ':'Без питания',
    BB:'Завтраки','BED AND BREAKFAST':'Завтраки','ЗАВТРАК':'Завтраки','ЗАВТРАКИ':'Завтраки','ТОЛЬКО ЗАВТРАК':'Завтраки',
    HB:'Полупансион','HALF BOARD':'Полупансион','ПОЛУПАНСИОН':'Полупансион',
    FB:'Полный пансион','FULL BOARD':'Полный пансион','ПОЛНЫЙ ПАНСИОН':'Полный пансион',
    AI:'Всё включено',ALL:'Всё включено','ALL INCLUSIVE':'Всё включено','ВСЕ ВКЛЮЧЕНО':'Всё включено','ВСЁ ВКЛЮЧЕНО':'Всё включено',
    UAI:'Ультра всё включено','ULTRA ALL INCLUSIVE':'Ультра всё включено',
    'УЛЬТРА ВСЕ ВКЛЮЧЕНО':'Ультра всё включено','УЛЬТРА ВСЁ ВКЛЮЧЕНО':'Ультра всё включено',
    'УЛЬТРА ВСЕ ВКЛ':'Ультра всё включено','УЛЬТРА ВСЁ ВКЛ':'Ультра всё включено',
    'AI-WITHOUT ALCOHOL':'Всё включено без алкоголя','AI WITHOUT ALCOHOL':'Всё включено без алкоголя',
    'ВСЕ ВКЛЮЧЕНО БЕЗ АЛКОГОЛЯ':'Всё включено без алкоголя','ВСЁ ВКЛЮЧЕНО БЕЗ АЛКОГОЛЯ':'Всё включено без алкоголя'
  });
  function meal(value){
    const label=text(value).trim(),record=catalog.meals.find(x=>value?.id&&String(x.id)===String(value.id)
      ||text(x).trim().toLocaleLowerCase('ru-RU')===label.toLocaleLowerCase('ru-RU'));
    const candidates=[label,text(value?.fullName),text(value?.russianName),text(record?.fullName),text(record?.russianName),text(record)]
      .map(value=>value.trim()).filter(Boolean);
    for(const candidate of candidates){
      const normalized=candidate.toUpperCase().replace(/\s+/g,' ');
      if(mealAliases[normalized])return mealAliases[normalized];
      const coded=normalized.match(/^(RO|BB|HB|FB|AI|UAI|ALL)\s*(?:[-—:]\s*|\s+).+$/);
      if(coded&&mealAliases[coded[1]])return mealAliases[coded[1]];
    }
    return candidates.find(candidate=>!(/^[A-Z]{1,7}\+?$/).test(candidate))||candidates[0]||'';
  }
  const operatorAliases=Object.freeze({
    ANEX:'ANEX','ANEX TOUR':'ANEX','АНЕКС':'ANEX','АНЕКС ТУР':'ANEX',
    'FUN&SUN':'FUN&SUN','FUN SUN':'FUN&SUN','FUN&SUN (RU)':'FUN&SUN',
    'BIBLIO GLOBUS':'Библио-Глобус','БИБЛИО ГЛОБУС':'Библио-Глобус',
    INTOURIST:'Интурист','ИНТУРИСТ':'Интурист',
    CORAL:'Coral Travel','CORAL TRAVEL':'Coral Travel',
    PEGAS:'Pegas Touristik','PEGAS TOURISTIK':'Pegas Touristik','PEGAS TOURISTIC':'Pegas Touristik',
    SUNMAR:'Sunmar','SUNMAR TOUR':'Sunmar'
  });
  function operator(value){
    const label=text(value).trim();if(!label)return '';
    const key=label.toLocaleUpperCase('ru-RU').replace(/[._-]+/g,' ').replace(/\s*&\s*/g,'&').replace(/\s+/g,' ').trim();
    return operatorAliases[key]||label;
  }
  const image = value => { const raw=typeof value === 'object' && value ? value.url || value.src : value; if(typeof raw!=='string'||!raw.trim())return ''; try { const url = new URL(raw, root.location.href); return ['https:', 'http:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } };
  async function regions(country) {
    const key=String(country);
    if(catalog.regions[key])return catalog.regions[key];
    if(regionRequests.has(key))return regionRequests.get(key);
    const request=rt.api('regions',{countryId:key}).then(rows=>{
      if(!Array.isArray(rows))throw new Error('Не удалось загрузить курорты.');
      const seen=new Set(),items=[];
      for(const row of rows){const id=String(row?.id||''),name=text(row).trim();if(!/^[1-9][0-9]*$/.test(id)||!name||seen.has(id)||String(row.countryId)!==key)throw new Error('Не удалось проверить справочник курортов.');seen.add(id);items.push({id,name,country:key});}
      catalog.regions[key]=items;return items;
    }).finally(()=>regionRequests.delete(key));
    regionRequests.set(key,request);return request;
  }
  function regionIds(s,filters) {
    return (filters.resorts||[]).map(name=>{
      const found=(catalog.regions[String(s.country)]||[]).filter(row=>row.name===name);
      if(found.length!==1)throw new Error('Выберите курорт из загруженного справочника.');
      return found[0].id;
    });
  }
  function params(s, hotelIds = [], filters = {}) {
    const departure = catalog.departures.find(x => text(x) === s.origin || String(x.id) === s.origin);
    if (!departure || !catalog.countries.some(x => String(x.id) === String(s.country))) throw new Error('Выберите город вылета и страну из загруженного списка.');
    if (!date(s.from) || !date(s.to) || s.from > s.to || (new Date(s.to) - new Date(s.from)) / 86400000 > 21) throw new Error('Выберите диапазон вылета не больше 21 дня.');
    if (!Number.isInteger(s.adults) || s.adults < 1 || s.adults > 6 || !Array.isArray(s.ages) || s.ages.length > 3 || s.ages.some(x => !Number.isInteger(x) || x < 0 || x > 17)) throw new Error('Укажите возраст каждого ребёнка.');
    if (!Number.isInteger(s.minNights) || !Number.isInteger(s.maxNights) || s.minNights < 1 || s.maxNights > 28 || s.maxNights < s.minNights || s.maxNights - s.minNights > 10) throw new Error('Проверьте диапазон ночей.');
    const selectedMeal=filters.meals?.length===1?meal(filters.meals[0]):'';
    const chosenMeal=selectedMeal?catalog.meals.find(x=>meal(x)===selectedMeal):null;
    if(selectedMeal&&!chosenMeal)throw new Error('Выберите питание из загруженного справочника.');
    const stars=(filters.stars||[]).filter(x=>Number.isInteger(x)&&x>=1&&x<=5);
    return {departureId:String(departure.id),countryId:String(s.country),dateFrom:s.from,dateTo:s.to,nightsFrom:s.minNights,nightsTo:s.maxNights,adults:s.adults,childs:[...s.ages].sort((a,b)=>a-b),meal:chosenMeal?String(chosenMeal.id):'',hotelCategory:stars.length?String(Math.min(...stars)):'',hotelRating:'',hotelTypes:[],hotelIds:hotelIds.map(String),hotelServices:[],arrivalId:'',regionIds:regionIds(s,filters),subregionIds:[],operatorIds:[],priceFrom:filters.min>0?String(filters.min):'',priceTo:filters.max!==null&&filters.max!==undefined&&filters.max!==''?String(filters.max):'',currency:'RUB',onlyCharter:false,onlyDirect:false};
  }
  function sameScope(request, response) {
    if (!response || response.scopeVersion !== 1 || Object.keys(response).length !== Object.keys(request).length + 1) return false;
    return Object.keys(request).every(key => {
      const a=request[key], b=response[key];
      if (Array.isArray(a)) return Array.isArray(b) && JSON.stringify(a.map(String).sort()) === JSON.stringify(b.map(String).sort());
      return typeof a === 'boolean' ? a === b : b !== null && String(a) === String(b);
    });
  }
  async function db(s, signal, hotelIds=[], filters={}) {
    const p = params(s, hotelIds,filters);
    const response = await fetch(local+'data/search3-local-results-read-v1.php', {method:'POST',credentials:'same-origin',cache:'no-store',signal,headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify({params:p})});
    if (!response.ok) throw new Error('Цены из базы временно недоступны.');
    const payload=await response.json(),data=payload?.data;
    if(payload?.ok!==true||!data)throw new Error('Цены из базы временно недоступны.');
    if (!sameScope(p,data.scope) || !root.AnyTourLocalDbProviderV1.parse(data)) throw new Error('Ответ базы не соответствует параметрам поездки.');
    return data;
  }
  function amenities(h) {
    const groups=h.hotelInformation?.services?.tags||h.services?.tags,items=new Map();
    if(!Array.isArray(groups))return [];
    for(const group of groups){
      // Saved structured hotel facts only. Promotional/availability badges
      // (group 7) are not amenities and cannot promise a bookable tour.
      if(!group||![1,2,3,5,8].includes(group.id)||!text(group.name).trim()||!Array.isArray(group.items))continue;
      for(const item of group.items){
        if(!item||!Number.isSafeInteger(item.id)||item.id<1||!text(item.name).trim())continue;
        const key=group.id+':'+item.id;
        items.set(key,{key,label:text(item.name).trim(),group:text(group.name).trim(),groupId:group.id});
      }
    }
    return [...items.values()];
  }
  function hotel(h, s) {
    const own=Number(h.anytourHotelId || h.id), photos=[h.primaryImage,...(Array.isArray(h.images)?h.images:[])].map(image).filter(Boolean);
    const rating=Number(h.rating && typeof h.rating === 'object' ? h.rating.value : h.rating);
    const ratingScale=Number(h.rating && typeof h.rating === 'object' ? h.rating.scale : 5);
    const region=text(h.region).trim(),subRegion=text(h.subRegion).trim();
    return {id:own,legacyIds:(h.canonicalLegacyIds || []).map(String),name:text(h.name),country:String(s.country),region,subRegion,resort:subRegion||region,stars:Number(h.category)||0,rating:rating>0&&rating<=ratingScale?rating*5/ratingScale:null,beach:null,family:null,spa:null,pool:null,amenities:amenities(h),photos:[...new Set(photos)].slice(0,12),note:text(h.description),tag:'',raw:h,offers:[]};
  }
  function offer(t, h, s, index) {
    const price=amount(t.price), day=date(t.date), nights=Number(t.nights);
    if(!price || !day || !Number.isInteger(nights) || nights<1) return null;
    const provider=String(t.provider||'tourvisor').toLowerCase();
    return {key:encodeURIComponent(`${provider}:${String(t.id)}`),hotelId:h.id,day,nights,variant:index,total:price,returnDay:plus(day,nights),room:text(t.roomType)||'Номер уточняется',placement:text(t.placement),adults:s.adults,ages:[...s.ages],origin:s.origin,meal:meal(t.meal)||'Питание уточняется',operator:operator(t.operator)||'Туроператор уточняется',flight:t.isCharter===true?'charter':t.isCharter===false?'regular':'unknown',cached:t.cachedListing===true,provider,raw:t,search:structuredClone(s),fuel:t.fuelCharge??null,flightChoiceId:null};
  }
  function project(list,s) { return list.map(rawHotel=>{const h=hotel(rawHotel,s);h.offers=(rawHotel.tours||[]).map((t,i)=>offer(t,h,s,i)).filter(Boolean);return h;}).filter(h=>h.offers.length); }
  function tourvisorInventory(){
    return {hotels:raw.length,offers:raw.reduce((sum,h)=>sum+(Array.isArray(h?.tours)?h.tours.length:0),0)};
  }
  function canonicalUnion(){
    if(!owner||!context)return {hotels:0,offers:0,hotelsByProvider:{},offersByProvider:{},providerSets:{}};
    const rows=project(owner.read(raw,{}),context),hotelsByProvider={},offersByProvider={},providerSets={};let offers=0;
    for(const row of rows){
      const providers=[...new Set(row.offers.map(o=>o.provider).filter(Boolean))].sort();
      if(providers.length)providerSets[providers.join('+')]=(providerSets[providers.join('+')]||0)+1;
      for(const provider of providers)hotelsByProvider[provider]=(hotelsByProvider[provider]||0)+1;
      for(const rowOffer of row.offers){offers++;offersByProvider[rowOffer.provider]=(offersByProvider[rowOffer.provider]||0)+1;}
    }
    return {hotels:rows.length,offers,hotelsByProvider,offersByProvider,providerSets};
  }
  function publish() {if(owner&&context&&activeSearch&&current(activeSearch))notify({type:'results',hotels:project(owner.read(raw,{}),context)});}
  function current(run){return activeSearch===run&&run.generation===generation;}
  function stop(){
    clearCalendarWindows();generation++;clearTimeout(timer);timer=null;
    activeSearch?.controller.abort();activeSearch=null;
    return generation;
  }
  async function searchError(run,error){
    if(!current(run))return;
    // An expired supplier search cannot be continued. A lost response is not
    // evidence that search_continue failed: subsequent recovery only reads it.
    if(error?.status===404||error?.status===410)run.expired=true;
    if(!run.continued){
      run.sourceCounts.tourvisor={status:'error',...tourvisorInventory()};
      notify({type:'provider',provider:'tourvisor',status:'error'});
      await settleInitialSources(run);if(!current(run))return;
      run.pending=false;run.canContinue=!!run.searchId&&!run.expired;
      notify({type:'complete',partial:true,message:error.message,canContinue:run.canContinue,
        retryRead:run.resumeOnly,continued:false,resultLimitReached:false,sources:structuredClone(run.sourceCounts),union:canonicalUnion()});
      return;
    }
    run.pending=false;if(run.expired)run.canContinue=false;
    notify({type:'error',message:error.message,canContinue:run.canContinue&&!run.expired,retryRead:run.resumeOnly});
  }
  function readDatabase(run){
    return db(run.search,run.controller.signal,run.hotelIds,run.filters).then(data=>{
      if(!current(run)||!owner)return;
      clearCalendarWindows();root.AnyTourLocalDbProviderV1.apply(owner,data);
      const providerOfferCounts=data.providerOfferCounts&&typeof data.providerOfferCounts==='object'?structuredClone(data.providerOfferCounts):{};
      run.sourceCounts.database={status:'complete',hotels:Number(data.hotelCount)||0,offers:Number(data.offerCount)||0,storedOffers:Number(data.storedOfferCount)||0,providerOfferCounts};
      if(current(run))notify({type:'database',...run.sourceCounts.database});
    }).catch(error=>{
      if(!current(run))return;
      run.sourceCounts.database={status:'error'};
      notify({type:'database-error',message:error.message});
    });
  }
  function refreshDatabase(run){
    // Serial snapshots cannot overwrite a later snapshot with an earlier one.
    // Provider completion uses the same reader; no parallel store or DTO.
    return run.database=run.database.then(()=>{if(current(run))return readDatabase(run);});
  }
  async function digestRef(value){
    const subtle=root.crypto&&root.crypto.subtle,Encoder=root.TextEncoder||globalThis.TextEncoder;
    if(!subtle||typeof subtle.digest!=='function'||typeof Encoder!=='function')throw new Error('Offer identity digest unavailable');
    const bytes=await subtle.digest('SHA-256',new Encoder().encode(value));
    return Array.from(new Uint8Array(bytes),byte=>byte.toString(16).padStart(2,'0')).join('');
  }
  async function directAnexOffer(hotel,tour,run,p,seen){
    if(!tour||typeof tour!=='object'||!tour.price||tour.price.currency!=='RUB'
      ||typeof tour.price.amount!=='string'||!(/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/).test(tour.price.amount)
      ||Number(tour.price.amount)<=0)throw new Error('Invalid ANEX price');
    const day=date(tour.checkin),nights=Number(tour.nights),searchRef=String(tour.search_ref||''),offerRef=String(tour.offer_ref||'');
    if(!day||day<p.dateFrom||day>p.dateTo||!Number.isInteger(nights)||nights<Number(p.nightsFrom)||nights>Number(p.nightsTo)
      ||Number(tour.adults)!==Number(p.adults)||Number(tour.children)!==p.childs.length
      ||!(/^[a-f0-9]{32}$/).test(searchRef)||!(/^anex_online:[a-f0-9]{64}$/).test(offerRef)
      ||tour.selection_enabled!==false||tour.final_price_verified!==false||seen.has(offerRef))throw new Error('Invalid ANEX offer');
    seen.add(offerRef);
    const total=Number(tour.price.amount),mealName=meal(tour.meal)||'Питание уточняется';
    if(p.priceFrom&&total<Number(p.priceFrom)||p.priceTo&&total>Number(p.priceTo))return null;
    const selectedMeals=(run.filters.meals||[]).map(meal).filter(Boolean);
    if(selectedMeals.length&&!selectedMeals.includes(mealName))return null;
    const flight=String(tour.flight_type||'').toLowerCase(),offerIdentityDigest=await digestRef(offerRef);
    return {id:offerRef,offerRef,offerIdentityDigest,searchRef,provider:'anex',price:total,date:day,nights,
      meal:{name:mealName},roomType:text(tour.room)||'Номер уточняется',placement:'',
      operator:{name:'ANEX'},isCharter:flight==='charter'?true:flight==='regular'?false:undefined,
      cachedListing:false,selectionEnabled:false,finalPriceVerified:false,anexKind:String(tour.kind||''),
      anexLocalHotelId:hotel.local_id};
  }
  async function applyDirectAnex(run,data,p){
    if(!data||data.provider!=='anex'||data.generation!==run.generation||!Array.isArray(data.hotels)||data.hotels.length>300
      ||!data.date_range||data.date_range.from!==p.dateFrom||!date(data.date_range.to)||data.date_range.to<p.dateFrom||data.date_range.to>p.dateTo
      ||typeof data.search_ref!=='string'||!(/^[a-f0-9]{32}$/).test(data.search_ref))throw new Error('Invalid ANEX search response');
    const expectedEnd=plus(p.dateFrom,6)<p.dateTo?plus(p.dateFrom,6):p.dateTo;
    if(data.date_range.to!==expectedEnd)throw new Error('Invalid ANEX date range');
    const seenHotels=new Set(),seenOffers=new Set(),prepared=[];let receivedOffers=0;
    for(const hotel of data.hotels){
      if(!hotel||!Number.isSafeInteger(hotel.local_id)||hotel.local_id<1||seenHotels.has(hotel.local_id)
        ||hotel.catalog?.source!=='tourvisor'||Number(hotel.catalog?.hotel_id)!==hotel.local_id
        ||!Array.isArray(hotel.tours)||hotel.tours.length<1||hotel.tours.length>300)throw new Error('Invalid ANEX hotel');
      seenHotels.add(hotel.local_id);receivedOffers+=hotel.tours.length;
      for(const tour of hotel.tours){
        if(tour.search_ref!==data.search_ref)throw new Error('Invalid ANEX search identity');
        const normalized=await directAnexOffer(hotel,tour,run,p,seenOffers);
        if(normalized)prepared.push({legacyHotelId:hotel.local_id,tour:normalized});
      }
    }
    if(receivedOffers>300)throw new Error('Invalid ANEX result size');
    owner.clearOffers('direct-anex');
    for(const entry of prepared)owner.upsertLegacyOffer(entry.legacyHotelId,entry.tour,{source:'direct-anex'});
    owner.refresh();
    const visibleHotels=new Set(prepared.map(entry=>entry.legacyHotelId)).size,visibleOffers=prepared.length,partialRange=data.date_range.to!==p.dateTo;
    run.sourceCounts.anex={status:partialRange?'partial':'complete',hotels:visibleHotels,offers:visibleOffers,
      receivedHotels:data.hotels.length,receivedOffers,mappedHotels:data.hotels.length,mappedOffers:receivedOffers,
      visibleHotels,visibleOffers,scopeFilteredOffers:Math.max(0,receivedOffers-visibleOffers),dateFrom:data.date_range.from,dateTo:data.date_range.to};
    return run.sourceCounts.anex;
  }
  async function enrichAnex(run,p){
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url||!current(run)){run.sourceCounts.anex={status:'skipped',hotels:0,offers:0};return;}
    notify({type:'provider',provider:'anex',status:'loading'});if(!current(run))return;
    try{
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:run.controller.signal,
        headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
        body:JSON.stringify({action:'search',generation:run.generation,params:p})});
      const payload=await response.json().catch(()=>null),data=payload&&payload.data;if(!current(run))return;
      if(!response.ok||payload?.ok!==true)throw new Error('ANEX search unavailable');
      const result=await applyDirectAnex(run,data,p);if(!current(run))return;
      notify({type:'provider',provider:'anex',...result});
    }catch(error){
      if(!current(run)||error?.name==='AbortError')return;
      run.sourceCounts.anex={status:'error',hotels:0,offers:0};
      notify({type:'provider',provider:'anex',status:'error'});
    }
  }
  async function directAndromedaOffer(hotel,tour,run,p,seen,data){
    const price=tour&&tour.price,context=tour&&tour.offer_context;
    if(!tour||typeof tour!=='object'||tour.provider!=='andromeda'||!price||price.currency!=='RUB'
      ||typeof price.amount!=='string'||!(/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/).test(price.amount)
      ||Number(price.amount)<=0||!context||context.provider!=='andromeda'
      ||context.search_ref!==data.search_ref||context.generation!==run.generation
      ||!Number.isInteger(context.page)||context.page<1||context.page>1000
      ||context.offer_ref!==tour.offer_ref||tour.selection_enabled!==false)throw new Error('Invalid Andromeda offer');
    const day=date(tour.checkin),nights=Number(tour.nights),offerRef=String(tour.offer_ref||'');
    if(!day||!Number.isInteger(nights)||nights<1||!(/^offer_[a-f0-9]{64}$/).test(offerRef)||seen.has(offerRef))throw new Error('Invalid Andromeda offer');
    if(tour.listing_price_ref!==undefined&&(!(/^listing_[a-f0-9]{64}$/).test(String(tour.listing_price_ref))))throw new Error('Invalid Andromeda listing price reference');
    seen.add(offerRef);
    if(day<p.dateFrom||day>p.dateTo||nights<Number(p.nightsFrom)||nights>Number(p.nightsTo))return null;
    const total=Number(price.amount),mealName=meal(tour.meal)||'Питание уточняется';
    if(p.priceFrom&&total<Number(p.priceFrom)||p.priceTo&&total>Number(p.priceTo))return null;
    const selectedMeals=(run.filters.meals||[]).map(meal).filter(Boolean);
    if(selectedMeals.length&&!selectedMeals.includes(mealName))return null;
    const flight=String(tour.flight_type||'').toLowerCase(),offerIdentityDigest=await digestRef(offerRef);
    const normalized={id:offerRef,offerRef,offerIdentityDigest,searchRef:data.search_ref,provider:'andromeda',price:total,date:day,nights,
      meal:{name:mealName},roomType:text(tour.room)||'Номер уточняется',placement:text(tour.placement),
      operator:tour.operator&&typeof tour.operator==='object'?structuredClone(tour.operator):{name:text(tour.operator)||'Туроператор уточняется'},
      isCharter:flight==='charter'?true:flight==='regular'?false:undefined,cachedListing:false,selectionEnabled:false,bookingEnabled:false,
      finalPriceVerified:false,quoteRequired:true,andromedaLocalHotelId:hotel.local_id,offer_context:structuredClone(context)};
    if(tour.listing_price_ref!==undefined)normalized.listing_price_ref=String(tour.listing_price_ref);
    if(tour.base_search_price&&typeof tour.base_search_price==='object')normalized.base_search_price=structuredClone(tour.base_search_price);
    if(tour.search_surcharge&&typeof tour.search_surcharge==='object')normalized.search_surcharge=structuredClone(tour.search_surcharge);
    return normalized;
  }
  async function applyDirectAndromeda(run,data,p){
    if(!data||data.provider!=='andromeda'||data.generation!==run.generation||!Array.isArray(data.hotels)||data.hotels.length>5000
      ||!data.date_range||data.date_range.from!==p.dateFrom||data.date_range.to!==p.dateTo
      ||typeof data.search_ref!=='string'||!(/^[a-f0-9]{64}$/).test(data.search_ref)
      ||!['complete','partial'].includes(data.status)||data.selection_enabled!==false||data.first_page_only!==false)throw new Error('Invalid Andromeda search response');
    const seenHotels=new Set(),seenOffers=new Set(),prepared=[];let projectedOffers=0;
    for(const hotel of data.hotels){
      if(!hotel||!Number.isSafeInteger(hotel.local_id)||hotel.local_id<1||hotel.mapping_status!=='resolved'||seenHotels.has(hotel.local_id)
        ||!Array.isArray(hotel.tours)||hotel.tours.length<1)throw new Error('Invalid Andromeda hotel');
      seenHotels.add(hotel.local_id);projectedOffers+=hotel.tours.length;
      if(projectedOffers>15000)throw new Error('Invalid Andromeda result size');
      for(const tour of hotel.tours){
        const normalized=await directAndromedaOffer(hotel,tour,run,p,seenOffers,data);
        if(normalized)prepared.push({legacyHotelId:hotel.local_id,tour:normalized});
      }
    }
    owner.clearOffers('direct-andromeda');
    for(const entry of prepared)owner.upsertLegacyOffer(entry.legacyHotelId,entry.tour,{source:'direct-andromeda'});
    owner.refresh();
    const receivedOffers=Number.isInteger(data.received_offers)&&data.received_offers>=projectedOffers?data.received_offers:projectedOffers;
    const mappedOffers=Number.isInteger(data.mapped_offers)&&data.mapped_offers>=projectedOffers?data.mapped_offers:projectedOffers;
    const visibleHotels=new Set(prepared.map(entry=>entry.legacyHotelId)).size,visibleOffers=prepared.length;
    run.sourceCounts.andromeda={status:data.status==='partial'?'partial':'complete',
      hotels:visibleHotels,offers:visibleOffers,receivedHotels:data.hotels.length,mappedHotels:data.hotels.length,
      projectedOffers,receivedOffers,mappedOffers,visibleHotels,visibleOffers,
      scopeFilteredOffers:Math.max(0,projectedOffers-visibleOffers),dateFrom:data.date_range.from,dateTo:data.date_range.to};
    return run.sourceCounts.andromeda;
  }
  async function enrichAndromeda(run,p){
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaApi,'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php');
    if(!url||!current(run)){run.sourceCounts.andromeda={status:'skipped',hotels:0,offers:0};return;}
    notify({type:'provider',provider:'andromeda',status:'loading'});if(!current(run))return;
    try{
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:run.controller.signal,headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify({generation:run.generation,params:p})});
      const payload=await response.json().catch(()=>null),data=payload&&payload.data;if(!current(run))return;
      if(!response.ok||payload?.ok!==true)throw new Error('Andromeda search unavailable');
      const result=await applyDirectAndromeda(run,data,p);if(!current(run))return;
      notify({type:'provider',provider:'andromeda',...result});
      // Keep durable/cache visibility independent: autosave may land the same
      // offer later, and the canonical SHA-256 identity collapses that duplicate.
      await refreshDatabase(run);
    }catch(error){
      if(!current(run)||error?.name==='AbortError')return;
      owner.clearOffers('direct-andromeda');owner.refresh();
      run.sourceCounts.andromeda={status:'error',hotels:0,offers:0};
      notify({type:'provider',provider:'andromeda',status:'error'});
    }
  }
  async function settleInitialSources(run){
    await Promise.allSettled([run.anex,run.andromeda]);if(!current(run))return false;
    await run.database;return current(run);
  }
  async function pollSearch(run){
    if(!current(run))return;
    try{
      const status=await rt.api('search_status',{searchId:run.searchId});if(!current(run))return;
      const progress=Math.max(0,Math.min(100,Number(status.progress)||0));
      const complete=progress>=100||status.status==='complete';notify({type:'progress',progress});if(!current(run))return;
      if(complete||progress>=run.lastProgress+10||Date.now()-run.lastRead>7000){
        // #3444 validates this explicit request in the existing gateway. This
        // is a response-size bound, not a claim that these are all market tours.
        const rows=await rt.api('search_results',{searchId:run.searchId,limit:complete||run.continued?5000:25});
        if(!current(run))return;
        if(!Array.isArray(rows))throw new Error('Не удалось прочитать предложения.');
        raw=rows;run.lastProgress=progress;run.lastRead=Date.now();publish();if(!current(run))return;
      }
      if(complete){
        const inventory=tourvisorInventory();
        run.sourceCounts.tourvisor={status:'complete',...inventory};
        notify({type:'provider',provider:'tourvisor',...run.sourceCounts.tourvisor});
        await refreshDatabase(run);if(!current(run))return;
        if(!run.continued&&!(await settleInitialSources(run)))return;
        const resultLimitReached=inventory.hotels>=5000,baseline=run.continueBaseline;
        const grew=!run.continued||!baseline||inventory.hotels>baseline.hotels||inventory.offers>baseline.offers;
        run.pending=false;run.resumeOnly=false;run.canContinue=!resultLimitReached&&(!run.continued||grew);
        notify({type:'complete',canContinue:run.canContinue,continued:run.continued,resultLimitReached,
          continuationGrowth:run.continued&&baseline?{before:structuredClone(baseline),after:structuredClone(inventory),grew}:null,
          sources:structuredClone(run.sourceCounts),union:canonicalUnion()});return;
      }
      if(run.deadline&&Date.now()>=run.deadline)throw new Error('Продолжение поиска ещё не завершено. Проверьте результат повторно.');
      timer=setTimeout(()=>pollSearch(run),2500);
    }catch(error){await searchError(run,error);}
  }
  async function search(s, callback, hotelIds=[], filters={}) {
    const p=params(s,hotelIds,filters),epoch=stop();notify=callback;context=structuredClone(s);searchParams=structuredClone(p);raw=[];searchId=0;rt.setSearchId(0);owner?.reset();
    const run={generation:epoch,search:structuredClone(s),hotelIds:[...hotelIds],filters:structuredClone(filters),controller:new AbortController(),pending:true,searchId:0,resumeOnly:true,continued:false,expired:false,canContinue:true,continueBaseline:null,lastProgress:-10,lastRead:0,deadline:0,sourceCounts:{}};
    activeSearch=run;callback({type:'loading'});if(!current(run))return;
    run.database=readDatabase(run);
    run.andromeda=enrichAndromeda(run,p);
    run.anex=enrichAnex(run,p);
    notify({type:'provider',provider:'tourvisor',status:'loading'});if(!current(run))return;
    try{
      const started=await rt.api('search_start',p);if(!current(run))return;
      searchId=Number(started.searchId);
      if(!Number.isSafeInteger(searchId)||searchId<1)throw new Error('Не удалось запустить поиск.');
      run.searchId=searchId;rt.setSearchId(searchId);
      timer=setTimeout(()=>pollSearch(run),1000);
    }catch(error){await searchError(run,error);}
  }
  async function continueSearch(){
    const run=activeSearch;
    if(!run||!current(run)||run.pending||!run.searchId||run.expired||!run.canContinue)return false;
    // Lock before the first await: double clicks never spend a second request.
    run.pending=true;run.continued=true;run.lastProgress=-10;run.lastRead=0;run.deadline=Date.now()+75000;
    const retryRead=run.resumeOnly;if(!retryRead)run.continueBaseline=tourvisorInventory();run.resumeOnly=true;
    notify({type:'loading',continued:true,retryRead});if(!current(run))return false;
    try{
      if(!retryRead){await rt.api('search_continue',{searchId:run.searchId});if(!current(run))return false;}
      await pollSearch(run);return current(run);
    }catch(error){await searchError(run,error);return false;}
  }
  function clearCalendarWindows(){calendarWindows.clear();calendarWindowBytes=0;calendarVersion++;}
  function retainCalendarWindow(key,rows,data,startedAt){
    if(!rows.length)return;
    let until=startedAt+CALENDAR_REUSE_MS,offers=0;
    for(const group of data.hotels)for(const row of group.offers){
      // LOCAL listing expiry is independent of the short supplier quote context.
      const raw=row.expiresAt;
      if(typeof raw!=='string'||!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?Z$/.test(raw))return;
      const expires=Date.parse(raw);if(!Number.isFinite(expires))return;
      until=Math.min(until,expires);offers++;
    }
    if(!offers||until<=Date.now())return;
    // Bound retained serialized payload, not the returned inventory or budget.
    const size=2*(JSON.stringify(rows).length+key.length);if(size>CALENDAR_CACHE_BYTES)return;
    const old=calendarWindows.get(key);if(old){calendarWindowBytes-=old.size;calendarWindows.delete(key);}
    while(calendarWindows.size&&(calendarWindows.size>=8||calendarWindowBytes+size>CALENDAR_CACHE_BYTES)){
      const first=calendarWindows.keys().next().value;calendarWindowBytes-=calendarWindows.get(first).size;calendarWindows.delete(first);
    }
    calendarWindows.set(key,{rows:structuredClone(rows),until,startedAt,size});calendarWindowBytes+=size;
  }
  async function calendar(s,from,to,signal,filters={},onWindow) {
    const result=[],version=calendarVersion,queryFilters=structuredClone(filters),search=structuredClone(s);
    for(let start=from;start<=to;start=plus(start,22)) {
      if(signal?.aborted)throw new DOMException('Aborted','AbortError');
      const scope={...search,from:start,to:plus(start,21)<to?plus(start,21):to};
      const key=JSON.stringify([scope,params(scope,[],queryFilters)]),now=Date.now();
      for(const [id,entry]of calendarWindows)if(entry.until<=now||now<entry.startedAt){calendarWindowBytes-=entry.size;calendarWindows.delete(id);}
      const hit=version===calendarVersion?calendarWindows.get(key):null;
      if(hit){
        calendarWindows.delete(key);calendarWindows.set(key,hit);
        const rows=structuredClone(hit.rows);result.push(...rows);
        if(typeof onWindow==='function')onWindow(structuredClone(rows),{from:scope.from,to:scope.to,cached:true});
        continue;
      }
      const data=await db(scope,signal,[],queryFilters),parsed=root.AnyTourLocalDbProviderV1.parse(data);
      if(signal?.aborted)throw new DOMException('Aborted','AbortError');
      const list=parsed.hotels.map(g=>({...g.hotel,anytourHotelId:g.anytourHotelId,canonicalLegacyIds:[...new Set(g.offers.map(o=>o.legacyHotelId))],tours:g.offers.map(o=>o.tour)}));
      const rows=project(list,scope);result.push(...rows);
      if(version===calendarVersion)retainCalendarWindow(key,rows,data,now);
      if(version===calendarVersion&&typeof onWindow==='function')onWindow(structuredClone(rows),{from:scope.from,to:scope.to,cached:false});
    }
    return result;
  }
  function observationScopeSupported(s,filters={}) {
    // Existing observation reader describes two adults, no children, with an
    // optional single region. Never use its aggregate for unsupported filters.
    if(s.adults!==2||s.ages.length||filters.hotelId||filters.q||filters.min>0
      ||filters.max!==undefined&&filters.max!==null&&filters.max!==''
      ||['stars','meals','operators','flight','amenities'].some(key=>filters[key]?.length)
      ||['rating','beach','family','spa'].some(key=>filters[key])||(filters.resorts||[]).length>1)return false;
    return true;
  }
  async function observedCalendar(s,from,to,signal,filters={}) {
    if(!observationScopeSupported(s,filters))return [];
    const departure=catalog.departures.find(x=>text(x)===s.origin||String(x.id)===s.origin);
    if(!departure||!catalog.countries.some(x=>String(x.id)===String(s.country)))return [];
    const selected=regionIds(s,filters),query={departureId:String(departure.id),countryId:String(s.country),dateFrom:from,dateTo:to,nightsFrom:String(s.minNights),nightsTo:String(s.maxNights)};
    if(selected.length)query.regionId=selected[0];
    const response=await fetch('/data/price-calendar-read-v1.php?'+new URLSearchParams(query),{credentials:'same-origin',signal});
    if(!response.ok)throw new Error('Сохранённые цены календаря временно недоступны.');
    const result=await response.json();
    if(result?.ok!==true||result.source!=='latest-known-exact-segments-from-anytour-first-party-observations'
      ||result.cachedPriceIsFinal!==false||result.currency!=='RUB'||result.adults!==2||result.childrenCount!==0
      ||String(result.departureId)!==query.departureId||String(result.countryId)!==query.countryId
      ||String(result.regionId||'')!==String(query.regionId||'')||result.dateFrom!==from||result.dateTo!==to
      ||String(result.nightsFrom)!==query.nightsFrom||String(result.nightsTo)!==query.nightsTo||!Array.isArray(result.series))throw new Error('Сохранённые цены не соответствуют параметрам поездки.');
    const points=[],seen=new Set();
    for(const row of result.series){
      if(!row||date(row.date)!==row.date||row.date<from||row.date>to||seen.has(row.date)||typeof row.observed!=='boolean')throw new Error('Некорректные даты сохранённых цен.');
      seen.add(row.date);
      if(row.observed){const price=amount(row.minPrice);if(price===null)throw new Error('Некорректная сохранённая цена.');points.push({date:row.date,price});}
    }
    return points;
  }
  async function calendarPrices(s,from,to,signal,filters={},onUpdate) {
    const snapshot={hotels:[],observations:[]},show=()=>{if(!signal?.aborted&&typeof onUpdate==='function')onUpdate(structuredClone(snapshot));};
    const settled=await Promise.allSettled([
      calendar(s,from,to,signal,filters,rows=>{snapshot.hotels.push(...rows);show();}),
      observedCalendar(s,from,to,signal,filters).then(rows=>{snapshot.observations=rows;show();})
    ]);
    if(signal?.aborted)throw new DOMException('Aborted','AbortError');
    const failed=settled.filter(row=>row.status==='rejected');
    if(failed.length===settled.length)throw failed[0].reason;
    return {...snapshot,partial:failed.length>0};
  }
  async function init(origin='Москва') {
    try{const response=await fetch('/data/departures-v1.php',{credentials:'same-origin'});const data=await response.json();if(response.ok&&data.ok&&Array.isArray(data.items))catalog.departures=data.items;}catch{}
    if(!catalog.departures.length)catalog.departures=await rt.api('departures',{departureCountryId:1});
    if(!Array.isArray(catalog.departures)||!catalog.departures.length)throw new Error('Не удалось загрузить города вылета.');
    try{const rows=await rt.api('meals',{});if(Array.isArray(rows))catalog.meals=rows;}catch{}
    return countries(origin);
  }
  let catalogGeneration=0;
  async function countries(origin) {
    const run=++catalogGeneration,departure=catalog.departures.find(x=>text(x)===origin)||catalog.departures[0];
    const rows=await rt.api('countries',{departureId:departure.id,onlyDirect:false,onlyCharter:false});
    if(run!==catalogGeneration)return null;if(!Array.isArray(rows)||!rows.length)throw new Error('Для этого города список стран недоступен.');
    catalog.countries=rows;return {departures:catalog.departures,countries:rows,origin:text(departure)};
  }
  async function savedHotels(ids,s){if(!owner)return[];const rows=await Promise.allSettled(ids.slice(0,20).map(id=>owner.readProfile(id)));return rows.filter(r=>r.status==='fulfilled'&&r.value).map(r=>hotel({...r.value,anytourHotelId:r.value.id},s));}
  async function lookupHotels(q,country,signal){
    if(q.trim().length<2)return[];
    const read=async url=>{const r=await fetch(url,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});if(!r.ok)throw new Error('Hotel catalogue unavailable');const p=await r.json();if(p?.ok!==true||!Array.isArray(p.items))throw new Error('Invalid hotel catalogue');return p;};
    const found=await read('/data/hotel-search-v1.php?'+new URLSearchParams({q:q.trim(),countryId:country,limit:'10'}));
    const ids=[...new Set(found.items.filter(h=>String(h.country?.id)===String(country)&&Number.isSafeInteger(Number(h.id))&&Number(h.id)>0).map(h=>String(h.id)))].slice(0,10);
    if(!ids.length)return[];
    const query=new URLSearchParams({catalog:'anytour'});ids.forEach(id=>query.append('legacyHotelIds[]',id));
    const p=await read(local+'data/hotel-details-read-v1.php?'+query);
    if(p.source!=='anytour-canonical-catalog'||p.catalog!=='anytour'||!Array.isArray(p.links)||!Array.isArray(p.requestedLegacyIds)||p.requestedLegacyIds.map(String).join(',')!==ids.join(','))throw new Error('Invalid canonical hotel lookup');
    return p.items.map(raw=>{
      if(raw.catalog!=='anytour'||!Number.isSafeInteger(raw.id)||raw.id<1||typeof raw.name!=='string'||!raw.name.trim())throw new Error('Invalid canonical hotel');
      const legacyIds=p.links.filter(link=>Number(link.anytourHotelId)===raw.id&&ids.includes(String(link.legacyHotelId))).map(link=>String(link.legacyHotelId));
      if(!legacyIds.length)throw new Error('Missing hotel search identity');
      return hotel({...raw,canonicalLegacyIds:legacyIds},{country});
    });
  }
  async function restoreHotel(id,country){
    if(!owner)throw new Error('Hotel catalogue unavailable');
    const request=new AbortController(),timeout=setTimeout(()=>request.abort(),15000);
    try{
      const profile=await owner.readProfile(id);
      if(!profile||request.signal.aborted)throw new Error('Hotel profile unavailable');
      // A saved own ID is not a supplier ID. Recover only a currently verified
      // catalogue link in this country, never a similarly named replacement.
      const rows=await lookupHotels(profile.name,country,request.signal),match=rows.find(h=>h.id===id);
      if(!match)throw new Error('Hotel search identity unavailable');
      return match;
    }finally{clearTimeout(timeout);}
  }
  async function quote(o) {
    if(o.cached||o.provider!=='tourvisor'||o.raw.selectionEnabled===false)throw new Error('Сначала обновите предложения отеля.');
    const run=generation,id=searchId;
    const t=await rt.api('tour',{tourId:o.raw.id,currency:'RUB'});
    if(run!==generation||id!==searchId)throw new Error('Условия поиска изменились. Выберите тур заново.');
    if(!t||String(t.id)!==String(o.raw.id)||!amount(t.price))throw new Error('Не удалось подтвердить цену выбранного тура.');
    quoteReceipts.set(t,{generation:run,searchId:id});
    return t;
  }
  async function flights(t) {
    const data=await rt.api('flights',{tourId:t.id,currency:'RUB'});
    if(Array.isArray(data))return data;
    if(Array.isArray(data?.flights))return data.flights;
    throw new Error('Не удалось загрузить рейсы. Попробуйте ещё раз.');
  }
  function leadSession(o) {
    if(!o?.tour||o.cached||o.provider!=='tourvisor'||String(o.tour.id)!==String(o.raw.id)||!searchId||!searchParams)throw new Error('Сначала подтвердите актуальное предложение.');
    const run=generation,id=searchId;
    const receipt=quoteReceipts.get(o.tour);
    if(!receipt||receipt.generation!==run||receipt.searchId!==id)throw new Error('Предложение устарело. Откройте условия тура и проверьте цену заново.');
    if(o.flightsLoading||o.flightsError)throw new Error(o.flightsLoading?'Дождитесь загрузки рейсов.':'Не удалось загрузить рейсы. Повторите загрузку перед выбором тура.');
    if(o.pricePending||!amount(o.tour.price))throw new Error('Цена тура пока не подтверждена. Проверьте условия заново.');
    let flight=null;
    if(o.flightChoiceId!==null){
      const index=Number(o.flightChoiceId);
      flight=Number.isInteger(index)&&index>=0&&String(index)===String(o.flightChoiceId)?o.variants?.[index]:null;
      if(!flight||!variantPrice(o.tour,flight))throw new Error('Цена выбранного перелёта пока не подтверждена. Выберите другой вариант.');
    }
    const session=root.V2TourController.createLeadSession({tour:o.tour,flight,searchId:id,search:searchParams});
    const current=()=>{if(run!==generation||id!==searchId)throw new Error('Условия поиска изменились. Выберите тур заново.');};
    return Object.freeze({payload(fd){current();return session.payload(fd);},submit(form,controls){current();return session.submit(form,controls);}});
  }
  function variantPrice(t,v){return amount(v?.price);}
  function fuel(t,v){const source=v&&Object.hasOwn(v,'fuelCharge')?v:t;const raw=source?.fuelCharge,value=raw&&typeof raw==='object'?raw.value:raw;if(value===null||value===undefined||value==='')return null;const n=Number(value);return Number.isFinite(n)&&n>=0?n:null;}
  root.AnyTourPrototypeData=Object.freeze({init,countries,regions,search,continueSearch,stop,calendar,calendarPrices,observedCalendar,observationScopeSupported,quote,flights,leadSession,params,sameScope,project,amount,date,text,meal,operator,variantPrice,fuel,savedHotels,lookupHotels,restoreHotel,catalog,get searchId(){return searchId;}});
})(window);