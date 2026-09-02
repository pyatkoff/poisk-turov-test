<?php
declare(strict_types=1);
require_once __DIR__.'/seo-offer-snapshot-v1.php';

/**
 * Read fresh offers for one exact month/resort-month page identity.
 * This is DB-only and never issues a Tourvisor request.
 */
function v2_seo_seasonal_snapshot_offers(string $pageKey, int $limit=6): array
{
    $pageKey=trim($pageKey);
    if(!preg_match('/^(month:\d+:\d+:\d{4}-\d{2}|resort_month:\d+:\d+:\d+:\d{4}-\d{2})$/',$pageKey)) return [];
    $limit=max(1,min(12,$limit));
    try{
        $pdo=v2_data_db();
        $stmt=$pdo->prepare("SELECT s.departure_id,s.offers_json,s.observed_at,s.expires_at,COALESCE(d.name,'') departure_name
          FROM seo_offer_snapshots s
          LEFT JOIN catalog_departures d ON d.id=s.departure_id
         WHERE s.page_key=:page_key
           AND s.page_type IN ('month','resort_month')
           AND s.expires_at>=NOW()
           AND s.offer_count>0
           AND s.currency='RUB'
         ORDER BY s.observed_at DESC,s.min_price ASC
         LIMIT 8");
        $stmt->execute(['page_key'=>$pageKey]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }catch(Throwable $e){return [];}

    $offers=[];$seen=[];
    foreach($rows as $row){
        $decoded=json_decode((string)($row['offers_json']??''),true);
        if(!is_array($decoded))continue;
        foreach($decoded as $offer){
            if(!is_array($offer))continue;
            $hotelId=(int)($offer['hotelId']??0);$price=(float)($offer['price']??0);
            $date=trim((string)($offer['departureDate']??''));$nights=(int)($offer['nights']??0);
            if($hotelId<=0||$price<=0||$date===''||$nights<=0)continue;
            $departureId=(int)($row['departure_id']??0);
            $key=$departureId.':'.$hotelId.':'.$date.':'.$nights;
            if(isset($seen[$key]))continue;$seen[$key]=true;
            $offer['departureId']=$departureId;
            $offer['departureName']=trim((string)($row['departure_name']??''));
            $offer['snapshotObservedAt']=(string)($row['observed_at']??'');
            $offers[]=$offer;
        }
    }
    usort($offers,static function(array $a,array $b):int{
        $cmp=((float)$a['price'])<=>((float)$b['price']);
        return $cmp!==0?$cmp:strcmp((string)$a['departureDate'],(string)$b['departureDate']);
    });
    return array_slice($offers,0,$limit);
}
