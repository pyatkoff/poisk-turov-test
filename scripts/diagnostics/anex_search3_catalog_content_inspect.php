<?php
declare(strict_types=1);
// Owner requested actual preserved text. No supplier client, DDL or data mutations.
error_reporting(0);
ob_start();
$result = ['status' => 'source_error', 'reason' => 'saved_content_unavailable'];
try {
    $root = realpath((string)getenv('HOME') . '/www/anytoour.ru');
    if (!$root || realpath((string)getcwd()) !== $root) throw new RuntimeException();
    require_once (is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    $pdo = v2_data_db();
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    $query = $pdo->prepare('SELECT anex_hotel_id,batch_key,status,source_sha,content_sha256,fetched_at_utc,'
        . ' CASE WHEN OCTET_LENGTH(payload_json)<=1500000 THEN payload_json ELSE NULL END AS payload_json'
        . ' FROM anex_hotel_content WHERE batch_key IN (?,?) ORDER BY anex_hotel_id LIMIT 31');
    $query->execute(['owner_content_pilot_20260909', 'owner_content_review_20260909_01']);
    $stored = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($stored) > 30) throw new RuntimeException();
    $items = [];
    foreach ($stored as $row) {
        $id = (int)$row['anex_hotel_id'];
        $item = ['id' => $id, 'batch_key' => $row['batch_key'], 'status' => $row['status'],
            'fetched_at_utc' => $row['fetched_at_utc'], 'source_sha' => $row['source_sha'],
            'content_sha256' => $row['content_sha256'], 'integrity_verified' => false];
        try {
            $payload = anytour_anex_content_saved_payload($row, $id);
            $data = $payload['content'];
            $item['integrity_verified'] = true;
            foreach (['name','description','address','location','transfer','note'] as $key) {
                $item[$key] = $data[$key] ?? '';
            }
            $item['description_bytes'] = strlen($item['description']);
            $item['description_sha256'] = hash('sha256', $item['description']);
            $item['attributes'] = $data['attributes'] ?? [];
            $item['rooms'] = $data['rooms'] ?? [];
            $item['photo_count'] = count($data['photos'] ?? []);
            $item['availability'] = $payload['availability'];
        } catch (Throwable $ignored) {
            $item['reason'] = 'stored_content_integrity_unavailable';
        }
        $items[] = $item;
    }
    $pdo->commit();
    $result = ['status' => 'ok', 'read_only' => true, 'supplier_requests' => 0,
        'collection_disabled' => true, 'rows' => $items, 'count' => count($items),
        'expected_completed_cohort_size' => 30];
} catch (Throwable $ignored) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
