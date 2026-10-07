<?php
declare(strict_types=1);

/** Fresh CURRENT validation of the complete, already prepared retained29 batch. */
require_once __DIR__.'/hotel_match_live234_v65_current_package_v67.php';
const V68_OP = 'hotel-match-retained29-current-1971-20260927-v68';
const V68_PRIOR_SHA = '13c531f5ec35b5aa4b35ab0053a389c8081bdfd97add417a7c41e2bc5e204489';

function v68_main(array $argv): int {
    v66_need(($argv[1] ?? '')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT'); $dir=(string)getenv('MATCH_OPERATION_DIR');
    $source=(string)getenv('MATCH_SOURCE_RESULT'); $receipt=(string)getenv('MATCH_SOURCE_RECEIPT');
    $head=(string)getenv('MATCH_SOURCE_SHA'); $plan=(string)getenv('MATCH_RETAINED_PLAN');
    $prior=(string)getenv('MATCH_PRIOR_RESULT'); $priorReceipt=(string)getenv('MATCH_PRIOR_RECEIPT');
    v66_need(is_dir($root) && !is_link($root) && is_dir($dir) && !is_link($dir) && basename($dir)===V68_OP && preg_match('/^[0-9a-f]{40}$/D',$head)===1,'runtime_scope');
    v66_need(hash_file('sha256',$source)===V66_SOURCE_SHA && hash_file('sha256',$receipt)===V66_RECEIPT_SHA,'source_hash');
    $q=v66_load($receipt);
    v66_need(($q['operation'] ?? '')===V66_SOURCE_OP && ($q['result_sha256'] ?? '')===V66_SOURCE_SHA && ($q['readback_verified'] ?? false)===true && ($q['no_replay'] ?? false)===true,'source_receipt');
    v66_need(hash_file('sha256',$prior)===V68_PRIOR_SHA,'prior_hash');
    $p=v66_load($prior); $pq=v66_load($priorReceipt);
    v66_need(($p['operation'] ?? '')===V67_OP && ($p['state'] ?? '')==='completed_read_only_v65_current_package','prior_state');
    v66_need(($pq['operation'] ?? '')===V67_OP && ($pq['result_sha256'] ?? '')===V68_PRIOR_SHA && ($pq['readback_verified'] ?? false)===true && ($pq['no_replay'] ?? false)===true,'prior_receipt');
    foreach ([$p,$pq] as $old) foreach (['database_writes','mapping_writes','provider_http_calls'] as $key) v66_need(($old[$key] ?? null)===0,'prior_not_zero');
    v66_load($source); v66_load($plan);
    $sourceRaw=(string)file_get_contents($source); $planRaw=(string)file_get_contents($plan);
    $input=v66_prepare_bulk($sourceRaw,$planRaw);
    v66_need(count($input['selected'])===29 && count($input['ordinary_review_ids'])===21 && count($input['historical_review_ids'])===8 && count($input['ordinary_ids_outside_legacy49'])===6,'exact_bulk_scope');
    $reservation=v66_load($dir.'/reservation.json');
    v66_need(($reservation['operation'] ?? '')===V68_OP && ($reservation['state'] ?? '')==='reserved_before_db_read' && ($reservation['source_sha'] ?? '')===$head && ($reservation['scope'] ?? '')==='retained29_current_only' && ($reservation['retained_plan_sha256'] ?? '')===V66_BULK_PLAN_SHA,'reservation');
    v66_need(!file_exists($dir.'/execution-started.json') && !file_exists($dir.'/result.json') && !file_exists($dir.'/receipt.json'),'terminal_no_replay');
    v66_save($dir.'/execution-started.json',['operation'=>V68_OP,'source_sha'=>$head,'state'=>'db_read_reserved','retained_plan_sha256'=>V66_BULK_PLAN_SHA]);
    $readStarted=false; $stage='dependency_preflight'; $db=null;
    try {
        v67_package_check();
        $stage='db_bootstrap';
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        v66_need(is_file($bootstrap) && !is_link($bootstrap),'db_bootstrap_missing');
        require_once $bootstrap;
        v66_need(function_exists('v2_data_db'),'db_factory_missing');
        $stage='current_read'; $readStarted=true; $db=v2_data_db();
        $current=v66_current($db,$input['selected']);
        $assessment=v66_assess_bulk($sourceRaw,$planRaw,$current);
        $reasons=[];
        foreach ($assessment['rows'] as $row) foreach ($row['reasons'] as $reason) $reasons[$reason]=($reasons[$reason] ?? 0)+1;
        ksort($reasons);
        $result=['operation'=>V68_OP,'state'=>'completed_read_only_retained29_current','source_sha'=>$head,
            'source_result_sha256'=>V66_SOURCE_SHA,'retained_plan_sha256'=>V66_BULK_PLAN_SHA,'generated_at_utc'=>gmdate('c'),
            'input_dossiers'=>$input['input_dossiers'],'candidate_pairs_examined'=>$input['candidate_pairs_examined'],
            'selected_pairs'=>29,'ordinary_review_ids'=>$input['ordinary_review_ids'],'historical_review_ids'=>$input['historical_review_ids'],
            'ordinary_ids_outside_legacy49'=>$input['ordinary_ids_outside_legacy49'],'registry_rows_read'=>$current['registry_rows_read'],
            'required_exact_operator_lanes'=>1,'second_operator_required'=>false,
            'rows'=>$assessment['rows'],'status_counts'=>$assessment['status_counts'],'reason_counts'=>$reasons,
            'current_validation_performed'=>true,'provider_http_calls'=>0,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    } catch (Throwable $error) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        $code=$error->getMessage();
        if (preg_match('/^[a-z][a-z0-9_]{0,99}$/D',$code)!==1) $code=$error instanceof PDOException?'database_read_failed':'audit_failed';
        $result=['operation'=>V68_OP,'state'=>'failed_read_only_retained29_current','reason'=>$code,
            'failure_stage'=>$stage,'error_class'=>get_class($error),'source_sha'=>$head,'current_validation_performed'=>false,
            'provider_http_calls'=>0,'database_reads'=>$readStarted?1:0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }
    $result['predecessor_operation']=V67_OP; $result['predecessor_result_sha256']=V68_PRIOR_SHA;
    $hash=v66_save($dir.'/result.json',$result);
    v66_save($dir.'/receipt.json',['operation'=>V68_OP,'state'=>$result['state'],'source_sha'=>$head,'result_sha256'=>$hash,
        'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'no_replay'=>true,
        'provider_http_calls'=>0,'database_reads'=>$readStarted?1:0,'database_writes'=>0,'mapping_writes'=>0]);
    echo v66_json(array_diff_key($result,['rows'=>true]))."\n";
    return $result['state']==='completed_read_only_retained29_current'?0:2;
}
if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) exit(v68_main($argv));
