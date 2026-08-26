(function(){'use strict';
if(window.V2LeadSearchContext)return;
const originalFetch=window.fetch.bind(window),cfg=window.V2_CONFIG||{};
const leadUrl=new URL(String(cfg.leadApi||'/poisk-turov-test/v2/lead-adapter-v2.php'),location.href);
function snapshot(){const s=window.V2SearchLifecycle&&window.V2SearchLifecycle.snapshot;return s&&typeof s==='object'?s:null;}
function childAgesFrom(s){return(Array.isArray(s&&s.childs)?s.childs:[]).map(Number).filter(v=>Number.isInteger(v)&&v>=0&&v<=17).slice(0,3);}
function enrichPayload(payload,s){const out=Object.assign({},payload||{}),ages=childAgesFrom(s);if(ages.length){out.childAges=ages;out.childs=ages.length;}if(s&&Number.isInteger(Number(s.adults))&&Number(s.adults)>=1&&Number(s.adults)<=6)out.adults=Number(s.adults);return out;}
function requestUrl(input){try{return new URL(typeof input==='string'?input:(input&&input.url)||'',location.href);}catch(e){return null;}}
function isLeadRequest(input,init){const url=requestUrl(input),method=String(init&&init.method||input&&input.method||'GET').toUpperCase();return method==='POST'&&url&&url.origin===leadUrl.origin&&url.pathname===leadUrl.pathname;}
function contextualFetch(input,init){if(!isLeadRequest(input,init)||!init||typeof init.body!=='string')return originalFetch(input,init);try{const data=JSON.parse(init.body);if(!data||typeof data!=='object'||Array.isArray(data))return originalFetch(input,init);const next=Object.assign({},init,{body:JSON.stringify(enrichPayload(data,snapshot()))});return originalFetch(input,next);}catch(e){return originalFetch(input,init);}}
window.fetch=contextualFetch;
window.V2LeadSearchContext={snapshot,childAgesFrom,enrichPayload,isLeadRequest,version:1};
})();
