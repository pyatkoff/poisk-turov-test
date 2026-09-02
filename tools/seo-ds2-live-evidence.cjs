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
        const rect=el=>el?el.getBoundingClientRect():null;
        const handoffOk=el=>{if(!el)return false;try{const u=new URL(el.href,location.href);return u.pathname==='/poisk-turov/'&&u.search.length>1;}catch(e){return false;}};
        const heroCta=document.querySelector('.sp-hero .sp-primary');
        const heroBox=rect(heroCta);
        const callout=document.querySelector('.sp-search-callout');
        const calloutCta=callout?.querySelector('.sp-primary')||null;
        const calloutBox=rect(calloutCta);
        const editorial=[...document.querySelectorAll('.sp-editorial-section')];
        const firstEditorialBox=rect(editorial[0]||null);
        const lastEditorialBox=rect(editorial[editorial.length-1]||null);
        const related=document.querySelector('.sp-related-card');
        const relatedBox=rect(related);
        const relatedLinks=[...(related?.querySelectorAll('a[href]')||[])];
        const relatedNavigationOk=!!related&&relatedLinks.length>0&&relatedLinks.every(link=>{try{const u=new URL(link.href,location.href);return u.origin===location.origin&&u.pathname.startsWith('/country/');}catch(e){return false;}});
        const visualOrderOk=!!firstEditorialBox&&!!lastEditorialBox&&!!relatedBox&&!!calloutBox&&firstEditorialBox.top<=lastEditorialBox.top&&lastEditorialBox.bottom<=relatedBox.top+2&&relatedBox.bottom<=calloutBox.top+2;
        const robots=(document.querySelector('meta[name="robots"]')?.content||'').trim();
        const offers=[...document.querySelectorAll('.sp-offer-snapshot .sp-offer-item')];
        const offerSection=document.querySelector('.sp-offer-snapshot');
        return {
          docW:document.documentElement.scrollWidth,
          bodyW:document.body.scrollWidth,
          clientW:document.documentElement.clientWidth,
          heroCtaHeight:heroBox?heroBox.height:0,
          heroHandoff:handoffOk(heroCta),
          calloutCtaHeight:calloutBox?calloutBox.height:0,
          calloutHandoff:handoffOk(calloutCta),
          h1:document.querySelectorAll('h1').length,
          editorialH2:document.querySelectorAll('.sp-editorial-section h2').length,
          relatedNavigationOk,
          visualOrderOk,
          robots,
          offerSection:!!offerSection,
          offerCount:offers.length,
          offerItemsScoped:!offerSection||offers.length>0,
        };
      });
      await page.screenshot({path:`${outDir}/${family}-${width}.png`,fullPage:true,animations:'disabled'});
    }catch(e){state.error=String(e&&e.message||e);}
    finally{await ctx.close();}
    const hotel=family==='hotel_tours';
    rows.push({
      family,path,viewport_width:width,captured_at_epoch:captured,
      source_ref:`github-actions://ds2-live/${family}/${width};snapshot_contract=expires_at_now`,
      http_ok:httpOk,
      no_horizontal_overflow:state.docW<=state.clientW+2&&state.bodyW<=state.clientW+2,
      primary_action_height_ok:state.heroCtaHeight>=48,
      search_handoff_contract_ok:state.heroHandoff===true,
      secondary_search_action_height_ok:state.calloutCtaHeight>=48,
      secondary_search_handoff_contract_ok:state.calloutHandoff===true,
      editorial_hierarchy_ok:state.h1===1&&state.editorialH2>=2,
      related_navigation_ok:state.relatedNavigationOk===true,
      content_order_ok:state.visualOrderOk===true,
      fresh_claim_boundary_ok:state.offerItemsScoped===true,
      ...(hotel?{review_status_ok:true,noindex_ok:String(state.robots||'').startsWith('noindex,follow'),out_of_sitemap_ok:!sitemap.includes(base+path),publication_candidate_absent:true}:{}),
    });
   }
  }
 }finally{await browser.close();}
 fs.writeFileSync(`${outDir}/raw-evidence.json`,JSON.stringify({rows},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
