<?php
/** Read-only v2 visibility owner: latest completed snapshots with a current accepted AnyTour identity only. */
declare(strict_types=1);

final class AnyTourOfferStoreReadV2
{
    private static function digest(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/\A[a-f0-9]{64}\z/D',$value))throw new InvalidArgumentException('ANYTOUR_OFFER_SCOPE');
        return $value;
    }
    private static function decimal(string $value): string
    {
        if(!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$value))throw new RuntimeException('ANYTOUR_OFFER_PRICE_INTEGRITY');
        $parts=explode('.',$value,2);$fraction=rtrim($parts[1]??'','0');return $parts[0].($fraction===''?'':'.'.$fraction);
    }
    private static function iso(string $value): string
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        if(!$date)throw new RuntimeException('ANYTOUR_OFFER_TIME_INTEGRITY');
        return $date->format('Y-m-d\TH:i:s\Z');
    }
    public static function readScope(PDO $db,string $scope,DateTimeImmutable $now,int $limit=1000): array
    {
        $scope=self::digest($scope);if($limit<1||$limit>5000)throw new InvalidArgumentException('ANYTOUR_OFFER_READ_LIMIT');
        $sql='SELECT o.anytour_hotel_id,o.legacy_hotel_id,o.provider,o.payload_json,o.payload_sha256,o.display_price,o.currency,o.observed_at,o.last_seen_at,o.expires_at '
            .'FROM anytour_offers o JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256 '
            .'AND s.latest_complete_refresh_token IS NOT NULL AND s.latest_complete_refresh_token=o.last_refresh_token '
            .'WHERE o.scope_sha256=:scope AND o.is_active=1 AND o.final_price_ready=1 AND o.expires_at>:now '
            ."AND EXISTS (SELECT 1 FROM anytour_hotel_sources hs WHERE hs.namespace='legacy_catalog' AND hs.external_key=CAST(o.legacy_hotel_id AS CHAR) AND hs.anytour_hotel_id=o.anytour_hotel_id) "
            .'ORDER BY o.display_price ASC,o.id ASC LIMIT '.$limit;
        $stmt=$db->prepare($sql);$stmt->execute(['scope'=>$scope,'now'=>$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);$items=[];
        while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
            $raw=(string)$row['payload_json'];if(!hash_equals((string)$row['payload_sha256'],hash('sha256',$raw)))throw new RuntimeException('ANYTOUR_OFFER_PAYLOAD_INTEGRITY');
            $payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($payload))throw new RuntimeException('ANYTOUR_OFFER_PAYLOAD_INTEGRITY');
            $items[]=['anytourHotelId'=>(int)$row['anytour_hotel_id'],'legacyHotelId'=>(int)$row['legacy_hotel_id'],'provider'=>(string)$row['provider'],
                'price'=>self::decimal((string)$row['display_price']),'currency'=>(string)$row['currency'],'observedAt'=>self::iso((string)$row['observed_at']),
                'lastSeenAt'=>self::iso((string)$row['last_seen_at']),'expiresAt'=>self::iso((string)$row['expires_at']),'offer'=>$payload];
        }
        return['source'=>'anytour-offer-store-v2','scopeDigest'=>$scope,'items'=>$items];
    }
}
