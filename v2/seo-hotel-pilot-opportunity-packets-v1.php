<?php
declare(strict_types=1);

/**
 * Aggregate exact opportunity evidence packets for the controlled 3x3 hotel
 * review slice. This never creates publication candidates; it only reports how
 * much opportunity evidence is complete before any future launch decision.
 */
function v2_seo_hotel_pilot_opportunity_packets(array $reviewItems, array $packetsByPath): array
{
    if(count($reviewItems)!==9) throw new InvalidArgumentException('Hotel pilot opportunity packet set requires exactly 9 review items');
    $rows=[]; $countryCounts=[]; $ready=0; $blocked=0; $scoringPending=0; $seen=[];
    foreach($reviewItems as $item){
        if(!is_array($item)) throw new InvalidArgumentException('Hotel pilot review item must be an array');
        $path=(string)($item['path']??'');
        $countryId=(int)($item['country_id']??0);
        $hotelId=(int)($item['hotel_id']??0);
        if($path===''||isset($seen[$path])||$countryId<=0||$hotelId<=0) throw new InvalidArgumentException('Hotel pilot review identity invalid');
        $seen[$path]=true;
        if((int)($item['score']??0)!==100) throw new InvalidArgumentException('Hotel pilot opportunity packet set requires technical score 100');
        $packet=is_array($packetsByPath[$path]??null)?$packetsByPath[$path]:[];
        $packetState=(string)($packet['state']??'missing');
        $packetPath=(string)($packet['path']??'');
        if($packet!==[]&&$packetPath!==$path) throw new InvalidArgumentException('Hotel pilot opportunity packet path mismatch');
        $isReady=$packetState==='opportunity_evidence_review_ready'&&($packet['evidence_fresh']??false)===true&&($packet['evidence_confirmed']??false)===true&&($packet['uniqueness_distinct']??false)===true;
        if($isReady)$ready++;else$blocked++;
        if(($packet['scoring_policy_pending']??false)===true)$scoringPending++;
        $countryCounts[$countryId]=($countryCounts[$countryId]??0)+1;
        $rows[]=[
            'path'=>$path,
            'country_id'=>$countryId,
            'hotel_id'=>$hotelId,
            'technical_score'=>100,
            'packet_state'=>$packetState,
            'packet_sha256'=>(string)($packet['packet_sha256']??''),
            'opportunity_evidence_ready'=>$isReady,
            'scoring_policy_pending'=>(bool)($packet['scoring_policy_pending']??false),
        ];
    }
    ksort($countryCounts,SORT_NUMERIC);
    if($countryCounts!==[1=>3,4=>3,8=>3]) throw new InvalidArgumentException('Hotel pilot opportunity packet set must remain balanced 3x3 across Egypt/Turkey/Maldives');
    usort($rows,static fn(array $a,array $b):int=>strcmp($a['path'],$b['path']));
    $fingerprint=hash('sha256',json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    return [
        'state'=>$ready===9?'review_only_opportunity_evidence_complete':'review_only_opportunity_evidence_incomplete',
        'hotel_count'=>9,
        'country_counts'=>$countryCounts,
        'opportunity_evidence_ready_count'=>$ready,
        'opportunity_evidence_blocked_count'=>$blocked,
        'scoring_policy_pending_count'=>$scoringPending,
        'rows'=>$rows,
        'packet_set_sha256'=>$fingerprint,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,
    ];
}
