<?php
/**
 * Demand-first LOCAL content acquisition for active AnyTour canonical hotels.
 *
 * Identity comes only from verified AnyTour-owned `anytour_local_id` aliases.
 * The supplier contract remains the existing Tourvisor `/hotels/{localId}` hotel-card
 * endpoint. This collector persists saved hotel presentation only; canonical profiles
 * are updated separately by AnyTourProfileEnrichmentV1 (fill-missing only).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';
require_once __DIR__ . '/hotel-details-v1.php';

const ANYTOUR_CANONICAL_CONTENT_ALIAS_NAMESPACE = 'anytour_local_id';
const ANYTOUR_CANONICAL_CONTENT_ALIAS_VIA = 'canonical_local_alias_v1';

function anytour_canonical_content_arg(array $argv, string $name, ?string $fallback = null): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return $fallback;
}

function anytour_canonical_content_int(array $argv, string $name, int $default, int $min, int $max): int
{
    $raw = anytour_canonical_content_arg($argv, $name, (string)$default);
    $n = filter_var($raw, FILTER_VALIDATE_INT);
    return $n === false ? $default : max($min, min($max, (int)$n));
}

function anytour_canonical_content_normalize_name(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    return mb_strtolower($value);
}

function anytour_canonical_content_positive_id(mixed $value, string $code): int
{
    if (is_int($value) && $value > 0) return $value;
    if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value)
        && filter_var($value, FILTER_VALIDATE_INT) !== false) return (int)$value;
    throw new RuntimeException($code);
}

function anytour_canonical_content_decode_profile(array $row): array
{
    $json = $row['profile_json'] ?? null;
    $sha = $row['profile_sha256'] ?? null;
    if (!is_string($json) || !is_string($sha) || !preg_match('/\A[a-f0-9]{64}\z/D', $sha)
        || !hash_equals($sha, hash('sha256', $json))) {
        throw new RuntimeException('ANYTOUR_CANONICAL_CONTENT_PROFILE_INTEGRITY');
    }
    try { $profile = json_decode($json, true, 512, JSON_THROW_ON_ERROR); }
    catch (Throwable) { throw new RuntimeException('ANYTOUR_CANONICAL_CONTENT_PROFILE_JSON'); }
    if (!is_array($profile)) throw new RuntimeException('ANYTOUR_CANONICAL_CONTENT_PROFILE_JSON');
    return $profile;
}

function anytour_canonical_content_decode_alias(array $row): array
{
    $ownId = anytour_canonical_content_positive_id($row['anytour_hotel_id'] ?? null, 'ANYTOUR_CANONICAL_CONTENT_OWN_ID');
    $localId = anytour_canonical_content_positive_id($row['local_id'] ?? null, 'ANYTOUR_CANONICAL_CONTENT_LOCAL_ID');
    $json = $row['alias_source_json'] ?? null;
    $sha = $row['alias_source_sha256'] ?? null;
    if (($row['alias_acquired_via'] ?? null) !== ANYTOUR_CANONICAL_CONTENT_ALIAS_VIA
        || !is_string($json) || !is_string($sha) || !preg_match('/\A[a-f0-9]{64}\z/D', $sha)
        || !hash_equals($sha, hash('sha256', $json))) {
        throw new RuntimeException('ANYTOUR_CANONICAL_CONTENT_ALIAS_INTEGRITY');
    }
    try { $source = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
    catch (Throwable) { throw new RuntimeException('ANYTOUR_CANONICAL_CONTENT_ALIAS_JSON'); }
    $expected = ['accepted_local_hotel_id','canonical_hotel_id','derived_from_namespace','derived_from_source_sha256','schema_version'];
    if (!is_array($source) || count($source) !== count($expected)
        || array_diff($expected, array_keys($source)) !== [] || array_diff(array_keys($source), $expected) !== []
        || ($source['schema_version'] ?? null) !== 1
        || ($source['accepted_local_hotel_id'] ?? null) !== $localId
        || ($source['canonical_hotel_id'] ?? null) !== $ownId
        || ($source['derived_from_namespace'] ?? null) !== 'legacy_catalog'
        || !is_string($source['derived_from_source_sha256'] ?? null)
        || !preg_match('/\A[a-f0-9]{64}\z/D', $source['derived_from_source_sha256'])) {
        throw new RuntimeException('ANYTOUR_CANONICAL_CONTENT_ALIAS_SEMANTICS');
    }
    return ['own_id'=>$ownId,'local_id'=>$localId];
}

function anytour_canonical_content_missing(mixed $value): bool
{
    if ($value === null) return true;
    if (is_string($value)) return trim($value) === '';
    if (is_array($value)) return $value === [];
    return false;
}

function anytour_canonical_content_profile_needs_content(array $profile): bool
{
    $hotelInfo = is_array($profile['hotelInformation'] ?? null) ? $profile['hotelInformation'] : [];
    foreach (['description','primaryImage','images','address','place','build','repair','square'] as $field) {
        if (anytour_canonical_content_missing($profile[$field] ?? null)) return true;
    }
    if (anytour_canonical_content_missing($hotelInfo['infrastructure'] ?? null)
        || anytour_canonical_content_missing($hotelInfo['services'] ?? null)) return true;
    return false;
}

/**
 * Select stale/missing saved hotel cards for active canonical profiles.
 * Demand-bearing identities come first; provider-only identities follow deterministically.
 */
function anytour_canonical_content_pending_rows(PDO $pdo, string $cutoff, string $retryCutoff, int $limit): array
{
    $scanLimit = min(50000, max(1000, $limit * 20));
    $sql = "WITH demand AS (
        SELECT hotel_id,
               SUM(source='user_search') AS user_search_count,
               COUNT(*) AS observation_count,
               MAX(observed_at) AS last_seen_at
        FROM tour_price_observations
        WHERE hotel_id>0
        GROUP BY hotel_id
    ), hot AS (
        SELECT hotel_id, MAX(fetched_at) AS hot_last_seen_at
        FROM hot_tours_current
        WHERE hotel_id>0
        GROUP BY hotel_id
    )
    SELECT h.id AS anytour_hotel_id,h.profile_json,h.profile_sha256,
           CAST(a.external_key AS CHAR) AS local_id,
           a.acquired_via AS alias_acquired_via,a.source_json AS alias_source_json,a.source_sha256 AS alias_source_sha256,
           COALESCE(d.user_search_count,0) AS user_search_count,
           COALESCE(d.observation_count,0) AS observation_count,
           d.last_seen_at,hot.hot_last_seen_at,c.name AS catalog_name,
           det.status AS detail_status,det.fetched_at AS detail_fetched_at
    FROM anytour_hotels h
    JOIN anytour_hotel_sources a
      ON a.anytour_hotel_id=h.id AND a.namespace='" . ANYTOUR_CANONICAL_CONTENT_ALIAS_NAMESPACE . "'
    LEFT JOIN demand d ON d.hotel_id=CAST(a.external_key AS UNSIGNED)
    LEFT JOIN hot ON hot.hotel_id=CAST(a.external_key AS UNSIGNED)
    LEFT JOIN catalog_hotels c ON c.id=CAST(a.external_key AS UNSIGNED)
    LEFT JOIN catalog_hotel_details det ON det.hotel_id=CAST(a.external_key AS UNSIGNED)
    WHERE h.is_active=1
      AND c.id IS NOT NULL
      AND (
           det.hotel_id IS NULL
           OR (det.status='failure' AND det.fetched_at < :retry_cutoff)
           OR (det.status<>'failure' AND det.fetched_at < :cutoff)
      )
    ORDER BY COALESCE(d.user_search_count,0) DESC,
             COALESCE(d.observation_count,0) DESC,
             (COALESCE(d.last_seen_at,hot.hot_last_seen_at) IS NULL) ASC,
             COALESCE(d.last_seen_at,hot.hot_last_seen_at) DESC,
             h.id ASC
    LIMIT {$scanLimit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['retry_cutoff'=>$retryCutoff,'cutoff'=>$cutoff]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $selected = [];
    $genericSkipped = 0;
    $contentReadySkipped = 0;
    $seenLocal = [];
    foreach ($rows as $row) {
        $alias = anytour_canonical_content_decode_alias($row);
        if (isset($seenLocal[$alias['local_id']])) throw new RuntimeException('ANYTOUR_CANONICAL_CONTENT_DUPLICATE_LOCAL_ALIAS');
        $seenLocal[$alias['local_id']] = true;
        $profile = anytour_canonical_content_decode_profile($row);
        $profileName = is_string($profile['name'] ?? null) ? trim($profile['name']) : '';
        if (v2_hotel_detail_is_generic_product_name($profileName)
            || v2_hotel_detail_is_generic_product_name($row['catalog_name'] ?? null)) {
            $genericSkipped++;
            continue;
        }
        if (!anytour_canonical_content_profile_needs_content($profile)) {
            $contentReadySkipped++;
            continue;
        }
        $row['hotel_id'] = $alias['local_id'];
        $row['canonical_name'] = $profileName;
        $row['last_seen_at'] = $row['last_seen_at'] ?: $row['hot_last_seen_at'];
        $selected[] = $row;
        if (count($selected) >= $limit) break;
    }
    return [
        'rows'=>$selected,
        'scanned'=>count($rows),
        'generic_skipped'=>$genericSkipped,
        'content_ready_skipped'=>$contentReadySkipped,
    ];
}

function anytour_canonical_content_upsert_failure(PDO $pdo, int $hotelId, string $status, string $message, string $now): void
{
    $message = mb_substr(str_replace(["\r","\n"], ' ', $message), 0, 1000);
    $stmt = $pdo->prepare("INSERT INTO catalog_hotel_details (hotel_id,status,fetched_at,last_error)
        VALUES (:hotel_id,:status,:fetched_at,:last_error)
        ON DUPLICATE KEY UPDATE
          status=IF(VALUES(status)='not_found','not_found',status),
          fetched_at=IF(VALUES(status)='not_found',VALUES(fetched_at),fetched_at),
          last_error=VALUES(last_error)");
    $stmt->execute(['hotel_id'=>$hotelId,'status'=>$status,'fetched_at'=>$now,'last_error'=>$message]);
}

function anytour_canonical_content_run(array $argv): int
{
    $limit = anytour_canonical_content_int($argv,'limit',250,1,3000);
    $freshDays = anytour_canonical_content_int($argv,'fresh-days',365,1,365);
    $minIntervalMs = anytour_canonical_content_int($argv,'min-interval-ms',600,500,5000);
    $maxAttempts = anytour_canonical_content_int($argv,'max-attempts',1,1,4);
    $httpBudget = anytour_canonical_content_int($argv,'http-budget',250,1,3000);
    if ($limit * $maxAttempts > $httpBudget) throw new RuntimeException('ANYTOUR_CANONICAL_CONTENT_HTTP_BUDGET');
    putenv('TOURVISOR_HTTP_MAX_ATTEMPTS=' . $maxAttempts);

    $now = new DateTimeImmutable('now');
    $cutoff = $now->modify('-' . $freshDays . ' days')->format('Y-m-d H:i:s');
    $retryCutoff = $now->modify('-1 day')->format('Y-m-d H:i:s');
    $pdo = v2_data_db();
    $plan = anytour_canonical_content_pending_rows($pdo,$cutoff,$retryCutoff,$limit);
    $pending = $plan['rows'];
    echo 'ANYTOUR_CANONICAL_CONTENT_PLAN selected=' . count($pending)
        . ' scanned=' . $plan['scanned']
        . ' generic_skipped=' . $plan['generic_skipped']
        . ' content_ready_skipped=' . $plan['content_ready_skipped']
        . ' fresh_days=' . $freshDays
        . ' max_attempts=' . $maxAttempts
        . ' http_budget=' . $httpBudget . "\n";

    $saveDetail = $pdo->prepare("INSERT INTO catalog_hotel_details (
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
    $updateCatalog = $pdo->prepare("UPDATE catalog_hotels SET
        country_id=COALESCE(:country_id,country_id),country_name=COALESCE(:country_name,country_name),
        region_id=COALESCE(:region_id,region_id),region_name=COALESCE(:region_name,region_name),
        subregion_id=COALESCE(:subregion_id,subregion_id),subregion_name=COALESCE(:subregion_name,subregion_name),
        name=COALESCE(:name,name),normalized_name=COALESCE(:normalized_name,normalized_name),search_key=COALESCE(:search_key,search_key),
        category=COALESCE(:category,category),rating=COALESCE(:rating,rating),hotel_type=COALESCE(:hotel_type,hotel_type),
        latitude=COALESCE(:latitude,latitude),longitude=COALESCE(:longitude,longitude),
        primary_image_url=COALESCE(:primary_image_url,primary_image_url),
        image_updated_at=IF(:primary_image_url_2 IS NOT NULL,:image_updated_at,image_updated_at),
        last_seen_at=GREATEST(last_seen_at,:last_seen_at),synced_at=:synced_at,is_active=1
        WHERE id=:hotel_id");

    $success=0; $notFound=0; $failed=0; $withImages=0; $withDescriptions=0;
    $genericRuntimeSkipped=0; $attempted=0; $budgetStopped=false; $lastRequestAt=0.0;
    foreach ($pending as $sourceRow) {
        if (v2_data_tv_http_attempt_count() >= $httpBudget) { $budgetStopped=true; break; }
        $hotelId=(int)$sourceRow['hotel_id'];
        $elapsedMs=(microtime(true)-$lastRequestAt)*1000;
        if ($lastRequestAt>0 && $elapsedMs<$minIntervalMs) usleep((int)(($minIntervalMs-$elapsedMs)*1000));
        $lastRequestAt=microtime(true); $attempted++;
        $fetchedAt=(new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        try {
            $payload=v2_data_tv_get('/hotels/' . $hotelId);
            $hotel=v2_hotel_detail_object($payload);
            if ($hotel===null || (int)($hotel['id'] ?? 0)!==$hotelId) throw new RuntimeException('hotel detail payload identity mismatch');
            $detail=v2_hotel_detail_normalized($hotel);
            if (($detail['generic_product'] ?? false)===true) { $genericRuntimeSkipped++; continue; }
            $rawJson=json_encode($hotel,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $saveDetail->execute([
                'hotel_id'=>$hotelId,'source_hash'=>hash('sha256',$rawJson),'country_id'=>$detail['country_id'],
                'region_id'=>$detail['region_id'],'subregion_id'=>$detail['subregion_id'],'name'=>$detail['name'],
                'category'=>$detail['category'],'rating'=>$detail['rating'],'hotel_type'=>$detail['hotel_type'],
                'description'=>$detail['description'],'address'=>$detail['address'],'place'=>$detail['place'],'phone'=>$detail['phone'],
                'site'=>$detail['site'],'build_info'=>$detail['build'],'repair_info'=>$detail['repair'],'square_info'=>$detail['square'],
                'latitude'=>$detail['latitude'],'longitude'=>$detail['longitude'],'primary_image_url'=>$detail['primary_image_url'],
                'images_json'=>$detail['images_json'],'infrastructure_json'=>$detail['infrastructure_json'],'meals_json'=>$detail['meals_json'],
                'services_json'=>$detail['services_json'],'room_types'=>$detail['room_types'],'raw_json'=>$rawJson,'fetched_at'=>$fetchedAt,
            ]);
            $name=$detail['name']; $countryName=$detail['country_name'];
            $normalizedName=$name!==null ? anytour_canonical_content_normalize_name($name) : null;
            $searchKey=$name!==null ? anytour_canonical_content_normalize_name(implode(' ',array_filter([$name,$countryName,$detail['region_name'],$detail['subregion_name']]))) : null;
            $lastSeenAt=trim((string)($sourceRow['last_seen_at'] ?? '')) ?: $fetchedAt;
            $updateCatalog->execute([
                'hotel_id'=>$hotelId,'country_id'=>$detail['country_id'],'country_name'=>$countryName,
                'region_id'=>$detail['region_id'],'region_name'=>$detail['region_name'],'subregion_id'=>$detail['subregion_id'],
                'subregion_name'=>$detail['subregion_name'],'name'=>$name,'normalized_name'=>$normalizedName,'search_key'=>$searchKey,
                'category'=>$detail['category'],'rating'=>$detail['rating'],'hotel_type'=>$detail['hotel_type'],
                'latitude'=>$detail['latitude'],'longitude'=>$detail['longitude'],'primary_image_url'=>$detail['primary_image_url'],
                'primary_image_url_2'=>$detail['primary_image_url'],'image_updated_at'=>$detail['primary_image_url']!==null ? $fetchedAt : null,
                'last_seen_at'=>$lastSeenAt,'synced_at'=>$fetchedAt,
            ]);
            if ($detail['primary_image_url']!==null) $withImages++;
            if ($detail['description']!==null) $withDescriptions++;
            $success++;
        } catch (Throwable $e) {
            $message=$e->getMessage();
            if (str_contains($message,'HTTP 404')) {
                anytour_canonical_content_upsert_failure($pdo,$hotelId,'not_found',$message,$fetchedAt); $notFound++;
            } else {
                anytour_canonical_content_upsert_failure($pdo,$hotelId,'failure',$message,$fetchedAt); $failed++;
            }
        }
        if ($attempted%50===0 || $attempted===count($pending)) {
            echo 'ANYTOUR_CANONICAL_CONTENT_PROGRESS done=' . $attempted . '/' . count($pending)
                . ' http_attempts=' . v2_data_tv_http_attempt_count()
                . ' success=' . $success . ' not_found=' . $notFound . ' failed=' . $failed . "\n";
        }
    }
    echo 'ANYTOUR_CANONICAL_CONTENT_DONE selected=' . count($pending)
        . ' attempted=' . $attempted . ' http_attempts=' . v2_data_tv_http_attempt_count()
        . ' http_budget=' . $httpBudget . ' budget_stopped=' . ($budgetStopped?1:0)
        . ' generic_pre_skipped=' . $plan['generic_skipped'] . ' generic_runtime_skipped=' . $genericRuntimeSkipped
        . ' content_ready_skipped=' . $plan['content_ready_skipped']
        . ' success=' . $success . ' not_found=' . $notFound . ' failed=' . $failed
        . ' batch_images=' . $withImages . ' batch_descriptions=' . $withDescriptions . "\n";
    return ($failed>0 && $success===0) ? 2 : 0;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(anytour_canonical_content_run($argv));
}
