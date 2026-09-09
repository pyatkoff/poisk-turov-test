<?php
declare(strict_types=1);
// Reproject only missing media from saved Tourvisor JSON; no API or identity changes.
error_reporting(0);
ob_start();
$result = ['status' => 'source_error', 'reason' => 'media_repair_unavailable'];
$phase = 'root';
try {
    $root = realpath((string)getenv('HOME') . '/www/anytoour.ru');
    if (!$root || realpath((string)getcwd()) !== $root) throw new RuntimeException();
    require_once (is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    $pdo = v2_data_db();
    $phase = 'read';
    $rows = $pdo->query("SELECT hotel_id,source_hash,raw_json FROM catalog_hotel_details
        WHERE status='success' AND (primary_image_url IS NULL OR TRIM(primary_image_url)='')
        AND raw_json IS NOT NULL AND OCTET_LENGTH(raw_json)<=2097152 ORDER BY hotel_id LIMIT 2001")->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 2000) throw new RuntimeException();
    $write = $pdo->prepare("UPDATE catalog_hotel_details SET primary_image_url=?,images_json=?
        WHERE hotel_id=? AND source_hash=? AND status='success'
        AND (primary_image_url IS NULL OR TRIM(primary_image_url)='')");
    $catalog = $pdo->prepare("UPDATE catalog_hotels SET primary_image_url=?,image_updated_at=UTC_TIMESTAMP()
        WHERE id=? AND (primary_image_url IS NULL OR TRIM(primary_image_url)='')");
    $check = $pdo->prepare('SELECT source_hash,raw_json,primary_image_url,images_json FROM catalog_hotel_details WHERE hotel_id=?');
    $changed = 0; $catalogChanged = 0; $photos = 0; $unchanged = 0;
    $phase = 'repair';
    $pdo->beginTransaction();
    foreach ($rows as $row) {
        $data = json_decode($row['raw_json'], true);
        if (!is_array($data) || (int)($data['id'] ?? 0) !== (int)$row['hotel_id']) throw new RuntimeException();
        $images = v2_hotel_detail_images($data);
        if (!$images) { $unchanged++; continue; }
        $json = json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $write->execute([$images[0], $json, $row['hotel_id'], $row['source_hash']]);
        if ($write->rowCount() !== 1) throw new RuntimeException();
        $catalog->execute([$images[0], $row['hotel_id']]);
        $catalogChanged += $catalog->rowCount();
        $check->execute([$row['hotel_id']]);
        $saved = $check->fetch(PDO::FETCH_ASSOC);
        if (!$saved || $saved['source_hash'] !== $row['source_hash'] || $saved['raw_json'] !== $row['raw_json']
            || $saved['primary_image_url'] !== $images[0] || json_decode($saved['images_json'], true) !== $images) throw new RuntimeException();
        $changed++; $photos += count($images);
    }
    $pdo->commit();
    $result = ['status' => 'ok', 'supplier_requests' => 0, 'selected' => count($rows),
        'details_repaired' => $changed, 'photos_retained' => $photos, 'catalog_missing_photos_filled' => $catalogChanged,
        'without_usable_photos' => $unchanged, 'raw_json_unchanged' => true,
        'description_and_fetch_time_untouched' => true];
} catch (Throwable $ignored) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $result = ['status' => 'source_error', 'reason' => 'media_repair_unavailable', 'phase' => $phase];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($result), "\n";
