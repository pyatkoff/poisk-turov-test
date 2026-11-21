// The approved home hands off to the canonical search form and no longer owns
// a second catalog/form controller. Exercise the actual PHP route boundary.
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');

function render(preview, indexable) {
  const prefix = preview ? '/_preview/search3-site-candidate' : '';
  const program = `
    $_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir() . '/anytour-home-empty-root';
    $_SERVER['SCRIPT_NAME'] = '${prefix}/index.php';
    $_SERVER['REQUEST_URI'] = '${prefix}/';
    $_SERVER['HTTP_HOST'] = 'anytoour.ru';
    define('V2_PUBLIC_BASE_PATH', '${prefix}');
    define('V2_SITE_BASE_PATH', '${prefix}');
    define('METRIKA_COUNTER_ID', 1234567);
    $params = ['PHONE' => '8 (800) 100-61-50',
      'SEO_INDEXABLE' => ${indexable ? 'true' : 'false'}, 'SEO_INDEXABLE_PATHS' => ['/']];
    $_GET = [];
    require_once 'v2/site-path-v1.php';
    require_once 'v2/seo-config.php';
    fwrite(STDERR, v2_seo_robots_content(v2_seo_indexable($params)));
    require 'v2/home-entry-v1.php';
  `;
  const result = spawnSync('php', ['-d', 'display_errors=1', '-r', program], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr || result.error?.message);
  return { html: result.stdout, robots: result.stderr };
}

for (const preview of [false, true]) {
  for (const indexable of [false, true]) {
    const { html, robots } = render(preview, indexable);
    const prefix = preview ? '/_preview/search3-site-candidate' : '';
    assert.doesNotMatch(html, /(?:Warning|Fatal error|Deprecated):/, 'actual PHP render must be clean');
    assert.equal((html.match(/<header\b/g) || []).length, 1, 'home renders one canonical header');
    assert.equal((html.match(/<footer\b/g) || []).length, 1, 'home retains the existing factual footer');
    assert.equal((html.match(/<h1\b/g) || []).length, 1);
    const main = html.match(/<main\b[\s\S]*?<\/main>/)?.[0];
    assert.ok(main, 'home content is rendered');
    for (const path of ['/poisk-turov/', '/country/', '/hot/', '/rb/']) {
      assert.ok(main.includes(`href="${prefix}${path}"`), `home handoff missing: ${path}`);
    }
    assert.ok(main.includes(`href="${prefix}/poisk-turov/?country=4"`), 'Turkey keeps its real country identity');
    assert.ok(main.includes(`class="primary home-search-link" href="${prefix}/poisk-turov/"`));
    assert.ok(main.includes(`src="${prefix}/home-hero-v1.jpg?v=`), 'approved hero is versioned inside the site prefix');
    assert.doesNotMatch(main, /<form\b|scenario=|fixture|demo|макет|семейный пример/i,
      'production home delegates real parameters to Search3 and carries no demonstration contract');
    assert.doesNotMatch(html, /data-home-search|data-home-departures|data-home-countries|fetch\(/,
      'merely opening home must not fetch catalogs or start a search');
    assert.doesNotMatch(html, /home-journey-v1\.css|home-design-system-alignment-v1\.css/,
      'superseded home skins are not loaded');
    assert.ok(html.includes('<link rel="canonical" href="https://anytoour.ru/">'));
    assert.ok(html.includes('<title>AnyTour — поиск и подбор туров онлайн</title>'));
    assert.ok(html.includes(`<meta name="robots" content="${robots}">`), 'existing scoped SEO policy remains authoritative');
    if (preview) assert.equal(robots, 'noindex,follow,max-image-preview:large');
    assert.equal((html.match(/data-anytour-favicon-bootstrap/g) || []).length, 0,
      'root retains its head favicon instead of replacing it after parse');
    assert.ok(html.includes('href="/favicon.ico?v=') && html.includes('href="/favicon.png?v='));
    const schema = JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)[1]);
    assert.deepEqual(schema['@graph'].map(row => row['@type']), ['WebSite', 'TravelAgency']);
    assert.ok(schema['@graph'].every(row => row.url === 'https://anytoour.ru/'));
    assert.equal(schema['@graph'][1].telephone, '8 (800) 100-61-50');
    assert.equal((html.match(/analytics-v4\.js\?v=/g) || []).length, 1,
      'existing analytics owner is included exactly once');
    assert.ok(html.includes('metrikaCounter:1234567'), 'home retains its configured public counter bridge');
  }
}
console.log('HOME_SEARCH_HANDOFF_OK: actual home routes, preview isolation, SEO, favicon and analytics');
