(function(){'use strict';
const rt=window.V2Runtime;if(!rt||typeof rt.api!=='function')return;
const original=rt.api.bind(rt);
async function local(action,params){const q=new URLSearchParams({action});if(action==='regions'&&params&&params.countryId)q.set('countryId',params.countryId);if(action==='subregions'&&params&&params.regionId)q.set('regionId',params.regionId);const r=await fetch('/data/destinations-v1.php?'+q.toString(),{headers:{Accept:'application/json'},credentials:'same-origin'});if(!r.ok)throw new Error('local '+action+' '+r.status);const d=await r.json();if(!d||!d.ok||!Array.isArray(d.items))throw new Error('invalid local '+action);return d.items;}
rt.api=async function(action,params){if(action==='regions'&&!(params&&params.arrivalId)){try{return await local(action,params);}catch(e){console.warn('local regions',e);}}if(action==='subregions'){try{return await local(action,params);}catch(e){console.warn('local subregions',e);}}return original(action,params);};
})();
