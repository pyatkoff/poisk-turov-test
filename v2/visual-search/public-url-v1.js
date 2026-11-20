'use strict';
// Pure public-link compatibility. The future production entry must call this
// explicitly for the legacy namespace with its loaded departure/country catalog.
// Loading this file does not navigate, rewrite history, fetch or start a search.
(function(root){
 const aliases={from:['date_from','dateFrom'],to:['date_till','dateTo'],minNights:['days_from','daysFrom'],maxNights:['days_till','daysTill'],adults:['count_people']};
 const attribution=['yclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
 const unsupported=['arrival','region','subregion','hotel','operator','hotel_type','stars','rating','food','price_from','price_till','onlyDirect','only_direct','onlyCharter','only_charter','hotel_service[]','hotel_service'];
 // from/country are shared; legacy hotel/stars/rating remain explicit unresolved filters above.
 const nextKeys=['origin','scenario','to','minNights','maxNights','adults','ages','date','searched','favorites','hotels','resorts','meals','operators','flight','amenities','amenityLabels','beach','family','spa','q','min','max','sort'];
 const known=new Set(['from','country','child_count','child_age[]','child_age',...Object.values(aliases).flat(),...unsupported,...attribution]);
 const shared=new Set(['from','country','hotel','stars','rating']);
 const modern=new Set([...nextKeys,...shared,...attribution]);
 const value=(p,keys)=>{for(const key of keys){const v=p.get(key);if(v!==null&&v!=='')return v;}return '';};
 const positive=v=>/^[1-9]\d*$/.test(v)&&Number.isSafeInteger(Number(v));
 const fail=(code,fields)=>Object.freeze({ok:false,code,fields:Object.freeze(fields)});
 function inspect(query){
  const input=new URLSearchParams(query),keys=[...new Set([...input.keys()])];
  const next=nextKeys.filter(key=>input.has(key));
  const legacy=keys.filter(key=>known.has(key)&&!shared.has(key)&&!attribution.includes(key));
  const nativeDeparture=input.has('from')&&/^[1-9]\d*$/.test(input.get('from')||'');
  if(next.length&&(legacy.length||nativeDeparture))return fail('mixed_namespace',[...legacy,...(nativeDeparture?['from']:[])]);
  const namespace=next.length?'next':keys.some(key=>!attribution.includes(key)&&input.getAll(key).some(v=>v!==''))?'legacy':'empty';
  const allowed=namespace==='next'?modern:known;
  const unknown=keys.filter(key=>!allowed.has(key)&&input.getAll(key).some(v=>v!==''));
  if(unknown.length)return fail('unknown_parameters',unknown);
  return Object.freeze({ok:true,namespace});
 }
 function fromLegacy(query,catalog){
  const input=new URLSearchParams(query),output=new URLSearchParams();
  const modern=nextKeys.filter(key=>input.has(key));if(modern.length)return fail('next_namespace',modern);
  const restricted=unsupported.filter(key=>input.getAll(key).some(v=>v!==''));if(restricted.length)return fail('unresolved_filters',restricted);
  const unknown=[...new Set([...input].filter(([key,v])=>v!==''&&!known.has(key)).map(([key])=>key))];if(unknown.length)return fail('unknown_parameters',unknown);
  if(!catalog||!Array.isArray(catalog.departures)||!Array.isArray(catalog.countries)||typeof catalog.text!=='function')return fail('catalog_required',[]);
  if(input.has('from')){
   const id=input.get('from'),rows=positive(id)?catalog.departures.filter(row=>String(row.id)===id):[];
   if(rows.length!==1||!catalog.text(rows[0]))return fail('departure_unmapped',['from']);
   const origin=catalog.text(rows[0]);
   if(catalog.origin!==origin)return fail('departure_catalog_required',['from']);
   output.set('origin',origin);
  }
  if(input.has('country')){
   const id=input.get('country'),rows=positive(id)?catalog.countries.filter(row=>Array.isArray(row.tourvisorIds)&&row.tourvisorIds.map(String).includes(id)):[];
   if(rows.length!==1||!positive(String(rows[0].id))||rows[0].tourvisorIds.length!==1)return fail('country_unmapped',['country']);
   output.set('country',String(rows[0].id));
  }
  for(const [key,names] of Object.entries(aliases)){const v=value(input,names);if(v!=='')output.set(key,v);}
  if(output.has('from')||output.has('to')){
   const valid=v=>/^\d{4}-\d{2}-\d{2}$/.test(v||'')&&Number.isFinite(Date.parse(v+'T00:00:00Z'))&&new Date(v+'T00:00:00Z').toISOString().slice(0,10)===v;
   const from=output.get('from'),to=output.get('to');
   if(!valid(from)||!valid(to)||to<from||(Date.parse(to)-Date.parse(from))/86400000>21)return fail('dates_invalid',['dateFrom','dateTo']);
   if(!valid(catalog.startDay)||!valid(catalog.endDay))return fail('date_bounds_required',[]);
   if(from<catalog.startDay||to>catalog.endDay)return fail('dates_outside_next_window',['dateFrom','dateTo']);
  }
  for(const key of ['minNights','maxNights'])if(output.has(key)&&(!positive(output.get(key))||Number(output.get(key))>28))return fail('nights_invalid',[key]);
  if(output.has('minNights')!==output.has('maxNights'))return fail('nights_incomplete',['daysFrom','daysTill']);
  if(output.has('minNights')){const low=Number(output.get('minNights')),high=Number(output.get('maxNights'));if(high<low||high-low>10)return fail('nights_invalid',['daysFrom','daysTill']);}
  if(output.has('adults')&&!/^[1-6]$/.test(output.get('adults')))return fail('party_invalid',['count_people']);
  const rawAges=input.getAll('child_age[]').concat(input.getAll('child_age')),count=value(input,['child_count']);
  if(rawAges.length||count!==''){
   if(count!==''&&!/^[0-3]$/.test(count)||rawAges.length>3||count!==''&&Number(count)!==rawAges.length)return fail('party_incomplete',['child_count','child_age[]']);
   if(rawAges.some(age=>!/^\d+$/.test(age.trim())||Number(age)>17))return fail('party_invalid',['child_age[]']);
   output.set('ages',rawAges.map(age=>String(Number(age))).join(','));
  }
  for(const key of attribution)if(input.has(key))output.set(key,input.get(key));
  // Match the old hydrateUrlState decision; this flag alone performs no request.
  const explicit=['date_from','dateFrom','date_till','dateTo','days_from','daysFrom','days_till','daysTill','hotel'];
  const autoSearch=input.has('from')&&input.has('country')&&explicit.some(key=>input.has(key));
  return Object.freeze({ok:true,query:output.toString(),autoSearch});
 }
 root.AnyTourPublicSearchURLV1=Object.freeze({fromLegacy,inspect});
})(typeof window==='undefined'?globalThis:window);
