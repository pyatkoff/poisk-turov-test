<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_translit_exact_review.php';

const HCAR_OPERATION = 'hotel-match-coordinate-anchor-review-1971-20260911-v1';

function hcar_critical(string $name): array {
    $critical = ['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1];
    $out=[];
    foreach (pcbr_identity_tokens(htxr_latin($name)) as $token) if (isset($critical[$token])) $out[$token]=true;
    $keys=array_keys($out); sort($keys,SORT_STRING); return $keys;
}
function hcar_ordered_contains(array $small,array $large): bool {
    if(!$small || count($small)>count($large)) return false;
    $i=0;$need=count($small);
    foreach($large as $token) if($i<$need && $token===$small[$i]) $i++;
    return $i===$need;
}
function hcar_name_evidence(array $sourceNames,array $targetNames,bool $bridge): array {
    $best=['safe'=>false,'shared'=>0,'score'=>0.0,'ordered'=>false,'source'=>'','target'=>'','critical_ok'=>false];
    foreach($sourceNames as $source){$source=trim((string)$source);if($source==='')continue;$s=pcbr_identity_tokens(htxr_latin($source));if(!$s)continue;
        foreach($targetNames as $target){$target=trim((string)$target);if($target==='')continue;$t=pcbr_identity_tokens(htxr_latin($target));if(!$t)continue;
            $critical=hcar_critical($source)===hcar_critical($target);
            $ls=array_values(array_unique($s));$lt=array_values(array_unique($t));$shared=count(array_intersect($ls,$lt));$union=count(array_unique(array_merge($ls,$lt)));$score=$union?($shared/$union):0.0;
            $ordered=hcar_ordered_contains($s,$t)||hcar_ordered_contains($t,$s);
            $safe=$critical && ($bridge || ($shared>=2 && ($ordered || $score>=0.50)));
            $candidate=['safe'=>$safe,'shared'=>$shared,'score'=>round($score,6),'ordered'=>$ordered,'source'=>$source,'target'=>$target,'critical_ok'=>$critical,'source_tokens'=>$s,'target_tokens'=>$t];
            if((int)$candidate['safe']>(int)$best['safe'] || ((bool)$candidate['safe']===(bool)$best['safe'] && ($candidate['shared']>$best['shared'] || ($candidate['shared']===$best['shared'] && $candidate['score']>$best['score']))))$best=$candidate;
        }
    }
    return $best;
}
function hcar_coordinate_candidate(array $sourceCoord,array $sourceNames,int $country,array $hotels,array $names,array $oppositeLocal,array $excluded=[]): array {
    [$lat,$lon]=mbr_coord($sourceCoord);
    if($lat===null||$lon===null)return ['candidate'=>null,'reason'=>'source_coordinates_missing','nearby'=>0];
    $near=[];
    foreach($hotels as $id=>$hotel){if((int)$hotel['country_id']!==$country||isset($excluded[(int)$id]))continue;$d=fc_dist($lat,$lon,$hotel['latitude']??null,$hotel['longitude']??null);if($d!==null&&$d<=500)$near[]=['id'=>(int)$id,'distance_m'=>$d];}
    if(!$near)return ['candidate'=>null,'reason'=>'no_catalog_within_500m','nearby'=>0];
    usort($near,static fn($a,$b)=>$a['distance_m']<=>$b['distance_m'] ?: $a['id']<=>$b['id']);
    $best=$near[0];$id=$best['id'];$bridge=isset($oppositeLocal[$id]);$limit=$bridge?250.0:100.0;
    if($best['distance_m']>$limit)return ['candidate'=>null,'reason'=>'nearest_too_far','nearby'=>count($near),'nearest_m'=>round($best['distance_m'],2)];
    $competitors=array_values(array_filter($near,static fn($x)=>$x['id']!==$id&&$x['distance_m']<=250));
    if($competitors && !$bridge)return ['candidate'=>null,'reason'=>'coordinate_cluster_ambiguous','nearby'=>count($near),'nearest_m'=>round($best['distance_m'],2),'competitors_250m'=>count($competitors)];
    $name=hcar_name_evidence($sourceNames,$names[$id]??[$hotels[$id]['name']],$bridge);
    if(!$name['safe'])return ['candidate'=>null,'reason'=>'name_or_qualifier_guard','nearby'=>count($near),'nearest_m'=>round($best['distance_m'],2),'bridge'=>$bridge,'name'=>$name];
    $second=$near[1]['distance_m']??null;
    return ['candidate'=>[
        'target_local_hotel_id'=>$id,'target_name'=>$hotels[$id]['name'],'target_region'=>$hotels[$id]['region_name'],'target_subregion'=>$hotels[$id]['subregion_name'],
        'target_category'=>$hotels[$id]['category']===null?null:(int)$hotels[$id]['category'],'distance_m'=>round($best['distance_m'],2),'second_distance_m'=>$second===null?null:round($second,2),
        'competitors_250m'=>count($competitors),'existing_opposite_provider_bridge'=>$bridge,'name'=>$name,'rule'=>$bridge?'coordinate_anchor_plus_existing_opposite_provider':'unique_coordinate_anchor_plus_name_alias_tokens'
    ],'reason'=>'safe','nearby'=>count($near)];
}
function hcar_review(PDO $db,string $operation=HCAR_OPERATION): array {
    if($operation!==HCAR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);$shaCountry=fc_sha_countries($db);[$latest,$counts,$lastSeen]=mlp_observations($db);
        $stats=['andromeda_examined'=>0,'anex_examined'=>0,'live_examined'=>0,'with_coordinates'=>0,'prepared'=>0,'prepared_andromeda'=>0,'prepared_anex'=>0,'live_prepared'=>0,'bridge_prepared'=>0,'category_mismatch_prepared'=>0,'source_coordinates_missing'=>0,'no_catalog_within_500m'=>0,'nearest_too_far'=>0,'coordinate_cluster_ambiguous'=>0,'name_or_qualifier_guard'=>0];$prepared=[];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$obsCount=(int)($counts[$external]??0);$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;$stats['andromeda_examined']++;if($obsCount>0)$stats['live_examined']++;
            [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($r,$obs);[$clat,$clon]=mbr_coord($coordSource);if($clat!==null&&$clon!==null)$stats['with_coordinates']++;
            $found=hcar_coordinate_candidate($coordSource,$sourceNames,$country,$hotels,$names,$anexLocal);if($found['candidate']===null){$reason=$found['reason'];if(isset($stats[$reason]))$stats[$reason]++;continue;}
            $c=$found['candidate'];$mismatch=$sourceCategory!==null&&$c['target_category']!==null&&$sourceCategory!==$c['target_category'];$prepared[]=array_merge(['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'observation_count'=>$obsCount,'last_seen_utc'=>$lastSeen[$external]??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'category_mismatch'=>$mismatch],$c);$stats['prepared_andromeda']++;if($obsCount>0)$stats['live_prepared']++;if($c['existing_opposite_provider_bridge'])$stats['bridge_prepared']++;if($mismatch)$stats['category_mismatch_prepared']++;
        }
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;$observed=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(int)$o['anex_hotel_id'];if(!isset($observed[$id]))$observed[$id]=$o;}
        $ids=array_values(array_unique(array_merge(array_keys($observed),array_keys($staging))));sort($ids,SORT_NUMERIC);
        foreach($ids as $id){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id]))continue;$s=$staging[$id]??[];$o=$observed[$id]??null;$country=(int)($o['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(MBR_CORE8[$country]))continue;$stats['anex_examined']++;$obsCount=(int)($o['search_count']??0);if($obsCount>0)$stats['live_examined']++;
            $sourceNames=array_values(array_unique(array_filter([(string)($o['hotel_name']??''),(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??'')],static fn($v)=>trim($v)!=='')));$sourcePlaces=array_values(array_unique(array_filter([(string)($s['api_region']??''),(string)($s['api_town']??''),(string)($o['region_name']??'')],static fn($v)=>trim($v)!=='')));$coordSource=array_merge($s,$o??[]);[$clat,$clon]=mbr_coord($coordSource);if($clat!==null&&$clon!==null)$stats['with_coordinates']++;$sourceCategory=mbr_numeric_category($s);if($sourceCategory===null&&$o)$sourceCategory=mbr_numeric_category($o);
            $found=hcar_coordinate_candidate($coordSource,$sourceNames,$country,$hotels,$names,$andromedaLocal,$excluded[$id]??[]);if($found['candidate']===null){$reason=$found['reason'];if(isset($stats[$reason]))$stats[$reason]++;continue;}
            $c=$found['candidate'];$mismatch=$sourceCategory!==null&&$c['target_category']!==null&&$sourceCategory!==$c['target_category'];$prepared[]=array_merge(['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'observation_count'=>$obsCount,'last_seen_utc'=>$o['last_seen_utc']??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'category_mismatch'=>$mismatch],$c);$stats['prepared_anex']++;if($obsCount>0)$stats['live_prepared']++;if($c['existing_opposite_provider_bridge'])$stats['bridge_prepared']++;if($mismatch)$stats['category_mismatch_prepared']++;
        }
        $stats['prepared']=count($prepared);usort($prepared,static fn($a,$b)=>(int)$b['observation_count']<=>(int)$a['observation_count'] ?: (int)$b['existing_opposite_provider_bridge']<=>(int)$a['existing_opposite_provider_bridge'] ?: $a['distance_m']<=>$b['distance_m'] ?: strcmp((string)$a['provider'],(string)$b['provider']) ?: strcmp((string)$a['external_id'],(string)$b['external_id']));$coverage=fc_coverage($db);$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'catalog_scope'=>$scope,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
