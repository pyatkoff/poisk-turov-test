'use strict';
// SAMO/Andromeda is temporarily paused; its optional search and quote gates stay closed.
(function(){
 const dataset=window.document?.currentScript?.dataset,path=window.location?.pathname||'';
 const productionPath=dataset?.search3Production==='enabled'&&(path==='/poisk-turov/'||path==='/poisk-turov/index.php');
 const query=productionPath?new URLSearchParams(window.location?.search||''):null,scenarios=query?.getAll('scenario')||[];
 const production=productionPath&&![...query.keys()].some(key=>key.startsWith('scenario['))&&(scenarios.length===0||scenarios.length===1&&scenarios[0]==='live');
 const next=/^\/_preview\/search3-next-candidate\/visual-search\//.test(path);
 const config={api:'/api-v2.php',leadApi:'../preview-lead-disabled.php',andromedaApi:null,andromedaQuoteApi:null,anexApi:'/_preview/search3-anex-candidate/api-anex-search3-preview.php',
  search3TVSessionEnabled:dataset?.search3TvSession==='enabled'&&(next||production),
  search3MessengerEnabled:dataset?.search3Messenger==='enabled'&&(next||production),
  localTvCatalogEnabled:dataset?.localTvCatalog==='enabled'&&(/^\/_preview\/search3-(?:next|local)-candidate\//.test(path)||production)};
 if(production){config.search3ProductionEnabled=true;config.search3PhoneLeadEnabled=false;}
 window.V2_CONFIG=config;
})();
