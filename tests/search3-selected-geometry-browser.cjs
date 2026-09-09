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
const tour = { id: 'geometry-tour', price: 148500, hotel: { name: 'Проверочный отель с длинным названием', country: { name: 'Турция' }, region: { name: 'Анталья' } }, departure: { name: 'Москва' }, date: '2026-09-12', nights: 9, adults: 2, childs: 1, meal: { name: 'Всё включено' }, roomType: 'STANDARD LAND VIEW', placement: 'DBL + CHD', operator: { name: 'TEST OPERATOR' }, isCharter: true, picture, hotelDescription: 'Номер 25 м&#178; &amp; SPA <b>рядом</b> &#x3C;script&#x3E;alert(1)&#x3C;/script&#x3E;. ' + 'Описание проверочного отеля. '.repeat(15) };
const segment = { company: { name: 'Test airline' }, number: 'AB123', departure: { name: 'Москва', airport: { name: 'Шереметьево', code: 'SVO' }, time: '09:30' }, arrival: { name: 'Анталья', airport: { name: 'Анталья', code: 'AYT' }, time: '14:00' }, baggage: 20, carryOn: '5 кг' };
const flights = Array.from({length:6},(_,index)=>({
  isDefault:index===0,
  price:{value:148500+index*1250},
  forward:[{...segment,number:'AB'+(123+index)}],
  backward:[{...segment,number:'AB'+(223+index)}]
}));
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
async function checkLargeList(page,width){
  const many=Array.from({length:89},(_,index)=>({...flights[0],isDefault:index===0,price:{value:148500+index*1250},forward:[{...segment,number:'AB'+(123+index)}],backward:[{...segment,number:'AB'+(223+index)}]}));
  await page.evaluate(({tour,many})=>{
    window.__largeCalls={tour:0,flights:0,other:0};
    window.V2Runtime.api=async action=>{
      if(action==='tour'){window.__largeCalls.tour++;return {...tour,id:'large-flight-tour',meal:{name:'RO',fullName:'Без питания'}};}
      if(action==='flights'){window.__largeCalls.flights++;return many;}
      window.__largeCalls.other++;throw Error('unexpected large-list API action');
    };
    window.V2TourController.selectTour('large-flight-tour');
  },{tour,many});
  const root=page.locator('#selectedTour'),list=root.locator('.flight-variants'),toggle=root.locator('.search3-flight-toggle');
  await toggle.waitFor();
  await settle(page);
  assert.equal(await root.locator('.facts>div').filter({hasText:'Питание'}).locator('b').innerText(),'Без питания','selected uses the canonical readable supplier meal label');
  assert.equal(await list.locator('input[name=v2flight]').count(),89,'all supplier choices retained');
  assert.equal(await list.locator('input:visible').count(),1,'large list initially shows the selected flight');
  assert.equal(await toggle.getAttribute('aria-expanded'),'false');
  assert.match(await toggle.textContent(),/89/,'disclosure announces the total');
  assert.ok((await toggle.boundingBox()).height>=44,'disclosure has a touch target');
  const collapsedHeight=(await root.locator('.tour-flights').boundingBox()).height;
  assert.ok(collapsedHeight<1600,'large-list closed height does not grow with89 alternatives');
  await root.locator('.tour-flights').screenshot({path:path.join(output,'large-'+width+'-collapsed.png')});
  await toggle.focus();await page.keyboard.press('Enter');
  assert.equal(await toggle.getAttribute('aria-expanded'),'true','keyboard opens every choice');
  assert.equal(await list.locator('input:visible').count(),89,'every alternative becomes reachable');
  assert.deepEqual(await list.locator('.flight-variant').evaluateAll(nodes=>nodes.map(n=>Number(n.dataset.flightIndex))),Array.from({length:89},(_,i)=>i),'original supplier order and indices remain unchanged');
  const expandedHeight=(await list.boundingBox()).height;
  assert.ok(expandedHeight<=641,'expanded list scrolls within a bounded panel');
  await list.locator('input[value="88"]').click();
  await page.waitForFunction(()=>document.querySelector('#selectedTour .flight-variant.is-selected')?.dataset.flightIndex==='88');
  assert.match((await root.locator('.selected-price').innerText()).replace(/\s/g,''),/258500₽/,'last alternative keeps its canonical total');
  await list.locator('input[value="88"]').focus();await page.keyboard.press('ArrowUp');
  await page.waitForFunction(()=>document.querySelector('#selectedTour .flight-variant.is-selected')?.dataset.flightIndex==='87');
  assert.match((await root.locator('.selected-price').innerText()).replace(/\s/g,''),/257250₽/,'native radio keyboard changes the total');
  await toggle.click();
  assert.equal(await list.locator('input:visible').count(),1,'collapse retains only the actual selected node');
  assert.equal(await list.locator('input[value="87"]').isVisible(),true);
  assert.equal(await list.locator('.is-selected .flight-segment:visible').count(),2,'both selected directions remain directly readable');
  assert.equal(await root.locator('.search3-flight-continue button:visible').count(),1);
  await root.locator('.search3-flight-continue button').click();
  await page.waitForFunction(()=>document.activeElement?.name==='phone');
  assert.match((await root.locator('.lead-selection-summary').innerText()).replace(/\s/g,''),/257250₽/,'contact summary agrees with canonical total');
  assert.match(await root.locator('.lead-selection-summary').innerText(),/AB210/,'contact summary retains selected identity');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false,'large choices never overflow the page');
  assert.deepEqual(await page.evaluate(()=>window.__largeCalls),{tour:1,flights:1,other:0},'disclosure/selection do not call supplier or leads');
  await root.locator('.tour-flights').screenshot({path:path.join(output,'large-'+width+'-selected.png')});
  return {choices:89,collapsedHeight,expandedHeight,selectedIndex:87,price:257250,allChoicesReachable:true,keyboard:true,realLeads:0};
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
      const imageBox=await page.locator('#selectedTour .selected-picture img').boundingBox();
      const pictureBox=await page.locator('#selectedTour .selected-picture').boundingBox();
      assert.ok(imageBox.height>=150 && imageBox.height<=321,'selected photo remains legible and bounded');
      assert.ok(Math.abs(imageBox.width-pictureBox.width)<=2,'selected photo fills its responsive container');
      const priceBox=await page.locator('#selectedTour .selected-price').boundingBox();
      const headBox=await page.locator('#selectedTour .selected-head').boundingBox();
      assert.ok(priceBox.x>=headBox.x-1 && priceBox.x+priceBox.width<=headBox.x+headBox.width+1,'selected price fits its header at the current width');
      assert.equal(await page.locator('#tourSearch').isVisible(),false,'selected tour does not repeat the search form');
      assert.equal(await page.locator('.v2-product-hero').isVisible(),false,'selected tour does not repeat the entry hero');
      assert.equal(await page.locator('.search3-selected-mobile-bar,.facts-secondary-toggle,.hotel-desc-toggle,.lead-optional-toggle,.search3-flight-show-all').count(),0,'retired presentation owners are not reconstructed');
      assert.equal(await page.locator('#selectedTour .facts > div[hidden]').count(),0,'all original tour facts remain directly available');
      const description=page.locator('#selectedTour .hotel-desc');
      assert.match(await description.innerText(),/Номер 25 м² & SPA рядом <script>alert\(1\)<\/script>/,'supplier entities render as readable inert text');
      assert.doesNotMatch(await description.innerText(),/&#178;/,'numeric entity is not leaked to the visitor');
      assert.equal(await description.locator('script').count(),0,'decoded entity text cannot become executable markup');
      assert.equal(await page.evaluate(()=>typeof window.V2ConversionConfidenceV1),'undefined','retired runtime is absent');
      assert.equal(await page.evaluate(()=>typeof window.V2PriceConfidenceV1),'undefined','retired price-confidence runtime is absent');
      assert.equal(await page.locator('#v2CompareTray,#v2CompareOverlay,#v2AgencyTrust,#v2ResultsConfidence').count(),0,'retired surfaces are not constructed');
      assert.equal(await page.locator('#selectedTour .selected-price-confidence').count(),0,'legacy price-confidence note is not constructed');
      assert.equal(await page.locator('#selectedTour .flight-variant input[name="v2flight"]').count(),6,'every flight radio remains available');
      assert.equal(await page.locator('#selectedTour .flight-variant input[name="v2flight"]:visible').count(),6,'every flight radio remains visible');
      assert.equal(await page.locator('#selectedTour .flight-variant.is-selected .flight-segment:visible').count(),2,'selected flight exposes both directions');
      assert.equal(await page.locator('#selectedTour .flight-variant:not(.is-selected) .flight-segment:visible').count(),0,'unselected flight details stay compact');
      assert.equal(await page.locator('#selectedTour .selected-lead-cta:visible').count(),0,'duplicate top lead CTA is hidden in Search3');
      assert.equal(await page.locator('#selectedTour .search3-flight-continue button:visible').count(),1,'one visible Search3 handoff CTA remains');
      await page.locator('#selectedTour .flight-variant').nth(1).locator('input[name="v2flight"]').click();
      await page.waitForFunction(()=>document.querySelector('#selectedTour .flight-variant[data-flight-index="1"]')?.classList.contains('is-selected'));
      assert.equal(await page.locator('#selectedTour .flight-variant.is-selected .flight-segment:visible').count(),2,'radio switch expands the new selection');
      assert.equal(await page.locator('#selectedTour .flight-variant:not(.is-selected) .flight-segment:visible').count(),0,'radio switch collapses the previous selection');
      await page.locator('#selectedTour .flight-variant').first().locator('input[name="v2flight"]').click();
      await page.waitForFunction(()=>document.querySelector('#selectedTour .flight-variant[data-flight-index="0"]')?.classList.contains('is-selected'));
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
      assert.ok((await page.locator('#selectedTour .lead-form input[name=phone]').boundingBox()).height>=44,'phone retains a full touch target');
      assert.ok((await page.locator('#selectedTour .lead-form button[type=submit]').boundingBox()).height>=44,'contact submit retains a full touch target without submitting');
      assert.match((await page.locator('#selectedTour .lead-selection-summary').innerText()).replace(/\s/g,' '),/AB123 09:30/,'contact summary keeps the selected flight identity');
      const leadSummary=await page.locator('#selectedTour .lead-selection-summary').evaluate(node=>({display:getComputedStyle(node).display,b:getComputedStyle(node.querySelector('b')).display}));
      assert.deepEqual(leadSummary,{display:'grid',b:'block'},'lead selection summary keeps labels and values visually separated');
    }
    const calls=await page.evaluate(()=>window.__geometryCalls);
    assert.deepEqual(calls,{tour:1,flights:1,other:0});
    if(!previous && [375,1440].includes(width)) states.large=await checkLargeList(page,width);
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
