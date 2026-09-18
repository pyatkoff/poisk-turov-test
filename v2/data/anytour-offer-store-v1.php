<?php
declare(strict_types=1);

require_once __DIR__ . '/anytour-provider-identity-bridge-v1.php';

/** Provider-neutral current-offer store for Search3. No supplier I/O or price arithmetic. */
final class AnyTourOfferStoreV1
{
    private const PROVIDERS=['tourvisor','anex','andromeda'];
    private const DTO=['schema_version','provider','operator','local_hotel_id','identity','tour','money','quote_state','final_price_verified','quote_evidence_digest','context','selection_state','booking_enabled','finalPriceReady','finalPrice','price','currency'];
    private const ID=['search_ref_digest','offer_ref_digest','provider_hotel_ref_digest'];
    private const OP=['raw','canonical_name','canonical_verified','identity_source','filter_status','cross_provider_equivalence_verified','supplier_code_exposed'];
    private const TOUR=['checkin','nights','party','meal','room','placement','availability','flight_details','observed_at'];
    private const PARTY=['adults','children','child_ages'];
    private const CTX=['generation','page','issued_at','expires_at','current_context_verified'];
    private const MAX_LISTING_TTL_SECONDS=86400;

    public static function beginRefresh(PDO $db,string $provider,string $scope,DateTimeImmutable $now,int $lease=900):string
    {
        self::outsideTx($db); $provider=self::provider($provider); $scope=self::digest($scope,'ANYTOUR_OFFER_SCOPE');
        if($lease<60||$lease>1800) throw new InvalidArgumentException('ANYTOUR_OFFER_REFRESH_LEASE');
        $token=bin2hex(random_bytes(32)); $at=self::sqlTime($now); $until=self::sqlTime($now->modify("+$lease seconds"));
        self::tx($db,static function()use($db,$provider,$scope,$token,$at,$until):void{
            $q=$db->prepare('INSERT IGNORE INTO anytour_offer_scope_state(provider,scope_sha256,active_refresh_token,latest_complete_refresh_token,revision,updated_at) VALUES(:p,:s,NULL,NULL,1,:at)');
            $q->execute(['p'=>$provider,'s'=>$scope,'at'=>$at]);
            $state=self::scopeLock($db,$provider,$scope); $active=$state['active_refresh_token']??null;
            if(is_string($active)&&$active!==''){
                $old=self::refreshLock($db,$active);
                if(($old['status']??null)==='running'&&($old['lease_expires_at']??'')>$at) throw new DomainException('ANYTOUR_OFFER_REFRESH_BUSY');
                if(($old['status']??null)==='running'){
                    $q=$db->prepare("UPDATE anytour_offer_refreshes SET status='abandoned',completed_at=:at WHERE refresh_token=:t AND status='running'");
                    $q->execute(['at'=>$at,'t'=>$active]);
                }
            }
            $q=$db->prepare("INSERT INTO anytour_offer_refreshes(refresh_token,provider,scope_sha256,status,started_at,lease_expires_at,completed_at) VALUES(:t,:p,:s,'running',:at,:until,NULL)");
            $q->execute(['t'=>$token,'p'=>$provider,'s'=>$scope,'at'=>$at,'until'=>$until]);
            $q=$db->prepare('UPDATE anytour_offer_scope_state SET active_refresh_token=:t,revision=revision+1,updated_at=:at WHERE provider=:p AND scope_sha256=:s');
            $q->execute(['t'=>$token,'at'=>$at,'p'=>$provider,'s'=>$scope]);
            if($q->rowCount()!==1) throw new RuntimeException('ANYTOUR_OFFER_SCOPE_WRITE');
        });
        return $token;
    }

    public static function upsertReadyOffer(PDO $db,string $token,int $ownHotelId,array $dto,DateTimeImmutable $expiresAt,DateTimeImmutable $seenAt):array
    {
        self::outsideTx($db); $token=self::digest($token,'ANYTOUR_OFFER_REFRESH_TOKEN');
        if($ownHotelId<1) throw new InvalidArgumentException('ANYTOUR_OFFER_HOTEL_ID');
        $v=self::validateDto($dto); $seen=self::sqlTime($seenAt); $expires=self::sqlTime($expiresAt);
        if($expires<=$seen||$expiresAt->getTimestamp()-$seenAt->getTimestamp()>self::MAX_LISTING_TTL_SECONDS) throw new InvalidArgumentException('ANYTOUR_OFFER_EXPIRY');
        return self::tx($db,static function()use($db,$token,$ownHotelId,$dto,$v,$seen,$expires):array{
            $r=self::refreshLock($db,$token); if(($r['status']??null)!=='running') throw new DomainException('ANYTOUR_OFFER_REFRESH_NOT_RUNNING');
            $state=self::scopeLock($db,(string)$r['provider'],(string)$r['scope_sha256']);
            if(($state['active_refresh_token']??null)!==$token) throw new DomainException('ANYTOUR_OFFER_REFRESH_NOT_ACTIVE');
            if($v['provider']!==$r['provider']) throw new InvalidArgumentException('ANYTOUR_OFFER_PROVIDER_SCOPE');
            self::bridge($db,$v['provider'],$dto['identity']['provider_hotel_ref_digest'],$v['legacy_hotel_id'],$ownHotelId);

            $payload=self::json(self::listingProjection($dto)); $operator=self::json($dto['operator']); $party=self::json($dto['tour']['party']);
            $meal=self::json($dto['tour']['meal']); $room=self::json($dto['tour']['room']); $placement=self::json($dto['tour']['placement']);
            $identitySha=hash('sha256',self::json(['provider'=>$v['provider'],'identity'=>$dto['identity']]));
            $sql='INSERT INTO anytour_offers(anytour_hotel_id,legacy_hotel_id,provider,scope_sha256,search_ref_digest,offer_ref_digest,provider_hotel_ref_digest,identity_sha256,operator_json,operator_sha256,checkin,nights,adults,children,child_ages_json,party_sha256,meal_json,room_json,placement_json,display_price,currency,final_price_ready,final_price_verified,payload_json,payload_sha256,observed_at,source_context_expires_at,last_refresh_token,last_seen_at,expires_at,is_active) VALUES(:own,:legacy,:p,:scope,:search,:offer,:photel,:identity,:operator,:operator_sha,:checkin,:nights,:adults,:children,:ages,:party_sha,:meal,:room,:placement,:price,\'RUB\',:ready,:verified,:payload,:payload_sha,:observed,:ctx_exp,:refresh,:seen,:expires,1) ON DUPLICATE KEY UPDATE anytour_hotel_id=VALUES(anytour_hotel_id),legacy_hotel_id=VALUES(legacy_hotel_id),operator_json=VALUES(operator_json),operator_sha256=VALUES(operator_sha256),checkin=VALUES(checkin),nights=VALUES(nights),adults=VALUES(adults),children=VALUES(children),child_ages_json=VALUES(child_ages_json),party_sha256=VALUES(party_sha256),meal_json=VALUES(meal_json),room_json=VALUES(room_json),placement_json=VALUES(placement_json),display_price=VALUES(display_price),currency=\'RUB\',final_price_ready=VALUES(final_price_ready),final_price_verified=VALUES(final_price_verified),payload_json=VALUES(payload_json),payload_sha256=VALUES(payload_sha256),observed_at=VALUES(observed_at),source_context_expires_at=VALUES(source_context_expires_at),last_refresh_token=VALUES(last_refresh_token),last_seen_at=VALUES(last_seen_at),expires_at=VALUES(expires_at),is_active=1';
            $q=$db->prepare($sql); $q->execute([
                'own'=>$ownHotelId,'legacy'=>$v['legacy_hotel_id'],'p'=>$v['provider'],'scope'=>$r['scope_sha256'],
                'search'=>$dto['identity']['search_ref_digest'],'offer'=>$dto['identity']['offer_ref_digest'],'photel'=>$dto['identity']['provider_hotel_ref_digest'],'identity'=>$identitySha,
                'operator'=>$operator,'operator_sha'=>hash('sha256',$operator),'checkin'=>$dto['tour']['checkin'],'nights'=>$dto['tour']['nights'],'adults'=>$dto['tour']['party']['adults'],'children'=>$dto['tour']['party']['children'],
                'ages'=>self::json($dto['tour']['party']['child_ages']),'party_sha'=>hash('sha256',$party),'meal'=>$meal,'room'=>$room,'placement'=>$placement,'price'=>$v['price'],
                'ready'=>$v['final_price_ready']?1:0,'verified'=>$v['final_price_verified']?1:0,
                'payload'=>$payload,'payload_sha'=>hash('sha256',$payload),'observed'=>$v['observed_at'],'ctx_exp'=>$v['context_expires_at'],'refresh'=>$token,'seen'=>$seen,'expires'=>$expires,
            ]);
            return ['provider'=>$v['provider'],'anytourHotelId'=>$ownHotelId,'legacyHotelId'=>$v['legacy_hotel_id'],'scopeDigest'=>$r['scope_sha256'],'identitySha256'=>$identitySha,'payloadSha256'=>hash('sha256',$payload),'price'=>$v['price'],'currency'=>'RUB'];
        });
    }

    public static function completeRefresh(PDO $db,string $token,DateTimeImmutable $at):array
    {
        self::outsideTx($db); $token=self::digest($token,'ANYTOUR_OFFER_REFRESH_TOKEN'); $time=self::sqlTime($at);
        return self::tx($db,static function()use($db,$token,$time):array{
            $r=self::refreshLock($db,$token); if(($r['status']??null)!=='running') throw new DomainException('ANYTOUR_OFFER_REFRESH_NOT_RUNNING');
            $p=(string)$r['provider']; $scope=(string)$r['scope_sha256']; $state=self::scopeLock($db,$p,$scope);
            if(($state['active_refresh_token']??null)!==$token) throw new DomainException('ANYTOUR_OFFER_REFRESH_NOT_ACTIVE');
            $q=$db->prepare('UPDATE anytour_offers SET is_active=0,expires_at=LEAST(expires_at,:at) WHERE provider=:p AND scope_sha256=:s AND is_active=1 AND last_refresh_token<>:t');
            $q->execute(['at'=>$time,'p'=>$p,'s'=>$scope,'t'=>$token]); $expired=$q->rowCount();
            $q=$db->prepare("UPDATE anytour_offer_refreshes SET status='completed',completed_at=:at WHERE refresh_token=:t AND status='running'"); $q->execute(['at'=>$time,'t'=>$token]);
            if($q->rowCount()!==1) throw new RuntimeException('ANYTOUR_OFFER_REFRESH_FINISH');
            $q=$db->prepare('UPDATE anytour_offer_scope_state SET active_refresh_token=NULL,latest_complete_refresh_token=:t,revision=revision+1,updated_at=:at WHERE provider=:p AND scope_sha256=:s');
            $q->execute(['t'=>$token,'at'=>$time,'p'=>$p,'s'=>$scope]); if($q->rowCount()!==1) throw new RuntimeException('ANYTOUR_OFFER_SCOPE_FINISH');
            return ['provider'=>$p,'scopeDigest'=>$scope,'expiredUnseen'=>$expired];
        });
    }

    public static function abortRefresh(PDO $db,string $token,DateTimeImmutable $at):array
    {
        self::outsideTx($db); $token=self::digest($token,'ANYTOUR_OFFER_REFRESH_TOKEN'); $time=self::sqlTime($at);
        return self::tx($db,static function()use($db,$token,$time):array{
            $r=self::refreshLock($db,$token); if(($r['status']??null)!=='running') throw new DomainException('ANYTOUR_OFFER_REFRESH_NOT_RUNNING');
            $p=(string)$r['provider']; $scope=(string)$r['scope_sha256']; $state=self::scopeLock($db,$p,$scope);
            if(($state['active_refresh_token']??null)!==$token) throw new DomainException('ANYTOUR_OFFER_REFRESH_NOT_ACTIVE');
            $q=$db->prepare("UPDATE anytour_offer_refreshes SET status='aborted',completed_at=:at WHERE refresh_token=:t AND status='running'"); $q->execute(['at'=>$time,'t'=>$token]); $a=$q->rowCount();
            $q=$db->prepare('UPDATE anytour_offer_scope_state SET active_refresh_token=NULL,revision=revision+1,updated_at=:at WHERE provider=:p AND scope_sha256=:s AND active_refresh_token=:t');
            $q->execute(['at'=>$time,'p'=>$p,'s'=>$scope,'t'=>$token]); if($a!==1||$q->rowCount()!==1) throw new RuntimeException('ANYTOUR_OFFER_REFRESH_ABORT');
            return ['provider'=>$p,'scopeDigest'=>$scope,'expiredUnseen'=>0];
        });
    }

    public static function readScope(PDO $db,string $scope,DateTimeImmutable $now,int $limit=1000):array
    {
        $scope=self::digest($scope,'ANYTOUR_OFFER_SCOPE'); if($limit<1||$limit>5000) throw new InvalidArgumentException('ANYTOUR_OFFER_READ_LIMIT');
        $q=$db->prepare('SELECT anytour_hotel_id,legacy_hotel_id,provider,payload_json,payload_sha256,display_price,currency,observed_at,last_seen_at,expires_at FROM anytour_offers WHERE scope_sha256=:s AND is_active=1 AND expires_at>:now ORDER BY display_price ASC,id ASC LIMIT '.$limit);
        $q->execute(['s'=>$scope,'now'=>self::sqlTime($now)]); $items=[];
        while($r=$q->fetch(PDO::FETCH_ASSOC)){
            $payload=json_decode((string)$r['payload_json'],true,512,JSON_THROW_ON_ERROR);
            if(!is_array($payload)||hash('sha256',self::json($payload))!==$r['payload_sha256']) throw new RuntimeException('ANYTOUR_OFFER_PAYLOAD_INTEGRITY');
            $items[]=['anytourHotelId'=>(int)$r['anytour_hotel_id'],'legacyHotelId'=>(int)$r['legacy_hotel_id'],'provider'=>(string)$r['provider'],'price'=>self::decimal((string)$r['display_price']),'currency'=>(string)$r['currency'],'observedAt'=>self::iso((string)$r['observed_at']),'lastSeenAt'=>self::iso((string)$r['last_seen_at']),'expiresAt'=>self::iso((string)$r['expires_at']),'offer'=>$payload];
        }
        return ['source'=>'anytour-offer-store-v1','scopeDigest'=>$scope,'items'=>$items];
    }

    private static function listingProjection(array $d):array
    {
        $v=self::validateDto($d);
        return [
            'schema_version'=>1,'provider'=>$d['provider'],'operator'=>$d['operator'],'identity'=>$d['identity'],
            'tour'=>$d['tour'],'money'=>$d['money'],
            'listingPriceState'=>$v['listing_price_state'],
            'listingPriceReady'=>$v['final_price_ready'],
            'priceConfirmationRequired'=>$v['listing_price_state']==='search_price_confirmation_required',
            'listingPrice'=>$v['price'],'currency'=>'RUB',
            'quoteState'=>$d['quote_state'],'finalPriceVerified'=>$d['final_price_verified'],
            'quoteEvidenceDigest'=>$d['quote_evidence_digest'],
            'selection_state'=>'refresh_required','booking_enabled'=>false
        ];
    }

    private static function validateDto(array $d):array
    {
        if(!self::keys($d,self::DTO)||($d['schema_version']??null)!==1) throw new InvalidArgumentException('ANYTOUR_OFFER_DTO');
        $provider=self::provider($d['provider']??null); if(!is_int($d['local_hotel_id']??null)||$d['local_hotel_id']<1) throw new InvalidArgumentException('ANYTOUR_OFFER_LEGACY_HOTEL');
        if(!is_array($d['identity']??null)||!self::keys($d['identity'],self::ID)) throw new InvalidArgumentException('ANYTOUR_OFFER_IDENTITY'); foreach(self::ID as $k) self::digest($d['identity'][$k]??null,'ANYTOUR_OFFER_IDENTITY');
        self::operator($d['operator']??null); $tour=$d['tour']??null;
        if(!is_array($tour)||!self::keys($tour,self::TOUR)) throw new InvalidArgumentException('ANYTOUR_OFFER_TOUR'); self::date($tour['checkin']??null,'ANYTOUR_OFFER_CHECKIN');
        if(!is_int($tour['nights']??null)||$tour['nights']<1||$tour['nights']>60) throw new InvalidArgumentException('ANYTOUR_OFFER_NIGHTS'); self::party($tour['party']??null);
        foreach(['meal','room','availability','flight_details'] as $k) if(!is_array($tour[$k]??null)) throw new InvalidArgumentException('ANYTOUR_OFFER_'.strtoupper($k));
        if($tour['placement']!==null&&!is_array($tour['placement'])) throw new InvalidArgumentException('ANYTOUR_OFFER_PLACEMENT'); $observed=self::utc($tour['observed_at']??null,'ANYTOUR_OFFER_OBSERVED');
        $c=$d['context']??null; if(!is_array($c)||!self::keys($c,self::CTX)||!is_int($c['generation'])||$c['generation']<1||!is_int($c['page'])||$c['page']<1||!is_int($c['issued_at'])||!is_int($c['expires_at'])||$c['expires_at']<=$c['issued_at']||$c['expires_at']-$c['issued_at']>900||$c['current_context_verified']!==true) throw new InvalidArgumentException('ANYTOUR_OFFER_CONTEXT');
        if($d['currency']!=='RUB'||$d['selection_state']!=='disabled'||$d['booking_enabled']!==false
            ||!is_array($d['money']??null)||!is_bool($d['finalPriceReady']??null)
            ||!is_bool($d['final_price_verified']??null)) throw new InvalidArgumentException('ANYTOUR_OFFER_READINESS');
        $quote=$d['quote_state']??null;$evidence=$d['quote_evidence_digest']??null;
        $ready=$d['finalPriceReady'];$verified=$d['final_price_verified'];
        if($quote==='verified'){
            if(!$ready||!$verified||!is_string($evidence)||!preg_match('/\A[a-f0-9]{64}\z/D',$evidence)) throw new InvalidArgumentException('ANYTOUR_OFFER_READINESS');
            $price=self::money($d['finalPrice']??null);
            if(self::money($d['price']??null)!==$price) throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_MISMATCH');
            $state='final_verified';
        }elseif($quote==='unknown'){
            if($verified||$evidence!==null) throw new InvalidArgumentException('ANYTOUR_OFFER_READINESS');
            if($ready){
                $price=self::money($d['finalPrice']??null);
                if(self::money($d['price']??null)!==$price) throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_MISMATCH');
                $state='final_ready_estimate';
            }else{
                if($d['finalPrice']!==null) throw new InvalidArgumentException('ANYTOUR_OFFER_READINESS');
                $price=self::money($d['price']??null);
                $search=$d['money']['search_price']??null;
                if(!is_array($search)||($search['currency']??null)!=='RUB'||self::money($search['amount']??null)!==$price) throw new InvalidArgumentException('ANYTOUR_OFFER_CONFIRMATION_PRICE');
                $state='search_price_confirmation_required';
            }
        }else throw new InvalidArgumentException('ANYTOUR_OFFER_READINESS');
        return ['provider'=>$provider,'legacy_hotel_id'=>$d['local_hotel_id'],'price'=>$price,
            'final_price_ready'=>$ready,'final_price_verified'=>$verified,'listing_price_state'=>$state,
            'observed_at'=>self::sqlTime($observed),'context_expires_at'=>gmdate('Y-m-d H:i:s',$c['expires_at'])];
    }

    private static function operator(mixed $o):void
    {
        if(!is_array($o)||!self::keys($o,self::OP)||!is_bool($o['canonical_verified']??null)||!is_bool($o['cross_provider_equivalence_verified']??null)||($o['supplier_code_exposed']??null)!==false) throw new InvalidArgumentException('ANYTOUR_OFFER_OPERATOR');
        foreach(['raw','canonical_name'] as $k) if($o[$k]!==null&&(!is_string($o[$k])||trim($o[$k])===''||strlen($o[$k])>240||preg_match('/[\x00-\x1F\x7F]/',$o[$k]))) throw new InvalidArgumentException('ANYTOUR_OFFER_OPERATOR');
        foreach(['identity_source','filter_status'] as $k) if(!is_string($o[$k]??null)||$o[$k]===''||strlen($o[$k])>64) throw new InvalidArgumentException('ANYTOUR_OFFER_OPERATOR');
    }

    private static function party(mixed $p):void
    {
        if(!is_array($p)||!self::keys($p,self::PARTY)||!is_int($p['adults'])||$p['adults']<1||$p['adults']>9||!is_int($p['children'])||$p['children']<0||$p['children']>9||!is_array($p['child_ages'])||count($p['child_ages'])!==$p['children']) throw new InvalidArgumentException('ANYTOUR_OFFER_PARTY');
        if($p['child_ages']!==[]&&array_keys($p['child_ages'])!==range(0,count($p['child_ages'])-1)) throw new InvalidArgumentException('ANYTOUR_OFFER_PARTY'); foreach($p['child_ages'] as $age) if(!is_int($age)||$age<0||$age>17) throw new InvalidArgumentException('ANYTOUR_OFFER_PARTY');
    }

    private static function bridge(PDO $db,string $provider,string $providerHotelRefDigest,int $legacy,int $own):void
    {
        if(!AnyTourProviderIdentityBridgeV1::allowsOffer($db,$provider,$providerHotelRefDigest,$legacy,$own)) {
            throw new DomainException('ANYTOUR_OFFER_HOTEL_BRIDGE');
        }
    }
    private static function scopeLock(PDO $db,string $p,string $s):array{ $q=$db->prepare('SELECT provider,scope_sha256,active_refresh_token,latest_complete_refresh_token FROM anytour_offer_scope_state WHERE provider=:p AND scope_sha256=:s FOR UPDATE');$q->execute(['p'=>$p,'s'=>$s]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!is_array($r))throw new RuntimeException('ANYTOUR_OFFER_SCOPE_MISSING');return $r; }
    private static function refreshLock(PDO $db,string $t):array{ $q=$db->prepare('SELECT refresh_token,provider,scope_sha256,status,started_at,lease_expires_at,completed_at FROM anytour_offer_refreshes WHERE refresh_token=:t FOR UPDATE');$q->execute(['t'=>$t]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!is_array($r))throw new DomainException('ANYTOUR_OFFER_REFRESH_UNKNOWN');return $r; }
    private static function tx(PDO $db,callable $fn):mixed{ $db->beginTransaction();try{$v=$fn();$db->commit();return $v;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;} }
    private static function outsideTx(PDO $db):void{ if($db->inTransaction())throw new LogicException('ANYTOUR_OFFER_CALLER_TRANSACTION');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    private static function provider(mixed $v):string{ if(!is_string($v)||!in_array($v,self::PROVIDERS,true))throw new InvalidArgumentException('ANYTOUR_OFFER_PROVIDER');return $v; }
    private static function digest(mixed $v,string $e):string{ if(!is_string($v)||!preg_match('/\A[a-f0-9]{64}\z/D',$v))throw new InvalidArgumentException($e);return $v; }
    private static function money(mixed $v):string{ if(!is_string($v)||!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$v)||!preg_match('/[1-9]/',$v))throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE');return self::decimal($v); }
    private static function decimal(string $v):string{ $p=explode('.',$v,2);$f=rtrim($p[1]??'','0');return $p[0].($f===''?'':'.'.$f); }
    private static function date(mixed $v,string $e):DateTimeImmutable{ $d=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('UTC')):false;if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException($e);return $d; }
    private static function utc(mixed $v,string $e):DateTimeImmutable{ $d=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z',$v,new DateTimeZone('UTC')):false;if(!$d||$d->format('Y-m-d\\TH:i:s\\Z')!==$v)throw new InvalidArgumentException($e);return $d; }
    private static function sqlTime(DateTimeImmutable $d):string{return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
    private static function iso(string $v):string{$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v,new DateTimeZone('UTC'));if(!$d)throw new RuntimeException('ANYTOUR_OFFER_TIME_INTEGRITY');return $d->format('Y-m-d\\TH:i:s\\Z');}
    private static function json(mixed $v):string{return json_encode(self::canon($v),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
    private static function canon(mixed $v):mixed{ if(!is_array($v))return $v;$list=$v===[]||array_keys($v)===range(0,count($v)-1);if($list)return array_map([self::class,'canon'],$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=self::canon($x);return $v; }
    private static function keys(array $v,array $keys):bool{return count($v)===count($keys)&&array_diff($keys,array_keys($v))===[]&&array_diff(array_keys($v),$keys)===[];}
}