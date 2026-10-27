'use strict';
// Adapt the existing homepage GET contract. IDs remain in their source namespace
// until the current catalogues provide one unambiguous accepted correspondence.
(function(root){
 const aliases=[['date_from','dateFrom'],['date_till','dateTo'],['days_from','daysFrom'],['days_till','daysTill']];
 const dateNightKeys=['dateFrom','dateTo','daysFrom','daysTill',...aliases.map(([key])=>key)];
 const legacyKeys=[...dateNightKeys,'count_people','child_age[]','child_age','child_count','count_child','food','price_from','price_till'];
 const unsupportedTerms=['hotel','hotels','hotelId','hotelIds','hotel_id','hotel_ids','region','regions','regionId','regionIds','region_id','region_ids','subregion','subRegion','subregionId','subregionIds','town','townId','resort','resorts',
  'operator','operator[]','operatorId','operatorIds','operator_ids','arrival','arrivalId','hotel_type','hotel_type[]','hotelTypes','onlyDirect','only_direct','onlyCharter','only_charter','hotel_service[]','hotelServices','rating'];
 const singletonKeys=['s3','origin','from','to','country',...dateNightKeys,'count_people','child_count','count_child','food','price_from','price_till','minNights','maxNights','adults','ages','meals','min','max','stars'];
 function fail(code){const error=new Error('Не удалось перенести условия из старой ссылки. Откройте новый поиск и задайте параметры заново.');error.code=code;throw error;}
 function read(search){
  const params=new URLSearchParams(search);
  if(params.getAll('s3').length>1||params.has('s3')&&params.get('s3')!=='1')fail('search3_query_marker_invalid');
  return params;
 }
 function numeric(value){return typeof value==='string'&&/^[1-9][0-9]{0,9}$/.test(value)&&Number.isSafeInteger(Number(value))&&Number(value)<=2147483647;}
 function day(value){
  if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;
  const stamp=Date.parse(value+'T12:00:00Z');return Number.isFinite(stamp)&&new Date(stamp).toISOString().slice(0,10)===value;
 }
 function integer(value,min,max,code){if(typeof value!=='string'||!/^(?:0|[1-9][0-9]*)$/.test(value)||!Number.isSafeInteger(Number(value))||Number(value)<min||Number(value)>max)fail(code);return value;}
 function label(row,fields,code){
  const value=fields.map(key=>row?.[key]).find(value=>typeof value==='string'&&value.trim());
  if(!value||value.length>500||/[\x00-\x1f\x7f]/.test(value))fail(code);return value.trim();
 }
 function unique(rows,predicate,code){const found=Array.isArray(rows)?rows.filter(row=>row&&predicate(row)):[];if(found.length!==1)fail(code);return found[0];}
 function put(out,key,value){if(out.has(key)&&out.get(key)!==value)fail('search3_query_conflicting_terms');out.set(key,value);}
 function initialOrigin(search,fallback){
  const p=read(search);
  for(const key of ['origin','from'])if(p.getAll(key).length>1)fail('search3_query_duplicate_field');
  if(p.get('origin'))return p.get('origin');
  const from=p.get('from');if(numeric(from))return from;
  if(from&&!day(from))fail('search3_query_departure_invalid');
  return fallback;
 }
 function normalize(search,catalog={},options={}){
  const p=read(search),out=new URLSearchParams(p);
  if(p.get('s3')==='1')return out;
  for(const key of singletonKeys)if(p.getAll(key).length>1)fail('search3_query_duplicate_field');
  for(const [old,key] of aliases)if(p.has(old)){if(p.has(key)&&p.get(key)!==p.get(old))fail('search3_query_conflicting_terms');p.set(key,p.get(old));}
  for(const key of p.keys())if(/^child_age\[/.test(key)&&key!=='child_age[]')fail('search3_query_ages_invalid');
  const legacyDeparture=numeric(p.get('from'));
  const legacy=legacyDeparture||legacyKeys.some(key=>p.has(key))
   ||(p.has('country')||unsupportedTerms.some(key=>p.has(key)))&&!p.get('origin')&&!day(p.get('from'))&&!day(p.get('to'));
  if(!legacy)return out;
  for(const key of unsupportedTerms)if(p.getAll(key).some(value=>value!==''))fail('search3_query_legacy_terms_unsupported');
  const ready=options.identitiesReady===true;
  if(legacyDeparture&&p.get('origin')&&p.get('origin')!==p.get('from'))fail('search3_query_conflicting_terms');
  const departure=legacyDeparture?p.get('from'):numeric(p.get('origin'))?p.get('origin'):null;
  if(departure){
   if(ready){
    const row=unique(catalog.departures,row=>String(row.id)===departure,'search3_query_departure_unmapped');
    out.set('origin',label(row,['russianName','name'],'search3_query_departure_unmapped'));
   }else out.set('origin',departure);
  }else if(p.has('from')&&!day(p.get('from'))&&p.get('from')!=='')fail('search3_query_departure_invalid');
  if(p.has('country')){
   const id=p.get('country');if(!numeric(id))fail('search3_query_country_invalid');
   if(ready){
    const row=unique(catalog.countries,row=>Array.isArray(row.tourvisorIds)&&row.tourvisorIds.length===1&&String(row.tourvisorIds[0])===id,'search3_query_country_unmapped');
    const local=String(row.id);if(!numeric(local))fail('search3_query_country_unmapped');out.set('country',local);
   }
  }
  if(p.has('dateFrom')!==p.has('dateTo'))fail('search3_query_dates_incomplete');
  if(p.has('dateFrom')){
   const from=p.get('dateFrom'),to=p.get('dateTo');if(!day(from)||!day(to)||from>to||(Date.parse(to)-Date.parse(from))/86400000>21)fail('search3_query_dates_invalid');
   if(!legacyDeparture)put(out,'from',from);else out.set('from',from);
   put(out,'to',to);
  }else if(legacyDeparture)out.delete('from');
  if(p.has('daysFrom')!==p.has('daysTill'))fail('search3_query_nights_incomplete');
  if(p.has('daysFrom')){
   const min=integer(p.get('daysFrom'),1,28,'search3_query_nights_invalid'),max=integer(p.get('daysTill'),1,28,'search3_query_nights_invalid');
   if(Number(min)>Number(max)||Number(max)-Number(min)>10)fail('search3_query_nights_invalid');
   put(out,'minNights',min);put(out,'maxNights',max);
  }
  if(p.has('count_people'))put(out,'adults',integer(p.get('count_people'),1,6,'search3_query_adults_invalid'));
  if(p.has('child_age[]')&&p.has('child_age'))fail('search3_query_ages_invalid');
  const ages=p.has('child_age[]')?p.getAll('child_age[]'):p.getAll('child_age');
  if(ages.length>3)fail('search3_query_ages_invalid');
  ages.forEach(value=>integer(value,0,17,'search3_query_ages_invalid'));
  if(p.has('child_age[]')||p.has('child_age'))put(out,'ages',ages.join(','));
  for(const key of ['child_count','count_child'])if(p.has(key)){
   const count=Number(integer(p.get(key),0,3,'search3_query_ages_invalid'));
   if(count!==ages.length)fail('search3_query_ages_incomplete');
   if(!count)put(out,'ages','');
  }
  if(p.has('food')&&p.get('food')!==''){
   const id=p.get('food');if(!numeric(id))fail('search3_query_meal_invalid');
   if(ready){const row=unique(catalog.meals,row=>String(row.id)===id,'search3_query_meal_unmapped');put(out,'meals',label(row,['russianName','fullRussianName','name'],'search3_query_meal_unmapped'));}
  }
  for(const [old,key] of [['price_from','min'],['price_till','max']])if(p.has(old)&&p.get(old)!==''){
   const value=p.get(old);if(!/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/.test(value))fail('search3_query_budget_invalid');put(out,key,value);
  }
  if(out.has('min')&&out.has('max')&&Number(out.get('min'))>Number(out.get('max')))fail('search3_query_budget_invalid');
  if(ready&&p.has('stars')&&p.get('stars')!==''){
   const min=Number(integer(p.get('stars'),1,5,'search3_query_stars_invalid'));
   out.set('stars',Array.from({length:6-min},(_,index)=>String(min+index)).join('|'));
  }
  if(ready){for(const key of [...legacyKeys,...unsupportedTerms])out.delete(key);out.set('s3','1');}
  return out;
 }
 function isLegacySubmission(search){
  const p=read(search);
  if(p.has('s3')||p.get('origin')||!numeric(p.get('from'))||!numeric(p.get('country'))
    ||!['dateFrom','date_from'].some(key=>p.has(key))||!['dateTo','date_till'].some(key=>p.has(key)))return false;
  // This detects the original form intent only. The caller must also validate
  // the normalized dates against its actual current UI/search window.
  try{normalize(p,{},{});return true;}catch{return false;}
 }
 root.AnyTourSearch3ProductionQueryV1=Object.freeze({normalize,initialOrigin,isLegacySubmission});
})(window);
