<?php
declare(strict_types=1);

const MATCH_TV_IDENTITY_BACKFILL_OP = 'hotel-match-tourvisor-identity-backfill-1971-20260917-v1';

function match_tv_identity_backfill_fingerprint(int $hotelId, int $operatorId): string
{
    return hash('sha256', 'tourvisor|' . $hotelId . '|' . $operatorId);
}

function match_tv_identity_backfill_sql(): string
{
    return <<<'SQL'
INSERT INTO tour_operator_identity_observations (
    fingerprint,first_seen_at,last_seen_at,observation_count,source,search_id,country_id,region_id,subregion_id,
    hotel_id,hotel_name,region_name,subregion_name,latitude,longitude,operator_id,operator_name,tour_id,
    operator_link,operator_link_host,operator_link_path,operator_link_query,native_id_type,native_id_value,native_id_conflict
)
SELECT
    SHA2(CONCAT('tourvisor|',p.hotel_id,'|',p.operator_id),256),
    p.first_seen_at,p.last_seen_at,p.observation_count,'historical_backfill',NULL,
    c.country_id,c.region_id,c.subregion_id,c.id,c.name,c.region_name,c.subregion_name,c.latitude,c.longitude,
    p.operator_id,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0
FROM (
    SELECT hotel_id,operator_id,MIN(observed_at) first_seen_at,MAX(observed_at) last_seen_at,
           GREATEST(1,COUNT(DISTINCT search_id)) observation_count
    FROM tour_price_observations
    WHERE operator_id IS NOT NULL AND operator_id > 0
    GROUP BY hotel_id,operator_id
) p
JOIN catalog_hotels c ON c.id=p.hotel_id
ON DUPLICATE KEY UPDATE
    first_seen_at=LEAST(first_seen_at,VALUES(first_seen_at)),
    last_seen_at=GREATEST(last_seen_at,VALUES(last_seen_at)),
    observation_count=GREATEST(observation_count,VALUES(observation_count)),
    country_id=VALUES(country_id),
    region_id=COALESCE(VALUES(region_id),region_id),
    subregion_id=COALESCE(VALUES(subregion_id),subregion_id),
    hotel_name=COALESCE(VALUES(hotel_name),hotel_name),
    region_name=COALESCE(VALUES(region_name),region_name),
    subregion_name=COALESCE(VALUES(subregion_name),subregion_name),
    latitude=COALESCE(VALUES(latitude),latitude),
    longitude=COALESCE(VALUES(longitude),longitude)
SQL;
}

function match_tv_identity_backfill_expected_sql(): string
{
    return "SELECT COUNT(*) FROM (SELECT p.hotel_id,p.operator_id FROM tour_price_observations p JOIN catalog_hotels c ON c.id=p.hotel_id WHERE p.operator_id IS NOT NULL AND p.operator_id>0 GROUP BY p.hotel_id,p.operator_id) x";
}

function match_tv_identity_backfill_self_test(): void
{
    $a = match_tv_identity_backfill_fingerprint(21477, 25);
    if ($a !== match_tv_identity_backfill_fingerprint(21477, 25)) throw new RuntimeException('unstable_fingerprint');
    if ($a === match_tv_identity_backfill_fingerprint(21477, 43)) throw new RuntimeException('operator_not_separated');
    $sql = match_tv_identity_backfill_sql();
    foreach (["SHA2(CONCAT('tourvisor|'", 'tour_price_observations', 'catalog_hotels', 'ON DUPLICATE KEY UPDATE', 'historical_backfill'] as $needle) {
        if (!str_contains($sql, $needle)) throw new RuntimeException('sql_contract_' . $needle);
    }
    if (preg_match('/https?:\/\//i', $sql)) throw new RuntimeException('network_not_allowed');
    if (!str_contains(match_tv_identity_backfill_expected_sql(), 'GROUP BY p.hotel_id,p.operator_id')) throw new RuntimeException('expected_pair_contract');
    echo "hotel_match_tourvisor_identity_backfill_v1 self-test: PASS\n";
}

function match_tv_identity_backfill_write_json(string $path, array $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) throw new RuntimeException('write_failed');
    return hash('sha256', $json);
}

function match_tv_identity_backfill_main(): void
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('cli_only');
    $sourceSha = (string)getenv('MATCH_SOURCE_SHA');
    if ((string)getenv('MATCH_OPERATION_ID') !== MATCH_TV_IDENTITY_BACKFILL_OP || !preg_match('/^[a-f0-9]{40}$/D', $sourceSha)) {
        throw new RuntimeException('operation_guard');
    }
    $dir = (string)getenv('HOME') . '/.anytoour-match/operations/' . MATCH_TV_IDENTITY_BACKFILL_OP;
    $reservation = json_decode((string)file_get_contents($dir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($reservation['operation_id'] ?? '') !== MATCH_TV_IDENTITY_BACKFILL_OP || ($reservation['source_sha'] ?? '') !== $sourceSha || ($reservation['state'] ?? '') !== 'reserved_before_db_access') {
        throw new RuntimeException('reservation_guard');
    }

    $root = realpath(getcwd());
    if (!is_string($root) || basename($root) !== 'anytoour.ru') throw new RuntimeException('root_guard');
    require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $required = ['fingerprint','first_seen_at','last_seen_at','observation_count','hotel_id','operator_id','operator_link','native_id_type','native_id_value','native_id_conflict'];
    $columns = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_operator_identity_observations'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($required as $column) if (!in_array($column, $columns, true)) throw new RuntimeException('schema_v2_missing_' . $column);
    $uniqueIndex = (int)$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_operator_identity_observations' AND INDEX_NAME='uq_operator_identity_hotel_operator' AND NON_UNIQUE=0")->fetchColumn();
    if ($uniqueIndex < 1) throw new RuntimeException('schema_v2_unique_pair_missing');

    $before = (int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations")->fetchColumn();
    $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $db->beginTransaction();
    try {
        // Seal the expected pair set in the same snapshot used by INSERT...SELECT.
        $expected = (int)$db->query(match_tv_identity_backfill_expected_sql())->fetchColumn();
        $affected = $db->exec(match_tv_identity_backfill_sql());
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    $after = (int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations")->fetchColumn();
    $covered = (int)$db->query("SELECT COUNT(*) FROM (SELECT p.hotel_id,p.operator_id FROM tour_price_observations p JOIN catalog_hotels c ON c.id=p.hotel_id JOIN tour_operator_identity_observations i ON i.fingerprint=SHA2(CONCAT('tourvisor|',p.hotel_id,'|',p.operator_id),256) WHERE p.operator_id IS NOT NULL AND p.operator_id>0 GROUP BY p.hotel_id,p.operator_id) x")->fetchColumn();
    $duplicates = (int)$db->query("SELECT COUNT(*) FROM (SELECT hotel_id,operator_id FROM tour_operator_identity_observations GROUP BY hotel_id,operator_id HAVING COUNT(*)>1) x")->fetchColumn();
    $linkless = (int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations WHERE operator_link IS NULL")->fetchColumn();
    $native = (int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations WHERE native_id_value IS NOT NULL")->fetchColumn();
    if ($covered < $expected) throw new RuntimeException('post_commit_coverage_mismatch');
    if ($duplicates !== 0) throw new RuntimeException('post_commit_duplicate_pairs');

    $result = [
        'operation_id'=>MATCH_TV_IDENTITY_BACKFILL_OP,'source_sha'=>$sourceSha,'state'=>'completed',
        'expected_hotel_operator_pairs'=>$expected,'rows_before'=>$before,'rows_after'=>$after,
        'sql_affected_rows'=>$affected,'covered_pairs_readback'=>$covered,'duplicate_pair_groups'=>$duplicates,
        'linkless_rows'=>$linkless,'native_enriched_rows'=>$native,
        'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'mapping_writes'=>0,'read_at_utc'=>gmdate('c'),
    ];
    $hash = match_tv_identity_backfill_write_json($dir . '/result.json', $result);
    match_tv_identity_backfill_write_json($dir . '/receipt.json', [
        'operation_id'=>MATCH_TV_IDENTITY_BACKFILL_OP,'source_sha'=>$sourceSha,'state'=>'completed','result_sha256'=>$hash,
        'readback_verified'=>true,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'mapping_writes'=>0,'no_replay'=>true,
    ]);
    echo json_encode($result + ['result_sha256'=>$hash], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
}

if (in_array('--self-test', $argv ?? [], true)) {
    match_tv_identity_backfill_self_test();
    exit(0);
}
match_tv_identity_backfill_main();
