<?php

declare(strict_types=1);

$mode = (string)($argv[1] ?? '');
if (!in_array($mode, ['production', 'preview'], true)) {
    fwrite(STDERR, "usage: php tests/site-preview-path-smoke.php production|preview\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'anytoour.ru';
$_SERVER['SCRIPT_NAME'] = $mode === 'preview'
    ? '/_preview/search3-site-candidate/poisk-turov/index.php'
    : '/poisk-turov/index.php';

define('V2_PUBLIC_BASE_PATH', $mode === 'preview' ? '/_preview/search3-site-candidate' : '');
if ($mode === 'preview') {
    define('V2_SITE_BASE_PATH', '/_preview/search3-site-candidate');
    define('V2_API_PUBLIC_PATH', '/api-v2.php');
    define('V2_LEAD_PUBLIC_PATH', '/_preview/search3-site-candidate/preview-lead-disabled.php');
}

require_once __DIR__ . '/../v2/site-path-v1.php';
require_once __DIR__ . '/../v2/assets.php';
require_once __DIR__ . '/../v2/site-header-v2.php';
require_once __DIR__ . '/../v2/site-footer-v1.php';
require_once __DIR__ . '/../v2/seo-page-primitives-v1.php';

$prefix = $mode === 'preview' ? '/_preview/search3-site-candidate' : '';
$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$expectedSearch = $prefix . '/poisk-turov/';
if (v2_site_base_path() !== $prefix) $fail('site base mismatch');
if (v2_site_href('/poisk-turov/') !== $expectedSearch) $fail('search path mismatch');
if (v2_site_href($expectedSearch) !== $expectedSearch) $fail('site path helper is not idempotent');
if (v2_site_href('https://example.com/') !== 'https://example.com/') $fail('external URL changed');
$sample = '<a href="/country/turkey/">Turkey</a><form action="/poisk-turov/"></form><img src="/images/logo.svg">';
$rewritten = v2_site_rewrite_preview_navigation($sample);
$expectedSample = '<a href="' . $prefix . '/country/turkey/">Turkey</a><form action="' . $prefix . '/poisk-turov/"></form><img src="/images/logo.svg">';
if ($rewritten !== $expectedSample) $fail('content navigation rewrite mismatch');

$asset = v2_public_path('app.css');
$expectedAsset = $prefix . '/app.css';
if ($asset !== $expectedAsset) $fail('asset path mismatch: ' . $asset);
$api = v2_public_path('api-v2.php');
$lead = v2_public_path('lead-adapter-v2.php');
if ($mode === 'preview') {
    if ($api !== '/api-v2.php') $fail('preview API escaped contract');
    if ($lead !== $prefix . '/preview-lead-disabled.php') $fail('preview lead sink mismatch');
} else {
    if ($api !== '/api-v2.php' || $lead !== '/lead-adapter-v2.php') $fail('production API/lead path changed');
}

ob_start();
v2_render_site_header('8 (800) 100-61-50', '+78001006150', '/country/turkey/');
$header = (string)ob_get_clean();
foreach (['/poisk-turov/', '/country/', '/hot/', '/rb/', '/how-to-buy/', '/contacts/'] as $path) {
    $expected = 'href="' . $prefix . $path . '"';
    if (!str_contains($header, $expected)) $fail('header path missing: ' . $expected);
}
if (substr_count($header, 'aria-current="page"') !== 2) $fail('header active state changed');

ob_start();
v2_render_site_footer('8 (800) 100-61-50', '+78001006150');
$footer = (string)ob_get_clean();
foreach (['/poisk-turov/', '/country/', '/hot/', '/rb/', '/how-to-buy/', '/contacts/', '/payment/', '/personal-data/', '/politika-konfidentsialnosti/'] as $path) {
    $expected = 'href="' . $prefix . $path . '"';
    if (!str_contains($footer, $expected)) $fail('footer path missing: ' . $expected);
}

$handoff = v2_seo_search_handoff_url('/poisk-turov/', [
    'from' => 1,
    'country' => 4,
    'dateFrom' => '2026-09-20',
    'dateTo' => '2026-09-27',
    'daysFrom' => 7,
    'daysTill' => 10,
    'count_people' => 2,
]);
if (!str_starts_with($handoff, $expectedSearch . '?')) $fail('SEO handoff escaped site candidate');
foreach (['from=1', 'country=4', 'dateFrom=2026-09-20', 'daysFrom=7', 'daysTill=10', 'count_people=2'] as $needle) {
    if (!str_contains($handoff, $needle)) $fail('SEO handoff lost state: ' . $needle);
}

$poiskEntry = (string)file_get_contents(__DIR__ . '/../v2/poisk-turov/index.php');
foreach (['V2_PUBLIC_BASE_PATH', 'V2_API_PUBLIC_PATH', 'V2_LEAD_PUBLIC_PATH', 'preview-lead-disabled.php'] as $needle) {
    if (!str_contains($poiskEntry, $needle)) $fail('search preview boundary missing: ' . $needle);
}

$leadSink = (string)file_get_contents(__DIR__ . '/../v2/preview-lead-disabled.php');
if (!str_contains($leadSink, 'PREVIEW_LEAD_DISABLED') || !str_contains($leadSink, 'http_response_code(403)')) {
    $fail('preview lead sink contract missing');
}

echo strtoupper($mode) . "_SITE_PREVIEW_PATHS_OK\n";
