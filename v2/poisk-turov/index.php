<?php
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isStandalone = $host === 'anytoour.ru' || $host === 'www.anytoour.ru' || str_starts_with($host, 'anytoour.ru:') || str_starts_with($host, 'www.anytoour.ru:');
if (!defined('V2_FORCE_SEARCH_PAGE')) define('V2_FORCE_SEARCH_PAGE', true);
if (!defined('V2_CANONICAL_PATH')) define('V2_CANONICAL_PATH', '/poisk-turov/');
if (!defined('V2_PUBLIC_BASE_PATH')) {
    if ($isStandalone) {
        define('V2_PUBLIC_BASE_PATH', '');
    } else {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = dirname(dirname($script));
        define('V2_PUBLIC_BASE_PATH', $base === '/' ? '' : $base);
    }
}
$root = dirname(__DIR__);
$searchEntry = $root . '/search-page-v2.php';
if (!is_file($searchEntry)) $searchEntry = $root . '/index.php';
require $searchEntry;
