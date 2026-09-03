const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.SEARCH3_PREVIEW_URL || 'https://anytoour.ru/_preview/search3/poisk-turov/';
const outDir = process.env.SEARCH3_QA_OUT || 'search3-visual-desktop-artifacts';
fs.mkdirSync(outDir, { recursive: true });
const required = ['d00-footer','01-search','02-results','03-expanded-hotel','04-tour-details','05-flights','06-final-review','07-lead-sending','08-lead-success','09-lead-error'];
const report = { mode:'desktop', url:baseUrl, startedAt:new Date().toISOString(), states:[], errors:[] };
const sleep = ms => new Promise(r=>setTimeout(r,ms));

async function visible(page, selector, timeout=15000){try{await page.locator(selector).first().waitFor({state:'visible',timeout});return true}catch(_){return false}}
async function attached(page, selector, timeout=15000){try{await page.locator(selector).first().waitFor({state:'attached',timeout});return true}catch(_){return false}}
async function clickVisible(page, selector, timeout=7000){const loc=page.locator(selector).first();try{if(!await loc.count()||!await loc.isVisible())return false;await loc.click({timeout});return true}catch(_){return false}}
async function settleImages(page, selector, timeout=5000){const end=Date.now()+timeout;while(Date.now()<end){const pending=await page.locator(selector).evaluateAll(xs=>xs.filter(x=>x&&x.tagName==='IMG'&&x.src&&!x.complete).length).catch(()=>0);if(!pending)return true;await sleep(150)}return false}
async function snap(page,name,anchor){try{if(anchor){const loc=page.locator(anchor).first();if(await loc.count())await loc.evaluate(el=>{const y=el.getBoundingClientRect().top+scrollY-10;scrollTo(0,Math.max(0,y))}).catch(()=>{});await sleep(150)}else{await page.evaluate(()=>scrollTo(0,0));await sleep(80)}const file=path.join(outDir,`${name}.png`);await page.screenshot({path:file,fullPage:false,animations:'disabled'});report.states.push({name,ok:true,file});console.log(`[desktop] ${name}`);return true}catch(e){report.states.push({name,ok:false,error:String(e)});report.errors.push(`${name}: ${String(e)}`);return false}}
async function footerCheck(page){const state=await page.evaluate(()=>{const f=document.querySelector('.ds2-site-footer');if(!f)return null;const c=getComputedStyle(f);return{background:c.backgroundColor,color:c.color}});report.footer=state;if(!state)throw new Error('desktop footer missing');if(state.background==='rgba(0, 0, 0, 0)'||state.background==='rgb(255, 255, 255)')throw new Error(`desktop footer is not dark: ${state.background}`)}
async function forceLeadState(page,state,detail={}){const r=await page.evaluate(({state,detail})=>{const form=document.querySelector('#selectedTour .lead-form');if(!form)return false;delete form.dataset.search3LeadState;window.dispatchEvent(new CustomEvent('search3:preview-lead-state',{detail:Object.assign({previewSimulation:true,state},detail)}));return form.dataset.search3LeadState===state&&!!form.querySelector('.search3-lead-status:not([hidden])')},{state,detail});return r&&visible(page,`#selectedTour .lead-form[data-search3-lead-state="${state}"] .search3-lead-status`,4000)}

(async()=>{
 const browser=await chromium.launch({headless:true});
 const context=await browser.newContext({viewport:{width:1440,height:1000},deviceScaleFactor:1,ignoreHTTPSErrors:true});
 const page=await context.newPage();page.setDefaultTimeout(10000);page.setDefaultNavigationTimeout(60000);
 page.on('pageerror',e=>report.errors.push(`pageerror: ${String(e)}`));
 page.on('console',m=>{if(m.type()==='error')report.errors.push(`console: ${m.text()}`)});
 try{
  const response=await page.goto(baseUrl,{waitUntil:'domcontentloaded',timeout:60000});report.http=response?response.status():0;
  await page.waitForSelector('body.search3-preview, #tourSearch',{timeout:20000});
  await page.addStyleTag({content:'*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}'});await sleep(500);
  await footerCheck(page);await snap(page,'d00-footer','.ds2-site-footer');await snap(page,'01-search');
  if(!await clickVisible(page,'#tourSearch .search-submit',10000))throw new Error('search submit missing');
  const hasResults=await visible(page,'#results .hotel-card',120000);const complete=hasResults&&await attached(page,'#status .search-progress-done',120000);report.search={submitted:true,hasResults,complete};if(!hasResults||!complete)throw new Error('search incomplete');
  await settleImages(page,'#results .hotel-card img');await snap(page,'02-results','#resultsTools');
  if(!await clickVisible(page,'#results .search3-show-tours'))throw new Error('show tours missing');if(!await visible(page,'#results .hotel-card .hotel-tours:not([hidden])',20000))throw new Error('tour list missing');await snap(page,'03-expanded-hotel','#results .hotel-card .hotel-tours:not([hidden])');
  if(!await clickVisible(page,'#results .hotel-tours:not([hidden]) .direct-tour'))throw new Error('direct tour missing');if(!await visible(page,'#selectedTour:not([hidden])',45000))throw new Error('selected tour missing');report.tourDetailsReady=await visible(page,'#selectedTour .selected-head h2, #selectedTour .selected-picture',45000);if(!report.tourDetailsReady)throw new Error('tour details loading');await settleImages(page,'#selectedTour img');await snap(page,'04-tour-details','#selectedTour');
  if(!await visible(page,'#selectedTour .tour-flights',30000))throw new Error('flights missing');if(!await visible(page,'#selectedTour .flight-variant',45000))throw new Error('flight variants missing');if(!await visible(page,'#selectedTour .search3-flight-continue button',6000))throw new Error('flight continue render missing');await snap(page,'05-flights','#selectedTour .tour-flights');
  if(!await clickVisible(page,'#selectedTour .search3-flight-continue button'))throw new Error('flight continue missing');if(!await visible(page,'#selectedTour.search3-final-review',10000))throw new Error('final review missing');await snap(page,'06-final-review','#selectedTour');
  report.leadStates={};report.leadStates.sending=await forceLeadState(page,'sending');await snap(page,'07-lead-sending','#selectedTour .lead-form');report.leadStates.success=await forceLeadState(page,'success',{leadId:'PREVIEW'});await snap(page,'08-lead-success','#selectedTour .lead-form');report.leadStates.error=await forceLeadState(page,'error');await snap(page,'09-lead-error','#selectedTour .lead-form');
 }catch(e){report.errors.push(String(e));console.error(e)}finally{report.finishedAt=new Date().toISOString();report.captureComplete=required.every(n=>report.states.some(s=>s.name===n&&s.ok));fs.writeFileSync(path.join(outDir,'report-desktop.json'),JSON.stringify(report,null,2));await context.close().catch(()=>{});await browser.close().catch(()=>{})}
 const lifecycle=report.leadStates&&report.leadStates.sending&&report.leadStates.success&&report.leadStates.error;const search=report.search&&report.search.hasResults&&report.search.complete;const pass=report.http>0&&report.http<400&&search&&report.captureComplete&&report.tourDetailsReady&&lifecycle&&report.errors.length===0;if(!pass){console.error('Desktop Search3 QA failed',JSON.stringify(report));process.exit(2)}
})().catch(e=>{console.error(e);process.exit(1)});
