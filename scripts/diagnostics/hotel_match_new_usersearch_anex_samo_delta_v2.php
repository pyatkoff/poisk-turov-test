<?php
declare(strict_types=1);

const NUD_OP = 'hotel-match-new-usersearch-anex-samo-delta-1971-20260920-v2';
const NUD_CUTOFF = '2026-09-20 12:00:00'; // database observed_at domain; do not reinterpret as UTC.
const NUD_PRIOR_OP = 'hotel-match-new-usersearch-anex-samo-intake-1971-20260920-v1';
const NUD_PRIOR_SHA = '3f7ffe8224ca0ca346fa24d00980ea46d54f9a7caf6ca2fd178c26995856695b';
const NUD_PRIOR_SEEDS = 656;
const NUD_MAX_HOTELS = 1000;

function nud_need(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function nud_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function nud_save(string $path,array $v):string{$raw=nud_json($v);$f=fopen($path,'xb');nud_need(is_resource($f),'exclusive_output');try{nud_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))nud_need(fsync($f),'output_sync');}finally{fclose($f);}nud_need(file_get_contents($path)===$raw,'output_readback');return hash('sha256',$raw);}
function nud_rows(PDO $db,string $sql,array $p=[],int $cap=30000):array{$q=$db->prepare($sql);$q->execute(array_values($p));$rows=$q->fetchAll(PDO::FETCH_ASSOC);nud_need(count($rows)<=$cap,'row_limit');return$rows;}
function nud_pos(mixed $v):?int{if(is_int($v))return$v>0?$v:null;if(!is_string($v)||preg_match('/^[1-9][0-9]{0,9}$/D',$v)!==1)return null;$n=(int)$v;return$n>0?$n:null;}
function nud_ages(array $row):bool{$children=(string)($row['children_count']??'');$raw=(string)($row['child_ages_signature']??'');return$children==='0'&&$raw==='';}
function nud_prior_ids(array $prior):array{
    nud_need(($prior['operation_id']??'')===NUD_PRIOR_OP&&($prior['state']??'')==='completed_read_only','prior_terminal_state');
    nud_need(($prior['database_time_cutoff']??'')===NUD_CUTOFF&&(int)($prior['seed_hotel_count']??-1)===NUD_PRIOR_SEEDS,'prior_scope');
    $rows=$prior['seed_rows']??null;nud_need(is_array($rows)&&count($rows)===NUD_PRIOR_SEEDS,'prior_seed_rows');$ids=[];
    foreach($rows as$row){nud_need(is_array($row),'prior_seed_shape');$id=nud_pos($row['hotel_id']??null);nud_need($id!==null&&$id!==420,'prior_seed_id');$ids[$id]=true;}
    nud_need(count($ids)===NUD_PRIOR_SEEDS,'prior_seed_unique');ksort($ids,SORT_NUMERIC);return$ids;
}
/** Route only a hotel that was absent from every sealed #3211 seed row. No HTTP/write authority. */
function nud_route(array $hotel,array $history,array $contexts,array $nativeIds,array $identities,?array $country):array{
    $id=nud_pos($hotel['id']??null);$base=['local_hotel_id'=>$id,'hotel_raw'=>$hotel,'user_search_history'=>$history,'anex_native_ids'=>$nativeIds,'country_raw'=>$country,'saved_identity_rows'=>$identities,'retained_anex_contexts'=>$contexts,'safe_to_query_now'=>false,'safe_to_write_now'=>false];
    if($id===null||$id===420)return$base+['state'=>'excluded_protected_or_invalid_target'];
    if((int)($hotel['is_active']??0)!==1)return$base+['state'=>'inactive_target'];
    if($nativeIds===[])return$base+['state'=>'no_effective_anex_anchor'];
    foreach($identities as$r)if(($r['supplier_namespace']??'')==='andromeda_catalog'&&($r['decision_status']??'')==='accepted')return$base+['state'=>'samo_already_accepted'];
    if($country===null||!is_string($country['name']??null))return$base+['state'=>'country_unresolved'];
    if(in_array(trim($country['name']),['Россия','Абхазия','Russia','Russian Federation','Abkhazia'],true))return$base+['state'=>'excluded_country'];
    if($identities!==[])return$base+['state'=>'saved_identity_evidence_review_first'];
    $usable=[];foreach($contexts as$c){
        if(nud_pos($c['hotel_id']??null)!==$id||(string)($c['operator_id']??'')!=='13'||(string)($c['source']??'')!=='user_search')continue;
        if((string)($c['country_id']??'')!==(string)($hotel['country_id']??'')||nud_pos($c['departure_id']??null)===null||nud_pos($c['nights']??null)===null||nud_pos($c['adults']??null)===null||!nud_ages($c))continue;
        if(!is_string($c['departure_date']??null)||preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$c['departure_date'])!==1||!is_scalar($c['tour_id']??null)||trim((string)$c['tour_id'])==='')continue;
        $usable[]=$c;
    }
    if($usable===[])return$base+['state'=>'no_usable_retained_anex_context'];
    usort($usable,static fn($a,$b)=>[$a['departure_date'],(string)$a['observed_at'],(string)$a['id']]<=>[$b['departure_date'],(string)$b['observed_at'],(string)$b['id']]);
    return$base+['state'=>'new_seed_missing_samo_edge_needs_evidence_dedupe','preferred_context'=>$usable[0]];
}

if(($argv[1]??'')==='--self-test'){
    $prior=['operation_id'=>NUD_PRIOR_OP,'state'=>'completed_read_only','database_time_cutoff'=>NUD_CUTOFF,'seed_hotel_count'=>NUD_PRIOR_SEEDS,'seed_rows'=>[]];for($i=1;$i<=NUD_PRIOR_SEEDS;$i++)$prior['seed_rows'][]=['hotel_id'=>1000+$i];$ids=nud_prior_ids($prior);nud_need(count($ids)===NUD_PRIOR_SEEDS&&isset($ids[1001],$ids[1656]),'self_prior');
    $h=['id'=>1001,'name'=>'EXAMPLE','is_active'=>1,'country_id'=>4];$hist=['first_seen_at'=>'2026-09-01 00:00:00'];$country=['id'=>4,'name'=>'Турция'];$c=['id'=>1,'hotel_id'=>1001,'source'=>'user_search','operator_id'=>13,'country_id'=>4,'departure_id'=>1,'departure_date'=>'2026-10-01','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','tour_id'=>'123','observed_at'=>'2026-09-20 16:00:00'];
    $r=nud_route($h,$hist,[$c],[9001],[],$country);nud_need($r['state']==='new_seed_missing_samo_edge_needs_evidence_dedupe'&&!$r['safe_to_query_now']&&!$r['safe_to_write_now'],'self_eligible');
    nud_need(nud_route($h,$hist,[$c],[],[],$country)['state']==='no_effective_anex_anchor','self_no_anchor');$rows=[['supplier_namespace'=>'andromeda_catalog','decision_status'=>'accepted']];nud_need(nud_route($h,$hist,[$c],[9001],$rows,$country)['state']==='samo_already_accepted','self_accepted');$rows=[['supplier_namespace'=>'operator_5','decision_status'=>'pending']];nud_need(nud_route($h,$hist,[$c],[9001],$rows,$country)['state']==='saved_identity_evidence_review_first','self_saved');
    $bad=$c;$bad['operator_id']=2;nud_need(nud_route($h,$hist,[$bad],[9001],[],$country)['state']==='no_usable_retained_anex_context','self_operator');$bad=$c;$bad['children_count']=1;$bad['child_ages_signature']='7';nud_need(nud_route($h,$hist,[$bad],[9001],[],$country)['state']==='no_usable_retained_anex_context','self_ages');$bad=$h;$bad['id']=420;nud_need(nud_route($bad,$hist,[$c],[9001],[],$country)['state']==='excluded_protected_or_invalid_target','self_protected');nud_need(nud_route($h,$hist,[$c],[9001],[],['name'=>'Россия'])['state']==='excluded_country','self_exclusion');
    echo "MATCH_NEW_USERSEARCH_DELTA_V2_SELFTEST_OK 9\n";exit(0);
}

nud_need(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===NUD_OP,'operation_guard');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.NUD_OP;nud_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'operation_directory');$source=(string)getenv('MATCH_SOURCE_SHA');nud_need(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);nud_need(($res['operation_id']??'')===NUD_OP&&($res['source_sha']??'')===$source&&($res['state']??'')==='reserved_before_db_read'&&($res['database_time_cutoff']??'')===NUD_CUTOFF&&($res['prior_result_sha256']??'')===NUD_PRIOR_SHA,'reservation_guard');
$priorPath=$dir.'/input/prior-result.json';nud_need(is_file($priorPath)&&hash_file('sha256',$priorPath)===NUD_PRIOR_SHA,'prior_result_hash');$prior=json_decode((string)file_get_contents($priorPath),true,128,JSON_THROW_ON_ERROR);$priorIds=nud_prior_ids($prior);
$base=['operation_id'=>NUD_OP,'source_sha'=>$source,'database_time_cutoff'=>NUD_CUTOFF,'prior_operation_id'=>NUD_PRIOR_OP,'prior_result_sha256'=>NUD_PRIOR_SHA,'prior_seed_count'=>count($priorIds),'supplier_calls'=>0,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,'safe_to_query_now'=>false,'safe_to_write_now'=>false];$db=null;
try{
    nud_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);$root=realpath(getcwd());nud_need(is_string($root)&&basename($root)==='anytoour.ru','root_guard');require_once __DIR__.'/hotel_match_anex_effective_coverage.php';require_once(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    foreach(['tour_price_observations','catalog_hotels','catalog_countries','andromeda_hotel_identities','anex_hotel_search_mappings','anex_hotel_decisions']as$table){$eng=nud_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);nud_need(count($eng)===1&&strtoupper((string)$eng[0]['ENGINE'])==='INNODB','table_engine');}
    $clock=nud_rows($db,'SELECT NOW() AS database_now,UTC_TIMESTAMP() AS utc_now,@@session.time_zone AS session_time_zone')[0];$seeds=nud_rows($db,"SELECT hotel_id,COUNT(*) AS recent_anex_observation_count FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND observed_at>=? AND departure_date>=CURDATE() AND price>0 AND hotel_id<>420 GROUP BY hotel_id ORDER BY hotel_id LIMIT 1001",[NUD_CUTOFF],NUD_MAX_HOTELS);
    $currentIds=[];foreach($seeds as$s){$id=nud_pos($s['hotel_id']??null);nud_need($id!==null,'seed_identity');$currentIds[$id]=true;}nud_need(count($currentIds)<=NUD_MAX_HOTELS,'seed_cap');$deltaIds=array_values(array_diff(array_keys($currentIds),array_keys($priorIds)));sort($deltaIds,SORT_NUMERIC);
    $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$dossiers=[];$states=[];$histories=[];
    if($deltaIds!==[]){$ph=implode(',',array_fill(0,count($deltaIds),'?'));foreach(nud_rows($db,"SELECT hotel_id,MIN(observed_at) AS first_seen_at,MAX(observed_at) AS last_seen_at,COUNT(*) AS user_search_observation_count FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($ph) GROUP BY hotel_id",$deltaIds,NUD_MAX_HOTELS)as$h)$histories[(int)$h['hotel_id']]=$h;
        $hotels=nud_rows($db,"SELECT * FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$deltaIds,NUD_MAX_HOTELS);$found=[];foreach($hotels as$hotel){$id=(int)$hotel['id'];$found[$id]=true;$hist=$histories[$id]??[];$nativeIds=array_map('intval',array_keys($coverage['by_local'][$id]??[]));sort($nativeIds,SORT_NUMERIC);$ident=nud_rows($db,'SELECT * FROM andromeda_hotel_identities WHERE local_hotel_id=? ORDER BY supplier_namespace,external_hotel_id',[$id]);$countries=nud_rows($db,'SELECT * FROM catalog_countries WHERE id=?',[$hotel['country_id']??null]);$country=count($countries)===1?$countries[0]:null;$contexts=nud_rows($db,"SELECT id,source,search_id,tour_id,hotel_id,departure_id,country_id,region_id,subregion_id,departure_date,nights,adults,children_count,child_ages_signature,operator_id,observed_at FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND hotel_id=? AND departure_date>=CURDATE() AND price>0 ORDER BY observed_at DESC,id DESC LIMIT 30001",[$id]);$d=nud_route($hotel,$hist,$contexts,$nativeIds,$ident,$country);$dossiers[]=$d;$states[$d['state']]=($states[$d['state']]??0)+1;}
        foreach($deltaIds as$id)if(!isset($found[$id])){$states['missing_catalog_row']=($states['missing_catalog_row']??0)+1;$dossiers[]=['local_hotel_id'=>$id,'state'=>'missing_catalog_row','safe_to_query_now'=>false,'safe_to_write_now'=>false];}
    }
    $db->rollBack();$result=$base+['state'=>'completed_read_only','clock'=>$clock,'current_seed_hotel_count'=>count($currentIds),'prior_seed_overlap_count'=>count(array_intersect_key($currentIds,$priorIds)),'new_seed_hotel_count'=>count($deltaIds),'new_seed_hotel_ids'=>$deltaIds,'route_counts'=>$states,'current_seed_rows'=>$seeds,'new_seed_histories'=>$histories,'dossiers'=>$dossiers];
}catch(Throwable$e){if($db&&$db->inTransaction())$db->rollBack();$reason=preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error';$result=$base+['state'=>'failed_no_replay','reason'=>$reason,'error_class'=>get_class($e)];}
$hash=nud_save($dir.'/result.json',$result);nud_save($dir.'/receipt.json',['operation_id'=>NUD_OP,'source_sha'=>$source,'state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo nud_json(['state'=>$result['state'],'current_seed_hotel_count'=>$result['current_seed_hotel_count']??null,'new_seed_hotel_count'=>$result['new_seed_hotel_count']??null,'route_counts'=>$result['route_counts']??null,'result_sha256'=>$hash]);exit($result['state']==='completed_read_only'?0:1);
