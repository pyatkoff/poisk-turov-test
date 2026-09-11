<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_live_priority_review.php';

const HABFR_OPERATION = 'hotel-match-anex-bridge-fuzzy-review-1971-20260911-v1';

function habfr_is_subsequence(array $small, array $large): bool {
    if (!$small || count($small) > count($large)) return false;
    $i = 0;
    foreach ($large as $token) {
        if ($i < count($small) && $token === $small[$i]) $i++;
    }
    return $i === count($small);
}

function habfr_pair_policy(string $source, string $target, string $region): array {
    $drop = array_values(array_unique(array_merge(pcbr_region_tokens($region), ['adult','adults','only','16'])));
    $s = pcbr_without_tokens(pcbr_identity_tokens($source), $drop);
    $t = pcbr_without_tokens(pcbr_identity_tokens($target), $drop);
    $criticalOk = mlp_critical_signature($source) === mlp_critical_signature($target);
    $anchorOk = isset($s[0], $t[0]) && $s[0] === $t[0];
    $shared = array_values(array_unique(array_intersect($s, $t)));
    $sOnly = array_values(array_filter($s, static fn($v) => !in_array($v, $t, true)));
    $tOnly = array_values(array_filter($t, static fn($v) => !in_array($v, $s, true)));
    $oneSided = ($sOnly === [] || $tOnly === []) && !($sOnly === [] && $tOnly === []);
    $extra = count($sOnly) + count($tOnly);
    $subsequence = habfr_is_subsequence($s, $t) || habfr_is_subsequence($t, $s);
    [$score, $sharedCount] = mbr_similarity($source, $target);
    $safeContainment = $criticalOk && $anchorOk && $oneSided && $extra <= 1 && $subsequence && count($shared) >= 2 && $score >= 0.60;
    $evidenceFuzzy = $criticalOk && $anchorOk && count($shared) >= 3 && $score >= 0.75 && !($sOnly !== [] && $tOnly !== []);
    return [
        'source_tokens'=>$s,'target_tokens'=>$t,'critical_ok'=>$criticalOk,'anchor_ok'=>$anchorOk,
        'shared_unique'=>count($shared),'source_only'=>$sOnly,'target_only'=>$tOnly,'one_sided'=>$oneSided,
        'extra_token_count'=>$extra,'subsequence'=>$subsequence,'score'=>round($score,6),'shared'=>$sharedCount,
        'safe_containment'=>$safeContainment,'evidence_fuzzy'=>$evidenceFuzzy,
    ];
}

function habfr_best_policy(array $sourceNames, array $targetNames, string $region): array {
    $best = ['safe_rank'=>0,'score'=>0.0,'shared'=>0,'source'=>'','target'=>'','policy'=>[]];
    foreach ($sourceNames as $source) {
        $source = trim((string)$source); if ($source === '') continue;
        foreach ($targetNames as $target) {
            $target = trim((string)$target); if ($target === '') continue;
            $policy = habfr_pair_policy($source, $target, $region);
            $safeRank = $policy['safe_containment'] ? 2 : ($policy['evidence_fuzzy'] ? 1 : 0);
            if ($safeRank > $best['safe_rank'] || ($safeRank === $best['safe_rank'] && ($policy['score'] > $best['score'] || ($policy['score'] === $best['score'] && $policy['shared'] > $best['shared'])))) {
                $best = ['safe_rank'=>$safeRank,'score'=>$policy['score'],'shared'=>$policy['shared'],'source'=>$source,'target'=>$target,'policy'=>$policy];
            }
        }
    }
    return $best;
}

function habfr_review(PDO $db, string $operation=HABFR_OPERATION): array {
    if ($operation !== HABFR_OPERATION) throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope] = mbr_catalog($db);
    [$anexLocal,$andromedaLocal] = mbr_local_sets($db);
    $shaCountry = fc_sha_countries($db);
    [$latest,$counts,$lastSeen] = mlp_observations($db);
    $stats = [
        'pending_examined'=>0,'live_pending'=>0,'observation_rows'=>0,'prior_candidate_rows'=>0,'direct_place_rows'=>0,
        'anex_bridge_pool_rows'=>0,'direct_geo_candidates'=>0,'safe_containment_rows'=>0,'safe_live'=>0,
        'evidence_fuzzy_rows'=>0,'ambiguous_safe'=>0,'coordinate_conflict'=>0,'category_mismatch_safe'=>0,'no_bridge_candidate'=>0,
    ];
    $safe=[]; $evidence=[];
    $sql = "SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id";
    foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $external=(string)$row['external_hotel_id']; $obs=$latest[$external]??null; $obsCount=(int)($counts[$external]??0);
        $country=(int)($obs['country_id']??0); if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$row['catalog_sha256']]??0); if(!isset(MBR_CORE8[$country]))continue;
        $stats['pending_examined']++; if($obsCount>0){$stats['live_pending']++;$stats['observation_rows']+=$obsCount;}
        [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($row,$obs);
        $priorIds=array_values(array_unique(array_map('intval',$prior['candidate_ids']??[]))); if($priorIds)$stats['prior_candidate_rows']++;
        $placeIds=mbr_place_pool($places,$country,$sourcePlaces); if($placeIds)$stats['direct_place_rows']++;
        $pool=[];
        foreach($priorIds as $id) if(isset($hotels[$id]) && (int)$hotels[$id]['country_id']===$country && isset($anexLocal[$id])) $pool[$id]='prior';
        foreach($placeIds as $id) if(isset($anexLocal[$id])) $pool[$id]=isset($pool[$id])?'prior+place':'place';
        if(!$pool){$stats['no_bridge_candidate']++;continue;} $stats['anex_bridge_pool_rows']++;
        $rank=[];
        foreach($pool as $id=>$origin){
            $hotel=$hotels[$id];
            if(!fc_place($sourcePlaces,[$hotel['region_name'],$hotel['subregion_name']]))continue;
            $pair=habfr_best_policy($sourceNames,$names[$id]??[$hotel['name']],(string)$hotel['region_name']);
            if(($pair['safe_rank']??0)===0)continue;
            $guard=mbr_target_guard($coordSource,$hotel); if($guard['coordinate_conflict']){$stats['coordinate_conflict']++;continue;}
            $stats['direct_geo_candidates']++;
            $rank[]=['id'=>(int)$id,'origin'=>$origin,'pair'=>$pair,'guard'=>$guard];
        }
        if(!$rank)continue;
        usort($rank,static fn($a,$b)=>$b['pair']['safe_rank']<=>$a['pair']['safe_rank'] ?: $b['pair']['score']<=>$a['pair']['score'] ?: $b['pair']['shared']<=>$a['pair']['shared'] ?: $a['id']<=>$b['id']);
        $best=$rank[0]; $second=$rank[1]??null; $margin=$second?($best['pair']['score']-$second['pair']['score']):1.0;
        $base=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'live_observed'=>$obsCount>0,'observation_count'=>$obsCount,'last_seen_utc'=>$lastSeen[$external]??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'target_local_hotel_id'=>$best['id'],'target_name'=>$hotels[$best['id']]['name'],'target_region'=>$hotels[$best['id']]['region_name'],'target_subregion'=>$hotels[$best['id']]['subregion_name'],'origin'=>$best['origin'],'pair'=>$best['pair'],'score_margin'=>round($margin,6),'distance_m'=>$best['guard']['distance_m']??null,'existing_anex_link'=>true];
        $targetCategory=$hotels[$best['id']]['category']===null?null:(int)$hotels[$best['id']]['category'];
        $base['source_category']=$sourceCategory; $base['target_category']=$targetCategory; $base['category_mismatch']=$sourceCategory!==null&&$targetCategory!==null&&$sourceCategory!==$targetCategory;
        $safeCandidates=array_values(array_filter($rank,static fn($c)=>($c['pair']['safe_rank']??0)===2));
        if(count($safeCandidates)===1 && ($best['pair']['safe_rank']??0)===2 && $margin>=0.15){
            $base['rule']='anex_bridge_one_sided_identity_plus_direct_geo'; $safe[]=$base; $stats['safe_containment_rows']++; if($obsCount>0)$stats['safe_live']++; if($base['category_mismatch'])$stats['category_mismatch_safe']++;
        } elseif(count($safeCandidates)>1) {
            $stats['ambiguous_safe']++;
        } else {
            $base['rule']='anex_bridge_high_fuzzy_evidence_only'; $evidence[]=$base; $stats['evidence_fuzzy_rows']++;
        }
    }
    $sort=static fn($a,$b)=>(int)($b['live_observed']??false)<=>(int)($a['live_observed']??false) ?: (int)($b['observation_count']??0)<=>(int)($a['observation_count']??0) ?: strcmp((string)$a['external_id'],(string)$b['external_id']);
    usort($safe,$sort); usort($evidence,$sort);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'safe_prepared_count'=>count($safe),'evidence_only_count'=>count($evidence),'safe_prepared'=>$safe,'evidence_only'=>$evidence];
}
