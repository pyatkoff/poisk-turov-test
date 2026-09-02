<?php
require_once __DIR__ . '/seo-content-catalog-v1.php';
require_once __DIR__ . '/seo-page-launch-readiness-v1.php';

/**
 * Build and verify one country -> resort editorial family without publishing it.
 *
 * Stable editorial/search identities only. This diagnostic does not mutate
 * statuses, routes, robots, canonicals, sitemap output or launch flags.
 */
function v2_seo_resort_family_integrity(array $countryRecord, array $resortRecords): array
{
    if (($countryRecord['type'] ?? '') !== 'country') {
        throw new InvalidArgumentException('Resort family requires one country parent record');
    }
    $countryPath = v2_seo_registry_path($countryRecord['path'] ?? '');
    $countryState = is_array($countryRecord['data']['search_state'] ?? null)
        ? $countryRecord['data']['search_state'] : [];
    $countryId = (int)($countryState['country'] ?? 0);
    if ($countryId <= 0) throw new InvalidArgumentException('Resort family country identity must be positive');
    if (!$resortRecords) throw new InvalidArgumentException('Resort family requires at least one resort record');

    $records = [$countryRecord];
    $relations = [];
    $seenPaths = [];
    $seenRegions = [];
    foreach ($resortRecords as $record) {
        if (!is_array($record) || ($record['type'] ?? '') !== 'resort') {
            throw new InvalidArgumentException('Resort family accepts resort records only');
        }
        $path = v2_seo_registry_path($record['path'] ?? '');
        if (isset($seenPaths[$path])) throw new InvalidArgumentException('Duplicate resort path: ' . $path);
        $seenPaths[$path] = true;
        if (!str_starts_with($path, rtrim($countryPath, '/') . '/')) {
            throw new InvalidArgumentException('Resort path is outside country parent: ' . $path);
        }
        $state = is_array($record['data']['search_state'] ?? null)
            ? $record['data']['search_state'] : [];
        $recordCountry = (int)($state['country'] ?? 0);
        $regionId = (int)($state['region'] ?? 0);
        if ($recordCountry !== $countryId || $regionId <= 0) {
            throw new InvalidArgumentException('Resort search identity mismatch: ' . $path);
        }
        if (isset($seenRegions[$regionId])) {
            throw new InvalidArgumentException('Duplicate resort region identity: ' . $regionId);
        }
        $seenRegions[$regionId] = $path;
        $relations[$path] = ['parent' => $countryPath];
        $records[] = $record;
    }

    $catalog = v2_seo_content_catalog($records, $relations);
    $rows = v2_seo_page_launch_readiness($catalog);
    $resortRows = [];
    foreach ($rows as $row) {
        if (($row['type'] ?? '') === 'resort') $resortRows[] = $row;
    }
    if (count($resortRows) !== count($resortRecords)) {
        throw new InvalidArgumentException('Resort family readiness row count mismatch');
    }

    $ready = 0;
    $blocked = 0;
    $errorCounts = [];
    foreach ($resortRows as $row) {
        if (($row['ready_for_launch_review'] ?? false) === true) $ready++;
        else $blocked++;
        foreach (($row['errors'] ?? []) as $error) {
            $error = (string)$error;
            if ($error === '') continue;
            $errorCounts[$error] = ($errorCounts[$error] ?? 0) + 1;
        }
    }
    ksort($errorCounts);

    return [
        'state' => 'diagnostic_only_no_publication_mutation',
        'country_path' => $countryPath,
        'country_id' => $countryId,
        'resort_count' => count($resortRows),
        'unique_paths' => count($seenPaths),
        'unique_region_identities' => count($seenRegions),
        'ready' => $ready,
        'blocked' => $blocked,
        'error_counts' => $errorCounts,
        'rows' => $resortRows,
        'publication_candidates' => array_values($catalog['publication_candidates'] ?? []),
    ];
}
