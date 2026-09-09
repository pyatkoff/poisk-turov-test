<?php
declare(strict_types=1);
// Owner-requested content collection. This separate store never changes hotel identities.
error_reporting(0);
ob_start();
$result = ['status' => 'source_error', 'reason' => 'content_unavailable'];
$locked = false;
$phase = 'input';
try {
    $root = realpath((string)getenv('HOME') . '/www/anytoour.ru');
    if (!$root || realpath((string)getcwd()) !== $root) throw new RuntimeException();
    $input = json_decode((string)file_get_contents('php://stdin', false, null, 0, 200001), true);
    if (!is_array($input) || !preg_match('/^[0-9a-f]{40}$/D', $input['source_sha'] ?? '')
        || !is_array($input['verified_ids'] ?? null) || count($input['verified_ids']) > 10000) throw new RuntimeException();
    $ids = [];
    foreach ($input['verified_ids'] as $id) {
        if (!is_int($id) || $id < 1 || $id > 2147483647) throw new RuntimeException();
        $ids[$id] = true;
    }
    if (!$ids) throw new RuntimeException();
    $plan = anytour_anex_content_plan($input['plan'] ?? null);
    $phase = 'configuration';
    require (string)getenv('HOME') . '/.anytoour-anex/search3-preview.php';
    if (!defined('ANYTOUR_ANEX_PREVIEW_ENABLED') || ANYTOUR_ANEX_PREVIEW_ENABLED !== true) throw new RuntimeException();
    require_once $root . '/_preview/search3-anex-candidate/app/integrations/anex-client.php';
    require_once (is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    $pdo = v2_data_db();
    $phase = 'lock';
    $locked = (int)$pdo->query("SELECT GET_LOCK('anytour_anex_hotel_content',0)")->fetchColumn() === 1;
    if (!$locked) throw new RuntimeException();
    $phase = 'schema';
    $pdo->exec("CREATE TABLE IF NOT EXISTS anex_hotel_content (
        anex_hotel_id INT UNSIGNED NOT NULL PRIMARY KEY,
        batch_key VARCHAR(64) NOT NULL,
        status VARCHAR(24) NOT NULL,
        source_sha CHAR(40) NOT NULL,
        content_sha256 CHAR(64) NULL,
        payload_json MEDIUMTEXT NULL,
        reason VARCHAR(64) NULL,
        reserved_at_utc DATETIME NOT NULL,
        fetched_at_utc DATETIME NULL,
        INDEX content_batch (batch_key), INDEX content_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS anex_hotel_content_requests (
        batch_key VARCHAR(64) NOT NULL PRIMARY KEY, method VARCHAR(32) NOT NULL,
        status VARCHAR(24) NOT NULL, source_sha CHAR(40) NOT NULL,
        ids_json TEXT NOT NULL, report_json MEDIUMTEXT NULL,
        reserved_at_utc DATETIME NOT NULL, finished_at_utc DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // The immutable manifest also reserves empty cohorts and interrupted requests.
    // The completed pilot is neither selected nor updated by this new review batch.
    $batch = $plan['batch_key'];
    $manifest = $pdo->prepare('SELECT method,status,ids_json FROM anex_hotel_content_requests WHERE batch_key=?');
    $manifest->execute([$batch]);
    $saved = $manifest->fetch(PDO::FETCH_ASSOC);
    $select = $pdo->prepare('SELECT * FROM anex_hotel_content WHERE batch_key=? ORDER BY anex_hotel_id');
    $select->execute([$batch]);
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);
    $newIds = [];
    $batchIds = [];
    if ($saved) {
        $batchIds = json_decode($saved['ids_json'], true, 512, JSON_THROW_ON_ERROR);
        if ($saved['method'] !== 'Hotels_DETAILS' || !is_array($batchIds) || !array_is_list($batchIds)
            || count($batchIds) > $plan['limit']) throw new RuntimeException();
        $storedIds = array_map(static fn($row) => (int)$row['anex_hotel_id'], $rows);
        $expectedIds = $batchIds;
        sort($expectedIds);
        if ($storedIds !== $expectedIds) throw new RuntimeException();
    } else {
        if ($rows) throw new RuntimeException();
        $phase = 'reservation';
        $observed = $pdo->query('SELECT o.anex_hotel_id,o.search_count,o.last_seen_utc,'
            . ' m.anex_hotel_id AS mapped_hotel_id,c.anex_hotel_id AS content_hotel_id'
            . ' FROM anex_search_hotel_observations o'
            . ' LEFT JOIN anex_hotel_search_mappings m ON m.anex_hotel_id=o.anex_hotel_id AND m.enabled=1'
            . ' LEFT JOIN anex_hotel_content c ON c.anex_hotel_id=o.anex_hotel_id'
            . ' WHERE c.anex_hotel_id IS NULL'
            . ' ORDER BY (m.anex_hotel_id IS NULL) DESC,o.search_count DESC,o.last_seen_utc DESC,o.anex_hotel_id LIMIT 50001');
        $newIds = anytour_anex_content_choose($observed->fetchAll(PDO::FETCH_ASSOC), array_keys($ids), $plan['limit']);
        $batchIds = $newIds;
        $reserve = $pdo->prepare('INSERT INTO anex_hotel_content '
            . '(anex_hotel_id,batch_key,status,source_sha,reserved_at_utc) VALUES (?,?,\'in_flight\',?,UTC_TIMESTAMP())');
        $pdo->beginTransaction();
        $reserveBatch = $pdo->prepare("INSERT INTO anex_hotel_content_requests
            (batch_key,method,status,source_sha,ids_json,reserved_at_utc) VALUES (?,'Hotels_DETAILS','in_flight',?,?,UTC_TIMESTAMP())");
        $reserveBatch->execute([$batch, $input['source_sha'], json_encode($batchIds, JSON_THROW_ON_ERROR)]);
        foreach ($newIds as $id) $reserve->execute([$id, $batch, $input['source_sha']]);
        $pdo->commit();
    }
    $phase = 'content';
    $attempts = 0;
    $started = microtime(true);
    $stopReason = null;
    $write = $pdo->prepare('UPDATE anex_hotel_content SET status=?,content_sha256=?,payload_json=?,reason=?,fetched_at_utc=UTC_TIMESTAMP()'
        . ' WHERE anex_hotel_id=? AND batch_key=? AND status=\'in_flight\'');
    foreach ($newIds as $id) {
        if (microtime(true) - $started >= 170) $stopReason = $stopReason ?? 'batch_time_budget';
        if ($stopReason !== null) {
            $write->execute(['deferred', null, null, $stopReason, $id, $batch]);
            continue;
        }
        try {
            $client = new AnyTourAnexClient(ANEX_API_TOKEN);
            $attempts++;
            $data = $client->request('Hotels_DETAILS', ['HOTELINC' => $id]);
            $payload = anytour_anex_hotel_content($data, $id, ANEX_API_TOKEN);
            if (!in_array($payload['status'] ?? '', ['ok','empty'], true)) throw new RuntimeException();
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            if (strlen($encoded) > 1500000 || strpos($encoded, ANEX_API_TOKEN) !== false) throw new RuntimeException();
            $write->execute([$payload['status'] === 'ok' ? 'ready' : 'empty', $payload['source_sha256'], $encoded, null, $id, $batch]);
        } catch (Throwable $ignored) {
            $write->execute(['source_error', null, null, 'content_unavailable', $id, $batch]);
            $diagnostic = isset($client) ? $client->lastRequestDiagnostics() : [];
            if (($diagnostic['http_status'] ?? 0) === 429) $stopReason = 'supplier_rate_limit';
        }
    }
    if (!$saved) {
        $finish = $pdo->prepare("UPDATE anex_hotel_content_requests SET status='completed',report_json=?,finished_at_utc=UTC_TIMESTAMP()
            WHERE batch_key=? AND method='Hotels_DETAILS' AND status='in_flight'");
        $finish->execute([json_encode(['selected' => count($newIds), 'supplier_requests' => $attempts, 'stop_reason' => $stopReason], JSON_THROW_ON_ERROR), $batch]);
    }
    $select->execute([$batch]);
    $items = [];
    foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = ['anex_hotel_id' => (int)$row['anex_hotel_id'], 'status' => $row['status'],
            'source_sha' => $row['source_sha'], 'content_sha256' => $row['content_sha256'],
            'fetched_at_utc' => $row['fetched_at_utc'], 'reason' => $row['reason'],
            'payload' => $row['payload_json'] === null ? null : json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR)];
    }
    if (count($items) > $plan['limit']) throw new RuntimeException();
    $result = ['status' => 'ok', 'scope' => 'preview', 'batch_key' => $batch,
        'supplier_requests' => $attempts, 'cached' => (bool)$saved, 'reserved_ids' => $batchIds,
        'batch_status' => $saved ? $saved['status'] : 'completed', 'rows' => $items];
} catch (Throwable $ignored) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $result = ['status' => 'source_error', 'reason' => 'content_unavailable', 'phase' => $phase];
} finally {
    if ($locked && isset($pdo)) {
        try { $pdo->query("SELECT RELEASE_LOCK('anytour_anex_hotel_content')"); } catch (Throwable $ignored) {}
    }
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
