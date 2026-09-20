<?php
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
// The host caches JS/CSS for a day. Bind each URL to the deployed file bytes.
echo preg_replace_callback(
    '/\b(src|href)="(\.\.?\/[^"?]+\.(?:js|css))"/',
    function ($match) {
        $path = __DIR__ . '/' . $match[2];
        if (!is_file($path)) return $match[0];
        return $match[1] . '="' . $match[2] . '?v=' . substr(hash_file('sha256', $path), 0, 12) . '"';
    },
    file_get_contents(__DIR__ . '/index.html')
);
