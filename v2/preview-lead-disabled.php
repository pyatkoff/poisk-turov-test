<?php
/** Whole-site preview sink: never forwards a lead outside the preview. */
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow', true);
echo json_encode([
    'ok' => false,
    'error' => 'PREVIEW_LEAD_DISABLED',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
