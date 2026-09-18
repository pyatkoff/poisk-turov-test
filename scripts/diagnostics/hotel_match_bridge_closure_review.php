<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_pending_candidate_bridge_review.php';
require_once __DIR__ . '/hotel_match_live_priority_review.php';

const HBCR_OPERATION = 'hotel-match-bridge-closure-review-1971-20260911-v1';

function hbcr_candidate(array $sourceNames,array $sourcePlaces,array $coordSource,int $country,array $providerLocal,array $hotels,array $names,array $places,array $excluded=[]): ?array {
    $placeIds=mbr_place_pool($places,$country,$sourcePlaces);
    if(!$placeIds)return null;
    $matches=[];
    foreach($placeIds as $id){
        $id=(int)$id;
        if(!isset($providerLocal[$id])||!isset($hotels[$id])||isset($excluded[$id]))continue;
        $h=$hotels[$id];
        if(!fc_place($sourcePlaces,[(string)$h['region_name'],(string)$h['subregion_name']]))continue;
        $pair=pcbr_strict_pair($sourceNames,$names[$id]??[(string)$h['name']],(string)$h['region_name']);
        if($pair===null)continue;
        $guard=mbr_target_guard($coordSource,$h);
        if($guard['coordinate_conflict'])continue;
        $matches[$id]=['target_local_hotel_id'=>$id,'target_name'=>$h['name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'strict_identity'=>$pair,'distance_m'=>$guard['distance_m']??null];
    }
    if(count($matches)!==1)return null;
    return reset($matches) ?: null;
}

function hbcr_review(PDO $db,string $operation=HBCR_OPERATION,int $maxRounds=8): array {
    if($operation!==HBCR_OPERATION)throw new RuntimeException('operation_scope');
    if($maxRounds<1||$maxRounds>16)throw new RuntimeException('round_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);
        [$anexLocal,$andromedaLocal]=mbr_local_sets($db);
        $initial=['anex'=>count($anexLocal),'andromeda'=>count($andromedaLocal)];
        $shaCountry=fc_sha_countries($db);
        [$latest,$counts,$lastSeen]=mlp_observations($db);

        $andPending=[];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;
            [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($r,$obs);
            $andPending[$external]=['country'=>$country,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'coord_source'=>$coordSource,'observations'=>(int)($counts[$external]??0),'last_seen_utc'=>$lastSeen[$external]??null];
        }

        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;
        $anPending=[];$seen=[];
        foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $o){
            $id=(int)$o['anex_hotel_id'];$country=(int)$o['country_id'];if(!isset(MBR_CORE8[$country])||isset($manual[$id])||isset($existing[$id])||isset($seen[$id]))continue;$seen[$id]=true;$s=$staging[$id]??[];
            $anPending[$id]=['country'=>$country,'source_names'=>array_values(array_unique(array_filter([(string)$o['hotel_name'],(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??'')],static fn($v)=>trim($v)!==''))),'source_places'=>array_values(array_unique(array_filter([(string)($s['api_region']??''),(string)($s['api_town']??'')],static fn($v)=>trim($v)!==''))),'coord_source'=>$s,'observations'=>(int)$o['search_count'],'last_seen_utc'=>$o['last_seen_utc']];
        }
        foreach($staging as $id=>$s){if(isset($manual[$id])||isset($existing[$id])||isset($seen[$id]))continue;$country=fc_country($s['api_country']??'');if(!$country||!isset(MBR_CORE8[$country]))continue;$anPending[$id]=['country'=>$country,'source_names'=>array_values(array_unique(array_filter([(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??'')],static fn($v)=>trim($v)!==''))),'source_places'=>array_values(array_unique(array_filter([(string)($s['api_region']??''),(string)($s['api_town']??'')],static fn($v)=>trim($v)!==''))),'coord_source'=>$s,'observations'=>0,'last_seen_utc'=>null];}

        $accepted=[];$rounds=[];
        for($round=1;$round<=$maxRounds;$round++){
            $new=[];
            foreach($andPending as $external=>$src){if(isset($accepted['andromeda:'.$external]))continue;$c=hbcr_candidate($src['source_names'],$src['source_places'],$src['coord_source'],$src['country'],$anexLocal,$hotels,$names,$places);if($c!==null)$new['andromeda:'.$external]=array_merge(['provider'=>'andromeda','external_id'=>$external,'country_id'=>$src['country'],'observation_count'=>$src['observations'],'last_seen_utc'=>$src['last_seen_utc'],'source_names'=>$src['source_names'],'source_places'=>$src['source_places'],'round'=>$round,'rule'=>'fixed_point_strict_identity_direct_geo_existing_anex_tv'], $c);}
            foreach($anPending as $id=>$src){if(isset($accepted['anex:'.$id]))continue;$c=hbcr_candidate($src['source_names'],$src['source_places'],$src['coord_source'],$src['country'],$andromedaLocal,$hotels,$names,$places,$excluded[$id]??[]);if($c!==null)$new['anex:'.$id]=array_merge(['provider'=>'anex','external_id'=>$id,'country_id'=>$src['country'],'observation_count'=>$src['observations'],'last_seen_utc'=>$src['last_seen_utc'],'source_names'=>$src['source_names'],'source_places'=>$src['source_places'],'round'=>$round,'rule'=>'fixed_point_strict_identity_direct_geo_existing_andromeda_tv'], $c);}
            if(!$new)break;
            foreach($new as $key=>$row){$accepted[$key]=$row;$target=(int)$row['target_local_hotel_id'];if($row['provider']==='andromeda')$andromedaLocal[$target]=true;else$anexLocal[$target]=true;}
            $rounds[]=['round'=>$round,'new'=>count($new),'andromeda'=>count(array_filter($new,static fn($r)=>$r['provider']==='andromeda')),'anex'=>count(array_filter($new,static fn($r)=>$r['provider']==='anex'))];
        }
        $rows=array_values($accepted);usort($rows,static fn($a,$b)=>(int)$b['observation_count']<=>(int)$a['observation_count'] ?: $a['round']<=>$b['round'] ?: strcmp((string)$a['provider'],(string)$b['provider']) ?: strcmp((string)$a['external_id'],(string)$b['external_id']));
        $stats=['andromeda_pending_examined'=>count($andPending),'anex_unresolved_examined'=>count($anPending),'prepared'=>count($rows),'prepared_andromeda'=>count(array_filter($rows,static fn($r)=>$r['provider']==='andromeda')),'prepared_anex'=>count(array_filter($rows,static fn($r)=>$r['provider']==='anex')),'live_prepared'=>count(array_filter($rows,static fn($r)=>(int)$r['observation_count']>0)),'rounds'=>count($rounds)];
        $coverage=fc_coverage($db);$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'catalog_scope'=>$scope,'coverage'=>$coverage,'initial_provider_local_sets'=>$initial,'stats'=>$stats,'rounds'=>$rounds,'prepared'=>$rows];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
