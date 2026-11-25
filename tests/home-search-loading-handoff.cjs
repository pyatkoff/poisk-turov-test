'use strict';
// Actual PHP source composition in an isolated fixture. Static resources are
// markers, not a compiled artifact; this suite does not execute browser JS or
// certify supplier starts, URL hydration, navigation, geometry or publication.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const source = path.resolve(__dirname, '../v2');
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'anytour-home-source-'));
const site = path.join(temporary, 'site');
const docroot = path.join(temporary, 'empty-document-root');
const visual = path.join(site, 'visual-search');
const buildPath = path.join(visual, 'public-build.json');
const program = `
  $_SERVER['DOCUMENT_ROOT'] = $argv[2];
  $_SERVER['SCRIPT_NAME'] = $argv[4];
  $_SERVER['REQUEST_URI'] = $argv[3] . '/';
  $_SERVER['HTTP_HOST'] = 'anytoour.ru';
  define('V2_PUBLIC_BASE_PATH', $argv[3]);
  define('V2_SITE_BASE_PATH', $argv[3]);
  define('METRIKA_COUNTER_ID', 1234567);
  $params = ['PHONE' => '8 (800) 100-61-50',
    'SEO_INDEXABLE' => $argv[5] === '1', 'SEO_INDEXABLE_PATHS' => ['/']];
  $_GET = [];
  require_once $argv[1] . '/site-path-v1.php';
  require_once $argv[1] . '/seo-config.php';
  $robots = v2_seo_robots_content(v2_seo_indexable($params));
  ob_start();
  register_shutdown_function(function () use ($robots) {
    echo json_encode(['status' => http_response_code() ?: 200,
      'robots' => $robots, 'html' => ob_get_clean()], JSON_THROW_ON_ERROR);
  });
  require $argv[1] . '/' . $argv[6];
`;

function profile(value) {
  fs.writeFileSync(buildPath, JSON.stringify({ schemaVersion: 1, profile: value, scenario: 'family' }));
}

function render({ prefix = '', route = prefix + '/index.php', indexable = false, entry = 'home-entry-v1.php' } = {}) {
  const result = spawnSync('php', ['-n', '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0',
    '-d', 'display_errors=stderr', '-r', program, site, docroot, prefix, route, indexable ? '1' : '0', entry],
  { encoding: 'utf8', timeout: 20000, maxBuffer: 8 * 1024 * 1024 });
  assert.equal(result.status, 0, result.stderr || result.error?.message || 'PHP did not complete');
  assert.equal(result.stderr, '', 'actual PHP render must be clean');
  const output = JSON.parse(result.stdout);
  assert.doesNotMatch(output.html, /(?:Warning|Fatal error|Deprecated):/);
  return output;
}

const scripts = html => [...html.matchAll(/<script\b[^>]*\bsrc="([^"]+)"[^>]*><\/script>/g)].map(row => row[1]);
const {resultsOnly} = require('./search3-public-home-assets.cjs');
const form = html => html.match(/<form\b[^>]*\bid="search-form"[^>]*>[\s\S]*?<\/form>/)?.[0];
function publicConfig(html) {
  const matches = [...html.matchAll(/data-search3-public-config="([^"]+)"/g)];
  assert.equal(matches.length, 1, 'one trusted public configuration owner');
  return JSON.parse(matches[0][1].replace(/&quot;/g, '"').replace(/&#0*39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&'));
}

try {
  fs.mkdirSync(visual, { recursive: true });
  fs.mkdirSync(docroot);
  // Keep real PHP owners and an empty private/document root. The marker asset
  // fixture follows search3_public_entry_test.py and is intentionally not served.
  for (const name of ['home-entry-v1.php', 'home-v1.php', 'site-header-v2.php', 'site-footer-v1.php',
    'phone-value.php', 'asset-version-v1.php', 'site-path-v1.php', 'assets.php', 'bundle-manifest-v1.php',
    'form-defaults.php', 'analytics-config.php', 'seo-config.php',
    'visual-search/entry-v1.php', 'visual-search/public-entry-v1.php', 'visual-search/index.html']) {
    fs.copyFileSync(path.join(source, name), path.join(site, name));
  }
  const htmlSource = fs.readFileSync(path.join(visual, 'index.html'), 'utf8');
  const rendererSource = fs.readFileSync(path.join(visual, 'entry-v1.php'), 'utf8');
  const resources = new Set([
    ...[...htmlSource.matchAll(/(?:src|href|data-[a-z-]+-src)="(\.\.?\/[^"?]+)"/g)].map(row => row[1]),
    ...[...rendererSource.matchAll(/'(\.\.?\/[^']+\.js)'/g)].map(row => row[1]),
  ]);
  for (const resource of resources) {
    const target = path.resolve(visual, resource);
    assert.ok(target.startsWith(site + path.sep), 'fixture resource remains inside the site');
    fs.mkdirSync(path.dirname(target), { recursive: true });
    if (!fs.existsSync(target)) fs.writeFileSync(target, 'PHP source-render fixture: ' + resource);
  }

  for (const [buildProfile, prefix] of [['live', ''], ['review', ''], ['review', '/_preview/search3-site-candidate']]) {
    profile(buildProfile);
    const receiving = render({ prefix, route: prefix + '/poisk-turov/index.php', entry: 'visual-search/public-entry-v1.php' });
    assert.equal(receiving.status, 200, 'canonical receiving renderer is available');
    assert.ok(form(receiving.html));
    for (const indexable of [false, true]) {
      const { html, robots, status } = render({ prefix, indexable });
      assert.equal(status, 200, 'healthy source composition must not fall back');
      assert.equal((html.match(/<header\b/g) || []).length, 1, 'one canonical header');
      assert.equal((html.match(/<footer\b/g) || []).length, 1, 'one factual home footer');
      assert.equal((html.match(/<h1\b/g) || []).length, 1);
      assert.equal((html.match(/<form\b/g) || []).length, 1, 'home owns exactly the shared Search3 form');
      const main = html.match(/<main\b[\s\S]*?<\/main>/)?.[0];
      assert.ok(main && form(main), 'Search3 form is inside the home content');
      const heroStart = main.indexOf('<section class="home-hero"');
      const nextSection = main.indexOf('<section class="home-section');
      assert.ok(heroStart >= 0 && nextSection > heroStart && main.indexOf('<form') < nextSection,
        'the shared form is composed into the hero');
      assert.equal(form(html).replace(/<details class="home-search-more"><summary>Дополнительные параметры<\/summary>([\s\S]*?)<\/details>/, '$1'),
        form(receiving.html), 'all form controls come from the canonical Search3 renderer');
      assert.match(form(html), /<details class="home-search-more"><summary>Дополнительные параметры<\/summary><button\b[^>]*id="quick-budget"/);
      for (const id of ['quick-stars', 'quick-meal']) assert.ok(form(html).includes(`id="${id}"`));
      assert.ok(html.includes('<div hidden inert data-home-search-support>'), 'result support is hidden and inert');
      assert.equal((html.match(/<dialog\b[^>]*id="modal"/g) || []).length, 1, 'one shared picker dialog');
      assert.doesNotMatch(main, /data-home-(?:departures|countries|children)|<iframe\b/,
        'the removed parallel form/controller must not return');
      assert.doesNotMatch(html, /home-journey-v1\.css|home-design-system-alignment-v1\.css/);
      for (const route of ['/poisk-turov/', '/country/', '/hot/', '/rb/']) {
        assert.ok(main.includes(`href="${prefix}${route}"`), `home handoff missing: ${route}`);
      }
      for (const route of ['/country/', '/hot/', '/contacts/', '/how-to-buy/', '/rb/']) {
        assert.ok(html.includes(`href="${prefix}${route}"`), `shared home navigation missing: ${route}`);
      }
      assert.doesNotMatch(html, /https:\/\/anytour\.online\/country\//, 'home navigation stays inside the standalone site');
      assert.ok(main.includes(`href="${prefix}/poisk-turov/?country=4"`), 'Turkey retains its real country identity');
      assert.ok(main.includes(`src="${prefix}/home-hero-v1.jpg?v=`));
      assert.doesNotMatch(main, /class="primary home-search-link"/, 'the fallback link is not a successful form render');
      const config = publicConfig(html);
      assert.equal(config.entry, 'home');
      assert.equal(config.profile, buildProfile);
      assert.equal(config.publicBase, prefix + '/poisk-turov/');
      assert.equal(config.resourceBase, prefix + '/visual-search/');
      assert.equal(config.metrikaCounter, buildProfile === 'live' ? 1234567 : 0);
      assert.equal(config.leadApi, '/_preview/search3-next-candidate/preview-lead-disabled.php');
      const graph = scripts(html);
      const receivingGraph = scripts(receiving.html);
      if (buildProfile === 'live') assert.equal(receivingGraph.filter(src => resultsOnly.includes(src.split('?')[0])).length, resultsOnly.length);
      assert.deepEqual(graph, buildProfile === 'live' ? receivingGraph.filter(src => !resultsOnly.includes(src.split('?')[0])) : receivingGraph,
        'the live home keeps the ordered form owners and the receiver keeps the results owners');
      assert.equal(new Set(graph).size, graph.length, 'no second script owner');
      assert.ok(graph[0].startsWith(prefix + '/visual-search/public-config-v1.js?v='));
      assert.ok(graph.at(-1).startsWith(prefix + '/visual-search/app.js?v='));
      assert.equal(graph.filter(src => /\/analytics-v4\.js\?v=/.test(src)).length, buildProfile === 'live' ? 1 : 0);
      if (buildProfile === 'review') assert.ok(graph.every(src => !/analytics|live-bridge/.test(src)));
      else assert.doesNotMatch(main, /fixture|demo|макет|семейный пример/i);
      assert.equal((html.match(/href="[^"]*\/site-header-v2\.css\?v=/g) || []).length, 1);
      assert.ok(html.includes('<link rel="canonical" href="https://anytoour.ru/">'));
      assert.ok(html.includes('<title>AnyTour — поиск и подбор туров онлайн</title>'));
      assert.ok(html.includes(`<meta name="robots" content="${robots}">`));
      if (prefix) assert.equal(robots, 'noindex,follow,max-image-preview:large');
      assert.equal((html.match(/data-anytour-favicon-bootstrap/g) || []).length, 0);
      assert.ok(html.includes('href="/favicon.ico?v=') && html.includes('href="/favicon.png?v='));
      const schema = JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)[1]);
      assert.deepEqual(schema['@graph'].map(row => row['@type']), ['WebSite', 'TravelAgency']);
      assert.ok(schema['@graph'].every(row => row.url === 'https://anytoour.ru/'));
      assert.equal(schema['@graph'][1].telephone, '8 (800) 100-61-50');
    }
    assert.equal(render({ prefix, route: prefix + '/' }).status, 200, 'exact root alias is accepted');
  }

  profile('live');
  for (const route of ['/home-entry-v1.php', '/poisk-turov/index.php', '/other/']) {
    const denied = render({ route });
    assert.equal(denied.status, 403, 'home renderer rejects non-home route ' + route);
    assert.ok(!form(denied.html));
  }
  assert.equal(render({ prefix: '/_preview/search3-site-candidate' }).status, 503,
    'the site preview cannot admit a live Home graph');
  fs.unlinkSync(buildPath);
  const unavailable = render();
  assert.equal(unavailable.status, 503, 'a missing generated build is a failed prerequisite, not a successful link-only home');
  assert.ok(!form(unavailable.html));
  assert.match(unavailable.html, /class="primary home-search-link" href="\/poisk-turov\/"/);
  assert.equal(scripts(unavailable.html).length, 0, 'a failed composition does not run a partial graph');
  console.log('HOME_SEARCH_SOURCE_RENDER_OK: real PHP, shared form/graph, exact routes, review isolation, fail-closed build, SEO/favicon/analytics; browser and native artifact remain separate gates');
} finally {
  fs.rmSync(temporary, { recursive: true, force: true });
}
