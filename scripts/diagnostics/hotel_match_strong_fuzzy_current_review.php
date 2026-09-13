<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_live_priority_review.php';

const HFSR_OPERATION = 'hotel-match-strong-fuzzy-current-review-1971-20260911-v1';

function hfsr_pair(string $source,string $target,string $region,bool $anexBridge): array {
    $drop=array_values(array_unique(array_merge(pcbr_region_tokens($region),['hotel','resort','spa','ex','the','adult','adults','only','16'])));
    $s=array_values(array_unique(pcbr_without_tokens(pcbr_identity_tokens($source),$drop)));
    $t=array_values(array_unique(pcbr_without_tokens(pcbr_identity_tokens($target),$drop)));
    $sharedTokens=array_values(array_unique(array_intersect($s,$t)));
    $sOnly=array_values(array_filter($s,static fn($v)=>!in_array($v,$t,true))); $tOnly=array_values(array_filter($t,static fn($v)=>!in_array($v,$s,true)));
    $den=count($s)+count($t); $score=$den>0?(2.0*count($sharedTokens)/$den):0.0; $shared=count($sharedTokens);
    $critical=mlp_critical_signature($source)===mlp_critical_signature($target);
    $anchor=isset($s[0],$t[0])&&$s[0]===$t[0];
    $diff=count($sOnly)+count($tOnly); $minTokens=min(count($s),count($t));
    $safe=$critical&&$anchor&&$diff<=2&&(
        ($anexBridge&&$minTokens>=2&&count($sharedTokens)>=2&&$score>=0.78) ||
        (!$anexBridge&&$minTokens>=3&&count($sharedTokens)>=3&&$score>=0.84)
    );
    return ['score'=>round($score,6),'shared'=>$shared,'shared_unique'=>count($sharedTokens),'source_tokens'=>$s,'target_tokens'=>$t,'source_only'=>$sOnly,'target_only'=>$tOnly,'token_diff'=>$diff,'critical_ok'=>$critical,'anchor_ok'=>$anchor,'existing_anex_link'=>$anexBridge,'safe_strong_fuzzy'=>$safe];
}

function hfsr_best_pair(array $sourceNames,array $targetNames,string $region,bool $anexBridge): array {
    $best=['score'=>0.0,'shared'=>0,'safe_strong_fuzzy'=>false,'source'=>'','target'=>''];
    foreach($sourceNames as $source){$source=trim((string)$source);if($source==='')continue;foreach($targetNames as $target){$target=trim((string)$target);if($target==='')continue;$pair=hfsr_pair($source,$target,$region,$anexBridge);if((int)$pair['safe_strong_fuzzy']>(int)($best['safe_strong_fuzzy']??false)||((bool)$pair['safe_strong_fuzzy']===(bool)($best['safe_strong_fuzzy']??false)&&($pair['score']>$best['score']||($pair['score']===$best['score']&&$pair['shared']>$best['shared'])))){$best=$pair+['source'=>$source,'target'=>$target];}}}
    return $best;
}

function hfsr_review(PDO $db,string $operation=HFSR_OPERATION): array {
    if($operation!==HFSR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db); [$anexLocal,$andromedaLocal]=mbr_local_sets($db); $shaCountry=fc_sha_countries($db); [$latest,$counts,$lastSeen]=mlp_observations($db);
    $stats=['pending_examined'=>0,'live_pending'=>0,'observation_rows'=>0,'direct_place_rows'=>0,'candidate_rows'=>0,'safe'=>0,'safe_live'=>0,'safe_anex_bridge'=>0,'safe_tourvisor_only'=>0,'category_mismatch_safe'=>0,'margin_block'=>0,'critical_or_anchor_block'=>0];
    $safe=[];$near=[];
    $sql="SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row){
        $external=(string)$row['external_hotel_id'];$obs=$latest[$external]??null;$obsCount=(int)($counts[$external]??0);$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$row['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;
        $stats['pending_examined']++;if($obsCount>0){$stats['live_pending']++;$stats['observation_rows']+=$obsCount;}
        [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($row,$obs);$placeIds=mbr_place_pool($places,$country,$sourcePlaces);if(!$placeIds)continue;$stats['direct_place_rows']++;
        $rank=[];
        foreach($placeIds as $id){$id=(int)$id;if(!isset($hotels[$id]))continue;$hotel=$hotels[$id];$anex=isset($anexLocal[$id]);$pair=hfsr_best_pair($sourceNames,$names[$id]??[$hotel['name']],(string)$hotel['region_name'],$anex);if(!($pair['critical_ok']??false)||!($pair['anchor_ok']??false))continue;$stats['candidate_rows']++;$rank[]=['id'=>$id,'pair'=>$pair,'anex'=>$anex];}
        if(!$rank)continue;
        usort($rank,static fn($a,$b)=>(int)$b['pair']['safe_strong_fuzzy']<=>(int)$a['pair']['safe_strong_fuzzy'] ?: $b['pair']['score']<=>$a['pair']['score'] ?: $b['pair']['shared']<=>$a['pair']['shared'] ?: $a['id']<=>$b['id']);
        $best=$rank[0];$second=$rank[1]??null;$margin=$second?($best['pair']['score']-$second['pair']['score']):1.0;
        if(!($best['pair']['safe_strong_fuzzy']??false)){$stats['critical_or_anchor_block']++;continue;}
        if($margin<0.12){$stats['margin_block']++;$near[]=['external_id'=>$external,'target_local_hotel_id'=>$best['id'],'score'=>$best['pair']['score'],'score_margin'=>round($margin,6)];continue;}
        $target=$hotels[$best['id']];$targetCategory=$target['category']===null?null:(int)$target['category'];$mismatch=$sourceCategory!==null&&$targetCategory!==null&&$sourceCategory!==$targetCategory;
        $item=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'live_observed'=>$obsCount>0,'observation_count'=>$obsCount,'last_seen_utc'=>$lastSeen[$external]??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'target_local_hotel_id'=>$best['id'],'target_name'=>$target['name'],'target_region'=>$target['region_name'],'target_subregion'=>$target['subregion_name'],'target_category'=>$targetCategory,'pair'=>$best['pair'],'score_margin'=>round($margin,6),'existing_anex_link'=>$best['anex'],'category_mismatch'=>$mismatch,'rule'=>$best['anex']?'strong_fuzzy_direct_geo_anex_bridge':'strong_fuzzy_direct_geo_tourvisor'];
        $safe[]=$item;$stats['safe']++;if($obsCount>0)$stats['safe_live']++;if($best['anex'])$stats['safe_anex_bridge']++;else$stats['safe_tourvisor_only']++;if($mismatch)$stats['category_mismatch_safe']++;
    }
    usort($safe,static fn($a,$b)=>(int)$b['live_observed']<=>(int)$a['live_observed'] ?: (int)$b['existing_anex_link']<=>(int)$a['existing_anex_link'] ?: $b['observation_count']<=>$a['observation_count'] ?: $b['pair']['score']<=>$a['pair']['score'] ?: strcmp($a['external_id'],$b['external_id']));
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'safe_prepared_count'=>count($safe),'near_ambiguous_count'=>count($near),'safe_prepared'=>$safe,'near_ambiguous'=>$near];
}
