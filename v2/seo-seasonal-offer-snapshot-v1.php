<?php
declare(strict_types=1);
require_once __DIR__.'/seo-offer-snapshot-v1.php';
require_once __DIR__.'/data/price-segment-v1.php';

/** Keep the absolute cheapest card first; proven nearby promo drops may follow. */
function v2_seo_rank_seasonal_offer_cards(array $offers,int $limit=6):array
{
    $limit=max(1,min(12,$limit));
    if(!$offers)return [];
    usort($offers,static function(array $a,array $b):int{
        $cmp=((float)($a['price']??0))<=>((float)($b['price']??0));
        return $cmp!==0?$cmp:strcmp((string)($a['departureDate']??''),(string)($b['departureDate']??''));
    });
    $cheapest=(float)($offers[0]['price']??0);
    if($cheapest<=0)return array_slice($offers,0,$limit);
    $selected=[0=>$offers[0]];$used=[0=>true];$promo=[];$guard=$cheapest*1.12;
    foreach($offers as $i=>$offer){
        if($i===0)continue;
        $price=(float)($offer['price']??0);
        $intel=is_array($offer['priceIntelligence']??null)?$offer['priceIntelligence']:[];
        if($price<=0||$price>$guard||($intel['showPromoDrop']??false)!==true)continue;
        $promo[]=['index'=>$i,'drop'=>(int)($intel['historicalDropPercent']??0),'price'=>$price];
    }
    usort($promo,static function(array $a,array $b):int{
        $drop=$b['drop']<=>$a['drop'];return $drop!==0?$drop:($a['price']<=>$b['price']);
    });
    foreach($promo as $candidate){
        if(count($selected)>=$limit)break;
        $i=(int)$candidate['index'];if(isset($used[$i]))continue;
        $selected[]=$offers[$i];$used[$i]=true;
    }
    foreach($offers as $i=>$offer){
        if(count($selected)>=$limit)break;
        if(isset($used[$i]))continue;
        $selected[]=$offer;$used[$i]=true;
    }
    return array_values($selected);
}

/** Exact page identity, never a substitute country/month/departure. */
function v2_seo_seasonal_page_identity(string $pageKey): ?array
{
    if(!preg_match('/^(month|resort_month):([1-9][0-9]*):([1-9][0-9]*):(?:([1-9][0-9]*):)?([0-9]{4})-(0[1-9]|1[0-2])$/D',trim($pageKey),$m))return null;
    $resort=$m[1]==='resort_month';
    if($resort!==($m[4]!==''))return null;
    $year=(int)$m[5];$month=(int)$m[6];
    if($year<2020||$year>2100)return null;
    $from=sprintf('%04d-%02d-01',$year,$month);
    return ['departure_id'=>(int)$m[2],'country_id'=>(int)$m[3],'region_id'=>$resort?(int)$m[4]:null,'year'=>$year,'month'=>$month,'from'=>$from,'to'=>(new DateTimeImmutable($from))->modify('last day of this month')->format('Y-m-d')];
}

/**
 * Fill a missing materialized month block from already committed observations.
 * Uses the existing destination/month index; no supplier calls or DB writes.
 * Latest exact segment first, then one cheapest offer per hotel, then LIMIT.
 */
function v2_seo_seasonal_observation_offers(PDO $pdo,string $pageKey,int $limit=24): array
{
    $identity=v2_seo_seasonal_page_identity($pageKey);
    if($identity===null)return [];
    $limit=max(1,min(24,$limit));
    $now=new DateTimeImmutable((string)$pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn());
    $params=['departure'=>$identity['departure_id'],'country'=>$identity['country_id'],'year'=>$identity['year'],'month'=>$identity['month'],
        'fresh_since'=>$now->modify('-72 hours')->format('Y-m-d H:i:s'),'observed_until'=>$now->format('Y-m-d H:i:s'),
        'date_from'=>max($identity['from'],$now->format('Y-m-d')),'date_to'=>$identity['to']];
    $region='';
    if($identity['region_id']!==null){$region=' AND o.region_id=:region';$params['region']=$identity['region_id'];}
    $sql="WITH latest AS (
        SELECT o.*, ROW_NUMBER() OVER (
            PARTITION BY o.hotel_id,o.departure_date,o.nights,COALESCE(o.meal_id,0),COALESCE(o.room_id,0),COALESCE(o.room_type,''),COALESCE(o.operator_id,0),o.currency
            ORDER BY o.observed_at DESC,o.id DESC
        ) segment_rank
        FROM tour_price_observations o
        WHERE o.departure_id=:departure AND o.country_id=:country
          AND o.departure_year=:year AND o.departure_month=:month
          AND o.observed_at>=:fresh_since AND o.observed_at<=:observed_until
          AND o.departure_date>=:date_from AND o.departure_date<=:date_to
          AND o.adults=2 AND o.children_count=0 AND o.currency='RUB'
          AND o.price>0 AND o.nights BETWEEN 1 AND 28{$region}
    ), hotels AS (
        SELECT r.*,h.name hotelName,h.category hotelCategory,h.primary_image_url hotelImage,
               COALESCE(d.name,'') departureName,COALESCE(cr.name,'') regionName,
               ROW_NUMBER() OVER (PARTITION BY r.hotel_id ORDER BY r.price,r.observed_at DESC,r.id DESC) hotel_rank
        FROM latest r
        JOIN catalog_hotels h ON h.id=r.hotel_id AND h.country_id=r.country_id AND h.is_active=1
        LEFT JOIN catalog_departures d ON d.id=r.departure_id
        LEFT JOIN catalog_regions cr ON cr.id=r.region_id
        WHERE r.segment_rank=1
    ) SELECT * FROM hotels WHERE hotel_rank=1 ORDER BY price,departure_date,hotel_id LIMIT {$limit}";
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    $offers=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){
        try{$segment=v2_price_segment_fingerprint($row);}catch(InvalidArgumentException){continue;}
        $offers[]=['tourId'=>(string)($row['tour_id']??''),'segmentFingerprint'=>$segment,'hotelId'=>(int)$row['hotel_id'],
            'hotelName'=>(string)$row['hotelName'],'hotelCategory'=>$row['hotelCategory']!==null?(int)$row['hotelCategory']:null,'hotelImage'=>(string)($row['hotelImage']??''),
            'regionId'=>$row['region_id']!==null?(int)$row['region_id']:null,'regionName'=>(string)$row['regionName'],
            'departureId'=>(int)$row['departure_id'],'departureName'=>(string)$row['departureName'],'departureDate'=>(string)$row['departure_date'],
            'nights'=>(int)$row['nights'],'adults'=>(int)$row['adults'],'childrenCount'=>(int)$row['children_count'],
            'mealId'=>$row['meal_id']!==null?(int)$row['meal_id']:null,'roomId'=>$row['room_id']!==null?(int)$row['room_id']:null,'roomType'=>(string)($row['room_type']??''),
            'operatorId'=>$row['operator_id']!==null?(int)$row['operator_id']:null,'price'=>(float)$row['price'],'currency'=>(string)$row['currency'],
            'observedAt'=>(string)$row['observed_at'],'snapshotObservedAt'=>(string)$row['observed_at'],'observationSource'=>(string)$row['source']];
    }
    return $offers;
}

/** Shared by runtime and SQL regression; snapshot remains the fast first choice. */
function v2_seo_seasonal_snapshot_candidates(PDO $pdo,string $pageKey): array
{
    $identity=v2_seo_seasonal_page_identity($pageKey);if($identity===null)return [];
    $today=(string)$pdo->query('SELECT CURRENT_DATE')->fetchColumn();
    $rows=[];
    try{
        $stmt=$pdo->prepare("SELECT s.departure_id,s.offers_json,s.observed_at,s.expires_at,COALESCE(d.name,'') departure_name
          FROM seo_offer_snapshots s LEFT JOIN catalog_departures d ON d.id=s.departure_id
          WHERE s.page_key=:page_key AND s.page_type IN ('month','resort_month')
            AND s.expires_at>=CURRENT_TIMESTAMP AND s.offer_count>0 AND s.currency='RUB'
          ORDER BY s.observed_at DESC,s.min_price ASC LIMIT 8");
        $stmt->execute(['page_key'=>$pageKey]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }catch(Throwable $e){error_log('ANYTOUR_SEASONAL_SNAPSHOT_READ_FAILED key='.$pageKey.' code='.$e->getCode());}
    $offers=[];$seen=[];
    foreach($rows as $row){
        $decoded=json_decode((string)($row['offers_json']??''),true);if(!is_array($decoded))continue;
        foreach($decoded as $offer){
            if(!is_array($offer))continue;
            $hotelId=(int)($offer['hotelId']??0);$price=(float)($offer['price']??0);
            $date=trim((string)($offer['departureDate']??''));$nights=(int)($offer['nights']??0);
            $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if($hotelId<=0||$price<=0||$nights<1||$nights>28||!$parsed||$parsed->format('Y-m-d')!==$date||$date<max($today,$identity['from'])||$date>$identity['to'])continue;
            if($identity['region_id']!==null&&(int)($offer['regionId']??0)!==$identity['region_id'])continue;
            $departureId=(int)($row['departure_id']??0);if($departureId!==$identity['departure_id'])continue;
            $key=$departureId.':'.$hotelId.':'.$date.':'.$nights;if(isset($seen[$key]))continue;$seen[$key]=true;
            $offer['departureId']=$departureId;$offer['departureName']=trim((string)$row['departure_name']);$offer['snapshotObservedAt']=(string)$row['observed_at'];
            $offers[]=$offer;
        }
    }
    if(!$offers)$offers=v2_seo_seasonal_observation_offers($pdo,$pageKey,24);
    usort($offers,static fn(array $a,array $b):int=>((float)$a['price']<=>(float)$b['price'])?:strcmp($a['departureDate'],$b['departureDate']));
    return $offers;
}

/** DB-only: one read per page key in a request, shared by cards and calendar. */
function v2_seo_seasonal_snapshot_offers(string $pageKey,int $limit=6): array
{
    $pageKey=trim($pageKey);if(v2_seo_seasonal_page_identity($pageKey)===null)return [];
    $limit=max(1,min(12,$limit));static $cache=[];
    try{if(!array_key_exists($pageKey,$cache))$cache[$pageKey]=v2_seo_seasonal_snapshot_candidates(v2_data_db(),$pageKey);}
    catch(Throwable $e){error_log('ANYTOUR_SEASONAL_OFFERS_READ_FAILED key='.$pageKey.' code='.$e->getCode());return [];}
    $offers=$cache[$pageKey];$candidateLimit=min(count($offers),$limit*2);
    return v2_seo_rank_seasonal_offer_cards(v2_seo_enrich_offer_prices(array_slice($offers,0,$candidateLimit)),$limit);
}
