<?php
/** Bounded GET-only bridge from retained monitor data; never starts or refreshes a search. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/hotel-monitor-prices-v1.php';
try {
    if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {
        header('Allow: GET'); http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'Only GET is allowed']); exit;
    }
    // Reject invalid namespaces/bounds before even connecting to the database.
    v2_hmp_request($_GET, gmdate('Y-m-d'));
    echo json_encode(v2_hmp_read(v2_data_db(),$_GET), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('hotel-monitor-prices-read-v1: '.$e->getMessage());
    http_response_code(503); echo json_encode(['ok'=>false,'error'=>'Retained monitor prices temporarily unavailable']);
}
