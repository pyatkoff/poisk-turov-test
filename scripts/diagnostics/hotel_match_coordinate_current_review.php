<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_live_priority_review.php';

const HCCR_OPERATION = 'hotel-match-coordinate-current-review-1971-20260911-v2';

function hccr_name_evidence(array $sourceNames,array $targetNames,string $region): array {
    $best=['rank'=>0,'score'=>0.0,'shared'=>0,'strict'=>false,'broad'=>false,'critical_ok'=>false,'anchor_ok'=>false,'source'=>'','target'=>''];
    foreach($sourceNames as $source){
        $source=trim((string)$source); if($source==='')continue;
        foreach($targetNames as $target){
            $target=trim((string)$target); if($target==='')continue;
            $strict=fc_key($source,false,false)!=='' && fc_key($source,false,false)===fc_key($target,false,false);
            $broad=fc_key($source,true,true)!=='' && fc_key($source,true,true)===fc_key($target,true,true);
            [$score,$shared]=mbr_similarity($source,$target);
            $guard=mlp_pair_guard($source,$target,$region);
            $critical=(bool)($guard['critical_ok']??false); $anchor=(bool)($guard['anchor_ok']??false);
            $rank=$strict?4:($broad?3:(($anchor&&$shared>=2&&$score>=0.65)?2:(($anchor&&$shared>=1&&$score>=0.50)?1:0)));
            if($rank>$best['rank']||($rank===$best['rank']&&($score>$best['score']||($score===$best['score']&&$shared>$best['shared'])))){
                $best=['rank'=>$rank,'score'=>round($score,6),'shared'=>$shared,'strict'=>$strict,'broad'=>$broad,'critical_ok'=>$critical,'anchor_ok'=>$anchor,'source'=>$source,'target'=>$target];
            }
        }
    }
    return $best;
}

function hccr_candidate_rule(array $name,int $distance,?int $margin): ?string {
    if(!($name['critical_ok']??false))return null;
    if($margin!==null&&$margin<75)return null;
    if(($name['strict']??false) && $distance<=1000)return 'coordinate_strict_name_direct_geo';
    if(($name['broad']??false) && ($name['anchor_ok']??false) && (($name['shared']??0)>=2 || $distance<=100) && $distance<=500)return 'coordinate_broad_name_direct_geo';
    if(($name['anchor_ok']??false) && ($name['shared']??0)>=2 && ($name['score']??0)>=0.65 && $distance<=200 && ($margin===null||$margin>=150))return 'coordinate_fuzzy_name_direct_geo';
    return null;
}

function hccr_review(PDO $db,string $operation=HCCR_OPERATION): array {
    if($operation!==HCCR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);
    [$anexLocal,$andromedaLocal]=mbr_local_sets($db);
    $shaCountry=fc_sha_countries($db);
    [$latest,$counts,$lastSeen]=mlp_observations($db);
    $stats=[
        'pending_examined'=>0,'live_pending'=>0,'observation_rows'=>0,'with_coordinates'=>0,'with_direct_place'=>0,
        'nearest_within_5km'=>0,'safe'=>0,'safe_live'=>0,'safe_existing_anex'=>0,'category_mismatch_safe'=>0,
        'strict_safe'=>0,'broad_safe'=>0,'fuzzy_safe'=>0,'critical_block'=>0,'nearest_ambiguous'=>0,'over_5km_or_none'=>0,
    ];
    $safe=[];$evidence=[];
    $sql="SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row){
        $external=(string)$row['external_hotel_id']; $obs=$latest[$external]??null; $obsCount=(int)($counts[$external]??0);
        $country=(int)($obs['country_id']??0); if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$row['catalog_sha256']]??0); if(!isset(MBR_CORE8[$country]))continue;
        $stats['pending_examined']++; if($obsCount>0){$stats['live_pending']++;$stats['observation_rows']+=$obsCount;}
        [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($row,$obs);
        [$lat,$lon]=mbr_coord($coordSource); if($lat===null||$lon===null)continue; $stats['with_coordinates']++;
        $placeIds=mbr_place_pool($places,$country,$sourcePlaces); if(!$placeIds)continue; $stats['with_direct_place']++;
        $rank=[];
        foreach($placeIds as $id){
            $id=(int)$id; if(!isset($hotels[$id]))continue; $hotel=$hotels[$id];
            $distance=fc_dist($lat,$lon,$hotel['latitude']??null,$hotel['longitude']??null); if($distance===null||$distance>5000)continue;
            $name=hccr_name_evidence($sourceNames,$names[$id]??[$hotel['name']],(string)$hotel['region_name']);
            $rank[]=['id'=>$id,'distance_m'=>(int)round($distance),'name'=>$name];
        }
        if(!$rank){$stats['over_5km_or_none']++;continue;}
        usort($rank,static fn($a,$b)=>$a['distance_m']<=>$b['distance_m'] ?: $b['name']['rank']<=>$a['name']['rank'] ?: $b['name']['score']<=>$a['name']['score'] ?: $a['id']<=>$b['id']);
        $best=$rank[0]; $second=$rank[1]??null; $margin=$second?((int)$second['distance_m']-(int)$best['distance_m']):null; $stats['nearest_within_5km']++;
        if(!($best['name']['critical_ok']??false)){$stats['critical_block']++;continue;}
        $rule=hccr_candidate_rule($best['name'],(int)$best['distance_m'],$margin);
        if($rule===null){$stats['nearest_ambiguous']++; $evidence[]=['external_id'=>$external,'country_id'=>$country,'target_local_hotel_id'=>$best['id'],'distance_m'=>$best['distance_m'],'distance_margin_m'=>$margin,'name'=>$best['name']]; continue;}
        $target=$hotels[$best['id']]; $targetCategory=$target['category']===null?null:(int)$target['category']; $mismatch=$sourceCategory!==null&&$targetCategory!==null&&$sourceCategory!==$targetCategory;
        $item=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'live_observed'=>$obsCount>0,'observation_count'=>$obsCount,'last_seen_utc'=>$lastSeen[$external]??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'latitude'=>$lat,'longitude'=>$lon,'target_local_hotel_id'=>$best['id'],'target_name'=>$target['name'],'target_region'=>$target['region_name'],'target_subregion'=>$target['subregion_name'],'target_category'=>$targetCategory,'distance_m'=>$best['distance_m'],'distance_margin_m'=>$margin,'name'=>$best['name'],'rule'=>$rule,'existing_anex_link'=>isset($anexLocal[$best['id']]),'category_mismatch'=>$mismatch];
        $safe[]=$item; $stats['safe']++; if($obsCount>0)$stats['safe_live']++; if($item['existing_anex_link'])$stats['safe_existing_anex']++; if($mismatch)$stats['category_mismatch_safe']++;
        if($rule==='coordinate_strict_name_direct_geo')$stats['strict_safe']++; elseif($rule==='coordinate_broad_name_direct_geo')$stats['broad_safe']++; else $stats['fuzzy_safe']++;
    }
    usort($safe,static fn($a,$b)=>(int)$b['live_observed']<=>(int)$a['live_observed'] ?: (int)$b['existing_anex_link']<=>(int)$a['existing_anex_link'] ?: (int)$b['observation_count']<=>(int)$a['observation_count'] ?: $a['distance_m']<=>$b['distance_m'] ?: strcmp($a['external_id'],$b['external_id']));
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'safe_prepared_count'=>count($safe),'evidence_only_count'=>count($evidence),'safe_prepared'=>$safe,'evidence_only'=>$evidence];
}
