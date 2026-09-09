<?php

declare(strict_types=1);

const SEARCH3_CANDIDATE_LEAD_API = '/_preview/search3-candidate/poisk-turov/?lead=disabled';
const SEARCH3_CANDIDATE_ASSET_BASE = '/_preview/search3-candidate/poisk-turov/';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_GET['lead'] ?? '') === 'disabled') {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }
    http_response_code(403);
    echo json_encode(
        ['ok' => false, 'error' => 'PREVIEW_LEAD_DISABLED'],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (!headers_sent()) {
    header('X-Robots-Tag: noindex, follow');
}

define('V2_FORCE_SEARCH_PAGE', true);
define('V2_CANONICAL_PATH', '/_preview/search3-candidate/poisk-turov/');
define('V2_PUBLIC_BASE_PATH', '');

$params = is_array($params ?? null) ? $params : [];
$params['SEO_INDEXABLE'] = false;

ob_start();
$documentRoot = dirname(dirname(dirname(__DIR__)));
$productionSearchPage = $documentRoot . '/search-page-v2.php';
$sourceSearchPage = is_file($productionSearchPage)
    ? $productionSearchPage
    : $documentRoot . '/index.php';
require $sourceSearchPage;
$html = (string)ob_get_clean();
$productionLeadConfig = 'leadApi:"/lead-adapter-v2.php"';

if (substr_count($html, $productionLeadConfig) !== 1) {
    http_response_code(500);
    echo 'Search3 candidate configuration error';
    exit;
}

$html = str_replace(
    $productionLeadConfig,
    'leadApi:' . json_encode(SEARCH3_CANDIDATE_LEAD_API, JSON_UNESCAPED_SLASHES),
    $html
);

$html = preg_replace('/metrikaCounter:[0-9]+/', 'metrikaCounter:0', $html, -1, $metrikaConfigCount);
if ($metrikaConfigCount !== 1 || !is_string($html)) {
    http_response_code(500);
    echo 'Search3 candidate analytics isolation error';
    exit;
}

$candidateAssets = [
    'css' => 'search3-results-filters-v1.css',
    'js' => 'search3-results-filters-v1.js',
];
$assetUrls = [];
foreach ($candidateAssets as $type => $filename) {
    $path = __DIR__ . '/' . $filename;
    $hash = is_file($path) ? hash_file('sha256', $path) : false;
    if (!is_string($hash) || !preg_match('/^[0-9a-f]{64}$/', $hash)) {
        http_response_code(500);
        echo 'Search3 candidate presentation asset error';
        exit;
    }
    $assetUrls[$type] = SEARCH3_CANDIDATE_ASSET_BASE . $filename . '?v=' . substr($hash, 0, 16);
}

$primaryBundleScript = '<script src="'
    . htmlspecialchars(v2_bundle_asset('js'), ENT_QUOTES, 'UTF-8') . '"></script>';
$tabletFilterBootstrap = '<script id="search3-tablet-filter-bootstrap">'
    . '(function(){"use strict";if(!window.matchMedia)return;'
    . 'var nativeMatchMedia=window.matchMedia;'
    . 'window.__search3CandidateNativeMatchMedia=nativeMatchMedia;'
    . 'window.matchMedia=function(query){return nativeMatchMedia.call(window,'
    . 'String(query)==="(max-width:760px)"?"(max-width:999px)":query);};})();'
    . '</script>';
$tabletFilterRestore = '<script id="search3-tablet-filter-restore">'
    . '(function(){"use strict";var nativeMatchMedia=window.__search3CandidateNativeMatchMedia;'
    . 'if(typeof nativeMatchMedia!=="function")return;window.matchMedia=nativeMatchMedia;'
    . 'document.documentElement.dataset.search3MatchMediaRestored='
    . 'window.matchMedia===nativeMatchMedia?"1":"0";'
    . 'delete window.__search3CandidateNativeMatchMedia;})();'
    . '</script>';

$presentationMarkers = [
    '<body>' => '<body class="search3-candidate">',
    '</head>' => '<link id="search3-results-filters-v1-style" rel="stylesheet" href="'
        . htmlspecialchars($assetUrls['css'], ENT_QUOTES, 'UTF-8') . '"></head>',
    $primaryBundleScript => $tabletFilterBootstrap . $primaryBundleScript . $tabletFilterRestore,
    '</body>' => '<script id="search3-results-filters-v1-script" src="'
        . htmlspecialchars($assetUrls['js'], ENT_QUOTES, 'UTF-8') . '"></script></body>',
];
foreach ($presentationMarkers as $needle => $replacement) {
    if (substr_count($html, $needle) !== 1) {
        http_response_code(500);
        echo 'Search3 candidate presentation injection error';
        exit;
    }
    $html = str_replace($needle, $replacement, $html);
}

echo $html;
