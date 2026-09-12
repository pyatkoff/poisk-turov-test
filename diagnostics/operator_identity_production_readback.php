<?php
/** Read-only production counters for passive operator identity evidence. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = getenv('ANYTOUR_ROOT') ?: (__DIR__ . '/..');
require rtrim($root, '/') . '/data/db-v1.php';
$pdo = v2_data_db();

$exists = (bool)$pdo->query("SHOW TABLES LIKE 'tour_operator_identity_observations'")->fetchColumn();
echo 'IDENTITY_TABLE=' . ($exists ? 'PRESENT' : 'MISSING') . "\n";
if (!$exists) exit(0);

$row = $pdo->query("SELECT COUNT(*) rows_count, COUNT(DISTINCT hotel_id) hotels, COUNT(DISTINCT operator_id) operators, COALESCE(SUM(observation_count),0) total_observations, MAX(last_seen_at) last_seen_at, SUM(last_seen_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) seen_15m, SUM(last_seen_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) seen_1h FROM tour_operator_identity_observations")->fetch(PDO::FETCH_ASSOC) ?: [];
foreach (['rows_count','hotels','operators','total_observations','last_seen_at','seen_15m','seen_1h'] as $key) {
    echo 'IDENTITY_' . strtoupper($key) . '=' . ($row[$key] ?? '0') . "\n";
}

echo "IDENTITY_TOP_OPERATORS_BEGIN\n";
$stmt = $pdo->query("SELECT operator_id, COALESCE(NULLIF(operator_name,''),'UNKNOWN') operator_name, COUNT(*) rows_count, COUNT(DISTINCT hotel_id) hotels, SUM(observation_count) observations, MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY operator_id, operator_name ORDER BY observations DESC, hotels DESC LIMIT 20");
while ($op = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = preg_replace('/[^\p{L}\p{N} .&+_()\/-]+/u', '', (string)$op['operator_name']);
    echo implode('|', [(string)$op['operator_id'],$name,(string)$op['rows_count'],(string)$op['hotels'],(string)$op['observations'],(string)$op['last_seen_at']]) . "\n";
}
echo "IDENTITY_TOP_OPERATORS_END\n";
