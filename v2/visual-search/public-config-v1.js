'use strict';
// Selected by the public entry, never by query parameters or browser storage.
(function(root){
 const disabledLead='/_preview/search3-next-candidate/preview-lead-disabled.php';
 const nativeApi='/_preview/search3-anex-candidate/api-anex-search3-preview.php';
 const pairs=[['/poisk-turov/','/visual-search/'],['/_preview/search3-site-candidate/poisk-turov/','/_preview/search3-site-candidate/visual-search/']];
 let value;
 try{value=JSON.parse(root.document?.currentScript?.dataset.search3PublicConfig||'null');}catch{}
 const pair=value&&pairs.find(([page,resources])=>value.publicBase===page&&value.resourceBase===resources);
 const profile=value?.profile,path=String(root.location?.pathname||'');
 const valid=!!pair&&value.schemaVersion===1&&['review','live'].includes(profile)
  &&[pair[0],pair[0]+'index.php'].includes(path)&&(profile!=='live'||pair===pairs[0])
  &&['family','snapshot'].includes(value.scenario)&&Number.isSafeInteger(value.metrikaCounter)&&value.metrikaCounter>=0
  &&value.api==='/api-v2.php'&&value.leadApi===disabledLead
  &&value.anexApi===(profile==='live'?nativeApi:null)&&(profile!=='review'||value.metrikaCounter===0);
 const config=Object.freeze({api:valid&&profile==='live'?value.api:null,leadApi:disabledLead,
  andromedaApi:null,andromedaQuoteApi:null,anexApi:valid&&profile==='live'?nativeApi:null,
  search3TVSessionEnabled:valid&&profile==='live',search3MessengerEnabled:valid&&profile==='live',localTvCatalogEnabled:valid&&profile==='live',
  metrikaCounter:valid&&profile==='live'?value.metrikaCounter:0,search3PublicEntry:valid?profile:'blocked',search3ContactMode:valid?'messenger-only':null,
  search3PublicBase:valid?pair[0]:null,search3PublicResourceBase:valid?pair[1]:null,search3ReviewScenario:valid&&profile==='review'?value.scenario:null});
 const asset=name=>{
  if(!valid||typeof name!=='string'||!name||name.startsWith('/')||name.split('/').some(part=>!part||part==='.'||part==='..')||!/^[A-Za-z0-9._/-]+$/.test(name))throw new Error('Public resource path is unavailable.');
  return pair[1]+name;
 };
 root.V2_CONFIG=config;
 root.AnyTourPublicConfigV1=Object.freeze({valid,profile:valid?profile:'blocked',asset});
})(window);
