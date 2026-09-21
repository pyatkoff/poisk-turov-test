(function (root) {
  'use strict';
  // Presentation adapter only. Existing API actions, source DTOs and DB guards stay authoritative.
  const rt = root.V2Runtime;
  const local = '/_preview/search3-local-candidate/';
  const catalog = { departures: [], countries: [], meals: [], mealCatalogueAvailable:false, mealCatalogueError:'' };
  const quoteReceipts = new WeakMap();
  let generation = 0, searchId = 0, timer = null, notify = () => {}, raw = [], context = null, searchParams = null;
  const owner = root.Search3CanonicalProfilesV1.create(() => publish());
  const text = value => typeof value === 'object' && value ? String(value.russianName || value.name || '') : String(value ?? '');
  const amount = value => { const n = Number(value && typeof value === 'object' ? value.value : value); return Number.isFinite(n) && n > 0 ? n : null; };
  const date = value => { const s = String(value || '').slice(0, 10), p = s.match(/^(\d{2})\.(\d{2})\.(\d{4})$/); return p ? `${p[3]}-${p[2]}-${p[1]}` : /^\d{4}-\d{2}-\d{2}$/.test(s) ? s : ''; };
  const plus = (d, n) => new Date(new Date(d + 'T12:00:00Z').getTime() + n * 86400000).toISOString().slice(0, 10);
  let mealLoadGeneration=0, mealByNative=new Map();
  const mealId = value => /^(?:[1-9][0-9]*)$/.test(String(value??''))&&Number.isSafeInteger(Number(value))?String(value):'';
  function mealPlanId(value,provider='tourvisor') { return provider==='tourvisor'?mealByNative.get(String(value?.id??''))?.id??null:null; }
  function meal(value){return mealByNative.get(String(value?.id??''))?.nameRu||text(value);}
  function selectedMealPlans(values=[]) {
    if(!Array.isArray(values)||values.length>100)throw new Error('Проверьте выбранное питание.');
    return [...new Set(values.map(v=>{const id=mealId(v);if(!id)throw new Error('Сохранённое питание недоступно. Выберите его заново из нашего справочника.');return id;}))].map(id=>{
      const plan=catalog.meals.find(p=>String(p.id)===id);
      if(!plan)throw new Error('Выбранное питание отсутствует в нашем справочнике. Выберите его заново.');
      return plan;
    });
  }
  function requestMeal(values=[]) {
    const plans=selectedMealPlans(values);if(!plans.length)return '';
    if(!catalog.mealCatalogueAvailable||plans.some(p=>!p.nativeIds.length))throw new Error('Не настроено соответствие выбранного питания для Tourvisor. Повторите загрузку справочника или измените питание.');
    const ids=plans.flatMap(p=>p.nativeIds);
    if(ids.some(id=>!mealId(id)))throw new Error('Некорректное соответствие питания Tourvisor.');
    // Tourvisor accepts one integer MINIMUM. Exact selected categories are ORed
    // locally after the response maps back to our own meal-plan IDs.
    return String(Math.min(...ids.map(Number)));
  }
  async function loadMealCatalogue() {
    const run=++mealLoadGeneration;
    try{
      const response=await fetch(local+'data/search3-local-results-read-v1.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify({action:'meal_catalog',provider:'tourvisor',scopeKey:'global'})});
      const payload=await response.json(),c=payload?.data;
      if(!response.ok||payload?.ok!==true||c?.source!=='anytour-search-meal-v1'||c.provider!=='tourvisor'||c.scopeKey!=='global'||typeof c.available!=='boolean'||!Array.isArray(c.plans)||c.plans.length>1000||(c.available&&!/^[0-9a-f]{64}$/.test(c.revision)))throw new Error('Справочник питания временно недоступен.');
      const seen=new Set(),native=new Map();let count=0;
      const plans=c.plans.map(p=>{
        const id=mealId(p?.id);
        if(!id||seen.has(id)||typeof p.nameRu!=='string'||!p.nameRu.trim()||p.nameRu.length>255||/[\u0000-\u001f\u007f]/.test(p.nameRu)||!Array.isArray(p.nativeIds)||(!c.available&&p.nativeIds.length))throw new Error('Некорректный справочник питания.');
        seen.add(id);const plan={id:Number(id),nameRu:p.nameRu,nativeIds:[...p.nativeIds]};
        for(const n of plan.nativeIds){if(++count>10000||typeof n!=='string'||!n||n.length>128||native.has(n))throw new Error('Неоднозначное соответствие питания.');native.set(n,plan);}
        return plan;
      });
      if(run!==mealLoadGeneration)return false;
      catalog.meals=plans;catalog.mealCatalogueAvailable=c.available;catalog.mealCatalogueError=c.available?'':'Соответствия питания ещё не подключены.';catalog.mealCatalogueRevision=c.revision;
      mealByNative=native;return true;
    }catch(error){if(run===mealLoadGeneration){catalog.mealCatalogueAvailable=false;catalog.mealCatalogueError=error.message;mealByNative=new Map();}throw error;}
  }
  const image = value => { const raw=typeof value === 'object' && value ? value.url || value.src : value; if(typeof raw!=='string'||!raw.trim())return ''; try { const url = new URL(raw, root.location.href); return ['https:', 'http:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } };
  function params(s, hotelIds = [], filters = {}) {
    const departure = catalog.departures.find(x => text(x) === s.origin || String(x.id) === s.origin);
    if (!departure || !catalog.countries.some(x => String(x.id) === String(s.country))) throw new Error('Выберите город вылета и страну из загруженного списка.');
    if (!date(s.from) || !date(s.to) || s.from > s.to || (new Date(s.to) - new Date(s.from)) / 86400000 > 21) throw new Error('Выберите диапазон вылета не больше 21 дня.');
    if (!Number.isInteger(s.adults) || s.adults < 1 || s.adults > 6 || !Array.isArray(s.ages) || s.ages.length > 3 || s.ages.some(x => !Number.isInteger(x) || x < 0 || x > 17)) throw new Error('Укажите возраст каждого ребёнка.');
    if (!Number.isInteger(s.minNights) || !Number.isInteger(s.maxNights) || s.minNights < 1 || s.maxNights > 28 || s.maxNights < s.minNights || s.maxNights - s.minNights > 10) throw new Error('Проверьте диапазон ночей.');
    const mappedMeal=requestMeal(filters.meals);
    const stars=(filters.stars||[]).filter(x=>Number.isInteger(x)&&x>=1&&x<=5);
    return {departureId:String(departure.id),countryId:String(s.country),dateFrom:s.from,dateTo:s.to,nightsFrom:s.minNights,nightsTo:s.maxNights,adults:s.adults,childs:[...s.ages].sort((a,b)=>a-b),meal:mappedMeal,hotelCategory:stars.length?String(Math.min(...stars)):'',hotelRating:'',hotelTypes:[],hotelIds:hotelIds.map(String),hotelServices:[],arrivalId:'',regionIds:[],subregionIds:[],operatorIds:[],priceFrom:filters.min>0?String(filters.min):'',priceTo:filters.max>0&&filters.max<600000?String(filters.max):'',currency:'RUB',onlyCharter:false,onlyDirect:false};
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
    selectedMealPlans(filters.meals);
    // DB cohorts use their trip scope; exact local category filtering happens on offers.
    const p = params(s, hotelIds,{...filters,meals:[]});
    const response = await fetch(local+'data/search3-local-results-read-v1.php', {method:'POST',credentials:'same-origin',cache:'no-store',signal,headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify({params:p})});
    if (!response.ok) throw new Error('Цены из базы временно недоступны.');
    const payload=await response.json(),data=payload?.data;
    if(payload?.ok!==true||!data)throw new Error('Цены из базы временно недоступны.');
    if (!sameScope(p,data.scope) || !root.AnyTourLocalDbProviderV1.parse(data)) throw new Error('Ответ базы не соответствует параметрам поездки.');
    return data;
  }
  function hotel(h, s) {
    const own=Number(h.anytourHotelId || h.id), photos=[h.primaryImage,...(Array.isArray(h.images)?h.images:[])].map(image).filter(Boolean);
    const rating=Number(h.rating && typeof h.rating === 'object' ? h.rating.value : h.rating);
    const ratingScale=Number(h.rating && typeof h.rating === 'object' ? h.rating.scale : 5);
    return {id:own,legacyIds:(h.canonicalLegacyIds || []).map(String),name:text(h.name),country:String(s.country),resort:text(h.region)||text(h.subRegion),stars:Number(h.category)||0,rating:rating>0&&rating<=ratingScale?rating*5/ratingScale:null,beach:null,family:null,spa:null,pool:null,photos:[...new Set(photos)].slice(0,12),note:text(h.description),tag:'',raw:h,offers:[]};
  }
  function offer(t, h, s, index) {
    const price=amount(t.price), day=date(t.date), nights=Number(t.nights);
    if(!price || !day || !Number.isInteger(nights) || nights<1) return null;
    const provider=String(t.provider||'tourvisor').toLowerCase();
    const stay=t.cachedListing===true&&t.localStay?.source==='anytour-hotel-stay-v2'?t.localStay:null;
    const localMeal=stay?.meal?.hotelId===h.id?stay.meal:null,localRoom=stay?.room?.hotelId===h.id?stay.room:null;
    const plan=t.cachedListing===true?t.searchMealPlan:provider==='tourvisor'?mealByNative.get(String(t.meal?.id??'')):null;
    return {key:encodeURIComponent(`${provider}:${String(t.id)}`),hotelId:h.id,day,nights,variant:index,total:price,returnDay:plus(day,nights),roomId:localRoom?.id??null,mealId:localMeal?.id??null,mealPlanId:plan?.id??null,stayCatalog:stay?.source??null,room:localRoom?.nameRu||text(t.roomType)||'Номер уточняется',placement:text(t.placement),adults:s.adults,ages:[...s.ages],origin:s.origin,meal:localMeal?.nameRu||plan?.nameRu||text(t.meal)||'Питание уточняется',operator:text(t.operator)||'Туроператор уточняется',flight:t.isCharter===true?'charter':t.isCharter===false?'regular':'unknown',cached:t.cachedListing===true,provider,raw:t,search:structuredClone(s),fuel:t.fuelCharge??null,flightChoiceId:null};
  }
  function project(list,s) { return list.map(rawHotel=>{const h=hotel(rawHotel,s);h.offers=(rawHotel.tours||[]).map((t,i)=>offer(t,h,s,i)).filter(Boolean);return h;}).filter(h=>h.offers.length); }
  function publish() {if(owner&&context)notify({type:'results',hotels:project(owner.read(raw,{}),context)});}
  function stop(){generation++;clearTimeout(timer);timer=null;return generation;}
  async function search(s, callback, hotelIds=[], filters={}) {
    const run=stop();notify=callback;context=structuredClone(s);searchParams=null;raw=[];searchId=0;rt.setSearchId(0);owner?.reset();
    callback({type:'loading'});
    try {
      try{await loadMealCatalogue();}catch(error){if(filters.meals?.length)throw error;}
      if(run!==generation)return;
      callback({type:'meal-catalog'});
      selectedMealPlans(filters.meals);
      db(s,undefined,hotelIds,filters).then(data=>{if(run!==generation||!owner)return;root.AnyTourLocalDbProviderV1.apply(owner,data);callback({type:'database'});}).catch(error=>{if(run===generation)callback({type:'database-error',message:error.message});});
      const p=params(s,hotelIds,filters);searchParams=structuredClone(p);
      const started=await rt.api('search_start',p);
      if(run!==generation)return;
      searchId=Number(started.searchId);if(!searchId)throw new Error('Не удалось запустить поиск.');rt.setSearchId(searchId);
      let lastProgress=-10,lastRead=0;
      const poll=async()=>{
        try {
          const status=await rt.api('search_status',{searchId});if(run!==generation)return;
          const progress=Number(status.progress)||0,complete=progress>=100||status.status==='complete';callback({type:'progress',progress});
          if(complete||progress>=lastProgress+10||Date.now()-lastRead>7000){
            const rows=await rt.api('search_results',{searchId,limit:complete?100:25});if(run!==generation)return;
            if(!Array.isArray(rows))throw new Error('Не удалось прочитать предложения.');
            raw=rows;lastProgress=progress;lastRead=Date.now();publish();
          }
          if(complete){callback({type:'complete'});return;}
          timer=setTimeout(poll,2500);
        }catch(error){if(run===generation)callback({type:'error',message:error.message});}
      };
      timer=setTimeout(poll,1000);
    }catch(error){if(run===generation)callback({type:'error',message:error.message});}
  }
  async function calendar(s,from,to,signal,filters={}) {
    const result=[];
    for(let start=from;start<=to;start=plus(start,22)) {
      if(signal?.aborted)throw new DOMException('Aborted','AbortError');
      const scope={...s,from:start,to:plus(start,21)<to?plus(start,21):to};
      const data=await db(scope,signal,[],filters),parsed=root.AnyTourLocalDbProviderV1.parse(data);
      const list=parsed.hotels.map(g=>({...g.hotel,anytourHotelId:g.anytourHotelId,canonicalLegacyIds:[...new Set(g.offers.map(o=>o.legacyHotelId))],tours:g.offers.map(o=>o.tour)}));
      result.push(...project(list,scope));
    }
    return result;
  }
  async function init(origin='Москва') {
    try{const response=await fetch('/data/departures-v1.php',{credentials:'same-origin'});const data=await response.json();if(response.ok&&data.ok&&Array.isArray(data.items))catalog.departures=data.items;}catch{}
    if(!catalog.departures.length)catalog.departures=await rt.api('departures',{departureCountryId:1});
    if(!Array.isArray(catalog.departures)||!catalog.departures.length)throw new Error('Не удалось загрузить города вылета.');
    try{await loadMealCatalogue();}catch{/* Unfiltered search remains available; selected meals fail explicitly. */}
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
  root.AnyTourPrototypeData=Object.freeze({init,countries,search,stop,calendar,quote,flights,leadSession,params,sameScope,project,loadMealCatalogue,mealPlanId,selectedMealPlans,amount,date,text,meal,variantPrice,fuel,savedHotels,lookupHotels,restoreHotel,catalog,get searchId(){return searchId;}});
})(window);
