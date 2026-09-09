'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../v2/andromeda-provider-v1.js'),'utf8');
const window={location:{href:'https://anytoour.ru/_preview/search3-site-candidate/poisk-turov/',origin:'https://anytoour.ru'}};
vm.runInNewContext(source,{window,URL,Map,Set,Array,Number,String,Object,RegExp,decodeURIComponent,globalThis:window});
const api=window.AnyTourAndromedaProvider,hex='a'.repeat(64),offer='offer_'+hex;
assert.equal(api.version,1);
assert.equal(JSON.stringify(api.operatorHotelCodeFromImage('https://cdn.samo.ru/img/5.5844.3414.jpg')),JSON.stringify({operator:'anex',code:'5844',evidence:'hotel_image_path'}));
assert.equal(JSON.stringify(api.operatorHotelCodeFromImage('https://files.anextour.ru/hotel/example/o417822?hotelCode=5844')),JSON.stringify({operator:'anex',code:'5844',evidence:'hotel_image_query'}));
assert.equal(api.operatorHotelCodeFromImage('https://cdn.samo.ru/img/5844.jpg'),null,'an arbitrary number in a photo URL is not promoted to an operator code');
assert.equal(api.operatorHotelCodeFromImage('https://cdn.samo.ru/img/7.5844.3414.jpg'),null,'another Andromeda operator prefix is not labeled as ANEX');
assert.equal(api.safeUrl('https://cdn.samo.ru/image.jpg?session=secret'),'','credential-like query values are not retained');
assert.equal(api.endpoint('/_preview/search3-anex-candidate/api-andromeda-search3-preview.php').origin,'https://anytoour.ru');
assert.equal(api.endpoint('https://evil.example/api-andromeda-search3-preview.php'),null,'provider endpoint must remain same-origin');
const context={provider:'andromeda',search_ref:hex,generation:7,page:1,offer_ref:offer};
const rawHotel=(localId,name='Movenpick')=>({local_id:localId,card_key:localId===null?'andromeda:andromeda_catalog:3414':null,name,provider:'andromeda',mapping_status:localId===null?'unresolved':'resolved',country:'Египет',category:4,andromeda_content:{source:'andromeda',image_url:'https://cdn.samo.ru/img/5.5844.3414.jpg',hotel_url:'https://operator.example/hotels/movenpick',region:'Шарм-эль-Шейх'},tours:[{provider:'andromeda',offer_ref:offer,offer_context:context,price:{amount:'155079.00',currency:'RUB'},checkin:'2026-09-18',nights:8,meal:'AI',room:'STANDARD',placement:'2 ADL',operator:'ANEX'}]});
const normalized=api.normalizeHotel(rawHotel(21477));
assert.equal(normalized.id,'21477');
assert.equal(normalized.tours[0].id,'andromeda:'+offer);
assert.equal(normalized.tours[0].selectionEnabled,false);
assert.equal(JSON.stringify(normalized.tours[0].providerHotelCode),JSON.stringify({operator:'anex',code:'5844',evidence:'hotel_image_path'}),'photo-derived operator code remains offer metadata');
const tv={id:21477,name:'Movenpick Resort',price:165000,picturelink:'https://tourvisor.example/photo.jpg',tours:[{id:'tv-1',price:165000,date:'18.09.2026'}]};
const merged=api.merge([tv],[rawHotel(21477)]);
assert.equal(merged.length,1,'accepted local ID merges provider offers into one hotel card');
assert.equal(merged[0].tours.length,2);
assert.equal(merged[0].picturelink,tv.picturelink,'existing catalog presentation remains authoritative');
const unresolved=api.merge([tv],[rawHotel(null,'Movenpick Resort')]);
assert.equal(unresolved.length,1,'unresolved Andromeda hotels stay out of customer results');
assert.equal(unresolved[0].tours.length,1,'matching names alone never merge an unresolved Andromeda offer');
const invalid=rawHotel(21477);invalid.tours[0].offer_context={...context,generation:0};
assert.equal(api.normalizeHotel(invalid),null,'invalid offer context is rejected before rendering');
const rendererWindow={};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../v2/results-renderer-v5.js'),'utf8'),{window:rendererWindow,document:{readyState:'loading',addEventListener(){}},Intl,Number,String,Object,Array,Set});
const andromedaRow=rendererWindow.V2Results.tourRow(normalized.tours[0]);
assert.match(andromedaRow,/Источник<\/small><b>Андромеда<\/b>/);
assert.match(andromedaRow,/перед выбором нужна проверка/);
assert.doesNotMatch(andromedaRow,/class="direct-tour"/,'Andromeda offer IDs never reach the Tourvisor selection controller');
const tvRow=rendererWindow.V2Results.tourRow(tv.tours[0]);
assert.match(tvRow,/Источник<\/small><b>Tourvisor<\/b>/);
assert.match(tvRow,/class="direct-tour"/,'Tourvisor selection remains available');

(async()=>{
  const listeners=new Map(),renders=[],providerEvents=[];
  const lifecycle={generation:11,dirty:false,snapshot:{departureId:'1',countryId:'1',dateFrom:'2026-09-18',dateTo:'2026-09-18',nightsFrom:'8',nightsTo:'8',adults:'2',childs:[],currency:'RUB'}};
  const runtimeWindow={
    location:window.location,V2_CONFIG:{andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'},
    V2SearchLifecycle:lifecycle,V2Results:{render(items,options){renders.push({items,options});return items;}},document:{},
    addEventListener(name,listener){listeners.set(name,listener);},
    dispatchEvent(event){providerEvents.push(event.detail);},
    async fetch(url,options){assert.equal(url,'https://anytoour.ru/_preview/search3-anex-candidate/api-andromeda-search3-preview.php');assert.equal(options.method,'POST');assert.equal(options.headers['X-Requested-With'],'AnyTourSearch3');return{ok:true,async json(){return{ok:true,data:{provider:'andromeda',generation:11,page:1,pages_count:1,hotels:[rawHotel(21477),rawHotel(null,'Movenpick Resort')]}};}};}
  };
  class FixtureEvent{constructor(name,options){this.type=name;this.detail=options&&options.detail;}}
  vm.runInNewContext(source,{window:runtimeWindow,URL,Map,Set,Array,Number,String,Object,RegExp,decodeURIComponent,AbortController,CustomEvent:FixtureEvent,globalThis:runtimeWindow});
  listeners.get('v2:search-reset')({detail:{generation:11}});
  runtimeWindow.V2Results.render([tv],{empty:true});
  await new Promise(resolve=>setImmediate(resolve));
  const final=renders.at(-1);
  assert.equal(final.items.length,1,'runtime hides unresolved cards while retaining the resolved hotel');
  assert.equal(final.items[0].tours.length,2,'runtime adds Andromeda to the current shared result renderer');
  assert.equal(final.options.empty,true,'terminal Tourvisor options are restored after Andromeda completes');
  assert.deepEqual(providerEvents.map(item=>item.status),['loading','progress','complete']);
  console.log('SEARCH3_ANDROMEDA_PROVIDER_OK resolved_merge=1 unresolved_hidden=1 matching_writes=0');
})().catch(error=>{console.error(error);process.exitCode=1;});
