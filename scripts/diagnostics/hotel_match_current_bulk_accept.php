<?php
declare(strict_types=1);

if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_full_catalog_reconcile.php';
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const MBA_OPERATION = 'hotel-match-current-bulk-accept-1971-20260911-v1';

function mba_require_transactional(PDO $db): void {
    $tables = [
        'catalog_hotels','catalog_hotel_details','hotel_aliases',
        'anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions',
        'anex_hotels','anex_search_hotel_observations',
        'andromeda_hotel_identities','andromeda_search_hotel_observations',
    ];
    $q = $db->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    foreach ($tables as $table) {
        $q->execute([$table]);
        $engine = $q->fetchColumn();
        if ($engine === false) throw new RuntimeException('required_table_missing');
        if (strcasecmp((string)$engine, 'InnoDB') !== 0) throw new RuntimeException('required_transactional_table_missing');
    }
}

function mba_identity_counts(PDO $db): array {
    $out = ['accepted'=>0,'pending'=>0,'conflict'=>0];
    $q = $db->query(
        "SELECT decision_status,COUNT(*) AS n FROM andromeda_hotel_identities " .
        "WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status"
    );
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $status = (string)$row['decision_status'];
        if (array_key_exists($status,$out)) $out[$status] = (int)$row['n'];
    }
    return $out;
}

function mba_anex_evidence(string $operation, array $row): array {
    return [
        'operation_id' => $operation,
        'lane' => 'MATCH',
        'provider' => 'anex',
        'rule' => $row['reason'] ?? null,
        'anex_hotel_id' => (int)$row['external_id'],
        'country_id' => (int)$row['country_id'],
        'source_names' => $row['source_names'] ?? [],
        'source_places' => $row['source_places'] ?? [],
        'search_count' => (int)($row['search_count'] ?? 0),
        'last_seen_utc' => $row['last_seen_utc'] ?? null,
        'guard' => $row['guard'] ?? null,
        'target' => $row['target'] ?? null,
        'server_current' => true,
    ];
}

function mba_andromeda_evidence(string $operation, array $prior, array $row): array {
    return [
        'prior_evidence' => $prior,
        'promotion' => [
            'operation_id' => $operation,
            'lane' => 'MATCH',
            'rule' => $row['reason'] ?? null,
            'country_id' => (int)$row['country_id'],
            'target' => (int)$row['target']['local_hotel_id'],
            'guard' => $row['guard'] ?? null,
            'source_category' => $row['source_category'] ?? null,
            'server_current' => true,
        ],
    ];
}

function mba_accept(PDO $db, string $operation): array {
    if ($operation !== MBA_OPERATION) throw new RuntimeException('operation_scope');
    fc_require_tables($db);
    mba_require_transactional($db);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET SESSION innodb_lock_wait_timeout=20');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

    $before = fc_coverage($db);
    $identityBefore = mba_identity_counts($db);
    $anRows = [];
    $andRows = [];
    $planned = ['anex'=>0,'andromeda'=>0];
    $skipped = [
        'anex_protected'=>0,'anex_not_auto'=>0,'anex_pair_excluded'=>0,
        'andromeda_not_auto'=>0,'andromeda_country_unknown'=>0
    ];
    $writes = 0;
    $committed = false;

    try {
        $db->beginTransaction();

        [$hotels,$names,$strict,$broad,$places,$catalogScope] = mbr_catalog($db);
        $shaCountry = fc_sha_countries($db);

        $manual = array_fill_keys(array_map(
            'intval',
            $db->query('SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)
        ), true);
        $existing = array_fill_keys(array_map(
            'intval',
            $db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)
        ), true);
        $excluded = [];
        foreach ($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']] = true;
        }

        $staging = [];
        foreach ($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $staging[(int)$s['anex_hotel_id']] = $s;
        }
        $observations = $db->query(
            'SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id FOR UPDATE'
        )->fetchAll(PDO::FETCH_ASSOC);

        $insert = $db->prepare(
            "INSERT INTO anex_hotel_search_mappings
             (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled)
             VALUES(?,?,'strong_candidate','preview',?,?,?,1)"
        );
        $mappingDigest = fc_hash([$operation,'current_db_bulk_accept_v1']);
        $seenAnex = [];

        $acceptAnex = static function(array $row) use (&$anRows,&$planned,&$writes,$insert,$mappingDigest,$operation): void {
            $id = (int)$row['external_id'];
            $target = (int)$row['target']['local_hotel_id'];
            $evidence = mba_anex_evidence($operation,$row);
            $digest = fc_hash($evidence);
            $insert->execute([$id,$target,MBR_POLICY,$digest,$mappingDigest]);
            if ($insert->rowCount() !== 1) throw new RuntimeException('anex_insert_not_one');
            $anRows[$id] = ['target'=>$target,'source_row_digest'=>$digest,'reason'=>(string)$row['reason']];
            $planned['anex']++;
            $writes++;
        };

        foreach ($observations as $o) {
            $id = (int)$o['anex_hotel_id'];
            $country = (int)$o['country_id'];
            if (!isset(MBR_CORE8[$country])) continue;
            $seenAnex[$id] = true;
            if (isset($manual[$id]) || isset($existing[$id])) {
                $skipped['anex_protected']++;
                continue;
            }
            $s = $staging[$id] ?? [];
            $source = [
                'observed'=>true,
                'search_count'=>(int)$o['search_count'],
                'last_seen_utc'=>$o['last_seen_utc'],
                'names'=>[$o['hotel_name'],$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],
                'places'=>[$s['api_region']??'',$s['api_town']??''],
                'latitude'=>$s['latitude']??null,
                'longitude'=>$s['longitude']??null,
            ];
            $row = mbr_review_anex($source,$id,$country,$hotels,$names,$strict,$broad,$places);
            $target = (int)($row['target']['local_hotel_id'] ?? 0);
            if ($target && isset($excluded[$id][$target])) {
                $skipped['anex_pair_excluded']++;
                continue;
            }
            if (($row['bucket'] ?? '') !== 'auto_accept') {
                $skipped['anex_not_auto']++;
                continue;
            }
            $acceptAnex($row);
            $existing[$id] = true;
        }

        foreach ($staging as $id => $s) {
            if (isset($seenAnex[$id]) || isset($manual[$id]) || isset($existing[$id])) continue;
            $country = fc_country($s['api_country'] ?? '');
            if (!$country || !isset(MBR_CORE8[$country])) continue;
            $source = [
                'observed'=>false,
                'search_count'=>0,
                'names'=>[$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],
                'places'=>[$s['api_region']??'',$s['api_town']??''],
                'latitude'=>$s['latitude']??null,
                'longitude'=>$s['longitude']??null,
            ];
            $row = mbr_review_anex($source,(int)$id,$country,$hotels,$names,$strict,$broad,$places);
            $target = (int)($row['target']['local_hotel_id'] ?? 0);
            if ($target && isset($excluded[(int)$id][$target])) {
                $skipped['anex_pair_excluded']++;
                continue;
            }
            if (($row['bucket'] ?? '') !== 'auto_accept') {
                $skipped['anex_not_auto']++;
                continue;
            }
            $acceptAnex($row);
            $existing[(int)$id] = true;
        }

        $latest = [];
        foreach ($db->query(
            "SELECT * FROM andromeda_search_hotel_observations
             WHERE supplier_namespace='andromeda_catalog'
             ORDER BY observed_at_utc DESC,external_hotel_id FOR UPDATE"
        )->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $id = (string)$o['external_hotel_id'];
            if (!isset($latest[$id])) $latest[$id] = $o;
        }

        $pending = $db->query(
            "SELECT * FROM andromeda_hotel_identities
             WHERE supplier_namespace='andromeda_catalog'
               AND decision_status='pending' AND local_hotel_id IS NULL
             ORDER BY external_hotel_id FOR UPDATE"
        )->fetchAll(PDO::FETCH_ASSOC);

        $update = $db->prepare(
            "UPDATE andromeda_hotel_identities
             SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=?
             WHERE supplier_namespace='andromeda_catalog'
               AND external_hotel_id=?
               AND decision_status='pending' AND local_hotel_id IS NULL
               AND evidence_sha256 <=> ?"
        );

        foreach ($pending as $r) {
            $external = (string)$r['external_hotel_id'];
            $obs = $latest[$external] ?? null;
            $country = (int)($obs['country_id'] ?? 0);
            if (!isset(MBR_CORE8[$country])) $country = (int)($shaCountry[$r['catalog_sha256']] ?? 0);
            if (!isset(MBR_CORE8[$country])) {
                $skipped['andromeda_country_unknown']++;
                continue;
            }
            $row = mbr_review_andromeda($r,$obs,$country,$hotels,$names,$strict,$places);
            if (($row['bucket'] ?? '') !== 'auto_accept') {
                $skipped['andromeda_not_auto']++;
                continue;
            }
            $prior = fc_evidence($r['evidence_json'] ?? '');
            $evidence = mba_andromeda_evidence($operation,$prior,$row);
            $json = fc_json($evidence);
            $hash = hash('sha256',$json);
            $target = (int)$row['target']['local_hotel_id'];
            $update->execute([$target,$hash,$json,$external,$r['evidence_sha256']]);
            if ($update->rowCount() !== 1) throw new RuntimeException('andromeda_concurrent_change');
            $andRows[$external] = ['target'=>$target,'evidence_sha256'=>$hash,'reason'=>(string)$row['reason']];
            $planned['andromeda']++;
            $writes++;
        }

        $db->commit();
        $committed = true;

        $qAn = $db->prepare(
            "SELECT catalog_hotel_id,source_row_digest,mapping_digest,enabled
             FROM anex_hotel_search_mappings
             WHERE anex_hotel_id=? AND scope='preview' AND approval_policy=?"
        );
        foreach ($anRows as $id => $expected) {
            $qAn->execute([(int)$id,MBR_POLICY]);
            $actual = $qAn->fetch(PDO::FETCH_ASSOC);
            if (!$actual
                || (int)$actual['catalog_hotel_id'] !== (int)$expected['target']
                || !hash_equals((string)$expected['source_row_digest'],(string)$actual['source_row_digest'])
                || !hash_equals($mappingDigest,(string)$actual['mapping_digest'])
                || (int)$actual['enabled'] !== 1) {
                throw new RuntimeException('anex_post_commit_readback_failed');
            }
        }

        $qAnd = $db->prepare(
            "SELECT local_hotel_id,decision_status,evidence_sha256
             FROM andromeda_hotel_identities
             WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?"
        );
        foreach ($andRows as $external => $expected) {
            $qAnd->execute([(string)$external]);
            $actual = $qAnd->fetch(PDO::FETCH_ASSOC);
            if (!$actual
                || (int)$actual['local_hotel_id'] !== (int)$expected['target']
                || (string)$actual['decision_status'] !== 'accepted'
                || !hash_equals((string)$expected['evidence_sha256'],(string)$actual['evidence_sha256'])) {
                throw new RuntimeException('andromeda_post_commit_readback_failed');
            }
        }

        $after = fc_coverage($db);
        $identityAfter = mba_identity_counts($db);
        return [
            'status'=>'completed',
            'operation_id'=>$operation,
            'mode'=>'server_current_guarded_write',
            'database_writes'=>$writes,
            'committed'=>true,
            'supplier_calls'=>0,
            'planned'=>$planned,
            'skipped'=>$skipped,
            'catalog_scope'=>$catalogScope,
            'coverage_before'=>$before,
            'coverage_after'=>$after,
            'identity_before'=>$identityBefore,
            'identity_after'=>$identityAfter,
            'accepted'=>[
                'anex'=>array_values(array_map(
                    static fn($id,$r)=>['external_id'=>(int)$id]+$r,
                    array_keys($anRows),array_values($anRows)
                )),
                'andromeda'=>array_values(array_map(
                    static fn($id,$r)=>['external_id'=>(string)$id]+$r,
                    array_keys($andRows),array_values($andRows)
                )),
            ],
            'readback_verified'=>true,
            'guards'=>[
                'current_db_same_transaction'=>true,
                'core8_only'=>true,
                'manual_decisions_overwritten'=>false,
                'pair_exclusions_overwritten'=>false,
                'existing_mappings_overwritten'=>false,
                'coordinate_conflict_auto_block_m'=>5000,
                'numeric_star_is_guard_not_identity'=>true,
                'supplier_calls'=>0,
            ],
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $known = [
            'operation_scope','required_table_missing','required_transactional_table_missing',
            'country_contract_changed','hotel_scope_limit','alias_scope_limit',
            'anex_insert_not_one','andromeda_concurrent_change',
            'anex_post_commit_readback_failed','andromeda_post_commit_readback_failed',
        ];
        return [
            'status'=>'failed',
            'operation_id'=>$operation,
            'mode'=>'server_current_guarded_write',
            'database_writes'=>$committed ? $writes : 0,
            'committed'=>$committed,
            'supplier_calls'=>0,
            'reason'=>in_array($e->getMessage(),$known,true)?$e->getMessage():'runtime_failure',
            'readback_verified'=>false,
        ];
    }
}
