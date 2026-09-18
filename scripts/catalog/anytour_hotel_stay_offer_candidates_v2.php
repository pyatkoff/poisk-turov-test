<?php
/** Mass read-only review frontier from already-saved CURRENT AnyTour offers. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../v2/data/anytour-hotel-stay-catalog-v2.php';
require_once __DIR__ . '/../../v2/data/anytour-provider-identity-bridge-v1.php';

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
            throw new RuntimeException('HOTEL_STAY_OFFER_CANDIDATES_SCHEMA');
        }
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
    }

    private function currentRows(DateTimeImmutable $now): array
    {
        $stmt=$this->pdo->prepare(
            'SELECT o.id,o.anytour_hotel_id,o.legacy_hotel_id,o.provider,o.provider_hotel_ref_digest,
                    o.payload_json,o.payload_sha256,o.last_seen_at
             FROM anytour_offers o
             JOIN anytour_offer_scope_state s
               ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256
              AND s.latest_complete_refresh_token IS NOT NULL
              AND s.latest_complete_refresh_token=o.last_refresh_token
             JOIN anytour_hotels h ON h.id=o.anytour_hotel_id AND h.is_active=1
             WHERE o.is_active=1 AND o.final_price_ready=1 AND o.expires_at>:now
             ORDER BY o.last_seen_at DESC,o.id DESC
             LIMIT '.self::READ_ROW_CAP
        );
        $stmt->execute(['now'=>$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        return AnyTourProviderIdentityBridgeV1::filterOfferRows($this->pdo,$rows);
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
        $limit=self::limit($limit);
        $now=$now ?? new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $this->begin();
        try {
            $rows=$this->currentRows($now);
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
            return [
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
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
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
