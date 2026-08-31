(function(){'use strict';
const rt=window.V2Runtime;if(!rt||typeof rt.api!=='function')return;
const original=rt.api.bind(rt);
async function local(action,params){
    let url='/data/destinations-v1.php',q=new URLSearchParams({action});
    if(action==='regions'&&params&&params.countryId)q.set('countryId',params.countryId);
    if(action==='subregions'&&params&&params.regionId)q.set('regionId',params.regionId);
    if(action==='hotels'){
        url='/data/hotels-select-v1.php';q=new URLSearchParams();
        if(params&&params.countryId)q.set('countryId',params.countryId);
        if(params&&params.regionId)q.set('regionId',params.regionId);
        if(params&&params.subregionId)q.set('subregionId',params.subregionId);
        if(params&&params.category)q.set('category',params.category);
        if(params&&params.rating)q.set('rating',params.rating);
        if(params&&Array.isArray(params.types)&&params.types[0])q.set('type',params.types[0]);
        q.set('limit',String(Math.min(100,Number(params&&params.limit)||100)));
    }
    const r=await fetch(url+'?'+q.toString(),{headers:{Accept:'application/json'},credentials:'same-origin'});if(!r.ok)throw new Error('local '+action+' '+r.status);const d=await r.json();if(!d||!d.ok||!Array.isArray(d.items))throw new Error('invalid local '+action);return d.items;
}
rt.api=async function(action,params){
    if(action==='regions'&&!(params&&params.arrivalId)){try{return await local(action,params);}catch(e){console.warn('local regions',e);}}
    if(action==='subregions'){try{return await local(action,params);}catch(e){console.warn('local subregions',e);}}
    if(action==='hotels'&&params&&params.countryId&&params.regionId){try{return await local(action,params);}catch(e){console.warn('local hotels',e);}}
    return original(action,params);
};
})();
