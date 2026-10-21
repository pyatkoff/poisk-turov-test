'use strict';
// The prototype must not hand an unpriced flight to the existing lead owner.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
let reply={id:'exact-tour',price:120000},flightReply=[],handoffs=[],calls=[];
const window={
 location:{href:'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'},
 V2Runtime:{setSearchId(){},async api(action,params){
  calls.push({action,params});
  if(action==='countries')return[{id:4,name:'Турция'}];
  if(action==='meals')return[];
  if(action==='search_start')return{searchId:123};
  if(action==='tour'){if(reply instanceof Error)throw reply;return structuredClone(reply);}
  if(action==='flights'){if(flightReply instanceof Error)throw flightReply;return flightReply;}
  throw Error('Unexpected fixture action '+action);
 }},
 Search3CanonicalProfilesV1:{create:()=>({reset(){},read:()=>[]})},
 AnyTourLocalDbProviderV1:{parse:()=>null},
 V2TourController:{createLeadSession(value){handoffs.push(value);return{payload:()=>value,submit:()=>{throw Error('No delivery in this test');}};}}
};
const context=vm.createContext({window,structuredClone,URL,URLSearchParams,AbortController,DOMException,setTimeout:()=>1,clearTimeout(){},fetch:async url=>({ok:true,json:async()=>url==='/data/departures-v1.php'?{ok:true,items:[{id:1,name:'Москва'}]}:String(url).includes('/search3-destination-read-v1.php?')?{ok:true,source:'anytour-destination-identities-v1',provider:'tourvisor',items:[{id:4,name:'Турция',tourvisorIds:['4']}]}:{ok:false}})});
vm.runInContext(fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/data.js'),'utf8'),context);
(async()=>{
 const data=window.AnyTourPrototypeData;
 const beforeFuel=calls.length;
 for(const fuel of [undefined,null,'',' \t\n',false,true,[],[0],{},NaN,Infinity,-1,'unknown',{value:null},{value:'  '},{value:false},{value:[]}]){
  assert.equal(data.fuel({fuelCharge:fuel}),null,'Unknown fuel is not an explicit zero');
  assert.equal(data.fuel({fuelCharge:20686},{fuelCharge:fuel}),null,'A pair-owned unknown cannot inherit tour fuel');
 }
 for(const [fuel,expected] of [[0,0],['0',0],[' 0 ',0],[{value:0},0],[{value:'0'},0],[20686,20686],['20686.50',20686.5],[{value:'20686.50'},20686.5]]){
  assert.equal(data.fuel({fuelCharge:fuel}),expected);
  assert.equal(data.fuel({fuelCharge:17},{fuelCharge:fuel}),expected,'Explicit pair amount takes precedence');
 }
 assert.equal(data.fuel({fuelCharge:20686},{}),20686,'Only an absent pair fuel field can use the tour amount');
 assert.equal(calls.length,beforeFuel,'Fuel validation makes no supplier calls');
 await data.init();await data.search({origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-01',minNights:7,maxNights:7,adults:2,ages:[]},()=>{});
 const blockedOperators=['Coral',' CORAL-TRAVEL ','Корал Тревел','Корал Трэвел','Sunmar','Sunmar Tour','Санмар',
  {name:'Coral Travel'},{russianName:'Санмар'},{name:'Coral Travel',russianName:'Неизвестная подпись'},
  {name:'Неизвестная подпись',russianName:'Санмар'}];
 const blocked=error=>error.code==='tourvisor_operator_unsupported'&&error.retryable===false;
 for(const operator of blockedOperators){
  for(const field of ['operator','operatorName']){
   const before=calls.length;
   await assert.rejects(data.quote({raw:{id:'exact-tour',[field]:operator},provider:'tourvisor',cached:false}),blocked);
   await assert.rejects(data.flights({id:'exact-tour',[field]:operator}),blocked);
   assert.equal(calls.length,before,'Unsupported operators never initiate a Tourvisor quote or flight request');
  }
 }
 for(const operator of ['ANEX','FUN&SUN','Unknown operator',undefined]){
  reply={id:'exact-tour',price:120000};flightReply=[];
  const before=calls.length,quoted=await data.quote({raw:{id:'exact-tour',operator},provider:'tourvisor',cached:false});
  assert.equal(quoted.price,120000,'Allowed/unknown operator retains its own unchanged supplier price');
  await data.flights(quoted);
  assert.deepEqual(JSON.parse(JSON.stringify(calls.slice(before))),[
   {action:'tour',params:{tourId:'exact-tour',currency:'RUB'}},
   {action:'flights',params:{tourId:'exact-tour',currency:'RUB'}}
  ],'Allowed operators keep the existing request contract when response operator is absent');
  quoted.operator={name:'Sunmar'};
  const afterQuote=calls.length;
  await assert.rejects(data.flights(quoted),blocked);
  assert.equal(calls.length,afterQuote,'A blocked operator discovered after quoting cannot request flights');
 }
 for(const operator of blockedOperators){
  reply={id:'exact-tour',price:120000,operator};
  const before=calls.length;
  await assert.rejects(data.quote({raw:{id:'exact-tour'},provider:'tourvisor',cached:false}),blocked);
  assert.equal(calls.length,before+1,'Unknown listing ownership may be discovered only by its one quote response');
  assert.throws(()=>data.leadSession({raw:{id:'exact-tour'},tour:reply,provider:'tourvisor',cached:false,flightChoiceId:null}),/устарело/,'Rejected response never acquires quote authority');
 }
 reply={id:'exact-tour',price:120000};
 const sourceOperator={name:'ANEX'},quotedWithoutOperator=await data.quote({raw:{id:'exact-tour',operator:sourceOperator},provider:'tourvisor',cached:false});
 sourceOperator.name='Sunmar';
 const beforeReceiptCheck=calls.length;
 await assert.rejects(data.flights(quotedWithoutOperator),blocked);
 assert.equal(calls.length,beforeReceiptCheck,'Receipt retains source policy when the quote response omits operator');
 reply={id:'exact-tour',price:120000,operatorName:'Coral Travel'};
 await assert.rejects(data.quote({raw:{id:'exact-tour'},provider:'tourvisor',cached:false}),blocked);
 reply={id:'exact-tour',price:120000};flightReply=[];
 const raw={id:'exact-tour'},offer={raw,provider:'tourvisor',cached:false,flightChoiceId:null};
 offer.tour=await data.quote(offer);
 for(const flightState of [{flightsLoading:true},{flightsError:'Не удалось загрузить рейсы.'}]){
  for(const selection of [{flightChoiceId:null},{flightChoiceId:'0',variants:[{price:{value:133500.5}}]}]){
   const callsBefore=calls.length;
   assert.throws(()=>data.leadSession({...offer,...selection,...flightState}),/рейс/i,'A pending or failed flight request must not become a no-flight handoff, even with a previous priced variant');
   assert.equal(calls.length,callsBefore,'Rejecting flight state does not retry the supplier');
  }
 }
 assert.equal(handoffs.length,0,'Incomplete flight loading never enters the delivery owner');
 for(const malformed of [undefined,null,false,{},'unavailable',{flights:null},{flights:{}},{flights:'unavailable'}]){
  flightReply=malformed;
  await assert.rejects(data.flights(offer.tour),/рейс/i,'An unsupported response is not a successful empty flight list');
 }
 flightReply=new Error('Fixture transport failure');
 await assert.rejects(data.flights(offer.tour),/Fixture transport failure/,'Transport errors propagate to the existing retry flow');

 for(const price of [undefined,null,{value:null},{value:''},{value:0},{value:-1}]){
  assert.throws(()=>data.leadSession({...offer,flightChoiceId:'0',variants:[{price}]}),/цен/i,'Unpriced flight cannot use the tour base price');
 }
 for(const flightChoiceId of ['2','-1','broken',''])assert.throws(()=>data.leadSession({...offer,flightChoiceId,variants:[{price:{value:130000}}]}),/цен/i,'Missing variant cannot become no flight');
 assert.throws(()=>data.leadSession({...offer,pricePending:true}),/цен/i);
 assert.equal(handoffs.length,0,'Rejected selections never enter the delivery owner');
 const variant={price:{value:133500.5},fuelCharge:0,forward:[],backward:[]};
 for(const response of [[],{flights:[]},[variant],{flights:[variant]}]){
  flightReply=response;
  assert.equal(await data.flights(offer.tour),Array.isArray(response)?response:response.flights,'Valid flight list and wrapped list are passed unchanged, including an explicit empty result');
  assert.deepEqual(JSON.parse(JSON.stringify(calls.at(-1))),{action:'flights',params:{tourId:'exact-tour',currency:'RUB'}},'The existing flight request contract is unchanged');
 }
 const session=data.leadSession({...offer,flightChoiceId:'0',variants:[variant]});
 assert.equal(session.payload({}).flight,variant,'Pass the unchanged, whole-price supplier variant');
 assert.equal(handoffs[0].tour.price,120000,'No mutation or price arithmetic');
 assert.equal(handoffs[0].flight.price.value,133500.5);
 data.leadSession({...offer,flightsLoading:false,flightsError:'',variants:[]});
 assert.equal(handoffs[1].flight,null,'The existing no-flight fallback remains explicit');
 for(const price of [undefined,null,0,-1]){reply={id:'exact-tour',price};await assert.rejects(data.quote(offer),/подтвердить/,'An unavailable exact-tour price cannot reuse the listing');}
 // Use the actual selected-offer consumer: an explicit expiry must not turn
 // a failed flight check into a manager-flight handoff at the old base price.
 const app=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
 function appFunction(start,end){const from=app.indexOf(start),to=app.indexOf(end,from);assert.ok(from>=0&&to>from);return app.slice(from,to);}
 function flightConsumer(selectedOffer){
  const consumer=vm.createContext({data,selectedOffer,selectionGeneration:1,modalType:'offer',
   $:()=>({open:true}),retainedProviderView:()=>null,renderRealOffer(){},
   flightPairFor:()=>null,withFlightPair(){throw Error('No successful pair expected');},openFlightPicker(){}});
  vm.runInContext(appFunction('function isCurrentOfferRequest(','async function quoteSelectedOffer(')
   +appFunction('async function loadRealFlights(','async function openLeadWithQuote('),consumer);
  return consumer;
 }
 const httpError=status=>Object.assign(new Error('Fixture HTTP '+status),{code:'HTTP_ERROR',status,
  data:{ok:false,upstreamStatus:status,diagnostics:{provider:'tourvisor',stage:'flights',category:'http',httpStatus:status,elapsedMs:17}}});
 reply={id:'exact-tour',price:120000};flightReply=[];
 const expiring={...offer,tour:await data.quote(offer)};
 const priorSession=data.leadSession(expiring);
 const other={...offer,raw:{id:'other-tour'}};reply={id:'other-tour',price:95000};other.tour=await data.quote(other);
 const otherSession=data.leadSession(other);
 const gone=httpError(410);flightReply=gone;
 const consumer=flightConsumer(expiring),beforeExpiryCalls=calls.length;
 await consumer.loadRealFlights();
 assert.equal(consumer.selectedOffer.quoteErrorCode,'offer_expired','HTTP410 enters the existing terminal offer path');
 assert.equal(consumer.selectedOffer.quoteErrorTerminal,true);
 assert.equal(consumer.selectedOffer.tour,null,'Expired flights revoke the old base tour in the actual consumer');
 assert.equal(consumer.leadReadyOffer(consumer.selectedOffer),null,'Known expiry cannot fall back to the old price through a manager');
 assert.equal(calls.length,beforeExpiryCalls+1,'Expiry handling makes only the original flight intent, with no automatic retry');
 const expired=error=>error===gone&&error.code==='offer_expired'&&error.retryable===false&&error.status===410&&error.data===gone.data;
 assert.throws(()=>data.leadSession(expiring),expired,'The exact failed quote cannot create another session');
 assert.throws(()=>priorSession.payload({}),expired,'An existing session loses its expired quote authority');
 assert.throws(()=>priorSession.submit({}),expired,'Expiry rejects before invoking the delivery owner');
 const sealedCalls=calls.length;
 await assert.rejects(data.flights(expiring.tour),expired,'The known-expired exact receipt is not requested again');
 assert.equal(calls.length,sealedCalls,'A repeated expired flight intent performs no supplier call');
 assert.equal(otherSession.payload({}).tour,other.tour,'Another exact offer receipt remains current');
 data.leadSession(other);
 reply=httpError(410);reply.data.diagnostics.stage='tour';
 const quoteFailure=reply;
 await assert.rejects(data.quote(other),error=>error===quoteFailure&&error.code==='offer_expired'&&error.retryable===false&&error.status===410&&error.data===quoteFailure.data);
 assert.throws(()=>otherSession.payload({}),error=>error.code==='offer_expired','Failed revalidation revokes only its previous exact quote');
 for(const status of [404,429,500,502,503,504]){
  const original=httpError(status);reply=original;
  await assert.rejects(data.quote(offer),error=>error===original,'Unproven/transient quote failures retain their original contract');
  flightReply=original;
  await assert.rejects(data.flights(offer.tour),error=>error===original,'Unproven/transient flight failures retain their original contract');
 }
 // A new successful receipt for the same ID is independent of the revoked one.
 reply={id:'exact-tour',price:121000};flightReply=[];
 const fresh={...offer,tour:await data.quote(offer)};
 assert.equal(data.leadSession(fresh).payload({}).tour.price,121000);
 const freshSession=data.leadSession(fresh);
 reply=httpError(410);
 await assert.rejects(data.quote({...fresh,raw:{id:'foreign-tour'}}),error=>error.code==='offer_expired');
 assert.equal(freshSession.payload({}).tour,fresh.tour,'A mismatched previous-tour ID cannot revoke another quote');
 for(const original of [Object.assign(httpError(410),{status:'410'}),Object.assign(httpError(410),{code:'NETWORK_ERROR'})]){
  reply=original;
  await assert.rejects(data.quote(fresh),error=>error===original&&error.retryable===undefined,'Only a native numeric HTTP410 is terminalized');
 }
 assert.equal(freshSession.payload({}).tour,fresh.tour,'Other failure kinds leave the current exact receipt untouched');
 assert.equal(offer.tour.price,120000,'Revocation never changes the old supplier price');
 data.stop();assert.throws(()=>session.payload({}),/изменились/);
 console.log('Prototype flight handoff: explicit410 terminal/receipt revocation; Coral/Sunmar, transient errors, empty flights and whole prices preserved. Supplier/lead calls: 0.');
})().catch(error=>{console.error(error);process.exitCode=1;});
