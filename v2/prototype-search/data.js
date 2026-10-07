(function (root) {
  'use strict';
  // Presentation adapter only. Existing API actions, source DTOs and DB guards stay authoritative.
  const rt = root.V2Runtime;
  const local = '/_preview/search3-local-candidate/';
  const catalog = { departures: [], countries: [], meals: [], mealPlans: [], mealPlanRevision: null, mealPlanAvailable: false, regions: {} };
  const regionRequests=new Map();
  const quoteReceipts = new WeakMap();
  const andromedaQuoteChoices=new Map(),andromedaQuoteAttempts=new Map();
  const anexCurrentReceipts=new Set(),anexAdditionalAttempts=new Set(),anexFlightAttempts=new Set(),anexFlightReceipts=new Map();
  const anexPackageReceipts=new Map();
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
    RO:'Без питания',AO:'Без питания','ROOM ONLY':'Без питания','ACCOMMODATION ONLY':'Без питания','NO MEAL':'Без питания',
    'БЕЗ ПИТАНИЯ':'Без питания','БЕЗ ПИТАНИЯ (ROOM ONLY)':'Без питания',
    BB:'Завтраки',BREAKFAST:'Завтраки','BED & BREAKFAST':'Завтраки','BED AND BREAKFAST':'Завтраки','ЗАВТРАК':'Завтраки','ЗАВТРАКИ':'Завтраки','ТОЛЬКО ЗАВТРАК':'Завтраки',
    HB:'Полупансион','HALF BOARD':'Полупансион','ПОЛУПАНСИОН':'Полупансион',
    FB:'Полный пансион','FULL BOARD':'Полный пансион','ПОЛНЫЙ ПАНСИОН':'Полный пансион',
    AI:'Всё включено',ALL:'Всё включено','ALL INCLUSIVE':'Всё включено','ВСЕ ВКЛЮЧЕНО':'Всё включено','ВСЁ ВКЛЮЧЕНО':'Всё включено',
    UAI:'Ультра всё включено','ULTRA ALL':'Ультра всё включено','ULTRA ALL INCLUSIVE':'Ультра всё включено',
    'УЛЬТРА ВСЕ ВКЛЮЧЕНО':'Ультра всё включено','УЛЬТРА ВСЁ ВКЛЮЧЕНО':'Ультра всё включено',
    'УЛЬТРА ВСЕ ВКЛ':'Ультра всё включено','УЛЬТРА ВСЁ ВКЛ':'Ультра всё включено',
    'AI-WITHOUT ALCOHOL':'Всё включено без алкоголя','AI WITHOUT ALCOHOL':'Всё включено без алкоголя',
    'ВСЕ ВКЛЮЧЕНО БЕЗ АЛКОГОЛЯ':'Всё включено без алкоголя','ВСЁ ВКЛЮЧЕНО БЕЗ АЛКОГОЛЯ':'Всё включено без алкоголя'
  });
  function mealIndexes(){
    const mealById=new Map(),mealByLabel=new Map(),nativeIdsByLabel=new Map();let mealFindInvalidAt=Infinity;
    for(let index=0,length=catalog.meals.length;index<length;index++){
      const row=catalog.meals[index];if(row==null)mealFindInvalidAt=Math.min(mealFindInvalidAt,index);
      const id=String(row?.id??''),fields=[text(row?.fullName),text(row?.russianName),text(row)],label=fields[2].trim().toLocaleLowerCase('ru-RU'),entry={index,fields};
      if(!mealById.has(id))mealById.set(id,entry);
      if(!mealByLabel.has(label))mealByLabel.set(label,entry);
      if(/^[1-9][0-9]*$/.test(id)){
        const labels=[fields[2],fields[0],fields[1]].map(value=>value.trim()).filter(Boolean);
        for(const value of labels){
          let ids=nativeIdsByLabel.get(value);if(!ids)nativeIdsByLabel.set(value,ids=new Set());ids.add(id);
        }
      }
    }
    const planById=new Map(),plansByNative=new Map(),plansByName=new Map();let planFindInvalidAt=Infinity,planFilterInvalid=false;
    for(let index=0,length=catalog.mealPlans.length;index<length;index++){
      const present=index in catalog.mealPlans,plan=catalog.mealPlans[index];
      if(plan==null)planFindInvalidAt=Math.min(planFindInvalidAt,index);
      if(!present)continue;
      if(!plan){planFilterInvalid=true;continue;}
      const planId=plan.id;if(!planById.has(planId))planById.set(planId,{index,plan});
      if(!plan.nativeIds||typeof plan.nativeIds.includes!=='function'){planFilterInvalid=true;continue;}
      if(plan?.nativeIds?.length){
        let named=plansByName.get(plan.nameRu);if(!named)plansByName.set(plan.nameRu,named=[]);named.push(plan);
        for(const native of new Set(plan.nativeIds)){
          let plans=plansByNative.get(native);if(!plans)plansByNative.set(native,plans=[]);plans.push(plan);
        }
      }
    }
    return {mealById,mealByLabel,nativeIdsByLabel,mealFindInvalidAt,planById,plansByNative,plansByName,planFindInvalidAt,planFilterInvalid};
  }
  function indexedMeal(value,indexes){
    const label=text(value).trim(),byId=value?.id?indexes.mealById.get(String(value.id)):null,byLabel=indexes.mealByLabel.get(label.toLocaleLowerCase('ru-RU'));
    const record=!byId?byLabel:!byLabel||byId.index<=byLabel.index?byId:byLabel;
    if(value?.id&&indexes.mealFindInvalidAt<(record?.index??Infinity))throw new TypeError('Invalid supplier meal catalogue row');
    const candidates=[label,text(value?.fullName),text(value?.russianName),...(record?.fields||[])]
      .map(value=>value.trim()).filter(Boolean);
    for(const candidate of candidates){
      const normalized=candidate.toUpperCase().replace(/\s+/g,' ');
      if(mealAliases[normalized])return mealAliases[normalized];
      const coded=normalized.match(/^(RO|BB|HB|FB|AI|UAI|ALL)\s*(?:[-—:]\s*|\s+).+$/);
      if(coded&&mealAliases[coded[1]])return mealAliases[coded[1]];
    }
    return candidates.find(candidate=>!(/^[A-Z]{1,7}\+?$/).test(candidate))||candidates[0]||'';
  }
  function meal(value){return indexedMeal(value,mealIndexes());}
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
  function supplierMealNativeId(value,indexes=mealIndexes()){
    const direct=value&&typeof value==='object'?String(value.id??''):'';
    if(/^[1-9][0-9]*$/.test(direct))return direct;
    const candidates=[text(value),text(value?.fullName),text(value?.russianName)].map(v=>v.trim()).filter(Boolean);
    if(!candidates.length)return '';
    const matches=new Set();
    for(const candidate of candidates)for(const id of indexes.nativeIdsByLabel.get(candidate)||[])matches.add(id);
    return matches.size===1?matches.values().next().value:'';
  }
  function mealPlan(t,provider=String(t?.provider||'tourvisor').toLowerCase(),indexes=mealIndexes()){
    const explicit=t&&t.searchMealPlan;
    if(explicit&&Number.isSafeInteger(explicit.id)&&explicit.id>0&&typeof explicit.code==='string'&&/^[a-z0-9][a-z0-9-]{0,63}$/.test(explicit.code)
      &&typeof explicit.nameRu==='string'&&explicit.nameRu.trim()&&explicit.nameRu.length<=255){
      const known=indexes.planById.get(explicit.id);
      if(indexes.planFindInvalidAt<(known?.index??Infinity))throw new TypeError('Invalid canonical meal plan row');
      if(!known||known.plan.code===explicit.code&&known.plan.nameRu===explicit.nameRu.trim())return Object.freeze({id:explicit.id,code:explicit.code,nameRu:explicit.nameRu.trim()});
      return null;
    }
    if(provider!=='tourvisor'||catalog.mealPlanAvailable!==true)return null;
    const native=supplierMealNativeId(t?.meal,indexes);if(!native)return null;
    if(indexes.planFilterInvalid)throw new TypeError('Invalid canonical meal plan row');
    const matches=indexes.plansByNative.get(native)||[];
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
    const regionIds=[],subregionIds=[],selected=filters.resorts||[],byName=new Map();
    if(selected.length)(catalog.regions[String(s.country)]||[]).forEach(row=>{
      const name=row.name;byName.set(name,byName.has(name)?null:row);
    });
    for(const name of selected){
      const row=name===name&&byName.get(name);
      if(!row)throw new Error('Выберите курорт из канонического справочника.');
      const target=row.kind==='region'?regionIds:row.kind==='subregion'?subregionIds:null;
      if(!target)throw new Error('Некорректный тип направления.');
      target.push(...tourvisorIds(row));
    }
    const unique=ids=>[...new Set(ids)].sort((a,b)=>Number(a)-Number(b));
    return {regionIds:unique(regionIds),subregionIds:unique(subregionIds)};
  }
  function supplierScope(filters = {}, hotelIds = []) {
    const indexes=mealIndexes();
    const selectedMeal=filters.meals?.length===1?String(filters.meals[0]||'').trim():'';
    if(selectedMeal&&indexes.planFilterInvalid)throw new TypeError('Invalid canonical meal plan row');
    const selectedPlans=selectedMeal?indexes.plansByName.get(selectedMeal)||[]:[];
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
    const services=h.hotelInformation?.services||h.services||{},groups=services?.tags,items=new Map(),labels=new Set();
    if(Array.isArray(groups))for(const group of groups){
      // Saved structured hotel facts only. Promotional/availability badges
      // (group 7) are not amenities and cannot promise a bookable tour.
      if(!group||![1,2,3,5,8].includes(group.id)||!text(group.name).trim()||!Array.isArray(group.items))continue;
      for(const item of group.items){
        if(!item||!Number.isSafeInteger(item.id)||item.id<1||!text(item.name).trim())continue;
        const key=group.id+':'+item.id,label=text(item.name).trim();
        items.set(key,{key,label,group:text(group.name).trim(),groupId:group.id,filterable:true});labels.add(label);
      }
    }
    // Older canonical profiles can retain descriptive service maps keyed by a
    // supplier service id. Keep their exact text visible, but never invent a
    // Tourvisor group identity or turn it into a search/filter contract.
    if(services&&typeof services==='object'&&!Array.isArray(services))for(const [serviceId,value] of Object.entries(services)){
      if(serviceId==='tags'||!(/^[1-9][0-9]*$/).test(serviceId)||typeof value!=='string')continue;
      const label=value.replace(/\s+/g,' ').trim();if(!label||labels.has(label))continue;
      const key='local-service:'+serviceId;
      items.set(key,{key,label,group:'Услуги отеля',groupId:0,filterable:false});labels.add(label);
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
  function localStayName(t,h,kind){
    const stay=t?.localStay,part=stay&&stay[kind];
    if(!stay||stay.source!=='anytour-hotel-stay-v2'||!part||part.kind!==kind||part.hotelId!==h.id
      ||!Number.isSafeInteger(part.id)||part.id<1||!Number.isSafeInteger(part.revision)||part.revision<1)return '';
    const name=typeof part.nameRu==='string'&&part.nameRu.length<=255&&!/[\u0000-\u001f\u007f]/.test(part.nameRu)?part.nameRu.trim():'';
    const key=typeof part.localKey==='string'&&part.localKey.length<=128&&!/[\u0000-\u001f\u007f]/.test(part.localKey)?part.localKey.trim():'';
    return name&&key?name:'';
  }
  function offer(t, h, s, index, indexes=mealIndexes()) {
    const price=amount(t.price), day=date(t.date), nights=Number(t.nights);
    if(!price || !day || !Number.isInteger(nights) || nights<1) return null;
    const provider=String(t.provider||'tourvisor').toLowerCase(),plan=mealPlan(t,provider,indexes);
    const rawMeal=[text(t.meal),text(t.meal?.fullName),text(t.meal?.russianName)].map(value=>value.trim()).find(Boolean)||'';
    const displayMeal=indexedMeal(t.meal,indexes)||rawMeal,localMeal=localStayName(t,h,'meal'),localRoom=localStayName(t,h,'room');
    return {key:encodeURIComponent(`${provider}:${String(t.id)}`),hotelId:h.id,day,nights,variant:index,total:price,returnDay:plus(day,nights),room:localRoom||text(t.roomType)||'Номер уточняется',placement:text(t.placement),adults:s.adults,ages:[...s.ages],origin:s.origin,
      mealPlanId:plan?.id??null,mealPlanCode:plan?.code||'',mealFacet:plan?.nameRu||'',meal:localMeal||plan?.nameRu||displayMeal||'Питание уточняется',mealRaw:rawMeal,
      operator:operator(t.operator)||'Туроператор уточняется',flight:t.isCharter===true?'charter':t.isCharter===false?'regular':'unknown',cached:t.cachedListing===true,provider,raw:t,search:structuredClone(s),fuel:t.fuelCharge??null,flightChoiceId:null};
  }
  function project(list,s) {
    const result=[],indexes=mealIndexes();
    list.forEach(rawHotel=>{
      const h=hotel(rawHotel,s),offers=[];
      (rawHotel.tours||[]).forEach((t,i)=>{const item=offer(t,h,s,i,indexes);if(item)offers.push(item);});
      h.offers=offers;if(offers.length)result.push(h);
    });
    return result;
  }
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
    activeVerification?.abort();activeVerification=null;andromedaQuoteChoices.clear();andromedaQuoteAttempts.clear();anexCurrentReceipts.clear();anexAdditionalAttempts.clear();anexFlightAttempts.clear();anexFlightReceipts.clear();anexPackageReceipts.clear();
    return generation;
  }
  async function searchError(run,error){
    if(!current(run))return;
    if(run.continued&&!(await settleContinuedSources(run)))return;
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
    const continuationFailed=[...windows.values()].some(window=>window.continuationFailed===true);
    const incompletePages=[...windows.values()].some(window=>window.continuation&&window.continuation.state!=='exhausted');
    const status=contiguous&&order.length===total&&!continuationFailed&&!incompletePages?'complete':'partial',first=windows.get(order[0]),last=windows.get(order[order.length-1]);
    run.sourceCounts.anex={status,hotels:visibleHotels.size,offers:seenOffers.size,receivedHotels:receivedHotels.size,receivedOffers,
      mappedHotels:receivedHotels.size,mappedOffers,visibleHotels:visibleHotels.size,visibleOffers:seenOffers.size,
      scopeFilteredOffers,deduplicatedOffers,windowsLoaded:order.length,windowsTotal:total,dateFrom:first.dateFrom,dateTo:last.dateTo};
    if(continuationFailed)run.sourceCounts.anex.continuationFailed=true;
    if(!contiguous||order.length!==total)run.sourceCounts.anex.destinationBranchFailed=true;
    return run.sourceCounts.anex;
  }
  function anexPageContinuation(data,page){
    const value=data?.continuation;
    if(!value||typeof value!=='object'||Array.isArray(value)
      ||Object.keys(value).sort().join(',')!=='next_page,pages_read,state'
      ||value.pages_read!==page||!Number.isInteger(page)||page<1||page>12
      ||data.pages_read!==1||data.first_page_only!==(page===1)||page>1&&data.page!==page
      ||!['available','exhausted','blocked','limit'].includes(value.state))return null;
    if(value.state==='available'?(page>=12||value.next_page!==page+1):value.next_page!==null)return null;
    if(value.state==='limit'&&page!==12)return null;
    return Object.freeze({state:value.state,pages_read:page,next_page:value.next_page});
  }
  function anexContinuationAvailable(run){
    return current(run)&&run.anexWindows instanceof Map&&[...run.anexWindows.values()].some(window=>
      window.continuationFailed!==true&&!window.requestedPage&&window.continuation?.state==='available'
      &&window.continuation.pages_read===window.pagesRead&&window.continuation.next_page===window.pagesRead+1
      &&window.pagesRead<12);
  }
  async function applyDirectAnex(run,data,p,index,total,page=1){
    const emptyExcluded=page===1&&Array.isArray(data?.hotels)&&data.hotels.length===0&&Number(data.pages_read)===0&&data.search_ref===undefined;
    if(!data||data.provider!=='anex'||data.generation!==run.generation||!Array.isArray(data.hotels)||data.hotels.length>300
      ||!data.date_range||data.date_range.from!==p.dateFrom||data.date_range.to!==p.dateTo
      ||!Number.isInteger(index)||index<0||!Number.isInteger(total)||total<1||index>=total
      ||!Number.isInteger(page)||page<1||page>12
      ||(!emptyExcluded&&(typeof data.search_ref!=='string'||!(/^[a-f0-9]{32}$/).test(data.search_ref))))throw new Error('Invalid ANEX search response');
    const windows=run.anexWindows||(run.anexWindows=new Map()),previous=windows.get(index);
    const continuation=emptyExcluded?null:anexPageContinuation(data,page);
    if(page===1?!!previous:(!previous||previous.pagesRead!==page-1||previous.searchRef!==data.search_ref
      ||previous.continuation?.next_page!==page||previous.requestedPage!==page||!continuation
      ||data.page!==page||data.pages_read!==1||data.first_page_only!==false||run.anexWindowsTotal!==total))throw new Error('Invalid ANEX page sequence');
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
    // Existing first-page cap is retained; each explicit page has the same cap.
    const priorOffers=[...windows.values()].reduce((sum,window)=>sum+window.receivedOffers,0);
    const initialOffers=[...windows.values()].reduce((sum,window)=>sum+(window.initialReceivedOffers??window.receivedOffers),0);
    if((page===1&&initialOffers+receivedOffers>1200)||priorOffers+receivedOffers>1200*12
      ||(previous?.receivedOffers||0)+receivedOffers>300*12)throw new Error('Invalid ANEX result size');
    // No asynchronous digest from an old generation may mutate the current union.
    if(!current(run))return null;
    windows.set(index,{prepared:previous?[...previous.prepared,...prepared]:prepared,
      hotelIds:[...new Set([...(previous?.hotelIds||[]),...seenHotels])],
      receivedOffers:(previous?.receivedOffers||0)+receivedOffers,mappedOffers:(previous?.mappedOffers||0)+receivedOffers,
      scopeFilteredOffers:(previous?.scopeFilteredOffers||0)+Math.max(0,receivedOffers-prepared.length),
      initialReceivedOffers:previous?previous.initialReceivedOffers:receivedOffers,
      dateFrom:data.date_range.from,dateTo:data.date_range.to,params:structuredClone(p),searchRef:data.search_ref,
      pagesRead:page,continuation,requestedPage:null,
      // Legacy endpoints without metadata remain usable, but never gain a guessed cursor.
      continuationFailed:data.continuation!==undefined&&!continuation});
    run.anexWindowsTotal=total;
    return rebuildDirectAnex(run);
  }
  async function continueAnexPages(run,url){
    if(!current(run)||!(run.anexWindows instanceof Map))return;
    notify({type:'provider',provider:'anex',status:'loading',continued:true});
    const order=[...run.anexWindows.keys()].sort((a,b)=>a-b);
    for(const index of order){
      if(!current(run))return;
      const window=run.anexWindows.get(index),page=window.continuation?.next_page;
      if(window.continuationFailed===true||window.requestedPage||window.continuation?.state!=='available'
        ||!Number.isInteger(page)||page!==window.pagesRead+1||page>12)continue;
      // Browser-local reservation only. Durable no-replay remains the server's responsibility.
      window.requestedPage=page;
      try{
        const response=await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',signal:run.controller.signal,
          headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
          body:JSON.stringify({action:'continue',generation:run.generation,search_ref:window.searchRef,page})});
        const payload=await response.json().catch(()=>null);
        if(!current(run))return;
        if(!response.ok||payload?.ok!==true)throw new Error('ANEX continuation unavailable');
        await applyDirectAnex(run,payload.data,window.params,index,run.anexWindowsTotal,page);
      }catch(error){
        if(!current(run))return;
        window.continuationFailed=true;
      }
    }
    if(!current(run))return;
    const result=rebuildDirectAnex(run);
    notify({type:'provider',provider:'anex',...result,continued:true});
    clearCalendarWindows();
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
      finalPriceVerified:false,quoteRequired:true,andromedaLocalHotelId:hotel.local_id,offer_context:structuredClone(context),
      andromedaSearchParams:structuredClone(p)};
    if(tour.listing_price_ref!==undefined)normalized.listing_price_ref=String(tour.listing_price_ref);
    if(tour.base_search_price&&typeof tour.base_search_price==='object')normalized.base_search_price=structuredClone(tour.base_search_price);
    if(tour.search_surcharge&&typeof tour.search_surcharge==='object')normalized.search_surcharge=structuredClone(tour.search_surcharge);
    return normalized;
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
  function andromedaSearchFailure(phase,httpStatus,code){
    return Object.assign(new Error('Andromeda search unavailable'),{searchFailure:{phase,httpStatus,code}});
  }
  function reportAndromedaSearchFailure(error,page){
    const fact=error?.searchFailure;
    const phase=['request','response'].includes(fact?.phase)?fact.phase:'projection';
    const httpStatus=Number.isInteger(fact?.httpStatus)&&fact.httpStatus>=100&&fact.httpStatus<=599?fact.httpStatus:0;
    const code=['transport_error','invalid_response','not_found','method_not_allowed','forbidden','invalid_request',
      'supplier_unavailable','monthly_quota_exhausted','search_not_supported'].includes(fact?.code)?fact.code:'invalid_result';
    root.console?.warn?.('[AnyTour search] '+JSON.stringify({provider:'andromeda',action:page===1?'initial':'continue',phase,httpStatus,code}));
  }
  function reportAndromedaSearchPage(run){
    const source=run.sourceCounts.andromeda,detail={provider:'andromeda',action:'page',status:['complete','partial'].includes(source?.status)?source.status:'unknown'};
    for(const key of ['pagesLoaded','receivedOffers','mappedOffers','projectedOffers','visibleOffers']){
      const value=source?.[key];if(Number.isSafeInteger(value)&&value>=0&&value<=10000000)detail[key]=value;
    }
    root.console?.info?.('[AnyTour search] '+JSON.stringify(detail));
  }
  async function requestDirectAndromeda(run,p,url,page){
    let response;
    try{response=await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',signal:run.controller.signal,
      headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
      body:JSON.stringify({generation:run.generation,page,params:p})});
    }catch(error){if(error?.name==='AbortError')throw error;throw andromedaSearchFailure('request',0,'transport_error');}
    const payload=await response.json().catch(()=>null),data=payload&&payload.data;
    if(!current(run))return null;
    if(!response.ok||payload?.ok!==true)throw andromedaSearchFailure('response',response.status,
      ['not_found','method_not_allowed','forbidden','invalid_request','supplier_unavailable','monthly_quota_exhausted','search_not_supported'].includes(payload?.error)?payload.error:'invalid_response');
    if(!data||typeof data!=='object'||Array.isArray(data))throw andromedaSearchFailure('response',response.status,'invalid_response');
    return data;
  }
  function andromedaContinuationAvailable(run){
    const branches=run.andromedaBranches;
    if(!(branches instanceof Map)||!branches.size)return false;
    for(const branch of branches.values()){
      if(!branch||branch.continuationFailed===true||!(branch.pages instanceof Map)||!branch.pages.size
        ||!Number.isInteger(branch.pagesTotal)||branch.pagesTotal<1)continue;
      const order=[...branch.pages.keys()].sort((a,b)=>a-b);
      if(order.every((page,index)=>page===index+1)&&order[order.length-1]<branch.pagesTotal)return true;
    }
    return false;
  }
  function andromedaCoverageFailures(run){
    const branches=run.andromedaBranches;
    if(!(branches instanceof Map)||!branches.size)return {destinationBranchFailed:true,continuationFailed:false,partialPages:false};
    const order=[...branches.keys()].sort((a,b)=>a-b),expected=run.andromedaBranchesTotal;
    const destinationBranchFailed=!Number.isInteger(expected)||expected<1||order.length!==expected||!order.every((value,index)=>value===index);
    const continuationFailed=[...branches.values()].some(branch=>branch.continuationFailed===true);
    const partialPages=[...branches.values()].some(branch=>!(branch.pages instanceof Map)||!branch.pages.size||[...branch.pages.values()].some(page=>page.status!=='complete'));
    return {destinationBranchFailed,continuationFailed,partialPages};
  }
  function andromedaProviderStatus(run,failed=false){
    const coverage=andromedaCoverageFailures(run);
    if(failed||coverage.destinationBranchFailed||coverage.continuationFailed||coverage.partialPages)return 'partial';
    return andromedaContinuationAvailable(run)?'ready':'complete';
  }
  async function continueDirectAndromeda(run,url){
    const branches=run.andromedaBranches;
    if(!(branches instanceof Map)||!branches.size||!current(run))return {loaded:0,failed:0,canContinue:false};
    const branchOrder=[...branches.keys()].sort((a,b)=>a-b);
    let loaded=0,failed=0;
    notify({type:'provider',provider:'andromeda',status:'loading',continued:true});
    for(const branchIndex of branchOrder){
      if(!current(run))return {loaded,failed,canContinue:false};
      const branch=branches.get(branchIndex);
      if(!branch||branch.continuationFailed===true||!(branch.pages instanceof Map)||!branch.pages.size
        ||!Number.isInteger(branch.pagesTotal)||branch.pagesTotal<1)continue;
      const order=[...branch.pages.keys()].sort((a,b)=>a-b);
      if(!order.every((page,index)=>page===index+1))continue;
      const page=order[order.length-1]+1;
      if(page>branch.pagesTotal)continue;
      try{
        const data=await requestDirectAndromeda(run,branch.params,url,page);if(!data||!current(run))return {loaded,failed,canContinue:false};
        await applyDirectAndromeda(run,data,branch.params,branchIndex,run.andromedaBranchesTotal||branchOrder.length);if(!current(run))return {loaded,failed,canContinue:false};
        reportAndromedaSearchPage(run);
        loaded++;
      }catch(error){
        if(!current(run)||error?.name==='AbortError')return {loaded,failed,canContinue:false};
        reportAndromedaSearchFailure(error,page);
        branch.continuationFailed=true;failed++;
        const previous=run.sourceCounts.andromeda;
        if(previous&&Number.isInteger(previous.pagesLoaded)&&previous.pagesLoaded>0){
          run.sourceCounts.andromeda={...previous,status:'partial',continuationFailed:true};
        }
      }
    }
    if(!current(run))return {loaded,failed,canContinue:false};
    const previous=run.sourceCounts.andromeda||{status:'partial',hotels:0,offers:0};
    run.andromedaCanContinue=andromedaContinuationAvailable(run);
    const coverage=andromedaCoverageFailures(run);
    const failedEver=failed>0||coverage.continuationFailed;
    const providerStatus=andromedaProviderStatus(run,failedEver);
    run.sourceCounts.andromeda={...previous,status:providerStatus==='complete'?'complete':'partial'};
    if(failedEver)run.sourceCounts.andromeda.continuationFailed=true;
    if(coverage.destinationBranchFailed)run.sourceCounts.andromeda.destinationBranchFailed=true;
    notify({type:'provider',provider:'andromeda',...run.sourceCounts.andromeda,status:providerStatus,continued:true});
    clearCalendarWindows();
    return {loaded,failed,canContinue:run.andromedaCanContinue};
  }
  async function enrichAndromeda(run,p){
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaApi,'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php');
    if(!url||!current(run)){run.sourceCounts.andromeda={status:'skipped',hotels:0,offers:0};run.andromedaCanContinue=false;return;}
    notify({type:'provider',provider:'andromeda',status:'loading'});if(!current(run))return;
    const scopes=directFirstWeekScopes(p);let loaded=0,failed=0;
    for(let branchIndex=0;current(run)&&branchIndex<scopes.length;branchIndex++){
      const scope=scopes[branchIndex];
      try{
        const data=await requestDirectAndromeda(run,scope,url.href,1);if(!data||!current(run))return;
        await applyDirectAndromeda(run,data,scope,branchIndex,scopes.length);if(!current(run))return;
        reportAndromedaSearchPage(run);
        loaded++;
        // Provider persistence may update calendar/SEO data, but stored offers never re-enter this live union.
        clearCalendarWindows();
      }catch(error){
        if(!current(run)||error?.name==='AbortError')return;
        reportAndromedaSearchFailure(error,1);
        failed++;
        if(scopes.length===1){
          owner.clearOffers('direct-andromeda');owner.refresh();
          run.sourceCounts.andromeda={status:'error',hotels:0,offers:0};run.andromedaCanContinue=false;
          notify({type:'provider',provider:'andromeda',status:'error'});return;
        }
        const failedBranch=run.andromedaBranches?.get(branchIndex);
        if(failedBranch&&failedBranch.pages instanceof Map&&!failedBranch.pages.size)run.andromedaBranches.delete(branchIndex);
      }
    }
    if(!current(run))return;
    if(!loaded){
      owner.clearOffers('direct-andromeda');owner.refresh();
      run.sourceCounts.andromeda={status:'error',hotels:0,offers:0};run.andromedaCanContinue=false;
      notify({type:'provider',provider:'andromeda',status:'error'});return;
    }
    const previous=run.sourceCounts.andromeda||{hotels:0,offers:0};
    run.andromedaCanContinue=andromedaContinuationAvailable(run);
    if(failed){
      run.sourceCounts.andromeda={...previous,status:'partial',destinationBranchFailed:true};
      notify({type:'provider',provider:'andromeda',...run.sourceCounts.andromeda,status:'partial'});
    }else{
      const providerStatus=andromedaProviderStatus(run,false);
      run.sourceCounts.andromeda={...previous,status:providerStatus==='complete'?'complete':'partial'};
      notify({type:'provider',provider:'andromeda',...run.sourceCounts.andromeda,status:providerStatus});
    }
    clearCalendarWindows();
  }
  async function settleInitialSources(run){
    await Promise.allSettled([run.anex,run.andromeda]);
    return current(run);
  }
  async function settleContinuedSources(run){
    await Promise.allSettled([run.andromedaContinuation,run.anexContinuation]);
    if(!current(run))return false;
    run.andromedaContinuation=null;run.anexContinuation=null;
    return true;
  }
  // Terminal bookkeeping is synchronous: polling retains every await/guard.
  function completeTourvisorRun(run,inventory){
    const resultLimitReached=inventory.hotels>=5000,tvBaseline=run.continueBaselineTourvisor,baseline=run.continueBaseline;
    const tvGrew=!run.continued||!tvBaseline||inventory.hotels>tvBaseline.hotels||inventory.offers>tvBaseline.offers;
    const union=canonicalUnion(),after={hotels:union.hotels,offers:union.offers};
    const unionGrew=!run.continued||!baseline||after.hotels>baseline.hotels||after.offers>baseline.offers;
    // Preserve the established Tourvisor continuation receipt. Provider-union
    // growth is used only when Continue has no Tourvisor continuation to report.
    const growthBefore=tvBaseline||baseline,growthAfter=tvBaseline?inventory:after,growthGrew=tvBaseline?tvGrew:unionGrew;
    run.tvCanContinue=!resultLimitReached&&(!run.continued||tvGrew);
    run.andromedaCanContinue=andromedaContinuationAvailable(run);
    run.pending=false;run.resumeOnly=false;run.canContinue=run.tvCanContinue||run.andromedaCanContinue||anexContinuationAvailable(run);
    notify({type:'complete',canContinue:run.canContinue,continued:run.continued,resultLimitReached,
      continuationGrowth:run.continued&&growthBefore?{before:structuredClone(growthBefore),after:structuredClone(growthAfter),grew:growthGrew}:null,
      sources:structuredClone(run.sourceCounts),union});
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
        if(run.continued&&!(await settleContinuedSources(run)))return;
        completeTourvisorRun(run,inventory);return;
      }
      if(run.deadline&&Date.now()>=run.deadline)throw new Error('Продолжение поиска ещё не завершено. Проверьте результат повторно.');
      timer=setTimeout(()=>pollSearch(run),2500);
    }catch(error){await searchError(run,error);}
  }
  // One synchronous boundary owns validation, invalidation and the new run.
  // Keep callbacks and supplier starts in their respective public entrypoints.
  function prepareSearchRun(s,callback,hotelIds,filters,cached){
    const plan=requestPlan(s,hotelIds,filters),p=plan.request,epoch=stop();
    currentSupplierScope=plan.scope;notify=callback;context=structuredClone(s);searchParams=structuredClone(p);
    raw=[];searchId=0;rt.setSearchId(0);owner?.reset();
    const skipped=()=>({status:'skipped',hotels:0,offers:0});
    const run={generation:epoch,search:structuredClone(s),hotelIds:[...hotelIds],filters:structuredClone(filters),
      controller:new AbortController(),pending:!cached,searchId:0,resumeOnly:true,continued:false,expired:false,canContinue:!cached,
      continueBaseline:null,lastProgress:-10,lastRead:0,deadline:0,
      sourceCounts:cached
        ?{tourvisor:skipped(),anex:skipped(),andromeda:skipped(),database:skipped()}
        :{database:skipped()}};
    activeSearch=run;
    return {run,params:p};
  }
  async function resumeCached(s, callback, hotelIds=[], filters={}) {
    const {run}=prepareSearchRun(s,callback,hotelIds,filters,true);
    callback({type:'loading',cachedResume:true});if(!current(run))return false;
    notify({type:'complete',cachedResume:true,partial:false,canContinue:false,retryRead:false,resultLimitReached:false,
      sources:structuredClone(run.sourceCounts),union:canonicalUnion()});
    return true;
  }
  async function search(s, callback, hotelIds=[], filters={}) {
    const {run,params:p}=prepareSearchRun(s,callback,hotelIds,filters,false);
    callback({type:'loading'});if(!current(run))return;
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
  // Provider-only continuation deliberately reports union growth, not TV growth.
  function completeProviderRun(run){
    const baseline=run.continueBaseline,union=canonicalUnion(),after={hotels:union.hotels,offers:union.offers};
    const grew=!baseline||after.hotels>baseline.hotels||after.offers>baseline.offers;
    run.andromedaCanContinue=andromedaContinuationAvailable(run);run.pending=false;run.resumeOnly=false;
    run.canContinue=run.andromedaCanContinue||anexContinuationAvailable(run);
    notify({type:'complete',canContinue:run.canContinue,continued:true,resultLimitReached:false,
      continuationGrowth:baseline?{before:structuredClone(baseline),after:structuredClone(after),grew}:null,
      sources:structuredClone(run.sourceCounts),union});
  }
  async function continueSearch(){
    const run=activeSearch;
    if(!run||!current(run)||run.pending||!run.searchId||run.expired||!run.canContinue)return false;
    const tvCan=run.tvCanContinue!==false,andromedaCan=andromedaContinuationAvailable(run),anexCan=anexContinuationAvailable(run);
    if(!tvCan&&!andromedaCan&&!anexCan)return false;
    // Lock before the first await: double clicks never spend a second request.
    run.pending=true;run.continued=true;run.lastProgress=-10;run.lastRead=0;run.deadline=Date.now()+75000;
    const retryRead=run.resumeOnly&&tvCan;
    if(!retryRead){
      const union=canonicalUnion();run.continueBaseline={hotels:union.hotels,offers:union.offers};
      run.continueBaselineTourvisor=tourvisorInventory();
    }
    run.resumeOnly=tvCan;
    notify({type:'loading',continued:true,retryRead});if(!current(run))return false;
    try{
      const andromedaUrl=andromedaCan
        ?nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaApi,'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php')
        :null;
      const anexUrl=anexCan
        ?nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php')
        :null;
      if(!retryRead){
        if(andromedaUrl)run.andromedaContinuation=continueDirectAndromeda(run,andromedaUrl.href);
        if(anexUrl)run.anexContinuation=continueAnexPages(run,anexUrl.href);
      }
      if(tvCan){
        if(!retryRead){await rt.api('search_continue',{searchId:run.searchId});if(!current(run))return false;}
        await pollSearch(run);return current(run);
      }
      if(!(await settleContinuedSources(run)))return false;
      if(!current(run))return false;
      completeProviderRun(run);
      return current(run);
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
  function observationMealPlans(filters={}) {
    const labels=filters.meals||[];
    if(!Array.isArray(labels)||labels.length>20)return null;
    const ids=[],indexes=mealIndexes();
    for(const label of labels){
      if(indexes.planFilterInvalid)throw new TypeError('Invalid canonical meal plan row');
      const matches=indexes.plansByName.get(label)||[];
      if(catalog.mealPlanAvailable!==true||matches.length!==1)return null;
      ids.push(matches[0].id);
    }
    return [...new Set(ids)].sort((a,b)=>a-b);
  }
  function observationScopeSupported(s,filters={}) {
    // Party and canonical region OR are exact first-class observation scopes.
    // The observation reader has no exact subregion dimension yet: suppress it
    // instead of presenting a broader parent-region minimum as exact.
    if(filters.hotelId||filters.q||observationBudget(filters)===null
      ||['stars','operators','flight','amenities'].some(key=>filters[key]?.length)
      ||['rating','beach','family','spa'].some(key=>filters[key]))return false;
    try{if(destinationScope(s,filters).subregionIds.length)return false;}catch{return false;}
    return observationMealPlans(filters)!==null;
  }
  function observationBudget(filters={}) {
    const from=filters.min??0,to=filters.max===undefined||filters.max===null||filters.max===''?null:filters.max;
    const valid=value=>typeof value==='number'&&Number.isFinite(value)&&value>=0&&value<=9999999999.99&&Math.round(value*100)/100===value;
    if(!valid(from)||to!==null&&(!valid(to)||from>to))return null;
    return {priceFrom:from>0?from:null,priceTo:to};
  }
  async function observedCalendar(s,from,to,signal,filters={}) {
    if(!observationScopeSupported(s,filters))return [];
    const departure=catalog.departures.find(x=>text(x)===s.origin||String(x.id)===s.origin);
    if(!departure)return [];
    let nativeCountryId;try{nativeCountryId=tourvisorCountryId(s.country);}catch{return [];}
    const childAges=[...s.ages].sort((a,b)=>a-b),childSignature=childAges.join(',');
    const destination=destinationScope(s,filters);if(destination.subregionIds.length)return [];
    const selected=[...new Set(destination.regionIds.map(Number))].sort((a,b)=>a-b),mealPlanIds=observationMealPlans(filters),budget=observationBudget(filters);
    const query={departureId:String(departure.id),countryId:nativeCountryId,dateFrom:from,dateTo:to,nightsFrom:String(s.minNights),nightsTo:String(s.maxNights),adults:String(s.adults),childs:childSignature,regionIds:selected.map(String)};
    const response=await fetch(local+'data/search3-local-results-read-v1.php',{
      method:'POST',credentials:'same-origin',cache:'no-store',signal,
      headers:{Accept:'application/json','Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},
      body:JSON.stringify({action:'price_calendar',departureId:Number(query.departureId),countryId:Number(query.countryId),regionIds:selected,mealPlanIds,...budget,
        dateFrom:from,dateTo:to,nightsFrom:s.minNights,nightsTo:s.maxNights,adults:s.adults,childs:childAges})
    });
    if(!response.ok)throw new Error('Сохранённые цены календаря временно недоступны.');
    const payload=await response.json(),result=payload?.data;
    if(payload?.ok!==true)throw new Error('Сохранённые цены календаря временно недоступны.');
    if(result?.ok!==true||result.source!=='latest-known-exact-segments-from-anytour-first-party-observations'
      ||result.cachedPriceIsFinal!==false||result.currency!=='RUB'||result.adults!==s.adults||result.childrenCount!==childAges.length
      ||!Array.isArray(result.childAges)||result.childAges.length!==childAges.length||result.childAges.some((age,index)=>age!==childAges[index])||result.childAgesSignature!==childSignature
      ||String(result.departureId)!==query.departureId||String(result.countryId)!==query.countryId
      ||(mealPlanIds.length||result.mealPlanIds!==undefined)&&(!Array.isArray(result.mealPlanIds)||JSON.stringify(result.mealPlanIds)!==JSON.stringify(mealPlanIds))
      ||(budget.priceFrom!==null||budget.priceTo!==null||result.priceFrom!==undefined||result.priceTo!==undefined)
        &&(result.priceFrom!==budget.priceFrom||result.priceTo!==budget.priceTo)
      ||!Array.isArray(result.regionIds)||result.regionIds.map(String).join(',')!==query.regionIds.join(',')
      ||String(result.regionId||'')!==(query.regionIds.length===1?query.regionIds[0]:'')||result.dateFrom!==from||result.dateTo!==to
      ||String(result.nightsFrom)!==query.nightsFrom||String(result.nightsTo)!==query.nightsTo||!Array.isArray(result.series))throw new Error('Сохранённые цены не соответствуют параметрам поездки.');
    const points=[],seen=new Set();
    for(const row of result.series){
      if(!row||date(row.date)!==row.date||row.date<from||row.date>to||seen.has(row.date)||typeof row.observed!=='boolean')throw new Error('Некорректные даты сохранённых цен.');
      seen.add(row.date);
      if(row.observed){const price=amount(row.minPrice);if(price===null||budget.priceFrom!==null&&price<budget.priceFrom||budget.priceTo!==null&&price>budget.priceTo)throw new Error('Некорректная сохранённая цена.');points.push({date:row.date,price});}
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
    if(activeVerification?.quoteMutation)throw Object.assign(new Error('Дождитесь текущей проверки тура.'),{code:'quote_operation_pending',retryable:true});
    const rawOffer=o&&o.raw,localHotelId=Number(rawOffer?.anexLocalHotelId),targetRef=String(rawOffer?.offerRef||''),searchRef=String(rawOffer?.searchRef||'');
    if(!o||o.cached||o.provider!=='anex'||rawOffer?.selectionEnabled!==false||rawOffer?.anexKind!=='group_minimum'
      ||rawOffer.anexGeneration!==generation||!Number.isSafeInteger(localHotelId)||localHotelId<1
      ||!(/^anex_online:[a-f0-9]{64}$/).test(targetRef)||!(/^[a-f0-9]{32}$/).test(searchRef)) {
      throw new Error('Выбранное предложение ANEX нельзя конкретизировать.');
    }
    const exact={...structuredClone(o.search),from:o.day,to:o.day,minNights:o.nights,maxNights:o.nights,adults:o.adults,ages:[...o.ages]};
    const filters={};if(o.meal&&!/уточняется/i.test(o.meal))filters.meals=[o.meal];
    // Concretizing one hotel belongs to this search. Stopping it would expire
    // the still-visible SAMO/ANEX offers and discard their no-replay receipts.
    // Expand retains the original first-week/nights window. Native meal labels
    // constrain the returned offers locally, not a new Tourvisor search scope.
    const p=directFirstWeekScopes(params(o.search,[String(localHotelId)]))[0],epoch=generation;
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url)throw new Error('ANEX сейчас недоступен.');
    activeVerification?.abort();const controller=new AbortController();activeVerification=controller;
    const request=async body=>{
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
        headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(body)});
      const payload=await response.json().catch(()=>null);
      if(controller.signal.aborted||epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
      if(!response.ok||payload?.ok!==true||!payload.data)throw new Error('ANEX не смог проверить выбранное предложение.');
      return payload.data;
    };
    try{
      // The initial search already retained this exact group on the server.
      // A narrowed replacement search would violate its first-week window gate.
      const expanded=await request({action:'expand',generation:epoch,search_ref:searchRef,offer_ref:targetRef,local_hotel_id:localHotelId});
      if(expanded.provider!=='anex'||expanded.generation!==epoch||expanded.search_ref!==searchRef||expanded.offer_ref!==targetRef
        ||expanded.status!=='expanded'||!Array.isArray(expanded.hotels)||expanded.hotels.length!==1
        ||Number(expanded.hotels[0]?.local_id)!==localHotelId||!Array.isArray(expanded.hotels[0]?.tours)||!expanded.hotels[0].tours.length) {
        throw new Error('ANEX не вернул конкретные варианты выбранного тура.');
      }
      const seen=new Set(),normalized=[];
      for(const tour of expanded.hotels[0].tours){
        if(tour?.kind!=='concrete'||tour?.search_ref!==searchRef)throw new Error('ANEX вернул некорректный конкретный вариант.');
        const item=await directAnexOffer(expanded.hotels[0],tour,{filters,generation:epoch,anexSessionCurrent:true},p,seen);
        if(item&&item.date===o.day&&item.nights===o.nights)normalized.push(item);
      }
      if(controller.signal.aborted||epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
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
      ||value.context?.status!=='current'||value.context?.current_context_verified!==true
      ||value.context?.selection_state!=='disabled'||typeof value.finalPriceReady!=='boolean')return null;
    const ready=value.finalPriceReady;
    let finalPrice=null,additionalPrices=null;
    if(ready){
      const amount=String(value.finalPrice??'');
      if(!(/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/).test(amount)||Number(amount)<=0||String(value.price??'')!==amount)return null;
      finalPrice=Object.freeze({amount,currency:'RUB'});
      // The current-offer response can already carry retained APD evidence.
      // Reuse the same validation as an explicit APD read; readiness alone is
      // not authority for a priced application or a final supplier quote.
      additionalPrices=normalizeAnexAdditional(value,o,'current');
      if(!additionalPrices||anexMoneyFact(finalPrice)?.units!==anexMoneyFact(additionalPrices.calculatedTotal)?.units)return null;
    }else if(value.finalPrice!==null&&value.finalPrice!==undefined||value.price!==null&&value.price!==undefined)return null;
    return Object.freeze({state:'current',currentContextVerified:true,finalPriceReady:ready,finalPrice,additionalPrices,
      finalPriceVerified:false,localHotelId:localId,searchRef,offerRef});
  }
  const anexFollowUps=Object.freeze({
    offer:{normalize:normalizeAnexConcrete,
      stale:'Конкретное предложение ANEX устарело. Откройте актуальные варианты.',
      limit:'Лимит проверки ANEX временно исчерпан.',failed:'ANEX не смог проверить выбранное предложение.',
      invalid:'ANEX вернул ответ для другого или устаревшего предложения.'},
    flights:{normalize:normalizeAnexFlights,attempts:anexFlightAttempts,receipts:anexFlightReceipts,
      stale:'Сначала проверьте контекст конкретного предложения ANEX.',
      repeated:'Рейсы уже запрашивались. Неизвестный результат не запрашивается повторно.',
      limit:'Лимит проверки рейсов ANEX временно исчерпан.',failed:'Не удалось получить рейсы ANEX.',
      invalid:'Рейсы выбранного предложения не подтверждены.'},
    additional_prices:{normalize:normalizeAnexAdditional,attempts:anexAdditionalAttempts,
      stale:'Сначала подтвердите актуальность конкретного предложения ANEX.',
      repeated:'Обязательные доплаты уже запрашивались для этого предложения. Повторите поиск для новой проверки.',
      limit:'Лимит проверки доплат ANEX временно исчерпан.',failed:'ANEX не смог уточнить обязательные доплаты.',
      invalid:'ANEX не вернул применимый расчёт обязательных доплат.'}
  });
  async function verifyAnexFollowUp(o,action){
    if(activeVerification?.quoteMutation)throw Object.assign(new Error('Дождитесь текущей проверки тура.'),{code:'quote_operation_pending',retryable:true});
    const operation=anexFollowUps[action],identity=anexConcreteKey(o);
    if(!identity||action!=='offer'&&!anexCurrentReceipts.has(identity.key))throw new Error(operation.stale);
    if(operation.receipts?.has(identity.key))return operation.receipts.get(identity.key);
    if(operation.attempts?.has(identity.key))throw new Error(operation.repeated);
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url)throw new Error('ANEX сейчас недоступен.');
    operation.attempts?.add(identity.key);
    activeVerification?.abort();const controller=new AbortController();activeVerification=controller;
    const timeout=setTimeout(()=>controller.abort(),30000);
    try{
      const body={action,generation:identity.epoch,search_ref:identity.searchRef,offer_ref:identity.offerRef,local_hotel_id:identity.localId};
      const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
        headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(body)});
      const payload=await response.json().catch(()=>null);
      if(controller.signal.aborted||identity.epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
      if(!response.ok||payload?.ok!==true||action!=='flights'&&!payload.data)throw new Error(response.status===429?operation.limit:operation.failed);
      const result=operation.normalize(payload.data,o);if(!result)throw new Error(operation.invalid);
      if(action==='offer'){
        const currentIdentity=anexConcreteKey(o);if(!currentIdentity)throw new Error('Условия поиска изменились. Выберите тур заново.');
        anexCurrentReceipts.add(currentIdentity.key);
      }else operation.receipts?.set(identity.key,result);
      return result;
    }finally{clearTimeout(timeout);if(activeVerification===controller)activeVerification=null;}
  }
  async function verifyAnexConcrete(o){return verifyAnexFollowUp(o,'offer');}
  function normalizeAnexPackage(value,o,choiceRef){
    const identity=anexConcreteKey(o),repricing=quoteRepricing(value?.repricing);
    if(!identity||repricing===null||value?.provider!=='anex'||value.generation!==identity.epoch||value.search_ref!==identity.searchRef||value.offer_ref!==identity.offerRef)return null;
    const choice=item=>{
      if(!item||!(/^anex_quote:[a-f0-9]{64}$/).test(item.choice_ref)||!Array.isArray(item.legs)||item.legs.length!==2
        ||item.legs.some(leg=>typeof leg?.label!=='string'||!leg.label.trim()||leg.label.length>6000))throw Error();
      return Object.freeze({choiceRef:item.choice_ref,legs:Object.freeze(item.legs.map(leg=>Object.freeze({label:leg.label}))),current:item.current===true});
    };
    try{
      let choices;
      if(value.choices!==undefined){
        if(!Array.isArray(value.choices)||value.choices.length<1||value.choices.length>40)return null;
        choices=value.choices.map(choice);if(new Set(choices.map(c=>c.choiceRef)).size!==choices.length)return null;
        choices=Object.freeze(choices);
      }
      if(value.status==='quote_choices'&&!choiceRef&&value.final_price_verified===false&&value.selection_state==='disabled'&&choices){
        if(repricing?.enabled===false)return null;
        return Object.freeze({state:'quote_choices',finalPriceVerified:false,choices,...(repricing?{repricing}:{})});
      }
      const price=anexMoneyFact(value.price),selected=choice(value.choice);
      if(value.status!=='quote_verified'||value.final_price_verified!==true||value.selection_state!=='preview_only'
        ||value.price?.basis!=='supplier_gross_package'||!price||selected.choiceRef!==choiceRef
        ||!Number.isInteger(value.verified_at)||!Number.isInteger(value.expires_at)||value.verified_at>value.expires_at
        ||value.expires_at*1000<=Date.now()||repricing?.enabled===false)return null;
      if(repricing&&(!Number.isSafeInteger(value.expires_at)||!Number.isSafeInteger(value.verified_at)||value.verified_at<=0||value.verified_at>Math.floor(Date.now()/1000)
        ||value.verified_at>=value.expires_at||repricing.used_pairs<1||!choices||!choices.some(item=>item.choiceRef===selected.choiceRef
        &&item.legs.every((leg,index)=>leg.label===selected.legs[index].label))))return null;
      return Object.freeze({state:'quote_verified',finalPriceVerified:true,finalPrice:Object.freeze({amount:price.amount,currency:'RUB'}),
        choice:selected,verifiedAt:value.verified_at,expiresAt:value.expires_at,...(choices?{choices}:{}),...(repricing?{repricing}:{})});
    }catch{return null;}
  }
  function safeQuoteFailureReason(provider,value){
    const codes=provider==='anex'?[
      'QUOTE_IDENTITY_UNCONFIRMED','QUOTE_TRANSPORT_UNCONFIRMED','QUOTE_PRICE_UNCONFIRMED',
      'QUOTE_SUPPLIER_REJECTED','QUOTE_HTTP_ERROR','QUOTE_TRANSPORT_ERROR','QUOTE_INVALID_RESPONSE',
      'QUOTE_CLIENT_UNAVAILABLE','QUOTE_RATE_LIMIT','QUOTE_UNKNOWN'
    ]:provider==='andromeda'?[
      'QUOTE_CONTEXT_MISMATCH','QUOTE_CHECKPOINT_INVALID','QUOTE_CHECKPOINT_CHANGED','QUOTE_CHECKPOINT_FAILED',
      'QUOTE_LOCK_FAILED','QUOTE_NOT_OFFER','FLIGHT_STATE_CHANGED','FLIGHT_STATE_FAILED','FLIGHT_STATE_INVALID',
      'FLIGHT_SELECTION_INVALID','FLIGHT_UID_INVALID','FLIGHT_OPTIONS_INVALID','FLIGHT_REF_INVALID',
      'FLIGHT_REFS_INVALID','FLIGHT_CONTEXT_INVALID','FLIGHT_ALREADY_SELECTED','SELECTED_FLIGHTS_INVALID',
      'FINAL_PRICE_MISSING','CLAIM_SHAPE_INVALID','CLAIM_TOO_LARGE','CLAIM_REQUEST_BUDGET',
      'CLAIM_ACTION_NOT_ALLOWED','PACKAGE_DISABLED','PACKAGE_REPLAY_REFUSED','INVALID_PACKAGE_ID',
      'SELECTION_CONTEXT_MISMATCH','SELECTION_MAPPING_UNAVAILABLE','QUOTE_ATTEMPT_INVALID','QUOTE_RESULT_INVALID',
      'QUOTE_MONEY_INVALID','QUOTE_PRIVATE_STATE','QUOTE_PROVENANCE_INVALID'
    ]:[];
    return typeof value==='string'&&codes.some(code=>value===provider.toUpperCase()+'_'+code)?value:null;
  }
  function safeAnexIdentityMismatches(value){
    if(!value||typeof value!=='object'||Array.isArray(value))return {};
    const fields=['document','checkin','checkout','nights','adults','children','hotel','room','meal','hotel_place','room_id'];
    return Object.fromEntries(Object.entries(value).filter(([key,kind])=>fields.includes(key)&&['missing','mismatch','shape','format'].includes(kind)));
  }
  async function verifyAnexPackage(o,choiceRef=null){
    const identity=anexConcreteKey(o);
    if(!identity||!anexCurrentReceipts.has(identity.key))throw new Error('Предложение ANEX устарело. Откройте актуальные варианты.');
    let receipt=anexPackageReceipts.get(identity.key);
    if(!receipt){receipt={result:null,inventory:null,chosen:null,error:null,pending:null,pendingChoice:null,queuedChoice:null,queueRevision:0,quotes:new Map(),repricing:null};anexPackageReceipts.set(identity.key,receipt);}
    if(receipt.error)throw receipt.error;
    if(receipt.repricing?.enabled&&receipt.expiresAt*1000<=Date.now())throw new Error('Срок подтверждённой цены истёк.');
    if(receipt.pending){
      const same=!choiceRef||receipt.pendingChoice===choiceRef;
      if(!same&&(!receipt.repricing?.enabled||!receipt.inventory?.choices.some(c=>c.choiceRef===choiceRef)))
        throw new Error('Расчёт выбранного перелёта уже выполнен.');
      if(choiceRef&&(same?receipt.queuedChoice!==null:receipt.queuedChoice!==choiceRef)){
        receipt.queuedChoice=same?null:choiceRef;receipt.queueRevision++;
      }
      const revision=receipt.queueRevision;
      let result;
      try{result=await receipt.pending;}catch(error){if(same||error.code!=='quote_pair_budget_exhausted')throw error;}
      if(receipt.error)throw receipt.error;
      if(!same&&choiceRef&&revision!==receipt.queueRevision)throw Object.assign(new Error('Выбран другой перелёт.'),{code:'quote_superseded',retryable:true});
      if(!anexConcreteKey(o)||identity.epoch!==generation)throw new Error('Условия поиска изменились. Выберите тур заново.');
      if(same)return result;
      return verifyAnexPackage(o,choiceRef);
    }
    if(!choiceRef&&receipt.result){
      if(receipt.result.state==='quote_verified'&&receipt.result.expiresAt*1000<=Date.now())throw new Error('Срок подтверждённой цены истёк.');
      return receipt.result.repricing?Object.freeze({...receipt.result,repricing:receipt.repricing}):receipt.result;
    }
    if(choiceRef){
      if(!receipt.inventory?.choices.some(c=>c.choiceRef===choiceRef))throw new Error('Выберите перелёт из ответа ANEX.');
      if(!receipt.repricing?.enabled&&receipt.chosen&&receipt.chosen!==choiceRef)throw new Error('Расчёт выбранного перелёта уже выполнен.');
      const cached=receipt.quotes.get(choiceRef);
      if(cached){
        if(cached.expiresAt*1000<=Date.now())throw new Error('Срок подтверждённой цены истёк.');
        return cached.repricing?Object.freeze({...cached,repricing:receipt.repricing}):cached;
      }
      if(receipt.repricing?.enabled&&(receipt.repricing.remaining_pairs===0||receipt.quotes.size>=3))
        throw Object.assign(new Error('Проверены три варианта перелёта. Выберите один из них.'),{code:'quote_pair_budget_exhausted',retryable:true});
    }
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.anexApi,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
    if(!url)throw new Error('ANEX сейчас недоступен.');
    // A browser abort cannot cancel a supplier mutation. Do not replace an active operation.
    if(activeVerification)throw Object.assign(new Error('Дождитесь текущей проверки тура.'),{code:'quote_operation_pending',retryable:true});
    if(choiceRef)receipt.chosen=choiceRef;
    const controller=new AbortController();controller.quoteMutation=true;activeVerification=controller;
    const timeout=setTimeout(()=>controller.abort(),65000);receipt.pendingChoice=choiceRef;receipt.queuedChoice=null;
    receipt.pending=(async()=>{
      try{
        const body={action:choiceRef?'quote_calculate':'quote_start',generation:identity.epoch,search_ref:identity.searchRef,
          offer_ref:identity.offerRef,local_hotel_id:identity.localId,...(choiceRef?{choice_ref:choiceRef}:{})};
        const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
          headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(body)});
        const payload=await response.json().catch(()=>null);
        if(controller.signal.aborted||identity.epoch!==generation)throw new Error('Проверка прервана. Повторный запрос автоматически не выполняется.');
        const capability=quoteRepricing(payload?.data?.repricing);
        if(choiceRef&&receipt.repricing?.enabled&&response.ok&&payload?.ok===true
          &&payload.data?.status==='quote_selection_locked'&&payload.data.provider==='anex'
          &&payload.data.generation===identity.epoch&&payload.data.search_ref===identity.searchRef&&payload.data.offer_ref===identity.offerRef
          &&payload.data.final_price_verified===false&&payload.data.selection_state==='disabled'
          &&Array.isArray(payload.data.choices)&&payload.data.choices.length===receipt.inventory.choices.length
          &&payload.data.choices.every((item,index)=>item?.choice_ref===receipt.inventory.choices[index].choiceRef
            &&Array.isArray(item.legs)&&item.legs.length===2&&item.legs.every((leg,n)=>leg?.label===receipt.inventory.choices[index].legs[n].label))
          &&capability?.enabled&&capability.remaining_pairs===0){
          receipt.repricing=capability;
          throw Object.assign(new Error('Проверены три варианта перелёта. Выберите один из них.'),{code:'quote_pair_budget_exhausted',retryable:true});
        }
        const result=response.ok&&payload?.ok===true?normalizeAnexPackage(payload.data,o,choiceRef):null;
        const sameInventory=!choiceRef||!result?.repricing||result.choices?.length===receipt.inventory?.choices.length
          &&result.choices.every((item,index)=>item.choiceRef===receipt.inventory.choices[index].choiceRef
            &&item.legs.every((leg,n)=>leg.label===receipt.inventory.choices[index].legs[n].label));
        const sameCapability=!choiceRef||Boolean(receipt.repricing)===Boolean(result?.repricing)
          &&(!result?.repricing||result.repricing.used_pairs>=receipt.repricing.used_pairs
            &&result.repricing.used_pairs>=receipt.quotes.size+1);
        const sameDeadline=!choiceRef||!result?.repricing||receipt.expiresAt===undefined||result.expiresAt===receipt.expiresAt;
        if(!result||!sameInventory||!sameCapability||!sameDeadline){
          const reason=safeQuoteFailureReason('anex',payload?.data?.reason);
          const detail={provider:'anex',action:body.action,code:'quote_unconfirmed',
            httpStatus:Number.isInteger(response.status)?response.status:0,...(reason?{failureReason:reason}:{})};
          const mismatches=reason==='ANEX_QUOTE_IDENTITY_UNCONFIRMED'?safeAnexIdentityMismatches(payload?.data?.identity_mismatches):{};
          if(Object.keys(mismatches).length)detail.identityMismatches=mismatches;
          if(reason&&['start','transports','SetTransport','calcfull'].includes(payload?.data?.failure_stage))detail.failureStage=payload.data.failure_stage;
          const supplierHttpStatus=payload?.data?.supplier_http_status;
          if(reason==='ANEX_QUOTE_HTTP_ERROR'&&Number.isInteger(supplierHttpStatus)&&supplierHttpStatus>=100&&supplierHttpStatus<=599)detail.supplierHttpStatus=supplierHttpStatus;
          const responseStatus=payload?.data?.status;
          detail.responseStatus=['quote_failed','quote_unavailable','quote_expired','quote_unknown','quote_selection_locked',
            'expired','mismatch','not_loaded','identity_unresolved','identity_changed','not_available','quote_choices','quote_verified'].includes(responseStatus)?responseStatus:'unknown';
          root.console?.warn?.('[AnyTour quote] '+JSON.stringify(detail));
          throw new Error('ANEX не подтвердил расчёт выбранного тура. Цена и наличие требуют уточнения.');
        }
        receipt.result=result;
        if(result.state==='quote_choices')receipt.inventory=result;
        if(result.repricing)receipt.repricing=result.repricing;
        if(choiceRef){receipt.quotes.set(choiceRef,result);if(result.repricing)receipt.expiresAt=result.expiresAt;}
        return result;
      }catch(error){if(error.code!=='quote_pair_budget_exhausted')receipt.error=error;throw error;}
      finally{clearTimeout(timeout);receipt.pending=null;receipt.pendingChoice=null;if(activeVerification===controller)activeVerification=null;}
    })();
    return receipt.pending;
  }
  function normalizeAnexFlights(value,o){
    const identity=anexConcreteKey(o),inventory=value?.flights;
    if(!identity||value?.provider!=='anex'||value.generation!==identity.epoch||value.search_ref!==identity.searchRef
      ||value.offer_ref!==identity.offerRef||value.status!=='flights'||value.selection_state!=='disabled'
      ||inventory?.provider!=='anex'||inventory.selected!==false||inventory.final_price_verified!==false
      ||inventory.included_in_search_price_verified!==false||typeof inventory.truncated!=='boolean'
      ||!Array.isArray(inventory.routes)||inventory.routes.length>6)return null;
    const label=v=>{if(v===null)return null;if(typeof v!=='string'||v.length>720)throw Error();return v;};
    const list=(v,max)=>{if(!Array.isArray(v)||v.length>max)throw Error();return v;};
    try{
      const routes=inventory.routes.map(route=>Object.freeze({date:label(route.date),from:label(route.from),to:label(route.to),
        options:Object.freeze(list(route.options,60).map(option=>Object.freeze({name:label(option.name),carrier:label(option.carrier),
          transportType:label(option.transport_type),
          departure:Object.freeze({airport:label(option.departure?.airport),airportCode:label(option.departure?.airport_code),time:label(option.departure?.time)}),
          arrival:Object.freeze({airport:label(option.arrival?.airport),airportCode:label(option.arrival?.airport_code),time:label(option.arrival?.time)}),
          classes:Object.freeze(list(option.classes,10).map(item=>{
            if(![null,'Y','N','R','F'].includes(item.availability))throw Error();
            return Object.freeze({name:label(item.name),availability:item.availability,baggage:label(item.baggage),handBaggage:label(item.hand_baggage)});
          }))}))) }));
      return Object.freeze({state:'flights',selected:false,finalPriceVerified:false,includedInSearchPriceVerified:false,
        routes:Object.freeze(routes),truncated:inventory.truncated});
    }catch{return null;}
  }
  async function verifyAnexFlights(o){return verifyAnexFollowUp(o,'flights');}
  function normalizeAnexAdditional(value,o,status='additional_prices'){
    const identity=anexConcreteKey(o),evidence=value&&value.additional_prices;
    if(!identity||!value||value.provider!=='anex'||value.generation!==identity.epoch||value.search_ref!==identity.searchRef
      ||value.offer_ref!==identity.offerRef||value.status!==status||value.selection_state!=='disabled'
      ||!evidence||evidence.application_state!=='applied'||evidence.arithmetic_applied!==true||evidence.final_price_verified!==false
      ||evidence.included_in_search_price!==false||evidence.converted_currency!=='RUB'
      ||evidence.per_person_or_package!=='per_person_by_party_type')return null;
    const search=anexMoneyFact(evidence.search_price),surcharge=anexMoneyFact(evidence.party_surcharge,false),total=anexMoneyFact(evidence.search_plus_additional);
    if(!search||!surcharge||!total||search.units+surcharge.units!==total.units
      ||evidence.search_price.source!=='direct_anex_search'
      ||evidence.party_surcharge.source!=='anex_b2b_additional_prices_daily'
      ||evidence.search_plus_additional.formula!=='search_price_plus_program_date_party_additional')return null;
    return Object.freeze({state:'additional_prices',finalPriceVerified:false,arithmeticApplied:true,
      searchPrice:Object.freeze({amount:search.amount,currency:'RUB'}),
      partySurcharge:Object.freeze({amount:surcharge.amount,currency:'RUB'}),
      calculatedTotal:Object.freeze({amount:total.amount,currency:'RUB'})});
  }
  async function verifyAnexAdditional(o){return verifyAnexFollowUp(o,'additional_prices');}
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
    const quoteParams=rawOffer?.andromedaSearchParams&&typeof rawOffer.andromedaSearchParams==='object'&&!Array.isArray(rawOffer.andromedaSearchParams)
      ?rawOffer.andromedaSearchParams:null;
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
      if(!outbound||!inbound||!andromedaQuoteCurrent(retained))return null;
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
  function andromedaTransportMarkup(value){
    if(!value||value.source!=='andromeda_transport_detail'||value.aggregation!=='unknown'
      ||typeof value.amount!=='string'||!(/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/).test(value.amount)
      ||typeof value.currency!=='string'||!(/^[A-Z0-9_]{2,8}$/).test(value.currency))return null;
    return Object.freeze({amount:value.amount,currency:value.currency,source:value.source,aggregation:value.aggregation});
  }
  function quoteRepricing(value){
    if(value===undefined)return undefined;
    if(!value||typeof value!=='object'||Array.isArray(value)||typeof value.enabled!=='boolean'||value.max_pairs!==3
      ||!Number.isInteger(value.used_pairs)||value.used_pairs<0||value.used_pairs>3
      ||!Number.isInteger(value.remaining_pairs)||value.remaining_pairs<0||value.remaining_pairs>3
      ||(value.enabled?value.used_pairs+value.remaining_pairs!==3:value.remaining_pairs!==0))return null;
    return Object.freeze({enabled:value.enabled,max_pairs:3,used_pairs:value.used_pairs,remaining_pairs:value.remaining_pairs});
  }
  function andromedaQuoteFlight(value,pending,seen){
    if(!value||!['0','1'].includes(String(value.direction||'')))return null;
    const row={direction:String(value.direction),name:typeof value.name==='string'?value.name.slice(0,160):null,
      datebeg:typeof value.datebeg==='string'?value.datebeg.slice(0,40):null,dateend:typeof value.dateend==='string'?value.dateend.slice(0,40):null,
      class:typeof value.class==='string'?value.class.slice(0,80):null,departure:andromedaPoint(value.departure),arrival:andromedaPoint(value.arrival),
      transportMarkupReported:andromedaTransportMarkup(value.transport_markup_reported)};
    if(value.departure!==null&&value.departure!==undefined&&!row.departure||value.arrival!==null&&value.arrival!==undefined&&!row.arrival)return null;
    if(pending||Object.prototype.hasOwnProperty.call(value,'flight_ref')){
      const ref=value.flight_ref;if(typeof ref!=='string'||!(/^flight_[a-f0-9]{32}$/).test(ref)||seen.has(ref))return null;
      seen.add(ref);row.flightRef=ref;
    }
    return Object.freeze(row);
  }
  function andromedaQuoteCurrent(quote){return Number.isSafeInteger(quote?.expiresAt)&&quote.expiresAt*1000>Date.now();}
  function normalizeAndromedaQuote(value,localId,selection=null){
    const repricing=quoteRepricing(value?.repricing);
    if(repricing===null||repricing?.enabled===false||!value||value.schema_version!==1||value.provider!=='andromeda'||Number(value.local_id)!==localId
      ||value.selection_enabled!==true||value.booking_enabled!==false||!Array.isArray(value.flights)
      ||!Number.isSafeInteger(value.expires_at)||value.expires_at*1000<=Date.now()
      ||value.flights.length>(value.state==='flight_selection_required'?1000:100))return null;
    const verified=value.state==='quote_verified'&&value.quote_state==='verified'&&value.final_price_verified===true
      &&value.flight_selection_required===false;
    const pending=value.state==='flight_selection_required'&&value.quote_state==='unverified'&&value.final_price_verified===false
      &&value.flight_selection_required===true&&value.final_price===null;
    if(!verified&&!pending||repricing&&verified&&!selection)return null;
    let finalPrice=null;
    if(verified){
      const amount=String(value.final_price?.amount??''),currency=String(value.final_price?.currency??'');
      if(currency!=='RUB'||!(/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/).test(amount)||Number(amount)<=0
        ||repricing&&(!Number.isSafeInteger(value.verified_at)||value.verified_at<=0||value.verified_at>Math.floor(Date.now()/1000)
          ||value.verified_at>=value.expires_at||repricing.used_pairs<1))return null;
      finalPrice=Object.freeze({amount,currency});
    }
    const seen=new Set(),flights=[];
    for(const raw of value.flights){const flight=andromedaQuoteFlight(raw,pending,seen);if(!flight)return null;flights.push(flight);}
    if(pending&&(!flights.some(row=>row.direction==='0')||!flights.some(row=>row.direction==='1')))return null;
    // Legacy completed receipts have no refs; retain their sealed outcome.
    // When the server supplies refs, a total must belong to the submitted pair.
    if(verified&&selection&&(repricing||flights.some(row=>row.flightRef))
      &&(flights.length!==2||flights.find(row=>row.direction==='0')?.flightRef!==selection.outbound_ref
        ||flights.find(row=>row.direction==='1')?.flightRef!==selection.return_ref))return null;
    return Object.freeze({state:pending?'flight_selection_required':'quote_verified',finalPrice,finalPriceVerified:verified,
flightSelectionRequired:pending,flights:Object.freeze(flights),expiresAt:value.expires_at,...(verified&&Number.isSafeInteger(value.verified_at)?{verifiedAt:value.verified_at}:{}),...(repricing?{repricing}:{})});
  }
  function hasAndromedaQuoteAttempt(o){const prepared=andromedaQuoteRequest(o);return !!prepared&&andromedaQuoteAttempts.has(prepared.key);}
  function andromedaQuoteFailure(status=0,payload=null,kind=''){
    const allowed=['supplier_transport','supplier_http','supplier_rejected','supplier_response','supplier_auth','quote_state','internal'];
    const category=kind|| (status===429?'limit':status===422?'unavailable':status===403?'access':status===400?'invalid_request':allowed.includes(payload?.failure_category)?payload.failure_category:'internal');
    const facts={};
    const responseCategory=status===200&&kind==='invalid_response'&&allowed.includes(payload?.failure_category)?payload.failure_category:null;
    const responseFailure=responseCategory?{failureCategory:responseCategory}:null,diagnosticFacts=responseFailure||facts;
    const diagnosticCategory=responseCategory||category;
    const reason=diagnosticCategory==='quote_state'?safeQuoteFailureReason('andromeda',payload?.failure_reason)
      ||(responseCategory==='quote_state'&&['ANDROMEDA_QUOTE_REPLAY_REFUSED','ANDROMEDA_FLIGHT_REPRICE_BUDGET',
        'ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID','ANDROMEDA_FLIGHT_REPRICE_CURRENCY'].includes(payload?.failure_reason)?payload.failure_reason:null)
      :diagnosticCategory==='supplier_response'&&[
      'ANDROMEDA_INVALID_RESPONSE','ANDROMEDA_INVALID_PACKAGE_RESPONSE','ANDROMEDA_INVALID_CLAIM_RESPONSE',
      'ANDROMEDA_RESPONSE_TOO_LARGE','ANDROMEDA_SECRET_ECHO'
    ].includes(payload?.failure_reason)?payload.failure_reason:null;
    if(reason)diagnosticFacts.failureReason=reason;
    if(['request','database','catalog','criteria','quote_resolve','quote_reserve','quote_bootstrap','flight_state',
      'quote_validate','quote_checkpoint','flight_continuation'].includes(payload?.failure_phase))facts.failurePhase=payload.failure_phase;
    if(diagnosticCategory==='supplier_rejected'&&['broninit','get_flights','changeservice','calc'].includes(payload?.failure_stage)){
      diagnosticFacts.failureStage=payload.failure_stage;
      const code=payload.supplier_code;
      if(typeof code==='string'&&code.length>0&&code.length<=64&&!/[^A-Za-z0-9_.:-]/.test(code))diagnosticFacts.supplierCode=code;
    }
    if(responseFailure)facts.responseFailure=Object.freeze(responseFailure);
    const message=category==='limit'?'Сейчас проверка этого поставщика недоступна. Выберите другое предложение или вернитесь позже.':
      category==='unavailable'?'Не удалось подтвердить этот тур. Выберите другое предложение.':
      category==='access'?'Проверка этого тура временно недоступна.':
      category==='invalid_request'?'Условия этого предложения не удалось проверить.':
      category==='invalid_response'?'Andromeda вернул некорректное подтверждение. Цена и наличие пока неизвестны.':
      category==='expired'?'Срок подтверждения тура истёк. Выполните новый поиск.':
      category==='stale'?'Условия поиска изменились. Выберите тур заново.':
      'Подтверждение тура не получено. Цена и наличие пока неизвестны.';
    return Object.assign(new Error(message),{code:category==='expired'?'offer_expired':category==='unavailable'?'offer_unavailable':'quote_unconfirmed',retryable:false,
      httpStatus:Number.isInteger(status)&&status>=0&&status<=599?status:0,failureCategory:category},facts);
  }
  async function verifyAndromeda(o,flightSelection=null){
    const base=andromedaQuoteRequest(o);
    const url=nativeEndpoint(root.V2_CONFIG&&root.V2_CONFIG.andromedaQuoteApi,'/_preview/search3-anex-candidate/api-andromeda-quote-preview.php');
    if(!base||!url)throw Object.assign(new Error('Предложение Andromeda устарело. Повторите поиск.'),{code:'offer_expired',retryable:false});
    const retained=andromedaQuoteAttempts.get(base.key),phase=flightSelection?'continuation':'initial';
    if(retained?.error)throw retained.error;
    let pairKey=null;
    if(flightSelection){
      if(Object.keys(flightSelection).sort().join(',')!=='outbound_ref,provider,return_ref'||flightSelection.provider!=='andromeda'
        ||!(/^flight_[a-f0-9]{32}$/).test(String(flightSelection.outbound_ref||''))
        ||!(/^flight_[a-f0-9]{32}$/).test(String(flightSelection.return_ref||'')))throw andromedaQuoteFailure(0,null,'unavailable');
      pairKey=flightSelection.outbound_ref+'|'+flightSelection.return_ref;
    }
    if(retained?.active){
      const active=retained.active,same=!flightSelection||active.selection?.outbound_ref===flightSelection.outbound_ref
        &&active.selection?.return_ref===flightSelection.return_ref;
      if(!same&&(!retained.repricing?.enabled||!andromedaQuoteRequest(o,flightSelection)))throw andromedaQuoteFailure(0,null,'unavailable');
      if(flightSelection&&(same?retained.queuedChoice!==null:retained.queuedChoice!==pairKey)){
        retained.queuedChoice=same?null:pairKey;retained.queueRevision++;
      }
      const revision=retained.queueRevision;
      let quote;
      try{quote=await active.promise;}catch(error){if(same||error.code!=='quote_pair_budget_exhausted')throw error;}
      if(retained.error)throw retained.error;
      if(!same&&flightSelection&&revision!==retained.queueRevision)throw Object.assign(new Error('Выбран другой перелёт.'),{code:'quote_superseded',retryable:true});
      if(!andromedaQuoteRequest(o)||same&&!andromedaQuoteCurrent(quote))throw andromedaQuoteFailure(0,null,'expired');
      if(same)return quote;
      return verifyAndromeda(o,flightSelection);
    }
    // Legacy receipts retain one continuation. Only an explicit fresh capability enables a pair cache.
    const previous=flightSelection?(retained?.repricing?.enabled?retained.pairs?.get(pairKey):retained?.continuation)
      :retained?.continuation||retained?.initial;
    if(previous){
      if(flightSelection){
        const expected=previous.selection;
        if(Object.keys(expected).some(key=>expected[key]!==flightSelection[key])
          ||retained.repricing?.enabled&&!andromedaQuoteRequest(o,flightSelection))throw andromedaQuoteFailure(0,null,'unavailable');
      }
      const quote=await previous.promise;
      if(retained.error)throw retained.error;
      if(!andromedaQuoteCurrent(quote))throw andromedaQuoteFailure(0,null,'expired');
      return quote.repricing?Object.freeze({...quote,repricing:retained.repricing}):quote;
    }
    if(flightSelection&&!andromedaQuoteCurrent(andromedaQuoteChoices.get(base.key)))throw andromedaQuoteFailure(0,null,'expired');
    const prepared=flightSelection?andromedaQuoteRequest(o,flightSelection):base;
    if(!prepared)throw Object.assign(new Error('Предложение Andromeda устарело. Повторите поиск.'),{code:'offer_expired',retryable:false});
    if(flightSelection&&retained?.repricing?.enabled&&(retained.repricing.remaining_pairs===0||retained.pairs.size>=3))
      throw Object.assign(new Error('Проверены три варианта перелёта. Выберите один из них.'),{code:'quote_pair_budget_exhausted',retryable:true});
    // Keep the supplier mutation alive; its completion or UNKNOWN seal governs all queued selections.
    if(activeVerification)throw Object.assign(new Error('Дождитесь текущей проверки тура.'),{code:'quote_operation_pending',retryable:true});
    const controller=new AbortController();controller.quoteMutation=true;activeVerification=controller;
    const epoch=generation;let timedOut=false;
    const timeout=setTimeout(()=>{timedOut=true;controller.abort();},45000);
    const attempts=retained||{pairs:new Map(),queueRevision:0,queuedChoice:null,repricing:null,error:null},attempt={selection:flightSelection?structuredClone(flightSelection):null};
    const priorContinuation=attempts.continuation;
    attempts[phase]=attempt;attempts.active=attempt;attempts.queuedChoice=null;
    if(flightSelection&&attempts.repricing?.enabled)attempts.pairs.set(pairKey,attempt);
    andromedaQuoteAttempts.set(base.key,attempts);
    attempt.promise=(async()=>{
      let status=0;
      try{
        const response=await fetch(url.href,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,
          headers:{'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3'},body:JSON.stringify(prepared.body)});
        status=response.status;
        const payload=await response.json().catch(()=>null);
        if(controller.signal.aborted||epoch!==generation||!andromedaQuoteRequest(o,flightSelection))throw andromedaQuoteFailure(status,null,timedOut?'timeout':'stale');
        const capability=quoteRepricing(payload?.data?.repricing),reason=payload?.data?.failure_reason||payload?.failure_reason;
        const projectedBudget=flightSelection&&attempts.repricing?.enabled&&response.ok&&payload?.ok===true
          &&reason==='ANDROMEDA_FLIGHT_REPRICE_BUDGET'?normalizeAndromedaQuote(payload?.data,prepared.localId):null;
        if(projectedBudget?.state==='flight_selection_required'&&capability?.enabled&&capability.remaining_pairs===0
          &&projectedBudget.expiresAt===andromedaQuoteChoices.get(base.key)?.expiresAt
          &&projectedBudget.flights.length===andromedaQuoteChoices.get(base.key)?.flights.length
          &&projectedBudget.flights.every((flight,index)=>flight.direction===andromedaQuoteChoices.get(base.key).flights[index].direction
            &&flight.flightRef===andromedaQuoteChoices.get(base.key).flights[index].flightRef)){
          attempts.repricing=capability;
          throw Object.assign(new Error('Проверены три варианта перелёта. Выберите один из них.'),{code:'quote_pair_budget_exhausted',retryable:true});
        }
        if(!response.ok||payload?.ok!==true||!payload.data)throw andromedaQuoteFailure(status,payload);
        let quote=normalizeAndromedaQuote(payload.data,prepared.localId,prepared.body.flight_selection);
        if(!quote||flightSelection&&attempts.repricing?.enabled&&(!quote.repricing
          ||quote.repricing.used_pairs<attempts.repricing.used_pairs||quote.repricing.used_pairs<attempts.pairs.size||quote.state!=='quote_verified'))
          throw andromedaQuoteFailure(status,payload.data,'invalid_response');
        const inventory=andromedaQuoteChoices.get(base.key);
        if(quote.repricing){
          if(flightSelection&&!attempts.repricing)throw andromedaQuoteFailure(status,null,'invalid_response');
          if(flightSelection&&(!inventory||quote.expiresAt!==inventory.expiresAt))throw andromedaQuoteFailure(status,null,'invalid_response');
          attempts.repricing=quote.repricing;
          if(quote.state==='quote_verified'&&inventory)quote=Object.freeze({...quote,flightChoices:inventory.flights});
        }
        if(quote.flightSelectionRequired)andromedaQuoteChoices.set(prepared.key,quote);
        else if(!quote.repricing)andromedaQuoteChoices.delete(prepared.key);
        return quote;
      }catch(error){
        if(error.code==='quote_pair_budget_exhausted'){attempts.pairs.delete(pairKey);attempts.continuation=priorContinuation;throw error;}
        if(flightSelection)andromedaQuoteChoices.delete(prepared.key);
        const failure=error?.retryable===false?error:andromedaQuoteFailure(status,null,epoch!==generation?'stale':timedOut?'timeout':'network');
        attempts.error=failure;
        if(attempts.repricing)attempts.repricing=Object.freeze({...attempts.repricing,enabled:false,remaining_pairs:0});
        // Browser-local diagnostics retain bounded public failure facts, never response text or identities.
        if(epoch===generation){
          const detail=Object.freeze({provider:'andromeda',action:prepared.body.action,code:failure.code,
            httpStatus:failure.httpStatus,failureCategory:failure.failureCategory,
            ...(failure.failureReason?{failureReason:failure.failureReason}:{}),
            ...(failure.failureStage?{failureStage:failure.failureStage}:{}),
            ...(failure.supplierCode?{supplierCode:failure.supplierCode}:{})});
          root.console?.warn?.('[AnyTour quote] '+JSON.stringify({...detail,
            ...(failure.failurePhase?{failurePhase:failure.failurePhase}:{}),
            ...(failure.responseFailure?{responseFailure:failure.responseFailure}:{})}));
          if(typeof root.CustomEvent==='function'&&typeof root.dispatchEvent==='function')root.dispatchEvent(new root.CustomEvent('anytour:quote-failure',{detail}));
        }
        throw failure;
      }finally{clearTimeout(timeout);attempts.active=null;if(activeVerification===controller)activeVerification=null;}
    })();
    return attempt.promise;
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
  function fuel(t,v){
    const source=v&&Object.hasOwn(v,'fuelCharge')?v:t;
    const raw=source?.fuelCharge,value=raw&&typeof raw==='object'?raw.value:raw;
    if(!['number','string'].includes(typeof value)||String(value).trim()==='')return null;
    const n=Number(value);return Number.isFinite(n)&&n>=0?n:null;
  }
  root.AnyTourPrototypeData=Object.freeze({init,countries,regions,search,resumeCached,continueSearch,stop,calendar,calendarPrices,observedCalendar,observationScopeSupported,expandAnexGroup,verifyAnexConcrete,verifyAnexAdditional,verifyAnexFlights,verifyAnexPackage,verifyAndromeda,hasAndromedaQuoteAttempt,quote,flights,leadSession,params,supplierScope,supplierScopeCovered,sameScope,project,amount,date,text,meal,mealPlan,operator,variantPrice,fuel,savedHotels,lookupHotels,restoreHotel,catalog,get searchId(){return searchId;},get currentSupplierScope(){return currentSupplierScope;}});
})(window);
