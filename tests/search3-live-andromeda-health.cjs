'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');

const TARGET='https://anytoour.ru/_preview/search3-local-candidate/prototype-search/';
const OUTPUT=process.env.SEARCH3_LIVE_OUTPUT||'search3-live-andromeda-health';
const params={
  departureId:'1',countryId:'4',dateFrom:'2026-09-29',dateTo:'2026-10-05',
  nightsFrom:7,nightsTo:7,adults:2,childs:[],meal:'',hotelCategory:'',
  hotelRating:'',hotelTypes:[],hotelIds:[],hotelServices:[],arrivalId:'',
  regionIds:[],subregionIds:[],operatorIds:[],priceFrom:'',priceTo:'',
  currency:'RUB',onlyCharter:false,onlyDirect:false
};

(async()=>{
  fs.mkdirSync(OUTPUT,{recursive:true});
  const browser=await chromium.launch({headless:true});
  const context=await browser.newContext({viewport:{width:1200,height:800},extraHTTPHeaders:{'X-AnyTour-CI':'1'}});
  const page=await context.newPage();
  const nav=await page.goto(TARGET,{waitUntil:'domcontentloaded',timeout:45000});
  assert(nav&&nav.status()===200,'preview page must return 200');
  const result=await page.evaluate(async params=>{
    const response=await fetch('/_preview/search3-anex-candidate/api-andromeda-search3-preview.php',{
      method:'POST',credentials:'same-origin',cache:'no-store',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'AnyTourSearch3'},
      body:JSON.stringify({generation:910004,params})
    });
    let payload;try{payload=await response.json();}catch{payload={error:'non_json'};}
    return {status:response.status,payload};
  },params);
  const safe={capturedAt:new Date().toISOString(),status:result.status,
    ok:result.payload?.ok===true,
    provider:result.payload?.data?.provider??null,
    state:result.payload?.data?.status??null,
    page:result.payload?.data?.page??null,
    pagesCount:result.payload?.data?.pages_count??null,
    hotels:Array.isArray(result.payload?.data?.hotels)?result.payload.data.hotels.length:null,
    receivedOffers:result.payload?.data?.received_offers??null,
    mappedOffers:result.payload?.data?.mapped_offers??null,
    error:result.payload?.error??result.payload?.data?.error??null};
  fs.writeFileSync(path.join(OUTPUT,'result.json'),JSON.stringify(safe,null,2));
  console.log('SEARCH3_LIVE_ANDROMEDA_HEALTH '+JSON.stringify(safe));
  assert.equal(result.status,200,'direct Andromeda must no longer fail whole request');
  assert.equal(result.payload?.ok,true,'direct Andromeda response must be ok');
  assert.equal(result.payload?.data?.provider,'andromeda');
  assert(['complete','partial'].includes(result.payload?.data?.status),'direct Andromeda must return complete or partial');
  assert(Array.isArray(result.payload?.data?.hotels),'direct Andromeda must return validated hotel array');
  await context.close();await browser.close();
})().catch(error=>{console.error(error);process.exit(1);});
