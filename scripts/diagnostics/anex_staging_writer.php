<?php
// Ephemeral staging writer. It never changes catalog_hotels or manual decisions.
error_reporting(0);
ob_start();
$pdo = null;
$transaction = false;
$result = array('status' => 'staging_import_failed');

function anex_stage_json($value) {
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) throw new RuntimeException();
    return $encoded;
}

function anex_stage_text($value, $limit) {
    $value = trim((string)$value);
    if (strlen($value) > $limit || !preg_match('//u', $value)) throw new RuntimeException();
    return $value;
}

function anex_stage_number($value) {
    return is_int($value) || is_float($value) ? $value : null;
}

try {
    $first = fgets(STDIN, 16384);
    $meta = is_string($first) ? json_decode($first, true) : null;
    if (!is_array($meta) || ($meta['type'] ?? '') !== 'meta' || ($meta['protocol_version'] ?? 0) !== 1
        || !preg_match('/^[0-9a-f]{64}$/D', (string)($meta['checkpoint_digest'] ?? ''))
        || !is_int($meta['processed_total'] ?? null) || $meta['processed_total'] < 0) {
        throw new RuntimeException();
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

    $ddl = array(
        "CREATE TABLE IF NOT EXISTS anex_hotels (
            anex_hotel_id INT UNSIGNED NOT NULL,
            source_fingerprint CHAR(24) CHARACTER SET ascii NOT NULL,
            xml_name VARCHAR(255) NOT NULL DEFAULT '',
            xml_alternate_name VARCHAR(255) NOT NULL DEFAULT '',
            xml_town_id INT UNSIGNED NULL,
            api_name VARCHAR(255) NOT NULL DEFAULT '',
            api_country VARCHAR(255) NOT NULL DEFAULT '',
            api_region VARCHAR(255) NOT NULL DEFAULT '',
            api_town VARCHAR(255) NOT NULL DEFAULT '',
            api_town_id INT UNSIGNED NULL,
            api_address VARCHAR(1024) NOT NULL DEFAULT '',
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            supplier_record_json MEDIUMTEXT NOT NULL,
            checked_at DATETIME NULL,
            first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (anex_hotel_id), KEY idx_anex_hotels_country (api_country)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS anex_hotel_auto_matches (
            anex_hotel_id INT UNSIGNED NOT NULL,
            row_digest CHAR(64) CHARACTER SET ascii NOT NULL,
            source_fingerprint CHAR(24) CHARACTER SET ascii NOT NULL,
            original_status VARCHAR(32) NOT NULL DEFAULT '',
            automated_status VARCHAR(32) NOT NULL,
            automated_reason VARCHAR(64) NOT NULL DEFAULT '',
            api_xml_relation VARCHAR(32) NOT NULL DEFAULT '',
            suggested_catalog_hotel_id INT UNSIGNED NULL,
            candidate_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            stored_candidate_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            candidate_limit SMALLINT UNSIGNED NULL,
            evidence_json MEDIUMTEXT NOT NULL,
            checked_at DATETIME NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (anex_hotel_id), UNIQUE KEY uq_anex_auto_digest (row_digest),
            KEY idx_anex_auto_status (automated_status), KEY idx_anex_auto_suggestion (suggested_catalog_hotel_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS anex_hotel_candidates (
            anex_hotel_id INT UNSIGNED NOT NULL,
            candidate_rank SMALLINT UNSIGNED NOT NULL,
            catalog_hotel_id INT UNSIGNED NOT NULL,
            score DECIMAL(8,6) NULL,
            name_similarity DECIMAL(8,6) NULL,
            distance_m DECIMAL(12,2) NULL,
            country_match TINYINT(1) NULL,
            address_exact TINYINT(1) NULL,
            candidate_json TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (anex_hotel_id, candidate_rank),
            KEY idx_anex_candidate_catalog (catalog_hotel_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS anex_hotel_decisions (
            anex_hotel_id INT UNSIGNED NOT NULL,
            decision_status VARCHAR(32) NOT NULL,
            catalog_hotel_id INT UNSIGNED NULL,
            decided_by VARCHAR(255) NOT NULL DEFAULT '',
            decision_note TEXT NOT NULL,
            decided_at DATETIME NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (anex_hotel_id), KEY idx_anex_decision_status (decision_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS anex_sync_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            checkpoint_digest CHAR(64) CHARACTER SET ascii NOT NULL,
            checkpoint_schema_version SMALLINT UNSIGNED NOT NULL,
            source_run_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_run_attempt INT UNSIGNED NOT NULL DEFAULT 0,
            processed_total INT UNSIGNED NOT NULL,
            remaining_count INT UNSIGNED NOT NULL,
            strong_candidate_count INT UNSIGNED NOT NULL,
            review_count INT UNSIGNED NOT NULL,
            unmatched_count INT UNSIGNED NOT NULL,
            imported_rows INT UNSIGNED NOT NULL,
            unchanged_rows INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY uq_anex_sync_digest (checkpoint_digest),
            KEY idx_anex_sync_source (source_run_id, source_run_attempt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    foreach ($ddl as $sql) $pdo->exec($sql);

    $seenRun = $pdo->prepare('SELECT id FROM anex_sync_runs WHERE checkpoint_digest=? LIMIT 1');
    $seenRun->execute(array($meta['checkpoint_digest']));
    if ($seenRun->fetchColumn()) {
        while (fgets(STDIN) !== false) {}
        $result = array('status' => 'already_imported', 'processed_total' => $meta['processed_total'],
            'checkpoint_digest' => $meta['checkpoint_digest']);
    } else {
        $existing = array();
        foreach ($pdo->query('SELECT anex_hotel_id,row_digest FROM anex_hotel_auto_matches') as $item) {
            $existing[(int)$item['anex_hotel_id']] = (string)$item['row_digest'];
        }
        $hotelSql = "INSERT INTO anex_hotels
            (anex_hotel_id,source_fingerprint,xml_name,xml_alternate_name,xml_town_id,api_name,api_country,
             api_region,api_town,api_town_id,api_address,latitude,longitude,supplier_record_json,checked_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
             source_fingerprint=VALUES(source_fingerprint),xml_name=VALUES(xml_name),
             xml_alternate_name=VALUES(xml_alternate_name),xml_town_id=VALUES(xml_town_id),api_name=VALUES(api_name),
             api_country=VALUES(api_country),api_region=VALUES(api_region),api_town=VALUES(api_town),
             api_town_id=VALUES(api_town_id),api_address=VALUES(api_address),latitude=VALUES(latitude),
             longitude=VALUES(longitude),supplier_record_json=VALUES(supplier_record_json),checked_at=VALUES(checked_at)";
        $autoSql = "INSERT INTO anex_hotel_auto_matches
            (anex_hotel_id,row_digest,source_fingerprint,original_status,automated_status,automated_reason,
             api_xml_relation,suggested_catalog_hotel_id,candidate_count,stored_candidate_count,candidate_limit,evidence_json,checked_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
             row_digest=VALUES(row_digest),source_fingerprint=VALUES(source_fingerprint),
             original_status=VALUES(original_status),automated_status=VALUES(automated_status),
             automated_reason=VALUES(automated_reason),api_xml_relation=VALUES(api_xml_relation),
             suggested_catalog_hotel_id=VALUES(suggested_catalog_hotel_id),candidate_count=VALUES(candidate_count),
             stored_candidate_count=VALUES(stored_candidate_count),candidate_limit=VALUES(candidate_limit),
             evidence_json=VALUES(evidence_json),checked_at=VALUES(checked_at)";
        $candidateSql = "INSERT INTO anex_hotel_candidates
            (anex_hotel_id,candidate_rank,catalog_hotel_id,score,name_similarity,distance_m,country_match,address_exact,candidate_json)
            VALUES (?,?,?,?,?,?,?,?,?)";
        $hotelStatement = $pdo->prepare($hotelSql);
        $autoStatement = $pdo->prepare($autoSql);
        $deleteCandidates = $pdo->prepare('DELETE FROM anex_hotel_candidates WHERE anex_hotel_id=?');
        $candidateStatement = $pdo->prepare($candidateSql);
        $pdo->beginTransaction();
        $transaction = true;
        $seen = array();
        $counts = array('strong_candidate' => 0, 'review' => 0, 'unmatched' => 0);
        $imported = 0;
        $unchanged = 0;
        $committed = false;
        while (($line = fgets(STDIN, 1048577)) !== false) {
            if (strlen($line) > 1048576) throw new RuntimeException();
            $message = json_decode($line, true);
            if (!is_array($message)) throw new RuntimeException();
            if (($message['type'] ?? '') === 'commit') { $committed = true; break; }
            if (($message['type'] ?? '') !== 'row' || !is_array($message['row'] ?? null)) throw new RuntimeException();
            $row = $message['row'];
            $id = $row['anex_hotel_id'] ?? null;
            $digest = (string)($row['row_digest'] ?? '');
            $status = (string)($row['automated_status'] ?? '');
            if (!is_int($id) || $id <= 0 || isset($seen[$id]) || !preg_match('/^[0-9a-f]{64}$/D', $digest)
                || !array_key_exists($status, $counts)) throw new RuntimeException();
            $seen[$id] = true;
            $counts[$status]++;
            if (($existing[$id] ?? null) === $digest) { $unchanged++; continue; }
            $xml = is_array($row['xml'] ?? null) ? $row['xml'] : array();
            $api = is_array($row['api'] ?? null) ? $row['api'] : array();
            $candidates = is_array($row['candidates'] ?? null) ? $row['candidates'] : array();
            if (count($candidates) > 5 || (int)($row['candidate_count'] ?? -1) < count($candidates)) throw new RuntimeException();
            $checkedAt = anex_stage_text($row['checked_at'] ?? '', 64);
            $checkedAt = $checkedAt === '' ? null : gmdate('Y-m-d H:i:s', strtotime($checkedAt));
            $hotelStatement->execute(array($id, $row['fingerprint'], anex_stage_text($xml['name'] ?? '', 255),
                anex_stage_text($xml['alternate_name'] ?? '', 255), $xml['town_id'] ?? null,
                anex_stage_text($api['name'] ?? '', 255), anex_stage_text($api['country'] ?? '', 255),
                anex_stage_text($api['region'] ?? '', 255), anex_stage_text($api['town'] ?? '', 255),
                $api['town_id'] ?? null, anex_stage_text($api['address'] ?? '', 1024),
                anex_stage_number($api['latitude'] ?? null), anex_stage_number($api['longitude'] ?? null),
                anex_stage_json(array('xml' => $xml, 'api' => $api)), $checkedAt));
            $suggested = count($candidates) ? $candidates[0]['catalog_hotel_id'] : null;
            $autoStatement->execute(array($id, $digest, $row['fingerprint'],
                anex_stage_text($row['original_status'] ?? '', 32), $status,
                anex_stage_text($row['reason'] ?? '', 64), anex_stage_text($row['api_xml_relation'] ?? '', 32),
                $suggested, (int)$row['candidate_count'], count($candidates), $row['candidate_limit'] ?? null,
                anex_stage_json(array('xml' => $xml, 'api' => $api, 'candidates' => $candidates)), $checkedAt));
            $deleteCandidates->execute(array($id));
            foreach ($candidates as $candidate) {
                $candidateStatement->execute(array($id, $candidate['rank'], $candidate['catalog_hotel_id'],
                    anex_stage_number($candidate['score'] ?? null), anex_stage_number($candidate['name_similarity'] ?? null),
                    anex_stage_number($candidate['distance_m'] ?? null),
                    isset($candidate['country_match']) ? (int)$candidate['country_match'] : null,
                    isset($candidate['address_exact']) ? (int)$candidate['address_exact'] : null,
                    anex_stage_json($candidate)));
            }
            $imported++;
        }
        if (!$committed || count($seen) !== $meta['processed_total']
            || $counts['strong_candidate'] !== $meta['strong_candidate_count']
            || $counts['review'] !== $meta['review_count'] || $counts['unmatched'] !== $meta['unmatched_count']) {
            throw new RuntimeException();
        }
        $sync = $pdo->prepare("INSERT INTO anex_sync_runs
            (checkpoint_digest,checkpoint_schema_version,source_run_id,source_run_attempt,processed_total,
             remaining_count,strong_candidate_count,review_count,unmatched_count,imported_rows,unchanged_rows)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $sync->execute(array($meta['checkpoint_digest'], $meta['checkpoint_schema_version'],
            $meta['source_run_id'], $meta['source_run_attempt'], $meta['processed_total'], $meta['remaining'],
            $meta['strong_candidate_count'], $meta['review_count'], $meta['unmatched_count'], $imported, $unchanged));
        $pdo->commit();
        $transaction = false;
        $result = array('status' => 'imported', 'processed_total' => count($seen),
            'imported_rows' => $imported, 'unchanged_rows' => $unchanged,
            'strong_candidate' => $counts['strong_candidate'], 'review' => $counts['review'],
            'unmatched' => $counts['unmatched'], 'remaining' => $meta['remaining'],
            'checkpoint_digest' => $meta['checkpoint_digest']);
    }
} catch (Throwable $ignored) {
    if ($pdo instanceof PDO && $transaction) {
        try { $pdo->rollBack(); } catch (Throwable $rollbackIgnored) {}
    }
}
while (ob_get_level() > 0) ob_end_clean();
echo anex_stage_json($result), "\n";
exit($result['status'] === 'staging_import_failed' ? 1 : 0);
