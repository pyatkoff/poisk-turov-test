<?php
declare(strict_types=1);

/** Corrected dependency-complete package after terminal v66 pre-DB failure. */
require_once __DIR__.'/hotel_match_live234_v65_current_audit_v66.php';
const V67_OP = 'hotel-match-live234-v65-current-package-1971-20260927-v67';
const V67_PRIOR_SHA = '76657ef9ccd625823baf2157f727ef152be259b2c9355039427d58fa5a6e4922';

/** Verify the exact dependency closure before any server or database access. */
function v67_package_check(): void {
    $base=dirname(__DIR__,2);
    $files=[
        'scripts/diagnostics/hotel_match_live234_v65_current_audit_v66.php'=>'9aaae6deab79fdbf26e116eabc02c5b9fc2fd7c0',
        'scripts/diagnostics/hotel_match_anex_effective_coverage.php'=>'1b5190727690f50a4cdd606c48afa575978ec48e',
        'app/integrations/anex-search-mapping-registry.php'=>'cc135a95d2a6e0f73ce50be2141c9b8a26fddc58',
    ];
    foreach ($files as $relative=>$blob) {
        $path=$base.'/'.$relative;
        v66_need(is_file($path) && !is_link($path), 'package_dependency_missing');
        $bytes=file_get_contents($path);
        v66_need($bytes!==false && sha1('blob '.strlen($bytes)."\0".$bytes)===$blob,'package_dependency_drift');
    }
    require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
    v66_need(class_exists('AnyTourMatchAnexEffectiveCoverage',false) && class_exists('AnyTourAnexSearchMappingRegistry',false),'package_classes_missing');
}

function v67_main(array $argv): int {
    v66_need(($argv[1] ?? '')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT'); $dir=(string)getenv('MATCH_OPERATION_DIR');
    $source=(string)getenv('MATCH_SOURCE_RESULT'); $receipt=(string)getenv('MATCH_SOURCE_RECEIPT');
    $head=(string)getenv('MATCH_SOURCE_SHA'); $prior=(string)getenv('MATCH_PRIOR_RESULT'); $priorReceipt=(string)getenv('MATCH_PRIOR_RECEIPT');
    v66_need(is_dir($root) && !is_link($root) && is_dir($dir) && !is_link($dir) && basename($dir)===V67_OP && preg_match('/^[0-9a-f]{40}$/D',$head)===1,'runtime_scope');
    v66_need(hash_file('sha256',$source)===V66_SOURCE_SHA && hash_file('sha256',$receipt)===V66_RECEIPT_SHA,'source_hash');
    $q=v66_load($receipt);
    v66_need(($q['operation'] ?? '')===V66_SOURCE_OP && ($q['result_sha256'] ?? '')===V66_SOURCE_SHA && ($q['readback_verified'] ?? false)===true && ($q['no_replay'] ?? false)===true,'source_receipt');
    v66_need(hash_file('sha256',$prior)===V67_PRIOR_SHA,'prior_hash');
    $p=v66_load($prior); $pq=v66_load($priorReceipt);
    v66_need(($p['operation'] ?? '')===V66_OP && ($p['state'] ?? '')==='failed_read_only_v65_current_audit' && ($p['reason'] ?? '')==='audit_failed','prior_state');
    v66_need(($pq['operation'] ?? '')===V66_OP && ($pq['result_sha256'] ?? '')===V67_PRIOR_SHA && ($pq['readback_verified'] ?? false)===true && ($pq['no_replay'] ?? false)===true,'prior_receipt');
    foreach ([$p,$pq] as $old) foreach (['database_reads','database_writes','mapping_writes','provider_http_calls'] as $key) v66_need(($old[$key] ?? null)===0,'prior_not_zero');
    $reservation=v66_load($dir.'/reservation.json');
    v66_need(($reservation['operation'] ?? '')===V67_OP && ($reservation['state'] ?? '')==='reserved_before_db_read' && ($reservation['source_sha'] ?? '')===$head && ($reservation['scope'] ?? '')==='original49_only_not_bulk29','reservation');
    v66_need(!file_exists($dir.'/execution-started.json') && !file_exists($dir.'/result.json') && !file_exists($dir.'/receipt.json'),'terminal_no_replay');
    v66_save($dir.'/execution-started.json',['operation'=>V67_OP,'source_sha'=>$head,'state'=>'db_read_reserved','predecessor_operation'=>V66_OP]);
    $readStarted=false; $stage='input'; $db=null;
    try {
        $input=v66_load($source); v66_source($input);
        $stage='dependency_preflight'; v67_package_check();
        $stage='db_bootstrap';
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        v66_need(is_file($bootstrap) && !is_link($bootstrap),'db_bootstrap_missing');
        require_once $bootstrap;
        v66_need(function_exists('v2_data_db'),'db_factory_missing');
        $stage='current_read'; $readStarted=true; $db=v2_data_db();
        $result=v66_run($db,$input,$head);
        $result['operation']=V67_OP;
        $result['state']='completed_read_only_v65_current_package';
    } catch (Throwable $error) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        $code=$error->getMessage();
        if (preg_match('/^[a-z][a-z0-9_]{0,99}$/D',$code)!==1) $code=$error instanceof PDOException?'database_read_failed':'audit_failed';
        $result=['operation'=>V67_OP,'state'=>'failed_read_only_v65_current_package','reason'=>$code,
                 'failure_stage'=>$stage,'error_class'=>get_class($error),'source_sha'=>$head,
                 'provider_http_calls'=>0,'database_reads'=>$readStarted?1:0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }
    $result['predecessor_operation']=V66_OP;
    $result['predecessor_result_sha256']=V67_PRIOR_SHA;
    $hash=v66_save($dir.'/result.json',$result);
    v66_save($dir.'/receipt.json',['operation'=>V67_OP,'state'=>$result['state'],'source_sha'=>$head,'result_sha256'=>$hash,
        'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'no_replay'=>true,
        'provider_http_calls'=>0,'database_reads'=>$readStarted?1:0,'database_writes'=>0,'mapping_writes'=>0]);
    echo v66_json(array_diff_key($result,['rows'=>true]))."\n";
    return $result['state']==='completed_read_only_v65_current_package'?0:2;
}
if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) exit(v67_main($argv));
