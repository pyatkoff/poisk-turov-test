<?php
/**
 * Policy-driven review readiness for seasonal data coverage.
 *
 * This scorer never decides publication/indexation. A caller must supply an
 * explicit review policy; missing or invalid policy fails closed. The result is
 * only suitable for prioritising further editorial/data review.
 */
function v2_seo_seasonal_coverage_assess(array $dataReadiness, array $policy): array
{
    $errors = [];
    $countryId = (int)($policy['country_id'] ?? 0);
    $minMonth = (int)($policy['min_month_identities'] ?? 0);
    $minResortMonth = (int)($policy['min_resort_month_identities'] ?? -1);
    $minFreshness = (int)($policy['min_freshness_seconds'] ?? 0);
    $minOffers = (int)($policy['min_offers_per_snapshot'] ?? 0);

    if ($countryId <= 0) $errors[] = 'invalid_policy_country';
    if ($minMonth <= 0) $errors[] = 'invalid_policy_month_identity_floor';
    if ($minResortMonth < 0) $errors[] = 'invalid_policy_resort_month_identity_floor';
    if ($minFreshness <= 0) $errors[] = 'invalid_policy_freshness_floor';
    if ($minOffers <= 0) $errors[] = 'invalid_policy_offer_floor';
    if (($dataReadiness['state'] ?? '') !== 'review_only_data_readiness') $errors[] = 'invalid_data_readiness_state';

    $country = null;
    foreach (($dataReadiness['countries'] ?? []) as $row) {
        if (is_array($row) && (int)($row['country_id'] ?? 0) === $countryId) { $country = $row; break; }
    }
    if ($country === null) $errors[] = 'country_evidence_missing';

    $types = [];
    if ($country !== null) {
        foreach (($country['types'] ?? []) as $type) {
            if (!is_array($type)) continue;
            $name = (string)($type['page_type'] ?? '');
            if (in_array($name, ['month','resort_month'], true)) $types[$name] = $type;
        }
        if ((int)($country['snapshot_count'] ?? 0) <= 0) $errors[] = 'country_snapshot_empty';
        if ((int)($country['usable_snapshot_count'] ?? -1) !== (int)($country['snapshot_count'] ?? 0)) $errors[] = 'country_has_unusable_snapshots';
    }

    $monthIds = (int)($types['month']['identity_count'] ?? 0);
    $resortMonthIds = (int)($types['resort_month']['identity_count'] ?? 0);
    if ($monthIds < $minMonth) $errors[] = 'month_identity_coverage_below_policy';
    if ($resortMonthIds < $minResortMonth) $errors[] = 'resort_month_identity_coverage_below_policy';

    foreach (['month','resort_month'] as $typeName) {
        if (!isset($types[$typeName])) {
            if ($typeName === 'month' || $minResortMonth > 0) $errors[] = $typeName . '_evidence_missing';
            continue;
        }
        $type = $types[$typeName];
        if ((int)($type['snapshot_count'] ?? 0) !== (int)($type['usable_snapshot_count'] ?? -1)) $errors[] = $typeName . '_has_unusable_snapshots';
        if ((int)($type['min_freshness_seconds'] ?? 0) < $minFreshness) $errors[] = $typeName . '_freshness_below_policy';
        if ((int)($type['min_offer_count'] ?? 0) < $minOffers) $errors[] = $typeName . '_offer_depth_below_policy';
    }

    $errors = array_values(array_unique($errors));
    $checks = [
        'month_identity_coverage' => ['actual'=>$monthIds,'required'=>$minMonth,'pass'=>$monthIds >= $minMonth],
        'resort_month_identity_coverage' => ['actual'=>$resortMonthIds,'required'=>$minResortMonth,'pass'=>$resortMonthIds >= $minResortMonth],
        'freshness_floor_seconds' => $minFreshness,
        'offer_floor_per_snapshot' => $minOffers,
    ];
    $passed = 0;
    $total = 4;
    if ($monthIds >= $minMonth) $passed++;
    if ($resortMonthIds >= $minResortMonth) $passed++;
    $freshPass = true;
    $offerPass = true;
    foreach (['month','resort_month'] as $typeName) {
        if (!isset($types[$typeName])) {
            if ($typeName === 'month' || $minResortMonth > 0) { $freshPass=false; $offerPass=false; }
            continue;
        }
        if ((int)($types[$typeName]['min_freshness_seconds'] ?? 0) < $minFreshness) $freshPass=false;
        if ((int)($types[$typeName]['min_offer_count'] ?? 0) < $minOffers) $offerPass=false;
    }
    if ($freshPass) $passed++;
    if ($offerPass) $passed++;

    return [
        'state'=>$errors === [] ? 'review_ready' : 'review_blocked',
        'review_ready'=>$errors === [],
        'country_id'=>$countryId,
        'score'=>$total > 0 ? (int)round($passed * 100 / $total) : 0,
        'policy'=>[
            'min_month_identities'=>$minMonth,
            'min_resort_month_identities'=>$minResortMonth,
            'min_freshness_seconds'=>$minFreshness,
            'min_offers_per_snapshot'=>$minOffers,
        ],
        'checks'=>$checks,
        'publication_allowed'=>false,
        'feed_publish_allowed'=>false,
        'copy_allowed'=>false,
        'errors'=>$errors,
    ];
}
