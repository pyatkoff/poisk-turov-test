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
    // A stable cohort makes code/CI reruns read the same results, never another supplier batch.
    $batch = 'owner_content_pilot_20260909';
    $select = $pdo->prepare('SELECT * FROM anex_hotel_content WHERE batch_key=? ORDER BY anex_hotel_id');
    $select->execute([$batch]);
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);
    $newIds = [];
    if (!$rows) {
        $phase = 'reservation';
        $observed = $pdo->query('SELECT o.anex_hotel_id FROM anex_search_hotel_observations o'
            . ' LEFT JOIN anex_hotel_search_mappings m ON m.anex_hotel_id=o.anex_hotel_id AND m.enabled=1'
            . ' ORDER BY (m.anex_hotel_id IS NULL) DESC,o.search_count DESC,o.last_seen_utc DESC,o.anex_hotel_id LIMIT 50001');
        $count = 0;
        while ($row = $observed->fetch(PDO::FETCH_ASSOC)) {
            if (++$count > 50000) throw new RuntimeException();
            $id = (int)$row['anex_hotel_id'];
            if (isset($ids[$id])) $newIds[$id] = $id;
            if (count($newIds) === 5) break;
        }
        if (!$newIds) throw new RuntimeException();
        $reserve = $pdo->prepare('INSERT INTO anex_hotel_content '
            . '(anex_hotel_id,batch_key,status,source_sha,reserved_at_utc) VALUES (?,?,\'in_flight\',?,UTC_TIMESTAMP())');
        $pdo->beginTransaction();
        foreach ($newIds as $id) $reserve->execute([$id, $batch, $input['source_sha']]);
        $pdo->commit();
    }
    $phase = 'content';
    $attempts = 0;
    $write = $pdo->prepare('UPDATE anex_hotel_content SET status=?,content_sha256=?,payload_json=?,reason=?,fetched_at_utc=UTC_TIMESTAMP()'
        . ' WHERE anex_hotel_id=? AND batch_key=? AND status=\'in_flight\'');
    foreach ($newIds as $id) {
        try {
            $client = new AnyTourAnexClient(ANEX_API_TOKEN);
            $attempts++;
            $data = $client->request('Hotels_DETAILS', ['HOTELINC' => $id]);
            $payload = anytour_anex_hotel_content($data, $id, ANEX_API_TOKEN);
            if (!in_array($payload['status'] ?? '', ['ok','empty'], true)) throw new RuntimeException();
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($encoded) > 1500000 || strpos($encoded, ANEX_API_TOKEN) !== false) throw new RuntimeException();
            $write->execute([$payload['status'] === 'ok' ? 'ready' : 'empty', $payload['source_sha256'], $encoded, null, $id, $batch]);
        } catch (Throwable $ignored) {
            $write->execute(['source_error', null, null, 'content_unavailable', $id, $batch]);
            $diagnostic = isset($client) ? $client->lastRequestDiagnostics() : [];
            if (($diagnostic['http_status'] ?? 0) === 429) break;
        }
    }
    $select->execute([$batch]);
    $items = [];
    foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = ['anex_hotel_id' => (int)$row['anex_hotel_id'], 'status' => $row['status'],
            'source_sha' => $row['source_sha'], 'content_sha256' => $row['content_sha256'],
            'fetched_at_utc' => $row['fetched_at_utc'], 'reason' => $row['reason'],
            'payload' => $row['payload_json'] === null ? null : json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR)];
    }
    if (count($items) > 5) throw new RuntimeException();
    $result = ['status' => 'ok', 'scope' => 'preview', 'batch_key' => $batch,
        'supplier_requests' => $attempts, 'cached' => !$newIds, 'rows' => $items];
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
