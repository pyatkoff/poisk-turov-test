<?php
declare(strict_types=1);

/** Same 97-row audit, corrected classification. No supplier client or SQL writes. */
const A74_OP = 'hotel-match-current15-samo82-anex-1971-20260927-v74d';
const A74_MANIFEST_BLOB = '3af896b13f6d858caa14c7e9a886df25b4458de4';
const A74_S15 = [125=>'2000062991',1181=>'2000028206',1229=>'5464',1772=>'37255',4326=>'309768',7958=>'2000026726',13947=>'2000046831',55945=>'2000037585',56479=>'2000093384',65341=>'2000106034',67304=>'2000055490',76753=>'2000087342',108356=>'269426',116886=>'2000103169',121109=>'2000090159'];

function a74_need(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function a74_json(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function a74_blob(string $raw): string { return sha1('blob '.strlen($raw)."\0".$raw); }
function a74_read(string $path): string {
    a74_need(is_file($path)&&!is_link($path)&&filesize($path)<=8388608,'input_file');
    $raw=file_get_contents($path); a74_need(is_string($raw),'input_read'); return $raw;
}
function a74_save(string $path,array $value): string {
    $raw=a74_json($value)."\n"; $f=@fopen($path,'x+b'); a74_need($f!==false,'exclusive_record');
    try { a74_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'record_write'); if(function_exists('fsync'))a74_need(fsync($f),'record_sync'); }
    finally { fclose($f); } return hash('sha256',$raw);
}
function a74_pairs(string $raw): array {
    a74_need(a74_blob($raw)===A74_MANIFEST_BLOB,'manifest_hash');
    $m=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    a74_need(($m['schema']??'')==='retained_anex82_v74'&&($m['direct_anex_count']??0)===82&&count($m['direct_anex_candidates']??[])===82,'manifest_scope');
    $out=[];$native=[];
    foreach($m['direct_anex_candidates'] as $r){
        $id=$r['local_hotel_id']??null;$n=$r['anex_hotel_id']??null;
        a74_need(is_int($id)&&$id>0&&is_string($n)&&preg_match('/^[1-9][0-9]{0,7}$/D',$n)===1&&!isset($out[$id])&&!isset($native[$n]),'manifest_pair');
        $out[$id]=$n;$native[$n]=true;
    }
    ksort($out,SORT_NUMERIC);return $out;
}
function a74_q(PDO $db,string $sql,array $args=[]): array {
    $st=$db->prepare($sql);a74_need($st!==false&&$st->execute(array_values($args)),'query_failed');
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);a74_need(count($rows)<=50000,'row_limit');return $rows;
}
function a74_target(int $id,array $c): array {
    $h=$c['hotels'][$id]??null;$reasons=[];
    if(!$h||(int)$h['is_active']!==1)$reasons[]='target_inactive_or_missing';
    if($h&&preg_match('/^(?:россия|абхазия|russia|abkhazia|russian federation)$/ui',trim((string)$h['country_name']))===1)$reasons[]='excluded_country';
    if(empty($c['live'][$id]))$reasons[]='outside_tv_live30';
    if(!empty($c['manual_target'][$id]))$reasons[]='manual_target_protected';
    return $reasons;
}
function a74_samo(int $id,string $cat,array $c): array {
    $s=$c['samo_source'][$cat]??[];$t=$c['samo_target'][$id]??[];$reasons=a74_target($id,$c);
    $status='source_missing_needs_identity_proof';
    if($s){
        if(count($s)!==1){$status='hold';$reasons[]='duplicate_source';}
        else {
            $r=$s[0];$local=$r['local_hotel_id'];$decision=(string)$r['decision_status'];
            if($local!==null&&$local!==''&&(int)$local!==$id){$status='hold';$reasons[]='source_other_target';}
            elseif($decision==='accepted'&&(int)$local===$id)$status='already_accepted_same';
            elseif($decision==='pending'&&($local===null||$local===''))$status='pending_unassigned_needs_identity_review';
            elseif($decision==='pending'&&(int)$local===$id)$status='pending_same_needs_identity_review';
            else {$status='hold';$reasons[]='source_decision_protected_or_invalid';}
            if(($r['evidence_valid']??false)!==true){$reasons[]='source_evidence_invalid';$status='hold';}
        }
    }
    foreach($t as $r)if((string)$r['external_hotel_id']!==$cat){$reasons[]='target_catalog_other_source';break;}
    if(empty($c['effective']['by_local'][$id]))$reasons[]='direct_anex_anchor_missing';
    if($id===1772&&$cat==='37255')$reasons[]='known_marhaba_target_collision';
    if($reasons)$status='hold';
    return ['local_hotel_id'=>$id,'catalog_id'=>$cat,'name'=>$c['hotels'][$id]['name']??null,'status'=>$status,'reasons'=>array_values(array_unique($reasons)),'current_source'=>$s,'current_target_catalogs'=>$t,'direct_anex_ids'=>array_map('strval',array_keys($c['effective']['by_local'][$id]??[])),'safe_to_write_now'=>false];
}
function a74_anex(int $id,string $native,array $c): array {
    $effective=$c['effective']['by_native'][(int)$native]??null;$maps=$c['mapping_source'][$native]??[];$reasons=a74_target($id,$c);
    $status='source_missing_needs_identity_proof';
    if($effective!==null){$status=$effective===$id?'already_effective_same':'hold';if($effective!==$id)$reasons[]='effective_source_other_target';}
    elseif($maps){$status='hold';$reasons[]='existing_non_effective_mapping_protected';}
    if(!empty($c['manual_source'][$native]))$reasons[]='manual_source_protected';
    if(!empty($c['excluded_source'][$native]))$reasons[]='pair_exclusion_protected';
    foreach($c['mapping_target'][$id]??[] as $r)if((string)$r['anex_hotel_id']!==$native){$reasons[]='target_mapping_other_native';break;}
    // SAMO operator_5 is corroboration/conflict only. It never closes the direct ANEX edge.
    $op5=$c['op5_source'][$native]??[];
    foreach($op5 as $r){if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null&&(int)$r['local_hotel_id']!==$id){$reasons[]='operator5_other_target';break;}}
    if($reasons)$status='hold';
    return ['local_hotel_id'=>$id,'anex_hotel_id'=>$native,'name'=>$c['hotels'][$id]['name']??null,'status'=>$status,'reasons'=>array_values(array_unique($reasons)),'effective_target'=>$effective,'current_mappings'=>$maps,'target_mappings'=>$c['mapping_target'][$id]??[],'operator5_support'=>$op5,'safe_to_write_now'=>false];
}
function a74_snapshot(PDO $db,array $pairs): array {
    require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
    a74_need(!$db->inTransaction(),'nested_transaction');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try {
        $ids=array_values(array_unique(array_merge(array_keys(A74_S15),array_keys($pairs))));sort($ids,SORT_NUMERIC);
        $cats=array_values(A74_S15);$natives=array_values($pairs);
        $ih=implode(',',array_fill(0,count($ids),'?'));$ch=implode(',',array_fill(0,count($cats),'?'));$nh=implode(',',array_fill(0,count($natives),'?'));
        $c=['hotels'=>[],'live'=>[],'samo_source'=>[],'samo_target'=>[],'mapping_source'=>[],'mapping_target'=>[],'op5_source'=>[],'manual_source'=>[],'manual_target'=>[],'excluded_source'=>[]];
        foreach(a74_q($db,"SELECT id,name,country_name,is_active FROM catalog_hotels WHERE id IN ($ih)",$ids) as $r)$c['hotels'][(int)$r['id']]=$r;
        foreach(a74_q($db,"SELECT DISTINCT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN ($ih) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)",$ids) as $r)$c['live'][(int)$r['hotel_id']]=true;
        $sql="SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE (supplier_namespace='andromeda_catalog' AND (external_hotel_id IN ($ch) OR local_hotel_id IN ($ih))) OR (supplier_namespace='operator_5' AND external_hotel_id IN ($nh))";
        foreach(a74_q($db,$sql,array_merge($cats,$ids,$natives)) as $r){
            $raw=(string)$r['evidence_json'];$ev=json_decode($raw,true);
            $r['evidence_valid']=is_array($ev)&&hash_equals((string)$r['evidence_sha256'],hash('sha256',$raw));
            $r['evidence_keys']=is_array($ev)?array_keys($ev):[];unset($r['evidence_json']);
            if($r['supplier_namespace']==='andromeda_catalog'){$c['samo_source'][(string)$r['external_hotel_id']][]=$r;if($r['local_hotel_id']!==null)$c['samo_target'][(int)$r['local_hotel_id']][]=$r;}
            else $c['op5_source'][(string)$r['external_hotel_id']][]=$r;
        }
        // Exact canonical schema; decision_status belongs to decisions, not mappings.
        foreach(a74_q($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,approval_policy,enabled,scope FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($nh) OR catalog_hotel_id IN ($ih)",array_merge($natives,$ids)) as $r){$c['mapping_source'][(string)$r['anex_hotel_id']][]=$r;$c['mapping_target'][(int)$r['catalog_hotel_id']][]=$r;}
        foreach(a74_q($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($nh) OR catalog_hotel_id IN ($ih)",array_merge($natives,$ids)) as $r){$c['manual_source'][(string)$r['anex_hotel_id']][]=$r;if($r['catalog_hotel_id']!==null)$c['manual_target'][(int)$r['catalog_hotel_id']][]=$r;}
        foreach(a74_q($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($nh) OR catalog_hotel_id IN ($ih)",array_merge($natives,$ids)) as $r)$c['excluded_source'][(string)$r['anex_hotel_id']][]=$r;
        $c['effective']=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
        $out=['samo_rows'=>[],'anex_rows'=>[],'samo_counts'=>[],'anex_counts'=>[]];
        foreach(A74_S15 as $id=>$cat){$row=a74_samo($id,$cat,$c);$out['samo_rows'][]=$row;$out['samo_counts'][$row['status']]=($out['samo_counts'][$row['status']]??0)+1;}
        foreach($pairs as $id=>$native){$row=a74_anex($id,$native,$c);$out['anex_rows'][]=$row;$out['anex_counts'][$row['status']]=($out['anex_counts'][$row['status']]??0)+1;}
        ksort($out['samo_counts']);ksort($out['anex_counts']);$out['effective_anex_native_count']=$c['effective']['native_count'];
        $db->rollBack();return $out;
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function a74_main(array $argv): int {
    a74_need(PHP_SAPI==='cli'&&($argv[1]??'')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    a74_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===A74_OP&&preg_match('/^[0-9a-f]{40}$/D',$head)===1,'runtime_scope');
    $pairs=a74_pairs(a74_read((string)getenv('MATCH_ANEX82')));
    $reservation=json_decode(a74_read($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    a74_need(($reservation['operation']??'')===A74_OP&&($reservation['source_sha']??'')===$head&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    foreach(['execution-started.json','result.json','receipt.json'] as $f)a74_need(!file_exists($dir.'/'.$f),'terminal_no_replay');
    a74_save($dir.'/execution-started.json',['operation'=>A74_OP,'source_sha'=>$head]);
    $out=['operation'=>A74_OP,'source_sha'=>$head,'manifest_blob'=>A74_MANIFEST_BLOB,'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0,'no_replay'=>true,'safe_to_write_now'=>false,'identity_proof_revalidated'=>false];
    $stage='bootstrap';
    try {
        $boot=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');a74_need(is_file($boot)&&!is_link($boot),'bootstrap_missing');require_once $boot;
        a74_need(function_exists('v2_data_db'),'db_factory');$stage='read_only_snapshot';$out+=a74_snapshot(v2_data_db(),$pairs);$out['state']='completed_read_only_current';
    }catch(Throwable $e){$out['state']='failed_read_only_current';$out['failure_stage']=$stage;$out['error_class']=get_class($e);$out['reason']=preg_match('/^[a-z][a-z0-9_]{0,99}$/D',$e->getMessage())===1?$e->getMessage():'audit_failed';}
    $out['generated_at_utc']=gmdate('c');$hash=a74_save($dir.'/result.json',$out);
    a74_save($dir.'/receipt.json',['operation'=>A74_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0,'no_replay'=>true]);
    echo a74_json(array_diff_key($out,['samo_rows'=>true,'anex_rows'=>true]))."\n";return $out['state']==='completed_read_only_current'?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(a74_main($argv));
