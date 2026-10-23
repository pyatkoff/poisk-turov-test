'use strict';
// Execute the actual preparation module; no browser, supplier or bot traffic.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const file=path.resolve(__dirname,'../v2/prototype-search/messenger-context-v1.js');
const calls=[];
const root={fetch(){calls.push('fetch');throw Error('unexpected transport');},localStorage:{setItem(){calls.push('storage');throw Error('unexpected persistence');}},document:new Proxy({},{get(){calls.push('DOM');throw Error('unexpected DOM');}})};
const context=vm.createContext({window:root,Date,WeakMap,Object,Number,Array,String,JSON,Error});
// Reuse exact existing pure presentation owners, rather than another airline,
// airport, placeholder or baggage normalization policy in the new module.
const textLine=fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/data.js'),'utf8').split('\n').find(line=>line.trim().startsWith('const text = ')).trim();
vm.runInContext('window.AnyTourPrototypeData={text:'+textLine.slice('const text = '.length,-1)+'};',context);
vm.runInContext(fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/flight-picker-v18.js'),'utf8'),context);
vm.runInContext(fs.readFileSync(file,'utf8'),context,{filename:file});
const api=root.AnyTourSearch3MessengerContextV1;
let time=1800000000000;
const forward={number:'VF 276',company:{name:'AJet'},departure:{date:'2027-03-29',time:'06:05',town:'Москва',port:{name:'Внуково',id:'VKO'}},arrival:{date:'2027-03-29',time:'10:40',town:'Хургада',port:{name:'Хургада',id:'HRG'}},baggage:0,carryOn:'5 кг',token:'SECRET_LEG'};
const backward={...forward,number:'VF 277',departure:{...forward.arrival,date:'2027-04-05'},arrival:{...forward.departure,date:'2027-04-05'}};
function fixture(){return {
  revision:1,
  hotel:{id:3634,name:'BEACH SAFARI NUBIAN RESORT',country:'2',resort:'Марса-Алам',customerId:'SECRET_CUSTOMER'},
  offer:{key:'anex:SECRET_OFFER_REF',hotelId:3634,provider:'anex',origin:'Москва',day:'2027-03-29',returnDay:'2027-04-05',nights:7,adults:2,ages:[0,0,8],room:'Family Room',meal:'Всё включено',placement:'2AD+3CH',operator:'ANEX',total:200016,
    flightChoiceId:'0',variants:[{price:{value:200016,currency:'RUB'},forward:[structuredClone(forward)],backward:[structuredClone(backward)]},{price:201000,forward:[{...forward,number:'VF 278'}],backward:[{...backward,number:'VF 279'}]}],raw:{searchRef:'SECRET_SEARCH',jwt:'SECRET_TOKEN'},phone:'SECRET_PHONE',utm_source:'SECRET_UTM'},
  price:{state:'checked',currency:'RUB',description:'Цена с выбранными рейсами · сбор уточняется',expiresAt:time/1000+60,finalPriceVerified:true}
};}
const plain=value=>JSON.parse(JSON.stringify(value));
let count=0;
function test(name,fn){fn();count++;console.log('PASS '+name);}
test('Exact chosen trip, repeated infant ages and both flight directions survive',()=>{
  const data=fixture(),before=plain(data),session=api.createSession(()=>data,()=>time),s=session.capture();
  assert.deepEqual(plain(s.trip.childAges),[0,0,8]);assert.equal(s.trip.adults,2);assert.equal(s.trip.departure,'2027-03-29');
  assert.equal(s.stay.room,'Family Room');assert.equal(s.stay.meal,'Всё включено');assert.equal(s.hotel.uiId,3634);assert.equal(s.hotel.namespace,'search3-ui');
  assert.equal(s.trip.stayEndBasis,'shown-stay-end');assert.equal(s.price.amount,'200016');assert.equal(s.price.currency,'RUB');
  assert.equal(s.flights.outward[0].number,'VF 276');assert.equal(s.flights.inward[0].number,'VF 277');assert.equal(s.flights.outward[0].baggage,'без багажа');
  assert.equal(s.flights.outward[0].departure.port,'Внуково');assert.equal(s.flights.outward[0].departure.portCode,'VKO');assert.equal(session.isCurrent(s),true);assert.deepEqual(plain(data),before);
  assert.equal(Object.isFrozen(s),true);assert.equal(Object.isFrozen(s.trip.childAges),true);assert.equal(Object.isFrozen(s.flights.outward[0].departure),true);
  assert.throws(()=>s.trip.childAges.push(1),error=>error.name==='TypeError');
});
test('Browser checked/verified flags never become trusted price or public identifiers',()=>{
  const data=fixture(),s=api.createSession(()=>data,()=>time).capture(),wire=JSON.stringify(s);
  assert.equal(s.price.state,'checked');assert.equal(s.price.finalPriceVerified,false);assert.equal(s.price.authority,'browser-observation-only');
  assert.doesNotMatch(wire,/SECRET_|phone|jwt|searchRef|offerRef|utm_|https?:\/\/|anex_quote:/);
});
test('No implicit first pair and explicit manager continuation',()=>{
  const data=fixture();data.offer.flightChoiceId=null;const session=api.createSession(()=>data,()=>time),s=session.capture();
  assert.equal(s.flights.state,'not-selected');assert.deepEqual(plain(s.flights.outward),[]);assert.equal(s.price.amount,'200016');
  data.offer.hotelFlightDeferred=true;assert.equal(session.isCurrent(s),false);const manager=session.capture();
  assert.equal(manager.flights.state,'manager-deferred');assert.equal(manager.price.basis,'shown-offer');assert.equal(session.isCurrent(manager),true);
});
test('Existing presentation owners retain string and localized carrier/airport/town labels',()=>{
  for(const [company,expected] of [['AJet','AJet'],[{name:'AJet'},'AJet'],[{russianName:'АЖет',name:'AJet'},'АЖет']]){
    const data=fixture(),f=data.offer.variants[0].forward[0];f.company=company;f.departure.town={russianName:'Москва',name:'Moscow'};f.departure.port={russianName:'Внуково',name:'Vnukovo',id:'VKO'};
    const s=api.createSession(()=>data,()=>time).capture();assert.equal(s.flights.outward[0].carrier,expected);assert.equal(s.flights.outward[0].departure.town,'Москва');assert.equal(s.flights.outward[0].departure.port,'Внуково');
  }
});
test('Placeholder schedule and allowance remain unknown; real midnight and zero baggage stay explicit',()=>{
  const data=fixture(),f=data.offer.variants[0].forward[0];f.number='VF 000';f.departure.time='00:00';f.arrival.time='00:00';f.baggage=0;f.carryOn=0;
  const session=api.createSession(()=>data,()=>time),s=session.capture();assert.equal(s.flights.outward[0].departure.time,null);assert.equal(s.flights.outward[0].arrival.time,null);assert.equal(s.flights.outward[0].baggage,null);assert.equal(s.flights.outward[0].carryOn,null);
  assert.equal(s.flights.inward[0].baggage,'без багажа');f.number='VF 276';f.arrival.time='04:00';const real=session.capture();assert.equal(real.flights.outward[0].departure.time,'00:00');assert.equal(real.flights.outward[0].baggage,'без багажа');assert.equal(real.flights.outward[0].carryOn,'без ручной клади');
});
test('Unknown pair/amount remains unknown, with discussion context preserved',()=>{
  const data=fixture();data.offer.variants[0].price=null;data.price.state='unknown';const s=api.createSession(()=>data,()=>time).capture();
  assert.equal(s.price.amount,null);assert.equal(s.price.state,'unknown');assert.equal(s.flights.outward[0].number,'VF 276');
  data.offer.flightChoiceId=null;data.offer.total=0;assert.equal(api.createSession(()=>data,()=>time).capture().price.amount,null);
});
test('Pair price/currency disagreement is rejected instead of borrowing a total',()=>{
  for(const change of [d=>d.offer.total=201000,d=>d.offer.variants[0].price.currency='USD']){
    const data=fixture();change(data);assert.throws(()=>api.createSession(()=>data,()=>time).capture(),/selected_pair_(amount|currency)_mismatch/);
  }
});
test('Missing, malformed and manager-plus-selected pair never become pair zero',()=>{
  for(const change of [d=>d.offer.flightChoiceId='',d=>d.offer.flightChoiceId='00',d=>d.offer.flightChoiceId='99',d=>d.offer.variants[0].backward=[],d=>d.offer.hotelFlightDeferred=true]){
    const data=fixture();change(data);assert.throws(()=>api.createSession(()=>data,()=>time).capture(),/invalid_selected_pair|ambiguous_flight_selection/);
  }
  const data=fixture();data.offer.flightChoiceId=0;assert.equal(api.createSession(()=>data,()=>time).capture().flights.state,'selected');
});
test('Every changed trip/selected term invalidates a pending producer result',()=>{
  const changes=[d=>d.offer.room='Suite',d=>d.offer.meal='Завтраки',d=>d.offer.adults=3,d=>d.offer.ages=[0,8,0],d=>d.offer.day='2027-03-30',d=>d.offer.returnDay='2027-04-06',d=>d.offer.nights=8,d=>d.offer.origin='Казань',d=>d.offer.search={country:'3'},d=>d.hotel.name='Другой отель',d=>d.price.description='Цена изменилась',d=>{d.offer.flightChoiceId='1';d.offer.total=201000;},d=>d.offer.key='anex:DIFFERENT_REF',d=>d.offer.raw={...d.offer.raw},d=>d.offer.variants[0]={...d.offer.variants[0]},d=>d.revision++];
  for(const change of changes){const data=fixture(),session=api.createSession(()=>data,()=>time),s=session.capture();change(data);assert.equal(session.isCurrent(s),false);}
});
test('Returning to identical terms in a newer selection revision does not revive an old result',()=>{
  const data=fixture(),session=api.createSession(()=>data,()=>time),s=session.capture();data.offer.room='Suite';data.revision++;data.offer.room='Family Room';
  assert.equal(session.isCurrent(s),false);assert.equal(session.isCurrent(session.capture()),true);
});
test('Copied/forged/foreign snapshots cannot complete this session',()=>{
  const data=fixture(),first=api.createSession(()=>data,()=>time),second=api.createSession(()=>data,()=>time),s=first.capture();
  assert.equal(first.isCurrent(plain(s)),false);assert.equal(second.isCurrent(s),false);assert.equal(first.isCurrent(null),false);assert.equal(first.isCurrent('x'),false);
});
test('Missing or primitive selected rows cannot establish an exact-row binding',()=>{
  for(const raw of [undefined,null,'row',1,[]]){const data=fixture();data.offer.raw=raw;assert.throws(()=>api.createSession(()=>data,()=>time).capture(),/missing_selected_row/);}
});
test('Expiry invalidates an in-flight result but keeps truthful historical context',()=>{
  const data=fixture(),session=api.createSession(()=>data,()=>time),s=session.capture();time+=61000;
  assert.equal(session.isCurrent(s),false);const expired=session.capture();assert.equal(expired.price.state,'expired');assert.equal(expired.price.amount,'200016');assert.equal(expired.price.finalPriceVerified,false);assert.equal(session.isCurrent(expired),true);
});
test('Invalid party, real calendar date, namespace mismatch and missing context are rejected',()=>{
  for(const change of [d=>d.offer.ages=[-1],d=>d.offer.ages=[18],d=>d.offer.day='2027-02-29',d=>d.hotel.id=687,d=>d.offer.origin='',d=>d.revision=undefined,d=>d.price.currency='',d=>d.price.expiresAt='future']){
    const data=fixture();change(data);assert.throws(()=>api.createSession(()=>data,()=>time).capture());
  }
});
test('Lost current selection and reader failure reject without transport or state writes',()=>{
  let data=fixture();const session=api.createSession(()=>{if(data===false)throw Error('closed');return data;},()=>time),s=session.capture();data=null;assert.equal(session.isCurrent(s),false);data=false;assert.equal(session.isCurrent(s),false);
  assert.deepEqual(calls,[]);
});
console.log(JSON.stringify({cases:count,result:'PASS',supplierRequests:0,botMessages:0,storageWrites:0,runtimeConnected:false}));
