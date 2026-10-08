'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');

const root=path.resolve(__dirname,'..');
const source=fs.readFileSync(path.join(root,'v2/prototype-search/config.js'),'utf8');
const sandbox={window:{}};
vm.runInNewContext(source,sandbox,{filename:'v2/prototype-search/config.js'});

const config=JSON.parse(JSON.stringify(sandbox.window.V2_CONFIG));
assert.deepEqual(config,{
  api:'/api-v2.php',
  leadApi:'../preview-lead-disabled.php',
  andromedaApi:null,
  andromedaQuoteApi:null,
  anexApi:'/_preview/search3-anex-candidate/api-anex-search3-preview.php'
});
assert.doesNotMatch(source,/https?:\/\//i);
assert.ok(config.leadApi.endsWith('/preview-lead-disabled.php'),'prototype lead transport must stay disabled');

// Exercise the actual configuration and canonical data owner with intercepted,
// fictional transport. A disabled source must not consume even a next-page call.
async function liveConfiguration(){
  const transport=fixture(),events=[],timers=new Map();let timerId=0;
  const window={location:{href:'https://anytoour.ru/_preview/search3-next-candidate/visual-search/',origin:'https://anytoour.ru'},
    crypto:require('node:crypto').webcrypto,TextEncoder,
    V2Runtime:{setSearchId(){},api:(action,body={})=>transport.json('/api-v2.php',{body:JSON.stringify({...body,action})})},
    Search3CanonicalProfilesV1:{create(publish){const native=new Map();return {
      reset:()=>native.clear(),clearOffers:source=>native.delete(source),refresh:publish,
      upsertLegacyOffer(id,tour,{source}){const rows=native.get(source)||[];rows.push({id,tour});native.set(source,rows);},
      read(rows){const hotels=new Map(rows.map(h=>[h.id,{...h,tours:[...(h.tours||[])]}]));
        for(const group of native.values())for(const {id,tour} of group){const h=hotels.get(id)||{id,name:'Fictional hotel',tours:[]};h.tours.push(tour);hotels.set(id,h);}
        return [...hotels.values()];}
    };}},dispatchEvent(){}};
  const context=vm.createContext({window,URL,URLSearchParams,AbortController,structuredClone,CustomEvent:class{},
    fetch:async(url,options)=>new Response(JSON.stringify(await transport.json(url,options)),{headers:{'Content-Type':'application/json'}}),
    setTimeout(fn){const id=++timerId;timers.set(id,fn);return id;},clearTimeout:id=>timers.delete(id)});
  vm.runInContext(source,context,{filename:'prototype-search/config.js'});
  vm.runInContext(fs.readFileSync(path.join(root,'v2/prototype-search/data.js'),'utf8'),context,{filename:'prototype-search/data.js'});
  const api=window.AnyTourPrototypeData;
  api.catalog.departures=[{id:1,name:'Москва'}];api.catalog.countries=[{id:4,name:'Турция',tourvisorIds:['4']}];
  const poll=async()=>{const [id,fn]=timers.entries().next().value;timers.delete(id);await fn();};
  await api.search(trip,event=>events.push(event));await poll();
  const complete=events.findLast(event=>event.type==='complete');assert(complete,'initial search completes');
  assert.equal(complete.sources.andromeda.status,'skipped');assert.equal(complete.sources.anex.status,'complete');
  const offers=events.filter(event=>Array.isArray(event.hotels)).at(-1).hotels.flatMap(h=>h.offers);
  assert.deepEqual([...new Set(offers.map(o=>o.provider))].sort(),['anex','tourvisor']);
  assert(!transport.calls.some(call=>call.url.includes('andromeda')),'default search never calls SAMO');
  assert.equal(await api.continueSearch(),true,'Tourvisor continuation stays available');
  assert(transport.calls.some(call=>call.action==='search_continue'));
  assert(!transport.calls.some(call=>call.url.includes('andromeda')),'continuation never calls SAMO');
  const tv=offers.find(o=>o.provider==='tourvisor');await api.quote(tv);
  assert(transport.calls.some(call=>call.action==='tour'),'Tourvisor quote still reaches its transport');

  // A previously retained valid SAMO offer also cannot quote while paused.
  // Enable only the fictional search fixture to obtain a current exact offer.
  window.V2_CONFIG.andromedaApi='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php';
  await api.search(trip,event=>events.push(event));await poll();
  const retained=events.filter(event=>Array.isArray(event.hotels)).at(-1).hotels.flatMap(h=>h.offers).find(o=>o.provider==='andromeda');assert(retained);
  window.V2_CONFIG.andromedaApi=null;
  const before=transport.calls.length;
  await assert.rejects(api.verifyAndromeda(retained),error=>error.code==='offer_expired');
  assert.equal(transport.calls.length,before,'paused quote fails locally before any transport call');
  api.stop();
}
liveConfiguration().then(()=>console.log('search3 prototype provider config: PASS; default TV + direct ANEX, SAMO search/continue/quote disabled')).catch(error=>{console.error(error);process.exitCode=1;});
