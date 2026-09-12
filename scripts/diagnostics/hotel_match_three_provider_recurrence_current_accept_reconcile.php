<?php
declare(strict_types=1);

/** Reconcile UNKNOWN recurrence writer v1 from CURRENT DB provenance only. */
const HMRCRX_OPERATION = 'hotel-match-three-provider-recurrence-current-accept-reconcile-1971-20260912-v1';
const HMRCRX_UNKNOWN_OPERATION = 'hotel-match-three-provider-recurrence-current-accept-1971-20260912-v1';
const HMRCRX_EVIDENCE_SHA256 = 'e78a092225a3d976345cc6f1eafd9d5d170bcfe34dd608c66de1cd99f843d98f';
const HMRCRX_POLICY = 'owner_exact_and_strong_20260908';

function hmrcx_json($v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function hmrcx_digest(): string {
    return hash('sha256',hmrcx_json(['operation_id'=>HMRCRX_UNKNOWN_OPERATION,'evidence_sha256'=>HMRCRX_EVIDENCE_SHA256,'mode'=>'server_current_recurrence_v1']));
}
function hmrcx_evidence(): array {
    if(!defined('HMRCRX_EVIDENCE_B64'))throw new RuntimeException('evidence_not_embedded');
    $raw=base64_decode((string)constant('HMRCRX_EVIDENCE_B64'),true);
    if(!is_string($raw)||hash('sha256',$raw)!==HMRCRX_EVIDENCE_SHA256)throw new RuntimeException('evidence_sha_mismatch');
    $d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(($d['v']??null)!==1||($d['summary']??null)!==[10,233,71,50,41,32,13,46,40,27]||count($d['tuples']??[])!==71)throw new RuntimeException('evidence_contract_changed');
    return$d;
}
function hmrcx_in(array $v): array {$v=array_values(array_unique($v,SORT_REGULAR));return $v?[implode(',',array_fill(0,count($v),'?')),$v]:['NULL',[]];}
function hmrcx_andromeda_op(array $e): ?string {
    if(isset($e['promotion'])&&is_array($e['promotion'])&&isset($e['promotion']['operation_id']))return (string)$e['promotion']['operation_id'];
    return null;
}
function hmrcx_reconcile(PDO $db,array $ev): array {
    $tupleByAn=[];$tupleByAnd=[];$anIds=[];$andIds=[];
    foreach($ev['tuples'] as$t){$tv=(int)$t[0];$an=(int)$t[1];$and=(string)$t[2];$ns=(string)$t[3];$tuple=['tourvisor_id'=>$tv,'anex_hotel_id'=>$an,'andromeda_external_id'=>$and,'andromeda_namespace'=>$ns];$tupleByAn[$an][]=$tuple;if($ns==='andromeda_catalog'){$tupleByAnd[$and][]=$tuple;$andIds[]=$and;}$anIds[]=$an;}
    [$anSql,$anP]=hmrcx_in($anIds);[$andSql,$andP]=hmrcx_in($andIds);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $digest=hmrcx_digest();$ownedAn=[];$ownedAnd=[];$bad=[];
        $q=$db->prepare("SELECT anex_hotel_id,catalog_hotel_id,mapping_digest,source_row_digest,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($anSql) AND mapping_digest=?");$q->execute(array_merge($anP,[$digest]));
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as$r){$aid=(int)$r['anex_hotel_id'];$target=(int)$r['catalog_hotel_id'];$tuples=$tupleByAn[$aid]??[];$ok=false;foreach($tuples as$t)if($t['tourvisor_id']===$target){$ok=true;break;}$x=['provider'=>'anex','external_id'=>$aid,'local_hotel_id'=>$target,'enabled'=>(int)$r['enabled'],'scope'=>(string)$r['scope'],'approval_policy'=>(string)$r['approval_policy'],'target_matches_immutable_tuple'=>$ok];if($ok&&$x['enabled']===1&&$x['scope']==='preview'&&$x['approval_policy']===HMRCRX_POLICY)$ownedAn[]=$x;else$bad[]=$x;}
        if($andIds){$q=$db->prepare("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($andSql)");$q->execute($andP);foreach($q->fetchAll(PDO::FETCH_ASSOC) as$r){$e=json_decode((string)($r['evidence_json']??''),true);if(!is_array($e)||hmrcx_andromeda_op($e)!==HMRCRX_UNKNOWN_OPERATION)continue;$id=(string)$r['external_hotel_id'];$target=(int)$r['local_hotel_id'];$ok=false;foreach($tupleByAnd[$id]??[] as$t)if($t['tourvisor_id']===$target){$ok=true;break;}$x=['provider'=>'andromeda','external_id'=>$id,'local_hotel_id'=>$target,'decision_status'=>(string)$r['decision_status'],'target_matches_immutable_tuple'=>$ok];if($ok&&$x['decision_status']==='accepted')$ownedAnd[]=$x;else$bad[]=$x;}}

        $q=$db->prepare("SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy,mapping_digest FROM anex_hotel_search_mappings WHERE anex_hotel_id=8280 ORDER BY enabled DESC,catalog_hotel_id");$q->execute();$a8280=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$db->prepare("SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id=8280");$q->execute();$d8280=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$db->prepare("SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id='2000034099'");$q->execute();$bridge=$q->fetchAll(PDO::FETCH_ASSOC);
        $db->commit();
        $owned=count($ownedAn)+count($ownedAnd);$status=$bad?'conflict':($owned?'reconciled_committed':'reconciled_no_write');
        return ['status'=>'completed','operation_id'=>HMRCRX_OPERATION,'reconciles_operation_id'=>HMRCRX_UNKNOWN_OPERATION,'reconciliation'=>$status,'examined_tuples'=>71,'evidence_sha256'=>HMRCRX_EVIDENCE_SHA256,'operation_owned_rows'=>$owned,'operation_owned_anex'=>count($ownedAn),'operation_owned_andromeda'=>count($ownedAnd),'owned_rows'=>array_merge($ownedAn,$ownedAnd),'unexpected_operation_rows'=>$bad,'safe_target_current'=>['anex_8280_mappings'=>$a8280,'anex_8280_decisions'=>$d8280,'andromeda_2000034099'=>$bridge],'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}

if(PHP_SAPI==='cli'&&($argv[1]??'')==='--self-test'){
    if(strlen(hmrcx_digest())!==64)throw new RuntimeException('digest_test');
    if(hmrcx_andromeda_op(['promotion'=>['operation_id'=>HMRCRX_UNKNOWN_OPERATION]])!==HMRCRX_UNKNOWN_OPERATION)throw new RuntimeException('promotion_test');
    if(hmrcx_andromeda_op(['prior_evidence'=>['operation_id'=>HMRCRX_UNKNOWN_OPERATION]])!==null)throw new RuntimeException('prior_must_not_attribute');
    echo "MATCH_RECURRENCE_ACCEPT_RECONCILE_TEST_OK\n";exit(0);
}
if(PHP_SAPI==='cli'&&($argv[1]??'')==='--live'){
    try{$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('server_root_invalid');$helper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once$helper;$r=hmrcx_reconcile(v2_data_db(),hmrcx_evidence());echo'HMRCRX_RESULT:'.hmrcx_json($r).PHP_EOL;}catch(Throwable $e){echo'HMRCRX_RESULT:'.hmrcx_json(['status'=>'failed','operation_id'=>HMRCRX_OPERATION,'reason'=>'reconcile_failed','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'no_replay'=>true]).PHP_EOL;exit(2);}
}
