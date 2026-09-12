<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_global_cross_provider_rescue.php';

const HMGTR_OPERATION = 'hotel-match-global-tourvisor-rescue-1971-20260911-v1';
const HMGTR_COORD_BLOCK_M = 5000.0;
const HMGTR_COORD_DIRECT_M = 1000.0;
const HMGTR_COORD_TIGHT_M = 250.0;
const HMGTR_COORD_SINGLE_M = 50.0;
const HMGTR_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmgtr_route(array $pair, ?float $distance, bool $place, float $margin): ?string {
    if (!($pair['critical_ok'] ?? false) || (int)($pair['shared'] ?? 0) < 1) return null;
    if ($distance !== null && $distance > HMGTR_COORD_BLOCK_M) return null;
    $shared=(int)$pair['shared'];
    $direct=$place || ($distance !== null && $distance <= HMGTR_COORD_DIRECT_M);
    if (($pair['exact_bag'] ?? false) && $shared >= 2 && $direct) {
        return ($pair['cross_script'] ?? false) ? 'tourvisor_translit_exact_direct_geo' : 'tourvisor_generic_free_exact_direct_geo';
    }
    if (($pair['exact_bag'] ?? false) && $shared >= 3 && $distance !== null && $distance <= HMGTR_COORD_TIGHT_M) {
        return ($pair['cross_script'] ?? false) ? 'tourvisor_translit_exact_tight_coordinate' : 'tourvisor_generic_free_exact_tight_coordinate';
    }
    if (($pair['exact_bag'] ?? false) && $shared === 1 && $place && $distance !== null && $distance <= HMGTR_COORD_SINGLE_M) {
        return 'tourvisor_single_token_ultratight_place';
    }
    if ($direct && $shared >= 3 && (float)$pair['score'] >= 0.88 && $margin >= 0.20 && (int)$pair['token_diff'] <= 2 && (($pair['anchor_ok'] ?? false) || ($pair['ordered'] ?? false))) {
        return 'tourvisor_high_overlap_direct_geo';
    }
    return null;
}

function hmgtr_decide(array $source, int $country, array $index, array $hotels, array $names, array $sameProviderClaims, array $excluded=[]): array {
    [$exactIds,$fuzzyIds]=hmgcr_source_candidate_ids($source['names'],$source['places'],$country,$index);
    $rawIds=array_values(array_unique(array_merge($exactIds,$fuzzyIds)));
    $pool=[];$occupied=0;$excludedCount=0;
    foreach($rawIds as $id){
        $id=(int)$id;
        if(!isset($hotels[$id]) || (int)$hotels[$id]['country_id']!==$country) continue;
        if(isset($sameProviderClaims[$id])){$occupied++;continue;}
        if(isset($excluded[$id])){$excludedCount++;continue;}
        $target=$hotels[$id];$targetPlaces=[(string)$target['region_name'],(string)$target['subregion_name']];
        $pair=hmgcr_best_pair($source['names'],$names[$id]??[(string)$target['name']],$source['places'],$targetPlaces);
        if(!($pair['critical_ok']??false))continue;
        $distance=fc_dist($source['latitude'],$source['longitude'],$target['latitude']??null,$target['longitude']??null);
        $place=hmgcr_place($source['places'],$targetPlaces);
        $pool[]=['id'=>$id,'pair'=>$pair,'distance_m'=>$distance===null?null:round($distance,2),'place_match'=>$place,'target'=>$target];
    }
    if(!$pool){
        $reason=$rawIds ? (($occupied>0 && $occupied+$excludedCount>=count($rawIds))?'all_candidates_protected_or_claimed':'tourvisor_identity_candidate_insufficient') : 'no_tourvisor_identity_candidate';
        return ['bucket'=>'near','reason'=>$reason,'candidate_count'=>count($rawIds),'occupied_count'=>$occupied,'excluded_count'=>$excludedCount];
    }
    $exactPool=array_values(array_filter($pool,static fn($x)=>(bool)($x['pair']['exact_bag']??false)));
    if(count($exactPool)>1)return ['bucket'=>'near','reason'=>'tourvisor_exact_ambiguous','candidate_ids'=>array_map(static fn($x)=>(int)$x['id'],$exactPool)];
    if(count($exactPool)===1)$pool=$exactPool;
    usort($pool,static fn($a,$b)=>$b['pair']['shared']<=>$a['pair']['shared'] ?: $b['pair']['score']<=>$a['pair']['score'] ?: $a['id']<=>$b['id']);
    $best=$pool[0];$second=$pool[1]??null;$margin=$second?((float)$best['pair']['score']-(float)$second['pair']['score']):1.0;
    if(($best['pair']['exact_bag']??false) && $best['distance_m']!==null && (float)$best['distance_m']>HMGTR_COORD_BLOCK_M){
        return ['bucket'=>'hard_conflict','reason'=>'unique_tourvisor_exact_coordinate_conflict_gt_5km','target_local_hotel_id'=>$best['id'],'distance_m'=>$best['distance_m'],'pair'=>$best['pair']];
    }
    $route=hmgtr_route($best['pair'],$best['distance_m']===null?null:(float)$best['distance_m'],(bool)$best['place_match'],$margin);
    if($route===null){
        return ['bucket'=>'near','reason'=>count($pool)>1&&$margin<0.20?'tourvisor_winner_margin_small':'tourvisor_identity_or_geo_insufficient','target_local_hotel_id'=>$best['id'],'score_margin'=>round($margin,6),'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'pair'=>$best['pair']];
    }
    if(count($pool)>1 && $margin<0.20){
        return ['bucket'=>'near','reason'=>'tourvisor_winner_margin_small','target_local_hotel_id'=>$best['id'],'score_margin'=>round($margin,6),'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'pair'=>$best['pair']];
    }
    return ['bucket'=>'prepared','reason'=>$route,'target_local_hotel_id'=>$best['id'],'target_name'=>$best['target']['name'],'target_region'=>$best['target']['region_name'],'target_subregion'=>$best['target']['subregion_name'],'target_category'=>$best['target']['category']===null?null:(int)$best['target']['category'],'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'score_margin'=>round($margin,6),'pair'=>$best['pair']];
}

function hmgtr_review(PDO $db,string $operation=HMGTR_OPERATION): array {
    if($operation!==HMGTR_OPERATION)throw new RuntimeException('HMGTR_OPERATION_SCOPE');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);[$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);$shaCountry=fc_sha_countries($db);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);
        $allSet=[];foreach(array_keys($hotels) as $id)$allSet[(int)$id]=true;$index=hmgcr_build_index($allSet,$hotels,$names);
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $anClaims=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1")->fetchAll(PDO::FETCH_ASSOC) as $r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$mapped=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $latest=[];$andObsCount=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$andObsCount[$id]=($andObsCount[$id]??0)+1;if(!isset($latest[$id]))$latest[$id]=$o;}
        $prepared=[];$near=[];$hard=[];$stats=['andromeda_examined'=>0,'anex_observed_examined'=>0,'anex_staging_examined'=>0,'protected'=>0,'country_unknown'=>0,'prepared'=>0,'prepared_andromeda'=>0,'prepared_anex'=>0,'prepared_live'=>0,'prepared_star_mismatch'=>0,'hard_conflict'=>0,'near'=>0,'route_exact_geo'=>0,'route_exact_tight'=>0,'route_translit'=>0,'route_single'=>0,'route_fuzzy_geo'=>0];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(HMGTR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(HMGTR_CORE8[$country])){$stats['country_unknown']++;continue;}$stats['andromeda_examined']++;
            $source=hmgcr_andromeda_source($r,$obs);$d=hmgtr_decide($source,$country,$index,$hotels,$names,$andClaims,[]);$row=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'live'=>(int)($andObsCount[$external]??0)>0,'observation_count'=>(int)($andObsCount[$external]??0),'source_names'=>$source['names'],'source_places'=>$source['places'],'source_category'=>$source['category']]+$d;
            if($d['bucket']==='prepared' && isset($d['target_category']) && $source['category']!==null && (int)$d['target_category']!==(int)$source['category'])$row['star_mismatch']=true;else$row['star_mismatch']=false;
            if($d['bucket']==='prepared')$prepared[]=$row;elseif($d['bucket']==='hard_conflict')$hard[]=$row;else$near[]=$row;
        }
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;$seen=[];$observations=$db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
        $processAnex=function(int $id,int $country,array $obs,array $stage,bool $observed)use(&$prepared,&$near,&$hard,&$stats,$index,$hotels,$names,$anClaims,$excluded,$manual,$mapped){if(isset($manual[$id])||isset($mapped[$id])){$stats['protected']++;return;}$source=['names'=>array_values(array_unique(array_filter([(string)($obs['hotel_name']??''),(string)($stage['api_name']??''),(string)($stage['xml_name']??''),(string)($stage['xml_alternate_name']??'')],static fn($v)=>trim($v)!==''))),'places'=>array_values(array_unique(array_filter([(string)($stage['api_region']??''),(string)($stage['api_town']??'')],static fn($v)=>trim($v)!==''))),'latitude'=>$stage['latitude']??null,'longitude'=>$stage['longitude']??null];$d=hmgtr_decide($source,$country,$index,$hotels,$names,$anClaims,$excluded[$id]??[]);$row=['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'live'=>$observed,'observation_count'=>(int)($obs['search_count']??0),'source_names'=>$source['names'],'source_places'=>$source['places']]+$d;if($d['bucket']==='prepared')$prepared[]=$row;elseif($d['bucket']==='hard_conflict')$hard[]=$row;else$near[]=$row;};
        foreach($observations as $o){$id=(int)$o['anex_hotel_id'];if(isset($seen[$id]))continue;$seen[$id]=true;$country=(int)$o['country_id'];if(!isset(HMGTR_CORE8[$country]))continue;$stats['anex_observed_examined']++;$processAnex($id,$country,$o,$staging[$id]??[],true);}
        foreach($staging as $id=>$s){$id=(int)$id;if(isset($seen[$id])||isset($manual[$id])||isset($mapped[$id]))continue;$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(HMGTR_CORE8[$country]))continue;$stats['anex_staging_examined']++;$processAnex($id,$country,[],$s,false);}
        $byTarget=[];foreach($prepared as $i=>$r)$byTarget[$r['provider'].':'.(int)$r['target_local_hotel_id']][]=$i;$demote=[];foreach($byTarget as $idxs)if(count($idxs)>1)foreach($idxs as $i)$demote[$i]=true;if($demote){$kept=[];foreach($prepared as $i=>$r){if(isset($demote[$i])){$r['bucket']='near';$r['reason']='same_provider_target_collision';$near[]=$r;}else$kept[]=$r;}$prepared=$kept;}
        foreach($prepared as $r){$stats['prepared']++;if($r['provider']==='andromeda')$stats['prepared_andromeda']++;else$stats['prepared_anex']++;if($r['live'])$stats['prepared_live']++;if($r['star_mismatch']??false)$stats['prepared_star_mismatch']++;$reason=$r['reason'];if(str_contains($reason,'translit'))$stats['route_translit']++;elseif($reason==='tourvisor_single_token_ultratight_place')$stats['route_single']++;elseif($reason==='tourvisor_high_overlap_direct_geo')$stats['route_fuzzy_geo']++;elseif(str_contains($reason,'tight_coordinate'))$stats['route_exact_tight']++;else$stats['route_exact_geo']++;}
        $stats['near']=count($near);$stats['hard_conflict']=count($hard);$reasonCounts=[];$providerCounts=[];$countryCounts=[];foreach(array_merge($prepared,$near,$hard) as $r){$providerCounts[$r['provider']][$r['bucket']]=($providerCounts[$r['provider']][$r['bucket']]??0)+1;$countryCounts[(string)$r['country_id']][$r['bucket']]=($countryCounts[(string)$r['country_id']][$r['bucket']]??0)+1;}foreach(array_merge($near,$hard) as $r)$reasonCounts[$r['reason']]=($reasonCounts[$r['reason']]??0)+1;ksort($reasonCounts);ksort($countryCounts);usort($prepared,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1))?:($b['observation_count']<=>$a['observation_count'])?:strcmp((string)$a['provider'],(string)$b['provider'])?:strcmp((string)$a['external_id'],(string)$b['external_id']));$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'global_tourvisor_current_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'catalog_scope'=>$scope,'tourvisor_targets'=>count($index['targets']),'stats'=>$stats,'provider_counts'=>$providerCounts,'country_counts'=>$countryCounts,'blocked_reasons'=>$reasonCounts,'prepared'=>$prepared,'hard_conflicts'=>$hard,'near'=>$near,'guards'=>['core8_only'=>true,'all_active_tourvisor_targets'=>true,'same_provider_target_claim_blocks'=>true,'pair_exclusions_protected'=>true,'coordinate_conflict_auto_block_m'=>HMGTR_COORD_BLOCK_M,'generic_hotel_resort_spa_not_identity'=>true,'critical_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'stars_not_identity'=>true,'no_no_geo_fuzzy_accept'=>true,'russia_abkhazia_excluded'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded CURRENT review workflow\n");exit(64);}
