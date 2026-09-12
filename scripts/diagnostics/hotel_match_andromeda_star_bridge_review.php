<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const HMASBR_OPERATION = 'hotel-match-andromeda-star-bridge-review-1971-20260911-v1';
const HMASBR_COORD_BLOCK_M = 5000.0;
const HMASBR_COORD_STRONG_M = 1000.0;
const HMASBR_COORD_SINGLE_TOKEN_M = 100.0;

function hmasbr_source(array $identity, ?array $observation): array {
    $prior=fc_evidence($identity['evidence_json']??'');
    $source=$prior['source']??[]; if(!is_array($source))$source=[];
    $geo=$prior['geography']??[]; if(!is_array($geo))$geo=[];
    $names=array_values(array_unique(array_filter([
        (string)($source['name']??''),(string)($source['lName']??''),(string)($observation['hotel_name']??'')
    ],static fn($v)=>trim($v)!=='')));
    $places=array_values(array_unique(array_filter([
        (string)($source['town']??''),(string)($geo['town']??''),(string)($geo['parent']??''),(string)($observation['region_name']??'')
    ],static fn($v)=>trim($v)!=='')));
    $coord=$source; if($observation)$coord+=$observation;
    [$lat,$lon]=mbr_coord($coord);
    $category=mbr_numeric_category($source); if($category===null&&$observation)$category=mbr_numeric_category($observation);
    return ['names'=>$names,'places'=>$places,'latitude'=>$lat,'longitude'=>$lon,'category'=>$category];
}
function hmasbr_targets(array $broad,int $country,array $names): array {
    $ids=[];$matched=[];
    foreach($names as $name){$key=fc_key($name,true,true);if($key==='')continue;foreach(array_keys($broad[$country][$key]??[]) as $id){$ids[(int)$id]=true;$matched[$key]=true;}}
    $out=array_map('intval',array_keys($ids));sort($out,SORT_NUMERIC);return [$out,array_keys($matched)];
}
function hmasbr_identity_tokens(array $sourceNames,array $targetNames,array $sourcePlaces,array $targetPlaces): array {
    $source=[];$target=[];$places=[];
    foreach($sourceNames as $name)foreach(fc_tokens($name,true) as $t)$source[$t]=true;
    foreach($targetNames as $name)foreach(fc_tokens($name,true) as $t)$target[$t]=true;
    foreach(array_merge($sourcePlaces,$targetPlaces) as $place)foreach(fc_tokens($place,true) as $t)$places[$t]=true;
    $shared=array_intersect_key($source,$target);foreach(array_keys($places) as $p)unset($shared[$p]);
    $weak=array_fill_keys(['city','family','view','royal','grand','blue','sun','sea','holiday','elite','adults','adult','only','wellness'],true);
    foreach(array_keys($weak) as $w)unset($shared[$w]);
    $tokens=array_keys($shared);sort($tokens,SORT_STRING);return $tokens;
}
function hmasbr_route(float|null $distance,bool $place,bool $anexBridge,array $identityTokens): ?string {
    $n=count($identityTokens);
    if($distance!==null&&$distance>HMASBR_COORD_BLOCK_M)return null;
    if($distance!==null&&$distance<=HMASBR_COORD_STRONG_M&&$n>=2)return 'generic_free_exact_plus_direct_coordinate';
    if($distance!==null&&$distance<=HMASBR_COORD_SINGLE_TOKEN_M&&$n>=1&&($place||$anexBridge))return 'single_token_ultratight_coordinate_anchor';
    if($place&&$n>=2)return 'generic_free_exact_plus_place';
    if($anexBridge&&$n>=2)return 'generic_free_exact_plus_anex_tourvisor_bridge';
    if($place&&$anexBridge&&$n>=1)return 'single_token_place_plus_anex_tourvisor_bridge';
    return null;
}
function hmasbr_review(PDO $db,string $operation=HMASBR_OPERATION): array {
    if($operation!==HMASBR_OPERATION)throw new RuntimeException('HMASBR_OPERATION_SCOPE');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);[$hotels,$names,$strict,$broad,$places,$catalogScope]=mbr_catalog($db);$shaCountry=fc_sha_countries($db);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $row)$andClaims[(int)$row['local_hotel_id']][]=(string)$row['external_hotel_id'];
        $latest=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];if(!isset($latest[$id]))$latest[$id]=$o;}
        $pending=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        $stats=['pending_examined'=>0,'country_unknown'=>0,'generic_free_exact_unique'=>0,'generic_free_exact_ambiguous'=>0,'star_mismatch_examined'=>0,'same_provider_target_claimed'=>0,'coordinate_conflict'=>0,'low_independent_evidence'=>0,'prepared'=>0,'prepared_live'=>0,'prepared_star_mismatch'=>0,'prepared_anex_bridge'=>0,'route_coordinate'=>0,'route_single_ultratight'=>0,'route_place'=>0,'route_bridge'=>0,'route_single_place_bridge'=>0];$prepared=[];
        foreach($pending as $identity){
            $external=(string)$identity['external_hotel_id'];$obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$identity['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country])){$stats['country_unknown']++;continue;}$stats['pending_examined']++;
            $source=hmasbr_source($identity,$obs);[$targets,$matchedKeys]=hmasbr_targets($broad,$country,$source['names']);if(count($targets)!==1){if(count($targets)>1)$stats['generic_free_exact_ambiguous']++;continue;}$stats['generic_free_exact_unique']++;$targetId=(int)$targets[0];$target=$hotels[$targetId]??null;if(!$target)continue;
            $targetCategory=$target['category']===null?null:(int)$target['category'];$starMismatch=$source['category']!==null&&$targetCategory!==null&&(int)$source['category']!==$targetCategory;if($starMismatch)$stats['star_mismatch_examined']++;
            if(!empty($andClaims[$targetId])){$stats['same_provider_target_claimed']++;continue;}
            $distance=fc_dist($source['latitude'],$source['longitude'],$target['latitude']??null,$target['longitude']??null);if($distance!==null&&$distance>HMASBR_COORD_BLOCK_M){$stats['coordinate_conflict']++;continue;}
            $targetPlaces=[(string)($target['region_name']??''),(string)($target['subregion_name']??'')];$place=fc_place($source['places'],$targetPlaces);$identityTokens=hmasbr_identity_tokens($source['names'],$names[$targetId]??[(string)$target['name']],$source['places'],$targetPlaces);$bridge=isset($anexLocal[$targetId]);$route=hmasbr_route($distance,$place,$bridge,$identityTokens);if($route===null){$stats['low_independent_evidence']++;continue;}
            $live=$obs!==null;$row=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'live'=>$live,'observation_count'=>$live?1:0,'last_seen_utc'=>$obs['observed_at_utc']??null,'source_names'=>$source['names'],'source_places'=>$source['places'],'source_category'=>$source['category'],'target_local_hotel_id'=>$targetId,'target_name'=>$target['name'],'target_region'=>$target['region_name'],'target_subregion'=>$target['subregion_name'],'target_category'=>$targetCategory,'star_mismatch'=>$starMismatch,'distance_m'=>$distance===null?null:round($distance,2),'place_match'=>$place,'anex_tourvisor_bridge'=>$bridge,'identity_tokens'=>$identityTokens,'matched_generic_free_keys'=>$matchedKeys,'route'=>$route];$prepared[]=$row;$stats['prepared']++;if($live)$stats['prepared_live']++;if($starMismatch)$stats['prepared_star_mismatch']++;if($bridge)$stats['prepared_anex_bridge']++;if($route==='generic_free_exact_plus_direct_coordinate')$stats['route_coordinate']++;elseif($route==='single_token_ultratight_coordinate_anchor')$stats['route_single_ultratight']++;elseif($route==='generic_free_exact_plus_place')$stats['route_place']++;elseif($route==='generic_free_exact_plus_anex_tourvisor_bridge')$stats['route_bridge']++;elseif($route==='single_token_place_plus_anex_tourvisor_bridge')$stats['route_single_place_bridge']++;
        }
        usort($prepared,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1)) ?: (($b['star_mismatch']?1:0)<=>($a['star_mismatch']?1:0)) ?: strcmp((string)$a['external_id'],(string)$b['external_id']));$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'catalog_scope'=>$catalogScope,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared,'guards'=>['core8_only'=>true,'generic_non_identity'=>['HOTEL','RESORT','SPA'],'significant_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'numeric_star_is_guard_not_identity'=>true,'coordinate_conflict_auto_block_m'=>HMASBR_COORD_BLOCK_M,'same_provider_target_claim_blocks'=>true,'requires_independent_geo_or_anex_tourvisor_bridge'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: run guarded CURRENT-DB workflow\n");exit(64);}
