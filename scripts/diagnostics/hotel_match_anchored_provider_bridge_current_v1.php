<?php
declare(strict_types=1);

/**
 * MATCH anchored provider-bridge CURRENT dossier v1.
 *
 * READ ONLY. Recomputes the bounded residual where a pending provider-native
 * identity has a saved provider_bridge to an Andromeda hotel whose
 * `andromeda_catalog` identity is already accepted to the Tourvisor-backed
 * catalog. Existing accepted provider sources are subtracted. This diagnostic
 * emits evidence/name/geo/protection facts only; it never accepts mappings.
 */

if (!defined('MATCH_PROVIDER_EVIDENCE_EXPORT_LIBRARY_ONLY')) {
    define('MATCH_PROVIDER_EVIDENCE_EXPORT_LIBRARY_ONLY', true);
}
require_once __DIR__ . '/hotel_match_provider_evidence_current_export_v1.php';

const ABR_SCHEMA = 'anchored-provider-bridge-current-v1';
const ABR_PROVIDER_NAMESPACES = ['operator_315', 'operator_342', 'operator_5'];

function abr_normalize_name(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    $value = mb_strtoupper($value, 'UTF-8');
    $value = str_replace(['&', '+'], ' AND ', $value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}

function abr_name_forms(?string $value): array {
    $raw = trim((string)$value);
    if ($raw === '') return [];
    $parts = [$raw];
    if (preg_match('/^(.*?)\s*\(\s*EX\.?\s+(.+?)\s*\)\s*$/iu', $raw, $m)) {
        $parts[] = trim($m[1]);
        $parts[] = trim($m[2]);
    }
    $forms = [];
    foreach ($parts as $part) {
        $normalized = abr_normalize_name($part);
        if ($normalized !== '') $forms[$normalized] = true;
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $generic = ['HOTEL'=>true,'HOTELS'=>true,'RESORT'=>true,'RESORTS'=>true,'SPA'=>true,'EX'=>true];
        $reduced = array_values(array_filter($tokens, fn($t) => !isset($generic[$t])));
        if ($reduced) $forms[implode(' ', $reduced)] = true;
    }
    $keys = array_keys($forms);
    sort($keys, SORT_STRING);
    return $keys;
}

function abr_token_set(?string $value): array {
    $normalized = abr_normalize_name($value);
    if ($normalized === '') return [];
    $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $set = [];
    foreach ($tokens as $token) $set[$token] = true;
    ksort($set, SORT_STRING);
    return array_keys($set);
}

function abr_jaccard(?string $a, ?string $b): float {
    $aa = array_fill_keys(abr_token_set($a), true);
    $bb = array_fill_keys(abr_token_set($b), true);
    if (!$aa && !$bb) return 1.0;
    $union = array_unique(array_merge(array_keys($aa), array_keys($bb)));
    if (!$union) return 0.0;
    $intersection = array_intersect_key($aa, $bb);
    return round(count($intersection) / count($union), 6);
}

function abr_name_relation(?string $sourceName, ?string $targetName): array {
    $sourceForms = abr_name_forms($sourceName);
    $targetForms = abr_name_forms($targetName);
    $intersection = array_values(array_intersect($sourceForms, $targetForms));
    sort($intersection, SORT_STRING);
    return [
        'source_normalized' => abr_normalize_name($sourceName),
        'target_normalized' => abr_normalize_name($targetName),
        'exact_primary' => abr_normalize_name($sourceName) !== '' && abr_normalize_name($sourceName) === abr_normalize_name($targetName),
        'exact_any_form' => count($intersection) > 0,
        'matching_forms' => $intersection,
        'token_jaccard' => abr_jaccard($sourceName, $targetName),
    ];
}

function abr_bridge_records(array $identityRows): array {
    $anchors = [];
    $acceptedSources = [];
    $pending = [];
    foreach ($identityRows as $row) {
        $namespace = trim((string)($row['supplier_namespace'] ?? ''));
        $external = trim((string)($row['external_hotel_id'] ?? ''));
        $status = strtolower(trim((string)($row['decision_status'] ?? '')));
        $local = trim((string)($row['local_hotel_id'] ?? ''));
        $evidence = me_json_decode_object($row['evidence_json'] ?? null);
        if ($namespace === 'andromeda_catalog' && $status === 'accepted' && ctype_digit($external) && ctype_digit($local) && (int)$local > 0) {
            $anchors[$external] = [
                'tourvisor_hotel_id' => (int)$local,
                'evidence_sha256' => me_clean_text($row['evidence_sha256'] ?? null, 128),
            ];
            continue;
        }
        if (!in_array($namespace, ABR_PROVIDER_NAMESPACES, true) || $external === '') continue;
        if ($status === 'accepted' && ctype_digit($local) && (int)$local > 0) {
            $acceptedSources[$namespace . '/' . $external] = (int)$local;
            continue;
        }
        if ($status !== 'pending') continue;
        $bridges = $evidence['provider_bridges'] ?? [];
        if (!is_array($bridges)) continue;
        foreach ($bridges as $bridge) {
            if (!is_array($bridge)) continue;
            $andromedaId = trim((string)($bridge['andromeda_hotel_id'] ?? ''));
            if ($andromedaId === '' || !ctype_digit($andromedaId)) continue;
            $pending[] = [
                'provider_namespace' => $namespace,
                'provider_hotel_id' => $external,
                'andromeda_hotel_id' => $andromedaId,
                'source_name' => me_clean_text($bridge['hotel_name'] ?? ($evidence['source']['name'] ?? null), 255),
                'source_country_id' => isset($bridge['country_id']) ? (int)$bridge['country_id'] : null,
                'source_state' => me_clean_text($evidence['source']['state'] ?? null, 255),
                'source_evidence_sha256' => me_clean_text($row['evidence_sha256'] ?? null, 128),
                'protected_identity_state' => me_protected_json($evidence),
            ];
        }
    }
    return ['anchors'=>$anchors, 'accepted_sources'=>$acceptedSources, 'pending'=>$pending];
}

function abr_anex_accepted_sources(array $mappingRows): array {
    $accepted = [];
    foreach ($mappingRows as $row) {
        if (!me_bool($row['enabled'] ?? false)) continue;
        $id = trim((string)($row['anex_hotel_id'] ?? ''));
        $target = trim((string)($row['catalog_hotel_id'] ?? ''));
        if ($id !== '' && ctype_digit($id) && ctype_digit($target) && (int)$target > 0) {
            $accepted['operator_5/' . $id] = (int)$target;
        }
    }
    return $accepted;
}

function abr_pair_set(array $rows): array {
    $set = [];
    foreach ($rows as $row) {
        $source = trim((string)($row['anex_hotel_id'] ?? ''));
        $target = trim((string)($row['catalog_hotel_id'] ?? ''));
        if (ctype_digit($source) && ctype_digit($target)) $set[$source . '/' . $target] = true;
    }
    return $set;
}

function abr_build_dossier(array $identityRows, array $anexMappings, array $catalogRows, array $decisionRows = [], array $exclusionRows = []): array {
    $parts = abr_bridge_records($identityRows);
    $accepted = $parts['accepted_sources'] + abr_anex_accepted_sources($anexMappings);
    $catalog = [];
    foreach ($catalogRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) $catalog[$id] = $row;
    }
    $decisions = abr_pair_set($decisionRows);
    $exclusions = abr_pair_set($exclusionRows);
    $out = [];
    $seen = [];
    foreach ($parts['pending'] as $pending) {
        $sourceKey = $pending['provider_namespace'] . '/' . $pending['provider_hotel_id'];
        if (isset($accepted[$sourceKey])) continue;
        $anchor = $parts['anchors'][$pending['andromeda_hotel_id']] ?? null;
        if (!$anchor) continue;
        $targetId = (int)$anchor['tourvisor_hotel_id'];
        $dedupe = $sourceKey . '->' . $targetId . '@' . $pending['andromeda_hotel_id'];
        if (isset($seen[$dedupe])) continue;
        $seen[$dedupe] = true;
        $target = $catalog[$targetId] ?? null;
        $pairKey = $pending['provider_hotel_id'] . '/' . $targetId;
        $protected = $pending['protected_identity_state'] ||
            ($pending['provider_namespace'] === 'operator_5' && (isset($decisions[$pairKey]) || isset($exclusions[$pairKey])));
        $status = 'review_saved_evidence';
        if (!$target) $status = 'hold_target_missing';
        elseif (!me_bool($target['is_active'] ?? false)) $status = 'hold_target_inactive';
        elseif ($protected) $status = 'hold_protected';
        $targetName = $target ? me_clean_text($target['name'] ?? null, 255) : null;
        $targetCountry = $target && isset($target['country_id']) ? (int)$target['country_id'] : null;
        $countryRelation = 'unknown';
        if ($pending['source_country_id'] !== null && $targetCountry !== null) {
            $countryRelation = $pending['source_country_id'] === $targetCountry ? 'same' : 'conflict';
        }
        $out[] = [
            'provider_namespace' => $pending['provider_namespace'],
            'provider_hotel_id' => $pending['provider_hotel_id'],
            'andromeda_hotel_id' => $pending['andromeda_hotel_id'],
            'tourvisor_hotel_id' => $targetId,
            'source_name' => $pending['source_name'],
            'source_country_id' => $pending['source_country_id'],
            'source_state' => $pending['source_state'],
            'source_evidence_sha256' => $pending['source_evidence_sha256'],
            'anchor_evidence_sha256' => $anchor['evidence_sha256'],
            'target_name' => $targetName,
            'target_country_id' => $targetCountry,
            'target_country_name' => $target ? me_clean_text($target['country_name'] ?? null, 180) : null,
            'target_region_id' => $target && isset($target['region_id']) ? (int)$target['region_id'] : null,
            'target_region_name' => $target ? me_clean_text($target['region_name'] ?? null, 180) : null,
            'target_subregion_id' => $target && isset($target['subregion_id']) ? (int)$target['subregion_id'] : null,
            'target_subregion_name' => $target ? me_clean_text($target['subregion_name'] ?? null, 180) : null,
            'target_category' => $target ? me_clean_text($target['category'] ?? null, 80) : null,
            'target_latitude' => $target && $target['latitude'] !== null ? (float)$target['latitude'] : null,
            'target_longitude' => $target && $target['longitude'] !== null ? (float)$target['longitude'] : null,
            'target_active' => $target ? me_bool($target['is_active'] ?? false) : false,
            'country_relation' => $countryRelation,
            'name_relation' => abr_name_relation($pending['source_name'], $targetName),
            'protected' => $protected,
            'status' => $status,
        ];
    }
    usort($out, fn($a, $b) => [$a['provider_namespace'], $a['provider_hotel_id'], $a['tourvisor_hotel_id']] <=> [$b['provider_namespace'], $b['provider_hotel_id'], $b['tourvisor_hotel_id']]);
    return $out;
}

function abr_fetch_pair_rows(PDO $db, string $table): array {
    if (!me_table_exists($db, $table)) return [];
    $cols = me_columns($db, $table);
    if (!isset($cols['anex_hotel_id'], $cols['catalog_hotel_id'])) return [];
    return me_query($db, "SELECT anex_hotel_id,catalog_hotel_id FROM {$table} ORDER BY anex_hotel_id,catalog_hotel_id", [], 60000);
}

function abr_fetch_current(PDO $db): array {
    me_require_columns($db, 'andromeda_hotel_identities', ['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','evidence_sha256','evidence_json']);
    me_require_columns($db, 'anex_hotel_search_mappings', ['anex_hotel_id','catalog_hotel_id','enabled']);
    me_require_columns($db, 'catalog_hotels', ['id','country_id','country_name','region_id','region_name','subregion_id','subregion_name','name','category','latitude','longitude','is_active']);

    $identities = me_query($db, "SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace IN ('andromeda_catalog','operator_315','operator_342','operator_5') ORDER BY supplier_namespace,external_hotel_id", [], 60000);
    $anex = me_query($db, "SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE enabled=1 ORDER BY anex_hotel_id,catalog_hotel_id", [], 60000);

    $parts = abr_bridge_records($identities);
    $targetIds = [];
    foreach ($parts['pending'] as $pending) {
        $anchor = $parts['anchors'][$pending['andromeda_hotel_id']] ?? null;
        if ($anchor) $targetIds[(int)$anchor['tourvisor_hotel_id']] = true;
    }
    $catalog = [];
    if ($targetIds) {
        $ids = array_keys($targetIds);
        sort($ids, SORT_NUMERIC);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $catalog = me_query($db, "SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ({$marks}) ORDER BY id", $ids, 10000);
    }
    return [
        'identity_rows' => $identities,
        'anex_mapping_rows' => $anex,
        'catalog_rows' => $catalog,
        'decision_rows' => abr_fetch_pair_rows($db, 'anex_hotel_decisions'),
        'exclusion_rows' => abr_fetch_pair_rows($db, 'anex_review_pair_exclusions'),
    ];
}

function abr_census(array $rows): array {
    $status = $provider = $country = $exact = [];
    foreach ($rows as $row) {
        $status[$row['status']] = ($status[$row['status']] ?? 0) + 1;
        $provider[$row['provider_namespace']] = ($provider[$row['provider_namespace']] ?? 0) + 1;
        $country[$row['country_relation']] = ($country[$row['country_relation']] ?? 0) + 1;
        $key = $row['name_relation']['exact_any_form'] ? 'exact_any_form' : 'not_exact';
        $exact[$key] = ($exact[$key] ?? 0) + 1;
    }
    foreach ([$status, $provider, $country, $exact] as &$part) ksort($part, SORT_STRING);
    return ['rows'=>count($rows),'by_status'=>$status,'by_provider'=>$provider,'by_country_relation'=>$country,'by_name_relation'=>$exact];
}

function abr_main(): int {
    me_require(PHP_SAPI === 'cli', 'cli_only');
    $operationId = trim((string)getenv('MATCH_OPERATION_ID'));
    $sourceSha = trim((string)getenv('MATCH_SOURCE_SHA'));
    me_require((bool)preg_match('/^hotel-match-anchored-provider-bridge-current-1971-[0-9]{8}-v[0-9]+$/D', $operationId), 'operation_id');
    me_require((bool)preg_match('/^[a-f0-9]{40}$/D', $sourceSha), 'source_sha');
    $root = realpath(getcwd());
    me_require(is_string($root) && basename($root) === 'anytoour.ru', 'repo_root');
    $dir = rtrim((string)getenv('HOME'), '/') . '/.anytoour-match/operations/' . $operationId;
    me_require(is_dir($dir) && is_file($dir . '/reservation.json'), 'reservation_missing');
    $reservation = json_decode((string)file_get_contents($dir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    me_require(($reservation['operation_id'] ?? '') === $operationId && ($reservation['source_sha'] ?? '') === $sourceSha, 'reservation_identity');

    $result = ['schema'=>ABR_SCHEMA,'operation_id'=>$operationId,'source_sha'=>$sourceSha,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];
    $db = null;
    try {
        $dbFile = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
        require_once $dbFile;
        $db = v2_data_db();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $current = abr_fetch_current($db);
        $rows = abr_build_dossier($current['identity_rows'], $current['anex_mapping_rows'], $current['catalog_rows'], $current['decision_rows'], $current['exclusion_rows']);
        $db->exec('ROLLBACK');
        $report = ['schema'=>ABR_SCHEMA,'read_at_utc'=>gmdate('c'),'transaction'=>'REPEATABLE READ / READ ONLY','census'=>abr_census($rows),'rows'=>$rows];
        $reportBytes = me_json_bytes($report);
        $reportSha = me_write_exclusive($dir . '/dossier.json', $reportBytes);
        $result['state'] = 'completed_read_only';
        $result['row_count'] = count($rows);
        $result['census'] = $report['census'];
        $result['dossier_sha256'] = $reportSha;
    } catch (Throwable $e) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        $message = $e->getMessage();
        $result['error_code'] = preg_match('/^[a-zA-Z0-9_\-]{2,120}$/D', $message) ? $message : 'sanitized_failure';
    }
    $resultBytes = me_json_bytes($result);
    $resultSha = me_write_exclusive($dir . '/result.json', $resultBytes);
    $receipt = ['operation_id'=>$operationId,'source_sha'=>$sourceSha,'state'=>$result['state'],'result_sha256'=>$resultSha,'readback_verified'=>hash('sha256',(string)file_get_contents($dir . '/result.json')) === $resultSha,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];
    me_write_exclusive($dir . '/receipt.json', me_json_bytes($receipt));
    echo me_json_bytes(['state'=>$result['state'],'row_count'=>$result['row_count'] ?? null,'census'=>$result['census'] ?? null,'result_sha256'=>$resultSha]);
    return $result['state'] === 'completed_read_only' ? 0 : 2;
}

if (!defined('ABR_LIBRARY_ONLY')) {
    if (in_array('--self-test', $argv ?? [], true)) {
        $r = abr_name_relation('Sealife Buket Resort & Beach', 'SEALIFE BUKET BEACH (EX. ASKA BUKET RESORT & SPA)');
        me_require(is_float($r['token_jaccard']) && $r['token_jaccard'] > 0, 'self_test_relation');
        echo "anchored provider bridge current v1 self-test PASS\n";
        exit(0);
    }
    exit(abr_main());
}
