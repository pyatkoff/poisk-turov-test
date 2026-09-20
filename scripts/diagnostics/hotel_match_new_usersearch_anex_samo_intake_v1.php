<?php
declare(strict_types=1);

const NUI_OP = 'hotel-match-new-usersearch-anex-samo-intake-1971-20260920-v1';
const NUI_CUTOFF = '2026-09-20 12:00:00'; // Database observed_at time, not an asserted UTC conversion.
const NUI_MAX_HOTELS = 1000;

function nui_need(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function nui_json(array $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function nui_save(string $path, array $v): string {
    $raw=nui_json($v); $f=fopen($path,'xb'); nui_need(is_resource($f),'exclusive_output');
    try { nui_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write'); if(function_exists('fsync')) nui_need(fsync($f),'output_sync'); }
    finally { fclose($f); }
    nui_need(file_get_contents($path)===$raw,'output_readback'); return hash('sha256',$raw);
}
function nui_rows(PDO $db,string $sql,array $p=[],int $cap=30000): array {
    $q=$db->prepare($sql); $q->execute(array_values($p)); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    nui_need(count($rows)<=$cap,'row_limit'); return $rows;
}
function nui_pos(mixed $v): ?int {
    if(is_int($v)) return $v>0?$v:null;
    if(!is_string($v)||preg_match('/^[1-9][0-9]{0,9}$/D',$v)!==1) return null;
    $n=(int)$v;return $n>0?$n:null;
}
function nui_ages(array $row): bool {
    $children=(string)($row['children_count']??'');$raw=(string)($row['child_ages_signature']??'');
    if($children==='0') return $raw==='';
    // Unknown child-age serialization is retained as a hold, never guessed for acquisition.
    return false;
}
/** Pure routing only; acceptance and HTTP authority are intentionally absent. */
function nui_route(array $hotel,array $history,array $contexts,array $nativeIds,array $identities,?array $country): array {
    $id=nui_pos($hotel['id']??null);$base=['local_hotel_id'=>$id,'hotel_raw'=>$hotel,'user_search_history'=>$history,'anex_native_ids'=>$nativeIds,'country_raw'=>$country,'saved_identity_rows'=>$identities,'retained_anex_contexts'=>$contexts,'safe_to_query_now'=>false,'safe_to_write_now'=>false];
    if($id===null||$id===420) return $base+['state'=>'excluded_protected_or_invalid_target'];
    if((int)($hotel['is_active']??0)!==1) return $base+['state'=>'inactive_target'];
    $first=$history['first_seen_at']??null;
    if(!is_string($first)||$first<NUI_CUTOFF) return $base+['state'=>'not_new_user_search_hotel'];
    if($nativeIds===[]) return $base+['state'=>'no_effective_anex_anchor'];
    foreach($identities as $r) {
        if(($r['supplier_namespace']??'')==='andromeda_catalog'&&($r['decision_status']??'')==='accepted') return $base+['state'=>'samo_already_accepted'];
    }
    if($country===null||!is_string($country['name']??null)) return $base+['state'=>'country_unresolved'];
    if(in_array(trim($country['name']),['Россия','Абхазия','Russia','Russian Federation','Abkhazia'],true)) return $base+['state'=>'excluded_country'];
    if($identities!==[]) return $base+['state'=>'saved_identity_evidence_review_first'];
    $usable=[];
    foreach($contexts as $c) {
        if(nui_pos($c['hotel_id']??null)!==$id||(string)($c['operator_id']??'')!=='13'||(string)($c['source']??'')!=='user_search') continue;
        if((string)($c['country_id']??'')!==(string)($hotel['country_id']??'')||nui_pos($c['departure_id']??null)===null||nui_pos($c['nights']??null)===null||nui_pos($c['adults']??null)===null||!nui_ages($c)) continue;
        if(!is_string($c['departure_date']??null)||preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$c['departure_date'])!==1||!is_scalar($c['tour_id']??null)||trim((string)$c['tour_id'])==='')continue;
        $usable[]=$c;
    }
    if($usable===[])return $base+['state'=>'no_usable_retained_anex_context'];
    usort($usable,static fn($a,$b)=>[$a['departure_date'],(string)$a['observed_at'],(string)$a['id']]<=>[$b['departure_date'],(string)$b['observed_at'],(string)$b['id']]);
    return $base+['state'=>'new_missing_samo_edge_needs_evidence_dedupe','preferred_context'=>$usable[0]];
}

if(($argv[1]??'')==='--self-test') {
    $h=['id'=>1001,'name'=>'EXAMPLE','is_active'=>1,'country_id'=>4];$hist=['first_seen_at'=>NUI_CUTOFF];$country=['id'=>4,'name'=>'Турция'];
    $c=['id'=>1,'hotel_id'=>1001,'source'=>'user_search','operator_id'=>13,'country_id'=>4,'departure_id'=>1,'departure_date'=>'2026-10-01','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','tour_id'=>'123','observed_at'=>NUI_CUTOFF];
    $r=nui_route($h,$hist,[$c],[9001],[],$country);nui_need($r['state']==='new_missing_samo_edge_needs_evidence_dedupe'&&!$r['safe_to_query_now']&&!$r['safe_to_write_now'],'self_eligible');
    nui_need(nui_route($h,['first_seen_at'=>'2026-09-19 12:00:00'],[$c],[9001],[],$country)['state']==='not_new_user_search_hotel','self_old');
    nui_need(nui_route($h,$hist,[$c],[],[],$country)['state']==='no_effective_anex_anchor','self_no_anchor');
    $rows=[['supplier_namespace'=>'andromeda_catalog','decision_status'=>'accepted']];nui_need(nui_route($h,$hist,[$c],[9001],$rows,$country)['state']==='samo_already_accepted','self_accepted');
    $rows=[['supplier_namespace'=>'operator_5','decision_status'=>'pending','evidence_json'=>'raw']];nui_need(nui_route($h,$hist,[$c],[9001],$rows,$country)['state']==='saved_identity_evidence_review_first','self_saved');
    $bad=$c;$bad['operator_id']=2;nui_need(nui_route($h,$hist,[$bad],[9001],[],$country)['state']==='no_usable_retained_anex_context','self_operator');
    $bad=$c;$bad['country_id']=1;nui_need(nui_route($h,$hist,[$bad],[9001],[],$country)['state']==='no_usable_retained_anex_context','self_country');
    $bad=$c;$bad['children_count']=1;$bad['child_ages_signature']='7';nui_need(nui_route($h,$hist,[$bad],[9001],[],$country)['state']==='no_usable_retained_anex_context','self_ages');
    $bad=$h;$bad['id']=420;nui_need(nui_route($bad,$hist,[$c],[9001],[],$country)['state']==='excluded_protected_or_invalid_target','self_protected');
    $bad=$h;$bad['is_active']=0;nui_need(nui_route($bad,$hist,[$c],[9001],[],$country)['state']==='inactive_target','self_inactive');
    nui_need(nui_route($h,$hist,[$c],[9001],[],null)['state']==='country_unresolved','self_country_unknown');
    nui_need(nui_route($h,$hist,[$c],[9001],[],['name'=>'Россия'])['state']==='excluded_country','self_exclusion');
    $r2=nui_route($h,$hist,[$c],[9001],[],$country);nui_need($r2['retained_anex_contexts']==[$c]&&$r2['hotel_raw']===$h,'self_raw');
    echo "MATCH_NEW_USERSEARCH_INTAKE_SELFTEST_OK 13\n";exit(0);
}

nui_need(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===NUI_OP,'operation_guard');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.NUI_OP;nui_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'operation_directory');
$source=(string)getenv('MATCH_SOURCE_SHA');nui_need(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);
nui_need(($res['operation_id']??'')===NUI_OP&&($res['source_sha']??'')===$source&&($res['state']??'')==='reserved_before_db_read'&&($res['database_time_cutoff']??'')===NUI_CUTOFF,'reservation_guard');
$base=['operation_id'=>NUI_OP,'source_sha'=>$source,'database_time_cutoff'=>NUI_CUTOFF,'supplier_calls'=>0,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,'safe_to_query_now'=>false,'safe_to_write_now'=>false];
$db=null;
try {
    nui_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);
    $root=realpath(getcwd());nui_need(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
    require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
    require_once(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    foreach(['tour_price_observations','catalog_hotels','catalog_countries','andromeda_hotel_identities','anex_hotel_search_mappings','anex_hotel_decisions'] as $table){
        $eng=nui_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);nui_need(count($eng)===1&&strtoupper((string)$eng[0]['ENGINE'])==='INNODB','table_engine');
    }
    $clock=nui_rows($db,'SELECT NOW() AS database_now,UTC_TIMESTAMP() AS utc_now,@@session.time_zone AS session_time_zone')[0];
    $seeds=nui_rows($db,"SELECT hotel_id,COUNT(*) AS recent_anex_observation_count FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND observed_at>=? AND departure_date>=CURDATE() AND price>0 AND hotel_id<>420 GROUP BY hotel_id ORDER BY hotel_id LIMIT 1001",[NUI_CUTOFF],NUI_MAX_HOTELS);
    $ids=[];foreach($seeds as $s){$id=nui_pos($s['hotel_id']??null);nui_need($id!==null,'seed_identity');$ids[$id]=true;}$ids=array_keys($ids);
    $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$dossiers=[];$states=[];$histories=[];
    if($ids!==[]) {
        $ph=implode(',',array_fill(0,count($ids),'?'));
        foreach(nui_rows($db,"SELECT hotel_id,MIN(observed_at) AS first_seen_at,MAX(observed_at) AS last_seen_at,COUNT(*) AS user_search_observation_count FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($ph) GROUP BY hotel_id",$ids,NUI_MAX_HOTELS) as $h)$histories[(int)$h['hotel_id']]=$h;
        foreach(nui_rows($db,"SELECT * FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids,NUI_MAX_HOTELS) as $hotel) {
            $id=(int)$hotel['id'];$hist=$histories[$id]??[];
            if(!is_string($hist['first_seen_at']??null)||$hist['first_seen_at']<NUI_CUTOFF){$states['not_new_user_search_hotel']=($states['not_new_user_search_hotel']??0)+1;continue;}
            $nativeIds=array_map('intval',array_keys($coverage['by_local'][$id]??[]));sort($nativeIds,SORT_NUMERIC);
            $ident=nui_rows($db,'SELECT * FROM andromeda_hotel_identities WHERE local_hotel_id=? ORDER BY supplier_namespace,external_hotel_id',[$id]);
            $countries=nui_rows($db,'SELECT * FROM catalog_countries WHERE id=?',[$hotel['country_id']??null]);$country=count($countries)===1?$countries[0]:null;
            $contexts=nui_rows($db,"SELECT id,source,search_id,tour_id,hotel_id,departure_id,country_id,region_id,subregion_id,departure_date,nights,adults,children_count,child_ages_signature,operator_id,observed_at FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND hotel_id=? AND departure_date>=CURDATE() AND price>0 ORDER BY observed_at DESC,id DESC LIMIT 30001",[$id]);
            $d=nui_route($hotel,$hist,$contexts,$nativeIds,$ident,$country);$dossiers[]=$d;$states[$d['state']]=($states[$d['state']]??0)+1;
        }
    }
    $db->rollBack();
    $result=$base+['state'=>'completed_read_only','clock'=>$clock,'seed_hotel_count'=>count($ids),'new_user_search_hotel_dossiers'=>count($dossiers),'route_counts'=>$states,'seed_rows'=>$seeds,'seed_histories'=>$histories,'dossiers'=>$dossiers];
} catch(Throwable $e) {
    if($db&&$db->inTransaction())$db->rollBack();
    $reason=preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error';
    $result=$base+['state'=>'failed_no_replay','reason'=>$reason,'error_class'=>get_class($e)];
}
$hash=nui_save($dir.'/result.json',$result);
nui_save($dir.'/receipt.json',['operation_id'=>NUI_OP,'source_sha'=>$source,'state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
echo nui_json(['state'=>$result['state'],'seed_hotel_count'=>$result['seed_hotel_count']??null,'new_user_search_hotel_dossiers'=>$result['new_user_search_hotel_dossiers']??null,'route_counts'=>$result['route_counts']??null,'result_sha256'=>$hash]);
exit($result['state']==='completed_read_only'?0:1);
