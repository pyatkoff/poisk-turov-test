(function(){'use strict';
if(window.V2Runtime)return;
const listeners=new Map(),state={searchId:0},baseFetch=window.fetch.bind(window);
function actionFrom(input){try{const raw=typeof input==='string'?input:(input&&input.url)||'';if(!raw)return'';const u=new URL(raw,location.href);return u.searchParams.get('action')||'';}catch(e){return'';}}
function emit(action,data,response){const set=listeners.get(action);if(!set||!set.size)return;set.forEach(fn=>{try{fn(data,response);}catch(e){console.warn('V2Runtime listener',action,e);}});}
window.fetch=async function(input,init){const action=actionFrom(input),response=await baseFetch(input,init);if(action){response.clone().json().then(data=>{if(action==='search_start'&&data)state.searchId=Number(data.searchId||0);emit(action,data,response);}).catch(()=>{});}return response;};
function build(action,params){const q=new URLSearchParams({action});Object.entries(params||{}).forEach(([k,v])=>{if(v===''||v===null||v===undefined)return;if(Array.isArray(v)){v.forEach(x=>{if(x!==''&&x!==null&&x!==undefined)q.append(k+'[]',String(x));});}else q.append(k,String(v));});return (window.V2_CONFIG&&window.V2_CONFIG.api||'api.php')+'?'+q.toString();}
async function api(action,params,options){const r=await window.fetch(build(action,params),Object.assign({credentials:'same-origin'},options||{}));const d=await r.json().catch(()=>({}));if(!r.ok){const e=new Error(d&&d.error||('API HTTP '+r.status));e.status=r.status;e.data=d;throw e;}return d;}
function on(action,fn){if(!listeners.has(action))listeners.set(action,new Set());listeners.get(action).add(fn);return()=>{const set=listeners.get(action);if(set)set.delete(fn);};}
function setSearchId(id){state.searchId=Number(id||0);}
window.V2Runtime={api,on,state,setSearchId,build,version:1};
})();