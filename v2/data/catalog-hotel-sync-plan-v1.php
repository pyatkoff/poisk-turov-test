<?php
/** Select a bounded batch of active Tourvisor countries whose hotel catalogs need refresh. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function hotel_plan_arg(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return $default;
}

$limitRaw = filter_var(hotel_plan_arg($argv, 'limit', '2'), FILTER_VALIDATE_INT);
$limit = $limitRaw === false ? 2 : max(1, min(10, (int)$limitRaw));
$staleHoursRaw = filter_var(hotel_plan_arg($argv, 'stale-hours', '168'), FILTER_VALIDATE_INT);
$staleHours = $staleHoursRaw === false ? 168 : max(6, min(720, (int)$staleHoursRaw));

$pdo = v2_data_db();
$sql = "SELECT
          c.id,
          c.name,
          COUNT(DISTINCT dc.departure_id) AS departure_count,
          MAX(CASE WHEN s.sync_key=CONCAT('hotels:country:',c.id) AND s.status='success' THEN s.finished_at END) AS last_success_at,
          MAX(CASE WHEN s.sync_key=CONCAT('hotels:country:',c.id) THEN s.status END) AS last_status
        FROM catalog_countries c
        JOIN catalog_departure_countries dc ON dc.country_id=c.id AND dc.is_active=1
        LEFT JOIN catalog_sync_state s ON s.sync_key=CONCAT('hotels:country:',c.id)
        WHERE c.is_active=1
        GROUP BY c.id,c.name
        HAVING last_success_at IS NULL OR last_success_at < DATE_SUB(NOW(), INTERVAL :stale_hours HOUR)
        ORDER BY
          CASE WHEN last_success_at IS NULL THEN 0 ELSE 1 END,
          departure_count DESC,
          COALESCE(last_success_at,'1970-01-01 00:00:00') ASC,
          c.id ASC
        LIMIT {$limit}";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':stale_hours', $staleHours, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ids = [];
foreach ($rows as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) continue;
    $ids[] = $id;
    fwrite(STDERR, sprintf(
        "HOTEL_SYNC_TARGET country=%d:%s departures=%d last_success=%s\n",
        $id,
        (string)($row['name'] ?? ''),
        (int)($row['departure_count'] ?? 0),
        (string)($row['last_success_at'] ?? 'never')
    ));
}

if ($ids === []) {
    echo "NONE\n";
    exit(0);
}

echo implode(',', $ids) . "\n";
