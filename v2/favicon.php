<?php
/**
 * Compatibility favicon endpoint.
 * The owner-provided artwork remains the single source of truth in favicon.svg;
 * this endpoint exposes its embedded PNG directly for browsers that are picky
 * about SVG favicons containing data-URI raster images.
 */
$svg = @file_get_contents(__DIR__ . '/favicon.svg');
if (!is_string($svg) || !preg_match('~data:image/png;base64,([^"\']+)~', $svg, $matches)) {
    http_response_code(404);
    exit;
}

$png = base64_decode($matches[1], true);
if (!is_string($png) || $png === '') {
    http_response_code(404);
    exit;
}

$etag = '"' . hash('sha256', $png) . '"';
header('Content-Type: image/png');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=604800');
header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . strlen($png));
echo $png;
