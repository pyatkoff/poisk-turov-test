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

foreach (['header-current-site.js', 'selected-tour-return-v1.js', 'flight-empty-recovery-v1.js', 'price-confidence-v1.js', 'results-filter-autorefresh-v1.js', 'results-depth-v1.js', 'results-local-filters-v1.js', 'search-redesign-v2.js', 'sales-leader-ui-v1.js', 'conversion-confidence-v1.js', 'compare-refresh-guard-v1.js', 'checkout-experience-v1.js', 'selected-tour-description-v1.js', 'mobile-search-summary-v1.js', 'primary-meal-ux-v1.js', 'search-progress-ux-v1.js', 'search-complete-recovery-v1.js', 'search-dirty-ux-v1.js', 'search-params-filter-rail-v1.js'] as $excluded) {
    if (!in_array($excluded, $fullJs, true)) lean_bundle_fail('legacy owner missing: ' . $excluded);
    if (in_array($excluded, $search3Js, true)) lean_bundle_fail('legacy owner leaked into Search3: ' . $excluded);
}
if (count($fullJs) !== count($search3Js) + 19) lean_bundle_fail('unexpected JavaScript scope delta');
if (array_values(array_diff($fullCss, $search3Css)) !== ['enhancements.css', 'design-v1.css', 'hotel-details-design.css', 'tour-design-v1.css', 'results-experience-v1.css', 'sales-leader-ui-v1.css', 'conversion-confidence-v1.css', 'checkout-experience-v1.css', 'header-current-site.css', 'primary-meal-ux-v1.css', 'search-progress-ux-v1.css', 'search-dirty-ux-v1.css', 'mobile-search-summary-v1.css', 'search-params-filter-rail-v1.css', 'search-shell-grid-v1.css', 'results-layout-guard-v1.css', 'search-header-layout-guard-v1.css', 'search-footer-rhythm-v1.css', 'ds2-search-tablet-filters-v1.css']) lean_bundle_fail('unreviewed CSS scope delta');
if (!in_array('enhancements.css', $fullCss, true)) lean_bundle_fail('legacy enhancements layer missing');
if (!in_array('design-v1.css', $fullCss, true)) lean_bundle_fail('legacy design layer missing');
if (!in_array('hotel-details-design.css', $fullCss, true)) lean_bundle_fail('legacy hotel details layer missing');
if (!in_array('tour-design-v1.css', $fullCss, true)) lean_bundle_fail('legacy tour design layer missing');
if (!in_array('results-experience-v1.css', $fullCss, true)) lean_bundle_fail('legacy results experience layer missing');
if (!in_array('header-current-site.css', $fullCss, true)) lean_bundle_fail('legacy header CSS missing');
if (!in_array('header-current-site.js', $fullJs, true)) lean_bundle_fail('legacy header runtime missing');
if (!in_array('selected-tour-return-v1.js', $fullJs, true)) lean_bundle_fail('legacy selected return runtime missing');
if (!in_array('flight-empty-recovery-v1.js', $fullJs, true)) lean_bundle_fail('legacy flight recovery runtime missing');
if (!in_array('price-confidence-v1.js', $fullJs, true)) lean_bundle_fail('legacy price confidence runtime missing');
if (!in_array('results-filter-autorefresh-v1.js', $fullJs, true)) lean_bundle_fail('legacy filter autorefresh runtime missing');
if (!in_array('results-depth-v1.js', $fullJs, true)) lean_bundle_fail('legacy results depth runtime missing');
if (!in_array('results-local-filters-v1.js', $fullJs, true)) lean_bundle_fail('legacy form-local filter runtime missing');
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
