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
// Exercise the installed UI's actual receipt constructors, not a second receipt
// normalizer. The quote receipt intentionally exposes labels, not invented legs.
const uiSource=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const estimateStart=uiSource.indexOf('function anexApplicationReceipt('),estimateEnd=uiSource.indexOf('\nfunction openAnexApplicationPreview',estimateStart);
const quoteStart=uiSource.indexOf("  const raw=o.raw;anexApplicationDraft=Object.freeze({provider:'anex'"),quoteEnd=uiSource.indexOf('\n }',quoteStart);
assert.ok(estimateStart>=0&&estimateEnd>estimateStart&&quoteStart>=0&&quoteEnd>quoteStart,'canonical ANEX application builders exist');
function applicationFixture(verified=false,choiceRef='PRIVATE_PAIR'){
  const input=fixture(),o=input.offer,h=input.hotel;
  o.flightChoiceId=null;o.raw={anexKind:'concrete',anexSessionCurrent:true,offerRef:'anex_online:'+ 'a'.repeat(64),searchRef:'b'.repeat(32),anexGeneration:7,anexLocalHotelId:3634};
  const result=verified?{state:'quote_verified',finalPriceVerified:true,finalPrice:{amount:231456,currency:'RUB'},expiresAt:time/1000+60,choice:{choiceRef,legs:[{label:'VF 276 · Москва → Хургада · 29.03.2027'},{label:'VF 277 · Хургада → Москва · 05.04.2027'}]}}
    :{state:'additional_prices',finalPriceVerified:false,arithmeticApplied:true,calculatedTotal:{amount:220000,currency:'RUB'}};
  const scope=vm.createContext({o,h,result,state:{search:{origin:'Москва'}},countryNames:{2:'Египет'},mealLabel:()=> 'Всё включено'});
  vm.runInContext(uiSource.slice(estimateStart,estimateEnd),scope);
  input.application=verified?vm.runInContext('let anexApplicationDraft;'+uiSource.slice(quoteStart,quoteEnd)+';anexApplicationDraft;',scope):scope.anexApplicationReceipt(o,result,h);
  assert.ok(input.application);return input;
}
test('Canonical ANEX estimate preserves calculated whole-party amount and manager-deferred flights',()=>{
  const input=applicationFixture(),session=api.createSession(()=>input,()=>time),s=session.capture();
  assert.equal(s.price.amount,'220000');assert.equal(s.price.state,'estimated');assert.equal(s.price.basis,'shown-offer');
  assert.equal(s.flights.state,'manager-deferred');assert.deepEqual(plain(s.flights.outward),[]);assert.deepEqual(plain(s.trip.childAges),[0,0,8]);
  assert.equal(s.stay.room,input.application.room);assert.equal(s.stay.meal,input.application.meal);assert.equal(session.isCurrent(s),true);
});
test('Canonical ANEX chosen pair preserves supplied direction labels without manufacturing parsed flight facts',()=>{
  const input=applicationFixture(true),before=plain(input),session=api.createSession(()=>input,()=>time),s=session.capture();
  assert.equal(s.price.amount,'231456');assert.equal(s.price.state,'checked');assert.equal(s.price.basis,'selected-pair');
  assert.deepEqual(plain(s.flights),{state:'selected',outward:[{label:input.application.flights[0].name}],inward:[{label:input.application.flights[1].name}]});
  assert.equal(s.price.finalPriceVerified,false);assert.equal(s.price.authority,'browser-observation-only');assert.equal(session.isCurrent(s),true);assert.deepEqual(plain(input),before);
  assert.doesNotMatch(JSON.stringify(s),/PRIVATE_PAIR|anex_online:|offerRef|searchRef|choiceRef|generation|SECRET_|localHotelId/);
});
test('Application receipt replacement, source row, revision and original terms invalidate pending results',()=>{
  for(const change of [d=>d.application={...d.application},d=>d.offer.raw={...d.offer.raw},d=>d.revision++,d=>d.offer.meal='BB',d=>d.offer.placement='Different',d=>d.offer.total++,d=>d.hotel.resort='Other']){
    const input=applicationFixture(true),session=api.createSession(()=>input,()=>time),s=session.capture();change(input);assert.equal(session.isCurrent(s),false);
  }
  const input=applicationFixture(true);input.application={...input.application};const session=api.createSession(()=>input,()=>time),s=session.capture();
  input.application.choiceRef='ANOTHER_PRIVATE_PAIR';assert.equal(session.isCurrent(s),false);
});
test('Foreign receipt identity, trip, party and hotel cannot borrow the selected row',()=>{
  for(const change of [r=>r.provider='tourvisor',r=>r.offerRef+='0',r=>r.searchRef+='0',r=>r.generation++,r=>r.localHotelId++,r=>r.hotel='Other',r=>r.day='2027-03-30',r=>r.nights++,r=>r.adults++,r=>r.ages=[0,8,0],r=>r.room='Other',r=>r.currency='USD']){
    const input=applicationFixture(true);input.application={...input.application,ages:[...input.application.ages]};change(input.application);
    assert.throws(()=>api.createSession(()=>input,()=>time).capture(),/invalid_application_context/);
  }
});
test('Incomplete or ambiguous application price/flight facts are rejected',()=>{
  for(const change of [r=>r.price=null,r=>r.price=0,r=>r.priceKind='listing',r=>r.finalPriceVerified=false,r=>r.choiceRef='',r=>r.expiresAt=undefined,r=>r.flights=[],r=>r.flights=[r.flights[1],r.flights[0]],r=>r.flights=[{direction:'0',name:''},r.flights[1]]]){
    const input=applicationFixture(true);input.application={...input.application};change(input.application);
    assert.throws(()=>api.createSession(()=>input,()=>time).capture(),/invalid_application_context/);
  }
  const input=applicationFixture();input.application={...input.application,flights:[{direction:'0',name:'Not selected'}]};
  assert.throws(()=>api.createSession(()=>input,()=>time).capture(),/invalid_application_context/);
});
test('ANEX application quote expires at its exact deadline without reviving the old snapshot',()=>{
  const input=applicationFixture(true),session=api.createSession(()=>input,()=>time),s=session.capture();time=input.application.expiresAt*1000;
  assert.equal(session.isCurrent(s),false);const historical=session.capture();assert.equal(historical.price.state,'expired');assert.equal(historical.price.amount,'231456');
  assert.equal(historical.price.finalPriceVerified,false);assert.deepEqual(calls,[]);
});
const exactChoice='anex_quote:'+'c'.repeat(64);
test('ANEX server selector contains only the five retained identity pins, with one current read',()=>{
  const input=applicationFixture(true,exactChoice),before=plain(input);let reads=0;
  const session=api.createSession(()=>{reads++;return input;},()=>time),snapshot=session.capture(),selector=session.anexSelector(snapshot);
  assert.deepEqual(plain(selector),{generation:7,search_ref:'b'.repeat(32),offer_ref:'anex_online:'+'a'.repeat(64),local_hotel_id:3634,choice_ref:exactChoice});
  assert.equal(reads,2);assert.equal(Object.isFrozen(selector),true);assert.throws(()=>selector.generation++,error=>error.name==='TypeError');
  assert.doesNotMatch(JSON.stringify(selector),/"(?:price|amount|currency|authority|observation|record_id|revision|phone|ages|hotel|room|meal|capturedAt)"\s*:|https?:\/\//i);
  assert.doesNotMatch(JSON.stringify(snapshot),/anex_quote:|anex_online:|search_ref|offer_ref|choice_ref|searchRef|offerRef|generation/);
  assert.equal(snapshot.price.finalPriceVerified,false);assert.deepEqual(plain(input),before);
});
test('Changed or replaced canonical selection cannot produce pins from an earlier snapshot',()=>{
  for(const change of [d=>d.revision++,d=>d.offer.raw={...d.offer.raw},d=>d.application={...d.application},d=>d.offer.room='Suite',d=>d.offer.ages=[0,8,0],d=>{d.application={...d.application,choiceRef:'anex_quote:'+'d'.repeat(64)};}]){
    const input=applicationFixture(true,exactChoice),session=api.createSession(()=>input,()=>time),snapshot=session.capture();change(input);
    assert.equal(session.anexSelector(snapshot),null);
  }
  let input=applicationFixture(true,exactChoice);const session=api.createSession(()=>input,()=>time),snapshot=session.capture();input=null;
  assert.equal(session.anexSelector(snapshot),null);
});
test('Copied or foreign observations never supply a server selector',()=>{
  const input=applicationFixture(true,exactChoice),first=api.createSession(()=>input,()=>time),second=api.createSession(()=>input,()=>time),snapshot=first.capture();
  for(const value of [plain(snapshot),null,'x',{},second.capture()])assert.equal(first.anexSelector(value),null);
  assert.equal(second.anexSelector(snapshot),null);
});
test('Exact quote deadline also rejects newly captured historical observations',()=>{
  const input=applicationFixture(true,exactChoice),session=api.createSession(()=>input,()=>time),snapshot=session.capture();time=input.application.expiresAt*1000;
  assert.equal(session.anexSelector(snapshot),null);const expired=session.capture();assert.equal(session.isCurrent(expired),true);
  assert.equal(session.anexSelector(expired),null);assert.equal(expired.price.amount,'231456');assert.equal(expired.price.state,'expired');
});
test('Estimate, manager-deferred and other source owners never receive an implicit selected-pair selector',()=>{
  const deferred=applicationFixture();
  const normalized=fixture();
  const tv=fixture();tv.offer.provider='tourvisor';
  for(const input of [deferred,normalized,tv]){const session=api.createSession(()=>input,()=>time),snapshot=session.capture();assert.equal(session.anexSelector(snapshot),null);assert.equal(session.isCurrent(snapshot),true);}
  assert.equal(api.createSession(()=>deferred,()=>time).capture().flights.state,'manager-deferred');
});
test('Noncanonical private refs and out-of-range identity pins are unavailable without truncation or I/O',()=>{
  for(const choice of ['PRIVATE_PAIR','0','anex_quote:'+'C'.repeat(64),exactChoice+'0',exactChoice+'\n']){
    const input=applicationFixture(true,choice),session=api.createSession(()=>input,()=>time),snapshot=session.capture();
    assert.equal(session.anexSelector(snapshot),null);
  }
  for(const field of ['generation','localHotelId']){
    const input=applicationFixture(true,exactChoice);input.application={...input.application,[field]:2147483648};
    if(field==='generation')input.offer.raw.anexGeneration=2147483648;
    else{input.offer.raw.anexLocalHotelId=2147483648;input.offer.hotelId=2147483648;input.hotel.id=2147483648;}
    const session=api.createSession(()=>input,()=>time),snapshot=session.capture();assert.equal(session.anexSelector(snapshot),null);
  }
  assert.deepEqual(calls,[]);
});
function tourvisorFixture(manager=false){
 const value=fixture(),o=value.offer;o.provider='tourvisor';o.key=encodeURIComponent('tourvisor:tv-exact');o.raw={id:'tv-exact'};
 o.tour={id:'tv-exact',roomType:o.room,meal:{name:'AI'}};value.hotel.legacyIds=['101'];
 value.selector={provider:'tourvisor',generation:23,search_ref:'d'.repeat(32),offer_ref:'tourvisor:'+'e'.repeat(64),local_hotel_id:101,
  choice_ref:manager?'manager-deferred':'tourvisor_pair:'+'f'.repeat(64)};
 value.price.expiresAt=null;
 value.requestParty={adults:o.adults,childAges:[...o.ages].sort((a,b)=>a-b)};
 if(manager){o.flightChoiceId=null;o.variants=[];}
 return value;
}
test('TV manager and optional pair use their accepted private pins and canonical server review terms',()=>{
 for(const manager of [true,false]){
  const input=tourvisorFixture(manager),session=api.createSession(()=>input,()=>time),snapshot=session.capture();
  assert.equal(snapshot.source,'tourvisor');assert.equal(snapshot.hotel.uiId,101,'exact accepted legacy crosswalk');
  assert.equal(snapshot.stay.meal,'AI','compare the retained native meal term without changing its displayed owner');
  assert.equal(snapshot.price.finalPriceVerified,false);assert.equal(snapshot.flights.state,manager?'manager-deferred':'selected');
  assert.deepEqual(plain(session.selector(snapshot)),input.selector);assert.equal(session.anexSelector(snapshot),null);
  if(!manager)assert.deepEqual(plain(snapshot.flights.outward),[{label:'VF 276 · VKO → HRG · 2027-03-29'}]);
  assert.doesNotMatch(JSON.stringify(snapshot),/tourvisor_pair:|tourvisor:|search_ref|choice_ref|csrf/);
 }
});
test('Changing TV pins, UI labels, row, original party or pair invalidates an existing capture',()=>{
 for(const change of [x=>x.selector.generation++,x=>x.selector.choice_ref='tourvisor_pair:'+'a'.repeat(64),x=>x.offer.raw={...x.offer.raw},
  x=>x.offer.meal='Завтраки',x=>x.offer.ages.reverse(),x=>x.hotel.legacyIds=[],x=>x.offer.variants[0].forward[0].number='CHANGED']){
  const input=tourvisorFixture(),session=api.createSession(()=>input,()=>time),snapshot=session.capture();change(input);
  assert.equal(session.isCurrent(snapshot),false);assert.equal(session.selector(snapshot),null);
 }
});
test('TV incomplete, mixed, unverified and foreign contexts cannot acquire a dispatcher selector',()=>{
 for(const change of [x=>x.selector.provider='anex',x=>x.selector.offer_ref='anex_online:'+'e'.repeat(64),x=>x.selector.extra='bad',
  x=>x.selector.local_hotel_id=102,x=>x.selector.choice_ref='manager-deferred',x=>x.price.state='estimated',x=>x.price.currency='EUR',
  x=>x.offer.variants[0].forward[0].departure.port={},x=>x.offer.tour.meal=null,x=>x.requestParty.childAges=[0,8],x=>x.requestParty.adults=3]){
  const input=tourvisorFixture();change(input);assert.throws(()=>api.createSession(()=>input,()=>time).capture());
 }
 const input=tourvisorFixture(true),session=api.createSession(()=>input,()=>time),snapshot=session.capture();
 assert.equal(session.selector(plain(snapshot)),null);assert.deepEqual(calls,[]);
});
test('TV request-order comparison retains the UI-entered child order and invalidates a later edit',()=>{
 const input=tourvisorFixture(true);input.offer.ages=[12,0];input.requestParty.childAges=[0,12];
 const session=api.createSession(()=>input,()=>time),snapshot=session.capture();assert.deepEqual(plain(snapshot.trip.childAges),[0,12]);assert.deepEqual(plain(input.offer.ages),[12,0]);
 assert.equal(session.isCurrent(snapshot),true);input.offer.ages.reverse();assert.equal(session.isCurrent(snapshot),false);assert.equal(session.selector(snapshot),null);
});
console.log(JSON.stringify({cases:count,result:'PASS',supplierRequests:0,botMessages:0,storageWrites:0,runtimeConnected:false}));
