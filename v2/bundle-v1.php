<?php
require_once __DIR__ . '/assets.php';
$type = strtolower((string)($_GET['type'] ?? ''));
$scope = strtolower((string)($_GET['scope'] ?? 'full'));
try {
    $files = v2_bundle_files($type, $scope);
} catch (InvalidArgumentException $error) {
    http_response_code(404);
    exit;
}
$version = v2_bundle_content_version($type, $scope);
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
        $compact = null;
        if ($scope === 'search3') {
            require_once __DIR__ . '/search3-shared-runtime.php';
            $compact = v2_search3_compact_script($file);
        }
        if ($compact === null) readfile($path);
        else echo $compact;
        echo "\n;\n";
    }
}
