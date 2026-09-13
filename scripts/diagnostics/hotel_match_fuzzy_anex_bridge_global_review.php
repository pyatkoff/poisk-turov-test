<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_fuzzy_learned_geo_global_review.php';

const HMAB_OPERATION='hotel-match-fuzzy-anex-bridge-global-review-2333-20260914-v1';

function hmab_numbers(string $v): array {
    preg_match_all('/(?<![\\p{L}\\p{N}])\\d+(?![\\p{L}\\p{N}])/u', hmlg_latin($v), $m);
    $out=[]; foreach($m[0]??[] as $n)$out[(string)((int)$n)]=1;
    $x=array_keys($out); sort($x,SORT_NATURAL); return $x;
}
function hmab_numeric_parity(string $a,string $b): bool {
    $x=hmab_numbers($a);$y=hmab_numbers($b);
    if(!$x&&!$y)return true;
    return $x===$y;
}
function hmab_add_anchor(array &$anchorNames,array &$tokenIndex,int $localId,array $row): void {
    $names=[];foreach(['api_name','xml_name','xml_alternate_name'] as $k){$v=trim((string)($row[$k]??''));if($v!=='')$names[$v]=1;}
    if(!$names)return;
    foreach(array_keys($names) as $name){$anchorNames[$localId][$name]=1;foreach(hmfg_tokens($name) as $t){if(mb_strlen($t,'UTF-8')<2)continue;$tokenIndex[$t][$localId]=1;}}
}
function hmab_review(PDO $db,string $operation=HMAB_OPERATION): array {
    if($operation!==HMAB_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);$shaCountry=fc_sha_countries($db);

    $latest=[];$obsCount=[];
    foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$obsCount[$id]=($obsCount[$id]??0)+1;if(!isset($latest[$id]))$latest[$id]=$o;}

    $andAccepted=[];$learn=[];$training=0;
    foreach($db->query("SELECT local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){$lid=(int)$r['local_hotel_id'];$andAccepted[$lid]=1;if(!isset($hotels[$lid]))continue;$e=fc_evidence($r['evidence_json']??'');$src=$e['source']??($e['prior_evidence']['source']??[]);if(!is_array($src))$src=[];$geo=$e['geography']??($e['prior_evidence']['geography']??[]);if(!is_array($geo))$geo=[];$sp=[];foreach([(string)($src['town']??''),(string)($geo['town']??''),(string)($geo['parent']??'')] as $v){$k=hmlg_place_key($v);if($k!=='')$sp[$k]=1;}$tk=hmlg_target_place_keys($hotels[$lid]);if(!$sp||!$tk)continue;$training++;foreach(array_keys($sp) as $s)foreach($tk as $t)$learn[$s][$t]=($learn[$s][$t]??0)+1;}

    $anchorNames=[];$tokenIndex=[];$anchorRows=0;
    $sql="SELECT m.anex_hotel_id,m.catalog_hotel_id,h.api_name,h.xml_name,h.xml_alternate_name FROM anex_hotel_search_mappings m LEFT JOIN anex_hotels h ON h.anex_hotel_id=m.anex_hotel_id LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".FC_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r){$lid=(int)$r['catalog_hotel_id'];if(!isset($hotels[$lid]))continue;$anchorRows++;hmab_add_anchor($anchorNames,$tokenIndex,$lid,$r);}
    $sql="SELECT d.anex_hotel_id,d.catalog_hotel_id,h.api_name,h.xml_name,h.xml_alternate_name FROM anex_hotel_decisions d LEFT JOIN anex_hotels h ON h.anex_hotel_id=d.anex_hotel_id WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r){$lid=(int)$r['catalog_hotel_id'];if(!isset($hotels[$lid]))continue;$anchorRows++;hmab_add_anchor($anchorNames,$tokenIndex,$lid,$r);}
    foreach($anchorNames as $lid=>$set)$anchorNames[$lid]=array_keys($set);

    $stats=['pending_examined'=>0,'no_unique_identity_examined'=>0,'observed'=>0,'token_pool'=>0,'ranked'=>0,'safe'=>0,'medium'=>0,'no_anchor_tokens'=>0,'weak_score'=>0,'weak_margin'=>0,'weak_geo'=>0,'numeric_conflict'=>0,'qualifier_conflict'=>0,'coordinate_conflict'=>0,'occupied_target'=>0,'country_unknown'=>0];
    $safe=[];$medium=[];$reasons=[];
    foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$prior=fc_evidence($r['evidence_json']??'');$src=$prior['source']??[];if(!is_array($src))$src=[];$geo=$prior['geography']??[];if(!is_array($geo))$geo=[];
        $country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country])){$stats['country_unknown']++;continue;}$stats['pending_examined']++;
        $sourceNames=array_values(array_unique(array_filter([(string)($src['name']??''),(string)($src['lName']??''),(string)($obs['hotel_name']??'')],static fn($v)=>trim($v)!=='')));
        $sourcePlaces=array_values(array_unique(array_filter([(string)($src['town']??''),(string)($geo['town']??''),(string)($geo['parent']??''),(string)($obs['region_name']??'')],static fn($v)=>trim($v)!=='')));
        $strictIds=mbr_ids_for_names($strict,$country,$sourceNames,false);$broadIds=mbr_ids_for_names($broad,$country,$sourceNames,true);if(count($strictIds)!==0||count($broadIds)!==0)continue;
        $stats['no_unique_identity_examined']++;$oc=(int)($obsCount[$external]??0);if($oc>0)$stats['observed']++;

        $pool=[];foreach($sourceNames as $s)foreach(hmfg_tokens($s) as $t){if(mb_strlen($t,'UTF-8')<2)continue;foreach(array_keys($tokenIndex[$t]??[]) as $lid){$lid=(int)$lid;if(isset($hotels[$lid])&&(int)$hotels[$lid]['country_id']===$country)$pool[$lid]=1;}}
        if(!$pool){$stats['no_anchor_tokens']++;$reasons['no_anex_anchor_token_overlap']=($reasons['no_anex_anchor_token_overlap']??0)+1;continue;}$stats['token_pool']++;
        $rank=hmfg_rank(array_map('intval',array_keys($pool)),$sourceNames,$anchorNames);$best=$rank[0]??null;if(!$best)continue;$stats['ranked']++;
        $second=$rank[1]['score']??0.0;$margin=$best['score']-$second;$lid=(int)$best['id'];$target=$hotels[$lid];
        if(isset($andAccepted[$lid])){$stats['occupied_target']++;$reasons['target_already_accepted_andromeda']=($reasons['target_already_accepted_andromeda']??0)+1;continue;}
        if(!hmab_numeric_parity((string)$best['source'],(string)$best['target'])){$stats['numeric_conflict']++;$reasons['numeric_name_token_conflict']=($reasons['numeric_name_token_conflict']??0)+1;continue;}
        if(!hmlg_qualifiers_ok($sourceNames,$anchorNames[$lid]??[])){$stats['qualifier_conflict']++;$reasons['meaningful_qualifier_conflict']=($reasons['meaningful_qualifier_conflict']??0)+1;continue;}
        $coord=$src;if($obs)$coord+=$obs;$guard=mbr_target_guard($coord,$target);if($guard['coordinate_conflict']){$stats['coordinate_conflict']++;$reasons['coordinate_conflict_gt_5km']=($reasons['coordinate_conflict_gt_5km']??0)+1;continue;}
        $direct=fc_place($sourcePlaces,[(string)$target['region_name'],(string)$target['subregion_name']]);$proof=hmlg_best_geo($learn,$sourcePlaces,hmlg_target_place_keys($target));$strongGeo=$direct||($proof!==null&&$proof['support']>=3&&$proof['confidence']>=0.80);
        if(!$strongGeo){$stats['weak_geo']++;$reasons['provider_bridge_without_strong_geo']=($reasons['provider_bridge_without_strong_geo']??0)+1;continue;}
        $base=['external_id'=>$external,'country_id'=>$country,'observation_count'=>$oc,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'target'=>mbr_row_target($target),'anex_anchor_names'=>$anchorNames[$lid]??[],'best'=>$best,'second_score'=>round($second,6),'margin'=>round($margin,6),'direct_geo'=>(bool)$direct,'learned_geo'=>$proof,'guard'=>$guard];
        if($best['score']>=0.88&&$best['shared']>=2&&$margin>=0.15){$base['tier']='safe';$base['reason']='independent_anex_anchor_strong_fuzzy_plus_geo';$safe[]=$base;$stats['safe']++;continue;}
        if($best['score']>=0.80&&$best['shared']>=2&&$margin>=0.10){$base['tier']='medium';$base['reason']='independent_anex_anchor_fuzzy_needs_more_evidence';$medium[]=$base;$stats['medium']++;continue;}
        if($best['score']<0.80||$best['shared']<2){$stats['weak_score']++;$reasons['weak_provider_bridge_score']=($reasons['weak_provider_bridge_score']??0)+1;}else{$stats['weak_margin']++;$reasons['insufficient_provider_bridge_margin']=($reasons['insufficient_provider_bridge_margin']??0)+1;}
    }
    $sort=static fn($a,$b)=>(int)$b['observation_count']<=>(int)$a['observation_count'] ?: $b['best']['score']<=>$a['best']['score'] ?: $b['margin']<=>$a['margin'] ?: strcmp($a['external_id'],$b['external_id']);usort($safe,$sort);usort($medium,$sort);arsort($reasons);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'historical_operations_replayed'=>false,'training_rows'=>$training,'learned_place_keys'=>count($learn),'anex_anchor_rows'=>$anchorRows,'anex_anchor_locals'=>count($anchorNames),'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'blocked_reasons'=>$reasons,'safe_count'=>count($safe),'medium_count'=>count($medium),'safe'=>$safe,'medium'=>$medium];
}
