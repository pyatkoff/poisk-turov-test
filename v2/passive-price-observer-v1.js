(function(){'use strict';
if(window.V2PassivePriceObserver)return;
const searches=new Map(),MAX_ATTEMPTS=3;
function snapshot(searchId,allowForm){
  const lifecycle=window.V2SearchLifecycle;
  let raw=lifecycle&&Number(lifecycle.searchId)===searchId?lifecycle.snapshot:null;
  if(!raw&&allowForm){
    const form=document.getElementById('tourSearch');if(!form)return null;
    const f=new FormData(form);
    raw={departureId:f.get('from'),countryId:f.get('country'),adults:f.get('count_people')||2,childs:f.getAll('child_age[]')};
  }
  if(!raw)return null;
  const payload={searchId,departureId:Number(raw.departureId),countryId:Number(raw.countryId),adults:Number(raw.adults||2),childs:Array.isArray(raw.childs)?raw.childs.map(Number):[]};
  if(![payload.searchId,payload.departureId,payload.countryId].every(n=>Number.isSafeInteger(n)&&n>0)||!Number.isInteger(payload.adults)||payload.adults<1||payload.adults>6||payload.childs.length>3||payload.childs.some(n=>!Number.isInteger(n)||n<0||n>17))return null;
  return payload;
}
function remember(id,allowForm){
  if(searches.has(id))return searches.get(id);
  const payload=snapshot(id,allowForm);if(!payload)return null;
  const state={payload,revision:0,saved:0,attempts:0,inFlight:false,timer:0};
  searches.set(id,state);return state;
}
async function persist(state){
  if(state.inFlight||state.timer||state.saved>=state.revision||state.attempts>=MAX_ATTEMPTS)return;
  state.inFlight=true;state.attempts++;
  const revision=state.revision;
  let saved=false;
  try{
    const response=await fetch('/data/observe-search-v1.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',keepalive:true,body:JSON.stringify(state.payload)});
    const result=await response.json();
    if(!response.ok||!result||result.ok!==true||result.persisted!==true)throw new Error('Price persistence was not confirmed');
    state.saved=revision;state.attempts=0;saved=true;
  }catch(error){console.warn('price observer',error);}
  finally{state.inFlight=false;}
  if(state.saved>=state.revision)return;
  // A continuation arriving during the POST must get its own trusted refetch.
  if(saved){persist(state);return;}
  if(state.attempts<MAX_ATTEMPTS)state.timer=setTimeout(()=>{state.timer=0;persist(state);},1000*Math.pow(2,state.attempts-1));
}
function send(searchId,continued){
  const id=Number(searchId)||0;if(!id)return false;
  const state=remember(id,false);if(!state)return false;
  if(continued||state.revision===0){state.revision++;if(!state.inFlight)state.attempts=0;}
  persist(state);return true;
}
window.addEventListener('v2:search-started',event=>{const id=Number(event&&event.detail&&event.detail.searchId)||0;if(id)remember(id,true);});
window.addEventListener('v2:search-complete',event=>send(event&&event.detail&&event.detail.searchId,false));
window.addEventListener('v2:search-continued',event=>send(event&&event.detail&&event.detail.searchId,true));
window.V2PassivePriceObserver={send,version:2};
})();
