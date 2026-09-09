<?php
declare(strict_types=1);
// One separately reserved photograph request for the five saved content-pilot hotels.
error_reporting(0);
ob_start();
$result = ['status' => 'source_error', 'reason' => 'photos_unavailable'];
$locked = false;
$phase = 'input';
try {
    $root = realpath((string)getenv('HOME') . '/www/anytoour.ru');
    if (!$root || realpath((string)getcwd()) !== $root) throw new RuntimeException();
    $input = json_decode((string)file_get_contents('php://stdin', false, null, 0, 1025), true);
    if (!is_array($input) || !preg_match('/^[0-9a-f]{40}$/D', $input['source_sha'] ?? '')) throw new RuntimeException();
    $phase = 'configuration';
    require (string)getenv('HOME') . '/.anytoour-anex/search3-preview.php';
    if (!defined('ANYTOUR_ANEX_PREVIEW_ENABLED') || ANYTOUR_ANEX_PREVIEW_ENABLED !== true) throw new RuntimeException();
    require_once (is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    $pdo = v2_data_db();
    $phase = 'lock';
    $locked = (int)$pdo->query("SELECT GET_LOCK('anytour_anex_hotel_content',0)")->fetchColumn() === 1;
    if (!$locked) throw new RuntimeException();
    $phase = 'schema';
    $pdo->exec("CREATE TABLE IF NOT EXISTS anex_hotel_content_requests (
        batch_key VARCHAR(64) NOT NULL PRIMARY KEY, method VARCHAR(32) NOT NULL,
        status VARCHAR(24) NOT NULL, source_sha CHAR(40) NOT NULL,
        ids_json TEXT NOT NULL, report_json MEDIUMTEXT NULL,
        reserved_at_utc DATETIME NOT NULL, finished_at_utc DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $batch = 'owner_content_photos_pilot_20260909';
    $existing = $pdo->prepare('SELECT status,report_json FROM anex_hotel_content_requests WHERE batch_key=?');
    $existing->execute([$batch]);
    $saved = $existing->fetch(PDO::FETCH_ASSOC);
    if ($saved) {
        $result = ['status' => $saved['status'], 'supplier_requests' => 0, 'cached' => true,
            'report' => $saved['report_json'] === null ? null : json_decode($saved['report_json'], true)];
    } else {
        $phase = 'reservation';
        $rows = $pdo->query("SELECT anex_hotel_id,content_sha256,payload_json FROM anex_hotel_content
            WHERE batch_key='owner_content_pilot_20260909' AND status='ready' ORDER BY anex_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows || count($rows) > 5) throw new RuntimeException();
        $ids = array_map(static fn($row) => (int)$row['anex_hotel_id'], $rows);
        $reserve = $pdo->prepare("INSERT INTO anex_hotel_content_requests
            (batch_key,method,status,source_sha,ids_json,reserved_at_utc) VALUES (?,'Hotels_PHOTOS','in_flight',?,?,UTC_TIMESTAMP())");
        $reserve->execute([$batch, $input['source_sha'], json_encode($ids)]);
        $finish = $pdo->prepare('UPDATE anex_hotel_content_requests SET status=?,report_json=?,finished_at_utc=UTC_TIMESTAMP() WHERE batch_key=?');
        try {
            $phase = 'photos';
            $client = new AnyTourAnexClient(ANEX_API_TOKEN);
            $data = $client->request('Hotels_PHOTOS', ['HOTELS' => implode(',', $ids)]);
            if (!is_array($data) || !array_is_list($data) || count($data) > count($ids)) throw new RuntimeException();
            $byId = [];
            foreach ($data as $row) {
                $id = anytour_anex_content_id($row['hotelKey'] ?? null);
                if ($id === null || !in_array($id, $ids, true) || isset($byId[$id]) || !is_array($row['photos'] ?? null)) throw new RuntimeException();
                $byId[$id] = anytour_anex_hotel_content(['id' => $id, 'photos' => $row['photos']], $id, ANEX_API_TOKEN);
            }
            $summary = [];
            $pdo->beginTransaction();
            $write = $pdo->prepare('UPDATE anex_hotel_content SET payload_json=?,content_sha256=? WHERE anex_hotel_id=? AND content_sha256=?');
            foreach ($rows as $row) {
                $id = (int)$row['anex_hotel_id'];
                if (!isset($byId[$id])) {
                    $summary[] = ['id' => $id, 'photos' => null, 'status' => 'hotel_missing_from_response'];
                    continue;
                }
                $payload = json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
                $photo = $byId[$id];
                $payload['content']['photos'] = $photo['content']['photos'];
                $payload['availability']['photos'] = $photo['availability']['photos'];
                $payload['photos_source'] = ['method' => 'Hotels_PHOTOS', 'source_sha' => $input['source_sha'], 'fetched_at_utc' => gmdate('c')];
                $payload['source_sha256'] = hash('sha256', json_encode($payload['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
                $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                if (strlen($encoded) > 1500000 || strpos($encoded, ANEX_API_TOKEN) !== false) throw new RuntimeException();
                $write->execute([$encoded, $payload['source_sha256'], $id, $row['content_sha256']]);
                if ($write->rowCount() !== 1) throw new RuntimeException();
                $summary[] = ['id' => $id, 'photos' => count($payload['content']['photos']), 'status' => $payload['availability']['photos']];
            }
            $report = ['requested' => count($ids), 'returned' => count($data), 'rows' => $summary];
            $finish->execute(['completed', json_encode($report), $batch]);
            $pdo->commit();
            $result = ['status' => 'completed', 'supplier_requests' => 1, 'cached' => false, 'report' => $report];
        } catch (Throwable $ignored) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $diagnostic = isset($client) ? $client->lastRequestDiagnostics() : [];
            $error = ['reason' => 'photos_unavailable'];
            foreach (['http_status', 'supplier_code'] as $key) if (isset($diagnostic[$key]) && is_int($diagnostic[$key])) $error[$key] = $diagnostic[$key];
            $finish->execute(['source_error', json_encode($error), $batch]);
            $result = ['status' => 'source_error', 'supplier_requests' => 1, 'cached' => false, 'report' => $error];
        }
    }
} catch (Throwable $ignored) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $result = ['status' => 'source_error', 'reason' => 'photos_unavailable', 'phase' => $phase];
} finally {
    if ($locked && isset($pdo)) {
        try { $pdo->query("SELECT RELEASE_LOCK('anytour_anex_hotel_content')"); } catch (Throwable $ignored) {}
    }
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
