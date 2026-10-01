<?php
/** Independent retained-first successor: skip predecessor 2000, plan next CURRENT cohort. */
declare(strict_types=1);
require_once __DIR__.'/local_profile_mass_plan_4191.php';

const LPM2_OPERATION = 'int-andromeda-local-profile-mass-plan2-4191-20261002-v1';
const LPM2_BATCH = 'local4191-mass-retained2-20261002';
const LPM2_PARENT_OPERATION = 'int-andromeda-local-profile-mass-plan-4191-20261002-v1';
const LPM2_PARENT_BATCH = 'local4191-mass-retained-20261002';
const LPM2_PARENT_SHA = '0b0a8807bf4b7b07172561537eafe1bc865265ef926c91fa117eed1c9f618426';
const LPM2_PARENT_SOURCE = '5e6797373c61f5b1ad4cb365a18cf15d66526b99';
const LPM2_PARENT_CONTROL = '58af4702bbee0ec584812ce5bf643f60b59df96a';
const LPM2_PARENT_APPLY = 'int-andromeda-local-profile-mass-apply71-4191-20261002-v1';
const LPM2_PARENT_PROFILES = 2000;

/** Pure verifier used by tests; never turns predecessor rows into write authority. */
function lpm2_parent_values(array $private, array $public, array $outer, array $apply): array {
    $exactCounts = [
        'D1_OVERLAP_HELD'=>70,
        'HISTORICAL_366_HELD'=>366,
        'PLAN_BOUND_DEFERRED'=>11142,
        'PRIOR_OR_EDITORIAL_HELD'=>714,
        'RETAINED_DELTA_PREPARED'=>71,
        'SCREENED_FIELDS_PRESENT'=>1707,
        'SOURCE_MISSING'=>1920,
        'SOURCE_PROVENANCE_HELD'=>9,
    ];
    lpp_need(($private['schema_version'] ?? null) === 1
        && ($private['operation_id'] ?? null) === LPM2_PARENT_OPERATION
        && ($private['batch'] ?? null) === LPM2_PARENT_BATCH
        && ($private['source_sha'] ?? null) === LPM2_PARENT_SOURCE
        && ($private['control_source_sha'] ?? null) === LPM2_PARENT_CONTROL
        && ($private['safe_to_apply'] ?? null) === false
        && ($private['active_profiles'] ?? null) === 15999
        && ($private['source_plans_prepared'] ?? null) === LPM2_PARENT_PROFILES
        && ($private['profiles_with_delta'] ?? null) === 71
        && ($private['planned_fields'] ?? null) === 735
        && ($private['classification_counts'] ?? null) === $exactCounts
        && is_array($private['rows'] ?? null) && array_is_list($private['rows']), 'parent_private');
    lpp_need(($public['schema_version'] ?? null) === 1
        && ($public['state'] ?? null) === 'completed_read_only'
        && ($public['operation_id'] ?? null) === LPM2_PARENT_OPERATION
        && ($public['batch'] ?? null) === LPM2_PARENT_BATCH
        && ($public['source_sha'] ?? null) === LPM2_PARENT_SOURCE
        && ($public['control_source_sha'] ?? null) === LPM2_PARENT_CONTROL
        && ($public['private_plan_sha256'] ?? null) === LPM2_PARENT_SHA
        && ($public['source_plans_prepared'] ?? null) === LPM2_PARENT_PROFILES
        && ($public['profiles_with_delta'] ?? null) === 71
        && ($public['planned_fields'] ?? null) === 735
        && ($public['classification_counts'] ?? null) === $exactCounts
        && ($public['provider_http_calls'] ?? null) === 0
        && ($public['database_writes'] ?? null) === 0
        && ($public['profile_writes'] ?? null) === 0
        && ($public['mapping_writes'] ?? null) === 0
        && ($public['schema_writes'] ?? null) === 0
        && ($public['safe_to_apply'] ?? null) === false, 'parent_public');
    lpp_need(($outer['status'] ?? null) === 'complete'
        && ($outer['mode'] ?? null) === 'local-profile-plan-4191'
        && ($outer['operation_id'] ?? null) === LPM2_PARENT_OPERATION
        && ($outer['source_sha'] ?? null) === LPM2_PARENT_SOURCE
        && ($outer['supplier_calls'] ?? null) === 0
        && ($outer['database_writes'] ?? null) === 0
        && is_array($outer['local_profile_plan'] ?? null)
        && lpm_digest($outer['local_profile_plan']) === lpm_digest($public), 'parent_outer');
    lpp_need(($apply['schema_version'] ?? null) === 1
        && ($apply['state'] ?? null) === 'committed_verified'
        && ($apply['operation_id'] ?? null) === LPM2_PARENT_APPLY
        && ($apply['private_plan_sha256'] ?? null) === LPM2_PARENT_SHA
        && ($apply['requested_profiles'] ?? null) === 71
        && ($apply['profiles_verified'] ?? null) === 71
        && ($apply['fields_verified'] ?? null) === 735
        && ($apply['batches_verified'] ?? null) === 4
        && ($apply['profile_writes'] ?? null) === 71
        && ($apply['provenance_writes'] ?? null) === 71
        && ($apply['readback_verified'] ?? null) === true
        && ($apply['unknown_batch'] ?? null) === null
        && ($apply['supplier_calls'] ?? null) === 0
        && ($apply['provider_http_calls'] ?? null) === 0
        && ($apply['mapping_writes'] ?? null) === 0
        && ($apply['legacy_writes'] ?? null) === 0
        && ($apply['schema_writes'] ?? null) === 0, 'parent_apply');
    $own = []; $local = [];
    $plannedStates = ['RETAINED_DELTA_PREPARED'=>true,'SOURCE_MISSING'=>true,'SOURCE_PROVENANCE_HELD'=>true];
    foreach ($private['rows'] as $row) {
        if (!is_array($row) || !isset($plannedStates[$row['state'] ?? ''])) continue;
        $o = $row['anytourHotelId'] ?? null; $l = $row['localHotelId'] ?? null;
        lpp_need(is_int($o) && $o > 0 && is_int($l) && $l > 0
            && !isset($own[$o]) && !isset($local[$l]), 'parent_identity');
        $own[$o] = true; $local[$l] = true;
    }
    lpp_need(count($own) === LPM2_PARENT_PROFILES && count($local) === LPM2_PARENT_PROFILES, 'parent_count');
    return ['state'=>'verified_terminal_predecessor','ownIds'=>array_keys($own),'localIds'=>array_keys($local),
        'privatePlanSha256'=>LPM2_PARENT_SHA,'sourceSha'=>LPM2_PARENT_SOURCE];
}

function lpm2_parent(string $home): array {
    try {
        $dir = $home.'/.anytoour-int-executor/'.LPM2_PARENT_OPERATION;
        $bytes = lpp_file($dir.'/local-mass-plan.json',32*1024*1024);
        lpp_need(hash_equals(LPM2_PARENT_SHA,hash('sha256',$bytes)), 'parent_digest');
        $private = json_decode($bytes,true,512,JSON_THROW_ON_ERROR);
        $public = json_decode(lpp_file($dir.'/local-mass-receipt.json',65536),true,512,JSON_THROW_ON_ERROR);
        $outer = json_decode(lpp_file($dir.'/result.json',65536),true,512,JSON_THROW_ON_ERROR);
        $applyDir = $home.'/.anytoour-int-executor/'.LPM2_PARENT_APPLY;
        $apply = json_decode(lpp_file($applyDir.'/local-mass-apply-receipt.json',65536),true,512,JSON_THROW_ON_ERROR);
        lpp_need(is_array($private)&&is_array($public)&&is_array($outer)&&is_array($apply), 'parent_json');
        return lpm2_parent_values($private,$public,$outer,$apply);
    } catch (Throwable) {
        return ['state'=>'unknown_held','ownIds'=>[],'localIds'=>[]];
    }
}

/** Pre-mark predecessor identities, then reuse the proven mass planner unchanged. */
function lpm2_prepare(array $snapshot, array $d1, array $parent, callable $plan, callable $save): array {
    $rows = $snapshot['rows'];
    if (($parent['state'] ?? null) !== 'verified_terminal_predecessor') {
        foreach ($rows as &$row) if (($row['state'] ?? null) === 'CANDIDATE') $row['state'] = 'PREDECESSOR_UNKNOWN_HELD';
        unset($row);
    } else {
        $own = array_fill_keys($parent['ownIds'],true); $local = array_fill_keys($parent['localIds'],true);
        foreach ($rows as $id=>&$row) {
            $localId = $row['localHotelId'] ?? 0;
            if (isset($own[$id]) || isset($local[$localId])) $row['state'] = 'PREDECESSOR_2000_HELD';
        }
        unset($row);
    }
    $snapshot['rows'] = $rows;
    return lpm_prepare($snapshot,$d1,$plan,$save);
}

function lpm2_main(array $argv): int {
    lpp_need(PHP_SAPI === 'cli' && count($argv) === 2 && $argv[1] === '--plan-only','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('LOCAL_PROFILE_PLAN_DIR');
    $head=(string)getenv('LOCAL_PROFILE_SOURCE_SHA');$control=(string)getenv('LOCAL_PROFILE_CONTROL_SHA');
    $home=(string)getenv('HOME');
    lpp_need($root===$home.'/www/anytoour.ru'&&realpath($root)===$root&&realpath($dir)===$dir
        &&dirname($dir)===$home.'/.anytoour-int-executor'&&basename($dir)===LPM2_OPERATION
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1&&preg_match('/^[a-f0-9]{40}$/D',$control)===1,'runtime_scope');
    $reservation=json_decode(lpp_file($dir.'/reservation.json',65536),true,32,JSON_THROW_ON_ERROR);
    lpp_need(($reservation['operation_id']??null)===LPM2_OPERATION&&($reservation['source_sha']??null)===$head
        &&($reservation['mode']??null)==='local-profile-plan-4191','reservation');
    foreach(['local-mass2-started.json','local-mass2-plan.json','local-mass2-receipt.json'] as $file)
        lpp_need(!file_exists($dir.'/'.$file)&&!is_link($dir.'/'.$file),'no_replay');
    lpp_save($dir.'/local-mass2-started.json',['operation_id'=>LPM2_OPERATION,'source_sha'=>$head]);
    $parent=lpm2_parent($home);$d1=lpp_d1($home);
    $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    lpp_need(is_file($bootstrap)&&!is_link($bootstrap)&&realpath($bootstrap)===$bootstrap,'bootstrap');
    $_SERVER['DOCUMENT_ROOT']=$root;require_once $bootstrap;
    require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
    $db=v2_data_db();lpp_need($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','mysql_required');
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    lpp_need(AnyTourProfileEnrichmentV1::MAX_BATCH===LPP_OWNER_MAX_BATCH,'owner_batch_limit_drift');
    $through=gmdate('Y-m-d H:i:s');$snapshot=lpm_snapshot($db,$through);$owner=new AnyTourProfileEnrichmentV1($db);
    $identity=['schema_version'=>1,'batch'=>LPM2_BATCH,'operation_id'=>LPM2_OPERATION,'source_sha'=>$head,
        'control_source_sha'=>$control,'demand_through'=>$through,'predecessor_private_plan_sha256'=>LPM2_PARENT_SHA];
    $audit=lpm2_prepare($snapshot,$d1,$parent,
        static fn(array $scope):array=>$owner->plan(count($scope),$through,$scope,true),
        static function(int $index,array $plan)use($dir,$identity):array{
            $file=sprintf('mass2-batch-%03d.json',$index);
            $digest=lpp_save($dir.'/'.$file,$identity+['owner_plan'=>$plan,'safe_to_apply'=>false]);
            return ['file'=>$file,'sha256'=>$digest,'profiles'=>count($plan['selected']),
                'scope_profiles'=>$plan['limit'],'plan_sha256'=>$plan['planSha256']];
        });
    $private=$identity+['predecessor_exclusion'=>$parent,'d1_exclusion'=>$d1,'history_exclusion'=>$snapshot['history']]+$audit;
    $digest=lpp_save($dir.'/local-mass2-plan.json',$private);
    $receipt=$identity+array_intersect_key($audit,array_flip(['active_profiles','census_complete','core_fields_present',
        'missing_field_counts','eligible_profiles','source_plans_prepared','profiles_with_delta','planned_fields',
        'classification_counts','safe_to_apply']))+[
        'state'=>'completed_read_only','predecessor_exclusion_state'=>$parent['state'],
        'predecessor_excluded_profiles'=>($parent['state']==='verified_terminal_predecessor'?LPM2_PARENT_PROFILES:0),
        'd1_exclusion_state'=>$d1['state'],'history_exclusion_state'=>$snapshot['history']['state'],
        'ready_batches'=>count($audit['batches']),'private_plan_sha256'=>$digest,'provider_http_calls'=>0,
        'database_writes'=>0,'profile_writes'=>0,'mapping_writes'=>0,'schema_writes'=>0];
    lpp_save($dir.'/local-mass2-receipt.json',$receipt);echo lpp_json($receipt)."\n";return 0;
}
if(PHP_SAPI==='cli'&&realpath((string)($argv[0]??''))===__FILE__){
    try{exit(lpm2_main($argv));}catch(Throwable){fwrite(STDERR,"local_profile_mass2_plan_failed_no_replay\n");exit(2);}
}
