'use strict';
// The prototype must not hand an unpriced flight to the existing lead owner.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
let reply={id:'exact-tour',price:120000},handoffs=[];
const window={
 location:{href:'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'},
 V2Runtime:{setSearchId(){},async api(action){
  if(action==='countries')return[{id:4,name:'Турция'}];
  if(action==='meals')return[];
  if(action==='search_start')return{searchId:123};
  if(action==='tour')return structuredClone(reply);
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
 for(const price of [undefined,null,{value:null},{value:''},{value:0},{value:-1}]){
  assert.throws(()=>data.leadSession({...offer,flightChoiceId:'0',variants:[{price}]}),/цен/i,'Unpriced flight cannot use the tour base price');
 }
 for(const flightChoiceId of ['2','-1','broken',''])assert.throws(()=>data.leadSession({...offer,flightChoiceId,variants:[{price:{value:130000}}]}),/цен/i,'Missing variant cannot become no flight');
 assert.throws(()=>data.leadSession({...offer,pricePending:true}),/цен/i);
 assert.equal(handoffs.length,0,'Rejected selections never enter the delivery owner');
 const variant={price:{value:133500.5},fuelCharge:0,forward:[],backward:[]};
 const session=data.leadSession({...offer,flightChoiceId:'0',variants:[variant]});
 assert.equal(session.payload({}).flight,variant,'Pass the unchanged, whole-price supplier variant');
 assert.equal(handoffs[0].tour.price,120000,'No mutation or price arithmetic');
 assert.equal(handoffs[0].flight.price.value,133500.5);
 data.leadSession(offer);
 assert.equal(handoffs[1].flight,null,'The existing no-flight fallback remains explicit');
 for(const price of [undefined,null,0,-1]){reply={id:'exact-tour',price};await assert.rejects(data.quote(offer),/подтвердить/,'An unavailable exact-tour price cannot reuse the listing');}
 data.stop();assert.throws(()=>session.payload({}),/изменились/);
 console.log('Prototype flight-price handoff: unknown/missing blocked; priced and explicit no-flight context preserved. Supplier/lead calls: 0.');
})().catch(error=>{console.error(error);process.exitCode=1;});
