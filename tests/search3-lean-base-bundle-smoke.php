<?php
require __DIR__ . '/../v2/assets.php';

function lean_bundle_fail(string $message): void
{
    fwrite(STDERR, "SEARCH3_LEAN_BASE_FAIL: $message\n");
    exit(1);
}

$fullJs = v2_bundle_files('js', 'full');
$search3Js = v2_bundle_files('js', 'search3');
$fullCss = v2_bundle_files('css', 'full');
$search3Css = v2_bundle_files('css', 'search3');

foreach (['search-redesign-v2.js', 'conversion-confidence-v1.js', 'compare-refresh-guard-v1.js', 'mobile-search-summary-v1.js', 'primary-meal-ux-v1.js', 'search-params-filter-rail-v1.js'] as $excluded) {
    if (!in_array($excluded, $fullJs, true)) lean_bundle_fail('legacy owner missing: ' . $excluded);
    if (in_array($excluded, $search3Js, true)) lean_bundle_fail('legacy owner leaked into Search3: ' . $excluded);
}
if (count($fullJs) !== count($search3Js) + 6) lean_bundle_fail('unexpected JavaScript scope delta');
if (array_values(array_diff($fullCss, $search3Css)) !== ['conversion-confidence-v1.css', 'primary-meal-ux-v1.css', 'mobile-search-summary-v1.css', 'search-params-filter-rail-v1.css', 'results-layout-guard-v1.css', 'search-header-layout-guard-v1.css', 'ds2-search-tablet-filters-v1.css']) lean_bundle_fail('unreviewed CSS scope delta');
if (!in_array('conversion-confidence-v1.css', $fullCss, true)) lean_bundle_fail('legacy confidence CSS missing');
if (v2_bundle_content_version('css', 'full') === v2_bundle_content_version('css', 'search3')) lean_bundle_fail('CSS scope versions collide');
if (v2_bundle_content_version('js', 'full') === v2_bundle_content_version('js', 'search3')) lean_bundle_fail('scope versions collide');

$legacyUrl = v2_bundle_asset('js', 'full');
$search3Url = v2_bundle_asset('js', 'search3');
if (str_contains($legacyUrl, 'scope=')) lean_bundle_fail('legacy URL changed scope contract');
if (!str_contains($legacyUrl, 'search-redesign-v2.js')) lean_bundle_fail('legacy closure lost view owner');
if (!str_contains($search3Url, '&scope=search3')) lean_bundle_fail('Search3 scope missing from URL');
if (str_contains($search3Url, 'search-redesign-v2.js')) lean_bundle_fail('Search3 closure exposes excluded owner');

echo 'SEARCH3_LEAN_BASE_OK full_js=' . count($fullJs) . ' search3_js=' . count($search3Js) . "\n";
