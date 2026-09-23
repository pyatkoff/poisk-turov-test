<?php
/** Read-only canonical exact-offer + consumer-equivalent price history. */
declare(strict_types=1);

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/anytour-offer-price-history-v1.php';
require_once __DIR__ . '/price-intelligence-v1.php';

final class AnyTourOfferPriceHistoryReadV1
{
    private const PROVIDERS = ['tourvisor'=>true,'anex'=>true,'andromeda'=>true];

    public static function read(
        PDO $db,
        string $provider,
        string $offerRefDigest,
        string $requestedCurrentPrice,
        int $days,
        DateTimeImmutable $now
    ): array {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_READ_PROVIDER');
        }
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $offerRefDigest)) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_READ_OFFER');
        }
        $requestedCurrentPrice = self::money($requestedCurrentPrice);
        if ($days < 7 || $days > 90) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_READ_DAYS');
        }
        if (!AnyTourOfferPriceHistoryV1::installed($db)) {
            return [
                'ok'=>true,
                'installed'=>false,
                'historyAvailable'=>false,
                'provider'=>$provider,
                'offerRefDigest'=>$offerRefDigest,
                'requestedCurrentPrice'=>$requestedCurrentPrice,
                'windowDays'=>$days,
                'source'=>'anytour-canonical-offer-price-history',
            ];
        }

        $latestStmt=$db->prepare(
            "SELECT id,consumer_segment_sha256,anytour_hotel_id,display_price,currency,
                    final_price_ready,final_price_verified,observed_at
               FROM anytour_offer_price_observations
              WHERE provider=:provider
                AND offer_ref_digest=:offer
                AND final_price_ready=1
              ORDER BY observed_at DESC,id DESC
              LIMIT 1"
        );
        $latestStmt->execute(['provider'=>$provider,'offer'=>$offerRefDigest]);
        $latest=$latestStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($latest)) {
            return [
                'ok'=>true,
                'installed'=>true,
                'historyAvailable'=>false,
                'provider'=>$provider,
                'offerRefDigest'=>$offerRefDigest,
                'requestedCurrentPrice'=>$requestedCurrentPrice,
                'windowDays'=>$days,
                'source'=>'anytour-canonical-offer-price-history',
            ];
        }

        $latestPrice=self::money((string)$latest['display_price']);
        $currentMatches=hash_equals($latestPrice,$requestedCurrentPrice);
        $from=$now->setTimezone(new DateTimeZone('UTC'))
            ->modify('-'.($days-1).' days')
            ->setTime(0,0,0)
            ->format('Y-m-d H:i:s');

        $exactStmt=$db->prepare(
            "SELECT observed_at,display_price,search_ref_digest,provider
               FROM anytour_offer_price_observations
              WHERE provider=:provider
                AND offer_ref_digest=:offer
                AND final_price_ready=1
                AND observed_at>=:from_value
              ORDER BY observed_at ASC,id ASC"
        );
        $exactStmt->execute(['provider'=>$provider,'offer'=>$offerRefDigest,'from_value'=>$from]);
        $exactRows=$exactStmt->fetchAll(PDO::FETCH_ASSOC)?:[];

        $consumerSegment=(string)$latest['consumer_segment_sha256'];
        if (!preg_match('/\A[a-f0-9]{64}\z/D',$consumerSegment)) {
            throw new RuntimeException('ANYTOUR_OFFER_PRICE_HISTORY_READ_CONSUMER_SEGMENT');
        }
        $consumerStmt=$db->prepare(
            "SELECT observed_at,display_price,search_ref_digest,provider
               FROM anytour_offer_price_observations
              WHERE consumer_segment_sha256=:segment
                AND final_price_ready=1
                AND observed_at>=:from_value
              ORDER BY observed_at ASC,id ASC"
        );
        $consumerStmt->execute(['segment'=>$consumerSegment,'from_value'=>$from]);
        $consumerRows=$consumerStmt->fetchAll(PDO::FETCH_ASSOC)?:[];

        $exactDaily=self::dailyExact($exactRows);
        $consumerDaily=self::dailyConsumerBest($consumerRows);
        $exact=v2_price_intelligence_summary($exactDaily,(float)$latestPrice);
        $consumer=v2_price_intelligence_summary($consumerDaily,(float)$latestPrice);

        if (($exact['ok']??false)===true && ($exact['series']??[])!==[]) {
            $exact['referenceMethod']='max_observed_price_exact_offer';
        }
        $exact['comparisonMode']='exact_offer';
        if (($consumer['ok']??false)===true && ($consumer['series']??[])!==[]) {
            $consumer['referenceMethod']='max_daily_best_price_consumer_comparable_segment';
        }
        $consumer['comparisonMode']='consumer_equivalent_operator_independent';

        if (!$currentMatches) {
            foreach ([&$exact,&$consumer] as &$summary) {
                if (($summary['ok']??false)!==true) continue;
                $summary['showPromoDrop']=false;
                $summary['showHistoricalDrop']=false;
                $summary['claimSuppressedReason']='current_price_mismatch';
            }
            unset($summary);
        }

        return [
            'ok'=>true,
            'installed'=>true,
            'historyAvailable'=>true,
            'provider'=>$provider,
            'offerRefDigest'=>$offerRefDigest,
            'consumerSegment'=>$consumerSegment,
            'anytourHotelId'=>(int)$latest['anytour_hotel_id'],
            'requestedCurrentPrice'=>$requestedCurrentPrice,
            'latestHistoryPrice'=>$latestPrice,
            'currentPriceMatchesLatest'=>$currentMatches,
            'latestObservedAt'=>self::iso((string)$latest['observed_at']),
            'latestFinalPriceVerified'=>(int)$latest['final_price_verified']===1,
            'windowDays'=>$days,
            'exactOffer'=>$exact,
            'consumerEquivalent'=>$consumer,
            'source'=>'anytour-canonical-offer-price-history',
        ];
    }

    private static function dailyExact(array $rows): array
    {
        $days=[];
        foreach($rows as $row){
            $date=substr((string)($row['observed_at']??''),0,10);
            self::date($date);
            $price=(float)self::money((string)($row['display_price']??''));
            $search=(string)($row['search_ref_digest']??'');
            if(!preg_match('/\A[a-f0-9]{64}\z/D',$search))throw new RuntimeException('ANYTOUR_OFFER_PRICE_HISTORY_READ_SEARCH');
            $days[$date]['prices'][]=$price;
            $days[$date]['searches'][$search]=true;
        }
        ksort($days,SORT_STRING);
        $out=[];
        foreach($days as $date=>$day){
            $prices=$day['prices'];sort($prices,SORT_NUMERIC);
            $count=count($prices);$middle=intdiv($count,2);
            $median=$count%2===1?$prices[$middle]:($prices[$middle-1]+$prices[$middle])/2;
            $out[]=[
                'price_date'=>$date,
                'min_price'=>min($prices),
                'median_price'=>$median,
                'max_price'=>max($prices),
                'observation_count'=>$count,
                'independent_search_count'=>count($day['searches']),
            ];
        }
        return $out;
    }

    private static function dailyConsumerBest(array $rows): array
    {
        $days=[];
        foreach($rows as $row){
            $date=substr((string)($row['observed_at']??''),0,10);
            self::date($date);
            $price=(float)self::money((string)($row['display_price']??''));
            $search=(string)($row['search_ref_digest']??'');
            $provider=(string)($row['provider']??'');
            if(!preg_match('/\A[a-f0-9]{64}\z/D',$search)||!isset(self::PROVIDERS[$provider])){
                throw new RuntimeException('ANYTOUR_OFFER_PRICE_HISTORY_READ_ROW');
            }
            if(!isset($days[$date]))$days[$date]=['best'=>$price,'count'=>0,'searches'=>[],'providers'=>[]];
            $days[$date]['best']=min((float)$days[$date]['best'],$price);
            $days[$date]['count']++;
            $days[$date]['searches'][$search]=true;
            $days[$date]['providers'][$provider]=true;
        }
        ksort($days,SORT_STRING);
        $out=[];
        foreach($days as $date=>$day){
            $best=(float)$day['best'];
            $out[]=[
                'price_date'=>$date,
                'min_price'=>$best,
                'median_price'=>$best,
                'max_price'=>$best,
                'observation_count'=>(int)$day['count'],
                'independent_search_count'=>count($day['searches']),
                'provider_count'=>count($day['providers']),
            ];
        }
        return $out;
    }

    private static function money(string $value): string
    {
        $value=trim($value);
        if(!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$value)||!preg_match('/[1-9]/',$value)){
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_READ_PRICE');
        }
        $parts=explode('.',$value,2);
        $fraction=rtrim($parts[1]??'','0');
        return $parts[0].($fraction===''?'':'.'.$fraction);
    }

    private static function date(string $value): void
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==$value)throw new RuntimeException('ANYTOUR_OFFER_PRICE_HISTORY_READ_DATE');
    }

    private static function iso(string $value): string
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new RuntimeException('ANYTOUR_OFFER_PRICE_HISTORY_READ_TIME');
        return $date->format('Y-m-d\TH:i:s\Z');
    }
}

if (!defined('ANYTOUR_OFFER_PRICE_HISTORY_READ_LIBRARY_ONLY')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=60');
    header('X-Content-Type-Options: nosniff');

    $out=static function(array $payload,int $status=200):never{
        http_response_code($status);
        echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    };

    try{
        $provider=trim((string)($_GET['provider']??''));
        $offer=trim((string)($_GET['offerRefDigest']??''));
        $current=$_GET['currentPrice']??null;
        if(!is_scalar($current))throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_READ_PRICE');
        $daysRaw=filter_var($_GET['days']??30,FILTER_VALIDATE_INT);
        $days=$daysRaw===false?30:(int)$daysRaw;
        $result=AnyTourOfferPriceHistoryReadV1::read(
            v2_data_db(),
            $provider,
            $offer,
            (string)$current,
            $days,
            new DateTimeImmutable('now',new DateTimeZone('UTC'))
        );
        $out($result);
    }catch(InvalidArgumentException $e){
        $out(['ok'=>false,'error'=>'invalid_request'],400);
    }catch(Throwable $e){
        error_log('anytour-offer-price-history-read-v1: '.mb_substr($e->getMessage(),0,500,'UTF-8'));
        $out(['ok'=>false,'error'=>'Price history is temporarily unavailable'],503);
    }
}
