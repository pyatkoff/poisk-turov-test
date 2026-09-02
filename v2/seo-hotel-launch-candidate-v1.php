<?php

/**
 * Build a proposal-only hotel-tour launch slice from an explicit reviewed path list.
 *
 * This helper intentionally does not auto-rank or publish pages. Every proposed path
 * must already have a 100/100 launch-readiness result and must be named explicitly
 * by the caller. The output is review metadata only and is not consumed by robots,
 * sitemap, canonical or route mounting.
 */
function v2_seo_hotel_launch_candidate_proposal(array $readinessRows, array $priorityPaths, int $maxTotal = 15): array
{
    if ($maxTotal < 1 || $maxTotal > 50) {
        throw new InvalidArgumentException('Hotel launch proposal maxTotal must be between 1 and 50');
    }

    $byPath = [];
    foreach ($readinessRows as $row) {
        if (!is_array($row)) continue;
        $path = trim((string)($row['path'] ?? ''));
        if ($path === '') continue;
        if (isset($byPath[$path])) {
            throw new InvalidArgumentException('Duplicate hotel launch-readiness path: ' . $path);
        }
        $byPath[$path] = $row;
    }

    $seen = [];
    $proposal = [];
    foreach ($priorityPaths as $path) {
        $path = trim((string)$path);
        if ($path === '' || isset($seen[$path])) {
            throw new InvalidArgumentException('Hotel launch proposal contains an empty or duplicate path');
        }
        $seen[$path] = true;

        $row = $byPath[$path] ?? null;
        if (!is_array($row)) {
            throw new InvalidArgumentException('Hotel launch proposal path is missing readiness evidence: ' . $path);
        }
        if (($row['ready_for_launch_review'] ?? false) !== true || (int)($row['score'] ?? 0) !== 100) {
            throw new InvalidArgumentException('Hotel launch proposal path is not 100/100 launch-ready: ' . $path);
        }
        if (count($proposal) >= $maxTotal) {
            throw new InvalidArgumentException('Hotel launch proposal exceeds explicit maximum size');
        }

        $proposal[] = [
            'path' => $path,
            'country_id' => (int)($row['country_id'] ?? 0),
            'hotel_id' => (int)($row['hotel_id'] ?? 0),
            'score' => 100,
            'state' => 'proposal_only_requires_launch_approval',
        ];
    }

    return $proposal;
}

/**
 * Build the small AnyTour Turkey/Maldives/Egypt pilot proposal without ranking.
 *
 * Buckets are explicit and ordered by the caller. Every path still must be 100/100
 * ready. This helper only adds country-balance and hard-size constraints on top of
 * the proposal-only gate; it has no publication consumers.
 */
function v2_seo_hotel_country_launch_slice_proposal(
    array $readinessRows,
    array $countryBuckets,
    array $requiredCountryIds = [4, 8, 1],
    int $maxPerCountry = 5,
    int $maxTotal = 15
): array {
    if ($maxPerCountry < 1 || $maxPerCountry > 10) {
        throw new InvalidArgumentException('Hotel launch country slice maxPerCountry must be between 1 and 10');
    }
    if ($maxTotal < 1 || $maxTotal > 30) {
        throw new InvalidArgumentException('Hotel launch country slice maxTotal must be between 1 and 30');
    }

    $required = [];
    foreach ($requiredCountryIds as $countryId) {
        $countryId = (int)$countryId;
        if ($countryId <= 0 || isset($required[$countryId])) {
            throw new InvalidArgumentException('Hotel launch country slice requires unique positive country IDs');
        }
        $required[$countryId] = true;
    }
    if (!$required) throw new InvalidArgumentException('Hotel launch country slice requires countries');

    $readinessCountryByPath = [];
    foreach ($readinessRows as $row) {
        if (!is_array($row)) continue;
        $path = trim((string)($row['path'] ?? ''));
        if ($path === '') continue;
        if (isset($readinessCountryByPath[$path])) {
            throw new InvalidArgumentException('Duplicate hotel launch-readiness path: ' . $path);
        }
        $readinessCountryByPath[$path] = (int)($row['country_id'] ?? 0);
    }

    $seenCountries = [];
    $flatPaths = [];
    $bucketCounts = [];
    foreach ($countryBuckets as $bucket) {
        if (!is_array($bucket)) throw new InvalidArgumentException('Hotel launch country slice bucket must be an array');
        $countryId = (int)($bucket['country_id'] ?? 0);
        $paths = is_array($bucket['paths'] ?? null) ? array_values($bucket['paths']) : [];
        if (!isset($required[$countryId]) || isset($seenCountries[$countryId])) {
            throw new InvalidArgumentException('Hotel launch country slice contains unexpected or duplicate country');
        }
        if (!$paths || count($paths) > $maxPerCountry) {
            throw new InvalidArgumentException('Hotel launch country slice country bucket is empty or exceeds cap');
        }
        $seenCountries[$countryId] = true;
        $bucketCounts[$countryId] = count($paths);
        foreach ($paths as $path) {
            $path = trim((string)$path);
            if ($path === '' || !array_key_exists($path, $readinessCountryByPath)) {
                throw new InvalidArgumentException('Hotel launch country slice path is missing readiness evidence: ' . $path);
            }
            if ($readinessCountryByPath[$path] !== $countryId) {
                throw new InvalidArgumentException('Hotel launch country slice path country mismatch: ' . $path);
            }
            $flatPaths[] = $path;
        }
    }

    if (array_diff_key($required, $seenCountries) || array_diff_key($seenCountries, $required)) {
        throw new InvalidArgumentException('Hotel launch country slice must include every required country exactly once');
    }
    if (count($flatPaths) > $maxTotal) {
        throw new InvalidArgumentException('Hotel launch country slice exceeds total cap');
    }

    $proposal = v2_seo_hotel_launch_candidate_proposal($readinessRows, $flatPaths, $maxTotal);
    $byCountry = [];
    foreach ($proposal as $row) {
        $countryId = (int)($row['country_id'] ?? 0);
        if (!isset($required[$countryId])) {
            throw new InvalidArgumentException('Hotel launch country slice proposal escaped required countries');
        }
        $byCountry[$countryId][] = $row;
    }
    foreach ($bucketCounts as $countryId => $expectedCount) {
        if (count($byCountry[$countryId] ?? []) !== $expectedCount) {
            throw new InvalidArgumentException('Hotel launch country slice path country mismatch');
        }
    }

    return [
        'state' => 'proposal_only_requires_launch_approval',
        'required_country_ids' => array_map('intval', array_keys($required)),
        'max_per_country' => $maxPerCountry,
        'max_total' => $maxTotal,
        'total' => count($proposal),
        'countries' => $byCountry,
        'proposal' => $proposal,
    ];
}
