'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(require('path').join(__dirname,'../v2/anex-final-price-provider-v1.js'),'utf8');
function load(extra={}){
  const root={location:{protocol:'https:',hostname:'anytoour.ru',origin:'https://anytoour.ru',pathname:'/_preview/search3-local-candidate/poisk-turov/',href:'https://anytoour.ru/_preview/search3-local-candidate/poisk-turov/'},V2_CONFIG:{},URL,console,AbortController,setTimeout,clearTimeout,...extra};
  root.window=root;root.globalThis=root;
  vm.runInNewContext(source,root,{filename:'anex-final-price-provider-v1.js'});
  return root;
}
const r=load();const api=r.AnyTourAnexFinalPriceProvider;
assert(api);
assert.equal(api.endpoint('').pathname,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
r.location.pathname='/poisk-turov/';assert.equal(api.endpoint(''),null);r.location.pathname='/_preview/search3-local-candidate/poisk-turov/';
const search='a'.repeat(32), offer=n=>'anex_online:'+String(n).padStart(64,'b').slice(-64);
function hotel(id,price,kind='concrete',off=offer(id)){return{local_id:id,name:'H'+id,category:5,rating:4.8,country:'Turkey',region:'Side',catalog:{image_url:'https://img.example/h.jpg'},tours:[{price:{amount:String(price),currency:'RUB'},checkin:'2026-10-05',nights:7,meal:'AI',room:'STANDARD',kind,search_ref:search,offer_ref:off}]};}
const planned=api.plan([hotel(1,200000),hotel(2,190000),hotel(3,180000),hotel(4,170000),hotel(5,160000),hotel(6,150000)],search);
assert.deepEqual(Array.from(planned,x=>x.row.hotelId),[6,5,4,3,2]);
const item={offer_ref:planned[0].row.offerRef,local_hotel_id:6,status:'additional_prices',finalPriceReady:true,finalPrice:'165000',price:'165000',additional_prices:{application_state:'applied',search_plus_additional:{amount:'165000',currency:'RUB'}}};
assert.equal(api.readyPrice(item,planned[0]),'165000');
assert.equal(api.readyPrice({...item,finalPriceReady:false},planned[0]),null);
assert.equal(api.readyPrice({...item,price:'164999'},planned[0]),null);
const normalized=api.normalize(planned[0],'165000');assert.equal(normalized.tours[0].price,165000);assert.equal(normalized.tours[0].fuelIncluded,true);assert.equal(normalized.tours[0].selectionEnabled,false);
const merged=api.merge([{id:'6',provider:'tourvisor',price:170000,tours:[{id:'tv1',provider:'tourvisor',price:170000}]}],[normalized]);
assert.equal(merged.length,1);assert.equal(merged[0].tours.length,2);assert.equal(merged[0].price,165000);assert(merged[0].providers.includes('anex'));
async function runtime(finalReady){
  const listeners={},renders=[],events=[],bodies=[];let resolveDone;const done=new Promise(resolve=>resolveDone=resolve);
  class CE{constructor(type,options){this.type=type;this.detail=options.detail;}}
  const searchHotel=hotel(101,185125,'concrete','anex_online:'+'c'.repeat(64));
  const responses=[
    {ok:true,data:{provider:'anex',generation:1,search_ref:search,hotels:[searchHotel]}},
    {ok:true,data:{provider:'anex',generation:1,search_ref:search,status:'additional_prices_batch',offers:[{offer_ref:searchHotel.tours[0].offer_ref,local_hotel_id:101,status:finalReady?'additional_prices':'additional_prices_unknown',finalPriceReady:finalReady,finalPrice:finalReady?'199390':null,price:finalReady?'199390':null,additional_prices:finalReady?{application_state:'applied',search_plus_additional:{amount:'199390',currency:'RUB'}}:null}]}}
  ];
  const root=load({document:{},V2_CONFIG:{},V2Results:{render(list){renders.push(JSON.parse(JSON.stringify(list||[])));return list;}},V2SearchLifecycle:{generation:1,dirty:false,snapshot:{departureId:1,countryId:4,dateFrom:'2026-10-05',dateTo:'2026-10-05',nightsFrom:7,nightsTo:7,adults:2,childs:[],currency:'RUB'}},addEventListener(type,fn){listeners[type]=fn;},dispatchEvent(event){events.push(event);if(event.type==='v2:provider-status'&&event.detail.provider==='anex'&&['complete','error'].includes(event.detail.status))resolveDone(event.detail);return true;},CustomEvent:CE,async fetch(url,options){bodies.push(JSON.parse(options.body));const body=responses.shift();return{ok:true,async json(){return body;}};}});
  root.V2Results.render([{id:'101',provider:'tourvisor',price:210000,tours:[{id:'tv',provider:'tourvisor',price:210000}]}],{});
  listeners['v2:search-reset']({detail:{generation:1,dirty:false}});
  const status=await Promise.race([done,new Promise((_,reject)=>setTimeout(()=>reject(new Error('timeout')),1000))]);
  return{root,renders,events,bodies,status};
}
(async()=>{
  const good=await runtime(true);assert.equal(good.bodies.length,2);assert.equal(good.bodies[1].action,'additional_prices_batch');assert.equal(good.bodies[1].items.length,1);
  const anexPrices=good.renders.flatMap(list=>list.flatMap(h=>(h.tours||[]).filter(t=>t.provider==='anex').map(t=>t.price)));
  assert.deepEqual(anexPrices,[199390]);assert(!anexPrices.includes(185125));assert.equal(good.status.hotels,1);
  const bad=await runtime(false);const badAnex=bad.renders.flatMap(list=>list.flatMap(h=>(h.tours||[]).filter(t=>t.provider==='anex')));
  assert.equal(badAnex.length,0);assert.equal(bad.status.hotels,0);
  console.log('ANEX_FINAL_PRICE_PROVIDER_OK pure=15 runtime=2 final=199390 base_not_rendered=185125 max_ready=5');
})().catch(error=>{console.error(error);process.exit(1);});
