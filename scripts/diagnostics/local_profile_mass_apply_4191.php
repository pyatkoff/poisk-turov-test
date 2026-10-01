<?php
/** Exact retained71 apply; only the existing canonical owner can write SQL. */
declare(strict_types=1);
require_once __DIR__.'/local_profile_mass_plan_4191.php';

const LPMA_OPERATION = 'int-andromeda-local-profile-mass-apply71-4191-20261002-v1';
const LPMA_BATCH = 'local4191-mass-retained71-20261002';
const LPMA_PLAN_SHA = '0b0a8807bf4b7b07172561537eafe1bc865265ef926c91fa117eed1c9f618426';
const LPMA_PLAN_SOURCE = '5e6797373c61f5b1ad4cb365a18cf15d66526b99';
const LPMA_PLAN_CONTROL = '58af4702bbee0ec584812ce5bf643f60b59df96a';
const LPMA_PROFILES = 71;
const LPMA_FIELDS = 735;
const LPMA_BATCHES = 4;

function lpma_identity(array $value): void {
    lpp_need(($value['schema_version'] ?? null) === 1
        && ($value['operation_id'] ?? null) === LPM_OPERATION
        && ($value['batch'] ?? null) === LPM_BATCH
        && ($value['source_sha'] ?? null) === LPMA_PLAN_SOURCE
        && ($value['control_source_sha'] ?? null) === LPMA_PLAN_CONTROL
        && ($value['demand_through'] ?? null) === '2026-10-01 22:03:08'
        && ($value['safe_to_apply'] ?? null) === false, 'sealed_identity');
}

/** Validate each private batch, before-image and identity against the sealed census. */
function lpma_batches(array $index, array $protected, callable $load): array {
    lpma_identity($index);
    lpp_need(($index['profiles_with_delta'] ?? null) === LPMA_PROFILES
        && ($index['planned_fields'] ?? null) === LPMA_FIELDS
        && is_array($index['rows'] ?? null) && array_is_list($index['rows'])
        && is_array($index['batches'] ?? null) && array_is_list($index['batches'])
        && count($index['batches']) === LPMA_BATCHES, 'sealed_counts');
    $rows = []; $expected = []; $seenOwn = []; $seenLocal = []; $out = []; $fields = 0;
    foreach ($index['rows'] as $row) {
        $own = $row['anytourHotelId'] ?? null;
        lpp_need(is_int($own) && $own > 0 && !isset($rows[$own]), 'census_identity');
        $rows[$own] = $row;
        if (($row['state'] ?? null) === 'RETAINED_DELTA_PREPARED') $expected[$own] = true;
    }
    lpp_need(count($expected) === LPMA_PROFILES, 'selected_count');
    foreach ($index['batches'] as $i=>$meta) {
        lpp_need(is_array($meta) && ($meta['file'] ?? null) === sprintf('mass-batch-%03d.json',$i+1)
            && is_string($meta['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/D',$meta['sha256']) === 1,
            'batch_path');
        $bytes = $load($meta['file']);
        lpp_need(is_string($bytes) && hash_equals($meta['sha256'],hash('sha256',$bytes)), 'batch_digest');
        $batch = json_decode($bytes,true,512,JSON_THROW_ON_ERROR); lpma_identity($batch);
        $plan = $batch['owner_plan'] ?? null;
        lpp_need(is_array($plan) && is_array($plan['contentScope'] ?? null)
            && array_is_list($plan['contentScope']) && count($plan['contentScope']) >= 1
            && count($plan['contentScope']) <= 250 && ($plan['limit'] ?? null) === $meta['scope_profiles']
            && ($plan['planSha256'] ?? null) === ($meta['plan_sha256'] ?? null)
            && ($plan['demandThrough'] ?? null) === $index['demand_through'], 'batch_scope');
        foreach ($plan['contentScope'] as $scope) {
            $own = $scope['anytourHotelId'] ?? null; $local = $scope['localHotelId'] ?? null;
            lpp_need(is_int($own) && is_int($local) && isset($rows[$own])
                && !isset($protected['own'][$own]) && !isset($protected['local'][$local])
                && ($rows[$own]['missingFields'] ?? null) === ($scope['fields'] ?? null), 'excluded_scope');
        }
        $selected = lpm_validate_plan($plan,$plan['contentScope'],$rows);
        lpp_need(count($selected) === ($meta['profiles'] ?? null) && $selected !== [], 'batch_selected');
        $fieldCounts = [];
        foreach ($selected as $own=>$item) {
            $local = $item['localHotelId'];
            lpp_need(isset($expected[$own]) && !isset($seenOwn[$own]) && !isset($seenLocal[$local])
                && $item['expectedRevision'] === 1, 'selected_identity');
            $before = json_decode($item['beforeProfileJson'],true,512,JSON_THROW_ON_ERROR);
            foreach ($item['patch'] as $field=>$value) {
                lpp_need(in_array($field,LPP_FIELDS,true), 'patch_field');
                $old = $before;
                foreach (explode('.',$field) as $part) $old = is_array($old) ? ($old[$part] ?? null) : null;
                lpp_need(lpm_missing($old) && !lpm_missing($value), 'nonempty_preserved');
                $fieldCounts[$field] = ($fieldCounts[$field] ?? 0)+1; ++$fields;
            }
            $seenOwn[$own] = $seenLocal[$local] = true;
        }
        ksort($fieldCounts);
        $out[] = ['plan'=>$plan,'rows'=>$rows,'profiles'=>count($selected),'fieldCounts'=>$fieldCounts];
    }
    lpp_need(count($seenOwn) === LPMA_PROFILES && count($seenLocal) === LPMA_PROFILES
        && array_diff_key($expected,$seenOwn) === [] && $fields === LPMA_FIELDS, 'cohort_count');
    return $out;
}

function lpma_protected(PDO $db, array $d1): array {
    lpp_need(($d1['state'] ?? null) === 'verified_terminal_manifest', 'd1_unknown');
    lpp_need(!$db->inTransaction(), 'caller_transaction');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
    try {
        $history = lpm_historical_rows_in_snapshot($db); $locals = [];
        foreach (lpp_ids() as $own) {
            $rows = $history[$own] ?? []; $local = count($rows) === 1 ? lpp_alias($rows[0],$own) : null;
            lpp_need($local !== null,'history_unknown'); $locals[] = $local;
        }
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    return ['own'=>array_fill_keys(array_merge(lpp_ids(),$d1['ownIds']),true),
        'local'=>array_fill_keys(array_merge($locals,$d1['legacyIds']),true)];
}

/** All batches replan before the first write; no retry after any owner apply call. */
function lpma_execute(array $batches, callable $plan, callable $apply, callable $consume, callable $checkpoint): array {
    $state = ['state'=>'held_before_write','profiles_verified'=>0,'fields_verified'=>0,
        'field_counts'=>[],'batches_verified'=>0,'profile_writes'=>0,'provenance_writes'=>0,
        'readback_verified'=>false,'unknown_batch'=>null];
    $started = false; $number = null;
    try {
        foreach ($batches as $batch) {
            $old = $batch['plan']; $fresh = $plan($old);
            lpm_validate_plan($fresh,$old['contentScope'],$batch['rows']);
            lpp_need(hash_equals($old['planSha256'],$fresh['planSha256']), 'current_plan_drift');
        }
        $consume();
        foreach ($batches as $i=>$batch) {
            $number = $i+1; $started = true; $old = $batch['plan']; $applied = $apply($old);
            $expectedFields = array_sum($batch['fieldCounts']);
            lpp_need(($applied['status'] ?? null) === 'committed_verified'
                && ($applied['operation'] ?? null) === LPMA_OPERATION
                && ($applied['planSha256'] ?? null) === $old['planSha256']
                && ($applied['profilesUpdated'] ?? null) === $batch['profiles']
                && ($applied['profileWrites'] ?? null) === $batch['profiles']
                && ($applied['provenanceWrites'] ?? null) === $batch['profiles']
                && ($applied['fieldsFilled'] ?? null) === $expectedFields
                && ($applied['fieldCounts'] ?? null) === $batch['fieldCounts']
                && ($applied['supplierCalls'] ?? null) === 0
                && ($applied['mappingWrites'] ?? null) === 0 && ($applied['legacyWrites'] ?? null) === 0,
                'apply_readback_contract');
            $state['profiles_verified'] += $batch['profiles']; $state['fields_verified'] += $expectedFields;
            ++$state['batches_verified'];
            foreach ($batch['fieldCounts'] as $field=>$count)
                $state['field_counts'][$field] = ($state['field_counts'][$field] ?? 0)+$count;
            $checkpoint($number,$applied);
        }
        lpp_need($state['profiles_verified'] === LPMA_PROFILES && $state['fields_verified'] === LPMA_FIELDS
            && $state['batches_verified'] === LPMA_BATCHES, 'final_count');
        $state['state'] = 'committed_verified'; $state['readback_verified'] = true;
        $state['profile_writes'] = $state['provenance_writes'] = LPMA_PROFILES;
    } catch (Throwable) {
        if ($started) {
            $state['state'] = 'unknown_no_replay'; $state['unknown_batch'] = $number;
            $state['profile_writes'] = $state['provenance_writes'] = 'unknown';
        }
    }
    ksort($state['field_counts']); return $state;
}

function lpma_main(array $argv): int {
    lpp_need(PHP_SAPI === 'cli' && count($argv) === 2 && $argv[1] === '--apply-retained71','disabled');
    $home = (string)getenv('HOME'); $root = (string)getenv('ANYTOUR_ROOT');
    $dir = (string)getenv('LOCAL_PROFILE_APPLY_DIR'); $head = (string)getenv('LOCAL_PROFILE_SOURCE_SHA');
    $control = (string)getenv('LOCAL_PROFILE_CONTROL_SHA');
    lpp_need($root === $home.'/www/anytoour.ru' && realpath($root) === $root && realpath($dir) === $dir
        && dirname($dir) === $home.'/.anytoour-int-executor' && basename($dir) === LPMA_OPERATION
        && preg_match('/^[a-f0-9]{40}$/D',$head) === 1 && preg_match('/^[a-f0-9]{40}$/D',$control) === 1,'runtime_scope');
    $reservation = json_decode(lpp_file($dir.'/reservation.json',65536),true,32,JSON_THROW_ON_ERROR);
    lpp_need(($reservation['operation_id'] ?? null) === LPMA_OPERATION
        && ($reservation['source_sha'] ?? null) === $head && ($reservation['mode'] ?? null) === 'local-profile-apply-4191','reservation');
    $identity = ['schema_version'=>1,'operation_id'=>LPMA_OPERATION,'batch'=>LPMA_BATCH,
        'source_sha'=>$head,'control_source_sha'=>$control,'plan_source_sha'=>LPMA_PLAN_SOURCE,
        'private_plan_sha256'=>LPMA_PLAN_SHA,'requested_profiles'=>LPMA_PROFILES];
    lpp_save($dir.'/mass-apply-started.json',$identity);
    $parent = $home.'/.anytoour-int-executor/'.LPM_OPERATION;
    $bytes = lpp_file($parent.'/local-mass-plan.json',32*1024*1024);
    lpp_need(hash_equals(LPMA_PLAN_SHA,hash('sha256',$bytes)),'private_plan_digest');
    $index = json_decode($bytes,true,512,JSON_THROW_ON_ERROR); lpma_identity($index);
    $outer = json_decode(lpp_file($parent.'/result.json',65536),true,512,JSON_THROW_ON_ERROR);
    $public = json_decode(lpp_file($parent.'/local-mass-receipt.json',65536),true,512,JSON_THROW_ON_ERROR);
    lpma_identity($public);
    lpp_need(($outer['status'] ?? null) === 'complete' && ($outer['mode'] ?? null) === 'local-profile-plan-4191'
        && ($outer['operation_id'] ?? null) === LPM_OPERATION && ($outer['source_sha'] ?? null) === LPMA_PLAN_SOURCE
        && ($outer['database_writes'] ?? null) === 0 && ($outer['supplier_calls'] ?? null) === 0
        && ($public['state'] ?? null) === 'completed_read_only' && ($public['private_plan_sha256'] ?? null) === LPMA_PLAN_SHA
        && is_array($outer['local_profile_plan'] ?? null)
        && hash_equals(lpm_digest($public),lpm_digest($outer['local_profile_plan'])),'producer_receipt');
    $bootstrap = $root.(is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    lpp_need(is_file($bootstrap) && !is_link($bootstrap) && realpath($bootstrap) === $bootstrap,'bootstrap');
    $_SERVER['DOCUMENT_ROOT'] = $root; require_once $bootstrap;
    require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
    $db = v2_data_db(); lpp_need($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql','mysql_required');
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    $protected = lpma_protected($db,lpp_d1($home));
    $batches = lpma_batches($index,$protected,static fn(string $file): string => lpp_file($parent.'/'.$file,32*1024*1024));
    $owner = new AnyTourProfileEnrichmentV1($db);
    $result = lpma_execute($batches,
        static fn(array $p): array => $owner->plan($p['limit'],$p['demandThrough'],$p['contentScope'],true),
        static fn(array $p): array => $owner->apply(LPMA_OPERATION,$p['limit'],$p['demandThrough'],$p['planSha256'],$p['contentScope'],true),
        static fn() => lpp_save($parent.'/mass-apply71-consumed.json',$identity),
        static fn(int $i,array $applied) => lpp_save($dir.'/'.sprintf('mass-apply-batch-%03d.json',$i),$identity+['owner_receipt'=>$applied]));
    $receipt = $identity+$result+['supplier_calls'=>0,'provider_http_calls'=>0,'mapping_writes'=>0,'legacy_writes'=>0,'schema_writes'=>0];
    lpp_save($dir.'/local-mass-apply-receipt.json',$receipt); echo lpp_json($receipt)."\n";
    return $result['state'] === 'committed_verified' ? 0 : 2;
}
if (PHP_SAPI === 'cli' && realpath((string)($argv[0] ?? '')) === __FILE__) {
    try { exit(lpma_main($argv)); }
    catch (Throwable) { fwrite(STDERR,"local_mass_apply_failed_no_replay\n"); exit(2); }
}
