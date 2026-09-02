<?php
declare(strict_types=1);
require_once __DIR__ . '/seo-seasonal-preview-readiness-v1.php';

function v2_seo_seasonal_preview_identity_record_matches_key(array $identity, string $pageKey): bool
{
    $parts = explode(':', $pageKey);
    $pageType = (string) ($identity['page_type'] ?? '');
    $countryId = (int) ($identity['country_id'] ?? 0);
    $regionId = $identity['region_id'] ?? null;
    $departureId = (int) ($identity['departure_id'] ?? 0);
    $year = (int) ($identity['year'] ?? 0);
    $month = (int) ($identity['month'] ?? 0);

    if (($parts[0] ?? '') === 'month') {
        return count($parts) === 4
            && $pageType === 'month'
            && $departureId === (int) ($parts[1] ?? 0)
            && $countryId === (int) ($parts[2] ?? 0)
            && $regionId === null
            && sprintf('%04d-%02d', $year, $month) === (string) ($parts[3] ?? '');
    }
    if (($parts[0] ?? '') === 'resort_month') {
        return count($parts) === 5
            && $pageType === 'resort_month'
            && $departureId === (int) ($parts[1] ?? 0)
            && $countryId === (int) ($parts[2] ?? 0)
            && (int) $regionId === (int) ($parts[3] ?? 0)
            && sprintf('%04d-%02d', $year, $month) === (string) ($parts[4] ?? '');
    }
    return false;
}

/**
 * Bind review-ready seasonal previews to a fresh exact production identity inventory.
 * This is evidence for review only and cannot authorize publication.
 */
function v2_seo_seasonal_preview_evidence_readiness(array $identityInventory, ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    $structural = v2_seo_seasonal_preview_readiness($nowEpoch);
    $blocked = [];
    $identitiesByKey = [];

    if (($identityInventory['state'] ?? '') !== 'review_only_seasonal_identity_inventory') {
        $blocked[] = ['code' => 'invalid_identity_inventory_state'];
    }
    if (($identityInventory['publication_allowed'] ?? true) !== false
        || ($identityInventory['copy_allowed'] ?? true) !== false
        || ($identityInventory['publication_candidates'] ?? null) !== []) {
        $blocked[] = ['code' => 'identity_inventory_crossed_review_boundary'];
    }

    $checkedAt = (int) ($identityInventory['evidence_checked_at_epoch'] ?? 0);
    $validUntil = (int) ($identityInventory['evidence_valid_until_epoch'] ?? 0);
    if (($identityInventory['evidence_clock_valid'] ?? false) !== true
        || $checkedAt <= 0
        || $checkedAt > $nowEpoch + 5
        || $validUntil <= $nowEpoch) {
        $blocked[] = ['code' => 'stale_or_invalid_identity_evidence_clock'];
    }

    foreach (($identityInventory['identities'] ?? []) as $index => $identity) {
        if (!is_array($identity)) {
            $blocked[] = ['code' => 'invalid_identity_record', 'index' => $index];
            continue;
        }
        $pageKey = trim((string) ($identity['page_key'] ?? ''));
        if ($pageKey === '') {
            $blocked[] = ['code' => 'missing_identity_page_key', 'index' => $index];
            continue;
        }
        if (isset($identitiesByKey[$pageKey])) {
            $blocked[] = ['code' => 'duplicate_identity_page_key', 'page_key' => $pageKey];
            continue;
        }
        if (($identity['state'] ?? '') !== 'fresh_review_identity'
            || ($identity['publication_allowed'] ?? true) !== false
            || ($identity['copy_allowed'] ?? true) !== false
            || (int) ($identity['evidence_checked_at_epoch'] ?? 0) <= 0
            || (int) ($identity['evidence_checked_at_epoch'] ?? 0) > $nowEpoch + 5
            || (int) ($identity['expires_at_epoch'] ?? 0) <= $nowEpoch
            || (int) ($identity['freshness_seconds'] ?? 0) <= 0
            || !v2_seo_seasonal_preview_identity_record_matches_key($identity, $pageKey)) {
            $blocked[] = ['code' => 'invalid_or_stale_identity_record', 'page_key' => $pageKey];
            continue;
        }
        $identitiesByKey[$pageKey] = $identity;
    }

    $pages = [];
    foreach (($structural['pages'] ?? []) as $previewKey => $page) {
        if (!is_array($page)) {
            $blocked[] = ['code' => 'invalid_structural_preview_record', 'preview_key' => (string) $previewKey];
            continue;
        }
        $pageKey = trim((string) ($page['page_key'] ?? ''));
        $identity = $identitiesByKey[$pageKey] ?? null;
        $identityReady = is_array($identity);
        if (!$identityReady) {
            $blocked[] = ['code' => 'missing_fresh_exact_identity', 'preview_key' => (string) $previewKey, 'page_key' => $pageKey];
        }
        $structuralScore = (int) ($page['score'] ?? 0);
        $score = $structuralScore === 100 && $identityReady ? 100 : min(80, $structuralScore);
        $pages[(string) $previewKey] = [
            'path' => (string) ($page['path'] ?? ''),
            'page_key' => $pageKey,
            'structural_score' => $structuralScore,
            'fresh_identity_ready' => $identityReady,
            'score' => $score,
            'review_ready' => $score === 100,
            'identity_evidence_checked_at_epoch' => $identityReady ? (int) ($identity['evidence_checked_at_epoch'] ?? 0) : 0,
            'identity_evidence_valid_until_epoch' => $identityReady ? (int) ($identity['expires_at_epoch'] ?? 0) : 0,
            'publication_allowed' => false,
            'indexation_allowed' => false,
            'sitemap_allowed' => false,
        ];
    }

    $allReady = $pages !== [] && $blocked === [] && ($structural['state'] ?? '') === 'review_ready';
    foreach ($pages as $page) {
        if (($page['review_ready'] ?? false) !== true) {
            $allReady = false;
            break;
        }
    }

    return [
        'state' => $allReady ? 'review_ready_with_fresh_identity_evidence' : 'blocked',
        'preview_count' => count($pages),
        'ready_count' => count(array_filter($pages, static fn(array $page): bool => ($page['review_ready'] ?? false) === true)),
        'all_review_ready' => $allReady,
        'evidence_checked_at_epoch' => $checkedAt,
        'evidence_valid_until_epoch' => $validUntil,
        'pages' => $pages,
        'blocked' => $blocked,
        'publication_candidates' => [],
        'publication_allowed' => false,
        'indexation_allowed' => false,
        'sitemap_allowed' => false,
        'route_launch_allowed' => false,
        'explicit_launch_approval_required' => true,
    ];
}
