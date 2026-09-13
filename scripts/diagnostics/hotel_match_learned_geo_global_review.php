<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const HMLG_OPERATION='hotel-match-learned-geo-global-review-2333-20260914-v1';

function hmlg_latin(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');
    return strtr($v,['щ'=>'shch','ш'=>'sh','ч'=>'ch','ц'=>'ts','ю'=>'yu','я'=>'ya','ё'=>'e','ж'=>'zh','х'=>'kh','а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','ы'=>'y','э'=>'e','ь'=>'','ъ'=>'']);
}
function hmlg_place_key(string $v): string { return fc_norm(hmlg_latin($v)); }
function hmlg_target_place_keys(array $h): array {
    $out=[]; foreach([(string)($h['region_name']??''),(string)($h['subregion_name']??'')] as $v){$k=hmlg_place_key($v);if($k!=='')$out[$k]=1;} return array_keys($out);
}
function hmlg_critical(string $v): array {
    $aliases=['gardens'=>'garden','beaches'=>'beach','annexe'=>'annex','annexe'=>'annex'];
    $critical=['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1]; $out=[];
    foreach(fc_tokens($v,true) as $t){$t=$aliases[$t]??$t;if(isset($critical[$t]))$out[$t]=1;} ksort($out); return array_keys($out);
}
function hmlg_qualifiers_ok(array $sourceNames,array $targetNames): bool {
    $sourceSets=[]; foreach($sourceNames as $n)$sourceSets[implode('|',hmlg_critical((string)$n))]=1;
    foreach($targetNames as $n)if(isset($sourceSets[implode('|',hmlg_critical((string)$n))]))return true;
    return false;
}
function hmlg_best_geo(array $learn,array $sourcePlaces,array $targetKeys): ?array {
    $targetSet=array_fill_keys($targetKeys,true); $best=null;
    foreach($sourcePlaces as $place){$pk=hmlg_place_key((string)$place);if($pk===''||empty($learn[$pk]))continue;$total=array_sum($learn[$pk]);
        foreach($learn[$pk] as $tk=>$n){if(!isset($targetSet[$tk]))continue;$c=$total>0?$n/$total:0.0;$x=['source_place'=>(string)$place,'source_place_key'=>$pk,'target_place_key'=>$tk,'support'=>(int)$n,'total'=>(int)$total,'confidence'=>round($c,6)];
            if($best===null||$x['confidence']>$best['confidence']||($x['confidence']===$best['confidence']&&$x['support']>$best['support']))$best=$x;}}
    return $best;
}
function hmlg_review(PDO $db,string $operation=HMLG_OPERATION): array {
    if($operation!==HMLG_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db); $shaCountry=fc_sha_countries($db);
    $latest=[];$obsCount=[]; foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$obsCount[$id]=($obsCount[$id]??0)+1;if(!isset($latest[$id]))$latest[$id]=$o;}
    $learn=[];$training=0;
    foreach($db->query("SELECT local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){$lid=(int)$r['local_hotel_id'];if(!isset($hotels[$lid]))continue;$e=fc_evidence($r['evidence_json']??'');$src=$e['source']??($e['prior_evidence']['source']??[]);if(!is_array($src))$src=[];$geo=$e['geography']??($e['prior_evidence']['geography']??[]);if(!is_array($geo))$geo=[];$sp=[];foreach([(string)($src['town']??''),(string)($geo['town']??''),(string)($geo['parent']??'')] as $v){$k=hmlg_place_key($v);if($k!=='')$sp[$k]=1;}$tk=hmlg_target_place_keys($hotels[$lid]);if(!$sp||!$tk)continue;$training++;foreach(array_keys($sp) as $s)foreach($tk as $t)$learn[$s][$t]=($learn[$s][$t]??0)+1;}
    $stats=['pending_examined'=>0,'observed_pending'=>0,'strict_unique'=>0,'broad_unique'=>0,'identity_ambiguous'=>0,'no_unique_identity'=>0,'direct_geo'=>0,'learned_geo_supported'=>0,'safe'=>0,'medium'=>0,'coordinate_conflict'=>0,'qualifier_conflict'=>0,'weak_geo'=>0,'country_unknown'=>0,'star_mismatch_safe'=>0];
    $safe=[];$medium=[];$blockedReasons=[];
    foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$prior=fc_evidence($r['evidence_json']??'');$src=$prior['source']??[];if(!is_array($src))$src=[];$geo=$prior['geography']??[];if(!is_array($geo))$geo=[];$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country])){$stats['country_unknown']++;continue;}$stats['pending_examined']++;$oc=(int)($obsCount[$external]??0);if($oc>0)$stats['observed_pending']++;
        $sourceNames=array_values(array_unique(array_filter([(string)($src['name']??''),(string)($src['lName']??''),(string)($obs['hotel_name']??'')],static fn($v)=>trim($v)!=='')));
        $sourcePlaces=array_values(array_unique(array_filter([(string)($src['town']??''),(string)($geo['town']??''),(string)($geo['parent']??''),(string)($obs['region_name']??'')],static fn($v)=>trim($v)!=='')));
        $strictIds=mbr_ids_for_names($strict,$country,$sourceNames,false);$broadIds=[];$identity='';$ids=[];
        if(count($strictIds)===1){$ids=$strictIds;$identity='strict';$stats['strict_unique']++;}
        elseif(count($strictIds)>1){$stats['identity_ambiguous']++;$blockedReasons['strict_name_ambiguous']=($blockedReasons['strict_name_ambiguous']??0)+1;continue;}
        else{$broadIds=mbr_ids_for_names($broad,$country,$sourceNames,true);if(count($broadIds)===1){$ids=$broadIds;$identity='broad';$stats['broad_unique']++;}elseif(count($broadIds)>1){$stats['identity_ambiguous']++;$blockedReasons['broad_name_ambiguous']=($blockedReasons['broad_name_ambiguous']??0)+1;continue;}else{$stats['no_unique_identity']++;$blockedReasons['no_unique_identity']=($blockedReasons['no_unique_identity']??0)+1;continue;}}
        $lid=(int)$ids[0];$target=$hotels[$lid];$targetNames=$names[$lid]??[(string)$target['name']];if(!hmlg_qualifiers_ok($sourceNames,$targetNames)){$stats['qualifier_conflict']++;$blockedReasons['meaningful_qualifier_conflict']=($blockedReasons['meaningful_qualifier_conflict']??0)+1;continue;}
        $coord=$src;if($obs)$coord+=$obs;$guard=mbr_target_guard($coord,$target);if($guard['coordinate_conflict']){$stats['coordinate_conflict']++;$blockedReasons['coordinate_conflict_gt_5km']=($blockedReasons['coordinate_conflict_gt_5km']??0)+1;continue;}
        $direct=fc_place($sourcePlaces,[(string)$target['region_name'],(string)$target['subregion_name']]);if($direct)$stats['direct_geo']++;
        $learned=hmlg_best_geo($learn,$sourcePlaces,hmlg_target_place_keys($target));if($learned)$stats['learned_geo_supported']++;
        $strongLearned=$learned!==null&&$learned['support']>=3&&$learned['confidence']>=0.80;$mediumLearned=$learned!==null&&$learned['support']>=2&&$learned['confidence']>=0.67;
        $sourceCat=mbr_numeric_category($src);if($sourceCat===null&&$obs)$sourceCat=mbr_numeric_category($obs);$targetCat=$target['category']===null?null:(int)$target['category'];$starMismatch=$sourceCat!==null&&$targetCat!==null&&$sourceCat!==$targetCat;
        $base=['external_id'=>$external,'country_id'=>$country,'observation_count'=>$oc,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'identity'=>$identity,'target'=>mbr_row_target($target),'guard'=>$guard,'learned_geo'=>$learned,'direct_geo'=>(bool)$direct,'source_category'=>$sourceCat,'target_category'=>$targetCat,'star_mismatch'=>$starMismatch];
        $isSafe=($identity==='strict'&&($direct||$strongLearned))||($identity==='broad'&&$strongLearned&&$learned['support']>=5&&$learned['confidence']>=0.90);
        if($isSafe){$base['tier']='safe';$base['reason']=$direct?'unique_identity_plus_direct_geo':'unique_identity_plus_strong_learned_geo';$safe[]=$base;$stats['safe']++;if($starMismatch)$stats['star_mismatch_safe']++;continue;}
        if(($identity==='strict'&&$mediumLearned)||($identity==='broad'&&$strongLearned)){$base['tier']='medium';$base['reason']='unique_identity_plus_nonfinal_learned_geo';$medium[]=$base;$stats['medium']++;continue;}
        $stats['weak_geo']++;$blockedReasons['unique_identity_without_strong_geo']=($blockedReasons['unique_identity_without_strong_geo']??0)+1;
    }
    $sort=static fn($a,$b)=>(int)$b['observation_count']<=>(int)$a['observation_count'] ?: $a['country_id']<=>$b['country_id'] ?: strcmp($a['external_id'],$b['external_id']);usort($safe,$sort);usort($medium,$sort);arsort($blockedReasons);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'historical_operations_replayed'=>false,'training_rows'=>$training,'learned_place_keys'=>count($learn),'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'blocked_reasons'=>$blockedReasons,'safe_count'=>count($safe),'medium_count'=>count($medium),'safe'=>$safe,'medium'=>$medium];
}
