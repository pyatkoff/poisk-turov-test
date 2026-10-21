'use strict';
// Installed HTTPS presentation evidence with an explicit fictional data seam.
// No supplier, canonical API, analytics or delivery endpoint is permitted.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium,request:playwrightRequest}=require('playwright');
const {fixture,fixtureBridge,terminalDataFixture,recordTerminalBridge,manifest:compiledManifest,sourceSHA,hash,base,retainedMedia,mediaByURL}=require('./search3-design-v2-native-browser.cjs');
const TARGET='https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/';
const mealOnly=process.argv.includes('--meal-only');
const PRIOR_INSTALLED=Object.freeze({source:'09f5e18d5eb40f4f62bb723a9fd9e4092f784060',release:'d8dab6b8748d70a0892b5b0c065c9de4b2676b4f',publishRun:39458975873,manifestSHA256:'4c9874357d026f428d53ea018bb2f78037322b1c1e3dfb3b5c08ea12d6080aed'});
// ROOT fills these only from the actual one-shot publication terminal receipt.
const MEAL_INSTALLED=Object.freeze({source:'4442cec9921d0888b43cc7a7137385f1e6223c48',release:'43c6a56e06a7fd634a603e24a890f745c5e49773',publishRun:39518137791,manifestSHA256:'839d40fef279afee958d447858eada8bfac5e536b99b7b76d7bf34d22f8f1d67'});
const INSTALLED=mealOnly?MEAL_INSTALLED:PRIOR_INSTALLED;
const MANIFEST_URL=TARGET+'native-v2/source-manifest.json';
const out=path.resolve(process.env.SEARCH3_NATIVE_INSTALLED_EVIDENCE||'');
assert(process.env.SEARCH3_NATIVE_INSTALLED_EVIDENCE,'Explicit installed evidence directory required');
assert.notEqual(out,path.resolve(process.env.SEARCH3_NATIVE_EVIDENCE),'Installed evidence is separate from compiled evidence');
const origin=new URL(TARGET).origin;
const forbidden=/\/(?:api(?:[^/]*)|lead-(?:adapter|bridge|receiver)[^/]*)\.(?:php|json)(?:$|\?)/i;
const nativePrefix=base+'/visual-search/native-v2/';
const bridgePath=nativePrefix+'bridge.js';
const localHelperPath=base+'/search3-local-db-provider-v1.js';
const MEAL_HELPER_SHA256='83dc1add39bde2e5d5360d52e36387a0197b71859b9e2a6a8a6324a641207036';
const noOp=Buffer.from('/* Controlled installed presentation evidence: canonical transport disabled. */\n');
const fixtureScript=Buffer.from('('+fixtureBridge.toString()+')('+JSON.stringify(fixture)+');');
const terminalScript=Buffer.from('('+terminalDataFixture.toString()+')('+JSON.stringify(fixture)+');');
const report={schema:1,status:'running',mode:mealOnly?'meal-only':'full-clarity',testSourceSHA:sourceSHA,installed:INSTALLED,target:TARGET,
  scope:mealOnly?'Installed HTTPS meal-filter and food-popup presentation using the actual installed LOCAL helper, with explicit fictional canonical data; Chromium 430/1280 at 100%; not live supplier or delivery authority':'Installed HTTPS native presentation and actual installed bridge denial handling with explicit fictional data seams, Chromium 430/1280 at 100%; not live supplier or delivery authority',
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
  if(spec.kind==='entry'&&url.search)verifyInitialURL(url.href+'#search');
  else assert(url.search===''||spec.sha256&&url.search==='?v='+spec.sha256.slice(0,12),'Only the pinned static version query');
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
  if(mealOnly){
    const actual=rows.find(row=>base+'/'+row.path===localHelperPath),compiled=compiledManifest.graph.scripts.find(row=>base+'/'+row.path===localHelperPath);
    assert(actual&&compiled,'LOCAL meal helper belongs to both exact graphs');
    assert.equal(actual.sha256,MEAL_HELPER_SHA256,'Exact reviewed LOCAL meal helper hash');assert.equal(actual.bytes,12283,'Exact reviewed LOCAL meal helper bytes');assert.equal(actual.sha256,compiled.sha256);assert.equal(actual.bytes,compiled.bytes);
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
function verifyInitialURL(value){
  const actual=new URL(value),target=new URL(TARGET);
  assert.equal(actual.origin,target.origin);assert.equal(actual.pathname,target.pathname);
  assert.equal(actual.username,'');assert.equal(actual.password,'');assert.equal(actual.hash,'#search');
  const expected={...fixture.trip,ages:fixture.trip.ages.join(',')};
  const entries=Object.entries(expected).map(([key,value])=>[key,String(value)]).sort(([a],[b])=>a.localeCompare(b));
  assert.deepEqual([...actual.searchParams.entries()].sort(([a],[b])=>a.localeCompare(b)),entries,'Only the exact canonical fictional trip is restored in the installed URL');
  return actual.href;
}
function calls(page){return page.evaluate(()=>structuredClone(window.__nativeFixture.calls));}
function captureOptions(name){return name==='calendar-later'?{fullPage:false,animations:'allow',caret:'initial'}:{fullPage:true,animations:'disabled',caret:'hide'};}
async function capture(page,row,name){
  await page.evaluate(()=>document.fonts.ready);
  const calendar=name==='calendar-later';
  const contact=['lead-preview','lead-return'].includes(name);
  let before,contactBefore;
  const contactFeedback=()=>page.evaluate(()=>{
    const root=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]'));
    const form=root?.querySelector('[data-screen="lead"] [data-lead-form]'),status=form?.querySelector('[data-native-contact-status]');
    if(!form)return{formPresent:false};
    const value={formPresent:true,checked:form.dataset.checked||null,statusPresent:!!status};if(!status)return value;
    const css=getComputedStyle(status),rect=status.getBoundingClientRect();
    return{...value,text:status.textContent.trim(),role:status.getAttribute('role'),hidden:status.hidden,hiddenAncestor:!!status.closest('[hidden]'),
      display:css.display,visibility:css.visibility,clientRects:status.getClientRects().length,
      rect:{left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom,width:rect.width,height:rect.height}};
  });
  const calendarPosition=()=>page.evaluate(()=>{
    const root=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]'));
    const strip=root.querySelector('[data-native-calendar-strip]'),cell=strip.querySelector('[data-native-day="2026-10-25"]');
    const rail=strip.getBoundingClientRect(),date=cell.getBoundingClientRect(),css=getComputedStyle(strip),cellCSS=getComputedStyle(cell);
    return{date:'2026-10-25',page:strip.dataset.nativeCalendarStrip,left:strip.scrollLeft,client:strip.clientWidth,scroll:strip.scrollWidth,
      rail:{left:rail.left,right:rail.right,top:rail.top,bottom:rail.bottom},cell:{left:date.left,right:date.right,top:date.top,bottom:date.bottom},
      viewport:{width:innerWidth,height:innerHeight},css:{scrollSnapType:css.scrollSnapType,scrollBehavior:css.scrollBehavior,display:cellCSS.display,visibility:cellCSS.visibility,fontSize:cellCSS.fontSize},
      withinStrip:date.width>0&&date.height>0&&date.left>=rail.left-1&&date.right<=rail.right+1&&date.top>=rail.top-1&&date.bottom<=rail.bottom+1,
      withinViewport:date.width>0&&date.height>0&&date.left>=-1&&date.right<=innerWidth+1&&date.top>=-1&&date.bottom<=innerHeight+1};
  });
  if(calendar){
    await page.evaluate(()=>new Promise((resolve,reject)=>{const deadline=setTimeout(()=>reject(new Error('Calendar paint did not settle within 6 seconds')),6000);requestAnimationFrame(()=>requestAnimationFrame(()=>{clearTimeout(deadline);resolve();}));}));
    await page.evaluate(()=>window.scrollTo(0,0));
    before=await calendarPosition();assert(before.withinStrip&&before.withinViewport,'Priced final date is visibly within the real strip and viewport before capture');
  }
  if(contact)contactBefore=await contactFeedback();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false,'No document horizontal overflow: '+name);
  const nameFile='installed-'+name+'-'+row.width+'-text100.png',file=path.join(out,nameFile);
  await page.screenshot({path:file,...captureOptions(name)});
  if(calendar){
    const after=await calendarPosition();row.proof.calendarCapture={options:captureOptions(name),before,after};
    assert(after.withinStrip&&after.withinViewport,'Priced final date remains visibly within the real strip and viewport after capture');
    assert(Math.abs(after.left-before.left)<=1,'Screenshot retains the actual date-strip position');
  }
  if(contact){row.proof.contactFeedback||={};row.proof.contactFeedback[name]={before:contactBefore,after:await contactFeedback()};}
  const bytes=fs.readFileSync(file);row.frames.push({file:nameFile,sha256:hash(bytes),bytes:bytes.length});
}
async function acceptMealFamilies(page,row,product,screen,installedManifest){
  const width=row.width;
  // One explicit fictional search and one exact fictional quote establish an
  // existing receipt. No contact validation or submission is part of this mode.
  await screen('search').locator('button[type="submit"]').click();await screen('results').waitFor({state:'visible'});
  assert.equal((await calls(page)).search,1);assert.equal(await product.locator('.st-hotel-card').count(),4);
  await product.locator('[data-favorite="1"]').click();
  await product.locator('[data-hotel-open="1"]').first().click();await screen('hotel').waitFor({state:'visible'});
  assert.equal((await calls(page)).quote,0,'Installed hotel opening is passive');
  await product.locator('[data-hotel-offers] [data-offer="fixture:offer:1"]').click();await screen('tour').waitFor({state:'visible'});
  await product.locator('[data-native-tour-flight="0"]').click();await product.locator('[data-action="accept-price"]:visible').first().click();
  const businessState=()=>page.evaluate(()=>{
    const c=window.__nativeFixture.calls,s=window.__nativeFixture.getSelection();
    return{search:c.search,quote:c.quote,expand:c.expand,flight:[...c.flight],preview:c.preview,submit:c.submit,
      key:s.key,total:s.total,choice:s.flightChoiceId,accepted:s.priceAccepted,exactOffer:s.offer===window.__nativeFixture.hotels[0].offers[0]};
  });
  const baseline=await businessState();
  assert.deepEqual(baseline,{search:1,quote:1,expand:0,flight:[0],preview:0,submit:0,key:'fixture:offer:1',total:112000,choice:0,accepted:true,exactOffer:true});
  await screen('tour').locator('[data-action="hotel"]').click();await screen('hotel').waitFor({state:'visible'});
  await screen('hotel').locator('[data-action="results"]').click();await screen('results').waitFor({state:'visible'});
  const reset=product.locator('[data-action="reset-filters"]:visible').first();if(await reset.isVisible())await reset.click();
  const priorDate=product.locator('[data-native-remove-constraint="date"]:visible').first();if(await priorDate.isVisible())await priorDate.click();
  const onlyFavorite=product.locator('[data-action="favorites"][aria-pressed="true"]:visible').first();if(await onlyFavorite.isVisible())await onlyFavorite.click();
  await page.evaluate(()=>window.__nativeFixture.installMealFamilies());
  if(width===430&&await product.locator('[data-native-calendar]').getAttribute('aria-expanded')!=='true')await product.locator('[data-native-calendar]').click();
  await product.locator('[data-native-day="'+fixture.trip.from+'"]:visible').click();
  if(width===430&&await product.locator('[data-native-calendar]').getAttribute('aria-expanded')==='true')await product.locator('[data-native-calendar]').click();
  if(width===430)await product.locator('[data-action="filters"]').first().click();
  const mealPanel=product.locator(width===430?'[data-filter-form]':'[data-filter-sidebar]');
  const family=()=>mealPanel.locator('[data-native-meal-group-toggle="meal-family:all-inclusive"]');
  const refine=mealPanel.locator('[data-native-meal-expand="meal-family:all-inclusive"]');if(await refine.getAttribute('aria-expanded')==='true')await refine.click();
  await mealPanel.locator('input[type="number"][data-native-budget="max"]').fill('125000');
  if(width===1280)await mealPanel.locator('input[type="number"][data-native-budget="max"]').press('Tab');
  assert.equal(await family().locator('..').locator('.st-filter-count').innerText(),'3','Distinct matching hotel union, not sum of child counts');
  assert.equal(await mealPanel.locator('[data-native-meal-members] .st-filter-count').evaluateAll(nodes=>nodes.reduce((sum,node)=>sum+Number(node.textContent),0)),4);
  assert.match(await family().locator('..').innerText(),/Все варианты \(4\)/);
  assert.equal(await mealPanel.locator('[data-native-meal-members]:visible').count(),0);
  await capture(page,row,'meal-families');
  await family().check();await mealPanel.locator('[data-native-meal-expand="meal-family:all-inclusive"]').click();
  await mealPanel.locator('[data-native-filter="meals"][value="Ультра всё включено"]').uncheck();
  assert.equal(await family().evaluate(node=>node.indeterminate),true);assert.equal(await family().getAttribute('aria-checked'),'mixed');
  const exactChildren=await mealPanel.locator('[data-native-meal-members] [data-native-filter="meals"]').evaluateAll(nodes=>nodes.map(node=>({name:node.value,checked:node.checked})));
  assert.deepEqual(exactChildren,[{name:'Всё включено',checked:true},{name:'Ультра всё включено',checked:false},{name:'Софт всё включено',checked:true},{name:'Всё включено без алкоголя',checked:true}]);
  assert.match(await mealPanel.locator('[data-native-meal-selection]').innerText(),/Всё включено без алкоголя/);
  if(width===430)assert.equal(await product.locator('[data-filter-apply]').innerText(),'Показать 2 отеля');
  else assert.deepEqual(await product.locator('.st-hotel-card').evaluateAll(nodes=>nodes.map(node=>Number(node.dataset.hotelCard))),[911,914]);
  const fit=await mealPanel.locator('[data-native-meal-family]').evaluate(node=>({overflow:node.scrollWidth>node.clientWidth+1,
    targets:[...node.querySelectorAll('label,button')].filter(control=>control.getClientRects().length).map(control=>({height:control.getBoundingClientRect().height,overflow:control.scrollWidth>control.clientWidth+1}))}));
  assert.equal(fit.overflow,false);assert(fit.targets.length>=6&&fit.targets.every(target=>target.height>=44&&!target.overflow));
  await capture(page,row,'meal-family-details');
  await family().check();if(width===430)await product.locator('[data-action="apply-filters"]').click();
  const resultIds=()=>product.locator('.st-hotel-card').evaluateAll(nodes=>nodes.map(node=>Number(node.dataset.hotelCard)));
  assert.deepEqual(await resultIds(),[911,912,914],'Same-offer date + meal + budget predicate excludes mismatched offers');
  assert.equal((await product.locator('[data-hotel-card="911"] .st-price').innerText()).replace(/\s+/g,''),'от120000₽');
  assert.equal(await product.locator('[data-active-chips] [data-native-remove-constraint="mealFamily"]').count(),1);
  assert.equal(await product.locator('[data-active-chips] [data-native-remove-constraint="meals"]').count(),0);
  const url=new URL(page.url());assert.equal(url.searchParams.get('meals').split('|').length,4);assert(!url.searchParams.get('meals').includes('meal-family:'));
  await product.locator('[data-action="search"]:visible').first().click();await screen('search').waitFor({state:'visible'});
  await product.locator('[data-conditions-open="food"]').click();
  const popup=product.locator('[data-native-form-popup][open]');
  const foodFamily=()=>popup.locator('[data-native-meal-group-toggle="meal-family:all-inclusive"]');
  await popup.locator('[data-native-meal-expand="meal-family:all-inclusive"]').click();await foodFamily().uncheck();
  await popup.locator('[data-native-form-meal="Ультра всё включено"]').check();
  assert.equal(await foodFamily().evaluate(node=>node.indeterminate),true);assert.equal(await product.locator('[data-search-food-value]').innerText(),'Ультра всё включено');
  await page.setViewportSize({width:width===430?1280:430,height:900});await popup.waitFor({state:'visible'});
  await page.setViewportSize({width,height:900});await popup.waitFor({state:'visible'});
  assert.equal(await foodFamily().evaluate(node=>node.indeterminate),true);assert.equal(await popup.locator('[data-native-form-meal="Ультра всё включено"]').isChecked(),true);
  assert.equal(await popup.locator('[data-native-meal-members]').isVisible(),true);
  const popupFit=await popup.evaluate(node=>{
    const apply=node.querySelector('.st-popup-actions [data-conditions-apply="food"]'),box=apply.getBoundingClientRect(),form=node.closest('[data-search-form]');
    return{modal:node.matches(':modal'),applyCount:node.querySelectorAll('[data-conditions-apply="food"]').length,applyHeight:box.height,
      applyEnabled:!apply.disabled,findInside:node.contains(form.querySelector('[type="submit"]')),submitCount:form.querySelectorAll('[type="submit"]').length};
  });
  assert(popupFit.modal&&popupFit.applyCount===1&&popupFit.applyHeight>=44&&popupFit.applyEnabled&&!popupFit.findInside&&popupFit.submitCount===1);
  await capture(page,row,'popup-food-families');
  await product.locator('[data-conditions-cancel="food"]').click();assert.match(await product.locator('[data-search-food-value]').innerText(),/все варианты/);
  // Applying the exact child changes only the pending form, without Find.
  await product.locator('[data-conditions-open="food"]').click();
  if(await popup.locator('[data-native-meal-expand="meal-family:all-inclusive"]').getAttribute('aria-expanded')!=='true')await popup.locator('[data-native-meal-expand="meal-family:all-inclusive"]').click();
  await foodFamily().uncheck();await popup.locator('[data-native-form-meal="Ультра всё включено"]').check();
  await popup.locator('[data-conditions-apply="food"]').click();await popup.waitFor({state:'hidden'});
  assert.equal(await product.locator('[data-search-food-value]').innerText(),'Ультра всё включено');
  assert.deepEqual(await businessState(),baseline,'Group/refinement/Cancel/Apply/resize never changes operations or selected receipt');
  await screen('search').locator('[data-action="results"]:visible').first().click();await screen('results').waitFor({state:'visible'});
  assert.deepEqual(await resultIds(),[911,912,914],'Returning without Find keeps the actual applied result context');
  await page.goBack();await screen('search').waitFor({state:'visible'});await page.goForward();await screen('results').waitFor({state:'visible'});
  assert.deepEqual(await resultIds(),[911,912,914]);assert.deepEqual(await businessState(),baseline);
  assert.deepEqual(await page.evaluate(()=>JSON.parse(localStorage.getItem('anytour.prototype.v18.favorites.v1'))),[1]);
  const helper=installedManifest.graph.scripts.find(row=>base+'/'+row.path===localHelperPath);
  assert.equal(helper.sha256,MEAL_HELPER_SHA256);assert.equal(await page.evaluate(()=>typeof window.AnyTourLocalDbProviderV1?.mealFilterGroups),'function');
  row.proof.mealFamilies={localHelperSHA256:helper.sha256,localHelperBytes:helper.bytes,familyHotelCount:3,memberHotelCountSum:4,partialHotelCount:2,
    memberPlans:exactChildren.map(member=>member.name),distinctHotelUnion:true,sameOfferDateMealBudget:true,matchingDisplayedMinimum:120000,
    canonicalLabelsOnlyURL:true,oneFamilyChip:true,partialExactSelection:true,formCancelAndResponsiveRetained:true,applyExtraOperations:0,
    extraOperations:0,selectedReceiptRetained:true,selectedFullTotal:112000,originalFlightChoiceIndex:0,backForwardRetained:true,favorites:[1],fit,popupFit,calls:await calls(page)};
}
async function run(){
  assert(/^[0-9a-f]{40}$/.test(INSTALLED.source)&&/^[0-9a-f]{40}$/.test(INSTALLED.release)&&Number.isSafeInteger(INSTALLED.publishRun)&&INSTALLED.publishRun>0&&/^[0-9a-f]{64}$/.test(INSTALLED.manifestSHA256),'Actual terminal publication pins required before installed evidence');
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
      let page,terminalJourney=false;
      await context.route('**/*',async route=>{
        const req=route.request(),value=req.url(),entry={width,url:value,method:req.method(),resourceType:req.resourceType()};report.network.push(entry);
        try{
          assert.equal(req.method(),'GET','GET static resources only');
          const media=mediaByURL.get(value);
          if(media){entry.action='retained-media-substitution';entry.sha256=media.sha256;await route.fulfill({status:200,contentType:'image/jpeg',body:media.body});return;}
          const {url,spec}=safeURL(value);const actual=await fetchPublic(value);
          let body=actual.body;
          if(url.pathname===bridgePath){
            body=terminalJourney?Buffer.concat([body,Buffer.from('\n('+recordTerminalBridge.toString()+')();')]):fixtureScript;
            entry.action=terminalJourney?'verified-installed-bridge-instrumentation':'fixture-bridge';
          }
          else if(terminalJourney&&url.pathname===base+'/prototype-search/data.js'){body=terminalScript;entry.action='fictional-canonical-data';}
          else if(spec.kind==='script'&&!url.pathname.startsWith(nativePrefix)&&!url.pathname.endsWith('/lead-form-guard-v1.js')&&!(mealOnly&&url.pathname===localHelperPath)){body=noOp;entry.action='canonical-script-disabled';}
          else entry.action='verified-installed-bytes';
          if(body!==actual.body){const change={path:url.pathname,originalSHA256:actual.sha256,replacementSHA256:hash(body),reason:entry.action};if(!report.replacements.some(r=>r.path===change.path&&r.replacementSHA256===change.replacementSHA256))report.replacements.push(change);}
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
        row.initialURL=verifyInitialURL(page.url());const product=page.locator('[data-product]:visible');const screen=name=>product.locator('[data-screen="'+name+'"]');
        await screen('search').waitFor({state:'visible'});assert.equal((await calls(page)).search,0);
        if(mealOnly){
          await acceptMealFamilies(page,row,product,screen,installedManifest);
          assert.equal(row.frames.length,3,'Only the three changed installed meal states per width');
          assert.deepEqual(row.errors,[]);assert.deepEqual(report.unexpectedRequests,[]);row.status='passed';continue;
        }
        await capture(page,row,'search');
        await product.locator('[data-search-nights-toggle]').click();await product.locator('[data-native-night="10"]').click();
        await product.locator('[data-search-editor-cancel="nights"]').click();assert.equal(await product.locator('[data-search-night-value]').innerText(),'7 ночей');
        assert.equal(await product.locator('[data-search-nights-toggle]').evaluate(node=>document.activeElement===node),true);
        await product.locator('[data-search-travelers-toggle]').click();await product.locator('[data-search-adult="-1"]').click();
        await product.locator('[data-search-editor-cancel="travelers"]').click();assert.equal(await product.locator('[data-search-travelers-value]').innerText(),'2 взрослых');
        await product.locator('[data-search-date-toggle]').click();await capture(page,row,'popup-calendar');
        const popup=product.locator('[data-native-form-popup]');
        const applyFit=await popup.evaluate(node=>{
          const apply=node.querySelector('.st-popup-actions [data-calendar-done]'),box=apply.getBoundingClientRect();
          const find=node.closest('[data-search-form]').querySelector('[type="submit"]');
          return{modal:node.matches(':modal'),applyCount:node.querySelectorAll('[data-calendar-done]').length,
            applyHeight:box.height,applyEnabled:!apply.disabled,findInside:node.contains(find),
            submitCount:node.closest('[data-search-form]').querySelectorAll('[type="submit"]').length};
        });
        assert(applyFit.modal&&applyFit.applyCount===1&&applyFit.applyHeight>=44&&applyFit.applyEnabled&&!applyFit.findInside&&applyFit.submitCount===1,'Installed popup has one readable Apply and the only Find outside');
        await popup.locator('[data-calendar-done]').click();await popup.waitFor({state:'hidden'});
        assert.equal((await calls(page)).search,0,'Applying installed dates is local');
        await product.locator('[data-conditions-open="budget"]').click();
        await product.locator('[data-search-budget="min"]').fill('0');await product.locator('[data-search-budget="min"]').press('Enter');
        await popup.waitFor({state:'hidden'});assert.equal((await calls(page)).search,0,'Enter applies installed input without a search');
        row.proof.fieldApply={...applyFit,applyExtraSearches:0,enterExtraSearches:0,cancelRetainsDraftAndFocus:true};
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
        assert.equal(await product.locator('[data-action="best-tour"]:visible').count(),0,'No unselected minimum-tour action competes with explicit variant choice');
        assert.equal(await product.locator('[data-hotel-name]').evaluate(node=>getComputedStyle(node).outlineStyle),'none');
        await capture(page,row,'hotel');
        await product.locator('[data-hotel-offers] [data-offer="fixture:offer:1"]').click();await screen('tour').waitFor({state:'visible'});
        await product.locator('[data-native-tour-flight="0"]').click();await product.locator('[data-action="accept-price"]:visible').first().click();
        assert.deepEqual(await page.evaluate(()=>({exact:window.__nativeFixture.getSelection().offer===window.__nativeFixture.hotels[0].offers[0],total:window.__nativeFixture.getSelection().total,choice:window.__nativeFixture.getSelection().flightChoiceId})),{exact:true,total:112000,choice:0});
        assert.match(await screen('tour').innerText(),/112\s*000/);assert.match(await screen('tour').innerText(),/09:20/);await capture(page,row,'tour');
        assert.match(await screen('tour').locator('[data-native-price-caption]:visible').first().innerText(),/Цена с выбранным перелётом за всех/);
        assert(await screen('tour').locator('[data-native-package-note]').isVisible(),'Installed package conditions are visible');
        const conditionsFit=await screen('tour').evaluate(node=>{
          const note=node.querySelector('[data-native-package-note]'),total=note.closest('.st-total'),amount=total.querySelector('[data-tour-price]'),action=total.querySelector('[data-action="lead"]');
          return{count:node.querySelectorAll('[data-native-package-note]').length,text:note.innerText,
            afterAmount:Boolean(amount.compareDocumentPosition(note)&Node.DOCUMENT_POSITION_FOLLOWING),
            beforeAction:Boolean(note.compareDocumentPosition(action)&Node.DOCUMENT_POSITION_FOLLOWING)};
        });
        assert.equal(conditionsFit.count,1);assert.match(conditionsFit.text,/обязательные доплаты/);assert(conditionsFit.afterAmount&&conditionsFit.beforeAction,'Installed conditions stay between full amount and contact action');
        if(width===430){assert(await screen('tour').locator('.st-quick-price-note').isVisible(),'Mobile price caveat is visible');assert.match(await screen('tour').locator('.st-quick-price-note').innerText(),/Доплаты и наличие уточняются до оформления/);}
        row.proof.priceClarity={caption:'Цена с выбранным перелётом за всех',conditionsCount:1,unknownExtrasVisible:true,conditionsAfterAmount:true,conditionsBeforeAction:true,unselectedHotelActionAbsent:true};
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
        row.proof={...row.proof,passiveSearches:0,explicitFixtureSearches:1,localFilterExtraOperations:0,calendarFinalLoadedTotal:166400,passiveHotelQuotes:0,explicitFixtureQuotes:1,exactOffer:'fixture:offer:1',tourId:'fixture-tour:1',adults:2,ages:[],selectedFullTotal:112000,originalFlightChoiceIndex:0,previewCount:1,realLeadSubmissions:0,backForwardRetained:true,draftRetained:true,consentRetained:true,favorites:[1],calls:finalCalls};
        // A second installed-entry visit uses the actual verified bridge bytes.
        // Only its canonical data dependency supplies explicit fictional responses.
        terminalJourney=true;
        const fixtureQuery=new URLSearchParams({...fixture.trip,ages:fixture.trip.ages.join(',')});
        await page.goto(TARGET+'?'+fixtureQuery,{waitUntil:'networkidle'});
        await page.locator('[data-native-startup]').waitFor({state:'hidden'});
        verifyInitialURL(page.url());
        const actualProduct=page.locator('[data-product]:visible');
        assert.equal(await page.evaluate(()=>window.__nativeTerminalFixture.calls.search),0);
        await actualProduct.locator('[data-screen="search"] button[type="submit"]').click();
        await actualProduct.locator('[data-screen="results"]').waitFor({state:'visible'});
        const resizeTour=async responsiveWidth=>{
          await page.setViewportSize({width:responsiveWidth,height:900});
          await page.waitForFunction(()=>{
            const root=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]'));
            return root&&root.classList.contains('st-mobile')===(innerWidth<=760)&&!root.querySelector('[data-screen="tour"]').hidden&&document.querySelector('[data-native-startup]').hidden;
          },null,{timeout:10000,polling:50});
        };
        // The actual compiled bridge consumes the canonical non-retryable Error.
        // No canonical HTTP or delivery is permitted in this fictional journey.
        await page.evaluate(()=>window.__nativeTerminalFixture.installDenials());
        await actualProduct.locator('[data-hotel-open="1"]').first().click();
        await actualProduct.locator('[data-screen="hotel"]').waitFor({state:'visible'});
        const denialProof={};
        for(const phase of ['quote','flights']){
          const before=await page.evaluate(()=>({...window.__nativeTerminalFixture.calls}));
          await actualProduct.locator('[data-hotel-offers] [data-offer="fixture:denial:'+phase+'"]').click();
          const deniedTour=actualProduct.locator('[data-screen="tour"]');await deniedTour.waitFor({state:'visible'});
          await deniedTour.locator('[data-native-flight-status]').filter({hasText:'Недоступно онлайн'}).waitFor({state:'visible'});
          const read=()=>page.evaluate(phase=>{
            const c=window.__nativeTerminalFixture,value=c.bridge.getSelection();
            return{...c.calls,key:value.key,exactOffer:value.offer===c.denials.offers[phase],searchTotal:value.offer.total,
              pricePending:value.pricePending,priceAccepted:value.priceAccepted,retryable:value[phase+'Failure']?.retryable};
          },phase);
          const denied=await read();
          assert.equal(denied.exactOffer,true);assert.equal(denied.retryable,false);assert.equal(denied.pricePending,true);assert.equal(denied.priceAccepted,false);
          assert.equal(denied.quote,before.quote+1);assert.equal(denied.flights,before.flights+(phase==='flights'?1:0));
          assert.equal(await deniedTour.locator('[data-native-check-tour],[data-native-retry-flights],[data-native-defer-flight],[data-native-tour-flight]').count(),0);
          assert.equal(await deniedTour.locator('[data-action="lead"]').evaluateAll(nodes=>nodes.every(node=>node.disabled)),true);
          assert.match(await deniedTour.locator('[data-native-price-caption]').first().innerText(),/Цена из поиска/);
          const fit=await deniedTour.locator('[data-native-other-offers]').evaluate(node=>{
            const r=node.getBoundingClientRect();return{height:r.height,width:r.width,clipped:node.scrollWidth>node.clientWidth+1};
          });assert(fit.height>=44&&fit.width>=44&&!fit.clipped);
          await capture(page,row,phase+'-unavailable');
          await resizeTour(width<=760?1280:430);
          await actualProduct.locator('[data-native-flight-status]').filter({hasText:'Недоступно онлайн'}).waitFor({state:'visible'});
          await resizeTour(width);
          await actualProduct.locator('[data-native-flight-status]').filter({hasText:'Недоступно онлайн'}).waitFor({state:'visible'});
          assert.deepEqual(await read(),denied,'Responsive remount keeps the exact terminal denial without more operations');
          await actualProduct.locator('[data-native-other-offers]').click();await actualProduct.locator('[data-screen="hotel"]').waitFor({state:'visible'});
          await actualProduct.locator('[data-hotel-offers] [data-offer="fixture:denial:'+phase+'"]').click();
          await actualProduct.locator('[data-native-flight-status]').filter({hasText:'Недоступно онлайн'}).waitFor({state:'visible'});
          assert.deepEqual(await read(),denied,'Hotel return/reopen cannot turn the denial into another quote');
          denialProof[phase]={exactOfferRetained:true,retryable:false,searchTotal:denied.searchTotal,contactBlocked:true,
            retryAndManagerActionsAbsent:true,backAndResponsiveExtraOperations:0,actionFit:fit};
          await actualProduct.locator('[data-native-other-offers]').click();await actualProduct.locator('[data-screen="hotel"]').waitFor({state:'visible'});
        }
        await actualProduct.locator('[data-hotel-offers] [data-offer="fixture:denial:allowed"]').click();
        await actualProduct.locator('[data-native-tour-flight="0"]').click();
        await actualProduct.locator('[data-action="accept-price"]:visible').first().click();
        const allowedContact=actualProduct.locator('[data-screen="tour"] [data-action="lead"]:visible').first();
        await allowedContact.waitFor({state:'visible'});assert.equal(await allowedContact.isEnabled(),true);
        const finalDenials=await page.evaluate(()=>({...window.__nativeTerminalFixture.calls}));
        assert.deepEqual(finalDenials,{search:1,other:0,quote:3,flights:2});
        row.proof.permanentDenials={...denialProof,differentAllowedOfferReady:true,canonicalBridgeSHA256:installedManifest.files['bridge.js'],calls:finalDenials};
        assert.equal(row.frames.length,12,'Ten existing installed states plus two permanent denials');
        assert.deepEqual(row.errors,[]);assert.deepEqual(report.unexpectedRequests,[]);row.status='passed';
      }catch(error){row.status='failed';row.error=error.stack||String(error);if(page)try{await page.screenshot({path:path.join(out,'failure-'+width+'-text100.png'),fullPage:true});}catch{}throw error;}
      finally{await context.close();writeReport();}
    }
    assert.deepEqual(report.unexpectedRequests,[]);assert.equal(report.cases.reduce((n,row)=>n+row.frames.length,0),mealOnly?6:24);report.status='passed';
  }catch(error){report.status='failed';report.error=error.stack||String(error);throw error;}
  finally{await browser?.close();await api.dispose();writeReport();}
}
if(process.argv.includes('--policy-only')){
  installGraph(compiledManifest);
  if(mealOnly){
    const wrong=structuredClone(compiledManifest);wrong.graph.scripts.find(row=>base+'/'+row.path===localHelperPath).sha256='0'.repeat(64);
    assert.throws(()=>installGraph(wrong),/Exact reviewed LOCAL meal helper hash/,'The helper cannot execute after graph hash drift');
    const missing=structuredClone(compiledManifest);missing.graph.scripts=missing.graph.scripts.filter(row=>base+'/'+row.path!==localHelperPath);
    assert.throws(()=>installGraph(missing),'The helper must belong to the exact public script graph');installGraph(compiledManifest);
  }
  assert.throws(()=>safeURL(origin+'/api.php?action=search_start'));assert.throws(()=>safeURL('https://example.com/x.js'));assert.throws(()=>safeURL(TARGET+'native-v2/frontend.js?unknown=1'));safeURL(TARGET);safeURL(MANIFEST_URL);
  const rendered=renderedEntry(compiledManifest).body;verifyEntry(rendered,compiledManifest);
  const drift=Buffer.from(rendered.toString('utf8').replace('</body>','<div>UNREVIEWED ENTRY DRIFT</div></body>'));
  assert(!drift.equals(rendered));assert.throws(()=>verifyEntry(drift,compiledManifest),/Exact installed entry DOM/,'Changed static body must fail despite its unchanged script graph');
  const canonical=TARGET+'?'+new URLSearchParams({...fixture.trip,ages:fixture.trip.ages.join(',')})+'#search';verifyInitialURL(canonical);
  safeURL(canonical.replace('#search',''));
  assert.throws(()=>safeURL(canonical.replace('#search','&searched=1')));
  assert.throws(()=>safeURL(canonical.replace('country=4','country=1').replace('#search','')));
  assert.throws(()=>verifyInitialURL(canonical.replace('#search','&searched=1#search')));assert.throws(()=>verifyInitialURL(canonical.replace('country=4','country=1')));assert.throws(()=>verifyInitialURL(canonical.replace('#search','#results')));
  assert.deepEqual(captureOptions('calendar-later'),{fullPage:false,animations:'allow',caret:'initial'});assert.deepEqual(captureOptions('results'),{fullPage:true,animations:'disabled',caret:'hide'});
  console.log('Installed native static policy PASS; no browser or network used.');
}else run().then(()=>console.log('Installed native presentation acceptance PASS: 430/1280, controlled data only.')).catch(error=>{console.error(error.stack||error);process.exitCode=1;});
