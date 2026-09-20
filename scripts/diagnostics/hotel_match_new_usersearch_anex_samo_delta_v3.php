<?php
declare(strict_types=1);

const NUD3_OP='hotel-match-new-usersearch-anex-samo-delta-1971-20260920-v3';
const NUD3_CUTOFF='2026-09-20 12:00:00'; // DB observed_at domain; never reinterpret timezone.
const NUD3_PRIOR_OP='hotel-match-new-usersearch-anex-samo-delta-1971-20260920-v2';
const NUD3_PRIOR_SHA='a6217f102e477f42a69b51cfbb009cf6efc438e343e8865aaea51bd5103e669a';
const NUD3_PRIOR_SEEDS=666;
const NUD3_MAX_HOTELS=1000;

function n3_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function n3_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function n3_save(string $path,array $v):string{$raw=n3_json($v);$f=fopen($path,'xb');n3_need(is_resource($f),'exclusive_output');try{n3_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))n3_need(fsync($f),'output_sync');}finally{fclose($f);}n3_need(file_get_contents($path)===$raw,'output_readback');return hash('sha256',$raw);}
function n3_rows(PDO $db,string $sql,array $args=[],int $cap=30000):array{$q=$db->prepare($sql);$q->execute(array_values($args));$rows=$q->fetchAll(PDO::FETCH_ASSOC);n3_need(count($rows)<=$cap,'row_limit');return $rows;}
function n3_pos(mixed $v):?int{if(is_int($v))return $v>0?$v:null;if(!is_string($v)||preg_match('/^[1-9][0-9]{0,9}$/D',$v)!==1)return null;$n=(int)$v;return $n>0?$n:null;}
function n3_no_children(array $r):bool{return (string)($r['children_count']??'')==='0'&&(string)($r['child_ages_signature']??'')==='';}
function n3_prior_ids(array $prior):array{
    n3_need(($prior['operation_id']??'')===NUD3_PRIOR_OP&&($prior['state']??'')==='completed_read_only','prior_terminal_state');
    n3_need(($prior['database_time_cutoff']??'')===NUD3_CUTOFF&&(int)($prior['current_seed_hotel_count']??-1)===NUD3_PRIOR_SEEDS,'prior_scope');
    $rows=$prior['current_seed_rows']??null;n3_need(is_array($rows)&&count($rows)===NUD3_PRIOR_SEEDS,'prior_seed_rows');$ids=[];
    foreach($rows as $row){n3_need(is_array($row),'prior_seed_shape');$id=n3_pos($row['hotel_id']??null);n3_need($id!==null&&$id!==420,'prior_seed_id');$ids[$id]=true;}
    n3_need(count($ids)===NUD3_PRIOR_SEEDS,'prior_seed_unique');ksort($ids,SORT_NUMERIC);return $ids;
}
function n3_route(array $hotel,array $history,array $contexts,array $nativeIds,array $identities,?array $country):array{
    $id=n3_pos($hotel['id']??null);$base=['local_hotel_id'=>$id,'hotel_raw'=>$hotel,'user_search_history'=>$history,'anex_native_ids'=>$nativeIds,'country_raw'=>$country,'saved_identity_rows'=>$identities,'retained_anex_contexts'=>$contexts,'safe_to_query_now'=>false,'safe_to_write_now'=>false];
    if($id===null||$id===420)return $base+['state'=>'excluded_protected_or_invalid_target'];
    if((int)($hotel['is_active']??0)!==1)return $base+['state'=>'inactive_target'];
    if($nativeIds===[])return $base+['state'=>'no_effective_anex_anchor'];
    foreach($identities as $r)if(($r['supplier_namespace']??'')==='andromeda_catalog'&&($r['decision_status']??'')==='accepted')return $base+['state'=>'samo_already_accepted'];
    if($country===null||!is_string($country['name']??null))return $base+['state'=>'country_unresolved'];
    if(in_array(trim((string)$country['name']),['Россия','Абхазия','Russia','Russian Federation','Abkhazia'],true))return $base+['state'=>'excluded_country'];
    if($identities!==[])return $base+['state'=>'saved_identity_evidence_review_first'];
    $usable=[];foreach($contexts as $c){
        if(n3_pos($c['hotel_id']??null)!==$id||(string)($c['operator_id']??'')!=='13'||(string)($c['source']??'')!=='user_search')continue;
        if((string)($c['country_id']??'')!==(string)($hotel['country_id']??'')||n3_pos($c['departure_id']??null)===null||n3_pos($c['nights']??null)===null||n3_pos($c['adults']??null)===null||!n3_no_children($c))continue;
        if(!is_string($c['departure_date']??null)||preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$c['departure_date'])!==1||!is_scalar($c['tour_id']??null)||trim((string)$c['tour_id'])==='')continue;
        $usable[]=$c;
    }
    if($usable===[])return $base+['state'=>'no_usable_retained_anex_context'];
    usort($usable,static fn($a,$b)=>[(string)$a['departure_date'],(string)$a['observed_at'],(string)$a['id']]<=>[(string)$b['departure_date'],(string)$b['observed_at'],(string)$b['id']]);
    return $base+['state'=>'new_seed_missing_samo_edge_needs_evidence_dedupe','preferred_context'=>$usable[0]];
}

if(($argv[1]??'')==='--self-test'){
    $prior=['operation_id'=>NUD3_PRIOR_OP,'state'=>'completed_read_only','database_time_cutoff'=>NUD3_CUTOFF,'current_seed_hotel_count'=>NUD3_PRIOR_SEEDS,'current_seed_rows'=>[]];for($i=1;$i<=NUD3_PRIOR_SEEDS;$i++)$prior['current_seed_rows'][]=['hotel_id'=>1000+$i];$ids=n3_prior_ids($prior);n3_need(count($ids)===NUD3_PRIOR_SEEDS&&isset($ids[1001],$ids[1666]),'self_prior');
    $h=['id'=>2001,'name'=>'EXAMPLE','is_active'=>1,'country_id'=>4];$hist=['first_seen_at'=>'2026-09-01 00:00:00'];$country=['id'=>4,'name'=>'Турция'];$c=['id'=>1,'hotel_id'=>2001,'source'=>'user_search','operator_id'=>13,'country_id'=>4,'departure_id'=>1,'departure_date'=>'2026-10-01','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','tour_id'=>'123','observed_at'=>'2026-09-20 16:20:00'];
    n3_need(n3_route($h,$hist,[$c],[9001],[],$country)['state']==='new_seed_missing_samo_edge_needs_evidence_dedupe','self_route');
    n3_need(n3_route($h,$hist,[$c],[],[],$country)['state']==='no_effective_anex_anchor','self_anchor');
    n3_need(n3_route($h,$hist,[$c],[9001],[['supplier_namespace'=>'andromeda_catalog','decision_status'=>'accepted']],$country)['state']==='samo_already_accepted','self_samo');
    echo "MATCH_NEW_USERSEARCH_DELTA_V3_SELFTEST_OK 4\n";exit(0);
}

n3_need(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===NUD3_OP,'operation_guard');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.NUD3_OP;n3_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'operation_directory');$source=(string)getenv('MATCH_SOURCE_SHA');n3_need(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);n3_need(($res['operation_id']??'')===NUD3_OP&&($res['source_sha']??'')===$source&&($res['state']??'')==='reserved_before_db_read'&&($res['database_time_cutoff']??'')===NUD3_CUTOFF&&($res['prior_result_sha256']??'')===NUD3_PRIOR_SHA,'reservation_guard');
$priorPath=$dir.'/input/prior-result.json';n3_need(is_file($priorPath)&&hash_file('sha256',$priorPath)===NUD3_PRIOR_SHA,'prior_hash');$prior=json_decode((string)file_get_contents($priorPath),true,128,JSON_THROW_ON_ERROR);$priorIds=n3_prior_ids($prior);
$base=['operation_id'=>NUD3_OP,'source_sha'=>$source,'database_time_cutoff'=>NUD3_CUTOFF,'prior_operation_id'=>NUD3_PRIOR_OP,'prior_result_sha256'=>NUD3_PRIOR_SHA,'prior_seed_count'=>count($priorIds),'supplier_calls'=>0,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,'safe_to_query_now'=>false,'safe_to_write_now'=>false];$db=null;
try{
    n3_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);$root=realpath(getcwd());n3_need(is_string($root)&&basename($root)==='anytoour.ru','root_guard');require_once __DIR__.'/hotel_match_anex_effective_coverage.php';require_once(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    foreach(['tour_price_observations','catalog_hotels','catalog_countries','andromeda_hotel_identities','anex_hotel_search_mappings','anex_hotel_decisions'] as $table){$e=n3_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);n3_need(count($e)===1&&strtoupper((string)$e[0]['ENGINE'])==='INNODB','table_engine');}
    $clock=n3_rows($db,'SELECT NOW() AS database_now,UTC_TIMESTAMP() AS utc_now,@@session.time_zone AS session_time_zone')[0];
    $seeds=n3_rows($db,"SELECT hotel_id,COUNT(*) AS recent_anex_observation_count FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND observed_at>=? AND departure_date>=CURDATE() AND price>0 AND hotel_id<>420 GROUP BY hotel_id ORDER BY hotel_id LIMIT 1001",[NUD3_CUTOFF],NUD3_MAX_HOTELS);
    $currentIds=[];foreach($seeds as $s){$id=n3_pos($s['hotel_id']??null);n3_need($id!==null,'seed_identity');$currentIds[$id]=true;}n3_need(count($currentIds)<=NUD3_MAX_HOTELS,'seed_cap');$deltaIds=array_values(array_diff(array_keys($currentIds),array_keys($priorIds)));sort($deltaIds,SORT_NUMERIC);
    $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$dossiers=[];$states=[];$histories=[];
    if($deltaIds!==[]){$ph=implode(',',array_fill(0,count($deltaIds),'?'));foreach(n3_rows($db,"SELECT hotel_id,MIN(observed_at) AS first_seen_at,MAX(observed_at) AS last_seen_at,COUNT(*) AS user_search_observation_count FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($ph) GROUP BY hotel_id",$deltaIds,NUD3_MAX_HOTELS) as $h)$histories[(int)$h['hotel_id']]=$h;
        $hotels=n3_rows($db,"SELECT * FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$deltaIds,NUD3_MAX_HOTELS);$found=[];foreach($hotels as $hotel){$id=(int)$hotel['id'];$found[$id]=true;$nativeIds=array_map('intval',array_keys($coverage['by_local'][$id]??[]));sort($nativeIds,SORT_NUMERIC);$ident=n3_rows($db,'SELECT * FROM andromeda_hotel_identities WHERE local_hotel_id=? ORDER BY supplier_namespace,external_hotel_id',[$id]);$countries=n3_rows($db,'SELECT * FROM catalog_countries WHERE id=?',[$hotel['country_id']??null]);$country=count($countries)===1?$countries[0]:null;$contexts=n3_rows($db,"SELECT id,source,search_id,tour_id,hotel_id,departure_id,country_id,region_id,subregion_id,departure_date,nights,adults,children_count,child_ages_signature,operator_id,observed_at FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND hotel_id=? AND departure_date>=CURDATE() AND price>0 ORDER BY observed_at DESC,id DESC LIMIT 30001",[$id]);$d=n3_route($hotel,$histories[$id]??[],$contexts,$nativeIds,$ident,$country);$dossiers[]=$d;$states[$d['state']]=($states[$d['state']]??0)+1;}
        foreach($deltaIds as $id)if(!isset($found[$id])){$states['missing_catalog_row']=($states['missing_catalog_row']??0)+1;$dossiers[]=['local_hotel_id'=>$id,'state'=>'missing_catalog_row','safe_to_query_now'=>false,'safe_to_write_now'=>false];}
    }
    $db->rollBack();$result=$base+['state'=>'completed_read_only','clock'=>$clock,'current_seed_hotel_count'=>count($currentIds),'prior_seed_overlap_count'=>count(array_intersect_key($currentIds,$priorIds)),'new_seed_hotel_count'=>count($deltaIds),'new_seed_hotel_ids'=>$deltaIds,'route_counts'=>$states,'current_seed_rows'=>$seeds,'new_seed_histories'=>$histories,'dossiers'=>$dossiers];
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();$reason=preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error';$result=$base+['state'=>'failed_no_replay','reason'=>$reason,'error_class'=>get_class($e)];}
$hash=n3_save($dir.'/result.json',$result);n3_save($dir.'/receipt.json',['operation_id'=>NUD3_OP,'source_sha'=>$source,'state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo n3_json(['state'=>$result['state'],'current_seed_hotel_count'=>$result['current_seed_hotel_count']??null,'new_seed_hotel_count'=>$result['new_seed_hotel_count']??null,'route_counts'=>$result['route_counts']??null,'result_sha256'=>$hash]);exit($result['state']==='completed_read_only'?0:1);
