(function (root) {
  'use strict';
  // Presentation adapter only. Existing API actions, source DTOs and DB guards stay authoritative.
  const rt = root.V2Runtime;
  const local = '/_preview/search3-local-candidate/';
  const catalog = { departures: [], countries: [], meals: [], mealPlans: [], mealPlanRevision: null, mealPlanAvailable: false, regions: {} };
  const regionRequests=new Map();
  const quoteReceipts = new WeakMap();
  const andromedaQuoteChoices=new Map();
  const anexCurrentReceipts=new Set(),anexAdditionalAttempts=new Set();
  const calendarWindows=new Map(),CALENDAR_REUSE_MS=30000,CALENDAR_CACHE_BYTES=4*1024*1024;
  let calendarWindowBytes=0,calendarVersion=0;
  let generation = 0, searchId = 0, timer = null, notify = () => {}, raw = [], context = null, searchParams = null, activeSearch = null, activeVerification = null, currentSupplierScope = null;
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
  function installMealPlans(payload){
    if(!payload||payload.ok!==true||payload.source!=='anytour-search-meal-v1'||payload.provider!=='tourvisor'||payload.scopeKey!=='global'
      ||typeof payload.available!=='boolean'||!Array.isArray(payload.plans)||payload.plans.length>1000)return false;
    const plans=[],ids=new Set(),nativeIds=new Set();
    for(const row of payload.plans){
      if(!row||!Number.isSafeInteger(row.id)||row.id<1||ids.has(row.id)||typeof row.code!=='string'||!/^[a-z0-9][a-z0-9-]{0,63}$/.test(row.code)
        ||typeof row.nameRu!=='string'||!row.nameRu.trim()||row.nameRu.length>255||!Array.isArray(row.nativeIds))return false;
      const native=[];
      for(const raw of row.nativeIds){
        if(typeof raw!=='string'||!raw.trim()||raw.length>128||/[\u0000-\u001f\u007f]/.test(raw)||nativeIds.has(raw))return false;
        nativeIds.add(raw);native.push(raw);
      }
      ids.add(row.id);plans.push(Object.freeze({id:row.id,code:row.code,nameRu:row.nameRu.trim(),nativeIds:Object.freeze(native)}));
    }
    catalog.mealPlans=plans;
    catalog.mealPlanAvailable=payload.available;
    catalog.mealPlanRevision=typeof payload.revision==='string'&&/^[a-f0-9]{64}$/.test(payload.revision)?payload.revision:null;
    return true;
  }
  function supplierMealNativeId(value){
    const direct=value&&typeof value==='object'?String(value.id??''):'';
    if(/^[1-9][0-9]*$/.test(direct))return direct;
    const candidates=[text(value),text(value?.fullName),text(value?.russianName)].map(v=>v.trim()).filter(Boolean);
    if(!candidates.length)return '';
    const matches=catalog.meals.filter(row=>{
      const labels=[text(row),text(row?.fullName),text(row?.russianName)].map(v=>v.trim()).filter(Boolean);
      return candidates.some(candidate=>labels.includes(candidate));
    }).map(row=>String(row?.id??'')).filter(id=>/^[1-9][0-9]*$/.test(id));
    return [...new Set(matches)].length===1?matches[0]:'';
  }
  function mealPlan(t,provider=String(t?.provider||'tourvisor').toLowerCase()){
    const explicit=t&&t.searchMealPlan;
    if(explicit&&Number.isSafeInteger(explicit.id)&&explicit.id>0&&typeof explicit.code==='string'&&/^[a-z0-9][a-z0-9-]{0,63}$/.test(explicit.code)
      &&typeof explicit.nameRu==='string'&&explicit.nameRu.trim()&&explicit.nameRu.length<=255){
      const known=catalog.mealPlans.find(plan=>plan.id===explicit.id);
      if(!known||known.code===explicit.code&&known.nameRu===explicit.nameRu.trim())return Object.freeze({id:explicit.id,code:explicit.code,nameRu:explicit.nameRu.trim()});
      return null;
    }
    if(provider!=='tourvisor'||catalog.mealPlanAvailable!==true)return null;
    const native=supplierMealNativeId(t?.meal);if(!native)return null;
    const matches=catalog.mealPlans.filter(plan=>plan.nativeIds.includes(native));
    return matches.length===1?Object.freeze({id:matches[0].id,code:matches[0].code,nameRu:matches[0].nameRu}):null;
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
  async function destinationCatalog(action,params={},signal){
    const query=new URLSearchParams({action});Object.entries(params).forEach(([key,value])=>{if(value!==''&&value!==null&&value!==undefined)query.set(key,String(value));});
    const response=await fetch(local+'data/search3-destination-read-v1.php?'+query.toString(),{credentials:'same-origin',cache:'no-store',signal,headers:{Accept:'application/json'}});
    const payload=await response.json().catch(()=>null);
    if(!response.ok||payload?.ok!==true||payload.source!=='anytour-destination-identities-v1'||payload.provider!=='tourvisor'||!Array.isArray(payload.items))throw new Error('Канонический справочник направлений временно недоступен.');
    return payload.items;
  }
  function tourvisorIds(row){
    if(!row||!Array.isArray(row.tourvisorIds)||!row.tourvisorIds.length)throw new Error('Для направления нет подтверждённого соответствия Tourvisor.');
    const ids=[...new Set(row.tourvisorIds.map(String))];
    if(ids.some(id=>!/^[1-9][0-9]*$/.test(id)))throw new Error('Некорректное соответствие направления Tourvisor.');
    return ids.sort((a,b)=>Number(a)-Number(b));
  }
  function countryRow(country){
    const matches=catalog.countries.filter(row=>String(row?.id)===String(country));
    if(matches.length!==1)throw new Error('Выберите страну из канонического справочника.');
    return matches[0];
  }
  function tourvisorCountryId(country){
    const ids=tourvisorIds(countryRow(country));
    if(ids.length!==1)throw new Error('Для страны нет однозначного соответствия Tourvisor.');
    return ids[0];
  }
  async function regions(country) {
    const key=String(country);
    countryRow(key);
    if(catalog.regions[key])return catalog.regions[key];
    if(regionRequests.has(key))return regionRequests.get(key);
    const request=destinationCatalog('regions',{countryId:key}).then(rows=>{
      const seen=new Set(),items=[],regionIds=new Set();
      for(const row of rows){
        const id=String(row?.id||''),name=text(row).trim();
        if(row?.kind!=='region'||!/^[1-9][0-9]*$/.test(id)||!name||seen.has(id)||String(row.parentId)!==key)throw new Error('Не удалось проверить канонический справочник курортов.');
        const native=tourvisorIds(row);seen.add(id);regionIds.add(id);
        items.push({id,name,country:key,kind:'region',parentId:key,tourvisorIds:native});
        const children=Array.isArray(row.subregions)?row.subregions:[];
        for(const child of children){
          const childId=String(child?.id||''),childName=text(child).trim(),parentId=String(child?.parentId||'');
          if(child?.kind!=='subregion'||!/^[1-9][0-9]*$/.test(childId)||!childName||seen.has(childId)||parentId!==id)throw new Error('Не удалось проверить канонический справочник подкурортов.');
          const childNative=tourvisorIds(child);seen.add(childId);
          items.push({id:childId,name:childName,country:key,kind:'subregion',parentId:id,tourvisorIds:childNative});
        }
      }
      for(const item of items)if(item.kind==='subregion'&&!regionIds.has(item.parentId))throw new Error('Не удалось проверить иерархию курортов.');
      catalog.regions[key]=items;return items;
    }).finally(()=>regionRequests.delete(key));
    regionRequests.set(key,request);return request;
  }
  function destinationScope(s,filters) {
    const regionIds=[],subregionIds=[];
    for(const name of filters.resorts||[]){
      const found=(catalog.regions[String(s.country)]||[]).filter(row=>row.name===name);
      if(found.length!==1)throw new Error('Выберите курорт из канонического справочника.');
      const row=found[0],target=row.kind==='region'?regionIds:row.kind==='subregion'?subregionIds:null;
      if(!target)throw new Error('Некорректный тип направления.');
      target.push(...tourvisorIds(row));
    }
    const unique=ids=>[...new Set(ids)].sort((a,b)=>Number(a)-Number(b));
    return {regionIds:unique(regionIds),subregionIds:unique(subregionIds)};
  }
  function regionIds(s,filters) {return destinationScope(s,filters).regionIds;}
  function subregionIds(s,filters) {return destinationScope(s,filters).subregionIds;}
  function supplierScope(filters = {}, hotelIds = []) {
    const selectedMeal=filters.meals?.length===1?String(filters.meals[0]||'').trim():'';
    const selectedPlans=selectedMeal?catalog.mealPlans.filter(plan=>plan.nameRu===selectedMeal&&plan.nativeIds.length):[];
    if(selectedMeal&&selectedPlans.length!==1)throw new Error('Выберите питание из канонического справочника.');
    const chosenMealIds=selectedPlans.length?[...selectedPlans[0].nativeIds]:[];
    const stars=(filters.stars||[]).filter(x=>Number.isInteger(x)&&x>=1&&x<=5);
    const resorts=[...new Set((filters.resorts||[]).map(value=>String(value||'').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'ru'));
    const exactHotel=Number(filters.hotelId),supplierHotels=[...new Set((hotelIds||[]).map(String).filter(Boolean))].sort();
    const hotel=Number.isSafeInteger(exactHotel)&&exactHotel>0?'local:'+exactHotel:supplierHotels.length?'supplier:'+supplierHotels.join(','):'';
    const priceFrom=filters.min>0?String(filters.min):'',hasMax=filters.max!==null&&filters.max!==undefined&&filters.max!=='',priceTo=hasMax?String(filters.max):'';
    return Object.freeze({
      hotel,
      resorts:Object.freeze(resorts),
      // Canonical aliases can map to several supplier meal IDs. Narrow only an
      // unambiguous single ID; Search3 applies the exact canonical predicate
      // after all sources join.
      meal:chosenMealIds.length===1?chosenMealIds[0]:'',
      // The upstream category field cannot express an exact OR-set.
      hotelCategory:stars.length===1?String(stars[0]):'',
      priceFrom,
      priceTo,
      min:priceFrom?Number(priceFrom):0,
      max:priceTo===''?null:Number(priceTo)
    });
  }
  function supplierScopeCovered(previous,next) {
    if(!previous||!next||!Array.isArray(previous.resorts)||!Array.isArray(next.resorts)
      ||!Number.isFinite(previous.min)||!Number.isFinite(next.min)
      ||previous.max!==null&&!Number.isFinite(previous.max)||next.max!==null&&!Number.isFinite(next.max))return false;
    if(previous.hotel&&previous.hotel!==next.hotel)return false;
    if(previous.resorts.length&&(!next.resorts.length||!next.resorts.every(value=>previous.resorts.includes(value))))return false;
    if(previous.hotelCategory&&previous.hotelCategory!==next.hotelCategory)return false;
    if(previous.meal&&previous.meal!==next.meal)return false;
    if(next.min<previous.min)return false;
    if(previous.max!==null&&(next.max===null||next.max>previous.max))return false;
    return true;
  }
  function requestPlan(s, hotelIds = [], filters = {}) {
    const departure = catalog.departures.find(x => text(x) === s.origin || String(x.id) === s.origin);
    if (!departure) throw new Error('Выберите город вылета из загруженного списка.');
    const nativeCountryId=tourvisorCountryId(s.country);
    if (!date(s.from) || !date(s.to) || s.from > s.to || (new Date(s.to) - new Date(s.from)) / 86400000 > 21) throw new Error('Выберите диапазон вылета не больше 21 дня.');
    if (!Number.isInteger(s.adults) || s.adults < 1 || s.adults > 6 || !Array.isArray(s.ages) || s.ages.length > 3 || s.ages.some(x => !Number.isInteger(x) || x < 0 || x > 17)) throw new Error('Укажите возраст каждого ребёнка.');
    if (!Number.isInteger(s.minNights) || !Number.isInteger(s.maxNights) || s.minNights < 1 || s.maxNights > 28 || s.maxNights < s.minNights || s.maxNights - s.minNights > 10) throw new Error('Проверьте диапазон ночей.');
    const scope=supplierScope(filters,hotelIds),destination=destinationScope(s,filters);
    const request={departureId:String(departure.id),countryId:nativeCountryId,dateFrom:s.from,dateTo:s.to,nightsFrom:s.minNights,nightsTo:s.maxNights,adults:s.adults,childs:[...s.ages].sort((a,b)=>a-b),meal:scope.meal,hotelCategory:scope.hotelCategory,hotelRating:'',hotelTypes:[],hotelIds:hotelIds.map(String),hotelServices:[],arrivalId:'',regionIds:destination.regionIds,subregionIds:destination.subregionIds,operatorIds:[],priceFrom:scope.priceFrom,priceTo:scope.priceTo,currency:'RUB',onlyCharter:false,onlyDirect:false};
    return {request,scope};
  }
  function params(s, hotelIds = [], filters = {}) {
    return requestPlan(s,hotelIds,filters).request;
  }
  function sameScope(request, response) {
    if (!response || response.scopeVersion !== 1 || Object.keys(response).length !== Object.keys(request).length + 1) return false;
    return Object.keys(request).every(key => {
      const a=request[key], b=response[key];
      if (Array.isArray(a)) return Array.isArray(b) && JSON.stringify(a.map(String).sort()) === JSON.stringify(b.map(String).sort());
      return typeof a === 'boolean' ? a === b : b !== null && String(a) === String(b);
    });
  }
  function providerDestinationScopes(p) {
    const regions=Array.isArray(p?.regionIds)?[...p.regionIds]:[],subregions=Array.isArray(p?.subregionIds)?[...p.subregionIds]:[];
    if(!regions.length||!subregions.length)return [p];
    return [
      {...p,regionIds:regions,subregionIds:[]},
      {...p,regionIds:[],subregionIds:subregions}
    ];
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
    return {id:own,legacyIds:(h.canonicalLegacyIds || []).map(String),name:text(h.name),country:String(s.country),region,subRegion,resort:subRegion||region,stars:Number(h.category)||0,rating:rating>0&&rating<=ratingScale?rating*5/ratingScale:null,beach:null,family:null,spa:null,pool:null,amenities:amenities(h),photos:[...new Set(photos)],note:text(h.description),tag:'',raw:h,offers:[]};
  }
  function offer(t, h, s, index) {
    const price=amount(t.price), day=date(t.date), nights=Number(t.nights);
    if(!price || !day || !Number.isInteger(nights) || nights<1) return null;
    const provider=String(t.provider||'tourvisor').toLowerCase(),plan=mealPlan(t,provider);
    const rawMeal=[text(t.meal),text(t.meal?.fullName),text(t.meal?.russianName)].map(value=>value.trim()).find(Boolean)||'';
    const displayMeal=meal(t.meal)||rawMeal;
    return {key:encodeURIComponent(`${provider}:${String(t.id)}`),hotelId:h.id,day,nights,variant:index,total:price,returnDay:plus(day,nights),room:text(t.roomType)||'Номер уточняется',placement:text(t.placement),adults:s.adults,ages:[...s.ages],origin:s.origin,
      mealPlanId:plan?.id??null,mealPlanCode:plan?.code||'',mealFacet:plan?.nameRu||'',meal:plan?.nameRu||displayMeal||'Питание уточняется',mealRaw:rawMeal,
      operator:operator(t.operator)||'Туроператор уточняется',flight:t.isCharter===true?'charter':t.isCharter===false?'regular':'unknown',cached:t.cachedListing===true,provider,raw:t,search:structuredClone(s),fuel:t.fuelCharge??null,flightChoiceId:null};
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
    activeVerification?.abort();activeVerification=null;andromedaQuoteChoices.clear();anexCurrentReceipts.clear();anexAdditionalAttempts.clear();
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
      anexLocalHotelId:hotel.local_id,anexGeneration:Number.isInteger(run?.generation)?run.generation:generation,
      anexSessionCurrent:run?.anexSessionCurrent===true};
  }
  function directFirstWeekScopes(p){
    const end=plus(p.dateFrom,6)<p.dateTo?plus(p.dateFrom,6):p.dateTo;
    return providerDestinationScopes({...p,dateTo:end});
  }
  function directAnexWindows(p){ return directFirstWeekScopes(p); }
  function rebuildDirectAnex(run){
    const windows=run.anexWindows;
    if(!(windows instanceof Map)||!windows.size)throw new Error('Invalid ANEX window state');
    const order=[...windows.keys()].sort((a,b)=>a-b),seenOffers=new Set(),visibleHotels=new Set(),receivedHotels=new Set();
    let receivedOffers=0,mappedOffers=0,scopeFilteredOffers=0,deduplicatedOffers=0;
    owner.clearOffers('direct-anex');
    for(const index of order){
      const window=windows.get(index);
      receivedOffers+=window.receivedOffers;mappedOffers+=window.mappedOffers;scopeFilteredOffers+=window.scopeFilteredOffers;
      for(const id of window.hotelIds)receivedHotels.add(id);
      for(const entry of window.prepared){
        if(seenOffers.has(entry.tour.offerRef)){deduplicatedOffers++;continue;}
        seenOffers.add(entry.tour.offerRef);visibleHotels.add(entry.legacyHotelId);
        owner.upsertLegacyOffer(entry.legacyHotelId,entry.tour,{source:'direct-anex'});
      }
    }
    owner.refresh();
    const total=run.anexWindowsTotal||order.length,contiguous=order.every((value,index)=>value===index);
    const status=contiguous&&order.length===total?'complete':'partial',first=windows.get(order[0]),last=windows.get(order[order.length-1]);
    run.sourceCounts.anex={status,hotels:visibleHotels.size,offers:seenOffers.size,receivedHotels:receivedHotels.size,receivedOffers,
      mappedHotels:receivedHotels.size,mappedOffers,visibleHotels:visibleHotels.size,visibleOffers:seenOffers.size,
      scopeFilteredOffers,deduplicatedOffers,windowsLoaded:order.length,windowsTotal:total,dateFrom:first.dateFrom,dateTo:last.dateTo};
    return run.sourceCounts.anex;
  }
  async function applyDirectAnex(run,data,p,index,total){
    const emptyExcluded=Array.isArray(data?.hotels)&&data.hotels.length===0&&Number(data.pages_read)===0&&data.search_ref===undefined;
    if(!data||data.provider!=='anex'||data.generation!==run.generation||!Array.isArray(data.hotels)||data.hotels.length>300
      ||!data.date_range||data.date_range.from!==p.dateFrom||data.date_range.to!==p.dateTo
      ||!Number.isInteger(index)||index<0||!Number.isInteger(total)||total<1||index>=total
      ||(!emptyExcluded&&(typeof data.search_ref!=='string'||!(/^[a-f0-9]{32}$/).test(data.search_ref))))throw new Error('Invalid ANEX search response');
    const windows=run.anexWindows||(run.anexWindows=new Map());
    if(windows.has(index))throw new Error('Invalid ANEX window sequence');
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
    const priorOffers=[...windows.values()].reduce((sum,window)=>sum+window.receivedOffers,0);
    if(priorOffers+receivedOffers>1200)throw new Error('Invalid ANEX result size');
    windows.set(index,{prepared,hotelIds:[...seenHotels],receivedOffers,mappedOffers:receivedOffers,
      scopeFilteredOffers:Math.max(0,receivedOffers-prepared.length),dateFrom:data.date_range.from,dateTo:data.date_range.to});
    run.anexWindowsTotal=total;
    return rebuildDirectAnex(run);
  }
  async function requestDirectAnex(run,p,url){
    const response=await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',signal:run.controller.signal,
      headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
      body:JSON.stringify({action:'search',generation:run.generation,params:p})});
    const payload=await response.json().catch(()=>null),data=payload&&payload.data;
    if(!current(run))return null;
    if(!response.ok||payload?.ok!==true)throw new Error('ANEX search unavailable');
    return data;
  }
  async function continueDirectAnex(run,url,windows,startIndex){
    let index=startIndex;
    try{
      while(current(run)&&index<windows.length){
        const data=await requestDirectAnex(run,windows[index],url);if(!data||!current(run))return;
        const result=await applyDirectAnex(run,data,windows[index],index,windows.length);if(!current(run))return;
        notify({type:'provider',provider:'anex',...result});index++;
      }
      if(current(run))clearCalendarWindows();
    }catch(error){
      if(!current(run)||error?.name==='AbortError')return;
      const previous=run.sourceCounts.anex;
      if(previous&&Number.isInteger(previous.windowsLoaded)&&previous.windowsLoaded>0){
        run.sourceCounts.anex={...previous,status:'partial',continuationFailed:true};
        notify({type:'provider',provider:'anex',...run.sourceCounts.anex});
        clearCalendarWindows();
      }
    }
  }
  async function enrichAnex(run,p){
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url||!current(run)){run.sourceCounts.anex={status:'skipped',hotels:0,offers:0};return;}
    notify({type:'provider',provider:'anex',status:'loading'});if(!current(run))return;
    const windows=directAnexWindows(p);
    if(windows.length===1){
      try{
        const first=await requestDirectAnex(run,windows[0],url.href);if(!first||!current(run))return;
        const result=await applyDirectAnex(run,first,windows[0],0,1);if(!current(run))return;
        notify({type:'provider',provider:'anex',...result});
      }catch(error){
        if(!current(run)||error?.name==='AbortError')return;
        run.sourceCounts.anex={status:'error',hotels:0,offers:0};
        notify({type:'provider',provider:'anex',status:'error'});
      }
      return;
    }
    let loaded=0,failed=0;
    for(let index=0;current(run)&&index<windows.length;index++){
      try{
        const resultData=await requestDirectAnex(run,windows[index],url.href);if(!resultData||!current(run))return;
        const result=await applyDirectAnex(run,resultData,windows[index],index,windows.length);if(!current(run))return;
        loaded++;notify({type:'provider',provider:'anex',...result});
      }catch(error){
        if(!current(run)||error?.name==='AbortError')return;
        failed++;
      }
    }
    if(!current(run))return;
    if(!loaded){
      run.sourceCounts.anex={status:'error',hotels:0,offers:0};
      notify({type:'provider',provider:'anex',status:'error'});return;
    }
    if(failed){
      const previous=run.sourceCounts.anex||{hotels:0,offers:0};
      run.sourceCounts.anex={...previous,status:'partial',destinationBranchFailed:true};
      notify({type:'provider',provider:'anex',...run.sourceCounts.anex});
    }
    clearCalendarWindows();
  }
  async function directAndromedaOffer(hotel,tour,run,p,seen,data){
    const price=tour&&tour.price,context=tour&&tour.offer_context;
    if(!tour||typeof tour!=='object'||tour.provider!=='andromeda'||!price||price.currency!=='RUB'
      ||typeof price.amount!=='string'||!(/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/).test(price.amount)
      ||Number(price.amount)<=0||!context||context.provider!=='andromeda'
      ||context.search_ref!==data.search_ref||context.generation!==run.generation
      ||!Number.isInteger(context.page)||context.page<1||context.page>1000||context.page!==data.page
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
  function cachedRehydration(o){
    const r=o?.raw?.rehydration,party=r?.party,legacy=Number(r?.legacy_hotel_id),own=Number(r?.anytour_hotel_id);
    if(!o||o.cached!==true||!r||r.schema_version!==1||r.provider!==o.provider||!['anex','andromeda'].includes(r.provider)
      ||!Number.isSafeInteger(legacy)||legacy<1||!Number.isSafeInteger(own)||own!==Number(o.hotelId)
      ||r.checkin!==o.day||!Number.isInteger(r.nights)||r.nights!==o.nights
      ||!party||!Number.isInteger(party.adults)||party.adults!==o.adults||!Number.isInteger(party.children)
      ||!Array.isArray(party.child_ages)||party.children!==party.child_ages.length
      ||party.child_ages.some(age=>!Number.isInteger(age)||age<0||age>17)
      ||JSON.stringify(party.child_ages)!==JSON.stringify(o.ages||[])
      ||!r.operator||typeof r.operator.name!=='string'||!r.operator.name.trim())return null;
    return {provider:r.provider,legacyHotelId:legacy,anytourHotelId:own,checkin:r.checkin,nights:r.nights,
      adults:party.adults,ages:[...party.child_ages],operator:r.operator.name.trim()};
  }
  function cachedRehydrationSearch(o,r){
    const base=o.search;
    if(!base||typeof base!=='object'||Array.isArray(base)||typeof base.origin!=='string'||!base.origin||typeof base.country!=='string'||!base.country)return null;
    return {...structuredClone(base),from:r.checkin,to:r.checkin,minNights:r.nights,maxNights:r.nights,adults:r.adults,ages:[...r.ages]};
  }
  async function rehydrateAnexCached(o,r,exact,p,controller,epoch){
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url)throw new Error('ANEX сейчас недоступен.');
    const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
      headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
      body:JSON.stringify({action:'search',generation:epoch,params:p})});
    const payload=await response.json().catch(()=>null),data=payload&&payload.data;
    if(controller.signal.aborted||epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
    if(!response.ok||payload?.ok!==true||!data)throw new Error(response.status===429?'Лимит проверки ANEX временно исчерпан.':'ANEX не смог обновить сохранённое предложение.');
    const empty=Array.isArray(data.hotels)&&data.hotels.length===0&&data.search_ref===undefined;
    if(data.provider!=='anex'||data.generation!==epoch||!data.date_range||data.date_range.from!==r.checkin||data.date_range.to!==r.checkin
      ||!Array.isArray(data.hotels)||data.hotels.length>300||(!empty&&!(/^[a-f0-9]{32}$/).test(String(data.search_ref||''))))throw new Error('ANEX вернул ответ для других условий.');
    const target=data.hotels.find(h=>Number(h?.local_id)===r.legacyHotelId);
    if(!target)return [];
    if(target.catalog?.source!=='tourvisor'||Number(target.catalog?.hotel_id)!==r.legacyHotelId||!Array.isArray(target.tours)||target.tours.length>300)throw new Error('ANEX вернул некорректные варианты отеля.');
    const seen=new Set(),normalized=[];
    for(const tour of target.tours){
      if(tour?.search_ref!==data.search_ref)throw new Error('ANEX вернул устаревший контекст поиска.');
      const item=await directAnexOffer(target,tour,{filters:{},generation:epoch,anexSessionCurrent:true},p,seen);
      if(item)normalized.push(item);
    }
    if(!normalized.length)return [];
    const projected=project([{anytourHotelId:r.anytourHotelId,canonicalLegacyIds:[String(r.legacyHotelId)],name:'ANEX',tours:normalized}],exact)[0]?.offers||[];
    if(projected.length!==normalized.length||projected.some(item=>item.provider!=='anex'||item.cached||Number(item.raw?.anexLocalHotelId)!==r.legacyHotelId))throw new Error('Не удалось подготовить актуальные варианты ANEX.');
    return projected;
  }
  async function rehydrateAndromedaCached(o,r,exact,p,controller,epoch){
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaApi,'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php');
    if(!url)throw new Error('Andromeda сейчас недоступна.');
    const offers=[],seen=new Set();let page=1,pages=1;
    do{
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
        headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
        body:JSON.stringify({generation:epoch,page,params:p})});
      const payload=await response.json().catch(()=>null),data=payload&&payload.data;
      if(controller.signal.aborted||epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
      if(!response.ok||payload?.ok!==true||!data)throw new Error(response.status===429?'Лимит проверки Andromeda временно исчерпан.':'Andromeda не смогла обновить сохранённое предложение.');
      if(data.provider!=='andromeda'||data.generation!==epoch||data.page!==page||!Number.isInteger(data.pages_count)||data.pages_count<1||data.pages_count>20
        ||!data.date_range||data.date_range.from!==r.checkin||data.date_range.to!==r.checkin||!Array.isArray(data.hotels)||data.hotels.length>300)throw new Error('Andromeda вернула ответ для других условий.');
      if(page===1)pages=data.pages_count;else if(data.pages_count!==pages)throw new Error('Andromeda изменила страницы во время проверки.');
      const target=data.hotels.find(h=>Number(h?.local_id)===r.legacyHotelId);
      if(target){
        if(target.mapping_status!=='resolved'||!Array.isArray(target.tours)||target.tours.length>300)throw new Error('Andromeda вернула некорректные варианты отеля.');
        for(const tour of target.tours){
          const item=await directAndromedaOffer(target,tour,{filters:{},generation:epoch},p,seen,data);
          if(item){item.rehydrationParams=structuredClone(p);offers.push(item);}
        }
      }
      page++;
    }while(page<=pages);
    if(!offers.length)return [];
    const projected=project([{anytourHotelId:r.anytourHotelId,canonicalLegacyIds:[String(r.legacyHotelId)],name:'Andromeda',tours:offers}],exact)[0]?.offers||[];
    if(projected.length!==offers.length||projected.some(item=>item.provider!=='andromeda'||item.cached||Number(item.raw?.andromedaLocalHotelId)!==r.legacyHotelId))throw new Error('Не удалось подготовить актуальные варианты Andromeda.');
    return projected;
  }
  async function rehydrateCached(o){
    const r=cachedRehydration(o);if(!r)return Object.freeze({state:'unsupported',provider:String(o?.provider||''),offers:Object.freeze([])});
    const exact=cachedRehydrationSearch(o,r);if(!exact)throw new Error('Не удалось восстановить условия сохранённого тура.');
    const p=params(exact,[String(r.legacyHotelId)],{}),epoch=generation;
    activeVerification?.abort();const controller=new AbortController();activeVerification=controller;
    const timeout=setTimeout(()=>controller.abort(),45000);
    try{
      const rows=r.provider==='anex'
        ?await rehydrateAnexCached(o,r,exact,p,controller,epoch)
        :await rehydrateAndromedaCached(o,r,exact,p,controller,epoch);
      if(controller.signal.aborted||epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
      return Object.freeze({state:rows.length?'current':'empty',provider:r.provider,hotelId:r.anytourHotelId,
        offers:Object.freeze(rows.map(item=>structuredClone(item)))});
    }finally{clearTimeout(timeout);if(activeVerification===controller)activeVerification=null;}
  }
  function andromedaBranch(run,index,total,p){
    if(!Number.isInteger(index)||index<0||!Number.isInteger(total)||total<1||index>=total)throw new Error('Invalid Andromeda destination branch');
    const branches=run.andromedaBranches||(run.andromedaBranches=new Map());
    if(run.andromedaBranchesTotal!==undefined&&run.andromedaBranchesTotal!==total)throw new Error('Invalid Andromeda destination branch count');
    run.andromedaBranchesTotal=total;
    let branch=branches.get(index);
    if(!branch){branch={pages:new Map(),searchRef:null,pagesTotal:0,params:p};branches.set(index,branch);}
    return branch;
  }
  function rebuildDirectAndromeda(run){
    const branches=run.andromedaBranches;
    if(!(branches instanceof Map)||!branches.size)throw new Error('Invalid Andromeda branch state');
    const branchOrder=[...branches.keys()].sort((a,b)=>a-b),seenOffers=new Set(),visibleHotels=new Set(),receivedHotels=new Set();
    let projectedOffers=0,receivedOffers=0,mappedOffers=0,scopeFilteredOffers=0,deduplicatedOffers=0,pagesLoaded=0,pagesTotal=0,allComplete=true,first=null;
    owner.clearOffers('direct-andromeda');
    for(const branchIndex of branchOrder){
      const branch=branches.get(branchIndex),pages=branch.pages,order=[...pages.keys()].sort((a,b)=>a-b);
      if(!(pages instanceof Map)||!order.length)throw new Error('Invalid Andromeda page state');
      const branchPagesTotal=branch.pagesTotal||order[order.length-1],contiguous=order.every((page,index)=>page===index+1);
      pagesLoaded+=order.length;pagesTotal+=branchPagesTotal;
      allComplete=allComplete&&contiguous&&order.length===branchPagesTotal;
      for(const pageNumber of order){
        const page=pages.get(pageNumber);if(!first)first=page;
        projectedOffers+=page.projectedOffers;receivedOffers+=page.receivedOffers;mappedOffers+=page.mappedOffers;
        scopeFilteredOffers+=page.scopeFilteredOffers;allComplete=allComplete&&page.status==='complete';
        for(const id of page.hotelIds)receivedHotels.add(id);
        for(const entry of page.prepared){
          if(seenOffers.has(entry.tour.offerRef)){deduplicatedOffers++;continue;}
          seenOffers.add(entry.tour.offerRef);visibleHotels.add(entry.legacyHotelId);
          owner.upsertLegacyOffer(entry.legacyHotelId,entry.tour,{source:'direct-andromeda'});
        }
      }
    }
    owner.refresh();
    const branchesTotal=run.andromedaBranchesTotal||branchOrder.length,contiguousBranches=branchOrder.every((value,index)=>value===index);
    const status=contiguousBranches&&branchOrder.length===branchesTotal&&allComplete?'complete':'partial';
    run.sourceCounts.andromeda={status,hotels:visibleHotels.size,offers:seenOffers.size,
      receivedHotels:receivedHotels.size,mappedHotels:receivedHotels.size,projectedOffers,receivedOffers,mappedOffers,
      visibleHotels:visibleHotels.size,visibleOffers:seenOffers.size,scopeFilteredOffers,deduplicatedOffers,
      branchesLoaded:branchOrder.length,branchesTotal,pagesLoaded,pagesTotal,dateFrom:first.dateFrom,dateTo:first.dateTo};
    return run.sourceCounts.andromeda;
  }
  async function applyDirectAndromeda(run,data,p,branchIndex=0,branchTotal=1){
    if(!data||data.provider!=='andromeda'||data.generation!==run.generation||!Array.isArray(data.hotels)||data.hotels.length>5000
      ||!data.date_range||data.date_range.from!==p.dateFrom||data.date_range.to!==p.dateTo
      ||typeof data.search_ref!=='string'||!(/^[a-f0-9]{64}$/).test(data.search_ref)
      ||!Number.isInteger(data.page)||data.page<1||data.page>1000
      ||!Number.isInteger(data.pages_count)||data.pages_count<data.page||data.pages_count>1000
      ||!['complete','partial'].includes(data.status)||data.selection_enabled!==false||data.first_page_only!==false)throw new Error('Invalid Andromeda search response');
    const branch=andromedaBranch(run,branchIndex,branchTotal,p),pages=branch.pages;
    if(data.page===1){
      if(pages.size||branch.searchRef&&branch.searchRef!==data.search_ref)throw new Error('Invalid Andromeda page sequence');
      branch.searchRef=data.search_ref;
    }else if(branch.searchRef!==data.search_ref||pages.has(data.page)||!pages.has(data.page-1))throw new Error('Invalid Andromeda page sequence');
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
    const branches=run.andromedaBranches;
    const accumulated=[...branches.values()].reduce((sum,item)=>sum+[...item.pages.values()].reduce((inner,page)=>inner+page.projectedOffers,0),0);
    if(accumulated+projectedOffers>15000)throw new Error('Invalid Andromeda result size');
    const allHotels=new Set([...branches.values()].flatMap(item=>[...item.pages.values()].flatMap(page=>page.hotelIds)));
    for(const id of seenHotels)allHotels.add(id);
    if(allHotels.size>5000)throw new Error('Invalid Andromeda result size');
    const receivedOffers=Number.isInteger(data.received_offers)&&data.received_offers>=projectedOffers?data.received_offers:projectedOffers;
    const mappedOffers=Number.isInteger(data.mapped_offers)&&data.mapped_offers>=projectedOffers?data.mapped_offers:projectedOffers;
    pages.set(data.page,{status:data.status,prepared,hotelIds:[...seenHotels],projectedOffers,receivedOffers,mappedOffers,
      scopeFilteredOffers:Math.max(0,projectedOffers-prepared.length),dateFrom:data.date_range.from,dateTo:data.date_range.to});
    branch.pagesTotal=data.pages_count;
    return rebuildDirectAndromeda(run);
  }
  async function requestDirectAndromeda(run,p,url,page){
    const response=await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',signal:run.controller.signal,
      headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
      body:JSON.stringify({generation:run.generation,page,params:p})});
    const payload=await response.json().catch(()=>null),data=payload&&payload.data;
    if(!current(run))return null;
    if(!response.ok||payload?.ok!==true)throw new Error('Andromeda search unavailable');
    return data;
  }
  async function continueDirectAndromeda(run,p,url,startPage,pagesTotal,branchIndex=0,branchTotal=1){
    let page=startPage,target=pagesTotal;
    try{
      while(current(run)&&page<=target){
        const data=await requestDirectAndromeda(run,p,url,page);if(!data||!current(run))return;
        const result=await applyDirectAndromeda(run,data,p,branchIndex,branchTotal);if(!current(run))return;
        target=data.pages_count;notify({type:'provider',provider:'andromeda',...result});page++;
      }
      if(current(run))clearCalendarWindows();
    }catch(error){
      if(!current(run)||error?.name==='AbortError')return;
      const previous=run.sourceCounts.andromeda;
      if(previous&&Number.isInteger(previous.pagesLoaded)&&previous.pagesLoaded>0){
        run.sourceCounts.andromeda={...previous,status:'partial',continuationFailed:true};
        notify({type:'provider',provider:'andromeda',...run.sourceCounts.andromeda});
        clearCalendarWindows();
      }
    }
  }
  async function enrichAndromeda(run,p){
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaApi,'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php');
    if(!url||!current(run)){run.sourceCounts.andromeda={status:'skipped',hotels:0,offers:0};return;}
    notify({type:'provider',provider:'andromeda',status:'loading'});if(!current(run))return;
    const scopes=directFirstWeekScopes(p);let loaded=0,failed=0;
    for(let branchIndex=0;current(run)&&branchIndex<scopes.length;branchIndex++){
      const scope=scopes[branchIndex];
      try{
        const data=await requestDirectAndromeda(run,scope,url.href,1);if(!data||!current(run))return;
        const result=await applyDirectAndromeda(run,data,scope,branchIndex,scopes.length);if(!current(run))return;
        loaded++;notify({type:'provider',provider:'andromeda',...result});
        // Provider persistence may update calendar/SEO data, but stored offers never re-enter this live union.
        clearCalendarWindows();if(!current(run))return;
        if(data.pages_count>1){
          notify({type:'provider',provider:'andromeda',...result,status:'loading',background:true});
          await continueDirectAndromeda(run,scope,url.href,2,data.pages_count,branchIndex,scopes.length);
        }
      }catch(error){
        if(!current(run)||error?.name==='AbortError')return;
        failed++;
        if(scopes.length===1){
          owner.clearOffers('direct-andromeda');owner.refresh();
          run.sourceCounts.andromeda={status:'error',hotels:0,offers:0};
          notify({type:'provider',provider:'andromeda',status:'error'});return;
        }
        const failedBranch=run.andromedaBranches?.get(branchIndex);
        if(failedBranch&&failedBranch.pages instanceof Map&&!failedBranch.pages.size)run.andromedaBranches.delete(branchIndex);
      }
    }
    if(!current(run))return;
    if(!loaded){
      owner.clearOffers('direct-andromeda');owner.refresh();
      run.sourceCounts.andromeda={status:'error',hotels:0,offers:0};
      notify({type:'provider',provider:'andromeda',status:'error'});return;
    }
    if(failed){
      const previous=run.sourceCounts.andromeda||{hotels:0,offers:0};
      run.sourceCounts.andromeda={...previous,status:'partial',destinationBranchFailed:true};
      notify({type:'provider',provider:'andromeda',...run.sourceCounts.andromeda});
      clearCalendarWindows();
    }
  }
  async function settleInitialSources(run){
    await Promise.allSettled([run.anex,run.andromeda]);
    return current(run);
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
        clearCalendarWindows();if(!current(run))return;
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
  async function resumeCached(s, callback, hotelIds=[], filters={}) {
    const plan=requestPlan(s,hotelIds,filters),p=plan.request,epoch=stop();
    currentSupplierScope=plan.scope;notify=callback;context=structuredClone(s);searchParams=structuredClone(p);
    raw=[];searchId=0;rt.setSearchId(0);owner?.reset();
    const skipped=()=>({status:'skipped',hotels:0,offers:0});
    const run={generation:epoch,search:structuredClone(s),hotelIds:[...hotelIds],filters:structuredClone(filters),
      controller:new AbortController(),pending:false,searchId:0,resumeOnly:true,continued:false,expired:false,canContinue:false,
      continueBaseline:null,lastProgress:-10,lastRead:0,deadline:0,
      sourceCounts:{tourvisor:skipped(),anex:skipped(),andromeda:skipped(),database:skipped()}};
    activeSearch=run;callback({type:'loading',cachedResume:true});if(!current(run))return false;
    notify({type:'complete',cachedResume:true,partial:false,canContinue:false,retryRead:false,resultLimitReached:false,
      sources:structuredClone(run.sourceCounts),union:canonicalUnion()});
    return true;
  }
  async function search(s, callback, hotelIds=[], filters={}) {
    const plan=requestPlan(s,hotelIds,filters),p=plan.request,epoch=stop();currentSupplierScope=plan.scope;notify=callback;context=structuredClone(s);searchParams=structuredClone(p);raw=[];searchId=0;rt.setSearchId(0);owner?.reset();
    const run={generation:epoch,search:structuredClone(s),hotelIds:[...hotelIds],filters:structuredClone(filters),controller:new AbortController(),pending:true,searchId:0,resumeOnly:true,continued:false,expired:false,canContinue:true,continueBaseline:null,lastProgress:-10,lastRead:0,deadline:0,sourceCounts:{database:{status:'skipped',hotels:0,offers:0}}};
    activeSearch=run;callback({type:'loading'});if(!current(run))return;
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
    // Party and canonical region OR are exact first-class observation scopes.
    // The observation reader has no exact subregion dimension yet: suppress it
    // instead of presenting a broader parent-region minimum as exact.
    if(filters.hotelId||filters.q||filters.min>0
      ||filters.max!==undefined&&filters.max!==null&&filters.max!==''
      ||['stars','meals','operators','flight','amenities'].some(key=>filters[key]?.length)
      ||['rating','beach','family','spa'].some(key=>filters[key]))return false;
    try{if(destinationScope(s,filters).subregionIds.length)return false;}catch{return false;}
    return true;
  }
  async function observedCalendar(s,from,to,signal,filters={}) {
    if(!observationScopeSupported(s,filters))return [];
    const departure=catalog.departures.find(x=>text(x)===s.origin||String(x.id)===s.origin);
    if(!departure)return [];
    let nativeCountryId;try{nativeCountryId=tourvisorCountryId(s.country);}catch{return [];}
    const childAges=[...s.ages].sort((a,b)=>a-b),childSignature=childAges.join(',');
    const destination=destinationScope(s,filters);if(destination.subregionIds.length)return [];
    const selected=[...new Set(destination.regionIds.map(Number))].sort((a,b)=>a-b);
    const query={departureId:String(departure.id),countryId:nativeCountryId,dateFrom:from,dateTo:to,nightsFrom:String(s.minNights),nightsTo:String(s.maxNights),adults:String(s.adults),childs:childSignature,regionIds:selected.map(String)};
    const response=await fetch(local+'data/search3-local-results-read-v1.php',{
      method:'POST',credentials:'same-origin',cache:'no-store',signal,
      headers:{Accept:'application/json','Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
      body:JSON.stringify({action:'price_calendar',departureId:Number(query.departureId),countryId:Number(query.countryId),regionIds:selected,
        dateFrom:from,dateTo:to,nightsFrom:s.minNights,nightsTo:s.maxNights,adults:s.adults,childs:childAges})
    });
    if(!response.ok)throw new Error('Сохранённые цены календаря временно недоступны.');
    const payload=await response.json(),result=payload?.data;
    if(payload?.ok!==true)throw new Error('Сохранённые цены календаря временно недоступны.');
    if(result?.ok!==true||result.source!=='latest-known-exact-segments-from-anytour-first-party-observations'
      ||result.cachedPriceIsFinal!==false||result.currency!=='RUB'||result.adults!==s.adults||result.childrenCount!==childAges.length
      ||!Array.isArray(result.childAges)||result.childAges.length!==childAges.length||result.childAges.some((age,index)=>age!==childAges[index])||result.childAgesSignature!==childSignature
      ||String(result.departureId)!==query.departureId||String(result.countryId)!==query.countryId
      ||!Array.isArray(result.regionIds)||result.regionIds.map(String).join(',')!==query.regionIds.join(',')
      ||String(result.regionId||'')!==(query.regionIds.length===1?query.regionIds[0]:'')||result.dateFrom!==from||result.dateTo!==to
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
    try{
      const response=await fetch(local+'data/search3-local-results-read-v1.php',{
        method:'POST',credentials:'same-origin',cache:'no-store',
        headers:{Accept:'application/json','Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
        body:JSON.stringify({action:'meal_catalog',provider:'tourvisor',scopeKey:'global'})
      });
      const payload=await response.json();
      if(!response.ok||payload?.ok!==true||!payload.data||!installMealPlans({ok:true,...payload.data}))throw new Error('Invalid canonical meal catalogue');
    }catch{catalog.mealPlans=[];catalog.mealPlanAvailable=false;catalog.mealPlanRevision=null;}
    return countries(origin);
  }
  let catalogGeneration=0;
  async function countries(origin) {
    const run=++catalogGeneration,departure=catalog.departures.find(x=>text(x)===origin)||catalog.departures[0];
    const rows=await destinationCatalog('countries',{departureId:departure.id});
    if(run!==catalogGeneration)return null;if(!rows.length)throw new Error('Для этого города список стран недоступен.');
    const seen=new Set(),items=[];
    for(const row of rows){
      const id=String(row?.id||''),name=text(row).trim();
      if(!/^[1-9][0-9]*$/.test(id)||!name||seen.has(id))throw new Error('Не удалось проверить канонический список стран.');
      const native=tourvisorIds(row);if(native.length!==1)throw new Error('Для страны нет однозначного соответствия Tourvisor.');
      seen.add(id);items.push({id,name,russianName:name,tourvisorIds:native});
    }
    catalog.countries=items;catalog.regions={};return {departures:catalog.departures,countries:items,origin:text(departure)};
  }
  async function savedHotels(ids,s){if(!owner)return[];const rows=await Promise.allSettled(ids.slice(0,20).map(id=>owner.readProfile(id)));return rows.filter(r=>r.status==='fulfilled'&&r.value).map(r=>hotel({...r.value,anytourHotelId:r.value.id},s));}
  async function lookupHotels(q,country,signal){
    if(q.trim().length<2)return[];
    const read=async url=>{const r=await fetch(url,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});if(!r.ok)throw new Error('Hotel catalogue unavailable');const p=await r.json();if(p?.ok!==true||!Array.isArray(p.items))throw new Error('Invalid hotel catalogue');return p;};
    const nativeCountryId=tourvisorCountryId(country);
    const found=await read('/data/hotel-search-v1.php?'+new URLSearchParams({q:q.trim(),countryId:nativeCountryId,limit:'10'}));
    const ids=[...new Set(found.items.filter(h=>String(h.country?.id)===nativeCountryId&&Number.isSafeInteger(Number(h.id))&&Number(h.id)>0).map(h=>String(h.id)))].slice(0,10);
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
  async function expandAnexGroup(o){
    const rawOffer=o&&o.raw,localHotelId=Number(rawOffer?.anexLocalHotelId),targetRef=String(rawOffer?.offerRef||'');
    if(!o||o.cached||o.provider!=='anex'||rawOffer?.selectionEnabled!==false||rawOffer?.anexKind!=='group_minimum'
      ||!Number.isSafeInteger(localHotelId)||localHotelId<1||!(/^anex_online:[a-f0-9]{64}$/).test(targetRef)) {
      throw new Error('Выбранное предложение ANEX нельзя конкретизировать.');
    }
    const exact={...structuredClone(o.search),from:o.day,to:o.day,minNights:o.nights,maxNights:o.nights,adults:o.adults,ages:[...o.ages]};
    const filters={};if(o.meal&&!/уточняется/i.test(o.meal))filters.meals=[o.meal];
    const p=params(exact,[String(localHotelId)],filters),epoch=stop();
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url)throw new Error('ANEX сейчас недоступен.');
    const controller=new AbortController();activeVerification=controller;
    const request=async body=>{
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
        headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(body)});
      const payload=await response.json().catch(()=>null);
      if(epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
      if(!response.ok||payload?.ok!==true||!payload.data)throw new Error('ANEX не смог проверить выбранное предложение.');
      return payload.data;
    };
    try{
      const searched=await request({action:'search',generation:epoch,params:p});
      if(searched.provider!=='anex'||searched.generation!==epoch||searched.date_range?.from!==o.day||searched.date_range?.to!==o.day
        ||typeof searched.search_ref!=='string'||!(/^[a-f0-9]{32}$/).test(searched.search_ref)||!Array.isArray(searched.hotels)) {
        throw new Error('ANEX вернул ответ для других условий.');
      }
      const targetHotel=searched.hotels.find(h=>Number(h?.local_id)===localHotelId);
      const targetTour=targetHotel?.tours?.find(t=>t?.offer_ref===targetRef);
      if(!targetTour||targetTour.kind!=='group_minimum'||targetTour.search_ref!==searched.search_ref) {
        throw new Error('Выбранное предложение ANEX изменилось. Откройте актуальные варианты.');
      }
      const expanded=await request({action:'expand',generation:epoch,search_ref:searched.search_ref,offer_ref:targetRef,local_hotel_id:localHotelId});
      if(expanded.provider!=='anex'||expanded.generation!==epoch||expanded.search_ref!==searched.search_ref||expanded.offer_ref!==targetRef
        ||expanded.status!=='expanded'||!Array.isArray(expanded.hotels)||expanded.hotels.length!==1
        ||Number(expanded.hotels[0]?.local_id)!==localHotelId||!Array.isArray(expanded.hotels[0]?.tours)||!expanded.hotels[0].tours.length) {
        throw new Error('ANEX не вернул конкретные варианты выбранного тура.');
      }
      const seen=new Set(),normalized=[];
      for(const tour of expanded.hotels[0].tours){
        if(tour?.kind!=='concrete'||tour?.search_ref!==searched.search_ref)throw new Error('ANEX вернул некорректный конкретный вариант.');
        const item=await directAnexOffer(expanded.hotels[0],tour,{filters,generation:epoch,anexSessionCurrent:true},p,seen);
        if(item)normalized.push(item);
      }
      if(!normalized.length)throw new Error('Конкретные варианты ANEX больше недоступны.');
      const projected=project([{anytourHotelId:o.hotelId,canonicalLegacyIds:[String(localHotelId)],name:'ANEX',tours:normalized}],exact)[0]?.offers||[];
      if(projected.length!==normalized.length||projected.some(item=>item.provider!=='anex'||item.raw?.anexKind!=='concrete'||Number(item.raw?.anexLocalHotelId)!==localHotelId)) {
        throw new Error('Не удалось подготовить конкретные варианты ANEX.');
      }
      return Object.freeze({hotelId:o.hotelId,offers:projected.map(item=>structuredClone(item))});
    }finally{if(activeVerification===controller)activeVerification=null;}
  }
  function anexConcreteKey(o){
    const raw=o&&o.raw,localId=Number(raw?.anexLocalHotelId),epoch=Number(raw?.anexGeneration),offerRef=String(raw?.offerRef||''),searchRef=String(raw?.searchRef||'');
    if(!o||o.cached||o.provider!=='anex'||raw?.selectionEnabled!==false||raw?.anexKind!=='concrete'||raw?.anexSessionCurrent!==true
      ||!Number.isSafeInteger(localId)||localId<1||!Number.isInteger(epoch)||epoch!==generation
      ||!(/^anex_online:[a-f0-9]{64}$/).test(offerRef)||!(/^[a-f0-9]{32}$/).test(searchRef))return null;
    return {key:[epoch,searchRef,offerRef,localId].join('|'),localId,epoch,offerRef,searchRef};
  }
  function anexMoneyFact(value,positive=true){
    if(!value||value.currency!=='RUB'||typeof value.amount!=='string'||!(/^(?:0|[1-9][0-9]{0,10})(?:\.[0-9]{1,4})?$/).test(value.amount))return null;
    const parts=value.amount.split('.'),whole=Number(parts[0]),fraction=Number((parts[1]||'').padEnd(4,'0'));
    const units=whole*10000+fraction;
    if(!Number.isSafeInteger(units)||(positive?units<=0:units<0))return null;
    return Object.freeze({amount:value.amount,currency:'RUB',units});
  }
  function normalizeAnexConcrete(value,o){
    const raw=o?.raw,localId=Number(raw?.anexLocalHotelId),epoch=Number(raw?.anexGeneration),offerRef=String(raw?.offerRef||''),searchRef=String(raw?.searchRef||'');
    if(!value||value.provider!=='anex'||value.generation!==epoch||value.search_ref!==searchRef||value.offer_ref!==offerRef
      ||value.status!=='current'||value.selection_state!=='disabled'||!value.offer||value.offer.final_price_verified!==false
      ||value.offer.context?.current_context_verified!==true||typeof value.finalPriceReady!=='boolean')return null;
    const ready=value.finalPriceReady;
    let finalPrice=null;
    if(ready){
      const amount=String(value.finalPrice??'');
      if(!(/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/).test(amount)||Number(amount)<=0||String(value.price??'')!==amount)return null;
      finalPrice=Object.freeze({amount,currency:'RUB'});
    }else if(value.finalPrice!==null&&value.finalPrice!==undefined||value.price!==null&&value.price!==undefined)return null;
    return Object.freeze({state:'current',currentContextVerified:true,finalPriceReady:ready,finalPrice,
      finalPriceVerified:false,localHotelId:localId,searchRef,offerRef});
  }
  async function verifyAnexConcrete(o){
    const raw=o&&o.raw,localId=Number(raw?.anexLocalHotelId),epoch=Number(raw?.anexGeneration),offerRef=String(raw?.offerRef||''),searchRef=String(raw?.searchRef||'');
    if(!o||o.cached||o.provider!=='anex'||raw?.selectionEnabled!==false||raw?.anexKind!=='concrete'||raw?.anexSessionCurrent!==true
      ||!Number.isSafeInteger(localId)||localId<1||!Number.isInteger(epoch)||epoch!==generation
      ||!(/^anex_online:[a-f0-9]{64}$/).test(offerRef)||!(/^[a-f0-9]{32}$/).test(searchRef)){
      throw new Error('Конкретное предложение ANEX устарело. Откройте актуальные варианты.');
    }
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url)throw new Error('ANEX сейчас недоступен.');
    activeVerification?.abort();const controller=new AbortController();activeVerification=controller;
    const timeout=setTimeout(()=>controller.abort(),30000);
    try{
      const body={action:'offer',generation:epoch,search_ref:searchRef,offer_ref:offerRef,local_hotel_id:localId};
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
        headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(body)});
      const payload=await response.json().catch(()=>null);
      if(controller.signal.aborted||epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
      if(!response.ok||payload?.ok!==true||!payload.data)throw new Error(response.status===429?'Лимит проверки ANEX временно исчерпан.':'ANEX не смог проверить выбранное предложение.');
      const currentOffer=normalizeAnexConcrete(payload.data,o);if(!currentOffer)throw new Error('ANEX вернул ответ для другого или устаревшего предложения.');
      const identity=anexConcreteKey(o);if(!identity)throw new Error('Условия поиска изменились. Выберите тур заново.');
      anexCurrentReceipts.add(identity.key);
      return currentOffer;
    }finally{clearTimeout(timeout);if(activeVerification===controller)activeVerification=null;}
  }
  function normalizeAnexAdditional(value,o){
    const identity=anexConcreteKey(o),evidence=value&&value.additional_prices;
    if(!identity||!value||value.provider!=='anex'||value.generation!==identity.epoch||value.search_ref!==identity.searchRef
      ||value.offer_ref!==identity.offerRef||value.status!=='additional_prices'||value.selection_state!=='disabled'
      ||!evidence||evidence.application_state!=='applied'||evidence.arithmetic_applied!==true||evidence.final_price_verified!==false
      ||evidence.included_in_search_price!==false||evidence.converted_currency!=='RUB'
      ||evidence.per_person_or_package!=='per_person_by_party_type')return null;
    const search=anexMoneyFact(evidence.search_price),surcharge=anexMoneyFact(evidence.party_surcharge),total=anexMoneyFact(evidence.search_plus_additional);
    if(!search||!surcharge||!total||search.units+surcharge.units!==total.units
      ||evidence.search_price.source!=='direct_anex_search'
      ||evidence.party_surcharge.source!=='anex_b2b_additional_prices_daily'
      ||evidence.search_plus_additional.formula!=='search_price_plus_program_date_party_additional')return null;
    return Object.freeze({state:'additional_prices',finalPriceVerified:false,arithmeticApplied:true,
      searchPrice:Object.freeze({amount:search.amount,currency:'RUB'}),
      partySurcharge:Object.freeze({amount:surcharge.amount,currency:'RUB'}),
      calculatedTotal:Object.freeze({amount:total.amount,currency:'RUB'})});
  }
  async function verifyAnexAdditional(o){
    const identity=anexConcreteKey(o);
    if(!identity||!anexCurrentReceipts.has(identity.key))throw new Error('Сначала подтвердите актуальность конкретного предложения ANEX.');
    if(anexAdditionalAttempts.has(identity.key))throw new Error('Обязательные доплаты уже запрашивались для этого предложения. Повторите поиск для новой проверки.');
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url)throw new Error('ANEX сейчас недоступен.');
    anexAdditionalAttempts.add(identity.key);
    activeVerification?.abort();const controller=new AbortController();activeVerification=controller;
    const timeout=setTimeout(()=>controller.abort(),30000);
    try{
      const body={action:'additional_prices',generation:identity.epoch,search_ref:identity.searchRef,offer_ref:identity.offerRef,local_hotel_id:identity.localId};
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
        headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(body)});
      const payload=await response.json().catch(()=>null);
      if(controller.signal.aborted||identity.epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
      if(!response.ok||payload?.ok!==true||!payload.data){
        throw new Error(response.status===429?'Лимит проверки доплат ANEX временно исчерпан.':'ANEX не смог уточнить обязательные доплаты.');
      }
      const result=normalizeAnexAdditional(payload.data,o);if(!result)throw new Error('ANEX не вернул применимый расчёт обязательных доплат.');
      return result;
    }finally{clearTimeout(timeout);if(activeVerification===controller)activeVerification=null;}
  }
  function andromedaContext(value,depth=0){
    if(!value||depth>1||value.provider!=='andromeda'||!(/^offer_[a-f0-9]{64}$/).test(String(value.offer_ref||''))
      ||!(/^[a-f0-9]{64}$/).test(String(value.search_ref||''))||!Number.isInteger(value.generation)||value.generation<1
      ||!Number.isInteger(value.page)||value.page<1||value.page>1000)return null;
    const result={provider:'andromeda',search_ref:String(value.search_ref),generation:value.generation,page:value.page,offer_ref:String(value.offer_ref)};
    if(value.hotel_scope!==undefined){
      const scope=value.hotel_scope;
      if(!scope||!Number.isSafeInteger(scope.local_id)||scope.local_id<1||!scope.seed||scope.seed.hotel_scope)return null;
      const seed=andromedaContext(scope.seed,depth+1);if(!seed)return null;
      result.hotel_scope={local_id:scope.local_id,seed};
    }
    return result;
  }
  function andromedaQuoteKey(context){return context?JSON.stringify(context):'';}
  function andromedaQuoteRequest(o,flightSelection=null){
    const rawOffer=o&&o.raw,ctx=andromedaContext(rawOffer?.offer_context),localId=Number(rawOffer?.andromedaLocalHotelId);
    const quoteParams=rawOffer?.rehydrationParams&&typeof rawOffer.rehydrationParams==='object'&&!Array.isArray(rawOffer.rehydrationParams)
      ?rawOffer.rehydrationParams:searchParams;
    if(!o||o.cached||o.provider!=='andromeda'||rawOffer?.selectionEnabled!==false||rawOffer?.quoteRequired!==true
      ||!ctx||ctx.generation!==generation||ctx.offer_ref!==String(rawOffer?.offerRef||'')
      ||!Number.isSafeInteger(localId)||localId<1||!quoteParams)return null;
    const {hotel_scope,...identity}=ctx,body={action:flightSelection?'quote_select_flights':'quote',generation:ctx.generation,page:ctx.page,
      params:structuredClone(quoteParams),offer_context:identity};
    if(hotel_scope){if(hotel_scope.local_id!==localId)return null;body.hotel_scope=structuredClone(hotel_scope);}
    const listing=String(rawOffer?.listing_price_ref||'');if((/^listing_[a-f0-9]{64}$/).test(listing))body.listing_price_ref=listing;
    if(flightSelection){
      const keys=Object.keys(flightSelection).sort();
      if(keys.join(',')!=='outbound_ref,provider,return_ref'||flightSelection.provider!=='andromeda'
        ||!(/^flight_[a-f0-9]{32}$/).test(String(flightSelection.outbound_ref||''))
        ||!(/^flight_[a-f0-9]{32}$/).test(String(flightSelection.return_ref||'')))return null;
      const retained=andromedaQuoteChoices.get(andromedaQuoteKey(ctx));
      const outbound=retained?.flights?.find(row=>row.direction==='0'&&row.flightRef===flightSelection.outbound_ref);
      const inbound=retained?.flights?.find(row=>row.direction==='1'&&row.flightRef===flightSelection.return_ref);
      if(!outbound||!inbound)return null;
      body.flight_selection=structuredClone(flightSelection);
    }
    return {body,ctx,localId,key:andromedaQuoteKey(ctx)};
  }
  function andromedaPoint(value){
    if(!value||typeof value!=='object')return null;
    const clean={};
    for(const key of ['state','town','port']){
      const item=value[key];if(item!==null&&item!==undefined&&typeof item!=='string')return null;
      clean[key]=typeof item==='string'?item.slice(0,100):null;
    }
    return clean;
  }
  function andromedaQuoteFlight(value,pending,seen){
    if(!value||!['0','1'].includes(String(value.direction||'')))return null;
    const row={direction:String(value.direction),name:typeof value.name==='string'?value.name.slice(0,160):null,
      datebeg:typeof value.datebeg==='string'?value.datebeg.slice(0,40):null,dateend:typeof value.dateend==='string'?value.dateend.slice(0,40):null,
      class:typeof value.class==='string'?value.class.slice(0,80):null,departure:andromedaPoint(value.departure),arrival:andromedaPoint(value.arrival)};
    if(value.departure!==null&&value.departure!==undefined&&!row.departure||value.arrival!==null&&value.arrival!==undefined&&!row.arrival)return null;
    if(pending){
      const ref=String(value.flight_ref||'');if(!(/^flight_[a-f0-9]{32}$/).test(ref)||seen.has(ref))return null;
      seen.add(ref);row.flightRef=ref;
    }
    return Object.freeze(row);
  }
  function normalizeAndromedaQuote(value,localId){
    if(!value||value.schema_version!==1||value.provider!=='andromeda'||Number(value.local_id)!==localId
      ||value.selection_enabled!==true||value.booking_enabled!==false||!Array.isArray(value.flights)||value.flights.length>100)return null;
    const verified=value.state==='quote_verified'&&value.quote_state==='verified'&&value.final_price_verified===true
      &&value.flight_selection_required===false;
    const pending=value.state==='flight_selection_required'&&value.quote_state==='unverified'&&value.final_price_verified===false
      &&value.flight_selection_required===true&&value.final_price===null;
    if(!verified&&!pending)return null;
    let finalPrice=null;
    if(verified){
      const amount=String(value.final_price?.amount??''),currency=String(value.final_price?.currency??'');
      if(currency!=='RUB'||!(/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/).test(amount)||Number(amount)<=0)return null;
      finalPrice=Object.freeze({amount,currency});
    }
    const seen=new Set(),flights=[];
    for(const raw of value.flights){const flight=andromedaQuoteFlight(raw,pending,seen);if(!flight)return null;flights.push(flight);}
    if(pending&&(!flights.some(row=>row.direction==='0')||!flights.some(row=>row.direction==='1')))return null;
    return Object.freeze({state:pending?'flight_selection_required':'quote_verified',finalPrice,finalPriceVerified:verified,
      flightSelectionRequired:pending,flights:Object.freeze(flights)});
  }
  async function verifyAndromeda(o,flightSelection=null){
    const prepared=andromedaQuoteRequest(o,flightSelection);
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaQuoteApi,'/_preview/search3-anex-candidate/api-andromeda-quote-preview.php');
    if(!prepared||!url)throw new Error('Предложение Andromeda устарело. Повторите поиск.');
    activeVerification?.abort();const controller=new AbortController();activeVerification=controller;
    const epoch=generation,timeout=setTimeout(()=>controller.abort(),45000);
    try{
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
        headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(prepared.body)});
      const payload=await response.json().catch(()=>null);
      if(controller.signal.aborted||epoch!==generation||!andromedaQuoteRequest(o,flightSelection))throw new Error('Условия поиска изменились. Выберите тур заново.');
      if(!response.ok||payload?.ok!==true||!payload.data){
        if(flightSelection)andromedaQuoteChoices.delete(prepared.key);
        throw new Error(response.status===429?'Лимит проверки Andromeda временно исчерпан.':'Andromeda не смог подтвердить выбранное предложение.');
      }
      const quote=normalizeAndromedaQuote(payload.data,prepared.localId);if(!quote){if(flightSelection)andromedaQuoteChoices.delete(prepared.key);throw new Error('Andromeda вернул некорректное подтверждение.');}
      if(quote.flightSelectionRequired)andromedaQuoteChoices.set(prepared.key,quote);else andromedaQuoteChoices.delete(prepared.key);
      return quote;
    }finally{clearTimeout(timeout);if(activeVerification===controller)activeVerification=null;}
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
  root.AnyTourPrototypeData=Object.freeze({init,countries,regions,search,resumeCached,continueSearch,stop,calendar,calendarPrices,observedCalendar,observationScopeSupported,rehydrateCached,expandAnexGroup,verifyAnexConcrete,verifyAnexAdditional,verifyAndromeda,quote,flights,leadSession,params,supplierScope,supplierScopeCovered,sameScope,project,amount,date,text,meal,mealPlan,operator,variantPrice,fuel,savedHotels,lookupHotels,restoreHotel,catalog,get searchId(){return searchId;},get currentSupplierScope(){return currentSupplierScope;}});
})(window);