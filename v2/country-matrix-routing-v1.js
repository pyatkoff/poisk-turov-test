(function(){'use strict';
const rt=window.V2Runtime,local=window.V2LocalCatalogApi&&window.V2LocalCatalogApi.load;if(!rt||!local||typeof rt.api!=='function')return;
const upstream=rt.api.bind(rt);
rt.api=async function(action,params,options){
    if(action==='countries'&&params&&params.departureId&&!params.onlyDirect&&!params.onlyCharter){
        try{
            const items=await local('countries',params);
            if(Array.isArray(items)&&items.length)return items;
        }catch(e){console.warn('local countries',e);}
    }
    return upstream(action,params,options);
};
window.V2CountryMatrixRoutingV1={version:1};
})();
