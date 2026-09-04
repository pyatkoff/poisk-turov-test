<?php
require_once dirname(__DIR__) . '/site-path-v1.php';
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isStandalone = $host === 'anytoour.ru' || $host === 'www.anytoour.ru' || str_starts_with($host, 'anytoour.ru:') || str_starts_with($host, 'www.anytoour.ru:');
$siteBase = v2_site_base_path();
if (!defined('V2_SEARCH3_PRESENTATION')) define('V2_SEARCH3_PRESENTATION', $isStandalone);
if (!defined('V2_FORCE_SEARCH_PAGE')) define('V2_FORCE_SEARCH_PAGE', true);
if (!defined('V2_CANONICAL_PATH')) define('V2_CANONICAL_PATH', '/poisk-turov/');
if (!defined('V2_PUBLIC_BASE_PATH')) {
    if ($isStandalone) {
        define('V2_PUBLIC_BASE_PATH', $siteBase);
    } else {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = dirname(dirname($script));
        define('V2_PUBLIC_BASE_PATH', $base === '/' ? '' : $base);
    }
}
if ($siteBase !== '') {
    if (!defined('V2_API_PUBLIC_PATH')) define('V2_API_PUBLIC_PATH', '/api-v2.php');
    if (!defined('V2_LEAD_PUBLIC_PATH')) define('V2_LEAD_PUBLIC_PATH', v2_site_href('/preview-lead-disabled.php'));
}
$root = dirname(__DIR__);
$searchEntry = $root . '/search-page-v2.php';
if (!is_file($searchEntry)) $searchEntry = $root . '/index.php';
require $searchEntry;