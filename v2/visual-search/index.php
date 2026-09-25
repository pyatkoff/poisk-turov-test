<?php
// Isolated migration entry. Never expose this candidate as a production search.
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
if (!str_starts_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/_preview/search3-next-candidate/visual-search/')) {
    http_response_code(403);
    exit('Visual migration is only available in its separate preview.');
}
$html = file_get_contents(__DIR__ . '/index.html');
echo preg_replace_callback('/\b(src|href)="(\.\/[^"?]+\.(?:js|css))"/', function ($match) {
    $path = __DIR__ . '/' . $match[2];
    if (!is_file($path)) return $match[0];
    return $match[1] . '="' . $match[2] . '?v=' . substr(hash_file('sha256', $path), 0, 12) . '"';
}, $html);
