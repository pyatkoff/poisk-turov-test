<?php
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isStandalone = $host === 'anytoour.ru' || $host === 'www.anytoour.ru' || str_starts_with($host, 'anytoour.ru:') || str_starts_with($host, 'www.anytoour.ru:');
$isSearch2Preview = str_starts_with($script, '/_preview/search2/');
if (!defined('V2_FORCE_SEARCH_PAGE')) define('V2_FORCE_SEARCH_PAGE', true);
if (!defined('V2_SEARCH2_PREVIEW')) define('V2_SEARCH2_PREVIEW', $isSearch2Preview);
if (!defined('V2_CANONICAL_PATH')) define('V2_CANONICAL_PATH', $isSearch2Preview ? '/_preview/search2/poisk-turov/' : '/poisk-turov/');
if (!defined('V2_PUBLIC_BASE_PATH')) {
    if ($isSearch2Preview) {
        define('V2_PUBLIC_BASE_PATH', '/_preview/search2');
    } elseif ($isStandalone) {
        define('V2_PUBLIC_BASE_PATH', '');
    } else {
        $base = dirname(dirname($script));
        define('V2_PUBLIC_BASE_PATH', $base === '/' ? '' : $base);
    }
}
$root = dirname(__DIR__);
$searchEntry = $root . '/search-page-v2.php';
if (!is_file($searchEntry)) $searchEntry = $root . '/index.php';
require $searchEntry;
