(function(){'use strict';
const catalogs=window.V2Catalogs,form=document.getElementById('tourSearch');if(!catalogs||!form||typeof catalogs.init!=='function'||typeof catalogs.handleChange!=='function')return;
const originalInit=catalogs.init.bind(catalogs);
function hasOption(select,value){return !!select&&Array.from(select.options||[]).some(option=>String(option.value)===String(value));}
function ensureTemporaryOption(select,value){if(!select||!value||hasOption(select,value))return;const option=document.createElement('option');option.value=String(value);option.textContent='ID '+String(value);select.appendChild(option);}
function activeQuery(sp,key){const values=sp.getAll(key);return values.some(value=>String(value||'').trim()!=='');}
function activeFlag(sp,keys){return keys.some(key=>sp.getAll(key).some(value=>['1','true','yes'].includes(String(value||'').trim().toLowerCase())));}
function revealSecondaryOnDesktop(sp){const extras=form.querySelector('details.extras');if(!extras||typeof window.matchMedia!=='function'||!window.matchMedia('(min-width: 1025px)').matches)return;const secondary=['arrival','region','subregion','hotel','operator','hotel_type','rating','food','price_from','price_till'];const active=secondary.some(key=>activeQuery(sp,key))||activeFlag(sp,['onlyDirect','only_direct'])||activeFlag(sp,['onlyCharter','only_charter'])||activeQuery(sp,'hotel_service[]')||activeQuery(sp,'hotel_service');if(active)extras.open=true;}
async function syncPrimary(desiredFrom,desiredCountry){
  const from=form.elements.from,country=form.elements.country;if(!from||!country)return;
  if(desiredFrom&&hasOption(from,desiredFrom)&&String(from.value)!==String(desiredFrom)){
    from.value=String(desiredFrom);
    if(desiredCountry){ensureTemporaryOption(country,desiredCountry);country.value=String(desiredCountry);}
    try{await catalogs.handleChange({target:from});}catch(error){console.warn('V2 URL primary catalog sync',error);}
  }
  if(desiredCountry&&hasOption(country,desiredCountry)&&String(country.value)!==String(desiredCountry)){
    country.value=String(desiredCountry);
    try{await catalogs.handleChange({target:country});}catch(error){console.warn('V2 URL country catalog sync',error);}
  }
}
async function syncAdvanced(desiredRegion,desiredSubregion,desiredHotel){
  if(!desiredRegion&&!desiredSubregion&&!desiredHotel)return;
  const region=form.elements.region,subregion=form.elements.subregion,hotel=form.elements.hotel,extras=form.querySelector('details.extras');
  if(!region||!subregion||!hotel||typeof catalogs.refreshDestination!=='function')return;
  if(extras)extras.open=true;
  try{await catalogs.refreshDestination();}catch(error){console.warn('V2 URL destination catalog sync',error);return;}
  if(!desiredRegion||!hasOption(region,desiredRegion))return;
  region.value=String(desiredRegion);
  try{await catalogs.handleChange({target:region});}catch(error){console.warn('V2 URL region catalog sync',error);return;}
  if(desiredSubregion&&hasOption(subregion,desiredSubregion)){
    subregion.value=String(desiredSubregion);
    try{await catalogs.handleChange({target:subregion});}catch(error){console.warn('V2 URL subregion catalog sync',error);return;}
  }
  if(desiredHotel&&hasOption(hotel,desiredHotel))hotel.value=String(desiredHotel);
}
catalogs.init=async function(){
  await originalInit();
  if(typeof URLSearchParams!=='function')return;
  const sp=new URLSearchParams(window.location.search||''),desiredFrom=sp.get('from'),desiredCountry=sp.get('country'),desiredRegion=sp.get('region'),desiredSubregion=sp.get('subregion'),desiredHotel=sp.get('hotel');
  revealSecondaryOnDesktop(sp);
  await syncPrimary(desiredFrom,desiredCountry);
  await syncAdvanced(desiredRegion,desiredSubregion,desiredHotel);
};
})();
