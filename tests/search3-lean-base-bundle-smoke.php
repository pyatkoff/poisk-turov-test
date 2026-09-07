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

if (!in_array('search-redesign-v2.js', $fullJs, true)) lean_bundle_fail('legacy view owner missing');
if (in_array('search-redesign-v2.js', $search3Js, true)) lean_bundle_fail('legacy view owner leaked into Search3');
if (count($fullJs) !== count($search3Js) + 1) lean_bundle_fail('unexpected JavaScript scope delta');
if ($fullCss !== $search3Css) lean_bundle_fail('unreviewed CSS scope delta');
if (v2_bundle_content_version('js', 'full') === v2_bundle_content_version('js', 'search3')) lean_bundle_fail('scope versions collide');

$legacyUrl = v2_bundle_asset('js', 'full');
$search3Url = v2_bundle_asset('js', 'search3');
if (str_contains($legacyUrl, 'scope=')) lean_bundle_fail('legacy URL changed scope contract');
if (!str_contains($legacyUrl, 'search-redesign-v2.js')) lean_bundle_fail('legacy closure lost view owner');
if (!str_contains($search3Url, '&scope=search3')) lean_bundle_fail('Search3 scope missing from URL');
if (str_contains($search3Url, 'search-redesign-v2.js')) lean_bundle_fail('Search3 closure exposes excluded owner');

echo 'SEARCH3_LEAN_BASE_OK full_js=' . count($fullJs) . ' search3_js=' . count($search3Js) . "\n";
