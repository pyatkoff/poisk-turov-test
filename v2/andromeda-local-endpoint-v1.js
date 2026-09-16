(function(root){'use strict';
if(!root||!root.location||!root.V2_CONFIG)return;
const l=root.location;if(l.protocol!=='https:'||l.hostname!=='anytoour.ru'||!/^\/_preview\/search3-local-candidate(?:\/|$)/.test(l.pathname||''))return;
if(typeof root.V2_CONFIG.andromedaApi==='string'&&root.V2_CONFIG.andromedaApi!=='')return;
root.V2_CONFIG.andromedaApi='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php';
})(typeof window!=='undefined'?window:globalThis);
