<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const PCBR_OPERATION = 'hotel-match-pending-candidate-bridge-review-1971-20260911-v1';

function pcbr_compat(array $sourceNames, array $targetNames): array {
    $best=['score'=>0.0,'shared'=>0,'strict'=>false,'broad'=>false,'source'=>'','target'=>''];
    foreach($sourceNames as $source){$source=trim((string)$source);if($source==='')continue;foreach($targetNames as $target){$target=trim((string)$target);if($target==='')continue;
        $strict=fc_key($source,false,false)!==''&&fc_key($source,false,false)===fc_key($target,false,false);
        $broad=fc_key($source,true,true)!==''&&fc_key($source,true,true)===fc_key($target,true,true);
        [$score,$shared]=mbr_similarity($source,$target);
        if($strict||(!$best['strict']&&($broad&&!$best['broad']||($broad===$best['broad']&&($score>$best['score']||($score===$best['score']&&$shared>$best['shared']))))))$best=['score'=>round($score,6),'shared'=>$shared,'strict'=>$strict,'broad'=>$broad,'source'=>$source,'target'=>$target];
    }}
    return $best;
}

function pcbr_review(PDO $db,string $operation=PCBR_OPERATION): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);
    [$anexLocal,$andromedaLocal]=mbr_local_sets($db);
    $shaCountry=fc_sha_countries($db);
    $latest=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];if(!isset($latest[$id]))$latest[$id]=$o;}
    $stats=['pending_examined'=>0,'country_unknown'=>0,'prior_candidate_rows'=>0,'place_pool_rows'=>0,'anex_bridge_pool_rows'=>0,'geo_compatible_rows'=>0,'name_compatible_rows'=>0,'prepared'=>0,'category_mismatch'=>0,'ambiguous'=>0,'no_direct_place'=>0];
    $prepared=[];
    foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$prior=fc_evidence($r['evidence_json']??'');$source=$prior['source']??[];if(!is_array($source))$source=[];$geo=$prior['geography']??[];if(!is_array($geo))$geo=[];
        $country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country])){$stats['country_unknown']++;continue;}$stats['pending_examined']++;
        $sourceNames=array_values(array_unique(array_filter([(string)($source['name']??''),(string)($source['lName']??''),(string)($obs['hotel_name']??'')],static fn($v)=>trim($v)!=='')));
        $sourcePlaces=array_values(array_unique(array_filter([(string)($source['town']??''),(string)($geo['town']??''),(string)($geo['parent']??''),(string)($obs['region_name']??'')],static fn($v)=>trim($v)!=='')));
        $pool=[];$priorIds=array_values(array_unique(array_map('intval',$prior['candidate_ids']??[])));if($priorIds)$stats['prior_candidate_rows']++;
        foreach($priorIds as $id)if(isset($hotels[$id])&&(int)$hotels[$id]['country_id']===$country&&isset($anexLocal[$id]))$pool[$id]='prior_candidate';
        $placeIds=mbr_place_pool($places,$country,$sourcePlaces);if($placeIds)$stats['place_pool_rows']++;
        foreach($placeIds as $id)if(isset($anexLocal[$id]))$pool[$id]=isset($pool[$id])?'prior_plus_place':'place_anex_bridge';
        if(!$pool)continue;$stats['anex_bridge_pool_rows']++;
        $ranked=[];
        foreach($pool as $id=>$origin){$h=$hotels[$id];$place=fc_place($sourcePlaces,[$h['region_name'],$h['subregion_name']]);if(!$place)continue;$compat=pcbr_compat($sourceNames,$names[$id]??[$h['name']]);
            if(!$compat['broad']&&!$compat['strict']&&($compat['shared']<2||$compat['score']<0.60))continue;
            $ranked[]=['id'=>(int)$id,'origin'=>$origin,'compat'=>$compat,'target'=>mbr_row_target($h)];
        }
        if(!$ranked){$stats['no_direct_place']++;continue;}$stats['geo_compatible_rows']++;$stats['name_compatible_rows']++;
        usort($ranked,static fn($a,$b)=>(int)$b['compat']['strict']<=>(int)$a['compat']['strict'] ?: (int)$b['compat']['broad']<=>(int)$a['compat']['broad'] ?: $b['compat']['score']<=>$a['compat']['score'] ?: $b['compat']['shared']<=>$a['compat']['shared'] ?: $a['id']<=>$b['id']);
        $best=$ranked[0];$second=$ranked[1]??null;$margin=$second?($best['compat']['score']-$second['compat']['score']):1.0;
        if($second&&$best['compat']['strict']===$second['compat']['strict']&&$best['compat']['broad']===$second['compat']['broad']&&$margin<0.15){$stats['ambiguous']++;continue;}
        $sourceCat=mbr_numeric_category($source);if($sourceCat===null&&$obs)$sourceCat=mbr_numeric_category($obs);$targetCat=$hotels[$best['id']]['category']===null?null:(int)$hotels[$best['id']]['category'];$catMismatch=$sourceCat!==null&&$targetCat!==null&&$sourceCat!==$targetCat;if($catMismatch)$stats['category_mismatch']++;
        $prepared[]=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'target_local_hotel_id'=>$best['id'],'target_name'=>$hotels[$best['id']]['name'],'target_region'=>$hotels[$best['id']]['region_name'],'target_subregion'=>$hotels[$best['id']]['subregion_name'],'origin'=>$best['origin'],'name'=>$best['compat'],'score_margin'=>round($margin,6),'source_category'=>$sourceCat,'target_category'=>$targetCat,'category_mismatch'=>$catMismatch,'evidence'=>'current_pending_candidate_or_place_plus_existing_anex_tourvisor_plus_direct_geo'];
        $stats['prepared']++;
    }
    usort($prepared,static fn($a,$b)=>$a['country_id']<=>$b['country_id'] ?: strcmp($a['external_id'],$b['external_id']));
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'prepared_count'=>count($prepared),'prepared'=>$prepared];
}
