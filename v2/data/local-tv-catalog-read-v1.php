<?php
/** Content only; GET never discovers hotels, fetches Tourvisor or writes data. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/local-tv-catalog-v1.php';

function local_tv_read_out(array $data, int $status=200): never
{
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

$directory=str_replace('\\','/',(string)realpath(__DIR__));
if (!str_ends_with($directory,'/_preview/search3-next-candidate/data')
    && !str_ends_with($directory,'/_preview/search3-local-candidate/data')) {
    local_tv_read_out(['ok'=>false,'error'=>'LOCAL catalogue is isolated to search preview'],403);
}
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {
    header('Allow: GET'); local_tv_read_out(['ok'=>false,'error'=>'Only GET is allowed'],405);
}
try {
    $keys=array_intersect(['tourvisorHotelIds','oldLocalHotelIds'],array_keys($_GET));
    if (count($keys)!==1 || count($_GET)!==1) throw new InvalidArgumentException('One ID namespace required');
    $key=array_values($keys)[0]; $ids=LocalTvCatalogV1::ids($_GET[$key]);
} catch (InvalidArgumentException) {
    local_tv_read_out(['ok'=>false,'error'=>'Invalid LOCAL hotel IDs'],400);
}
try {
    $catalogue=new LocalTvCatalogV1(v2_data_db());
    local_tv_read_out(['ok'=>true]+$catalogue->read($ids,$key==='oldLocalHotelIds'));
} catch (Throwable $e) {
    error_log('local-tv-catalog-read-v1: '.$e->getMessage());
    local_tv_read_out(['ok'=>false,'error'=>'Hotel content temporarily unavailable'],503);
}
