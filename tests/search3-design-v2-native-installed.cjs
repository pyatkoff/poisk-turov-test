'use strict';
// Installed HTTPS presentation evidence with an explicit fictional data seam.
// No supplier, canonical API, analytics or delivery endpoint is permitted.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium,request:playwrightRequest}=require('playwright');
const {fixture,fixtureBridge,manifest:compiledManifest,sourceSHA,hash,base,retainedMedia,mediaByURL}=require('./search3-design-v2-native-browser.cjs');
const TARGET='https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/';
const INSTALLED=Object.freeze({source:'2344c8533a5175cbb9f6843f5324248dd9562784',release:'fa3f0453cc2f79fab93b2fdff3b0f9f9cb3aa6a0',publishRun:39390835598,manifestSHA256:'1466712517312614b838df11f6841b4e5012ca8e69a82118ea52ea1e9fbbd96e'});
const MANIFEST_URL=TARGET+'native-v2/source-manifest.json';
const out=path.resolve(process.env.SEARCH3_NATIVE_INSTALLED_EVIDENCE||'');
assert(process.env.SEARCH3_NATIVE_INSTALLED_EVIDENCE,'Explicit installed evidence directory required');
assert.notEqual(out,path.resolve(process.env.SEARCH3_NATIVE_EVIDENCE),'Installed evidence is separate from compiled evidence');
const origin=new URL(TARGET).origin;
const forbidden=/\/(?:api(?:[^/]*)|lead-(?:adapter|bridge|receiver)[^/]*)\.(?:php|json)(?:$|\?)/i;
const nativePrefix=base+'/visual-search/native-v2/';
const bridgePath=nativePrefix+'bridge.js';
const noOp=Buffer.from('/* Controlled installed presentation evidence: canonical transport disabled. */\n');
const fixtureScript=Buffer.from('('+fixtureBridge.toString()+')('+JSON.stringify(fixture)+');');
const report={schema:1,status:'running',testSourceSHA:sourceSHA,installed:INSTALLED,target:TARGET,
  scope:'Installed HTTPS native presentation, deterministic fictional bridge, Chromium 430/1280 at 100%; not live supplier or delivery authority',
  fixture:fixture.label,clock:'2026-10-12T08:00:00Z',widths:[430,1280],height:900,textPercent:100,
  manifest:null,graph:[],publicGETs:[],replacements:[],network:[],cases:[],unexpectedRequests:[],
  exclusions:{supplierSearches:0,supplierQuotes:0,canonicalAPIRequests:0,realLeadSubmissions:0,analyticsRequests:0,physicalSafari:'not_measured',liveAuthority:'not_measured'}};
const allowed=new Map();
function safeURL(value){
  const url=new URL(value);
  assert.equal(url.origin,origin,'Fixed HTTPS origin only');
  assert.equal(url.protocol,'https:');assert.equal(url.username,'');assert.equal(url.password,'');assert.equal(url.hash,'');
  assert(!forbidden.test(url.pathname),'No API or delivery path');
  const spec=allowed.get(url.pathname);assert(spec,'Only pinned public graph paths: '+url.pathname);
  assert(url.search===''||spec.sha256&&url.search==='?v='+spec.sha256.slice(0,12),'Only the pinned static version query');
  return{url,spec};
}
function installGraph(manifest){
  assert.equal(manifest.schema,2);assert.equal(manifest.donorSource,'00fe6734c08acf234756bb59e5b61dbfecf41941');
  assert.equal(manifest.legacyPresentationLoaded,false);assert.equal(manifest.mockSimulatorLoaded,false);
  assert.deepEqual(manifest.files,compiledManifest.files,'Installed native bytes equal this final test source build');
  const rows=[...manifest.graph.scripts.map(row=>({...row,kind:'script'})),...manifest.graph.styles.map(row=>({...row,kind:'style'}))];
  assert.equal(manifest.graph.scripts.length,16);assert.equal(manifest.graph.styles.length,3);
  const paths=new Set();
  for(const row of rows){
    assert(/^[0-9a-f]{64}$/.test(row.sha256));assert(Number.isSafeInteger(row.bytes)&&row.bytes>0);
    assert(!row.path.startsWith('/')&&!row.path.split('/').includes('..'));
    const url=new URL(row.src,TARGET);assert.equal(url.origin,origin);assert.equal(url.search,'');
    assert.equal(url.pathname,base+'/'+row.path);assert(!forbidden.test(url.pathname));assert(!paths.has(url.pathname));paths.add(url.pathname);
    assert(row.kind==='script'?url.pathname.endsWith('.js'):url.pathname.endsWith('.css'));
    allowed.set(url.pathname,row);
  }
  report.graph=rows;return rows;
}
allowed.set(new URL(TARGET).pathname,{kind:'entry'});
allowed.set(new URL(MANIFEST_URL).pathname,{kind:'manifest',sha256:INSTALLED.manifestSHA256});
function renderedEntry(manifest){
  const entryRoot=path.join(path.resolve(process.env.SEARCH3_NATIVE_ASSET_ROOT),'visual-search');
  const sourceHTML=fs.readFileSync(path.join(entryRoot,'index.html')),sourcePHP=fs.readFileSync(path.join(entryRoot,'index.php'));
  assert.equal(hash(sourceHTML),manifest.entry.html,'Reviewed installed HTML source pin');
  assert.equal(hash(sourcePHP),manifest.entry.php,'Reviewed installed PHP renderer pin');
  assert.deepEqual(manifest.entry,compiledManifest.entry,'Installed entry sources equal the final compiled entry');
  const versions=new Map([...manifest.graph.scripts,...manifest.graph.styles].map(row=>[row.src,row.sha256.slice(0,12)]));
  const transformed=[];
  const expected=sourceHTML.toString('utf8').replace(/\b(src|href)="(\.\.?\/[^"?]+\.(?:js|css))"/g,(whole,attribute,relative)=>{
    assert(versions.has(relative),'Only a reviewed graph attribute receives a version pin');
    transformed.push(relative);return attribute+'="'+relative+'?v='+versions.get(relative)+'"';
  });
  assert.equal(transformed.length,19);assert.equal(new Set(transformed).size,19);
  return{body:Buffer.from(expected),sourceHTMLSHA256:hash(sourceHTML),sourcePHPSHA256:hash(sourcePHP)};
}
function verifyEntry(bytes,manifest){
  const html=bytes.toString('utf8'),expected=renderedEntry(manifest);
  assert(bytes.equals(expected.body),'Exact installed entry DOM and reviewed PHP version transform');
  assert(/<meta\s+name="robots"\s+content="noindex,nofollow,noarchive"/i.test(html),'Installed entry remains noindex');
  assert(!/<meta[^>]+http-equiv\s*=\s*["']?refresh/i.test(html));
  const scripts=[...html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)];
  assert.equal(scripts.length,manifest.graph.scripts.length);
  const actualScripts=scripts.map(([,attrs,inline])=>{assert.equal(inline.trim(),'','No inline executable code');const src=attrs.match(/\bsrc="([^"]+)"/);assert(src);return new URL(src[1],TARGET).href;});
  const actualStyles=[...html.matchAll(/<link\b[^>]*\brel="stylesheet"[^>]*\bhref="([^"]+)"[^>]*>/gi)].map(m=>new URL(m[1],TARGET).href);
  const urls=rows=>rows.map(row=>new URL(row.src+'?v='+row.sha256.slice(0,12),TARGET).href);
  assert.deepEqual(actualScripts,urls(manifest.graph.scripts),'Exact installed script graph and version pins');
  assert.deepEqual(actualStyles,urls(manifest.graph.styles),'Exact installed stylesheet graph and version pins');
  assert(!/<(?:iframe|object|embed)\b/i.test(html),'No nested browsing or plugin content');
  return{sha256:hash(bytes),bytes:bytes.length,exactSourceTransform:true,sourceHTMLSHA256:expected.sourceHTMLSHA256,sourcePHPSHA256:expected.sourcePHPSHA256,scripts:actualScripts,styles:actualStyles};
}
function writeReport(){fs.writeFileSync(path.join(out,'acceptance.json'),JSON.stringify(report,null,2)+'\n');}
function calls(page){return page.evaluate(()=>structuredClone(window.__nativeFixture.calls));}
async function capture(page,row,name){
  await page.evaluate(()=>document.fonts.ready);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false,'No document horizontal overflow: '+name);
  const nameFile='installed-'+name+'-'+row.width+'-text100.png',file=path.join(out,nameFile);
  await page.screenshot({path:file,fullPage:true,animations:'disabled'});
  const bytes=fs.readFileSync(file);row.frames.push({file:nameFile,sha256:hash(bytes),bytes:bytes.length});
}
async function run(){
  fs.mkdirSync(out,{recursive:true});
  const cache=new Map();let installedManifest;
  const api=await playwrightRequest.newContext({ignoreHTTPSErrors:false,timeout:20000});
  async function fetchPublic(value){
    const {url,spec}=safeURL(value),key=url.pathname;
    if(cache.has(key))return cache.get(key);
    const response=await api.get(url.href,{maxRedirects:0,failOnStatusCode:false,headers:{Accept:spec.kind==='entry'?'text/html':'*/*'}});
    assert.equal(response.status(),200,'Public static GET: '+url.pathname);assert.equal(response.url(),url.href,'No redirect');
    const body=await response.body(),headers=response.headers(),sha256=hash(body);
    if(spec.sha256)assert.equal(sha256,spec.sha256,'Installed body hash: '+key);
    if(spec.bytes)assert.equal(body.length,spec.bytes,'Installed body length: '+key);
    if(spec.kind==='script')assert(/javascript/.test(headers['content-type']||''));
    if(spec.kind==='style')assert(/text\/css/.test(headers['content-type']||''));
    if(spec.kind==='entry'){
      assert(/text\/html/.test(headers['content-type']||''));report.entry=verifyEntry(body,installedManifest);
    }
    const saved={body,headers,status:response.status(),sha256};cache.set(key,saved);
    report.publicGETs.push({url:url.href,status:response.status(),redirects:0,sha256,bytes:body.length,kind:spec.kind});return saved;
  }
  let browser;
  try{
    const actualManifest=await fetchPublic(MANIFEST_URL);
    installedManifest=JSON.parse(actualManifest.body);installGraph(installedManifest);
    report.manifest={url:MANIFEST_URL,sha256:actualManifest.sha256,bytes:actualManifest.body.length};
    // All graph resources are fetched and pinned before any installed script runs.
    await fetchPublic(TARGET);
    for(const asset of report.graph)await fetchPublic(new URL(asset.src+'?v='+asset.sha256.slice(0,12),TARGET).href);
    browser=await chromium.launch({headless:true});report.browser=browser.version();
    for(const width of [430,1280]){
      const row={width,height:900,textPercent:100,frames:[],errors:[],proof:{},status:'running'};report.cases.push(row);
      const context=await browser.newContext({viewport:{width,height:900},locale:'ru-RU',timezoneId:'UTC',reducedMotion:'reduce',serviceWorkers:'block'});
      let page;
      await context.route('**/*',async route=>{
        const req=route.request(),value=req.url(),entry={width,url:value,method:req.method(),resourceType:req.resourceType()};report.network.push(entry);
        try{
          assert.equal(req.method(),'GET','GET static resources only');
          const media=mediaByURL.get(value);
          if(media){entry.action='retained-media-substitution';entry.sha256=media.sha256;await route.fulfill({status:200,contentType:'image/jpeg',body:media.body});return;}
          const {url,spec}=safeURL(value);const actual=await fetchPublic(value);
          let body=actual.body;
          if(url.pathname===bridgePath){body=fixtureScript;entry.action='fixture-bridge';}
          else if(spec.kind==='script'&&!url.pathname.startsWith(nativePrefix)&&!url.pathname.endsWith('/lead-form-guard-v1.js')){body=noOp;entry.action='canonical-script-disabled';}
          else entry.action='verified-installed-bytes';
          if(body!==actual.body){const change={path:url.pathname,originalSHA256:actual.sha256,replacementSHA256:hash(body),reason:entry.action};if(!report.replacements.some(r=>r.path===change.path))report.replacements.push(change);}
          entry.servedSHA256=hash(body);
          const headers={...actual.headers,'content-length':String(body.length),'cache-control':'no-store'};
          delete headers['content-encoding'];delete headers['transfer-encoding'];
          await route.fulfill({status:200,headers,body});
        }catch(error){entry.action='blocked';entry.error=error.message;report.unexpectedRequests.push(entry);await route.abort('blockedbyclient');}
      });
      try{
        page=await context.newPage();page.setDefaultTimeout(10000);page.setDefaultNavigationTimeout(20000);
        page.on('pageerror',error=>row.errors.push(error.message));
        await page.clock.setFixedTime(new Date(report.clock));
        await page.goto(TARGET,{waitUntil:'networkidle'});
        assert.equal(page.url(),TARGET);const product=page.locator('[data-product]:visible');const screen=name=>product.locator('[data-screen="'+name+'"]');
        await screen('search').waitFor({state:'visible'});assert.equal((await calls(page)).search,0);
        await capture(page,row,'search');
        await product.locator('[data-search-nights-toggle]').click();await product.locator('[data-native-night="10"]').click();
        await product.locator('[data-search-editor-cancel="nights"]').click();assert.equal(await product.locator('[data-search-night-value]').innerText(),'7 ночей');
        await product.locator('[data-search-travelers-toggle]').click();await product.locator('[data-search-adult="-1"]').click();
        await product.locator('[data-search-editor-cancel="travelers"]').click();assert.equal(await product.locator('[data-search-travelers-value]').innerText(),'2 взрослых');
        await product.locator('[data-search-date-toggle]').click();await capture(page,row,'popup-calendar');
        await product.locator('[data-search-calendar-close]').click();assert.equal((await calls(page)).search,0);
        await screen('search').locator('button[type="submit"]').click();await screen('results').waitFor({state:'visible'});
        assert.equal(await product.locator('.st-hotel-card').count(),4);assert.equal((await calls(page)).search,1);
        assert.deepEqual(await product.locator('[data-result-count]').evaluate(node=>({focused:document.activeElement===node,outline:getComputedStyle(node).outlineStyle})),{focused:true,outline:'none'});
        await product.locator('[data-favorite="1"]').click();assert.equal(await product.locator('[data-favorite="1"]').getAttribute('aria-pressed'),'true');
        await capture(page,row,'results');
        if(width<=760)await product.locator('[data-native-calendar]').click();
        const finalDate=product.locator('[data-native-day="2026-10-25"]');
        const visibleDate=()=>finalDate.evaluate(node=>{const r=node.getBoundingClientRect(),p=node.closest('[data-native-calendar-strip]').getBoundingClientRect();return r.width>0&&r.left>=p.left-1&&r.right<=p.right+1;});
        for(let attempt=0;attempt<6;attempt++){
          if(await finalDate.count()&&await visibleDate())break;
          const next=product.locator('[data-native-calendar-page="next"]');assert(await next.isEnabled(),'Loaded final date remains reachable');await next.click();
        }
        assert(await finalDate.count()&&await visibleDate(),'Last loaded priced day reached without locator auto-scroll');
        assert.equal(await finalDate.locator('.st-pc-full').innerText(),'166 400 ₽');await capture(page,row,'calendar-later');
        if(width<=760)await product.locator('[data-action="filters"]').first().click();
        await product.locator('[data-native-filter="meals"][value="Ультра всё включено"]:visible').first().check();
        if(width<=760)await product.locator('[data-action="apply-filters"]').click();
        assert.equal(await product.locator('.st-hotel-card').count(),2);assert.equal((await calls(page)).search,1);await capture(page,row,'filters-active');
        await product.locator('[data-active-chips] [data-native-remove-constraint="meals"]').click();assert.equal(await product.locator('.st-hotel-card').count(),4);
        await product.locator('[data-hotel-open="1"]').first().click();await screen('hotel').waitFor({state:'visible'});
        assert.equal((await calls(page)).quote,0,'Opening the installed hotel is passive');
        assert.equal(await product.locator('[data-hotel-name]').evaluate(node=>getComputedStyle(node).outlineStyle),'none');
        await capture(page,row,'hotel');
        await product.locator('[data-hotel-offers] [data-offer="fixture:offer:1"]').click();await screen('tour').waitFor({state:'visible'});
        await product.locator('[data-native-tour-flight="0"]').click();await product.locator('[data-action="accept-price"]:visible').first().click();
        assert.deepEqual(await page.evaluate(()=>({exact:window.__nativeFixture.getSelection().offer===window.__nativeFixture.hotels[0].offers[0],total:window.__nativeFixture.getSelection().total,choice:window.__nativeFixture.getSelection().flightChoiceId})),{exact:true,total:112000,choice:0});
        assert.match(await screen('tour').innerText(),/112\s*000/);assert.match(await screen('tour').innerText(),/09:20/);await capture(page,row,'tour');
        await product.locator('[data-action="lead"]:visible').first().click();await screen('lead').waitFor({state:'visible'});
        if(width<=760)await product.locator('[data-native-contact-facts] > summary').click();
        for(const value of ['FX 101','FX 102','09:20','13:20','13:00','17:00'])assert((await product.locator('[data-native-contact-facts]').innerText()).includes(value));
        await capture(page,row,'lead');
        const form=product.locator('[data-lead-form]');await form.locator('[name="name"]').fill('FICTIONAL QA');await form.locator('[name="phone"]').fill('+7 999 000 00 00');await form.locator('[name="consent"]').check();await form.locator('button[type="submit"]').click();
        await page.waitForFunction(()=>window.__nativeFixture.calls.preview===1);
        assert.deepEqual(await page.evaluate(()=>({total:window.__nativeFixture.payload.price,flight:window.__nativeFixture.payload.flightChoiceId,tour:window.__nativeFixture.payload.tourId,submit:window.__nativeFixture.calls.submit})),{total:112000,flight:0,tour:'fixture-tour:1',submit:0});await capture(page,row,'lead-preview');
        await page.goBack();await screen('tour').waitFor({state:'visible'});await page.goBack();await screen('hotel').waitFor({state:'visible'});
        assert.equal(await product.locator('[data-hotel-offers] .st-offer--selected [data-offer="fixture:offer:1"]').count(),1);
        await page.goForward();await screen('tour').waitFor({state:'visible'});await page.goForward();await screen('lead').waitFor({state:'visible'});
        assert.equal(await form.locator('[name="name"]').inputValue(),'FICTIONAL QA');assert.equal(await form.locator('[name="phone"]').inputValue(),'+7 999 000 00 00');assert.equal(await form.locator('[name="consent"]').isChecked(),true);
        assert.deepEqual(await page.evaluate(()=>JSON.parse(localStorage.getItem('anytour.prototype.v18.favorites.v1'))),[1]);
        const finalCalls=await calls(page);assert.deepEqual(finalCalls,{init:finalCalls.init,search:1,quote:1,expand:0,flight:[0],preview:1,submit:0});assert(finalCalls.init>=1);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),112000);await capture(page,row,'lead-return');
        row.proof={passiveSearches:0,explicitFixtureSearches:1,localFilterExtraOperations:0,calendarFinalLoadedTotal:166400,passiveHotelQuotes:0,explicitFixtureQuotes:1,exactOffer:'fixture:offer:1',tourId:'fixture-tour:1',adults:2,ages:[],selectedFullTotal:112000,originalFlightChoiceIndex:0,previewCount:1,realLeadSubmissions:0,backForwardRetained:true,draftRetained:true,consentRetained:true,favorites:[1],calls:finalCalls};
        assert.deepEqual(row.errors,[]);assert.deepEqual(report.unexpectedRequests,[]);row.status='passed';
      }catch(error){row.status='failed';row.error=error.stack||String(error);if(page)try{await page.screenshot({path:path.join(out,'failure-'+width+'-text100.png'),fullPage:true});}catch{}throw error;}
      finally{await context.close();writeReport();}
    }
    assert.deepEqual(report.unexpectedRequests,[]);report.status='passed';
  }catch(error){report.status='failed';report.error=error.stack||String(error);throw error;}
  finally{await browser?.close();await api.dispose();writeReport();}
}
if(process.argv.includes('--policy-only')){
  installGraph(compiledManifest);assert.throws(()=>safeURL(origin+'/api.php?action=search_start'));assert.throws(()=>safeURL('https://example.com/x.js'));assert.throws(()=>safeURL(TARGET+'native-v2/frontend.js?unknown=1'));safeURL(TARGET);safeURL(MANIFEST_URL);
  const rendered=renderedEntry(compiledManifest).body;verifyEntry(rendered,compiledManifest);
  const drift=Buffer.from(rendered.toString('utf8').replace('</body>','<div>UNREVIEWED ENTRY DRIFT</div></body>'));
  assert(!drift.equals(rendered));assert.throws(()=>verifyEntry(drift,compiledManifest),/Exact installed entry DOM/,'Changed static body must fail despite its unchanged script graph');
  console.log('Installed native static policy PASS; no browser or network used.');
}else run().then(()=>console.log('Installed native presentation acceptance PASS: 430/1280, controlled data only.')).catch(error=>{console.error(error.stack||error);process.exitCode=1;});
