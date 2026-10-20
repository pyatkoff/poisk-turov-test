'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require.resolve('../src/search3-design-v2/bridge.js'),'utf8');
const context={URLSearchParams,Date,Promise,console};context.window=context;vm.createContext(context);vm.runInContext(source,context);
const search={origin:'Москва',country:'4',from:'2026-10-25',to:'2026-10-31',minNights:7,maxNights:7,adults:2,ages:[0,17]};
const offer={key:'exact:fixture',hotelId:7,total:105000,provider:'tourvisor',cached:false,raw:{id:'provider-offer'},search,day:search.from,adults:2,ages:search.ages};
let initCalls=0,searchCalls=0,flightCalls=0,leadSubmit=0,acceptedTour=null,resolveQuote=null;
const tour={id:'provider-offer',price:105000},variant={price:112000};
const data={catalog:{departures:[{id:1,name:'Москва'}],countries:[{id:4,name:'Турция'}],mealPlans:[]},text:x=>x.name,
 async init(){initCalls++;return{origin:'Москва'};},params(){return{};},async search(s,callback){searchCalls++;callback({type:'results',hotels:[{id:7,name:'Fixture',country:'4',offers:[offer],legacyIds:['9']}]});callback({type:'complete'});},
 quote(){return new Promise(resolve=>resolveQuote=resolve);},async flights(t){assert.strictEqual(t,tour);flightCalls++;return[variant];},variantPrice(t,v){assert.strictEqual(t,tour);assert.strictEqual(v,variant);return 112000;},
 leadSession(selected){acceptedTour=selected.tour;return{payload(){return{exact:true};},submit(){leadSubmit++;}};},stop(){} };
const bridge=context.AnyTourDesignV2Bridge.create({data,location:{search:'?origin=Москва&country=4&from=2026-10-25&to=2026-10-31&minNights=7&maxNights=7&adults=2&ages=0,17&searched=1'}});
(async()=>{
 const [a,b]=await Promise.all([bridge.init(),bridge.init()]);assert.strictEqual(a,b);assert.equal(initCalls,1);assert.equal(searchCalls,0);assert.equal(a.requestedSearch,true);assert.deepEqual(Array.from(a.search.ages),[0,17]);
 await bridge.search(search,{},()=>{});assert.equal(searchCalls,1);assert.strictEqual(bridge.readOffers(7).offers[0],offer);
 const quotePromise=bridge.quoteOffer(offer.key);resolveQuote(tour);const selection=await quotePromise;assert.strictEqual(selection.tour,tour);assert.equal(flightCalls,1);
 const selected=bridge.selectFlight(0);assert.equal(selected.total,112000);assert.throws(()=>bridge.previewContact({}),/стоимость/);bridge.acceptPrice();bridge.previewContact({});assert.strictEqual(acceptedTour,tour);assert.equal(leadSubmit,0);
 const stale=bridge.quoteOffer(offer.key,{force:true});await bridge.search(search,{},()=>{});resolveQuote(tour);await assert.rejects(stale,/изменился/);assert.equal(flightCalls,1,'stale quote must not cross into flights');
 const bad=context.AnyTourDesignV2Bridge.create({data,location:{search:'?country=4&ages=0,19&searched=1'}});await assert.rejects(bad.init(),/возраст/);
 await terminalDenialChecks();
 assert.equal(leadSubmit,0);console.log('PASS native bridge: once-only passive init, full child identity, exact loaded offer/tour reference, canonical full flight price, stale-generation rejection, no lead submit');
})().catch(e=>{console.error(e);process.exitCode=1;});

async function terminalDenialChecks(){
 let quotes=0,flights=0,payloads=0,mode='quote';
 const allowed={...offer,key:'exact:allowed',raw:{id:'allowed-offer'}};
 const failure=()=>Object.assign(new Error('Онлайн-проверка этого предложения недоступна.'),{retryable:false});
 const adapter={...data,
  async search(s,emit){emit({type:'results',hotels:[{id:7,name:'Fixture',country:'4',offers:[offer,allowed]}]});emit({type:'complete'});},
  async quote(o){quotes++;if(o===offer&&mode==='quote')throw failure();return tour;},
  async flights(){flights++;if(mode==='flights')throw failure();if(mode==='transient'){mode='ready';throw new Error('Временный сбой');}return[variant];},
  leadSession(){payloads++;return{payload:()=>({exact:true})};}
 };
 const value=context.AnyTourDesignV2Bridge.create({data:adapter,location:{search:''}});
 await value.search(search,{});
 await assert.rejects(value.quoteOffer(offer.key),/недоступна/);
 const blocked=value.getSelection();
 assert.strictEqual(blocked.offer,offer);assert.equal(blocked.total,offer.total);assert.equal(blocked.priceAccepted,false);
 assert.equal(blocked.quoteFailure.retryable,false,'Permanent canonical failure retains its classification');
 await assert.rejects(value.quoteOffer(offer.key,{force:true}),/недоступна/);
 await assert.rejects(value.retryFlights());
 for(const action of [()=>value.selectFlight(0),()=>value.deferFlight(),()=>value.acceptPrice(),()=>value.previewContact({})])assert.throws(action);
 assert.deepEqual([quotes,flights,payloads],[1,0,0],'Permanent denial cannot be retried or turned into contact authority');
 await value.quoteOffer(allowed.key);value.selectFlight(0);value.acceptPrice();value.previewContact({});
 assert.deepEqual([quotes,flights,payloads],[2,1,1],'Another exact offer remains usable');

 mode='flights';await value.search(search,{});await value.quoteOffer(offer.key);
 assert.equal(value.getSelection().flightsFailure.retryable,false);
 const before=[quotes,flights,payloads];
 await assert.rejects(value.retryFlights(),/недоступна/);
 await assert.rejects(value.quoteOffer(offer.key,{force:true}),/недоступна/);
 for(const action of [()=>value.selectFlight(0),()=>value.deferFlight(),()=>value.acceptPrice(),()=>value.previewContact({})])assert.throws(action);
 assert.deepEqual([quotes,flights,payloads],before,'Permanent flight denial cannot be cleared through manager/defer or forced quote');

 mode='transient';await value.search(search,{});await value.quoteOffer(offer.key);
 assert.equal(value.getSelection().flightsFailure.retryable,true);
 await value.retryFlights();value.selectFlight(0);value.acceptPrice();value.previewContact({});
 assert.equal(payloads,2,'Transient flight recovery retains the existing successful path');
}
