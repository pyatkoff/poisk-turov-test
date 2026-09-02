<?php
/** Aggregate review-only readiness for fresh month/resort-month snapshot rows. */
function v2_seo_data_readiness_summary(array $rows, array $requestedCountryIds): array
{
    $requestedCountryIds = array_values(array_unique(array_filter(array_map('intval', $requestedCountryIds), static fn(int $v): bool => $v > 0)));
    sort($requestedCountryIds, SORT_NUMERIC);
    $byCountry = [];
    $totalSnapshots = 0;
    $totalUsable = 0;

    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $countryId = (int)($row['country_id'] ?? 0);
        $pageType = (string)($row['page_type'] ?? '');
        if ($countryId <= 0 || !in_array($pageType, ['month','resort_month'], true)) continue;
        $snapshotCount = max(0, (int)($row['snapshot_count'] ?? 0));
        $usableCount = max(0, min($snapshotCount, (int)($row['usable_snapshot_count'] ?? 0)));
        $entry = [
            'page_type'=>$pageType,
            'snapshot_count'=>$snapshotCount,
            'identity_count'=>max(0, (int)($row['identity_count'] ?? 0)),
            'offer_count'=>max(0, (int)($row['offer_count'] ?? 0)),
            'min_offer_count'=>max(0, (int)($row['min_offer_count'] ?? 0)),
            'oldest_observed_at'=>(string)($row['oldest_observed_at'] ?? ''),
            'newest_observed_at'=>(string)($row['newest_observed_at'] ?? ''),
            'earliest_expires_at'=>(string)($row['earliest_expires_at'] ?? ''),
            'latest_expires_at'=>(string)($row['latest_expires_at'] ?? ''),
            'min_freshness_seconds'=>(int)($row['min_freshness_seconds'] ?? 0),
            'max_freshness_seconds'=>(int)($row['max_freshness_seconds'] ?? 0),
            'usable_snapshot_count'=>$usableCount,
            'all_unexpired_snapshots_usable'=>$snapshotCount > 0 && $snapshotCount === $usableCount,
        ];
        if (!isset($byCountry[$countryId])) {
            $byCountry[$countryId] = [
                'country_id'=>$countryId,
                'country_name'=>(string)($row['country_name'] ?? ''),
                'snapshot_count'=>0,
                'usable_snapshot_count'=>0,
                'types'=>[],
            ];
        }
        $byCountry[$countryId]['snapshot_count'] += $snapshotCount;
        $byCountry[$countryId]['usable_snapshot_count'] += $usableCount;
        $byCountry[$countryId]['types'][] = $entry;
        $totalSnapshots += $snapshotCount;
        $totalUsable += $usableCount;
    }
    ksort($byCountry, SORT_NUMERIC);
    foreach ($byCountry as &$country) usort($country['types'], static fn(array $a,array $b): int => $a['page_type'] <=> $b['page_type']);
    unset($country);

    $missingCountries = array_values(array_diff($requestedCountryIds, array_map('intval', array_keys($byCountry))));
    return [
        'state'=>'review_only_data_readiness',
        'requested_country_ids'=>$requestedCountryIds,
        'country_count'=>count($byCountry),
        'missing_country_ids'=>$missingCountries,
        'snapshot_count'=>$totalSnapshots,
        'usable_snapshot_count'=>$totalUsable,
        'blocked_snapshot_count'=>$totalSnapshots-$totalUsable,
        'all_unexpired_snapshots_usable'=>$totalSnapshots > 0 && $totalSnapshots === $totalUsable,
        'publication_allowed'=>false,
        'feed_publish_allowed'=>false,
        'copy_allowed'=>false,
        'countries'=>array_values($byCountry),
    ];
}
