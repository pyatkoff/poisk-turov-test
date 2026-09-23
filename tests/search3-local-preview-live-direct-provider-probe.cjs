'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const TARGET='https://anytoour.ru/_preview/search3-local-candidate/prototype-search/';
const OUTPUT=process.env.SEARCH3_LOCAL_LIVE_OUTPUT || 'search3-live-provider-probe';
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
  assert(nav&&nav.status()===200);

  const result=await page.evaluate(async ({params})=>{
    const call=async(url,body)=>{
      const response=await fetch(url,{
        method:'POST',credentials:'same-origin',
        headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'AnyTourSearch3'},
        body:JSON.stringify(body)
      });
      let payload;
      try{payload=await response.json();}catch{payload={error:'non_json'};}
      return {status:response.status,payload};
    };
    const manifestResponse=await fetch('/_preview/search3-anex-candidate/anex-preview-manifest.json',{credentials:'same-origin',cache:'no-store'});
    let manifest=null;try{manifest=await manifestResponse.json();}catch{manifest={error:'non_json'};}
    return {
      manifest:{status:manifestResponse.status,payload:manifest},
      andromeda:await call('/_preview/search3-anex-candidate/api-andromeda-search3-preview.php',{generation:910003,params})
    };
  },{params});

  const safe={capturedAt:new Date().toISOString(),...result};
  fs.writeFileSync(path.join(OUTPUT,'provider-probe.json'),JSON.stringify(safe,null,2));
  console.log('SEARCH3_DIRECT_PROVIDER_PROBE '+JSON.stringify(safe));
  await context.close();await browser.close();
})().catch(e=>{console.error(e);process.exit(1);});
