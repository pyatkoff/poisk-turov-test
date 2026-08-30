<?php
/** Synchronize Tourvisor countries/regions/subregions/hotels into AnyTour DB. */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';

function sync_catalog_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return null;
}

function sync_catalog_state(PDO $pdo, string $key, string $status, int $seen = 0, int $changed = 0, ?string $error = null): void
{
    $sql = "INSERT INTO catalog_sync_state (sync_key,status,started_at,finished_at,rows_seen,rows_changed,last_error)
            VALUES (:k,:s,IF(:s='running',NOW(),NULL),IF(:s IN ('success','failure'),NOW(),NULL),:seen,:changed,:err)
            ON DUPLICATE KEY UPDATE
              status=VALUES(status),
              started_at=IF(VALUES(status)='running',NOW(),started_at),
              finished_at=IF(VALUES(status) IN ('success','failure'),NOW(),finished_at),
              rows_seen=VALUES(rows_seen),
              rows_changed=VALUES(rows_changed),
              last_error=VALUES(last_error)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['k'=>$key,'s'=>$status,'seen'=>$seen,'changed'=>$changed,'err'=>$error]);
}

function sync_catalog_countries(PDO $pdo, array $rows, string $now): int
{
    $stmt = $pdo->prepare("INSERT INTO catalog_countries (id,name,slug,is_active,synced_at)
        VALUES (:id,:name,:slug,1,:synced)
        ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),is_active=1,synced_at=VALUES(synced_at)");
    $count = 0;
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        if ($id <= 0 || $name === '') continue;
        $stmt->execute(['id'=>$id,'name'=>$name,'slug'=>v2_data_slug($name),'synced'=>$now]);
        $count++;
    }
    return $count;
}

function sync_catalog_regions(PDO $pdo, array $rows, string $now): int
{
    $stmt = $pdo->prepare("INSERT INTO catalog_regions (id,country_id,name,slug,is_active,synced_at)
        VALUES (:id,:country,:name,:slug,1,:synced)
        ON DUPLICATE KEY UPDATE country_id=VALUES(country_id),name=VALUES(name),slug=VALUES(slug),is_active=1,synced_at=VALUES(synced_at)");
    $count = 0;
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $country = (int)($row['countryId'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        if ($id <= 0 || $country <= 0 || $name === '') continue;
        $stmt->execute(['id'=>$id,'country'=>$country,'name'=>$name,'slug'=>v2_data_slug($name),'synced'=>$now]);
        $count++;
    }
    return $count;
}

function sync_catalog_subregions(PDO $pdo, array $rows, string $now): int
{
    $stmt = $pdo->prepare("INSERT INTO catalog_subregions (id,region_id,name,slug,is_active,synced_at)
        VALUES (:id,:region,:name,:slug,1,:synced)
        ON DUPLICATE KEY UPDATE region_id=VALUES(region_id),name=VALUES(name),slug=VALUES(slug),is_active=1,synced_at=VALUES(synced_at)");
    $count = 0;
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $region = (int)($row['regionId'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        if ($id <= 0 || $region <= 0 || $name === '') continue;
        $stmt->execute(['id'=>$id,'region'=>$region,'name'=>$name,'slug'=>v2_data_slug($name),'synced'=>$now]);
        $count++;
    }
    return $count;
}

function sync_catalog_hotels_for_country(PDO $pdo, int $countryId, string $now): int
{
    $sql = "INSERT INTO catalog_hotels (
        id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,
        name,normalized_name,search_key,slug,category,rating,hotel_type,latitude,longitude,
        is_active,first_seen_at,last_seen_at,synced_at
    ) VALUES (
        :id,:country_id,:country_name,:region_id,:region_name,:subregion_id,:subregion_name,
        :name,:normalized_name,:search_key,:slug,:category,:rating,:hotel_type,:latitude,:longitude,
        1,:first_seen,:last_seen,:synced
    ) ON DUPLICATE KEY UPDATE
        country_id=VALUES(country_id),country_name=VALUES(country_name),region_id=VALUES(region_id),region_name=VALUES(region_name),
        subregion_id=VALUES(subregion_id),subregion_name=VALUES(subregion_name),name=VALUES(name),normalized_name=VALUES(normalized_name),
        search_key=VALUES(search_key),slug=VALUES(slug),category=VALUES(category),rating=VALUES(rating),hotel_type=VALUES(hotel_type),
        latitude=VALUES(latitude),longitude=VALUES(longitude),is_active=1,last_seen_at=VALUES(last_seen_at),synced_at=VALUES(synced_at)";
    $stmt = $pdo->prepare($sql);
    $aliasStmt = $pdo->prepare("INSERT IGNORE INTO hotel_aliases (hotel_id,alias,normalized_alias,source) VALUES (:hotel,:alias,:normalized,'generated')");

    $page = 1;
    $limit = 100;
    $seen = 0;
    while (true) {
        $rows = v2_data_tv_get('/hotels', ['countryId'=>$countryId,'page'=>$page,'limit'=>$limit]);
        if ($rows === []) break;

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $name = trim((string)($row['name'] ?? ''));
            $country = is_array($row['country'] ?? null) ? $row['country'] : [];
            $region = is_array($row['region'] ?? null) ? $row['region'] : [];
            $sub = is_array($row['subRegion'] ?? null) ? $row['subRegion'] : [];
            $common = is_array($row['common'] ?? null) ? $row['common'] : [];
            if ($id <= 0 || $name === '') continue;

            $normalized = v2_data_normalize_text($name);
            $parts = array_filter([
                $normalized,
                v2_data_normalize_text((string)($country['name'] ?? '')),
                v2_data_normalize_text((string)($region['name'] ?? '')),
                v2_data_normalize_text((string)($sub['name'] ?? '')),
            ]);
            $searchKey = implode(' ', array_values(array_unique($parts)));
            $slugBase = v2_data_slug($name) ?: 'hotel';

            $stmt->execute([
                'id'=>$id,
                'country_id'=>(int)($country['id'] ?? $countryId),
                'country_name'=>(string)($country['name'] ?? ''),
                'region_id'=>isset($region['id']) ? (int)$region['id'] : null,
                'region_name'=>$region['name'] ?? null,
                'subregion_id'=>isset($sub['id']) ? (int)$sub['id'] : null,
                'subregion_name'=>$sub['name'] ?? null,
                'name'=>$name,
                'normalized_name'=>$normalized,
                'search_key'=>$searchKey,
                'slug'=>$slugBase . '-' . $id,
                'category'=>isset($row['category']) ? (int)$row['category'] : null,
                'rating'=>isset($row['rating']) ? (float)$row['rating'] : null,
                'hotel_type'=>isset($row['type']) ? (int)$row['type'] : null,
                'latitude'=>isset($common['latitude']) ? (float)$common['latitude'] : null,
                'longitude'=>isset($common['longitude']) ? (float)$common['longitude'] : null,
                'first_seen'=>$now,
                'last_seen'=>$now,
                'synced'=>$now,
            ]);
            $aliasStmt->execute(['hotel'=>$id,'alias'=>$name,'normalized'=>$normalized]);
            $seen++;
        }

        if (count($rows) < $limit) break;
        $page++;
        if ($page > 1000) throw new RuntimeException('Hotel pagination safety limit reached for country ' . $countryId);
    }
    return $seen;
}

$pdo = v2_data_db();
$countryArg = sync_catalog_arg($argv, 'country');
$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

try {
    sync_catalog_state($pdo, 'catalog:base', 'running');
    $countries = v2_data_tv_get('/countries');
    $countryCount = sync_catalog_countries($pdo, $countries, $now);
    $regionCount = sync_catalog_regions($pdo, v2_data_tv_get('/regions'), $now);
    $subregionCount = sync_catalog_subregions($pdo, v2_data_tv_get('/subregions'), $now);
    sync_catalog_state($pdo, 'catalog:base', 'success', $countryCount + $regionCount + $subregionCount, $countryCount + $regionCount + $subregionCount);

    $countryIds = [];
    if ($countryArg !== null && $countryArg !== '') {
        foreach (explode(',', $countryArg) as $raw) {
            $id = filter_var(trim($raw), FILTER_VALIDATE_INT);
            if ($id !== false && (int)$id > 0) $countryIds[] = (int)$id;
        }
    } else {
        foreach ($countries as $country) {
            $id = (int)($country['id'] ?? 0);
            if ($id > 0) $countryIds[] = $id;
        }
    }
    $countryIds = array_values(array_unique($countryIds));

    foreach ($countryIds as $countryId) {
        $key = 'hotels:country:' . $countryId;
        sync_catalog_state($pdo, $key, 'running');
        try {
            $count = sync_catalog_hotels_for_country($pdo, $countryId, $now);
            sync_catalog_state($pdo, $key, 'success', $count, $count);
            fwrite(STDOUT, "HOTELS country={$countryId} rows={$count}\n");
        } catch (Throwable $e) {
            sync_catalog_state($pdo, $key, 'failure', 0, 0, mb_substr($e->getMessage(), 0, 1000));
            fwrite(STDERR, "HOTELS_FAILED country={$countryId} {$e->getMessage()}\n");
        }
    }

    fwrite(STDOUT, "CATALOG_OK countries={$countryCount} regions={$regionCount} subregions={$subregionCount}\n");
} catch (Throwable $e) {
    sync_catalog_state($pdo, 'catalog:base', 'failure', 0, 0, mb_substr($e->getMessage(), 0, 1000));
    fwrite(STDERR, "CATALOG_FAILED {$e->getMessage()}\n");
    exit(1);
}
