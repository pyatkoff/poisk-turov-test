<?php
/** Fast local hotel autocomplete for AnyTour hotel-first search. */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db-v1.php';

function hotel_search_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hotel_search_optional_int(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    $v = filter_var($value, FILTER_VALIDATE_INT);
    return ($v === false || (int)$v <= 0) ? null : (int)$v;
}

$q = v2_data_normalize_text(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
if (mb_strlen($q, 'UTF-8') < 2) {
    hotel_search_out(['ok' => true, 'items' => [], 'query' => $q]);
}

$countryId = hotel_search_optional_int($_GET['countryId'] ?? null);
$regionId = hotel_search_optional_int($_GET['regionId'] ?? null);
$limitRaw = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);
$limit = max(1, min(20, $limitRaw === false ? 10 : (int)$limitRaw));

try {
    $pdo = v2_data_db();

    $where = ['h.is_active = 1'];
    $params = [];
    if ($countryId !== null) {
        $where[] = 'h.country_id = :country_id';
        $params['country_id'] = $countryId;
    }
    if ($regionId !== null) {
        $where[] = 'h.region_id = :region_id';
        $params['region_id'] = $regionId;
    }

    $params += [
        'exact_hotel' => $q,
        'prefix_hotel' => $q . '%',
        'exact_alias' => $q,
        'prefix_alias' => $q . '%',
        'contains_hotel' => '%' . $q . '%',
        'contains_alias_rank' => '%' . $q . '%',
        'contains_hotel_where' => '%' . $q . '%',
        'contains_key_where' => '%' . $q . '%',
        'contains_alias_where' => '%' . $q . '%',
    ];

    $sql = "
        SELECT DISTINCT
            h.id,
            h.name,
            h.country_id,
            h.country_name,
            h.region_id,
            h.region_name,
            h.subregion_id,
            h.subregion_name,
            h.category,
            h.rating,
            CASE
                WHEN h.normalized_name = :exact_hotel THEN 0
                WHEN h.normalized_name LIKE :prefix_hotel THEN 1
                WHEN a.normalized_alias = :exact_alias THEN 2
                WHEN a.normalized_alias LIKE :prefix_alias THEN 3
                WHEN h.normalized_name LIKE :contains_hotel THEN 4
                WHEN a.normalized_alias LIKE :contains_alias_rank THEN 5
                ELSE 9
            END AS rank_group
        FROM catalog_hotels h
        LEFT JOIN hotel_aliases a ON a.hotel_id = h.id
        WHERE " . implode(' AND ', $where) . "
          AND (
                h.normalized_name LIKE :contains_hotel_where
                OR h.search_key LIKE :contains_key_where
                OR a.normalized_alias LIKE :contains_alias_where
          )
        ORDER BY rank_group ASC, h.rating DESC, h.name ASC
        LIMIT " . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $items = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'country' => [
                'id' => (int)$row['country_id'],
                'name' => (string)$row['country_name'],
            ],
            'region' => $row['region_id'] !== null ? [
                'id' => (int)$row['region_id'],
                'name' => (string)($row['region_name'] ?? ''),
            ] : null,
            'subRegion' => $row['subregion_id'] !== null ? [
                'id' => (int)$row['subregion_id'],
                'name' => (string)($row['subregion_name'] ?? ''),
            ] : null,
            'category' => $row['category'] !== null ? (int)$row['category'] : null,
            'rating' => $row['rating'] !== null ? (float)$row['rating'] : null,
        ];
    }, $rows);

    hotel_search_out([
        'ok' => true,
        'query' => $q,
        'items' => $items,
        'count' => count($items),
        'source' => 'anytour-catalog',
    ]);
} catch (Throwable $e) {
    error_log('hotel-search-v1: ' . $e->getMessage());
    hotel_search_out([
        'ok' => false,
        'error' => 'Hotel catalog is temporarily unavailable',
        'items' => [],
    ], 503);
}
