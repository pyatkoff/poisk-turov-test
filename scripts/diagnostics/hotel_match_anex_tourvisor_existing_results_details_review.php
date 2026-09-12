<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_sold_details_strong_review.php';

const HMATV_OPERATION = 'hotel-match-anex-tourvisor-existing-results-details-review-1971-20260911-v1';
const HMATV_V7_OPERATION = 'hotel-match-anex-tourvisor-hotelcode-evidence-1971-20260911-v7';
const HMATV_DIRECT_OPERATION = 'hotel-match-anex-sold-details-review-1971-20260911-v2';
const HMATV_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmatv_tv_rows(array $payload): array {
    if (array_is_list($payload)) return array_values(array_filter($payload,'is_array'));
    foreach(['hotels','items','results'] as $key) if(is_array($payload[$key]??null)) return array_values(array_filter($payload[$key],'is_array'));
    return [];
}
function hmatv_has_operator(array $hotel,int $operatorId): bool {
    foreach(($hotel['tours']??[]) as $tour){if(!is_array($tour))continue;$op=is_array($tour['operator']??null)?(int)($tour['operator']['id']??0):0;if($op===$operatorId)return true;}
    return false;
}
function hmatv_places(array $row,array $catalog): array {
    $out=[];foreach(['region','subRegion'] as $key)if(is_array($row[$key]??null)&&trim((string)($row[$key]['name']??''))!=='')$out[]=(string)$row[$key]['name'];
    foreach(['region_name','subregion_name'] as $key)if(trim((string)($catalog[$key]??''))!=='')$out[]=(string)$catalog[$key];
    return array_values(array_unique($out));
}
function hmatv_source_variants(array $detail): array {
    $name=trim((string)($detail['name']??''));return $name===''?[]:hmasr_variants($name);
}
function hmatv_target_variants(array $row,array $catalog,array $aliases): array {
    $values=[(string)($row['name']??''),(string)($catalog['name']??'')];foreach($aliases as $a)$values[]=(string)$a;
    $out=[];foreach($values as $value)foreach(hmasr_variants($value) as $variant){$variant=trim($variant);if($variant!=='')$out[$variant]=true;}return array_keys($out);
}
function hmatv_candidate(array $detail,array $row,array $catalog,array $aliases,bool $andromedaBridge): ?array {
    $sourceVariants=hmatv_source_variants($detail);$targetVariants=hmatv_target_variants($row,$catalog,$aliases);if(!$sourceVariants||!$targetVariants)return null;
    $name=hmasr_best_name($sourceVariants,$targetVariants);if(!$name['critical_ok']||$name['shared']<2)return null;
    $slat=hmasr_coord($detail['latitude']??null);$slon=hmasr_coord($detail['longitude']??null);$tlat=hmasr_coord($catalog['latitude']??($row['latitude']??null));$tlon=hmasr_coord($catalog['longitude']??($row['longitude']??null));
    $dist=fc_dist($slat,$slon,$tlat,$tlon);if($dist!==null&&$dist>5000)return ['blocked'=>'coordinate_conflict','distance_m'=>$dist,'name'=>$name];
    $place=fc_place([(string)($detail['region']??''),(string)($detail['town']??'')],hmatv_places($row,$catalog));$geo=($dist!==null&&$dist<=1000)||$place;
    $strict=false;foreach($sourceVariants as $sv){$sk=fc_key($sv,false,false);if($sk==='')continue;foreach($targetVariants as $tv)if($sk===fc_key($tv,false,false)){$strict=true;break 2;}}
    $nameStrong=$name['shared']>=2&&($name['jaccard']>=0.58||($name['ordered']&&$name['character']>=0.72)||$name['character']>=0.86);
    $coordStrong=$dist!==null&&$dist<=120&&$name['shared']>=2&&($name['jaccard']>=0.42||$name['character']>=0.72);
    if(!$strict&&!$nameStrong&&!$coordStrong)return null;if(!$geo&&$dist===null)return null;
    $score=(float)$name['score']+($strict?0.18:0.0)+(($dist!==null&&$dist<=120)?0.24:(($dist!==null&&$dist<=1000)?0.12:0.0))+($place?0.08:0.0)+($andromedaBridge?0.04:0.0);
    return ['blocked'=>null,'name'=>$name,'distance_m'=>$dist===null?null:round($dist,2),'place_match'=>$place,'geo_strong'=>$geo,'strict_name'=>$strict,'andromeda_bridge'=>$andromedaBridge,'score'=>round($score,6)];
}
function hmatv_safe(array $best,float $margin): bool {
    if(($best['blocked']??null)!==null)return false;$name=$best['name'];$dist=$best['distance_m']??null;
    if(!$name['critical_ok']||$name['shared']<2)return false;if($dist!==null&&(float)$dist>5000)return false;
    if($best['strict_name']&&(($best['geo_strong']??false)||($dist!==null&&(float)$dist<=1000))&&$margin>=0.05)return true;
    if(($best['geo_strong']??false)&&(float)$best['score']>=0.78&&$margin>=0.10)return true;
    if($dist!==null&&(float)$dist<=120&&(float)$best['score']>=0.72&&$margin>=0.08)return true;
    return false;
}
function hmatv_validate_inputs(array $v7,array $direct): array {
    if(($v7['status']??null)!=='completed'||($v7['operation_id']??null)!==HMATV_V7_OPERATION||($v7['database_writes']??null)!==0||($v7['search_continue_calls']??null)!==0||($v7['booking_calls']??null)!==0)throw new RuntimeException('v7_input_invalid');
    if(($direct['status']??null)!=='completed'||($direct['operation_id']??null)!==HMATV_DIRECT_OPERATION||($direct['database_writes']??null)!==0||($direct['mapping_writes']??null)!==0)throw new RuntimeException('direct_input_invalid');
    $handles=[];foreach(($v7['searches']??[]) as $s){if(($s['status']??'')!=='completed'||!($s['search_complete']??false))continue;$sid=(int)($s['search_id']??0);$cid=(int)($s['country_id']??0);$op=(int)($s['operator_id']??0);if($sid<1||!isset(HMATV_CORE8[$cid])||$op<1)continue;$handles[$sid]=['search_id'=>$sid,'country_id'=>$cid,'operator_id'=>$op,'date'=>$s['date']??null,'date_mode'=>$s['date_mode']??null,'historical_result_hotels'=>(int)($s['result_hotels']??0)];}
    if(count($handles)!==21)throw new RuntimeException('v7_handle_count_changed');
    $details=[];foreach(($direct['details']['rows']??[]) as $r){if(($r['status']??'')!=='ok'||!is_array($r['detail']??null))continue;$id=(int)($r['anex_hotel_id']??0);if($id<1)continue;$details[$id]=$r['detail'];}
    if(count($details)!==208)throw new RuntimeException('direct_detail_count_changed');
    return ['handles'=>array_values($handles),'details'=>$details];
}
function hmatv_current_sources(PDO $db,array $details): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
        $obs=[];foreach($db->query('SELECT anex_hotel_id,country_id,hotel_name,search_count,last_seen_utc FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];if(!isset($obs[$id]))$obs[$id]=$r;}
        $sources=[];foreach($details as $id=>$detail){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id]))continue;$o=$obs[$id]??null;if(!$o||(int)($o['search_count']??0)<=0)continue;$country=(int)($o['country_id']??0);if(!isset(HMATV_CORE8[$country]))continue;$sources[$id]=['anex_hotel_id'=>$id,'country_id'=>$country,'search_count'=>(int)$o['search_count'],'last_seen_utc'=>$o['last_seen_utc']??null,'detail'=>$detail];}
        $db->commit();return $sources;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function hmatv_current_catalog(PDO $db): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];foreach($db->query('SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $h)$hotels[(int)$h['id']]=$h;
        $aliases=[];foreach($db->query('SELECT a.hotel_id,a.alias,a.normalized_alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $a){$id=(int)$a['hotel_id'];foreach([$a['alias'],$a['normalized_alias']] as $v)if(trim((string)$v)!=='')$aliases[$id][]=(string)$v;}
        $andr=array_fill_keys(array_map('intval',$db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN)),true);
        $claimed=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC) as $r)$claimed[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $coverage=fc_coverage($db);$db->commit();return compact('hotels','aliases','andr','claimed','excluded','coverage');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function hmatv_review(PDO $db,array $v7,array $direct,array $resultSets,string $operation=HMATV_OPERATION): array {
    if($operation!==HMATV_OPERATION)throw new RuntimeException('operation_scope');$inputs=hmatv_validate_inputs($v7,$direct);$sources=hmatv_current_sources($db,$inputs['details']);$cat=hmatv_current_catalog($db);
    $rowsByCountry=[];$resultRows=0;$handleStats=[];
    foreach($inputs['handles'] as $h){$sid=(int)$h['search_id'];$payload=$resultSets[$sid]??null;if(!is_array($payload)){ $handleStats[]=$h+['status'=>'missing_result','rows'=>0];continue;}$rows=hmatv_tv_rows($payload);$kept=0;foreach($rows as $row){$id=(int)($row['id']??0);$country=is_array($row['country']??null)?(int)($row['country']['id']??0):0;if($id<1||$country!==(int)$h['country_id']||!hmatv_has_operator($row,(int)$h['operator_id']))continue;$rowsByCountry[$country][$id]=$row;$kept++;}$resultRows+=count($rows);$handleStats[]=$h+['status'=>'read','rows'=>count($rows),'anex_rows'=>$kept];}
    $stats=['current_direct_live_unresolved'=>count($sources),'result_handles'=>count($inputs['handles']),'result_rows'=>$resultRows,'unique_tv_hotels'=>0,'prepared'=>0,'coordinate_conflict'=>0,'no_candidate'=>0,'weak_best'=>0,'small_margin'=>0,'target_already_claimed'=>0,'pair_excluded'=>0,'target_collision'=>0,'andromeda_bridge'=>0];$uniq=[];foreach($rowsByCountry as $set)foreach(array_keys($set) as $id)$uniq[$id]=true;$stats['unique_tv_hotels']=count($uniq);
    $prepared=[];$provisional=[];
    foreach($sources as $aid=>$source){$country=(int)$source['country_id'];$detail=$source['detail'];$scored=[];foreach(($rowsByCountry[$country]??[]) as $tvId=>$row){$catalog=$cat['hotels'][(int)$tvId]??null;if(!$catalog||(int)$catalog['country_id']!==$country)continue;if(isset($cat['excluded'][$aid][(int)$tvId]))continue;$cand=hmatv_candidate($detail,$row,$catalog,$cat['aliases'][(int)$tvId]??[],isset($cat['andr'][(int)$tvId]));if($cand===null)continue;if(($cand['blocked']??null)==='coordinate_conflict'){$stats['coordinate_conflict']++;continue;}$cand+=['target_local_hotel_id'=>(int)$tvId,'target_name'=>$catalog['name'],'tv_name'=>$row['name']??null,'target_region'=>$catalog['region_name'],'target_subregion'=>$catalog['subregion_name']];$scored[]=$cand;}
        if(!$scored){$stats['no_candidate']++;continue;}usort($scored,static fn($a,$b)=>$b['score']<=>$a['score'] ?: (($a['distance_m']??PHP_FLOAT_MAX)<=>($b['distance_m']??PHP_FLOAT_MAX)) ?: $a['target_local_hotel_id']<=>$b['target_local_hotel_id']);$best=$scored[0];$second=$scored[1]??null;$margin=$second===null?1.0:(float)$best['score']-(float)$second['score'];if(!hmatv_safe($best,$margin)){if($margin<0.08)$stats['small_margin']++;else $stats['weak_best']++;continue;}$target=(int)$best['target_local_hotel_id'];$claimed=array_values(array_filter($cat['claimed'][$target]??[],static fn($x)=>(int)$x!==(int)$aid));if($claimed){$stats['target_already_claimed']++;continue;}if(isset($cat['excluded'][$aid][$target])){$stats['pair_excluded']++;continue;}$provisional[$aid]=['anex_hotel_id'=>(int)$aid,'country_id'=>$country,'search_count'=>$source['search_count'],'last_seen_utc'=>$source['last_seen_utc'],'source_name'=>$detail['name']??null,'source_region'=>$detail['region']??null,'source_town'=>$detail['town']??null,'target_local_hotel_id'=>$target,'target_name'=>$best['target_name'],'tv_name'=>$best['tv_name'],'target_region'=>$best['target_region'],'target_subregion'=>$best['target_subregion'],'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'strict_name'=>$best['strict_name'],'andromeda_bridge'=>$best['andromeda_bridge'],'name'=>$best['name'],'score'=>$best['score'],'margin'=>round($margin,6),'second'=>$second===null?null:['target_local_hotel_id'=>$second['target_local_hotel_id'],'target_name'=>$second['target_name'],'score'=>$second['score'],'distance_m'=>$second['distance_m']],'rule'=>'existing_completed_tourvisor_result_plus_direct_anex_details'];}
    $byTarget=[];foreach($provisional as $aid=>$row)$byTarget[(int)$row['target_local_hotel_id']][]=(int)$aid;foreach($provisional as $aid=>$row){if(count($byTarget[(int)$row['target_local_hotel_id']])!==1){$stats['target_collision']++;continue;}$prepared[]=$row;if($row['andromeda_bridge'])$stats['andromeda_bridge']++;}
    usort($prepared,static fn($a,$b)=>$b['search_count']<=>$a['search_count'] ?: $a['anex_hotel_id']<=>$b['anex_hotel_id']);$stats['prepared']=count($prepared);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'anex_supplier_calls'=>0,'tourvisor_search_starts'=>0,'tourvisor_status_calls'=>0,'tourvisor_search_continue_calls'=>0,'tourvisor_tour_detail_calls'=>0,'tourvisor_result_reads'=>count(array_filter($handleStats,static fn($h)=>($h['status']??'')==='read')),'historical_operations_replayed'=>false,'stats'=>$stats,'coverage'=>$cat['coverage'],'handles'=>$handleStats,'prepared'=>$prepared];
}
