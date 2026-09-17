<?php
/** Read-only v2 visibility owner: latest completed snapshots with a current accepted AnyTour identity only. */
declare(strict_types=1);

require_once __DIR__ . '/anytour-provider-identity-bridge-v1.php';

final class AnyTourOfferStoreReadV2
{
    private const PROVIDERS=['tourvisor'=>true,'anex'=>true,'andromeda'=>true];
    /** Three provider snapshots may each contain up to 5,000 accepted offers. */
    private const MAX_SCOPE_OFFERS=15000;
    private const MAX_SCOPES=64;
    private const MAX_LISTING_TTL_SECONDS=86400;

    private static function digest(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/\A[a-f0-9]{64}\z/D',$value))throw new InvalidArgumentException('ANYTOUR_OFFER_SCOPE');
        return $value;
    }
    private static function decimal(string $value): string
    {
        if(!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$value))throw new RuntimeException('ANYTOUR_OFFER_PRICE_INTEGRITY');
        $parts=explode('.',$value,2);$fraction=rtrim($parts[1]??'','0');
        if($parts[0]==='0'&&$fraction==='')throw new RuntimeException('ANYTOUR_OFFER_PRICE_INTEGRITY');
        return $parts[0].($fraction===''?'':'.'.$fraction);
    }
    private static function time(string $value): DateTimeImmutable
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new RuntimeException('ANYTOUR_OFFER_TIME_INTEGRITY');
        return $date;
    }
    private static function iso(string $value): string
    {
        return self::time($value)->format('Y-m-d\TH:i:s\Z');
    }
    private static function listing(array $payload,string $provider,string $price,string $currency): void
    {
        $payloadPrice=$payload['listingPrice']??null;
        if(($payload['schema_version']??null)!==1
            ||!isset(self::PROVIDERS[$provider])
            ||($payload['provider']??null)!==$provider
            ||($payload['listingPriceReady']??null)!==true
            ||!is_string($payloadPrice)
            ||self::decimal($payloadPrice)!==$price
            ||$currency!=='RUB'
            ||($payload['currency']??null)!=='RUB'
            ||($payload['selection_state']??null)!=='refresh_required'
            ||($payload['booking_enabled']??null)!==false){
            throw new RuntimeException('ANYTOUR_OFFER_LISTING_INTEGRITY');
        }
    }
    public static function readScope(PDO $db,string $scope,DateTimeImmutable $now,int $limit=self::MAX_SCOPE_OFFERS): array
    {
        $scope=self::digest($scope);$read=self::readScopes($db,[$scope],$now,$limit);
        return['source'=>'anytour-offer-store-v2','scopeDigest'=>$scope,'items'=>$read['items']];
    }
    /** Read a bounded union of already-approved compatible scope digests and deduplicate offer identity. */
    public static function readScopes(PDO $db,array $scopes,DateTimeImmutable $now,int $limit=self::MAX_SCOPE_OFFERS): array
    {
        if(!array_is_list($scopes)||$scopes===[]||count($scopes)>self::MAX_SCOPES)throw new InvalidArgumentException('ANYTOUR_OFFER_SCOPES');
        if($limit<1||$limit>self::MAX_SCOPE_OFFERS)throw new InvalidArgumentException('ANYTOUR_OFFER_READ_LIMIT');
        $unique=[];foreach($scopes as $scope)$unique[self::digest($scope)]=true;$scopes=array_keys($unique);
        $params=['now'=>$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];$slots=[];
        foreach($scopes as $i=>$scope){$key='scope'.$i;$slots[]=':'.$key;$params[$key]=$scope;}
        // Read the bounded complete provider cohort before identity filtering. Applying
        // the caller's smaller limit in SQL could let stale/unresolved cheap rows hide
        // valid canonical offers that sort after them.
        $sql='SELECT o.scope_sha256,o.anytour_hotel_id,o.legacy_hotel_id,o.provider,o.provider_hotel_ref_digest,o.offer_ref_digest,o.payload_json,o.payload_sha256,o.display_price,o.currency,o.observed_at,o.last_seen_at,o.expires_at '
            .'FROM anytour_offers o JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256 '
            .'AND s.latest_complete_refresh_token IS NOT NULL AND s.latest_complete_refresh_token=o.last_refresh_token '
            .'WHERE o.scope_sha256 IN ('.implode(',',$slots).') AND o.is_active=1 AND o.final_price_ready=1 AND o.expires_at>:now '
            .'ORDER BY o.last_seen_at DESC,o.id DESC LIMIT '.self::MAX_SCOPE_OFFERS;
        $stmt=$db->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows=AnyTourProviderIdentityBridgeV1::filterOfferRows($db,$rows);
        $items=[];$seen=[];
        foreach($rows as $row){
            $provider=(string)$row['provider'];$offerRef=self::digest((string)$row['offer_ref_digest']);$identity=$provider.':'.$offerRef;
            if(isset($seen[$identity]))continue;$seen[$identity]=true;
            $raw=(string)$row['payload_json'];if(!hash_equals((string)$row['payload_sha256'],hash('sha256',$raw)))throw new RuntimeException('ANYTOUR_OFFER_PAYLOAD_INTEGRITY');
            try{$payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('ANYTOUR_OFFER_PAYLOAD_INTEGRITY',0,$e);}
            if(!is_array($payload))throw new RuntimeException('ANYTOUR_OFFER_PAYLOAD_INTEGRITY');
            $price=self::decimal((string)$row['display_price']);$currency=(string)$row['currency'];self::listing($payload,$provider,$price,$currency);
            $lastSeen=self::time((string)$row['last_seen_at']);$expires=self::time((string)$row['expires_at']);
            $visibilitySeconds=$expires->getTimestamp()-$lastSeen->getTimestamp();
            if($visibilitySeconds<=0||$visibilitySeconds>self::MAX_LISTING_TTL_SECONDS)throw new RuntimeException('ANYTOUR_OFFER_TIME_INTEGRITY');
            $items[]=['anytourHotelId'=>(int)$row['anytour_hotel_id'],'legacyHotelId'=>(int)$row['legacy_hotel_id'],'provider'=>$provider,
                'price'=>$price,'currency'=>$currency,'observedAt'=>self::iso((string)$row['observed_at']),
                'lastSeenAt'=>$lastSeen->format('Y-m-d\TH:i:s\Z'),'expiresAt'=>$expires->format('Y-m-d\TH:i:s\Z'),'sourceScopeDigest'=>(string)$row['scope_sha256'],'offer'=>$payload];
            if(count($items)>=$limit)break;
        }
        usort($items,static fn($a,$b)=>(float)$a['price']<=>(float)$b['price']?:strcmp($a['provider'],$b['provider'])?:strcmp($a['sourceScopeDigest'],$b['sourceScopeDigest']));
        return['source'=>'anytour-offer-store-v2','scopeDigests'=>$scopes,'items'=>$items];
    }
}
