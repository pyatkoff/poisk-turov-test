<?php
require_once __DIR__ . '/../v2/seo-config.php';
require_once __DIR__ . '/../v2/seo-launch-slice-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-catalog-v1.php';

function seo_launch_fail(string $message): void
{
    fwrite(STDERR, "SEO_LAUNCH_SLICE_FAIL:$message\n");
    exit(1);
}

$expected = [
    '/country/turkey/',
    '/country/turkey/alanya/',
    '/country/turkey/antalya/',
    '/country/turkey/belek/',
    '/country/turkey/kemer/',
    '/country/turkey/side/',
];

$paths = v2_seo_turkey_launch_paths();
if ($paths !== $expected) seo_launch_fail('unexpected_paths');
if (in_array('/poisk-turov/', $paths, true)) seo_launch_fail('search_route_must_not_be_indexable');
if (count($paths) !== count(array_unique($paths))) seo_launch_fail('duplicate_path');

$disabled = v2_seo_turkey_launch_site_params(['OTHER' => 'keep'], false);
if (!empty($disabled['SEO_INDEXABLE']) || ($disabled['SEO_INDEXABLE_PATHS'] ?? null) !== []) seo_launch_fail('disabled_gate');
if (($disabled['OTHER'] ?? '') !== 'keep') seo_launch_fail('site_params_mutated');

$enabled = v2_seo_turkey_launch_site_params([], true);
if (empty($enabled['SEO_INDEXABLE']) || ($enabled['SEO_INDEXABLE_PATHS'] ?? []) !== $paths) seo_launch_fail('enabled_gate');

$catalog = v2_seo_content_pilot_turkey_catalog();
$urls = v2_seo_turkey_launch_sitemap_urls($catalog, true);
$expectedUrls = array_map(static fn(string $path): string => 'https://anytoour.ru' . $path, $paths);
sort($expectedUrls, SORT_STRING);
if ($urls !== $expectedUrls) seo_launch_fail('sitemap_drift');
if (v2_seo_turkey_launch_sitemap_urls($catalog, false) !== []) seo_launch_fail('sitemap_disabled_gate');

$xml = v2_seo_turkey_launch_sitemap_xml($catalog, true);
foreach ($expectedUrls as $url) {
    if (!str_contains($xml, $url)) seo_launch_fail('xml_missing_' . $url);
}
if (str_contains($xml, '/poisk-turov/')) seo_launch_fail('xml_search_route_leak');

// Defense-in-depth: even if an upstream bug manually injects an approved/publishable
// hotel-tour candidate, the publication manifest must still refuse the new page type
// until it receives a separate explicit launch decision.
$roguePath = '/country/turkey/hotel/rogue-hotel-999999/';
$rogue = $catalog;
$sourcePage = $catalog['registry']['/country/turkey/']['page'] ?? null;
if (!is_array($sourcePage)) seo_launch_fail('rogue_source_page_missing');
$rogue['registry'][$roguePath] = [
    'path' => $roguePath,
    'type' => 'hotel_tours',
    'page' => $sourcePage,
];
$rogue['reports'][$roguePath] = [
    'id' => 'hotel_tours.turkey.999999.v1',
    'status' => 'approved',
    'publishable' => true,
    'errors' => [],
];
$rogue['graph'][$roguePath] = ['parent' => '/country/turkey/', 'related' => []];
$rogue['publication_candidates'][] = $roguePath;
try {
    v2_seo_publication_manifest($rogue);
    seo_launch_fail('hotel_tours_publication_fence_bypassed');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'separate launch decision')) {
        seo_launch_fail('hotel_tours_publication_fence_wrong_error');
    }
}

// The sitemap gate must also fail closed because it consumes the publication manifest.
try {
    v2_seo_sitemap_candidate_urls($rogue, true, [$roguePath]);
    seo_launch_fail('hotel_tours_sitemap_fence_bypassed');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'separate launch decision')) {
        seo_launch_fail('hotel_tours_sitemap_fence_wrong_error');
    }
}

echo "SEO_LAUNCH_SLICE_OK paths=6 indexGate=1 sitemapGate=1 noSearchLeak=1 publicationTypeFence=1\n";
