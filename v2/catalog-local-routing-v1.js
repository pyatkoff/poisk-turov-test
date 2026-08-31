(function(){'use strict';
async function local(action,params){
    let url='/data/destinations-v1.php',q=new URLSearchParams({action});
    if(action==='countries'&&params&&params.departureId)q.set('departureId',params.departureId);
    if(action==='regions'&&params&&params.countryId)q.set('countryId',params.countryId);
    if(action==='subregions'&&params&&params.regionId)q.set('regionId',params.regionId);
    if(action==='hotels'){
        url='/data/hotels-select-v1.php';q=new URLSearchParams();
        if(params&&params.countryId)q.set('countryId',params.countryId);
        if(params&&params.regionId)q.set('regionId',params.regionId);
        const form=document.getElementById('tourSearch'),subregion=params&&params.subregionId||form&&form.elements.subregion&&form.elements.subregion.value||'';
        if(subregion)q.set('subregionId',subregion);
        if(params&&params.category)q.set('category',params.category);
        if(params&&params.rating)q.set('rating',params.rating);
        if(params&&Array.isArray(params.types)&&params.types[0])q.set('type',params.types[0]);
        q.set('limit',String(Math.min(100,Number(params&&params.limit)||100)));
    }
    const r=await fetch(url+'?'+q.toString(),{headers:{Accept:'application/json'},credentials:'same-origin'});
    if(!r.ok)throw new Error('local '+action+' '+r.status);
    const d=await r.json();
    if(!d||!d.ok||!Array.isArray(d.items))throw new Error('invalid local '+action);
    return d.items;
}
window.V2LocalCatalogApi={load:local,version:1};
})();
