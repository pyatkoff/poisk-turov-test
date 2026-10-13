'use strict';
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
const {test} = require('node:test');
const {JSDOM} = require('jsdom');
const builder = require('../scripts/build/search3_design_v2_native.cjs');
const source = fs.readFileSync(require.resolve('../src/search3-design-v2/bridge.js'), 'utf8');
const bootstrap = fs.readFileSync(require.resolve('../src/search3-design-v2/bootstrap.js'), 'utf8');
const trip = {origin:'Москва', country:'4', from:'2026-10-25', to:'2026-10-31', minNights:7, maxNights:7, adults:2, ages:[0,17]};
const deferred = () => {let resolve, reject; const promise = new Promise((a,b) => {resolve=a; reject=b;}); return {promise,resolve,reject};};
const tick = () => new Promise(setImmediate);
const startupShell = '<div class="st-app st-native-startup" data-native-startup><section role="status" aria-live="polite" aria-atomic="true" data-native-startup-status><h1 data-native-startup-heading>Готовим поиск туров</h1><p data-native-startup-message>Загружаем города вылета и направления…</p><button type="button" data-native-startup-retry hidden>Обновить страницу</button></section></div>';
function bootstrapDOM() {
  return new JSDOM('<main id="design-v2-native-root" aria-busy="true"><div id="b4">'+startupShell+'<div data-variant="Мобильная версия"><div class="st-mobile" data-product hidden><main class="st-main"></main></div></div><div data-variant="На компьютере"><div data-desktop-mount></div></div></div></main>',{runScripts:'outside-only'});
}
function harness(extra = {}, query = '') {
  const context = {URLSearchParams, Date, Promise, console}; context.window = context;
  vm.createContext(context); vm.runInContext(source, context);
  const calls = [], streams = [];
  const data = {catalog:{departures:[{name:'Москва'},{name:'Калининград'}],countries:[{id:4,name:'Турция'}],mealPlans:[{id:'ai',nameRu:'Всё включено'}]},
    text:row=>row.name, params(){return {};}, async init(){return {origin:'Москва'};}, stop(){},
    async search(search, callback, ids, filters){calls.push({search,ids,filters}); streams.push(callback);}, ...extra};
  const bridge = context.AnyTourDesignV2Bridge.create({data,location:{search:query}});
  return {bridge,data,calls,streams,context};
}
test('passive URL hydration retains exact ages, hotel constraints and full-price budget', async () => {
  const h = harness({}, '?origin=Москва&country=4&from=2026-10-25&to=2026-10-31&ages=0,17&hotelIds=7|9&hotelId=7&stars=4|5&meals=Всё включено&resorts=Кемер&min=99000.50&max=112000&searched=1');
  const initial = await h.bridge.init();
  assert.deepEqual(Array.from(initial.search.ages),[0,17]); assert.deepEqual(Array.from(initial.filters.hotelIds),[7,9]);
  assert.equal(initial.filters.min,99000.5); assert.equal(initial.filters.max,112000); assert.equal(h.calls.length,0);
  for (const query of ['?hotelIds=1|bad','?min=120000&max=100000','?stars=6','?meals=Invented','?origin=Invented','?from=2026-02-30']) {
    await assert.rejects(harness({},query).bridge.init());
  }
});
test('loaded calendar pages 8, 14 and 22 days without requests and restores the selected page', async () => {
  const frontend=fs.readFileSync(require.resolve('../src/search3-design-v2/frontend.js'),'utf8');
  const add=(day,n)=>new Date(Date.parse(day+'T12:00:00Z')+n*86400000).toISOString().slice(0,10);
  for(const length of [8,14,22]){
    const from='2026-10-01',to=add(from,length-1),selectedDate=to;
    const parts=builder.extract(),dom=new JSDOM(builder.documentHTML(parts.markup),{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'}),w=dom.window;
    w.lucide={createIcons(){}};w.eval(frontend);
    const root=w.document.querySelector('[data-variant="Мобильная версия"] [data-product]');root.hidden=false;
    const filters={hotelId:0,hotelIds:[],q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false};
    const search={origin:'Москва',country:'4',from,to,minNights:7,maxNights:7,adults:2,ages:[]};
    const offer={key:'fixture:last:'+length,hotelId:1,day:to,returnDay:add(to,7),nights:7,adults:2,ages:[],room:'Fixture room',meal:'Fixture meal',mealFacet:'Fixture meal',operator:'Fixture',provider:'tourvisor',total:100000+length,search,raw:{fixture:true}};
    const state={screen:'results',search,filters,hotels:[{id:1,name:'Fixture hotel',country:'4',location:'Fixture',stars:4,photos:[],amenities:[],offers:[offer]}],status:'complete',message:'',sort:'recommended',favorites:[],onlyFavorites:false,hotelId:null,selected:null,selectedDate,calendarOpen:true,hasSearched:true};
    let externalCalls=0;const bridge={async init(){return{dictionaries:{origins:['Москва'],countries:[{id:'4',name:'Турция'}],meals:[]},requestedSearch:false};},async search(){externalCalls++;throw new Error('Calendar paging must stay local');}};
    let owner=w.AnyTourDesignV2Native.mount({root,bridge,initialState:state});await owner.ready;
    const dates=()=>[...root.querySelectorAll('[data-native-day]')].map(node=>node.dataset.nativeDay);
    assert.equal(dates().at(-1),to,`Restored selected day is visible for ${length} days`);
    assert.equal(root.querySelector('[data-native-day="'+to+'"]').getAttribute('aria-pressed'),'true');
    assert.equal(dates().length,length%7||7,'Last calendar page is complete or the exact partial remainder');
    const prev=root.querySelector('[data-native-calendar-page="prev"]');prev.focus();prev.click();
    assert.equal(dates().length,7);assert.equal(w.document.activeElement.matches('[data-native-calendar-page]:not(:disabled)'),true,'Paging preserves keyboard focus on an enabled control');
    root.querySelector('[data-native-calendar-page="next"]').click();assert.equal(dates().at(-1),to);
    const retained=owner.getState();owner.destroy();owner=w.AnyTourDesignV2Native.mount({root,bridge,initialState:retained});await owner.ready;
    assert.equal(dates().at(-1),to,'Responsive remount retains the selected page');
    retained.search={...search,to:add(from,6)};retained.selectedDate=null;owner.render();
    assert.deepEqual(dates(),Array.from({length:7},(_,i)=>add(from,i)),'A new range clamps the old page to its first valid page');
    assert.equal(externalCalls,0,'Paging, selection restore, remount and clamp make no supplier/calendar call');
    owner.destroy();w.close();
  }
});
test('large loaded result sets render in bounded local batches', async () => {
  const frontend=fs.readFileSync(require.resolve('../src/search3-design-v2/frontend.js'),'utf8');
  const parts=builder.extract(),dom=new JSDOM(builder.documentHTML(parts.markup),{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'}),w=dom.window;
  w.lucide={createIcons(){}};w.eval(frontend);
  const root=w.document.querySelector('[data-variant="Мобильная версия"] [data-product]');root.hidden=false;
  const filters={hotelId:0,hotelIds:[],q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false};
  const hotels=Array.from({length:55},(_,index)=>{const id=index+1,offer={key:'fixture:batch:'+id,hotelId:id,day:trip.from,returnDay:'2026-11-01',nights:7,adults:2,ages:[],room:'Fixture room',meal:'Fixture meal',mealFacet:'Fixture meal',operator:'Fixture',provider:'tourvisor',total:100000+index,search:trip,raw:{fixture:true}};return{id,name:'FICTIONAL loaded hotel '+String(id).padStart(2,'0'),country:'4',location:'Fixture',stars:4,photos:[],amenities:[],offers:[offer]};});
  const state={screen:'results',search:trip,filters,hotels,status:'complete',message:'',sort:'recommended',favorites:[],onlyFavorites:false,hotelId:null,selected:null,selectedDate:null,calendarOpen:false,hasSearched:true};
  let externalCalls=0;const bridge={async init(){return{dictionaries:{origins:['Москва'],countries:[{id:'4',name:'Турция'}],meals:[]},requestedSearch:false};},async search(){externalCalls++;throw new Error('Local result reveal must not search');}};
  const owner=w.AnyTourDesignV2Native.mount({root,bridge,initialState:state});await owner.ready;
  assert.equal(root.querySelectorAll('.st-hotel-card').length,24);assert.equal(root.querySelector('[data-result-count]').textContent,'55 отелей');
  root.querySelector('[data-native-result-more]').click();assert.equal(root.querySelectorAll('.st-hotel-card').length,48);
  const sort=root.querySelector('.st-sort');sort.value='name';sort.dispatchEvent(new w.Event('change',{bubbles:true}));
  assert.equal(root.querySelectorAll('.st-hotel-card').length,24,'A changed result order starts at the first batch');
  root.querySelector('[data-native-result-more]').click();root.querySelector('[data-native-result-more]').click();
  assert.equal(root.querySelectorAll('.st-hotel-card').length,55);assert.equal(root.querySelector('[data-native-result-more]'),null);
  assert.equal(externalCalls,0);assert.equal(owner.getState().resultLimit,72);
  owner.destroy();w.close();
});
test('superseded restoreHotel never dispatches a stale supplier search (75c2513 regression)', async () => {
  const restoration = deferred();
  const h = harness({restoreHotel:()=>restoration.promise}); await h.bridge.init();
  const old = h.bridge.search(trip,{hotelIds:[7]}); const rejection = assert.rejects(old,/заменён/); await tick();
  await h.bridge.search({...trip,from:'2026-11-01',to:'2026-11-07'},{});
  restoration.resolve({id:7,country:'4',legacyIds:['supplier-7']}); await rejection;
  assert.equal(h.calls.length,1); assert.equal(h.calls[0].search.from,'2026-11-01');
});
test('replaced dictionary responses cannot restore an older origin', async () => {
  const pending = new Map(); let generation = 0;
  const h = harness({countries(origin){const epoch=++generation, d=deferred(); pending.set(origin,{d,epoch}); return d.promise.then(rows=>{
    if(epoch!==generation)return null; this.catalog.countries=rows; return {origin};
  });}});
  await h.bridge.init();
  const first = h.bridge.changeOrigin('Калининград'); const rejection=assert.rejects(first,/изменился/); await tick();
  const second = h.bridge.changeOrigin('Москва'); await tick();
  pending.get('Москва').d.resolve([{id:4,name:'Турция'}]); const dictionaries=await second;
  pending.get('Калининград').d.resolve([{id:99,name:'Stale'}]); await rejection;
  assert.equal(dictionaries.countries[0].id,'4'); assert.equal(h.data.catalog.countries[0].id,4);
});
test('durable search subscriptions replay current results and reject older streams', async () => {
  const h = harness(); await h.bridge.init(); const snapshots=[];
  const unsubscribe=h.bridge.subscribe(snapshot=>snapshots.push(snapshot)); await h.bridge.search(trip,{});
  h.streams[0]({type:'results',hotels:[{id:7,country:'4',offers:[]}]}); unsubscribe();
  let current; h.bridge.subscribe(snapshot=>current=snapshot); assert.equal(current.hotels[0].id,7);
  await h.bridge.search({...trip,from:'2026-11-01',to:'2026-11-07'},{});
  h.streams[0]({type:'results',hotels:[{id:99,country:'4',offers:[]}]});
  assert.equal(current.hotels.length,0,'new conditions never inherit previous offer prices'); h.streams[1]({type:'complete'}); assert.equal(current.status,'complete');
  assert.equal(h.calls.length,2); assert.equal(snapshots.length,3);
});
test('late presentation events retain authoritative terminal state and exact offers until a new search', async () => {
  for (const event of [{type:'complete'}, {type:'complete',partial:true,message:'Fixture partial'},
                       {type:'error',message:'Fixture error'}]) {
    const h=harness(),received=[];h.bridge.subscribe(value=>received.push(value));
    await h.bridge.search(trip,{});
    const offer={key:'fixture:exact',total:112000,raw:{id:'provider-7'}};
    const hotel={id:7,country:'4',offers:[offer],raw:{description:'Before enrichment'}};
    h.streams[0]({type:'results',hotels:[hotel]});h.streams[0](event);
    const terminal=h.bridge.getSnapshot();
    for (const late of [{type:'results',hotels:[{...hotel,raw:{description:'After enrichment'}}]},
                       {type:'provider',status:'complete'}, {type:'progress',progress:100}, {type:'loading'}]) {
      h.streams[0](late);const current=h.bridge.getSnapshot();
      assert.equal(current.status,terminal.status);assert.equal(current.message,terminal.message);
      assert.equal(current.error,terminal.error);assert.equal(current.searchGeneration,terminal.searchGeneration);
      assert.strictEqual(current.hotels[0].offers[0],offer);assert.equal(current.hotels[0].offers[0].total,112000);
    }
    assert.equal(h.calls.length,1,'Late metadata does not dispatch another search');
    await h.bridge.search({...trip,from:'2026-11-01',to:'2026-11-07'},{});
    assert.equal(h.bridge.getSnapshot().status,'loading');assert.equal(h.bridge.getSnapshot().message,'');
    assert.equal(h.bridge.getSnapshot().hotels.length,0);assert.equal(h.calls.length,2);
    h.streams[0]({type:'error',message:'Stale terminal'});
    h.streams[0]({type:'results',hotels:[hotel]});
    assert.equal(h.bridge.getSnapshot().status,'loading');assert.equal(h.bridge.getSnapshot().hotels.length,0);
    h.streams[1]({type:'results',hotels:[hotel]});h.streams[1]({type:'progress',progress:20});
    assert.equal(h.bridge.getSnapshot().status,'loading','Pending rows never fabricate completion');
    h.streams[1]({type:'complete',partial:true,message:'New terminal'});
    h.streams[1]({type:'complete'});
    assert.equal(h.bridge.getSnapshot().status,'complete','A fresh explicit terminal event remains authoritative');
    const count=received.length;h.bridge.stop();h.streams[1]({type:'results',hotels:[hotel]});
    assert.equal(h.bridge.getSnapshot().status,'stopped');assert.equal(received.length,count+1);
  }
});
test('viewport remount receives pending results with no repeated supplier start or stale ready effect', async () => {
  const h=harness(); await h.bridge.init();
  const dom=bootstrapDOM();
  const w=dom.window; let change; const media={matches:true,addEventListener(name,callback){change=callback;}};
  w.matchMedia=()=>media; w.AnyTourDesignV2Bridge={create:()=>h.bridge}; const mounts=[];
  w.AnyTourDesignV2Native={mount({root,initialState,onReady}){const d=deferred(),state=initialState||{screen:'results',search:trip,hasSearched:true,status:'loading',hotels:[]};
    const mount={root,state,d,onReady,ready:d.promise,renders:0,getState:()=>state,render(){this.renders++;},destroy(){this.dead=true;}}; mounts.push(mount); return mount;}};
  w.eval(bootstrap); await h.bridge.search(trip,{}); assert.equal(h.calls.length,1);
  const startup=w.document.querySelector('[data-native-startup]');assert.equal(startup.hidden,false);assert.equal(startup.getAttribute('class').includes('st-mobile'),true);
  media.matches=false; change(); assert.equal(mounts.length,2); assert.equal(mounts[0].dead,true); assert.strictEqual(mounts[1].state,mounts[0].state);
  h.streams[0]({type:'results',hotels:[{id:7,country:'4',offers:[]}]}); h.streams[0]({type:'complete'});
  assert.equal(mounts[1].state.hotels[0].id,7); assert.equal(mounts[1].state.status,'complete'); assert.equal(h.calls.length,1);
  mounts[0].d.reject(new Error('Old initialization')); await tick();
  mounts[0].onReady();assert.equal(startup.hidden,false,'stale bound callback cannot remove the latest pending shell');
  assert.equal(w.document.getElementById('design-v2-native-root').getAttribute('aria-busy'),'true');
  assert.equal(mounts[0].root.querySelector('.st-main').textContent,'');
  mounts[1].d.resolve(); await tick(); assert.equal(w.document.getElementById('design-v2-native-root').getAttribute('aria-busy'),'false');
  assert.equal(startup.hidden,true);assert.equal(mounts[1].root.hidden,false);
  // A retained history view of another trip must not consume current results.
  mounts[1].state.search={...trip,from:'2026-09-01',to:'2026-09-07'};
  h.streams[0]({type:'results',hotels:[{id:8,country:'4',offers:[]}]}); assert.equal(mounts[1].state.hotels[0].id,7);
  w.close();
});
test('startup failure keeps donor forms hidden, exposes page reload and rejects stale remount recovery', async () => {
  const dom=bootstrapDOM(),w=dom.window;let change;const media={matches:true,addEventListener(name,callback){change=callback;}};
  w.matchMedia=()=>media;w.AnyTourDesignV2Bridge={create:()=>({subscribe(){}})};const mounts=[];
  w.AnyTourDesignV2Native={mount({root,onReady}){const d=deferred(),mount={root,onReady,d,ready:d.promise,getState:()=>({}),destroy(){}};mounts.push(mount);return mount;}};
  // A minimal document facade observes the actual reload listener without
  // replacing jsdom's non-configurable Location or initiating any request.
  let reloads=0;const context={document:w.document,matchMedia:w.matchMedia,window:{location:{reload(){reloads++;}},AnyTourDesignV2Bridge:w.AnyTourDesignV2Bridge,AnyTourDesignV2Native:w.AnyTourDesignV2Native},Promise};
  vm.createContext(context);vm.runInContext(bootstrap,context);
  const startup=w.document.querySelector('[data-native-startup]'),retry=startup.querySelector('[data-native-startup-retry]');
  assert.equal(retry.hidden,true);media.matches=false;change();assert.equal(startup.hidden,false);
  mounts[1].d.reject(new Error('Private transport detail'));await tick();
  assert.equal(startup.querySelector('[data-native-startup-status]').getAttribute('role'),'alert');assert.equal(retry.hidden,false);
  assert.equal(w.document.querySelector('#design-v2-native-root').getAttribute('aria-busy'),'false');
  assert([...w.document.querySelectorAll('[data-product]')].every(product=>product.hidden));assert(!startup.textContent.includes('Private transport detail'));
  mounts[0].onReady();mounts[0].d.resolve();await tick();assert.equal(startup.hidden,false,'stale resolution cannot cover current initialization failure');
  retry.click();assert.equal(reloads,1,'only the explicit reload action retries startup');
  media.matches=true;change();assert.equal(retry.hidden,true);assert.equal(startup.querySelector('[data-native-startup-status]').getAttribute('role'),'status');
  mounts[2].onReady();assert.equal(startup.hidden,true);assert.equal(mounts[2].root.hidden,false);mounts[2].d.resolve();await tick();w.close();
});
test('dictionary-bound startup gives way to real loading results before an automatic URL search resolves', async () => {
  const dictionaries=deferred(),search=deferred();let starts=0;
  const h=harness({async init(){await dictionaries.promise;return {origin:'Москва'};},async search(){starts++;await search.promise;}},'?searched=1');
  const parts=builder.extract(),dom=new JSDOM(builder.documentHTML(parts.markup),{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/?searched=1',runScripts:'outside-only'}),w=dom.window;
  w.matchMedia=()=>({matches:true,addEventListener(){}});w.AnyTourDesignV2Bridge={create:()=>h.bridge};
  w.eval(fs.readFileSync(require.resolve('../src/search3-design-v2/frontend.js'),'utf8'));w.eval(bootstrap);
  const startup=w.document.querySelector('[data-native-startup]');assert.equal(startup.hidden,false);assert.equal(starts,0);assert([...w.document.querySelectorAll('[data-product]')].every(product=>product.hidden));
  dictionaries.resolve();await tick();await tick();assert.equal(startup.hidden,true);assert.equal(starts,1);
  const product=w.document.querySelector('[data-variant="Мобильная версия"] [data-product]');assert.equal(product.hidden,false);
  assert.equal(product.querySelector('[data-screen="results"]').hidden,false);assert.equal(product.querySelector('[data-source-alert]').hidden,true);
  assert.equal(product.querySelector('[data-result-count]').textContent,'Ищем…');assert.equal(product.querySelector('[data-empty-action]').hidden,true);
  assert.equal(w.document.querySelector('#design-v2-native-root').getAttribute('aria-busy'),'false');search.resolve();await tick();assert.equal(starts,1);w.close();
});
test('exact ANEX choice receipt gates contact review and expiry without submitting', async () => {
  const choiceRef='anex_quote:'+'a'.repeat(64), choice={choiceRef,legs:[{label:'Fixture outbound'},{label:'Fixture return'}]};
  const offer={key:'anex:fixture',hotelId:7,provider:'anex',cached:false,total:100000,day:trip.from,nights:7,adults:2,ages:[0,17],search:trip,
    room:'Fixture room',meal:'Всё включено',raw:{anexKind:'concrete',offerRef:'anex_online:'+'b'.repeat(64),searchRef:'c'.repeat(32),anexGeneration:1,anexLocalHotelId:9}};
  let calculations=0, payloads=0, prices=[];
  const h=harness({async verifyAnexConcrete(value){assert.strictEqual(value,offer);return {state:'current'};},
    async verifyAnexPackage(value,ref){assert.strictEqual(value,offer); if(!ref)return {state:'quote_choices',choices:[choice]};
      assert.equal(ref,choiceRef);calculations++;const result={state:'quote_verified',finalPriceVerified:true,finalPrice:{amount:'112000',currency:'RUB'},choice,choices:[choice],expiresAt:Math.floor(Date.now()/1000)+60,repricing:{enabled:true}};
      prices=[{choiceRef,finalPrice:result.finalPrice}];return result;},anexPackagePrices(){return prices;}});
  h.context.AnyTourPrototypeLead={providerPreviewPayload(receipt){payloads++;assert.deepEqual(Array.from(receipt.ages),[0,17]);assert.equal(receipt.choiceRef,choiceRef);assert.equal(receipt.price,112000);return {delivery:'preview-disabled'};}};
  await h.bridge.init();await h.bridge.search(trip,{});h.streams[0]({type:'results',hotels:[{id:7,country:'4',name:'Fixture hotel',offers:[offer]}]});
  const selected=await h.bridge.quoteOffer(offer.key,{expectedOffer:offer});assert.strictEqual(selected.variants[0],choice);
  await assert.rejects(h.bridge.selectProviderFlight('invented'),/ответа/);
  await h.bridge.selectProviderFlight(choiceRef);assert.equal(calculations,1);assert.throws(()=>h.bridge.previewContact({}),/стоимость/);
  h.bridge.acceptPrice();assert.equal(h.bridge.previewContact({}).delivery,'preview-disabled');assert.equal(payloads,1);
  prices=[];assert.throws(()=>h.bridge.previewContact({}),/подтверждён/);assert.equal(payloads,1);
  await h.bridge.search({...trip,from:'2026-11-01',to:'2026-11-07'},{});assert.equal(h.bridge.getSelection(),null);assert.throws(()=>h.bridge.previewContact({}),/проверьте/);
});
test('quote and follow-up flight promises join responsive mounts and reject stale search delivery', async () => {
  const initialFlights=deferred(), retry=deferred(), tour={id:'fixture-tour',price:100000}, offer={key:'tv:fixture',hotelId:7,provider:'tourvisor',total:100000,raw:{id:tour.id},search:trip};
  let quotes=0, flights=0;
  const h=harness({async quote(value){assert.strictEqual(value,offer);quotes++;return tour;},flights(value){assert.strictEqual(value,tour);flights++;return flights===1?initialFlights.promise:retry.promise;},variantPrice(t,value){assert.strictEqual(t,tour);return value.price;}});
  await h.bridge.init();await h.bridge.search(trip,{});h.streams[0]({type:'results',hotels:[{id:7,country:'4',offers:[offer]}]});
  const quote=h.bridge.quoteOffer(offer.key,{expectedOffer:offer});assert.strictEqual(h.bridge.quoteOffer(offer.key,{expectedOffer:offer}),quote);
  assert.strictEqual(h.bridge.waitSelection(),quote);await tick();assert.equal(quotes,1);assert.equal(flights,1);
  initialFlights.resolve([{price:112000}]);await quote;h.bridge.selectFlight(0);h.bridge.acceptPrice();
  const flight=h.bridge.retryFlights(), rejection=assert.rejects(flight,/изменился/);assert.strictEqual(h.bridge.waitSelection(),flight);
  assert.throws(()=>h.bridge.acceptPrice(),/стоимость/);assert.throws(()=>h.bridge.selectFlight(0),/перелёт/);
  await h.bridge.search({...trip,from:'2026-11-01',to:'2026-11-07'},{});retry.resolve([{price:99000}]);await rejection;
  assert.equal(quotes,1);assert.equal(flights,2);assert.equal(h.bridge.getSelection(),null);
  await assert.rejects(h.bridge.quoteOffer(offer.key,{expectedOffer:{...offer}}),/актуализации/);
});

test('cold dictionary viewport remount starts a requested URL search once', async () => {
  const dictionary=deferred(),h=harness({init:()=>dictionary.promise},'?origin=Москва&country=4&from=2026-10-25&to=2026-10-31&minNights=7&maxNights=7&adults=2&ages=&searched=1');
  const builder=require('../scripts/build/search3_design_v2_native.cjs');
  const dom=new JSDOM(builder.documentHTML(builder.extract().markup),{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/?searched=1',runScripts:'outside-only'}),w=dom.window;
  let change;const media={matches:true,addEventListener(name,callback){change=callback;}};
  w.matchMedia=()=>media;w.AnyTourDesignV2Bridge={create:()=>h.bridge};
  w.eval(fs.readFileSync(require.resolve('../src/search3-design-v2/frontend.js'),'utf8'));w.eval(bootstrap);
  media.matches=false;change();assert.equal(h.calls.length,0);
  dictionary.resolve({origin:'Москва'});await tick();await tick();
  assert.equal(h.calls.length,1,'Only the latest bound mount dispatches the requested search');
  assert.equal(w.document.querySelector('[data-native-startup]').hidden,true,'Working results replace the startup shell');
  await new Promise(resolve=>{w.addEventListener('popstate',resolve,{once:true});w.history.back();});
  assert.equal(new URL(w.location.href).searchParams.get('searched'),null,'Back restores the original unsearched screen');
  media.matches=true;change();await tick();assert.equal(h.calls.length,1,'Back plus responsive remount does not replay the consumed URL search');
  assert.equal(w.document.querySelector('.st-mobile [data-screen="search"]').hidden,false);
  media.matches=false;change();await tick();assert.equal(h.calls.length,1,'A second rotation also preserves the consumed intent');
  w.close();
});


test('rendered startup fixture joins catalogue readiness across responsive mounts', async () => {
  const browserSource=fs.readFileSync(require.resolve('./search3-design-v2-native-browser.cjs'),'utf8');
  const start=browserSource.indexOf('function fixtureBridge('),end=browserSource.indexOf('function donorReferenceHTML()',start);
  assert(start>=0&&end>start);
  const dom=new JSDOM('',{url:'https://fixture.test/',runScripts:'outside-only'}),w=dom.window;
  w.__nativeHoldInit=true;
  w.eval(browserSource.slice(start,end)+';fixtureBridge({trip:'+JSON.stringify(trip)+',hotels:[]});');
  const api=w.AnyTourDesignV2Bridge.create(),first=api.init(),second=api.init();
  assert.strictEqual(first,second,'Pending responsive mounts join the same fixture catalogue');
  w.__nativeFixture.releaseInit();await first;
  assert.strictEqual(api.init(),first,'A ready responsive mount cannot re-enter held startup');
  assert.equal(w.__nativeHoldInit,false);
  w.close();
});
