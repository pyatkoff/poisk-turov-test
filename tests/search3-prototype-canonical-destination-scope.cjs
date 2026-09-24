'use strict';

const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

const source=fs.readFileSync(path.join(__dirname,'..','v2','prototype-search','data.js'),'utf8');

function harness(){
  const fetchCalls=[];
  const owner={
    clearOffers(){},upsertHotel(){},upsertOffer(){},upsertLegacyOffer(){},refresh(){},read(){return[];},
    async readProfile(){return null;}
  };
  const root={
    location:new URL('https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'),
    V2Runtime:{api:async()=>{throw new Error('unexpected legacy catalog API');},setSearchId(){},fetch(){}},
    Search3CanonicalProfilesV1:{create(){return owner;}},
    AnyTourLocalDbProviderV1:{parse(){return null;},apply(){}},
    crypto:require('node:crypto').webcrypto,
    TextEncoder,
    fetch:async(url,options={})=>{
      const target=new URL(url,root.location.href);fetchCalls.push({target,options});
      if(target.pathname.endsWith('/data/search3-destination-read-v1.php')){
        const action=target.searchParams.get('action');
        if(action==='countries')return {ok:true,json:async()=>({ok:true,source:'anytour-destination-identities-v1',provider:'tourvisor',kind:'country',items:[
          {id:101,kind:'country',parentId:null,name:'Турция',russianName:'Турция',slug:'turkey',revision:1,tourvisorIds:['4']}
        ]})};
        if(action==='regions')return {ok:true,json:async()=>({ok:true,source:'anytour-destination-identities-v1',provider:'tourvisor',kind:'region',parentId:101,items:[
          {id:201,kind:'region',parentId:101,name:'Белек',russianName:'Белек',slug:'belek',revision:1,tourvisorIds:['21','121']},
          {id:202,kind:'region',parentId:101,name:'Кемер',russianName:'Кемер',slug:'kemer',revision:1,tourvisorIds:['22']}
        ]})};
        throw new Error('unexpected destination action '+action);
      }
      if(target.pathname==='/data/hotel-search-v1.php'){
        assert.equal(target.searchParams.get('countryId'),'4','hotel lookup must use accepted Tourvisor country id');
        return {ok:true,json:async()=>({ok:true,items:[{id:301,country:{id:4},name:'Rixos Fixture'}]})};
      }
      if(target.pathname.endsWith('/data/hotel-details-read-v1.php')){
        assert.deepEqual(target.searchParams.getAll('legacyHotelIds[]'),['301']);
        return {ok:true,json:async()=>({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:['301'],
          items:[{catalog:'anytour',id:501,name:'Rixos Fixture',category:5,rating:4.8,region:'Белек',subRegion:'',images:[],primaryImage:'',description:''}],
          links:[{legacyHotelId:'301',anytourHotelId:501}],missingLegacyIds:[]})};
      }
      throw new Error('unexpected fetch '+url);
    }
  };
  const sandbox={window:root,fetch:root.fetch,URL,URLSearchParams,AbortController,DOMException,structuredClone,console,
    crypto:root.crypto,TextEncoder,setTimeout,clearTimeout};
  vm.createContext(sandbox);vm.runInContext(source,sandbox,{filename:'data.js'});
  const data=root.AnyTourPrototypeData;
  data.catalog.departures.push({id:1,name:'Москва'});
  return {data,fetchCalls};
}

test('local country and region IDs resolve to accepted Tourvisor request IDs',async()=>{
  const h=harness();
  const init=await h.data.countries('Москва');
  assert.equal(init.countries.length,1);
  assert.equal(init.countries[0].id,'101','UI/search state owns local country id');
  assert.deepEqual(Array.from(init.countries[0].tourvisorIds),['4']);

  const regions=await h.data.regions('101');
  assert.deepEqual(regions.map(row=>row.id),['201','202'],'region choices own local ids');
  assert.deepEqual(Array.from(regions[0].tourvisorIds),['21','121']);

  const search={origin:'Москва',country:'101',from:'2026-10-01',to:'2026-10-07',minNights:7,maxNights:7,adults:2,ages:[]};
  const params=h.data.params(search,[],{resorts:['Белек'],stars:[],meals:[],min:0,max:null});
  assert.equal(params.countryId,'4','supplier/LOCAL scope uses exact accepted Tourvisor country id');
  assert.deepEqual(Array.from(params.regionIds),['21','121'],'one local resort expands only to its accepted Tourvisor ids');
});

test('hotel lookup uses native country but returns hotel in local country scope',async()=>{
  const h=harness();await h.data.countries('Москва');await h.data.regions('101');
  const rows=await h.data.lookupHotels('rixos','101',new AbortController().signal);
  assert.equal(rows.length,1);
  assert.equal(rows[0].id,501);
  assert.equal(rows[0].country,'101','canonical hotel remains bound to local country identity');
});

test('missing accepted bridge fails closed instead of broadening search',async()=>{
  const h=harness();await h.data.countries('Москва');
  h.data.catalog.countries[0].tourvisorIds=[];
  const search={origin:'Москва',country:'101',from:'2026-10-01',to:'2026-10-07',minNights:7,maxNights:7,adults:2,ages:[]};
  assert.throws(()=>h.data.params(search,[],{resorts:[],stars:[],meals:[],min:0,max:null}),/нет подтверждённого соответствия Tourvisor/);
});

console.log('SEARCH3_CANONICAL_DESTINATION_SCOPE_OK');
