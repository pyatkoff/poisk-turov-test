<?php
$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
if (!defined('V2_SEARCH2_PREVIEW') && str_starts_with($script, '/_preview/search2/')) {
    define('V2_SEARCH2_PREVIEW', true);
}
if (!defined('V2_PUBLIC_BASE_PATH') && defined('V2_SEARCH2_PREVIEW') && V2_SEARCH2_PREVIEW) {
    define('V2_PUBLIC_BASE_PATH', '/_preview/search2');
}
require_once __DIR__ . '/assets.php';
$type = strtolower((string)($_GET['type'] ?? ''));
$manifest = v2_bundle_manifest();
if (!isset($manifest[$type])) {
    http_response_code(404);
    exit;
}
$files = $manifest[$type];
$version = v2_bundle_content_version($type);
$requestedVersion = (string)($_GET['v'] ?? '');
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . ($type === 'css' ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8'));
header('ETag: "' . $version . '"');
if ($requestedVersion !== '' && hash_equals($version, $requestedVersion)) {
    header('Cache-Control: public, max-age=31536000, immutable');
} else {
    header('Cache-Control: no-cache');
}
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), '"') === $version) {
    http_response_code(304);
    exit;
}
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(500);
        echo $type === 'css' ? '/* missing bundle asset */' : 'throw new Error("Missing V2 bundle asset");';
        exit;
    }
    if ($type === 'css') {
        echo "\n/* --- " . $file . " --- */\n";
        readfile($path);
        echo "\n";
    } else {
        echo "\n;/* --- " . $file . " --- */\n";
        readfile($path);
        echo "\n;\n";
    }
}
