<?php
// This source entry belongs only to the separate native design-v2 preview.
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
$entry = '/_preview/search3-design-v2-candidate/visual-search/';
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
if ($script !== $entry . 'index.php') {
    http_response_code(403);
    exit('This entry is available only in the design-v2 preview.');
}
$html = file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
    http_response_code(503);
    exit('Search is temporarily unavailable.');
}
echo preg_replace_callback('/\b(src|href)="(\.\.?\/[^"?]+\.(?:js|css))"/', static function ($match) {
    $path = __DIR__ . '/' . $match[2];
    if (!is_file($path)) return $match[0];
    return $match[1] . '="' . $match[2] . '?v=' . substr(hash_file('sha256', $path), 0, 12) . '"';
}, $html);
