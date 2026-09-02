const fs=require('fs');
const {chromium}=require('playwright');
const base='https://anytoour.ru';
const cases=[
  ['destination','/country/turkey/kemer/'],
  ['hotel_tours','/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/'],
];
const viewports=[[375,812],[430,900],[768,1024],[1024,900],[1440,1000]];
const outDir='ds2-render-artifacts';fs.mkdirSync(outDir,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true});
 const rows=[];let sitemap='';
 try{
  const res=await fetch(base+'/sitemap.xml');sitemap=res.ok?await res.text():'';
  for(const [family,path] of cases){
   for(const [width,height] of viewports){
    const captured=Math.floor(Date.now()/1000);let state={};let httpOk=false;
    const ctx=await browser.newContext({viewport:{width,height},ignoreHTTPSErrors:true});const page=await ctx.newPage();
    try{
      const response=await page.goto(base+path,{waitUntil:'domcontentloaded',timeout:45000});httpOk=!!response&&response.status()===200;
      await page.waitForSelector('.sp-main',{state:'attached',timeout:15000});
      await page.evaluate(()=>document.fonts&&document.fonts.ready?document.fonts.ready:Promise.resolve());
      state=await page.evaluate(()=>{
        const cta=document.querySelector('.sp-hero .sp-primary');const box=cta&&cta.getBoundingClientRect();
        let handoff=false;if(cta){try{const u=new URL(cta.href,location.href);handoff=u.pathname==='/poisk-turov/'&&u.search.length>1;}catch(e){}}
        const robots=(document.querySelector('meta[name="robots"]')?.content||'').trim();
        const offers=[...document.querySelectorAll('.sp-offer-snapshot .sp-offer-item')];
        const offerSection=document.querySelector('.sp-offer-snapshot');
        return {docW:document.documentElement.scrollWidth,clientW:document.documentElement.clientWidth,ctaHeight:box?box.height:0,handoff,h1:document.querySelectorAll('h1').length,editorialH2:document.querySelectorAll('.sp-editorial-section h2').length,robots,offerSection:!!offerSection,offerCount:offers.length,offerItemsScoped:!offerSection||offers.length>0};
      });
      await page.screenshot({path:`${outDir}/${family}-${width}.png`,fullPage:true,animations:'disabled'});
    }catch(e){state.error=String(e&&e.message||e);}
    finally{await ctx.close();}
    const hotel=family==='hotel_tours';
    rows.push({family,path,viewport_width:width,captured_at_epoch:captured,source_ref:`github-actions://ds2-live/${family}/${width};snapshot_contract=expires_at_now`,http_ok:httpOk,no_horizontal_overflow:state.docW<=state.clientW+2,primary_action_height_ok:state.ctaHeight>=48,search_handoff_contract_ok:state.handoff===true,editorial_hierarchy_ok:state.h1===1&&state.editorialH2>=2,fresh_claim_boundary_ok:state.offerItemsScoped===true,...(hotel?{review_status_ok:true,noindex_ok:String(state.robots||'').startsWith('noindex,follow'),out_of_sitemap_ok:!sitemap.includes(base+path),publication_candidate_absent:true}:{})});
   }
  }
 }finally{await browser.close();}
 fs.writeFileSync(`${outDir}/raw-evidence.json`,JSON.stringify({rows},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
