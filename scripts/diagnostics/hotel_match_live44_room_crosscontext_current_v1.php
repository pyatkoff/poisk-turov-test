<?php
declare(strict_types=1);
require_once __DIR__ . '/hotel_match_operator_fingerprint_room_evidence_v1.php';

const M44X_OP = 'hotel-match-live44-room-crosscontext-current-1971-20260920-v1';
const M44X_INPUT_SHA = '8811a26e6416c2b10d605b2871f4615f5e2e6de68e78d5f9cac4dedd4e2d14f2';

function m44x_need(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function m44x_json(array $v): string { return json_encode($v, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function m44x_save(string $path, array $v): string {
    $raw=m44x_json($v); $f=fopen($path,'xb'); m44x_need(is_resource($f),'immutable_output');
    try { m44x_need(fwrite($f,$raw)===strlen($raw) && fflush($f),'output_write'); if(function_exists('fsync'))m44x_need(fsync($f),'output_sync'); }
    finally { fclose($f); }
    m44x_need(file_get_contents($path)===$raw,'output_readback'); return hash('sha256',$raw);
}
function m44x_rows(PDO $db,string $sql,array $params=[]):array{
    $q=$db->prepare($sql);$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);m44x_need(count($rows)<=30000,'row_limit');return $rows;
}
function m44x_pos(mixed $v):?int{if(!is_scalar($v)||!preg_match('/^[1-9][0-9]*$/D',(string)$v))return null;$n=(int)$v;return $n>0?$n:null;}
function m44x_context(array $r):string{
    return implode('|',[(string)($r['search_id']??''),(string)($r['tour_id']??''),(string)($r['departure_date']??''),(string)($r['nights']??''),(string)($r['adults']??''),(string)($r['children_count']??''),(string)($r['child_ages_signature']??'')]);
}
function m44x_match(array $tvRows,array $samoRooms):array{
    $tv=[];$sa=[];
    foreach($tvRows as $r){
        $id=m44x_pos($r['room_id']??null);$raw=is_scalar($r['room_type']??null)?trim((string)$r['room_type']):'';$key=hmf_room_key($raw);
        if($id===null||$raw===''||$key==='')continue;
        $tv[$key]['ids'][$id]=true;$tv[$key]['raw'][$raw]=true;$tv[$key]['contexts'][m44x_context($r)]=true;
        if(isset($r['observed_at'])&&is_scalar($r['observed_at']))$tv[$key]['observed_at'][(string)$r['observed_at']]=true;
        if(isset($r['meal_id'])&&$r['meal_id']!==null)$tv[$key]['meals'][(string)$r['meal_id']]=true;
    }
    foreach($samoRooms as $r){
        if(!is_array($r))continue;$id=m44x_pos($r['room_key']??null);$raw=is_scalar($r['room_raw']??null)?trim((string)$r['room_raw']):'';$key=hmf_room_key($raw);
        if($id===null||$raw===''||$key==='')continue;
        $sa[$key]['ids'][$id]=true;$sa[$key]['raw'][$raw]=true;
        foreach(($r['meal_variants']??[]) as $meal){if(!is_array($meal))continue;$mk=is_scalar($meal['meal_key']??null)?trim((string)$meal['meal_key']):'';$mr=is_scalar($meal['meal_raw']??null)?trim((string)$meal['meal_raw']):'';if($mk!==''||$mr!=='')$sa[$key]['meals'][$mk.'|'.$mr]=true;}
        foreach(($r['source_evidence']??[]) as $e){if(!is_array($e))continue;$file=is_scalar($e['file']??null)?trim((string)$e['file']):'';$sha=is_scalar($e['sha256']??null)?trim((string)$e['sha256']):'';if($file!==''&&preg_match('/^[0-9a-f]{64}$/D',$sha))$sa[$key]['sources'][$file.'|'.$sha]=true;}
    }
    $ok=[];$holds=[];
    foreach(array_intersect(array_keys($tv),array_keys($sa)) as $key){
        $tids=array_map('intval',array_keys($tv[$key]['ids']??[]));$sids=array_map('intval',array_keys($sa[$key]['ids']??[]));sort($tids,SORT_NUMERIC);sort($sids,SORT_NUMERIC);
        $base=['room_key'=>$key,'tv_room_ids'=>$tids,'samo_room_keys'=>$sids,'tv_rooms'=>array_values(array_keys($tv[$key]['raw']??[])),'samo_rooms'=>array_values(array_keys($sa[$key]['raw']??[])),'tv_contexts'=>array_values(array_keys($tv[$key]['contexts']??[])),'tv_observed_at'=>array_values(array_keys($tv[$key]['observed_at']??[])),'tv_meal_ids'=>array_values(array_keys($tv[$key]['meals']??[])),'samo_meal_variants'=>array_values(array_keys($sa[$key]['meals']??[])),'samo_sources'=>array_values(array_keys($sa[$key]['sources']??[])),'safe_to_write_now'=>false];
        foreach(['tv_rooms','samo_rooms','tv_contexts','tv_observed_at','tv_meal_ids','samo_meal_variants','samo_sources'] as $k)sort($base[$k],SORT_STRING);
        if(count($tids)===1&&count($sids)===1){$base['tv_room_id']=$tids[0];$base['samo_room_key']=$sids[0];$base['evidence_class']='same_hotel_cross_context_exact_room_key';$ok[]=$base;}
        else{$base['reason']=count($tids)!==1?'ambiguous_tv_room_id':'ambiguous_samo_room_key';$holds[]=$base;}
    }
    usort($ok,static fn($a,$b)=>[$a['room_key'],$a['tv_room_id'],$a['samo_room_key']]<=>[$b['room_key'],$b['tv_room_id'],$b['samo_room_key']]);
    usort($holds,static fn($a,$b)=>[$a['room_key'],$a['reason']]<=>[$b['room_key'],$b['reason']]);
    return ['concepts'=>$ok,'holds'=>$holds];
}

if(($argv[1]??'')==='--self-test'){
    $tv=[['room_id'=>101,'room_type'=>'DELUXE ROOM','search_id'=>1,'tour_id'=>'a','departure_date'=>'2026-10-01','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','observed_at'=>'x'],['room_id'=>202,'room_type'=>'FAMILY SEA VIEW ROOM','search_id'=>2,'tour_id'=>'b','departure_date'=>'2026-11-01','nights'=>10,'adults'=>2,'children_count'=>1,'child_ages_signature'=>'7','observed_at'=>'y']];
    $sa=[['room_key'=>'501','room_raw'=>'DELUXE','meal_variants'=>[],'source_evidence'=>[]],['room_key'=>'502','room_raw'=>'FAMILY SEA VIEW','meal_variants'=>[],'source_evidence'=>[]]];
    $m=m44x_match($tv,$sa);m44x_need(count($m['concepts'])===2&&count($m['holds'])===0,'self_count');$by=[];foreach($m['concepts'] as $c)$by[$c['room_key']]=$c;m44x_need(isset($by['deluxe'],$by['family sea view']),'self_qualifier');m44x_need(count($by['deluxe']['tv_contexts'])===1,'self_context');
    $tv[]=['room_id'=>303,'room_type'=>'DELUXE','search_id'=>3,'tour_id'=>'c','departure_date'=>'2026-12-01','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','observed_at'=>'z'];$m=m44x_match($tv,$sa);m44x_need(count($m['holds'])===1&&$m['holds'][0]['reason']==='ambiguous_tv_room_id','self_ambiguous');echo "MATCH_LIVE44_ROOM_CROSSCONTEXT_SELFTEST_OK\n";exit(0);
}

m44x_need(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===M44X_OP,'operation_guard');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.M44X_OP;m44x_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'operation_directory');
$source=(string)getenv('MATCH_SOURCE_SHA');m44x_need(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');m44x_need(hash_file('sha256',$dir.'/input.json')===M44X_INPUT_SHA,'input_digest');
$input=json_decode((string)file_get_contents($dir.'/input.json'),true,128,JSON_THROW_ON_ERROR);m44x_need(($input['schema']??'')==='match-live44-room-current-input-v1'&&($input['input_count']??0)===44&&count($input['rows']??[])===44,'input_guard');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);m44x_need(($res['operation_id']??'')===M44X_OP&&($res['source_sha']??'')===$source&&($res['state']??'')==='reserved_before_db_read','reservation_guard');
$base=['operation_id'=>M44X_OP,'source_sha'=>$source,'input_sha256'=>M44X_INPUT_SHA,'supplier_calls'=>0,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'room_mapping_writes'=>0,'no_replay'=>true];
$db=null;
try{
    m44x_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);$root=realpath(getcwd());m44x_need(is_string($root)&&basename($root)==='anytoour.ru','root_guard');require_once(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    foreach(['tour_price_observations','catalog_hotels','andromeda_hotel_identities'] as $t){$e=m44x_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);m44x_need(count($e)===1&&strtoupper((string)$e[0]['ENGINE'])==='INNODB','table_engine');}
    $dossiers=[];$concepts=0;$holds=0;$clustersTv=0;$clustersConcept=0;$obs=0;$contexts=[];
    foreach($input['rows'] as $row){
        $tv=m44x_pos($row['tv_hotel_id']??null);$samo=is_scalar($row['samo_hotel_id']??null)?trim((string)$row['samo_hotel_id']):'';$native=is_scalar($row['native_anex_id']??null)?trim((string)$row['native_anex_id']):'';m44x_need($tv!==null&&preg_match('/^[1-9][0-9]*$/D',$samo)&&preg_match('/^[1-9][0-9]*$/D',$native),'identity_guard');
        $active=m44x_rows($db,'SELECT id,is_active FROM catalog_hotels WHERE id=?',[$tv]);$accepted=m44x_rows($db,"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND local_hotel_id=? AND decision_status='accepted'",[$samo,$tv]);
        if(count($active)!==1||(int)$active[0]['is_active']!==1||count($accepted)!==1){$dossiers[]=['tv_hotel_id'=>$tv,'samo_hotel_id'=>$samo,'native_anex_id'=>$native,'state'=>'current_hotel_cluster_hold','safe_to_write_now'=>false];continue;}
        $tvRows=m44x_rows($db,"SELECT observed_at,search_id,tour_id,hotel_id,departure_date,nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND hotel_id=? AND room_id IS NOT NULL AND room_id>0 AND room_type IS NOT NULL AND room_type<>'' ORDER BY observed_at DESC,id DESC",[$tv]);
        $obs+=count($tvRows);if($tvRows!==[])$clustersTv++;foreach($tvRows as $r)$contexts[m44x_context($r)]=true;
        $m=m44x_match($tvRows,is_array($row['samo_rooms']??null)?$row['samo_rooms']:[]);if($m['concepts']!==[])$clustersConcept++;
        foreach($m['concepts'] as &$c){$c['tv_hotel_id']=$tv;$c['samo_hotel_id']=$samo;$c['native_anex_id']=$native;$c['hotel_name']=(string)($row['hotel_name']??'');$c['samo_retained_context']=$row['context']??[];$c['native_anex_anchor']='retained_samo_original_hotel_key_exact';$c['accepted_hotel_evidence_sha256']=(string)($row['accepted_evidence_sha256']??'');$concepts++;}unset($c);
        foreach($m['holds'] as &$h){$h['tv_hotel_id']=$tv;$h['samo_hotel_id']=$samo;$h['native_anex_id']=$native;$h['hotel_name']=(string)($row['hotel_name']??'');$holds++;}unset($h);
        $dossiers[]=['tv_hotel_id'=>$tv,'samo_hotel_id'=>$samo,'native_anex_id'=>$native,'hotel_name'=>(string)($row['hotel_name']??''),'samo_retained_context'=>$row['context']??[],'tv_anex_room_observation_count'=>count($tvRows),'samo_room_count'=>count($row['samo_rooms']??[]),'room_concepts'=>$m['concepts'],'room_holds'=>$m['holds'],'state'=>$m['concepts']!==[]?'crosscontext_room_evidence_found':($tvRows===[]?'no_current_tv_anex_room_observation':'no_exact_room_key_overlap'),'safe_to_write_now'=>false];
    }
    $clock=m44x_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];$db->rollBack();
    $result=$base+['state'=>'completed_read_only','read_at_utc'=>$clock,'input_clusters'=>44,'input_samo_room_observations'=>119,'clusters_with_any_current_tv_anex_room_observations'=>$clustersTv,'current_tv_anex_room_observations'=>$obs,'distinct_tv_anex_room_contexts'=>count($contexts),'clusters_with_room_concepts'=>$clustersConcept,'room_concept_count'=>$concepts,'room_hold_count'=>$holds,'dossiers'=>$dossiers];
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();$reason=preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error';$result=$base+['state'=>'failed_no_replay','reason'=>$reason,'error_class'=>get_class($e)];}
$hash=m44x_save($dir.'/result.json',$result);m44x_save($dir.'/receipt.json',['operation_id'=>M44X_OP,'source_sha'=>$source,'state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'supplier_calls'=>0,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'room_mapping_writes'=>0,'no_replay'=>true]);
echo m44x_json(['state'=>$result['state'],'clusters_with_any_current_tv_anex_room_observations'=>$result['clusters_with_any_current_tv_anex_room_observations']??null,'current_tv_anex_room_observations'=>$result['current_tv_anex_room_observations']??null,'clusters_with_room_concepts'=>$result['clusters_with_room_concepts']??null,'room_concept_count'=>$result['room_concept_count']??null,'room_hold_count'=>$result['room_hold_count']??null,'result_sha256'=>$hash]);if($result['state']!=='completed_read_only')exit(2);
