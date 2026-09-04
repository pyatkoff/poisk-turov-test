<?php

declare(strict_types=1);

const SEARCH3_CANDIDATE_LEAD_API = '/_preview/search3-candidate/poisk-turov/?lead=disabled';

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
require dirname(dirname(dirname(__DIR__))) . '/index.php';
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

echo $html;
