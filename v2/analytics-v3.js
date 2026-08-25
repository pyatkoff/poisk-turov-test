(function(){'use strict';
if(window.V2Analytics&&Number(window.V2Analytics.version||0)>=3)return;
const startedAt=new Map(),cfg=window.V2_CONFIG||{};
const SAFE_KEYS=new Set(['searchId','durationMs','resultsCount','sort','tourId','hotelId','hotel','region','country','price','currency','flightIndex','flightNumber','isDefault','continuedResultsCount','phase','errorCode','leadId','deduplicated']);
function primitive(v){return v===null||v===undefined||typeof v==='string'||typeof v==='number'||typeof v==='boolean';}
function safeParams(input){const out={};Object.entries(input||{}).forEach(([k,v])=>{if(!SAFE_KEYS.has(k)||!primitive(v))return;out[k]=typeof v==='string'?v.slice(0,160):v;});return out;}
function metrikaCounter(){const n=Number(cfg.metrikaCounter||0);return Number.isFinite(n)&&n>0?Math.trunc(n):0;}
function ensureMetrika(){const id=metrikaCounter();if(!id)return 0;if(!window.ym){window.ym=function(){(window.ym.a=window.ym.a||[]).push(arguments);};window.ym.l=Date.now();const s=document.createElement('script'),first=document.getElementsByTagName('script')[0];s.async=true;s.src='https://mc.yandex.ru/metrika/tag.js';if(first&&first.parentNode)first.parentNode.insertBefore(s,first);else document.head.appendChild(s);}if(!window.__V2_METRIKA_INIT){window.__V2_METRIKA_INIT=id;try{window.ym(id,'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:false});}catch(e){}}return id;}
function send(name,params){const event={event:'v2_'+name,v2_event:name,v2:params};try{if(Array.isArray(window.dataLayer))window.dataLayer.push(event);}catch(e){}try{const id=ensureMetrika();if(id&&typeof window.ym==='function')window.ym(id,'reachGoal','V2_'+String(name).toUpperCase(),params);}catch(e){}window.dispatchEvent(new CustomEvent('v2:analytics',{detail:{name,params}}));}
function track(name,input){const params=safeParams(input);send(String(name||'event'),params);return params;}
function errMeta(e){return{errorCode:String(e&&e.code||e&&e.name||'ERROR').slice(0,80)};}
ensureMetrika();
window.addEventListener('v2:search-started',e=>{const d=e.detail||{},id=Number(d.searchId||0);if(id)startedAt.set(id,performance.now());track('search_started',{searchId:id});});
window.addEventListener('v2:search-complete',e=>{const d=e.detail||{},id=Number(d.searchId||0),start=startedAt.get(id),items=Array.isArray(d.items)?d.items:[];track('search_completed',{searchId:id,durationMs:start?Math.round(performance.now()-start):0,resultsCount:items.length});if(id)startedAt.delete(id);});
window.addEventListener('v2:search-error',e=>{const d=e.detail||{};track('search_error',Object.assign({searchId:Number(d.searchId||0),phase:String(d.phase||'')},errMeta(d.error)));});
window.addEventListener('v2:search-continued',e=>{const d=e.detail||{},items=Array.isArray(d.items)?d.items:[];track('search_continued',{searchId:Number(d.searchId||0),continuedResultsCount:items.length});});
window.addEventListener('v2:tour-selected',e=>{const t=e.detail&&e.detail.tour||{},h=t.hotel||{};track('tour_selected',{tourId:String(t.id||''),hotelId:String(h.id||''),hotel:String(h.name||t.name||''),region:String(h.region&&h.region.name||''),country:String(h.country&&h.country.name||''),price:Number(t.price||0),currency:'RUB'});});
window.addEventListener('v2:flight-selected',e=>{const d=e.detail||{},f=d.flight||{},forward=Array.isArray(f.forward)?f.forward:[];track('flight_selected',{tourId:String(d.tour&&d.tour.id||''),flightIndex:Number(d.index||0),flightNumber:String(forward[0]&&forward[0].number||''),isDefault:!!f.isDefault});});
window.addEventListener('v2:lead-started',e=>{const d=e.detail||{};track('lead_started',{searchId:Number(d.searchId||0),tourId:String(d.tourId||'')});});
window.addEventListener('v2:lead-success',e=>{const d=e.detail||{};track('lead_success',{searchId:Number(d.searchId||0),tourId:String(d.tourId||''),leadId:Number(d.leadId||0),deduplicated:!!d.deduplicated});});
window.addEventListener('v2:lead-error',e=>{const d=e.detail||{};track('lead_error',Object.assign({searchId:Number(d.searchId||0),tourId:String(d.tourId||'')},errMeta(d.error)));});
document.addEventListener('change',e=>{if(e.target&&e.target.id==='sortResults')track('sort_changed',{sort:String(e.target.value||'price')});});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('.hotel-info-toggle');if(!b||String(b.textContent||'').trim()!=='Об отеле')return;const card=b.closest('.hotel-card');track('hotel_details_opened',{hotelId:String(card&&card.dataset.hotelId||'')});},true);
window.V2Analytics={track,safeParams,ensureMetrika,version:3,metrikaCounter:metrikaCounter()};
})();