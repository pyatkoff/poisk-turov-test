<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
const CF_OP='hotel-match-anex-canonical-frontier-current-1971-20260920-v1';
const CF_OLD_SHA='8644e09a8301b3a98aced075365c0ce89488d2e09adab3d43796f3b4ced0c968';
function cf_require(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function cf_json(array $v):string{return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function cf_save(string $p,array $v):string{$b=cf_json($v);$f=fopen($p,'xb');cf_require(is_resource($f),'immutable_output');try{cf_require(fwrite($f,$b)===strlen($b)&&fflush($f),'output_write');if(function_exists('fsync'))cf_require(fsync($f),'output_sync');}finally{fclose($f);}cf_require(file_get_contents($p)===$b,'output_readback');return hash('sha256',$b);}
function cf_rows(PDO $db,string $sql,array $params=[]):array{$q=$db->prepare($sql);$q->execute($params);$r=$q->fetchAll(PDO::FETCH_ASSOC);cf_require(count($r)<=50000,'row_limit');return$r;}
cf_require(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===CF_OP,'operation_guard');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.CF_OP;
cf_require(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'operation_directory');
$source=(string)getenv('MATCH_SOURCE_SHA');cf_require(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);
cf_require(($res['operation_id']??'')===CF_OP&&($res['source_sha']??'')===$source&&($res['state']??'')==='reserved_before_db_read','reservation_guard');
$base=['operation_id'=>CF_OP,'source_sha'=>$source,'supplier_calls'=>0,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];$db=null;
try{
    cf_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);
    cf_require(hash_file('sha256',$dir.'/old-frontier.json')===CF_OLD_SHA,'old_frontier_digest');
    $old=json_decode((string)file_get_contents($dir.'/old-frontier.json'),true,64,JSON_THROW_ON_ERROR);
    cf_require(count($old['frontier']??[])===3792&&($old['supplier_calls']??-1)===0,'old_frontier_shape');
    $root=realpath(getcwd());cf_require(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
    require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    foreach(['catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities'] as $table){
        $r=cf_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);cf_require(count($r)===1&&strtoupper((string)$r[0]['ENGINE'])==='INNODB','table_engine');
    }
    $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=$coverage['by_local'];$samo=[];
    foreach(cf_rows($db,"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r){$id=(int)$r['local_hotel_id'];if($id>0)$samo[$id][(string)$r['external_hotel_id']]=true;}
    // The old single-policy SQL is used ONLY to measure the bug in this same snapshot.
    $legacy=[];
    foreach(cf_rows($db,"SELECT m.catalog_hotel_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='owner_exact_and_strong_20260908' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)") as $r)$legacy[(int)$r['catalog_hotel_id']]=true;
    foreach(cf_rows($db,"SELECT d.catalog_hotel_id FROM anex_hotel_decisions d WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)") as $r)$legacy[(int)$r['catalog_hotel_id']]=true;
    $missing=array_diff_key($samo,$anex);ksort($missing,SORT_NUMERIC);
    $triple=array_intersect_key($samo,$anex);$oldIds=[];$removed=[];$remaining=[];$lostSamo=[];$routes=[];$oldReady=0;$removedReady=0;
    foreach($old['frontier'] as $r){
        $id=(int)$r['local_hotel_id'];$oldIds[$id]=true;$wasReady=$r['route']==='saved_tour_detail_ready';$oldReady+=(int)$wasReady;
        if(isset($anex[$id])){
            $removed[]=['local_hotel_id'=>$id,'name'=>$r['name'],'prior_route'=>$r['route'],'effective_anex_ids'=>array_keys($anex[$id]),'excluded_by_legacy_policy_query'=>!isset($legacy[$id]),'reason'=>'already_effective_canonical_anex'];$removedReady+=(int)$wasReady;continue;
        }
        if(!isset($samo[$id])){$lostSamo[]=$id;continue;}
        $r['safe_to_write_now']=false;$r['safe_to_query_now']=false;$r['context_snapshot_at_utc']=$old['read_at_utc'];$r['identity_checked_at_utc']=null;
        $remaining[]=$r;$routes[$r['route']]=($routes[$r['route']]??0)+1;
    }
    $newIds=array_values(array_map('intval',array_keys(array_diff_key($missing,$oldIds))));
    $clock=cf_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];
    foreach($remaining as &$r)$r['identity_checked_at_utc']=$clock;unset($r);
    cf_require($oldReady===181,'old_ready_count');
    cf_require(count($remaining)+count($removed)+count($lostSamo)===3792,'partition_count');
    cf_require(count($remaining)+count($newIds)===count($missing),'current_missing_count');
    $db->rollBack();
    $result=$base+['state'=>'completed_read_only','read_at_utc'=>$clock,'canonical_registry_blob'=>'cc135a95d2a6e0f73ce50be2141c9b8a26fddc58','old_frontier_sha256'=>CF_OLD_SHA,'old_context_snapshot_at_utc'=>$old['read_at_utc'],'coverage_global'=>['anex_effective_native'=>$coverage['native_count'],'tv_anex_unique_local'=>count($anex),'tv_samo_unique_local'=>count($samo),'tv_anex_samo_triple'=>count($triple),'tv_anex_missing_samo'=>count($anex)-count($triple),'tv_samo_missing_anex'=>count($missing)],'same_snapshot_legacy_comparison'=>['legacy_anex_unique_local'=>count($legacy),'canonical_anex_unique_local'=>count($anex),'canonical_samo_hotels_falsely_missing_anex'=>count(array_diff_key($triple,$legacy))],'old_reverse_count'=>3792,'removed_already_effective_count'=>count($removed),'old_ready181_removed'=> $removedReady,'old_ready181_remaining'=>181-$removedReady,'remaining_route_counts'=>$routes,'current_missing_ids'=>array_values(array_map('intval',array_keys($missing))),'removed_already_effective'=>$removed,'remaining_retained_context_frontier'=>$remaining,'lost_canonical_samo_ids'=>$lostSamo,'new_missing_ids_need_context'=>$newIds];
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();$result=$base+['state'=>'failed_no_replay','reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','error_class'=>get_class($e)];}
$hash=cf_save($dir.'/result.json',$result);cf_save($dir.'/receipt.json',['operation_id'=>CF_OP,'source_sha'=>$source,'state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
echo cf_json(['state'=>$result['state'],'coverage_global'=>$result['coverage_global']??null,'removed_already_effective_count'=>$result['removed_already_effective_count']??null,'old_ready181_remaining'=>$result['old_ready181_remaining']??null,'result_sha256'=>$hash]);if($result['state']!=='completed_read_only')exit(2);
