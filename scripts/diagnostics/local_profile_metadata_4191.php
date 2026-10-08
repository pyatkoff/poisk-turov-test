<?php
/** Private canonical metadata only. No source-card read, content plan or writer. */
declare(strict_types=1);
require_once __DIR__.'/local_profile_plan_4191.php';

const LMD_OPERATION = 'int-andromeda-local-metadata-read-4191-20261008-v1';
const LMD_BATCH = 'local4191-metadata-20261008';
const LMD_PARENT = 'int-andromeda-local-profile-mass-plan2-4191-20261002-v1';
const LMD_PARENT_HASH = 'aafbc0aa015d485817ae9d851a6200f677488ea5538ab73447f2ee1dc67c84e1';
const LMD_LIMIT = 250;
const LMD_STATES = ['PROFILE_UNAVAILABLE','ALIAS_HELD','PROFILE_INTEGRITY_HELD',
    'CURRENT_LINK_DRIFT_HELD','CURRENT_METADATA_DRIFT_HELD','D1_OVERLAP_HELD','PRIOR_OR_EDITORIAL_HELD',
    'PHASE3_INDEPENDENCE_UNPROVEN'];

function lmd_positive(mixed $value): bool {
    return is_int($value) && $value > 0 && $value <= 9007199254740991;
}
function lmd_hash(mixed $value): bool {
    return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
}
/** A historical exclusion index is not the missing phase3 roster. */
function lmd_input(array $index, array $d1): array {
    lpp_need(($index['schema_version'] ?? null) === 1
        && ($index['operation_id'] ?? null) === LMD_PARENT
        && ($index['batch'] ?? null) === 'local4191-mass-retained2-20261002'
        && ($index['source_sha'] ?? null) === 'a54255507643501abdeca150aeae19b84cb586f6'
        && ($index['control_source_sha'] ?? null) === '00cc9b3ba28319c85282a993c6ca0d57558b604e'
        && ($index['active_profiles'] ?? null) === 15999
        && ($index['source_plans_prepared'] ?? null) === 2000
        && ($index['safe_to_apply'] ?? null) === false
        && is_array($index['rows'] ?? null) && array_is_list($index['rows'])
        && count($index['rows']) === 15999, 'metadata_parent_contract');
    lpp_need(($d1['state'] ?? null) === 'verified_terminal_manifest'
        && count($d1['ownIds'] ?? []) === 80 && count($d1['legacyIds'] ?? []) === 80,
        'metadata_d1_unknown');
    $blockedOwn = array_fill_keys(array_merge(lpp_ids(), $d1['ownIds']), true);
    $blockedLocal = array_fill_keys($d1['legacyIds'], true);
    $seen = $counts = $candidates = [];
    foreach ($index['rows'] as $row) {
        lpp_need(is_array($row) && lmd_positive($row['anytourHotelId'] ?? null)
            && is_string($row['state'] ?? null) && !isset($seen[$row['anytourHotelId']]),
            'metadata_parent_identity');
        $own = $row['anytourHotelId']; $seen[$own] = true;
        $state = $row['state']; $counts[$state] = ($counts[$state] ?? 0) + 1;
        if ($state !== 'PLAN_BOUND_DEFERRED') {
            $blockedOwn[$own] = true;
            if (lmd_positive($row['localHotelId'] ?? null)) $blockedLocal[$row['localHotelId']] = true;
            continue;
        }
        lpp_need(lmd_positive($row['localHotelId'] ?? null)
            && ($row['expectedRevision'] ?? null) === 1
            && lmd_hash($row['expectedProfileSha256'] ?? null)
            && lmd_hash($row['expectedAliasSha256'] ?? null)
            && is_int($row['userSearches'] ?? null) && $row['userSearches'] >= 0
            && is_int($row['observations'] ?? null) && $row['observations'] >= 0
            && is_string($row['lastSeenAt'] ?? null), 'metadata_parent_row');
        $candidates[] = $row;
    }
    ksort($counts); $expected = $index['classification_counts'] ?? null;
    lpp_need(is_array($expected), 'metadata_parent_counts'); ksort($expected);
    lpp_need($counts === $expected && ($counts['PLAN_BOUND_DEFERRED'] ?? 0) === 9142
        && ($counts['PREDECESSOR_2000_HELD'] ?? 0) === 2000
        && ($counts['SOURCE_MISSING'] ?? 0) === 1868
        && ($counts['RETAINED_DELTA_PREPARED'] ?? 0) === 130
        && ($counts['SOURCE_PROVENANCE_HELD'] ?? 0) === 2, 'metadata_parent_counts');
    usort($candidates, static fn(array $a, array $b): int =>
        ($b['userSearches'] <=> $a['userSearches']) ?: ($b['observations'] <=> $a['observations'])
        ?: strcmp($b['lastSeenAt'], $a['lastSeenAt']) ?: ($a['anytourHotelId'] <=> $b['anytourHotelId']));
    $scope = []; $locals = [];
    foreach ($candidates as $row) {
        $own = $row['anytourHotelId']; $local = $row['localHotelId'];
        if (isset($blockedOwn[$own]) || isset($blockedLocal[$local])) continue;
        lpp_need(!isset($locals[$local]), 'metadata_duplicate_local'); $locals[$local] = true;
        if (count($scope) < LMD_LIMIT) $scope[] = array_intersect_key($row, array_flip([
            'anytourHotelId','localHotelId','expectedRevision','expectedProfileSha256','expectedAliasSha256']));
    }
    lpp_need(count($scope) === LMD_LIMIT, 'metadata_input_bound');
    return $scope;
}

function lmd_missing(array $profile): array {
    $out = [];
    foreach (LPP_FIELDS as $field) {
        $value = $profile;
        foreach (explode('.', $field) as $part) $value = is_array($value) ? ($value[$part] ?? null) : null;
        if ($value === null || $value === [] || (is_string($value) && trim($value) === '')) $out[] = $field;
    }
    sort($out, SORT_STRING); return $out;
}
/** CURRENT metadata cannot turn UNKNOWN phase3 into an independent source cohort. */
function lmd_classify(array $scope, array $byId, array $d1): array {
    $rows = $counts = $missing = []; $read = $screened = $aliases = 0;
    $d1Own = array_fill_keys($d1['ownIds'], true); $d1Local = array_fill_keys($d1['legacyIds'], true);
    foreach ($scope as $input) {
        $own = $input['anytourHotelId']; $current = $byId[$own] ?? [];
        $out = ['anytourHotelId'=>$own,'requestedLocalHotelId'=>$input['localHotelId'],
            'state'=>'PROFILE_UNAVAILABLE','missingFields'=>[],
            'safeToPlan'=>false,'safeToApply'=>false,'sourceMaterialEvaluated'=>false];
        if ($current !== [] && (int)$current[0]['is_active'] === 1) {
            ++$read;
            if (count($current) !== 1) $out['state'] = 'ALIAS_HELD';
            elseif (!lpp_profile_valid($current[0])) $out['state'] = 'PROFILE_INTEGRITY_HELD';
            else {
                $row = $current[0]; ++$screened;
                $out['revision'] = (int)$row['revision']; $out['profileSha256'] = $row['profile_sha256'];
                $out['priorContentOperations'] = (int)$row['prior_content_operations'];
                $out['missingFields'] = lmd_missing(json_decode($row['profile_json'], true, 512, JSON_THROW_ON_ERROR));
                foreach ($out['missingFields'] as $field) $missing[$field] = ($missing[$field] ?? 0) + 1;
                $local = lpp_alias($row, $own);
                $out['state'] = 'ALIAS_HELD';
                if ($local !== null) {
                    ++$aliases; $out['acceptedLocalHotelId'] = $local; $out['aliasSha256'] = $row['alias_source_sha256'];
                    $out['state'] = isset($d1Own[$own]) || isset($d1Local[$local]) ? 'D1_OVERLAP_HELD'
                        : ($local !== $input['localHotelId'] ? 'CURRENT_LINK_DRIFT_HELD'
                        : (($out['revision'] !== 1 || $out['priorContentOperations'] !== 0)
                            ? 'PRIOR_OR_EDITORIAL_HELD'
                            : ((!hash_equals($input['expectedProfileSha256'], $out['profileSha256'])
                                || !hash_equals($input['expectedAliasSha256'], $out['aliasSha256']))
                                ? 'CURRENT_METADATA_DRIFT_HELD' : 'PHASE3_INDEPENDENCE_UNPROVEN')));
                }
            }
        }
        $counts[$out['state']] = ($counts[$out['state']] ?? 0) + 1; $rows[] = $out;
    }
    ksort($counts); ksort($missing);
    return ['rows'=>$rows,'requested_profiles'=>count($scope),'profiles_read'=>$read,
        'metadata_screened'=>$screened,'aliases_validated'=>$aliases,
        'classification_counts'=>$counts,'missing_field_counts'=>($missing === [] ? (object)[] : $missing)];
}

function lmd_read(PDO $db, array $scope): array {
    lpp_need(!$db->inTransaction() && $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'metadata_database');
    lpp_need(count($scope) === LMD_LIMIT, 'metadata_read_bound');
    $ids = array_column($scope, 'anytourHotelId');
    lpp_need(count(array_unique($ids)) === LMD_LIMIT && count(array_filter($ids, 'lmd_positive')) === LMD_LIMIT,
        'metadata_read_identity');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
    try {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $q = $db->prepare("SELECT h.id,h.is_active,h.revision,h.profile_json,h.profile_sha256,
            s.external_key AS local_id,s.acquired_via AS alias_acquired_via,
            s.source_json AS alias_source_json,s.source_sha256 AS alias_source_sha256,
            (SELECT COUNT(*) FROM anytour_hotel_sources p WHERE p.anytour_hotel_id=h.id
                AND p.namespace IN ('profile_enrichment:legacy_saved_v1','profile_sync:retained_tv_v1')) AS prior_content_operations
            FROM anytour_hotels h LEFT JOIN anytour_hotel_sources s
                ON s.anytour_hotel_id=h.id AND s.namespace='anytour_local_id'
            WHERE h.id IN ($marks) ORDER BY h.id,s.id");
        $q->execute($ids); $byId = []; $bytes = $n = 0; $wanted = array_fill_keys($ids, true);
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $own = (int)$row['id'];
            lpp_need(isset($wanted[$own]) && ++$n <= LMD_LIMIT * 4, 'metadata_result_bound');
            $bytes += strlen((string)$row['profile_json']) + strlen((string)$row['alias_source_json']);
            lpp_need($bytes <= 32 * 1024 * 1024, 'metadata_byte_bound');
            $byId[$own][] = $row;
        }
        $q->closeCursor(); $db->commit(); return $byId;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack(); throw $error;
    }
}
function lmd_store(string $path, array $value): string {
    $hash = lpp_save($path, $value);
    lpp_need(hash_equals($hash, hash('sha256', lpp_file($path, 32 * 1024 * 1024))), 'metadata_output_readback');
    return $hash;
}
function lmd_main(array $argv): int {
    lpp_need(PHP_SAPI === 'cli' && count($argv) === 2 && $argv[1] === '--metadata-only', 'metadata_disabled');
    $home = (string)getenv('HOME'); $root = (string)getenv('ANYTOUR_ROOT');
    $dir = (string)getenv('LOCAL_METADATA_DIR'); $source = (string)getenv('LOCAL_PROFILE_SOURCE_SHA');
    $control = (string)getenv('LOCAL_PROFILE_CONTROL_SHA');
    lpp_need($root === $home.'/www/anytoour.ru' && realpath($root) === $root
        && realpath($dir) === $dir && dirname($dir) === $home.'/.anytoour-int-executor'
        && basename($dir) === LMD_OPERATION && preg_match('/^[a-f0-9]{40}$/D', $source) === 1
        && preg_match('/^[a-f0-9]{40}$/D', $control) === 1, 'metadata_runtime');
    $reservation = json_decode(lpp_file($dir.'/reservation.json', 65536), true, 32, JSON_THROW_ON_ERROR);
    lpp_need(($reservation['operation_id'] ?? null) === LMD_OPERATION
        && ($reservation['source_sha'] ?? null) === $source
        && ($reservation['mode'] ?? null) === 'local-profile-plan-4191', 'metadata_reservation');
    foreach (['local-metadata-input.json','local-metadata.json','local-metadata-receipt.json'] as $file)
        lpp_need(!file_exists($dir.'/'.$file) && !is_link($dir.'/'.$file), 'metadata_no_replay');
    $bytes = lpp_file($home.'/.anytoour-int-executor/'.LMD_PARENT.'/local-mass2-plan.json', 32 * 1024 * 1024);
    lpp_need(hash_equals(LMD_PARENT_HASH, hash('sha256', $bytes)), 'metadata_parent_hash');
    $index = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR); $d1 = lpp_d1($home);
    $scope = lmd_input($index, $d1);
    $identity = ['schema_version'=>1,'operation_id'=>LMD_OPERATION,'batch'=>LMD_BATCH,
        'source_sha'=>$source,'control_source_sha'=>$control,'parent_sha256'=>LMD_PARENT_HASH];
    $receipt = lmd_observe($dir, $identity, $scope, $d1, static function() use ($root): PDO {
        $bootstrap = $root.(is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
        lpp_need(is_file($bootstrap) && !is_link($bootstrap) && realpath($bootstrap) === $bootstrap, 'metadata_bootstrap');
        $_SERVER['DOCUMENT_ROOT'] = $root; require_once $bootstrap;
        return v2_data_db();
    });
    echo lpp_json($receipt)."\n"; return 0;
}
/** The callback is internal testability, not a caller-controlled endpoint or command. */
function lmd_observe(string $dir, array $identity, array $scope, array $d1, callable $connect): array {
    // Durable exact roster precedes configuration loading and the first DB connection.
    $inputHash = lmd_store($dir.'/local-metadata-input.json', $identity + ['scope'=>$scope,
        'priority_basis'=>'historical_mass2_demand','safe_to_plan'=>false,'safe_to_apply'=>false]);
    $readAt = gmdate('Y-m-d H:i:s'); $audit = lmd_classify($scope, lmd_read($connect(), $scope), $d1);
    $privateHash = lmd_store($dir.'/local-metadata.json', $identity + ['input_sha256'=>$inputHash,
        'read_at'=>$readAt,'rows'=>$audit['rows'],'phase3_state'=>'UNKNOWN_NO_REPLAY',
        'safe_to_plan'=>false,'safe_to_apply'=>false]);
    unset($audit['rows']);
    $receipt = $identity + $audit + ['state'=>'completed_read_only','input_sha256'=>$inputHash,
        'metadata_sha256'=>$privateHash,'read_at'=>$readAt,'phase3_state'=>'UNKNOWN_NO_REPLAY',
        'safe_to_plan'=>false,'safe_to_apply'=>false,'source_plans_prepared'=>0,'source_cards_read'=>0,
        'provider_http_calls'=>0,'database_writes'=>0,'profile_writes'=>0,'mapping_writes'=>0,'schema_writes'=>0];
    lmd_store($dir.'/local-metadata-receipt.json', $receipt);
    return $receipt;
}
if (PHP_SAPI === 'cli' && realpath((string)($argv[0] ?? '')) === __FILE__) {
    try { exit(lmd_main($argv)); }
    catch (Throwable) { fwrite(STDERR, "local_metadata_failed_no_replay\n"); exit(2); }
}
