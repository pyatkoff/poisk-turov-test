<?php
declare(strict_types=1);
// Two saved operator-link identities only. No provider calls and no mapping writes.
const RS2_OP = 'hotel-match-reverse-saved2-current-1971-20260920-v1';
const RS2_INPUT_SHA = '68fe7d79d9bad888a621a7b9df833551f7d58ba34413257fa24e70b5a8aaa492';
const RS2_GUARD_SHA = '5e3875e9835a8de388317e0bd6ef41d76b56531596ed7fab99ea286b66a96878';
function rs2_check(bool $ok,string $reason):void {if(!$ok)throw new RuntimeException($reason);}
function rs2_input(string $path):array {
    rs2_check(!is_link($path)&&is_file($path)&&hash_file('sha256',$path)===RS2_INPUT_SHA,'input_digest');
    $a=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    rs2_check(($a['schema']??'')==='match_reverse_saved2_v1'&&count($a['pairs']??[])===2,'input_shape');
    $ids=[];
    foreach($a['pairs'] as $p){
        $ids[]=[(int)$p['hotel_id'],(int)$p['native_anex_id']];
        rs2_check($p['operator_id']===13&&$p['country_id']===4&&$p['safe_to_write_now']===false,'input_scope');
        rs2_check(r54_link($p['operator_link'])===$p['native_anex_id']&&$p['raw_signed_tokens']===[(string)$p['native_anex_id']],'native_link');
        rs2_check($p['tv_hotel']['id']===$p['hotel_id']&&$p['tv_hotel']['country']['id']===4&&$p['tv_hotel']['name']===$p['hotel_name'],'hotel_binding');
        foreach(['detail_response_sha256','recorded_http_body_sha256','source_edge_sha256','source_request_sha256'] as $k)rs2_check(preg_match('/^[0-9a-f]{64}$/D',$p[$k]??'')===1,'evidence_hash');
    }
    rs2_check($ids===[[1478,8419],[28460,18685]],'pair_allowlist');
    return $a['pairs'];
}
function rs2_run(string $dir,string $source):void {
    $base=['operation_id'=>RS2_OP,'source_sha'=>$source,'input_sha256'=>RS2_INPUT_SHA,'guard_sha256'=>RS2_GUARD_SHA,'provider_calls'=>0,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];
    $db=null;
    try{
        r54_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);
        $pairs=rs2_input($dir.'/input.json');
        $root=realpath(getcwd());rs2_check(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
        require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        foreach(['catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','anex_hotels','anex_hotel_auto_matches','anex_search_hotel_observations','anex_hotel_candidates','andromeda_hotel_identities'] as $t){
            $r=r54_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);
            rs2_check(count($r)===1&&strtoupper((string)$r[0]['ENGINE'])==='INNODB','table_engine');
        }
        $s=r54_current($db,$pairs,false);
        $canonical=r54_rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND (local_hotel_id IN (?,?) OR external_hotel_id IN (?,?)) ORDER BY external_hotel_id",[1478,28460,'226342','2000023030']);
        $rows=[];$eligible=0;
        foreach($pairs as $p){
            $d=r54_review($p,$s);
            $same=array_values(array_filter($canonical,static fn($r)=>(int)$r['local_hotel_id']===$p['hotel_id']&&$r['decision_status']==='accepted'&&in_array((string)$r['external_hotel_id'],$p['canonical_samo_ids'],true)));
            if(count($same)!==count($p['canonical_samo_ids']))$d['holds'][]='canonical_samo_drift';
            $d['eligible_for_guarded_append']=$d['holds']===[];
            $d['safe_to_write_now']=false;
            $d['canonical_samo']=$same;
            $d['saved_link_provenance']=$p;
            $eligible+=(int)$d['eligible_for_guarded_append'];$rows[]=$d;
        }
        $clock=r54_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];
        $db->rollBack();
        $out=$base+['state'=>'completed_read_only','read_at_utc'=>$clock,'examined'=>2,'eligible_count'=>$eligible,'held_count'=>2-$eligible,'dossiers'=>$rows];
    }catch(Throwable $e){
        if($db&&$db->inTransaction())$db->rollBack();
        $out=$base+['state'=>'failed_no_replay','reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','error_class'=>get_class($e)];
    }
    $hash=r54_save($dir.'/result.json',$out);
    r54_save($dir.'/receipt.json',['operation_id'=>RS2_OP,'source_sha'=>$source,'state'=>$out['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'no_replay'=>true]);
    echo json_encode(['state'=>$out['state'],'examined'=>$out['examined']??0,'eligible_count'=>$out['eligible_count']??0,'result_sha256'=>$hash],JSON_THROW_ON_ERROR)."\n";
    if($out['state']!=='completed_read_only')exit(2);
}
rs2_check(PHP_SAPI==='cli','cli_only');
$dir=__DIR__;
rs2_check(!is_link($dir.'/guards.php')&&hash_file('sha256',$dir.'/guards.php')===RS2_GUARD_SHA,'guard_digest');
require_once $dir.'/guards.php';
if(($argv[1]??'')==='--self-test'){r54_selftest();rs2_input($dir.'/input.json');echo "REVERSE_SAVED2_INPUT_OK\n";exit;}
rs2_check(($argv[1]??'')==='--read'&&(string)getenv('MATCH_OPERATION_ID')===RS2_OP,'operation_guard');
$expected=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.RS2_OP;
rs2_check(realpath($dir)===$expected,'operation_dir');
$source=(string)getenv('MATCH_SOURCE_SHA');rs2_check(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');
$r=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);
rs2_check(($r['operation_id']??'')===RS2_OP&&($r['source_sha']??'')===$source&&($r['state']??'')==='reserved_before_db_read','reservation_guard');
rs2_run($dir,$source);
