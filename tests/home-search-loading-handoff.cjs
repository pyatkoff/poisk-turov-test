'use strict';
// MAIN owns no visual-search source tree. This contract renders the exact native
// LIVE fixture admitted by home-native-artifact-fixture.py; it creates no assets.
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const hash = data => crypto.createHash('sha256').update(data).digest('hex');

function loadFixture() {
  assert(process.env.HOME_NATIVE_FIXTURE, 'HOME_NATIVE_FIXTURE must come from the pinned controller fixture builder');
  const root = fs.realpathSync(process.env.HOME_NATIVE_FIXTURE);
  const receipt = JSON.parse(fs.readFileSync(path.join(root, 'receipt.json'), 'utf8'));
  const manifestBytes = fs.readFileSync(path.join(root, 'manifest.json'));
  const manifest = JSON.parse(manifestBytes);
  assert.equal(hash(manifestBytes), receipt.home_manifest_sha256);
  assert.equal(receipt.source_sha, manifest.candidate.sha);
  assert.equal(receipt.source_tree, manifest.candidate.tree_sha);
  assert.equal(receipt.main_sha, manifest.main.sha);
  assert.deepEqual(manifest.profile, { schemaVersion: 1, profile: 'live', scenario: 'family' });
  assert.equal(hash(fs.readFileSync(path.resolve(__dirname, '../scripts/deploy/search3_public_install.py'))), receipt.controller_sha256,
    'the fixture was admitted by this exact checked-out controller');
  const site = path.join(root, 'site'), observed = [];
  function walk(directory) {
    for (const item of fs.readdirSync(directory, { withFileTypes: true })) {
      const target = path.join(directory, item.name);
      assert(!item.isSymbolicLink(), 'fixture cannot contain symlinks');
      if (item.isDirectory()) walk(target);
      else {
        assert(item.isFile(), 'fixture contains only regular files');
        const name = path.relative(site, target).split(path.sep).join('/');
        const data = fs.readFileSync(target), info = fs.statSync(target), expected = receipt.site_files[name];
        assert.deepEqual({ size: data.length, mode: (info.mode & 0o777).toString(8).padStart(4, '0'), sha256: hash(data) }, expected,
          'native fixture bytes/mode changed: ' + name);
        observed.push(name);
      }
    }
  }
  walk(site);
  assert.deepEqual(observed.sort(), Object.keys(receipt.site_files).sort(), 'complete native site inventory');
  assert.equal(hash(fs.readFileSync(path.join(root, 'transport.cjs'))), receipt.transport.sha256);
  for (const name of ['config.php', 'site_conf.php']) assert(!fs.existsSync(path.join(site, name)), 'no installed configuration');
  return { root, site, receipt, manifest };
}

const program = `
  $_SERVER['DOCUMENT_ROOT'] = $argv[1];
  $_SERVER['SCRIPT_NAME'] = $argv[2];
  $_SERVER['REQUEST_URI'] = $argv[2];
  $_SERVER['HTTP_HOST'] = 'anytoour.ru';
  define('METRIKA_COUNTER_ID', 1234567);
  $params = ['PHONE' => '8 (800) 100-61-50',
    'SEO_INDEXABLE' => $argv[4] === '1', 'SEO_INDEXABLE_PATHS' => ['/']];
  $_GET = [];
  ob_start();
  register_shutdown_function(function () {
    echo json_encode(['status' => http_response_code() ?: 200,
      'robots' => function_exists('v2_seo_robots_content') ? v2_seo_robots_content(v2_seo_indexable($GLOBALS['params'])) : null,
      'html' => ob_get_clean(), 'error' => error_get_last()], JSON_THROW_ON_ERROR);
  });
  require $argv[1] . '/' . $argv[3];
`;
function render(site, { route = '/index.php', owner = 'index.php', indexable = false } = {}) {
  const result = spawnSync('php', ['-n', '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0',
    '-d', 'display_errors=stderr', '-r', program, site, route, owner, indexable ? '1' : '0'],
    { encoding: 'utf8', timeout: 30000, maxBuffer: 8 * 1024 * 1024 });
  assert.equal(result.status, 0, result.stderr || result.error?.message || 'PHP did not complete');
  assert.equal(result.stderr, '', 'actual native PHP render must be clean');
  const output = JSON.parse(result.stdout);
  assert.equal(output.error, null);
  assert.doesNotMatch(output.html, /(?:Warning|Fatal error|Deprecated):/);
  return output;
}
const scripts = html => [...html.matchAll(/<script\b[^>]*\bsrc="([^"]+)"[^>]*><\/script>/g)].map(row => row[1]);
const form = html => html.match(/<form\b[^>]*\bid="search-form"[^>]*>[\s\S]*?<\/form>/)?.[0];
function publicConfig(html) {
  const matches = [...html.matchAll(/data-search3-public-config="([^"]+)"/g)];
  assert.equal(matches.length, 1, 'one trusted public configuration owner');
  return JSON.parse(matches[0][1].replace(/&quot;/g, '"').replace(/&#0*39;/g, "'")
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&'));
}
function main() {
  const native = loadFixture();
  const receiving = render(native.site, { route: '/poisk-turov/index.php', owner: 'poisk-turov/index.php' });
  assert.equal(receiving.status, 200, 'native canonical receiver is available');
  assert.ok(form(receiving.html));
  for (const indexable of [false, true]) {
    const { status, html, robots } = render(native.site, { indexable });
    assert.equal(status, 200, 'a fallback/503 is a failure');
    assert.equal((html.match(/<header\b/g) || []).length, 1);
    assert.equal((html.match(/<footer\b/g) || []).length, 1);
    assert.equal((html.match(/<h1\b/g) || []).length, 1);
    assert.equal((html.match(/<form\b/g) || []).length, 1, 'one shared native form');
    const main = html.match(/<main\b[\s\S]*?<\/main>/)?.[0];
    assert.ok(main && form(main));
    assert(main.indexOf('<section class="home-hero"') < main.indexOf('<form')
      && main.indexOf('<form') < main.indexOf('<section class="home-section'), 'form belongs to the first hero');
    const budget = /<details class="home-search-more"><summary>Дополнительные параметры<\/summary>([\s\S]*?)<\/details>/;
    assert.equal(form(html).replace(budget, '$1'), form(receiving.html), 'the entire form has one native owner');
    assert.match(form(html), /<details class="home-search-more"><summary>Дополнительные параметры<\/summary><button\b[^>]*id="quick-budget"/);
    for (const id of ['quick-stars', 'quick-meal']) assert.ok(form(html).includes(`id="${id}"`));
    assert.ok(html.includes('<div hidden inert data-home-search-support>'));
    assert.equal((html.match(/<dialog\b[^>]*id="modal"/g) || []).length, 1);
    assert.doesNotMatch(main, /home-search-link|data-home-(?:departures|countries|children)|<iframe\b|scenario=|fixture|demo|макет|семейный пример/i);
    for (const route of ['/poisk-turov/', '/country/', '/hot/', '/contacts/', '/how-to-buy/', '/rb/'])
      assert.ok(html.includes(`href="${route}"`), 'canonical navigation: ' + route);
    assert.ok(main.includes('href="/poisk-turov/?country=4"'));
    assert.ok(main.includes('src="/home-hero-v1.jpg?v='));
    assert.doesNotMatch(html, /https:\/\/anytour\.online\/country\/|home-journey-v1\.css|home-design-system-alignment-v1\.css/);
    const config = publicConfig(html), receiverConfig = publicConfig(receiving.html);
    assert.deepEqual(config, { ...receiverConfig, entry: 'home' });
    assert.equal(config.profile, 'live'); assert.equal(config.scenario, 'family');
    assert.equal(config.publicBase, '/poisk-turov/'); assert.equal(config.resourceBase, '/visual-search/');
    assert.equal(config.metrikaCounter, 1234567);
    assert.equal(config.leadApi, '/_preview/search3-next-candidate/preview-lead-disabled.php');
    const graph = scripts(html);
    assert.deepEqual(graph, scripts(receiving.html), 'complete ordered native graph is shared');
    assert.equal(new Set(graph).size, graph.length);
    assert.ok(graph[0].startsWith('/visual-search/public-config-v1.js?v='));
    assert.ok(graph.at(-1).startsWith('/visual-search/app.js?v='));
    assert.equal(graph.filter(src => /\/analytics-v4\.js\?v=/.test(src)).length, 1);
    assert.equal(graph.filter(src => /\/public-analytics-v1\.js\?v=/.test(src)).length, 1);
    assert.equal((html.match(/href="[^"]*\/site-header-v2\.css\?v=/g) || []).length, 1);
    assert.ok(html.includes('<link rel="canonical" href="https://anytoour.ru/">'));
    assert.ok(html.includes('<title>AnyTour — поиск и подбор туров онлайн</title>'));
    assert.ok(html.includes(`<meta name="robots" content="${robots}">`));
    assert.equal((html.match(/data-anytour-favicon-bootstrap/g) || []).length, 0);
    assert.ok(html.includes('href="/favicon.ico?v=') && html.includes('href="/favicon.png?v='));
    const schema = JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)[1]);
    assert.deepEqual(schema['@graph'].map(row => row['@type']), ['WebSite', 'TravelAgency']);
    assert.ok(schema['@graph'].every(row => row.url === 'https://anytoour.ru/'));
    assert.equal(schema['@graph'][1].telephone, '8 (800) 100-61-50');
  }
  assert.equal(render(native.site, { route: '/' }).status, 200);
  assert.equal(render(native.site, { route: '/home-entry-v1.php' }).status, 403, 'home exact-route guard');
  assert.equal(render(native.site, { route: '/other/', owner: 'poisk-turov/index.php' }).status, 403, 'receiving exact-route guard');
  const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'home-native-unavailable-'));
  try {
    const unavailable = path.join(temporary, 'site'); fs.cpSync(native.site, unavailable, { recursive: true });
    fs.unlinkSync(path.join(unavailable, 'visual-search/public-build.json'));
    for (const options of [{}, { route: '/poisk-turov/index.php', owner: 'poisk-turov/index.php' }]) {
      const result = render(unavailable, options);
      assert.equal(result.status, 503, 'missing generated profile cannot produce a green form');
      assert.ok(!form(result.html));
    }
  } finally { fs.rmSync(temporary, { recursive: true, force: true }); }
  loadFixture(); // Prove that neither rendering nor the negative case modified the admitted site.
  console.log('HOME_NATIVE_PHP_CONTRACT_OK', JSON.stringify({ source: native.receipt.source_sha,
    artifact: native.receipt.home_artifact_id, browser: 'separate gate', productionOperations: 0 }));
}
module.exports = { loadFixture };
if (require.main === module) main();
