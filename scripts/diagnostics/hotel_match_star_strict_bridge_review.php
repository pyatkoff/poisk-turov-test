<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_star_semantics_current_review.php';

const HMSSB_OPERATION = 'hotel-match-star-strict-bridge-review-2333-20260913-v2';

function hmssb_review(PDO $db,string $operation=HMSSB_OPERATION): array {
    if($operation!==HMSSB_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $base=hmss_review($db,HMSS_OPERATION);
        $anexByLocal=[];
        $sql="SELECT m.catalog_hotel_id,m.anex_hotel_id,h.api_name,h.xml_name,h.xml_alternate_name,h.api_region,h.api_town,h.api_country,h.category,h.star,h.stars FROM anex_hotel_search_mappings m LEFT JOIN anex_hotels h ON h.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 ORDER BY m.catalog_hotel_id,m.anex_hotel_id";
        try{$rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){
            $sql="SELECT m.catalog_hotel_id,m.anex_hotel_id,h.api_name,h.xml_name,h.xml_alternate_name,h.api_region,h.api_town,h.api_country FROM anex_hotel_search_mappings m LEFT JOIN anex_hotels h ON h.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 ORDER BY m.catalog_hotel_id,m.anex_hotel_id";
            $rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach($rows as $r){$anexByLocal[(int)$r['catalog_hotel_id']][]=$r;}
        $out=[];$stats=['strict_mismatch'=>0,'target_has_anex_anchor'=>0,'direct_place_match'=>0,'both_anchor_and_place'=>0,'strong_distinctive_candidates'=>0];
        foreach($base['rows'] as $row){if(empty($row['strict_unique_name']))continue;$stats['strict_mismatch']++;$target=$row['target']??[];$local=(int)($target['local_hotel_id']??0);$anchors=$anexByLocal[$local]??[];$place=fc_place(array_map('strval',$row['source_places']??[]),[(string)($target['region']??''),(string)($target['subregion']??'')]);if($anchors)$stats['target_has_anex_anchor']++;if($place)$stats['direct_place_match']++;if($anchors&&$place)$stats['both_anchor_and_place']++;
            $tokens=fc_tokens(implode(' ',array_map('strval',$row['source_names']??[])),true);$distinctive=count(array_unique($tokens))>=2;
            if($anchors&&$place&&$distinctive)$stats['strong_distinctive_candidates']++;
            $out[]=$row+['direct_place_match'=>$place,'accepted_anex_anchors'=>$anchors,'distinctive_multi_token'=>$distinctive];
        }
        $db->commit();return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$base['coverage'],'stats'=>$stats,'rows'=>$out];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
