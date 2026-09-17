<?php
/**
 * LOCAL-owned acquisition queue for first-party AnyTour hotel presentation gaps.
 *
 * The candidate universe is deliberately restricted to active AnyTour profiles
 * that are still identity-only. Historical legacy_catalog keys are used only as
 * exact Tourvisor content-source IDs, never as current identity authority.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../v2/data/db-v1.php';
require_once __DIR__ . '/../../v2/data/tourvisor-client-v1.php';
require_once __DIR__ . '/../../v2/data/hotel-details-v1.php';

final class AnyTourIdentityOnlyHotelContentV1
{
    public const MAX_BATCH = 3000;
    private PDO $pdo;
    private Closure $fetchHotel;
    private bool $liveProvider;
    private int $providerAttempts = 0;

    public function __construct(PDO $pdo, ?callable $fetchHotel = null)
    {
        $this->pdo = $pdo;
        $this->liveProvider = $fetchHotel === null;
        $this->fetchHotel = $fetchHotel === null
            ? static fn(int $hotelId): array => v2_data_tv_get('/hotels/' . $hotelId)
            : Closure::fromCallable($fetchHotel);
    }

    public static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function positiveInt(mixed $value, string $error): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
            throw new InvalidArgumentException($error);
        }
        $n = (int)$value;
        if ($n < 1) throw new InvalidArgumentException($error);
        return $n;
    }

    private static function limit(mixed $value): int
    {
        $n = self::positiveInt($value, 'ANYTOUR_IDENTITY_CONTENT_LIMIT');
        if ($n > self::MAX_BATCH) throw new InvalidArgumentException('ANYTOUR_IDENTITY_CONTENT_LIMIT');
        return $n;
    }

    private static function utc(mixed $value, string $error): string
    {
        if (!is_string($value) || trim($value) === '') throw new InvalidArgumentException($error);
        try { $time = new DateTimeImmutable($value); }
        catch (Throwable) { throw new InvalidArgumentException($error); }
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function sha(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) {
            throw new InvalidArgumentException('ANYTOUR_IDENTITY_CONTENT_PLAN_SHA');
        }
        return $value;
    }

    private function assertSchema(): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('ANYTOUR_IDENTITY_CONTENT_MYSQL_REQUIRED');
        }
        $tables = ['anytour_hotels','anytour_hotel_sources','catalog_hotels','catalog_hotel_details','tour_price_observations'];
        $quoted = implode(',', array_fill(0, count($tables), '?'));
        $stmt = $this->pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($quoted)");
        $stmt->execute($tables);
        $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        sort($found); sort($tables);
        if ($found !== $tables) throw new RuntimeException('ANYTOUR_IDENTITY_CONTENT_SCHEMA');
    }

    /** Same deterministic identity-only contract as the verified coverage census. */
    private function rankedRows(string $retryBefore, string $demandThrough): array
    {
        $sql = "SELECT a.id AS anytour_hotel_id,
                       CAST(CONVERT(s.external_key USING ascii) AS UNSIGNED) AS hotel_id,
                       JSON_UNQUOTE(JSON_EXTRACT(a.profile_json,'$.name')) AS canonical_name,
                       c.name AS catalog_name,
                       d.status AS detail_status,d.fetched_at AS detail_fetched_at,
                       COALESCE(x.user_search_count,0) AS user_search_count,
                       COALESCE(x.observation_count,0) AS observation_count,
                       x.last_seen_at AS demand_last_seen_at
                FROM anytour_hotels a
                JOIN anytour_hotel_sources s
                  ON s.anytour_hotel_id=a.id AND s.namespace='legacy_catalog'
                 AND CONVERT(s.external_key USING ascii) REGEXP '^[1-9][0-9]*$'
                JOIN catalog_hotels c
                  ON c.id=CAST(CONVERT(s.external_key USING ascii) AS UNSIGNED)
                LEFT JOIN catalog_hotel_details d
                  ON d.hotel_id=CAST(CONVERT(s.external_key USING ascii) AS UNSIGNED)
                LEFT JOIN (
                    SELECT hotel_id,
                           SUM(source='user_search') AS user_search_count,
                           COUNT(*) AS observation_count,
                           MAX(observed_at) AS last_seen_at
                    FROM tour_price_observations
                    WHERE observed_at<=:through
                    GROUP BY hotel_id
                ) x ON x.hotel_id=CAST(CONVERT(s.external_key USING ascii) AS UNSIGNED)
                WHERE a.is_active=1 AND JSON_VALID(a.profile_json)=1
                  AND COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.profile_json,'$.name'))),'null'),'')<>''
                  AND COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.profile_json,'$.country.name'))),'null'),'')<>''
                  AND COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.profile_json,'$.description'))),'null'),'')=''
                  AND COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.profile_json,'$.primaryImage'))),'null'),'')=''
                  AND COALESCE(JSON_LENGTH(JSON_EXTRACT(a.profile_json,'$.images')),0)=0
                  AND COALESCE(JSON_LENGTH(JSON_EXTRACT(a.profile_json,'$.hotelInformation.infrastructure')),0)=0
                  AND COALESCE(JSON_LENGTH(JSON_EXTRACT(a.profile_json,'$.hotelInformation.services')),0)=0
                  AND COALESCE(JSON_LENGTH(JSON_EXTRACT(a.profile_json,'$.traits')),0)=0
                  AND (d.hotel_id IS NULL OR (d.status<>'generic_product' AND d.fetched_at<:retry_before))
                ORDER BY user_search_count DESC,observation_count DESC,
                         (x.last_seen_at IS NULL) ASC,x.last_seen_at DESC,a.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['through'=>$demandThrough,'retry_before'=>$retryBefore]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 250000) throw new RuntimeException('ANYTOUR_IDENTITY_CONTENT_COHORT_BOUND');
        return $rows;
    }

    private function snapshot(int $limit, string $retryBefore, string $demandThrough): array
    {
        $rows = $this->rankedRows($retryBefore, $demandThrough);
        $selected = [];
        $genericSkipped = 0;
        $seenTv = [];
        $seenOwn = [];
        foreach ($rows as $row) {
            $hotelId = self::positiveInt($row['hotel_id'] ?? null, 'ANYTOUR_IDENTITY_CONTENT_TV_ID');
            $ownId = self::positiveInt($row['anytour_hotel_id'] ?? null, 'ANYTOUR_IDENTITY_CONTENT_OWN_ID');
            if (isset($seenTv[$hotelId]) || isset($seenOwn[$ownId])) throw new RuntimeException('ANYTOUR_IDENTITY_CONTENT_DUPLICATE_SOURCE');
            $seenTv[$hotelId] = true; $seenOwn[$ownId] = true;
            if (v2_hotel_detail_is_generic_product_name($row['canonical_name'] ?? null)
                || v2_hotel_detail_is_generic_product_name($row['catalog_name'] ?? null)) {
                ++$genericSkipped;
                continue;
            }
            $selected[] = [
                'anytourHotelId'=>$ownId,
                'tourvisorHotelId'=>$hotelId,
                'userSearches'=>(int)$row['user_search_count'],
                'observations'=>(int)$row['observation_count'],
                'lastSeenAt'=>$row['demand_last_seen_at'] === null ? null : (string)$row['demand_last_seen_at'],
                'previousDetailStatus'=>$row['detail_status'] === null ? null : (string)$row['detail_status'],
                'previousDetailFetchedAt'=>$row['detail_fetched_at'] === null ? null : (string)$row['detail_fetched_at'],
            ];
            if (count($selected) >= $limit) break;
        }
        $core = [
            'schemaVersion'=>1,'retryBefore'=>$retryBefore,'demandThrough'=>$demandThrough,
            'limit'=>$limit,'eligibleIdentityOnly'=>count($rows),'genericPreSkipped'=>$genericSkipped,
            'selected'=>$selected,'supplierCalls'=>0,'writes'=>0,
        ];
        $core['planSha256'] = hash('sha256', self::json($core));
        return $core;
    }

    public function plan(mixed $limit, mixed $retryBefore, mixed $demandThrough): array
    {
        $limit = self::limit($limit);
        $retryBefore = self::utc($retryBefore, 'ANYTOUR_IDENTITY_CONTENT_RETRY_BEFORE');
        $demandThrough = self::utc($demandThrough, 'ANYTOUR_IDENTITY_CONTENT_DEMAND_THROUGH');
        $this->assertSchema();
        if ($this->pdo->inTransaction()) throw new RuntimeException('ANYTOUR_IDENTITY_CONTENT_CALLER_TRANSACTION');
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
        try {
            $snapshot = $this->snapshot($limit, $retryBefore, $demandThrough);
            $this->pdo->commit();
            return ['status'=>'prepared_read_only'] + $snapshot;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function upsertTerminalStatus(int $hotelId, string $status, string $message, string $now): void
    {
        $message = mb_substr(str_replace(["\r","\n"], ' ', $message), 0, 1000);
        $stmt = $this->pdo->prepare("INSERT INTO catalog_hotel_details (hotel_id,status,fetched_at,last_error)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE
              status=CASE WHEN VALUES(status) IN ('not_found','generic_product') THEN VALUES(status) ELSE status END,
              fetched_at=CASE WHEN VALUES(status) IN ('not_found','generic_product') THEN VALUES(fetched_at) ELSE fetched_at END,
              last_error=VALUES(last_error)");
        $stmt->execute([$hotelId,$status,$now,$message]);
    }

    private function storeSuccess(int $hotelId, array $hotel, array $detail, string $fetchedAt): void
    {
        $rawJson = self::json($hotel);
        $save = $this->pdo->prepare("INSERT INTO catalog_hotel_details (
            hotel_id,status,source_hash,country_id,region_id,subregion_id,name,category,rating,hotel_type,
            description,address,place,phone,site,build_info,repair_info,square_info,latitude,longitude,
            primary_image_url,images_json,infrastructure_json,meals_json,services_json,room_types,raw_json,fetched_at,last_error
        ) VALUES (
            :hotel_id,'success',:source_hash,:country_id,:region_id,:subregion_id,:name,:category,:rating,:hotel_type,
            :description,:address,:place,:phone,:site,:build_info,:repair_info,:square_info,:latitude,:longitude,
            :primary_image_url,:images_json,:infrastructure_json,:meals_json,:services_json,:room_types,:raw_json,:fetched_at,NULL
        ) ON DUPLICATE KEY UPDATE
            status='success',source_hash=VALUES(source_hash),country_id=VALUES(country_id),region_id=VALUES(region_id),
            subregion_id=VALUES(subregion_id),name=VALUES(name),category=VALUES(category),rating=VALUES(rating),hotel_type=VALUES(hotel_type),
            description=VALUES(description),address=VALUES(address),place=VALUES(place),phone=VALUES(phone),site=VALUES(site),
            build_info=VALUES(build_info),repair_info=VALUES(repair_info),square_info=VALUES(square_info),latitude=VALUES(latitude),longitude=VALUES(longitude),
            primary_image_url=VALUES(primary_image_url),images_json=VALUES(images_json),infrastructure_json=VALUES(infrastructure_json),
            meals_json=VALUES(meals_json),services_json=VALUES(services_json),room_types=VALUES(room_types),raw_json=VALUES(raw_json),
            fetched_at=VALUES(fetched_at),last_error=NULL");
        $save->execute([
            'hotel_id'=>$hotelId,'source_hash'=>hash('sha256',$rawJson),
            'country_id'=>$detail['country_id'],'region_id'=>$detail['region_id'],'subregion_id'=>$detail['subregion_id'],
            'name'=>$detail['name'],'category'=>$detail['category'],'rating'=>$detail['rating'],'hotel_type'=>$detail['hotel_type'],
            'description'=>$detail['description'],'address'=>$detail['address'],'place'=>$detail['place'],'phone'=>$detail['phone'],'site'=>$detail['site'],
            'build_info'=>$detail['build'],'repair_info'=>$detail['repair'],'square_info'=>$detail['square'],
            'latitude'=>$detail['latitude'],'longitude'=>$detail['longitude'],'primary_image_url'=>$detail['primary_image_url'],
            'images_json'=>$detail['images_json'],'infrastructure_json'=>$detail['infrastructure_json'],'meals_json'=>$detail['meals_json'],
            'services_json'=>$detail['services_json'],'room_types'=>$detail['room_types'],'raw_json'=>$rawJson,'fetched_at'=>$fetchedAt,
        ]);
        if ($detail['primary_image_url'] !== null) {
            $image = $this->pdo->prepare('UPDATE catalog_hotels SET image_updated_at=IF(primary_image_url IS NULL,?,image_updated_at), primary_image_url=COALESCE(primary_image_url,?), synced_at=? WHERE id=?');
            $image->execute([$fetchedAt,$detail['primary_image_url'],$fetchedAt,$hotelId]);
        }
    }

    public function hydrate(mixed $limit, mixed $retryBefore, mixed $demandThrough, mixed $expectedPlanSha,
        mixed $maxAttempts, mixed $httpBudget, mixed $minIntervalMs): array
    {
        $limit = self::limit($limit);
        $retryBefore = self::utc($retryBefore, 'ANYTOUR_IDENTITY_CONTENT_RETRY_BEFORE');
        $demandThrough = self::utc($demandThrough, 'ANYTOUR_IDENTITY_CONTENT_DEMAND_THROUGH');
        $expectedPlanSha = self::sha($expectedPlanSha);
        $maxAttempts = self::positiveInt($maxAttempts, 'ANYTOUR_IDENTITY_CONTENT_MAX_ATTEMPTS');
        $httpBudget = self::positiveInt($httpBudget, 'ANYTOUR_IDENTITY_CONTENT_HTTP_BUDGET');
        $minIntervalMs = self::positiveInt($minIntervalMs, 'ANYTOUR_IDENTITY_CONTENT_INTERVAL');
        if ($maxAttempts > 4 || $httpBudget > 3000 || $minIntervalMs < 500 || $minIntervalMs > 5000
            || $limit * $maxAttempts > $httpBudget) {
            throw new InvalidArgumentException('ANYTOUR_IDENTITY_CONTENT_BUDGET');
        }
        $this->assertSchema();
        $plan = $this->plan($limit, $retryBefore, $demandThrough);
        if (!hash_equals($expectedPlanSha, (string)$plan['planSha256'])) {
            throw new DomainException('ANYTOUR_IDENTITY_CONTENT_PLAN_DRIFT');
        }
        putenv('TOURVISOR_HTTP_MAX_ATTEMPTS=' . $maxAttempts);
        $beforeHttp = v2_data_tv_http_attempt_count();
        $success=$notFound=$failed=$genericRuntime=$withImages=$withDescriptions=0;
        $budgetStopped=false; $lastRequestAt=0.0;
        foreach ($plan['selected'] as $row) {
            $used = $this->liveProvider ? v2_data_tv_http_attempt_count()-$beforeHttp : $this->providerAttempts;
            if ($httpBudget-$used < $maxAttempts) { $budgetStopped=true; break; }
            $hotelId = (int)$row['tourvisorHotelId'];
            $elapsedMs=(microtime(true)-$lastRequestAt)*1000;
            if ($lastRequestAt>0 && $elapsedMs<$minIntervalMs) usleep((int)(($minIntervalMs-$elapsedMs)*1000));
            $lastRequestAt=microtime(true); $fetchedAt=gmdate('Y-m-d H:i:s');
            try {
                ++$this->providerAttempts;
                $payload=($this->fetchHotel)($hotelId);
                $hotel=v2_hotel_detail_object($payload);
                if ($hotel===null || (int)($hotel['id']??0)!==$hotelId) throw new RuntimeException('hotel detail payload identity mismatch');
                $detail=v2_hotel_detail_normalized($hotel);
                if (($detail['generic_product']??false)===true) {
                    $this->upsertTerminalStatus($hotelId,'generic_product','generic accommodation product, no concrete hotel identity',$fetchedAt);
                    ++$genericRuntime; continue;
                }
                $this->storeSuccess($hotelId,$hotel,$detail,$fetchedAt);
                if ($detail['primary_image_url']!==null) ++$withImages;
                if ($detail['description']!==null) ++$withDescriptions;
                ++$success;
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(),'HTTP 404')) {
                    $this->upsertTerminalStatus($hotelId,'not_found',$e->getMessage(),$fetchedAt); ++$notFound;
                } else {
                    $this->upsertTerminalStatus($hotelId,'failure',$e->getMessage(),$fetchedAt); ++$failed;
                }
            }
        }
        $httpAttempts = $this->liveProvider ? v2_data_tv_http_attempt_count()-$beforeHttp : $this->providerAttempts;
        return [
            'status'=>'completed','planSha256'=>$expectedPlanSha,'selected'=>count($plan['selected']),
            'providerAttempts'=>$this->providerAttempts,'httpAttempts'=>$httpAttempts,'httpBudget'=>$httpBudget,'budgetStopped'=>$budgetStopped,
            'success'=>$success,'notFound'=>$notFound,'failed'=>$failed,'genericRuntimeSkipped'=>$genericRuntime,
            'batchImages'=>$withImages,'batchDescriptions'=>$withDescriptions,'canonicalProfileWrites'=>0,'mappingWrites'=>0,
        ];
    }
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        $options=[];
        foreach (array_slice($argv,1) as $arg) {
            if (!preg_match('/^--([a-z-]+)=(.+)$/D',$arg,$m) || isset($options[$m[1]])) throw new InvalidArgumentException('ANYTOUR_IDENTITY_CONTENT_ARGS');
            $options[$m[1]]=$m[2];
        }
        foreach (['limit','retry-before','demand-through'] as $required) if (!isset($options[$required])) throw new InvalidArgumentException('ANYTOUR_IDENTITY_CONTENT_ARGS');
        $tool=new AnyTourIdentityOnlyHotelContentV1(v2_data_db());
        $plan=$tool->plan($options['limit'],$options['retry-before'],$options['demand-through']);
        if (!isset($options['apply-plan-sha'])) {
            echo AnyTourIdentityOnlyHotelContentV1::json($plan)."\n";
            exit(0);
        }
        foreach (['max-attempts','http-budget','min-interval-ms'] as $required) if (!isset($options[$required])) throw new InvalidArgumentException('ANYTOUR_IDENTITY_CONTENT_ARGS');
        $result=$tool->hydrate($options['limit'],$options['retry-before'],$options['demand-through'],$options['apply-plan-sha'],
            $options['max-attempts'],$options['http-budget'],$options['min-interval-ms']);
        echo AnyTourIdentityOnlyHotelContentV1::json($result)."\n";
        if (($result['failed']??0)>0 && ($result['success']??0)===0) exit(2);
    } catch (Throwable $e) {
        fwrite(STDERR,'ANYTOUR_IDENTITY_ONLY_CONTENT_FAILED class='.get_class($e)."\n");
        exit(1);
    }
}
