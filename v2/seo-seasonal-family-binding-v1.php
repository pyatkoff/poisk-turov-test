<?php
require_once __DIR__ . '/seo-page-registry-v1.php';

/** Bind review-only seasonal identities to verified country/resort records. */
function v2_seo_seasonal_family_binding(array $countryRecord, array $resortRecords, array $identityInventory, ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    if (($countryRecord['type'] ?? '') !== 'country') throw new InvalidArgumentException('Seasonal binding requires one country record');
    $countryPath = v2_seo_registry_path($countryRecord['path'] ?? '');
    $countryState = is_array($countryRecord['data']['search_state'] ?? null) ? $countryRecord['data']['search_state'] : [];
    $countryId = (int)($countryState['country'] ?? 0);
    if ($countryId <= 0) throw new InvalidArgumentException('Seasonal binding country identity must be positive');

    if (($identityInventory['state'] ?? '') !== 'review_only_seasonal_identity_inventory') throw new InvalidArgumentException('Seasonal binding requires review-only identity inventory');
    if (($identityInventory['publication_allowed'] ?? true) !== false || ($identityInventory['copy_allowed'] ?? true) !== false || ($identityInventory['publication_candidates'] ?? null) !== []) {
        throw new InvalidArgumentException('Seasonal identity inventory crossed publication boundary');
    }
    $checkedAt=(int)($identityInventory['evidence_checked_at_epoch']??0);
    $validUntil=(int)($identityInventory['evidence_valid_until_epoch']??0);
    if (($identityInventory['evidence_clock_valid']??false)!==true || $checkedAt<=0 || $checkedAt>$nowEpoch+5 || $validUntil<=$nowEpoch) {
        throw new InvalidArgumentException('Seasonal identity inventory evidence clock is stale or invalid');
    }

    $regionParents = [];
    foreach ($resortRecords as $record) {
        if (!is_array($record) || ($record['type'] ?? '') !== 'resort') throw new InvalidArgumentException('Seasonal binding accepts resort records only');
        $path = v2_seo_registry_path($record['path'] ?? '');
        if (!str_starts_with($path, rtrim($countryPath, '/') . '/')) throw new InvalidArgumentException('Seasonal resort is outside country parent: ' . $path);
        $state = is_array($record['data']['search_state'] ?? null) ? $record['data']['search_state'] : [];
        $recordCountryId = (int)($state['country'] ?? 0);
        $regionId = (int)($state['region'] ?? 0);
        if ($recordCountryId !== $countryId || $regionId <= 0) throw new InvalidArgumentException('Seasonal resort identity mismatch: ' . $path);
        if (isset($regionParents[$regionId])) throw new InvalidArgumentException('Duplicate seasonal resort region identity: ' . $regionId);
        $regionParents[$regionId] = $path;
    }
    ksort($regionParents, SORT_NUMERIC);

    $bound = [];
    $blocked = [];
    $seenKeys = [];
    foreach (($identityInventory['identities'] ?? []) as $index => $identity) {
        if (!is_array($identity)) { $blocked[] = ['index'=>$index,'errors'=>['invalid_identity']]; continue; }
        $errors = [];
        $key = trim((string)($identity['page_key'] ?? ''));
        $pageType = (string)($identity['page_type'] ?? '');
        $identityCountryId = (int)($identity['country_id'] ?? 0);
        $regionId = isset($identity['region_id']) ? (int)$identity['region_id'] : null;
        $departureId = (int)($identity['departure_id'] ?? 0);
        $year = (int)($identity['year'] ?? 0);
        $month = (int)($identity['month'] ?? 0);
        $offerCount = (int)($identity['offer_count'] ?? 0);
        $freshness = (int)($identity['freshness_seconds'] ?? 0);
        $identityCheckedAt=(int)($identity['evidence_checked_at_epoch']??0);
        $identityExpires=(int)($identity['expires_at_epoch']??0);

        if (($identity['state'] ?? '') !== 'fresh_review_identity') $errors[] = 'identity_not_fresh_review_state';
        if ($key === '') $errors[] = 'missing_page_key'; elseif (isset($seenKeys[$key])) $errors[] = 'duplicate_page_key';
        if ($identityCountryId !== $countryId) $errors[] = 'country_identity_mismatch';
        if ($departureId <= 0) $errors[] = 'invalid_departure_identity';
        if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) $errors[] = 'invalid_period';
        if ($offerCount <= 0) $errors[] = 'empty_offer_depth';
        if ($freshness <= 0) $errors[] = 'stale_identity';
        if ($identityCheckedAt<=0||$identityCheckedAt>$nowEpoch+5||$identityExpires<=$nowEpoch) $errors[]='identity_evidence_expired_or_invalid';
        if (($identity['publication_allowed'] ?? true) !== false || ($identity['copy_allowed'] ?? true) !== false) $errors[] = 'identity_publication_boundary_crossed';

        $parentPath = null;
        if ($pageType === 'month') {
            if ($regionId !== null && $regionId > 0) $errors[] = 'unexpected_region_identity';
            $parentPath = $countryPath;
        } elseif ($pageType === 'resort_month') {
            if ($regionId === null || $regionId <= 0) $errors[] = 'invalid_region_identity';
            elseif (!isset($regionParents[$regionId])) $errors[] = 'unregistered_region_identity';
            else $parentPath = $regionParents[$regionId];
        } else $errors[] = 'invalid_page_type';

        if ($key !== '') $seenKeys[$key] = true;
        $errors = array_values(array_unique($errors));
        if ($errors !== []) { $blocked[] = ['index'=>$index,'page_key'=>$key,'errors'=>$errors]; continue; }
        $bound[] = [
            'state'=>'review_only_family_bound_identity','page_key'=>$key,'page_type'=>$pageType,'country_id'=>$countryId,
            'region_id'=>$regionId,'departure_id'=>$departureId,'year'=>$year,'month'=>$month,'parent_path'=>$parentPath,
            'offer_count'=>$offerCount,'evidence_checked_at_epoch'=>$identityCheckedAt,'expires_at_epoch'=>$identityExpires,'freshness_seconds'=>$freshness,
            'publication_allowed'=>false,'copy_allowed'=>false,
        ];
    }

    usort($bound, static fn(array $a,array $b): int => $a['page_key'] <=> $b['page_key']);
    return [
        'state'=>'review_only_seasonal_family_binding','country_id'=>$countryId,'country_path'=>$countryPath,
        'evidence_checked_at_epoch'=>$checkedAt,'evidence_valid_until_epoch'=>$validUntil,
        'registered_region_count'=>count($regionParents),'bound_count'=>count($bound),'blocked_count'=>count($blocked),
        'publication_candidates'=>[],'publication_allowed'=>false,'copy_allowed'=>false,'bound'=>$bound,'blocked'=>$blocked,
    ];
}
