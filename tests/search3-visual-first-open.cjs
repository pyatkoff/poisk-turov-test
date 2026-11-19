'use strict';

const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {createHash} = require('node:crypto');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {setTimeout: delay} = require('node:timers/promises');
const {chromium} = require('playwright');
const {startFirefox} = require('./search3-firefox-driver.cjs');
const {fixture} = require('./search3-visual-live-fixture.cjs');

const base = '/_preview/search3-next-candidate/';
const entry = base + 'visual-search/';
const sha = bytes => createHash('sha256').update(bytes).digest('hex');
const types = {'.js':'text/javascript', '.css':'text/css', '.woff':'font/woff', '.woff2':'font/woff2',
  '.svg':'image/svg+xml', '.png':'image/png', '.webp':'image/webp', '.jpg':'image/jpeg', '.avif':'image/avif'};

// Installed before the real defer scripts. Read the browser's navigation clock at
// the first usable frame, independently of WebDriver polling and screenshot time.
function observeFirstOpen() {
  const sample = window.__firstOpen = {};
  let frame = 0;
  const form = () => document.querySelector('#search-form');
  function observe() {
    const button = form()?.querySelector('.search-submit');
    if (!button || sample.readyMs !== undefined) return;
    if (button.disabled) {sample.disabledMs ??= performance.now(); return;}
    if (sample.disabledMs === undefined || frame) return;
    frame = requestAnimationFrame(() => {
      frame = 0;
      const meal = form()?.querySelector('#quick-meal');
      if (!meal || meal.disabled || button.disabled || form().getAttribute('aria-busy') !== 'false') return;
      const labels = ['origin-label','destination-label','dates-label','nights-label','guests-label','meal-label']
        .map(id => document.getElementById(id)?.textContent.trim() || '');
      if (labels.some(value => !value)) return;
      const visible = [meal,button].every(el => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && r.top >= 0 && r.bottom <= innerHeight &&
          r.left >= 0 && r.right <= innerWidth && getComputedStyle(el).visibility === 'visible';
      });
      if (!visible) return;
      sample.readyMs = performance.now();
      sample.labels = labels;
      sample.viewport = [innerWidth, innerHeight];
      observer.disconnect();
    });
  }
  const observer = new MutationObserver(observe);
  observer.observe(document, {subtree:true, childList:true, attributes:true});
  document.addEventListener('submit', e => {
    if (e.target === form()) sample.trustedSubmit = e.isTrusted;
  }, true);
}

function catalogName(url, body) {
  const action = url.searchParams.get('action') || body.action;
  if (url.pathname === '/data/departures-v1.php') return 'departures';
  if (url.pathname === '/api-v2.php' && action === 'meals') return 'meals';
  if (url.pathname.endsWith('/data/search3-destination-read-v1.php') && ['countries','regions'].includes(action)) return action;
  if (url.pathname.endsWith('/data/search3-local-results-read-v1.php') && action === 'meal_catalog') return 'meal_catalog/' + body.provider;
  return '';
}

async function openReplay(root, html, catalogDelayMs) {
  const transport = fixture(), catalog = [], blocked = [], scripts = {}, requests = [];
  let navigation = 0, clicked = false;
  const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    const respond = (status, type, body) => {
      res.writeHead(status, {'Content-Type':type,'Cache-Control':'no-store',
        'Content-Security-Policy':"default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:; form-action 'none'; base-uri 'none'"});
      res.end(body);
    };
    try {
      if (req.method === 'GET' && url.pathname === entry) {
        navigation = performance.now();
        return respond(200, 'text/html; charset=utf-8', html);
      }
      if (url.pathname === '/favicon.ico' || url.pathname === '/blank') return respond(200, 'text/html', '<!doctype html><title>Local</title>');
      if (!url.pathname.endsWith('.php')) {
        const file = path.resolve(root, '.' + url.pathname.slice(base.length - 1));
        const type = types[path.extname(file)];
        assert.ok(req.method === 'GET' && url.pathname.startsWith(base) && file.startsWith(root + path.sep) && type, url.pathname);
        const bytes = fs.readFileSync(file);
        if (path.extname(file) === '.js') scripts[url.pathname.slice(base.length)] = sha(bytes);
        return respond(200, type, bytes);
      }
      const chunks = [];
      for await (const chunk of req) chunks.push(chunk);
      const payload = Buffer.concat(chunks).toString(), body = payload ? JSON.parse(payload) : {};
      const name = catalogName(url, body);
      if (name) {
        const read = {name, startMs:Math.round(performance.now() - navigation)};
        catalog.push(read);
        if (catalogDelayMs) await delay(catalogDelayMs);
        const data = await transport.json(url.href, {body:payload});
        read.endMs = Math.round(performance.now() - navigation);
        return respond(200, 'application/json', JSON.stringify(data));
      }
      assert.ok(clicked, 'unexpected request before search: ' + url.pathname);
      if (url.pathname === '/_preview/search3-anex-candidate/api-anex-search3-preview.php') {
        if (req.method === 'GET' && url.searchParams.get('action') === 'tv_token') {
          return respond(200, 'application/json', JSON.stringify({ok:true,provider:'tourvisor',csrf:'a'.repeat(64)}));
        }
        if (req.method === 'POST' && body.action === 'tv_search') requests.push(body.params);
      }
      if (url.pathname.endsWith('/data/search3-local-results-read-v1.php') && body.params) {
        return respond(200, 'application/json', JSON.stringify(await transport.json(url.href, {body:payload})));
      }
      // Capture the real adapter's tour search parameters; the request terminates
      // here, before any supplier service or external handoff.
      return respond(503, 'application/json', '{"ok":false,"error":"offline_replay"}');
    } catch (error) {
      blocked.push(String(error.message));
      respond(403, 'application/json', '{"ok":false}');
    }
  });
  await new Promise((resolve,reject) => {server.once('error', reject);server.listen(0, '127.0.0.1', resolve);});
  return {origin:'http://127.0.0.1:' + server.address().port, blocked, scripts, catalog, requests,
    allowClick() {clicked = true;},
    async close() {server.closeAllConnections();await new Promise(resolve => server.close(resolve));}};
}

async function measure(driver, engine, size, root, html, evidence) {
  const groups = [];
  for (const [delayMs, count] of [[150,9],[0,5]]) {
    const samples = [];
    for (let i = 0; i < count; i++) {
      const replay = await openReplay(root, html, delayMs);
      try {
        await driver.go(replay.origin + entry);
        let sample;
        for (let attempt = 0; attempt < 140; attempt++) {
          sample = await driver.evaluate('window.__firstOpen || {}');
          if (sample.readyMs !== undefined) break;
          await delay(70);
        }
        assert.ok(sample.readyMs > sample.disabledMs, 'no usable first frame: ' + JSON.stringify({sample,catalog:replay.catalog,blocked:replay.blocked}));
        assert.deepEqual(sample.viewport, size);
        const [origin,country,dates,nights,party,meal] = sample.labels;
        assert.equal(origin,'Москва');assert.equal(country,'Турция');assert.match(dates,/\d/);
        assert.match(nights,/7/);assert.match(party,/2/);assert.equal(meal,'Любое');
        if (i === 0 && delayMs) await driver.screenshot(path.join(evidence,engine + '.png'));
        assert.equal(replay.requests.length,0);
        const expected = await driver.evaluate('({...window.AnyTourPrototypeData.initialSearch})');
        replay.allowClick();
        await driver.click('.search-submit');
        for (let n=0;!replay.requests.length && n<100;n++) await delay(40);
        assert.equal(replay.requests.length, 1, 'native click must dispatch one tour search to loopback');
        const p = replay.requests[0];
        assert.deepEqual([String(p.departureId),String(p.countryId),p.dateFrom,p.dateTo,
          p.nightsFrom,p.nightsTo,p.adults,p.childs], ['1','4',expected.from,expected.to,7,7,2,[]]);
        assert.equal(await driver.evaluate('window.__firstOpen.trustedSubmit'),true);
        assert.deepEqual(replay.blocked,[]);
        for (const key of ['visual-search/app.js','prototype-search/data.js']) {
          assert.equal(replay.scripts[key], sha(fs.readFileSync(path.join(root,key))), 'compiled bytes ' + key);
        }
        samples.push({...sample, catalog:replay.catalog, request:{originId:p.departureId,countryId:p.countryId,
          dateFrom:p.dateFrom,dateTo:p.dateTo,adults:p.adults,childs:p.childs,nights:p.nightsFrom},
          scripts:replay.scripts, trustedSubmit:true});
      } finally {await replay.close();}
    }
    const sorted = samples.map(s => s.readyMs).sort((a,b) => a-b);
    groups.push({catalogDelayMs:delayMs, count, medianMs:sorted[Math.floor(count/2)], minMs:sorted[0],maxMs:sorted[count-1],samples});
  }
  return groups;
}

async function run() {
  const root=fs.realpathSync(process.argv[2]), evidence=path.resolve(process.argv[3]);
  fs.mkdirSync(evidence,{recursive:true});
  const php='$_SERVER["SCRIPT_NAME"]=$argv[1]; include $argv[2];';
  const original=execFileSync('php',['-r',php,entry+'index.php',path.join(root,'visual-search/index.php')]).toString();
  assert.ok(original.includes('data-search3-tv-session="enabled"') && original.includes('prototype-search/data.js?v='));
  const html=original.replace(/<head\b[^>]*>/i, head => head + '<script>(' + observeFirstOpen.toString() + ')()</script>');
  assert.notEqual(html, original);
  const report={source:execFileSync('git',['rev-parse','HEAD'],{cwd:__dirname,encoding:'utf8'}).trim(), entrySHA256:sha(original),
    method:'Navigation start to the first animation frame with a hydrated, enabled search, all six parameter labels, visible meal control and submit. Every navigation uses a fresh loopback origin and no-store responses. Fixed delay per catalog response; browser process and OS are warm. Suppliers and leads are offline.',
    engines:{}};
  const browser=await chromium.launch({headless:true,args:['--proxy-server=127.0.0.1:9','--proxy-bypass-list=127.0.0.1;localhost']});
  try {
    const page=await browser.newPage({viewport:{width:1280,height:800}});
    report.engines.chromium={version:browser.version(),viewport:[1280,800],groups:await measure({
      go:url=>page.goto(url),evaluate:expr=>page.evaluate(expr),click:selector=>page.locator(selector).click(),
      screenshot:file=>page.screenshot({path:file})},'chromium-1280',[1280,800],root,html,evidence)};
  } finally {await browser.close();}
  const firefox=await startFirefox(), landing=await openReplay(root,html,0);
  try {
    await firefox.go(landing.origin+'/blank');
    const rdm=await firefox.enableResponsiveMode();
    report.engines.firefox={version:firefox.capabilities.browserVersion,rdm,viewport:[430,932],
      groups:await measure(firefox,'firefox-rdm-430',[430,932],root,html,evidence)};
  } finally {await landing.close();await firefox.quit();}
  fs.writeFileSync(path.join(evidence,'result.json'),JSON.stringify(report,null,2));
  for (const [engine,run] of Object.entries(report.engines)) console.log('PASS first open',engine,
    run.groups.map(g=>({catalogDelayMs:g.catalogDelayMs,n:g.count,medianMs:g.medianMs,minMs:g.minMs,maxMs:g.maxMs})));
}
if (require.main === module) run().catch(error=>{console.error(error);process.exitCode=1;});
