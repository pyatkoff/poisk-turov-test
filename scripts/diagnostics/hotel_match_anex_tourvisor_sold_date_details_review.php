<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_tourvisor_existing_results_details_review.php';

const HMATSD_OPERATION = 'hotel-match-anex-tourvisor-sold-date-details-review-1971-20260911-v1';
const HMATSD_SELL_OPERATION = 'hotel-match-anex-sellability-review-1971-20260911-v4';
const HMATSD_DIRECT_OPERATION = 'hotel-match-anex-sold-details-review-1971-20260911-v2';
const HMATSD_SELL_RESULT_SHA256 = '47a55cf4ba8cab0fb2fa175f0856e2eca2f144e64b1d7b7a48ddfb0335d85c61';
const HMATSD_DIRECT_RESULT_SHA256 = '86f3cbe232a262fa6c6bcd5d87a56af8167ce52f1dc7b589c7d4605fd57cc7fa';
const HMATSD_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];
const HMATSD_COORDINATE_CONFLICT_BLOCK_M = 5000;

function hmatsd_valid_date(string $date): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d instanceof DateTimeImmutable && $d->format('Y-m-d') === $date;
}
function hmatsd_departure_id(array $rows): ?int {
    foreach($rows as $row){if(!is_array($row))continue;$name=fc_norm((string)($row['name']??''));if($name==='москва'||$name==='moscow'){$id=filter_var($row['id']??null,FILTER_VALIDATE_INT);if($id!==false&&(int)$id>0)return (int)$id;}}
    return null;
}
function hmatsd_operator_id(array $rows): ?int {
    foreach($rows as $row){if(!is_array($row))continue;$name=fc_norm(implode(' ',[(string)($row['name']??''),(string)($row['russianName']??''),(string)($row['fullName']??'')]));if($name!==''&&(str_contains($name,'anex')||str_contains($name,'анекс'))){$id=filter_var($row['id']??null,FILTER_VALIDATE_INT);if($id!==false&&(int)$id>0)return (int)$id;}}
    return null;
}
function hmatsd_date_set(array $rows): array {
    $out=[];foreach($rows as $value){if(!is_string($value))continue;$date=trim($value);if(hmatsd_valid_date($date))$out[$date]=true;}return $out;
}
function hmatsd_search_id(array $payload): ?int {
    foreach(['searchId','id'] as $key){$id=filter_var($payload[$key]??null,FILTER_VALIDATE_INT);if($id!==false&&(int)$id>0)return (int)$id;}return null;
}
function hmatsd_search_complete(array $payload): bool {
    return (int)($payload['progress']??0)>=100||in_array(strtolower(trim((string)($payload['status']??''))),['complete','completed','done'],true);
}
function hmatsd_validate_inputs(array $sell, array $direct, array $plan): array {
    if (($sell['status'] ?? null) !== 'completed' || ($sell['operation_id'] ?? null) !== HMATSD_SELL_OPERATION) throw new RuntimeException('HMATSD_SELL_INPUT');
    if (($sell['database_writes'] ?? null) !== 0 || ($sell['mapping_writes'] ?? null) !== 0 || ($sell['bookings'] ?? null) !== 0 || ($sell['search_continuations'] ?? null) !== 0) throw new RuntimeException('HMATSD_SELL_INPUT');
    if (!is_array($sell['sold'] ?? null) || count($sell['sold']) !== 254 || (int)($sell['stats']['sold_on_probe'] ?? -1) !== 254) throw new RuntimeException('HMATSD_SELL_COUNT');
    if (($direct['status'] ?? null) !== 'completed' || ($direct['operation_id'] ?? null) !== HMATSD_DIRECT_OPERATION) throw new RuntimeException('HMATSD_DIRECT_INPUT');
    if (($direct['database_writes'] ?? null) !== 0 || ($direct['mapping_writes'] ?? null) !== 0 || !is_array($direct['details']['rows'] ?? null)) throw new RuntimeException('HMATSD_DIRECT_INPUT');
    if (($plan['status'] ?? null) !== 'ready' || ($plan['operation_id'] ?? null) !== HMATSD_DIRECT_OPERATION || ($plan['database_writes'] ?? null) !== 0 || ($plan['mapping_writes'] ?? null) !== 0) throw new RuntimeException('HMATSD_PLAN_INPUT');
    if (($direct['target_sha256'] ?? '') === '' || ($direct['target_sha256'] ?? '') !== ($plan['target_sha256'] ?? '')) throw new RuntimeException('HMATSD_TARGET_SHA');
    $manifest = $plan['manifest'] ?? null;
    if (!is_array($manifest) || (int)($manifest['source_artifact_id'] ?? 0) !== 10192364780 || ($manifest['source_operation_id'] ?? null) !== HMATSD_SELL_OPERATION || ($manifest['source_result_sha256'] ?? null) !== HMATSD_SELL_RESULT_SHA256) throw new RuntimeException('HMATSD_MANIFEST');

    $soldDates = [];
    foreach ($sell['sold'] as $row) {
        if (!is_array($row)) continue;
        $id = (int)($row['anex_hotel_id'] ?? 0); if ($id < 1) continue;
        $dates = [];
        foreach (($row['probe_dates'] ?? []) as $date) {
            $date = trim((string)$date); if (hmatsd_valid_date($date)) $dates[$date] = true;
        }
        if ($dates) { ksort($dates, SORT_STRING); $soldDates[$id] = array_keys($dates); }
    }
    if (count($soldDates) !== 254) throw new RuntimeException('HMATSD_SOLD_DATES');

    $context = [];
    foreach (($plan['target_context'] ?? []) as $row) {
        if (!is_array($row)) continue; $id=(int)($row['anex_hotel_id']??0); if($id>0)$context[$id]=$row;
    }
    $details = [];
    foreach ($direct['details']['rows'] as $row) {
        if (!is_array($row) || ($row['status'] ?? '') !== 'ok' || !is_array($row['detail'] ?? null)) continue;
        $id=(int)($row['anex_hotel_id']??0); if($id<1||!isset($soldDates[$id])||!isset($context[$id]))continue;
        if (isset($row['detail']['id']) && (int)$row['detail']['id'] !== $id) throw new RuntimeException('HMATSD_DETAIL_ID');
        $details[$id]=$row['detail'];
    }
    if (count($details) !== 208) throw new RuntimeException('HMATSD_DETAIL_COUNT');
    return ['sold_dates'=>$soldDates,'context'=>$context,'details'=>$details];
}
function hmatsd_current_sources(PDO $db, array $input): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION READ ONLY');
    try {
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $mapped=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN)),true);
        $obs=[]; foreach($db->query('SELECT anex_hotel_id,country_id,hotel_name,search_count,last_seen_utc FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];if(!isset($obs[$id]))$obs[$id]=$r;}
        $sources=[];$protected=0;$nonCore=0;$noDates=0;
        foreach($input['details'] as $id=>$detail){$id=(int)$id;if(isset($manual[$id])||isset($mapped[$id])){$protected++;continue;}$ctx=$input['context'][$id]??[];$o=$obs[$id]??null;$country=(int)($o['country_id']??($ctx['country_id']??0));if(!isset(HMATSD_CORE8[$country])){$nonCore++;continue;}$dates=array_values($input['sold_dates'][$id]??[]);if(!$dates){$noDates++;continue;}$sources[$id]=['anex_hotel_id'=>$id,'country_id'=>$country,'live'=>$o!==null&&(int)($o['search_count']??0)>0,'search_count'=>(int)($o['search_count']??0),'last_seen_utc'=>$o['last_seen_utc']??null,'observed_name'=>$o['hotel_name']??null,'probe_dates'=>$dates,'detail'=>$detail];}
        usort($sources,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1))?:($b['search_count']<=>$a['search_count'])?:($a['anex_hotel_id']<=>$b['anex_hotel_id']));
        $coverage=fc_coverage($db);$db->commit();return ['sources'=>$sources,'coverage'=>$coverage,'protected'=>$protected,'non_core'=>$nonCore,'no_dates'=>$noDates];
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function hmatsd_slices(array $sources): array {
    $sets=[];
    foreach($sources as $source){$country=(int)($source['country_id']??0);$aid=(int)($source['anex_hotel_id']??0);if($aid<1||!isset(HMATSD_CORE8[$country]))continue;foreach(($source['probe_dates']??[]) as $date){$date=trim((string)$date);if(!hmatsd_valid_date($date))continue;$key=$country.'|'.$date;$sets[$key]['country_id']=$country;$sets[$key]['date']=$date;$sets[$key]['source_ids'][$aid]=true;}}
    $out=[];foreach($sets as $set){$ids=array_map('intval',array_keys($set['source_ids']));sort($ids,SORT_NUMERIC);$out[]=['country_id'=>(int)$set['country_id'],'date'=>(string)$set['date'],'source_ids'=>$ids,'source_count'=>count($ids)];}
    usort($out,static fn($a,$b)=>$a['date']<=>$b['date'] ?: $a['country_id']<=>$b['country_id']);return $out;
}
function hmatsd_plan_fingerprint(array $sources,array $slices): string {
    $rows=[];foreach($sources as $source){$dates=array_values($source['probe_dates']??[]);sort($dates,SORT_STRING);$rows[]=['anex_hotel_id'=>(int)($source['anex_hotel_id']??0),'country_id'=>(int)($source['country_id']??0),'probe_dates'=>$dates];}
    usort($rows,static fn($a,$b)=>$a['anex_hotel_id']<=>$b['anex_hotel_id']);$sliceRows=[];foreach($slices as $slice){$ids=array_map('intval',$slice['source_ids']??[]);sort($ids,SORT_NUMERIC);$sliceRows[]=['country_id'=>(int)($slice['country_id']??0),'date'=>(string)($slice['date']??''),'source_ids'=>$ids];}
    usort($sliceRows,static fn($a,$b)=>$a['date']<=>$b['date'] ?: $a['country_id']<=>$b['country_id']);return hash('sha256',json_encode(['sources'=>$rows,'slices'=>$sliceRows],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function hmatsd_candidate_for_slice(array $source,string $date,array $row,array $catalog,array $aliases,bool $andromedaBridge): ?array {
    if(!in_array($date,$source['probe_dates']??[],true))return null;
    $country=is_array($row['country']??null)?(int)($row['country']['id']??0):0;if($country!==(int)($source['country_id']??0))return null;
    $candidate=hmatv_candidate($source['detail']??[],$row,$catalog,$aliases,$andromedaBridge);if($candidate===null)return null;if(($candidate['distance_m']??null)!==null&&(float)$candidate['distance_m']>HMATSD_COORDINATE_CONFLICT_BLOCK_M)$candidate['blocked']='coordinate_conflict';$candidate['sold_date']=$date;return $candidate;
}
function hmatsd_review(PDO $db,array $sell,array $direct,array $plan,array $resultSets,string $operation=HMATSD_OPERATION): array {
    if($operation!==HMATSD_OPERATION)throw new RuntimeException('HMATSD_OPERATION_SCOPE');$input=hmatsd_validate_inputs($sell,$direct,$plan);$current=hmatsd_current_sources($db,$input);$sources=$current['sources'];$cat=hmatv_current_catalog($db);$slices=hmatsd_slices($sources);
    $stats=['immutable_sold'=>254,'immutable_direct_details'=>208,'current_direct_unresolved'=>count($sources),'current_live'=>0,'search_slices'=>count($slices),'successful_slices'=>0,'result_hotel_rows'=>0,'unique_tv_hotels'=>0,'prepared'=>0,'prepared_live'=>0,'andromeda_bridge'=>0,'coordinate_conflict'=>0,'no_candidate'=>0,'weak_best'=>0,'small_margin'=>0,'target_already_claimed'=>0,'pair_excluded'=>0,'target_collision'=>0,'protected'=>$current['protected']];foreach($sources as $s)if($s['live'])$stats['current_live']++;
    $sliceStats=[];$rowsBySlice=[];$uniqueTv=[];
    foreach($slices as $slice){$key=$slice['country_id'].'|'.$slice['date'];$packet=$resultSets[$key]??null;if(!is_array($packet)||!is_array($packet['rows']??null)){$sliceStats[]=$slice+['status'=>'missing_result','rows'=>0,'anex_rows'=>0];continue;}$op=(int)($packet['operator_id']??0);$kept=0;$all=hmatv_tv_rows($packet['rows']);foreach($all as $row){$id=(int)($row['id']??0);$country=is_array($row['country']??null)?(int)($row['country']['id']??0):0;if($id<1||$country!==(int)$slice['country_id']||$op<1||!hmatv_has_operator($row,$op))continue;$rowsBySlice[$key][$id]=$row;$uniqueTv[$id]=true;$kept++;}$stats['successful_slices']++;$stats['result_hotel_rows']+=count($all);$sliceStats[]=$slice+['status'=>'read','rows'=>count($all),'anex_rows'=>$kept];}
    $stats['unique_tv_hotels']=count($uniqueTv);$provisional=[];
    foreach($sources as $source){$aid=(int)$source['anex_hotel_id'];$country=(int)$source['country_id'];$byTarget=[];foreach($source['probe_dates'] as $date){$key=$country.'|'.$date;foreach(($rowsBySlice[$key]??[]) as $tvId=>$row){$tvId=(int)$tvId;$catalog=$cat['hotels'][$tvId]??null;if(!$catalog||(int)$catalog['country_id']!==$country)continue;if(isset($cat['excluded'][$aid][$tvId]))continue;$candidate=hmatsd_candidate_for_slice($source,$date,$row,$catalog,$cat['aliases'][$tvId]??[],isset($cat['andr'][$tvId]));if($candidate===null)continue;if(($candidate['blocked']??null)==='coordinate_conflict'){$stats['coordinate_conflict']++;continue;}$candidate+=['target_local_hotel_id'=>$tvId,'target_name'=>$catalog['name'],'tv_name'=>$row['name']??null,'target_region'=>$catalog['region_name'],'target_subregion'=>$catalog['subregion_name']];if(!isset($byTarget[$tvId])||$candidate['score']>$byTarget[$tvId]['best']['score'])$byTarget[$tvId]=['best'=>$candidate,'dates'=>[]];$byTarget[$tvId]['dates'][$date]=true;}}
        if(!$byTarget){$stats['no_candidate']++;continue;}$scored=[];foreach($byTarget as $target=>$pack){$best=$pack['best'];$best['matched_sold_dates']=array_keys($pack['dates']);sort($best['matched_sold_dates'],SORT_STRING);$best['matched_sold_date_count']=count($best['matched_sold_dates']);$scored[]=$best;}usort($scored,static fn($a,$b)=>$b['score']<=>$a['score'] ?: $b['matched_sold_date_count']<=>$a['matched_sold_date_count'] ?: (($a['distance_m']??PHP_FLOAT_MAX)<=>($b['distance_m']??PHP_FLOAT_MAX)) ?: $a['target_local_hotel_id']<=>$b['target_local_hotel_id']);$best=$scored[0];$second=$scored[1]??null;$margin=$second===null?1.0:(float)$best['score']-(float)$second['score'];if(!hmatv_safe($best,$margin)){if($margin<0.08)$stats['small_margin']++;else $stats['weak_best']++;continue;}$target=(int)$best['target_local_hotel_id'];$claimed=array_values(array_filter($cat['claimed'][$target]??[],static fn($x)=>(int)$x!==$aid));if($claimed){$stats['target_already_claimed']++;continue;}if(isset($cat['excluded'][$aid][$target])){$stats['pair_excluded']++;continue;}$provisional[$aid]=['anex_hotel_id'=>$aid,'country_id'=>$country,'live'=>(bool)$source['live'],'search_count'=>(int)$source['search_count'],'last_seen_utc'=>$source['last_seen_utc'],'source_name'=>$source['detail']['name']??null,'source_region'=>$source['detail']['region']??null,'source_town'=>$source['detail']['town']??null,'target_local_hotel_id'=>$target,'target_name'=>$best['target_name'],'tv_name'=>$best['tv_name'],'target_region'=>$best['target_region'],'target_subregion'=>$best['target_subregion'],'matched_sold_dates'=>$best['matched_sold_dates'],'matched_sold_date_count'=>$best['matched_sold_date_count'],'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'strict_name'=>$best['strict_name'],'andromeda_bridge'=>$best['andromeda_bridge'],'name'=>$best['name'],'score'=>$best['score'],'margin'=>round($margin,6),'rule'=>'same_sold_date_tourvisor_plus_direct_anex_details'];}
    $targets=[];foreach($provisional as $aid=>$row)$targets[(int)$row['target_local_hotel_id']][]=(int)$aid;$prepared=[];foreach($provisional as $aid=>$row){if(count($targets[(int)$row['target_local_hotel_id']]??[])!==1){$stats['target_collision']++;continue;}$prepared[]=$row;$stats['prepared']++;if($row['live'])$stats['prepared_live']++;if($row['andromeda_bridge'])$stats['andromeda_bridge']++;}
    usort($prepared,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1))?:($b['search_count']<=>$a['search_count'])?:($b['matched_sold_date_count']<=>$a['matched_sold_date_count'])?:($b['score']<=>$a['score'])?:($a['anex_hotel_id']<=>$b['anex_hotel_id']));
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'anex_supplier_calls'=>0,'tourvisor_search_continue_calls'=>0,'tourvisor_tour_detail_calls'=>0,'booking_calls'=>0,'historical_operations_replayed'=>false,'input_sha256'=>['sell'=>HMATSD_SELL_RESULT_SHA256,'direct'=>HMATSD_DIRECT_RESULT_SHA256],'coverage'=>$cat['coverage'],'stats'=>$stats,'search_slices'=>$sliceStats,'prepared'=>$prepared];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded workflow with immutable artifacts and server DB\n");exit(64);}
