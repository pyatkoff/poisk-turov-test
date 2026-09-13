<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const HMSS_OPERATION = 'hotel-match-star-semantics-current-review-2333-20260913-v1';

function hmss_raw_category(array $source): array {
    $out=[];
    foreach (['category','star','stars','starName','star_name','starKey','star_key'] as $key) {
        if (array_key_exists($key,$source)) $out[$key]=$source[$key];
    }
    return $out;
}

function hmss_review(PDO $db,string $operation=HMSS_OPERATION): array {
    if ($operation!==HMSS_OPERATION) throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);
        $shaCountry=fc_sha_countries($db);
        $latest=[];
        foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];if(!isset($latest[$id]))$latest[$id]=$o;}
        $stats=['pending_examined'=>0,'numeric_star_guard_mismatch'=>0,'strict_name_mismatch'=>0,'non_strict_mismatch'=>0];
        $byField=[];$byPair=[];$rows=[];
        $sql="SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id";
        foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r){
            $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;$stats['pending_examined']++;
            $review=mbr_review_andromeda($r,$obs,$country,$hotels,$names,$strict,$places);if(($review['reason']??'')!=='numeric_star_guard_mismatch')continue;
            $stats['numeric_star_guard_mismatch']++;$prior=fc_evidence($r['evidence_json']??'');$source=$prior['source']??[];if(!is_array($source))$source=[];
            $rawSource=hmss_raw_category($source);$rawObs=is_array($obs)?hmss_raw_category($obs):[];$sourceCategory=$review['source_category']??null;$targetCategory=$review['target']['category']??null;$pair=(string)$sourceCategory.'→'.(string)$targetCategory;$byPair[$pair]=($byPair[$pair]??0)+1;
            foreach($rawSource as $k=>$v){$key='source.'.$k.'='.(is_scalar($v)?(string)$v:gettype($v));$byField[$key]=($byField[$key]??0)+1;}
            foreach($rawObs as $k=>$v){$key='observation.'.$k.'='.(is_scalar($v)?(string)$v:gettype($v));$byField[$key]=($byField[$key]??0)+1;}
            $strictIds=mbr_ids_for_names($strict,$country,$review['source_names']??[],false);$strictUnique=count($strictIds)===1;$stats[$strictUnique?'strict_name_mismatch':'non_strict_mismatch']++;
            $rows[]=['external_id'=>$external,'country_id'=>$country,'source_names'=>$review['source_names']??[],'source_places'=>$review['source_places']??[],'source_category'=>$sourceCategory,'target'=>$review['target']??null,'strict_unique_name'=>$strictUnique,'raw_source_category_fields'=>$rawSource,'raw_observation_category_fields'=>$rawObs];
        }
        arsort($byField);arsort($byPair);$coverage=fc_coverage($db);$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'catalog_scope'=>$scope,'coverage'=>$coverage,'stats'=>$stats,'category_pairs'=>$byPair,'raw_field_values'=>$byField,'rows'=>$rows];
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
