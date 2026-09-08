<?php
declare(strict_types=1);
// One hotel operation. The deployed client owns token-wide pacing/cooldown.
error_reporting(0);
ob_start();
$result = ['status' => 'source_error', 'reason' => 'details_unavailable'];
try {
    $root = realpath((string)getenv('HOME') . '/www/anytoour.ru');
    if (!$root || realpath((string)getcwd()) !== $root) throw new RuntimeException();
    $input = json_decode((string)file_get_contents('php://stdin', false, null, 0, 1025), true);
    $id = $input['id'] ?? null;
    $snapshot = ($input['mode'] ?? null) === 'snapshot';
    if (!$snapshot && (!is_int($id) || $id < 1 || $id > 2147483647)) throw new RuntimeException();
    $preview = $root . '/_preview/search3-anex-candidate';
    require (string)getenv('HOME') . '/.anytoour-anex/search3-preview.php';
    require_once $preview . '/app/integrations/anex-client.php';
    require_once is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
    $pdo = v2_data_db();
    $pdo->exec('START TRANSACTION READ ONLY');
    if ($snapshot) {
        $result = ['status' => 'ok', 'staging_total' => (int)$pdo->query('SELECT COUNT(*) FROM anex_hotels')->fetchColumn()];
        foreach (['anex_hotel_search_mappings', 'anex_hotel_decisions'] as $table) {
            $hash = hash_init('sha256');
            $count = 0;
            $query = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY anex_hotel_id');
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) { hash_update($hash, json_encode($row) . "\n"); $count++; }
            $result[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
        }
        $pdo->rollBack();
        while (ob_get_level() > 0) ob_end_clean();
        echo json_encode($result), "\n";
        exit;
    }
    $query = $pdo->prepare('SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=? '
        . 'UNION SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND enabled=1');
    $query->execute([$id, $id]);
    $protected = $query->fetchColumn() !== false;
    $pdo->rollBack();
    if ($protected) {
        $result = ['status' => 'protected', 'reason' => 'existing_mapping_or_manual_decision'];
    } else {
        if (!defined('ANYTOUR_ANEX_PREVIEW_ENABLED') || ANYTOUR_ANEX_PREVIEW_ENABLED !== true) throw new RuntimeException();
        $client = new AnyTourAnexClient(ANEX_API_TOKEN);
        $data = $client->request('Hotels_DETAILS', ['HOTELINC' => $id]);
        // Fixed, bounded identity fields only. No descriptions, URLs or arbitrary supplier metadata.
        $safe = [];
        foreach (['id','name','state','region','town','townKey','address','latitude','longitude'] as $field) {
            $value = $data[$field] ?? null;
            if (is_int($value) || is_float($value) || (is_string($value) && strlen($value) <= 1200
                && strpos($value, ANEX_API_TOKEN) === false && !preg_match('/oauth_token|https?:\/\/|[<>]/i', $value))) $safe[$field] = $value;
        }
        $result = ['status' => 'ok', 'details' => $safe];
    }
} catch (Throwable $ignored) {
    if (isset($client)) {
        $diagnostic = $client->lastRequestDiagnostics();
        if (($diagnostic['http_status'] ?? 0) === 429) $result['reason'] = 'rate_limited';
        elseif (isset($diagnostic['supplier_code'])) $result['reason'] = 'supplier_error_' . (int)$diagnostic['supplier_code'];
    }
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
