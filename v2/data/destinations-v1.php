<?php
/** Local AnyTour destination reference catalogs. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=3600');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db-v1.php';

function destinations_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function destinations_positive_int(mixed $value): ?int
{
    $v = filter_var($value, FILTER_VALIDATE_INT);
    return ($v === false || (int)$v <= 0) ? null : (int)$v;
}

$action = (string)($_GET['action'] ?? '');
try {
    $pdo = v2_data_db();
    if ($action === 'countries') {
        $departureId = destinations_positive_int($_GET['departureId'] ?? null);
        if ($departureId === null) destinations_out(['ok'=>true,'items'=>[],'source'=>'anytour-departure-country-matrix']);
        $stmt = $pdo->prepare("SELECT c.id,c.name
            FROM catalog_departure_countries dc
            INNER JOIN catalog_countries c ON c.id=dc.country_id AND c.is_active=1
            WHERE dc.departure_id=:departure AND dc.is_active=1
            ORDER BY c.name ASC");
        $stmt->execute(['departure'=>$departureId]);
        $items = array_map(static fn(array $r): array => [
            'id'=>(int)$r['id'],
            'name'=>(string)$r['name'],
            'russianName'=>(string)$r['name'],
        ], $stmt->fetchAll());
        destinations_out(['ok'=>true,'items'=>$items,'count'=>count($items),'source'=>'anytour-departure-country-matrix']);
    }
    if ($action === 'regions') {
        $countryId = destinations_positive_int($_GET['countryId'] ?? null);
        if ($countryId === null) destinations_out(['ok'=>true,'items'=>[],'source'=>'anytour-catalog']);
        $stmt = $pdo->prepare('SELECT id,country_id,name FROM catalog_regions WHERE is_active=1 AND country_id=:country ORDER BY name ASC');
        $stmt->execute(['country'=>$countryId]);
        $items = array_map(static fn(array $r): array => ['id'=>(int)$r['id'],'countryId'=>(int)$r['country_id'],'name'=>(string)$r['name'],'russianName'=>(string)$r['name']], $stmt->fetchAll());
        destinations_out(['ok'=>true,'items'=>$items,'count'=>count($items),'source'=>'anytour-catalog']);
    }
    if ($action === 'subregions') {
        $regionId = destinations_positive_int($_GET['regionId'] ?? null);
        if ($regionId === null) destinations_out(['ok'=>true,'items'=>[],'source'=>'anytour-catalog']);
        $stmt = $pdo->prepare('SELECT id,region_id,name FROM catalog_subregions WHERE is_active=1 AND region_id=:region ORDER BY name ASC');
        $stmt->execute(['region'=>$regionId]);
        $items = array_map(static fn(array $r): array => ['id'=>(int)$r['id'],'regionId'=>(int)$r['region_id'],'name'=>(string)$r['name'],'russianName'=>(string)$r['name']], $stmt->fetchAll());
        destinations_out(['ok'=>true,'items'=>$items,'count'=>count($items),'source'=>'anytour-catalog']);
    }
    destinations_out(['ok'=>false,'items'=>[],'error'=>'Unsupported catalog action'], 400);
} catch (Throwable $e) {
    error_log('destinations-v1: '.$e->getMessage());
    destinations_out(['ok'=>false,'items'=>[],'error'=>'Destination catalog is temporarily unavailable'], 503);
}
