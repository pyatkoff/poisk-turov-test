'use strict';
/*
 * Finite diagnostics only. Exact compiled artifact roots, unchanged saved fixture,
 * PHP entry and actual Chromium. No source recompilation, API, supplier or lead call.
 *
 * Usage:
 *   node tests/search3-visual-performance.cjs \
 *     --baseline-root /tmp/published/payload/v2 \
 *     --candidate-root /tmp/night/payload/v2 --output /tmp/performance.json
 *
 * Default: 16 retained paired trials + 2 discarded warmup pairs, at each viewport.
 * A/B and B/A orders alternate and balance. Each trial has a fresh browser context;
 * the browser process and OS file caches remain warm. Desktop uses CPU1x; mobile
 * CPU4x is CDP emulation, not a physical device. Local HTTP has no WAN throttling.
 *
 * Timers start inside the capturing browser click/change listener, before runtime
 * handlers. A MutationObserver armed before the action records the first matching
 * final DOM state. Driver polling/command latency is excluded. The second rAF after
 * that endpoint is a conservative frame proxy, NOT a measurement of actual paint,
 * input queue delay or INP. Readbacks/hashes happen after timing has finished.
 * Pagination cumulative values sum separate processing intervals; they exclude
 * think time and driver gaps. No latency threshold fails on machine noise.
 * Mobile reset uses the visible drawer-header Reset control, then Apply; its
 * selected-chip container is intentionally hidden by the actual compiled CSS.
 *
 * Snapshot has at most 3 offers/hotel. Group-more (>4), dense-offer performance,
 * progressive LIVE supplier updates and public-host network timing are unmeasured.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const crypto = require('node:crypto');
const os = require('node:os');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');

const args = {};
for (let i = 2; i < process.argv.length; i += 2) {
  assert(process.argv[i].startsWith('--') && process.argv[i + 1], 'arguments are --name value');
  args[process.argv[i].slice(2)] = process.argv[i + 1];
}
assert(args['baseline-root'] && args['candidate-root'], '--baseline-root and --candidate-root are required');
const pairs = Number(args.pairs || 16), warmupPairs = 2, height = 900;
assert(Number.isInteger(pairs) && pairs >= 16 && pairs <= 24 && pairs % 2 === 0, '--pairs must be even, 16..24');
const outputArgument = path.resolve(args.output || 'visual-live-evidence/compiled-benchmark');
const output = outputArgument.endsWith('.json') ? outputArgument : path.join(outputArgument,'performance.json');
fs.mkdirSync(path.dirname(output), {recursive:true});
const sha = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const normalizeRoot = value => {
  for (const candidate of [path.resolve(value),path.resolve(value,'v2'),path.resolve(value,'payload/v2')]) {
    if (fs.existsSync(path.join(candidate,'visual-search/index.php'))) return candidate;
  }
  throw new Error('compiled v2 root not found: ' + value);
};
const roots = {baseline:normalizeRoot(args['baseline-root']),candidate:normalizeRoot(args['candidate-root'])};
assert.notEqual(roots.baseline, roots.candidate, 'roots must be distinct');
const fixturePath = 'visual-search/fixtures/live-search-2026-09-23.json';
const fixtureBytes = Object.fromEntries(Object.entries(roots).map(([label,root])=>[label,fs.readFileSync(path.join(root,fixturePath))]));
assert(fixtureBytes.baseline.equals(fixtureBytes.candidate), 'A/B fixtures must be byte-identical');
const fixture = JSON.parse(fixtureBytes.baseline);
assert.equal(fixture.hotels.length,1047);assert.equal(fixture.hotels.reduce((n,h)=>n+h.offers.length,0),1453);
const hotelById = new Map(fixture.hotels.map(h=>[String(h.id),h]));
const star4Ids = fixture.hotels.filter(h=>Number(h.stars)===4).map(h=>String(h.id));
const star4Offers = fixture.hotels.filter(h=>Number(h.stars)===4).reduce((n,h)=>n+h.offers.length,0);
assert.equal(star4Ids.length,395);assert.equal(star4Offers,522);
const maxOffers = Math.max(...fixture.hotels.map(h=>h.offers.length));
assert(maxOffers <= 4, 'group-more is intentionally absent in this unchanged snapshot');
const base = '/_preview/search3-next-candidate/';
const routeReceipts = {baseline:[],candidate:[]}, serverErrors = [];
const knownExtensions = new Set(['.js','.css','.json','.woff2','.woff','.ttf','.jpg','.jpeg','.png','.svg','.webp','.ico','.gif','.avif','.html']);
function makeServer(label) {
  const root = roots[label];
  return http.createServer((req,res)=>{
    try {
      const url = new URL(req.url,'http://fixture');
      assert.equal(req.method,'GET');
      assert(url.pathname.startsWith(base),'unknown prefix');
      const local = path.resolve(root,decodeURIComponent(url.pathname.slice(base.length)) || 'index.php');
      assert(local.startsWith(root+path.sep),'path escapes artifact');
      const file = fs.existsSync(local)&&fs.statSync(local).isDirectory()?path.join(local,'index.php'):local;
      assert(fs.existsSync(file)&&fs.statSync(file).isFile(),'unknown local asset '+url.pathname);
      let bytes;
      if(file.endsWith('.php')){
        assert.equal(file,path.join(root,'visual-search/index.php'),'only isolated entry PHP is executable');
        assert.equal(url.searchParams.get('scenario'),'snapshot','only unchanged saved snapshot is served');
        bytes=execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php"; $_GET["scenario"]="snapshot"; include $argv[1];',file],{timeout:10000});
        res.setHeader('Content-Type','text/html; charset=utf-8');
        res.setHeader('X-Robots-Tag','noindex, nofollow, noarchive');
      } else {
        assert(knownExtensions.has(path.extname(file)),'unsupported local asset');
        bytes=fs.readFileSync(file);
        const types={'.js':'application/javascript','.css':'text/css','.json':'application/json','.html':'text/html','.woff2':'font/woff2','.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.webp':'image/webp'};
        res.setHeader('Content-Type',types[path.extname(file)]||'application/octet-stream');
      }
      res.setHeader('Cache-Control','no-store');
      routeReceipts[label].push({path:url.pathname,bytes:bytes.length});
      res.end(bytes);
    } catch(error) {
      serverErrors.push({label,url:req.url,error:error.message});
      res.writeHead(500,{'Content-Type':'text/plain'}).end(error.message);
    }
  });
}
const servers={baseline:makeServer('baseline'),candidate:makeServer('candidate')};
const pinsPath=path.join(__dirname,'search3-visual-performance-pins.json');
const pins=fs.existsSync(pinsPath)?JSON.parse(fs.readFileSync(pinsPath,'utf8')):null;
const artifacts = Object.fromEntries(Object.entries(roots).map(([label,root])=>{
  const html=fs.readFileSync(path.join(root,'visual-search/index.html'),'utf8');
  const initial=[...html.matchAll(/<script src="([^"?]+)"/g)].map(m=>m[1]);
  assert.equal(initial.length,8,'offline entry has 8 initial script owners');
  const assets=[...initial,'./styles.css','./mobile-controls-v1.css','./offer-list-v1.js','./hotel-details-v1.js'];
  const hashes=Object.fromEntries(assets.map(relative=>{
    const filename=path.resolve(root,'visual-search',relative),bytes=fs.readFileSync(filename);
    return [path.relative(root,filename),{sha256:sha(bytes),bytes:bytes.length}];
  }));
  let provenance=null;
  for(const origin of [path.join(root,'../benchmark-origin.json'),path.join(root,'../verified-artifact.json')]){
    if(fs.existsSync(origin)){provenance=JSON.parse(fs.readFileSync(origin,'utf8'));break;}
  }
  const pin=pins?.arms?.[label];
  if(pin){
    assert.equal(sha(fixtureBytes[label]),pin.snapshot_sha256);
    for(const owner of pin.compiled_owners){
      const bytes=fs.readFileSync(path.join(root,owner.path));
      assert.equal(bytes.length,owner.bytes,label+' pinned owner bytes '+owner.path);
      assert.equal(sha(bytes),owner.sha256,label+' pinned owner hash '+owner.path);
    }
  }
  return[label,{root,provenance,pin:pin?{source_sha:pin.source_sha,source_tree_sha:pin.source_tree_sha,integration_sha:pin.integration_sha,artifact_id:pin.artifact_id,build_run_id:pin.build_run_id,zip_sha256:pin.zip_sha256}:null,assetHashes:hashes}];
}));

// This function is serialized into the test page only. It never edits runtime JS.
function installProbe(config) {
  const normalize=value=>String(value||'').replace(/\s+/g,' ').trim();
  const numbers=value=>(String(value||'').replace(/\s+/g,'').match(/\d+/g)||[]).map(Number);
  const q=selector=>document.querySelector(selector);
  const qa=selector=>[...document.querySelectorAll(selector)];
  const records=new Map();
  let armed=null;
  function matches(condition) {
    if(condition.kind==='results'){
      const ids=qa('#cards .hotel-card').map(el=>el.dataset.hotelId);
      const summary=numbers(q('#results-summary')?.textContent);
      if(ids.length!==condition.cards||summary[0]!==condition.hotels||summary[1]!==condition.offers)return false;
      if(condition.star4===true&&(!q('#active-filters [data-key="stars"][data-value="4"]')||!ids.every(id=>config.star4Ids.includes(id))))return false;
      if(condition.star4===false&&q('#active-filters [data-key="stars"]'))return false;
      if(condition.drawer===false&&q('#filter-panel')?.classList.contains('open'))return false;
      return true;
    }
    if(condition.kind==='drawer'){
      return q('#filter-panel')?.classList.contains('open')&&numbers(q('#filter-preview-count')?.textContent)[0]===condition.hotels&&q('#filters [data-action="star"][data-value="4"]')?.getAttribute('aria-pressed')===String(condition.star4)&&!!q('#drawer-selected [data-key="stars"][data-value="4"]')===condition.star4;
    }
    if(condition.kind==='hotel'){
      return q('#modal')?.open&&normalize(q('#modal-title')?.textContent)===condition.title&&!!normalize(q('#hotel-room-count')?.textContent)&&!!window.AnyTourHotelDetails?.create;
    }
    if(condition.kind==='offers'){
      return q('#modal')?.open&&normalize(q('#modal-title')?.textContent)===condition.title&&numbers(q('#offer-count')?.textContent)[0]===condition.offers&&!!window.AnyTourOfferList?.create;
    }
    if(condition.kind==='group'){
      const heading=qa('.offer-group-heading').find(el=>el.dataset.value===condition.key),body=document.getElementById('group-'+condition.key);
      return !!heading&&!!body&&heading.getAttribute('aria-expanded')===String(condition.open)&&body.hidden===!condition.open&&body.querySelectorAll('.grouped-offer').length>0;
    }
    return false;
  }
  function frameEndpoint(record,resolve) {
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
      record.second_raf_ms=performance.now()-record.started_at_ms;
      resolve(record);
    }));
  }
  let initialResolved;
  const initialPromise=new Promise(resolve=>{initialResolved=resolve;});
  const initialObserver=new MutationObserver(()=>{
    const observedAt=performance.now();
    if(!matches({kind:'results',cards:24,hotels:1047,offers:1453,star4:false}))return;
    initialObserver.disconnect();
    const record={name:'initial_loading',event:'navigation',trusted:null,started_at_ms:0,dom_ms:observedAt};
    frameEndpoint(record,initialResolved);
  });
  initialObserver.observe(document,{subtree:true,childList:true,attributes:true,characterData:true});
  function onEvent(event) {
    const current=armed;
    if(!current||current.started||event.type!==current.event||!(event.target instanceof Element)||!event.target.closest(current.selector))return;
    current.started=true;
    current.record={name:current.name,event:event.type,trusted:event.isTrusted,started_at_ms:performance.now()};
  }
  document.addEventListener('click',onEvent,true);
  document.addEventListener('change',onEvent,true);
  window.__search3Performance={
    initial:()=>initialPromise,
    arm(spec){
      if(armed)throw new Error('previous measurement remains armed');
      if(matches(spec.condition))throw new Error('endpoint already true before action: '+spec.name);
      let resolve,reject;
      const promise=new Promise((a,b)=>{resolve=a;reject=b;});
      const current={...spec,started:false,record:null,promise,resolve,reject};
      const target=q(spec.root);
      if(!target)throw new Error('observation root missing '+spec.root);
      current.observer=new MutationObserver(()=>{
        const observedAt=performance.now();
        if(!current.started||!matches(current.condition))return;
        current.observer.disconnect();clearTimeout(current.timer);armed=null;
        current.record.dom_ms=observedAt-current.record.started_at_ms;
        frameEndpoint(current.record,current.resolve);
      });
      current.observer.observe(target,{subtree:true,childList:true,attributes:true,characterData:true});
      current.timer=setTimeout(()=>{
        current.observer.disconnect();if(armed===current)armed=null;
        reject(new Error('measurement endpoint timed out: '+spec.name));
      },10000);
      records.set(spec.name,current);armed=current;
      // Attach a rejection handler immediately; Node retrieves the same promise later.
      promise.catch(()=>{});
    },
    wait(name){const record=records.get(name);if(!record)throw new Error('unknown measurement '+name);return record.promise;},
    semantic(){
      return {
        summary:normalize(q('#results-summary')?.textContent),
        cards:qa('#cards .hotel-card').map(el=>({id:el.dataset.hotelId,text:normalize(el.textContent)})),
        activeFilters:normalize(q('#active-filters')?.textContent),
        drawer:q('#filter-panel')?.classList.contains('open')?{
          preview:normalize(q('#filter-preview-count')?.textContent),
          selected:normalize(q('#drawer-selected')?.textContent)
        }:null,
        modal:q('#modal')?.open?{
          title:normalize(q('#modal-title')?.textContent),kicker:normalize(q('#modal-kicker')?.textContent),
          body:normalize(q('#modal-body')?.textContent),
          groups:qa('.offer-group-heading').map(el=>({key:el.dataset.value,expanded:el.getAttribute('aria-expanded')})),
          refinements:qa('#modal-body select[id^="offer-"]').map(el=>({
            id:el.id,value:el.value,options:[...el.options].map(option=>({value:option.value,text:normalize(option.textContent),disabled:option.disabled}))
          }))
        }:null
      };
    }
  };
}
const samples=[], failures=[];
let browser,phpVersion=null,deadlineExceeded=false,startedAt=new Date().toISOString();
const deadline=setTimeout(()=>{deadlineExceeded=true;browser?.close().catch(()=>{});},720*1000);deadline.unref();
async function closeDialog(page) {
  await page.locator('#modal [data-action="close-modal"]').click();
  await page.waitForFunction(()=>!document.querySelector('#modal').open&&!history.state?.['anytour.prototype.v18.ui.v1']);
  await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
}
async function trial(label,width,pair,warmup,order,origin) {
  const cpuRate=width===390?4:1;
  const context=await browser.newContext({viewport:{width,height},reducedMotion:'reduce',locale:'ru-RU',timezoneId:'UTC',colorScheme:'light'});
  const page=await context.newPage(),errors=[],requests=[],blockedImages=[],forbidden=[];
  page.setDefaultTimeout(10000);page.setDefaultNavigationTimeout(20000);
  page.on('pageerror',error=>errors.push(error.message));
  page.on('request',request=>requests.push({url:request.url(),type:request.resourceType()}));
  const session=await context.newCDPSession(page);
  await session.send('Emulation.setCPUThrottlingRate',{rate:cpuRate});
  await page.addInitScript(installProbe,{star4Ids});
  await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.origin!==origin){
      if(request.resourceType()==='image')blockedImages.push(url.href);else forbidden.push(url.href);
      await route.abort();return;
    }
    if(!url.pathname.startsWith(base)){forbidden.push(url.href);await route.abort();return;}
    await route.continue();
  });
  const record={label,width,height,cpu_rate:cpuRate,pair,warmup,order,steps:[],navigation:null,errors,forbidden,blocked_external_images:0,external_network_requests:0,owner_downloads:null};
  async function saveStep(timing,extra={}) {
    assert(Number.isFinite(timing.dom_ms)&&timing.dom_ms>=0);
    assert(Number.isFinite(timing.second_raf_ms)&&timing.second_raf_ms>=timing.dom_ms);
    if(timing.event==='click')assert.equal(timing.trusted,true,'click starts in a trusted browser input event');
    const semantic=await page.evaluate(()=>window.__search3Performance.semantic());
    const digest=sha(JSON.stringify(semantic));
    record.steps.push({...timing,...extra,semantic_digest:digest,semantic});
  }
  async function action(name,selector,condition,options={}) {
    const event=options.event||'click',root=options.root||(condition.kind==='drawer'?'#filter-panel':condition.kind==='results'?'#results':'#modal');
    await page.evaluate(spec=>window.__search3Performance.arm(spec),{name,event,selector,condition,root});
    if(event==='change')await page.locator(selector).selectOption(options.value);else await page.locator(selector).click();
    const timing=await page.evaluate(name=>window.__search3Performance.wait(name),name);
    await saveStep(timing,{endpoint:condition,input_method:event==='change'?'Playwright selectOption change event':'trusted pointer click'});
  }
  try {
    await page.goto(origin+base+'visual-search/?scenario=snapshot',{waitUntil:'domcontentloaded'});
    await saveStep(await page.evaluate(()=>window.__search3Performance.initial()),{endpoint:{cards:24,hotels:1047,offers:1453}});
    await page.waitForLoadState('load');
    record.initial_defaults=await page.evaluate(()=>({sort:document.querySelector('#sort').value,any_stars:document.querySelector('#quick-stars [data-action="any-stars"]').getAttribute('aria-pressed'),active_filter_count:document.querySelectorAll('#active-filters [data-action="remove-filter"]').length,drawer_open:document.querySelector('#filter-panel').classList.contains('open'),modal_open:document.querySelector('#modal').open}));
    assert.deepEqual(record.initial_defaults,{sort:'recommended',any_stars:'true',active_filter_count:0,drawer_open:false,modal_open:false});
    await page.evaluate(()=>document.fonts.ready);
    record.navigation=await page.evaluate(()=>{
      const n=performance.getEntriesByType('navigation')[0];
      return Object.fromEntries(['startTime','duration','fetchStart','requestStart','responseStart','responseEnd','domInteractive','domContentLoadedEventStart','domContentLoadedEventEnd','loadEventStart','loadEventEnd','transferSize','encodedBodySize','decodedBodySize'].map(key=>[key,n[key]]));
    });
    record.browser_environment=await page.evaluate(()=>({userAgent:navigator.userAgent,hardwareConcurrency:navigator.hardwareConcurrency,devicePixelRatio,timezone:Intl.DateTimeFormat().resolvedOptions().timeZone}));
    const initialScripts=requests.filter(request=>request.type==='script').map(request=>new URL(request.url).pathname);
    assert.equal(initialScripts.length,8,'snapshot loads 8 initial scripts');
    assert(!initialScripts.some(filename=>/offer-list-v1|hotel-details-v1|flight-picker-ui-v1/.test(filename)),'cold owners are absent initially');
    record.initial_scripts=initialScripts;

    if(width<=1100)await page.locator('.mobile-bottom [data-action="filters"]').click();
    const star='#filters [data-action="star"][data-value="4"]';
    if(!await page.locator(star).isVisible())await page.locator('#filters .filter-group').filter({has:page.locator('[data-action="star"][data-value="4"]')}).locator('.filter-section-toggle').click();
    if(width<=1100){
      await action('star4_draft_preview',star,{kind:'drawer',hotels:395,star4:true});
      await action('star4_apply','#apply-filters',{kind:'results',cards:24,hotels:395,offers:522,star4:true,drawer:false});
    }else{
      await action('star4_apply',star,{kind:'results',cards:24,hotels:395,offers:522,star4:true});
    }
    if(width<=1100){
      await page.locator('.mobile-bottom [data-action="filters"]').click();
      await action('star4_reset_draft_preview','#filter-panel .filter-top [data-action="reset"]',{kind:'drawer',hotels:1047,star4:false});
      await action('star4_reset','#apply-filters',{kind:'results',cards:24,hotels:1047,offers:1453,star4:false,drawer:false});
    }else{
      await action('star4_reset','#active-filters [data-action="remove-filter"][data-key="stars"][data-value="4"]',{kind:'results',cards:24,hotels:1047,offers:1453,star4:false});
    }
    for(let cards=48;cards<=240;cards+=24){
      await action('more_cards_'+cards,'#cards [data-action="more-cards"]',{kind:'results',cards,hotels:1047,offers:1453,star4:false});
    }
    const pagination=record.steps.filter(step=>step.name.startsWith('more_cards_'));
    const last=pagination.at(-1);
    record.steps.push({
      name:'pagination_24_to_240_sum',event:'9 separate clicks',trusted:true,
      dom_ms:pagination.reduce((sum,step)=>sum+step.dom_ms,0),
      second_raf_ms:pagination.reduce((sum,step)=>sum+step.second_raf_ms,0),
      metric_kind:'sum of separate browser processing intervals; driver and think time excluded',
      semantic_digest:last.semantic_digest,semantic:last.semantic
    });

    const visibleIds=await page.locator('#cards .hotel-card').evaluateAll(cards=>cards.map(card=>card.dataset.hotelId));
    const preferred=visibleIds.find(id=>{
      const hotel=hotelById.get(id);
      return hotel&&hotel.offers.length>=2&&new Set(hotel.offers.map(offer=>offer.dates)).size>1;
    });
    assert(preferred,'unchanged first240 cards contain a hotel with multiple departure dates');
    const chosen=hotelById.get(preferred),title=chosen.name,offerCount=chosen.offers.length;
    record.cold_owner_hotel={id:preferred,title,offers:offerCount};
    await action('hotel_details_cold','#hotel-'+preferred+' h3 [data-action="hotel-details"]',{kind:'hotel',title});
    await closeDialog(page);
    await action('hotel_details_warm','#hotel-'+preferred+' h3 [data-action="hotel-details"]',{kind:'hotel',title});
    await closeDialog(page);
    await action('all_offers_cold','#hotel-'+preferred+' [data-action="all-offers"]',{kind:'offers',title,offers:offerCount});
    assert.equal(await page.locator('[data-action="group-more"]').count(),0,'group-more is unreachable without altering saved offers');

    const choice=await page.evaluate(()=>{
      for(const id of ['offer-departure','offer-room','offer-meal','offer-flight']){
        const select=document.getElementById(id);
        if(!select||select.closest('label').hidden||select.options.length<3)continue;
        const option=[...select.options].find(option=>option.value&&/·\s*\d+\s*тур/.test(option.textContent));
        if(option)return{id,value:option.value,count:Number(option.textContent.match(/·\s*(\d+)\s*тур/)[1])};
      }
      return null;
    });
    assert(choice&&choice.count>0&&choice.count<offerCount,'reachable refinement removes at least one unchanged snapshot offer');
    if(!await page.locator('.offer-filter-disclosure').evaluate(element=>element.open))await page.locator('.offer-filter-disclosure > summary').click();
    await action('offer_refinement','#'+choice.id,{kind:'offers',title,offers:choice.count},{event:'change',value:choice.value});
    const field=choice.id.replace('offer-','');
    await action('offer_refinement_reset','[data-action="remove-offer-filter"][data-field="'+field+'"]',{kind:'offers',title,offers:offerCount});
    const group=await page.locator('.offer-group-heading').first().evaluate(element=>({key:element.dataset.value,open:element.getAttribute('aria-expanded')==='true'}));
    const heading='.offer-group-heading[data-value="'+group.key.replaceAll('\\','\\\\').replaceAll('"','\\"')+'"]';
    await action('offer_group_toggle_1',heading,{kind:'group',key:group.key,open:!group.open});
    await action('offer_group_toggle_2',heading,{kind:'group',key:group.key,open:group.open});
    await closeDialog(page);
    await action('all_offers_warm','#hotel-'+preferred+' [data-action="all-offers"]',{kind:'offers',title,offers:offerCount});
    await closeDialog(page);

    record.owner_downloads=Object.fromEntries(['hotel-details-v1.js','offer-list-v1.js','flight-picker-ui-v1.js'].map(filename=>[filename,requests.filter(request=>new URL(request.url).pathname.endsWith('/'+filename)).length]));
    assert.equal(record.owner_downloads['hotel-details-v1.js'],1);
    assert.equal(record.owner_downloads['offer-list-v1.js'],1);
    assert.equal(record.owner_downloads['flight-picker-ui-v1.js'],0);
    assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);
    record.blocked_external_images=blockedImages.length;
    record.blocked_external_image_origins=[...new Set(blockedImages.map(url=>new URL(url).origin))];
    record.local_requests=requests.filter(request=>new URL(request.url).origin===origin).map(request=>({path:new URL(request.url).pathname,type:request.type}));
    return record;
  } catch(error) {
    record.failure={error:error.stack||String(error),url:page.url()};
    record.blocked_external_images=blockedImages.length;
    const screenshot=path.join(path.dirname(output),'failure-'+label+'-'+width+'-'+pair+'.png');
    try{await page.screenshot({path:screenshot,timeout:3000});record.failure.screenshot=screenshot;}catch{}
    failures.push({partial_trial:record});
    throw error;
  } finally {await context.close();}
}
const median=values=>{const sorted=[...values].sort((a,b)=>a-b),mid=Math.floor(sorted.length/2);return sorted.length%2?sorted[mid]:(sorted[mid-1]+sorted[mid])/2;};
const percentile=(values,p)=>[...values].sort((a,b)=>a-b)[Math.min(values.length-1,Math.max(0,Math.ceil(p*values.length)-1))];
function bootstrapMedianCI(values,seed) {
  let state=seed>>>0;
  const random=()=>{state=(Math.imul(1664525,state)+1013904223)>>>0;return state/4294967296;};
  const estimates=[];
  for(let replicate=0;replicate<10000;replicate++)estimates.push(median(Array.from({length:values.length},()=>values[Math.floor(random()*values.length)])));
  return [percentile(estimates,.025),percentile(estimates,.975)];
}
function summarize() {
  const stats=[];
  for(const width of [1280,390]){
    const retained=samples.filter(sample=>sample.width===width&&!sample.warmup);
    const names=[...new Set(retained.flatMap(sample=>sample.steps.map(step=>step.name)))];
    for(const name of names){
      const before=[],after=[],deltas=[],ratios=[];
      for(let pair=0;pair<pairs;pair++){
        const a=retained.find(sample=>sample.pair===pair&&sample.label==='baseline')?.steps.find(step=>step.name===name);
        const b=retained.find(sample=>sample.pair===pair&&sample.label==='candidate')?.steps.find(step=>step.name===name);
        if(!a||!b)continue;
        assert.equal(a.semantic_digest,b.semantic_digest,'paired exact semantic output mismatch '+width+'/'+pair+'/'+name);
        before.push(a);after.push(b);
      }
      for(const field of ['dom_ms','second_raf_ms']){
        deltas.length=0;ratios.length=0;
        for(let i=0;i<before.length;i++){deltas.push(after[i][field]-before[i][field]);if(before[i][field]>0)ratios.push(after[i][field]/before[i][field]);}
        if(!deltas.length)continue;
        const baseline=before.map(step=>step[field]),candidate=after.map(step=>step[field]);
        const seed=Number.parseInt(sha(Buffer.from(width+'/'+name+'/'+field)).slice(0,8),16);
        stats.push({width,name,metric:field,n:deltas.length,
          baseline_median_ms:median(baseline),candidate_median_ms:median(candidate),
          baseline_p90_ms:percentile(baseline,.9),candidate_p90_ms:percentile(candidate,.9),
          median_paired_delta_ms:median(deltas),paired_delta_median_bootstrap_95ci_ms:bootstrapMedianCI(deltas,seed),
          median_paired_ratio:ratios.length?median(ratios):null,
          interpretation:'candidate minus baseline; p90 descriptive only; bootstrap paired median delta, 10000 seeded resamples; no significance/performance gate'});
      }
    }
  }
  return stats;
}
function writeReceipt(status,error=null) {
  let statistics=[],parityError=null;
  try{statistics=summarize();}catch(err){parityError=err.message;}
  // Only serialization is compacted. In-memory samples/parity/statistics retain
  // their full original semantic objects; every raw timing remains per trial.
  const semanticSnapshots=Object.create(null),snapshotJSON=new Map();
  function serializeStep(step){
    if(step.semantic===undefined)return {...step};
    const serialized=JSON.stringify(step.semantic),digest=sha(serialized);
    assert.equal(digest,step.semantic_digest,'serialized semantic digest must match recorded proof');
    if(snapshotJSON.has(digest))assert.equal(snapshotJSON.get(digest),serialized,'semantic digest collision');
    else{snapshotJSON.set(digest,serialized);semanticSnapshots[digest]=step.semantic;}
    const {semantic,...timingAndProof}=step;
    return timingAndProof;
  }
  const serializeTrial=sample=>({...sample,steps:sample.steps.map(serializeStep)});
  const serializedSamples=samples.map(serializeTrial);
  const serializedFailures=failures.map(failure=>failure.partial_trial?{...failure,partial_trial:serializeTrial(failure.partial_trial)}:{...failure});
  const receipt={
    schema:'search3.visual-performance.v1',status:parityError?'invalid':status,started_at:startedAt,finished_at:new Date().toISOString(),
    error:error?.stack||error||parityError,deadline_exceeded:deadlineExceeded,
    metadata:{node:process.version,platform:process.platform,arch:process.arch,cpu:os.cpus()[0]?.model,
      logical_cpus:os.cpus().length,browser:browser?.version(),php:phpVersion,
      cpu_throttling:{method:'CDP Emulation.setCPUThrottlingRate',physical_device:false},
      viewports:[{width:1280,height,cpu_rate:1},{width:390,height,cpu_rate:4}],retained_pairs_per_viewport:pairs,discarded_warmup_pairs_per_viewport:warmupPairs,
      context_policy:'fresh isolated context per variant/trial; warm browser process and OS caches; local HTTP no WAN emulation',
      order_policy:'AB/BA alternating and balanced per viewport',maximum_wall_seconds:720,
      timing_policy:'capturing trusted click or selectOption change -> expected DOM MutationObserver -> second subsequent rAF; no driver latency; paint proxy is not actual paint or INP'},
    fixture:{sha256:sha(fixtureBytes.baseline),bytes:fixtureBytes.baseline.length,hotels:1047,offers:1453,max_offers_per_hotel:maxOffers,star4_hotels:395,star4_offers:522,unchanged:true},
    inputs:artifacts,unmeasured:['group-more: saved snapshot max3 offers/hotel, button requires >4','dense per-hotel offer workloads','LIVE pristine17-owner canonical catalog/bootstrap loading (this run uses saved offline8-owner entry)','LIVE supplier/progressive update work','public-host/WAN timing','physical mobile devices','actual compositor paint, INP and input queue delay'],
    blocked_image_policy:'all external image requests aborted identically; original fixture and source URLs unchanged; no external connection allowed',
    server_errors:serverErrors,failures:serializedFailures,samples:serializedSamples,statistics,
    semantic_snapshots:semanticSnapshots,
    semantic_storage:'step.semantic_digest references exact full payload in semantic_snapshots; identical payloads stored once; complete and partial failed trials included; digest and collision equality checked',
    parity_policy:'exact JSON semantic payload digest per paired step; timings finished before snapshot/hashing; cards/summary/filter/modal/group/refinement content included'
  };
  fs.writeFileSync(output,JSON.stringify(receipt,null,2)+'\n');
  return receipt;
}
(async()=>{
  let caught=null;
  try {
    phpVersion=execFileSync('php',['--version'],{timeout:5000}).toString().split('\n')[0];
    const origins={};
    for(const label of ['baseline','candidate']){
      await new Promise(resolve=>servers[label].listen(0,'127.0.0.1',resolve));
      origins[label]='http://127.0.0.1:'+servers[label].address().port;
    }
    browser=await chromium.launch({headless:true,timeout:20000});
    for(const [widthIndex,width] of [1280,390].entries()){
      for(let index=-warmupPairs;index<pairs;index++){
        const warmup=index<0,pair=warmup?index+warmupPairs:index;
        const order=((index+warmupPairs+widthIndex)%2===0)?['baseline','candidate']:['candidate','baseline'];
        for(const label of order){
          assert(!deadlineExceeded,'finite 720 second deadline exceeded');
          const sample=await trial(label,width,pair,warmup,order.join('/'),origins[label]);
          samples.push(sample);
        }
        const a=samples.at(-2),b=samples.at(-1);
        assert.deepEqual(a.steps.map(step=>[step.name,step.semantic_digest]),b.steps.map(step=>[step.name,step.semantic_digest]),'paired semantic results must match');
        process.stdout.write(JSON.stringify({width,pair,warmup,order,samples:samples.length,status:'valid'})+'\n');
      }
    }
    assert.equal(samples.filter(sample=>!sample.warmup).length,pairs*4);
    assert.deepEqual(serverErrors,[]);
    const receipt=writeReceipt('valid');
    assert.equal(receipt.status,'valid',receipt.error||'receipt invalid');
    process.stdout.write('PASS compiled A/B performance diagnostics '+output+'\n');
  } catch(error) {
    caught=error;failures.push({error:error.stack||String(error),samples_completed:samples.length});
    writeReceipt('invalid',error);
    process.stderr.write(error.stack+'\n');process.exitCode=1;
  } finally {
    clearTimeout(deadline);
    await browser?.close().catch(()=>{});
    for(const server of Object.values(servers))if(server.listening)await new Promise(resolve=>server.close(resolve));
  }
})().catch(error=>{process.stderr.write(error.stack+'\n');process.exitCode=1;});
