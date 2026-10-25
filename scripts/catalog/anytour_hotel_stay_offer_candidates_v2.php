<?php
/** Mass read-only review frontier from already-saved CURRENT AnyTour offers. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../v2/data/anytour-hotel-stay-catalog-v2.php';
require_once __DIR__ . '/../../v2/data/anytour-provider-identity-bridge-v1.php';

final class AnyTourHotelStayOfferCandidatesSchemaException extends RuntimeException
{
    public function __construct(public readonly array $missingTables)
    {
        parent::__construct('HOTEL_STAY_OFFER_CANDIDATES_SCHEMA');
    }
}

final class AnyTourHotelStayOfferCandidatesV2
{
    public const LIMIT = 5000;
    private const READ_ROW_CAP = 15000;
    private const TABLES = [
        'anytour_hotels',
        'anytour_hotel_sources',
        'anytour_offers',
        'anytour_offer_scope_state',
        'anytour_hotel_room_concepts_v2',
        'anytour_hotel_meal_concepts_v2',
        'anytour_hotel_stay_mappings_v2',
    ];

    public function __construct(private PDO $pdo) {}

    private static function limit(int $value): int
    {
        if ($value < 1 || $value > self::LIMIT) {
            throw new InvalidArgumentException('HOTEL_STAY_OFFER_CANDIDATES_LIMIT');
        }
        return $value;
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    /** Match the existing offer-store identity digest without changing grouping serialization. */
    private static function identityDigest(array $identity): string
    {
        $canonical=static function(mixed $value) use (&$canonical): mixed {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value,SORT_STRING);
            foreach ($value as $key=>$part) $value[$key]=$canonical($part);
            return $value;
        };
        return hash('sha256',self::json($canonical(['provider'=>'tourvisor','identity'=>$identity])));
    }

    private static function rawLabel(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value) || trim($value)==='' || strlen($value)>512
            || !preg_match('//u',$value) || preg_match('/[\x00-\x1f\x7f]/',$value)) {
            return null;
        }
        return $value;
    }

    private function begin(): void
    {
        if ($this->pdo->inTransaction()
            || $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $this->pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) {
            throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATES_DEDICATED_MYSQL');
        }
        $slots=implode(',',array_fill(0,count(self::TABLES),'?'));
        $stmt=$this->pdo->prepare(
            'SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$slots.') ORDER BY TABLE_NAME'
        );
        $stmt->execute(self::TABLES);
        $tables=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($tables)!==count(self::TABLES)
            || count(array_filter($tables,static fn($engine)=>$engine==='InnoDB'))!==count(self::TABLES)) {
            throw new AnyTourHotelStayOfferCandidatesSchemaException(array_values(array_filter(
                self::TABLES,static fn($table)=>($tables[$table]??null)!=='InnoDB'
            )));
        }
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
    }

    private function currentRows(DateTimeImmutable $now, ?array $anchor=null): array
    {
        $where=$anchor===null?'':' AND o.anytour_hotel_id=:anchor_hotel AND o.legacy_hotel_id=:anchor_legacy
             AND o.provider=:anchor_provider AND o.provider_hotel_ref_digest=:anchor_digest';
        $stmt=$this->pdo->prepare(
            'SELECT o.id,o.anytour_hotel_id,o.legacy_hotel_id,o.provider,o.provider_hotel_ref_digest,
                    o.offer_ref_digest,o.search_ref_digest,o.identity_sha256,o.payload_json,o.payload_sha256,o.last_seen_at
             FROM anytour_offers o
             JOIN anytour_offer_scope_state s
               ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256
              AND s.latest_complete_refresh_token IS NOT NULL
              AND s.latest_complete_refresh_token=o.last_refresh_token
             JOIN anytour_hotels h ON h.id=o.anytour_hotel_id AND h.is_active=1
             WHERE o.is_active=1 AND o.expires_at>:now'.$where.'
             ORDER BY o.last_seen_at DESC,o.id DESC
             LIMIT '.(self::READ_ROW_CAP+($anchor===null?0:1))
        );
        $params=['now'=>$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        if ($anchor!==null) $params += ['anchor_hotel'=>$anchor['hotelId'],'anchor_legacy'=>$anchor['legacyHotelId'],
            'anchor_provider'=>'tourvisor','anchor_digest'=>$anchor['providerHotelRefDigest']];
        $stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($anchor!==null && count($rows)>self::READ_ROW_CAP) {
            throw new RuntimeException('HOTEL_STAY_ANCHOR_CURRENT_ROW_LIMIT');
        }
        return AnyTourProviderIdentityBridgeV1::filterOfferRows($this->pdo,$rows);
    }

    /** A retained tour identifies a hotel only; expiry never grants current price/offer authority. */
    private function anchorScope(string $tourId, DateTimeImmutable $now): array
    {
        $digest=hash('sha256','tourvisor:tour:'.$tourId);
        $stmt=$this->pdo->prepare(
            "SELECT id,anytour_hotel_id,legacy_hotel_id,provider,provider_hotel_ref_digest,
                    offer_ref_digest,search_ref_digest,identity_sha256,payload_json,payload_sha256,last_seen_at,expires_at,is_active
             FROM anytour_offers WHERE provider='tourvisor' AND offer_ref_digest=:offer
             ORDER BY id LIMIT 65"
        );
        $stmt->execute(['offer'=>$digest]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows===[]) throw new RuntimeException('HOTEL_STAY_ANCHOR_MISSING');
        if (count($rows)>64) throw new RuntimeException('HOTEL_STAY_ANCHOR_ROW_LIMIT');
        $identities=[];$facts=[];$expired=0;
        foreach ($rows as $row) {
            $raw=(string)$row['payload_json'];
            if (!is_string($row['payload_sha256']) || !hash_equals($row['payload_sha256'],hash('sha256',$raw))) {
                throw new RuntimeException('HOTEL_STAY_ANCHOR_PAYLOAD_INVALID');
            }
            try { $payload=json_decode($raw,true,128,JSON_THROW_ON_ERROR); }
            catch (Throwable $e) { throw new RuntimeException('HOTEL_STAY_ANCHOR_PAYLOAD_INVALID',0,$e); }
            if (!is_array($payload)) throw new RuntimeException('HOTEL_STAY_ANCHOR_PAYLOAD_INVALID');
            $hotel=filter_var($row['anytour_hotel_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $legacy=filter_var($row['legacy_hotel_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $identity=is_array($payload)?($payload['identity']??null):null;
            $operator=is_array($payload['operator']??null)?self::rawLabel($payload['operator']['raw']??null):null;
            $room=is_array($payload['tour']['room']??null)?self::rawLabel($payload['tour']['room']['raw']??null):null;
            $meal=is_array($payload['tour']['meal']??null)?self::rawLabel($payload['tour']['meal']['raw']??null):null;
            if ($hotel===false || $legacy===false || ($payload['schema_version']??null)!==1 || ($payload['provider']??null)!=='tourvisor'
                || !is_array($identity)
                || ($identity['offer_ref_digest']??null)!==$digest || $row['offer_ref_digest']!==$digest
                || ($identity['search_ref_digest']??null)!==$row['search_ref_digest']
                || !preg_match('/^[a-f0-9]{64}$/D',(string)$row['search_ref_digest'])
                || ($identity['provider_hotel_ref_digest']??null)!==$row['provider_hotel_ref_digest']
                || $row['provider_hotel_ref_digest']!==hash('sha256','tourvisor:hotel:'.$legacy)
                || $row['identity_sha256']!==self::identityDigest($identity)
                || $operator===null || $room===null || $meal===null) {
                throw new RuntimeException('HOTEL_STAY_ANCHOR_PAYLOAD_INVALID');
            }
            $key=self::json([$hotel,$legacy,'tourvisor',$row['provider_hotel_ref_digest']]);
            $identities[$key]=['hotelId'=>$hotel,'legacyHotelId'=>$legacy,
                'provider'=>'tourvisor','providerHotelRefDigest'=>$row['provider_hotel_ref_digest']];
            $isExpired=(string)$row['expires_at']<=$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            if ($isExpired) ++$expired;
            $facts[]=['payloadSha256'=>$row['payload_sha256'],'operatorRaw'=>$operator,'roomRaw'=>$room,'mealRaw'=>$meal,
                'lastSeenAt'=>(string)$row['last_seen_at'],'expiresAt'=>(string)$row['expires_at'],
                'expired'=>$isExpired,'active'=>(int)$row['is_active']===1];
        }
        if (count($identities)!==1) throw new RuntimeException('HOTEL_STAY_ANCHOR_IDENTITY_AMBIGUOUS');
        $anchor=array_values($identities)[0];
        $profile=$this->pdo->prepare('SELECT profile_json,profile_sha256 FROM anytour_hotels WHERE id=? AND is_active=1');
        $profile->execute([$anchor['hotelId']]);$row=$profile->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !hash_equals((string)$row['profile_sha256'],hash('sha256',(string)$row['profile_json']))) {
            throw new RuntimeException('HOTEL_STAY_ANCHOR_IDENTITY_UNAVAILABLE');
        }
        try { $hotelProfile=json_decode((string)$row['profile_json'],true,128,JSON_THROW_ON_ERROR); }
        catch (Throwable $e) { throw new RuntimeException('HOTEL_STAY_ANCHOR_IDENTITY_UNAVAILABLE',0,$e); }
        if (!is_array($hotelProfile) || self::rawLabel($hotelProfile['name']??null)===null
            || !AnyTourProviderIdentityBridgeV1::allowsOffer($this->pdo,'tourvisor',$anchor['providerHotelRefDigest'],
                $anchor['legacyHotelId'],$anchor['hotelId'])) {
            throw new RuntimeException('HOTEL_STAY_ANCHOR_IDENTITY_UNAVAILABLE');
        }
        return $anchor+['offerRefDigest'=>$digest,'retainedRows'=>count($rows),'expiredRows'=>$expired,
            'identityOnly'=>true,'profileSha256'=>$row['profile_sha256'],'retainedFacts'=>$facts];
    }

    private static function statusCounts(array $cohorts): array
    {
        $counts=[
            'room'=>['accepted'=>0,'unmapped'=>0,'pending'=>0,'rejected'=>0,'conflict'=>0,'target-unavailable'=>0,'missing'=>0,'other'=>0],
            'meal'=>['accepted'=>0,'unmapped'=>0,'pending'=>0,'rejected'=>0,'conflict'=>0,'target-unavailable'=>0,'missing'=>0,'other'=>0],
        ];
        foreach ($cohorts as $cohort) {
            foreach (['room','meal'] as $kind) {
                $status=(string)($cohort['match'][$kind]['status'] ?? 'other');
                if (!array_key_exists($status,$counts[$kind])) $status='other';
                ++$counts[$kind][$status];
            }
        }
        return $counts;
    }

    private static function reviewState(array $match): string
    {
        $statuses=[
            (string)($match['room']['status'] ?? 'missing'),
            (string)($match['meal']['status'] ?? 'missing'),
        ];
        if (in_array('conflict',$statuses,true) || in_array('target-unavailable',$statuses,true)) return 'held';
        if (in_array('pending',$statuses,true) || in_array('unmapped',$statuses,true)) return 'needs-review';
        if (in_array('rejected',$statuses,true)) return 'existing-negative';
        return 'resolved-or-missing';
    }

    public function collect(int $limit=1000, ?DateTimeImmutable $now=null): array
    {
        return $this->collectInventory(self::limit($limit),$now,null);
    }

    public function collectForTourvisorAnchor(string $tourId, int $limit=50, ?DateTimeImmutable $now=null): array
    {
        if (!preg_match('/^[1-9][0-9]{0,23}$/D',$tourId) || $limit<1 || $limit>50) {
            throw new InvalidArgumentException('HOTEL_STAY_ANCHOR_SCOPE');
        }
        return $this->collectInventory($limit,$now,$tourId);
    }

    private function collectInventory(int $limit, ?DateTimeImmutable $now, ?string $tourId): array
    {
        $limit=self::limit($limit);
        $now=$now ?? new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $this->begin();
        try {
            $anchor=$tourId===null?null:$this->anchorScope($tourId,$now);
            $rows=$this->currentRows($now,$anchor);
            $rawCurrentCount=count($rows);
            $groups=[];
            $missingOperator=0;$invalidPayload=0;$missingStayFacts=0;
            foreach ($rows as $row) {
                $raw=(string)$row['payload_json'];
                if (!is_string($row['payload_sha256'] ?? null)
                    || !hash_equals((string)$row['payload_sha256'],hash('sha256',$raw))) {
                    ++$invalidPayload;
                    throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY');
                }
                try { $payload=json_decode($raw,true,128,JSON_THROW_ON_ERROR); }
                catch (Throwable $e) {
                    throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY',0,$e);
                }
                if (!is_array($payload)
                    || ($payload['provider'] ?? null)!==$row['provider']
                    || !is_array($payload['identity'] ?? null)
                    || ($payload['identity']['provider_hotel_ref_digest'] ?? null)!==$row['provider_hotel_ref_digest']) {
                    ++$invalidPayload;
                    throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY');
                }
                if ($anchor!==null && (($payload['schema_version']??null)!==1
                    || ($payload['identity']['offer_ref_digest']??null)!==$row['offer_ref_digest']
                    || ($payload['identity']['search_ref_digest']??null)!==$row['search_ref_digest']
                    || $row['identity_sha256']!==self::identityDigest($payload['identity']))) {
                    throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY');
                }

                $operator=is_array($payload['operator'] ?? null)?($payload['operator']['raw'] ?? null):null;
                if ($operator===null) { ++$missingOperator; continue; }
                $operator=self::rawLabel($operator);
                if ($operator===null) { ++$missingOperator; continue; }

                $tour=$payload['tour'] ?? null;
                if (!is_array($tour)) { ++$missingStayFacts; continue; }
                $room=self::rawLabel(is_array($tour['room'] ?? null)?($tour['room']['raw'] ?? null):null);
                $meal=self::rawLabel(is_array($tour['meal'] ?? null)?($tour['meal']['raw'] ?? null):null);
                if ($room===null && $meal===null) { ++$missingStayFacts; continue; }

                $hotelId=(int)$row['anytour_hotel_id'];
                $legacyId=(int)$row['legacy_hotel_id'];
                $provider=(string)$row['provider'];
                $digest=(string)$row['provider_hotel_ref_digest'];
                $scope=AnyTourHotelStayCatalogV2::offerScope($provider,$legacyId,$digest,$operator);
                if ($scope===null) { ++$missingOperator; continue; }

                $key=self::json([$hotelId,$legacyId,$provider,$digest,$operator,$room,$meal]);
                if (!isset($groups[$key])) {
                    $groups[$key]=[
                        'hotelId'=>$hotelId,
                        'legacyHotelId'=>$legacyId,
                        'provider'=>$provider,
                        'providerHotelRefDigest'=>$digest,
                        'operatorRaw'=>$operator,
                        'scope'=>$scope,
                        'roomRaw'=>$room,
                        'mealRaw'=>$meal,
                        'observedCount'=>0,
                        'lastSeenAt'=>(string)$row['last_seen_at'],
                    ];
                }
                ++$groups[$key]['observedCount'];
                if ((string)$row['last_seen_at']>$groups[$key]['lastSeenAt']) {
                    $groups[$key]['lastSeenAt']=(string)$row['last_seen_at'];
                }
            }

            $cohorts=array_values($groups);
            usort($cohorts,static function(array $a,array $b): int {
                return $b['observedCount']<=>$a['observedCount']
                    ?: strcmp($b['lastSeenAt'],$a['lastSeenAt'])
                    ?: $a['hotelId']<=>$b['hotelId']
                    ?: strcmp($a['provider'],$b['provider'])
                    ?: strcmp((string)$a['roomRaw'],(string)$b['roomRaw'])
                    ?: strcmp((string)$a['mealRaw'],(string)$b['mealRaw']);
            });
            $totalExactCohorts=count($cohorts);
            $cohorts=array_slice($cohorts,0,$limit);

            $catalog=new AnyTourHotelStayCatalogV2($this->pdo);
            $requests=[];
            foreach ($cohorts as $cohort) {
                $requests[]=[
                    'anytourHotelId'=>$cohort['hotelId'],
                    'legacyHotelId'=>$cohort['legacyHotelId'],
                    'provider'=>$cohort['provider'],
                    'providerHotelRefDigest'=>$cohort['providerHotelRefDigest'],
                    'operatorRaw'=>$cohort['operatorRaw'],
                    'roomRaw'=>$cohort['roomRaw'],
                    'mealRaw'=>$cohort['mealRaw'],
                ];
            }
            $matches=[];
            foreach (array_chunk($requests,AnyTourHotelStayCatalogV2::OFFER_BATCH_LIMIT) as $chunk) {
                array_push($matches,...$catalog->resolveOfferFactsBatch($chunk));
            }
            if (count($matches)!==count($cohorts)) {
                throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATE_MATCH_COUNT');
            }
            foreach ($cohorts as $i=>&$cohort) {
                $cohort['match']=$matches[$i];
                $cohort['reviewState']=self::reviewState($matches[$i]);
            }
            unset($cohort);

            $hotelIds=[];
            if ($anchor!==null) $hotelIds[$anchor['hotelId']]=true;
            foreach ($cohorts as $cohort) $hotelIds[$cohort['hotelId']]=true;
            $concepts=[];
            foreach (array_chunk(array_keys($hotelIds),AnyTourHotelStayCatalogV2::HOTEL_BATCH_LIMIT) as $chunk) {
                $rooms=$catalog->roomsForHotels($chunk);
                $meals=$catalog->mealsForHotels($chunk);
                foreach ($chunk as $hotelId) {
                    $concepts[(int)$hotelId]=[
                        'hotelId'=>(int)$hotelId,
                        'rooms'=>$rooms[(int)$hotelId] ?? [],
                        'meals'=>$meals[(int)$hotelId] ?? [],
                    ];
                }
            }
            ksort($concepts,SORT_NUMERIC);

            $statusCounts=self::statusCounts($cohorts);
            $reviewNeeded=count(array_filter($cohorts,static fn($row)=>$row['reviewState']==='needs-review'));
            $held=count(array_filter($cohorts,static fn($row)=>$row['reviewState']==='held'));
            $negative=count(array_filter($cohorts,static fn($row)=>$row['reviewState']==='existing-negative'));

            $this->pdo->commit();
            $result=[
                'status'=>'read_only_current_offer_review_inventory',
                'source'=>'anytour-current-complete-offer-snapshots',
                'generatedAt'=>$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                'currentIdentityValidatedOffers'=>$rawCurrentCount,
                'totalExactCohorts'=>$totalExactCohorts,
                'returnedCohorts'=>count($cohorts),
                'omittedMissingOperatorOffers'=>$missingOperator,
                'omittedMissingStayFactOffers'=>$missingStayFacts,
                'invalidPayloads'=>$invalidPayload,
                'reviewNeededCohorts'=>$reviewNeeded,
                'heldCohorts'=>$held,
                'existingNegativeCohorts'=>$negative,
                'statusCounts'=>$statusCounts,
                'cohorts'=>$cohorts,
                'hotelConcepts'=>array_values($concepts),
                'writes'=>0,
                'supplierCalls'=>0,
                'automaticAccepts'=>0,
            ];
            if ($anchor!==null) $result['anchorEvidence']=$anchor;
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    // SOURCE_FACTS_ADDITIVE_BEGIN: historical methods above remain byte-for-byte unchanged.
    public function directPrivilegeProof(): array
    {
        $proof=['state'=>'unavailable','scope'=>'direct_global_or_schema','CREATE'=>null,'REFERENCES'=>null,'SELECT'=>null,'TRIGGER'=>null,'allRequired'=>null];
        try {
            $user=(string)$this->pdo->query('SELECT CURRENT_USER()')->fetchColumn();$at=strrpos($user,'@');
            if ($at===false||strlen($user)>288) return $proof;
            $grantee="'".str_replace("'","''",substr($user,0,$at))."'@'".str_replace("'","''",substr($user,$at+1))."'";
            $q=$this->pdo->prepare("SELECT PRIVILEGE_TYPE FROM information_schema.USER_PRIVILEGES WHERE BINARY GRANTEE=BINARY ?
                AND PRIVILEGE_TYPE IN ('CREATE','REFERENCES','TRIGGER','SELECT')
                UNION SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES
                WHERE BINARY GRANTEE=BINARY ? AND BINARY TABLE_SCHEMA=BINARY DATABASE()
                AND PRIVILEGE_TYPE IN ('CREATE','REFERENCES','TRIGGER','SELECT') LIMIT 9");
            $q->execute([$grantee,$grantee]);$found=$q->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($found)||count($found)>4||count(array_unique($found))!==count($found)
                ||array_diff($found,['CREATE','REFERENCES','TRIGGER','SELECT'])!==[]) return $proof;
            $proof['state']='direct_metadata_observed';
            foreach (['CREATE','REFERENCES','SELECT','TRIGGER'] as $key) $proof[$key]=in_array($key,$found,true);
            $proof['allRequired']=$proof['CREATE']&&$proof['REFERENCES']&&$proof['SELECT']&&$proof['TRIGGER'];
        } catch (Throwable $ignored) {} // No account, raw grant, SQL or error is exported.
        return $proof;
    }

    /** Independent raw source dossier: no V2 stay table lookup or normalization decision. */
    public function sourceOnly(string $tourId,?DateTimeImmutable $now=null): array
    {
        if (!preg_match('/^[1-9][0-9]{0,23}$/D',$tourId)) throw new InvalidArgumentException('HOTEL_STAY_ANCHOR_SCOPE');
        if ($this->pdo->inTransaction()||$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            ||$this->pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) throw new RuntimeException('HOTEL_STAY_SOURCE_CALLER_TRANSACTION');
        $tables=['anytour_hotels','anytour_hotel_sources','anytour_offers','anytour_offer_scope_state'];
        $q=$this->pdo->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
            AND TABLE_NAME IN (?,?,?,?) ORDER BY TABLE_NAME');$q->execute($tables);$present=$q->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($present)!==4||count(array_filter($present,static fn($engine)=>$engine==='InnoDB'))!==4)
            throw new AnyTourHotelStayOfferCandidatesSchemaException(array_values(array_filter($tables,static fn($name)=>($present[$name]??null)!=='InnoDB')));
        $now=$now??new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->pdo->exec('SET TRANSACTION READ ONLY');$this->pdo->beginTransaction();
        try {
            $anchor=$this->anchorScope($tourId,$now);$facts=[];
            foreach ($anchor['retainedFacts'] as $fact) {
                if (strlen($fact['operatorRaw'])>240) throw new RuntimeException('HOTEL_STAY_ANCHOR_PAYLOAD_INVALID');
                $facts[]=['evidenceState'=>'retained_identity_only','hotelId'=>$anchor['hotelId'],'legacyHotelId'=>$anchor['legacyHotelId'],
                    'provider'=>$anchor['provider'],'providerHotelRefDigest'=>$anchor['providerHotelRefDigest'],'operatorRaw'=>$fact['operatorRaw'],
                    'scope'=>AnyTourHotelStayCatalogV2::offerScope($anchor['provider'],$anchor['legacyHotelId'],$anchor['providerHotelRefDigest'],$fact['operatorRaw']),
                    'roomRaw'=>$fact['roomRaw'],'mealRaw'=>$fact['mealRaw'],'payloadSha256'=>$fact['payloadSha256'],
                    'lastSeenAt'=>$fact['lastSeenAt'],'expiresAt'=>$fact['expiresAt'],'expired'=>$fact['expired'],'active'=>$fact['active']];
            }
            unset($anchor['retainedFacts']);$rows=$this->currentRows($now,$anchor);$groups=[];$missingOperator=0;$missingStay=0;$current=0;
            foreach ($rows as $row) {
                $raw=(string)$row['payload_json'];
                if (!is_string($row['payload_sha256']??null)||!hash_equals($row['payload_sha256'],hash('sha256',$raw))) throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY');
                try {$payload=json_decode($raw,true,128,JSON_THROW_ON_ERROR);} catch (Throwable $e) {throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY');}
                if (!is_array($payload)||($payload['schema_version']??null)!==1||($payload['provider']??null)!==$row['provider']
                    ||!is_array($payload['identity']??null)
                    ||($payload['identity']['provider_hotel_ref_digest']??null)!==$row['provider_hotel_ref_digest']
                    ||($payload['identity']['offer_ref_digest']??null)!==$row['offer_ref_digest']
                    ||($payload['identity']['search_ref_digest']??null)!==$row['search_ref_digest']
                    ||$row['identity_sha256']!==self::identityDigest($payload['identity'])) throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY');
                $operator=is_array($payload['operator']??null)?self::rawLabel($payload['operator']['raw']??null):null;
                if ($operator===null||strlen($operator)>240) {++$missingOperator;continue;}
                $room=is_array($payload['tour']['room']??null)?self::rawLabel($payload['tour']['room']['raw']??null):null;
                $meal=is_array($payload['tour']['meal']??null)?self::rawLabel($payload['tour']['meal']['raw']??null):null;
                if ($room===null&&$meal===null) {++$missingStay;continue;}
                $base=['hotelId'=>(int)$row['anytour_hotel_id'],'legacyHotelId'=>(int)$row['legacy_hotel_id'],'provider'=>(string)$row['provider'],
                    'providerHotelRefDigest'=>(string)$row['provider_hotel_ref_digest'],'operatorRaw'=>$operator,
                    'scope'=>AnyTourHotelStayCatalogV2::offerScope((string)$row['provider'],(int)$row['legacy_hotel_id'],(string)$row['provider_hotel_ref_digest'],$operator)];
                $facts[]=['evidenceState'=>'current_complete']+$base+['roomRaw'=>$room,'mealRaw'=>$meal,'payloadSha256'=>$row['payload_sha256'],
                    'lastSeenAt'=>(string)$row['last_seen_at'],'expiresAt'=>null,'expired'=>false,'active'=>true];++$current;
                $key=self::json([$base,$room,$meal]);
                if (!isset($groups[$key])) $groups[$key]=$base+['roomRaw'=>$room,'mealRaw'=>$meal,'observedCount'=>0,'lastSeenAt'=>(string)$row['last_seen_at']];
                ++$groups[$key]['observedCount'];$groups[$key]['lastSeenAt']=max($groups[$key]['lastSeenAt'],(string)$row['last_seen_at']);
                if (count($groups)>50) throw new RuntimeException('HOTEL_STAY_SOURCE_COHORT_LIMIT');
            }
            $cohorts=array_values($groups);usort($cohorts,static fn($a,$b)=>strcmp(self::json($a),self::json($b)));
            $drafts=[];
            foreach ($facts as $index=>$fact) foreach (['room'=>'roomRaw','meal'=>'mealRaw'] as $kind=>$rawKey) {
                if ($fact[$rawKey]===null) continue;
                $key=hash('sha256',self::sourceJson([$fact['hotelId'],$fact['legacyHotelId'],$fact['provider'],$fact['providerHotelRefDigest'],$fact['operatorRaw'],$kind,$fact[$rawKey]]));
                if (!isset($drafts[$key])) $drafts[$key]=['draftId'=>$key,'hotelId'=>$fact['hotelId'],'legacyHotelId'=>$fact['legacyHotelId'],
                    'provider'=>$fact['provider'],'providerHotelRefDigest'=>$fact['providerHotelRefDigest'],'operatorRaw'=>$fact['operatorRaw'],'scope'=>$fact['scope'],
                    'kind'=>$kind,'raw'=>$fact[$rawKey],'evidenceStates'=>[],'sourceFactIndexes'=>[],'canonicalId'=>null,'targetLocalKey'=>null,'nameRu'=>null,
                    'canonicalFacts'=>null,'status'=>'manual_review_required'];
                $drafts[$key]['sourceFactIndexes'][]=$index;
                if (!in_array($fact['evidenceState'],$drafts[$key]['evidenceStates'],true)) $drafts[$key]['evidenceStates'][]=$fact['evidenceState'];
            }
            ksort($drafts,SORT_STRING);foreach ($drafts as &$draft) sort($draft['evidenceStates'],SORT_STRING);unset($draft);
            $result=['anchorEvidence'=>$anchor,'sourceFacts'=>$facts,'currentCohorts'=>$cohorts,'draftConcepts'=>array_values($drafts),
                'currentValidatedOffers'=>count($rows),'currentFactRows'=>$current,'omittedMissingOperatorOffers'=>$missingOperator,'omittedMissingStayFactOffers'=>$missingStay];
            if (strlen(self::sourceJson($result))>2097152-8192) throw new RuntimeException('HOTEL_STAY_SOURCE_SIZE_LIMIT');
            $this->pdo->commit();return $result;
        } catch (Throwable $e) {if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $e;}
    }

    private static function sourceJson(mixed $value): string
    {
        $canonical=static function(mixed $item) use (&$canonical): mixed {
            if (!is_array($item)) return $item;
            if (!array_is_list($item)) ksort($item,SORT_STRING);
            foreach ($item as $key=>$part) $item[$key]=$canonical($part);
            return $item;
        };
        return json_encode($canonical($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    // SOURCE_FACTS_ADDITIVE_END

}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))===__FILE__) {
    $dsn=(string)(getenv('ANYTOUR_HOTEL_STAY_OFFER_CANDIDATES_DSN') ?: '');
    $user=(string)(getenv('ANYTOUR_HOTEL_STAY_OFFER_CANDIDATES_USER') ?: '');
    $password=(string)(getenv('ANYTOUR_HOTEL_STAY_OFFER_CANDIDATES_PASSWORD') ?: '');
    if ($dsn==='') throw new RuntimeException('ANYTOUR_HOTEL_STAY_OFFER_CANDIDATES_DSN required');
    $limit=1000;
    foreach (array_slice($argv,1) as $arg) {
        if (preg_match('/^--limit=([0-9]+)$/D',$arg,$m)) $limit=(int)$m[1];
        else throw new InvalidArgumentException('Usage: anytour_hotel_stay_offer_candidates_v2.php [--limit=1..5000]');
    }
    $pdo=new PDO($dsn,$user,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_STRINGIFY_FETCHES=>false,
    ]);
    echo json_encode(
        (new AnyTourHotelStayOfferCandidatesV2($pdo))->collect($limit),
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR
    ) . PHP_EOL;
}
