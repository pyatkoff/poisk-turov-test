<?php
require_once __DIR__ . '/../v2/seo-launch-slice-v1.php';

function seo_static_preservation_fail(string $message): void
{
    fwrite(STDERR, "SEO_STATIC_PRESERVATION_FAIL:$message\n");
    exit(1);
}

/**
 * Independent snapshot of the 104 static URLs that were already launched.
 *
 * Keep this baseline separate from production matrix/catalog helpers so an
 * accidental allowlist rewrite cannot make the preservation check agree with
 * the same bad source. New approved URLs may be added, but these paths remain
 * a mandatory subset of both launch allowlists and the checked-in sitemap.
 */
function seo_preserved_static_launch_paths(): array
{
    $months = [
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
    ];
    $countryPaths = [
        '/country/turkey/',
        '/country/egypt/',
        '/country/maldives/',
    ];
    $turkeyResortPaths = [
        '/country/turkey/alanya/',
        '/country/turkey/antalya/',
        '/country/turkey/belek/',
        '/country/turkey/kemer/',
        '/country/turkey/side/',
    ];

    $paths = array_merge($countryPaths, $turkeyResortPaths);
    foreach (array_merge($countryPaths, $turkeyResortPaths) as $basePath) {
        foreach ($months as $month) {
            $paths[] = $basePath . $month . '/';
        }
    }

    $paths = array_values(array_unique($paths));
    sort($paths, SORT_STRING);
    return $paths;
}

function seo_assert_preserved_subset(array $baseline, array $actual, string $surface): void
{
    $missing = array_values(array_diff($baseline, $actual));
    if ($missing !== []) {
        seo_static_preservation_fail($surface . '_missing_' . implode(',', $missing));
    }
}

function seo_assert_protected_routes_absent(array $paths, string $surface): void
{
    foreach ($paths as $path) {
        if (str_contains($path, '/poisk-turov/') || $path === '/hotel/' || str_contains($path, '/hotel/')) {
            seo_static_preservation_fail($surface . '_protected_route_' . $path);
        }
    }
}

$preservedStaticPaths = seo_preserved_static_launch_paths();
if (count($preservedStaticPaths) !== 104) {
    seo_static_preservation_fail('baseline_count_' . count($preservedStaticPaths));
}

$staticAllowlist = v2_seo_static_controlled_launch_paths();
$controlledAllowlist = v2_seo_controlled_launch_paths();
seo_assert_preserved_subset($preservedStaticPaths, $staticAllowlist, 'static_allowlist');
seo_assert_preserved_subset($preservedStaticPaths, $controlledAllowlist, 'controlled_allowlist');
seo_assert_protected_routes_absent($staticAllowlist, 'static_allowlist');
seo_assert_protected_routes_absent($controlledAllowlist, 'controlled_allowlist');

$sitemapXml = file_get_contents(__DIR__ . '/../v2/sitemap.xml');
if ($sitemapXml === false) {
    seo_static_preservation_fail('sitemap_unreadable');
}
preg_match_all('~<loc>\s*https://anytoour\.ru([^<]+)</loc>~', $sitemapXml, $matches);
$sitemapPaths = array_values(array_unique(array_map('trim', $matches[1] ?? [])));
seo_assert_preserved_subset($preservedStaticPaths, $sitemapPaths, 'sitemap');
seo_assert_protected_routes_absent($sitemapPaths, 'sitemap');

echo "SEO_STATIC_PRESERVATION_OK baseline=104 static=" . count($staticAllowlist)
    . " controlled=" . count($controlledAllowlist)
    . " sitemap=" . count($sitemapPaths) . " protected=0\n";
