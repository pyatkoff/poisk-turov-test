'use strict';

const assert = require('node:assert/strict');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { startFirefox } = require('./search3-firefox-driver.cjs');
const journey = require('./search3-firefox-journey.cjs');

const base = '/_preview/search3-next-candidate/';
const entry = base + 'visual-search/';
const mime = {
  '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2',
  '.svg': 'image/svg+xml', '.webp': 'image/webp', '.png': 'image/png',
  '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.avif': 'image/avif'
};

async function run() {
  const root = fs.realpathSync(process.argv[2]);
  const evidence = path.resolve(process.argv[3], 'firefox-rdm');
  fs.mkdirSync(evidence, {recursive: true});
  const php = '$_SERVER["SCRIPT_NAME"]=$argv[1]; $_GET["scenario"]="journey"; include $argv[2];';
  const html = execFileSync('php', ['-r', php, entry + 'index.php', path.join(root, 'visual-search/index.php')]);
  const sha = bytes => createHash('sha256').update(bytes).digest('hex');
  const appDigest = sha(fs.readFileSync(path.join(root, 'visual-search/app.js')));
  const blocked = [], appResponses = [];
  // Serve only the real offline entry and its static assets. Keep every API
  // path, form submission and outbound connection outside the fixture.
  const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1');
    const reject = () => {
      blocked.push({method: req.method, path: url.pathname});
      res.writeHead(403).end();
    };
    if (req.method !== 'GET') return reject();
    if (url.pathname === entry && url.searchParams.get('scenario') === 'journey') {
      res.setHeader('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'none'; form-action 'none'; base-uri 'none'; report-uri /blocked-request");
      res.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'}).end(html);
      return;
    }
    if (url.pathname === '/favicon.ico') { res.writeHead(204).end(); return; }
    const file = path.resolve(root, '.' + url.pathname.slice(base.length - 1));
    const type = mime[path.extname(file)];
    if (!url.pathname.startsWith(base) || !file.startsWith(root + path.sep) ||
        !type || !fs.existsSync(file) || !fs.statSync(file).isFile()) return reject();
    const bytes = fs.readFileSync(file);
    if (url.pathname === entry + 'app.js') appResponses.push(sha(bytes));
    res.writeHead(200, {'Content-Type': type}).end(bytes);
  });
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', resolve);
  });
  const origin = 'http://127.0.0.1:' + server.address().port;
  let driver;
  try {
    driver = await startFirefox();
    await driver.go(origin + entry + '?scenario=journey');
    const rdm = await driver.enableResponsiveMode();
    const stages = [];
    async function documentGuard(label) {
      const state = await driver.evaluate(`({
        width: innerWidth, height: innerHeight, touch: navigator.maxTouchPoints,
        documentWidth: document.documentElement.scrollWidth,
        dialogOverflow: [...document.querySelectorAll('dialog[open]')].some(d => d.scrollWidth > d.clientWidth + 1),
        app: [...document.scripts].map(s => s.src).find(src => new URL(src, location.href).pathname.endsWith('/visual-search/app.js')),
        url: location.href
      })`);
      assert.equal(new URL(state.url).origin, origin);
      assert.equal(state.app, origin + entry + 'app.js?v=' + appDigest.slice(0, 12));
      assert.deepEqual([state.width, state.height], [430, 932], 'RDM viewport at ' + label);
      assert.ok(state.touch > 0, 'touch emulation at ' + label);
      assert.ok(state.documentWidth <= state.width + 1 && !state.dialogOverflow, 'layout at ' + label);
      stages.push({stage: label, ...state});
    }
    const selection = await journey({driver, origin, base, evidence, documentGuard});
    assert.ok(appResponses.length && appResponses.every(hash => hash === appDigest), 'Firefox received the compiled app');
    assert.deepEqual(blocked, [], 'offline journey requested only allowed static assets');
    const receipt = {engine: driver.capabilities.browserName, version: driver.capabilities.browserVersion,
      platform: driver.capabilities.platformName, rdm, appSHA256: appDigest, entrySHA256: sha(html),
      mode: 'compiled offline journey served on loopback', selection, stages, blocked};
    fs.writeFileSync(path.join(evidence, 'result.json'), JSON.stringify(receipt, null, 2));
    console.log('PASS Firefox RDM whole journey', JSON.stringify(receipt));
  } catch (error) {
    console.error('Firefox journey stopped', {blocked, appDigest});
    if (driver) await driver.screenshot(path.join(evidence, 'last-state.png')).catch(() => {});
    throw error;
  } finally {
    try { if (driver) await driver.quit(); }
    finally { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
  }
}

if (require.main === module) run().catch(error => {console.error(error); process.exitCode = 1;});
