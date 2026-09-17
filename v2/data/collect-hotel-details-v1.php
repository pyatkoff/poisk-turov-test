<?php
/** Enrich saved AnyTour hotel cards using the Tourvisor hotel-description API. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';
require_once __DIR__ . '/hotel-details-v1.php';

function hotel_details_arg(array $argv, string $name, ?string $fallback = null): ?string
{
    foreach ($argv as $arg) if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    return $fallback;
}

function hotel_details_int(array $argv, string $name, int $default, int $min, int $max): int
{
    $raw = hotel_details_arg($argv, $name, (string)$default);
    $n = filter_var($raw, FILTER_VALIDATE_INT);
    return $n === false ? $default : max($min, min($max, (int)$n));
}

function hotel_details_normalize_name(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    return mb_strtolower($value);
}

function hotel_details_source_total(PDO $pdo, string $scope): int
{
    if ($scope === 'demand') {
        $sql = "SELECT COUNT(*) FROM (
            SELECT hotel_id FROM tour_price_observations WHERE hotel_id>0 GROUP BY hotel_id
            UNION
            SELECT hotel_id FROM hot_tours_current WHERE hotel_id>0 GROUP BY hotel_id
        ) h";
        return (int)$pdo->query($sql)->fetchColumn();
    }
    $sql = "SELECT COUNT(*)
            FROM anytour_hotel_sources s
            JOIN anytour_hotels a ON a.id=s.anytour_hotel_id AND a.is_active=1
            WHERE s.namespace='legacy_catalog'
              AND CONVERT(s.external_key USING ascii) REGEXP '^[1-9][0-9]*$'";
    return (int)$pdo->query($sql)->fetchColumn();
}

function hotel_details_demand_source_sql(): string
{
    return "WITH source_rows AS (
        SELECT hotel_id,0 AS anytour_hotel_id,0 AS user_search_count,COUNT(*) AS observation_count,
               MAX(seen_at) AS last_seen_at,NULL AS canonical_name,0 AS identity_only
        FROM (
            SELECT hotel_id,observed_at AS seen_at FROM tour_price_observations WHERE hotel_id>0
            UNION ALL
            SELECT hotel_id,fetched_at AS seen_at FROM hot_tours_current WHERE hotel_id>0
        ) u
        GROUP BY hotel_id
    )";
}

/**
 * Canonical acquisition uses legacy_catalog solely as immutable Tourvisor source
 * provenance. It is not a runtime identity resolver and does not alter MATCH.
 */
function hotel_details_canonical_source_sql(): string
{
    return "WITH demand AS (
        SELECT hotel_id,SUM(source='user_search') AS user_search_count,COUNT(*) AS observation_count,
               MAX(observed_at) AS last_seen_at
        FROM tour_price_observations WHERE hotel_id>0 GROUP BY hotel_id
    ), hot AS (
        SELECT hotel_id,MAX(fetched_at) AS last_seen_at
        FROM hot_tours_current WHERE hotel_id>0 GROUP BY hotel_id
    ), source_rows AS (
        SELECT CAST(CONVERT(s.external_key USING ascii) AS UNSIGNED) AS hotel_id,
               a.id AS anytour_hotel_id,
               COALESCE(d.user_search_count,0) AS user_search_count,
               COALESCE(d.observation_count,0) AS observation_count,
               CASE
                   WHEN d.last_seen_at IS NULL THEN hot.last_seen_at
                   WHEN hot.last_seen_at IS NULL THEN d.last_seen_at
                   ELSE GREATEST(d.last_seen_at,hot.last_seen_at)
               END AS last_seen_at,
               JSON_UNQUOTE(JSON_EXTRACT(a.profile_json,'$.name')) AS canonical_name,
               CASE WHEN
                   COALESCE(TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.profile_json,'$.description'))),'')='' AND
                   COALESCE(TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.profile_json,'$.primaryImage'))),'')='' AND
                   COALESCE(JSON_LENGTH(JSON_EXTRACT(a.profile_json,'$.images')),0)=0 AND
                   COALESCE(JSON_LENGTH(JSON_EXTRACT(a.profile_json,'$.hotelInformation.infrastructure')),0)=0 AND
                   COALESCE(JSON_LENGTH(JSON_EXTRACT(a.profile_json,'$.hotelInformation.services')),0)=0 AND
                   COALESCE(JSON_LENGTH(JSON_EXTRACT(a.profile_json,'$.traits')),0)=0
               THEN 1 ELSE 0 END AS identity_only
        FROM anytour_hotel_sources s
        JOIN anytour_hotels a ON a.id=s.anytour_hotel_id AND a.is_active=1
        LEFT JOIN demand d ON d.hotel_id=CAST(CONVERT(s.external_key USING ascii) AS UNSIGNED)
        LEFT JOIN hot ON hot.hotel_id=CAST(CONVERT(s.external_key USING ascii) AS UNSIGNED)
        WHERE s.namespace='legacy_catalog'
          AND CONVERT(s.external_key USING ascii) REGEXP '^[1-9][0-9]*$'
          AND JSON_VALID(a.profile_json)=1
    )";
}

/**
 * Demand remains first priority. Canonical scope then continues through all active
 * own hotels via their exact historical Tourvisor source IDs, identity-only first.
 * Generic Fortuna/Roulette products are deliberately excluded from hotel hydration.
 */
function hotel_details_pending_rows(PDO $pdo, string $cutoff, string $retryCutoff, int $limit, string $scope): array
{
    $scanLimit = min(50000, max($limit, $limit * 4));
    $with = $scope === 'canonical' ? hotel_details_canonical_source_sql() : hotel_details_demand_source_sql();
    $sql = $with . "
    SELECT s.hotel_id,s.anytour_hotel_id,s.user_search_count,s.observation_count,
           COALESCE(s.last_seen_at,h.last_seen_at) AS last_seen_at,
           s.canonical_name,s.identity_only,d.status,d.fetched_at,h.name AS catalog_name
    FROM source_rows s
    LEFT JOIN catalog_hotel_details d ON d.hotel_id=s.hotel_id
    LEFT JOIN catalog_hotels h ON h.id=s.hotel_id
    WHERE d.hotel_id IS NULL
       OR (d.status='failure' AND d.fetched_at < :retry_cutoff)
       OR (d.status<>'failure' AND d.fetched_at < :cutoff)
    ORDER BY s.user_search_count DESC,s.observation_count DESC,s.identity_only DESC,
             (s.last_seen_at IS NULL) ASC,s.last_seen_at DESC,s.hotel_id ASC
    LIMIT {$scanLimit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['retry_cutoff'=>$retryCutoff,'cutoff'=>$cutoff]);
    $scanned = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $selected = [];
    $genericSkipped = 0;
    foreach ($scanned as $row) {
        if (v2_hotel_detail_is_generic_product_name($row['catalog_name'] ?? null)
            || v2_hotel_detail_is_generic_product_name($row['canonical_name'] ?? null)) {
            $genericSkipped++;
            continue;
        }
        $selected[] = $row;
        if (count($selected) >= $limit) break;
    }
    return ['rows'=>$selected,'scanned'=>count($scanned),'generic_skipped'=>$genericSkipped];
}

function hotel_details_fresh_success(PDO $pdo, string $scope, string $cutoff): int
{
    if ($scope === 'demand') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM catalog_hotel_details d JOIN (
            SELECT hotel_id FROM tour_price_observations WHERE hotel_id>0 GROUP BY hotel_id
            UNION
            SELECT hotel_id FROM hot_tours_current WHERE hotel_id>0 GROUP BY hotel_id
        ) s ON s.hotel_id=d.hotel_id WHERE d.status='success' AND d.fetched_at >= :cutoff");
        $stmt->execute(['cutoff'=>$cutoff]);
        return (int)$stmt->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM catalog_hotel_details d
        JOIN anytour_hotel_sources s
          ON s.namespace='legacy_catalog'
         AND CONVERT(s.external_key USING ascii) REGEXP '^[1-9][0-9]*$'
         AND CAST(CONVERT(s.external_key USING ascii) AS UNSIGNED)=d.hotel_id
        JOIN anytour_hotels a ON a.id=s.anytour_hotel_id AND a.is_active=1
        WHERE d.status='success' AND d.fetched_at >= :cutoff");
    $stmt->execute(['cutoff'=>$cutoff]);
    return (int)$stmt->fetchColumn();
}

function hotel_details_upsert_failure(PDO $pdo, int $hotelId, string $status, string $message, string $now): void
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

$limit = hotel_details_int($argv, 'limit', 3000, 1, 3000);
$freshDays = hotel_details_int($argv, 'fresh-days', 30, 1, 365);
$minIntervalMs = hotel_details_int($argv, 'min-interval-ms', 600, 500, 5000);
$maxAttempts = hotel_details_int($argv, 'max-attempts', 1, 1, 4);
$httpBudget = hotel_details_int($argv, 'http-budget', 3000, 1, 3000);
$scope = v2_hotel_detail_candidate_scope(hotel_details_arg($argv, 'candidate-scope', 'demand'));
$worstCaseHttpAttempts = $limit * $maxAttempts;
if ($worstCaseHttpAttempts > $httpBudget) {
    throw new RuntimeException('Hotel detail request plan exceeds explicit HTTP budget');
}
putenv('TOURVISOR_HTTP_MAX_ATTEMPTS=' . $maxAttempts);

$now = new DateTimeImmutable('now');
$cutoff = $now->modify('-' . $freshDays . ' days')->format('Y-m-d H:i:s');
$retryCutoff = $now->modify('-1 day')->format('Y-m-d H:i:s');

$pdo = v2_data_db();
$sourceTotal = hotel_details_source_total($pdo, $scope);
$pendingPlan = hotel_details_pending_rows($pdo, $cutoff, $retryCutoff, $limit, $scope);
$pending = $pendingPlan['rows'];
$identityOnlySelected = count(array_filter($pending, static fn(array $row): bool => (int)($row['identity_only'] ?? 0) === 1));
$demandSelected = count(array_filter($pending, static fn(array $row): bool => (int)($row['observation_count'] ?? 0) > 0));
echo 'ANYTOUR_HOTEL_DETAILS_PLAN candidate_scope=' . $scope
    . ' source_hotels=' . $sourceTotal
    . ' selected=' . count($pending)
    . ' demand_selected=' . $demandSelected
    . ' identity_only_selected=' . $identityOnlySelected
    . ' scanned=' . $pendingPlan['scanned']
    . ' generic_skipped=' . $pendingPlan['generic_skipped']
    . ' fresh_days=' . $freshDays
    . ' interval_ms=' . $minIntervalMs
    . ' max_attempts=' . $maxAttempts
    . ' http_budget=' . $httpBudget
    . ' worst_case_http_attempts=' . $worstCaseHttpAttempts . "\n";

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

$insertCatalog = $pdo->prepare("INSERT INTO catalog_hotels (
    id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,normalized_name,search_key,slug,
    category,rating,hotel_type,latitude,longitude,primary_image_url,image_updated_at,is_active,first_seen_at,last_seen_at,synced_at
) VALUES (
    :hotel_id,:country_id,:country_name,:region_id,:region_name,:subregion_id,:subregion_name,:name,:normalized_name,:search_key,NULL,
    :category,:rating,:hotel_type,:latitude,:longitude,:primary_image_url,:image_updated_at,1,:first_seen_at,:last_seen_at,:synced_at
)");

$success = 0;
$notFound = 0;
$failed = 0;
$catalogInserted = 0;
$withImages = 0;
$withDescriptions = 0;
$genericRuntimeSkipped = 0;
$hotelsAttempted = 0;
$budgetStopped = false;
$lastRequestAt = 0.0;

foreach ($pending as $sourceRow) {
    if (v2_data_tv_http_attempt_count() >= $httpBudget) {
        $budgetStopped = true;
        break;
    }
    $hotelId = (int)$sourceRow['hotel_id'];
    if ($hotelId <= 0) continue;

    $elapsedMs = (microtime(true) - $lastRequestAt) * 1000;
    if ($lastRequestAt > 0 && $elapsedMs < $minIntervalMs) usleep((int)(($minIntervalMs - $elapsedMs) * 1000));
    $lastRequestAt = microtime(true);
    $fetchedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $hotelsAttempted++;

    try {
        $payload = v2_data_tv_get('/hotels/' . $hotelId);
        $hotel = v2_hotel_detail_object($payload);
        if ($hotel === null || (int)($hotel['id'] ?? 0) !== $hotelId) throw new RuntimeException('hotel detail payload identity mismatch');
        $detail = v2_hotel_detail_normalized($hotel);
        if (($detail['generic_product'] ?? false) === true) {
            $genericRuntimeSkipped++;
            continue;
        }
        $rawJson = json_encode($hotel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $sourceHash = hash('sha256', $rawJson);

        $saveDetail->execute([
            'hotel_id'=>$hotelId,'source_hash'=>$sourceHash,
            'country_id'=>$detail['country_id'],'region_id'=>$detail['region_id'],'subregion_id'=>$detail['subregion_id'],
            'name'=>$detail['name'],'category'=>$detail['category'],'rating'=>$detail['rating'],'hotel_type'=>$detail['hotel_type'],
            'description'=>$detail['description'],'address'=>$detail['address'],'place'=>$detail['place'],'phone'=>$detail['phone'],'site'=>$detail['site'],
            'build_info'=>$detail['build'],'repair_info'=>$detail['repair'],'square_info'=>$detail['square'],
            'latitude'=>$detail['latitude'],'longitude'=>$detail['longitude'],'primary_image_url'=>$detail['primary_image_url'],
            'images_json'=>$detail['images_json'],'infrastructure_json'=>$detail['infrastructure_json'],'meals_json'=>$detail['meals_json'],
            'services_json'=>$detail['services_json'],'room_types'=>$detail['room_types'],'raw_json'=>$rawJson,'fetched_at'=>$fetchedAt,
        ]);

        $name = $detail['name'];
        $countryName = $detail['country_name'];
        $normalizedName = $name !== null ? hotel_details_normalize_name($name) : null;
        $searchKey = $name !== null ? hotel_details_normalize_name(implode(' ', array_filter([$name,$countryName,$detail['region_name'],$detail['subregion_name']]))) : null;
        $lastSeenAt = trim((string)($sourceRow['last_seen_at'] ?? '')) ?: $fetchedAt;
        $catalogArgs = [
            'hotel_id'=>$hotelId,'country_id'=>$detail['country_id'],'country_name'=>$countryName,
            'region_id'=>$detail['region_id'],'region_name'=>$detail['region_name'],'subregion_id'=>$detail['subregion_id'],'subregion_name'=>$detail['subregion_name'],
            'name'=>$name,'normalized_name'=>$normalizedName,'search_key'=>$searchKey,'category'=>$detail['category'],'rating'=>$detail['rating'],'hotel_type'=>$detail['hotel_type'],
            'latitude'=>$detail['latitude'],'longitude'=>$detail['longitude'],'primary_image_url'=>$detail['primary_image_url'],'primary_image_url_2'=>$detail['primary_image_url'],
            'image_updated_at'=>$detail['primary_image_url'] !== null ? $fetchedAt : null,'last_seen_at'=>$lastSeenAt,'synced_at'=>$fetchedAt,
        ];
        $updateCatalog->execute($catalogArgs);
        if ($updateCatalog->rowCount() === 0 && $detail['country_id'] !== null && $countryName !== null && $name !== null && $normalizedName !== null && $searchKey !== null) {
            $insertArgs = $catalogArgs;
            unset($insertArgs['primary_image_url_2']);
            $insertArgs['first_seen_at'] = $lastSeenAt;
            try {
                $insertCatalog->execute($insertArgs);
                $catalogInserted++;
            } catch (PDOException $e) {
                if ((string)$e->getCode() !== '23000') throw $e;
            }
        }

        if ($detail['primary_image_url'] !== null) $withImages++;
        if ($detail['description'] !== null) $withDescriptions++;
        $success++;
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (str_contains($message, 'HTTP 404')) {
            hotel_details_upsert_failure($pdo, $hotelId, 'not_found', $message, $fetchedAt);
            $notFound++;
        } else {
            hotel_details_upsert_failure($pdo, $hotelId, 'failure', $message, $fetchedAt);
            $failed++;
        }
    }

    if ($hotelsAttempted % 50 === 0 || $hotelsAttempted === count($pending)) {
        echo 'ANYTOUR_HOTEL_DETAILS_PROGRESS done=' . $hotelsAttempted . '/' . count($pending)
            . ' http_attempts=' . v2_data_tv_http_attempt_count()
            . ' success=' . $success . ' not_found=' . $notFound . ' failed=' . $failed
            . ' generic_runtime_skipped=' . $genericRuntimeSkipped . "\n";
    }
}

$freshSuccess = hotel_details_fresh_success($pdo, $scope, $cutoff);
$imageCount = (int)$pdo->query("SELECT COUNT(*) FROM catalog_hotel_details WHERE status='success' AND primary_image_url IS NOT NULL AND TRIM(primary_image_url)<>''")->fetchColumn();
$descriptionCount = (int)$pdo->query("SELECT COUNT(*) FROM catalog_hotel_details WHERE status='success' AND description IS NOT NULL AND TRIM(description)<>''")->fetchColumn();

echo 'ANYTOUR_HOTEL_DETAILS_DONE candidate_scope=' . $scope
    . ' source_hotels=' . $sourceTotal
    . ' selected=' . count($pending)
    . ' demand_selected=' . $demandSelected
    . ' identity_only_selected=' . $identityOnlySelected
    . ' attempted=' . $hotelsAttempted
    . ' http_attempts=' . v2_data_tv_http_attempt_count()
    . ' http_budget=' . $httpBudget
    . ' budget_stopped=' . ($budgetStopped ? 1 : 0)
    . ' generic_pre_skipped=' . $pendingPlan['generic_skipped']
    . ' generic_runtime_skipped=' . $genericRuntimeSkipped
    . ' success=' . $success . ' not_found=' . $notFound . ' failed=' . $failed
    . ' catalog_inserted=' . $catalogInserted . ' batch_images=' . $withImages
    . ' batch_descriptions=' . $withDescriptions . ' fresh_success=' . $freshSuccess
    . ' total_images=' . $imageCount . ' total_descriptions=' . $descriptionCount . "\n";
if ($failed > 0 && $success === 0) exit(2);