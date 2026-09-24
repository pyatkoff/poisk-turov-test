<?php
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
// One ordered TOP500 cohort feeds collectors, legacy V2 and prototype ranking.
$priorityHotelIds = [];
$popularitySource = dirname(__DIR__) . '/data/hotel-popularity-v1.php';
if (is_file($popularitySource)) {
    require_once $popularitySource;
    if (function_exists('v2_hotel_popularity_legacy_ids')) {
        foreach (v2_hotel_popularity_legacy_ids() as $id) {
            $id = (int)$id;
            if ($id > 0) $priorityHotelIds[$id] = $id;
        }
    }
}
// The host caches JS/CSS for a day. Bind each URL to the deployed file bytes.
$html = file_get_contents(__DIR__ . '/index.html');
$rehydrationNeedle = '<script src="./data.js" defer></script>';
if (substr_count($html, $rehydrationNeedle) !== 1) {
    http_response_code(500);
    exit('Prototype asset order is invalid.');
}
$html = str_replace(
    $rehydrationNeedle,
    $rehydrationNeedle . "\n  <script src=\"./rehydration-retention-v1.js\" defer></script>"
        . "\n  <script src=\"./calendar-exact-hotel-v1.js\" defer></script>",
    $html
);
$legacyIdAttribute = htmlspecialchars(implode(',', array_values($priorityHotelIds)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$html = str_replace('<body>', '<body data-popular-hotel-legacy-ids="' . $legacyIdAttribute . '">', $html);
echo preg_replace_callback(
    '/\b(src|href)="(\.\.?\/[^"?]+\.(?:js|css))"/',
    function ($match) {
        $path = __DIR__ . '/' . $match[2];
        if (!is_file($path)) return $match[0];
        return $match[1] . '="' . $match[2] . '?v=' . substr(hash_file('sha256', $path), 0, 12) . '"';
    },
    $html
);