<?php
/** Saved normalized Search3 scopes for conservative DB-first reuse. No supplier I/O. */
declare(strict_types=1);

require_once __DIR__ . '/anytour-search-scope-v1.php';

final class AnyTourOfferScopeIndexV1
{
    private const MAX_CANDIDATE_SCOPES = 64;

    private static function digest(mixed $value,string $error='ANYTOUR_SCOPE_INDEX_DIGEST'): string
    {
        if(!is_string($value)||!preg_match('/\A[a-f0-9]{64}\z/D',$value))throw new InvalidArgumentException($error);
        return $value;
    }
    private static function sqlTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    public static function installed(PDO $db): bool
    {
        $stmt=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_offer_scopes' AND TABLE_TYPE='BASE TABLE'");
        $stmt->execute();return (int)$stmt->fetchColumn()===1;
    }
    /** Register canonical scope metadata. Digest/JSON disagreement always fails closed. */
    public static function recordIfInstalled(PDO $db,array $scope,DateTimeImmutable $at): bool
    {
        if(!self::installed($db))return false;
        $digest=self::digest($scope['digest']??null);$version=$scope['version']??null;$params=$scope['params']??null;
        if($version!==AnyTourSearchScopeV1::VERSION||!is_array($params))throw new InvalidArgumentException('ANYTOUR_SCOPE_INDEX_SCOPE');
        $params=AnyTourSearchScopeV1::validateNormalized($params);$json=AnyTourSearchScopeV1::json($params);
        if(!hash_equals($digest,hash('sha256',$json)))throw new InvalidArgumentException('ANYTOUR_SCOPE_INDEX_SCOPE_HASH');
        $family=AnyTourSearchScopeV1::familyDigest($params);$time=self::sqlTime($at);
        $stmt=$db->prepare('INSERT INTO anytour_offer_scopes(scope_sha256,scope_version,family_sha256,params_json,params_sha256,first_seen_at,last_seen_at) VALUES(:scope,:version,:family,:json,:json_sha,:at,:at) ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at)');
        $stmt->execute(['scope'=>$digest,'version'=>$version,'family'=>$family,'json'=>$json,'json_sha'=>hash('sha256',$json),'at'=>$time]);
        $check=$db->prepare('SELECT scope_version,family_sha256,params_json,params_sha256 FROM anytour_offer_scopes WHERE scope_sha256=:scope');
        $check->execute(['scope'=>$digest]);$row=$check->fetch(PDO::FETCH_ASSOC);
        if(!$row||(int)$row['scope_version']!==$version||!hash_equals($family,(string)$row['family_sha256'])||!hash_equals((string)$row['params_sha256'],hash('sha256',(string)$row['params_json']))||!hash_equals($digest,hash('sha256',(string)$row['params_json']))||!hash_equals($json,(string)$row['params_json']))throw new RuntimeException('ANYTOUR_SCOPE_INDEX_CONFLICT');
        return true;
    }
    /**
     * Return only saved scopes whose complete cached result set is a subset of current.
     * The exact current scope is excluded; callers use exact visibility first.
     */
    public static function compatibleDigests(PDO $db,array $current,DateTimeImmutable $now,int $limit=self::MAX_CANDIDATE_SCOPES): array
    {
        if($limit<1||$limit>self::MAX_CANDIDATE_SCOPES)throw new InvalidArgumentException('ANYTOUR_SCOPE_INDEX_LIMIT');
        if(!self::installed($db))return [];
        $digest=self::digest($current['digest']??null);$params=$current['params']??null;
        if(($current['version']??null)!==AnyTourSearchScopeV1::VERSION||!is_array($params))throw new InvalidArgumentException('ANYTOUR_SCOPE_INDEX_CURRENT');
        $params=AnyTourSearchScopeV1::validateNormalized($params);$family=AnyTourSearchScopeV1::familyDigest($params);
        // Fetch a bounded recent family cohort which has at least one currently visible row
        // in a latest completed provider snapshot. Compatibility itself is checked in PHP.
        $sql='SELECT x.scope_sha256,x.scope_version,x.family_sha256,x.params_json,x.params_sha256,MAX(o.last_seen_at) AS newest '
            .'FROM anytour_offer_scopes x '
            .'JOIN anytour_offer_scope_state s ON s.scope_sha256=x.scope_sha256 AND s.latest_complete_refresh_token IS NOT NULL '
            .'JOIN anytour_offers o ON o.scope_sha256=x.scope_sha256 AND o.provider=s.provider AND o.last_refresh_token=s.latest_complete_refresh_token '
            .'AND o.is_active=1 AND o.final_price_ready=1 AND o.expires_at>:now '
            .'WHERE x.family_sha256=:family AND x.scope_sha256<>:current '
            .'GROUP BY x.scope_sha256,x.scope_version,x.family_sha256,x.params_json,x.params_sha256 '
            .'ORDER BY newest DESC,x.last_seen_at DESC LIMIT '.self::MAX_CANDIDATE_SCOPES;
        $stmt=$db->prepare($sql);$stmt->execute(['now'=>self::sqlTime($now),'family'=>$family,'current'=>$digest]);
        $out=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $savedDigest=self::digest((string)$row['scope_sha256']);$json=(string)$row['params_json'];
            if((int)$row['scope_version']!==AnyTourSearchScopeV1::VERSION||!hash_equals($family,(string)$row['family_sha256'])||!hash_equals((string)$row['params_sha256'],hash('sha256',$json))||!hash_equals($savedDigest,hash('sha256',$json)))throw new RuntimeException('ANYTOUR_SCOPE_INDEX_INTEGRITY');
            try{$saved=json_decode($json,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('ANYTOUR_SCOPE_INDEX_INTEGRITY',0,$e);}
            if(!is_array($saved))throw new RuntimeException('ANYTOUR_SCOPE_INDEX_INTEGRITY');
            if(AnyTourSearchScopeV1::savedSubsetOfCurrent($saved,$params))$out[]=$savedDigest;
            if(count($out)>=$limit)break;
        }
        return $out;
    }
}
