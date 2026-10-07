<?php
/** Separate read-only recovery plan after the sealed phase3 UNKNOWN/no-replay inspection. */
declare(strict_types=1);
require_once __DIR__.'/local_profile_mass_plan3_4191.php';

const LPMR_OPERATION='int-andromeda-local-profile-mass-recovery-plan-4191-20261008-v1';
const LPMR_BATCH='local4191-mass-recovery-20261008';
const LPMR_INSPECTION_OPERATION='int-andromeda-local-phase3-inspection-4191-20261007-v1';
const LPMR_INSPECTION_BATCH='local4191-phase3-inspection-20261007';
const LPMR_INSPECTION_SOURCE='fda042436b6ec17f1701bd485931ec67ccba2525';
const LPMR_INSPECTION_CONTROL='35a7feac9d53c9b1d72c9b6f18a6e452b51f5d33';
const LPMR_INSPECTION_INPUT_SHA='3caa94dc41a2eaec57de720c2655ec5ec6c042ae2f4552668e68190e37a40d27';
const LPMR_FAILED_OPERATION='int-andromeda-local-profile-mass-plan3-4191-20261002-v1';
const LPMR_FAILED_SOURCE='1dcda2f59b757d30bd062ab5808d0b46eac09019';
const LPMR_FAILED_RESULT_SHA='60496365be1a7d5a39db8a90a3f8fccdff2c82138ea6bd995a5045ca67cab93d';
const LPMR_FAILED_RESERVATION_SHA='421ec164247e9f7d9170c9c4ae353b6f9989718241037f03e676875c10d84110';
const LPMR_FAILED_INSTALLED_SHA='ed03a342229ec44a4586931070a95d6e330ae1f219c91d094889b68dde5c7844';
const LPMR_FAILED_RUNNER_SHA='02f55562e01c4874d8abe0dd7754873bd051af9a4f7ac389c8c8b36601b0abf3';

function lpmr_json_file(string $path,int $max):array{
    $value=json_decode(lpp_file($path,$max),true,512,JSON_THROW_ON_ERROR);
    lpp_need(is_array($value),'recovery_json');
    return $value;
}
function lpmr_evidence(string $home):array{
    $dir=$home.'/.anytoour-int-executor/'.LPMR_INSPECTION_OPERATION;
    $receipt=lpmr_json_file($dir.'/phase3-inspection-receipt.json',65536);
    $files=[
        ['file'=>'reservation.json','present'=>true,'bytes'=>195,'sha256'=>LPMR_FAILED_RESERVATION_SHA],
        ['file'=>'installed-source.json','present'=>true,'bytes'=>1112,'sha256'=>LPMR_FAILED_INSTALLED_SHA],
        ['file'=>'result.json','present'=>true,'bytes'=>521,'sha256'=>LPMR_FAILED_RESULT_SHA],
        ['file'=>'local-mass3-started.json','present'=>false,'bytes'=>0,'sha256'=>null],
        ['file'=>'local-mass3-plan.json','present'=>false,'bytes'=>0,'sha256'=>null],
        ['file'=>'local-mass3-receipt.json','present'=>false,'bytes'=>0,'sha256'=>null],
    ];
    lpp_need(($receipt['schema_version']??null)===1&&($receipt['state']??null)==='inspected_read_only'
        &&($receipt['operation_id']??null)===LPMR_INSPECTION_OPERATION
        &&($receipt['batch']??null)===LPMR_INSPECTION_BATCH
        &&($receipt['source_sha']??null)===LPMR_INSPECTION_SOURCE
        &&($receipt['control_source_sha']??null)===LPMR_INSPECTION_CONTROL
        &&($receipt['target_operation_id']??null)===LPMR_FAILED_OPERATION
        &&($receipt['target_source_sha']??null)===LPMR_FAILED_SOURCE
        &&($receipt['files']??null)===$files
        &&($receipt['input_sha256']??null)===LPMR_INSPECTION_INPUT_SHA
        &&($receipt['recorded_scope_state']??null)==='not_recorded'
        &&array_key_exists('recorded_candidate_profiles',$receipt)&&$receipt['recorded_candidate_profiles']===null
        &&array_key_exists('recorded_scope_sha256',$receipt)&&$receipt['recorded_scope_sha256']===null
        &&($receipt['original_files_unchanged']??null)===true
        &&($receipt['original_status']??null)==='unknown_no_replay'
        &&($receipt['original_supplier_calls']??null)==='unknown'
        &&($receipt['original_database_writes']??null)==='unknown'
        &&($receipt['safe_to_apply']??null)===false&&($receipt['replay_allowed']??null)===false
        &&($receipt['automatic_successor_allowed']??null)===false
        &&($receipt['supplier_calls']??null)===0&&($receipt['provider_http_calls']??null)===0
        &&($receipt['database_reads']??null)===0&&($receipt['database_writes']??null)===0
        &&($receipt['profile_writes']??null)===0&&($receipt['mapping_writes']??null)===0
        &&($receipt['schema_writes']??null)===0&&($receipt['lead_calls']??null)===0
        &&($receipt['booking_calls']??null)===0,'recovery_inspection_receipt');
    $input=lpp_file($dir.'/phase3-inspection-input.json',65536);
    lpp_need(hash_equals(LPMR_INSPECTION_INPUT_SHA,hash('sha256',$input)),'recovery_inspection_input_digest');
    $inputValue=json_decode($input,true,512,JSON_THROW_ON_ERROR);
    lpp_need(is_array($inputValue)&&($inputValue['operation_id']??null)===LPMR_INSPECTION_OPERATION
        &&($inputValue['batch']??null)===LPMR_INSPECTION_BATCH
        &&($inputValue['source_sha']??null)===LPMR_INSPECTION_SOURCE
        &&($inputValue['control_source_sha']??null)===LPMR_INSPECTION_CONTROL
        &&($inputValue['target_operation_id']??null)===LPMR_FAILED_OPERATION
        &&($inputValue['target_source_sha']??null)===LPMR_FAILED_SOURCE
        &&($inputValue['files']??null)===$files,'recovery_inspection_input');
    $reservation=lpp_file($dir.'/phase3-original-reservation.json',65536);
    $installed=lpp_file($dir.'/phase3-original-installed-source.json',131072);
    $terminal=lpp_file($dir.'/phase3-original-result.json',65536);
    lpp_need(hash_equals(LPMR_FAILED_RESERVATION_SHA,hash('sha256',$reservation))
        &&hash_equals(LPMR_FAILED_INSTALLED_SHA,hash('sha256',$installed))
        &&hash_equals(LPMR_FAILED_RESULT_SHA,hash('sha256',$terminal)),'recovery_original_digest');
    $reservationValue=json_decode($reservation,true,64,JSON_THROW_ON_ERROR);
    $installedValue=json_decode($installed,true,512,JSON_THROW_ON_ERROR);
    $terminalValue=json_decode($terminal,true,64,JSON_THROW_ON_ERROR);
    lpp_need(is_array($reservationValue)&&($reservationValue['operation_id']??null)===LPMR_FAILED_OPERATION
        &&($reservationValue['source_sha']??null)===LPMR_FAILED_SOURCE
        &&($reservationValue['mode']??null)==='local-profile-plan-4191','recovery_original_reservation');
    $installedFiles=$installedValue['files']??null;
    lpp_need(is_array($installedValue)&&($installedValue['source_sha']??null)===LPMR_FAILED_SOURCE
        &&is_array($installedFiles)
        &&($installedFiles['scripts/diagnostics/local_profile_mass_plan3_4191.php']??null)===LPMR_FAILED_RUNNER_SHA
        &&!array_key_exists('scripts/diagnostics/local_profile_mass_apply2_4191.php',$installedFiles),
        'recovery_original_manifest');
    lpp_need(is_array($terminalValue)&&($terminalValue['schema_version']??null)===1
        &&($terminalValue['status']??null)==='unknown_no_replay'
        &&($terminalValue['reason']??null)==='local_mass3_terminal_missing_no_replay'
        &&($terminalValue['mode']??null)==='local-profile-plan-4191'
        &&($terminalValue['operation_id']??null)===LPMR_FAILED_OPERATION
        &&($terminalValue['source_sha']??null)===LPMR_FAILED_SOURCE
        &&($terminalValue['supplier_calls']??null)==='unknown'
        &&($terminalValue['database_writes']??null)==='unknown','recovery_original_terminal');
    lpp_need(!file_exists($dir.'/phase3-recorded-scope.json')
        &&!is_link($dir.'/phase3-recorded-scope.json'),'recovery_recorded_scope_absent');
    return [
        'state'=>'verified_pre_main_no_recorded_scope',
        'inspectionOperationId'=>LPMR_INSPECTION_OPERATION,
        'inspectionInputSha256'=>LPMR_INSPECTION_INPUT_SHA,
        'failedOperationId'=>LPMR_FAILED_OPERATION,
        'failedOperationReplayAllowed'=>false,
    ];
}
function lpmr_main(array $argv):int{
    lpp_need(PHP_SAPI==='cli'&&count($argv)===2&&$argv[1]==='--plan-only','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('LOCAL_PROFILE_PLAN_DIR');
    $head=(string)getenv('LOCAL_PROFILE_SOURCE_SHA');$control=(string)getenv('LOCAL_PROFILE_CONTROL_SHA');$home=(string)getenv('HOME');
    lpp_need($root===$home.'/www/anytoour.ru'&&realpath($root)===$root&&realpath($dir)===$dir
        &&dirname($dir)===$home.'/.anytoour-int-executor'&&basename($dir)===LPMR_OPERATION
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1&&preg_match('/^[a-f0-9]{40}$/D',$control)===1,'runtime_scope');
    $reservation=lpmr_json_file($dir.'/reservation.json',65536);
    lpp_need(($reservation['operation_id']??null)===LPMR_OPERATION&&($reservation['source_sha']??null)===$head
        &&($reservation['mode']??null)==='local-profile-plan-4191','reservation');
    foreach(['local-mass-recovery-started.json','local-mass-recovery-plan.json','local-mass-recovery-receipt.json'] as $file)
        lpp_need(!file_exists($dir.'/'.$file)&&!is_link($dir.'/'.$file),'no_replay');
    $evidence=lpmr_evidence($home);
    lpp_save($dir.'/local-mass-recovery-started.json',[
        'operation_id'=>LPMR_OPERATION,'source_sha'=>$head,'recovery_evidence_state'=>$evidence['state']]);
    $parent1=lpm2_parent($home);$parent2=lpm3_parent2($home);$d1=lpp_d1($home);
    $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    lpp_need(is_file($bootstrap)&&!is_link($bootstrap)&&realpath($bootstrap)===$bootstrap,'bootstrap');
    $_SERVER['DOCUMENT_ROOT']=$root;require_once $bootstrap;require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
    $db=v2_data_db();lpp_need($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','mysql_required');
    $db->exec('SET SESSION TRANSACTION READ ONLY');lpp_need(AnyTourProfileEnrichmentV1::MAX_BATCH===LPP_OWNER_MAX_BATCH,'owner_batch_limit_drift');
    $through=gmdate('Y-m-d H:i:s');$snapshot=lpm_snapshot($db,$through);$owner=new AnyTourProfileEnrichmentV1($db);
    $identity=['schema_version'=>1,'batch'=>LPMR_BATCH,'operation_id'=>LPMR_OPERATION,'source_sha'=>$head,
        'control_source_sha'=>$control,'demand_through'=>$through,
        'recovery_evidence_state'=>$evidence['state'],'inspection_operation_id'=>$evidence['inspectionOperationId'],
        'inspection_input_sha256'=>$evidence['inspectionInputSha256'],'failed_operation_id'=>$evidence['failedOperationId'],
        'failed_operation_replay_allowed'=>$evidence['failedOperationReplayAllowed'],
        'predecessor_private_plan_sha256'=>LPM2_PARENT_SHA,'predecessor2_private_plan_sha256'=>LPM3_PARENT2_SHA];
    $audit=lpm3_prepare($snapshot,$d1,$parent1,$parent2,
        static fn(array $scope):array=>$owner->plan(count($scope),$through,$scope,true),
        static function(int $index,array $plan)use($dir,$identity):array{
            $file=sprintf('recovery-batch-%03d.json',$index);
            $digest=lpp_save($dir.'/'.$file,$identity+['owner_plan'=>$plan,'safe_to_apply'=>false]);
            return ['file'=>$file,'sha256'=>$digest,'profiles'=>count($plan['selected']),
                'scope_profiles'=>$plan['limit'],'plan_sha256'=>$plan['planSha256']];
        });
    $private=$identity+['recovery_evidence'=>$evidence,'predecessor_exclusion'=>$parent1,
        'predecessor2_exclusion'=>$parent2,'d1_exclusion'=>$d1,'history_exclusion'=>$snapshot['history']]+$audit;
    $digest=lpp_save($dir.'/local-mass-recovery-plan.json',$private);
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
    lpp_save($dir.'/local-mass-recovery-receipt.json',$receipt);echo lpp_json($receipt)."\n";return 0;
}
if(PHP_SAPI==='cli'&&realpath((string)($argv[0]??''))===__FILE__){
    try{exit(lpmr_main($argv));}catch(Throwable){fwrite(STDERR,"local_profile_mass_recovery_plan_failed_no_replay\n");exit(2);}
}
