<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_sold_details_strong_review.php';

const HMADDCR_OPERATION = 'hotel-match-anex-direct-details-coordinate-review-1971-20260911-v1';
const HMADDCR_DIRECT_RESULT_SHA256 = '86f3cbe232a262fa6c6bcd5d87a56af8167ce52f1dc7b589c7d4605fd57cc7fa';
const HMADDCR_DIRECT_PLAN_SHA256 = '18ebd5b63c9a5043a9fd573390a0b06331cd099f1956e3d9eea7eb1c2bd9bad0';
const HMADDCR_COORDINATE_CONFLICT_BLOCK_M = 5000.0;
const HMADDCR_PHYSICAL_SCAN_M = 350.0;
const HMADDCR_ONE_TOKEN_CLUSTER_M = 80.0;

function hmaddcr_target_names(string $name, array $aliases): array {
    $out=[];
    foreach(array_merge([$name],$aliases) as $raw){
        foreach(hmasr_variants((string)$raw) as $variant){$variant=trim($variant);if($variant!=='')$out[$variant]=true;}
    }
    return array_keys($out);
}
function hmaddcr_exact_tokens(array $sourceVariants,array $targetNames): bool {
    foreach($sourceVariants as $source){$st=hmasr_tokens((string)$source);if(!$st)continue;foreach($targetNames as $target){$tt=hmasr_tokens((string)$target);if($st===$tt)return true;}}
    return false;
}
function hmaddcr_country_unique_shared(array $name,array $tokenFrequency): bool {
    foreach(array_intersect($name['source_tokens']??[],$name['target_tokens']??[]) as $token){if((int)($tokenFrequency[$token]??0)===1)return true;}
    return false;
}
function hmaddcr_candidate(array $detail,array $target,array $targetNames,bool $andromedaBridge,array $tokenFrequency,int $physicalNearCount): ?array {
    $sourceName=trim((string)($detail['name']??''));if($sourceName==='')return null;$variants=hmasr_variants($sourceName);$name=hmasr_best_name($variants,$targetNames);if(!($name['critical_ok']??false))return null;
    $slat=hmasr_coord($detail['latitude']??null);$slon=hmasr_coord($detail['longitude']??null);$tlat=hmasr_coord($target['latitude']??null);$tlon=hmasr_coord($target['longitude']??null);if($slat===null||$slon===null||$tlat===null||$tlon===null)return null;
    $dist=fc_dist($slat,$slon,$tlat,$tlon);if($dist===null)return null;$dist=(float)$dist;
    if($dist>HMADDCR_COORDINATE_CONFLICT_BLOCK_M)return ['blocked'=>'coordinate_conflict','distance_m'=>round($dist,2),'name'=>$name];
    if($dist>HMADDCR_PHYSICAL_SCAN_M)return null;
    $place=fc_place([(string)($detail['region']??''),(string)($detail['town']??'')],[(string)($target['region_name']??''),(string)($target['subregion_name']??'')]);
    $shared=(int)($name['shared']??0);$exact=hmaddcr_exact_tokens($variants,$targetNames);$uniqueShared=hmaddcr_country_unique_shared($name,$tokenFrequency);$route=null;
    if($exact&&$shared>=1&&$dist<=250.0&&($place||$dist<=80.0||$andromedaBridge)&&($shared>=2||$andromedaBridge||$physicalNearCount===1))$route='exact_tokens_direct_coordinate';
    elseif($shared>=2&&$dist<=180.0&&($place||$andromedaBridge||$dist<=50.0)&&((float)($name['jaccard']??0)>=0.42||(float)($name['character']??0)>=0.72||($name['ordered']??false)))$route='two_token_direct_coordinate';
    elseif($shared===1&&$dist<=100.0&&$place&&$andromedaBridge)$route='one_token_bridge_ultratight_coordinate';
    elseif($shared===1&&$dist<=60.0&&$place&&$uniqueShared&&$physicalNearCount===1)$route='one_token_country_unique_ultratight_coordinate';
    if($route===null)return null;
    $score=(float)($name['score']??0)+($dist<=25.0?0.30:($dist<=60.0?0.24:($dist<=120.0?0.18:0.12)))+($place?0.10:0.0)+($andromedaBridge?0.05:0.0)+($exact?0.10:0.0);
    return ['blocked'=>null,'route'=>$route,'distance_m'=>round($dist,2),'place_match'=>$place,'andromeda_bridge'=>$andromedaBridge,'country_unique_shared'=>$uniqueShared,'physical_near_count'=>$physicalNearCount,'exact_tokens'=>$exact,'name'=>$name,'score'=>round($score,6)];
}
function hmaddcr_review(PDO $db,array $directResult,array $directPlan,string $operation=HMADDCR_OPERATION): array {
    if($operation!==HMADDCR_OPERATION)throw new RuntimeException('HMADDCR_OPERATION_SCOPE');
    if(($directResult['status']??null)!=='completed'||($directResult['operation_id']??null)!==HMASR_SOURCE_OPERATION||($directResult['database_writes']??null)!==0||($directResult['mapping_writes']??null)!==0)throw new RuntimeException('HMADDCR_DIRECT_RESULT');
    if(($directPlan['status']??null)!=='ready'||($directPlan['operation_id']??null)!==HMASR_SOURCE_OPERATION||($directPlan['database_writes']??null)!==0||($directPlan['mapping_writes']??null)!==0)throw new RuntimeException('HMADDCR_DIRECT_PLAN');
    $sources=hmasr_source_payload($directResult,$directPlan);if(count($sources)!==208)throw new RuntimeException('HMADDCR_SOURCE_COUNT');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $mapped=[];$claimed=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC) as $row){$aid=(int)$row['anex_hotel_id'];$hid=(int)$row['catalog_hotel_id'];$mapped[$aid]=true;$claimed[$hid][$aid]=true;}
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $row)$excluded[(int)$row['anex_hotel_id']][(int)$row['catalog_hotel_id']]=true;
        $andromeda=[];foreach($db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id)$andromeda[(int)$id]=true;
        $observed=[];foreach($db->query('SELECT anex_hotel_id,country_id,hotel_name,search_count,last_seen_utc FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC')->fetchAll(PDO::FETCH_ASSOC) as $row){$id=(int)$row['anex_hotel_id'];if(!isset($observed[$id]))$observed[$id]=$row;}
        $hotels=[];$names=[];$countryHotels=[];$tokenSets=[];
        foreach($db->query('SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,h.category,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $row){$hid=(int)$row['id'];$country=(int)$row['country_id'];$hotels[$hid]=$row;$names[$hid]=[(string)$row['name']];$countryHotels[$country][]=$hid;}
        foreach($db->query('SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $row){$hid=(int)$row['hotel_id'];foreach([(string)$row['alias'],(string)$row['normalized_alias']] as $alias)if(trim($alias)!=='')$names[$hid][]=$alias;}
        foreach($hotels as $hid=>$hotel){$country=(int)$hotel['country_id'];$names[$hid]=hmaddcr_target_names((string)$hotel['name'],$names[$hid]??[]);foreach($names[$hid] as $name)foreach(hmasr_tokens($name) as $token)$tokenSets[$country][$token][$hid]=true;}
        $tokenFrequency=[];foreach($tokenSets as $country=>$tokens)foreach($tokens as $token=>$ids)$tokenFrequency[$country][$token]=count($ids);
        $stats=['immutable_details'=>count($sources),'protected'=>0,'country_other'=>0,'country_mismatch'=>0,'missing_source_coordinates'=>0,'with_source_coordinates'=>0,'physical_targets_scanned'=>0,'qualifier_or_name_block'=>0,'coordinate_conflict'=>0,'pair_excluded'=>0,'target_already_claimed'=>0,'ambiguous_safe_targets'=>0,'target_collision'=>0,'prepared'=>0,'prepared_live'=>0,'prepared_andromeda_bridge'=>0,'route_exact_tokens'=>0,'route_two_token'=>0,'route_one_token_bridge'=>0,'route_one_token_unique'=>0];$provisional=[];
        foreach($sources as $aid=>$source){$aid=(int)$aid;if(isset($manual[$aid])||isset($mapped[$aid])){$stats['protected']++;continue;}$detail=$source['detail'];$ctx=$source['context'];$obs=$observed[$aid]??null;$country=(int)($obs['country_id']??($ctx['country_id']??0));if(!isset(HMASR_CORE8[$country])){$stats['country_other']++;continue;}if(isset($ctx['country_id'])&&(int)$ctx['country_id']>0&&(int)$ctx['country_id']!==$country){$stats['country_mismatch']++;continue;}$slat=hmasr_coord($detail['latitude']??null);$slon=hmasr_coord($detail['longitude']??null);if($slat===null||$slon===null){$stats['missing_source_coordinates']++;continue;}$stats['with_source_coordinates']++;
            $near=[];foreach($countryHotels[$country]??[] as $hid){$target=$hotels[$hid];$tlat=hmasr_coord($target['latitude']??null);$tlon=hmasr_coord($target['longitude']??null);if($tlat===null||$tlon===null)continue;$distance=fc_dist($slat,$slon,$tlat,$tlon);if($distance!==null&&(float)$distance<=HMADDCR_PHYSICAL_SCAN_M)$near[(int)$hid]=(float)$distance;}$stats['physical_targets_scanned']+=count($near);$clusterCount=count(array_filter($near,static fn($d)=>(float)$d<=HMADDCR_ONE_TOKEN_CLUSTER_M));
            $safe=[];foreach($near as $hid=>$distance){$hid=(int)$hid;if(isset($excluded[$aid][$hid])){$stats['pair_excluded']++;continue;}$otherClaims=array_filter(array_keys($claimed[$hid]??[]),static fn($x)=>(int)$x!==$aid);if($otherClaims){$stats['target_already_claimed']++;continue;}$candidate=hmaddcr_candidate($detail,$hotels[$hid],$names[$hid]??[(string)$hotels[$hid]['name']],isset($andromeda[$hid]),$tokenFrequency[$country]??[],$clusterCount);if($candidate===null){$stats['qualifier_or_name_block']++;continue;}if(($candidate['blocked']??null)==='coordinate_conflict'){$stats['coordinate_conflict']++;continue;}$candidate+=['target_local_hotel_id'=>$hid,'target_name'=>$hotels[$hid]['name'],'target_region'=>$hotels[$hid]['region_name'],'target_subregion'=>$hotels[$hid]['subregion_name']];$safe[]=$candidate;}
            if(!$safe)continue;usort($safe,static fn($a,$b)=>$b['score']<=>$a['score'] ?: $a['distance_m']<=>$b['distance_m'] ?: $a['target_local_hotel_id']<=>$b['target_local_hotel_id']);if(count($safe)>1){$stats['ambiguous_safe_targets']++;continue;}$best=$safe[0];$live=$obs!==null&&(int)($obs['search_count']??0)>0;$provisional[$aid]=['anex_hotel_id'=>$aid,'country_id'=>$country,'live'=>$live,'search_count'=>(int)($obs['search_count']??($ctx['search_count']??0)),'last_seen_utc'=>$obs['last_seen_utc']??($ctx['last_seen_utc']??null),'source_name'=>$detail['name']??null,'source_region'=>$detail['region']??null,'source_town'=>$detail['town']??null,'source_latitude'=>$slat,'source_longitude'=>$slon,'target_local_hotel_id'=>$best['target_local_hotel_id'],'target_name'=>$best['target_name'],'target_region'=>$best['target_region'],'target_subregion'=>$best['target_subregion'],'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'andromeda_bridge'=>$best['andromeda_bridge'],'country_unique_shared'=>$best['country_unique_shared'],'physical_near_count'=>$best['physical_near_count'],'exact_tokens'=>$best['exact_tokens'],'name'=>$best['name'],'score'=>$best['score'],'rule'=>$best['route']];}
        $targets=[];foreach($provisional as $aid=>$row)$targets[(int)$row['target_local_hotel_id']][]=(int)$aid;$prepared=[];foreach($provisional as $aid=>$row){if(count($targets[(int)$row['target_local_hotel_id']]??[])!==1){$stats['target_collision']++;continue;}$prepared[]=$row;$stats['prepared']++;if($row['live'])$stats['prepared_live']++;if($row['andromeda_bridge'])$stats['prepared_andromeda_bridge']++;if($row['rule']==='exact_tokens_direct_coordinate')$stats['route_exact_tokens']++;elseif($row['rule']==='two_token_direct_coordinate')$stats['route_two_token']++;elseif($row['rule']==='one_token_bridge_ultratight_coordinate')$stats['route_one_token_bridge']++;elseif($row['rule']==='one_token_country_unique_ultratight_coordinate')$stats['route_one_token_unique']++;}
        usort($prepared,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1))?:($b['search_count']<=>$a['search_count'])?:($a['distance_m']<=>$b['distance_m'])?:($b['score']<=>$a['score'])?:($a['anex_hotel_id']<=>$b['anex_hotel_id']));$coverage=fc_coverage($db);$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'direct_result_sha256'=>HMADDCR_DIRECT_RESULT_SHA256,'direct_plan_sha256'=>HMADDCR_DIRECT_PLAN_SHA256,'stats'=>$stats,'coverage'=>$coverage,'prepared'=>$prepared];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded workflow with immutable direct-details artifacts and CURRENT DB\n");exit(64);}
