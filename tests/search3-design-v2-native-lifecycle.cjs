'use strict';
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
const {test} = require('node:test');
const {JSDOM} = require('jsdom');
const source = fs.readFileSync(require.resolve('../src/search3-design-v2/bridge.js'), 'utf8');
const bootstrap = fs.readFileSync(require.resolve('../src/search3-design-v2/bootstrap.js'), 'utf8');
const trip = {origin:'Москва', country:'4', from:'2026-10-25', to:'2026-10-31', minNights:7, maxNights:7, adults:2, ages:[0,17]};
const deferred = () => {let resolve, reject; const promise = new Promise((a,b) => {resolve=a; reject=b;}); return {promise,resolve,reject};};
const tick = () => new Promise(setImmediate);
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
test('viewport remount receives pending results with no repeated supplier start or stale ready effect', async () => {
  const h=harness(); await h.bridge.init();
  const dom=new JSDOM('<main id="design-v2-native-root" aria-busy="true"><div id="b4"><div data-variant="Мобильная версия"><div class="st-mobile" data-product hidden><main class="st-main"></main></div></div><div data-variant="На компьютере"><div data-desktop-mount></div></div></div></main>',{runScripts:'outside-only'});
  const w=dom.window; let change; const media={matches:true,addEventListener(name,callback){change=callback;}};
  w.matchMedia=()=>media; w.AnyTourDesignV2Bridge={create:()=>h.bridge}; const mounts=[];
  w.AnyTourDesignV2Native={mount({root,initialState}){const d=deferred(),state=initialState||{screen:'results',search:trip,hasSearched:true,status:'loading',hotels:[]};
    const mount={root,state,d,ready:d.promise,renders:0,getState:()=>state,render(){this.renders++;},destroy(){this.dead=true;}}; mounts.push(mount); return mount;}};
  w.eval(bootstrap); await h.bridge.search(trip,{}); assert.equal(h.calls.length,1);
  media.matches=false; change(); assert.equal(mounts.length,2); assert.equal(mounts[0].dead,true); assert.strictEqual(mounts[1].state,mounts[0].state);
  h.streams[0]({type:'results',hotels:[{id:7,country:'4',offers:[]}]}); h.streams[0]({type:'complete'});
  assert.equal(mounts[1].state.hotels[0].id,7); assert.equal(mounts[1].state.status,'complete'); assert.equal(h.calls.length,1);
  mounts[0].d.reject(new Error('Old initialization')); await tick();
  assert.equal(w.document.getElementById('design-v2-native-root').getAttribute('aria-busy'),'true');
  assert.equal(mounts[0].root.querySelector('.st-main').textContent,'');
  mounts[1].d.resolve(); await tick(); assert.equal(w.document.getElementById('design-v2-native-root').getAttribute('aria-busy'),'false');
  // A retained history view of another trip must not consume current results.
  mounts[1].state.search={...trip,from:'2026-09-01',to:'2026-09-07'};
  h.streams[0]({type:'results',hotels:[{id:8,country:'4',offers:[]}]}); assert.equal(mounts[1].state.hotels[0].id,7);
  w.close();
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
