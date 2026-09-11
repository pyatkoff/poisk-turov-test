<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_pending_candidate_bridge_review.php';

const MLP_OPERATION = 'hotel-match-live-priority-review-1971-20260911-v1';

function mlp_observations(PDO $db): array {
    $latest=[]; $counts=[]; $lastSeen=[];
    $sql="SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row){
        $id=(string)$row['external_hotel_id'];
        $counts[$id]=($counts[$id]??0)+1;
        if(!isset($latest[$id])){$latest[$id]=$row;$lastSeen[$id]=$row['observed_at_utc']??null;}
    }
    return [$latest,$counts,$lastSeen];
}

function mlp_critical_signature(string $name): array {
    $tokens=pcbr_identity_tokens($name); $critical=['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1]; $out=[];
    foreach($tokens as $t) if(isset($critical[$t])) $out[$t]=true;
    $keys=array_keys($out); sort($keys,SORT_STRING); return $keys;
}

function mlp_pair_guard(string $source,string $target,string $region): array {
    $s=pcbr_identity_tokens($source); $t=pcbr_identity_tokens($target); $drop=array_values(array_unique(array_merge(pcbr_region_tokens($region),['adult','adults','only','16'])));
    $s=pcbr_without_tokens($s,$drop); $t=pcbr_without_tokens($t,$drop);
    $shared=array_values(array_intersect($s,$t)); $sharedUnique=array_values(array_unique($shared));
    $sourceOnly=[]; foreach($s as $v) if(!in_array($v,$t,true)) $sourceOnly[]=$v;
    $targetOnly=[]; foreach($t as $v) if(!in_array($v,$s,true)) $targetOnly[]=$v;
    $criticalOk=mlp_critical_signature($source)===mlp_critical_signature($target);
    $anchorOk=isset($s[0],$t[0]) && $s[0]===$t[0];
    $dangerousSwap=$sourceOnly!==[] && $targetOnly!==[];
    return ['critical_ok'=>$criticalOk,'anchor_ok'=>$anchorOk,'shared_unique'=>count($sharedUnique),'source_only'=>$sourceOnly,'target_only'=>$targetOnly,'dangerous_swap'=>$dangerousSwap];
}

function mlp_best_pair(array $sourceNames,array $targetNames,string $region): array {
    $best=['score'=>0.0,'shared'=>0,'source'=>'','target'=>'','guard'=>[],'strict_ordered'=>null];
    foreach($sourceNames as $source){$source=trim((string)$source);if($source==='')continue;
        foreach($targetNames as $target){$target=trim((string)$target);if($target==='')continue;
            [$score,$shared]=mbr_similarity($source,$target);$guard=mlp_pair_guard($source,$target,$region);$strict=pcbr_strict_pair([$source],[$target],$region);
            $better=$strict!==null&&$best['strict_ordered']===null;
            if(!$better&&(($strict!==null)===($best['strict_ordered']!==null)))$better=$score>$best['score']||($score===$best['score']&&$shared>$best['shared']);
            if($better)$best=['score'=>round($score,6),'shared'=>$shared,'source'=>$source,'target'=>$target,'guard'=>$guard,'strict_ordered'=>$strict];
        }
    }
    return $best;
}

function mlp_source_context(array $row,?array $obs): array {
    $prior=fc_evidence($row['evidence_json']??''); $source=$prior['source']??[]; if(!is_array($source))$source=[]; $geo=$prior['geography']??[]; if(!is_array($geo))$geo=[];
    $names=array_values(array_unique(array_filter([(string)($source['name']??''),(string)($source['lName']??''),(string)($obs['hotel_name']??'')],static fn($v)=>trim($v)!=='')));
    $places=array_values(array_unique(array_filter([(string)($source['town']??''),(string)($geo['town']??''),(string)($geo['parent']??''),(string)($obs['region_name']??'')],static fn($v)=>trim($v)!=='')));
    $category=mbr_numeric_category($source);if($category===null&&$obs)$category=mbr_numeric_category($obs);
    $coord=$source;if($obs)$coord+=$obs;
    return [$prior,$source,$names,$places,$category,$coord];
}

function mlp_target_row(array $hotel,array $pair,array $guard,?int $sourceCategory,bool $anexLinked,string $rule): array {
    $targetCategory=$hotel['category']===null?null:(int)$hotel['category'];
    return [
        'rule'=>$rule,'target_local_hotel_id'=>(int)$hotel['id'],'target_name'=>(string)$hotel['name'],'target_region'=>(string)$hotel['region_name'],'target_subregion'=>(string)$hotel['subregion_name'],
        'source_name'=>$pair['source']??'','target_evidence_name'=>$pair['target']??'','score'=>$pair['score']??null,'shared'=>$pair['shared']??null,'pair_guard'=>$pair['guard']??null,'strict_identity'=>$pair['strict_ordered']??null,
        'distance_m'=>$guard['distance_m']??null,'coordinate_conflict'=>(bool)($guard['coordinate_conflict']??false),'source_category'=>$sourceCategory,'target_category'=>$targetCategory,'category_mismatch'=>$sourceCategory!==null&&$targetCategory!==null&&$sourceCategory!==$targetCategory,'existing_anex_link'=>$anexLinked,
    ];
}

function mlp_review(PDO $db,string $operation=MLP_OPERATION): array {
    if($operation!==MLP_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);$shaCountry=fc_sha_countries($db);[$latest,$counts,$lastSeen]=mlp_observations($db);
    $stats=['live_pending'=>0,'observation_rows'=>0,'exact_safe'=>0,'generic_exact_safe'=>0,'ordered_safe'=>0,'strong_evidence'=>0,'ambiguous'=>0,'coordinate_conflict'=>0,'no_direct_geo'=>0,'no_candidate'=>0,'category_mismatch_safe'=>0,'existing_anex_safe'=>0];
    $safe=[];$strong=[];$tail=[];
    $sql="SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row){
        $external=(string)$row['external_hotel_id'];$obs=$latest[$external]??null;$obsCount=(int)($counts[$external]??0);if(!$obs||$obsCount<1)continue;
        $country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$row['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;
        $stats['live_pending']++;$stats['observation_rows']+=$obsCount;[$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($row,$obs);
        $base=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'observation_count'=>$obsCount,'last_seen_utc'=>$lastSeen[$external]??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'catalog_sha256'=>$row['catalog_sha256']??null];
        $strictIds=mbr_ids_for_names($strict,$country,$sourceNames,false);$broadIds=mbr_ids_for_names($broad,$country,$sourceNames,true);$placeIds=mbr_place_pool($places,$country,$sourcePlaces);
        $picked=null;$rule='';
        if(count($strictIds)===1){
            $id=$strictIds[0];$hotel=$hotels[$id];$guard=mbr_target_guard($coordSource,$hotel);$geo=fc_place($sourcePlaces,[$hotel['region_name'],$hotel['subregion_name']]);
            if($guard['coordinate_conflict']){$stats['coordinate_conflict']++;}
            elseif($geo||($guard['distance_m']!==null&&$guard['distance_m']<=1000)){$pair=mlp_best_pair($sourceNames,$names[$id]??[$hotel['name']],(string)$hotel['region_name']);$picked=mlp_target_row($hotel,$pair,$guard,$sourceCategory,isset($anexLocal[$id]),'unique_exact_name_plus_geo');$rule='exact';}
            else{$stats['no_direct_geo']++;}
        }
        if($picked===null&&count($broadIds)===1){
            $id=$broadIds[0];$hotel=$hotels[$id];$guard=mbr_target_guard($coordSource,$hotel);$geo=fc_place($sourcePlaces,[$hotel['region_name'],$hotel['subregion_name']]);$pair=mlp_best_pair($sourceNames,$names[$id]??[$hotel['name']],(string)$hotel['region_name']);
            if($guard['coordinate_conflict']){$stats['coordinate_conflict']++;}
            elseif(($geo||($guard['distance_m']!==null&&$guard['distance_m']<=1000))&&($pair['guard']['critical_ok']??false)){$picked=mlp_target_row($hotel,$pair,$guard,$sourceCategory,isset($anexLocal[$id]),'unique_generic_name_plus_geo_critical_guard');$rule='generic';}
        }
        if($picked===null&&$placeIds){
            $rank=[];
            foreach($placeIds as $id){$hotel=$hotels[$id];$pair=mlp_best_pair($sourceNames,$names[$id]??[$hotel['name']],(string)$hotel['region_name']);if(($pair['shared']??0)<2)continue;$guard=mbr_target_guard($coordSource,$hotel);if($guard['coordinate_conflict'])continue;$rank[]=['id'=>$id,'pair'=>$pair,'guard'=>$guard];}
            usort($rank,static fn($a,$b)=>(int)($b['pair']['strict_ordered']!==null)<=>(int)($a['pair']['strict_ordered']!==null) ?: $b['pair']['score']<=>$a['pair']['score'] ?: $b['pair']['shared']<=>$a['pair']['shared'] ?: $a['id']<=>$b['id']);
            $best=$rank[0]??null;$second=$rank[1]??null;$margin=$best?($best['pair']['score']-($second['pair']['score']??0.0)):0.0;
            if($best&&$best['pair']['strict_ordered']!==null){$id=$best['id'];$picked=mlp_target_row($hotels[$id],$best['pair'],$best['guard'],$sourceCategory,isset($anexLocal[$id]),'ordered_identity_plus_direct_geo');$picked['score_margin']=round($margin,6);$rule='ordered';}
            elseif($best){
                $g=$best['pair']['guard'];$strongOk=($best['pair']['score']??0)>=0.82&&($best['pair']['shared']??0)>=3&&$margin>=0.15&&($g['critical_ok']??false)&&($g['anchor_ok']??false)&&!($g['dangerous_swap']??true);
                if($strongOk){$id=$best['id'];$candidate=$base+mlp_target_row($hotels[$id],$best['pair'],$best['guard'],$sourceCategory,isset($anexLocal[$id]),'strong_fuzzy_direct_geo_anchor_guard');$candidate['score_margin']=round($margin,6);$strong[]=$candidate;$stats['strong_evidence']++;}
                elseif($second&&abs($margin)<0.15)$stats['ambiguous']++;
            }
        }
        if($picked!==null){$candidate=$base+$picked;$safe[]=$candidate;if($rule==='exact')$stats['exact_safe']++;elseif($rule==='generic')$stats['generic_exact_safe']++;else$stats['ordered_safe']++;if($candidate['category_mismatch'])$stats['category_mismatch_safe']++;if($candidate['existing_anex_link'])$stats['existing_anex_safe']++;}
        else{$stats['no_candidate']++;$tail[]=$base+['prior_candidate_ids'=>array_values(array_unique(array_map('intval',$prior['candidate_ids']??[]))),'strict_ids'=>$strictIds,'broad_ids'=>array_slice($broadIds,0,10),'place_pool_size'=>count($placeIds)];}
    }
    $sort=static fn($a,$b)=>(int)($b['observation_count']??0)<=>(int)($a['observation_count']??0) ?: strcmp((string)$a['external_id'],(string)$b['external_id']);usort($safe,$sort);usort($strong,$sort);usort($tail,$sort);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'safe_prepared_count'=>count($safe),'strong_evidence_count'=>count($strong),'safe_prepared'=>$safe,'strong_evidence'=>$strong,'live_tail'=>$tail];
}
