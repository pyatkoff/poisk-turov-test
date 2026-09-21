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
  if(action==='tour')return structuredClone(reply);
  if(action==='flights'){if(flightReply instanceof Error)throw flightReply;return flightReply;}
  throw Error('Unexpected fixture action '+action);
 }},
 Search3CanonicalProfilesV1:{create:()=>({reset(){},read:()=>[]})},
 AnyTourLocalDbProviderV1:{parse:()=>null},
 V2TourController:{createLeadSession(value){handoffs.push(value);return{payload:()=>value,submit:()=>{throw Error('No delivery in this test');}};}}
};
const context=vm.createContext({window,structuredClone,URL,DOMException,setTimeout:()=>1,clearTimeout(){},fetch:async url=>({ok:true,json:async()=>url==='/data/departures-v1.php'?{ok:true,items:[{id:1,name:'Москва'}]}:{ok:false}})});
vm.runInContext(fs.readFileSync(path.resolve(__dirname,'../v2/prototype-search/data.js'),'utf8'),context);
(async()=>{
 const data=window.AnyTourPrototypeData;
 await data.init();await data.search({origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-01',minNights:7,maxNights:7,adults:2,ages:[]},()=>{});
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
 data.stop();assert.throws(()=>session.payload({}),/изменились/);
 console.log('Prototype flight handoff: pending/failed/malformed blocked; priced and explicit empty flight context preserved. Supplier/lead calls: 0.');
})().catch(error=>{console.error(error);process.exitCode=1;});
