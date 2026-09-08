/* Intentional selected presentation retirement: compare protected content and journey,
 * record changed geometry, and reject overflow. All catalogue/lead/network calls blocked. */
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const baseline = process.env.SEARCH3_GEOMETRY_BASE;
assert.match(baseline || '', /^[0-9a-f]{40}$/, 'exact baseline commit required');
const runtimeBaseline = process.env.SEARCH3_RUNTIME_BASE || baseline;
assert.match(runtimeBaseline, /^[0-9a-f]{40}$/, 'exact runtime baseline required');
const base = process.env.SEARCH3_VISUAL_BASE;
assert.ok(base && new URL(base).hostname === '127.0.0.1', 'fixture must use the isolated local payload');
const output = process.env.SEARCH3_GEOMETRY_OUTPUT;
assert.ok(output, 'evidence output required');
fs.mkdirSync(output, { recursive: true });
const baselineStyles = new Map(Object.keys(require('../src/search3/manifest.json').assets)
  .map(name => [name, execFileSync('git', ['show', `${name.endsWith('.css') ? baseline : runtimeBaseline}:v2/${name}`])]));
// Compare the actual route closures, including retired shared owners and runtime.
const baselineBundles = Object.fromEntries(['css', 'js'].map(type => {
  const commit = type === 'css' ? baseline : runtimeBaseline;
  const baselineManifest = execFileSync('git', ['show', `${commit}:v2/bundle-manifest-v1.php`]);
  const names = JSON.parse(execFileSync('php', ['-r',
    'eval("?>" . stream_get_contents(STDIN)); echo json_encode(function_exists("v2_bundle_files") ? v2_bundle_files($argv[1], "search3") : v2_bundle_manifest()[$argv[1]]);', type
  ], { input: baselineManifest, encoding: 'utf8' }));
  return [type, names.map(name => execFileSync('git', ['show', `${commit}:v2/${name}`], { encoding: 'utf8' })).join(type === 'js' ? '\n;\n' : '\n')];
}));
const picture = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="750"><path fill="#9ac7df" d="M0 0h1200v750H0z"/><path fill="#f5efe0" d="M250 150h700v600H250z"/></svg>');
const tour = { id: 'geometry-tour', price: 148500, hotel: { name: 'Проверочный отель с длинным названием', country: { name: 'Турция' }, region: { name: 'Анталья' } }, departure: { name: 'Москва' }, date: '2026-09-12', nights: 9, adults: 2, childs: 1, meal: { name: 'Всё включено' }, roomType: 'STANDARD LAND VIEW', placement: 'DBL + CHD', operator: { name: 'TEST OPERATOR' }, isCharter: true, picture, hotelDescription: 'Описание проверочного отеля. '.repeat(16) };
const segment = { company: { name: 'Test airline' }, number: 'AB123', departure: { name: 'Москва', airport: { name: 'Шереметьево', code: 'SVO' }, time: '09:30' }, arrival: { name: 'Анталья', airport: { name: 'Анталья', code: 'AYT' }, time: '14:00' }, baggage: 20, carryOn: '5 кг' };
const flights = [{ isDefault: true, price: { value: 148500 }, forward: [segment], backward: [{ ...segment, number: 'AB124' }] }];
const settle = page => page.evaluate(async () => {
  await document.fonts.ready;
  // Drain the presentation frame/zero-timer handoff before canonicalizing scroll.
  for(let i=0;i<4;i++) await new Promise(r=>requestAnimationFrame(()=>setTimeout(r,0)));
  window.scrollTo({top:0,left:0,behavior:'instant'});
  await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));
});
async function capture(page, label) {
  await settle(page);
  const snapshot = await page.evaluate(() => {
    const root = document.getElementById('selectedTour'), rr = root.getBoundingClientRect();
    const round = x => Math.round(x * 100) / 100;
    const properties = ['display','position','grid-template-columns','grid-template-rows','flex-direction','align-items','justify-content','gap','padding','margin','border-width','border-radius','font-family','font-size','font-weight','line-height','white-space','overflow-x','overflow-y','color','background-color','object-fit'];
    const all = [root, ...root.querySelectorAll('*'), ...document.querySelectorAll('.search3-selected-mobile-bar,.search3-selected-mobile-bar *')];
    const visible = all.filter(n => { const r=n.getBoundingClientRect(); return r.width>0 && r.height>0 && getComputedStyle(n).visibility!=='hidden'; });
    const nodes=visible.map(n=>{const r=n.getBoundingClientRect(),s=getComputedStyle(n),fixed=n.closest('.search3-selected-mobile-bar');return {tag:n.tagName,classes:[...n.classList].sort().join(' '),mobileBar:!!fixed,mobileCta:n.matches('[data-s3-selected-lead]'),rect:[r.x-(fixed?0:rr.x),r.y-(fixed?0:rr.y),r.width,r.height].map(round),styles:Object.fromEntries(properties.map(p=>[p,s.getPropertyValue(p)]))};});
    const text = node => String(node?.textContent || '').replace(/\s+/g,' ').trim();
    const contract = {
      facts: [...root.querySelectorAll('.facts > div')].map(node => [text(node.querySelector('span')), text(node.querySelector('b'))]),
      selectedPrice: text(root.querySelector('.selected-price')),
      fields: [...root.querySelectorAll('.lead-form [name]')].map(node => [node.name,node.type,node.value,node.required]).sort((a,b)=>a[0].localeCompare(b[0])),
      lead: root.classList.contains('search3-lead-entry')
    };
    return {overflow:document.documentElement.scrollWidth>innerWidth+2,rootWidth:round(rr.width),nodes,contract};
  });
  await page.locator('#selectedTour').screenshot({ path: path.join(output, label+'.png'), animations:'disabled' });
  return snapshot;
}
async function run(browser, width, previous) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  const errors=[];
  page.on('pageerror', e=>errors.push(String(e)));
  await page.route('**/*', route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.origin!==new URL(base).origin || request.method()!=='GET' || /\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)) return route.abort();
    const old=baselineStyles.get(url.pathname.split('/').pop());
    if(previous && old) return route.fulfill({status:200,contentType:url.pathname.endsWith('.css')?'text/css':'application/javascript',body:old});
    if(previous && url.pathname.endsWith('/bundle-v1.php')) {
      const type=url.searchParams.get('type');
      assert.ok(type in baselineBundles, 'known scoped bundle type');
      return route.fulfill({status:200,contentType:type==='css'?'text/css':'application/javascript',body:baselineBundles[type]});
    }
    return route.continue();
  });
  try {
    const response=await page.goto(base+'/poisk-turov/', {waitUntil:'domcontentloaded'});
    assert.equal(response.status(),200,'isolated Search3 entry must load');
    assert.equal(await page.locator('body').evaluate(n=>n.classList.contains('search3-candidate')),true,'canonical host gate must enable Search3');
    await page.waitForFunction(()=>window.V2TourController && window.Search3SummaryCta);
    await page.evaluate(({tour,flights})=>{
      window.__geometryCalls={tour:0,flights:0,other:0};
      window.V2Runtime.api=async action=>{
        if(action==='tour'){window.__geometryCalls.tour++;return tour;}
        if(action==='flights'){window.__geometryCalls.flights++;return flights;}
        window.__geometryCalls.other++; throw Error('unexpected fixture API action '+action);
      };
      window.V2TourController.selectTour(tour.id);
    },{tour,flights});
    await page.waitForSelector('#selectedTour .flight-variant');
    await page.waitForFunction(previous
      ? ()=>window.Search3SelectedFlowV2
      : ()=>window.V2FlightEmptyRecoveryV1);
    await page.waitForSelector('#selectedTour .search3-flight-continue button');
    await page.waitForFunction(()=>document.body.classList.contains('search3-selected-open'));
    const prefix=(previous?'baseline':'current')+'-'+width;
    const states={detail:await capture(page,prefix+'-detail')};
    assert.equal(await page.locator('#selectedTour .selected-confidence').count(),0,'the retired trust decoration stays absent');
    if(!previous) {
      assert.equal(await page.locator('.search3-selected-mobile-bar,.facts-secondary-toggle,.hotel-desc-toggle,.lead-optional-toggle,.search3-flight-show-all').count(),0,'retired presentation owners are not reconstructed');
      assert.equal(await page.locator('#selectedTour .facts > div[hidden]').count(),0,'all original tour facts remain directly available');
      assert.equal(await page.evaluate(()=>typeof window.V2ConversionConfidenceV1),'undefined','retired runtime is absent');
      assert.equal(await page.evaluate(()=>typeof window.V2PriceConfidenceV1),'undefined','retired price-confidence runtime is absent');
      assert.equal(await page.locator('#v2CompareTray,#v2CompareOverlay,#v2AgencyTrust,#v2ResultsConfidence').count(),0,'retired surfaces are not constructed');
      assert.equal(await page.locator('#selectedTour .selected-price-confidence').count(),0,'legacy price-confidence note is not constructed');
    }
    await page.locator('#selectedTour .search3-flight-continue button').click();
    if(previous){
      await page.waitForSelector('#selectedTour.search3-final-review .search3-summary-submit');
      await page.locator('#selectedTour .search3-summary-submit').click();
    }
    await page.waitForSelector('#selectedTour.search3-lead-entry .lead-form input[name="phone"]');
    states.lead=await capture(page,prefix+'-lead');
    assert.equal(await page.locator('#selectedTour .lead-form button[type=submit]').isVisible(),true,'lead submit remains reachable');
    if(!previous) {
      assert.equal(await page.locator('#selectedTour .search3-booking-summary,.search3-summary-submit').count(),0,'duplicate review card and intermediary CTA stay retired');
      assert.match((await page.locator('#selectedTour .selected-price').textContent()).replace(/\s/g,' '),/148 500 ₽/,'canonical selected price remains visible');
      assert.equal(await page.locator('#selectedTour .lead-form input[name=phone]').isVisible(),true,'phone remains directly reachable');
    }
    const calls=await page.evaluate(()=>window.__geometryCalls);
    assert.deepEqual(calls,{tour:1,flights:1,other:0});
    assert.deepEqual(errors,[],'fixture must not cause browser errors');
    return states;
  } catch(error) {
    const prefix=(previous?'baseline':'current')+'-'+width+'-failure';
    fs.writeFileSync(path.join(output,prefix+'.json'),JSON.stringify({message:String(error),errors,url:page.url(),body:await page.locator('body').getAttribute('class'),scripts:await page.locator('script[src]').evaluateAll(nodes=>nodes.map(n=>n.src))},null,2));
    await page.screenshot({path:path.join(output,prefix+'.png'),fullPage:true});
    throw error;
  } finally { await page.close(); }
}
(async()=>{
  const browser=await chromium.launch({headless:true});
  const evidence={baseline,runtimeBaseline,differences:[],widths:{}};
  try {
    for(const width of [375,760,1000,1440]){
      const before=await run(browser,width,true),after=await run(browser,width,false);
      evidence.widths[width]={before,after};
      for(const phase of ['detail','lead']){
        const a=before[phase],b=after[phase];
        // Card/disclosure/optional-field wrappers are intentionally removed. Do
        // not pretend their old pixel tree is the new design contract: retain
        // exact facts, prices, lead fields/required flags and stage transitions.
        if(JSON.stringify(a.contract)!==JSON.stringify(b.contract)) evidence.differences.push({width,phase,before:a.contract,after:b.contract});
        if(b.rootWidth<=0||b.rootWidth>width+2) evidence.differences.push({width,phase,error:'selected content is not bounded and visible'});
        if(b.overflow) evidence.differences.push({width,phase,error:'horizontal overflow'});
      }
      console.log('SELECTED_GEOMETRY_CAPTURED',width);
    }
  } finally {
    await browser.close();
    fs.writeFileSync(path.join(output,'geometry.json'),JSON.stringify(evidence,null,2)+'\n');
  }
  assert.deepEqual(evidence.differences,[],'protected selected facts/prices/lead fields and stages must match in all8 states without overflow; see evidence');
  console.log('SEARCH3_SELECTED_GEOMETRY_OK states=8 widths=375,760,1000,1440 native_lead_handoff=1');
})().catch(error=>{console.error(error);process.exitCode=1});
