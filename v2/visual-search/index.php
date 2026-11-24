<?php
// Isolated migration entry. Never expose this candidate as a production search.
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
if (!str_starts_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/_preview/search3-next-candidate/visual-search/')) {
    http_response_code(403);
    exit('Visual migration is only available in its separate preview.');
}
define('V2_VISUAL_ENTRY_ALLOWED', true);
require __DIR__ . '/entry-v1.php';
$scenario = is_string($_GET['scenario'] ?? null) ? $_GET['scenario'] : '';
$offline = in_array($scenario, ['snapshot', 'journey', 'flights', 'mixed', 'family', 'incomplete', 'price-change', 'unavailable', 'expired', 'flight-error', 'empty', 'recorded'], true);
echo v2_visual_search_replace(
    v2_visual_search_render($offline ? 'preview-offline' : 'preview-live'),
    '<form id="search-form" class="search-box">',
    '<form id="search-form" class="search-box" data-next-inline-pickers>'
);
