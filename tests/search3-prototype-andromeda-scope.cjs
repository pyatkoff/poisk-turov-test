'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const crypto=require('node:crypto');

const rootDir=path.resolve(__dirname,'..');
const trip={origin:'Москва',country:'4',from:'2026-09-29',to:'2026-10-05',minNights:7,maxNights:7,adults:2,ages:[]};
const flush=async()=>{for(let i=0;i<10;i++)await new Promise(setImmediate);};

function andromedaPayload(body,{brokenIdentity=false}={}){
  const searchRef='c'.repeat(64);
  const offer=(suffix,checkin,offerRef='offer_'+suffix.repeat(64))=>({
    provider:'andromeda',price:{amount:'148000',currency:'RUB'},checkin,nights:7,adults:2,children:0,
    meal:'AI',room:'STANDARD',placement:'DBL',operator:{name:'FUN&SUN'},offer_ref:offerRef,
    offer_context:{provider:'andromeda',search_ref:searchRef,generation:body.generation,page:1,offer_ref:offerRef},
    listing_price_ref:'listing_'+suffix.repeat(64),selection_enabled:false
  });
  const valid=offer('d',body.params.dateFrom);
  const second=brokenIdentity?offer('e',body.params.dateFrom,'broken-ref'):offer('e','2026-10-06');
  return {ok:true,data:{provider:'andromeda',generation:body.generation,date_range:{from:body.params.dateFrom,to:body.params.dateTo},
    grouped:true,first_page_only:false,page:1,pages_count:1,external_search_pending:false,search_ref:searchRef,status:'complete',selection_enabled:false,
    received_offers:2,mapped_offers:2,hotels:[{local_id:101,mapping_status:'resolved',tours:[valid,second]}]}};
}

async function runCase({brokenIdentity=false}={}){
  const events=[];
  const owner={
    reset(){},clearOffers(){},upsertLegacyOffer(){},refresh(){},read(raw){return raw;},async readProfile(){return null;}
  };
  const localProvider={parse(){return {hotels:[]};},apply(){}};
  let timerId=0;
  const fetch=async(url,options={})=>{
    const target=new URL(url,'https://anytoour.ru/');
    if(target.pathname==='/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'){
      const body=JSON.parse(options.body);
      return {ok:true,json:async()=>andromedaPayload(body,{brokenIdentity})};
    }
    if(target.pathname==='/_preview/search3-local-candidate/data/search3-local-results-read-v1.php'){
      const params=JSON.parse(options.body).params;
      return {ok:true,json:async()=>({ok:true,data:{source:'anytour-db-first-results-v1',scopeVersion:1,scope:{...params,scopeVersion:1},selectionAuthority:false,hotels:[],hotelCount:0,offerCount:0,storedOfferCount:0,providerOfferCounts:{}}})};
    }
    throw new Error('unexpected fetch '+target.pathname);
  };
  const rt={setSearchId(){},api:async(action)=>{
    if(action==='search_start')return {searchId:9001};
    throw new Error('unexpected API '+action);
  }};
  const win={
    V2Runtime:rt,V2_CONFIG:{andromedaApi:'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'},
    Search3CanonicalProfilesV1:{create(){return owner;}},AnyTourLocalDbProviderV1:localProvider,
    location:new URL('https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'),fetch,
    crypto:crypto.webcrypto,TextEncoder,setTimeout:()=>++timerId,clearTimeout:()=>{}
  };
  const sandbox={window:win,fetch,URL,URLSearchParams,AbortController,DOMException,structuredClone,console,
    crypto:crypto.webcrypto,TextEncoder,Date,setTimeout:win.setTimeout,clearTimeout:win.clearTimeout};
  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync(path.join(rootDir,'v2/prototype-search/data.js'),'utf8'),sandbox,{filename:'prototype-search/data.js'});
  const data=win.AnyTourPrototypeData;
  data.catalog.departures.push({id:1,name:'Москва'});
  data.catalog.countries.push({id:4,name:'Турция'});
  await data.search(structuredClone(trip),event=>events.push(event));
  await flush();
  data.stop();
  return events;
}

(async()=>{
  const scoped=await runCase();
  const receipt=scoped.find(event=>event.type==='provider'&&event.provider==='andromeda'&&event.status==='complete');
  assert.ok(receipt,'one out-of-scope row must not erase valid Andromeda inventory');
  assert.equal(receipt.receivedOffers,2);
  assert.equal(receipt.mappedOffers,2);
  assert.equal(receipt.visibleOffers,1);
  assert.equal(receipt.visibleHotels,1);
  assert.equal(receipt.scopeFilteredOffers,1);
  assert.equal(receipt.offers,1);
  assert.equal(receipt.hotels,1);

  const broken=await runCase({brokenIdentity:true});
  assert.ok(broken.some(event=>event.type==='provider'&&event.provider==='andromeda'&&event.status==='error'),
    'identity corruption must still fail the whole direct Andromeda source closed');
  console.log('search3 prototype Andromeda scope: ok');
})().catch(error=>{console.error(error);process.exitCode=1;});
