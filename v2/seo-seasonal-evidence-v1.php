<?php
/**
 * Review-only evidence contract for future seasonal/data SEO layers.
 *
 * This module deliberately does not generate copy, routes, canonicals, robots,
 * sitemap entries or publication candidates. It only decides whether a monthly
 * first-party offer observation is safe to use as fresh operational evidence.
 *
 * Critically, offer observations are NOT evidence for climate, weather, beach
 * conditions, "best time to travel", discounts, ratings or hotel attributes.
 */

function v2_seo_seasonal_evidence_assess(array $row, ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    $errors = [];

    $source = trim((string)($row['source'] ?? ''));
    if ($source !== 'seo_offer_snapshot') $errors[] = 'unsupported_source';

    $scope = trim((string)($row['scope'] ?? ''));
    if (!in_array($scope, ['country_month', 'resort_month'], true)) $errors[] = 'invalid_scope';

    $countryId = (int)($row['country_id'] ?? 0);
    if ($countryId <= 0) $errors[] = 'invalid_country_identity';

    $regionId = isset($row['region_id']) ? (int)$row['region_id'] : null;
    if ($scope === 'resort_month' && ($regionId === null || $regionId <= 0)) $errors[] = 'invalid_region_identity';
    if ($scope === 'country_month' && $regionId !== null && $regionId > 0) $errors[] = 'unexpected_region_identity';

    $year = (int)($row['year'] ?? 0);
    $month = (int)($row['month'] ?? 0);
    if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) $errors[] = 'invalid_period';

    $offerCount = (int)($row['offer_count'] ?? 0);
    if ($offerCount <= 0) $errors[] = 'empty_evidence';

    $observedAt = v2_seo_seasonal_evidence_epoch($row['observed_at'] ?? null);
    $expiresAt = v2_seo_seasonal_evidence_epoch($row['expires_at'] ?? null);
    if ($observedAt === null) $errors[] = 'invalid_observed_at';
    if ($expiresAt === null) $errors[] = 'invalid_expires_at';
    if ($observedAt !== null && $expiresAt !== null && $expiresAt <= $observedAt) $errors[] = 'invalid_freshness_window';
    if ($observedAt !== null && $observedAt > $nowEpoch + 300) $errors[] = 'future_observation';
    if ($expiresAt !== null && $expiresAt <= $nowEpoch) $errors[] = 'stale_evidence';

    $pageKey = trim((string)($row['page_key'] ?? ''));
    $expectedKey = null;
    if ($countryId > 0 && $year > 0 && $month > 0) {
        $monthKey = sprintf('%04d-%02d', $year, $month);
        if ($scope === 'country_month') $expectedKey = 'month:' . (int)($row['departure_id'] ?? 0) . ':' . $countryId . ':' . $monthKey;
        elseif ($scope === 'resort_month' && $regionId !== null) $expectedKey = 'resort_month:' . (int)($row['departure_id'] ?? 0) . ':' . $countryId . ':' . $regionId . ':' . $monthKey;
    }
    if ((int)($row['departure_id'] ?? 0) <= 0) $errors[] = 'invalid_departure_identity';
    if ($expectedKey === null || $pageKey !== $expectedKey) $errors[] = 'page_key_mismatch';

    $errors = array_values(array_unique($errors));
    return [
        'state' => $errors === [] ? 'fresh_operational_evidence' : 'blocked',
        'usable' => $errors === [],
        'source' => $source,
        'scope' => $scope,
        'page_key' => $pageKey,
        'country_id' => $countryId,
        'region_id' => $regionId,
        'departure_id' => (int)($row['departure_id'] ?? 0),
        'year' => $year,
        'month' => $month,
        'offer_count' => $offerCount,
        'observed_at_epoch' => $observedAt,
        'expires_at_epoch' => $expiresAt,
        'freshness_seconds' => $expiresAt !== null ? $expiresAt - $nowEpoch : null,
        'allowed_uses' => ['candidate_selection', 'freshness_gate', 'feed_input'],
        'forbidden_claims' => [
            'climate', 'weather', 'sea_temperature', 'best_time_to_travel',
            'discount', 'deal_quality', 'hotel_rating', 'hotel_attribute', 'availability_guarantee',
        ],
        'errors' => $errors,
    ];
}

function v2_seo_seasonal_evidence_epoch(mixed $value): ?int
{
    if (is_int($value)) return $value > 0 ? $value : null;
    if (is_float($value)) return $value > 0 ? (int)$value : null;
    $value = trim((string)$value);
    if ($value === '') return null;
    if (ctype_digit($value)) return (int)$value > 0 ? (int)$value : null;
    $epoch = strtotime($value);
    return $epoch === false ? null : $epoch;
}

function v2_seo_seasonal_evidence_from_snapshot(array $snapshot): array
{
    $type = (string)($snapshot['page_type'] ?? '');
    $dims = is_array($snapshot['dimensions'] ?? null) ? $snapshot['dimensions'] : [];
    return [
        'source' => 'seo_offer_snapshot',
        'scope' => $type === 'resort_month' ? 'resort_month' : ($type === 'month' ? 'country_month' : ''),
        'page_key' => (string)($snapshot['page_key'] ?? ''),
        'country_id' => (int)($dims['countryId'] ?? $snapshot['country_id'] ?? 0),
        'region_id' => isset($dims['regionId']) ? (int)$dims['regionId'] : (isset($snapshot['region_id']) ? (int)$snapshot['region_id'] : null),
        'departure_id' => (int)($dims['departureId'] ?? $snapshot['departure_id'] ?? 0),
        'year' => (int)($dims['year'] ?? $snapshot['departure_year'] ?? 0),
        'month' => (int)($dims['month'] ?? $snapshot['departure_month'] ?? 0),
        'offer_count' => (int)($snapshot['offer_count'] ?? (is_array($snapshot['offers'] ?? null) ? count($snapshot['offers']) : 0)),
        'observed_at' => $snapshot['observed_at'] ?? null,
        'expires_at' => $snapshot['expires_at'] ?? null,
    ];
}
