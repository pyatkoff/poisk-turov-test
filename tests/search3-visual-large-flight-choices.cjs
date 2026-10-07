'use strict';
// The real NEXT assets and existing fictional supplier fixture. No external requests.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),vm=require('node:vm');
const {execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const {fixture,trip}=require('./search3-visual-live-fixture.cjs');
const root=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||path.join(__dirname,'../v2')),base='/_preview/search3-next-candidate/';
const flightRef=i=>'flight_'+(i+1).toString(16).padStart(32,'0');
function choices(seed,count=132){return Array.from({length:count},(_,i)=>({...seed[i<count/2?0:1],name:'TEST SAMO '+(i<count/2?'OUT':'BACK')+' '+(i+1),flight_ref:flightRef(i)}));}
function parserChecks(){
 const source=fs.readFileSync(path.join(root,'prototype-search/data.js'),'utf8');
 const start=source.search(/\bfunction andromedaPoint\(/),offset=source.slice(start).search(/\bfunction hasAndromedaQuoteAttempt\(/),end=start+offset;
 assert(start>0&&end>start,'use the actual canonical normalizer, not a substitute');
 const parse=vm.runInNewContext(source.slice(start,end)+'\nnormalizeAndromedaQuote;');
 const seed=[{direction:'0',name:'TEST OUT'},{direction:'1',name:'TEST BACK'}];
 const response=count=>({schema_version:1,provider:'andromeda',expires_at:Math.floor(Date.now()/1000)+900,local_id:101,selection_enabled:true,booking_enabled:false,state:'flight_selection_required',quote_state:'unverified',final_price:null,final_price_verified:false,flight_selection_required:true,flights:choices(seed,count)});
 for(const count of [2,100,132,1000]){const quote=parse(response(count),101);assert(quote,`valid ${count}-option SAMO response rejected`);assert.equal(quote.flights.length,count);assert.equal(quote.flights.at(-1).flightRef,flightRef(count-1));}
 assert.equal(parse(response(1001),101),null,'pending options remain bounded');
 for(const mutate of [x=>delete x.expires_at,x=>x.expires_at=Math.floor(Date.now()/1000),x=>x.expires_at='9999999999',x=>x.flights[131].flight_ref=x.flights[0].flight_ref,x=>x.flights[131].flight_ref='supplier-uid',x=>x.flights.forEach(f=>f.direction='0'),x=>x.booking_enabled=true,x=>x.local_id=102]){const bad=response(132);mutate(bad);assert.equal(parse(bad,101),null,'malformed quote must remain rejected');}
 const fact={amount:'2000.50',currency:'RUB',source:'andromeda_transport_detail',aggregation:'unknown'};
 for(const [value,expected] of [[fact,fact],[{...fact,amount:'0'}, {...fact,amount:'0'}],[{...fact,currency:'USD'}, {...fact,currency:'USD'}],
  [undefined,null],[null,null],[{...fact,amount:0},null],[{...fact,amount:'-1'},null],[{...fact,amount:'1,00'},null],
  [{...fact,amount:'1.001'},null],[{...fact,currency:'<RUB>'},null],[{...fact,source:'other'},null],[{...fact,aggregation:'sum'},null]]){
  const raw=response(2);raw.flights.forEach(f=>f.transport_markup_reported=value&&{...value,uid:'private-not-public'});
  const quote=parse(raw,101);assert(quote,'optional money cannot break a valid flight choice');
  assert.deepEqual(JSON.parse(JSON.stringify(quote.flights[0].transportMarkupReported)),expected);
  assert.equal(quote.finalPrice,null);assert.equal(quote.finalPriceVerified,false,'neither repeated nor zero markup grants gross-price authority');
  assert.doesNotMatch(JSON.stringify(quote),/private-not-public/);
 }
 const verified=response(132);Object.assign(verified,{state:'quote_verified',quote_state:'verified',final_price:{amount:'125500',currency:'RUB'},final_price_verified:true,flight_selection_required:false});
 assert.equal(parse(verified,101),null,'do not widen the completed-itinerary bound');
 verified.flights=seed;assert.equal(parse(verified,101).finalPrice.amount,'125500');
 console.log('PASS SAMO quote parser: 132/1000 choices, native money/zero/unknown, bounds, refs, directions, identity and final price');
}
async function run(){
 if(process.env.SAMO_TEST_BROWSER_ONLY!=='1')parserChecks();
 if(process.env.SAMO_TEST_PARSER_ONLY==='1')return;
 const evidence=path.resolve(process.env.SAMO_EVIDENCE_DIR||'visual-live-evidence/large-flight-choices');fs.mkdirSync(evidence,{recursive:true});
 const server=http.createServer((req,res)=>{
  const u=new URL(req.url,'http://fixture');if(!u.pathname.startsWith(base)){res.writeHead(404).end();return;}
  const local=path.resolve(root,u.pathname.slice(base.length)||'index.php');if(!local.startsWith(root+'/')){res.writeHead(403).end();return;}
  const file=fs.existsSync(local)&&fs.statSync(local).isDirectory()?path.join(local,'index.php'):local;
  if(!fs.existsSync(file)){res.writeHead(404).end();return;}
  if(file.endsWith('.php')){const html=execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php"; include $argv[1];',file]);res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':'application/octet-stream');res.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const origin='http://127.0.0.1:'+server.address().port;
 let browser;const receipts=[];
 try{
  browser=await chromium.launch(process.env.TEST_CHROMIUM?{executablePath:process.env.TEST_CHROMIUM}:{});
  for(const width of [390,768,1280]){
   const anexEstimate=width===390?121000:123000,transport=fixture({anexZeroSurcharge:width===390}),errors=[],forbidden=[],context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();
   transport.state.samoFlightChoice=true;page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));
   let supplied=[];
   await page.route('**/*',async route=>{
    const req=route.request(),u=new URL(req.url());
    if(u.origin!==origin){forbidden.push('external_request');await route.abort();return;}
    if(u.pathname==='/test-photo.svg'){await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'});return;}
    if(u.pathname.startsWith(base)&&!u.pathname.includes('/data/')){await route.continue();return;}
    try{
     const value=await transport.json(req.url(),{body:req.postData()});
     if(u.pathname.endsWith('/api-andromeda-quote-preview.php')&&value.ok){
      const body=JSON.parse(req.postData());
      if(body.action==='quote'){supplied=choices(value.data.flights);value.data.flights=supplied;}
      else if(body.action==='quote_select_flights'){
       assert.equal(body.flight_selection.outbound_ref,flightRef(65));assert.equal(body.flight_selection.return_ref,flightRef(131));
       value.data.flights=[supplied[65],supplied[131]].map(({flight_ref,...f})=>f);
      }
     }
     await route.fulfill({status:value.ok===false?502:200,contentType:'application/json',body:JSON.stringify(value)});
    }catch(e){forbidden.push(e.message);await route.abort();}
   });
   try{
    await page.goto(origin+base+'visual-search/?'+new URLSearchParams({...trip,ages:''}));
    await page.waitForFunction(()=>!document.querySelector('.search-submit').disabled);
    assert.equal(transport.calls.filter(c=>c.action==='search_start').length,0);
    await page.locator('.search-submit').click();await page.waitForFunction(()=>document.querySelector('#results-summary').textContent.includes('3 варианта'));
    const startsFor=name=>transport.calls.filter(c=>c.url.endsWith('/api-'+name+'-search3-preview.php')&&(!c.action||c.action==='search'));
    for(const name of ['anex','andromeda'])assert.equal(startsFor(name).length,1,'one initial batch per provider');
    const choose=async provider=>{
     await page.locator('[data-action="all-offers"][data-id="501"]').first().click();
     const offer=page.locator('#modal-body [data-action="offer"][data-key^="'+provider+'%3A"]').first();
     await offer.waitFor({state:'visible'});
     await offer.click();
    };
    const application=async tag=>{
     const before=transport.calls.length;
     await page.locator('[name="phone"]').fill('+7 999 123-45-67');await page.locator('[name="consent"]').check();
     await page.locator('[type="submit"][form="prototype-lead-form"]').click();
     await page.waitForFunction(()=>document.querySelector('#prototype-lead-form')?.dataset.checked==='1');
     assert.match(await page.locator('.lead-message').textContent(),/не отправлена/i);
     assert.equal(transport.calls.length,before,'application checking must not request a provider or send contacts');
     assert.equal(await page.locator('#modal').evaluate(el=>el.scrollWidth>el.clientWidth+1),false);
     await page.screenshot({path:path.join(evidence,`${tag}-${width}.png`)});
    };
    await choose('andromeda');await page.locator('[data-action="refresh-hotel"]').click();
    await page.waitForFunction(()=>!!document.querySelector('[data-action="apply-andromeda-flights"]')||!!document.querySelector('#modal-body [role="alert"]'));
    assert.equal(await page.locator('[name="andromeda-outbound"]').count(),66,'all 66 outbound choices must reach NEXT');
    assert.equal(await page.locator('[name="andromeda-return"]').count(),66,'all 66 return choices must reach NEXT');
    await page.locator('[name="andromeda-outbound"]').last().check();await page.locator('[name="andromeda-return"]').last().check();
    await page.locator('#modal-footer').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(evidence,`samo-flights-${width}.png`)});
    await page.locator('[data-action="apply-andromeda-flights"]').click();
    await page.locator('[data-action="andromeda-application-preview"]').click();
    const summary=await page.locator('#modal-body').textContent();assert.match(summary,/TEST SAMO OUT 66/);assert.match(summary,/TEST SAMO BACK 132/);assert.match(summary,/SAMO STANDARD/);
    await application('samo-application');assert.match((await page.locator('.lead-message').textContent()).replace(/\s/g,''),/125500/);
    assert.equal(transport.calls.filter(c=>c.url.endsWith('/api-andromeda-quote-preview.php')).length,2);
    await page.locator('[data-action="close-modal"]').click();
    await choose('tourvisor');assert.equal(transport.calls.filter(c=>c.action==='tour'||c.action==='flights').length,0,'opening the tour only shows its exact conditions');
    await page.locator('[data-action="start-tour-flights"]').click();await page.locator('[name="flight-pair"][value="1"]').check();await page.locator('[data-action="apply-flight"]').click();
    assert.equal(transport.calls.filter(c=>c.action==='tour').length,1);assert.equal(transport.calls.filter(c=>c.action==='flights').length,1);
    await page.locator('#prototype-lead-form').waitFor();await application('tourvisor-application');
    await page.locator('[data-action="close-modal"]').click();
    await choose('anex');await page.locator('[data-action="refresh-hotel"]').click();
    await page.waitForFunction(()=>document.querySelector('#modal-title').textContent==='Ваш тур в деталях'&&document.querySelector('#modal-body').textContent.includes('ANEX CONCRETE'));
    assert.equal(await page.locator('#all-offers-list').count(),0,'single expanded ANEX offer opens directly');
    assert.equal(transport.calls.filter(c=>c.action==='offer').length,0,'direct entry retains explicit concrete verification');
    await page.locator('[data-action="refresh-hotel"]').click();await page.locator('[data-action="anex-additional-prices"]').click();
    await page.locator('[data-action="anex-application-preview"]').click();await application('anex-application');
    assert.match(await page.locator('.lead-message').textContent(),/Расчётная сумма.*требует подтверждения/);
    assert((await page.locator('.lead-message').textContent()).replace(/\s/g,'').includes(String(anexEstimate)),'evidenced zero surcharge keeps the search amount and permits preview application');
    assert.equal(transport.calls.filter(c=>c.action==='search_start').length,1);
    assert.equal(startsFor('andromeda').length,1);assert.equal(startsFor('andromeda')[0].body.page,1);
    const anexStarts=startsFor('anex');assert.equal(anexStarts.length,1,'selected hotel expands the retained initial group without another search');
    const anexExpands=transport.calls.filter(c=>c.url.endsWith('/api-anex-search3-preview.php')&&c.action==='expand');
    assert.equal(anexExpands.length,1,'one explicit selected-hotel expansion');
    assert.equal(anexExpands[0].body.generation,anexStarts[0].body.generation);
    assert.equal(anexExpands[0].body.search_ref,'a'.repeat(32));assert.equal(anexExpands[0].body.offer_ref,'anex_online:'+'b'.repeat(64));
    assert.equal(String(anexExpands[0].body.local_hotel_id),'101');
    assert(!transport.calls.some(c=>c.action==='search_continue'||c.action==='continue'||/lead|payment|booking/.test(c.url)));
    assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
    receipts.push({width,samo_choices:132,samo_selected:[66,132],samo_quote_calls:2,samo_final:125500,tourvisor_final:133500.5,anex_estimate:anexEstimate,anex_zero_surcharge:width===390,three_application_checks:true,initial_searches_per_source:1,anex_explicit_selected_hotel_searches:0,anex_retained_group_expands:1,continue_calls:0,supplier_calls:0,lead_calls:0});
   }catch(error){await page.screenshot({path:path.join(evidence,`failure-${width}.png`)}).catch(()=>{});throw error;}
   finally{await context.close();}
  }
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify({live_data:false,physical_device:false,results:receipts},null,2));
 console.log('PASS large flight choices through NEXT to all three preview applications',JSON.stringify(receipts));
}
module.exports=run;
if(require.main===module)run().catch(e=>{console.error(e);process.exitCode=1;});
