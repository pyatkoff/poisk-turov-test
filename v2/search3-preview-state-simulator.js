(function(){'use strict';
/* Preview-only visual QA helper. No network calls, no lead writes. */
const params=new URLSearchParams(location.search),requested=String(params.get('search3_state')||'').toLowerCase();
if(!['sending','success','error'].includes(requested))return;
let applied=false;
function apply(){if(applied)return;const form=document.querySelector('#selectedTour .lead-form');if(!form)return;applied=true;const event=requested==='sending'?'lead-started':requested==='success'?'lead-success':'lead-error';window.dispatchEvent(new CustomEvent('v2:'+event,{detail:{previewSimulation:true}}));}
window.addEventListener('v2:tour-selected',()=>setTimeout(apply,0));
window.addEventListener('v2:booking-review',()=>setTimeout(apply,0));
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',apply,{once:true});else apply();
})();
