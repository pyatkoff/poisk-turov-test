(function(){'use strict';
if(window.V2PassivePriceObserver)return;
const sent=new Set();
function context(searchId){const form=document.getElementById('tourSearch');if(!form)return null;const f=new FormData(form),childs=f.getAll('child_age[]').map(v=>Number(v)).filter(v=>Number.isInteger(v)&&v>=0&&v<=17);const payload={searchId:Number(searchId)||0,departureId:Number(f.get('from'))||0,countryId:Number(f.get('country'))||0,adults:Number(f.get('count_people'))||2,childs};return payload.searchId&&payload.departureId&&payload.countryId?payload:null;}
function send(searchId){const payload=context(searchId);if(!payload||sent.has(payload.searchId))return false;sent.add(payload.searchId);const body=JSON.stringify(payload);fetch('/data/observe-search-v1.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',keepalive:true,body}).catch(err=>console.warn('price observer',err));return true;}
window.addEventListener('v2:search-complete',e=>{const id=Number(e&&e.detail&&e.detail.searchId)||0;if(id)send(id);});
window.V2PassivePriceObserver={send,version:1};
})();