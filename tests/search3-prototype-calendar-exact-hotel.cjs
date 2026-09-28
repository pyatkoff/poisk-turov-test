'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const bridge = fs.readFileSync(path.join(root, 'v2/prototype-search/calendar-exact-hotel-v1.js'), 'utf8');
const index = fs.readFileSync(path.join(root, 'v2/prototype-search/index.php'), 'utf8');

assert.ok(index.includes('rehydration-retention-v1.js'), 'rehydration bridge remains installed');
assert.ok(index.includes('calendar-exact-hotel-v1.js'), 'exact-hotel calendar bridge is installed');
assert.ok(index.indexOf('rehydration-retention-v1.js') < index.indexOf('calendar-exact-hotel-v1.js'), 'exact-hotel bridge stacks after rehydration retention');

function contextHarness(onFetch) {
  const fetchCalls = [], events = [], updates = [];
  let broadCalendarCalls = 0, currentSearchId = 11;
  const trip = {origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-25',minNights:7,maxNights:7,adults:2,ages:[]};
  const base = {
    search(search, callback) {
      callback({type:'results',hotels:[{id:501,legacyIds:['102','101'],offers:[]}]});
      callback({type:'complete'});
      return Promise.resolve(true);
    },
    resumeCached(search, callback) {
      callback({type:'results',hotels:[{id:501,legacyIds:['101','102'],offers:[]}]});
      return Promise.resolve(true);
    },
    lookupHotels() { return Promise.resolve([{id:601,legacyIds:['201'],offers:[]}]); },
    restoreHotel() { return Promise.resolve({id:701,legacyIds:['301'],offers:[]}); },
    savedHotels() { return Promise.resolve([]); },
    calendarPrices() { broadCalendarCalls++; return Promise.resolve({hotels:[{id:999}],observations:[{date:trip.from,price:1}],partial:false}); },
    params(search, legacyIds, filters) {
      return {scopeVersion:1,departureId:'1',countryId:String(search.country),dateFrom:search.from,dateTo:search.to,nightsFrom:search.minNights,nightsTo:search.maxNights,adults:search.adults,childs:[...search.ages],hotelIds:[...legacyIds],hotelId:Number(filters.hotelId)||0};
    },
    sameScope(request, scope) { return JSON.stringify(request) === JSON.stringify(scope); },
    project(list) { return list.map(row=>({id:Number(row.anytourHotelId),legacyIds:[...(row.canonicalLegacyIds||[])],offers:[]})); },
    get searchId() { return currentSearchId; },
    get currentSupplierScope() { return {hotel:'test'}; }
  };
  Object.freeze(base);
  const context = {
    console,
    structuredClone,
    DOMException,
    setTimeout,
    clearTimeout,
    AnyTourPrototypeData: base,
    AnyTourLocalDbProviderV1: {parse(data){ return data.parsed; }},
    fetch: async (url, options) => {
      const body = JSON.parse(options.body);fetchCalls.push({url,body});
      const requested = body.params;
      if (onFetch) {
        const response = await onFetch({requested, options, window:fetchCalls.length});
        if (response) return response;
      }
      return {ok:true,json:async()=>({ok:true,data:{scope:structuredClone(requested),parsed:{hotels:[{
        anytourHotelId:501,
        hotel:{id:501,name:'Exact Hotel'},
        offers:[{legacyHotelId:Number(requested.hotelIds[0]),tour:{id:'t1',price:100000,date:requested.dateFrom,nights:7}}]
      }]}}})};
    }
  };
  context.window = context;
  vm.createContext(context);
  vm.runInContext(bridge, context);
  return {context,base,trip,fetchCalls,events,updates,get broadCalendarCalls(){return broadCalendarCalls;},setSearchId(value){currentSearchId=value;}};
}

(async()=>{
  const h=contextHarness(), data=h.context.AnyTourPrototypeData;
  assert.equal(h.context.AnyTourPrototypeCalendarExactHotelV1.version,1);
  assert.equal(data.__calendarExactHotelV1,true);

  await data.search(h.trip,event=>h.events.push(event));
  assert.equal(h.events.filter(event=>event.type==='results').length,1,'search events still reach the app');

  const result=await data.calendarPrices(h.trip,h.trip.from,h.trip.to,new AbortController().signal,{hotelId:501},snapshot=>h.updates.push(snapshot));
  assert.equal(h.broadCalendarCalls,0,'exact hotel never delegates to the broad calendar reader');
  assert.equal(h.fetchCalls.length,2,'25-day exact hotel calendar uses bounded LOCAL windows');
  assert.deepEqual(h.fetchCalls.map(call=>Array.from(call.body.params.hotelIds)),[['101','102'],['101','102']]);
  assert.deepEqual(h.fetchCalls.map(call=>[call.body.params.dateFrom,call.body.params.dateTo]),[['2026-10-01','2026-10-22'],['2026-10-23','2026-10-25']]);
  assert.equal(result.partial,false);assert.equal(result.observations.length,0,'destination observation aggregate is not substituted for an exact hotel');
  assert.ok(result.hotels.length===2&&result.hotels.every(row=>row.id===501));
  assert.ok(h.updates.length===2&&h.updates.every(update=>update.observations.length===0));

  // A date window may contain prices for only one of a hotel's accepted IDs.
  // Reopening must still request all resolved IDs, including IDs without prices.
  const repeatBefore=h.fetchCalls.length;
  await data.calendarPrices(h.trip,h.trip.from,h.trip.from,new AbortController().signal,{hotelId:501},()=>{});
  assert.equal(h.fetchCalls.length,repeatBefore+1);
  assert.deepEqual(Array.from(h.fetchCalls.at(-1).body.params.hotelIds),['101','102'],
    'partial calendar observations must not narrow the resolved hotel identity');

  const broad=await data.calendarPrices(h.trip,h.trip.from,h.trip.from,new AbortController().signal,{},()=>{});
  assert.equal(h.broadCalendarCalls,1,'non-hotel calendars delegate unchanged');
  assert.equal(broad.hotels[0].id,999);

  const beforeMissing=h.fetchCalls.length;
  const missing=await data.calendarPrices(h.trip,h.trip.from,h.trip.from,new AbortController().signal,{hotelId:999},()=>{});
  assert.equal(h.fetchCalls.length,beforeMissing,'missing exact hotel identity does not fall back to broad LOCAL scope');
  assert.equal(h.broadCalendarCalls,1);assert.equal(missing.partial,true);assert.deepEqual(Array.from(missing.hotels),[]);

  await data.lookupHotels('x','4',new AbortController().signal);
  const lookupBefore=h.fetchCalls.length;
  const lookupScoped=await data.calendarPrices(h.trip,h.trip.from,h.trip.from,new AbortController().signal,{hotelId:601},()=>{});
  assert.equal(h.fetchCalls.length,lookupBefore+1,'catalog lookup identity can scope a pre-search hotel calendar');
  assert.deepEqual(Array.from(h.fetchCalls.at(-1).body.params.hotelIds),['201']);
  assert.equal(lookupScoped.partial,true,'wrong canonical hotel response fails closed');
  assert.deepEqual(Array.from(lookupScoped.hotels),[]);

  h.setSearchId(77);
  assert.equal(data.searchId,77,'descriptor cloning preserves live getters from the wrapped data owner');

  const aborted=new AbortController();aborted.abort();
  await assert.rejects(data.calendarPrices(h.trip,h.trip.from,h.trip.from,aborted.signal,{hotelId:501},()=>{}),error=>error.name==='AbortError');

  // Each originally requested date window is independent. A temporary failure
  // must not discard earlier prices or prevent later windows from being read.
  const rangeTo='2026-11-20';
  const windows=[['2026-10-01','2026-10-22'],['2026-10-23','2026-11-13'],['2026-11-14','2026-11-20']];
  for (const failedWindow of [1,2,3]) {
    const partial=contextHarness(({window})=>window===failedWindow?{ok:false,status:503}:null);
    const exact=partial.context.AnyTourPrototypeData;
    await exact.search(partial.trip,()=>{});
    const recovered=await exact.calendarPrices(partial.trip,partial.trip.from,rangeTo,new AbortController().signal,
      {hotelId:501},snapshot=>partial.updates.push(snapshot));
    assert.equal(partial.fetchCalls.length,3,`failed window ${failedWindow} must not suppress later dates`);
    assert.deepEqual(partial.fetchCalls.map(call=>[call.body.params.dateFrom,call.body.params.dateTo]),windows,
      'no failed-window retries, overlap or dates outside the requested range');
    assert.ok(partial.fetchCalls.every(call=>call.url==='/_preview/search3-local-candidate/data/search3-local-results-read-v1.php'));
    assert.deepEqual(partial.fetchCalls.map(call=>Array.from(call.body.params.hotelIds)),[['101','102'],['101','102'],['101','102']]);
    assert.equal(partial.broadCalendarCalls,0,'recovery cannot substitute destination-wide observations');
    assert.equal(recovered.hotels.length,2,'successful windows before and after the failure remain available');
    assert.ok(recovered.hotels.every(row=>row.id===501));
    assert.equal(recovered.partial,true,'a later success does not erase the partial-state flag');
    assert.equal(partial.updates.length,3);
    assert.deepEqual(partial.updates.map(update=>update.partial),windows.map((_,i)=>i+1>=failedWindow));
    assert.deepEqual(partial.updates.map(update=>update.hotels.length),windows.map((_,i)=>i+1-(i+1>=failedWindow?1:0)),
      'progress snapshots are independent and retain all successfully read windows');
  }

  for (const kind of ['network','invalid-json','wrong-hotel','all-failed','empty']) {
    const partial=contextHarness(({requested,window})=>{
      if (kind==='all-failed') return {ok:false,status:503};
      if (kind==='empty') return {ok:true,json:async()=>({ok:true,data:{scope:requested,parsed:{hotels:[]}}})};
      if (window!==2) return null;
      if (kind==='network') throw new TypeError('fixture network failure');
      if (kind==='invalid-json') return {ok:true,json:async()=>{throw new SyntaxError('fixture JSON failure');}};
      return {ok:true,json:async()=>({ok:true,data:{scope:requested,parsed:{hotels:[{
        anytourHotelId:999,hotel:{id:999},offers:[{legacyHotelId:101,tour:{price:1}}]
      }]}}})};
    });
    const exact=partial.context.AnyTourPrototypeData;
    await exact.search(partial.trip,()=>{});
    const recovered=await exact.calendarPrices(partial.trip,partial.trip.from,rangeTo,new AbortController().signal,{hotelId:501});
    assert.equal(partial.fetchCalls.length,3,`${kind}: bounded independent windows are all attempted once`);
    assert.equal(recovered.hotels.length,['all-failed','empty'].includes(kind)?0:2);
    assert.ok(recovered.hotels.every(row=>row.id===501),'wrong-hotel prices never enter the calendar');
    assert.equal(recovered.observations.length,0);
    assert.equal(recovered.partial,kind!=='empty','a valid empty result and a failed read stay distinct');
  }

  // Authorization failures and a rate-limit apply to the reader, not one date
  // window. Stop rather than continuing the same denied/limited request scope.
  for (const status of [400,401,403,404,422,429]) for (const stopWindow of [1,2]) {
    const stopped=contextHarness(({window})=>window===stopWindow?{ok:false,status}:null);
    const exact=stopped.context.AnyTourPrototypeData;
    await exact.search(stopped.trip,()=>{});
    const result=await exact.calendarPrices(stopped.trip,stopped.trip.from,rangeTo,new AbortController().signal,
      {hotelId:501},snapshot=>stopped.updates.push(snapshot));
    assert.equal(stopped.fetchCalls.length,stopWindow,`HTTP ${status}: no further calendar requests`);
    assert.equal(result.partial,true);
    assert.equal(result.hotels.length,stopWindow-1,'already received prices remain available');
    assert.equal(stopped.updates.at(-1).partial,true);
  }

  for (const abortKind of ['response','error','progress']) {
    const controller=new AbortController();
    const cancelled=contextHarness(()=>{
      if (abortKind==='response') controller.abort();
      if (abortKind==='error') throw new DOMException('Fixture abort','AbortError');
      return null;
    });
    const exact=cancelled.context.AnyTourPrototypeData;
    await exact.search(cancelled.trip,()=>{});
    await assert.rejects(exact.calendarPrices(cancelled.trip,cancelled.trip.from,rangeTo,controller.signal,
      {hotelId:501},snapshot=>{
        cancelled.updates.push(snapshot);
        if (abortKind==='progress') controller.abort();
      }),error=>error.name==='AbortError');
    assert.equal(cancelled.fetchCalls.length,1,'cancellation never starts a later window');
    assert.equal(cancelled.updates.length,abortKind==='progress'?1:0,'cancelled responses cannot publish late prices');
  }

  console.log('SEARCH3_CALENDAR_EXACT_HOTEL_OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
