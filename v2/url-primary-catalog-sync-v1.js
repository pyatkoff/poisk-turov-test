(function(){'use strict';
const catalogs=window.V2Catalogs,form=document.getElementById('tourSearch');if(!catalogs||!form||typeof catalogs.init!=='function'||typeof catalogs.handleChange!=='function')return;
const originalInit=catalogs.init.bind(catalogs);
function hasOption(select,value){return !!select&&Array.from(select.options||[]).some(option=>String(option.value)===String(value));}
function ensureTemporaryOption(select,value){if(!select||!value||hasOption(select,value))return;const option=document.createElement('option');option.value=String(value);option.textContent='ID '+String(value);select.appendChild(option);}
catalogs.init=async function(){
  await originalInit();
  if(typeof URLSearchParams!=='function')return;
  const sp=new URLSearchParams(window.location.search||''),desiredFrom=sp.get('from'),desiredCountry=sp.get('country');
  if(!desiredFrom||!desiredCountry)return;
  const from=form.elements.from,country=form.elements.country;if(!from||!country||!hasOption(from,desiredFrom))return;
  if(String(from.value)===String(desiredFrom)){
    if(hasOption(country,desiredCountry))country.value=String(desiredCountry);
    return;
  }
  from.value=String(desiredFrom);
  ensureTemporaryOption(country,desiredCountry);
  country.value=String(desiredCountry);
  try{await catalogs.handleChange({target:from});}catch(error){console.warn('V2 URL primary catalog sync',error);}
};
})();
