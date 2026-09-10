<?php
// Ephemeral preview mapping importer; catalog, manual decisions and staging stay untouched.
error_reporting(0);
ob_start();
$pdo = null;
$transaction = false;
$result = array('status' => 'mapping_import_failed');

function anex_mapping_json($value) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_UNESCAPED_LINE_TERMINATORS')) $flags |= JSON_UNESCAPED_LINE_TERMINATORS;
    $encoded = json_encode($value, $flags);
    if (!is_string($encoded)) throw new RuntimeException();
    return $encoded;
}

function anex_mapping_digest($value) {
    return is_string($value) && preg_match('/^[0-9a-f]{64}$/D', $value);
}

function anex_mapping_id($value) {
    return is_int($value) && $value > 0 && $value <= 2147483647;
}

function anex_mapping_message() {
    $line = fgets(STDIN, 16385);
    if ($line === false) return null;
    if (strlen($line) > 16384 || substr($line, -1) !== "\n") throw new RuntimeException();
    $message = json_decode($line, true);
    if (!is_array($message)) throw new RuntimeException();
    return $message;
}

function anex_mapping_preservation($pdo, $ids) {
    $skip = array_fill_keys($ids, true);
    $result = ['staging_total' => (int)$pdo->query('SELECT COUNT(*) FROM anex_hotels')->fetchColumn()];
    foreach (['anex_hotel_search_mappings', 'anex_hotel_decisions'] as $table) {
        $hash = hash_init('sha256'); $count = 0;
        $statement = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY anex_hotel_id');
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($table === 'anex_hotel_search_mappings' && isset($skip[(int)$row['anex_hotel_id']])) continue;
            hash_update($hash, json_encode($row) . "\n"); $count++;
        }
        $result[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
    }
    return $result;
}

try {
    $policy = 'owner_exact_and_strong_20260908';
    $meta = anex_mapping_message();
    $appendOnly = ($meta['append_only'] ?? false) === true;
    if (!is_array($meta) || ($meta['type'] ?? '') !== 'meta'
        || ($meta['protocol_version'] ?? null) !== 1 || ($meta['schema_version'] ?? null) !== 1
        || ($meta['scope'] ?? '') !== 'preview' || ($meta['approval_policy'] ?? '') !== $policy
        || !anex_mapping_digest($meta['mapping_digest'] ?? null)
        || !anex_mapping_digest($meta['rows_digest'] ?? null)
        || !is_array($meta['sources'] ?? null) || count($meta['sources']) !== ($appendOnly ? 3 : 2)
        || ($appendOnly && !anex_mapping_digest($meta['sources']['gap_sha256'] ?? null))
        || !anex_mapping_digest($meta['sources']['catalog_sha256'] ?? null)
        || !anex_mapping_digest($meta['sources']['geo_sha256'] ?? null)
        || !is_array($meta['counts'] ?? null) || count($meta['counts']) !== 4) throw new RuntimeException();
    foreach (array('exact', 'strong', 'total', 'unique_catalog_hotels') as $field) {
        if (!is_int($meta['counts'][$field] ?? null) || $meta['counts'][$field] < 0) throw new RuntimeException();
    }
    if ($meta['counts']['total'] < 1 || $meta['counts']['total'] > 100000) throw new RuntimeException();
    $linkReview = $appendOnly && ($meta['sources']['gap_sha256'] ?? '') ===
        '324d25b3d08fd448a54fa2fd7dc2da6882f8858ea874b6820fc24f62b78cfa02';

    // Validate the complete protocol before opening the DB or creating a table.
    $rows = array();
    $targets = array();
    $counts = array('exact' => 0, 'strong' => 0, 'total' => 0, 'unique_catalog_hotels' => 0);
    $rowHash = hash_init('sha256');
    $committed = false;
    $previousId = 0;
    while (($message = anex_mapping_message()) !== null) {
        if (($message['type'] ?? '') === 'commit') {
            if (count($message) !== 3 || ($message['mapping_digest'] ?? '') !== $meta['mapping_digest']
                || ($message['rows_digest'] ?? '') !== $meta['rows_digest']) throw new RuntimeException();
            $committed = true;
            break;
        }
        if (($message['type'] ?? '') !== 'row' || count($message) !== 2
            || !is_array($message['row'] ?? null)) throw new RuntimeException();
        $row = $message['row'];
        $keys = array_keys($row);
        sort($keys);
        if ($keys !== array('anex_hotel_id', 'catalog_hotel_id', 'match_class', 'reason', 'source_row_digest')
            || !anex_mapping_id($row['anex_hotel_id']) || !anex_mapping_id($row['catalog_hotel_id'])
            || $row['anex_hotel_id'] <= $previousId
            || !in_array($row['match_class'], array('exact', 'strong_candidate'), true)
            || !is_string($row['reason']) || strlen($row['reason']) < 1 || strlen($row['reason']) > 512
            || !preg_match('//u', $row['reason']) || !anex_mapping_digest($row['source_row_digest'])) throw new RuntimeException();
        $previousId = $row['anex_hotel_id'];
        ksort($row);
        hash_update($rowHash, anex_mapping_json($row) . "\n");
        $rows[$row['anex_hotel_id']] = $row;
        $targets[$row['catalog_hotel_id']] = true;
        $counts[$row['match_class'] === 'exact' ? 'exact' : 'strong']++;
        $counts['total']++;
        if ($counts['total'] > $meta['counts']['total']) throw new RuntimeException();
    }
    $counts['unique_catalog_hotels'] = count($targets);
    if (!$committed || fgets(STDIN) !== false || !hash_equals($meta['rows_digest'], hash_final($rowHash))) throw new RuntimeException();
    foreach ($counts as $key => $value) if ($meta['counts'][$key] !== $value) throw new RuntimeException();
    if ($linkReview && (array_map(static function ($row) { return $row['catalog_hotel_id']; }, $rows)
            !== [8121=>6319,16275=>1326,23775=>17568,26688=>55648] || $counts['strong'] !== 4)) {
        throw new RuntimeException('link_review_scope_changed');
    }

    $root = realpath(getcwd());
    if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException();
    $helper = false;
    foreach (array('data/db-v1.php', 'v2/data/db-v1.php') as $relative) {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($candidate !== false && is_file($candidate) && strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0) {
            $helper = $candidate;
            break;
        }
    }
    if ($helper === false) throw new RuntimeException();
    require_once $helper;
    if (!function_exists('v2_data_db')) throw new RuntimeException();
    $pdo = v2_data_db();
    if (!($pdo instanceof PDO)) throw new RuntimeException();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // MySQL DDL commits implicitly; the four-link path requires the existing schema.
    if (!$linkReview) $pdo->exec("CREATE TABLE IF NOT EXISTS anex_hotel_search_mappings (
        anex_hotel_id INT UNSIGNED NOT NULL,
        catalog_hotel_id INT UNSIGNED NOT NULL,
        match_class VARCHAR(32) CHARACTER SET ascii NOT NULL,
        scope VARCHAR(16) CHARACTER SET ascii NOT NULL,
        approval_policy VARCHAR(64) CHARACTER SET ascii NOT NULL,
        source_row_digest CHAR(64) CHARACTER SET ascii NOT NULL,
        mapping_digest CHAR(64) CHARACTER SET ascii NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (anex_hotel_id), KEY idx_anex_search_catalog (catalog_hotel_id),
        KEY idx_anex_search_enabled (scope,approval_policy,enabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $engine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anex_hotel_search_mappings'")->fetchColumn();
    if (strtoupper((string)$engine) !== 'INNODB' || !$pdo->beginTransaction()) throw new RuntimeException();
    $transaction = true;
    $tables = $pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()"
        . " AND TABLE_NAME IN ('anex_review_pair_exclusions','anex_search_hotel_observations')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $reviewEnabled = array_key_exists('anex_review_pair_exclusions', $tables);
    if ($linkReview && !$reviewEnabled) throw new RuntimeException('link_review_schema_required');
    try {
        // information_schema can hide an inaccessible table. Probe permission
        // directly, without reading rows; only genuine absence is optional.
        $probe = $pdo->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE 1=0');
        if ($probe === false || !$reviewEnabled) throw new RuntimeException('review_schema_unavailable');
    } catch (PDOException $error) {
        $info = $error->errorInfo ?? array();
        if ($reviewEnabled || ($info[0] ?? null) !== '42S02' || (int)($info[1] ?? 0) !== 1146) throw $error;
    }
    if ($reviewEnabled) {
        if (strtoupper((string)$tables['anex_review_pair_exclusions']) !== 'INNODB'
            || strtoupper((string)($tables['anex_search_hotel_observations'] ?? '')) !== 'INNODB') throw new RuntimeException();
        // Same mutex and first lock as AnexReviewService::decide. A rejection
        // committed while this importer waits must be seen by the locking read.
        foreach (array_chunk(array_keys($rows), 250) as $ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $statement = $pdo->prepare('SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE anex_hotel_id IN (' . $marks . ') ORDER BY anex_hotel_id FOR UPDATE');
            $statement->execute($ids);
            $statement->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    $preservation = $appendOnly ? anex_mapping_preservation($pdo, array_keys($rows)) : null;
    if ($appendOnly && $preservation['staging_total'] !== 8362) throw new RuntimeException();

    // Bound every ID list. Lock current catalog targets and overrides before any mapping writes.
    foreach (array_chunk(array_keys($targets), 250) as $ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare('SELECT id FROM catalog_hotels WHERE id IN (' . $marks . ')'
            . ($linkReview ? ' AND country_id=4 AND is_active=1' : '') . ' FOR UPDATE');
        $statement->execute($ids);
        $found = $statement->fetchAll(PDO::FETCH_COLUMN);
        if (count(array_unique(array_map('intval', $found))) !== count($ids)) throw new RuntimeException();
    }
    $manual = array();
    $existing = array();
    $exclusions = array();
    foreach (array_chunk(array_keys($rows), 250) as $ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare('SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN (' . $marks . ') FOR UPDATE');
        $statement->execute($ids);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) $manual[(int)$item['anex_hotel_id']] = $item;
        $statement = $pdo->prepare('SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN (' . $marks . ') FOR UPDATE');
        $statement->execute($ids);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
            if ($item['scope'] !== 'preview' || $item['approval_policy'] !== $policy) throw new RuntimeException();
            $existing[(int)$item['anex_hotel_id']] = $item;
        }
        if ($reviewEnabled) {
            $statement = $pdo->prepare('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN (' . $marks . ') ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE');
            $statement->execute($ids);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $exclusions[(int)$item['anex_hotel_id']][(int)$item['catalog_hotel_id']] = true;
            }
        }
    }
    $insert = $pdo->prepare('INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,?,?,?,?,?,1)');
    $update = $pdo->prepare('UPDATE anex_hotel_search_mappings SET catalog_hotel_id=?,match_class=?,source_row_digest=?,mapping_digest=?,enabled=1 WHERE anex_hotel_id=? AND scope=? AND approval_policy=?');
    $disable = $pdo->prepare('UPDATE anex_hotel_search_mappings SET enabled=0 WHERE anex_hotel_id=? AND scope=? AND approval_policy=? AND enabled<>0');
    $stats = array('inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped_manual' => 0,
        'inactivated_manual' => 0, 'manual_accepted_count' => 0, 'manual_conflicts' => 0, 'skipped_pair_excluded' => 0);
    foreach ($rows as $id => $row) {
        $old = $existing[$id] ?? null;
        if (isset($exclusions[$id][$row['catalog_hotel_id']])) {
            $stats['skipped_pair_excluded']++;
            continue; // Never choose another candidate or overwrite a previous mapping here.
        }
        if (isset($manual[$id])) {
            $stats['skipped_manual']++;
            $decision = $manual[$id];
            if ($decision['decision_status'] === 'accepted') $stats['manual_accepted_count']++;
            if ($decision['decision_status'] !== 'accepted' || (int)$decision['catalog_hotel_id'] !== $row['catalog_hotel_id']) $stats['manual_conflicts']++;
            if (!$appendOnly && $old !== null && (int)$old['enabled'] !== 0) {
                $disable->execute(array($id, 'preview', $policy));
                $stats['inactivated_manual']++;
            }
            continue;
        }
        if ($old === null) {
            $insert->execute(array($id, $row['catalog_hotel_id'], $row['match_class'], 'preview', $policy,
                $row['source_row_digest'], $meta['mapping_digest']));
            $stats['inserted']++;
        } elseif ((int)$old['catalog_hotel_id'] === $row['catalog_hotel_id'] && $old['match_class'] === $row['match_class']
            && $old['source_row_digest'] === $row['source_row_digest'] && ($appendOnly || $old['mapping_digest'] === $meta['mapping_digest'])
            && (int)$old['enabled'] === 1) {
            $stats['unchanged']++;
        } else {
            if ($appendOnly) throw new RuntimeException('existing mapping conflict');
            $update->execute(array($row['catalog_hotel_id'], $row['match_class'], $row['source_row_digest'],
                $meta['mapping_digest'], $id, 'preview', $policy));
            $stats['updated']++;
        }
    }
    // Count effective policy links separately from all accepted manual overrides.
    $effective = $pdo->prepare("SELECT COUNT(*) AS total_enabled,COUNT(DISTINCT m.catalog_hotel_id) AS unique_catalog_hotels,
        COALESCE(SUM(m.match_class='exact'),0) AS exact,COALESCE(SUM(m.match_class='strong_candidate'),0) AS strong
        FROM anex_hotel_search_mappings m INNER JOIN catalog_hotels c ON c.id=m.catalog_hotel_id
        LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id
        WHERE m.scope=? AND m.approval_policy=? AND m.enabled=1 AND d.anex_hotel_id IS NULL"
        . ($reviewEnabled ? ' AND NOT EXISTS (SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)' : ''));
    $effective->execute(array('preview', $policy));
    $enabled = $effective->fetch(PDO::FETCH_ASSOC);
    if (!is_array($enabled)) throw new RuntimeException();
    $manualAccepted = $pdo->query("SELECT COUNT(*) FROM anex_hotel_decisions d INNER JOIN catalog_hotels c ON c.id=d.catalog_hotel_id WHERE d.decision_status='accepted'"
        . ($reviewEnabled ? ' AND NOT EXISTS (SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)' : ''))->fetchColumn();
    if ($manualAccepted === false) throw new RuntimeException();
    if ($appendOnly && $preservation !== anex_mapping_preservation($pdo, array_keys($rows))) throw new RuntimeException();
    $pdo->commit();
    $transaction = false;
    $linkReadback = [];
    if ($linkReview) {
        // Post-COMMIT readback. A lost/changed result remains unknown, never replayed.
        $readback = $pdo->prepare('SELECT catalog_hotel_id,source_row_digest,match_class,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND scope=? AND approval_policy=?');
        foreach ($rows as $id => $row) {
            $status = isset($exclusions[$id][$row['catalog_hotel_id']]) ? 'skipped_pair_excluded'
                : (isset($manual[$id]) ? 'skipped_manual' : 'verified_policy_mapping');
            if ($status === 'verified_policy_mapping') {
                $readback->execute([$id, 'preview', $policy]);
                $actual = $readback->fetch(PDO::FETCH_ASSOC);
                if (!$actual || (int)$actual['catalog_hotel_id'] !== $row['catalog_hotel_id']
                        || (int)$actual['enabled'] !== 1 || $actual['match_class'] !== 'strong_candidate'
                        || $actual['source_row_digest'] !== $row['source_row_digest']) throw new RuntimeException();
            }
            $linkReadback[] = ['anex_hotel_id'=>$id, 'catalog_hotel_id'=>$row['catalog_hotel_id'], 'status'=>$status];
        }
    }
    $result = array_merge(array('status' => $stats['inserted'] + $stats['updated'] + $stats['inactivated_manual'] === 0 ? 'already_imported' : 'imported',
        'scope' => 'preview', 'approval_policy' => $policy, 'mapping_digest' => $meta['mapping_digest'],
        'append_only' => $appendOnly, 'preservation' => $preservation,
        'input_count' => count($rows), 'exact' => $counts['exact'], 'strong' => $counts['strong'],
        'total_enabled' => (int)$enabled['total_enabled'], 'enabled_exact' => (int)$enabled['exact'],
        'enabled_strong' => (int)$enabled['strong'], 'enabled_unique_catalog_hotels' => (int)$enabled['unique_catalog_hotels'],
        'effective_manual_accepted' => (int)$manualAccepted,
        'effective_mapped_count' => (int)$enabled['total_enabled'] + (int)$manualAccepted), $stats);
    if ($linkReview) $result += ['readback_verified'=>true, 'catalog_country_guard'=>4, 'link_readback'=>$linkReadback];
} catch (Throwable $ignored) {
    if ($pdo instanceof PDO && $transaction) {
        try { $pdo->rollBack(); } catch (Throwable $rollbackIgnored) {}
    }
}
while (ob_get_level() > 0) ob_end_clean();
echo anex_mapping_json($result), "\n";
exit($result['status'] === 'mapping_import_failed' ? 1 : 0);
