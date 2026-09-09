<?php
/** Fast local departure-city catalog for the AnyTour search form. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=3600');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db-v1.php';

function departures_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = v2_data_db();
    $rows = $pdo->query("SELECT id, name, name_genitive FROM catalog_departures WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    $items = array_map(static function (array $row): array {
        $name = trim((string)$row['name']);
        $genitive = trim((string)($row['name_genitive'] ?? ''));
        return [
            'id' => (int)$row['id'],
            'name' => $name,
            'russianName' => $name,
            'nameGenitive' => $genitive !== '' ? $genitive : $name,
        ];
    }, $rows);
    departures_out(['ok' => true, 'items' => $items, 'count' => count($items), 'source' => 'anytour-catalog']);
} catch (Throwable $e) {
    error_log('departures-v1: ' . $e->getMessage());
    departures_out(['ok' => false, 'items' => [], 'error' => 'Departure catalog is temporarily unavailable'], 503);
}
