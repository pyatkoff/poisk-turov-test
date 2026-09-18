<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const HCBR_OPERATION = 'hotel-match-coordinate-bridge-review-1971-20260911-v1';

function hcbr_coord_source(array $source, ?array $observation): array {
    $merged = $source;
    if ($observation) {
        foreach ($observation as $k => $v) if (!array_key_exists($k, $merged) || $merged[$k] === null || $merged[$k] === '') $merged[$k] = $v;
    }
    return mbr_coord($merged);
}

function hcbr_names_compatible(array $sourceNames, array $targetNames): array {
    $bestScore = 0.0; $bestShared = 0; $strict = false; $broad = false;
    foreach ($sourceNames as $source) {
        $source = trim((string)$source); if ($source === '') continue;
        foreach ($targetNames as $target) {
            $target = trim((string)$target); if ($target === '') continue;
            if (fc_key($source, false, false) !== '' && fc_key($source, false, false) === fc_key($target, false, false)) $strict = true;
            if (fc_key($source, true, true) !== '' && fc_key($source, true, true) === fc_key($target, true, true)) $broad = true;
            [$score,$shared] = mbr_similarity($source,$target);
            if ($score > $bestScore || ($score === $bestScore && $shared > $bestShared)) { $bestScore=$score; $bestShared=$shared; }
        }
    }
    return ['strict'=>$strict,'broad'=>$broad,'score'=>round($bestScore,6),'shared'=>$bestShared];
}

function hcbr_nearest(array $catalogByCountry, int $country, float $lat, float $lon, array $names, array $targetNames): ?array {
    $ranked=[];
    foreach ($catalogByCountry[$country] ?? [] as $id=>$hotel) {
        $distance=fc_dist($lat,$lon,$hotel['latitude']??null,$hotel['longitude']??null);
        if ($distance===null || $distance>5000) continue;
        $ranked[]=['id'=>(int)$id,'distance_m'=>(int)round($distance)];
    }
    usort($ranked,static fn($a,$b)=>$a['distance_m']<=>$b['distance_m'] ?: $a['id']<=>$b['id']);
    if (!$ranked) return null;
    $best=$ranked[0]; $second=$ranked[1]['distance_m']??null; $hotel=$catalogByCountry[$country][$best['id']];
    $compat=hcbr_names_compatible($names,$targetNames[$best['id']]??[$hotel['name']]);
    $best['second_distance_m']=$second;
    $best['distance_margin_m']=$second===null?null:$second-$best['distance_m'];
    $best['name']=$compat;
    $best['target']=mbr_row_target($hotel);
    return $best;
}

function hcbr_safe_coordinate_candidate(array $nearest): bool {
    $d=(int)$nearest['distance_m']; $margin=$nearest['distance_margin_m']; $name=$nearest['name'];
    if ($margin !== null && $margin < 75) return false;
    if ($name['strict']) return $d <= 1000;
    if ($name['broad']) return $d <= 500;
    if ($name['shared'] >= 2 && $name['score'] >= 0.60) return $d <= 250 && ($margin===null || $margin>=150);
    if ($name['shared'] >= 1 && $name['score'] >= 0.45) return $d <= 50 && ($margin===null || $margin>=200);
    return false;
}

function hcbr_review(PDO $db, string $operation=HCBR_OPERATION): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$targetNames] = [[],[]];
    [$allHotels,$allNames] = [[],[]];
    [$allHotels,$allNames] = (function(PDO $db): array {
        [$hotels,$names] = mbr_catalog($db);
        return [$hotels,$names];
    })($db);
    $catalogByCountry=[];
    foreach ($allHotels as $id=>$hotel) {
        if (($hotel['latitude']??null)==='' || ($hotel['longitude']??null)==='') continue;
        $catalogByCountry[(int)$hotel['country_id']][(int)$id]=$hotel;
    }
    $targetNames=$allNames;
    [$anexLocal,$andromedaLocal]=mbr_local_sets($db);
    $shaCountry=fc_sha_countries($db);

    $latest=[];
    foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];if(!isset($latest[$id]))$latest[$id]=$o;}
    $andStats=['pending_examined'=>0,'with_coordinates'=>0,'safe_coordinate'=>0,'missing_third_bridge'=>0,'not_anex_local'=>0,'star_guard'=>0,'nearest_ambiguous'=>0,'country_unknown'=>0];
    $andCandidates=[];
    foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $external=(string)$r['external_hotel_id']; $obs=$latest[$external]??null; $prior=fc_evidence($r['evidence_json']??''); $source=$prior['source']??[]; if(!is_array($source))$source=[];
        $country=(int)($obs['country_id']??0); if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);
        if(!isset(MBR_CORE8[$country])){$andStats['country_unknown']++;continue;} $andStats['pending_examined']++;
        [$lat,$lon]=hcbr_coord_source($source,$obs); if($lat===null||$lon===null)continue; $andStats['with_coordinates']++;
        $names=array_values(array_unique(array_filter([(string)($source['name']??''),(string)($source['lName']??''),(string)($obs['hotel_name']??'')],static fn($v)=>trim($v)!=='')));
        $nearest=hcbr_nearest($catalogByCountry,$country,$lat,$lon,$names,$targetNames); if(!$nearest||!hcbr_safe_coordinate_candidate($nearest)){$andStats['nearest_ambiguous']++;continue;}
        $target=(int)$nearest['id']; $sourceCat=mbr_numeric_category($source); if($sourceCat===null&&$obs)$sourceCat=mbr_numeric_category($obs); $targetCat=$allHotels[$target]['category']===null?null:(int)$allHotels[$target]['category'];
        if($sourceCat!==null&&$targetCat!==null&&$sourceCat!==$targetCat){$andStats['star_guard']++;continue;}
        $andStats['safe_coordinate']++; if(!isset($anexLocal[$target])){$andStats['not_anex_local']++;continue;} $andStats['missing_third_bridge']++;
        $andCandidates[]=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'source_names'=>$names,'latitude'=>$lat,'longitude'=>$lon,'target_local_hotel_id'=>$target,'target_name'=>$allHotels[$target]['name'],'distance_m'=>$nearest['distance_m'],'distance_margin_m'=>$nearest['distance_margin_m'],'name'=>$nearest['name'],'evidence'=>'coordinate_first_to_existing_anex_tourvisor_local'];
    }

    $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
    $existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
    $excluded=[]; foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
    $staging=[]; foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;
    $observed=[]; foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $o)if(!isset($observed[(int)$o['anex_hotel_id']]))$observed[(int)$o['anex_hotel_id']]=$o;
    $anStats=['examined'=>0,'with_coordinates'=>0,'safe_coordinate'=>0,'missing_third_bridge'=>0,'not_andromeda_local'=>0,'protected'=>0,'excluded'=>0,'nearest_ambiguous'=>0];
    $anCandidates=[]; $ids=array_unique(array_merge(array_keys($observed),array_keys($staging)));
    foreach($ids as $id){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id])){$anStats['protected']++;continue;}$s=$staging[$id]??[];$o=$observed[$id]??null;$country=(int)($o['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(MBR_CORE8[$country]))continue;$anStats['examined']++;
        $lat=fc_num($s['latitude']??null);$lon=fc_num($s['longitude']??null);if($lat===null||$lon===null)continue;$anStats['with_coordinates']++;
        $names=array_values(array_unique(array_filter([(string)($o['hotel_name']??''),(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??'')],static fn($v)=>trim($v)!=='')));
        $nearest=hcbr_nearest($catalogByCountry,$country,$lat,$lon,$names,$targetNames);if(!$nearest||!hcbr_safe_coordinate_candidate($nearest)){$anStats['nearest_ambiguous']++;continue;}$target=(int)$nearest['id'];$anStats['safe_coordinate']++;
        if(isset($excluded[$id][$target])){$anStats['excluded']++;continue;}if(!isset($andromedaLocal[$target])){$anStats['not_andromeda_local']++;continue;}$anStats['missing_third_bridge']++;
        $anCandidates[]=['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'observed'=>$o!==null,'search_count'=>(int)($o['search_count']??0),'source_names'=>$names,'latitude'=>$lat,'longitude'=>$lon,'target_local_hotel_id'=>$target,'target_name'=>$allHotels[$target]['name'],'distance_m'=>$nearest['distance_m'],'distance_margin_m'=>$nearest['distance_margin_m'],'name'=>$nearest['name'],'evidence'=>'coordinate_first_to_existing_andromeda_tourvisor_local'];
    }
    usort($andCandidates,static fn($a,$b)=>$a['country_id']<=>$b['country_id'] ?: strcmp($a['external_id'],$b['external_id']));
    usort($anCandidates,static fn($a,$b)=>($b['observed']<=>$a['observed']) ?: ($b['search_count']<=>$a['search_count']) ?: $a['external_id']<=>$b['external_id']);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'coverage'=>fc_coverage($db),'andromeda'=>$andStats,'anex'=>$anStats,'prepared_count'=>count($andCandidates)+count($anCandidates),'prepared'=>array_merge($anCandidates,$andCandidates)];
}

if (!defined('FC_LIBRARY_ONLY')) {
    fwrite(STDERR,"library_only\n"); exit(64);
}
