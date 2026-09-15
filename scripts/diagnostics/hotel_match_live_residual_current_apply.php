<?php
declare(strict_types=1);
putenv('MATCH_TEST_LIBRARY=1');
require_once __DIR__ . '/hotel_match_live_residual_current_review.php';

const MCA_OP='hotel-match-live-residual-current-apply-1971-20260915-v5';
const MCA_REVIEW_OP='hotel-match-live-residual-current-review-1971-20260915-v5';
const MCA_REVIEW_SHA256='7ebfceafa858149d0581b94918188f59b22184592d07c566356e4f814fe4006f';
const MCA_PLAN_COUNT=21;

function mca_json(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function mca_key(array $r):string{return (string)($r['supplier_namespace']??'').'|'.(string)($r['external_hotel_id']??'');}
function mca_plan(array $review):array{
    $out=[];
    foreach($review['auto_accept_candidates']??[] as $r){
        if(!is_array($r)||($r['kind']??'')!=='operator_native')continue;
        $ns=(string)($r['supplier_namespace']??'');
        if(!in_array($ns,['operator_315','operator_342'],true))continue;
        if(($r['reason']??'')!=='operator_native_accepted_andromeda_bridge')continue;
        $id=(string)($r['external_hotel_id']??'');$target=(int)($r['target']??0);
        if($id===''||$target<=0)continue;
        $k=mca_key($r);if(isset($out[$k])&&(int)$out[$k]['target']!==$target)throw new RuntimeException('plan_conflict');$out[$k]=$r;
    }
    return $out;
}
function mca_review(string $path):array{
    $raw=@file_get_contents($path);if(!is_string($raw)||hash('sha256',$raw)!==MCA_REVIEW_SHA256)throw new RuntimeException('review_hash');
    $x=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($x)||($x['operation_id']??'')!==MCA_REVIEW_OP||($x['state']??'')!=='completed_read_only'||($x['no_replay']??false)!==true||($x['db_writes']??-1)!==0||($x['mapping_writes']??-1)!==0)throw new RuntimeException('review_contract');
    return $x;
}
function mca_row(PDO $db,string $ns,string $id):?array{
    $r=mcr_query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=? FOR UPDATE',[$ns,$id]);
    return count($r)===1?$r[0]:null;
}
function mca_check(PDO $db,array $plan,array $cur):array{
    $ns=(string)$cur['supplier_namespace'];
    if(!in_array($ns,['operator_315','operator_342'],true))return['ok'=>false,'reason'=>'namespace_not_allowed'];
    if($cur['decision_status']!=='pending'||$cur['local_hotel_id']!==null)return['ok'=>false,'reason'=>'current_state_protected'];
    $e=mcr_evidence((string)$cur['evidence_json']);$refs=mcr_provider_bridges($e);if(!$refs)return['ok'=>false,'reason'=>'no_andromeda_refs'];
    $targets=[];$anchors=[];
    foreach($refs as $aid){
        $r=mcr_query($db,"SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE",[$aid]);
        if(count($r)!==1||$r[0]['decision_status']!=='accepted'||$r[0]['local_hotel_id']===null)return['ok'=>false,'reason'=>'ref_not_accepted','andromeda_id'=>$aid];
        $target=(int)$r[0]['local_hotel_id'];$targets[$target]=true;$anchors[]=['andromeda_hotel_id'=>$aid,'local_hotel_id'=>$target,'evidence_sha256'=>(string)$r[0]['evidence_sha256']];
    }
    if(count($targets)!==1)return['ok'=>false,'reason'=>'ref_target_conflict'];
    $target=(int)array_key_first($targets);if($target!==(int)($plan['target']??0))return['ok'=>false,'reason'=>'target_changed'];
    $h=mcr_query($db,'SELECT id,country_id,name,latitude,longitude,is_active FROM catalog_hotels WHERE id=? FOR UPDATE',[$target]);
    if(count($h)!==1||(int)$h[0]['is_active']!==1)return['ok'=>false,'reason'=>'target_inactive_or_missing'];
    $g=mcr_direct_target_guard(mcr_names($e),mcr_points($e),$h[0]);if(!($g['ok']??false))return['ok'=>false,'reason'=>$g['reason']??'direct_guard'];
    return['ok'=>true,'evidence'=>$e,'target'=>$target,'anchors'=>$anchors,'distance_km'=>$g['distance_km']??null];
}
function mca_evidence(array $prior,array $promotion):array{return['prior_evidence'=>$prior,'promotion'=>$promotion];}
function mca_readback_ok(array $r,int $target):bool{
    $e=mcr_evidence((string)($r['evidence_json']??''));
    return($r['decision_status']??'')==='accepted'&&(int)($r['local_hotel_id']??0)===$target&&($e['promotion']['operation_id']??'')===MCA_OP&&(int)($e['promotion']['target_local_hotel_id']??0)===$target;
}
function mca_write_exclusive(string $file,array $value):string{
    $raw=mca_json($value);$f=@fopen($file,'x+b');if(!$f)throw new RuntimeException('exclusive_file');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('file_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('file_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('file_readback');}finally{fclose($f);}return hash('sha256',$raw);
}

if(getenv('MATCH_APPLY_TEST_LIBRARY')==='1')return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$operation=(string)getenv('MATCH_OPERATION_ID');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if($operation!==MCA_OP||!preg_match('/^[0-9a-f]{40}$/D',$sourceSha))throw new RuntimeException('operation_or_source_guard');
$home=(string)getenv('HOME');if($home==='')throw new RuntimeException('home_missing');$dir=$home.'/.anytoour-match/operations/'.MCA_OP;$reviewPath=$home.'/.anytoour-match/operations/'.MCA_REVIEW_OP.'/result.json';
$res=mcr_evidence((string)@file_get_contents($dir.'/reservation.json'));if(($res['operation_id']??'')!==MCA_OP||($res['source_sha']??'')!==$sourceSha||($res['state']??'')!=='reserved_before_db_access'||(int)($res['planned_rows']??-1)!==MCA_PLAN_COUNT)throw new RuntimeException('reservation_contract');
$review=mca_review($reviewPath);$plans=mca_plan($review);if(count($plans)!==MCA_PLAN_COUNT)throw new RuntimeException('plan_count_'.count($plans));
$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=30');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();$committed=false;
try{
    $eng=array_column(mcr_query($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels')"),'ENGINE','TABLE_NAME');foreach(['andromeda_hotel_identities','catalog_hotels'] as $t)if(strtoupper((string)($eng[$t]??''))!=='INNODB')throw new RuntimeException('transaction_table_'.$t);
    $valid=[];$holds=[];
    foreach($plans as $key=>$plan){$ns=(string)$plan['supplier_namespace'];$cur=mca_row($db,$ns,(string)$plan['external_hotel_id']);if(!$cur){$holds[]=['key'=>$key,'reason'=>'identity_missing'];continue;}$check=mca_check($db,$plan,$cur);if(!($check['ok']??false)){$holds[]=['key'=>$key,'reason'=>$check['reason']??'validation_failed'];continue;}$valid[$key]=['plan'=>$plan,'cur'=>$cur,'check'=>$check];}
    $up=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace=? AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");$written=[];
    foreach($valid as $key=>$x){$p=$x['plan'];$c=$x['cur'];$ck=$x['check'];$target=(int)$ck['target'];$promotion=['operation_id'=>MCA_OP,'rule'=>'operator_native_accepted_andromeda_bridge','target_local_hotel_id'=>$target,'review_operation_id'=>MCA_REVIEW_OP,'review_result_sha256'=>MCA_REVIEW_SHA256,'server_current_revalidated'=>true,'andromeda_anchors'=>$ck['anchors'],'coordinate_conflict_gt5km_blocked'=>true,'distance_km'=>$ck['distance_km']];$json=mca_json(mca_evidence($ck['evidence'],$promotion));$sha=hash('sha256',$json);$up->execute([$target,$sha,$json,(string)$c['supplier_namespace'],(string)$c['external_hotel_id'],$c['evidence_sha256']]);if($up->rowCount()!==1)throw new RuntimeException('concurrency_'.$key);$written[]=['supplier_namespace'=>(string)$c['supplier_namespace'],'external_hotel_id'=>(string)$c['external_hotel_id'],'target'=>$target,'new_sha'=>$sha];}
    $db->commit();$committed=true;$read=[];
    foreach($written as $w){$r=mcr_query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?',[$w['supplier_namespace'],$w['external_hotel_id']]);if(count($r)!==1||!mca_readback_ok($r[0],(int)$w['target'])||(string)$r[0]['evidence_sha256']!==$w['new_sha'])throw new RuntimeException('post_commit_'.$w['supplier_namespace'].'_'.$w['external_hotel_id']);$read[]=['supplier_namespace'=>$w['supplier_namespace'],'external_hotel_id'=>$w['external_hotel_id'],'local_hotel_id'=>(int)$r[0]['local_hotel_id'],'decision_status'=>$r[0]['decision_status'],'evidence_sha256'=>$r[0]['evidence_sha256']];}
    $ns=[];foreach($read as $r)$ns[$r['supplier_namespace']]=($ns[$r['supplier_namespace']]??0)+1;ksort($ns);
    $result=['schema'=>'hotel-match-live-residual-operator-bridge-apply/1','operation_id'=>MCA_OP,'source_sha'=>$sourceSha,'state'=>'completed_committed','server_current'=>true,'review_operation_id'=>MCA_REVIEW_OP,'review_result_sha256'=>MCA_REVIEW_SHA256,'planned'=>count($plans),'validated'=>count($valid),'written'=>count($read),'holds_count'=>count($holds),'holds'=>$holds,'written_by_namespace'=>$ns,'written_by_rule'=>['operator_native_accepted_andromeda_bridge'=>count($read)],'post_commit_readback'=>$read,'supplier_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'mapping_writes'=>count($read),'operator_5_writes'=>0,'no_replay'=>true,'completed_at'=>gmdate('c')];
    $hash=mca_write_exclusive($dir.'/result.json',$result);$raw=(string)file_get_contents($dir.'/result.json');$ok=hash('sha256',$raw)===$hash&&count((mcr_evidence($raw))['post_commit_readback']??[])===count($read);$receipt=['operation_id'=>MCA_OP,'source_sha'=>$sourceSha,'state'=>'completed_committed','result_sha256'=>$hash,'readback_verified'=>$ok,'planned'=>count($plans),'written'=>count($read),'holds_count'=>count($holds),'post_commit_rows'=>count($read),'operator_5_writes'=>0,'supplier_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'no_replay'=>true,'completed_at'=>gmdate('c')];mca_write_exclusive($dir.'/receipt.json',$receipt);if(!$ok)throw new RuntimeException('result_readback');echo mca_json(['operation_id'=>MCA_OP,'state'=>'completed_committed','planned'=>count($plans),'written'=>count($read),'holds'=>count($holds),'written_by_namespace'=>$ns,'result_sha256'=>$hash]);
}catch(Throwable $e){if(!$committed&&$db->inTransaction())$db->rollBack();if(!$committed&&!is_file($dir.'/failure.json'))mca_write_exclusive($dir.'/failure.json',['operation_id'=>MCA_OP,'source_sha'=>$sourceSha,'state'=>'rolled_back','error_class'=>get_class($e),'error'=>substr($e->getMessage(),0,300),'supplier_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'operator_5_writes'=>0,'no_replay'=>true,'failed_at'=>gmdate('c')]);throw $e;}
