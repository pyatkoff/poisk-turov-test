<?php
// The owner explicitly accepted these nine pairs. Insert-only manual decisions.
error_reporting(0);
ob_start();
$pdo = null;
$result = ['status' => 'owner_decisions_failed'];
$phase = 'approval';

function anex_owner_preservation(PDO $pdo, array $approved): array {
    $out = ['staging_total' => (int)$pdo->query('SELECT COUNT(*) FROM anex_hotels')->fetchColumn()];
    foreach (['anex_hotel_search_mappings', 'anex_hotel_decisions'] as $table) {
        $hash = hash_init('sha256'); $count = 0;
        $statement = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY anex_hotel_id');
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($table === 'anex_hotel_decisions' && isset($approved[(int)$row['anex_hotel_id']])) continue;
            hash_update($hash, json_encode($row) . "\n"); $count++;
        }
        $out[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
    }
    return $out;
}

try {
    $approved = [28892=>17449,28941=>17428,28950=>28738,29292=>17325,30305=>17444,
                 30474=>67478,31788=>71458,32652=>28652,32739=>28697];
    $approvalId = 'owner_nine_capped_pairs_20260908';
    $manifestSha = '19fc0e6b0250298a6edb095a267bb28da43f750342b22c8a451339ebcab939a1';
    $raw = file_get_contents('php://stdin', false, null, 0, 32769);
    if (!is_string($raw) || strlen($raw) > 32768) throw new RuntimeException();
    $input = json_decode($raw, true);
    if (($input['scope'] ?? '') !== 'preview' || ($input['approval_id'] ?? '') !== $approvalId
        || ($input['manifest_sha256'] ?? '') !== $manifestSha
        || ($input['owner_instruction'] ?? '') !== 'Да, соединяй их и дальше продолжай'
        || !is_array($input['rows'] ?? null) || count($input['rows']) !== 9) throw new RuntimeException();
    $rows = [];
    foreach ($input['rows'] as $row) {
        $id = $row['anex_hotel_id'] ?? null; $target = $row['catalog_hotel_id'] ?? null;
        if (!is_int($id) || !is_int($target) || ($approved[$id] ?? null) !== $target || isset($rows[$id])
            || ($row['country'] ?? '') !== 'Турция'
            || !preg_match('/^[a-f0-9]{64}$/D', $row['evidence_row_sha256'] ?? '')) throw new RuntimeException();
        $rows[$id] = $row;
    }
    ksort($rows);
    $phase = 'database';
    $root = realpath(getcwd());
    if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException();
    $helper = false;
    foreach (['data/db-v1.php', 'v2/data/db-v1.php'] as $relative) {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($candidate !== false && is_file($candidate) && strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0) {
            $helper = $candidate; break;
        }
    }
    if ($helper === false) throw new RuntimeException();
    require_once $helper;
    $pdo = v2_data_db();
    if (!($pdo instanceof PDO)) throw new RuntimeException();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();
    $phase = 'catalog_targets';
    $ids = array_keys($rows); $marks = implode(',', array_fill(0, 9, '?'));
    $statement = $pdo->prepare('SELECT id,country_name,is_active FROM catalog_hotels WHERE id IN (' . $marks . ') LOCK IN SHARE MODE');
    $statement->execute(array_values($approved));
    $targets = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $target) $targets[(int)$target['id']] = $target;
    foreach ($rows as $row) {
        $target = $targets[$row['catalog_hotel_id']] ?? null;
        if ($target === null || (int)$target['is_active'] !== 1 || trim($target['country_name']) !== $row['country']) throw new RuntimeException();
    }
    $phase = 'existing_decisions';
    $statement = $pdo->prepare('SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN (' . $marks . ') FOR UPDATE');
    $statement->execute($ids);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $mapping) {
        if ((int)$mapping['catalog_hotel_id'] !== $approved[(int)$mapping['anex_hotel_id']] || (int)$mapping['enabled'] !== 1) throw new RuntimeException();
    }
    $statement = $pdo->prepare('SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN (' . $marks . ') FOR UPDATE');
    $statement->execute($ids);
    $existing = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $decision) {
        $id = (int)$decision['anex_hotel_id'];
        if ($decision['decision_status'] !== 'accepted' || (int)$decision['catalog_hotel_id'] !== $approved[$id]) throw new RuntimeException();
        $existing[$id] = $decision;
    }
    $before = anex_owner_preservation($pdo, $approved);
    if ($before['staging_total'] !== 8362) throw new RuntimeException();
    $insert = $pdo->prepare("INSERT INTO anex_hotel_decisions (anex_hotel_id,decision_status,catalog_hotel_id,decided_by,decision_note,decided_at) VALUES (?,'accepted',?,?,?,UTC_TIMESTAMP())");
    $inserted = 0;
    $phase = 'append_and_verify';
    foreach ($rows as $id => $row) {
        if (isset($existing[$id])) continue;
        $note = json_encode(['approval_id'=>$approvalId,'manifest_sha256'=>$manifestSha,
            'owner_instruction'=>$input['owner_instruction'],'source_artifact_id'=>10072776915,
            'evidence_row_sha256'=>$row['evidence_row_sha256'],
            'original_automated_status'=>'review','original_reason'=>'candidate_limit_reached'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $insert->execute([$id,$row['catalog_hotel_id'],'owner:explicit_chat_20260908',$note]);
        $inserted++;
    }
    $statement->execute($ids);
    $verified = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($verified) !== 9) throw new RuntimeException();
    foreach ($verified as $decision) {
        $id = (int)$decision['anex_hotel_id'];
        if ($decision['decision_status'] !== 'accepted' || (int)$decision['catalog_hotel_id'] !== $approved[$id]
            || (isset($existing[$id]) && $existing[$id] !== $decision)) throw new RuntimeException();
    }
    $after = anex_owner_preservation($pdo, $approved);
    if ($before !== $after) throw new RuntimeException();
    $pdo->commit();
    $phase = 'committed_registry_readback';
    // Read committed decisions through the same registry used by the isolated search.
    $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $readback = [];
    foreach ($approved as $id => $target) {
        if ($registry->resolve('anex_online', $id, 'preview') !== $target
            || $registry->resolve('anex_online', $id, 'production') !== null) throw new RuntimeException();
        $readback[] = ['anex_hotel_id'=>$id,'catalog_hotel_id'=>$target,'decision_status'=>'accepted'];
    }
    $result = ['status'=>$inserted ? 'imported' : 'already_imported','approval_id'=>$approvalId,
               'inserted'=>$inserted,'approved_count'=>9,'unchanged'=>9-$inserted,
               'preservation_before'=>$before,'preservation_after'=>$after,'rows'=>$readback,
               'effective_mapped_count'=>$registry->count(),'readback_verified'=>true];
} catch (Throwable $ignored) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $result['phase'] = $phase;
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
exit($result['status'] === 'owner_decisions_failed' ? 1 : 0);
