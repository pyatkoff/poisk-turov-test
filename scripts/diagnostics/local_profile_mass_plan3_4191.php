<?php
/** Phase3 retained-first successor: skip exact source-planned4000, plan next CURRENT cohort. */
declare(strict_types=1);
require_once __DIR__.'/local_profile_mass_plan2_4191.php';
require_once __DIR__.'/local_profile_mass_apply2_4191.php';

const LPM3_OPERATION='int-andromeda-local-profile-mass-plan3-4191-20261002-v1';
const LPM3_BATCH='local4191-mass-retained3-20261002';
const LPM3_PARENT2_OPERATION='int-andromeda-local-profile-mass-plan2-4191-20261002-v1';
const LPM3_PARENT2_BATCH='local4191-mass-retained2-20261002';
const LPM3_PARENT2_SHA='aafbc0aa015d485817ae9d851a6200f677488ea5538ab73447f2ee1dc67c84e1';
const LPM3_PARENT2_SOURCE='a54255507643501abdeca150aeae19b84cb586f6';
const LPM3_PARENT2_CONTROL='00cc9b3ba28319c85282a993c6ca0d57558b604e';
const LPM3_PARENT2_APPLY='int-andromeda-local-profile-mass-apply130-phase2-4191-20261002-v1';
const LPM3_PARENT2_PROFILES=2000;

function lpm3_parent2_values(array $private,array $public,array $outer,array $apply):array{
    $counts=[
        'D1_OVERLAP_HELD'=>70,'HISTORICAL_366_HELD'=>366,'PLAN_BOUND_DEFERRED'=>9142,
        'PREDECESSOR_2000_HELD'=>2000,'PRIOR_OR_EDITORIAL_HELD'=>714,
        'RETAINED_DELTA_PREPARED'=>130,'SCREENED_FIELDS_PRESENT'=>1707,
        'SOURCE_MISSING'=>1868,'SOURCE_PROVENANCE_HELD'=>2,
    ];
    lpp_need(($private['schema_version']??null)===1
        &&($private['operation_id']??null)===LPM3_PARENT2_OPERATION
        &&($private['batch']??null)===LPM3_PARENT2_BATCH
        &&($private['source_sha']??null)===LPM3_PARENT2_SOURCE
        &&($private['control_source_sha']??null)===LPM3_PARENT2_CONTROL
        &&($private['predecessor_private_plan_sha256']??null)===LPM2_PARENT_SHA
        &&($private['safe_to_apply']??null)===false
        &&($private['active_profiles']??null)===15999
        &&($private['source_plans_prepared']??null)===LPM3_PARENT2_PROFILES
        &&($private['profiles_with_delta']??null)===130
        &&($private['planned_fields']??null)===1312
        &&($private['classification_counts']??null)===$counts
        &&is_array($private['rows']??null)&&array_is_list($private['rows']),'parent2_private');
    lpp_need(($public['schema_version']??null)===1&&($public['state']??null)==='completed_read_only'
        &&($public['operation_id']??null)===LPM3_PARENT2_OPERATION
        &&($public['batch']??null)===LPM3_PARENT2_BATCH
        &&($public['source_sha']??null)===LPM3_PARENT2_SOURCE
        &&($public['control_source_sha']??null)===LPM3_PARENT2_CONTROL
        &&($public['predecessor_private_plan_sha256']??null)===LPM2_PARENT_SHA
        &&($public['private_plan_sha256']??null)===LPM3_PARENT2_SHA
        &&($public['predecessor_exclusion_state']??null)==='verified_terminal_predecessor'
        &&($public['predecessor_excluded_profiles']??null)===2000
        &&($public['source_plans_prepared']??null)===LPM3_PARENT2_PROFILES
        &&($public['profiles_with_delta']??null)===130&&($public['planned_fields']??null)===1312
        &&($public['classification_counts']??null)===$counts&&($public['safe_to_apply']??null)===false
        &&($public['provider_http_calls']??null)===0&&($public['database_writes']??null)===0
        &&($public['profile_writes']??null)===0&&($public['mapping_writes']??null)===0&&($public['schema_writes']??null)===0,
        'parent2_public');
    lpp_need(($outer['status']??null)==='complete'&&($outer['mode']??null)==='local-profile-plan-4191'
        &&($outer['operation_id']??null)===LPM3_PARENT2_OPERATION&&($outer['source_sha']??null)===LPM3_PARENT2_SOURCE
        &&($outer['supplier_calls']??null)===0&&($outer['database_writes']??null)===0
        &&is_array($outer['local_profile_plan']??null)&&lpm_digest($outer['local_profile_plan'])===lpm_digest($public),
        'parent2_outer');
    lpp_need(($apply['schema_version']??null)===1&&($apply['state']??null)==='committed_verified'
        &&($apply['operation_id']??null)===LPM3_PARENT2_APPLY&&($apply['private_plan_sha256']??null)===LPM3_PARENT2_SHA
        &&($apply['requested_profiles']??null)===130&&($apply['profiles_verified']??null)===130
        &&($apply['fields_verified']??null)===1312&&($apply['batches_verified']??null)===3
        &&($apply['profile_writes']??null)===130&&($apply['provenance_writes']??null)===130
        &&($apply['readback_verified']??null)===true&&($apply['unknown_batch']??null)===null
        &&($apply['supplier_calls']??null)===0&&($apply['provider_http_calls']??null)===0
        &&($apply['mapping_writes']??null)===0&&($apply['legacy_writes']??null)===0&&($apply['schema_writes']??null)===0,
        'parent2_apply');
    $own=[];$local=[];$planned=['RETAINED_DELTA_PREPARED'=>true,'SOURCE_MISSING'=>true,'SOURCE_PROVENANCE_HELD'=>true];
    foreach($private['rows'] as $row){
        if(!is_array($row)||!isset($planned[$row['state']??'']))continue;
        $o=$row['anytourHotelId']??null;$l=$row['localHotelId']??null;
        lpp_need(is_int($o)&&$o>0&&is_int($l)&&$l>0&&!isset($own[$o])&&!isset($local[$l]),'parent2_identity');
        $own[$o]=true;$local[$l]=true;
    }
    lpp_need(count($own)===LPM3_PARENT2_PROFILES&&count($local)===LPM3_PARENT2_PROFILES,'parent2_count');
    return ['state'=>'verified_terminal_predecessor2','ownIds'=>array_keys($own),'localIds'=>array_keys($local),
        'privatePlanSha256'=>LPM3_PARENT2_SHA,'sourceSha'=>LPM3_PARENT2_SOURCE];
}
function lpm3_parent2(string $home):array{
    try{
        $dir=$home.'/.anytoour-int-executor/'.LPM3_PARENT2_OPERATION;
        $bytes=lpp_file($dir.'/local-mass2-plan.json',32*1024*1024);
        lpp_need(hash_equals(LPM3_PARENT2_SHA,hash('sha256',$bytes)),'parent2_digest');
        $private=json_decode($bytes,true,512,JSON_THROW_ON_ERROR);
        $public=json_decode(lpp_file($dir.'/local-mass2-receipt.json',65536),true,512,JSON_THROW_ON_ERROR);
        $outer=json_decode(lpp_file($dir.'/result.json',65536),true,512,JSON_THROW_ON_ERROR);
        $applyDir=$home.'/.anytoour-int-executor/'.LPM3_PARENT2_APPLY;
        $apply=json_decode(lpp_file($applyDir.'/local-mass2-apply-receipt.json',65536),true,512,JSON_THROW_ON_ERROR);
        lpp_need(is_array($private)&&is_array($public)&&is_array($outer)&&is_array($apply),'parent2_json');
        return lpm3_parent2_values($private,$public,$outer,$apply);
    }catch(Throwable){return ['state'=>'unknown_held','ownIds'=>[],'localIds'=>[]];}
}
function lpm3_prepare(array $snapshot,array $d1,array $parent1,array $parent2,callable $plan,callable $save):array{
    $rows=$snapshot['rows'];
    if(($parent2['state']??null)!=='verified_terminal_predecessor2'){
        foreach($rows as &$row)if(($row['state']??null)==='CANDIDATE')$row['state']='PREDECESSOR2_UNKNOWN_HELD';unset($row);
    }else{
        $own=array_fill_keys($parent2['ownIds'],true);$local=array_fill_keys($parent2['localIds'],true);
        foreach($rows as $id=>&$row){$lid=$row['localHotelId']??0;if(isset($own[$id])||isset($local[$lid]))$row['state']='PREDECESSOR2_2000_HELD';}unset($row);
    }
    $snapshot['rows']=$rows;
    return lpm2_prepare($snapshot,$d1,$parent1,$plan,$save);
}
function lpm3_main(array $argv):int{
    lpp_need(PHP_SAPI==='cli'&&count($argv)===2&&$argv[1]==='--plan-only','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('LOCAL_PROFILE_PLAN_DIR');
    $head=(string)getenv('LOCAL_PROFILE_SOURCE_SHA');$control=(string)getenv('LOCAL_PROFILE_CONTROL_SHA');$home=(string)getenv('HOME');
    lpp_need($root===$home.'/www/anytoour.ru'&&realpath($root)===$root&&realpath($dir)===$dir
        &&dirname($dir)===$home.'/.anytoour-int-executor'&&basename($dir)===LPM3_OPERATION
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1&&preg_match('/^[a-f0-9]{40}$/D',$control)===1,'runtime_scope');
    $reservation=json_decode(lpp_file($dir.'/reservation.json',65536),true,32,JSON_THROW_ON_ERROR);
    lpp_need(($reservation['operation_id']??null)===LPM3_OPERATION&&($reservation['source_sha']??null)===$head
        &&($reservation['mode']??null)==='local-profile-plan-4191','reservation');
    foreach(['local-mass3-started.json','local-mass3-plan.json','local-mass3-receipt.json'] as $file)
        lpp_need(!file_exists($dir.'/'.$file)&&!is_link($dir.'/'.$file),'no_replay');
    lpp_save($dir.'/local-mass3-started.json',['operation_id'=>LPM3_OPERATION,'source_sha'=>$head]);
    $parent1=lpm2_parent($home);$parent2=lpm3_parent2($home);$d1=lpp_d1($home);
    $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    lpp_need(is_file($bootstrap)&&!is_link($bootstrap)&&realpath($bootstrap)===$bootstrap,'bootstrap');
    $_SERVER['DOCUMENT_ROOT']=$root;require_once $bootstrap;require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
    $db=v2_data_db();lpp_need($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','mysql_required');
    $db->exec('SET SESSION TRANSACTION READ ONLY');lpp_need(AnyTourProfileEnrichmentV1::MAX_BATCH===LPP_OWNER_MAX_BATCH,'owner_batch_limit_drift');
    $through=gmdate('Y-m-d H:i:s');$snapshot=lpm_snapshot($db,$through);$owner=new AnyTourProfileEnrichmentV1($db);
    $identity=['schema_version'=>1,'batch'=>LPM3_BATCH,'operation_id'=>LPM3_OPERATION,'source_sha'=>$head,'control_source_sha'=>$control,
        'demand_through'=>$through,'predecessor_private_plan_sha256'=>LPM2_PARENT_SHA,
        'predecessor2_private_plan_sha256'=>LPM3_PARENT2_SHA];
    $audit=lpm3_prepare($snapshot,$d1,$parent1,$parent2,
        static fn(array $scope):array=>$owner->plan(count($scope),$through,$scope,true),
        static function(int $index,array $plan)use($dir,$identity):array{
            $file=sprintf('mass3-batch-%03d.json',$index);
            $digest=lpp_save($dir.'/'.$file,$identity+['owner_plan'=>$plan,'safe_to_apply'=>false]);
            return ['file'=>$file,'sha256'=>$digest,'profiles'=>count($plan['selected']),
                'scope_profiles'=>$plan['limit'],'plan_sha256'=>$plan['planSha256']];
        });
    $private=$identity+['predecessor_exclusion'=>$parent1,'predecessor2_exclusion'=>$parent2,
        'd1_exclusion'=>$d1,'history_exclusion'=>$snapshot['history']]+$audit;
    $digest=lpp_save($dir.'/local-mass3-plan.json',$private);
    $receipt=$identity+array_intersect_key($audit,array_flip(['active_profiles','census_complete','core_fields_present',
        'missing_field_counts','eligible_profiles','source_plans_prepared','profiles_with_delta','planned_fields',
        'classification_counts','safe_to_apply']))+[
        'state'=>'completed_read_only','predecessor_exclusion_state'=>$parent1['state'],
        'predecessor_excluded_profiles'=>($parent1['state']==='verified_terminal_predecessor'?LPM2_PARENT_PROFILES:0),
        'predecessor2_exclusion_state'=>$parent2['state'],
        'predecessor2_excluded_profiles'=>($parent2['state']==='verified_terminal_predecessor2'?LPM3_PARENT2_PROFILES:0),
        'd1_exclusion_state'=>$d1['state'],'history_exclusion_state'=>$snapshot['history']['state'],
        'ready_batches'=>count($audit['batches']),'private_plan_sha256'=>$digest,'provider_http_calls'=>0,
        'database_writes'=>0,'profile_writes'=>0,'mapping_writes'=>0,'schema_writes'=>0];
    lpp_save($dir.'/local-mass3-receipt.json',$receipt);echo lpp_json($receipt)."\n";return 0;
}
if(PHP_SAPI==='cli'&&realpath((string)($argv[0]??''))===__FILE__){
    try{exit(lpm3_main($argv));}catch(Throwable){fwrite(STDERR,"local_profile_mass3_plan_failed_no_replay\n");exit(2);}
}
