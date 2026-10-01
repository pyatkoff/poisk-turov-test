<?php
/** New independent read-only census, through the existing LOCAL executor/owner. */
declare(strict_types=1);
require_once __DIR__.'/local_profile_plan_4191.php';

const LPM_OPERATION = 'int-andromeda-local-profile-mass-plan-4191-20261002-v1';
const LPM_BATCH = 'local4191-mass-retained-20261002';
const LPM_MAX_CENSUS = 30000;
const LPM_MAX_PLAN_PROFILES = 2000;

function lpm_stable(mixed $value): mixed {
    if (!is_array($value)) return $value;
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    foreach ($value as $key=>$part) $value[$key] = lpm_stable($part);
    return $value;
}
function lpm_digest(array $value): string { return hash('sha256', lpp_json(lpm_stable($value))); }
function lpm_missing(mixed $value): bool {
    return $value === null || (is_string($value) && trim($value) === '') || $value === [];
}
/** Screening is not content acceptance. Nonempty fields never enter this fill scope. */
function lpm_describe(array $row): array {
    $own = (int)$row['id'];
    $out = ['anytourHotelId'=>$own,'state'=>'ALIAS_HELD','missingFields'=>[],
        'coreFieldsPresent'=>false,'safeToApply'=>false];
    if ((int)($row['alias_count'] ?? 0) !== 1) return $out;
    $local = lpp_alias($row, $own);
    if ($local === null) return $out;
    $out['localHotelId'] = $local;
    if (!lpp_profile_valid($row)) { $out['state'] = 'PROFILE_INTEGRITY_HELD'; return $out; }
    $profile = json_decode($row['profile_json'], true, 512, JSON_THROW_ON_ERROR);
    foreach (LPP_FIELDS as $field) {
        $parts = explode('.', $field);
        $value = $profile;
        foreach ($parts as $part) $value = is_array($value) ? ($value[$part] ?? null) : null;
        if (lpm_missing($value)) $out['missingFields'][] = $field;
    }
    sort($out['missingFields'], SORT_STRING);
    $out['coreFieldsPresent'] = array_intersect(['description','primaryImage','images'], $out['missingFields']) === [];
    $out += ['expectedRevision'=>(int)$row['revision'], 'expectedProfileSha256'=>$row['profile_sha256'],
        'expectedAliasSha256'=>$row['alias_source_sha256'],
        'userSearches'=>(int)($row['user_search_count'] ?? 0),
        'observations'=>(int)($row['observation_count'] ?? 0),
        'lastSeenAt'=>(string)($row['demand_last_seen_at'] ?? '')];
    $out['state'] = (int)$row['revision'] !== 1 || (int)($row['prior_content_operations'] ?? 0) !== 0
        ? 'PRIOR_OR_EDITORIAL_HELD' : ($out['missingFields'] === [] ? 'SCREENED_FIELDS_PRESENT' : 'CANDIDATE');
    return $out;
}

/** All counts/alias exclusions are read from ONE read-only snapshot. No HTTP audit. */
function lpm_snapshot(PDO $db, string $through): array {
    lpp_need(!$db->inTransaction(), 'caller_transaction');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
        $total = (int)$db->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn();
        lpp_need($total <= LPM_MAX_CENSUS, 'catalogue_bound_not_complete');
        $prior = lpm_historical_rows_in_snapshot($db);
        $history = ['state'=>'verified','ownIds'=>lpp_ids(),'localIds'=>[]];
        foreach (lpp_ids() as $own) {
            $rows = $prior[$own] ?? [];
            $local = count($rows) === 1 ? lpp_alias($rows[0], $own) : null;
            if ($local === null) $history['state'] = 'unknown_held';
            else $history['localIds'][] = $local;
        }
        $demandQuery = $db->prepare("SELECT hotel_id,SUM(source='user_search') AS user_search_count,
            COUNT(*) AS observation_count,MAX(observed_at) AS demand_last_seen_at
            FROM tour_price_observations WHERE observed_at<=:through GROUP BY hotel_id");
        $demandQuery->execute(['through'=>$through]); $demand = [];
        while ($entry = $demandQuery->fetch(PDO::FETCH_ASSOC)) {
            lpp_need(count($demand) < 250000,'demand_bound');
            $demand[(int)$entry['hotel_id']] = $entry;
        }
        $demandQuery->closeCursor();
        $q = $db->prepare("SELECT h.id,h.revision,h.profile_json,h.profile_sha256,
            COALESCE(a.alias_count,0) AS alias_count,s.external_key AS local_id,
            s.acquired_via AS alias_acquired_via,s.source_json AS alias_source_json,
            s.source_sha256 AS alias_source_sha256,
            COALESCE(p.n,0) AS prior_content_operations
            FROM anytour_hotels h
            LEFT JOIN (SELECT anytour_hotel_id,COUNT(*) AS alias_count,MIN(id) AS alias_id
                FROM anytour_hotel_sources WHERE namespace='anytour_local_id' GROUP BY anytour_hotel_id) a
                ON a.anytour_hotel_id=h.id
            LEFT JOIN anytour_hotel_sources s ON s.id=a.alias_id
            LEFT JOIN (SELECT anytour_hotel_id,COUNT(*) AS n FROM anytour_hotel_sources
                WHERE namespace IN ('profile_enrichment:legacy_saved_v1','profile_sync:retained_tv_v1')
                GROUP BY anytour_hotel_id) p ON p.anytour_hotel_id=h.id
            WHERE h.is_active=1 AND h.id>:after_id ORDER BY h.id LIMIT 250");
        $items = []; $after = 0;
        do {
            $q->execute(['after_id'=>$after]); $pageCount = 0;
            while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                $own = (int)$row['id'];
                lpp_need($own > $after && !isset($items[$own]) && count($items) < LPM_MAX_CENSUS, 'census_identity');
                $items[$own] = lpm_describe($row+($demand[(int)($row['local_id'] ?? 0)] ?? []));
                $after = $own; ++$pageCount;
            }
            $q->closeCursor();
        } while ($pageCount === 250);
        lpp_need(count($items) === $total, 'census_count');
        $db->commit();
        return ['activeProfiles'=>$total,'rows'=>$items,'history'=>$history];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
/** Historical roster is reused ONLY as a read-only exclusion list. */
function lpm_historical_rows_in_snapshot(PDO $db): array {
    $ids = lpp_ids(); $marks = implode(',', array_fill(0, count($ids), '?'));
    $q = $db->prepare("SELECT h.id,s.external_key AS local_id,s.acquired_via AS alias_acquired_via,
        s.source_json AS alias_source_json,s.source_sha256 AS alias_source_sha256
        FROM anytour_hotels h LEFT JOIN anytour_hotel_sources s
        ON s.anytour_hotel_id=h.id AND s.namespace='anytour_local_id'
        WHERE h.id IN ($marks) ORDER BY h.id,s.id");
    $q->execute($ids); $out = [];
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) $out[(int)$row['id']][] = $row;
    return $out;
}

/** Verify the existing owner's exact scope, hash and current before-images. */
function lpm_validate_plan(array $candidate, array $scope, array $rows): array {
    $n = count($scope);
    lpp_need(($candidate['status'] ?? null) === 'prepared_read_only'
        && ($candidate['writes'] ?? null) === 0 && ($candidate['supplierCalls'] ?? null) === 0
        && ($candidate['limit'] ?? null) === $n && ($candidate['activeProfiles'] ?? null) === $n
        && ($candidate['scannedProfiles'] ?? null) === $n
        && ($candidate['contentPolicy'] ?? null) === 'sync_imported_retained_tv_v1'
        && ($candidate['contentScope'] ?? null) === $scope
        && is_array($candidate['selected'] ?? null) && array_is_list($candidate['selected'])
        && is_array($candidate['held'] ?? null), 'owner_plan_contract');
    $core = $candidate; unset($core['status'],$core['writes'],$core['supplierCalls'],$core['planSha256']);
    lpp_need(is_string($candidate['planSha256'] ?? null)
        && hash_equals(lpm_digest($core), $candidate['planSha256']), 'owner_plan_digest');
    $targets = array_column($scope, null, 'anytourHotelId'); $selected = [];
    foreach ($candidate['selected'] as $item) {
        $own = $item['anytourHotelId'] ?? null;
        lpp_need(is_int($own) && isset($targets[$own]) && !isset($selected[$own]), 'owner_plan_identity');
        $row = $rows[$own];
        foreach (['localHotelId','expectedRevision','expectedProfileSha256','expectedAliasSha256'] as $key)
            lpp_need(($item[$key] ?? null) === $row[$key], 'current_drift');
        lpp_need(is_string($item['beforeProfileJson'] ?? null)
            && hash_equals($row['expectedProfileSha256'], hash('sha256',$item['beforeProfileJson']))
            && is_array($item['patch'] ?? null) && $item['patch'] !== []
            && array_diff(array_keys($item['patch']),$targets[$own]['fields']) === [], 'owner_patch_scope');
        foreach ($item['patch'] as $value) lpp_need(!lpm_missing($value), 'empty_patch');
        $selected[$own] = $item;
    }
    foreach ($candidate['held'] as $own=>$reasons)
        lpp_need(isset($targets[$own]) && is_array($reasons), 'owner_held_identity');
    return $selected;
}

/** Owner callback is invoked only AFTER all exclusions, in bounded demand order. */
function lpm_prepare(array $snapshot, array $d1, callable $plan, callable $save): array {
    $rows = $snapshot['rows']; $history = $snapshot['history']; $eligible = [];
    $oldOwn = array_fill_keys(lpp_ids(), true);
    $oldLocal = array_fill_keys($history['localIds'], true);
    $d1Own = array_fill_keys($d1['ownIds'], true); $d1Local = array_fill_keys($d1['legacyIds'], true);
    foreach ($rows as $own=>&$row) {
        $local = $row['localHotelId'] ?? 0;
        if (isset($oldOwn[$own]) || isset($oldLocal[$local])) $row['state'] = 'HISTORICAL_366_HELD';
        elseif (($d1['state'] ?? null) !== 'verified_terminal_manifest') $row['state'] = 'D1_MANIFEST_UNKNOWN_HELD';
        elseif (isset($d1Own[$own]) || isset($d1Local[$local])) $row['state'] = 'D1_OVERLAP_HELD';
        elseif ($history['state'] !== 'verified') $row['state'] = 'HISTORY_UNKNOWN_HELD';
        elseif ($row['state'] === 'CANDIDATE') $eligible[$own] = $row;
    }
    unset($row);
    uasort($eligible, static fn(array $a,array $b): int =>
        ($b['userSearches'] <=> $a['userSearches']) ?: ($b['observations'] <=> $a['observations'])
        ?: strcmp($b['lastSeenAt'],$a['lastSeenAt']) ?: ($a['anytourHotelId'] <=> $b['anytourHotelId']));
    $eligibleCount = count($eligible);
    foreach (array_slice($eligible,LPM_MAX_PLAN_PROFILES,null,true) as $own=>$row) $rows[$own]['state'] = 'PLAN_BOUND_DEFERRED';
    $eligible = array_slice($eligible,0,LPM_MAX_PLAN_PROFILES,true);
    $batches = []; $prepared = 0; $fields = 0;
    foreach (array_chunk($eligible,LPP_OWNER_MAX_BATCH,true) as $chunk) {
        ksort($chunk,SORT_NUMERIC); $scope = [];
        foreach ($chunk as $own=>$row) $scope[] = ['anytourHotelId'=>$own,
            'localHotelId'=>$row['localHotelId'],'fields'=>$row['missingFields']];
        try {
            $candidate = $plan($scope);
            $selected = lpm_validate_plan($candidate,$scope,$rows);
        } catch (Throwable) {
            foreach ($chunk as $own=>$row) $rows[$own]['state'] = 'OWNER_PLAN_HELD';
            continue; // No retries and no public exception/source text.
        }
        $prepared += count($scope);
        foreach ($chunk as $own=>$row) {
            $reasons = array_values($candidate['held'][$own] ?? []);
            $rows[$own]['state'] = isset($selected[$own]) ? 'RETAINED_DELTA_PREPARED'
                : ($reasons === [] ? 'NO_DELTA_NOT_PROVEN_COMPLETE'
                : (array_diff($reasons,['SYNC_FULL_CARD_UNAVAILABLE','SYNC_SAVED_PROFILE_UNAVAILABLE','source_missing_preserved']) === []
                    ? 'SOURCE_MISSING' : 'SOURCE_PROVENANCE_HELD'));
            if (isset($selected[$own])) $fields += count($selected[$own]['patch']);
        }
        if ($selected !== []) {
            // The entire exact owner scope/hash is sealed; never reconstruct it from counters.
            $batches[] = $save(count($batches)+1,$candidate);
        }
    }
    $counts = []; $corePresent = 0; $missingCounts = [];
    foreach ($rows as $row) {
        $counts[$row['state']] = ($counts[$row['state']] ?? 0)+1;
        if ($row['coreFieldsPresent']) ++$corePresent;
        foreach ($row['missingFields'] as $field) $missingCounts[$field] = ($missingCounts[$field] ?? 0)+1;
    }
    ksort($counts); ksort($missingCounts);
    return ['rows'=>array_values($rows),'classification_counts'=>$counts,
        'active_profiles'=>$snapshot['activeProfiles'],'census_complete'=>true,
        'core_fields_present'=>$corePresent,'missing_field_counts'=>$missingCounts,
        'eligible_profiles'=>$eligibleCount,'source_plans_prepared'=>$prepared,
        'profiles_with_delta'=>$counts['RETAINED_DELTA_PREPARED'] ?? 0,
        'planned_fields'=>$fields,'batches'=>$batches,'safe_to_apply'=>false];
}

function lpm_main(array $argv): int {
    lpp_need(PHP_SAPI === 'cli' && count($argv) === 2 && $argv[1] === '--plan-only','disabled');
    $home = (string)getenv('HOME'); $root = (string)getenv('ANYTOUR_ROOT');
    $dir = (string)getenv('LOCAL_PROFILE_PLAN_DIR'); $head = (string)getenv('LOCAL_PROFILE_SOURCE_SHA');
    $control = (string)getenv('LOCAL_PROFILE_CONTROL_SHA');
    lpp_need($root === $home.'/www/anytoour.ru' && realpath($root) === $root
        && realpath($dir) === $dir && dirname($dir) === $home.'/.anytoour-int-executor'
        && basename($dir) === LPM_OPERATION && preg_match('/^[a-f0-9]{40}$/D',$head) === 1
        && preg_match('/^[a-f0-9]{40}$/D',$control) === 1,'runtime_scope');
    $reservation = json_decode(lpp_file($dir.'/reservation.json',65536),true,32,JSON_THROW_ON_ERROR);
    lpp_need(($reservation['operation_id'] ?? null) === LPM_OPERATION
        && ($reservation['source_sha'] ?? null) === $head
        && ($reservation['mode'] ?? null) === 'local-profile-plan-4191','reservation');
    foreach (['local-mass-started.json','local-mass-plan.json','local-mass-receipt.json'] as $file)
        lpp_need(!file_exists($dir.'/'.$file) && !is_link($dir.'/'.$file),'no_replay');
    lpp_save($dir.'/local-mass-started.json',['operation_id'=>LPM_OPERATION,'source_sha'=>$head]);
    $d1 = lpp_d1($home);
    $bootstrap = $root.(is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    lpp_need(is_file($bootstrap) && !is_link($bootstrap) && realpath($bootstrap) === $bootstrap,'bootstrap');
    $_SERVER['DOCUMENT_ROOT'] = $root; require_once $bootstrap;
    require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
    $db = v2_data_db(); lpp_need($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql','mysql_required');
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    lpp_need(AnyTourProfileEnrichmentV1::MAX_BATCH === LPP_OWNER_MAX_BATCH,'owner_batch_limit_drift');
    $through = gmdate('Y-m-d H:i:s'); $snapshot = lpm_snapshot($db,$through);
    $owner = new AnyTourProfileEnrichmentV1($db);
    $identity = ['schema_version'=>1,'operation_id'=>LPM_OPERATION,'batch'=>LPM_BATCH,
        'source_sha'=>$head,'control_source_sha'=>$control,'demand_through'=>$through];
    $audit = lpm_prepare($snapshot,$d1,
        static fn(array $scope): array => $owner->plan(count($scope),$through,$scope,true),
        static function(int $index,array $plan) use ($dir,$identity): array {
            $file = sprintf('mass-batch-%03d.json',$index);
            $digest = lpp_save($dir.'/'.$file,$identity+['owner_plan'=>$plan,'safe_to_apply'=>false]);
            return ['file'=>$file,'sha256'=>$digest,'profiles'=>count($plan['selected']),
                'scope_profiles'=>$plan['limit'],'plan_sha256'=>$plan['planSha256']];
        });
    $private = $identity+['d1_exclusion'=>$d1,'history_exclusion'=>$snapshot['history']]+$audit;
    $digest = lpp_save($dir.'/local-mass-plan.json',$private);
    $receipt = $identity+array_intersect_key($audit,array_flip(['active_profiles','census_complete',
        'core_fields_present','missing_field_counts','eligible_profiles','source_plans_prepared',
        'profiles_with_delta','planned_fields','classification_counts','safe_to_apply']))+
        ['state'=>'completed_read_only','d1_exclusion_state'=>$d1['state'],
        'history_exclusion_state'=>$snapshot['history']['state'],'ready_batches'=>count($audit['batches']),
        'private_plan_sha256'=>$digest,'provider_http_calls'=>0,'database_writes'=>0,
        'profile_writes'=>0,'mapping_writes'=>0,'schema_writes'=>0];
    lpp_save($dir.'/local-mass-receipt.json',$receipt);
    echo lpp_json($receipt)."\n"; return 0;
}
if (PHP_SAPI === 'cli' && realpath((string)($argv[0] ?? '')) === __FILE__) {
    try { exit(lpm_main($argv)); }
    catch (Throwable) { fwrite(STDERR,"local_profile_mass_plan_failed_no_replay\n"); exit(2); }
}
