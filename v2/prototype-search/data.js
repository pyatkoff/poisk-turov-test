(function (root) {
  'use strict';
  // Presentation adapter only. Existing API actions, source DTOs and DB guards stay authoritative.
  const rt = root.V2Runtime;
  const local = '/_preview/search3-local-candidate/';
  const catalog = { departures: [], countries: [], meals: [] };
  const quoteReceipts = new WeakMap();
  const calendarWindows=new Map(),CALENDAR_REUSE_MS=30000,CALENDAR_CACHE_BYTES=4*1024*1024;
  let calendarWindowBytes=0,calendarVersion=0;
  let generation = 0, searchId = 0, timer = null, notify = () => {}, raw = [], context = null, searchParams = null, activeSearch = null;
  const owner = root.Search3CanonicalProfilesV1.create(() => publish());
  function nativeEndpoint(value){
    if(typeof value!=='string'||!root.location)return null;
    try{const url=new URL(value,root.location.href);return url.origin===root.location.origin&&url.pathname==='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'&&!url.search&&!url.hash?url:null;}catch{return null;}
  }
  const text = value => typeof value === 'object' && value ? String(value.russianName || value.name || '') : String(value ?? '');
  const amount = value => { const n = Number(value && typeof value === 'object' ? value.value : value); return Number.isFinite(n) && n > 0 ? n : null; };
  const date = value => { const s = String(value || '').slice(0, 10), p = s.match(/^(\d{2})\.(\d{2})\.(\d{4})$/); return p ? `${p[3]}-${p[2]}-${p[1]}` : /^\d{4}-\d{2}-\d{2}$/.test(s) ? s : ''; };
  const plus = (d, n) => new Date(new Date(d + 'T12:00:00Z').getTime() + n * 86400000).toISOString().slice(0, 10);
  function meal(value){const label=text(value),record=catalog.meals.find(x=>value?.id&&String(x.id)===String(value.id)||text(x).toLocaleLowerCase('ru-RU')===label.toLocaleLowerCase('ru-RU')),full=text(value?.fullName)||text(record?.fullName);return full&&(!label||/^[A-Z]{1,5}\+?$/.test(label))?full:label;}
  const image = value => { const raw=typeof value === 'object' && value ? value.url || value.src : value; if(typeof raw!=='string'||!raw.trim())return ''; try { const url = new URL(raw, root.location.href); return ['https:', 'http:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } };
  function params(s, hotelIds = [], filters = {}) {
    const departure = catalog.departures.find(x => text(x) === s.origin || String(x.id) === s.origin);
    if (!departure || !catalog.countries.some(x => String(x.id) === String(s.country))) throw new Error('Выберите город вылета и страну из загруженного списка.');
    if (!date(s.from) || !date(s.to) || s.from > s.to || (new Date(s.to) - new Date(s.from)) / 86400000 > 21) throw new Error('Выберите диапазон вылета не больше 21 дня.');
    if (!Number.isInteger(s.adults) || s.adults < 1 || s.adults > 6 || !Array.isArray(s.ages) || s.ages.length > 3 || s.ages.some(x => !Number.isInteger(x) || x < 0 || x > 17)) throw new Error('Укажите возраст каждого ребёнка.');
    if (!Number.isInteger(s.minNights) || !Number.isInteger(s.maxNights) || s.minNights < 1 || s.maxNights > 28 || s.maxNights < s.minNights || s.maxNights - s.minNights > 10) throw new Error('Проверьте диапазон ночей.');
    const chosenMeal=filters.meals?.length===1?catalog.meals.find(x=>meal(x)===filters.meals[0]):null;
    const stars=(filters.stars||[]).filter(x=>Number.isInteger(x)&&x>=1&&x<=5);
    return {departureId:String(departure.id),countryId:String(s.country),dateFrom:s.from,dateTo:s.to,nightsFrom:s.minNights,nightsTo:s.maxNights,adults:s.adults,childs:[...s.ages].sort((a,b)=>a-b),meal:chosenMeal?String(chosenMeal.id):'',hotelCategory:stars.length?String(Math.min(...stars)):'',hotelRating:'',hotelTypes:[],hotelIds:hotelIds.map(String),hotelServices:[],arrivalId:'',regionIds:[],subregionIds:[],operatorIds:[],priceFrom:filters.min>0?String(filters.min):'',priceTo:filters.max!==null&&filters.max!==undefined&&filters.max!==''?String(filters.max):'',currency:'RUB',onlyCharter:false,onlyDirect:false};
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
    return {id:own,legacyIds:(h.canonicalLegacyIds || []).map(String),name:text(h.name),country:String(s.country),resort:text(h.region)||text(h.subRegion),stars:Number(h.category)||0,rating:rating>0&&rating<=ratingScale?rating*5/ratingScale:null,beach:null,family:null,spa:null,pool:null,amenities:amenities(h),photos:[...new Set(photos)].slice(0,12),note:text(h.description),tag:'',raw:h,offers:[]};
  }
  function offer(t, h, s, index) {
    const price=amount(t.price), day=date(t.date), nights=Number(t.nights);
    if(!price || !day || !Number.isInteger(nights) || nights<1) return null;
    const provider=String(t.provider||'tourvisor').toLowerCase();
    return {key:encodeURIComponent(`${provider}:${String(t.id)}`),hotelId:h.id,day,nights,variant:index,total:price,returnDay:plus(day,nights),room:text(t.roomType)||'Номер уточняется',placement:text(t.placement),adults:s.adults,ages:[...s.ages],origin:s.origin,meal:meal(t.meal)||'Питание уточняется',operator:text(t.operator)||'Туроператор уточняется',flight:t.isCharter===true?'charter':t.isCharter===false?'regular':'unknown',cached:t.cachedListing===true,provider,raw:t,search:structuredClone(s),fuel:t.fuelCharge??null,flightChoiceId:null};
  }
  function project(list,s) { return list.map(rawHotel=>{const h=hotel(rawHotel,s);h.offers=(rawHotel.tours||[]).map((t,i)=>offer(t,h,s,i)).filter(Boolean);return h;}).filter(h=>h.offers.length); }
  function publish() {if(owner&&context&&activeSearch&&current(activeSearch))notify({type:'results',hotels:project(owner.read(raw,{}),context)});}
  function current(run){return activeSearch===run&&run.generation===generation;}
  function stop(){
    clearCalendarWindows();generation++;clearTimeout(timer);timer=null;
    activeSearch?.controller.abort();activeSearch=null;
    return generation;
  }
  function searchError(run,error){
    if(!current(run))return;
    run.pending=false;
    // An expired supplier search cannot be continued. A lost response is not
    // evidence that search_continue failed: subsequent recovery only reads it.
    if(error?.status===404||error?.status===410)run.expired=true;
    notify({type:'error',message:error.message,canContinue:!!run.searchId&&!run.expired,retryRead:run.resumeOnly});
  }
  function readDatabase(run){
    return db(run.search,run.controller.signal,run.hotelIds,run.filters).then(data=>{
      if(!current(run)||!owner)return;
      clearCalendarWindows();root.AnyTourLocalDbProviderV1.apply(owner,data);if(current(run))notify({type:'database'});
    }).catch(error=>{if(current(run))notify({type:'database-error',message:error.message});});
  }
  function refreshDatabase(run){
    // Serial snapshots cannot overwrite a later snapshot with an earlier one.
    // Provider completion uses the same reader; no parallel store or DTO.
    return run.database=run.database.then(()=>{if(current(run))return readDatabase(run);});
  }
  async function enrichAndromeda(run,p){
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaApi);if(!url||!current(run))return;
    notify({type:'provider',provider:'andromeda',status:'loading'});if(!current(run))return;
    try{
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:run.controller.signal,headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify({generation:run.generation,params:p})});
      const payload=await response.json().catch(()=>null),data=payload&&payload.data;if(!current(run))return;
      if(!response.ok||payload?.ok!==true||!data||data.provider!=='andromeda'||data.generation!==run.generation)throw new Error('Andromeda search unavailable');
      await refreshDatabase(run);if(!current(run))return;
      notify({type:'provider',provider:'andromeda',status:'complete',hotels:Array.isArray(data.hotels)?data.hotels.length:0});
    }catch(error){
      if(!current(run)||error?.name==='AbortError')return;
      notify({type:'provider',provider:'andromeda',status:'error'});
    }
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
        run.pending=false;run.resumeOnly=false;refreshDatabase(run);
        notify({type:'complete',canContinue:true,continued:run.continued,resultLimitReached:raw.length>=5000});return;
      }
      if(run.deadline&&Date.now()>=run.deadline)throw new Error('Продолжение поиска ещё не завершено. Проверьте результат повторно.');
      timer=setTimeout(()=>pollSearch(run),2500);
    }catch(error){searchError(run,error);}
  }
  async function search(s, callback, hotelIds=[], filters={}) {
    const p=params(s,hotelIds,filters),epoch=stop();notify=callback;context=structuredClone(s);searchParams=structuredClone(p);raw=[];searchId=0;rt.setSearchId(0);owner?.reset();
    const run={generation:epoch,search:structuredClone(s),hotelIds:[...hotelIds],filters:structuredClone(filters),controller:new AbortController(),pending:true,searchId:0,resumeOnly:true,continued:false,expired:false,lastProgress:-10,lastRead:0,deadline:0};
    activeSearch=run;callback({type:'loading'});if(!current(run))return;run.database=readDatabase(run);void enrichAndromeda(run,p);
    try{
      const started=await rt.api('search_start',p);if(!current(run))return;
      searchId=Number(started.searchId);
      if(!Number.isSafeInteger(searchId)||searchId<1)throw new Error('Не удалось запустить поиск.');
      run.searchId=searchId;rt.setSearchId(searchId);
      timer=setTimeout(()=>pollSearch(run),1000);
    }catch(error){searchError(run,error);}
  }
  async function continueSearch(){
    const run=activeSearch;
    if(!run||!current(run)||run.pending||!run.searchId||run.expired)return false;
    // Lock before the first await: double clicks never spend a second request.
    run.pending=true;run.continued=true;run.lastProgress=-10;run.lastRead=0;run.deadline=Date.now()+75000;
    const retryRead=run.resumeOnly;run.resumeOnly=true;
    notify({type:'loading',continued:true,retryRead});if(!current(run))return false;
    try{
      if(!retryRead){await rt.api('search_continue',{searchId:run.searchId});if(!current(run))return false;}
      await pollSearch(run);return current(run);
    }catch(error){searchError(run,error);return false;}
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
  root.AnyTourPrototypeData=Object.freeze({init,countries,search,continueSearch,stop,calendar,quote,flights,leadSession,params,sameScope,project,amount,date,text,meal,variantPrice,fuel,savedHotels,lookupHotels,restoreHotel,catalog,get searchId(){return searchId;}});
})(window);
