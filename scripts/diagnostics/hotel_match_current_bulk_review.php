<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_full_catalog_reconcile.php';

const MBR_OPERATION = 'hotel-match-current-bulk-review-1971-20260911-v1';
const MBR_POLICY = 'owner_exact_and_strong_20260908';
const MBR_CORE8 = [1=>'Египет',2=>'Таиланд',4=>'Турция',8=>'Мальдивы',9=>'ОАЭ',10=>'Куба',12=>'Шри-Ланка',16=>'Вьетнам'];

function mbr_numeric_category(array $source): ?int {
    foreach (['category','star','stars','starName','star_name'] as $key) {
        if (!array_key_exists($key, $source)) continue;
        $value = trim((string)$source[$key]);
        if (preg_match('/^([1-5])(?:\s*(?:\*|★|stars?))?$/iu', $value, $m)) return (int)$m[1];
    }
    return null;
}
function mbr_similarity($a, $b): array {
    $left = array_fill_keys(array_unique(fc_tokens($a, true)), true);
    $right = array_fill_keys(array_unique(fc_tokens($b, true)), true);
    $shared = count(array_intersect_key($left, $right));
    $union = count($left + $right);
    return [$union ? $shared / $union : 0.0, $shared];
}
function mbr_coord(array $source): array {
    foreach ([['latitude','longitude'],['lat','lng'],['lat','lon'],['hotelLatitude','hotelLongitude']] as $keys) {
        if (!array_key_exists($keys[0], $source) || !array_key_exists($keys[1], $source)) continue;
        $lat = fc_num($source[$keys[0]]); $lon = fc_num($source[$keys[1]]);
        if ($lat !== null && $lon !== null && abs($lat) <= 90 && abs($lon) <= 180) return [$lat,$lon];
    }
    return [null,null];
}
function mbr_catalog(PDO $db): array {
    $countries = [];
    $q = $db->query('SELECT id,name FROM catalog_countries WHERE id IN (1,2,4,8,9,10,12,16) ORDER BY id');
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)$row['id'];
        if ((MBR_CORE8[$id] ?? null) !== $row['name']) throw new RuntimeException('country_contract_changed');
        $countries[$id] = true;
    }
    if (count($countries) !== 8) throw new RuntimeException('country_contract_changed');
    $sql = 'SELECT h.id,h.country_id,h.country_name,h.name,h.normalized_name,h.region_name,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY h.country_id,h.id';
    $hotels=[]; $names=[]; $strict=[]; $broad=[]; $places=[]; $count=0;
    foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $h) {
        if (++$count > 50000) throw new RuntimeException('hotel_scope_limit');
        $id=(int)$h['id']; $country=(int)$h['country_id']; $hotels[$id]=$h; $names[$id]=[$h['name']];
        foreach ([$h['name'],$h['normalized_name']] as $name) {
            $key=fc_key($name,false,false); if ($key!=='') $strict[$country][$key][$id]=true;
            $key=fc_key($name,true,true); if ($key!=='') $broad[$country][$key][$id]=true;
        }
        foreach ([$h['region_name'],$h['subregion_name']] as $place) {
            $key=fc_norm($place); if ($key!=='') $places[$country][$key][$id]=true;
        }
    }
    $sql = 'SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY h.country_id,a.hotel_id,a.id';
    $aliases=0;
    foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $a) {
        if (++$aliases > 100000) throw new RuntimeException('alias_scope_limit');
        $id=(int)$a['hotel_id']; $country=(int)$a['country_id'];
        foreach ([$a['alias'],$a['normalized_alias']] as $name) {
            if ((string)$name==='') continue; $names[$id][]=$name;
            $key=fc_key($name,false,false); if ($key!=='') $strict[$country][$key][$id]=true;
            $key=fc_key($name,true,true); if ($key!=='') $broad[$country][$key][$id]=true;
        }
    }
    return [$hotels,$names,$strict,$broad,$places,['countries'=>8,'hotels'=>$count,'aliases'=>$aliases]];
}
function mbr_ids_for_names(array $index, int $country, array $sourceNames, bool $broad): array {
    $ids=[];
    foreach ($sourceNames as $name) {
        $key=fc_key($name,$broad,$broad); if ($key==='') continue;
        foreach (array_keys($index[$country][$key] ?? []) as $id) $ids[(int)$id]=true;
    }
    $out=array_map('intval',array_keys($ids)); sort($out,SORT_NUMERIC); return $out;
}
function mbr_place_pool(array $places, int $country, array $sourcePlaces): array {
    $ids=[];
    foreach ($sourcePlaces as $place) {
        $key=fc_norm($place); if ($key==='') continue;
        foreach (array_keys($places[$country][$key] ?? []) as $id) $ids[(int)$id]=true;
    }
    $out=array_map('intval',array_keys($ids)); sort($out,SORT_NUMERIC); return $out;
}
function mbr_rank(array $pool, array $sourceNames, array $names): array {
    $ranked=[];
    foreach ($pool as $id) {
        $bestScore=0.0; $bestShared=0; $bestSource=''; $bestTarget='';
        foreach ($sourceNames as $source) foreach ($names[$id] ?? [] as $target) {
            [$score,$shared]=mbr_similarity($source,$target);
            if ($score>$bestScore || ($score===$bestScore && $shared>$bestShared)) {
                $bestScore=$score; $bestShared=$shared; $bestSource=(string)$source; $bestTarget=(string)$target;
            }
        }
        $ranked[]=['id'=>(int)$id,'score'=>round($bestScore,6),'shared'=>$bestShared,'source_name'=>$bestSource,'target_name'=>$bestTarget];
    }
    usort($ranked,static fn($a,$b)=>$b['score']<=>$a['score'] ?: $b['shared']<=>$a['shared'] ?: $a['id']<=>$b['id']);
    return $ranked;
}
function mbr_target_guard(array $source, array $target): array {
    [$lat,$lon]=mbr_coord($source);
    $distance=fc_dist($lat,$lon,$target['latitude']??null,$target['longitude']??null);
    return ['distance_m'=>$distance===null?null:(int)round($distance),'coordinate_conflict'=>$distance!==null && $distance>5000];
}
function mbr_row_target(array $target): array {
    return ['local_hotel_id'=>(int)$target['id'],'name'=>$target['name'],'country_id'=>(int)$target['country_id'],'region'=>$target['region_name'],'subregion'=>$target['subregion_name'],'category'=>$target['category']===null?null:(int)$target['category']];
}
function mbr_review_anex(array $source, int $id, int $country, array $hotels, array $names, array $strict, array $broad, array $places): array {
    $sourceNames=array_values(array_unique(array_filter(array_map('strval',$source['names'] ?? []),static fn($v)=>trim($v)!=='')));
    $sourcePlaces=array_values(array_unique(array_filter(array_map('strval',$source['places'] ?? []),static fn($v)=>trim($v)!=='')));
    $base=['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'observed'=>(bool)($source['observed']??false),'search_count'=>(int)($source['search_count']??0),'last_seen_utc'=>$source['last_seen_utc']??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces];
    $strictIds=mbr_ids_for_names($strict,$country,$sourceNames,false);
    if (count($strictIds)===1) {
        $target=$hotels[$strictIds[0]]; $guard=mbr_target_guard($source,$target);
        if ($guard['coordinate_conflict']) return $base+['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_gt_5km','guard'=>$guard,'target'=>mbr_row_target($target)];
        return $base+['bucket'=>'auto_accept','reason'=>'unique_strict_name_country','guard'=>$guard,'target'=>mbr_row_target($target)];
    }
    if (count($strictIds)>1) return $base+['bucket'=>'needs_extra_evidence','reason'=>'strict_name_ambiguous','candidate_ids'=>$strictIds];
    $broadIds=mbr_ids_for_names($broad,$country,$sourceNames,true);
    if (count($broadIds)===1) {
        $target=$hotels[$broadIds[0]]; $guard=mbr_target_guard($source,$target);
        if ($guard['coordinate_conflict']) return $base+['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_gt_5km','guard'=>$guard,'target'=>mbr_row_target($target)];
        $place=fc_place($sourcePlaces,[$target['region_name'],$target['subregion_name']]);
        if (($guard['distance_m']!==null && $guard['distance_m']<=1000) || $place) return $base+['bucket'=>'auto_accept','reason'=>'unique_generic_name_plus_geo','guard'=>$guard,'target'=>mbr_row_target($target)];
    }
    $pool=mbr_place_pool($places,$country,$sourcePlaces);
    if ($pool) {
        $ranked=mbr_rank($pool,$sourceNames,$names); $best=$ranked[0]??null; $second=$ranked[1]['score']??0.0;
        if ($best) {
            $target=$hotels[$best['id']]; $guard=mbr_target_guard($source,$target); $margin=$best['score']-$second;
            if ($guard['coordinate_conflict']) return $base+['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_gt_5km','guard'=>$guard,'best'=>$best,'margin'=>round($margin,6),'target'=>mbr_row_target($target)];
            if ($best['score']>=0.90 && $best['shared']>=2 && $margin>=0.20) return $base+['bucket'=>'auto_accept','reason'=>'strong_fuzzy_geo_large_margin','guard'=>$guard,'best'=>$best,'margin'=>round($margin,6),'target'=>mbr_row_target($target)];
            if ($best['score']>=0.82 && $best['shared']>=2 && $margin>=0.15) return $base+['bucket'=>'needs_extra_evidence','reason'=>'medium_fuzzy_geo','guard'=>$guard,'best'=>$best,'margin'=>round($margin,6),'target'=>mbr_row_target($target)];
        }
    }
    return $base+['bucket'=>'needs_extra_evidence','reason'=>'tourvisor_anex_operator_link_hotelcode_needed','candidate_ids'=>array_slice($broadIds,0,10)];
}
function mbr_review_andromeda(array $row, ?array $observation, int $country, array $hotels, array $names, array $strict, array $places): array {
    $prior=fc_evidence($row['evidence_json']??''); $source=$prior['source']??[]; if(!is_array($source))$source=[]; $geo=$prior['geography']??[]; if(!is_array($geo))$geo=[];
    $sourceNames=array_values(array_unique(array_filter([(string)($source['name']??''),(string)($source['lName']??''),(string)($observation['hotel_name']??'')],static fn($v)=>trim($v)!=='')));
    $sourcePlaces=array_values(array_unique(array_filter([(string)($source['town']??''),(string)($geo['town']??''),(string)($geo['parent']??''),(string)($observation['region_name']??'')],static fn($v)=>trim($v)!=='')));
    $base=['provider'=>'andromeda','external_id'=>(string)$row['external_hotel_id'],'country_id'=>$country,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'catalog_sha256'=>$row['catalog_sha256']??null];
    $category=mbr_numeric_category($source); if ($category===null && $observation) $category=mbr_numeric_category($observation);
    $coordSource=$source; if ($observation) $coordSource += $observation;
    $strictIds=mbr_ids_for_names($strict,$country,$sourceNames,false);
    if (count($strictIds)===1) {
        $target=$hotels[$strictIds[0]]; $guard=mbr_target_guard($coordSource,$target); $targetCat=$target['category']===null?null:(int)$target['category'];
        if ($guard['coordinate_conflict']) return $base+['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_gt_5km','guard'=>$guard,'target'=>mbr_row_target($target)];
        if ($category!==null && $targetCat!==null && $category!==$targetCat) return $base+['bucket'=>'needs_extra_evidence','reason'=>'numeric_star_guard_mismatch','source_category'=>$category,'target'=>mbr_row_target($target)];
        return $base+['bucket'=>'auto_accept','reason'=>'unique_strict_name_country','guard'=>$guard,'source_category'=>$category,'target'=>mbr_row_target($target)];
    }
    if (count($strictIds)>1) return $base+['bucket'=>'needs_extra_evidence','reason'=>'strict_name_ambiguous','candidate_ids'=>$strictIds];
    $pool=[]; foreach(array_map('intval',$prior['candidate_ids']??[]) as $id) if(isset($hotels[$id])&&(int)$hotels[$id]['country_id']===$country)$pool[$id]=true;
    if(!$pool) foreach(mbr_place_pool($places,$country,$sourcePlaces) as $id)$pool[$id]=true;
    $ranked=mbr_rank(array_map('intval',array_keys($pool)),$sourceNames,$names); $best=$ranked[0]??null; $second=$ranked[1]['score']??0.0;
    if ($best) {
        $target=$hotels[$best['id']]; $guard=mbr_target_guard($coordSource,$target); $margin=$best['score']-$second; $targetCat=$target['category']===null?null:(int)$target['category'];
        if ($guard['coordinate_conflict']) return $base+['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_gt_5km','guard'=>$guard,'best'=>$best,'margin'=>round($margin,6),'target'=>mbr_row_target($target)];
        if ($category!==null && $targetCat!==null && $category!==$targetCat) return $base+['bucket'=>'needs_extra_evidence','reason'=>'numeric_star_guard_mismatch','source_category'=>$category,'best'=>$best,'margin'=>round($margin,6),'target'=>mbr_row_target($target)];
        if ($best['score']>=0.90 && $best['shared']>=2 && $margin>=0.20) return $base+['bucket'=>'auto_accept','reason'=>'strong_fuzzy_geo_large_margin','guard'=>$guard,'source_category'=>$category,'best'=>$best,'margin'=>round($margin,6),'target'=>mbr_row_target($target)];
        if ($best['score']>=0.82 && $best['shared']>=2 && $margin>=0.15) return $base+['bucket'=>'needs_extra_evidence','reason'=>'medium_fuzzy_geo','guard'=>$guard,'source_category'=>$category,'best'=>$best,'margin'=>round($margin,6),'target'=>mbr_row_target($target)];
    }
    return $base+['bucket'=>'needs_extra_evidence','reason'=>'cross_provider_or_supplier_evidence_needed','candidate_ids'=>array_slice(array_map('intval',array_keys($pool)),0,20)];
}
function mbr_local_sets(PDO $db): array {
    $anex=[];
    $sql="SELECT m.catalog_hotel_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".MBR_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id)$anex[(int)$id]=true;
    $sql="SELECT d.catalog_hotel_id FROM anex_hotel_decisions d WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id)$anex[(int)$id]=true;
    $andromeda=[];
    foreach($db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id)$andromeda[(int)$id]=true;
    return [$anex,$andromeda];
}
function mbr_review(PDO $db, string $operation): array {
    if ($operation!==MBR_OPERATION) throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $coverage=fc_coverage($db); [$hotels,$names,$strict,$broad,$places,$catalogScope]=mbr_catalog($db); $shaCountry=fc_sha_countries($db);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[]; foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[]; foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;
        $buckets=['auto_accept'=>[],'needs_extra_evidence'=>[],'hard_conflict'=>[],'manual_last'=>[]]; $stats=['anex'=>['observed_examined'=>0,'staging_examined'=>0,'protected'=>0],'andromeda'=>['pending_examined'=>0,'country_unknown'=>0],'protected_pairs'=>count($excluded)]; $seenAnex=[];
        $observations=$db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
        foreach($observations as $o){$id=(int)$o['anex_hotel_id'];$country=(int)$o['country_id'];if(!isset(MBR_CORE8[$country]))continue;$stats['anex']['observed_examined']++;$seenAnex[$id]=true;if(isset($manual[$id])||isset($existing[$id])){$stats['anex']['protected']++;continue;}$s=$staging[$id]??[];$source=['observed'=>true,'search_count'=>(int)$o['search_count'],'last_seen_utc'=>$o['last_seen_utc'],'names'=>[$o['hotel_name'],$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];$row=mbr_review_anex($source,$id,$country,$hotels,$names,$strict,$broad,$places);$target=(int)($row['target']['local_hotel_id']??0);if($target&&isset($excluded[$id][$target])){$row['bucket']='hard_conflict';$row['reason']='pair_exclusion_protected';}$buckets[$row['bucket']][]=$row;}
        foreach($staging as $id=>$s){if(isset($seenAnex[$id])||isset($manual[$id])||isset($existing[$id]))continue;$country=fc_country($s['api_country']??'');if(!$country||!isset(MBR_CORE8[$country]))continue;$stats['anex']['staging_examined']++;$source=['observed'=>false,'search_count'=>0,'names'=>[$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];$row=mbr_review_anex($source,(int)$id,$country,$hotels,$names,$strict,$broad,$places);$target=(int)($row['target']['local_hotel_id']??0);if($target&&isset($excluded[(int)$id][$target])){$row['bucket']='hard_conflict';$row['reason']='pair_exclusion_protected';}$buckets[$row['bucket']][]=$row;}
        $latest=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];if(!isset($latest[$id]))$latest[$id]=$o;}
        $pending=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach($pending as $r){$external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country])){$stats['andromeda']['country_unknown']++;continue;}$stats['andromeda']['pending_examined']++;$row=mbr_review_andromeda($r,$obs,$country,$hotels,$names,$strict,$places);$buckets[$row['bucket']][]=$row;}
        [$anexLocal,$andromedaLocal]=mbr_local_sets($db);$third=[];$anexOnly=array_diff_key($anexLocal,$andromedaLocal);$andromedaOnly=array_diff_key($andromedaLocal,$anexLocal);
        foreach([['anex_tv_only',$anexOnly],['andromeda_tv_only',$andromedaOnly]] as [$type,$set])foreach(array_keys($set) as $id){$id=(int)$id;if(!isset($hotels[$id]))continue;$third[]=['type'=>$type,'local_hotel_id'=>$id,'country_id'=>(int)$hotels[$id]['country_id'],'name'=>$hotels[$id]['name'],'region'=>$hotels[$id]['region_name'],'subregion'=>$hotels[$id]['subregion_name'],'next_evidence'=>$type==='anex_tv_only'?'resolve_andromeda_to_existing_local':'resolve_anex_via_tourvisor_operator_hotelcode_to_existing_local'];}
        usort($third,static fn($a,$b)=>$a['country_id']<=>$b['country_id'] ?: $a['local_hotel_id']<=>$b['local_hotel_id'] ?: strcmp($a['type'],$b['type']));
        $seed=[];foreach(array_merge($buckets['needs_extra_evidence'],$buckets['hard_conflict']) as $row)if(($row['provider']??'')==='anex')$seed[]=['anex_hotel_id'=>(int)$row['external_id'],'search_count'=>(int)($row['search_count']??0),'reason'=>$row['reason'],'next'=>'Tourvisor ANEX-only result -> visible operator link -> ANEX page/media hotelCode -> country/name/geo/coordinate verification'];usort($seed,static fn($a,$b)=>$b['search_count']<=>$a['search_count'] ?: $a['anex_hotel_id']<=>$b['anex_hotel_id']);
        $counts=[];foreach($buckets as $key=>$rows)$counts[$key]=count($rows);$counts['third_link_gaps']=count($third);$counts['tourvisor_anex_seeds']=count($seed);$counts['anex_tv_only_core8']=count(array_filter($third,static fn($r)=>$r['type']==='anex_tv_only'));$counts['andromeda_tv_only_core8']=count(array_filter($third,static fn($r)=>$r['type']==='andromeda_tv_only'));
        $db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_consistent_snapshot_read_only','database_writes'=>0,'supplier_calls'=>0,'catalog_scope'=>$catalogScope,'coverage'=>$coverage,'examined'=>$stats,'counts'=>$counts,'buckets'=>$buckets,'third_link_gaps'=>$third,'tourvisor_anex_seeds'=>$seed,'guards'=>['core8_only'=>true,'russia_abkhazia_auto_import'=>false,'manual_decisions_overwritten'=>false,'pair_exclusions_overwritten'=>false,'existing_mappings_overwritten'=>false,'coordinate_conflict_auto_block_m'=>5000,'numeric_star_is_guard_not_identity'=>true,'generic_identity_tokens'=>['HOTEL','RESORT','SPA'],'significant_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH']]];
    } catch(Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        return ['status'=>'failed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'reason'=>in_array($e->getMessage(),['country_contract_changed','hotel_scope_limit','alias_scope_limit','operation_scope'],true)?$e->getMessage():'runtime_failure'];
    }
}
